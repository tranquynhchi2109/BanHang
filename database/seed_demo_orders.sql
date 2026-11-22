-- OPTIONAL: demo data for BanHang. Never automatically imported by banhang.sql.
-- Generates 16 demo products, 20 fake customers and 240 demo orders.
-- Orders marked PAID are SIMULATED, not bank payments or real revenue.
-- NEVER use in production. See database/README.md before import.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
-- Legacy XAMPP tables may have utf8mb4_general_ci even when new tables
-- use utf8mb4_unicode_ci. Always normalize both sides of textual joins
-- explicitly; changing a whole customer database's collation is unsafe.
SET @bh_demo_allow_existing_database = 0; -- change to 1 ONLY for a deliberate local demo import
SET @bh_demo_allowed = DATABASE() IS NOT NULL AND
  (DATABASE() REGEXP '(_demo|_test|_testing|_ci)$' OR @bh_demo_allow_existing_database=1);
SET @bh_demo_guard_sql = IF(@bh_demo_allowed, 'SELECT 1 AS demo_import_allowed',
  'SELECT * FROM BANHANG_DEMO_SEED_BLOCKED_EXPLICIT_OPT_IN_REQUIRED');
PREPARE bh_guard FROM @bh_demo_guard_sql;
EXECUTE bh_guard;
DEALLOCATE PREPARE bh_guard;
SET @bh_demo_schema_ok = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (
    'tbl_danhmuc','tbl_sanpham','tbl_dangky','tbl_product_variants','tbl_orders',
    'tbl_order_items','tbl_order_status_history','tbl_notifications',
    'tbl_inventory_movements','tbl_order_cancel_requests','tbl_payment_events'
  )
)=11;
SET @bh_demo_guard_sql = IF(@bh_demo_schema_ok, 'SELECT 1 AS schema_ready',
  'SELECT * FROM BANHANG_DEMO_STOP_IMPORT_BANHANG_SQL_FIRST');
PREPARE bh_guard FROM @bh_demo_guard_sql;
EXECUTE bh_guard;
DEALLOCATE PREPARE bh_guard;

DROP TEMPORARY TABLE IF EXISTS bh_demo_nums;
DROP TEMPORARY TABLE IF EXISTS bh_demo_catalog;
DROP TEMPORARY TABLE IF EXISTS bh_demo_products;
DROP TEMPORARY TABLE IF EXISTS bh_demo_customers;
DROP TEMPORARY TABLE IF EXISTS bh_demo_lines;
DROP TEMPORARY TABLE IF EXISTS bh_demo_fresh;
DROP TEMPORARY TABLE IF EXISTS bh_demo_stock_need;

