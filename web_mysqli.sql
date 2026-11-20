-- phpMyAdmin SQL Dump
-- Cơ sở dữ liệu: `web_mysqli`
-- Hệ thống website bán hàng điện tử

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Tạo database web_mysqli nếu chưa tồn tại
--
CREATE DATABASE IF NOT EXISTS `web_mysqli` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `web_mysqli`;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tbl_admin`
--

CREATE TABLE IF NOT EXISTS `tbl_admin` (
  `id_admin` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `admin_status` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tbl_admin`
-- Mật khẩu mặc định là: 123456 (md5: e10adc3949ba59abbe56e057f20f883e)
--

INSERT INTO `tbl_admin` (`id_admin`, `username`, `password`, `admin_status`) VALUES
(1, 'admin', 'e10adc3949ba59abbe56e057f20f883e', 1)
ON DUPLICATE KEY UPDATE `username`=VALUES(`username`);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tbl_cart`
--

CREATE TABLE IF NOT EXISTS `tbl_cart` (
  `id_cart` int(11) NOT NULL,
  `id_khachhang` int(11) NOT NULL,
  `code_cart` varchar(20) NOT NULL,
  `cart_status` int(11) NOT NULL DEFAULT 1,
  `cart_date` datetime NOT NULL DEFAULT current_timestamp(),
  `cart_payment` varchar(50) NOT NULL DEFAULT 'COD',
  `tongtien` double NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tbl_cart`
--

INSERT INTO `tbl_cart` (`id_cart`, `id_khachhang`, `code_cart`, `cart_status`, `cart_date`, `cart_payment`, `tongtien`) VALUES
(1, 1, '8492', 1, '2026-11-18 16:30:00', 'COD', 36480000)
ON DUPLICATE KEY UPDATE `code_cart`=VALUES(`code_cart`);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tbl_cart_details`
--

CREATE TABLE IF NOT EXISTS `tbl_cart_details` (
  `id_cart_details` int(11) NOT NULL,
  `code_cart` varchar(20) NOT NULL,
  `id_sanpham` int(11) NOT NULL,
  `soluongmua` int(11) NOT NULL,
  `dungluong` varchar(50) DEFAULT '128GB'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tbl_cart_details`
--

INSERT INTO `tbl_cart_details` (`id_cart_details`, `code_cart`, `id_sanpham`, `soluongmua`, `dungluong`) VALUES
(1, '8492', 1, 1, '256GB'),
(2, '8492', 5, 1, '')
ON DUPLICATE KEY UPDATE `code_cart`=VALUES(`code_cart`);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tbl_dangky`
--

CREATE TABLE IF NOT EXISTS `tbl_dangky` (
  `id_dangky` int(11) NOT NULL,
  `tenkhachhang` varchar(200) NOT NULL,
  `email` varchar(100) NOT NULL,
  `diachi` varchar(200) NOT NULL,
  `matkhau` varchar(100) NOT NULL,
  `dienthoai` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tbl_dangky`
--

INSERT INTO `tbl_dangky` (`id_dangky`, `tenkhachhang`, `email`, `diachi`, `matkhau`, `dienthoai`) VALUES
(1, 'Nguyễn Văn A', 'test@gmail.com', '123 Đường ABC, Quy Nhơn', 'e10adc3949ba59abbe56e057f20f883e', '0987654321')
ON DUPLICATE KEY UPDATE `email`=VALUES(`email`);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tbl_danhmuc`
--

CREATE TABLE IF NOT EXISTS `tbl_danhmuc` (
  `id_danhmuc` int(11) NOT NULL,
  `tendanhmuc` varchar(100) NOT NULL,
  `thutu` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tbl_danhmuc`
--

INSERT INTO `tbl_danhmuc` (`id_danhmuc`, `tendanhmuc`, `thutu`) VALUES
(1, 'Điện thoại', 1),
(2, 'Laptop', 2),
(3, 'Tai nghe & Loa', 3),
(4, 'Phụ kiện điện tử', 4)
ON DUPLICATE KEY UPDATE `tendanhmuc`=VALUES(`tendanhmuc`);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tbl_danhmucbaiviet`
--

CREATE TABLE IF NOT EXISTS `tbl_danhmucbaiviet` (
  `id_baiviet` int(11) NOT NULL,
  `tendanhmuc_baiviet` varchar(255) NOT NULL,
  `thutu` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tbl_danhmucbaiviet`
