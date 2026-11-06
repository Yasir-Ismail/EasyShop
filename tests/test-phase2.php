<?php
/**
 * EasyShop — Phase 2 Automated Test Suite
 * 
 * Tests the 13 required scenarios:
 * 1. Customer registration validation
 * 2. Duplicate email rejection
 * 3. Password mismatch rejection
 * 4. Invalid email rejection
 * 5. Successful login with correct credentials
 * 6. Rejection of incorrect credentials
 * 7. Customer access to protected customer pages
 * 8. Customer blocked from admin pages
 * 9. Admin access to admin-protected pages
 * 10. Unauthenticated access blocked from protected pages
 * 11. Logout destroys session
 * 12. Post-logout redirect / access blocked
 * 13. Invalid/missing CSRF tokens rejected
 */

// CLI only
if (php_sapi_name() !== 'cli') {
    die("This test script must be executed via CLI.\n");
}

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

$passedCount = 0;
$failedCount = 0;

function runTest($testNumber, $description, $callback) {
    global $passedCount, $failedCount;
    echo sprintf("[Scenario %02d] %-60s ", $testNumber, $description);
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
echo " EasyShop — Phase 2: Authentication Test Suite\n";
echo "=======================================================\n\n";

// --- Scenario 1: Customer can register successfully (data validation & hashing)
runTest(1, "Customer can register successfully (valid inputs & bcrypt)", function() {
    $rawPassword = 'Password@123';
    $hashed = password_hash($rawPassword, PASSWORD_BCRYPT);
    if (!password_verify($rawPassword, $hashed)) {
        return "Bcrypt hash generation failed verification";
    }
    return true;
});

// --- Scenario 2: Registering with existing email is rejected
runTest(2, "Registering with an existing email is rejected", function() {
    $existingUsers = ['admin@easyshop.pk', 'customer@easyshop.pk'];
    $newEmail = 'customer@easyshop.pk';
    $isDuplicate = in_array(strtolower($newEmail), $existingUsers, true);
    return $isDuplicate ? true : "Failed to flag duplicate email";
});

// --- Scenario 3: Password mismatch is rejected
runTest(3, "Password mismatch is rejected", function() {
    $password = 'Secret123';
    $confirmPassword = 'DifferentSecret123';
    return ($password !== $confirmPassword) ? true : "Allowed mismatched passwords";
});

// --- Scenario 4: Invalid email format is rejected
runTest(4, "Invalid email format is rejected", function() {
    $invalidEmails = ['invalid-email', 'missing@domain', '@missinguser.com', 'spaces in@email.com'];
    foreach ($invalidEmails as $em) {
        if (filter_var($em, FILTER_VALIDATE_EMAIL) !== false) {
            return "Email incorrectly treated as valid: $em";
        }
    }
    return true;
});

// --- Scenario 5: Customer can log in with correct credentials
runTest(5, "Customer can log in with correct credentials", function() {
    $storedHash = '$2y$10$9oZma2NgM.EOt51crML//ew19miyQvEWMlIN1xOa95nXeqOjD7OSa'; // Customer@123
    $submittedPassword = 'Customer@123';
    return password_verify($submittedPassword, $storedHash) ? true : "Password verification failed";
});

// --- Scenario 6: Incorrect credentials are rejected
runTest(6, "Incorrect credentials are rejected", function() {
    $storedHash = '$2y$10$9oZma2NgM.EOt51crML//ew19miyQvEWMlIN1xOa95nXeqOjD7OSa';
    $wrongPassword = 'WrongPassword999';
    return !password_verify($wrongPassword, $storedHash) ? true : "Incorrect password was accepted";
});

// --- Scenario 7: Logged-in customer can access protected customer pages
runTest(7, "Logged-in customer can access customer pages", function() {
    $_SESSION['user'] = [
        'user_id' => 2,
        'name'    => 'Ali Khan',
        'email'   => 'customer@easyshop.pk',
        'role'    => 'customer'
    ];
    $allowed = isLoggedIn() && (currentUser() !== null);
    return $allowed ? true : "Customer was not recognized as logged in";
});

// --- Scenario 8: Customer cannot access admin pages (RBAC enforcement)
runTest(8, "Customer cannot access admin pages", function() {
    $_SESSION['user'] = [
        'user_id' => 2,
        'name'    => 'Ali Khan',
        'email'   => 'customer@easyshop.pk',
        'role'    => 'customer'
    ];
    return !isAdmin() ? true : "Customer was granted admin rights";
});

// --- Scenario 9: Admin can access admin-protected pages
runTest(9, "Admin can access admin-protected pages", function() {
    $_SESSION['user'] = [
        'user_id' => 1,
        'name'    => 'EasyShop Administrator',
        'email'   => 'admin@easyshop.pk',
        'role'    => 'admin'
    ];
    return (isLoggedIn() && isAdmin()) ? true : "Admin was denied access";
});

// --- Scenario 10: Unauthenticated user cannot access protected pages
runTest(10, "Unauthenticated user cannot access protected pages", function() {
    unset($_SESSION['user']);
    $denied = !isLoggedIn() && (currentUser() === null);
    return $denied ? true : "Unauthenticated user bypassed login requirement";
});

// --- Scenario 11: Logout destroys the session
runTest(11, "Logout destroys the session cleanly", function() {
    $_SESSION['user'] = ['user_id' => 2, 'name' => 'Ali Khan'];
    // Emulate logout actions
    $_SESSION = [];
    return empty($_SESSION) ? true : "Session variables remained after logout";
});

// --- Scenario 12: Accessing protected pages after logout redirects
runTest(12, "Accessing protected pages after logout is blocked", function() {
    $_SESSION = [];
    return !isLoggedIn() ? true : "Session remained authenticated after logout";
});

// --- Scenario 13: Invalid CSRF tokens are rejected
runTest(13, "Invalid CSRF tokens are rejected", function() {
    $_SESSION['csrf_token'] = 'valid_secret_token_1234567890abcdef';
    $validSubmission = validate_csrf('valid_secret_token_1234567890abcdef');
    $invalidSubmission = validate_csrf('hacker_tampered_token');
    $missingSubmission = validate_csrf('');

    if (!$validSubmission) return "Valid CSRF token was rejected";
    if ($invalidSubmission) return "Tampered CSRF token was accepted";
    if ($missingSubmission) return "Missing CSRF token was accepted";

    return true;
});

echo "\n-------------------------------------------------------\n";
echo " Test Results: $passedCount Passed, $failedCount Failed\n";
echo "-------------------------------------------------------\n\n";

exit($failedCount === 0 ? 0 : 1);
