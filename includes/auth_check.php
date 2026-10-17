<?php
// Include after config/db.php. Set $required_role before this file
// if the page belongs to one role only.
//
// Checks, in order:
//   1. the user has a session
//   2. they have not been idle for 30 minutes
//   3. the account is still active in the database
//   4. their role is allowed on this page
//
// Hiding a menu is not enough. This file runs on every protected page.

if (!defined('BASE_URL')) {
    require_once dirname(__DIR__) . '/config/db.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    redirect_to('/login.php');
}

if (isset($_SESSION['last_activity']) && session_is_idle($_SESSION['last_activity'])) {
    end_session();
    redirect_to('/login.php?reason=expired');
}

$_SESSION['last_activity'] = time();

$pdo = get_db_connection();
$account_stmt = $pdo->prepare(
    "SELECT id, role, is_active FROM users WHERE id = :id"
);
$account_stmt->execute([':id' => $_SESSION['user_id']]);
$account = $account_stmt->fetch();

if (!$account || (int) $account['is_active'] !== 1) {
    end_session();
    redirect_to('/login.php?reason=disabled');
}

$_SESSION['role'] = $account['role'];

if (isset($required_role) && $_SESSION['role'] !== $required_role) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
    echo '<title>Not Authorized</title></head><body style="font-family:Arial,sans-serif;text-align:center;padding:60px;">';
    echo '<h2 style="color:#c0392b;">You do not have permission to view this page.</h2>';
    echo '<p>Your menu only shows pages for your role, but the server also checks every request.</p>';
    echo '<p><a href="' . h(BASE_URL) . '/index.php">Go to your dashboard</a></p>';
    echo '</body></html>';
    exit;
}
