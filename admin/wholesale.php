<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        db()->prepare("UPDATE wholesale_requests SET status = 'handled', staff_note = ? WHERE id = ?")->execute([trim((string) ($_POST['note'] ?? '')), (int) $_POST['id']]);
        audit_log((int) $admin['id'], 'wholesale_handled', 'wholesale', (int) $_POST['id'], null, 'handled');
        flash_set('Request marked handled');
        header('Location: /admin/wholesale');
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'That request could not be updated.');
    }
}
$filter = (string) ($_GET['status'] ?? '');
$where = '';
$params = [];
if (in_array($filter, ['new', 'handled'], true)) {
    $where = ' WHERE status = ?';
    $params[] = $filter;
}
$count = db()->prepare('SELECT COUNT(*) FROM wholesale_requests' . $where);
$count->execute($params);
[$page, $pages, $offset] = list_bounds((int) $count->fetchColumn(), 25);
$stmt = db()->prepare('SELECT * FROM wholesale_requests' . $where . ' ORDER BY id DESC LIMIT 25 OFFSET ' . (int) $offset);
$stmt->execute($params);
admin_open('Wholesale', 'enquiries');
if ($error) {
    echo '<p class="flash bad" role="alert">' . e($error) . '</p>';
}
echo '<nav class="subnav"><a href="/admin/wholesale">All</a><a href="/admin/wholesale?status=new">New</a><a href="/admin/wholesale?status=handled">Handled</a></nav>';
echo '<div class="table-wrap"><table><tr><th>Date</th><th>From</th><th>Request</th><th>Status</th><th></th></tr>';
foreach ($stmt as $row) {
    echo '<tr><td>' . e((string) $row['created_at']) . '</td><td>' . e($row['name']) . '<br>' . e($row['phone']) . '</td><td>' . e($row['products']) . ' · ' . e($row['quantity']) . '<br>' . e((string) $row['notes']);
    if (!empty($row['staff_note'])) {
        echo '<br><span class="muted">' . e((string) $row['staff_note']) . '</span>';
    }
    echo '</td><td>' . e((string) $row['status']) . '</td><td>';
    if (($row['status'] ?? '') !== 'handled') {
        echo '<form method="post">' . csrf_field() . '<input type="hidden" name="id" value="' . (int) $row['id'] . '"><label>Note <input name="note"></label><button class="btn" type="submit">Mark handled</button></form>';
    }
    echo '</td></tr>';
}
echo '</table></div>';
pager_nav($page, $pages);
admin_close();
