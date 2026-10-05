<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();
$done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $stmt = db()->prepare('SELECT id, email FROM users WHERE email = ? AND status = "active"');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user) {
        $token = bin2hex(random_bytes(32));
        db()->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))')
            ->execute([(int) $user['id'], hash('sha256', $token)]);
        global $config;
        $link = rtrim((string) ($config['site_url'] ?? ''), '/') . '/reset-password?token=' . $token;
        $from = (string) ($config['mail_from'] ?? 'info@precisionagritech.in');
        $sent = mail(
            (string) $user['email'],
            'Reset your Precision Agritech password',
            "Use this link within 30 minutes. It works once.\n\n" . $link,
            'From: ' . $from . "\r\nContent-Type: text/plain; charset=UTF-8"
        );
        if (!$sent) {
            error_log('Password reset mail was not accepted for delivery.');
        }
    }
    $done = true;
}
render_header('Forgot password | Precision Agritech');
?>
<section class="section"><div class="wrap narrow">
  <h1>Forgot password</h1>
  <?php if ($done): ?>
    <p>If that email has an account, a reset link is on its way. The link expires in 30 minutes.</p>
  <?php else: ?>
    <form method="post">
      <?= csrf_field() ?>
      <label>Email <input name="email" type="email" required></label>
      <button class="btn">Send reset link</button>
    </form>
  <?php endif; ?>
</div></section>
<?php render_footer(); ?>
