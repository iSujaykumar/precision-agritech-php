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
        apply_order_status(
            $pdo,
            $id,
            (string) ($_POST['status'] ?? ''),
            (int) $admin['id'],
            trim((string) ($_POST['note'] ?? '')),
            isset($_POST['public_note'])
        );
        $pdo->commit();
        flash('success', 'Order updated.');
        header('Location: /admin/order-view?id=' . $id);
        exit;
    } catch (Throwable $err) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = safe_error($err, 'That status change was not saved.');
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
$events = db()->prepare('SELECT * FROM order_events WHERE order_id = ? ORDER BY id');
$events->execute([$id]);
if ($error) {
    echo '<p class="flash error" role="alert">' . e($error) . '</p>';
}
echo '<p>' . e($row['order_number']) . ' · ' . e(status_label((string) $row['status'])) . ' · ' . e(status_label((string) $row['payment_status'])) . ' · ' . e(status_label((string) $row['payment_method'])) . '</p>';
echo '<p>' . e(fmt_dt((string) $row['created_at'])) . ' · trays ' . inr((int) $row['subtotal_inr']) . ' · discount ' . inr((int) $row['discount_inr']) . ' · delivery ' . inr((int) $row['shipping_inr']) . ' · total ' . inr((int) $row['total_inr']) . '</p>';
echo '<p>' . e($row['customer_name']) . ' · ' . e($row['customer_phone']) . ' · ' . e($row['customer_email']) . '</p>';
echo '<p>' . e($row['address_line']) . ', ' . e($row['city']) . ', ' . e($row['state_name']) . ' ' . e($row['postal_code']) . '</p>';
if (!empty($row['cancel_requested'])) {
    echo '<p class="flash info">The customer asked to cancel this order.</p>';
}
echo '<div class="table-scroll"><table><thead><tr><th>Tray</th><th>SKU</th><th>Qty</th><th>Total</th></tr></thead>';
foreach ($items as $item) {
    echo '<tr><td>' . e($item['product_name']) . '</td><td>' . e($item['sku']) . '</td><td>' . (int) $item['quantity'] . '</td><td>' . inr((int) $item['line_total_inr']) . '</td></tr>';
}
echo '</table></div>';
echo '<h2>History</h2>';
foreach ($events as $event) {
    echo '<p>' . e(fmt_dt((string) $event['created_at'])) . ' · ' . e(status_label((string) $event['status'])) . ' · ' . e((string) $event['note']) . '</p>';
}
$next = order_next_statuses((string) $row['status']);
if ($next) {
    echo '<form method="post">' . csrf_field() . '<input type="hidden" name="id" value="' . (int) $row['id'] . '">';
    echo '<label>Next step <select name="status" required><option value="">Choose</option>';
    foreach ($next as $status) {
        echo '<option value="' . e($status) . '">' . e(status_label($status)) . '</option>';
    }
    echo '</select></label><label>Note <textarea name="note"></textarea></label>';
    echo '<label><input type="checkbox" name="public_note" value="1" checked> Show the note to the customer</label>';
    echo '<button class="btn">Update</button></form>';
}
echo '<p><a href="/order/' . e($row['lookup_token']) . '">Customer link</a></p>';
admin_close();
