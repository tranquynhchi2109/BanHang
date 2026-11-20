<?php
$tukhoa = '';
if(isset($_POST['timkiem']) && !empty($_POST['tukhoa'])){
    $tukhoa = $_POST['tukhoa'];
}

$sql_pro = "SELECT * FROM tbl_sanpham 
JOIN tbl_danhmuc 
ON tbl_sanpham.id_danhmuc = tbl_danhmuc.id_danhmuc
WHERE tbl_sanpham.tensanpham LIKE '%".$tukhoa."%'
OR tbl_danhmuc.tendanhmuc LIKE '%".$tukhoa."%'";

$query_pro = mysqli_query($mysqli, $sql_pro);
?>

<h3>Từ khóa tìm kiếm : <?php echo $tukhoa; ?></h3>

<ul class="product_list">
<?php while($row = mysqli_fetch_array($query_pro)){ ?>
    <li>
        <a href="index.php?quanly=sanpham&id=<?php echo $row['id_sanpham'] ?>">
            <img src="admin/modules/quanlysp/uploads/<?php echo $row['hinhanh'] ?>">
            <p class="title_product"><?php echo $row['tensanpham'] ?></p>
            <p class="price_product">
                Giá: <?php echo number_format($row['giasp'], 0, ',', '.') ?> vnd
            </p>
            <p style="text-align:center;color:#d1d1d1">
                <?php echo $row['tendanhmuc'] ?>
            </p>
        </a>
    </li>
<?php } ?>
</ul>