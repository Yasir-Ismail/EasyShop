<?php
/**
 * EasyShop — Place Order Action Handler
 * 
 * Secure order placement processor using MySQL transactions (ACID compliant).
 * 
 * Process:
 * 1. Verifies authentication and CSRF token.
 * 2. Validates shipping and recipient inputs.
 * 3. Begins database transaction.
 * 4. Locks product rows using `SELECT ... FOR UPDATE` to prevent overselling.
 * 5. Re-validates stock levels and recalculates totals server-side.
 * 6. Generates unique order number.
 * 7. Inserts into `orders` and `order_items` (with historical snapshots).
 * 8. Deducts product stock.
 * 9. Clears customer cart.
 * 10. Commits transaction and redirects via Post/Redirect/Get (PRG).
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

// 1. Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../cart.php');
    exit;
}

// 2. Authentication Guard
if (!isLoggedIn()) {
    setFlash('error', 'Please log in to place an order.');
    header('Location: ../auth/login.php');
    exit;
}

// 3. CSRF Validation
if (!validate_csrf()) {
    setFlash('error', 'Security check failed. Please refresh your checkout page and try again.');
    header('Location: ../checkout.php');
    exit;
}

// Check database connection
if (!$conn) {
    setFlash('error', 'Database connection error. Please try placing your order again in a few moments.');
    header('Location: ../checkout.php');
    exit;
}

$currentUser = currentUser();
$userId      = (int)$currentUser['user_id'];

// 4. Sanitize and validate recipient delivery inputs
$shippingName    = trim($_POST['shipping_name'] ?? '');
$shippingPhone   = trim($_POST['shipping_phone'] ?? '');
$shippingAddress = trim($_POST['shipping_address'] ?? '');
$paymentMethod   = trim($_POST['payment_method'] ?? 'cod');

$errors = [];

if (empty($shippingName) || mb_strlen($shippingName) < 3 || mb_strlen($shippingName) > 100) {
    $errors[] = 'Please enter a valid full name (3 to 100 characters).';
}

// Pakistani phone format validation: allows digits, optional +, spaces, hyphens
// Must contain between 10 and 15 digits
$cleanPhoneDigits = preg_replace('/[^0-9]/', '', $shippingPhone);
if (empty($shippingPhone) || strlen($cleanPhoneDigits) < 10 || strlen($cleanPhoneDigits) > 15) {
    $errors[] = 'Please enter a valid Pakistani contact phone number (e.g. 03001234567).';
}

if (empty($shippingAddress) || mb_strlen($shippingAddress) < 10 || mb_strlen($shippingAddress) > 500) {
    $errors[] = 'Please enter a complete delivery address with street and city (at least 10 characters).';
}

if ($paymentMethod !== 'cod') {
    $errors[] = 'Only Cash on Delivery (COD) is currently supported.';
}

if (!empty($errors)) {
    setFlash('error', implode(' ', $errors));
    header('Location: ../checkout.php');
    exit;
}

// 5. Begin Database Transaction
mysqli_begin_transaction($conn);

try {
    // 5a. Retrieve current items in customer cart
    $cartQuery = "SELECT c.cart_item_id, c.product_id, c.quantity 
                  FROM cart_items c 
                  WHERE c.user_id = ? 
                  ORDER BY c.cart_item_id ASC";
    $cartStmt = mysqli_prepare($conn, $cartQuery);
    if (!$cartStmt) {
        throw new Exception('Failed to prepare cart retrieval statement.');
    }
    mysqli_stmt_bind_param($cartStmt, 'i', $userId);
    mysqli_stmt_execute($cartStmt);
    $cartRes = mysqli_stmt_get_result($cartStmt);
    
    $cartRows = [];
    while ($row = mysqli_fetch_assoc($cartRes)) {
        $cartRows[] = $row;
    }
    mysqli_stmt_close($cartStmt);

    if (empty($cartRows)) {
        mysqli_rollback($conn);
        setFlash('warning', 'Your shopping cart is empty. Please add products before checking out.');
        header('Location: ../cart.php');
        exit;
    }

    // 5b. Lock product rows with SELECT ... FOR UPDATE and validate stock & price
    $productStmt = mysqli_prepare($conn, "SELECT product_id, product_name, price, stock, status 
                                          FROM products 
                                          WHERE product_id = ? 
                                          FOR UPDATE");
    if (!$productStmt) {
        throw new Exception('Failed to prepare product locking statement.');
    }

    $orderItemsToInsert = [];
    $orderSubtotal = 0.0;

    foreach ($cartRows as $cartRow) {
        $productId = (int)$cartRow['product_id'];
        $requestedQty = (int)$cartRow['quantity'];

        mysqli_stmt_bind_param($productStmt, 'i', $productId);
        mysqli_stmt_execute($productStmt);
        $prodRes = mysqli_stmt_get_result($productStmt);
        $product = mysqli_fetch_assoc($prodRes);

        // Product existence and active status check
        if (!$product || $product['status'] !== 'active') {
            mysqli_stmt_close($productStmt);
            mysqli_rollback($conn);
            $pName = $product['product_name'] ?? 'A selected product';
            setFlash('error', "Sorry, \"{$pName}\" is currently unavailable. Please remove it from your cart.");
            header('Location: ../cart.php');
            exit;
        }

        // Strict stock availability check
        $availableStock = (int)$product['stock'];
        if ($availableStock < $requestedQty) {
            mysqli_stmt_close($productStmt);
            mysqli_rollback($conn);
            setFlash('error', "Insufficient stock for \"{$product['product_name']}\". Only {$availableStock} available (requested: {$requestedQty}). Please update your cart.");
            header('Location: ../cart.php');
            exit;
        }

        // Server-side calculation using live database price
        $unitPrice    = (float)$product['price'];
        $lineSubtotal = $unitPrice * $requestedQty;
        $orderSubtotal += $lineSubtotal;

        $orderItemsToInsert[] = [
            'product_id'   => $productId,
            'product_name' => $product['product_name'], // Historical snapshot
            'price'        => $unitPrice,              // Historical snapshot
            'quantity'     => $requestedQty,
            'subtotal'     => $lineSubtotal
        ];
    }
    mysqli_stmt_close($productStmt);

    // 5c. Shipping fee and grand total calculation
    $shippingFee = get_shipping_fee($orderSubtotal);
    $totalAmount = calculate_order_total($orderSubtotal, $shippingFee);

    // 5d. Generate unique collision-resistant order number
    // Format: ORD-YYYYMMDD-XXXXXX (e.g. ORD-20261106-A39DF1)
    $orderNumber = '';
    $maxAttempts = 5;
    $attempt = 0;
    $orderCheckStmt = mysqli_prepare($conn, "SELECT order_id FROM orders WHERE order_number = ? LIMIT 1");

    while ($attempt < $maxAttempts) {
        $candidateNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        mysqli_stmt_bind_param($orderCheckStmt, 's', $candidateNumber);
        mysqli_stmt_execute($orderCheckStmt);
        mysqli_stmt_store_result($orderCheckStmt);

        if (mysqli_stmt_num_rows($orderCheckStmt) === 0) {
            $orderNumber = $candidateNumber;
            break;
        }
        $attempt++;
    }
    mysqli_stmt_close($orderCheckStmt);

    if (empty($orderNumber)) {
        throw new Exception('Could not generate a unique order reference number. Please try again.');
    }

    // 5e. Insert Order Header
    $orderInsertSql = "INSERT INTO orders (
                         user_id, order_number, subtotal, shipping_fee, total_amount,
                         payment_method, payment_status, order_status, is_stock_restored,
                         shipping_name, shipping_phone, shipping_address, created_at
                       ) VALUES (
                         ?, ?, ?, ?, ?,
                         'cod', 'pending', 'pending', 0,
                         ?, ?, ?, NOW()
                       )";
    $orderInsertStmt = mysqli_prepare($conn, $orderInsertSql);
    if (!$orderInsertStmt) {
        throw new Exception('Failed to prepare order insertion statement.');
    }

    mysqli_stmt_bind_param(
        $orderInsertStmt,
        'isdddsss',
        $userId,
        $orderNumber,
        $orderSubtotal,
        $shippingFee,
        $totalAmount,
        $shippingName,
        $shippingPhone,
        $shippingAddress
    );

    if (!mysqli_stmt_execute($orderInsertStmt)) {
        $stmtErr = mysqli_stmt_error($orderInsertStmt);
        mysqli_stmt_close($orderInsertStmt);
        throw new Exception('Error creating order record: ' . $stmtErr);
    }

    $orderId = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($orderInsertStmt);

    if ($orderId <= 0) {
        throw new Exception('Failed to retrieve newly created order ID.');
    }

    // 5f. Insert Order Line Items (Historical Snapshots)
    $itemInsertSql = "INSERT INTO order_items (
                        order_id, product_id, product_name, price, quantity, subtotal
                      ) VALUES (?, ?, ?, ?, ?, ?)";
    $itemInsertStmt = mysqli_prepare($conn, $itemInsertSql);
    if (!$itemInsertStmt) {
        throw new Exception('Failed to prepare order items insertion.');
    }

    // 5g. Prepare Stock Deduction Statement
    $stockDeductSql = "UPDATE products SET stock = stock - ? WHERE product_id = ? AND stock >= ?";
    $stockDeductStmt = mysqli_prepare($conn, $stockDeductSql);
    if (!$stockDeductStmt) {
        mysqli_stmt_close($itemInsertStmt);
        throw new Exception('Failed to prepare stock deduction statement.');
    }

    foreach ($orderItemsToInsert as $item) {
        // Insert line item
        mysqli_stmt_bind_param(
            $itemInsertStmt,
            'iisdid',
            $orderId,
            $item['product_id'],
            $item['product_name'],
            $item['price'],
            $item['quantity'],
            $item['subtotal']
        );
        if (!mysqli_stmt_execute($itemInsertStmt)) {
            $err = mysqli_stmt_error($itemInsertStmt);
            mysqli_stmt_close($itemInsertStmt);
            mysqli_stmt_close($stockDeductStmt);
            throw new Exception('Failed to record order item: ' . $err);
        }

        // Deduct inventory
        mysqli_stmt_bind_param(
            $stockDeductStmt,
            'iii',
            $item['quantity'],
            $item['product_id'],
            $item['quantity']
        );
        if (!mysqli_stmt_execute($stockDeductStmt) || mysqli_stmt_affected_rows($stockDeductStmt) <= 0) {
            mysqli_stmt_close($itemInsertStmt);
            mysqli_stmt_close($stockDeductStmt);
            throw new Exception("Stock deduction failed for product ID {$item['product_id']}. Overselling prevented.");
        }
    }
    mysqli_stmt_close($itemInsertStmt);
    mysqli_stmt_close($stockDeductStmt);

    // 5h. Clear Customer Cart
    $clearCartStmt = mysqli_prepare($conn, "DELETE FROM cart_items WHERE user_id = ?");
    if ($clearCartStmt) {
        mysqli_stmt_bind_param($clearCartStmt, 'i', $userId);
        mysqli_stmt_execute($clearCartStmt);
        mysqli_stmt_close($clearCartStmt);
    }

    // 5i. Commit Entire Transaction Atomically
    mysqli_commit($conn);

    // Save recent order flag in session for confirmation page access
    $_SESSION['recent_order_placed'] = [
        'order_id'     => $orderId,
        'order_number' => $orderNumber,
        'timestamp'    => time()
    ];

    // Set flash message
    setFlash('success', "Thank you! Your order ({$orderNumber}) has been placed successfully.");

    // Redirect to Order Confirmation (PRG Pattern)
    header('Location: ../order-confirmation.php?order=' . urlencode($orderNumber));
    exit;

} catch (Throwable $e) {
    // Roll back all changes if any exception or failure occurs
    if ($conn) {
        mysqli_rollback($conn);
    }
    error_log('[EasyShop Order Placement Error] ' . $e->getMessage());

    setFlash('error', 'We encountered an issue placing your order. No charges were made, and your cart has been preserved. Please try again.');
    header('Location: ../checkout.php');
    exit;
}
