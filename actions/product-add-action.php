<?php
/**
 * EasyShop — Product Creation Action Handler
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
    setFlash('error', 'Security check failed (invalid CSRF token). Please try submitting again.');
    header('Location: ../admin/products/add.php');
    exit;
}

// 1. Extract & Sanitize Inputs
$productName   = trim($_POST['product_name'] ?? '');
$categoryId    = (int)($_POST['category_id'] ?? 0);
$sku           = strtoupper(trim($_POST['sku'] ?? ''));
$slug          = trim($_POST['slug'] ?? '');
$priceRaw      = trim($_POST['price'] ?? '');
$origPriceRaw  = trim($_POST['original_price'] ?? '');
$stockRaw      = trim($_POST['stock'] ?? '');
$description   = trim($_POST['description'] ?? '');
$status        = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

$_SESSION['old_product_input'] = [
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

// 2. Validate Fields
if (empty($productName) || mb_strlen($productName) < 2 || mb_strlen($productName) > 200) {
    $errors['product_name'] = 'Product title must be between 2 and 200 characters.';
}

if ($categoryId <= 0) {
    $errors['category_id'] = 'Please select a valid category.';
} else {
    // Verify category exists
    $catCheck = mysqli_prepare($conn, "SELECT category_id FROM categories WHERE category_id = ? LIMIT 1");
    mysqli_stmt_bind_param($catCheck, 'i', $categoryId);
    mysqli_stmt_execute($catCheck);
    if (!mysqli_stmt_fetch($catCheck)) {
        $errors['category_id'] = 'Selected category does not exist in the database.';
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

// Price validation: positive decimal
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

// Stock validation: non-negative integer
if (!is_numeric($stockRaw) || (int)$stockRaw < 0 || (string)(int)$stockRaw !== $stockRaw) {
    $errors['stock'] = 'Stock must be a non-negative whole integer (0 or greater).';
}
$stock = (int)$stockRaw;

// 3. Uniqueness Checks for SKU and Slug
if (empty($errors)) {
    $uniqStmt = mysqli_prepare($conn, "SELECT product_id, sku, slug FROM products WHERE sku = ? OR slug = ? LIMIT 1");
    mysqli_stmt_bind_param($uniqStmt, 'ss', $sku, $slug);
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

// 4. Handle Secure Image Upload
$imagePath = 'assets/images/product-placeholder.svg'; // Default fallback

if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $uploadResult = secure_upload_image($_FILES['image'], 'products');
    if ($uploadResult['success']) {
        $imagePath = $uploadResult['path'];
    } else {
        $errors['image'] = $uploadResult['error'];
    }
}

// If validation errors exist, redirect back
if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    setFlash('error', 'Please resolve the highlighted validation errors.');
    header('Location: ../admin/products/add.php');
    exit;
}

// 5. Insert Product with Prepared Statement
$insertSql = "INSERT INTO products 
              (category_id, product_name, slug, sku, description, price, original_price, stock, image, status) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$insertStmt = mysqli_prepare($conn, $insertSql);

mysqli_stmt_bind_param(
    $insertStmt, 
    'issssdidss', 
    $categoryId, 
    $productName, 
    $slug, 
    $sku, 
    $description, 
    $price, 
    $originalPrice, 
    $stock, 
    $imagePath, 
    $status
);

if (mysqli_stmt_execute($insertStmt)) {
    mysqli_stmt_close($insertStmt);
    unset($_SESSION['old_product_input'], $_SESSION['form_errors']);
    setFlash('success', 'Product "' . $productName . '" (SKU: ' . $sku . ') added to catalog successfully.');
    header('Location: ../admin/products/index.php');
    exit;
} else {
    error_log("[Product Add Error] " . mysqli_stmt_error($insertStmt));
    mysqli_stmt_close($insertStmt);
    setFlash('error', 'Database error: Unable to create product.');
    header('Location: ../admin/products/add.php');
    exit;
}
