<?php
// Admin can activate or deactivate accounts.
// Teacher and student accounts are created with their profiles later.
// There is no public registration page.

require_once __DIR__ . '/../config/db.php';

$required_role = 'admin';
require_once __DIR__ . '/../includes/auth_check.php';

$pdo        = get_db_connection();
$page_title = 'Manage User Accounts';
$message    = '';
$msg_type   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user_id'])) {
    $toggle_id  = (int) $_POST['toggle_user_id'];
    $new_status = (int) $_POST['new_status'];

    if (!csrf_ok()) {
        $message  = 'This form expired. Please try again.';
        $msg_type = 'error';
    } elseif ($new_status !== 0 && $new_status !== 1) {
        $message  = 'Invalid account status.';
        $msg_type = 'error';
    } elseif ($toggle_id === (int) $_SESSION['user_id']) {
        $message  = 'You cannot deactivate your own account.';
        $msg_type = 'error';
    } else {
        $target = $pdo->prepare("SELECT id, role, is_active FROM users WHERE id = :id");
        $target->execute([':id' => $toggle_id]);
        $target_user = $target->fetch();

        if (!$target_user) {
            $message  = 'That account was not found.';
            $msg_type = 'error';
        } elseif ($target_user['role'] === 'admin' && $new_status === 0) {
            $active_admins = (int) $pdo->query(
                "SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1"
            )->fetchColumn();

            if ($active_admins <= 1) {
                $message  = 'The last active administrator cannot be deactivated.';
                $msg_type = 'error';
            }
        }

        if ($message === '') {
            $stmt = $pdo->prepare(
                "UPDATE users SET is_active = :status WHERE id = :id"
            );
            $stmt->execute([':status' => $new_status, ':id' => $toggle_id]);

            $message  = $new_status === 1
                ? 'Account activated. The user can log in again.'
                : 'Account deactivated. Any existing session will be blocked on the next request.';
            $msg_type = 'success';
        }
    }
}

$users = $pdo->query(
    "SELECT
        u.id,
        u.username,
        u.role,
        u.is_active,
        u.created_at,
        COALESCE(t.full_name, s.full_name, 'Administrator') AS full_name
     FROM users u
     LEFT JOIN teachers t ON t.id = u.linked_id AND u.role = 'teacher'
     LEFT JOIN students s ON s.id = u.linked_id AND u.role = 'student'
     ORDER BY u.role, u.username"
)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">Manage User Accounts</h1>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo h($msg_type); ?>"><?php echo h($message); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-title">All User Accounts</div>
    <p style="color:#7f8c8d; font-size:13px; margin-bottom:16px;">
        Accounts are created by the administrator only. Deactivating an account keeps the history
        but stops that person from using the system.
    </p>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Role</th>
                    <th>Created</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr>
                    <td colspan="7" style="text-align:center; color:#7f8c8d;">No user accounts found.</td>
                </tr>
                <?php endif; ?>

                <?php foreach ($users as $index => $user): ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td><strong><?php echo h($user['username']); ?></strong></td>
                    <td><?php echo h($user['full_name']); ?></td>
                    <td>
                        <?php
                        $role_labels = [
                            'admin'   => 'Administrator',
                            'teacher' => 'Teacher',
                            'student' => 'Student / Parent',
                        ];
                        echo h($role_labels[$user['role']] ?? ucfirst($user['role']));
                        ?>
                    </td>
                    <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                    <td>
                        <?php if ($user['is_active']): ?>
                            <span class="badge badge-active">Active</span>
                        <?php else: ?>
                            <span class="badge badge-inactive">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ((int) $user['id'] !== (int) $_SESSION['user_id']): ?>
                            <form method="POST" action="" style="display:inline;">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="toggle_user_id" value="<?php echo (int) $user['id']; ?>">
                                <input type="hidden" name="new_status" value="<?php echo $user['is_active'] ? 0 : 1; ?>">
                                <button
                                    type="submit"
                                    class="btn btn-sm <?php echo $user['is_active'] ? 'btn-danger' : 'btn-success'; ?> confirm-action"
                                    data-message="<?php echo $user['is_active'] ? 'Deactivate this account?' : 'Activate this account?'; ?>"
                                >
                                    <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <span style="font-size:12px; color:#7f8c8d;">Your account</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
