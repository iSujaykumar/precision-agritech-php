<?php
declare(strict_types=1);

function fmt_dt(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    $time = strtotime($value);
    if ($time === false) {
        return $value;
    }
    return date('d M Y, H:i', $time);
}

function status_label(string $status): string
{
    return [
        'placed' => 'Order received',
        'payment_pending' => 'Waiting for payment',
        'payment_confirmed' => 'Payment confirmed',
        'preparing' => 'Being prepared',
        'dispatched' => 'On the way',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
        'unpaid' => 'Not paid yet',
        'pending' => 'Waiting for payment',
        'paid' => 'Paid',
        'cod' => 'Cash on delivery',
        'bank_transfer' => 'Bank transfer',
        'razorpay' => 'Online payment',
    ][$status] ?? $status;
}

function offer_is_active(array $product): bool
{
    if ((int) ($product['on_offer'] ?? 0) !== 1) {
        return false;
    }
    $sale = $product['sale_price_inr'] ?? null;
    if ($sale === null || (int) $sale <= 0 || (int) $sale >= (int) $product['price_inr']) {
        return false;
    }
    $now = time();
    if (!empty($product['offer_starts_at']) && strtotime((string) $product['offer_starts_at']) > $now) {
        return false;
    }
    if (!empty($product['offer_ends_at']) && strtotime((string) $product['offer_ends_at']) < $now) {
        return false;
    }
    return true;
}

function effective_price(array $product): int
{
    if (offer_is_active($product)) {
        return (int) $product['sale_price_inr'];
    }
    return (int) $product['price_inr'];
}

function shipping_for(int $afterDiscount): int
{
    if ($afterDiscount <= 0) {
        return 0;
    }
    $freeOver = (int) setting('free_shipping_over_inr', '4000');
    if ($afterDiscount >= $freeOver) {
        return 0;
    }
    return (int) setting('shipping_flat_inr', '180');
}

function line_problem(array $product, int $qty): ?string
{
    if ((int) ($product['active'] ?? 0) !== 1) {
        return 'No longer available';
    }
    if (($product['availability'] ?? '') === 'not_in_season') {
        return 'Not in season right now';
    }
    $free = available_trays($product);
    if ($free < 1) {
        return 'Out of stock';
    }
    if ($qty > 50) {
        return 'Choose at most 50 trays';
    }
    if ($qty < (int) $product['min_order']) {
        return 'Minimum ' . (int) $product['min_order'] . ' trays';
    }
    if ($qty > $free) {
        return 'Only ' . $free . ' trays left';
    }
    return null;
}

function quote_cart(string $couponCode = ''): array
{
    $cart = cart();
    $lines = [];
    $subtotal = 0;
    $blocked = false;
    if ($cart) {
        $slugs = array_map('strval', array_keys($cart));
        $marks = implode(',', array_fill(0, count($slugs), '?'));
        $stmt = db()->prepare(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug
             FROM products p JOIN categories c ON c.id = p.category_id
             WHERE p.slug IN (' . $marks . ')'
        );
        $stmt->execute($slugs);
        $bySlug = [];
        foreach ($stmt->fetchAll() as $row) {
            $bySlug[$row['slug']] = $row;
        }
        foreach ($cart as $slug => $qty) {
            $qty = (int) $qty;
            $product = $bySlug[(string) $slug] ?? null;
            if (!$product || $qty < 1) {
                $blocked = true;
                $lines[] = ['product' => ['slug' => (string) $slug, 'name' => (string) $slug, 'image_url' => '', 'price_inr' => 0, 'unit_label' => 'tray', 'active' => 0], 'qty' => $qty, 'line' => 0, 'warning' => 'No longer available', 'ok' => false];
                continue;
            }
            $warning = line_problem($product, $qty);
            $price = effective_price($product);
            $line = $warning ? 0 : $price * $qty;
            if ($warning) {
                $blocked = true;
            } else {
                $subtotal += $line;
            }
            $lines[] = ['product' => $product, 'qty' => $qty, 'line' => $line, 'warning' => $warning, 'ok' => $warning === null, 'unit' => $price];
        }
    }
    $discount = 0;
    $coupon = null;
    $code = strtoupper(trim($couponCode));
    if ($code !== '' && !$blocked) {
        $coupon = lookup_coupon($code, $subtotal, null);
        $discount = (int) $coupon['discount'];
    }
    $after = max(0, $subtotal - $discount);
    $shipping = shipping_for($after);
    return [
        'lines' => $lines,
        'subtotal' => $subtotal,
        'discount' => $discount,
        'shipping' => $shipping,
        'total' => $after + $shipping,
        'coupon' => $coupon,
        'blocked' => $blocked,
    ];
}

