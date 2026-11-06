<?php
/**
 * EasyShop — Category Deletion Action Handler
 * 
 * Safely prevents accidental deletion of categories that currently have products assigned.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/admin-check.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

// Only allow state-changing operations via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    setFlash('error', 'Method not allowed. Deletion must use POST.');
    header('Location: ../admin/categories/index.php');
    exit;
}

if (!validate_csrf()) {
    setFlash('error', 'Security check failed (invalid CSRF token). Deletion aborted.');
    header('Location: ../admin/categories/index.php');
    exit;
}

$categoryId = (int)($_POST['category_id'] ?? 0);

if ($categoryId <= 0) {
    setFlash('error', 'Invalid category ID specified for deletion.');
    header('Location: ../admin/categories/index.php');
    exit;
}

// 1. Fetch category name
$catStmt = mysqli_prepare($conn, "SELECT category_name FROM categories WHERE category_id = ? LIMIT 1");
mysqli_stmt_bind_param($catStmt, 'i', $categoryId);
mysqli_stmt_execute($catStmt);
$catRes = mysqli_stmt_get_result($catStmt);
$category = mysqli_fetch_assoc($catRes);
mysqli_stmt_close($catStmt);

if (!$category) {
    setFlash('error', 'Category does not exist.');
    header('Location: ../admin/categories/index.php');
    exit;
}

// 2. Foreign Key Protection Check: Verify if any products are assigned to this category
$countStmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM products WHERE category_id = ?");
mysqli_stmt_bind_param($countStmt, 'i', $categoryId);
mysqli_stmt_execute($countStmt);
mysqli_stmt_bind_result($countStmt, $productCount);
mysqli_stmt_fetch($countStmt);
mysqli_stmt_close($countStmt);

if ($productCount > 0) {
    setFlash('error', 'Deletion Blocked: Category "' . $category['category_name'] . '" contains ' . $productCount . ' product(s). Please reassign or delete those products before removing this category.');
    header('Location: ../admin/categories/index.php');
    exit;
}

// 3. Safe Deletion via Prepared Statement
$delStmt = mysqli_prepare($conn, "DELETE FROM categories WHERE category_id = ?");
mysqli_stmt_bind_param($delStmt, 'i', $categoryId);

if (mysqli_stmt_execute($delStmt)) {
    mysqli_stmt_close($delStmt);
    setFlash('success', 'Category "' . $category['category_name'] . '" was deleted successfully.');
} else {
    error_log("[Category Delete Error] " . mysqli_stmt_error($delStmt));
    mysqli_stmt_close($delStmt);
    setFlash('error', 'Database error: Unable to delete category.');
}

header('Location: ../admin/categories/index.php');
exit;
