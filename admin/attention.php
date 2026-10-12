<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
require_desk($admin, 'staff');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $id = (int) ($_POST['id'] ?? 0);
        if (($_POST['action'] ?? '') === 'show') {
            show_plant_in_shop(db(), $id, (int) $admin['id']);
            flash_set('That plant is now in the shop.');
        }
        header('Location: /admin/attention');
        exit;
    } catch (Throwable $err) {
        flash_set($err->getMessage(), false);
    }
}
admin_open('Needs attention', 'plants');
$sql = "SELECT id, name, slug, image_url, image_is_placeholder, stock_qty, reserved_qty, short_description, active, list_only, needs_copy
    FROM products WHERE parent_product_id IS NULL AND farmer_only = 0
    AND (active = 0 OR list_only = 1 OR needs_copy = 1 OR stock_qty - reserved_qty <= 0 OR image_is_placeholder = 1 OR short_description = '' OR image_url = '')
    ORDER BY name";
$rows = db()->query($sql)->fetchAll();
if (!$rows) {
    echo '<p>Nothing needs attention.</p>';
}
echo '<div class="table-wrap"><table><tr><th>Plant</th><th>Why</th><th></th></tr>';
foreach ($rows as $row) {
    $why = [];
    if ((int) $row['list_only'] === 1 || (int) $row['active'] !== 1) {
        $why[] = 'not shown in the shop';
    }
    if ((int) $row['image_is_placeholder'] === 1 || trim((string) $row['image_url']) === '') {
        $why[] = 'needs a photo';
    }
    if ((int) $row['stock_qty'] - (int) $row['reserved_qty'] <= 0) {
        $why[] = 'stock 0';
    }
    if (trim((string) $row['short_description']) === '' || (int) $row['needs_copy'] === 1) {
        $why[] = 'no description';
    }
    echo '<tr><td>' . e($row['name']) . '</td><td>' . e(implode(', ', $why)) . '</td><td>';
    if ((int) $row['list_only'] === 1 || (int) $row['active'] !== 1) {
        echo '<form method="post">' . csrf_field() . '<input type="hidden" name="action" value="show"><input type="hidden" name="id" value="' . (int) $row['id'] . '"><button class="btn" type="submit">Show this plant in the shop</button></form>';
    } else {
        echo '<a href="/admin/product-edit?id=' . (int) $row['id'] . '">Edit</a>';
    }
    echo '</td></tr>';
}
echo '</table></div>';
admin_close();
