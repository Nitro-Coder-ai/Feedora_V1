<?php
/**
 * Reversible media backfill: local uploads/ -> Cloudinary.
 *
 * Scans media columns for local "uploads/..." paths, uploads each existing file
 * to Cloudinary, and updates the DB row with the returned secure HTTPS URL.
 * Local files are KEPT during migration. A manifest records every migration so
 * that (a) cleanup can verify before deleting, and (b) you can roll back.
 *
 * Usage (run from anywhere; CLI only):
 *   php scripts/backfill_to_cloudinary.php             # migrate, keep local files
 *   php scripts/backfill_to_cloudinary.php --dry-run   # report only, no changes
 *   php scripts/backfill_to_cloudinary.php --cleanup    # delete local files that
 *                                                       # are confirmed migrated
 *   php scripts/backfill_to_cloudinary.php --table=POSTS   # limit to one table
 *
 * Reversibility:
 *   - Migration never deletes; the original file stays on disk.
 *   - The manifest (scripts/backfill_manifest.json) maps old local path ->
 *     {table, pk_column, pk, db_column, cloudinary_url}. Cleanup deletes a local
 *     file ONLY after re-reading the DB row and confirming it now equals that
 *     Cloudinary URL. If anything is out of sync, the file is left untouched.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script must be run from the command line.\n");
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/config/config.php';
require_once $projectRoot . '/includes/CloudinaryService.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    fwrite(STDERR, "ERROR: database connection (\$pdo) not available from config/config.php\n");
    exit(1);
}

// ---- CLI args ---------------------------------------------------------------
$opts        = getopt('', ['dry-run', 'cleanup', 'table::']);
$dryRun      = array_key_exists('dry-run', $opts);
$cleanup     = array_key_exists('cleanup', $opts);
$onlyTable   = isset($opts['table']) ? strtoupper($opts['table']) : null;
$manifestPath = __DIR__ . '/backfill_manifest.json';

// (table, primary-key column, media column, Cloudinary folder)
$targets = [
    ['table' => 'POSTS',   'pk' => 'POSTS_ID',   'col' => 'IMAGE_URL',     'folder' => 'feedora/posts'],
    ['table' => 'RECIPES', 'pk' => 'RECIPES_ID', 'col' => 'PHOTO_URL',     'folder' => 'feedora/recipes'],
    ['table' => 'USERS',   'pk' => 'USER_ID',    'col' => 'PROFILE_IMAGE', 'folder' => 'feedora/profiles'],
    ['table' => 'USERS',   'pk' => 'USER_ID',    'col' => 'BANNER_IMAGE',  'folder' => 'feedora/banners'],
    ['table' => 'STORIES', 'pk' => 'STORIES_ID', 'col' => 'IMAGE_URL',     'folder' => 'feedora/stories'],
    ['table' => 'MESSAGES','pk' => 'MESSAGE_ID', 'col' => 'IMAGE_URL',     'folder' => 'feedora/messages'],
];
if ($onlyTable) {
    $targets = array_values(array_filter($targets, fn($t) => $t['table'] === $onlyTable));
    if (!$targets) {
        fwrite(STDERR, "ERROR: unknown --table=$onlyTable\n");
        exit(1);
    }
}

function isLocalUploadPath(?string $v): bool
{
    if ($v === null || $v === '') return false;
    if (stripos($v, 'http://') === 0 || stripos($v, 'https://') === 0) return false;
    return strpos($v, 'uploads/') !== false;
}

function loadManifest(string $path): array
{
    if (!is_file($path)) return [];
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function saveManifest(string $path, array $manifest): void
{
    file_put_contents($path, json_encode(array_values($manifest), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

// =============================================================================
// CLEANUP MODE
// =============================================================================
if ($cleanup) {
    $manifest = loadManifest($manifestPath);
    if (!$manifest) {
        echo "Nothing to clean up: manifest is empty or missing ($manifestPath).\n";
        exit(0);
    }

    $deleted = 0; $kept = 0; $missing = 0;
    foreach ($manifest as &$entry) {
        if (!empty($entry['deleted'])) { continue; }

        $localRel = $entry['local_path'];
        $localAbs = $GLOBALS['projectRoot'] . '/' . ltrim($localRel, '/');

        // Re-confirm the DB row now holds the verified Cloudinary URL.
        $sql = "SELECT {$entry['db_column']} FROM {$entry['table']} WHERE {$entry['pk_column']} = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$entry['pk']]);
        $current = $stmt->fetchColumn();

        if ($current !== $entry['cloudinary_url']) {
            echo "KEEP  {$entry['table']}#{$entry['pk']} {$entry['db_column']}: DB value does not match migrated URL; not deleting $localRel\n";
            $kept++;
            continue;
        }
        if (!is_file($localAbs)) {
            echo "SKIP  local file already gone: $localRel\n";
            $entry['deleted'] = true;
            $missing++;
            continue;
        }
        if ($dryRun) {
            echo "DRY   would delete $localRel (confirmed migrated)\n";
            continue;
        }
        if (@unlink($localAbs)) {
            echo "DEL   $localRel\n";
            $entry['deleted'] = true;
            $deleted++;
        } else {
            echo "FAIL  could not delete $localRel\n";
            $kept++;
        }
    }
    unset($entry);

    if (!$dryRun) saveManifest($manifestPath, $manifest);
    echo "\nCleanup complete. Deleted: $deleted, kept: $kept, already-missing: $missing.\n";
    exit(0);
}

// =============================================================================
// MIGRATION MODE (default) — never deletes local files
// =============================================================================
echo ($dryRun ? "[DRY RUN] " : "") . "Scanning for local upload paths to migrate...\n";

if (!$dryRun && !CloudinaryService::isConfigured()) {
    fwrite(STDERR, "ERROR: Cloudinary is not configured (credentials/cURL). Aborting.\n");
    exit(1);
}

$manifest = loadManifest($manifestPath);
// Index existing manifest entries to avoid duplicates.
$seen = [];
foreach ($manifest as $e) {
    $seen[$e['table'] . '|' . $e['db_column'] . '|' . $e['pk']] = true;
}

$migrated = 0; $skipped = 0; $failed = 0;

foreach ($targets as $t) {
    $sql = "SELECT {$t['pk']} AS pk, {$t['col']} AS val FROM {$t['table']} WHERE {$t['col']} LIKE 'uploads/%' OR {$t['col']} LIKE '%/uploads/%'";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $val = $row['val'];
        if (!isLocalUploadPath($val)) { continue; }

        $localAbs = $projectRoot . '/' . ltrim($val, '/');
        if (!is_file($localAbs)) {
            echo "MISS  {$t['table']}#{$row['pk']} {$t['col']}: local file not found ($val)\n";
            $skipped++;
            continue;
        }

        $key = $t['table'] . '|' . $t['col'] . '|' . $row['pk'];
        if (isset($seen[$key])) { echo "SEEN  $key already in manifest\n"; $skipped++; continue; }

        if ($dryRun) {
            echo "DRY   would migrate {$t['table']}#{$row['pk']} {$t['col']}: $val\n";
            $migrated++;
            continue;
        }

        $cloud = CloudinaryService::upload($localAbs, $t['folder']);
        if (!$cloud['success']) {
            echo "FAIL  {$t['table']}#{$row['pk']} {$t['col']}: {$cloud['error']}\n";
            $failed++;
            continue;
        }

        try {
            $pdo->beginTransaction();
            $upd = $pdo->prepare("UPDATE {$t['table']} SET {$t['col']} = ? WHERE {$t['pk']} = ?");
            $upd->execute([$cloud['url'], $row['pk']]);
            $pdo->commit();
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo "FAIL  DB update {$t['table']}#{$row['pk']}: " . $ex->getMessage() . "\n";
            $failed++;
            continue;
        }

        // Record migration (local file intentionally kept on disk).
        $manifest[] = [
            'table'          => $t['table'],
            'pk_column'      => $t['pk'],
            'pk'             => $row['pk'],
            'db_column'      => $t['col'],
            'local_path'     => $val,
            'cloudinary_url' => $cloud['url'],
            'migrated_at'    => date('c'),
            'deleted'        => false,
        ];
        $seen[$key] = true;
        echo "OK    {$t['table']}#{$row['pk']} {$t['col']} -> {$cloud['url']}\n";
        $migrated++;
    }
}

if (!$dryRun) saveManifest($manifestPath, $manifest);

echo "\nMigration complete. " . ($dryRun ? "Would migrate" : "Migrated") . ": $migrated, skipped: $skipped, failed: $failed.\n";
echo "Local files were KEPT. Run with --cleanup to delete confirmed-migrated files.\n";
exit($failed > 0 ? 2 : 0);
