<div id="main">
    <?php
    include("sidebar/sidebar.php");
    ?>
<div class="maincontent">
            <?php
            if(isset($_GET['quanly'])){
                $tam=$_GET['quanly'];
            }
            else{
                $tam='';
            }
       
            if($tam=='danhmucsanpham'){
                include("main/danhmuc.php");
            }elseif($tam=='giohang'){
                include("main/giohang.php");
            }
            elseif($tam=='tintuc'){
                include("main/tintuc.php");
            }
            elseif($tam=='lienhe'){
                include("main/lienhe.php");
            }elseif($tam=='sanpham'){
                include("main/sanpham.php");
            }elseif($tam=='dangky'){
                include("main/dangky.php");
             } elseif($tam=='thanhtoan'){
                include("main/thanhtoan.php");
             }elseif($tam=='dangnhap'){
                include("main/dangnhap.php");
             }elseif($tam=='timkiem'){
                include("main/timkiem.php");
                }elseif($tam=='camon'){
                include("main/camon.php");
                }elseif($tam=='thaydoimatkhau'){
                include("main/thaydoimatkhau.php");
            }
              elseif ($tam == 'baiviet') {
            include("main/chitietbaiviet.php"); 

        }
        elseif($tam=='xulythanhtoan'){
        // Nhúng trực tiếp file xử lý vào đây để không bao giờ bị lỗi 404
        include("main/xulythanhtoan.php");
        }
           else
            {
                include("main/index.php");
            }
            ?>
        </div>
    
            
        </div>
