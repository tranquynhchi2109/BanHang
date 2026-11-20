<div class="container-chitiet">
    <h3 class="product-detail-title" style="color: #fff; margin-bottom: 20px;">Chi tiết sản phẩm</h3>

    <?php
    // Truy vấn lấy chi tiết sản phẩm và tên danh mục
    $sql_chitiet = "SELECT * FROM tbl_sanpham, tbl_danhmuc 
                    WHERE tbl_sanpham.id_danhmuc = tbl_danhmuc.id_danhmuc 
                    AND tbl_sanpham.id_sanpham='$_GET[id]' 
                    LIMIT 1";
    $query_chitiet = mysqli_query($mysqli, $sql_chitiet);
    
    while($row = mysqli_fetch_array($query_chitiet)){
        // 1. Xử lý biến thể dung lượng và giá
        $variant = isset($_GET['variant']) ? $_GET['variant'] : '128GB';
        $gia_hien_tai = $row['giasp'];
        
        if ($row['tendanhmuc'] == 'Điện thoại') {
            if ($variant == '256GB') $gia_hien_tai += 2000000;
            elseif ($variant == '512GB') $gia_hien_tai += 5000000;
            elseif ($variant == '1TB')   $gia_hien_tai += 10000000;
        }
    ?>

    <style>
        /* CSS cho phần biến thể dung lượng */
        .storage-box-dark { padding: 12px; border-radius: 8px; display: inline-block; margin: 10px 0 20px 0; }
        .storage-options { display: flex; gap: 8px; }
        .btn-variant { 
            display: inline-block; padding: 8px 18px; background-color: #3a3a3a; 
            color: #fff !important; border: 1px solid #444; border-radius: 4px; 
            text-decoration: none !important; font-size: 13px; transition: 0.3s;
        }
        .btn-variant.active { background-color: #007bff; border-color: #007bff; font-weight: bold; }
        .btn-variant:hover { background-color: #555; }

        /* CSS tùy chỉnh cho Tabs Bootstrap 5 */
        .tabs-container { margin-top: 30px; }
        .nav-tabs { border-bottom: 2px solid #ff9800; }
        .nav-tabs .nav-link { 
            color: #fff; background-color: #333; border: 1px solid #444; 
            margin-right: 5px; font-weight: bold; 
        }
        .nav-tabs .nav-link.active { 
            background-color: #ff9800 !important; color: #000 !important; border-color: #ff9800; 
        }
        .tab-content { 
            padding: 20px; border: 1px solid #444; border-top: none; 
            background: #fff; color: #333; line-height: 1.8; border-radius: 0 0 8px 8px; 
        }
    </style>

    <div class="wrapper_chitiet" style="display: flex; gap: 30px; background: #222; padding: 20px; border-radius: 10px;">
        <div class="hinhanh_sanpham" style="flex: 1; text-align: center;">
            <img src="admin/modules/quanlysp/uploads/<?php echo $row['hinhanh'] ?>" style="width: 100%; max-width: 400px; border-radius: 8px;">
        </div>

        <form method="POST" action="pages/main/themgiohang.php?idsanpham=<?php echo $row['id_sanpham'] ?>" style="flex: 1.5; color: #ddd;">
            <div class="chitiet_sanpham">
                <h2 style="color: #fff; font-weight: bold;"><?php echo $row['tensanpham'] ?></h2>
                <p>Mã sản phẩm: <span style="color: #ff9800;"><?php echo $row['masp'] ?></span></p>

                <p class="gia_sanpham" style="font-size: 24px;">
                    Giá: <strong style="color:#ff4444"><?php echo number_format($gia_hien_tai, 0, ',', '.') ?>đ</strong>
                </p>

                <?php if ($row['tendanhmuc'] == 'Điện thoại') { ?>
                    <p><strong>Chọn dung lượng:</strong></p>
                    <div class="storage-box-dark">
                        <div class="storage-options">
                            <?php 
                            $caps = ['128GB', '256GB', '512GB', '1TB'];
                            foreach($caps as $cap) {
                                $active = ($variant == $cap) ? 'active' : '';
                                echo "<a href='index.php?quanly=sanpham&id=".$_GET['id']."&variant=$cap' class='btn-variant $active'>$cap</a>";
                            }
                            ?>
                        </div>
                    </div>
                    <input type="hidden" name="dungluong" value="<?php echo $variant ?>">
                    <input type="hidden" name="giasp_bien_the" value="<?php echo $gia_hien_tai ?>">
                <?php } ?>

                <div style="margin: 15px 0;">
                    <p>Số lượng kho: <span class="badge bg-success"><?php echo $row['soluong'] ?></span></p>
                    <p>Danh mục: <strong><?php echo $row['tendanhmuc'] ?></strong></p>
                </div>

                <input class="btn btn-warning btn-lg" name="themgiohang" type="submit" value="Thêm vào giỏ hàng" style="width: 100%; font-weight: bold; padding: 15px;">
            </div>
        </form>
    </div>

    <div class="tabs-container">
        <ul class="nav nav-tabs" id="productTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="thongso-tab" data-bs-toggle="tab" data-bs-target="#thongso" type="button" role="tab">Thông số kỹ thuật</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="noidung-tab" data-bs-toggle="tab" data-bs-target="#noidung" type="button" role="tab">Mô tả chi tiết</button>
            </li>
        </ul>

        <div class="tab-content" id="productTabContent">
            <div class="tab-pane fade show active" id="thongso" role="tabpanel">
                <div class="specs-content">
                    <?php echo $row['tomtat'] ?>
                </div>
            </div>
            <div class="tab-pane fade" id="noidung" role="tabpanel">
                <div class="detail-content">
                    <?php echo $row['noidung'] ?>
                </div>
            </div>
        </div>
    </div>
    <?php } // Kết thúc vòng lặp while ?>
</div>