<?php
// ============================================================
// admin/fees/payment_receipt.php — Printable payment receipt
//
// Loaded after recording a payment (or by passing ?id=payment_id).
// Shows a formal receipt layout that the admin can print and
// hand to the student or parent.
// ============================================================

require_once __DIR__ . '/../../config/db.php';

$required_role = 'admin';
require_once __DIR__ . '/../../includes/auth_check.php';

$pdo        = get_db_connection();
$page_title = 'Payment Receipt';

$payment_id = (int) ($_GET['id'] ?? 0);
if ($payment_id === 0) {
    header('Location: ' . BASE_URL . '/admin/fees/record_payment.php');
    exit;
}

// Load the payment record with all related details
$stmt = $pdo->prepare(
    "SELECT
        fp.id,
        fp.receipt_number,
        fp.amount_paid,
        fp.payment_date,
        fp.payment_month,
        fp.notes,
        fp.created_at,
        s.student_code,
        s.full_name      AS student_name,
        s.guardian_name,
        c.class_name,
        c.grade_level,
        u.username       AS recorded_by_username
     FROM fee_payments fp
     JOIN students s ON s.id  = fp.student_id
     JOIN classes  c ON c.id  = fp.class_id
     JOIN users    u ON u.id  = fp.recorded_by
     WHERE fp.id = :id"
);
$stmt->execute([':id' => $payment_id]);
$payment = $stmt->fetch();

if (!$payment) {
    header('Location: ' . BASE_URL . '/admin/fees/record_payment.php');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="no-print" style="display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap;">
    <button id="print-btn" class="btn btn-print">Print Receipt</button>
    <a href="<?php echo BASE_URL; ?>/admin/fees/record_payment.php" class="btn btn-primary">Record Another Payment</a>
    <a href="<?php echo BASE_URL; ?>/admin/fees/overdue_list.php"   class="btn btn-secondary">View Overdue List</a>
</div>

<!-- Receipt box — centred, formal layout -->
<div style="max-width: 600px; margin: 0 auto; background:#fff; border:2px solid #1a3a5c; border-radius:6px; padding:36px 40px;">

    <!-- Header -->
    <div style="text-align:center; margin-bottom:28px; border-bottom:1px solid #dde2e8; padding-bottom:20px;">
        <div style="font-size:22px; font-weight:bold; color:#1a3a5c; letter-spacing:1px;">
            OXFORD ACADEMY
        </div>
        <div style="font-size:13px; color:#7f8c8d; margin-top:4px;">
            Nedunkerny, Vavuniya, Sri Lanka
        </div>
        <div style="font-size:18px; font-weight:bold; color:#27ae60; margin-top:14px; letter-spacing:2px;">
            FEE RECEIPT
        </div>
    </div>

    <!-- Receipt details -->
    <table style="width:100%; font-size:14px; margin-bottom:24px;">
        <tr>
            <td style="padding:5px 0; color:#7f8c8d; width:160px;">Receipt No.</td>
            <td style="padding:5px 0; font-weight:bold; font-family:monospace; color:#1a3a5c;">
                <?php echo htmlspecialchars($payment['receipt_number']); ?>
            </td>
        </tr>
        <tr>
            <td style="padding:5px 0; color:#7f8c8d;">Date</td>
            <td style="padding:5px 0;"><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></td>
        </tr>
        <tr>
            <td style="padding:5px 0; color:#7f8c8d;">Payment For</td>
            <td style="padding:5px 0;"><?php echo date('F Y', strtotime($payment['payment_month'] . '-01')); ?></td>
        </tr>
    </table>

    <hr style="border:none; border-top:1px dashed #bdc3c7; margin:20px 0;">

    <!-- Student details -->
    <table style="width:100%; font-size:14px; margin-bottom:24px;">
        <tr>
            <td style="padding:5px 0; color:#7f8c8d; width:160px;">Student Name</td>
            <td style="padding:5px 0;"><strong><?php echo htmlspecialchars($payment['student_name']); ?></strong></td>
        </tr>
        <tr>
            <td style="padding:5px 0; color:#7f8c8d;">Student ID</td>
            <td style="padding:5px 0; font-family:monospace;">
                <?php echo htmlspecialchars($payment['student_code']); ?>
            </td>
        </tr>
        <tr>
            <td style="padding:5px 0; color:#7f8c8d;">Guardian</td>
            <td style="padding:5px 0;"><?php echo htmlspecialchars($payment['guardian_name'] ?? '—'); ?></td>
        </tr>
        <tr>
            <td style="padding:5px 0; color:#7f8c8d;">Class</td>
            <td style="padding:5px 0;">
                <?php echo htmlspecialchars($payment['grade_level'] . ' — ' . $payment['class_name']); ?>
            </td>
        </tr>
    </table>

    <hr style="border:none; border-top:1px dashed #bdc3c7; margin:20px 0;">

    <!-- Amount -->
    <div style="background:#f0f9f4; border:1px solid #a8d5b5; border-radius:4px; padding:16px 20px; text-align:center; margin-bottom:24px;">
        <div style="font-size:13px; color:#7f8c8d;">Amount Paid</div>
        <div style="font-size:30px; font-weight:bold; color:#1a3a5c; margin-top:4px;">
            LKR <?php echo number_format($payment['amount_paid'], 2); ?>
        </div>
    </div>

    <?php if (!empty($payment['notes'])): ?>
    <div style="background:#fff3cd; border-radius:4px; padding:10px 14px; margin-bottom:24px; font-size:13px; color:#856404;">
        <strong>Note:</strong> <?php echo htmlspecialchars($payment['notes']); ?>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <div style="display:flex; justify-content:space-between; font-size:12px; color:#7f8c8d; border-top:1px solid #dde2e8; padding-top:16px; margin-top:4px;">
        <div>
            Recorded by: <?php echo htmlspecialchars($payment['recorded_by_username']); ?><br>
            Recorded at: <?php echo date('d M Y H:i', strtotime($payment['created_at'])); ?>
        </div>
        <div style="text-align:right;">
            <div style="border-top:1px solid #2c3e50; padding-top:4px; margin-top:30px; min-width:140px; text-align:center;">
                Authorised Signature
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
