<?php
// One day's attendance for a class and subject.
// Students come from subject enrollment on that date, not the whole class.

require_once __DIR__ . '/../../config/db.php';

$required_role = 'admin';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/attendance.php';

$pdo        = get_db_connection();
$page_title = 'Attendance by Class';

$class_id   = (int) ($_GET['class_id'] ?? 0);
$subject_id = (int) ($_GET['subject_id'] ?? 0);
$att_date   = trim($_GET['att_date'] ?? app_today());

if (!is_valid_date($att_date)) {
    $att_date = app_today();
}

$classes = $pdo->query(
    "SELECT id, class_name FROM classes ORDER BY class_name"
)->fetchAll();

$subjects = [];
if ($class_id > 0) {
    $sub_stmt = $pdo->prepare(
        "SELECT s.id, s.subject_name
         FROM subjects s
         JOIN class_subjects cs ON cs.subject_id = s.id
         WHERE cs.class_id = :class_id
         ORDER BY s.subject_name"
    );
    $sub_stmt->execute([':class_id' => $class_id]);
    $subjects = $sub_stmt->fetchAll();
}

$students   = [];
$existing   = [];
$class_name = '';
$subject_name = '';
$summary    = summarise_attendance([]);

if ($class_id > 0 && $subject_id > 0) {
    $class_row = $pdo->prepare("SELECT class_name FROM classes WHERE id = :id");
    $class_row->execute([':id' => $class_id]);
    $class_name = (string) $class_row->fetchColumn();

    $subject_row = $pdo->prepare("SELECT subject_name FROM subjects WHERE id = :id");
    $subject_row->execute([':id' => $subject_id]);
    $subject_name = (string) $subject_row->fetchColumn();

    $students = students_eligible_on_date($pdo, $class_id, $subject_id, $att_date, false);
    $existing = attendance_rows_for_date($pdo, $class_id, $subject_id, $att_date);
    $summary  = summarise_attendance($existing);
}

require_once __DIR__ . '/../../includes/header.php';
?>

<h1 class="page-title">Attendance by Class</h1>
<?php render_attendance_admin_nav('class'); ?>

<div class="card no-print">
    <form method="GET" action="">
        <div class="form-row">
            <div class="form-group">
                <label for="class_id">Class</label>
                <select id="class_id" name="class_id" onchange="this.form.submit()">
                    <option value="0">— Select class —</option>
                    <?php foreach ($classes as $row): ?>
                        <option value="<?php echo (int) $row['id']; ?>" <?php echo $class_id === (int) $row['id'] ? 'selected' : ''; ?>>
                            <?php echo h($row['class_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="subject_id">Subject</label>
                <select id="subject_id" name="subject_id">
                    <option value="0">— Select subject —</option>
                    <?php foreach ($subjects as $row): ?>
                        <option value="<?php echo (int) $row['id']; ?>" <?php echo $subject_id === (int) $row['id'] ? 'selected' : ''; ?>>
                            <?php echo h($row['subject_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="att_date">Date</label>
                <input type="date" id="att_date" name="att_date" value="<?php echo h($att_date); ?>">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">View attendance</button>
    </form>
</div>

<?php if ($class_id > 0 && $subject_id > 0 && $class_name !== '' && $subject_name !== ''): ?>

<div class="print-only-header" style="display:none;">
    <h2 style="text-align:center;">Oxford Academy — Attendance sheet</h2>
    <p style="text-align:center;">
        <?php echo h($class_name . ' | ' . $subject_name . ' | ' . date('d M Y', strtotime($att_date))); ?>
    </p>
</div>
<style>@media print { .print-only-header { display:block !important; } }</style>

<div class="summary-grid no-print">
    <div class="summary-box green">
        <div class="summary-number"><?php echo (int) $summary['present']; ?></div>
        <div class="summary-label">Present</div>
    </div>
    <div class="summary-box red">
        <div class="summary-number"><?php echo (int) $summary['absent']; ?></div>
        <div class="summary-label">Absent</div>
    </div>
    <div class="summary-box blue">
        <div class="summary-number"><?php echo (int) $summary['recorded']; ?></div>
        <div class="summary-label">Recorded sessions</div>
    </div>
</div>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
        <div class="card-title" style="margin:0; border:none; padding:0;">
            <?php echo h($class_name . ' | ' . $subject_name . ' | ' . date('d M Y', strtotime($att_date))); ?>
        </div>
        <button id="print-btn" class="btn btn-print btn-sm no-print">Print</button>
    </div>

    <?php if (empty($students) && empty($existing)): ?>
        <p style="color:#7f8c8d;">No students were enrolled in this subject on that date.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student ID</th>
                        <th>Full Name</th>
                        <th>Status</th>
                        <th class="no-print">Marked by</th>
                        <th class="no-print">Correction</th>
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
                        <td class="no-print"><?php echo h($row['marked_by'] ?? '—'); ?></td>
                        <td class="no-print">
                            <?php if ($row): ?>
                                <a href="<?php echo BASE_URL; ?>/admin/attendance/correct.php?id=<?php echo (int) $row['id']; ?>">Correct</a>
                                <?php if (!empty($row['correction_reason'])): ?>
                                    <div style="font-size:12px; color:#7f8c8d;"><?php echo h($row['correction_reason']); ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
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
