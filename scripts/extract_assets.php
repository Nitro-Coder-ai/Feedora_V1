<?php
/**
 * Conservative inline CSS/JS extractor for the refactor.
 *
 *   php scripts/extract_assets.php <phpfile> <basename>
 *
 * - Extracts every <style>...</style> block's inner CSS into
 *   assets/css/<basename>.css (in document order). Replaces the FIRST style
 *   block with <link rel="stylesheet" href="assets/css/<basename>.css">; later
 *   style blocks are removed.
 * - Extracts every attribute-less <script>...</script> block that does NOT
 *   contain a PHP tag into assets/js/<basename>.js (document order). Replaces
 *   the FIRST such block with <script src="assets/js/<basename>.js" defer>;
 *   later ones are removed.
 * - Any <script> that contains "<?" (PHP) or has attributes (src=, type=, etc.)
 *   is LEFT UNTOUCHED and reported, so PHP->JS cases are handled by hand.
 *
 * Writes <phpfile>.refbak first. Idempotent-ish: refuses if a target asset file
 * already exists (so re-runs don't clobber hand-edited output).
 */

if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }
$file = $argv[1] ?? '';
$base = $argv[2] ?? '';
if ($file === '' || $base === '' || !is_file($file)) {
    fwrite(STDERR, "usage: php scripts/extract_assets.php <phpfile> <basename>\n");
    exit(1);
}

$src  = file_get_contents($file);
$orig = $src;

$cssPath = "assets/css/$base.css";
$jsPath  = "assets/js/$base.js";
@mkdir('assets/css', 0777, true);
@mkdir('assets/js', 0777, true);

// ---- styles -----------------------------------------------------------------
$cssParts = [];
$styleIdx = 0;
$src = preg_replace_callback('#[ \t]*<style\b[^>]*>(.*?)</style>\s*#is', function ($m) use (&$cssParts, &$styleIdx, $cssPath) {
    $cssParts[] = trim($m[1], "\r\n");
    if ($styleIdx++ === 0) {
        return "    <link rel=\"stylesheet\" href=\"$cssPath\">\n";
    }
    return '';
}, $src);

// ---- scripts ----------------------------------------------------------------
$jsParts = [];
$jsIdx = 0;
$skippedPhp = 0; $skippedAttr = 0;
// Only match bare <script> (no attributes). Attributed scripts won't match \>.
$src = preg_replace_callback('#[ \t]*<script>(.*?)</script>\s*#is', function ($m) use (&$jsParts, &$jsIdx, &$skippedPhp, $jsPath) {
    $inner = $m[1];
    if (strpos($inner, '<?') !== false) {       // PHP inside -> leave inline
        $skippedPhp++;
        return $m[0];
    }
    $jsParts[] = trim($inner, "\r\n");
    if ($jsIdx++ === 0) {
        return "    <script src=\"$jsPath\" defer></script>\n";
    }
    return '';
}, $src);

if (!$cssParts && !$jsParts) {
    echo "Nothing extractable in $file (styles=0, pure-js scripts=0).\n";
    echo "PHP-containing scripts skipped: $skippedPhp\n";
    exit(0);
}

// Guard against clobbering hand-edited assets.
foreach ([[$cssPath, $cssParts], [$jsPath, $jsParts]] as [$p, $parts]) {
    if ($parts && is_file($p)) {
        fwrite(STDERR, "ERROR: $p already exists; refusing to overwrite. Remove it to re-run.\n");
        exit(2);
    }
}

copy($file, $file . '.refbak');

if ($cssParts) {
    file_put_contents($cssPath,
        "/* Extracted from $file inline <style> (refactor). */\n\n" .
        implode("\n\n", $cssParts) . "\n");
}
if ($jsParts) {
    file_put_contents($jsPath,
        "/* Extracted from $file inline <script> (refactor). */\n\n" .
        implode("\n\n", $jsParts) . "\n");
}
file_put_contents($file, $src);

echo "OK $file\n";
echo "  styles extracted: " . count($cssParts) . " -> $cssPath\n";
echo "  pure-js scripts extracted: " . count($jsParts) . " -> $jsPath\n";
echo "  PHP-containing scripts left inline (handle manually): $skippedPhp\n";
