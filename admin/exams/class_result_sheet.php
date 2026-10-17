<?php
// ============================================================
// admin/exams/class_result_sheet.php — Class-wise result sheet
//
// Shows every student's result for one examination.
// Also calculates and displays class statistics:
//   highest mark, lowest mark, class average, and pass count.
// Printable.
// ============================================================

require_once __DIR__ . '/../../config/db.php';

$required_role = 'admin';
require_once __DIR__ . '/../../includes/auth_check.php';

$pdo        = get_db_connection();
$page_title = 'Class Result Sheet';

$selected_exam_id = (int) ($_GET['exam_id'] ?? 0);

// All exams for the dropdown
$all_exams = $pdo->query(
    "SELECT e.id, e.exam_name, e.exam_date, c.grade_level, c.class_name, s.subject_name
     FROM examinations e
     JOIN classes  c ON c.id = e.class_id
     JOIN subjects s ON s.id = e.subject_id
     ORDER BY e.exam_date DESC"
)->fetchAll();

$exam_info    = null;
$results_data = [];
$stats        = [];

if ($selected_exam_id > 0) {
    // Load the exam details
    $ex_stmt = $pdo->prepare(
        "SELECT e.*, c.class_name, c.grade_level, s.subject_name
         FROM examinations e
         JOIN classes  c ON c.id = e.class_id
         JOIN subjects s ON s.id = e.subject_id
         WHERE e.id = :id"
    );
    $ex_stmt->execute([':id' => $selected_exam_id]);
    $exam_info = $ex_stmt->fetch();

    if ($exam_info) {
        // Fetch all enrolled students and LEFT JOIN their results.
        // Students with no result appear as NULL marks.
        $res_stmt = $pdo->prepare(
            "SELECT
                s.id          AS student_id,
                s.student_code,
                s.full_name,
                r.marks_obtained,
                r.grade,
                r.entered_at
             FROM students s
             JOIN student_classes sc ON sc.student_id = s.id
                                    AND sc.class_id    = :class_id
             LEFT JOIN results r ON r.student_id    = s.id
                                AND r.examination_id = :exam_id
             WHERE s.is_active = 1
             ORDER BY r.marks_obtained DESC, s.full_name ASC"
        );
        $res_stmt->execute([
            ':class_id' => $exam_info['class_id'],
            ':exam_id'  => $selected_exam_id,
        ]);
        $results_data = $res_stmt->fetchAll();

        // Calculate class statistics from students who have a result
        $marks_list = array_filter(
            array_column($results_data, 'marks_obtained'),
            fn($m) => $m !== null
        );

        if (!empty($marks_list)) {
            $total_marks   = $exam_info['total_marks'];
            $pass_threshold = 40; // percentage to pass

            $stats = [
                'highest'    => max($marks_list),
                'lowest'     => min($marks_list),
                'average'    => round(array_sum($marks_list) / count($marks_list), 1),
                'pass_count' => count(array_filter($marks_list, fn($m) => ($m / $total_marks * 100) >= $pass_threshold)),
                'entered'    => count($marks_list),
                'total'      => count($results_data),
            ];
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<h1 class="page-title">Class Result Sheet</h1>

<!-- Exam selector -->
<div class="card no-print">
    <form method="GET" action="">
        <div class="form-group">
            <label for="exam_id">Select Examination</label>
            <select id="exam_id" name="exam_id" onchange="this.form.submit()">
                <option value="0">— Select Exam —</option>
                <?php foreach ($all_exams as $ex): ?>
                    <option value="<?php echo $ex['id']; ?>"
                        <?php echo ($selected_exam_id === (int)$ex['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars(
                            $ex['exam_name'] . ' | '
                            . $ex['grade_level'] . ' ' . $ex['class_name'] . ' | '
                            . $ex['subject_name'] . ' | '
                            . date('d M Y', strtotime($ex['exam_date']))
                        ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<?php if ($selected_exam_id > 0 && $exam_info): ?>

<!-- Print header -->
<div style="display:none;" class="print-header">
    <h2 style="text-align:center;">Oxford Academy — Examination Result Sheet</h2>
    <p style="text-align:center;">
        <?php echo htmlspecialchars($exam_info['exam_name']); ?>
        &nbsp;|&nbsp; <?php echo htmlspecialchars($exam_info['grade_level'] . ' — ' . $exam_info['class_name']); ?>
        &nbsp;|&nbsp; <?php echo htmlspecialchars($exam_info['subject_name']); ?>
        &nbsp;|&nbsp; <?php echo date('d M Y', strtotime($exam_info['exam_date'])); ?>
    </p>
</div>
<style> @media print { .print-header { display:block !important; } } </style>

<!-- Statistics -->
<?php if (!empty($stats)): ?>
<div class="summary-grid no-print">
    <div class="summary-box blue">
        <div class="summary-number"><?php echo $stats['highest']; ?></div>
        <div class="summary-label">Highest Mark</div>
    </div>
    <div class="summary-box orange">
        <div class="summary-number"><?php echo $stats['lowest']; ?></div>
        <div class="summary-label">Lowest Mark</div>
    </div>
    <div class="summary-box purple">
        <div class="summary-number"><?php echo $stats['average']; ?></div>
        <div class="summary-label">Class Average</div>
    </div>
    <div class="summary-box green">
        <div class="summary-number"><?php echo $stats['pass_count']; ?>/<?php echo $stats['entered']; ?></div>
        <div class="summary-label">Passed (≥40%)</div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
        <div class="card-title" style="margin:0; border:none; padding:0;">
            <?php echo htmlspecialchars($exam_info['exam_name']); ?>
            — <?php echo htmlspecialchars($exam_info['subject_name']); ?>
            — <?php echo date('d M Y', strtotime($exam_info['exam_date'])); ?>
            (Out of <?php echo $exam_info['total_marks']; ?>)
        </div>
        <button id="print-btn" class="btn btn-print btn-sm no-print">Print</button>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student ID</th>
                    <th>Full Name</th>
                    <th style="text-align:center;">Marks</th>
                    <th style="text-align:center;">Percentage</th>
                    <th style="text-align:center;">Grade</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results_data as $i => $row):
                    $pct = ($row['marks_obtained'] !== null)
                        ? round(($row['marks_obtained'] / $exam_info['total_marks']) * 100, 1)
                        : null;
                ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td><span style="font-family:monospace;"><?php echo htmlspecialchars($row['student_code']); ?></span></td>
                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                    <td style="text-align:center;">
                        <?php echo $row['marks_obtained'] !== null
                            ? $row['marks_obtained'] . ' / ' . $exam_info['total_marks']
                            : '<span style="color:#7f8c8d;">Not entered</span>'; ?>
                    </td>
                    <td style="text-align:center;">
                        <?php echo $pct !== null ? $pct . '%' : '—'; ?>
                    </td>
                    <td style="text-align:center;">
                        <?php if ($row['grade']): ?>
                            <strong style="font-size:16px;"><?php echo htmlspecialchars($row['grade']); ?></strong>
                        <?php else: ?>
                            <span style="color:#7f8c8d;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if (!empty($stats)): ?>
            <tfoot>
                <tr style="background:#f0f2f5;">
                    <td colspan="3" style="text-align:right; font-weight:bold; padding:10px 14px;">Class Average</td>
                    <td colspan="3" style="text-align:center; font-weight:bold; padding:10px 14px;">
                        <?php echo $stats['average']; ?> / <?php echo $exam_info['total_marks']; ?>
                        &nbsp;(<?php echo round($stats['average'] / $exam_info['total_marks'] * 100, 1); ?>%)
                    </td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
