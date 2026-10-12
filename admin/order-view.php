<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
$error = null;
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $action = (string) ($_POST['action'] ?? 'status');
        if ($action === 'note') {
            $note = clean_text((string) ($_POST['internal_note'] ?? ''), 500);
            db()->prepare('UPDATE orders SET internal_note = ? WHERE id = ?')->execute([$note, $id]);
            audit_log((int) $admin['id'], 'order_note', 'order', $id, null, $note);
            flash_set('Note saved');
        } elseif ($action === 'paid') {
            $pdo = db();
            $pdo->beginTransaction();
            $current = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            $current->execute([$id]);
            $row = $current->fetch();
            if (!$row) {
                throw new RuntimeException('Order not found.');
            }
            if ((string) $row['payment_status'] === 'paid') {
                throw new RuntimeException('This order is already paid.');
            }
            if ((string) $row['status'] === 'delivered') {
                throw new RuntimeException('This order is already delivered.');
            }
            $amount = (int) ($_POST['amount'] ?? 0);
            if ($amount < 1) {
                throw new RuntimeException('Enter the amount received. It has to be at least 1.');
            }
            $date = (string) ($_POST['paid_on'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                throw new RuntimeException('Enter the date the payment arrived.');
            }
            $total = (int) $row['total_inr'];
            $already = (int) ($row['paid_amount_inr'] ?? 0);
            $gap = (string) ($_POST['pay_gap'] ?? '');
            $note = clean_text((string) ($_POST['pay_note'] ?? ''), 200);
            if (($already + $amount) !== $total) {
                if (!in_array($gap, ['partial', 'overpaid'], true) || strlen($note) < 3) {
                    throw new RuntimeException('This amount is not the order total. Choose partial payment or overpaid, and write a note.');
                }
            }
            $ref = (string) ($row['payment_ref'] ?? '');
            if ($ref !== '') {
                $dup = $pdo->prepare('SELECT order_number FROM orders WHERE payment_ref = ? AND id <> ? LIMIT 1');
                $dup->execute([$ref, $id]);
                $other = $dup->fetchColumn();
                if ($other && (string) ($_POST['confirm_duplicate'] ?? '') !== '1') {
                    throw new RuntimeException('This reference is already on order ' . $other . '. Tick the box to continue.');
                }
            }
            if ((string) $row['status'] === 'cancelled') {
                if ((string) ($_POST['reopen'] ?? '') !== '1') {
                    throw new RuntimeException('This order expired. Re-open it?');
                }
                $items = $pdo->prepare('SELECT product_id, quantity, product_name FROM order_items WHERE order_id = ? AND product_id IS NOT NULL');
                $items->execute([$id]);
                foreach ($items as $item) {
                    $stock = $pdo->prepare('SELECT name, stock_qty, reserved_qty FROM products WHERE id = ? FOR UPDATE');
                    $stock->execute([(int) $item['product_id']]);
                    $product = $stock->fetch();
                    $free = $product ? ((int) $product['stock_qty'] - (int) $product['reserved_qty']) : 0;
                    if (!$product || $free < (int) $item['quantity']) {
                        $name = $product['name'] ?? $item['product_name'];
                        throw new RuntimeException($name . ' is short by ' . ((int) $item['quantity'] - $free) . ' tray(s). The order was not re-opened.');
                    }
                }
                $items->execute([$id]);
                foreach ($items as $item) {
                    $upd = $pdo->prepare('UPDATE products SET reserved_qty = reserved_qty + ? WHERE id = ? AND stock_qty - reserved_qty >= ?');
                    $upd->execute([(int) $item['quantity'], (int) $item['product_id'], (int) $item['quantity']]);
                    if ($upd->rowCount() !== 1) {
                        throw new RuntimeException('Those trays were just reserved by another order.');
                    }
                }
                $pdo->prepare("UPDATE orders SET status = 'payment_pending', inventory_state = 'reserved', cancel_reason = NULL WHERE id = ?")->execute([$id]);
            }
            $sum = $already + $amount;
            if ($gap === 'partial' && $sum < $total) {
                $pdo->prepare("UPDATE orders SET payment_status = 'partial', paid_amount_inr = ?, paid_on = ?, paid_by = ?, payment_note = ? WHERE id = ?")
                    ->execute([$sum, $date, (int) $admin['id'], $note, $id]);
                $pdo->commit();
                audit_log((int) $admin['id'], 'payment_partial', 'order', $id, (string) $total, (string) $sum . ' ' . $note);
                flash_set('Partial payment recorded. The balance is still due.');
            } else {
                if (!in_array((string) $row['status'], ['payment_pending', 'payment_submitted', 'placed', 'cancelled'], true) && (string) $row['status'] !== 'payment_confirmed') {
                    $fresh = $pdo->prepare('SELECT status FROM orders WHERE id = ?');
                    $fresh->execute([$id]);
                    $now = (string) $fresh->fetchColumn();
                    if (!in_array($now, ['payment_pending', 'payment_submitted', 'placed'], true)) {
                        throw new RuntimeException('This order cannot be marked paid from its current status.');
                    }
                }
                $live = $pdo->prepare('SELECT status, inventory_state FROM orders WHERE id = ?');
                $live->execute([$id]);
                $liveRow = $live->fetch();
                if ($liveRow && $liveRow['inventory_state'] === 'reserved' && in_array($liveRow['status'], ['placed', 'payment_pending', 'payment_submitted'], true)) {
                    apply_order_status($pdo, $id, 'payment_confirmed', (int) $admin['id']);
                }
                $pdo->prepare("UPDATE orders SET payment_status = 'paid', paid_amount_inr = ?, paid_on = ?, paid_by = ?, payment_note = CONCAT(COALESCE(payment_note, ''), ' ', ?) WHERE id = ?")
                    ->execute([$sum, $date, (int) $admin['id'], $note, $id]);
                $pdo->commit();
                audit_log((int) $admin['id'], 'payment_received', 'order', $id, (string) $total, (string) $sum . ' ' . $gap);
                $sent = db()->prepare('SELECT * FROM orders WHERE id = ?');
                $sent->execute([$id]);
                $order = $sent->fetch();
                if ($order) {
                    mail_order_customer($order, 'Payment received. We will prepare your trays.');
                }
                flash_set('Payment marked as received');
            }
        } elseif ($action === 'missing') {
            db()->prepare("INSERT INTO order_events (order_id, status, note) VALUES (?, 'payment_pending', 'Payment was not found')")->execute([$id]);
            $sent = db()->prepare('SELECT * FROM orders WHERE id = ?');
            $sent->execute([$id]);
            $order = $sent->fetch();
            if ($order) {
                mail_order_customer($order, 'We could not find that payment yet. The trays stay reserved until the time on your order.');
            }
            audit_log((int) $admin['id'], 'payment_missing', 'order', $id, null, 'missing');
            flash_set('Customer told that the payment was not found');
        } elseif ($action === 'refund') {
            $amount = (int) ($_POST['amount'] ?? 0);
            $method = clean_text((string) ($_POST['method'] ?? ''), 40);
            $utr = clean_text((string) ($_POST['utr'] ?? ''), 80);
            $date = (string) ($_POST['paid_on'] ?? '');
            if ($amount < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $method === '' || $utr === '') {
                throw new RuntimeException('Enter the refund amount, method, reference and date.');
            }
            $pdo = db();
            $pdo->beginTransaction();
            $current = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            $current->execute([$id]);
            $row = $current->fetch();
            if (!$row) {
                throw new RuntimeException('Order not found.');
            }
            $paid = (int) ($row['paid_amount_inr'] ?? 0);
            if ($paid < 1 && in_array((string) $row['payment_status'], ['paid', 'refund_due', 'partially_refunded'], true)) {
                $paid = (int) $row['total_inr'];
            }
            $done = $pdo->prepare('SELECT COALESCE(SUM(amount_inr), 0) FROM refunds WHERE order_id = ?');
            $done->execute([$id]);
            $already = (int) $done->fetchColumn();
            if ($paid < 1 || $already + $amount > $paid) {
                throw new RuntimeException('Refunds cannot be more than the amount paid.');
            }
            $pdo->prepare('INSERT INTO refunds (order_id, amount_inr, method, utr, paid_on, admin_user_id) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$id, $amount, $method, $utr, $date, (int) $admin['id']]);
            $left = $paid - ($already + $amount);
            $next = $left === 0 ? 'refunded' : 'partially_refunded';
            $pdo->prepare('UPDATE orders SET payment_status = ? WHERE id = ?')->execute([$next, $id]);
            $pdo->commit();
            audit_log((int) $admin['id'], 'refund', 'order', $id, (string) $paid, $next . ' ' . $amount . ' ' . $utr);
            $sent = db()->prepare('SELECT * FROM orders WHERE id = ?');
            $sent->execute([$id]);
            if ($mail = $sent->fetch()) {
                mail_order_customer($mail, 'A refund of ' . inr($amount) . ' was sent by ' . $method . '. Reference ' . $utr . '.');
            }
            flash_set('Refund recorded');
        } elseif ($action === 'confirm') {
            $pdo = db();
            $pdo->beginTransaction();
            $current = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            $current->execute([$id]);
            $row = $current->fetch();
            if (!$row) {
                throw new RuntimeException('Order not found.');
            }
            if ((int) $row['phone_confirmed'] === 1) {
                throw new RuntimeException('This order was already confirmed by phone.');
            }
            if (!in_array((string) $row['status'], ['placed', 'payment_confirmed'], true)) {
                throw new RuntimeException('Confirm by phone only while the order is new or paid.');
            }
            apply_order_status($pdo, $id, 'confirmed', (int) $admin['id']);
            $pdo->prepare('UPDATE orders SET phone_confirmed = 1 WHERE id = ?')->execute([$id]);
            $pdo->commit();
            audit_log((int) $admin['id'], 'phone_confirm', 'order', $id, (string) $row['status'], 'confirmed');
            $sent = db()->prepare('SELECT * FROM orders WHERE id = ?');
            $sent->execute([$id]);
            if ($mail = $sent->fetch()) {
                mail_order_customer($mail, 'Your order is confirmed.');
            }
            flash_set('Confirmed by phone');
        } elseif ($action === 'assign') {
            $driver = (int) ($_POST['driver_id'] ?? 0);
            $date = (string) ($_POST['delivery_date'] ?? '');
            $slot = (string) ($_POST['delivery_slot'] ?? '');
            $pdo = db();
            $current = $pdo->prepare('SELECT status FROM orders WHERE id = ?');
            $current->execute([$id]);
            $status = (string) $current->fetchColumn();
            if ($status === '' || in_array($status, ['cancelled', 'delivered'], true)) {
                throw new RuntimeException('This order cannot be assigned.');
            }
            if ($driver === 0) {
                $pdo->prepare('UPDATE orders SET driver_id = NULL, delivery_date = NULL, delivery_slot = NULL WHERE id = ?')->execute([$id]);
                audit_log((int) $admin['id'], 'driver_clear', 'order', $id, null, 'cleared');
                flash_set('Driver assignment cleared');
            } else {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < date('Y-m-d') || !in_array($slot, ['Morning', 'Afternoon', 'Evening'], true)) {
                    throw new RuntimeException('Choose an active driver, today or a later date, and a time.');
                }
                $who = $pdo->prepare('SELECT id FROM drivers WHERE id = ? AND active = 1');
                $who->execute([$driver]);
                if (!$who->fetch()) {
                    throw new RuntimeException('Choose an active driver.');
                }
                $pdo->prepare('UPDATE orders SET driver_id = ?, delivery_date = ?, delivery_slot = ? WHERE id = ?')->execute([$driver, $date, $slot, $id]);
                audit_log((int) $admin['id'], 'driver_assign', 'order', $id, null, (string) $driver . ' ' . $date . ' ' . $slot);
                flash_set('Driver assigned');
            }
        } elseif ($action === 'cash') {
            $amount = (int) ($_POST['amount'] ?? 0);
            $reason = clean_text((string) ($_POST['cash_reason'] ?? ''), 160);
            $pdo = db();
            $pdo->beginTransaction();
            $current = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            $current->execute([$id]);
            $row = $current->fetch();
            if (!$row) {
                throw new RuntimeException('Order not found.');
            }
            if ((string) $row['payment_method'] !== 'cod') {
                throw new RuntimeException('Cash collection is only for cash on delivery orders.');
            }
            if ((string) $row['payment_status'] === 'paid' || $row['cash_collected_inr'] !== null) {
                throw new RuntimeException('Already collected.');
            }
            if (!in_array((string) $row['status'], ['out_for_delivery', 'dispatched'], true)) {
                throw new RuntimeException('Mark the order out for delivery before collecting cash.');
            }
            if ($amount < 1) {
                throw new RuntimeException('Enter the cash collected.');
            }
            if ($amount !== (int) $row['total_inr'] && strlen($reason) < 3) {
                throw new RuntimeException('This is not the order total. Write a short reason, such as short change or a discount.');
            }
            if ($row['inventory_state'] === 'reserved') {
                $pdo->prepare('UPDATE products p JOIN order_items oi ON oi.product_id = p.id SET p.reserved_qty = GREATEST(0, p.reserved_qty - oi.quantity), p.stock_qty = GREATEST(0, p.stock_qty - oi.quantity) WHERE oi.order_id = ?')->execute([$id]);
                $pdo->prepare("UPDATE orders SET inventory_state = 'sold' WHERE id = ?")->execute([$id]);
            }
            apply_order_status($pdo, $id, 'delivered', (int) $admin['id']);
            $pdo->prepare("UPDATE orders SET payment_status = 'paid', paid_amount_inr = ?, cash_collected_inr = ?, cash_reason = ?, collected_by = ?, collected_at = NOW() WHERE id = ?")
                ->execute([$amount, $amount, $reason !== '' ? $reason : null, (int) $admin['id'], $id]);
            $pdo->commit();
            audit_log((int) $admin['id'], 'cash_collected', 'order', $id, (string) $row['total_inr'], (string) $amount . ' ' . $reason);
            $sent = db()->prepare('SELECT * FROM orders WHERE id = ?');
            $sent->execute([$id]);
            if ($mail = $sent->fetch()) {
                mail_order_customer($mail, 'Your trays were delivered and the cash was collected.');
            }
            flash_set('Delivered and cash collected');
        } elseif ($action === 'proof') {
            $path = save_upload_webp($_FILES['proof'], '/uploads/private/proof', 200, 200);
            db()->prepare('INSERT INTO order_files (order_id, kind, path, created_by) VALUES (?, ?, ?, ?)')->execute([$id, 'proof', $path, (int) $admin['id']]);
            audit_log((int) $admin['id'], 'delivery_proof', 'order', $id, null, $path);
            flash_set('Delivery photo saved');
        } else {
            $status = (string) ($_POST['status'] ?? '');
            $pdo = db();
            $pdo->beginTransaction();
            apply_order_status($pdo, $id, $status, (int) $admin['id']);
            $pdo->commit();
            if (in_array($status, ['payment_confirmed', 'confirmed', 'dispatched', 'out_for_delivery', 'delivered', 'cancelled', 'delivery_failed'], true)) {
                $mailOrder = db()->prepare('SELECT * FROM orders WHERE id = ?');
                $mailOrder->execute([$id]);
                $sent = $mailOrder->fetch();
                if ($sent) {
                    $intro = match ($status) {
                        'payment_confirmed' => 'Payment received.',
                        'confirmed' => 'Your order is confirmed.',
                        'dispatched', 'out_for_delivery' => 'Your trays are out for delivery.',
                        'delivered' => 'Your trays were delivered.',
                        'cancelled' => 'This order is cancelled.',
                        'delivery_failed' => 'We could not complete the delivery. The nursery will contact you.',
                        default => 'Your order was updated.',
                    };
                    mail_order_customer($sent, $intro);
                }
            }
            flash_set('Order updated');
        }
        header('Location: /admin/order-view?id=' . $id);
        exit;
    } catch (Throwable $err) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = safe_error($err, 'That change was not saved.');
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
$items = db()->prepare('SELECT oi.*, p.image_url FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?');
$items->execute([$id]);
$itemRows = $items->fetchAll();
$events = db()->prepare('SELECT status, note, created_at FROM order_events WHERE order_id = ? ORDER BY id');
$events->execute([$id]);
$coupon = db()->prepare('SELECT c.code, r.amount_inr FROM coupon_redemptions r JOIN coupons c ON c.id = r.coupon_id WHERE r.order_id = ? LIMIT 1');
$coupon->execute([$id]);
$couponRow = $coupon->fetch();
$phone = preg_replace('/\D+/', '', (string) $row['customer_phone']) ?? '';
if (strlen($phone) === 10) {
    $phone = '91' . $phone;
}
$wa = $phone !== '' ? 'https://wa.me/' . $phone . '?text=' . rawurlencode('Hello, this is Precision Agritech about order ' . $row['order_number'] . '.') : '';
admin_open('Order ' . $row['order_number']);
if ($error) {
    echo '<p class="flash bad" role="alert">' . e($error) . '</p>';
}
?>
<article class="packing">
  <p><?= e(order_label((string) $row['status'])) ?> · Payment <?= e((string) $row['payment_status']) ?>
    <?php if (!empty($row['payment_ref'])): ?> · Reference <?= e((string) $row['payment_ref']) ?><?php endif; ?></p>
  <?php if (empty($row['phone_verified'])): ?>
    <?php
    $owner = null;
    if (!empty($row['user_id'])) {
        $u = db()->prepare('SELECT phone_verified_at FROM users WHERE id = ?');
        $u->execute([(int) $row['user_id']]);
        $owner = $u->fetch();
    }
    if (!$owner || empty($owner['phone_verified_at'])): ?><p>Mobile not verified</p><?php endif; ?>
  <?php endif; ?>
  <?php if (!empty($row['reserved_until'])): ?><p>Reserved until <?= e(fmt_when((string) $row['reserved_until'])) ?></p><?php endif; ?>
  <p><?= e($row['customer_name']) ?> · <?= e((string) $row['customer_phone']) ?> · <?= e((string) $row['customer_email']) ?></p>
  <p><?= e($row['address_line']) ?>, <?= e($row['city']) ?> <?= e($row['state_name']) ?> <?= e($row['postal_code']) ?></p>
  <?php if ($couponRow): ?><p>Coupon <?= e($couponRow['code']) ?> · <?= inr((int) $couponRow['amount_inr']) ?> off</p><?php endif; ?>
  <div class="table-wrap"><table>
    <?php foreach ($itemRows as $item): ?>
      <tr>
        <td><img class="thumb" src="<?= e(product_image((string) ($item['image_url'] ?? ''))) ?>" alt=""></td>
        <td><?= e($item['product_name']) ?></td>
        <td><?= (int) $item['quantity'] ?></td>
        <td><?= inr((int) $item['line_total_inr']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <p>Subtotal <?= inr((int) $row['subtotal_inr']) ?> · Discount <?= inr((int) $row['discount_inr']) ?> · Shipping <?= inr((int) $row['shipping_inr']) ?> · Total <?= inr((int) $row['total_inr']) ?></p>
</article>
<h2>Timeline</h2>
<?php foreach ($events as $event): ?>
  <p><strong><?= e(order_label((string) $event['status'])) ?></strong> · <?= e((string) $event['created_at']) ?><br><?= e((string) $event['note']) ?></p>
<?php endforeach; ?>
<form method="post" class="narrow">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
  <input type="hidden" name="action" value="note">
  <label>Internal note <textarea name="internal_note"><?= e((string) ($row['internal_note'] ?? '')) ?></textarea></label>
  <button class="btn" type="submit">Save note</button>
</form>
<form method="post" class="narrow">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
  <label>Next status <select name="status">
    <?php foreach (['payment_pending', 'payment_submitted', 'payment_confirmed', 'confirmed', 'preparing', 'packed', 'out_for_delivery', 'dispatched', 'delivered', 'delivery_failed', 'cancelled'] as $status): ?>
      <option value="<?= e($status) ?>"><?= e(order_label($status)) ?></option>
    <?php endforeach; ?>
  </select></label>
  <button class="btn" type="submit" data-confirm="Update this order?">Update</button>
  <button class="btn" type="button" data-print="1">Print packing slip</button>
</form>
<?php if ($wa !== ''): ?><p><a class="btn" href="<?= e($wa) ?>" target="_blank" rel="noopener">Message customer on WhatsApp</a></p><?php endif; ?>
<?php if (!empty($row['payment_ref'])): ?><p class="flash">Payment reference <?= e((string) $row['payment_ref']) ?> <?= e((string) ($row['payment_note'] ?? '')) ?></p><?php endif; ?>
<form method="post" class="inline">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
  <input type="hidden" name="action" value="paid">
  <label>Amount received <input name="amount" type="number" min="1" value="<?= (int) max(1, (int) $row['total_inr'] - (int) ($row['paid_amount_inr'] ?? 0)) ?>"></label>
  <label>Date <input name="paid_on" type="date" value="<?= e(date('Y-m-d')) ?>"></label>
  <label>If the amount differs <select name="pay_gap"><option value="">Same as the total</option><option value="partial">Partial payment</option><option value="overpaid">Overpaid</option></select></label>
  <label>Note <input name="pay_note" maxlength="200"></label>
  <label><input type="checkbox" name="confirm_duplicate" value="1"> The reference is already on another order and I still want to continue</label>
  <?php if ((string) $row['status'] === 'cancelled'): ?><label><input type="checkbox" name="reopen" value="1"> This order expired. Re-open it?</label><?php endif; ?>
  <button class="btn" type="submit">Mark payment received</button>
</form>
<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
  <input type="hidden" name="action" value="missing">
  <button class="btn" type="submit">Payment not found</button>
</form>
<form method="post" class="inline">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
  <input type="hidden" name="action" value="refund">
  <label>Refund amount <input name="amount" type="number" min="1"></label>
  <label>Method <input name="method"></label>
  <label>Reference <input name="utr" maxlength="80"></label>
  <label>Date <input name="paid_on" type="date" value="<?= e(date('Y-m-d')) ?>"></label>
  <button class="btn" type="submit">Refund recorded</button>
</form>
<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
  <input type="hidden" name="action" value="confirm">
  <button class="btn" type="submit">Confirmed by phone</button>
</form>
<?php $drivers = db()->query('SELECT id, name, vehicle_no FROM drivers WHERE active = 1 ORDER BY name')->fetchAll(); ?>
<form method="post" class="inline">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
  <input type="hidden" name="action" value="assign">
  <label>Driver <select name="driver_id"><option value="0">Clear assignment</option><?php foreach ($drivers as $driver): ?><option value="<?= (int) $driver['id'] ?>" <?= (int) ($row['driver_id'] ?? 0) === (int) $driver['id'] ? 'selected' : '' ?>><?= e($driver['name']) ?> · <?= e($driver['vehicle_no']) ?></option><?php endforeach; ?></select></label>
  <label>Date <input type="date" name="delivery_date" value="<?= e((string) ($row['delivery_date'] ?? date('Y-m-d'))) ?>"></label>
  <label>Time <select name="delivery_slot"><option>Morning</option><option>Afternoon</option><option>Evening</option></select></label>
  <button class="btn" type="submit">Assign driver</button>
</form>
<form method="post" class="inline">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
  <input type="hidden" name="action" value="cash">
  <label>Cash collected <input name="amount" type="number" min="1" value="<?= (int) $row['total_inr'] ?>"></label>
  <label>Reason if it differs <input name="cash_reason" maxlength="160" placeholder="Short change or discount"></label>
  <button class="btn" type="submit">Delivered — cash collected</button>
</form>
<form method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
  <input type="hidden" name="action" value="proof">
  <label>Delivery photo <input type="file" name="proof" accept="image/jpeg,image/png,image/webp" required></label>
  <button class="btn" type="submit">Save delivery photo</button>
</form>
<?php admin_close(); ?>
