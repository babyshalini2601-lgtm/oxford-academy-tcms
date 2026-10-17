<?php
// Shared application settings and one PDO connection per page.

define('BASE_URL', '/oxford-academy-tcms');

date_default_timezone_set('Asia/Colombo');

function show_setup_error($message) {
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
    echo '<title>Setup needed</title></head>';
    echo '<body style="font-family:Arial,sans-serif;max-width:640px;margin:60px auto;padding:20px;">';
    echo '<h2>Oxford Academy TCMS</h2>';
    echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p>See the README setup steps. Database details stay in config/local.php, not in this file.</p>';
    echo '</body></html>';
    exit;
}

$local_config = __DIR__ . '/local.php';
if (!is_file($local_config)) {
    show_setup_error('config/local.php is missing. Copy config/local.php.example to config/local.php and run the database setup.');
}

require_once $local_config;

if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
    show_setup_error('config/local.php is incomplete. Use config/local.php.example as the template.');
}

function get_db_connection() {
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log('TCMS database connection failed: ' . $e->getMessage());
        show_setup_error('The system cannot connect to the database. Check config/local.php and make sure MySQL is running.');
    }
}

require_once dirname(__DIR__) . '/includes/helpers.php';
