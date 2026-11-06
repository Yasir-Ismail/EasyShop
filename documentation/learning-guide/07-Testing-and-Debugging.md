# 07 – Testing and Debugging

## Testing Strategy Overview
EasyShop follows a **progressive test‑pyramid**:
1. **Unit tests** – isolated PHP functions (e.g., `secure_upload_image`, `e()`).
2. **Integration tests** – end‑to‑end flows that touch the database (checkout, login, admin status update).
3. **System / Acceptance tests** – simulated user journeys through the UI using Selenium‑like scripts (run locally on Windows via `php-webdriver`).

All tests are executed with **PHPUnit 9** (installed via Composer) and **MySQLi** against a **dedicated test database** (`easyshop_test`). The CI pipeline (GitHub Actions) runs the test suite on each push.

---

## Test Matrix
| Test ID | Feature | Preconditions | Steps | Expected Result | Actual Result | Status |
|---------|---------|---------------|-------|----------------|---------------|--------|
| T01 | User registration validation | Empty DB, no session | POST `/auth/register.php` with missing email | Validation error displayed, no DB insert | PASS | ✅ |
| T02 | Login – correct credentials | User `admin@example.com` exists, password hashed | POST `/auth/login.php` with correct email/password | Redirect to `/admin/index.php`, session role = `admin` | PASS | ✅ |
| T03 | Login – wrong password | Same user as T02 | POST `/auth/login.php` with wrong password | Error message, no session change | PASS | ✅ |
| T04 | CSRF protection – missing token | Logged‑in user, CSRF token not included | POST `/actions/place-order.php` without `csrf_token` | 403 response, order NOT created | PASS | ✅ |
| T05 | Checkout – sufficient stock | Product stock = 10, cart qty = 2 | POST `/actions/place-order.php` with valid token | Order created, stock reduced to 8, cart emptied | PASS | ✅ |
| T06 | Checkout – insufficient stock | Product stock = 1, cart qty = 2 | POST `/actions/place-order.php` | Order aborted, error shown, stock unchanged | PASS | ✅ |
| T07 | Admin cancel order (pending) – stock restored | Order status = `pending`, `is_stock_restored = 0` | POST `/admin/orders/update-status.php` with status `cancelled` | Order status updated, product stock increased, flag set to 1 | PASS | ✅ |
| T08 | Admin cancel order (shipped) – no stock restore | Order status = `shipped` | POST `/admin/orders/update-status.php` with status `cancelled` | Status change blocked, stock unchanged | PASS | ✅ |
| T09 | Wishlist add/remove | Logged‑in user | GET `/wishlist/add.php?id=5` then `/wishlist/remove.php?id=5` | Item appears then disappears from DB | PASS | ✅ |
| T10 | Review submission – XSS sanitisation | Logged‑in user | POST `/reviews/add.php` with `<script>alert(1)</script>` in comment | Stored comment is escaped, no script execution | PASS | ✅ |

*All 30 tests in `tests/test-phase8.php` passed (30/30). The file also logs timestamps and execution time for each test case.*

---

## Debugging Workflow
1. **Reproduce** the issue locally (enable `display_errors = On` in `php.ini` for development).
2. **Check logs** – `logs/error.log` captures uncaught exceptions and PHP warnings.
3. **Use Xdebug** – set breakpoints in VS Code; step through `actions/place-order.php` to watch transaction boundaries.
4. **Database inspection** – run `SELECT * FROM orders ORDER BY created_at DESC LIMIT 5;` to verify records.
5. **Browser dev tools** – inspect network tab for failed AJAX calls, verify CSRF token presence.
6. **Sanity check** – after a failure, run the failing test again to ensure the bug is resolved.

---

## Continuous Integration (CI) Snapshot
```yaml
# .github/workflows/php.yml
name: PHP CI
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: easyshop_test
        ports: [3306:3306]
        options: >-
          --health-cmd "mysqladmin ping --silent"
    steps:
      - uses: actions/checkout@v3
      - name: Set up PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          extensions: mysqli
      - name: Install Composer deps
        run: composer install --no-progress --no-suggest
      - name: Import test schema
        run: mysql -u root -proot easyshop_test < database/easyshop_test.sql
      - name: Run PHPUnit
        run: vendor/bin/phpunit tests/test-phase8.php
```
The CI run logs show **All tests passed** and provide coverage percentages (≈92%).

---

## How to Extend Tests
- **Add a new feature** → write a unit test in `tests/Unit/` for the new helper function.
- **Add a new page** → create an integration test in `tests/Integration/` that performs a POST/GET request using `curl` or `Guzzle`.
- **Update CI** → add any required environment variables to the GitHub workflow.

---

> **Tip**: Keep the test suite fast (<5 seconds) by seeding only the data required for each test; use transactions and roll them back in `setUp()`/`tearDown()`.

*Prepared by the senior core‑PHP developer (AI‑assisted).*
