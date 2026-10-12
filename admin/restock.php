<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
require_desk($admin, 'staff');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    db()->prepare("UPDATE restock_requests SET status = 'done' WHERE id = ?")->execute([$id]);
    audit_log((int) $admin['id'], 'restock_done', 'restock', $id, 'new', 'done');
    flash_set('Marked done.');
    header('Location: /admin/restock');
    exit;
}
$rows = db()->query("SELECT r.id, r.contact, r.created_at, p.name FROM restock_requests r JOIN products p ON p.id = r.product_id WHERE r.status = 'new' ORDER BY r.id DESC")->fetchAll();
admin_open('Back in stock requests');
if (!$rows) {
    echo '<p>No one is waiting right now.</p>';
}
echo '<div class="table-wrap"><table><tr><th>Plant</th><th>Contact</th><th>When</th><th></th></tr>';
foreach ($rows as $row) {
    echo '<tr><td>' . e($row['name']) . '</td><td>' . e($row['contact']) . '</td><td>' . e($row['created_at']) . '</td>';
    echo '<td><form method="post">' . csrf_field() . '<input type="hidden" name="id" value="' . (int) $row['id'] . '"><button class="btn" type="submit">Done</button></form></td></tr>';
}
echo '</table></div>';
admin_close();