function lookup_coupon(string $code, int $subtotal, ?PDO $pdo, ?string $email = null): array
{
    $pdo = $pdo ?: db();
    $sql = 'SELECT * FROM coupons WHERE code = ? AND active = 1';
    if ($pdo->inTransaction()) {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$code]);
    $coupon = $stmt->fetch();
    if (!$coupon) {
        throw new RuntimeException('This coupon is not valid.');
    }
    if (!empty($coupon['expires_at']) && strtotime((string) $coupon['expires_at']) < time()) {
        throw new RuntimeException('This coupon has expired.');
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
    $perCustomer = (int) ($coupon['per_customer_limit'] ?? 0);
    if ($perCustomer > 0 && $email) {
        $mine = $pdo->prepare('SELECT COUNT(*) FROM coupon_redemptions WHERE coupon_id = ? AND customer_email = ?');
        $mine->execute([(int) $coupon['id'], $email]);
        if ((int) $mine->fetchColumn() >= $perCustomer) {
            throw new RuntimeException('This coupon has already been used on your account.');
        }
    }
    $discount = $coupon['kind'] === 'percent'
        ? (int) round($subtotal * ((int) $coupon['amount']) / 100)
        : (int) $coupon['amount'];
    $coupon['discount'] = max(0, min($discount, $subtotal));
    return $coupon;
}

function lock_coupon(PDO $pdo, string $code, int $subtotal, ?string $email = null): ?array
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return null;
    }
    return lookup_coupon($code, $subtotal, $pdo, $email);
}

function order_next_statuses(string $from): array
{
    return [
        'placed' => ['preparing', 'cancelled'],
        'payment_pending' => ['payment_confirmed', 'cancelled'],
        'payment_confirmed' => ['preparing', 'cancelled'],
        'preparing' => ['dispatched', 'cancelled'],
        'dispatched' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
    ][$from] ?? [];
}

function convert_reserved_to_sold(PDO $pdo, int $orderId, int $adminId, string $reason): void
{
    $items = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ? AND product_id IS NOT NULL');
    $items->execute([$orderId]);
    foreach ($items->fetchAll() as $item) {
        $qty = (int) $item['quantity'];
        $productId = (int) $item['product_id'];
        $row = $pdo->prepare('SELECT stock_qty, reserved_qty FROM products WHERE id = ? FOR UPDATE');
        $row->execute([$productId]);
        $stock = $row->fetch();
        if (!$stock) {
            continue;
        }
        $before = (int) $stock['stock_qty'];
        $after = max(0, $before - $qty);
        $pdo->prepare('UPDATE products SET reserved_qty = GREATEST(0, reserved_qty - ?), stock_qty = GREATEST(0, stock_qty - ?) WHERE id = ?')
            ->execute([$qty, $qty, $productId]);
        $pdo->prepare('INSERT INTO inventory_movements (product_id, admin_user_id, change_qty, stock_before, stock_after, reason) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$productId, $adminId, -$qty, $before, $after, $reason]);
    }
    $pdo->prepare("UPDATE orders SET inventory_state = 'sold' WHERE id = ?")->execute([$orderId]);
}

function release_coupon(PDO $pdo, int $orderId): void
{
    $pdo->prepare('DELETE FROM coupon_redemptions WHERE order_id = ?')->execute([$orderId]);
}

function apply_order_status(PDO $pdo, int $id, string $status, int $adminId, string $note = '', bool $publicNote = true): void
{
    $order = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
    $order->execute([$id]);
    $row = $order->fetch();
    if (!$row) {
        throw new RuntimeException('Order not found.');
    }
    $from = (string) $row['status'];
    if (!in_array($status, order_next_statuses($from), true)) {
        throw new RuntimeException('That step is not available from ' . status_label($from) . '.');
    }
    $text = trim($note) !== '' ? trim($note) : 'Nursery updated the order.';
    if ($status === 'cancelled') {
        if (!in_array($row['inventory_state'], ['reserved', 'sold'], true)) {
            $pdo->prepare("UPDATE orders SET status = 'cancelled', cancel_reason = ? WHERE id = ?")->execute([$text, $id]);
        } elseif ($row['inventory_state'] === 'reserved') {
            $pdo->prepare('UPDATE products p JOIN order_items oi ON oi.product_id = p.id SET p.reserved_qty = GREATEST(0, p.reserved_qty - oi.quantity) WHERE oi.order_id = ?')->execute([$id]);
            $pdo->prepare("UPDATE orders SET inventory_state = 'released', status = 'cancelled', cancel_reason = ? WHERE id = ?")->execute([$text, $id]);
        } else {
            $items = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ? AND product_id IS NOT NULL');
            $items->execute([$id]);
            foreach ($items->fetchAll() as $item) {
                $qty = (int) $item['quantity'];
                $productId = (int) $item['product_id'];
                $stock = $pdo->prepare('SELECT stock_qty FROM products WHERE id = ? FOR UPDATE');
                $stock->execute([$productId]);
                $before = (int) $stock->fetchColumn();
                $after = $before + $qty;
                $pdo->prepare('UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?')->execute([$qty, $productId]);
                $pdo->prepare('INSERT INTO inventory_movements (product_id, admin_user_id, change_qty, stock_before, stock_after, reason) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$productId, $adminId, $qty, $before, $after, 'Cancelled order restock: ' . $text]);
            }
            $pdo->prepare("UPDATE orders SET inventory_state = 'restocked', status = 'cancelled', cancel_reason = ? WHERE id = ?")->execute([$text, $id]);
        }
        release_coupon($pdo, $id);
    } elseif ($status === 'payment_confirmed') {
        if ($row['inventory_state'] === 'reserved') {
            convert_reserved_to_sold($pdo, $id, $adminId, 'Bank payment confirmed');
        }
        $pdo->prepare("UPDATE orders SET status = 'payment_confirmed', payment_status = 'paid' WHERE id = ?")->execute([$id]);
    } elseif ($status === 'dispatched') {
        if ($row['inventory_state'] === 'reserved') {
            convert_reserved_to_sold($pdo, $id, $adminId, 'Order dispatched');
        }
        $pdo->prepare("UPDATE orders SET status = 'dispatched' WHERE id = ?")->execute([$id]);
    } elseif ($status === 'delivered') {
        $paid = $row['payment_method'] === 'cod' ? 'paid' : (string) $row['payment_status'];
        $pdo->prepare('UPDATE orders SET status = ?, payment_status = ? WHERE id = ?')->execute(['delivered', $paid, $id]);
    } else {
        $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$status, $id]);
    }
    $pdo->prepare('INSERT INTO order_events (order_id, status, note, public_note) VALUES (?, ?, ?, ?)')
        ->execute([$id, $status, $text, $publicNote ? 1 : 0]);
    audit_log($adminId, 'order_status', 'order', $id, $from, $status);
}

