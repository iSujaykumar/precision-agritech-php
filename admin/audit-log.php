<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
admin_boot();
$staff = (int) ($_GET['staff'] ?? 0);
$action = trim((string) ($_GET['action'] ?? ''));
$date = trim((string) ($_GET['date'] ?? ''));
$where = ' WHERE 1=1';
$params = [];
if ($staff > 0) {
    $where .= ' AND a.admin_user_id = ?';
    $params[] = $staff;
}
if ($action !== '') {
    $where .= ' AND a.action = ?';
    $params[] = $action;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $where .= ' AND DATE(a.created_at) = ?';
    $params[] = $date;
}
$count = db()->prepare('SELECT COUNT(*) FROM admin_audit_log a' . $where);
$count->execute($params);
[$page, $pages, $offset] = list_bounds((int) $count->fetchColumn(), 50);
$stmt = db()->prepare('SELECT a.*, u.email FROM admin_audit_log a JOIN users u ON u.id = a.admin_user_id' . $where . ' ORDER BY a.id DESC LIMIT 50 OFFSET ' . (int) $offset);
$stmt->execute($params);
$people = db()->query("SELECT id, email FROM users WHERE role = 'admin' ORDER BY email")->fetchAll();
admin_open('Activity log', 'settings');
echo '<form method="get" class="inline"><label>Staff <select name="staff"><option value="0">All</option>';
foreach ($people as $person) {
    echo '<option value="' . (int) $person['id'] . '"' . ($staff === (int) $person['id'] ? ' selected' : '') . '>' . e($person['email']) . '</option>';
}
echo '</select></label><label>Action <input name="action" value="' . e($action) . '"></label><label>Date <input name="date" type="date" value="' . e($date) . '"></label><button class="btn" type="submit">Filter</button></form>';
echo '<div class="table-wrap"><table><tr><th>When</th><th>Staff</th><th>Action</th><th>Item</th><th>After</th></tr>';
foreach ($stmt as $row) {
    echo '<tr><td>' . e($row['created_at']) . '</td><td>' . e($row['email']) . '</td><td>' . e($row['action']) . '</td><td>' . e($row['entity']) . ' ' . (int) $row['entity_id'] . '</td><td>' . e((string) $row['after_text']) . '</td></tr>';
}
echo '</table></div>';
pager_nav($page, $pages);
admin_close();
