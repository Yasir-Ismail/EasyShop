<?php
/**
 * EasyShop — Customer Registration Processor
 * 
 * Handles POST requests from auth/register.php.
 * Validates inputs, checks for duplicate emails via prepared statements,
 * hashes passwords, and assigns the 'customer' role.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../auth/register.php');
    exit;
}

// 1. Verify CSRF Token
if (!validate_csrf()) {
    setFlash('error', 'Security check failed (invalid CSRF token). Please try submitting the form again.');
    header('Location: ../auth/register.php');
    exit;
}

// 2. Extract and Sanitize Inputs
$name             = trim($_POST['name'] ?? '');
$email            = trim($_POST['email'] ?? '');
$phone            = trim($_POST['phone'] ?? '');
$password         = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Save inputs (excluding passwords) in session to repopulate form on error
$_SESSION['old_input'] = [
    'name'  => $name,
    'email' => $email,
    'phone' => $phone
];

// 3. Server-side Validation
$errors = [];

if (empty($name) || mb_strlen($name) < 2 || mb_strlen($name) > 100) {
    $errors['name'] = 'Full name must be between 2 and 100 characters.';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 191) {
    $errors['email'] = 'Please provide a valid email address.';
}

if (empty($phone) || !preg_match('/^[0-9+\-\s()]{10,20}$/', $phone)) {
    $errors['phone'] = 'Please enter a valid phone number (10-20 digits).';
}

if (empty($password) || strlen($password) < 6) {
    $errors['password'] = 'Password must be at least 6 characters in length.';
} elseif ($password !== $confirm_password) {
    $errors['confirm_password'] = 'Passwords do not match.';
}

// If validation errors exist, redirect back with error list
if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    setFlash('error', 'Please correct the highlighted errors in the form.');
    header('Location: ../auth/register.php');
    exit;
}

// 4. Check Database Connection
if (!$conn) {
    setFlash('error', 'Database connection error. Please try again later.');
    header('Location: ../auth/register.php');
    exit;
}

// 5. Check if Email Already Exists (MySQLi Prepared Statement)
$checkSql = "SELECT user_id FROM users WHERE email = ? LIMIT 1";
$checkStmt = mysqli_prepare($conn, $checkSql);

if (!$checkStmt) {
    error_log("[Register Error] Prepare failed: " . mysqli_error($conn));
    setFlash('error', 'A system error occurred. Please try again.');
    header('Location: ../auth/register.php');
    exit;
}

mysqli_stmt_bind_param($checkStmt, 's', $email);
mysqli_stmt_execute($checkStmt);
mysqli_stmt_store_result($checkStmt);

if (mysqli_stmt_num_rows($checkStmt) > 0) {
    mysqli_stmt_close($checkStmt);
    $_SESSION['form_errors'] = ['email' => 'An account with this email address already exists.'];
    setFlash('error', 'An account with this email already exists. Please log in.');
    header('Location: ../auth/register.php');
    exit;
}
mysqli_stmt_close($checkStmt);

// 6. Hash Password using standard PHP password_hash (bcrypt)
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

// 7. Insert New Customer (Enforcing 'customer' role strictly on server)
$insertSql = "INSERT INTO users (name, email, password, phone, role) VALUES (?, ?, ?, ?, 'customer')";
$insertStmt = mysqli_prepare($conn, $insertSql);

if (!$insertStmt) {
    error_log("[Register Error] Insert prepare failed: " . mysqli_error($conn));
    setFlash('error', 'A system error occurred. Please try again.');
    header('Location: ../auth/register.php');
    exit;
}

mysqli_stmt_bind_param($insertStmt, 'ssss', $name, $email, $hashedPassword, $phone);

if (mysqli_stmt_execute($insertStmt)) {
    mysqli_stmt_close($insertStmt);

    // Clear saved input
    unset($_SESSION['old_input']);
    unset($_SESSION['form_errors']);

    setFlash('success', 'Your account has been created successfully! Please log in.');
    header('Location: ../auth/login.php');
    exit;
} else {
    error_log("[Register Error] Execute failed: " . mysqli_stmt_error($insertStmt));
    mysqli_stmt_close($insertStmt);
    setFlash('error', 'Unable to create your account at this moment. Please try again.');
    header('Location: ../auth/register.php');
    exit;
}
