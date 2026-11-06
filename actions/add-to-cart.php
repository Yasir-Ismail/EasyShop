<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

// Detect if caller expects a JSON response
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
          || (isset($_POST['ajax']) && $_POST['ajax'] == '1')
          || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        exit;
    }
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
            'message'        => 'Please log in to add products to your cart.'
        ]);
        exit;
    }

    setFlash('error', 'Please log in to add items to your shopping cart.');
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

    setFlash('error', 'Security check failed. Please refresh the page and try again.');
    header('Location: ../index.php');
    exit;
}

$userId    = (int)$_SESSION['user']['user_id'];
$productId = (int)($_POST['product_id'] ?? 0);
$quantity  = max(1, (int)($_POST['quantity'] ?? 1));

if ($productId <= 0) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid product identifier.']);
        exit;
    }
    setFlash('error', 'Invalid product.');
    header('Location: ../products.php');
    exit;
}

// 3. Fetch Product Details from Database
$prodStmt = mysqli_prepare($conn, "SELECT product_id, product_name, price, stock, status FROM products WHERE product_id = ? LIMIT 1");
mysqli_stmt_bind_param($prodStmt, 'i', $productId);
mysqli_stmt_execute($prodStmt);
$prodRes = mysqli_stmt_get_result($prodStmt);
$product = mysqli_fetch_assoc($prodRes);
mysqli_stmt_close($prodStmt);

if (!$product || $product['status'] !== 'active') {
    $msg = 'This product is currently inactive or no longer available.';
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }
    setFlash('error', $msg);
    header('Location: ../products.php');
    exit;
}

$availableStock = (int)$product['stock'];
if ($availableStock <= 0) {
    $msg = 'Sorry, "' . $product['product_name'] . '" is currently out of stock.';
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }
    setFlash('error', $msg);
    header('Location: ../products.php');
    exit;
}

// 4. Check If Product Already Exists in User's Cart
$cartCheck = mysqli_prepare($conn, "SELECT cart_item_id, quantity FROM cart_items WHERE user_id = ? AND product_id = ? LIMIT 1");
mysqli_stmt_bind_param($cartCheck, 'ii', $userId, $productId);
mysqli_stmt_execute($cartCheck);
$cartRes = mysqli_stmt_get_result($cartCheck);
$existingCart = mysqli_fetch_assoc($cartRes);
mysqli_stmt_close($cartCheck);

if ($existingCart) {
    $cartItemId = (int)$existingCart['cart_item_id'];
    $currentQty = (int)$existingCart['quantity'];

    if ($currentQty >= $availableStock) {
        $msg = 'You already have the maximum available stock (' . $availableStock . ') in your cart.';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $msg]);
            exit;
        }
        setFlash('warning', $msg);
        header('Location: ../cart.php');
        exit;
    }

    $newQty = min($currentQty + $quantity, $availableStock);

    $upStmt = mysqli_prepare($conn, "UPDATE cart_items SET quantity = ? WHERE cart_item_id = ? AND user_id = ?");
    mysqli_stmt_bind_param($upStmt, 'iii', $newQty, $cartItemId, $userId);
    mysqli_stmt_execute($upStmt);
    mysqli_stmt_close($upStmt);

    $successMsg = 'Updated "' . $product['product_name'] . '" quantity to ' . $newQty . ' in your cart.';
} else {
    $insertQty = min($quantity, $availableStock);

    $inStmt = mysqli_prepare($conn, "INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($inStmt, 'iii', $userId, $productId, $insertQty);
    mysqli_stmt_execute($inStmt);
    mysqli_stmt_close($inStmt);

    $successMsg = 'Added ' . $insertQty . 'x "' . $product['product_name'] . '" to your cart.';
}

$newCartCount = get_cart_count($userId);

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success'    => true,
        'message'    => $successMsg,
        'cart_count' => $newCartCount
    ]);
    exit;
}

setFlash('success', $successMsg);
header('Location: ../cart.php');
exit;
