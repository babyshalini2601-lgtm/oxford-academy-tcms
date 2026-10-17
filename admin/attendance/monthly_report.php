<?php
// Monthly percentages by recorded sessions.
// A student who takes Mathematics only is not treated as absent from Science.
// Combined overall % counts sessions, not unique calendar days.

require_once __DIR__ . '/../../config/db.php';

$required_role = 'admin';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/attendance.php';

$pdo        = get_db_connection();
$page_title = 'Monthly Attendance Report';

$class_id = (int) ($_GET['class_id'] ?? 0);
$month    = trim($_GET['month'] ?? date('Y-m'));

if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}

$month_start = $month . '-01';
$month_end   = date('Y-m-t', strtotime($month_start));

$classes = $pdo->query(
    "SELECT id, class_name FROM classes ORDER BY class_name"
)->fetchAll();

$subjects    = [];
$students    = [];
$report_data = [];
$class_name  = '';

if ($class_id > 0) {
    $class_row = $pdo->prepare("SELECT class_name FROM classes WHERE id = :id");
    $class_row->execute([':id' => $class_id]);
    $class_name = (string) $class_row->fetchColumn();

    $sub_stmt = $pdo->prepare(
        "SELECT s.id, s.subject_name
         FROM subjects s
         JOIN class_subjects cs ON cs.subject_id = s.id
         WHERE cs.class_id = :class_id
         ORDER BY s.subject_name"
    );
    $sub_stmt->execute([':class_id' => $class_id]);
    $subjects = $sub_stmt->fetchAll();

    $stu_stmt = $pdo->prepare(
        "SELECT DISTINCT s.id, s.student_code, s.full_name
         FROM students s
         JOIN student_subjects ss ON ss.student_id = s.id
         WHERE ss.class_id = :class_id
           AND ss.starts_on <= :month_end
           AND (ss.ends_on IS NULL OR ss.ends_on >= :month_start)
         ORDER BY s.full_name"
    );
    $stu_stmt->execute([
        ':class_id'    => $class_id,
        ':month_end'   => $month_end,
        ':month_start' => $month_start,
    ]);
    $students = $stu_stmt->fetchAll();

    $att_stmt = $pdo->prepare(
        "SELECT student_id, subject_id, status
         FROM attendance
         WHERE class_id = :class_id
           AND attendance_date BETWEEN :start AND :end"
    );
    $att_stmt->execute([
        ':class_id' => $class_id,
        ':start'    => $month_start,
        ':end'      => $month_end,
    ]);

    foreach ($att_stmt as $row) {
        $sid = (int) $row['student_id'];
        $bid = (int) $row['subject_id'];
        if (!isset($report_data[$sid][$bid])) {
            $report_data[$sid][$bid] = ['present' => 0, 'recorded' => 0];
        }
        $report_data[$sid][$bid]['recorded']++;
        if ($row['status'] === 'present') {
            $report_data[$sid][$bid]['present']++;
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<h1 class="page-title">Monthly Attendance Report</h1>
<?php render_attendance_admin_nav('month'); ?>

<div class="card no-print">
    <form method="GET" action="">
        <div class="form-row">
            <div class="form-group">
                <label for="class_id">Class</label>
                <select id="class_id" name="class_id">
                    <option value="0">— Select class —</option>
                    <?php foreach ($classes as $row): ?>
                        <option value="<?php echo (int) $row['id']; ?>" <?php echo $class_id === (int) $row['id'] ? 'selected' : ''; ?>>
                            <?php echo h($row['class_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="month">Month</label>
                <input type="month" id="month" name="month" value="<?php echo h($month); ?>">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Generate report</button>
    </form>
</div>

<?php if ($class_id > 0 && $class_name !== ''): ?>

<?php if (empty($students)): ?>
    <div class="alert alert-info">No students were enrolled in this class during that month.</div>
<?php elseif (empty($subjects)): ?>
    <div class="alert alert-info">This class has no subjects.</div>
<?php else: ?>

<div class="print-only-header" style="display:none;">
    <h2 style="text-align:center; margin-bottom:4px;">Oxford Academy</h2>
    <h3 style="text-align:center; margin-bottom:4px;">Monthly attendance report</h3>
    <p style="text-align:center;">
        <?php echo h($class_name); ?>
        &nbsp;|&nbsp;
        <?php echo date('F Y', strtotime($month_start)); ?>
    </p>
</div>
<style>@media print { .print-only-header { display:block !important; } }</style>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
        <div class="card-title" style="margin:0; border:none; padding:0;">
            <?php echo h($class_name); ?>
            &nbsp;|&nbsp;
            <?php echo date('F Y', strtotime($month_start)); ?>
        </div>
        <button id="print-btn" class="btn btn-print btn-sm no-print">Print report</button>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <?php foreach ($subjects as $subject): ?>
                        <th style="text-align:center; min-width:120px;"><?php echo h($subject['subject_name']); ?></th>
                    <?php endforeach; ?>
                    <th style="text-align:center;">Overall</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $index => $student):
                    $overall_present  = 0;
                    $overall_recorded = 0;
                ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td>
                        <strong><?php echo h($student['full_name']); ?></strong><br>
                        <small style="color:#7f8c8d;"><?php echo h($student['student_code']); ?></small>
                    </td>
                    <?php foreach ($subjects as $subject):
                        $cell     = $report_data[(int) $student['id']][(int) $subject['id']] ?? ['present' => 0, 'recorded' => 0];
                        $present  = (int) $cell['present'];
                        $recorded = (int) $cell['recorded'];
                        $percent  = attendance_percentage($present, $recorded);
                        $overall_present  += $present;
                        $overall_recorded += $recorded;

                        if ($percent === null) {
                            $cell_color = '#f0f2f5';
                            $text_color = '#7f8c8d';
                        } elseif ($percent >= 75) {
                            $cell_color = '#d4edda';
                            $text_color = '#155724';
                        } elseif ($percent >= 50) {
                            $cell_color = '#fff3cd';
                            $text_color = '#856404';
                        } else {
                            $cell_color = '#f8d7da';
                            $text_color = '#721c24';
                        }
                    ?>
                    <td style="text-align:center; background:<?php echo $cell_color; ?>; color:<?php echo $text_color; ?>;">
                        <?php if ($percent === null): ?>
                            <span style="font-size:12px;">No attendance recorded</span>
                        <?php else: ?>
                            <strong><?php echo $percent; ?>%</strong><br>
                            <small><?php echo $present; ?>/<?php echo $recorded; ?></small>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>

                    <?php
                        $overall = attendance_percentage($overall_present, $overall_recorded);
                        $ov_color = ($overall === null) ? '#f0f2f5'
                            : (($overall >= 75) ? '#d4edda'
                            : (($overall >= 50) ? '#fff3cd' : '#f8d7da'));
                        $ov_text = ($overall === null) ? '#7f8c8d'
                            : (($overall >= 75) ? '#155724'
                            : (($overall >= 50) ? '#856404' : '#721c24'));
                    ?>
                    <td style="text-align:center; font-weight:bold; background:<?php echo $ov_color; ?>; color:<?php echo $ov_text; ?>;">
                        <?php echo $overall === null ? 'No attendance recorded' : $overall . '%'; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p style="font-size:12px; color:#7f8c8d; margin-top:12px;" class="no-print">
        Percentage = present sessions ÷ recorded sessions. Days with no record are not counted as absence.
        A student who did not take a subject is not treated as absent from it.
    </p>
</div>

<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
