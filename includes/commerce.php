<?php
declare(strict_types=1);

function clean_text(string $value, int $max): string
{
    $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?? '');
    if (strlen($value) > $max) {
        throw new RuntimeException('That text is too long.');
    }
    return $value;
}

function fmt_when(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    $time = strtotime($value);
    return $time === false ? $value : date('d M Y, g:i A', $time);
}

function rate_limited(string $bucket, int $max, int $minutes): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND attempted_at > (NOW() - INTERVAL ' . (int) $minutes . ' MINUTE)');
    $stmt->execute([substr($bucket, 0, 160)]);
    return (int) $stmt->fetchColumn() >= $max;
}

function note_rate(string $bucket): void
{
    note_login_failure(substr($bucket, 0, 160));
}

function pin_is_served(string $pin): bool
{
    $list = trim(setting('delivery_pins', ''));
    if ($list === '') {
        return true;
    }
    $pin = preg_replace('/\D+/', '', $pin) ?? '';
    if (!preg_match('/^[0-9]{6}$/', $pin)) {
        return false;
    }
    foreach (preg_split('/\R/', $list) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (preg_match('/^([0-9]{6})\s*-\s*([0-9]{6})$/', $line, $m)) {
            if ($pin >= $m[1] && $pin <= $m[2]) {
                return true;
            }
            continue;
        }
        if ($line === $pin) {
            return true;
        }
    }
    return false;
}

function zone_shipping(int $after, string $pin): int
{
    if ($after <= 0) {
        return 0;
    }
    $freeOver = (int) setting('free_shipping_over_inr', '4000');
    if ($after >= $freeOver) {
        return 0;
    }
    $pin = preg_replace('/\D+/', '', $pin) ?? '';
    foreach (preg_split('/\R/', setting('zone_shipping', '')) ?: [] as $line) {
        if (!preg_match('/^([0-9]{6})(?:\s*-\s*([0-9]{6}))?\s*=\s*([0-9]+)$/', trim($line), $m)) {
            continue;
        }
        $hit = $m[2] === '' ? $pin === $m[1] : ($pin >= $m[1] && $pin <= $m[2]);
        if ($hit) {
            return (int) $m[3];
        }
    }
    return (int) setting('shipping_flat_inr', '180');
}

function cod_is_blocked(string $phone): bool
{
    $stmt = db()->prepare('SELECT id FROM cod_blocks WHERE phone = ? LIMIT 1');
    $stmt->execute([$phone]);
    return (bool) $stmt->fetch();
}

function reservation_until(string $method): string
{
    $hours = $method === 'cod'
        ? (int) setting('reservation_hours_cod', '72')
        : (int) setting('reservation_hours_bank', '48');
    if ($hours < 1) {
        $hours = $method === 'cod' ? 72 : 48;
    }
    return date('Y-m-d H:i:s', time() + ($hours * 3600));
}

function upi_intent(string $orderNumber, int $amount): string
{
    $pa = setting('upi_id', 'precision9958@fbl');
    $pn = setting('upi_payee_name', 'Precision Agritech Private Limited');
    return 'upi://pay?pa=' . rawurlencode($pa)
        . '&pn=' . rawurlencode($pn)
        . '&am=' . rawurlencode((string) $amount)
        . '&cu=INR&tn=' . rawurlencode($orderNumber);
}

function release_order_hold(PDO $pdo, int $orderId): void
{
    $pdo->prepare('UPDATE products p JOIN order_items oi ON oi.product_id = p.id SET p.reserved_qty = GREATEST(0, p.reserved_qty - oi.quantity) WHERE oi.order_id = ?')->execute([$orderId]);
}

function release_coupon_for_order(PDO $pdo, int $orderId): void
{
    $pdo->prepare('DELETE FROM coupon_redemptions WHERE order_id = ?')->execute([$orderId]);
}

