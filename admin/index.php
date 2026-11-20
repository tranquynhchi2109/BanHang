<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="css/stylesadmin.css">
    <title>Admin</title>
</head>
<body>
    <h3 class="title_admin">Welcome</h3>
    <div class="wrapper">
        <?php
            include("config/config.php");
            include("modules/header.php");
            include("modules/menu.php");
            include("modules/main.php");
            include("modules/footer.php");
        ?>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.ckeditor.com/ckeditor5/41.0.0/classic/ckeditor.js"></script>
    
    <script>
    // Hàm khởi tạo CKEditor 5 cho từng phần tử
    function createEditor(elementId) {
        ClassicEditor
            .create(document.querySelector(elementId), {
                // Bạn có thể để trống licenseKey nếu dùng bản Free
                licenseKey: '', 
                // Toolbar cơ bản
                toolbar: [
                    'undo', 'redo', '|', 'bold', 'italic', '|',
                    'fontSize', 'fontFamily', 'fontColor', 'fontBackgroundColor', '|',
                    'link', 'bulletedList', 'numberedList', 'insertTable', 'blockQuote'
                ]
            })
            .then(editor => {
                console.log('Editor ' + elementId + ' was initialized');
            })
            .catch(error => {
                console.error(error);
            });
    }

    // Gọi hàm cho 2 ô của bạn
    document.addEventListener("DOMContentLoaded", function() {
        createEditor('#tomtat');
        createEditor('#noidung');
         createEditor('#thongtinlienhe');
    });

</script>
</body>
</html>