-- BANHANG.SQL: THE ONLY SQL FILE NEEDED FOR EXISTING INSTALLS OR NEW EMPTY DB.
-- BACK UP the real web_mysqli database before import.
-- Does NOT erase, replace or re-import customer, product, category or old order rows.
-- For existing old v2_* tables: renames them to tbl_* if no name collisions.
-- If an old and a new table coexist, the preflight fails: do not drop either.
-- No dummy products, prices, accounts, credentials or private customer data.
SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS `web_mysqli` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `web_mysqli`;

SET @banhang_conflict = ((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='v2_orders') > 0 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_orders') > 0) + ((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='v2_order_items') > 0 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_order_items') > 0) + ((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='v2_settings') > 0 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_settings') > 0) + ((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='v2_login_attempts') > 0 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_login_attempts') > 0);
SET @banhang_guard = IF(@banhang_conflict > 0,'SELECT * FROM `BANHANG_STOP_DUAL_TABLES_BACKUP_AND_RECONCILE`','SELECT 1 AS safe_to_migrate');
PREPARE bh_guard FROM @banhang_guard; EXECUTE bh_guard; DEALLOCATE PREPARE bh_guard;

SET @bh_sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='v2_orders') > 0 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_orders') = 0,'RENAME TABLE `v2_orders` TO `tbl_orders`','SELECT 1');
PREPARE bh_rename FROM @bh_sql; EXECUTE bh_rename; DEALLOCATE PREPARE bh_rename;
SET @bh_sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='v2_order_items') > 0 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_order_items') = 0,'RENAME TABLE `v2_order_items` TO `tbl_order_items`','SELECT 1');
PREPARE bh_rename FROM @bh_sql; EXECUTE bh_rename; DEALLOCATE PREPARE bh_rename;
SET @bh_sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='v2_settings') > 0 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_settings') = 0,'RENAME TABLE `v2_settings` TO `tbl_settings`','SELECT 1');
PREPARE bh_rename FROM @bh_sql; EXECUTE bh_rename; DEALLOCATE PREPARE bh_rename;
SET @bh_sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='v2_login_attempts') > 0 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_login_attempts') = 0,'RENAME TABLE `v2_login_attempts` TO `tbl_login_attempts`','SELECT 1');
PREPARE bh_rename FROM @bh_sql; EXECUTE bh_rename; DEALLOCATE PREPARE bh_rename;

