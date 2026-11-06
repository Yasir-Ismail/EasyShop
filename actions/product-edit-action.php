<?php
/**
 * EasyShop — Product Update Action Handler
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/admin-check.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin/products/index.php');
    exit;
}

if (!validate_csrf()) {
    setFlash('error', 'Security check failed (invalid CSRF token). Please try updating again.');
    header('Location: ../admin/products/index.php');
    exit;
}

$productId    = (int)($_POST['product_id'] ?? 0);
$productName  = trim($_POST['product_name'] ?? '');
$categoryId   = (int)($_POST['category_id'] ?? 0);
$sku          = strtoupper(trim($_POST['sku'] ?? ''));
$slug         = trim($_POST['slug'] ?? '');
$priceRaw     = trim($_POST['price'] ?? '');
$origPriceRaw = trim($_POST['original_price'] ?? '');
$stockRaw     = trim($_POST['stock'] ?? '');
$description  = trim($_POST['description'] ?? '');
$status       = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

if ($productId <= 0) {
    setFlash('error', 'Invalid product identifier.');
    header('Location: ../admin/products/index.php');
    exit;
}

// 1. Fetch current product record
$curStmt = mysqli_prepare($conn, "SELECT image FROM products WHERE product_id = ? LIMIT 1");
mysqli_stmt_bind_param($curStmt, 'i', $productId);
mysqli_stmt_execute($curStmt);
$curRes = mysqli_stmt_get_result($curStmt);
$currentProduct = mysqli_fetch_assoc($curRes);
mysqli_stmt_close($curStmt);

if (!$currentProduct) {
    setFlash('error', 'Product not found.');
    header('Location: ../admin/products/index.php');
    exit;
}

$_SESSION['old_product_input'] = [
    'product_id'     => $productId,
    'product_name'   => $productName,
    'category_id'    => $categoryId,
    'sku'            => $sku,
    'slug'           => $slug,
    'price'          => $priceRaw,
    'original_price' => $origPriceRaw,
    'stock'          => $stockRaw,
    'description'    => $description,
    'status'         => $status
];

$errors = [];

// 2. Validate Inputs
if (empty($productName) || mb_strlen($productName) < 2 || mb_strlen($productName) > 200) {
    $errors['product_name'] = 'Product title must be between 2 and 200 characters.';
}

if ($categoryId <= 0) {
    $errors['category_id'] = 'Please select a valid category.';
} else {
    $catCheck = mysqli_prepare($conn, "SELECT category_id FROM categories WHERE category_id = ? LIMIT 1");
    mysqli_stmt_bind_param($catCheck, 'i', $categoryId);
    mysqli_stmt_execute($catCheck);
    if (!mysqli_stmt_fetch($catCheck)) {
        $errors['category_id'] = 'Selected category does not exist.';
    }
    mysqli_stmt_close($catCheck);
}

if (empty($sku) || mb_strlen($sku) < 2 || mb_strlen($sku) > 50) {
    $errors['sku'] = 'SKU is required (2 to 50 alphanumeric characters).';
}

if (empty($slug)) {
    $slug = slugify($productName);
} else {
    $slug = slugify($slug);
}

if (!is_numeric($priceRaw) || (float)$priceRaw <= 0) {
    $errors['price'] = 'Price must be a valid positive number greater than zero.';
}
$price = (float)$priceRaw;

$originalPrice = null;
if (!empty($origPriceRaw)) {
    if (!is_numeric($origPriceRaw) || (float)$origPriceRaw <= 0) {
        $errors['original_price'] = 'Original price must be a valid positive number.';
    } else {
        $originalPrice = (float)$origPriceRaw;
    }
}

if (!is_numeric($stockRaw) || (int)$stockRaw < 0 || (string)(int)$stockRaw !== $stockRaw) {
    $errors['stock'] = 'Stock must be a non-negative whole integer (0 or greater).';
}
$stock = (int)$stockRaw;

// 3. Uniqueness Check (Excluding current product)
if (empty($errors)) {
    $uniqStmt = mysqli_prepare($conn, "SELECT product_id, sku, slug FROM products WHERE (sku = ? OR slug = ?) AND product_id != ? LIMIT 1");
    mysqli_stmt_bind_param($uniqStmt, 'ssi', $sku, $slug, $productId);
    mysqli_stmt_execute($uniqStmt);
    $uniqRes = mysqli_stmt_get_result($uniqStmt);

    if ($existing = mysqli_fetch_assoc($uniqRes)) {
        if ($existing['sku'] === $sku) {
            $errors['sku'] = 'Another product is already registered with this SKU.';
        }
        if ($existing['slug'] === $slug) {
            $errors['slug'] = 'Another product is already registered with this URL slug.';
        }
    }
    mysqli_stmt_close($uniqStmt);
}

// 4. Handle Optional Image Replacement
$newImagePath = null;
$oldImagePath = $currentProduct['image'];

if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $uploadResult = secure_upload_image($_FILES['image'], 'products');
    if ($uploadResult['success']) {
        $newImagePath = $uploadResult['path'];
    } else {
        $errors['image'] = $uploadResult['error'];
    }
}

// If errors exist, return back
if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    setFlash('error', 'Please resolve the highlighted validation errors.');
    header('Location: ../admin/products/edit.php?id=' . $productId);
    exit;
}

// 5. Update Product in Database
if ($newImagePath !== null) {
    $updateSql = "UPDATE products SET 
                  category_id = ?, product_name = ?, slug = ?, sku = ?, description = ?, 
                  price = ?, original_price = ?, stock = ?, image = ?, status = ? 
                  WHERE product_id = ?";
    $updateStmt = mysqli_prepare($conn, $updateSql);
    mysqli_stmt_bind_param(
        $updateStmt, 
        'issssdidssi', 
        $categoryId, $productName, $slug, $sku, $description, 
        $price, $originalPrice, $stock, $newImagePath, $status, $productId
    );
} else {
    $updateSql = "UPDATE products SET 
                  category_id = ?, product_name = ?, slug = ?, sku = ?, description = ?, 
                  price = ?, original_price = ?, stock = ?, status = ? 
                  WHERE product_id = ?";
    $updateStmt = mysqli_prepare($conn, $updateSql);
    mysqli_stmt_bind_param(
        $updateStmt, 
        'issssdidsi', 
        $categoryId, $productName, $slug, $sku, $description, 
        $price, $originalPrice, $stock, $status, $productId
    );
}

if (mysqli_stmt_execute($updateStmt)) {
    mysqli_stmt_close($updateStmt);

    // If a new image was saved, safely clean up old uploaded image if it was in uploads/
    if ($newImagePath !== null && !empty($oldImagePath) && strpos($oldImagePath, 'uploads/') === 0) {
        // Verify no other product references this old image file
        $refStmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM products WHERE image = ? AND product_id != ?");
        mysqli_stmt_bind_param($refStmt, 'si', $oldImagePath, $productId);
        mysqli_stmt_execute($refStmt);
        mysqli_stmt_bind_result($refStmt, $refCount);
        mysqli_stmt_fetch($refStmt);
        mysqli_stmt_close($refStmt);

        if ($refCount === 0) {
            $oldDiskPath = __DIR__ . '/../' . ltrim($oldImagePath, '/');
            if (file_exists($oldDiskPath)) {
                @unlink($oldDiskPath);
            }
        }
    }

    unset($_SESSION['old_product_input'], $_SESSION['form_errors']);
    setFlash('success', 'Product "' . $productName . '" (SKU: ' . $sku . ') updated successfully.');
    header('Location: ../admin/products/index.php');
    exit;
} else {
    error_log("[Product Edit Error] " . mysqli_stmt_error($updateStmt));
    mysqli_stmt_close($updateStmt);
    setFlash('error', 'Database error: Unable to update product.');
    header('Location: ../admin/products/edit.php?id=' . $productId);
    exit;
}
