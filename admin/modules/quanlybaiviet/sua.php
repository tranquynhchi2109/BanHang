<?php
$id = $_GET['id'];
$sql = "SELECT * FROM tbl_tintuc WHERE id_baiviet='$id' LIMIT 1";
$query = mysqli_query($mysqli, $sql);
$row = mysqli_fetch_array($query);
?>

<form method="POST"
      action="modules/quanlybaiviet/xuly.php"
      enctype="multipart/form-data">

    <input type="hidden" name="id_baiviet" value="<?= $row['id_baiviet'] ?>">

    <p>Tên bài viết</p>
    <input type="text" name="tenbaiviet" value="<?= $row['tenbaiviet'] ?>" required>

    <p>Tóm tắt</p>
    <textarea name="tomtat"><?= $row['tomtat'] ?></textarea>

    <p>Nội dung</p>
    <textarea name="noidung"><?= $row['noidung'] ?></textarea>

    <p>Ảnh hiện tại</p>
    <img src="modules/quanlybaiviet/uploads/<?= $row['hinhanh'] ?>" width="150">

    <p>Ảnh mới</p>
    <input type="file" name="hinhanh">

    <p>Trạng thái</p>
    <select name="trangthai">
        <option value="1" <?= $row['trangthai']==1?'selected':'' ?>>Hiển thị</option>
        <option value="0" <?= $row['trangthai']==0?'selected':'' ?>>Ẩn</option>
    </select>

    <br><br>
    <button type="submit" name="suabaiviet">Cập nhật</button>
</form>
