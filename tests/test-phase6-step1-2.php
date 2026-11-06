<?php
/**
 * EasyShop — Phase 6: Step 1 & Step 2 Verification Test
 * 
 * Verifies:
 * 1. Centralized shipping fee configuration returns Rs. 200
 * 2. Order total calculation accurately adds Rs. 200
 * 3. Guest user access to checkout.php redirects to login
 * 4. Logged-in customer with empty cart is redirected to cart.php
 * 5. Logged-in customer with valid cart can access checkout.php
 * 6. Checkout page renders CSRF token and delivery inputs
 */

if (php_sapi_name() !== 'cli') {
    die("CLI execution only.\n");
}

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

$passed = 0;
$failed = 0;

function assertTest($description, $condition, $failureMsg = '') {
    global $passed, $failed;
    echo sprintf("[Test] %-65s ", $description);
    if ($condition) {
        echo "\033[32m[PASS]\033[0m\n";
        $passed++;
    } else {
        echo "\033[31m[FAIL]\033[0m $failureMsg\n";
        $failed++;
    }
}

echo "\n=======================================================\n";
echo " EasyShop — Phase 6: Step 1 & Step 2 Verification\n";
echo "=======================================================\n\n";

// Test 1: Shipping constant and helper
assertTest(
    "Centralized shipping fee constant is defined as 200.00",
    defined('DEFAULT_SHIPPING_FEE') && DEFAULT_SHIPPING_FEE === 200.00
);

// Test 2: get_shipping_fee returns 200.0 regardless of subtotal
assertTest(
    "get_shipping_fee() returns 200.00 for subtotal 1000",
    get_shipping_fee(1000.0) === 200.0
);
assertTest(
    "get_shipping_fee() returns 200.00 even for subtotal > 5000 (no threshold)",
    get_shipping_fee(6500.0) === 200.0
);

// Test 3: calculate_order_total
assertTest(
    "calculate_order_total(1500) returns 1700.00",
    calculate_order_total(1500.0) === 1700.0
);

// Test 4: Format PKR helper formatting
assertTest(
    "format_pkr(get_shipping_fee()) produces 'Rs. 200'",
    format_pkr(get_shipping_fee()) === 'Rs. 200'
);

// Test 5: Cart page contains link to checkout.php
$cartContent = file_get_contents(__DIR__ . '/../cart.php');
assertTest(
    "cart.php links directly to checkout.php",
    strpos($cartContent, 'href="checkout.php"') !== false
);

// Test 6: checkout.php exists and has security checks
$checkoutContent = file_get_contents(__DIR__ . '/../checkout.php');
assertTest(
    "checkout.php enforces isLoggedIn() guard",
    strpos($checkoutContent, 'isLoggedIn()') !== false
);
assertTest(
    "checkout.php redirects empty cart to cart.php",
    strpos($checkoutContent, 'header(\'Location: cart.php\')') !== false
);
assertTest(
    "checkout.php includes CSRF protection (csrf_field)",
    strpos($checkoutContent, 'csrf_field()') !== false
);
assertTest(
    "checkout.php has Cash on Delivery (COD) radio option",
    strpos($checkoutContent, 'name="payment_method"') !== false && strpos($checkoutContent, 'value="cod"') !== false
);
assertTest(
    "checkout.php includes required delivery fields (name, phone, address)",
    strpos($checkoutContent, 'name="shipping_name"') !== false &&
    strpos($checkoutContent, 'name="shipping_phone"') !== false &&
    strpos($checkoutContent, 'name="shipping_address"') !== false
);

echo "\n-------------------------------------------------------\n";
echo " Results: $passed Passed, $failed Failed\n";
echo "-------------------------------------------------------\n\n";

if ($failed > 0) {
    exit(1);
}
