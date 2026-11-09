<?php
// ============================================================
// student/fees.php — Student's own fee payment history
//
// Read-only view. The student sees all past payments with
// receipt numbers, amounts, and the payment month.
// Also shows the current month's status per enrolled class.
//
// Record isolation: student_id is taken from the session, not
// from the URL, so a student cannot read another student's fees.
// ============================================================

require_once __DIR__ . '/../config/db.php';

$required_role = 'student';
require_once __DIR__ . '/../includes/auth_check.php';

$pdo        = get_db_connection();
$page_title = 'My Fee Payments';

// student_id from session — never from the URL
$student_id    = (int) $_SESSION['linked_id'];
$current_month = date('Y-m');

// ------------------------------------------------------------
// Current month status per enrolled class
// Shows whether each class fee is paid or still outstanding.
// ------------------------------------------------------------
$status_stmt = $pdo->prepare(
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
     WHERE sc.student_id = :student_id
       AND (sc.ends_on IS NULL OR sc.ends_on >= CURDATE())"
);
$status_stmt->execute([
    ':month'      => $current_month,
    ':student_id' => $student_id,
]);
$current_status = $status_stmt->fetchAll();

// ------------------------------------------------------------
// Full payment history, newest first
// ------------------------------------------------------------
$payments_stmt = $pdo->prepare(
    "SELECT
        fp.receipt_number,
        fp.amount_paid,
        fp.payment_date,
        fp.payment_month,
        fp.notes,
        c.class_name,
        c.grade_level
     FROM fee_payments fp
     JOIN classes c ON c.id = fp.class_id
     WHERE fp.student_id = :student_id
     ORDER BY fp.payment_date DESC, fp.id DESC"
);
$payments_stmt->execute([':student_id' => $student_id]);
$payments = $payments_stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">My Fee Payments</h1>

<!-- Current month payment status per class -->
<div class="card">
    <div class="card-title">
        Current Month Status — <?php echo date('F Y'); ?>
    </div>

    <?php if (empty($current_status)): ?>
        <p style="color:#7f8c8d;">No fee information is available yet. Contact the administrator.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Class</th>
                        <th style="text-align:right;">Monthly Fee</th>
                        <th style="text-align:center;">Status</th>
                        <th>Paid On</th>
                        <th>Receipt No.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($current_status as $row): ?>
                    <tr>
                        <td><?php echo h($row['grade_level'] . ' — ' . $row['class_name']); ?></td>
                        <td style="text-align:right;">LKR <?php echo number_format($row['monthly_amount'], 2); ?></td>
                        <td style="text-align:center;">
                            <?php if ($row['receipt_number']): ?>
                                <span class="badge badge-paid">Paid</span>
                            <?php else: ?>
                                <span class="badge badge-overdue">Not Paid</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo $row['payment_date']
                                ? date('d M Y', strtotime($row['payment_date']))
                                : '—'; ?>
                        </td>
                        <td>
                            <span style="font-family:monospace; font-size:12px;">
                                <?php echo h($row['receipt_number'] ?? '—'); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Full payment history -->
<div class="card">
    <div class="card-title">Payment History</div>

    <?php if (empty($payments)): ?>
        <p style="color:#7f8c8d;">No payment records found.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Receipt No.</th>
                        <th>Month Paid For</th>
                        <th>Class</th>
                        <th style="text-align:right;">Amount (LKR)</th>
                        <th>Date Paid</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $i => $p): ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td>
                            <span style="font-family:monospace; font-size:12px;">
                                <?php echo h($p['receipt_number']); ?>
                            </span>
                        </td>
                        <td><?php echo date('F Y', strtotime($p['payment_month'] . '-01')); ?></td>
                        <td><?php echo h($p['grade_level'] . ' — ' . $p['class_name']); ?></td>
                        <td style="text-align:right; font-weight:bold; color:#27ae60;">
                            <?php echo number_format($p['amount_paid'], 2); ?>
                        </td>
                        <td><?php echo date('d M Y', strtotime($p['payment_date'])); ?></td>
                        <td style="font-size:12px; color:#7f8c8d;">
                            <?php echo h($p['notes'] ?? '—'); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
