<?php
declare(strict_types=1);

function nav_here(string $href): string
{
    $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $path = rtrim($path, '/') ?: '/';
    $href = rtrim($href, '/') ?: '/';
    if ($href === '/') {
        return $path === '/' ? ' aria-current="page"' : '';
    }
    return ($path === $href || str_starts_with($path, $href . '/')) ? ' aria-current="page"' : '';
}

function product_image(?string $url): string
{
    $fallback = '/brand/seedling.webp';
    $url = trim((string) $url);
    if ($url === '') {
        return $fallback;
    }
    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }
    if ($url[0] !== '/') {
        $url = '/' . $url;
    }
    $path = dirname(__DIR__) . $url;
    return is_file($path) ? $url : $fallback;
}

function render_header(string $title, string $description = '', string $chrome = 'shop'): void
{
    $GLOBALS['page_chrome'] = $chrome;
    $user = null;
    try {
        $user = current_user();
    } catch (Throwable $err) {
        $user = null;
    }
    $count = 0;
    try {
        $count = cart_count();
    } catch (Throwable $err) {
        $count = 0;
    }
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?></title>
  <?php if ($description !== ''): ?><meta name="description" content="<?= e($description) ?>"><?php endif; ?>
  <?php if ($chrome !== 'shop'): ?><meta name="robots" content="noindex"><?php endif; ?>
  <link rel="icon" href="/favicon.svg">
  <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body>
<?php if ($chrome === 'admin'): ?>
<header class="admin-bar">
  <a class="logo" href="/admin">Nursery desk</a>
  <p class="admin-who"><?= e((string) ($user['email'] ?? '')) ?></p>
  <form method="post" action="/logout">
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="/admin/login">
    <button class="btn" type="submit">Sign out</button>
  </form>
</header>
<?php admin_menu(); ?>
<?php elseif ($chrome === 'staff'): ?>
<header class="admin-bar">
  <a class="logo" href="/admin/login">Precision Agritech</a>
  <p class="admin-who">Staff only</p>
</header>
<?php else: ?>
<header class="nav">
  <a class="logo" href="/">Precision Agritech</a>
  <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav">Menu</button>
  <nav id="site-nav">
    <a href="/shop"<?= nav_here('/shop') ?>>Shop</a>
    <a href="/nursery"<?= nav_here('/nursery') ?>>Our nursery</a>
    <a href="/wholesale"<?= nav_here('/wholesale') ?>>Wholesale</a>
    <?php if ($user): ?>
      <a href="/account"<?= nav_here('/account') ?>>Account</a>
      <form method="post" action="/logout">
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="/">
        <button class="linkish" type="submit">Sign out</button>
      </form>
    <?php else: ?>
      <a href="/login"<?= nav_here('/login') ?>>Sign in</a>
    <?php endif; ?>
    <a class="nav-cart" href="/cart"<?= nav_here('/cart') ?>>Cart (<?= (int) $count ?>)</a>
  </nav>
</header>
<?php endif; ?>
<main>
<?php
}

function admin_menu(): void
{
    $links = [
        'Desk' => '/admin',
        'Products' => '/admin/products',
        'Categories' => '/admin/categories',
        'Stock' => '/admin/inventory',
        'Stock history' => '/admin/inventory-history',
        'Orders' => '/admin/orders',
        'Customers' => '/admin/customers',
        'Reviews' => '/admin/reviews',
        'Coupons' => '/admin/coupons',
        'Messages' => '/admin/messages',
        'Wholesale' => '/admin/wholesale',
        'Settings' => '/admin/settings',
        'Staff' => '/admin/staff',
        'Audit' => '/admin/audit-log',
        'Password' => '/change-password',
    ];
    $path = rtrim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/') ?: '/';
    $alias = [
        '/admin/product-edit' => '/admin/products',
        '/admin/order-view' => '/admin/orders',
        '/admin/customer-view' => '/admin/customers',
    ];
    $here = $alias[$path] ?? $path;
    echo '<nav class="admin-nav" aria-label="Desk">';
    foreach ($links as $label => $href) {
        $on = rtrim($href, '/') === $here;
        echo '<a href="' . e($href) . '"' . ($on ? ' aria-current="page"' : '') . '>' . e($label) . '</a>';
    }
    echo '</nav>';
}

