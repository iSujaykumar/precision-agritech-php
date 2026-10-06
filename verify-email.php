<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();
$token = (string) ($_GET['token'] ?? '');
$hash = hash('sha256', $token);
$row = db()->prepare("SELECT * FROM email_tokens WHERE token_hash = ? AND purpose = 'verify' AND used_at IS NULL AND expires_at > NOW()");
$row->execute([$hash]);
$found = $row->fetch();
if (!$found) {
    render_header('Email link | Precision Agritech');
    echo '<section class="section"><div class="wrap narrow"><h1>That link has expired</h1><p><a href="/account">Back to your account</a></p></div></section>';
    render_footer();
    exit;
}
db()->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = ?')->execute([(int) $found['user_id']]);
db()->prepare('UPDATE email_tokens SET used_at = NOW() WHERE id = ?')->execute([(int) $found['id']]);
$email = db()->prepare('SELECT email FROM users WHERE id = ?');
$email->execute([(int) $found['user_id']]);
$address = (string) $email->fetchColumn();
db()->prepare('UPDATE orders SET user_id = ? WHERE customer_email = ? AND user_id IS NULL')->execute([(int) $found['user_id'], $address]);
flash('success', 'Email confirmed. Older guest orders with this email are now on the account.');
header('Location: /account');
exit;
