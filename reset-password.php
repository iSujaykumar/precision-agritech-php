<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();
$error = null;
$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$valid = false;
if ($token !== '') {
    $check = db()->prepare('SELECT id FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()');
    $check->execute([hash('sha256', $token)]);
    $valid = (bool) $check->fetch();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm'] ?? '');
        if (strlen($password) < 8 || !hash_equals($password, $confirm)) {
            throw new RuntimeException('Use matching passwords of at least 8 characters.');
        }
        $stmt = db()->prepare('SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()');
        $stmt->execute([hash('sha256', $token)]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('This reset link is no longer valid.');
        }
        db()->prepare('UPDATE users SET password_hash = ?, password_changed_at = NOW() WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), (int) $row['user_id']]);
        db()->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')->execute([(int) $row['user_id']]);
        $who = db()->prepare('SELECT email FROM users WHERE id = ?');
        $who->execute([(int) $row['user_id']]);
        send_mail((string) $who->fetchColumn(), 'Your Precision Agritech password was changed', 'The password on your account was just changed. If this was not you, call 9011975959.');
        flash('success', 'Password updated. Sign in with the new password.');
        header('Location: /login');
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'The password could not be changed.');
    }
}
render_header('Reset password | Precision Agritech');
?>
<section class="section"><div class="wrap narrow">
  <h1>Reset password</h1>
  <?php if (!$valid): ?>
    <p>That link has expired. <a href="/forgot-password">Ask for a new one</a>.</p>
  <?php else: ?>
  <?php if ($error): ?><p class="flash error" role="alert"><?= e($error) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <label>New password <input name="password" type="password" minlength="8" required></label>
    <label>Confirm password <input name="confirm" type="password" minlength="8" required></label>
    <button class="btn">Save password</button>
  </form>
  <?php endif; ?>
</div></section>
<?php render_footer(); ?>