function expire_reservations(): int
{
    $pdo = db();
    $rows = $pdo->query("SELECT id FROM orders WHERE status IN ('placed','payment_pending') AND inventory_state = 'reserved' AND reserved_until IS NOT NULL AND reserved_until < NOW()")->fetchAll();
    $count = 0;
    foreach ($rows as $row) {
        $pdo->beginTransaction();
        try {
            $order = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            $order->execute([(int) $row['id']]);
            $current = $order->fetch();
            if ($current && in_array($current['status'], ['placed', 'payment_pending'], true) && $current['inventory_state'] === 'reserved') {
                $pdo->prepare('UPDATE products p JOIN order_items oi ON oi.product_id = p.id SET p.reserved_qty = GREATEST(0, p.reserved_qty - oi.quantity) WHERE oi.order_id = ?')->execute([(int) $current['id']]);
                $pdo->prepare("UPDATE orders SET status = 'cancelled', inventory_state = 'released', cancel_reason = 'Reservation expired' WHERE id = ?")->execute([(int) $current['id']]);
                release_coupon($pdo, (int) $current['id']);
                $pdo->prepare("INSERT INTO order_events (order_id, status, note, public_note) VALUES (?, 'cancelled', 'Reservation expired', 1)")->execute([(int) $current['id']]);
                $count++;
            }
            $pdo->commit();
        } catch (Throwable $err) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($err->getMessage());
        }
    }
    return $count;
}

function order_rate_limited(string $phone): bool
{
    maybe_cleanup();
    $ip = db()->prepare('SELECT COUNT(*) FROM order_attempts WHERE ip = ? AND created_at > (NOW() - INTERVAL 1 HOUR)');
    $ip->execute([client_ip()]);
    $who = db()->prepare('SELECT COUNT(*) FROM order_attempts WHERE phone = ? AND created_at > (NOW() - INTERVAL 1 DAY)');
    $who->execute([$phone]);
    return (int) $ip->fetchColumn() >= 3 || (int) $who->fetchColumn() >= 3;
}

