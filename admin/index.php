<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
connect_or_explain();
$admin = require_admin();
$notice = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $action = (string) ($_POST['action'] ?? '');
        $pdo = db();
        if ($action === 'status') {
            $id = (int) ($_POST['id'] ?? 0);
            $status = (string) ($_POST['status'] ?? '');
            $pdo->beginTransaction();
            $order = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            $order->execute([$id]);
            $row = $order->fetch();
            if (!$row) {
                throw new RuntimeException('Order not found.');
            }
            if ($status === 'cancelled' && $row['inventory_state'] === 'reserved') {
                $pdo->prepare('UPDATE products p JOIN order_items oi ON oi.product_id = p.id SET p.reserved_qty = GREATEST(0, p.reserved_qty - oi.quantity) WHERE oi.order_id = ?')->execute([$id]);
                $pdo->prepare("UPDATE orders SET inventory_state = 'released', status = 'cancelled' WHERE id = ?")->execute([$id]);
            } elseif (in_array($status, ['payment_confirmed', 'delivered'], true) && $row['inventory_state'] === 'reserved') {
                $pdo->prepare('UPDATE products p JOIN order_items oi ON oi.product_id = p.id SET p.reserved_qty = GREATEST(0, p.reserved_qty - oi.quantity), p.stock_qty = GREATEST(0, p.stock_qty - oi.quantity) WHERE oi.order_id = ?')->execute([$id]);
                $pdo->prepare("UPDATE orders SET inventory_state = 'sold', status = ?, payment_status = 'paid' WHERE id = ?")->execute([$status, $id]);
            } else {
                $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$status, $id]);
            }
            $pdo->prepare('INSERT INTO order_events (order_id, status, note) VALUES (?, ?, ?)')->execute([$id, $status, 'Nursery updated the order.']);
            $pdo->commit();
            $notice = 'Order updated.';
        } elseif ($action === 'stock') {
            $id = (int) ($_POST['id'] ?? 0);
            $change = (int) ($_POST['change'] ?? 0);
            $reason = trim((string) ($_POST['reason'] ?? ''));
            if (strlen($reason) < 3) {
                throw new RuntimeException('Write a reason for the stock change.');
            }
            $pdo->prepare('UPDATE products SET stock_qty = stock_qty + ? WHERE id = ? AND stock_qty + ? >= 0')->execute([$change, $id, $change]);
            $notice = 'Stock updated.';
        } elseif ($action === 'review') {
            $pdo->prepare('UPDATE reviews SET status = ? WHERE id = ?')->execute([(string) $_POST['status'], (int) $_POST['id']]);
            $notice = 'Review updated.';
        } elseif ($action === 'coupon') {
            $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
            $kind = (string) ($_POST['kind'] ?? 'fixed');
            $amount = (int) ($_POST['amount'] ?? 0);
            $min = (int) ($_POST['min_order'] ?? 0);
            if ($kind === 'percent' && $amount > 90) {
                throw new RuntimeException('A percent coupon cannot be more than 90.');
            }
            $pdo->prepare('INSERT INTO coupons (code, kind, amount, min_order_inr, active) VALUES (?, ?, ?, ?, 1)')->execute([$code, $kind, $amount, $min]);
            $notice = 'Coupon saved.';
        }
    } catch (Throwable $err) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $err->getMessage();
    }
}

$orders = db()->query('SELECT id, order_number, customer_name, status, payment_status, total_inr FROM orders ORDER BY id DESC LIMIT 50')->fetchAll();
$products = db()->query('SELECT id, name, stock_qty, reserved_qty FROM products ORDER BY name')->fetchAll();
$reviews = db()->query("SELECT r.id, r.author_name, r.rating, r.body, p.name FROM reviews r JOIN products p ON p.id = r.product_id WHERE r.status = 'pending'")->fetchAll();
render_header('Nursery desk');
?>
<section class="section"><div class="wrap">
  <h1>Nursery desk</h1>
  <p><?= e($admin['email']) ?></p>
  <?php if ($notice): ?><p class="flash"><?= e($notice) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="flash"><?= e($error) ?></p><?php endif; ?>
  <h2>Orders</h2>
  <table>
    <?php foreach ($orders as $order): ?>
      <tr>
        <td><?= e($order['order_number']) ?><br><?= e($order['customer_name']) ?></td>
        <td><?= e($order['status']) ?> · <?= e($order['payment_status']) ?><br><?= inr((int) $order['total_inr']) ?></td>
        <td>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="status">
            <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
            <select name="status">
              <?php foreach (['placed','payment_pending','payment_confirmed','preparing','dispatched','delivered','cancelled'] as $status): ?>
                <option <?= $order['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn">Save</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <h2>Stock</h2>
  <table>
    <?php foreach ($products as $product): ?>
      <tr>
        <td><?= e($product['name']) ?></td>
        <td><?= (int) $product['stock_qty'] ?> on hand, <?= (int) $product['reserved_qty'] ?> reserved</td>
        <td>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="stock">
            <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
            <input name="change" type="number" value="0">
            <input name="reason" placeholder="Reason" required>
            <button class="btn">Adjust</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <h2>Reviews waiting</h2>
  <?php foreach ($reviews as $review): ?>
    <p><?= e($review['name']) ?> · <?= e($review['author_name']) ?> · <?= (int) $review['rating'] ?>/5<br><?= e($review['body']) ?></p>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="review">
      <input type="hidden" name="id" value="<?= (int) $review['id'] ?>">
      <button class="btn" name="status" value="approved">Approve</button>
      <button class="btn" name="status" value="rejected">Reject</button>
    </form>
  <?php endforeach; ?>
  <h2>Coupon</h2>
  <form method="post" class="narrow">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="coupon">
    <input name="code" placeholder="Code" required>
    <select name="kind"><option value="fixed">Fixed rupees</option><option value="percent">Percent</option></select>
    <input name="amount" type="number" min="1" required>
    <input name="min_order" type="number" min="0" value="0">
    <button class="btn">Create coupon</button>
  </form>
</div></section>
<?php render_footer(); ?>
