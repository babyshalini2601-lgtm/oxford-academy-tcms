<?php
// ============================================================
// admin/fees/monthly_summary.php — Monthly fee collection report
//
// For the selected month, shows per class:
//   - Total students enrolled in that class
//   - Number who have paid
//   - Total amount collected
//   - Total amount still outstanding
//
// Printable summary for management review.
// ============================================================

require_once __DIR__ . '/../../config/db.php';

$required_role = 'admin';
require_once __DIR__ . '/../../includes/auth_check.php';

$pdo        = get_db_connection();
$page_title = 'Monthly Fee Collection Summary';

$selected_month = $_GET['month'] ?? date('Y-m');

// For each class that has a fee structure, calculate:
//   enrolled  = COUNT of active students in that class
//   paid      = COUNT of students who have a payment for the selected month
//   collected = SUM of amounts paid for the selected month
$summary = $pdo->prepare(
    "SELECT
        c.id               AS class_id,
        c.class_name,
        c.grade_level,
        fs.monthly_amount,
        COUNT(DISTINCT sc.student_id)      AS enrolled_students,
        COUNT(DISTINCT fp.student_id)      AS paid_students,
        COALESCE(SUM(fp.amount_paid), 0)   AS total_collected
     FROM classes c

     -- Only classes with a fee structure
     JOIN fee_structures fs
         ON fs.class_id = c.id
         AND fs.effective_from = (
             SELECT MAX(fs2.effective_from)
             FROM fee_structures fs2
             WHERE fs2.class_id = c.id
         )

     -- All active students enrolled in the class
     LEFT JOIN student_classes sc ON sc.class_id = c.id
     LEFT JOIN students s ON s.id = sc.student_id AND s.is_active = 1

     -- Payments for the selected month
     LEFT JOIN fee_payments fp
         ON fp.class_id     = c.id
         AND fp.payment_month = :month
         AND fp.student_id  = sc.student_id

     WHERE c.is_active = 1
     GROUP BY c.id, fs.monthly_amount
     ORDER BY c.grade_level ASC, c.class_name ASC"
);
$summary->execute([':month' => $selected_month]);
$rows = $summary->fetchAll();

// Calculate grand totals
$grand_enrolled    = 0;
$grand_paid        = 0;
$grand_expected    = 0;
$grand_collected   = 0;

foreach ($rows as $row) {
    $grand_enrolled  += $row['enrolled_students'];
    $grand_paid      += $row['paid_students'];
    $grand_expected  += $row['enrolled_students'] * $row['monthly_amount'];
    $grand_collected += $row['total_collected'];
}

$grand_outstanding = $grand_expected - $grand_collected;

// Also fetch individual payment records for the selected month
$payments_stmt = $pdo->prepare(
    "SELECT
        fp.receipt_number,
        fp.payment_date,
        fp.amount_paid,
        s.student_code,
        s.full_name AS student_name,
        c.class_name,
        c.grade_level
     FROM fee_payments fp
     JOIN students s ON s.id = fp.student_id
     JOIN classes  c ON c.id = fp.class_id
     WHERE fp.payment_month = :month
     ORDER BY fp.payment_date ASC, s.full_name ASC"
);
$payments_stmt->execute([':month' => $selected_month]);
$payment_records = $payments_stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<h1 class="page-title">Monthly Fee Collection Summary</h1>

<!-- Month filter -->
<div class="card no-print">
    <form method="GET" action="" style="display:flex; align-items:flex-end; gap:12px; flex-wrap:wrap;">
        <div class="form-group" style="margin:0;">
            <label for="month">Select Month</label>
            <input type="month" id="month" name="month"
                value="<?php echo htmlspecialchars($selected_month); ?>">
        </div>
        <button type="submit" class="btn btn-primary">Generate Report</button>
    </form>
</div>

<!-- Print header -->
<div style="display:none;" class="print-header">
    <h2 style="text-align:center;">Oxford Academy — Fee Collection Summary</h2>
    <p style="text-align:center;"><?php echo date('F Y', strtotime($selected_month . '-01')); ?></p>
</div>
<style> @media print { .print-header { display:block !important; } } </style>

