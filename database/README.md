# BanHang — Dữ liệu mẫu cho đồ án môn học

File SQL: [seed_demo_orders.sql](seed_demo_orders.sql)

Đây là **dữ liệu để chạy thử website bán thiết bị công nghệ**, không phải dữ liệu từ khách mua hàng thực tế. File seed chạy **thủ công**, không được gọi bởi `banhang.sql`.

## Sau khi nạp

| Thành phần | Số lượng |
| --- | ---: |
| Sản phẩm mẫu đang hiển thị để mua | **50** |
| Danh mục sản phẩm | 5 danh mục chung: Điện thoại, Laptop, Tai nghe & Loa, Phụ kiện điện tử, Đồng hồ |
| Khách hàng mẫu (không đăng nhập được) | 20 |
| Đơn hàng mẫu, mã `BHDEMO0001` đến `BHDEMO0240` | 240 |
| Chi tiết sản phẩm trong đơn | 480 |
| Yêu cầu hủy đang chờ | 20 |
| Yêu cầu hủy đã từ chối | 20 |

Các tên máy như **iPhone 15/16/17, Samsung Galaxy, OPPO, ASUS, Dell, AirPods**, phụ kiện và đồng hồ có **ảnh thực sự có sẵn trong thư mục `images/`**. Tên trên frontend bình thường, **không có tiền tố [DEMO]**. Giá/tồn kho chỉ mang tính chất minh họa cho bài học và có thể sửa trong Admin.

Mọi sản phẩm dùng chung bảng `tbl_sanpham` và các danh mục thật trong `tbl_danhmuc`. Không có bảng hàng riêng; các sản phẩm trước đó mang SKU `BH-DEMO-P001`–`P016` được chuyển thành `BH-TECH-P001`–`P016` ngay trên **chính ID cũ** để giữ lịch sử đơn hàng và tồn kho. Import lại không tạo sản phẩm/đơn trùng, không trừ kho lần hai.

## Chạy trên database XAMPP đang sử dụng: web_mysqli

1. Trong phpMyAdmin, **Export/backup database `web_mysqli`** để giữ dữ liệu hiện tại.
2. Cập nhật source từ GitHub: `git pull origin main`.
3. Mở **database/seed_demo_orders.sql** trong VS Code. Vì đây là database local mày chủ động dùng cho bài tập, đổi:

   ~~~sql
   SET @bh_demo_allow_existing_database = 0;
   ~~~

   thành:

   ~~~sql
   SET @bh_demo_allow_existing_database = 1;
   ~~~

4. Chọn tên **database `web_mysqli`** trong phpMyAdmin (không chọn một bảng như tbl_sanpham), rồi Import file SQL vừa sửa.
5. Refresh website. Vào `index.php?page=products` sẽ thấy các sản phẩm mới được bật (`tinhtrang=1`). Admin cũng hiển thị chúng trong cùng hệ thống sản phẩm.

Nếu database đã có 6 sản phẩm trước đó, sau import dự kiến có khoảng **56 sản phẩm đang mở bán** (tùy dữ liệu đã sửa/xóa). Mã `BHDEMO...` vẫn được giữ cho 240 đơn mẫu để tránh nhầm với giao dịch ngân hàng thật.

### Kiểm tra ngay trong phpMyAdmin

~~~sql
SELECT COUNT(*) AS so_san_pham_mau_dang_ban
FROM tbl_sanpham WHERE masp LIKE 'BH-TECH-P%' AND tinhtrang=1;

SELECT COUNT(*) AS so_don_hang_mau
FROM tbl_orders WHERE order_code LIKE 'BHDEMO%';

SELECT tendanhmuc FROM tbl_danhmuc
WHERE tendanhmuc LIKE '[DEMO]%';
~~~

Kết quả lần lượt mong đợi là **50**, **240**, **0 dòng**. Nếu import lại, các con số này không tăng.

## Lưu ý về dữ liệu giả lập

- Một số đơn hàng được đánh dấu **PAID** chỉ để kiểm thử báo cáo doanh thu và đối soát. Không có chuyển khoản thật. **Không nạp file seed này lên cửa hàng đang kinh doanh thật**.
- Những khách hàng có email `example.invalid` không đăng nhập được, không gửi email ra ngoài.
- SQL không đổi tài khoản MB Bank của bạn, không tự tạo webhook, không gửi email, không xóa đơn người dùng.
- Giá và số lượng mẫu có thể chỉnh từ Admin sau khi nạp; reimport sẽ không ghi đè giá hoặc tồn kho sản phẩm đã có.
- Sản phẩm đã được tham chiếu trong lịch sử đơn không bị xóa; việc cập nhật diễn ra ngay tại ID cũ.

### Lỗi thường gặp

**#1146: BANHANG_DEMO_SEED_BLOCKED...**: Chưa bật opt-in cho `web_mysqli`; thay 0 thành 1 trong file SQL trước khi import.

**#1267: Illegal mix of collations**: Lấy bản seed mới nhất; SQL đã chỉ định COLLATE khi so sánh dữ liệu từ schema MySQL cũ, không phải tự sửa collation của toàn database.

**Sản phẩm vẫn chỉ có 6 món**: Đã kéo code nhưng chưa **reimport file SQL mới**; hoặc đang chọn nhầm database. Kiểm tra bằng các câu SQL trên, rồi hard refresh (`Ctrl+F5`).
