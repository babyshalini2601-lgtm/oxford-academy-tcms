<?php
// ============================================================
// admin/dashboard.php — Administrator home dashboard
//
// Key performance metrics, daily attendance stats, monthly fees,
// upcoming examinations, and overdue fee balances.
// ============================================================

require_once __DIR__ . '/../config/db.php';

$required_role = 'admin';
require_once __DIR__ . '/../includes/auth_check.php';

$pdo        = get_db_connection();
$page_title = 'Admin Dashboard';
$today      = app_today();

// 1. Core entity counts
$total_students = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE is_active = 1")->fetchColumn();
$total_teachers = (int) $pdo->query("SELECT COUNT(*) FROM teachers WHERE is_active = 1")->fetchColumn();
$total_classes  = (int) $pdo->query("SELECT COUNT(*) FROM classes  WHERE is_active = 1")->fetchColumn();

// 2. Attendance metrics for today (explicitly distinguishing students vs subject sessions)
$today_present_stmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT student_id)
     FROM attendance
     WHERE attendance_date = :today AND status = 'present'"
);
$today_present_stmt->execute([':today' => $today]);
$distinct_students_present_today = (int) $today_present_stmt->fetchColumn();

$today_sessions_stmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS total_marked_sessions,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_sessions
     FROM attendance
     WHERE attendance_date = :today"
);
$today_sessions_stmt->execute([':today' => $today]);
$today_sessions_row = $today_sessions_stmt->fetch();
$total_marked_sessions = (int) ($today_sessions_row['total_marked_sessions'] ?? 0);
$present_sessions_today = (int) ($today_sessions_row['present_sessions'] ?? 0);

// 3. Financial metrics for current month
$current_month = date('Y-m');

$fees_month_stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(amount_paid), 0) FROM fee_payments WHERE payment_month = :month"
);
$fees_month_stmt->execute([':month' => $current_month]);
$fees_collected_month = (float) $fees_month_stmt->fetchColumn();

// Count overdue entries: enrolled students with a fee structure but no payment this month
$overdue_count_stmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM students s
     JOIN student_classes sc ON sc.student_id = s.id
     JOIN classes c          ON c.id = sc.class_id AND c.is_active = 1
     JOIN fee_structures fs
         ON fs.class_id = c.id
         AND fs.effective_from = (
             SELECT MAX(fs2.effective_from)
             FROM fee_structures fs2
             WHERE fs2.class_id = c.id
         )
     LEFT JOIN fee_payments fp
         ON fp.student_id    = s.id
         AND fp.class_id     = c.id
         AND fp.payment_month = :month
     WHERE s.is_active = 1
       AND (sc.ends_on IS NULL OR sc.ends_on >= CURDATE())
       AND fp.id IS NULL"
);
$overdue_count_stmt->execute([':month' => $current_month]);
$overdue_count = (int) $overdue_count_stmt->fetchColumn();

// 4. Overdue students preview (up to 5 students)
$overdue_preview_stmt = $pdo->prepare(
    "SELECT
        s.student_code,
        s.full_name,
        s.guardian_phone,
        c.class_name,
        c.grade_level,
        fs.monthly_amount
     FROM students s
     JOIN student_classes sc ON sc.student_id = s.id
     JOIN classes c          ON c.id = sc.class_id AND c.is_active = 1
     JOIN fee_structures fs
         ON fs.class_id = c.id
         AND fs.effective_from = (
             SELECT MAX(fs2.effective_from)
             FROM fee_structures fs2
             WHERE fs2.class_id = c.id
         )
     LEFT JOIN fee_payments fp
         ON fp.student_id    = s.id
         AND fp.class_id     = c.id
         AND fp.payment_month = :month
     WHERE s.is_active = 1
       AND (sc.ends_on IS NULL OR sc.ends_on >= CURDATE())
       AND fp.id IS NULL
     ORDER BY s.full_name ASC
     LIMIT 5"
);
$overdue_preview_stmt->execute([':month' => $current_month]);
$overdue_preview = $overdue_preview_stmt->fetchAll();

// 5. Upcoming examinations (up to 5)
$upcoming_exams_stmt = $pdo->query(
    "SELECT
        e.id,
        e.exam_name,
        e.exam_date,
        e.total_marks,
        c.class_name,
        c.grade_level,
        s.subject_name
     FROM examinations e
     JOIN classes  c ON c.id = e.class_id
     JOIN subjects s ON s.id = e.subject_id
     WHERE e.exam_date >= CURDATE()
     ORDER BY e.exam_date ASC
     LIMIT 5"
);
$upcoming_exams = $upcoming_exams_stmt->fetchAll();
$upcoming_exams_count = count($upcoming_exams);

