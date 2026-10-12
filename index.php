<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();

$bundle = home_bundle();
$bestsellers = $bundle['bestsellers'];
$arrivals = $bundle['new'];
$offers = $bundle['offers'];
$cards = $bundle['cards'];
$monthNo = (int) (new DateTimeImmutable('now', shop_timezone()))->format('n');
$monthNames = array_map('trim', explode(',', month_plant_text($monthNo)));
$monthPlants = [];
foreach ($cards as $card) {
    foreach ($monthNames as $wanted) {
        if ($wanted !== '' && strcasecmp((string) $card['name'], $wanted) === 0) {
            $monthPlants[] = $card;
        }
    }
}
$reviews = db()->query(
    "SELECT r.author_name, r.rating, r.body, r.created_at, p.name AS product_name
     FROM reviews r LEFT JOIN products p ON p.id = r.product_id
     WHERE r.status = 'approved' ORDER BY r.id DESC LIMIT 6"
)->fetchAll();
$GLOBALS['json_ld'] = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'LocalBusiness',
    'name' => 'Precision Agritech',
    'telephone' => array_map(static fn ($row) => '+91' . $row['digits'], public_phone_rows()) ?: shop_phone_digits(),
    'email' => shop_setting('shop_email', 'info@precisionagritech.in'),
    'address' => shop_setting('shop_address', 'Survey No. 44/2, Theur Naygaon Road, Gaikwadvasti, Pune 412110'),
    'image' => '/brand/greenhouse-hero.webp',
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$phone = shop_phone_digits();
$tel = strlen($phone) === 10 ? '91' . $phone : $phone;

render_header('Precision Agritech | Flower seedling trays from Theur, Pune', 'Order flower seedling trays from Precision Agritech, Theur. Sold by the tray.');
?>
<section class="hero">
  <img src="/brand/greenhouse-hero.webp" alt="Precision Agritech greenhouse at Theur" width="1600" height="1067" decoding="async" fetchpriority="high">
  <div class="hero-shade"></div>
  <div class="hero-panel">
    <p class="kicker"><?= e(setting('hero_kicker', 'Precision Agritech · Theur, Pune')) ?></p>
    <h1><?= e(setting('hero_title', 'Healthy seedlings. Better blooms.')) ?></h1>
    <p><?= e(setting('hero_text', 'Nursery-grown flower seedlings from Theur, Pune. Sold by the tray.')) ?></p>
    <div class="btn-row">
      <a class="btn light" href="<?= e(safe_shop_href(setting('hero_primary_href', '/shop'), '/shop')) ?>"><?= e(setting('hero_primary_label', 'Shop seedlings')) ?></a>
      <a class="btn ghost" href="<?= e(safe_shop_href(setting('hero_secondary_href', '/nursery'), '/nursery')) ?>"><?= e(setting('hero_secondary_label', 'Explore our nursery')) ?></a>
    </div>
  </div>
</section>
<section class="trust">
  <p><strong>Nursery grown</strong><br><span class="muted">Raised at Theur, not bought in.</span></p>
  <p><strong>Theur, Pune</strong><br><span class="muted">Gaikwadvasti, 412110.</span></p>
  <p><strong>Sold by the tray</strong><br><span class="muted">The plant count is on each variety.</span></p>
  <p><strong>Bulk by phone</strong><br><a href="tel:+<?= e($tel) ?>"><?= e($phone) ?></a></p>
</section>
<?php
$banner = discount_banner_text();
if ($banner !== '') {
    echo '<p class="announce">' . e($banner) . '</p>';
}
if (setting('rail_best_on', '1') === '1') {
    render_product_rail('best-sellers', setting('rail_best_title', 'Best sellers'), $bestsellers, 'No best sellers are ready to ship right now.', '/shop?sort=popular');
}
if (setting('rail_new_on', '1') === '1') {
    render_product_rail('new-arrivals', setting('rail_new_title', 'New arrivals'), $arrivals, 'No new trays are ready to ship right now.', '/shop?filter=new');
}
if (setting('rail_offers_on', '1') === '1') {
    render_product_rail('offers', setting('rail_offers_title', 'Offers'), $offers, 'No current offers.', '/shop?flag=offer');
}
?>
<section class="section" id="plant-this-month">
  <div class="wrap">
    <div class="head">
      <h2>Plant this month</h2>
      <a href="/shop">Browse all plants</a>
    </div>
    <p class="muted">Suited to this month around Pune.</p>
    <div class="filters">
      <?php foreach ($monthPlants as $plant): ?>
        <a href="/product/<?= e((string) $plant['slug']) ?>"><?= e((string) $plant['name']) ?></a>
      <?php endforeach; ?>
      <?php if (!$monthPlants): ?><span class="muted">The nursery has not listed this month yet.</span><?php endif; ?>
    </div>
  </div>
