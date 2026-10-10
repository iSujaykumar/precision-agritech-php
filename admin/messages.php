<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        db()->prepare("UPDATE contact_messages SET status = 'handled', handled_at = NOW(), staff_note = ? WHERE id = ?")->execute([trim((string) ($_POST['note'] ?? '')), (int) $_POST['id']]);
        audit_log((int) $admin['id'], 'message_handled', 'message', (int) $_POST['id'], null, 'handled');
        flash_set('Message marked handled');
        header('Location: /admin/messages');
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'That message could not be updated.');
    }
}
$filter = (string) ($_GET['status'] ?? '');
$where = '';
$params = [];
if (in_array($filter, ['new', 'handled'], true)) {
    $where = ' WHERE status = ?';
    $params[] = $filter;
}
$count = db()->prepare('SELECT COUNT(*) FROM contact_messages' . $where);
$count->execute($params);
[$page, $pages, $offset] = list_bounds((int) $count->fetchColumn(), 25);
$stmt = db()->prepare('SELECT * FROM contact_messages' . $where . ' ORDER BY id DESC LIMIT 25 OFFSET ' . (int) $offset);
$stmt->execute($params);
admin_open('Messages', 'enquiries');
if ($error) {
    echo '<p class="flash bad" role="alert">' . e($error) . '</p>';
}
echo '<nav class="subnav"><a href="/admin/messages">All</a><a href="/admin/messages?status=new">New</a><a href="/admin/messages?status=handled">Handled</a></nav>';
echo '<div class="table-wrap"><table><tr><th>Date</th><th>From</th><th>Message</th><th>Status</th><th></th></tr>';
foreach ($stmt as $row) {
    echo '<tr><td>' . e((string) $row['created_at']) . '</td><td>' . e($row['name']) . '<br>' . e($row['email']) . '</td><td>' . e($row['body']);
    if (!empty($row['staff_note'])) {
        echo '<br><span class="muted">' . e((string) $row['staff_note']) . '</span>';
    }
    echo '</td><td>' . e((string) $row['status']) . '</td><td>';
    if ($row['status'] !== 'handled') {
        echo '<form method="post">' . csrf_field() . '<input type="hidden" name="id" value="' . (int) $row['id'] . '"><label>Note <input name="note"></label><button class="btn" type="submit">Mark handled</button></form>';
    }
    echo '</td></tr>';
}
echo '</table></div>';
pager_nav($page, $pages);
admin_close();