CREATE TEMPORARY TABLE bh_demo_nums(n INT PRIMARY KEY) ENGINE=MEMORY;
INSERT INTO bh_demo_nums(n)
SELECT 1+a.n+10*b.n+100*c.n
FROM (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) a CROSS JOIN (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) b CROSS JOIN (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) c
WHERE 1+a.n+10*b.n+100*c.n<=240;
CREATE TEMPORARY TABLE bh_demo_catalog(
 n INT PRIMARY KEY,sku VARCHAR(100),title VARCHAR(250),category VARCHAR(100),
 demo_price INT,image_file VARCHAR(255)
) ENGINE=MEMORY;
INSERT INTO bh_demo_catalog(n,sku,title,category,demo_price,image_file) VALUES
(1,'BH-DEMO-P001','[DEMO] iPhone 15','[DEMO] Điện thoại',16990000,'ip15.jpg'),
(2,'BH-DEMO-P002','[DEMO] iPhone 16','[DEMO] Điện thoại',20990000,'iphone-16 thuong.webp'),
(3,'BH-DEMO-P003','[DEMO] Galaxy S24','[DEMO] Điện thoại',18990000,'samsung-galaxy-s24.jpg'),
(4,'BH-DEMO-P004','[DEMO] Galaxy S25','[DEMO] Điện thoại',22990000,'samsung-galaxy-s25.jpg'),
(5,'BH-DEMO-P005','[DEMO] OPPO Reno 14','[DEMO] Điện thoại',11990000,'oppo reno 14.webp'),
(6,'BH-DEMO-P006','[DEMO] Galaxy Z Flip7','[DEMO] Điện thoại',25990000,'samsung_galaxy_z_flip7.webp'),
(7,'BH-DEMO-P007','[DEMO] ASUS TUF Gaming A15','[DEMO] Laptop',21490000,'Asus TUF Gaming A15.jpg'),
(8,'BH-DEMO-P008','[DEMO] ASUS Vivobook Go 15','[DEMO] Laptop',11990000,'asus vivobook go 15.jpg'),
(9,'BH-DEMO-P009','[DEMO] Dell Inspiron 15 3530','[DEMO] Laptop',16490000,'Dell inspiron 15 3530 i5 1334U.jpg'),
(10,'BH-DEMO-P010','[DEMO] Dell Latitude 3450','[DEMO] Laptop',19490000,'dell latitude 3450 i7 1355U.jpg'),
(11,'BH-DEMO-P011','[DEMO] AirPods 4 ANC','[DEMO] Âm thanh',3990000,'airpods 4 ANC.webp'),
(12,'BH-DEMO-P012','[DEMO] AirPods Pro 3','[DEMO] Âm thanh',6490000,'airpods-pro-3.webp'),
(13,'BH-DEMO-P013','[DEMO] Củ sạc iPhone','[DEMO] Phụ kiện',390000,'củ sạc ip.webp'),
(14,'BH-DEMO-P014','[DEMO] Ốp lưng Samsung','[DEMO] Phụ kiện',199000,'ốp lưng samsung.webp'),
(15,'BH-DEMO-P015','[DEMO] Pin dự phòng Anker 335','[DEMO] Phụ kiện',990000,'sạc dự phòng anker 335.png'),
(16,'BH-DEMO-P016','[DEMO] Giá đỡ điện thoại','[DEMO] Phụ kiện',189000,'giá đỡ đt.jpg');
CREATE TEMPORARY TABLE bh_demo_products(
 n INT PRIMARY KEY,product_id INT,product_name VARCHAR(250),base_price INT,is_phone TINYINT
) ENGINE=MEMORY;
CREATE TEMPORARY TABLE bh_demo_customers(
 n INT PRIMARY KEY,user_id INT,display_name VARCHAR(160)
) ENGINE=MEMORY;
CREATE TEMPORARY TABLE bh_demo_lines(
 n INT,line_no INT,product_id INT,product_name VARCHAR(250),
 variant_label VARCHAR(20),quantity INT,unit_price BIGINT,PRIMARY KEY(n,line_no)
) ENGINE=MEMORY;
CREATE TEMPORARY TABLE bh_demo_fresh(n INT PRIMARY KEY,order_id BIGINT UNSIGNED) ENGINE=MEMORY;
CREATE TEMPORARY TABLE bh_demo_stock_need(product_id INT PRIMARY KEY,units_required INT) ENGINE=MEMORY;

START TRANSACTION;

INSERT INTO tbl_danhmuc(tendanhmuc,thutu)
SELECT c.category,1000 FROM (SELECT DISTINCT category FROM bh_demo_catalog) c
WHERE NOT EXISTS(SELECT 1 FROM tbl_danhmuc d WHERE d.tendanhmuc COLLATE utf8mb4_unicode_ci = c.category COLLATE utf8mb4_unicode_ci);

INSERT INTO tbl_sanpham(tensanpham,masp,giasp,soluong,hinhanh,tomtat,noidung,tinhtrang,id_danhmuc)
SELECT c.title,c.sku,c.demo_price,1000,c.image_file,
 '[DEMO] Giá minh họa, không phải giá bán thực tế.',
 '[DEMO] Chỉ để thử sản phẩm, đơn hàng, quản trị và biểu đồ.',1,d.id_danhmuc
