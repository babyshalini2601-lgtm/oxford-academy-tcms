<?php
// ============================================================
// admin/classes/edit_class.php — Edit class details and assign subjects
//
// Admin can change the class name, grade level, and choose
// which subjects are taught in this class.
// The subject checkboxes update the class_subjects linking table.
// ============================================================

require_once __DIR__ . '/../../config/db.php';

$required_role = 'admin';
require_once __DIR__ . '/../../includes/auth_check.php';

$pdo        = get_db_connection();
$page_title = 'Edit Class';
$errors     = [];
$message    = '';
$msg_type   = '';

// Read class ID from the URL
$class_id = (int) ($_GET['id'] ?? 0);

if ($class_id === 0) {
    header('Location: ' . BASE_URL . '/admin/classes/list_classes.php');
    exit;
}

// Load the existing class record
$stmt = $pdo->prepare("SELECT * FROM classes WHERE id = :id");
$stmt->execute([':id' => $class_id]);
$class = $stmt->fetch();

if (!$class) {
    header('Location: ' . BASE_URL . '/admin/classes/list_classes.php');
    exit;
}

// Show a success message if we just created this class
if (isset($_GET['created'])) {
    $message  = 'Class created successfully. Now assign the subjects taught in this class.';
    $msg_type = 'success';
}

$grade_levels = ['6', '7', '8', '9', '10', '11', '12', '13'];
if (!in_array((string) $class['grade_level'], $grade_levels, true)) {
    $grade_levels[] = (string) $class['grade_level'];
}

// Fetch all active subjects to show as checkboxes
$all_subjects = $pdo->query(
    "SELECT * FROM subjects WHERE is_active = 1 ORDER BY subject_name ASC"
)->fetchAll();

// Find which subjects are already assigned to this class
$assigned_stmt = $pdo->prepare(
    "SELECT subject_id FROM class_subjects WHERE class_id = :class_id"
);
$assigned_stmt->execute([':class_id' => $class_id]);
$assigned_ids = $assigned_stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_name  = trim($_POST['class_name']  ?? '');
    $grade_level = trim($_POST['grade_level'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $is_active   = isset($_POST['is_active']) ? 1 : 0;
    $selected_subject_ids = array_map('intval', $_POST['subject_ids'] ?? []);

    if (!csrf_ok()) {
        $errors[] = 'This form expired. Please try again.';
    }
    if (empty($class_name)) {
        $errors[] = 'Class name is required.';
    }
    if ($grade_level === '') {
        $errors[] = 'Please select a valid grade level.';
    }

    if (empty($errors)) {
        $pdo->beginTransaction();

        $update = $pdo->prepare(
            "UPDATE classes SET class_name = :name, grade_level = :grade,
             description = :desc, is_active = :active
             WHERE id = :id"
        );
        $update->execute([
            ':name'   => $class_name,
            ':grade'  => $grade_level,
            ':desc'   => $description,
            ':active' => $is_active,
            ':id'     => $class_id,
        ]);

        $insert_sub = $pdo->prepare(
            "INSERT IGNORE INTO class_subjects (class_id, subject_id)
             VALUES (:class_id, :subject_id)"
        );
        foreach ($selected_subject_ids as $sub_id) {
            $insert_sub->execute([':class_id' => $class_id, ':subject_id' => $sub_id]);
        }

        $current_ids = $pdo->prepare(
            "SELECT subject_id FROM class_subjects WHERE class_id = :class_id"
        );
        $current_ids->execute([':class_id' => $class_id]);
        $remove = $pdo->prepare(
            "DELETE FROM class_subjects
             WHERE class_id = :class_id AND subject_id = :subject_id"
        );
        foreach ($current_ids as $row) {
            $existing_id = (int) $row['subject_id'];
            if (!in_array($existing_id, $selected_subject_ids, true)) {
                $remove->execute([
                    ':class_id'   => $class_id,
                    ':subject_id' => $existing_id,
                ]);
            }
        }

        $pdo->commit();

        // Refresh the assigned_ids variable to reflect the new state
        $assigned_ids = $selected_subject_ids;
        $message      = 'Class updated successfully.';
        $msg_type     = 'success';

        // Reload the class record to show updated values
        $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = :id");
        $stmt->execute([':id' => $class_id]);
        $class = $stmt->fetch();
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<h1 class="page-title">Edit Class</h1>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $msg_type; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

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
        <div class="card-title">Class Details</div>
        <div class="form-row">
            <div class="form-group">
                <label for="class_name">Class Name <span style="color:#e74c3c;">*</span></label>
                <input
                    type="text"
                    id="class_name"
                    name="class_name"
                    value="<?php echo htmlspecialchars($class['class_name']); ?>"
                    required
                >
            </div>
            <div class="form-group">
                <label for="grade_level">Grade Level <span style="color:#e74c3c;">*</span></label>
                <select id="grade_level" name="grade_level" required>
                    <?php foreach ($grade_levels as $grade): ?>
                        <option value="<?php echo h($grade); ?>"
                            <?php echo ((string) $class['grade_level'] === (string) $grade) ? 'selected' : ''; ?>>
                            Grade <?php echo h($grade); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="description">Description (optional)</label>
                <input
                    type="text"
                    id="description"
                    name="description"
                    value="<?php echo htmlspecialchars($class['description'] ?? ''); ?>"
                >
            </div>
            <div class="form-group" style="padding-top:28px;">
                <label>
                    <input type="checkbox" name="is_active" value="1"
                        <?php echo $class['is_active'] ? 'checked' : ''; ?>>
                    &nbsp;Class is Active
                </label>
            </div>
        </div>
    </div>

    <!-- Subject Assignment -->
    <div class="card">
        <div class="card-title">Assign Subjects to This Class</div>
        <p style="color:#7f8c8d; font-size:13px; margin-bottom:14px;">
            Tick all subjects that are taught in this class. These subjects will be available
            for attendance marking and examination scheduling.
        </p>

        <?php if (empty($all_subjects)): ?>
            <p style="color:#e67e22;">
                No active subjects found.
                <a href="<?php echo BASE_URL; ?>/admin/subjects/list_subjects.php">Add subjects first</a>.
            </p>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px;">
                <?php foreach ($all_subjects as $sub): ?>
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer; padding:8px; border:1px solid #dde2e8; border-radius:4px;">
                        <input
                            type="checkbox"
                            name="subject_ids[]"
                            value="<?php echo $sub['id']; ?>"
                            <?php echo in_array($sub['id'], $assigned_ids) ? 'checked' : ''; ?>
                        >
                        <?php echo htmlspecialchars($sub['subject_name']); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div style="display:flex; gap:10px; margin-bottom:30px;">
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="<?php echo BASE_URL; ?>/admin/classes/list_classes.php" class="btn btn-secondary">Back to Classes</a>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
