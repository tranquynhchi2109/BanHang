<?php
declare(strict_types=1);

/**
 * BanHang Hardened Optional SMTP & Email Outbox Service.
 * - Never sends network mail inside a DB transaction.
 * - Enforces TLS/SSL before SMTP AUTH (never allows AUTH LOGIN on unencrypted TCP,
 *   never silently downgrades invalid SMTP_SECURE values to plain TCP).
 * - Sanitizes all errors so SMTP credentials are never written to logs or DB.
 * - Uses atomic DB claiming (PENDING/FAILED -> PROCESSING -> SENT/FAILED) compatible
 *   with both MariaDB 10.4+ and MySQL 8.0, with stale-claim recovery and retry cap.
 * - Does NOT auto-flood historical SKIPPED rows when SMTP is enabled later.
 * - Note: SMTP does not guarantee distributed exactly-once delivery across network
 *   drops after DATA; atomic claiming prevents concurrent duplicate dispatches.
 */

const EMAIL_OUTBOX_MAX_ATTEMPTS = 3;
const EMAIL_OUTBOX_STALE_MINUTES = 5;

function smtp_config(bool $fresh = false): array
{
    static $cached = null;
    if ($cached !== null && !$fresh) return $cached;
    $local = is_file(__DIR__ . '/admin/config/config.local.php') ? require __DIR__ . '/admin/config/config.local.php' : [];
    $get = static fn(string $key, string $default = ''): string =>
        trim((string)(getenv($key) !== false ? getenv($key) : ($local[$key] ?? $default)));

    $portRaw = $get('SMTP_PORT', '587');
    $port = filter_var($portRaw, FILTER_VALIDATE_INT) !== false ? (int)$portRaw : 0;

    $cached = [
        'host' => $get('SMTP_HOST'),
        'port' => $port,
        'secure' => strtolower($get('SMTP_SECURE', 'tls')),
        'user' => $get('SMTP_USER'),
        'pass' => (string)(getenv('SMTP_PASS') !== false ? getenv('SMTP_PASS') : ($local['SMTP_PASS'] ?? '')),
        'from_email' => $get('SMTP_FROM_EMAIL', $get('SMTP_USER')),
        'from_name' => $get('SMTP_FROM_NAME', setting('store_name', 'BanHang Tech Store')),
    ];
    return $cached;
}

/**
 * Returns null if valid, or a descriptive validation error message if misconfigured/unsafe.
 */
function smtp_validate_config(array $cfg): ?string
{
    $host = trim((string)($cfg['host'] ?? ''));
    if ($host === '') return 'SMTP_HOST chưa được cấu hình.';
    if (preg_match('/[\s\x00-\x1F\x7F]/', $host)) return 'SMTP_HOST chứa ký tự không hợp lệ.';

    $port = (int)($cfg['port'] ?? 0);
    if ($port < 1 || $port > 65535) return 'SMTP_PORT phải nằm trong khoảng 1–65535.';

    $secure = strtolower(trim((string)($cfg['secure'] ?? '')));
    if (!in_array($secure, ['tls', 'ssl', 'none'], true)) {
        return 'SMTP_SECURE không hợp lệ (chỉ chấp nhận tls, ssl hoặc none không xác thực).';
    }

    $fromEmail = trim((string)($cfg['from_email'] ?? ''));
    if ($fromEmail === '' || filter_var($fromEmail, FILTER_VALIDATE_EMAIL) === false || preg_match('/[\r\n]/', $fromEmail)) {
        return 'SMTP_FROM_EMAIL không hợp lệ.';
    }

    $user = trim((string)($cfg['user'] ?? ''));
    $pass = (string)($cfg['pass'] ?? '');
    if ($user !== '' || $pass !== '') {
        if ($user === '' || $pass === '') {
            return 'Thiếu SMTP_USER hoặc SMTP_PASS khi bật xác thực SMTP.';
        }
        if (!in_array($secure, ['tls', 'ssl'], true)) {
            return 'Không cho phép xác thực SMTP AUTH trên kết nối không mã hóa (phải dùng tls hoặc ssl).';
        }
    }

    return null;
}

function smtp_is_configured(bool $fresh = false): bool
{
    return smtp_validate_config(smtp_config($fresh)) === null;
}

