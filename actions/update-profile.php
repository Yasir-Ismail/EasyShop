<?php
/**
 * EasyShop — Customer Profile Update Action Handler
 * 
 * Handles POST updates for customer Name, Phone, and Email.
 * Enforces email format & uniqueness checks, input sanitization,
 * CSRF protection, and session synchronization.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../customer/profile.php');
    exit;
}

if (!isLoggedIn()) {
    setFlash('error', 'Please log in to update your profile.');
    header('Location: ../auth/login.php');
    exit;
}

if (!validate_csrf()) {
    setFlash('error', 'Security check failed (invalid or expired token). Please try again.');
    header('Location: ../customer/profile.php');
    exit;
}

if (!$conn) {
    setFlash('error', 'Database connection unavailable.');
    header('Location: ../customer/profile.php');
    exit;
}

$userId   = (int)$_SESSION['user']['user_id'];
$name     = trim($_POST['name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$phone    = trim($_POST['phone'] ?? '');

$errors = [];

// Name validation
if (empty($name) || mb_strlen($name) < 2 || mb_strlen($name) > 100) {
    $errors[] = 'Full name must be between 2 and 100 characters.';
}

// Email validation
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
    $errors[] = 'Please enter a valid email address.';
}

// Optional Phone validation
if (!empty($phone)) {
    $cleanDigits = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($cleanDigits) < 10 || strlen($cleanDigits) > 15) {
        $errors[] = 'Phone number must contain between 10 and 15 digits.';
    }
}

if (!empty($errors)) {
    setFlash('error', implode(' ', $errors));
    header('Location: ../customer/profile.php');
    exit;
}

// Uniqueness check for email (must not collide with any other user's email)
$checkStmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ? AND user_id != ? LIMIT 1");
if (!$checkStmt) {
    setFlash('error', 'An error occurred while verifying email availability.');
    header('Location: ../customer/profile.php');
    exit;
}
mysqli_stmt_bind_param($checkStmt, 'si', $email, $userId);
mysqli_stmt_execute($checkStmt);
mysqli_stmt_store_result($checkStmt);

if (mysqli_stmt_num_rows($checkStmt) > 0) {
    mysqli_stmt_close($checkStmt);
    setFlash('error', 'The email address "' . e($email) . '" is already registered to another account.');
    header('Location: ../customer/profile.php');
    exit;
}
mysqli_stmt_close($checkStmt);

// Update user profile record using prepared statement
$updStmt = mysqli_prepare($conn, "UPDATE users SET name = ?, email = ?, phone = ? WHERE user_id = ?");
if (!$updStmt) {
    setFlash('error', 'Failed to prepare profile update statement.');
    header('Location: ../customer/profile.php');
    exit;
}

mysqli_stmt_bind_param($updStmt, 'sssi', $name, $email, $phone, $userId);

if (mysqli_stmt_execute($updStmt)) {
    mysqli_stmt_close($updStmt);

    // Update session state
    $_SESSION['user']['name']  = $name;
    $_SESSION['user']['email'] = $email;
    $_SESSION['user']['phone'] = $phone;

    setFlash('success', 'Your profile information has been updated successfully.');
} else {
    $err = mysqli_stmt_error($updStmt);
    mysqli_stmt_close($updStmt);
    error_log('[Profile Update Error] ' . $err);
    setFlash('error', 'Unable to save profile changes. Please try again.');
}

header('Location: ../customer/profile.php');
exit;
