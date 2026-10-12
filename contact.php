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
            if (too_many_attempts('contact-form')) {
                throw new RuntimeException('Too many messages. Wait a few minutes and try again.');
            }
            note_login_failure('contact-form');
            db()->prepare('INSERT INTO contact_messages (name, email, phone, body) VALUES (?, ?, ?, ?)')
                ->execute([$name, $email, trim((string) ($_POST['phone'] ?? '')), $body]);
            mail_staff('New message from ' . $name, $name . ' ' . $email . "\n" . $body);
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
  <?php $phone = shop_phone_digits(); $tel = strlen($phone) === 10 ? '91' . $phone : $phone; $email = shop_setting('shop_email', 'info@precisionagritech.in'); $wa = wa_href('Hello Precision Agritech, I want to know about seedling trays.'); ?>
  <p><a href="tel:+<?= e($tel) ?>"><?= e($phone) ?></a> · <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>
    <?php if ($wa !== ''): ?> · <a href="<?= e($wa) ?>" target="_blank" rel="noopener">Chat on WhatsApp</a><?php endif; ?>
  </p>
  <p class="muted"><?= e(shop_setting('opening_hours', '')) ?></p>
  <?php if ($done): ?><p class="flash">Message saved. The nursery will read it from the desk.</p><?php endif; ?>
  <?php if ($error): ?><p class="flash"><?= e($error) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <input class="hp" name="company_website" tabindex="-1" autocomplete="off" aria-hidden="true">
    <label>Name <input name="name" required></label>
    <label>Email <input name="email" type="email" required></label>
    <label>Phone <input name="phone"></label>
    <label>Message <textarea name="body" required></textarea></label>
    <button class="btn">Send</button>
  </form>
  <h2 id="grievance">Grievance officer</h2>
  <p><?= e(setting('grievance_officer_name', 'The nursery office')) ?><?php $role = 'Grievance officer'; ?> · <?= e($role) ?></p>
  <p><?php $gPhone = setting('grievance_officer_phone', shop_phone_digits()); $gMail = setting('grievance_officer_email', setting('shop_email', 'info@precisionagritech.in')); ?>
    <a href="tel:+91<?= e(preg_replace('/\D+/', '', $gPhone) ?? '') ?>"><?= e($gPhone) ?></a>
    · <a href="mailto:<?= e($gMail) ?>"><?= e($gMail) ?></a></p>
  <p>We acknowledge complaints within 48 hours and aim to resolve them within one month.</p>
</div></section>
<?php render_footer(); ?>
