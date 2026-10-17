<?php
// Tick the class + subject pairs this teacher teaches.
// Unticking closes the assignment. Old attendance rows stay.

require_once __DIR__ . '/../../config/db.php';

$required_role = 'admin';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/teacher_access.php';

$pdo        = get_db_connection();
$page_title = 'Assign Classes to Teacher';
$errors     = [];
$message    = '';

$teacher_id = (int) ($_GET['teacher_id'] ?? 0);
if ($teacher_id === 0) {
    redirect_to('/admin/teachers/list_teachers.php');
}

$stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = :id");
$stmt->execute([':id' => $teacher_id]);
$teacher = $stmt->fetch();

if (!$teacher) {
    redirect_to('/admin/teachers/list_teachers.php');
}

if (isset($_GET['added'])) {
    $message = 'Teacher added. Now choose the class and subject they teach.';
}

$offerings = $pdo->query(
    "SELECT
        cs.class_id,
        cs.subject_id,
        c.class_name,
        c.grade_level,
        s.subject_name
     FROM class_subjects cs
     JOIN classes  c ON c.id = cs.class_id
     JOIN subjects s ON s.id = cs.subject_id
     WHERE c.is_active = 1 AND s.is_active = 1
     ORDER BY c.grade_level, c.class_name, s.subject_name"
)->fetchAll();

$current = teacher_current_assignments($pdo, $teacher_id);
$assigned_set = [];
foreach ($current as $row) {
    $assigned_set[$row['class_id'] . '_' . $row['subject_id']] = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $errors[] = 'This form expired. Please try again.';
    } else {
        $selected = [];
        foreach ($_POST['assignments'] ?? [] as $pair) {
            $parts = explode('_', (string) $pair);
            if (count($parts) === 2) {
                $selected[] = [
                    'class_id'   => (int) $parts[0],
                    'subject_id' => (int) $parts[1],
                ];
            }
        }

        try {
            $pdo->beginTransaction();
            apply_teacher_assignments($pdo, $teacher_id, $selected, app_today());
            $pdo->commit();
            $message = 'Assignments updated for ' . $teacher['full_name'] . '.';
            $current = teacher_current_assignments($pdo, $teacher_id);
            $assigned_set = [];
            foreach ($current as $row) {
                $assigned_set[$row['class_id'] . '_' . $row['subject_id']] = true;
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            if ($e->getMessage() === 'invalid_offering') {
                $errors[] = 'One selected subject is not offered in that class.';
            } else {
                error_log('Teacher assignment failed: ' . $e->getMessage());
                $errors[] = 'Assignments were not saved. Please try again.';
            }
        }
    }
}

$grouped = [];
foreach ($offerings as $row) {
    $grouped[$row['class_name']][] = $row;
}

require_once __DIR__ . '/../../includes/header.php';
?>

<h1 class="page-title">
    Assign Classes —
    <span style="font-size:16px; color:#7f8c8d; font-weight:normal;"><?php echo h($teacher['full_name']); ?></span>
</h1>

<?php if ($message !== ''): ?>
    <div class="alert alert-success"><?php echo h($message); ?></div>
<?php endif; ?>
<?php show_form_errors($errors); ?>

<form method="POST" action="">
    <?php csrf_field(); ?>

    <div class="card">
        <div class="card-title">Class and subject responsibilities</div>
        <p style="color:#7f8c8d; font-size:13px; margin-bottom:18px;">
            Tick the subjects this teacher teaches. The teacher will only see students
            who chose those subjects.
        </p>

        <?php if (empty($grouped)): ?>
            <p style="color:#e67e22;">
                No class-subject offerings exist yet.
                <a href="<?php echo BASE_URL; ?>/admin/classes/list_classes.php">Assign subjects to a class first</a>.
            </p>
        <?php else: ?>
            <?php foreach ($grouped as $class_name => $subjects): ?>
                <div style="margin-bottom: 20px;">
                    <div style="font-weight:bold; color:#1a3a5c; margin-bottom:8px;"><?php echo h($class_name); ?></div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 8px;">
                        <?php foreach ($subjects as $row):
                            $pair_key = $row['class_id'] . '_' . $row['subject_id'];
                            $is_assigned = isset($assigned_set[$pair_key]);
                        ?>
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; padding:8px 10px; border:1px solid #dde2e8; border-radius:4px; background:<?php echo $is_assigned ? '#eaf4fb' : '#fff'; ?>;">
                                <input type="checkbox" name="assignments[]" value="<?php echo h($pair_key); ?>" <?php echo $is_assigned ? 'checked' : ''; ?>>
                                <?php echo h($row['subject_name']); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div style="display:flex; gap:10px; margin-bottom:30px;">
        <button type="submit" class="btn btn-primary">Save Assignments</button>
        <a href="<?php echo BASE_URL; ?>/admin/teachers/edit_teacher.php?id=<?php echo $teacher_id; ?>" class="btn btn-secondary">Edit Profile</a>
        <a href="<?php echo BASE_URL; ?>/admin/teachers/list_teachers.php" class="btn btn-secondary">Back to Teachers</a>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
