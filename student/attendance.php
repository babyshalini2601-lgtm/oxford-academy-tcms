<?php
// The logged-in student sees only their own attendance.

require_once __DIR__ . '/../config/db.php';

$required_role = 'student';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/attendance.php';

$pdo        = get_db_connection();
$page_title = 'My Attendance';
$student_id = (int) $_SESSION['linked_id'];

$date_from = trim($_GET['date_from'] ?? date('Y-m-01'));
$date_to   = trim($_GET['date_to'] ?? app_today());

if (!is_valid_date($date_from)) {
    $date_from = date('Y-m-01');
}
if (!is_valid_date($date_to)) {
    $date_to = app_today();
}

$stmt = $pdo->prepare(
    "SELECT
        a.attendance_date,
        a.status,
        c.class_name,
        s.subject_name
     FROM attendance a
     JOIN classes  c ON c.id = a.class_id
     JOIN subjects s ON s.id = a.subject_id
     WHERE a.student_id = :student_id
       AND a.attendance_date BETWEEN :date_from AND :date_to
     ORDER BY a.attendance_date DESC, s.subject_name"
);
$stmt->execute([
    ':student_id' => $student_id,
    ':date_from'  => $date_from,
    ':date_to'    => $date_to,
]);
$records = $stmt->fetchAll();
$summary = summarise_attendance($records);

require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">My Attendance</h1>

<div class="card no-print">
    <div class="card-title">Filter by date range</div>
    <form method="GET" action="">
        <div class="form-row">
            <div class="form-group">
                <label for="date_from">From</label>
                <input type="date" id="date_from" name="date_from" value="<?php echo h($date_from); ?>">
            </div>
            <div class="form-group">
                <label for="date_to">To</label>
                <input type="date" id="date_to" name="date_to" value="<?php echo h($date_to); ?>">
            </div>
            <div class="form-group" style="align-self:flex-end;">
                <button type="submit" class="btn btn-primary">Apply</button>
            </div>
        </div>
    </form>
</div>

<div class="summary-grid">
    <div class="summary-box blue">
        <div class="summary-number"><?php echo (int) $summary['recorded']; ?></div>
        <div class="summary-label">Recorded sessions</div>
    </div>
    <div class="summary-box green">
        <div class="summary-number"><?php echo (int) $summary['present']; ?></div>
        <div class="summary-label">Present</div>
    </div>
    <div class="summary-box orange">
        <div class="summary-number"><?php echo (int) $summary['absent']; ?></div>
        <div class="summary-label">Absent</div>
    </div>
    <div class="summary-box <?php echo ($summary['percent'] !== null && $summary['percent'] >= 75) ? 'green' : 'orange'; ?>">
        <div class="summary-number" style="font-size:<?php echo $summary['percent'] === null ? '16px' : '22px'; ?>;">
            <?php echo h($summary['label']); ?>
        </div>
        <div class="summary-label">Attendance</div>
    </div>
</div>

<div class="card">
    <div class="card-title">
        Attendance records
        <span style="font-size:13px; font-weight:normal; color:#7f8c8d; margin-left:8px;">
            <?php echo date('d M Y', strtotime($date_from)); ?>
            to
            <?php echo date('d M Y', strtotime($date_to)); ?>
        </span>
    </div>

    <?php if (empty($records)): ?>
        <p style="color:#7f8c8d;">No attendance recorded for this date range.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Class</th>
                        <th>Subject</th>
                        <th style="text-align:center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $index => $row): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><?php echo date('d M Y', strtotime($row['attendance_date'])); ?></td>
                        <td><?php echo h($row['class_name']); ?></td>
                        <td><?php echo h($row['subject_name']); ?></td>
                        <td style="text-align:center;"><?php echo attendance_status_badge($row['status']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
