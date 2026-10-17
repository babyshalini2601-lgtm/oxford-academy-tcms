<?php
require_once __DIR__ . '/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    redirect_to('/login.php');
}

switch ($_SESSION['role']) {
    case 'admin':
        redirect_to('/admin/dashboard.php');
        break;
    case 'teacher':
        redirect_to('/teacher/dashboard.php');
        break;
    case 'student':
        redirect_to('/student/dashboard.php');
        break;
    default:
        redirect_to('/login.php');
}
