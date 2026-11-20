<?php
session_start();

// Kiểm tra giỏ hàng
if (!isset($_SESSION['cart']) || count($_SESSION['cart']) == 0) {
    echo "<p>Giỏ hàng trống. Không thể thanh toán.</p>";
    exit;
}

// Tính tổng tiền
$tongtien = 0;
foreach ($_SESSION['cart'] as $item) {
    $tongtien += $item['giasp'] * $item['soluong'];
}
?>

<!-- BỌC TOÀN TRANG -->
<div class="checkout-wrapper">
    <div class="checkout-box">

        <h2 class="checkout-title">Thanh toán đơn hàng</h2>

        <form method="POST" action="pages/main/xulythanhtoan.php">

            <!-- THÔNG TIN KHÁCH HÀNG -->
            <h3 class="checkout-subtitle">Thông tin khách hàng</h3>

            <div class="checkout-row">
                <input type="text" name="hoten" placeholder="Họ và tên" required>
                <input type="text" name="dienthoai" placeholder="Số điện thoại" required>
            </div>

            <div class="checkout-row">
                <input type="email" name="email" placeholder="Email">
            </div>

            <textarea name="diachi" placeholder="Địa chỉ giao hàng" required></textarea>

            <!-- PHƯƠNG THỨC THANH TOÁN -->
            <h3 class="checkout-subtitle">Phương thức thanh toán</h3>

            <div class="checkout-radio">
                <label>
                    <input type="radio" name="phuongthuc" value="COD" checked>
                    Thanh toán tiền mặt khi nhận hàng (COD)
                </label>
            </div>

            <div class="checkout-radio">
                <label>
                    <input type="radio" name="phuongthuc" value="ATM">
                    Thanh toán qua thẻ tín dụng / ATM
                </label>
            </div>

            <!-- THÔNG TIN THẺ -->
            <div class="checkout-card" id="cardBox">
                <h3 class="checkout-subtitle">Thông tin thẻ</h3>
                <input type="text" name="sothe" placeholder="Số thẻ">
                <input type="text" name="tenchu" placeholder="Tên chủ thẻ">
                <input type="text" name="ngayhethan" placeholder="MM/YY">
                <input type="text" name="cvv" placeholder="CVV">
            </div>

            <!-- TỔNG TIỀN -->
            <div class="checkout-total">
                Tổng tiền:
                <span><?= number_format($tongtien, 0, ',', '.') ?> VNĐ</span>
            </div>

            <input type="hidden" name="tongtien" value="<?= $tongtien ?>">

            <button type="submit" name="dathang" class="checkout-btn">
                Xác nhận thanh toán
            </button>

        </form>
    </div>
</div>

<!-- JS -->
<script>
const radios = document.querySelectorAll('input[name="phuongthuc"]');
const cardBox = document.getElementById('cardBox');

function toggleCardBox() {
    const value = document.querySelector('input[name="phuongthuc"]:checked').value;
    cardBox.style.display = (value === 'ATM') ? 'block' : 'none';
}
radios.forEach(r => r.addEventListener('change', toggleCardBox));
toggleCardBox();
</script>