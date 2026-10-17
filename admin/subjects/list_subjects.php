<?php
// ============================================================
// admin/subjects/list_subjects.php — Subject management
//
// Displays all subjects. Admin can add a new subject using the
// form at the bottom and deactivate existing ones.
// Subjects are the disciplines taught at Oxford Academy
// (e.g., Mathematics, Science, English).
// ============================================================

require_once __DIR__ . '/../../config/db.php';

$required_role = 'admin';
require_once __DIR__ . '/../../includes/auth_check.php';

$pdo        = get_db_connection();
$page_title = 'Manage Subjects';
$message    = '';
$msg_type   = '';

// --- Handle Add New Subject ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $subject_name = trim($_POST['subject_name'] ?? '');
    $description  = trim($_POST['description']  ?? '');

    if (!csrf_ok()) {
        $message  = 'This form expired. Please try again.';
        $msg_type = 'error';
    } elseif (empty($subject_name)) {
        $message  = 'Subject name is required.';
        $msg_type = 'error';
    } else {
        // Check that a subject with the same name does not already exist
        $check = $pdo->prepare("SELECT id FROM subjects WHERE subject_name = :name");
        $check->execute([':name' => $subject_name]);

        if ($check->fetch()) {
            $message  = 'A subject with this name already exists.';
            $msg_type = 'error';
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO subjects (subject_name, description, is_active)
                 VALUES (:name, :desc, 1)"
            );
            $stmt->execute([':name' => $subject_name, ':desc' => $description]);

            $message  = 'Subject "' . htmlspecialchars($subject_name) . '" added successfully.';
            $msg_type = 'success';
        }
    }
}

// --- Handle Deactivate / Activate ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle') {
    $subject_id = (int) $_POST['subject_id'];
    $new_status = (int) $_POST['new_status'];

    if (!csrf_ok()) {
        $message  = 'This form expired. Please try again.';
        $msg_type = 'error';
    } else {
    $stmt = $pdo->prepare("UPDATE subjects SET is_active = :status WHERE id = :id");
    $stmt->execute([':status' => $new_status, ':id' => $subject_id]);

    $message  = $new_status === 1 ? 'Subject activated.' : 'Subject deactivated.';
    $msg_type = 'success';
    }
}

// Fetch all subjects, newest first
$subjects = $pdo->query(
    "SELECT * FROM subjects ORDER BY is_active DESC, subject_name ASC"
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<h1 class="page-title">Manage Subjects</h1>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $msg_type; ?>"><?php echo $message; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-title">All Subjects</div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Subject Name</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($subjects)): ?>
                <tr>
                    <td colspan="5" style="text-align:center; color:#7f8c8d;">
                        No subjects added yet. Use the form below to add the first subject.
                    </td>
                </tr>
                <?php endif; ?>

                <?php foreach ($subjects as $i => $sub): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td><strong><?php echo htmlspecialchars($sub['subject_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($sub['description'] ?? '—'); ?></td>
                    <td>
                        <?php if ($sub['is_active']): ?>
                            <span class="badge badge-active">Active</span>
                        <?php else: ?>
                            <span class="badge badge-inactive">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" action="" style="display:inline;">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="action"     value="toggle">
                            <input type="hidden" name="subject_id" value="<?php echo $sub['id']; ?>">
                            <input type="hidden" name="new_status" value="<?php echo $sub['is_active'] ? 0 : 1; ?>">
                            <button
                                type="submit"
                                class="btn btn-sm <?php echo $sub['is_active'] ? 'btn-danger' : 'btn-success'; ?> confirm-action"
                                data-message="<?php echo $sub['is_active'] ? 'Deactivate this subject?' : 'Activate this subject?'; ?>"
                            >
                                <?php echo $sub['is_active'] ? 'Deactivate' : 'Activate'; ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add New Subject Form -->
<div class="card">
    <div class="card-title">Add New Subject</div>
    <form method="POST" action="">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="add">
        <div class="form-row">
            <div class="form-group">
                <label for="subject_name">Subject Name <span style="color:#e74c3c;">*</span></label>
                <input
                    type="text"
                    id="subject_name"
                    name="subject_name"
                    value="<?php echo htmlspecialchars($_POST['subject_name'] ?? ''); ?>"
                    placeholder="e.g., Mathematics"
                    required
                >
            </div>
            <div class="form-group">
                <label for="description">Description (optional)</label>
                <input
                    type="text"
                    id="description"
                    name="description"
                    value="<?php echo htmlspecialchars($_POST['description'] ?? ''); ?>"
                    placeholder="Brief description"
                >
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Add Subject</button>
    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
