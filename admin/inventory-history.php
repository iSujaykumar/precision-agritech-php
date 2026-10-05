<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
admin_boot();
admin_open('Stock history');
$rows = db()->query('SELECT m.*, p.name, u.email FROM inventory_movements m JOIN products p ON p.id = m.product_id JOIN users u ON u.id = m.admin_user_id ORDER BY m.id DESC LIMIT 100')->fetchAll();
echo '<table><tr><th>When</th><th>Product</th><th>Change</th><th>After</th><th>Reason</th><th>Staff</th></tr>';
foreach ($rows as $row) {
    echo '<tr><td>' . e($row['created_at']) . '</td><td>' . e($row['name']) . '</td><td>' . (int) $row['change_qty'] . '</td><td>' . (int) $row['stock_after'] . '</td><td>' . e($row['reason']) . '</td><td>' . e($row['email']) . '</td></tr>';
}
echo '</table>';
admin_close();
