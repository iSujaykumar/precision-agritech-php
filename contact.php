<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();
$done = false;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        form_is_human();
        if (too_many_attempts('contact:' . client_ip())) {
            throw new RuntimeException('Please wait a little, then send the message again.');
        }
        note_login_failure('contact:' . client_ip());
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $body = trim((string) ($_POST['body'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        if ($phone !== '') {
            $phone = normalize_phone($phone);
        }
        if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($body) < 8) {
            throw new RuntimeException('Add your name, email and a message.');
        }
        db()->prepare('INSERT INTO contact_messages (name, email, phone, body) VALUES (?, ?, ?, ?)')
            ->execute([$name, $email, $phone, $body]);
        global $config;
        send_mail((string) ($config['mail_from'] ?? 'info@precisionagritech.in'), 'New message from the website', $name . ' (' . $email . ') wrote: ' . $body, $email);
        flash('success', 'Thank you. The nursery will reply by phone or email.');
        header('Location: /contact');
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'The message could not be saved.');
    }
}
render_header('Contact | Precision Agritech');
?>
<section class="section"><div class="wrap narrow">
  <h1>Contact the nursery</h1>
  <p>9011975959 · info@precisionagritech.in</p>
  <?php if ($error): ?><p class="flash error" role="alert"><?= e($error) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <?= opened_field() ?>
    <label>Name <input name="name" required></label>
    <label>Email <input name="email" type="email" required></label>
    <label>Phone <input name="phone"></label>
    <label>Message <textarea name="body" required></textarea></label>
    <button class="btn">Send</button>
  </form>
</div></section>
<?php render_footer(); ?>
