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
    $jsonLd = (string) ($GLOBALS['json_ld'] ?? '');
    if ($jsonLd !== '') {
        $hash = base64_encode(hash('sha256', $jsonLd, true));
        send_security_headers($hash);
    }
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
  <?php
    global $config;
    $canonicalBase = rtrim((string) ($config['site_url'] ?? ''), '/');
    $canonicalPath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $ogImage = (string) ($GLOBALS['og_image'] ?? '/brand/greenhouse-hero.webp');
    if ($canonicalBase !== '' && $chrome === 'shop'): ?>
  <link rel="canonical" href="<?= e($canonicalBase . $canonicalPath) ?>">
  <meta property="og:title" content="<?= e($title) ?>">
  <?php if ($description !== ''): ?><meta property="og:description" content="<?= e($description) ?>"><?php endif; ?>
  <meta property="og:image" content="<?= e($canonicalBase . $ogImage) ?>">
  <meta property="og:url" content="<?= e($canonicalBase . $canonicalPath) ?>">
  <?php endif; ?>
  <?php if ($jsonLd !== ''): ?><script type="application/ld+json"><?= $jsonLd ?></script><?php endif; ?>
  <link rel="icon" href="/favicon.svg">
  <link rel="stylesheet" href="/assets/css/site.css?v=<?= (int) @filemtime(dirname(__DIR__) . '/assets/css/site.css') ?>">
</head>
<body class="<?= $chrome === 'shop' ? 'shop' : 'desk' ?>">
<a class="skip" href="#content">Skip to content</a>
<?php if ($chrome === 'admin'): ?>
<header class="admin-bar">
  <a class="logo" href="/admin">Nursery desk</a>
  <button class="nav-toggle admin-toggle" type="button" aria-expanded="false" aria-controls="admin-nav">Menu</button>
  <details class="staff-menu">
    <summary><?= e((string) ($user['name'] ?? $user['email'] ?? 'Staff')) ?></summary>
    <a href="/change-password">Password</a>
    <form method="post" action="/logout">
      <?= csrf_field() ?>
      <input type="hidden" name="next" value="/admin/login">
      <button class="linkish" type="submit">Sign out</button>
    </form>
  </details>
</header>
<?php admin_menu(); ?>
<?php elseif ($chrome === 'staff'): ?>
<header class="admin-bar">
  <a class="logo" href="/admin/login">Precision Agritech</a>
  <p class="admin-who">Staff only</p>
</header>
<?php else: ?>
<?php if (shop_setting('announcement_on', '0') === '1' && trim(shop_setting('announcement', '')) !== ''): ?>
<p class="announce"><?= e(shop_setting('announcement', '')) ?></p>
<?php endif; ?>
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
<main id="content">
<?php
}

function admin_menu(): void
{
    $counts = desk_counts();
    $links = [
        'Desk' => ['/admin', 0],
        'Orders' => ['/admin/orders', $counts['orders']],
        'Products' => ['/admin/products', 0],
        'Customers' => ['/admin/customers', 0],
        'Reviews' => ['/admin/reviews', $counts['reviews']],
        'Coupons' => ['/admin/coupons', 0],
        'Enquiries' => ['/admin/messages', $counts['enquiries']],
        'Settings' => ['/admin/settings', 0],
    ];
    $path = rtrim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/') ?: '/';
    $alias = [
        '/admin/product-edit' => '/admin/products',
        '/admin/categories' => '/admin/products',
        '/admin/inventory' => '/admin/products',
        '/admin/inventory-history' => '/admin/products',
        '/admin/order-view' => '/admin/orders',
        '/admin/customer-view' => '/admin/customers',
        '/admin/wholesale' => '/admin/messages',
        '/admin/staff' => '/admin/settings',
        '/admin/audit-log' => '/admin/settings',
        '/admin/payments' => '/admin/settings',
        '/admin/delivery' => '/admin/settings',
        '/admin/deliveries' => '/admin/orders',
        '/admin/claims' => '/admin/orders',
        '/admin/health' => '/admin/settings',
        '/admin/cod-block' => '/admin/settings',
    ];
    $here = $alias[$path] ?? $path;
    echo '<nav class="admin-nav" id="admin-nav" aria-label="Desk">';
    foreach ($links as $label => [$href, $count]) {
        $on = rtrim($href, '/') === $here;
        echo '<a href="' . e($href) . '"' . ($on ? ' aria-current="page"' : '') . '>' . e($label);
        if ($count > 0) {
            echo ' <span class="count-badge">' . (int) $count . '</span>';
        }
        echo '</a>';
    }
    echo '</nav>';
}

