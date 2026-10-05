<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();
$done = false;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        if (trim((string) ($_POST['company_website'] ?? '')) !== '') {
            $done = true;
        } else {
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $body = trim((string) ($_POST['body'] ?? ''));
            if (strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($body) < 8) {
                throw new RuntimeException('Add your name, email and a message.');
            }
            db()->prepare('INSERT INTO contact_messages (name, email, phone, body) VALUES (?, ?, ?, ?)')
                ->execute([$name, $email, trim((string) ($_POST['phone'] ?? '')), $body]);
            $done = true;
        }
    } catch (Throwable $err) {
        $error = safe_error($err, 'The message could not be saved.');
    }
}
render_header('Contact | Precision Agritech');
?>
<section class="section"><div class="wrap narrow">
  <h1>Contact the nursery</h1>
  <p>9011975959 · info@precisionagritech.in</p>
  <?php if ($done): ?><p class="flash">Message saved. The nursery will read it from the desk.</p><?php endif; ?>
  <?php if ($error): ?><p class="flash"><?= e($error) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <input name="company_website" style="display:none" tabindex="-1" autocomplete="off">
    <label>Name <input name="name" required></label>
    <label>Email <input name="email" type="email" required></label>
    <label>Phone <input name="phone"></label>
    <label>Message <textarea name="body" required></textarea></label>
    <button class="btn">Send</button>
  </form>
</div></section>
<?php render_footer(); ?>
