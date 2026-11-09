<?php
// ============================================================
// admin/exams/list_exams.php — All examinations
//
// Lists all scheduled and past exams. Shows how many students
// have results entered vs how many are enrolled in the subject.
// Upcoming exams (today or later) are highlighted.
// ============================================================

require_once __DIR__ . '/../../config/db.php';

$required_role = 'admin';
require_once __DIR__ . '/../../includes/auth_check.php';

$pdo        = get_db_connection();
$page_title = 'Examinations';
$today      = date('Y-m-d');

// Fetch all exams with class, subject, results entered vs enrolled students taking that subject
$exams = $pdo->query(
    "SELECT
        e.id,
        e.exam_name,
        e.exam_date,
        e.total_marks,
        c.class_name,
        c.grade_level,
        s.subject_name,
        COUNT(DISTINCT r.student_id)   AS results_entered,
        COUNT(DISTINCT stu.id)         AS students_enrolled
     FROM examinations e
     JOIN classes  c  ON c.id = e.class_id
     JOIN subjects s  ON s.id = e.subject_id
     LEFT JOIN results r ON r.examination_id = e.id
     LEFT JOIN student_subjects ss
         ON ss.class_id   = e.class_id
        AND ss.subject_id = e.subject_id
        AND (ss.ends_on IS NULL OR ss.ends_on >= CURDATE())
     LEFT JOIN students stu
         ON stu.id = ss.student_id
        AND stu.is_active = 1
     GROUP BY e.id
     ORDER BY e.exam_date DESC"
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<h1 class="page-title">Examinations</h1>

<?php if (isset($_GET['scheduled'])): ?>
    <div class="alert alert-success">Exam scheduled successfully.</div>
<?php endif; ?>

<div style="display:flex; gap:10px; margin-bottom:16px; flex-wrap:wrap;">
    <a href="<?php echo BASE_URL; ?>/admin/exams/schedule_exam.php" class="btn btn-primary">+ Schedule New Exam</a>
    <a href="<?php echo BASE_URL; ?>/admin/exams/grading_criteria.php" class="btn btn-secondary">Grading Criteria</a>
    <a href="<?php echo BASE_URL; ?>/admin/exams/student_result_report.php" class="btn btn-secondary">Student Result Report</a>
</div>

<div class="card">
    <div class="card-title">All Examinations</div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Exam Name</th>
                    <th>Class</th>
                    <th>Subject</th>
                    <th>Date</th>
                    <th style="text-align:center;">Total Marks</th>
                    <th style="text-align:center;">Results Entered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($exams)): ?>
                <tr>
                    <td colspan="8" style="text-align:center; color:#7f8c8d;">
                        No exams scheduled yet.
                        <a href="<?php echo BASE_URL; ?>/admin/exams/schedule_exam.php">Schedule the first exam</a>.
                    </td>
                </tr>
                <?php endif; ?>

                <?php foreach ($exams as $i => $exam):
                    $is_upcoming = ($exam['exam_date'] >= $today);
                    $results_complete = ($exam['results_entered'] >= $exam['students_enrolled'] && $exam['students_enrolled'] > 0);
                ?>
                <tr style="<?php echo $is_upcoming ? 'background:#eaf4fb;' : ''; ?>">
                    <td><?php echo $i + 1; ?></td>
                    <td>
                        <strong><?php echo h($exam['exam_name']); ?></strong>
                        <?php if ($is_upcoming): ?>
                            <span class="badge" style="background:#d1ecf1; color:#0c5460; font-size:11px; margin-left:6px;">Upcoming</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo h($exam['grade_level'] . ' — ' . $exam['class_name']); ?></td>
                    <td><?php echo h($exam['subject_name']); ?></td>
                    <td><?php echo date('d M Y', strtotime($exam['exam_date'])); ?></td>
                    <td style="text-align:center;"><?php echo (int) $exam['total_marks']; ?></td>
                    <td style="text-align:center;">
                        <span style="color:<?php echo $results_complete ? '#27ae60' : '#e67e22'; ?>; font-weight:bold;">
                            <?php echo (int) $exam['results_entered']; ?> / <?php echo (int) $exam['students_enrolled']; ?>
                        </span>
                    </td>
                    <td style="white-space:nowrap;">
                        <a href="<?php echo BASE_URL; ?>/admin/exams/class_result_sheet.php?exam_id=<?php echo (int) $exam['id']; ?>"
                           class="btn btn-sm btn-secondary">Results</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
