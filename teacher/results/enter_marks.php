<?php
// ============================================================
// teacher/results/enter_marks.php — Enter exam marks
//
// Two-step page:
//   Step 1: Teacher selects an exam from the exams assigned
//           to their class+subject pairs.
//   Step 2: A table of enrolled students appears with mark inputs.
//           If marks were already entered they are pre-filled.
//
// Validation rules (from spec):
//   - Marks must be numeric. Non-numeric input is rejected.
//   - Marks must be in range [0, total_marks]. Out-of-range is rejected.
//   - Blank rows are skipped (absent student, marks entered later).
//   - Only students enrolled in the specific subject via student_subjects
//     are shown — not all students in the class.
//   - Teacher must be assigned to the exam's class+subject pair.
//
// Grade calculation:
//   percentage = (marks_obtained / total_marks) * 100
//   The grade label is looked up from grading_criteria server-side.
//
// Save strategy:
//   INSERT ... ON DUPLICATE KEY UPDATE handles both first entry
//   and correction in a single statement.
// ============================================================

require_once __DIR__ . '/../../config/db.php';

$required_role = 'teacher';
require_once __DIR__ . '/../../includes/auth_check.php';

$pdo        = get_db_connection();
$page_title = 'Enter Exam Marks';
$teacher_id = (int) $_SESSION['linked_id'];

$message  = '';
$msg_type = '';
$errors   = [];

// -------------------------------------------------------
// Helper: look up grade for a percentage.
// Uses two separate parameters (:pct_min and :pct_max) to
// avoid reusing the same placeholder name, which fails when
// PDO emulated prepares are disabled (ATTR_EMULATE_PREPARES=false).
// -------------------------------------------------------
function get_grade(PDO $pdo, float $percentage): string
{
    $stmt = $pdo->prepare(
        "SELECT grade_label FROM grading_criteria
         WHERE :pct_min >= min_percentage AND :pct_max <= max_percentage
         LIMIT 1"
    );
    $pct_rounded = round($percentage, 2);
    $stmt->execute([
        ':pct_min' => $pct_rounded,
        ':pct_max' => $pct_rounded,
    ]);
    $grade = $stmt->fetchColumn();
    return $grade ?: 'F';
}

