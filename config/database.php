<?php
/**
 * EasyShop — MySQLi Database Connection
 * 
 * Secure connection wrapper using PHP MySQLi extension.
 * Supports local configuration overrides via 'config/database.local.php'
 * and handles connection errors without leaking sensitive credentials.
 */

// Prevent direct script execution if accessed via browser outside application context
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    http_response_code(403);
    exit('Direct access forbidden.');
}

// 1. Load local credentials override if present (ignored by Git)
$localConfigFile = __DIR__ . '/database.local.php';
if (file_exists($localConfigFile)) {
    require_once $localConfigFile;
}

// 2. Define default fallback settings if not already defined
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'easyshop_db');
if (!defined('DB_PORT')) define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
if (!defined('APP_ENV')) define('APP_ENV', getenv('APP_ENV') ?: 'development');

// Suppress public error display in production
if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}

// 3. Configure MySQLi error reporting
// We disable automatic exception throwing on connection failures
// so we can catch and display sanitized user-friendly error messages
// rather than exposing database passwords in PHP fatal error stack traces.
mysqli_report(MYSQLI_REPORT_OFF);

// 4. Establish the database connection
$conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// 5. Connection Error Handling
if (!$conn) {
    $errorNumber  = mysqli_connect_errno();
    $errorMessage = mysqli_connect_error();

    // Log the detailed error to the server error log for debugging
    error_log(sprintf(
        "[EasyShop DB Error] Code %d: %s | Host: %s | Database: %s",
        $errorNumber,
        $errorMessage,
        DB_HOST,
        DB_NAME
    ));

    // Store error context for testing pages (sanitized, no credentials)
    $db_connection_error = [
        'code'    => $errorNumber,
        'message' => (APP_ENV === 'development') ? $errorMessage : 'Database service currently unavailable. Please try again later.'
    ];
} else {
    // 6. Set utf8mb4 encoding for full Unicode compatibility (emojis, Urdu, etc.)
    if (!mysqli_set_charset($conn, 'utf8mb4')) {
        error_log("[EasyShop DB Error] Failed to set character set utf8mb4: " . mysqli_error($conn));
    }

    $db_connection_error = null;
}

/**
 * Helper function to retrieve the active MySQLi connection.
 * 
 * @return mysqli|null
 */
function get_db_connection() {
    global $conn;
    return $conn;
}
