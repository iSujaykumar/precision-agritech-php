<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();

$base = 'SELECT p.*, c.name AS category_name FROM products p
     JOIN categories c ON c.id = p.category_id
     WHERE p.active = 1 AND p.stock_qty > p.reserved_qty';
$bestsellers = db()->query($base . ' AND p.bestseller = 1 ORDER BY p.name LIMIT 8')->fetchAll();
$offers = db()->query($base . ' AND (p.on_offer = 1 OR (p.compare_at_inr IS NOT NULL AND p.compare_at_inr > p.price_inr)) AND (p.offer_starts_at IS NULL OR p.offer_starts_at <= NOW()) AND (p.offer_ends_at IS NULL OR p.offer_ends_at >= NOW()) ORDER BY p.name LIMIT 8')->fetchAll();
$reviews = db()->query(
    "SELECT r.author_name, r.rating, r.body, p.name AS product_name
     FROM reviews r JOIN products p ON p.id = r.product_id
     WHERE r.status = 'approved' ORDER BY r.id DESC LIMIT 3"
)->fetchAll();

render_header('Precision Agritech | Flower seedling trays from Theur, Pune', 'Order flower seedling trays from Precision Agritech, Theur. Sold by the tray.');
?>
<section class="hero">
  <img src="/brand/greenhouse-hero.webp" alt="Precision Agritech greenhouse at Theur" width="1600" height="1067" decoding="async" fetchpriority="high">
  <div class="hero-shade"></div>
  <div class="hero-panel">
    <p class="kicker">Precision Agritech · Theur, Pune</p>
    <h1>Healthy seedlings. Better blooms.</h1>
    <p>Nursery-grown flower seedlings from Theur, Pune. Sold by the tray.</p>
    <div class="btn-row">
      <a class="btn light" href="/shop">Shop seedlings</a>
      <a class="btn ghost" href="/nursery">Explore our nursery</a>
    </div>
  </div>
</section>
<section class="trust">
  <p><strong>Nursery grown</strong><br><span class="muted">Raised at Theur, not bought in.</span></p>
  <p><strong>Theur, Pune</strong><br><span class="muted">Gaikwadvasti, 412110.</span></p>
  <p><strong>Sold by the tray</strong><br><span class="muted">The plant count is on each variety.</span></p>
  <p><strong>Bulk by phone</strong><br><a href="tel:+919011975959">9011975959</a></p>
</section>
<section class="section">
  <div class="wrap">
    <div class="head">
      <h2>Best sellers</h2>
      <a href="/shop">Shop all seedlings</a>
    </div>
    <div class="rail-wrap">
      <button class="rail-btn rail-prev" type="button" aria-label="Previous">&#8249;</button>
      <div class="rail" tabindex="0">
        <?php foreach ($bestsellers as $product) { product_card($product); } ?>
      </div>
      <button class="rail-btn rail-next" type="button" aria-label="Next">&#8250;</button>
    </div>
  </div>
</section>
<?php if ($offers): ?>
<section class="section">
  <div class="wrap">
    <div class="head">
      <h2>Offers</h2>
      <a href="/shop">Shop all seedlings</a>
    </div>
    <div class="rail-wrap">
      <button class="rail-btn rail-prev" type="button" aria-label="Previous">&#8249;</button>
      <div class="rail" tabindex="0">
        <?php foreach ($offers as $product) { product_card($product); } ?>
      </div>
      <button class="rail-btn rail-next" type="button" aria-label="Next">&#8250;</button>
    </div>
  </div>
</section>
<?php endif; ?>
<section class="section why-section">
  <div class="wrap why">
    <h2>Why Precision Agritech</h2>
    <p><strong>Named varieties</strong><span class="muted">Each tray is a listed flower line, with colour, season and a plant count.</span></p>
    <p><strong>Held, then sold</strong><span class="muted">An order reserves trays. The nursery confirms payment before it becomes a sale.</span></p>
    <p><strong>Local, and by the tray</strong><span class="muted">The nursery is in Theur. Large lots are confirmed on 9011975959.</span></p>
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
    <h2>Reviews</h2>
    <?php foreach ($reviews as $review): ?>
      <p><strong><?= e($review['author_name']) ?></strong> on <?= e($review['product_name']) ?> · <?= (int) $review['rating'] ?>/5<br><?= e($review['body']) ?></p>
    <?php endforeach; ?>
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
