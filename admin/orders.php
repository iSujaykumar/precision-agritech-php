<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
admin_boot();
$status = (string) ($_GET['status'] ?? '');
$pay = (string) ($_GET['pay'] ?? '');
$q = trim((string) ($_GET['q'] ?? ''));
$where = ' WHERE 1=1';
$params = [];
$allowed = ['placed', 'payment_pending', 'payment_submitted', 'payment_confirmed', 'confirmed', 'preparing', 'packed', 'out_for_delivery', 'dispatched', 'delivery_failed', 'delivered', 'cancelled'];
if (($_GET['check'] ?? '') === '1') {
    $where .= " AND status = 'payment_submitted'";
}
if (in_array($status, $allowed, true)) {
    $where .= ' AND status = ?';
    $params[] = $status;
}
if ($pay === 'paid') {
    $where .= " AND payment_status = 'paid'";
} elseif ($pay === 'unpaid') {
    $where .= " AND payment_status IN ('unpaid','pending')";
}
if ($q !== '') {
    $where .= ' AND (order_number LIKE ? OR customer_name LIKE ? OR customer_phone LIKE ?)';
    $like = '%' . str_replace(['%', '_'], '', $q) . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if (($_GET['export'] ?? '') === 'csv') {
    $stmt = db()->prepare('SELECT order_number, created_at, customer_name, customer_phone, status, payment_status, total_inr FROM orders' . $where . ' ORDER BY id DESC');
    $stmt->execute($params);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="orders.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Order', 'Date', 'Customer', 'Mobile', 'Status', 'Payment', 'Total']);
    foreach ($stmt as $row) {
        fputcsv($out, [$row['order_number'], $row['created_at'], $row['customer_name'], $row['customer_phone'], order_label((string) $row['status']), $row['payment_status'], $row['total_inr']]);
    }
    fclose($out);
    exit;
}
$count = db()->prepare('SELECT COUNT(*) FROM orders' . $where);
$count->execute($params);
[$page, $pages, $offset] = list_bounds((int) $count->fetchColumn(), 25);
$stmt = db()->prepare('SELECT id, order_number, customer_name, status, payment_status, total_inr, created_at FROM orders' . $where . ' ORDER BY id DESC LIMIT 25 OFFSET ' . (int) $offset);
$stmt->execute($params);
$rows = $stmt->fetchAll();
admin_open('Orders');
$tabs = ['' => 'All', 'placed' => 'New', 'payment_submitted' => 'Waiting for payment check', 'payment_pending' => 'Payment pending', 'payment_confirmed' => 'Paid', 'preparing' => 'Preparing', 'dispatched' => 'Dispatched', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];
echo '<nav class="subnav">';
foreach ($tabs as $key => $label) {
    $href = '/admin/orders' . ($key !== '' ? '?status=' . rawurlencode($key) : '');
    $on = $status === $key && $pay === '';
    echo '<a href="' . e($href) . '"' . ($on ? ' aria-current="page"' : '') . '>' . e($label) . '</a>';
}
echo '</nav>';
echo '<form method="get" class="inline"><label>Search <input name="q" value="' . e($q) . '" placeholder="Order, name or mobile"></label>';
if ($status !== '') {
    echo '<input type="hidden" name="status" value="' . e($status) . '">';
}
echo '<button class="btn" type="submit">Search</button> <a href="/admin/orders?export=csv">Export CSV</a></form>';
echo '<div class="table-wrap"><table><tr><th>Order</th><th>Date</th><th>Customer</th><th>Status</th><th>Payment</th><th>Total</th></tr>';
foreach ($rows as $row) {
    $class = $row['status'] === 'payment_submitted' ? ' class="pay-wait"' : '';
    echo '<tr' . $class . '><td><a href="/admin/order-view?id=' . (int) $row['id'] . '">' . e($row['order_number']) . '</a></td><td>' . e((string) $row['created_at']) . '</td><td>' . e($row['customer_name']) . '</td><td>' . e(order_label((string) $row['status'])) . '</td><td>' . e((string) $row['payment_status']) . '</td><td>' . inr((int) $row['total_inr']) . '</td></tr>';
}
echo '</table></div>';
pager_nav($page, $pages);
admin_close();
