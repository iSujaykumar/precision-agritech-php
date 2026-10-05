<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();

$error = null;
$mode = (($_POST['mode'] ?? 'email') === 'mobile') ? 'mobile' : 'email';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        if (!login_is_allowed()) {
            throw new RuntimeException('Too many attempts. Wait and try again.');
        }
        $password = (string) ($_POST['password'] ?? '');
        if ($mode === 'mobile') {
            $phone = normalize_phone((string) ($_POST['mobile'] ?? ''));
            $stmt = db()->prepare('SELECT * FROM users WHERE phone = ?');
            $stmt->execute([$phone]);
        } else {
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$email]);
        }
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            note_login_failure();
            throw new RuntimeException('That sign-in is not right.');
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        header('Location: ' . ($user['role'] === 'admin' ? '/admin' : '/account'));
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'Sign-in is unavailable right now.');
    }
}
render_header('Sign in | Precision Agritech');
?>
<section class="section"><div class="wrap narrow">
  <h1>Sign in</h1>
  <?php if ($error): ?><p class="flash" role="alert"><?= e($error) ?></p><?php endif; ?>
  <form method="post" id="signin">
    <?= csrf_field() ?>
    <div class="filters">
      <label><input type="radio" name="mode" value="email" <?= $mode === 'email' ? 'checked' : '' ?>> Email</label>
      <label><input type="radio" name="mode" value="mobile" <?= $mode === 'mobile' ? 'checked' : '' ?>> Mobile</label>
    </div>
    <label class="field-email">Email
      <input name="email" type="email" autocomplete="username" inputmode="email" value="<?= e((string) ($_POST['email'] ?? '')) ?>">
    </label>
    <label class="field-mobile">Mobile
      <input name="mobile" type="tel" autocomplete="tel" inputmode="tel" placeholder="10-digit mobile" value="<?= e((string) ($_POST['mobile'] ?? '')) ?>">
    </label>
    <label>Password
      <input name="password" type="password" autocomplete="current-password" required>
    </label>
    <button class="btn" type="submit">Sign in</button>
  </form>
  <p>OTP is not available. Sign in with your password.</p>
  <p><a href="/register">Create an account</a></p>
</div></section>
<script>
(function () {
  var form = document.getElementById('signin');
  function sync() {
    var mobile = form.querySelector('input[name="mode"][value="mobile"]').checked;
    form.querySelector('.field-email').hidden = mobile;
    form.querySelector('.field-mobile').hidden = !mobile;
  }
  form.addEventListener('change', sync);
  sync();
})();
</script>
<?php render_footer(); ?>
