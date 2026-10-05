<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
$id = (int) ($_GET['id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'customer';
    $status = ($_POST['status'] ?? '') === 'disabled' ? 'disabled' : 'active';
    db()->prepare('UPDATE users SET role = ?, status = ? WHERE id = ? AND id <> ?')->execute([$role, $status, $id, (int) $admin['id']]);
    audit_log((int) $admin['id'], 'customer_update', 'user', $id, null, $role . ' ' . $status);
    header('Location: /admin/customer-view?id=' . $id);
    exit;
}
$stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch();
admin_open('Customer');
if (!$user) {
    echo '<p>Not found.</p>';
    admin_close();
    exit;
}
echo '<p>' . e($user['name']) . ' · ' . e($user['email']) . ' · ' . e((string) $user['phone']) . '</p>';
echo '<p>Mobile verified: ' . ($user['phone_verified_at'] ? e($user['phone_verified_at']) : 'no') . '</p>';
echo '<form method="post">' . csrf_field() . '<label>Role <select name="role"><option value="customer">customer</option><option value="admin" ' . ($user['role'] === 'admin' ? 'selected' : '') . '>admin</option></select></label><label>Status <select name="status"><option value="active">active</option><option value="disabled" ' . ($user['status'] === 'disabled' ? 'selected' : '') . '>disabled</option></select></label><button class="btn">Save</button></form>';
admin_close();