function note_order_attempt(string $phone): void
{
    db()->prepare('INSERT INTO order_attempts (ip, phone) VALUES (?, ?)')->execute([client_ip(), $phone]);
}

function maybe_cleanup(): void
{
    static $done = false;
    if ($done || random_int(1, 50) !== 1) {
        $done = true;
        return;
    }
    $done = true;
    db()->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 2 DAY)');
    db()->exec('DELETE FROM lookup_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
    db()->exec('DELETE FROM order_attempts WHERE created_at < (NOW() - INTERVAL 2 DAY)');
    db()->exec('DELETE FROM password_resets WHERE expires_at < (NOW() - INTERVAL 2 DAY)');
    db()->exec("DELETE FROM mobile_verifications WHERE created_at < (NOW() - INTERVAL 2 DAY) AND status <> 'pending'");
}

function flash(string $type, string $message): void
{
    ensure_session();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function render_flash(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['flash']['message'])) {
        return;
    }
    $type = (string) ($_SESSION['flash']['type'] ?? 'info');
    $message = (string) $_SESSION['flash']['message'];
    unset($_SESSION['flash']);
    $role = $type === 'error' ? 'alert' : 'status';
    echo '<p class="flash ' . e($type) . '" role="' . $role . '">' . e($message) . '</p>';
}

function form_is_human(): void
{
    if (trim((string) ($_POST['company_website'] ?? '')) !== '') {
        throw new RuntimeException('The form could not be sent.');
    }
    $opened = (int) ($_POST['opened_at'] ?? 0);
    if ($opened > 0 && time() - $opened < 3) {
        throw new RuntimeException('Please wait a moment, then send the form again.');
    }
    global $config;
    $secret = (string) ($config['turnstile_secret'] ?? '');
    if ($secret === '') {
        return;
    }
    $token = (string) ($_POST['cf-turnstile-response'] ?? '');
    if ($token === '') {
        throw new RuntimeException('Please confirm you are not a robot.');
    }
    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['secret' => $secret, 'response' => $token, 'remoteip' => client_ip()]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    $data = json_decode((string) $raw, true);
    if (!is_array($data) || empty($data['success'])) {
        throw new RuntimeException('Please confirm you are not a robot.');
    }
}

function turnstile_field(): string
{
    global $config;
    $site = (string) ($config['turnstile_site_key'] ?? '');
    if ($site === '') {
        return '';
    }
    return '<div class="cf-turnstile" data-sitekey="' . e($site) . '"></div>';
}

function opened_field(): string
{
    return '<input type="hidden" name="opened_at" value="' . time() . '"><input class="hp" name="company_website" tabindex="-1" autocomplete="off" aria-hidden="true">';
}

function image_variants(string $url): array
{
    if (!preg_match('#^/products/([a-z0-9-]+)\.webp$#', $url, $match)) {
        return ['src' => $url, 'srcset' => '', 'width' => 800, 'height' => 600];
    }
    $slug = $match[1];
    $srcset = [];
    foreach ([400, 800, 1200] as $width) {
        $path = '/products/sm/' . $slug . '-' . $width . '.webp';
        if (is_file(dirname(__DIR__) . $path)) {
            $srcset[] = $path . ' ' . $width . 'w';
        }
    }
    return [
        'src' => $srcset ? '/products/sm/' . $slug . '-400.webp' : $url,
        'srcset' => implode(', ', $srcset),
        'width' => 800,
        'height' => 600,
    ];
}

function whatsapp_link(string $text): string
{
    $number = preg_replace('/\D+/', '', setting('whatsapp_number', '919011975959')) ?? '';
    return 'https://wa.me/' . $number . '?text=' . rawurlencode($text);
}

function clear_login_failures(string $identifier): void
{
    db()->prepare('DELETE FROM login_attempts WHERE ip = ? AND identifier = ?')->execute([client_ip(), substr($identifier, 0, 160)]);
}

function duplicate_key(PDOException $err): string
{
    $info = $err->errorInfo ?? [];
    if ((int) ($info[1] ?? 0) !== 1062) {
        return '';
    }
    $detail = (string) ($info[2] ?? '');
    if (str_contains($detail, 'users_phone')) {
        return 'phone';
    }
    if (str_contains($detail, 'users_email')) {
        return 'email';
    }
    if (str_contains($detail, 'products_slug') || str_contains($detail, 'slug')) {
        return 'slug';
    }
    if (str_contains($detail, 'products_sku') || str_contains($detail, 'sku')) {
        return 'sku';
    }
    if (str_contains($detail, 'coupons_code')) {
        return 'code';
    }
    return 'other';
}
