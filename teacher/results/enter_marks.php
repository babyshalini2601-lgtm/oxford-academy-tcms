<?php
// ============================================================
// teacher/results/enter_marks.php — Enter exam marks
//
// Two-step page:
//   Step 1: Teacher selects an exam from the exams assigned
//           to their classes.
//   Step 2: A table of students appears with marks input fields.
//           If marks were already entered, they are pre-filled
//           so the teacher can correct them.
//
// Grade calculation:
//   percentage = (marks_obtained / total_marks) * 100
//   PHP queries grading_criteria to find the matching grade label.
//
// Save strategy:
//   INSERT ... ON DUPLICATE KEY UPDATE handles both first-entry
//   and re-entry without separate INSERT/UPDATE logic.
// ============================================================

require_once __DIR__ . '/../../config/db.php';

$required_role = 'teacher';
require_once __DIR__ . '/../../includes/auth_check.php';

$pdo        = get_db_connection();
$page_title = 'Enter Exam Marks';
$teacher_id = $_SESSION['linked_id'];

$message  = '';
$msg_type = '';

// -------------------------------------------------------
// Helper: look up the grade label for a given percentage
// -------------------------------------------------------
function get_grade($pdo, $percentage) {
    $stmt = $pdo->prepare(
        "SELECT grade_label FROM grading_criteria
         WHERE :pct >= min_percentage AND :pct <= max_percentage
         LIMIT 1"
    );
    $stmt->execute([':pct' => round($percentage, 2)]);
    $grade = $stmt->fetchColumn();
    return $grade ?: 'F';
}

// Load all exams that belong to this teacher's assigned classes
// Teachers can only enter marks for exams in their assigned class+subject pairs
$exams_stmt = $pdo->prepare(
    "SELECT
        e.id,
        e.exam_name,
        e.exam_date,
        e.total_marks,
        c.class_name,
        c.grade_level,
        s.subject_name
     FROM examinations e
     JOIN classes  c ON c.id = e.class_id
     JOIN subjects s ON s.id = e.subject_id
     JOIN teacher_assignments ta
         ON ta.class_id   = e.class_id
         AND ta.subject_id = e.subject_id
         AND ta.teacher_id = :teacher_id
         AND ta.ends_on IS NULL
     ORDER BY e.exam_date DESC"
);
$exams_stmt->execute([':teacher_id' => $teacher_id]);
$my_exams = $exams_stmt->fetchAll();

$selected_exam_id = (int) ($_GET['exam_id'] ?? $_POST['exam_id'] ?? 0);
$exam_info        = null;
$students         = [];
$existing_results = [];  // [student_id => ['marks' => X, 'grade' => Y]]

if ($selected_exam_id > 0) {
    // Verify this exam belongs to one of the teacher's assigned classes
    $is_authorised = false;
    foreach ($my_exams as $ex) {
        if ((int)$ex['id'] === $selected_exam_id) {
            $exam_info     = $ex;
            $is_authorised = true;
            break;
        }
    }

    if ($is_authorised) {
        // Load all students enrolled in this exam's class
        $stu_stmt = $pdo->prepare(
            "SELECT s.id, s.student_code, s.full_name
             FROM students s
             JOIN student_classes sc ON sc.student_id = s.id
             WHERE sc.class_id = (
                 SELECT class_id FROM examinations WHERE id = :exam_id
             )
             AND s.is_active = 1
             ORDER BY s.full_name ASC"
        );
        $stu_stmt->execute([':exam_id' => $selected_exam_id]);
        $students = $stu_stmt->fetchAll();

        // Load any existing results so the form is pre-filled
        $res_stmt = $pdo->prepare(
            "SELECT student_id, marks_obtained, grade
             FROM results
             WHERE examination_id = :exam_id"
        );
        $res_stmt->execute([':exam_id' => $selected_exam_id]);
        foreach ($res_stmt->fetchAll() as $r) {
            $existing_results[$r['student_id']] = [
                'marks' => $r['marks_obtained'],
                'grade' => $r['grade'],
            ];
        }
    }
}

// Handle marks submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_marks'])) {
    $post_exam_id  = (int) $_POST['exam_id'];
    $marks_input   = $_POST['marks'] ?? [];   // [student_id => marks_value]

    // Re-verify teacher authorization
    $authorised_post = false;
    $exam_for_save   = null;
    foreach ($my_exams as $ex) {
        if ((int)$ex['id'] === $post_exam_id) {
            $authorised_post = true;
            $exam_for_save   = $ex;
            break;
        }
    }

    if ($authorised_post && $exam_for_save) {
        $total_marks = $exam_for_save['total_marks'];
        $saved_count = 0;

        $insert_stmt = $pdo->prepare(
            "INSERT INTO results (examination_id, student_id, marks_obtained, grade, entered_by)
             VALUES (:exam_id, :student_id, :marks, :grade, :entered_by)
             ON DUPLICATE KEY UPDATE
                 marks_obtained = VALUES(marks_obtained),
                 grade          = VALUES(grade),
                 entered_by     = VALUES(entered_by),
                 entered_at     = CURRENT_TIMESTAMP"
        );

        foreach ($marks_input as $stu_id => $raw_marks) {
            // Skip rows where the marks field was left blank
            if (trim($raw_marks) === '') {
                continue;
            }

            $marks_obtained = (float) $raw_marks;

            // Enforce marks cannot exceed total_marks
            if ($marks_obtained > $total_marks) {
                $marks_obtained = $total_marks;
            }
            if ($marks_obtained < 0) {
                $marks_obtained = 0;
            }

            // Calculate percentage and look up grade from grading_criteria
            $percentage = ($total_marks > 0) ? ($marks_obtained / $total_marks) * 100 : 0;
            $grade      = get_grade($pdo, $percentage);

            $insert_stmt->execute([
                ':exam_id'    => $post_exam_id,
                ':student_id' => (int) $stu_id,
                ':marks'      => $marks_obtained,
                ':grade'      => $grade,
                ':entered_by' => $_SESSION['user_id'],
            ]);
            $saved_count++;
        }

        $message  = $saved_count . ' result(s) saved successfully.';
        $msg_type = 'success';

        header('Location: ' . BASE_URL . '/teacher/results/enter_marks.php?exam_id=' . $post_exam_id . '&saved=1');
        exit;
    }
}

