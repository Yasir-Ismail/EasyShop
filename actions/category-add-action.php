<?php
/**
 * EasyShop — Category Creation Action Handler
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/admin-check.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin/categories/index.php');
    exit;
}

if (!validate_csrf()) {
    setFlash('error', 'Security validation failed (invalid CSRF token). Please try again.');
    header('Location: ../admin/categories/add.php');
    exit;
}

$categoryName = trim($_POST['category_name'] ?? '');
$slug         = trim($_POST['slug'] ?? '');
$description  = trim($_POST['description'] ?? '');
$status       = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

$_SESSION['old_category_input'] = [
    'category_name' => $categoryName,
    'slug'          => $slug,
    'description'   => $description,
    'status'        => $status
];

$errors = [];

if (empty($categoryName) || mb_strlen($categoryName) < 2 || mb_strlen($categoryName) > 100) {
    $errors['category_name'] = 'Category name must be between 2 and 100 characters.';
}

// Generate slug if empty
if (empty($slug)) {
    $slug = slugify($categoryName);
} else {
    $slug = slugify($slug);
}

// Check uniqueness of Category Name and Slug
if (empty($errors)) {
    $checkStmt = mysqli_prepare($conn, "SELECT category_id, category_name, slug FROM categories WHERE category_name = ? OR slug = ? LIMIT 1");
    mysqli_stmt_bind_param($checkStmt, 'ss', $categoryName, $slug);
    mysqli_stmt_execute($checkStmt);
    $checkRes = mysqli_stmt_get_result($checkStmt);

    if ($existing = mysqli_fetch_assoc($checkRes)) {
        if (strcasecmp($existing['category_name'], $categoryName) === 0) {
            $errors['category_name'] = 'A category with this name already exists.';
        }
        if ($existing['slug'] === $slug) {
            $errors['slug'] = 'A category with this URL slug already exists.';
        }
    }
    mysqli_stmt_close($checkStmt);
}

if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    setFlash('error', 'Please resolve the highlighted validation issues.');
    header('Location: ../admin/categories/add.php');
    exit;
}

// Insert category
$insertStmt = mysqli_prepare($conn, "INSERT INTO categories (category_name, slug, description, status) VALUES (?, ?, ?, ?)");
mysqli_stmt_bind_param($insertStmt, 'ssss', $categoryName, $slug, $description, $status);

if (mysqli_stmt_execute($insertStmt)) {
    mysqli_stmt_close($insertStmt);
    unset($_SESSION['old_category_input'], $_SESSION['form_errors']);
    setFlash('success', 'Category "' . $categoryName . '" created successfully.');
    header('Location: ../admin/categories/index.php');
    exit;
} else {
    error_log("[Category Add Error] " . mysqli_stmt_error($insertStmt));
    mysqli_stmt_close($insertStmt);
    setFlash('error', 'Database error: Unable to create category.');
    header('Location: ../admin/categories/add.php');
    exit;
}
