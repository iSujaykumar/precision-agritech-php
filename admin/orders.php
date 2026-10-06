<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
admin_boot();
$q = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$method = trim((string) ($_GET['method'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$where = 'WHERE 1=1';
$params = [];
if ($q !== '') {
    $where .= ' AND (order_number LIKE ? OR customer_name LIKE ? OR customer_phone LIKE ?)';
    $like = '%' . str_replace(['%', '_'], '', $q) . '%';
    $params = [$like, $like, $like];
}
if ($status !== '') {
    $where .= ' AND status = ?';
    $params[] = $status;
}
if ($method !== '') {
    $where .= ' AND payment_method = ?';
    $params[] = $method;
}
$count = db()->prepare('SELECT COUNT(*) FROM orders ' . $where);
$count->execute($params);
$pages = max(1, (int) ceil(((int) $count->fetchColumn()) / 50));
$page = min($page, $pages);
$stmt = db()->prepare('SELECT id, order_number, customer_name, status, payment_status, payment_method, total_inr, created_at FROM orders ' . $where . ' ORDER BY id DESC LIMIT 50 OFFSET ' . (($page - 1) * 50));
$stmt->execute($params);
admin_open('Orders');
echo '<form method="get" class="search-row"><label>Search <input name="q" value="' . e($q) . '"></label><label>Status <input name="status" value="' . e($status) . '"></label><label>Payment <input name="method" value="' . e($method) . '"></label><button class="btn">Filter</button></form>';
echo '<div class="table-scroll"><table><thead><tr><th>Order</th><th>Date</th><th>Customer</th><th>Status</th><th>Payment</th><th>Total</th></tr></thead>';
foreach ($stmt as $row) {
    echo '<tr><td><a href="/admin/order-view?id=' . (int) $row['id'] . '">' . e($row['order_number']) . '</a></td><td>' . e(fmt_dt((string) $row['created_at'])) . '</td><td>' . e($row['customer_name']) . '</td><td>' . e(status_label((string) $row['status'])) . '</td><td>' . e(status_label((string) $row['payment_status'])) . ' · ' . e(status_label((string) $row['payment_method'])) . '</td><td>' . inr((int) $row['total_inr']) . '</td></tr>';
}
echo '</table></div>';
if ($page > 1) {
    echo '<a href="/admin/orders?page=' . ($page - 1) . '">Previous</a> ';
}
if ($page < $pages) {
    echo '<a href="/admin/orders?page=' . ($page + 1) . '">Next</a>';
}
admin_close();
