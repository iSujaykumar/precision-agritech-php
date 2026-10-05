<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    db()->prepare("UPDATE contact_messages SET status = 'handled', handled_at = NOW(), staff_note = ? WHERE id = ?")->execute([trim((string) $_POST['note']), (int) $_POST['id']]);
    audit_log((int) $admin['id'], 'message_handled', 'message', (int) $_POST['id'], null, 'handled');
    header('Location: /admin/messages');
    exit;
}
admin_open('Messages');
foreach (db()->query('SELECT * FROM contact_messages ORDER BY id DESC LIMIT 100') as $row) {
    echo '<p><strong>' . e($row['name']) . '</strong> · ' . e($row['email']) . ' · ' . e((string) $row['status']) . '<br>' . e($row['body']) . '</p>';
    if ($row['status'] !== 'handled') {
        echo '<form method="post">' . csrf_field() . '<input type="hidden" name="id" value="' . (int) $row['id'] . '"><input name="note" placeholder="Note"><button>Mark handled</button></form>';
    }
}
admin_close();
