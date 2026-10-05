<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();

$error = null;
$user = current_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $name = trim((string) ($_POST['name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $address = trim((string) ($_POST['address'] ?? ''));
        $city = trim((string) ($_POST['city'] ?? ''));
        $state = trim((string) ($_POST['state'] ?? ''));
        $postal = trim((string) ($_POST['postal'] ?? ''));
        $method = (string) ($_POST['method'] ?? 'cod');
        $couponCode = trim((string) ($_POST['coupon'] ?? ''));
        if (strlen($name) < 2 || !preg_match('/^[0-9]{10}$/', $phone) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Check the name, 10-digit phone and email.');
        }
        if (strlen($address) < 8 || strlen($city) < 2 || strlen($state) < 2 || !preg_match('/^[0-9]{6}$/', $postal)) {
            throw new RuntimeException('Check the address and 6-digit PIN code.');
        }
        if (!in_array($method, ['cod', 'bank_transfer'], true)) {
            throw new RuntimeException('Choose cash on delivery or bank transfer.');
        }
        $quote = quote_cart($couponCode);
        if (!$quote['lines']) {
            throw new RuntimeException('The cart is empty.');
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
            if ($qty < (int) $product['min_order']) {
                throw new RuntimeException($product['name'] . ' starts at ' . $product['min_order'] . ' trays.');
            }
            if ($qty > ((int) $product['stock_qty'] - (int) $product['reserved_qty'])) {
                throw new RuntimeException($product['name'] . ' does not have that many trays left.');
            }
            $locked[] = ['product' => $product, 'qty' => $qty, 'line' => (int) $product['price_inr'] * $qty];
        }
        $subtotal = 0;
        foreach ($locked as $line) {
            $subtotal += $line['line'];
        }
        $quote = quote_cart($couponCode);
        foreach ($locked as $line) {
            $upd = $pdo->prepare('UPDATE products SET reserved_qty = reserved_qty + ? WHERE id = ? AND stock_qty - reserved_qty >= ?');
            $upd->execute([$line['qty'], (int) $line['product']['id'], $line['qty']]);
            if ($upd->rowCount() !== 1) {
                throw new RuntimeException('Those trays were just reserved by another order.');
            }
        }
        $status = $method === 'cod' ? 'placed' : 'payment_pending';
        $payment = $method === 'cod' ? 'unpaid' : 'pending';
        $token = bin2hex(random_bytes(16));
        $number = 'PA' . random_int(100000, 999999);
        $ins = $pdo->prepare('INSERT INTO orders (
            order_number, lookup_token, user_id, customer_name, customer_phone, customer_email,
            status, payment_status, payment_method, inventory_state, subtotal_inr, discount_inr,
            shipping_inr, total_inr, address_line, city, state_name, postal_code
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $ins->execute([
            $number, $token, $user['id'] ?? null, $name, $phone, $email,
            $status, $payment, $method, 'reserved', $quote['subtotal'], $quote['discount'],
            $quote['shipping'], $quote['total'], $address, $city, $state, $postal,
        ]);
        $orderId = (int) $pdo->lastInsertId();
        $item = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, sku, unit_label, unit_price_inr, quantity, line_total_inr) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($locked as $line) {
            $item->execute([
                $orderId, (int) $line['product']['id'], $line['product']['name'], $line['product']['sku'],
                $line['product']['unit_label'], (int) $line['product']['price_inr'], $line['qty'], $line['line'],
            ]);
        }
        $pdo->prepare('INSERT INTO order_events (order_id, status, note) VALUES (?, ?, ?)')
            ->execute([$orderId, $status, 'Order received. Trays are reserved. Payment is not confirmed until the nursery says so.']);
        if ($quote['coupon']) {
            $pdo->prepare('INSERT INTO coupon_redemptions (coupon_id, order_id, customer_email, amount_inr) VALUES (?, ?, ?, ?)')
                ->execute([(int) $quote['coupon']['id'], $orderId, $email, $quote['discount']]);
        }
        $pdo->commit();
        $_SESSION['cart'] = [];
        header('Location: /order/' . $token);
        exit;
    } catch (Throwable $err) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $err->getMessage();
    }
}
try {
    $quote = quote_cart((string) ($_POST['coupon'] ?? ''));
} catch (Throwable $err) {
    $quote = quote_cart();
    $error = $error ?: $err->getMessage();
}
render_header('Checkout | Precision Agritech');
?>
<section class="section"><div class="wrap narrow">
  <h1>Checkout</h1>
  <?php if ($error): ?><p class="flash"><?= e($error) ?></p><?php endif; ?>
  <?php if (!$quote['lines']): ?><p>Your cart is empty.</p><?php else: ?>
  <p>Total <?= inr($quote['total']) ?>. Shipping <?= inr($quote['shipping']) ?>. The server calculates this total.</p>
  <form method="post">
    <?= csrf_field() ?>
    <label>Name <input name="name" required value="<?= e($user['name'] ?? '') ?>"></label>
    <label>Phone <input name="phone" required pattern="[0-9]{10}" value="<?= e($user['phone'] ?? '') ?>"></label>
    <label>Email <input name="email" type="email" required value="<?= e($user['email'] ?? '') ?>"></label>
    <label>Address <textarea name="address" required></textarea></label>
    <label>City <input name="city" required></label>
    <label>State <input name="state" required value="Maharashtra"></label>
    <label>PIN <input name="postal" required pattern="[0-9]{6}"></label>
    <label>Coupon <input name="coupon" value="<?= e((string) ($_POST['coupon'] ?? '')) ?>"></label>
    <label><input type="radio" name="method" value="cod" checked> Cash on delivery</label>
    <label><input type="radio" name="method" value="bank_transfer"> Bank transfer. The nursery confirms payment.</label>
    <button class="btn">Place order</button>
  </form>
  <?php endif; ?>
</div></section>
<?php render_footer(); ?>
