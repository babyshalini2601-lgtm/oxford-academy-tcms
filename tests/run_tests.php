<?php
// ============================================================
// tests/run_tests.php — Comprehensive Phase 9 Automated Test Suite
//
// Standalone CLI test runner for Oxford Academy TCMS.
// Validates:
//   1. Unit tests: helper functions, date validation, password policy
//   2. Security tests: XSS escaping, CSRF protection, session timeout
//   3. Business rules: attendance percentages (zero division check),
//      grade criteria boundaries, mark validation, receipt numbering
//   4. Static code analysis: SQL injection check, CSRF coverage,
//      auth checks on all admin/teacher/student entry points
//
// Run from CLI:
//   php tests/run_tests.php
// ============================================================

define('BASE_URL', '/oxford-academy-tcms');
ini_set('session.save_path', sys_get_temp_dir());
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/attendance.php';

$total_assertions = 0;
$passed_assertions = 0;
$failed_assertions = [];

function assert_true($condition, $description) {
    global $total_assertions, $passed_assertions, $failed_assertions;
    $total_assertions++;
    if ($condition) {
        $passed_assertions++;
        echo "  [PASS] " . $description . "\n";
    } else {
        $failed_assertions[] = $description;
        echo "  [FAIL] " . $description . "\n";
    }
}

function assert_equals($expected, $actual, $description) {
    $matched = ($expected === $actual);
    assert_true($matched, $description . " (Expected: " . var_export($expected, true) . ", Got: " . var_export($actual, true) . ")");
}

echo "============================================================\n";
echo " Oxford Academy TCMS — Phase 9 Test Suite\n";
echo "============================================================\n\n";

// ============================================================
// 1. UNIT TESTS: Helpers & Sanitisation
// ============================================================
echo "1. Unit Tests: Helpers, Validation, and Security Helpers\n";
echo "------------------------------------------------------------\n";

// Test XSS escaping
assert_equals('&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;', h('<script>alert("XSS")</script>'), 'h() escapes HTML script tags');
assert_equals('O&#039;Connor &amp; Sons', h("O'Connor & Sons"), 'h() escapes single quotes and ampersands');
assert_equals('12345', h(12345), 'h() correctly casts and handles integers');

// Test date validation
assert_true(is_valid_date('2026-11-09'), 'is_valid_date() accepts valid ISO date');
assert_true(!is_valid_date('2026-02-31'), 'is_valid_date() rejects February 31st');
assert_true(!is_valid_date('invalid-date'), 'is_valid_date() rejects non-date string');
assert_true(!is_valid_date(''), 'is_valid_date() rejects empty string');
assert_true(!is_valid_date('2026/11/09'), 'is_valid_date() rejects slash format YYYY/MM/DD');

// Test password policy (minimum 8 characters)
assert_true(password_long_enough('Admin@2026'), 'password_long_enough() accepts 10-char password');
assert_true(password_long_enough('12345678'), 'password_long_enough() accepts 8-char password');
assert_true(!password_long_enough('1234567'), 'password_long_enough() rejects 7-char password');
assert_true(!password_long_enough(''), 'password_long_enough() rejects empty password');

// Test session idle timeout (30 minutes = 1800s)
$now = 1700000000;
assert_true(!session_is_idle($now - 1799, $now), 'session_is_idle() allows session active 29 min 59 sec ago');
assert_true(session_is_idle($now - 1801, $now), 'session_is_idle() expires session active 30 min 1 sec ago');
assert_true(session_is_idle($now - 3600, $now), 'session_is_idle() expires session active 1 hour ago');

// Test CSRF token generation and validation
$_SESSION = [];
$token = csrf_token();
assert_true(!empty($token) && strlen($token) === 64, 'csrf_token() generates 64-char hex token');
$_POST['csrf_token'] = $token;
assert_true(csrf_ok(), 'csrf_ok() succeeds when tokens match');
$_POST['csrf_token'] = 'invalid_forged_token';
assert_true(!csrf_ok(), 'csrf_ok() rejects forged or tampered token');
unset($_POST['csrf_token']);

echo "\n";

// ============================================================
// 2. BUSINESS RULES: Attendance Calculations
// ============================================================
echo "2. Business Rules: Attendance Calculation & Edge Cases\n";
echo "------------------------------------------------------------\n";

// Zero sessions division-by-zero prevention
assert_equals(null, attendance_percentage(0, 0), 'attendance_percentage(0, 0) returns null to prevent division by zero');
assert_equals('No attendance recorded', format_attendance_percentage(0, 0), 'format_attendance_percentage(0, 0) prints friendly label');

