<?php
declare(strict_types=1);
require __DIR__ . '/app.php';

migrate_legacy_session();
$legacyPage = (string)($_GET['quanly'] ?? '');
$legacyRoutes = [
    'danhmucsanpham'=>'products', 'giohang'=>'cart', 'sanpham'=>'product',
    'dangnhap'=>'login', 'dangky'=>'register', 'thanhtoan'=>'checkout',
    'tintuc'=>'news', 'baiviet'=>'article', 'thaydoimatkhau'=>'password',
    'timkiem'=>'products', 'lienhe'=>'contact', 'camon'=>'orders',
    'xulythanhtoan'=>'checkout'
];
$page = (string)($_GET['page'] ?? ($legacyRoutes[$legacyPage] ?? 'home'));
if ($legacyPage === 'danhmucsanpham' && isset($_GET['id'])) $_GET['category_id'] = (int)$_GET['id'];
if ($legacyPage === 'timkiem') $_GET['q'] = (string)($_POST['tukhoa'] ?? $_GET['q'] ?? '');
$known = ['home','products','product','cart','checkout','login','register','account','password','orders','order','news','article','recommendations','contact'];
if (!in_array($page,$known,true)) $page='home';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['timkiem'])) {
    redirect_to(link_to('products', ['q' => trim((string)($_POST['tukhoa'] ?? ''))]));
}
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        ensure_post();
        $action = (string)($_POST['action'] ?? '');
        switch ($action) {
            case 'add_cart':
                $id=as_int($_POST['product_id'] ?? 0);
                $qty=as_int($_POST['qty'] ?? 1,1,20);
                $p=row('SELECT id_sanpham,soluong FROM tbl_sanpham WHERE id_sanpham=? AND tinhtrang=1',[$id]);
                if (!$p || (int)$p['soluong']<1) throw new DomainException('Sản phẩm đã hết hàng.');
                $cart=$_SESSION['cart']??[];
                $cart[$id]=min((int)$p['soluong'],20,(int)($cart[$id]??0)+$qty);
                $_SESSION['cart']=$cart;
                notice('Đã thêm sản phẩm vào giỏ hàng.','success');
                redirect_to(link_to('cart'));
            case 'cart_update':
                $id=as_int($_POST['product_id']??0);
                $qty=as_int($_POST['qty']??0,0,20);
                $cart=$_SESSION['cart']??[];
                if ($qty===0) unset($cart[$id]);
                else {
                    $p=row('SELECT soluong FROM tbl_sanpham WHERE id_sanpham=? AND tinhtrang=1',[$id]);
                    if (!$p) throw new DomainException('Sản phẩm không còn bán.');
                    $cart[$id]=min((int)$p['soluong'],$qty);
                    if ($cart[$id]===0) unset($cart[$id]);
                }
                $_SESSION['cart']=$cart;
                redirect_to(link_to('cart'));
            case 'login':
                $email=mb_strtolower(trim((string)($_POST['email']??'')),'UTF-8');
                $password=(string)($_POST['password']??'');
                if (!filter_var($email,FILTER_VALIDATE_EMAIL) || $password==='') throw new DomainException('Vui lòng nhập email và mật khẩu hợp lệ.');
                if (!login_customer($email,$password)) throw new DomainException('Email hoặc mật khẩu không đúng.');
                notice('Đăng nhập thành công.','success');
                redirect_to(link_to('account'));
            case 'register':
                $name=trim((string)($_POST['name']??''));
                $email=mb_strtolower(trim((string)($_POST['email']??'')),'UTF-8');
                $phone=trim((string)($_POST['phone']??''));
                $password=(string)($_POST['password']??'');
                if (mb_strlen($name)<2 || mb_strlen($name)>160 || !filter_var($email,FILTER_VALIDATE_EMAIL) ||
                    !preg_match('/^[0-9+().\-\s]{9,25}$/',$phone) || strlen($password)<12 || strlen($password)>72) {
                    throw new DomainException('Hãy nhập họ tên, email, số điện thoại hợp lệ và mật khẩu từ 12–72 ký tự.');
                }
                if (row('SELECT id_dangky FROM tbl_dangky WHERE email=?',[$email])) throw new DomainException('Email này đã được đăng ký.');
                run('INSERT INTO tbl_dangky(tenkhachhang,email,diachi,matkhau,dienthoai) VALUES (?,?,?,?,?)',
                    [$name,$email,'',password_hash($password,PASSWORD_DEFAULT),$phone]);
                session_regenerate_id(true);
                $_SESSION['customer_id']=(int)db()->lastInsertId();
                $_SESSION['csrf']=bin2hex(random_bytes(32));
                notice('Tạo tài khoản thành công!','success');
                redirect_to(link_to('account'));
            case 'logout':
                sign_out('customer');
                notice('Bạn đã đăng xuất.','success');
                redirect_to(link_to('home'));
            case 'profile':
                $user=require_customer();
                $name=trim((string)($_POST['name']??''));
                $phone=trim((string)($_POST['phone']??''));
                $address=trim((string)($_POST['address']??''));
                if (mb_strlen($name)<2 || mb_strlen($name)>160 ||
                    !preg_match('/^[0-9+().\-\s]{9,25}$/',$phone) || mb_strlen($address)>200) {
                    throw new DomainException('Thông tin cá nhân không hợp lệ.');
                }
                run('UPDATE tbl_dangky SET tenkhachhang=?,dienthoai=?,diachi=? WHERE id_dangky=?',
                    [$name,$phone,$address,$user['id_dangky']]);
                notice('Đã lưu thông tin tài khoản.','success');
                redirect_to(link_to('account'));
            case 'password':
                $user=require_customer();
                $old=(string)($_POST['old_password']??'');
                $new=(string)($_POST['new_password']??'');
                $confirm=(string)($_POST['confirm_password']??'');
                if (strlen($new)<12 || strlen($new)>72 || $new!==$confirm) throw new DomainException('Mật khẩu mới phải dài 12–72 ký tự và nhập lại trùng khớp.');
                $record=row('SELECT matkhau FROM tbl_dangky WHERE id_dangky=?',[$user['id_dangky']]);
                if (!$record || !verify_legacy_password($old,$record['matkhau'])) throw new DomainException('Mật khẩu hiện tại không đúng.');
                run('UPDATE tbl_dangky SET matkhau=? WHERE id_dangky=?',[password_hash($new,PASSWORD_DEFAULT),$user['id_dangky']]);
                session_regenerate_id(true);
                $_SESSION['csrf']=bin2hex(random_bytes(32));
                notice('Đổi mật khẩu thành công.','success');
                redirect_to(link_to('account'));
            case 'checkout':
                $user=require_customer();
                $cart=$_SESSION['cart']??[];
                if (!$cart) throw new DomainException('Giỏ hàng đang trống.');
                $name=trim((string)($_POST['name']??''));
                $phone=trim((string)($_POST['phone']??''));
                $address=trim((string)($_POST['address']??''));
                $note=trim((string)($_POST['note']??''));
                $payment=(string)($_POST['payment']??'COD');
                if (mb_strlen($name)<2 || mb_strlen($name)>160 ||
                    !preg_match('/^[0-9+().\-\s]{9,25}$/',$phone) ||
                    mb_strlen($address)<10 || mb_strlen($address)>500 || mb_strlen($note)>500 ||
                    !in_array($payment,['COD','BANK'],true)) throw new DomainException('Thông tin giao hàng không hợp lệ.');
                if ($payment==='BANK' && (setting('bank_id')==='' || setting('bank_account')==='')) {
                    throw new DomainException('Cửa hàng chưa cấu hình nhận chuyển khoản. Hãy chọn COD.');
                }
                ksort($cart,SORT_NUMERIC);
                db()->beginTransaction();
                try {
                    $total=0; $lines=[];
                    foreach ($cart as $id=>$qty) {
                        $id=(int)$id; $qty=(int)$qty;
                        if ($qty<1 || $qty>20) throw new DomainException('Số lượng đặt hàng không hợp lệ.');
                        $p=row('SELECT id_sanpham,tensanpham,giasp,soluong,tinhtrang FROM tbl_sanpham WHERE id_sanpham=? FOR UPDATE',[$id]);
                        if (!$p || (int)$p['tinhtrang']!==1 || (int)$p['soluong']<$qty) {
                            throw new DomainException('Sản phẩm không đủ tồn kho. Vui lòng cập nhật giỏ hàng.');
                        }
                        $price=(int)$p['giasp'];
                        if ($price<=0) throw new DomainException('Giá sản phẩm không hợp lệ.');
                        $total+=$price*$qty;
                        if ($total>1000000000000) throw new DomainException('Giá trị đơn hàng vượt giới hạn.');
                        $lines[]=['id'=>$id,'name'=>$p['tensanpham'],'price'=>$price,'qty'=>$qty];
                    }
                    $code='BH'.strtoupper(bin2hex(random_bytes(6)));
                    run('INSERT INTO v2_orders(order_code,user_id,customer_name,phone,delivery_address,note,total_amount,payment_method)
                         VALUES (?,?,?,?,?,?,?,?)',[$code,$user['id_dangky'],$name,$phone,$address,$note,$total,$payment]);
                    $orderId=(int)db()->lastInsertId();
                    foreach ($lines as $line) {
                        run('INSERT INTO v2_order_items(order_id,product_id,product_name,unit_price,quantity) VALUES (?,?,?,?,?)',
                            [$orderId,$line['id'],$line['name'],$line['price'],$line['qty']]);
                        $changed=run('UPDATE tbl_sanpham SET soluong=soluong-? WHERE id_sanpham=? AND soluong>=?',
                            [$line['qty'],$line['id'],$line['qty']]);
                        if ($changed!==1) throw new RuntimeException('Không thể cập nhật kho.');
                    }
                    db()->commit();
                } catch (Throwable $e) {
                    if (db()->inTransaction()) db()->rollBack();
                    throw $e;
                }
                unset($_SESSION['cart']);
                notice('Đơn hàng đã được ghi nhận.','success');
                redirect_to(link_to('order',['code'=>$code]));
            default:
                http_response_code(400);
                throw new DomainException('Yêu cầu không được hỗ trợ.');
        }
    }
} catch (DomainException $e) {
    notice($e->getMessage(),'danger');
    $target=match ((string)($_POST['action']??'')) {
        'login'=>'login','register'=>'register','profile'=>'account','password'=>'password','checkout'=>'checkout',
        default=>'cart'
    };
    redirect_to(link_to($target));
} catch (Throwable $e) {
    error_log('[BanHang v2 customer] '.$e->getMessage());
    http_response_code(500);
    exit('Có lỗi hệ thống. Vui lòng kiểm tra kết nối cơ sở dữ liệu và tập tin nâng cấp.');
}

