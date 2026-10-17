<?php
// Administrator correction. Teachers cannot change a submitted list.

require_once __DIR__ . '/../../config/db.php';

$required_role = 'admin';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/attendance.php';

$pdo        = get_db_connection();
$page_title = 'Correct Attendance';
$errors     = [];
$attendance_id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if ($attendance_id <= 0) {
    redirect_to('/admin/attendance/view_by_class.php');
}

$stmt = $pdo->prepare(
    "SELECT
        a.*,
        s.student_code,
        s.full_name,
        c.class_name,
        sub.subject_name
     FROM attendance a
     JOIN students s ON s.id = a.student_id
     JOIN classes  c ON c.id = a.class_id
     JOIN subjects sub ON sub.id = a.subject_id
     WHERE a.id = :id"
);
$stmt->execute([':id' => $attendance_id]);
$record = $stmt->fetch();

if (!$record) {
    redirect_to('/admin/attendance/view_by_class.php');
}

$new_status = $record['status'];
$reason     = $record['correction_reason'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $errors[] = 'This form expired. Please try again.';
    } else {
        $new_status = $_POST['status'] ?? '';
        $reason     = trim($_POST['reason'] ?? '');
        try {
            correct_attendance($pdo, $attendance_id, $new_status, $reason, (int) $_SESSION['user_id']);
            redirect_to(
                '/admin/attendance/view_by_class.php?class_id=' . (int) $record['class_id']
                . '&subject_id=' . (int) $record['subject_id']
                . '&att_date=' . urlencode($record['attendance_date'])
            );
        } catch (Exception $e) {
            $errors[] = attendance_message($e->getMessage());
        }
    }
}

$back = BASE_URL . '/admin/attendance/view_by_class.php?class_id=' . (int) $record['class_id']
    . '&subject_id=' . (int) $record['subject_id']
    . '&att_date=' . urlencode($record['attendance_date']);

require_once __DIR__ . '/../../includes/header.php';
?>

<h1 class="page-title">Correct Attendance</h1>
<?php render_attendance_admin_nav('class'); ?>
<?php show_form_errors($errors); ?>

<div class="card">
    <div class="card-title">Record</div>
    <p>
        <strong><?php echo h($record['full_name']); ?></strong>
        (<?php echo h($record['student_code']); ?>)<br>
        <?php echo h($record['class_name'] . ' — ' . $record['subject_name']); ?><br>
        <?php echo date('d M Y', strtotime($record['attendance_date'])); ?><br>
        Current status: <?php echo attendance_status_badge($record['status']); ?>
    </p>
</div>

<form method="POST" action="">
    <?php csrf_field(); ?>
    <input type="hidden" name="id" value="<?php echo (int) $attendance_id; ?>">

    <div class="card">
        <div class="card-title">Correction</div>
        <div class="form-group">
            <label>New status</label>
            <label style="display:inline-block; margin-right:16px;">
                <input type="radio" name="status" value="present" <?php echo $new_status === 'present' ? 'checked' : ''; ?>>
                Present
            </label>
            <label style="display:inline-block;">
                <input type="radio" name="status" value="absent" <?php echo $new_status === 'absent' ? 'checked' : ''; ?>>
                Absent
            </label>
        </div>
        <div class="form-group">
            <label for="reason">Reason <span style="color:#e74c3c;">*</span></label>
            <input type="text" id="reason" name="reason" value="<?php echo h($reason); ?>"
                placeholder="Why is this being changed?" required>
        </div>
        <p style="color:#7f8c8d; font-size:13px;">
            Cancel leaves the original mark unchanged.
        </p>
    </div>

    <div style="display:flex; gap:10px; margin-bottom:30px;">
        <button type="submit" class="btn btn-primary">Save correction</button>
        <a href="<?php echo h($back); ?>" class="btn btn-secondary">Cancel</a>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
