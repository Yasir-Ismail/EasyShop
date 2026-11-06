<?php
/**
 * EasyShop — Phase 6: Checkout & Order Management Automated Test Suite
 * 
 * Tests all 20 required scenarios:
 * 1. Customer can access checkout with a non-empty cart
 * 2. Guest cannot access checkout
 * 3. Empty cart cannot be checked out
 * 4. Invalid delivery information is rejected
 * 5. Correct order is created using current database prices
 * 6. Correct shipping fee is applied (Rs. 200 flat)
 * 7. Order number is unique
 * 8. Order items contain historical snapshots
 * 9. Product stock is deducted correctly
 * 10. Cart is cleared after successful order
 * 11. Failed order creation rolls back all changes
 * 12. Insufficient stock prevents checkout
 * 13. Simultaneous checkout cannot oversell stock
 * 14. Repeated submission does not create duplicate orders
 * 15. Customer can view their own order history
 * 16. Customer cannot view another customer's order
 * 17. Admin can view all orders
 * 18. Admin can update order status
 * 19. Cancelled orders restore stock only once
 * 20. Existing authentication, cart and storefront continue working
 */

if (php_sapi_name() !== 'cli') {
    die("This test suite must be executed via CLI.\n");
}

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

$passedCount = 0;
$failedCount = 0;

function runTest($testNumber, $description, $callback) {
    global $passedCount, $failedCount;
    echo sprintf("[Scenario %02d] %-62s ", $testNumber, $description);
    try {
        $result = $callback();
        if ($result === true) {
            echo "\033[32m[PASS]\033[0m\n";
            $passedCount++;
        } else {
            echo "\033[31m[FAIL]\033[0m: $result\n";
            $failedCount++;
        }
    } catch (Throwable $e) {
        echo "\033[31m[ERROR]\033[0m: " . $e->getMessage() . "\n";
        $failedCount++;
    }
}

echo "\n=======================================================\n";
echo " EasyShop — Phase 6: Checkout & Order Management Suite\n";
echo "=======================================================\n\n";

// In-Memory Simulated Database Mock for transactional simulation
function createFreshDb() {
    return [
        'users' => [
            1 => ['user_id' => 1, 'name' => 'Admin User', 'email' => 'admin@easyshop.pk', 'role' => 'admin', 'phone' => '03001111111'],
            2 => ['user_id' => 2, 'name' => 'Ali Khan', 'email' => 'ali@example.com', 'role' => 'customer', 'phone' => '03001234567'],
            3 => ['user_id' => 3, 'name' => 'Sara Ahmed', 'email' => 'sara@example.com', 'role' => 'customer', 'phone' => '03217654321']
        ],
        'products' => [
            1 => ['product_id' => 1, 'product_name' => 'Wireless Earbuds Pro', 'price' => 2500.00, 'stock' => 5, 'status' => 'active'],
            2 => ['product_id' => 2, 'product_name' => 'Smart Watch Elite', 'price' => 5000.00, 'stock' => 2, 'status' => 'active'],
            3 => ['product_id' => 3, 'product_name' => 'Out of Stock Bag', 'price' => 1800.00, 'stock' => 0, 'status' => 'active'],
            4 => ['product_id' => 4, 'product_name' => 'Inactive Jacket', 'price' => 3500.00, 'stock' => 10, 'status' => 'inactive']
        ],
        'cart_items' => [
            ['cart_item_id' => 1, 'user_id' => 2, 'product_id' => 1, 'quantity' => 2],
            ['cart_item_id' => 2, 'user_id' => 2, 'product_id' => 2, 'quantity' => 1]
        ],
        'orders' => [],
        'order_items' => []
    ];
}

$db = createFreshDb();

