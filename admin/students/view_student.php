<?php
require_once __DIR__ . '/../../config/db.php';

$required_role = 'admin';
require_once __DIR__ . '/../../includes/auth_check.php';

$pdo        = get_db_connection();
$page_title = 'Student Profile';

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

$classes_stmt = $pdo->prepare(
    "SELECT c.class_name, c.grade_level, sc.enrolled_date, sc.ends_on
     FROM student_classes sc
     JOIN classes c ON c.id = sc.class_id
     WHERE sc.student_id = :student_id
     ORDER BY sc.ends_on IS NULL DESC, c.grade_level, c.class_name"
);
$classes_stmt->execute([':student_id' => $student_id]);
$enrolled_classes = $classes_stmt->fetchAll();

$subjects_stmt = $pdo->prepare(
    "SELECT c.class_name, s.subject_name, ss.starts_on, ss.ends_on
     FROM student_subjects ss
     JOIN classes c ON c.id = ss.class_id
     JOIN subjects s ON s.id = ss.subject_id
     WHERE ss.student_id = :student_id
     ORDER BY ss.ends_on IS NULL DESC, s.subject_name"
);
$subjects_stmt->execute([':student_id' => $student_id]);
$enrolled_subjects = $subjects_stmt->fetchAll();

$user_stmt = $pdo->prepare(
    "SELECT username, is_active, created_at FROM users
     WHERE linked_id = :id AND role = 'student'"
);
$user_stmt->execute([':id' => $student_id]);
$user_account = $user_stmt->fetch();

$page_title = 'Profile: ' . $student['full_name'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:20px;">
    <h1 class="page-title" style="margin:0; border:none; padding:0;">Student Profile</h1>
    <div class="no-print" style="display:flex; gap:8px;">
        <a href="<?php echo BASE_URL; ?>/admin/students/edit_student.php?id=<?php echo $student_id; ?>" class="btn btn-warning">Edit Profile</a>
        <button id="print-btn" class="btn btn-print">Print</button>
        <a href="<?php echo BASE_URL; ?>/admin/students/list_students.php" class="btn btn-secondary">Back to List</a>
    </div>
</div>

<?php if (isset($_GET['added'])): ?>
    <div class="alert alert-success">Student registered. Login username: <strong><?php echo h($student['student_code']); ?></strong></div>
<?php endif; ?>

<div class="card">
    <div style="display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:16px;">
        <div>
            <div style="font-size:26px; font-weight:bold; color:#1a3a5c;"><?php echo h($student['full_name']); ?></div>
            <div style="font-size:18px; color:#2980b9; font-family:monospace; margin-top:4px;"><?php echo h($student['student_code']); ?></div>
        </div>
        <div>
            <?php if ($student['is_active']): ?>
                <span class="badge badge-active" style="font-size:14px; padding:6px 16px;">Active</span>
            <?php else: ?>
                <span class="badge badge-inactive" style="font-size:14px; padding:6px 16px;">Inactive</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
    <div class="card">
        <div class="card-title">Personal Details</div>
        <table style="width:100%;">
            <tr>
                <td style="padding:6px 0; font-weight:bold; color:#7f8c8d; width:140px;">Date of Birth</td>
                <td><?php echo !empty($student['date_of_birth']) ? date('d M Y', strtotime($student['date_of_birth'])) : '—'; ?></td>
            </tr>
            <tr>
                <td style="padding:6px 0; font-weight:bold; color:#7f8c8d;">Address</td>
                <td><?php echo h($student['address'] ?? '—'); ?></td>
            </tr>
            <tr>
                <td style="padding:6px 0; font-weight:bold; color:#7f8c8d;">Enrolled On</td>
                <td><?php echo date('d M Y', strtotime($student['enrolled_date'])); ?></td>
            </tr>
        </table>
    </div>

    <div class="card">
        <div class="card-title">Guardian / Parent Contact</div>
        <table style="width:100%;">
            <tr>
                <td style="padding:6px 0; font-weight:bold; color:#7f8c8d; width:140px;">Name</td>
                <td><?php echo h($student['guardian_name'] ?? '—'); ?></td>
            </tr>
            <tr>
                <td style="padding:6px 0; font-weight:bold; color:#7f8c8d;">Phone</td>
                <td><?php echo h($student['guardian_phone'] ?? '—'); ?></td>
            </tr>
            <tr>
                <td style="padding:6px 0; font-weight:bold; color:#7f8c8d;">Email</td>
                <td><?php echo h($student['guardian_email'] ?? '—'); ?></td>
            </tr>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-title">Class enrollment</div>
    <?php if (empty($enrolled_classes)): ?>
        <p style="color:#7f8c8d;">Not enrolled in any class.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Class</th>
                        <th>From</th>
                        <th>Until</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($enrolled_classes as $cls): ?>
                    <tr>
                        <td><?php echo h($cls['class_name']); ?></td>
                        <td><?php echo date('d M Y', strtotime($cls['enrolled_date'])); ?></td>
                        <td><?php echo $cls['ends_on'] ? date('d M Y', strtotime($cls['ends_on'])) : 'Current'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-title">Chosen subjects</div>
    <p style="color:#7f8c8d; font-size:13px; margin-bottom:12px;">
        Only these subjects are used for attendance and exams. The class fee does not change with the subject count.
    </p>
    <?php if (empty($enrolled_subjects)): ?>
        <p style="color:#7f8c8d;">No subjects selected.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Class</th>
                        <th>From</th>
                        <th>Until</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($enrolled_subjects as $sub): ?>
                    <tr>
                        <td><?php echo h($sub['subject_name']); ?></td>
                        <td><?php echo h($sub['class_name']); ?></td>
                        <td><?php echo date('d M Y', strtotime($sub['starts_on'])); ?></td>
                        <td><?php echo $sub['ends_on'] ? date('d M Y', strtotime($sub['ends_on'])) : 'Current'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-title">Login Account</div>
    <?php if ($user_account): ?>
        <table style="width:auto;">
            <tr>
                <td style="padding:6px 20px 6px 0; font-weight:bold; color:#7f8c8d;">Username</td>
                <td><span style="font-family:monospace;"><?php echo h($user_account['username']); ?></span></td>
            </tr>
            <tr>
                <td style="padding:6px 20px 6px 0; font-weight:bold; color:#7f8c8d;">Account Status</td>
                <td>
                    <?php if ($user_account['is_active']): ?>
                        <span class="badge badge-active">Active</span>
                    <?php else: ?>
                        <span class="badge badge-inactive">Inactive</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    <?php else: ?>
        <p style="color:#e74c3c;">No login account found for this student.</p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
