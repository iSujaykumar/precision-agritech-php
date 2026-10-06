<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();

$token = (string) ($_GET['token'] ?? '');
$byNumber = (bool) preg_match('/^PA[A-Z0-9]{6,12}$/', $token);
if ($byNumber) {
    if (lookup_is_limited()) {
        http_response_code(429);
        render_header('Order lookup paused');
        echo '<section class="section"><div class="wrap"><h1>Lookups are paused for a few minutes.</h1></div></section>';
        render_footer();
        exit;
    }
    note_lookup();
}
$stmt = db()->prepare('SELECT * FROM orders WHERE lookup_token = ? OR order_number = ? LIMIT 1');
$stmt->execute([$token, $token]);
$order = $stmt->fetch();
if (!$order) {
    if (!$byNumber) {
        note_lookup();
    }
    http_response_code(404);
    render_header('Order not found');
    echo '<section class="section"><div class="wrap"><h1>Order not found.</h1><p><a href="/track">Try the order number again</a></p></div></section>';
    render_footer();
    exit;
}
$private = hash_equals((string) $order['lookup_token'], $token);
if (!$private && !$byNumber) {
    note_lookup();
}
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: private, no-store');
if ($private && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        if (in_array($order['status'], ['placed', 'payment_pending', 'payment_confirmed', 'preparing'], true)) {
            db()->prepare('UPDATE orders SET cancel_requested = 1 WHERE id = ?')->execute([(int) $order['id']]);
            db()->prepare("INSERT INTO order_events (order_id, status, note, public_note) VALUES (?, ?, 'Customer asked to cancel this order.', 0)")
                ->execute([(int) $order['id'], $order['status']]);
            global $config;
            send_mail((string) ($config['mail_from'] ?? 'info@precisionagritech.in'), 'Cancel request ' . $order['order_number'], 'The customer asked to cancel ' . $order['order_number'] . ' before dispatch.');
            flash('success', 'The nursery has your cancel request. It is not cancelled until the desk confirms.');
        }
    } catch (Throwable $err) {
        flash('error', safe_error($err, 'That request could not be saved.'));
    }
    header('Location: /order/' . $order['lookup_token']);
    exit;
}
render_header('Order ' . $order['order_number']);
?>
<section class="section"><div class="wrap narrow order-sheet">
  <h1>Order <?= e($order['order_number']) ?></h1>
  <p>Status: <?= e(status_label((string) $order['status'])) ?> · Payment: <?= e(status_label((string) $order['payment_status'])) ?></p>
  <?php if ($private): ?>
    <?php
    $items = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
    $items->execute([(int) $order['id']]);
    $events = db()->prepare('SELECT status, note, created_at FROM order_events WHERE order_id = ? AND public_note = 1 ORDER BY id');
    $events->execute([(int) $order['id']]);
    ?>
    <p>Placed <?= e(fmt_dt((string) $order['created_at'])) ?> · <?= e(status_label((string) $order['payment_method'])) ?></p>
    <ul>
      <?php foreach ($items as $item): ?>
        <li><?= (int) $item['quantity'] ?> × <?= e($item['product_name']) ?> · <?= inr((int) $item['line_total_inr']) ?></li>
      <?php endforeach; ?>
    </ul>
    <p>Trays <?= inr((int) $order['subtotal_inr']) ?><br>Discount <?= inr((int) $order['discount_inr']) ?><br>Shipping <?= (int) $order['shipping_inr'] === 0 ? 'Free' : inr((int) $order['shipping_inr']) ?><br><strong>Total <?= inr((int) $order['total_inr']) ?></strong></p>
    <p><?= e($order['customer_name']) ?><br><?= e($order['customer_phone']) ?><br><?= e($order['address_line']) ?><br><?= e($order['city']) ?>, <?= e($order['state_name']) ?> <?= e($order['postal_code']) ?></p>
    <?php if ($order['payment_method'] === 'bank_transfer' && $order['payment_status'] !== 'paid' && bank_ready()): ?>
      <div class="flash info">
        <h2>How to pay</h2>
        <?= nl2br(e(bank_instructions((string) $order['order_number'], (int) $order['total_inr']))) ?>
      </div>
    <?php endif; ?>
    <h2>Updates</h2>
    <?php foreach ($events as $event): ?>
      <p><strong><?= e(status_label((string) $event['status'])) ?></strong> · <?= e(fmt_dt((string) $event['created_at'])) ?><br><?= e((string) $event['note']) ?></p>
    <?php endforeach; ?>
    <?php if (in_array($order['status'], ['placed', 'payment_pending', 'payment_confirmed', 'preparing'], true)): ?>
      <form method="post"><?= csrf_field() ?><button class="btn" type="submit">Ask the nursery to cancel</button></form>
    <?php endif; ?>
    <p><a href="tel:+919011975959">Call us about this order</a></p>
    <p class="muted">Keep this link. The order number alone does not show the address.</p>
  <?php else: ?>
    <p class="muted">The order number shows progress only. The address and the tray list stay on the private link.</p>
  <?php endif; ?>
</div></section>
<?php render_footer(); ?>
