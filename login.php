<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();

if (isset($_GET['next'])) {
    $_SESSION['login_next'] = safe_next((string) $_GET['next']);
}
$signedIn = current_user();
if ($signedIn && ($signedIn['role'] ?? '') !== 'admin') {
    header('Location: ' . safe_next((string) ($_SESSION['login_next'] ?? ''), '/account'));
    exit;
}
if (isset($_GET['change'])) {
    unset(
        $_SESSION['otp_id'],
        $_SESSION['otp_phone'],
        $_SESSION['otp_purpose'],
        $_SESSION['otp_decoy'],
        $_SESSION['otp_debug'],
        $_SESSION['otp_verified_phone'],
        $_SESSION['otp_sent_at']
    );
    header('Location: /login');
    exit;
}

$error = null;
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $step = (string) ($_POST['step'] ?? 'ask');
        if ($step === 'profile') {
            $phone = (string) ($_SESSION['otp_verified_phone'] ?? '');
            if ($phone === '') {
                throw new RuntimeException('Verify the mobile number first.');
            }
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            if (strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Enter your name and a valid email.');
            }
            $exists = db()->prepare('SELECT id, role FROM users WHERE phone = ? LIMIT 1');
            $exists->execute([$phone]);
            $already = $exists->fetch();
            if ($already && ($already['role'] ?? '') !== 'admin') {
                $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
                $stmt->execute([(int) $already['id']]);
                $user = $stmt->fetch();
                sign_in_user($user);
                header('Location: ' . login_destination());
                exit;
            }
            if ($already) {
                throw new RuntimeException('That sign-in is not right.');
            }
            $hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
            db()->prepare('INSERT INTO users (name, email, phone, password_hash, role, status, phone_verified_at) VALUES (?, ?, ?, ?, ?, ?, NOW())')
                ->execute([$name, $email, $phone, $hash, 'customer', 'active']);
            $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([(int) db()->lastInsertId()]);
            sign_in_user($stmt->fetch());
            unset($_SESSION['otp_verified_phone']);
            header('Location: ' . login_destination());
            exit;
        }
        if ($step === 'code') {
            $phone = (string) ($_SESSION['otp_phone'] ?? '');
            if ($phone === '' || too_many_attempts('otp:' . $phone)) {
                throw new RuntimeException('That sign-in is not right.');
            }
            $phone = check_mobile_code(trim((string) ($_POST['code'] ?? '')), 'login');
            $stmt = db()->prepare('SELECT * FROM users WHERE phone = ? AND status = "active" LIMIT 1');
            $stmt->execute([$phone]);
            $user = $stmt->fetch();
            if ($user && ($user['role'] ?? '') === 'admin') {
                throw new RuntimeException('That sign-in is not right.');
            }
            if ($user) {
                if (empty($user['phone_verified_at'])) {
                    db()->prepare('UPDATE users SET phone_verified_at = NOW() WHERE id = ?')->execute([(int) $user['id']]);
                    $user['phone_verified_at'] = date('Y-m-d H:i:s');
                }
                sign_in_user($user);
                header('Location: ' . login_destination());
                exit;
            }
            $_SESSION['otp_verified_phone'] = $phone;
        } elseif ($step === 'resend') {
            $phone = (string) ($_SESSION['otp_phone'] ?? '');
            if ($phone === '') {
                throw new RuntimeException('Enter the mobile number again.');
            }
            begin_customer_otp($phone);
            $notice = 'A new code is on the way.';
        } else {
            $phone = normalize_phone((string) ($_POST['mobile'] ?? ''));
            if (too_many_attempts('otp:' . $phone)) {
                throw new RuntimeException('Too many attempts. Wait and try again.');
            }
            begin_customer_otp($phone);
        }
    } catch (Throwable $err) {
        $message = $err->getMessage();
        if ($err instanceof PDOException) {
            error_log($message);
            $error = str_contains($message, 'email')
                ? 'That email already has an account.'
                : (str_contains($message, 'phone') ? 'That mobile number is already on an account.' : 'The account could not be created.');
        } else {
            $error = safe_error($err, 'Sign-in is unavailable right now.');
        }
    }
}

$stepView = 'ask';
if (!empty($_SESSION['otp_verified_phone'])) {
    $stepView = 'profile';
} elseif (!empty($_SESSION['otp_phone']) && (($_SESSION['otp_purpose'] ?? '') === 'login')) {
    $stepView = 'code';
}
$wait = 0;
if ($stepView === 'code') {
    $sentAt = (int) ($_SESSION['otp_sent_at'] ?? time());
    $wait = max(0, 45 - (time() - $sentAt));
}

render_header('Sign in | Precision Agritech');
?>
<section class="section auth-wrap"><div class="auth-card">
  <h1>Sign in or create account</h1>
  <?php if ($error): ?><p class="flash" role="alert"><?= e($error) ?></p><?php endif; ?>
  <?php if ($notice): ?><p class="flash"><?= e($notice) ?></p><?php endif; ?>
  <?php if ($stepView === 'profile'): ?>
    <p>This number is new here. Add your name and email to finish the account.</p>
    <form method="post" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="step" value="profile">
      <label>Name <input name="name" autocomplete="name" required value="<?= e((string) ($_POST['name'] ?? '')) ?>"></label>
      <label>Email <input name="email" type="email" autocomplete="email" inputmode="email" required value="<?= e((string) ($_POST['email'] ?? '')) ?>"></label>
      <button class="btn" type="submit">Create account</button>
    </form>
  <?php elseif ($stepView === 'code'): ?>
    <p>Enter the 6-digit code sent to <?= e(mask_phone((string) $_SESSION['otp_phone'])) ?>.</p>
    <?= otp_debug_notice() ?>
    <form method="post" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="step" value="code">
      <label>Code
        <input name="code" inputmode="numeric" autocomplete="one-time-code" required maxlength="6" pattern="[0-9]{6}" placeholder="6-digit code">
      </label>
      <button class="btn" type="submit">Verify</button>
    </form>
    <div class="auth-links">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="resend">
        <button class="btn" type="submit" data-resend="<?= (int) $wait ?>" data-label="Resend code">Resend code</button>
      </form>
      <a href="/login?change=1">Change number</a>
    </div>
    <script src="/assets/js/signin.js"></script>
  <?php else: ?>
    <form method="post" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="step" value="ask">
      <label>Mobile number
        <span class="phone-field">
          <span class="phone-prefix">+91</span>
          <input name="mobile" type="tel" inputmode="numeric" pattern="[6-9][0-9]{9}" maxlength="10" autocomplete="tel-national" required placeholder="10-digit mobile" value="<?= e((string) ($_POST['mobile'] ?? '')) ?>">
        </span>
      </label>
      <button class="btn" type="submit">Send OTP</button>
    </form>
  <?php endif; ?>
</div></section>
<?php render_footer(); ?>