function smtp_sanitize_error(string $message, string $secret = ''): string
{
    $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', ' ', $message) ?? $message;
    if ($secret !== '') {
        $clean = str_replace($secret, '[REDACTED]', $clean);
        $b64 = base64_encode($secret);
        if ($b64 !== '') {
            $clean = str_replace($b64, '[REDACTED]', $clean);
        }
    }
    return mb_substr(trim($clean), 0, 480);
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
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false || preg_match('/[\r\n]/', $email)) return;

    $rendered = render_order_email($eventType, $order, $extraMessage);
    $cfgError = smtp_validate_config(smtp_config());
    $configured = ($cfgError === null);
    $status = $configured ? 'PENDING' : 'SKIPPED';
    $lastError = $configured ? '' : ($cfgError . ' Thông báo vẫn được lưu trong hệ thống website.');

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
            mb_substr($lastError, 0, 480),
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
    $meta = stream_get_meta_data($socket);
    if (!empty($meta['timed_out'])) {
        throw new RuntimeException('Kết nối SMTP hết thời gian chờ (timeout).');
    }
    if ($response === '') {
        throw new RuntimeException('Kết nối SMTP bị ngắt đột ngột.');
    }
    return $response;
}

function smtp_command($socket, string $command, array $expectedCodes, bool $redactInError = false): string
{
    $written = @fwrite($socket, $command . "\r\n");
    if ($written === false) {
        throw new RuntimeException('Không thể ghi dữ liệu tới máy chủ SMTP.');
    }
    $resp = smtp_read_response($socket);
    $code = (int)substr($resp, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        $detail = $redactInError ? 'Xác thực SMTP thất bại (kiểm tra tài khoản/mật khẩu).' : ('SMTP error (' . $code . '): ' . trim($resp));
        throw new RuntimeException($detail);
    }
    return $resp;
}

function smtp_deliver_message(string $to, string $subject, string $html, string $text, ?array $cfgOverride = null): void
{
    $to = trim($to);
    if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false || preg_match('/[\r\n]/', $to)) {
        throw new RuntimeException('Địa chỉ email người nhận không hợp lệ.');
    }
    $cfg = $cfgOverride ?? smtp_config();
    $cfgError = smtp_validate_config($cfg);
    if ($cfgError !== null) {
        throw new RuntimeException($cfgError);
    }

    $secret = (string)($cfg['pass'] ?? '');
    $secure = strtolower((string)$cfg['secure']);
    $useAuth = trim((string)$cfg['user']) !== '';

    // Double-check invariant: never authenticate over unencrypted transport
    if ($useAuth && !in_array($secure, ['tls', 'ssl'], true)) {
        throw new RuntimeException('Không cho phép xác thực SMTP AUTH trên kết nối chưa mã hóa.');
    }

    // Use PHPMailer automatically if installed in vendor/autoload.php
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }
    if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
        try {
            $mailerClass = '\\PHPMailer\\PHPMailer\\PHPMailer';
            $mail = new $mailerClass(true);
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host = $cfg['host'];
            $mail->Port = (int)$cfg['port'];
            $mail->Timeout = 8;
            $mail->SMTPAutoTLS = false;
            if ($secure === 'ssl') {
                $mail->SMTPSecure = 'ssl';
            } elseif ($secure === 'tls') {
                $mail->SMTPSecure = 'tls';
            } else {
                $mail->SMTPSecure = '';
            }
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                ],
            ];
            if ($useAuth) {
                $mail->SMTPAuth = true;
                $mail->Username = $cfg['user'];
                $mail->Password = $secret;
            }
            $mail->setFrom($cfg['from_email'], $cfg['from_name']);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $text;
            $mail->send();
            return;
        } catch (Throwable $e) {
            throw new RuntimeException(smtp_sanitize_error($e->getMessage(), $secret), 0, $e);
        }
    }

    // Native PHP SMTP socket delivery fallback (TLS/SSL strict, no silent plain-TCP downgrade)
    if ($secure === 'ssl') {
        $transport = 'ssl://';
        $isEncrypted = true;
    } elseif ($secure === 'tls' || $secure === 'none') {
        $transport = 'tcp://';
        $isEncrypted = false;
    } else {
        throw new RuntimeException('Chế độ bảo mật SMTP không được hỗ trợ.');
    }

    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
        ],
    ]);
    $socket = @stream_socket_client(
        $transport . $cfg['host'] . ':' . (int)$cfg['port'],
        $errno,
        $errstr,
        8,
        STREAM_CLIENT_CONNECT,
        $context
    );
    if (!$socket) {
        throw new RuntimeException(smtp_sanitize_error("Không kết nối được SMTP ({$errno}): {$errstr}", $secret));
    }
    stream_set_timeout($socket, 8);

    try {
        $greeting = smtp_read_response($socket);
        if ((int)substr($greeting, 0, 3) !== 220) {
            throw new RuntimeException('SMTP greeting failed: ' . trim($greeting));
        }
        $hostName = preg_replace('/[^a-zA-Z0-9.\-]/', '', (string)($_SERVER['SERVER_NAME'] ?? 'localhost')) ?: 'localhost';
        smtp_command($socket, 'EHLO ' . $hostName, [250]);

        if ($secure === 'tls') {
            smtp_command($socket, 'STARTTLS', [220]);
            $cryptoMethod = defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')
                ? (STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)
                : STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (!@stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
                throw new RuntimeException('Không thể bật mã hóa STARTTLS (TLS 1.2+).');
            }
            $isEncrypted = true;
            smtp_command($socket, 'EHLO ' . $hostName, [250]);
        }

        if ($useAuth) {
            if (!$isEncrypted) {
                throw new RuntimeException('Từ chối xác thực SMTP AUTH trên kết nối chưa mã hóa TLS/SSL.');
            }
            smtp_command($socket, 'AUTH LOGIN', [334]);
            smtp_command($socket, base64_encode($cfg['user']), [334], true);
            smtp_command($socket, base64_encode($secret), [235], true);
        }

        smtp_command($socket, 'MAIL FROM:<' . $cfg['from_email'] . '>', [250]);
        smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtp_command($socket, 'DATA', [354]);

        $boundary = 'bh_' . bin2hex(random_bytes(10));
        $encodedSubject = '=?UTF-8?B?' . base64_encode(str_replace(["\r", "\n"], ' ', $subject)) . '?=';
        $encodedFrom = '=?UTF-8?B?' . base64_encode(str_replace(["\r", "\n"], ' ', (string)$cfg['from_name'])) . '?=';
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
    } catch (Throwable $e) {
        throw new RuntimeException(smtp_sanitize_error($e->getMessage(), $secret), 0, $e);
    } finally {
        fclose($socket);
    }
}

