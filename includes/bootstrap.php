<?php
declare(strict_types=1);

$config = require dirname(__DIR__) . '/config/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
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
    $stmt = db()->prepare('SELECT id, name, email, phone, role FROM users WHERE id = ?');
    $stmt->execute([(int) $id]);
    $user = $stmt->fetch();
    return $user ?: null;
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
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function require_csrf(): void
{
    $sent = (string) ($_POST['csrf'] ?? '');
    $known = (string) ($_SESSION['csrf'] ?? '');
    if ($sent === '' || !hash_equals($known, $sent)) {
        throw new RuntimeException('The form expired. Reload the page and try again.');
    }
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

require dirname(__DIR__) . '/includes/layout.php';
