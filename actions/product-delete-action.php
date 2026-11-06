<?php
/**
 * EasyShop — Product Deletion Action Handler
 * 
 * Safely removes a product from catalog and deletes orphaned image file if located in uploads/.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/admin-check.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

// Only allow state-changing operations via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    setFlash('error', 'Method not allowed. Deletion must use POST.');
    header('Location: ../admin/products/index.php');
    exit;
}

if (!validate_csrf()) {
    setFlash('error', 'Security check failed (invalid CSRF token). Deletion aborted.');
    header('Location: ../admin/products/index.php');
    exit;
}

$productId = (int)($_POST['product_id'] ?? 0);

if ($productId <= 0) {
    setFlash('error', 'Invalid product ID specified.');
    header('Location: ../admin/products/index.php');
    exit;
}

// 1. Fetch product name and image path
$stmt = mysqli_prepare($conn, "SELECT product_name, sku, image FROM products WHERE product_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $productId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$product) {
    setFlash('error', 'Product not found.');
    header('Location: ../admin/products/index.php');
    exit;
}

// 2. Check if product is referenced in order history
$orderCheckStmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM order_items WHERE product_id = ?");
mysqli_stmt_bind_param($orderCheckStmt, 'i', $productId);
mysqli_stmt_execute($orderCheckStmt);
mysqli_stmt_bind_result($orderCheckStmt, $orderRefCount);
mysqli_stmt_fetch($orderCheckStmt);
mysqli_stmt_close($orderCheckStmt);

if ($orderRefCount > 0) {
    // Soft-deactivate to protect order historical integrity
    $deactivateStmt = mysqli_prepare($conn, "UPDATE products SET status = 'inactive' WHERE product_id = ?");
    mysqli_stmt_bind_param($deactivateStmt, 'i', $productId);
    mysqli_stmt_execute($deactivateStmt);
    mysqli_stmt_close($deactivateStmt);

    setFlash('warning', 'Product "' . e($product['product_name']) . '" is linked to ' . $orderRefCount . ' historical order(s) and cannot be permanently deleted. It has been archived (status set to "inactive") instead.');
    header('Location: ../admin/products/index.php');
    exit;
}

// 3. Delete product record from database if no order history exists
$delStmt = mysqli_prepare($conn, "DELETE FROM products WHERE product_id = ?");
mysqli_stmt_bind_param($delStmt, 'i', $productId);

if (mysqli_stmt_execute($delStmt)) {
    mysqli_stmt_close($delStmt);

    // 3. Clean up uploaded image if in uploads/ and not shared by any other product
    $imagePath = $product['image'];
    if (!empty($imagePath) && strpos($imagePath, 'uploads/') === 0) {
        $sharedStmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM products WHERE image = ?");
        mysqli_stmt_bind_param($sharedStmt, 's', $imagePath);
        mysqli_stmt_execute($sharedStmt);
        mysqli_stmt_bind_result($sharedStmt, $sharedCount);
        mysqli_stmt_fetch($sharedStmt);
        mysqli_stmt_close($sharedStmt);

        if ($sharedCount === 0) {
            $diskPath = __DIR__ . '/../' . ltrim($imagePath, '/');
            if (file_exists($diskPath)) {
                @unlink($diskPath);
            }
        }
    }

    setFlash('success', 'Product "' . $product['product_name'] . '" (SKU: ' . $product['sku'] . ') deleted successfully.');
} else {
    error_log("[Product Delete Error] " . mysqli_stmt_error($delStmt));
    mysqli_stmt_close($delStmt);
    setFlash('error', 'Database error: Unable to delete product.');
}

header('Location: ../admin/products/index.php');
exit;
