<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
require_desk($admin, 'manager');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        if (($_POST['action'] ?? '') === 'undo') {
            $last = db()->query('SELECT * FROM price_undo WHERE undone = 0 ORDER BY id DESC LIMIT 1')->fetch();
            if (!$last) {
                throw new RuntimeException('There is no price change to undo.');
            }
            $paise = (int) $last['old_paise'];
            $tray = exact_tray_paise($paise, 150);
            db()->prepare('UPDATE products SET price_per_seedling_paise = ?, tray_paise = ?, price_inr = ? WHERE id = ?')
                ->execute([$paise, $tray, intdiv($tray + 50, 100), (int) $last['product_id']]);
            db()->prepare('UPDATE price_undo SET undone = 1 WHERE id = ?')->execute([(int) $last['id']]);
            audit_log((int) $admin['id'], 'price_undo', 'product', (int) $last['product_id'], (string) $last['new_paise'], (string) $paise);
            flash_set('Last price change undone.');
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            $raw = trim((string) ($_POST['rate'] ?? ''));
            $paise = price_rupees_to_paise($raw);
            $stmt = db()->prepare('SELECT price_per_seedling_paise, name FROM products WHERE id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) {
                throw new RuntimeException('That plant was not found.');
            }
            $old = (int) ($row['price_per_seedling_paise'] ?? 0);
            $tray = exact_tray_paise($paise, 150);
            db()->prepare('UPDATE products SET price_per_seedling_paise = ?, tray_paise = ?, price_inr = ? WHERE id = ?')
                ->execute([$paise, $tray, intdiv($tray + 50, 100), $id]);
            db()->prepare('INSERT INTO price_undo (product_id, old_paise, new_paise, admin_id) VALUES (?, ?, ?, ?)')
                ->execute([$id, $old, $paise, (int) $admin['id']]);
            audit_log((int) $admin['id'], 'price_quick', 'product', $id, (string) $old, (string) $paise);
            flash_set('Price saved for ' . $row['name'] . '.');
        }
        header('Location: /admin/prices');
        exit;
    } catch (Throwable $err) {
        flash_set($err->getMessage(), false);
    }
}
$rows = db()->query('SELECT id, name, variety, price_per_seedling_paise FROM products WHERE active = 1 AND parent_product_id IS NULL AND farmer_only = 0 ORDER BY name')->fetchAll();
admin_open('Price quick edit');
echo '<form method="post">' . csrf_field() . '<input type="hidden" name="action" value="undo"><button class="btn" type="submit">Undo last price change</button></form>';
echo '<div class="table-wrap"><table><tr><th>Plant</th><th>Rate per seedling</th><th>Tray (150)</th><th></th></tr>';
foreach ($rows as $row) {
    $paise = (int) ($row['price_per_seedling_paise'] ?? 0);
    echo '<tr><form method="post">' . csrf_field();
    echo '<td>' . e($row['name']) . '<input type="hidden" name="id" value="' . (int) $row['id'] . '"></td>';
    echo '<td><input name="rate" data-rate value="' . e($paise ? number_format($paise / 100, 2, '.', '') : '') . '" inputmode="decimal"></td>';
    echo '<td data-tray>' . e($paise ? inr_paise(exact_tray_paise($paise, 150)) : '') . '</td>';
    echo '<td><button class="btn" type="submit">Save</button></td></form></tr>';
}
echo '</table></div>';
echo '<script>document.querySelectorAll("[data-rate]").forEach(function(input){input.addEventListener("input",function(){var n=parseFloat(input.value);var cell=input.closest("tr").querySelector("[data-tray]");if(!n||n<0){cell.textContent="";return;}var paise=Math.round(n*100);cell.textContent="Rs "+((paise*150)/100).toLocaleString("en-IN");});});</script>';
admin_close();