function admin_tabs(string $group): void
{
    $sets = [
        'products' => ['Products' => '/admin/products', 'Categories' => '/admin/categories', 'Stock' => '/admin/inventory'],
        'enquiries' => ['Messages' => '/admin/messages', 'Wholesale' => '/admin/wholesale'],
        'settings' => ['Shop' => '/admin/settings', 'Payments' => '/admin/payments', 'Delivery' => '/admin/delivery', 'Staff' => '/admin/staff', 'Health' => '/admin/health', 'Activity log' => '/admin/audit-log'],
    ];
    if (!isset($sets[$group])) {
        return;
    }
    $path = rtrim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/') ?: '/';
    if ($path === '/admin/inventory-history') {
        $path = '/admin/inventory';
    }
    if ($path === '/admin/product-edit') {
        $path = '/admin/products';
    }
    echo '<nav class="subnav" aria-label="Section">';
    foreach ($sets[$group] as $label => $href) {
        $on = rtrim($href, '/') === $path;
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
<?php
$phone = shop_phone_digits();
$tel = strlen($phone) === 10 ? '91' . $phone : $phone;
$wa = wa_href('Hello Precision Agritech, I want to know about seedling trays.');
$hours = shop_setting('opening_hours', 'Monday to Saturday, 9:00 to 6:00');
$address = shop_setting('shop_address', 'Survey No. 44/2, Theur Naygaon Road, Gaikwadvasti, Pune 412110');
$email = shop_setting('shop_email', 'info@precisionagritech.in');
$map = shop_setting('map_url', 'https://www.google.com/maps/search/?api=1&query=Survey+No.+44/2+Theur+Naygaon+Road+Gaikwadvasti+Pune+412110');
$legal = shop_setting('seller_legal_name', 'Precision Agritech Private Limited');
$gstin = trim(shop_setting('gstin', ''));
$cin = trim(shop_setting('cin', ''));
?>
<footer class="footer">
  <div class="wrap footer-grid">
    <div>
      <strong><?= e($legal) ?></strong>
      <p><?= e($address) ?></p>
      <p><?= e($hours) ?></p>
      <p><a href="tel:+<?= e($tel) ?>"><?= e($phone) ?></a>
        <?php if ($wa !== ''): ?> · <a href="<?= e($wa) ?>" target="_blank" rel="noopener">Chat on WhatsApp</a><?php endif; ?>
      </p>
      <p><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></p>
      <?php if ($gstin !== ''): ?><p>GSTIN <?= e($gstin) ?></p><?php endif; ?>
      <?php if ($cin !== ''): ?><p>CIN <?= e($cin) ?></p><?php endif; ?>
      <p><a href="<?= e($map) ?>" target="_blank" rel="noopener">Map</a></p>
    </div>
    <div>
      <p class="footer-title">Shop</p>
      <a href="/shop">Shop</a>
      <a href="/shipping">Shipping</a>
      <a href="/returns">Returns</a>
      <a href="/cancellation">Cancellation</a>
      <a href="/faq">FAQ</a>
      <a href="/contact">Contact</a>
      <a href="/reviews">Reviews</a>
    </div>
    <div>
      <p class="footer-title">Company</p>
      <a href="/about">About</a>
      <a href="/nursery">Our nursery</a>
      <a href="/wholesale">Wholesale</a>
      <a href="/privacy">Privacy</a>
      <a href="/terms">Terms</a>
      <a href="/contact#grievance">Grievance officer</a>
    </div>
  </div>
  <div class="wrap footer-copy"><p>© <?= e($legal) ?></p></div>
</footer>
<?php if ($wa !== ''): ?>
<a class="wa-float" href="<?= e($wa) ?>" target="_blank" rel="noopener" aria-label="Chat with our sales team on WhatsApp">
  <svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true"><path fill="#fff" d="M20.5 3.5A11 11 0 0 0 2.1 16.8L1 22.3l5.6-1.1A11 11 0 0 0 20.5 3.5zM12 20.2a8.2 8.2 0 0 1-4.2-1.1l-.3-.2-3.3.7.7-3.2-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.4-.7-1.6-.8s-.4-.1-.5.1-.6.8-.7.9-.3.2-.5.1a6.7 6.7 0 0 1-2-1.2 7.4 7.4 0 0 1-1.4-1.7c-.1-.2 0-.4.1-.5l.4-.4.2-.3a.5.5 0 0 0 0-.5c0-.1-.5-1.3-.7-1.7s-.4-.4-.5-.4h-.5a1 1 0 0 0-.7.3 2.9 2.9 0 0 0-.9 2.2 5 5 0 0 0 1.1 2.7 11.4 11.4 0 0 0 4.3 3.8 14 14 0 0 0 1.4.5 3.4 3.4 0 0 0 1.6.1 2.6 2.6 0 0 0 1.7-1.2 2.1 2.1 0 0 0 .2-1.2c-.1-.1-.2-.1-.4-.2z"/></svg>
</a>
<?php endif; ?>
<script src="/assets/js/nav.js?v=<?= (int) @filemtime(dirname(__DIR__) . '/assets/js/nav.js') ?>"></script>
<script src="/assets/js/rail.js?v=<?= (int) @filemtime(dirname(__DIR__) . '/assets/js/rail.js') ?>"></script>
<script src="/assets/js/cart.js?v=<?= (int) @filemtime(dirname(__DIR__) . '/assets/js/cart.js') ?>"></script>
<script src="/assets/js/pay.js?v=<?= (int) @filemtime(dirname(__DIR__) . '/assets/js/pay.js') ?>"></script>
<script src="/assets/js/signin.js?v=<?= (int) @filemtime(dirname(__DIR__) . '/assets/js/signin.js') ?>"></script>
<?php elseif ($chrome === 'admin'): ?>
<script src="/assets/js/admin.js"></script>
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
    <?php $rating = product_rating((int) ($product['id'] ?? 0)); if ($rating): ?>
      <p class="rating-line"><?= stars_markup($rating['avg'], $rating['n']) ?> <span class="muted"><?= e(number_format($rating['avg'], 1)) ?> · <?= (int) $rating['n'] ?></span></p>
    <?php endif; ?>
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
        render_header('The shop is unavailable | Precision Agritech');
        echo '<section class="section"><div class="wrap narrow">';
        echo '<h1>The shop is not ready yet</h1>';
        echo '<p>Please call 9011975959 and we will help.</p>';
        echo '</div></section>';
        render_footer();
        exit;
    }
}