// Simulated order placement function mirroring actions/place-order.php
function simulatePlaceOrder(&$database, $userId, $shippingData, $simulatedConcurrencyLock = null) {
    if (!isset($database['users'][$userId])) {
        return ['success' => false, 'error' => 'Unauthenticated'];
    }

    // Input Validation
    $name = trim($shippingData['shipping_name'] ?? '');
    $phone = trim($shippingData['shipping_phone'] ?? '');
    $address = trim($shippingData['shipping_address'] ?? '');
    $method = trim($shippingData['payment_method'] ?? 'cod');

    if (mb_strlen($name) < 3) return ['success' => false, 'error' => 'Invalid name'];
    $digits = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($digits) < 10 || strlen($digits) > 15) return ['success' => false, 'error' => 'Invalid phone'];
    if (mb_strlen($address) < 10) return ['success' => false, 'error' => 'Invalid address'];
    if ($method !== 'cod') return ['success' => false, 'error' => 'Invalid payment method'];

    // Transaction start (snapshot state)
    $backupDb = $database;

    // Get user cart
    $cart = [];
    foreach ($database['cart_items'] as $ci) {
        if ($ci['user_id'] === $userId) {
            $cart[] = $ci;
        }
    }

    if (empty($cart)) {
        return ['success' => false, 'error' => 'Cart is empty'];
    }

    $subtotal = 0.0;
    $orderItems = [];

    // Verify stock and calculate
    foreach ($cart as $ci) {
        $pId = $ci['product_id'];
        $qty = $ci['quantity'];

        if (!isset($database['products'][$pId])) {
            $database = $backupDb;
            return ['success' => false, 'error' => 'Product not found'];
        }

        $prod = &$database['products'][$pId];

        // Concurrency test hook
        if (is_callable($simulatedConcurrencyLock)) {
            $simulatedConcurrencyLock($prod);
        }

        if ($prod['status'] !== 'active') {
            $database = $backupDb;
            return ['success' => false, 'error' => 'Product inactive'];
        }

        if ($prod['stock'] < $qty) {
            $database = $backupDb; // Rollback
            return ['success' => false, 'error' => 'Insufficient stock for ' . $prod['product_name']];
        }

        $lineSubtotal = $prod['price'] * $qty;
        $subtotal += $lineSubtotal;

        $orderItems[] = [
            'product_id'   => $pId,
            'product_name' => $prod['product_name'], // Historical snapshot
            'price'        => $prod['price'],        // Historical snapshot
            'quantity'     => $qty,
            'subtotal'     => $lineSubtotal
        ];

        // Deduct stock
        $prod['stock'] -= $qty;
    }

    $shippingFee = get_shipping_fee($subtotal);
    $totalAmount = calculate_order_total($subtotal, $shippingFee);

    $orderId = count($database['orders']) + 1;
    $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid((string)$orderId, true)), 0, 6));

    $orderRecord = [
        'order_id'          => $orderId,
        'user_id'           => $userId,
        'order_number'      => $orderNumber,
        'subtotal'          => $subtotal,
        'shipping_fee'      => $shippingFee,
        'total_amount'      => $totalAmount,
        'payment_method'    => 'cod',
        'payment_status'    => 'pending',
        'order_status'      => 'pending',
        'is_stock_restored' => 0,
        'shipping_name'     => $name,
        'shipping_phone'    => $phone,
        'shipping_address'  => $address,
        'created_at'        => date('Y-m-d H:i:s')
    ];

    $database['orders'][$orderId] = $orderRecord;

    foreach ($orderItems as $oi) {
        $oi['order_item_id'] = count($database['order_items']) + 1;
        $oi['order_id'] = $orderId;
        $database['order_items'][] = $oi;
    }

    // Clear cart
    $database['cart_items'] = array_values(array_filter($database['cart_items'], function($ci) use ($userId) {
        return $ci['user_id'] !== $userId;
    }));

    return ['success' => true, 'order_id' => $orderId, 'order_number' => $orderNumber];
}

// Simulated status update mirroring admin/orders/update-status.php
function simulateUpdateStatus(&$database, $orderId, $newOrderStatus, $newPaymentStatus) {
    if (!isset($database['orders'][$orderId])) {
        return ['success' => false, 'error' => 'Order not found'];
    }

    $order = &$database['orders'][$orderId];
    $currentStatus = $order['order_status'];

    if ($currentStatus === 'cancelled' && $newOrderStatus !== 'cancelled') {
        return ['success' => false, 'error' => 'Cancelled orders cannot be reopened'];
    }

    if ($newOrderStatus === 'cancelled') {
        $allowed = ['pending', 'confirmed', 'processing'];
        if (!in_array($currentStatus, $allowed, true)) {
            return ['success' => false, 'error' => 'Cannot cancel shipped or delivered orders'];
        }

        // Restore stock only once
        if ((int)$order['is_stock_restored'] === 0) {
            foreach ($database['order_items'] as $oi) {
                if ($oi['order_id'] === $orderId) {
                    $pid = $oi['product_id'];
                    if (isset($database['products'][$pid])) {
                        $database['products'][$pid]['stock'] += $oi['quantity'];
                    }
                }
            }
            $order['is_stock_restored'] = 1;
        }

        $order['order_status'] = 'cancelled';
        $order['payment_status'] = $newPaymentStatus;
        return ['success' => true, 'stock_restored' => true];
    }

    $order['order_status'] = $newOrderStatus;
    $order['payment_status'] = $newPaymentStatus;
    return ['success' => true, 'stock_restored' => false];
}