/**
 * Recover rows stuck in PROCESSING beyond stale threshold so crashes never hang the queue.
 */
function recover_stale_outbox_rows(int $staleMinutes = EMAIL_OUTBOX_STALE_MINUTES): int
{
    $staleMinutes = max(1, min(1440, $staleMinutes));
    $maxAttempts = EMAIL_OUTBOX_MAX_ATTEMPTS;
    return run(
        "UPDATE tbl_email_outbox
         SET status = CASE WHEN attempts >= {$maxAttempts} THEN 'FAILED' ELSE 'PENDING' END,
             claim_token = NULL,
             claimed_at = NULL,
             last_error = CASE WHEN last_error = '' THEN 'Phiên gửi email trước bị gián đoạn; đã khôi phục hàng đợi.' ELSE last_error END
         WHERE status = 'PROCESSING' AND claimed_at IS NOT NULL AND claimed_at < NOW() - INTERVAL {$staleMinutes} MINUTE"
    );
}

/**
 * Atomically claim eligible PENDING/FAILED rows under InnoDB row locks (works on both
 * MariaDB 10.4+ and MySQL 8.0 without requiring SKIP LOCKED).
 * Never claims SENT or SKIPPED rows.
 */
function claim_email_outbox_batch(int $limit = 5, ?string $claimToken = null): array
{
    recover_stale_outbox_rows();
    $limit = max(1, min(20, $limit));
    $maxAttempts = EMAIL_OUTBOX_MAX_ATTEMPTS;
    $token = $claimToken ?? bin2hex(random_bytes(16));

    $claimedCount = run(
        "UPDATE tbl_email_outbox
         SET status = 'PROCESSING',
             claim_token = ?,
             claimed_at = NOW(),
             attempts = attempts + 1
         WHERE status IN ('PENDING', 'FAILED')
           AND attempts < {$maxAttempts}
           AND (next_retry_at IS NULL OR next_retry_at <= NOW())
         ORDER BY id ASC
         LIMIT {$limit}",
        [$token]
    );
    if ($claimedCount < 1) return [];

    return rows(
        "SELECT id,order_id,user_id,recipient_email,event_type,subject,body_html,body_text,attempts,claim_token
         FROM tbl_email_outbox
         WHERE status = 'PROCESSING' AND claim_token = ?
         ORDER BY id ASC",
        [$token]
    );
}

