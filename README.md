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
5. Vào trang quản trị → Sản phẩm, kiểm tra những giá dung lượng được chuyển từ frontend cũ (128GB = giá gốc, 256GB +2 triệu, 512GB +5 triệu, 1TB +10 triệu), rồi sửa giá nếu cần. Đây là giá **đã từng hiển thị trên frontend**, không phải báo giá thị trường đã được xác minh.
6. Dùng một đơn hàng thử COD để đối chiếu: trang sản phẩm → giỏ → checkout → đơn hàng. Kiểm tra MB Bank (970422, 0973258529) và tên người nhận thực tế trước khi mở nhận chuyển khoản.

**Không import lại file `web_mysqli.sql` mẫu hoặc bản SQL cũ lên database đang có dữ liệu.**
Bản export riêng của mày vẫn cần giữ để khôi phục nếu có sự cố, đừng đẩy dữ liệu khách hàng lên GitHub.

## Giá sản phẩm thống nhất
- Điện thoại 128GB: từ `tbl_product_variants` (mới), ban đầu được đưa từ **giá thật** `tbl_sanpham.giasp` (cũ).
- Điện thoại 256GB / 512GB / 1TB: `banhang.sql` đưa mức giá từng hiển thị trên frontend cũ vào `tbl_product_variants` bằng `INSERT IGNORE`. Không ghi đè cấu hình Admin đã lưu. Chủ shop nên xác nhận lại các mức giá trước khi nhận thanh toán.
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

## Đồng bộ dữ liệu FE → MySQL
- Dữ liệu sản phẩm thật (Samsung S24 Ultra, Sony WH-1000XM5, Anker...) vốn đã nằm trong `tbl_sanpham` của bản export người dùng gửi.
- Thiếu phần bản dung lượng 1TB trong `tbl_product_variants`. Import lại duy nhất `banhang.sql` bổ sung hàng 1TB và các bản còn thiếu theo đúng giá giao diện cũ đã từng hiện, không sửa/xóa dữ liệu khách hàng hay đè giá Admin.
- Ví dụ Samsung S24 Ultra: gốc 27.500.000đ, 1TB 37.500.000đ. Sau khi import, sản phẩm, giỏ hàng và checkout đều đọc một giá từ SQL.
- Backend không thể tự đẩy dữ liệu vào MySQL `localhost` của máy người dùng qua GitHub; phải import SQL trên XAMPP một lần.

## Ảnh sản phẩm và banner
- PHP `safe_image()` ưu tiên ảnh do cửa hàng upload tại `admin/modules/.../uploads/`, sau đó tìm `images/` của dự án.
- Khi dump cũ nhắc tới `iphone15.jpg`, `tintuc1.jpg`, `tintuc2.jpg` nhưng file không tồn tại, có mapping đến ảnh sẵn trong repository.
- Với 5 mẫu demo thiếu ảnh tại máy, frontend trỏ đến hình đúng sản phẩm từ Samsung, Apple, ASUS, Sony và ảnh Anker A1384 được tra cứu trên nhà bán lẻ. Những ảnh này tải từ mạng, không được lưu vào database hoặc tự động tải xuống GitHub.
- Trong sản phẩm thật, hãy upload ảnh đã được phép sử dụng tại trang Admin để ảnh hoạt động offline, tải ổn định và không phụ thuộc website thứ ba.
- Cải thiện banner, danh mục, ảnh chi tiết, giỏ hàng và ảnh tin tức. Ảnh hỏng có ảnh minh họa danh mục thay thế.


## VietQR thủ công (không cần API ngân hàng)

1. Khách đặt đơn `BANK` để lấy QR MB Bank kèm số tiền và mã đơn. Trạng thái vẫn `UNPAID`.
2. Khách chuyển khoản xong có thể bấm **Tôi đã chuyển khoản – gửi kiểm tra**. Website chỉ lưu báo cáo `REPORTED`, chưa tự nhận tiền hay đánh dấu đã thanh toán.
3. **Admin → Thanh toán → Chờ đối soát**. Kiểm tra khoản **tiền vào thực tế** trên sao kê MB Bank: tài khoản nhận, số tiền, mã đơn, mã giao dịch.
4. Nhập mã giao dịch ngân hàng (duy nhất) và số tiền thực nhận; xác nhận kiểm tra sao kê. Chỉ lúc này đơn chuyển sang `PAID`, đồng thời lưu thời gian, Admin xác nhận và nhật ký trong `tbl_payment_events`.
5. Nếu khách báo nhưng chưa có giao dịch đối chiếu được, Admin bấm **Báo chưa tìm thấy giao dịch**. Khách có thể báo lại khi đã kiểm tra.
6. Đơn `BANK` chưa `PAID` không được xác nhận/giao hàng. Đơn `PENDING` quá 24 giờ chưa thanh toán và không có báo cáo đang chờ có thể **hủy thủ công** trong Admin và hoàn lại số lượng kho. Không có job tự chạy hay auto-refund.
7. Luôn **backup database** rồi import lại chỉ file `banhang.sql` mới để tạo các cột đối soát và bảng sự kiện trước khi kéo code PHP. Không cần API MB Bank, webhook hoặc OTP.
8. Nút khách báo, ảnh chụp giao dịch và QR chỉ là thông tin; không phải bằng chứng ngân hàng đã nhận tiền. Nếu tiền đến sau khi đơn đã hủy thì liên hệ và xử lý hoàn tiền/thỏa thuận lại bằng tay.
