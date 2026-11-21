<?php
declare(strict_types=1);

/**
 * BanHang optional SMTP Email Outbox.
 * - Never sends network mail inside a DB transaction.
 * - Uses PHPMailer automatically if installed via Composer, otherwise native PHP SMTP sockets.
 * - Never claims an email was sent when SMTP is unconfigured or delivery fails.
 */

function smtp_config(): array
{
    static $cached = null;
    if ($cached !== null) return $cached;
    $local = is_file(__DIR__ . '/admin/config/config.local.php') ? require __DIR__ . '/admin/config/config.local.php' : [];
    $get = static fn(string $key, string $default = ''): string =>
        trim((string)(getenv($key) !== false ? getenv($key) : ($local[$key] ?? $default)));

    $cached = [
        'host' => $get('SMTP_HOST'),
        'port' => max(1, (int)$get('SMTP_PORT', '587')),
        'secure' => strtolower($get('SMTP_SECURE', 'tls')),
        'user' => $get('SMTP_USER'),
        'pass' => (string)(getenv('SMTP_PASS') !== false ? getenv('SMTP_PASS') : ($local['SMTP_PASS'] ?? '')),
        'from_email' => $get('SMTP_FROM_EMAIL', $get('SMTP_USER')),
        'from_name' => $get('SMTP_FROM_NAME', setting('store_name', 'BanHang Tech Store')),
    ];
    return $cached;
}

function smtp_is_configured(): bool
{
    $cfg = smtp_config();
    return $cfg['host'] !== '' && filter_var($cfg['from_email'], FILTER_VALIDATE_EMAIL) !== false;
}