--

INSERT INTO `tbl_danhmucbaiviet` (`id_baiviet`, `tendanhmuc_baiviet`, `thutu`) VALUES
(1, 'Tin công nghệ', 1),
(2, 'Đánh giá sản phẩm', 2),
(3, 'Khuyến mãi', 3)
ON DUPLICATE KEY UPDATE `tendanhmuc_baiviet`=VALUES(`tendanhmuc_baiviet`);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tbl_lienhe`
--

CREATE TABLE IF NOT EXISTS `tbl_lienhe` (
  `id` int(11) NOT NULL,
  `thongtinlienhe` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tbl_lienhe`
--

INSERT INTO `tbl_lienhe` (`id`, `thongtinlienhe`) VALUES
(1, '<h3>CỬA HÀNG THIẾT BỊ ĐIỆN TỬ</h3><p><strong>Địa chỉ:</strong> 123 Đường ABC, Quy Nhơn, Bình Định</p><p><strong>Hotline:</strong> 0123.456.789</p><p><strong>Email:</strong> support@domain.com</p><p><strong>Giờ làm việc:</strong> 08:00 - 21:30 (Tất cả các ngày trong tuần)</p>')
ON DUPLICATE KEY UPDATE `thongtinlienhe`=VALUES(`thongtinlienhe`);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tbl_sanpham`
--

CREATE TABLE IF NOT EXISTS `tbl_sanpham` (
  `id_sanpham` int(11) NOT NULL,
  `tensanpham` varchar(250) NOT NULL,
  `masp` varchar(100) NOT NULL,
  `giasp` int(11) NOT NULL,
  `soluong` int(11) NOT NULL,
  `hinhanh` varchar(255) NOT NULL,
  `tomtat` text NOT NULL,
  `noidung` longtext NOT NULL,
  `tinhtrang` int(11) NOT NULL DEFAULT 1,
  `id_danhmuc` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tbl_sanpham`
--

INSERT INTO `tbl_sanpham` (`id_sanpham`, `tensanpham`, `masp`, `giasp`, `soluong`, `hinhanh`, `tomtat`, `noidung`, `tinhtrang`, `id_danhmuc`) VALUES
(1, 'iPhone 15 Pro Max', 'IP15PM', 29990000, 15, 'iphone15.jpg', 'Màn hình: 6.7 inch Super Retina XDR<br>Chip: Apple A17 Pro<br>Camera sau: Chính 48 MP & Phụ 12 MP, 12 MP', 'iPhone 15 Pro Max sở hữu thiết kế khung viền Titan chuẩn hàng không vũ trụ siêu nhẹ, nút Action đa năng và cổng kết nối USB-C tốc độ cao.', 1, 1),
(2, 'Samsung Galaxy S24 Ultra', 'SSS24U', 27500000, 10, 's24ultra.jpg', 'Màn hình: 6.8 inch Dynamic AMOLED 2X<br>Chip: Snapdragon 8 Gen 3 for Galaxy<br>Bút S-Pen tích hợp', 'Samsung Galaxy S24 Ultra mang đến kỷ nguyên quyền năng Galaxy AI, khung viền Titan bền bỉ cùng hệ thống camera mắt thần bóng đêm 200MP.', 1, 1),
(3, 'MacBook Air M3 13 inch', 'MBA2024', 26990000, 8, 'macbookm3.jpg', 'CPU: Apple M3 8 nhân<br>RAM: 8GB | SSD: 256GB<br>Trọng lượng: 1.24 kg', 'MacBook Air M3 siêu mỏng nhẹ với hiệu năng vượt trội cho công việc văn phòng, đồ họa 2D và thời lượng pin lên đến 18 tiếng.', 1, 2),
(4, 'Laptop ASUS ROG Strix G16', 'ROG16', 32500000, 5, 'asusrog.jpg', 'CPU: Intel Core i7-13650HX<br>Card đồ họa: NVIDIA GeForce RTX 4060 8GB<br>Màn hình: 16 inch 165Hz', 'Chiến mượt mọi tựa game AAA với hệ thống tản nhiệt 3 quạt thông minh và bàn phím LED RGB từng phím ấn tượng.', 1, 2),
(5, 'Tai nghe Sony WH-1000XM5', 'SNXM5', 6490000, 20, 'sonyxm5.jpg', 'Công nghệ: Chống ồn chủ động ANC<br>Thời lượng pin: 30 giờ<br>Kết nối: Bluetooth 5.2', 'Tai nghe chụp tai không dây chống ồn tốt nhất phân khúc với 8 micro thu âm và chất âm chuẩn Hi-Res Audio.', 1, 3),
(6, 'Sạc dự phòng Anker 20000mAh', 'AK20K', 1150000, 30, 'anker20k.jpg', 'Dung lượng: 20.000 mAh<br>Công suất tối đa: 30W Power Delivery<br>Cổng sạc: 2 USB-C, 1 USB-A', 'Sạc nhanh đồng thời nhiều thiết bị an toàn với công nghệ kiểm soát nhiệt độ ActiveShield 2.0.', 1, 4)
ON DUPLICATE KEY UPDATE `tensanpham`=VALUES(`tensanpham`);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tbl_tintuc`
--

CREATE TABLE IF NOT EXISTS `tbl_tintuc` (
  `id_baiviet` int(11) NOT NULL,
  `tenbaiviet` varchar(255) NOT NULL,
  `tomtat` text NOT NULL,
  `noidung` longtext NOT NULL,
  `hinhanh` varchar(255) NOT NULL,
  `ngaydang` datetime NOT NULL DEFAULT current_timestamp(),
  `trangthai` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tbl_tintuc`
