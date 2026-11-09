<?php
// ============================================================
// student/progress_report.php — Student's consolidated progress report
//
// Read-only view for the student and their parent.
// Combines:
//   1. Profile, enrolled class, and chosen subjects
//   2. Attendance summary per subject & cumulative attendance rate
//   3. All examination results, percentage, and letter grade
//   4. Fee payment history & current month fee status
//
// Record isolation: student_id is taken strictly from the session
// ($_SESSION['linked_id']). A student cannot view another student's report.
// Printable.
// ============================================================

require_once __DIR__ . '/../config/db.php';

$required_role = 'student';
require_once __DIR__ . '/../includes/auth_check.php';

$pdo        = get_db_connection();
$page_title = 'My Progress Report';
$student_id = (int) $_SESSION['linked_id'];

// ---- 1. Student profile ----
$stu_stmt = $pdo->prepare("SELECT * FROM students WHERE id = :id");
$stu_stmt->execute([':id' => $student_id]);
$student = $stu_stmt->fetch();

if (!$student) {
    http_response_code(404);
    echo "Student record not found.";
    exit;
}

// Enrolled classes
$cls_stmt = $pdo->prepare(
    "SELECT c.id, c.class_name, c.grade_level
     FROM student_classes sc
     JOIN classes c ON c.id = sc.class_id
     WHERE sc.student_id = :id
       AND (sc.ends_on IS NULL OR sc.ends_on >= CURDATE())"
);
$cls_stmt->execute([':id' => $student_id]);
$enrolled_classes = $cls_stmt->fetchAll();

// Chosen subjects
$subj_stmt = $pdo->prepare(
    "SELECT s.subject_name, c.class_name, c.grade_level
     FROM student_subjects ss
     JOIN subjects s ON s.id = ss.subject_id
     JOIN classes c  ON c.id = ss.class_id
     WHERE ss.student_id = :id
       AND (ss.ends_on IS NULL OR ss.ends_on >= CURDATE())
     ORDER BY c.grade_level, s.subject_name"
);
$subj_stmt->execute([':id' => $student_id]);
$enrolled_subjects = $subj_stmt->fetchAll();

// ---- 2. Attendance summary ----
$att_stmt = $pdo->prepare(
    "SELECT
        s.subject_name,
        c.class_name,
        c.grade_level,
        COUNT(a.id)                                            AS total_sessions,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_sessions
     FROM attendance a
     JOIN subjects s ON s.id = a.subject_id
     JOIN classes  c ON c.id = a.class_id
     WHERE a.student_id = :id
     GROUP BY a.subject_id, a.class_id
     ORDER BY c.grade_level, s.subject_name"
);
$att_stmt->execute([':id' => $student_id]);
$att_summary = $att_stmt->fetchAll();

// ---- 3. Exam results ----
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
     ORDER BY e.exam_date DESC, s.subject_name ASC"
);
$res_stmt->execute([':id' => $student_id]);
$exam_results = $res_stmt->fetchAll();

// ---- 4. Fee payments & current status ----
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
$fee_stmt->execute([':id' => $student_id]);
$fee_payments = $fee_stmt->fetchAll();

$current_month = date('Y-m');
$fee_status_stmt = $pdo->prepare(
    "SELECT
        c.id          AS class_id,
        c.class_name,
        c.grade_level,
        fs.monthly_amount,
        fp.receipt_number,
        fp.amount_paid,
        fp.payment_date
     FROM student_classes sc
     JOIN classes c ON c.id = sc.class_id AND c.is_active = 1
     JOIN fee_structures fs
         ON fs.class_id = c.id
         AND fs.effective_from = (
             SELECT MAX(fs2.effective_from)
             FROM fee_structures fs2
             WHERE fs2.class_id = c.id
         )
     LEFT JOIN fee_payments fp
         ON fp.student_id    = sc.student_id
         AND fp.class_id     = c.id
         AND fp.payment_month = :month
     WHERE sc.student_id = :id
       AND (sc.ends_on IS NULL OR sc.ends_on >= CURDATE())"
);
$fee_status_stmt->execute([
    ':id'    => $student_id,
    ':month' => $current_month,
]);
$current_fee_status = $fee_status_stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:18px;">
    <h1 class="page-title" style="margin:0;">My Consolidated Progress Report</h1>
    <button id="print-btn" class="btn btn-print no-print">Print Progress Report</button>
</div>

