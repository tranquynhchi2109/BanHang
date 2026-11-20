<?php
    // 1. Truy vấn lấy tên danh mục (Chỉ lấy 1 dòng từ bảng tbl_danhmuc)
    $id_danhmuc = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $sql_cate = "SELECT * FROM tbl_danhmuc WHERE id_danhmuc = $id_danhmuc LIMIT 1";
    $query_cate = mysqli_query($mysqli, $sql_cate);
    $row_title = mysqli_fetch_array($query_cate);

    // 2. Truy vấn lấy danh sách sản phẩm thuộc danh mục đó
    $sql_pro = "SELECT * FROM tbl_sanpham WHERE id_danhmuc = $id_danhmuc ORDER BY id_sanpham DESC";
    $query_pro = mysqli_query($mysqli, $sql_pro);
?>

<h3>Danh mục sản phẩm: <?php echo isset($row_title['tendanhmuc']) ? htmlspecialchars($row_title['tendanhmuc'], ENT_QUOTES, 'UTF-8') : 'Không tồn tại' ?></h3>

<ul class="product_list">
    <?php  
    // Vòng lặp này bây giờ sẽ chạy đầy đủ từ sản phẩm đầu tiên đến cuối cùng
    while($row_pro= mysqli_fetch_array($query_pro)){
    ?>
        <li>
            <a href="index.php?quanly=sanpham&id=<?php echo $row_pro['id_sanpham'] ?>">
                <img src="admin/modules/quanlysp/uploads/<?php echo $row_pro['hinhanh'] ?>">
                <p class="title_product"><?php echo $row_pro['tensanpham'] ?></p>
                <p class="price_product">Giá: <?php echo number_format($row_pro['giasp'], 0, ',', '.') ?>vnd</p>
            </a>
        </li>
    <?php
    }
    ?>
</ul>