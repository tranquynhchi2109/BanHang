<?php
declare(strict_types=1);

require __DIR__ . '/app.php';
migrate_legacy_session();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function json_out(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Per-session lightweight rate limiting (prevents abusive polling)
$now = time();
$bucket = $_SESSION['api_rate'] ?? ['window' => $now, 'count' => 0];
if (!is_array($bucket) || ($now - (int)($bucket['window'] ?? 0)) >= 60) {
    $bucket = ['window' => $now, 'count' => 0];
}
$bucket['count'] = (int)$bucket['count'] + 1;
$_SESSION['api_rate'] = $bucket;
if ($bucket['count'] > 45) {
    json_out(['ok' => false, 'error' => 'Quá nhiều yêu cầu. Vui lòng thử lại sau ít phút.'], 429);
}

$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

try {
    if ($action === 'notifications' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $user = current_user();
        if (!$user) json_out(['ok' => false, 'error' => 'Unauthorized'], 401);
        $limit = max(1, min(20, (int)($_GET['limit'] ?? 8)));
        $uid = (int)$user['id_dangky'];
        $unread = unread_notification_count($uid);
        $items = user_notifications($uid, $limit, 0);
        $formatted = array_map(static fn(array $n): array => [
            'id' => (int)$n['id'],
            'order_id' => $n['order_id'] !== null ? (int)$n['order_id'] : null,
            'type' => (string)$n['notification_type'],
            'title' => (string)$n['title'],
            'message' => (string)$n['message'],
            'link_url' => (string)$n['link_url'],
            'is_read' => (int)$n['is_read'] === 1,
            'created_at' => (string)$n['created_at'],
        ], $items);
        json_out([
            'ok' => true,
            'unread_count' => $unread,
            'notifications' => $formatted,
            'server_time' => date('Y-m-d H:i:s'),
        ]);
    }

    if ($action === 'mark_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $user = current_user();
        if (!$user) json_out(['ok' => false, 'error' => 'Unauthorized'], 401);
        $sentCsrf = (string)($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!hash_equals(csrf_token(), $sentCsrf)) {
            json_out(['ok' => false, 'error' => 'Invalid CSRF token'], 403);
        }
        $uid = (int)$user['id_dangky'];
        if ((string)($_POST['all'] ?? '') === '1') {
            mark_all_notifications_read($uid);
        } else {
            $nid = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
            if ($nid && $nid > 0) {
                mark_notification_read($uid, (int)$nid);
            }
        }
        json_out(['ok' => true, 'unread_count' => unread_notification_count($uid)]);
    }

    if ($action === 'order_status' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $user = current_user();
        if (!$user) json_out(['ok' => false, 'error' => 'Unauthorized'], 401);
        $code = trim((string)($_GET['code'] ?? ''));
        if ($code === '' || strlen($code) > 24) {
            json_out(['ok' => false, 'error' => 'Mã đơn hàng không hợp lệ'], 400);
        }
        $order = row(
            'SELECT id,order_code,total_amount,payment_method,payment_status,payment_review_status,order_status,
                    shipping_carrier,tracking_code,shipped_at,delivered_at,cancel_reason,customer_note,
                    created_at,updated_at,payment_verified_at,cod_paid_at
             FROM tbl_orders WHERE order_code=? AND user_id=? LIMIT 1',
            [$code, (int)$user['id_dangky']]
        );
        if (!$order) json_out(['ok' => false, 'error' => 'Không tìm thấy đơn hàng'], 404);
        $payState = payment_state_summary($order);
        $snapshot = $order['payment_method'] === 'BANK' ? order_payment_snapshot((int)$order['id']) : null;
        $publicHistory = rows(
            'SELECT from_status,to_status,note,created_at FROM tbl_order_status_history WHERE order_id=? AND is_public=1 ORDER BY id ASC',
            [(int)$order['id']]
        );
        $timeline = build_order_timeline($order, $publicHistory);
        $historyItems = array_map(static fn(array $ev): array => [
            'label' => order_status_label((string)$ev['to_status']),
            'note' => (string)($ev['note'] ?? ''),
            'created_at' => (string)$ev['created_at'],
        ], array_slice(array_reverse($publicHistory), 0, 20));
        $histCount = count($publicHistory);
        $signature = implode('|', [
            (string)$order['order_status'],
            (string)$order['payment_status'],
            (string)$order['payment_review_status'],
            (string)($order['shipping_carrier'] ?? ''),
            (string)($order['tracking_code'] ?? ''),
            (string)($order['customer_note'] ?? ''),
            (string)$order['updated_at'],
            (string)$histCount,
        ]);
        $isFinal = ($order['order_status'] === 'CANCELLED')
            || ($order['order_status'] === 'COMPLETED' && $order['payment_status'] === 'PAID');
        json_out([
            'ok' => true,
            'order_code' => (string)$order['order_code'],
            'total_amount' => (int)$order['total_amount'],
            'total_amount_formatted' => money((int)$order['total_amount']),
            'order_status' => (string)$order['order_status'],
            'order_status_label' => order_status_label((string)$order['order_status']),
            'order_status_tone' => order_status_tone((string)$order['order_status']),
            'payment_method' => (string)$order['payment_method'],
            'payment_method_label' => payment_method_label((string)$order['payment_method']),
            'payment_status' => (string)$order['payment_status'],
            'payment_review_status' => (string)$order['payment_review_status'],
            'payment_review_label' => payment_review_label((string)$order['payment_review_status']),
            'payment_label' => $payState['label'],
            'payment_tone' => $payState['tone'],
            'payment_snapshot' => $snapshot ? [
                'has_snapshot' => true,
                'bank_id' => (string)$snapshot['bank_id'],
                'bank_label' => bank_display_label((string)$snapshot['bank_id']),
                'bank_account' => (string)$snapshot['bank_account'],
                'bank_account_name' => (string)$snapshot['bank_account_name'],
                'expected_amount' => (int)$snapshot['expected_amount'],
                'transfer_content' => (string)$snapshot['transfer_content'],
            ] : ['has_snapshot' => false],
            'shipping_carrier' => (string)($order['shipping_carrier'] ?? ''),
            'tracking_code' => (string)($order['tracking_code'] ?? ''),
            'shipped_at' => (string)($order['shipped_at'] ?? ''),
            'delivered_at' => (string)($order['delivered_at'] ?? ''),
            'cancel_reason' => (string)($order['cancel_reason'] ?? ''),
            'customer_note' => (string)($order['customer_note'] ?? ''),
            'payment_verified_at' => (string)($order['payment_verified_at'] ?? ''),
            'cod_paid_at' => (string)($order['cod_paid_at'] ?? ''),
            'updated_at' => (string)$order['updated_at'],
            'history_count' => $histCount,
            'timeline' => $timeline,
            'history' => $historyItems,
            'signature' => $signature,
            'is_final' => $isFinal,
        ]);
    }

    if ($action === 'admin_summary' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $admin = current_admin();
        if (!$admin) json_out(['ok' => false, 'error' => 'Unauthorized'], 401);
        $pendingRec = admin_pending_reconciliation_count();
        $pendingOrders = (int)(row("SELECT COUNT(*) AS n FROM tbl_orders WHERE order_status='PENDING'")['n'] ?? 0);
        json_out([
            'ok' => true,
            'pending_reconciliation' => $pendingRec,
            'pending_orders' => $pendingOrders,
            'server_time' => date('Y-m-d H:i:s'),
        ]);
    }

    json_out(['ok' => false, 'error' => 'Unsupported action'], 400);
} catch (Throwable $e) {
    error_log('[BanHang api] ' . $e->getMessage());
    json_out(['ok' => false, 'error' => 'Lỗi hệ thống'], 500);
}
