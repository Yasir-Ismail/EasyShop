<?php
/**
 * EasyShop — CSRF (Cross-Site Request Forgery) Protection
 * 
 * Generates cryptographically secure anti-CSRF tokens, provides form input
 * generators, and validates submitted tokens using timing-attack-safe hash_equals.
 */

require_once __DIR__ . '/functions.php';

/**
 * Generates or retrieves the active session CSRF token.
 * Uses 32 cryptographically secure pseudo-random bytes.
 *
 * @return string
 */
function get_csrf_token() {
    init_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Outputs a hidden input field containing the current CSRF token.
 *
 * @return string
 */
function csrf_field() {
    $token = get_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * Validates the submitted CSRF token against the session token.
 * Uses hash_equals to prevent timing attack side-channel leaks.
 *
 * @param string|null $submittedToken
 * @return bool
 */
function validate_csrf($submittedToken = null) {
    init_session();

    if ($submittedToken === null) {
        $submittedToken = $_POST['csrf_token'] ?? '';
    }

    if (empty($_SESSION['csrf_token']) || empty($submittedToken)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $submittedToken);
}

/**
 * Guard function: rejects the request immediately if CSRF token is invalid.
 */
function require_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validate_csrf()) {
            http_response_code(403);
            die('Error 403: Invalid or missing CSRF token. Request aborted for your security.');
        }
    }
}
