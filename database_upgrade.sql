-- Run AFTER importing the existing web_mysqli.sql into the web_mysqli database.
-- Non-destructive: legacy customer, product, category and news tables remain intact.
CREATE TABLE IF NOT EXISTS v2_orders (
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
CREATE TABLE IF NOT EXISTS v2_order_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 product_id INT NOT NULL,
 product_name VARCHAR(250) NOT NULL,
 unit_price BIGINT UNSIGNED NOT NULL,
 quantity SMALLINT UNSIGNED NOT NULL,
 CONSTRAINT fk_v2_items_order FOREIGN KEY (order_id) REFERENCES v2_orders(id) ON DELETE CASCADE,
 INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS v2_settings (
 setting_key VARCHAR(60) PRIMARY KEY,
 setting_value VARCHAR(400) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO v2_settings(setting_key, setting_value) VALUES
 ('store_name','BanHang Tech Store'),('support_email',''),('bank_id',''),('bank_account',''),('bank_name','');
CREATE TABLE IF NOT EXISTS v2_login_attempts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 key_hash CHAR(64) NOT NULL,
 attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_hash_time (key_hash, attempted_at),
 INDEX idx_attempt_time (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
