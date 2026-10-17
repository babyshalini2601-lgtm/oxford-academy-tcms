<?php
// Fictional development records only. Not real Oxford Academy students.
// Example used later in presentations:
//   Grade 10 offers Mathematics and Science
//   Demo student takes Mathematics only
//   Demo teacher is assigned to Grade 10 Mathematics
//
// Run: php setup/seed_dev_data.php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is for the command line only.\n");
}

require_once dirname(__DIR__) . '/config/db.php';

$pdo = get_db_connection();

$class_count = (int) $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
if ($class_count > 0) {
    echo "Development data already exists. Seed skipped.\n";
    exit(0);
}

$today = app_today();
$month_start = date('Y-m-01');

$pdo->beginTransaction();

try {
    $pdo->exec(
        "INSERT INTO grading_criteria (grade_label, min_percentage, max_percentage) VALUES
         ('A+', 90.00, 100.00),
         ('A',  75.00,  89.99),
         ('B',  65.00,  74.99),
         ('C',  55.00,  64.99),
         ('D',  40.00,  54.99),
         ('F',   0.00,  39.99)"
    );

    $pdo->prepare(
        "INSERT INTO classes (class_name, grade_level, description, is_active)
         VALUES ('Grade 10', '10', 'Fictional development class', 1)"
    )->execute();
    $class_id = (int) $pdo->lastInsertId();

    $subject_stmt = $pdo->prepare(
        "INSERT INTO subjects (subject_name, description, is_active)
         VALUES (:name, :description, 1)"
    );

    $subject_stmt->execute([
        ':name'        => 'Mathematics',
        ':description' => 'Fictional development subject',
    ]);
    $maths_id = (int) $pdo->lastInsertId();

    $subject_stmt->execute([
        ':name'        => 'Science',
        ':description' => 'Fictional development subject',
    ]);
    $science_id = (int) $pdo->lastInsertId();

    $offer_stmt = $pdo->prepare(
        "INSERT INTO class_subjects (class_id, subject_id)
         VALUES (:class_id, :subject_id)"
    );
    $offer_stmt->execute([':class_id' => $class_id, ':subject_id' => $maths_id]);
    $offer_stmt->execute([':class_id' => $class_id, ':subject_id' => $science_id]);

    $pdo->prepare(
        "INSERT INTO fee_structures (class_id, monthly_amount, effective_from)
         VALUES (:class_id, 2000.00, :from_date)"
    )->execute([
        ':class_id'  => $class_id,
        ':from_date' => date('Y-m-01'),
    ]);

    $pdo->prepare(
        "INSERT INTO teachers (full_name, phone, email, joined_date, is_active)
         VALUES ('N. Perera', '0770000001', 'teacher.demo@example.com', :joined, 1)"
    )->execute([':joined' => $today]);
    $teacher_id = (int) $pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO users (username, password_hash, role, linked_id, is_active)
         VALUES ('teacher.demo', :password_hash, 'teacher', :linked_id, 1)"
    )->execute([
        ':password_hash' => password_hash('Teacher@2026', PASSWORD_BCRYPT),
        ':linked_id'     => $teacher_id,
    ]);

    $pdo->prepare(
        "INSERT INTO teacher_assignments (teacher_id, class_id, subject_id, starts_on)
         VALUES (:teacher_id, :class_id, :subject_id, :starts_on)"
    )->execute([
        ':teacher_id' => $teacher_id,
        ':class_id'   => $class_id,
        ':subject_id' => $maths_id,
        ':starts_on'  => $month_start,
    ]);

    $pdo->prepare(
        "INSERT INTO students
            (student_code, full_name, date_of_birth, address, guardian_name, guardian_phone, enrolled_date, is_active)
         VALUES
            ('OXF-DEMO-001', 'S. Kumar', '2011-03-15', 'Nedunkerny', 'R. Kumar', '0770000002', :enrolled, 1)"
    )->execute([':enrolled' => $month_start]);
    $student_id = (int) $pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO users (username, password_hash, role, linked_id, is_active)
         VALUES ('OXF-DEMO-001', :password_hash, 'student', :linked_id, 1)"
    )->execute([
        ':password_hash' => password_hash('Student@2026', PASSWORD_BCRYPT),
        ':linked_id'     => $student_id,
    ]);

    $pdo->prepare(
        "INSERT INTO student_classes (student_id, class_id, enrolled_date)
         VALUES (:student_id, :class_id, :enrolled)"
    )->execute([
        ':student_id' => $student_id,
        ':class_id'   => $class_id,
        ':enrolled'   => $month_start,
    ]);

    // Mathematics only. Science is offered by the class but not chosen.
    $pdo->prepare(
        "INSERT INTO student_subjects (student_id, class_id, subject_id, starts_on)
         VALUES (:student_id, :class_id, :subject_id, :starts_on)"
    )->execute([
        ':student_id' => $student_id,
        ':class_id'   => $class_id,
        ':subject_id' => $maths_id,
        ':starts_on'  => $month_start,
    ]);

    // Contrast student: Science only. The Mathematics teacher must not see them.
    $pdo->prepare(
        "INSERT INTO students
            (student_code, full_name, date_of_birth, address, guardian_name, guardian_phone, enrolled_date, is_active)
         VALUES
            ('OXF-DEMO-002', 'M. Silva', '2011-07-22', 'Vavuniya', 'K. Silva', '0770000003', :enrolled, 1)"
    )->execute([':enrolled' => $month_start]);
    $science_student_id = (int) $pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO users (username, password_hash, role, linked_id, is_active)
         VALUES ('OXF-DEMO-002', :password_hash, 'student', :linked_id, 1)"
    )->execute([
        ':password_hash' => password_hash('Student@2026', PASSWORD_BCRYPT),
        ':linked_id'     => $science_student_id,
    ]);

    $pdo->prepare(
        "INSERT INTO student_classes (student_id, class_id, enrolled_date)
         VALUES (:student_id, :class_id, :enrolled)"
    )->execute([
        ':student_id' => $science_student_id,
        ':class_id'   => $class_id,
        ':enrolled'   => $month_start,
    ]);

    $pdo->prepare(
        "INSERT INTO student_subjects (student_id, class_id, subject_id, starts_on)
         VALUES (:student_id, :class_id, :subject_id, :starts_on)"
    )->execute([
        ':student_id' => $science_student_id,
        ':class_id'   => $class_id,
        ':subject_id' => $science_id,
        ':starts_on'  => $month_start,
    ]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Seed failed: " . $e->getMessage() . "\n");
    exit(1);
}

echo "Fictional development data created.\n";
echo "Class: Grade 10 with Mathematics and Science.\n";
echo "Student OXF-DEMO-001 takes Mathematics only.\n";
echo "Student OXF-DEMO-002 takes Science only.\n";
echo "Teacher teacher.demo is assigned to Grade 10 Mathematics.\n";
echo "Demo logins (development only):\n";
echo "  teacher.demo / Teacher@2026\n";
echo "  OXF-DEMO-001 / Student@2026\n";
echo "  OXF-DEMO-002 / Student@2026\n";
