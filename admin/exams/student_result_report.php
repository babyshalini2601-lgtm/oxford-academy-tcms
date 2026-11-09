<?php
// ============================================================
// admin/exams/student_result_report.php — All results for one student
//
// Admin selects a student and sees every exam result for that
// student across all classes and subjects.
// Shows marks, percentage, grade, and a summary of overall
// performance. Printable.
// ============================================================

require_once __DIR__ . '/../../config/db.php';

$required_role = 'admin';
require_once __DIR__ . '/../../includes/auth_check.php';

$pdo        = get_db_connection();
$page_title = 'Student Result Report';

$selected_student_id = (int) ($_GET['student_id'] ?? 0);

// All active students for the dropdown
$all_students = $pdo->query(
    "SELECT id, student_code, full_name FROM students WHERE is_active = 1 ORDER BY full_name"
)->fetchAll();

$student = null;
$results = [];

if ($selected_student_id > 0) {
    $stu_stmt = $pdo->prepare("SELECT * FROM students WHERE id = :id AND is_active = 1");
    $stu_stmt->execute([':id' => $selected_student_id]);
    $student = $stu_stmt->fetch();

    if ($student) {
        // Fetch all results for this student with exam and subject info
        $res_stmt = $pdo->prepare(
            "SELECT
                r.marks_obtained,
                r.grade,
                r.entered_at,
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
             ORDER BY e.exam_date DESC, s.subject_name ASC"
        );
        $res_stmt->execute([':student_id' => $selected_student_id]);
        $results = $res_stmt->fetchAll();
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<h1 class="page-title">Student Result Report</h1>

<div class="no-print" style="margin-bottom:16px;">
    <a href="<?php echo BASE_URL; ?>/admin/exams/list_exams.php" class="btn btn-secondary">← Back to Examinations</a>
</div>

<!-- Student selector -->
<div class="card no-print">
    <form method="GET" action="">
        <div class="form-group" style="margin:0;">
            <label for="student_id">Select Student</label>
            <select id="student_id" name="student_id" onchange="this.form.submit()">
                <option value="0">— Select Student —</option>
                <?php foreach ($all_students as $stu): ?>
                    <option value="<?php echo (int) $stu['id']; ?>"
                        <?php echo ($selected_student_id === (int) $stu['id']) ? 'selected' : ''; ?>>
                        <?php echo h($stu['student_code'] . ' — ' . $stu['full_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<?php if ($selected_student_id > 0 && $student): ?>

<!-- Print header -->
<div style="display:none;" class="print-header">
    <h2 style="text-align:center;">Oxford Academy — Student Exam Result Report</h2>
    <p style="text-align:center;">
        <?php echo h($student['full_name']); ?>
        &nbsp;|&nbsp; <?php echo h($student['student_code']); ?>
        &nbsp;|&nbsp; Generated: <?php echo date('d M Y'); ?>
    </p>
    <hr>
</div>
<style> @media print { .print-header { display:block !important; } } </style>

<!-- Student profile summary -->
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
        <div class="card-title" style="margin:0; border:none; padding:0;">
            <?php echo h($student['full_name']); ?>
            <span style="font-size:13px; font-weight:normal; color:#7f8c8d; margin-left:8px;">
                (<?php echo h($student['student_code']); ?>)
            </span>
        </div>
        <button id="print-btn" class="btn btn-print btn-sm no-print">Print</button>
    </div>

    <?php if (empty($results)): ?>
        <p style="color:#7f8c8d;">No exam results have been entered for this student yet.</p>
    <?php else: ?>

        <!-- Summary stats -->
        <?php
            $marks_pcts = array_map(
                fn($r) => round(($r['marks_obtained'] / $r['total_marks']) * 100, 1),
                $results
            );
            $overall_avg = round(array_sum($marks_pcts) / count($marks_pcts), 1);
            $best_pct    = max($marks_pcts);
            $worst_pct   = min($marks_pcts);
        ?>
        <div class="summary-grid no-print" style="margin-bottom:16px;">
            <div class="summary-box blue">
                <div class="summary-number"><?php echo count($results); ?></div>
                <div class="summary-label">Exams Taken</div>
            </div>
            <div class="summary-box green">
                <div class="summary-number"><?php echo $overall_avg; ?>%</div>
                <div class="summary-label">Overall Average</div>
            </div>
            <div class="summary-box purple">
                <div class="summary-number"><?php echo $best_pct; ?>%</div>
                <div class="summary-label">Best Score</div>
            </div>
            <div class="summary-box orange">
                <div class="summary-number"><?php echo $worst_pct; ?>%</div>
                <div class="summary-label">Lowest Score</div>
            </div>
        </div>

        <!-- Results table -->
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Exam Name</th>
                        <th>Class</th>
                        <th>Subject</th>
                        <th>Date</th>
                        <th style="text-align:center;">Marks</th>
                        <th style="text-align:center;">%</th>
                        <th style="text-align:center;">Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $i => $r):
                        $pct = round(($r['marks_obtained'] / $r['total_marks']) * 100, 1);
                    ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><?php echo h($r['exam_name']); ?></td>
                        <td><?php echo h($r['grade_level'] . ' — ' . $r['class_name']); ?></td>
                        <td><?php echo h($r['subject_name']); ?></td>
                        <td><?php echo date('d M Y', strtotime($r['exam_date'])); ?></td>
                        <td style="text-align:center;">
                            <?php echo $r['marks_obtained']; ?> / <?php echo (int) $r['total_marks']; ?>
                        </td>
                        <td style="text-align:center;">
                            <!-- Colour-code: green ≥75%, orange ≥50%, red <50% -->
                            <span style="color:<?php echo $pct >= 75 ? '#27ae60' : ($pct >= 50 ? '#e67e22' : '#e74c3c'); ?>; font-weight:bold;">
                                <?php echo $pct; ?>%
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <strong style="font-size:16px;"><?php echo h($r['grade']); ?></strong>
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
