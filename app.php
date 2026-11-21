<?php
declare(strict_types=1);

/**
 * BanHang shared infrastructure.
 * Public front controllers: index.php and admin/index.php.
 * No framework, no composer dependency; PHP 8.2+ and pdo_mysql.
 */
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name('PHPSESSID');
$scriptDirectory = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
if (preg_match('~/admin$~', $scriptDirectory)) $scriptDirectory = substr($scriptDirectory, 0, -6);
$cookiePath = rtrim($scriptDirectory, '/');
if ($cookiePath === '') $cookiePath = '/';
session_set_cookie_params([
    'lifetime' => 0, 'path' => $cookiePath, 'secure' => $https,
    'httponly' => true, 'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' https://fonts.googleapis.com; img-src 'self' https://img.vietqr.io https://images.samsung.com https://cdsassets.apple.com https://in.store.asus.com https://www.sony.com.pe https://cdn.tgdd.vn data:; font-src 'self' https://fonts.gstatic.com; form-action 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'");
header('Cache-Control: no-store');

function base_path(): string
{
    static $base = null;
    if ($base !== null) return $base;
    $directory = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
    if (preg_match('~/admin$~', $directory)) $directory = substr($directory, 0, -6);
    $base = rtrim($directory, '/');
    return $base;
}
function project_url(string $path): string
{
    return base_path() . '/' . ltrim($path, '/');
}
function asset_url(string $path): string
{
    $relative = ltrim($path, '/');
    $file = __DIR__ . '/' . $relative;
    $version = is_file($file) ? (string) filemtime($file) : '1';
    return project_url($relative) . '?v=' . rawurlencode($version);
}

function db(): PDO
{
    static $db = null;
    if ($db instanceof PDO) return $db;
    $config = is_file(__DIR__ . '/admin/config/config.local.php') ? require __DIR__ . '/admin/config/config.local.php' : [];
    $get = static fn(string $key, string $default): string =>
        (string) (getenv($key) !== false ? getenv($key) : ($config[$key] ?? $default));
    $host = $get('DB_HOST', '127.0.0.1');
    $port = $get('DB_PORT', '3306');
    $name = $get('DB_NAME', 'web_mysqli');
    $username = $get('DB_USER', 'root');
    $password = $get('DB_PASS', '');
    $db = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_PERSISTENT => false,
    ]);
    return $db;
}

function row(string $sql, array $values = []): ?array
{
    $statement = db()->prepare($sql);
    $statement->execute($values);
    $result = $statement->fetch();
    return $result === false ? null : $result;
}
function rows(string $sql, array $values = []): array
{
    $statement = db()->prepare($sql);
    $statement->execute($values);
    return $statement->fetchAll();
}
function run(string $sql, array $values = []): int
{
    $statement = db()->prepare($sql);
    $statement->execute($values);
    return $statement->rowCount();
}
function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function money(int|float|string $amount): string
{
    return number_format((float)$amount, 0, ',', '.') . ' ₫';
}
function link_to(string $page, array $args = []): string
{
    return project_url('index.php') . '?' . http_build_query(['page' => $page] + $args, '', '&', PHP_QUERY_RFC3986);
}
function admin_to(string $page, array $args = []): string
{
    return project_url('admin/index.php') . '?' . http_build_query(['page' => $page] + $args, '', '&', PHP_QUERY_RFC3986);
}
function redirect_to(string $url): never
{
    header('Location: ' . $url, true, 303);
    exit;
}
function csrf_token(): string
{
    if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}
