# EasyShop — PHP & Backend Code Explanation

This document teaches you how the PHP backend of EasyShop actually works. You will learn fundamental PHP concepts through real code extracted from the project, followed by line-by-line breakdowns of all primary backend functions.

---

## 1. Core PHP Concepts Explained with EasyShop Examples

### A. Variables and Data Types
In PHP, every variable starts with a dollar sign (`$`). PHP is dynamically typed, but EasyShop uses strict type checking and explicit typecasting for safety.

```php
$userId       = (int)$_SESSION['user']['user_id']; // Integer (e.g. 15)
$shippingName = trim($_POST['shipping_name'] ?? ''); // String (e.g. "Ahmed Ali")
$shippingFee  = (float)DEFAULT_SHIPPING_FEE;          // Float / Decimal (e.g. 200.00)
$isDelivered  = ($orderStatus === 'delivered');      // Boolean (true or false)
```

### B. Arrays & Associative Arrays
Arrays hold multiple values. An **Associative Array** uses named keys (strings) instead of numerical indices:

```php
// An associative array representing the logged-in user in $_SESSION['user']
$_SESSION['user'] = [
    'user_id' => 1,
    'name'    => 'Yasir Ismail',
    'email'   => 'admin@easyshop.pk',
    'role'    => 'admin',
    'status'  => 'active'
];

// Accessing values by key:
echo $_SESSION['user']['name']; // Outputs: Yasir Ismail
```

### C. `require_once` vs `include`
- `require_once`: Loads the specified file once. If the file is missing or contains a fatal error, PHP halts execution immediately (`Fatal Error`). Used for critical system files (`config/database.php`, `includes/functions.php`).
- `include`: Loads the file, but if it is missing, PHP outputs a warning and continues executing. Used for optional UI templates.

### D. `$_GET` vs `$_POST`
- `$_GET`: Retrieves parameters from the URL query string (`products.php?category=1&search=watch`). Used for idempotent, non-destructive read operations (searching, filtering, pagination).
- `$_POST`: Transmits payload inside the HTTP request body (`actions/place-order.php`). Values are invisible in the browser URL. Mandatory for state-changing mutations (logging in, adding to cart, placing orders).

### E. Sessions & Cookies
HTTP is a stateless protocol (the server forgets who you are the moment a page finishes loading).
- **Session (`$_SESSION`)**: Data stored on the server's hard disk or memory.
- **Session Cookie (`PHPSESSID`)**: A small unique identifier sent to the browser that matches the user to their server-side session.

### F. Post/Redirect/Get (PRG) Pattern
Whenever a form is submitted, EasyShop never renders HTML directly from the POST handler. Instead, it processes data, sets a flash message, and sends an HTTP redirect header:

```php
header('Location: ../order-confirmation.php?order=' . urlencode($orderNumber));
exit;
```
> [!IMPORTANT]
> Always call `exit;` immediately after `header('Location: ...')`. Otherwise, PHP will continue executing the rest of the script in the background!

---

## 2. Deep Dive: Key Backend Functions

Let us examine the most important functions in `includes/functions.php` and `includes/csrf.php`.

---

