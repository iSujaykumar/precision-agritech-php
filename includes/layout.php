<?php
declare(strict_types=1);

function canonical_url(): string
{
    global $config;
    $site = rtrim((string) ($config['site_url'] ?? ''), '/');
    $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if ($path === '/index.php') {
        $path = '/';
    }
    return $site . ($path === '' ? '/' : $path);
}

function render_header(string $title, string $description = '', array $extra = []): void
{
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
    $desc = $description !== '' ? $description : 'Flower seedling trays from Precision Agritech, Theur, Pune.';
    $image = (string) ($extra['image'] ?? '/brand/greenhouse-hero.webp');
    global $config;
    $site = rtrim((string) ($config['site_url'] ?? ''), '/');
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?></title>
  <meta name="description" content="<?= e($desc) ?>">
  <meta name="theme-color" content="#234d38">
  <link rel="canonical" href="<?= e(canonical_url()) ?>">
  <meta property="og:title" content="<?= e($title) ?>">
  <meta property="og:description" content="<?= e($desc) ?>">
  <meta property="og:image" content="<?= e($site . $image) ?>">
  <meta name="twitter:card" content="summary_large_image">
  <link rel="icon" href="/favicon.svg">
  <link rel="preload" href="/assets/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="/assets/css/site.css?v=<?= (int) @filemtime(dirname(__DIR__) . '/assets/css/site.css') ?>">
  <?php if (!empty($extra['json'])): ?>
  <script type="application/ld+json"><?= $extra['json'] ?></script>
  <?php endif; ?>
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<header class="nav">
  <div class="wrap nav-inner">
    <a class="logo" href="/">Precision Agritech</a>
    <a class="cart-link" href="/cart">Cart<?= $count ? ' (' . $count . ')' : '' ?></a>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav">Menu</button>
    <nav id="site-nav" aria-label="Shop">
      <a href="/shop">Shop</a>
      <a href="/nursery">Our nursery</a>
      <a href="/wholesale">Wholesale</a>
      <a href="/account"><?= $user ? 'Account' : 'Sign in' ?></a>
      <a href="/cart">Cart<?= $count ? ' (' . $count . ')' : '' ?></a>
      <?php if ($user): ?>
      <form method="post" action="/logout" class="inline-form">
        <?= csrf_field() ?>
        <button class="linkish" type="submit">Sign out</button>
      </form>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main id="main">
<?php
    render_flash();
}

function render_footer(): void
{
    $phone = setting_safe('contact_phone', '9011975959');
    ?>
</main>
<footer class="footer">
  <div class="wrap footer-grid">
    <div>
      <strong>Precision Agritech</strong>
      <p>Survey No. 44/2, Theur Naygaon Road, Gaikwadvasti, Pune 412110.</p>
      <p><a href="tel:+91<?= e(preg_replace('/\D+/', '', $phone) ?? '') ?>"><?= e($phone) ?></a> · <a href="mailto:info@precisionagritech.in">info@precisionagritech.in</a></p>
      <p class="muted">Nursery hours: 9:00 to 6:00, Monday to Saturday. Have the legal pages checked by a lawyer before you rely on them.</p>
      <?php
      try {
          $wa = whatsapp_link('Hello, I would like to ask about seedling trays.');
      } catch (Throwable $err) {
          $wa = 'https://wa.me/919011975959';
      }
      ?>
      <p><a href="<?= e($wa) ?>">WhatsApp the nursery</a></p>
    </div>
    <div>
      <a href="/shop">Shop</a>
      <a href="/shipping">Shipping</a>
      <a href="/returns">Returns</a>
      <a href="/faq">FAQ</a>
      <a href="/contact">Contact</a>
    </div>
    <div>
      <a href="/about">About</a>
      <a href="/privacy">Privacy</a>
      <a href="/terms">Terms</a>
    </div>
  </div>
</footer>
<script src="/assets/js/nav.js" defer></script>
</body>
</html>
<?php
}

function setting_safe(string $key, string $fallback): string
{
    try {
        return setting($key, $fallback);
    } catch (Throwable $err) {
        return $fallback;
    }
}

function product_card(array $product): void
{
    $stock = available_trays($product);
    $href = '/product/' . rawurlencode((string) $product['slug']);
    $price = effective_price($product);
    $onSale = offer_is_active($product);
    $img = image_variants((string) $product['image_url']);
    $out = $stock < 1 || ($product['availability'] ?? '') === 'not_in_season';
    ?>
<article class="card<?= $out ? ' is-out' : '' ?>">
  <a href="<?= e($href) ?>">
    <img src="<?= e($img['src']) ?>" <?php if ($img['srcset'] !== ''): ?>srcset="<?= e($img['srcset']) ?>" sizes="(max-width: 700px) 50vw, 280px" <?php endif; ?>alt="<?= e($product['name']) ?> seedling tray" width="<?= (int) $img['width'] ?>" height="<?= (int) $img['height'] ?>" loading="lazy" decoding="async">
  </a>
  <div class="card-body">
    <?php if (!empty($product['is_new'])): ?><p class="badge">New</p><?php endif; ?>
    <h3><a href="<?= e($href) ?>"><?= e($product['name']) ?></a></h3>
    <p class="muted"><?= e((string) ($product['variety'] ?? $product['scientific_name'])) ?></p>
    <p class="price"><?= inr($price) ?> <span class="muted">per tray</span><?php if ($onSale): ?><span class="compare"><?= inr((int) $product['price_inr']) ?></span><?php endif; ?></p>
    <p class="muted"><?= $out ? (($product['availability'] ?? '') === 'not_in_season' ? 'Not in season' : 'Out of stock') : ($stock <= 5 ? 'Only ' . $stock . ' trays left' : $stock . ' trays available') ?></p>
  </div>
</article>
<?php
}

function connect_or_explain(): void
{
    try {
        db();
        maybe_cleanup();
    } catch (Throwable $err) {
        error_log($err::class . ' ' . $err->getMessage());
        http_response_code(503);
        header('Retry-After: 300');
        $showSetup = !empty($GLOBALS['config']['debug']);
        render_header('We will be back shortly | Precision Agritech');
        echo '<section class="section"><div class="wrap narrow">';
        echo '<h1>We will be back shortly</h1>';
        echo '<p>The shop cannot open just now. Call <a href="tel:+919011975959">9011975959</a> and the nursery will help.</p>';
        if ($showSetup) {
            echo '<p>Import database/schema.sql into an empty database, or database/upgrade.sql if this database already exists. Fill config.local.php from config.example.php.</p>';
        }
        echo '</div></section>';
        render_footer();
        exit;
    }
}