function select_cart_items(): array
{
    $cart=$_SESSION['cart']??[];
    if (!$cart) return [];
    $ids=array_keys($cart);
    $placeholders=implode(',',array_fill(0,count($ids),'?'));
    $products=rows('SELECT p.id_sanpham,p.tensanpham,p.giasp,p.hinhanh,p.soluong,c.tendanhmuc FROM tbl_sanpham p JOIN tbl_danhmuc c ON c.id_danhmuc=p.id_danhmuc WHERE p.id_sanpham IN ('.$placeholders.')',array_map('intval',$ids));
    foreach ($products as &$p) {
        $p['cart_qty']=(int)($cart[$p['id_sanpham']]??0);
        $p['cart_total']=$p['cart_qty']*(int)$p['giasp'];
    }
    unset($p);
    return $products;
}
function product_grid(array $products): void
{
    if (!$products) { echo '<div class="empty-state"><h3>Chưa có sản phẩm phù hợp</h3><p>Hãy thử từ khóa hoặc bộ lọc khác.</p></div>'; return; }
    echo '<div class="products-grid">';
    foreach ($products as $p) product_card($p);
    echo '</div>';
}
function section_header(string $title,string $subtitle='',string $link=''): void
{
    echo '<div class="section-heading"><div><h2>'.h($title).'</h2><p>'.h($subtitle).'</p></div>';
    if ($link!=='') echo '<a class="link-blue" href="'.h($link).'">Xem tất cả ↗</a>';
    echo '</div>';
}
function input_field(string $name,string $label,string $value='',string $type='text',string $extra=''): void
{
    echo '<label class="field-group"><span>'.h($label).'</span><input class="field" type="'.h($type).'" name="'.h($name).'" value="'.h($value).'" '. $extra . '></label>';
}
function page_title(string $title,string $desc=''): void
{
    echo '<section class="page-title"><div class="container"><p class="eyebrow">BANHANG TECH STORE</p><h1>'.h($title).'</h1>';
    if ($desc!=='') echo '<p>'.h($desc).'</p>';
    echo '</div></section>';
}


try {
    include __DIR__ . '/pages/header.php';
    include __DIR__ . '/pages/main.php';
    include __DIR__ . '/pages/footer.php';
} catch (Throwable $e) {
    error_log('[BanHang storefront shell] ' . $e->getMessage());
    http_response_code(500);
    echo '<main class="container section"><div class="surface"><h1>Không thể tải website.</h1><p>Vui lòng kiểm tra cấu hình máy chủ và cơ sở dữ liệu.</p></div></main>';
}
