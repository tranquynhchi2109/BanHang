<?php
try {
echo '<main>';
echo '<div class="container flash">'.(notices_html()??'').'</div>';

if ($page==='home') {
    $featured=rows('SELECT p.id_sanpham,p.tensanpham,p.giasp,p.hinhanh,p.soluong,c.tendanhmuc FROM tbl_sanpham p JOIN tbl_danhmuc c ON c.id_danhmuc=p.id_danhmuc WHERE p.tinhtrang=1 ORDER BY p.id_sanpham DESC LIMIT 8');
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
        $picture=backup_image((string)$cat['tendanhmuc']);
        echo '<a href="'.link_to('products',['category_id'=>(int)$cat['id_danhmuc']]).'"><span class="category-photo"><img loading="lazy" decoding="async" src="'.h($picture).'" alt="'.h($cat['tendanhmuc']).'"></span><strong>'.h($cat['tendanhmuc']).'</strong><small>Khám phá ↗</small></a>';
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
    $products=rows('SELECT p.id_sanpham,p.tensanpham,p.giasp,p.hinhanh,p.soluong,c.tendanhmuc FROM tbl_sanpham p JOIN tbl_danhmuc c ON c.id_danhmuc=p.id_danhmuc WHERE '.$clause.' ORDER BY '.$sortSql.' LIMIT '.$limit.' OFFSET '.$offset,$params);
    page_title($page==='recommendations'?'Gợi ý cho bạn':'Khám phá sản phẩm','Chọn theo thương hiệu, giá cả và nhu cầu của bạn.');
    echo '<div class="container section"><div class="catalog-layout"><aside class="sidebar-filter"><h3>Danh mục sản phẩm</h3><a href="'.link_to('products').'">Tất cả sản phẩm</a>';
    foreach (rows('SELECT id_danhmuc,tendanhmuc FROM tbl_danhmuc ORDER BY thutu,id_danhmuc') as $cat) {
        echo '<a href="'.link_to('products',['category_id'=>(int)$cat['id_danhmuc']]).'">'.h($cat['tendanhmuc']).'</a>';
    }
    echo '<hr><p>Giá và tồn kho được lấy trực tiếp từ cơ sở dữ liệu.</p></aside><section class="catalog-main"><div class="catalog-toolbar"><span><strong>'.(int)($totalRow['n']??0).'</strong> sản phẩm</span><form method="get"><input type="hidden" name="page" value="products"><input type="hidden" name="q" value="'.h($q).'">';
    if ($categoryId) echo '<input type="hidden" name="category_id" value="'.(int)$categoryId.'">';
    echo '<select class="field" name="sort" data-auto-submit><option value="latest"'.selected($sort,'latest').'>Mới nhất</option><option value="price_asc"'.selected($sort,'price_asc').'>Giá tăng dần</option><option value="price_desc"'.selected($sort,'price_desc').'>Giá giảm dần</option><option value="name"'.selected($sort,'name').'>Tên A–Z</option></select></form></div>';
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
        $isPhone=is_phone_product($product);
        $variant=$isPhone?strtoupper((string)($_GET['variant']??'128GB')):'BASE';
        $prices=$isPhone?available_variants($id):[];
        if ($isPhone && !in_array($variant,['128GB','256GB','512GB','1TB'],true)) $variant='128GB';
        $displayPrice=$isPhone?($prices[$variant]??null):(int)$product['giasp'];
        echo '<div class="container section"><p class="breadcrumb"><a href="'.h(project_url('index.php')).'">Trang chủ</a> / '.h($product['tendanhmuc']).' / '.h($product['tensanpham']).'</p>';
        echo '<div class="product-detail"><div class="product-detail__photo"><img decoding="async" src="'.h(safe_image($product['hinhanh'])).'" data-image-fallback="'.h(backup_image((string)$product['tendanhmuc'])).'" alt="'.h($product['tensanpham']).'"></div><div class="product-detail__info"><span class="badge">CHÍNH HÃNG</span>';
        echo '<h1>'.h($product['tensanpham']).'</h1><p class="rating">★ 4.9 <span>· Giá được đối chiếu với cơ sở dữ liệu</span></p>';
        echo '<p class="detail-price">'.($displayPrice===null?'Dung lượng chưa có giá bán':money($displayPrice)).'</p>';
        if ($isPhone) {
            echo '<div class="variant-picker"><strong>Chọn dung lượng</strong><div class="variant-options">';
            foreach (['128GB','256GB','512GB','1TB'] as $option) {
                if (isset($prices[$option])) {
                    echo '<a class="variant-option'.($variant===$option?' active':'').'" href="'.link_to('product',['id'=>$id,'variant'=>$option]).'">'.h($option).'<small>'.money($prices[$option]).'</small></a>';
                } else {
                    echo '<span class="variant-option disabled" title="Chưa có giá được cửa hàng xác nhận">'.h($option).'<small>Chưa mở bán</small></span>';
                }
            }
            echo '</div></div>';
        }
        echo '<p>'.nl2br(h(strip_tags(str_ireplace(['<br>','<br/>','<br />'],"\n",(string)$product['tomtat'])))).'</p><p><strong>Tình trạng:</strong> '.((int)$product['soluong']>0?'Còn '.(int)$product['soluong'].' sản phẩm':'Tạm hết hàng').'</p>';
        if ((int)$product['soluong']>0 && $displayPrice!==null && $displayPrice>0) {
            echo '<form action="'.h(project_url('index.php')).'" method="post" class="purchase-form">'.csrf_field().'<input type="hidden" name="action" value="add_cart"><input type="hidden" name="product_id" value="'.$id.'"><input type="hidden" name="variant" value="'.h($variant).'">';
            echo '<label>Số lượng <input class="field qty" name="qty" type="number" min="1" max="'.min((int)$product['soluong'],20).'" value="1" required></label><button class="button">＋ Thêm vào giỏ hàng</button></form>';
        } else if ($displayPrice===null) {
            echo '<p class="hint">Cửa hàng chưa xác nhận giá dung lượng này. Vui lòng chọn phiên bản có giá hoặc liên hệ cửa hàng.</p>';
        }
        echo '<div class="product-services">✓ Thanh toán bảo mật &nbsp; ✓ Có theo dõi đơn &nbsp; ✓ Tư vấn hỗ trợ</div></div></div>';
        echo '<section class="section"><h2>Mô tả sản phẩm</h2><div class="surface prose">'.nl2br(h(strip_tags((string)$product['noidung']))).'</div></section>';
        $recommended=rows('SELECT p.id_sanpham,p.tensanpham,p.giasp,p.hinhanh,p.soluong,c.tendanhmuc FROM tbl_sanpham p JOIN tbl_danhmuc c ON c.id_danhmuc=p.id_danhmuc WHERE p.id_danhmuc=? AND p.id_sanpham<>? AND p.tinhtrang=1 ORDER BY p.id_sanpham DESC LIMIT 4',[$product['id_danhmuc'],$id]);
        section_header('Sản phẩm liên quan','Bạn có thể quan tâm');product_grid($recommended);
        echo '</div>';
    }
}
elseif ($page==='cart' || $page==='checkout') {
    $items=select_cart_items();$total=array_sum(array_column($items,'cart_total'));
    $hasMissingPrice=false;
    foreach ($items as $item) {if ($item['cart_price_error']) $hasMissingPrice=true;}
    if ($page==='checkout' && !$items) redirect_to(link_to('cart'));
    if ($page==='checkout' && $hasMissingPrice) {
        notice('Một số dung lượng trong giỏ chưa có giá bán. Vui lòng xóa sản phẩm chưa có giá rồi chọn lại.', 'danger');
        redirect_to(link_to('cart'));
    }
    if ($page==='checkout') $user=require_customer();
    page_title($page==='cart'?'Giỏ hàng của bạn':'Thanh toán đơn hàng','Mọi thông tin được xác minh lại trên máy chủ.');
    echo '<div class="container section"><div class="checkout-layout"><section class="surface cart-items">';
    if (!$items) echo '<div class="empty-state"><h2>Giỏ hàng trống</h2><p>Thêm sản phẩm yêu thích và quay lại nhé.</p><a class="button" href="'.link_to('products').'">Xem sản phẩm</a></div>';
    elseif ($page==='cart') {
        echo '<h2>Sản phẩm đã chọn</h2>';
        foreach ($items as $item) {
            $id=(int)$item['id_sanpham'];
            echo '<div class="cart-item"><img loading="lazy" decoding="async" src="'.h(safe_image($item['hinhanh'])).'" data-image-fallback="'.h(backup_image((string)$item['tendanhmuc'])).'" alt="'.h($item['tensanpham']).'"><div><strong>'.h($item['tensanpham']).'</strong>';
            if ($item['variant_label']!=='') echo '<small class="cart-variant">'.h($item['variant_label']).'</small>';
            echo '<p>'.($item['cart_price_error']?'Dung lượng chưa có giá · vui lòng xóa khỏi giỏ':money((int)$item['cart_unit_price'])).'</p></div>';
            echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="cart_update"><input type="hidden" name="product_id" value="'.$id.'"><input type="hidden" name="cart_key" value="'.h($item['cart_key']).'"><input class="field qty" name="qty" type="number" min="0" max="20" value="'.(int)$item['cart_qty'].'"><button class="button button--light">Cập nhật</button></form></div>';
        }
    } else {
        echo '<h2>Thông tin giao hàng</h2><form action="index.php" method="post" id="checkout-form">'.csrf_field().'<input type="hidden" name="action" value="checkout"><input type="hidden" name="expected_total" value="'.(int)$total.'">';
        input_field('name','Họ và tên',$user['tenkhachhang'],'text','required maxlength="160"');
        input_field('phone','Số điện thoại',$user['dienthoai'],'tel','required maxlength="25"');
        echo '<label class="field-group"><span>Địa chỉ nhận hàng</span><textarea class="field" name="address" minlength="10" maxlength="500" required>'.h($user['diachi']).'</textarea></label>';
        echo '<label class="field-group"><span>Ghi chú giao hàng (không bắt buộc)</span><textarea class="field" maxlength="500" name="note"></textarea></label>';
        echo '<h3>Phương thức thanh toán</h3><label class="radio-option"><input type="radio" name="payment" value="COD" checked> Thanh toán khi nhận hàng (COD)</label>';
        echo '<label class="radio-option"><input type="radio" name="payment" value="BANK"> Chuyển khoản ngân hàng / VietQR</label>';
        echo '<p class="hint">VietQR hiển thị thông tin chuyển khoản, không tự xác nhận tiền đã nhận.</p></form>';
    }
    echo '</section><aside class="surface order-summary"><h2>Tóm tắt đơn hàng</h2>';
    foreach ($items as $item) echo '<div class="summary-line"><span>'.h($item['tensanpham']).($item['variant_label']!==''?' · '.h($item['variant_label']):'').' × '.(int)$item['cart_qty'].'</span><strong>'.($item['cart_price_error']?'Chưa có giá':money((int)$item['cart_total'])).'</strong></div>';
    echo '<hr><div class="summary-total"><span>Tổng tiền</span><strong>'.($hasMissingPrice?'Chưa thể tính':money((int)$total)).'</strong></div>';
    if ($hasMissingPrice) echo '<p class="hint">Có sản phẩm chưa được định giá. Xóa dòng đó khỏi giỏ trước khi thanh toán.</p>';
    if ($items && !$hasMissingPrice) {
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
    echo '<div class="container section account-layout"><aside class="surface account-side"><h2>'.h($user['tenkhachhang']).'</h2><p>'.h($user['email']).'</p><a href="'.link_to('account').'">Thông tin cá nhân</a><a href="'.link_to('orders').'">Đơn hàng của tôi</a><a href="'.link_to('notifications').'">Thông báo của tôi</a><a href="'.link_to('password').'">Đổi mật khẩu</a>';
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
elseif ($page==='notifications') {
    $user=require_customer();
    $uid=(int)$user['id_dangky'];
    $filter=(string)($_GET['filter']??'all');
    $unreadOnly=$filter==='unread';
    $curPage=max(1,min(200,(int)($_GET['p']??1)));
    $perPage=15;
    $offset=($curPage-1)*$perPage;
    $totalRow=row('SELECT COUNT(*) AS n FROM tbl_notifications WHERE user_id=?'.($unreadOnly?' AND is_read=0':''),[$uid]);
    $totalCount=(int)($totalRow['n']??0);
    $unreadCount=unread_notification_count($uid);
    $notifs=user_notifications($uid,$perPage,$offset,$unreadOnly);
    page_title('Trung tâm thông báo','Cập nhật trạng thái thanh toán và giao hàng cho đơn hàng của bạn.');
    echo '<div class="container section"><div class="surface notif-page">';
    echo '<div class="notif-page__head"><div class="filter-tabs">';
    echo '<a class="'.(!$unreadOnly?'active':'').'" href="'.link_to('notifications').'">Tất cả</a>';
    echo '<a class="'.($unreadOnly?'active':'').'" href="'.link_to('notifications',['filter'=>'unread']).'">Chưa đọc ('.$unreadCount.')</a>';
    echo '</div>';
    if ($unreadCount>0) {
        echo '<form method="post" action="'.h(project_url('index.php')).'">'.csrf_field().'<input type="hidden" name="action" value="mark_all_notif_read"><button type="submit" class="button button--light">Đánh dấu tất cả đã đọc</button></form>';
    }
    echo '</div>';
    if (!$notifs) {
        echo '<div class="empty-state"><h3>'.($unreadOnly?'Không có thông báo chưa đọc':'Chưa có thông báo nào').'</h3><p>Khi đơn hàng có cập nhật thanh toán hoặc vận chuyển, thông báo sẽ hiển thị tại đây.</p><a class="button" href="'.link_to('orders').'">Xem đơn hàng của tôi</a></div>';
    } else {
        echo '<div class="notif-list">';
        foreach ($notifs as $n) {
            $isUnread=(int)$n['is_read']===0;
            $href=trim((string)$n['link_url'])!==''?(string)$n['link_url']:link_to('orders');
            echo '<article class="notif-card'.($isUnread?' is-unread':'').'">';
            echo '<div class="notif-card__body"><div class="notif-card__top">';
            echo '<span class="status-pill status-pill--'.($n['notification_type']==='PAYMENT_CONFIRMED' || $n['notification_type']==='ORDER_COMPLETED'?'success':($n['notification_type']==='ORDER_CANCELLED'?'danger':($n['notification_type']==='PAYMENT_REJECTED'?'warning':'info'))).'">'.($isUnread?'Mới':'Đã đọc').'</span>';
            echo '<small>'.h($n['created_at']).'</small></div>';
            echo '<h3>'.h($n['title']).'</h3><p>'.h($n['message']).'</p></div>';
            echo '<div class="notif-card__actions">';
            echo '<a class="button button--light" href="'.h($href).'" data-notif-id="'.(int)$n['id'].'">Xem chi tiết đơn hàng →</a>';
            if ($isUnread) {
                echo '<form method="post" action="'.h(project_url('index.php')).'">'.csrf_field().'<input type="hidden" name="action" value="mark_notif_read"><input type="hidden" name="id" value="'.(int)$n['id'].'"><button type="submit" class="button button--ghost">Đánh dấu đã đọc</button></form>';
            }
            echo '</div></article>';
        }
        echo '</div>';
        $totalPages=(int)ceil($totalCount/$perPage);
        if ($totalPages>1) {
            echo '<nav class="pagination">';
            for ($p=1;$p<=min($totalPages,20);$p++) {
                echo '<a class="'.($p===$curPage?'active':'').'" href="'.link_to('notifications',array_filter(['filter'=>$unreadOnly?'unread':'','p'=>$p])).'">'.$p.'</a>';
            }
            echo '</nav>';
        }
    }
    echo '</div></div>';
}
elseif ($page==='orders' || $page==='order') {
    $user=require_customer();
    page_title($page==='orders'?'Đơn hàng của tôi':'Theo dõi đơn hàng','Xem tiến độ thanh toán, đóng gói và giao hàng trực tiếp từ hệ thống.');
    echo '<div class="container section">';
    if ($page==='orders') {
        $curPage=max(1,min(200,(int)($_GET['p']??1)));
        $perPage=15;$offset=($curPage-1)*$perPage;
        $totalOrders=(int)(row('SELECT COUNT(*) AS n FROM tbl_orders WHERE user_id=?',[$user['id_dangky']])['n']??0);
        $orders=rows('SELECT id,order_code,total_amount,order_status,payment_method,payment_status,payment_review_status,created_at,updated_at FROM tbl_orders WHERE user_id=? ORDER BY id DESC LIMIT '.$perPage.' OFFSET '.$offset,[$user['id_dangky']]);
        echo '<div class="surface"><h2>Lịch sử đặt hàng</h2>';
        if ($orders) {
            echo '<div class="table-scroll"><table><thead><tr><th>Mã đơn</th><th>Ngày đặt</th><th>Tổng tiền</th><th>Thanh toán</th><th>Trạng thái đơn</th><th>Thao tác</th></tr></thead><tbody>';
            foreach ($orders as $o) {
                $payState=payment_state_summary($o);
                echo '<tr><td><a class="link-blue" href="'.link_to('order',['code'=>$o['order_code']]).'">'.h($o['order_code']).'</a></td>';
                echo '<td>'.h($o['created_at']).'</td>';
                echo '<td><strong>'.money((int)$o['total_amount']).'</strong></td>';
                echo '<td><span class="status-pill status-pill--'.h($payState['tone']).'">'.h($payState['label']).'</span></td>';
                echo '<td><span class="status-pill status-pill--'.h(order_status_tone((string)$o['order_status'])).'">'.h(order_status_label((string)$o['order_status'])).'</span></td>';
                echo '<td><a class="link-blue" href="'.link_to('order',['code'=>$o['order_code']]).'">Theo dõi →</a></td></tr>';
            }
            echo '</tbody></table></div>';
            $totalPages=(int)ceil($totalOrders/$perPage);
            if ($totalPages>1) {
                echo '<nav class="pagination">';
                for ($p=1;$p<=min($totalPages,20);$p++) echo '<a class="'.($p===$curPage?'active':'').'" href="'.link_to('orders',['p'=>$p]).'">'.$p.'</a>';
                echo '</nav>';
            }
        } else {
            echo '<p class="hint">Bạn chưa có đơn hàng mới nào.</p>';
        }
        $legacyOrders=rows('SELECT id_cart,code_cart,cart_date,cart_payment,tongtien,cart_status FROM tbl_cart WHERE id_khachhang=? ORDER BY id_cart DESC LIMIT 50',[$user['id_dangky']]);
        echo '</div>';
        if ($legacyOrders) {
            echo '<div class="surface legacy-orders"><h2>Lịch sử đơn hàng trước đây</h2><div class="table-scroll"><table><thead><tr><th>Mã đơn</th><th>Ngày đặt</th><th>Số tiền</th><th>Thanh toán</th><th>Trạng thái</th></tr></thead><tbody>';
            foreach ($legacyOrders as $old) echo '<tr><td><a class="link-blue" href="'.link_to('legacy_order',['id'=>$old['id_cart']]).'">'.h($old['code_cart']).'</a></td><td>'.h($old['cart_date']).'</td><td>'.money((int)$old['tongtien']).'</td><td>'.h($old['cart_payment']).'</td><td>'.((int)$old['cart_status']===1?'Đang chờ cửa hàng xem':'Đã được xem').'</td></tr>';
            echo '</tbody></table></div></div>';
        }
    } else {
        $code=trim((string)($_GET['code']??''));
        $order=row('SELECT * FROM tbl_orders WHERE order_code=? AND user_id=?',[$code,$user['id_dangky']]);
        if (!$order) { http_response_code(404);echo '<div class="surface">Không tìm thấy đơn hàng của bạn.</div>'; }
        else {
            // Mark related unread notifications as read when customer opens this order
            run('UPDATE tbl_notifications SET is_read=1, read_at=COALESCE(read_at, NOW()) WHERE user_id=? AND order_id=? AND is_read=0',
                [(int)$user['id_dangky'],(int)$order['id']]);
            $payState=payment_state_summary($order);
            $history=rows('SELECT from_status,to_status,actor_type,note,created_at FROM tbl_order_status_history WHERE order_id=? AND is_public=1 ORDER BY id ASC',[(int)$order['id']]);
            $reached=[];
            foreach ($history as $hItem) {
                $reached[(string)$hItem['to_status']]=(string)$hItem['created_at'];
            }
            $curStatus=(string)$order['order_status'];
            $steps=[
                'PENDING'=>'Đã đặt hàng',
                'CONFIRMED'=>'Đã xác nhận',
                'PREPARING'=>'Đang chuẩn bị hàng',
                'SHIPPING'=>'Đang giao hàng',
                'COMPLETED'=>'Đã giao thành công',
            ];
            $stepOrder=array_keys($steps);
            $curIdx=array_search($curStatus,$stepOrder,true);
            $sig=$curStatus.'|'.$order['payment_status'].'|'.$order['payment_review_status'].'|'.$order['updated_at'];

            echo '<div class="order-watch-banner" id="order-live-banner" hidden></div>';
            echo '<div class="checkout-layout" data-order-watch="'.h($order['order_code']).'" data-order-signature="'.h($sig).'" data-order-final="'.(in_array($curStatus,['COMPLETED','CANCELLED'],true)?'1':'0').'">';
            echo '<section class="surface">';
            echo '<div class="order-header-row"><div><span class="status-pill status-pill--'.h(order_status_tone($curStatus)).'">'.h(order_status_label($curStatus)).'</span>';
            echo '<h2>Đơn hàng '.h($order['order_code']).'</h2><p class="hint">Đặt lúc '.h($order['created_at']).' · Cập nhật gần nhất: '.h($order['updated_at']).'</p></div>';
            echo '<a class="button button--light" href="'.link_to('orders').'">← Tất cả đơn hàng</a></div>';

            if ($curStatus==='COMPLETED') {
                echo '<div class="order-banner order-banner--success"><strong>Giao hàng thành công!</strong><p>Đơn hàng '.h($order['order_code']).' đã được giao thành công'.(!empty($order['delivered_at'])?' lúc '.h($order['delivered_at']):'').'. Cảm ơn bạn đã tin tưởng BanHang!</p></div>';
            } elseif ($curStatus==='CANCELLED') {
                echo '<div class="order-banner order-banner--danger"><strong>Đơn hàng đã bị hủy</strong><p>Lý do: '.h($order['cancel_reason']!==''?$order['cancel_reason']:'Đơn hàng đã bị hủy theo quy trình xử lý.').'</p><p>Tình trạng thanh toán: '.h($payState['label']).'.</p></div>';
            }

            // Accurate visual timeline showing ONLY reached/current/upcoming steps
            echo '<div class="order-timeline">';
            foreach ($steps as $stKey=>$stLabel) {
                $idx=array_search($stKey,$stepOrder,true);
                $stateClass='upcoming';
                $stepTime=$reached[$stKey]??'';
                if ($curStatus==='CANCELLED') {
                    if ($stKey==='PENDING' || isset($reached[$stKey])) $stateClass='done';
                } elseif ($curIdx!==false) {
                    if ($idx<$curIdx || ($curStatus==='COMPLETED' && $idx===$curIdx)) $stateClass='done';
                    elseif ($idx===$curIdx) $stateClass='current';
                }
                if ($stKey==='PENDING' && $stepTime==='') $stepTime=(string)$order['created_at'];
                echo '<div class="timeline-step is-'.$stateClass.'"><span class="timeline-dot"></span><strong>'.h($stLabel).'</strong><small>'.($stepTime!==''?h($stepTime):($stateClass==='upcoming'?'Chờ xử lý':'')).'</small></div>';
            }
            if ($curStatus==='CANCELLED') {
                echo '<div class="timeline-step is-cancelled"><span class="timeline-dot"></span><strong>Đã hủy</strong><small>'.h($reached['CANCELLED']??$order['updated_at']).'</small></div>';
            }
            echo '</div>';

            // Shipping / tracking block (real data only)
            if (trim((string)($order['shipping_carrier']??''))!=='' || trim((string)($order['tracking_code']??''))!=='' || !empty($order['shipped_at'])) {
                echo '<div class="shipping-box"><h3>Thông tin vận chuyển</h3>';
                if (trim((string)$order['shipping_carrier'])!=='') echo '<p>Đơn vị vận chuyển: <strong>'.h($order['shipping_carrier']).'</strong></p>';
                if (trim((string)$order['tracking_code'])!=='') echo '<p>Mã vận đơn: <code>'.h($order['tracking_code']).'</code></p>';
                if (!empty($order['shipped_at'])) echo '<p>Thời gian bàn giao vận chuyển: '.h($order['shipped_at']).'</p>';
                if (!empty($order['delivered_at'])) echo '<p>Thời gian giao thành công: '.h($order['delivered_at']).'</p>';
                echo '</div>';
            }
            if (trim((string)($order['customer_note']??''))!=='') {
                echo '<div class="payment-status-note"><strong>Ghi chú từ cửa hàng</strong><p>'.nl2br(h($order['customer_note'])).'</p></div>';
            }

            echo '<h3>Sản phẩm trong đơn</h3>';
            foreach (rows('SELECT product_name,variant_label,quantity,unit_price FROM tbl_order_items WHERE order_id=?',[$order['id']]) as $item) {
                echo '<div class="summary-line"><span>'.h($item['product_name']).($item['variant_label']!=='BASE'?' · '.h($item['variant_label']):'').' × '.(int)$item['quantity'].'</span><strong>'.money((int)$item['unit_price']*(int)$item['quantity']).'</strong></div>';
            }
            echo '<hr><h3>Thông tin nhận hàng</h3><p><strong>'.h($order['customer_name']).'</strong> · '.h($order['phone']).'</p><p>'.h($order['delivery_address']).'</p>';
            if (trim((string)$order['note'])!=='') echo '<p class="hint">Ghi chú của bạn: '.h($order['note']).'</p>';

            echo '<hr><h3>Lịch sử cập nhật đơn hàng</h3>';
            if (!$history) {
                echo '<p class="hint">Đơn hàng đã được ghi nhận lúc '.h($order['created_at']).'.</p>';
            } else {
                echo '<div class="history-list">';
                foreach (array_reverse($history) as $ev) {
                    echo '<div class="history-item"><div><strong>'.h(order_status_label((string)$ev['to_status'])).'</strong><span>'.h($ev['created_at']).'</span></div>';
                    if (trim((string)$ev['note'])!=='') echo '<p>'.h($ev['note']).'</p>';
                    echo '</div>';
                }
                echo '</div>';
            }
            echo '</section>';

            // Right summary: payment & reconciliation card
            echo '<aside class="surface order-summary"><h2>Thông tin thanh toán</h2>';
            echo '<p>Phương thức: <strong>'.h(payment_method_label((string)$order['payment_method'])).'</strong></p>';
            echo '<p>Trạng thái thanh toán: <span class="status-pill status-pill--'.h($payState['tone']).'">'.h($payState['label']).'</span></p>';
            if ($order['payment_method']==='BANK') {
                echo '<p>Trạng thái đối soát: <strong>'.h(payment_review_label((string)$order['payment_review_status'])).'</strong></p>';
            }
            echo '<p class="detail-price">'.money((int)$order['total_amount']).'</p>';

            if ($order['payment_method']==='BANK' && $order['payment_status']==='PAID') {
                echo '<div class="order-banner order-banner--success"><strong>Thanh toán thành công!</strong>';
                echo '<p>BanHang đã đối soát sao kê MB Bank và xác nhận nhận đủ <strong>'.money((int)$order['total_amount']).'</strong> cho đơn hàng '.h($order['order_code']).'.</p>';
                if (!empty($order['payment_verified_at'])) echo '<small>Xác nhận lúc: '.h($order['payment_verified_at']).'</small>';
                echo '</div>';
            } elseif ($order['payment_method']==='COD' && $order['payment_status']==='PAID') {
                echo '<div class="order-banner order-banner--success"><strong>Đã xác nhận thu tiền COD</strong>';
                echo '<p>Cửa hàng đã xác nhận nhận đủ tiền thanh toán cho đơn hàng này.</p>';
                if (!empty($order['cod_paid_at'])) echo '<small>Xác nhận lúc: '.h($order['cod_paid_at']).'</small>';
                echo '</div>';
            } elseif ($order['payment_method']==='BANK' && $order['payment_status']==='UNPAID' && $curStatus!=='CANCELLED') {
                $qr=qr_link($order);
                if ($qr) {
                    $bankId = setting('bank_id');
                    $bankLabel = in_array(strtoupper($bankId), ['970422','MB','MBBANK'], true) ? 'MB Bank (Ngân hàng Quân đội)' : $bankId;
                    echo '<img class="payment-qr" src="'.h($qr).'" alt="Mã QR chuyển khoản theo đơn hàng">';
                    echo '<p>Ngân hàng: <strong>'.h($bankLabel).'</strong></p>';
                    echo '<p>Số tài khoản: <strong>'.h(setting('bank_account')).'</strong></p>';
                    if (trim(setting('bank_name')) !== '') echo '<p>Chủ tài khoản: <strong>'.h(setting('bank_name')).'</strong></p>';
                    echo '<p>Nội dung chuyển khoản: <code>BANHANG '.h($order['order_code']).'</code></p>';
                    echo '<p class="hint">Hãy kiểm tra đúng tên người nhận trong ứng dụng ngân hàng trước khi chuyển. Cửa hàng đối soát thủ công; quét QR không đồng nghĩa đơn đã thanh toán.</p>';
                } else echo '<p>Chưa cấu hình tài khoản nhận chuyển khoản.</p>';
                if ($order['payment_review_status']==='REPORTED') {
                    echo '<div class="payment-status-note"><strong>Đã báo chuyển khoản – đang chờ đối soát</strong>';
                    echo '<p>Đây chưa phải xác nhận thanh toán thành công. Cửa hàng đang kiểm tra giao dịch thực tế trên MB Bank và sẽ gửi thông báo ngay khi xác nhận.</p>';
                    if ($order['payment_reported_at']) echo '<small>Đã báo lúc '.h($order['payment_reported_at']).'</small>';
                    echo '</div>';
                } else {
                    if ($order['payment_review_status']==='REJECTED')
                        echo '<p class="payment-alert">Cửa hàng chưa tìm thấy giao dịch khớp trên sao kê MB Bank. Vui lòng kiểm tra lại và gửi báo cáo lần nữa nếu bạn đã chuyển khoản.</p>';
                    echo '<form method="post" action="'.h(project_url('index.php')).'" class="payment-report-form">'.csrf_field();
                    echo '<input type="hidden" name="action" value="report_bank_transfer"><input type="hidden" name="order_code" value="'.h($order['order_code']).'">';
                    echo '<label class="field-group">Ghi chú chuyển khoản (không bắt buộc)<input class="field" name="transfer_note" maxlength="200" placeholder="Ví dụ: 4 số cuối mã giao dịch"></label>';
                    echo '<button type="submit" class="button full" data-confirm="Chỉ báo sau khi bạn thực sự đã chuyển khoản. Tiếp tục?">Tôi đã chuyển khoản – gửi kiểm tra</button>';
                    echo '<p class="hint">Nút này chỉ gửi yêu cầu đối soát, KHÔNG tự xác nhận đã nhận tiền. Không nhập mật khẩu, OTP hoặc mã PIN ngân hàng.</p></form>';
                }
            } elseif ($order['payment_method']==='COD' && $curStatus!=='CANCELLED') {
                echo '<p class="hint">Bạn sẽ thanh toán bằng tiền mặt hoặc theo hướng dẫn khi nhân viên giao hàng giao sản phẩm.</p>';
            }

            echo '<hr><p class="hint">Cần hỗ trợ về đơn hàng này? <a class="link-blue" href="'.link_to('contact').'">Liên hệ bộ phận CSKH →</a></p>';
            echo '</aside></div>';
        }
    }
    echo '</div>';
}
elseif ($page==='legacy_order') {
    $user=require_customer();
    $legacyId=as_int($_GET['id']??0);
    $order=row('SELECT * FROM tbl_cart WHERE id_cart=? AND id_khachhang=?',[$legacyId,$user['id_dangky']]);
    page_title('Đơn hàng cũ','Thông tin đơn hàng được lưu từ hệ thống trước.');
    if (!$order) {
        http_response_code(404);
        echo '<div class="container section"><div class="surface">Không tìm thấy đơn hàng của bạn.</div></div>';
    } else {
        echo '<div class="container section"><div class="checkout-layout"><section class="surface"><span class="badge">'.((int)$order['cart_status']===1?'Chưa xem':'Đã xem').'</span><h2>Đơn hàng '.h($order['code_cart']).'</h2><p>Ngày đặt: '.h($order['cart_date']).'</p><h3>Sản phẩm</h3>';
        foreach (rows('SELECT d.soluongmua,d.dungluong,p.tensanpham FROM tbl_cart_details d LEFT JOIN tbl_sanpham p ON p.id_sanpham=d.id_sanpham WHERE d.code_cart=? ORDER BY d.id_cart_details',[$order['code_cart']]) as $item) {
            echo '<div class="summary-line"><span>'.h($item['tensanpham']??'Sản phẩm không còn trong danh mục').($item['dungluong']!==''?' · '.h($item['dungluong']):'').' × '.(int)$item['soluongmua'].'</span></div>';
        }
        echo '</section><aside class="surface order-summary"><h2>Thông tin thanh toán</h2><p>Phương thức: '.h($order['cart_payment']).'</p><p class="detail-price">'.money((int)$order['tongtien']).'</p><p class="hint">Đơn hàng này được tạo bằng phiên bản cũ; trạng thái thanh toán và giao hàng chi tiết chưa được lưu trong bảng gốc.</p><a class="button button--light" href="'.link_to('orders').'">Quay lại lịch sử đơn</a></aside></div></div>';
    }
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

elseif ($page==='contact') {
    page_title('Liên hệ','Kết nối với BanHang để được tư vấn và hỗ trợ.');
    $contact = row('SELECT thongtinlienhe FROM tbl_lienhe WHERE id=1');
    echo '<div class="container section"><div class="surface prose">'.nl2br(h(strip_tags((string)($contact['thongtinlienhe'] ?? 'Thông tin liên hệ đang được cập nhật.')))).'</div></div>';
}
echo '</main>';
} catch (Throwable $e) {
    error_log('[BanHang rendering] '.$e->getMessage());
    http_response_code(500);
    echo '<section class="container section"><div class="surface"><h1>Không thể hiển thị trang.</h1><p>Kiểm tra kết nối MySQL và đã import banhang.sql.</p></div></section>';
}
