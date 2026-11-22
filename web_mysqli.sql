-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1:3306
-- Thời gian đã tạo: Th10 22, 2026 lúc 09:07 AM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `web_mysqli`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tbl_admin`
--

CREATE TABLE `tbl_admin` (
  `id_admin` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `admin_status` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tbl_admin`
--

INSERT INTO `tbl_admin` (`id_admin`, `username`, `password`, `admin_status`) VALUES
(1, 'admin', '$2y$10$TZQVjydobQckqJwN4Ne9eOQJACyslMLo7mqu8xSoIvQ.tbczyudTu', 1);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tbl_cart`
--

CREATE TABLE `tbl_cart` (
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
(1, 1, '8492', 1, '2026-11-18 16:30:00', 'COD', 36480000);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tbl_cart_details`
--

CREATE TABLE `tbl_cart_details` (
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
(2, '8492', 5, 1, '');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tbl_dangky`
--

CREATE TABLE `tbl_dangky` (
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
(1, 'Nguyễn Văn A', 'test@gmail.com', '123 Đường ABC, Quy Nhơn', 'e10adc3949ba59abbe56e057f20f883e', '0987654321'),
(2, 'TRAN QUYNH CHI', '1@gmail.com', '', '$2y$10$IkbUM6WggYSXfSWyzDSEr.r9LgX00vC22gX5mM0n6DW1C9P8JEEm2', '08387388322');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tbl_danhmuc`
--

CREATE TABLE `tbl_danhmuc` (
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
(4, 'Phụ kiện điện tử', 4),
(33, 'Đồng hồ', 1000);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tbl_danhmucbaiviet`
--

CREATE TABLE `tbl_danhmucbaiviet` (
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
(3, 'Khuyến mãi', 3);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tbl_email_outbox`
--

CREATE TABLE `tbl_email_outbox` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `recipient_email` varchar(160) NOT NULL,
  `event_type` varchar(40) NOT NULL,
  `dedup_key` varchar(120) DEFAULT NULL,
  `subject` varchar(200) NOT NULL,
  `body_html` mediumtext NOT NULL,
  `body_text` text NOT NULL,
  `status` enum('PENDING','PROCESSING','SENT','FAILED','SKIPPED') NOT NULL DEFAULT 'PENDING',
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `last_error` varchar(500) NOT NULL DEFAULT '',
  `claimed_at` datetime DEFAULT NULL,
  `claim_token` varchar(40) DEFAULT NULL,
  `next_retry_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `sent_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tbl_email_outbox`
--

INSERT INTO `tbl_email_outbox` (`id`, `order_id`, `user_id`, `recipient_email`, `event_type`, `dedup_key`, `subject`, `body_html`, `body_text`, `status`, `attempts`, `last_error`, `claimed_at`, `claim_token`, `next_retry_at`, `created_at`, `sent_at`) VALUES
(22, 9, 2, '1@gmail.com', 'PAYMENT_CONFIRMED', 'email_paid:9', '[BanHang Tech Store] Thanh toán thành công cho đơn hàng BH4F97EE6EC8A7', '<!doctype html><html lang=\"vi\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"></head><body style=\"margin:0;padding:24px 12px;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#151923;\"><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"max-width:600px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5eaf2;\"><tr><td style=\"background:#101c33;padding:20px 26px;color:#ffffff;font-size:20px;font-weight:700;letter-spacing:-0.02em;\">BanHang Tech Store</td></tr><tr><td style=\"padding:26px;\"><span style=\"display:inline-block;padding:6px 12px;border-radius:999px;background:#137b4d;color:#ffffff;font-size:12px;font-weight:700;\">BH4F97EE6EC8A7</span><h1 style=\"margin:14px 0 10px;font-size:22px;line-height:1.3;color:#101c33;\">Thanh toán thành công!</h1><p style=\"margin:0 0 14px;font-size:14px;line-height:1.65;color:#334155;\">BanHang Tech Store đã đối soát và xác nhận nhận đủ 27.500.000 ₫ cho đơn hàng BH4F97EE6EC8A7. Đơn hàng của bạn đang được chuẩn bị.</p><p style=\"margin:14px 0;padding:12px 14px;background:#f4f7fc;border-radius:8px;color:#1e293b;font-size:14px;line-height:1.6;\">BanHang đã xác nhận nhận đủ 27.500.000 ₫ cho đơn hàng BH4F97EE6EC8A7. Đơn hàng của bạn đang được chuẩn bị. Chúng tôi sẽ cập nhật ngay khi sản phẩm được giao cho đơn vị vận chuyển.</p><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"8\" style=\"margin:18px 0;border-collapse:collapse;background:#f8fafc;border-radius:8px;font-size:13px;\"><tr><td style=\"color:#64748b;\">Mã đơn hàng</td><td style=\"text-align:right;font-weight:700;\">BH4F97EE6EC8A7</td></tr><tr><td style=\"color:#64748b;\">Tổng tiền</td><td style=\"text-align:right;font-weight:700;color:#2563eb;\">27.500.000 ₫</td></tr><tr><td style=\"color:#64748b;\">Thanh toán</td><td style=\"text-align:right;\">Chuyển khoản ngân hàng (VietQR)</td></tr><tr><td style=\"color:#64748b;\">Trạng thái đơn</td><td style=\"text-align:right;\">Đã đặt hàng</td></tr></table><p style=\"margin:16px 0 0;font-size:12px;color:#64748b;line-height:1.6;\">Vui lòng đăng nhập tài khoản trên website BanHang Tech Store để xem chi tiết đơn hàng và theo dõi tiến độ mới nhất.</p></td></tr><tr><td style=\"padding:16px 26px;background:#f8fafc;border-top:1px solid #e5eaf2;font-size:11px;color:#64748b;\">Email tự động từ hệ thống BanHang Tech Store. Không chia sẻ mật khẩu hoặc OTP qua email.</td></tr></table></body></html>', 'BanHang Tech Store\nThanh toán thành công!\n\nBanHang Tech Store đã đối soát và xác nhận nhận đủ 27.500.000 ₫ cho đơn hàng BH4F97EE6EC8A7. Đơn hàng của bạn đang được chuẩn bị.\n\nBanHang đã xác nhận nhận đủ 27.500.000 ₫ cho đơn hàng BH4F97EE6EC8A7. Đơn hàng của bạn đang được chuẩn bị. Chúng tôi sẽ cập nhật ngay khi sản phẩm được giao cho đơn vị vận chuyển.\n\nMã đơn hàng: BH4F97EE6EC8A7\nTổng tiền: 27.500.000 ₫\nThanh toán: Chuyển khoản ngân hàng (VietQR)\n', 'SKIPPED', 0, 'SMTP chưa được cấu hình; thông báo vẫn được lưu trong hệ thống website.', NULL, NULL, NULL, '2026-11-21 20:25:55', NULL),
(23, 26, 2, '1@gmail.com', 'ORDER_CREATED', 'email_created:26', '[BanHang Tech Store] Xác nhận tạo đơn hàng BH2FA379193619', '<!doctype html><html lang=\"vi\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"></head><body style=\"margin:0;padding:24px 12px;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#151923;\"><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"max-width:600px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5eaf2;\"><tr><td style=\"background:#101c33;padding:20px 26px;color:#ffffff;font-size:20px;font-weight:700;letter-spacing:-0.02em;\">BanHang Tech Store</td></tr><tr><td style=\"padding:26px;\"><span style=\"display:inline-block;padding:6px 12px;border-radius:999px;background:#2563eb;color:#ffffff;font-size:12px;font-weight:700;\">BH2FA379193619</span><h1 style=\"margin:14px 0 10px;font-size:22px;line-height:1.3;color:#101c33;\">Đơn hàng của bạn đã được ghi nhận</h1><p style=\"margin:0 0 14px;font-size:14px;line-height:1.65;color:#334155;\">Cảm ơn TRAN QUYNH CHI đã đặt hàng tại BanHang Tech Store. Tổng giá trị đơn hàng là 26.990.000 ₫ (Thanh toán khi nhận hàng (COD)).</p><p style=\"margin:14px 0;padding:12px 14px;background:#f4f7fc;border-radius:8px;color:#1e293b;font-size:14px;line-height:1.6;\">Đơn hàng thanh toán khi nhận hàng (COD) đã được tạo, đang chờ cửa hàng xác nhận.</p><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"8\" style=\"margin:18px 0;border-collapse:collapse;background:#f8fafc;border-radius:8px;font-size:13px;\"><tr><td style=\"color:#64748b;\">Mã đơn hàng</td><td style=\"text-align:right;font-weight:700;\">BH2FA379193619</td></tr><tr><td style=\"color:#64748b;\">Tổng tiền</td><td style=\"text-align:right;font-weight:700;color:#2563eb;\">26.990.000 ₫</td></tr><tr><td style=\"color:#64748b;\">Thanh toán</td><td style=\"text-align:right;\">Thanh toán khi nhận hàng (COD)</td></tr><tr><td style=\"color:#64748b;\">Trạng thái đơn</td><td style=\"text-align:right;\">Đã đặt hàng</td></tr></table><p style=\"margin:16px 0 0;font-size:12px;color:#64748b;line-height:1.6;\">Vui lòng đăng nhập tài khoản trên website BanHang Tech Store để xem chi tiết đơn hàng và theo dõi tiến độ mới nhất.</p></td></tr><tr><td style=\"padding:16px 26px;background:#f8fafc;border-top:1px solid #e5eaf2;font-size:11px;color:#64748b;\">Email tự động từ hệ thống BanHang Tech Store. Không chia sẻ mật khẩu hoặc OTP qua email.</td></tr></table></body></html>', 'BanHang Tech Store\nĐơn hàng của bạn đã được ghi nhận\n\nCảm ơn TRAN QUYNH CHI đã đặt hàng tại BanHang Tech Store. Tổng giá trị đơn hàng là 26.990.000 ₫ (Thanh toán khi nhận hàng (COD)).\n\nĐơn hàng thanh toán khi nhận hàng (COD) đã được tạo, đang chờ cửa hàng xác nhận.\n\nMã đơn hàng: BH2FA379193619\nTổng tiền: 26.990.000 ₫\nThanh toán: Thanh toán khi nhận hàng (COD)\n', 'SKIPPED', 0, 'SMTP chưa được cấu hình; thông báo vẫn được lưu trong hệ thống website.', NULL, NULL, NULL, '2026-11-21 20:27:11', NULL),
(180, 105, 2, '1@gmail.com', 'ORDER_CREATED', 'email_created:105', '[BanHang Tech Store] Xác nhận tạo đơn hàng BH5D4B70E9A14B', '<!doctype html><html lang=\"vi\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"></head><body style=\"margin:0;padding:24px 12px;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#151923;\"><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"max-width:600px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5eaf2;\"><tr><td style=\"background:#101c33;padding:20px 26px;color:#ffffff;font-size:20px;font-weight:700;letter-spacing:-0.02em;\">BanHang Tech Store</td></tr><tr><td style=\"padding:26px;\"><span style=\"display:inline-block;padding:6px 12px;border-radius:999px;background:#2563eb;color:#ffffff;font-size:12px;font-weight:700;\">BH5D4B70E9A14B</span><h1 style=\"margin:14px 0 10px;font-size:22px;line-height:1.3;color:#101c33;\">Đơn hàng của bạn đã được ghi nhận</h1><p style=\"margin:0 0 14px;font-size:14px;line-height:1.65;color:#334155;\">Cảm ơn TRAN QUYNH CHI đã đặt hàng tại BanHang Tech Store. Tổng giá trị đơn hàng là 27.500.000 ₫ (Chuyển khoản ngân hàng (VietQR)).</p><p style=\"margin:14px 0;padding:12px 14px;background:#f4f7fc;border-radius:8px;color:#1e293b;font-size:14px;line-height:1.6;\">Đơn hàng chuyển khoản VietQR đã được tạo, đang chờ khách chuyển khoản và cửa hàng đối soát.</p><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"8\" style=\"margin:18px 0;border-collapse:collapse;background:#f8fafc;border-radius:8px;font-size:13px;\"><tr><td style=\"color:#64748b;\">Mã đơn hàng</td><td style=\"text-align:right;font-weight:700;\">BH5D4B70E9A14B</td></tr><tr><td style=\"color:#64748b;\">Tổng tiền</td><td style=\"text-align:right;font-weight:700;color:#2563eb;\">27.500.000 ₫</td></tr><tr><td style=\"color:#64748b;\">Thanh toán</td><td style=\"text-align:right;\">Chuyển khoản ngân hàng (VietQR)</td></tr><tr><td style=\"color:#64748b;\">Trạng thái đơn</td><td style=\"text-align:right;\">Đã đặt hàng</td></tr></table><p style=\"margin:16px 0 0;font-size:12px;color:#64748b;line-height:1.6;\">Vui lòng đăng nhập tài khoản trên website BanHang Tech Store để xem chi tiết đơn hàng và theo dõi tiến độ mới nhất.</p></td></tr><tr><td style=\"padding:16px 26px;background:#f8fafc;border-top:1px solid #e5eaf2;font-size:11px;color:#64748b;\">Email tự động từ hệ thống BanHang Tech Store. Không chia sẻ mật khẩu hoặc OTP qua email.</td></tr></table></body></html>', 'BanHang Tech Store\nĐơn hàng của bạn đã được ghi nhận\n\nCảm ơn TRAN QUYNH CHI đã đặt hàng tại BanHang Tech Store. Tổng giá trị đơn hàng là 27.500.000 ₫ (Chuyển khoản ngân hàng (VietQR)).\n\nĐơn hàng chuyển khoản VietQR đã được tạo, đang chờ khách chuyển khoản và cửa hàng đối soát.\n\nMã đơn hàng: BH5D4B70E9A14B\nTổng tiền: 27.500.000 ₫\nThanh toán: Chuyển khoản ngân hàng (VietQR)\n', 'SKIPPED', 0, 'SMTP_HOST chưa được cấu hình. Thông báo vẫn được lưu trong hệ thống website.', NULL, NULL, NULL, '2026-11-21 23:44:19', NULL),
(181, 9, 2, '1@gmail.com', 'ORDER_CONFIRMED', 'email_status:9:CONFIRMED', '[BanHang Tech Store] Đơn hàng BH4F97EE6EC8A7 đã được xác nhận', '<!doctype html><html lang=\"vi\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"></head><body style=\"margin:0;padding:24px 12px;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#151923;\"><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"max-width:600px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5eaf2;\"><tr><td style=\"background:#101c33;padding:20px 26px;color:#ffffff;font-size:20px;font-weight:700;letter-spacing:-0.02em;\">BanHang Tech Store</td></tr><tr><td style=\"padding:26px;\"><span style=\"display:inline-block;padding:6px 12px;border-radius:999px;background:#2563eb;color:#ffffff;font-size:12px;font-weight:700;\">BH4F97EE6EC8A7</span><h1 style=\"margin:14px 0 10px;font-size:22px;line-height:1.3;color:#101c33;\">Đơn hàng đã được xác nhận</h1><p style=\"margin:0 0 14px;font-size:14px;line-height:1.65;color:#334155;\">Đơn hàng BH4F97EE6EC8A7 (27.500.000 ₫) đã được cửa hàng xác nhận và đưa vào quy trình xử lý.</p><p style=\"margin:14px 0;padding:12px 14px;background:#f4f7fc;border-radius:8px;color:#1e293b;font-size:14px;line-height:1.6;\">Cửa hàng đã xác nhận đơn hàng.</p><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"8\" style=\"margin:18px 0;border-collapse:collapse;background:#f8fafc;border-radius:8px;font-size:13px;\"><tr><td style=\"color:#64748b;\">Mã đơn hàng</td><td style=\"text-align:right;font-weight:700;\">BH4F97EE6EC8A7</td></tr><tr><td style=\"color:#64748b;\">Tổng tiền</td><td style=\"text-align:right;font-weight:700;color:#2563eb;\">27.500.000 ₫</td></tr><tr><td style=\"color:#64748b;\">Thanh toán</td><td style=\"text-align:right;\">Chuyển khoản ngân hàng (VietQR)</td></tr><tr><td style=\"color:#64748b;\">Trạng thái đơn</td><td style=\"text-align:right;\">Đã xác nhận</td></tr></table><p style=\"margin:16px 0 0;font-size:12px;color:#64748b;line-height:1.6;\">Vui lòng đăng nhập tài khoản trên website BanHang Tech Store để xem chi tiết đơn hàng và theo dõi tiến độ mới nhất.</p></td></tr><tr><td style=\"padding:16px 26px;background:#f8fafc;border-top:1px solid #e5eaf2;font-size:11px;color:#64748b;\">Email tự động từ hệ thống BanHang Tech Store. Không chia sẻ mật khẩu hoặc OTP qua email.</td></tr></table></body></html>', 'BanHang Tech Store\nĐơn hàng đã được xác nhận\n\nĐơn hàng BH4F97EE6EC8A7 (27.500.000 ₫) đã được cửa hàng xác nhận và đưa vào quy trình xử lý.\n\nCửa hàng đã xác nhận đơn hàng.\n\nMã đơn hàng: BH4F97EE6EC8A7\nTổng tiền: 27.500.000 ₫\nThanh toán: Chuyển khoản ngân hàng (VietQR)\n', 'SKIPPED', 0, 'SMTP_HOST chưa được cấu hình. Thông báo vẫn được lưu trong hệ thống website.', NULL, NULL, NULL, '2026-11-21 23:47:59', NULL),
(182, 9, 2, '1@gmail.com', 'ORDER_PREPARING', 'email_status:9:PREPARING', '[BanHang Tech Store] Đơn hàng BH4F97EE6EC8A7 đang được chuẩn bị', '<!doctype html><html lang=\"vi\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"></head><body style=\"margin:0;padding:24px 12px;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#151923;\"><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"max-width:600px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5eaf2;\"><tr><td style=\"background:#101c33;padding:20px 26px;color:#ffffff;font-size:20px;font-weight:700;letter-spacing:-0.02em;\">BanHang Tech Store</td></tr><tr><td style=\"padding:26px;\"><span style=\"display:inline-block;padding:6px 12px;border-radius:999px;background:#2563eb;color:#ffffff;font-size:12px;font-weight:700;\">BH4F97EE6EC8A7</span><h1 style=\"margin:14px 0 10px;font-size:22px;line-height:1.3;color:#101c33;\">Đang chuẩn bị hàng</h1><p style=\"margin:0 0 14px;font-size:14px;line-height:1.65;color:#334155;\">Sản phẩm trong đơn hàng BH4F97EE6EC8A7 đang được kiểm tra kỹ và đóng gói cẩn thận trước khi bàn giao vận chuyển.</p><p style=\"margin:14px 0;padding:12px 14px;background:#f4f7fc;border-radius:8px;color:#1e293b;font-size:14px;line-height:1.6;\">Đơn hàng đang được kiểm tra và đóng gói.</p><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"8\" style=\"margin:18px 0;border-collapse:collapse;background:#f8fafc;border-radius:8px;font-size:13px;\"><tr><td style=\"color:#64748b;\">Mã đơn hàng</td><td style=\"text-align:right;font-weight:700;\">BH4F97EE6EC8A7</td></tr><tr><td style=\"color:#64748b;\">Tổng tiền</td><td style=\"text-align:right;font-weight:700;color:#2563eb;\">27.500.000 ₫</td></tr><tr><td style=\"color:#64748b;\">Thanh toán</td><td style=\"text-align:right;\">Chuyển khoản ngân hàng (VietQR)</td></tr><tr><td style=\"color:#64748b;\">Trạng thái đơn</td><td style=\"text-align:right;\">Đang chuẩn bị hàng</td></tr></table><p style=\"margin:16px 0 0;font-size:12px;color:#64748b;line-height:1.6;\">Vui lòng đăng nhập tài khoản trên website BanHang Tech Store để xem chi tiết đơn hàng và theo dõi tiến độ mới nhất.</p></td></tr><tr><td style=\"padding:16px 26px;background:#f8fafc;border-top:1px solid #e5eaf2;font-size:11px;color:#64748b;\">Email tự động từ hệ thống BanHang Tech Store. Không chia sẻ mật khẩu hoặc OTP qua email.</td></tr></table></body></html>', 'BanHang Tech Store\nĐang chuẩn bị hàng\n\nSản phẩm trong đơn hàng BH4F97EE6EC8A7 đang được kiểm tra kỹ và đóng gói cẩn thận trước khi bàn giao vận chuyển.\n\nĐơn hàng đang được kiểm tra và đóng gói.\n\nMã đơn hàng: BH4F97EE6EC8A7\nTổng tiền: 27.500.000 ₫\nThanh toán: Chuyển khoản ngân hàng (VietQR)\n', 'SKIPPED', 0, 'SMTP_HOST chưa được cấu hình. Thông báo vẫn được lưu trong hệ thống website.', NULL, NULL, NULL, '2026-11-21 23:48:07', NULL),
(183, 9, 2, '1@gmail.com', 'ORDER_SHIPPING', 'email_status:9:SHIPPING', '[BanHang Tech Store] Đơn hàng BH4F97EE6EC8A7 đang được giao', '<!doctype html><html lang=\"vi\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"></head><body style=\"margin:0;padding:24px 12px;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#151923;\"><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"max-width:600px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5eaf2;\"><tr><td style=\"background:#101c33;padding:20px 26px;color:#ffffff;font-size:20px;font-weight:700;letter-spacing:-0.02em;\">BanHang Tech Store</td></tr><tr><td style=\"padding:26px;\"><span style=\"display:inline-block;padding:6px 12px;border-radius:999px;background:#0369a1;color:#ffffff;font-size:12px;font-weight:700;\">BH4F97EE6EC8A7</span><h1 style=\"margin:14px 0 10px;font-size:22px;line-height:1.3;color:#101c33;\">Đơn hàng đang trên đường giao đến bạn</h1><p style=\"margin:0 0 14px;font-size:14px;line-height:1.65;color:#334155;\">Đơn hàng BH4F97EE6EC8A7 đã được bàn giao cho đơn vị vận chuyển.</p><p style=\"margin:14px 0;padding:12px 14px;background:#f4f7fc;border-radius:8px;color:#1e293b;font-size:14px;line-height:1.6;\">Đơn hàng đã bàn giao cho đơn vị vận chuyển.</p><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"8\" style=\"margin:18px 0;border-collapse:collapse;background:#f8fafc;border-radius:8px;font-size:13px;\"><tr><td style=\"color:#64748b;\">Mã đơn hàng</td><td style=\"text-align:right;font-weight:700;\">BH4F97EE6EC8A7</td></tr><tr><td style=\"color:#64748b;\">Tổng tiền</td><td style=\"text-align:right;font-weight:700;color:#2563eb;\">27.500.000 ₫</td></tr><tr><td style=\"color:#64748b;\">Thanh toán</td><td style=\"text-align:right;\">Chuyển khoản ngân hàng (VietQR)</td></tr><tr><td style=\"color:#64748b;\">Trạng thái đơn</td><td style=\"text-align:right;\">Đang giao hàng</td></tr></table><p style=\"margin:16px 0 0;font-size:12px;color:#64748b;line-height:1.6;\">Vui lòng đăng nhập tài khoản trên website BanHang Tech Store để xem chi tiết đơn hàng và theo dõi tiến độ mới nhất.</p></td></tr><tr><td style=\"padding:16px 26px;background:#f8fafc;border-top:1px solid #e5eaf2;font-size:11px;color:#64748b;\">Email tự động từ hệ thống BanHang Tech Store. Không chia sẻ mật khẩu hoặc OTP qua email.</td></tr></table></body></html>', 'BanHang Tech Store\nĐơn hàng đang trên đường giao đến bạn\n\nĐơn hàng BH4F97EE6EC8A7 đã được bàn giao cho đơn vị vận chuyển.\n\nĐơn hàng đã bàn giao cho đơn vị vận chuyển.\n\nMã đơn hàng: BH4F97EE6EC8A7\nTổng tiền: 27.500.000 ₫\nThanh toán: Chuyển khoản ngân hàng (VietQR)\n', 'SKIPPED', 0, 'SMTP_HOST chưa được cấu hình. Thông báo vẫn được lưu trong hệ thống website.', NULL, NULL, NULL, '2026-11-21 23:48:12', NULL),
(184, 9, 2, '1@gmail.com', 'ORDER_COMPLETED', 'email_status:9:COMPLETED', '[BanHang Tech Store] Giao hàng thành công đơn hàng BH4F97EE6EC8A7', '<!doctype html><html lang=\"vi\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"></head><body style=\"margin:0;padding:24px 12px;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#151923;\"><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"max-width:600px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5eaf2;\"><tr><td style=\"background:#101c33;padding:20px 26px;color:#ffffff;font-size:20px;font-weight:700;letter-spacing:-0.02em;\">BanHang Tech Store</td></tr><tr><td style=\"padding:26px;\"><span style=\"display:inline-block;padding:6px 12px;border-radius:999px;background:#137b4d;color:#ffffff;font-size:12px;font-weight:700;\">BH4F97EE6EC8A7</span><h1 style=\"margin:14px 0 10px;font-size:22px;line-height:1.3;color:#101c33;\">Giao hàng thành công!</h1><p style=\"margin:0 0 14px;font-size:14px;line-height:1.65;color:#334155;\">Đơn hàng BH4F97EE6EC8A7 đã được giao thành công. Cảm ơn bạn đã tin tưởng mua sắm tại BanHang Tech Store.</p><p style=\"margin:14px 0;padding:12px 14px;background:#f4f7fc;border-radius:8px;color:#1e293b;font-size:14px;line-height:1.6;\">Đơn hàng đã được giao thành công đến khách hàng.</p><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"8\" style=\"margin:18px 0;border-collapse:collapse;background:#f8fafc;border-radius:8px;font-size:13px;\"><tr><td style=\"color:#64748b;\">Mã đơn hàng</td><td style=\"text-align:right;font-weight:700;\">BH4F97EE6EC8A7</td></tr><tr><td style=\"color:#64748b;\">Tổng tiền</td><td style=\"text-align:right;font-weight:700;color:#2563eb;\">27.500.000 ₫</td></tr><tr><td style=\"color:#64748b;\">Thanh toán</td><td style=\"text-align:right;\">Chuyển khoản ngân hàng (VietQR)</td></tr><tr><td style=\"color:#64748b;\">Trạng thái đơn</td><td style=\"text-align:right;\">Đã giao thành công</td></tr></table><p style=\"margin:16px 0 0;font-size:12px;color:#64748b;line-height:1.6;\">Vui lòng đăng nhập tài khoản trên website BanHang Tech Store để xem chi tiết đơn hàng và theo dõi tiến độ mới nhất.</p></td></tr><tr><td style=\"padding:16px 26px;background:#f8fafc;border-top:1px solid #e5eaf2;font-size:11px;color:#64748b;\">Email tự động từ hệ thống BanHang Tech Store. Không chia sẻ mật khẩu hoặc OTP qua email.</td></tr></table></body></html>', 'BanHang Tech Store\nGiao hàng thành công!\n\nĐơn hàng BH4F97EE6EC8A7 đã được giao thành công. Cảm ơn bạn đã tin tưởng mua sắm tại BanHang Tech Store.\n\nĐơn hàng đã được giao thành công đến khách hàng.\n\nMã đơn hàng: BH4F97EE6EC8A7\nTổng tiền: 27.500.000 ₫\nThanh toán: Chuyển khoản ngân hàng (VietQR)\n', 'SKIPPED', 0, 'SMTP_HOST chưa được cấu hình. Thông báo vẫn được lưu trong hệ thống website.', NULL, NULL, NULL, '2026-11-21 23:48:21', NULL),
(185, 105, 2, '1@gmail.com', 'PAYMENT_CONFIRMED', 'email_paid:105', '[BanHang Tech Store] Thanh toán thành công cho đơn hàng BH5D4B70E9A14B', '<!doctype html><html lang=\"vi\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"></head><body style=\"margin:0;padding:24px 12px;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#151923;\"><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"max-width:600px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5eaf2;\"><tr><td style=\"background:#101c33;padding:20px 26px;color:#ffffff;font-size:20px;font-weight:700;letter-spacing:-0.02em;\">BanHang Tech Store</td></tr><tr><td style=\"padding:26px;\"><span style=\"display:inline-block;padding:6px 12px;border-radius:999px;background:#137b4d;color:#ffffff;font-size:12px;font-weight:700;\">BH5D4B70E9A14B</span><h1 style=\"margin:14px 0 10px;font-size:22px;line-height:1.3;color:#101c33;\">Thanh toán thành công!</h1><p style=\"margin:0 0 14px;font-size:14px;line-height:1.65;color:#334155;\">BanHang Tech Store đã đối soát và xác nhận nhận đủ 27.500.000 ₫ cho đơn hàng BH5D4B70E9A14B. Đơn hàng của bạn đang được chuẩn bị.</p><p style=\"margin:14px 0;padding:12px 14px;background:#f4f7fc;border-radius:8px;color:#1e293b;font-size:14px;line-height:1.6;\">BanHang đã xác nhận nhận đủ 27.500.000 ₫ cho đơn hàng BH5D4B70E9A14B. Đơn hàng của bạn đang được chuẩn bị. Chúng tôi sẽ cập nhật ngay khi sản phẩm được giao cho đơn vị vận chuyển.</p><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"8\" style=\"margin:18px 0;border-collapse:collapse;background:#f8fafc;border-radius:8px;font-size:13px;\"><tr><td style=\"color:#64748b;\">Mã đơn hàng</td><td style=\"text-align:right;font-weight:700;\">BH5D4B70E9A14B</td></tr><tr><td style=\"color:#64748b;\">Tổng tiền</td><td style=\"text-align:right;font-weight:700;color:#2563eb;\">27.500.000 ₫</td></tr><tr><td style=\"color:#64748b;\">Thanh toán</td><td style=\"text-align:right;\">Chuyển khoản ngân hàng (VietQR)</td></tr><tr><td style=\"color:#64748b;\">Trạng thái đơn</td><td style=\"text-align:right;\">Đã đặt hàng</td></tr></table><p style=\"margin:16px 0 0;font-size:12px;color:#64748b;line-height:1.6;\">Vui lòng đăng nhập tài khoản trên website BanHang Tech Store để xem chi tiết đơn hàng và theo dõi tiến độ mới nhất.</p></td></tr><tr><td style=\"padding:16px 26px;background:#f8fafc;border-top:1px solid #e5eaf2;font-size:11px;color:#64748b;\">Email tự động từ hệ thống BanHang Tech Store. Không chia sẻ mật khẩu hoặc OTP qua email.</td></tr></table></body></html>', 'BanHang Tech Store\nThanh toán thành công!\n\nBanHang Tech Store đã đối soát và xác nhận nhận đủ 27.500.000 ₫ cho đơn hàng BH5D4B70E9A14B. Đơn hàng của bạn đang được chuẩn bị.\n\nBanHang đã xác nhận nhận đủ 27.500.000 ₫ cho đơn hàng BH5D4B70E9A14B. Đơn hàng của bạn đang được chuẩn bị. Chúng tôi sẽ cập nhật ngay khi sản phẩm được giao cho đơn vị vận chuyển.\n\nMã đơn hàng: BH5D4B70E9A14B\nTổng tiền: 27.500.000 ₫\nThanh toán: Chuyển khoản ngân hàng (VietQR)\n', 'SKIPPED', 0, 'SMTP_HOST chưa được cấu hình. Thông báo vẫn được lưu trong hệ thống website.', NULL, NULL, NULL, '2026-11-21 23:50:50', NULL),
(186, 105, 2, '1@gmail.com', 'ORDER_CONFIRMED', 'email_status:105:CONFIRMED', '[BanHang Tech Store] Đơn hàng BH5D4B70E9A14B đã được xác nhận', '<!doctype html><html lang=\"vi\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"></head><body style=\"margin:0;padding:24px 12px;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#151923;\"><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"max-width:600px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5eaf2;\"><tr><td style=\"background:#101c33;padding:20px 26px;color:#ffffff;font-size:20px;font-weight:700;letter-spacing:-0.02em;\">BanHang Tech Store</td></tr><tr><td style=\"padding:26px;\"><span style=\"display:inline-block;padding:6px 12px;border-radius:999px;background:#2563eb;color:#ffffff;font-size:12px;font-weight:700;\">BH5D4B70E9A14B</span><h1 style=\"margin:14px 0 10px;font-size:22px;line-height:1.3;color:#101c33;\">Đơn hàng đã được xác nhận</h1><p style=\"margin:0 0 14px;font-size:14px;line-height:1.65;color:#334155;\">Đơn hàng BH5D4B70E9A14B (27.500.000 ₫) đã được cửa hàng xác nhận và đưa vào quy trình xử lý.</p><p style=\"margin:14px 0;padding:12px 14px;background:#f4f7fc;border-radius:8px;color:#1e293b;font-size:14px;line-height:1.6;\">Cửa hàng đã xác nhận đơn hàng.</p><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"8\" style=\"margin:18px 0;border-collapse:collapse;background:#f8fafc;border-radius:8px;font-size:13px;\"><tr><td style=\"color:#64748b;\">Mã đơn hàng</td><td style=\"text-align:right;font-weight:700;\">BH5D4B70E9A14B</td></tr><tr><td style=\"color:#64748b;\">Tổng tiền</td><td style=\"text-align:right;font-weight:700;color:#2563eb;\">27.500.000 ₫</td></tr><tr><td style=\"color:#64748b;\">Thanh toán</td><td style=\"text-align:right;\">Chuyển khoản ngân hàng (VietQR)</td></tr><tr><td style=\"color:#64748b;\">Trạng thái đơn</td><td style=\"text-align:right;\">Đã xác nhận</td></tr></table><p style=\"margin:16px 0 0;font-size:12px;color:#64748b;line-height:1.6;\">Vui lòng đăng nhập tài khoản trên website BanHang Tech Store để xem chi tiết đơn hàng và theo dõi tiến độ mới nhất.</p></td></tr><tr><td style=\"padding:16px 26px;background:#f8fafc;border-top:1px solid #e5eaf2;font-size:11px;color:#64748b;\">Email tự động từ hệ thống BanHang Tech Store. Không chia sẻ mật khẩu hoặc OTP qua email.</td></tr></table></body></html>', 'BanHang Tech Store\nĐơn hàng đã được xác nhận\n\nĐơn hàng BH5D4B70E9A14B (27.500.000 ₫) đã được cửa hàng xác nhận và đưa vào quy trình xử lý.\n\nCửa hàng đã xác nhận đơn hàng.\n\nMã đơn hàng: BH5D4B70E9A14B\nTổng tiền: 27.500.000 ₫\nThanh toán: Chuyển khoản ngân hàng (VietQR)\n', 'SKIPPED', 0, 'SMTP_HOST chưa được cấu hình. Thông báo vẫn được lưu trong hệ thống website.', NULL, NULL, NULL, '2026-11-21 23:50:56', NULL),
(187, 106, 2, '1@gmail.com', 'ORDER_CREATED', 'email_created:106', '[BanHang Tech Store] Xác nhận tạo đơn hàng BH4C01ECB9BC29', '<!doctype html><html lang=\"vi\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"></head><body style=\"margin:0;padding:24px 12px;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#151923;\"><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"max-width:600px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5eaf2;\"><tr><td style=\"background:#101c33;padding:20px 26px;color:#ffffff;font-size:20px;font-weight:700;letter-spacing:-0.02em;\">BanHang Tech Store</td></tr><tr><td style=\"padding:26px;\"><span style=\"display:inline-block;padding:6px 12px;border-radius:999px;background:#2563eb;color:#ffffff;font-size:12px;font-weight:700;\">BH4C01ECB9BC29</span><h1 style=\"margin:14px 0 10px;font-size:22px;line-height:1.3;color:#101c33;\">Đơn hàng của bạn đã được ghi nhận</h1><p style=\"margin:0 0 14px;font-size:14px;line-height:1.65;color:#334155;\">Cảm ơn TRAN QUYNH CHI đã đặt hàng tại BanHang Tech Store. Tổng giá trị đơn hàng là 1.150.000 ₫ (Chuyển khoản ngân hàng (VietQR)).</p><p style=\"margin:14px 0;padding:12px 14px;background:#f4f7fc;border-radius:8px;color:#1e293b;font-size:14px;line-height:1.6;\">Đơn hàng chuyển khoản VietQR đã được tạo, đang chờ khách chuyển khoản và cửa hàng đối soát.</p><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"8\" style=\"margin:18px 0;border-collapse:collapse;background:#f8fafc;border-radius:8px;font-size:13px;\"><tr><td style=\"color:#64748b;\">Mã đơn hàng</td><td style=\"text-align:right;font-weight:700;\">BH4C01ECB9BC29</td></tr><tr><td style=\"color:#64748b;\">Tổng tiền</td><td style=\"text-align:right;font-weight:700;color:#2563eb;\">1.150.000 ₫</td></tr><tr><td style=\"color:#64748b;\">Thanh toán</td><td style=\"text-align:right;\">Chuyển khoản ngân hàng (VietQR)</td></tr><tr><td style=\"color:#64748b;\">Trạng thái đơn</td><td style=\"text-align:right;\">Đã đặt hàng</td></tr></table><p style=\"margin:16px 0 0;font-size:12px;color:#64748b;line-height:1.6;\">Vui lòng đăng nhập tài khoản trên website BanHang Tech Store để xem chi tiết đơn hàng và theo dõi tiến độ mới nhất.</p></td></tr><tr><td style=\"padding:16px 26px;background:#f8fafc;border-top:1px solid #e5eaf2;font-size:11px;color:#64748b;\">Email tự động từ hệ thống BanHang Tech Store. Không chia sẻ mật khẩu hoặc OTP qua email.</td></tr></table></body></html>', 'BanHang Tech Store\nĐơn hàng của bạn đã được ghi nhận\n\nCảm ơn TRAN QUYNH CHI đã đặt hàng tại BanHang Tech Store. Tổng giá trị đơn hàng là 1.150.000 ₫ (Chuyển khoản ngân hàng (VietQR)).\n\nĐơn hàng chuyển khoản VietQR đã được tạo, đang chờ khách chuyển khoản và cửa hàng đối soát.\n\nMã đơn hàng: BH4C01ECB9BC29\nTổng tiền: 1.150.000 ₫\nThanh toán: Chuyển khoản ngân hàng (VietQR)\n', 'SKIPPED', 0, 'SMTP_HOST chưa được cấu hình. Thông báo vẫn được lưu trong hệ thống website.', NULL, NULL, NULL, '2026-11-22 00:44:02', NULL),
(188, 107, 2, '1@gmail.com', 'ORDER_CREATED', 'email_created:107', '[BanHang Tech Store] Xác nhận tạo đơn hàng BH2D64EE88CDBC', '<!doctype html><html lang=\"vi\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"></head><body style=\"margin:0;padding:24px 12px;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#151923;\"><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"max-width:600px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5eaf2;\"><tr><td style=\"background:#101c33;padding:20px 26px;color:#ffffff;font-size:20px;font-weight:700;letter-spacing:-0.02em;\">BanHang Tech Store</td></tr><tr><td style=\"padding:26px;\"><span style=\"display:inline-block;padding:6px 12px;border-radius:999px;background:#2563eb;color:#ffffff;font-size:12px;font-weight:700;\">BH2D64EE88CDBC</span><h1 style=\"margin:14px 0 10px;font-size:22px;line-height:1.3;color:#101c33;\">Đơn hàng của bạn đã được ghi nhận</h1><p style=\"margin:0 0 14px;font-size:14px;line-height:1.65;color:#334155;\">Cảm ơn TRAN QUYNH CHI đã đặt hàng tại BanHang Tech Store. Tổng giá trị đơn hàng là 1.150.000 ₫ (Chuyển khoản ngân hàng (VietQR)).</p><p style=\"margin:14px 0;padding:12px 14px;background:#f4f7fc;border-radius:8px;color:#1e293b;font-size:14px;line-height:1.6;\">Đơn hàng chuyển khoản VietQR đã được tạo, đang chờ khách chuyển khoản và cửa hàng đối soát.</p><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"8\" style=\"margin:18px 0;border-collapse:collapse;background:#f8fafc;border-radius:8px;font-size:13px;\"><tr><td style=\"color:#64748b;\">Mã đơn hàng</td><td style=\"text-align:right;font-weight:700;\">BH2D64EE88CDBC</td></tr><tr><td style=\"color:#64748b;\">Tổng tiền</td><td style=\"text-align:right;font-weight:700;color:#2563eb;\">1.150.000 ₫</td></tr><tr><td style=\"color:#64748b;\">Thanh toán</td><td style=\"text-align:right;\">Chuyển khoản ngân hàng (VietQR)</td></tr><tr><td style=\"color:#64748b;\">Trạng thái đơn</td><td style=\"text-align:right;\">Đã đặt hàng</td></tr></table><p style=\"margin:16px 0 0;font-size:12px;color:#64748b;line-height:1.6;\">Vui lòng đăng nhập tài khoản trên website BanHang Tech Store để xem chi tiết đơn hàng và theo dõi tiến độ mới nhất.</p></td></tr><tr><td style=\"padding:16px 26px;background:#f8fafc;border-top:1px solid #e5eaf2;font-size:11px;color:#64748b;\">Email tự động từ hệ thống BanHang Tech Store. Không chia sẻ mật khẩu hoặc OTP qua email.</td></tr></table></body></html>', 'BanHang Tech Store\nĐơn hàng của bạn đã được ghi nhận\n\nCảm ơn TRAN QUYNH CHI đã đặt hàng tại BanHang Tech Store. Tổng giá trị đơn hàng là 1.150.000 ₫ (Chuyển khoản ngân hàng (VietQR)).\n\nĐơn hàng chuyển khoản VietQR đã được tạo, đang chờ khách chuyển khoản và cửa hàng đối soát.\n\nMã đơn hàng: BH2D64EE88CDBC\nTổng tiền: 1.150.000 ₫\nThanh toán: Chuyển khoản ngân hàng (VietQR)\n', 'SKIPPED', 0, 'SMTP_HOST chưa được cấu hình. Thông báo vẫn được lưu trong hệ thống website.', NULL, NULL, NULL, '2026-11-22 00:58:27', NULL),
(189, 108, 2, '1@gmail.com', 'ORDER_CREATED', 'email_created:108', '[BanHang Tech Store] Xác nhận tạo đơn hàng BH841F9BEC5127', '<!doctype html><html lang=\"vi\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"></head><body style=\"margin:0;padding:24px 12px;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#151923;\"><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"max-width:600px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5eaf2;\"><tr><td style=\"background:#101c33;padding:20px 26px;color:#ffffff;font-size:20px;font-weight:700;letter-spacing:-0.02em;\">BanHang Tech Store</td></tr><tr><td style=\"padding:26px;\"><span style=\"display:inline-block;padding:6px 12px;border-radius:999px;background:#2563eb;color:#ffffff;font-size:12px;font-weight:700;\">BH841F9BEC5127</span><h1 style=\"margin:14px 0 10px;font-size:22px;line-height:1.3;color:#101c33;\">Đơn hàng của bạn đã được ghi nhận</h1><p style=\"margin:0 0 14px;font-size:14px;line-height:1.65;color:#334155;\">Cảm ơn TRAN QUYNH CHI đã đặt hàng tại BanHang Tech Store. Tổng giá trị đơn hàng là 32.500.000 ₫ (Chuyển khoản ngân hàng (VietQR)).</p><p style=\"margin:14px 0;padding:12px 14px;background:#f4f7fc;border-radius:8px;color:#1e293b;font-size:14px;line-height:1.6;\">Đơn hàng chuyển khoản VietQR đã được tạo, đang chờ khách chuyển khoản và cửa hàng đối soát.</p><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"8\" style=\"margin:18px 0;border-collapse:collapse;background:#f8fafc;border-radius:8px;font-size:13px;\"><tr><td style=\"color:#64748b;\">Mã đơn hàng</td><td style=\"text-align:right;font-weight:700;\">BH841F9BEC5127</td></tr><tr><td style=\"color:#64748b;\">Tổng tiền</td><td style=\"text-align:right;font-weight:700;color:#2563eb;\">32.500.000 ₫</td></tr><tr><td style=\"color:#64748b;\">Thanh toán</td><td style=\"text-align:right;\">Chuyển khoản ngân hàng (VietQR)</td></tr><tr><td style=\"color:#64748b;\">Trạng thái đơn</td><td style=\"text-align:right;\">Đã đặt hàng</td></tr></table><p style=\"margin:16px 0 0;font-size:12px;color:#64748b;line-height:1.6;\">Vui lòng đăng nhập tài khoản trên website BanHang Tech Store để xem chi tiết đơn hàng và theo dõi tiến độ mới nhất.</p></td></tr><tr><td style=\"padding:16px 26px;background:#f8fafc;border-top:1px solid #e5eaf2;font-size:11px;color:#64748b;\">Email tự động từ hệ thống BanHang Tech Store. Không chia sẻ mật khẩu hoặc OTP qua email.</td></tr></table></body></html>', 'BanHang Tech Store\nĐơn hàng của bạn đã được ghi nhận\n\nCảm ơn TRAN QUYNH CHI đã đặt hàng tại BanHang Tech Store. Tổng giá trị đơn hàng là 32.500.000 ₫ (Chuyển khoản ngân hàng (VietQR)).\n\nĐơn hàng chuyển khoản VietQR đã được tạo, đang chờ khách chuyển khoản và cửa hàng đối soát.\n\nMã đơn hàng: BH841F9BEC5127\nTổng tiền: 32.500.000 ₫\nThanh toán: Chuyển khoản ngân hàng (VietQR)\n', 'SKIPPED', 0, 'SMTP_HOST chưa được cấu hình. Thông báo vẫn được lưu trong hệ thống website.', NULL, NULL, NULL, '2026-11-22 00:59:27', NULL),
(190, 109, 2, '1@gmail.com', 'ORDER_CREATED', 'email_created:109', '[BanHang Tech Store] Xác nhận tạo đơn hàng BH2F119C74C62B', '<!doctype html><html lang=\"vi\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"></head><body style=\"margin:0;padding:24px 12px;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#151923;\"><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"max-width:600px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5eaf2;\"><tr><td style=\"background:#101c33;padding:20px 26px;color:#ffffff;font-size:20px;font-weight:700;letter-spacing:-0.02em;\">BanHang Tech Store</td></tr><tr><td style=\"padding:26px;\"><span style=\"display:inline-block;padding:6px 12px;border-radius:999px;background:#2563eb;color:#ffffff;font-size:12px;font-weight:700;\">BH2F119C74C62B</span><h1 style=\"margin:14px 0 10px;font-size:22px;line-height:1.3;color:#101c33;\">Đơn hàng của bạn đã được ghi nhận</h1><p style=\"margin:0 0 14px;font-size:14px;line-height:1.65;color:#334155;\">Cảm ơn TRAN QUYNH CHI đã đặt hàng tại BanHang Tech Store. Tổng giá trị đơn hàng là 32.500.000 ₫ (Thanh toán khi nhận hàng (COD)).</p><p style=\"margin:14px 0;padding:12px 14px;background:#f4f7fc;border-radius:8px;color:#1e293b;font-size:14px;line-height:1.6;\">Đơn hàng thanh toán khi nhận hàng (COD) đã được tạo, đang chờ cửa hàng xác nhận.</p><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"8\" style=\"margin:18px 0;border-collapse:collapse;background:#f8fafc;border-radius:8px;font-size:13px;\"><tr><td style=\"color:#64748b;\">Mã đơn hàng</td><td style=\"text-align:right;font-weight:700;\">BH2F119C74C62B</td></tr><tr><td style=\"color:#64748b;\">Tổng tiền</td><td style=\"text-align:right;font-weight:700;color:#2563eb;\">32.500.000 ₫</td></tr><tr><td style=\"color:#64748b;\">Thanh toán</td><td style=\"text-align:right;\">Thanh toán khi nhận hàng (COD)</td></tr><tr><td style=\"color:#64748b;\">Trạng thái đơn</td><td style=\"text-align:right;\">Đã đặt hàng</td></tr></table><p style=\"margin:16px 0 0;font-size:12px;color:#64748b;line-height:1.6;\">Vui lòng đăng nhập tài khoản trên website BanHang Tech Store để xem chi tiết đơn hàng và theo dõi tiến độ mới nhất.</p></td></tr><tr><td style=\"padding:16px 26px;background:#f8fafc;border-top:1px solid #e5eaf2;font-size:11px;color:#64748b;\">Email tự động từ hệ thống BanHang Tech Store. Không chia sẻ mật khẩu hoặc OTP qua email.</td></tr></table></body></html>', 'BanHang Tech Store\nĐơn hàng của bạn đã được ghi nhận\n\nCảm ơn TRAN QUYNH CHI đã đặt hàng tại BanHang Tech Store. Tổng giá trị đơn hàng là 32.500.000 ₫ (Thanh toán khi nhận hàng (COD)).\n\nĐơn hàng thanh toán khi nhận hàng (COD) đã được tạo, đang chờ cửa hàng xác nhận.\n\nMã đơn hàng: BH2F119C74C62B\nTổng tiền: 32.500.000 ₫\nThanh toán: Thanh toán khi nhận hàng (COD)\n', 'SKIPPED', 0, 'SMTP_HOST chưa được cấu hình. Thông báo vẫn được lưu trong hệ thống website.', NULL, NULL, NULL, '2026-11-22 01:00:01', NULL),
(198, 1132, 2, '1@gmail.com', 'ORDER_CREATED', 'email_created:1132', '[BanHang Tech Store] Xác nhận tạo đơn hàng BH15CAC5F7388C', '<!doctype html><html lang=\"vi\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"></head><body style=\"margin:0;padding:24px 12px;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#151923;\"><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"max-width:600px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5eaf2;\"><tr><td style=\"background:#101c33;padding:20px 26px;color:#ffffff;font-size:20px;font-weight:700;letter-spacing:-0.02em;\">BanHang Tech Store</td></tr><tr><td style=\"padding:26px;\"><span style=\"display:inline-block;padding:6px 12px;border-radius:999px;background:#2563eb;color:#ffffff;font-size:12px;font-weight:700;\">BH15CAC5F7388C</span><h1 style=\"margin:14px 0 10px;font-size:22px;line-height:1.3;color:#101c33;\">Đơn hàng của bạn đã được ghi nhận</h1><p style=\"margin:0 0 14px;font-size:14px;line-height:1.65;color:#334155;\">Cảm ơn TRAN QUYNH CHI đã đặt hàng tại BanHang Tech Store. Tổng giá trị đơn hàng là 28.099.000 ₫ (Chuyển khoản ngân hàng (VietQR)).</p><p style=\"margin:14px 0;padding:12px 14px;background:#f4f7fc;border-radius:8px;color:#1e293b;font-size:14px;line-height:1.6;\">Đơn hàng chuyển khoản VietQR đã được tạo, đang chờ khách chuyển khoản và cửa hàng đối soát.</p><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"8\" style=\"margin:18px 0;border-collapse:collapse;background:#f8fafc;border-radius:8px;font-size:13px;\"><tr><td style=\"color:#64748b;\">Mã đơn hàng</td><td style=\"text-align:right;font-weight:700;\">BH15CAC5F7388C</td></tr><tr><td style=\"color:#64748b;\">Tổng tiền</td><td style=\"text-align:right;font-weight:700;color:#2563eb;\">28.099.000 ₫</td></tr><tr><td style=\"color:#64748b;\">Thanh toán</td><td style=\"text-align:right;\">Chuyển khoản ngân hàng (VietQR)</td></tr><tr><td style=\"color:#64748b;\">Trạng thái đơn</td><td style=\"text-align:right;\">Đã đặt hàng</td></tr></table><p style=\"margin:16px 0 0;font-size:12px;color:#64748b;line-height:1.6;\">Vui lòng đăng nhập tài khoản trên website BanHang Tech Store để xem chi tiết đơn hàng và theo dõi tiến độ mới nhất.</p></td></tr><tr><td style=\"padding:16px 26px;background:#f8fafc;border-top:1px solid #e5eaf2;font-size:11px;color:#64748b;\">Email tự động từ hệ thống BanHang Tech Store. Không chia sẻ mật khẩu hoặc OTP qua email.</td></tr></table></body></html>', 'BanHang Tech Store\nĐơn hàng của bạn đã được ghi nhận\n\nCảm ơn TRAN QUYNH CHI đã đặt hàng tại BanHang Tech Store. Tổng giá trị đơn hàng là 28.099.000 ₫ (Chuyển khoản ngân hàng (VietQR)).\n\nĐơn hàng chuyển khoản VietQR đã được tạo, đang chờ khách chuyển khoản và cửa hàng đối soát.\n\nMã đơn hàng: BH15CAC5F7388C\nTổng tiền: 28.099.000 ₫\nThanh toán: Chuyển khoản ngân hàng (VietQR)\n', 'SKIPPED', 0, 'SMTP_HOST chưa được cấu hình. Thông báo vẫn được lưu trong hệ thống website.', NULL, NULL, NULL, '2026-11-22 11:16:33', NULL),
(199, 1132, 2, '1@gmail.com', 'PAYMENT_CONFIRMED', 'email_paid:1132', '[BanHang Tech Store] Thanh toán thành công cho đơn hàng BH15CAC5F7388C', '<!doctype html><html lang=\"vi\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"></head><body style=\"margin:0;padding:24px 12px;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#151923;\"><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"max-width:600px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5eaf2;\"><tr><td style=\"background:#101c33;padding:20px 26px;color:#ffffff;font-size:20px;font-weight:700;letter-spacing:-0.02em;\">BanHang Tech Store</td></tr><tr><td style=\"padding:26px;\"><span style=\"display:inline-block;padding:6px 12px;border-radius:999px;background:#137b4d;color:#ffffff;font-size:12px;font-weight:700;\">BH15CAC5F7388C</span><h1 style=\"margin:14px 0 10px;font-size:22px;line-height:1.3;color:#101c33;\">Thanh toán thành công!</h1><p style=\"margin:0 0 14px;font-size:14px;line-height:1.65;color:#334155;\">BanHang Tech Store đã đối soát và xác nhận nhận đủ 28.099.000 ₫ cho đơn hàng BH15CAC5F7388C. Đơn hàng của bạn đang được chuẩn bị.</p><p style=\"margin:14px 0;padding:12px 14px;background:#f4f7fc;border-radius:8px;color:#1e293b;font-size:14px;line-height:1.6;\">BanHang đã xác nhận nhận đủ 28.099.000 ₫ cho đơn hàng BH15CAC5F7388C. Đơn hàng của bạn đang được chuẩn bị. Chúng tôi sẽ cập nhật ngay khi sản phẩm được giao cho đơn vị vận chuyển.</p><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"8\" style=\"margin:18px 0;border-collapse:collapse;background:#f8fafc;border-radius:8px;font-size:13px;\"><tr><td style=\"color:#64748b;\">Mã đơn hàng</td><td style=\"text-align:right;font-weight:700;\">BH15CAC5F7388C</td></tr><tr><td style=\"color:#64748b;\">Tổng tiền</td><td style=\"text-align:right;font-weight:700;color:#2563eb;\">28.099.000 ₫</td></tr><tr><td style=\"color:#64748b;\">Thanh toán</td><td style=\"text-align:right;\">Chuyển khoản ngân hàng (VietQR)</td></tr><tr><td style=\"color:#64748b;\">Trạng thái đơn</td><td style=\"text-align:right;\">Đã đặt hàng</td></tr></table><p style=\"margin:16px 0 0;font-size:12px;color:#64748b;line-height:1.6;\">Vui lòng đăng nhập tài khoản trên website BanHang Tech Store để xem chi tiết đơn hàng và theo dõi tiến độ mới nhất.</p></td></tr><tr><td style=\"padding:16px 26px;background:#f8fafc;border-top:1px solid #e5eaf2;font-size:11px;color:#64748b;\">Email tự động từ hệ thống BanHang Tech Store. Không chia sẻ mật khẩu hoặc OTP qua email.</td></tr></table></body></html>', 'BanHang Tech Store\nThanh toán thành công!\n\nBanHang Tech Store đã đối soát và xác nhận nhận đủ 28.099.000 ₫ cho đơn hàng BH15CAC5F7388C. Đơn hàng của bạn đang được chuẩn bị.\n\nBanHang đã xác nhận nhận đủ 28.099.000 ₫ cho đơn hàng BH15CAC5F7388C. Đơn hàng của bạn đang được chuẩn bị. Chúng tôi sẽ cập nhật ngay khi sản phẩm được giao cho đơn vị vận chuyển.\n\nMã đơn hàng: BH15CAC5F7388C\nTổng tiền: 28.099.000 ₫\nThanh toán: Chuyển khoản ngân hàng (VietQR)\n', 'SKIPPED', 0, 'SMTP_HOST chưa được cấu hình. Thông báo vẫn được lưu trong hệ thống website.', NULL, NULL, NULL, '2026-11-22 11:18:40', NULL),
(200, 1132, 2, '1@gmail.com', 'ORDER_CONFIRMED', 'email_status:1132:CONFIRMED', '[BanHang Tech Store] Đơn hàng BH15CAC5F7388C đã được xác nhận', '<!doctype html><html lang=\"vi\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"></head><body style=\"margin:0;padding:24px 12px;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#151923;\"><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"max-width:600px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5eaf2;\"><tr><td style=\"background:#101c33;padding:20px 26px;color:#ffffff;font-size:20px;font-weight:700;letter-spacing:-0.02em;\">BanHang Tech Store</td></tr><tr><td style=\"padding:26px;\"><span style=\"display:inline-block;padding:6px 12px;border-radius:999px;background:#2563eb;color:#ffffff;font-size:12px;font-weight:700;\">BH15CAC5F7388C</span><h1 style=\"margin:14px 0 10px;font-size:22px;line-height:1.3;color:#101c33;\">Đơn hàng đã được xác nhận</h1><p style=\"margin:0 0 14px;font-size:14px;line-height:1.65;color:#334155;\">Đơn hàng BH15CAC5F7388C (28.099.000 ₫) đã được cửa hàng xác nhận và đưa vào quy trình xử lý.</p><p style=\"margin:14px 0;padding:12px 14px;background:#f4f7fc;border-radius:8px;color:#1e293b;font-size:14px;line-height:1.6;\">Cửa hàng đã xác nhận đơn hàng.</p><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"8\" style=\"margin:18px 0;border-collapse:collapse;background:#f8fafc;border-radius:8px;font-size:13px;\"><tr><td style=\"color:#64748b;\">Mã đơn hàng</td><td style=\"text-align:right;font-weight:700;\">BH15CAC5F7388C</td></tr><tr><td style=\"color:#64748b;\">Tổng tiền</td><td style=\"text-align:right;font-weight:700;color:#2563eb;\">28.099.000 ₫</td></tr><tr><td style=\"color:#64748b;\">Thanh toán</td><td style=\"text-align:right;\">Chuyển khoản ngân hàng (VietQR)</td></tr><tr><td style=\"color:#64748b;\">Trạng thái đơn</td><td style=\"text-align:right;\">Đã xác nhận</td></tr></table><p style=\"margin:16px 0 0;font-size:12px;color:#64748b;line-height:1.6;\">Vui lòng đăng nhập tài khoản trên website BanHang Tech Store để xem chi tiết đơn hàng và theo dõi tiến độ mới nhất.</p></td></tr><tr><td style=\"padding:16px 26px;background:#f8fafc;border-top:1px solid #e5eaf2;font-size:11px;color:#64748b;\">Email tự động từ hệ thống BanHang Tech Store. Không chia sẻ mật khẩu hoặc OTP qua email.</td></tr></table></body></html>', 'BanHang Tech Store\nĐơn hàng đã được xác nhận\n\nĐơn hàng BH15CAC5F7388C (28.099.000 ₫) đã được cửa hàng xác nhận và đưa vào quy trình xử lý.\n\nCửa hàng đã xác nhận đơn hàng.\n\nMã đơn hàng: BH15CAC5F7388C\nTổng tiền: 28.099.000 ₫\nThanh toán: Chuyển khoản ngân hàng (VietQR)\n', 'SKIPPED', 0, 'SMTP_HOST chưa được cấu hình. Thông báo vẫn được lưu trong hệ thống website.', NULL, NULL, NULL, '2026-11-22 11:19:28', NULL);
INSERT INTO `tbl_email_outbox` (`id`, `order_id`, `user_id`, `recipient_email`, `event_type`, `dedup_key`, `subject`, `body_html`, `body_text`, `status`, `attempts`, `last_error`, `claimed_at`, `claim_token`, `next_retry_at`, `created_at`, `sent_at`) VALUES
(201, 1132, 2, '1@gmail.com', 'ORDER_PREPARING', 'email_status:1132:PREPARING', '[BanHang Tech Store] Đơn hàng BH15CAC5F7388C đang được chuẩn bị', '<!doctype html><html lang=\"vi\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"></head><body style=\"margin:0;padding:24px 12px;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#151923;\"><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"max-width:600px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5eaf2;\"><tr><td style=\"background:#101c33;padding:20px 26px;color:#ffffff;font-size:20px;font-weight:700;letter-spacing:-0.02em;\">BanHang Tech Store</td></tr><tr><td style=\"padding:26px;\"><span style=\"display:inline-block;padding:6px 12px;border-radius:999px;background:#2563eb;color:#ffffff;font-size:12px;font-weight:700;\">BH15CAC5F7388C</span><h1 style=\"margin:14px 0 10px;font-size:22px;line-height:1.3;color:#101c33;\">Đang chuẩn bị hàng</h1><p style=\"margin:0 0 14px;font-size:14px;line-height:1.65;color:#334155;\">Sản phẩm trong đơn hàng BH15CAC5F7388C đang được kiểm tra kỹ và đóng gói cẩn thận trước khi bàn giao vận chuyển.</p><p style=\"margin:14px 0;padding:12px 14px;background:#f4f7fc;border-radius:8px;color:#1e293b;font-size:14px;line-height:1.6;\">Đơn hàng đang được kiểm tra và đóng gói.</p><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"8\" style=\"margin:18px 0;border-collapse:collapse;background:#f8fafc;border-radius:8px;font-size:13px;\"><tr><td style=\"color:#64748b;\">Mã đơn hàng</td><td style=\"text-align:right;font-weight:700;\">BH15CAC5F7388C</td></tr><tr><td style=\"color:#64748b;\">Tổng tiền</td><td style=\"text-align:right;font-weight:700;color:#2563eb;\">28.099.000 ₫</td></tr><tr><td style=\"color:#64748b;\">Thanh toán</td><td style=\"text-align:right;\">Chuyển khoản ngân hàng (VietQR)</td></tr><tr><td style=\"color:#64748b;\">Trạng thái đơn</td><td style=\"text-align:right;\">Đang chuẩn bị hàng</td></tr></table><p style=\"margin:16px 0 0;font-size:12px;color:#64748b;line-height:1.6;\">Vui lòng đăng nhập tài khoản trên website BanHang Tech Store để xem chi tiết đơn hàng và theo dõi tiến độ mới nhất.</p></td></tr><tr><td style=\"padding:16px 26px;background:#f8fafc;border-top:1px solid #e5eaf2;font-size:11px;color:#64748b;\">Email tự động từ hệ thống BanHang Tech Store. Không chia sẻ mật khẩu hoặc OTP qua email.</td></tr></table></body></html>', 'BanHang Tech Store\nĐang chuẩn bị hàng\n\nSản phẩm trong đơn hàng BH15CAC5F7388C đang được kiểm tra kỹ và đóng gói cẩn thận trước khi bàn giao vận chuyển.\n\nĐơn hàng đang được kiểm tra và đóng gói.\n\nMã đơn hàng: BH15CAC5F7388C\nTổng tiền: 28.099.000 ₫\nThanh toán: Chuyển khoản ngân hàng (VietQR)\n', 'SKIPPED', 0, 'SMTP_HOST chưa được cấu hình. Thông báo vẫn được lưu trong hệ thống website.', NULL, NULL, NULL, '2026-11-22 11:19:44', NULL),
(202, 1132, 2, '1@gmail.com', 'ORDER_SHIPPING', 'email_status:1132:SHIPPING', '[BanHang Tech Store] Đơn hàng BH15CAC5F7388C đang được giao', '<!doctype html><html lang=\"vi\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"></head><body style=\"margin:0;padding:24px 12px;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#151923;\"><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"max-width:600px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5eaf2;\"><tr><td style=\"background:#101c33;padding:20px 26px;color:#ffffff;font-size:20px;font-weight:700;letter-spacing:-0.02em;\">BanHang Tech Store</td></tr><tr><td style=\"padding:26px;\"><span style=\"display:inline-block;padding:6px 12px;border-radius:999px;background:#0369a1;color:#ffffff;font-size:12px;font-weight:700;\">BH15CAC5F7388C</span><h1 style=\"margin:14px 0 10px;font-size:22px;line-height:1.3;color:#101c33;\">Đơn hàng đang trên đường giao đến bạn</h1><p style=\"margin:0 0 14px;font-size:14px;line-height:1.65;color:#334155;\">Đơn hàng BH15CAC5F7388C đã được bàn giao cho đơn vị vận chuyển.</p><p style=\"margin:14px 0;padding:12px 14px;background:#f4f7fc;border-radius:8px;color:#1e293b;font-size:14px;line-height:1.6;\">Đơn hàng đã bàn giao cho đơn vị vận chuyển.</p><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"8\" style=\"margin:18px 0;border-collapse:collapse;background:#f8fafc;border-radius:8px;font-size:13px;\"><tr><td style=\"color:#64748b;\">Mã đơn hàng</td><td style=\"text-align:right;font-weight:700;\">BH15CAC5F7388C</td></tr><tr><td style=\"color:#64748b;\">Tổng tiền</td><td style=\"text-align:right;font-weight:700;color:#2563eb;\">28.099.000 ₫</td></tr><tr><td style=\"color:#64748b;\">Thanh toán</td><td style=\"text-align:right;\">Chuyển khoản ngân hàng (VietQR)</td></tr><tr><td style=\"color:#64748b;\">Trạng thái đơn</td><td style=\"text-align:right;\">Đang giao hàng</td></tr></table><p style=\"margin:16px 0 0;font-size:12px;color:#64748b;line-height:1.6;\">Vui lòng đăng nhập tài khoản trên website BanHang Tech Store để xem chi tiết đơn hàng và theo dõi tiến độ mới nhất.</p></td></tr><tr><td style=\"padding:16px 26px;background:#f8fafc;border-top:1px solid #e5eaf2;font-size:11px;color:#64748b;\">Email tự động từ hệ thống BanHang Tech Store. Không chia sẻ mật khẩu hoặc OTP qua email.</td></tr></table></body></html>', 'BanHang Tech Store\nĐơn hàng đang trên đường giao đến bạn\n\nĐơn hàng BH15CAC5F7388C đã được bàn giao cho đơn vị vận chuyển.\n\nĐơn hàng đã bàn giao cho đơn vị vận chuyển.\n\nMã đơn hàng: BH15CAC5F7388C\nTổng tiền: 28.099.000 ₫\nThanh toán: Chuyển khoản ngân hàng (VietQR)\n', 'SKIPPED', 0, 'SMTP_HOST chưa được cấu hình. Thông báo vẫn được lưu trong hệ thống website.', NULL, NULL, NULL, '2026-11-22 11:20:02', NULL),
(203, 1132, 2, '1@gmail.com', 'ORDER_COMPLETED', 'email_status:1132:COMPLETED', '[BanHang Tech Store] Giao hàng thành công đơn hàng BH15CAC5F7388C', '<!doctype html><html lang=\"vi\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"></head><body style=\"margin:0;padding:24px 12px;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#151923;\"><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"max-width:600px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5eaf2;\"><tr><td style=\"background:#101c33;padding:20px 26px;color:#ffffff;font-size:20px;font-weight:700;letter-spacing:-0.02em;\">BanHang Tech Store</td></tr><tr><td style=\"padding:26px;\"><span style=\"display:inline-block;padding:6px 12px;border-radius:999px;background:#137b4d;color:#ffffff;font-size:12px;font-weight:700;\">BH15CAC5F7388C</span><h1 style=\"margin:14px 0 10px;font-size:22px;line-height:1.3;color:#101c33;\">Giao hàng thành công!</h1><p style=\"margin:0 0 14px;font-size:14px;line-height:1.65;color:#334155;\">Đơn hàng BH15CAC5F7388C đã được giao thành công. Cảm ơn bạn đã tin tưởng mua sắm tại BanHang Tech Store.</p><p style=\"margin:14px 0;padding:12px 14px;background:#f4f7fc;border-radius:8px;color:#1e293b;font-size:14px;line-height:1.6;\">Đơn hàng đã được giao thành công đến khách hàng.</p><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"8\" style=\"margin:18px 0;border-collapse:collapse;background:#f8fafc;border-radius:8px;font-size:13px;\"><tr><td style=\"color:#64748b;\">Mã đơn hàng</td><td style=\"text-align:right;font-weight:700;\">BH15CAC5F7388C</td></tr><tr><td style=\"color:#64748b;\">Tổng tiền</td><td style=\"text-align:right;font-weight:700;color:#2563eb;\">28.099.000 ₫</td></tr><tr><td style=\"color:#64748b;\">Thanh toán</td><td style=\"text-align:right;\">Chuyển khoản ngân hàng (VietQR)</td></tr><tr><td style=\"color:#64748b;\">Trạng thái đơn</td><td style=\"text-align:right;\">Đã giao thành công</td></tr></table><p style=\"margin:16px 0 0;font-size:12px;color:#64748b;line-height:1.6;\">Vui lòng đăng nhập tài khoản trên website BanHang Tech Store để xem chi tiết đơn hàng và theo dõi tiến độ mới nhất.</p></td></tr><tr><td style=\"padding:16px 26px;background:#f8fafc;border-top:1px solid #e5eaf2;font-size:11px;color:#64748b;\">Email tự động từ hệ thống BanHang Tech Store. Không chia sẻ mật khẩu hoặc OTP qua email.</td></tr></table></body></html>', 'BanHang Tech Store\nGiao hàng thành công!\n\nĐơn hàng BH15CAC5F7388C đã được giao thành công. Cảm ơn bạn đã tin tưởng mua sắm tại BanHang Tech Store.\n\nĐơn hàng đã được giao thành công đến khách hàng.\n\nMã đơn hàng: BH15CAC5F7388C\nTổng tiền: 28.099.000 ₫\nThanh toán: Chuyển khoản ngân hàng (VietQR)\n', 'SKIPPED', 0, 'SMTP_HOST chưa được cấu hình. Thông báo vẫn được lưu trong hệ thống website.', NULL, NULL, NULL, '2026-11-22 11:20:15', NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tbl_inventory_movements`
--

CREATE TABLE `tbl_inventory_movements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` int(11) NOT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `movement_type` enum('INITIAL','ADJUSTMENT','ORDER_DEDUCT','ORDER_CANCEL_RESTORE') NOT NULL,
  `delta` int(11) NOT NULL,
  `stock_before` int(11) NOT NULL,
  `stock_after` int(11) NOT NULL,
  `reason` varchar(300) NOT NULL DEFAULT '',
  `actor_type` enum('CUSTOMER','ADMIN','SYSTEM') NOT NULL DEFAULT 'ADMIN',
  `actor_id` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tbl_inventory_movements`
--

INSERT INTO `tbl_inventory_movements` (`id`, `product_id`, `order_id`, `movement_type`, `delta`, `stock_before`, `stock_after`, `reason`, `actor_type`, `actor_id`, `created_at`) VALUES
(17, 2, 105, 'ORDER_DEDUCT', -1, 8, 7, 'Trừ tồn kho khi khách đặt đơn BH5D4B70E9A14B', 'CUSTOMER', 2, '2026-11-21 23:44:19'),
(18, 6, 106, 'ORDER_DEDUCT', -1, 27, 26, 'Trừ tồn kho khi khách đặt đơn BH4C01ECB9BC29', 'CUSTOMER', 2, '2026-11-22 00:44:02'),
(19, 6, 107, 'ORDER_DEDUCT', -1, 26, 25, 'Trừ tồn kho khi khách đặt đơn BH2D64EE88CDBC', 'CUSTOMER', 2, '2026-11-22 00:58:26'),
(20, 4, 108, 'ORDER_DEDUCT', -1, 5, 4, 'Trừ tồn kho khi khách đặt đơn BH841F9BEC5127', 'CUSTOMER', 2, '2026-11-22 00:59:27'),
(21, 4, 109, 'ORDER_DEDUCT', -1, 4, 3, 'Trừ tồn kho khi khách đặt đơn BH2F119C74C62B', 'CUSTOMER', 2, '2026-11-22 01:00:01'),
(1048, 2, 1132, 'ORDER_DEDUCT', -1, 7, 6, 'Trừ tồn kho khi khách đặt đơn BH15CAC5F7388C', 'CUSTOMER', 2, '2026-11-22 11:16:33'),
(1049, 113, 1132, 'ORDER_DEDUCT', -1, 120, 119, 'Trừ tồn kho khi khách đặt đơn BH15CAC5F7388C', 'CUSTOMER', 2, '2026-11-22 11:16:33');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tbl_lienhe`
--

CREATE TABLE `tbl_lienhe` (
  `id` int(11) NOT NULL,
  `thongtinlienhe` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tbl_lienhe`
--

INSERT INTO `tbl_lienhe` (`id`, `thongtinlienhe`) VALUES
(1, 'CỬA HÀNG THIẾT BỊ ĐIỆN TỬĐịa chỉ: 123 Đường ABC, Quy Nhơn, Bình ĐịnhHotline: 0123.456.789Email: support@domain.comGiờ làm việc: 08:00 - 21:30 (Tất cả các ngày trong tuần)');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tbl_login_attempts`
--

CREATE TABLE `tbl_login_attempts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `key_hash` char(64) NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tbl_notifications`
--

CREATE TABLE `tbl_notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `notification_type` varchar(40) NOT NULL,
  `dedup_key` varchar(120) DEFAULT NULL,
  `title` varchar(180) NOT NULL,
  `message` varchar(600) NOT NULL,
  `link_url` varchar(255) NOT NULL DEFAULT '',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tbl_notifications`
--

INSERT INTO `tbl_notifications` (`id`, `user_id`, `order_id`, `notification_type`, `dedup_key`, `title`, `message`, `link_url`, `is_read`, `created_at`, `read_at`) VALUES
(28, 2, 9, 'PAYMENT_CONFIRMED', 'order_paid:9', 'Thanh toán thành công!', 'BanHang đã xác nhận nhận đủ 27.500.000 ₫ cho đơn hàng BH4F97EE6EC8A7. Đơn hàng của bạn đang được chuẩn bị. Chúng tôi sẽ cập nhật ngay khi sản phẩm được giao cho đơn vị vận chuyển.', '/web_banhang/index.php?page=order&code=BH4F97EE6EC8A7', 1, '2026-11-21 20:25:55', '2026-11-21 20:26:08'),
(29, 2, 26, 'ORDER_CREATED', 'order_created:26', 'Đặt hàng thành công!', 'Đơn hàng BH2FA379193619 với tổng giá trị 26.990.000 ₫ (Thanh toán khi nhận hàng (COD)) đã được ghi nhận. Cửa hàng sẽ sớm xác nhận và chuẩn bị hàng.', '/web_banhang/index.php?page=order&code=BH2FA379193619', 1, '2026-11-21 20:27:11', '2026-11-21 20:27:11'),
(227, 2, 105, 'ORDER_CREATED', 'order_created:105', 'Đặt hàng thành công!', 'Đơn hàng BH5D4B70E9A14B với tổng giá trị 27.500.000 ₫ (Chuyển khoản ngân hàng (VietQR)) đã được ghi nhận. Vui lòng chuyển khoản đúng nội dung để cửa hàng đối soát.', '/web_banhang/index.php?page=order&code=BH5D4B70E9A14B', 1, '2026-11-21 23:44:19', '2026-11-21 23:46:38'),
(228, 2, 9, 'ORDER_CONFIRMED', 'order_status:9:CONFIRMED', 'Đơn hàng đã được xác nhận', 'Đơn hàng BH4F97EE6EC8A7 đã được BanHang xác nhận.', '/web_banhang/index.php?page=order&code=BH4F97EE6EC8A7', 1, '2026-11-21 23:47:59', '2026-11-21 23:48:02'),
(229, 2, 9, 'ORDER_PREPARING', 'order_status:9:PREPARING', 'Đơn hàng đang được chuẩn bị', 'BanHang đang kiểm tra và đóng gói đơn hàng BH4F97EE6EC8A7.', '/web_banhang/index.php?page=order&code=BH4F97EE6EC8A7', 1, '2026-11-21 23:48:07', '2026-11-21 23:49:49'),
(230, 2, 9, 'ORDER_SHIPPING', 'order_status:9:SHIPPING', 'Đơn hàng đang được giao', 'Đơn hàng BH4F97EE6EC8A7 đã được giao cho đơn vị vận chuyển.', '/web_banhang/index.php?page=order&code=BH4F97EE6EC8A7', 1, '2026-11-21 23:48:12', '2026-11-21 23:49:49'),
(231, 2, 9, 'ORDER_COMPLETED', 'order_status:9:COMPLETED', 'Giao hàng thành công!', 'Đơn hàng BH4F97EE6EC8A7 đã được giao thành công. Cảm ơn bạn đã mua sắm tại BanHang!', '/web_banhang/index.php?page=order&code=BH4F97EE6EC8A7', 1, '2026-11-21 23:48:21', '2026-11-21 23:49:49'),
(232, 2, 105, 'PAYMENT_CONFIRMED', 'order_paid:105', 'Thanh toán thành công!', 'BanHang đã xác nhận nhận đủ 27.500.000 ₫ cho đơn hàng BH5D4B70E9A14B. Đơn hàng của bạn đang được chuẩn bị. Chúng tôi sẽ cập nhật ngay khi sản phẩm được giao cho đơn vị vận chuyển.', '/web_banhang/index.php?page=order&code=BH5D4B70E9A14B', 1, '2026-11-21 23:50:50', '2026-11-21 23:51:03'),
(233, 2, 105, 'ORDER_CONFIRMED', 'order_status:105:CONFIRMED', 'Đơn hàng đã được xác nhận', 'Đơn hàng BH5D4B70E9A14B đã được BanHang xác nhận.', '/web_banhang/index.php?page=order&code=BH5D4B70E9A14B', 1, '2026-11-21 23:50:56', '2026-11-21 23:51:03'),
(234, 2, 106, 'ORDER_CREATED', 'order_created:106', 'Đặt hàng thành công!', 'Đơn hàng BH4C01ECB9BC29 với tổng giá trị 1.150.000 ₫ (Chuyển khoản ngân hàng (VietQR)) đã được ghi nhận. Vui lòng chuyển khoản đúng nội dung để cửa hàng đối soát.', '/web_banhang/index.php?page=order&code=BH4C01ECB9BC29', 1, '2026-11-22 00:44:02', '2026-11-22 00:44:19'),
(235, 2, 107, 'ORDER_CREATED', 'order_created:107', 'Đặt hàng thành công!', 'Đơn hàng BH2D64EE88CDBC với tổng giá trị 1.150.000 ₫ (Chuyển khoản ngân hàng (VietQR)) đã được ghi nhận. Vui lòng chuyển khoản đúng nội dung để cửa hàng đối soát.', '/web_banhang/index.php?page=order&code=BH2D64EE88CDBC', 1, '2026-11-22 00:58:26', '2026-11-22 00:58:33'),
(236, 2, 108, 'ORDER_CREATED', 'order_created:108', 'Đặt hàng thành công!', 'Đơn hàng BH841F9BEC5127 với tổng giá trị 32.500.000 ₫ (Chuyển khoản ngân hàng (VietQR)) đã được ghi nhận. Vui lòng chuyển khoản đúng nội dung để cửa hàng đối soát.', '/web_banhang/index.php?page=order&code=BH841F9BEC5127', 0, '2026-11-22 00:59:27', NULL),
(237, 2, 109, 'ORDER_CREATED', 'order_created:109', 'Đặt hàng thành công!', 'Đơn hàng BH2F119C74C62B với tổng giá trị 32.500.000 ₫ (Thanh toán khi nhận hàng (COD)) đã được ghi nhận. Cửa hàng sẽ sớm xác nhận và chuẩn bị hàng.', '/web_banhang/index.php?page=order&code=BH2F119C74C62B', 1, '2026-11-22 01:00:01', '2026-11-22 01:03:41'),
(500, 2, 1132, 'ORDER_CREATED', 'order_created:1132', 'Đặt hàng thành công!', 'Đơn hàng BH15CAC5F7388C với tổng giá trị 28.099.000 ₫ (Chuyển khoản ngân hàng (VietQR)) đã được ghi nhận. Vui lòng chuyển khoản đúng nội dung để cửa hàng đối soát.', '/web_banhang/index.php?page=order&code=BH15CAC5F7388C', 1, '2026-11-22 11:16:33', '2026-11-22 11:16:51'),
(501, 2, 1132, 'PAYMENT_REPORTED', 'bank_reported:1132:1', 'Đã ghi nhận báo chuyển khoản', 'BanHang đã nhận thông báo chuyển khoản cho đơn hàng BH15CAC5F7388C (28.099.000 ₫). Cửa hàng sẽ đối soát trực tiếp sao kê MB Bank rồi xác nhận sớm nhất.', '/web_banhang/index.php?page=order&code=BH15CAC5F7388C', 1, '2026-11-22 11:16:51', '2026-11-22 11:16:51'),
(502, 2, 1132, 'PAYMENT_CONFIRMED', 'order_paid:1132', 'Thanh toán thành công!', 'BanHang đã xác nhận nhận đủ 28.099.000 ₫ cho đơn hàng BH15CAC5F7388C. Đơn hàng của bạn đang được chuẩn bị. Chúng tôi sẽ cập nhật ngay khi sản phẩm được giao cho đơn vị vận chuyển.', '/web_banhang/index.php?page=order&code=BH15CAC5F7388C', 1, '2026-11-22 11:18:40', '2026-11-22 11:18:45'),
(503, 2, 1132, 'ORDER_CONFIRMED', 'order_status:1132:CONFIRMED', 'Đơn hàng đã được xác nhận', 'Đơn hàng BH15CAC5F7388C đã được BanHang xác nhận.', '/web_banhang/index.php?page=order&code=BH15CAC5F7388C', 1, '2026-11-22 11:19:28', '2026-11-22 11:21:10'),
(504, 2, 1132, 'ORDER_PREPARING', 'order_status:1132:PREPARING', 'Đơn hàng đang được chuẩn bị', 'BanHang đang kiểm tra và đóng gói đơn hàng BH15CAC5F7388C.', '/web_banhang/index.php?page=order&code=BH15CAC5F7388C', 1, '2026-11-22 11:19:44', '2026-11-22 11:21:10'),
(505, 2, 1132, 'ORDER_SHIPPING', 'order_status:1132:SHIPPING', 'Đơn hàng đang được giao', 'Đơn hàng BH15CAC5F7388C đã được giao cho đơn vị vận chuyển.', '/web_banhang/index.php?page=order&code=BH15CAC5F7388C', 1, '2026-11-22 11:20:02', '2026-11-22 11:21:10'),
(506, 2, 1132, 'ORDER_COMPLETED', 'order_status:1132:COMPLETED', 'Giao hàng thành công!', 'Đơn hàng BH15CAC5F7388C đã được giao thành công. Cảm ơn bạn đã mua sắm tại BanHang!', '/web_banhang/index.php?page=order&code=BH15CAC5F7388C', 1, '2026-11-22 11:20:15', '2026-11-22 11:21:10');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tbl_orders`
--

CREATE TABLE `tbl_orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_code` varchar(24) NOT NULL,
  `user_id` int(11) NOT NULL,
  `customer_name` varchar(160) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `delivery_address` varchar(500) NOT NULL,
  `note` varchar(500) NOT NULL DEFAULT '',
  `total_amount` bigint(20) UNSIGNED NOT NULL,
  `payment_method` enum('COD','BANK') NOT NULL DEFAULT 'COD',
  `payment_status` enum('UNPAID','PAID') NOT NULL DEFAULT 'UNPAID',
  `order_status` enum('PENDING','CONFIRMED','PREPARING','SHIPPING','COMPLETED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `payment_review_status` enum('NONE','REPORTED','REJECTED','VERIFIED') NOT NULL DEFAULT 'NONE',
  `payment_reported_at` datetime DEFAULT NULL,
  `customer_transfer_note` varchar(200) DEFAULT NULL,
  `bank_reference` varchar(120) DEFAULT NULL,
  `payment_verified_at` datetime DEFAULT NULL,
  `payment_verified_by` int(11) DEFAULT NULL,
  `admin_note` varchar(500) NOT NULL DEFAULT '',
  `customer_note` varchar(500) NOT NULL DEFAULT '',
  `shipping_carrier` varchar(100) NOT NULL DEFAULT '',
  `tracking_code` varchar(100) NOT NULL DEFAULT '',
  `shipped_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `cancel_reason` varchar(300) NOT NULL DEFAULT '',
  `cod_paid_at` datetime DEFAULT NULL,
  `cod_verified_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tbl_orders`
--

INSERT INTO `tbl_orders` (`id`, `order_code`, `user_id`, `customer_name`, `phone`, `delivery_address`, `note`, `total_amount`, `payment_method`, `payment_status`, `order_status`, `created_at`, `updated_at`, `payment_review_status`, `payment_reported_at`, `customer_transfer_note`, `bank_reference`, `payment_verified_at`, `payment_verified_by`, `admin_note`, `customer_note`, `shipping_carrier`, `tracking_code`, `shipped_at`, `delivered_at`, `cancel_reason`, `cod_paid_at`, `cod_verified_by`) VALUES
(6, 'BH57A58F6EF8AC', 2, 'TRAN QUYNH CHI', '08387388322', 'fewtrshrttyeere', '', 21770000, 'BANK', 'UNPAID', 'PENDING', '2026-11-21 16:45:34', '2026-11-21 16:45:34', 'NONE', NULL, NULL, NULL, NULL, NULL, '', '', '', '', NULL, NULL, '', NULL, NULL),
(7, 'BHFB0B22025B64', 2, 'TRAN QUYNH CHI', '08387388322', 'ừeaeasasêfsdfds', '', 1150000, 'BANK', 'UNPAID', 'PENDING', '2026-11-21 16:49:19', '2026-11-21 16:49:19', 'NONE', NULL, NULL, NULL, NULL, NULL, '', '', '', '', NULL, NULL, '', NULL, NULL),
(8, 'BH0D833522ED05', 2, 'TRAN QUYNH CHI', '08387388322', 'tygefwdqsưưeqwewqewq', 'ưdefwrfed', 37500000, 'BANK', 'UNPAID', 'PENDING', '2026-11-21 16:57:53', '2026-11-21 16:57:53', 'NONE', NULL, NULL, NULL, NULL, NULL, '', '', '', '', NULL, NULL, '', NULL, NULL),
(9, 'BH4F97EE6EC8A7', 2, 'TRAN QUYNH CHI', '08387388322', 'ewrtrehtwrt3423rtwerewq', '', 27500000, 'BANK', 'PAID', 'COMPLETED', '2026-11-21 17:08:47', '2026-11-21 23:48:21', 'VERIFIED', '2026-11-21 17:09:03', '', 'ryugdfsdbvfdfadg', '2026-11-21 20:25:55', 1, '', '', '', '', '2026-11-21 23:48:12', '2026-11-21 23:48:21', '', NULL, NULL),
(10, 'BH80C4B2C146FC', 2, 'TRAN QUYNH CHI', '08387388322', 'qewretrysdjfhgjjhghret', '', 26990000, 'COD', 'UNPAID', 'PENDING', '2026-11-21 17:15:48', '2026-11-21 17:15:48', 'NONE', NULL, NULL, NULL, NULL, NULL, '', '', '', '', NULL, NULL, '', NULL, NULL),
(26, 'BH2FA379193619', 2, 'TRAN QUYNH CHI', '08387388322', 'ádfhggfđadâfas', '', 26990000, 'COD', 'UNPAID', 'PENDING', '2026-11-21 20:27:11', '2026-11-21 20:27:11', 'NONE', NULL, NULL, NULL, NULL, NULL, '', '', '', '', NULL, NULL, '', NULL, NULL),
(105, 'BH5D4B70E9A14B', 2, 'TRAN QUYNH CHI', '08387388322', 'aDƯEAFSRGDTHTREW', '', 27500000, 'BANK', 'PAID', 'CONFIRMED', '2026-11-21 23:44:19', '2026-11-21 23:50:56', 'VERIFIED', NULL, NULL, 'ryugdfsdbvfdfadg434e', '2026-11-21 23:50:50', 1, '', '', '', '', NULL, NULL, '', NULL, NULL),
(106, 'BH4C01ECB9BC29', 2, 'TRAN QUYNH CHI', '08387388322', '170 an duong vuong', 'SAFDFF', 1150000, 'BANK', 'UNPAID', 'PENDING', '2026-11-22 00:44:02', '2026-11-22 00:44:02', 'NONE', NULL, NULL, NULL, NULL, NULL, '', '', '', '', NULL, NULL, '', NULL, NULL),
(107, 'BH2D64EE88CDBC', 2, 'TRAN QUYNH CHI', '08387388322', '170 an duong vuong', '', 1150000, 'BANK', 'UNPAID', 'PENDING', '2026-11-22 00:58:26', '2026-11-22 00:58:26', 'NONE', NULL, NULL, NULL, NULL, NULL, '', '', '', '', NULL, NULL, '', NULL, NULL),
(108, 'BH841F9BEC5127', 2, 'TRAN QUYNH CHI', '08387388322', '170 an duong vuong', '', 32500000, 'BANK', 'UNPAID', 'PENDING', '2026-11-22 00:59:27', '2026-11-22 00:59:27', 'NONE', NULL, NULL, NULL, NULL, NULL, '', '', '', '', NULL, NULL, '', NULL, NULL),
(109, 'BH2F119C74C62B', 2, 'TRAN QUYNH CHI', '08387388322', '170 an duong vuong', '', 32500000, 'COD', 'UNPAID', 'PENDING', '2026-11-22 01:00:01', '2026-11-22 01:00:01', 'NONE', NULL, NULL, NULL, NULL, NULL, '', '', '', '', NULL, NULL, '', NULL, NULL),
(1132, 'BH15CAC5F7388C', 2, 'TRAN QUYNH CHI', '08387388322', '170 an duong vuong', 'rewtrs', 28099000, 'BANK', 'PAID', 'COMPLETED', '2026-11-22 11:16:33', '2026-11-22 11:20:15', 'VERIFIED', '2026-11-22 11:16:51', 'hhyr', 'ryugdfsdbvfdfadgee', '2026-11-22 11:18:40', 1, '', '', '', '', '2026-11-22 11:20:02', '2026-11-22 11:20:15', '', NULL, NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tbl_order_cancel_requests`
--

CREATE TABLE `tbl_order_cancel_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `reason` varchar(300) NOT NULL,
  `status` enum('PENDING','APPROVED','DECLINED') NOT NULL DEFAULT 'PENDING',
  `admin_note` varchar(300) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `resolved_at` datetime DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tbl_order_items`
--

CREATE TABLE `tbl_order_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(250) NOT NULL,
  `variant_label` varchar(20) NOT NULL DEFAULT 'BASE',
  `unit_price` bigint(20) UNSIGNED NOT NULL,
  `quantity` smallint(5) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tbl_order_items`
--

INSERT INTO `tbl_order_items` (`id`, `order_id`, `product_id`, `product_name`, `variant_label`, `unit_price`, `quantity`) VALUES
(1, 6, 6, 'Sạc dự phòng Anker 20000mAh', 'BASE', 1150000, 2),
(2, 6, 5, 'Tai nghe Sony WH-1000XM5', 'BASE', 6490000, 3),
(3, 7, 6, 'Sạc dự phòng Anker 20000mAh', 'BASE', 1150000, 1),
(4, 8, 2, 'Samsung Galaxy S24 Ultra', '1TB', 37500000, 1),
(5, 9, 2, 'Samsung Galaxy S24 Ultra', '128GB', 27500000, 1),
(6, 10, 3, 'MacBook Air M3 13 inch', 'BASE', 26990000, 1),
(17, 26, 3, 'MacBook Air M3 13 inch', 'BASE', 26990000, 1),
(58, 105, 2, 'Samsung Galaxy S24 Ultra', '128GB', 27500000, 1),
(59, 106, 6, 'Sạc dự phòng Anker 20000mAh', 'BASE', 1150000, 1),
(60, 107, 6, 'Sạc dự phòng Anker 20000mAh', 'BASE', 1150000, 1),
(61, 108, 4, 'Laptop ASUS ROG Strix G16', 'BASE', 32500000, 1),
(62, 109, 4, 'Laptop ASUS ROG Strix G16', 'BASE', 32500000, 1),
(1087, 1132, 2, 'Samsung Galaxy S24 Ultra', '128GB', 27500000, 1),
(1088, 1132, 113, 'Bộ sạc Samsung USB-C', 'BASE', 599000, 1);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tbl_order_payment_details`
--

CREATE TABLE `tbl_order_payment_details` (
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `bank_id` varchar(20) NOT NULL,
  `bank_account` varchar(30) NOT NULL,
  `bank_account_name` varchar(160) NOT NULL DEFAULT '',
  `expected_amount` bigint(20) UNSIGNED NOT NULL,
  `transfer_content` varchar(64) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tbl_order_payment_details`
--

INSERT INTO `tbl_order_payment_details` (`order_id`, `bank_id`, `bank_account`, `bank_account_name`, `expected_amount`, `transfer_content`, `created_at`) VALUES
(105, '970422', '0973258529', '', 27500000, 'BANHANG BH5D4B70E9A14B', '2026-11-21 23:44:19'),
(106, '970422', '0973258529', 'TRAN QUYNH CHI', 1150000, 'BANHANG BH4C01ECB9BC29', '2026-11-22 00:44:02'),
(107, '970422', '0973258529', 'TRAN QUYNH CHI', 1150000, 'BANHANG BH2D64EE88CDBC', '2026-11-22 00:58:26'),
(108, '970422', '0973258529', 'TRAN QUYNH CHI', 32500000, 'BANHANG BH841F9BEC5127', '2026-11-22 00:59:27'),
(1132, '970422', '0973258529', 'TRAN QUYNH CHI', 28099000, 'BANHANG BH15CAC5F7388C', '2026-11-22 11:16:33');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tbl_order_status_history`
--

CREATE TABLE `tbl_order_status_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `from_status` varchar(30) DEFAULT NULL,
  `to_status` varchar(30) NOT NULL,
  `actor_type` enum('CUSTOMER','ADMIN','SYSTEM') NOT NULL DEFAULT 'ADMIN',
  `actor_id` int(11) NOT NULL DEFAULT 0,
  `note` varchar(500) NOT NULL DEFAULT '',
  `is_public` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tbl_order_status_history`
--

INSERT INTO `tbl_order_status_history` (`id`, `order_id`, `from_status`, `to_status`, `actor_type`, `actor_id`, `note`, `is_public`, `created_at`) VALUES
(1, 6, NULL, 'PENDING', 'SYSTEM', 0, 'Khởi tạo lịch sử trạng thái từ dữ liệu hiện có.', 1, '2026-11-21 16:45:34'),
(2, 7, NULL, 'PENDING', 'SYSTEM', 0, 'Khởi tạo lịch sử trạng thái từ dữ liệu hiện có.', 1, '2026-11-21 16:49:19'),
(3, 8, NULL, 'PENDING', 'SYSTEM', 0, 'Khởi tạo lịch sử trạng thái từ dữ liệu hiện có.', 1, '2026-11-21 16:57:53'),
(4, 9, NULL, 'PENDING', 'SYSTEM', 0, 'Khởi tạo lịch sử trạng thái từ dữ liệu hiện có.', 1, '2026-11-21 17:08:47'),
(5, 10, NULL, 'PENDING', 'SYSTEM', 0, 'Khởi tạo lịch sử trạng thái từ dữ liệu hiện có.', 1, '2026-11-21 17:15:48'),
(30, 9, NULL, 'PAYMENT_VERIFIED', 'ADMIN', 1, 'Đã đối soát và xác nhận nhận đủ 27.500.000 ₫ qua MB Bank.', 1, '2026-11-21 20:25:55'),
(31, 26, NULL, 'PENDING', 'CUSTOMER', 2, 'Đơn hàng thanh toán khi nhận hàng (COD) đã được tạo, đang chờ cửa hàng xác nhận.', 1, '2026-11-21 20:27:11'),
(201, 105, NULL, 'PENDING', 'CUSTOMER', 2, 'Đơn hàng chuyển khoản VietQR đã được tạo, đang chờ khách chuyển khoản và cửa hàng đối soát.', 1, '2026-11-21 23:44:19'),
(202, 9, 'PENDING', 'CONFIRMED', 'ADMIN', 1, 'Cửa hàng đã xác nhận đơn hàng.', 1, '2026-11-21 23:47:59'),
(203, 9, 'CONFIRMED', 'PREPARING', 'ADMIN', 1, 'Đơn hàng đang được kiểm tra và đóng gói.', 1, '2026-11-21 23:48:07'),
(204, 9, 'PREPARING', 'SHIPPING', 'ADMIN', 1, 'Đơn hàng đã bàn giao cho đơn vị vận chuyển.', 1, '2026-11-21 23:48:12'),
(205, 9, 'SHIPPING', 'COMPLETED', 'ADMIN', 1, 'Đơn hàng đã được giao thành công đến khách hàng.', 1, '2026-11-21 23:48:21'),
(206, 105, NULL, 'PAYMENT_VERIFIED', 'ADMIN', 1, 'Đã đối soát và xác nhận nhận đủ 27.500.000 ₫ qua MB Bank.', 1, '2026-11-21 23:50:50'),
(207, 105, 'PENDING', 'CONFIRMED', 'ADMIN', 1, 'Cửa hàng đã xác nhận đơn hàng.', 1, '2026-11-21 23:50:56'),
(208, 106, NULL, 'PENDING', 'CUSTOMER', 2, 'Đơn hàng chuyển khoản VietQR đã được tạo, đang chờ khách chuyển khoản và cửa hàng đối soát.', 1, '2026-11-22 00:44:02'),
(209, 107, NULL, 'PENDING', 'CUSTOMER', 2, 'Đơn hàng chuyển khoản VietQR đã được tạo, đang chờ khách chuyển khoản và cửa hàng đối soát.', 1, '2026-11-22 00:58:26'),
(210, 108, NULL, 'PENDING', 'CUSTOMER', 2, 'Đơn hàng chuyển khoản VietQR đã được tạo, đang chờ khách chuyển khoản và cửa hàng đối soát.', 1, '2026-11-22 00:59:27'),
(211, 109, NULL, 'PENDING', 'CUSTOMER', 2, 'Đơn hàng thanh toán khi nhận hàng (COD) đã được tạo, đang chờ cửa hàng xác nhận.', 1, '2026-11-22 01:00:01'),
(3401, 1132, NULL, 'PENDING', 'CUSTOMER', 2, 'Đơn hàng chuyển khoản VietQR đã được tạo, đang chờ khách chuyển khoản và cửa hàng đối soát.', 1, '2026-11-22 11:16:33'),
(3402, 1132, NULL, 'PAYMENT_VERIFIED', 'ADMIN', 1, 'Đã đối soát và xác nhận nhận đủ 28.099.000 ₫ qua MB Bank.', 1, '2026-11-22 11:18:40'),
(3403, 1132, 'PENDING', 'CONFIRMED', 'ADMIN', 1, 'Cửa hàng đã xác nhận đơn hàng.', 1, '2026-11-22 11:19:28'),
(3404, 1132, 'CONFIRMED', 'PREPARING', 'ADMIN', 1, 'Đơn hàng đang được kiểm tra và đóng gói.', 1, '2026-11-22 11:19:44'),
(3405, 1132, 'PREPARING', 'SHIPPING', 'ADMIN', 1, 'Đơn hàng đã bàn giao cho đơn vị vận chuyển.', 1, '2026-11-22 11:20:02'),
(3406, 1132, 'SHIPPING', 'COMPLETED', 'ADMIN', 1, 'Đơn hàng đã được giao thành công đến khách hàng.', 1, '2026-11-22 11:20:15');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tbl_payment_events`
--

CREATE TABLE `tbl_payment_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `event_type` enum('CUSTOMER_REPORTED','ADMIN_CONFIRMED','ADMIN_REJECTED','ADMIN_EXPIRED','ADMIN_COD_PAID') NOT NULL,
  `actor_type` enum('CUSTOMER','ADMIN') NOT NULL,
  `actor_id` int(11) NOT NULL,
  `detail` varchar(500) NOT NULL DEFAULT '',
  `reference_code` varchar(120) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tbl_payment_events`
--

INSERT INTO `tbl_payment_events` (`id`, `order_id`, `event_type`, `actor_type`, `actor_id`, `detail`, `reference_code`, `created_at`) VALUES
(1, 9, 'CUSTOMER_REPORTED', 'CUSTOMER', 2, '', NULL, '2026-11-21 17:09:03'),
(18, 9, 'ADMIN_CONFIRMED', 'ADMIN', 1, 'Admin đã đối soát đủ số tiền 27500000 VND với sao kê MB Bank', 'ryugdfsdbvfdfadg', '2026-11-21 20:25:55'),
(88, 105, 'ADMIN_CONFIRMED', 'ADMIN', 1, 'Admin đã đối soát đủ số tiền 27500000 VND với sao kê MB Bank', 'ryugdfsdbvfdfadg434e', '2026-11-21 23:50:50'),
(214, 1132, 'CUSTOMER_REPORTED', 'CUSTOMER', 2, 'hhyr', NULL, '2026-11-22 11:16:51'),
(215, 1132, 'ADMIN_CONFIRMED', 'ADMIN', 1, 'Admin đã đối soát đủ số tiền 28099000 VND với sao kê MB Bank', 'ryugdfsdbvfdfadgee', '2026-11-22 11:18:40');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tbl_product_variants`
--

CREATE TABLE `tbl_product_variants` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` int(11) NOT NULL,
  `variant_code` varchar(20) NOT NULL,
  `price` bigint(20) UNSIGNED NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tbl_product_variants`
--

INSERT INTO `tbl_product_variants` (`id`, `product_id`, `variant_code`, `price`, `is_active`) VALUES
(1, 1, '128GB', 29990000, 1),
(2, 2, '128GB', 27500000, 1),
(4, 1, '256GB', 31990000, 1),
(5, 1, '512GB', 34990000, 1),
(6, 1, '1TB', 39990000, 1),
(7, 2, '256GB', 29500000, 1),
(8, 2, '512GB', 32500000, 1),
(9, 2, '1TB', 37500000, 1),
(68, 52, '128GB', 16990000, 1),
(69, 52, '256GB', 18990000, 1),
(70, 52, '512GB', 21990000, 1),
(71, 52, '1TB', 26990000, 1),
(72, 53, '128GB', 20990000, 1),
(73, 53, '256GB', 22990000, 1),
(74, 53, '512GB', 25990000, 1),
(75, 53, '1TB', 30990000, 1),
(76, 54, '128GB', 18990000, 1),
(77, 54, '256GB', 20990000, 1),
(78, 54, '512GB', 23990000, 1),
(79, 54, '1TB', 28990000, 1),
(80, 55, '128GB', 22990000, 1),
(81, 55, '256GB', 24990000, 1),
(82, 55, '512GB', 27990000, 1),
(83, 55, '1TB', 32990000, 1),
(84, 56, '128GB', 11990000, 1),
(85, 56, '256GB', 13990000, 1),
(86, 56, '512GB', 16990000, 1),
(87, 56, '1TB', 21990000, 1),
(88, 57, '128GB', 25990000, 1),
(89, 57, '256GB', 27990000, 1),
(90, 57, '512GB', 30990000, 1),
(91, 57, '1TB', 35990000, 1),
(99, 83, '128GB', 7490000, 1),
(100, 83, '256GB', 9490000, 1),
(101, 83, '512GB', 12490000, 1),
(102, 83, '1TB', 17490000, 1),
(103, 84, '128GB', 10990000, 1),
(104, 84, '256GB', 12990000, 1),
(105, 84, '512GB', 15990000, 1),
(106, 84, '1TB', 20990000, 1),
(107, 85, '128GB', 9990000, 1),
(108, 85, '256GB', 11990000, 1),
(109, 85, '512GB', 14990000, 1),
(110, 85, '1TB', 19990000, 1),
(111, 86, '128GB', 13990000, 1),
(112, 86, '256GB', 15990000, 1),
(113, 86, '512GB', 18990000, 1),
(114, 86, '1TB', 23990000, 1),
(115, 87, '128GB', 13990000, 1),
(116, 87, '256GB', 15990000, 1),
(117, 87, '512GB', 18990000, 1),
(118, 87, '1TB', 23990000, 1),
(119, 88, '128GB', 16990000, 1),
(120, 88, '256GB', 18990000, 1),
(121, 88, '512GB', 21990000, 1),
(122, 88, '1TB', 26990000, 1),
(123, 89, '128GB', 17990000, 1),
(124, 89, '256GB', 19990000, 1),
(125, 89, '512GB', 22990000, 1),
(126, 89, '1TB', 27990000, 1),
(127, 90, '128GB', 19990000, 1),
(128, 90, '256GB', 21990000, 1),
(129, 90, '512GB', 24990000, 1),
(130, 90, '1TB', 29990000, 1),
(131, 91, '128GB', 22490000, 1),
(132, 91, '256GB', 24490000, 1),
(133, 91, '512GB', 27490000, 1),
(134, 91, '1TB', 32490000, 1),
(135, 92, '128GB', 24990000, 1),
(136, 92, '256GB', 26990000, 1),
(137, 92, '512GB', 29990000, 1),
(138, 92, '1TB', 34990000, 1),
(139, 93, '128GB', 28990000, 1),
(140, 93, '256GB', 30990000, 1),
(141, 93, '512GB', 33990000, 1),
(142, 93, '1TB', 38990000, 1),
(143, 94, '128GB', 23990000, 1),
(144, 94, '256GB', 25990000, 1),
(145, 94, '512GB', 28990000, 1),
(146, 94, '1TB', 33990000, 1),
(147, 95, '128GB', 30990000, 1),
(148, 95, '256GB', 32990000, 1),
(149, 95, '512GB', 35990000, 1),
(150, 95, '1TB', 40990000, 1),
(151, 96, '128GB', 5490000, 1),
(152, 96, '256GB', 7490000, 1),
(153, 96, '512GB', 10490000, 1),
(154, 96, '1TB', 15490000, 1),
(155, 97, '128GB', 8490000, 1),
(156, 97, '256GB', 10490000, 1),
(157, 97, '512GB', 13490000, 1),
(158, 97, '1TB', 18490000, 1),
(159, 98, '128GB', 4490000, 1),
(160, 98, '256GB', 6490000, 1),
(161, 98, '512GB', 9490000, 1),
(162, 98, '1TB', 14490000, 1),
(163, 99, '128GB', 31990000, 1),
(164, 99, '256GB', 33990000, 1),
(165, 99, '512GB', 36990000, 1),
(166, 99, '1TB', 41990000, 1),
(167, 100, '128GB', 3990000, 1),
(168, 100, '256GB', 5990000, 1),
(169, 100, '512GB', 8990000, 1),
(170, 100, '1TB', 13990000, 1);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tbl_sanpham`
--

CREATE TABLE `tbl_sanpham` (
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
(2, 'Samsung Galaxy S24 Ultra', 'SSS24U', 27500000, 6, 's24ultra.jpg', 'Màn hình: 6.8 inch Dynamic AMOLED 2X<br>Chip: Snapdragon 8 Gen 3 for Galaxy<br>Bút S-Pen tích hợp', 'Samsung Galaxy S24 Ultra mang đến kỷ nguyên quyền năng Galaxy AI, khung viền Titan bền bỉ cùng hệ thống camera mắt thần bóng đêm 200MP.', 1, 1),
(3, 'MacBook Air M3 13 inch', 'MBA2024', 26990000, 6, 'macbookm3.jpg', 'CPU: Apple M3 8 nhân<br>RAM: 8GB | SSD: 256GB<br>Trọng lượng: 1.24 kg', 'MacBook Air M3 siêu mỏng nhẹ với hiệu năng vượt trội cho công việc văn phòng, đồ họa 2D và thời lượng pin lên đến 18 tiếng.', 1, 2),
(4, 'Laptop ASUS ROG Strix G16', 'ROG16', 32500000, 3, 'asusrog.jpg', 'CPU: Intel Core i7-13650HX<br>Card đồ họa: NVIDIA GeForce RTX 4060 8GB<br>Màn hình: 16 inch 165Hz', 'Chiến mượt mọi tựa game AAA với hệ thống tản nhiệt 3 quạt thông minh và bàn phím LED RGB từng phím ấn tượng.', 1, 2),
(5, 'Tai nghe Sony WH-1000XM5', 'SNXM5', 6490000, 17, 'sonyxm5.jpg', 'Công nghệ: Chống ồn chủ động ANC<br>Thời lượng pin: 30 giờ<br>Kết nối: Bluetooth 5.2', 'Tai nghe chụp tai không dây chống ồn tốt nhất phân khúc với 8 micro thu âm và chất âm chuẩn Hi-Res Audio.', 1, 3),
(6, 'Sạc dự phòng Anker 20000mAh', 'AK20K', 1150000, 25, 'anker20k.jpg', 'Dung lượng: 20.000 mAh<br>Công suất tối đa: 30W Power Delivery<br>Cổng sạc: 2 USB-C, 1 USB-A', 'Sạc nhanh đồng thời nhiều thiết bị an toàn với công nghệ kiểm soát nhiệt độ ActiveShield 2.0.', 1, 4),
(52, 'iPhone 15', 'BH-TECH-P001', 16990000, 975, 'ip15.jpg', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 1),
(53, 'iPhone 16', 'BH-TECH-P002', 20990000, 975, 'iphone-16 thuong.webp', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 1),
(54, 'Samsung Galaxy S24', 'BH-TECH-P003', 18990000, 970, 'samsung-galaxy-s24.jpg', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 1),
(55, 'Samsung Galaxy S25', 'BH-TECH-P004', 22990000, 975, 'samsung-galaxy-s25.jpg', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 1),
(56, 'OPPO Reno 14', 'BH-TECH-P005', 11990000, 965, 'oppo reno 14.webp', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 1),
(57, 'Samsung Galaxy Z Flip7', 'BH-TECH-P006', 25990000, 975, 'samsung_galaxy_z_flip7.webp', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 1),
(58, 'ASUS TUF Gaming A15', 'BH-TECH-P007', 21490000, 960, 'Asus TUF Gaming A15.jpg', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 2),
(59, 'ASUS Vivobook Go 15', 'BH-TECH-P008', 11990000, 975, 'asus vivobook go 15.jpg', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 2),
(60, 'Dell Inspiron 15 3530', 'BH-TECH-P009', 16490000, 975, 'Dell inspiron 15 3530 i5 1334U.jpg', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 2),
(61, 'Dell Latitude 3450', 'BH-TECH-P010', 19490000, 975, 'dell latitude 3450 i7 1355U.jpg', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 2),
(62, 'AirPods 4 ANC', 'BH-TECH-P011', 3990000, 970, 'airpods 4 ANC.webp', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 3),
(63, 'AirPods Pro 3', 'BH-TECH-P012', 6490000, 975, 'airpods-pro-3.webp', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 3),
(64, 'Củ sạc iPhone USB-C', 'BH-TECH-P013', 390000, 965, 'củ sạc ip.webp', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 4),
(65, 'Ốp lưng Samsung', 'BH-TECH-P014', 199000, 975, 'ốp lưng samsung.webp', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 4),
(66, 'Pin dự phòng Anker 335', 'BH-TECH-P015', 990000, 960, 'sạc dự phòng anker 335.png', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 4),
(67, 'Giá đỡ điện thoại', 'BH-TECH-P016', 189000, 975, 'giá đỡ đt.jpg', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 4),
(83, 'iPhone 12', 'BH-TECH-P017', 7490000, 120, 'ip 12 thuong.webp', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 1),
(84, 'iPhone 12 Pro Max', 'BH-TECH-P018', 10990000, 120, 'ip 12prm.webp', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 1),
(85, 'iPhone 13', 'BH-TECH-P019', 9990000, 120, 'ip 13.webp', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 1),
(86, 'iPhone 13 Pro Max', 'BH-TECH-P020', 13990000, 120, 'ip 13prm.webp', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 1),
(87, 'iPhone 14 Plus', 'BH-TECH-P021', 13990000, 120, 'iPhone-14-plus.jpg', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 1),
(88, 'iPhone 14 Pro Max', 'BH-TECH-P022', 16990000, 120, 'ip 14prm.webp', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 1),
(89, 'iPhone 15 Plus', 'BH-TECH-P023', 17990000, 120, 'iphone-15-plus.webp', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 1),
(90, 'iPhone 15 Pro', 'BH-TECH-P024', 19990000, 120, 'ip15pro.jpg', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 1),
(91, 'iPhone 15 Pro Max', 'BH-TECH-P025', 22490000, 120, 'ip 15prm.webp', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 1),
(92, 'iPhone 16 Pro', 'BH-TECH-P026', 24990000, 120, 'iphone-16-pro-titan-sa-mac.png', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 1),
(93, 'iPhone 16 Pro Max', 'BH-TECH-P027', 28990000, 120, 'ip 16prm.webp', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 1),
(94, 'iPhone 17', 'BH-TECH-P028', 23990000, 120, 'iphone_17 thuong.webp', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 1),
(95, 'iPhone 17 Pro', 'BH-TECH-P029', 30990000, 120, 'iphone-17-pro.webp', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 1),
(96, 'Samsung Galaxy S10', 'BH-TECH-P030', 5490000, 120, 'samsung-galaxy-s10.webp', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 1),
(97, 'Samsung Galaxy S21', 'BH-TECH-P031', 8490000, 120, 'samsung galaxy s21.jpg', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 1),
(98, 'Samsung Galaxy A16', 'BH-TECH-P032', 4490000, 120, '(600x600)_samsung_galaxy_a16.jpg', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 1),
(99, 'Samsung Galaxy Z Fold6', 'BH-TECH-P033', 31990000, 120, 'samsung_galaxy_z_fold6.webp', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 1),
(100, 'OPPO A5x', 'BH-TECH-P034', 3990000, 120, 'oppo-a5x.webp', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 1),
(101, 'Dell Inspiron 15 3520 i7', 'BH-TECH-P035', 15490000, 120, 'Dell Inspirion 15 3520 i7 1255U.jpg', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 2),
(102, 'Samsung Galaxy Watch8', 'BH-TECH-P036', 8990000, 120, 'samsung-galaxy-watch8.jpg', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 33),
(103, 'Samsung Galaxy Fit3', 'BH-TECH-P037', 1490000, 120, 'samsung-galaxy-fit3-hong.jpg', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 33),
(104, 'Dây đồng hồ Zobo G1', 'BH-TECH-P038', 250000, 120, 'zobo-g1-423mm-day-silicone.jpg', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 33),
(105, 'Củ sạc nhanh Samsung', 'BH-TECH-P039', 490000, 120, 'củ sạc samsung.webp', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 4),
(106, 'Cáp sạc Samsung USB-C', 'BH-TECH-P040', 199000, 120, 'dây sạc samsung.webp', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 4),
(107, 'Cáp sạc iPhone', 'BH-TECH-P041', 199000, 120, 'dây sạc ip.webp', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 4),
(108, 'Dây sạc iPhone 15/16 USB-C', 'BH-TECH-P042', 249000, 120, 'dây sạc cho ip 15 16.jpg', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 4),
(109, 'Ốp lưng iPhone', 'BH-TECH-P043', 149000, 120, 'ốp lưng ip.webp', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 4),
(110, 'Ốp lưng iPhone 17', 'BH-TECH-P044', 219000, 120, 'ốp lưng ip17.jpg', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 4),
(111, 'Ốp lưng silicon', 'BH-TECH-P045', 99000, 120, 'ốp lưng silicon.jpg', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 4),
(112, 'Bộ củ và dây sạc iPhone', 'BH-TECH-P046', 590000, 120, 'củ và dây ip.webp', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 4),
(113, 'Bộ sạc Samsung USB-C', 'BH-TECH-P047', 599000, 119, 'dây và củ samsung.webp', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 4),
(114, 'Bộ củ dây sạc Samsung', 'BH-TECH-P048', 649000, 120, 'củ dây sạc samsung.webp', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 4),
(115, 'Bộ sạc nhanh OPPO', 'BH-TECH-P049', 499000, 120, 'đồ sạc oppo.webp', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 4),
(116, 'Ốp lưng điện thoại trong suốt', 'BH-TECH-P050', 99000, 120, 'ốp lưng.jpeg', 'Giá tham khảo phục vụ đồ án thương mại điện tử.', 'Sản phẩm công nghệ có hình ảnh minh họa trong dự án BanHang. Có thể điều chỉnh giá và tồn kho tại Admin.', 1, 4);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tbl_settings`
--

CREATE TABLE `tbl_settings` (
  `setting_key` varchar(60) NOT NULL,
  `setting_value` varchar(400) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tbl_settings`
--

INSERT INTO `tbl_settings` (`setting_key`, `setting_value`) VALUES
('bank_account', '0973258529'),
('bank_id', '970422'),
('bank_name', 'TRAN QUYNH CHI'),
('store_name', 'BanHang Tech Store'),
('support_email', '');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tbl_tintuc`
--

CREATE TABLE `tbl_tintuc` (
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
(2, 'Top 5 mẫu laptop sinh viên đáng mua nhất năm 2026', 'Tổng hợp những mẫu laptop mỏng nhẹ, pin trâu giá tốt dành cho học tập và làm việc.', 'Các dòng laptop thế hệ mới được trang bị vi xử lý tích hợp nhân AI giúp tối ưu thời lượng pin cả ngày dài và xử lý mượt mà mọi tác vụ văn phòng.', 'tintuc2.jpg', '2026-11-18 14:00:00', 1);

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `tbl_admin`
--
ALTER TABLE `tbl_admin`
  ADD PRIMARY KEY (`id_admin`);

--
-- Chỉ mục cho bảng `tbl_cart`
--
ALTER TABLE `tbl_cart`
  ADD PRIMARY KEY (`id_cart`);

--
-- Chỉ mục cho bảng `tbl_cart_details`
--
ALTER TABLE `tbl_cart_details`
  ADD PRIMARY KEY (`id_cart_details`);

--
-- Chỉ mục cho bảng `tbl_dangky`
--
ALTER TABLE `tbl_dangky`
  ADD PRIMARY KEY (`id_dangky`);

--
-- Chỉ mục cho bảng `tbl_danhmuc`
--
ALTER TABLE `tbl_danhmuc`
  ADD PRIMARY KEY (`id_danhmuc`);

--
-- Chỉ mục cho bảng `tbl_danhmucbaiviet`
--
ALTER TABLE `tbl_danhmucbaiviet`
  ADD PRIMARY KEY (`id_baiviet`);

--
-- Chỉ mục cho bảng `tbl_email_outbox`
--
ALTER TABLE `tbl_email_outbox`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_email_dedup` (`dedup_key`),
  ADD KEY `idx_outbox_status_created` (`status`,`created_at`),
  ADD KEY `fk_banhang_outbox_order` (`order_id`),
  ADD KEY `idx_outbox_claim` (`status`,`claimed_at`),
  ADD KEY `idx_outbox_retry` (`status`,`attempts`,`next_retry_at`);

--
-- Chỉ mục cho bảng `tbl_inventory_movements`
--
ALTER TABLE `tbl_inventory_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inv_product_time` (`product_id`,`created_at`),
  ADD KEY `idx_inv_order` (`order_id`);

--
-- Chỉ mục cho bảng `tbl_lienhe`
--
ALTER TABLE `tbl_lienhe`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `tbl_login_attempts`
--
ALTER TABLE `tbl_login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hash_time` (`key_hash`,`attempted_at`),
  ADD KEY `idx_attempt_time` (`attempted_at`);

--
-- Chỉ mục cho bảng `tbl_notifications`
--
ALTER TABLE `tbl_notifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_notif_dedup` (`dedup_key`),
  ADD KEY `idx_user_read_created` (`user_id`,`is_read`,`created_at`),
  ADD KEY `idx_user_id_desc` (`user_id`,`id`),
  ADD KEY `fk_banhang_notif_order` (`order_id`);

--
-- Chỉ mục cho bảng `tbl_orders`
--
ALTER TABLE `tbl_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_code` (`order_code`),
  ADD UNIQUE KEY `uniq_bank_reference` (`bank_reference`),
  ADD KEY `idx_user_created` (`user_id`,`created_at`),
  ADD KEY `idx_status_created` (`order_status`,`created_at`),
  ADD KEY `idx_payment_status` (`payment_status`,`created_at`),
  ADD KEY `idx_review_created` (`payment_method`,`payment_review_status`,`created_at`),
  ADD KEY `idx_orders_phone` (`phone`),
  ADD KEY `idx_orders_bank_verified` (`payment_method`,`payment_status`,`payment_verified_at`),
  ADD KEY `idx_orders_cod_paid` (`payment_method`,`payment_status`,`cod_paid_at`);

--
-- Chỉ mục cho bảng `tbl_order_cancel_requests`
--
ALTER TABLE `tbl_order_cancel_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_cancel_request_order` (`order_id`),
  ADD KEY `idx_cancel_status_time` (`status`,`created_at`);

--
-- Chỉ mục cho bảng `tbl_order_items`
--
ALTER TABLE `tbl_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_v2_items_order` (`order_id`),
  ADD KEY `idx_product` (`product_id`);

--
-- Chỉ mục cho bảng `tbl_order_payment_details`
--
ALTER TABLE `tbl_order_payment_details`
  ADD PRIMARY KEY (`order_id`);

--
-- Chỉ mục cho bảng `tbl_order_status_history`
--
ALTER TABLE `tbl_order_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_history_order_time` (`order_id`,`created_at`);

--
-- Chỉ mục cho bảng `tbl_payment_events`
--
ALTER TABLE `tbl_payment_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_events` (`order_id`,`created_at`);

--
-- Chỉ mục cho bảng `tbl_product_variants`
--
ALTER TABLE `tbl_product_variants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_product_variant` (`product_id`,`variant_code`),
  ADD KEY `idx_product_active` (`product_id`,`is_active`);

--
-- Chỉ mục cho bảng `tbl_sanpham`
--
ALTER TABLE `tbl_sanpham`
  ADD PRIMARY KEY (`id_sanpham`);

--
-- Chỉ mục cho bảng `tbl_settings`
--
ALTER TABLE `tbl_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Chỉ mục cho bảng `tbl_tintuc`
--
ALTER TABLE `tbl_tintuc`
  ADD PRIMARY KEY (`id_baiviet`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `tbl_admin`
--
ALTER TABLE `tbl_admin`
  MODIFY `id_admin` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT cho bảng `tbl_cart`
--
ALTER TABLE `tbl_cart`
  MODIFY `id_cart` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `tbl_cart_details`
--
ALTER TABLE `tbl_cart_details`
  MODIFY `id_cart_details` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT cho bảng `tbl_dangky`
--
ALTER TABLE `tbl_dangky`
  MODIFY `id_dangky` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=173;

--
-- AUTO_INCREMENT cho bảng `tbl_danhmuc`
--
ALTER TABLE `tbl_danhmuc`
  MODIFY `id_danhmuc` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT cho bảng `tbl_danhmucbaiviet`
--
ALTER TABLE `tbl_danhmucbaiviet`
  MODIFY `id_baiviet` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `tbl_email_outbox`
--
ALTER TABLE `tbl_email_outbox`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=221;

--
-- AUTO_INCREMENT cho bảng `tbl_inventory_movements`
--
ALTER TABLE `tbl_inventory_movements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1057;

--
-- AUTO_INCREMENT cho bảng `tbl_lienhe`
--
ALTER TABLE `tbl_lienhe`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `tbl_login_attempts`
--
ALTER TABLE `tbl_login_attempts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `tbl_notifications`
--
ALTER TABLE `tbl_notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=528;

--
-- AUTO_INCREMENT cho bảng `tbl_orders`
--
ALTER TABLE `tbl_orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1137;

--
-- AUTO_INCREMENT cho bảng `tbl_order_cancel_requests`
--
ALTER TABLE `tbl_order_cancel_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT cho bảng `tbl_order_items`
--
ALTER TABLE `tbl_order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1093;

--
-- AUTO_INCREMENT cho bảng `tbl_order_status_history`
--
ALTER TABLE `tbl_order_status_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3426;

--
-- AUTO_INCREMENT cho bảng `tbl_payment_events`
--
ALTER TABLE `tbl_payment_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=221;

--
-- AUTO_INCREMENT cho bảng `tbl_product_variants`
--
ALTER TABLE `tbl_product_variants`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=226;

--
-- AUTO_INCREMENT cho bảng `tbl_sanpham`
--
ALTER TABLE `tbl_sanpham`
  MODIFY `id_sanpham` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=147;

--
-- AUTO_INCREMENT cho bảng `tbl_tintuc`
--
ALTER TABLE `tbl_tintuc`
  MODIFY `id_baiviet` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `tbl_email_outbox`
--
ALTER TABLE `tbl_email_outbox`
  ADD CONSTRAINT `fk_banhang_outbox_order` FOREIGN KEY (`order_id`) REFERENCES `tbl_orders` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `tbl_inventory_movements`
--
ALTER TABLE `tbl_inventory_movements`
  ADD CONSTRAINT `fk_banhang_inv_order` FOREIGN KEY (`order_id`) REFERENCES `tbl_orders` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `tbl_notifications`
--
ALTER TABLE `tbl_notifications`
  ADD CONSTRAINT `fk_banhang_notif_order` FOREIGN KEY (`order_id`) REFERENCES `tbl_orders` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `tbl_order_cancel_requests`
--
ALTER TABLE `tbl_order_cancel_requests`
  ADD CONSTRAINT `fk_banhang_cancel_request_order` FOREIGN KEY (`order_id`) REFERENCES `tbl_orders` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `tbl_order_items`
--
ALTER TABLE `tbl_order_items`
  ADD CONSTRAINT `fk_v2_items_order` FOREIGN KEY (`order_id`) REFERENCES `tbl_orders` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `tbl_order_payment_details`
--
ALTER TABLE `tbl_order_payment_details`
  ADD CONSTRAINT `fk_banhang_payment_details_order` FOREIGN KEY (`order_id`) REFERENCES `tbl_orders` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `tbl_order_status_history`
--
ALTER TABLE `tbl_order_status_history`
  ADD CONSTRAINT `fk_banhang_history_order` FOREIGN KEY (`order_id`) REFERENCES `tbl_orders` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `tbl_payment_events`
--
ALTER TABLE `tbl_payment_events`
  ADD CONSTRAINT `fk_banhang_payment_events_order` FOREIGN KEY (`order_id`) REFERENCES `tbl_orders` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
