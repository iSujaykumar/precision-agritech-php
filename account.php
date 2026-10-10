<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();
$user = require_user();
if (($user['role'] ?? '') === 'admin') {
    header('Location: /admin');
    exit;
}
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $line = trim((string) ($_POST['line'] ?? ''));
        $city = trim((string) ($_POST['city'] ?? ''));
        $state = trim((string) ($_POST['state'] ?? ''));
        $postal = trim((string) ($_POST['postal'] ?? ''));
        if (strlen($line) < 8 || strlen($city) < 2 || strlen($state) < 2 || !preg_match('/^[0-9]{6}$/', $postal)) {
            throw new RuntimeException('Check the address, state and 6-digit PIN code.');
        }
        db()->prepare('INSERT INTO addresses (user_id, line, city, state_name, postal_code) VALUES (?, ?, ?, ?, ?)')
            ->execute([(int) $user['id'], $line, $city, $state, $postal]);
        header('Location: /account');
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'That change could not be saved.');
    }
}
$fresh = db()->prepare('SELECT phone FROM users WHERE id = ?');
$fresh->execute([(int) $user['id']]);
$phone = (string) $fresh->fetchColumn();
$orders = db()->prepare('SELECT order_number, lookup_token, status, payment_status, total_inr, created_at FROM orders WHERE user_id = ? ORDER BY id DESC');
$orders->execute([(int) $user['id']]);
$orderRows = $orders->fetchAll();
$addresses = db()->prepare('SELECT * FROM addresses WHERE user_id = ? ORDER BY id DESC');
$addresses->execute([(int) $user['id']]);
$addressRows = $addresses->fetchAll();
render_header('Account | Precision Agritech');
?>
<section class="section"><div class="wrap narrow">
  <h1><?= e($user['name']) ?></h1>
  <p><?= e($user['email']) ?></p>
  <p>Mobile <?= $phone !== '' ? e(mask_phone($phone)) : 'not on file' ?></p>
  <form method="post" action="/logout" class="inline">
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="/">
    <button class="btn" type="submit">Sign out</button>
  </form>
  <?php if ($error): ?><p class="flash" role="alert"><?= e($error) ?></p><?php endif; ?>
  <h2>Orders</h2>
  <?php if (!$orderRows): ?><p class="muted">No orders yet.</p><?php endif; ?>
  <?php foreach ($orderRows as $order): ?>
    <p><a href="/order/<?= e($order['lookup_token']) ?>"><?= e($order['order_number']) ?></a> · <?= e($order['status']) ?> · <?= inr((int) $order['total_inr']) ?></p>
  <?php endforeach; ?>
  <h2>Addresses</h2>
  <?php if (!$addressRows): ?><p class="muted">No saved addresses yet.</p><?php endif; ?>
  <?php foreach ($addressRows as $address): ?>
    <p><?= e($address['line']) ?>, <?= e($address['city']) ?>, <?= e($address['state_name']) ?> <?= e($address['postal_code']) ?></p>
  <?php endforeach; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Address <textarea name="line" required></textarea></label>
    <label>City <input name="city" required></label>
    <label>State <input name="state" required value="Maharashtra"></label>
    <label>PIN <input name="postal" required pattern="[0-9]{6}" inputmode="numeric" maxlength="6"></label>
    <button class="btn" type="submit">Save address</button>
  </form>
</div></section>
<?php render_footer(); ?>