function render_footer(): void
{
    $chrome = (string) ($GLOBALS['page_chrome'] ?? 'shop');
    ?>
</main>
<?php if ($chrome === 'shop'): ?>
<footer class="footer">
  <div class="wrap footer-grid">
    <div>
      <strong>Precision Agritech</strong>
      <p>Survey No. 44/2, Theur Naygaon Road, Gaikwadvasti, Pune 412110.</p>
      <p><a href="tel:+919011975959">9011975959</a></p>
      <p><a href="mailto:info@precisionagritech.in">info@precisionagritech.in</a></p>
    </div>
    <div>
      <p class="footer-title">Shop</p>
      <a href="/shop">Shop</a>
      <a href="/shipping">Shipping</a>
      <a href="/returns">Returns</a>
      <a href="/faq">FAQ</a>
      <a href="/contact">Contact</a>
    </div>
    <div>
      <p class="footer-title">Company</p>
      <a href="/about">About</a>
      <a href="/wholesale">Wholesale</a>
      <a href="/privacy">Privacy</a>
      <a href="/terms">Terms</a>
    </div>
  </div>
  <div class="wrap footer-copy"><p>(c) Precision Agritech</p></div>
</footer>
<script src="/assets/js/nav.js"></script>
<script src="/assets/js/rail.js"></script>
<?php endif; ?>
</body>
</html>
<?php
}

function product_card(array $product, bool $eager = false): void
{
    $stock = available_trays($product);
    $href = '/product/' . rawurlencode((string) $product['slug']);
    $image = product_image((string) ($product['image_url'] ?? ''));
    $onSale = $product['compare_at_inr'] !== null && (int) $product['compare_at_inr'] > (int) $product['price_inr'];
    $variety = (string) ($product['variety'] ?? $product['scientific_name'] ?? '');
    ?>
<article class="card">
  <a class="card-photo" href="<?= e($href) ?>">
    <img src="<?= e($image) ?>" alt="<?= e($product['name']) ?> seedling tray" width="800" height="600" decoding="async"<?= $eager ? '' : ' loading="lazy"' ?>>
    <?php if ($onSale): ?><span class="sale-badge">Sale</span><?php endif; ?>
  </a>
  <div class="card-body">
    <h3><a href="<?= e($href) ?>"><?= e($product['name']) ?></a></h3>
    <p class="muted"><?= e($variety) ?></p>
    <p class="price"><?= inr((int) $product['price_inr']) ?> <span class="muted">/ tray</span><?php if ($onSale): ?><span class="compare"><?= inr((int) $product['compare_at_inr']) ?></span><?php endif; ?></p>
    <?php if ($stock <= 0): ?>
      <p class="stock-out">Out of stock</p>
    <?php elseif ($stock <= 5): ?>
      <p class="stock-low">Only <?= (int) $stock ?> left</p>
    <?php else: ?>
      <p class="muted"><?= (int) $stock ?> trays available</p>
    <?php endif; ?>
    <?php if ($stock > 0): ?>
    <form class="card-add" method="post" action="/cart">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="slug" value="<?= e((string) $product['slug']) ?>">
      <input type="hidden" name="qty" value="1">
      <button class="btn" type="submit">Add to cart</button>
    </form>
    <?php else: ?>
    <p class="card-add"><a class="btn" href="<?= e($href) ?>">View</a></p>
    <?php endif; ?>
  </div>
</article>
<?php
}

function connect_or_explain(): void
{
    try {
        db();
    } catch (Throwable $err) {
        error_log($err::class . ' ' . $err->getMessage());
        $connection = $err instanceof PDOException || str_contains($err->getMessage(), 'not configured');
        render_header($connection ? 'Database setup | Precision Agritech' : 'Unavailable | Precision Agritech');
        echo '<section class="section"><div class="wrap narrow">';
        if ($connection) {
            echo '<h1>Connect the database</h1>';
            echo '<p>Import <code>database/schema.sql</code> into an empty database. If this database already exists, import <code>database/upgrade.sql</code> once. Then fill <code>config/config.local.php</code> from <code>config/config.example.php</code>.</p>';
        } else {
            echo '<h1>The shop could not be opened</h1>';
            echo '<p>The nursery has the details. Nothing on this page is a database password or a file path.</p>';
        }
        echo '</div></section>';
        render_footer();
        exit;
    }
}
