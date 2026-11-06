<?php
/**
 * EasyShop — Update Cart Quantity Action Handler
 * 
 * Modifies line item quantities, ensures minimum quantity >= 1,
 * enforces available stock bounds, and recalculates server-side subtotals.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
          || (isset($_POST['ajax']) && $_POST['ajax'] == '1')
          || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../cart.php');
    exit;
}

if (!isLoggedIn()) {
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Please log in to manage your cart.']);
        exit;
    }
    setFlash('error', 'Please log in to update your cart.');
    header('Location: ../auth/login.php');
    exit;
}

if (!validate_csrf()) {
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired CSRF token.']);
        exit;
    }
    setFlash('error', 'Security check failed. Please refresh your page.');
    header('Location: ../cart.php');
    exit;
}

$userId     = (int)$_SESSION['user']['user_id'];
$cartItemId = (int)($_POST['cart_item_id'] ?? 0);
$quantity   = max(1, (int)($_POST['quantity'] ?? 1));

if ($cartItemId <= 0) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid cart item.']);
        exit;
    }
    header('Location: ../cart.php');
    exit;
}

// Fetch item with stock from products table
$stmt = mysqli_prepare($conn, "SELECT c.cart_item_id, p.product_name, p.stock, p.price, p.status 
                               FROM cart_items c 
                               INNER JOIN products p ON c.product_id = p.product_id 
                               WHERE c.cart_item_id = ? AND c.user_id = ? 
                               LIMIT 1");
mysqli_stmt_bind_param($stmt, 'ii', $cartItemId, $userId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$item = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$item) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Cart item not found.']);
        exit;
    }
    setFlash('error', 'Cart item not found.');
    header('Location: ../cart.php');
    exit;
}

$availableStock = (int)$item['stock'];
if ($availableStock <= 0 || $item['status'] !== 'active') {
    // Product out of stock or inactive
    setFlash('warning', '"' . $item['product_name'] . '" is no longer available in stock.');
    header('Location: ../cart.php');
    exit;
}

// Cap requested quantity to available stock
$finalQty = min($quantity, $availableStock);

$updateStmt = mysqli_prepare($conn, "UPDATE cart_items SET quantity = ? WHERE cart_item_id = ? AND user_id = ?");
mysqli_stmt_bind_param($updateStmt, 'iii', $finalQty, $cartItemId, $userId);
mysqli_stmt_execute($updateStmt);
mysqli_stmt_close($updateStmt);

$cartData = get_user_cart($userId);

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success'       => true,
        'quantity'      => $finalQty,
        'line_subtotal' => format_pkr((float)$item['price'] * $finalQty),
        'cart_subtotal' => format_pkr($cartData['subtotal']),
        'total_qty'     => $cartData['total_qty'],
        'cart_count'    => get_cart_count($userId),
        'message'       => 'Cart updated successfully.'
    ]);
    exit;
}

setFlash('success', 'Cart updated successfully.');
header('Location: ../cart.php');
exit;
