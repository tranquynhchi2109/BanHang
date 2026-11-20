<?php
// KHÔNG include chitietbaiviet.php
// KHÔNG include config.php nếu đã include ở index.php

if (!isset($_GET['id'])) {
    echo "Bài viết không tồn tại";
    exit;
}

$id = (int)$_GET['id'];

$sql = "SELECT * FROM tbl_tintuc WHERE id_baiviet = $id LIMIT 1";
$query = mysqli_query($mysqli, $sql);

if (!$query || mysqli_num_rows($query) == 0) {
    echo "Không tìm thấy bài viết";
    exit;
}

$row = mysqli_fetch_array($query);
?>

<div class="baiviet-container">
    <h1 class="baiviet-title"><?php echo $row['tenbaiviet']; ?></h1>

    <p class="baiviet-date">
        Ngày đăng: <?php echo date('d/m/Y', strtotime($row['ngaydang'])); ?>
    </p>

    <div class="baiviet-image">
         <img src="admin/modules/quanlybaiviet/uploads/<?php echo $row['hinhanh']; ?>" alt="">
</div>


    <div class="baiviet-noidung">
        <?php echo nl2br($row['noidung']); ?>
    </div>
</div>
