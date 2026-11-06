<?php


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
          || (isset($_POST['ajax']) && $_POST['ajax'] == '1')
          || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

// 1. Guest Check: Enforce Authentication
if (!isLoggedIn()) {
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode([
            'success'        => false,
            'login_required' => true,
            'message'        => 'Please log in to save products to your wishlist.'
        ]);
        exit;
    }

    setFlash('error', 'Please log in to add products to your wishlist.');
    header('Location: ../auth/login.php');
    exit;
}

// 2. Validate CSRF Token
if (!validate_csrf()) {
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired CSRF token.']);
        exit;
    }
    setFlash('error', 'Security check failed. Please try again.');
    header('Location: ../index.php');
    exit;
}

$userId    = (int)$_SESSION['user']['user_id'];
$productId = (int)($_POST['product_id'] ?? 0);

if ($productId <= 0) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid product identifier.']);
        exit;
    }
    header('Location: ../products.php');
    exit;
}

// 3. Verify Product Active Status
$prodStmt = mysqli_prepare($conn, "SELECT product_name, status FROM products WHERE product_id = ? LIMIT 1");
mysqli_stmt_bind_param($prodStmt, 'i', $productId);
mysqli_stmt_execute($prodStmt);
$prodRes = mysqli_stmt_get_result($prodStmt);
$product = mysqli_fetch_assoc($prodRes);
mysqli_stmt_close($prodStmt);

if (!$product || $product['status'] !== 'active') {
    $msg = 'This product is currently unavailable.';
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }
    setFlash('error', $msg);
    header('Location: ../products.php');
    exit;
}

// 4. Check for Existing Wishlist Entry
$checkStmt = mysqli_prepare($conn, "SELECT wishlist_id FROM wishlist_items WHERE user_id = ? AND product_id = ? LIMIT 1");
mysqli_stmt_bind_param($checkStmt, 'ii', $userId, $productId);
mysqli_stmt_execute($checkStmt);
mysqli_stmt_store_result($checkStmt);
$alreadyInWishlist = (mysqli_stmt_num_rows($checkStmt) > 0);
mysqli_stmt_close($checkStmt);

if ($alreadyInWishlist) {
    $msg = '"' . $product['product_name'] . '" is already saved in your wishlist.';
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success'        => true,
            'already_saved'  => true,
            'message'        => $msg,
            'wishlist_count' => get_wishlist_count($userId)
        ]);
        exit;
    }
    setFlash('info', $msg);
    header('Location: ../customer/wishlist.php');
    exit;
}

// 5. Insert Wishlist Record
$inStmt = mysqli_prepare($conn, "INSERT INTO wishlist_items (user_id, product_id) VALUES (?, ?)");
mysqli_stmt_bind_param($inStmt, 'ii', $userId, $productId);
mysqli_stmt_execute($inStmt);
mysqli_stmt_close($inStmt);

$newWishlistCount = get_wishlist_count($userId);
$successMsg = 'Added "' . $product['product_name'] . '" to your wishlist.';

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success'        => true,
        'message'        => $successMsg,
        'wishlist_count' => $newWishlistCount
    ]);
    exit;
}

setFlash('success', $successMsg);
header('Location: ../customer/wishlist.php');
exit;
