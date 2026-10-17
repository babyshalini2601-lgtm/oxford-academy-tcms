<?php
require_once __DIR__ . '/teacher_access.php';

// Attendance is one present/absent record per student, class, subject, and date.
// Teachers submit that list once. Administrators correct a row with a reason.

function attendance_percentage($present_count, $recorded_count) {
    $recorded = (int) $recorded_count;
    if ($recorded <= 0) {
        return null;
    }

    return round(((int) $present_count / $recorded) * 100, 1);
}

function format_attendance_percentage($present_count, $recorded_count) {
    $percent = attendance_percentage($present_count, $recorded_count);
    if ($percent === null) {
        return 'No attendance recorded';
    }

    return $percent . '%';
}

function summarise_attendance($records) {
    $present = 0;
    $absent  = 0;

    foreach ($records as $row) {
        if (($row['status'] ?? '') === 'present') {
            $present++;
        } elseif (($row['status'] ?? '') === 'absent') {
            $absent++;
        }
    }

    $recorded = $present + $absent;

    return [
        'present'  => $present,
        'absent'   => $absent,
        'recorded' => $recorded,
        'percent'  => attendance_percentage($present, $recorded),
        'label'    => format_attendance_percentage($present, $recorded),
    ];
}

function teacher_can_mark_offering($pdo, $teacher_id, $class_id, $subject_id, $on_date) {
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM teacher_assignments
         WHERE teacher_id = :teacher_id
           AND class_id = :class_id
           AND subject_id = :subject_id
           AND starts_on <= :on_date
           AND ends_on IS NULL"
    );
    $stmt->execute([
        ':teacher_id' => $teacher_id,
        ':class_id'   => $class_id,
        ':subject_id' => $subject_id,
        ':on_date'    => $on_date,
    ]);

    return (bool) $stmt->fetchColumn();
}

function students_eligible_on_date($pdo, $class_id, $subject_id, $on_date, $active_only = true) {
    $sql = "SELECT
                s.id,
                s.student_code,
                s.full_name
            FROM student_subjects ss
            JOIN students s ON s.id = ss.student_id
            WHERE ss.class_id = :class_id
              AND ss.subject_id = :subject_id
              AND ss.starts_on <= :on_date_start
              AND (ss.ends_on IS NULL OR ss.ends_on >= :on_date_end)";

    if ($active_only) {
        $sql .= " AND s.is_active = 1";
    }

    $sql .= " ORDER BY s.full_name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':class_id'      => $class_id,
        ':subject_id'    => $subject_id,
        ':on_date_start' => $on_date,
        ':on_date_end'   => $on_date,
    ]);

    return $stmt->fetchAll();
}

function attendance_has_correction_columns($pdo) {
    static $has_columns = null;

    if ($has_columns !== null) {
        return $has_columns;
    }

    $check = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = :db
           AND TABLE_NAME = 'attendance'
           AND COLUMN_NAME = 'correction_reason'"
    );
    $check->execute([':db' => DB_NAME]);
    $has_columns = (int) $check->fetchColumn() > 0;

    return $has_columns;
}

function attendance_rows_for_date($pdo, $class_id, $subject_id, $on_date) {
    $correction_sql = attendance_has_correction_columns($pdo)
        ? ", a.correction_reason, a.corrected_at, u.username AS corrected_by_username"
        : ", NULL AS correction_reason, NULL AS corrected_at, NULL AS corrected_by_username";
    $correction_join = attendance_has_correction_columns($pdo)
        ? "LEFT JOIN users u ON u.id = a.corrected_by"
        : "";

    $stmt = $pdo->prepare(
        "SELECT
            a.id,
            a.student_id,
            a.status,
            a.marked_at,
            t.full_name AS marked_by
            {$correction_sql}
         FROM attendance a
         JOIN teachers t ON t.id = a.teacher_id
         {$correction_join}
         WHERE a.class_id = :class_id
           AND a.subject_id = :subject_id
           AND a.attendance_date = :on_date"
    );
    $stmt->execute([
        ':class_id'   => $class_id,
        ':subject_id' => $subject_id,
        ':on_date'    => $on_date,
    ]);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[(int) $row['student_id']] = $row;
    }

    return $rows;
}

