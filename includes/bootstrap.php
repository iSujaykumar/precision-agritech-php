<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Kolkata');
function request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}
function send_security_headers(?string $scriptHash = null): void
{
    global $config;
    $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $admin = str_starts_with($path, '/admin');
    $script = "'self'";
    $frame = "'none'";
    if ($scriptHash !== null && $scriptHash !== '') {
        $script .= " 'sha256-$scriptHash'";
    }
    if (($config['turnstile_site_key'] ?? '') !== '') {
        $script .= ' https://challenges.cloudflare.com';
        $frame = 'https://challenges.cloudflare.com';
    }
    $ancestors = $admin ? "'none'" : "'self'";
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; font-src 'self'; script-src $script; frame-src $frame; form-action 'self'; base-uri 'self'; frame-ancestors $ancestors");
    header('X-Frame-Options: ' . ($admin ? 'DENY' : 'SAMEORIGIN'));
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
header('Cross-Origin-Opener-Policy: same-origin');
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
send_security_headers();
if (request_is_https()) {
    header('Strict-Transport-Security: max-age=31536000');
}
$robotPath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$robotScript = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
if (str_starts_with($robotPath, '/admin') || str_contains($robotScript, '/admin/')) {
    header('X-Robots-Tag: noindex, nofollow');
}
if (!empty($_SESSION['user_id'])) {
    $seen = (int) ($_SESSION['seen'] ?? 0);
    $idle = (($_SESSION['role'] ?? '') === 'admin') ? 2 * 3600 : 12 * 3600;
    $started = (int) ($_SESSION['admin_started'] ?? 0);
    $absolute = (($_SESSION['role'] ?? '') === 'admin') && $started > 0 && time() - $started > 8 * 3600;
    if ($seen > 0 && (time() - $seen > $idle || $absolute)) {
        $wasAdmin = (($_SESSION['role'] ?? '') === 'admin');
        $_SESSION = [];
        session_regenerate_id(true);
        if ($wasAdmin) {
            $_SESSION['login_notice'] = 'Session ended, please sign in again';
        }
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
        error_log('Database is not configured.');
        throw new RuntimeException('The shop is not ready yet. Please call 8080370152.');
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
    if (function_exists('housekeeping_tick')) {
        housekeeping_tick();
    }
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
    $stmt = db()->prepare('SELECT id, name, email, phone, role, desk_role, farmer, phone_verified_at, email_verified_at, google_id, status, password_changed_at, must_change_password FROM users WHERE id = ?');
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
    $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    if (!empty($user['must_change_password']) && !str_contains($path, 'change-password')) {
        header('Location: /change-password');
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
    if (($user['role'] ?? '') === 'admin') {
        $_SESSION['admin_started'] = time();
    }
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
function shop_timezone(): DateTimeZone
{
    return new DateTimeZone('Asia/Kolkata');
}
function offer_moment(?string $value): ?DateTimeImmutable
{
    $value = trim(str_replace('T', ' ', (string) $value));
    if ($value === '') {
        return null;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
        $value .= ':00';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
        return null;
    }
    $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, shop_timezone());
    $errors = DateTimeImmutable::getLastErrors() ?: ['warning_count' => 0, 'error_count' => 0];
    if (!$parsed || ($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0) {
        return null;
    }
    if ($parsed->format('Y-m-d H:i:s') !== $value) {
        return null;
    }
    return $parsed;
}
function offer_input_datetime(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    $parsed = offer_moment($raw);
    if ($parsed === null) {
        throw new RuntimeException('Offer dates must be a real date and time, or left blank.');
    }
    return $parsed->format('Y-m-d H:i:s');
}
function offer_window_open(array $product, ?DateTimeImmutable $now = null): bool
{
    $now = $now ?? new DateTimeImmutable('now', shop_timezone());
    foreach (['offer_starts_at' => false, 'offer_ends_at' => true] as $key => $isEnd) {
        $raw = trim((string) ($product[$key] ?? ''));
        if ($raw === '') {
            continue;
        }
        $at = offer_moment($raw);
        if ($at === null) {
            return false;
        }
        if (!$isEnd && $now < $at) {
            return false;
        }
        if ($isEnd && $now > $at) {
            return false;
        }
    }
    return true;
}
function offer_compare_price(array $product): ?int
{
    if (!array_key_exists('compare_at_inr', $product) || $product['compare_at_inr'] === null || $product['compare_at_inr'] === '') {
        return null;
    }
    $compare = (int) $product['compare_at_inr'];
    if ($compare > (int) ($product['price_inr'] ?? 0)) {
        return $compare;
    }
    return null;
}
function offer_is_listed(array $product, ?DateTimeImmutable $now = null): bool
{
    if (!offer_window_open($product, $now)) {
        return false;
    }
    return (int) ($product['on_offer'] ?? 0) === 1 || offer_compare_price($product) !== null;
}
function offer_badge(array $product, ?DateTimeImmutable $now = null): string
{
    if (!offer_is_listed($product, $now)) {
        return '';
    }
    return offer_compare_price($product) !== null ? 'Sale' : 'Offer';
}
function product_is_new(array $product): bool
{
    return (int) ($product['active'] ?? 0) === 1 && (int) ($product['is_new'] ?? 0) === 1;
}
function catalog_public_sql(): string
{
    $sql = 'p.active = 1 AND c.active = 1';
    if (!customer_is_farmer()) {
        $sql .= ' AND p.farmer_only = 0';
    }
    return $sql;
}
function offer_active_sql(): string
{
    return '(p.on_offer = 1 OR (p.compare_at_inr IS NOT NULL AND p.compare_at_inr > p.price_inr))'
        . ' AND (p.offer_starts_at IS NULL OR p.offer_starts_at <= NOW())'
        . ' AND (p.offer_ends_at IS NULL OR p.offer_ends_at >= NOW())';
}
function home_products(string $section): array
{
    // Homepage rails: active product, active category, and free stock.
    // The shop still lists an out-of-stock tray so the card can say so.
    $limit = (int) setting('rail_limit', '8');
    if ($limit < 4 || $limit > 24) {
        $limit = 8;
    }
    $base = 'SELECT p.*, c.name AS category_name FROM products p
        JOIN categories c ON c.id = p.category_id
        WHERE ' . catalog_card_sql() . ' AND p.stock_qty > p.reserved_qty';
    if ($section === 'bestsellers') {
        $sql = $base . ' AND p.bestseller = 1 ORDER BY p.name, p.id LIMIT ' . $limit;
    } elseif ($section === 'new') {
        $sql = $base . ' AND p.is_new = 1 ORDER BY p.name, p.id LIMIT ' . $limit;
    } elseif ($section === 'offers') {
        $sql = $base . ' AND ' . offer_active_sql() . ' ORDER BY p.name, p.id LIMIT ' . $limit;
    } else {
        throw new InvalidArgumentException('Unknown home section.');
    }
    return db()->query($sql)->fetchAll();
}
function public_product_slugs(): array
{
    return db()->query(
        'SELECT p.slug FROM products p
         JOIN categories c ON c.id = p.category_id
         WHERE ' . catalog_card_sql() . '
         ORDER BY p.slug'
    )->fetchAll(PDO::FETCH_COLUMN);
}
function lock_listed_product(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT p.* FROM products p
         JOIN categories c ON c.id = p.category_id
         WHERE p.id = ? AND p.active = 1 AND c.active = 1 AND (p.farmer_only = 0 OR ? = 1)
         FOR UPDATE'
    );
    $stmt->execute([$id, can_see_farmer() ? 1 : 0]);
    $row = $stmt->fetch();
    return $row ?: null;
}
function product_by_slug(string $slug): ?array
{
    $stmt = db()->prepare(
        'SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM products p JOIN categories c ON c.id = p.category_id
         WHERE p.slug = ? AND p.active = 1 AND c.active = 1 AND (p.farmer_only = 0 OR ? = 1)'
    );
    $stmt->execute([$slug, can_see_farmer() ? 1 : 0]);
    $row = $stmt->fetch();
    return $row ?: null;
}
function quote_cart(string $couponCode = ''): array
{
    $lines = [];
    $subtotal = 0;
    $blocked = false;
    foreach (cart() as $slug => $qty) {
        $qty = (int) $qty;
        $stmt = db()->prepare('SELECT p.*, c.name AS category_name, c.active AS category_active FROM products p JOIN categories c ON c.id = p.category_id WHERE p.slug = ?');
        $stmt->execute([(string) $slug]);
        $product = $stmt->fetch() ?: null;
        if (!$product || $qty < 1) {
            $blocked = true;
            $lines[] = [
                'product' => ['slug' => (string) $slug, 'name' => 'Tray', 'image_url' => '', 'variety' => '', 'price_inr' => 0, 'unit_label' => 'tray', 'active' => 0, 'stock_qty' => 0, 'reserved_qty' => 0],
                'qty' => max(1, $qty),
                'line' => 0,
                'warning' => 'No longer available. Remove it from the cart.',
                'ok' => false,
            ];
            continue;
        }
        $free = available_trays($product);
        $warning = null;
        if ((int) $product['active'] !== 1 || (int) ($product['category_active'] ?? 0) !== 1 || $free < 1) {
            $warning = 'No longer available. Remove it from the cart.';
        } elseif ($qty > $free) {
            $warning = 'Only ' . $free . ' left';
        } elseif ($qty > 50) {
            $warning = 'Choose at most 50 trays';
        }
        $sell = selling_price($product);
        $price = (int) $sell['rupees'];
        $unitPaise = (int) ($sell['tray_paise'] ?? ($price * 100));
        if ($warning === null && (!empty($sell['blocked']) || ((int) ($product['farmer_only'] ?? 0) === 1 && !can_see_farmer()))) {
            $warning = 'Price not available, please contact us.';
            $price = 0;
            $unitPaise = 0;
        }
        $linePaise = $warning ? 0 : $unitPaise * $qty;
        $line = $warning ? 0 : intdiv($linePaise + 50, 100);
        if ($warning) {
            $blocked = true;
        } else {
            $subtotal += $line;
        }
        $lines[] = ['product' => $product, 'qty' => $qty, 'line' => $line, 'line_paise' => $linePaise, 'unit' => $price, 'unit_paise' => $unitPaise, 'warning' => $warning, 'ok' => $warning === null, 'free' => $free, 'sell' => $sell];
    }
    $discount = 0;
    $coupon = null;
    $code = strtoupper(trim($couponCode));
    if ($code !== '' && !$blocked && $subtotal > 0) {
        $stmt = db()->prepare('SELECT * FROM coupons WHERE code = ? AND active = 1 LIMIT 1');
        $stmt->execute([$code]);
        $coupon = $stmt->fetch() ?: null;
        if (!$coupon) {
            throw new RuntimeException('This coupon is not valid.');
        }
        assert_coupon_window($coupon);
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
        $coupon['discount'] = $discount;
    }
    $shipFlat = (int) setting('shipping_flat_inr', '180');
    $freeOver = (int) setting('free_shipping_over_inr', '4000');
    $seasonal = seasonal_offer(seasonal_base_amount($lines), customer_is_farmer(), false);
    if ($seasonal['amount'] > 0 && $discount > 0 && setting('seasonal_stacks_coupons', '0') !== '1') {
        if ($seasonal['amount'] > $discount) {
            $discount = 0;
            if ($coupon) {
                $coupon['discount'] = 0;
            }
        } else {
            $seasonal['amount'] = 0;
        }
    }
    $after = max(0, $subtotal - $discount - (int) $seasonal['amount']);
    $shipping = ($after >= $freeOver || $after === 0) ? 0 : $shipFlat;
    $subtotalPaise = 0;
    foreach ($lines as $ln) {
        if (!empty($ln['ok'])) {
            $subtotalPaise += (int) ($ln['line_paise'] ?? 0);
        }
    }
    if ($coupon && ($coupon['kind'] ?? '') === 'percent' && $discount > 0) {
        $discountPaise = round_half_up_percent($subtotalPaise, (int) $coupon['amount']);
    } else {
        $discountPaise = $discount * 100;
    }
    $discountPaise = max(0, min($discountPaise, $subtotalPaise));
    $seasonalPaise = ((int) $seasonal['amount']) * 100;
    if ($seasonalPaise > $subtotalPaise - $discountPaise) {
        $seasonalPaise = max(0, $subtotalPaise - $discountPaise);
    }
    $shippingPaise = $shipping * 100;
    $totalPaise = max(0, $subtotalPaise - $discountPaise - $seasonalPaise) + $shippingPaise;
    return [
        'lines' => $lines,
        'subtotal' => $subtotal,
        'discount' => $discount,
        'seasonal' => $seasonal,
        'shipping' => $shipping,
        'total' => $after + $shipping,
        'subtotal_paise' => $subtotalPaise,
        'discount_paise' => $discountPaise,
        'seasonal_paise' => $seasonalPaise,
        'shipping_paise' => $shippingPaise,
        'total_paise' => $totalPaise,
        'coupon' => $coupon,
        'blocked' => $blocked,
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
    assert_coupon_window($coupon);
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
    $perCustomer = array_key_exists('per_customer_limit', $coupon) ? (int) $coupon['per_customer_limit'] : 1;
    $whoPhone = preg_replace('/\D+/', '', (string) ($GLOBALS['coupon_phone'] ?? '')) ?? '';
    $whoEmail = strtolower(trim((string) ($GLOBALS['coupon_email'] ?? '')));
    if ($perCustomer > 0 && ($whoPhone !== '' || $whoEmail !== '')) {
        $parts = [];
        $args = [(int) $coupon['id']];
        if ($whoPhone !== '') {
            $parts[] = 'phone = ?';
            $args[] = $whoPhone;
        }
        if ($whoEmail !== '') {
            $parts[] = 'customer_email = ?';
            $args[] = $whoEmail;
        }
        $mine = $pdo->prepare('SELECT COUNT(*) FROM coupon_redemptions WHERE coupon_id = ? AND (' . implode(' OR ', $parts) . ')');
        $mine->execute($args);
        if ((int) $mine->fetchColumn() >= $perCustomer) {
            throw new RuntimeException('You have already used this coupon.');
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
        'placed' => ['payment_submitted', 'payment_confirmed', 'confirmed', 'preparing', 'cancelled'],
        'payment_pending' => ['payment_submitted', 'payment_confirmed', 'cancelled'],
        'payment_submitted' => ['payment_confirmed', 'cancelled'],
        'payment_confirmed' => ['confirmed', 'preparing', 'cancelled'],
        'confirmed' => ['packed', 'preparing', 'cancelled'],
        'packed' => ['out_for_delivery', 'dispatched', 'cancelled'],
        'preparing' => ['dispatched', 'out_for_delivery', 'cancelled'],
        'out_for_delivery' => ['delivered', 'delivery_failed'],
        'dispatched' => ['delivered', 'delivery_failed'],
        'delivery_failed' => ['out_for_delivery', 'dispatched', 'cancelled'],
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
    if (in_array($status, ['dispatched', 'out_for_delivery'], true)
        && ($row['payment_method'] ?? '') === 'cod'
        && setting('require_phone_confirm', '1') === '1'
        && (int) ($row['phone_confirmed'] ?? 0) !== 1) {
        throw new RuntimeException('Confirm this order by phone before it goes out.');
    }
    if ($status === 'cancelled') {
        if ($row['inventory_state'] === 'reserved') {
            release_order_hold($pdo, $id);
            $pdo->prepare("UPDATE orders SET inventory_state = 'released', status = 'cancelled' WHERE id = ?")->execute([$id]);
        } elseif ($row['inventory_state'] === 'sold' && $from !== 'delivered') {
            $items = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ? AND product_id IS NOT NULL');
            $items->execute([$id]);
            foreach ($items as $item) {
                $pdo->prepare('UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?')->execute([(int) $item['quantity'], (int) $item['product_id']]);
            }
            $pdo->prepare("UPDATE orders SET inventory_state = 'restocked', status = 'cancelled' WHERE id = ?")->execute([$id]);
        } else {
            $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$id]);
        }
        release_coupon_for_order($pdo, $id);
        if (in_array((string) $row['payment_status'], ['paid', 'partial', 'partially_refunded'], true) || (int) ($row['paid_amount_inr'] ?? 0) > 0) {
            $pdo->prepare("UPDATE orders SET payment_status = 'refund_due' WHERE id = ?")->execute([$id]);
        }
        if (($row['payment_method'] ?? '') === 'cod' && in_array($from, ['out_for_delivery', 'dispatched', 'delivery_failed'], true)) {
            $pdo->prepare("UPDATE orders SET cancel_reason = 'Refused on delivery' WHERE id = ? AND (cancel_reason IS NULL OR cancel_reason = '')")->execute([$id]);
            maybe_cod_block($pdo, (string) $row['customer_phone']);
        }
    } elseif ($status === 'payment_confirmed' && $row['inventory_state'] === 'reserved') {
        $pdo->prepare('UPDATE products p JOIN order_items oi ON oi.product_id = p.id SET p.reserved_qty = GREATEST(0, p.reserved_qty - oi.quantity), p.stock_qty = GREATEST(0, p.stock_qty - oi.quantity) WHERE oi.order_id = ?')->execute([$id]);
        $pdo->prepare("UPDATE orders SET inventory_state = 'sold', status = 'payment_confirmed', payment_status = 'paid' WHERE id = ?")->execute([$id]);
    } elseif (in_array($status, ['dispatched', 'out_for_delivery'], true) && $row['inventory_state'] === 'reserved') {
        $pdo->prepare('UPDATE products p JOIN order_items oi ON oi.product_id = p.id SET p.reserved_qty = GREATEST(0, p.reserved_qty - oi.quantity), p.stock_qty = GREATEST(0, p.stock_qty - oi.quantity) WHERE oi.order_id = ?')->execute([$id]);
        $pdo->prepare('UPDATE orders SET inventory_state = ?, status = ? WHERE id = ?')->execute(['sold', $status, $id]);
    } elseif ($status === 'delivered' && ($row['payment_method'] ?? '') !== 'cod') {
        $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$status, $id]);
    } else {
        $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$status, $id]);
    }
    if ($status === 'delivery_failed' && ($row['payment_method'] ?? '') === 'cod') {
        maybe_cod_block($pdo, (string) $row['customer_phone']);
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
require dirname(__DIR__) . '/includes/desk.php';
require dirname(__DIR__) . '/includes/otp.php';
require dirname(__DIR__) . '/includes/mail.php';
require dirname(__DIR__) . '/includes/pricing.php';
require dirname(__DIR__) . '/includes/pdf.php';
require dirname(__DIR__) . '/includes/invoice.php';
require dirname(__DIR__) . '/includes/qr.php';
require dirname(__DIR__) . '/includes/commerce.php';
require dirname(__DIR__) . '/includes/signin.php';
require dirname(__DIR__) . '/includes/layout.php';
require dirname(__DIR__) . '/includes/lists.php';
schema_guard();
