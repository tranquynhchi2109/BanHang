<?php
session_start();
include('../../admin/config/config.php');

// 1. Thêm số lượng (Cộng)
if(isset($_GET['cong'])){
    $id = $_GET['cong'];
    $product = array();
    foreach($_SESSION['cart'] as $cart_item){
        if($cart_item['id'] == $id){
            $tangsoluong = $cart_item['soluong'] + 1;
            if($cart_item['soluong'] < 10){
                $product[] = array('tensanpham'=>$cart_item['tensanpham'],'id'=>$cart_item['id'],'soluong'=>$tangsoluong,'giasp'=>$cart_item['giasp'],'hinhanh'=>$cart_item['hinhanh'],'masp'=>$cart_item['masp'],'dungluong'=>$cart_item['dungluong']);
            }else{
                $product[] = array('tensanpham'=>$cart_item['tensanpham'],'id'=>$cart_item['id'],'soluong'=>$cart_item['soluong'],'giasp'=>$cart_item['giasp'],'hinhanh'=>$cart_item['hinhanh'],'masp'=>$cart_item['masp'],'dungluong'=>$cart_item['dungluong']);
            }
        }else{
            $product[] = array('tensanpham'=>$cart_item['tensanpham'],'id'=>$cart_item['id'],'soluong'=>$cart_item['soluong'],'giasp'=>$cart_item['giasp'],'hinhanh'=>$cart_item['hinhanh'],'masp'=>$cart_item['masp'],'dungluong'=>$cart_item['dungluong']);
        }
    }
    $_SESSION['cart'] = $product;
    header("Location:../../index.php?quanly=giohang");
}

// 2. Trừ số lượng
if(isset($_GET['tru'])){
    $id = $_GET['tru'];
    $product = array();
    foreach($_SESSION['cart'] as $cart_item){
        if($cart_item['id'] == $id){
            $giamsoluong = $cart_item['soluong'] - 1;
            if($cart_item['soluong'] > 1){
                $product[] = array('tensanpham'=>$cart_item['tensanpham'],'id'=>$cart_item['id'],'soluong'=>$giamsoluong,'giasp'=>$cart_item['giasp'],'hinhanh'=>$cart_item['hinhanh'],'masp'=>$cart_item['masp'],'dungluong'=>$cart_item['dungluong']);
            }else{
                $product[] = array('tensanpham'=>$cart_item['tensanpham'],'id'=>$cart_item['id'],'soluong'=>$cart_item['soluong'],'giasp'=>$cart_item['giasp'],'hinhanh'=>$cart_item['hinhanh'],'masp'=>$cart_item['masp'],'dungluong'=>$cart_item['dungluong']);
            }
        }else{
            $product[] = array('tensanpham'=>$cart_item['tensanpham'],'id'=>$cart_item['id'],'soluong'=>$cart_item['soluong'],'giasp'=>$cart_item['giasp'],'hinhanh'=>$cart_item['hinhanh'],'masp'=>$cart_item['masp'],'dungluong'=>$cart_item['dungluong']);
        }
    }
    $_SESSION['cart'] = $product;
    header("Location:../../index.php?quanly=giohang");
}

// 3. Xóa sản phẩm
if(isset($_SESSION['cart']) && isset($_GET['xoa'])){
    $id = $_GET['xoa'];
    $product = array();
    foreach($_SESSION['cart'] as $cart_item){
        if($cart_item['id'] != $id){
            $product[] = array('tensanpham'=>$cart_item['tensanpham'],'id'=>$cart_item['id'],'soluong'=>$cart_item['soluong'],'giasp'=>$cart_item['giasp'],'hinhanh'=>$cart_item['hinhanh'],'masp'=>$cart_item['masp'],'dungluong'=>$cart_item['dungluong']);
        }
    }
    $_SESSION['cart'] = $product;
    header("Location:../../index.php?quanly=giohang");
}

// 4. Xóa tất cả
if(isset($_GET['xoatatca']) && $_GET['xoatatca'] == 1){
    unset($_SESSION['cart']);
    header('Location:../../index.php?quanly=giohang');
}

// 5. THÊM MỚI VÀO GIỎ HÀNG (QUAN TRỌNG NHẤT)
if(isset($_POST['themgiohang'])){
    $id = $_GET['idsanpham'];
    $soluong = 1;
    $dungluong = $_POST['dungluong']; // Lấy giá trị từ radio button

    $sql = "SELECT * FROM tbl_sanpham WHERE id_sanpham='".$id."' LIMIT 1";
    $query = mysqli_query($mysqli, $sql);
    $row = mysqli_fetch_array($query);

    if($row){
        $new_product = array(array('tensanpham'=>$row['tensanpham'], 'id'=>$id, 'soluong'=>$soluong, 'giasp'=>$row['giasp'], 'hinhanh'=>$row['hinhanh'], 'masp'=>$row['masp'], 'dungluong'=>$dungluong));

        if(isset($_SESSION['cart'])){
            $found = false;
            $product = array();
            foreach($_SESSION['cart'] as $cart_item){
                // Nếu trùng cả ID và Dung lượng thì tăng số lượng
                if($cart_item['id'] == $id && $cart_item['dungluong'] == $dungluong){
                    $product[] = array('tensanpham'=>$cart_item['tensanpham'],'id'=>$cart_item['id'],'soluong'=>$cart_item['soluong']+1,'giasp'=>$cart_item['giasp'],'hinhanh'=>$cart_item['hinhanh'],'masp'=>$cart_item['masp'],'dungluong'=>$cart_item['dungluong']);
                    $found = true;
                }else{
                    $product[] = array('tensanpham'=>$cart_item['tensanpham'],'id'=>$cart_item['id'],'soluong'=>$cart_item['soluong'],'giasp'=>$cart_item['giasp'],'hinhanh'=>$cart_item['hinhanh'],'masp'=>$cart_item['masp'],'dungluong'=>$cart_item['dungluong']);
                }
            }
            if($found == false){
                $_SESSION['cart'] = array_merge($product, $new_product);
            }else{
                $_SESSION['cart'] = $product;
            }
        }else{
            $_SESSION['cart'] = $new_product;
        }
    }
    header('Location:../../index.php?quanly=giohang');
}
?>