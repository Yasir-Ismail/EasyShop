<?php
/**
 * EasyShop — Customer Password Change Action Handler
 * 
 * Verifies the existing current password, validates new password complexity
 * and confirmation, securely hashes the new password with password_hash(),
 * and updates the database via prepared statements.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../customer/profile.php');
    exit;
}

if (!isLoggedIn()) {
    setFlash('error', 'Please log in to manage your security settings.');
    header('Location: ../auth/login.php');
    exit;
}

if (!validate_csrf()) {
    setFlash('error', 'Security check failed. Please refresh your page and try again.');
    header('Location: ../customer/profile.php');
    exit;
}

if (!$conn) {
    setFlash('error', 'Database connection unavailable.');
    header('Location: ../customer/profile.php');
    exit;
}

$userId          = (int)$_SESSION['user']['user_id'];
$currentPassword = $_POST['current_password'] ?? '';
$newPassword     = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

$errors = [];

if (empty($currentPassword)) {
    $errors[] = 'Please enter your current password.';
}

if (empty($newPassword) || strlen($newPassword) < 8) {
    $errors[] = 'New password must be at least 8 characters long.';
}

if ($newPassword !== $confirmPassword) {
    $errors[] = 'New password and confirm password do not match.';
}

if (!empty($errors)) {
    setFlash('error', implode(' ', $errors));
    header('Location: ../customer/profile.php');
    exit;
}

// 1. Fetch current password hash from database
$stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE user_id = ? LIMIT 1");
if (!$stmt) {
    setFlash('error', 'A system error occurred. Please try again.');
    header('Location: ../customer/profile.php');
    exit;
}
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $currentPasswordHash);
$found = mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

if (!$found || !password_verify($currentPassword, $currentPasswordHash)) {
    setFlash('error', 'The current password you entered is incorrect.');
    header('Location: ../customer/profile.php');
    exit;
}

// Prevent reusing the same password
if (password_verify($newPassword, $currentPasswordHash)) {
    setFlash('error', 'New password must be different from your current password.');
    header('Location: ../customer/profile.php');
    exit;
}

// 2. Hash new password and update in database
$newHash = password_hash($newPassword, PASSWORD_DEFAULT);
$updStmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE user_id = ?");
if (!$updStmt) {
    setFlash('error', 'Unable to prepare password update.');
    header('Location: ../customer/profile.php');
    exit;
}
mysqli_stmt_bind_param($updStmt, 'si', $newHash, $userId);

if (mysqli_stmt_execute($updStmt)) {
    mysqli_stmt_close($updStmt);
    setFlash('success', 'Your password has been changed successfully.');
} else {
    mysqli_stmt_close($updStmt);
    setFlash('error', 'Failed to update password. Please try again.');
}

header('Location: ../customer/profile.php');
exit;
