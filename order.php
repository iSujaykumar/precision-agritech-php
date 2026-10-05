<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();

if (lookup_is_limited()) {
    http_response_code(429);
    render_header('Order lookup paused');
    echo '<section class="section"><div class="wrap"><h1>Lookups are paused for a few minutes.</h1></div></section>';
    render_footer();
    exit;
}
note_lookup();
$token = (string) ($_GET['token'] ?? '');
$stmt = db()->prepare('SELECT * FROM orders WHERE lookup_token = ? OR order_number = ? LIMIT 1');
$stmt->execute([$token, $token]);
$order = $stmt->fetch();
if (!$order) {
    http_response_code(404);
    render_header('Order not found');
    echo '<section class="section"><div class="wrap"><h1>Order not found.</h1></div></section>';
    render_footer();
    exit;
}
$private = hash_equals((string) $order['lookup_token'], $token);
render_header('Order ' . $order['order_number']);
?>
<section class="section"><div class="wrap narrow">
  <h1>Order <?= e($order['order_number']) ?></h1>
  <p>Status: <?= e($order['status']) ?> · Payment: <?= e($order['payment_status']) ?></p>
  <?php if ($private): ?>
    <?php
    $items = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
    $items->execute([(int) $order['id']]);
    $events = db()->prepare('SELECT status, created_at FROM order_events WHERE order_id = ? ORDER BY id');
    $events->execute([(int) $order['id']]);
    ?>
    <p>Total <?= inr((int) $order['total_inr']) ?></p>
    <ul>
      <?php foreach ($items as $item): ?>
        <li><?= (int) $item['quantity'] ?> × <?= e($item['product_name']) ?> · <?= inr((int) $item['line_total_inr']) ?></li>
      <?php endforeach; ?>
    </ul>
    <p><?= e($order['customer_name']) ?><br><?= e($order['address_line']) ?><br><?= e($order['city']) ?> <?= e($order['postal_code']) ?></p>
    <h2>Updates</h2>
    <?php foreach ($events as $event): ?>
      <p><strong><?= e($event['status']) ?></strong></p>
    <?php endforeach; ?>
  <?php else: ?>
    <p class="muted">The order number shows progress only. The address and the tray list stay on the private link.</p>
  <?php endif; ?>
</div></section>
<?php render_footer(); ?>
