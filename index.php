<?php
/**
 * Root Router / Redirector
 * 
 * Redirects default root requests to the main application landing page (Home.php).
 */
// Absolute path (not a relative "Home.php") so that if this ever runs as a
// fallback for a missing nested asset, the redirect doesn't resolve relative to
// that path and loop (e.g. /images/x/foo.png -> images/x/Home.php -> ...).
$base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
header("Location: $base/Home.php");
exit();
?>
