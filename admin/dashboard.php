<?php
// Administrator home page. Counts come from the live tables.

require_once __DIR__ . '/../config/db.php';

$required_role = 'admin';
require_once __DIR__ . '/../includes/auth_check.php';

$pdo        = get_db_connection();
$page_title = 'Admin Dashboard';
$today      = app_today();

$total_students = $pdo->query("SELECT COUNT(*) FROM students WHERE is_active = 1")->fetchColumn();
$total_teachers = $pdo->query("SELECT COUNT(*) FROM teachers WHERE is_active = 1")->fetchColumn();
$total_classes  = $pdo->query("SELECT COUNT(*) FROM classes  WHERE is_active = 1")->fetchColumn();

$today_present = $pdo->prepare(
    "SELECT COUNT(DISTINCT student_id)
     FROM attendance
     WHERE attendance_date = :today AND status = 'present'"
);
$today_present->execute([':today' => $today]);
$attendance_today = $today_present->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">Admin Dashboard</h1>

<div class="summary-grid">
    <div class="summary-box blue">
        <div class="summary-number"><?php echo (int) $total_students; ?></div>
        <div class="summary-label">Active students</div>
    </div>
    <div class="summary-box green">
        <div class="summary-number"><?php echo (int) $total_teachers; ?></div>
        <div class="summary-label">Teachers</div>
    </div>
    <div class="summary-box purple">
        <div class="summary-number"><?php echo (int) $total_classes; ?></div>
        <div class="summary-label">Classes</div>
    </div>
    <div class="summary-box blue">
        <div class="summary-number"><?php echo (int) $attendance_today; ?></div>
        <div class="summary-label">Present today</div>
    </div>
</div>

<div class="card">
    <div class="card-title">Manage</div>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px;">
        <a href="<?php echo BASE_URL; ?>/admin/students/list_students.php" class="module-card">
            <div class="module-label">Students</div>
            <div class="module-desc">Register students and choose their subjects</div>
        </a>
        <a href="<?php echo BASE_URL; ?>/admin/teachers/list_teachers.php" class="module-card">
            <div class="module-label">Teachers</div>
            <div class="module-desc">Teacher profiles and class assignments</div>
        </a>
        <a href="<?php echo BASE_URL; ?>/admin/classes/list_classes.php" class="module-card">
            <div class="module-label">Classes</div>
            <div class="module-desc">Classes and the subjects they offer</div>
        </a>
        <a href="<?php echo BASE_URL; ?>/admin/subjects/list_subjects.php" class="module-card">
            <div class="module-label">Subjects</div>
            <div class="module-desc">Subject list used by classes</div>
        </a>
        <a href="<?php echo BASE_URL; ?>/admin/attendance/view_by_class.php" class="module-card">
            <div class="module-label">Attendance</div>
            <div class="module-desc">Daily sheets, student history, monthly report</div>
        </a>
        <a href="<?php echo BASE_URL; ?>/admin/manage_users.php" class="module-card">
            <div class="module-label">Accounts</div>
            <div class="module-desc">Login accounts and active status</div>
        </a>
    </div>
</div>

<style>
.module-card {
    display: block;
    background: #f8fafc;
    border: 1px solid #dde2e8;
    border-radius: 6px;
    padding: 18px 14px;
    text-decoration: none;
    color: #2c3e50;
}
.module-card:hover {
    border-color: #2980b9;
    text-decoration: none;
    color: #2c3e50;
}
.module-label { font-weight: bold; font-size: 14px; margin-bottom: 4px; }
.module-desc  { font-size: 12px; color: #7f8c8d; line-height: 1.4; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
