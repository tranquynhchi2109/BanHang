-- Optional performance indexes for large data sets.
-- Back up your database and review existing indexes first.
-- Every index is applied only when its name is absent.

SET @missing_0=(SELECT COUNT(*)=0 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_sanpham' AND INDEX_NAME='idx_v2_sanpham_visibility');
SET @sql_0=IF(@missing_0,'ALTER TABLE tbl_sanpham ADD INDEX idx_v2_sanpham_visibility (tinhtrang,id_sanpham)','SELECT 1');
PREPARE stmt_0 FROM @sql_0; EXECUTE stmt_0; DEALLOCATE PREPARE stmt_0;

SET @missing_1=(SELECT COUNT(*)=0 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_sanpham' AND INDEX_NAME='idx_v2_sanpham_cat_visible');
SET @sql_1=IF(@missing_1,'ALTER TABLE tbl_sanpham ADD INDEX idx_v2_sanpham_cat_visible (id_danhmuc,tinhtrang,id_sanpham)','SELECT 1');
PREPARE stmt_1 FROM @sql_1; EXECUTE stmt_1; DEALLOCATE PREPARE stmt_1;

SET @missing_2=(SELECT COUNT(*)=0 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_tintuc' AND INDEX_NAME='idx_v2_tintuc_visible_date');
SET @sql_2=IF(@missing_2,'ALTER TABLE tbl_tintuc ADD INDEX idx_v2_tintuc_visible_date (trangthai,ngaydang)','SELECT 1');
PREPARE stmt_2 FROM @sql_2; EXECUTE stmt_2; DEALLOCATE PREPARE stmt_2;

SET @missing_3=(SELECT COUNT(*)=0 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_dangky' AND INDEX_NAME='idx_v2_dangky_email');
SET @sql_3=IF(@missing_3,'ALTER TABLE tbl_dangky ADD INDEX idx_v2_dangky_email (email)','SELECT 1');
PREPARE stmt_3 FROM @sql_3; EXECUTE stmt_3; DEALLOCATE PREPARE stmt_3;

SET @missing_4=(SELECT COUNT(*)=0 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_admin' AND INDEX_NAME='idx_v2_admin_username');
SET @sql_4=IF(@missing_4,'ALTER TABLE tbl_admin ADD INDEX idx_v2_admin_username (username)','SELECT 1');
PREPARE stmt_4 FROM @sql_4; EXECUTE stmt_4; DEALLOCATE PREPARE stmt_4;

SET @missing_5=(SELECT COUNT(*)=0 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_danhmuc' AND INDEX_NAME='idx_v2_danhmuc_sort');
SET @sql_5=IF(@missing_5,'ALTER TABLE tbl_danhmuc ADD INDEX idx_v2_danhmuc_sort (thutu,id_danhmuc)','SELECT 1');
PREPARE stmt_5 FROM @sql_5; EXECUTE stmt_5; DEALLOCATE PREPARE stmt_5;

