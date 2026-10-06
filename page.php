<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();

$ship = inr((int) setting('shipping_flat_inr', '180'));
$free = inr((int) setting('free_shipping_over_inr', '4000'));
$pages = [
    'about' => ['About', 'Precision Agritech grows flower seedling trays at Theur, Pune. You buy a full tray, not a single plant. The legal name and GSTIN, if the nursery is registered, should be confirmed by the owner before this page is treated as a legal notice.'],
    'nursery' => ['Our nursery', 'The nursery is at Survey No. 44/2, Theur Naygaon Road, Gaikwadvasti, Pune 412110. Photographs on this site are from the nursery. Call 9011975959 before a large visit. Hours are 9:00 to 6:00, Monday to Saturday.'],
    'faq' => ['FAQ', 'Trays are sold as a full tray. Delivery in the Pune area the nursery quotes is ' . $ship . ', and free when the order is ' . $free . ' or more. Cash on delivery and bank transfer are confirmed by the nursery. Tax is not added on this site. If you close the confirmation page, use the private link from your email, or call with the order number.'],
    'shipping' => ['Shipping', 'Seedling trays leave after the nursery confirms the order. Delivery in the quoted Pune area is ' . $ship . '. Orders of ' . $free . ' and above ship free. There is no courier tracking number. The nursery will call if the PIN is outside the usual run.'],
    'returns' => ['Returns', 'Live seedlings are not returned once they leave the nursery, unless the nursery agrees the tray was damaged before dispatch. Call 9011975959 with the order number the same day. You can ask to cancel an order that has not been dispatched from the private order link. The desk confirms the cancellation. A lawyer should review this policy before launch.'],
    'privacy' => ['Privacy', 'We keep your name, mobile, email and delivery address so we can grow and deliver the trays, and so you can see your own orders. The address is shown only on the private order link and to the nursery desk. We do not sell the list. To ask for a correction or deletion, email info@precisionagritech.in or call 9011975959. A lawyer should review this notice before launch.'],
    'terms' => ['Terms', 'The price you see at checkout is the price for that order. Large lots can still be revised by phone before dispatch if the nursery agrees. Payment is cash on delivery or bank transfer, and online only when that option is shown. Placing an order reserves the trays for the time shown to the nursery. This page is a shop notice, not a lawyer-reviewed contract.'],
    'wholesale' => ['Wholesale', 'For farm and landscaping lots, call 9011975959 or send the form. A form does not reserve trays. Tell us the varieties and how many trays you need, and the PIN where they should go.'],
];
$key = (string) ($_GET['p'] ?? 'about');
$missing = !isset($pages[$key]);
if ($missing) {
    http_response_code(404);
}
$done = false;
$error = null;
if (!$missing && $key === 'wholesale' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        form_is_human();
        if (too_many_attempts('form:' . client_ip())) {
            throw new RuntimeException('Please wait a little, then send the form again.');
        }
        note_login_failure('form:' . client_ip());
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $location = trim((string) ($_POST['location'] ?? ''));
        $products = trim((string) ($_POST['products'] ?? ''));
        $quantity = trim((string) ($_POST['quantity'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $pin = trim((string) ($_POST['pin'] ?? ''));
        $phone = normalize_phone((string) ($_POST['phone'] ?? ''));
        if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($location) < 3 || mb_strlen($products) < 2 || !preg_match('/^[0-9]{1,5}$/', $quantity) || !preg_match('/^[0-9]{6}$/', $pin)) {
            throw new RuntimeException('Check the name, mobile, email, varieties, number of trays and 6-digit PIN.');
        }
        db()->prepare('INSERT INTO wholesale_requests (name, phone, email, location, products, quantity, notes) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$name, $phone, $email, $location, $products, $quantity, 'PIN ' . $pin . ($notes !== '' ? "\n" . $notes : '')]);
        global $config;
        send_mail((string) ($config['mail_from'] ?? 'info@precisionagritech.in'), 'Wholesale request', $name . ' asked for ' . $quantity . ' trays. ' . $products . '. ' . $phone);
        flash('success', 'Thank you. The nursery will reply by phone or email. A form does not reserve trays.');
        header('Location: /wholesale');
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'The form could not be saved.');
    }
}
render_header(($missing ? 'Page not found' : $pages[$key][0]) . ' | Precision Agritech');
?>
<section class="section"><div class="wrap narrow">
  <?php if ($missing): ?>
    <h1>Page not found</h1>
    <p>That page is not on this site. <a href="/shop">Shop the trays</a> or <a href="/">go home</a>.</p>
  <?php else: [$title, $body] = $pages[$key]; ?>
  <h1><?= e($title) ?></h1>
  <?php if ($key === 'nursery'): ?>
    <img src="/brand/nursery-fields.webp" alt="The nursery fields at Theur" width="1600" height="900" loading="lazy" decoding="async">
  <?php endif; ?>
  <p><?= e($body) ?></p>
  <?php if ($key === 'wholesale'): ?>
    <?php if ($error): ?><p class="flash error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <?= opened_field() ?>
      <label>Name <input name="name" required value="<?= e((string) ($_POST['name'] ?? '')) ?>"></label>
      <label>Phone <input name="phone" type="tel" required value="<?= e((string) ($_POST['phone'] ?? '')) ?>"></label>
      <label>Email <input name="email" type="email" required value="<?= e((string) ($_POST['email'] ?? '')) ?>"></label>
      <label>Delivery location <input name="location" required value="<?= e((string) ($_POST['location'] ?? '')) ?>"></label>
      <label>PIN <input name="pin" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required value="<?= e((string) ($_POST['pin'] ?? '')) ?>"></label>
      <label>Varieties <input name="products" required value="<?= e((string) ($_POST['products'] ?? '')) ?>"></label>
      <label>Number of trays <input name="quantity" inputmode="numeric" required value="<?= e((string) ($_POST['quantity'] ?? '')) ?>"></label>
      <label>Notes <textarea name="notes"><?= e((string) ($_POST['notes'] ?? '')) ?></textarea></label>
      <button class="btn">Send wholesale request</button>
    </form>
  <?php endif; ?>
  <?php endif; ?>
</div></section>
<?php render_footer(); ?>
