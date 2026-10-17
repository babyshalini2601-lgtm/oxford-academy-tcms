<?php
// ============================================================
// admin/classes/add_class.php — Create a new class
//
// Admin fills in the class name and grade level. Subjects are
// assigned after the class is created, from the edit page.
// ============================================================

require_once __DIR__ . '/../../config/db.php';

$required_role = 'admin';
require_once __DIR__ . '/../../includes/auth_check.php';

$pdo        = get_db_connection();
$page_title = 'Add New Class';
$errors     = [];

// Grade levels that Oxford Academy offers (Grade 6 to 13)
$grade_levels = ['6', '7', '8', '9', '10', '11', '12', '13'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_name  = trim($_POST['class_name']  ?? '');
    $grade_level = trim($_POST['grade_level'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (!csrf_ok()) {
        $errors[] = 'This form expired. Please try again.';
    }
    if (empty($class_name)) {
        $errors[] = 'Class name is required.';
    }
    if (empty($grade_level) || !in_array($grade_level, $grade_levels, true)) {
        $errors[] = 'Please select a valid grade level.';
    }

    // Check for duplicate class name
    if (empty($errors)) {
        $check = $pdo->prepare(
            "SELECT id FROM classes WHERE class_name = :name AND grade_level = :grade"
        );
        $check->execute([':name' => $class_name, ':grade' => $grade_level]);
        if ($check->fetch()) {
            $errors[] = 'A class with this name already exists for the selected grade level.';
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "INSERT INTO classes (class_name, grade_level, description, is_active)
             VALUES (:name, :grade, :desc, 1)"
        );
        $stmt->execute([
            ':name'  => $class_name,
            ':grade' => $grade_level,
            ':desc'  => $description,
        ]);

        $new_class_id = $pdo->lastInsertId();

        // Redirect to the edit page so admin can assign subjects immediately
        header('Location: ' . BASE_URL . '/admin/classes/edit_class.php?id=' . $new_class_id . '&created=1');
        exit;
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<h1 class="page-title">Add New Class</h1>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as $err): ?>
            <div><?php echo htmlspecialchars($err); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-title">Class Details</div>
    <form method="POST" action="">
        <?php csrf_field(); ?>
        <div class="form-row">
            <div class="form-group">
                <label for="class_name">Class Name <span style="color:#e74c3c;">*</span></label>
                <input
                    type="text"
                    id="class_name"
                    name="class_name"
                    value="<?php echo htmlspecialchars($_POST['class_name'] ?? ''); ?>"
                    placeholder="e.g., Science Group A"
                    required
                >
            </div>
            <div class="form-group">
                <label for="grade_level">Grade Level <span style="color:#e74c3c;">*</span></label>
                <select id="grade_level" name="grade_level" required>
                    <option value="">— Select Grade —</option>
                    <?php foreach ($grade_levels as $grade): ?>
                        <option value="<?php echo $grade; ?>"
                            <?php echo (($_POST['grade_level'] ?? '') === $grade) ? 'selected' : ''; ?>>
                            Grade <?php echo $grade; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label for="description">Description (optional)</label>
            <input
                type="text"
                id="description"
                name="description"
                value="<?php echo htmlspecialchars($_POST['description'] ?? ''); ?>"
                placeholder="Optional notes about this class"
            >
        </div>
        <div style="display:flex; gap:10px; margin-top:8px;">
            <button type="submit" class="btn btn-primary">Save and Assign Subjects</button>
            <a href="<?php echo BASE_URL; ?>/admin/classes/list_classes.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