<!-- Print header -->
<div style="display:none;" class="print-header">
    <div style="text-align:center; border-bottom:2px solid #1a3a5c; padding-bottom:12px; margin-bottom:18px;">
        <h2 style="margin:0; color:#1a3a5c; font-size:22px;">OXFORD ACADEMY</h2>
        <p style="margin:2px 0; color:#555; font-size:13px;">Nedunkerny, Vavuniya</p>
        <h3 style="margin:6px 0 2px; color:#2c3e50; font-size:16px;">CONSOLIDATED STUDENT PROGRESS REPORT</h3>
        <p style="margin:0; font-size:12px; color:#777;">Generated on: <?php echo date('d M Y, h:i A'); ?></p>
    </div>
</div>
<style>
@media print {
    .print-header { display:block !important; }
    .site-header, .site-footer, .no-print { display:none !important; }
    .card { border:1px solid #ddd !important; box-shadow:none !important; page-break-inside:avoid; margin-bottom:16px !important; }
}
</style>

<!-- 1. Student Profile -->
<div class="card">
    <div class="card-title">1. Student Profile</div>
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:12px;">
        <div>
            <span style="color:#7f8c8d; font-size:12px;">Full Name</span><br>
            <strong><?php echo h($student['full_name']); ?></strong>
        </div>
        <div>
            <span style="color:#7f8c8d; font-size:12px;">Student ID</span><br>
            <strong style="font-family:monospace;"><?php echo h($student['student_code']); ?></strong>
        </div>
        <div>
            <span style="color:#7f8c8d; font-size:12px;">Date of Birth</span><br>
            <?php echo $student['date_of_birth'] ? date('d M Y', strtotime($student['date_of_birth'])) : '—'; ?>
        </div>
        <div>
            <span style="color:#7f8c8d; font-size:12px;">Guardian</span><br>
            <?php echo h($student['guardian_name'] ?: '—'); ?>
        </div>
        <div>
            <span style="color:#7f8c8d; font-size:12px;">Enrolled Date</span><br>
            <?php echo date('d M Y', strtotime($student['enrolled_date'])); ?>
        </div>
        <div>
            <span style="color:#7f8c8d; font-size:12px;">Enrolled Class</span><br>
            <?php foreach ($enrolled_classes as $cls): ?>
                <span class="badge" style="background:#e8f4fd; color:#1a5276;">
                    <?php echo h($cls['grade_level'] . ' — ' . $cls['class_name']); ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!empty($enrolled_subjects)): ?>
    <div style="margin-top:12px; padding-top:10px; border-top:1px solid #f0f2f5;">
        <span style="color:#7f8c8d; font-size:12px;">Selected Subjects:</span>
        <?php foreach ($enrolled_subjects as $subj): ?>
            <span class="badge" style="background:#f0f9f4; color:#155724; margin-left:4px;">
                <?php echo h($subj['subject_name']); ?>
            </span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- 2. Attendance Summary -->
<div class="card">
    <div class="card-title">2. Attendance Summary</div>
    <?php if (empty($att_summary)): ?>
        <p style="color:#7f8c8d;">No attendance records recorded yet.</p>
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
                        <th style="text-align:center;">Attendance Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $grand_total   = 0;
                    $grand_present = 0;
                    foreach ($att_summary as $row):
                        $tot  = (int) $row['total_sessions'];
                        $pres = (int) $row['present_sessions'];
                        $pct  = $tot > 0 ? round(($pres / $tot) * 100, 1) : null;
                        $grand_total   += $tot;
                        $grand_present += $pres;
                    ?>
                    <tr>
                        <td><strong><?php echo h($row['subject_name']); ?></strong></td>
                        <td><?php echo h($row['grade_level'] . ' — ' . $row['class_name']); ?></td>
                        <td style="text-align:center;"><?php echo $tot; ?></td>
                        <td style="text-align:center; color:#27ae60; font-weight:bold;"><?php echo $pres; ?></td>
                        <td style="text-align:center; color:#c0392b; font-weight:bold;"><?php echo $tot - $pres; ?></td>
                        <td style="text-align:center;">
                            <?php if ($pct !== null): ?>
                                <span style="color:<?php echo $pct >= 75 ? '#27ae60' : ($pct >= 50 ? '#e67e22' : '#c0392b'); ?>; font-weight:bold;">
                                    <?php echo $pct; ?>%
                                </span>
                            <?php else: ?>
                                <span style="color:#7f8c8d;">No attendance recorded</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#f0f2f5; font-weight:bold;">
                        <td colspan="2" style="padding:10px 14px; text-align:right;">Overall Cumulative Attendance</td>
                        <td style="text-align:center;"><?php echo $grand_total; ?></td>
                        <td style="text-align:center; color:#27ae60;"><?php echo $grand_present; ?></td>
                        <td style="text-align:center; color:#c0392b;"><?php echo $grand_total - $grand_present; ?></td>
                        <td style="text-align:center;">
                            <?php if ($grand_total > 0):
                                $overall_att = round(($grand_present / $grand_total) * 100, 1);
                            ?>
                                <span style="color:<?php echo $overall_att >= 75 ? '#27ae60' : ($overall_att >= 50 ? '#e67e22' : '#c0392b'); ?>; font-size:14px;">
                                    <?php echo $overall_att; ?>%
                                </span>
                            <?php else: ?>
                                <span style="color:#7f8c8d;">No attendance recorded</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- 3. Examination Results -->