FROM bh_demo_catalog c JOIN tbl_danhmuc d ON d.tendanhmuc COLLATE utf8mb4_unicode_ci = c.category COLLATE utf8mb4_unicode_ci
WHERE NOT EXISTS(SELECT 1 FROM tbl_sanpham p WHERE p.masp COLLATE utf8mb4_unicode_ci = c.sku COLLATE utf8mb4_unicode_ci);

INSERT INTO bh_demo_products(n,product_id,product_name,base_price,is_phone)
SELECT c.n,p.id_sanpham,p.tensanpham,p.giasp,IF(c.category='[DEMO] Điện thoại',1,0)
FROM bh_demo_catalog c JOIN tbl_sanpham p ON p.masp COLLATE utf8mb4_unicode_ci = c.sku COLLATE utf8mb4_unicode_ci;

INSERT INTO tbl_product_variants(product_id,variant_code,price,is_active)
SELECT p.product_id,v.variant,p.base_price+v.extra,1
FROM bh_demo_products p CROSS JOIN (
 SELECT '128GB' AS variant,0 AS extra UNION ALL SELECT '256GB',2000000
 UNION ALL SELECT '512GB',5000000 UNION ALL SELECT '1TB',10000000
) v
WHERE p.is_phone=1 AND NOT EXISTS(
 SELECT 1 FROM tbl_product_variants pv
 WHERE pv.product_id=p.product_id AND pv.variant_code COLLATE utf8mb4_unicode_ci = v.variant COLLATE utf8mb4_unicode_ci
);

-- Fake email addresses do not receive messages; invalid hashes prevent login.
INSERT INTO tbl_dangky(tenkhachhang,email,diachi,matkhau,dienthoai)
SELECT CONCAT('[DEMO] Khách ',LPAD(s.n,2,'0')),
 CONCAT('banhang-demo-',LPAD(s.n,2,'0'),'@example.invalid'),
 '[DEMO] Địa chỉ giả, không giao hàng','!DEMO_LOGIN_DISABLED!','0000000000'
FROM bh_demo_nums s WHERE s.n<=20
AND NOT EXISTS (
 SELECT 1 FROM tbl_dangky u
 WHERE u.email COLLATE utf8mb4_unicode_ci = CONCAT('banhang-demo-',LPAD(s.n,2,'0'),'@example.invalid') COLLATE utf8mb4_unicode_ci
);
INSERT INTO bh_demo_customers(n,user_id,display_name)
SELECT s.n,u.id_dangky,u.tenkhachhang
FROM bh_demo_nums s JOIN tbl_dangky u
 ON u.email COLLATE utf8mb4_unicode_ci = CONCAT('banhang-demo-',LPAD(s.n,2,'0'),'@example.invalid') COLLATE utf8mb4_unicode_ci
WHERE s.n<=20;

-- Stable 1-3 item lines per order; prices read from existing demo product/variant DB.
INSERT INTO bh_demo_lines(n,line_no,product_id,product_name,variant_label,quantity,unit_price)
SELECT s.n,x.line_no,p.product_id,p.product_name,
 IF(p.is_phone=1,ELT(MOD(s.n+x.line_no,4)+1,'128GB','256GB','512GB','1TB'),'BASE'),
 IF(MOD(s.n+x.line_no,8)=0,2,1),
 IF(p.is_phone=1,v.price,p.base_price)
FROM bh_demo_nums s
JOIN (SELECT 1 AS line_no UNION ALL SELECT 2 UNION ALL SELECT 3) x
 ON x.line_no<=1+MOD(s.n,3)
