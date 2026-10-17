<?php
// ============================================================
// student/results.php — Student/parent view of own exam results
//
// A read-only page. The student (or parent) sees all their
// exam results grouped by subject, plus overall statistics.
// ============================================================

require_once __DIR__ . '/../config/db.php';

$required_role = 'student';
require_once __DIR__ . '/../includes/auth_check.php';

$pdo        = get_db_connection();
$page_title = 'My Exam Results';
$student_id = $_SESSION['linked_id'];

// Load student name
$stu_stmt = $pdo->prepare("SELECT full_name, student_code FROM students WHERE id = :id");
$stu_stmt->execute([':id' => $student_id]);
$student = $stu_stmt->fetch();

// Fetch all exam results for this student
$res_stmt = $pdo->prepare(
    "SELECT
        r.marks_obtained,
        r.grade,
        e.exam_name,
        e.exam_date,
        e.total_marks,
        c.class_name,
        c.grade_level,
        s.subject_name
     FROM results r
     JOIN examinations e ON e.id = r.examination_id
     JOIN classes      c ON c.id = e.class_id
     JOIN subjects     s ON s.id = e.subject_id
     WHERE r.student_id = :student_id
     ORDER BY s.subject_name ASC, e.exam_date DESC"
);
$res_stmt->execute([':student_id' => $student_id]);
$all_results = $res_stmt->fetchAll();

// Group results by subject for easier display
$by_subject = [];
foreach ($all_results as $r) {
    $by_subject[$r['subject_name']][] = $r;
}

// Overall stats
$total_exams   = count($all_results);
$overall_avg   = 0;
if ($total_exams > 0) {
    $sum_pct = array_reduce($all_results, function ($carry, $r) {
        return $carry + ($r['marks_obtained'] / $r['total_marks'] * 100);
    }, 0);
    $overall_avg = round($sum_pct / $total_exams, 1);
}

require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">My Exam Results</h1>

<?php if ($total_exams === 0): ?>
    <div class="card">
        <p style="color:#7f8c8d; text-align:center; padding:30px 0;">
            No exam results have been entered for your account yet.
        </p>
    </div>
<?php else: ?>

<!-- Summary -->
<div class="summary-grid">
    <div class="summary-box blue">
        <div class="summary-number"><?php echo $total_exams; ?></div>
        <div class="summary-label">Exams Taken</div>
    </div>
    <div class="summary-box <?php echo $overall_avg >= 75 ? 'green' : ($overall_avg >= 50 ? 'orange' : 'red'); ?>">
        <div class="summary-number"><?php echo $overall_avg; ?>%</div>
        <div class="summary-label">Overall Average</div>
    </div>
    <div class="summary-box purple">
        <div class="summary-number"><?php echo count($by_subject); ?></div>
        <div class="summary-label">Subjects</div>
    </div>
</div>

<!-- Results grouped by subject -->
<?php foreach ($by_subject as $subject_name => $rows): ?>
<div class="card">
    <div class="card-title"><?php echo htmlspecialchars($subject_name); ?></div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Exam Name</th>
                    <th>Class</th>
                    <th>Date</th>
                    <th style="text-align:center;">Marks</th>
                    <th style="text-align:center;">Percentage</th>
                    <th style="text-align:center;">Grade</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $i => $r):
                    $pct = round(($r['marks_obtained'] / $r['total_marks']) * 100, 1);
                ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td><?php echo htmlspecialchars($r['exam_name']); ?></td>
                    <td><?php echo htmlspecialchars($r['grade_level'] . ' — ' . $r['class_name']); ?></td>
                    <td><?php echo date('d M Y', strtotime($r['exam_date'])); ?></td>
                    <td style="text-align:center;"><?php echo $r['marks_obtained']; ?> / <?php echo $r['total_marks']; ?></td>
                    <td style="text-align:center;">
                        <span style="color:<?php echo $pct >= 75 ? '#27ae60' : ($pct >= 50 ? '#e67e22' : '#e74c3c'); ?>; font-weight:bold;">
                            <?php echo $pct; ?>%
                        </span>
                    </td>
                    <td style="text-align:center;">
                        <strong style="font-size:16px;"><?php echo htmlspecialchars($r['grade']); ?></strong>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
