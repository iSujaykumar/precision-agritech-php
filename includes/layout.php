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

function image_srcset(string $url): string
{
    $url = product_image($url);
    $path = parse_url($url, PHP_URL_PATH) ?: $url;
    $root = dirname(__DIR__);
    $set = [];
    if (preg_match('#^/products/([a-z0-9-]+)\.webp$#', $path, $m)) {
        foreach ([400, 800, 1200] as $width) {
            $rel = '/products/sm/' . $m[1] . '-' . $width . '.webp';
            if (is_file($root . $rel)) {
                $set[] = $rel . ' ' . $width . 'w';
            }
        }
    } elseif (preg_match('#^/uploads/products/([a-z0-9]+)\.webp$#', $path, $m)) {
        foreach ([400, 800, 1200] as $width) {
            $rel = '/uploads/products/' . $m[1] . '-' . $width . '.webp';
            if (is_file($root . $rel)) {
                $set[] = $rel . ' ' . $width . 'w';
            }
        }
    }
    return implode(', ', $set);
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
<html lang="<?= e(shop_lang()) ?>">
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
<?php if (shop_paused()): ?>
<p class="announce">Orders are paused. You can still look through the trays.</p>
<?php endif; ?>
<header class="nav">
  <a class="logo" href="/">Precision Agritech</a>
  <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav">Menu</button>
  <nav id="site-nav">
    <a href="/shop"<?= nav_here('/shop') ?>><?= e(ui_label('shop')) ?></a>
    <a href="/nursery"<?= nav_here('/nursery') ?>>Our nursery</a>
    <a href="/wholesale"<?= nav_here('/wholesale') ?>>Wholesale</a>
    <a href="/price-list">Price list</a>
    <?php if (calc_menu_on()): ?>
      <button class="linkish nav-calc" type="button" data-calc-open aria-expanded="false" aria-controls="calc-panel"><?= e(calc_label()) ?></button>
    <?php endif; ?>
    <?php if ($user): ?>
      <a href="/account"<?= nav_here('/account') ?>><?= e(ui_label('account')) ?></a>
      <form method="post" action="/logout">
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="/">
        <button class="linkish" type="submit"><?= e(ui_label('sign_out')) ?></button>
      </form>
    <?php else: ?>
      <a href="/login"<?= nav_here('/login') ?>><?= e(ui_label('sign_in')) ?></a>
    <?php endif; ?>
    <a class="nav-cart" href="/cart"<?= nav_here('/cart') ?>><?= e(ui_label('cart')) ?> (<?= (int) $count ?>)</a>
    <a href="/lang?set=<?= shop_lang() === 'mr' ? 'en' : 'mr' ?>"><?= e(ui_label('lang')) ?></a>
  </nav>
</header>
<?php endif; ?>
<main id="content">
<?php
}

