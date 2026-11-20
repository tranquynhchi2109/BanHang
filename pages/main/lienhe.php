<p class="contact-title">Liên hệ</p>
<div class="contact-wrapper">
    <?php
    $sql_lh = "SELECT * FROM tbl_lienhe WHERE id=1";
    $query_lh = mysqli_query($mysqli, $sql_lh);
    while($dong = mysqli_fetch_array($query_lh)){
    ?>
        <div class="contact-content">
            <?php echo $dong['thongtinlienhe'] ?>
        </div>
    <?php
    }
    ?>
</div>