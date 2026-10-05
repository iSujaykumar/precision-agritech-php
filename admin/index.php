<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
admin_open('Nursery desk');
$placed = (int) db()->query("SELECT COUNT(*) FROM orders WHERE status IN ('placed','payment_pending')")->fetchColumn();
$paid = (int) db()->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'paid'")->fetchColumn();
$low = (int) db()->query('SELECT COUNT(*) FROM products WHERE active = 1 AND stock_qty - reserved_qty <= 5')->fetchColumn();
?>
<p><?= e($admin['email']) ?></p>
<div class="grid">
  <p class="card card-body"><strong><?= $placed ?></strong><br><span class="muted">Waiting on the nursery</span></p>
  <p class="card card-body"><strong><?= $paid ?></strong><br><span class="muted">Paid orders</span></p>
  <p class="card card-body"><strong><?= $low ?></strong><br><span class="muted">Low or reserved tight</span></p>
</div>
<?php admin_close(); ?>
