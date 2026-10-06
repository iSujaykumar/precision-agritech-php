<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();
$user = require_user();
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        if (($_POST['action'] ?? '') === 'phone') {
            $phone = normalize_phone((string) ($_POST['phone'] ?? ''));
            if ($phone === (string) ($user['phone'] ?? '')) {
                header('Location: /account');
                exit;
            }
            $taken = db()->prepare('SELECT id FROM users WHERE phone = ? AND id <> ?');
            $taken->execute([$phone, (int) $user['id']]);
            if ($taken->fetch()) {
                throw new RuntimeException('That mobile number is already on an account.');
            }
            db()->prepare('UPDATE users SET phone = ?, phone_verified_at = NULL WHERE id = ?')->execute([$phone, (int) $user['id']]);
            unset($_SESSION['otp_id'], $_SESSION['otp_phone'], $_SESSION['otp_purpose'], $_SESSION['otp_decoy']);
        } else {
            $line = trim((string) ($_POST['line'] ?? ''));
            $city = trim((string) ($_POST['city'] ?? ''));
            $state = trim((string) ($_POST['state'] ?? ''));
            $postal = trim((string) ($_POST['postal'] ?? ''));
            if (mb_strlen($line) < 8 || mb_strlen($city) < 2 || mb_strlen($state) < 2 || !preg_match('/^[0-9]{6}$/', $postal)) {
                throw new RuntimeException('Check the address and 6-digit PIN code.');
            }
            db()->prepare('INSERT INTO addresses (user_id, line, city, state_name, postal_code) VALUES (?, ?, ?, ?, ?)')
                ->execute([(int) $user['id'], $line, $city, $state, $postal]);
            flash('success', 'Address saved.');
        }
        header('Location: /account');
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'That change could not be saved.');
    }
}
$fresh = db()->prepare('SELECT phone FROM users WHERE id = ?');
$fresh->execute([(int) $user['id']]);
$phone = (string) $fresh->fetchColumn();
$orders = db()->prepare('SELECT order_number, lookup_token, status, payment_status, total_inr, created_at FROM orders WHERE user_id = ? ORDER BY id DESC');
$orders->execute([(int) $user['id']]);
$addresses = db()->prepare('SELECT * FROM addresses WHERE user_id = ? ORDER BY id DESC');
$addresses->execute([(int) $user['id']]);
render_header('Account | Precision Agritech');
?>
<section class="section"><div class="wrap">
  <h1><?= e($user['name']) ?></h1>
  <p><?= e($user['email']) ?> · <?= $phone !== '' ? e(mask_phone($phone)) : 'No mobile yet' ?> · <?php if (twilio_configured() && empty($user['phone_verified_at'])): ?><a href="/verify-mobile">Verify mobile</a> · <?php elseif (!empty($user['phone_verified_at'])): ?>Mobile verified · <?php endif; ?><a href="/change-password">Change password</a><?php if ($user['role'] === 'admin'): ?> · <a href="/admin">Nursery desk</a><?php endif; ?></p>
  <?php if ($error): ?><p class="flash" role="alert"><?= e($error) ?></p><?php endif; ?>
  <h2>Mobile number</h2>
  <form method="post" class="narrow">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="phone">
    <label>Mobile <input name="phone" type="tel" inputmode="tel" required value="<?= e($phone) ?>"></label>
    <button class="btn">Save mobile</button>
  </form>
  <p class="muted">The same account signs in with this email or this mobile.</p>
  <h2>Orders</h2>
  <?php foreach ($orders as $order): ?>
    <p><a href="/order/<?= e($order['lookup_token']) ?>"><?= e($order['order_number']) ?></a> · <?= e(status_label((string) $order['status'])) ?> · <?= inr((int) $order['total_inr']) ?></p>
  <?php endforeach; ?>
  <h2>Addresses</h2>
  <?php foreach ($addresses as $address): ?>
    <p><?= e($address['line']) ?>, <?= e($address['city']) ?> <?= e($address['postal_code']) ?></p>
  <?php endforeach; ?>
  <form method="post" class="narrow">
    <?= csrf_field() ?>
    <label>Address <textarea name="line" required></textarea></label>
    <label>City <input name="city" required></label>
    <label>State <input name="state" required></label>
    <label>PIN <input name="postal" required pattern="[0-9]{6}" inputmode="numeric"></label>
    <button class="btn">Save address</button>
  </form>
</div></section>
<?php render_footer(); ?>
