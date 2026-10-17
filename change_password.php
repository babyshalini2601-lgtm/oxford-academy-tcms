<?php
// Any logged-in role can change their own password.
// The current password must be correct. The new hash replaces the old one.

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_check.php';

$pdo        = get_db_connection();
$page_title = 'Change password';
$errors     = [];
$success    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $errors[] = 'This form expired. Please try again.';
    } else {
        $current     = $_POST['current_password'] ?? '';
        $new         = $_POST['new_password'] ?? '';
        $confirm     = $_POST['confirm_password'] ?? '';

        $user_stmt = $pdo->prepare(
            "SELECT password_hash FROM users WHERE id = :id AND is_active = 1"
        );
        $user_stmt->execute([':id' => $_SESSION['user_id']]);
        $user = $user_stmt->fetch();

        if (!$user || !password_verify($current, $user['password_hash'])) {
            $errors[] = 'Current password is not correct.';
        }
        if (!password_long_enough($new)) {
            $errors[] = 'New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
        }
        if ($new !== $confirm) {
            $errors[] = 'New password and confirmation do not match.';
        }
        if ($current !== '' && $new !== '' && hash_equals($current, $new)) {
            $errors[] = 'New password must be different from the current password.';
        }

        if (empty($errors)) {
            $update = $pdo->prepare(
                "UPDATE users SET password_hash = :password_hash WHERE id = :id"
            );
            $update->execute([
                ':password_hash' => password_hash($new, PASSWORD_BCRYPT),
                ':id'            => $_SESSION['user_id'],
            ]);
            $success = 'Password updated. Use the new password the next time you log in.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<h1 class="page-title">Change password</h1>

<?php if ($success !== ''): ?>
    <div class="alert alert-success"><?php echo h($success); ?></div>
<?php endif; ?>

<?php show_form_errors($errors); ?>

<div class="card" style="max-width:480px;">
    <form method="POST" action="">
        <?php csrf_field(); ?>

        <div class="form-group">
            <label for="current_password">Current password</label>
            <input type="password" id="current_password" name="current_password" required>
        </div>

        <div class="form-group">
            <label for="new_password">New password</label>
            <input type="password" id="new_password" name="new_password" minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" required>
            <small style="color:#7f8c8d;">At least <?php echo PASSWORD_MIN_LENGTH; ?> characters.</small>
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm new password</label>
            <input type="password" id="confirm_password" name="confirm_password" minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" required>
        </div>

        <button type="submit" class="btn btn-primary">Update password</button>
        <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
