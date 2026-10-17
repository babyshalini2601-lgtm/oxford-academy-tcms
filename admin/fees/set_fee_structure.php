<?php
// ============================================================
// admin/fees/set_fee_structure.php — Configure monthly fees
//
// Displays all classes. For each class the admin can set or
// update the monthly fee amount.
//
// Design note: when a fee is updated a NEW row is inserted into
// fee_structures with today as the effective_from date. This
// preserves the fee history so old payment records remain accurate.
// The "current fee" is always the record with the latest effective_from.
// ============================================================

require_once __DIR__ . '/../../config/db.php';

$required_role = 'admin';
require_once __DIR__ . '/../../includes/auth_check.php';

$pdo        = get_db_connection();
$page_title = 'Fee Structure';
$message    = '';
$msg_type   = '';

// Handle form submission — admin sets a new fee for a class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['class_id'])) {
    $class_id  = (int)   $_POST['class_id'];
    $amount    = trim($_POST['monthly_amount'] ?? '');

    if (empty($amount) || !is_numeric($amount) || (float)$amount <= 0) {
        $message  = 'Please enter a valid fee amount greater than zero.';
        $msg_type = 'error';
    } else {
        // Insert a new fee record with today's date as effective_from.
        // This records the change history without deleting the old rate.
        $stmt = $pdo->prepare(
            "INSERT INTO fee_structures (class_id, monthly_amount, effective_from)
             VALUES (:class_id, :amount, CURDATE())"
        );
        $stmt->execute([
            ':class_id' => $class_id,
            ':amount'   => (float) $amount,
        ]);

        $message  = 'Fee updated successfully.';
        $msg_type = 'success';
    }
}

// Fetch all active classes with their current (latest) fee.
// The subquery finds the most recent effective_from date per class.
$classes = $pdo->query(
    "SELECT
        c.id,
        c.class_name,
        c.grade_level,
        fs.monthly_amount,
        fs.effective_from
     FROM classes c
     LEFT JOIN fee_structures fs
         ON fs.class_id = c.id
         AND fs.effective_from = (
             SELECT MAX(fs2.effective_from)
             FROM fee_structures fs2
             WHERE fs2.class_id = c.id
         )
     WHERE c.is_active = 1
     ORDER BY c.grade_level ASC, c.class_name ASC"
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<h1 class="page-title">Fee Structure</h1>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $msg_type; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-title">Monthly Fee per Class</div>
    <p style="color:#7f8c8d; font-size:13px; margin-bottom:16px;">
        Set the monthly fee for each class. Updating a fee saves the new rate with today's date
        and keeps the old rate in history so past payment records remain accurate.
    </p>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Class</th>
                    <th>Grade</th>
                    <th>Current Fee (LKR)</th>
                    <th>Effective From</th>
                    <th>Update Fee</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($classes)): ?>
                <tr>
                    <td colspan="6" style="text-align:center; color:#7f8c8d;">
                        No classes found. <a href="<?php echo BASE_URL; ?>/admin/classes/add_class.php">Add classes first</a>.
                    </td>
                </tr>
                <?php endif; ?>

                <?php foreach ($classes as $i => $cls): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td><strong><?php echo htmlspecialchars($cls['class_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($cls['grade_level']); ?></td>
                    <td>
                        <?php if ($cls['monthly_amount'] !== null): ?>
                            <strong style="color:#27ae60;">
                                LKR <?php echo number_format($cls['monthly_amount'], 2); ?>
                            </strong>
                        <?php else: ?>
                            <span style="color:#e67e22;">Not set</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo $cls['effective_from']
                            ? date('d M Y', strtotime($cls['effective_from']))
                            : '—'; ?>
                    </td>
                    <td>
                        <!-- Inline form to update the fee for this class -->
                        <form method="POST" action="" style="display:flex; gap:6px; align-items:center;">
                            <input type="hidden" name="class_id" value="<?php echo $cls['id']; ?>">
                            <input
                                type="number"
                                name="monthly_amount"
                                step="0.01"
                                min="1"
                                placeholder="Amount"
                                value="<?php echo $cls['monthly_amount'] ?? ''; ?>"
                                style="width:110px; padding:6px 8px; border:1px solid #bdc3c7; border-radius:4px; font-size:13px;"
                            >
                            <button type="submit" class="btn btn-sm btn-primary">Save</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card" style="margin-top:4px;">
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a href="<?php echo BASE_URL; ?>/admin/fees/record_payment.php"  class="btn btn-success">Record a Payment</a>
        <a href="<?php echo BASE_URL; ?>/admin/fees/overdue_list.php"     class="btn btn-warning">View Overdue List</a>
        <a href="<?php echo BASE_URL; ?>/admin/fees/monthly_summary.php"  class="btn btn-secondary">Monthly Summary</a>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