--

INSERT INTO `tbl_tintuc` (`id_baiviet`, `tenbaiviet`, `tomtat`, `noidung`, `hinhanh`, `ngaydang`, `trangthai`) VALUES
(1, 'Đánh giá chi tiết iPhone 15 Pro Max: Có còn đáng mua?', 'Trải nghiệm thực tế hiệu năng chip A17 Pro, khung viền Titan và camera zoom quang học 5x.', 'Khung viền Titan giúp máy nhẹ hơn đáng kể khi cầm nắm sử dụng liên tục. Cổng USB-C mang lại sự tiện lợi vượt bậc trong việc truyền dữ liệu và dùng chung cáp sạc với MacBook, iPad.', 'tintuc1.jpg', '2026-11-15 09:30:00', 1),
(2, 'Top 5 mẫu laptop sinh viên đáng mua nhất năm 2026', 'Tổng hợp những mẫu laptop mỏng nhẹ, pin trâu giá tốt dành cho học tập và làm việc.', 'Các dòng laptop thế hệ mới được trang bị vi xử lý tích hợp nhân AI giúp tối ưu thời lượng pin cả ngày dài và xử lý mượt mà mọi tác vụ văn phòng.', 'tintuc2.jpg', '2026-11-18 14:00:00', 1)
ON DUPLICATE KEY UPDATE `tenbaiviet`=VALUES(`tenbaiviet`);

--
-- Chỉ mục cho các bảng
--

ALTER TABLE `tbl_admin`
  ADD PRIMARY KEY (`id_admin`);

ALTER TABLE `tbl_cart`
  ADD PRIMARY KEY (`id_cart`);

ALTER TABLE `tbl_cart_details`
  ADD PRIMARY KEY (`id_cart_details`);

ALTER TABLE `tbl_dangky`
  ADD PRIMARY KEY (`id_dangky`);

ALTER TABLE `tbl_danhmuc`
  ADD PRIMARY KEY (`id_danhmuc`);

ALTER TABLE `tbl_danhmucbaiviet`
  ADD PRIMARY KEY (`id_baiviet`);

ALTER TABLE `tbl_lienhe`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `tbl_sanpham`
  ADD PRIMARY KEY (`id_sanpham`);

ALTER TABLE `tbl_tintuc`
  ADD PRIMARY KEY (`id_baiviet`);

--
-- AUTO_INCREMENT cho các bảng
--

ALTER TABLE `tbl_admin`
  MODIFY `id_admin` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `tbl_cart`
  MODIFY `id_cart` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `tbl_cart_details`
  MODIFY `id_cart_details` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `tbl_dangky`
  MODIFY `id_dangky` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `tbl_danhmuc`
  MODIFY `id_danhmuc` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

ALTER TABLE `tbl_danhmucbaiviet`
  MODIFY `id_baiviet` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `tbl_lienhe`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `tbl_sanpham`
  MODIFY `id_sanpham` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

ALTER TABLE `tbl_tintuc`
  MODIFY `id_baiviet` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
