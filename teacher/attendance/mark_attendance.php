<?php
// Teacher marks one assigned class+subject for one date.
// Every enrolled student must be present or absent. After save,
// the teacher cannot change it — the administrator corrects it.

require_once __DIR__ . '/../../config/db.php';

$required_role = 'teacher';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/attendance.php';

$pdo        = get_db_connection();
$page_title = 'Mark Attendance';
$teacher_id = (int) $_SESSION['linked_id'];
$errors     = [];
$message    = '';

$assignments = teacher_current_assignments($pdo, $teacher_id);

$class_id   = (int) ($_POST['class_id']   ?? $_GET['class_id']   ?? 0);
$subject_id = (int) ($_POST['subject_id'] ?? $_GET['subject_id'] ?? 0);
$att_date   = trim($_POST['att_date']     ?? $_GET['att_date']   ?? app_today());

if (isset($_GET['saved'])) {
    $message = 'Attendance saved. Contact the administrator if a correction is needed.';
}

$posted_status = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $errors[] = 'This form expired. Please try again.';
    } else {
        $posted_status = $_POST['status'] ?? [];
        try {
            save_teacher_attendance($pdo, $teacher_id, $class_id, $subject_id, $att_date, $posted_status);
            redirect_to(
                '/teacher/attendance/mark_attendance.php?class_id=' . $class_id
                . '&subject_id=' . $subject_id
                . '&att_date=' . urlencode($att_date)
                . '&saved=1'
            );
        } catch (Exception $e) {
            $errors[] = attendance_message($e->getMessage());
        }
    }
}

$can_mark   = false;
$students   = [];
$existing   = [];
$already    = false;
$class_name = '';
$subject_name = '';

if ($class_id > 0 && $subject_id > 0 && is_valid_date($att_date)) {
    $can_mark = teacher_can_mark_offering($pdo, $teacher_id, $class_id, $subject_id, $att_date);
    if ($can_mark) {
        $students = students_eligible_on_date($pdo, $class_id, $subject_id, $att_date, true);
        $existing = attendance_rows_for_date($pdo, $class_id, $subject_id, $att_date);
        $already  = !empty($existing);

        foreach ($assignments as $row) {
            if ((int) $row['class_id'] === $class_id && (int) $row['subject_id'] === $subject_id) {
                $class_name   = $row['class_name'];
                $subject_name = $row['subject_name'];
                break;
            }
        }
    }
}

$date_error = '';
if ($att_date !== '' && !is_valid_date($att_date)) {
    $date_error = 'Please choose a real date.';
} elseif (is_valid_date($att_date) && $att_date > app_today()) {
    $date_error = 'Attendance cannot be marked for a future date.';
}

require_once __DIR__ . '/../../includes/header.php';
?>

<h1 class="page-title">Mark Attendance</h1>

<?php if ($message !== ''): ?>
    <div class="alert alert-success"><?php echo h($message); ?></div>
<?php endif; ?>
<?php show_form_errors($errors); ?>

<div class="card">
    <div class="card-title">Select class, subject, and date</div>
    <form method="GET" action="">
        <div class="form-row">
            <div class="form-group">
                <label for="offering">Class and subject</label>
                <select id="offering" name="offering" onchange="splitOffering(this)">
                    <option value="">— Select —</option>
                    <?php foreach ($assignments as $row):
                        $value = $row['class_id'] . '_' . $row['subject_id'];
                        $selected = ($class_id === (int) $row['class_id'] && $subject_id === (int) $row['subject_id']);
                    ?>
                        <option value="<?php echo h($value); ?>" <?php echo $selected ? 'selected' : ''; ?>>
                            <?php echo h($row['class_name'] . ' — ' . $row['subject_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" id="class_id" name="class_id" value="<?php echo (int) $class_id; ?>">
                <input type="hidden" id="subject_id" name="subject_id" value="<?php echo (int) $subject_id; ?>">
            </div>
            <div class="form-group">
                <label for="att_date">Date</label>
                <input type="date" id="att_date" name="att_date"
                    value="<?php echo h($att_date); ?>"
                    max="<?php echo h(app_today()); ?>">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Load students</button>
    </form>
</div>

<script>
function splitOffering(selectEl) {
    var parts = selectEl.value.split('_');
    document.getElementById('class_id').value = parts[0] || '';
    document.getElementById('subject_id').value = parts[1] || '';
}
</script>

<?php if ($class_id > 0 && $subject_id > 0 && $date_error !== ''): ?>
    <div class="alert alert-error"><?php echo h($date_error); ?></div>

<?php elseif ($class_id > 0 && $subject_id > 0 && !$can_mark): ?>
    <div class="alert alert-error">You are not assigned to that class and subject on this date.</div>

<?php elseif ($can_mark && empty($students)): ?>
    <div class="alert alert-warning">No students were enrolled in this subject on that date.</div>

<?php elseif ($can_mark && $already): ?>
    <div class="card">
        <div class="card-title">
            <?php echo h($class_name . ' — ' . $subject_name . ' — ' . date('d M Y', strtotime($att_date))); ?>
        </div>
        <p style="color:#7f8c8d; margin-bottom:14px;">
            Submitted — contact the administrator for a correction.
        </p>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student ID</th>
                        <th>Full Name</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $index => $student):
                        $row = $existing[(int) $student['id']] ?? null;
                    ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><span style="font-family:monospace;"><?php echo h($student['student_code']); ?></span></td>
                        <td><?php echo h($student['full_name']); ?></td>
                        <td><?php echo attendance_status_badge($row['status'] ?? ''); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($can_mark): ?>
    <form method="POST" action="">
        <?php csrf_field(); ?>
        <input type="hidden" name="class_id" value="<?php echo (int) $class_id; ?>">
        <input type="hidden" name="subject_id" value="<?php echo (int) $subject_id; ?>">
        <input type="hidden" name="att_date" value="<?php echo h($att_date); ?>">

        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
                <div class="card-title" style="margin:0; border:none; padding:0;">
                    <?php echo h($class_name . ' — ' . $subject_name . ' — ' . date('d M Y', strtotime($att_date))); ?>
                </div>
                <span id="marked-count" style="color:#7f8c8d; font-size:13px;">Marked: 0 of <?php echo count($students); ?></span>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th style="text-align:center;">Present</th>
                            <th style="text-align:center;">Absent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $index => $student):
                            $chosen = $posted_status[$student['id']] ?? '';
                        ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><span style="font-family:monospace;"><?php echo h($student['student_code']); ?></span></td>
                            <td><?php echo h($student['full_name']); ?></td>
                            <td style="text-align:center;">
                                <input type="radio"
                                    name="status[<?php echo (int) $student['id']; ?>]"
                                    value="present"
                                    <?php echo $chosen === 'present' ? 'checked' : ''; ?>
                                    onchange="countMarked()">
                            </td>
                            <td style="text-align:center;">
                                <input type="radio"
                                    name="status[<?php echo (int) $student['id']; ?>]"
                                    value="absent"
                                    <?php echo $chosen === 'absent' ? 'checked' : ''; ?>
                                    onchange="countMarked()">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="margin-top:16px;">
                <button type="submit" class="btn btn-primary">Save attendance</button>
            </div>
        </div>
    </form>

    <script>
    function countMarked() {
        var names = {};
        var radios = document.querySelectorAll('input[type="radio"]:checked');
        radios.forEach(function (radio) {
            names[radio.name] = true;
        });
        document.getElementById('marked-count').textContent =
            'Marked: ' + Object.keys(names).length + ' of <?php echo count($students); ?>';
    }
    countMarked();
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
