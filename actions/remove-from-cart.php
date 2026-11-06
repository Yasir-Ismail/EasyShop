<?php
/**
 * EasyShop — Remove Item from Cart Action Handler
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
    setFlash('error', 'Please log in to modify your cart.');
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

if ($cartItemId <= 0) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid cart item.']);
        exit;
    }
    header('Location: ../cart.php');
    exit;
}

// Delete cart item strictly scoped to current user
$delStmt = mysqli_prepare($conn, "DELETE FROM cart_items WHERE cart_item_id = ? AND user_id = ?");
mysqli_stmt_bind_param($delStmt, 'ii', $cartItemId, $userId);
mysqli_stmt_execute($delStmt);
$affected = mysqli_stmt_affected_rows($delStmt);
mysqli_stmt_close($delStmt);

$cartData = get_user_cart($userId);

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success'       => true,
        'message'       => 'Item removed from your cart.',
        'cart_subtotal' => format_pkr($cartData['subtotal']),
        'total_qty'     => $cartData['total_qty'],
        'cart_count'    => get_cart_count($userId),
        'is_empty'      => empty($cartData['items'])
    ]);
    exit;
}

setFlash('info', 'Item removed from your shopping cart.');
header('Location: ../cart.php');
exit;
