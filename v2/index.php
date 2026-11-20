<?php
declare(strict_types=1);
require __DIR__ . '/app.php';

$page = (string)($_GET['page'] ?? 'home');
$known = ['home','products','product','cart','checkout','login','register','account','password','orders','order','news','article','recommendations'];
if (!in_array($page,$known,true)) $page='home';

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
    $products=rows('SELECT p.*,c.tendanhmuc FROM tbl_sanpham p JOIN tbl_danhmuc c ON c.id_danhmuc=p.id_danhmuc WHERE p.id_sanpham IN ('.$placeholders.')',array_map('intval',$ids));
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
page_head(ucfirst($page));
echo '<main>';
echo '<div class="container flash">'.(notices_html()??'').'</div>';

if ($page==='home') {
    $featured=rows('SELECT p.*,c.tendanhmuc FROM tbl_sanpham p JOIN tbl_danhmuc c ON c.id_danhmuc=p.id_danhmuc WHERE p.tinhtrang=1 ORDER BY p.id_sanpham DESC LIMIT 8');
    $categories=rows('SELECT id_danhmuc,tendanhmuc FROM tbl_danhmuc ORDER BY thutu ASC,id_danhmuc ASC LIMIT 8');
    echo '<div class="container">';
    echo '<section class="hero-grid"><div class="hero-primary"><div class="hero-copy"><span class="hero-label">NEW DROP · TECH EDITION</span><h1>Thiết bị mới.<br>Cảm hứng mới.</h1><p>Nâng tầm từng khoảnh khắc với những thiết bị công nghệ được chọn lọc.</p><a class="button button--mint" href="'.link_to('products').'">Khám phá sản phẩm ↗</a></div>';
    echo '<div class="hero-visual"><div class="hero-device">B</div><div class="hero-glow"></div></div></div>';
    echo '<div class="hero-side"><a class="promo promo--blue" href="'.link_to('products',['category'=>'laptop']).'"><span class="eyebrow">DÀNH CHO SÁNG TẠO</span><h3>Laptop<br>hiệu năng cao</h3><span>Khám phá ngay ↗</span><span class="promo-symbol">⌘</span></a>';
    echo '<a class="promo promo--peach" href="'.link_to('products',['category'=>'audio']).'"><span class="eyebrow">ÂM THANH ĐỈNH CAO</span><h3>Nghe chất.<br>Sống khác.</h3><span>Khám phá ngay ↗</span><span class="promo-symbol">♫</span></a></div></section>';
    echo '<div class="benefits"><div>◇ <strong>Mua sắm an tâm</strong><small>Chính sách rõ ràng</small></div><div>♧ <strong>Giao hàng linh hoạt</strong><small>Theo dõi đơn tiện lợi</small></div><div>▣ <strong>Thanh toán thuận tiện</strong><small>COD và VietQR</small></div><div>☏ <strong>Hỗ trợ khách hàng</strong><small>Chăm sóc khi cần</small></div></div>';
    echo '<section class="section">';
    section_header('Khám phá theo danh mục','Tìm chính xác thiết bị phù hợp với bạn.');
    echo '<div class="categories">';
    foreach ($categories as $i=>$cat) {
        $symbol=['▯','▱','♫','◷','◇','⚡','◎','♧'][$i%8];
        echo '<a href="'.link_to('products',['category_id'=>(int)$cat['id_danhmuc']]).'"><span>'.h($symbol).'</span><strong>'.h($cat['tendanhmuc']).'</strong><small>Khám phá ↗</small></a>';
    }
    echo '</div></section><section class="section">';
    section_header('Được yêu thích nhất','Những sản phẩm công nghệ nổi bật đang có tại cửa hàng.',link_to('products'));
    product_grid(array_slice($featured,0,4));
    echo '</section><section class="editorial"><div><span class="hero-label">BỘ SƯU TẬP ĐẶC BIỆT</span><h2>Tìm chất riêng<br>trong từng kết nối.</h2><p>Phụ kiện thông minh. Thiết kế tinh tế.<br>Tối ưu cho mọi nhịp sống.</p><a href="'.link_to('products',['category'=>'accessories']).'" class="button button--mint">Khám phá bộ sưu tập ↗</a></div><div class="editorial-art">♫</div></section>';
    echo '<section class="section">';
    section_header('Dành riêng cho bạn','Dựa trên danh mục đang được quan tâm.',link_to('recommendations'));
    product_grid(array_slice(array_reverse($featured),0,4));
    echo '</section><section class="newsletter"><div><h2>Công nghệ tốt hơn, mỗi ngày.</h2><p>Khám phá sản phẩm mới và tin tức công nghệ hữu ích.</p></div><a href="'.link_to('news').'" class="button">Đọc tin công nghệ ↗</a></section>';
    echo '</div>';
}
elseif ($page==='products' || $page==='recommendations') {
    $q=trim((string)($_GET['q']??''));
    $category=(string)($_GET['category']??'');
    $categoryId=filter_var($_GET['category_id']??null,FILTER_VALIDATE_INT);
    $sort=(string)($_GET['sort']??'latest');
    $sortSql=match($sort){'price_asc'=>'p.giasp ASC','price_desc'=>'p.giasp DESC','name'=>'p.tensanpham ASC',default=>'p.id_sanpham DESC'};
    $currentPage=max(1,min(999,(int)($_GET['p']??1)));
    $limit=12;$offset=($currentPage-1)*$limit;
    $where=['p.tinhtrang=1']; $params=[];
    if ($q!=='') { $where[]='(p.tensanpham LIKE ? OR p.masp LIKE ?)';$params[]='%'.mb_substr($q,0,120).'%';$params[]='%'.mb_substr($q,0,120).'%';}
    if ($categoryId) {$where[]='p.id_danhmuc=?';$params[]=$categoryId;}
    elseif ($category!=='') {
        $patterns=['phone'=>'%điện thoại%','laptop'=>'%laptop%','audio'=>'%tai nghe%','watch'=>'%đồng hồ%','accessories'=>'%phụ kiện%'];
        if (isset($patterns[$category])) {$where[]='LOWER(c.tendanhmuc) LIKE ?';$params[]=$patterns[$category];}
    }
    $clause=implode(' AND ',$where);
    $totalRow=row('SELECT COUNT(*) AS n FROM tbl_sanpham p JOIN tbl_danhmuc c ON c.id_danhmuc=p.id_danhmuc WHERE '.$clause,$params);
    $products=rows('SELECT p.*,c.tendanhmuc FROM tbl_sanpham p JOIN tbl_danhmuc c ON c.id_danhmuc=p.id_danhmuc WHERE '.$clause.' ORDER BY '.$sortSql.' LIMIT '.$limit.' OFFSET '.$offset,$params);
    page_title($page==='recommendations'?'Gợi ý cho bạn':'Khám phá sản phẩm','Chọn theo thương hiệu, giá cả và nhu cầu của bạn.');
    echo '<div class="container section"><div class="catalog-layout"><aside class="sidebar-filter"><h3>Danh mục sản phẩm</h3><a href="'.link_to('products').'">Tất cả sản phẩm</a>';
    foreach (rows('SELECT id_danhmuc,tendanhmuc FROM tbl_danhmuc ORDER BY thutu,id_danhmuc') as $cat) {
        echo '<a href="'.link_to('products',['category_id'=>(int)$cat['id_danhmuc']]).'">'.h($cat['tendanhmuc']).'</a>';
    }
    echo '<hr><p>Giá và tồn kho được lấy trực tiếp từ cơ sở dữ liệu.</p></aside><section class="catalog-main"><div class="catalog-toolbar"><span><strong>'.(int)($totalRow['n']??0).'</strong> sản phẩm</span><form method="get"><input type="hidden" name="page" value="products"><input type="hidden" name="q" value="'.h($q).'">';
    if ($categoryId) echo '<input type="hidden" name="category_id" value="'.(int)$categoryId.'">';
    echo '<select class="field" name="sort" onchange="this.form.submit()"><option value="latest"'.selected($sort,'latest').'>Mới nhất</option><option value="price_asc"'.selected($sort,'price_asc').'>Giá tăng dần</option><option value="price_desc"'.selected($sort,'price_desc').'>Giá giảm dần</option><option value="name"'.selected($sort,'name').'>Tên A–Z</option></select></form></div>';
    product_grid($products);
    $pages=(int)ceil((int)($totalRow['n']??0)/$limit);
    echo '<nav class="pagination">';
    for ($p=1;$p<=min($pages,20);$p++) {
        echo '<a class="'.($p===$currentPage?'active':'').'" href="'.link_to('products',array_filter(['q'=>$q,'category'=>$category,'category_id'=>$categoryId,'sort'=>$sort,'p'=>$p],static fn($x)=>$x!==''&&$x!==false&&$x!==null)).'">'.$p.'</a>';
    }
    echo '</nav></section></div></div>';
}
elseif ($page==='product') {
    $id=as_int($_GET['id']??0);
    $product=row('SELECT p.*,c.tendanhmuc FROM tbl_sanpham p JOIN tbl_danhmuc c ON p.id_danhmuc=c.id_danhmuc WHERE p.id_sanpham=? AND p.tinhtrang=1',[$id]);
    if (!$product) {http_response_code(404);page_title('Không tìm thấy sản phẩm');}
    else {
        echo '<div class="container section"><p class="breadcrumb"><a href="index.php">Trang chủ</a> / '.h($product['tendanhmuc']).' / '.h($product['tensanpham']).'</p>';
        echo '<div class="product-detail"><div class="product-detail__photo"><img src="'.h(safe_image($product['hinhanh'])).'" alt="'.h($product['tensanpham']).'"></div><div class="product-detail__info"><span class="badge">CHÍNH HÃNG</span>';
        echo '<h1>'.h($product['tensanpham']).'</h1><p class="rating">★ 4.9 <span>· Bảo hành theo quy định cửa hàng</span></p><p class="detail-price">'.money((int)$product['giasp']).'</p>';
        echo '<p>'.h($product['tomtat']).'</p><p><strong>Tình trạng:</strong> '.((int)$product['soluong']>0?'Còn '.(int)$product['soluong'].' sản phẩm':'Tạm hết hàng').'</p>';
        if ((int)$product['soluong']>0) {
            echo '<form action="index.php" method="post" class="purchase-form">'.csrf_field().'<input type="hidden" name="action" value="add_cart"><input type="hidden" name="product_id" value="'.$id.'">';
            echo '<label>Số lượng <input class="field qty" name="qty" type="number" min="1" max="'.min((int)$product['soluong'],20).'" value="1" required></label><button class="button">＋ Thêm vào giỏ hàng</button></form>';
        }
        echo '<div class="product-services">✓ Thanh toán bảo mật &nbsp; ✓ Có theo dõi đơn &nbsp; ✓ Tư vấn hỗ trợ</div></div></div>';
        echo '<section class="section"><h2>Mô tả sản phẩm</h2><div class="surface prose">'.nl2br(h(strip_tags((string)$product['noidung']))).'</div></section>';
        $recommended=rows('SELECT p.*,c.tendanhmuc FROM tbl_sanpham p JOIN tbl_danhmuc c ON c.id_danhmuc=p.id_danhmuc WHERE p.id_danhmuc=? AND p.id_sanpham<>? AND p.tinhtrang=1 ORDER BY p.id_sanpham DESC LIMIT 4',[$product['id_danhmuc'],$id]);
        section_header('Sản phẩm liên quan','Bạn có thể quan tâm');product_grid($recommended);
        echo '</div>';
    }
}
elseif ($page==='cart' || $page==='checkout') {
    $items=select_cart_items();$total=array_sum(array_column($items,'cart_total'));
    if ($page==='checkout' && !$items) redirect_to(link_to('cart'));
    if ($page==='checkout') $user=require_customer();
    page_title($page==='cart'?'Giỏ hàng của bạn':'Thanh toán đơn hàng','Mọi thông tin được xác minh lại trên máy chủ.');
    echo '<div class="container section"><div class="checkout-layout"><section class="surface cart-items">';
    if (!$items) echo '<div class="empty-state"><h2>Giỏ hàng trống</h2><p>Thêm sản phẩm yêu thích và quay lại nhé.</p><a class="button" href="'.link_to('products').'">Xem sản phẩm</a></div>';
    elseif ($page==='cart') {
        echo '<h2>Sản phẩm đã chọn</h2>';
        foreach ($items as $item) {
            $id=(int)$item['id_sanpham'];
            echo '<div class="cart-item"><img src="'.h(safe_image($item['hinhanh'])).'" alt=""><div><strong>'.h($item['tensanpham']).'</strong><p>'.money((int)$item['giasp']).'</p></div>';
            echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="cart_update"><input type="hidden" name="product_id" value="'.$id.'"><input class="field qty" name="qty" type="number" min="0" max="20" value="'.(int)$item['cart_qty'].'"><button class="button button--light">Cập nhật</button></form></div>';
        }
    } else {
        echo '<h2>Thông tin giao hàng</h2><form action="index.php" method="post" id="checkout-form">'.csrf_field().'<input type="hidden" name="action" value="checkout">';
        input_field('name','Họ và tên',$user['tenkhachhang'],'text','required maxlength="160"');
        input_field('phone','Số điện thoại',$user['dienthoai'],'tel','required maxlength="25"');
        echo '<label class="field-group"><span>Địa chỉ nhận hàng</span><textarea class="field" name="address" minlength="10" maxlength="500" required>'.h($user['diachi']).'</textarea></label>';
        echo '<label class="field-group"><span>Ghi chú giao hàng (không bắt buộc)</span><textarea class="field" maxlength="500" name="note"></textarea></label>';
        echo '<h3>Phương thức thanh toán</h3><label class="radio-option"><input type="radio" name="payment" value="COD" checked> Thanh toán khi nhận hàng (COD)</label>';
        echo '<label class="radio-option"><input type="radio" name="payment" value="BANK"> Chuyển khoản ngân hàng / VietQR</label>';
        echo '<p class="hint">VietQR hiển thị thông tin chuyển khoản, không tự xác nhận tiền đã nhận.</p></form>';
    }
    echo '</section><aside class="surface order-summary"><h2>Tóm tắt đơn hàng</h2>';
    foreach ($items as $item) echo '<div class="summary-line"><span>'.h($item['tensanpham']).' × '.(int)$item['cart_qty'].'</span><strong>'.money((int)$item['cart_total']).'</strong></div>';
    echo '<hr><div class="summary-total"><span>Tổng tiền</span><strong>'.money((int)$total).'</strong></div>';
    if ($items) {
        if ($page==='checkout') echo '<button form="checkout-form" class="button full">Xác nhận đặt hàng →</button>';
        else echo '<a class="button full" href="'.link_to('checkout').'">Tiến hành thanh toán →</a>';
    }
    echo '</aside></div></div>';
}
elseif ($page==='login' || $page==='register') {
    $register=$page==='register';
    page_title($register?'Tạo tài khoản':'Chào mừng trở lại','Tài khoản BanHang giúp bạn theo dõi mọi đơn hàng.');
    echo '<div class="container section"><div class="auth-box surface"><h2>'.($register?'Đăng ký khách hàng':'Đăng nhập khách hàng').'</h2><form action="index.php" method="post">'.csrf_field().'<input type="hidden" name="action" value="'.$page.'">';
    if ($register) input_field('name','Họ và tên','','text','required minlength="2" maxlength="160"');
    input_field('email','Email','','email','required maxlength="100" autocomplete="email"');
    if ($register) input_field('phone','Số điện thoại','','tel','required maxlength="25"');
    input_field('password','Mật khẩu','','password','required '.($register?'minlength="12" maxlength="72"':'').' autocomplete="'.($register?'new-password':'current-password').'"');
    echo '<button class="button full" type="submit">'.($register?'Tạo tài khoản':'Đăng nhập').'</button></form>';
    echo '<p>'.($register?'Đã có tài khoản? <a href="'.link_to('login').'">Đăng nhập</a>':'Chưa có tài khoản? <a href="'.link_to('register').'">Đăng ký ngay</a>').'</p></div></div>';
}
elseif ($page==='account' || $page==='password') {
    $user=require_customer();
    page_title('Tài khoản của tôi','Quản lý thông tin giao hàng và bảo mật.');
    echo '<div class="container section account-layout"><aside class="surface account-side"><h2>'.h($user['tenkhachhang']).'</h2><p>'.h($user['email']).'</p><a href="'.link_to('account').'">Thông tin cá nhân</a><a href="'.link_to('orders').'">Đơn hàng của tôi</a><a href="'.link_to('password').'">Đổi mật khẩu</a>';
    echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="logout"><button class="button button--light" type="submit">Đăng xuất</button></form></aside>';
    echo '<section class="surface"><h2>'.($page==='password'?'Đổi mật khẩu':'Thông tin cá nhân').'</h2><form method="post">'.csrf_field().'<input type="hidden" name="action" value="'.($page==='password'?'password':'profile').'">';
    if ($page==='password') {
        input_field('old_password','Mật khẩu hiện tại','','password','required');
        input_field('new_password','Mật khẩu mới (12–72 ký tự)','','password','minlength="12" maxlength="72" required');
        input_field('confirm_password','Xác nhận mật khẩu mới','','password','minlength="12" maxlength="72" required');
    } else {
        input_field('name','Họ và tên',$user['tenkhachhang'],'text','maxlength="160" required');
        input_field('email','Email',$user['email'],'email','disabled');
        input_field('phone','Số điện thoại',$user['dienthoai'],'tel','maxlength="25" required');
        echo '<label class="field-group"><span>Địa chỉ</span><textarea class="field" maxlength="200" name="address">'.h($user['diachi']).'</textarea></label>';
    }
    echo '<button class="button">Lưu thay đổi</button></form></section></div>';
}
elseif ($page==='orders' || $page==='order') {
    $user=require_customer();
    page_title($page==='orders'?'Đơn hàng của tôi':'Theo dõi đơn hàng','Xem tiến độ giao hàng và phương thức thanh toán.');
    echo '<div class="container section">';
    if ($page==='orders') {
        $orders=rows('SELECT id,order_code,total_amount,order_status,payment_method,payment_status,created_at FROM v2_orders WHERE user_id=? ORDER BY id DESC LIMIT 50',[$user['id_dangky']]);
        echo '<div class="surface"><h2>Lịch sử đặt hàng</h2><div class="table-scroll"><table><thead><tr><th>Mã đơn</th><th>Ngày đặt</th><th>Số tiền</th><th>Trạng thái</th><th>Thanh toán</th></tr></thead><tbody>';
        foreach ($orders as $o) echo '<tr><td><a class="link-blue" href="'.link_to('order',['code'=>$o['order_code']]).'">'.h($o['order_code']).'</a></td><td>'.h($o['created_at']).'</td><td>'.money((int)$o['total_amount']).'</td><td>'.h($o['order_status']).'</td><td>'.h($o['payment_status']).'</td></tr>';
        echo '</tbody></table></div>';
        if (!$orders) echo '<p>Chưa có đơn hàng.</p>';
        echo '</div>';
    } else {
        $code=trim((string)($_GET['code']??''));
        $order=row('SELECT * FROM v2_orders WHERE order_code=? AND user_id=?',[$code,$user['id_dangky']]);
        if (!$order) { http_response_code(404);echo '<div class="surface">Không tìm thấy đơn hàng của bạn.</div>'; }
        else {
            echo '<div class="checkout-layout"><section class="surface"><span class="badge">'.h($order['order_status']).'</span><h2>Đơn hàng '.h($order['order_code']).'</h2><p>Đặt lúc '.h($order['created_at']).'</p><div class="timeline"><span>Đã đặt</span><span>Đã xác nhận</span><span>Đang giao</span><span>Hoàn thành</span></div><h3>Sản phẩm</h3>';
            foreach (rows('SELECT product_name,quantity,unit_price FROM v2_order_items WHERE order_id=?',[$order['id']]) as $item) {
                echo '<div class="summary-line"><span>'.h($item['product_name']).' × '.(int)$item['quantity'].'</span><strong>'.money((int)$item['unit_price']*(int)$item['quantity']).'</strong></div>';
            }
            echo '<hr><h3>Thông tin nhận hàng</h3><p>'.h($order['customer_name']).' · '.h($order['phone']).'</p><p>'.h($order['delivery_address']).'</p></section>';
            echo '<aside class="surface order-summary"><h2>Thanh toán</h2><p>Phương thức: <strong>'.h($order['payment_method']).'</strong></p><p>Trạng thái: <strong>'.h($order['payment_status']).'</strong></p><p class="detail-price">'.money((int)$order['total_amount']).'</p>';
            if ($order['payment_method']==='BANK' && $order['payment_status']==='UNPAID' && $order['order_status']!=='CANCELLED') {
                $qr=qr_link($order);
                if ($qr) {
                    echo '<img class="payment-qr" src="'.h($qr).'" alt="Mã QR chuyển khoản theo đơn hàng"><p>Chuyển khoản đúng số tiền và nội dung <strong>BANHANG '.h($order['order_code']).'</strong>.</p>';
                    echo '<p class="hint">Cửa hàng sẽ kiểm tra giao dịch và xác nhận thủ công. Quét QR không đồng nghĩa đơn đã thanh toán.</p>';
                } else echo '<p>Chưa cấu hình tài khoản nhận chuyển khoản.</p>';
            }
            echo '</aside></div>';
        }
    }
    echo '</div>';
}
elseif ($page==='news' || $page==='article') {
    if ($page==='news') {
        page_title('Tin công nghệ','Bài viết, đánh giá, hướng dẫn sử dụng sản phẩm.');
        $posts=rows('SELECT id_baiviet,tenbaiviet,tomtat,hinhanh,ngaydang FROM tbl_tintuc WHERE trangthai=1 ORDER BY ngaydang DESC LIMIT 24');
        echo '<div class="container section article-grid">';
        foreach ($posts as $post) echo '<a class="article-card surface" href="'.link_to('article',['id'=>$post['id_baiviet']]).'"><img loading="lazy" src="'.h(safe_image($post['hinhanh'],'news')).'" alt=""><span>'.h($post['ngaydang']).'</span><h3>'.h($post['tenbaiviet']).'</h3><p>'.h(strip_tags($post['tomtat'])).'</p></a>';
        echo '</div>';
    } else {
        $id=as_int($_GET['id']??0);
        $post=row('SELECT * FROM tbl_tintuc WHERE id_baiviet=? AND trangthai=1',[$id]);
        if (!$post) {http_response_code(404);page_title('Không tìm thấy bài viết');}
        else {
            page_title($post['tenbaiviet'],(string)$post['ngaydang']);
            echo '<div class="container section"><article class="surface prose article-detail"><img src="'.h(safe_image($post['hinhanh'],'news')).'" alt=""><p>'.nl2br(h(strip_tags($post['noidung']))).'</p></article></div>';
        }
    }
}
echo '</main>';
page_foot();
} catch (Throwable $e) {
    error_log('[BanHang v2 rendering] '.$e->getMessage());
    http_response_code(500);
    echo '<section class="container section"><div class="surface"><h1>Không thể hiển thị trang.</h1><p>Kiểm tra kết nối MySQL và đã import v2/database_upgrade.sql.</p></div></section>';
    page_foot();
}
