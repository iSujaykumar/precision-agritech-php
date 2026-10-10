<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();

$token = (string) ($_GET['token'] ?? '');
$peek = db()->prepare('SELECT id, lookup_token, order_number FROM orders WHERE lookup_token = ? OR order_number = ? LIMIT 1');
$peek->execute([$token, $token]);
$found = $peek->fetch();
$private = $found && hash_equals((string) $found['lookup_token'], $token);
if (!$private) {
    if (lookup_is_limited()) {
        http_response_code(429);
        render_header('Order lookup paused');
        echo '<section class="section"><div class="wrap"><h1>Lookups are paused for a few minutes.</h1></div></section>';
        render_footer();
        exit;
    }
    note_lookup();
}
if (!$found) {
    http_response_code(404);
    render_header('Order not found');
    echo '<section class="section"><div class="wrap"><h1>Order not found.</h1></div></section>';
    render_footer();
    exit;
}
$full = db()->prepare('SELECT * FROM orders WHERE id = ?');
$full->execute([(int) $found['id']]);
$order = $full->fetch();
$error = null;
$notice = null;
if ($private && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'paid') {
            if (rate_limited('paid:' . $order['id'], 5, 60)) {
                throw new RuntimeException('Too many updates. Wait a little and try again.');
            }
            note_rate('paid:' . $order['id']);
            if (!in_array($order['payment_method'], ['upi', 'bank_transfer'], true)) {
                throw new RuntimeException('This order does not need a payment reference.');
            }
            $ref = preg_replace('/\s+/', '', (string) ($_POST['payment_ref'] ?? '')) ?? '';
            if (!preg_match('/^[A-Za-z0-9]{12}$/', $ref)) {
                throw new RuntimeException('Enter the 12-character UPI or bank reference.');
            }
            $note = clean_text((string) ($_POST['payment_note'] ?? ''), 255);
            db()->prepare("UPDATE orders SET payment_ref = ?, payment_note = ?, status = 'payment_submitted' WHERE id = ? AND status IN ('payment_pending','placed')")
                ->execute([$ref, $note, (int) $order['id']]);
            db()->prepare("INSERT INTO order_events (order_id, status, note) VALUES (?, 'payment_submitted', 'Customer sent a payment reference')")
                ->execute([(int) $order['id']]);
            mail_staff('Payment to check ' . $order['order_number'], 'Order ' . $order['order_number'] . ' reference ' . $ref);
            $notice = 'Thank you. The nursery will check that payment.';
        } elseif ($action === 'claim') {
            if ($order['status'] !== 'delivered') {
                throw new RuntimeException('You can report a problem after the trays are delivered.');
            }
            $hours = max(1, (int) setting('claim_hours', '48'));
            $delivered = db()->prepare("SELECT created_at FROM order_events WHERE order_id = ? AND status = 'delivered' ORDER BY id DESC LIMIT 1");
            $delivered->execute([(int) $order['id']]);
            $at = strtotime((string) ($delivered->fetchColumn() ?: $order['created_at']));
            if ($at && time() - $at > $hours * 3600) {
                throw new RuntimeException('The report window for this order has closed. Please call the nursery.');
            }
            if (rate_limited('claim:' . $order['id'], 3, 1440)) {
                throw new RuntimeException('A report is already with the nursery.');
            }
            note_rate('claim:' . $order['id']);
            $reason = clean_text((string) ($_POST['reason'] ?? ''), 500);
            if (strlen($reason) < 8) {
                throw new RuntimeException('Tell us what happened.');
            }
            $photos = [];
            $files = $_FILES['photos'] ?? null;
            if (is_array($files) && isset($files['name']) && is_array($files['name'])) {
                $count = min(3, count($files['name']));
                for ($i = 0; $i < $count; $i++) {
                    if ((int) $files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    $one = [
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i],
                    ];
                    $photos[] = save_upload_webp($one, '/uploads/private/claims', 200, 200);
                }
            }
            db()->prepare('INSERT INTO claims (order_id, reason, photos, status) VALUES (?, ?, ?, ?)')
                ->execute([(int) $order['id'], $reason, implode('|', $photos), 'new']);
            mail_staff('Problem reported ' . $order['order_number'], $reason);
            $notice = 'The nursery has your report.';
        }
        $full->execute([(int) $order['id']]);
        $order = $full->fetch();
    } catch (Throwable $err) {
        $error = safe_error($err, 'That could not be saved.');
    }
}
render_header('Order ' . $order['order_number']);
if (!$private) {
    echo '<section class="section"><div class="wrap narrow"><h1>Order ' . e($order['order_number']) . '</h1>';
    echo '<p>Status: ' . e(order_label((string) $order['status'])) . '</p>';
    echo '<p>Payment: ' . e((string) $order['payment_status']) . '</p>';
    echo '<p class="muted">The order number shows progress only. The address and the tray list stay on the private link.</p></div></section>';
    render_footer();
    exit;
}
$items = db()->prepare('SELECT oi.*, p.image_url, p.slug FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?');
$items->execute([(int) $order['id']]);
$itemRows = $items->fetchAll();
$driver = null;
if (!empty($order['driver_id'])) {
    $d = db()->prepare('SELECT name, phone, vehicle_no FROM drivers WHERE id = ?');
    $d->execute([(int) $order['driver_id']]);
    $driver = $d->fetch() ?: null;
}
$bank = bank_details();
$qr = trim(setting('upi_qr_image', ''));
$help = wa_href('Hello Precision Agritech, I need help with order ' . $order['order_number']);
$showPay = in_array($order['payment_method'], ['upi', 'bank_transfer'], true) && $order['payment_status'] !== 'paid' && $order['status'] !== 'cancelled';
?>
<section class="section"><div class="wrap narrow">
  <h1>Order placed</h1>
  <p class="order-no"><?= e($order['order_number']) ?></p>
  <?php if ($notice): ?><p class="flash ok" role="status"><?= e($notice) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="flash bad" role="alert"><?= e($error) ?></p><?php endif; ?>
  <p><?= e(order_label((string) $order['status'])) ?> · <?= e((string) $order['payment_status']) ?></p>
  <?php if (!empty($order['reserved_until']) && $order['status'] !== 'cancelled' && $order['payment_status'] !== 'paid'): ?>
    <p>Reserved until <?= e(fmt_when((string) $order['reserved_until'])) ?></p>
  <?php endif; ?>
  <ul class="order-items">
    <?php foreach ($itemRows as $item): ?>
      <li>
        <img src="<?= e(product_image((string) ($item['image_url'] ?? ''))) ?>" alt="" width="96" height="72">
        <span><?= (int) $item['quantity'] ?> × <?= e($item['product_name']) ?></span>
        <strong><?= inr((int) $item['line_total_inr']) ?></strong>
      </li>
    <?php endforeach; ?>
  </ul>
  <p class="summary-total"><span>Total</span><span><?= inr((int) $order['total_inr']) ?></span></p>
  <?php if (trim(setting('gstin', '')) === ''): ?>
    <p>Bill of supply / delivery challan. GST is not charged.</p>
  <?php else: ?>
    <p>Invoice · GSTIN <?= e(setting('gstin', '')) ?><?php if (setting('hsn_code', '') !== ''): ?> · HSN <?= e(setting('hsn_code', '')) ?><?php endif; ?></p>
  <?php endif; ?>
  <?php if ($showPay): ?>
  <section class="card pay-box">
    <h2>Pay <?= inr((int) $order['total_inr']) ?></h2>
    <p>Use <?= e($order['order_number']) ?> as the payment reference.</p>
    <?php if ($qr !== ''): ?><img class="qr" src="<?= e($qr) ?>" alt="UPI QR code for Precision Agritech" width="220" height="220"><?php endif; ?>
    <p>UPI ID <strong id="upi-id"><?= e($bank['upi']) ?></strong>
      <button class="btn" type="button" data-copy="<?= e($bank['upi']) ?>">Copy</button></p>
    <p class="upi-app"><a class="btn" href="<?= e(upi_intent((string) $order['order_number'], (int) $order['total_inr'])) ?>">Pay with UPI app</a></p>
    <?php if ($bank['number'] !== ''): ?>
      <h3>Bank transfer</h3>
      <p><?= e($bank['name']) ?><br><?= e($bank['bank']) ?><br><?= e($bank['number']) ?><br>IFSC <?= e($bank['ifsc']) ?></p>
    <?php endif; ?>
    <p>Your trays are reserved until <?= e(fmt_when((string) $order['reserved_until'])) ?>. We confirm your order after the payment reaches our bank account.</p>
    <?php if (empty($order['payment_ref'])): ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="paid">
      <label>UPI reference or UTR <input name="payment_ref" inputmode="text" minlength="12" maxlength="12" required pattern="[A-Za-z0-9]{12}"></label>
      <label>Note <input name="payment_note" maxlength="255"></label>
      <button class="btn" type="submit">I have paid</button>
    </form>
    <?php else: ?>
      <p>Reference received: <?= e((string) $order['payment_ref']) ?>. The nursery is checking it.</p>
    <?php endif; ?>
  </section>
  <?php endif; ?>
  <?php if ($order['payment_method'] === 'cod'): ?>
    <p>We will call or WhatsApp you to confirm before dispatch. Pay the driver when the trays arrive.</p>
  <?php endif; ?>
  <?php if ($driver && $order['status'] === 'out_for_delivery'): ?>
    <p>Out for delivery with <?= e(explode(' ', (string) $driver['name'])[0]) ?>, vehicle <?= e((string) $driver['vehicle_no']) ?>.
      Nursery phone <a href="tel:+91<?= e(shop_phone_digits()) ?>"><?= e(shop_phone_digits()) ?></a>
      <?php if (setting('show_driver_phone', '0') === '1'): ?> · Driver <?= e((string) $driver['phone']) ?><?php endif; ?>
    </p>
  <?php endif; ?>
  <p>
    <a class="btn" href="/track">Track order</a>
    <?php if ($help !== ''): ?><a class="btn" href="<?= e($help) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
    <button class="text-link" type="button" data-print="1">Print</button>
  </p>
  <?php if ($order['status'] === 'delivered'): ?>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="claim">
    <h2>Report a problem</h2>
    <label>What happened <textarea name="reason" required maxlength="500"></textarea></label>
    <label>Photos (up to 3) <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple></label>
    <button class="btn" type="submit">Send report</button>
  </form>
  <?php endif; ?>
</div></section>
<?php render_footer(); ?>
