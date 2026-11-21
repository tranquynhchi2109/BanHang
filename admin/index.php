<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app.php';
migrate_legacy_session();
$page=(string)($_GET['page']??'dashboard');
$pages=['login','dashboard','products','product_form','categories','orders','order','customers','payments','inventory','revenue','recommendations','news','article_form','settings','legacy_orders','legacy_order'];
if (!in_array($page,$pages,true)) $page='dashboard';

function save_product_upload(?string $previous): string
{
    if (!isset($_FILES['photo']) || ($_FILES['photo']['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return (string)$previous;
    $upload=$_FILES['photo'];
    if (($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK ||
        !is_uploaded_file((string)($upload['tmp_name']??'')) ||
        (int)($upload['size']??0)<1 || (int)$upload['size']>3*1024*1024) {
        throw new DomainException('Ảnh phải hợp lệ và nhỏ hơn 3MB.');
    }
    $tmp=(string)$upload['tmp_name'];
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $extension=match($mime) {'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp',default=>null};
    if ($extension===null || getimagesize($tmp)===false) throw new DomainException('Chỉ hỗ trợ JPEG, PNG hoặc WebP.');
    $folder=dirname(__DIR__).'/admin/modules/quanlysp/uploads';
    if (!is_dir($folder)) throw new DomainException('Không tìm thấy thư mục upload của dự án gốc.');
    $filename='product_'.bin2hex(random_bytes(12)).'.'.$extension;
    if (!move_uploaded_file($tmp,$folder.'/'.$filename)) throw new RuntimeException('Không thể ghi ảnh lên máy chủ.');
    return $filename;
}
function save_news_upload(?string $previous): string
{
    if (!isset($_FILES['photo']) || ($_FILES['photo']['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return (string)$previous;
    $upload=$_FILES['photo'];
    if (($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK ||
        !is_uploaded_file((string)($upload['tmp_name']??'')) ||
        (int)($upload['size']??0)<1 || (int)$upload['size']>3*1024*1024) throw new DomainException('Ảnh phải hợp lệ và nhỏ hơn 3MB.');
    $tmp=(string)$upload['tmp_name'];
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $extension=match($mime) {'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp',default=>null};
    if ($extension===null || getimagesize($tmp)===false) throw new DomainException('Chỉ hỗ trợ JPEG, PNG hoặc WebP.');
    $folder=dirname(__DIR__).'/admin/modules/quanlybaiviet/uploads';
    if (!is_dir($folder)) throw new DomainException('Không tìm thấy thư mục upload bài viết.');
    $filename='news_'.bin2hex(random_bytes(12)).'.'.$extension;
    if (!move_uploaded_file($tmp,$folder.'/'.$filename)) throw new RuntimeException('Không thể ghi ảnh bài viết.');
    return $filename;
}
try {
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        ensure_post();
        $action=(string)($_POST['action']??'');
        if ($action==='login_admin') {
            $username=trim((string)($_POST['username']??''));
            $password=(string)($_POST['password']??'');
            if ($username==='' || $password==='') throw new DomainException('Nhập tài khoản và mật khẩu.');
            if (!login_admin($username,$password)) throw new DomainException('Tài khoản hoặc mật khẩu không đúng.');
            notice('Đăng nhập quản trị thành công.','success');
            redirect_to(admin_to('dashboard'));
        }
        if ($action==='logout_admin') {
            sign_out('admin');
            notice('Đã đăng xuất.','success');
            redirect_to(admin_to('login'));
        }
        require_admin();
        switch ($action) {
            case 'save_product':
                $id=as_int($_POST['id']??0,0,2147483647);
                $name=trim((string)($_POST['name']??''));
                $sku=trim((string)($_POST['sku']??''));
                $category=as_int($_POST['category']??0);
                $price=as_int($_POST['price']??-1,0,2000000000);
                $stock=as_int($_POST['stock']??-1,0,1000000);
                $summary=trim((string)($_POST['summary']??''));
                $description=trim((string)($_POST['description']??''));
                $visible=(int)(($_POST['visible']??'0')==='1');
                if (mb_strlen($name)<3 || mb_strlen($name)>250 || mb_strlen($sku)<1 || mb_strlen($sku)>100 ||
                    mb_strlen($summary)>10000 || mb_strlen($description)>200000) throw new DomainException('Thông tin sản phẩm vượt giới hạn.');
                if (!row('SELECT id_danhmuc FROM tbl_danhmuc WHERE id_danhmuc=?',[$category])) throw new DomainException('Danh mục không tồn tại.');
                $existing=$id>0?row('SELECT hinhanh FROM tbl_sanpham WHERE id_sanpham=?',[$id]):null;
                if ($id>0 && !$existing) throw new DomainException('Sản phẩm không còn tồn tại.');
                $filename=save_product_upload($existing['hinhanh']??'');
                if ($id===0 && $filename==='') throw new DomainException('Sản phẩm mới cần có ảnh.');
                if ($id>0) {
                    run('UPDATE tbl_sanpham SET tensanpham=?,masp=?,giasp=?,soluong=?,hinhanh=?,tomtat=?,noidung=?,tinhtrang=?,id_danhmuc=? WHERE id_sanpham=?',
                        [$name,$sku,$price,$stock,$filename,$summary,$description,$visible,$category,$id]);
                } else {
                    run('INSERT INTO tbl_sanpham(tensanpham,masp,giasp,soluong,hinhanh,tomtat,noidung,tinhtrang,id_danhmuc)
                         VALUES (?,?,?,?,?,?,?,?,?)',
                        [$name,$sku,$price,$stock,$filename,$summary,$description,$visible,$category]);
                }
                notice('Đã lưu sản phẩm.','success');
                redirect_to(admin_to('products'));
            case 'hide_product':
                $id=as_int($_POST['id']??0);
                run('UPDATE tbl_sanpham SET tinhtrang=0 WHERE id_sanpham=?',[$id]);
                notice('Sản phẩm đã được ẩn khỏi cửa hàng.','success');
                redirect_to(admin_to('products'));
            case 'save_category':
                $id=as_int($_POST['id']??0,0,2147483647);
                $name=trim((string)($_POST['name']??''));
                $sort=as_int($_POST['sort']??0,0,10000);
                if (mb_strlen($name)<2 || mb_strlen($name)>100) throw new DomainException('Tên danh mục không hợp lệ.');
                if ($id>0) run('UPDATE tbl_danhmuc SET tendanhmuc=?,thutu=? WHERE id_danhmuc=?',[$name,$sort,$id]);
                else run('INSERT INTO tbl_danhmuc(tendanhmuc,thutu) VALUES (?,?)',[$name,$sort]);
                notice('Đã lưu danh mục.','success');
                redirect_to(admin_to('categories'));
            case 'legacy_order_seen':
                $legacyId=as_int($_POST['id']??0);
                $changed=run('UPDATE tbl_cart SET cart_status=0 WHERE id_cart=?',[$legacyId]);
                if ($changed!==1) throw new DomainException('Không tìm thấy đơn hàng cũ.');
                notice('Đã đánh dấu đơn hàng là đã xem.','success');
                redirect_to(admin_to('legacy_order',['id'=>$legacyId]));
            case 'order_status':
                $id=as_int($_POST['id']??0);
                $next=(string)($_POST['status']??'');
                db()->beginTransaction();
                try {
                    $order=row('SELECT id,order_status,payment_status FROM tbl_orders WHERE id=? FOR UPDATE',[$id]);
                    if (!$order) throw new DomainException('Không tìm thấy đơn hàng.');
                    $transitions=[
                        'PENDING'=>['CONFIRMED','CANCELLED'],
                        'CONFIRMED'=>['SHIPPING','CANCELLED'],
                        'SHIPPING'=>['COMPLETED'],
                        'COMPLETED'=>[],
                        'CANCELLED'=>[],
                    ];
                    if (!in_array($next,$transitions[$order['order_status']]??[],true)) throw new DomainException('Chuyển trạng thái đơn hàng không được phép.');
                    if ($next==='CANCELLED') {
                        if ($order['payment_status']==='PAID') throw new DomainException('Đơn đã thanh toán cần xử lý hoàn tiền thủ công trước khi hủy.');
                        $items=rows('SELECT product_id,quantity FROM tbl_order_items WHERE order_id=?',[$id]);
                        foreach ($items as $item) {
                            run('UPDATE tbl_sanpham SET soluong=soluong+? WHERE id_sanpham=?',[(int)$item['quantity'],(int)$item['product_id']]);
                        }
                    }
                    run('UPDATE tbl_orders SET order_status=? WHERE id=?',[$next,$id]);
                    db()->commit();
                } catch (Throwable $e) {
                    if (db()->inTransaction()) db()->rollBack();
                    throw $e;
                }
                notice('Đã cập nhật trạng thái đơn hàng.','success');
                redirect_to(admin_to('order',['id'=>$id]));
            case 'mark_paid':
                $id=as_int($_POST['id']??0);
                $changed=run("UPDATE tbl_orders SET payment_status='PAID'
                    WHERE id=? AND payment_method='BANK' AND payment_status='UNPAID' AND order_status<>'CANCELLED'",[$id]);
                if ($changed!==1) throw new DomainException('Đơn không hợp lệ hoặc đã được xác nhận.');
                notice('Đã ghi nhận thanh toán do quản trị viên xác nhận.','success');
                redirect_to(admin_to('order',['id'=>$id]));
            case 'save_settings':
                $values=[
                    'store_name'=>trim((string)($_POST['store_name']??'')),
                    'support_email'=>trim((string)($_POST['support_email']??'')),
                    'bank_id'=>strtoupper(trim((string)($_POST['bank_id']??''))),
                    'bank_account'=>trim((string)($_POST['bank_account']??'')),
                    'bank_name'=>trim((string)($_POST['bank_name']??'')),
                    'contact_info'=>trim((string)($_POST['contact_info']??'')),
                ];
                if (mb_strlen($values['store_name'])<2 || mb_strlen($values['store_name'])>100 ||
                    ($values['support_email']!=='' && !filter_var($values['support_email'],FILTER_VALIDATE_EMAIL)) ||
                    ($values['bank_id']!=='' && !preg_match('/^[A-Z0-9]{2,15}$/',$values['bank_id'])) ||
                    ($values['bank_account']!=='' && !preg_match('/^[0-9]{6,24}$/',$values['bank_account'])) ||
                    mb_strlen($values['bank_name'])>150 || mb_strlen($values['contact_info'])>2000) throw new DomainException('Cấu hình ngân hàng hoặc cửa hàng không hợp lệ.');
                foreach ($values as $key=>$value) {
                    if ($key==='contact_info') continue;
                    run('INSERT INTO tbl_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',[$key,$value]);
                }
                run('INSERT INTO tbl_lienhe(id,thongtinlienhe) VALUES (1,?) ON DUPLICATE KEY UPDATE thongtinlienhe=VALUES(thongtinlienhe)',[$values['contact_info']]);
                notice('Đã lưu cấu hình cửa hàng.','success');
                redirect_to(admin_to('settings'));
            case 'save_article':
                $id=as_int($_POST['id']??0,0,2147483647);
                $title=trim((string)($_POST['title']??''));
                $summary=trim((string)($_POST['summary']??''));
                $body=trim((string)($_POST['body']??''));
                $visible=(int)(($_POST['visible']??'0')==='1');
                if (mb_strlen($title)<4 || mb_strlen($title)>255 ||
                    mb_strlen($summary)>10000 || mb_strlen($body)>200000) throw new DomainException('Nội dung bài viết không hợp lệ.');
                $old=$id>0?row('SELECT hinhanh FROM tbl_tintuc WHERE id_baiviet=?',[$id]):null;
                if ($id>0 && !$old) throw new DomainException('Không tìm thấy bài viết.');
                $image=save_news_upload($old['hinhanh']??'');
                if ($id===0 && $image==='') throw new DomainException('Hãy chọn ảnh đại diện bài viết.');
                if ($id>0) run('UPDATE tbl_tintuc SET tenbaiviet=?,tomtat=?,noidung=?,hinhanh=?,trangthai=? WHERE id_baiviet=?',
                    [$title,$summary,$body,$image,$visible,$id]);
                else run('INSERT INTO tbl_tintuc(tenbaiviet,tomtat,noidung,hinhanh,trangthai) VALUES (?,?,?,?,?)',
                    [$title,$summary,$body,$image,$visible]);
                notice('Đã lưu bài viết.','success');
                redirect_to(admin_to('news'));
            default:
                throw new DomainException('Thao tác quản trị không hợp lệ.');
        }
    }
} catch (DomainException $e) {
    notice($e->getMessage(),'danger');
    $target=match((string)($_POST['action']??'')) {
        'login_admin'=>'login','save_product'=>'product_form','save_category'=>'categories',
        'order_status','mark_paid'=>'orders','legacy_order_seen'=>'legacy_order','save_settings'=>'settings','save_article'=>'article_form',
        default=>'dashboard'
    };
    redirect_to(admin_to($target));
} catch (Throwable $e) {
    error_log('[BanHang admin] '.$e->getMessage());
    http_response_code(500);
    exit('Không thể xử lý thao tác quản trị. Kiểm tra log máy chủ.');
}

function admin_page_head(string $title): void
{
    page_head($title,true);
    $admin=require_admin();
    $nav=[
        'dashboard'=>['◈','Tổng quan'],
        'products'=>['▣','Sản phẩm'],
        'categories'=>['▦','Danh mục'],
        'orders'=>['♧','Đơn hàng'],
        'legacy_orders'=>['▤','Đơn hàng cũ'],
        'customers'=>['♙','Khách hàng'],
        'payments'=>['▤','Thanh toán'],
        'inventory'=>['◫','Kho hàng'],
        'revenue'=>['▥','Doanh thu'],
        'recommendations'=>['◇','Gợi ý sản phẩm'],
        'news'=>['▧','Tin tức'],
        'settings'=>['⚙','Cấu hình'],
    ];
    echo '<div class="admin-shell"><aside class="admin-sidebar" id="admin-sidebar"><a class="admin-brand" href="'.admin_to('dashboard').'"><span class="brand__mark">B</span> BANHANG <small>MANAGEMENT CONSOLE</small></a><p class="admin-caption">WORKSPACE</p>';
    $current=(string)($_GET['page']??'dashboard');
    foreach ($nav as $key=>$entry) {
        echo '<a class="'.($current===$key?'active':'').'" href="'.admin_to($key).'"><span>'.h($entry[0]).'</span>'.h($entry[1]).'</a>';
    }
    echo '<hr><a href="' . h(project_url('index.php')) . '">↗ Xem cửa hàng</a>';
    echo '</aside><div class="admin-body"><header class="admin-topbar"><button type="button" class="mobile-toggle" data-menu-toggle>☰</button><strong>BanHang Management</strong><div class="admin-profile">Quản trị viên: '.h($admin['username']);
    echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="logout_admin"><button class="button button--light" type="submit">Đăng xuất</button></form></div></header><main class="admin-content">';
    echo '<div class="flash">';notices_html();echo '</div>';
}
function admin_page_foot(): void
{
    echo '</main></div></div>';
    page_foot(true);
}
function admin_title(string $title,string $desc,string $button=''): void
{
    echo '<div class="admin-heading"><div><h1>'.h($title).'</h1><p>'.h($desc).'</p></div>'.$button.'</div>';
}
function admin_table_start(array $headers): void
{
    echo '<div class="surface admin-table-wrap"><table><thead><tr>';
    foreach ($headers as $header) echo '<th>'.h($header).'</th>';
    echo '</tr></thead><tbody>';
}
function admin_table_end(): void {echo '</tbody></table></div>';}
function stat_card(string $label,string $value,string $note=''): void
{
    echo '<article class="stat-card surface"><p>'.h($label).'</p><strong>'.h($value).'</strong><span>'.h($note).'</span></article>';
}
try {
if ($page==='login' && current_admin()===null) {
    page_head('Đăng nhập quản trị',true);
    echo '<main class="admin-login"><div class="surface auth-box"><a class="brand" href="' . h(project_url('index.php')) . '"><span class="brand__mark">B</span> BANHANG.</a><h1>Chào mừng trở lại</h1><p>Đăng nhập vào hệ thống quản trị.</p>';
    notices_html();
    echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="login_admin">';
    echo '<label class="field-group">Tên đăng nhập <input class="field" name="username" required autocomplete="username"></label>';
    echo '<label class="field-group">Mật khẩu <input class="field" name="password" type="password" required autocomplete="current-password"></label>';
    echo '<button class="button full" type="submit">Đăng nhập quản trị</button></form></div></main>';
    page_foot(true);
    exit;
}
require_admin();
admin_page_head('Quản trị BanHang');
if ($page==='dashboard') {
    $income=row("SELECT COALESCE(SUM(total_amount),0) AS total,COUNT(*) AS n FROM tbl_orders WHERE order_status<>'CANCELLED'");
    $customers=row('SELECT COUNT(*) AS n FROM tbl_dangky');
    $products=row('SELECT COUNT(*) AS n FROM tbl_sanpham');
    $pending=row("SELECT COUNT(*) AS n FROM tbl_orders WHERE order_status='PENDING'");
    admin_title('Tổng quan hệ thống','Số liệu cửa hàng và các đơn hàng mới nhất.');
    echo '<div class="stats">';
    stat_card('Doanh thu ghi nhận',money((int)($income['total']??0)),'Không tính đơn đã hủy');
    stat_card('Đơn hàng mới',(string)($income['n']??0),'Đơn hàng đã ghi nhận');
    stat_card('Khách hàng',(string)($customers['n']??0),'Tài khoản đang lưu');
    stat_card('Sản phẩm',(string)($products['n']??0),'Tất cả trạng thái');
    echo '</div><div class="admin-grid"><div class="surface"><h2>Đơn hàng gần đây</h2>';
    admin_table_start(['Mã đơn','Khách','Giá trị','Trạng thái']);
    foreach (rows('SELECT id,order_code,customer_name,total_amount,order_status FROM tbl_orders ORDER BY id DESC LIMIT 8') as $o) {
        echo '<tr><td><a class="link-blue" href="'.admin_to('order',['id'=>$o['id']]).'">'.h($o['order_code']).'</a></td><td>'.h($o['customer_name']).'</td><td>'.money((int)$o['total_amount']).'</td><td><span class="badge">'.h($o['order_status']).'</span></td></tr>';
    }
    admin_table_end();
    echo '</div><div class="surface"><h2>Vận hành</h2><p class="metric-number">'.(int)($pending['n']??0).'</p><p>Đơn chờ xác nhận</p><a class="button" href="'.admin_to('orders').'">Xem đơn hàng →</a><hr><p>Các giao dịch VietQR phải được đối soát thủ công trước khi xác nhận thanh toán.</p></div></div>';
}
elseif ($page==='products') {
    admin_title('Quản lý sản phẩm','Danh sách tồn kho, giá bán và trạng thái hiển thị.',
        '<a class="button" href="'.admin_to('product_form').'">＋ Thêm sản phẩm</a>');
    $q=trim((string)($_GET['q']??''));
    $params=[];$filter='';
    if ($q!=='') {$filter=' WHERE p.tensanpham LIKE ? OR p.masp LIKE ?';$params=['%'.mb_substr($q,0,80).'%','%'.mb_substr($q,0,80).'%'];}
    $list=rows('SELECT p.id_sanpham,p.tensanpham,p.masp,p.giasp,p.soluong,p.tinhtrang,p.hinhanh,c.tendanhmuc
                FROM tbl_sanpham p JOIN tbl_danhmuc c ON p.id_danhmuc=c.id_danhmuc '.$filter.' ORDER BY p.id_sanpham DESC LIMIT 100',$params);
    echo '<form method="get" class="admin-search"><input type="hidden" name="page" value="products"><input class="field" name="q" maxlength="80" value="'.h($q).'" placeholder="Tìm theo tên hoặc SKU"><button class="button">Tìm kiếm</button></form>';
    admin_table_start(['Sản phẩm','SKU','Danh mục','Giá bán','Tồn kho','Trạng thái','Thao tác']);
    foreach ($list as $p) {
        echo '<tr><td><div class="table-product"><img src="'.h(safe_image($p['hinhanh'])).'" alt=""><strong>'.h($p['tensanpham']).'</strong></div></td>';
        echo '<td>'.h($p['masp']).'</td><td>'.h($p['tendanhmuc']).'</td><td>'.money((int)$p['giasp']).'</td><td>'.(int)$p['soluong'].'</td>';
        echo '<td><span class="badge">'.((int)$p['tinhtrang']===1?'Đang bán':'Đã ẩn').'</span></td><td><a href="'.admin_to('product_form',['id'=>$p['id_sanpham']]).'" class="link-blue">Chỉnh sửa</a></td></tr>';
    }
    admin_table_end();
}
elseif ($page==='product_form') {
    $id=as_int($_GET['id']??0,0,2147483647);
    $p=$id?row('SELECT * FROM tbl_sanpham WHERE id_sanpham=?',[$id]):[];
    if ($id && !$p) throw new DomainException('Không tìm thấy sản phẩm.');
    admin_title($id?'Cập nhật sản phẩm':'Thêm sản phẩm','Chỉnh sửa nội dung và ảnh hiển thị. Tồn kho cập nhật theo số lượng thực.');
    echo '<form class="surface admin-form" method="post" enctype="multipart/form-data">'.csrf_field().'<input type="hidden" name="action" value="save_product"><input type="hidden" name="id" value="'.$id.'">';
    echo '<div class="form-grid"><div><label class="field-group">Tên sản phẩm <input class="field" name="name" maxlength="250" required value="'.h($p['tensanpham']??'').'"></label>';
    echo '<label class="field-group">Mã SKU <input class="field" name="sku" maxlength="100" required value="'.h($p['masp']??'').'"></label>';
    echo '<label class="field-group">Danh mục <select class="field" name="category">';
    foreach (rows('SELECT id_danhmuc,tendanhmuc FROM tbl_danhmuc ORDER BY thutu,id_danhmuc') as $cat) {
        echo '<option value="'.(int)$cat['id_danhmuc'].'"'.((int)($p['id_danhmuc']??0)===(int)$cat['id_danhmuc']?' selected':'').'>'.h($cat['tendanhmuc']).'</option>';
    }
    echo '</select></label><label class="field-group">Mô tả ngắn <textarea class="field" name="summary" maxlength="10000">'.h($p['tomtat']??'').'</textarea></label>';
    echo '<label class="field-group">Nội dung <textarea class="field" name="description" rows="9" maxlength="200000">'.h(strip_tags($p['noidung']??'')).'</textarea></label></div><div>';
    echo '<label class="field-group">Giá bán (VND) <input class="field" type="number" min="0" max="2000000000" name="price" required value="'.h($p['giasp']??'').'"></label>';
    echo '<label class="field-group">Tồn kho <input class="field" type="number" min="0" max="1000000" name="stock" required value="'.h($p['soluong']??'0').'"></label>';
    echo '<label class="field-group">Trạng thái <select class="field" name="visible"><option value="1"'.((int)($p['tinhtrang']??1)===1?' selected':'').'>Đang bán</option><option value="0"'.((int)($p['tinhtrang']??1)===0?' selected':'').'>Tạm ẩn</option></select></label>';
    if (!empty($p['hinhanh'])) echo '<div class="image-preview"><img src="'.h(safe_image($p['hinhanh'])).'" alt="Ảnh hiện tại"></div>';
    echo '<label class="field-group">Ảnh (JPG, PNG, WebP ≤ 3MB) <input class="field" type="file" name="photo" accept="image/jpeg,image/png,image/webp"></label>';
    echo '<button class="button full" type="submit">Lưu sản phẩm</button></div></div></form>';
    if ($id) echo '<form method="post" class="admin-danger">'.csrf_field().'<input type="hidden" name="action" value="hide_product"><input type="hidden" name="id" value="'.$id.'"><button class="button button--danger" data-confirm="Ẩn sản phẩm khỏi website?">Ẩn sản phẩm</button></form>';
}
elseif ($page==='categories') {
    admin_title('Danh mục sản phẩm','Danh sách và thứ tự hiển thị.');
    echo '<div class="admin-grid"><div class="surface"><h2>Thêm danh mục</h2><form method="post">'.csrf_field().'<input type="hidden" name="action" value="save_category"><input type="hidden" name="id" value="0">';
    echo '<label class="field-group">Tên danh mục <input name="name" class="field" required maxlength="100"></label><label class="field-group">Thứ tự <input name="sort" type="number" min="0" max="10000" class="field" value="0"></label><button class="button">Thêm danh mục</button></form></div>';
    echo '<div class="surface"><h2>Danh sách danh mục</h2>';
    foreach (rows('SELECT c.id_danhmuc,c.tendanhmuc,c.thutu,(SELECT COUNT(*) FROM tbl_sanpham p WHERE p.id_danhmuc=c.id_danhmuc) AS product_count FROM tbl_danhmuc c ORDER BY c.thutu,c.id_danhmuc') as $cat) {
        echo '<form method="post" class="category-row">'.csrf_field().'<input type="hidden" name="action" value="save_category"><input type="hidden" name="id" value="'.(int)$cat['id_danhmuc'].'"><input class="field" name="name" maxlength="100" value="'.h($cat['tendanhmuc']).'"><input class="field tiny" type="number" min="0" max="10000" name="sort" value="'.(int)$cat['thutu'].'"><span>'.(int)$cat['product_count'].' SP</span><button class="button button--light">Lưu</button></form>';
    }
    echo '</div></div>';
}
elseif ($page==='orders' || $page==='payments') {
    admin_title($page==='orders'?'Quản lý đơn hàng':'Quản lý thanh toán',
        $page==='orders'?'Xác nhận đơn và theo dõi các bước vận chuyển.':'Xác nhận thanh toán sau khi đối chiếu giao dịch ngân hàng.');
    $status=(string)($_GET['status']??'');
    $params=[];$where='';
    if ($page==='payments') $where=' WHERE payment_method=\'BANK\'';
    elseif ($status!=='' && in_array($status,['PENDING','CONFIRMED','SHIPPING','COMPLETED','CANCELLED'],true)) {
        $where=' WHERE order_status=?';$params=[$status];
    }
    echo '<div class="filter-tabs">';
    foreach ([''=>'Tất cả','PENDING'=>'Chờ xác nhận','CONFIRMED'=>'Đã xác nhận','SHIPPING'=>'Đang giao','COMPLETED'=>'Hoàn thành','CANCELLED'=>'Đã hủy'] as $k=>$label)
        echo '<a class="'.($status===$k?'active':'').'" href="'.admin_to('orders',['status'=>$k]).'">'.h($label).'</a>';
    echo '</div>';
    admin_table_start(['Mã đơn','Khách hàng','Ngày','Tổng tiền','Phương thức','Thanh toán','Trạng thái']);
    foreach (rows('SELECT id,order_code,customer_name,created_at,total_amount,payment_method,payment_status,order_status FROM tbl_orders'.$where.' ORDER BY id DESC LIMIT 100',$params) as $o) {
        echo '<tr><td><a class="link-blue" href="'.admin_to('order',['id'=>$o['id']]).'">'.h($o['order_code']).'</a></td><td>'.h($o['customer_name']).'</td><td>'.h($o['created_at']).'</td><td>'.money((int)$o['total_amount']).'</td><td>'.h($o['payment_method']).'</td><td>'.h($o['payment_status']).'</td><td><span class="badge">'.h($o['order_status']).'</span></td></tr>';
    }
    admin_table_end();
    if ($page==='orders') {
        $legacyOrders=rows('SELECT c.id_cart,c.code_cart,c.cart_status,c.cart_date,c.cart_payment,c.tongtien,u.tenkhachhang,u.dienthoai
                            FROM tbl_cart c LEFT JOIN tbl_dangky u ON u.id_dangky=c.id_khachhang
                            ORDER BY c.id_cart DESC LIMIT 50');
        echo '<section class="surface legacy-orders"><h2>Đơn hàng từ hệ thống trước</h2><p class="hint">Lịch sử trong tbl_cart được giữ nguyên; trạng thái cũ chỉ thể hiện đã xem hay chưa xem.</p>';
        admin_table_start(['Mã đơn','Khách hàng','Ngày đặt','Tổng tiền','Thanh toán','Trạng thái']);
        foreach ($legacyOrders as $old) {
            echo '<tr><td><a class="link-blue" href="'.admin_to('legacy_order',['id'=>$old['id_cart']]).'">'.h($old['code_cart']).'</a></td><td>'.h($old['tenkhachhang']??'Khách hàng cũ').'<br><small>'.h($old['dienthoai']??'').'</small></td><td>'.h($old['cart_date']).'</td><td>'.money((int)$old['tongtien']).'</td><td>'.h($old['cart_payment']).'</td><td>'.((int)$old['cart_status']===1?'Chưa xem':'Đã xem').'</td></tr>';
        }
        admin_table_end();
        if (!$legacyOrders) echo '<p>Không có đơn hàng cũ.</p>';
        echo '</section>';
    }
}
elseif ($page==='order') {
    $id=as_int($_GET['id']??0);
    $order=row('SELECT * FROM tbl_orders WHERE id=?',[$id]);
    if (!$order) throw new DomainException('Đơn hàng không tồn tại.');
    admin_title('Đơn hàng '. $order['order_code'],'Kiểm tra thông tin và cập nhật trạng thái.');
    echo '<div class="admin-grid"><div class="surface"><h2>Thông tin đơn hàng</h2><p>Khách hàng: <strong>'.h($order['customer_name']).'</strong></p><p>SĐT: '.h($order['phone']).'</p><p>Địa chỉ: '.h($order['delivery_address']).'</p><p>Ghi chú: '.h($order['note']).'</p><p>Ngày đặt: '.h($order['created_at']).'</p><hr><h3>Sản phẩm</h3>';
    foreach (rows('SELECT product_name,variant_label,quantity,unit_price FROM tbl_order_items WHERE order_id=?',[$id]) as $item) {
        echo '<div class="summary-line"><span>'.h($item['product_name']).($item['variant_label']!=='BASE'?' · '.h($item['variant_label']):'').' × '.(int)$item['quantity'].'</span><strong>'.money((int)$item['unit_price']*(int)$item['quantity']).'</strong></div>';
    }
    echo '<hr><h2>Tổng tiền: '.money((int)$order['total_amount']).'</h2></div>';
    echo '<div class="surface"><h2>Trạng thái xử lý</h2><p><span class="badge">'.h($order['order_status']).'</span></p><p>Thanh toán: <strong>'.h($order['payment_method']).' / '.h($order['payment_status']).'</strong></p>';
    $transitions=['PENDING'=>['CONFIRMED'=>'Xác nhận','CANCELLED'=>'Hủy đơn'],'CONFIRMED'=>['SHIPPING'=>'Bắt đầu giao','CANCELLED'=>'Hủy đơn'],'SHIPPING'=>['COMPLETED'=>'Đã giao'], 'COMPLETED'=>[], 'CANCELLED'=>[]];
    foreach ($transitions[$order['order_status']]??[] as $status=>$label) {
        if ($status==='CANCELLED' && $order['payment_status']==='PAID') continue;
        echo '<form method="post" class="inline-form">'.csrf_field().'<input type="hidden" name="action" value="order_status"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="status" value="'.h($status).'"><button class="button '.($status==='CANCELLED'?'button--danger':'button--light').'" '.($status==='CANCELLED'?'data-confirm="Hủy đơn và hoàn lại tồn kho?"':'').'>'.h($label).'</button></form>';
    }
    if ($order['payment_method']==='BANK' && $order['payment_status']==='UNPAID' && $order['order_status']!=='CANCELLED')
        echo '<hr><p class="hint">Chỉ bấm sau khi kiểm tra ngân hàng đã nhận tiền.</p><form method="post">'.csrf_field().'<input type="hidden" name="action" value="mark_paid"><input type="hidden" name="id" value="'.$id.'"><button class="button" data-confirm="Đã đối soát và nhận đủ số tiền?">Xác nhận đã nhận chuyển khoản</button></form>';
    echo '</div></div>';
}
elseif ($page==='legacy_orders') {
    admin_title('Đơn hàng cũ','Lịch sử đơn hàng được giữ lại từ cấu trúc database ban đầu.');
    $legacyOrders=rows('SELECT c.id_cart,c.code_cart,c.cart_status,c.cart_date,c.cart_payment,c.tongtien,u.tenkhachhang,u.dienthoai
                        FROM tbl_cart c LEFT JOIN tbl_dangky u ON u.id_dangky=c.id_khachhang
                        ORDER BY c.id_cart DESC LIMIT 100');
    admin_table_start(['Mã đơn','Khách hàng','Ngày đặt','Tổng tiền','Thanh toán','Trạng thái']);
    foreach ($legacyOrders as $old) {
        echo '<tr><td><a class="link-blue" href="'.admin_to('legacy_order',['id'=>$old['id_cart']]).'">'.h($old['code_cart']).'</a></td><td>'.h($old['tenkhachhang']??'Khách hàng cũ').'<br><small>'.h($old['dienthoai']??'').'</small></td><td>'.h($old['cart_date']).'</td><td>'.money((int)$old['tongtien']).'</td><td>'.h($old['cart_payment']).'</td><td>'.((int)$old['cart_status']===1?'Chưa xem':'Đã xem').'</td></tr>';
    }
    admin_table_end();
    if (!$legacyOrders) echo '<div class="surface"><p>Không có đơn hàng cũ.</p></div>';
}
elseif ($page==='legacy_order') {
    $legacyId=as_int($_GET['id']??0);
    $order=row('SELECT c.*,u.tenkhachhang,u.email,u.dienthoai,u.diachi FROM tbl_cart c LEFT JOIN tbl_dangky u ON u.id_dangky=c.id_khachhang WHERE c.id_cart=?',[$legacyId]);
    if (!$order) throw new DomainException('Không tìm thấy đơn hàng cũ.');
    admin_title('Đơn hàng cũ '.$order['code_cart'],'Dữ liệu được giữ từ hệ thống BanHang ban đầu.');
    echo '<div class="admin-grid"><section class="surface"><h2>Thông tin khách hàng</h2><p><strong>'.h($order['tenkhachhang']??'Khách hàng cũ').'</strong></p><p>'.h($order['email']??'').'</p><p>'.h($order['dienthoai']??'').'</p><p>'.h($order['diachi']??'').'</p><p>Ngày đặt: '.h($order['cart_date']).'</p><p>Thanh toán: '.h($order['cart_payment']).'</p><h2>Tổng tiền đã lưu: '.money((int)$order['tongtien']).'</h2></section>';
    echo '<section class="surface"><h2>Sản phẩm trong đơn</h2>';
    foreach (rows('SELECT d.soluongmua,d.dungluong,p.tensanpham FROM tbl_cart_details d LEFT JOIN tbl_sanpham p ON p.id_sanpham=d.id_sanpham WHERE d.code_cart=? ORDER BY d.id_cart_details',[$order['code_cart']]) as $item) {
        echo '<div class="summary-line"><span>'.h($item['tensanpham']??'Sản phẩm không còn trong danh mục').($item['dungluong']!==''?' · '.h($item['dungluong']):'').' × '.(int)$item['soluongmua'].'</span></div>';
    }
    echo '<hr><p>Trạng thái lưu trữ: <strong>'.((int)$order['cart_status']===1?'Chưa xem':'Đã xem').'</strong></p>';
    if ((int)$order['cart_status']===1) {
        echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="legacy_order_seen"><input type="hidden" name="id" value="'.$legacyId.'"><button class="button">Đánh dấu đã xem</button></form>';
    }
    echo '</section></div>';
}
elseif ($page==='customers') {
    admin_title('Khách hàng','Dữ liệu tài khoản và lịch sử đặt hàng.');
    admin_table_start(['Khách hàng','Email','Số điện thoại','Địa chỉ','Số đơn']);
    foreach (rows('SELECT u.id_dangky,u.tenkhachhang,u.email,u.dienthoai,u.diachi,(SELECT COUNT(*) FROM tbl_orders o WHERE o.user_id=u.id_dangky) AS order_count FROM tbl_dangky u ORDER BY u.id_dangky DESC LIMIT 100') as $c)
        echo '<tr><td>'.h($c['tenkhachhang']).'</td><td>'.h($c['email']).'</td><td>'.h($c['dienthoai']).'</td><td>'.h($c['diachi']).'</td><td>'.(int)$c['order_count'].'</td></tr>';
    admin_table_end();
}
elseif ($page==='inventory') {
    admin_title('Tồn kho sản phẩm','Hàng sắp hết và trạng thái hiển thị.');
    admin_table_start(['Sản phẩm','SKU','Tồn kho','Trạng thái','Thao tác']);
    foreach (rows('SELECT id_sanpham,tensanpham,masp,soluong,tinhtrang FROM tbl_sanpham ORDER BY soluong ASC,id_sanpham DESC LIMIT 100') as $p)
        echo '<tr><td>'.h($p['tensanpham']).'</td><td>'.h($p['masp']).'</td><td>'.(int)$p['soluong'].'</td><td><span class="badge">'.((int)$p['soluong']<=3?'Cần nhập hàng':'Ổn định').'</span></td><td><a class="link-blue" href="'.admin_to('product_form',['id'=>$p['id_sanpham']]).'">Cập nhật kho</a></td></tr>';
    admin_table_end();
}
elseif ($page==='revenue') {
    admin_title('Thống kê doanh thu','Báo cáo đơn hàng của cửa hàng.');
    $total=row("SELECT COALESCE(SUM(total_amount),0) AS total,COUNT(*) AS n FROM tbl_orders WHERE order_status<>'CANCELLED'");
    $paid=row("SELECT COALESCE(SUM(total_amount),0) AS total FROM tbl_orders WHERE payment_status='PAID'");
    echo '<div class="stats">';
    stat_card('Doanh thu chưa hủy',money((int)($total['total']??0)));
    stat_card('Giá trị đã xác nhận thanh toán',money((int)($paid['total']??0)));
    stat_card('Đơn chưa hủy',(string)($total['n']??0));
    echo '</div><div class="surface"><h2>Doanh thu theo tháng</h2>';
    admin_table_start(['Tháng','Số đơn','Giá trị']);
    foreach (rows("SELECT DATE_FORMAT(created_at,'%Y-%m') AS month,COUNT(*) AS n,SUM(total_amount) AS total FROM tbl_orders WHERE order_status<>'CANCELLED' GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY month DESC LIMIT 12") as $m)
        echo '<tr><td>'.h($m['month']).'</td><td>'.(int)$m['n'].'</td><td>'.money((int)$m['total']).'</td></tr>';
    admin_table_end();
    echo '</div>';
}
elseif ($page==='recommendations') {
    admin_title('Gợi ý sản phẩm','Hiện gợi ý theo danh mục liên quan và sản phẩm mới; chưa triển khai mô hình machine learning.');
    echo '<div class="stats">';
    $count=row('SELECT COUNT(*) AS n FROM tbl_sanpham WHERE tinhtrang=1');
    stat_card('Sản phẩm có thể gợi ý',(string)($count['n']??0));
    $countCat=row('SELECT COUNT(*) AS n FROM tbl_danhmuc');
    stat_card('Danh mục',(string)($countCat['n']??0));
    echo '</div><div class="surface"><h2>Cơ chế gợi ý hiện tại</h2><p>Tại trang chi tiết sản phẩm: hiển thị sản phẩm cùng danh mục, mới nhất, khác sản phẩm đang xem.</p><p>Trên trang chủ: sản phẩm mới và sản phẩm nổi bật.</p><p>Để sử dụng AI cần dữ liệu hành vi, consent phù hợp và đánh giá offline trước khi triển khai.</p></div>';
}
elseif ($page==='news') {
    admin_title('Quản lý tin tức','Biên tập nội dung công nghệ.', '<a class="button" href="'.admin_to('article_form').'">＋ Viết bài</a>');
    admin_table_start(['Tiêu đề','Ngày đăng','Trạng thái','Thao tác']);
    foreach (rows('SELECT id_baiviet,tenbaiviet,ngaydang,trangthai FROM tbl_tintuc ORDER BY ngaydang DESC LIMIT 100') as $n)
        echo '<tr><td>'.h($n['tenbaiviet']).'</td><td>'.h($n['ngaydang']).'</td><td>'.((int)$n['trangthai']===1?'Đã đăng':'Bản nháp').'</td><td><a class="link-blue" href="'.admin_to('article_form',['id'=>$n['id_baiviet']]).'">Chỉnh sửa</a></td></tr>';
    admin_table_end();
}
elseif ($page==='article_form') {
    $id=as_int($_GET['id']??0,0,2147483647);
    $n=$id?row('SELECT * FROM tbl_tintuc WHERE id_baiviet=?',[$id]):[];
    if ($id && !$n) throw new DomainException('Bài viết không tồn tại.');
    admin_title($id?'Sửa bài viết':'Tạo bài viết','Nội dung hiện được nhập ở dạng văn bản thuần để tránh XSS.');
    echo '<form method="post" enctype="multipart/form-data" class="surface admin-form">'.csrf_field().'<input type="hidden" name="action" value="save_article"><input type="hidden" name="id" value="'.$id.'">';
    echo '<label class="field-group">Tiêu đề <input class="field" name="title" required maxlength="255" value="'.h($n['tenbaiviet']??'').'"></label>';
    echo '<label class="field-group">Tóm tắt <textarea class="field" maxlength="10000" name="summary">'.h(strip_tags($n['tomtat']??'')).'</textarea></label>';
    echo '<label class="field-group">Nội dung <textarea class="field" name="body" rows="12" maxlength="200000">'.h(strip_tags($n['noidung']??'')).'</textarea></label>';
    echo '<label class="field-group">Ảnh đại diện <input class="field" name="photo" type="file" accept="image/jpeg,image/png,image/webp"></label>';
    echo '<label class="field-group">Trạng thái <select class="field" name="visible"><option value="1"'.((int)($n['trangthai']??1)===1?' selected':'').'>Xuất bản</option><option value="0"'.((int)($n['trangthai']??1)===0?' selected':'').'>Bản nháp</option></select></label>';
    echo '<button class="button">Lưu bài viết</button></form>';
}
elseif ($page==='settings') {
    admin_title('Cấu hình cửa hàng','Đổi thông tin cửa hàng và tài khoản VietQR không cần sửa code.');
    echo '<form class="surface admin-form" method="post">'.csrf_field().'<input type="hidden" name="action" value="save_settings"><div class="form-grid"><div>';
    echo '<h2>Thông tin chung</h2><label class="field-group">Tên cửa hàng <input class="field" name="store_name" required maxlength="100" value="'.h(setting('store_name')).'"></label>';
    echo '<label class="field-group">Email hỗ trợ <input class="field" type="email" name="support_email" maxlength="100" value="'.h(setting('support_email')).'"></label></div><div>';
    echo '<h2>VietQR chuyển khoản</h2><p class="hint">Mã ngân hàng (BIN/short code) từ VietQR; không phải mã truy cập bí mật.</p>';
    echo '<label class="field-group">Mã ngân hàng <input class="field" name="bank_id" maxlength="15" placeholder="MB hoặc 970422" value="'.h(setting('bank_id')).'"></label>';
    echo '<label class="field-group">Số tài khoản <input class="field" name="bank_account" maxlength="24" value="'.h(setting('bank_account')).'"></label>';
    echo '<label class="field-group">Tên chủ tài khoản <input class="field" name="bank_name" maxlength="150" value="'.h(setting('bank_name')).'"></label>';
    echo '<p class="hint">Đơn BANK hiển thị QR có số tiền và mã đơn. Quản trị viên phải đối soát chuyển khoản thật.</p></div></div>';
    $contactRow=row('SELECT thongtinlienhe FROM tbl_lienhe WHERE id=1');
    echo '<label class="field-group">Thông tin liên hệ hiển thị trên website <textarea class="field" name="contact_info" rows="5" maxlength="2000">'.h(strip_tags((string)($contactRow['thongtinlienhe']??''))).'</textarea></label>';
    echo '<button class="button" type="submit">Lưu cấu hình</button></form>';
}
admin_page_foot();
} catch (Throwable $e) {
    error_log('[BanHang admin render] '.$e->getMessage());
    http_response_code(500);
    echo '<div class="admin-content"><div class="surface"><h2>Có lỗi hiển thị trang quản trị</h2><p>Kiểm tra log, MySQL và migration.</p></div></div>';
    page_foot(true);
}