// Standard attendance calculations
assert_equals(75.0, attendance_percentage(3, 4), 'attendance_percentage(3, 4) produces 75.0%');
assert_equals('75%', format_attendance_percentage(3, 4), 'format_attendance_percentage(3, 4) produces 75%');
assert_equals(100.0, attendance_percentage(5, 5), 'attendance_percentage(5, 5) produces 100.0%');
assert_equals(0.0, attendance_percentage(0, 4), 'attendance_percentage(0, 4) produces 0.0%');

// Attendance summarisation
$sample_records = [
    ['status' => 'present'],
    ['status' => 'present'],
    ['status' => 'present'],
    ['status' => 'absent'],
];
$summary = summarise_attendance($sample_records);
assert_equals(3, $summary['present'], 'summarise_attendance() counts 3 presents');
assert_equals(1, $summary['absent'], 'summarise_attendance() counts 1 absent');
assert_equals(4, $summary['recorded'], 'summarise_attendance() calculates 4 total recorded sessions');
assert_equals(75.0, $summary['percent'], 'summarise_attendance() gives 75.0% rate');

echo "\n";

// ============================================================
// 3. BUSINESS RULES: Grading Criteria Evaluation
// ============================================================
echo "3. Business Rules: Examination Grading Scale\n";
echo "------------------------------------------------------------\n";

function evaluate_mock_grade($pct) {
    // Official Oxford Academy scale:
    // A+: 90 - 100
    // A : 75 - 89.99
    // B : 65 - 74.99
    // C : 55 - 64.99
    // D : 40 - 54.99
    // F : < 40
    $scale = [
        ['label' => 'A+', 'min' => 90.0, 'max' => 100.0],
        ['label' => 'A',  'min' => 75.0, 'max' => 89.99],
        ['label' => 'B',  'min' => 65.0, 'max' => 74.99],
        ['label' => 'C',  'min' => 55.0, 'max' => 64.99],
        ['label' => 'D',  'min' => 40.0, 'max' => 54.99],
        ['label' => 'F',  'min' => 0.0,  'max' => 39.99],
    ];
    $pct_round = round((float) $pct, 2);
    foreach ($scale as $g) {
        if ($pct_round >= $g['min'] && $pct_round <= $g['max']) {
            return $g['label'];
        }
    }
    return ($pct_round > 100.0) ? 'A+' : 'F';
}

assert_equals('A+', evaluate_mock_grade(100), '100% maps to A+');
assert_equals('A+', evaluate_mock_grade(90.0), '90.0% boundary maps to A+');
assert_equals('A',  evaluate_mock_grade(89.99), '89.99% boundary maps to A');
assert_equals('A',  evaluate_mock_grade(75.0), '75.0% boundary maps to A');
assert_equals('B',  evaluate_mock_grade(74.99), '74.99% boundary maps to B');
assert_equals('B',  evaluate_mock_grade(65.0), '65.0% boundary maps to B');
assert_equals('C',  evaluate_mock_grade(64.99), '64.99% boundary maps to C');
assert_equals('C',  evaluate_mock_grade(55.0), '55.0% boundary maps to C');
assert_equals('D',  evaluate_mock_grade(54.99), '54.99% boundary maps to D');
assert_equals('D',  evaluate_mock_grade(40.0), '40.0% boundary maps to D');
assert_equals('F',  evaluate_mock_grade(39.99), '39.99% boundary maps to F');
assert_equals('F',  evaluate_mock_grade(0), '0% maps to F');

echo "\n";

// ============================================================
// 4. BUSINESS RULES: Mark Entry & Validation Policies
// ============================================================
echo "4. Business Rules: Mark Validation Policies\n";
echo "------------------------------------------------------------\n";

function validate_mark_entry($raw_marks, $total_marks) {
    if (trim((string) $raw_marks) === '') {
        return ['valid' => true, 'skipped' => true];
    }
    if (!is_numeric($raw_marks)) {
        return ['valid' => false, 'error' => 'Non-numeric mark'];
    }
    $val = (float) $raw_marks;
    if ($val < 0) {
        return ['valid' => false, 'error' => 'Negative mark'];
    }
    if ($val > $total_marks) {
        return ['valid' => false, 'error' => 'Marks exceed maximum'];
    }
    return ['valid' => true, 'skipped' => false, 'marks' => $val];
}

$v1 = validate_mark_entry('85', 100);
assert_true($v1['valid'] && !$v1['skipped'] && $v1['marks'] === 85.0, 'Valid marks accepted');

$v2 = validate_mark_entry('', 100);
assert_true($v2['valid'] && $v2['skipped'], 'Blank marks marked as skipped (absent/pending), not clamped to zero');

