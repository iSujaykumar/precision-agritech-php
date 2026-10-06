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
        if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            throw new RuntimeException('Use a name, email, mobile and a password of at least 8 characters.');
        }
        if (!hash_equals($password, $confirm)) {
            throw new RuntimeException('The two passwords do not match.');
        }
        $holder = db()->prepare('SELECT id, email_verified_at, phone_verified_at, created_at FROM users WHERE phone = ?');
        $holder->execute([$phone]);
        $other = $holder->fetch();
        if ($other && empty($other['email_verified_at']) && empty($other['phone_verified_at']) && strtotime((string) $other['created_at']) < time() - 7 * 86400) {
            db()->prepare('UPDATE users SET phone = NULL WHERE id = ?')->execute([(int) $other['id']]);
        }
        db()->prepare('INSERT INTO users (name, email, phone, password_hash, role, status) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT), 'customer', 'active']);
        $id = (int) db()->lastInsertId();
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $created = $stmt->fetch();
        sign_in_user($created);
        send_verify_email($created);
        header('Location: ' . (twilio_configured() ? '/verify-mobile' : '/account'));
        exit;
    } catch (Throwable $err) {
        if ($err instanceof PDOException && duplicate_key($err) !== '') {
            error_log($err->getMessage());
            $error = 'If this email and mobile are new, try again. If you already have an account, sign in.';
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
