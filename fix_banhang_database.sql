-- BanHang: sua database checkout XAMPP / MariaDB 10.4+
-- Tuong thich ban backup web_mysqli.sql da gui.
-- QUAN TRONG: sao luu database hien tai truoc khi chay.
-- Khong import lai web_mysqli.sql len database dang co du lieu.
-- Khong DROP, DELETE, TRUNCATE du lieu nao trong script nay.

USE `web_mysqli`;

-- Checkout ghi dung luong san pham vao v2_order_items.variant_label.
-- MariaDB ho tro ADD COLUMN IF NOT EXISTS, cho phep chay lai an toan.
ALTER TABLE `v2_order_items`
  ADD COLUMN IF NOT EXISTS `variant_label` VARCHAR(20) NOT NULL DEFAULT 'BASE' AFTER `product_name`;

-- Tai khoan nhan VietQR theo thong tin chu shop cung cap.
-- KHONG tu dien ten chu tai khoan khi chua duoc xac minh.
INSERT INTO `v2_settings` (`setting_key`, `setting_value`)
VALUES
  ('bank_id', '970422'),
  ('bank_account', '0973258529')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- Kiem tra ket qua. Phai thay 1 dong variant_label va 2 dong bank.
SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'v2_order_items'
  AND COLUMN_NAME = 'variant_label';

SELECT `setting_key`, `setting_value`
FROM `v2_settings`
WHERE `setting_key` IN ('bank_id', 'bank_account')
ORDER BY `setting_key`;