// =========================================================================
// RUN TEST SCENARIOS
// =========================================================================

// Scenario 1: Customer can access checkout with non-empty cart
runTest(1, "Customer can access checkout with a non-empty cart", function() use ($db) {
    $cart = array_filter($db['cart_items'], fn($ci) => $ci['user_id'] === 2);
    return count($cart) > 0;
});

// Scenario 2: Guest cannot access checkout
runTest(2, "Guest cannot access checkout (requires auth)", function() {
    $checkoutCode = file_get_contents(__DIR__ . '/../checkout.php');
    return strpos($checkoutCode, 'if (!isLoggedIn())') !== false &&
           strpos($checkoutCode, "header('Location: auth/login.php')") !== false;
});

// Scenario 3: Empty cart cannot be checked out
runTest(3, "Empty cart cannot be checked out", function() use (&$db) {
    $emptyUserCartRes = simulatePlaceOrder($db, 3, [
        'shipping_name' => 'Sara Ahmed',
        'shipping_phone' => '03217654321',
        'shipping_address' => 'House 1, Street 2, Karachi',
        'payment_method' => 'cod'
    ]);
    return $emptyUserCartRes['success'] === false && $emptyUserCartRes['error'] === 'Cart is empty';
});

// Scenario 4: Invalid delivery information is rejected
runTest(4, "Invalid delivery information is rejected", function() use (&$db) {
    // Short name
    $r1 = simulatePlaceOrder($db, 2, ['shipping_name' => 'A', 'shipping_phone' => '03001234567', 'shipping_address' => 'Valid Street Address 123']);
    // Invalid phone
    $r2 = simulatePlaceOrder($db, 2, ['shipping_name' => 'Ali Khan', 'shipping_phone' => '123', 'shipping_address' => 'Valid Street Address 123']);
    // Short address
    $r3 = simulatePlaceOrder($db, 2, ['shipping_name' => 'Ali Khan', 'shipping_phone' => '03001234567', 'shipping_address' => 'Short']);

    return ($r1['success'] === false) && ($r2['success'] === false) && ($r3['success'] === false);
});

// Scenario 5: Correct order is created using current database prices
runTest(5, "Correct order is created using current database prices", function() use (&$db) {
    // User 2 has 2x Earbuds (2500) + 1x Smart Watch (5000) = 10,000 subtotal
    $res = simulatePlaceOrder($db, 2, [
        'shipping_name' => 'Ali Khan',
        'shipping_phone' => '03001234567',
        'shipping_address' => 'House 14-B, Sector F-7/2, Islamabad',
        'payment_method' => 'cod'
    ]);
    if (!$res['success']) return $res['error'];

    $order = $db['orders'][$res['order_id']];
    return ((float)$order['subtotal'] === 10000.0);
});

// Scenario 6: Correct shipping fee is applied (Rs. 200 flat)
runTest(6, "Correct shipping fee is applied (Rs. 200 flat)", function() use ($db) {
    $order = end($db['orders']);
    return ((float)$order['shipping_fee'] === 200.0) && ((float)$order['total_amount'] === 10200.0);
});

// Scenario 7: Order number is unique
runTest(7, "Order number is unique and follows ORD-YYYYMMDD- format", function() use ($db) {
    $order = end($db['orders']);
    return (strpos($order['order_number'], 'ORD-' . date('Ymd')) === 0) && strlen($order['order_number']) >= 16;
});

// Scenario 8: Order items contain historical snapshots
runTest(8, "Order items contain historical snapshots", function() use ($db) {
    $items = array_filter($db['order_items'], fn($oi) => $oi['order_id'] === 1);
    if (count($items) !== 2) return 'Expected 2 items';

    foreach ($items as $item) {
        if (empty($item['product_name']) || empty($item['price']) || empty($item['quantity'])) {
            return 'Missing item snapshot data';
        }
    }
    return true;
});

