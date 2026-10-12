<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();

$q = trim((string) ($_GET['q'] ?? ''));
$category = trim((string) ($_GET['category'] ?? ''));
$use = trim((string) ($_GET['use'] ?? ''));
$sort = (string) ($_GET['sort'] ?? 'name');
if (!in_array($sort, ['name', 'price', 'price_desc'], true)) {
    $sort = 'name';
}
$flag = (string) ($_GET['flag'] ?? '');
if (!in_array($flag, ['', 'new', 'offer'], true)) {
    $flag = '';
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;
// Shop lists active trays in active categories, including out-of-stock cards.
$where = ' WHERE ' . catalog_public_sql();
$params = [];
if ($q !== '') {
    $where .= ' AND (p.name LIKE ? OR p.scientific_name LIKE ? OR p.variety LIKE ? OR p.uses LIKE ?)';
    $like = '%' . str_replace(['%', '_'], '', $q) . '%';
    $params = array_merge($params, [$like, $like, $like, $like]);
}
if ($category !== '') {
    $where .= ' AND c.slug = ?';
    $params[] = $category;
}
if ($use !== '') {
    $where .= ' AND p.uses LIKE ?';
    $params[] = '%' . str_replace(['%', '_'], '', $use) . '%';
}
if ($flag === 'new') {
    $where .= ' AND p.is_new = 1';
} elseif ($flag === 'offer') {
    $where .= ' AND ' . offer_active_sql();
}
$count = db()->prepare('SELECT COUNT(*) FROM products p JOIN categories c ON c.id = p.category_id' . $where);
$count->execute($params);
$total = (int) $count->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
if ($page > $pages) {
    $page = $pages;
}
$offset = ($page - 1) * $perPage;
$sql = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p JOIN categories c ON c.id = p.category_id' . $where;
$sql .= match ($sort) {
    'price' => ' ORDER BY p.price_inr, p.name, p.id',
    'price_desc' => ' ORDER BY p.price_inr DESC, p.name, p.id',
    default => ' ORDER BY p.bestseller DESC, p.name, p.id',
};
$sql .= ' LIMIT ' . $perPage . ' OFFSET ' . $offset;
$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
$categories = db()->query('SELECT slug, name FROM categories WHERE active = 1 ORDER BY sort_order, name')->fetchAll();

function shop_href(array $extra): string
{
    $query = array_merge($_GET, $extra);
    unset($query['p']);
    foreach ($query as $key => $value) {
        if ($value === '' || $value === null) {
            unset($query[$key]);
        }
    }
    $built = http_build_query($query);
    return '/shop' . ($built !== '' ? '?' . $built : '');
}

render_header('Shop seedling trays | Precision Agritech', 'Flower seedling trays from the Theur nursery.');
?>
<section class="section">
  <div class="wrap">
    <h1>Shop</h1>
    <form method="get" action="/shop">
      <?php if ($category !== ''): ?><input type="hidden" name="category" value="<?= e($category) ?>"><?php endif; ?>
      <?php if ($sort !== 'name'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
      <?php if ($flag !== ''): ?><input type="hidden" name="flag" value="<?= e($flag) ?>"><?php endif; ?>
      <label class="search-label">Search trays
        <input name="q" value="<?= e($q) ?>" placeholder="Search marigold, petunia, shade...">
      </label>
    </form>
    <div class="filters">
      <a href="/shop"<?= $category === '' ? ' aria-current="page"' : '' ?>>All</a>
      <?php foreach ($categories as $cat): ?>
        <a href="/shop?category=<?= e($cat['slug']) ?>"<?= $category === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e($cat['name']) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(shop_href(['sort' => 'name', 'page' => null])) ?>"<?= $sort === 'name' ? ' aria-current="page"' : '' ?>>Name</a>
      <a href="<?= e(shop_href(['sort' => 'price', 'page' => null])) ?>"<?= $sort === 'price' ? ' aria-current="page"' : '' ?>>Price low</a>
      <a href="<?= e(shop_href(['sort' => 'price_desc', 'page' => null])) ?>"<?= $sort === 'price_desc' ? ' aria-current="page"' : '' ?>>Price high</a>
      <a href="<?= e(shop_href(['flag' => $flag === 'new' ? null : 'new', 'page' => null])) ?>"<?= $flag === 'new' ? ' aria-current="page"' : '' ?>>New</a>
      <a href="<?= e(shop_href(['flag' => $flag === 'offer' ? null : 'offer', 'page' => null])) ?>"<?= $flag === 'offer' ? ' aria-current="page"' : '' ?>>Offers</a>
    </div>
    <div class="grid">
      <?php foreach ($products as $index => $product) { product_card($product, $page === 1 && $index < 4); } ?>
    </div>
    <?php if (!$products): ?><p>No trays match that search.</p><?php endif; ?>
    <?php if ($pages > 1): ?>
      <nav class="pager" aria-label="Pages">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
          <?php if ($i === $page): ?><span class="is-current"><?= $i ?></span><?php else: ?><a href="<?= e(shop_href(['page' => $i])) ?>"><?= $i ?></a><?php endif; ?>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  </div>
</section>
<?php render_footer(); ?>
