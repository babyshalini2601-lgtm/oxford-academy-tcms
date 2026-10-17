<?php
// Update profile and subject choices. Old enrollments are closed with
// an end date instead of being deleted, so past records stay meaningful.

require_once __DIR__ . '/../../config/db.php';

$required_role = 'admin';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/enrollment.php';

$pdo        = get_db_connection();
$page_title = 'Edit Student';
$errors     = [];
$message    = '';

$student_id = (int) ($_GET['id'] ?? 0);
if ($student_id === 0) {
    redirect_to('/admin/students/list_students.php');
}

$stmt = $pdo->prepare("SELECT * FROM students WHERE id = :id");
$stmt->execute([':id' => $student_id]);
$student = $stmt->fetch();

if (!$student) {
    redirect_to('/admin/students/list_students.php');
}

$classes            = get_active_classes($pdo);
$offerings_by_class = get_offerings_by_class($pdo);
$class_id           = current_class_id($pdo, $student_id);
$subject_ids        = current_subject_ids($pdo, $student_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $errors[] = 'This form expired. Please try again.';
    }

    $full_name      = post_value('full_name');
    $date_of_birth  = post_value('date_of_birth');
    $address        = post_value('address');
    $guardian_name  = post_value('guardian_name');
    $guardian_phone = post_value('guardian_phone');
    $guardian_email = post_value('guardian_email');
    $enrolled_date  = post_value('enrolled_date');
    $is_active      = isset($_POST['is_active']) ? 1 : 0;
    $new_password   = $_POST['new_password'] ?? '';
    $class_id       = (int) ($_POST['class_id'] ?? 0);
    $subject_ids    = array_map('intval', $_POST['subject_ids'] ?? []);

    if (!is_filled($full_name)) {
        $errors[] = 'Full name is required.';
    }
    if (!is_valid_date($date_of_birth)) {
        $errors[] = 'Date of birth is required.';
    }
    if (!is_filled($address)) {
        $errors[] = 'Address is required.';
    }
    if (!is_filled($guardian_name)) {
        $errors[] = 'Guardian name is required.';
    }
    if (!is_filled($guardian_phone)) {
        $errors[] = 'Guardian phone is required.';
    }
    if (!is_valid_date($enrolled_date)) {
        $errors[] = 'Enrollment date is required.';
    }
    if ($guardian_email !== '' && !filter_var($guardian_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Guardian email address is not valid.';
    }
    if ($new_password !== '' && !password_long_enough($new_password)) {
        $errors[] = 'New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    }
    if ($class_id <= 0) {
        $errors[] = 'Please select a class.';
    }
    if (empty($subject_ids)) {
        $errors[] = 'Select at least one subject.';
    }

    if ($class_id > 0 && empty($errors)) {
        foreach ($subject_ids as $subject_id) {
            if (!class_offers_subject($pdo, $class_id, $subject_id)) {
                $errors[] = 'One of the selected subjects is not offered in that class.';
                break;
            }
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $update = $pdo->prepare(
                "UPDATE students SET
                    full_name = :name,
                    date_of_birth = :dob,
                    address = :address,
                    guardian_name = :g_name,
                    guardian_phone = :g_phone,
                    guardian_email = :g_email,
                    enrolled_date = :enrolled,
                    is_active = :active
                 WHERE id = :id"
            );
            $update->execute([
                ':name'     => $full_name,
                ':dob'      => $date_of_birth,
                ':address'  => $address,
                ':g_name'   => $guardian_name,
                ':g_phone'  => $guardian_phone,
                ':g_email'  => $guardian_email !== '' ? $guardian_email : null,
                ':enrolled' => $enrolled_date,
                ':active'   => $is_active,
                ':id'       => $student_id,
            ]);

            apply_enrollment($pdo, $student_id, $class_id, $subject_ids, $enrolled_date);

            $pdo->prepare(
                "UPDATE users SET is_active = :active WHERE linked_id = :linked AND role = 'student'"
            )->execute([':active' => $is_active, ':linked' => $student_id]);

            if ($new_password !== '') {
                $pdo->prepare(
                    "UPDATE users SET password_hash = :hash WHERE linked_id = :linked AND role = 'student'"
                )->execute([
                    ':hash'   => password_hash($new_password, PASSWORD_BCRYPT),
                    ':linked' => $student_id,
                ]);
            }

            $pdo->commit();
            $message = 'Student profile updated. History was kept.';

            $stmt = $pdo->prepare("SELECT * FROM students WHERE id = :id");
            $stmt->execute([':id' => $student_id]);
            $student = $stmt->fetch();
            $class_id    = current_class_id($pdo, $student_id);
            $subject_ids = current_subject_ids($pdo, $student_id);
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Student update failed: ' . $e->getMessage());
            $errors[] = 'The change was not saved. Please try again.';
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<h1 class="page-title">
    Edit Student —
    <span style="font-size:16px; color:#7f8c8d; font-weight:normal;"><?php echo h($student['student_code']); ?></span>
</h1>

<?php if ($message !== ''): ?>
    <div class="alert alert-success"><?php echo h($message); ?></div>
<?php endif; ?>
<?php show_form_errors($errors); ?>

<form method="POST" action="">
    <?php csrf_field(); ?>

    <div class="card">
        <div class="card-title">Personal Details</div>
        <div class="form-row">
            <div class="form-group">
                <label for="full_name">Full Name <span style="color:#e74c3c;">*</span></label>
                <input type="text" id="full_name" name="full_name" value="<?php echo h($student['full_name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="date_of_birth">Date of Birth <span style="color:#e74c3c;">*</span></label>
                <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo h($student['date_of_birth'] ?? ''); ?>" required>
            </div>
        </div>
        <div class="form-group">
            <label for="address">Address <span style="color:#e74c3c;">*</span></label>
            <textarea id="address" name="address" rows="2" required><?php echo h($student['address'] ?? ''); ?></textarea>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="enrolled_date">Enrollment Date <span style="color:#e74c3c;">*</span></label>
                <input type="date" id="enrolled_date" name="enrolled_date" value="<?php echo h($student['enrolled_date']); ?>" required>
            </div>
            <div class="form-group" style="padding-top:28px;">
                <label>
                    <input type="checkbox" name="is_active" value="1" <?php echo $student['is_active'] ? 'checked' : ''; ?>>
                    Student is active (untick to deactivate the login, keep the records)
                </label>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-title">Guardian / Parent Details</div>
        <div class="form-row">
            <div class="form-group">
                <label for="guardian_name">Guardian Name <span style="color:#e74c3c;">*</span></label>
                <input type="text" id="guardian_name" name="guardian_name" value="<?php echo h($student['guardian_name'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="guardian_phone">Guardian Phone <span style="color:#e74c3c;">*</span></label>
                <input type="text" id="guardian_phone" name="guardian_phone" value="<?php echo h($student['guardian_phone'] ?? ''); ?>" required>
            </div>
        </div>
        <div class="form-group">
            <label for="guardian_email">Guardian Email</label>
            <input type="email" id="guardian_email" name="guardian_email" value="<?php echo h($student['guardian_email'] ?? ''); ?>">
        </div>
    </div>

    <div class="card">
        <div class="card-title">Class and subjects</div>
        <div class="form-group">
            <label for="class_id">Class <span style="color:#e74c3c;">*</span></label>
            <select id="class_id" name="class_id" required>
                <option value="0">— Select class —</option>
                <?php foreach ($classes as $cls): ?>
                    <option value="<?php echo (int) $cls['id']; ?>" <?php echo $class_id === (int) $cls['id'] ? 'selected' : ''; ?>>
                        <?php echo h($cls['class_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div id="subject-box"></div>
    </div>

    <div class="card">
        <div class="card-title">Reset Password (optional)</div>
        <div class="form-row">
            <div class="form-group">
                <label>Username</label>
                <input type="text" value="<?php echo h($student['student_code']); ?>" disabled style="background:#f0f2f5; color:#7f8c8d;">
            </div>
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" placeholder="Leave blank to keep the current password">
            </div>
        </div>
    </div>

    <div style="display:flex; gap:10px; margin-bottom:30px;">
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="<?php echo BASE_URL; ?>/admin/students/view_student.php?id=<?php echo $student_id; ?>" class="btn btn-secondary">View Profile</a>
        <a href="<?php echo BASE_URL; ?>/admin/students/list_students.php" class="btn btn-secondary">Back to List</a>
    </div>
</form>

<script>
var offerings = <?php echo json_encode($offerings_by_class, JSON_UNESCAPED_UNICODE); ?>;
var selectedSubjects = <?php echo json_encode(array_values($subject_ids)); ?>;

function renderSubjects() {
    var classId = document.getElementById('class_id').value;
    var box = document.getElementById('subject-box');
    var list = offerings[classId] || [];

    if (classId === '0' || list.length === 0) {
        box.innerHTML = '<p style="color:#e67e22;">Select a class that has subjects assigned.</p>';
        return;
    }

    var html = '<p style="font-weight:bold; margin-bottom:8px;">Subjects in this class</p>';
    list.forEach(function (item) {
        var checked = selectedSubjects.indexOf(item.subject_id) !== -1 ? ' checked' : '';
        html += '<label style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">';
        html += '<input type="checkbox" name="subject_ids[]" value="' + item.subject_id + '"' + checked + '>';
        html += item.subject_name;
        html += '</label>';
    });
    box.innerHTML = html;
}

document.getElementById('class_id').addEventListener('change', function () {
    selectedSubjects = [];
    renderSubjects();
});
renderSubjects();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
