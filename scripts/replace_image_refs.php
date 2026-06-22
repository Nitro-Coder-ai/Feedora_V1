<?php
/**
 * Rewrites local images/... references to their Cloudinary URLs using the
 * manifest produced by migrate_static_images.php.
 *
 * Scans *.php, *.js, *.css across the project (excluding vcs/vendor/build and
 * the scripts/ + uploads/ dirs) and replaces each known "images/<file>" token
 * with its Cloudinary delivery URL. Matching is path-boundary aware so it never
 * rewrites an unrelated path such as "assets/images/x.svg".
 *
 * It only touches strings that map 1:1 to a migrated asset, so HTML structure,
 * CSS and layout are untouched — only the URL inside src/href/url() changes.
 *
 * Usage (CLI only):
 *   php scripts/replace_image_refs.php --dry-run   # report, change nothing
 *   php scripts/replace_image_refs.php             # apply replacements
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script must be run from the command line.\n");
}

$projectRoot  = dirname(__DIR__);
$manifestPath = __DIR__ . '/static_images_manifest.json';
$opts   = getopt('', ['dry-run']);
$dryRun = array_key_exists('dry-run', $opts);

if (!is_file($manifestPath)) {
    fwrite(STDERR, "ERROR: manifest not found: $manifestPath (run migrate_static_images.php first)\n");
    exit(1);
}
$entries = json_decode((string)file_get_contents($manifestPath), true);
if (!is_array($entries) || !$entries) {
    fwrite(STDERR, "ERROR: manifest is empty or invalid.\n");
    exit(1);
}

$map = [];
foreach ($entries as $e) {
    if (!empty($e['local_path']) && !empty($e['cloudinary_url'])) {
        $map[$e['local_path']] = $e['cloudinary_url'];
    }
}
// Longest local paths first so a longer name can't be shadowed by a prefix.
uksort($map, fn($a, $b) => strlen($b) - strlen($a));

$targetExts = ['php', 'js', 'css'];

$rii = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projectRoot, FilesystemIterator::SKIP_DOTS)
);

$changedFiles = 0; $totalRepl = 0;
$remaining    = []; // file => count of images/ tokens left unmapped (for reporting)

foreach ($rii as $f) {
    if (!$f->isFile()) continue;
    $path = $f->getPathname();
    $norm = str_replace('\\', '/', $path);

    if (preg_match('#/(\.git|node_modules|vendor|scripts|uploads)/#', $norm)) continue;
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($ext, $targetExts, true)) continue;

    $src  = file_get_contents($path);
    $orig = $src;
    $fileRepl = 0;

    foreach ($map as $local => $url) {
        // Optional ./ or / prefix; preceded by a non path-char so we match a
        // whole path token only (not "xyzimages/..." nor "assets/images/...").
        $pattern = '#(?<![A-Za-z0-9_./-])(?:\./|/)?' . preg_quote($local, '#') . '#';
        $src = preg_replace($pattern, $url, $src, -1, $c);
        $fileRepl += $c;
    }

    if ($fileRepl > 0 && $src !== $orig) {
        if (!$dryRun) file_put_contents($path, $src);
        echo ($dryRun ? "DRY  " : "UPD  ") . "$norm: $fileRepl replacement(s)\n";
        $changedFiles++;
        $totalRepl += $fileRepl;
    }

    // Report any images/ tokens still present (dead refs / non-migrated files).
    if (preg_match_all('#(?<![A-Za-z0-9_./-])(?:\./|/)?images/[^\s"\'()<>]+#', $src, $m)) {
        $remaining[$norm] = $m[0];
    }
}

echo "\n" . ($dryRun ? "[DRY RUN] " : "")
   . "Done. files changed=$changedFiles, replacements=$totalRepl.\n";

if ($remaining) {
    echo "\nNOTE: images/ references still present (not in manifest — likely dead refs with fallbacks or non-migrated files):\n";
    foreach ($remaining as $file => $toks) {
        echo "  $file\n";
        foreach (array_unique($toks) as $t) echo "      $t\n";
    }
}