### Function 1: `init_session()`
- **File**: [`includes/functions.php`](file:///e:/intenship/easyshop/includes/functions.php#L10-L30)
- **Purpose**: Initializes a hardened user session with secure cookie parameters.
- **Called By**: Automatically called whenever `functions.php` is included.

```php
function init_session() {
    if (session_status() === PHP_SESSION_NONE) {
        // Detect HTTPS (supports direct SSL and Cloudflare/proxy SSL)
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                    || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
                    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
                    || (!empty($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], 'https') !== false);

        // Security-hardened session cookie settings
        session_set_cookie_params([
            'lifetime' => 0,              // Cookie expires when browser closes
            'path'     => '/',            // Available across whole site
            'domain'   => '',             // Current domain
            'secure'   => $isSecure,      // True on HTTPS, false on local HTTP
            'httponly' => true,           // Inaccessible to JavaScript (mitigates XSS)
            'samesite' => 'Lax'           // Defends against cross-site request forgery
        ]);

        session_start();
    }
}
```

#### Line-by-Line Breakdown:
1. `session_status() === PHP_SESSION_NONE`: Checks if a session has already been started. This avoids PHP warnings about duplicate `session_start()`.
2. `$isSecure = ...`: Inspects multiple server headers (`HTTPS`, port 443, reverse proxy headers). If the website is loaded via HTTPS, `$isSecure` becomes `true`.
3. `'lifetime' => 0`: Cookie is cleared when the browser window closes.
4. `'httponly' => true`: **Critical security flag**. Prevents JavaScript code from accessing `document.cookie`. Even if an attacker injects a script, they cannot steal the session cookie!
5. `'samesite' => 'Lax'`: Instructs modern browsers not to send this cookie during cross-site requests.
6. `session_start()`: Starts or resumes the session.

---

### Function 2: `e($data)` (Output Escaping Helper)
- **File**: [`includes/functions.php`](file:///e:/intenship/easyshop/includes/functions.php#L143-L145)
- **Purpose**: Neutralizes Cross-Site Scripting (XSS) by encoding special HTML characters.
- **Called By**: Every view template displaying user-generated or dynamic database content.

```php
function e($data) {
    return htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8');
}
```

#### How it Works:
If a malicious user registers with the name `<script>alert('Hacked')</script>`, calling `e($user['name'])` converts it to:
`&lt;script&gt;alert('Hacked')&lt;/script&gt;`  
The browser displays the text safely on the screen instead of executing it as JavaScript!

---

### Function 3: `setFlash()` and `getFlash()`
- **File**: [`includes/functions.php`](file:///e:/intenship/easyshop/includes/functions.php#L98-L123)
- **Purpose**: Stores one-time notification banners (e.g. "Order placed successfully") across redirects.

```php
function setFlash($type, $message) {
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][$type] = $message;
}

function getFlash($type) {
    if (isset($_SESSION['flash'][$type])) {
        $msg = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]); // Delete immediately so it only shows ONCE!
        return $msg;
    }
    return null;
}
```

#### Practical Flow:
1. In `actions/login-action.php`: User enters bad password → `setFlash('error', 'Invalid email or password.')` → redirects to `auth/login.php`.
2. In `auth/login.php`: Calls `getFlash('error')`. The message is displayed in a red Bootstrap alert and instantly removed from `$_SESSION`. If the user refreshes the page, the alert is gone!

---

### Function 4: `get_user_cart($userId)`
- **File**: [`includes/functions.php`](file:///e:/intenship/easyshop/includes/functions.php#L377-L430)
- **Purpose**: Retrieves all items from `cart_items` for a user, joins them with live product data, validates stock, and calculates subtotal.

```php
function get_user_cart($userId = null) {
    global $conn;
    if ($userId === null) {
        if (!isLoggedIn()) return ['items' => [], 'subtotal' => 0.0, 'total_qty' => 0];
        $userId = (int)$_SESSION['user']['user_id'];
    }

    $result = ['items' => [], 'subtotal' => 0.0, 'total_qty' => 0];
    if (!$conn) return $result;

    $sql = "SELECT c.cart_item_id, c.user_id, c.product_id, c.quantity,
                   p.product_name, p.slug, p.sku, p.price, p.stock, p.image, p.status,
                   cat.category_name,
                   (p.price * c.quantity) AS line_subtotal
            FROM cart_items c
            INNER JOIN products p ON c.product_id = p.product_id
            LEFT JOIN categories cat ON p.category_id = cat.category_id
            WHERE c.user_id = ?
            ORDER BY c.cart_item_id DESC";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($res)) {
        if ($row['status'] === 'active' && (int)$row['stock'] > 0) {
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
```

#### Why this function is brilliant:
- Notice `min((int)$row['quantity'], (int)$row['stock'])`: If a customer added 5 units to their cart last week, but other buyers reduced warehouse stock to 2, the function **clamps** the effective quantity to 2! The customer cannot pay for stock that no longer exists.

---

### Function 5: `secure_upload_image($file, $subDir)`
- **File**: [`includes/functions.php`](file:///e:/intenship/easyshop/includes/functions.php#L219-L285)
- **Purpose**: Uploads and validates administrative product photos using a 4-tier security pipeline.

#### The 4 Verification Steps:
1. **Size Check**: Rejects files larger than 2MB (`$maxSize = 2 * 1024 * 1024`).
2. **True Server-Side MIME Inspection**:
   ```php
   $finfo = finfo_open(FILEINFO_MIME_TYPE);
   $mimeType = finfo_file($finfo, $file['tmp_name']);
   finfo_close($finfo);
   ```
   *Does not trust* the browser-supplied `$_FILES['image']['type']` (which is easily forged).
3. **Image Dimension & Bitmap Check**:
   ```php
   $imageInfo = @getimagesize($file['tmp_name']);
   if ($imageInfo === false || $imageInfo[0] <= 0 || $imageInfo[1] <= 0) {
       return ['success' => false, 'error' => 'The uploaded file is not a valid image.'];
   }
   ```
   Rejects PHP scripts disguised with `.jpg` extensions.
4. **Collision-Resistant Random Filename**:
   ```php
   $uniqueName = 'prod_' . bin2hex(random_bytes(10)) . '_' . time() . '.' . $extension;
   ```
   Prevents file overwrite and directory traversal attacks.

---

### Function 6: `validate_csrf($submittedToken)`
- **File**: [`includes/csrf.php`](file:///e:/intenship/easyshop/includes/csrf.php#L42-L54)
- **Purpose**: Validates submitted form token against session token using timing-safe comparison.

```php
function validate_csrf($submittedToken = null) {
    init_session();
    if ($submittedToken === null) {
        $submittedToken = $_POST['csrf_token'] ?? '';
    }
    if (empty($_SESSION['csrf_token']) || empty($submittedToken)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $submittedToken);
}
```
- `hash_equals()` compares two strings in **constant time**. Regular string comparison (`===`) stops comparing at the first mismatched character, which allows advanced attackers to measure microsecond response variations to guess tokens (timing attack). `hash_equals()` eliminates this vulnerability completely.

---

Proceed to **[`05-Feature-Workflows.md`](file:///e:/intenship/easyshop/documentation/learning-guide/05-Feature-Workflows.md)** to trace every customer and admin feature from button click to database update.
