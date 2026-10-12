<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
$date = (string) ($_GET['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        if (($_POST['action'] ?? '') === 'handover') {
            $driverId = (int) ($_POST['driver_id'] ?? 0);
            db()->prepare("UPDATE orders SET cash_handed_at = NOW() WHERE driver_id = ? AND delivery_date = ? AND payment_method = 'cod' AND payment_status = 'paid' AND cash_handed_at IS NULL")
                ->execute([$driverId, $date]);
            audit_log((int) $admin['id'], 'cash_handover', 'driver', $driverId, $date, 'handed');
            flash_set('Cash marked as handed over');
        }
        header('Location: /admin/deliveries?date=' . rawurlencode($date));
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'That could not be saved.');
    }
}
$stmt = db()->prepare("SELECT o.*, d.name AS driver_name FROM orders o LEFT JOIN drivers d ON d.id = o.driver_id WHERE o.delivery_date = ? AND o.status NOT IN ('cancelled') ORDER BY d.name, o.id");
$stmt->execute([$date]);
$rows = $stmt->fetchAll();
$groups = [];
foreach ($rows as $row) {
    $groups[(string) ($row['driver_name'] ?? 'Unassigned')][] = $row;
}
admin_open('Today\'s deliveries');
if (!empty($error)) {
    echo '<p class="flash bad">' . e($error) . '</p>';
}
echo '<form method="get" class="inline"><label>Date <input type="date" name="date" value="' . e($date) . '"></label><button class="btn" type="submit">Show</button> <button class="btn" type="button" data-print="1">Print</button></form>';
foreach ($groups as $name => $list) {
    $collect = 0;
    echo '<h2>' . e($name) . '</h2><div class="table-wrap"><table><tr><th>Order</th><th>Address</th><th>PIN</th><th>Phone</th><th>Items</th><th>Collect</th><th>Payment</th></tr>';
    foreach ($list as $row) {
        $items = db()->prepare('SELECT product_name, quantity FROM order_items WHERE order_id = ?');
        $items->execute([(int) $row['id']]);
        $names = [];
        foreach ($items as $item) {
            $names[] = (int) $item['quantity'] . ' ' . $item['product_name'];
        }
        $due = ($row['payment_method'] === 'cod' && $row['payment_status'] !== 'paid') ? (int) $row['total_inr'] : 0;
        $collect += $due;
        echo '<tr><td><a href="/admin/order-view?id=' . (int) $row['id'] . '">' . e($row['order_number']) . '</a></td><td>' . e($row['address_line']) . '</td><td>' . e($row['postal_code']) . '</td><td>' . e($row['customer_phone']) . '</td><td>' . e(implode(', ', $names)) . '</td><td>' . inr($due) . '</td><td>' . e((string) $row['payment_status']) . '</td></tr>';
    }
    echo '</table></div><p>Collect ' . inr($collect) . '</p>';
}
$cash = db()->prepare("SELECT d.id, d.name, COALESCE(SUM(o.cash_collected_inr),0) AS total FROM drivers d LEFT JOIN orders o ON o.driver_id = d.id AND o.delivery_date = ? AND o.payment_method = 'cod' AND o.payment_status = 'paid' AND o.cash_handed_at IS NULL GROUP BY d.id, d.name");
$cash->execute([$date]);
echo '<h2>Cash with drivers</h2><div class="table-wrap"><table>';
foreach ($cash as $row) {
    echo '<tr><td>' . e($row['name']) . '</td><td>' . inr((int) $row['total']) . '</td><td><form method="post">' . csrf_field() . '<input type="hidden" name="action" value="handover"><input type="hidden" name="driver_id" value="' . (int) $row['id'] . '"><button class="btn" type="submit">Cash handed over</button></form></td></tr>';
}
echo '</table></div>';
echo '<script src="/assets/js/pay.js?v=' . (int) @filemtime(dirname(__DIR__) . '/assets/js/pay.js') . '"></script>';
admin_close();
