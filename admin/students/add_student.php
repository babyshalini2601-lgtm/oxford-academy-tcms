<?php
// Register a student, pick one class, then pick only the subjects
// that student takes. All inserts run in one transaction.

require_once __DIR__ . '/../../config/db.php';

$required_role = 'admin';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/enrollment.php';

$pdo        = get_db_connection();
$page_title = 'Register New Student';
$errors     = [];

$classes           = get_active_classes($pdo);
$offerings_by_class = get_offerings_by_class($pdo);

$full_name      = '';
$date_of_birth  = '';
$address        = '';
$guardian_name  = '';
$guardian_phone = '';
$guardian_email = '';
$enrolled_date  = app_today();
$class_id       = 0;
$subject_ids    = [];

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
    $password       = $_POST['password'] ?? '';
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
    if (!password_long_enough($password)) {
        $errors[] = 'Initial password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    }
    if ($class_id <= 0) {
        $errors[] = 'Please select a class.';
    }
    if (empty($subject_ids)) {
        $errors[] = 'Select at least one subject. A student does not have to take every subject in the class.';
    }

    if ($class_id > 0 && empty($errors)) {
        $class_ok = $pdo->prepare("SELECT id FROM classes WHERE id = :id AND is_active = 1");
        $class_ok->execute([':id' => $class_id]);
        if (!$class_ok->fetchColumn()) {
            $errors[] = 'That class is not available.';
        }
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
        $student_code = generate_student_code($pdo);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "INSERT INTO students
                    (student_code, full_name, date_of_birth, address,
                     guardian_name, guardian_phone, guardian_email, enrolled_date, is_active)
                 VALUES
                    (:code, :name, :dob, :address,
                     :g_name, :g_phone, :g_email, :enrolled, 1)"
            );
            $stmt->execute([
                ':code'     => $student_code,
                ':name'     => $full_name,
                ':dob'      => $date_of_birth,
                ':address'  => $address,
                ':g_name'   => $guardian_name,
                ':g_phone'  => $guardian_phone,
                ':g_email'  => $guardian_email !== '' ? $guardian_email : null,
                ':enrolled' => $enrolled_date,
            ]);
            $student_id = (int) $pdo->lastInsertId();

            apply_enrollment($pdo, $student_id, $class_id, $subject_ids, $enrolled_date);

            $user_stmt = $pdo->prepare(
                "INSERT INTO users (username, password_hash, role, linked_id, is_active)
                 VALUES (:username, :password_hash, 'student', :linked_id, 1)"
            );
            $user_stmt->execute([
                ':username'      => $student_code,
                ':password_hash' => password_hash($password, PASSWORD_BCRYPT),
                ':linked_id'     => $student_id,
            ]);

            $pdo->commit();
            redirect_to('/admin/students/view_student.php?id=' . $student_id . '&added=1');
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Student registration failed: ' . $e->getMessage());
            $errors[] = 'The student was not saved. Nothing was written to the database. Please try again.';
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<h1 class="page-title">Register New Student</h1>

<?php show_form_errors($errors); ?>

<form method="POST" action="" id="student-form">
    <?php csrf_field(); ?>

    <div class="card">
        <div class="card-title">Personal Details</div>
        <div class="form-row">
            <div class="form-group">
                <label for="full_name">Full Name <span style="color:#e74c3c;">*</span></label>
                <input type="text" id="full_name" name="full_name" value="<?php echo h($full_name); ?>" required>
            </div>
            <div class="form-group">
                <label for="date_of_birth">Date of Birth <span style="color:#e74c3c;">*</span></label>
                <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo h($date_of_birth); ?>" required>
            </div>
        </div>
        <div class="form-group">
            <label for="address">Address <span style="color:#e74c3c;">*</span></label>
            <textarea id="address" name="address" rows="2" required><?php echo h($address); ?></textarea>
        </div>
        <div class="form-group">
            <label for="enrolled_date">Enrollment Date <span style="color:#e74c3c;">*</span></label>
            <input type="date" id="enrolled_date" name="enrolled_date" value="<?php echo h($enrolled_date); ?>" required>
        </div>
    </div>

    <div class="card">
        <div class="card-title">Guardian / Parent Details</div>
        <div class="form-row">
            <div class="form-group">
                <label for="guardian_name">Guardian Name <span style="color:#e74c3c;">*</span></label>
                <input type="text" id="guardian_name" name="guardian_name" value="<?php echo h($guardian_name); ?>" required>
            </div>
            <div class="form-group">
                <label for="guardian_phone">Guardian Phone <span style="color:#e74c3c;">*</span></label>
                <input type="text" id="guardian_phone" name="guardian_phone" value="<?php echo h($guardian_phone); ?>" required>
            </div>
        </div>
        <div class="form-group">
            <label for="guardian_email">Guardian Email</label>
            <input type="email" id="guardian_email" name="guardian_email" value="<?php echo h($guardian_email); ?>">
        </div>
    </div>

    <div class="card">
        <div class="card-title">Class and subjects</div>
        <p style="color:#7f8c8d; font-size:13px; margin-bottom:14px;">
            Choose the class first. Then tick only the subjects this student takes.
            The monthly class fee is the same whether they take one subject or all of them.
        </p>
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
        <div id="subject-box">
            <p style="color:#7f8c8d;">Subjects appear after you select a class.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-title">Login Account</div>
        <p style="color:#7f8c8d; font-size:13px; margin-bottom:14px;">
            The username is the student ID, created automatically when you save.
        </p>
        <div class="form-row">
            <div class="form-group">
                <label>Username (Student ID)</label>
                <input type="text" value="Auto-generated (for example OXF<?php echo date('Y'); ?>001)" disabled style="background:#f0f2f5; color:#7f8c8d;">
            </div>
            <div class="form-group">
                <label for="password">Initial Password <span style="color:#e74c3c;">*</span></label>
                <input type="password" id="password" name="password" minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" placeholder="At least <?php echo PASSWORD_MIN_LENGTH; ?> characters" required>
            </div>
        </div>
    </div>

    <div style="display:flex; gap:10px; margin-bottom:30px;">
        <button type="submit" class="btn btn-primary">Register Student</button>
        <a href="<?php echo BASE_URL; ?>/admin/students/list_students.php" class="btn btn-secondary">Cancel</a>
    </div>
</form>

<script>
var offerings = <?php echo json_encode($offerings_by_class, JSON_UNESCAPED_UNICODE); ?>;
var selectedSubjects = <?php echo json_encode(array_values($subject_ids)); ?>;

function renderSubjects() {
    var classId = document.getElementById('class_id').value;
    var box = document.getElementById('subject-box');
    var list = offerings[classId] || [];

    if (classId === '0') {
        box.innerHTML = '<p style="color:#7f8c8d;">Subjects appear after you select a class.</p>';
        return;
    }

    if (list.length === 0) {
        box.innerHTML = '<p style="color:#e67e22;">This class has no subjects yet. Assign subjects on the class page first.</p>';
        return;
    }

    var html = '<p style="font-weight:bold; margin-bottom:8px;">Subjects in this class <span style="color:#e74c3c;">*</span></p>';
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
