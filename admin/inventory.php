<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
$error = null;
$tab = (string) ($_GET['tab'] ?? 'stock');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $pdo = db();
        $pdo->beginTransaction();
        $id = (int) ($_POST['id'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if (($_POST['mode'] ?? '') === 'set') {
            $target = (int) ($_POST['set_to'] ?? 0);
            $current = $pdo->prepare('SELECT stock_qty FROM products WHERE id = ?');
            $current->execute([$id]);
            $before = $current->fetchColumn();
            if ($before === false) {
                throw new RuntimeException('Product not found.');
            }
            adjust_stock($pdo, $id, $target - (int) $before, $reason, (int) $admin['id']);
        } else {
            adjust_stock($pdo, $id, (int) ($_POST['change'] ?? 0), $reason, (int) $admin['id']);
        }
        $pdo->commit();
        flash_set('Stock updated');
        header('Location: /admin/inventory');
        exit;
    } catch (Throwable $err) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = safe_error($err, 'The stock change was not saved.');
    }
}
admin_open('Stock', 'products');
if ($error) {
    echo '<p class="flash bad" role="alert">' . e($error) . '</p>';
}
echo '<nav class="subnav"><a href="/admin/inventory"' . ($tab !== 'history' ? ' aria-current="page"' : '') . '>On hand</a><a href="/admin/inventory?tab=history"' . ($tab === 'history' ? ' aria-current="page"' : '') . '>History</a></nav>';
if ($tab === 'history') {
    [$page, $pages, $offset] = list_bounds((int) db()->query('SELECT COUNT(*) FROM inventory_movements')->fetchColumn(), 50);
    $rows = db()->query('SELECT m.*, p.name, u.email FROM inventory_movements m JOIN products p ON p.id = m.product_id JOIN users u ON u.id = m.admin_user_id ORDER BY m.id DESC LIMIT 50 OFFSET ' . (int) $offset)->fetchAll();
    echo '<div class="table-wrap"><table><tr><th>When</th><th>Product</th><th>Change</th><th>After</th><th>Reason</th><th>Staff</th></tr>';
    foreach ($rows as $row) {
        echo '<tr><td>' . e($row['created_at']) . '</td><td>' . e($row['name']) . '</td><td>' . (int) $row['change_qty'] . '</td><td>' . (int) $row['stock_after'] . '</td><td>' . e($row['reason']) . '</td><td>' . e($row['email']) . '</td></tr>';
    }
    echo '</table></div>';
    pager_nav($page, $pages);
} else {
    $rows = db()->query('SELECT id, name, stock_qty, reserved_qty FROM products ORDER BY name')->fetchAll();
    echo '<div class="table-wrap"><table><tr><th>Tray</th><th>On hand</th><th>Reserved</th><th>Free</th><th>Change</th></tr>';
    foreach ($rows as $row) {
        $free = (int) $row['stock_qty'] - (int) $row['reserved_qty'];
        echo '<tr><td>' . e($row['name']) . '</td><td>' . (int) $row['stock_qty'] . '</td><td>' . (int) $row['reserved_qty'] . '</td><td>' . $free . '</td><td>';
        echo '<form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="id" value="' . (int) $row['id'] . '"><input name="change" type="number" placeholder="+/-" aria-label="Stock change"><input name="set_to" type="number" min="0" placeholder="Set to" aria-label="Set stock to"><input name="reason" required placeholder="Reason" aria-label="Reason"><button class="btn" name="mode" value="delta" type="submit">Change</button> <button class="btn" name="mode" value="set" type="submit">Set</button></form>';
        echo '</td></tr>';
    }
    echo '</table></div>';
}
admin_close();
