<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();

$product = product_by_slug((string) ($_GET['slug'] ?? ''));
if (!$product) {
    http_response_code(404);
    render_header('Tray not found');
    echo '<section class="section"><div class="wrap"><h1>That tray is not listed.</h1></div></section>';
    render_footer();
    exit;
}
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        if (($_POST['action'] ?? '') === 'review') {
            $user = require_user();
            if (($user['role'] ?? '') === 'admin') {
                throw new RuntimeException('Sign in with a customer account to review.');
            }
            if (review_limit_hit((int) $user['id'])) {
                throw new RuntimeException('You have sent several reviews. Please try again in an hour.');
            }
            $existing = db()->prepare('SELECT id FROM reviews WHERE product_id = ? AND user_id = ? LIMIT 1');
            $existing->execute([(int) $product['id'], (int) $user['id']]);
            if ($existing->fetch()) {
                throw new RuntimeException('You already reviewed this tray.');
            }
            $body = clean_review_body((string) ($_POST['body'] ?? ''));
            $author = trim((string) ($user['name'] ?? ''));
            $rating = (int) ($_POST['rating'] ?? 0);
            if ($rating < 1 || $rating > 5 || strlen($body) < 10 || strlen($body) > 600 || strlen($author) < 2) {
                throw new RuntimeException('Choose a star rating and write between 10 and 600 characters.');
            }
            $bought = db()->prepare("SELECT oi.id FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.product_id = ? AND o.user_id = ? AND o.status = 'delivered' LIMIT 1");
            $bought->execute([(int) $product['id'], (int) $user['id']]);
            $verified = $bought->fetch() ? 1 : 0;
            db()->prepare('INSERT INTO reviews (product_id, user_id, author_name, rating, body, status, verified) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([(int) $product['id'], (int) $user['id'], $author, $rating, $body, 'pending', $verified]);
            mail_staff('Review waiting', $product['name'] . ' has a new review to check.');
            flash_set('Thanks - your review will appear after we check it.');
            header('Location: /product/' . rawurlencode($product['slug']));
            exit;
        }
        $qty = tray_qty((int) ($_POST['qty'] ?? 0));
        $next = (int) (cart()[$product['slug']] ?? 0) + $qty;
        if ($next > 50) {
            throw new RuntimeException('Choose between 1 and 50 trays.');
        }
        $_SESSION['cart'][$product['slug']] = $next;
        header('Location: /cart');
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'That could not be saved.');
    }
}
$reviews = db()->prepare("SELECT author_name, rating, body, reply, verified, created_at FROM reviews WHERE product_id = ? AND status = 'approved' ORDER BY id DESC");
$reviews->execute([(int) $product['id']]);
$reviewRows = $reviews->fetchAll();
$rating = product_rating((int) $product['id']);
$stock = available_trays($product);
$image = product_image((string) ($product['image_url'] ?? ''));
$onSale = $product['compare_at_inr'] !== null && (int) $product['compare_at_inr'] > (int) $product['price_inr'];
$qtyMin = max(1, (int) $product['min_order']);
$GLOBALS['og_image'] = $image;
$schema = [
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $product['name'],
    'image' => $image,
    'description' => $product['short_description'],
    'sku' => $product['sku'],
    'offers' => [
        '@type' => 'Offer',
        'priceCurrency' => 'INR',
        'price' => (int) $product['price_inr'],
        'availability' => $stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
    ],
];
if ($rating) {
    $schema['aggregateRating'] = [
        '@type' => 'AggregateRating',
        'ratingValue' => $rating['avg'],
        'reviewCount' => $rating['n'],
    ];
}
$GLOBALS['json_ld'] = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$flash = flash_take();
$viewer = current_user();
$waTray = wa_href('Hello Precision Agritech, I want to know about ' . $product['name'] . ' at ' . inr((int) $product['price_inr']) . ' per tray.');
render_header($product['name'] . ' trays | Precision Agritech', $product['short_description']);
?>
<section class="section">
  <div class="wrap split product-split">
    <img src="<?= e($image) ?>" alt="<?= e($product['name']) ?> seedling tray" width="1200" height="900" decoding="async">
    <div>
      <p class="kicker"><?= e($product['category_name']) ?></p>
      <h1><?= e($product['name']) ?></h1>
      <p class="muted"><?= e($product['scientific_name']) ?> · <?= e($product['variety']) ?></p>
      <p class="price"><?= inr((int) $product['price_inr']) ?> <span class="muted">/ <?= e((string) $product['unit_label']) ?></span><?php if ($onSale): ?><span class="compare"><?= inr((int) $product['compare_at_inr']) ?></span><?php endif; ?></p>
      <p>Grown at Theur, Pune · <?= (int) available_trays($product) ?> trays ready · Delivery in 2–4 days after payment is confirmed.</p>
      <?php if ($rating): ?><p class="rating-line"><?= stars_markup($rating['avg'], $rating['n']) ?> <span class="muted"><?= e(number_format($rating['avg'], 1)) ?> · <?= (int) $rating['n'] ?> reviews</span></p><?php endif; ?>
      <p><?= e($product['description']) ?></p>
      <?php if ($stock <= 0): ?>
        <p class="stock-out">Out of stock</p>
      <?php elseif ($stock <= 5): ?>
        <p class="stock-low">Only <?= (int) $stock ?> left</p>
      <?php else: ?>
        <p class="muted"><?= (int) $stock ?> trays ready · minimum <?= (int) $qtyMin ?></p>
      <?php endif; ?>
      <?php if ($flash): ?><p class="flash <?= e($flash['kind']) ?>"><?= e($flash['message']) ?></p><?php endif; ?>
      <?php if ($error): ?><p class="flash bad"><?= e($error) ?></p><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <label>Trays <input name="qty" type="number" min="1" max="50" value="<?= (int) $qtyMin ?>"></label>
        <button class="btn" type="submit" <?= $stock < 1 ? 'disabled' : '' ?>>Add to cart</button>
      </form>
      <?php if ($waTray !== ''): ?><p><a class="btn" href="<?= e($waTray) ?>" target="_blank" rel="noopener">Ask about this tray on WhatsApp</a></p><?php endif; ?>
      <h2>Planting</h2><p><?= e($product['planting_info']) ?></p>
      <h2>Care</h2><p><?= e($product['care_info']) ?></p>
      <h2>Flowering</h2><p><?= e($product['flowering_info']) ?></p>
      <h2>Reviews</h2>
      <?php foreach ($reviewRows as $review): ?>
        <p><?= stars_markup((float) $review['rating']) ?> <strong><?= e(public_reviewer((string) $review['author_name'])) ?></strong>
          <?php if ($review['verified']): ?><span class="pill pill-active">Verified buyer</span><?php endif; ?>
          <br><?= e($review['body']) ?>
          <?php if (!empty($review['reply'])): ?><br><span class="muted">Nursery: <?= e((string) $review['reply']) ?></span><?php endif; ?>
        </p>
      <?php endforeach; ?>
      <?php if ($viewer && ($viewer['role'] ?? '') !== 'admin'): ?>
      <form method="post" class="review-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="review">
        <fieldset class="star-pick">
          <legend>Rating</legend>
          <?php for ($star = 5; $star >= 1; $star--): ?>
            <input id="star-<?= $star ?>" type="radio" name="rating" value="<?= $star ?>" required>
            <label for="star-<?= $star ?>"><?= $star ?> stars</label>
          <?php endfor; ?>
        </fieldset>
        <label>Review <textarea name="body" minlength="10" maxlength="600" required placeholder="How did this tray grow?"></textarea></label>
        <button class="btn" type="submit">Send review</button>
      </form>
      <?php elseif (!$viewer): ?><p><a href="/login?next=<?= e('/product/' . rawurlencode((string) $product['slug'])) ?>">Sign in to review</a></p><?php endif; ?>
    </div>
  </div>
</section>
<?php render_footer(); ?>
