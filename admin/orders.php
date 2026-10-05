<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
admin_boot();
admin_open('Orders');
$rows = db()->query('SELECT id, order_number, customer_name, status, payment_status, total_inr, created_at FROM orders ORDER BY id DESC LIMIT 100')->fetchAll();
echo '<table><tr><th>Order</th><th>Customer</th><th>Status</th><th>Total</th></tr>';
foreach ($rows as $row) {
    echo '<tr><td><a href="/admin/order-view?id=' . (int) $row['id'] . '">' . e($row['order_number']) . '</a></td><td>' . e($row['customer_name']) . '</td><td>' . e($row['status']) . '</td><td>' . inr((int) $row['total_inr']) . '</td></tr>';
}
echo '</table>';
admin_close();
