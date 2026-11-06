<?php
/**
 * EasyShop — Category Update Action Handler
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
    header('Location: ../admin/categories/index.php');
    exit;
}

$categoryId   = (int)($_POST['category_id'] ?? 0);
$categoryName = trim($_POST['category_name'] ?? '');
$slug         = trim($_POST['slug'] ?? '');
$description  = trim($_POST['description'] ?? '');
$status       = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

if ($categoryId <= 0) {
    setFlash('error', 'Invalid category ID.');
    header('Location: ../admin/categories/index.php');
    exit;
}

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

if (empty($slug)) {
    $slug = slugify($categoryName);
} else {
    $slug = slugify($slug);
}

// Uniqueness check excluding current category
if (empty($errors)) {
    $checkStmt = mysqli_prepare($conn, "SELECT category_id, category_name, slug FROM categories WHERE (category_name = ? OR slug = ?) AND category_id != ? LIMIT 1");
    mysqli_stmt_bind_param($checkStmt, 'ssi', $categoryName, $slug, $categoryId);
    mysqli_stmt_execute($checkStmt);
    $checkRes = mysqli_stmt_get_result($checkStmt);

    if ($existing = mysqli_fetch_assoc($checkRes)) {
        if (strcasecmp($existing['category_name'], $categoryName) === 0) {
            $errors['category_name'] = 'Another category is already using this name.';
        }
        if ($existing['slug'] === $slug) {
            $errors['slug'] = 'Another category is already using this URL slug.';
        }
    }
    mysqli_stmt_close($checkStmt);
}

if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    setFlash('error', 'Please resolve the highlighted validation issues.');
    header('Location: ../admin/categories/edit.php?id=' . $categoryId);
    exit;
}

// Update category
$updateStmt = mysqli_prepare($conn, "UPDATE categories SET category_name = ?, slug = ?, description = ?, status = ? WHERE category_id = ?");
mysqli_stmt_bind_param($updateStmt, 'ssssi', $categoryName, $slug, $description, $status, $categoryId);

if (mysqli_stmt_execute($updateStmt)) {
    mysqli_stmt_close($updateStmt);
    unset($_SESSION['old_category_input'], $_SESSION['form_errors']);
    setFlash('success', 'Category "' . $categoryName . '" updated successfully.');
    header('Location: ../admin/categories/index.php');
    exit;
} else {
    error_log("[Category Edit Error] " . mysqli_stmt_error($updateStmt));
    mysqli_stmt_close($updateStmt);
    setFlash('error', 'Database error: Unable to update category.');
    header('Location: ../admin/categories/edit.php?id=' . $categoryId);
    exit;
}
