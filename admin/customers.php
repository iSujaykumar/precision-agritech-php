<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
admin_boot();
admin_open('Customers');
$rows = db()->query("SELECT id, name, email, phone, phone_verified_at, role, status, created_at FROM users ORDER BY id DESC LIMIT 200")->fetchAll();
echo '<table><tr><th>Name</th><th>Email</th><th>Mobile</th><th>Role</th></tr>';
foreach ($rows as $row) {
    echo '<tr><td><a href="/admin/customer-view?id=' . (int) $row['id'] . '">' . e($row['name']) . '</a></td><td>' . e($row['email']) . '</td><td>' . e((string) $row['phone']) . '</td><td>' . e($row['role']) . '</td></tr>';
}
echo '</table>';
admin_close();
