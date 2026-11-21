<?php
declare(strict_types=1);

/**
 * BanHang CLI Email Outbox Worker.
 * Designed for Windows Task Scheduler or cron (no Redis/RabbitMQ needed).
 * Strictly CLI-only; direct HTTP requests are rejected with 403.
 *
 * Usage:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\web_banhang\scripts\email_worker.php --limit=10 --stale-minutes=5
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Forbidden: CLI only.');
}

require dirname(__DIR__) . '/app.php';

$options = getopt('', ['limit::', 'stale-minutes::']);
$limit = isset($options['limit']) ? max(1, min(50, (int)$options['limit'])) : 10;
$staleMinutes = isset($options['stale-minutes']) ? max(1, min(1440, (int)$options['stale-minutes'])) : EMAIL_OUTBOX_STALE_MINUTES;

try {
    $recovered = recover_stale_outbox_rows($staleMinutes);
    $stats = dispatch_email_outbox($limit);
    $stats['recovered'] = $recovered;
    $stats['smtp_configured'] = smtp_is_configured(true);
    $stats['timestamp'] = date('Y-m-d H:i:s');
    echo json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    $cfg = smtp_config();
    $safe = smtp_sanitize_error($e->getMessage(), (string)($cfg['pass'] ?? ''));
    error_log('[BanHang email_worker] ' . $safe);
    fwrite(STDERR, 'Worker error: ' . $safe . PHP_EOL);
    exit(1);
}
