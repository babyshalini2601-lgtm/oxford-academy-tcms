-- Oxford Academy TCMS schema
-- Import this file into an empty oxford_academy database.
-- Demo users are created by setup/seed_dev_data.php, not here.

CREATE TABLE IF NOT EXISTS users (
    id            INT            AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)    NOT NULL UNIQUE,
    password_hash VARCHAR(255)   NOT NULL,
    role          ENUM('admin', 'teacher', 'student') NOT NULL,
    linked_id     INT            DEFAULT NULL,
    is_active     TINYINT(1)     NOT NULL DEFAULT 1,
    created_at    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- linked_id is empty for admin.
-- For a teacher it stores teachers.id. For a student it stores students.id.

CREATE TABLE IF NOT EXISTS classes (
    id          INT           AUTO_INCREMENT PRIMARY KEY,
    class_name  VARCHAR(100)  NOT NULL,
    grade_level VARCHAR(20)   NOT NULL,
    description VARCHAR(255)  DEFAULT NULL,
    is_active   TINYINT(1)    NOT NULL DEFAULT 1,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS subjects (
    id           INT           AUTO_INCREMENT PRIMARY KEY,
    subject_name VARCHAR(100)  NOT NULL,
    description  VARCHAR(255)  DEFAULT NULL,
    is_active    TINYINT(1)    NOT NULL DEFAULT 1,
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS class_subjects (
    id         INT  AUTO_INCREMENT PRIMARY KEY,
    class_id   INT  NOT NULL,
    subject_id INT  NOT NULL,
    UNIQUE KEY uq_class_subject (class_id, subject_id),
    FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE RESTRICT,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS students (
    id             INT           AUTO_INCREMENT PRIMARY KEY,
    student_code   VARCHAR(20)   NOT NULL UNIQUE,
    full_name      VARCHAR(150)  NOT NULL,
    date_of_birth  DATE          DEFAULT NULL,
    address        TEXT          DEFAULT NULL,
    guardian_name  VARCHAR(150)  DEFAULT NULL,
    guardian_phone VARCHAR(20)   DEFAULT NULL,
    guardian_email VARCHAR(100)  DEFAULT NULL,
    enrolled_date  DATE          NOT NULL,
    is_active      TINYINT(1)    NOT NULL DEFAULT 1,
    created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS student_classes (
    id            INT   AUTO_INCREMENT PRIMARY KEY,
    student_id    INT   NOT NULL,
    class_id      INT   NOT NULL,
    enrolled_date DATE  NOT NULL,
    ends_on       DATE  DEFAULT NULL,
    UNIQUE KEY uq_student_class (student_id, class_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
    FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE RESTRICT
);

-- A student can take some subjects in a class and skip others.
CREATE TABLE IF NOT EXISTS student_subjects (
    id         INT  AUTO_INCREMENT PRIMARY KEY,
    student_id INT  NOT NULL,
    class_id   INT  NOT NULL,
    subject_id INT  NOT NULL,
    starts_on  DATE NOT NULL,
    ends_on    DATE DEFAULT NULL,
    UNIQUE KEY uq_student_subject (student_id, class_id, subject_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
    FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE RESTRICT,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS teachers (
    id          INT           AUTO_INCREMENT PRIMARY KEY,
    full_name   VARCHAR(150)  NOT NULL,
    phone       VARCHAR(20)   DEFAULT NULL,
    email       VARCHAR(100)  DEFAULT NULL,
    address     TEXT          DEFAULT NULL,
    joined_date DATE          NOT NULL,
    is_active   TINYINT(1)    NOT NULL DEFAULT 1,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS teacher_assignments (
    id         INT  AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT  NOT NULL,
    class_id   INT  NOT NULL,
    subject_id INT  NOT NULL,
    starts_on  DATE DEFAULT NULL,
    ends_on    DATE DEFAULT NULL,
    UNIQUE KEY uq_teacher_assignment (teacher_id, class_id, subject_id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE RESTRICT,
    FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE RESTRICT,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS attendance (
    id                INT      AUTO_INCREMENT PRIMARY KEY,
    student_id        INT      NOT NULL,
    class_id          INT      NOT NULL,
    subject_id        INT      NOT NULL,
    teacher_id        INT      NOT NULL,
    attendance_date   DATE     NOT NULL,
    status            ENUM('present', 'absent') NOT NULL,
    marked_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    corrected_by      INT      DEFAULT NULL,
    corrected_at      DATETIME DEFAULT NULL,
    correction_reason VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY uq_attendance (student_id, class_id, subject_id, attendance_date),
    FOREIGN KEY (student_id)   REFERENCES students(id)  ON DELETE RESTRICT,
    FOREIGN KEY (class_id)     REFERENCES classes(id)   ON DELETE RESTRICT,
    FOREIGN KEY (subject_id)   REFERENCES subjects(id)  ON DELETE RESTRICT,
    FOREIGN KEY (teacher_id)   REFERENCES teachers(id)  ON DELETE RESTRICT,
    FOREIGN KEY (corrected_by) REFERENCES users(id)     ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS fee_structures (
    id             INT            AUTO_INCREMENT PRIMARY KEY,
    class_id       INT            NOT NULL,
    monthly_amount DECIMAL(10,2)  NOT NULL,
    effective_from DATE           NOT NULL,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS fee_payments (
    id             INT            AUTO_INCREMENT PRIMARY KEY,
    student_id     INT            NOT NULL,
    class_id       INT            NOT NULL,
    amount_paid    DECIMAL(10,2)  NOT NULL,
    payment_date   DATE           NOT NULL,
    receipt_number VARCHAR(30)    NOT NULL UNIQUE,
    payment_month  VARCHAR(7)     NOT NULL,
    recorded_by    INT            NOT NULL,
    notes          TEXT           DEFAULT NULL,
    created_at     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id)  REFERENCES students(id) ON DELETE RESTRICT,
    FOREIGN KEY (class_id)    REFERENCES classes(id)  ON DELETE RESTRICT,
    FOREIGN KEY (recorded_by) REFERENCES users(id)    ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS grading_criteria (
    id             INT            AUTO_INCREMENT PRIMARY KEY,
    grade_label    VARCHAR(5)     NOT NULL,
    min_percentage DECIMAL(5,2)   NOT NULL,
    max_percentage DECIMAL(5,2)   NOT NULL
);

CREATE TABLE IF NOT EXISTS examinations (
    id          INT           AUTO_INCREMENT PRIMARY KEY,
    exam_name   VARCHAR(150)  NOT NULL,
    class_id    INT           NOT NULL,
    subject_id  INT           NOT NULL,
    exam_date   DATE          NOT NULL,
    total_marks INT           NOT NULL,
    created_by  INT           NOT NULL,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE RESTRICT,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS results (
    id             INT            AUTO_INCREMENT PRIMARY KEY,
    examination_id INT            NOT NULL,
    student_id     INT            NOT NULL,
    marks_obtained DECIMAL(6,2)   NOT NULL,
    grade          VARCHAR(5)     DEFAULT NULL,
    entered_by     INT            NOT NULL,
    entered_at     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_result (examination_id, student_id),
    FOREIGN KEY (examination_id) REFERENCES examinations(id) ON DELETE RESTRICT,
    FOREIGN KEY (student_id)     REFERENCES students(id)     ON DELETE RESTRICT,
    FOREIGN KEY (entered_by)     REFERENCES users(id)        ON DELETE RESTRICT
);
