# BanHang Premium PHP v2

A non-destructive PHP/MySQL rebuild on branch `redesign/premium-php-v2`.
All legacy source files remain in place while v2 is tested.

## Quick start — Windows + XAMPP

1. Back up your MySQL database (export `web_mysqli`) and project directory before trying the new app.
2. Pull the branch into a **separate worktree or checkout**, not on top of uncommitted local work:
   ```powershell
   cd C:\xampp\htdocs\web_banhang
   git status
   git fetch origin
   git switch redesign/premium-php-v2
   ```
   If that directory is used for production or contains uncommitted work, create another clone/worktree first.
3. Start **Apache and MySQL** in XAMPP. Use PHP **8.2+** with `pdo_mysql`, `fileinfo`, and `mbstring` enabled.
4. Import `web_mysqli.sql` into an empty `web_mysqli` database **only if needed**. If it already contains your shop data, **DO NOT import the sample SQL again**.
5. Run `v2/database_upgrade.sql` against the existing `web_mysqli` database in phpMyAdmin > SQL. This creates only `v2_*` tables.
6. Copy `v2/config.local.example.php` to `v2/config.local.php` and enter the local database credentials. `config.local.php` is git-ignored. Prefer a dedicated least-privilege MySQL user outside localhost.
7. Open `http://localhost/web_banhang/v2/index.php` for the store and `http://localhost/web_banhang/v2/admin.php` for admin. The correct URL depends on your local folder name.
8. In admin > Cấu hình, enter **your real bank ID/BIN, account number, and account holder** to enable VietQR (do not use sample data).
9. Test registration, login, admin, cart, checkout, order review, stock, uploads and order-status updates with test data before handling genuine orders.

## Implemented
- Fresh responsive premium storefront; home, categories, search, sorting/pagination, product detail, cart, customer account/password, checkout (COD or bank transfer), QR instructions, order history/detail, recommendations by category and news.
- Admin login, dashboard, product/category editing, stock, news editing, customers, manual bank-transfer confirmation, order processing, revenue reports and store/bank settings.
- PDO native prepared SQL, escaped output, per-form CSRF, strict and HttpOnly session cookies, session rotation, password_hash/password_verify with **legacy MD5 upgrade after successful password verification**, session-independent hashed login attempt throttling, server-side prices, transactional stock deduction, and guarded image upload.

## Important limitations / remaining hardening
- This is **not a formally security-audited production deployment**. Conduct a security review, automated tests, integration tests and an independent penetration test before public launch.
- The database migration is additive; legacy application files have not been removed or automatically migrated. The old `tbl_cart` / `tbl_cart_details` historical orders are **not** imported into `v2_orders`; v2 reports cover only new v2 orders.
- The homepage product photos come from the legacy repository upload folder; visual banners fall back to existing images. Premium mockups in Figma are design references, not identical pixel-for-pixel assets.
- Password resets by email, two-factor auth, dedicated user consent/preferences, carrier shipping API, automated payment reconciliation, multi-warehouse inventory, variants/capacity-specific pricing, automated analytics data pipelines, real recommendation ML, and automated refunds are **not included**.
- VietQR Quick Link only generates a transfer QR with amount and reference. **It does not verify that money arrived.** Admin must reconcile against the real bank statement; never mark paid based solely on the buyer clicking a button.
- No credit-card entry: never handle raw PAN/CVV on this server; integrate a PCI-compliant hosted payment provider for cards.
- Product pricing currently uses the base price `tbl_sanpham.giasp`. Variant-specific price and inventory require a separate normalized variants table before enablement.
- Admin order state machine is deliberately restrictive. Cancel from pending/confirmed restores stock once; completed/shipping orders cannot silently revert. Paid cancellations require off-system refund handling.
- Harden the hosting setup: HTTPS, proper MySQL access rights, regular backups and restore drills, session/cookie security, firewall, restricted uploads, up-to-date PHP/XAMPP and server-side logging.
- Change default seeded demo passwords and remove demo users before public deployment.
- CSP currently allows Google Fonts and VietQR image origins; review the policy if external services change.
- The v2 folder is isolated; `main` is untouched until you explicitly merge the PR.


## Query performance
For a larger product catalog, review your query plans with `EXPLAIN` and optionally run `v2/performance_indexes.sql` **after a database backup**. The script avoids adding an index with the same name twice. Run it only after checking whether equivalent indexes already exist. Turn on PHP OPcache on production, compress static CSS/JS and use an image optimizer/CDN where appropriate; no throughput or load-test claim is made here.

## Configuration and files

- `v2/app.php`: security, db, shared components
- `v2/index.php`: storefront controller + rendering
- `v2/admin.php`: admin controller + rendering
- `v2/database_upgrade.sql`: schema for orders, items, settings, login attempts
- `v2/assets/site.css`: design system and responsive layout
- `v2/assets/app.js`: enhancements; no authentication or pricing logic in JS
- `v2/config.local.php`: untracked local credentials

## QA checklist

- [ ] PHP lint + browser smoke test on your actual XAMPP PHP version
- [ ] Verify all UI pages at 390px, tablet and 1440px
- [ ] Confirm real SQL column types and session handling on target install
- [ ] Verify legacy MD5 migration for customer and admin
- [ ] Login throttling, CSRF rejection and output encoding
- [ ] Concurrency test: two buyers purchasing last remaining unit
- [ ] Verify customer order isolation (cannot access another customer's order code)
- [ ] Verify admin-only routes, image upload MIME validation and file permission
- [ ] Confirm bank QR with your bank application and manual reconciliation
- [ ] Backup/restore rehearsal; review sensitive data in original SQL/old config

## Documentation
Security model is based on OWASP CSRF, SQL Injection and Authentication Cheat Sheets,
PHP docs for PDO prepared statements, password hashing, and session security,
plus VietQR Quick Link documentation.
