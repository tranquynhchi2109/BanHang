<?php
if (isset($_POST['dangky'])) {
    $tenkhachhang = trim($_POST['hovaten'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $dienthoai = trim($_POST['dienthoai'] ?? '');
    $diachi = trim($_POST['diachi'] ?? '');
    $matkhau = md5($_POST['matkhau'] ?? '');

    if ($tenkhachhang === '' || $email === '' || $dienthoai === '' || $diachi === '' || ($_POST['matkhau'] ?? '') === '') {
        echo '<p style="color:red">Vui lòng nhập đầy đủ thông tin.</p>';
    } else {
        $stmt = $mysqli->prepare("INSERT INTO tbl_dangky (tenkhachhang, email, diachi, matkhau, dienthoai) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sssss", $tenkhachhang, $email, $diachi, $matkhau, $dienthoai);
            if ($stmt->execute()) {
                $_SESSION['dangky'] = $tenkhachhang;
                $_SESSION['id_khachhang'] = $mysqli->insert_id;
                $stmt->close();
                header("Location: index.php?quanly=giohang");
                exit;
            }
            echo '<p style="color:red">Đăng ký chưa thành công: email có thể đã tồn tại hoặc dữ liệu không hợp lệ.</p>';
            $stmt->close();
        } else {
            echo '<p style="color:red">Không thể tạo truy vấn đăng ký. Kiểm tra cấu trúc bảng tbl_dangky.</p>';
        }
    }
}
?>
<div class="register-wrapper">
    <h3>Đăng ký thành viên</h3>

    <form action="" method="POST">
        <div class="form-group">
            <label>Họ và tên</label>
            <input type="text" name="hovaten" placeholder="Nhập đầy đủ họ và tên">
        </div>

        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" placeholder="Ví dụ: name@gmail.com">
        </div>

        <div class="form-group">
            <label>Điện thoại</label>
            <input type="text" name="dienthoai" placeholder="Nhập số điện thoại của bạn">
        </div>

        <div class="form-group">
            <label>Địa chỉ</label>
            <input type="text" name="diachi" placeholder="Địa chỉ nhận hàng chi tiết">
        </div>

        <div class="form-group">
            <label>Mật khẩu</label>
            <input type="password" name="matkhau" placeholder="Tối thiểu 6 ký tự">
        </div>

        <button type="submit" name="dangky" class="btn-submit">ĐĂNG KÝ NGAY</button>
        
        <a href="index.php?quanly=dangnhap" class="login-redirect">
            Bạn đã có tài khoản? Đăng nhập tại đây
        </a>
    </form>
</div>