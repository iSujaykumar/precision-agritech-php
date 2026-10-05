<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();

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
$items = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
$items->execute([(int) $order['id']]);
$events = db()->prepare('SELECT * FROM order_events WHERE order_id = ? ORDER BY id');
$events->execute([(int) $order['id']]);
render_header('Order ' . $order['order_number']);
?>
<section class="section"><div class="wrap narrow">
  <h1>Order <?= e($order['order_number']) ?></h1>
  <p>Status: <?= e($order['status']) ?> · Payment: <?= e($order['payment_status']) ?></p>
  <p>Total <?= inr((int) $order['total_inr']) ?></p>
  <ul>
    <?php foreach ($items as $item): ?>
      <li><?= (int) $item['quantity'] ?> × <?= e($item['product_name']) ?> · <?= inr((int) $item['line_total_inr']) ?></li>
    <?php endforeach; ?>
  </ul>
  <?php if ($private): ?>
    <p><?= e($order['customer_name']) ?><br><?= e($order['address_line']) ?><br><?= e($order['city']) ?> <?= e($order['postal_code']) ?></p>
    <p class="muted">Keep this link. The order number alone does not show the address.</p>
  <?php else: ?>
    <p class="muted">The address is only on the private link sent after checkout.</p>
  <?php endif; ?>
  <h2>Updates</h2>
  <?php foreach ($events as $event): ?>
    <p><strong><?= e($event['status']) ?></strong> · <?= e($event['note']) ?></p>
  <?php endforeach; ?>
</div></section>
<?php render_footer(); ?>
