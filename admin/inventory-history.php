<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
admin_boot();
admin_open('Stock history', 'plants');
echo '<p><a href="/admin/stock">Back to stock</a></p>';
[$page, $pages, $offset] = list_bounds((int) db()->query('SELECT COUNT(*) FROM inventory_movements')->fetchColumn(), 50);
$rows = db()->query('SELECT m.*, p.name, u.email FROM inventory_movements m JOIN products p ON p.id = m.product_id JOIN users u ON u.id = m.admin_user_id ORDER BY m.id DESC LIMIT 50 OFFSET ' . (int) $offset)->fetchAll();
echo '<div class="table-wrap"><table><tr><th>When</th><th>Product</th><th>Change</th><th>After</th><th>Reason</th><th>Staff</th></tr>';
foreach ($rows as $row) {
    echo '<tr><td>' . e((string) $row['created_at']) . '</td><td>' . e((string) $row['name']) . '</td><td>' . (int) $row['change_qty'] . '</td><td>' . (int) $row['stock_after'] . '</td><td>' . e((string) $row['reason']) . '</td><td>' . e((string) $row['email']) . '</td></tr>';
}
echo '</table></div>';
pager_nav($page, $pages);
admin_close();
