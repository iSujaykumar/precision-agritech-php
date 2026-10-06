<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();
$count = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
if ($count > 0 || (string) ($config['setup_token'] ?? '') === '') {
    http_response_code(404);
    exit('Not found');
}
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        global $config;
        $expected = (string) ($config['setup_token'] ?? '');
        $given = (string) ($_POST['setup_token'] ?? '');
        if ($expected === '' || !hash_equals($expected, $given)) {
            note_login_failure('setup');
            throw new RuntimeException('The setup code is not right.');
        }
        if (too_many_attempts('setup')) {
            throw new RuntimeException('Too many attempts. Wait and try again.');
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $phone = normalize_phone((string) ($_POST['phone'] ?? ''));
        if (strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
            throw new RuntimeException('Use a name, email, mobile and a password of at least 12 characters.');
        }
        $pdo = db();
        $lock = $pdo->query("SELECT GET_LOCK('pa_setup_admin', 10)")->fetchColumn();
        if ((string) $lock !== '1') {
            throw new RuntimeException('Setup is busy. Try again.');
        }
        try {
            $count = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
            if ($count > 0) {
                throw new RuntimeException('A staff account already exists.');
            }
            $pdo->prepare('INSERT INTO users (name, email, phone, password_hash, role, status) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT), 'admin', 'active']);
            $id = (int) $pdo->lastInsertId();
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('pa_setup_admin')");
        }
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        sign_in_user($stmt->fetch());
        header('Location: /admin');
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'The staff account could not be created.');
    }
}
render_header('Create the staff account');
?>
<section class="section"><div class="wrap narrow">
  <h1>Create the staff account</h1>
  <p>This page works once. There is no preset password.</p>
  <?php if ($error): ?><p class="flash"><?= e($error) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Setup code <input name="setup_token" required autocomplete="off"></label>
    <label>Name <input name="name" required></label>
    <label>Email <input name="email" type="email" required></label>
    <label>Mobile <input name="phone" type="tel" inputmode="tel" required placeholder="10-digit mobile"></label>
    <label>Password <input name="password" type="password" minlength="12" required></label>
    <button class="btn">Create staff login</button>
  </form>
</div></section>
<?php render_footer(); ?>