// Load all exams that belong to this teacher's current assigned class+subject pairs
$exams_stmt = $pdo->prepare(
    "SELECT
        e.id,
        e.exam_name,
        e.exam_date,
        e.total_marks,
        e.class_id,
        e.subject_id,
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
$existing_results = [];   // [student_id => ['marks' => X, 'grade' => Y]]

if ($selected_exam_id > 0) {
    // Verify this exam is in one of the teacher's authorised pairs
    foreach ($my_exams as $ex) {
        if ((int) $ex['id'] === $selected_exam_id) {
            $exam_info = $ex;
            break;
        }
    }

    if ($exam_info) {
        // Load students enrolled in both the class AND the specific subject
        // (student_subjects, not just student_classes — per spec)
        $stu_stmt = $pdo->prepare(
            "SELECT s.id, s.student_code, s.full_name
             FROM students s
             JOIN student_subjects ss
                 ON ss.student_id = s.id
                 AND ss.class_id   = :class_id
                 AND ss.subject_id = :subject_id
                 AND (ss.ends_on IS NULL OR ss.ends_on >= CURDATE())
             WHERE s.is_active = 1
             ORDER BY s.full_name ASC"
        );
        $stu_stmt->execute([
            ':class_id'   => $exam_info['class_id'],
            ':subject_id' => $exam_info['subject_id'],
        ]);
        $students = $stu_stmt->fetchAll();

        // Load any existing results so the form is pre-filled
        $res_stmt = $pdo->prepare(
            "SELECT student_id, marks_obtained, grade
             FROM results
             WHERE examination_id = :exam_id"
        );
        $res_stmt->execute([':exam_id' => $selected_exam_id]);
        foreach ($res_stmt->fetchAll() as $r) {
            $existing_results[(int) $r['student_id']] = [
                'marks' => $r['marks_obtained'],
                'grade' => $r['grade'],
            ];
        }
    }
}

// Build a lookup of authorised student IDs for the selected exam
$authorised_student_ids = array_column($students, 'id');

// Handle marks submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_marks'])) {
    if (!csrf_ok()) {
        $errors[] = 'The form expired. Please try again.';
    } else {
        $post_exam_id = (int) $_POST['exam_id'];
        $marks_input  = $_POST['marks'] ?? [];   // [student_id => marks_value]

        // Re-verify teacher authorisation for this exam
        $exam_for_save = null;
        foreach ($my_exams as $ex) {
            if ((int) $ex['id'] === $post_exam_id) {
                $exam_for_save = $ex;
                break;
            }
        }

        if (!$exam_for_save) {
            $errors[] = 'You are not authorised to enter marks for this exam.';
        }

        // Validate every submitted mark before saving anything
        if (empty($errors) && $exam_for_save) {
            $total_marks        = (int) $exam_for_save['total_marks'];
            $validation_errors  = [];

            foreach ($marks_input as $stu_id => $raw_marks) {
                $stu_id = (int) $stu_id;

                // Skip blank entries — absent students can be filled in later
                if (trim((string) $raw_marks) === '') {
                    continue;
                }

                // Reject non-numeric marks
                if (!is_numeric($raw_marks)) {
                    // Find student name for the message
                    $stu_name = '';
                    foreach ($students as $s) {
                        if ((int) $s['id'] === $stu_id) {
                            $stu_name = $s['full_name'];
                            break;
                        }
                    }
                    $validation_errors[] = 'Marks for ' . $stu_name . ' are not a valid number.';
                    continue;
                }

                $marks_val = (float) $raw_marks;

                // Reject marks below zero
                if ($marks_val < 0) {
                    $stu_name = '';
                    foreach ($students as $s) {
                        if ((int) $s['id'] === $stu_id) { $stu_name = $s['full_name']; break; }
                    }
                    $validation_errors[] = 'Marks for ' . $stu_name . ' cannot be negative.';
                    continue;
                }

                // Reject marks above the exam maximum — do not silently clamp
                if ($marks_val > $total_marks) {
                    $stu_name = '';
                    foreach ($students as $s) {
                        if ((int) $s['id'] === $stu_id) { $stu_name = $s['full_name']; break; }
                    }
                    $validation_errors[] = 'Marks for ' . $stu_name
                        . ' (' . $marks_val . ') exceed the maximum (' . $total_marks . ').';
                    continue;
                }

                // Reject student IDs that are not enrolled in this subject
                if (!in_array($stu_id, $authorised_student_ids, true)) {
                    $validation_errors[] = 'Student ID ' . $stu_id
                        . ' is not enrolled in this subject.';
                }
            }

            if (!empty($validation_errors)) {
                $errors = array_merge($errors, $validation_errors);
            }
        }

        // If all marks passed validation, save them all
        if (empty($errors) && $exam_for_save) {
            $total_marks = (int) $exam_for_save['total_marks'];

            $insert_stmt = $pdo->prepare(
                "INSERT INTO results
                    (examination_id, student_id, marks_obtained, grade, entered_by)
                 VALUES
                    (:exam_id, :student_id, :marks, :grade, :entered_by)
                 ON DUPLICATE KEY UPDATE
                     marks_obtained = VALUES(marks_obtained),
                     grade          = VALUES(grade),
                     entered_by     = VALUES(entered_by),
                     entered_at     = CURRENT_TIMESTAMP"
            );

            $saved_count = 0;
            foreach ($marks_input as $stu_id => $raw_marks) {
                if (trim((string) $raw_marks) === '') {
                    continue;  // blank = skip (not absent-zero)
                }

                $marks_obtained = (float) $raw_marks;
                $percentage     = ($total_marks > 0)
                    ? ($marks_obtained / $total_marks) * 100
                    : 0;
                $grade          = get_grade($pdo, $percentage);

                $insert_stmt->execute([
                    ':exam_id'    => $post_exam_id,
                    ':student_id' => (int) $stu_id,
                    ':marks'      => $marks_obtained,
                    ':grade'      => $grade,
                    ':entered_by' => $_SESSION['user_id'],
                ]);
                $saved_count++;
            }

            // POST-redirect-GET prevents resubmission on refresh
            redirect_to(
                '/teacher/results/enter_marks.php?exam_id=' . $post_exam_id . '&saved=' . $saved_count
            );
        }
    }
}

if (isset($_GET['saved'])) {
    $message  = (int) $_GET['saved'] . ' result(s) saved successfully.';
    $msg_type = 'success';
}

require_once __DIR__ . '/../../includes/header.php';
?>

<h1 class="page-title">Enter Exam Marks</h1>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo h($msg_type); ?>"><?php echo h($message); ?></div>
<?php endif; ?>
<?php show_form_errors($errors); ?>

