<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
require_role($admin, 'staff');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $ids = $_POST['id'] ?? [];
        $qtys = $_POST['qty'] ?? [];
        if (!is_array($ids) || !is_array($qtys)) {
            throw new RuntimeException('Nothing to save.');
        }
        db()->beginTransaction();
        $find = db()->prepare('SELECT stock_qty, reserved_qty FROM products WHERE id = ? FOR UPDATE');
        $save = db()->prepare('UPDATE products SET stock_qty = ? WHERE id = ?');
        foreach ($ids as $i => $id) {
            $id = (int) $id;
            $qty = (int) ($qtys[$i] ?? 0);
            if ($id < 1 || $qty < 0 || $qty > 100000) {
                throw new RuntimeException('Check the tray numbers.');
            }
            $find->execute([$id]);
            $row = $find->fetch();
            if (!$row) {
                continue;
            }
            if ($qty < (int) $row['reserved_qty']) {
                throw new RuntimeException('Stock cannot be lower than trays already reserved.');
            }
            $save->execute([$qty, $id]);
            audit_log((int) $admin['id'], 'stock_quick', 'product', $id, (string) $row['stock_qty'], (string) $qty);
        }
        db()->commit();
        flash_set('Stock saved.');
        header('Location: /admin/stock');
        exit;
    } catch (Throwable $err) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        flash_set($err->getMessage(), false);
    }
}
$rows = stock_rows((string) ($_GET['filter'] ?? ''));
admin_open('Stock quick edit', 'plants');
$filter = (string) ($_GET['filter'] ?? '');
echo '<p class="filters"><a href="/admin/stock"' . ($filter === '' ? ' aria-current="page"' : '') . '>All plants</a> ';
echo '<a href="/admin/stock?filter=low"' . ($filter === 'low' ? ' aria-current="page"' : '') . '>Low stock</a> ';
echo '<a href="/admin/stock?filter=out"' . ($filter === 'out' ? ' aria-current="page"' : '') . '>Out of stock</a> ';
echo '<a href="/admin/inventory-history">History</a></p>';
echo '<form method="post">';
echo csrf_field();
echo '<div class="table-wrap"><table><tr><th>Plant</th><th>Free</th><th>Reserved</th><th>Stock</th></tr>';
foreach ($rows as $row) {
    $free = (int) $row['stock_qty'] - (int) $row['reserved_qty'];
    echo '<tr><td>' . e($row['name']) . ($row['list_only'] ? ' <span class="muted">(price list only)</span>' : '') . '<input type="hidden" name="id[]" value="' . (int) $row['id'] . '"></td>';
    echo '<td>' . $free . '</td><td>' . (int) $row['reserved_qty'] . '</td>';
    echo '<td><button class="step" type="button" data-step="minus" aria-label="Less">−</button> <input class="stock-qty" name="qty[]" type="number" min="0" value="' . (int) $row['stock_qty'] . '"> <button class="step" type="button" data-step="plus" aria-label="More">+</button></td></tr>';
}
echo '</table></div><button class="btn" type="submit">Save all</button></form>';
echo '<script src="/assets/js/cart.js"></script>';
admin_close();
