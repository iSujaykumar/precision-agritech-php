<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();

$q = trim((string) ($_GET['q'] ?? ''));
$category = trim((string) ($_GET['category'] ?? ''));
$use = trim((string) ($_GET['use'] ?? ''));
$sort = (string) ($_GET['sort'] ?? 'name');
$sql = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p JOIN categories c ON c.id = p.category_id WHERE p.active = 1';
$params = [];
if ($q !== '') {
    $sql .= ' AND (p.name LIKE ? OR p.scientific_name LIKE ? OR p.variety LIKE ? OR p.uses LIKE ?)';
    $like = '%' . str_replace(['%', '_'], '', $q) . '%';
    $params = array_merge($params, [$like, $like, $like, $like]);
}
if ($category !== '') {
    $sql .= ' AND c.slug = ?';
    $params[] = $category;
}
if ($use !== '') {
    $sql .= ' AND p.uses LIKE ?';
    $params[] = '%' . str_replace(['%', '_'], '', $use) . '%';
}
$sql .= $sort === 'price' ? ' ORDER BY p.price_inr, p.name' : ' ORDER BY p.bestseller DESC, p.name';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
$categories = db()->query('SELECT slug, name FROM categories ORDER BY sort_order')->fetchAll();

render_header('Shop seedling trays | Precision Agritech', 'Flower seedling trays from the Theur nursery.');
?>
<section class="section">
  <div class="wrap">
    <h1>Shop</h1>
    <form method="get" action="/shop">
      <input name="q" value="<?= e($q) ?>" placeholder="Search marigold, petunia, shade...">
    </form>
    <div class="filters">
      <a href="/shop">All</a>
      <?php foreach ($categories as $cat): ?>
        <a href="/shop?category=<?= e($cat['slug']) ?>"><?= e($cat['name']) ?></a>
      <?php endforeach; ?>
      <a href="/shop?sort=price">Price</a>
    </div>
    <div class="grid">
      <?php foreach ($products as $product) { product_card($product); } ?>
    </div>
    <?php if (!$products): ?><p>No trays match that search.</p><?php endif; ?>
  </div>
</section>
<?php render_footer(); ?>
