<?php
/**
 * Feedora shared helpers (procedural, no Composer).
 *
 * Loaded by config/config.php (see config/config.sample.php). Every function is
 * wrapped in function_exists() so an older private config.php that already
 * defines some of them keeps working.
 *
 *   asset()               cache-busted URL for a local CSS/JS file (prefers *.min.*)
 *   csrf_token()          the session's CSRF token (sent by assets/js/csrf.js)
 *   csrf_field()          hidden <input name="csrf_token"> for HTML forms
 *   csrf_verify()         rejects state-changing requests without a valid token
 *   rate_limit_exceeded() / rate_limit_record()   simple file-based throttling
 *   safe_image_ext()      validated image extension from the file's real content
 *   feedora_is_placeholder()  true for empty / "PASTE_YOUR_..." config values
 */

if (!function_exists('feedora_is_placeholder')) {
    /** True when a config value is empty or still the template placeholder. */
    function feedora_is_placeholder($value): bool
    {
        $v = trim((string) $value);
        return $v === '' || stripos($v, 'PASTE_YOUR_') === 0;
    }
}

if (!function_exists('asset')) {
    /**
     * URL for a local asset relative to the project root, e.g. asset('Home.css').
     * Serves the minified sibling (Home.min.css) when it exists and appends
     * ?v=<filemtime> so browsers fetch the new file after every change.
     */
    function asset(string $path): string
    {
        $root = dirname(__DIR__);
        $min = preg_replace('/\.(css|js)$/', '.min.$1', $path);
        $use = ($min !== $path && is_file($root . '/' . $min)) ? $min : $path;
        $mtime = @filemtime($root . '/' . $use);
        return $use . ($mtime ? '?v=' . $mtime : '');
    }
}

if (!function_exists('feedora_session')) {
    /** Start the session if a page has not done so yet (and output has not started). */
    function feedora_session(): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        feedora_session();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
    }
}

if (!function_exists('csrf_verify')) {
    /**
     * Call at the top of any page that handles POST/PUT/PATCH/DELETE.
     * Accepts the token from the X-CSRF-Token header (AJAX via csrf.js) or the
     * csrf_token form field (csrf_field()). GET/HEAD requests pass through.
     */
    function csrf_verify(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }
        feedora_session();
        $expected = $_SESSION['csrf_token'] ?? '';
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
        if ($expected !== '' && is_string($sent) && hash_equals($expected, $sent)) {
            return;
        }
        http_response_code(403);
        $wantsJson = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
            || !empty($_SERVER['HTTP_X_CSRF_TOKEN'])
            || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
        if ($wantsJson) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Your session expired. Please reload the page and try again.']);
        } else {
            echo 'Your session expired. Please go back, reload the page and try again.';
        }
        exit;
    }
}

if (!function_exists('feedora_rate_file')) {
    function feedora_rate_file(string $key): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'feedora_ratelimit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        return $dir . DIRECTORY_SEPARATOR . sha1($key) . '.json';
    }
}

if (!function_exists('rate_limit_exceeded')) {
    /** True when $key has $max or more recorded attempts within the last $window seconds. */
    function rate_limit_exceeded(string $key, int $max, int $window): bool
    {
        $file = feedora_rate_file($key);
        $hits = is_file($file) ? (json_decode((string) @file_get_contents($file), true) ?: []) : [];
        $since = time() - $window;
        $recent = array_filter($hits, static fn($t) => is_int($t) && $t > $since);
        return count($recent) >= $max;
    }
}

if (!function_exists('rate_limit_record')) {
    /** Record one attempt for $key; entries older than $window seconds are dropped. */
    function rate_limit_record(string $key, int $window): void
    {
        $file = feedora_rate_file($key);
        $fh = @fopen($file, 'c+');
        if (!$fh) {
            return; // throttling is best-effort; never break the request
        }
        flock($fh, LOCK_EX);
        $hits = json_decode((string) stream_get_contents($fh), true) ?: [];
        $since = time() - $window;
        $hits = array_values(array_filter($hits, static fn($t) => is_int($t) && $t > $since));
        $hits[] = time();
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($hits));
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}

if (!function_exists('safe_image_ext')) {
    /**
     * Extension (no dot) for a real JPG/PNG/GIF/WEBP image, judged by the file's
     * content, never by its name. Returns null for anything else.
     */
    function safe_image_ext(string $tmpPath): ?string
    {
        if ($tmpPath === '' || !is_file($tmpPath)) {
            return null;
        }
        $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        $mime = null;
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $fi ? finfo_file($fi, $tmpPath) : null;
            if ($fi) {
                finfo_close($fi);
            }
        }
        $info = @getimagesize($tmpPath);
        if ($mime === null && $info) {
            $mime = $info['mime'] ?? null;
        }
        if (!$info || !isset($map[$mime])) {
            return null;
        }
        return $map[$mime];
    }
}