<div class="card">
    <div class="card-title">3. Examination Performance</div>
    <?php if (empty($exam_results)): ?>
        <p style="color:#7f8c8d;">No examination results entered yet.</p>
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
                        <th style="text-align:center;">Percentage</th>
                        <th style="text-align:center;">Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $pct_sum = 0;
                    foreach ($exam_results as $i => $r):
                        $pct = round(($r['marks_obtained'] / $r['total_marks']) * 100, 1);
                        $pct_sum += $pct;
                    ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><?php echo h($r['exam_name']); ?></td>
                        <td><strong><?php echo h($r['subject_name']); ?></strong></td>
                        <td><?php echo date('d M Y', strtotime($r['exam_date'])); ?></td>
                        <td style="text-align:center;">
                            <?php echo $r['marks_obtained']; ?> / <?php echo (int) $r['total_marks']; ?>
                        </td>
                        <td style="text-align:center;">
                            <span style="color:<?php echo $pct >= 75 ? '#27ae60' : ($pct >= 50 ? '#e67e22' : '#c0392b'); ?>; font-weight:bold;">
                                <?php echo $pct; ?>%
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <strong style="font-size:15px;"><?php echo h($r['grade']); ?></strong>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#f0f2f5; font-weight:bold;">
                        <td colspan="4" style="padding:10px 14px; text-align:right;">Academic Average</td>
                        <td colspan="3" style="text-align:center; padding:10px 14px;">
                            <?php $avg_pct = count($exam_results) > 0 ? round($pct_sum / count($exam_results), 1) : 0; ?>
                            <span style="color:<?php echo $avg_pct >= 75 ? '#27ae60' : ($avg_pct >= 50 ? '#e67e22' : '#c0392b'); ?>; font-size:15px;">
                                <?php echo $avg_pct; ?>%
                            </span>
                            (<?php echo count($exam_results); ?> exams evaluated)
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- 4. Fee Payments & Current Status -->
<div class="card">
    <div class="card-title">4. Fee Payment Summary</div>

    <?php if (!empty($current_fee_status)): ?>
    <div style="margin-bottom:14px;">
        <span style="font-weight:bold; font-size:13px; color:#2c3e50;">Current Month Status (<?php echo date('F Y'); ?>):</span>
        <div style="display:flex; gap:10px; margin-top:6px; flex-wrap:wrap;">
            <?php foreach ($current_fee_status as $cfs): ?>
                <div style="background:#f8fafc; border:1px solid #dde2e8; border-radius:4px; padding:8px 12px; font-size:13px;">
                    <strong><?php echo h($cfs['grade_level'] . ' — ' . $cfs['class_name']); ?></strong>:
                    LKR <?php echo number_format($cfs['monthly_amount'], 2); ?> —
                    <?php if ($cfs['receipt_number']): ?>
                        <span class="badge badge-paid">Paid (Receipt #<?php echo h($cfs['receipt_number']); ?>)</span>
                    <?php else: ?>
                        <span class="badge badge-overdue">Outstanding</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($fee_payments)): ?>
        <p style="color:#7f8c8d;">No payment history recorded.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Receipt No.</th>
                        <th>Month Paid For</th>
                        <th>Class</th>
                        <th style="text-align:right;">Amount (LKR)</th>
                        <th>Payment Date</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total_paid = 0;
                    foreach ($fee_payments as $fp):
                        $total_paid += (float) $fp['amount_paid'];
                    ?>
                    <tr>
                        <td><span style="font-family:monospace; font-size:12px;"><?php echo h($fp['receipt_number']); ?></span></td>
                        <td><?php echo date('F Y', strtotime($fp['payment_month'] . '-01')); ?></td>
                        <td><?php echo h($fp['grade_level'] . ' — ' . $fp['class_name']); ?></td>
                        <td style="text-align:right; font-weight:bold; color:#27ae60;">
                            LKR <?php echo number_format($fp['amount_paid'], 2); ?>
                        </td>
                        <td><?php echo date('d M Y', strtotime($fp['payment_date'])); ?></td>
                        <td><?php echo h($fp['notes'] ?: '—'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#f0f2f5; font-weight:bold;">
                        <td colspan="3" style="padding:10px 14px; text-align:right;">Total Fees Paid</td>
                        <td style="text-align:right; padding:10px 14px; color:#27ae60;">
                            LKR <?php echo number_format($total_paid, 2); ?>
                        </td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
