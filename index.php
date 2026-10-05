<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();

$base = 'SELECT p.*, c.name AS category_name FROM products p
     JOIN categories c ON c.id = p.category_id
     WHERE p.active = 1 AND p.stock_qty > p.reserved_qty';
$bestsellers = db()->query($base . ' AND p.bestseller = 1 ORDER BY p.name LIMIT 8')->fetchAll();
$offers = db()->query($base . ' AND (p.on_offer = 1 OR (p.compare_at_inr IS NOT NULL AND p.compare_at_inr > p.price_inr)) ORDER BY p.name LIMIT 8')->fetchAll();
$categories = db()->query(
    'SELECT c.*, (
        SELECT image_url FROM products p
        WHERE p.category_id = c.id AND p.active = 1
        ORDER BY p.bestseller DESC, p.name LIMIT 1
     ) AS image_url FROM categories c ORDER BY c.sort_order'
)->fetchAll();
$reviews = db()->query(
    "SELECT r.author_name, r.rating, r.body, p.name AS product_name
     FROM reviews r JOIN products p ON p.id = r.product_id
     WHERE r.status = 'approved' ORDER BY r.id DESC LIMIT 3"
)->fetchAll();

render_header('Precision Agritech | Flower seedling trays from Theur, Pune', 'Order flower seedling trays from Precision Agritech, Theur. Sold by the tray.');
?>
<section class="hero">
  <img src="/brand/greenhouse-hero.webp" alt="Precision Agritech greenhouse at Theur" width="1600" height="1067">
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
    <div class="rail">
      <?php foreach ($bestsellers as $product) { product_card($product); } ?>
    </div>
  </div>
</section>
<section class="section" style="padding-top:0">
  <div class="wrap">
    <div class="head">
      <h2>Shop by category</h2>
      <a href="/shop">Shop all seedlings</a>
    </div>
    <div class="mosaic">
      <?php foreach ($categories as $index => $category): ?>
      <a class="tile<?= $index === 0 ? ' lead' : '' ?>" href="/shop?category=<?= e($category['slug']) ?>">
        <img src="<?= e((string) ($category['image_url'] ?: '/brand/seedling.webp')) ?>" alt="" width="800" height="600">
        <span><?= e($category['name']) ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php if ($offers): ?>
<section class="section">
  <div class="wrap">
    <h2>Offers</h2>
    <div class="rail">
      <?php foreach ($offers as $product) { product_card($product); } ?>
    </div>
  </div>
</section>
<?php endif; ?>
<section class="section" style="background:#fffcf7">
  <div class="wrap split">
    <img src="/brand/workbench.webp" alt="Seedling trays on a bench at the Theur nursery" width="1200" height="900">
    <div>
      <h2>Shop by use</h2>
      <ul class="use-list">
        <li><a href="/shop?use=beds"><strong>Flower beds</strong><span class="muted">Colour for open beds</span></a></li>
        <li><a href="/shop?use=pots"><strong>Pots</strong><span class="muted">Trays for containers</span></a></li>
        <li><a href="/shop?use=landscaping"><strong>Landscaping</strong><span class="muted">Runs of one variety</span></a></li>
        <li><a href="/shop?use=garlands"><strong>Garlands</strong><span class="muted">Marigold and seasonal colour</span></a></li>
        <li><a href="/shop?use=borders"><strong>Borders</strong><span class="muted">Front-of-bed plants</span></a></li>
      </ul>
    </div>
  </div>
</section>
<section class="section">
  <div class="wrap nursery">
    <img class="nursery-photo" src="/brand/nursery-fields.webp" alt="Fields beside the Precision Agritech nursery at Theur" width="1600" height="1200">
    <div>
      <p class="kicker" style="color:var(--muted)">Theur, Pune</p>
      <h2>Grown in our nursery</h2>
      <p class="muted">Precision Agritech grows flower seedlings at Survey No. 44/2, Theur Naygaon Road. These photographs are from the nursery. Trays are hardened before a booking leaves.</p>
      <img src="/brand/seedling.webp" alt="A young plant at the Precision Agritech nursery" width="1200" height="900" style="margin-top:1rem">
      <p><a class="btn" href="/nursery">Explore our nursery</a></p>
    </div>
  </div>
</section>
<section class="section" style="border-top:1px solid var(--line);border-bottom:1px solid var(--line)">
  <div class="wrap why">
    <h2>Why Precision Agritech</h2>
    <p><strong>Named varieties</strong><br><span class="muted">Each tray is a listed flower line, with colour, season and a plant count.</span></p>
    <p><strong>Held, then sold</strong><br><span class="muted">An order reserves trays. The nursery confirms payment before it becomes a sale.</span></p>
    <p><strong>Local, and by the tray</strong><br><span class="muted">The nursery is in Theur. Large lots are confirmed on 9011975959.</span></p>
  </div>
</section>
<section class="section band">
  <div class="wrap split">
    <div>
      <h2>Growing at scale?</h2>
      <p>For landscapers, growers and commercial projects. A form does not reserve trays.</p>
    </div>
    <p><a class="btn light" href="/wholesale">Request a bulk quote</a></p>
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
<section class="section" style="border-top:1px solid var(--line)">
  <div class="wrap head">
    <div>
      <h2>Ready to grow?</h2>
      <p class="muted">The full list of trays is in the shop.</p>
    </div>
    <a class="btn" href="/shop">Shop seedlings</a>
  </div>
</section>
<?php render_footer(); ?>
