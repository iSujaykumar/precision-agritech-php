<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $status = ($_POST['status'] ?? '') === 'disabled' ? 'disabled' : 'active';
        $stmt = db()->prepare("SELECT id, role FROM users WHERE id = ? AND role = 'customer'");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            throw new RuntimeException('That customer was not found.');
        }
        db()->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$status, $id]);
        audit_log((int) $admin['id'], 'customer_status', 'user', $id, null, $status);
        flash_set($status === 'active' ? 'Account enabled' : 'Account disabled');
        header('Location: /admin/customer-view?id=' . $id);
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'That customer could not be saved.');
    }
}
$stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();
if ($user && ($user['role'] ?? '') === 'admin') {
    header('Location: /admin/staff');
    exit;
}
admin_open('Customer');
if ($error) {
    echo '<p class="flash bad" role="alert">' . e($error) . '</p>';
}
if (!$user) {
    echo '<p>Not found.</p>';
    admin_close();
    exit;
}
$orders = db()->prepare('SELECT id, order_number, status, total_inr, created_at FROM orders WHERE user_id = ? ORDER BY id DESC');
$orders->execute([$id]);
echo '<p>' . e($user['name']) . ' · ' . e($user['email']) . ' · ' . e((string) $user['phone']) . '</p>';
echo '<p>Last sign-in: ' . e((string) ($user['last_login_at'] ?: '—')) . '</p>';
echo '<form method="post">' . csrf_field() . '<input type="hidden" name="id" value="' . (int) $user['id'] . '">';
echo '<input type="hidden" name="status" value="' . ($user['status'] === 'disabled' ? 'active' : 'disabled') . '">';
echo '<button class="btn" type="submit" data-confirm="Change this account?">' . ($user['status'] === 'disabled' ? 'Enable account' : 'Disable account') . '</button></form>';
echo '<h2>Orders</h2><div class="table-wrap"><table>';
foreach ($orders as $row) {
    echo '<tr><td><a href="/admin/order-view?id=' . (int) $row['id'] . '">' . e($row['order_number']) . '</a></td><td>' . e(order_label((string) $row['status'])) . '</td><td>' . inr((int) $row['total_inr']) . '</td><td>' . e((string) $row['created_at']) . '</td></tr>';
}
echo '</table></div>';
admin_close();
