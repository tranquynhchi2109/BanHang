# BanHang — PHP + MariaDB

## Một file SQL duy nhất: `banhang.sql`

File **`banhang.sql`** là file SQL duy nhất trong repository để tạo/nâng cấp
cấu trúc. Nó tự đổi bốn bảng `v2_*` thành `tbl_*` nếu an toàn,
bổ sung `tbl_order_items.variant_label`, `tbl_product_variants`,
thiết lập MB Bank và giữ nguyên bảng/dữ liệu cũ.

**Trên máy XAMPP đã có database `web_mysqli`:**

1. Tạm ngừng thử đặt hàng. Trong phpMyAdmin, **Export/Backup toàn bộ** `web_mysqli` và cất bản sao ở nơi riêng.
2. Chọn `web_mysqli`, tab **Import**, chọn duy nhất **`banhang.sql`**.
3. Nếu gặp lỗi **BANHANG_STOP_DUAL_TABLES_BACKUP_AND_RECONCILE**, dừng. Có bảng tên `v2_*` và `tbl_*` trùng nhau; phải so sánh dữ liệu trước khi xử lý, đừng tự DROP.
4. Sau khi nhập SQL thành công, trong PowerShell:
   ```powershell
   cd C:\xampp\htdocs\web_banhang
   git switch main
   git pull origin main
   ```
5. Vào trang quản trị → Sản phẩm, chỉnh giá **256GB / 512GB / 1TB** bằng giá cửa hàng duyệt. Mục nào bỏ trống **không bán**, không tự tính thêm phí.
6. Dùng một đơn hàng thử COD để đối chiếu: trang sản phẩm → giỏ → checkout → đơn hàng. Kiểm tra MB Bank (970422, 0973258529) và tên người nhận thực tế trước khi mở nhận chuyển khoản.

**Không import lại file `web_mysqli.sql` mẫu hoặc bản SQL cũ lên database đang có dữ liệu.**
Bản export riêng của mày vẫn cần giữ để khôi phục nếu có sự cố, đừng đẩy dữ liệu khách hàng lên GitHub.

## Giá sản phẩm thống nhất
- Điện thoại 128GB: từ `tbl_product_variants` (mới), ban đầu được đưa từ **giá thật** `tbl_sanpham.giasp` (cũ).
- Điện thoại 256GB / 512GB / 1TB: chỉ được mở bán sau khi quản trị viên nhập giá chính xác vào `tbl_product_variants`.
- Laptop/phụ kiện/sản phẩm khác: `tbl_sanpham.giasp`.
- Danh sách sản phẩm, chi tiết, giỏ hàng, checkout đều dùng hàm `variant_price()`, đọc từ MySQL.
- Giỏ cũ có dung lượng chưa có giá sẽ được nhắc xóa/chọn lại; không hiển thị sai mức 0đ.
- Khi khách bấm đặt hàng, giá được đọc lại dưới khóa transaction, và được đối chiếu với tổng tiền khách vừa thấy. Giá thay đổi thì phải xem lại.
- Tồn kho hiện vẫn là `tbl_sanpham.soluong` chia sẻ cho các dung lượng; chưa có kho riêng từng dung lượng.

## Bảng dữ liệu
Giữ `tbl_sanpham`, `tbl_dangky`, `tbl_cart`, `tbl_cart_details`, `tbl_admin`...;
bổ sung `tbl_orders`, `tbl_order_items`, `tbl_settings`, `tbl_login_attempts`, `tbl_product_variants`.
Không xóa các đơn cũ. Schema tạo bằng `CREATE TABLE IF NOT EXISTS` và phần đổi tên có bước kiểm tra xung đột trước.

## Phạm vi và bảo mật
Không thu số thẻ/CVV. VietQR tạo thông tin chuyển khoản, chưa xác nhận tiền đến; admin phải đối soát thủ công.
Source chưa kiểm thử đầy đủ trên XAMPP của mày, chưa có chứng nhận bảo mật hay kiểm thử tải.
Hãy thay mật khẩu demo, dùng HTTPS, MySQL user quyền tối thiểu, cập nhật PHP, sao lưu định kỳ.

## Kiểm thử tự động
GitHub Actions: PHP lint, tạo database chỉ bằng `banhang.sql`, chạy lại nhiều lần,
kiểm thử bảo toàn đơn khi đổi tên bảng, kiểm tra giá dung lượng từ SQL
và không có mức phụ thu ẩn trong PHP.