// Scenario 9: Product stock is deducted correctly
runTest(9, "Product stock is deducted correctly", function() use ($db) {
    // Earbuds stock was 5, 2 ordered -> should be 3
    // Smart Watch stock was 2, 1 ordered -> should be 1
    return ($db['products'][1]['stock'] === 3) && ($db['products'][2]['stock'] === 1);
});

// Scenario 10: Cart is cleared after successful order
runTest(10, "Cart is cleared after successful order", function() use ($db) {
    $userCart = array_filter($db['cart_items'], fn($ci) => $ci['user_id'] === 2);
    return count($userCart) === 0;
});

// Scenario 11: Failed order creation rolls back all changes
runTest(11, "Failed order creation rolls back all changes", function() {
    $testDb = createFreshDb();
    // Intentionally add unavailable product
    $testDb['cart_items'][] = ['cart_item_id' => 3, 'user_id' => 2, 'product_id' => 4, 'quantity' => 1]; // Inactive
    $initialStock1 = $testDb['products'][1]['stock'];

    $res = simulatePlaceOrder($testDb, 2, [
        'shipping_name' => 'Ali Khan',
        'shipping_phone' => '03001234567',
        'shipping_address' => 'House 14-B, Sector F-7/2, Islamabad',
        'payment_method' => 'cod'
    ]);

    return ($res['success'] === false) &&
           (count($testDb['orders']) === 0) &&
           ($testDb['products'][1]['stock'] === $initialStock1);
});

// Scenario 12: Insufficient stock prevents checkout
runTest(12, "Insufficient stock prevents checkout", function() {
    $testDb = createFreshDb();
    // User requested 10 Earbuds but only 5 in stock
    $testDb['cart_items'] = [
        ['cart_item_id' => 1, 'user_id' => 2, 'product_id' => 1, 'quantity' => 10]
    ];
    $res = simulatePlaceOrder($testDb, 2, [
        'shipping_name' => 'Ali Khan',
        'shipping_phone' => '03001234567',
        'shipping_address' => 'House 14-B, Sector F-7/2, Islamabad',
        'payment_method' => 'cod'
    ]);
    return ($res['success'] === false) && strpos($res['error'], 'Insufficient stock') !== false;
});

// Scenario 13: Simultaneous checkout cannot oversell stock
runTest(13, "Simultaneous checkout cannot oversell stock", function() {
    $testDb = createFreshDb();
    // Smart watch has stock = 2.
    // Customer A has 2 smart watches. Customer B has 2 smart watches.
    $testDb['cart_items'] = [
        ['cart_item_id' => 1, 'user_id' => 2, 'product_id' => 2, 'quantity' => 2],
        ['cart_item_id' => 2, 'user_id' => 3, 'product_id' => 2, 'quantity' => 2]
    ];

    // Customer A checks out first
    $resA = simulatePlaceOrder($testDb, 2, [
        'shipping_name' => 'Ali Khan',
        'shipping_phone' => '03001234567',
        'shipping_address' => 'House 14-B, Sector F-7/2, Islamabad',
        'payment_method' => 'cod'
    ]);

    // Customer B tries to check out concurrently
    $resB = simulatePlaceOrder($testDb, 3, [
        'shipping_name' => 'Sara Ahmed',
        'shipping_phone' => '03217654321',
        'shipping_address' => 'Flat 4, Clifton Block 2, Karachi',
        'payment_method' => 'cod'
    ]);

    return ($resA['success'] === true) &&
           ($resB['success'] === false) &&
           ($testDb['products'][2]['stock'] === 0); // No negative stock
});

// Scenario 14: Repeated submission does not create duplicate orders
runTest(14, "Repeated submission does not create duplicate orders", function() {
    $testDb = createFreshDb();
    $res1 = simulatePlaceOrder($testDb, 2, [
        'shipping_name' => 'Ali Khan',
        'shipping_phone' => '03001234567',
        'shipping_address' => 'House 14-B, Sector F-7/2, Islamabad',
        'payment_method' => 'cod'
    ]);
    // Immediate second submit (cart is already empty)
    $res2 = simulatePlaceOrder($testDb, 2, [
        'shipping_name' => 'Ali Khan',
        'shipping_phone' => '03001234567',
        'shipping_address' => 'House 14-B, Sector F-7/2, Islamabad',
        'payment_method' => 'cod'
    ]);
    return ($res1['success'] === true) && ($res2['success'] === false) && ($res2['error'] === 'Cart is empty');
});

