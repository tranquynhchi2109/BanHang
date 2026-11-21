-- ONE-TIME RENAME: preserve all rows, primary keys, indexes and relationships.
-- For XAMPP / MariaDB and MySQL. BACK UP your live web_mysqli DB first.
-- Run this in phpMyAdmin BEFORE pulling PHP code that uses tbl_* tables.
-- Only run if the four v2_* tables EXIST and their corresponding tbl_* names DO NOT.
-- Do not rerun. Do NOT import web_mysqli.sql over your existing data.
-- If any tbl_* target already exists, STOP and reconcile it first.
USE `web_mysqli`;

RENAME TABLE
  `v2_orders` TO `tbl_orders`,
  `v2_order_items` TO `tbl_order_items`,
  `v2_settings` TO `tbl_settings`,
  `v2_login_attempts` TO `tbl_login_attempts`;

-- All four new table names should appear here.
SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('tbl_orders','tbl_order_items','tbl_settings','tbl_login_attempts')
ORDER BY TABLE_NAME;

-- Check that previously saved orders are still present.
SELECT COUNT(*) AS total_orders FROM `tbl_orders`;

-- Check the storage-variant snapshot column (needed for checkout).
SELECT COLUMN_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'tbl_order_items'
  AND COLUMN_NAME = 'variant_label';
