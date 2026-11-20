<p>Thêm bài viết tin tức</p>

<form method="POST"
      action="modules/quanlybaiviet/xuly.php"
      enctype="multipart/form-data">

<table border="1" width="50%" style="border-collapse: collapse;">
    
    <tr>
        <td>Tên bài viết</td>
        <td>
            <input type="text" name="tenbaiviet" required>
        </td>
    </tr>

    <tr>
        <td>Hình ảnh</td>
        <td>
            <input type="file" name="hinhanh">
        </td>
    </tr>

    <tr>
        <td>Tóm tắt</td>
        <td>
            <textarea rows="5" name="tomtat" style="width:100%"></textarea>
        </td>
    </tr>

    <tr>
        <td>Nội dung</td>
        <td>
            <textarea rows="10" name="noidung" style="width:100%"></textarea>
        </td>
    </tr>

    <tr>
        <td>Trạng thái</td>
        <td>
            <select name="trangthai">
                <option value="1" selected>Hiển thị</option>
                <option value="0">Ẩn</option>
            </select>
        </td>
    </tr>

    <tr>
        <td colspan="2" align="center">
            <input type="submit" name="thembaiviet" value="Thêm bài viết">
        </td>
    </tr>

</table>
</form>