JOIN bh_demo_products p ON p.n=1+MOD(s.n*7+x.line_no*5,16)
LEFT JOIN tbl_product_variants v ON v.product_id=p.product_id
 AND v.variant_code=ELT(MOD(s.n+x.line_no,4)+1,'128GB','256GB','512GB','1TB');

-- 240 orders over past 12-115 days; all data explicitly labelled DEMO.
-- Status pattern: 60 PENDING, 40 CONFIRMED, 20 PREPARING,
-- 40 SHIPPING, 40 COMPLETED, 40 CANCELLED. 80 simulated PAID.
INSERT INTO tbl_orders(
 order_code,user_id,customer_name,phone,delivery_address,note,total_amount,
 payment_method,payment_status,order_status,payment_review_status,payment_reported_at,
 customer_transfer_note,bank_reference,payment_verified_at,cod_paid_at,
 shipping_carrier,tracking_code,shipped_at,delivered_at,cancel_reason,created_at,updated_at
)
SELECT CONCAT('BHDEMO',LPAD(s.n,4,'0')),
 u.user_id,u.display_name,'0000000000','[DEMO] Không giao hàng',
 '[DEMO] Đơn giả lập - không thu tiền, không giao hàng',
 (SELECT SUM(l.quantity*l.unit_price) FROM bh_demo_lines l WHERE l.n=s.n),
 IF(MOD(s.n,12) IN (0,2,7,9,10,11),'BANK','COD'),
 IF(MOD(s.n,12) IN (6,7,10,11),'PAID','UNPAID'),
 CASE MOD(s.n,12) WHEN 0 THEN 'PENDING' WHEN 1 THEN 'PENDING' WHEN 2 THEN 'PENDING' WHEN 3 THEN 'CONFIRMED' WHEN 4 THEN 'PREPARING' WHEN 5 THEN 'SHIPPING' WHEN 6 THEN 'COMPLETED' WHEN 7 THEN 'COMPLETED' WHEN 8 THEN 'CANCELLED' WHEN 9 THEN 'CANCELLED' WHEN 10 THEN 'CONFIRMED' ELSE 'SHIPPING' END,
 CASE WHEN MOD(s.n,12)=2 THEN 'REPORTED' WHEN MOD(s.n,12) IN (7,10,11) THEN 'VERIFIED' ELSE 'NONE' END,
 IF(MOD(s.n,12)=2,DATE_ADD(DATE_SUB(NOW(), INTERVAL (12+MOD(s.n*7,104)) DAY),INTERVAL 30 MINUTE),NULL),
 IF(MOD(s.n,12)=2,'[DEMO] Báo chuyển khoản giả lập',NULL),
 IF(MOD(s.n,12) IN (7,10,11),CONCAT('DEMO-REF-',LPAD(s.n,5,'0')),NULL),
 IF(MOD(s.n,12) IN (7,10,11),DATE_ADD(DATE_SUB(NOW(), INTERVAL (12+MOD(s.n*7,104)) DAY),INTERVAL 1 DAY),NULL),
 IF(MOD(s.n,12)=6,DATE_ADD(DATE_SUB(NOW(), INTERVAL (12+MOD(s.n*7,104)) DAY),INTERVAL 4 DAY),NULL),
 IF(MOD(s.n,12) IN (5,6,7,11),'[DEMO] Vận chuyển giả lập',''),
 IF(MOD(s.n,12) IN (5,6,7,11),CONCAT('DEMO-TRACK-',LPAD(s.n,5,'0')),''),
 IF(MOD(s.n,12) IN (5,6,7,11),DATE_ADD(DATE_SUB(NOW(), INTERVAL (12+MOD(s.n*7,104)) DAY),INTERVAL 2 DAY),NULL),
 IF(MOD(s.n,12) IN (6,7),DATE_ADD(DATE_SUB(NOW(), INTERVAL (12+MOD(s.n*7,104)) DAY),INTERVAL 3 DAY),NULL),
 IF(MOD(s.n,12) IN (8,9),'[DEMO] Khách đổi ý',''),
 DATE_SUB(NOW(), INTERVAL (12+MOD(s.n*7,104)) DAY),
 DATE_ADD(DATE_SUB(NOW(), INTERVAL (12+MOD(s.n*7,104)) DAY),INTERVAL CASE
   WHEN MOD(s.n,12) IN (6,7) THEN 4 WHEN MOD(s.n,12) IN (5,6,7,11) THEN 3
   WHEN MOD(s.n,12) IN (8,9,4,10) THEN 2 WHEN MOD(s.n,12)=3 THEN 1
   ELSE 0 END DAY)
