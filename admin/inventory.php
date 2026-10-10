<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $pdo = db();
        $pdo->beginTransaction();
        adjust_stock($pdo, (int) $_POST['id'], (int) $_POST['change'], trim((string) $_POST['reason']), (int) $admin['id']);
        $pdo->commit();
        header('Location: /admin/inventory');
        exit;
    } catch (Throwable $err) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        $error = safe_error($err, 'The stock change was not saved.');
    }
}
admin_open('Stock');
if ($error) echo '<p class="flash">' . e($error) . '</p>';
$rows = db()->query('SELECT id, name, stock_qty, reserved_qty FROM products ORDER BY name')->fetchAll();
echo '<div class="table-wrap"><table>';
foreach ($rows as $row) {
    $free = (int) $row['stock_qty'] - (int) $row['reserved_qty'];
    echo '<tr><td>' . e($row['name']) . '</td><td>' . (int) $row['stock_qty'] . ' on hand, ' . (int) $row['reserved_qty'] . ' reserved, ' . $free . ' free</td><td><form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="id" value="' . (int) $row['id'] . '"><input name="change" type="number" required placeholder="+/-" aria-label="Stock change"><input name="reason" required placeholder="Reason" aria-label="Reason"><button class="btn" type="submit">Save</button></form></td></tr>';
}
echo '</table></div>';
admin_close();
