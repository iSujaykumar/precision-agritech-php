<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();

if (isset($_GET['next'])) {
    $_SESSION['login_next'] = safe_next((string) $_GET['next']);
}
$signedIn = current_user();
if ($signedIn && ($signedIn['role'] ?? '') !== 'admin' && (string) ($_GET['need'] ?? '') !== 'mobile') {
    header('Location: ' . safe_next((string) ($_SESSION['login_next'] ?? ''), '/account'));
    exit;
}
if (isset($_GET['change'])) {
    unset($_SESSION['email_login'], $_SESSION['email_verified'], $_SESSION['email_sent_at'], $_SESSION['email_debug'], $_SESSION['otp_phone'], $_SESSION['otp_sent_at'], $_SESSION['otp_debug']);
    header('Location: /login');
    exit;
}
$method = (string) ($_POST['method'] ?? $_GET['method'] ?? 'email');
if ($method !== 'mobile' || !sms_ready()) {
    $method = 'email';
}
$error = null;
$notice = null;
if (!empty($_SESSION['flash']) && is_string($_SESSION['flash'])) {
    $error = $_SESSION['flash'];
    unset($_SESSION['flash']);
}
$step = (string) ($_SESSION['email_login'] ?? '') !== '' ? 'code' : 'ask';
if (!empty($_SESSION['email_verified'])) {
    $step = 'profile';
}
if ($method === 'mobile' && !empty($_SESSION['otp_phone'])) {
    $step = empty($_SESSION['otp_verified_phone']) ? 'code' : 'profile';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $action = (string) ($_POST['step'] ?? 'ask');
        if ($action === 'profile') {
            $email = (string) ($_SESSION['email_verified'] ?? '');
            $phoneVerified = (string) ($_SESSION['otp_verified_phone'] ?? '');
            $name = clean_text((string) ($_POST['name'] ?? ''), 120);
            $mobile = normalize_phone((string) ($_POST['mobile'] ?? ''));
            if (strlen($name) < 2) {
                throw new RuntimeException('Enter your name.');
            }
            if ($phoneVerified !== '') {
                $email = strtolower(clean_text((string) ($_POST['email'] ?? ''), 160));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Enter a valid email.');
                }
                $hash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
                db()->prepare('INSERT INTO users (name, email, phone, password_hash, role, status, phone_verified_at) VALUES (?, ?, ?, ?, ?, ?, NOW())')
                    ->execute([$name, $email, $mobile, $hash, 'customer', 'active']);
            } else {
                if ($email === '') {
                    throw new RuntimeException('Verify the email first.');
                }
                $hash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
                db()->prepare('INSERT INTO users (name, email, phone, password_hash, role, status, email_verified_at) VALUES (?, ?, ?, ?, ?, ?, NOW())')
                    ->execute([$name, $email, $mobile, $hash, 'customer', 'active']);
            }
            $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([(int) db()->lastInsertId()]);
            finish_customer($stmt->fetch());
            header('Location: ' . login_destination());
            exit;
        }
        if ($action === 'code' && $method === 'email') {
            $email = (string) ($_SESSION['email_login'] ?? '');
            if ($email === '' || rate_limited('mailfail:' . $email, 8, 15)) {
                throw new RuntimeException('That sign-in is not right.');
            }
            try {
                email_code_check($email, (string) ($_POST['code'] ?? ''));
            } catch (RuntimeException $err) {
                note_rate('mailfail:' . $email);
                throw $err;
            }
            $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if ($user && ($user['role'] ?? '') === 'admin') {
                audit_log(0, 'customer_signin_refused', 'user', (int) $user['id'], $email, 'staff email code');
                throw new RuntimeException('If this email can sign in, a code was sent.');
            }
            if ($user) {
                db()->prepare('UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?')->execute([(int) $user['id']]);
                $user['email_verified_at'] = $user['email_verified_at'] ?: date('Y-m-d H:i:s');
                finish_customer($user);
                header('Location: ' . login_destination());
                exit;
            }
            $_SESSION['email_verified'] = $email;
            unset($_SESSION['email_login']);
            $step = 'profile';
        } elseif ($action === 'resend' && $method === 'email') {
            email_code_issue((string) ($_SESSION['email_login'] ?? ''));
            $notice = 'If this email can sign in, a code was sent.';
            $step = 'code';
        } elseif ($action === 'ask' && $method === 'email') {
            email_code_issue((string) ($_POST['email'] ?? ''));
            $notice = 'If this email can sign in, a code was sent.';
            $step = 'code';
        } elseif ($method === 'mobile' && sms_ready()) {
            if ($action === 'code') {
                $phone = check_mobile_code(trim((string) ($_POST['code'] ?? '')), 'login');
                $stmt = db()->prepare('SELECT * FROM users WHERE phone = ? AND status = "active" LIMIT 1');
                $stmt->execute([$phone]);
                $user = $stmt->fetch();
                if ($user && ($user['role'] ?? '') === 'admin') {
                    throw new RuntimeException('That sign-in is not right.');
                }
                if ($user) {
                    finish_customer($user);
                    header('Location: ' . login_destination());
                    exit;
                }
                $_SESSION['otp_verified_phone'] = $phone;
                $step = 'profile';
            } elseif ($action === 'resend') {
                begin_customer_otp((string) ($_SESSION['otp_phone'] ?? ''));
                $notice = 'A new code is on the way.';
                $step = 'code';
            } else {
                begin_customer_otp(normalize_phone((string) ($_POST['mobile'] ?? '')));
                $notice = 'If this mobile can sign in, a code was sent.';
                $step = 'code';
            }
        }
    } catch (Throwable $err) {
        $error = safe_error($err, 'Sign-in is unavailable right now.');
    }
}
$sentAt = (int) ($_SESSION['email_sent_at'] ?? $_SESSION['otp_sent_at'] ?? 0);
$wait = max(0, 45 - (time() - $sentAt));
$next = rawurlencode(safe_next((string) ($_SESSION['login_next'] ?? ''), '/account'));
render_header('Sign in | Precision Agritech', 'Sign in to Precision Agritech.');
?>
<section class="section auth-wrap"><div class="auth-card">
  <h1>Sign in</h1>
  <?php if ($error): ?><p class="flash bad" role="alert"><?= e($error) ?></p><?php endif; ?>
  <?php if ($notice): ?><p class="flash ok" role="status"><?= e($notice) ?></p><?php endif; ?>
  <?php if (!empty($_SESSION['email_debug']) && !empty($GLOBALS['config']['show_otp_on_screen'])): ?>
    <p class="flash">Code <?= e((string) $_SESSION['email_debug']) ?></p>
  <?php endif; ?>
  <?php if (google_ready() && $step === 'ask'): ?>
    <a class="btn btn-block" href="/auth/google?next=<?= e(safe_next((string) ($_SESSION['login_next'] ?? ''), '/account')) ?>">Continue with Google</a>
    <p class="or-line">or</p>
  <?php endif; ?>
  <?php if (sms_ready()): ?>
    <p class="method-tabs">
      <a href="/login?method=email"<?= $method === 'email' ? ' aria-current="page"' : '' ?>>Email</a>
      <a href="/login?method=mobile"<?= $method === 'mobile' ? ' aria-current="page"' : '' ?>>Mobile</a>
    </p>
  <?php endif; ?>
  <?php if ($step === 'profile'): ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="method" value="<?= e($method) ?>">
      <input type="hidden" name="step" value="profile">
      <label>Name <input name="name" required autocomplete="name"></label>
      <label>Mobile <input name="mobile" required inputmode="tel" autocomplete="tel" placeholder="10-digit mobile"></label>
      <?php if (!empty($_SESSION['otp_verified_phone'])): ?>
        <label>Email <input name="email" type="email" required autocomplete="email"></label>
      <?php endif; ?>
      <button class="btn btn-block" type="submit">Continue</button>
    </form>
  <?php elseif ($step === 'code'): ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="method" value="<?= e($method) ?>">
      <input type="hidden" name="step" value="code">
      <label>Code <input name="code" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" required autocomplete="one-time-code"></label>
      <button class="btn btn-block" type="submit">Sign in</button>
    </form>
    <p><a href="/login?change=1">Change</a></p>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="method" value="<?= e($method) ?>">
      <input type="hidden" name="step" value="resend">
      <button class="text-link" type="submit" data-resend="<?= (int) $wait ?>" data-label="Resend code">Resend code</button>
    </form>
  <?php else: ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="method" value="<?= e($method) ?>">
      <input type="hidden" name="step" value="ask">
      <?php if ($method === 'mobile'): ?>
        <label>Mobile <input name="mobile" required inputmode="tel" autocomplete="tel" placeholder="10-digit mobile"></label>
      <?php else: ?>
        <label>Email <input name="email" type="email" required autocomplete="email"></label>
      <?php endif; ?>
      <button class="btn btn-block" type="submit">Send code</button>
    </form>
  <?php endif; ?>
  <p class="muted">No account needed — you can also <a href="/shop">order as a guest</a>.</p>
  <p class="consent">By continuing you agree we may receive your name and email from Google, email you a sign-in code, and use your mobile number for delivery. <a href="/privacy">Privacy Policy</a></p>
</div></section>
<?php render_footer(); ?>