function offering_is_marked($pdo, $class_id, $subject_id, $on_date) {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM attendance
         WHERE class_id = :class_id
           AND subject_id = :subject_id
           AND attendance_date = :on_date"
    );
    $stmt->execute([
        ':class_id'   => $class_id,
        ':subject_id' => $subject_id,
        ':on_date'    => $on_date,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function save_teacher_attendance($pdo, $teacher_id, $class_id, $subject_id, $on_date, $statuses) {
    if (!is_valid_date($on_date)) {
        throw new Exception('invalid_date');
    }
    if ($on_date > app_today()) {
        throw new Exception('future_date');
    }
    if (!teacher_can_mark_offering($pdo, $teacher_id, $class_id, $subject_id, $on_date)) {
        throw new Exception('not_assigned');
    }

    $eligible = students_eligible_on_date($pdo, $class_id, $subject_id, $on_date, true);
    $needed   = [];
    foreach ($eligible as $student) {
        $needed[(int) $student['id']] = true;
    }

    if (empty($needed)) {
        throw new Exception('no_students');
    }

    $clean = [];
    foreach ($statuses as $student_id => $status) {
        $student_id = (int) $student_id;
        if (!isset($needed[$student_id])) {
            throw new Exception('forged_student');
        }
        if ($status !== 'present' && $status !== 'absent') {
            throw new Exception('invalid_status');
        }
        $clean[$student_id] = $status;
    }

    if (count($clean) !== count($needed)) {
        throw new Exception('incomplete');
    }

    $pdo->beginTransaction();

    try {
        $check = $pdo->prepare(
            "SELECT COUNT(*)
             FROM attendance
             WHERE class_id = :class_id
               AND subject_id = :subject_id
               AND attendance_date = :on_date
             FOR UPDATE"
        );
        $check->execute([
            ':class_id'   => $class_id,
            ':subject_id' => $subject_id,
            ':on_date'    => $on_date,
        ]);

        if ((int) $check->fetchColumn() > 0) {
            throw new Exception('already_submitted');
        }

        $insert = $pdo->prepare(
            "INSERT INTO attendance
                (student_id, class_id, subject_id, teacher_id, attendance_date, status)
             VALUES
                (:student_id, :class_id, :subject_id, :teacher_id, :on_date, :status)"
        );

        foreach ($clean as $student_id => $status) {
            $insert->execute([
                ':student_id' => $student_id,
                ':class_id'   => $class_id,
                ':subject_id' => $subject_id,
                ':teacher_id' => $teacher_id,
                ':on_date'    => $on_date,
                ':status'     => $status,
            ]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function correct_attendance($pdo, $attendance_id, $new_status, $reason, $admin_user_id) {
    if ($new_status !== 'present' && $new_status !== 'absent') {
        throw new Exception('invalid_status');
    }

    $reason = trim($reason);
    if (strlen($reason) < 3) {
        throw new Exception('reason_required');
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("SELECT * FROM attendance WHERE id = :id FOR UPDATE");
        $stmt->execute([':id' => $attendance_id]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new Exception('not_found');
        }

        if (attendance_has_correction_columns($pdo)) {
            $pdo->prepare(
                "UPDATE attendance
                 SET status = :status,
                     corrected_by = :corrected_by,
                     corrected_at = NOW(),
                     correction_reason = :reason
                 WHERE id = :id"
            )->execute([
                ':status'       => $new_status,
                ':corrected_by' => $admin_user_id,
                ':reason'       => $reason,
                ':id'           => $attendance_id,
            ]);
        } else {
            $pdo->prepare(
                "UPDATE attendance SET status = :status WHERE id = :id"
            )->execute([
                ':status' => $new_status,
                ':id'     => $attendance_id,
            ]);
        }

        $pdo->commit();
        return $row;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function attendance_message($code) {
    $messages = [
        'invalid_date'      => 'Please choose a real date.',
        'future_date'       => 'Attendance cannot be marked for a future date.',
        'not_assigned'      => 'You are not assigned to that class and subject on this date.',
        'no_students'       => 'No students were enrolled in this subject on that date.',
        'forged_student'    => 'One of the students is not enrolled in this subject on that date.',
        'invalid_status'    => 'Each student must be marked present or absent.',
        'incomplete'        => 'Choose Present or Absent for every student before saving.',
        'already_submitted' => 'This list has already been submitted. Ask the administrator to correct it.',
        'reason_required'   => 'Write a short reason for the correction.',
        'not_found'         => 'That attendance record was not found.',
    ];

    return $messages[$code] ?? 'The attendance was not saved. Previous records were left unchanged.';
}

function render_attendance_admin_nav($current) {
    $links = [
        'class'   => [BASE_URL . '/admin/attendance/view_by_class.php', 'By class and date'],
        'student' => [BASE_URL . '/admin/attendance/view_by_student.php', 'By student'],
        'month'   => [BASE_URL . '/admin/attendance/monthly_report.php', 'Monthly report'],
    ];

    echo '<div class="card no-print" style="padding:12px 16px;">';
    $parts = [];
    foreach ($links as $key => $item) {
        if ($key === $current) {
            $parts[] = '<strong>' . h($item[1]) . '</strong>';
        } else {
            $parts[] = '<a href="' . h($item[0]) . '">' . h($item[1]) . '</a>';
        }
    }
    echo implode(' &nbsp;|&nbsp; ', $parts);
    echo '</div>';
}

function attendance_status_badge($status) {
    if ($status === 'present') {
        return '<span class="badge badge-present">Present</span>';
    }
    if ($status === 'absent') {
        return '<span class="badge badge-absent">Absent</span>';
    }

    return '<span style="color:#7f8c8d; font-size:12px;">Not marked</span>';
}
