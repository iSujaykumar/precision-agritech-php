<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user || !password_verify((string) ($_POST['password'] ?? ''), (string) $user['password_hash'])) {
            throw new RuntimeException('That email or password is not right.');
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        header('Location: /account');
        exit;
    } catch (Throwable $err) {
        $error = $err->getMessage();
    }
}
render_header('Sign in | Precision Agritech');
?>
<section class="section"><div class="wrap narrow">
  <h1>Sign in</h1>
  <?php if ($error): ?><p class="flash"><?= e($error) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Email <input name="email" type="email" required></label>
    <label>Password <input name="password" type="password" required></label>
    <button class="btn">Sign in</button>
  </form>
  <p><a href="/register">Create an account</a></p>
</div></section>
<?php render_footer(); ?>
