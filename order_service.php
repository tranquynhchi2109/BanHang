<?php
declare(strict_types=1);

/**
 * BanHang Order Workflow, Inventory Ledger, VietQR Snapshot, Timeline & Revenue Service.
 * Loaded by app.php; direct HTTP access is denied via .htaccess.
 */

function bank_display_label(string $bankId): string
{
    $code = strtoupper(trim($bankId));
    if (in_array($code, ['970422', 'MB', 'MBBANK'], true)) {
        return 'MB Bank (Ngân hàng Quân đội)';
    }
    return $bankId !== '' ? $bankId : 'Ngân hàng nhận chuyển khoản';
}

/**
 * Lock and validate all requested products in stable ID order.
 * The storefront and integration tests use this exact production primitive.
 *
 * @param array<int,int> $requested product_id => aggregate quantity
 * @return array<int,array<string,mixed>> product rows indexed by product ID
 */
function lock_checkout_products(PDO $pdo, array $requested): array
{
    if (!$requested) throw new DomainException('Giỏ hàng đang trống.');
    $ids = [];
    foreach ($requested as $productId => $quantity) {
        $id = (int)$productId;
        $qty = (int)$quantity;
        if ($id < 1 || $qty < 1) throw new DomainException('Số lượng đặt hàng không hợp lệ.');
        $ids[] = $id;
    }
    $ids = array_values(array_unique($ids));
    sort($ids, SORT_NUMERIC);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        'SELECT p.id_sanpham,p.tensanpham,p.giasp,p.soluong,p.tinhtrang,c.tendanhmuc
         FROM tbl_sanpham p
         JOIN tbl_danhmuc c ON c.id_danhmuc=p.id_danhmuc
         WHERE p.id_sanpham IN (' . $placeholders . ')
         ORDER BY p.id_sanpham
         FOR UPDATE'
    );
    $stmt->execute($ids);
    $productMap = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $product) {
        $productMap[(int)$product['id_sanpham']] = $product;
    }
    foreach ($requested as $productId => $quantity) {
        $id = (int)$productId;
        $product = $productMap[$id] ?? null;
        if (!$product) throw new DomainException('Sản phẩm không còn tồn tại.');
        if ((int)$product['tinhtrang'] !== 1 || (int)$product['soluong'] < (int)$quantity) {
            throw new DomainException('Sản phẩm không đủ tồn kho. Vui lòng cập nhật giỏ hàng.');
        }
    }
    return $productMap;
}

/** Deduct checkout stock and append inventory movements on the same PDO transaction. */
function deduct_checkout_stock(
    PDO $pdo,
    array $requested,
    array $productMap,
    ?int $orderId,
    int $customerId,
    string $orderCode
): void {
    ksort($requested, SORT_NUMERIC);
    $update = $pdo->prepare('UPDATE tbl_sanpham SET soluong=soluong-? WHERE id_sanpham=? AND soluong>=?');
    $movement = $pdo->prepare(
        "INSERT INTO tbl_inventory_movements
         (product_id,order_id,movement_type,delta,stock_before,stock_after,reason,actor_type,actor_id)
         VALUES (?,?,'ORDER_DEDUCT',?,?,?,?, 'CUSTOMER',?)"
    );
    foreach ($requested as $productId => $quantity) {
        $id = (int)$productId;
        $qty = (int)$quantity;
        $product = $productMap[$id] ?? null;
        if (!$product || $qty < 1) throw new RuntimeException('Dữ liệu tồn kho checkout không hợp lệ.');
        $before = (int)$product['soluong'];
        $update->execute([$qty, $id, $qty]);
        if ($update->rowCount() !== 1) throw new RuntimeException('Không thể cập nhật kho.');
        $movement->execute([
            $id, $orderId, -$qty, $before, $before - $qty,
            "Trừ tồn kho khi khách đặt đơn {$orderCode}", $customerId,
        ]);
    }
}

/**
 * Create an order using the exact stock-locking primitives used by the storefront.
 * The controller clears the session cart only after this transaction commits.
 */
