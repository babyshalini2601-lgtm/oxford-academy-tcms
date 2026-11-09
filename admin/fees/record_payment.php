<?php
// ============================================================
// admin/fees/record_payment.php — Record a fee payment
//
// Admin selects a student. The page loads the classes that
// student is enrolled in. Admin selects the class, month,
// and enters the amount paid.
//
// On save:
//   1. Enrollment in that class is confirmed in student_classes
//   2. No existing payment for the same student, class, and month
//   3. A receipt number is auto-generated (RCP + date + sequence)
//   4. The payment is inserted into fee_payments
//   5. Admin is redirected to the receipt page
// ============================================================

require_once __DIR__ . '/../../config/db.php';

$required_role = 'admin';
require_once __DIR__ . '/../../includes/auth_check.php';

$pdo        = get_db_connection();
$page_title = 'Record Fee Payment';
$errors     = [];

// Load all active students for the dropdown
$students = $pdo->query(
    "SELECT id, student_code, full_name FROM students WHERE is_active = 1 ORDER BY full_name ASC"
)->fetchAll();

// -------------------------------------------------------
// Helper: generate next receipt number (e.g., RCP202411150001)
// Sequential per day; unique constraint on receipt_number
// prevents any race condition from producing a duplicate.
// -------------------------------------------------------
function generate_receipt_number(PDO $pdo): string
{
    $date_part = date('Ymd');
    $prefix    = 'RCP' . $date_part;

    $stmt = $pdo->prepare(
        "SELECT receipt_number FROM fee_payments
         WHERE receipt_number LIKE :prefix
         ORDER BY receipt_number DESC
         LIMIT 1"
    );
    $stmt->execute([':prefix' => $prefix . '%']);
    $last = $stmt->fetchColumn();

    if ($last) {
        $last_number = (int) substr($last, strlen($prefix));
        $next_number = $last_number + 1;
    } else {
        $next_number = 1;
    }

    return $prefix . str_pad($next_number, 4, '0', STR_PAD_LEFT);
}

// When a student is selected via GET or after a failed POST,
// load their enrolled classes with the current fee for each.
$selected_student_id = (int) ($_GET['student_id'] ?? $_POST['student_id'] ?? 0);
$enrolled_classes    = [];

