<?php
// ============================================================
// admin/fees/overdue_list.php — Students with overdue payments
//
// Finds all active students who are enrolled in a class that
// has a fee structure but have NO payment record for the
// selected month.
//
// Query logic:
//   - Start with: active students joined to their classes
//   - Each class must have a fee structure (INNER JOIN)
//   - LEFT JOIN to fee_payments for the selected month
//   - WHERE payment record IS NULL → student has not paid
// ============================================================

require_once __DIR__ . '/../../config/db.php';

$required_role = 'admin';
require_once __DIR__ . '/../../includes/auth_check.php';

$pdo        = get_db_connection();
$page_title = 'Overdue Payments';

$selected_month = $_GET['month'] ?? date('Y-m');

// The overdue query
// A student is overdue if:
//   1. They are enrolled in a class that has a fee structure
//   2. They have no entry in fee_payments for the selected month in that class
$stmt = $pdo->prepare(
    "SELECT
        s.id            AS student_id,
        s.student_code,
        s.full_name,
        s.guardian_phone,
        c.id            AS class_id,
        c.class_name,
        c.grade_level,
        fs.monthly_amount
     FROM students s
     JOIN student_classes sc  ON sc.student_id = s.id
     JOIN classes c           ON c.id = sc.class_id AND c.is_active = 1

     -- Get the current fee for this class (latest effective_from)
     JOIN fee_structures fs
         ON fs.class_id = c.id
         AND fs.effective_from = (
             SELECT MAX(fs2.effective_from)
             FROM fee_structures fs2
             WHERE fs2.class_id = c.id
         )

     -- LEFT JOIN to payments: if no matching payment, fp.id will be NULL
     LEFT JOIN fee_payments fp
         ON fp.student_id    = s.id
         AND fp.class_id     = c.id
         AND fp.payment_month = :month

     WHERE s.is_active = 1
       AND fp.id IS NULL

     ORDER BY c.grade_level ASC, c.class_name ASC, s.full_name ASC"
);
$stmt->execute([':month' => $selected_month]);
$overdue = $stmt->fetchAll();

$total_overdue_amount = array_sum(array_column($overdue, 'monthly_amount'));

require_once __DIR__ . '/../../includes/header.php';
?>

<h1 class="page-title">Overdue Payments</h1>

<!-- Month filter -->
<div class="card no-print">
    <form method="GET" action="" style="display:flex; align-items:flex-end; gap:12px; flex-wrap:wrap;">
        <div class="form-group" style="margin:0;">
            <label for="month">Select Month</label>
            <input type="month" id="month" name="month"
                value="<?php echo htmlspecialchars($selected_month); ?>">
        </div>
        <button type="submit" class="btn btn-primary">Check Overdue</button>
    </form>
</div>

<!-- Print header -->
<div style="display:none;" class="print-header">
    <h2 style="text-align:center;">Oxford Academy — Overdue Payments</h2>
    <p style="text-align:center;"><?php echo date('F Y', strtotime($selected_month . '-01')); ?></p>
</div>
<style> @media print { .print-header { display:block !important; } } </style>

<!-- Summary cards -->
<div class="summary-grid no-print">
    <div class="summary-box red">
        <div class="summary-number"><?php echo count($overdue); ?></div>
        <div class="summary-label">Overdue Entries</div>
    </div>
    <div class="summary-box orange">
        <div class="summary-number">LKR <?php echo number_format($total_overdue_amount, 0); ?></div>
        <div class="summary-label">Total Outstanding</div>
    </div>
</div>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
        <div class="card-title" style="margin:0; border:none; padding:0;">
            Overdue for <?php echo date('F Y', strtotime($selected_month . '-01')); ?>
        </div>
        <button id="print-btn" class="btn btn-print btn-sm no-print">Print</button>
    </div>

    <?php if (empty($overdue)): ?>
        <div class="alert alert-success">
            All students have paid their fees for <?php echo date('F Y', strtotime($selected_month . '-01')); ?>.
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Guardian Phone</th>
                        <th>Class</th>
                        <th>Fee Due (LKR)</th>
                        <th class="no-print">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($overdue as $i => $row): ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><span style="font-family:monospace;"><?php echo htmlspecialchars($row['student_code']); ?></span></td>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['guardian_phone'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($row['grade_level'] . ' — ' . $row['class_name']); ?></td>
                        <td style="color:#c0392b; font-weight:bold;">
                            LKR <?php echo number_format($row['monthly_amount'], 2); ?>
                        </td>
                        <td class="no-print">
                            <!-- Quick link to record payment for this student -->
                            <a href="<?php echo BASE_URL; ?>/admin/fees/record_payment.php?student_id=<?php echo $row['student_id']; ?>"
                               class="btn btn-sm btn-success">
                                Record Payment
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#fdf3f2;">
                        <td colspan="5" style="text-align:right; font-weight:bold; padding:10px 14px;">
                            Total Outstanding:
                        </td>
                        <td style="font-weight:bold; color:#c0392b; padding:10px 14px;">
                            LKR <?php echo number_format($total_overdue_amount, 2); ?>
                        </td>
                        <td class="no-print"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
