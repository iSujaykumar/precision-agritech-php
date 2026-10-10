<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $id = (int) ($_POST['id'] ?? 0);
        $active = (int) ($_POST['active'] ?? 0);
        db()->prepare('UPDATE products SET active = ? WHERE id = ?')->execute([$active, $id]);
        audit_log((int) $admin['id'], $active ? 'product_restore' : 'product_archive', 'product', $id, null, (string) $active);
        header('Location: /admin/products');
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'That change could not be saved.');
    }
}
admin_open('Products');
if (!empty($error)) echo '<p class="flash">' . e($error) . '</p>';
$rows = db()->query('SELECT id, name, sku, price_inr, stock_qty, reserved_qty, active, on_offer FROM products ORDER BY name')->fetchAll();
echo '<p><a class="btn" href="/admin/product-edit">Add product</a></p><div class="table-wrap"><table>';
foreach ($rows as $row) {
    echo '<tr><td><a href="/admin/product-edit?id=' . (int) $row['id'] . '">' . e($row['name']) . '</a></td><td>' . inr((int) $row['price_inr']) . '</td><td>' . (int) $row['stock_qty'] . ' / reserved ' . (int) $row['reserved_qty'] . '</td><td>' . ($row['active'] ? 'Live' : 'Archived') . '</td>';
    echo '<td><form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="id" value="' . (int) $row['id'] . '"><input type="hidden" name="active" value="' . ($row['active'] ? '0' : '1') . '"><button class="linkish">' . ($row['active'] ? 'Archive' : 'Restore') . '</button></form></td></tr>';
}
echo '</table></div>';
admin_close();
