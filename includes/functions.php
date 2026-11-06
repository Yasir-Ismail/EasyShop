<?php
/**
 * EasyShop — Core Helper Functions & Session Management
 * 
 * Provides centralized session configuration, role-based access control (RBAC),
 * input sanitization, and flash message management.
 */

// 1. Safe Session Initialization
function init_session() {
    if (session_status() === PHP_SESSION_NONE) {
        // Detect if request is using HTTPS (supports direct SSL and proxy/Cloudflare SSL)
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                    || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
                    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
                    || (!empty($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], 'https') !== false);

        // Security-hardened session cookie settings
        session_set_cookie_params([
            'lifetime' => 0,              // Valid until browser is closed
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isSecure,      // True on HTTPS
            'httponly' => true,           // Inaccessible to JavaScript (mitigates XSS cookie theft)
            'samesite' => 'Lax'           // Protects against cross-site request forgery
        ]);

        session_start();
    }
}

// Automatically initialize session whenever this helper is included
init_session();

// 2. Authentication & Role Check Helpers

/**
 * Checks if a user is currently logged in.
 *
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['user_id']);
}

/**
 * Checks if the logged-in user has the 'admin' role.
 *
 * @return bool
 */
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';
}

/**
 * Returns current authenticated user array or null.
 *
 * @return array|null
 */
function currentUser() {
    return isLoggedIn() ? $_SESSION['user'] : null;
}

/**
 * Guards customer/authenticated routes.
 * Redirects unauthenticated visitors to login with an alert.
 */
function requireLogin() {
    if (!isLoggedIn()) {
        setFlash('error', 'Please log in to access this page.');
        header('Location: ../auth/login.php');
        exit;
    }
}

/**
 * Guards administrative routes.
 * Enforces both authentication AND admin role check.
 */
function requireAdmin() {
    if (!isLoggedIn()) {
        setFlash('error', 'Administrator access required. Please log in.');
        header('Location: ../auth/login.php');
        exit;
    }

    if (!isAdmin()) {
        setFlash('error', 'Access Denied: You do not have administrator permissions.');
        header('Location: ../customer/dashboard.php');
        exit;
    }
}

// 3. Flash Messaging System
// Stores messages in session that are deleted immediately after reading once

/**
 * Sets a flash notification message.
 *
 * @param string $type ('success', 'error', 'info', 'warning')
 * @param string $message
 */
function setFlash($type, $message) {
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][$type] = $message;
}

/**
 * Retrieves and clears a flash notification message.
 *
 * @param string $type
 * @return string|null
 */
function getFlash($type) {
    if (isset($_SESSION['flash'][$type])) {
        $msg = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $msg;
    }
    return null;
}

/**
 * Checks if a flash message of given type exists.
 *
 * @param string $type
 * @return bool
 */
function hasFlash($type) {
    return isset($_SESSION['flash'][$type]);
}

// 4. Output Escaping & Input Sanitization

/**
 * Sanitizes output string to prevent Cross-Site Scripting (XSS).
 *
 * @param string|null $data
 * @return string
 */
function e($data) {
    return htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8');
}

// 5. E-Commerce & File Helpers (Phase 3)

/**
 * Converts a text string into an SEO-friendly URL slug.
 *
 * @param string $text
 * @return string
 */
function slugify($text) {
    // Replace non letter or digits by -
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    // Transliterate
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    // Remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);
    // Trim
    $text = trim($text, '-');
    // Remove duplicate -
    $text = preg_replace('~-+~', '-', $text);
    // Lowercase
    $text = strtolower($text);

    return empty($text) ? 'item-' . time() : $text;
}

/**
 * Formats a decimal amount in Pakistani Rupees (PKR).
 *
 * @param float|int|string $amount
 * @param bool $showDecimals
 * @return string
 */
function format_pkr($amount, $showDecimals = false) {
    $val = (float)$amount;
    return 'Rs. ' . number_format($val, $showDecimals ? 2 : 0);
}

/**
 * Resolves a product image relative path, with fallback to default placeholder.
 *
 * @param string|null $path
 * @param string $prefixPath Prefix if called from subdirectories (e.g. '../../')
 * @return string
 */
function get_product_image_url($path, $prefixPath = '') {
    if (empty($path)) {
        return $prefixPath . 'assets/images/product-placeholder.svg';
    }

    // Determine root on disk
    $diskPath = __DIR__ . '/../' . ltrim($path, '/');
    if (file_exists($diskPath)) {
        return $prefixPath . ltrim($path, '/');
    }

    return $prefixPath . 'assets/images/product-placeholder.svg';
}

/**
 * Handles secure file uploads for product photos.
 * 
 * Validates:
 * - Error status
 * - File size (max 2MB)
 * - Server-side MIME type inspection (JPEG, PNG, WebP)
 * - Extension matching MIME type
 * - Randomized unique name to prevent collision and path traversal
 *
 * @param array $file $_FILES['image'] array
 * @param string $subDir Subdirectory inside uploads/
 * @return array ['success' => bool, 'path' => string, 'error' => string]
 */
