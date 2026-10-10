<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        if (($_POST['action'] ?? '') === 'remove') {
            db()->prepare('DELETE FROM cod_blocks WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
        } else {
            $phone = normalize_phone((string) ($_POST['phone'] ?? ''));
            $note = clean_text((string) ($_POST['note'] ?? ''), 200);
            db()->prepare('INSERT INTO cod_blocks (phone, note) VALUES (?, ?) ON DUPLICATE KEY UPDATE note = VALUES(note)')->execute([$phone, $note]);
            audit_log((int) $admin['id'], 'cod_block', 'phone', 0, null, $phone);
        }
        flash_set('List updated');
        header('Location: /admin/cod-block');
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'That number could not be saved.');
    }
}
$rows = db()->query('SELECT * FROM cod_blocks ORDER BY id DESC')->fetchAll();
admin_open('Cash on delivery block list', 'settings');
if (!empty($error)) {
    echo '<p class="flash bad">' . e($error) . '</p>';
}
echo '<form method="post" class="inline">' . csrf_field() . '<label>Mobile <input name="phone" required></label><label>Note <input name="note"></label><button class="btn" type="submit">Block cash on delivery</button></form>';
echo '<div class="table-wrap"><table>';
foreach ($rows as $row) {
    echo '<tr><td>' . e($row['phone']) . '</td><td>' . e((string) $row['note']) . '</td><td><form method="post">' . csrf_field() . '<input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="' . (int) $row['id'] . '"><button class="text-link" type="submit" data-confirm="Remove this block?">Remove</button></form></td></tr>';
}
echo '</table></div>';
admin_close();
