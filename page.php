<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$pages = [
    'about' => ['About', 'Precision Agritech Pvt. Ltd. grows flower seedling trays at Theur, Pune. The tray is the unit of sale.'],
    'nursery' => ['Our nursery', 'The nursery is at Survey No. 44/2, Theur Naygaon Road, Gaikwadvasti, Pune 412110. Photographs on this site are from the nursery. Call 9011975959 before a large visit.'],
    'faq' => ['FAQ', 'Trays are sold as a full tray. Shipping inside the quoted area is ₹180, and free over ₹4,000. Cash on delivery and bank transfer are confirmed by the nursery. Tax is not added.'],
    'shipping' => ['Shipping', 'Seedling trays leave after the nursery confirms the order. The flat shipping charge is ₹180. Orders of ₹4,000 and above ship free. There is no courier tracking number.'],
    'returns' => ['Returns', 'Live seedlings are not returned once they leave the nursery, unless the nursery agrees the tray was damaged before dispatch. Call 9011975959 with the order number.'],
    'privacy' => ['Privacy', 'The order address is shown only on the private order link. The order number alone does not show it. Account orders are visible only to that account and the nursery desk.'],
    'terms' => ['Terms', 'Prices on the site are starting rates in rupees. The server total at checkout is the amount due. Large lots can be revised by phone before dispatch.'],
    'wholesale' => ['Wholesale', 'For farm and landscaping lots, call 9011975959 or send the form. A form does not reserve trays.'],
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
        db()->prepare('INSERT INTO wholesale_requests (name, phone, email, location, products, quantity, notes) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                trim((string) $_POST['name']),
                trim((string) $_POST['phone']),
                strtolower(trim((string) $_POST['email'])),
                trim((string) $_POST['location']),
                trim((string) $_POST['products']),
                trim((string) $_POST['quantity']),
                trim((string) ($_POST['notes'] ?? '')),
            ]);
        $done = true;
    } catch (Throwable $err) {
        $error = $err->getMessage();
    }
}
[$title, $body] = $pages[$key];
render_header($title . ' | Precision Agritech');
?>
<section class="section"><div class="wrap narrow">
  <h1><?= e($title) ?></h1>
  <?php if ($key === 'nursery'): ?>
    <img src="/brand/nursery-fields.webp" alt="The nursery fields at Theur" width="1600" height="1200">
  <?php endif; ?>
  <p><?= e($body) ?></p>
  <?php if ($key === 'wholesale'): ?>
    <?php if ($done): ?><p class="flash">Request saved.</p><?php endif; ?>
    <?php if ($error): ?><p class="flash"><?= e($error) ?></p><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <label>Name <input name="name" required></label>
      <label>Phone <input name="phone" required pattern="[0-9]{10}"></label>
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
