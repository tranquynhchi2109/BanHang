<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app.php';

function check_payment(bool $condition, string $description): void
{
    if (!$condition) throw new RuntimeException('FAILED: ' . $description);
    echo "OK: {$description}\n";
}
function expect_refusal(callable $fn, string $description): void
{
    try { $fn(); }
    catch (DomainException $e) {
        echo "OK: {$description} was refused\n";
        return;
    }
    throw new RuntimeException('FAILED: ' . $description . ' was permitted');
}
function test_order(string $code, int $total, string $created = 'NOW()'): int
{
    if (!in_array($created,['NOW()','NOW() - INTERVAL 2 DAY'],true)) throw new RuntimeException('Invalid test date');
    run("INSERT INTO tbl_orders(order_code,user_id,customer_name,phone,delivery_address,total_amount,payment_method,created_at)
         VALUES (?,1,'CI Payment','0900000000','CI address',?,'BANK',{$created})",[$code,$total]);
    return (int)db()->lastInsertId();
}

$first=test_order('CI_MANUAL_A',37500000);
expect_refusal(fn()=>report_bank_transfer('CI_MANUAL_A',2,''),'Wrong customer');
report_bank_transfer('CI_MANUAL_A',1,'Đã chuyển khoản.');
$a=row('SELECT payment_status,payment_review_status,payment_reported_at FROM tbl_orders WHERE id=?',[$first]);
check_payment($a['payment_status']==='UNPAID' && $a['payment_review_status']==='REPORTED','Customer report keeps payment UNPAID');
check_payment($a['payment_reported_at']!==null,'Customer report time saved');
expect_refusal(fn()=>report_bank_transfer('CI_MANUAL_A',1,''),'Repeated customer report');
reject_bank_report($first,7,'Chưa thấy giao dịch tiền vào.');
check_payment(row('SELECT payment_review_status FROM tbl_orders WHERE id=?',[$first])['payment_review_status']==='REJECTED','Admin reject does not mark paid');
report_bank_transfer('CI_MANUAL_A',1,'Báo lại sau kiểm tra.');
expect_refusal(fn()=>confirm_bank_transfer($first,7,'MB-CI-001',2000),'Wrong received amount');
check_payment(row('SELECT payment_status FROM tbl_orders WHERE id=?',[$first])['payment_status']==='UNPAID','Bad amount did not mark PAID');
confirm_bank_transfer($first,7,'MB-CI-001',37500000);
$a=row('SELECT payment_status,payment_review_status,bank_reference,payment_verified_at,payment_verified_by FROM tbl_orders WHERE id=?',[$first]);
check_payment($a['payment_status']==='PAID' && $a['payment_review_status']==='VERIFIED','Admin confirmed the actual exact amount');
check_payment($a['bank_reference']==='MB-CI-001' && $a['payment_verified_at']!==null && (int)$a['payment_verified_by']===7,'Audit metadata stored');
expect_refusal(fn()=>report_bank_transfer('CI_MANUAL_A',1,''),'Reporting after PAID');
expect_refusal(fn()=>confirm_bank_transfer($first,7,'MB-CI-001',37500000),'Second confirmation');

$second=test_order('CI_MANUAL_B',37500000);
expect_refusal(fn()=>confirm_bank_transfer($second,7,'MB-CI-001',37500000),'Bank transaction reused for another order');
check_payment(row('SELECT payment_status FROM tbl_orders WHERE id=?',[$second])['payment_status']==='UNPAID','Second order stayed UNPAID');
expect_refusal(fn()=>expire_unpaid_bank_order($second,7),'Early cancellation before 24h');

$stockBefore=(int)row('SELECT soluong FROM tbl_sanpham WHERE id_sanpham=1')['soluong'];
$expiring=test_order('CI_EXPIRE_C',1000,'NOW() - INTERVAL 2 DAY');
run('UPDATE tbl_sanpham SET soluong=soluong-1 WHERE id_sanpham=1');
run("INSERT INTO tbl_order_items(order_id,product_id,product_name,variant_label,unit_price,quantity) VALUES (?,1,'CI Phone','128GB',1000,1)",[$expiring]);
expire_unpaid_bank_order($expiring,7);
check_payment(row('SELECT order_status FROM tbl_orders WHERE id=?',[$expiring])['order_status']==='CANCELLED','Overdue unpaid order manually cancelled');
check_payment((int)row('SELECT soluong FROM tbl_sanpham WHERE id_sanpham=1')['soluong']===$stockBefore,'Stock restored exactly once');
expect_refusal(fn()=>expire_unpaid_bank_order($expiring,7),'Second cancellation');
check_payment((int)row('SELECT soluong FROM tbl_sanpham WHERE id_sanpham=1')['soluong']===$stockBefore,'Stock not double-restored');

$reported=test_order('CI_EXPIRE_D',1000,'NOW() - INTERVAL 2 DAY');
report_bank_transfer('CI_EXPIRE_D',1,'Khách đã chuyển tiền.');
expect_refusal(fn()=>expire_unpaid_bank_order($reported,7),'Unverified reported transfer cannot be expired');
check_payment(row('SELECT order_status FROM tbl_orders WHERE id=?',[$reported])['order_status']==='PENDING','Reported order remains active');

$events=(int)row('SELECT COUNT(*) AS n FROM tbl_payment_events WHERE order_id=?',[$first])['n'];
check_payment($events===4,'Four audit events saved for first order');
echo "Manual bank reconciliation integration checks PASSED\n";