/**
 * Re-queue eligible FAILED rows (attempts < max) and recover stale PROCESSING rows
 * so the CLI worker picks them up on the next tick without blocking web requests.
 */
function requeue_failed_outbox_emails(): array
{
    $recovered = recover_stale_outbox_rows();
    $maxAttempts = EMAIL_OUTBOX_MAX_ATTEMPTS;
    $requeued = run(
        "UPDATE tbl_email_outbox
         SET status = 'PENDING', next_retry_at = NOW()
         WHERE status = 'FAILED' AND attempts < {$maxAttempts}"
    );
    return ['recovered' => $recovered, 'requeued' => $requeued];
}

/**
 * Explicitly re-queue historical SKIPPED mail only after Admin confirms SMTP is valid.
 * Workers never auto-retry SKIPPED rows.
 */
function requeue_skipped_outbox_emails(bool $smtpConfigured): int
{
    if (!$smtpConfigured) {
        throw new DomainException('SMTP chưa cấu hình hợp lệ. Không thể gửi lại email SKIPPED.');
    }
    $maxAttempts = EMAIL_OUTBOX_MAX_ATTEMPTS;
    return run(
        "UPDATE tbl_email_outbox
         SET status='PENDING', last_error='', claim_token=NULL, claimed_at=NULL, next_retry_at=NOW()
         WHERE status='SKIPPED' AND attempts < {$maxAttempts}"
    );
}

/**
 * Process email outbox outside any DB transaction (for CLI worker & tests).
 * Optional $sender callable allows deterministic unit/integration testing without external SMTP.
 * Historical SKIPPED rows are NOT auto-dispatched when SMTP is later enabled.
 */
function dispatch_email_outbox(int $limit = 5, ?callable $sender = null): array
{
    $stats = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'claimed' => 0];
    if (db()->inTransaction()) return $stats;

    $cfg = smtp_config();
    $cfgError = smtp_validate_config($cfg);
    if ($sender === null && $cfgError !== null) {
        $msg = mb_substr($cfgError . ' Thông báo vẫn hiển thị trên website.', 0, 480);
        $skipped = run(
            "UPDATE tbl_email_outbox SET status='SKIPPED', last_error=?, claim_token=NULL, claimed_at=NULL WHERE status='PENDING'",
            [$msg]
        );
        $stats['skipped'] = $skipped;
        return $stats;
    }

    $items = claim_email_outbox_batch($limit);
    $stats['claimed'] = count($items);
    $secret = (string)($cfg['pass'] ?? '');

    foreach ($items as $item) {
        $id = (int)$item['id'];
        $token = (string)$item['claim_token'];
        $attempts = (int)$item['attempts'];
        try {
            if ($sender !== null) {
                $sender(
                    (string)$item['recipient_email'],
                    (string)$item['subject'],
                    (string)$item['body_html'],
                    (string)$item['body_text'],
                    $item
                );
            } else {
                smtp_deliver_message(
                    (string)$item['recipient_email'],
                    (string)$item['subject'],
                    (string)$item['body_html'],
                    (string)$item['body_text'],
                    $cfg
                );
            }
            run(
                "UPDATE tbl_email_outbox
                 SET status='SENT', last_error='', sent_at=NOW(), claim_token=NULL, claimed_at=NULL, next_retry_at=NULL
                 WHERE id=? AND claim_token=? AND status='PROCESSING'",
                [$id, $token]
            );
            $stats['sent']++;
        } catch (Throwable $e) {
            $safeErr = smtp_sanitize_error($e->getMessage(), $secret);
            error_log('[BanHang mail] #' . $id . ' ' . $safeErr);
            $backoffMin = min(30, 2 * max(1, $attempts));
            run(
                "UPDATE tbl_email_outbox
                 SET status='FAILED', last_error=?, claim_token=NULL, claimed_at=NULL,
                     next_retry_at=DATE_ADD(NOW(), INTERVAL {$backoffMin} MINUTE)
                 WHERE id=? AND claim_token=? AND status='PROCESSING'",
                [$safeErr, $id, $token]
            );
            $stats['failed']++;
        }
    }
    return $stats;
}
