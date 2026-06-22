<?php
/**
 * Rewrites local CSS/JS references in root *.php to use asset(), which serves the
 * minified sibling and appends ?v=<filemtime> for cache-busting.
 *
 *   php scripts/cachebust_refs.php           # apply
 *   php scripts/cachebust_refs.php --dry-run # preview
 *
 * Idempotent: a ref already wrapped in <?php echo asset(...) ?> won't match again.
 * External URLs (https://…) and inline <script> blocks are left untouched.
 */
if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }
$dry  = in_array('--dry-run', $argv, true);
$root = dirname(__DIR__);

// href/src pointing at a local built asset (assets/css|js/*.css|js or the shared
// root files). The value must start with the path (not "<?php …"), so wrapped
// refs are skipped automatically.
$re = '#(\b(?:href|src)=)"(assets/(?:css|js)/[A-Za-z0-9_.\-]+\.(?:css|js)|Home\.css|Home\.js|fonts\.css)"#';

$total = 0; $fileCount = 0;
foreach (glob($root . '/*.php') as $f) {
    $src = file_get_contents($f);
    $new = preg_replace_callback($re, function ($m) {
        return $m[1] . '"<?php echo asset(\'' . $m[2] . '\'); ?>"';
    }, $src, -1, $c);
    if ($c > 0 && $new !== $src) {
        echo ($dry ? "DRY  " : "UPD  ") . basename($f) . ": $c ref(s)\n";
        if (!$dry) file_put_contents($f, $new);
        $total += $c; $fileCount++;
    }
}
echo "\n" . ($dry ? "[DRY] " : "") . "Rewrote $total ref(s) across $fileCount file(s).\n";
