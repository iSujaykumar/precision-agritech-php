<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();
$user = require_user();
$error = null;
$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $phone = (string) ($user['phone'] ?? '');
        if ($phone === '') {
            throw new RuntimeException('Add a mobile number on your account first.');
        }
        if (($_POST['step'] ?? '') === 'code') {
            $verified = check_mobile_code(trim((string) ($_POST['code'] ?? '')), 'verify');
            if ($verified !== $phone) {
                throw new RuntimeException('That code is not right.');
            }
            db()->prepare('UPDATE users SET phone_verified_at = NOW() WHERE id = ? AND phone = ?')->execute([(int) $user['id'], $phone]);
            header('Location: /account');
            exit;
        }
        start_mobile_code($phone, 'verify', (int) $user['id']);
        $sent = true;
    } catch (Throwable $err) {
        $error = safe_error($err, 'The mobile could not be verified.');
        $sent = !empty($_SESSION['otp_phone']);
    }
}
render_header('Verify mobile | Precision Agritech');
?>
<section class="section"><div class="wrap narrow">
  <h1>Verify mobile</h1>
  <?php if ($error): ?><p class="flash" role="alert"><?= e($error) ?></p><?php endif; ?>
  <?php if (!twilio_configured()): ?>
    <p>SMS codes are not set up on this server yet. Password sign-in still works. A code is sent only after the SMS service is configured.</p>
  <?php elseif ($sent): ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="step" value="code">
      <label>Code <input name="code" inputmode="numeric" autocomplete="one-time-code" required></label>
      <button class="btn">Confirm mobile</button>
    </form>
  <?php else: ?>
    <p>We will text a code to <?= e(mask_phone((string) ($user['phone'] ?? ''))) ?>. The mobile stays unverified until the code matches.</p>
    <form method="post"><?= csrf_field() ?><button class="btn">Send code</button></form>
  <?php endif; ?>
</div></section>
<?php render_footer(); ?>
