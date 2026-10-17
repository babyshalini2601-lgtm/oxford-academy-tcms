<?php
// Small shared helpers. Each page still does its own validation
// so the supervisor can follow one request from form to SQL.

function h($text) {
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function post_value($key, $default = '') {
    return trim((string) ($_POST[$key] ?? $default));
}

function is_filled($value) {
    return trim((string) $value) !== '';
}

function is_valid_date($value) {
    if (!is_filled($value)) {
        return false;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
}

function app_today() {
    return date('Y-m-d');
}

function app_month() {
    return date('Y-m');
}

function redirect_to($path) {
    header('Location: ' . BASE_URL . $path);
    exit;
}

function show_form_errors($errors) {
    if (empty($errors)) {
        return;
    }

    echo '<div class="alert alert-error"><ul style="margin-left:18px;">';
    foreach ($errors as $error) {
        echo '<li>' . h($error) . '</li>';
    }
    echo '</ul></div>';
}

// Chapter 3: passwords must be at least 8 characters.
define('PASSWORD_MIN_LENGTH', 8);

// Chapter 3 CFR-07: log the user out after 30 minutes without activity.
define('SESSION_IDLE_SECONDS', 30 * 60);

function password_long_enough($password) {
    return strlen((string) $password) >= PASSWORD_MIN_LENGTH;
}

function csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field() {
    echo '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_ok() {
    $sent  = $_POST['csrf_token'] ?? '';
    $saved = $_SESSION['csrf_token'] ?? '';

    return is_filled($saved) && hash_equals($saved, $sent);
}

function end_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 3600,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

function session_is_idle($last_activity, $now = null) {
    $now = $now ?? time();
    return ($now - (int) $last_activity) > SESSION_IDLE_SECONDS;
}