function order_attempt_limited(string $phone): bool
{
    $ip = db()->prepare('SELECT COUNT(*) FROM order_attempts WHERE ip = ? AND created_at > (NOW() - INTERVAL 1 HOUR)');
    $ip->execute([client_ip()]);
    $who = db()->prepare('SELECT COUNT(*) FROM order_attempts WHERE phone = ? AND created_at > (NOW() - INTERVAL 1 DAY)');
    $who->execute([$phone]);
    return (int) $ip->fetchColumn() >= 3 || (int) $who->fetchColumn() >= 3;
}

function note_order_attempt(string $phone): void
{
    db()->prepare('INSERT INTO order_attempts (ip, phone) VALUES (?, ?)')->execute([client_ip(), $phone]);
}

function form_opened_ok(): void
{
    if (trim((string) ($_POST['company_website'] ?? '')) !== '') {
        throw new RuntimeException('The form could not be sent.');
    }
    $opened = (int) ($_POST['opened_at'] ?? 0);
    if ($opened > 0 && time() - $opened < 3) {
        throw new RuntimeException('Please wait a moment, then try again.');
    }
}

function opened_fields(): string
{
    return '<input type="hidden" name="opened_at" value="' . time() . '"><input class="hp" name="company_website" tabindex="-1" autocomplete="off" aria-hidden="true" value="">';
}

function expire_reservations(): int
{
    $pdo = db();
    $rows = $pdo->query("SELECT id FROM orders WHERE status IN ('placed','payment_pending','payment_submitted') AND inventory_state = 'reserved' AND reserved_until IS NOT NULL AND reserved_until < NOW()")->fetchAll();
    $count = 0;
    foreach ($rows as $row) {
        $pdo->beginTransaction();
        $current = null;
        try {
            $order = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            $order->execute([(int) $row['id']]);
            $current = $order->fetch();
            if ($current && in_array($current['status'], ['placed', 'payment_pending', 'payment_submitted'], true) && $current['inventory_state'] === 'reserved' && strtotime((string) $current['reserved_until']) < time()) {
                release_order_hold($pdo, (int) $current['id']);
                release_coupon_for_order($pdo, (int) $current['id']);
                $pdo->prepare("UPDATE orders SET status = 'cancelled', inventory_state = 'released', cancel_reason = 'Reservation expired' WHERE id = ?")->execute([(int) $current['id']]);
                $pdo->prepare("INSERT INTO order_events (order_id, status, note) VALUES (?, 'cancelled', 'Reservation expired')")->execute([(int) $current['id']]);
                $count++;
            } else {
                $current = null;
            }
            $pdo->commit();
        } catch (Throwable $err) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Reservation expiry failed: ' . $err->getMessage());
            $current = null;
        }
        if ($current) {
            mail_order_customer($current, 'The reservation for this order has ended, so it is cancelled. The trays are free for someone else.');
            mail_staff('Reservation ended ' . $current['order_number'], 'Order ' . $current['order_number'] . ' was cancelled because payment was not confirmed in time.');
        }
    }
    return $count;
}

function housekeeping_tick(): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;
    try {
        $pdo = db();
        $lock = $pdo->query("SELECT GET_LOCK('pa_housekeeping', 0)")->fetchColumn();
        if ((string) $lock !== '1') {
            return;
        }
        try {
            $last = (int) setting('cleanup_last_run', '0');
            if ($last > 0 && (time() - $last) < 600) {
                return;
            }
            expire_reservations();
            $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 2 DAY)');
            $pdo->exec('DELETE FROM lookup_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
            $pdo->exec('DELETE FROM order_attempts WHERE created_at < (NOW() - INTERVAL 2 DAY)');
            $pdo->exec("DELETE FROM mobile_verifications WHERE created_at < (NOW() - INTERVAL 2 DAY) AND status <> 'pending'");
            $pdo->exec('DELETE FROM email_codes WHERE created_at < (NOW() - INTERVAL 2 DAY)');
            save_setting('cleanup_last_run', (string) time());
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('pa_housekeeping')");
        }
    } catch (Throwable $err) {
        error_log('Housekeeping skipped: ' . $err->getMessage());
    }
}
