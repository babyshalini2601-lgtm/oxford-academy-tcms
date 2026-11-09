<?php
// ============================================================
// admin/exams/grading_criteria.php — Manage grade boundaries
//
// Shows the current grade scale (A+, A, B, C, D, F by default).
// Admin can update each row's label and percentage range, or add
// a new grade. Grades are used in enter_marks.php to auto-assign
// a grade label when marks are saved.
//
// Rule: ranges must not overlap and should cover 0–100.
// ============================================================

require_once __DIR__ . '/../../config/db.php';

$required_role = 'admin';
require_once __DIR__ . '/../../includes/auth_check.php';

$pdo        = get_db_connection();
$page_title = 'Grading Criteria';
$message    = '';
$msg_type   = '';

// Handle update of a single grade row
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // CSRF check on every POST
    if (!csrf_ok()) {
        $message  = 'The form expired. Please try again.';
        $msg_type = 'error';
    } elseif ($_POST['action'] === 'update') {
        $grade_id       = (int)   $_POST['grade_id'];
        $grade_label    = trim($_POST['grade_label']    ?? '');
        $min_percentage = trim($_POST['min_percentage'] ?? '');
        $max_percentage = trim($_POST['max_percentage'] ?? '');

        if (empty($grade_label) || !is_numeric($min_percentage) || !is_numeric($max_percentage)) {
            $message  = 'Please fill in all fields with valid values.';
            $msg_type = 'error';
        } elseif ((float) $min_percentage > (float) $max_percentage) {
            $message  = 'Minimum percentage cannot be greater than maximum percentage.';
            $msg_type = 'error';
        } elseif ($grade_id <= 0) {
            $message  = 'Invalid grade row.';
            $msg_type = 'error';
        } else {
            $stmt = $pdo->prepare(
                "UPDATE grading_criteria
                 SET grade_label = :label, min_percentage = :min, max_percentage = :max
                 WHERE id = :id"
            );
            $stmt->execute([
                ':label' => $grade_label,
                ':min'   => (float) $min_percentage,
                ':max'   => (float) $max_percentage,
                ':id'    => $grade_id,
            ]);
            $message  = 'Grade updated successfully.';
            $msg_type = 'success';
        }
    } elseif ($_POST['action'] === 'add') {
        $grade_label    = trim($_POST['new_grade_label']    ?? '');
        $min_percentage = trim($_POST['new_min_percentage'] ?? '');
        $max_percentage = trim($_POST['new_max_percentage'] ?? '');

        if (empty($grade_label) || !is_numeric($min_percentage) || !is_numeric($max_percentage)) {
            $message  = 'Please fill in all fields to add a new grade.';
            $msg_type = 'error';
        } elseif ((float) $min_percentage > (float) $max_percentage) {
            $message  = 'Minimum percentage cannot be greater than maximum percentage.';
            $msg_type = 'error';
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO grading_criteria (grade_label, min_percentage, max_percentage)
                 VALUES (:label, :min, :max)"
            );
            $stmt->execute([
                ':label' => $grade_label,
                ':min'   => (float) $min_percentage,
                ':max'   => (float) $max_percentage,
            ]);
            $message  = 'New grade added.';
            $msg_type = 'success';
        }
    }
}

// Fetch all grade rows, highest range first (A+ at top)
$grades = $pdo->query(
    "SELECT * FROM grading_criteria ORDER BY min_percentage DESC"
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<h1 class="page-title">Grading Criteria</h1>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo h($msg_type); ?>"><?php echo h($message); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-title">Current Grade Scale</div>
    <p style="color:#7f8c8d; font-size:13px; margin-bottom:16px;">
        These ranges are used to assign a grade automatically when a teacher
        enters marks. Make sure the ranges do not overlap and cover 0–100%.
    </p>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Grade</th>
                    <th>Min %</th>
                    <th>Max %</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grades as $g): ?>
                <tr>
                    <form method="POST" action="" style="display:contents;">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action"   value="update">
                        <input type="hidden" name="grade_id" value="<?php echo (int) $g['id']; ?>">
                        <td>
                            <input type="text" name="grade_label"
                                value="<?php echo h($g['grade_label']); ?>"
                                style="width:60px; padding:5px 8px; border:1px solid #bdc3c7;
                                       border-radius:4px; font-size:14px; font-weight:bold;
                                       text-align:center;">
                        </td>
                        <td>
                            <input type="number" name="min_percentage"
                                value="<?php echo $g['min_percentage']; ?>"
                                step="0.01" min="0" max="100"
                                style="width:80px; padding:5px 8px; border:1px solid #bdc3c7;
                                       border-radius:4px; font-size:14px;">
                        </td>
                        <td>
                            <input type="number" name="max_percentage"
                                value="<?php echo $g['max_percentage']; ?>"
                                step="0.01" min="0" max="100"
                                style="width:80px; padding:5px 8px; border:1px solid #bdc3c7;
                                       border-radius:4px; font-size:14px;">
                        </td>
                        <td>
                            <button type="submit" class="btn btn-sm btn-primary">Update</button>
                        </td>
                    </form>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add new grade -->
<div class="card">
    <div class="card-title">Add New Grade</div>
    <form method="POST" action="">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="add">
        <div class="form-row">
            <div class="form-group">
                <label for="new_grade_label">Grade Label</label>
                <input type="text" id="new_grade_label" name="new_grade_label"
                    placeholder="e.g., A+" style="max-width:100px;">
            </div>
            <div class="form-group">
                <label for="new_min_percentage">Min %</label>
                <input type="number" id="new_min_percentage" name="new_min_percentage"
                    step="0.01" min="0" max="100" placeholder="e.g., 75">
            </div>
            <div class="form-group">
                <label for="new_max_percentage">Max %</label>
                <input type="number" id="new_max_percentage" name="new_max_percentage"
                    step="0.01" min="0" max="100" placeholder="e.g., 89.99">
            </div>
        </div>
        <button type="submit" class="btn btn-success">Add Grade</button>
    </form>
</div>

<div class="card" style="margin-top:4px;">
    <a href="<?php echo BASE_URL; ?>/admin/exams/list_exams.php"
       class="btn btn-secondary">← Back to Exams</a>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
