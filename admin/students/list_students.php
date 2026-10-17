<?php
require_once __DIR__ . '/../../config/db.php';

$required_role = 'admin';
require_once __DIR__ . '/../../includes/auth_check.php';

$pdo        = get_db_connection();
$page_title = 'Manage Students';

$search       = trim($_GET['search'] ?? '');
$filter_class = (int) ($_GET['class_id'] ?? 0);

$sql = "SELECT
            s.id,
            s.student_code,
            s.full_name,
            s.guardian_phone,
            s.enrolled_date,
            s.is_active,
            GROUP_CONCAT(DISTINCT c.class_name ORDER BY c.class_name SEPARATOR ', ') AS class_names,
            GROUP_CONCAT(DISTINCT sub.subject_name ORDER BY sub.subject_name SEPARATOR ', ') AS subject_names
        FROM students s
        LEFT JOIN student_classes sc
            ON sc.student_id = s.id AND sc.ends_on IS NULL
        LEFT JOIN classes c
            ON c.id = sc.class_id
        LEFT JOIN student_subjects ss
            ON ss.student_id = s.id AND ss.ends_on IS NULL
        LEFT JOIN subjects sub
            ON sub.id = ss.subject_id";

$params = [];
$where  = [];

if ($search !== '') {
    $where[] = '(s.full_name LIKE :search_name OR s.student_code LIKE :search_code)';
    $params[':search_name'] = '%' . $search . '%';
    $params[':search_code'] = '%' . $search . '%';
}

if ($filter_class > 0) {
    $where[] = 'sc.class_id = :class_id';
    $params[':class_id'] = $filter_class;
}

if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' GROUP BY s.id ORDER BY s.is_active DESC, s.full_name ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

$classes = $pdo->query(
    "SELECT id, class_name, grade_level FROM classes WHERE is_active = 1 ORDER BY grade_level, class_name"
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<h1 class="page-title">Manage Students</h1>

<?php if (isset($_GET['added'])): ?>
    <div class="alert alert-success">Student registered. Student ID: <strong><?php echo h($_GET['added']); ?></strong></div>
<?php endif; ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
    <a href="<?php echo BASE_URL; ?>/admin/students/add_student.php" class="btn btn-primary">+ Register New Student</a>
    <span style="color:#7f8c8d; font-size:13px;"><?php echo count($students); ?> student(s) found</span>
</div>

<div class="card">
    <form method="GET" action="">
        <div class="form-row">
            <div class="form-group">
                <label for="search">Search by Name or Student ID</label>
                <input type="text" id="search" name="search" value="<?php echo h($search); ?>" placeholder="Type name or student code...">
            </div>
            <div class="form-group">
                <label for="class_id">Filter by Class</label>
                <select id="class_id" name="class_id">
                    <option value="0">— All Classes —</option>
                    <?php foreach ($classes as $cls): ?>
                        <option value="<?php echo (int) $cls['id']; ?>" <?php echo $filter_class === (int) $cls['id'] ? 'selected' : ''; ?>>
                            <?php echo h($cls['class_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div style="display:flex; gap:10px;">
            <button type="submit" class="btn btn-primary">Search</button>
            <a href="<?php echo BASE_URL; ?>/admin/students/list_students.php" class="btn btn-secondary">Clear</a>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-title">Student List</div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student ID</th>
                    <th>Full Name</th>
                    <th>Class</th>
                    <th>Subjects</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                <tr>
                    <td colspan="7" style="text-align:center; color:#7f8c8d;">
                        No students found.
                        <?php if ($search === '' && $filter_class === 0): ?>
                            <a href="<?php echo BASE_URL; ?>/admin/students/add_student.php">Register the first student</a>.
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>

                <?php foreach ($students as $i => $stu): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td><strong style="font-family:monospace;"><?php echo h($stu['student_code']); ?></strong></td>
                    <td><?php echo h($stu['full_name']); ?></td>
                    <td><?php echo h($stu['class_names'] ?: '—'); ?></td>
                    <td><?php echo h($stu['subject_names'] ?: '—'); ?></td>
                    <td>
                        <?php if ($stu['is_active']): ?>
                            <span class="badge badge-active">Active</span>
                        <?php else: ?>
                            <span class="badge badge-inactive">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;">
                        <a href="<?php echo BASE_URL; ?>/admin/students/view_student.php?id=<?php echo (int) $stu['id']; ?>" class="btn btn-sm btn-secondary">View</a>
                        <a href="<?php echo BASE_URL; ?>/admin/students/edit_student.php?id=<?php echo (int) $stu['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