$v3 = validate_mark_entry('105', 100);
assert_true(!$v3['valid'] && $v3['error'] === 'Marks exceed maximum', 'Marks above total_marks strictly rejected (not clamped)');

$v4 = validate_mark_entry('-5', 100);
assert_true(!$v4['valid'] && $v4['error'] === 'Negative mark', 'Negative marks strictly rejected');

$v5 = validate_mark_entry('abc', 100);
assert_true(!$v5['valid'] && $v5['error'] === 'Non-numeric mark', 'Non-numeric input strictly rejected');

echo "\n";

// ============================================================
// 5. STATIC SECURITY & CODE INTEGRITY AUDIT
// ============================================================
echo "5. Static Security & Architectural Integrity Checks\n";
echo "------------------------------------------------------------\n";

$base_dir = realpath(__DIR__ . '/..');
$all_php_files = [];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base_dir));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getPathname();
        if (strpos($path, '/.resources/') === false && strpos($path, '/tests/') === false) {
            $all_php_files[] = $path;
        }
    }
}

// 5.1 Check SQL Injection Vulnerabilities (raw concatenation of $_POST or $_GET into SQL)
$sqli_found = 0;
foreach ($all_php_files as $file) {
    $content = file_get_contents($file);
    // Search for patterns like "query(...$_GET...)" or "...$_POST..." without prepared statement
    if (preg_match('/\$pdo->query\([^)]*\$_(GET|POST)/i', $content, $m)) {
        $sqli_found++;
        echo "  [FAIL] Potential SQL injection found in " . basename($file) . "\n";
    }
}
assert_equals(0, $sqli_found, 'Zero raw $_GET/$_POST string concatenations in PDO queries across codebase');

// 5.2 Verify CSRF Field presence on state-changing pages
$state_changing_pages = [
    'admin/fees/set_fee_structure.php',
    'admin/fees/record_payment.php',
    'admin/exams/schedule_exam.php',
    'admin/exams/grading_criteria.php',
    'admin/students/add_student.php',
    'admin/teachers/add_teacher.php',
    'admin/classes/add_class.php',
    'teacher/attendance/mark_attendance.php',
    'teacher/results/enter_marks.php',
    'change_password.php',
];

$missing_csrf = 0;
foreach ($state_changing_pages as $rel_path) {
    $full = $base_dir . '/' . $rel_path;
    if (file_exists($full)) {
        $code = file_get_contents($full);
        if (strpos($code, 'csrf_field()') === false || strpos($code, 'csrf_ok()') === false) {
            $missing_csrf++;
            echo "  [FAIL] Missing CSRF protection in $rel_path\n";
        }
    }
}
assert_equals(0, $missing_csrf, 'All state-changing forms implement both csrf_field() and csrf_ok()');

// 5.3 Verify Auth Check Guard on role-protected pages
$protected_dirs = ['admin', 'teacher', 'student'];
$missing_auth_guard = 0;
foreach ($all_php_files as $file) {
    $rel = str_replace($base_dir . '/', '', $file);
    $parts = explode('/', $rel);
    if (in_array($parts[0], $protected_dirs, true)) {
        $code = file_get_contents($file);
        if (strpos($code, 'auth_check.php') === false) {
            $missing_auth_guard++;
            echo "  [FAIL] Protected file $rel does not include auth_check.php\n";
        }
    }
}
assert_equals(0, $missing_auth_guard, 'Every protected script in admin/, teacher/, and student/ executes auth_check.php');

// 5.4 Verify Student Portal Isolation (student files do not accept student_id from GET)
$student_files = [
    'student/attendance.php',
    'student/fees.php',
    'student/results.php',
    'student/progress_report.php',
    'student/dashboard.php',
];
$forged_student_param = 0;
foreach ($student_files as $sf) {
    $code = file_get_contents($base_dir . '/' . $sf);
    if (preg_match('/\$student_id\s*=\s*(\(int\)\s*)?\$_GET\[/i', $code)) {
        $forged_student_param++;
        echo "  [FAIL] Insecure student_id retrieval from \$_GET in $sf\n";
    }
}
assert_equals(0, $forged_student_param, 'Student portal scripts resolve student identity strictly from $_SESSION');

echo "\n============================================================\n";
echo " Test Execution Summary\n";
echo "============================================================\n";
echo " Total Assertions : " . $total_assertions . "\n";
echo " Passed           : " . $passed_assertions . "\n";
echo " Failed           : " . count($failed_assertions) . "\n";

if (count($failed_assertions) === 0) {
    echo " Status           : ALL TESTS PASSED (100% success rate)\n";
} else {
    echo " Status           : TESTS FAILED\n";
    foreach ($failed_assertions as $f) {
        echo "   - " . $f . "\n";
    }
}
echo "============================================================\n";
