<?php
/**
 * EasyShop — Remove from Wishlist Action Handler
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
          || (isset($_POST['ajax']) && $_POST['ajax'] == '1')
          || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../customer/wishlist.php');
    exit;
}

if (!isLoggedIn()) {
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Please log in to manage your wishlist.']);
        exit;
    }
    setFlash('error', 'Please log in to manage your wishlist.');
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
    header('Location: ../customer/wishlist.php');
    exit;
}

$userId     = (int)$_SESSION['user']['user_id'];
$wishlistId = (int)($_POST['wishlist_id'] ?? 0);
$productId  = (int)($_POST['product_id'] ?? 0);

if ($wishlistId > 0) {
    $stmt = mysqli_prepare($conn, "DELETE FROM wishlist_items WHERE wishlist_id = ? AND user_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $wishlistId, $userId);
} elseif ($productId > 0) {
    $stmt = mysqli_prepare($conn, "DELETE FROM wishlist_items WHERE product_id = ? AND user_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $productId, $userId);
} else {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid identifier.']);
        exit;
    }
    header('Location: ../customer/wishlist.php');
    exit;
}

mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$newWishlistCount = get_wishlist_count($userId);

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success'        => true,
        'message'        => 'Item removed from your wishlist.',
        'wishlist_count' => $newWishlistCount
    ]);
    exit;
}

setFlash('info', 'Item removed from your wishlist.');
header('Location: ../customer/wishlist.php');
exit;
