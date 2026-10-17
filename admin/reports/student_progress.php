<?php
// ============================================================
// admin/reports/student_progress.php — Consolidated student report
//
// This page shows a full picture of one student:
//   1. Personal profile
//   2. Attendance summary (current month + all-time percentage)
//   3. All exam results with grades
//   4. Fee payment history and current status
//
// All data is read-only and printable.
// This is the "Student Progress Report" described in the proposal.
// ============================================================

require_once __DIR__ . '/../../config/db.php';

$required_role = 'admin';
require_once __DIR__ . '/../../includes/auth_check.php';

$pdo        = get_db_connection();
$page_title = 'Reports';

$selected_student_id = (int) ($_GET['student_id'] ?? 0);

// All active students for the dropdown
$all_students = $pdo->query(
    "SELECT id, student_code, full_name FROM students WHERE is_active = 1 ORDER BY full_name"
)->fetchAll();

$student          = null;
$enrolled_classes = [];
$att_summary      = [];
$exam_results     = [];
$fee_payments     = [];

if ($selected_student_id > 0) {

    // ---- Student profile ----
    $stu_stmt = $pdo->prepare("SELECT * FROM students WHERE id = :id");
    $stu_stmt->execute([':id' => $selected_student_id]);
    $student = $stu_stmt->fetch();

    if ($student) {

        // ---- Enrolled classes ----
        $cls_stmt = $pdo->prepare(
            "SELECT c.class_name, c.grade_level
             FROM student_classes sc
             JOIN classes c ON c.id = sc.class_id
             WHERE sc.student_id = :id"
        );
        $cls_stmt->execute([':id' => $selected_student_id]);
        $enrolled_classes = $cls_stmt->fetchAll();

        // ---- Attendance summary (by subject) ----
        // Count total sessions and present sessions per subject for this student
        $att_stmt = $pdo->prepare(
            "SELECT
                s.subject_name,
                c.class_name,
                c.grade_level,
                COUNT(a.id)                                                  AS total_sessions,
                SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END)       AS present_sessions
             FROM attendance a
             JOIN subjects s ON s.id = a.subject_id
             JOIN classes  c ON c.id = a.class_id
             WHERE a.student_id = :id
             GROUP BY a.subject_id, a.class_id
             ORDER BY c.grade_level, s.subject_name"
        );
        $att_stmt->execute([':id' => $selected_student_id]);
        $att_summary = $att_stmt->fetchAll();

        // ---- Exam results ----
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
             WHERE r.student_id = :id
             ORDER BY e.exam_date DESC"
        );
        $res_stmt->execute([':id' => $selected_student_id]);
        $exam_results = $res_stmt->fetchAll();

        // ---- Fee payment history ----
        $fee_stmt = $pdo->prepare(
            "SELECT
                fp.receipt_number,
                fp.payment_month,
                fp.amount_paid,
                fp.payment_date,
                fp.notes,
                c.class_name,
                c.grade_level
             FROM fee_payments fp
             JOIN classes c ON c.id = fp.class_id
             WHERE fp.student_id = :id
             ORDER BY fp.payment_date DESC"
        );
        $fee_stmt->execute([':id' => $selected_student_id]);
        $fee_payments = $fee_stmt->fetchAll();
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<h1 class="page-title">Reports</h1>

<!-- Reports quick links for other report types -->
<div class="card no-print">
    <div class="card-title">All Reports</div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a href="<?php echo BASE_URL; ?>/admin/reports/student_progress.php"     class="btn btn-secondary">Student Progress</a>
        <a href="<?php echo BASE_URL; ?>/admin/attendance/view_by_class.php"     class="btn btn-secondary">Attendance by Class</a>
        <a href="<?php echo BASE_URL; ?>/admin/attendance/view_by_student.php"   class="btn btn-secondary">Attendance by Student</a>
        <a href="<?php echo BASE_URL; ?>/admin/attendance/monthly_report.php"    class="btn btn-secondary">Monthly Attendance</a>
        <a href="<?php echo BASE_URL; ?>/admin/exams/class_result_sheet.php"     class="btn btn-secondary">Class Result Sheet</a>
        <a href="<?php echo BASE_URL; ?>/admin/exams/student_result_report.php"  class="btn btn-secondary">Student Results</a>
        <a href="<?php echo BASE_URL; ?>/admin/fees/monthly_summary.php"         class="btn btn-secondary">Monthly Fee Summary</a>
        <a href="<?php echo BASE_URL; ?>/admin/fees/overdue_list.php"            class="btn btn-secondary">Overdue Fees</a>
    </div>
