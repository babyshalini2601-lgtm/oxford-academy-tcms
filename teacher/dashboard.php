<?php
require_once __DIR__ . '/../config/db.php';

$required_role = 'teacher';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/teacher_access.php';

$pdo        = get_db_connection();
$page_title = 'Teacher Dashboard';
$teacher_id = (int) $_SESSION['linked_id'];

$assignments = teacher_current_assignments($pdo, $teacher_id);

$count_stmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT ss.student_id)
     FROM teacher_assignments ta
     JOIN student_subjects ss
         ON ss.class_id = ta.class_id
        AND ss.subject_id = ta.subject_id
        AND ss.ends_on IS NULL
     JOIN students s ON s.id = ss.student_id AND s.is_active = 1
     WHERE ta.teacher_id = :teacher_id
       AND ta.ends_on IS NULL"
);
$count_stmt->execute([':teacher_id' => $teacher_id]);
$total_students = (int) $count_stmt->fetchColumn();

// Count exams scheduled for this teacher's assigned classes & subjects
$exam_count_stmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM examinations e
     JOIN teacher_assignments ta
         ON ta.class_id = e.class_id
        AND ta.subject_id = e.subject_id
        AND ta.teacher_id = :teacher_id
        AND ta.ends_on IS NULL"
);
$exam_count_stmt->execute([':teacher_id' => $teacher_id]);
$total_exams = (int) $exam_count_stmt->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">Welcome, <?php echo h($_SESSION['full_name']); ?></h1>

<div class="summary-grid">
    <div class="summary-box blue">
        <div class="summary-number"><?php echo count($assignments); ?></div>
        <div class="summary-label">Assigned subjects</div>
    </div>
    <div class="summary-box green">
        <div class="summary-number"><?php echo $total_students; ?></div>
        <div class="summary-label">Students in those subjects</div>
    </div>
    <div class="summary-box purple">
        <div class="summary-number"><?php echo $total_exams; ?></div>
        <div class="summary-label">Examinations</div>
    </div>
</div>

<div class="card">
    <div class="card-title">Quick Actions</div>
    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
        <a href="<?php echo BASE_URL; ?>/teacher/my_students.php" class="btn btn-primary">View My Students</a>
        <a href="<?php echo BASE_URL; ?>/teacher/attendance/mark_attendance.php" class="btn btn-secondary">Mark Attendance</a>
        <a href="<?php echo BASE_URL; ?>/teacher/results/enter_marks.php" class="btn btn-secondary">Enter Exam Marks</a>
    </div>
</div>

<div class="card">
    <div class="card-title">My assigned class and subject</div>
    <?php if (empty($assignments)): ?>
        <p style="color:#7f8c8d;">No subjects have been assigned to you yet. Ask the administrator.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Class</th>
                        <th>Subject</th>
                        <th>Students</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assignments as $index => $row):
                        $offering_students = students_for_offering($pdo, $row['class_id'], $row['subject_id']);
                    ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><?php echo h($row['class_name']); ?></td>
                        <td><?php echo h($row['subject_name']); ?></td>
                        <td>
                            <?php echo count($offering_students); ?>
                            <a href="<?php echo BASE_URL; ?>/teacher/my_students.php?class_id=<?php echo (int) $row['class_id']; ?>&subject_id=<?php echo (int) $row['subject_id']; ?>">
                                view
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
