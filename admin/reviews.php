<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $status = in_array($_POST['status'] ?? '', ['approved', 'rejected', 'pending'], true) ? $_POST['status'] : 'pending';
    db()->prepare('UPDATE reviews SET status = ? WHERE id = ?')->execute([$status, (int) $_POST['id']]);
    audit_log((int) $admin['id'], 'review', 'review', (int) $_POST['id'], null, $status);
    header('Location: /admin/reviews');
    exit;
}
admin_open('Reviews');
$rows = db()->query('SELECT r.*, p.name FROM reviews r JOIN products p ON p.id = r.product_id ORDER BY r.id DESC LIMIT 100')->fetchAll();
foreach ($rows as $row) {
    echo '<p><strong>' . e($row['author_name']) . '</strong> on ' . e($row['name']) . ' · ' . (int) $row['rating'] . '/5 · ' . e($row['status']) . '<br>' . e($row['body']) . '</p>';
    echo '<form method="post">' . csrf_field() . '<input type="hidden" name="id" value="' . (int) $row['id'] . '"><button name="status" value="approved">Approve</button> <button name="status" value="rejected">Reject</button></form>';
}
admin_close();
