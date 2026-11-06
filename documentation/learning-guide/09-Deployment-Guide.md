# 09 – Deployment Guide

## 1️⃣  Preparing the Application for Production

| Step | Action | Commands / Notes |
|------|--------|------------------|
| 1 | **Clean the repo** – remove development‑only files (`tests/`, `.env.example`, `README-dev.md`). | `git rm -r tests/ README-dev.md` |
| 2 | **Create a production `.env`** (not version‑controlled) with DB credentials, site URL, and optional salts. | ```
DB_HOST=localhost
DB_USER=easyshop_user
DB_PASS=********
DB_NAME=easyshop_prod
BASE_URL=https://yourdomain.com
``` |
| 3 | **Upload source files** – all PHP, HTML, CSS, JS, and the `uploads/` folder (if you have existing product images). Use FTP/SFTP or the **InfinityFree file manager**. Keep the directory structure identical to the repository (`e:/intenship/easyshop/`). |
| 4 | **Import the database** – open phpMyAdmin on InfinityFree, create a new database, then import `database/easyshop_production.sql`. |
| 5 | **Adjust file permissions** – ensure `uploads/` is writable by the web server (chmod 755). |
| 6 | **Set the correct `DOCUMENT_ROOT`** – on InfinityFree the public folder is `htdocs/`. Move the contents of the project root into `htdocs/`. |
| 7 | **Configure `.htaccess`** – add security headers, enable URL rewriting, and block direct script access.

---

## 2️⃣  Recommended `.htaccess` for InfinityFree
```
# --------------------------------------------------
# Basic security headers
# --------------------------------------------------
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "SAMEORIGIN"
Header set X-XSS-Protection "1; mode=block"
# Content‑Security‑Policy – simple example (customise as needed)
Header set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;"

# --------------------------------------------------
# Disable directory listing
# --------------------------------------------------
Options -Indexes

# --------------------------------------------------
# Force HTTPS (InfinityFree provides free SSL on the domain)
# --------------------------------------------------
RewriteEngine On
RewriteCond %{HTTPS} !=on
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# --------------------------------------------------
# Friendly URLs (optional – not required for current routing)
# --------------------------------------------------
#RewriteBase /
#RewriteCond %{REQUEST_FILENAME} !-f
#RewriteCond %{REQUEST_FILENAME} !-d
#RewriteRule ^(.*)$ index.php?route=$1 [L,QSA]
```
*Note*: InfinityFree disables some Apache modules; verify that `mod_headers` is enabled (it usually is). If any directive is rejected, comment it out.

---

## 3️⃣  Environment Variables in Code
The project reads configuration from `$_ENV` (populated by PHP’s `dotenv` library). To keep the code unchanged, create a **real** `.env` file in the project root (outside the web‑visible tree) on the server:
```php
// includes/config.php (already present)
$DB_HOST = $_ENV['DB_HOST'] ?? 'localhost';
$DB_USER = $_ENV['DB_USER'] ?? 'root';
$DB_PASS = $_ENV['DB_PASS'] ?? '';
$DB_NAME = $_ENV['DB_NAME'] ?? 'easyshop';
```
During deployment, copy the `.env` file to the same location.

---

## 4️⃣  Post‑Deployment Checklist
| ✅ | Item |
|----|------|
| ✔ | Database imported **without errors** (run `SELECT COUNT(*) FROM users;`). |
| ✔ | `uploads/` folder is **writable** (try uploading a product image via the admin panel). |
| ✔ | All **CSRF tokens** render correctly on forms. |
| ✔ | Session cookies have `Secure` flag (check via browser dev tools → Application → Cookies). |
| ✔ | No **PHP notices** or **warnings** appear in the page source (enable error logging to a file, not the output). |
| ✔ | SSL certificate is active (`https://yourdomain.com` shows the padlock). |
| ✔ | Search engine robots are allowed (`robots.txt` present, but admin pages are blocked). |

---

## 5️⃣  Common Deployment Issues & Fixes
| Symptom | Likely Cause | Fix |
|---------|--------------|-----|
| `Fatal error: Call to undefined function mysqli_connect()` | MySQLi extension disabled on the host. | Switch to a host that enables MySQLi (InfinityFree does). |
| Images not displaying after upload | `uploads/` not inside the public path or permissions too strict. | Move `uploads/` to `htdocs/uploads/` and set `chmod 755`. |
| 403 Forbidden on admin pages | `.htaccess` missing `AllowOverride All` on the server. | Verify the host allows custom `.htaccess` (InfinityFree does). |
| Session lost after login | `session.save_path` unwritable. | Change `session.save_path` in `php.ini` or ask host support to set a writable directory. |

---

## 6️⃣  Optional – Docker Development Container (for future work)
A lightweight Dockerfile can speed up local development and produce an identical environment for CI:
```Dockerfile
FROM php:8.1-apache
RUN apt-get update && apt-get install -y libpng-dev libjpeg-dev libfreetype6-dev && \
    docker-php-ext-install mysqli pdo pdo_mysql && \
    a2enmod rewrite
COPY ./ /var/www/html/
COPY ./docker/vhost.conf /etc/apache2/sites-available/000-default.conf
WORKDIR /var/www/html
EXPOSE 80
```
The `docker-compose.yml` would spin up MySQL with the same schema. This is **not** required for InfinityFree, but useful if you ever move to a VPS.

---

*Prepared by the senior core‑PHP developer (AI‑assisted).*
