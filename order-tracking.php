<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();
$error = null;
$found = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        if (lookup_is_limited()) {
            http_response_code(429);
            throw new RuntimeException('Lookups are paused for a few minutes.');
        }
        note_lookup();
        $number = strtoupper(trim((string) ($_POST['order_number'] ?? '')));
        $phone = normalize_phone((string) ($_POST['mobile'] ?? ''));
        if (!preg_match('/^PA[A-Z0-9]{6,12}$/', $number)) {
            throw new RuntimeException('That order could not be found.');
        }
        $stmt = db()->prepare('SELECT order_number, status, payment_status FROM orders WHERE order_number = ? AND customer_phone = ? LIMIT 1');
        $stmt->execute([$number, $phone]);
        $found = $stmt->fetch() ?: null;
        if (!$found) {
            throw new RuntimeException('That order could not be found.');
        }
    } catch (Throwable $err) {
        $error = safe_error($err, 'That order could not be found.');
    }
}
render_header('Track an order | Precision Agritech');
?>
<section class="section"><div class="wrap narrow">
  <h1>Track an order</h1>
  <p>Use the order number and the mobile number from the order. The delivery address stays on the private link.</p>
  <?php if ($error): ?><p class="flash" role="alert"><?= e($error) ?></p><?php endif; ?>
  <?php if ($found): ?>
    <p><strong><?= e($found['order_number']) ?></strong> · <?= e($found['status']) ?> · Payment <?= e($found['payment_status']) ?></p>
  <?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Order number <input name="order_number" required autocomplete="off" value="<?= e((string) ($_POST['order_number'] ?? '')) ?>"></label>
    <label>Mobile number <input name="mobile" type="tel" inputmode="numeric" required pattern="[6-9][0-9]{9}" maxlength="10" autocomplete="tel"></label>
    <button class="btn" type="submit">Look up</button>
  </form>
</div></section>
<?php render_footer(); ?>
