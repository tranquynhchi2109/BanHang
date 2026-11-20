<?php
session_start();
include("../../admin/config/config.php");

if (!isset($_SESSION['id_khachhang'])) {
    header('Location: ../../index.php?quanly=dangnhap');
    exit;
}

if (!isset($_SESSION['cart']) || count($_SESSION['cart']) == 0) {
    echo "Giỏ hàng trống";
    exit;
}

// TÍNH TỔNG TIỀN
$tongtien = 0;
foreach ($_SESSION['cart'] as $value) {
    $tongtien += $value['giasp'] * $value['soluong'];
}

$id_khachhang = $_SESSION['id_khachhang'];
$code_order = rand(1000,9999);

// TẠO ĐƠN HÀNG
$insert_cart = mysqli_query($mysqli,
    "INSERT INTO tbl_cart(id_khachhang, code_cart, cart_status, tongtien)
     VALUES('$id_khachhang','$code_order',1,'$tongtien')"
);

if ($insert_cart) {

    foreach ($_SESSION['cart'] as $value) {
        $id_sanpham = $value['id'];
        $soluong = $value['soluong'];

        mysqli_query($mysqli,
            "INSERT INTO tbl_cart_details(id_sanpham, code_cart, soluongmua)
             VALUES('$id_sanpham','$code_order','$soluong')"
        );
    }

    // LƯU ĐỂ CAMON HIỂN THỊ
    $_SESSION['tongtien'] = $tongtien;

    unset($_SESSION['cart']);
    header('Location: ../../index.php?quanly=camon');
    exit;
} else {
    echo "Lỗi tạo đơn hàng";
}