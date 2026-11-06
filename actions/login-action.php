<?php
/**
 * EasyShop — User Authentication Processor (Login)
 * 
 * Handles POST requests from auth/login.php.
 * Validates credentials using MySQLi prepared statements and password_verify(),
 * regenerates session ID to prevent session fixation, and routes users based on role.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../auth/login.php');
    exit;
}

// 1. Verify CSRF Token
if (!validate_csrf()) {
    setFlash('error', 'Security check failed (invalid CSRF token). Please try logging in again.');
    header('Location: ../auth/login.php');
    exit;
}

// 2. Extract Credentials
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Save email to repopulate input on failure
$_SESSION['old_login_email'] = $email;

// 3. Basic Validation
if (empty($email) || empty($password)) {
    setFlash('error', 'Please enter both your email address and password.');
    header('Location: ../auth/login.php');
    exit;
}

// 4. Check Database Connection
if (!$conn) {
    setFlash('error', 'Database connection error. Please try again later.');
    header('Location: ../auth/login.php');
    exit;
}

// 5. Query User using MySQLi Prepared Statement
$sql = "SELECT user_id, name, email, password, phone, role, status FROM users WHERE email = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    error_log("[Login Error] Prepare failed: " . mysqli_error($conn));
    setFlash('error', 'A system error occurred. Please try again.');
    header('Location: ../auth/login.php');
    exit;
}

mysqli_stmt_bind_param($stmt, 's', $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$genericErrorMessage = 'Invalid email or password. Please verify your credentials and try again.';

if ($user = mysqli_fetch_assoc($result)) {
    mysqli_stmt_close($stmt);

    // 6. Verify Hashed Password
    if (password_verify($password, $user['password'])) {
        // Inactive account check
        if (isset($user['status']) && $user['status'] === 'inactive') {
            setFlash('error', 'Your account has been deactivated. Please contact customer support.');
            header('Location: ../auth/login.php');
            exit;
        }

        // Prevent Session Fixation Attack by generating a fresh session ID
        session_regenerate_id(true);

        // Store authenticated user state (never store the password in session)
        $_SESSION['user'] = [
            'user_id' => (int)$user['user_id'],
            'name'    => $user['name'],
            'email'   => $user['email'],
            'phone'   => $user['phone'],
            'role'    => $user['role'],
            'status'  => $user['status'] ?? 'active'
        ];

        // Clean up temporary form session data
        unset($_SESSION['old_login_email']);

        setFlash('success', 'Welcome back, ' . htmlspecialchars($user['name']) . '!');

        // 7. Role-Based Routing
        if ($user['role'] === 'admin') {
            header('Location: ../admin/index.php');
            exit;
        } else {
            header('Location: ../customer/dashboard.php');
            exit;
        }
    } else {
        // Password mismatch (generic error to prevent account enumeration)
        setFlash('error', $genericErrorMessage);
        header('Location: ../auth/login.php');
        exit;
    }
} else {
    // User not found (same generic error)
    mysqli_stmt_close($stmt);
    setFlash('error', $genericErrorMessage);
    header('Location: ../auth/login.php');
    exit;
}
