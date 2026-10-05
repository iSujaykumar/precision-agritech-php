<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();
$user = require_user();
$error = null;
$notice = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $current = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $current->execute([(int) $user['id']]);
        $hash = (string) $current->fetchColumn();
        $next = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm'] ?? '');
        if (!password_verify((string) ($_POST['current'] ?? ''), $hash)) {
            throw new RuntimeException('The current password is not right.');
        }
        if (strlen($next) < 8 || !hash_equals($next, $confirm)) {
            throw new RuntimeException('Use matching passwords of at least 8 characters.');
        }
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($next, PASSWORD_DEFAULT), (int) $user['id']]);
        rotate_csrf();
        $notice = 'Password updated.';
    } catch (Throwable $err) {
        $error = safe_error($err, 'The password could not be changed.');
    }
}
render_header('Change password | Precision Agritech');
?>
<section class="section"><div class="wrap narrow">
  <h1>Change password</h1>
  <?php if ($notice): ?><p class="flash"><?= e($notice) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="flash" role="alert"><?= e($error) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Current password <input name="current" type="password" required></label>
    <label>New password <input name="password" type="password" minlength="8" required></label>
    <label>Confirm password <input name="confirm" type="password" minlength="8" required></label>
    <button class="btn">Save password</button>
  </form>
</div></section>
<?php render_footer(); ?>
