<?php
declare(strict_types=1);

/**
 * Customer order actions - isolated integration checks.
 * Requires BANHANG_TEST_MODE=1, CLI and a *_test/_testing/_ci database.
 */
require dirname(__DIR__) . '/app.php';

function action_ok(bool $test, string $message): void
{
    if (!$test) throw new RuntimeException('FAILED: '.$message);
    echo "OK: {$message}\n";
}
function action_refuses(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (DomainException $e) {
        echo "OK: refused {$message}\n";
        return;
    }
    throw new RuntimeException('FAILED: permitted '.$message);
}

$runId = strtoupper(bin2hex(random_bytes(4)));
$orderIds = [];
$userIds = [];
$categoryId = null;
$productId = null;

try {
    run('INSERT INTO tbl_danhmuc(tendanhmuc,thutu) VALUES (?,999)',["Action Test {$runId}"]);
    $categoryId = (int)db()->lastInsertId();
    run("INSERT INTO tbl_sanpham(tensanpham,masp,giasp,soluong,hinhanh,tomtat,noidung,tinhtrang,id_danhmuc)
         VALUES (?,?,1250000,10,'ip15.jpg','','',1,?)",
         ["Action Product {$runId}","ACTION-{$runId}",$categoryId]);
    $productId=(int)db()->lastInsertId();

    foreach (['owner','other'] as $role) {
        run('INSERT INTO tbl_dangky(tenkhachhang,email,diachi,matkhau,dienthoai) VALUES (?,?,?,?,?)',
            ["{$role} {$runId}","{$role}_{$runId}@example.com",'CI address',
            password_hash('Password123456!',PASSWORD_DEFAULT),'0901111222']);
        $userIds[$role]=(int)db()->lastInsertId();
    }
    $ownerId=$userIds['owner'];
    $otherId=$userIds['other'];

    $makeOrder=static function(string $suffix, string $payment='COD', string $status='PENDING') use
        ($runId,$ownerId,$productId,&$orderIds): array {
        $code="CA{$runId}{$suffix}";
        run('INSERT INTO tbl_orders(order_code,user_id,customer_name,phone,delivery_address,total_amount,payment_method,order_status)
             VALUES (?,?,?,?,?,?,?,?)',
            [$code,$ownerId,'CI Owner','0901111222','CI address',1250000,$payment,$status]);
        $id=(int)db()->lastInsertId();
        $orderIds[]=$id;
        run("INSERT INTO tbl_order_items(order_id,product_id,product_name,variant_label,unit_price,quantity)
             VALUES (?,?,'Action Product','BASE',1250000,1)",[$id,$productId]);
        run('UPDATE tbl_sanpham SET soluong=soluong-1 WHERE id_sanpham=?',[$productId]);
        return ['id'=>$id,'code'=>$code];
    };

    // 1: customer-owned COD order cancels exactly once, with inventory ledger.
    $cod=$makeOrder('COD');
    $before=(int)row('SELECT soluong FROM tbl_sanpham WHERE id_sanpham=?',[$productId])['soluong'];
    action_refuses(fn()=>customer_cancel_order($cod['code'],$otherId,'Đặt nhầm đơn hàng'),'other user cancels order');
    $result=customer_cancel_order($cod['code'],$ownerId,'Đặt nhầm đơn hàng');
    action_ok($result==='CANCELLED','COD PENDING order cancelled by owner');
    action_ok((int)row('SELECT soluong FROM tbl_sanpham WHERE id_sanpham=?',[$productId])['soluong']===$before+1,'stock restored exactly once');
    action_ok(row('SELECT order_status FROM tbl_orders WHERE id=?',[$cod['id']])['order_status']==='CANCELLED','order stored as CANCELLED');
    action_refuses(fn()=>customer_cancel_order($cod['code'],$ownerId,'Đặt nhầm đơn hàng'),'second customer cancellation');
    action_ok((int)row("SELECT COUNT(*) AS n FROM tbl_inventory_movements WHERE order_id=? AND movement_type='ORDER_CANCEL_RESTORE'",[$cod['id']])['n']===1,'one restore ledger entry');
    action_refuses(fn()=>customer_change_payment_method($cod['code'],$ownerId,'BANK'),'change payment after cancellation');

    // 2: COD -> BANK -> COD, original immutable snapshot must persist.
    $switch=$makeOrder('SW');
    action_refuses(fn()=>customer_change_payment_method($switch['code'],$otherId,'BANK'),'other user changes payment');
    customer_change_payment_method($switch['code'],$ownerId,'BANK');
    $sw1=row('SELECT payment_method,payment_status FROM tbl_orders WHERE id=?',[$switch['id']]);
    $snap=order_payment_snapshot($switch['id']);
    action_ok($sw1['payment_method']==='BANK' && $sw1['payment_status']==='UNPAID' && $snap!==null,'COD -> BANK creates snapshot but never marks PAID');
    customer_change_payment_method($switch['code'],$ownerId,'COD');
    action_ok(row('SELECT payment_method FROM tbl_orders WHERE id=?',[$switch['id']])['payment_method']==='COD','BANK -> COD works before bank report');
    customer_change_payment_method($switch['code'],$ownerId,'BANK');
    action_ok(order_payment_snapshot($switch['id'])===$snap,'BANK details remain immutable on repeated switch');
    report_bank_transfer($switch['code'],$ownerId,'Đã chuyển tiền rồi');
    action_refuses(fn()=>customer_change_payment_method($switch['code'],$ownerId,'COD'),'cannot switch after customer bank report');
    action_ok(customer_cancel_order($switch['code'],$ownerId,'Muốn hủy sau khi chuyển khoản')==='REQUESTED','reported BANK creates review request, not immediate cancel');
    action_ok(customer_cancel_order($switch['code'],$ownerId,'Muốn hủy sau khi chuyển khoản')==='REQUESTED','duplicate pending request idempotent');
    action_ok((int)row('SELECT COUNT(*) AS n FROM tbl_order_cancel_requests WHERE order_id=?',[$switch['id']])['n']===1,'exactly one cancellation request');
    action_refuses(fn()=>report_bank_transfer($switch['code'],$ownerId,'Báo lại khi chờ hủy'),'bank reporting blocked during pending cancellation');
    action_ok(row('SELECT order_status FROM tbl_orders WHERE id=?',[$switch['id']])['order_status']==='PENDING','request does not prematurely cancel order');
    action_refuses(fn()=>update_order_status($switch['id'],1,'CONFIRMED'),'Admin cannot fulfill pending cancel request');
    action_refuses(fn()=>update_order_status($switch['id'],1,'CANCELLED'),'Admin cannot cancel reported bank transfer');
    admin_decline_cancel_request($switch['id'],1,'Cần kiểm tra sao kê ngân hàng trước khi hủy');
    action_ok(row('SELECT status FROM tbl_order_cancel_requests WHERE order_id=?',[$switch['id']])['status']==='DECLINED','Admin refusal stored');
    action_refuses(fn()=>customer_cancel_order($switch['code'],$ownerId,'Tiếp tục hủy lần hai'),'cannot spam already resolved request');

    // 3: confirmed COD needs approval, then stock restores once on Admin cancellation.
    $confirmed=$makeOrder('CONF','COD','CONFIRMED');
    $beforeConfirm=(int)row('SELECT soluong FROM tbl_sanpham WHERE id_sanpham=?',[$productId])['soluong'];
    action_ok(customer_cancel_order($confirmed['code'],$ownerId,'Không còn nhu cầu mua')==='REQUESTED','confirmed COD request is pending');
    action_refuses(fn()=>customer_change_payment_method($confirmed['code'],$ownerId,'BANK'),'confirmed COD cannot switch payment');
    update_order_status($confirmed['id'],1,'CANCELLED',['cancel_reason'=>'Khách yêu cầu hủy: Không còn nhu cầu mua']);
    action_ok(row('SELECT status FROM tbl_order_cancel_requests WHERE order_id=?',[$confirmed['id']])['status']==='APPROVED','Admin cancellation approves request atomically');
    action_ok((int)row('SELECT soluong FROM tbl_sanpham WHERE id_sanpham=?',[$productId])['soluong']===$beforeConfirm+1,'Admin cancellation restored stock once');
    action_refuses(fn()=>update_order_status($confirmed['id'],1,'CANCELLED'),'second Admin cancellation');
    action_ok((int)row("SELECT COUNT(*) AS n FROM tbl_inventory_movements WHERE order_id=? AND movement_type='ORDER_CANCEL_RESTORE'",[$confirmed['id']])['n']===1,'Admin cancel one restore entry');

    // 4: paid order always requires manual refund handling; never instant cancel.
    $paid=$makeOrder('PAID','BANK');
    run("UPDATE tbl_orders SET payment_status='PAID',payment_review_status='VERIFIED' WHERE id=?",[$paid['id']]);
    action_ok(customer_cancel_order($paid['code'],$ownerId,'Đã thanh toán nhưng muốn hủy')==='REQUESTED','paid BANK can request but not auto cancel');
    action_refuses(fn()=>update_order_status($paid['id'],1,'CANCELLED'),'paid BANK requires refund procedure');
    action_ok(row('SELECT order_status FROM tbl_orders WHERE id=?',[$paid['id']])['order_status']==='PENDING','paid BANK status preserved');

    // 5: overdue manual expiry also resolves an outstanding customer cancellation request.
    $expired=$makeOrder('EXP','BANK');
    report_bank_transfer($expired['code'],$ownerId,'Cần kiểm tra ngân hàng');
    reject_bank_report($expired['id'],1,'Đã kiểm tra sao kê nhưng chưa thấy tiền.');
    action_ok(customer_cancel_order($expired['code'],$ownerId,'Không còn nhu cầu mua')==='REQUESTED','previous bank report needs approval');
    run('UPDATE tbl_orders SET created_at=NOW()-INTERVAL 2 DAY WHERE id=?',[$expired['id']]);
    expire_unpaid_bank_order($expired['id'],1);
    action_ok(row('SELECT status FROM tbl_order_cancel_requests WHERE order_id=?',[$expired['id']])['status']==='APPROVED','manual expiry resolves pending cancel request');
    action_ok(row('SELECT order_status FROM tbl_orders WHERE id=?',[$expired['id']])['order_status']==='CANCELLED','manual expiry cancels order');
    action_ok((int)row("SELECT COUNT(*) AS n FROM tbl_inventory_movements WHERE order_id=? AND movement_type='ORDER_CANCEL_RESTORE'",[$expired['id']])['n']===1,'manual expiry restored stock once');

    echo "Customer order action tests PASSED\n";
} finally {
    foreach ($orderIds as $id) {
        run('DELETE FROM tbl_order_cancel_requests WHERE order_id=?',[$id]);
        run('DELETE FROM tbl_inventory_movements WHERE order_id=?',[$id]);
        run('DELETE FROM tbl_order_payment_details WHERE order_id=?',[$id]);
        run('DELETE FROM tbl_email_outbox WHERE order_id=?',[$id]);
        run('DELETE FROM tbl_notifications WHERE order_id=?',[$id]);
        run('DELETE FROM tbl_order_status_history WHERE order_id=?',[$id]);
        run('DELETE FROM tbl_payment_events WHERE order_id=?',[$id]);
        run('DELETE FROM tbl_order_items WHERE order_id=?',[$id]);
        run('DELETE FROM tbl_orders WHERE id=?',[$id]);
    }
    foreach ($userIds as $id) {
        run('DELETE FROM tbl_email_outbox WHERE user_id=?',[$id]);
        run('DELETE FROM tbl_notifications WHERE user_id=?',[$id]);
        run('DELETE FROM tbl_dangky WHERE id_dangky=?',[$id]);
    }
    if ($productId!==null) {
        run('DELETE FROM tbl_inventory_movements WHERE product_id=?',[$productId]);
        run('DELETE FROM tbl_product_variants WHERE product_id=?',[$productId]);
        run('DELETE FROM tbl_sanpham WHERE id_sanpham=?',[$productId]);
    }
    if ($categoryId!==null) run('DELETE FROM tbl_danhmuc WHERE id_danhmuc=?',[$categoryId]);
}