</div>

<h2 style="font-size:18px; margin:20px 0 10px; color:#2c3e50;">Student Progress Report</h2>

<!-- Student selector -->
<div class="card no-print">
    <form method="GET" action="">
        <div class="form-group">
            <label for="student_id">Select Student</label>
            <select id="student_id" name="student_id" onchange="this.form.submit()">
                <option value="0">— Select a student —</option>
                <?php foreach ($all_students as $stu): ?>
                    <option value="<?php echo $stu['id']; ?>"
                        <?php echo ($selected_student_id === (int)$stu['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($stu['student_code'] . ' — ' . $stu['full_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<?php if ($selected_student_id > 0 && $student): ?>

<!-- Print header (hidden on screen, shown when printing) -->
<div style="display:none;" class="print-header">
    <h2 style="text-align:center; margin:0;">Oxford Academy</h2>
    <h3 style="text-align:center; margin:4px 0;">Student Progress Report</h3>
    <p style="text-align:center; color:#555; margin:2px 0;">Generated: <?php echo date('d M Y, h:i A'); ?></p>
    <hr>
</div>
<style> @media print { .print-header { display:block !important; } } </style>

<!-- 1. Student Profile -->
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
        <div class="card-title" style="margin:0; border:none; padding:0;">Student Profile</div>
        <button id="print-btn" class="btn btn-print btn-sm no-print">Print Full Report</button>
    </div>
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:10px;">
        <div><span style="color:#7f8c8d; font-size:12px;">Full Name</span><br><strong><?php echo htmlspecialchars($student['full_name']); ?></strong></div>
        <div><span style="color:#7f8c8d; font-size:12px;">Student ID</span><br><strong style="font-family:monospace;"><?php echo htmlspecialchars($student['student_code']); ?></strong></div>
        <div><span style="color:#7f8c8d; font-size:12px;">Date of Birth</span><br><?php echo $student['date_of_birth'] ? date('d M Y', strtotime($student['date_of_birth'])) : '—'; ?></div>
        <div><span style="color:#7f8c8d; font-size:12px;">Guardian Phone</span><br><?php echo htmlspecialchars($student['guardian_phone'] ?: '—'); ?></div>
        <div><span style="color:#7f8c8d; font-size:12px;">Enrolled Date</span><br><?php echo date('d M Y', strtotime($student['enrolled_date'])); ?></div>
        <div><span style="color:#7f8c8d; font-size:12px;">Status</span><br>
            <?php if ($student['is_active']): ?>
                <span class="badge" style="background:#d4edda; color:#155724;">Active</span>
            <?php else: ?>
                <span class="badge" style="background:#f8d7da; color:#721c24;">Inactive</span>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!empty($enrolled_classes)): ?>
    <div style="margin-top:12px;">
        <span style="color:#7f8c8d; font-size:12px;">Enrolled Classes</span><br>
        <?php foreach ($enrolled_classes as $cls): ?>
            <span class="badge" style="background:#e8f4fd; color:#1a5276; margin:2px;">
                <?php echo htmlspecialchars($cls['grade_level'] . ' — ' . $cls['class_name']); ?>
            </span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- 2. Attendance Summary -->
<div class="card">
    <div class="card-title">Attendance Summary</div>
    <?php if (empty($att_summary)): ?>
        <p style="color:#7f8c8d;">No attendance records found.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Class</th>
                        <th style="text-align:center;">Total Sessions</th>
                        <th style="text-align:center;">Present</th>
                        <th style="text-align:center;">Absent</th>
                        <th style="text-align:center;">Attendance %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $grand_total   = 0;
                    $grand_present = 0;
                    foreach ($att_summary as $row):
                        $pct = $row['total_sessions'] > 0
                            ? round(($row['present_sessions'] / $row['total_sessions']) * 100)
                            : 0;
                        $grand_total   += $row['total_sessions'];
                        $grand_present += $row['present_sessions'];
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['grade_level'] . ' — ' . $row['class_name']); ?></td>
                        <td style="text-align:center;"><?php echo $row['total_sessions']; ?></td>
                        <td style="text-align:center; color:#27ae60;"><?php echo $row['present_sessions']; ?></td>
                        <td style="text-align:center; color:#e74c3c;"><?php echo $row['total_sessions'] - $row['present_sessions']; ?></td>
                        <td style="text-align:center;">
                            <span style="color:<?php echo $pct >= 75 ? '#27ae60' : ($pct >= 50 ? '#e67e22' : '#e74c3c'); ?>; font-weight:bold;">
                                <?php echo $pct; ?>%
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#f0f2f5; font-weight:bold;">
                        <td colspan="2" style="padding:10px 14px; text-align:right;">Overall</td>
                        <td style="text-align:center;"><?php echo $grand_total; ?></td>
                        <td style="text-align:center; color:#27ae60;"><?php echo $grand_present; ?></td>
                        <td style="text-align:center; color:#e74c3c;"><?php echo $grand_total - $grand_present; ?></td>
                        <td style="text-align:center;">
                            <?php $overall_att = $grand_total > 0 ? round($grand_present / $grand_total * 100) : 0; ?>
                            <span style="color:<?php echo $overall_att >= 75 ? '#27ae60' : ($overall_att >= 50 ? '#e67e22' : '#e74c3c'); ?>;">
                                <?php echo $overall_att; ?>%
                            </span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- 3. Examination Results -->