function secure_upload_image($file, $subDir = 'products') {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => false, 'error' => 'No file was uploaded.'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload failed with error code: ' . $file['error']];
    }

    // 1. Max size: 2MB (suitable for InfinityFree upload limits)
    $maxSize = 2 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'File size exceeds maximum allowed limit of 2MB.'];
    }

    // 2. Server-side MIME type verification using finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    if (!array_key_exists($mimeType, $allowedMimes)) {
        return ['success' => false, 'error' => 'Invalid image format. Only JPG, PNG, and WebP are accepted (detected: ' . $mimeType . ').'];
    }

    $extension = $allowedMimes[$mimeType];
 
    // 2b. Verify image dimensions and decodeability (prevents polyglot malicious files)
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false || $imageInfo[0] <= 0 || $imageInfo[1] <= 0) {
        return ['success' => false, 'error' => 'The uploaded file is not a valid or decodeable image.'];
    }

    // 3. Generate collision-resistant unique filename
    $uniqueName = 'prod_' . bin2hex(random_bytes(10)) . '_' . time() . '.' . $extension;

    // 4. Secure Target Path (normalized, no path traversal)
    $uploadBase = realpath(__DIR__ . '/../uploads');
    if (!$uploadBase) {
        $uploadBase = __DIR__ . '/../uploads';
    }

    $targetDir = $uploadBase . DIRECTORY_SEPARATOR . trim($subDir, '/\\');
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $targetFile = $targetDir . DIRECTORY_SEPARATOR . $uniqueName;

    if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
        return ['success' => false, 'error' => 'Failed to save uploaded file to storage.'];
    }

    // Return the relative path for database storage
    $dbPath = 'uploads/' . trim($subDir, '/') . '/' . $uniqueName;

    return [
        'success' => true,
        'path'    => $dbPath,
        'filename' => $uniqueName
    ];
}

// 6. Cart & Wishlist Helpers (Phase 5)

/**
 * Returns total items quantity in customer's cart.
 *
 * @param int|null $userId
 * @return int
 */
function get_cart_count($userId = null) {
    global $conn;
    if ($userId === null) {
        if (!isLoggedIn()) return 0;
        $userId = (int)$_SESSION['user']['user_id'];
    }

    if (!$conn) return 0;

    $stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE user_id = ?");
    if (!$stmt) return 0;

    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    return (int)$count;
}

/**
 * Returns total items count in customer's wishlist.
 *
 * @param int|null $userId
 * @return int
 */
function get_wishlist_count($userId = null) {
    global $conn;
    if ($userId === null) {
        if (!isLoggedIn()) return 0;
        $userId = (int)$_SESSION['user']['user_id'];
    }

    if (!$conn) return 0;

    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM wishlist_items WHERE user_id = ?");
    if (!$stmt) return 0;

    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    return (int)$count;
}

/**
 * Checks whether a given product is saved in user's wishlist.
 *
 * @param int $productId
 * @param int|null $userId
 * @return bool
 */
function is_in_wishlist($productId, $userId = null) {
    global $conn;
    if ($userId === null) {
        if (!isLoggedIn()) return false;
        $userId = (int)$_SESSION['user']['user_id'];
    }

    if (!$conn || $productId <= 0) return false;

    $stmt = mysqli_prepare($conn, "SELECT wishlist_id FROM wishlist_items WHERE user_id = ? AND product_id = ? LIMIT 1");
    if (!$stmt) return false;

    mysqli_stmt_bind_param($stmt, 'ii', $userId, $productId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $exists = (mysqli_stmt_num_rows($stmt) > 0);
    mysqli_stmt_close($stmt);

    return $exists;
}

/**
 * Retrieves all cart items with live product prices and subtotal for a user.
 *
 * @param int|null $userId
 * @return array ['items' => array, 'subtotal' => float, 'total_qty' => int]
 */
function get_user_cart($userId = null) {
    global $conn;
    if ($userId === null) {
        if (!isLoggedIn()) return ['items' => [], 'subtotal' => 0.0, 'total_qty' => 0];
        $userId = (int)$_SESSION['user']['user_id'];
    }

    $result = [
        'items'     => [],
        'subtotal'  => 0.0,
        'total_qty' => 0
    ];

    if (!$conn) return $result;

    $sql = "SELECT c.cart_item_id, c.user_id, c.product_id, c.quantity,
                   p.product_name, p.slug, p.sku, p.price, p.original_price, p.stock, p.image, p.status,
                   cat.category_name,
                   (p.price * c.quantity) AS line_subtotal
            FROM cart_items c
            INNER JOIN products p ON c.product_id = p.product_id
            LEFT JOIN categories cat ON p.category_id = cat.category_id
            WHERE c.user_id = ?
            ORDER BY c.cart_item_id DESC";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return $result;

    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($res)) {
        // Only calculate subtotal for items that are active and in-stock
        if ($row['status'] === 'active' && (int)$row['stock'] > 0) {
            // If cart quantity exceeds current stock, clamp to stock
            $effectiveQty = min((int)$row['quantity'], (int)$row['stock']);
            $row['effective_quantity'] = $effectiveQty;
            $lineSubtotal = (float)$row['price'] * $effectiveQty;
            $row['line_subtotal'] = $lineSubtotal;

            $result['subtotal'] += $lineSubtotal;
            $result['total_qty'] += $effectiveQty;
        } else {
            $row['effective_quantity'] = (int)$row['quantity'];
            $row['line_subtotal'] = 0.0;
        }

        $result['items'][] = $row;
    }
    mysqli_stmt_close($stmt);

    return $result;
}

// 7. Shipping & Checkout Helpers (Phase 6)

if (!defined('DEFAULT_SHIPPING_FEE')) {
    define('DEFAULT_SHIPPING_FEE', 200.00);
}

/**
 * Calculates shipping fee for order subtotal.
 * Centralized rule: Flat Rs. 200 fee across all orders.
 *
 * @param float|int $subtotal
 * @return float
 */
function get_shipping_fee($subtotal = 0.0) {
    return (float)DEFAULT_SHIPPING_FEE;
}

/**
 * Calculates grand total for order (Subtotal + Shipping Fee).
 *
 * @param float|int $subtotal
 * @param float|int|null $shippingFee
 * @return float
 */
function calculate_order_total($subtotal, $shippingFee = null) {
    if ($shippingFee === null) {
        $shippingFee = get_shipping_fee($subtotal);
    }
    return (float)$subtotal + (float)$shippingFee;
}


