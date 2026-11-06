# 12 – Project Demonstration Script

**Audience**: Professors, classmates, or potential employers who want a quick, polished walkthrough of EasyShop.

**Length**: 5‑10 minutes (≈600 seconds). Adjust speed according to audience interest.

---

## Slide 1 – Title & Context (30 s)
- **Slide content**: "EasyShop – End‑to‑End E‑commerce Application"
- Mention:
  - Single‑vendor shop for Pakistan.
  - Built with **Core PHP**, **MySQLi**, **Bootstrap 5**, **Vanilla JS**.
  - Hosted on **InfinityFree** (free LAMP).
- Show the **project logo** (if any) and a screenshot of the home page.

## Slide 2 – Architecture Overview (45 s)
- Display the **folder‑structure diagram** from *02‑Architecture‑and‑Folder‑Structure.md* (Mermaid flowchart).
- Highlight the three main layers:
  1. **Presentation** – HTML + Bootstrap + JS.
  2. **Application** – `includes/`, `actions/`, `admin/`.
  3. **Data** – MySQL schema (`database/easyshop_production.sql`).
- Point out the **entry point** (`index.php`) and the **router‑less** design (each page is a separate PHP file).

## Slide 3 – Core Features (1 min)
| Feature | Where it lives | Demo cue |
|--------|----------------|----------|
| User Registration / Login | `auth/` & `actions/login-action.php` | Register a new user, then log in. |
| Product Management (Admin) | `admin/products/` | Add a new product with image upload. |
| Shopping Cart | `cart_items` table, `actions/add-to-cart.php` | Add a product to the cart, change quantity. |
| Wishlist | `wishlist_items` | Add/remove an item, show the wishlist page. |
| Checkout & Order Placement | `actions/place-order.php` | Fill the cart, proceed to checkout, complete order. |
| Order History (Customer) | `account/orders.php` | View the newly created order. |
| Admin Order Management | `admin/orders/` | Change order status, demonstrate cancellation with stock restoration. |

## Slide 4 – Checkout Flow Deep‑Dive (1 min 30 s)
1. **Cart retrieval** – `get_user_cart()` (lines 295‑430 in `includes/functions.php`).
2. **Transaction start** – `mysqli_begin_transaction()` (line 87 in `place-order.php`).
3. **Row lock** – `SELECT … FOR UPDATE` (lines 118‑124). Guarantees serialisable stock updates.
4. **Price snapshot** – price taken from the locked row before deduction.
5. **Insert order & items** – uses prepared statements, stores historical price.
6. **Stock deduction** – `UPDATE products SET stock = stock - ?`.
7. **Commit / Rollback** – ACID guarantee; if any step fails the whole order is aborted.

Show the **SQL log** in the browser console (Network → place-order.php) and point to the **order_success.php** page with the order number.

## Slide 5 – Security Highlights (1 min)
- **SQL Injection** – prepared statements everywhere (see `06‑Security‑Explained.md`).
- **XSS** – `e()` helper (`includes/functions.php` line 143).
- **CSRF** – token generation (`includes/csrf.php` line 17) and validation (`require_csrf()`).
- **Session hardening** – Secure, HttpOnly, SameSite Lax flags (`init_session`).
- **File upload hardening** – MIME check, size limit, random filename (`secure_upload_image`).
- **Concurrency** – transactions + `FOR UPDATE`.
- Mention the **audit checklist** at the end of the security file.

## Slide 6 – Admin Dashboard & Analytics (45 s)
- Open `admin/index.php`.
- Show three cards: **Products**, **Categories**, **Customers**.
- Highlight the **Total Revenue** card (added in Exercise 4 of the hands‑on chapter).
- Explain the underlying queries (aggregate SELECTs) and how they help business decisions.

## Slide 7 – Testing & CI (30 s)
- Open `tests/test-phase8.php` – 30 tests, all PASS.
- Show the **GitHub Actions** workflow (`.github/workflows/php.yml`).
- Emphasise the use of a **dedicated test database** and the CI pipeline that runs on each push.

## Slide 8 – Deployment (45 s)
- Summarise the steps from *09‑Deployment‑Guide.md*:
  1. Clean repo, create `.env`.
  2. Upload files to `htdocs/` on InfinityFree.
  3. Import `easyshop_production.sql` via phpMyAdmin.
  4. Set correct permissions for `uploads/`.
  5. Enable HTTPS, add `.htaccess` security headers.
- Show a screenshot of the live site (if deployed) and the HTTPS lock icon.

## Slide 9 – Hands‑on Exercise Showcase (1 min)
- Pick **Exercise 4 – Dashboard Total Revenue** as a quick win.
- Open a Git branch, edit `admin/index.php`, refresh the page, and show the new card.
- Mention that each exercise in *10‑Hands‑on‑Practice.md* reinforces a real‑world skill.

## Slide 10 – Lessons Learned & Future Roadmap (45 s)
- **Lessons** – ACID transactions, CSRF, modular functions, testing discipline.
- **Future enhancements**:
  - Payment gateway integration (Stripe, local bank APIs).
  - 2‑FA for admin accounts.
  - Rate‑limiting and CAPTCHA on login.
  - Containerisation (Docker) for easier staging.
  - Migration to a modern framework (Laravel) for scalability.

## Closing (15 s)
- Recap the **full stack** covered.
- Invite questions.
- Provide the **GitHub repository URL** (if public) and a QR code linking to the code.

---

### Presenter Tips
- Keep the browser tabs **pre‑opened** to avoid delays.
- Use the **browser back button** to demonstrate navigation (e.g., from product page to cart).
- If the demo environment is slow, **pause** and explain what should happen.
- Have a **fallback GIF** or static screenshots in case of a network hiccup.

---

*Prepared by the senior core‑PHP developer (AI‑assisted).*
