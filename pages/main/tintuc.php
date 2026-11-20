<?php
    // 1. Đường dẫn tính từ file index.php ngoài cùng
    include_once('admin/config/config.php'); 

    // 2. Truy vấn dữ liệu
    // Thử bỏ "WHERE trangthai=1" để kiểm tra nếu dữ liệu chưa hiện
    $sql_list = "SELECT * FROM tbl_tintuc ORDER BY id_baiviet DESC"; 
    $query_list = mysqli_query($mysqli, $sql_list);

    // Kiểm tra nếu truy vấn lỗi
    if (!$query_list) {
        die("Lỗi truy vấn: " . mysqli_error($mysqli));
    }
?>

<h1 class="page-title">Tin Tức Mới Nhất</h1>

<div class="news-grid">
    <?php while($row = mysqli_fetch_array($query_list)){ ?>
        <div class="news-card">
            <div class="news-image">
                <img src="admin/modules/quanlybaiviet/uploads/<?php echo $row['hinhanh']; ?>" alt="">

            </div>
            <div class="news-info">
                <h3><?php echo $row['tenbaiviet']; ?></h3>
                <p><?php echo $row['tomtat']; ?></p>
                <a href="index.php?quanly=baiviet&id=<?php echo $row['id_baiviet']; ?>" class="btn-xem">Xem chi tiết</a>
            </div>
        </div>
    <?php } ?>
</div>