if ($selected_student_id > 0) {
    $class_stmt = $pdo->prepare(
        "SELECT
            c.id   AS class_id,
            c.class_name,
            c.grade_level,
            fs.monthly_amount
         FROM student_classes sc
         JOIN classes c ON c.id = sc.class_id AND c.is_active = 1
         LEFT JOIN fee_structures fs
             ON fs.class_id = c.id
             AND fs.effective_from = (
                 SELECT MAX(fs2.effective_from)
                 FROM fee_structures fs2
                 WHERE fs2.class_id = c.id
             )
         WHERE sc.student_id = :student_id
           AND (sc.ends_on IS NULL OR sc.ends_on >= CURDATE())
         ORDER BY c.grade_level, c.class_name"
    );
    $class_stmt->execute([':student_id' => $selected_student_id]);
    $enrolled_classes = $class_stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payment'])) {
    // CSRF check
    if (!csrf_ok()) {
        $errors[] = 'The form expired. Please try again.';
    } else {
        $student_id    = (int)   $_POST['student_id'];
        $class_id      = (int)   $_POST['class_id'];
        $amount_paid   = trim($_POST['amount_paid']   ?? '');
        $payment_date  = trim($_POST['payment_date']  ?? '');
        $payment_month = trim($_POST['payment_month'] ?? '');
        $notes         = trim($_POST['notes']         ?? '');

        // --- Validation ---
        if ($student_id <= 0) {
            $errors[] = 'Please select a student.';
        }
        if ($class_id <= 0) {
            $errors[] = 'Please select a class.';
        }
        if (empty($amount_paid) || !is_numeric($amount_paid) || (float) $amount_paid <= 0) {
            $errors[] = 'Please enter a valid payment amount greater than zero.';
        }
        if (!is_valid_date($payment_date)) {
            $errors[] = 'Payment date is required and must be a valid date.';
        }
        if (empty($payment_month) || !preg_match('/^\d{4}-\d{2}$/', $payment_month)) {
            $errors[] = 'Payment month is required (e.g. 2026-11).';
        }

        // Confirm student is enrolled in the selected class
        if (empty($errors)) {
            $enrol_check = $pdo->prepare(
                "SELECT id FROM student_classes
                 WHERE student_id = :student_id
                   AND class_id   = :class_id
                   AND (ends_on IS NULL OR ends_on >= CURDATE())
                 LIMIT 1"
            );
            $enrol_check->execute([
                ':student_id' => $student_id,
                ':class_id'   => $class_id,
            ]);
            if (!$enrol_check->fetch()) {
                $errors[] = 'That student is not enrolled in the selected class.';
            }
        }

        // Reject a duplicate payment for the same student, class, and month
        if (empty($errors)) {
            $dup_check = $pdo->prepare(
                "SELECT id FROM fee_payments
                 WHERE student_id    = :student_id
                   AND class_id      = :class_id
                   AND payment_month = :month
                 LIMIT 1"
            );
            $dup_check->execute([
                ':student_id' => $student_id,
                ':class_id'   => $class_id,
                ':month'      => $payment_month,
            ]);
            if ($dup_check->fetch()) {
                $errors[] = 'A payment for this student, class, and month already exists. '
                          . 'Each month is paid in full once only.';
            }
        }

        if (empty($errors)) {
            $receipt_number = generate_receipt_number($pdo);

            $stmt = $pdo->prepare(
                "INSERT INTO fee_payments
                    (student_id, class_id, amount_paid, payment_date,
                     receipt_number, payment_month, recorded_by, notes)
                 VALUES
                    (:student_id, :class_id, :amount, :pay_date,
                     :receipt, :pay_month, :recorded_by, :notes)"
            );
            $stmt->execute([
                ':student_id'  => $student_id,
                ':class_id'    => $class_id,
                ':amount'      => (float) $amount_paid,
                ':pay_date'    => $payment_date,
                ':receipt'     => $receipt_number,
                ':pay_month'   => $payment_month,
                ':recorded_by' => $_SESSION['user_id'],
                ':notes'       => $notes !== '' ? $notes : null,
            ]);

            $new_payment_id = $pdo->lastInsertId();

            // Redirect to the receipt page — POST-redirect-GET prevents resubmission
            redirect_to('/admin/fees/payment_receipt.php?id=' . $new_payment_id);
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<h1 class="page-title">Record Fee Payment</h1>

<?php show_form_errors($errors); ?>

<!-- Step 1: select a student -->
<div class="card">
    <div class="card-title">Select Student</div>
    <form method="GET" action="">
        <div class="form-row">
            <div class="form-group">
                <label for="student_id">Student</label>
                <select id="student_id" name="student_id"
                        onchange="this.form.submit()" required>
                    <option value="0">— Select Student —</option>
                    <?php foreach ($students as $stu): ?>
                        <option value="<?php echo (int) $stu['id']; ?>"
                            <?php echo ($selected_student_id === (int) $stu['id']) ? 'selected' : ''; ?>>
                            <?php echo h($stu['student_code'] . ' — ' . $stu['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </form>
</div>

<!-- Step 2: payment form — only shown once a student is selected -->
<?php if ($selected_student_id > 0): ?>

    <?php
    // Find selected student's name for the heading
    $selected_student_name = '';
    foreach ($students as $s) {
        if ((int) $s['id'] === $selected_student_id) {
            $selected_student_name = $s['full_name'] . ' (' . $s['student_code'] . ')';
            break;
        }
    }
    ?>

    <div class="card">
        <div class="card-title">
            Payment Details for <?php echo h($selected_student_name); ?>
        </div>

        <?php if (empty($enrolled_classes)): ?>
            <p style="color:#e67e22;">
                This student is not enrolled in any active class yet.
            </p>
        <?php else: ?>

        <form method="POST" action="">
            <?php csrf_field(); ?>
            <input type="hidden" name="student_id"   value="<?php echo $selected_student_id; ?>">
            <input type="hidden" name="save_payment" value="1">

            <div class="form-row">
                <div class="form-group">
                    <label for="class_id">Class <span style="color:#e74c3c;">*</span></label>
                    <select id="class_id" name="class_id"
                            onchange="fillAmount(this)" required>
                        <option value="0">— Select Class —</option>
                        <?php foreach ($enrolled_classes as $cls): ?>
                            <option value="<?php echo (int) $cls['class_id']; ?>"
                                data-fee="<?php echo h($cls['monthly_amount'] ?? ''); ?>"
                                <?php echo ((int) ($_POST['class_id'] ?? 0) === (int) $cls['class_id']) ? 'selected' : ''; ?>>
                                <?php echo h($cls['grade_level'] . ' — ' . $cls['class_name']); ?>
                                <?php if ($cls['monthly_amount']): ?>
                                    (LKR <?php echo number_format($cls['monthly_amount'], 2); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="payment_month">
                        Payment Month <span style="color:#e74c3c;">*</span>
                    </label>
                    <input type="month" id="payment_month" name="payment_month"
                        value="<?php echo h($_POST['payment_month'] ?? date('Y-m')); ?>"
                        required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="amount_paid">
                        Amount Paid (LKR) <span style="color:#e74c3c;">*</span>
                    </label>
                    <input type="number" id="amount_paid" name="amount_paid"
                        step="0.01" min="0.01"
                        value="<?php echo h($_POST['amount_paid'] ?? ''); ?>"
                        placeholder="Auto-filled from fee structure" required>
                </div>
                <div class="form-group">
                    <label for="payment_date">
                        Payment Date <span style="color:#e74c3c;">*</span>
                    </label>
                    <input type="date" id="payment_date" name="payment_date"
                        value="<?php echo h($_POST['payment_date'] ?? date('Y-m-d')); ?>"
                        max="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="notes">Notes (optional)</label>
                <input type="text" id="notes" name="notes"
                    value="<?php echo h($_POST['notes'] ?? ''); ?>"
                    placeholder="e.g., Paid by parent at office">
            </div>

            <div style="margin-top:8px;">
                <p style="font-size:13px; color:#7f8c8d; margin-bottom:12px;">
                    A unique receipt number is generated automatically on save.
                    Each month may be paid in full once only — a duplicate is rejected.
                </p>
                <button type="submit" class="btn btn-success">Save Payment &amp; Print Receipt</button>
            </div>
        </form>
        <?php endif; ?>
    </div>

<?php endif; ?>

<script>
// Auto-fill the amount field when a class is selected,
// using the data-fee attribute on each option.
function fillAmount(selectEl) {
    var fee = selectEl.options[selectEl.selectedIndex].getAttribute('data-fee');
    if (fee) {
        document.getElementById('amount_paid').value = fee;
    }
}
// Fill on initial load if a class is already chosen
(function () {
    var sel = document.getElementById('class_id');
    if (sel) { fillAmount(sel); }
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
