<?php
/**
 * EasyShop — Admin Toggle User Account Status Handler
 * 
 * Protected Route: Requires administrator privileges.
 * Toggles user status between 'active' and 'inactive'.
 * Prevents self-deactivation and ensures at least one active administrator remains.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/admin-check.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    setFlash('error', 'Method not allowed.');
    header('Location: index.php');
    exit;
}

if (!validate_csrf()) {
    setFlash('error', 'Security check failed (invalid CSRF token). Operation aborted.');
    header('Location: index.php');
    exit;
}

$targetUserId = (int)($_POST['user_id'] ?? 0);
$newStatus    = trim($_POST['new_status'] ?? '');
$currentAdminId = (int)$_SESSION['user']['user_id'];

if ($targetUserId <= 0 || !in_array($newStatus, ['active', 'inactive'], true)) {
    setFlash('error', 'Invalid status or user ID specified.');
    header('Location: index.php');
    exit;
}

if (!$conn) {
    setFlash('error', 'Database connection unavailable.');
    header('Location: details.php?id=' . $targetUserId);
    exit;
}

// 1. Safeguard: Prevent administrator from deactivating their own account
if ($targetUserId === $currentAdminId && $newStatus === 'inactive') {
    setFlash('error', 'Action rejected: You cannot deactivate your own administrator account.');
    header('Location: details.php?id=' . $targetUserId);
    exit;
}

// 2. Fetch Target User details
$stmt = mysqli_prepare($conn, "SELECT user_id, name, role, status FROM users WHERE user_id = ? LIMIT 1");
if (!$stmt) {
    setFlash('error', 'Failed to retrieve user details.');
    header('Location: index.php');
    exit;
}
mysqli_stmt_bind_param($stmt, 'i', $targetUserId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$targetUser = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$targetUser) {
    setFlash('error', 'Target user account not found.');
    header('Location: index.php');
    exit;
}

// 3. Safeguard: If target is an admin, ensure they are not the sole active admin
if ($targetUser['role'] === 'admin' && $newStatus === 'inactive') {
    $adminCountStmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active' AND user_id != ?");
    mysqli_stmt_bind_param($adminCountStmt, 'i', $targetUserId);
    mysqli_stmt_execute($adminCountStmt);
    mysqli_stmt_bind_result($adminCountStmt, $otherAdminCount);
    mysqli_stmt_fetch($adminCountStmt);
    mysqli_stmt_close($adminCountStmt);

    if ($otherAdminCount <= 0) {
        setFlash('error', 'Action rejected: Cannot deactivate the only active administrator account.');
        header('Location: details.php?id=' . $targetUserId);
        exit;
    }
}

// 4. Update status in database using prepared statement
$updStmt = mysqli_prepare($conn, "UPDATE users SET status = ? WHERE user_id = ?");
if (!$updStmt) {
    setFlash('error', 'Failed to prepare account status update.');
    header('Location: details.php?id=' . $targetUserId);
    exit;
}
mysqli_stmt_bind_param($updStmt, 'si', $newStatus, $targetUserId);

if (mysqli_stmt_execute($updStmt)) {
    mysqli_stmt_close($updStmt);
    $statusLabel = ($newStatus === 'active') ? 'reactivated' : 'deactivated';
    setFlash('success', "Account for \"{$targetUser['name']}\" was {$statusLabel} successfully.");
} else {
    mysqli_stmt_close($updStmt);
    setFlash('error', 'Database error: Unable to update account status.');
}

header('Location: details.php?id=' . $targetUserId);
exit;
