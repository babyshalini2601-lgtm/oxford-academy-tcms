<?php
// ============================================================
// admin/classes/list_classes.php — Class listing page
//
// Shows all classes with the count of enrolled students and
// assigned subjects. Links to add a new class or edit existing ones.
// ============================================================

require_once __DIR__ . '/../../config/db.php';

$required_role = 'admin';
require_once __DIR__ . '/../../includes/auth_check.php';

$pdo        = get_db_connection();
$page_title = 'Manage Classes';

// Fetch all classes with their subject count and enrolled student count
$classes = $pdo->query(
    "SELECT
        c.id,
        c.class_name,
        c.grade_level,
        c.description,
        c.is_active,
        COUNT(DISTINCT cs.subject_id) AS subject_count,
        COUNT(DISTINCT sc.student_id) AS student_count
     FROM classes c
     LEFT JOIN class_subjects   cs ON cs.class_id = c.id
     LEFT JOIN student_classes  sc ON sc.class_id = c.id
     GROUP BY c.id
     ORDER BY c.is_active DESC, c.grade_level ASC, c.class_name ASC"
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<h1 class="page-title">Manage Classes</h1>

<div style="margin-bottom: 16px;">
    <a href="<?php echo BASE_URL; ?>/admin/classes/add_class.php" class="btn btn-primary">
        + Add New Class
    </a>
</div>

<div class="card">
    <div class="card-title">All Classes</div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Class Name</th>
                    <th>Grade Level</th>
                    <th>Subjects</th>
                    <th>Students Enrolled</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($classes)): ?>
                <tr>
                    <td colspan="7" style="text-align:center; color:#7f8c8d;">
                        No classes created yet.
                        <a href="<?php echo BASE_URL; ?>/admin/classes/add_class.php">Add the first class</a>.
                    </td>
                </tr>
                <?php endif; ?>

                <?php foreach ($classes as $i => $cls): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td><strong><?php echo htmlspecialchars($cls['class_name']); ?></strong></td>
                    <td>Grade <?php echo htmlspecialchars($cls['grade_level']); ?></td>
                    <td><?php echo $cls['subject_count']; ?> subject(s)</td>
                    <td><?php echo $cls['student_count']; ?> student(s)</td>
                    <td>
                        <?php if ($cls['is_active']): ?>
                            <span class="badge badge-active">Active</span>
                        <?php else: ?>
                            <span class="badge badge-inactive">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?php echo BASE_URL; ?>/admin/classes/edit_class.php?id=<?php echo $cls['id']; ?>"
                           class="btn btn-sm btn-warning">
                            Edit
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
