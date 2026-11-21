<?php
declare(strict_types=1);

/**
 * Integration tests for notifications, order status history, timeline rules,
 * IDOR isolation, COD separation, revenue accuracy and optional SMTP outbox.
 * Safe to run in CI or locally (cleans up all created test records).
 */
require dirname(__DIR__) . '/app.php';

function assert_ok(bool $cond, string $label): void
{
    if (!$cond) throw new RuntimeException('FAILED: ' . $label);
    echo "OK: {$label}\n";
}

function assert_throws_domain(callable $fn, string $label): void
{
    try {
        $fn();
    } catch (DomainException $e) {
        echo "OK: {$label} refused ({$e->getMessage()})\n";
        return;
    }
    throw new RuntimeException('FAILED: ' . $label . ' was unexpectedly permitted');
}

$createdUsers = [];
$createdOrders = [];
$origProductStock = null;
$testProductId = null;

try {
    // Ensure a product exists (CI inserts id=1; local DB already has products)
    $prod = row('SELECT id_sanpham, soluong FROM tbl_sanpham ORDER BY id_sanpham ASC LIMIT 1');
    if (!$prod) {
        run("INSERT INTO tbl_danhmuc(tendanhmuc,thutu) VALUES ('Điện thoại',1)");
        $catId = (int)db()->lastInsertId();
        run("INSERT INTO tbl_sanpham(tensanpham,masp,giasp,soluong,hinhanh,tomtat,noidung,tinhtrang,id_danhmuc) VALUES ('Test Phone','TP1',20000000,10,'ip15.jpg','','',1,?)", [$catId]);
        $prod = row('SELECT id_sanpham, soluong FROM tbl_sanpham ORDER BY id_sanpham DESC LIMIT 1');
    }
    $testProductId = (int)$prod['id_sanpham'];
    $origProductStock = (int)$prod['soluong'];

    // Create two distinct test customers (A and B) for IDOR checks
    $suffix = bin2hex(random_bytes(4));
    run('INSERT INTO tbl_dangky(tenkhachhang,email,diachi,matkhau,dienthoai) VALUES (?,?,?,?,?)',
        ['Khách A Test', "test_a_{$suffix}@example.com", '123 Test St', password_hash('Password12345!', PASSWORD_DEFAULT), '0901111111']);
    $userA = (int)db()->lastInsertId();
    $createdUsers[] = $userA;

    run('INSERT INTO tbl_dangky(tenkhachhang,email,diachi,matkhau,dienthoai) VALUES (?,?,?,?,?)',
        ['Khách B Test', "test_b_{$suffix}@example.com", '456 Test St', password_hash('Password12345!', PASSWORD_DEFAULT), '0902222222']);
    $userB = (int)db()->lastInsertId();
    $createdUsers[] = $userB;

    // 1. Customer A creates BANK order
    $codeA = 'BHTEST' . strtoupper(bin2hex(random_bytes(4)));
    run("INSERT INTO tbl_orders(order_code,user_id,customer_name,phone,delivery_address,total_amount,payment_method)
         VALUES (?,?,?,'0901111111','123 Test St',37500000,'BANK')", [$codeA, $userA, 'Khách A Test']);
    $orderA = (int)db()->lastInsertId();
    $createdOrders[] = $orderA;
    run("INSERT INTO tbl_order_items(order_id,product_id,product_name,variant_label,unit_price,quantity) VALUES (?,?,'Test Phone','1TB',37500000,1)", [$orderA, $testProductId]);
    record_order_history($orderA, null, 'PENDING', 'CUSTOMER', $userA, 'Tạo đơn test', true);
    create_notification($userA, $orderA, 'ORDER_CREATED', 'Đặt hàng thành công!', "Đơn {$codeA} đã tạo.", link_to('order', ['code' => $codeA]), "order_created:{$orderA}");
    queue_order_email(row('SELECT * FROM tbl_orders WHERE id=?', [$orderA]), 'ORDER_CREATED', '', "email_created:{$orderA}");
    dispatch_email_outbox(5);

    assert_ok(unread_notification_count($userA) === 1, '1. Customer A receives order creation notification');

    // 2 & 3. Customer A reports transfer -> stays UNPAID, Admin pending reconciliation increases, no "Thanh toán thành công" yet
    $recBefore = admin_pending_reconciliation_count();
    assert_throws_domain(fn() => report_bank_transfer($codeA, $userB, 'Fake'), '8a. Customer B cannot report Customer A order');
    report_bank_transfer($codeA, $userA, 'FT cuối 1234');
    $ordARow = row('SELECT payment_status, payment_review_status FROM tbl_orders WHERE id=?', [$orderA]);
    assert_ok($ordARow['payment_status'] === 'UNPAID' && $ordARow['payment_review_status'] === 'REPORTED', '2. Customer report keeps order UNPAID');
    assert_ok(admin_pending_reconciliation_count() === $recBefore + 1, '3. Admin pending reconciliation counter increases');
    $paidNotifBefore = (int)(row("SELECT COUNT(*) AS n FROM tbl_notifications WHERE order_id=? AND notification_type='PAYMENT_CONFIRMED'", [$orderA])['n'] ?? 0);
    assert_ok($paidNotifBefore === 0, '2b. Reporting transfer does NOT send Thanh toán thành công notification');

    // Cannot confirm/prepare/ship BANK order before PAID
    assert_throws_domain(fn() => update_order_status($orderA, 1, 'CONFIRMED'), 'BANK order cannot be CONFIRMED before PAID');
    assert_throws_domain(fn() => update_order_status($orderA, 1, 'CANCELLED'), 'REPORTED order cannot be CANCELLED before reconciliation');

    // 9. Wrong amount cannot mark PAID
    $refA = 'MBTEST_' . strtoupper(bin2hex(random_bytes(5)));
    assert_throws_domain(fn() => confirm_bank_transfer($orderA, 1, $refA, 37000000), '9. Wrong received amount refused');

    // 4 & 5. Exact amount marks PAID + creates "Thanh toán thành công!" notification + outbox record
    confirm_bank_transfer($orderA, 1, $refA, 37500000);
    $ordAPaid = row('SELECT payment_status, payment_review_status, bank_reference FROM tbl_orders WHERE id=?', [$orderA]);
    assert_ok($ordAPaid['payment_status'] === 'PAID' && $ordAPaid['payment_review_status'] === 'VERIFIED', '4. Admin confirms exact amount -> PAID');

    $paidNotifs = rows("SELECT * FROM tbl_notifications WHERE user_id=? AND order_id=? AND notification_type='PAYMENT_CONFIRMED'", [$userA, $orderA]);
    assert_ok(count($paidNotifs) === 1 && str_contains($paidNotifs[0]['title'], 'Thanh toán thành công') && str_contains($paidNotifs[0]['message'], '37.500.000'), '5. Customer A receives Thanh toán thành công notification');

    // 11. Duplicate confirmation cannot create second event or notification
    assert_throws_domain(fn() => confirm_bank_transfer($orderA, 1, $refA, 37500000), '11. Second confirmation refused');
    $paidNotifsAgain = rows("SELECT id FROM tbl_notifications WHERE user_id=? AND order_id=? AND notification_type='PAYMENT_CONFIRMED'", [$userA, $orderA]);
    assert_ok(count($paidNotifsAgain) === 1, '11b. No duplicate payment notification created');

    // 6 & 8. Persistence in DB + IDOR isolation (Customer B cannot see or mark read Customer A's notifications)
    assert_ok(unread_notification_count($userB) === 0, '8b. Customer B sees zero notifications from Customer A');
    $markedByB = mark_notification_read($userB, (int)$paidNotifs[0]['id']);
    assert_ok($markedByB === 0 && (int)row('SELECT is_read FROM tbl_notifications WHERE id=?', [$paidNotifs[0]['id']])['is_read'] === 0, '8c. Customer B cannot mark Customer A notification read');
    // Re-querying DB simulates logout/login persistence
    $persisted = user_notifications($userA, 10, 0);
    assert_ok(count($persisted) >= 3, '6. Notifications persist in MySQL across sessions');

    // 7. Admin updates status: CONFIRMED -> PREPARING -> SHIPPING (with real tracking) -> COMPLETED
    update_order_status($orderA, 1, 'CONFIRMED', ['customer_note' => 'Đã tiếp nhận đơn']);
    update_order_status($orderA, 1, 'PREPARING');
    update_order_status($orderA, 1, 'SHIPPING', ['shipping_carrier' => 'Giao Hàng Nhanh', 'tracking_code' => 'GHN99887766']);
    update_order_status($orderA, 1, 'COMPLETED');
    $ordADone = row('SELECT order_status, shipping_carrier, tracking_code, shipped_at, delivered_at FROM tbl_orders WHERE id=?', [$orderA]);
    assert_ok(
        $ordADone['order_status'] === 'COMPLETED'
        && $ordADone['shipping_carrier'] === 'Giao Hàng Nhanh'
        && $ordADone['tracking_code'] === 'GHN99887766'
        && $ordADone['shipped_at'] !== null
        && $ordADone['delivered_at'] !== null,
        '7. Full order status lifecycle + shipping tracking saved'
    );
    $histStatuses = array_column(rows('SELECT to_status FROM tbl_order_status_history WHERE order_id=? AND is_public=1 ORDER BY id ASC', [$orderA]), 'to_status');
    assert_ok(in_array('PREPARING', $histStatuses, true) && in_array('SHIPPING', $histStatuses, true) && in_array('COMPLETED', $histStatuses, true), '7b. Order status history recorded accurately');

    // 10. Two orders cannot share the same bank_reference
    $codeB = 'BHTEST' . strtoupper(bin2hex(random_bytes(4)));
    run("INSERT INTO tbl_orders(order_code,user_id,customer_name,phone,delivery_address,total_amount,payment_method)
         VALUES (?,?,?,'0902222222','456 Test St',37500000,'BANK')", [$codeB, $userB, 'Khách B Test']);
    $orderB = (int)db()->lastInsertId();
    $createdOrders[] = $orderB;
    assert_throws_domain(fn() => confirm_bank_transfer($orderB, 1, $refA, 37500000), '10. Reusing same bank_reference across two orders refused');

    // 12. Cancelling restores stock exactly once (cannot cancel twice)
    $codeC = 'BHTEST' . strtoupper(bin2hex(random_bytes(4)));
    run("INSERT INTO tbl_orders(order_code,user_id,customer_name,phone,delivery_address,total_amount,payment_method)
         VALUES (?,?,?,'0902222222','456 Test St',20000000,'BANK')", [$codeC, $userB, 'Khách B Test']);
    $orderC = (int)db()->lastInsertId();
    $createdOrders[] = $orderC;
    run("INSERT INTO tbl_order_items(order_id,product_id,product_name,variant_label,unit_price,quantity) VALUES (?,?,'Test Phone','128GB',20000000,2)", [$orderC, $testProductId]);
    run('UPDATE tbl_sanpham SET soluong = soluong - 2 WHERE id_sanpham = ?', [$testProductId]);
    update_order_status($orderC, 1, 'CANCELLED', ['cancel_reason' => 'Khách đổi ý']);
    assert_ok((int)row('SELECT soluong FROM tbl_sanpham WHERE id_sanpham=?', [$testProductId])['soluong'] === $origProductStock, '12. Stock restored on valid cancel');
    assert_throws_domain(fn() => update_order_status($orderC, 1, 'CANCELLED'), '12b. Second cancel refused (no double stock restore)');
    assert_ok((int)row('SELECT soluong FROM tbl_sanpham WHERE id_sanpham=?', [$testProductId])['soluong'] === $origProductStock, '12c. Stock unchanged after blocked second cancel');

    // 13. COD works separately: never auto-marked PAID, requires explicit Admin confirmation
    $codeCod = 'BHTEST' . strtoupper(bin2hex(random_bytes(4)));
    run("INSERT INTO tbl_orders(order_code,user_id,customer_name,phone,delivery_address,total_amount,payment_method)
         VALUES (?,?,?,'0901111111','123 Test St',15000000,'COD')", [$codeCod, $userA, 'Khách A Test']);
    $orderCod = (int)db()->lastInsertId();
    $createdOrders[] = $orderCod;
    update_order_status($orderCod, 1, 'CONFIRMED');
    update_order_status($orderCod, 1, 'PREPARING');
    assert_throws_domain(fn() => confirm_cod_payment($orderCod, 1), '13a. COD cannot be marked paid before SHIPPING/COMPLETED');
    update_order_status($orderCod, 1, 'SHIPPING');
    update_order_status($orderCod, 1, 'COMPLETED');
    $codAfterDeliver = row('SELECT payment_status FROM tbl_orders WHERE id=?', [$orderCod]);
    assert_ok($codAfterDeliver['payment_status'] === 'UNPAID', '13b. COD stays UNPAID until Admin explicitly confirms collection');
    confirm_cod_payment($orderCod, 1, 'Shipper đã nộp tiền');
    assert_ok(row('SELECT payment_status FROM tbl_orders WHERE id=?', [$orderCod])['payment_status'] === 'PAID', '13c. Explicit Admin COD confirmation sets PAID');

    // 14. Without SMTP configured, outbox records SKIPPED cleanly and never claims SENT
    if (!smtp_is_configured()) {
        $outbox = rows('SELECT status FROM tbl_email_outbox WHERE order_id=?', [$orderA]);
        $sentCount = count(array_filter($outbox, static fn($r) => $r['status'] === 'SENT'));
        assert_ok(count($outbox) >= 1 && $sentCount === 0, '14. Without SMTP, outbox never falsely claims SENT and website notifications work');
    }

    echo "All order, notification, timeline, COD and outbox integration checks PASSED\n";
} finally {
    foreach ($createdOrders as $oid) {
        run('DELETE FROM tbl_email_outbox WHERE order_id=?', [$oid]);
        run('DELETE FROM tbl_notifications WHERE order_id=?', [$oid]);
        run('DELETE FROM tbl_orders WHERE id=?', [$oid]);
    }
    foreach ($createdUsers as $uid) {
        run('DELETE FROM tbl_notifications WHERE user_id=?', [$uid]);
        run('DELETE FROM tbl_dangky WHERE id_dangky=?', [$uid]);
    }
    if ($testProductId !== null && $origProductStock !== null) {
        run('UPDATE tbl_sanpham SET soluong=? WHERE id_sanpham=?', [$origProductStock, $testProductId]);
    }
}
