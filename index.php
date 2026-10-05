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
  <img src="/brand/greenhouse-hero.webp" alt="The Precision Agritech greenhouse at Theur" width="1600" height="1067">
  <div class="hero-copy"><div class="hero-panel">
    <p class="kicker">Theur, Pune · since 2009</p>
    <h1>Flower seedling trays, grown here.</h1>
    <p>Sold by the tray. Catalogue prices are starting rates. Large lots are confirmed by phone.</p>
    <a class="btn light" href="/shop">Shop trays</a>
  </div></div>
</section>
<section class="trust">
  <p><strong>Nursery grown</strong><br><span class="muted">Raised at Theur, not bought in.</span></p>
  <p><strong>Theur, Pune</strong><br><span class="muted">Gaikwadvasti, 412110.</span></p>
  <p><strong>Sold by the tray</strong><br><span class="muted">The plant count is on each variety.</span></p>
  <p><strong>Bulk by phone</strong><br><a href="tel:+919011975959">9011975959</a></p>
</section>
<section class="section">
  <div class="wrap">
    <h2>Best sellers</h2>
    <div class="rail">
      <?php foreach ($bestsellers as $product) { product_card($product); } ?>
    </div>
  </div>
</section>
<section class="section">
  <div class="wrap">
    <h2>Shop by category</h2>
    <div class="grid">
      <?php foreach ($categories as $category): ?>
      <a class="card" href="/shop?category=<?= e($category['slug']) ?>">
        <img src="<?= e((string) ($category['image_url'] ?: '/brand/seedling.webp')) ?>" alt="" width="800" height="600">
        <div class="card-body"><h3><?= e($category['name']) ?></h3></div>
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
      <p><a href="/shop?use=beds">Flower beds</a></p>
      <p><a href="/shop?use=pots">Pots</a></p>
      <p><a href="/shop?use=landscaping">Landscaping</a></p>
      <p><a href="/shop?use=garlands">Garlands</a></p>
      <p><a href="/shop?use=borders">Borders</a></p>
    </div>
  </div>
</section>
<section class="section">
  <div class="wrap split">
    <img src="/brand/nursery-fields.webp" alt="Fields beside the Precision Agritech nursery at Theur" width="1600" height="1200">
    <div>
      <p class="kicker">Theur, Pune</p>
      <h2>Grown in our nursery</h2>
      <p class="muted">Precision Agritech grows flower seedlings at Survey No. 44/2, Theur Naygaon Road. These photographs are from the nursery.</p>
      <a class="btn" href="/nursery">Visit the nursery page</a>
    </div>
  </div>
</section>
<section class="section">
  <div class="wrap narrow">
    <h2>Wholesale and bulk</h2>
    <p>Farm and landscaping lots are confirmed by phone. A form does not reserve trays.</p>
    <a class="btn" href="/wholesale">Send a wholesale request</a>
    <p><a href="tel:+919011975959">9011975959</a></p>
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
  <div class="wrap">
    <h2>Ready to book trays?</h2>
    <a class="btn" href="/shop">Shop all seedlings</a>
  </div>
</section>
<?php render_footer(); ?>
