<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();

$user = current_user();
if (!$user || ($user['role'] ?? '') === 'admin') {
    header('Location: /login?next=' . rawurlencode('/checkout'));
    exit;
}
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        take_checkout_token((string) ($_POST['checkout_token'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));
        $phone = normalize_phone((string) ($_POST['phone'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $address = trim((string) ($_POST['address'] ?? ''));
        $city = trim((string) ($_POST['city'] ?? ''));
        $state = trim((string) ($_POST['state'] ?? ''));
        $postal = trim((string) ($_POST['postal'] ?? ''));
        $method = (string) ($_POST['method'] ?? 'cod');
        $couponCode = trim((string) ($_POST['coupon'] ?? ''));
        if (strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Check the name, mobile and email.');
        }
        if (strlen($address) < 8 || strlen($city) < 2 || strlen($state) < 2 || !preg_match('/^[0-9]{6}$/', $postal)) {
            throw new RuntimeException('Check the address and 6-digit PIN code.');
        }
        if (!in_array($method, ['cod', 'bank_transfer'], true)) {
            throw new RuntimeException('Choose cash on delivery or bank transfer.');
        }
        if (shop_paused()) {
            throw new RuntimeException('Orders are paused');
        }
        $quote = quote_cart($couponCode);
        if (!$quote['lines']) {
            throw new RuntimeException('The cart is empty.');
        }
        $minimum = (int) setting('min_order_inr', '0');
        if ($minimum > 0 && $quote['subtotal'] < $minimum) {
            throw new RuntimeException('The minimum order is ' . inr($minimum) . '.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        $locked = [];
        foreach ($quote['lines'] as $line) {
            $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? AND active = 1 FOR UPDATE');
            $stmt->execute([(int) $line['product']['id']]);
            $product = $stmt->fetch();
            if (!$product) {
                throw new RuntimeException('A tray in this cart is no longer listed.');
            }
            $qty = (int) $line['qty'];
            if ($qty < (int) $product['min_order'] || $qty > 50) {
                throw new RuntimeException($product['name'] . ' must be between ' . (int) $product['min_order'] . ' and 50 trays.');
            }
            if ($qty > ((int) $product['stock_qty'] - (int) $product['reserved_qty'])) {
                throw new RuntimeException('A tray in your cart no longer has that many left. Check the cart and try again.');
            }
            $locked[] = ['product' => $product, 'qty' => $qty, 'line' => (int) $product['price_inr'] * $qty];
        }
        $subtotal = 0;
        foreach ($locked as $line) {
            $subtotal += $line['line'];
            $upd = $pdo->prepare('UPDATE products SET reserved_qty = reserved_qty + ? WHERE id = ? AND stock_qty - reserved_qty >= ?');
            $upd->execute([$line['qty'], (int) $line['product']['id'], $line['qty']]);
            if ($upd->rowCount() !== 1) {
                throw new RuntimeException('Those trays were just reserved by another order.');
            }
        }
        $coupon = lock_coupon($pdo, $couponCode, $subtotal);
        $discount = $coupon['discount'] ?? 0;
        $after = $subtotal - $discount;
        $shipFlat = (int) setting('shipping_flat_inr', '180');
        $freeOver = (int) setting('free_shipping_over_inr', '4000');
        $shipping = ($after >= $freeOver || $after === 0) ? 0 : $shipFlat;
        $total = $after + $shipping;
        $status = $method === 'cod' ? 'placed' : 'payment_pending';
        $payment = $method === 'cod' ? 'unpaid' : 'pending';
        $token = bin2hex(random_bytes(32));
        $ins = $pdo->prepare('INSERT INTO orders (
            order_number, lookup_token, user_id, customer_name, customer_phone, customer_email,
            status, payment_status, payment_method, inventory_state, subtotal_inr, discount_inr,
            shipping_inr, total_inr, address_line, city, state_name, postal_code
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $orderId = 0;
        $number = '';
        for ($try = 0; $try < 3; $try++) {
            $number = 'PA' . strtoupper(bin2hex(random_bytes(4)));
            $pdo->exec('SAVEPOINT order_number');
            try {
                $ins->execute([
                    $number, $token, $user ? (int) $user['id'] : null, $name, $phone, $email,
                    $status, $payment, $method, 'reserved', $subtotal, $discount,
                    $shipping, $total, $address, $city, $state, $postal,
                ]);
                $orderId = (int) $pdo->lastInsertId();
                break;
            } catch (PDOException $duplicate) {
                $pdo->exec('ROLLBACK TO SAVEPOINT order_number');
                if ($try === 2) {
                    throw $duplicate;
                }
            }
        }
        $item = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, sku, unit_label, unit_price_inr, quantity, line_total_inr) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($locked as $line) {
            $item->execute([
                $orderId, (int) $line['product']['id'], $line['product']['name'], $line['product']['sku'],
                $line['product']['unit_label'], (int) $line['product']['price_inr'], $line['qty'], $line['line'],
            ]);
        }
        $pdo->prepare('INSERT INTO order_events (order_id, status, note) VALUES (?, ?, ?)')
            ->execute([$orderId, $status, 'Order received. Trays are reserved. Payment is not confirmed until the nursery says so.']);
        if ($coupon) {
            $pdo->prepare('INSERT INTO coupon_redemptions (coupon_id, order_id, customer_email, amount_inr) VALUES (?, ?, ?, ?)')
                ->execute([(int) $coupon['id'], $orderId, $email, $discount]);
        }
        $pdo->commit();
        $_SESSION['cart'] = [];
        unset($_SESSION['coupon']);
        mail_order_customer([
            'order_number' => $number,
            'total_inr' => $total,
            'customer_email' => $email,
        ], 'Thank you. Your order is placed.');
        mail_staff('New order ' . $number, $name . ' placed order ' . $number . ' for ' . inr($total) . '.');
        header('Location: /order/' . $token);
        exit;
    } catch (Throwable $err) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = safe_error($err, 'The order could not be saved. Nothing was charged.');
    }
}
try {
    $quote = quote_cart((string) ($_POST['coupon'] ?? ''));
} catch (Throwable $err) {
    try {
        $quote = quote_cart();
    } catch (Throwable $ignored) {
        $quote = ['lines' => [], 'subtotal' => 0, 'discount' => 0, 'shipping' => 0, 'total' => 0, 'coupon' => null];
    }
    $error = $error ?: safe_error($err, 'The cart could not be priced.');
}
render_header('Checkout | Precision Agritech');
?>
<section class="section"><div class="wrap narrow">
  <h1>Checkout</h1>
  <?php if ($error): ?><p class="flash"><?= e($error) ?></p><?php endif; ?>
  <?php if (!$quote['lines']): ?><p>Your cart is empty.</p><?php else: ?>
  <p>Total <?= inr($quote['total']) ?>. Shipping <?= inr($quote['shipping']) ?>.</p>
  <p class="muted">Trays usually leave the nursery within 2 to 4 days after the order is confirmed.</p>
  <?php $payNote = trim(setting('payment_instructions', '')); if ($payNote !== ''): ?><p><?= e($payNote) ?></p><?php endif; ?>
  <?php if (shop_paused()): ?><p class="flash bad">Orders are paused</p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="checkout_token" value="<?= e(checkout_token()) ?>">
    <label>Name <input name="name" required value="<?= e($user ? (string) $user['name'] : '') ?>"></label>
    <label>Phone <input name="phone" required value="<?= e($user ? (string) $user['phone'] : '') ?>"></label>
    <label>Email <input name="email" type="email" required value="<?= e($user ? (string) $user['email'] : '') ?>"></label>
    <label>Address <textarea name="address" required></textarea></label>
    <label>City <input name="city" required></label>
    <label>State <input name="state" required value="Maharashtra"></label>
    <label>PIN <input name="postal" required pattern="[0-9]{6}" inputmode="numeric" maxlength="6"></label>
    <label>Coupon <input name="coupon" value="<?= e((string) ($_POST['coupon'] ?? $_SESSION['coupon'] ?? '')) ?>" autocomplete="off"></label>
    <label><input type="radio" name="method" value="cod" checked> Cash on delivery</label>
    <label><input type="radio" name="method" value="bank_transfer"> Bank transfer. The nursery confirms payment.</label>
    <button class="btn" type="submit" <?= shop_paused() ? 'disabled' : '' ?>>Place order</button>
  </form>
  <?php $help = wa_href('Hello Precision Agritech, I need help with checkout.'); if ($help !== ''): ?>
    <p><a href="<?= e($help) ?>" target="_blank" rel="noopener">Need help? Chat on WhatsApp</a></p>
  <?php endif; ?>
  <?php endif; ?>
</div></section>
<?php render_footer(); ?>
