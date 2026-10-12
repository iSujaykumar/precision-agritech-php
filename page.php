<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$pages = [
    'about' => ['About', [
        'Precision Agritech Private Limited grows flower seedling trays at Theur, near Pune. The tray is the unit of sale. Each tray holds 104 plants.',
        'Landscapers, farms and home gardeners order from this site. The nursery delivers with its own vehicles. There is no courier.',
        'Prices on the site are the prices charged. The total you see at checkout is the amount due.',
    ]],
    'nursery' => ['Our nursery', [
        'The nursery is at Survey No. 44/2, Theur Naygaon Road, Gaikwadvasti, Pune 412110. Trays are hardened here before they leave.',
        'Photographs on this site are from the nursery. Call before a large visit so someone is free to walk the beds with you.',
        'Opening hours are on the contact page. Wholesale lots are booked with the office, not only through the cart.',
    ]],
    'faq' => ['FAQ', [
        'A tray is 104 plants. We do not split a tray on this site.',
        'You can pay by UPI, bank transfer, or cash on delivery where that option is open. The nursery confirms UPI and bank payments before the trays leave.',
        'Delivery is by the nursery vehicle, usually 2 to 4 days after payment is confirmed. Cash orders are confirmed by phone first.',
        'If a tray arrives damaged, report it from the order page within the time shown there, with photos.',
        'You can check out as a guest. An account is only for sign-in and for seeing past orders.',
    ]],
    'shipping' => ['Shipping', []],
    'returns' => ['Returns and replacement', [
        'Live seedlings can be replaced or refunded when the nursery agrees the tray was damaged, short, or not what you ordered.',
        'Report the problem from your order page within 48 hours of delivery, with up to three photos. The nursery replies by email.',
        'A fair outcome is a replacement tray or a refund of the affected trays. We do not refuse every claim.',
        'Trays that were healthy on delivery and later failed from weather, watering or planting are not a nursery claim.',
    ]],
    'cancellation' => ['Cancellation and refund', [
        'You can ask to cancel before the trays leave the nursery. Call or WhatsApp with the order number.',
        'If a UPI or bank payment has not been confirmed, cancelling releases the trays. If we have already confirmed payment, we refund the amount you paid to the same method, after the nursery records it.',
        'Cash on delivery orders that are cancelled before dispatch have nothing to refund.',
        'A reservation that expires unpaid is cancelled automatically and the trays go back on sale.',
    ]],
    'privacy' => ['Privacy policy', [
        'We collect your name, mobile number, delivery address, and email if you give one, so we can grow, deliver and support your order.',
        'If you continue with Google, we receive your name and the email Google has verified. We do not receive your Google password.',
        'Email sign-in sends a short code to that address. Mobile numbers are used for delivery and for calls about the order. A mobile number is only treated as checked when an SMS code has been used.',
        'Guest orders are kept with the order. If you later sign in with the same verified email, those orders can show on your account.',
        'We share details with our own drivers so they can deliver. We do not sell your details.',
        'Orders and account details are kept while we need them for delivery, accounts and legal records. You can ask for access, a correction, or deletion from your account page or the contact page.',
        'The grievance officer on the contact page handles privacy complaints.',
    ]],
    'terms' => ['Terms of sale', [
        'The seller is Precision Agritech Private Limited, Theur, Pune. The contract is formed when the nursery confirms the order, not merely when the website accepts the form.',
        'The server calculates the price, discount and delivery charge from the nursery catalogue. A changed price in the browser is ignored.',
        'UPI and bank transfer orders reserve trays until the time shown on the order. Cash on delivery is confirmed by phone before dispatch.',
        'Risk in the plants passes when they are delivered to the address you gave. Report damage within the claim window.',
        'These terms are a plain-language summary for customers. They do not limit any right you have under Indian law.',
    ]],
    'wholesale' => ['Wholesale', [
        'For farm and landscaping lots, call the nursery or send the form. A form does not reserve trays.',
        'Wholesale prices can differ from the tray price on this site. The office confirms the lot, the date and the payment before dispatch.',
    ]],
];
$key = (string) ($_GET['p'] ?? 'about');
if (!isset($pages[$key])) {
    http_response_code(404);
    $key = 'about';
    $pages['about'][0] = 'Page not found';
}
$done = false;
$error = null;
if ($key === 'wholesale' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        connect_or_explain();
        require_csrf();
        if (too_many_attempts('wholesale-form')) {
            throw new RuntimeException('Too many requests. Wait a few minutes and try again.');
        }
        note_login_failure('wholesale-form');
        db()->prepare('INSERT INTO wholesale_requests (name, phone, email, location, products, quantity, notes) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                trim((string) $_POST['name']),
                normalize_phone((string) $_POST['phone']),
                strtolower(trim((string) $_POST['email'])),
                trim((string) $_POST['location']),
                trim((string) $_POST['products']),
                trim((string) $_POST['quantity']),
                trim((string) ($_POST['notes'] ?? '')),
            ]);
        mail_staff('New wholesale request', trim((string) $_POST['name']) . ' ' . trim((string) $_POST['phone']));
        $done = true;
    } catch (Throwable $err) {
        $error = safe_error($err, 'The form could not be saved.');
    }
}
[$title, $body] = $pages[$key];
connect_or_explain();
if (!is_array($body)) {
    $body = [$body];
}
if ($key === 'shipping') {
    $body = [
        'The nursery delivers with its own vehicles. There is no courier and no tracking number.',
        'The usual delivery charge is ' . inr((int) shop_setting('shipping_flat_inr', '180')) . '. Orders of ' . inr((int) shop_setting('free_shipping_over_inr', '4000')) . ' and above have free delivery.',
        'Some PIN codes can have a different charge. Checkout shows the charge before you pay.',
        shop_setting('delivery_area_text', 'We deliver with our own nursery vehicles.') . ' If your PIN is outside the area, checkout will say so and ask you to call.',
        'Trays usually leave 2 to 4 days after payment is confirmed, or after a cash order is confirmed by phone.',
    ];
}
render_header($title . ' | Precision Agritech');
?>
<section class="section"><div class="wrap narrow">
  <h1><?= e($title) ?></h1>
  <?php if ($key === 'nursery'): ?>
    <img src="/brand/nursery-fields.webp" alt="The nursery fields at Theur" width="1600" height="1200" loading="lazy" decoding="async">
  <?php endif; ?>
  <?php foreach ($body as $paragraph): ?><p><?= e($paragraph) ?></p><?php endforeach; ?>
  <?php if ($key === 'wholesale'): ?>
    <?php if ($done): ?><p class="flash">Request saved.</p><?php endif; ?>
    <?php if ($error): ?><p class="flash"><?= e($error) ?></p><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <label>Name <input name="name" required></label>
      <label>Phone <input name="phone" required></label>
      <label>Email <input name="email" type="email" required></label>
      <label>Location <input name="location" required></label>
      <label>Trays needed <input name="products" required></label>
      <label>Quantity <input name="quantity" required></label>
      <label>Notes <textarea name="notes"></textarea></label>
      <button class="btn">Send wholesale request</button>
    </form>
  <?php endif; ?>
</div></section>
<?php render_footer(); ?>