function render_order_email(string $eventType, array $order, string $extraMessage = ''): array
{
    $store = setting('store_name', 'BanHang Tech Store');
    $code = (string)$order['order_code'];
    $customer = (string)($order['customer_name'] ?? 'Quý khách');
    $amount = money((int)($order['total_amount'] ?? 0));
    $method = payment_method_label((string)($order['payment_method'] ?? 'COD'));
    $carrier = trim((string)($order['shipping_carrier'] ?? ''));
    $tracking = trim((string)($order['tracking_code'] ?? ''));

    [$subject, $heading, $lead, $badgeColor] = match ($eventType) {
        'ORDER_CREATED' => [
            "[{$store}] Xác nhận tạo đơn hàng {$code}",
            'Đơn hàng của bạn đã được ghi nhận',
            "Cảm ơn {$customer} đã đặt hàng tại {$store}. Tổng giá trị đơn hàng là {$amount} ({$method}).",
            '#2563eb',
        ],
        'PAYMENT_CONFIRMED' => [
            "[{$store}] Thanh toán thành công cho đơn hàng {$code}",
            'Thanh toán thành công!',
            "{$store} đã đối soát và xác nhận nhận đủ {$amount} cho đơn hàng {$code}. Đơn hàng của bạn đang được chuẩn bị.",
            '#137b4d',
        ],
        'ORDER_CONFIRMED' => [
            "[{$store}] Đơn hàng {$code} đã được xác nhận",
            'Đơn hàng đã được xác nhận',
            "Đơn hàng {$code} ({$amount}) đã được cửa hàng xác nhận và đưa vào quy trình xử lý.",
            '#2563eb',
        ],
        'ORDER_PREPARING' => [
            "[{$store}] Đơn hàng {$code} đang được chuẩn bị",
            'Đang chuẩn bị hàng',
            "Sản phẩm trong đơn hàng {$code} đang được kiểm tra kỹ và đóng gói cẩn thận trước khi bàn giao vận chuyển.",
            '#2563eb',
        ],
        'ORDER_SHIPPING' => [
            "[{$store}] Đơn hàng {$code} đang được giao",
            'Đơn hàng đang trên đường giao đến bạn',
            "Đơn hàng {$code} đã được bàn giao cho đơn vị vận chuyển."
            . ($carrier !== '' ? " Đơn vị vận chuyển: {$carrier}." : '')
            . ($tracking !== '' ? " Mã vận đơn: {$tracking}." : ''),
            '#0369a1',
        ],
        'ORDER_COMPLETED' => [
            "[{$store}] Giao hàng thành công đơn hàng {$code}",
            'Giao hàng thành công!',
            "Đơn hàng {$code} đã được giao thành công. Cảm ơn bạn đã tin tưởng mua sắm tại {$store}.",
            '#137b4d',
        ],
        'PAYMENT_REJECTED' => [
            "[{$store}] Cần kiểm tra lại chuyển khoản đơn hàng {$code}",
            'Cần đối chiếu lại giao dịch chuyển khoản',
            "Cửa hàng chưa tìm thấy giao dịch khớp cho đơn hàng {$code} ({$amount}). Vui lòng kiểm tra lại sao kê ngân hàng và gửi lại thông tin nếu cần.",
            '#b45309',
        ],
        'ORDER_CANCELLED' => [
            "[{$store}] Thông báo hủy đơn hàng {$code}",
            'Đơn hàng đã bị hủy',
            "Đơn hàng {$code} ({$amount}) đã được chuyển sang trạng thái Đã hủy.",
            '#b91c1c',
        ],
        default => [
            "[{$store}] Cập nhật đơn hàng {$code}",
            'Cập nhật trạng thái đơn hàng',
            "Đơn hàng {$code} của bạn vừa có cập nhật mới.",
            '#2563eb',
        ],
    };

    $extraHtml = trim($extraMessage) !== ''
        ? '<p style="margin:14px 0;padding:12px 14px;background:#f4f7fc;border-radius:8px;color:#1e293b;font-size:14px;line-height:1.6;">' . nl2br(h($extraMessage)) . '</p>'
        : '';

    $shippingHtml = '';
    if ($carrier !== '' || $tracking !== '') {
        $shippingHtml = '<p style="margin:10px 0;font-size:14px;color:#334155;">'
            . ($carrier !== '' ? '<strong>Đơn vị vận chuyển:</strong> ' . h($carrier) . '<br>' : '')
            . ($tracking !== '' ? '<strong>Mã vận đơn:</strong> ' . h($tracking) : '')
            . '</p>';
    }

    $html = '<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="margin:0;padding:24px 12px;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#151923;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5eaf2;">'
        . '<tr><td style="background:#101c33;padding:20px 26px;color:#ffffff;font-size:20px;font-weight:700;letter-spacing:-0.02em;">' . h($store) . '</td></tr>'
        . '<tr><td style="padding:26px;">'
        . '<span style="display:inline-block;padding:6px 12px;border-radius:999px;background:' . $badgeColor . ';color:#ffffff;font-size:12px;font-weight:700;">' . h($code) . '</span>'
        . '<h1 style="margin:14px 0 10px;font-size:22px;line-height:1.3;color:#101c33;">' . h($heading) . '</h1>'
        . '<p style="margin:0 0 14px;font-size:14px;line-height:1.65;color:#334155;">' . h($lead) . '</p>'
        . $extraHtml
        . $shippingHtml
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="8" style="margin:18px 0;border-collapse:collapse;background:#f8fafc;border-radius:8px;font-size:13px;">'
        . '<tr><td style="color:#64748b;">Mã đơn hàng</td><td style="text-align:right;font-weight:700;">' . h($code) . '</td></tr>'
        . '<tr><td style="color:#64748b;">Tổng tiền</td><td style="text-align:right;font-weight:700;color:#2563eb;">' . h($amount) . '</td></tr>'
        . '<tr><td style="color:#64748b;">Thanh toán</td><td style="text-align:right;">' . h($method) . '</td></tr>'
        . '<tr><td style="color:#64748b;">Trạng thái đơn</td><td style="text-align:right;">' . h(order_status_label((string)($order['order_status'] ?? 'PENDING'))) . '</td></tr>'
        . '</table>'
        . '<p style="margin:16px 0 0;font-size:12px;color:#64748b;line-height:1.6;">Vui lòng đăng nhập tài khoản trên website ' . h($store) . ' để xem chi tiết đơn hàng và theo dõi tiến độ mới nhất.</p>'
        . '</td></tr>'
        . '<tr><td style="padding:16px 26px;background:#f8fafc;border-top:1px solid #e5eaf2;font-size:11px;color:#64748b;">Email tự động từ hệ thống ' . h($store) . '. Không chia sẻ mật khẩu hoặc OTP qua email.</td></tr>'
        . '</table></body></html>';

    $text = "{$store}\n{$heading}\n\n{$lead}\n"
        . ($extraMessage !== '' ? "\n{$extraMessage}\n" : '')
        . "\nMã đơn hàng: {$code}\nTổng tiền: {$amount}\nThanh toán: {$method}\n";

    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}

function queue_order_email(array $order, string $eventType, string $extraMessage = '', ?string $dedupKey = null): void
{
    $userId = (int)($order['user_id'] ?? 0);
    if ($userId < 1) return;
    $user = row('SELECT email FROM tbl_dangky WHERE id_dangky = ? LIMIT 1', [$userId]);
    $email = trim((string)($user['email'] ?? ''));
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) return;

    $rendered = render_order_email($eventType, $order, $extraMessage);
    $configured = smtp_is_configured();
    $status = $configured ? 'PENDING' : 'SKIPPED';
    $lastError = $configured ? '' : 'SMTP chưa được cấu hình; thông báo vẫn được lưu trong hệ thống website.';

    run(
        'INSERT IGNORE INTO tbl_email_outbox(order_id,user_id,recipient_email,event_type,dedup_key,subject,body_html,body_text,status,last_error)
         VALUES (?,?,?,?,?,?,?,?,?,?)',
        [
            (int)($order['id'] ?? 0) ?: null,
            $userId,
            $email,
            $eventType,
            $dedupKey,
            mb_substr($rendered['subject'], 0, 200),
            $rendered['html'],
            $rendered['text'],
            $status,
            $lastError,
        ]
    );
}

function smtp_read_response($socket): string
{
    $response = '';
    while (($line = fgets($socket, 1024)) !== false) {
        $response .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') break;
    }
    return $response;
}

