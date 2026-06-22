<?php
/**
 * One-off migration: static UI assets (images/) -> Cloudinary (folder feedora/assets).
 *
 * Every file in images/ is uploaded to Cloudinary. A JSON manifest is written
 * mapping the old local path (e.g. "images/Feedora-logo.svg") to its new
 * Cloudinary delivery URL. Delivery URLs bake in compression/format:
 *   - raster (png/jpg/jpeg/gif/webp) -> f_webp,q_auto   (compressed WebP)
 *   - svg                            -> f_auto,q_auto    (kept as vector; webp
 *                                                         would rasterise logos
 *                                                         and break crispness)
 *
 * The local images/ folder is KEPT by this script. Delete it only after the
 * reference-replacement step and a visual verification pass.
 *
 * Usage (CLI only):
 *   php scripts/migrate_static_images.php            # upload + write manifest
 *   php scripts/migrate_static_images.php --dry-run  # report only, no uploads
 *
 * Idempotent: a local_path already present in the manifest is skipped, so the
 * script can be re-run to pick up failures without duplicating uploads.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script must be run from the command line.\n");
}

$projectRoot  = dirname(__DIR__);
require_once $projectRoot . '/config/config.php';
require_once $projectRoot . '/includes/CloudinaryService.php';

$opts   = getopt('', ['dry-run']);
$dryRun = array_key_exists('dry-run', $opts);

$imagesDir    = $projectRoot . '/images';
$manifestPath = __DIR__ . '/static_images_manifest.json';
$folder       = 'feedora/assets';
$exts         = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'];

if (!is_dir($imagesDir)) {
    fwrite(STDERR, "ERROR: images/ directory not found at $imagesDir\n");
    exit(1);
}
if (!$dryRun && !CloudinaryService::isConfigured()) {
    fwrite(STDERR, "ERROR: Cloudinary is not configured (credentials/cURL). Aborting.\n");
    exit(1);
}

// ---- load existing manifest (idempotency) -----------------------------------
$manifest = [];          // local_path => entry
$usedIds  = [];          // public_id => true (collision guard)
if (is_file($manifestPath)) {
    $j = json_decode((string)file_get_contents($manifestPath), true);
    if (is_array($j)) {
        foreach ($j as $e) {
            if (!empty($e['local_path'])) {
                $manifest[$e['local_path']] = $e;
                if (!empty($e['public_id'])) $usedIds[$e['public_id']] = true;
            }
        }
    }
}

/** Make a Cloudinary-safe, deterministic public id from a relative path + ext. */
function makePublicId(string $relNoExt, string $ext, array &$usedIds): string
{
    $slug = preg_replace('#[^A-Za-z0-9/_-]+#', '-', $relNoExt); // keep subdir slashes
    $slug = preg_replace('#-+#', '-', trim($slug, '-'));
    $base = ($slug === '' ? 'asset' : $slug) . '-' . $ext;
    $id   = $base;
    $n    = 1;
    while (isset($usedIds[$id])) { $id = $base . '-' . (++$n); }
    $usedIds[$id] = true;
    return $id;
}

// ---- collect files ----------------------------------------------------------
$rii = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($imagesDir, FilesystemIterator::SKIP_DOTS)
);
$files = [];
foreach ($rii as $f) {
    if ($f->isFile()) $files[] = $f->getPathname();
}
sort($files);

$migrated = 0; $skipped = 0; $failed = 0;

foreach ($files as $abs) {
    $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
    if (!in_array($ext, $exts, true)) { continue; }

    $rel       = str_replace('\\', '/', substr($abs, strlen($imagesDir) + 1));
    $localPath = 'images/' . $rel;

    if (isset($manifest[$localPath]) && !empty($manifest[$localPath]['cloudinary_url'])) {
        echo "SEEN  $localPath\n";
        $skipped++;
        continue;
    }

    $relNoExt = preg_replace('#\.[^./]+$#', '', $rel);
    $publicId = makePublicId($relNoExt, $ext, $usedIds);

    if ($dryRun) {
        echo "DRY   $localPath -> $folder/$publicId\n";
        $migrated++;
        continue;
    }

    $res = CloudinaryService::upload($abs, $folder, $publicId);
    if (!$res['success']) {
        echo "FAIL  $localPath: {$res['error']}\n";
        unset($usedIds[$publicId]); // free the id so a re-run can retry cleanly
        $failed++;
        continue;
    }

    $secure    = $res['url'];
    $transform = ($ext === 'svg') ? 'f_auto,q_auto' : 'f_webp,q_auto';
    $delivery  = preg_replace('#/upload/#', '/upload/' . $transform . '/', $secure, 1);

    $manifest[$localPath] = [
        'local_path'     => $localPath,
        'cloudinary_url' => $delivery,
        'raw_secure_url' => $secure,
        'public_id'      => $res['public_id'],
        'resource_type'  => $res['resource_type'],
        'format'         => $ext,
        'webp'           => ($ext !== 'svg'),
        'migrated_at'    => date('c'),
    ];
    echo "OK    $localPath -> $delivery\n";
    $migrated++;

    // Persist incrementally so a crash mid-run loses nothing.
    file_put_contents(
        $manifestPath,
        json_encode(array_values($manifest), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
}

if (!$dryRun) {
    file_put_contents(
        $manifestPath,
        json_encode(array_values($manifest), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
}

echo "\n" . ($dryRun ? "[DRY RUN] " : "")
   . "Done. migrated=$migrated, skipped=$skipped, failed=$failed.\n";
echo "Manifest: $manifestPath\n";
exit($failed > 0 ? 2 : 0);
