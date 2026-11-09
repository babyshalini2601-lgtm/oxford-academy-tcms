<?php
// Student / parent home page. The student sees only their own record.

require_once __DIR__ . '/../config/db.php';

$required_role = 'student';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/attendance.php';

$pdo        = get_db_connection();
$page_title = 'My Dashboard';
$student_id = (int) $_SESSION['linked_id'];

$stmt = $pdo->prepare("SELECT * FROM students WHERE id = :id");
$stmt->execute([':id' => $student_id]);
$student = $stmt->fetch();

$month_start = date('Y-m-01');
$month_end   = date('Y-m-t');

$att_stmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS total_days,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_days
     FROM attendance
     WHERE student_id = :student_id
       AND attendance_date BETWEEN :start AND :end"
);
$att_stmt->execute([
    ':student_id' => $student_id,
    ':start'      => $month_start,
    ':end'        => $month_end,
]);
$att_row = $att_stmt->fetch();

$total_days   = (int) ($att_row['total_days'] ?? 0);
$present_days = (int) ($att_row['present_days'] ?? 0);
$att_label    = format_attendance_percentage($present_days, $total_days);

// Current month fee status: how many classes are overdue
$current_month = date('Y-m');
$fee_status_stmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS total_classes,
        SUM(CASE WHEN fp.id IS NOT NULL THEN 1 ELSE 0 END) AS paid_classes
     FROM student_classes sc
     JOIN classes c ON c.id = sc.class_id AND c.is_active = 1
     JOIN fee_structures fs
         ON fs.class_id = c.id
         AND fs.effective_from = (
             SELECT MAX(fs2.effective_from)
             FROM fee_structures fs2
             WHERE fs2.class_id = c.id
         )
     LEFT JOIN fee_payments fp
         ON fp.student_id    = sc.student_id
         AND fp.class_id     = c.id
         AND fp.payment_month = :month
     WHERE sc.student_id = :student_id"
);
$fee_status_stmt->execute([
    ':month'      => $current_month,
    ':student_id' => $student_id,
]);
$fee_row     = $fee_status_stmt->fetch();
$fee_classes = (int) ($fee_row['total_classes'] ?? 0);
$fee_paid    = (int) ($fee_row['paid_classes']  ?? 0);
$fee_overdue = $fee_classes - $fee_paid;

// Exam results count & average
$exam_stmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS total_exams,
        AVG((r.marks_obtained / e.total_marks) * 100) AS avg_pct
     FROM results r
     JOIN examinations e ON e.id = r.examination_id
     WHERE r.student_id = :student_id"
);
$exam_stmt->execute([':student_id' => $student_id]);
$exam_row = $exam_stmt->fetch();
$student_exam_count = (int) ($exam_row['total_exams'] ?? 0);
$student_exam_avg   = $student_exam_count > 0 ? round((float) $exam_row['avg_pct'], 1) : null;

require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">Welcome, <?php echo h($_SESSION['full_name']); ?></h1>

<div class="summary-grid">
    <div class="summary-box blue">
        <div class="summary-number" style="font-size:<?php echo $total_days === 0 ? '16px' : '22px'; ?>;">
            <?php echo h($att_label); ?>
        </div>
        <div class="summary-label">Attendance this month</div>
    </div>
    <?php if ($fee_classes > 0): ?>
    <div class="summary-box <?php echo $fee_overdue > 0 ? 'red' : 'green'; ?>">
        <div class="summary-number">
            <?php echo $fee_overdue > 0 ? $fee_overdue . ' unpaid' : 'Paid'; ?>
        </div>
        <div class="summary-label">Fee status — <?php echo date('M Y'); ?></div>
    </div>
    <?php endif; ?>
    <?php if ($student_exam_count > 0): ?>
    <div class="summary-box purple">
        <div class="summary-number"><?php echo $student_exam_avg; ?>%</div>
        <div class="summary-label">Exam average (<?php echo $student_exam_count; ?> exams)</div>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-title">My profile</div>
    <table style="width:auto;">
        <tr>
            <td style="padding: 6px 20px 6px 0; font-weight:bold; color:#7f8c8d;">Student ID</td>
            <td><?php echo h($student['student_code']); ?></td>
        </tr>
        <tr>
            <td style="padding: 6px 20px 6px 0; font-weight:bold; color:#7f8c8d;">Full name</td>
            <td><?php echo h($student['full_name']); ?></td>
        </tr>
        <tr>
            <td style="padding: 6px 20px 6px 0; font-weight:bold; color:#7f8c8d;">Guardian</td>
            <td><?php echo h($student['guardian_name'] ?? '—'); ?></td>
        </tr>
        <tr>
            <td style="padding: 6px 20px 6px 0; font-weight:bold; color:#7f8c8d;">Enrolled</td>
            <td><?php echo date('d M Y', strtotime($student['enrolled_date'])); ?></td>
        </tr>
    </table>
</div>

<div style="margin-top: 4px; display:flex; gap:10px; flex-wrap:wrap;">
    <a href="<?php echo BASE_URL; ?>/student/attendance.php"      class="btn btn-primary">My Attendance</a>
    <a href="<?php echo BASE_URL; ?>/student/fees.php"            class="btn btn-secondary">My Fees</a>
    <a href="<?php echo BASE_URL; ?>/student/results.php"         class="btn btn-secondary">My Results</a>
    <a href="<?php echo BASE_URL; ?>/student/progress_report.php" class="btn btn-secondary">My Progress Report</a>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
