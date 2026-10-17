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

<div style="margin-top: 4px;">
    <a href="<?php echo BASE_URL; ?>/student/attendance.php" class="btn btn-primary">My Attendance</a>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