function ensure_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method not allowed');
    }
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(403);
        exit('Phiên đã hết hạn hoặc mã bảo vệ không hợp lệ. Tải lại trang rồi thử lại.');
    }
}
function notice(string $message, string $type = 'info'): void
{
    $_SESSION['notices'][] = ['message' => $message, 'type' => $type];
}
function notices_html(): void
{
    $messages = $_SESSION['notices'] ?? [];
    unset($_SESSION['notices']);
    foreach ($messages as $item) {
        echo '<div class="alert ' . h($item['type']) . '">' . h($item['message']) . '</div>';
    }
}
function current_user(): ?array
{
    $id = filter_var($_SESSION['customer_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) return null;
    return row('SELECT id_dangky,tenkhachhang,email,dienthoai,diachi FROM tbl_dangky WHERE id_dangky=?', [$id]);
}
function require_customer(): array
{
    $user = current_user();
    if (!$user) redirect_to(link_to('login'));
    return $user;
}
function current_admin(): ?array
{
    $id = filter_var($_SESSION['admin_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) return null;
    return row('SELECT id_admin,username,admin_status FROM tbl_admin WHERE id_admin=? AND admin_status=1', [$id]);
}
function cart_key(int $productId, string $variant = 'BASE'): string
{
    $variant = strtoupper(trim($variant));
    if (!in_array($variant, ['BASE','128GB','256GB','512GB','1TB'], true)) $variant = 'BASE';
    return $productId . ':' . $variant;
}
function parse_cart_key(string|int $key): array
{
    $parts = explode(':', (string)$key, 2);
    $productId = (int)($parts[0] ?? 0);
    $variant = strtoupper(trim($parts[1] ?? 'BASE'));
    if (!in_array($variant, ['BASE','128GB','256GB','512GB','1TB'], true)) $variant = 'BASE';
    return [$productId, $variant];
}
function is_phone_product(array $product): bool
{
    return mb_stripos((string)($product['tendanhmuc'] ?? ''), 'điện thoại') !== false;
}
function available_variants(int $productId): array
{
    $items = rows('SELECT variant_code,price FROM tbl_product_variants WHERE product_id=? AND is_active=1 AND price>0', [$productId]);
    $result = [];
    foreach ($items as $item) $result[(string)$item['variant_code']] = (int)$item['price'];
    return $result;
}
/**
 * One authoritative price for listing, product detail, cart and checkout.
 * Never compute synthetic capacity uplifts from the base product price.
 */
function variant_price(array $product, string $variant = 'BASE', bool $lock = false): int
{
    $base = (int)($product['giasp'] ?? 0);
    if (!is_phone_product($product)) {
        if ($variant !== 'BASE') throw new DomainException('Phiên bản sản phẩm không hợp lệ.');
        if ($base<=0) throw new DomainException('Sản phẩm chưa có giá bán.');
        return $base;
    }
    if ($variant === 'BASE') $variant = '128GB';
    if (!in_array($variant, ['128GB','256GB','512GB','1TB'], true)) {
        throw new DomainException('Dung lượng sản phẩm không hợp lệ.');
    }
    $sql = 'SELECT price FROM tbl_product_variants WHERE product_id=? AND variant_code=? AND is_active=1 AND price>0';
    if ($lock) $sql .= ' FOR UPDATE';
    $priceRow = row($sql, [(int)$product['id_sanpham'], $variant]);
    if (!$priceRow) throw new DomainException('Dung lượng ' . $variant . ' chưa có giá bán được xác nhận. Vui lòng chọn dung lượng khác.');
    return (int)$priceRow['price'];
}
function cart_product_quantity(array $cart, int $productId, string $excludeKey = ''): int
{
    $total = 0;
    foreach ($cart as $key => $quantity) {
        if ((string)$key === $excludeKey) continue;
        [$id] = parse_cart_key((string)$key);
        if ($id === $productId) $total += max(0, (int)$quantity);
    }
    return $total;
}
function migrate_legacy_session(): void
{
    if (!isset($_SESSION['customer_id']) && isset($_SESSION['id_khachhang'])) {
        $_SESSION['customer_id'] = (int)$_SESSION['id_khachhang'];
    }
    if (!isset($_SESSION['admin_id']) && isset($_SESSION['dangnhap']) && is_string($_SESSION['dangnhap'])) {
        $legacyAdmin = row('SELECT id_admin FROM tbl_admin WHERE username=? AND admin_status=1 LIMIT 1', [$_SESSION['dangnhap']]);
        if ($legacyAdmin) $_SESSION['admin_id'] = (int)$legacyAdmin['id_admin'];
    }
    $cart = $_SESSION['cart'] ?? [];
    if (!is_array($cart) || !$cart) return;
    $normalized = [];
    foreach ($cart as $key => $item) {
        if (is_array($item)) {
            $id = (int)($item['id'] ?? 0);
            $quantity = (int)($item['soluong'] ?? 0);
            $variant = strtoupper(trim((string)($item['dungluong'] ?? 'BASE')));
            if ($variant === '') $variant = 'BASE';
        } else {
            [$id, $variant] = parse_cart_key((string)$key);
            $quantity = (int)$item;
        }
        if ($id > 0 && $quantity > 0) {
            $normalizedKey = cart_key($id, $variant);
            $normalized[$normalizedKey] = min(20, ($normalized[$normalizedKey] ?? 0) + $quantity);
        }
    }
    $_SESSION['cart'] = $normalized;
}
function require_admin(): array
{
    $admin = current_admin();
    if (!$admin) redirect_to(admin_to('login'));
    return $admin;
}
function sign_out(string $area): void
{
    if ($area === 'admin') unset($_SESSION['admin_id']);
    else unset($_SESSION['customer_id'], $_SESSION['cart']);
    session_regenerate_id(true);
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function attempt_key(string $kind, string $identity): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return hash('sha256', $kind . '|' . mb_strtolower(trim($identity), 'UTF-8') . '|' . $ip);
}
function throttle_login(string $kind, string $identity): void
{
    $key = attempt_key($kind, $identity);
    $result = row('SELECT COUNT(*) AS total FROM tbl_login_attempts WHERE key_hash=? AND attempted_at > NOW() - INTERVAL 15 MINUTE', [$key]);
    if ((int)($result['total'] ?? 0) >= 6) {
        throw new DomainException('Đăng nhập quá nhiều lần. Vui lòng thử lại sau 15 phút.');
    }
}
function failed_login(string $kind, string $identity): void
{
    run('INSERT INTO tbl_login_attempts(key_hash) VALUES (?)', [attempt_key($kind, $identity)]);
}
function successful_login(string $kind, string $identity): void
{
    run('DELETE FROM tbl_login_attempts WHERE key_hash=?', [attempt_key($kind, $identity)]);
}
function verify_legacy_password(string $plain, string $hash): bool
{
    if (preg_match('/^[a-f0-9]{32}$/i', $hash)) {
        return hash_equals(strtolower($hash), md5($plain));
    }
    return password_verify($plain, $hash);
}
function login_customer(string $email, string $password): bool
{
    throttle_login('customer', $email);
    $user = row('SELECT id_dangky,email,matkhau FROM tbl_dangky WHERE email=? LIMIT 1', [$email]);
    if (!$user || !verify_legacy_password($password, $user['matkhau'])) {
        failed_login('customer', $email);
        return false;
    }
    if (password_needs_rehash($user['matkhau'], PASSWORD_DEFAULT)) {
        run('UPDATE tbl_dangky SET matkhau=? WHERE id_dangky=?', [password_hash($password, PASSWORD_DEFAULT), $user['id_dangky']]);
    }
    successful_login('customer', $email);
    session_regenerate_id(true);
    $_SESSION['customer_id'] = (int)$user['id_dangky'];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return true;
}
function login_admin(string $username, string $password): bool
{
    throttle_login('admin', $username);
    $admin = row('SELECT id_admin,username,password FROM tbl_admin WHERE username=? AND admin_status=1 LIMIT 1', [$username]);
    if (!$admin || !verify_legacy_password($password, $admin['password'])) {
        failed_login('admin', $username);
        return false;
    }
    if (password_needs_rehash($admin['password'], PASSWORD_DEFAULT)) {
        run('UPDATE tbl_admin SET password=? WHERE id_admin=?', [password_hash($password, PASSWORD_DEFAULT), $admin['id_admin']]);
    }
    successful_login('admin', $username);
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$admin['id_admin'];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return true;
}
/**
 * Prefer store-owned assets. The legacy SQL dump includes filenames that never
 * existed in the admin upload folder: resolve them without changing DB records.
 *
 * For five demo products without matching local media, use exact product photos
 * from manufacturer/product pages. Those remote sources need an internet
 * connection; owners should replace them with licensed local uploads in Admin.
 */

/**
 * Manual bank transfer workflow. No bank API or browser proof confirms payment.
 * Every mutation checks the owner/admin identity and locks the order row.
 */
function payment_event(int $orderId, string $event, string $actorType, int $actorId, string $detail = '', ?string $reference = null): void
{
    run('INSERT INTO tbl_payment_events(order_id,event_type,actor_type,actor_id,detail,reference_code)
         VALUES (?,?,?,?,?,?)', [$orderId,$event,$actorType,$actorId,$detail,$reference]);
}

function report_bank_transfer(string $code, int $customerId, string $note): void
{
    $note=trim($note);
    if (mb_strlen($note)>200 || preg_match('/[\x00-\x08\x0B-\x1F\x7F]/', $note)) {
        throw new DomainException('Ghi chú chuyển khoản không hợp lệ (tối đa 200 ký tự).');
    }
    db()->beginTransaction();
    try {
        $order=row('SELECT id,payment_method,payment_status,payment_review_status,order_status
                    FROM tbl_orders WHERE order_code=? AND user_id=? FOR UPDATE',[$code,$customerId]);
        if (!$order) throw new DomainException('Không tìm thấy đơn hàng của bạn.');
        if ($order['payment_method']!=='BANK' || $order['payment_status']!=='UNPAID'
            || $order['order_status']==='CANCELLED') throw new DomainException('Đơn hàng không còn chờ chuyển khoản.');
        if ($order['payment_review_status']==='REPORTED') throw new DomainException('Đơn hàng đã được báo chuyển khoản, cửa hàng đang đối soát.');
        if ($order['payment_review_status']==='VERIFIED') throw new DomainException('Đơn hàng đã được xác nhận thanh toán.');
        run("UPDATE tbl_orders SET payment_review_status='REPORTED', payment_reported_at=NOW(), customer_transfer_note=?
             WHERE id=?",[$note,$order['id']]);
        payment_event((int)$order['id'],'CUSTOMER_REPORTED','CUSTOMER',$customerId,$note);
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}

function confirm_bank_transfer(int $orderId, int $adminId, string $reference, int $receivedAmount): void
{
    $reference=trim($reference);
    if (mb_strlen($reference)<5 || mb_strlen($reference)>120
        || preg_match('/[\x00-\x1F\x7F]/', $reference))
        throw new DomainException('Nhập mã giao dịch MB Bank từ sao kê (5–120 ký tự).');
    if ($receivedAmount<=0) throw new DomainException('Số tiền đối soát không hợp lệ.');
    db()->beginTransaction();
    try {
        $order=row('SELECT id,total_amount,payment_method,payment_status,order_status
                    FROM tbl_orders WHERE id=? FOR UPDATE',[$orderId]);
        if (!$order || $order['payment_method']!=='BANK' || $order['payment_status']!=='UNPAID'
            || $order['order_status']==='CANCELLED')
            throw new DomainException('Không thể xác nhận đơn này hoặc đơn đã được thanh toán.');
        if ((int)$order['total_amount']!==$receivedAmount)
            throw new DomainException('Số tiền nhận thực tế khác tổng tiền đơn hàng. Không thể xác nhận.');
        if (row('SELECT id FROM tbl_orders WHERE bank_reference=? LIMIT 1',[$reference]))
            throw new DomainException('Mã giao dịch ngân hàng này đã được dùng cho một đơn khác.');
        run("UPDATE tbl_orders SET payment_status='PAID',payment_review_status='VERIFIED',
             payment_verified_at=NOW(),payment_verified_by=?,bank_reference=? WHERE id=?",
             [$adminId,$reference,$orderId]);
        payment_event($orderId,'ADMIN_CONFIRMED','ADMIN',$adminId,
            'Admin đã đối soát đủ số tiền '.(string)$receivedAmount.' VND với sao kê MB Bank',$reference);
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        if ($e instanceof PDOException && (string)$e->getCode()==='23000')
            throw new DomainException('Mã giao dịch đã được sử dụng. Kiểm tra sao kê và các đơn đã xác nhận.');
        throw $e;
    }
}

function reject_bank_report(int $orderId, int $adminId, string $reason): void
{
    $reason=trim($reason);
    if (mb_strlen($reason)<5 || mb_strlen($reason)>300 || preg_match('/[\x00-\x1F\x7F]/',$reason))
        throw new DomainException('Nhập lý do cần đối chiếu lại (5–300 ký tự).');
    db()->beginTransaction();
    try {
        $order=row('SELECT id,payment_status,payment_method,order_status,payment_review_status
                    FROM tbl_orders WHERE id=? FOR UPDATE',[$orderId]);
        if (!$order || $order['payment_method']!=='BANK' || $order['payment_status']!=='UNPAID'
            || $order['order_status']==='CANCELLED' || $order['payment_review_status']!=='REPORTED')
            throw new DomainException('Không có báo cáo chuyển khoản đang chờ xem xét.');
        run("UPDATE tbl_orders SET payment_review_status='REJECTED' WHERE id=?",[$orderId]);
        payment_event($orderId,'ADMIN_REJECTED','ADMIN',$adminId,$reason);
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}

function expire_unpaid_bank_order(int $orderId, int $adminId): void
{
    db()->beginTransaction();
    try {
        $order=row('SELECT id,payment_method,payment_status,payment_review_status,order_status,
                    (created_at < NOW() - INTERVAL 24 HOUR) AS past_deadline
                    FROM tbl_orders WHERE id=? FOR UPDATE',[$orderId]);
        if (!$order || $order['payment_method']!=='BANK' || $order['payment_status']!=='UNPAID'
            || $order['order_status']!=='PENDING' || !$order['past_deadline'])
            throw new DomainException('Chỉ hủy thủ công đơn chuyển khoản chờ xử lý trên 24 giờ.');
        if ($order['payment_review_status']==='REPORTED')
            throw new DomainException('Khách đã báo chuyển khoản. Phải đối soát ngân hàng trước khi hủy.');
        $items=rows('SELECT product_id,quantity FROM tbl_order_items WHERE order_id=?',[$orderId]);
        foreach ($items as $item) {
            run('UPDATE tbl_sanpham SET soluong=soluong+? WHERE id_sanpham=?',
                [(int)$item['quantity'],(int)$item['product_id']]);
        }
        run("UPDATE tbl_orders SET order_status='CANCELLED' WHERE id=?",[$orderId]);
        payment_event($orderId,'ADMIN_EXPIRED','ADMIN',$adminId,
            'Hủy thủ công đơn chưa thanh toán quá 24 giờ; đã hoàn tồn kho.');
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}

function safe_image(string $filename, string $kind = 'product'): string
{
    $basename = basename(str_replace('\\', '/', trim($filename)));
    if ($basename === '' || !preg_match('/\.(?:png|jpe?g|webp)$/i', $basename)) {
        return project_url('images/fallback.svg');
    }
    $uploadFolder = $kind === 'news' ? 'quanlybaiviet' : 'quanlysp';
    $uploadBase = 'admin/modules/' . $uploadFolder . '/uploads/';
    if (is_file(__DIR__ . '/' . $uploadBase . $basename)) {
        return project_url($uploadBase . rawurlencode($basename));
    }
    if (is_file(__DIR__ . '/images/' . $basename)) {
        return project_url('images/' . rawurlencode($basename));
    }

    // Aliases for real images already committed to this repository.
    // Always prefer a product owner's uploaded image over an alias.
    $aliases = [
        'iphone15.jpg' => 'ip 15prm.webp',
        'tintuc1.jpg' => 'ip15pro.jpg',
        'tintuc2.jpg' => 'Asus TUF Gaming A15.jpg',
    ];
    $alias = $aliases[strtolower($basename)] ?? null;
    if ($alias !== null && is_file(__DIR__ . '/images/' . $alias)) {
        return project_url('images/' . rawurlencode($alias));
    }

    // Curated exact-model photos; these are remotely hosted product media.
    // No arbitrary external URL from user-controlled database fields is used.
    $productMedia = [
        's24ultra.jpg' => 'https://images.samsung.com/is/image/samsung/assets/co/smartphones/galaxy-s24-ultra/buy/S24Ultra-Color-Titanium_Grey_MO_0527_final.jpg',
        'macbookm3.jpg' => 'https://cdsassets.apple.com/live/7WUAS350/images/tech-specs/mba_13_m3_2024_hero.png',
        'asusrog.jpg' => 'https://in.store.asus.com/media/catalog/product/g/6/g614jv_eclps_gry_4zn_rgb_kbd_mso_rfrsh_1_.jpg',
        'sonyxm5.jpg' => 'https://www.sony.com.pe/image/6145c1d32e6ac8e63a46c912dc33c5bb?fmt=png-alpha&hei=880&wid=880',
        'anker20k.jpg' => 'https://cdn.tgdd.vn/Products/Images/57/328167/pin-sac-du-phong-20000mah-type-c-pd-qc-3-0-30w-anker-a1384-kem-cap-type-c-thumb-600x600.jpg',
    ];
    return $productMedia[strtolower($basename)] ?? project_url('images/fallback.svg');
}

function backup_image(string $category = ''): string
{
    $label = mb_strtolower(trim($category));
    if (str_contains($label, 'điện thoại')) return project_url('images/' . rawurlencode('ip 15prm.webp'));
    if (str_contains($label, 'laptop')) return project_url('images/' . rawurlencode('Asus TUF Gaming A15.jpg'));
    if (str_contains($label, 'tai nghe') || str_contains($label, 'âm thanh')) return project_url('images/' . rawurlencode('airpods-pro-3.webp'));
    if (str_contains($label, 'phụ kiện') || str_contains($label, 'sạc')) return project_url('images/' . rawurlencode('sạc dự phòng anker 335.png'));
    return project_url('images/fallback.svg');
}
function setting(string $key, string $default = ''): string
{
    // Public recipient details only, never passwords or API credentials.
    // Admin-configured non-empty values take precedence over these defaults.
    $bankDefaults = [
        'bank_id' => '970422',       // MB Bank (Napas BIN)
        'bank_account' => '0973258529',
    ];
    if ($default === '' && isset($bankDefaults[$key])) $default = $bankDefaults[$key];
    $result = row('SELECT setting_value FROM tbl_settings WHERE setting_key=?', [$key]);
    $stored = (string)($result['setting_value'] ?? '');
    return $stored !== '' ? $stored : $default;
}
function qr_link(array $order): ?string
{
    $bank = setting('bank_id');
    $account = setting('bank_account');
    if (!preg_match('/^[A-Za-z0-9]{2,15}$/', $bank) || !preg_match('/^[0-9]{6,24}$/', $account)) return null;
    $params = [
        'amount' => (int)$order['total_amount'],
        'addInfo' => 'BANHANG ' . $order['order_code'],
    ];
    // VietQR accepts accountName as optional; never invent the beneficiary name.
    $accountName = trim(setting('bank_name'));
    if ($accountName !== '') $params['accountName'] = $accountName;
    $args = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    return 'https://img.vietqr.io/image/' . rawurlencode($bank) . '-' . rawurlencode($account) . '-compact2.png?' . $args;
}
function selected(string $actual, string $target): string
{
    return $actual === $target ? ' selected' : '';
}
function page_head(string $title, bool $admin = false): void
{
    $full = h($title . ' | ' . setting('store_name', 'BanHang Tech Store'));
    echo '<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . $full . '</title><meta name="description" content="BanHang — công nghệ cho cuộc sống hiện đại.">';
    echo '<link rel="stylesheet" href="' . h(asset_url('css/style.css')) . '"><script src="' . h(asset_url('js/app.js')) . '" defer></script></head><body>';
    if ($admin) return;
    $user = current_user();
    $count = array_sum(array_map('intval', $_SESSION['cart'] ?? []));
    echo '<div class="utility"><div class="container utility__inner"><span>BANHANG / TECH & LIFESTYLE</span><span>Công nghệ chọn lọc · Trải nghiệm tốt hơn mỗi ngày</span><span>Hỗ trợ khách hàng</span></div></div>';
    echo '<header class="header"><div class="container header__main"><a class="brand" href="index.php"><span class="brand__mark">B</span><span>BANHANG<small>TECH STORE</small></span></a>';
    echo '<form class="search" method="get" action="index.php"><input type="hidden" name="page" value="products"><span class="search__icon">⌕</span><input name="q" maxlength="120" placeholder="Tìm điện thoại, laptop hay phụ kiện?" value="' . h($_GET['q'] ?? '') . '"><button type="submit">Tìm kiếm</button></form>';
    echo '<div class="header__links"><a href="' . link_to($user ? 'account' : 'login') . '">♙ <span>' . ($user ? h($user['tenkhachhang']) : 'Tài khoản') . '</span></a>';
    echo '<a href="' . link_to('cart') . '">♧ <span>Giỏ hàng (' . $count . ')</span></a>';
    echo '<button class="mobile-toggle" type="button" aria-label="Mở menu" data-menu-toggle>☰</button></div></div>';
    echo '<nav class="nav container" id="mobile-nav"><a href="' . link_to('products') . '">Tất cả sản phẩm</a><a href="' . link_to('products', ['category' => 'phone']) . '">Điện thoại</a>';
    echo '<a href="' . link_to('products', ['category' => 'laptop']) . '">Laptop</a><a href="' . link_to('products', ['category' => 'audio']) . '">Âm thanh</a>';
    echo '<a href="' . link_to('products', ['category' => 'watch']) . '">Đồng hồ</a><a href="' . link_to('products', ['category' => 'accessories']) . '">Phụ kiện</a>';
    echo '<a href="' . link_to('news') . '">Tin công nghệ</a><a href="' . link_to('orders') . '">Đơn hàng</a><a href="' . admin_to('dashboard') . '">Quản trị</a></nav></header>';
}
function page_foot(bool $admin = false): void
{
    if (!$admin) {
        echo '<footer class="footer"><div class="container footer__grid"><section><h2>BANHANG.</h2><p>Công nghệ dành cho cuộc sống hiện đại.</p></section><section><h3>Mua sắm</h3><a href="' . link_to('products') . '">Sản phẩm</a><a href="' . link_to('cart') . '">Giỏ hàng</a></section><section><h3>Hỗ trợ</h3><a href="' . link_to('orders') . '">Đơn hàng</a><a href="' . link_to('news') . '">Tin tức</a></section><section><h3>Thông tin</h3><p>Trải nghiệm công nghệ hiện đại.</p></section></div></footer>';
    }
    echo '</body></html>';
}
function product_card(array $product): void
{
    $id = (int)$product['id_sanpham'];
    $variant = is_phone_product($product) ? '128GB' : 'BASE';
    $price = null;
    try { $price = variant_price($product, $variant); } catch (DomainException $e) {}
    echo '<article class="product-card"><a class="product-card__image" href="' . link_to('product', ['id' => $id]) . '"><img loading="lazy" decoding="async" src="' . h(safe_image($product['hinhanh'])) . '" data-image-fallback="' . h(backup_image((string)($product['tendanhmuc']??''))) . '" alt="' . h($product['tensanpham']) . '"></a>';
    echo '<p class="product-card__cat">' . h($product['tendanhmuc'] ?? 'Công nghệ') . '</p><h3><a href="' . link_to('product', ['id' => $id]) . '">' . h($product['tensanpham']) . '</a></h3>';
    echo '<p class="rating">★ 4.9 <span>· Sản phẩm chính hãng</span></p><div class="product-card__bottom">';
    echo '<strong>' . ($price === null ? 'Chưa có giá' : money($price)) . '</strong>';
    if ($price !== null && (int)$product['soluong'] > 0) {
        echo '<form action="' . h(project_url('index.php')) . '" method="post">' . csrf_field() . '<input type="hidden" name="action" value="add_cart"><input type="hidden" name="product_id" value="' . $id . '"><input type="hidden" name="variant" value="' . h($variant) . '"><input type="hidden" name="qty" value="1">';
        echo '<button type="submit" class="icon-button" aria-label="Thêm vào giỏ">＋</button></form>';
    }
    echo '</div></article>';
}
function form_error(?string $error): void
{
    if ($error !== null) echo '<div class="alert danger">' . h($error) . '</div>';
}
function as_int(mixed $value, int $min = 1, int $max = 1000): int
{
    $number = filter_var($value, FILTER_VALIDATE_INT);
    if ($number === false || $number < $min || $number > $max) throw new DomainException('Giá trị không hợp lệ.');
    return (int)$number;
}
