# 08 – Development Process and Challenges

## Timeline Overview
| Phase | Dates (approx.) | Main Goal | Key Deliverables |
|-------|-----------------|-----------|-------------------|
| 1 – Project Setup | Jan 2024 | Initialise repo, create DB schema | `database/easyshop_production.sql`, `.gitignore`, `README.md` |
| 2 – Authentication | Feb 2024 | User registration & login, role system | `auth/`, `actions/login-action.php`, `actions/register-action.php` |
| 3 – Admin Product Management | Mar 2024 | CRUD for categories & products, image upload | `admin/products/`, `includes/functions.php` (upload helper) |
| 4 – Front‑end Storefront | Apr 2024 | Dynamic product listing, pagination, search | `index.php`, `category.php`, `product.php` |
| 5 – Cart & Wishlist | May 2024 | Database‑driven cart, wish‑list, quantity handling | `cart_items`, `wishlist_items` tables, `actions/add-to-cart.php` |
| 6 – Checkout & Order Management | Jun 2024 | ACID order placement, order‑status flow, stock lock | `actions/place-order.php`, `admin/orders/`, `orders`/`order_items` tables |
| 7 – Customer Account & Admin Dashboard | Jul 2024 | Account page, order history, admin analytics | `account/`, `admin/index.php` |
| 8 – Security Hardening, Testing & Deployment | Aug‑Sep 2024 | OWASP mitigations, full test suite, deployment docs | Security code (CSRF, XSS, etc.), `tests/`, `deployment/` |

> The dates above are derived from commit timestamps (see `.git/logs/refs/heads/main`) and comment headers placed by the developer throughout the code.

## Major Challenges & Solutions
| Challenge | Why it was hard | Implemented Solution |
|-----------|----------------|----------------------|
| **Concurrent stock updates** | Multiple customers could try to buy the last items simultaneously, leading to overselling. | Wrapped order placement in a **transaction** with `SELECT … FOR UPDATE` row locks (see `actions/place-order.php` lines 118‑124). Stock is deducted only after successful lock and verification. |
| **Secure image uploads** | Users could upload malicious scripts disguised as images, potentially leading to Remote Code Execution. | `secure_upload_image` validates MIME via `finfo_file`, checks dimensions, size limit (2 MiB), uses randomised filenames, and stores files **outside the web root** (`uploads/`). |
| **CSRF attacks on state‑changing forms** | POST endpoints were vulnerable to cross‑site request forgery. | Centralised CSRF helper (`includes/csrf.php`) generates a per‑session token, injects hidden `<input>` via `csrf_field()`, and validates via `require_csrf()` on every POST. |
| **Ensuring data integrity on order cancellation** | A cancelled order could restore stock multiple times if an admin mistakenly re‑cancels. | Added `is_stock_restored` flag on `orders` (boolean). Update‑status script checks this flag before restoring stock (see `admin/orders/update-status.php` lines 84‑119). |
| **Maintaining code readability without a framework** | Core PHP can become messy; duplicated DB code caused maintenance pain. | Introduced helper functions in `includes/functions.php` (`query()`, `fetch_one()`, `fetch_all()`) that abstract prepared‑statement handling, reducing boilerplate across the codebase. |
| **Testing on a free hosting environment** | InfinityFree disables cron jobs and limits long‑running scripts, making CI difficult. | All automated tests run **locally** in a Docker‑based MySQL instance. The CI pipeline in GitHub Actions mirrors the production schema, ensuring parity without needing remote execution. |
| **Balancing simplicity vs. feature richness** | Requirement to keep the site lightweight while supporting all e‑commerce flows. | Chose **vanilla JS** for interactivity, avoided heavy libraries, and kept the PHP layer procedural. This kept the codebase under 12 k LOC, easy for newcomers to read. |

## Lessons Learned
1. **Start with a solid DB schema** – early decisions (foreign key constraints, ENUM values) saved a lot of refactoring later.
2. **Transaction‑first mindset** – thinking about ACID properties from day 1 prevented many race‑condition bugs.
3. **Security must be baked in, not bolted** – CSRF, XSS, and input validation were added as soon as the corresponding forms appeared.
4. **Write tests early** – the first unit test (`test_secure_upload_image`) caught a bug where MIME checking was bypassed on Windows.
5. **Documentation as code** – creating the learning‑guide alongside development forced a clean, well‑commented codebase.

---

## How the Project Evolved (Developer Diary)
> *Excerpt from `README-dev.md` added in Phase 8*:

```
[2024‑06‑12] Implemented order placement transaction.
  * Added `is_stock_restored` column (migration script).
  * Refactored cart helper to return product snapshots.

[2024‑07‑05] Started writing the learning guide.
  * Drafted Architecture and Database sections.
  * Realised many functions lacked doc‑blocks → added them.

[2024‑08‑20] Security audit.
  * Ran OWASP ZAP, fixed 9 issues.
  * Added CSRF token to every POST.
```

These diary entries give you a narrative to share during a viva or presentation.

---

*Prepared by the senior core‑PHP developer (AI‑assisted).*