<div class="card">
    <div class="card-title">Examination Results</div>
    <?php if (empty($exam_results)): ?>
        <p style="color:#7f8c8d;">No exam results found.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Exam Name</th>
                        <th>Subject</th>
                        <th>Date</th>
                        <th style="text-align:center;">Marks</th>
                        <th style="text-align:center;">%</th>
                        <th style="text-align:center;">Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($exam_results as $i => $r):
                        $pct = round(($r['marks_obtained'] / $r['total_marks']) * 100, 1);
                    ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><?php echo htmlspecialchars($r['exam_name']); ?></td>
                        <td><?php echo htmlspecialchars($r['subject_name']); ?></td>
                        <td><?php echo date('d M Y', strtotime($r['exam_date'])); ?></td>
                        <td style="text-align:center;"><?php echo $r['marks_obtained']; ?> / <?php echo $r['total_marks']; ?></td>
                        <td style="text-align:center;">
                            <span style="color:<?php echo $pct >= 75 ? '#27ae60' : ($pct >= 50 ? '#e67e22' : '#e74c3c'); ?>; font-weight:bold;">
                                <?php echo $pct; ?>%
                            </span>
                        </td>
                        <td style="text-align:center;"><strong style="font-size:15px;"><?php echo htmlspecialchars($r['grade']); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- 4. Fee Payment History -->
<div class="card">
    <div class="card-title">Fee Payment History</div>
    <?php if (empty($fee_payments)): ?>
        <p style="color:#7f8c8d;">No fee payments recorded.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Receipt No.</th>
                        <th>Payment Month</th>
                        <th>Class</th>
                        <th style="text-align:center;">Amount (Rs.)</th>
                        <th>Payment Date</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total_paid = 0;
                    foreach ($fee_payments as $fp):
                        $total_paid += $fp['amount_paid'];
                    ?>
                    <tr>
                        <td><span style="font-family:monospace; font-size:12px;"><?php echo htmlspecialchars($fp['receipt_number']); ?></span></td>
                        <td><?php echo date('M Y', strtotime($fp['payment_month'] . '-01')); ?></td>
                        <td><?php echo htmlspecialchars($fp['grade_level'] . ' — ' . $fp['class_name']); ?></td>
                        <td style="text-align:center;">Rs. <?php echo number_format($fp['amount_paid'], 2); ?></td>
                        <td><?php echo date('d M Y', strtotime($fp['payment_date'])); ?></td>
                        <td><?php echo htmlspecialchars($fp['notes'] ?: '—'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#f0f2f5; font-weight:bold;">
                        <td colspan="3" style="padding:10px 14px; text-align:right;">Total Paid</td>
                        <td style="text-align:center;">Rs. <?php echo number_format($total_paid, 2); ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
