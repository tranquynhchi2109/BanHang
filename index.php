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
$known = ['home','products','product','cart','checkout','login','register','account','password','orders','order','news','article','recommendations','contact','legacy_order'];
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
                $p=row('SELECT p.id_sanpham,p.giasp,p.soluong,c.tendanhmuc FROM tbl_sanpham p JOIN tbl_danhmuc c ON c.id_danhmuc=p.id_danhmuc WHERE p.id_sanpham=? AND p.tinhtrang=1',[$id]);
                if (!$p || (int)$p['soluong']<1) throw new DomainException('Sản phẩm đã hết hàng.');
                $variant=strtoupper(trim((string)($_POST['variant']??'BASE')));
                if ($p['tendanhmuc']==='Điện thoại' && !in_array($variant,['128GB','256GB','512GB','1TB'],true)) {
                    throw new DomainException('Vui lòng chọn dung lượng hợp lệ.');
                }
                if (!is_phone_product($p)) $variant='BASE';
                // Enforce an approved DB price before adding a capacity to cart.
                variant_price($p,$variant);
                $key=cart_key($id,$variant);
                $cart=$_SESSION['cart']??[];
                $already=cart_product_quantity($cart,$id);
                $available=max(0,min(20,(int)$p['soluong'])-$already);
                if ($available<1) throw new DomainException('Số lượng trong giỏ đã đạt giới hạn tồn kho.');
                $cart[$key]=(int)($cart[$key]??0)+min($qty,$available);
                $_SESSION['cart']=$cart;
                notice('Đã thêm sản phẩm vào giỏ hàng.','success');
                redirect_to(link_to('cart'));
            case 'cart_update':
                $cart=$_SESSION['cart']??[];
                $cartKey=(string)($_POST['cart_key']??'');
                [$id,$variant]=parse_cart_key($cartKey);
                $qty=as_int($_POST['qty']??0,0,20);
                if ($id<1 || !array_key_exists($cartKey,$cart)) throw new DomainException('Dòng giỏ hàng không hợp lệ.');
                if ($qty===0) unset($cart[$cartKey]);
                else {
                    $p=row('SELECT soluong FROM tbl_sanpham WHERE id_sanpham=? AND tinhtrang=1',[$id]);
                    if (!$p) throw new DomainException('Sản phẩm không còn bán.');
                    $others=cart_product_quantity($cart,$id,$cartKey);
                    $allowed=max(0,min(20,(int)$p['soluong'])-$others);
                    if ($allowed<1) unset($cart[$cartKey]);
                    else $cart[$cartKey]=min($allowed,$qty);
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
            case 'report_bank_transfer':
                $user=require_customer();
                $code=trim((string)($_POST['order_code']??''));
                if ($code==='') throw new DomainException('Thiếu mã đơn hàng.');
                report_bank_transfer($code,(int)$user['id_dangky'],(string)($_POST['transfer_note']??''));
                notice('Đã nhận thông báo. Cửa hàng sẽ kiểm tra giao dịch MB Bank; đơn vẫn CHƯA được xác nhận thanh toán.','success');
                redirect_to(link_to('order',['code'=>$code]));
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
                    !preg_match('/^[0-9+().\\-\\s]{9,25}$/',$phone) ||
                    mb_strlen($address)<10 || mb_strlen($address)>500 || mb_strlen($note)>500 ||
                    !in_array($payment,['COD','BANK'],true)) throw new DomainException('Thông tin giao hàng không hợp lệ.');
                if ($payment==='BANK' && (setting('bank_id')==='' || setting('bank_account')==='')) {
                    throw new DomainException('Cửa hàng chưa cấu hình nhận chuyển khoản. Hãy chọn COD.');
                }
                $entries=[];
                $requested=[];
                foreach ($cart as $cartKey=>$quantity) {
                    [$id,$variant]=parse_cart_key((string)$cartKey);
                    $quantity=(int)$quantity;
                    if ($id<1 || $quantity<1 || $quantity>20) throw new DomainException('Số lượng đặt hàng không hợp lệ.');
                    $entries[]=['id'=>$id,'variant'=>$variant,'qty'=>$quantity];
                    $requested[$id]=($requested[$id]??0)+$quantity;
                }
                $ids=array_keys($requested);
                sort($ids,SORT_NUMERIC);
                db()->beginTransaction();
                try {
                    $placeholders=implode(',',array_fill(0,count($ids),'?'));
                    $locked=rows('SELECT p.id_sanpham,p.tensanpham,p.giasp,p.soluong,p.tinhtrang,c.tendanhmuc
                                  FROM tbl_sanpham p JOIN tbl_danhmuc c ON c.id_danhmuc=p.id_danhmuc
                                  WHERE p.id_sanpham IN ('.$placeholders.') ORDER BY p.id_sanpham FOR UPDATE',$ids);
                    $productMap=[];
                    foreach ($locked as $product) $productMap[(int)$product['id_sanpham']]=$product;
                    foreach ($entries as &$entry) {
                        $product=$productMap[$entry['id']]??null;
                        if (!$product) throw new DomainException('Sản phẩm không còn tồn tại.');
                        if (is_phone_product($product) && $entry['variant']==='BASE') $entry['variant']='128GB';
                        if (!is_phone_product($product)) $entry['variant']='BASE';
                    }
                    unset($entry);
                    foreach ($requested as $id=>$quantity) {
                        $product=$productMap[$id]??null;
                        if (!$product || (int)$product['tinhtrang']!==1 || (int)$product['soluong']<$quantity) {
                            throw new DomainException('Sản phẩm không đủ tồn kho. Vui lòng cập nhật giỏ hàng.');
                        }
                    }
                    $total=0;
                    $lines=[];
                    foreach ($entries as $entry) {
                        $product=$productMap[$entry['id']];
                        $price=variant_price($product,$entry['variant'],true);
                        if ($price<=0) throw new DomainException('Giá sản phẩm không hợp lệ.');
                        $total+=$price*$entry['qty'];
                        if ($total>1000000000000) throw new DomainException('Giá trị đơn hàng vượt giới hạn.');
                        $lines[]=['id'=>$entry['id'],'name'=>$product['tensanpham'],'variant'=>$entry['variant'],'price'=>$price,'qty'=>$entry['qty']];
                    }
                    $expectedTotal=filter_var($_POST['expected_total']??null,FILTER_VALIDATE_INT);
                    if ($expectedTotal===false || $expectedTotal!==$total) {
                        throw new DomainException('Giá hoặc tổng tiền đã thay đổi. Vui lòng kiểm tra lại giỏ hàng trước khi đặt.');
                    }
                    $code='BH'.strtoupper(bin2hex(random_bytes(6)));
                    run('INSERT INTO tbl_orders(order_code,user_id,customer_name,phone,delivery_address,note,total_amount,payment_method)
                         VALUES (?,?,?,?,?,?,?,?)',[$code,$user['id_dangky'],$name,$phone,$address,$note,$total,$payment]);
                    $orderId=(int)db()->lastInsertId();
                    foreach ($lines as $line) {
                        run('INSERT INTO tbl_order_items(order_id,product_id,product_name,variant_label,unit_price,quantity) VALUES (?,?,?,?,?,?)',
                            [$orderId,$line['id'],$line['name'],$line['variant'],$line['price'],$line['qty']]);
                    }
                    foreach ($requested as $id=>$quantity) {
                        $changed=run('UPDATE tbl_sanpham SET soluong=soluong-? WHERE id_sanpham=? AND soluong>=?',
                            [$quantity,$id,$quantity]);
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
    if ((string)($_POST['action']??'')==='report_bank_transfer') {
        redirect_to(link_to('order',['code'=>trim((string)($_POST['order_code']??''))]));
    }
    $target=match ((string)($_POST['action']??'')) {
        'login'=>'login','register'=>'register','profile'=>'account','password'=>'password','checkout'=>'checkout',
        default=>'cart'
    };
    redirect_to(link_to($target));
} catch (Throwable $e) {
    error_log('[BanHang customer] '.$e->getMessage());
    http_response_code(500);
    $dbSchemaError = $e instanceof PDOException && in_array((string)$e->getCode(), ['42S02', '42S22'], true);
    $title = $dbSchemaError ? 'Cơ sở dữ liệu chưa đồng bộ' : 'Không thể xử lý yêu cầu';
    $explanation = $dbSchemaError
        ? 'Máy chủ đang thiếu bảng hoặc cột cần thiết để xử lý đơn hàng. Quản trị viên cần sao lưu dữ liệu và chạy banhang.sql trong phpMyAdmin, không nhập lại bản SQL mẫu.'
        : 'Có lỗi khi xử lý yêu cầu. Vui lòng kiểm tra nhật ký PHP/Apache trên máy chủ.';
    echo '<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($title) . ' | BanHang</title><link rel="stylesheet" href="' . h(asset_url('css/style.css')) . '"></head><body>';
    echo '<main class="container section"><div class="surface" style="max-width:690px;margin:70px auto">';
    echo '<h1>' . h($title) . '</h1><p>' . h($explanation) . '</p>';
    echo '<p>Giỏ hàng vẫn được giữ trong phiên hiện tại nếu đặt hàng chưa hoàn tất.</p>';
    echo '<a class="button" href="' . h(link_to('checkout')) . '">Quay lại thanh toán</a></div></main></body></html>';
    exit;
}

function select_cart_items(): array
{
    $cart=$_SESSION['cart']??[];
    if (!$cart) return [];
    $entries=[];$ids=[];
    foreach ($cart as $key=>$quantity) {
        [$id,$variant]=parse_cart_key((string)$key);
        if ($id<1 || (int)$quantity<1) continue;
        $entries[]=['key'=>(string)$key,'id'=>$id,'variant'=>$variant,'qty'=>(int)$quantity];
        $ids[$id]=$id;
    }
    if (!$entries) return [];
    $idList=array_values($ids);
    $placeholders=implode(',',array_fill(0,count($idList),'?'));
    $products=rows('SELECT p.id_sanpham,p.tensanpham,p.giasp,p.hinhanh,p.soluong,c.tendanhmuc
                    FROM tbl_sanpham p JOIN tbl_danhmuc c ON c.id_danhmuc=p.id_danhmuc
                    WHERE p.id_sanpham IN ('.$placeholders.')',$idList);
    $map=[];foreach ($products as $product) $map[(int)$product['id_sanpham']]=$product;
    $items=[];
    foreach ($entries as $entry) {
        if (!isset($map[$entry['id']])) continue;
        $item=$map[$entry['id']];
        $variant=$entry['variant'];
        if (is_phone_product($item) && $variant==='BASE') $variant='128GB';
        if (!is_phone_product($item)) $variant='BASE';
        $item['cart_key']=$entry['key'];
        $item['variant']=$variant;
        $item['variant_label']=$variant==='BASE'?'':$variant;
        $item['cart_qty']=$entry['qty'];
        $item['cart_price_error']=false;
        try {
            $item['cart_unit_price']=variant_price($item,$variant);
            $item['cart_total']=$item['cart_qty']*$item['cart_unit_price'];
        } catch (DomainException $e) {
            $item['cart_price_error']=true;
            $item['cart_unit_price']=null;
            $item['cart_total']=0;
        }
        $items[]=$item;
    }
    return $items;
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
