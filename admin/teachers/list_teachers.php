<?php
// ============================================================
// admin/teachers/list_teachers.php — Teacher listing page
//
// Shows all teachers with their assignment count and links to
// edit, assign classes, or view their student list.
// ============================================================

require_once __DIR__ . '/../../config/db.php';

$required_role = 'admin';
require_once __DIR__ . '/../../includes/auth_check.php';

$pdo        = get_db_connection();
$page_title = 'Manage Teachers';

// Show a success message if we just added a new teacher
$success_msg = '';
if (isset($_GET['added'])) {
    $success_msg = 'Teacher "' . htmlspecialchars($_GET['added']) . '" registered successfully.';
}
if (isset($_GET['updated'])) {
    $success_msg = 'Teacher profile updated successfully.';
}

// Fetch all teachers with the count of their class-subject assignments
$teachers = $pdo->query(
    "SELECT
        t.id,
        t.full_name,
        t.phone,
        t.email,
        t.joined_date,
        t.is_active,
        COUNT(ta.id) AS assignment_count
     FROM teachers t
     LEFT JOIN teacher_assignments ta ON ta.teacher_id = t.id AND ta.ends_on IS NULL
     GROUP BY t.id
     ORDER BY t.is_active DESC, t.full_name ASC"
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<h1 class="page-title">Manage Teachers</h1>

<?php if (!empty($success_msg)): ?>
    <div class="alert alert-success"><?php echo $success_msg; ?></div>
<?php endif; ?>

<div style="margin-bottom: 16px;">
    <a href="<?php echo BASE_URL; ?>/admin/teachers/add_teacher.php" class="btn btn-primary">
        + Add New Teacher
    </a>
</div>

<div class="card">
    <div class="card-title">All Teachers</div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Full Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Joined</th>
                    <th>Assignments</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($teachers)): ?>
                <tr>
                    <td colspan="8" style="text-align:center; color:#7f8c8d;">
                        No teachers added yet.
                        <a href="<?php echo BASE_URL; ?>/admin/teachers/add_teacher.php">Add the first teacher</a>.
                    </td>
                </tr>
                <?php endif; ?>

                <?php foreach ($teachers as $i => $teacher): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td><strong><?php echo htmlspecialchars($teacher['full_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($teacher['phone'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($teacher['email'] ?? '—'); ?></td>
                    <td><?php echo date('d M Y', strtotime($teacher['joined_date'])); ?></td>
                    <td>
                        <?php echo (int) $teacher['assignment_count']; ?> subject(s)
                    </td>
                    <td>
                        <?php if ($teacher['is_active']): ?>
                            <span class="badge badge-active">Active</span>
                        <?php else: ?>
                            <span class="badge badge-inactive">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;">
                        <a href="<?php echo BASE_URL; ?>/admin/teachers/edit_teacher.php?id=<?php echo $teacher['id']; ?>"
                           class="btn btn-sm btn-warning">Edit</a>
                        <a href="<?php echo BASE_URL; ?>/admin/teachers/assign_classes.php?teacher_id=<?php echo $teacher['id']; ?>"
                           class="btn btn-sm btn-primary">Assign Classes</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