FROM bh_demo_nums s
JOIN bh_demo_customers u ON u.n=1+MOD(s.n*7,20)
WHERE NOT EXISTS(SELECT 1 FROM tbl_orders o WHERE o.order_code COLLATE utf8mb4_unicode_ci = CONCAT('BHDEMO',LPAD(s.n,4,'0')) COLLATE utf8mb4_unicode_ci);

-- On second import no order is considered fresh: no duplicated lines or stock.
INSERT INTO bh_demo_fresh(n,order_id)
SELECT s.n,o.id FROM bh_demo_nums s
JOIN bh_demo_customers u ON u.n=1+MOD(s.n*7,20)
JOIN tbl_orders o ON o.order_code COLLATE utf8mb4_unicode_ci = CONCAT('BHDEMO',LPAD(s.n,4,'0')) COLLATE utf8mb4_unicode_ci AND o.user_id=u.user_id
WHERE NOT EXISTS(SELECT 1 FROM tbl_order_items i WHERE i.order_id=o.id);

INSERT INTO tbl_order_items(order_id,product_id,product_name,variant_label,unit_price,quantity)
SELECT f.order_id,l.product_id,l.product_name,l.variant_label,l.unit_price,l.quantity
FROM bh_demo_fresh f JOIN bh_demo_lines l ON l.n=f.n;

-- Only active orders reserve stock; cancelled demo orders reserve nothing.
INSERT INTO bh_demo_stock_need(product_id,units_required)
SELECT l.product_id,SUM(l.quantity)
FROM bh_demo_fresh f
JOIN tbl_orders o ON o.id=f.order_id AND o.order_status<>'CANCELLED'
JOIN bh_demo_lines l ON l.n=f.n
GROUP BY l.product_id;

SET @bh_demo_not_enough = (
 SELECT COUNT(*) FROM bh_demo_stock_need n
 JOIN tbl_sanpham p ON p.id_sanpham=n.product_id
 WHERE p.soluong < n.units_required
);
SET @bh_demo_guard_sql = IF(@bh_demo_not_enough=0,
 'SELECT 1 AS demo_stock_sufficient',
 'SELECT * FROM BANHANG_DEMO_STOP_INSUFFICIENT_STOCK');
PREPARE bh_guard FROM @bh_demo_guard_sql;
EXECUTE bh_guard;
DEALLOCATE PREPARE bh_guard;

-- Window sums provide deterministic before/after stock values per inserted item.
INSERT INTO tbl_inventory_movements(
 product_id,order_id,movement_type,delta,stock_before,stock_after,
 reason,actor_type,actor_id,created_at
)
SELECT l.product_id,f.order_id,'ORDER_DEDUCT',-l.quantity,
 p.soluong - COALESCE(SUM(l.quantity) OVER(
   PARTITION BY l.product_id ORDER BY l.n,l.line_no
   ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING),0),
 p.soluong - SUM(l.quantity) OVER(
   PARTITION BY l.product_id ORDER BY l.n,l.line_no
   ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW),
 '[DEMO] Xuất kho giả lập','SYSTEM',0,o.created_at
FROM bh_demo_fresh f
JOIN tbl_orders o ON o.id=f.order_id AND o.order_status<>'CANCELLED'
JOIN bh_demo_lines l ON l.n=f.n
JOIN tbl_sanpham p ON p.id_sanpham=l.product_id;

