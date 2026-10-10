<?php
declare(strict_types=1);
function request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}
global $config;
$config = require dirname(__DIR__) . '/config/config.php';
$debug = !empty($config['debug']);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.gc_maxlifetime', '43200');
    ini_set('session.use_strict_mode', '1');
    session_name('pa_session');
    session_set_cookie_params([
        'lifetime' => 12 * 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => request_is_https(),
    ]);
    session_start();
}
$siteUrl = (string) ($config['site_url'] ?? '');
$siteHost = (string) parse_url($siteUrl, PHP_URL_HOST);
$requestHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
if ($siteHost !== '' && strcasecmp($requestHost, $siteHost) === 0 && str_starts_with($siteUrl, 'https://') && !request_is_https()) {
    header('Location: https://' . $requestHost . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
    exit;
}
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; font-src 'self'; script-src 'self'; form-action 'self'; base-uri 'self'; frame-ancestors 'self'");
if (request_is_https()) {
    header('Strict-Transport-Security: max-age=15552000');
}
$robotPath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$robotScript = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
if (str_starts_with($robotPath, '/admin') || str_contains($robotScript, '/admin/')) {
    header('X-Robots-Tag: noindex, nofollow');
}
if (!empty($_SESSION['user_id'])) {
    $seen = (int) ($_SESSION['seen'] ?? 0);
    $idle = (($_SESSION['role'] ?? '') === 'admin') ? 2 * 3600 : 12 * 3600;
    if ($seen > 0 && time() - $seen > $idle) {
        $_SESSION = [];
        session_regenerate_id(true);
    } else {
        $_SESSION['seen'] = time();
    }
}
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
function normalize_phone(string $raw): string
{
    $digits = preg_replace('/\D+/', '', $raw) ?? '';
    if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
        $digits = substr($digits, 2);
    }
    if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
        $digits = substr($digits, 1);
    }
    if (!preg_match('/^[6-9][0-9]{9}$/', $digits)) {
        throw new RuntimeException('Use a 10-digit Indian mobile number.');
    }
    return $digits;
}
function phone_e164(string $raw): string
{
    return '+91' . normalize_phone($raw);
}
function twilio_configured(): bool
{
    global $config;
    return ($config['twilio_sid'] ?? '') !== ''
        && ($config['twilio_token'] ?? '') !== ''
        && ($config['twilio_verify'] ?? '') !== '';
}
function audit_log(int $adminId, string $action, string $entity, int $entityId, ?string $before, ?string $after): void
{
    db()->prepare('INSERT INTO admin_audit_log (admin_user_id, action, entity, entity_id, before_text, after_text, ip) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$adminId, $action, $entity, $entityId, $before, $after, client_ip()]);
}
function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 64);
}
function too_many_attempts(string $identifier): bool
{
    db()->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 2 DAY)');
    $ip = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)');
    $ip->execute([client_ip()]);
    $who = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND identifier <> "" AND attempted_at > (NOW() - INTERVAL 15 MINUTE)');
    $who->execute([$identifier]);
    return (int) $ip->fetchColumn() >= 20 || (int) $who->fetchColumn() >= 8;
}
function note_login_failure(string $identifier = ''): void
{
    db()->prepare('INSERT INTO login_attempts (ip, identifier) VALUES (?, ?)')->execute([client_ip(), substr($identifier, 0, 160)]);
}
function safe_error(Throwable $err, string $fallback): string
{
    if ($err instanceof PDOException) {
        error_log($err->getMessage());
        return $fallback;
    }
    if ($err instanceof RuntimeException) {
        return $err->getMessage();
    }
    error_log($err::class . ' ' . $err->getMessage());
    return $fallback;
}
function inr(int $amount): string
{
    return '₹' . number_format($amount);
}
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    global $config;
    if (($config['db_user'] ?? '') === '' || ($config['db_name'] ?? '') === '') {
        throw new RuntimeException('Database is not configured.');
    }
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $config['db_host'],
        (int) $config['db_port'],
        $config['db_name']
    );
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
function setting(string $key, string $fallback = ''): string
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (db()->query('SELECT `key`, `value` FROM settings') as $row) {
            $map[$row['key']] = (string) $row['value'];
        }
    }
    return $map[$key] ?? $fallback;
}
function current_user(): ?array
{
    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, name, email, phone, role, phone_verified_at, status, password_changed_at FROM users WHERE id = ?');
    $stmt->execute([(int) $id]);
    $user = $stmt->fetch();
    if (!$user || ($user['status'] ?? '') === 'disabled') {
        return null;
    }
    $stamp = (string) ($user['password_changed_at'] ?? '');
    $known = (string) ($_SESSION['pwd_stamp'] ?? '');
    if (!hash_equals($stamp, $known)) {
        unset($_SESSION['user_id']);
        return null;
    }
    return $user;
}
function safe_next(?string $next, string $fallback = '/account'): string
{
    $next = trim((string) $next);
    if ($next === '') {
        return $fallback;
    }
    $decoded = rawurldecode($next);
    foreach ([$next, $decoded] as $candidate) {
        if ($candidate === '' || $candidate[0] !== '/' || str_starts_with($candidate, '//') || str_starts_with($candidate, '/\\')) {
            return $fallback;
        }
        if (str_contains($candidate, '://') || str_contains($candidate, '\\') || preg_match('/[\r\n]/', $candidate)) {
            return $fallback;
        }
    }
    return $next;
}
function require_user(): array
{
    $user = current_user();
    if (!$user) {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/account');
        $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '/account');
        $query = (string) (parse_url($uri, PHP_URL_QUERY) ?: '');
        $next = $path . ($query !== '' ? '?' . $query : '');
        header('Location: /login?next=' . rawurlencode(safe_next($next)));
        exit;
    }
    return $user;
}
function require_admin(): array
{
    $user = current_user();
    if (!$user || $user['role'] !== 'admin') {
        header('Location: /admin/login');
        exit;
    }
    return $user;
}
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
function mask_phone(string $phone): string
{
    if (strlen($phone) < 4) {
        return $phone;
    }
    return substr($phone, 0, 2) . 'XXXXXX' . substr($phone, -2);
}
function rotate_csrf(): void
{
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function require_csrf(): void
{
    $sent = (string) ($_POST['csrf'] ?? '');
    $known = (string) ($_SESSION['csrf'] ?? '');
    if ($sent === '' || !hash_equals($known, $sent)) {
        throw new RuntimeException('The form expired. Reload the page and try again.');
    }
}
function sign_in_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['role'] = (string) ($user['role'] ?? 'customer');
    $_SESSION['seen'] = time();
    $_SESSION['pwd_stamp'] = (string) ($user['password_changed_at'] ?? '');
    rotate_csrf();
    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int) $user['id']]);
}
function login_destination(): string
{
    $dest = safe_next((string) ($_SESSION['login_next'] ?? ''), '/account');
    unset(
        $_SESSION['login_next'],
        $_SESSION['otp_id'],
        $_SESSION['otp_phone'],
        $_SESSION['otp_purpose'],
        $_SESSION['otp_decoy'],
        $_SESSION['otp_debug'],
        $_SESSION['otp_sent_at'],
        $_SESSION['otp_verified_phone']
    );
    return $dest;
}
function tray_qty(int $qty): int
{
    if ($qty < 1 || $qty > 50) {
        throw new RuntimeException('Choose between 1 and 50 trays.');
    }
    return $qty;
}
function checkout_token(): string
{
    if (empty($_SESSION['checkout_token'])) {
        $_SESSION['checkout_token'] = bin2hex(random_bytes(16));
    }
    return (string) $_SESSION['checkout_token'];
}
function take_checkout_token(string $sent): void
{
    $known = (string) ($_SESSION['checkout_token'] ?? '');
    unset($_SESSION['checkout_token']);
    if ($known === '' || $sent === '' || !hash_equals($known, $sent)) {
        throw new RuntimeException('This checkout was already submitted. Look at your orders before placing it again.');
    }
}
function lookup_is_limited(): bool
{
    db()->exec('DELETE FROM lookup_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
    $stmt = db()->prepare('SELECT COUNT(*) FROM lookup_attempts WHERE ip = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)');
    $stmt->execute([client_ip()]);
    return (int) $stmt->fetchColumn() >= 20;
}
function note_lookup(): void
{
    db()->prepare('INSERT INTO lookup_attempts (ip) VALUES (?)')->execute([client_ip()]);
}
function cart(): array
{
    $cart = $_SESSION['cart'] ?? [];
    return is_array($cart) ? $cart : [];
}
function cart_count(): int
{
    return array_sum(array_map('intval', cart()));
}
function available_trays(array $product): int
{
    return max(0, (int) $product['stock_qty'] - (int) $product['reserved_qty']);
}
function product_by_slug(string $slug): ?array
{
    $stmt = db()->prepare(
        'SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM products p JOIN categories c ON c.id = p.category_id
         WHERE p.slug = ? AND p.active = 1'
    );
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}
function quote_cart(string $couponCode = ''): array
{
    $lines = [];
    $subtotal = 0;
    foreach (cart() as $slug => $qty) {
        $product = product_by_slug((string) $slug);
        $qty = (int) $qty;
        if (!$product || $qty < 1) {
            continue;
        }
        $line = (int) $product['price_inr'] * $qty;
        $subtotal += $line;
        $lines[] = ['product' => $product, 'qty' => $qty, 'line' => $line];
    }
    $discount = 0;
    $coupon = null;
    $code = strtoupper(trim($couponCode));
    if ($code !== '') {
        $stmt = db()->prepare('SELECT * FROM coupons WHERE code = ? AND active = 1 LIMIT 1');
        $stmt->execute([$code]);
        $coupon = $stmt->fetch() ?: null;
        if (!$coupon) {
            throw new RuntimeException('This coupon is not valid.');
        }
        if ($subtotal < (int) $coupon['min_order_inr']) {
            throw new RuntimeException('This coupon needs a larger order.');
        }
        if ($coupon['usage_limit'] !== null) {
            $used = db()->prepare('SELECT COUNT(*) FROM coupon_redemptions WHERE coupon_id = ?');
            $used->execute([(int) $coupon['id']]);
            if ((int) $used->fetchColumn() >= (int) $coupon['usage_limit']) {
                throw new RuntimeException('This coupon has reached its limit.');
            }
        }
        $discount = $coupon['kind'] === 'percent'
            ? (int) round($subtotal * ((int) $coupon['amount']) / 100)
            : (int) $coupon['amount'];
        $discount = max(0, min($discount, $subtotal));
    }
    $shipFlat = (int) setting('shipping_flat_inr', '180');
    $freeOver = (int) setting('free_shipping_over_inr', '4000');
    $after = $subtotal - $discount;
    $shipping = ($after >= $freeOver || $after === 0) ? 0 : $shipFlat;
    return [
        'lines' => $lines,
        'subtotal' => $subtotal,
        'discount' => $discount,
        'shipping' => $shipping,
        'total' => $after + $shipping,
        'coupon' => $coupon,
    ];
}
function lock_coupon(PDO $pdo, string $code, int $subtotal): ?array
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM coupons WHERE code = ? AND active = 1 FOR UPDATE');
    $stmt->execute([$code]);
    $coupon = $stmt->fetch();
    if (!$coupon) {
        throw new RuntimeException('This coupon is not valid.');
    }
    if ($subtotal < (int) $coupon['min_order_inr']) {
        throw new RuntimeException('This coupon needs a larger order.');
    }
    if ($coupon['usage_limit'] !== null) {
        $used = $pdo->prepare('SELECT COUNT(*) FROM coupon_redemptions WHERE coupon_id = ?');
        $used->execute([(int) $coupon['id']]);
        if ((int) $used->fetchColumn() >= (int) $coupon['usage_limit']) {
            throw new RuntimeException('This coupon has reached its limit.');
        }
    }
    $discount = $coupon['kind'] === 'percent'
        ? (int) round($subtotal * ((int) $coupon['amount']) / 100)
        : (int) $coupon['amount'];
    $coupon['discount'] = max(0, min($discount, $subtotal));
    return $coupon;
}
function apply_order_status(PDO $pdo, int $id, string $status, int $adminId): void
{
    $allowed = [
        'placed' => ['payment_pending', 'payment_confirmed', 'preparing', 'cancelled'],
        'payment_pending' => ['payment_confirmed', 'cancelled'],
        'payment_confirmed' => ['preparing', 'cancelled'],
        'preparing' => ['dispatched', 'cancelled'],
        'dispatched' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
    ];
    $order = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
    $order->execute([$id]);
    $row = $order->fetch();
    if (!$row) {
        throw new RuntimeException('Order not found.');
    }
    $from = (string) $row['status'];
    if (!in_array($status, $allowed[$from] ?? [], true)) {
        throw new RuntimeException('That order status change is not allowed.');
    }
    if ($status === 'cancelled') {
        if ($row['inventory_state'] === 'sold') {
            throw new RuntimeException('A sold order cannot be cancelled here.');
        }
        if ($row['inventory_state'] === 'reserved') {
            $pdo->prepare('UPDATE products p JOIN order_items oi ON oi.product_id = p.id SET p.reserved_qty = GREATEST(0, p.reserved_qty - oi.quantity) WHERE oi.order_id = ?')->execute([$id]);
            $pdo->prepare("UPDATE orders SET inventory_state = 'released', status = 'cancelled' WHERE id = ?")->execute([$id]);
        } else {
            $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$id]);
        }
    } elseif ($status === 'payment_confirmed' && $row['inventory_state'] === 'reserved') {
        $pdo->prepare('UPDATE products p JOIN order_items oi ON oi.product_id = p.id SET p.reserved_qty = GREATEST(0, p.reserved_qty - oi.quantity), p.stock_qty = GREATEST(0, p.stock_qty - oi.quantity) WHERE oi.order_id = ?')->execute([$id]);
        $pdo->prepare("UPDATE orders SET inventory_state = 'sold', status = 'payment_confirmed', payment_status = 'paid' WHERE id = ?")->execute([$id]);
    } else {
        $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$status, $id]);
    }
    $pdo->prepare('INSERT INTO order_events (order_id, status, note) VALUES (?, ?, ?)')->execute([$id, $status, 'Nursery updated the order.']);
    audit_log($adminId, 'order_status', 'order', $id, $from, $status);
}
function adjust_stock(PDO $pdo, int $productId, int $change, string $reason, int $adminId): void
{
    if (strlen($reason) < 3) {
        throw new RuntimeException('Write a reason for the stock change.');
    }
    $stmt = $pdo->prepare('SELECT stock_qty, reserved_qty FROM products WHERE id = ? FOR UPDATE');
    $stmt->execute([$productId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Product not found.');
    }
    $before = (int) $row['stock_qty'];
    $after = $before + $change;
    if ($after < (int) $row['reserved_qty'] || $after < 0) {
        throw new RuntimeException('Stock cannot fall below the trays already reserved.');
    }
    $pdo->prepare('UPDATE products SET stock_qty = ? WHERE id = ?')->execute([$after, $productId]);
    $pdo->prepare('INSERT INTO inventory_movements (product_id, admin_user_id, change_qty, stock_before, stock_after, reason) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$productId, $adminId, $change, $before, $after, $reason]);
    audit_log($adminId, 'stock', 'product', $productId, (string) $before, (string) $after);
}
require dirname(__DIR__) . '/includes/otp.php';
require dirname(__DIR__) . '/includes/layout.php';
