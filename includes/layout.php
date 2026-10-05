<?php
declare(strict_types=1);

function render_header(string $title, string $description = ''): void
{
    $user = null;
    try {
        $user = current_user();
    } catch (Throwable $err) {
        $user = null;
    }
    $count = cart_count();
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?></title>
  <?php if ($description !== ''): ?><meta name="description" content="<?= e($description) ?>"><?php endif; ?>
  <link rel="icon" href="/favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Newsreader:opsz,wght@6..72,500;6..72,600&family=Source+Sans+3:wght@400;600&display=swap">
  <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body>
<header class="nav">
  <a class="logo" href="/">Precision Agritech</a>
  <nav>
    <a href="/shop">Shop</a>
    <a href="/nursery">Our nursery</a>
    <a href="/wholesale">Wholesale</a>
    <a href="/account"><?= $user ? 'Account' : 'Sign in' ?></a>
    <a href="/cart">Cart<?= $count ? ' (' . $count . ')' : '' ?></a>
    <?php if ($user): ?>
    <form method="post" action="/logout" style="display:inline">
      <?= csrf_field() ?>
      <button class="linkish" type="submit">Sign out</button>
    </form>
    <?php endif; ?>
  </nav>
</header>
<main>
<?php
}

function render_footer(): void
{
    ?>
</main>
<footer class="footer">
  <div class="wrap footer-grid">
    <div>
      <strong>Precision Agritech</strong>
      <p>Survey No. 44/2, Theur Naygaon Road, Gaikwadvasti, Pune 412110.</p>
      <p><a href="tel:+919011975959">9011975959</a> · <a href="mailto:info@precisionagritech.in">info@precisionagritech.in</a></p>
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
</body>
</html>
<?php
}

function product_card(array $product): void
{
    $stock = available_trays($product);
    $href = '/product/' . rawurlencode((string) $product['slug']);
    ?>
<article class="card">
  <a href="<?= e($href) ?>"><img src="<?= e((string) $product['image_url']) ?>" alt="<?= e($product['name']) ?> seedling tray" width="800" height="600"></a>
  <div class="card-body">
    <h3><a href="<?= e($href) ?>"><?= e($product['name']) ?></a></h3>
    <p class="muted"><?= e($product['variety'] ?? $product['scientific_name']) ?></p>
    <p class="price"><?= inr((int) $product['price_inr']) ?> <span class="muted">/ tray</span><?php if ($product['compare_at_inr']): ?><span class="compare"><?= inr((int) $product['compare_at_inr']) ?></span><?php endif; ?></p>
    <p class="muted"><?= $stock > 0 ? $stock . ' trays available' : 'Out of stock' ?></p>
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
