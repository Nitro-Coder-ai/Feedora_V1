<?php
/**
 * Sample configuration — copy to config.php and fill in real values.
 *
 *   cp config/config.sample.php config/config.php
 *
 * config.php is git-ignored so secrets never enter version control.
 * Prefer environment variables in production (getenv()) over literals.
 */

// ---- Database ---------------------------------------------------------------
$servername = getenv('FEEDORA_DB_HOST') ?: "localhost";
$username    = getenv('FEEDORA_DB_USER') ?: "root";
$password    = getenv('FEEDORA_DB_PASS') ?: "CHANGE_ME";          // do NOT commit a real password
$dbname      = getenv('FEEDORA_DB') ?: "Feedora";

try {
    $dsn = "mysql:host=$servername;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    $connectionError = $e->getMessage();
}

// ---- Cloudinary (get values from the Cloudinary dashboard) ------------------
define('CLOUDINARY_CLOUD_NAME', getenv('CLOUDINARY_CLOUD_NAME') ?: 'your_cloud_name');
define('CLOUDINARY_API_KEY',    getenv('CLOUDINARY_API_KEY')    ?: 'your_api_key');
define('CLOUDINARY_API_SECRET', getenv('CLOUDINARY_API_SECRET') ?: 'your_api_secret');

// NOTE: the asset() helper and any other non-secret config live in config.php.
?>