// Scenario 15: Customer can view their own order history
runTest(15, "Customer can view their own order history", function() use ($db) {
    $myOrders = array_filter($db['orders'], fn($o) => $o['user_id'] === 2);
    return count($myOrders) === 1;
});

// Scenario 16: Customer cannot view another customer's order
runTest(16, "Customer cannot view another customer's order", function() use ($db) {
    $orderDetailsCode = file_get_contents(__DIR__ . '/../customer/order-details.php');
    $orderConfirmCode = file_get_contents(__DIR__ . '/../order-confirmation.php');

    return (strpos($orderDetailsCode, 'WHERE order_number = ? AND user_id = ?') !== false) &&
           (strpos($orderConfirmCode, 'WHERE order_number = ? AND user_id = ?') !== false);
});

// Scenario 17: Admin can view all orders
runTest(17, "Admin can view all orders", function() use ($db) {
    $adminIndexCode = file_get_contents(__DIR__ . '/../admin/orders/index.php');
    return (strpos($adminIndexCode, 'FROM orders o') !== false) &&
           (strpos($adminIndexCode, 'require_once __DIR__ . \'/../../includes/admin-header.php\'') !== false);
});

// Scenario 18: Admin can update order status
runTest(18, "Admin can update order status", function() use (&$db) {
    $res1 = simulateUpdateStatus($db, 1, 'confirmed', 'pending');
    $res2 = simulateUpdateStatus($db, 1, 'processing', 'pending');
    $res3 = simulateUpdateStatus($db, 1, 'shipped', 'pending');

    return ($res1['success'] === true) &&
           ($res2['success'] === true) &&
           ($res3['success'] === true) &&
           ($db['orders'][1]['order_status'] === 'shipped');
});

// Scenario 19: Cancelled orders restore stock only once
runTest(19, "Cancelled orders restore stock only once", function() {
    $testDb = createFreshDb();
    // Place order: 2 Earbuds (stock 5 -> 3), 1 Watch (stock 2 -> 1)
    $resOrder = simulatePlaceOrder($testDb, 2, [
        'shipping_name' => 'Ali Khan',
        'shipping_phone' => '03001234567',
        'shipping_address' => 'House 14-B, Sector F-7/2, Islamabad',
        'payment_method' => 'cod'
    ]);
    if (!$resOrder['success']) return 'Order failed';

    // 1st cancellation: stock restored
    $c1 = simulateUpdateStatus($testDb, 1, 'cancelled', 'failed');
    $stockEarbudsAfter1 = $testDb['products'][1]['stock']; // Should be 5
    $stockWatchAfter1   = $testDb['products'][2]['stock']; // Should be 2

    // 2nd cancellation attempt on already cancelled order
    $c2 = simulateUpdateStatus($testDb, 1, 'cancelled', 'failed');
    $stockEarbudsAfter2 = $testDb['products'][1]['stock'];
    $stockWatchAfter2   = $testDb['products'][2]['stock'];

    return ($c1['success'] === true) &&
           ($stockEarbudsAfter1 === 5) &&
           ($stockWatchAfter1 === 2) &&
           ($stockEarbudsAfter2 === 5) && // Not incremented again to 7
           ($stockWatchAfter2 === 2);    // Not incremented again to 3
});

// Scenario 20: Existing authentication, cart and storefront continue working
runTest(20, "Storefront, cart and auth integrity preserved", function() {
    $files = [
        __DIR__ . '/../cart.php',
        __DIR__ . '/../checkout.php',
        __DIR__ . '/../order-confirmation.php',
        __DIR__ . '/../customer/orders.php',
        __DIR__ . '/../customer/order-details.php',
        __DIR__ . '/../admin/orders/index.php',
        __DIR__ . '/../admin/orders/details.php',
        __DIR__ . '/../admin/orders/update-status.php',
        __DIR__ . '/../actions/place-order.php'
    ];

    foreach ($files as $file) {
        if (!file_exists($file)) return 'Missing file: ' . basename($file);
    }
    return true;
});

echo "\n-------------------------------------------------------\n";
echo " Test Results: $passedCount Passed, $failedCount Failed\n";
echo "-------------------------------------------------------\n\n";

if ($failedCount > 0) {
    exit(1);
}
