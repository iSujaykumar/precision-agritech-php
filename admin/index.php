<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'dismiss_tips')) {
    require_csrf();
    save_setting('setup_tip_' . (int) $admin['id'], (new DateTimeImmutable('now', shop_timezone()))->format('Y-m-d'));
    flash_set('Setup tips hidden for today.');
    header('Location: /admin');
    exit;
}
admin_open('Today', 'today');
$updateDue = setting('schema_version', '') !== '2026.10.12-final' || setting('lists_loaded', '0') !== '1';
if ($updateDue) {
    echo '<p class="desk-warn"><a href="/admin/update">The shop update has not run. Open Update everything.</a></p>';
}
$metrics = [];
foreach (desk_metrics() as $row) {
    $metrics[$row['key']] = $row;
}
$now = array_filter($metrics, static fn ($row) => $row['band'] === 'now' && (int) $row['count'] > 0);
usort($now, static fn ($a, $b) => ($a['priority'] ?? 9) <=> ($b['priority'] ?? 9));
$now = array_slice($now, 0, 5);
if ($now) {
    echo '<h2>Do this now</h2><div class="desk-now">';
    $n = 1;
    foreach ($now as $row) {
        echo '<a class="btn" href="' . e($row['href']) . '">' . $n . '. ' . e($row['label']) . ' (' . (int) $row['count'] . ')</a>';
        $n++;
    }
    echo '</div>';
}
$tipDay = setting('setup_tip_' . (int) $admin['id'], '');
$today = (new DateTimeImmutable('now', shop_timezone()))->format('Y-m-d');
$tips = [];
if (trim(setting('gstin', '')) === '') {
    $tips[] = 'Confirm your GST status with your CA, then enter GSTIN in Settings.';
}
if (trim(setting('delivery_pins', '')) === '') {
    $tips[] = 'Set your delivery PIN codes.';
}
if ($tips && $tipDay !== $today) {
    echo '<div class="flash"><p><strong>Setup tips</strong></p><ul>';
    foreach ($tips as $tip) {
        echo '<li>' . e($tip) . '</li>';
    }
    echo '</ul><form method="post">' . csrf_field() . '<input type="hidden" name="action" value="dismiss_tips"><button class="btn" type="submit">Hide for today</button></form></div>';
}
echo '<h2>Orders</h2><div class="desk-cards">';
foreach (['today', 'week', 'dispatch', 'unpaid'] as $key) {
    $row = $metrics[$key];
    echo '<a class="card card-body" href="' . e($row['href']) . '"><strong>' . (int) $row['count'] . '</strong><br>' . e($row['label']) . '</a>';
}
echo '</div><h2>Stock</h2><div class="desk-cards">';
foreach (['low', 'out', 'photos'] as $key) {
    $row = $metrics[$key];
    echo '<a class="card card-body" href="' . e($row['href']) . '"><strong>' . (int) $row['count'] . '</strong><br>' . e($row['label']) . '</a>';
}
echo '</div><h2>Shop</h2><div class="desk-cards">';
foreach (['offers', 'reviews', 'messages', 'wholesale'] as $key) {
    $row = $metrics[$key];
    echo '<a class="card card-body" href="' . e($row['href']) . '"><strong>' . (int) $row['count'] . '</strong><br>' . e($row['label']) . '</a>';
}
echo '</div>';
$weekOrders = (int) $metrics['week']['count'];
$weekSales = (int) db()->query("SELECT COALESCE(SUM(total_inr),0) FROM orders WHERE payment_status = 'paid' AND created_at >= (NOW() - INTERVAL 7 DAY)")->fetchColumn();
$weekBest = db()->query("SELECT oi.product_name, SUM(oi.quantity) AS qty FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.created_at >= (NOW() - INTERVAL 7 DAY) GROUP BY oi.product_name ORDER BY qty DESC LIMIT 3")->fetchAll();
echo '<section class="week-box"><h2>Last 7 days</h2><p>' . $weekOrders . ' orders. Paid sales ' . inr($weekSales) . '.</p><ul>';
foreach ($weekBest as $best) {
    echo '<li>' . e((string) $best['product_name']) . ' · ' . (int) $best['qty'] . ' trays</li>';
}
if (!$weekBest) {
    echo '<li>No plants sold in the last 7 days.</li>';
}
echo '</ul></section>';
$lowLimit = low_stock_limit();
$lowRows = stock_rows('low');
echo '<h2>Low stock</h2>';
if (!$lowRows) {
    echo '<p class="muted">No shop plants are down to ' . (int) $lowLimit . ' trays or fewer.</p>';
}
echo '<div class="table-wrap"><table>';
foreach (array_slice($lowRows, 0, 8) as $row) {
    $free = (int) $row['stock_qty'] - (int) $row['reserved_qty'];
    echo '<tr><td><a href="/admin/stock?filter=low">' . e($row['name']) . '</a></td><td>' . $free . ' free</td></tr>';
}
echo '</table></div>';
$recent = db()->query('SELECT id, order_number, customer_name, total_inr, status FROM orders ORDER BY id DESC LIMIT 5')->fetchAll();
echo '<h2>Last orders</h2><div class="table-wrap"><table><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th></tr>';
foreach ($recent as $row) {
    echo '<tr><td><a href="/admin/order-view?id=' . (int) $row['id'] . '">' . e($row['order_number']) . '</a></td><td>' . e($row['customer_name']) . '</td><td>' . inr((int) $row['total_inr']) . '</td><td>' . e(order_label((string) $row['status'])) . '</td></tr>';
}
echo '</table></div>';
admin_close();
