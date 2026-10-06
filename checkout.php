<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();
expire_reservations();

$error = null;
$user = current_user();
$addresses = [];
if ($user) {
    $saved = db()->prepare('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC');
    $saved->execute([(int) $user['id']]);
    $addresses = $saved->fetchAll();
}
$posted = $_SERVER['REQUEST_METHOD'] === 'POST';
$fields = [
    'name' => $posted ? (string) ($_POST['name'] ?? '') : (string) ($user['name'] ?? ''),
    'phone' => $posted ? (string) ($_POST['phone'] ?? '') : (string) ($user['phone'] ?? ''),
    'email' => $posted ? (string) ($_POST['email'] ?? '') : (string) ($user['email'] ?? ''),
    'address' => $posted ? (string) ($_POST['address'] ?? '') : (string) ($addresses[0]['line'] ?? ''),
    'city' => $posted ? (string) ($_POST['city'] ?? '') : (string) ($addresses[0]['city'] ?? ''),
    'state' => $posted ? (string) ($_POST['state'] ?? '') : (string) ($addresses[0]['state_name'] ?? 'Maharashtra'),
    'postal' => $posted ? (string) ($_POST['postal'] ?? '') : (string) ($addresses[0]['postal_code'] ?? ''),
    'coupon' => (string) ($_POST['coupon'] ?? $_GET['coupon'] ?? ''),
    'method' => (string) ($_POST['method'] ?? 'cod'),
];
if ($posted && isset($_POST['address_id'], $addresses) && !$error) {
    foreach ($addresses as $savedAddress) {
        if ((int) $savedAddress['id'] === (int) $_POST['address_id'] && (int) $_POST['address_id'] > 0 && trim($fields['address']) === '') {
            $fields['address'] = (string) $savedAddress['line'];
            $fields['city'] = (string) $savedAddress['city'];
            $fields['state'] = (string) $savedAddress['state_name'];
            $fields['postal'] = (string) $savedAddress['postal_code'];
        }
    }
}

