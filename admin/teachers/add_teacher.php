<?php
// ============================================================
// admin/teachers/add_teacher.php — Register a new teacher
//
// Creates two records inside a single database transaction:
//   1. A row in 'teachers' with the profile details
//   2. A row in 'users' with role='teacher' and linked_id
//      pointing to the teacher's primary key
//
// This separation keeps login credentials in one table and
// profile data in another — following good normalization practice.
// ============================================================

require_once __DIR__ . '/../../config/db.php';

$required_role = 'admin';
require_once __DIR__ . '/../../includes/auth_check.php';

$pdo        = get_db_connection();
$page_title = 'Add New Teacher';
$errors     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $errors[] = 'This form expired. Please try again.';
    }

    $full_name   = trim($_POST['full_name']   ?? '');
    $phone       = trim($_POST['phone']       ?? '');
    $email       = trim($_POST['email']       ?? '');
    $address     = trim($_POST['address']     ?? '');
    $joined_date = trim($_POST['joined_date'] ?? '');
    $username    = trim($_POST['username']    ?? '');
    $password    = $_POST['password']          ?? '';

    // --- Validation ---
    if (empty($full_name)) {
        $errors[] = 'Full name is required.';
    }
    if (empty($joined_date)) {
        $errors[] = 'Joining date is required.';
    }
    if (empty($username)) {
        $errors[] = 'Username is required.';
    }
    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (!password_long_enough($password)) {
        $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email address is not valid.';
    }

    // Check that the chosen username is not already taken
    if (empty($errors)) {
        $check = $pdo->prepare("SELECT id FROM users WHERE username = :username");
        $check->execute([':username' => $username]);
        if ($check->fetch()) {
            $errors[] = 'The username "' . htmlspecialchars($username) . '" is already in use. Please choose a different one.';
        }
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        try {
            $pdo->beginTransaction();

            // Step 1: Insert the teacher profile
            $stmt = $pdo->prepare(
                "INSERT INTO teachers (full_name, phone, email, address, joined_date, is_active)
                 VALUES (:name, :phone, :email, :address, :joined, 1)"
            );
            $stmt->execute([
                ':name'    => $full_name,
                ':phone'   => !empty($phone)   ? $phone   : null,
                ':email'   => !empty($email)   ? $email   : null,
                ':address' => !empty($address) ? $address : null,
                ':joined'  => $joined_date,
            ]);

            $teacher_id = $pdo->lastInsertId();

            // Step 2: Create the login account.
            // linked_id connects this user account back to the teacher profile.
            $user_stmt = $pdo->prepare(
                "INSERT INTO users (username, password_hash, role, linked_id, is_active)
                 VALUES (:username, :password_hash, 'teacher', :linked_id, 1)"
            );
            $user_stmt->execute([
                ':username'      => $username,
                ':password_hash' => $hashed_password,
                ':linked_id'     => $teacher_id,
            ]);

            $pdo->commit();

            // After adding, redirect to assign_classes so admin can set up
            // the teacher's class assignments immediately
            header('Location: ' . BASE_URL . '/admin/teachers/assign_classes.php?teacher_id=' . $teacher_id . '&added=1');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Teacher create failed: ' . $e->getMessage());
            $errors[] = 'The teacher was not saved. Nothing was written. Please try again.';
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<h1 class="page-title">Add New Teacher</h1>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as $err): ?>
            <div><?php echo htmlspecialchars($err); ?></div>
        <?php endforeach; ?>
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
                    value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                    placeholder="Teacher's full name" required>
            </div>
            <div class="form-group">
                <label for="joined_date">Date Joined <span style="color:#e74c3c;">*</span></label>
                <input type="date" id="joined_date" name="joined_date"
                    value="<?php echo htmlspecialchars($_POST['joined_date'] ?? date('Y-m-d')); ?>" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="text" id="phone" name="phone"
                    value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                    placeholder="Contact number">
            </div>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email"
                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                    placeholder="Email address">
            </div>
        </div>
        <div class="form-group">
            <label for="address">Address</label>
            <textarea id="address" name="address" rows="2"
                placeholder="Home address"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
        </div>
    </div>

    <div class="card">
        <div class="card-title">Login Account</div>
        <p style="color:#7f8c8d; font-size:13px; margin-bottom:14px;">
            Choose a username and set an initial password for this teacher's login.
            The teacher can only log in once their account is active.
        </p>
        <div class="form-row">
            <div class="form-group">
                <label for="username">Username <span style="color:#e74c3c;">*</span></label>
                <input type="text" id="username" name="username"
                    value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                    placeholder="e.g., teacher.kumar" required>
            </div>
            <div class="form-group">
                <label for="password">Initial Password <span style="color:#e74c3c;">*</span></label>
                <input type="password" id="password" name="password"
                    placeholder="At least <?php echo PASSWORD_MIN_LENGTH; ?> characters" required>
            </div>
        </div>
    </div>

    <div style="display:flex; gap:10px; margin-bottom:30px;">
        <button type="submit" class="btn btn-primary">Save and Assign Classes</button>
        <a href="<?php echo BASE_URL; ?>/admin/teachers/list_teachers.php" class="btn btn-secondary">Cancel</a>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
