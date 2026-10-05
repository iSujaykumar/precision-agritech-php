<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
$error = null;
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $pdo = db();
        $pdo->beginTransaction();
        apply_order_status($pdo, $id, (string) ($_POST['status'] ?? ''), (int) $admin['id']);
        $pdo->commit();
        header('Location: /admin/order-view?id=' . $id);
        exit;
    } catch (Throwable $err) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'That status change was not saved.';
        error_log($err->getMessage());
    }
}
$order = db()->prepare('SELECT * FROM orders WHERE id = ?');
$order->execute([$id]);
$row = $order->fetch();
if (!$row) {
    admin_open('Order');
    echo '<p>Order not found.</p>';
    admin_close();
    exit;
}
admin_open('Order ' . $row['order_number']);
$items = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
$items->execute([$id]);
if ($error) {
    echo '<p class="flash">' . e($error) . '</p>';
}
echo '<p>' . e($row['order_number']) . ' · ' . e($row['status']) . ' · ' . e($row['payment_status']) . ' · stock ' . e($row['inventory_state']) . '</p>';
echo '<p>' . e($row['customer_name']) . ' · ' . e($row['customer_phone']) . ' · ' . e($row['customer_email']) . '</p>';
echo '<p>' . e($row['address_line']) . ', ' . e($row['city']) . ' ' . e($row['postal_code']) . '</p>';
echo '<table>';
foreach ($items as $item) {
    echo '<tr><td>' . e($item['product_name']) . '</td><td>' . (int) $item['quantity'] . '</td><td>' . inr((int) $item['line_total_inr']) . '</td></tr>';
}
echo '</table>';
echo '<form method="post">' . csrf_field() . '<input type="hidden" name="id" value="' . (int) $row['id'] . '">';
echo '<label>Next status <select name="status">';
foreach (['payment_pending', 'payment_confirmed', 'preparing', 'dispatched', 'delivered', 'cancelled'] as $status) {
    echo '<option>' . e($status) . '</option>';
}
echo '</select></label><button class="btn">Update</button></form>';
admin_close();