-- Legacy schema: only CREATE when missing. Current records and indexes remain untouched.
CREATE TABLE IF NOT EXISTS `tbl_admin` (
  `id_admin` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `username` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `admin_status` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_cart` (
  `id_cart` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `id_khachhang` int(11) NOT NULL,
  `code_cart` varchar(20) NOT NULL,
  `cart_status` int(11) NOT NULL DEFAULT 1,
  `cart_date` datetime NOT NULL DEFAULT current_timestamp(),
  `cart_payment` varchar(50) NOT NULL DEFAULT 'COD',
  `tongtien` double NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_cart_details` (
  `id_cart_details` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `code_cart` varchar(20) NOT NULL,
  `id_sanpham` int(11) NOT NULL,
  `soluongmua` int(11) NOT NULL,
  `dungluong` varchar(50) DEFAULT '128GB'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_dangky` (
  `id_dangky` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenkhachhang` varchar(200) NOT NULL,
  `email` varchar(100) NOT NULL,
  `diachi` varchar(200) NOT NULL,
  `matkhau` varchar(100) NOT NULL,
  `dienthoai` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_danhmuc` (
  `id_danhmuc` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tendanhmuc` varchar(100) NOT NULL,
  `thutu` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_danhmucbaiviet` (
  `id_baiviet` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tendanhmuc_baiviet` varchar(255) NOT NULL,
  `thutu` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_lienhe` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `thongtinlienhe` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_sanpham` (
  `id_sanpham` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tensanpham` varchar(250) NOT NULL,
  `masp` varchar(100) NOT NULL,
  `giasp` int(11) NOT NULL,
  `soluong` int(11) NOT NULL,
  `hinhanh` varchar(255) NOT NULL,
  `tomtat` text NOT NULL,
  `noidung` longtext NOT NULL,
  `tinhtrang` int(11) NOT NULL DEFAULT 1,
  `id_danhmuc` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_tintuc` (
  `id_baiviet` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenbaiviet` varchar(255) NOT NULL,
  `tomtat` text NOT NULL,
  `noidung` longtext NOT NULL,
  `hinhanh` varchar(255) NOT NULL,
  `ngaydang` datetime NOT NULL DEFAULT current_timestamp(),
  `trangthai` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order, payment and login schema.
CREATE TABLE IF NOT EXISTS tbl_orders (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 order_code VARCHAR(24) NOT NULL UNIQUE,
 user_id INT NOT NULL,
 customer_name VARCHAR(160) NOT NULL,
 phone VARCHAR(30) NOT NULL,
 delivery_address VARCHAR(500) NOT NULL,
 note VARCHAR(500) NOT NULL DEFAULT '',
 total_amount BIGINT UNSIGNED NOT NULL,
 payment_method ENUM('COD','BANK') NOT NULL DEFAULT 'COD',
 payment_status ENUM('UNPAID','PAID') NOT NULL DEFAULT 'UNPAID',
 order_status ENUM('PENDING','CONFIRMED','SHIPPING','COMPLETED','CANCELLED') NOT NULL DEFAULT 'PENDING',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_user_created (user_id,created_at),
 INDEX idx_status_created (order_status,created_at),
 INDEX idx_payment_status (payment_status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS tbl_order_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 product_id INT NOT NULL,
 product_name VARCHAR(250) NOT NULL,
 variant_label VARCHAR(20) NOT NULL DEFAULT 'BASE',
 unit_price BIGINT UNSIGNED NOT NULL,
 quantity SMALLINT UNSIGNED NOT NULL,
 CONSTRAINT fk_banhang_item_order FOREIGN KEY (order_id) REFERENCES tbl_orders(id) ON DELETE CASCADE,
 INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SET @bh_variant_exists=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_order_items' AND COLUMN_NAME='variant_label');
SET @bh_variant_sql=IF(@bh_variant_exists=0, 'ALTER TABLE tbl_order_items ADD COLUMN variant_label VARCHAR(20) NOT NULL DEFAULT ''BASE'' AFTER product_name','SELECT 1');
PREPARE bh_variant FROM @bh_variant_sql; EXECUTE bh_variant; DEALLOCATE PREPARE bh_variant;
CREATE TABLE IF NOT EXISTS tbl_settings (
 setting_key VARCHAR(60) NOT NULL PRIMARY KEY,
 setting_value VARCHAR(400) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS tbl_login_attempts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 key_hash CHAR(64) NOT NULL,
 attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_hash_time (key_hash,attempted_at),
 INDEX idx_attempt_time (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Single source of truth for confirmed per-capacity selling prices.
-- Non-phone products continue using tbl_sanpham.giasp directly.
-- Available stock is still shared at tbl_sanpham.soluong (not per capacity).
CREATE TABLE IF NOT EXISTS tbl_product_variants (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 product_id INT NOT NULL,
 variant_code VARCHAR(20) NOT NULL,
 price BIGINT UNSIGNED NOT NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 UNIQUE KEY uniq_product_variant (product_id,variant_code),
 INDEX idx_product_active (product_id,is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Seed real records for all four capacities at the EXACT prices
-- displayed by the former PHP frontend (not independently verified market prices).
-- Legacy frontend formula: 128GB base; 256GB +2m; 512GB +5m; 1TB +10m.
-- Samsung S24 Ultra: 27,500,000 (128GB) and 37,500,000 (1TB).
-- Never overwrite previously saved admin prices or disabled variants:
-- UNIQUE(product_id,variant_code) + INSERT IGNORE preserves existing records.
-- Verify prices in Admin before accepting real customer payments.
INSERT IGNORE INTO tbl_product_variants (product_id,variant_code,price,is_active)
SELECT p.id_sanpham,
       cap.variant_code,
       CAST(p.giasp AS UNSIGNED) + cap.legacy_surcharge,
       1
FROM tbl_sanpham p
JOIN tbl_danhmuc c ON c.id_danhmuc=p.id_danhmuc
CROSS JOIN (
 SELECT '128GB' AS variant_code, 0 AS legacy_surcharge
 UNION ALL SELECT '256GB', 2000000
 UNION ALL SELECT '512GB', 5000000
 UNION ALL SELECT '1TB', 10000000
) AS cap
WHERE LOWER(c.tendanhmuc) LIKE '%điện thoại%' AND p.giasp>0;

INSERT IGNORE INTO tbl_settings(setting_key,setting_value) VALUES
 ('store_name','BanHang Tech Store'),('support_email',''),('bank_name','');
INSERT INTO tbl_settings(setting_key,setting_value)
VALUES ('bank_id','970422'),('bank_account','0973258529')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);


-- Manually reconciled VietQR payments. Idempotent MariaDB 10.4+/MySQL 8 updates.
-- Never mark legacy/unverified orders PAID in the migration.
SET @bh_missing=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_orders' AND COLUMN_NAME='payment_review_status');
SET @bh_add_col=IF(@bh_missing=0,'ALTER TABLE tbl_orders ADD COLUMN payment_review_status ENUM(''NONE'',''REPORTED'',''REJECTED'',''VERIFIED'') NOT NULL DEFAULT ''NONE''','SELECT 1');
PREPARE bh_alter FROM @bh_add_col; EXECUTE bh_alter; DEALLOCATE PREPARE bh_alter;
SET @bh_missing=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_orders' AND COLUMN_NAME='payment_reported_at');
SET @bh_add_col=IF(@bh_missing=0,'ALTER TABLE tbl_orders ADD COLUMN payment_reported_at DATETIME NULL','SELECT 1');
PREPARE bh_alter FROM @bh_add_col; EXECUTE bh_alter; DEALLOCATE PREPARE bh_alter;
SET @bh_missing=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_orders' AND COLUMN_NAME='customer_transfer_note');
SET @bh_add_col=IF(@bh_missing=0,'ALTER TABLE tbl_orders ADD COLUMN customer_transfer_note VARCHAR(200) NULL','SELECT 1');
PREPARE bh_alter FROM @bh_add_col; EXECUTE bh_alter; DEALLOCATE PREPARE bh_alter;
SET @bh_missing=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_orders' AND COLUMN_NAME='bank_reference');
SET @bh_add_col=IF(@bh_missing=0,'ALTER TABLE tbl_orders ADD COLUMN bank_reference VARCHAR(120) NULL','SELECT 1');
PREPARE bh_alter FROM @bh_add_col; EXECUTE bh_alter; DEALLOCATE PREPARE bh_alter;
SET @bh_missing=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_orders' AND COLUMN_NAME='payment_verified_at');
SET @bh_add_col=IF(@bh_missing=0,'ALTER TABLE tbl_orders ADD COLUMN payment_verified_at DATETIME NULL','SELECT 1');
PREPARE bh_alter FROM @bh_add_col; EXECUTE bh_alter; DEALLOCATE PREPARE bh_alter;
SET @bh_missing=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_orders' AND COLUMN_NAME='payment_verified_by');
SET @bh_add_col=IF(@bh_missing=0,'ALTER TABLE tbl_orders ADD COLUMN payment_verified_by INT NULL','SELECT 1');
PREPARE bh_alter FROM @bh_add_col; EXECUTE bh_alter; DEALLOCATE PREPARE bh_alter;

SET @bh_index_exists=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_orders' AND INDEX_NAME='uniq_bank_reference');
SET @bh_index_sql=IF(@bh_index_exists=0,
  'ALTER TABLE tbl_orders ADD UNIQUE KEY uniq_bank_reference (bank_reference)','SELECT 1');
PREPARE bh_idx FROM @bh_index_sql; EXECUTE bh_idx; DEALLOCATE PREPARE bh_idx;

CREATE TABLE IF NOT EXISTS tbl_payment_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  event_type ENUM('CUSTOMER_REPORTED','ADMIN_CONFIRMED','ADMIN_REJECTED','ADMIN_EXPIRED','ADMIN_COD_PAID') NOT NULL,
  actor_type ENUM('CUSTOMER','ADMIN') NOT NULL,
  actor_id INT NOT NULL,
  detail VARCHAR(500) NOT NULL DEFAULT '',
  reference_code VARCHAR(120) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_order_events (order_id,created_at),
  CONSTRAINT fk_banhang_payment_events_order FOREIGN KEY (order_id) REFERENCES tbl_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Extend tbl_payment_events.event_type safely for explicit COD cash confirmation.
SET @bh_pe_has_cod=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_payment_events' AND COLUMN_NAME='event_type' AND COLUMN_TYPE LIKE '%ADMIN_COD_PAID%');
SET @bh_pe_sql=IF(@bh_pe_has_cod=0,
  'ALTER TABLE tbl_payment_events MODIFY COLUMN event_type ENUM(''CUSTOMER_REPORTED'',''ADMIN_CONFIRMED'',''ADMIN_REJECTED'',''ADMIN_EXPIRED'',''ADMIN_COD_PAID'') NOT NULL',
  'SELECT 1');
PREPARE bh_pe FROM @bh_pe_sql; EXECUTE bh_pe; DEALLOCATE PREPARE bh_pe;

-- Order workflow & fulfillment upgrades (idempotent, preserves all existing rows and statuses).
SET @bh_has_preparing=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_orders' AND COLUMN_NAME='order_status' AND COLUMN_TYPE LIKE '%PREPARING%');
SET @bh_order_enum_sql=IF(@bh_has_preparing=0,
  'ALTER TABLE tbl_orders MODIFY COLUMN order_status ENUM(''PENDING'',''CONFIRMED'',''PREPARING'',''SHIPPING'',''COMPLETED'',''CANCELLED'') NOT NULL DEFAULT ''PENDING''',
  'SELECT 1');
PREPARE bh_enum FROM @bh_order_enum_sql; EXECUTE bh_enum; DEALLOCATE PREPARE bh_enum;

SET @bh_missing=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_orders' AND COLUMN_NAME='admin_note');
SET @bh_add_col=IF(@bh_missing=0,'ALTER TABLE tbl_orders ADD COLUMN admin_note VARCHAR(500) NOT NULL DEFAULT ''''','SELECT 1');
PREPARE bh_alter FROM @bh_add_col; EXECUTE bh_alter; DEALLOCATE PREPARE bh_alter;

SET @bh_missing=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_orders' AND COLUMN_NAME='customer_note');
SET @bh_add_col=IF(@bh_missing=0,'ALTER TABLE tbl_orders ADD COLUMN customer_note VARCHAR(500) NOT NULL DEFAULT ''''','SELECT 1');
PREPARE bh_alter FROM @bh_add_col; EXECUTE bh_alter; DEALLOCATE PREPARE bh_alter;

SET @bh_missing=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_orders' AND COLUMN_NAME='shipping_carrier');
SET @bh_add_col=IF(@bh_missing=0,'ALTER TABLE tbl_orders ADD COLUMN shipping_carrier VARCHAR(100) NOT NULL DEFAULT ''''','SELECT 1');
PREPARE bh_alter FROM @bh_add_col; EXECUTE bh_alter; DEALLOCATE PREPARE bh_alter;

SET @bh_missing=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_orders' AND COLUMN_NAME='tracking_code');
SET @bh_add_col=IF(@bh_missing=0,'ALTER TABLE tbl_orders ADD COLUMN tracking_code VARCHAR(100) NOT NULL DEFAULT ''''','SELECT 1');
PREPARE bh_alter FROM @bh_add_col; EXECUTE bh_alter; DEALLOCATE PREPARE bh_alter;

SET @bh_missing=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_orders' AND COLUMN_NAME='shipped_at');
SET @bh_add_col=IF(@bh_missing=0,'ALTER TABLE tbl_orders ADD COLUMN shipped_at DATETIME NULL','SELECT 1');
PREPARE bh_alter FROM @bh_add_col; EXECUTE bh_alter; DEALLOCATE PREPARE bh_alter;

SET @bh_missing=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_orders' AND COLUMN_NAME='delivered_at');
SET @bh_add_col=IF(@bh_missing=0,'ALTER TABLE tbl_orders ADD COLUMN delivered_at DATETIME NULL','SELECT 1');
PREPARE bh_alter FROM @bh_add_col; EXECUTE bh_alter; DEALLOCATE PREPARE bh_alter;

SET @bh_missing=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_orders' AND COLUMN_NAME='cancel_reason');
SET @bh_add_col=IF(@bh_missing=0,'ALTER TABLE tbl_orders ADD COLUMN cancel_reason VARCHAR(300) NOT NULL DEFAULT ''''','SELECT 1');
PREPARE bh_alter FROM @bh_add_col; EXECUTE bh_alter; DEALLOCATE PREPARE bh_alter;

SET @bh_missing=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_orders' AND COLUMN_NAME='cod_paid_at');
SET @bh_add_col=IF(@bh_missing=0,'ALTER TABLE tbl_orders ADD COLUMN cod_paid_at DATETIME NULL','SELECT 1');
PREPARE bh_alter FROM @bh_add_col; EXECUTE bh_alter; DEALLOCATE PREPARE bh_alter;

SET @bh_missing=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_orders' AND COLUMN_NAME='cod_verified_by');
SET @bh_add_col=IF(@bh_missing=0,'ALTER TABLE tbl_orders ADD COLUMN cod_verified_by INT NULL','SELECT 1');
PREPARE bh_alter FROM @bh_add_col; EXECUTE bh_alter; DEALLOCATE PREPARE bh_alter;

SET @bh_index_exists=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_orders' AND INDEX_NAME='idx_review_created');
SET @bh_index_sql=IF(@bh_index_exists=0,
  'ALTER TABLE tbl_orders ADD INDEX idx_review_created (payment_method,payment_review_status,created_at)','SELECT 1');
PREPARE bh_idx FROM @bh_index_sql; EXECUTE bh_idx; DEALLOCATE PREPARE bh_idx;

SET @bh_index_exists=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_orders' AND INDEX_NAME='idx_orders_phone');
SET @bh_index_sql=IF(@bh_index_exists=0,
  'ALTER TABLE tbl_orders ADD INDEX idx_orders_phone (phone)','SELECT 1');
PREPARE bh_idx FROM @bh_index_sql; EXECUTE bh_idx; DEALLOCATE PREPARE bh_idx;

-- Customer notification center.
CREATE TABLE IF NOT EXISTS tbl_notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  order_id BIGINT UNSIGNED NULL,
  notification_type VARCHAR(40) NOT NULL,
  dedup_key VARCHAR(120) NULL,
  title VARCHAR(180) NOT NULL,
  message VARCHAR(600) NOT NULL,
  link_url VARCHAR(255) NOT NULL DEFAULT '',
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at DATETIME NULL,
  UNIQUE KEY uniq_notif_dedup (dedup_key),
  INDEX idx_user_read_created (user_id,is_read,created_at),
  INDEX idx_user_id_desc (user_id,id),
  CONSTRAINT fk_banhang_notif_order FOREIGN KEY (order_id) REFERENCES tbl_orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order status change history for accurate customer timeline and admin audit.
CREATE TABLE IF NOT EXISTS tbl_order_status_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  from_status VARCHAR(30) NULL,
  to_status VARCHAR(30) NOT NULL,
  actor_type ENUM('CUSTOMER','ADMIN','SYSTEM') NOT NULL DEFAULT 'ADMIN',
  actor_id INT NOT NULL DEFAULT 0,
  note VARCHAR(500) NOT NULL DEFAULT '',
  is_public TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_history_order_time (order_id,created_at),
  CONSTRAINT fk_banhang_history_order FOREIGN KEY (order_id) REFERENCES tbl_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill initial status row for existing orders that do not have history yet.
INSERT INTO tbl_order_status_history (order_id,from_status,to_status,actor_type,actor_id,note,is_public,created_at)
SELECT o.id, NULL, o.order_status, 'SYSTEM', 0, 'Khởi tạo lịch sử trạng thái từ dữ liệu hiện có.', 1, o.created_at
FROM tbl_orders o
WHERE NOT EXISTS (SELECT 1 FROM tbl_order_status_history h WHERE h.order_id = o.id);

-- Optional SMTP email outbox (never blocks order transactions when SMTP is unconfigured).
CREATE TABLE IF NOT EXISTS tbl_email_outbox (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NULL,
  user_id INT NULL,
  recipient_email VARCHAR(160) NOT NULL,
  event_type VARCHAR(40) NOT NULL,
  dedup_key VARCHAR(120) NULL,
  subject VARCHAR(200) NOT NULL,
  body_html MEDIUMTEXT NOT NULL,
  body_text TEXT NOT NULL,
  status ENUM('PENDING','PROCESSING','SENT','FAILED','SKIPPED') NOT NULL DEFAULT 'PENDING',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_error VARCHAR(500) NOT NULL DEFAULT '',
  claimed_at DATETIME NULL,
  claim_token VARCHAR(40) NULL,
  next_retry_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  UNIQUE KEY uniq_email_dedup (dedup_key),
  INDEX idx_outbox_status_created (status,created_at),
  INDEX idx_outbox_claim (status,claimed_at),
  INDEX idx_outbox_retry (status,attempts,next_retry_at),
  CONSTRAINT fk_banhang_outbox_order FOREIGN KEY (order_id) REFERENCES tbl_orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Idempotent email outbox upgrades for atomic claiming & controlled retry.
SET @bh_outbox_has_proc=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_email_outbox' AND COLUMN_NAME='status' AND COLUMN_TYPE LIKE '%PROCESSING%');
SET @bh_outbox_enum_sql=IF(@bh_outbox_has_proc=0,
  'ALTER TABLE tbl_email_outbox MODIFY COLUMN status ENUM(''PENDING'',''PROCESSING'',''SENT'',''FAILED'',''SKIPPED'') NOT NULL DEFAULT ''PENDING''',
  'SELECT 1');
PREPARE bh_ob_enum FROM @bh_outbox_enum_sql; EXECUTE bh_ob_enum; DEALLOCATE PREPARE bh_ob_enum;

SET @bh_missing=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_email_outbox' AND COLUMN_NAME='claimed_at');
SET @bh_add_col=IF(@bh_missing=0,'ALTER TABLE tbl_email_outbox ADD COLUMN claimed_at DATETIME NULL AFTER last_error','SELECT 1');
PREPARE bh_alter FROM @bh_add_col; EXECUTE bh_alter; DEALLOCATE PREPARE bh_alter;

SET @bh_missing=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_email_outbox' AND COLUMN_NAME='claim_token');
SET @bh_add_col=IF(@bh_missing=0,'ALTER TABLE tbl_email_outbox ADD COLUMN claim_token VARCHAR(40) NULL AFTER claimed_at','SELECT 1');
PREPARE bh_alter FROM @bh_add_col; EXECUTE bh_alter; DEALLOCATE PREPARE bh_alter;

SET @bh_missing=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_email_outbox' AND COLUMN_NAME='next_retry_at');
SET @bh_add_col=IF(@bh_missing=0,'ALTER TABLE tbl_email_outbox ADD COLUMN next_retry_at DATETIME NULL AFTER claim_token','SELECT 1');
PREPARE bh_alter FROM @bh_add_col; EXECUTE bh_alter; DEALLOCATE PREPARE bh_alter;

SET @bh_index_exists=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_email_outbox' AND INDEX_NAME='idx_outbox_claim');
SET @bh_index_sql=IF(@bh_index_exists=0,
  'ALTER TABLE tbl_email_outbox ADD INDEX idx_outbox_claim (status,claimed_at)','SELECT 1');
PREPARE bh_idx FROM @bh_index_sql; EXECUTE bh_idx; DEALLOCATE PREPARE bh_idx;

SET @bh_index_exists=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_email_outbox' AND INDEX_NAME='idx_outbox_retry');
SET @bh_index_sql=IF(@bh_index_exists=0,
  'ALTER TABLE tbl_email_outbox ADD INDEX idx_outbox_retry (status,attempts,next_retry_at)','SELECT 1');
PREPARE bh_idx FROM @bh_index_sql; EXECUTE bh_idx; DEALLOCATE PREPARE bh_idx;

-- Immutable per-order VietQR payment snapshot (new BANK orders freeze recipient details at checkout).
-- Existing legacy orders without a snapshot are NOT backfilled with current settings.
CREATE TABLE IF NOT EXISTS tbl_order_payment_details (
  order_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  bank_id VARCHAR(20) NOT NULL,
  bank_account VARCHAR(30) NOT NULL,
  bank_account_name VARCHAR(160) NOT NULL DEFAULT '',
  expected_amount BIGINT UNSIGNED NOT NULL,
  transfer_content VARCHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_banhang_payment_details_order FOREIGN KEY (order_id) REFERENCES tbl_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventory movement ledger (shared product-level stock at tbl_sanpham.soluong).
CREATE TABLE IF NOT EXISTS tbl_inventory_movements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  order_id BIGINT UNSIGNED NULL,
  movement_type ENUM('INITIAL','ADJUSTMENT','ORDER_DEDUCT','ORDER_CANCEL_RESTORE') NOT NULL,
  delta INT NOT NULL,
  stock_before INT NOT NULL,
  stock_after INT NOT NULL,
  reason VARCHAR(300) NOT NULL DEFAULT '',
  actor_type ENUM('CUSTOMER','ADMIN','SYSTEM') NOT NULL DEFAULT 'ADMIN',
  actor_id INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_inv_product_time (product_id,created_at),
  INDEX idx_inv_order (order_id),
  CONSTRAINT fk_banhang_inv_order FOREIGN KEY (order_id) REFERENCES tbl_orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indexes to support revenue reporting by actual verification timestamps.
SET @bh_index_exists=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_orders' AND INDEX_NAME='idx_orders_bank_verified');
SET @bh_index_sql=IF(@bh_index_exists=0,
  'ALTER TABLE tbl_orders ADD INDEX idx_orders_bank_verified (payment_method,payment_status,payment_verified_at)','SELECT 1');
PREPARE bh_idx FROM @bh_index_sql; EXECUTE bh_idx; DEALLOCATE PREPARE bh_idx;

SET @bh_index_exists=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_orders' AND INDEX_NAME='idx_orders_cod_paid');
SET @bh_index_sql=IF(@bh_index_exists=0,
  'ALTER TABLE tbl_orders ADD INDEX idx_orders_cod_paid (payment_method,payment_status,cod_paid_at)','SELECT 1');
PREPARE bh_idx FROM @bh_index_sql; EXECUTE bh_idx; DEALLOCATE PREPARE bh_idx;

SELECT 'tbl_sanpham' AS bang,COUNT(*) AS so_dong FROM tbl_sanpham
UNION ALL SELECT 'tbl_orders',COUNT(*) FROM tbl_orders
UNION ALL SELECT 'tbl_order_items',COUNT(*) FROM tbl_order_items
UNION ALL SELECT 'tbl_product_variants',COUNT(*) FROM tbl_product_variants
UNION ALL SELECT 'tbl_order_payment_details',COUNT(*) FROM tbl_order_payment_details
UNION ALL SELECT 'tbl_inventory_movements',COUNT(*) FROM tbl_inventory_movements
UNION ALL SELECT 'tbl_notifications',COUNT(*) FROM tbl_notifications
UNION ALL SELECT 'tbl_order_status_history',COUNT(*) FROM tbl_order_status_history
UNION ALL SELECT 'tbl_email_outbox',COUNT(*) FROM tbl_email_outbox;
SELECT setting_key,setting_value FROM tbl_settings WHERE setting_key IN ('bank_id','bank_account');
