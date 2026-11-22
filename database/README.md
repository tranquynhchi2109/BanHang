# BanHang — Demo database seed (manual / optional)

File: [seed_demo_orders.sql](seed_demo_orders.sql)

This is **fake data for local development and admin UI testing**. It is NOT part of the schema migration and is **never imported by banhang.sql**.

## What it adds

| Fixture | Count |
| --- | ---: |
| Demo categories | 4 |
| Demo products with images already in the repo | 16 |
| Fake customers with disabled-login passwords and .invalid email | 20 |
| Demo orders BHDEMO0001...BHDEMO0240 | 240 |
| Demo order items | 480 |
| Pending cancellation review requests | 20 |
| Declined cancellation requests | 20 |

The orders span PENDING / CONFIRMED / PREPARING / SHIPPING / COMPLETED / CANCELLED. The seed also populates stock deduction ledger entries for noncancelled new orders, public status timelines, payment audit examples and in-app notifications. Some orders are marked PAID **for chart testing only**; no bank transfer actually occurred. There are NO bank snapshots, real bank account numbers, actual shipment references or outgoing emails.

## Recommended: separate local demo database

1. Start XAMPP Apache and MySQL. Create **web_mysqli_demo** in phpMyAdmin with utf8mb4.
2. Import **banhang.sql** into **web_mysqli_demo** for the current schema.
3. Import **database/seed_demo_orders.sql** into **web_mysqli_demo**.
4. Set **DB_NAME** to **web_mysqli_demo** in the local **admin/config/config.local.php** (already git-ignored) so Admin displays these demo orders.

CLI alternative after creating that database:

~~~bash
mysql -u root -p -D web_mysqli_demo < banhang.sql
mysql -u root -p -D web_mysqli_demo < database/seed_demo_orders.sql
~~~

## Intentionally import into local web_mysqli instead?

> [!CAUTION]
> **Export a full database backup first.** The new demo orders include simulated PAID amounts, which will affect Admin reports. NEVER do this on a production database. To import into an existing disposable/local web_mysqli, open **database/seed_demo_orders.sql**, change **SET @bh_demo_allow_existing_database = 0** to **1**, then manually import that edited file. The default blocks this action.

Never import into production. Do not edit the database name inside the SQL; select the destination database first.

## Idempotency and limitations

- Stable BHDEMO order codes, BH-DEMO-P SKUs and example.invalid fixture emails prevent duplicates. Reimporting **does not duplicate existing demo orders, stock deductions, items, notifications, or requests**.
- Existing demo records are not overwritten by a reimport, including manually changed prices/statuses; real users/products/orders are untouched.
- Requires MySQL 8.0 or MariaDB 10.11, InnoDB, and an up-to-date banhang.sql schema.
- Order dates are distributed over the previous four months based on the initial import date and are retained on reimport.
- Fake customers cannot log in. There are no public demo account credentials.
- Cancelling a demo order from Admin uses the normal product inventory workflow. Demo orders must never be treated as real customer commitments.
- For a clean demo, rebuild **only** the disposable web_mysqli_demo database from scratch, NEVER web_mysqli.


## Lỗi #1267 khi import vào database XAMPP cũ

Nếu gặp **Illegal mix of collations (utf8mb4_unicode_ci và utf8mb4_general_ci)**, hãy lấy **bản seed_demo_orders.sql mới nhất** và import lại. Bản cập nhật dùng COLLATE tường minh khi so sánh tên danh mục, mã sản phẩm, email khách và mã đơn trong dữ liệu giả lập.

**Không** dùng ALTER TABLE hoặc CONVERT TO CHARACTER SET để chuyển đổi hàng loạt dữ liệu khách hàng. **Không** xóa database. Sau khi đã sao lưu, giữ nguyên opt-in nếu đang cố ý dùng database local và import lại file mới. File seed đã có bảo vệ chống sinh đơn trùng.


## Danh mục chung, không tách [DEMO]

Từ bản seed mới nhất, 16 sản phẩm **fixture nội bộ** được gắn vào **chính** các danh mục ban đầu: **Điện thoại, Laptop, Tai nghe & Loa, Phụ kiện điện tử**. Không còn tạo danh mục [DEMO] mới. Fixture sản phẩm được **tắt bán** (tinhtrang=0) để không nhân đôi iPhone, Galaxy hay laptop trên gian hàng, nhưng vẫn giữ ID và tồn kho cho 240 đơn hàng thử nghiệm/hủy đơn an toàn.

**Nếu đã import bản cũ và trang chủ hiện danh mục [DEMO]:**

1. Backup database web_mysqli.
2. git pull origin main để có bản seed mới.
3. Nếu chủ động import vào database local web_mysqli, giữ opt-in @bh_demo_allow_existing_database=1; nếu dùng web_mysqli_demo, giữ mặc định 0.
4. Import lại **database/seed_demo_orders.sql**. Script tự chuyển **chỉ** các sản phẩm có SKU BH-DEMO-P% về danh mục chung, ẩn chúng khỏi storefront và xóa các danh mục cũ **nếu trống**. Đơn đã tồn tại không bị thêm lại, không bị trừ kho lần hai.
5. Tải lại trang chủ và kiểm tra danh mục.

Không xóa tay tbl_sanpham hoặc tbl_orders. Các sản phẩm fixture còn nằm trong Admin ở trạng thái ẩn nhằm giữ tham chiếu đơn hàng lịch sử; đơn BHDEMO và dữ liệu thanh toán giả lập vẫn được giữ để kiểm thử, **không phải doanh thu thật**.
