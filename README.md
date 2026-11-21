# BanHang — Premium PHP storefront

## Project layout
This rebuild keeps the existing project folders and entry points:
- `index.php`: storefront front controller
- `pages/header.php`, `pages/main.php`, `pages/footer.php`: storefront shell and page rendering
- `pages/main/`: existing page modules retained for compatibility and staged refactoring
- `admin/index.php`: secure administration front controller
- `admin/modules/`: original admin modules and upload folders retained; direct PHP access is blocked
- `admin/config/`: local database configuration and legacy mysqli bridge
- `css/style.css`, `js/app.js`, `images/`: shared storefront/admin assets
- `database_upgrade.sql`: additive order/settings/login-security tables
- `performance_indexes.sql`: optional indexes; review query plans before running

## Local setup (Windows + XAMPP)
1. Back up the project folder and export the existing `web_mysqli` database.
2. Start Apache and MySQL in XAMPP.
3. Use PHP 8.2+ and enable `pdo_mysql`, `mysqli`, `fileinfo`, and `mbstring`.
4. Import `web_mysqli.sql` only into a new empty database. If your existing `web_mysqli` contains products, customers or orders, do not import the sample dump over it.
5. Copy `admin/config/config.local.example.php` to `admin/config/config.local.php` and set local credentials, or provide DB_HOST, DB_PORT, DB_NAME, DB_USER and DB_PASS environment variables.
6. In phpMyAdmin, select the existing `web_mysqli` database and run `database_upgrade.sql`. The migration adds `v2_*` tables and does not drop or rewrite the legacy tables.
7. Open `http://localhost/<project-folder>/index.php` and `http://localhost/<project-folder>/admin/index.php`.
8. Log in to the admin using the existing admin account. The seeded demo account in a fresh sample dump is `admin` / `123456`; change it before any public deployment.
9. In Admin > Cấu hình, set the real bank BIN/short code, account number and account holder to enable VietQR. Do not commit these details.

## Security and behaviour
- PDO native prepared statements, output escaping, CSRF tokens on state-changing forms, strict HttpOnly/SameSite session cookies and session ID rotation.
- Customer/admin authentication verifies legacy MD5 hashes and upgrades them to PHP password hashes after successful login.
- Login attempts are throttled using hashed identifiers.
- Checkout recalculates prices and checks stock server-side, locks stock rows and writes order items in a database transaction.
- Product/news uploads are MIME-checked, size-limited and use random server-generated filenames.
- Card number/CVV are never collected. Payment choices are COD or bank transfer with VietQR; QR generation is not proof of receipt. Admin must reconcile against the actual bank statement.
- The old database tables and existing uploaded product/news images are retained. New orders are stored in the additive `v2_orders` tables; the original `tbl_cart` history is not deleted.
- This project has not yet passed full integration, load or independent penetration testing. Do not describe it as production-audited until those tests are completed.

## Before deployment
- Configure HTTPS and a least-privilege MySQL account.
- Change seeded credentials and set strong admin passwords.
- Back up and rehearse database restore.
- Test checkout, inventory concurrency, account isolation, upload restrictions, CSRF, mobile layout and all legacy order screens.
- Run `performance_indexes.sql` only after a backup and review existing indexes and EXPLAIN plans.
