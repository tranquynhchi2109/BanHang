<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "web_mysqli";
// $_POST = '8111';

// Tạo kết nối
$mysqli = new mysqli($servername, $username, $password, $dbname);

// Kiểm tra nếu kết nối thất bại thì dừng chương trình và báo lỗi rõ ràng
if ($mysqli->connect_error) {
    die("Kết nối database thất bại: " . $mysqli->connect_error);
}

$mysqli->set_charset("utf8mb4");