if ($posted) {
    try {
        require_csrf();
        form_is_human();
        take_checkout_token((string) ($_POST['checkout_token'] ?? ''));
        $name = trim($fields['name']);
        $phone = normalize_phone($fields['phone']);
        $email = strtolower(trim($fields['email']));
        $address = trim($fields['address']);
        $city = trim($fields['city']);
        $state = trim($fields['state']);
        $postal = trim($fields['postal']);
        $method = $fields['method'];
        $couponCode = trim($fields['coupon']);
        if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Check the name, mobile and email.');
        }
        if (mb_strlen($address) < 8 || mb_strlen($city) < 2 || mb_strlen($state) < 2 || !preg_match('/^[0-9]{6}$/', $postal)) {
            throw new RuntimeException('Check the address and 6-digit PIN code.');
        }
        $allowed = ['cod'];
        if (bank_ready()) {
            $allowed[] = 'bank_transfer';
        }
        if (!in_array($method, $allowed, true)) {
            throw new RuntimeException('Choose a payment method the nursery is offering.');
        }
        if (order_rate_limited($phone)) {
            throw new RuntimeException('Too many orders were started from this connection. Please call 9011975959 or try again later.');
        }
        if ($method === 'cod' && twilio_configured()) {
            $verified = $user && !empty($user['phone_verified_at']) && (string) $user['phone'] === $phone;
            if (!$verified) {
                throw new RuntimeException('Cash on delivery needs a verified mobile on the account. Sign in and verify the mobile, or pay by bank transfer.');
            }
        }
        $quote = quote_cart($couponCode);
        if (!$quote['lines'] || $quote['blocked']) {
            throw new RuntimeException('Check the cart. A tray is out of stock, below the minimum, or no longer listed.');
        }
        $pdo = db();
        $placed = false;
        for ($attempt = 0; $attempt < 2 && !$placed; $attempt++) {
            try {
                $pdo->beginTransaction();
                $ids = [];
                foreach ($quote['lines'] as $line) {
                    $ids[] = (int) $line['product']['id'];
                }
                sort($ids);
                $locked = [];
                foreach ($ids as $productId) {
                    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? AND active = 1 FOR UPDATE');
                    $stmt->execute([$productId]);
                    $product = $stmt->fetch();
                    if (!$product) {
                        throw new RuntimeException('A tray in this cart is no longer listed.');
                    }
                    $qty = 0;
                    foreach ($quote['lines'] as $line) {
                        if ((int) $line['product']['id'] === $productId) {
                            $qty = (int) $line['qty'];
                        }
                    }
                    $problem = line_problem($product, $qty);
                    if ($problem) {
                        throw new RuntimeException($product['name'] . ': ' . $problem);
                    }
                    $unit = effective_price($product);
                    $locked[] = ['product' => $product, 'qty' => $qty, 'line' => $unit * $qty, 'unit' => $unit];
                }
                $subtotal = 0;
                foreach ($locked as $line) {
                    $subtotal += $line['line'];
                    $upd = $pdo->prepare('UPDATE products SET reserved_qty = reserved_qty + ? WHERE id = ? AND stock_qty - reserved_qty >= ?');
                    $upd->execute([$line['qty'], (int) $line['product']['id'], $line['qty']]);
                    if ($upd->rowCount() !== 1) {
                        throw new RuntimeException($line['product']['name'] . ' was just reserved by another order.');
                    }
                }
                $coupon = lock_coupon($pdo, $couponCode, $subtotal, $email);
                $discount = (int) ($coupon['discount'] ?? 0);
                $after = $subtotal - $discount;
                $shipping = shipping_for($after);
                $total = $after + $shipping;
                if ($method === 'cod') {
                    $status = 'placed';
                    $payment = 'unpaid';
                    $hours = (int) setting('cod_reserve_hours', '72');
                } else {
                    $status = 'payment_pending';
                    $payment = 'pending';
                    $hours = (int) setting('bank_reserve_hours', '48');
                }
                $token = bin2hex(random_bytes(32));
                $ins = $pdo->prepare('INSERT INTO orders (
                    order_number, lookup_token, user_id, customer_name, customer_phone, customer_email,
                    status, payment_status, payment_method, inventory_state, subtotal_inr, discount_inr,
                    shipping_inr, total_inr, address_line, city, state_name, postal_code, reserved_until
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ' . max(1, $hours) . ' HOUR))');
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
                        if ($try === 2 || !str_contains($duplicate->getMessage(), 'orders_number')) {
                            throw $duplicate;
                        }
                    }
                }
                $item = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, sku, unit_label, unit_price_inr, quantity, line_total_inr) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $summary = [];
                foreach ($locked as $line) {
                    $item->execute([
                        $orderId, (int) $line['product']['id'], $line['product']['name'], $line['product']['sku'],
                        $line['product']['unit_label'], $line['unit'], $line['qty'], $line['line'],
                    ]);
                    $summary[] = $line['qty'] . ' x ' . $line['product']['name'] . ' = ' . inr($line['line']);
                }
                $pdo->prepare('INSERT INTO order_events (order_id, status, note, public_note) VALUES (?, ?, ?, 1)')
                    ->execute([$orderId, $status, 'Order received. The trays are reserved.']);
                if ($coupon) {
                    $pdo->prepare('INSERT INTO coupon_redemptions (coupon_id, order_id, customer_email, amount_inr) VALUES (?, ?, ?, ?)')
                        ->execute([(int) $coupon['id'], $orderId, $email, $discount]);
                }
                $pdo->commit();
                $placed = true;
                note_order_attempt($phone);
                $_SESSION['cart'] = [];
                if ($user) {
                    db()->prepare('DELETE FROM carts WHERE user_id = ?')->execute([(int) $user['id']]);
                }
                $_SESSION['last_order'] = ['token' => $token, 'at' => time()];
                global $config;
                $link = rtrim((string) ($config['site_url'] ?? ''), '/') . '/order/' . $token;
                $customerMail = "Thank you. Your order " . $number . " is reserved.\n\n"
                    . implode("\n", $summary)
                    . "\n\nTrays " . inr($subtotal) . "\nDiscount " . inr($discount) . "\nShipping " . inr($shipping) . "\nTotal " . inr($total)
                    . "\nPayment: " . status_label($method)
                    . "\n\nKeep this private link. It shows the address and, for a bank transfer, where to pay.\n" . $link;
                if ($method === 'bank_transfer') {
                    $customerMail .= "\n\n" . bank_instructions($number, $total);
                }
                send_mail($email, 'Order ' . $number . ' from Precision Agritech', $customerMail, (string) ($config['mail_from'] ?? null));
                $nursery = (string) ($config['mail_from'] ?? 'info@precisionagritech.in');
                send_mail($nursery, 'New order ' . $number, "New order " . $number . "\n" . count($locked) . " lines, " . inr($total) . ", " . status_label($method) . "\n" . $link . "\nDesk: " . rtrim((string) ($config['site_url'] ?? ''), '/') . '/admin/order-view?id=' . $orderId);
                header('Location: /order/' . $token);
                exit;
            } catch (PDOException $err) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $sqlState = (string) ($err->errorInfo[1] ?? 0);
                if ($sqlState === '1213' && $attempt === 0) {
                    continue;
                }
                throw $err;
            }
        }
    } catch (Throwable $err) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $last = $_SESSION['last_order'] ?? null;
        if ($err instanceof RuntimeException && str_contains($err->getMessage(), 'already submitted') && is_array($last) && (time() - (int) ($last['at'] ?? 0)) < 600 && !empty($last['token'])) {
            header('Location: /order/' . $last['token']);
            exit;
        }
        $error = safe_error($err, 'Your order was not placed. Nothing was reserved.');
    }
}
try {
    $quote = quote_cart($fields['coupon']);
} catch (Throwable $err) {
    try {
        $quote = quote_cart();
    } catch (Throwable $ignored) {
        $quote = ['lines' => [], 'subtotal' => 0, 'discount' => 0, 'shipping' => 0, 'total' => 0, 'coupon' => null, 'blocked' => true];
    }
    $error = $error ?: safe_error($err, 'The cart could not be priced.');
}
render_header('Checkout | Precision Agritech');
?>
<section class="section"><div class="wrap narrow">
  <h1>Checkout</h1>
  <?php if ($error): ?><p class="flash error" role="alert" id="form-error"><?= e($error) ?></p><?php endif; ?>
  <?php if (!$quote['lines']): ?><p>Your cart is empty. <a href="/shop">Continue shopping</a></p><?php elseif (!empty($quote['blocked'])): ?>
    <p class="flash error" role="alert">A tray in the cart needs a change before checkout. <a href="/cart">Review the cart</a></p>
  <?php else: ?>
  <p>Total <?= inr($quote['total']) ?>. Shipping <?= $quote['shipping'] === 0 ? 'free' : inr($quote['shipping']) ?>.</p>
  <form method="post" id="checkout-form">
    <?= csrf_field() ?>
    <?= opened_field() ?>
    <?= turnstile_field() ?>
    <input type="hidden" name="checkout_token" value="<?= e(checkout_token()) ?>">
    <?php if ($addresses): ?>
      <label>Saved address
        <select name="address_id">
          <option value="0">Type a new address</option>
          <?php foreach ($addresses as $savedAddress): ?>
            <option value="<?= (int) $savedAddress['id'] ?>"><?= e($savedAddress['line'] . ', ' . $savedAddress['city']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    <?php endif; ?>
    <label>Name <input name="name" required autocomplete="name" value="<?= e($fields['name']) ?>"></label>
    <label>Phone <input name="phone" type="tel" inputmode="tel" autocomplete="tel" required value="<?= e($fields['phone']) ?>"></label>
    <label>Email <input name="email" type="email" autocomplete="email" required value="<?= e($fields['email']) ?>"></label>
    <label>Address <textarea name="address" required autocomplete="street-address"><?= e($fields['address']) ?></textarea></label>
    <label>City <input name="city" required autocomplete="address-level2" value="<?= e($fields['city']) ?>"></label>
    <label>State <input name="state" required autocomplete="address-level1" value="<?= e($fields['state']) ?>"></label>
    <label>PIN <input name="postal" required inputmode="numeric" autocomplete="postal-code" pattern="[0-9]{6}" maxlength="6" value="<?= e($fields['postal']) ?>" aria-describedby="form-error"></label>
    <label>Coupon <input name="coupon" value="<?= e($fields['coupon']) ?>"></label>
    <fieldset>
      <legend>Payment</legend>
      <label><input type="radio" name="method" value="cod" <?= $fields['method'] === 'cod' ? 'checked' : '' ?>> Cash on delivery</label>
      <?php if (bank_ready()): ?>
        <label><input type="radio" name="method" value="bank_transfer" <?= $fields['method'] === 'bank_transfer' ? 'checked' : '' ?>> Bank transfer. The nursery confirms the payment.</label>
      <?php endif; ?>
    </fieldset>
    <button class="btn" id="place-order" type="submit">Place order</button>
  </form>
  <?php endif; ?>
</div></section>
<script src="/assets/js/checkout.js" defer></script>
<?php if (($config['turnstile_site_key'] ?? '') !== ''): ?><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script><?php endif; ?>
<?php render_footer(); ?>