function admin_menu(): void
{
    $path = rtrim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/') ?: '/';
    $here = desk_path_group($path);
    $groups = desk_groups();
    $tabs = desk_tab_sets();
    echo '<nav class="admin-nav" id="admin-nav" aria-label="Desk">';
    echo '<form class="desk-finder" method="get" action="/admin/finder" role="search">';
    echo '<label class="finder-label">Where do I change this? <input name="q" type="search" placeholder="price, stock, offer" data-finder autocomplete="off"></label>';
    echo '<div class="finder-drop" data-finder-drop hidden></div></form>';
    echo '<script type="application/json" id="desk-finder-data">' . json_encode(finder_items(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>';
    foreach ($groups as $id => $group) {
        $on = $here === $id;
        $count = (int) $group['count'];
        $badge = $count > 0 ? ' <span class="count-badge">' . $count . '</span>' : '';
        $child = $tabs[$id] ?? [];
        if ($id === 'settings') {
            $child = array_merge($tabs['settings'], $tabs['settings_more']);
        }
        echo '<details class="admin-group"' . ($on ? ' open' : '') . '>';
        echo '<summary><a href="' . e($group['href']) . '"' . ($on ? ' aria-current="page"' : '') . '>' . e($group['label']) . $badge . '</a></summary>';
        if ($child) {
            echo '<div class="admin-sub">';
            foreach ($child as $label => $href) {
                echo '<a href="' . e($href) . '">' . e($label) . '</a>';
            }
            echo '</div>';
        }
        echo '</details>';
    }
    echo '</nav>';
}

function admin_tabs(string $group): void
{
    $sets = desk_tab_sets();
    if ($group === 'today' || $group === '') {
        return;
    }
    $links = $sets[$group] ?? [];
    $more = [];
    if ($group === 'settings') {
        $more = $sets['settings_more'] ?? [];
    }
    if (!$links && !$more) {
        return;
    }
    $current = admin_request_href();
    echo '<nav class="subnav" aria-label="Section">';
    foreach ($links as $label => $href) {
        $on = $href === $current || ($href === '/admin/banks' && $current === '/admin/payments');
        echo '<a href="' . e($href) . '"' . ($on ? ' aria-current="page"' : '') . '>' . e($label) . '</a>';
    }
    if ($more) {
        $moreOn = false;
        foreach ($more as $href) {
            if ($href === $current) {
                $moreOn = true;
            }
        }
        echo '<details class="subnav-more"' . ($moreOn ? ' open' : '') . '><summary>More</summary><div>';
        foreach ($more as $label => $href) {
            $on = $href === $current;
            echo '<a href="' . e($href) . '"' . ($on ? ' aria-current="page"' : '') . '>' . e($label) . '</a>';
        }
        echo '</div></details>';
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
      <?php if ($address !== ''): ?><p><?= e($address) ?></p><?php endif; ?>
      <?php if ($hours !== ''): ?><p><?= e($hours) ?></p><?php endif; ?>
      <?php if ($phone !== ''): ?><p><?= shop_phone_links() ?></p><?php endif; ?>
      <?php if ($wa !== ''): ?><p><a href="<?= e($wa) ?>" target="_blank" rel="noopener">Chat on WhatsApp</a></p><?php endif; ?>
      <?php if ($email !== ''): ?><p><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></p><?php endif; ?>
      <?php if ($gstin !== ''): ?><p>GSTIN <?= e($gstin) ?></p><?php endif; ?>
      <?php if ($cin !== ''): ?><p>CIN <?= e($cin) ?></p><?php endif; ?>
      <?php if ($map !== ''): ?><p><a href="<?= e($map) ?>" target="_blank" rel="noopener">Map</a></p><?php endif; ?>
    </div>
    <div>
      <p class="footer-title">Shop</p>
      <a href="/shop">Shop</a>
      <a href="/shipping">Shipping</a>
      <a href="/returns">Returns</a>
      <a href="/cancellation">Cancellation</a>
      <a href="/faq">FAQ</a>
      <?php if (shop_setting('overgrown_nav', '0') === '1'): ?><a href="/overgrown">Overgrown seedlings</a><?php endif; ?>
      <?php if (shop_setting('seasonal_list_public', '1') === '1'): ?><a href="/price-list">Seasonal price list</a><?php endif; ?>
      <?php if (can_see_farmer()): ?><a href="/farmer-prices">Farmer rates</a><?php endif; ?>
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
  <div class="wrap footer-copy"><p>© <?= date('Y') ?> <?= e($legal) ?></p></div>
</footer>
<?php if ($wa !== ''): ?>
<a class="wa-float" href="<?= e($wa) ?>" target="_blank" rel="noopener" aria-label="Chat with our sales team on WhatsApp">
  <svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true"><path fill="#fff" d="M20.5 3.5A11 11 0 0 0 2.1 16.8L1 22.3l5.6-1.1A11 11 0 0 0 20.5 3.5zM12 20.2a8.2 8.2 0 0 1-4.2-1.1l-.3-.2-3.3.7.7-3.2-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.4-.7-1.6-.8s-.4-.1-.5.1-.6.8-.7.9-.3.2-.5.1a6.7 6.7 0 0 1-2-1.2 7.4 7.4 0 0 1-1.4-1.7c-.1-.2 0-.4.1-.5l.4-.4.2-.3a.5.5 0 0 0 0-.5c0-.1-.5-1.3-.7-1.7s-.4-.4-.5-.4h-.5a1 1 0 0 0-.7.3 2.9 2.9 0 0 0-.9 2.2 5 5 0 0 0 1.1 2.7 11.4 11.4 0 0 0 4.3 3.8 14 14 0 0 0 1.4.5 3.4 3.4 0 0 0 1.6.1 2.6 2.6 0 0 0 1.7-1.2 2.1 2.1 0 0 0 .2-1.2c-.1-.1-.2-.1-.4-.2z"/></svg>
</a>
<?php endif; ?>
<?php
$calcPath = rtrim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/') ?: '/';
if ($calcPath !== '/calculator') {
    render_calc_panel('dialog');
}
$mobileWa = wa_href('Hello Precision Agritech. I want to ask about seedlings.');
$barCount = 0;
try {
    $barCount = cart_count();
} catch (Throwable $err) {
    $barCount = 0;
}
?>
<nav class="mobile-bar" aria-label="Quick actions">
  <?php if (calc_menu_on()): ?><button class="btn" type="button" data-calc-open><?= e(calc_label()) ?></button><?php endif; ?>
  <a class="btn" href="/cart"><?= e(ui_label('cart')) ?> (<?= (int) $barCount ?>)</a>
  <?php if ($mobileWa !== ''): ?><a class="btn" href="<?= e($mobileWa) ?>" target="_blank" rel="noopener"><?= e(ui_label('whatsapp')) ?></a><?php endif; ?>
</nav>
<script src="/assets/js/nav.js?v=<?= (int) @filemtime(dirname(__DIR__) . '/assets/js/nav.js') ?>"></script>
<script src="/assets/js/rail.js?v=<?= (int) @filemtime(dirname(__DIR__) . '/assets/js/rail.js') ?>"></script>
<script src="/assets/js/cart.js?v=<?= (int) @filemtime(dirname(__DIR__) . '/assets/js/cart.js') ?>"></script>
<script src="/assets/js/pay.js?v=<?= (int) @filemtime(dirname(__DIR__) . '/assets/js/pay.js') ?>"></script>
<script src="/assets/js/signin.js?v=<?= (int) @filemtime(dirname(__DIR__) . '/assets/js/signin.js') ?>"></script>
<script src="/assets/js/calc.js?v=<?= (int) @filemtime(dirname(__DIR__) . '/assets/js/calc.js') ?>"></script>
<?php elseif ($chrome === 'admin'): ?>
<script src="/assets/js/admin.js"></script>
<?php endif; ?>
</body>
</html>
<?php
}

function render_product_rail(string $id, string $heading, array $products, string $empty, string $more = '/shop'): void
{
    ?>
<section class="section" id="<?= e($id) ?>">
  <div class="wrap">
    <div class="head">
      <h2><?= e($heading) ?></h2>
      <a href="<?= e($more) ?>"><?= $id === 'new-arrivals' ? 'View all new arrivals' : 'Shop all seedlings' ?></a>
    </div>
    <?php if (!$products): ?>
      <p class="rail-empty"><?= e($empty) ?></p>
      <p><a class="btn" href="/shop">Shop seedlings</a></p>
    <?php else: ?>
    <div class="rail-wrap">
      <button class="rail-btn rail-prev" type="button" aria-label="Previous">&#8249;</button>
      <div class="rail" tabindex="0">
        <?php foreach ($products as $product) { product_card($product); } ?>
      </div>
      <button class="rail-btn rail-next" type="button" aria-label="Next">&#8250;</button>
    </div>
    <?php endif; ?>
  </div>
</section>
    <?php
}

function product_card(array $product, bool $eager = false): void
{
    $stock = available_trays($product);
    $href = '/product/' . rawurlencode((string) $product['slug']);
    $image = product_image((string) ($product['image_url'] ?? ''));
    $srcset = image_srcset($image);
    $badge = offer_badge($product);
    $isNew = product_is_new($product);
    $best = (int) ($product['bestseller'] ?? 0) === 1;
    $variety = (string) ($product['variety'] ?? $product['scientific_name'] ?? '');
    $shown = selling_price($product);
    $lowAt = low_stock_limit();
    $banner = discount_banner_text();
    $wa = wa_order_href($product, 1);
    ?>
<article class="card" data-cat="<?= e((string) ($product['category_slug'] ?? '')) ?>">
  <a class="card-photo" href="<?= e($href) ?>">
    <img src="<?= e($image) ?>"<?php if ($srcset !== ''): ?> srcset="<?= e($srcset) ?>" sizes="(max-width:640px) 50vw, 25vw"<?php endif; ?> alt="<?= e($product['name']) ?> seedling tray" width="800" height="600" decoding="async"<?= $eager ? '' : ' loading="lazy"' ?>>
    <?php if ($badge !== ''): ?><span class="sale-badge"><?= e($badge) ?></span><?php endif; ?>
    <?php if ((int) ($product['is_overgrown'] ?? 0) === 1): ?><span class="new-badge">Overgrown / as-is</span><?php endif; ?>
    <?php if ($isNew): ?><span class="new-badge">New</span><?php endif; ?>
    <?php if ($best): ?><span class="new-badge">Best seller</span><?php endif; ?>
  </a>
  <div class="card-body">
    <h3><a href="<?= e($href) ?>"><?= e($product['name']) ?></a></h3>
    <p class="muted"><?= e($variety) ?></p>
    <p class="price"><?= $shown['blocked'] ? 'Price on request' : e(price_pair_text($shown)) ?></p>
    <?php if ($banner !== ''): ?><p class="muted"><?= e($banner) ?></p><?php endif; ?>
    <?php $rating = product_rating((int) ($product['id'] ?? 0)); if ($rating): ?>
      <p class="rating-line"><?= stars_markup($rating['avg'], $rating['n']) ?> <span class="muted"><?= e(number_format($rating['avg'], 1)) ?> · <?= (int) $rating['n'] ?></span></p>
    <?php endif; ?>
    <?php if ($stock <= 0): ?>
      <p class="stock-out">Out of stock</p>
    <?php elseif ($stock <= $lowAt): ?>
      <p class="stock-low">Only <?= (int) $stock ?> trays left</p>
    <?php else: ?>
      <p class="muted"><?= (int) $stock ?> trays available</p>
    <?php endif; ?>
    <?php if ($stock > 0): ?>
    <form class="card-add" method="post" action="/cart">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="slug" value="<?= e((string) $product['slug']) ?>">
      <input type="hidden" name="qty" value="1">
      <button class="btn" type="submit"><?= e(ui_label('add')) ?></button>
    </form>
    <?php else: ?>
    <p class="card-add"><a class="btn" href="<?= e($href) ?>">View</a></p>
    <?php endif; ?>
    <?php if ($wa !== ''): ?><p><a href="<?= e($wa) ?>" target="_blank" rel="noopener"><?= e(ui_label('whatsapp')) ?></a></p><?php endif; ?>
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
        echo '<p>Please call 8080370152, 8421577787 or 8421577725 and we will help.</p>';
        echo '</div></section>';
        render_footer();
        exit;
    }
}
