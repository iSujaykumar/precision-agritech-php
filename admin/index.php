<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
admin_boot();
admin_open('Nursery desk');
$today = (int) db()->query("SELECT COUNT(*) FROM orders WHERE created_at >= CURDATE()")->fetchColumn();
$month = (int) db()->query("SELECT COALESCE(SUM(total_inr),0) FROM orders WHERE payment_status = 'paid' AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetchColumn();
$waiting = (int) db()->query("SELECT COUNT(*) FROM orders WHERE status IN ('placed','payment_pending')")->fetchColumn();
$unpaid = (int) db()->query("SELECT COUNT(*) FROM orders WHERE payment_status IN ('unpaid','pending') AND status <> 'cancelled'")->fetchColumn();
$paid = (int) db()->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'paid'")->fetchColumn();
$low = (int) db()->query('SELECT COUNT(*) FROM products WHERE active = 1 AND stock_qty - reserved_qty <= 5')->fetchColumn();
$messages = (int) db()->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'")->fetchColumn();
$wholesale = (int) db()->query("SELECT COUNT(*) FROM wholesale_requests WHERE status = 'new'")->fetchColumn();
$pendingReviews = (int) db()->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending'")->fetchColumn();
$lowRows = db()->query('SELECT id, name, stock_qty, reserved_qty FROM products WHERE active = 1 AND stock_qty - reserved_qty <= 5 ORDER BY (stock_qty - reserved_qty), name LIMIT 8')->fetchAll();
$recent = db()->query('SELECT id, order_number, customer_name, total_inr, status FROM orders ORDER BY id DESC LIMIT 5')->fetchAll();
?>
<div class="desk-cards">
  <a class="card card-body" href="/admin/orders"><strong><?= $today ?></strong><br><span class="muted">Today's orders</span></a>
  <a class="card card-body" href="/admin/orders?pay=paid"><strong><?= inr($month) ?></strong><br><span class="muted">This month's paid sales</span></a>
  <a class="card card-body" href="/admin/orders?status=placed"><strong><?= $waiting ?></strong><br><span class="muted">Orders waiting</span></a>
  <a class="card card-body" href="/admin/orders?pay=unpaid"><strong><?= $unpaid ?></strong><br><span class="muted">Unpaid or pending</span></a>
  <a class="card card-body" href="/admin/orders?pay=paid"><strong><?= $paid ?></strong><br><span class="muted">Paid orders</span></a>
  <a class="card card-body" href="/admin/inventory"><strong><?= $low ?></strong><br><span class="muted">Low stock</span></a>
  <a class="card card-body" href="/admin/messages"><strong><?= $messages ?></strong><br><span class="muted">New messages</span></a>
  <a class="card card-body" href="/admin/wholesale"><strong><?= $wholesale ?></strong><br><span class="muted">New wholesale requests</span></a>
  <a class="card card-body" href="/admin/reviews?status=pending"><strong><?= $pendingReviews ?></strong><br><span class="muted">Pending reviews</span></a>
</div>
<h2>Low stock</h2>
<?php if (!$lowRows): ?><p class="muted">No trays are down to 5 or fewer.</p><?php endif; ?>
<div class="table-wrap"><table>
<?php foreach ($lowRows as $row): $free = (int) $row['stock_qty'] - (int) $row['reserved_qty']; ?>
  <tr><td><a href="/admin/inventory"><?= e($row['name']) ?></a></td><td><?= $free ?> free</td></tr>
<?php endforeach; ?>
</table></div>
<h2>Last orders</h2>
<div class="table-wrap"><table>
  <tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th></tr>
<?php foreach ($recent as $row): ?>
  <tr>
    <td><a href="/admin/order-view?id=<?= (int) $row['id'] ?>"><?= e($row['order_number']) ?></a></td>
    <td><?= e($row['customer_name']) ?></td>
    <td><?= inr((int) $row['total_inr']) ?></td>
    <td><?= e(order_label((string) $row['status'])) ?></td>
  </tr>
<?php endforeach; ?>
</table></div>
<?php admin_close(); ?>