UPDATE tbl_sanpham p JOIN bh_demo_stock_need n ON n.product_id=p.id_sanpham
SET p.soluong=p.soluong-n.units_required;

-- Accurate status progression for demo timeline.
INSERT INTO tbl_order_status_history(
 order_id,from_status,to_status,actor_type,actor_id,note,is_public,created_at)
SELECT f.order_id,NULL,'PENDING','SYSTEM',0,'[DEMO] Đặt đơn giả lập',1,o.created_at
FROM bh_demo_fresh f JOIN tbl_orders o ON o.id=f.order_id;
INSERT INTO tbl_order_status_history(
 order_id,from_status,to_status,actor_type,actor_id,note,is_public,created_at)
SELECT f.order_id,'PENDING','CONFIRMED','SYSTEM',0,'[DEMO] Đơn đã xác nhận',1,DATE_ADD(o.created_at,INTERVAL 1 DAY)
FROM bh_demo_fresh f JOIN tbl_orders o ON o.id=f.order_id
WHERE o.order_status IN ('CONFIRMED','PREPARING','SHIPPING','COMPLETED');
INSERT INTO tbl_order_status_history(
 order_id,from_status,to_status,actor_type,actor_id,note,is_public,created_at)
SELECT f.order_id,'CONFIRMED','PREPARING','SYSTEM',0,'[DEMO] Đơn đang chuẩn bị',1,DATE_ADD(o.created_at,INTERVAL 1 DAY)
FROM bh_demo_fresh f JOIN tbl_orders o ON o.id=f.order_id
WHERE o.order_status IN ('PREPARING','SHIPPING','COMPLETED');
INSERT INTO tbl_order_status_history(
 order_id,from_status,to_status,actor_type,actor_id,note,is_public,created_at)
SELECT f.order_id,'PREPARING','SHIPPING','SYSTEM',0,'[DEMO] Đơn đang giao',1,DATE_ADD(o.created_at,INTERVAL 2 DAY)
FROM bh_demo_fresh f JOIN tbl_orders o ON o.id=f.order_id
WHERE o.order_status IN ('SHIPPING','COMPLETED');
INSERT INTO tbl_order_status_history(
 order_id,from_status,to_status,actor_type,actor_id,note,is_public,created_at)
SELECT f.order_id,'SHIPPING','COMPLETED','SYSTEM',0,'[DEMO] Đã giao giả lập',1,DATE_ADD(o.created_at,INTERVAL 3 DAY)
FROM bh_demo_fresh f JOIN tbl_orders o ON o.id=f.order_id
WHERE o.order_status='COMPLETED';
INSERT INTO tbl_order_status_history(
 order_id,from_status,to_status,actor_type,actor_id,note,is_public,created_at)
SELECT f.order_id,'PENDING','CANCELLED','SYSTEM',0,'[DEMO] Đơn đã hủy; không giữ tồn kho',1,DATE_ADD(o.created_at,INTERVAL 2 DAY)
FROM bh_demo_fresh f JOIN tbl_orders o ON o.id=f.order_id
WHERE o.order_status='CANCELLED';

-- No bank details are seeded: demo BANK orders must NOT be paid by customers.
-- References/events marked [DEMO] and do not represent actual bank statements.
INSERT INTO tbl_payment_events(order_id,event_type,actor_type,actor_id,detail,reference_code,created_at)
SELECT f.order_id,'CUSTOMER_REPORTED','CUSTOMER',o.user_id,
 '[DEMO] Báo chuyển khoản giả lập, không có tiền thật',NULL,o.payment_reported_at
