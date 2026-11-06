<?php
/**
 * EasyShop — Session Termination Handler (Logout)
 * 
 * Safely unsets all session variables, deletes the session cookie,
 * destroys the session storage, and redirects to login with flash feedback.
 */

require_once __DIR__ . '/../includes/functions.php';

init_session();

// 1. Clear all session variables
$_SESSION = [];

// 2. Delete the session cookie if present
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 3. Destroy session on server
session_destroy();

// 4. Start a clean session only to store the logout flash message
session_start();
setFlash('info', 'You have been logged out successfully. See you again soon!');

// 5. Redirect to Login
header('Location: ../auth/login.php');
exit;
