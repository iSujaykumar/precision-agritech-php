<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
$error = null;
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $action = (string) ($_POST['action'] ?? 'status');
        if ($action === 'note') {
            $note = trim((string) ($_POST['internal_note'] ?? ''));
            db()->prepare('UPDATE orders SET internal_note = ? WHERE id = ?')->execute([substr($note, 0, 500), $id]);
            audit_log((int) $admin['id'], 'order_note', 'order', $id, null, $note);
            flash_set('Note saved');
        } else {
            $status = (string) ($_POST['status'] ?? '');
            $pdo = db();
            $pdo->beginTransaction();
            apply_order_status($pdo, $id, $status, (int) $admin['id']);
            $pdo->commit();
            if (in_array($status, ['dispatched', 'delivered'], true)) {
                $mailOrder = db()->prepare('SELECT * FROM orders WHERE id = ?');
                $mailOrder->execute([$id]);
                $sent = $mailOrder->fetch();
                if ($sent) {
                    mail_order_customer($sent, 'Your order is now ' . order_label($status) . '.');
                }
            }
            flash_set('Order updated');
        }
        header('Location: /admin/order-view?id=' . $id);
        exit;
    } catch (Throwable $err) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = safe_error($err, 'That change was not saved.');
    }
}
$order = db()->prepare('SELECT * FROM orders WHERE id = ?');
$order->execute([$id]);
$row = $order->fetch();
if (!$row) {
    admin_open('Order');
    echo '<p>Order not found.</p>';
    admin_close();
    exit;
}
$items = db()->prepare('SELECT oi.*, p.image_url FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?');
$items->execute([$id]);
$itemRows = $items->fetchAll();
$events = db()->prepare('SELECT status, note, created_at FROM order_events WHERE order_id = ? ORDER BY id');
$events->execute([$id]);
$coupon = db()->prepare('SELECT c.code, r.amount_inr FROM coupon_redemptions r JOIN coupons c ON c.id = r.coupon_id WHERE r.order_id = ? LIMIT 1');
$coupon->execute([$id]);
$couponRow = $coupon->fetch();
$phone = preg_replace('/\D+/', '', (string) $row['customer_phone']) ?? '';
if (strlen($phone) === 10) {
    $phone = '91' . $phone;
}
$wa = $phone !== '' ? 'https://wa.me/' . $phone . '?text=' . rawurlencode('Hello, this is Precision Agritech about order ' . $row['order_number'] . '.') : '';
admin_open('Order ' . $row['order_number']);
if ($error) {
    echo '<p class="flash bad" role="alert">' . e($error) . '</p>';
}
?>
<article class="packing">
  <p><?= e(order_label((string) $row['status'])) ?> · Payment <?= e((string) $row['payment_status']) ?></p>
  <p><?= e($row['customer_name']) ?> · <?= e((string) $row['customer_phone']) ?> · <?= e((string) $row['customer_email']) ?></p>
  <p><?= e($row['address_line']) ?>, <?= e($row['city']) ?> <?= e($row['state_name']) ?> <?= e($row['postal_code']) ?></p>
  <?php if ($couponRow): ?><p>Coupon <?= e($couponRow['code']) ?> · <?= inr((int) $couponRow['amount_inr']) ?> off</p><?php endif; ?>
  <div class="table-wrap"><table>
    <?php foreach ($itemRows as $item): ?>
      <tr>
        <td><img class="thumb" src="<?= e(product_image((string) ($item['image_url'] ?? ''))) ?>" alt=""></td>
        <td><?= e($item['product_name']) ?></td>
        <td><?= (int) $item['quantity'] ?></td>
        <td><?= inr((int) $item['line_total_inr']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <p>Subtotal <?= inr((int) $row['subtotal_inr']) ?> · Discount <?= inr((int) $row['discount_inr']) ?> · Shipping <?= inr((int) $row['shipping_inr']) ?> · Total <?= inr((int) $row['total_inr']) ?></p>
</article>
<h2>Timeline</h2>
<?php foreach ($events as $event): ?>
  <p><strong><?= e(order_label((string) $event['status'])) ?></strong> · <?= e((string) $event['created_at']) ?><br><?= e((string) $event['note']) ?></p>
<?php endforeach; ?>
<form method="post" class="narrow">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
  <input type="hidden" name="action" value="note">
  <label>Internal note <textarea name="internal_note"><?= e((string) ($row['internal_note'] ?? '')) ?></textarea></label>
  <button class="btn" type="submit">Save note</button>
</form>
<form method="post" class="narrow">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
  <label>Next status <select name="status">
    <?php foreach (['payment_pending', 'payment_confirmed', 'preparing', 'dispatched', 'delivered', 'cancelled'] as $status): ?>
      <option value="<?= e($status) ?>"><?= e(order_label($status)) ?></option>
    <?php endforeach; ?>
  </select></label>
  <button class="btn" type="submit" data-confirm="Update this order?">Update</button>
  <button class="btn" type="button" data-print="1">Print packing slip</button>
</form>
<?php if ($wa !== ''): ?><p><a class="btn" href="<?= e($wa) ?>" target="_blank" rel="noopener">Message customer on WhatsApp</a></p><?php endif; ?>
<?php admin_close(); ?>
