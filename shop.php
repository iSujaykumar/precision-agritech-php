<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();

$q = trim((string) ($_GET['q'] ?? ''));
$category = trim((string) ($_GET['category'] ?? ''));
$use = trim((string) ($_GET['use'] ?? ''));
$sort = (string) ($_GET['sort'] ?? 'popular');
$stock = (string) ($_GET['stock'] ?? '');
$sql = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p JOIN categories c ON c.id = p.category_id WHERE p.active = 1';
$params = [];
if ($q !== '') {
    $sql .= ' AND (p.name LIKE ? OR p.scientific_name LIKE ? OR p.variety LIKE ? OR p.uses LIKE ? OR p.short_description LIKE ? OR p.use_tags LIKE ?)';
    $like = '%' . str_replace(['%', '_'], '', $q) . '%';
    $params = array_merge($params, [$like, $like, $like, $like, $like, $like]);
}
if ($category !== '') {
    $sql .= ' AND c.slug = ?';
    $params[] = $category;
}
if ($use !== '') {
    $sql .= ' AND CONCAT(",", p.use_tags, ",") LIKE ?';
    $params[] = '%,' . str_replace(['%', '_', ','], '', $use) . ',%';
}
if ($stock === '1') {
    $sql .= ' AND p.stock_qty > p.reserved_qty AND p.availability <> ?';
    $params[] = 'not_in_season';
}
$sql .= match ($sort) {
    'price' => ' ORDER BY (p.stock_qty > p.reserved_qty) DESC, p.price_inr, p.name',
    'price_desc' => ' ORDER BY (p.stock_qty > p.reserved_qty) DESC, p.price_inr DESC, p.name',
    'new' => ' ORDER BY (p.stock_qty > p.reserved_qty) DESC, p.is_new DESC, p.name',
    default => ' ORDER BY (p.stock_qty > p.reserved_qty) DESC, p.bestseller DESC, p.name',
};
$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
$categories = db()->query('SELECT slug, name FROM categories ORDER BY sort_order')->fetchAll();
$keep = static function (array $extra) use ($q, $category, $use, $sort, $stock): string {
    $params = array_filter([
        'q' => $q,
        'category' => $category,
        'use' => $use,
        'sort' => $sort === 'popular' ? '' : $sort,
        'stock' => $stock,
    ], static fn ($value) => $value !== '');
    return '/shop?' . http_build_query(array_merge($params, $extra));
};
render_header('Shop seedling trays | Precision Agritech', 'Flower seedling trays from the Theur nursery.');
?>
<section class="section">
  <div class="wrap">
    <h1>Shop</h1>
    <form method="get" action="/shop" class="search-row">
      <?php if ($category !== ''): ?><input type="hidden" name="category" value="<?= e($category) ?>"><?php endif; ?>
      <?php if ($use !== ''): ?><input type="hidden" name="use" value="<?= e($use) ?>"><?php endif; ?>
      <?php if ($sort !== 'popular'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
      <label>Search trays <input type="search" name="q" value="<?= e($q) ?>"></label>
      <button class="btn" type="submit">Search</button>
    </form>
    <div class="filters">
      <a href="/shop" <?= $category === '' && $use === '' ? 'aria-current="page"' : '' ?>>All</a>
      <?php foreach ($categories as $cat): ?>
        <a href="<?= e($keep(['category' => $cat['slug'], 'use' => ''])) ?>" <?= $category === $cat['slug'] ? 'aria-current="page"' : '' ?>><?= e($cat['name']) ?></a>
      <?php endforeach; ?>
      <a href="<?= e($keep(['stock' => $stock === '1' ? '' : '1'])) ?>" <?= $stock === '1' ? 'aria-current="page"' : '' ?>>In stock only</a>
      <?php if ($q !== '' || $category !== '' || $use !== '' || $stock !== ''): ?><a href="/shop">Clear filters</a><?php endif; ?>
    </div>
    <form method="get" class="search-row">
      <?php foreach (['q' => $q, 'category' => $category, 'use' => $use, 'stock' => $stock] as $key => $value): if ($value !== ''): ?>
        <input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>">
      <?php endif; endforeach; ?>
      <label>Sort
        <select name="sort">
          <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Popular</option>
          <option value="price" <?= $sort === 'price' ? 'selected' : '' ?>>Price low to high</option>
          <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price high to low</option>
          <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>>New</option>
        </select>
      </label>
      <button class="btn" type="submit">Apply</button>
    </form>
    <p><?= count($products) ?> trays found</p>
    <div class="grid">
      <?php foreach ($products as $product) { product_card($product); } ?>
    </div>
    <?php if (!$products): ?><p>No trays match that search. <a href="/shop">Show every tray</a></p><?php endif; ?>
  </div>
</section>
<?php render_footer(); ?>
