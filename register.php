<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if (strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            throw new RuntimeException('Use a name, email and a password of at least 8 characters.');
        }
        db()->prepare('INSERT INTO users (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, ?)')
            ->execute([$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT), 'customer']);
        $_SESSION['user_id'] = (int) db()->lastInsertId();
        header('Location: /account');
        exit;
    } catch (Throwable $err) {
        $error = str_contains($err->getMessage(), 'Duplicate') ? 'That email already has an account.' : $err->getMessage();
    }
}
render_header('Create account | Precision Agritech');
?>
<section class="section"><div class="wrap narrow">
  <h1>Create account</h1>
  <?php if ($error): ?><p class="flash"><?= e($error) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Name <input name="name" required></label>
    <label>Email <input name="email" type="email" required></label>
    <label>Phone <input name="phone" pattern="[0-9]{10}"></label>
    <label>Password <input name="password" type="password" minlength="8" required></label>
    <button class="btn">Create account</button>
  </form>
</div></section>
<?php render_footer(); ?>
