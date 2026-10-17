<?php
// ============================================================
// admin/exams/schedule_exam.php — Schedule a new examination
//
// Admin selects a class, then the subject dropdown automatically
// filters to show only subjects assigned to that class.
// This avoids scheduling an exam for a subject not taught in
// the selected class.
//
// When the class changes the page reloads via GET so the
// subject dropdown is populated server-side — no complex JS needed.
// ============================================================

require_once __DIR__ . '/../../config/db.php';

$required_role = 'admin';
require_once __DIR__ . '/../../includes/auth_check.php';

$pdo        = get_db_connection();
$page_title = 'Schedule New Exam';
$errors     = [];

// The selected class drives the subject dropdown.
// It is passed as a GET param when the class dropdown changes.
$filter_class_id = (int) ($_GET['class_id'] ?? $_POST['class_id'] ?? 0);

// All active classes
$classes = $pdo->query(
    "SELECT id, class_name, grade_level FROM classes WHERE is_active = 1 ORDER BY grade_level, class_name"
)->fetchAll();

// Subjects for the selected class only
$subjects = [];
if ($filter_class_id > 0) {
    $sub_stmt = $pdo->prepare(
        "SELECT s.id, s.subject_name
         FROM subjects s
         JOIN class_subjects cs ON cs.subject_id = s.id
         WHERE cs.class_id = :class_id AND s.is_active = 1
         ORDER BY s.subject_name"
    );
    $sub_stmt->execute([':class_id' => $filter_class_id]);
    $subjects = $sub_stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_exam'])) {
    $exam_name   = trim($_POST['exam_name']   ?? '');
    $class_id    = (int)   $_POST['class_id'];
    $subject_id  = (int)   $_POST['subject_id'];
    $exam_date   = trim($_POST['exam_date']   ?? '');
    $total_marks = (int)   ($_POST['total_marks'] ?? 0);

    // Validation
    if (empty($exam_name)) {
        $errors[] = 'Exam name is required.';
    }
    if ($class_id <= 0) {
        $errors[] = 'Please select a class.';
    }
    if ($subject_id <= 0) {
        $errors[] = 'Please select a subject.';
    }
    if (empty($exam_date)) {
        $errors[] = 'Exam date is required.';
    }
    if ($total_marks <= 0) {
        $errors[] = 'Total marks must be greater than zero.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "INSERT INTO examinations (exam_name, class_id, subject_id, exam_date, total_marks, created_by)
             VALUES (:name, :class_id, :subject_id, :exam_date, :total, :created_by)"
        );
        $stmt->execute([
            ':name'       => $exam_name,
            ':class_id'   => $class_id,
            ':subject_id' => $subject_id,
            ':exam_date'  => $exam_date,
            ':total'      => $total_marks,
            ':created_by' => $_SESSION['user_id'],
        ]);

        header('Location: ' . BASE_URL . '/admin/exams/list_exams.php?scheduled=1');
        exit;
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<h1 class="page-title">Schedule New Exam</h1>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as $err): echo '<div>' . htmlspecialchars($err) . '</div>'; endforeach; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-title">Exam Details</div>
    <form method="POST" action="">
        <input type="hidden" name="save_exam" value="1">

        <div class="form-group">
            <label for="exam_name">Exam Name <span style="color:#e74c3c;">*</span></label>
            <input type="text" id="exam_name" name="exam_name"
                value="<?php echo htmlspecialchars($_POST['exam_name'] ?? ''); ?>"
                placeholder="e.g., Term 1 Mid-Year Exam" required>
        </div>

        <div class="form-row">
            <!-- Class dropdown: changing it reloads the page via GET to filter subjects -->
            <div class="form-group">
                <label for="class_id">Class <span style="color:#e74c3c;">*</span></label>
                <select id="class_id" name="class_id"
                    onchange="window.location.href='?class_id=' + this.value">
                    <option value="0">— Select Class First —</option>
                    <?php foreach ($classes as $cls): ?>
                        <option value="<?php echo $cls['id']; ?>"
                            <?php echo ($filter_class_id === (int)$cls['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cls['grade_level'] . ' — ' . $cls['class_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Subject dropdown: only shows subjects for the selected class -->
            <div class="form-group">
                <label for="subject_id">Subject <span style="color:#e74c3c;">*</span></label>
                <select id="subject_id" name="subject_id" required
                    <?php echo empty($subjects) ? 'disabled' : ''; ?>>
                    <option value="0">
                        <?php echo empty($subjects)
                            ? '— Select a class first —'
                            : '— Select Subject —'; ?>
                    </option>
                    <?php foreach ($subjects as $sub): ?>
                        <option value="<?php echo $sub['id']; ?>"
                            <?php echo (((int)($_POST['subject_id'] ?? 0)) === (int)$sub['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sub['subject_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="exam_date">Exam Date <span style="color:#e74c3c;">*</span></label>
                <input type="date" id="exam_date" name="exam_date"
                    value="<?php echo htmlspecialchars($_POST['exam_date'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="total_marks">Total Marks <span style="color:#e74c3c;">*</span></label>
                <input type="number" id="total_marks" name="total_marks"
                    min="1" max="1000"
                    value="<?php echo htmlspecialchars($_POST['total_marks'] ?? '100'); ?>" required>
            </div>
        </div>

        <div style="display:flex; gap:10px; margin-top:8px;">
            <button type="submit" class="btn btn-primary"
                <?php echo ($filter_class_id === 0) ? 'disabled title="Select a class first"' : ''; ?>>
                Schedule Exam
            </button>
            <a href="<?php echo BASE_URL; ?>/admin/exams/list_exams.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
