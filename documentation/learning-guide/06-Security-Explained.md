# 06 – Security Explained

## Overview
EasyShop follows a defense‑in‑depth approach and implements many OWASP‑Top‑10 mitigations directly in the PHP code.  Below we detail each protection, the relevant source file(s) and line ranges, and discuss any known limitations.

---

### 1. SQL Injection Prevention
- **Technique**: All database interactions use **prepared statements** (`$stmt = $conn->prepare(...); $stmt->bind_param(...);`).
- **Files**:
  - `includes/functions.php` – helper `query($sql, $params)` (lines 150‑170) – central wrapper for prepared queries.
  - `actions/place-order.php` – inserts order and order items (lines 202‑236) use prepared statements.
  - `admin/orders/update-status.php` – updates order status (lines 84‑119).
- **Why it works**: Parameter values are sent to MySQL separately from the query string, preventing malicious input from being interpreted as SQL commands.

---

### 2. Cross‑Site Scripting (XSS) Mitigation
- **Technique**: All dynamic output is escaped using the `e()` helper (`htmlspecialchars($data, ENT_QUOTES, 'UTF-8')`).
- **File**: `includes/functions.php` – function `e($data)` (lines 143‑145).
- **Usage**: Every template echoes values via `<?= e($variable) ?>`.
- **Limitation**: Developers must remember to use `e()`; raw `echo` of user data still occurs in a few legacy admin pages (e.g., `admin/reviews/list.php` lines 112‑118). These should be refactored.

---

### 3. CSRF Protection
- **Technique**: A per‑session token is generated (`bin2hex(random_bytes(32))`) and stored in `$_SESSION['csrf_token']`. Forms output a hidden field via `csrf_field()` and every POST endpoint validates with `require_csrf()`.
- **Files**:
  - `includes/csrf.php` – token generation (`get_csrf_token` lines 17‑23), hidden field (`csrf_field` 30‑33), validation (`validate_csrf` 42‑54) and guard (`require_csrf` 59‑66).
  - Example usage in `actions/place-order.php` (line 12) and `admin/orders/update-status.php` (line 10).
- **Why it works**: The token is unpredictable and tied to the session; an attacker cannot guess it.
- **Limitation**: The token is not rotated per‑form; a stolen token from an XSS flaw could be reused.

---

### 4. Session Security
- **Settings** in `includes/functions.php` (`init_session` lines 10‑30):
  - `session.cookie_secure` set when `HTTPS` is detected.
  - `session.cookie_httponly = true` prevents JavaScript access.
  - `session.cookie_samesite = 'Lax'` mitigates CSRF via third‑party links.
  - `session_regenerate_id(true)` is called after successful login (`actions/login-action.php` line 78‑79).

---

### 5. File Upload Hardening
- **Function** `secure_upload_image` in `includes/functions.php` (lines 219‑285).
- Checks:
  - MIME type via `finfo_file`.
  - Image dimensions via `getimagesize` (max 2000×2000).
  - Size ≤ 2 MiB.
  - Randomised filename.
- Stores under `uploads/` which is **outside the web root** (`e:/intenship/easyshop/uploads/`).
- **Limitation**: No virus scanning; a malicious image could exploit a viewer library.

---

### 6. IDOR & Authorization Checks
- Every admin‑only page calls `requireAdmin()` (lines 80‑92) which validates `$_SESSION['user_role'] === 'admin'`.
- Customer‑only pages call `requireLogin()` (lines 35‑74).
- Direct object references (e.g., `edit-product.php?id=5`) are validated against ownership and existence before any DB operation.

---

### 7. Business Logic Validation (Price & Stock)
- Prices are **never trusted from the client**. In `actions/place-order.php` (lines 118‑124) the product row is re‑fetched with a `FOR UPDATE` lock and the `price` column is used to compute subtotal.
- Stock is verified against the *current* value inside the same transaction; insufficient stock aborts the order.

---

### 8. Concurrency Control & ACID Guarantees
- Both order placement and admin cancellation wrap critical sections in a MySQL transaction (`mysqli_begin_transaction`) and lock rows with `SELECT ... FOR UPDATE`.
- The `is_stock_restored` flag on `orders` prevents double‑restoration.

---

## Limitations & Future Improvements
| Area | Current Gap | Suggested Fix |
|------|-------------|----------------|
| Content Security Policy | No CSP header set. | Add `Header set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline';"` in `.htaccess`.
| Rate Limiting | No throttling on login or order endpoints. | Implement per‑IP request counters (e.g., using Redis or a DB table).
| Password Policy | Only length enforced. | Enforce complexity, check against breached passwords API.
| 2FA | Not implemented. | Add TOTP via Google Authenticator.
| Input Sanitisation | Server‑side validation exists but client‑side is minimal. | Use HTML5 validation + PHP filters (`filter_var`).
| Dependency Updates | Uses older Bootstrap 5.0. | Upgrade to latest stable version.

---

## Quick Checklist for Auditors
- [ ] All queries use prepared statements.
- [ ] All output escapes via `e()`.
- [ ] CSRF token present on every POST form.
- [ ] Session cookies have `Secure`, `HttpOnly`, `SameSite` flags.
- [ ] File uploads validated for type, size, dimensions.
- [ ] Business logic (price, stock) validated server‑side.
- [ ] Transactions lock rows and use `is_stock_restored` flag.
- [ ] Admin routes protected by `requireAdmin()`.

---

> **Note**: The code excerpts below illustrate the most relevant sections.

```php
// includes/functions.php – init_session (lines 10‑30)
session_set_cookie_params([
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();
```

```php
// includes/csrf.php – token generation (lines 17‑23)
function get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
```

```php
// actions/place-order.php – transaction & stock lock (lines 118‑124)
$stmt = $conn->prepare("SELECT stock FROM products WHERE id = ? FOR UPDATE");
$stmt->bind_param('i', $productId);
$stmt->execute();
```

---

*Prepared by the senior core‑PHP developer (AI‑assisted).*
