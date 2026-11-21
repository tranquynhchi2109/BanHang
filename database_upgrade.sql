-- Run against the web_mysqli database after backup.
-- For an existing installation with v2_* tables, first run doi_ten_bang_tbl.sql
-- to preserve old rows by RENAMING the tables. Do NOT import web_mysqli.sql
-- over an already populated database.
-- Non-destructive: legacy customer, product, category and news tables remain intact.
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
 INDEX idx_user_created (user_id, created_at),
 INDEX idx_status_created (order_status, created_at),
 INDEX idx_payment_status (payment_status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS tbl_order_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 product_id INT NOT NULL,
 product_name VARCHAR(250) NOT NULL,
 variant_label VARCHAR(20) NOT NULL DEFAULT 'BASE',
 unit_price BIGINT UNSIGNED NOT NULL,
 quantity SMALLINT UNSIGNED NOT NULL,
 CONSTRAINT fk_tbl_order_items_order FOREIGN KEY (order_id) REFERENCES tbl_orders(id) ON DELETE CASCADE,
 INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS tbl_settings (
 setting_key VARCHAR(60) PRIMARY KEY,
 setting_value VARCHAR(400) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO tbl_settings(setting_key, setting_value) VALUES
 ('store_name','BanHang Tech Store'),('support_email',''),('bank_id','970422'),('bank_account','0973258529'),('bank_name','');
CREATE TABLE IF NOT EXISTS tbl_login_attempts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 key_hash CHAR(64) NOT NULL,
 attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_hash_time (key_hash, attempted_at),
 INDEX idx_attempt_time (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add the variant snapshot safely when the original migration has already been run.
SET @variant_column_exists = (
 SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_order_items' AND COLUMN_NAME='variant_label'
);
SET @variant_sql = IF(@variant_column_exists=0,
 "ALTER TABLE tbl_order_items ADD COLUMN variant_label VARCHAR(20) NOT NULL DEFAULT 'BASE'",
 "SELECT 1");
PREPARE variant_stmt FROM @variant_sql;
EXECUTE variant_stmt;
DEALLOCATE PREPARE variant_stmt;