FROM bh_demo_fresh f JOIN tbl_orders o ON o.id=f.order_id
WHERE o.payment_review_status='REPORTED';
INSERT INTO tbl_payment_events(order_id,event_type,actor_type,actor_id,detail,reference_code,created_at)
SELECT f.order_id,'ADMIN_CONFIRMED','ADMIN',0,
 '[DEMO] Giả lập đối soát, không có giao dịch tiền thật',o.bank_reference,o.payment_verified_at
FROM bh_demo_fresh f JOIN tbl_orders o ON o.id=f.order_id
WHERE o.payment_method='BANK' AND o.payment_status='PAID';
INSERT INTO tbl_payment_events(order_id,event_type,actor_type,actor_id,detail,reference_code,created_at)
SELECT f.order_id,'ADMIN_COD_PAID','ADMIN',0,
 '[DEMO] Giả lập thu COD, không có tiền thật',NULL,o.cod_paid_at
FROM bh_demo_fresh f JOIN tbl_orders o ON o.id=f.order_id
WHERE o.payment_method='COD' AND o.payment_status='PAID';

INSERT INTO tbl_notifications(
 user_id,order_id,notification_type,dedup_key,title,message,link_url,is_read,created_at)
SELECT o.user_id,o.id,'ORDER_CREATED',CONCAT('demo_created:',o.id),
 '[DEMO] Đơn hàng giả lập',
 CONCAT('Đơn ',o.order_code,' chỉ dùng cho kiểm thử, không thu tiền/giao hàng.'),
 CONCAT('index.php?page=order&code=',o.order_code),1,o.created_at
FROM bh_demo_fresh f JOIN tbl_orders o ON o.id=f.order_id;

-- 40 examples of cancellation requests: 20 pending, 20 declined.
INSERT INTO tbl_order_cancel_requests(
 order_id,user_id,reason,status,admin_note,created_at,resolved_at,resolved_by)
SELECT o.id,o.user_id,'[DEMO] Khách muốn hủy đơn',
 IF(MOD(f.n,24) IN (2,4),'PENDING','DECLINED'),
 IF(MOD(f.n,24) IN (14,16),'[DEMO] Yêu cầu mẫu đã bị từ chối',''),
 DATE_ADD(o.created_at,INTERVAL 1 DAY),
 IF(MOD(f.n,24) IN (14,16),DATE_ADD(o.created_at,INTERVAL 2 DAY),NULL),
 NULL
FROM bh_demo_fresh f JOIN tbl_orders o ON o.id=f.order_id
WHERE MOD(f.n,24) IN (2,4,14,16);

COMMIT;

SELECT 'Demo products' AS metric,COUNT(*) AS total FROM tbl_sanpham WHERE masp LIKE 'BH-DEMO-P%'
UNION ALL SELECT 'Demo customers',COUNT(*) FROM tbl_dangky WHERE email LIKE 'banhang-demo-%@example.invalid'
UNION ALL SELECT 'Demo orders',COUNT(*) FROM tbl_orders WHERE order_code LIKE 'BHDEMO%'
UNION ALL SELECT 'Demo order items',COUNT(*) FROM tbl_order_items i JOIN tbl_orders o ON o.id=i.order_id WHERE o.order_code LIKE 'BHDEMO%'
UNION ALL SELECT 'Pending demo cancellation requests',COUNT(*) FROM tbl_order_cancel_requests c JOIN tbl_orders o ON o.id=c.order_id WHERE o.order_code LIKE 'BHDEMO%' AND c.status='PENDING';

DROP TEMPORARY TABLE IF EXISTS bh_demo_nums;
DROP TEMPORARY TABLE IF EXISTS bh_demo_catalog;
DROP TEMPORARY TABLE IF EXISTS bh_demo_products;
DROP TEMPORARY TABLE IF EXISTS bh_demo_customers;
DROP TEMPORARY TABLE IF EXISTS bh_demo_lines;
DROP TEMPORARY TABLE IF EXISTS bh_demo_fresh;
DROP TEMPORARY TABLE IF EXISTS bh_demo_stock_need;
