<?php
// 1. Bật bộ đệm đầu ra để tránh lỗi "Cannot modify header information"
ob_start(); 

// 2. Khởi tạo session để quản lý giỏ hàng và đăng nhập
session_start(); 

// 3. Kết nối cơ sở dữ liệu
include("admin/config/config.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thiết Bị Điện Tử</title>

    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" type="text/css" href="css/style.css">

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
</head>
<body>
    <div class="wrapper">
        <?php
            // Chia các thành phần giao diện
            include("pages/header.php"); // Đầu trang
            include("pages/menu.php");   // Thanh điều hướng danh mục
            include("pages/main.php");   // Nội dung thay đổi theo biến $_GET['quanly']
            include("pages/footer.php"); // Chân trang
        ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
<?php
// Giải phóng và gửi nội dung từ bộ đệm đến trình duyệt
ob_end_flush(); 
?>