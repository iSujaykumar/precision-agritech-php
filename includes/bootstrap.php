<?php
declare(strict_types=1);
if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    echo 'This shop needs PHP 8.1 or newer.';
    exit;
}
$config = require dirname(__DIR__) . '/config/config.php';
if (empty($config['debug'])) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
} else {
    ini_set('display_errors', '1');
}
date_default_timezone_set('Asia/Kolkata');
function request_is_https(): bool
{
    global $config;
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (!empty($config['trust_proxy']) && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        return true;
    }
    return false;
}
function ensure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $lifetime = 7 * 86400;
    ini_set('session.gc_maxlifetime', (string) $lifetime);
    $save = dirname(__DIR__) . '/storage/sessions';
    if (!is_dir($save)) {
        @mkdir($save, 0700, true);
    }
    if (is_dir($save) && is_writable($save)) {
        ini_set('session.save_path', $save);
    }
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => request_is_https(),
    ]);
    session_start();
    if (!empty($_SESSION['user_id'])) {
        $seen = (int) ($_SESSION['seen'] ?? 0);
        if ($seen > 0 && time() - $seen > 12 * 3600) {
            $_SESSION = [];
            session_regenerate_id(true);
        } else {
            $_SESSION['seen'] = time();
        }
    }
}
$uriPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$needsSession = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    || isset($_COOKIE[session_name()])
    || (bool) preg_match('#^/(cart|checkout|account|login|register|logout|setup|admin|verify|change-password|forgot|reset|track|contact|order)(/|$)#', $uriPath);
