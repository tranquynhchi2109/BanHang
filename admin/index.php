<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app.php';
migrate_legacy_session();
$page=(string)($_GET['page']??'dashboard');
$pages=['login','dashboard','products','product_form','categories','orders','order','customers','customer','payments','inventory','revenue','recommendations','news','article_form','settings','legacy_orders','legacy_order'];
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
        $adminUser = require_admin();
        $adminId = (int)$adminUser['id_admin'];
        switch ($action) {
            case 'save_product':
                $id=as_int($_POST['id']??0,0,2147483647);
                $name=trim((string)($_POST['name']??''));
                $sku=trim((string)($_POST['sku']??''));
                $category=as_int($_POST['category']??0);
                $price=as_int($_POST['price']??-1,1,2000000000);
                $initialStock=$id===0?as_int($_POST['stock']??-1,0,1000000):0;
                $summary=trim((string)($_POST['summary']??''));
                $description=trim((string)($_POST['description']??''));
                $visible=(int)(($_POST['visible']??'0')==='1');
                if (mb_strlen($name)<3 || mb_strlen($name)>250 || mb_strlen($sku)<1 || mb_strlen($sku)>100 ||
                    mb_strlen($summary)>10000 || mb_strlen($description)>200000) throw new DomainException('Thông tin sản phẩm vượt giới hạn.');
                $categoryData=row('SELECT tendanhmuc FROM tbl_danhmuc WHERE id_danhmuc=?',[$category]);
                if (!$categoryData) throw new DomainException('Danh mục không tồn tại.');
                $isPhone=is_phone_product($categoryData);
                $capacityPrices=[];
                if ($isPhone) {
                    foreach (['256GB','512GB','1TB'] as $capacity) {
                        $raw=trim((string)($_POST['variant_'.$capacity]??''));
                        $capacityPrices[$capacity]=$raw===''?null:as_int($raw,1,2000000000);
                    }
                }
                $existing=$id>0?row('SELECT hinhanh FROM tbl_sanpham WHERE id_sanpham=?',[$id]):null;
                if ($id>0 && !$existing) throw new DomainException('Sản phẩm không còn tồn tại.');
                $filename=save_product_upload($existing['hinhanh']??'');
                if ($id===0 && $filename==='') throw new DomainException('Sản phẩm mới cần có ảnh.');
                db()->beginTransaction();
                try {
                    if ($id>0) {
                        // Never overwrite soluong when editing product metadata (prevents lost updates during checkout)
                        run('UPDATE tbl_sanpham SET tensanpham=?,masp=?,giasp=?,hinhanh=?,tomtat=?,noidung=?,tinhtrang=?,id_danhmuc=? WHERE id_sanpham=?',
                            [$name,$sku,$price,$filename,$summary,$description,$visible,$category,$id]);
                    } else {
                        run('INSERT INTO tbl_sanpham(tensanpham,masp,giasp,soluong,hinhanh,tomtat,noidung,tinhtrang,id_danhmuc) VALUES (?,?,?,?,?,?,?,?,?)',
                            [$name,$sku,$price,$initialStock,$filename,$summary,$description,$visible,$category]);
                        $id=(int)db()->lastInsertId();
                        if ($initialStock>0) {
                            record_inventory_movement($id,null,'INITIAL',$initialStock,0,$initialStock,'Khởi tạo tồn kho ban đầu khi tạo sản phẩm','ADMIN',$adminId);
                        }
                    }
                    if ($isPhone) {
                        run('INSERT INTO tbl_product_variants(product_id,variant_code,price,is_active) VALUES (?,?,?,1)
                             ON DUPLICATE KEY UPDATE price=VALUES(price),is_active=1',[$id,'128GB',$price]);
                        foreach ($capacityPrices as $capacity=>$capacityPrice) {
                            if ($capacityPrice===null) {
                                run('UPDATE tbl_product_variants SET is_active=0 WHERE product_id=? AND variant_code=?',[$id,$capacity]);
                            } else {
                                run('INSERT INTO tbl_product_variants(product_id,variant_code,price,is_active) VALUES (?,?,?,1)
                                     ON DUPLICATE KEY UPDATE price=VALUES(price),is_active=1',[$id,$capacity,$capacityPrice]);
                            }
                        }
                    } else {
                        run('UPDATE tbl_product_variants SET is_active=0 WHERE product_id=?',[$id]);
                    }
                    db()->commit();
                } catch (Throwable $e) {
                    if (db()->inTransaction()) db()->rollBack();
                    throw $e;
                }
                notice('Đã lưu thông tin sản phẩm và giá dung lượng (tồn kho được giữ nguyên an toàn).','success');
                redirect_to(admin_to('products'));
            case 'adjust_stock':
                $productId=as_int($_POST['id']??0,1,2147483647);
                $delta=as_int($_POST['delta']??0,-1000000,1000000);
                $reason=(string)($_POST['reason']??'');
                $expectedRaw=trim((string)($_POST['expected_stock']??''));
                $expected=$expectedRaw!==''?as_int($expectedRaw,0,1000000000):null;
                $adj=adjust_product_stock($productId,$delta,$reason,$adminId,$expected);
                notice("Đã điều chỉnh tồn kho: {$adj['before']} → {$adj['after']} (thay đổi " . ($adj['delta']>0?'+':'') . "{$adj['delta']}).",'success');
                $returnTo=(string)($_POST['return_to']??'inventory');
                if ($returnTo==='product_form') redirect_to(admin_to('product_form',['id'=>$productId]));
                redirect_to(admin_to('inventory'));
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
            case 'decline_cancel_request':
                $id=as_int($_POST['id']??0);
                admin_decline_cancel_request($id,$adminId,(string)($_POST['decline_reason']??''));
                notice('Đã từ chối yêu cầu hủy và thông báo cho khách.','success');
                redirect_to(admin_to('order',['id'=>$id]));
            case 'order_status':
                $id=as_int($_POST['id']??0);
                $next=(string)($_POST['status']??'');
                update_order_status($id,$adminId,$next,[
                    'shipping_carrier'=>(string)($_POST['shipping_carrier']??''),
                    'tracking_code'=>(string)($_POST['tracking_code']??''),
                    'admin_note'=>(string)($_POST['admin_note']??''),
                    'customer_note'=>(string)($_POST['customer_note']??''),
                    'cancel_reason'=>(string)($_POST['cancel_reason']??''),
                ]);
                notice('Đã cập nhật trạng thái đơn hàng thành "'.order_status_label($next).'" và gửi thông báo tới khách hàng.','success');
                redirect_to(admin_to('order',['id'=>$id]));
            case 'save_order_notes':
                $id=as_int($_POST['id']??0);
                $noteRes=save_order_notes($id,$adminId,$_POST);
                if (!empty($noteRes['duplicate_retry'])) {
                    notice('Yêu cầu cập nhật này đã được xử lý trước đó (không tạo thông báo trùng).','info');
                } elseif (!$noteRes['changed']) {
                    notice('Không có thay đổi nào cần lưu.','info');
                } elseif ($noteRes['shipping_notified'] || $noteRes['customer_note_notified']) {
                    notice('Đã lưu ghi chú/vận chuyển và gửi thông báo tới khách hàng.','success');
                } else {
                    notice('Đã lưu ghi chú nội bộ và thông tin đơn hàng.','success');
                }
                redirect_to(admin_to('order',['id'=>$id]));
            case 'mark_paid':
                if ((string)($_POST['verified_receipt']??'')!=='1')
                    throw new DomainException('Bạn phải xác nhận đã kiểm tra giao dịch tiền vào trên MB Bank.');
                $id=as_int($_POST['id']??0);
                $amount=as_int($_POST['received_amount']??0,1,1000000000000);
                confirm_bank_transfer($id,$adminId,(string)($_POST['bank_reference']??''),$amount);
                notice('Đã xác nhận nhận đủ tiền qua MB Bank, ghi nhật ký đối soát và gửi thông báo thanh toán thành công tới khách hàng.','success');
                redirect_to(admin_to('order',['id'=>$id]));
            case 'confirm_cod_paid':
                $id=as_int($_POST['id']??0);
                confirm_cod_payment($id,$adminId,(string)($_POST['cod_note']??''));
                notice('Đã xác nhận thu đủ tiền thanh toán COD cho đơn hàng.','success');
                redirect_to(admin_to('order',['id'=>$id]));
            case 'reject_bank_report':
                $id=as_int($_POST['id']??0);
                reject_bank_report($id,$adminId,(string)($_POST['reason']??''));
                notice('Đã báo cần đối soát lại và gửi thông báo tới khách hàng. Đơn hàng vẫn ở trạng thái chưa thanh toán.','success');
                redirect_to(admin_to('order',['id'=>$id]));
            case 'expire_unpaid':
                $id=as_int($_POST['id']??0);
                expire_unpaid_bank_order($id,$adminId);
                notice('Đã hủy thủ công đơn BANK quá 24 giờ, hoàn tồn kho và thông báo cho khách hàng.','success');
                redirect_to(admin_to('order',['id'=>$id]));
            case 'send_customer_notification':
                $customerId=as_int($_POST['customer_id']??0,1,2147483647);
                $orderId=as_int($_POST['order_id']??0,1,2147483647);
                admin_send_order_notification($customerId,$orderId,$adminId,(string)($_POST['title']??''),(string)($_POST['message']??''));
                notice('Đã gửi thông báo tới khách hàng.','success');
                redirect_to(admin_to('customer',['id'=>$customerId]));
            case 'retry_email_outbox':
                if (!smtp_is_configured(true)) {
                    throw new DomainException('SMTP chưa cấu hình hợp lệ. Không đưa email trở lại hàng đợi để tránh tiếp tục chuyển chúng thành SKIPPED.');
                }
                $rq=requeue_failed_outbox_emails();
                notice("Đã đưa {$rq['requeued']} email FAILED vào hàng đợi và khôi phục {$rq['recovered']} job treo. Worker CLI sẽ gửi nền.",'success');
                redirect_to(admin_to('settings'));
            case 'retry_skipped_email_outbox':
                $requeuedSkipped=requeue_skipped_outbox_emails(smtp_is_configured(true));
                notice("Đã đưa {$requeuedSkipped} email SKIPPED trở lại hàng đợi theo xác nhận thủ công. Worker CLI sẽ gửi nền.",'success');
                redirect_to(admin_to('settings'));
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
                    (($values['bank_id']==='') !== ($values['bank_account']==='')) ||
                    mb_strlen($values['bank_name'])>150 || mb_strlen($values['contact_info'])>2000) {
                    throw new DomainException('Cấu hình ngân hàng hoặc cửa hàng không hợp lệ. Muốn tắt chuyển khoản, hãy xóa cả mã ngân hàng và số tài khoản.');
                }
                db()->beginTransaction();
                try {
                    foreach ($values as $key=>$value) {
                        if ($key==='contact_info') continue;
                        run('INSERT INTO tbl_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',[$key,$value]);
                    }
                    run('INSERT INTO tbl_lienhe(id,thongtinlienhe) VALUES (1,?) ON DUPLICATE KEY UPDATE thongtinlienhe=VALUES(thongtinlienhe)',[$values['contact_info']]);
                    db()->commit();
                } catch (Throwable $e) {
                    if (db()->inTransaction()) db()->rollBack();
                    throw $e;
                }
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
    $act=(string)($_POST['action']??'');
    $postedId=filter_var($_POST['id']??0,FILTER_VALIDATE_INT);
    $postedCustomerId=filter_var($_POST['customer_id']??0,FILTER_VALIDATE_INT);
    if (in_array($act,['order_status','decline_cancel_request','save_order_notes','mark_paid','reject_bank_report','expire_unpaid','confirm_cod_paid'],true) && $postedId && $postedId>0) {
        redirect_to(admin_to('order',['id'=>(int)$postedId]));
    }
    if ($act==='send_customer_notification' && $postedCustomerId && $postedCustomerId>0) {
        redirect_to(admin_to('customer',['id'=>(int)$postedCustomerId]));
    }
    if ($act==='adjust_stock') {
        $ret=(string)($_POST['return_to']??'inventory');
        if ($ret==='product_form' && $postedId && $postedId>0) redirect_to(admin_to('product_form',['id'=>(int)$postedId]));
        redirect_to(admin_to('inventory'));
    }
    if ($act==='save_product' && $postedId && $postedId>0) {
        redirect_to(admin_to('product_form',['id'=>(int)$postedId]));
    }
    if ($act==='save_article' && $postedId && $postedId>0) {
        redirect_to(admin_to('article_form',['id'=>(int)$postedId]));
    }
    $target=match($act) {
        'login_admin'=>'login','save_product'=>'product_form','save_category'=>'categories',
        'order_status','decline_cancel_request','save_order_notes','mark_paid','reject_bank_report','expire_unpaid','confirm_cod_paid'=>'orders',
        'legacy_order_seen'=>'legacy_orders','send_customer_notification'=>'customers',
        'save_settings','retry_email_outbox','retry_skipped_email_outbox'=>'settings','save_article'=>'article_form',
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
    $pendingRec=admin_pending_reconciliation_count();
    $pendingOrd=(int)(row("SELECT COUNT(*) AS n FROM tbl_orders WHERE order_status='PENDING'")['n']??0);
    $nav=[
        'dashboard'=>['◈','Tổng quan',0,''],
        'orders'=>['♧','Đơn hàng',$pendingOrd,'data-admin-pending-orders'],
        'payments'=>['▤','Thanh toán',$pendingRec,'data-admin-pending-rec'],
        'customers'=>['♙','Khách hàng',0,''],
        'products'=>['▣','Sản phẩm',0,''],
        'categories'=>['▦','Danh mục',0,''],
        'inventory'=>['◫','Kho hàng',0,''],
        'revenue'=>['▥','Doanh thu',0,''],
        'legacy_orders'=>['▤','Đơn hàng cũ',0,''],
        'recommendations'=>['◇','Gợi ý sản phẩm',0,''],
        'news'=>['▧','Tin tức',0,''],
        'settings'=>['⚙','Cấu hình',0,''],
    ];
    echo '<div class="admin-shell" data-admin-shell="1"><aside class="admin-sidebar" id="admin-sidebar"><a class="admin-brand" href="'.admin_to('dashboard').'"><span class="brand__mark">B</span> BANHANG <small>MANAGEMENT CONSOLE</small></a><p class="admin-caption">WORKSPACE</p>';
    $current=(string)($_GET['page']??'dashboard');
    if ($current==='order') $current='orders';
    if ($current==='customer') $current='customers';
    if ($current==='product_form') $current='products';
    if ($current==='article_form') $current='news';
    if ($current==='legacy_order') $current='legacy_orders';
    foreach ($nav as $key=>$entry) {
        $badgeHtml='';
        if ($entry[3]!=='') {
            $badgeHtml='<span class="nav-pill'.($entry[2]>0?'':' is-hidden').'" '.$entry[3].'>'.(int)$entry[2].'</span>';
        }
        echo '<a class="'.($current===$key?'active':'').'" href="'.admin_to($key).'"><span>'.h($entry[0]).'</span><em>'.h($entry[1]).'</em>'.$badgeHtml.'</a>';
    }
    echo '<hr><a href="' . h(project_url('index.php')) . '">↗ Xem cửa hàng</a>';
    echo '</aside><div class="admin-body"><header class="admin-topbar"><div class="admin-topbar__left"><button type="button" class="mobile-toggle" data-menu-toggle>☰</button><strong>BanHang Management</strong></div>';
    echo '<div class="admin-profile">';
    echo '<a class="topbar-alert'.($pendingRec>0?'':' is-hidden').'" href="'.admin_to('payments',['review'=>'REPORTED']).'" data-admin-alert-pill>🔔 Chờ đối soát: <strong data-admin-pending-rec-text>'.$pendingRec.'</strong></a>';
    echo '<span>Quản trị viên: '.h($admin['username']).'</span>';
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
function stat_card(string $label,string $value,string $note='',string $tone=''): void
{
    echo '<article class="stat-card surface'.($tone!==''?' stat-card--'.h($tone):'').'"><p>'.h($label).'</p><strong>'.h($value).'</strong><span>'.h($note).'</span></article>';
}
function render_revenue_chart(string $range, string $pageTarget, ?array $report = null): void
{
    $report = $report ?? revenue_report_data($range);
    $range = $report['range'];
    $series = $report['series'];
    echo '<div class="chart-head"><h2>Biểu đồ doanh thu theo ngày thực nhận</h2><div class="filter-tabs">';
    foreach (['7d'=>'7 ngày qua','30d'=>'30 ngày qua','12m'=>'12 tháng qua'] as $k=>$label) {
        echo '<a class="'.($range===$k?'active':'').'" href="'.admin_to($pageTarget,['range'=>$k]).'">'.h($label).'</a>';
    }
    echo '</div></div>';
    echo '<p class="hint">Giá trị đơn phát sinh tính theo ngày đặt (<code>created_at</code>). Tiền thực nhận tính đúng theo ngày đối soát BANK (<code>payment_verified_at</code>) và ngày xác nhận thu COD (<code>cod_paid_at</code>). Đây là doanh thu gộp đã xác nhận thu, không phải lợi nhuận và chưa phản ánh hoàn tiền nếu có.</p>';
    if (!$series) {
        echo '<p class="hint">Chưa có dữ liệu trong khoảng thời gian này.</p>';
        return;
    }
    $maxVal = 1;
    foreach ($series as $rowItem) {
        $maxVal = max($maxVal, (int)$rowItem['ordered_total'], (int)$rowItem['paid_total']);
    }
    echo '<div class="chart-legend"><span><i class="legend-dot legend-dot--paid"></i> Tiền thực nhận (theo ngày xác nhận PAID)</span><span><i class="legend-dot legend-dot--gross"></i> Giá trị đơn phát sinh (theo ngày đặt)</span></div>';
    echo '<div class="bar-chart">';
    foreach ($series as $rowItem) {
        $paidPct = round(((int)$rowItem['paid_total'] / $maxVal) * 100, 1);
        $grossPct = round(((int)$rowItem['ordered_total'] / $maxVal) * 100, 1);
        $shortLabel = strlen((string)$rowItem['bucket']) === 10 ? substr((string)$rowItem['bucket'], 5) : (string)$rowItem['bucket'];
        $tip = $rowItem['bucket']
            . ' · Thực nhận: ' . money((int)$rowItem['paid_total'])
            . ' (BANK: ' . money((int)$rowItem['bank_paid']) . ', COD: ' . money((int)$rowItem['cod_paid']) . ')'
            . ' · Đơn phát sinh: ' . money((int)$rowItem['ordered_total']);
        echo '<div class="bar-col" title="'.h($tip).'">';
        echo '<div class="bar-pair">';
        echo '<div class="bar-fill bar-fill--gross" style="height:'.($rowItem['ordered_total']>0?max(2, $grossPct):0).'%"></div>';
        echo '<div class="bar-fill bar-fill--paid" style="height:'.($rowItem['paid_total']>0?max(3, $paidPct):0).'%"></div>';
        echo '</div><small>'.h($shortLabel).'</small></div>';
    }
    echo '</div>';
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
    $kpi = row(
        "SELECT COUNT(*) AS total_orders,
                SUM(order_status='PENDING') AS pending_orders,
                SUM(order_status='SHIPPING') AS shipping_orders,
                SUM(order_status='COMPLETED') AS completed_orders,
                SUM(payment_method='BANK' AND payment_review_status='REPORTED' AND payment_status='UNPAID' AND order_status<>'CANCELLED') AS pending_reconciliation,
                COALESCE(SUM(CASE WHEN payment_status='PAID' THEN total_amount ELSE 0 END),0) AS paid_revenue,
                COALESCE(SUM(CASE WHEN payment_status='PAID' AND payment_method='BANK' THEN total_amount ELSE 0 END),0) AS bank_paid_revenue,
                COALESCE(SUM(CASE WHEN payment_status='PAID' AND payment_method='COD' THEN total_amount ELSE 0 END),0) AS cod_paid_revenue,
                COALESCE(SUM(CASE WHEN payment_status='UNPAID' AND order_status<>'CANCELLED' THEN total_amount ELSE 0 END),0) AS unpaid_value,
                COALESCE(SUM(CASE WHEN payment_status='UNPAID' AND payment_method='BANK' AND order_status<>'CANCELLED' THEN total_amount ELSE 0 END),0) AS bank_unpaid_value,
                COALESCE(SUM(CASE WHEN payment_status='UNPAID' AND payment_method='COD' AND order_status<>'CANCELLED' THEN total_amount ELSE 0 END),0) AS cod_unpaid_value
         FROM tbl_orders"
    );
    $lowStock=(int)(row('SELECT COUNT(*) AS n FROM tbl_sanpham WHERE soluong<=5')['n']??0);
    $customersCount=(int)(row('SELECT COUNT(*) AS n FROM tbl_dangky')['n']??0);
    $pendingRec=(int)($kpi['pending_reconciliation']??0);
    $pendingCancellationCount=(int)(row("SELECT COUNT(*) AS n FROM tbl_order_cancel_requests WHERE status='PENDING'")['n']??0);
    admin_title('Tổng quan hệ thống','Theo dõi vận hành, đối soát thanh toán và doanh thu thực nhận.');
    if ($pendingCancellationCount>0) {
        echo '<div class="alert info admin-cta-banner"><span>📩 Có <strong>'.$pendingCancellationCount.'</strong> yêu cầu hủy đơn hàng từ khách cần Admin xem xét.</span><a class="button" href="'.admin_to('orders',['cancel'=>'pending']).'">Duyệt yêu cầu hủy →</a></div>';
    }
    if ($pendingRec>0) {
        echo '<div class="alert info admin-cta-banner"><span>🔔 Hiện có <strong>'.$pendingRec.'</strong> đơn chuyển khoản VietQR khách đã báo chuyển tiền và đang chờ Admin đối soát sao kê MB Bank.</span><a class="button" href="'.admin_to('payments',['review'=>'REPORTED']).'">Đối soát ngay →</a></div>';
    }
    echo '<div class="stats stats--dashboard">';
    stat_card('Tổng số đơn hàng',(string)(int)($kpi['total_orders']??0),'Toàn bộ đơn ghi nhận');
    stat_card('Đơn mới cần xử lý',(string)(int)($kpi['pending_orders']??0),'Trạng thái Đã đặt hàng','warning');
    stat_card('Chờ đối soát CK',(string)$pendingRec,'Khách đã báo chuyển khoản MB Bank',$pendingRec>0?'danger':'');
    stat_card('Đơn đang giao',(string)(int)($kpi['shipping_orders']??0),'Đã bàn giao vận chuyển','info');
    stat_card('Đơn hoàn thành',(string)(int)($kpi['completed_orders']??0),'Đã giao thành công','success');
    stat_card('Doanh thu thực nhận (PAID)',money((int)($kpi['paid_revenue']??0)),'BANK: '.money((int)($kpi['bank_paid_revenue']??0)).' · COD: '.money((int)($kpi['cod_paid_revenue']??0)),'success');
    stat_card('Đơn đặt chưa thanh toán',money((int)($kpi['unpaid_value']??0)),'BANK chờ TT: '.money((int)($kpi['bank_unpaid_value']??0)).' · COD chưa thu: '.money((int)($kpi['cod_unpaid_value']??0)),'warning');
    stat_card('Sản phẩm sắp hết hàng',(string)$lowStock,'Tồn kho ≤ 5 sản phẩm',$lowStock>0?'danger':'');
    stat_card('Khách hàng',(string)$customersCount,'Tài khoản đã đăng ký');
    echo '</div>';

    $range=(string)($_GET['range']??'30d');
    echo '<div class="surface chart-panel">';
    render_revenue_chart($range,'dashboard');
    echo '</div>';

    echo '<div class="admin-grid admin-grid--spaced"><div class="surface"><h2>Đơn hàng gần đây</h2>';
    admin_table_start(['Mã đơn','Khách hàng','Tổng tiền','Thanh toán','Trạng thái']);
    foreach (rows('SELECT id,order_code,customer_name,total_amount,payment_method,payment_status,payment_review_status,order_status FROM tbl_orders ORDER BY id DESC LIMIT 10') as $o) {
        $payState=payment_state_summary($o);
        echo '<tr><td><a class="link-blue" href="'.admin_to('order',['id'=>$o['id']]).'">'.h($o['order_code']).'</a></td><td>'.h($o['customer_name']).'</td><td><strong>'.money((int)$o['total_amount']).'</strong></td>';
        echo '<td><span class="status-pill status-pill--'.h($payState['tone']).'">'.h($payState['label']).'</span></td>';
        echo '<td><span class="status-pill status-pill--'.h(order_status_tone((string)$o['order_status'])).'">'.h(order_status_label((string)$o['order_status'])).'</span></td></tr>';
    }
    admin_table_end();
    echo '</div><div class="surface"><h2>Hàng chờ đối soát MB Bank</h2>';
    $recQueue=rows("SELECT id,order_code,customer_name,total_amount,payment_reported_at FROM tbl_orders WHERE payment_method='BANK' AND payment_review_status='REPORTED' AND payment_status='UNPAID' AND order_status<>'CANCELLED' ORDER BY payment_reported_at ASC LIMIT 8");
    if (!$recQueue) {
        echo '<p class="hint">Không có đơn nào đang chờ đối soát.</p>';
    } else {
        foreach ($recQueue as $rq) {
            echo '<div class="queue-item"><div><a class="link-blue" href="'.admin_to('order',['id'=>$rq['id']]).'"><strong>'.h($rq['order_code']).'</strong></a><p>'.h($rq['customer_name']).' · '.money((int)$rq['total_amount']).'</p><small>Khách báo lúc: '.h((string)$rq['payment_reported_at']).'</small></div><a class="button button--light" href="'.admin_to('order',['id'=>$rq['id']]).'">Đối soát</a></div>';
        }
    }
    echo '<hr><p class="hint">Lưu ý: Đơn BANK chưa xác nhận thanh toán KHÔNG được tính vào doanh thu thực nhận. Chỉ xác nhận PAID sau khi kiểm tra sao kê MB Bank.</p></div></div>';
}
elseif ($page==='products') {
    admin_title('Quản lý sản phẩm','Danh sách sản phẩm thật, giá dung lượng SQL và tồn kho chia sẻ theo sản phẩm.',
        '<a class="button" href="'.admin_to('product_form').'">＋ Thêm sản phẩm</a>');
    $q=trim((string)($_GET['q']??''));
    $params=[];$filter='';
    if ($q!=='') {$filter=' WHERE p.tensanpham LIKE ? OR p.masp LIKE ?';$params=['%'.mb_substr($q,0,80).'%','%'.mb_substr($q,0,80).'%'];}
    $list=rows('SELECT p.id_sanpham,p.tensanpham,p.masp,p.giasp,p.soluong,p.tinhtrang,p.hinhanh,c.tendanhmuc
                FROM tbl_sanpham p JOIN tbl_danhmuc c ON p.id_danhmuc=c.id_danhmuc '.$filter.' ORDER BY p.id_sanpham DESC LIMIT 100',$params);
    echo '<form method="get" class="admin-search"><input type="hidden" name="page" value="products"><input class="field" name="q" maxlength="80" value="'.h($q).'" placeholder="Tìm theo tên hoặc SKU"><button class="button">Tìm kiếm</button></form>';
    admin_table_start(['Sản phẩm','SKU','Danh mục','Giá bán / Dung lượng','Tồn kho (dùng chung)','Trạng thái','Thao tác']);
    foreach ($list as $p) {
        $priceHtml='<strong>'.money((int)$p['giasp']).'</strong>';
        if (is_phone_product($p)) {
            $vars=available_variants((int)$p['id_sanpham']);
            if ($vars) {
                $parts=[];
                foreach ($vars as $vc=>$vp) $parts[]=$vc.': '.money($vp);
                $priceHtml.='<div class="hint">'.h(implode(' · ',$parts)).'</div>';
            }
        }
        echo '<tr><td><div class="table-product"><img src="'.h(safe_image($p['hinhanh'])).'" alt=""><strong>'.h($p['tensanpham']).'</strong></div></td>';
        echo '<td>'.h($p['masp']).'</td><td>'.h($p['tendanhmuc']).'</td><td>'.$priceHtml.'</td><td>'.(int)$p['soluong'].'</td>';
        echo '<td><span class="status-pill status-pill--'.((int)$p['tinhtrang']===1?'success':'neutral').'">'.((int)$p['tinhtrang']===1?'Đang bán':'Đã ẩn').'</span></td><td><a href="'.admin_to('product_form',['id'=>$p['id_sanpham']]).'" class="link-blue">Chỉnh sửa</a></td></tr>';
    }
    admin_table_end();
}
elseif ($page==='product_form') {
    $id=as_int($_GET['id']??0,0,2147483647);
    $p=$id?row('SELECT * FROM tbl_sanpham WHERE id_sanpham=?',[$id]):[];
    if ($id && !$p) throw new DomainException('Không tìm thấy sản phẩm.');
    admin_title($id?'Cập nhật sản phẩm':'Thêm sản phẩm','Giá từng dung lượng đọc từ tbl_product_variants; tồn kho dùng chung tại tbl_sanpham.soluong.');
    echo '<form class="surface admin-form" method="post" enctype="multipart/form-data">'.csrf_field().'<input type="hidden" name="action" value="save_product"><input type="hidden" name="id" value="'.$id.'">';
    echo '<div class="form-grid"><div><label class="field-group">Tên sản phẩm <input class="field" name="name" maxlength="250" required value="'.h($p['tensanpham']??'').'"></label>';
    echo '<label class="field-group">Mã SKU <input class="field" name="sku" maxlength="100" required value="'.h($p['masp']??'').'"></label>';
    echo '<label class="field-group">Danh mục <select class="field" name="category">';
    foreach (rows('SELECT id_danhmuc,tendanhmuc FROM tbl_danhmuc ORDER BY thutu,id_danhmuc') as $cat) {
        echo '<option value="'.(int)$cat['id_danhmuc'].'"'.((int)($p['id_danhmuc']??0)===(int)$cat['id_danhmuc']?' selected':'').'>'.h($cat['tendanhmuc']).'</option>';
    }
    echo '</select></label><label class="field-group">Mô tả ngắn <textarea class="field" name="summary" maxlength="10000">'.h($p['tomtat']??'').'</textarea></label>';
    echo '<label class="field-group">Nội dung <textarea class="field" name="description" rows="9" maxlength="200000">'.h(strip_tags($p['noidung']??'')).'</textarea></label></div><div>';
    echo '<label class="field-group">Giá gốc / 128GB (VND) <input class="field" type="number" min="1" max="2000000000" name="price" required value="'.h($p['giasp']??'').'"></label>';
    $dbVariants=$id?rows('SELECT variant_code,price FROM tbl_product_variants WHERE product_id=? AND is_active=1',[$id]):[];
    $variantValues=[];
    foreach ($dbVariants as $v) $variantValues[(string)$v['variant_code']]=(int)$v['price'];
    echo '<div class="variant-admin"><h3>Giá từng dung lượng điện thoại</h3><p class="hint">Chỉ áp dụng cho danh mục Điện thoại. Dung lượng để trống sẽ không mở bán. Tồn kho hiện được chia sẻ chung giữa các dung lượng tại trường Tồn kho bên dưới.</p>';
    foreach (['256GB','512GB','1TB'] as $capacity) {
        echo '<label class="field-group">'.h($capacity).' (VND) <input class="field" name="variant_'.h($capacity).'" type="number" min="1" max="2000000000" placeholder="Để trống nếu chưa mở bán" value="'.h($variantValues[$capacity]??'').'"></label>';
    }
    echo '</div>';
    if ($id===0) {
        echo '<label class="field-group">Tồn kho ban đầu (dùng chung) <input class="field" type="number" min="0" max="1000000" name="stock" required value="0"></label>';
    } else {
        echo '<div class="payment-status-note"><strong>Tồn kho hiện tại (dùng chung): '.(int)$p['soluong'].'</strong><p class="hint">Để tránh ghi đè tồn kho khi có khách đặt hàng đồng thời, form sửa thông tin sản phẩm không sửa trực tiếp tồn kho. Hãy dùng mục "Điều chỉnh tồn kho" bên dưới.</p></div>';
    }
    echo '<label class="field-group">Trạng thái <select class="field" name="visible"><option value="1"'.((int)($p['tinhtrang']??1)===1?' selected':'').'>Đang bán</option><option value="0"'.((int)($p['tinhtrang']??1)===0?' selected':'').'>Tạm ẩn</option></select></label>';
    if (!empty($p['hinhanh'])) echo '<div class="image-preview"><img src="'.h(safe_image($p['hinhanh'])).'" alt="Ảnh hiện tại"></div>';
    echo '<label class="field-group">Ảnh (JPG, PNG, WebP ≤ 3MB) <input class="field" type="file" name="photo" accept="image/jpeg,image/png,image/webp"></label>';
    echo '<button class="button full" type="submit">Lưu thông tin sản phẩm</button></div></div></form>';
    if ($id) {
        echo '<div class="admin-grid admin-grid--spaced"><section class="surface"><h2>Điều chỉnh tồn kho an toàn</h2>';
        echo '<p class="hint">Sử dụng khóa transaction và kiểm tra tồn kho hiện tại để chống mất cập nhật (lost update) khi có đơn hàng đồng thời. Nhập số dương (+5) để nhập thêm hoặc số âm (-2) để xuất/giảm.</p>';
        echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="adjust_stock"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="return_to" value="product_form"><input type="hidden" name="expected_stock" value="'.(int)$p['soluong'].'">';
        echo '<label class="field-group">Số lượng thay đổi (VD: 5 hoặc -2)<input class="field" type="number" name="delta" min="-1000000" max="1000000" required placeholder="Nhập số nguyên khác 0"></label>';
        echo '<label class="field-group">Lý do điều chỉnh (bắt buộc)<input class="field" name="reason" minlength="3" maxlength="300" required placeholder="VD: Nhập lô hàng mới, kiểm kê thực tế..."></label>';
        echo '<button type="submit" class="button">Xác nhận điều chỉnh tồn kho</button></form>';
        echo '<form method="post" class="admin-danger">'.csrf_field().'<input type="hidden" name="action" value="hide_product"><input type="hidden" name="id" value="'.$id.'"><button class="button button--danger" data-confirm="Ẩn sản phẩm khỏi website?">Ẩn sản phẩm</button></form></section>';
        echo '<section class="surface"><h2>Nhật ký biến động kho gần đây</h2>';
        $moves=rows('SELECT movement_type,delta,stock_before,stock_after,reason,actor_type,actor_id,created_at FROM tbl_inventory_movements WHERE product_id=? ORDER BY id DESC LIMIT 15',[$id]);
        if (!$moves) {
            echo '<p class="hint">Chưa có biến động kho được ghi nhận.</p>';
        } else {
            foreach ($moves as $mv) {
                $sign=(int)$mv['delta']>0?'+':'';
                echo '<div class="payment-event"><strong>'.h($mv['movement_type']).' ('.$sign.(int)$mv['delta'].')</strong> · '.(int)$mv['stock_before'].' → <strong>'.(int)$mv['stock_after'].'</strong> · '.h($mv['actor_type']).' #'.(int)$mv['actor_id'].' · '.h($mv['created_at']);
                if (trim((string)$mv['reason'])!=='') echo '<p>'.h($mv['reason']).'</p>';
                echo '</div>';
            }
        }
        echo '</section></div>';
    }
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
elseif ($page==='payments') {
    admin_title('Quản lý thanh toán & đối soát','Đối soát thủ công chuyển khoản MB Bank (VietQR) và theo dõi thu tiền COD.');
    $review=(string)($_GET['review']??'');
    $q=trim((string)($_GET['q']??''));
    $curPage=max(1,min(200,(int)($_GET['p']??1)));
    $perPage=20;$offset=($curPage-1)*$perPage;
    $where=[];$params=[];
    switch ($review) {
        case 'REPORTED':
            $where[]="payment_method='BANK' AND payment_review_status='REPORTED' AND payment_status='UNPAID' AND order_status<>'CANCELLED'";
            break;
        case 'NONE':
            $where[]="payment_method='BANK' AND payment_review_status='NONE' AND payment_status='UNPAID' AND order_status<>'CANCELLED'";
            break;
        case 'REJECTED':
            $where[]="payment_method='BANK' AND payment_review_status='REJECTED' AND payment_status='UNPAID' AND order_status<>'CANCELLED'";
            break;
        case 'VERIFIED':
            $where[]="payment_method='BANK' AND payment_status='PAID'";
            break;
        case 'OVERDUE':
            $where[]="payment_method='BANK' AND payment_status='UNPAID' AND order_status='PENDING' AND created_at < NOW() - INTERVAL 24 HOUR";
            break;
        case 'COD_UNPAID':
            $where[]="payment_method='COD' AND payment_status='UNPAID' AND order_status<>'CANCELLED'";
            break;
        case 'COD_PAID':
            $where[]="payment_method='COD' AND payment_status='PAID'";
            break;
        default:
            $review='';
            $where[]="payment_method='BANK'";
            break;
    }
    if ($q!=='') {
        $like='%'.mb_substr($q,0,80).'%';
        $where[]='(order_code LIKE ? OR customer_name LIKE ? OR phone LIKE ? OR bank_reference LIKE ?)';
        array_push($params,$like,$like,$like,$like);
    }
    $whereSql=' WHERE '.implode(' AND ',$where);
    $totalRows=(int)(row('SELECT COUNT(*) AS n FROM tbl_orders'.$whereSql,$params)['n']??0);
    $orders=rows('SELECT id,order_code,customer_name,phone,created_at,payment_reported_at,total_amount,payment_method,payment_status,payment_review_status,bank_reference,order_status FROM tbl_orders'.$whereSql.' ORDER BY id DESC LIMIT '.$perPage.' OFFSET '.$offset,$params);

    echo '<div class="filter-tabs">';
    $tabs=[
        ''=>'Tất cả BANK',
        'REPORTED'=>'Khách báo đã CK / Chờ đối soát',
        'NONE'=>'Chưa báo chuyển khoản',
        'REJECTED'=>'Cần kiểm tra lại',
        'VERIFIED'=>'Đã xác nhận thanh toán',
        'OVERDUE'=>'Quá hạn >24h chưa TT',
        'COD_UNPAID'=>'COD chưa thu tiền',
        'COD_PAID'=>'COD đã xác nhận thu',
    ];
    foreach ($tabs as $k=>$label) {
        echo '<a class="'.($review===$k?'active':'').'" href="'.admin_to('payments',array_filter(['review'=>$k,'q'=>$q],static fn($x)=>$x!=='')).'">'.h($label).'</a>';
    }
    echo '</div>';
    echo '<form method="get" class="admin-search"><input type="hidden" name="page" value="payments"><input type="hidden" name="review" value="'.h($review).'"><input class="field" name="q" maxlength="80" value="'.h($q).'" placeholder="Tìm mã đơn, khách hàng, SĐT, mã giao dịch"><button class="button">Tìm</button></form>';
    admin_table_start(['Mã đơn','Khách hàng','Số tiền','Phương thức','Trạng thái thanh toán','Đối soát / Mã GD','Trạng thái đơn','Thao tác']);
    foreach ($orders as $o) {
        $payState=payment_state_summary($o);
        $meta=$o['payment_method']==='BANK'
            ? ($o['bank_reference']?'Mã GD: '.h($o['bank_reference']):($o['payment_reported_at']?'Báo lúc '.h($o['payment_reported_at']):h(payment_review_label((string)$o['payment_review_status']))))
            : ($o['payment_status']==='PAID'?'Đã thu tiền COD':'Thu khi giao hàng');
        echo '<tr><td><a class="link-blue" href="'.admin_to('order',['id'=>$o['id']]).'"><strong>'.h($o['order_code']).'</strong></a><br><small>'.h($o['created_at']).'</small></td>';
        echo '<td>'.h($o['customer_name']).'<br><small>'.h($o['phone']).'</small></td>';
        echo '<td><strong>'.money((int)$o['total_amount']).'</strong></td>';
        echo '<td>'.h($o['payment_method']).'</td>';
        echo '<td><span class="status-pill status-pill--'.h($payState['tone']).'">'.h($payState['label']).'</span></td>';
        echo '<td><small>'.$meta.'</small></td>';
        echo '<td><span class="status-pill status-pill--'.h(order_status_tone((string)$o['order_status'])).'">'.h(order_status_label((string)$o['order_status'])).'</span></td>';
        echo '<td><a class="button button--light" href="'.admin_to('order',['id'=>$o['id']]).'">Chi tiết</a></td></tr>';
    }
    admin_table_end();
    if (!$orders) echo '<div class="surface"><p class="hint">Không có giao dịch nào khớp bộ lọc hiện tại.</p></div>';
    $totalPages=(int)ceil($totalRows/$perPage);
    if ($totalPages>1) {
        echo '<nav class="pagination">';
        for ($p=1;$p<=min($totalPages,20);$p++) echo '<a class="'.($p===$curPage?'active':'').'" href="'.admin_to('payments',array_filter(['review'=>$review,'q'=>$q,'p'=>$p])).'">'.$p.'</a>';
        echo '</nav>';
    }
}
elseif ($page==='orders') {
    admin_title('Quản lý đơn hàng','Tìm kiếm, lọc và cập nhật tiến độ xử lý đơn hàng.');
    $pendingCancelCount=(int)(row("SELECT COUNT(*) AS n FROM tbl_order_cancel_requests WHERE status='PENDING'")['n']??0);
    if ($pendingCancelCount>0) echo '<div class="alert info">📩 Có <strong>'.$pendingCancelCount.'</strong> yêu cầu hủy đang chờ duyệt. <a class="link-blue" href="'.admin_to('orders',['cancel'=>'pending']).'">Xem yêu cầu hủy →</a></div>';
    $status=(string)($_GET['status']??'');
    $method=(string)($_GET['method']??'');
    $q=trim((string)($_GET['q']??''));
    $from=trim((string)($_GET['from']??''));
    $to=trim((string)($_GET['to']??''));
    $curPage=max(1,min(200,(int)($_GET['p']??1)));
    $perPage=20;$offset=($curPage-1)*$perPage;
    $where=[];$params=[];
    $cancelFilter=(string)($_GET['cancel']??'');
    if ($cancelFilter==='pending') $where[]="EXISTS (SELECT 1 FROM tbl_order_cancel_requests cr WHERE cr.order_id=tbl_orders.id AND cr.status='PENDING')";
    if (in_array($status,['PENDING','CONFIRMED','PREPARING','SHIPPING','COMPLETED','CANCELLED'],true)) {
        $where[]='order_status=?';$params[]=$status;
    } else $status='';
    if (in_array($method,['BANK','COD'],true)) {
        $where[]='payment_method=?';$params[]=$method;
    } else $method='';
    if ($q!=='') {
        $like='%'.mb_substr($q,0,80).'%';
        $where[]='(order_code LIKE ? OR customer_name LIKE ? OR phone LIKE ?)';
        array_push($params,$like,$like,$like);
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)) { $where[]='created_at >= ?'; $params[]=$from.' 00:00:00'; } else $from='';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)) { $where[]='created_at <= ?'; $params[]=$to.' 23:59:59'; } else $to='';
    $whereSql=$where?' WHERE '.implode(' AND ',$where):'';
    $totalRows=(int)(row('SELECT COUNT(*) AS n FROM tbl_orders'.$whereSql,$params)['n']??0);
    $orders=rows("SELECT id,order_code,customer_name,phone,created_at,total_amount,payment_method,payment_status,payment_review_status,order_status,tracking_code,
                         (SELECT COUNT(*) FROM tbl_order_cancel_requests cr WHERE cr.order_id=tbl_orders.id AND cr.status='PENDING') AS cancel_pending
                   FROM tbl_orders".$whereSql.' ORDER BY id DESC LIMIT '.$perPage.' OFFSET '.$offset,$params);

    echo '<div class="filter-tabs">';
    foreach ([''=>'Tất cả','PENDING'=>'Đã đặt hàng','CONFIRMED'=>'Đã xác nhận','PREPARING'=>'Đang chuẩn bị','SHIPPING'=>'Đang giao','COMPLETED'=>'Đã giao','CANCELLED'=>'Đã hủy'] as $k=>$label) {
        echo '<a class="'.($status===$k?'active':'').'" href="'.admin_to('orders',array_filter(['status'=>$k,'method'=>$method,'q'=>$q,'from'=>$from,'to'=>$to],static fn($x)=>$x!=='')).'">'.h($label).'</a>';
    }
    echo '</div>';
    echo '<form method="get" class="surface admin-filter-bar"><input type="hidden" name="page" value="orders"><input type="hidden" name="status" value="'.h($status).'">';
    echo '<input class="field" name="q" maxlength="80" value="'.h($q).'" placeholder="Mã đơn, tên khách, SĐT">';
    echo '<select class="field" name="method"><option value="">Mọi phương thức</option><option value="BANK"'.selected($method,'BANK').'>VietQR / BANK</option><option value="COD"'.selected($method,'COD').'>COD</option></select>';
    echo '<input class="field" type="date" name="from" value="'.h($from).'" aria-label="Từ ngày">';
    echo '<input class="field" type="date" name="to" value="'.h($to).'" aria-label="Đến ngày">';
    echo '<button class="button" type="submit">Lọc đơn</button></form>';

    admin_table_start(['Mã đơn','Khách hàng','Ngày đặt','Tổng tiền','Thanh toán','Trạng thái đơn','Vận đơn']);
    foreach ($orders as $o) {
        $payState=payment_state_summary($o);
        echo '<tr><td><a class="link-blue" href="'.admin_to('order',['id'=>$o['id']]).'"><strong>'.h($o['order_code']).'</strong></a>'.((int)$o['cancel_pending']>0?'<br><span class="status-pill status-pill--warning">📩 Chờ duyệt hủy</span>':'').'</td>';
        echo '<td>'.h($o['customer_name']).'<br><small>'.h($o['phone']).'</small></td>';
        echo '<td>'.h($o['created_at']).'</td>';
        echo '<td><strong>'.money((int)$o['total_amount']).'</strong></td>';
        echo '<td><span class="status-pill status-pill--'.h($payState['tone']).'">'.h($payState['label']).'</span></td>';
        echo '<td><span class="status-pill status-pill--'.h(order_status_tone((string)$o['order_status'])).'">'.h(order_status_label((string)$o['order_status'])).'</span></td>';
        echo '<td><small>'.($o['tracking_code']!==''?h($o['tracking_code']):'—').'</small></td></tr>';
    }
    admin_table_end();
    if (!$orders) echo '<div class="surface"><p class="hint">Không có đơn hàng nào khớp bộ lọc.</p></div>';
    $totalPages=(int)ceil($totalRows/$perPage);
    if ($totalPages>1) {
        echo '<nav class="pagination">';
        for ($p=1;$p<=min($totalPages,20);$p++) echo '<a class="'.($p===$curPage?'active':'').'" href="'.admin_to('orders',array_filter(['status'=>$status,'method'=>$method,'q'=>$q,'from'=>$from,'to'=>$to,'p'=>$p])).'">'.$p.'</a>';
        echo '</nav>';
    }
    $legacyOrders=rows('SELECT c.id_cart,c.code_cart,c.cart_status,c.cart_date,c.cart_payment,c.tongtien,u.tenkhachhang,u.dienthoai
                        FROM tbl_cart c LEFT JOIN tbl_dangky u ON u.id_dangky=c.id_khachhang
                        ORDER BY c.id_cart DESC LIMIT 20');
    if ($legacyOrders) {
        echo '<section class="surface legacy-orders"><h2>Đơn hàng từ hệ thống trước</h2><p class="hint">Lịch sử trong tbl_cart được giữ nguyên; trạng thái cũ chỉ thể hiện đã xem hay chưa xem.</p>';
        admin_table_start(['Mã đơn','Khách hàng','Ngày đặt','Tổng tiền','Thanh toán','Trạng thái']);
        foreach ($legacyOrders as $old) {
            echo '<tr><td><a class="link-blue" href="'.admin_to('legacy_order',['id'=>$old['id_cart']]).'">'.h($old['code_cart']).'</a></td><td>'.h($old['tenkhachhang']??'Khách hàng cũ').'<br><small>'.h($old['dienthoai']??'').'</small></td><td>'.h($old['cart_date']).'</td><td>'.money((int)$old['tongtien']).'</td><td>'.h($old['cart_payment']).'</td><td>'.((int)$old['cart_status']===1?'Chưa xem':'Đã xem').'</td></tr>';
        }
        admin_table_end();
        echo '</section>';
    }
}
elseif ($page==='order') {
    $id=as_int($_GET['id']??0);
    $order=row('SELECT *, (created_at < NOW() - INTERVAL 24 HOUR) AS past_deadline FROM tbl_orders WHERE id=?',[$id]);
    if (!$order) throw new DomainException('Đơn hàng không tồn tại.');
    $payState=payment_state_summary($order);
    $curStatus=(string)$order['order_status'];
    $cancelRequest=row('SELECT * FROM tbl_order_cancel_requests WHERE order_id=?',[$id]);
    admin_title('Đơn hàng '. $order['order_code'],'Quản lý đối soát thanh toán, tiến độ xử lý, vận đơn và lịch sử thay đổi.','<a class="button button--light" href="'.admin_to('orders').'">← Danh sách đơn</a>');

    echo '<div class="admin-grid"><div class="surface"><h2>Thông tin đơn hàng</h2>';
    echo '<p>Khách hàng: <strong>'.h($order['customer_name']).'</strong> (<a class="link-blue" href="'.admin_to('customer',['id'=>(int)$order['user_id']]).'">Xem hồ sơ khách</a>)</p>';
    echo '<p>Số điện thoại: <strong>'.h($order['phone']).'</strong></p>';
    echo '<p>Địa chỉ giao hàng: '.h($order['delivery_address']).'</p>';
    if (trim((string)$order['note'])!=='') echo '<p>Ghi chú khách khi đặt: '.h($order['note']).'</p>';
    echo '<p>Đặt lúc: '.h($order['created_at']).' · Cập nhật gần nhất: '.h($order['updated_at']).'</p>';
    if (!empty($order['shipped_at'])) echo '<p>Bàn giao vận chuyển lúc: '.h($order['shipped_at']).'</p>';
    if (!empty($order['delivered_at'])) echo '<p>Giao thành công lúc: '.h($order['delivered_at']).'</p>';
    if (trim((string)($order['cancel_reason']??''))!=='') echo '<p class="payment-alert"><strong>Lý do hủy:</strong> '.h($order['cancel_reason']).'</p>';

    echo '<hr><h3>Sản phẩm</h3>';
    foreach (rows('SELECT product_name,variant_label,quantity,unit_price FROM tbl_order_items WHERE order_id=?',[$id]) as $item) {
        echo '<div class="summary-line"><span>'.h($item['product_name']).($item['variant_label']!=='BASE'?' · '.h($item['variant_label']):'').' × '.(int)$item['quantity'].'</span><strong>'.money((int)$item['unit_price']*(int)$item['quantity']).'</strong></div>';
    }
    echo '<hr><h2>Tổng tiền: '.money((int)$order['total_amount']).'</h2>';

    // Shipping & notes editor (with per-submission event_key for safe retry idempotency)
    echo '<hr><h3>Thông tin vận chuyển & Ghi chú</h3>';
    echo '<form method="post" class="admin-subform">'.csrf_field().'<input type="hidden" name="action" value="save_order_notes"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="event_key" value="'.h(bin2hex(random_bytes(12))).'">';
    echo '<div class="form-grid-2"><label class="field-group">Đơn vị vận chuyển (thực tế)<input class="field" name="shipping_carrier" maxlength="100" placeholder="VD: Giao Hàng Nhanh, Viettel Post..." value="'.h($order['shipping_carrier']??'').'"></label>';
    echo '<label class="field-group">Mã vận đơn thực tế (không tự sinh giả)<input class="field" name="tracking_code" maxlength="100" placeholder="Nhập mã vận đơn thật nếu có" value="'.h($order['tracking_code']??'').'"></label></div>';
    echo '<label class="field-group">Ghi chú gửi khách (hiển thị trên trang đơn của khách)<textarea class="field" name="customer_note" rows="2" maxlength="500">'.h($order['customer_note']??'').'</textarea></label>';
    echo '<label class="field-group">Ghi chú nội bộ Admin (không hiển thị cho khách)<textarea class="field" name="admin_note" rows="2" maxlength="500">'.h($order['admin_note']??'').'</textarea></label>';
    echo '<button type="submit" class="button button--light">Lưu ghi chú & vận chuyển</button></form>';

    // Full order status history
    echo '<hr><h3>Lịch sử trạng thái đơn hàng</h3>';
    $hist=rows('SELECT from_status,to_status,actor_type,actor_id,note,is_public,created_at FROM tbl_order_status_history WHERE order_id=? ORDER BY id DESC LIMIT 40',[$id]);
    if (!$hist) {
        echo '<p class="hint">Chưa có lịch sử trạng thái.</p>';
    } else {
        foreach ($hist as $hRow) {
            $tag=(int)$hRow['is_public']===1?'Công khai':'Nội bộ';
            $fromTxt=($hRow['from_status'] && $hRow['from_status']!==$hRow['to_status'])?order_status_label((string)$hRow['from_status']).' → ':'';
            echo '<div class="payment-event"><strong>'.h($fromTxt.order_status_label((string)$hRow['to_status'])).'</strong> · <span class="badge">'.h($tag).'</span> · '.h($hRow['actor_type']).' #'.(int)$hRow['actor_id'].' · '.h($hRow['created_at']);
            if (trim((string)$hRow['note'])!=='') echo '<p>'.h($hRow['note']).'</p>';
            echo '</div>';
        }
    }
    echo '</div>';

    // Right column: Order status transition & Payment reconciliation
    echo '<div class="surface"><h2>Trạng thái & Thanh toán</h2>';
    echo '<p>Trạng thái đơn: <span class="status-pill status-pill--'.h(order_status_tone($curStatus)).'">'.h(order_status_label($curStatus)).'</span></p>';
    echo '<p>Thanh toán: <strong>'.h(payment_method_label((string)$order['payment_method'])).'</strong> · <span class="status-pill status-pill--'.h($payState['tone']).'">'.h($payState['label']).'</span></p>';
    if ($cancelRequest) {
        echo '<section class="payment-admin-form"><h3>📩 Yêu cầu hủy từ khách hàng</h3>';
        echo '<p>Trạng thái: <strong>'.h($cancelRequest['status']).'</strong> · Gửi lúc '.h($cancelRequest['created_at']).'</p>';
        echo '<p><strong>Lý do:</strong> '.h($cancelRequest['reason']).'</p>';
        if ($cancelRequest['status']==='PENDING') {
            if (in_array($curStatus,['PENDING','CONFIRMED','PREPARING'],true)
                && $order['payment_status']!=='PAID' && $order['payment_review_status']!=='REPORTED') {
                echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="order_status"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="status" value="CANCELLED">';
                echo '<input type="hidden" name="cancel_reason" value="'.h($cancelRequest['reason']).'">';
                echo '<button type="submit" class="button button--danger full" data-confirm="Chấp thuận hủy đơn và hoàn tồn kho đúng một lần?">Chấp thuận hủy &amp; hoàn kho</button></form>';
            } else {
                echo '<p class="payment-alert">Đơn đã nhận tiền, đang đối soát hoặc đã giao. Không tự hủy/hoàn tiền trước khi kiểm tra thực tế.</p>';
            }
            echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="decline_cancel_request"><input type="hidden" name="id" value="'.$id.'">';
            echo '<label class="field-group">Lý do chưa duyệt hủy<input class="field" name="decline_reason" minlength="5" maxlength="300" required placeholder="Giải thích cho khách hàng"></label>';
            echo '<button type="submit" class="button button--light" data-confirm="Từ chối yêu cầu hủy và thông báo cho khách?">Từ chối yêu cầu hủy</button></form>';
        } elseif ($cancelRequest['admin_note']!=='') {
            echo '<p class="hint">Phản hồi Admin: '.h($cancelRequest['admin_note']).'</p>';
        }
        echo '</section>';
    }

    $transitions=order_status_transitions();
    $forwardNext=[];
    $canCancel=false;
    foreach ($transitions[$curStatus]??[] as $nextKey) {
        if ($nextKey==='CANCELLED') {
            if ($order['payment_status']!=='PAID' && $order['payment_review_status']!=='REPORTED') $canCancel=true;
            continue;
        }
        if ($order['payment_method']==='BANK' && $order['payment_status']!=='PAID') continue;
        $forwardNext[]=$nextKey;
    }
    if ($order['payment_method']==='BANK' && $order['payment_status']!=='PAID' && $curStatus==='PENDING') {
        echo '<p class="payment-alert">Đơn chuyển khoản BANK phải được đối soát và xác nhận <strong>PAID</strong> bên dưới trước khi xác nhận hoặc giao hàng.</p>';
    }
    if ($forwardNext) {
        echo '<div class="payment-admin-form"><h3>Cập nhật tiến độ đơn hàng</h3>';
        echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="order_status"><input type="hidden" name="id" value="'.$id.'">';
        echo '<label class="field-group">Chuyển sang trạng thái tiếp theo<select class="field" name="status" required>';
        foreach ($forwardNext as $nk) {
            echo '<option value="'.h($nk).'">'.h(order_status_label($nk)).'</option>';
        }
        echo '</select></label>';
        if (in_array('SHIPPING',$forwardNext,true)) {
            echo '<label class="field-group">Đơn vị vận chuyển (khi giao hàng)<input class="field" name="shipping_carrier" maxlength="100" value="'.h($order['shipping_carrier']??'').'" placeholder="VD: Giao Hàng Nhanh, GHTK..."></label>';
            echo '<label class="field-group">Mã vận đơn thực tế (nếu đã có)<input class="field" name="tracking_code" maxlength="100" value="'.h($order['tracking_code']??'').'" placeholder="Để trống nếu chưa có"></label>';
        }
        echo '<label class="field-group">Ghi chú gửi khách (tùy chọn)<input class="field" name="customer_note" maxlength="500" placeholder="Thông tin hiển thị trong thông báo cho khách"></label>';
        echo '<label class="field-group">Ghi chú nội bộ (tùy chọn)<input class="field" name="admin_note" maxlength="500" placeholder="Chỉ Admin nhìn thấy"></label>';
        echo '<button type="submit" class="button full" data-confirm="Cập nhật trạng thái đơn hàng và gửi thông báo cho khách?">Cập nhật trạng thái đơn</button></form></div>';
    }
    if ($canCancel) {
        echo '<form method="post" class="payment-reject-form">'.csrf_field().'<input type="hidden" name="action" value="order_status"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="status" value="CANCELLED">';
        echo '<label class="field-group">Lý do hủy đơn hàng<input class="field" name="cancel_reason" maxlength="300" placeholder="VD: Khách yêu cầu hủy, hết hàng..."></label>';
        echo '<button type="submit" class="button button--danger full" data-confirm="Bạn chắc chắn muốn HỦY đơn hàng này và hoàn lại tồn kho?">Hủy đơn hàng &amp; hoàn kho</button></form>';
    }

    // COD explicit payment confirmation
    if ($order['payment_method']==='COD') {
        echo '<hr><h3>Thanh toán COD</h3>';
        if ($order['payment_status']==='PAID') {
            echo '<div class="payment-status-note"><strong>Đã xác nhận thu tiền COD</strong><p>Xác nhận lúc: '.h((string)($order['cod_paid_at']??'')).'</p></div>';
        } elseif (in_array($curStatus,['SHIPPING','COMPLETED'],true)) {
            echo '<form method="post" class="payment-admin-form">'.csrf_field().'<input type="hidden" name="action" value="confirm_cod_paid"><input type="hidden" name="id" value="'.$id.'">';
            echo '<p class="hint">Chỉ xác nhận sau khi shipper/bưu cục đối soát đã thu đủ tiền COD thực tế từ khách hàng.</p>';
            echo '<label class="field-group">Ghi chú thu tiền COD (tùy chọn)<input class="field" name="cod_note" maxlength="300" placeholder="VD: Đối soát COD đợt 21/11"></label>';
            echo '<button type="submit" class="button" data-confirm="Xác nhận đã thu đủ tiền mặt COD cho đơn này?">Xác nhận đã thu tiền COD</button></form>';
        } else {
            echo '<p class="hint">Đơn COD sẽ mở nút xác nhận thu tiền khi đơn ở trạng thái Đang giao hoặc Đã giao thành công.</p>';
        }
    }

    // BANK VietQR manual reconciliation (uses immutable per-order snapshot)
    if ($order['payment_method']==='BANK') {
        $snapshot=order_payment_snapshot($id);
        echo '<hr><h3>Đối soát chuyển khoản VietQR (thủ công)</h3>';
        if ($snapshot) {
            echo '<p>Ngân hàng nhận (cố định theo đơn): <strong>'.h(bank_display_label((string)$snapshot['bank_id'])).'</strong> · Số tài khoản: <strong>'.h($snapshot['bank_account']).'</strong></p>';
            if (trim((string)$snapshot['bank_account_name'])!=='') echo '<p>Chủ tài khoản: <strong>'.h($snapshot['bank_account_name']).'</strong></p>';
            echo '<p>Nội dung chuyển khoản: <code>'.h($snapshot['transfer_content']).'</code></p>';
            echo '<p>Tổng tiền cần nhận: <strong>'.money((int)$snapshot['expected_amount']).'</strong></p>';
        } else {
            echo '<div class="payment-alert"><strong>Đơn hàng cũ chưa có snapshot tài khoản ngân hàng</strong><p>Đơn này được tạo trước khi hệ thống lưu cố định thông tin tài khoản theo từng đơn. Hệ thống không tự gán tài khoản cài đặt hiện tại làm tài khoản gốc — vui lòng kiểm tra sao kê thực tế tại thời điểm đặt hàng ('.h($order['created_at']).').</p></div>';
            echo '<p>Nội dung tham chiếu: <code>BANHANG '.h($order['order_code']).'</code></p>';
            echo '<p>Tổng tiền cần nhận: <strong>'.money((int)$order['total_amount']).'</strong></p>';
        }
        echo '<p>Trạng thái đối soát: <strong>'.h(payment_review_label((string)$order['payment_review_status'])).'</strong></p>';
        if ($order['payment_reported_at']) echo '<p>Khách báo lúc: <strong>'.h($order['payment_reported_at']).'</strong></p>';
        if ($order['customer_transfer_note']) echo '<p>Ghi chú chuyển khoản của khách: '.h($order['customer_transfer_note']).'</p>';
        if ($order['payment_status']==='PAID') {
            echo '<div class="payment-status-note"><strong>Đã đối soát và nhận đủ tiền!</strong>';
            echo '<p>Mã giao dịch MB Bank: <strong>'.h($order['bank_reference']??'').'</strong></p>';
            echo '<p>Xác nhận lúc: '.h($order['payment_verified_at']??'').' · Admin #'.(int)($order['payment_verified_by']??0).'</p></div>';
        } elseif ($curStatus!=='CANCELLED') {
            echo '<div class="payment-admin-form"><p class="hint">Chỉ xác nhận sau khi kiểm tra TIỀN VÀO thực tế trong sao kê MB Bank. Nút khách bấm hoặc ảnh chụp không chứng minh đã nhận tiền.</p>';
            echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="mark_paid"><input type="hidden" name="id" value="'.$id.'">';
            echo '<label class="field-group">Mã giao dịch trên sao kê MB Bank (duy nhất)<input class="field" type="text" name="bank_reference" maxlength="120" minlength="5" required placeholder="VD: FT2611218899001"></label>';
            echo '<label class="field-group">Số tiền thực nhận (VND)<input class="field" type="number" min="1" max="1000000000000" name="received_amount" required placeholder="Phải khớp '.(int)$order['total_amount'].'"></label>';
            echo '<label class="payment-check"><input type="checkbox" name="verified_receipt" value="1" required> Tôi đã kiểm tra đúng tài khoản nhận MB Bank, mã đơn, số tiền thực nhận và giao dịch tiền vào thực tế.</label>';
            echo '<button type="submit" class="button full" data-confirm="Bạn đã đối soát xong tiền vào MB Bank và muốn xác nhận PAID?">Xác nhận PAID sau đối soát</button></form></div>';
            if ($order['payment_review_status']==='REPORTED') {
                echo '<form method="post" class="payment-reject-form">'.csrf_field().'<input type="hidden" name="action" value="reject_bank_report"><input type="hidden" name="id" value="'.$id.'">';
                echo '<label class="field-group">Lý do chưa xác minh được <textarea class="field" name="reason" maxlength="300" minlength="5" required placeholder="Không thấy giao dịch trên sao kê, nội dung không khớp..."></textarea></label>';
                echo '<button type="submit" class="button button--light" data-confirm="Báo chưa tìm thấy giao dịch và yêu cầu khách kiểm tra lại?">Báo chưa tìm thấy giao dịch</button></form>';
            }
            if ($curStatus==='PENDING' && (int)$order['past_deadline']===1 && $order['payment_review_status']!=='REPORTED') {
                echo '<form method="post" class="payment-expire-form">'.csrf_field().'<input type="hidden" name="action" value="expire_unpaid"><input type="hidden" name="id" value="'.$id.'">';
                echo '<p class="hint">Đơn chưa thanh toán quá 24 giờ và không có báo cáo đang chờ. Hủy sẽ hoàn tồn kho một lần.</p>';
                echo '<button type="submit" class="button button--danger" data-confirm="Hủy đơn quá 24 giờ và hoàn kho?">Hủy thủ công đơn quá hạn</button></form>';
            }
        }
    }

    echo '<hr><h3>Nhật ký đối soát thanh toán</h3>';
    $payEvents=rows('SELECT event_type,actor_type,actor_id,detail,reference_code,created_at FROM tbl_payment_events WHERE order_id=? ORDER BY id DESC LIMIT 30',[$id]);
    if (!$payEvents) {
        echo '<p class="hint">Chưa có sự kiện đối soát thanh toán.</p>';
    } else {
        foreach ($payEvents as $event) {
            echo '<div class="payment-event"><strong>'.h($event['event_type']).'</strong> · '.h($event['actor_type']).' #'.(int)$event['actor_id'].' · '.h($event['created_at']);
            if ($event['detail']!=='') echo '<p>'.h($event['detail']).'</p>';
            if ($event['reference_code']) echo '<small>Mã giao dịch: '.h($event['reference_code']).'</small>';
            echo '</div>';
        }
    }
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
    admin_title('Quản lý khách hàng','Thông tin liên hệ, tổng giá trị mua hàng, đơn chưa thanh toán và lịch sử đơn.');
    $q=trim((string)($_GET['q']??''));
    $curPage=max(1,min(200,(int)($_GET['p']??1)));
    $perPage=20;$offset=($curPage-1)*$perPage;
    $where='';$params=[];
    if ($q!=='') {
        $like='%'.mb_substr($q,0,80).'%';
        $where=' WHERE u.tenkhachhang LIKE ? OR u.email LIKE ? OR u.dienthoai LIKE ?';
        $params=[$like,$like,$like];
    }
    $totalRows=(int)(row('SELECT COUNT(*) AS n FROM tbl_dangky u'.$where,$params)['n']??0);
    $list=rows(
        "SELECT u.id_dangky,u.tenkhachhang,u.email,u.dienthoai,u.diachi,
                COUNT(o.id) AS total_orders,
                COALESCE(SUM(CASE WHEN o.order_status='COMPLETED' THEN 1 ELSE 0 END),0) AS completed_orders,
                COALESCE(SUM(CASE WHEN o.payment_status='UNPAID' AND o.order_status<>'CANCELLED' THEN 1 ELSE 0 END),0) AS unpaid_orders,
                COALESCE(SUM(CASE WHEN o.payment_status='PAID' THEN o.total_amount ELSE 0 END),0) AS paid_spent,
                COALESCE(SUM(CASE WHEN o.order_status<>'CANCELLED' THEN o.total_amount ELSE 0 END),0) AS gross_spent
         FROM tbl_dangky u
         LEFT JOIN tbl_orders o ON o.user_id=u.id_dangky
         {$where}
         GROUP BY u.id_dangky
         ORDER BY u.id_dangky DESC
         LIMIT {$perPage} OFFSET {$offset}",
        $params
    );
    echo '<form method="get" class="admin-search"><input type="hidden" name="page" value="customers"><input class="field" name="q" maxlength="80" value="'.h($q).'" placeholder="Tìm tên, email hoặc SĐT"><button class="button">Tìm</button></form>';
    admin_table_start(['Khách hàng','Liên hệ','Tổng đơn','Hoàn thành','Chưa TT','Đã thanh toán (PAID)','Giá trị đặt','Thao tác']);
    foreach ($list as $c) {
        echo '<tr><td><strong>'.h($c['tenkhachhang']).'</strong><br><small>'.h($c['diachi']).'</small></td>';
        echo '<td>'.h($c['email']).'<br><small>'.h($c['dienthoai']).'</small></td>';
        echo '<td>'.(int)$c['total_orders'].'</td>';
        echo '<td>'.(int)$c['completed_orders'].'</td>';
        echo '<td>'.((int)$c['unpaid_orders']>0?'<span class="status-pill status-pill--warning">'.(int)$c['unpaid_orders'].'</span>':'0').'</td>';
        echo '<td><strong>'.money((int)$c['paid_spent']).'</strong></td>';
        echo '<td>'.money((int)$c['gross_spent']).'</td>';
        echo '<td><a class="link-blue" href="'.admin_to('customer',['id'=>$c['id_dangky']]).'">Chi tiết →</a></td></tr>';
    }
    admin_table_end();
    $totalPages=(int)ceil($totalRows/$perPage);
    if ($totalPages>1) {
        echo '<nav class="pagination">';
        for ($p=1;$p<=min($totalPages,20);$p++) echo '<a class="'.($p===$curPage?'active':'').'" href="'.admin_to('customers',array_filter(['q'=>$q,'p'=>$p])).'">'.$p.'</a>';
        echo '</nav>';
    }
}
elseif ($page==='customer') {
    $cid=as_int($_GET['id']??0,1,2147483647);
    $customer=row('SELECT id_dangky,tenkhachhang,email,dienthoai,diachi FROM tbl_dangky WHERE id_dangky=?',[$cid]);
    if (!$customer) throw new DomainException('Không tìm thấy khách hàng.');
    $orders=rows('SELECT id,order_code,total_amount,payment_method,payment_status,payment_review_status,order_status,created_at FROM tbl_orders WHERE user_id=? ORDER BY id DESC LIMIT 100',[$cid]);
    $paidSpent=0;$unpaidCount=0;$completedCount=0;$grossSpent=0;
    foreach ($orders as $o) {
        if ($o['payment_status']==='PAID') $paidSpent+=(int)$o['total_amount'];
        if ($o['payment_status']==='UNPAID' && $o['order_status']!=='CANCELLED') $unpaidCount++;
        if ($o['order_status']==='COMPLETED') $completedCount++;
        if ($o['order_status']!=='CANCELLED') $grossSpent+=(int)$o['total_amount'];
    }
    admin_title('Khách hàng: '.$customer['tenkhachhang'],'Hồ sơ liên hệ, lịch sử đặt hàng và gửi thông báo đơn hàng.','<a class="button button--light" href="'.admin_to('customers').'">← Danh sách khách</a>');
    echo '<div class="stats">';
    stat_card('Tổng số đơn',(string)count($orders));
    stat_card('Đơn hoàn thành',(string)$completedCount,'','success');
    stat_card('Đơn chưa thanh toán',(string)$unpaidCount,'',$unpaidCount>0?'warning':'');
    stat_card('Đã xác nhận thanh toán',money($paidSpent),'Tổng đặt chưa hủy: '.money($grossSpent),'success');
    echo '</div>';
    echo '<div class="admin-grid"><div class="surface"><h2>Thông tin & Lịch sử đơn hàng</h2>';
    echo '<p><strong>'.h($customer['tenkhachhang']).'</strong></p><p>Email: '.h($customer['email']).' · SĐT: '.h($customer['dienthoai']).'</p><p>Địa chỉ: '.h($customer['diachi']?: 'Chưa cập nhật').'</p><hr>';
    if (!$orders) {
        echo '<p class="hint">Khách hàng chưa có đơn hàng nào.</p>';
    } else {
        admin_table_start(['Mã đơn','Ngày đặt','Tổng tiền','Thanh toán','Trạng thái']);
        foreach ($orders as $o) {
            $ps=payment_state_summary($o);
            echo '<tr><td><a class="link-blue" href="'.admin_to('order',['id'=>$o['id']]).'">'.h($o['order_code']).'</a></td><td>'.h($o['created_at']).'</td><td>'.money((int)$o['total_amount']).'</td>';
            echo '<td><span class="status-pill status-pill--'.h($ps['tone']).'">'.h($ps['label']).'</span></td>';
            echo '<td><span class="status-pill status-pill--'.h(order_status_tone((string)$o['order_status'])).'">'.h(order_status_label((string)$o['order_status'])).'</span></td></tr>';
        }
        admin_table_end();
    }
    echo '</div><div class="surface"><h2>Gửi thông báo liên quan đến đơn</h2>';
    if (!$orders) {
        echo '<p class="hint">Khách hàng chưa có đơn hàng để gửi thông báo.</p>';
    } else {
        echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="send_customer_notification"><input type="hidden" name="customer_id" value="'.$cid.'">';
        echo '<label class="field-group">Chọn đơn hàng liên quan<select class="field" name="order_id" required>';
        foreach ($orders as $o) {
            echo '<option value="'.(int)$o['id'].'">'.h($o['order_code'].' · '.money((int)$o['total_amount']).' · '.order_status_label((string)$o['order_status'])).'</option>';
        }
        echo '</select></label>';
        echo '<label class="field-group">Tiêu đề thông báo<input class="field" name="title" minlength="3" maxlength="180" required placeholder="VD: Cập nhật lịch giao hàng đơn của bạn"></label>';
        echo '<label class="field-group">Nội dung gửi khách<textarea class="field" name="message" rows="4" minlength="5" maxlength="600" required placeholder="Nhập nội dung rõ ràng gửi tới trung tâm thông báo của khách..."></textarea></label>';
        echo '<button type="submit" class="button full">Gửi thông báo tới khách</button></form>';
    }
    echo '</div></div>';
}
elseif ($page==='inventory') {
    admin_title('Tồn kho sản phẩm','Tồn kho lưu tại tbl_sanpham.soluong (dùng chung cho các dung lượng điện thoại) và được điều chỉnh qua giao dịch khóa InnoDB.');
    echo '<div class="alert info">Chính sách tồn kho hiện tại: <strong>dùng chung theo sản phẩm</strong> (<code>tbl_sanpham.soluong</code>), chưa tách kho riêng từng dung lượng điện thoại. Mọi điều chỉnh nhập/xuất đều ghi nhật ký vào <code>tbl_inventory_movements</code> và chống mất cập nhật khi khách checkout đồng thời.</div>';
    $allProds=rows('SELECT p.id_sanpham,p.tensanpham,p.masp,p.soluong,p.tinhtrang,c.tendanhmuc FROM tbl_sanpham p JOIN tbl_danhmuc c ON c.id_danhmuc=p.id_danhmuc ORDER BY p.soluong ASC,p.id_sanpham DESC LIMIT 100');
    echo '<div class="admin-grid"><section class="surface"><h2>Danh sách tồn kho</h2>';
    admin_table_start(['Sản phẩm','SKU','Danh mục','Tồn kho','Cảnh báo','Điều chỉnh']);
    foreach ($allProds as $p) {
        $stock=(int)$p['soluong'];
        $tone=$stock<=0?'danger':($stock<=5?'warning':'success');
        $lbl=$stock<=0?'Hết hàng':($stock<=5?'Sắp hết hàng':'Ổn định');
        echo '<tr><td>'.h($p['tensanpham']).'</td><td>'.h($p['masp']).'</td><td>'.h($p['tendanhmuc']).'</td><td><strong>'.$stock.'</strong></td><td><span class="status-pill status-pill--'.$tone.'">'.$lbl.'</span></td><td><a class="link-blue" href="'.admin_to('product_form',['id'=>$p['id_sanpham']]).'">Điều chỉnh / Nhật ký</a></td></tr>';
    }
    admin_table_end();
    echo '</section><section class="surface"><h2>Điều chỉnh tồn kho nhanh</h2>';
    if ($allProds) {
        echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="adjust_stock"><input type="hidden" name="return_to" value="inventory">';
        echo '<label class="field-group">Chọn sản phẩm<select class="field" name="id" required>';
        foreach ($allProds as $p) {
            echo '<option value="'.(int)$p['id_sanpham'].'">'.h($p['tensanpham'].' (SKU: '.$p['masp'].' · Hiện còn: '.(int)$p['soluong'].')').'</option>';
        }
        echo '</select></label>';
        echo '<label class="field-group">Số lượng thay đổi (+ nhập thêm / - giảm bớt)<input class="field" type="number" name="delta" min="-1000000" max="1000000" required placeholder="VD: 10 hoặc -1"></label>';
        echo '<label class="field-group">Lý do điều chỉnh (bắt buộc)<input class="field" name="reason" minlength="3" maxlength="300" required placeholder="VD: Nhập kho nhà cung cấp, kiểm kê..."></label>';
        echo '<button type="submit" class="button full">Ghi nhận điều chỉnh kho</button></form>';
    }
    echo '<hr><h3>Biến động kho gần nhất</h3>';
    $recentMoves=rows('SELECT m.movement_type,m.delta,m.stock_before,m.stock_after,m.reason,m.actor_type,m.created_at,p.tensanpham FROM tbl_inventory_movements m LEFT JOIN tbl_sanpham p ON p.id_sanpham=m.product_id ORDER BY m.id DESC LIMIT 12');
    if (!$recentMoves) {
        echo '<p class="hint">Chưa có biến động kho nào được ghi nhận.</p>';
    } else {
        foreach ($recentMoves as $mv) {
            $sign=(int)$mv['delta']>0?'+':'';
            echo '<div class="payment-event"><strong>'.h($mv['tensanpham']??'SP đã xóa').'</strong> · '.h($mv['movement_type']).' ('.$sign.(int)$mv['delta'].': '.(int)$mv['stock_before'].'→'.(int)$mv['stock_after'].')<p>'.h($mv['reason']).'</p><small>'.h($mv['actor_type']).' · '.h($mv['created_at']).'</small></div>';
        }
    }
    echo '</section></div>';
}
elseif ($page==='revenue') {
    $range=(string)($_GET['range']??'12m');
    $report=revenue_report_data($range);
    $range=$report['range'];
    $t=$report['totals'];
    $periodLabel=['7d'=>'7 ngày qua','30d'=>'30 ngày qua','12m'=>'12 tháng qua'][$range];
    admin_title('Thống kê doanh thu ('.$periodLabel.')','Phân tách giá trị đơn phát sinh (theo ngày đặt) và tiền đã xác nhận nhận (theo ngày đối soát BANK / thu tiền COD).');
    echo '<div class="alert info">Lưu ý kế toán: Số liệu <strong>Tiền đã xác nhận nhận</strong> là doanh thu gộp đã thu theo ngày xác nhận thực tế (<code>payment_verified_at</code> / <code>cod_paid_at</code>), <strong>không phải lợi nhuận</strong> (chưa trừ giá vốn/chi phí) và <strong>chưa phản ánh các khoản hoàn tiền</strong> nếu có xử lý hoàn tiền thủ công ngoài hệ thống.</div>';
    if ($report['missing_timestamp_count']>0) {
        echo '<div class="payment-alert"><strong>Dữ liệu lịch sử thiếu mốc thời gian đối soát:</strong> Có <strong>'.(int)$report['missing_timestamp_count'].'</strong> đơn đã đánh dấu PAID (tổng '.money((int)$report['missing_timestamp_total']).') nhưng không có <code>payment_verified_at</code> / <code>cod_paid_at</code>. Hệ thống không tự lấy ngày đặt hàng làm ngày thu tiền.</div>';
    }
    echo '<div class="stats">';
    stat_card('Tiền đã xác nhận nhận ('.$periodLabel.')',money((int)$t['paid_total']),'BANK đã đối soát: '.money((int)$t['bank_paid']).' · COD đã thu: '.money((int)$t['cod_paid']),'success');
    stat_card('Tiền BANK đã đối soát',money((int)$t['bank_paid']),'Tính theo payment_verified_at','success');
    stat_card('Tiền COD đã xác nhận thu',money((int)$t['cod_paid']),'Tính theo cod_paid_at','info');
    stat_card('Giá trị đơn phát sinh',money((int)$t['ordered_total']),(int)$t['active_orders'].' đơn chưa hủy (theo created_at)');
    stat_card('Khoản chưa thanh toán',money((int)$t['unpaid_total']),'BANK chờ TT: '.money((int)$t['bank_unpaid']).' · COD chưa thu: '.money((int)$t['cod_unpaid']),'warning');
    echo '</div>';
    echo '<div class="surface chart-panel">';
    render_revenue_chart($range,'revenue',$report);
    echo '</div>';
    echo '<div class="surface admin-grid--spaced"><h2>Chi tiết theo kỳ (khớp biểu đồ · '.$periodLabel.')</h2>';
    admin_table_start(['Kỳ (Ngày/Tháng)','Đơn phát sinh (chưa hủy)','Giá trị đơn phát sinh','BANK đã đối soát','COD đã thu','Tổng thực nhận (PAID)','Chưa thanh toán']);
    foreach (array_reverse($report['series']) as $m) {
        echo '<tr><td><strong>'.h($m['bucket']).'</strong></td><td>'.(int)$m['active_orders'].'</td><td>'.money((int)$m['ordered_total']).'</td><td>'.money((int)$m['bank_paid']).'</td><td>'.money((int)$m['cod_paid']).'</td><td><strong>'.money((int)$m['paid_total']).'</strong></td><td>'.money((int)$m['unpaid_total']).'</td></tr>';
    }
    admin_table_end();
    if (!$report['series']) echo '<p class="hint">Chưa có dữ liệu trong khoảng thời gian đã chọn.</p>';
    echo '</div>';
}
elseif ($page==='recommendations') {
    admin_title('Gợi ý sản phẩm','Hiện gợi ý theo danh mục liên quan và sản phẩm mới; chưa triển khai mô hình machine learning.');
    echo '<div class="stats">';
    $count=row('SELECT COUNT(*) AS n FROM tbl_sanpham WHERE tinhtrang=1');
    stat_card('Sản phẩm có thể gợi ý',(string)($count['n']??0));
    $countCat=row('SELECT COUNT(*) AS n FROM tbl_danhmuc');
    stat_card('Danh mục',(string)($countCat['n']??0));
    echo '</div><div class="surface"><h2>Cơ chế gợi ý hiện tại</h2><p>Tại trang chi tiết sản phẩm: hiển thị sản phẩm cùng danh mục, mới nhất, khác sản phẩm đang xem.</p><p>Trên trang chủ: sản phẩm mới và sản phẩm nổi bật.</p></div>';
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
    admin_title('Cấu hình cửa hàng & Email','Đổi thông tin cửa hàng, tài khoản VietQR và theo dõi hàng đợi SMTP.');
    echo '<form class="surface admin-form" method="post">'.csrf_field().'<input type="hidden" name="action" value="save_settings"><div class="form-grid"><div>';
    echo '<h2>Thông tin chung</h2><label class="field-group">Tên cửa hàng <input class="field" name="store_name" required maxlength="100" value="'.h(setting('store_name')).'"></label>';
    echo '<label class="field-group">Email hỗ trợ <input class="field" type="email" name="support_email" maxlength="100" value="'.h(setting('support_email')).'"></label></div><div>';
    echo '<h2>VietQR chuyển khoản</h2><p class="hint">Mã ngân hàng (BIN/short code) từ VietQR; không phải mật khẩu hay API bí mật.</p>';
    echo '<label class="field-group">Mã ngân hàng <input class="field" name="bank_id" maxlength="15" placeholder="MB hoặc 970422" value="'.h(setting('bank_id')).'"></label>';
    echo '<label class="field-group">Số tài khoản <input class="field" name="bank_account" maxlength="24" value="'.h(setting('bank_account')).'"></label>';
    echo '<label class="field-group">Tên chủ tài khoản <input class="field" name="bank_name" maxlength="150" value="'.h(setting('bank_name')).'"></label>';
    echo '<p class="hint">Đơn BANK hiển thị QR có số tiền và mã đơn. Quản trị viên phải đối soát chuyển khoản thật.</p></div></div>';
    $contactRow=row('SELECT thongtinlienhe FROM tbl_lienhe WHERE id=1');
    echo '<label class="field-group">Thông tin liên hệ hiển thị trên website <textarea class="field" name="contact_info" rows="5" maxlength="2000">'.h(strip_tags((string)($contactRow['thongtinlienhe']??''))).'</textarea></label>';
    echo '<button class="button" type="submit">Lưu cấu hình</button></form>';

    // SMTP & Email Outbox monitoring panel
    $smtpCfg=smtp_config();
    $smtpErr=smtp_validate_config($smtpCfg);
    $outboxCounts=row("SELECT SUM(status='SENT') AS sent_n, SUM(status='PENDING') AS pending_n, SUM(status='PROCESSING') AS proc_n, SUM(status='FAILED') AS failed_n, SUM(status='SKIPPED') AS skipped_n FROM tbl_email_outbox");
    echo '<section class="surface admin-grid--spaced"><h2>Email thông báo (SMTP Outbox)</h2>';
    if ($smtpErr===null) {
        echo '<p><span class="status-pill status-pill--success">SMTP đã cấu hình hợp lệ</span> · Host: <strong>'.h($smtpCfg['host']).':'.(int)$smtpCfg['port'].'</strong> ('.h($smtpCfg['secure']).') · Từ: <strong>'.h($smtpCfg['from_email']).'</strong></p>';
    } else {
        echo '<p><span class="status-pill status-pill--warning">SMTP chưa sẵn sàng</span> · '.h($smtpErr).' Cấu hình qua biến môi trường hoặc <code>admin/config/config.local.php</code> (xem mẫu <code>config.local.example.php</code>). Website và trung tâm thông báo khách hàng vẫn hoạt động bình thường.</p>';
    }
    echo '<p>Thống kê hàng đợi: Đã gửi <strong>'.(int)($outboxCounts['sent_n']??0).'</strong> · Đang chờ <strong>'.(int)($outboxCounts['pending_n']??0).'</strong> · Đang xử lý <strong>'.(int)($outboxCounts['proc_n']??0).'</strong> · Thất bại <strong>'.(int)($outboxCounts['failed_n']??0).'</strong> · Bỏ qua <strong>'.(int)($outboxCounts['skipped_n']??0).'</strong></p>';
    echo '<p class="hint">Email SKIPPED không tự gửi lại khi bật SMTP. Chỉ dùng nút gửi lại riêng sau khi đã kiểm tra cấu hình và chấp nhận gửi các email lịch sử.</p>';
    if ($smtpErr===null) {
        echo '<form method="post" class="inline-form">'.csrf_field().'<input type="hidden" name="action" value="retry_email_outbox"><button type="submit" class="button button--light">Khôi phục email FAILED / job treo</button></form>';
        if ((int)($outboxCounts['skipped_n']??0)>0) {
            echo '<form method="post" class="inline-form">'.csrf_field().'<input type="hidden" name="action" value="retry_skipped_email_outbox"><button type="submit" class="button button--light" data-confirm="Gửi lại tất cả email SKIPPED còn trong giới hạn thử? Hành động này có thể gửi email lịch sử tới khách hàng.">Gửi lại toàn bộ email SKIPPED đủ điều kiện</button></form>';
        }
    } else {
        echo '<p class="hint">Cần cấu hình SMTP hợp lệ trước khi khôi phục email FAILED hoặc gửi lại email SKIPPED.</p>';
    }
    $recentMail=rows('SELECT id,recipient_email,event_type,subject,status,attempts,last_error,created_at,sent_at FROM tbl_email_outbox ORDER BY id DESC LIMIT 10');
    if ($recentMail) {
        admin_table_start(['Người nhận','Sự kiện','Tiêu đề','Trạng thái','Lần thử','Thời gian / Ghi chú']);
        foreach ($recentMail as $em) {
            $eTone=$em['status']==='SENT'?'success':($em['status']==='FAILED'?'danger':($em['status']==='SKIPPED'?'neutral':'warning'));
            echo '<tr><td>'.h($em['recipient_email']).'</td><td>'.h($em['event_type']).'</td><td>'.h($em['subject']).'</td><td><span class="status-pill status-pill--'.$eTone.'">'.h($em['status']).'</span></td><td>'.(int)$em['attempts'].'</td><td><small>'.h($em['sent_at']?:$em['created_at']).($em['last_error']!==''?' · '.$em['last_error']:'').'</small></td></tr>';
        }
        admin_table_end();
    }
    echo '</section>';
}
admin_page_foot();
} catch (Throwable $e) {
    error_log('[BanHang admin render] '.$e->getMessage());
    http_response_code(500);
    echo '<div class="admin-content"><div class="surface"><h2>Có lỗi hiển thị trang quản trị</h2><p>Kiểm tra log, MySQL và đã import banhang.sql mới nhất.</p></div></div>';
    page_foot(true);
}