if (isset($_GET['saved'])) {
    $message  = 'Marks saved successfully.';
    $msg_type = 'success';
}

require_once __DIR__ . '/../../includes/header.php';
?>

<h1 class="page-title">Enter Exam Marks</h1>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $msg_type; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<!-- Step 1: Select exam -->
<div class="card">
    <div class="card-title">Select Examination</div>
    <form method="GET" action="">
        <div class="form-group">
            <label for="exam_id">Examination</label>
            <select id="exam_id" name="exam_id" onchange="this.form.submit()">
                <option value="0">— Select Exam —</option>
                <?php foreach ($my_exams as $ex): ?>
                    <option value="<?php echo $ex['id']; ?>"
                        <?php echo ($selected_exam_id === (int)$ex['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars(
                            $ex['exam_name'] . ' | '
                            . $ex['grade_level'] . ' ' . $ex['class_name'] . ' | '
                            . $ex['subject_name'] . ' | '
                            . date('d M Y', strtotime($ex['exam_date']))
                        ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if (empty($my_exams)): ?>
            <p style="color:#7f8c8d; font-size:13px;">No exams found for your assigned classes. Ask the admin to schedule exams.</p>
        <?php endif; ?>
    </form>
</div>

<!-- Step 2: Marks entry table -->
<?php if ($selected_exam_id > 0 && $exam_info && !empty($students)): ?>

<form method="POST" action="">
    <input type="hidden" name="exam_id"    value="<?php echo $selected_exam_id; ?>">
    <input type="hidden" name="save_marks" value="1">

    <div class="card">
        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:14px; align-items:center;">
            <div class="card-title" style="margin:0; border:none; padding:0;">
                <?php echo htmlspecialchars($exam_info['exam_name']); ?>
                &nbsp;|&nbsp; <?php echo htmlspecialchars($exam_info['subject_name']); ?>
                &nbsp;|&nbsp; <?php echo date('d M Y', strtotime($exam_info['exam_date'])); ?>
                &nbsp;|&nbsp; Out of <strong><?php echo $exam_info['total_marks']; ?></strong>
            </div>
            <span style="font-size:13px; color:#7f8c8d;">
                <?php echo count($existing_results); ?> / <?php echo count($students); ?> marks already entered
            </span>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student ID</th>
                        <th>Full Name</th>
                        <th style="text-align:center;">Marks (out of <?php echo $exam_info['total_marks']; ?>)</th>
                        <th style="text-align:center;">Grade (auto)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $i => $stu):
                        $existing = $existing_results[$stu['id']] ?? null;
                    ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><span style="font-family:monospace;"><?php echo htmlspecialchars($stu['student_code']); ?></span></td>
                        <td><?php echo htmlspecialchars($stu['full_name']); ?></td>
                        <td style="text-align:center;">
                            <input
                                type="number"
                                name="marks[<?php echo $stu['id']; ?>]"
                                min="0"
                                max="<?php echo $exam_info['total_marks']; ?>"
                                step="0.5"
                                value="<?php echo $existing ? $existing['marks'] : ''; ?>"
                                placeholder="Leave blank to skip"
                                style="width:120px; padding:6px 8px; border:1px solid #bdc3c7; border-radius:4px; text-align:center;"
                                onchange="calcGrade(this, <?php echo $exam_info['total_marks']; ?>, 'grade_<?php echo $stu['id']; ?>')"
                            >
                        </td>
                        <td style="text-align:center;">
                            <span id="grade_<?php echo $stu['id']; ?>" style="font-weight:bold; font-size:15px;">
                                <?php echo $existing ? htmlspecialchars($existing['grade']) : '—'; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top:16px;">
            <button type="submit" class="btn btn-primary">Save All Marks</button>
        </div>
    </div>
</form>

<script>
// Grade boundaries loaded from PHP for instant client-side preview.
// The definitive grade is calculated server-side on save.
var gradeBoundaries = <?php
    $criteria = $pdo->query("SELECT grade_label, min_percentage, max_percentage FROM grading_criteria ORDER BY min_percentage DESC")->fetchAll();
    echo json_encode($criteria);
?>;

function calcGrade(inputEl, totalMarks, gradeSpanId) {
    var marks = parseFloat(inputEl.value);
    var span  = document.getElementById(gradeSpanId);

    if (isNaN(marks) || inputEl.value.trim() === '') {
        span.textContent = '—';
        return;
    }

    var pct = (marks / totalMarks) * 100;
    var grade = 'F';
    for (var i = 0; i < gradeBoundaries.length; i++) {
        var g = gradeBoundaries[i];
        if (pct >= parseFloat(g.min_percentage) && pct <= parseFloat(g.max_percentage)) {
            grade = g.grade_label;
            break;
        }
    }
    span.textContent = grade;
}
</script>

<?php elseif ($selected_exam_id > 0 && $exam_info && empty($students)): ?>
    <div class="alert alert-warning">No active students are enrolled in this class.</div>
<?php elseif ($selected_exam_id > 0 && !$exam_info): ?>
    <div class="alert alert-error">You are not authorized to enter marks for this exam.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
