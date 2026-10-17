# Oxford Academy TCMS

Tuition Centre Management System for Oxford Academy, Nedunkerny, Vavuniya.

The application uses PHP, PDO, MySQL, HTML, CSS, and JavaScript. There is no framework. Each page reads the form, checks the user’s role, validates the values, then saves with PDO.

**Current status:** Login, students, subject enrollment, teachers, assignments, and attendance work. Fees, examinations, and full progress reports are not included yet.

## What works now

| Role | Can do |
| --- | --- |
| Administrator | Create classes, subjects, students, and teachers. Enrol a student in selected subjects. Assign a teacher to a class and subject. View and correct attendance. |
| Teacher | See only students in assigned subjects. Mark attendance once per date. |
| Student | View own attendance. Change own password. |

A student who takes Mathematics is not listed in Science. A Mathematics teacher cannot open a Science list by changing the URL.

## Folder layout

```text
oxford-academy-tcms/
  login.php
  admin/
  teacher/
  student/
  includes/
  config/
  database/oxford_academy.sql
  setup/create_admin.php
  setup/seed_dev_data.php
  assets/
```

Keep the folder name `oxford-academy-tcms` so the address stays `/oxford-academy-tcms`.

## Setup on Windows (XAMPP)

1. Start Apache and MySQL in the XAMPP Control Panel.
2. Place this folder at `C:\xampp\htdocs\oxford-academy-tcms`.
3. Copy `config/local.php.example` to `config/local.php`.
4. Edit `config/local.php`. XAMPP usually uses:

```php
define('DB_USER', 'root');
define('DB_PASS', '');
```

5. Open http://localhost/phpmyadmin
6. Create a database named `oxford_academy`.
7. Open that database → Import → choose `database/oxford_academy.sql` → Go.
8. In Command Prompt:

```bat
cd C:\xampp\htdocs\oxford-academy-tcms
C:\xampp\php\php.exe setup\create_admin.php admin "Admin@2026"
C:\xampp\php\php.exe setup\seed_dev_data.php
```

9. Open http://localhost/oxford-academy-tcms/

`setup/create_admin.php` creates the first administrator. `setup/seed_dev_data.php` adds the sample class, teacher, and students. `config/local.php` stays on this computer and is not stored in Git.

## Demo accounts

These accounts are created by `seed_dev_data.php`. They are fictional.

| Role | Username | Password |
| --- | --- | --- |
| Administrator | admin | Admin@2026 |
| Teacher | teacher.demo | Teacher@2026 |
| Student (Mathematics) | OXF-DEMO-001 | Student@2026 |
| Student (Science) | OXF-DEMO-002 | Student@2026 |

Grade 10 offers Mathematics and Science. The teacher is assigned to Mathematics only. OXF-DEMO-001 takes Mathematics. OXF-DEMO-002 takes Science.
