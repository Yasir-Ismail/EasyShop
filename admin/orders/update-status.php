<?php
/**
 * EasyShop — Admin Update Order Status Action Handler
 * 
 * Protected Route: Requires administrator privileges.
 * Enforces ACID transaction and row-level locking for order cancellation
 * and guarantees that product stock is restored at most once.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/admin-check.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    setFlash('error', 'Method not allowed. Status updates must use POST.');
    header('Location: index.php');
    exit;
}

if (!validate_csrf()) {
    setFlash('error', 'Security check failed (invalid CSRF token). Update aborted.');
    header('Location: index.php');
    exit;
}

$orderId       = (int)($_POST['order_id'] ?? 0);
$newOrderStatus = trim($_POST['order_status'] ?? '');
$newPaymentStatus = trim($_POST['payment_status'] ?? '');

$validOrderStatuses   = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
$validPaymentStatuses = ['pending', 'paid', 'failed'];

if ($orderId <= 0 || !in_array($newOrderStatus, $validOrderStatuses, true) || !in_array($newPaymentStatus, $validPaymentStatuses, true)) {
    setFlash('error', 'Invalid status options or order ID submitted.');
    header('Location: index.php');
    exit;
}

if (!$conn) {
    setFlash('error', 'Database connection unavailable.');
    header('Location: index.php');
    exit;
}

// 1. Begin Database Transaction
mysqli_begin_transaction($conn);

try {
    // 2. Lock Order Row using SELECT ... FOR UPDATE
    $stmt = mysqli_prepare($conn, "SELECT order_id, order_number, order_status, payment_status, is_stock_restored 
                                   FROM orders 
                                   WHERE order_id = ? 
                                   FOR UPDATE");
    if (!$stmt) {
        throw new Exception('Failed to prepare order lock statement.');
    }
    mysqli_stmt_bind_param($stmt, 'i', $orderId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $order = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$order) {
        mysqli_rollback($conn);
        setFlash('error', 'Order not found.');
        header('Location: index.php');
        exit;
    }

    $currentStatus        = $order['order_status'];
    $isAlreadyRestored    = (int)$order['is_stock_restored'] === 1;
    $orderNumber          = $order['order_number'];

    // Rule: Cancelled orders are final and cannot be reopened
    if ($currentStatus === 'cancelled' && $newOrderStatus !== 'cancelled') {
        mysqli_rollback($conn);
        setFlash('error', "Order {$orderNumber} is permanently cancelled and cannot be reopened because stock was already restored.");
        header('Location: details.php?id=' . $orderId);
        exit;
    }

    // 3. Handle Order Cancellation & Stock Restoration
    if ($newOrderStatus === 'cancelled') {
        // Eligibility rule: Allow cancellation ONLY for pending, confirmed, and processing
        // Do NOT restore stock for shipped or delivered orders
        $cancellableStatuses = ['pending', 'confirmed', 'processing'];
        if (!in_array($currentStatus, $cancellableStatuses, true) && $currentStatus !== 'cancelled') {
            mysqli_rollback($conn);
            setFlash('error', "Cannot cancel Order {$orderNumber}. Orders that are already \"{$currentStatus}\" cannot be cancelled to preserve stock integrity.");
            header('Location: details.php?id=' . $orderId);
            exit;
        }

        // Atomic check: Restore stock ONLY IF not previously restored
        if (!$isAlreadyRestored) {
            // Retrieve line items
            $itemStmt = mysqli_prepare($conn, "SELECT product_id, quantity FROM order_items WHERE order_id = ?");
            mysqli_stmt_bind_param($itemStmt, 'i', $orderId);
            mysqli_stmt_execute($itemStmt);
            $itemRes = mysqli_stmt_get_result($itemStmt);

            $stockRestockStmt = mysqli_prepare($conn, "UPDATE products SET stock = stock + ? WHERE product_id = ?");

            while ($item = mysqli_fetch_assoc($itemRes)) {
                $prodId = $item['product_id'];
                $qty    = (int)$item['quantity'];

                // If product still exists in catalog, increment stock
                if (!empty($prodId) && $qty > 0 && $stockRestockStmt) {
                    mysqli_stmt_bind_param($stockRestockStmt, 'ii', $qty, $prodId);
                    mysqli_stmt_execute($stockRestockStmt);
                }
            }

            if ($stockRestockStmt) {
                mysqli_stmt_close($stockRestockStmt);
            }
            mysqli_stmt_close($itemStmt);

            // Update order record: set cancelled and mark is_stock_restored = 1
            $updStmt = mysqli_prepare($conn, "UPDATE orders 
                                             SET order_status = 'cancelled', payment_status = ?, is_stock_restored = 1 
                                             WHERE order_id = ?");
            mysqli_stmt_bind_param($updStmt, 'si', $newPaymentStatus, $orderId);
            mysqli_stmt_execute($updStmt);
            mysqli_stmt_close($updStmt);

            mysqli_commit($conn);

            setFlash('success', "Order {$orderNumber} status changed to Cancelled. Inventory was safely restored to stock.");
            header('Location: details.php?id=' . $orderId);
            exit;
        } else {
            // Already cancelled and stock was already restored: update payment status if changed
            $updStmt = mysqli_prepare($conn, "UPDATE orders SET payment_status = ? WHERE order_id = ?");
            mysqli_stmt_bind_param($updStmt, 'si', $newPaymentStatus, $orderId);
            mysqli_stmt_execute($updStmt);
            mysqli_stmt_close($updStmt);

            mysqli_commit($conn);

            setFlash('info', "Order {$orderNumber} payment status updated. Stock was previously restored and was not modified.");
            header('Location: details.php?id=' . $orderId);
            exit;
        }
    }

    // 4. Handle Standard Status Transition (Non-cancelled)
    // If transitioning to delivered, optionally auto-mark payment as paid for COD
    if ($newOrderStatus === 'delivered' && $newPaymentStatus === 'pending') {
        $newPaymentStatus = 'paid';
    }

    $updStmt = mysqli_prepare($conn, "UPDATE orders SET order_status = ?, payment_status = ? WHERE order_id = ?");
    mysqli_stmt_bind_param($updStmt, 'ssi', $newOrderStatus, $newPaymentStatus, $orderId);
    mysqli_stmt_execute($updStmt);
    mysqli_stmt_close($updStmt);

    mysqli_commit($conn);

    setFlash('success', "Order {$orderNumber} successfully updated to status: " . ucfirst($newOrderStatus) . ".");
    header('Location: details.php?id=' . $orderId);
    exit;

} catch (Throwable $e) {
    if ($conn) {
        mysqli_rollback($conn);
    }
    error_log('[Order Status Update Error] ' . $e->getMessage());

    setFlash('error', 'An error occurred while updating order status. Changes were rolled back.');
    header('Location: details.php?id=' . $orderId);
    exit;
}
