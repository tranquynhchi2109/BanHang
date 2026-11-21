-- One-time MB Bank recipient setup for existing BanHang installations.
-- Run against your existing web_mysqli database in phpMyAdmin after a backup.
-- This changes only the bank's BIN and recipient account number;
-- it intentionally leaves bank_name untouched (not provided by the owner).
INSERT INTO tbl_settings (setting_key, setting_value)
VALUES ('bank_id', '970422'), ('bank_account', '0973258529')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
