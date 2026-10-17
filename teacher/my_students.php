<?php
require_once __DIR__ . '/../config/db.php';

$required_role = 'teacher';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/teacher_access.php';

$pdo        = get_db_connection();
$page_title = 'My Students';
$teacher_id = (int) $_SESSION['linked_id'];
$denied     = '';

$assignments = teacher_current_assignments($pdo, $teacher_id);

$filter_class   = (int) ($_GET['class_id'] ?? 0);
$filter_subject = (int) ($_GET['subject_id'] ?? 0);
$offering       = trim($_GET['offering'] ?? '');

if (preg_match('/^(\d+)_(\d+)$/', $offering, $parts)) {
    $filter_class   = (int) $parts[1];
    $filter_subject = (int) $parts[2];
}

if ($filter_class > 0 && $filter_subject > 0) {
    if (!teacher_is_assigned($pdo, $teacher_id, $filter_class, $filter_subject)) {
        $denied = 'You are not assigned to that class and subject.';
        $students = [];
    } else {
        $students = students_for_offering($pdo, $filter_class, $filter_subject);
    }
} else {
    $students = [];
    $seen = [];
    foreach ($assignments as $row) {
        foreach (students_for_offering($pdo, $row['class_id'], $row['subject_id']) as $stu) {
            $key = $stu['id'] . '_' . $row['subject_id'];
            if (!isset($seen[$key])) {
                $stu['subject_name'] = $row['subject_name'];
                $students[] = $stu;
                $seen[$key] = true;
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">My Students</h1>

<?php if ($denied !== ''): ?>
    <div class="alert alert-error"><?php echo h($denied); ?></div>
<?php endif; ?>

<div class="card">
    <form method="GET" action="">
        <div class="form-row">
            <div class="form-group">
                <label for="offering">Filter by class and subject</label>
                <select id="offering" name="offering" onchange="this.form.submit()">
                    <option value="">— All my subjects —</option>
                    <?php foreach ($assignments as $a):
                        $value = $a['class_id'] . '_' . $a['subject_id'];
                        $selected = ($filter_class === (int) $a['class_id'] && $filter_subject === (int) $a['subject_id']);
                    ?>
                        <option value="<?php echo h($value); ?>" <?php echo $selected ? 'selected' : ''; ?>>
                            <?php echo h($a['class_name'] . ' — ' . $a['subject_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="padding-top:28px;">
                <span style="color:#7f8c8d; font-size:13px;"><?php echo count($students); ?> student(s)</span>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-title">Students who take your subject</div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student ID</th>
                    <th>Full Name</th>
                    <th>Class</th>
                    <th>Subject</th>
                    <th>Guardian Phone</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($assignments)): ?>
                <tr>
                    <td colspan="6" style="text-align:center; color:#7f8c8d;">No subjects have been assigned to you yet.</td>
                </tr>
                <?php elseif (empty($students) && $denied === ''): ?>
                <tr>
                    <td colspan="6" style="text-align:center; color:#7f8c8d;">No students are enrolled in this subject.</td>
                </tr>
                <?php endif; ?>

                <?php foreach ($students as $i => $stu): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td><span style="font-family:monospace;"><?php echo h($stu['student_code']); ?></span></td>
                    <td><?php echo h($stu['full_name']); ?></td>
                    <td><?php echo h($stu['class_name']); ?></td>
                    <td><?php echo h($stu['subject_name']); ?></td>
                    <td><?php echo h($stu['guardian_phone'] ?? '—'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
