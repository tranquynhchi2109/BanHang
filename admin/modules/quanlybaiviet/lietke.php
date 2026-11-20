<table border="1" width="100%" style="border-collapse:collapse">
    <tr>
        <th>ID</th>
        <th>Tên bài viết</th>
        <th>Ảnh</th>
        <th>Trạng thái</th>
        <th>Quản lý</th>
    </tr>

<?php
$sql = "SELECT * FROM tbl_tintuc ORDER BY id_baiviet DESC";
$query = mysqli_query($mysqli, $sql);

while ($row = mysqli_fetch_array($query)) {
?>
    <tr>
        <td><?= $row['id_baiviet'] ?></td>
        <td><?= $row['tenbaiviet'] ?></td>

        <td>
    <img src="modules/quanlybaiviet/uploads/<?= $row['hinhanh'] ?>" 
         style="width: 100px; height: 70px; object-fit: cover; border-radius: 5px;">
</td>

        <td><?= $row['trangthai'] == 1 ? 'Hiển thị' : 'Ẩn' ?></td>

        <td>
            <a href="index.php?action=quanlybaiviet&query=sua&id=<?= $row['id_baiviet'] ?>">Sửa</a> |
            <a href="modules/quanlybaiviet/xuly.php?xoabaiviet=<?= $row['id_baiviet'] ?>"
               onclick="return confirm('Xóa bài viết này?')">
               Xóa
            </a>
        </td>
    </tr>
<?php } ?>
</table>
