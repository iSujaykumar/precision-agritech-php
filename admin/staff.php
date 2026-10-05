<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
admin_boot();
admin_open('Staff');
$rows = db()->query("SELECT id, name, email, phone, status FROM users WHERE role = 'admin' ORDER BY id")->fetchAll();
echo '<table>';
foreach ($rows as $row) {
    echo '<tr><td>' . e($row['name']) . '</td><td>' . e($row['email']) . '</td><td>' . e((string) $row['status']) . '</td><td><a href="/admin/customer-view?id=' . (int) $row['id'] . '">Edit</a></td></tr>';
}
echo '</table>';
admin_close();
