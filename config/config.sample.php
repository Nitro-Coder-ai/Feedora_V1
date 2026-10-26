<?php
/**
 * Feedora configuration TEMPLATE.
 *
 *   1. Copy this file:   cp config/config.sample.php config/config.php
 *   2. Replace every PASTE_YOUR_..._HERE value below with your own value.
 *
 * config/config.php is git-ignored, so your keys never reach GitHub.
 * Environment variables (getenv) override the values in this file.
 * A value left as PASTE_YOUR_..._HERE counts as "not set", so the app still runs:
 * uploads are saved to the local uploads/ folder until Cloudinary is configured.
 */

// Shared helpers (asset(), CSRF protection, rate limiting, image checks)
require_once __DIR__ . '/../includes/helpers.php';

// ---- Database (MySQL) --------------------------------------------------------
// XAMPP default: user "root", empty password.  MAMP default: user "root", password "root".
$servername = getenv('FEEDORA_DB_HOST') ?: 'localhost';
$username   = getenv('FEEDORA_DB_USER') ?: 'root';
$password   = getenv('FEEDORA_DB_PASS') ?: 'PASTE_YOUR_DB_PASSWORD_HERE';
$dbname     = getenv('FEEDORA_DB')      ?: 'Feedora';
$dbport     = getenv('FEEDORA_DB_PORT') ?: '3306';

if (feedora_is_placeholder($password)) {
    $password = ''; // not set yet: try the XAMPP default (empty password)
}

try {
    $dsn = "mysql:host=$servername;port=$dbport;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    $connectionError = $e->getMessage();
}

// ---- Cloudinary (optional: image/video hosting) ------------------------------
// Free account: https://cloudinary.com  ->  Dashboard  ->  "API Keys".
// Leave the placeholders to store uploads locally in uploads/ instead.
define('CLOUDINARY_CLOUD_NAME', getenv('CLOUDINARY_CLOUD_NAME') ?: 'PASTE_YOUR_CLOUDINARY_CLOUD_NAME_HERE');
define('CLOUDINARY_API_KEY',    getenv('CLOUDINARY_API_KEY')    ?: 'PASTE_YOUR_CLOUDINARY_API_KEY_HERE');
define('CLOUDINARY_API_SECRET', getenv('CLOUDINARY_API_SECRET') ?: 'PASTE_YOUR_CLOUDINARY_API_SECRET_HERE');

// ---- Sign-in, sign-up, email verification, Google/Facebook login -------------
// Keys for these live in config/secrets.php (copy config/secrets.sample.php).
require_once __DIR__ . '/auth.php';
