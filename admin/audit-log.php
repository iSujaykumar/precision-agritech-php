<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
admin_boot();
admin_open('Audit log');
$rows = db()->query('SELECT a.*, u.email FROM admin_audit_log a JOIN users u ON u.id = a.admin_user_id ORDER BY a.id DESC LIMIT 200')->fetchAll();
echo '<table><tr><th>When</th><th>Staff</th><th>Action</th><th>Item</th><th>After</th></tr>';
foreach ($rows as $row) {
    echo '<tr><td>' . e($row['created_at']) . '</td><td>' . e($row['email']) . '</td><td>' . e($row['action']) . '</td><td>' . e($row['entity']) . ' ' . (int) $row['entity_id'] . '</td><td>' . e((string) $row['after_text']) . '</td></tr>';
}
echo '</table>';
admin_close();
