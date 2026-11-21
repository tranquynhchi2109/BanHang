<?php
declare(strict_types=1);

/**
 * Isolated integration tests for:
 * - Customer notification center (all triggers, customer_note/shipping changes, dedup, IDOR)
 * - Order timeline builder & status history rules
 * - Hardened SMTP config validation, secret redaction & atomic email outbox claiming/retry
 * - BANK & COD payment flows and stock restoration
 * Uses unique per-run fixtures and cleans up completely in finally.
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

$runId = strtoupper(bin2hex(random_bytes(4)));
$createdUsers = [];
$createdOrders = [];
$createdOutboxIds = [];
$createdProductId = null;
$createdCategoryId = null;

try {
    // Dedicated isolated category & product (never mutates existing catalog rows)
    run('INSERT INTO tbl_danhmuc(tendanhmuc,thutu) VALUES (?,998)', ["Điện thoại ON {$runId}"]);
    $createdCategoryId = (int)db()->lastInsertId();

    run(
        "INSERT INTO tbl_sanpham(tensanpham,masp,giasp,soluong,hinhanh,tomtat,noidung,tinhtrang,id_danhmuc)
         VALUES (?,?,27500000,10,'ip15.jpg','','',1,?)",
        ["ON Phone {$runId}", "SKU-ON-{$runId}", $createdCategoryId]
    );
    $createdProductId = (int)db()->lastInsertId();

    // Two distinct test customers (A and B) for strict IDOR checks
    run(
        'INSERT INTO tbl_dangky(tenkhachhang,email,diachi,matkhau,dienthoai) VALUES (?,?,?,?,?)',
        ["Khách A {$runId}", "on_a_{$runId}@example.com", '123 Test St', password_hash('Password12345!', PASSWORD_DEFAULT), '0901111111']
    );
    $userA = (int)db()->lastInsertId();
    $createdUsers[] = $userA;

    run(
        'INSERT INTO tbl_dangky(tenkhachhang,email,diachi,matkhau,dienthoai) VALUES (?,?,?,?,?)',
        ["Khách B {$runId}", "on_b_{$runId}@example.com", '456 Test St', password_hash('Password12345!', PASSWORD_DEFAULT), '0902222222']
    );
    $userB = (int)db()->lastInsertId();
    $createdUsers[] = $userB;

    // 1. Customer A creates BANK order
    $codeA = "ON{$runId}A";
    run(
        "INSERT INTO tbl_orders(order_code,user_id,customer_name,phone,delivery_address,total_amount,payment_method)
         VALUES (?,?,?,'0901111111','123 Test St',37500000,'BANK')",
        [$codeA, $userA, "Khách A {$runId}"]
    );
    $orderA = (int)db()->lastInsertId();
    $createdOrders[] = $orderA;
    run("INSERT INTO tbl_order_items(order_id,product_id,product_name,variant_label,unit_price,quantity) VALUES (?,?,'ON Phone','1TB',37500000,1)", [$orderA, $createdProductId]);
    record_order_history($orderA, null, 'PENDING', 'CUSTOMER', $userA, 'Tạo đơn test', true);
    create_notification($userA, $orderA, 'ORDER_CREATED', 'Đặt hàng thành công!', "Đơn {$codeA} đã tạo.", link_to('order', ['code' => $codeA]), "order_created:{$orderA}");
    queue_order_email(row('SELECT * FROM tbl_orders WHERE id=?', [$orderA]), 'ORDER_CREATED', '', "email_created:{$orderA}");
    dispatch_email_outbox(5);

    assert_ok(unread_notification_count($userA) === 1, '1. Customer A receives exactly 1 order creation notification');

    // 2 & 3. Customer A reports transfer -> stays UNPAID, Admin pending reconciliation increases, no "Thanh toán thành công" yet
    $recBefore = admin_pending_reconciliation_count();
    assert_throws_domain(fn() => report_bank_transfer($codeA, $userB, 'Fake'), '8a. Customer B cannot report Customer A order');
    report_bank_transfer($codeA, $userA, 'FT cuối 1234');
    $ordARow = row('SELECT payment_status, payment_review_status FROM tbl_orders WHERE id=?', [$orderA]);
    assert_ok($ordARow['payment_status'] === 'UNPAID' && $ordARow['payment_review_status'] === 'REPORTED', '2. Customer report keeps order UNPAID');
    assert_ok(admin_pending_reconciliation_count() === $recBefore + 1, '3. Admin pending reconciliation counter increases');
    $paidNotifBefore = (int)(row("SELECT COUNT(*) AS n FROM tbl_notifications WHERE order_id=? AND notification_type='PAYMENT_CONFIRMED'", [$orderA])['n'] ?? 0);
    assert_ok($paidNotifBefore === 0, '2b. Reporting transfer does NOT send Thanh toán thành công notification');

    // Rejection notification trigger
    reject_bank_report($orderA, 1, 'Chưa thấy giao dịch trên sao kê.');
    $rejNotifs = rows("SELECT id FROM tbl_notifications WHERE user_id=? AND order_id=? AND notification_type='PAYMENT_REJECTED'", [$userA, $orderA]);
    assert_ok(count($rejNotifs) === 1, '2c. PAYMENT_REJECTED notification created for owner');
    report_bank_transfer($codeA, $userA, 'Báo lại sau kiểm tra');

    // Cannot confirm/prepare/ship BANK order before PAID
    assert_throws_domain(fn() => update_order_status($orderA, 1, 'CONFIRMED'), 'BANK order cannot be CONFIRMED before PAID');
    assert_throws_domain(fn() => update_order_status($orderA, 1, 'CANCELLED'), 'REPORTED order cannot be CANCELLED before reconciliation');

    // 9. Wrong amount cannot mark PAID
    $refA = "MBON_{$runId}_1";
    assert_throws_domain(fn() => confirm_bank_transfer($orderA, 1, $refA, 37000000), '9. Wrong received amount refused');

    // 4 & 5. Exact amount marks PAID + creates "Thanh toán thành công!" notification
    confirm_bank_transfer($orderA, 1, $refA, 37500000);
    $ordAPaid = row('SELECT payment_status, payment_review_status, bank_reference FROM tbl_orders WHERE id=?', [$orderA]);
    assert_ok($ordAPaid['payment_status'] === 'PAID' && $ordAPaid['payment_review_status'] === 'VERIFIED', '4. Admin confirms exact amount -> PAID');

    $paidNotifs = rows("SELECT * FROM tbl_notifications WHERE user_id=? AND order_id=? AND notification_type='PAYMENT_CONFIRMED'", [$userA, $orderA]);
    assert_ok(count($paidNotifs) === 1 && str_contains($paidNotifs[0]['title'], 'Thanh toán thành công') && str_contains($paidNotifs[0]['message'], '37.500.000'), '5. Customer A receives Thanh toán thành công notification');

    // 11. Duplicate confirmation cannot create second event or notification
    assert_throws_domain(fn() => confirm_bank_transfer($orderA, 1, $refA, 37500000), '11. Second confirmation refused');
    $paidNotifsAgain = rows("SELECT id FROM tbl_notifications WHERE user_id=? AND order_id=? AND notification_type='PAYMENT_CONFIRMED'", [$userA, $orderA]);
    assert_ok(count($paidNotifsAgain) === 1, '11b. No duplicate payment notification created');

    // 6 & 8. Persistence + IDOR isolation
    assert_ok(unread_notification_count($userB) === 0, '8b. Customer B sees zero notifications from Customer A');
    $markedByB = mark_notification_read($userB, (int)$paidNotifs[0]['id']);
    assert_ok($markedByB === 0 && (int)row('SELECT is_read FROM tbl_notifications WHERE id=?', [$paidNotifs[0]['id']])['is_read'] === 0, '8c. Customer B cannot mark Customer A notification read');
    assert_ok(count(user_notifications($userA, 20, 0)) >= 4, '6. Notifications persist in MySQL across sessions');

    // P1: save_order_notes() independent detection, dedup, no-op, admin_note isolation
    $countBeforeNotes = (int)row('SELECT COUNT(*) AS n FROM tbl_notifications WHERE user_id=? AND order_id=?', [$userA, $orderA])['n'];
    // (a) Admin changes ONLY customer_note -> customer MUST receive notification
    $resCN = save_order_notes($orderA, 1, ['shipping_carrier' => '', 'tracking_code' => '', 'admin_note' => '', 'customer_note' => 'Shop sẽ giao trong buổi sáng mai']);
    assert_ok($resCN['changed'] && $resCN['customer_note_notified'] && !$resCN['shipping_notified'], 'P1a. save_order_notes notifies customer when ONLY customer_note changes');
    $cnNotifs = rows("SELECT * FROM tbl_notifications WHERE user_id=? AND order_id=? AND notification_type='ORDER_CUSTOMER_NOTE'", [$userA, $orderA]);
    assert_ok(count($cnNotifs) === 1 && str_contains($cnNotifs[0]['message'], 'Shop sẽ giao trong buổi sáng mai'), 'P1b. ORDER_CUSTOMER_NOTE saved with exact customer note');
    // (b) Re-submitting identical data -> NO change, NO duplicate notification
    $resNoop = save_order_notes($orderA, 1, ['shipping_carrier' => '', 'tracking_code' => '', 'admin_note' => '', 'customer_note' => 'Shop sẽ giao trong buổi sáng mai']);
    assert_ok(!$resNoop['changed'] && !$resNoop['customer_note_notified'] && !$resNoop['shipping_notified'], 'P1c. save_order_notes does NOT notify when nothing changed');
    assert_ok(count(rows("SELECT id FROM tbl_notifications WHERE user_id=? AND order_id=? AND notification_type='ORDER_CUSTOMER_NOTE'", [$userA, $orderA])) === 1, 'P1d. No duplicate customer_note notification on re-submit');
    // (c) Changing ONLY admin_note -> NEVER notifies customer or writes public history
    $resAdminOnly = save_order_notes($orderA, 1, ['shipping_carrier' => '', 'tracking_code' => '', 'admin_note' => 'SECRET_ADMIN_INTERNAL_999', 'customer_note' => 'Shop sẽ giao trong buổi sáng mai']);
    assert_ok($resAdminOnly['changed'] && !$resAdminOnly['customer_note_notified'] && !$resAdminOnly['shipping_notified'], 'P1e. Changing only admin_note creates zero customer notifications');
    $leakedNotif = row("SELECT id FROM tbl_notifications WHERE order_id=? AND (title LIKE '%SECRET_ADMIN_INTERNAL_999%' OR message LIKE '%SECRET_ADMIN_INTERNAL_999%')", [$orderA]);
    $leakedPublicHist = row("SELECT id FROM tbl_order_status_history WHERE order_id=? AND is_public=1 AND note LIKE '%SECRET_ADMIN_INTERNAL_999%'", [$orderA]);
    assert_ok($leakedNotif === null && $leakedPublicHist === null, 'P1f. admin_note NEVER leaks to customer notifications or public history');
    // (d) Changing ONLY shipping_carrier / tracking_code -> notifies shipping update without duplicating customer_note
    $resShip = save_order_notes($orderA, 1, ['shipping_carrier' => 'Giao Hàng Nhanh', 'tracking_code' => 'GHN99887766', 'admin_note' => 'SECRET_ADMIN_INTERNAL_999', 'customer_note' => 'Shop sẽ giao trong buổi sáng mai']);
    assert_ok($resShip['changed'] && $resShip['shipping_notified'] && !$resShip['customer_note_notified'], 'P1g. Changing shipping info notifies shipping only');
    $shipNotifs = rows("SELECT * FROM tbl_notifications WHERE user_id=? AND order_id=? AND notification_type='ORDER_SHIPPING_INFO'", [$userA, $orderA]);
    assert_ok(count($shipNotifs) === 1 && str_contains($shipNotifs[0]['message'], 'GHN99887766'), 'P1h. ORDER_SHIPPING_INFO saved with tracking code');
    assert_ok((int)row('SELECT COUNT(*) AS n FROM tbl_notifications WHERE user_id=? AND order_id=?', [$userA, $orderA])['n'] === $countBeforeNotes + 2, 'P1i. Exactly 2 new notifications created across all save_order_notes tests');

    // 7. Full status lifecycle + notification for EVERY step + accurate timeline builder
    update_order_status($orderA, 1, 'CONFIRMED');
    update_order_status($orderA, 1, 'PREPARING');
    $midOrder = row('SELECT * FROM tbl_orders WHERE id=?', [$orderA]);
    $midHist = rows('SELECT from_status,to_status,note,created_at FROM tbl_order_status_history WHERE order_id=? AND is_public=1 ORDER BY id ASC', [$orderA]);
    $midTimeline = build_order_timeline($midOrder, $midHist);
    $statesByKey = array_column($midTimeline, 'state', 'key');
    assert_ok(
        $statesByKey['PENDING'] === 'done'
        && $statesByKey['CONFIRMED'] === 'done'
        && $statesByKey['PREPARING'] === 'current'
        && $statesByKey['SHIPPING'] === 'upcoming'
        && $statesByKey['COMPLETED'] === 'upcoming',
        'P2. build_order_timeline marks only reached/current steps and keeps future steps upcoming'
    );

    update_order_status($orderA, 1, 'SHIPPING');
    update_order_status($orderA, 1, 'COMPLETED');
    $allTypesA = array_column(rows('SELECT notification_type FROM tbl_notifications WHERE user_id=? AND order_id=?', [$userA, $orderA]), 'notification_type');
    foreach (['ORDER_CONFIRMED', 'ORDER_PREPARING', 'ORDER_SHIPPING', 'ORDER_COMPLETED', 'PAYMENT_CONFIRMED', 'PAYMENT_REJECTED', 'ORDER_SHIPPING_INFO', 'ORDER_CUSTOMER_NOTE'] as $reqType) {
        assert_ok(in_array($reqType, $allTypesA, true), "7. Notification type {$reqType} verified for order owner");
    }

    // 10. Two orders cannot share the same bank_reference
    $codeB = "ON{$runId}B";
    run("INSERT INTO tbl_orders(order_code,user_id,customer_name,phone,delivery_address,total_amount,payment_method) VALUES (?,?,?,'0902222222','456 Test St',37500000,'BANK')", [$codeB, $userB, "Khách B {$runId}"]);
    $orderB = (int)db()->lastInsertId();
    $createdOrders[] = $orderB;
    assert_throws_domain(fn() => confirm_bank_transfer($orderB, 1, $refA, 37500000), '10. Reusing same bank_reference across two orders refused');

    // 12. Cancelling restores stock exactly once + creates ORDER_CANCELLED notification + timeline shows cancelled
    $codeC = "ON{$runId}C";
    run("INSERT INTO tbl_orders(order_code,user_id,customer_name,phone,delivery_address,total_amount,payment_method) VALUES (?,?,?,'0902222222','456 Test St',20000000,'BANK')", [$codeC, $userB, "Khách B {$runId}"]);
    $orderC = (int)db()->lastInsertId();
    $createdOrders[] = $orderC;
    run("INSERT INTO tbl_order_items(order_id,product_id,product_name,variant_label,unit_price,quantity) VALUES (?,?,'ON Phone','128GB',20000000,2)", [$orderC, $createdProductId]);
    run('UPDATE tbl_sanpham SET soluong = soluong - 2 WHERE id_sanpham = ?', [$createdProductId]);
    update_order_status($orderC, 1, 'CANCELLED', ['cancel_reason' => 'Khách đổi ý']);
    assert_ok((int)row('SELECT soluong FROM tbl_sanpham WHERE id_sanpham=?', [$createdProductId])['soluong'] === 10, '12. Stock restored on valid cancel');
    assert_throws_domain(fn() => update_order_status($orderC, 1, 'CANCELLED'), '12b. Second cancel refused (no double stock restore)');
    assert_ok((int)row('SELECT soluong FROM tbl_sanpham WHERE id_sanpham=?', [$createdProductId])['soluong'] === 10, '12c. Stock unchanged after blocked second cancel');
    $cancelNotif = row("SELECT id FROM tbl_notifications WHERE user_id=? AND order_id=? AND notification_type='ORDER_CANCELLED'", [$userB, $orderC]);
    assert_ok($cancelNotif !== null, '12d. ORDER_CANCELLED notification created for owner');

    // 13. COD works separately: never auto-marked PAID, requires explicit Admin confirmation
    $codeCod = "ON{$runId}D";
    run("INSERT INTO tbl_orders(order_code,user_id,customer_name,phone,delivery_address,total_amount,payment_method) VALUES (?,?,?,'0901111111','123 Test St',15000000,'COD')", [$codeCod, $userA, "Khách A {$runId}"]);
    $orderCod = (int)db()->lastInsertId();
    $createdOrders[] = $orderCod;
    update_order_status($orderCod, 1, 'CONFIRMED');
    update_order_status($orderCod, 1, 'PREPARING');
    assert_throws_domain(fn() => confirm_cod_payment($orderCod, 1), '13a. COD cannot be marked paid before SHIPPING/COMPLETED');
    update_order_status($orderCod, 1, 'SHIPPING');
    update_order_status($orderCod, 1, 'COMPLETED');
    assert_ok(row('SELECT payment_status FROM tbl_orders WHERE id=?', [$orderCod])['payment_status'] === 'UNPAID', '13b. COD stays UNPAID until Admin explicitly confirms collection');
    confirm_cod_payment($orderCod, 1, 'Shipper đã nộp tiền');
    assert_ok(row('SELECT payment_status FROM tbl_orders WHERE id=?', [$orderCod])['payment_status'] === 'PAID', '13c. Explicit Admin COD confirmation sets PAID');

    // 14. P1 Hardened SMTP validation, password redaction & atomic email outbox lifecycle
    assert_ok(
        smtp_validate_config(['host' => 'smtp.example.com', 'port' => 587, 'secure' => 'none', 'user' => 'u@example.com', 'pass' => 'secret123', 'from_email' => 'u@example.com']) !== null,
        '14a. SMTP refuses AUTH LOGIN on unencrypted connection (secure=none)'
    );
    assert_ok(
        smtp_validate_config(['host' => 'smtp.example.com', 'port' => 587, 'secure' => 'bogus', 'user' => '', 'pass' => '', 'from_email' => 'u@example.com']) !== null,
        '14b. SMTP refuses invalid SMTP_SECURE mode (no silent plain-TCP downgrade)'
    );
    assert_ok(
        smtp_validate_config(['host' => 'smtp.example.com', 'port' => 587, 'secure' => 'tls', 'user' => 'u@example.com', 'pass' => 'secret123', 'from_email' => 'u@example.com']) === null,
        '14c. SMTP accepts valid TLS+AUTH configuration'
    );
    $redacted = smtp_sanitize_error('Auth failed for MySecretPass! and ' . base64_encode('MySecretPass!'), 'MySecretPass!');
    assert_ok(!str_contains($redacted, 'MySecretPass!') && !str_contains($redacted, base64_encode('MySecretPass!')), '14d. SMTP error sanitizer redacts raw & base64 password');

    // Outbox atomic claim, stale PROCESSING recovery, SENT immutability, SKIPPED non-flooding
    $dkSkip = "test_skip_{$runId}";
    run(
        "INSERT INTO tbl_email_outbox(order_id,user_id,recipient_email,event_type,dedup_key,subject,body_html,body_text,status,last_error)
         VALUES (?,?,'on_a_{$runId}@example.com','ORDER_CREATED',?,'Skipped Subject','<p>x</p>','x','SKIPPED','SMTP off')",
        [$orderA, $userA, $dkSkip]
    );
    $skipId = (int)db()->lastInsertId();
    $createdOutboxIds[] = $skipId;

    $dkPend = "test_pend_{$runId}";
    run(
        "INSERT INTO tbl_email_outbox(order_id,user_id,recipient_email,event_type,dedup_key,subject,body_html,body_text,status)
         VALUES (?,?,'on_a_{$runId}@example.com','PAYMENT_CONFIRMED',?,'Pending Subject','<p>ok</p>','ok','PENDING')",
        [$orderA, $userA, $dkPend]
    );
    $pendId = (int)db()->lastInsertId();
    $createdOutboxIds[] = $pendId;

    // Concurrent claim check: first claim takes $pendId -> PROCESSING; second claim CANNOT take it!
    $batch1 = claim_email_outbox_batch(20, "tok1_{$runId}");
    $ids1 = array_column($batch1, 'id');
    $batch2 = claim_email_outbox_batch(20, "tok2_{$runId}");
    $ids2 = array_column($batch2, 'id');
    assert_ok(in_array($pendId, $ids1, true) && !in_array($pendId, $ids2, true), '14e. Atomic claim prevents concurrent workers claiming same email');
    assert_ok(!in_array($skipId, $ids1, true) && !in_array($skipId, $ids2, true), '14f. SKIPPED historical emails are never auto-claimed');

    // Stale PROCESSING recovery (> 5 min)
    run('UPDATE tbl_email_outbox SET claimed_at = NOW() - INTERVAL 10 MINUTE WHERE id=?', [$pendId]);
    $recovered = recover_stale_outbox_rows(5);
    assert_ok($recovered >= 1 && row('SELECT status, claim_token FROM tbl_email_outbox WHERE id=?', [$pendId])['status'] === 'PENDING', '14g. Stale PROCESSING row recovered safely back to PENDING');

    // Controlled dispatch with mock sender -> SENT, and SENT is never re-sent
    $deliveredIds = [];
    $mockSender = static function (string $to, string $subj, string $html, string $txt, array $row) use (&$deliveredIds): void {
        $deliveredIds[] = (int)$row['id'];
    };
    $disp1 = dispatch_email_outbox(20, $mockSender);
    $disp2 = dispatch_email_outbox(20, $mockSender);
    assert_ok(in_array($pendId, $deliveredIds, true) && row('SELECT status, sent_at FROM tbl_email_outbox WHERE id=?', [$pendId])['status'] === 'SENT', '14h. Outbox dispatches PENDING -> SENT cleanly');
    assert_ok(count(array_filter($deliveredIds, static fn($x) => $x === $pendId)) === 1 && $disp2['sent'] === 0, '14i. SENT email is never re-sent on subsequent dispatch');
    assert_ok(row('SELECT status FROM tbl_email_outbox WHERE id=?', [$skipId])['status'] === 'SKIPPED', '14j. Historical SKIPPED email stayed SKIPPED (no unintended flood)');

    echo "All order, notification, timeline, COD, SMTP and outbox integration checks PASSED\n";
} finally {
    foreach ($createdOutboxIds as $obId) {
        run('DELETE FROM tbl_email_outbox WHERE id=?', [$obId]);
    }
    foreach ($createdOrders as $oid) {
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
        run('DELETE FROM tbl_product_variants WHERE product_id=?', [$createdProductId]);
        run('DELETE FROM tbl_sanpham WHERE id_sanpham=?', [$createdProductId]);
    }
    if ($createdCategoryId !== null) {
        run('DELETE FROM tbl_danhmuc WHERE id_danhmuc=?', [$createdCategoryId]);
    }
}
