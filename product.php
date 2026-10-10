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
            $author = trim((string) ($_POST['author'] ?? ''));
            $rating = max(1, min(5, (int) ($_POST['rating'] ?? 5)));
            if (strlen($author) < 2 || strlen($body) < 8) {
                throw new RuntimeException('Add your name and a short review.');
            }
            db()->prepare('INSERT INTO reviews (product_id, user_id, author_name, rating, body, status, verified) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([(int) $product['id'], (int) $user['id'], $author, $rating, $body, 'pending', 1]);
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
$reviews = db()->prepare("SELECT author_name, rating, body FROM reviews WHERE product_id = ? AND status = 'approved' ORDER BY id DESC");
$reviews->execute([(int) $product['id']]);
$stock = available_trays($product);
$image = product_image((string) ($product['image_url'] ?? ''));
$onSale = $product['compare_at_inr'] !== null && (int) $product['compare_at_inr'] > (int) $product['price_inr'];
$qtyMin = max(1, (int) $product['min_order']);
render_header($product['name'] . ' trays | Precision Agritech', $product['short_description']);
?>
<section class="section">
  <div class="wrap split">
    <img src="<?= e($image) ?>" alt="<?= e($product['name']) ?> seedling tray" width="1200" height="900" decoding="async">
    <div>
      <p class="kicker"><?= e($product['category_name']) ?></p>
      <h1><?= e($product['name']) ?></h1>
      <p class="muted"><?= e($product['scientific_name']) ?> · <?= e($product['variety']) ?></p>
      <p class="price"><?= inr((int) $product['price_inr']) ?> <span class="muted">/ tray</span><?php if ($onSale): ?><span class="compare"><?= inr((int) $product['compare_at_inr']) ?></span><?php endif; ?></p>
      <p><?= e($product['description']) ?></p>
      <?php if ($stock <= 0): ?>
        <p class="stock-out">Out of stock</p>
      <?php elseif ($stock <= 5): ?>
        <p class="stock-low">Only <?= (int) $stock ?> left</p>
      <?php else: ?>
        <p class="muted"><?= (int) $stock ?> trays ready · minimum <?= (int) $qtyMin ?></p>
      <?php endif; ?>
      <?php if ($error): ?><p class="flash"><?= e($error) ?></p><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <label>Trays <input name="qty" type="number" min="1" max="50" value="<?= (int) $qtyMin ?>"></label>
        <button class="btn" type="submit" <?= $stock < 1 ? 'disabled' : '' ?>>Add to cart</button>
      </form>
      <h2>Planting</h2><p><?= e($product['planting_info']) ?></p>
      <h2>Care</h2><p><?= e($product['care_info']) ?></p>
      <h2>Flowering</h2><p><?= e($product['flowering_info']) ?></p>
      <h2>Reviews</h2>
      <?php foreach ($reviews as $review): ?>
        <p><strong><?= e($review['author_name']) ?></strong> · <?= (int) $review['rating'] ?>/5<br><?= e($review['body']) ?></p>
      <?php endforeach; ?>
      <?php if (current_user() && current_user()['role'] !== 'admin'): ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="review">
        <label>Your name <input name="author" required value="<?= e((string) current_user()['name']) ?>"></label>
        <label>Rating <input name="rating" type="number" min="1" max="5" value="5"></label>
        <label>Review <textarea name="body" minlength="8" required placeholder="How did this tray grow?"></textarea></label>
        <button class="btn" type="submit">Send review</button>
      </form>
      <?php elseif (!current_user()): ?><p><a href="/login?next=<?= e('/product/' . rawurlencode((string) $product['slug'])) ?>">Sign in</a> to review a tray you ordered.</p><?php endif; ?>
    </div>
  </div>
</section>
<?php render_footer(); ?>
