<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();
$user = require_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    db()->prepare('INSERT INTO addresses (user_id, line, city, state_name, postal_code) VALUES (?, ?, ?, ?, ?)')
        ->execute([(int) $user['id'], trim((string) $_POST['line']), trim((string) $_POST['city']), trim((string) $_POST['state']), trim((string) $_POST['postal'])]);
    header('Location: /account');
    exit;
}
$orders = db()->prepare('SELECT order_number, lookup_token, status, payment_status, total_inr, created_at FROM orders WHERE user_id = ? ORDER BY id DESC');
$orders->execute([(int) $user['id']]);
$addresses = db()->prepare('SELECT * FROM addresses WHERE user_id = ? ORDER BY id DESC');
$addresses->execute([(int) $user['id']]);
render_header('Account | Precision Agritech');
?>
<section class="section"><div class="wrap">
  <h1><?= e($user['name']) ?></h1>
  <p><?= e($user['email']) ?> · <a href="/logout">Sign out</a><?php if ($user['role'] === 'admin'): ?> · <a href="/admin">Nursery desk</a><?php endif; ?></p>
  <h2>Orders</h2>
  <?php foreach ($orders as $order): ?>
    <p><a href="/order/<?= e($order['lookup_token']) ?>"><?= e($order['order_number']) ?></a> · <?= e($order['status']) ?> · <?= inr((int) $order['total_inr']) ?></p>
  <?php endforeach; ?>
  <h2>Addresses</h2>
  <?php foreach ($addresses as $address): ?>
    <p><?= e($address['line']) ?>, <?= e($address['city']) ?> <?= e($address['postal_code']) ?></p>
  <?php endforeach; ?>
  <form method="post" class="narrow">
    <?= csrf_field() ?>
    <label>Address <textarea name="line" required></textarea></label>
    <label>City <input name="city" required></label>
    <label>State <input name="state" required></label>
    <label>PIN <input name="postal" required pattern="[0-9]{6}"></label>
    <button class="btn">Save address</button>
  </form>
</div></section>
<?php render_footer(); ?>
