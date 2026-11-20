<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// LẤY DỮ LIỆU TỪ SESSION
$hoten = $_SESSION['hoten'] ?? '';
$dienthoai = $_SESSION['dienthoai'] ?? '';
$diachi = $_SESSION['diachi'] ?? '';
$phuongthuc = $_SESSION['phuongthuc'] ?? '';
$tongtien = $_SESSION['tongtien'] ?? 0;

// ĐỔI TÊN PHƯƠNG THỨC THANH TOÁN
$pttt = ($phuongthuc === 'ATM')
    ? 'Thẻ tín dụng / ATM'
    : 'Thanh toán tiền mặt khi nhận hàng (COD)';
?>

<div class="order-wrapper">
    <div class="order-box">

        <h2 class="order-title">🎉 Đặt hàng thành công</h2>

        <p class="order-message">
            Chào <strong><?= htmlspecialchars($hoten) ?></strong>,
            đơn hàng của bạn đã được tiếp nhận.
        </p>

        <div class="order-divider"></div>

        <div class="order-info">
            <span class="order-label">Phương thức thanh toán:</span>
            <span class="order-value"><?= htmlspecialchars($pttt) ?></span>
        </div>

        <div class="order-total">
            Tổng tiền thanh toán:
            <?= number_format($tongtien, 0, ',', '.') ?> VNĐ
        </div>

        <div class="order-address">
            <span class="order-label">Địa chỉ giao hàng:</span>
            <?= htmlspecialchars($diachi) ?>
        </div>

        <div class="order-contact">
            Chúng tôi sẽ liên hệ với bạn qua số điện thoại
            <strong><?= htmlspecialchars($dienthoai) ?></strong>
            để xác nhận đơn hàng.
        </div>

        <a href="index.php" class="order-home-btn">🏠 Về trang chủ</a>

    </div>
</div>

<?php
// XÓA SESSION SAU KHI HIỂN THỊ
unset($_SESSION['hoten']);
unset($_SESSION['dienthoai']);
unset($_SESSION['diachi']);
unset($_SESSION['phuongthuc']);
unset($_SESSION['tongtien']);
?>