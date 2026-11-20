<?php
    // Lấy danh mục từ database
    $sql_danhmuc = "SELECT * FROM tbl_danhmuc ORDER BY id_danhmuc DESC";
    $query_danhmuc = mysqli_query($mysqli, $sql_danhmuc);

    // Kiểm tra đăng xuất
    if(isset($_GET['dangxuat']) && $_GET['dangxuat'] == 1){
        unset($_SESSION['dangky']);
    }
?>  

<div class="menu">
    <ul class="list_menu">

        <li><a href="index.php">Trang chủ</a></li>

        
        <li class="dropdown">
            <a href="#"
               class="dropdown-toggle"
               data-bs-toggle="dropdown"
               aria-expanded="false">
                Sản phẩm <span class="caret"></span>
            </a>

            <ul class="submenu dropdown-menu">
                <?php while ($row_danhmuc = mysqli_fetch_array($query_danhmuc)) { ?>
                    <li>
                        <a class="dropdown-item"
                           href="index.php?quanly=danhmucsanpham&id=<?php echo $row_danhmuc['id_danhmuc']; ?>">
                            <?php echo $row_danhmuc['tendanhmuc']; ?>
                        </a>
                    </li>
                <?php } ?>
            </ul>
        </li>

        <li><a href="index.php?quanly=giohang">Giỏ hàng</a></li>

        <?php if (isset($_SESSION['dangky'])) { ?>
            <li><a href="index.php?dangxuat=1">Đăng xuất</a></li>
            <li><a href="index.php?quanly=thaydoimatkhau">Đổi mật khẩu</a></li>
        <?php } else { ?>
            <li><a href="index.php?quanly=dangky">Đăng ký</a></li>
        <?php } ?>

        <li><a href="index.php?quanly=tintuc">Tin tức</a></li>
        <li><a href="index.php?quanly=lienhe">Liên hệ</a></li>

    </ul>
    <form action="index.php?quanly=timkiem" method="POST" class="search-form">
        <input type="text" placeholder="Tìm kiếm sản phẩm..." name="tukhoa">
        <input type="submit" name="timkiem" value="Tìm kiếm">
    </form>
</div>