<!-- Step 1: select exam -->
<div class="card">
    <div class="card-title">Select Examination</div>
    <form method="GET" action="">
        <div class="form-group">
            <label for="exam_id">Examination</label>
            <select id="exam_id" name="exam_id" onchange="this.form.submit()">
                <option value="0">— Select Exam —</option>
                <?php foreach ($my_exams as $ex): ?>
                    <option value="<?php echo (int) $ex['id']; ?>"
                        <?php echo ($selected_exam_id === (int) $ex['id']) ? 'selected' : ''; ?>>
                        <?php echo h(
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
            <p style="color:#7f8c8d; font-size:13px;">
                No exams found for your assigned classes.
                Ask the administrator to schedule exams for your class and subject.
            </p>
        <?php endif; ?>
    </form>
</div>

<!-- Step 2: marks entry table — only shown when an authorised exam is chosen -->
<?php if ($selected_exam_id > 0 && $exam_info && !empty($students)): ?>

<form method="POST" action="">
    <?php csrf_field(); ?>
    <input type="hidden" name="exam_id"    value="<?php echo (int) $selected_exam_id; ?>">
    <input type="hidden" name="save_marks" value="1">

    <div class="card">
        <div style="display:flex; justify-content:space-between; flex-wrap:wrap;
                    gap:10px; margin-bottom:14px; align-items:center;">
            <div class="card-title" style="margin:0; border:none; padding:0;">
                <?php echo h($exam_info['exam_name']); ?>
                &nbsp;|&nbsp; <?php echo h($exam_info['subject_name']); ?>
                &nbsp;|&nbsp; <?php echo date('d M Y', strtotime($exam_info['exam_date'])); ?>
                &nbsp;|&nbsp; Out of <strong><?php echo (int) $exam_info['total_marks']; ?></strong>
            </div>
            <span style="font-size:13px; color:#7f8c8d;">
                <?php echo count($existing_results); ?> / <?php echo count($students); ?> marks already entered
            </span>
        </div>

        <p style="font-size:13px; color:#7f8c8d; margin-bottom:12px;">
            Leave a row blank to skip it (the student's mark can be entered later).
            Marks must be a number between 0 and <?php echo (int) $exam_info['total_marks']; ?>;
            values outside this range will be rejected.
        </p>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student ID</th>
                        <th>Full Name</th>
                        <th style="text-align:center;">
                            Marks (out of <?php echo (int) $exam_info['total_marks']; ?>)
                        </th>
                        <th style="text-align:center;">Grade (preview)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $i => $stu):
                        $existing = $existing_results[(int) $stu['id']] ?? null;
                        // Retain a posted value on validation failure
                        $posted_val = $_POST['marks'][$stu['id']] ?? null;
                        $display_val = ($posted_val !== null)
                            ? $posted_val
                            : ($existing ? $existing['marks'] : '');
                    ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><span style="font-family:monospace;"><?php echo h($stu['student_code']); ?></span></td>
                        <td><?php echo h($stu['full_name']); ?></td>
                        <td style="text-align:center;">
                            <input
                                type="number"
                                name="marks[<?php echo (int) $stu['id']; ?>]"
                                min="0"
                                max="<?php echo (int) $exam_info['total_marks']; ?>"
                                step="0.5"
                                value="<?php echo h((string) $display_val); ?>"
                                placeholder="Leave blank to skip"
                                style="width:120px; padding:6px 8px; border:1px solid #bdc3c7;
                                       border-radius:4px; text-align:center;"
                                onchange="calcGrade(this, <?php echo (int) $exam_info['total_marks']; ?>,
                                          'grade_<?php echo (int) $stu['id']; ?>')">
                        </td>
                        <td style="text-align:center;">
                            <span id="grade_<?php echo (int) $stu['id']; ?>"
                                  style="font-weight:bold; font-size:15px;">
                                <?php echo $existing ? h($existing['grade']) : '—'; ?>
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
// Grade boundaries for instant client-side preview.
// The definitive grade is recalculated server-side on save.
var gradeBoundaries = <?php
    $criteria = $pdo->query(
        "SELECT grade_label, min_percentage, max_percentage
         FROM grading_criteria ORDER BY min_percentage DESC"
    )->fetchAll();
    echo json_encode($criteria);
?>;

function calcGrade(inputEl, totalMarks, gradeSpanId) {
    var marks = parseFloat(inputEl.value);
    var span  = document.getElementById(gradeSpanId);

    if (isNaN(marks) || inputEl.value.trim() === '') {
        span.textContent = '—';
        return;
    }

    var pct   = (marks / totalMarks) * 100;
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
    <div class="alert alert-warning">
        No active students are enrolled in
        <strong><?php echo h($exam_info['subject_name']); ?></strong>
        for this class yet.
    </div>
<?php elseif ($selected_exam_id > 0 && !$exam_info): ?>
    <div class="alert alert-error">
        You are not authorised to enter marks for this exam.
        Only teachers assigned to the exam's class and subject can do this.
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
