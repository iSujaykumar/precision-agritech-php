<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
connect_or_explain();
$me = current_user();
if ($me && $me['role'] === 'admin') {
    header('Location: /admin');
    exit;
}
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if (too_many_attempts('admin:' . $email)) {
            throw new RuntimeException('Too many attempts. Wait 15 minutes and try again.');
        }
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? AND role = "admin" AND status = "active"');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user || !password_verify((string) ($_POST['password'] ?? ''), (string) $user['password_hash'])) {
            note_login_failure('admin:' . $email);
            throw new RuntimeException('That staff sign-in is not right.');
        }
        sign_in_user($user);
        header('Location: /admin');
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'Staff sign-in is unavailable right now.');
    }
}
render_header('Staff sign in | Precision Agritech', '', 'staff');
?>
<section class="section auth-wrap"><div class="auth-card">
  <p class="kicker">Staff only</p>
  <h1>Staff sign in</h1>
  <?php if ($error): ?><p class="flash" role="alert"><?= e($error) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Staff email <input name="email" type="email" autocomplete="username" required value="<?= e((string) ($_POST['email'] ?? '')) ?>"></label>
    <label>Password <input name="password" type="password" autocomplete="current-password" required minlength="12"></label>
    <button class="btn" type="submit">Sign in</button>
  </form>
  <p class="muted">Customers sign in with a mobile code on the shop. This page is for staff only.</p>
</div></section>
<?php render_footer(); ?>
