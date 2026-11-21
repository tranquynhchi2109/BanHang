<?php
declare(strict_types=1);

// Legacy mysqli bridge for modules that still use the original $mysqli connection.
// Configure through environment variables or admin/config/config.local.php.
$config = is_file(__DIR__ . '/config.local.php') ? require __DIR__ . '/config.local.php' : [];
$get = static fn(string $key, string $default): string =>
    (string)(getenv($key) !== false ? getenv($key) : ($config[$key] ?? $default));

$servername = $get('DB_HOST', '127.0.0.1');
$username = $get('DB_USER', 'root');
$password = $get('DB_PASS', '');
$dbname = $get('DB_NAME', 'web_mysqli');
$port = (int)$get('DB_PORT', '3306');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $mysqli = new mysqli($servername, $username, $password, $dbname, $port);
    $mysqli->set_charset('utf8mb4');
} catch (Throwable $e) {
    error_log('[BanHang mysqli] ' . $e->getMessage());
    http_response_code(500);
    exit('Không thể kết nối cơ sở dữ liệu. Vui lòng kiểm tra cấu hình máy chủ.');
}
