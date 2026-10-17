<?php
// One student's recorded sessions. Percentage uses sessions, not unique days.
// Zero records show "No attendance recorded", not 0%.

require_once __DIR__ . '/../../config/db.php';

$required_role = 'admin';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/attendance.php';

$pdo        = get_db_connection();
$page_title = 'Attendance by Student';

$student_id = (int) ($_GET['student_id'] ?? 0);
$subject_id = (int) ($_GET['subject_id'] ?? 0);
$date_from  = trim($_GET['date_from'] ?? date('Y-m-01'));
$date_to    = trim($_GET['date_to'] ?? app_today());

if (!is_valid_date($date_from)) {
    $date_from = date('Y-m-01');
}
if (!is_valid_date($date_to)) {
    $date_to = app_today();
}

$students = $pdo->query(
    "SELECT id, student_code, full_name, is_active
     FROM students
     ORDER BY is_active DESC, full_name"
)->fetchAll();

$subjects = $pdo->query(
    "SELECT id, subject_name FROM subjects ORDER BY subject_name"
)->fetchAll();

$records     = [];
$student     = null;
$summary     = summarise_attendance([]);

if ($student_id > 0) {
    $stu_stmt = $pdo->prepare("SELECT * FROM students WHERE id = :id");
    $stu_stmt->execute([':id' => $student_id]);
    $student = $stu_stmt->fetch();

    if ($student) {
        $sql = "SELECT
                    a.id,
                    a.attendance_date,
                    a.status,
                    a.class_id,
                    a.subject_id,
                    c.class_name,
                    s.subject_name
                FROM attendance a
                JOIN classes  c ON c.id = a.class_id
                JOIN subjects s ON s.id = a.subject_id
                WHERE a.student_id = :student_id
                  AND a.attendance_date BETWEEN :date_from AND :date_to";
        $params = [
            ':student_id' => $student_id,
            ':date_from'  => $date_from,
            ':date_to'    => $date_to,
        ];

        if ($subject_id > 0) {
            $sql .= " AND a.subject_id = :subject_id";
            $params[':subject_id'] = $subject_id;
        }

        $sql .= " ORDER BY a.attendance_date DESC, s.subject_name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll();
        $summary = summarise_attendance($records);
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<h1 class="page-title">Attendance by Student</h1>
<?php render_attendance_admin_nav('student'); ?>

<div class="card no-print">
    <form method="GET" action="">
        <div class="form-row">
            <div class="form-group">
                <label for="student_id">Student</label>
                <select id="student_id" name="student_id">
                    <option value="0">— Select student —</option>
                    <?php foreach ($students as $row): ?>
                        <option value="<?php echo (int) $row['id']; ?>" <?php echo $student_id === (int) $row['id'] ? 'selected' : ''; ?>>
                            <?php echo h($row['student_code'] . ' — ' . $row['full_name']); ?>
                            <?php echo $row['is_active'] ? '' : ' (inactive)'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="subject_id">Subject</label>
                <select id="subject_id" name="subject_id">
                    <option value="0">All subjects</option>
                    <?php foreach ($subjects as $row): ?>
                        <option value="<?php echo (int) $row['id']; ?>" <?php echo $subject_id === (int) $row['id'] ? 'selected' : ''; ?>>
                            <?php echo h($row['subject_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="date_from">From</label>
                <input type="date" id="date_from" name="date_from" value="<?php echo h($date_from); ?>">
            </div>
            <div class="form-group">
                <label for="date_to">To</label>
                <input type="date" id="date_to" name="date_to" value="<?php echo h($date_to); ?>">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">View attendance</button>
    </form>
</div>

<?php if ($student): ?>

<div class="print-only-header" style="display:none;">
    <h2 style="text-align:center;">Oxford Academy — Student attendance</h2>
    <p style="text-align:center;">
        <?php echo h($student['full_name'] . ' (' . $student['student_code'] . ')'); ?>
        &nbsp;|&nbsp;
        <?php echo date('d M Y', strtotime($date_from)); ?>
        to
        <?php echo date('d M Y', strtotime($date_to)); ?>
    </p>
</div>
<style>@media print { .print-only-header { display:block !important; } }</style>

<div class="summary-grid no-print">
    <div class="summary-box blue">
        <div class="summary-number"><?php echo (int) $summary['recorded']; ?></div>
        <div class="summary-label">Recorded sessions</div>
    </div>
    <div class="summary-box green">
        <div class="summary-number"><?php echo (int) $summary['present']; ?></div>
        <div class="summary-label">Present</div>
    </div>
    <div class="summary-box red">
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
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
        <div class="card-title" style="margin:0; border:none; padding:0;">
            <?php echo h($student['full_name'] . ' (' . $student['student_code'] . ')'); ?>
        </div>
        <button id="print-btn" class="btn btn-print btn-sm no-print">Print</button>
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
                        <th>Status</th>
                        <th class="no-print">Correction</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $index => $row): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><?php echo date('d M Y', strtotime($row['attendance_date'])); ?></td>
                        <td><?php echo h($row['class_name']); ?></td>
                        <td><?php echo h($row['subject_name']); ?></td>
                        <td><?php echo attendance_status_badge($row['status']); ?></td>
                        <td class="no-print">
                            <a href="<?php echo BASE_URL; ?>/admin/attendance/correct.php?id=<?php echo (int) $row['id']; ?>">Correct</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