if ($needsSession) {
    ensure_session();
}
$siteUrl = (string) ($config['site_url'] ?? '');
$siteHost = strtolower((string) parse_url($siteUrl, PHP_URL_HOST));
$requestHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
if ($siteHost !== '' && str_starts_with($siteUrl, 'https://')) {
    $canonical = $siteHost;
    if ($requestHost === 'www.' . $siteHost || ($requestHost === $siteHost && !request_is_https())) {
        header('Location: https://' . $canonical . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
        exit;
    }
}
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
$scriptSrc = "'self'";
if (($config['razorpay_key'] ?? '') !== '') {
    $scriptSrc .= ' https://checkout.razorpay.com';
}
if (($config['turnstile_site_key'] ?? '') !== '') {
    $scriptSrc .= ' https://challenges.cloudflare.com';
}
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; font-src 'self'; script-src " . $scriptSrc . "; connect-src 'self' https://api.razorpay.com https://challenges.cloudflare.com; form-action 'self'; base-uri 'self'; frame-ancestors 'self'; frame-src https://challenges.cloudflare.com");
if (request_is_https()) {
    header('Strict-Transport-Security: max-age=15552000');
}
if (session_status() !== PHP_SESSION_ACTIVE && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    header('Cache-Control: public, max-age=300');
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
    maybe_cleanup();
    $ip = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND identifier = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)');
    $ip->execute([client_ip(), $identifier]);
    $spray = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)');
    $spray->execute([client_ip()]);
    return (int) $ip->fetchColumn() >= 8 || (int) $spray->fetchColumn() >= 20;
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
    $pdo->exec("SET time_zone = '+05:30'");
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
    global $paUserCache, $paUser;
    if ($paUserCache) {
        return $paUser;
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        if (!isset($_COOKIE[session_name()])) {
            return null;
        }
        ensure_session();
    }
    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        $paUserCache = true;
        $paUser = null;
        return null;
    }
    $stmt = db()->prepare('SELECT id, name, email, phone, role, phone_verified_at, email_verified_at, status, password_changed_at, last_login_at FROM users WHERE id = ?');
    $stmt->execute([(int) $id]);
    $found = $stmt->fetch() ?: null;
    if (!$found || ($found['status'] ?? '') === 'disabled') {
        $paUserCache = true;
        $paUser = null;
        return null;
    }
    $stamp = (string) ($found['password_changed_at'] ?? '');
    $known = (string) ($_SESSION['pwd_stamp'] ?? '');
    if (!hash_equals($stamp, $known)) {
        unset($_SESSION['user_id']);
        $paUserCache = true;
        $paUser = null;
        return null;
    }
    $paUserCache = true;
    $paUser = $found;
    return $paUser;
}
function forget_current_user(): void
{
    global $paUserCache, $paUser;
    $paUserCache = false;
    $paUser = null;
}
function require_user(): array
{
    $user = current_user();
    if (!$user) {
        header('Location: /login');
        exit;
    }
    return $user;
}
function require_admin(): array
{
    $user = require_user();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        exit('The nursery desk is not open for this account.');
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
        throw new RuntimeException('Your session expired. Reload the page and try again.');
    }
}
function sign_in_user(array $user): void
{
    ensure_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['seen'] = time();
    $_SESSION['pwd_stamp'] = (string) ($user['password_changed_at'] ?? '');
    forget_current_user();
    rotate_csrf();
    $params = session_get_cookie_params();
    setcookie(session_name(), session_id(), [
        'expires' => time() + 30 * 86400,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => (bool) $params['secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int) $user['id']]);
    merge_saved_cart((int) $user['id']);
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
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return [];
    }
    $cart = $_SESSION['cart'] ?? [];
    return is_array($cart) ? $cart : [];
}
function remember_cart_line(string $slug, int $qty): void
{
    ensure_session();
    if ($qty < 1) {
        unset($_SESSION['cart'][$slug]);
    } else {
        $_SESSION['cart'][$slug] = $qty;
    }
    $user = current_user();
    if (!$user) {
        return;
    }
    if ($qty < 1) {
        db()->prepare('DELETE FROM carts WHERE user_id = ? AND slug = ?')->execute([(int) $user['id'], $slug]);
        return;
    }
    db()->prepare('INSERT INTO carts (user_id, slug, qty) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE qty = VALUES(qty), updated_at = CURRENT_TIMESTAMP')
        ->execute([(int) $user['id'], $slug, $qty]);
}
function merge_saved_cart(int $userId): void
{
    $saved = db()->prepare('SELECT slug, qty FROM carts WHERE user_id = ?');
    $saved->execute([$userId]);
    foreach ($saved->fetchAll() as $row) {
        $slug = (string) $row['slug'];
        $qty = (int) $row['qty'];
        $have = (int) ($_SESSION['cart'][$slug] ?? 0);
        $_SESSION['cart'][$slug] = min(50, max($have, $qty));
    }
    db()->prepare('DELETE FROM carts WHERE user_id = ?')->execute([$userId]);
    foreach (cart() as $slug => $qty) {
        db()->prepare('INSERT INTO carts (user_id, slug, qty) VALUES (?, ?, ?)')->execute([$userId, (string) $slug, (int) $qty]);
    }
}
function cart_count(): int
{
    $count = 0;
    foreach (cart() as $qty) {
        $qty = (int) $qty;
        if ($qty >= 1 && $qty <= 50) {
            $count += $qty;
        }
    }
    return $count;
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
require dirname(__DIR__) . '/includes/commerce.php';
require dirname(__DIR__) . '/includes/mail.php';
require dirname(__DIR__) . '/includes/layout.php';
set_exception_handler(static function (Throwable $err): void {
    error_log($err::class . ' ' . $err->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
    }
    $message = $err instanceof RuntimeException ? $err->getMessage() : 'Something went wrong. Please try again, or call 9011975959.';
    if (function_exists('render_header')) {
        try {
            render_header('Something went wrong | Precision Agritech');
            echo '<section class="section"><div class="wrap narrow"><h1>Something went wrong</h1><p class="flash error" role="alert">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p><p><a href="/">Back to the shop</a></p></div></section>';
            render_footer();
            return;
        } catch (Throwable $inner) {
            error_log($inner->getMessage());
        }
    }
    echo '<!doctype html><meta charset="utf-8"><title>Something went wrong</title><h1>Something went wrong</h1><p>Please try again, or call 9011975959.</p>';
});
