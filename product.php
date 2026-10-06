<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();

$slug = (string) ($_GET['slug'] ?? '');
$product = product_by_slug($slug);
if (!$product) {
    $jump = db()->prepare('SELECT p.slug FROM slug_redirects r JOIN products p ON p.id = r.product_id WHERE r.from_slug = ?');
    $jump->execute([$slug]);
    $next = $jump->fetchColumn();
    if ($next) {
        header('Location: /product/' . rawurlencode((string) $next), true, 301);
        exit;
    }
    http_response_code(404);
    render_header('Tray not found');
    echo '<section class="section"><div class="wrap"><h1>That tray is not listed.</h1><p><a href="/shop">Back to the shop</a></p></div></section>';
    render_footer();
    exit;
}
$cartError = null;
$reviewError = null;
$user = current_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        if (($_POST['action'] ?? '') === 'notify') {
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Add an email and the nursery will write when this tray is back.');
            }
            db()->prepare('INSERT INTO stock_alerts (product_id, email, phone) VALUES (?, ?, ?)')->execute([(int) $product['id'], $email, '']);
            flash('success', 'We will write when this tray is back.');
            header('Location: /product/' . rawurlencode($product['slug']));
            exit;
        }
        if (($_POST['action'] ?? '') === 'review') {
            if (!$user) {
                throw new RuntimeException('Sign in to review a tray you ordered.');
            }
            if (empty($user['email_verified_at']) && empty($user['phone_verified_at'])) {
                throw new RuntimeException('Verify your email or mobile before reviewing.');
            }
            $bought = db()->prepare("SELECT oi.id FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.product_id = ? AND o.user_id = ? AND o.status = 'delivered' LIMIT 1");
            $bought->execute([(int) $product['id'], (int) $user['id']]);
            if (!$bought->fetch()) {
                throw new RuntimeException('A review is available after this tray has been delivered to your account.');
            }
            $existing = db()->prepare('SELECT id FROM reviews WHERE product_id = ? AND user_id = ? LIMIT 1');
            $existing->execute([(int) $product['id'], (int) $user['id']]);
            if ($existing->fetch()) {
                throw new RuntimeException('You already reviewed this tray.');
            }
            $body = trim((string) ($_POST['body'] ?? ''));
            $rating = (int) ($_POST['rating'] ?? 0);
            if ($rating < 1 || $rating > 5 || mb_strlen($body) < 8) {
                throw new RuntimeException('Choose a rating and write a short review.');
            }
            db()->prepare('INSERT INTO reviews (product_id, user_id, author_name, rating, body, status, verified) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([(int) $product['id'], (int) $user['id'], $user['name'], $rating, $body, 'pending', 1]);
            flash('success', 'Thanks, your review will appear after approval.');
            header('Location: /product/' . rawurlencode($product['slug']));
            exit;
        }
        $qty = (int) ($_POST['qty'] ?? 0);
        $problem = line_problem($product, $qty);
        if ($problem) {
            throw new RuntimeException($product['name'] . ': ' . $problem);
        }
        $next = (int) (cart()[$product['slug']] ?? 0) + $qty;
        if ($next > min(50, available_trays($product))) {
            throw new RuntimeException('Only ' . min(50, available_trays($product)) . ' trays can go in the cart.');
        }
        remember_cart_line((string) $product['slug'], $next);
        flash('success', 'Added to the cart.');
        header('Location: /cart');
        exit;
    } catch (Throwable $err) {
        $message = safe_error($err, 'That could not be saved.');
        if (($_POST['action'] ?? '') === 'review') {
            $reviewError = $message;
        } else {
            $cartError = $message;
        }
    }
}
$reviews = db()->prepare("SELECT author_name, rating, body FROM reviews WHERE product_id = ? AND status = 'approved' ORDER BY id DESC");
$reviews->execute([(int) $product['id']]);
$reviewRows = $reviews->fetchAll();
$avg = 0;
if ($reviewRows) {
    $avg = array_sum(array_column($reviewRows, 'rating')) / count($reviewRows);
}
$stock = available_trays($product);
$price = effective_price($product);
$sale = offer_is_active($product);
$max = max(0, min(50, $stock));
$eligible = false;
if ($user && (!empty($user['email_verified_at']) || !empty($user['phone_verified_at']))) {
    $bought = db()->prepare("SELECT oi.id FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.product_id = ? AND o.user_id = ? AND o.status = 'delivered' LIMIT 1");
    $bought->execute([(int) $product['id'], (int) $user['id']]);
    $eligible = (bool) $bought->fetch();
}
$related = db()->prepare('SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id WHERE p.category_id = ? AND p.id <> ? AND p.active = 1 ORDER BY p.bestseller DESC LIMIT 4');
$related->execute([(int) $product['category_id'], (int) $product['id']]);
$img = image_variants((string) $product['image_url']);
global $config;
$json = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $product['name'],
    'image' => rtrim((string) ($config['site_url'] ?? ''), '/') . $product['image_url'],
    'description' => $product['short_description'],
    'offers' => [
        '@type' => 'Offer',
        'priceCurrency' => 'INR',
        'price' => $price,
        'availability' => $stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
    ],
], JSON_UNESCAPED_SLASHES);
render_header($product['name'] . ' trays | Precision Agritech', (string) $product['short_description'], ['image' => (string) $product['image_url'], 'json' => $json]);
?>
<section class="section">
  <div class="wrap">
    <p class="crumbs"><a href="/shop">Shop</a> / <a href="/shop?category=<?= e($product['category_slug']) ?>"><?= e($product['category_name']) ?></a> / <?= e($product['name']) ?></p>
    <div class="split">
      <img src="<?= e($img['src']) ?>" <?php if ($img['srcset']): ?>srcset="<?= e($img['srcset']) ?>" sizes="(max-width: 800px) 100vw, 640px" <?php endif; ?>alt="<?= e($product['name']) ?> seedling tray" width="1200" height="900" fetchpriority="high" decoding="async">
      <div>
        <p class="kicker"><?= e($product['category_name']) ?></p>
        <h1><?= e($product['name']) ?></h1>
        <p class="muted"><?= e($product['scientific_name']) ?> · <?= e($product['variety']) ?></p>
        <?php if ($reviewRows): ?><p><?= e(number_format($avg, 1)) ?> out of 5 from <?= count($reviewRows) ?> reviews</p><?php endif; ?>
        <p class="price"><?= inr($price) ?> <span class="muted">per <?= e($product['unit_label']) ?></span><?php if ($sale): ?> <span class="compare"><?= inr((int) $product['price_inr']) ?></span> <span class="muted">save <?= (int) round((1 - $price / max(1, (int) $product['price_inr'])) * 100) ?>%</span><?php endif; ?></p>
        <p><?= e($product['short_description']) ?></p>
        <ul class="spec">
          <li>Colour: <?= e($product['colour']) ?></li>
          <li>Season: <?= e($product['season_label']) ?></li>
          <li>Good for: <?= e($product['uses']) ?></li>
        </ul>
        <?php if (($product['availability'] ?? '') === 'seasonal'): ?><p class="muted">This is a seasonal tray. <?= e($product['season_label']) ?>.</p><?php endif; ?>
        <?php if ($cartError): ?><p class="flash error" role="alert"><?= e($cartError) ?></p><?php endif; ?>
        <?php if ($stock < 1 || ($product['availability'] ?? '') === 'not_in_season'): ?>
          <p><strong><?= ($product['availability'] ?? '') === 'not_in_season' ? 'Not in season' : 'Out of stock' ?></strong></p>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="notify"><label>Email <input type="email" name="email" required value="<?= e((string) ($user['email'] ?? '')) ?>"></label><button class="btn">Tell me when it is back</button></form>
          <p><a href="tel:+919011975959">Or call 9011975959</a></p>
        <?php else: ?>
          <form method="post">
            <?= csrf_field() ?>
            <label>Trays <input name="qty" type="number" min="<?= (int) $product['min_order'] ?>" max="<?= $max ?>" step="1" required inputmode="numeric" value="<?= (int) $product['min_order'] ?>"></label>
            <button class="btn">Add to cart</button>
          </form>
          <p class="muted"><?= $stock ?> trays ready · minimum <?= (int) $product['min_order'] ?><?php if ($stock <= 5): ?> · only <?= $stock ?> left<?php endif; ?></p>
        <?php endif; ?>
        <p><a href="<?= e(whatsapp_link('I want to ask about ' . $product['name'] . ' trays.')) ?>">Ask on WhatsApp</a></p>
      </div>
    </div>
    <h2>Planting</h2><p><?= e($product['description']) ?></p>
    <p><?= e($product['planting_info']) ?></p>
    <h2>Care</h2><p><?= e($product['care_info']) ?></p>
    <h2>Flowering</h2><p><?= e($product['flowering_info']) ?></p>
    <h2>Reviews</h2>
    <?php foreach ($reviewRows as $review): ?>
      <p><strong><?= e($review['author_name']) ?></strong> · <?= (int) $review['rating'] ?>/5<br><?= e($review['body']) ?></p>
    <?php endforeach; ?>
    <?php if ($reviewError): ?><p class="flash error" role="alert"><?= e($reviewError) ?></p><?php endif; ?>
    <?php if ($eligible): ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="review">
        <fieldset>
          <legend>Rating</legend>
          <?php for ($star = 5; $star >= 1; $star--): ?>
            <label><input type="radio" name="rating" value="<?= $star ?>" <?= $star === 5 ? 'required' : '' ?>> <?= $star ?></label>
          <?php endfor; ?>
        </fieldset>
        <label>Review <textarea name="body" required></textarea></label>
        <button class="btn">Send review</button>
      </form>
    <?php elseif ($user): ?>
      <p>Reviews are open after this tray has been delivered to your account, and after your email or mobile is verified.</p>
    <?php else: ?>
      <p><a href="/login">Sign in</a> to review a tray you ordered.</p>
    <?php endif; ?>
    <h2>You may also like</h2>
    <div class="grid">
      <?php foreach ($related as $other) { product_card($other); } ?>
    </div>
  </div>
</section>
<?php render_footer(); ?>