require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">Admin Dashboard</h1>

<div class="summary-grid">
    <div class="summary-box blue">
        <div class="summary-number"><?php echo $total_students; ?></div>
        <div class="summary-label">Active students</div>
    </div>
    <div class="summary-box green">
        <div class="summary-number"><?php echo $total_teachers; ?></div>
        <div class="summary-label">Teachers</div>
    </div>
    <div class="summary-box purple">
        <div class="summary-number"><?php echo $total_classes; ?></div>
        <div class="summary-label">Classes</div>
    </div>
    <div class="summary-box blue">
        <div class="summary-number"><?php echo $distinct_students_present_today; ?></div>
        <div class="summary-label">Students present today</div>
    </div>
    <div class="summary-box green">
        <div class="summary-number">LKR <?php echo number_format($fees_collected_month, 0); ?></div>
        <div class="summary-label">Fees collected — <?php echo date('M Y'); ?></div>
    </div>
    <div class="summary-box <?php echo $overdue_count > 0 ? 'red' : 'green'; ?>">
        <div class="summary-number"><?php echo $overdue_count; ?></div>
        <div class="summary-label">Overdue students — <?php echo date('M Y'); ?></div>
    </div>
</div>

<div class="card">
    <div class="card-title">Management Modules</div>
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
        <a href="<?php echo BASE_URL; ?>/admin/fees/set_fee_structure.php" class="module-card">
            <div class="module-label">Fees</div>
            <div class="module-desc">Fee structures, record payments, overdue list</div>
        </a>
        <a href="<?php echo BASE_URL; ?>/admin/exams/list_exams.php" class="module-card">
            <div class="module-label">Examinations</div>
            <div class="module-desc">Schedule exams, grading criteria, result sheets</div>
        </a>
        <a href="<?php echo BASE_URL; ?>/admin/reports/student_progress.php" class="module-card">
            <div class="module-label">Reports</div>
            <div class="module-desc">Consolidated student progress &amp; performance</div>
        </a>
        <a href="<?php echo BASE_URL; ?>/admin/manage_users.php" class="module-card">
            <div class="module-label">Accounts</div>
            <div class="module-desc">Login accounts and active status</div>
        </a>
    </div>
</div>

<!-- Two-column grid for upcoming exams and overdue fees -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:16px;">
    <!-- Upcoming Exams -->
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <div class="card-title" style="margin:0; border:none; padding:0;">Upcoming Examinations</div>
            <a href="<?php echo BASE_URL; ?>/admin/exams/list_exams.php" class="btn btn-sm btn-secondary">View All</a>
        </div>
        <?php if (empty($upcoming_exams)): ?>
            <p style="color:#7f8c8d; font-size:13px;">No examinations currently scheduled.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Exam</th>
                            <th>Class / Subject</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcoming_exams as $ex): ?>
                        <tr>
                            <td><strong><?php echo h($ex['exam_name']); ?></strong></td>
                            <td><?php echo h($ex['grade_level'] . ' — ' . $ex['subject_name']); ?></td>
                            <td><span style="font-size:12px;"><?php echo date('d M Y', strtotime($ex['exam_date'])); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Overdue Fees Preview -->
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <div class="card-title" style="margin:0; border:none; padding:0;">
                Overdue Fees — <?php echo date('F Y'); ?>
            </div>
            <a href="<?php echo BASE_URL; ?>/admin/fees/overdue_list.php" class="btn btn-sm btn-secondary">Full List (<?php echo $overdue_count; ?>)</a>
        </div>
        <?php if (empty($overdue_preview)): ?>
            <div class="alert alert-success" style="font-size:13px; margin:0;">
                All active students have settled their fees for <?php echo date('F Y'); ?>.
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Class</th>
                            <th style="text-align:right;">Amount Due</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($overdue_preview as $od): ?>
                        <tr>
                            <td>
                                <strong><?php echo h($od['full_name']); ?></strong>
                                <br><span style="font-size:11px; color:#7f8c8d; font-family:monospace;"><?php echo h($od['student_code']); ?></span>
                            </td>
                            <td><?php echo h($od['grade_level'] . ' — ' . $od['class_name']); ?></td>
                            <td style="text-align:right; color:#c0392b; font-weight:bold;">
                                LKR <?php echo number_format($od['monthly_amount'], 2); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
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
    transition: border-color 0.15s ease-in-out;
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
