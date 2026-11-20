<?php
include('../../config/config.php');

/*
|==================================================
| THƯ MỤC UPLOAD (ĐƯỜNG DẪN VẬT LÝ)
|==================================================
*/
$upload_path = __DIR__ . '/uploads/';

// tạo thư mục nếu chưa có
if (!is_dir($upload_path)) {
    mkdir($upload_path, 0777, true);
}

/*
|==================================================
| THÊM BÀI VIẾT
|==================================================
*/
if (isset($_POST['thembaiviet'])) {

    $tenbaiviet = $_POST['tenbaiviet'] ?? '';
    $tomtat     = $_POST['tomtat'] ?? '';
    $noidung    = $_POST['noidung'] ?? '';
    $trangthai  = $_POST['trangthai'] ?? 1;

    $hinhanh = '';

    // xử lý ảnh
    if (isset($_FILES['hinhanh']) && $_FILES['hinhanh']['name'] != '') {

        if ($_FILES['hinhanh']['error'] == 0) {

            $hinhanh = time() . '_' . basename($_FILES['hinhanh']['name']);

            move_uploaded_file(
                $_FILES['hinhanh']['tmp_name'],
                $upload_path . $hinhanh
            );
        }
    }

    mysqli_query($mysqli, "
        INSERT INTO tbl_tintuc
        (tenbaiviet, tomtat, noidung, hinhanh, trangthai)
        VALUES
        ('$tenbaiviet', '$tomtat', '$noidung', '$hinhanh', '$trangthai')
    ");

    header('Location: ../../index.php?action=quanlybaiviet&query=lietke');
    exit;
}

/*
|==================================================
| SỬA BÀI VIẾT
|==================================================
*/
if (isset($_POST['suabaiviet'])) {

    $id         = $_POST['id_baiviet'];
    $tenbaiviet = $_POST['tenbaiviet'];
    $tomtat     = $_POST['tomtat'];
    $noidung    = $_POST['noidung'];
    $trangthai  = $_POST['trangthai'];

    // lấy ảnh cũ
    $old = mysqli_fetch_array(mysqli_query(
        $mysqli,
        "SELECT hinhanh FROM tbl_tintuc WHERE id_baiviet='$id'"
    ));

    $hinhanh = $old['hinhanh'];

    // nếu upload ảnh mới
    if (isset($_FILES['hinhanh']) && $_FILES['hinhanh']['name'] != '') {

        if ($_FILES['hinhanh']['error'] == 0) {

            // xóa ảnh cũ
            if ($hinhanh != '' && file_exists($upload_path . $hinhanh)) {
                unlink($upload_path . $hinhanh);
            }

            $hinhanh = time() . '_' . basename($_FILES['hinhanh']['name']);

            move_uploaded_file(
                $_FILES['hinhanh']['tmp_name'],
                $upload_path . $hinhanh
            );
        }
    }

    mysqli_query($mysqli, "
        UPDATE tbl_tintuc SET
            tenbaiviet='$tenbaiviet',
            tomtat='$tomtat',
            noidung='$noidung',
            hinhanh='$hinhanh',
            trangthai='$trangthai'
        WHERE id_baiviet='$id'
    ");

    header('Location: ../../index.php?action=quanlybaiviet&query=lietke');
    exit;
}

/*
|==================================================
| XÓA BÀI VIẾT
|==================================================
*/
if (isset($_GET['xoabaiviet'])) {

    $id = $_GET['xoabaiviet'];

    $row = mysqli_fetch_array(mysqli_query(
        $mysqli,
        "SELECT hinhanh FROM tbl_tintuc WHERE id_baiviet='$id'"
    ));

    if ($row['hinhanh'] != '' && file_exists($upload_path . $row['hinhanh'])) {
        unlink($upload_path . $row['hinhanh']);
    }

    mysqli_query($mysqli, "DELETE FROM tbl_tintuc WHERE id_baiviet='$id'");

    header('Location: ../../index.php?action=quanlybaiviet&query=lietke');
    exit;
}
