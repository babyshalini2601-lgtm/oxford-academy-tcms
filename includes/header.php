<?php
// ------------------------------------------------------------
// header.php — Shared HTML header and navigation bar.
//
// Set $page_title on the page before including this file.
// Requires BASE_URL (defined in config/db.php) and an active session.
// ------------------------------------------------------------

if (!isset($page_title)) {
    $page_title = 'Oxford Academy';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($page_title); ?> — Oxford Academy</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>
<body>

<header class="site-header">
    <div class="header-inner">
        <div class="header-logo">
            <span class="logo-text">Oxford Academy</span>
            <span class="logo-sub">Tuition Centre Management System</span>
        </div>

        <?php if (isset($_SESSION['user_id'])): ?>
        <nav class="main-nav">
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="<?php echo BASE_URL; ?>/admin/dashboard.php">Dashboard</a>
                <a href="<?php echo BASE_URL; ?>/admin/students/list_students.php">Students</a>
                <a href="<?php echo BASE_URL; ?>/admin/teachers/list_teachers.php">Teachers</a>
                <a href="<?php echo BASE_URL; ?>/admin/classes/list_classes.php">Classes</a>
                <a href="<?php echo BASE_URL; ?>/admin/subjects/list_subjects.php">Subjects</a>
                <a href="<?php echo BASE_URL; ?>/admin/attendance/view_by_class.php">Attendance</a>
                <a href="<?php echo BASE_URL; ?>/admin/manage_users.php">Accounts</a>

            <?php elseif ($_SESSION['role'] === 'teacher'): ?>
                <a href="<?php echo BASE_URL; ?>/teacher/dashboard.php">Dashboard</a>
                <a href="<?php echo BASE_URL; ?>/teacher/attendance/mark_attendance.php">Mark Attendance</a>
                <a href="<?php echo BASE_URL; ?>/teacher/my_students.php">My Students</a>

            <?php elseif ($_SESSION['role'] === 'student'): ?>
                <a href="<?php echo BASE_URL; ?>/student/dashboard.php">Dashboard</a>
                <a href="<?php echo BASE_URL; ?>/student/attendance.php">My Attendance</a>
            <?php endif; ?>
        </nav>

        <div class="header-user">
            <span class="user-name">
                <?php echo h($_SESSION['full_name']); ?>
                <span class="user-role"><?php echo ucfirst($_SESSION['role']); ?></span>
            </span>
            <a href="<?php echo BASE_URL; ?>/change_password.php" class="btn-change-password">Change password</a>
            <a href="<?php echo BASE_URL; ?>/logout.php" class="btn-logout">Logout</a>
        </div>
        <?php endif; ?>
    </div>
</header>

<main class="page-content">
