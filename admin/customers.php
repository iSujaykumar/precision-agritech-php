<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
admin_boot();
$q = trim((string) ($_GET['q'] ?? ''));
$where = " WHERE role = 'customer'";
$params = [];
if ($q !== '') {
    $where .= ' AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)';
    $like = '%' . str_replace(['%', '_'], '', $q) . '%';
    $params = [$like, $like, $like];
}
$count = db()->prepare('SELECT COUNT(*) FROM users' . $where);
$count->execute($params);
[$page, $pages, $offset] = list_bounds((int) $count->fetchColumn(), 25);
$sql = 'SELECT u.id, u.name, u.email, u.phone, u.status, u.last_login_at,
  (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS order_count,
  (SELECT COALESCE(SUM(total_inr),0) FROM orders o WHERE o.user_id = u.id AND o.status <> "cancelled") AS spent
  FROM users u' . $where . ' ORDER BY u.id DESC LIMIT 25 OFFSET ' . (int) $offset;
$stmt = db()->prepare($sql);
$stmt->execute($params);
admin_open('Customers');
echo '<form method="get" class="inline"><label>Search <input name="q" value="' . e($q) . '"></label><button class="btn" type="submit">Search</button></form>';
echo '<div class="table-wrap"><table><tr><th>Name</th><th>Email</th><th>Mobile</th><th>Orders</th><th>Spent</th><th>Last sign-in</th><th>Status</th></tr>';
foreach ($stmt as $row) {
    echo '<tr><td><a href="/admin/customer-view?id=' . (int) $row['id'] . '">' . e($row['name']) . '</a></td><td>' . e($row['email']) . '</td><td>' . e((string) $row['phone']) . '</td><td>' . (int) $row['order_count'] . '</td><td>' . inr((int) $row['spent']) . '</td><td>' . e((string) ($row['last_login_at'] ?: '—')) . '</td><td>' . e((string) $row['status']) . '</td></tr>';
}
echo '</table></div>';
pager_nav($page, $pages);
admin_close();
