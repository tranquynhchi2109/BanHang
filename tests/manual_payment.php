<?php
declare(strict_types=1);

/**
 * Isolated integration tests for manual VietQR bank reconciliation,
 * duplicate bank reference protection, audit logging and 24h manual expiry.
 * Uses unique per-run fixtures and cleans up completely in finally so it can
 * run independently, repeatedly, and in any order alongside other test suites.
 */
require dirname(__DIR__) . '/app.php';

function check_payment(bool $condition, string $description): void
{
    if (!$condition) throw new RuntimeException('FAILED: ' . $description);
    echo "OK: {$description}\n";
}

function expect_refusal(callable $fn, string $description): void
{
    try {
        $fn();
    } catch (DomainException $e) {
        echo "OK: {$description} was refused\n";
        return;
    }
    throw new RuntimeException('FAILED: ' . $description . ' was permitted');
}

$runId = strtoupper(bin2hex(random_bytes(4)));
$createdOrders = [];
$createdUsers = [];
$createdProductId = null;
$createdCategoryId = null;

try {
    // Dedicated isolated fixtures (no hardcoded user_id=1 or id_sanpham=1)
    run('INSERT INTO tbl_danhmuc(tendanhmuc,thutu) VALUES (?,999)', ["Điện thoại MP {$runId}"]);
    $createdCategoryId = (int)db()->lastInsertId();

    run(
        "INSERT INTO tbl_sanpham(tensanpham,masp,giasp,soluong,hinhanh,tomtat,noidung,tinhtrang,id_danhmuc)
         VALUES (?,?,27500000,10,'ip15.jpg','','',1,?)",
        ["MP Phone {$runId}", "SKU-MP-{$runId}", $createdCategoryId]
    );
    $createdProductId = (int)db()->lastInsertId();

    run(
        'INSERT INTO tbl_dangky(tenkhachhang,email,diachi,matkhau,dienthoai) VALUES (?,?,?,?,?)',
        ["CI Owner {$runId}", "mp_owner_{$runId}@example.com", 'CI address', password_hash('TestPass12345!', PASSWORD_DEFAULT), '0900000001']
    );
    $ownerId = (int)db()->lastInsertId();
    $createdUsers[] = $ownerId;

    run(
        'INSERT INTO tbl_dangky(tenkhachhang,email,diachi,matkhau,dienthoai) VALUES (?,?,?,?,?)',
        ["CI Other {$runId}", "mp_other_{$runId}@example.com", 'CI address 2', password_hash('TestPass12345!', PASSWORD_DEFAULT), '0900000002']
    );
    $otherUserId = (int)db()->lastInsertId();
    $createdUsers[] = $otherUserId;

    $makeOrder = static function (string $suffix, int $total, string $created = 'NOW()') use ($runId, $ownerId, &$createdOrders): array {
        if (!in_array($created, ['NOW()', 'NOW() - INTERVAL 2 DAY'], true)) {
            throw new RuntimeException('Invalid test date');
        }
        $code = "MP{$runId}{$suffix}";
        run(
            "INSERT INTO tbl_orders(order_code,user_id,customer_name,phone,delivery_address,total_amount,payment_method,created_at)
             VALUES (?,?,'CI Payment','0900000001','CI address',?,'BANK',{$created})",
            [$code, $ownerId, $total]
        );
        $id = (int)db()->lastInsertId();
        $createdOrders[] = $id;
        return ['id' => $id, 'code' => $code];
    };

    $refCode = "MB-CI-{$runId}-001";

    $first = $makeOrder('A', 37500000);
    expect_refusal(fn() => report_bank_transfer($first['code'], $otherUserId, ''), 'Wrong customer');
    report_bank_transfer($first['code'], $ownerId, 'Đã chuyển khoản.');
    $a = row('SELECT payment_status,payment_review_status,payment_reported_at FROM tbl_orders WHERE id=?', [$first['id']]);
    check_payment($a['payment_status'] === 'UNPAID' && $a['payment_review_status'] === 'REPORTED', 'Customer report keeps payment UNPAID');
    check_payment($a['payment_reported_at'] !== null, 'Customer report time saved');
    expect_refusal(fn() => report_bank_transfer($first['code'], $ownerId, ''), 'Repeated customer report');

    reject_bank_report($first['id'], 7, 'Chưa thấy giao dịch tiền vào.');
    check_payment(row('SELECT payment_review_status FROM tbl_orders WHERE id=?', [$first['id']])['payment_review_status'] === 'REJECTED', 'Admin reject does not mark paid');

    report_bank_transfer($first['code'], $ownerId, 'Báo lại sau kiểm tra.');
    expect_refusal(fn() => confirm_bank_transfer($first['id'], 7, $refCode, 2000), 'Wrong received amount');
    check_payment(row('SELECT payment_status FROM tbl_orders WHERE id=?', [$first['id']])['payment_status'] === 'UNPAID', 'Bad amount did not mark PAID');

    confirm_bank_transfer($first['id'], 7, $refCode, 37500000);
    $a = row('SELECT payment_status,payment_review_status,bank_reference,payment_verified_at,payment_verified_by FROM tbl_orders WHERE id=?', [$first['id']]);
    check_payment($a['payment_status'] === 'PAID' && $a['payment_review_status'] === 'VERIFIED', 'Admin confirmed the actual exact amount');
    check_payment($a['bank_reference'] === $refCode && $a['payment_verified_at'] !== null && (int)$a['payment_verified_by'] === 7, 'Audit metadata stored');
    expect_refusal(fn() => report_bank_transfer($first['code'], $ownerId, ''), 'Reporting after PAID');
    expect_refusal(fn() => confirm_bank_transfer($first['id'], 7, $refCode, 37500000), 'Second confirmation');

    $second = $makeOrder('B', 37500000);
    expect_refusal(fn() => confirm_bank_transfer($second['id'], 7, $refCode, 37500000), 'Bank transaction reused for another order');
    check_payment(row('SELECT payment_status FROM tbl_orders WHERE id=?', [$second['id']])['payment_status'] === 'UNPAID', 'Second order stayed UNPAID');
    expect_refusal(fn() => expire_unpaid_bank_order($second['id'], 7), 'Early cancellation before 24h');

    $stockBefore = (int)row('SELECT soluong FROM tbl_sanpham WHERE id_sanpham=?', [$createdProductId])['soluong'];
    $expiring = $makeOrder('C', 1000, 'NOW() - INTERVAL 2 DAY');
    run('UPDATE tbl_sanpham SET soluong=soluong-1 WHERE id_sanpham=?', [$createdProductId]);
    run(
        "INSERT INTO tbl_order_items(order_id,product_id,product_name,variant_label,unit_price,quantity) VALUES (?,?,'MP Phone','128GB',1000,1)",
        [$expiring['id'], $createdProductId]
    );
    expire_unpaid_bank_order($expiring['id'], 7);
    check_payment(row('SELECT order_status FROM tbl_orders WHERE id=?', [$expiring['id']])['order_status'] === 'CANCELLED', 'Overdue unpaid order manually cancelled');
    check_payment((int)row('SELECT soluong FROM tbl_sanpham WHERE id_sanpham=?', [$createdProductId])['soluong'] === $stockBefore, 'Stock restored exactly once');
    expect_refusal(fn() => expire_unpaid_bank_order($expiring['id'], 7), 'Second cancellation');
    check_payment((int)row('SELECT soluong FROM tbl_sanpham WHERE id_sanpham=?', [$createdProductId])['soluong'] === $stockBefore, 'Stock not double-restored');

    $reported = $makeOrder('D', 1000, 'NOW() - INTERVAL 2 DAY');
    report_bank_transfer($reported['code'], $ownerId, 'Khách đã chuyển tiền.');
    expect_refusal(fn() => expire_unpaid_bank_order($reported['id'], 7), 'Unverified reported transfer cannot be expired');
    check_payment(row('SELECT order_status FROM tbl_orders WHERE id=?', [$reported['id']])['order_status'] === 'PENDING', 'Reported order remains active');

    $events = (int)row('SELECT COUNT(*) AS n FROM tbl_payment_events WHERE order_id=?', [$first['id']])['n'];
    check_payment($events === 4, 'Four audit events saved for first order');
    echo "Manual bank reconciliation integration checks PASSED\n";
} finally {
    foreach ($createdOrders as $oid) {
        run('DELETE FROM tbl_inventory_movements WHERE order_id=?', [$oid]);
        run('DELETE FROM tbl_order_payment_details WHERE order_id=?', [$oid]);
        run('DELETE FROM tbl_email_outbox WHERE order_id=?', [$oid]);
        run('DELETE FROM tbl_notifications WHERE order_id=?', [$oid]);
        run('DELETE FROM tbl_order_status_history WHERE order_id=?', [$oid]);
        run('DELETE FROM tbl_payment_events WHERE order_id=?', [$oid]);
        run('DELETE FROM tbl_order_items WHERE order_id=?', [$oid]);
        run('DELETE FROM tbl_orders WHERE id=?', [$oid]);
    }
    foreach ($createdUsers as $uid) {
        run('DELETE FROM tbl_email_outbox WHERE user_id=?', [$uid]);
        run('DELETE FROM tbl_notifications WHERE user_id=?', [$uid]);
        run('DELETE FROM tbl_dangky WHERE id_dangky=?', [$uid]);
    }
    if ($createdProductId !== null) {
        run('DELETE FROM tbl_inventory_movements WHERE product_id=?', [$createdProductId]);
        run('DELETE FROM tbl_product_variants WHERE product_id=?', [$createdProductId]);
        run('DELETE FROM tbl_sanpham WHERE id_sanpham=?', [$createdProductId]);
    }
    if ($createdCategoryId !== null) {
        run('DELETE FROM tbl_danhmuc WHERE id_danhmuc=?', [$createdCategoryId]);
    }
}
