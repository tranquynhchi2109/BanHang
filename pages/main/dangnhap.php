<?php
if (isset($_POST['dangnhap'])) {
    $email = trim($_POST['email'] ?? '');
    $matkhau = md5($_POST['password'] ?? '');

    $stmt = $mysqli->prepare("SELECT id_dangky, tenkhachhang FROM tbl_dangky WHERE email = ? AND matkhau = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("ss", $email, $matkhau);
        $stmt->execute();
        $result = $stmt->get_result();
        $row_data = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if ($row_data) {
            $_SESSION['dangky'] = $row_data['tenkhachhang'];
            $_SESSION['id_khachhang'] = $row_data['id_dangky'];
            header("Location: index.php?quanly=giohang");
            exit;
        }
    }
    echo '<p style="color:red">Email hoặc mật khẩu không đúng. Vui lòng nhập lại.</p>';
}
?>
 
<div class="wrapper-login">
    <form action="" autocomplete="off" method="POST" class="form-login">
        <h3>ĐĂNG NHẬP KHÁCH HÀNG</h3>
        
        <div class="form-group">
            <label>Tài khoản</label>
            <input type="text" name="email" placeholder="Email...">
        </div>

        <div class="form-group">
            <label>Mật khẩu</label>
            <input type="password" name="password" placeholder="Mật khẩu...">
        </div>

        <input type="submit" name="dangnhap" class="btn-submit" value="ĐĂNG NHẬP">
        <a href="index.php?quanly=dangky" class="login-redirect">
            Bạn chưa có tài khoản? Đăng kí tại đây</a>
    </form>
</div>