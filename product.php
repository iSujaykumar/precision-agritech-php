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
            $bought = db()->prepare('SELECT oi.id, o.status FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.product_id = ? AND o.user_id = ? ORDER BY oi.id DESC LIMIT 1');
            $bought->execute([(int) $product['id'], (int) $user['id']]);
            $order = $bought->fetch();
            if (!$order) {
                throw new RuntimeException('A review needs an order for this tray on this account.');
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
                ->execute([(int) $product['id'], (int) $user['id'], $author, $rating, $body, 'pending', $order['status'] === 'delivered' ? 1 : 0]);
            header('Location: /product/' . rawurlencode($product['slug']));
            exit;
        }
        $qty = max(1, (int) ($_POST['qty'] ?? 1));
        $_SESSION['cart'][$product['slug']] = (int) (cart()[$product['slug']] ?? 0) + $qty;
        header('Location: /cart');
        exit;
    } catch (Throwable $err) {
        $error = $err->getMessage();
    }
}
$reviews = db()->prepare("SELECT author_name, rating, body FROM reviews WHERE product_id = ? AND status = 'approved' ORDER BY id DESC");
$reviews->execute([(int) $product['id']]);
$stock = available_trays($product);
render_header($product['name'] . ' trays | Precision Agritech', $product['short_description']);
?>
<section class="section">
  <div class="wrap split">
    <img src="<?= e($product['image_url']) ?>" alt="<?= e($product['name']) ?> seedling tray" width="1200" height="900">
    <div>
      <p class="kicker"><?= e($product['category_name']) ?></p>
      <h1><?= e($product['name']) ?></h1>
      <p class="muted"><?= e($product['scientific_name']) ?> · <?= e($product['variety']) ?></p>
      <p class="price"><?= inr((int) $product['price_inr']) ?> <span class="muted"><?= e($product['unit_label']) ?></span></p>
      <p><?= e($product['description']) ?></p>
      <p class="muted"><?= $stock ?> trays ready · minimum <?= (int) $product['min_order'] ?></p>
      <?php if ($error): ?><p class="flash"><?= e($error) ?></p><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <label>Trays <input name="qty" type="number" min="1" value="<?= (int) $product['min_order'] ?>"></label>
        <button class="btn" <?= $stock < 1 ? 'disabled' : '' ?>>Add to cart</button>
      </form>
      <h2>Planting</h2><p><?= e($product['planting_info']) ?></p>
      <h2>Care</h2><p><?= e($product['care_info']) ?></p>
      <h2>Flowering</h2><p><?= e($product['flowering_info']) ?></p>
      <h2>Reviews</h2>
      <?php foreach ($reviews as $review): ?>
        <p><strong><?= e($review['author_name']) ?></strong> · <?= (int) $review['rating'] ?>/5<br><?= e($review['body']) ?></p>
      <?php endforeach; ?>
      <?php if (current_user()): ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="review">
        <input name="author" placeholder="Your name" required>
        <input name="rating" type="number" min="1" max="5" value="5">
        <textarea name="body" minlength="8" required placeholder="How did this tray grow?"></textarea>
        <button class="btn">Send review</button>
      </form>
      <?php else: ?><p><a href="/login">Sign in</a> to review a tray you ordered.</p><?php endif; ?>
    </div>
  </div>
</section>
<?php render_footer(); ?>
