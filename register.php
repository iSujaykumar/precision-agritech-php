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
        $phone = normalize_phone((string) ($_POST['phone'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm'] ?? '');
        if (strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            throw new RuntimeException('Use a name, email, mobile and a password of at least 8 characters.');
        }
        if (!hash_equals($password, $confirm)) {
            throw new RuntimeException('The two passwords do not match.');
        }
        db()->prepare('INSERT INTO users (name, email, phone, password_hash, role, status) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT), 'customer', 'active']);
        $id = (int) db()->lastInsertId();
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        sign_in_user($stmt->fetch());
        header('Location: /verify-mobile');
        exit;
    } catch (Throwable $err) {
        $message = $err->getMessage();
        if ($err instanceof PDOException) {
            error_log($message);
            $error = str_contains($message, 'phone')
                ? 'That mobile number is already on an account.'
                : (str_contains($message, 'email') ? 'That email already has an account.' : 'The account could not be created.');
        } else {
            $error = safe_error($err, 'The account could not be created.');
        }
    }
}
render_header('Create account | Precision Agritech');
?>
<section class="section"><div class="wrap narrow">
  <h1>Create account</h1>
  <?php if ($error): ?><p class="flash" role="alert"><?= e($error) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Name <input name="name" autocomplete="name" required value="<?= e((string) ($_POST['name'] ?? '')) ?>"></label>
    <label>Email <input name="email" type="email" autocomplete="email" inputmode="email" required value="<?= e((string) ($_POST['email'] ?? '')) ?>"></label>
    <label>Mobile <input name="phone" type="tel" autocomplete="tel" inputmode="tel" required placeholder="10-digit mobile" value="<?= e((string) ($_POST['phone'] ?? '')) ?>"></label>
    <label>Password <input name="password" type="password" autocomplete="new-password" minlength="8" required></label>
    <label>Confirm password <input name="confirm" type="password" autocomplete="new-password" minlength="8" required></label>
    <button class="btn">Create account</button>
  </form>
</div></section>
<?php render_footer(); ?>
