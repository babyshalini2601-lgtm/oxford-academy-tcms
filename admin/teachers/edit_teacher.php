<?php
// ============================================================
// admin/teachers/edit_teacher.php — Edit teacher profile
//
// Admin can update profile details, reset the login password,
// and deactivate the teacher.
// Deactivating also sets the linked user account to inactive
// so the teacher cannot log in.
// ============================================================

require_once __DIR__ . '/../../config/db.php';

$required_role = 'admin';
require_once __DIR__ . '/../../includes/auth_check.php';

$pdo        = get_db_connection();
$page_title = 'Edit Teacher';
$errors     = [];
$message    = '';
$msg_type   = '';

$teacher_id = (int) ($_GET['id'] ?? 0);
if ($teacher_id === 0) {
    header('Location: ' . BASE_URL . '/admin/teachers/list_teachers.php');
    exit;
}

// Load the existing teacher record
$stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = :id");
$stmt->execute([':id' => $teacher_id]);
$teacher = $stmt->fetch();

if (!$teacher) {
    header('Location: ' . BASE_URL . '/admin/teachers/list_teachers.php');
    exit;
}

// Load the teacher's linked user account for the username field
$user_stmt = $pdo->prepare(
    "SELECT id, username, is_active FROM users WHERE linked_id = :id AND role = 'teacher'"
);
$user_stmt->execute([':id' => $teacher_id]);
$user_account = $user_stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $errors[] = 'This form expired. Please try again.';
    }

    $full_name    = trim($_POST['full_name']   ?? '');
    $phone        = trim($_POST['phone']       ?? '');
    $email        = trim($_POST['email']       ?? '');
    $address      = trim($_POST['address']     ?? '');
    $joined_date  = trim($_POST['joined_date'] ?? '');
    $is_active    = isset($_POST['is_active']) ? 1 : 0;
    $new_password = trim($_POST['new_password'] ?? '');

    // Validation
    if (empty($full_name)) {
        $errors[] = 'Full name is required.';
    }
    if (empty($joined_date)) {
        $errors[] = 'Joining date is required.';
    }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email address is not valid.';
    }
    if (!empty($new_password) && !password_long_enough($new_password)) {
        $errors[] = 'New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Update teacher profile
            $update = $pdo->prepare(
                "UPDATE teachers SET
                    full_name   = :name,
                    phone       = :phone,
                    email       = :email,
                    address     = :address,
                    joined_date = :joined,
                    is_active   = :active
                 WHERE id = :id"
            );
            $update->execute([
                ':name'    => $full_name,
                ':phone'   => !empty($phone)   ? $phone   : null,
                ':email'   => !empty($email)   ? $email   : null,
                ':address' => !empty($address) ? $address : null,
                ':joined'  => $joined_date,
                ':active'  => $is_active,
                ':id'      => $teacher_id,
            ]);

            // Sync the login account's active status with the teacher's status
            $pdo->prepare(
                "UPDATE users SET is_active = :active
                 WHERE linked_id = :linked AND role = 'teacher'"
            )->execute([':active' => $is_active, ':linked' => $teacher_id]);

            // Reset password only if a new one was provided
            if (!empty($new_password)) {
                $hashed = password_hash($new_password, PASSWORD_BCRYPT);
                $pdo->prepare(
                    "UPDATE users SET password_hash = :hash
                     WHERE linked_id = :linked AND role = 'teacher'"
                )->execute([':hash' => $hashed, ':linked' => $teacher_id]);
            }

            $pdo->commit();

            $message  = 'Teacher profile updated successfully.';
            $msg_type = 'success';

            // Reload the teacher record to show updated values
            $stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = :id");
            $stmt->execute([':id' => $teacher_id]);
            $teacher = $stmt->fetch();

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Teacher update failed: ' . $e->getMessage());
            $errors[] = 'The change was not saved. Please try again.';
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<h1 class="page-title">Edit Teacher</h1>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $msg_type; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as $err): echo '<div>' . htmlspecialchars($err) . '</div>'; endforeach; ?>
    </div>
<?php endif; ?>

<form method="POST" action="">
    <?php csrf_field(); ?>

    <div class="card">
        <div class="card-title">Teacher Profile</div>
        <div class="form-row">
            <div class="form-group">
                <label for="full_name">Full Name <span style="color:#e74c3c;">*</span></label>
                <input type="text" id="full_name" name="full_name"
                    value="<?php echo htmlspecialchars($teacher['full_name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="joined_date">Date Joined <span style="color:#e74c3c;">*</span></label>
                <input type="date" id="joined_date" name="joined_date"
                    value="<?php echo htmlspecialchars($teacher['joined_date']); ?>" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="text" id="phone" name="phone"
                    value="<?php echo htmlspecialchars($teacher['phone'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email"
                    value="<?php echo htmlspecialchars($teacher['email'] ?? ''); ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="address">Address</label>
            <textarea id="address" name="address" rows="2"><?php echo htmlspecialchars($teacher['address'] ?? ''); ?></textarea>
        </div>
        <div class="form-group" style="margin-top:4px;">
            <label>
                <input type="checkbox" name="is_active" value="1"
                    <?php echo $teacher['is_active'] ? 'checked' : ''; ?>>
                &nbsp;Teacher is Active (uncheck to deactivate login access)
            </label>
        </div>
    </div>

    <div class="card">
        <div class="card-title">Login Account</div>
        <div class="form-row">
            <div class="form-group">
                <label>Username</label>
                <input type="text" value="<?php echo htmlspecialchars($user_account['username'] ?? '—'); ?>"
                    disabled style="background:#f0f2f5; color:#7f8c8d;">
            </div>
            <div class="form-group">
                <label for="new_password">New Password (optional)</label>
                <input type="password" id="new_password" name="new_password"
                    placeholder="Leave blank to keep current password">
            </div>
        </div>
    </div>

    <div style="display:flex; gap:10px; margin-bottom:30px;">
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="<?php echo BASE_URL; ?>/admin/teachers/assign_classes.php?teacher_id=<?php echo $teacher_id; ?>"
           class="btn btn-secondary">Manage Assignments</a>
        <a href="<?php echo BASE_URL; ?>/admin/teachers/list_teachers.php"
           class="btn btn-secondary">Back to List</a>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
