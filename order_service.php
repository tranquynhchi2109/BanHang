<?php
declare(strict_types=1);

/**
 * BanHang Order Workflow, Timeline & Customer Note Service.
 * Loaded by app.php; direct HTTP access is denied via .htaccess.
 */

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

    $emailPayload = null;
    db()->beginTransaction();
    try {
        $order = row('SELECT * FROM tbl_orders WHERE id=? FOR UPDATE', [$orderId]);
        if (!$order) throw new DomainException('Không tìm thấy đơn hàng.');

        $from = (string)$order['order_status'];
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
            $items = rows('SELECT product_id,quantity FROM tbl_order_items WHERE order_id=?', [$orderId]);
            foreach ($items as $item) {
                run('UPDATE tbl_sanpham SET soluong=soluong+? WHERE id_sanpham=?', [(int)$item['quantity'], (int)$item['product_id']]);
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

        $updatedOrder = row('SELECT * FROM tbl_orders WHERE id=?', [$orderId]);
        queue_order_email($updatedOrder, $mailEvent, $publicNote, "email_status:{$orderId}:{$next}");
        $emailPayload = $updatedOrder;

        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }

    dispatch_email_outbox(5);
    return $emailPayload;
}

/**
 * Save admin shipping info, customer-visible note and internal admin note.
 * - Detects shipping changes and customer_note changes independently.
 * - Sends deduplicated customer notifications ONLY when customer-visible values actually change.
 * - NEVER exposes admin_note to customers or public history.
 */
function save_order_notes(int $orderId, int $adminId, array $data): array
{
    $carrier = trim((string)($data['shipping_carrier'] ?? ''));
    $tracking = trim((string)($data['tracking_code'] ?? ''));
    $adminNote = trim((string)($data['admin_note'] ?? ''));
    $customerNote = trim((string)($data['customer_note'] ?? ''));
    if (mb_strlen($carrier) > 100 || mb_strlen($tracking) > 100 || mb_strlen($adminNote) > 500 || mb_strlen($customerNote) > 500) {
        throw new DomainException('Ghi chú hoặc thông tin vận chuyển vượt giới hạn.');
    }
    if (preg_match('/[\x00-\x1F\x7F]/', $carrier . $tracking)) {
        throw new DomainException('Thông tin vận chuyển chứa ký tự không hợp lệ.');
    }
    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $adminNote . $customerNote)) {
        throw new DomainException('Ghi chú chứa ký tự điều khiển không hợp lệ.');
    }

    $result = ['changed' => false, 'shipping_notified' => false, 'customer_note_notified' => false];
    db()->beginTransaction();
    try {
        $order = row(
            'SELECT id,order_code,user_id,order_status,tracking_code,shipping_carrier,customer_note,admin_note
             FROM tbl_orders WHERE id=? FOR UPDATE',
            [$orderId]
        );
        if (!$order) throw new DomainException('Không tìm thấy đơn hàng.');

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
                    'order_track:' . $orderId . ':' . sha1($carrier . '|' . $tracking)
                );
            } else {
                record_order_history($orderId, null, 'SHIPPING_UPDATED', 'ADMIN', $adminId, 'Đã xóa thông tin vận chuyển trên đơn.', false);
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
                    'order_cnote:' . $orderId . ':' . sha1($customerNote)
                );
            } else {
                record_order_history($orderId, null, 'CUSTOMER_NOTE', 'ADMIN', $adminId, 'Đã xóa ghi chú gửi khách.', false);
            }
        }

        db()->commit();
        return $result;
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}

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
    dispatch_email_outbox(5);
}