</section>
<section class="section" id="order-calculator">
  <div class="wrap narrow">
    <h2>Order calculator</h2>
    <p class="muted">Trays are sold whole. 150 seedlings is one tray. A part tray is rounded up.</p>
    <form class="calc" data-calc data-min-inr="<?= (int) setting('seasonal_discount_min_inr', '5000') ?>" data-min-seed="<?= (int) setting('discount_min_seedlings', '5000') ?>" data-percent="<?= (int) setting('seasonal_discount_percent', '10') ?>" data-basis="<?= e(setting('discount_basis', 'rupees')) ?>" data-on="<?= setting('seasonal_discount_on', '0') === '1' ? '1' : '0' ?>">
      <label>Plant
        <select data-calc-plant>
          <?php foreach ($cards as $plant):
            $sell = selling_price($plant);
            if (!empty($sell['blocked']) || (int) ($sell['paise'] ?? 0) < 1) { continue; }
          ?>
            <option value="<?= (int) $sell['paise'] ?>" data-tray="<?= (int) $sell['tray'] ?>"><?= e((string) $plant['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Number of seedlings <input data-calc-qty type="number" min="1" value="150"></label>
      <p data-calc-out class="price"></p>
    </form>
  </div>
</section>
<section class="section" id="browse-all">
  <div class="wrap">
    <div class="head">
      <h2>Browse all plants</h2>
      <a href="/price-list">Full price list</a>
    </div>
    <?php
    $lastCat = '';
    foreach ($cards as $plant):
      if ($plant['category_name'] !== $lastCat):
        if ($lastCat !== '') { echo '</div>'; }
        $lastCat = (string) $plant['category_name'];
        echo '<h3>' . e($lastCat) . '</h3><div class="grid">';
      endif;
      product_card($plant);
    endforeach;
    if ($lastCat !== '') { echo '</div>'; }
    if (!$cards): ?><p>The plant list will appear after the shop update.</p><?php endif; ?>
  </div>
</section>
<p class="wrap btn-row">
  <a class="btn" href="/price-list?pdf=en">Download price list PDF</a>
  <a class="btn" href="/price-list?pdf=mr">Download Marathi PDF</a>
</p>
<section class="section why-section">
  <div class="wrap why">
    <h2>Why Precision Agritech</h2>
    <p><strong>Named varieties</strong><span class="muted">Each tray is a listed flower line, with colour, season and a plant count.</span></p>
    <p><strong>Held, then sold</strong><span class="muted">An order reserves trays. The nursery confirms payment before it becomes a sale.</span></p>
    <p><strong>Local, and by the tray</strong><span class="muted">The nursery is in Theur. Large lots are confirmed on <?= e($phone) ?>.</span></p>
  </div>
</section>
<section class="section band">
  <div class="wrap split">
    <div>
      <h2>Growing at scale?</h2>
      <p>For landscapers, growers and commercial projects. A form does not reserve trays.</p>
    </div>
    <p class="band-action"><a class="btn light" href="/wholesale">Request a bulk quote</a></p>
  </div>
</section>
<?php if ($reviews): ?>
<section class="section">
  <div class="wrap">
    <div class="head">
      <h2>What customers say</h2>
      <a href="/reviews">All reviews</a>
    </div>
    <div class="rail-wrap">
      <button class="rail-btn rail-prev" type="button" aria-label="Previous">&#8249;</button>
      <div class="rail" tabindex="0">
        <?php foreach ($reviews as $review): ?>
          <article class="card"><div class="card-body">
            <p><?= stars_markup((float) $review['rating']) ?></p>
            <p><?= e($review['body']) ?></p>
            <p class="muted"><?= e(public_reviewer((string) $review['author_name'])) ?> · <?= e((string) ($review['product_name'] ?: 'The nursery')) ?> · <?= e(date('d M Y', strtotime((string) $review['created_at']))) ?></p>
          </div></article>
        <?php endforeach; ?>
      </div>
      <button class="rail-btn rail-next" type="button" aria-label="Next">&#8250;</button>
    </div>
  </div>
</section>
<?php endif; ?>
<section class="section">
  <div class="wrap head">
    <div>
      <h2>Ready to grow?</h2>
      <p class="muted">The full list of trays is in the shop.</p>
    </div>
    <a class="btn" href="/shop">Shop seedlings</a>
  </div>
</section>
<?php render_footer(); ?>
