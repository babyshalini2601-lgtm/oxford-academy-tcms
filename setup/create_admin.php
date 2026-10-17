<?php
// Create the first administrator from the command line.
// Example: php setup/create_admin.php admin 'Admin@2026'
// This is not a web page. Apache blocks the setup folder.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is for the command line only.\n");
}

require_once dirname(__DIR__) . '/config/db.php';

$username = $argv[1] ?? '';
$password = $argv[2] ?? '';

if ($username === '' || $password === '') {
    fwrite(STDERR, "Usage: php setup/create_admin.php <username> <password>\n");
    exit(1);
}

if (!password_long_enough($password)) {
    fwrite(STDERR, "Password must be at least " . PASSWORD_MIN_LENGTH . " characters.\n");
    exit(1);
}

$pdo = get_db_connection();

$already = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
if ((int) $already > 0) {
    echo "An administrator already exists. Nothing was changed.\n";
    exit(0);
}

$taken = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
$taken->execute([':username' => $username]);
if ((int) $taken->fetchColumn() > 0) {
    fwrite(STDERR, "That username is already taken.\n");
    exit(1);
}

$stmt = $pdo->prepare(
    "INSERT INTO users (username, password_hash, role, linked_id, is_active)
     VALUES (:username, :password_hash, 'admin', NULL, 1)"
);
$stmt->execute([
    ':username'      => $username,
    ':password_hash' => password_hash($password, PASSWORD_BCRYPT),
]);

echo "Administrator created. Username: {$username}\n";
echo "Use this account on the login page. Do not commit the password to Git.\n";