function smtp_command($socket, string $command, array $expectedCodes): string
{
    fwrite($socket, $command . "\r\n");
    $resp = smtp_read_response($socket);
    $code = (int)substr($resp, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException('SMTP error (' . $code . '): ' . trim($resp));
    }
    return $resp;
}

function smtp_deliver_message(string $to, string $subject, string $html, string $text): void
{
    $cfg = smtp_config();
    if (!smtp_is_configured()) {
        throw new RuntimeException('SMTP chưa được cấu hình.');
    }

    // Use PHPMailer automatically if installed in vendor/autoload.php
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }
    if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
        $mailerClass = '\\PHPMailer\\PHPMailer\\PHPMailer';
        $mail = new $mailerClass(true);
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = $cfg['host'];
        $mail->Port = $cfg['port'];
        $mail->Timeout = 8;
        if ($cfg['user'] !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = $cfg['user'];
            $mail->Password = $cfg['pass'];
        }
        if ($cfg['secure'] === 'ssl') $mail->SMTPSecure = 'ssl';
        elseif ($cfg['secure'] === 'tls') $mail->SMTPSecure = 'tls';
        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = $text;
        $mail->send();
        return;
    }

    // Native PHP SMTP socket delivery fallback (TLS/SSL/PLAIN, no Composer required)
    $transport = $cfg['secure'] === 'ssl' ? 'ssl://' : 'tcp://';
    $socket = @stream_socket_client($transport . $cfg['host'] . ':' . $cfg['port'], $errno, $errstr, 8);
    if (!$socket) {
        throw new RuntimeException("Không kết nối được SMTP ({$errno}): {$errstr}");
    }
    stream_set_timeout($socket, 8);
    try {
        $greeting = smtp_read_response($socket);
        if ((int)substr($greeting, 0, 3) !== 220) {
            throw new RuntimeException('SMTP greeting failed: ' . trim($greeting));
        }
        $hostName = preg_replace('/[^a-zA-Z0-9.\-]/', '', (string)($_SERVER['SERVER_NAME'] ?? 'localhost')) ?: 'localhost';
        smtp_command($socket, 'EHLO ' . $hostName, [250]);
        if ($cfg['secure'] === 'tls') {
            smtp_command($socket, 'STARTTLS', [220]);
            if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Không thể bật mã hóa STARTTLS.');
            }
            smtp_command($socket, 'EHLO ' . $hostName, [250]);
        }
        if ($cfg['user'] !== '') {
            smtp_command($socket, 'AUTH LOGIN', [334]);
            smtp_command($socket, base64_encode($cfg['user']), [334]);
            smtp_command($socket, base64_encode($cfg['pass']), [235]);
        }
        smtp_command($socket, 'MAIL FROM:<' . $cfg['from_email'] . '>', [250]);
        smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtp_command($socket, 'DATA', [354]);

        $boundary = 'bh_' . bin2hex(random_bytes(10));
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedFrom = '=?UTF-8?B?' . base64_encode($cfg['from_name']) . '?=';
        $headers = [
            'From: ' . $encodedFrom . ' <' . $cfg['from_email'] . '>',
            'To: <' . $to . '>',
            'Subject: ' . $encodedSubject,
            'Date: ' . gmdate('D, d M Y H:i:s +0000'),
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        $body = implode("\r\n", $headers) . "\r\n\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text)) . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html)) . "\r\n"
            . "--{$boundary}--\r\n.";
        smtp_command($socket, $body, [250]);
        @fwrite($socket, "QUIT\r\n");
    } finally {
        fclose($socket);
    }
}

function dispatch_email_outbox(int $limit = 5): array
{
    $stats = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
    if (db()->inTransaction()) return $stats;

    if (!smtp_is_configured()) {
        $skipped = run(
            "UPDATE tbl_email_outbox SET status='SKIPPED', last_error='SMTP chưa được cấu hình; thông báo vẫn hiển thị trên website.' WHERE status='PENDING'"
        );
        $stats['skipped'] = $skipped;
        return $stats;
    }

    $limit = max(1, min(20, $limit));
    $items = rows(
        "SELECT id,recipient_email,subject,body_html,body_text,attempts
         FROM tbl_email_outbox
         WHERE status IN ('PENDING','FAILED') AND attempts < 3
         ORDER BY id ASC LIMIT {$limit}"
    );
    foreach ($items as $item) {
        try {
            smtp_deliver_message(
                (string)$item['recipient_email'],
                (string)$item['subject'],
                (string)$item['body_html'],
                (string)$item['body_text']
            );
            run(
                "UPDATE tbl_email_outbox SET status='SENT', attempts=attempts+1, last_error='', sent_at=NOW() WHERE id=?",
                [(int)$item['id']]
            );
            $stats['sent']++;
        } catch (Throwable $e) {
            error_log('[BanHang mail] #' . (int)$item['id'] . ' ' . $e->getMessage());
            run(
                "UPDATE tbl_email_outbox SET status='FAILED', attempts=attempts+1, last_error=? WHERE id=?",
                [mb_substr($e->getMessage(), 0, 480), (int)$item['id']]
            );
            $stats['failed']++;
        }
    }
    return $stats;
}
