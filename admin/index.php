<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
admin_boot();
admin_open('Today');
if (setting('schema_version', '') !== '2026.10.12-final' || setting('lists_loaded', '0') !== '1') {
    echo '<p class="desk-warn"><a href="/admin/update">The shop update has not run. Open Update everything.</a></p>';
}
foreach (desk_warnings() as $warning) {
    echo '<p class="desk-warn" role="alert">' . e($warning) . '</p>';
}
$refunds = (int) db()->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'refund_due'")->fetchColumn();
$waitingPay = (int) db()->query("SELECT COUNT(*) FROM orders WHERE status = 'payment_submitted'")->fetchColumn();
if ($refunds > 0) {
    echo '<p class="desk-warn"><a href="/admin/orders?pay=refund_due">Refunds due: ' . $refunds . '</a></p>';
}
if ($waitingPay > 0) {
    echo '<p class="flash">Payment waiting to be checked: ' . $waitingPay . '</p>';
}
if (trim(setting('gstin', '')) === '') {
    echo '<p class="flash">Confirm your GST status with your CA, then enter GSTIN in Settings.</p>';
}
if (trim(setting('delivery_pins', '')) === '') {
    echo '<p class="flash">Set your delivery PIN codes.</p>';
}
$today = (int) db()->query("SELECT COUNT(*) FROM orders WHERE created_at >= CURDATE()")->fetchColumn();
$month = (int) db()->query("SELECT COALESCE(SUM(total_inr),0) FROM orders WHERE payment_status = 'paid' AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetchColumn();
$waiting = (int) db()->query("SELECT COUNT(*) FROM orders WHERE status IN ('placed','payment_pending')")->fetchColumn();
$unpaid = (int) db()->query("SELECT COUNT(*) FROM orders WHERE payment_status IN ('unpaid','pending') AND status <> 'cancelled'")->fetchColumn();
$paid = (int) db()->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'paid'")->fetchColumn();
$lowLimit = low_stock_limit();
$waitingOrders = (int) db()->query("SELECT COUNT(*) FROM orders WHERE status IN ('placed','payment_pending')")->fetchColumn();
$payConfirm = (int) db()->query("SELECT COUNT(*) FROM orders WHERE status = 'payment_submitted'")->fetchColumn();
$toPack = (int) db()->query("SELECT COUNT(*) FROM orders WHERE status IN ('payment_confirmed','confirmed','preparing')")->fetchColumn();
$lowPlants = (int) db()->query('SELECT COUNT(*) FROM products WHERE active = 1 AND list_only = 0 AND farmer_only = 0 AND parent_product_id IS NULL AND stock_qty - reserved_qty > 0 AND stock_qty - reserved_qty <= ' . $lowLimit)->fetchColumn();
$outPlants = (int) db()->query('SELECT COUNT(*) FROM products WHERE active = 1 AND list_only = 0 AND farmer_only = 0 AND parent_product_id IS NULL AND stock_qty - reserved_qty <= 0')->fetchColumn();
$restockN = 0;
try {
    $restockN = (int) db()->query("SELECT COUNT(*) FROM restock_requests WHERE status = 'new'")->fetchColumn();
} catch (Throwable $err) {
    $restockN = 0;
}
$photoN = (int) db()->query('SELECT COUNT(*) FROM products WHERE active = 1 AND parent_product_id IS NULL AND farmer_only = 0 AND image_is_placeholder = 1')->fetchColumn();
?>
<div class="desk-cards">
  <a class="card card-body" href="/admin/orders?status=placed"><strong><?= $waitingOrders ?></strong><br>New orders waiting</a>
  <a class="card card-body" href="/admin/orders?status=payment_submitted"><strong><?= $payConfirm ?></strong><br>Payments to confirm</a>
  <a class="card card-body" href="/admin/orders?status=confirmed"><strong><?= $toPack ?></strong><br>Orders to pack</a>
  <a class="card card-body" href="/admin/stock"><strong><?= $lowPlants ?></strong><br>Low stock plants</a>
  <a class="card card-body" href="/admin/stock"><strong><?= $outPlants ?></strong><br>Out of stock plants</a>
  <a class="card card-body" href="/admin/restock"><strong><?= $restockN ?></strong><br>Back-in-stock requests</a>
  <a class="card card-body" href="/admin/photos?filter=placeholder"><strong><?= $photoN ?></strong><br>Plants needing a photo</a>
  <a class="card card-body" href="/admin/attention"><strong>Needs attention</strong><br><span class="muted">Photos, stock, hidden plants</span></a>
</div>
<?php
$weekOrders = (int) db()->query("SELECT COUNT(*) FROM orders WHERE created_at >= (NOW() - INTERVAL 7 DAY)")->fetchColumn();
$weekSales = (int) db()->query("SELECT COALESCE(SUM(total_inr),0) FROM orders WHERE payment_status = 'paid' AND created_at >= (NOW() - INTERVAL 7 DAY)")->fetchColumn();
$weekBest = db()->query("SELECT oi.product_name, SUM(oi.quantity) AS qty FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.created_at >= (NOW() - INTERVAL 7 DAY) GROUP BY oi.product_name ORDER BY qty DESC LIMIT 3")->fetchAll();
echo '<h2>Last 7 days</h2><p>' . $weekOrders . ' orders. Paid sales ' . inr($weekSales) . '.</p><ul>';
foreach ($weekBest as $best) {
    echo '<li>' . e((string) $best['product_name']) . ' · ' . (int) $best['qty'] . ' trays</li>';
}
if (!$weekBest) {
    echo '<li>No plants sold in the last 7 days.</li>';
}
echo '</ul>';
$week = (int) db()->query("SELECT COUNT(*) FROM orders WHERE created_at >= (NOW() - INTERVAL 7 DAY)")->fetchColumn();
$out = (int) db()->query('SELECT COUNT(*) FROM products WHERE active = 1 AND stock_qty - reserved_qty <= 0')->fetchColumn();
$offerCount = (int) db()->query('SELECT COUNT(*) FROM products p JOIN categories c ON c.id = p.category_id WHERE p.active = 1 AND c.active = 1 AND ' . offer_active_sql())->fetchColumn();
$newCustomers = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'customer' AND created_at >= CURDATE()")->fetchColumn();
$openClaims = (int) db()->query("SELECT COUNT(*) FROM claims WHERE status = 'new'")->fetchColumn();
$dispatch = (int) db()->query("SELECT COUNT(*) FROM orders WHERE status IN ('confirmed','preparing','packed')")->fetchColumn();
$low = (int) db()->query('SELECT COUNT(*) FROM products WHERE active = 1 AND stock_qty - reserved_qty <= ' . $lowLimit)->fetchColumn();
$messages = (int) db()->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'")->fetchColumn();
$wholesale = (int) db()->query("SELECT COUNT(*) FROM wholesale_requests WHERE status = 'new'")->fetchColumn();
$needsCopy = 0;
try {
    $needsCopy = (int) db()->query('SELECT COUNT(*) FROM products WHERE needs_copy = 1 AND active = 1')->fetchColumn();
} catch (Throwable $err) {
    $needsCopy = 0;
}
$pendingReviews = (int) db()->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending'")->fetchColumn();
$lowRows = db()->query('SELECT id, name, stock_qty, reserved_qty FROM products WHERE active = 1 AND stock_qty - reserved_qty <= ' . $lowLimit . ' ORDER BY (stock_qty - reserved_qty), name LIMIT 8')->fetchAll();
$recent = db()->query('SELECT id, order_number, customer_name, total_inr, status FROM orders ORDER BY id DESC LIMIT 5')->fetchAll();
?>
<div class="desk-cards">
  <a class="card card-body" href="/admin/orders"><strong><?= $today ?></strong><br><span class="muted">Today's orders</span></a>
  <a class="card card-body" href="/admin/orders"><strong><?= $week ?></strong><br><span class="muted">Orders this week</span></a>
  <a class="card card-body" href="/admin/orders?status=confirmed"><strong><?= $dispatch ?></strong><br><span class="muted">Ready to dispatch</span></a>
  <a class="card card-body" href="/admin/offers"><strong><?= $offerCount ?></strong><br><span class="muted">Active offers</span></a>
  <a class="card card-body" href="/admin/customers"><strong><?= $newCustomers ?></strong><br><span class="muted">New customers today</span></a>
  <a class="card card-body" href="/admin/claims"><strong><?= $openClaims ?></strong><br><span class="muted">Open claims</span></a>
  <a class="card card-body" href="/admin/inventory"><strong><?= $out ?></strong><br><span class="muted">Out of stock</span></a>
  <a class="card card-body" href="/admin/orders?pay=paid"><strong><?= inr($month) ?></strong><br><span class="muted">This month's paid sales</span></a>
  <a class="card card-body" href="/admin/orders?status=placed"><strong><?= $waiting ?></strong><br><span class="muted">Orders waiting</span></a>
  <a class="card card-body" href="/admin/orders?pay=unpaid"><strong><?= $unpaid ?></strong><br><span class="muted">Unpaid or pending</span></a>
  <a class="card card-body" href="/admin/orders?pay=paid"><strong><?= $paid ?></strong><br><span class="muted">Paid orders</span></a>
  <a class="card card-body" href="/admin/inventory"><strong><?= $low ?></strong><br><span class="muted">Low stock</span></a>
  <a class="card card-body" href="/admin/messages"><strong><?= $messages ?></strong><br><span class="muted">New messages</span></a>
  <a class="card card-body" href="/admin/wholesale"><strong><?= $wholesale ?></strong><br><span class="muted">New wholesale requests</span></a>
  <a class="card card-body" href="/admin/products"><strong><?= $needsCopy ?></strong><br><span class="muted">Needs photo and description</span></a>
  <a class="card card-body" href="/admin/reviews?status=pending"><strong><?= $pendingReviews ?></strong><br><span class="muted">Pending reviews</span></a>
</div>
<p class="btn-row">
  <a class="btn" href="/admin/product-edit">Add product</a>
  <a class="btn" href="/admin/orders?check=1">Pending payments</a>
  <a class="btn" href="/admin/offers">Add offer</a>
  <a class="btn" href="/admin/coupons">Add coupon</a>
</p>
<h2>Low stock</h2>
<?php if (!$lowRows): ?><p class="muted">No trays are down to <?= (int) $lowLimit ?> or fewer.</p><?php endif; ?>
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
