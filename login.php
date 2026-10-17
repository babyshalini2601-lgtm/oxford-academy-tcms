<?php
// ============================================================
// login.php — User login page
//
// Handles login for all three roles: admin, teacher, student.
// On successful login, starts a PHP session and redirects the
// user to their role-specific dashboard.
// ============================================================

require_once __DIR__ . '/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If the user is already logged in, send them to their dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$error = '';
$info  = '';

if (isset($_GET['reason']) && $_GET['reason'] === 'expired') {
    $info = 'Your session expired after 30 minutes of inactivity. Please log in again.';
} elseif (isset($_GET['reason']) && $_GET['reason'] === 'disabled') {
    $info = 'This account is no longer active. Ask the administrator for help.';
}

// Process the login form when it is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!csrf_ok()) {
        $error = 'This form expired. Please try again.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $pdo = get_db_connection();

        // Fetch the user record by username.
        // We only allow active accounts to log in.
        $stmt = $pdo->prepare(
            "SELECT * FROM users WHERE username = :username AND is_active = 1"
        );
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        // password_verify() checks the submitted password against the
        // bcrypt hash stored in the database. It returns true or false.
        if ($user && password_verify($password, $user['password_hash'])) {

            // Regenerate the session ID on login to prevent session fixation attacks
            session_regenerate_id(true);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            $_SESSION['user_id']       = $user['id'];
            $_SESSION['role']          = $user['role'];
            $_SESSION['linked_id']     = $user['linked_id'];
            $_SESSION['last_activity'] = time();

            // Fetch the full name to display in the navigation bar.
            // Admin accounts have no linked profile, so we use 'Administrator'.
            // Teachers and students have records in their own tables.
            if ($user['role'] === 'admin') {
                $_SESSION['full_name'] = 'Administrator';

            } elseif ($user['role'] === 'teacher') {
                $t = $pdo->prepare("SELECT full_name FROM teachers WHERE id = :id");
                $t->execute([':id' => $user['linked_id']]);
                $teacher = $t->fetch();
                $_SESSION['full_name'] = $teacher['full_name'] ?? 'Teacher';

            } elseif ($user['role'] === 'student') {
                $s = $pdo->prepare("SELECT full_name FROM students WHERE id = :id");
                $s->execute([':id' => $user['linked_id']]);
                $student = $s->fetch();
                $_SESSION['full_name'] = $student['full_name'] ?? 'Student';
            }

            // Redirect to the correct dashboard based on role
            switch ($user['role']) {
                case 'admin':
                    header('Location: ' . BASE_URL . '/admin/dashboard.php');
                    break;
                case 'teacher':
                    header('Location: ' . BASE_URL . '/teacher/dashboard.php');
                    break;
                case 'student':
                    header('Location: ' . BASE_URL . '/student/dashboard.php');
                    break;
            }
            exit;

        } else {
            // Generic error message — do not reveal whether the username
            // exists or if only the password was wrong
            $error = 'Invalid username or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Oxford Academy</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>
<body>

<div class="login-wrapper">
    <div class="login-box">

        <div class="login-logo">
            <h2>Oxford Academy</h2>
            <p>Tuition Centre Management System</p>
        </div>

        <?php if (!empty($info)): ?>
            <div class="alert alert-info"><?php echo h($info); ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo h($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php csrf_field(); ?>
            <div class="form-group">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    value="<?php echo h($_POST['username'] ?? ''); ?>"
                    placeholder="Enter your username"
                    required
                    autofocus
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                >
            </div>

            <div class="form-group" style="margin-top: 24px;">
                <button type="submit" class="btn btn-primary" style="width:100%; padding: 11px;">
                    Login
                </button>
            </div>
        </form>

        <p style="text-align:center; font-size:12px; color:#95a5a6; margin-top:20px;">
            Accounts are created by the administrator only.
        </p>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>
</body>
</html>
