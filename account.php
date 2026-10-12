<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();
$user = require_user();
if (($user['role'] ?? '') === 'admin') {
    header('Location: /admin');
    exit;
}
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        if (($_POST['action'] ?? '') === 'mobile') {
            $mobile = normalize_phone((string) ($_POST['mobile'] ?? ''));
            $taken = db()->prepare('SELECT id FROM users WHERE phone = ? AND id <> ?');
            $taken->execute([$mobile, (int) $user['id']]);
            if ($taken->fetch()) {
                throw new RuntimeException('That mobile is already on another account.');
            }
            db()->prepare('UPDATE users SET phone = ? WHERE id = ?')->execute([$mobile, (int) $user['id']]);
            header('Location: /account');
            exit;
        }
        if (($_POST['action'] ?? '') === 'email-send') {
            email_code_issue((string) ($_POST['email'] ?? ''), 'account');
            $_SESSION['flash'] = 'If this email can be added, a code was sent.';
            header('Location: /account');
            exit;
        }
        if (($_POST['action'] ?? '') === 'email-confirm') {
            $email = strtolower((string) ($_SESSION['account_email'] ?? ''));
            email_code_check($email, (string) ($_POST['code'] ?? ''), 'account');
            $taken = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
            $taken->execute([$email, (int) $user['id']]);
            if ($taken->fetch()) {
                throw new RuntimeException('That email is already on another account.');
            }
            db()->prepare('UPDATE users SET email = ?, email_verified_at = NOW() WHERE id = ?')->execute([$email, (int) $user['id']]);
            unset($_SESSION['account_email'], $_SESSION['account_email_debug'], $_SESSION['account_email_blocked']);
            $_SESSION['flash'] = 'Email added.';
            header('Location: /account');
            exit;
        }
        if (($_POST['action'] ?? '') === 'delete') {
            db()->prepare('UPDATE users SET deletion_requested = 1 WHERE id = ?')->execute([(int) $user['id']]);
            $_SESSION['flash'] = 'Your request is with the nursery. They will remove the account.';
            header('Location: /account');
            exit;
        }
        $line = clean_text((string) ($_POST['line'] ?? ''), 400);
        $city = trim((string) ($_POST['city'] ?? ''));
        $state = trim((string) ($_POST['state'] ?? ''));
        $postal = trim((string) ($_POST['postal'] ?? ''));
        if (strlen($line) < 8 || strlen($city) < 2 || strlen($state) < 2 || !preg_match('/^[0-9]{6}$/', $postal)) {
            throw new RuntimeException('Check the address, state and 6-digit PIN code.');
        }
        db()->prepare('INSERT INTO addresses (user_id, line, city, state_name, postal_code) VALUES (?, ?, ?, ?, ?)')
            ->execute([(int) $user['id'], $line, $city, $state, $postal]);
        header('Location: /account');
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'That change could not be saved.');
    }
}
$fresh = db()->prepare('SELECT phone, email, email_verified_at FROM users WHERE id = ?');
$fresh->execute([(int) $user['id']]);
$profile = $fresh->fetch() ?: ['phone' => '', 'email' => '', 'email_verified_at' => null];
$phone = (string) ($profile['phone'] ?? '');
$emailOnFile = (string) ($profile['email'] ?? '');
$orders = db()->prepare('SELECT order_number, lookup_token, status, payment_status, total_inr, created_at FROM orders WHERE user_id = ? OR (customer_email <> "" AND customer_email = ? AND ? = 1) ORDER BY id DESC');
$orders->execute([(int) $user['id'], (string) ($user['email'] ?? ''), (!empty($user['email_verified_at']) || !empty($user['google_id'])) ? 1 : 0]);
$orderRows = $orders->fetchAll();
$addresses = db()->prepare('SELECT * FROM addresses WHERE user_id = ? ORDER BY id DESC');
$addresses->execute([(int) $user['id']]);
$addressRows = $addresses->fetchAll();
render_header('Account | Precision Agritech');
?>
<section class="section"><div class="wrap narrow">
  <h1><?= e($user['name']) ?></h1>
  <?php if (!empty($_SESSION['flash'])): ?><p class="flash ok" role="status"><?= e((string) $_SESSION['flash']) ?></p><?php unset($_SESSION['flash']); endif; ?>
  <?php if (isset($_GET['need']) && $phone === ''): ?><p class="flash">Add your mobile number for delivery. This number is not checked by SMS yet.</p><?php endif; ?>
  <p><?= $emailOnFile !== '' ? e($emailOnFile) : 'No email on this account yet' ?></p>
  <p>Sign-in: <?php
    $ways = [];
    if (!empty($user['google_id'])) { $ways[] = 'Google'; }
    if (!empty($user['email_verified_at'])) { $ways[] = 'Email'; }
    if (!empty($user['phone_verified_at'])) { $ways[] = 'Mobile'; }
    echo e($ways ? implode(', ', $ways) : 'Email or guest orders');
  ?></p>
  <p>Mobile <?= $phone !== '' ? e(mask_phone($phone)) : 'not on file' ?><?php if ($phone !== '' && empty($user['phone_verified_at'])): ?> · not checked by SMS<?php endif; ?></p>
  <?php if ($phone === ''): ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="mobile"><label>Mobile for delivery <input name="mobile" required inputmode="tel"></label><button class="btn" type="submit">Save mobile</button></form>
    <p class="muted">This number is a contact number. It is not checked by SMS.</p>
  <?php endif; ?>
  <?php if ($emailOnFile === ''): ?>
    <?php if (!empty($_SESSION['account_email_debug']) && !empty($GLOBALS['config']['show_otp_on_screen'])): ?>
      <p class="flash">Code <?= e((string) $_SESSION['account_email_debug']) ?></p>
    <?php endif; ?>
    <?php if (!empty($_SESSION['account_email'])): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="email-confirm"><label>Code for <?= e((string) $_SESSION['account_email']) ?> <input name="code" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" required></label><button class="btn" type="submit">Add email</button></form>
    <?php endif; ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="email-send"><label>Email <input name="email" type="email" required value="<?= e((string) ($_SESSION['account_email'] ?? '')) ?>"></label><button class="btn" type="submit">Send code</button></form>
  <?php endif; ?>
  <form method="post" action="/logout" class="inline">
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="/">
    <button class="btn" type="submit">Sign out</button>
  </form>
  <?php if ($error): ?><p class="flash" role="alert"><?= e($error) ?></p><?php endif; ?>
  <h2>Orders</h2>
  <?php if (!$orderRows): ?><p class="muted">No orders yet.</p><?php endif; ?>
  <?php foreach ($orderRows as $order): ?>
    <p><a href="/order/<?= e($order['lookup_token']) ?>"><?= e($order['order_number']) ?></a> · <?= e($order['status']) ?> · <?= inr((int) $order['total_inr']) ?></p>
  <?php endforeach; ?>
  <h2>Addresses</h2>
  <?php if (!$addressRows): ?><p class="muted">No saved addresses yet.</p><?php endif; ?>
  <?php foreach ($addressRows as $address): ?>
    <p><?= e($address['line']) ?>, <?= e($address['city']) ?>, <?= e($address['state_name']) ?> <?= e($address['postal_code']) ?></p>
  <?php endforeach; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Address <textarea name="line" required></textarea></label>
    <label>City <input name="city" required></label>
    <label>State <input name="state" required value="Maharashtra"></label>
    <label>PIN <input name="postal" required pattern="[0-9]{6}" inputmode="numeric" maxlength="6"></label>
    <button class="btn" type="submit">Save address</button>
  </form>
  <h2>Delete my account and data</h2>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="text-link" type="submit">Ask the nursery to delete this account</button></form>
</div></section>
<?php render_footer(); ?>
