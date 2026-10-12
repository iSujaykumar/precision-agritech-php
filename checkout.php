<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();

$user = current_user();
if ($user && ($user['role'] ?? '') === 'admin') {
    $user = null;
}
$errors = [];
$posted = [
    'name' => trim((string) ($_POST['name'] ?? ($user['name'] ?? ''))),
    'phone' => trim((string) ($_POST['phone'] ?? ($user['phone'] ?? ''))),
    'email' => trim((string) ($_POST['email'] ?? ($user['email'] ?? ''))),
    'address' => trim((string) ($_POST['address'] ?? '')),
    'city' => trim((string) ($_POST['city'] ?? '')),
    'state' => trim((string) ($_POST['state'] ?? 'Maharashtra')),
    'postal' => trim((string) ($_POST['postal'] ?? '')),
    'method' => (string) ($_POST['method'] ?? 'upi'),
    'coupon' => trim((string) ($_POST['coupon'] ?? ($_SESSION['coupon'] ?? ''))),
];
if ($posted['address'] === '' && $user) {
    $saved = db()->prepare('SELECT * FROM addresses WHERE user_id = ? ORDER BY id DESC LIMIT 1');
    $saved->execute([(int) $user['id']]);
    $address = $saved->fetch();
    if ($address && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $posted['address'] = (string) $address['line'];
        $posted['city'] = (string) $address['city'];
        $posted['state'] = (string) $address['state_name'];
        $posted['postal'] = (string) $address['postal_code'];
    }
}
$requireEmail = setting('require_email', '1') === '1';
$codOn = setting('cod_enabled', '1') === '1';
$upiOn = setting('upi_enabled', '1') === '1' && upi_id_ok(setting('upi_id', 'precision9958@fbl'));
$bankOn = setting('bank_enabled', '1') === '1';
if (!in_array($posted['method'], array_filter([$upiOn ? 'upi' : '', $bankOn ? 'bank_transfer' : '', $codOn ? 'cod' : '']), true)) {
    $posted['method'] = $upiOn ? 'upi' : ($bankOn ? 'bank_transfer' : 'cod');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        form_opened_ok();
        unset($_POST['price'], $_POST['total'], $_POST['unit_price']);
        take_checkout_token((string) ($_POST['checkout_token'] ?? ''));
        $name = clean_text($posted['name'], 120);
        try {
            $phone = normalize_phone($posted['phone']);
        } catch (RuntimeException $err) {
            $phone = '';
            $errors['phone'] = 'Enter a 10-digit mobile number.';
        }
        $email = strtolower(clean_text($posted['email'], 160));
        $address = clean_text($posted['address'], 400);
        $city = clean_text($posted['city'], 80);
        $state = clean_text($posted['state'], 80);
        $postal = preg_replace('/\D+/', '', $posted['postal']) ?? '';
        $method = $posted['method'];
        $couponCode = strtoupper(clean_text($posted['coupon'], 40));
        if (strlen($name) < 2) {
            $errors['name'] = 'Enter the name for this order.';
        }
        if ($requireEmail && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter an email so we can send the order update.';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'That email does not look right.';
        }
        if (strlen($address) < 8) {
            $errors['address'] = 'Enter the full delivery address.';
        }
        if (strlen($city) < 2) {
            $errors['city'] = 'Enter the city.';
        }
        if (strlen($state) < 2) {
            $errors['state'] = 'Enter the state.';
        }
        if (!preg_match('/^[0-9]{6}$/', $postal)) {
            $errors['postal'] = 'Enter a 6-digit PIN code.';
        } elseif (!pin_is_served($postal)) {
            $errors['postal'] = 'We do not deliver to this PIN yet.';
        }
        $allowedMethods = [];
        if ($upiOn) {
            $allowedMethods[] = 'upi';
        }
        if ($bankOn) {
            $allowedMethods[] = 'bank_transfer';
        }
        if ($codOn) {
            $allowedMethods[] = 'cod';
        }
        if (!in_array($method, $allowedMethods, true)) {
            $errors['method'] = 'Choose how you would like to pay.';
        }
        if ($errors) {
            throw new RuntimeException('Check the fields below.');
        }
        if (shop_paused()) {
            throw new RuntimeException('Orders are paused right now.');
        }
        $hold = unpaid_hold_message($phone);
        if ($hold !== null) {
            throw new RuntimeException($hold);
        }
        if ($method === 'cod' && cod_is_blocked($phone)) {
            throw new RuntimeException('Cash on delivery is not available for this mobile number.');
        }
        $quote = quote_cart($couponCode);
        if (!$quote['lines'] || !empty($quote['blocked'])) {
            throw new RuntimeException('Fix the cart before checkout. A tray is missing or there are not enough left.');
        }
        $minimum = (int) setting('min_order_inr', '0');
        if ($minimum > 0 && $quote['subtotal'] < $minimum) {
            throw new RuntimeException('The minimum order is ' . inr($minimum) . '.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        $locked = [];
        foreach ($quote['lines'] as $line) {
            $product = lock_listed_product($pdo, (int) $line['product']['id']);
            if (!$product) {
                throw new RuntimeException('A tray in this cart is no longer listed.');
            }
            $qty = (int) $line['qty'];
            $sell = selling_price($product);
            if (!empty($sell['blocked']) || ((int) ($product['farmer_only'] ?? 0) === 1 && !can_see_farmer())) {
                throw new RuntimeException('Price not available, please contact us.');
            }
            $price = (int) $sell['rupees'];
            $unitPaise = (int) ($sell['tray_paise'] ?? ($price * 100));
            if ($qty < (int) $product['min_order'] || $qty > 50 || $qty > available_trays($product)) {
                throw new RuntimeException($product['name'] . ' no longer has that many trays.');
            }
            $upd = $pdo->prepare('UPDATE products SET reserved_qty = reserved_qty + ? WHERE id = ? AND stock_qty - reserved_qty >= ?');
            $upd->execute([$qty, (int) $product['id'], $qty]);
            if ($upd->rowCount() !== 1) {
                throw new RuntimeException('Those trays were just reserved by another order.');
            }
            $locked[] = ['product' => $product, 'qty' => $qty, 'price' => $price, 'line' => intdiv($unitPaise * $qty + 50, 100), 'unit_paise' => $unitPaise, 'line_paise' => $unitPaise * $qty, 'sell' => $sell];
        }
        $subtotal = 0;
        foreach ($locked as $line) {
            $subtotal += $line['line'];
        }
        $coupon = null;
        $GLOBALS['coupon_phone'] = $phone;
        $GLOBALS['coupon_email'] = $email;
        $coupon = lock_coupon($pdo, $couponCode, $subtotal);
        $discount = (int) ($coupon['discount'] ?? 0);
        $basePaise = seasonal_base_paise($locked);
        $seasonal = seasonal_offer(intdiv($basePaise + 50, 100), customer_is_farmer(), $method === 'cod', cart_seedling_count($locked));
        if ((int) ($seasonal['amount'] ?? 0) > 0) {
            $seasonal['amount_paise'] = round_half_up_percent($basePaise, (int) $seasonal['percent']);
            $seasonal['amount'] = intdiv((int) $seasonal['amount_paise'] + 50, 100);
        }
        if ($seasonal['amount'] > 0 && $discount > 0 && setting('seasonal_stacks_coupons', '0') !== '1') {
            if ($seasonal['amount'] > $discount) {
                $discount = 0;
            } else {
                $seasonal['amount'] = 0;
                $seasonal['amount_paise'] = 0;
            }
        }
        $seasonalAmt = (int) $seasonal['amount'];
        $seasonalPaise = (int) ($seasonal['amount_paise'] ?? ($seasonalAmt * 100));
        $after = max(0, $subtotal - $discount - $seasonalAmt);
        $shipping = zone_shipping($after, $postal);
        $total = $after + $shipping;
        $subtotalPaise = 0;
        foreach ($locked as $line) {
            $subtotalPaise += (int) $line['line_paise'];
        }
        $discountPaise = $discount * 100;
        $shippingPaise = $shipping * 100;
        $totalPaise = max(0, $subtotalPaise - $discountPaise - $seasonalPaise) + $shippingPaise;
        if ($method === 'cod') {
            $minCod = (int) setting('cod_min_inr', '0');
            $maxCod = (int) setting('cod_max_inr', '0');
            if ($minCod > 0 && $total < $minCod) {
                throw new RuntimeException('Cash on delivery starts at ' . inr($minCod) . '.');
            }
            if ($maxCod > 0 && $total > $maxCod) {
                throw new RuntimeException('Cash on delivery is available up to ' . inr($maxCod) . '.');
            }
        }
        $status = $method === 'cod' ? 'placed' : 'payment_pending';
        $payment = $method === 'cod' ? 'unpaid' : 'pending';
        $token = bin2hex(random_bytes(32));
        $when = reservation_until($method);
        $ins = $pdo->prepare('INSERT INTO orders (
            order_number, lookup_token, user_id, customer_name, customer_phone, customer_email,
            status, payment_status, payment_method, inventory_state, subtotal_inr, discount_inr,
            shipping_inr, total_inr, seasonal_discount_inr, price_list_name, address_line, city, state_name, postal_code, reserved_until,
            subtotal_paise, discount_paise, shipping_paise, total_paise, seasonal_discount_paise
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $orderId = 0;
        $number = '';
        for ($try = 0; $try < 3; $try++) {
            $number = 'PA' . strtoupper(bin2hex(random_bytes(4)));
            $pdo->exec('SAVEPOINT order_number');
            try {
                $ins->execute([
                    $number, $token, $user ? (int) $user['id'] : null, $name, $phone, $email !== '' ? $email : null,
                    $status, $payment, $method, 'reserved', $subtotal, $discount,
                    $shipping, $total, $seasonalAmt, customer_is_farmer() ? 'Farmer' : 'Seasonal (2026-27)', $address, $city, $state, $postal, $when,
                    $subtotalPaise, $discountPaise, $shippingPaise, $totalPaise, $seasonalPaise,
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
        $item = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, sku, unit_label, unit_price_inr, quantity, line_total_inr, seedling_paise, tray_size, price_list_name, marathi_name, unit_paise, line_paise) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($locked as $line) {
            $sell = $line['sell'];
            $label = (string) $line['product']['name'];
            $variety = trim((string) ($line['product']['variety'] ?? ''));
            if ($variety !== '' && !str_contains($label, $variety)) {
                $label .= ' — ' . $variety;
            }
            $marathi = (string) ($sell['marathi'] ?? '');
            if ($marathi === '') {
                $marathi = (string) ($line['product']['marathi_name'] ?? '');
            }
            $item->execute([
                $orderId, (int) $line['product']['id'], $label, $line['product']['sku'],
                $line['product']['unit_label'], $line['price'], $line['qty'], $line['line'],
                $sell['paise'], (int) $sell['tray'], (string) $sell['list'], $marathi !== '' ? $marathi : null,
                (int) $line['unit_paise'], (int) $line['line_paise'],
            ]);
        }
        $pdo->prepare('INSERT INTO order_events (order_id, status, note) VALUES (?, ?, ?)')
            ->execute([$orderId, $status, $method === 'cod' ? 'Order received. We will confirm by phone before dispatch.' : 'Order received. Trays are reserved until payment is confirmed.']);
        if ($coupon) {
            $pdo->prepare('INSERT INTO coupon_redemptions (coupon_id, order_id, customer_email, phone, amount_inr) VALUES (?, ?, ?, ?, ?)')
                ->execute([(int) $coupon['id'], $orderId, $email, $phone, $discount]);
        }
        $pdo->commit();
        note_order_attempt($phone);
        $_SESSION['cart'] = [];
        unset($_SESSION['coupon']);
        $mailOrder = [
            'order_number' => $number,
            'total_inr' => $total,
            'customer_email' => $email,
            'reserved_until' => $when,
            'payment_method' => $method,
            'payment_status' => $payment,
        ];
        mail_order_customer($mailOrder, $method === 'cod'
            ? 'Thank you. We will call or WhatsApp you to confirm before the trays leave.'
            : 'Thank you. Your trays are reserved. Pay with the details in this message, then we confirm the order.');
        mail_staff('New order ' . $number, $name . ' placed order ' . $number . ' for ' . inr($total) . ' (' . $method . ').');
        header('Location: /order/' . $token);
        exit;
    } catch (Throwable $err) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (!$errors) {
            $errors['form'] = safe_error($err, 'The order could not be saved. Nothing was charged.');
        }
    }
}
try {
    $quote = quote_cart($posted['coupon']);
} catch (Throwable $err) {
    $quote = quote_cart();
    $errors['coupon'] = safe_error($err, 'That coupon is not valid.');
}
$seasonalShown = (int) ($quote['seasonal']['amount'] ?? 0);
if ($posted['postal'] !== '' && preg_match('/^[0-9]{6}$/', $posted['postal'])) {
    $after = max(0, (int) $quote['subtotal'] - (int) $quote['discount'] - $seasonalShown);
    $quote['shipping'] = zone_shipping($after, $posted['postal']);
    $quote['total'] = $after + (int) $quote['shipping'];
}
$help = wa_href('Hello Precision Agritech, I need help with checkout. My PIN is ' . $posted['postal']);
$outside = $posted['postal'] !== '' && preg_match('/^[0-9]{6}$/', $posted['postal']) && !pin_is_served($posted['postal']);
render_header('Checkout | Precision Agritech');
?>
<section class="section"><div class="wrap">
  <p class="muted">1 Cart · 2 Details · 3 Pay · 4 Done</p>
  <h1>Checkout</h1>
  <?php if (!empty($errors['form'])): ?><p class="flash bad" role="alert"><?= e($errors['form']) ?></p><?php endif; ?>
  <?php if (shop_paused()): ?><p class="flash bad" role="alert">Orders are paused right now</p><?php endif; ?>
  <?php if (!$quote['lines']): ?><p>Your cart is empty. <a href="/shop">Shop seedlings</a></p><?php else: ?>
  <?php if ($quote['lines']): ?>
  <details class="card summary summary-mobile">
    <summary>Order summary — <?= inr((int) $quote['total']) ?></summary>
    <?php foreach ($quote['lines'] as $line): ?>
      <p><span><?= (int) $line['qty'] ?> × <?= e((string) $line['product']['name']) ?></span><span><?= inr((int) $line['line']) ?></span></p>
    <?php endforeach; ?>
    <p class="summary-total"><span>Total</span><span><?= inr((int) $quote['total']) ?></span></p>
  </details>
  <?php endif; ?>
  <div class="checkout-layout">
  <div>
    <form method="post" class="checkout-form">
      <?= csrf_field() ?>
      <?= opened_fields() ?>
      <input type="hidden" name="checkout_token" value="<?= e(checkout_token()) ?>">
      <fieldset>
        <legend><span class="step-no">1</span> Your details</legend>
        <label>Name <input name="name" required value="<?= e($posted['name']) ?>" autocomplete="name" aria-invalid="<?= !empty($errors['name']) ? 'true' : 'false' ?>"></label>
        <?php if (!empty($errors['name'])): ?><p class="field-error" role="alert"><?= e($errors['name']) ?></p><?php endif; ?>
        <label>Mobile <input name="phone" type="tel" required value="<?= e($posted['phone']) ?>" inputmode="tel" autocomplete="tel-national" maxlength="14" aria-invalid="<?= !empty($errors['phone']) ? 'true' : 'false' ?>"<?= !empty($errors['phone']) ? ' aria-describedby="err-phone"' : '' ?>></label>
        <p class="hint">We call or WhatsApp you to confirm.</p>
        <?php if (!empty($errors['phone'])): ?><p class="field-error" id="err-phone" role="alert"><?= e($errors['phone']) ?></p><?php endif; ?>
        <label>Email <?php if (!$requireEmail): ?><span class="muted">(optional)</span><?php endif; ?>
          <input name="email" type="email" <?= $requireEmail ? 'required' : '' ?> value="<?= e($posted['email']) ?>" autocomplete="email" aria-invalid="<?= !empty($errors['email']) ? 'true' : 'false' ?>"></label>
        <?php if (!empty($errors['email'])): ?><p class="field-error" role="alert"><?= e($errors['email']) ?></p><?php endif; ?>
      </fieldset>
      <fieldset>
        <legend><span class="step-no">2</span> Delivery address</legend>
        <p class="hint"><?= e(setting('delivery_area_text', 'We deliver with our own nursery vehicles.')) ?></p>
        <label>Address <textarea name="address" required autocomplete="street-address"><?= e($posted['address']) ?></textarea></label>
        <?php if (!empty($errors['address'])): ?><p class="field-error" role="alert"><?= e($errors['address']) ?></p><?php endif; ?>
        <label>City <input name="city" required value="<?= e($posted['city']) ?>" autocomplete="address-level2"></label>
        <?php if (!empty($errors['city'])): ?><p class="field-error" role="alert"><?= e($errors['city']) ?></p><?php endif; ?>
        <label>State <input name="state" required value="<?= e($posted['state']) ?>" autocomplete="address-level1"></label>
        <?php if (!empty($errors['state'])): ?><p class="field-error" role="alert"><?= e($errors['state']) ?></p><?php endif; ?>
        <label>PIN <input name="postal" required value="<?= e($posted['postal']) ?>" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="postal-code" aria-invalid="<?= !empty($errors['postal']) ? 'true' : 'false' ?>"></label>
        <p class="hint">We deliver to selected Pune PIN codes.</p>
        <?php if (!empty($errors['postal'])): ?><p class="field-error" role="alert"><?= e($errors['postal']) ?></p><?php endif; ?>
        <?php if ($outside && $help !== ''): ?><p><a href="<?= e($help) ?>" target="_blank" rel="noopener">Call or WhatsApp us for bulk or special delivery</a></p><?php endif; ?>
      </fieldset>
      <fieldset>
        <legend><span class="step-no">3</span> Payment</legend>
        <label class="pay-card"><input type="radio" name="method" value="upi" <?= $posted['method'] === 'upi' ? 'checked' : '' ?> <?= $upiOn ? '' : 'disabled' ?>><span><strong>Pay by UPI</strong><br><span class="hint">Scan the QR or use your UPI app</span></span></label>
        <?php if ($upiOn): ?>
          <p class="hint">UPI ID <?= e(setting('upi_id', '')) ?> · <?= e(setting('upi_payee_name', 'Precision Agritech Private Limited')) ?>. The exact-amount QR appears on the order page after you place the order.</p>
        <?php else: ?>
          <p class="hint">UPI is not configured. Please choose another method or contact us.</p>
        <?php endif; ?>
        <?php if ($bankOn): ?><label class="pay-card"><input type="radio" name="method" value="bank_transfer" <?= $posted['method'] === 'bank_transfer' ? 'checked' : '' ?>><span><strong>Bank transfer</strong><br><span class="hint">NEFT or IMPS</span></span></label><?php endif; ?>
        <?php if ($codOn): ?><label class="pay-card"><input type="radio" name="method" value="cod" <?= $posted['method'] === 'cod' ? 'checked' : '' ?>><span><strong>Cash on delivery</strong><br><span class="hint">Pay the driver</span></span></label><?php endif; ?>
        <?php if (!empty($errors['method'])): ?><p class="field-error" role="alert"><?= e($errors['method']) ?></p><?php endif; ?>
      </fieldset>
      <label>Coupon <input name="coupon" value="<?= e($posted['coupon']) ?>" autocomplete="off"></label>
      <?php if (!empty($errors['coupon'])): ?><p class="field-error" role="alert"><?= e($errors['coupon']) ?></p><?php endif; ?>
      <button class="btn btn-block" type="submit" <?= (shop_paused() || !empty($quote['blocked']) || $outside) ? 'disabled' : '' ?>>Place order</button>
      <p class="consent">We use your details only to deliver your order. <a href="/privacy">Privacy Policy</a></p>
    </form>
  </div>
  <aside class="card summary summary-desk">
    <h2>Your trays</h2>
    <?php foreach ($quote['lines'] as $line): ?>
      <p><span><?= (int) $line['qty'] ?> × <?= e((string) $line['product']['name']) ?></span><span><?= inr((int) $line['line']) ?></span></p>
    <?php endforeach; ?>
    <p><span>Shipping</span><span><?= inr((int) $quote['shipping']) ?></span></p>
    <?php if ($seasonalShown > 0): ?><p><span><?= e((string) ($quote['seasonal']['label'] ?? 'Bulk discount')) ?></span><span>−<?= inr($seasonalShown) ?></span></p><p class="muted">Packing and transport are not included in the discount.</p><?php endif; ?>
    <?php $progress = discount_progress_text($quote['seasonal'] ?? []); if ($seasonalShown < 1 && $progress !== ''): ?><p class="muted"><?= e($progress) ?></p><?php endif; ?>
    <?php $priceNote = trim(setting('prices_dispatch_note', '')); if ($priceNote !== ''): ?><p class="muted"><?= e($priceNote) ?></p><?php endif; ?>
    <p class="summary-total"><span>Total</span><span><?= inr((int) $quote['total']) ?></span></p>
  </aside>
  </div>
  <?php endif; ?>
</div></section>
<?php render_footer(); ?>
