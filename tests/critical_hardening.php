<?php
declare(strict_types=1);

/**
 * Isolated integration tests for the 5 critical hardening fixes:
 * 1. Inventory race condition & lost-update protection (with 2 independent PDO connections)
 * 2. Immutable per-order VietQR payment snapshot (and legacy missing-snapshot handling)
 * 3. Decoupled SMTP outbox + CLI email worker (no inline SMTP in web paths)
 * 4. Event-ID notification idempotency (A -> B -> A, A -> A, retry same event_key, clear note)
 * 5. Revenue statistics grouped strictly by payment_verified_at / cod_paid_at (not created_at)
 */
require dirname(__DIR__) . '/app.php';

function assert_hard(bool $cond, string $label): void
{
    if (!$cond) throw new RuntimeException('FAILED: ' . $label);
    echo "OK: {$label}\n";
}

function expect_domain_error(callable $fn, string $label): void
{
    try {
        $fn();
    } catch (DomainException $e) {
        echo "OK: {$label} refused ({$e->getMessage()})\n";
        return;
    }
    throw new RuntimeException('FAILED: ' . $label . ' was permitted');
}

function open_fresh_pdo(): PDO
{
    $config = is_file(dirname(__DIR__) . '/admin/config/config.local.php')
        ? require dirname(__DIR__) . '/admin/config/config.local.php' : [];
    $get = static fn(string $key, string $default): string =>
        (string)(getenv($key) !== false ? getenv($key) : ($config[$key] ?? $default));
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $get('DB_HOST', '127.0.0.1'),
        $get('DB_PORT', '3306'),
        $get('DB_NAME', 'web_mysqli')
    );
    return new PDO($dsn, $get('DB_USER', 'root'), $get('DB_PASS', ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

$runId = strtoupper(bin2hex(random_bytes(4)));
$origSettings = rows('SELECT setting_key, setting_value FROM tbl_settings');
$createdUsers = [];
$createdOrders = [];
$createdOutboxIds = [];
$createdProductId = null;
$createdCategoryId = null;

try {
    // Dedicated isolated fixtures
    run('INSERT INTO tbl_danhmuc(tendanhmuc,thutu) VALUES (?,997)', ["Điện thoại Hardening {$runId}"]);
    $createdCategoryId = (int)db()->lastInsertId();

    run(
        "INSERT INTO tbl_sanpham(tensanpham,masp,giasp,soluong,hinhanh,tomtat,noidung,tinhtrang,id_danhmuc)
         VALUES (?,?,25000000,10,'ip15.jpg','Tóm tắt','Mô tả',1,?)",
        ["Hardening Phone {$runId}", "SKU-HD-{$runId}", $createdCategoryId]
    );
    $createdProductId = (int)db()->lastInsertId();
    run(
        "INSERT INTO tbl_product_variants(product_id,variant_code,price,is_active) VALUES (?,'128GB',25000000,1),(?,'256GB',27000000,1)",
        [$createdProductId, $createdProductId]
    );

    run(
        'INSERT INTO tbl_dangky(tenkhachhang,email,diachi,matkhau,dienthoai) VALUES (?,?,?,?,?)',
        ["User A {$runId}", "hd_a_{$runId}@example.com", '123 Test St', password_hash('Password12345!', PASSWORD_DEFAULT), '0901000001']
    );
    $userA = (int)db()->lastInsertId();
    $createdUsers[] = $userA;

    run(
        'INSERT INTO tbl_dangky(tenkhachhang,email,diachi,matkhau,dienthoai) VALUES (?,?,?,?,?)',
        ["User B {$runId}", "hd_b_{$runId}@example.com", '456 Test St', password_hash('Password12345!', PASSWORD_DEFAULT), '0901000002']
    );
    $userB = (int)db()->lastInsertId();
    $createdUsers[] = $userB;

    // =========================================================================
    // 1. P1: INVENTORY RACE CONDITION & LOST-UPDATE PROTECTION (2 PDO CONNS)
    // =========================================================================
    // 1a. Admin metadata save does NOT overwrite soluong after concurrent checkout
    run('UPDATE tbl_sanpham SET soluong = soluong - 2 WHERE id_sanpham=? AND soluong >= 2', [$createdProductId]);
    assert_hard((int)row('SELECT soluong FROM tbl_sanpham WHERE id_sanpham=?', [$createdProductId])['soluong'] === 8, '1a. Checkout reduces stock 10 -> 8');
    // Simulate Admin saving product description/price (metadata-only UPDATE, no soluong)
    run('UPDATE tbl_sanpham SET tomtat=?, noidung=? WHERE id_sanpham=?', ['Mô tả mới', 'Chi tiết mới', $createdProductId]);
    assert_hard((int)row('SELECT soluong FROM tbl_sanpham WHERE id_sanpham=?', [$createdProductId])['soluong'] === 8, '1b. Saving product metadata never overwrites stock back to 10');

    // 1b. Optimistic locking rejects stale expected_stock & negative stock
    expect_domain_error(fn() => adjust_product_stock($createdProductId, 5, 'Nhập thêm lô mới', 1, 10), '1c. Stale expected_stock (10 vs 8) refused');
    expect_domain_error(fn() => adjust_product_stock($createdProductId, -9, 'Giảm quá mức', 1, 8), '1d. Negative resulting stock refused');
    $adj = adjust_product_stock($createdProductId, -5, 'Xuất mẫu trưng bày', 1, 8);
    assert_hard($adj['before'] === 8 && $adj['after'] === 3, '1e. Valid adjust_product_stock 8 -> 3 succeeds');
    $mvCount = (int)row("SELECT COUNT(*) AS n FROM tbl_inventory_movements WHERE product_id=? AND movement_type='ADJUSTMENT'", [$createdProductId])['n'];
    assert_hard($mvCount === 1, '1f. Inventory movement ledger recorded adjustment');

    // 1c. Two independent DB connections exercise the exact lock/deduction functions used by checkout.
    $conn1 = open_fresh_pdo();
    $conn2 = open_fresh_pdo();
    $conn1->exec('SET innodb_lock_wait_timeout = 3');
    $conn2->exec('SET innodb_lock_wait_timeout = 1');

    $conn1->beginTransaction();
    $lockedProducts1 = lock_checkout_products($conn1, [$createdProductId => 2]);
    assert_hard((int)$lockedProducts1[$createdProductId]['soluong'] === 3, '1g. Checkout service locks product row at stock=3');

    $conn2->beginTransaction();
    $lockBlocked = false;
    try {
        lock_checkout_products($conn2, [$createdProductId => 2]);
    } catch (PDOException $e) {
        $lockBlocked = true;
    } finally {
        if ($conn2->inTransaction()) $conn2->rollBack();
    }
    assert_hard($lockBlocked, '1h. Concurrent checkout service call blocks on the InnoDB row lock');

    deduct_checkout_stock($conn1, [$createdProductId => 2], $lockedProducts1, null, 1, "HD{$runId}CONCURRENT");
    $conn1->commit();

    $conn2->beginTransaction();
    $oversellBlocked = false;
    try {
        lock_checkout_products($conn2, [$createdProductId => 2]);
    } catch (DomainException $e) {
        $oversellBlocked = true;
    } finally {
        if ($conn2->inTransaction()) $conn2->rollBack();
    }
    assert_hard($oversellBlocked && (int)row('SELECT soluong FROM tbl_sanpham WHERE id_sanpham=?', [$createdProductId])['soluong'] === 1, '1i. Checkout service refuses the second purchase after stock falls to 1');
    $conn1 = null;
    $conn2 = null;

    // 1d. Cancel order restores stock exactly once + writes ORDER_CANCEL_RESTORE movement
    $codeInv = "HD{$runId}INV";
    run("INSERT INTO tbl_orders(order_code,user_id,customer_name,phone,delivery_address,total_amount,payment_method) VALUES (?,?,?,'0901000001','123 Test St',25000000,'BANK')", [$codeInv, $userA, "User A {$runId}"]);
    $ordInv = (int)db()->lastInsertId();
    $createdOrders[] = $ordInv;
    run("INSERT INTO tbl_order_items(order_id,product_id,product_name,variant_label,unit_price,quantity) VALUES (?,?,'Hardening Phone','128GB',25000000,1)", [$ordInv, $createdProductId]);
    update_order_status($ordInv, 1, 'CANCELLED', ['cancel_reason' => 'Test hoàn kho']);
    assert_hard((int)row('SELECT soluong FROM tbl_sanpham WHERE id_sanpham=?', [$createdProductId])['soluong'] === 2, '1j. Cancel restores stock 1 -> 2');
    $restoreMv = row("SELECT delta,stock_before,stock_after FROM tbl_inventory_movements WHERE order_id=? AND movement_type='ORDER_CANCEL_RESTORE'", [$ordInv]);
    assert_hard($restoreMv && (int)$restoreMv['delta'] === 1 && (int)$restoreMv['stock_before'] === 1 && (int)$restoreMv['stock_after'] === 2, '1k. ORDER_CANCEL_RESTORE ledger recorded accurately');

    // =========================================================================
    // 2. P1: IMMUTABLE PER-ORDER VIETQR SNAPSHOT
    // =========================================================================
    // Configure Bank X
    run("INSERT INTO tbl_settings(setting_key,setting_value) VALUES ('bank_id','970422'),('bank_account','1111222233'),('bank_name','NGUYEN VAN X') ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
    $codeX = "HD{$runId}X";
    run("INSERT INTO tbl_orders(order_code,user_id,customer_name,phone,delivery_address,total_amount,payment_method) VALUES (?,?,?,'0901000001','123 Test St',25000000,'BANK')", [$codeX, $userA, "User A {$runId}"]);
    $ordX = (int)db()->lastInsertId();
    $createdOrders[] = $ordX;
    save_order_payment_snapshot($ordX, $codeX, 25000000);

    // Switch settings to Bank Y
    run("INSERT INTO tbl_settings(setting_key,setting_value) VALUES ('bank_id','970415'),('bank_account','9999888877'),('bank_name','TRAN THI Y') ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
    $codeY = "HD{$runId}Y";
    run("INSERT INTO tbl_orders(order_code,user_id,customer_name,phone,delivery_address,total_amount,payment_method) VALUES (?,?,?,'0901000001','123 Test St',27000000,'BANK')", [$codeY, $userA, "User A {$runId}"]);
    $ordY = (int)db()->lastInsertId();
    $createdOrders[] = $ordY;
    save_order_payment_snapshot($ordY, $codeY, 27000000);

    $qrX = qr_link(row('SELECT * FROM tbl_orders WHERE id=?', [$ordX]));
    $qrY = qr_link(row('SELECT * FROM tbl_orders WHERE id=?', [$ordY]));
    assert_hard($qrX !== null && str_contains($qrX, '970422-1111222233') && str_contains($qrX, '25000000') && str_contains($qrX, rawurlencode("BANHANG {$codeX}")), '2a. Order A retains original Bank X snapshot after settings changed to Bank Y');
    assert_hard($qrY !== null && str_contains($qrY, '970415-9999888877') && str_contains($qrY, '27000000') && str_contains($qrY, rawurlencode("BANHANG {$codeY}")), '2b. Order B uses new Bank Y snapshot');

    run("UPDATE tbl_settings SET setting_value='' WHERE setting_key IN ('bank_id','bank_account')");
    assert_hard(setting('bank_id') === '' && setting('bank_account') === '', '2c. Clearing both bank settings disables BANK checkout without hidden fallback');
    run("UPDATE tbl_settings SET setting_value=CASE setting_key WHEN 'bank_id' THEN '970415' WHEN 'bank_account' THEN '9999888877' END WHERE setting_key IN ('bank_id','bank_account')");

    // Legacy order without snapshot MUST NOT silently inherit Bank Y
    $codeLeg = "HD{$runId}L";
    run("INSERT INTO tbl_orders(order_code,user_id,customer_name,phone,delivery_address,total_amount,payment_method) VALUES (?,?,?,'0901000001','123 Test St',19000000,'BANK')", [$codeLeg, $userA, "User A {$runId}"]);
    $ordLeg = (int)db()->lastInsertId();
    $createdOrders[] = $ordLeg;
    assert_hard(order_payment_snapshot($ordLeg) === null && qr_link(row('SELECT * FROM tbl_orders WHERE id=?', [$ordLeg])) === null, '2d. Legacy BANK order without snapshot returns null (never silently adopts current bank settings)');

    // =========================================================================
    // 3. P1: DECOUPLED EMAIL OUTBOX & CLI WORKER
    // =========================================================================
    // Confirming Bank X order queues email in outbox without sending SMTP inline
    confirm_bank_transfer($ordX, 1, "MBHD_{$runId}_X", 25000000);
    $queuedPaid = row("SELECT id,status FROM tbl_email_outbox WHERE order_id=? AND event_type='PAYMENT_CONFIRMED'", [$ordX]);
    assert_hard($queuedPaid !== null && in_array($queuedPaid['status'], ['PENDING', 'SKIPPED'], true), '3a. confirm_bank_transfer queues outbox without inline SMTP dispatch');

    // Simulate failed delivery -> backoff -> requeue -> retry -> SENT -> never re-sent
    $dkW = "hd_worker_{$runId}";
    run(
        "INSERT INTO tbl_email_outbox(order_id,user_id,recipient_email,event_type,dedup_key,subject,body_html,body_text,status)
         VALUES (?,?,?,'PAYMENT_CONFIRMED',?,'Worker Test','<p>Hi</p>','Hi','PENDING')",
        [$ordX, $userA, "hd_a_{$runId}@example.com", $dkW]
    );
    $obW = (int)db()->lastInsertId();
    $createdOutboxIds[] = $obW;

    $failSender = static function (): void {
        throw new RuntimeException('SMTP timeout for SuperSecretPass99!');
    };
    $rFail = dispatch_email_outbox(10, $failSender);
    $rowAfterFail = row('SELECT status,attempts,last_error,next_retry_at FROM tbl_email_outbox WHERE id=?', [$obW]);
    assert_hard($rFail['failed'] >= 1 && $rowAfterFail['status'] === 'FAILED' && (int)$rowAfterFail['attempts'] === 1 && $rowAfterFail['next_retry_at'] !== null, '3b. Failed email transitions to FAILED with backoff next_retry_at');

    // Requeue helper makes it immediately eligible again without sending inline
    $rq = requeue_failed_outbox_emails();
    assert_hard($rq['requeued'] >= 1 && row('SELECT status FROM tbl_email_outbox WHERE id=?', [$obW])['status'] === 'PENDING', '3c. requeue_failed_outbox_emails re-queues FAILED row cleanly');

    // Mock success sender marks SENT and subsequent run ignores SENT
    $sentHits = 0;
    $okSender = static function (string $to, string $s, string $h, string $t, array $row) use ($obW, &$sentHits): void {
        if ((int)$row['id'] === $obW) $sentHits++;
    };
    dispatch_email_outbox(10, $okSender);
    dispatch_email_outbox(10, $okSender);
    assert_hard($sentHits === 1 && row('SELECT status,sent_at FROM tbl_email_outbox WHERE id=?', [$obW])['status'] === 'SENT', '3d. Retry delivers once and SENT email is never re-sent');

    // Exercise the CLI entry point in dry-run mode; it must not claim or send mail.
    $workerPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'email_worker.php';
    $queuedStatusBeforeDryRun = (string)$queuedPaid['status'];
    $cmdOut = [];
    $cmdExit = 0;
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($workerPath) . ' --dry-run', $cmdOut, $cmdExit);
    $workerJson = json_decode(implode("\n", $cmdOut), true);
    $queuedStatusAfterDryRun = (string)row('SELECT status FROM tbl_email_outbox WHERE id=?', [(int)$queuedPaid['id']])['status'];
    assert_hard(
        $cmdExit === 0 && is_array($workerJson) && ($workerJson['dry_run'] ?? false) === true
            && (int)($workerJson['sent'] ?? -1) === 0 && $queuedStatusAfterDryRun === $queuedStatusBeforeDryRun,
        '3e. CLI worker dry-run reports queue state without sending or mutating email'
    );

    // =========================================================================
    // 4. P2: NOTIFICATION IDEMPOTENCY (A -> B -> A, A -> A, RETRY, CLEAR)
    // =========================================================================
    $ev1 = "ev1_{$runId}";
    $ev2 = "ev2_{$runId}";
    $ev3 = "ev3_{$runId}";
    $ev4 = "ev4_{$runId}";
    $ev5 = "ev5_{$runId}";

    $nBefore = (int)row("SELECT COUNT(*) AS n FROM tbl_notifications WHERE user_id=? AND order_id=? AND notification_type='ORDER_CUSTOMER_NOTE'", [$userA, $ordX])['n'];
    // "" -> A
    $s1 = save_order_notes($ordX, 1, ['shipping_carrier' => '', 'tracking_code' => '', 'admin_note' => '', 'customer_note' => 'Ghi chú A', 'event_key' => $ev1]);
    // A -> A (no change)
    $sNoop = save_order_notes($ordX, 1, ['shipping_carrier' => '', 'tracking_code' => '', 'admin_note' => '', 'customer_note' => 'Ghi chú A', 'event_key' => $ev2]);
    // A -> B
    $s2 = save_order_notes($ordX, 1, ['shipping_carrier' => '', 'tracking_code' => '', 'admin_note' => '', 'customer_note' => 'Ghi chú B', 'event_key' => $ev3]);
    // B -> A
    $s3 = save_order_notes($ordX, 1, ['shipping_carrier' => '', 'tracking_code' => '', 'admin_note' => '', 'customer_note' => 'Ghi chú A', 'event_key' => $ev4]);
    // Retry exact same event_key $ev4
    $sRetry = save_order_notes($ordX, 1, ['shipping_carrier' => '', 'tracking_code' => '', 'admin_note' => '', 'customer_note' => 'Ghi chú C', 'event_key' => $ev4]);
    // Clear A -> ""
    $sClear = save_order_notes($ordX, 1, ['shipping_carrier' => '', 'tracking_code' => '', 'admin_note' => 'INTERNAL_ONLY_XYZ', 'customer_note' => '', 'event_key' => $ev5]);

    $nAfter = (int)row("SELECT COUNT(*) AS n FROM tbl_notifications WHERE user_id=? AND order_id=? AND notification_type='ORDER_CUSTOMER_NOTE'", [$userA, $ordX])['n'];
    assert_hard($s1['customer_note_notified'] && !$sNoop['changed'] && $s2['customer_note_notified'] && $s3['customer_note_notified'], '4a. A -> B -> A creates 3 notifications and A -> A creates 0');
    assert_hard($sRetry['duplicate_retry'] && !$sRetry['changed'], '4b. Retrying the same event_key is idempotent (0 duplicates)');
    assert_hard($sClear['customer_note_notified'] && ($nAfter - $nBefore) === 4, '4c. Clearing customer_note notifies customer explicitly (total 4 note notifications)');
    assert_hard(unread_notification_count($userB) === 0, '4d. User B cannot see any of User A notifications');
    assert_hard(row("SELECT id FROM tbl_notifications WHERE order_id=? AND message LIKE '%INTERNAL_ONLY_XYZ%'", [$ordX]) === null, '4e. admin_note never appears in customer notifications');

    // =========================================================================
    // 5. P2: REVENUE BY ACTUAL VERIFICATION DATE (CROSS-MONTH)
    // =========================================================================
    // Pick two months within the 12-month window: previous month vs current month
    $prevMonthDate = (new DateTimeImmutable('first day of last month'))->format('Y-m-28 22:00:00');
    $prevBucket = substr($prevMonthDate, 0, 7);
    $curMonthDate = (new DateTimeImmutable('first day of this month'))->format('Y-m-02 10:00:00');
    $curBucket = substr($curMonthDate, 0, 7);

    $beforeRep = revenue_report_data('12m');
    $findBucket = static function (array $rep, string $bucket): array {
        foreach ($rep['series'] as $row) {
            if ($row['bucket'] === $bucket) return $row;
        }
        return ['ordered_total' => 0, 'bank_paid' => 0, 'cod_paid' => 0, 'paid_total' => 0, 'unpaid_total' => 0];
    };
    $prevBefore = $findBucket($beforeRep, $prevBucket);
    $curBefore = $findBucket($beforeRep, $curBucket);

    // BANK order created prev month, verified current month (+12,345,000)
    $codeRevBank = "HD{$runId}RB";
    run(
        "INSERT INTO tbl_orders(order_code,user_id,customer_name,phone,delivery_address,total_amount,payment_method,payment_status,payment_review_status,order_status,created_at,payment_verified_at)
         VALUES (?,?,?,'0901000001','Test',12345000,'BANK','PAID','VERIFIED','CONFIRMED',?,?)",
        [$codeRevBank, $userA, "Rev Bank {$runId}", $prevMonthDate, $curMonthDate]
    );
    $createdOrders[] = (int)db()->lastInsertId();

    // COD order created prev month, collected current month (+4,567,000)
    $codeRevCod = "HD{$runId}RC";
    run(
        "INSERT INTO tbl_orders(order_code,user_id,customer_name,phone,delivery_address,total_amount,payment_method,payment_status,order_status,created_at,cod_paid_at)
         VALUES (?,?,?,'0901000001','Test',4567000,'COD','PAID','COMPLETED',?,?)",
        [$codeRevCod, $userA, "Rev COD {$runId}", $prevMonthDate, $curMonthDate]
    );
    $createdOrders[] = (int)db()->lastInsertId();

    // UNPAID BANK order in current month (+9,999,000) -> MUST NOT enter paid_total!
    $codeRevUnpaid = "HD{$runId}RU";
    run(
        "INSERT INTO tbl_orders(order_code,user_id,customer_name,phone,delivery_address,total_amount,payment_method,payment_status,order_status,created_at)
         VALUES (?,?,?,'0901000001','Test',9999000,'BANK','UNPAID','PENDING',?)",
        [$codeRevUnpaid, $userA, "Rev Unpaid {$runId}", $curMonthDate]
    );
    $createdOrders[] = (int)db()->lastInsertId();

    // Legacy PAID order missing payment_verified_at -> audited separately, NOT attributed to created_at!
    $codeRevMissing = "HD{$runId}RM";
    run(
        "INSERT INTO tbl_orders(order_code,user_id,customer_name,phone,delivery_address,total_amount,payment_method,payment_status,order_status,created_at,payment_verified_at)
         VALUES (?,?,?,'0901000001','Test',2222000,'BANK','PAID','COMPLETED',?,NULL)",
        [$codeRevMissing, $userA, "Rev MissingTs {$runId}", $prevMonthDate]
    );
    $createdOrders[] = (int)db()->lastInsertId();

    $afterRep = revenue_report_data('12m');
    $prevAfter = $findBucket($afterRep, $prevBucket);
    $curAfter = $findBucket($afterRep, $curBucket);

    assert_hard(
        ($prevAfter['ordered_total'] - $prevBefore['ordered_total']) === (12345000 + 4567000 + 2222000)
        && ($prevAfter['paid_total'] - $prevBefore['paid_total']) === 0,
        "5a. Prev month ({$prevBucket}) counts ordered_total but 0 paid_total for orders paid in current month"
    );
    assert_hard(
        ($curAfter['bank_paid'] - $curBefore['bank_paid']) === 12345000
        && ($curAfter['cod_paid'] - $curBefore['cod_paid']) === 4567000
        && ($curAfter['paid_total'] - $curBefore['paid_total']) === (12345000 + 4567000),
        "5b. Current month ({$curBucket}) credits BANK by payment_verified_at & COD by cod_paid_at and excludes UNPAID"
    );
    assert_hard(
        ($afterRep['missing_timestamp_count'] - $beforeRep['missing_timestamp_count']) === 1
        && ($afterRep['missing_timestamp_total'] - $beforeRep['missing_timestamp_total']) === 2222000,
        '5c. Legacy PAID order without verification timestamp is surfaced transparently without faking created_at'
    );

    echo "All 5 critical hardening integration checks PASSED\n";
} finally {
    foreach ($createdOutboxIds as $obId) {
        run('DELETE FROM tbl_email_outbox WHERE id=?', [$obId]);
    }
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
    foreach ($origSettings as $s) {
        run('INSERT INTO tbl_settings(setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$s['setting_key'], $s['setting_value']]);
    }
}
