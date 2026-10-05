<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();

$error = null;
$mode = (string) ($_POST['mode'] ?? 'email');
if (!in_array($mode, ['email', 'mobile', 'otp'], true)) {
    $mode = 'email';
}
$step = (string) ($_POST['step'] ?? 'ask');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        if ($mode === 'otp' && $step === 'code') {
            $phone = (string) ($_SESSION['otp_phone'] ?? '');
            if ($phone === '' || too_many_attempts('otp:' . $phone)) {
                throw new RuntimeException('That sign-in is not right.');
            }
            $phone = check_mobile_code(trim((string) ($_POST['code'] ?? '')), 'login');
            $stmt = db()->prepare('SELECT * FROM users WHERE phone = ? AND phone_verified_at IS NOT NULL AND status = "active"');
            $stmt->execute([$phone]);
            $user = $stmt->fetch();
            if (!$user) {
                note_login_failure('otp:' . $phone);
                throw new RuntimeException('That sign-in is not right.');
            }
            sign_in_user($user);
            header('Location: ' . ($user['role'] === 'admin' ? '/admin' : '/account'));
            exit;
        }
        if ($mode === 'otp') {
            if (!twilio_configured()) {
                throw new RuntimeException('SMS codes are not set up on this server yet. Use your password.');
            }
            $phone = normalize_phone((string) ($_POST['mobile'] ?? ''));
            if (too_many_attempts('otp:' . $phone)) {
                throw new RuntimeException('Too many attempts. Wait and try again.');
            }
            $stmt = db()->prepare('SELECT * FROM users WHERE phone = ? AND phone_verified_at IS NOT NULL AND status = "active"');
            $stmt->execute([$phone]);
            $user = $stmt->fetch();
            if ($user) {
                start_mobile_code($phone, 'login', (int) $user['id']);
            } else {
                $_SESSION['otp_phone'] = $phone;
                $_SESSION['otp_purpose'] = 'login';
                $_SESSION['otp_id'] = 0;
                $_SESSION['otp_decoy'] = 1;
            }
            $step = 'code';
        } else {
            $identifier = $mode === 'email'
                ? strtolower(trim((string) ($_POST['email'] ?? '')))
                : normalize_phone((string) ($_POST['mobile'] ?? ''));
            if (too_many_attempts($identifier)) {
                throw new RuntimeException('Too many attempts. Wait and try again.');
            }
            $password = (string) ($_POST['password'] ?? '');
            if ($mode === 'mobile') {
                $stmt = db()->prepare('SELECT * FROM users WHERE phone = ? AND status = "active"');
                $stmt->execute([$identifier]);
            } else {
                $stmt = db()->prepare('SELECT * FROM users WHERE email = ? AND status = "active"');
                $stmt->execute([$identifier]);
            }
            $user = $stmt->fetch();
            if (!$user || !password_verify($password, (string) $user['password_hash'])) {
                note_login_failure($identifier);
                throw new RuntimeException('That sign-in is not right.');
            }
            sign_in_user($user);
            header('Location: ' . ($user['role'] === 'admin' ? '/admin' : '/account'));
            exit;
        }
    } catch (Throwable $err) {
        $error = safe_error($err, 'Sign-in is unavailable right now.');
    }
}
render_header('Sign in | Precision Agritech');
?>
<section class="section"><div class="wrap narrow">
  <h1>Sign in</h1>
  <?php if ($error): ?><p class="flash" role="alert"><?= e($error) ?></p><?php endif; ?>
  <?php if ($mode === 'otp' && $step === 'code' && !empty($_SESSION['otp_phone'])): ?>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="mode" value="otp">
    <input type="hidden" name="step" value="code">
    <p>Enter the SMS code sent to <?= e(mask_phone((string) $_SESSION['otp_phone'])) ?>.</p>
    <label>Code <input name="code" inputmode="numeric" autocomplete="one-time-code" required maxlength="10"></label>
    <button class="btn" type="submit">Verify</button>
  </form>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="mode" value="otp">
    <input type="hidden" name="mobile" value="<?= e((string) $_SESSION['otp_phone']) ?>">
    <button class="linkish" type="submit">Send another code</button>
  </form>
  <?php else: ?>
  <form method="post" id="signin">
    <?= csrf_field() ?>
    <div class="filters">
      <label><input type="radio" name="mode" value="email" <?= $mode === 'email' ? 'checked' : '' ?>> Email</label>
      <label><input type="radio" name="mode" value="mobile" <?= $mode === 'mobile' ? 'checked' : '' ?>> Mobile and password</label>
      <label><input type="radio" name="mode" value="otp" <?= $mode === 'otp' ? 'checked' : '' ?>> Mobile code</label>
    </div>
    <label class="field-email">Email
      <input name="email" type="email" autocomplete="username" inputmode="email" value="<?= e((string) ($_POST['email'] ?? '')) ?>">
    </label>
    <label class="field-mobile">Mobile
      <input name="mobile" type="tel" autocomplete="tel" inputmode="tel" placeholder="10-digit mobile" value="<?= e((string) ($_POST['mobile'] ?? '')) ?>">
    </label>
    <label class="field-password">Password
      <input name="password" type="password" autocomplete="current-password">
    </label>
    <button class="btn" type="submit">Continue</button>
  </form>
  <p><a href="/register">Create an account</a> · <a href="/forgot-password">Forgot password</a></p>
  <?php endif; ?>
</div></section>
<script src="/assets/js/signin.js"></script>
<?php render_footer(); ?>
