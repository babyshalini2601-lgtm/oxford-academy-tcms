<?php
require_once __DIR__ . '/enrollment.php';

// A teacher is allowed to see a student only when:
//   1. the teacher is assigned to that class + subject
//   2. the student chose that same subject
// Checking the class alone is not enough.

function teacher_current_assignments($pdo, $teacher_id) {
    $stmt = $pdo->prepare(
        "SELECT
            ta.class_id,
            ta.subject_id,
            c.class_name,
            c.grade_level,
            s.subject_name
         FROM teacher_assignments ta
         JOIN classes  c ON c.id = ta.class_id
         JOIN subjects s ON s.id = ta.subject_id
         WHERE ta.teacher_id = :teacher_id
           AND ta.ends_on IS NULL
         ORDER BY c.grade_level, c.class_name, s.subject_name"
    );
    $stmt->execute([':teacher_id' => $teacher_id]);

    return $stmt->fetchAll();
}

function teacher_is_assigned($pdo, $teacher_id, $class_id, $subject_id) {
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM teacher_assignments
         WHERE teacher_id = :teacher_id
           AND class_id = :class_id
           AND subject_id = :subject_id
           AND ends_on IS NULL"
    );
    $stmt->execute([
        ':teacher_id' => $teacher_id,
        ':class_id'   => $class_id,
        ':subject_id' => $subject_id,
    ]);

    return (bool) $stmt->fetchColumn();
}

function students_for_offering($pdo, $class_id, $subject_id) {
    $stmt = $pdo->prepare(
        "SELECT
            s.id,
            s.student_code,
            s.full_name,
            s.guardian_phone,
            c.class_name,
            c.grade_level,
            sub.subject_name
         FROM student_subjects ss
         JOIN students s ON s.id = ss.student_id
         JOIN classes  c ON c.id = ss.class_id
         JOIN subjects sub ON sub.id = ss.subject_id
         WHERE ss.class_id = :class_id
           AND ss.subject_id = :subject_id
           AND ss.ends_on IS NULL
           AND s.is_active = 1
         ORDER BY s.full_name"
    );
    $stmt->execute([
        ':class_id'   => $class_id,
        ':subject_id' => $subject_id,
    ]);

    return $stmt->fetchAll();
}

function apply_teacher_assignments($pdo, $teacher_id, $selected_pairs, $on_date) {
    $keep = [];
    foreach ($selected_pairs as $pair) {
        $class_id   = (int) ($pair['class_id'] ?? 0);
        $subject_id = (int) ($pair['subject_id'] ?? 0);
        if ($class_id <= 0 || $subject_id <= 0) {
            continue;
        }
        if (!class_offers_subject($pdo, $class_id, $subject_id)) {
            throw new Exception('invalid_offering');
        }
        $keep[$class_id . '_' . $subject_id] = [$class_id, $subject_id];
    }

    $open = $pdo->prepare(
        "SELECT id, class_id, subject_id
         FROM teacher_assignments
         WHERE teacher_id = :teacher_id AND ends_on IS NULL"
    );
    $open->execute([':teacher_id' => $teacher_id]);

    foreach ($open as $row) {
        $key = $row['class_id'] . '_' . $row['subject_id'];
        if (!isset($keep[$key])) {
            $pdo->prepare(
                "UPDATE teacher_assignments SET ends_on = :ends_on WHERE id = :id"
            )->execute([
                ':ends_on' => $on_date,
                ':id'      => $row['id'],
            ]);
        }
    }

    $find = $pdo->prepare(
        "SELECT id, ends_on FROM teacher_assignments
         WHERE teacher_id = :teacher_id
           AND class_id = :class_id
           AND subject_id = :subject_id"
    );
    $insert = $pdo->prepare(
        "INSERT INTO teacher_assignments (teacher_id, class_id, subject_id, starts_on)
         VALUES (:teacher_id, :class_id, :subject_id, :starts_on)"
    );
    $reopen = $pdo->prepare(
        "UPDATE teacher_assignments
         SET ends_on = NULL, starts_on = :starts_on
         WHERE id = :id"
    );

    foreach ($keep as $pair) {
        $find->execute([
            ':teacher_id' => $teacher_id,
            ':class_id'   => $pair[0],
            ':subject_id' => $pair[1],
        ]);
        $row = $find->fetch();

        if (!$row) {
            $insert->execute([
                ':teacher_id' => $teacher_id,
                ':class_id'   => $pair[0],
                ':subject_id' => $pair[1],
                ':starts_on'  => $on_date,
            ]);
        } elseif ($row['ends_on'] !== null) {
            $reopen->execute([
                ':starts_on' => $on_date,
                ':id'        => $row['id'],
            ]);
        }
    }
}
