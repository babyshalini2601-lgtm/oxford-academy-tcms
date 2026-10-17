<?php
// Enrollment helpers used by student add/edit pages.
// student_classes = the class used for fees
// student_subjects = the subjects the student actually takes

function generate_student_code($pdo) {
    $year   = date('Y');
    $prefix = 'OXF' . $year;

    $stmt = $pdo->prepare(
        "SELECT student_code FROM students
         WHERE student_code LIKE :prefix
         ORDER BY student_code DESC
         LIMIT 1"
    );
    $stmt->execute([':prefix' => $prefix . '%']);
    $last_code = $stmt->fetchColumn();

    if ($last_code) {
        $last_number = (int) substr($last_code, strlen($prefix));
        $next_number = $last_number + 1;
    } else {
        $next_number = 1;
    }

    return $prefix . str_pad($next_number, 3, '0', STR_PAD_LEFT);
}

function get_active_classes($pdo) {
    return $pdo->query(
        "SELECT id, class_name, grade_level
         FROM classes
         WHERE is_active = 1
         ORDER BY grade_level, class_name"
    )->fetchAll();
}

function get_offerings_by_class($pdo) {
    $rows = $pdo->query(
        "SELECT cs.class_id, cs.subject_id, s.subject_name
         FROM class_subjects cs
         JOIN subjects s ON s.id = cs.subject_id
         WHERE s.is_active = 1
         ORDER BY s.subject_name"
    )->fetchAll();

    $grouped = [];
    foreach ($rows as $row) {
        $grouped[(int) $row['class_id']][] = [
            'subject_id'   => (int) $row['subject_id'],
            'subject_name' => $row['subject_name'],
        ];
    }

    return $grouped;
}

function class_offers_subject($pdo, $class_id, $subject_id) {
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM class_subjects cs
         JOIN subjects s ON s.id = cs.subject_id
         WHERE cs.class_id = :class_id
           AND cs.subject_id = :subject_id
           AND s.is_active = 1"
    );
    $stmt->execute([
        ':class_id'   => $class_id,
        ':subject_id' => $subject_id,
    ]);

    return (bool) $stmt->fetchColumn();
}

function current_class_id($pdo, $student_id) {
    $stmt = $pdo->prepare(
        "SELECT class_id
         FROM student_classes
         WHERE student_id = :student_id
           AND ends_on IS NULL
         ORDER BY enrolled_date DESC
         LIMIT 1"
    );
    $stmt->execute([':student_id' => $student_id]);
    $value = $stmt->fetchColumn();

    return $value ? (int) $value : 0;
}

function current_subject_ids($pdo, $student_id) {
    $stmt = $pdo->prepare(
        "SELECT subject_id
         FROM student_subjects
         WHERE student_id = :student_id
           AND ends_on IS NULL"
    );
    $stmt->execute([':student_id' => $student_id]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function apply_enrollment($pdo, $student_id, $class_id, $subject_ids, $on_date) {
    $end_other = $pdo->prepare(
        "UPDATE student_classes
         SET ends_on = :ends_on
         WHERE student_id = :student_id
           AND class_id <> :class_id
           AND ends_on IS NULL"
    );
    $end_other->execute([
        ':ends_on'    => $on_date,
        ':student_id' => $student_id,
        ':class_id'   => $class_id,
    ]);

    $find_class = $pdo->prepare(
        "SELECT id FROM student_classes
         WHERE student_id = :student_id AND class_id = :class_id"
    );
    $find_class->execute([
        ':student_id' => $student_id,
        ':class_id'   => $class_id,
    ]);
    $class_row_id = $find_class->fetchColumn();

    if ($class_row_id) {
        $pdo->prepare(
            "UPDATE student_classes
             SET ends_on = NULL, enrolled_date = :enrolled_date
             WHERE id = :id"
        )->execute([
            ':enrolled_date' => $on_date,
            ':id'            => $class_row_id,
        ]);
    } else {
        $pdo->prepare(
            "INSERT INTO student_classes (student_id, class_id, enrolled_date)
             VALUES (:student_id, :class_id, :enrolled_date)"
        )->execute([
            ':student_id'    => $student_id,
            ':class_id'      => $class_id,
            ':enrolled_date' => $on_date,
        ]);
    }

    $keep_ids = array_map('intval', $subject_ids);
    $open_subjects = $pdo->prepare(
        "SELECT id, class_id, subject_id
         FROM student_subjects
         WHERE student_id = :student_id AND ends_on IS NULL"
    );
    $open_subjects->execute([':student_id' => $student_id]);

    foreach ($open_subjects as $row) {
        $still_wanted = ((int) $row['class_id'] === (int) $class_id)
            && in_array((int) $row['subject_id'], $keep_ids, true);
        if (!$still_wanted) {
            $pdo->prepare(
                "UPDATE student_subjects SET ends_on = :ends_on WHERE id = :id"
            )->execute([
                ':ends_on' => $on_date,
                ':id'      => $row['id'],
            ]);
        }
    }

    $find_subject = $pdo->prepare(
        "SELECT id FROM student_subjects
         WHERE student_id = :student_id
           AND class_id = :class_id
           AND subject_id = :subject_id"
    );
    $insert_subject = $pdo->prepare(
        "INSERT INTO student_subjects (student_id, class_id, subject_id, starts_on)
         VALUES (:student_id, :class_id, :subject_id, :starts_on)"
    );
    $reopen_subject = $pdo->prepare(
        "UPDATE student_subjects
         SET ends_on = NULL, starts_on = :starts_on
         WHERE id = :id"
    );

    foreach ($keep_ids as $subject_id) {
        $find_subject->execute([
            ':student_id' => $student_id,
            ':class_id'   => $class_id,
            ':subject_id' => $subject_id,
        ]);
        $subject_row_id = $find_subject->fetchColumn();

        if ($subject_row_id) {
            $reopen_subject->execute([
                ':starts_on' => $on_date,
                ':id'        => $subject_row_id,
            ]);
        } else {
            $insert_subject->execute([
                ':student_id' => $student_id,
                ':class_id'   => $class_id,
                ':subject_id' => $subject_id,
                ':starts_on'  => $on_date,
            ]);
        }
    }
}
