<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
expire_reservations();
admin_open('Nursery desk');
$waiting = (int) db()->query("SELECT COUNT(*) FROM orders WHERE status IN ('placed','payment_pending','payment_confirmed','preparing')")->fetchColumn();
$today = (int) db()->query("SELECT COALESCE(SUM(total_inr),0) FROM orders WHERE status <> 'cancelled' AND created_at >= CURDATE()")->fetchColumn();
$week = (int) db()->query("SELECT COALESCE(SUM(total_inr),0) FROM orders WHERE status <> 'cancelled' AND created_at >= (NOW() - INTERVAL 7 DAY)")->fetchColumn();
$low = db()->query('SELECT name, stock_qty, reserved_qty FROM products WHERE active = 1 AND stock_qty - reserved_qty <= 5 ORDER BY stock_qty - reserved_qty, name LIMIT 8')->fetchAll();
$old = db()->query("SELECT id, order_number, status, created_at FROM orders WHERE status IN ('placed','payment_pending') AND created_at < (NOW() - INTERVAL 24 HOUR) ORDER BY id LIMIT 8")->fetchAll();
$messages = (int) db()->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'")->fetchColumn();
$wholesale = (int) db()->query("SELECT COUNT(*) FROM wholesale_requests WHERE status = 'new'")->fetchColumn();
?>
<p><?= e($admin['email']) ?> · last sign-in <?= e(fmt_dt((string) ($admin['last_login_at'] ?? ''))) ?></p>
<div class="grid">
  <p class="card card-body"><strong><?= $waiting ?></strong><br><span class="muted">Needs action</span></p>
  <p class="card card-body"><strong><?= inr($today) ?></strong><br><span class="muted">Today, not cancelled</span></p>
  <p class="card card-body"><strong><?= inr($week) ?></strong><br><span class="muted">Last 7 days</span></p>
  <p class="card card-body"><strong><?= $messages + $wholesale ?></strong><br><span class="muted">New messages and wholesale</span></p>
</div>
<h2>Low stock</h2>
<ul>
<?php foreach ($low as $row): ?>
  <li><?= e($row['name']) ?> · <?= (int) $row['stock_qty'] - (int) $row['reserved_qty'] ?> free</li>
<?php endforeach; ?>
</ul>
<h2>Waiting more than a day</h2>
<ul>
<?php foreach ($old as $row): ?>
  <li><a href="/admin/order-view?id=<?= (int) $row['id'] ?>"><?= e($row['order_number']) ?></a> · <?= e(status_label((string) $row['status'])) ?> · <?= e(fmt_dt((string) $row['created_at'])) ?></li>
<?php endforeach; ?>
</ul>
<?php admin_close(); ?>
