<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    db()->prepare("UPDATE wholesale_requests SET status = 'handled', staff_note = ? WHERE id = ?")->execute([trim((string) $_POST['note']), (int) $_POST['id']]);
    audit_log((int) $admin['id'], 'wholesale_handled', 'wholesale', (int) $_POST['id'], null, 'handled');
    header('Location: /admin/wholesale');
    exit;
}
admin_open('Wholesale requests');
foreach (db()->query('SELECT * FROM wholesale_requests ORDER BY id DESC LIMIT 100') as $row) {
    echo '<p><strong>' . e($row['name']) . '</strong> · ' . e($row['phone']) . ' · ' . e($row['products']) . ' · ' . e((string) $row['status']) . '<br>' . e((string) $row['notes']) . '</p>';
    if (($row['status'] ?? '') !== 'handled') {
        echo '<form method="post">' . csrf_field() . '<input type="hidden" name="id" value="' . (int) $row['id'] . '"><input name="note" placeholder="Note"><button>Mark handled</button></form>';
    }
}
admin_close();
