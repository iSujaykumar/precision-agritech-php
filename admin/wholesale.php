<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        db()->prepare("UPDATE wholesale_requests SET status = 'handled', staff_note = ? WHERE id = ?")->execute([trim((string) $_POST['note']), (int) $_POST['id']]);
        audit_log((int) $admin['id'], 'wholesale_handled', 'wholesale', (int) $_POST['id'], null, 'handled');
        header('Location: /admin/wholesale');
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'That request could not be updated.');
    }
}
admin_open('Wholesale requests');
if (!empty($error)) echo '<p class="flash">' . e($error) . '</p>';
foreach (db()->query('SELECT * FROM wholesale_requests ORDER BY id DESC LIMIT 100') as $row) {
    echo '<p><strong>' . e($row['name']) . '</strong> · ' . e($row['phone']) . ' · ' . e($row['products']) . ' · ' . e((string) $row['status']) . '<br>' . e((string) $row['notes']) . '</p>';
    if (($row['status'] ?? '') !== 'handled') {
        echo '<form method="post">' . csrf_field() . '<input type="hidden" name="id" value="' . (int) $row['id'] . '"><input name="note" placeholder="Note"><button>Mark handled</button></form>';
    }
}
admin_close();