function create_order_from_cart(array $user, array $cart, array $data): array
{
    $customerId = (int)($user['id_dangky'] ?? 0);
    if ($customerId < 1) throw new DomainException('Vui lòng đăng nhập trước khi đặt hàng.');
    if (!$cart) throw new DomainException('Giỏ hàng đang trống.');
    $name = trim((string)($data['name'] ?? ''));
    $phone = trim((string)($data['phone'] ?? ''));
    $address = trim((string)($data['address'] ?? ''));
    $note = trim((string)($data['note'] ?? ''));
    $payment = (string)($data['payment'] ?? 'COD');
    if (mb_strlen($name) < 2 || mb_strlen($name) > 160 ||
        !preg_match('/^[0-9+().\\-\\s]{9,25}$/', $phone) ||
        mb_strlen($address) < 10 || mb_strlen($address) > 500 || mb_strlen($note) > 500 ||
        !in_array($payment, ['COD', 'BANK'], true)) {
        throw new DomainException('Thông tin giao hàng không hợp lệ.');
    }
    if ($payment === 'BANK' && (setting('bank_id') === '' || setting('bank_account') === '')) {
        throw new DomainException('Cửa hàng chưa cấu hình nhận chuyển khoản. Hãy chọn COD.');
    }

    $entries = [];
    $requested = [];
    foreach ($cart as $cartKey => $quantity) {
        [$id, $variant] = parse_cart_key((string)$cartKey);
        $quantity = (int)$quantity;
        if ($id < 1 || $quantity < 1 || $quantity > 20) {
            throw new DomainException('Số lượng đặt hàng không hợp lệ.');
        }
        $entries[] = ['id' => $id, 'variant' => $variant, 'qty' => $quantity];
        $requested[$id] = ($requested[$id] ?? 0) + $quantity;
    }

    db()->beginTransaction();
    try {
        $productMap = lock_checkout_products(db(), $requested);
        foreach ($entries as &$entry) {
            $product = $productMap[$entry['id']];
            if (is_phone_product($product) && $entry['variant'] === 'BASE') $entry['variant'] = '128GB';
            if (!is_phone_product($product)) $entry['variant'] = 'BASE';
        }
        unset($entry);

        $total = 0;
        $lines = [];
        foreach ($entries as $entry) {
            $product = $productMap[$entry['id']];
            $price = variant_price($product, $entry['variant'], true);
            if ($price <= 0) throw new DomainException('Giá sản phẩm không hợp lệ.');
            $total += $price * $entry['qty'];
            if ($total > 1000000000000) throw new DomainException('Giá trị đơn hàng vượt giới hạn.');
            $lines[] = [
                'id' => $entry['id'], 'name' => $product['tensanpham'],
                'variant' => $entry['variant'], 'price' => $price, 'qty' => $entry['qty'],
            ];
        }
        $expectedTotal = filter_var($data['expected_total'] ?? null, FILTER_VALIDATE_INT);
        if ($expectedTotal === false || $expectedTotal !== $total) {
            throw new DomainException('Giá hoặc tổng tiền đã thay đổi. Vui lòng kiểm tra lại giỏ hàng trước khi đặt.');
        }

        $code = 'BH' . strtoupper(bin2hex(random_bytes(6)));
        run(
            'INSERT INTO tbl_orders(order_code,user_id,customer_name,phone,delivery_address,note,total_amount,payment_method)
             VALUES (?,?,?,?,?,?,?,?)',
            [$code, $customerId, $name, $phone, $address, $note, $total, $payment]
        );
        $orderId = (int)db()->lastInsertId();
        if ($payment === 'BANK') save_order_payment_snapshot($orderId, $code, $total);
        foreach ($lines as $line) {
            run(
                'INSERT INTO tbl_order_items(order_id,product_id,product_name,variant_label,unit_price,quantity)
                 VALUES (?,?,?,?,?,?)',
                [$orderId, $line['id'], $line['name'], $line['variant'], $line['price'], $line['qty']]
            );
        }
        deduct_checkout_stock(db(), $requested, $productMap, $orderId, $customerId, $code);

        $orderLink = link_to('order', ['code' => $code]);
        $initNote = $payment === 'BANK'
            ? 'Đơn hàng chuyển khoản VietQR đã được tạo, đang chờ khách chuyển khoản và cửa hàng đối soát.'
            : 'Đơn hàng thanh toán khi nhận hàng (COD) đã được tạo, đang chờ cửa hàng xác nhận.';
        record_order_history($orderId, null, 'PENDING', 'CUSTOMER', $customerId, $initNote, true);
        create_notification(
            $customerId, $orderId, 'ORDER_CREATED', 'Đặt hàng thành công!',
            "Đơn hàng {$code} với tổng giá trị " . money($total) . " (" . payment_method_label($payment) . ") đã được ghi nhận."
                . ($payment === 'BANK' ? ' Vui lòng chuyển khoản đúng nội dung để cửa hàng đối soát.' : ' Cửa hàng sẽ sớm xác nhận và chuẩn bị hàng.'),
            $orderLink, "order_created:{$orderId}"
        );
        $createdOrder = row('SELECT * FROM tbl_orders WHERE id=?', [$orderId]);
        if (!$createdOrder) throw new RuntimeException('Đã tạo đơn nhưng không thể đọc lại thông tin đơn hàng.');
        queue_order_email($createdOrder, 'ORDER_CREATED', $initNote, "email_created:{$orderId}");
        db()->commit();
        return $createdOrder;
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}

function save_order_payment_snapshot(int $orderId, string $orderCode, int $totalAmount): array
{
    $bankId = strtoupper(trim(setting('bank_id')));
    $bankAccount = trim(setting('bank_account'));
    $bankName = trim(setting('bank_name'));
    if (!preg_match('/^[A-Z0-9]{2,15}$/', $bankId) || !preg_match('/^[0-9]{6,24}$/', $bankAccount)) {
        throw new DomainException('Cửa hàng chưa cấu hình tài khoản ngân hàng hợp lệ để tạo đơn chuyển khoản.');
    }
    if ($totalAmount <= 0) {
        throw new DomainException('Số tiền chuyển khoản của đơn hàng không hợp lệ.');
    }
    $transferContent = 'BANHANG ' . $orderCode;
    run(
        'INSERT INTO tbl_order_payment_details(order_id,bank_id,bank_account,bank_account_name,expected_amount,transfer_content)
         VALUES (?,?,?,?,?,?)',
        [$orderId, $bankId, $bankAccount, $bankName, $totalAmount, $transferContent]
    );
    return [
        'order_id' => $orderId,
        'bank_id' => $bankId,
        'bank_account' => $bankAccount,
        'bank_account_name' => $bankName,
        'expected_amount' => $totalAmount,
        'transfer_content' => $transferContent,
    ];
}

/**
 * Returns immutable VietQR payment snapshot for an order, or null for legacy orders
 * created before per-order snapshots existed (never silently substitutes current settings).
 */
function order_payment_snapshot(int $orderId): ?array
{
    if ($orderId < 1) return null;
    return row(
        'SELECT order_id,bank_id,bank_account,bank_account_name,expected_amount,transfer_content,created_at
         FROM tbl_order_payment_details WHERE order_id=? LIMIT 1',
        [$orderId]
    );
}

function record_inventory_movement(
    int $productId,
    ?int $orderId,
    string $movementType,
    int $delta,
    int $stockBefore,
    int $stockAfter,
    string $reason,
    string $actorType,
    int $actorId
): void {
    run(
        'INSERT INTO tbl_inventory_movements(product_id,order_id,movement_type,delta,stock_before,stock_after,reason,actor_type,actor_id)
         VALUES (?,?,?,?,?,?,?,?,?)',
        [$productId, $orderId, $movementType, $delta, $stockBefore, $stockAfter, mb_substr(trim($reason), 0, 300), $actorType, $actorId]
    );
}

/**
 * Atomic, race-safe inventory adjustment with InnoDB FOR UPDATE lock + optional optimistic check.
 * Stock is shared per product at tbl_sanpham.soluong (not per phone capacity variant).
 */
function adjust_product_stock(
    int $productId,
    int $delta,
    string $reason,
    int $adminId,
    ?int $expectedStock = null
): array {
    $reason = trim($reason);
    if ($delta === 0 || abs($delta) > 1000000) {
        throw new DomainException('Số lượng điều chỉnh tồn kho phải khác 0 và trong giới hạn cho phép.');
    }
    if (mb_strlen($reason) < 3 || mb_strlen($reason) > 300 || preg_match('/[\x00-\x1F\x7F]/', $reason)) {
        throw new DomainException('Vui lòng nhập lý do điều chỉnh tồn kho (3–300 ký tự).');
    }

    db()->beginTransaction();
    try {
        $prod = row('SELECT id_sanpham,tensanpham,soluong FROM tbl_sanpham WHERE id_sanpham=? FOR UPDATE', [$productId]);
        if (!$prod) throw new DomainException('Không tìm thấy sản phẩm để điều chỉnh tồn kho.');

        $before = (int)$prod['soluong'];
        if ($expectedStock !== null && $before !== $expectedStock) {
            throw new DomainException("Tồn kho thực tế hiện là {$before} (khác mức {$expectedStock} bạn đang xem do có đơn hàng hoặc điều chỉnh khác). Vui lòng tải lại trang rồi thao tác lại.");
        }
        $after = $before + $delta;
        if ($after < 0) {
            throw new DomainException("Không thể điều chỉnh tồn kho xuống âm (hiện còn {$before}, yêu cầu thay đổi {$delta}).");
        }
        $changed = run(
            'UPDATE tbl_sanpham SET soluong = soluong + ? WHERE id_sanpham=? AND soluong + ? >= 0',
            [$delta, $productId, $delta]
        );
        if ($changed !== 1) {
            throw new RuntimeException('Không thể cập nhật tồn kho sản phẩm.');
        }
        record_inventory_movement($productId, null, 'ADJUSTMENT', $delta, $before, $after, $reason, 'ADMIN', $adminId);
        db()->commit();
        return ['product_id' => $productId, 'before' => $before, 'after' => $after, 'delta' => $delta];
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}

function admin_send_order_notification(int $customerId, int $orderId, int $adminId, string $title, string $message): void
{
    $title = trim($title);
    $message = trim($message);
    if (mb_strlen($title) < 3 || mb_strlen($title) > 180 || mb_strlen($message) < 5 || mb_strlen($message) > 600) {
        throw new DomainException('Tiêu đề (3–180 ký tự) và nội dung thông báo (5–600 ký tự) không hợp lệ.');
    }
    $order = row('SELECT id,order_code,user_id FROM tbl_orders WHERE id=? AND user_id=? LIMIT 1', [$orderId, $customerId]);
    if (!$order) {
        throw new DomainException('Đơn hàng không thuộc khách hàng này.');
    }
    $link = link_to('order', ['code' => $order['order_code']]);
    create_notification($customerId, $orderId, 'ADMIN_MESSAGE', $title, $message, $link, null);
    record_order_history($orderId, null, 'MESSAGE', 'ADMIN', $adminId, "Gửi thông báo tới khách: {$title}", false);
}

function record_order_history(
    int $orderId,
    ?string $fromStatus,
    string $toStatus,
    string $actorType,
    int $actorId,
    string $note = '',
    bool $isPublic = true
): void {
    run(
        'INSERT INTO tbl_order_status_history(order_id,from_status,to_status,actor_type,actor_id,note,is_public)
         VALUES (?,?,?,?,?,?,?)',
        [$orderId, $fromStatus, mb_substr($toStatus, 0, 30), $actorType, $actorId, mb_substr(trim($note), 0, 500), $isPublic ? 1 : 0]
    );
}

function order_status_transitions(): array
{
    return [
        'PENDING' => ['CONFIRMED', 'CANCELLED'],
        'CONFIRMED' => ['PREPARING', 'SHIPPING', 'CANCELLED'],
        'PREPARING' => ['SHIPPING', 'CANCELLED'],
        'SHIPPING' => ['COMPLETED'],
        'COMPLETED' => [],
        'CANCELLED' => [],
    ];
}

/**
 * Single source of truth for rendering the customer-facing order timeline
 * in both server-rendered HTML (pages/main.php) and JSON polling (api.php).
 */
function build_order_timeline(array $order, array $publicHistory): array
{
    $reached = [];
    foreach ($publicHistory as $hItem) {
        $st = (string)($hItem['to_status'] ?? '');
        if ($st !== '' && !isset($reached[$st])) {
            $reached[$st] = (string)($hItem['created_at'] ?? '');
        }
    }
    $curStatus = (string)($order['order_status'] ?? 'PENDING');
    $steps = [
        'PENDING' => 'Đã đặt hàng',
        'CONFIRMED' => 'Đã xác nhận',
        'PREPARING' => 'Đang chuẩn bị hàng',
        'SHIPPING' => 'Đang giao hàng',
        'COMPLETED' => 'Đã giao thành công',
    ];
    $stepOrder = array_keys($steps);
    $curIdx = array_search($curStatus, $stepOrder, true);
    $timeline = [];

    foreach ($steps as $stKey => $stLabel) {
        $idx = array_search($stKey, $stepOrder, true);
        $state = 'upcoming';
        $stepTime = $reached[$stKey] ?? '';
        if ($curStatus === 'CANCELLED') {
            if ($stKey === 'PENDING' || isset($reached[$stKey])) {
                $state = 'done';
            }
        } elseif ($curIdx !== false) {
            if ($idx < $curIdx || ($curStatus === 'COMPLETED' && $idx === $curIdx)) {
                $state = 'done';
            } elseif ($idx === $curIdx) {
                $state = 'current';
            }
        }
        if ($stKey === 'PENDING' && $stepTime === '') {
            $stepTime = (string)($order['created_at'] ?? '');
        }
        $timeline[] = [
            'key' => $stKey,
            'label' => $stLabel,
            'state' => $state,
            'time' => $stepTime !== '' ? $stepTime : ($state === 'upcoming' ? 'Chờ xử lý' : ''),
        ];
    }

    if ($curStatus === 'CANCELLED') {
        $timeline[] = [
            'key' => 'CANCELLED',
            'label' => 'Đã hủy',
            'state' => 'cancelled',
            'time' => (string)($reached['CANCELLED'] ?? ($order['updated_at'] ?? '')),
        ];
    }

    return $timeline;
}

/**
 * Updates order status in a single DB transaction, restores stock once on CANCELLED,
 * writes history & customer notification, and enqueues email outbox (never sends SMTP inline).
 */
function update_order_status(int $orderId, int $adminId, string $next, array $options = []): array
{
    $carrier = trim((string)($options['shipping_carrier'] ?? ''));
    $tracking = trim((string)($options['tracking_code'] ?? ''));
    $adminNote = trim((string)($options['admin_note'] ?? ''));
    $customerNote = trim((string)($options['customer_note'] ?? ''));
    $cancelReason = trim((string)($options['cancel_reason'] ?? ''));

    if (mb_strlen($carrier) > 100 || mb_strlen($tracking) > 100 || mb_strlen($adminNote) > 500
        || mb_strlen($customerNote) > 500 || mb_strlen($cancelReason) > 300) {
        throw new DomainException('Thông tin ghi chú hoặc vận chuyển vượt quá độ dài cho phép.');
    }
    if (preg_match('/[\x00-\x1F\x7F]/', $carrier . $tracking)) {
        throw new DomainException('Đơn vị vận chuyển hoặc mã vận đơn chứa ký tự không hợp lệ.');
    }

    db()->beginTransaction();
    try {
        $order = row('SELECT * FROM tbl_orders WHERE id=? FOR UPDATE', [$orderId]);
        if (!$order) throw new DomainException('Không tìm thấy đơn hàng.');

        $from = (string)$order['order_status'];
        $pendingCancel = row("SELECT id,reason FROM tbl_order_cancel_requests WHERE order_id=? AND status='PENDING' FOR UPDATE",[$orderId]);
        if ($pendingCancel && $next!=='CANCELLED') {
            throw new DomainException('Khách đang yêu cầu hủy đơn. Admin cần duyệt hoặc từ chối yêu cầu trước khi tiếp tục xử lý.');
        }
        $transitions = order_status_transitions();
        if (!in_array($next, $transitions[$from] ?? [], true)) {
            throw new DomainException('Chuyển trạng thái đơn hàng không được phép theo quy trình hiện tại.');
        }
        if ($order['payment_method'] === 'BANK' && $order['payment_status'] !== 'PAID'
            && in_array($next, ['CONFIRMED', 'PREPARING', 'SHIPPING', 'COMPLETED'], true)) {
            throw new DomainException('Đơn chuyển khoản BANK phải được đối soát và xác nhận đã nhận tiền trước khi xác nhận/giao hàng.');
        }

        if ($next === 'CANCELLED') {
            if ($order['payment_status'] === 'PAID') {
                throw new DomainException('Đơn đã thanh toán cần xử lý hoàn tiền thủ công trước khi hủy.');
            }
            if ($order['payment_review_status'] === 'REPORTED') {
                throw new DomainException('Khách đã báo chuyển tiền. Phải đối soát ngân hàng trước khi hủy.');
            }
            if ($cancelReason === '') {
                $cancelReason = $customerNote !== '' ? $customerNote : 'Đơn hàng được hủy theo yêu cầu quản trị.';
            }
            $items = rows('SELECT product_id,quantity FROM tbl_order_items WHERE order_id=? ORDER BY product_id ASC', [$orderId]);
            $restoreByProduct = [];
            foreach ($items as $item) {
                $pid = (int)$item['product_id'];
                $restoreByProduct[$pid] = ($restoreByProduct[$pid] ?? 0) + (int)$item['quantity'];
            }
            ksort($restoreByProduct, SORT_NUMERIC);
            foreach ($restoreByProduct as $pid => $qty) {
                $prod = row('SELECT id_sanpham,soluong FROM tbl_sanpham WHERE id_sanpham=? FOR UPDATE', [$pid]);
                if ($prod) {
                    $before = (int)$prod['soluong'];
                    $after = $before + $qty;
                    run('UPDATE tbl_sanpham SET soluong = soluong + ? WHERE id_sanpham=?', [$qty, $pid]);
                    record_inventory_movement(
                        $pid,
                        $orderId,
                        'ORDER_CANCEL_RESTORE',
                        $qty,
                        $before,
                        $after,
                        "Hoàn kho khi hủy đơn {$order['order_code']}: {$cancelReason}",
                        'ADMIN',
                        $adminId
                    );
                }
            }
        }

        $finalCarrier = $carrier !== '' ? $carrier : (string)($order['shipping_carrier'] ?? '');
        $finalTracking = $tracking !== '' ? $tracking : (string)($order['tracking_code'] ?? '');
        $finalAdminNote = $adminNote !== '' ? $adminNote : (string)($order['admin_note'] ?? '');
        $finalCustomerNote = $customerNote !== '' ? $customerNote : (string)($order['customer_note'] ?? '');
        $finalCancelReason = $next === 'CANCELLED' ? $cancelReason : (string)($order['cancel_reason'] ?? '');

        $extraTimeSql = '';
        if ($next === 'SHIPPING') $extraTimeSql = ', shipped_at=COALESCE(shipped_at, NOW())';
        elseif ($next === 'COMPLETED') $extraTimeSql = ', delivered_at=COALESCE(delivered_at, NOW())';

        run(
            "UPDATE tbl_orders
             SET order_status=?, shipping_carrier=?, tracking_code=?, admin_note=?, customer_note=?, cancel_reason=?{$extraTimeSql}
             WHERE id=?",
            [$next, $finalCarrier, $finalTracking, $finalAdminNote, $finalCustomerNote, $finalCancelReason, $orderId]
        );

        $publicNote = match ($next) {
            'CONFIRMED' => $customerNote !== '' ? $customerNote : 'Cửa hàng đã xác nhận đơn hàng.',
            'PREPARING' => $customerNote !== '' ? $customerNote : 'Đơn hàng đang được kiểm tra và đóng gói.',
            'SHIPPING' => trim(($customerNote !== '' ? $customerNote . ' ' : 'Đơn hàng đã bàn giao cho đơn vị vận chuyển.')
                . ($finalCarrier !== '' ? " ĐVVC: {$finalCarrier}." : '')
                . ($finalTracking !== '' ? " Mã vận đơn: {$finalTracking}." : '')),
            'COMPLETED' => $customerNote !== '' ? $customerNote : 'Đơn hàng đã được giao thành công đến khách hàng.',
            'CANCELLED' => 'Lý do hủy: ' . $finalCancelReason,
            default => $customerNote,
        };
        record_order_history($orderId, $from, $next, 'ADMIN', $adminId, $publicNote, true);
        if ($adminNote !== '' && $adminNote !== (string)($order['admin_note'] ?? '')) {
            record_order_history($orderId, $from, $next, 'ADMIN', $adminId, 'Ghi chú nội bộ: ' . $adminNote, false);
        }

        $code = (string)$order['order_code'];
        $link = link_to('order', ['code' => $code]);
        [$nType, $nTitle, $nMsg, $mailEvent] = match ($next) {
            'CONFIRMED' => [
                'ORDER_CONFIRMED',
                'Đơn hàng đã được xác nhận',
                "Đơn hàng {$code} đã được BanHang xác nhận." . ($customerNote !== '' ? " Ghi chú: {$customerNote}" : ''),
                'ORDER_CONFIRMED',
            ],
            'PREPARING' => [
                'ORDER_PREPARING',
                'Đơn hàng đang được chuẩn bị',
                "BanHang đang kiểm tra và đóng gói đơn hàng {$code}." . ($customerNote !== '' ? " {$customerNote}" : ''),
                'ORDER_PREPARING',
            ],
            'SHIPPING' => [
                'ORDER_SHIPPING',
                'Đơn hàng đang được giao',
                "Đơn hàng {$code} đã được giao cho đơn vị vận chuyển."
                . ($finalCarrier !== '' ? " Đơn vị: {$finalCarrier}." : '')
                . ($finalTracking !== '' ? " Mã vận đơn: {$finalTracking}." : '')
                . ($customerNote !== '' ? " {$customerNote}" : ''),
                'ORDER_SHIPPING',
            ],
            'COMPLETED' => [
                'ORDER_COMPLETED',
                'Giao hàng thành công!',
                "Đơn hàng {$code} đã được giao thành công. Cảm ơn bạn đã mua sắm tại BanHang!" . ($customerNote !== '' ? " {$customerNote}" : ''),
                'ORDER_COMPLETED',
            ],
            'CANCELLED' => [
                'ORDER_CANCELLED',
                'Đơn hàng đã bị hủy',
                "Đơn hàng {$code} đã bị hủy. Lý do: {$finalCancelReason}.",
                'ORDER_CANCELLED',
            ],
            default => ['ORDER_UPDATE', 'Cập nhật đơn hàng', "Đơn hàng {$code} vừa được cập nhật.", 'ORDER_UPDATE'],
        };

        create_notification(
            (int)$order['user_id'],
            $orderId,
            $nType,
            $nTitle,
            $nMsg,
            $link,
            "order_status:{$orderId}:{$next}"
        );

        if ($next==='CANCELLED' && $pendingCancel) {
            run("UPDATE tbl_order_cancel_requests SET status='APPROVED',admin_note=?,resolved_at=NOW(),resolved_by=? WHERE id=?",
                ['Đã duyệt hủy đơn, hoàn lại tồn kho.',$adminId,$pendingCancel['id']]);
        }
        $updatedOrder = row('SELECT * FROM tbl_orders WHERE id=?', [$orderId]);
        queue_order_email($updatedOrder, $mailEvent, $publicNote, "email_status:{$orderId}:{$next}");

        db()->commit();
        return $updatedOrder;
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}

/**
 * Save admin shipping info, customer-visible note and internal admin note.
 * - Uses per-event idempotency key (`event_key`) instead of content hash so A -> B -> A
 *   creates 3 valid notifications while retries of the same event_key never duplicate.
 * - Handles clearing customer_note/shipping explicitly so customers aren't misled by stale notes.
 * - NEVER exposes admin_note to customers or public history.
 */
function save_order_notes(int $orderId, int $adminId, array $data): array
{
    $carrier = trim((string)($data['shipping_carrier'] ?? ''));
    $tracking = trim((string)($data['tracking_code'] ?? ''));
    $adminNote = trim((string)($data['admin_note'] ?? ''));
    $customerNote = trim((string)($data['customer_note'] ?? ''));
    $rawEventKey = trim((string)($data['event_key'] ?? ''));

    if (mb_strlen($carrier) > 100 || mb_strlen($tracking) > 100 || mb_strlen($adminNote) > 500 || mb_strlen($customerNote) > 500) {
        throw new DomainException('Ghi chú hoặc thông tin vận chuyển vượt giới hạn.');
    }
    if (preg_match('/[\x00-\x1F\x7F]/', $carrier . $tracking)) {
        throw new DomainException('Thông tin vận chuyển chứa ký tự không hợp lệ.');
    }
    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $adminNote . $customerNote)) {
        throw new DomainException('Ghi chú chứa ký tự điều khiển không hợp lệ.');
    }
    if ($rawEventKey !== '' && !preg_match('/^[A-Za-z0-9_\-:]{8,64}$/', $rawEventKey)) {
        throw new DomainException('Mã sự kiện cập nhật không hợp lệ.');
    }
    $eventKey = $rawEventKey !== '' ? $rawEventKey : bin2hex(random_bytes(12));

    $result = ['changed' => false, 'duplicate_retry' => false, 'shipping_notified' => false, 'customer_note_notified' => false];
    db()->beginTransaction();
    try {
        $order = row(
            'SELECT id,order_code,user_id,order_status,tracking_code,shipping_carrier,customer_note,admin_note
             FROM tbl_orders WHERE id=? FOR UPDATE',
            [$orderId]
        );
        if (!$order) throw new DomainException('Không tìm thấy đơn hàng.');

        $shipDedup = "order_ship:{$orderId}:{$eventKey}";
        $noteDedup = "order_cnote:{$orderId}:{$eventKey}";

        // Guard against retry of the exact same event_key
        if ($rawEventKey !== '') {
            $alreadyProcessed = row(
                'SELECT id FROM tbl_notifications WHERE dedup_key IN (?, ?) LIMIT 1',
                [$shipDedup, $noteDedup]
            );
            if ($alreadyProcessed) {
                db()->commit();
                $result['duplicate_retry'] = true;
                return $result;
            }
        }

        $oldCarrier = trim((string)($order['shipping_carrier'] ?? ''));
        $oldTracking = trim((string)($order['tracking_code'] ?? ''));
        $oldCustomerNote = trim((string)($order['customer_note'] ?? ''));
        $oldAdminNote = trim((string)($order['admin_note'] ?? ''));

        $shippingChanged = ($carrier !== $oldCarrier) || ($tracking !== $oldTracking);
        $customerNoteChanged = ($customerNote !== $oldCustomerNote);
        $adminNoteChanged = ($adminNote !== $oldAdminNote);

        if (!$shippingChanged && !$customerNoteChanged && !$adminNoteChanged) {
            db()->commit();
            return $result;
        }

        $result['changed'] = true;
        run(
            'UPDATE tbl_orders SET shipping_carrier=?, tracking_code=?, admin_note=?, customer_note=? WHERE id=?',
            [$carrier, $tracking, $adminNote, $customerNote, $orderId]
        );

        $orderLink = link_to('order', ['code' => $order['order_code']]);
        $userId = (int)$order['user_id'];

        if ($adminNoteChanged) {
            $internalText = $adminNote !== '' ? 'Ghi chú nội bộ: ' . $adminNote : 'Đã xóa ghi chú nội bộ.';
            record_order_history($orderId, null, 'INTERNAL_NOTE', 'ADMIN', $adminId, $internalText, false);
        }

        if ($shippingChanged) {
            if ($carrier !== '' || $tracking !== '') {
                $detail = trim(($carrier !== '' ? "ĐVVC: {$carrier}. " : '') . ($tracking !== '' ? "Mã vận đơn: {$tracking}." : ''));
                record_order_history($orderId, null, 'SHIPPING_UPDATED', 'ADMIN', $adminId, 'Cập nhật vận chuyển: ' . $detail, true);
                $result['shipping_notified'] = create_notification(
                    $userId,
                    $orderId,
                    'ORDER_SHIPPING_INFO',
                    'Cập nhật thông tin vận chuyển',
                    "Đơn hàng {$order['order_code']} đã cập nhật thông tin vận chuyển. {$detail}",
                    $orderLink,
                    $shipDedup
                );
            } else {
                $clearMsg = 'Thông tin vận chuyển trước đó của đơn hàng đã được cửa hàng gỡ bỏ/cập nhật lại.';
                record_order_history($orderId, null, 'SHIPPING_UPDATED', 'ADMIN', $adminId, $clearMsg, true);
                $result['shipping_notified'] = create_notification(
                    $userId,
                    $orderId,
                    'ORDER_SHIPPING_INFO',
                    'Cập nhật thông tin vận chuyển',
                    "Đơn hàng {$order['order_code']}: {$clearMsg}",
                    $orderLink,
                    $shipDedup
                );
            }
        }

        if ($customerNoteChanged) {
            if ($customerNote !== '') {
                record_order_history($orderId, null, 'CUSTOMER_NOTE', 'ADMIN', $adminId, 'Ghi chú từ cửa hàng: ' . $customerNote, true);
                $result['customer_note_notified'] = create_notification(
                    $userId,
                    $orderId,
                    'ORDER_CUSTOMER_NOTE',
                    'Ghi chú mới cho đơn hàng',
                    "Cửa hàng vừa gửi ghi chú mới cho đơn hàng {$order['order_code']}: {$customerNote}",
                    $orderLink,
                    $noteDedup
                );
            } else {
                $clearNoteMsg = 'Cửa hàng đã gỡ bỏ ghi chú trước đó của đơn hàng.';
                record_order_history($orderId, null, 'CUSTOMER_NOTE', 'ADMIN', $adminId, $clearNoteMsg, true);
                $result['customer_note_notified'] = create_notification(
                    $userId,
                    $orderId,
                    'ORDER_CUSTOMER_NOTE',
                    'Ghi chú đơn hàng đã được cập nhật',
                    "Đơn hàng {$order['order_code']}: {$clearNoteMsg}",
                    $orderLink,
                    $noteDedup
                );
            }
        }

        db()->commit();
        return $result;
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}

/**
 * Explicit Admin confirmation that COD cash has been collected.
 * Queues email in outbox (never sends SMTP inline).
 */
function confirm_cod_payment(int $orderId, int $adminId, string $note = ''): void
{
    $note = trim($note);
    if (mb_strlen($note) > 300 || preg_match('/[\x00-\x1F\x7F]/', $note)) {
        throw new DomainException('Ghi chú xác nhận thu tiền COD không hợp lệ.');
    }
    db()->beginTransaction();
    try {
        $order = row('SELECT * FROM tbl_orders WHERE id=? FOR UPDATE', [$orderId]);
        if (!$order || $order['payment_method'] !== 'COD' || $order['payment_status'] !== 'UNPAID') {
            throw new DomainException('Đơn hàng COD không hợp lệ hoặc đã được xác nhận thu tiền.');
        }
        if (!in_array($order['order_status'], ['SHIPPING', 'COMPLETED'], true)) {
            throw new DomainException('Chỉ xác nhận thu tiền COD khi đơn đang giao hoặc đã giao thành công.');
        }
        run(
            "UPDATE tbl_orders SET payment_status='PAID', cod_paid_at=NOW(), cod_verified_by=? WHERE id=?",
            [$adminId, $orderId]
        );
        $detail = 'Admin xác nhận đã thu đủ tiền COD ' . money((int)$order['total_amount']) . ($note !== '' ? ' · ' . $note : '');
        payment_event($orderId, 'ADMIN_COD_PAID', 'ADMIN', $adminId, $detail, null);
        record_order_history($orderId, null, 'COD_PAID', 'ADMIN', $adminId, 'Đã xác nhận thanh toán COD.', true);
        create_notification(
            (int)$order['user_id'],
            $orderId,
            'PAYMENT_CONFIRMED',
            'Xác nhận đã thu tiền COD',
            "BanHang đã xác nhận nhận đủ " . money((int)$order['total_amount']) . " tiền thanh toán COD cho đơn hàng {$order['order_code']}.",
            link_to('order', ['code' => $order['order_code']]),
            "order_cod_paid:{$orderId}"
        );
        $updated = row('SELECT * FROM tbl_orders WHERE id=?', [$orderId]);
        queue_order_email($updated, 'PAYMENT_CONFIRMED', $detail, "email_cod_paid:{$orderId}");
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}

/**
 * Unified revenue report used by BOTH charts and tables in Admin.
 * - Ordered value & unpaid balance are grouped by `created_at`.
 * - Verified BANK cash received is grouped strictly by `payment_verified_at`.
 * - Verified COD cash collected is grouped strictly by `cod_paid_at`.
 * - Legacy PAID rows without verification timestamp are audited separately (never faked with created_at).
 */
function revenue_report_data(string $range = '30d'): array
{
    $range = in_array($range, ['7d', '30d', '12m'], true) ? $range : '30d';
    if ($range === '12m') {
        $fmt = '%Y-%m';
        $cutoffExpr = "DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 MONTH), '%Y-%m-01 00:00:00')";
    } else {
        $days = $range === '7d' ? 6 : 29;
        $fmt = '%Y-%m-%d';
        $cutoffExpr = "DATE_SUB(CURDATE(), INTERVAL {$days} DAY)";
    }

    $orderedRows = rows(
        "SELECT DATE_FORMAT(created_at, '{$fmt}') AS bucket,
                SUM(order_status <> 'CANCELLED') AS active_orders,
                COALESCE(SUM(CASE WHEN order_status <> 'CANCELLED' THEN total_amount ELSE 0 END), 0) AS ordered_total,
                COALESCE(SUM(CASE WHEN payment_status = 'UNPAID' AND order_status <> 'CANCELLED' THEN total_amount ELSE 0 END), 0) AS unpaid_total,
                COALESCE(SUM(CASE WHEN payment_status = 'UNPAID' AND payment_method = 'BANK' AND order_status <> 'CANCELLED' THEN total_amount ELSE 0 END), 0) AS bank_unpaid,
                COALESCE(SUM(CASE WHEN payment_status = 'UNPAID' AND payment_method = 'COD' AND order_status <> 'CANCELLED' THEN total_amount ELSE 0 END), 0) AS cod_unpaid
         FROM tbl_orders
         WHERE created_at >= {$cutoffExpr}
         GROUP BY DATE_FORMAT(created_at, '{$fmt}')
         ORDER BY bucket ASC"
    );

    $bankPaidRows = rows(
        "SELECT DATE_FORMAT(payment_verified_at, '{$fmt}') AS bucket,
                COUNT(*) AS paid_count,
                COALESCE(SUM(total_amount), 0) AS bank_paid
         FROM tbl_orders
         WHERE payment_method = 'BANK'
           AND payment_status = 'PAID'
           AND payment_verified_at IS NOT NULL
           AND payment_verified_at >= {$cutoffExpr}
         GROUP BY DATE_FORMAT(payment_verified_at, '{$fmt}')
         ORDER BY bucket ASC"
    );

    $codPaidRows = rows(
        "SELECT DATE_FORMAT(cod_paid_at, '{$fmt}') AS bucket,
                COUNT(*) AS paid_count,
                COALESCE(SUM(total_amount), 0) AS cod_paid
         FROM tbl_orders
         WHERE payment_method = 'COD'
           AND payment_status = 'PAID'
           AND cod_paid_at IS NOT NULL
           AND cod_paid_at >= {$cutoffExpr}
         GROUP BY DATE_FORMAT(cod_paid_at, '{$fmt}')
         ORDER BY bucket ASC"
    );

    $buckets = [];
    $ensure = static function (string $key) use (&$buckets): void {
        if (!isset($buckets[$key])) {
            $buckets[$key] = [
                'bucket' => $key,
                'active_orders' => 0,
                'ordered_total' => 0,
                'bank_paid' => 0,
                'cod_paid' => 0,
                'paid_total' => 0,
                'unpaid_total' => 0,
                'bank_unpaid' => 0,
                'cod_unpaid' => 0,
            ];
        }
    };

    foreach ($orderedRows as $r) {
        $k = (string)$r['bucket'];
        $ensure($k);
        $buckets[$k]['active_orders'] = (int)$r['active_orders'];
        $buckets[$k]['ordered_total'] = (int)$r['ordered_total'];
        $buckets[$k]['unpaid_total'] = (int)$r['unpaid_total'];
        $buckets[$k]['bank_unpaid'] = (int)$r['bank_unpaid'];
        $buckets[$k]['cod_unpaid'] = (int)$r['cod_unpaid'];
    }
    foreach ($bankPaidRows as $r) {
        $k = (string)$r['bucket'];
        $ensure($k);
        $buckets[$k]['bank_paid'] = (int)$r['bank_paid'];
    }
    foreach ($codPaidRows as $r) {
        $k = (string)$r['bucket'];
        $ensure($k);
        $buckets[$k]['cod_paid'] = (int)$r['cod_paid'];
    }

    ksort($buckets, SORT_STRING);
    $totals = [
        'active_orders' => 0,
        'ordered_total' => 0,
        'bank_paid' => 0,
        'cod_paid' => 0,
        'paid_total' => 0,
        'unpaid_total' => 0,
        'bank_unpaid' => 0,
        'cod_unpaid' => 0,
    ];
    foreach ($buckets as &$b) {
        $b['paid_total'] = $b['bank_paid'] + $b['cod_paid'];
        foreach ($totals as $tk => $tv) {
            $totals[$tk] += $b[$tk];
        }
    }
    unset($b);

    $missingTs = row(
        "SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount), 0) AS total
         FROM tbl_orders
         WHERE payment_status = 'PAID'
           AND ((payment_method = 'BANK' AND payment_verified_at IS NULL)
             OR (payment_method = 'COD' AND cod_paid_at IS NULL))"
    );

    return [
        'range' => $range,
        'series' => array_values($buckets),
        'totals' => $totals,
        'missing_timestamp_count' => (int)($missingTs['cnt'] ?? 0),
        'missing_timestamp_total' => (int)($missingTs['total'] ?? 0),
    ];
}


/**
 * Decide whether the current customer can cancel immediately or must ask Admin.
 * This is presentation-only; every write rechecks the locked DB row.
 */
function customer_order_action_options(array $order): array
{
    $active = (string)$order['order_status'];
    $unpaid = (string)$order['payment_status'] === 'UNPAID';
    $reported = (string)$order['payment_review_status'] === 'REPORTED'
        || !empty($order['payment_reported_at']);
    $selfCancel = $active === 'PENDING' && $unpaid && !$reported;
    return [
        'can_cancel' => $selfCancel,
        'can_change_payment' => $selfCancel,
        'can_request_cancel' => !$selfCancel && in_array($active, ['PENDING','CONFIRMED','PREPARING'], true),
    ];
}

/**
 * Customer cancellation: only PENDING + UNPAID + never reported a transfer.
 * All stock, history, notifications and email updates commit atomically.
 * If review is needed, create exactly one pending request instead of cancelling.
 */
function customer_cancel_order(string $code, int $customerId, string $reason, bool $confirmedNoTransfer = false): string
{
    $code = trim($code);
    $reason = trim($reason);
    if ($customerId < 1 || $code === '' || mb_strlen($code) > 24
        || mb_strlen($reason) < 5 || mb_strlen($reason) > 300
        || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $reason)) {
        throw new DomainException('Vui lòng chọn lý do hủy hợp lệ (5–300 ký tự).');
    }
    db()->beginTransaction();
    try {
        $order = row('SELECT * FROM tbl_orders WHERE order_code=? AND user_id=? FOR UPDATE', [$code,$customerId]);
        if (!$order) throw new DomainException('Không tìm thấy đơn hàng của bạn.');
        $orderId = (int)$order['id'];
        $options = customer_order_action_options($order);
        if ($order['order_status'] === 'CANCELLED') throw new DomainException('Đơn hàng đã được hủy trước đó.');
        if ($order['order_status'] === 'COMPLETED' || $order['order_status'] === 'SHIPPING') {
            throw new DomainException('Đơn đã giao/đang giao, vui lòng liên hệ cửa hàng để được hỗ trợ.');
        }

        if ($options['can_cancel']) {
            if ($order['payment_method']==='BANK' && !$confirmedNoTransfer) {
                throw new DomainException('Bạn cần xác nhận chưa chuyển khoản trước khi tự hủy đơn VietQR. Nếu đã chuyển, vui lòng liên hệ cửa hàng.');
            }
            $items = rows('SELECT product_id,quantity FROM tbl_order_items WHERE order_id=? ORDER BY product_id ASC', [$orderId]);
            $restore = [];
            foreach ($items as $item) {
                $productId = (int)$item['product_id'];
                $restore[$productId] = ($restore[$productId] ?? 0) + (int)$item['quantity'];
            }
            ksort($restore, SORT_NUMERIC);
            foreach ($restore as $productId => $quantity) {
                $product = row('SELECT id_sanpham,soluong FROM tbl_sanpham WHERE id_sanpham=? FOR UPDATE', [$productId]);
                if (!$product) throw new RuntimeException('Không tìm thấy sản phẩm để hoàn tồn kho; hủy đơn chưa được thực hiện.');
                $before = (int)$product['soluong'];
                run('UPDATE tbl_sanpham SET soluong=soluong+? WHERE id_sanpham=?', [$quantity,$productId]);
                record_inventory_movement(
                    $productId,$orderId,'ORDER_CANCEL_RESTORE',$quantity,$before,$before+$quantity,
                    "Hoàn kho khi khách tự hủy đơn {$code}: {$reason}",'CUSTOMER',$customerId
                );
            }
            run("UPDATE tbl_orders SET order_status='CANCELLED', cancel_reason=? WHERE id=?", [$reason,$orderId]);
            record_order_history($orderId,'PENDING','CANCELLED','CUSTOMER',$customerId,
                'Khách hàng tự hủy đơn: '.$reason,true);
            create_notification($customerId,$orderId,'ORDER_CANCELLED','Đơn hàng đã hủy',
                "Đơn {$code} đã hủy theo yêu cầu của bạn. Hàng đã được trả về tồn kho.",
                link_to('order',['code'=>$code]),"customer_cancel:{$orderId}");
            $updated = row('SELECT * FROM tbl_orders WHERE id=?', [$orderId]);
            queue_order_email($updated,'ORDER_CANCELLED','Khách hàng yêu cầu hủy: '.$reason,
                "email_customer_cancel:{$orderId}");
            db()->commit();
            return 'CANCELLED';
        }

        if (!$options['can_request_cancel']) throw new DomainException('Đơn hàng không còn đủ điều kiện yêu cầu hủy.');
        $request = row('SELECT id,status FROM tbl_order_cancel_requests WHERE order_id=?', [$orderId]);
        if ($request) {
            if ($request['status'] === 'PENDING') {
                db()->commit();
                return 'REQUESTED';
            }
            throw new DomainException('Yêu cầu hủy trước đó đã được xử lý. Vui lòng liên hệ cửa hàng nếu cần hỗ trợ thêm.');
        }
        run('INSERT INTO tbl_order_cancel_requests(order_id,user_id,reason) VALUES (?,?,?)',
            [$orderId,$customerId,$reason]);
        record_order_history($orderId,null,'CANCEL_REQUESTED','CUSTOMER',$customerId,
            'Khách hàng yêu cầu hủy (chờ Admin duyệt): '.$reason,true);
        create_notification($customerId,$orderId,'ORDER_CANCEL_REQUESTED','Đã gửi yêu cầu hủy đơn',
            "Yêu cầu hủy đơn {$code} đang chờ cửa hàng xem xét. Đơn chưa bị hủy.",
            link_to('order',['code'=>$code]),"cancel_request:{$orderId}");
        $updated = row('SELECT * FROM tbl_orders WHERE id=?',[$orderId]);
        queue_order_email($updated,'ORDER_CANCEL_REQUESTED','Lý do yêu cầu hủy: '.$reason,
            "email_cancel_request:{$orderId}");
        db()->commit();
        return 'REQUESTED';
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}

/**
 * Switch COD/BANK only while order is pending, unpaid and never reported.
 * Reuse immutable bank details on repeated switches; never mark as PAID.
 */
function customer_change_payment_method(string $code, int $customerId, string $method): void
{
    if (!in_array($method,['COD','BANK'],true)) throw new DomainException('Phương thức thanh toán không hợp lệ.');
    db()->beginTransaction();
    try {
        $order = row('SELECT * FROM tbl_orders WHERE order_code=? AND user_id=? FOR UPDATE', [trim($code),$customerId]);
        if (!$order) throw new DomainException('Không tìm thấy đơn hàng của bạn.');
        if (!customer_order_action_options($order)['can_change_payment']) {
            throw new DomainException('Đơn đã xử lý hoặc đã báo/chuyển tiền; không thể tự đổi phương thức thanh toán.');
        }
        $orderId = (int)$order['id'];
        if (row("SELECT id FROM tbl_order_cancel_requests WHERE order_id=? AND status='PENDING'", [$orderId])) {
            throw new DomainException('Đơn đang có yêu cầu hủy chờ duyệt, không thể đổi thanh toán.');
        }
        if ($order['payment_method'] === $method) {
            db()->commit();
            return;
        }
        if ($method === 'BANK' && order_payment_snapshot($orderId) === null) {
            save_order_payment_snapshot($orderId,(string)$order['order_code'],(int)$order['total_amount']);
        }
        run("UPDATE tbl_orders SET payment_method=?, payment_review_status='NONE' WHERE id=?",[$method,$orderId]);
        $description = 'Đổi thanh toán từ '.payment_method_label((string)$order['payment_method'])
            .' sang '.payment_method_label($method)
            .'. Nếu đã chuyển khoản theo QR cũ, cần liên hệ cửa hàng ngay để đối soát.';
        record_order_history($orderId,null,'PAYMENT_METHOD_CHANGED','CUSTOMER',$customerId,$description,true);
        $changeCount = (int)(row("SELECT COUNT(*) AS n FROM tbl_order_status_history WHERE order_id=? AND to_status='PAYMENT_METHOD_CHANGED'",[$orderId])['n'] ?? 1);
        create_notification($customerId,$orderId,'PAYMENT_METHOD_CHANGED','Đã đổi phương thức thanh toán',
            "Đơn {$code}: {$description}",link_to('order',['code'=>$code]),
            "payment_change:{$orderId}:{$changeCount}");
        $updated = row('SELECT * FROM tbl_orders WHERE id=?',[$orderId]);
        queue_order_email($updated,'PAYMENT_METHOD_CHANGED',$description,"email_payment_change:{$orderId}:{$changeCount}");
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}

/** Admin declines a pending request, retaining the order and existing payment state. */
function admin_decline_cancel_request(int $orderId, int $adminId, string $note): void
{
    $note = trim($note);
    if (mb_strlen($note)<5 || mb_strlen($note)>300
        || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$note)) {
        throw new DomainException('Nhập lý do từ chối yêu cầu hủy (5–300 ký tự).');
    }
    db()->beginTransaction();
    try {
        $order = row('SELECT * FROM tbl_orders WHERE id=? FOR UPDATE',[$orderId]);
        if (!$order) throw new DomainException('Không tìm thấy đơn hàng.');
        $request = row("SELECT * FROM tbl_order_cancel_requests WHERE order_id=? AND status='PENDING' FOR UPDATE",[$orderId]);
        if (!$request) throw new DomainException('Không còn yêu cầu hủy đang chờ duyệt.');
        run("UPDATE tbl_order_cancel_requests SET status='DECLINED',admin_note=?,resolved_at=NOW(),resolved_by=? WHERE id=?",
            [$note,$adminId,$request['id']]);
        record_order_history($orderId,null,'CANCEL_DECLINED','ADMIN',$adminId,
            'Cửa hàng chưa thể chấp thuận yêu cầu hủy: '.$note,true);
        create_notification((int)$order['user_id'],$orderId,'ORDER_CANCEL_DECLINED','Yêu cầu hủy chưa được chấp thuận',
            "Đơn {$order['order_code']}: {$note}",link_to('order',['code'=>$order['order_code']]),
            "cancel_declined:{$orderId}");
        $updated = row('SELECT * FROM tbl_orders WHERE id=?',[$orderId]);
        queue_order_email($updated,'ORDER_CANCEL_DECLINED',$note,"email_cancel_declined:{$orderId}");
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}