<!-- Grand total summary boxes -->
<div class="summary-grid no-print">
    <div class="summary-box blue">
        <div class="summary-number"><?php echo $grand_enrolled; ?></div>
        <div class="summary-label">Total Students</div>
    </div>
    <div class="summary-box green">
        <div class="summary-number"><?php echo $grand_paid; ?></div>
        <div class="summary-label">Paid</div>
    </div>
    <div class="summary-box orange">
        <div class="summary-number">LKR <?php echo number_format($grand_collected, 0); ?></div>
        <div class="summary-label">Total Collected</div>
    </div>
    <div class="summary-box red">
        <div class="summary-number">LKR <?php echo number_format($grand_outstanding, 0); ?></div>
        <div class="summary-label">Outstanding</div>
    </div>
</div>

<!-- Per-class summary table -->
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
        <div class="card-title" style="margin:0; border:none; padding:0;">
            Summary by Class — <?php echo date('F Y', strtotime($selected_month . '-01')); ?>
        </div>
        <button id="print-btn" class="btn btn-print btn-sm no-print">Print</button>
    </div>

    <?php if (empty($rows)): ?>
        <p style="color:#7f8c8d;">No fee structures configured. <a href="<?php echo BASE_URL; ?>/admin/fees/set_fee_structure.php">Set fee structures first</a>.</p>
    <?php else: ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Class</th>
                    <th>Grade</th>
                    <th style="text-align:right;">Monthly Fee</th>
                    <th style="text-align:center;">Enrolled</th>
                    <th style="text-align:center;">Paid</th>
                    <th style="text-align:center;">Unpaid</th>
                    <th style="text-align:right;">Expected (LKR)</th>
                    <th style="text-align:right;">Collected (LKR)</th>
                    <th style="text-align:right;">Outstanding (LKR)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row):
                    $expected    = $row['enrolled_students'] * $row['monthly_amount'];
                    $outstanding = $expected - $row['total_collected'];
                    $unpaid      = $row['enrolled_students'] - $row['paid_students'];
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($row['class_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($row['grade_level']); ?></td>
                    <td style="text-align:right;"><?php echo number_format($row['monthly_amount'], 2); ?></td>
                    <td style="text-align:center;"><?php echo $row['enrolled_students']; ?></td>
                    <td style="text-align:center; color:#27ae60; font-weight:bold;"><?php echo $row['paid_students']; ?></td>
                    <td style="text-align:center; color:<?php echo $unpaid > 0 ? '#c0392b' : '#27ae60'; ?>; font-weight:bold;">
                        <?php echo $unpaid; ?>
                    </td>
                    <td style="text-align:right;"><?php echo number_format($expected, 2); ?></td>
                    <td style="text-align:right; color:#27ae60; font-weight:bold;"><?php echo number_format($row['total_collected'], 2); ?></td>
                    <td style="text-align:right; color:<?php echo $outstanding > 0 ? '#c0392b' : '#27ae60'; ?>; font-weight:bold;">
                        <?php echo number_format($outstanding, 2); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f0f2f5; font-weight:bold;">
                    <td colspan="3" style="text-align:right; padding:10px 14px;">Grand Total</td>
                    <td style="text-align:center; padding:10px 14px;"><?php echo $grand_enrolled; ?></td>
                    <td style="text-align:center; padding:10px 14px; color:#27ae60;"><?php echo $grand_paid; ?></td>
                    <td style="text-align:center; padding:10px 14px; color:#c0392b;"><?php echo $grand_enrolled - $grand_paid; ?></td>
                    <td style="text-align:right; padding:10px 14px;"><?php echo number_format($grand_expected, 2); ?></td>
                    <td style="text-align:right; padding:10px 14px; color:#27ae60;"><?php echo number_format($grand_collected, 2); ?></td>
                    <td style="text-align:right; padding:10px 14px; color:#c0392b;"><?php echo number_format($grand_outstanding, 2); ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Individual payment records for the month -->
<?php if (!empty($payment_records)): ?>
<div class="card" style="margin-top:4px;">
    <div class="card-title">All Payments Received — <?php echo date('F Y', strtotime($selected_month . '-01')); ?></div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Receipt No.</th>
                    <th>Date</th>
                    <th>Student</th>
                    <th>Class</th>
                    <th style="text-align:right;">Amount (LKR)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payment_records as $i => $p): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td><span style="font-family:monospace; font-size:12px;"><?php echo htmlspecialchars($p['receipt_number']); ?></span></td>
                    <td><?php echo date('d M Y', strtotime($p['payment_date'])); ?></td>
                    <td><?php echo htmlspecialchars($p['student_name']); ?></td>
                    <td><?php echo htmlspecialchars($p['grade_level'] . ' — ' . $p['class_name']); ?></td>
                    <td style="text-align:right;"><?php echo number_format($p['amount_paid'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
