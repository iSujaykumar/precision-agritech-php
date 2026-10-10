<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $action = (string) ($_POST['action'] ?? 'set');
        if ($action === 'coupon') {
            if (rate_limited('coupon:' . client_ip(), 20, 60)) {
                throw new RuntimeException('Too many coupon tries. Wait a little and try again.');
            }
            note_rate('coupon:' . client_ip());
            $code = strtoupper(clean_text((string) ($_POST['coupon'] ?? ''), 40));
            if ($code === '') {
                unset($_SESSION['coupon']);
            } else {
                quote_cart($code);
                $_SESSION['coupon'] = $code;
            }
        } else {
            $slug = (string) ($_POST['slug'] ?? '');
            if ($action === 'remove') {
                unset($_SESSION['cart'][$slug]);
            } else {
                $stmt = db()->prepare('SELECT * FROM products WHERE slug = ?');
                $stmt->execute([$slug]);
                $product = $stmt->fetch();
                if (!$product) {
                    throw new RuntimeException('That tray is not listed.');
                }
                $qty = (int) ($_POST['qty'] ?? 0);
                if ($action === 'add') {
                    $qty = (int) (cart()[$slug] ?? 0) + tray_qty((int) ($_POST['qty'] ?? 1));
                }
                if ($qty < 1) {
                    unset($_SESSION['cart'][$slug]);
                } else {
                    if ($qty > 50) {
                        throw new RuntimeException('Choose between 1 and 50 trays.');
                    }
                    $_SESSION['cart'][$slug] = $qty;
                }
            }
        }
    } catch (Throwable $err) {
        $_SESSION['flash'] = safe_error($err, 'The cart could not be updated.');
    }
    header('Location: /cart');
    exit;
}
$coupon = (string) ($_SESSION['coupon'] ?? '');
$error = (string) ($_SESSION['flash'] ?? '');
unset($_SESSION['flash']);
try {
    $quote = quote_cart($coupon);
} catch (Throwable $err) {
    unset($_SESSION['coupon']);
    $coupon = '';
    $quote = quote_cart();
    $error = $error !== '' ? $error : safe_error($err, 'That coupon is not valid.');
}
$freeOver = (int) setting('free_shipping_over_inr', '4000');
$after = max(0, (int) $quote['subtotal'] - (int) $quote['discount']);
$progress = $freeOver > 0 ? min(100, (int) round($after / $freeOver * 100)) : 100;
$paused = shop_paused();
$help = wa_href('Hello Precision Agritech, I need help with my cart.');
render_header('Cart | Precision Agritech', 'Review your seedling trays before checkout.');
?>
<section class="section"><div class="wrap">
  <p><a href="/shop">Continue shopping</a></p>
  <h1>Cart</h1>
  <?php if ($error !== ''): ?><p class="flash bad" role="alert"><?= e($error) ?></p><?php endif; ?>
  <?php if (!$quote['lines']): ?>
    <div class="empty-cart">
      <p class="empty-mark" aria-hidden="true">🌱</p>
      <h2>Your cart is empty</h2>
      <p><a class="btn" href="/shop">Shop seedlings</a></p>
    </div>
    <?php
    $picks = db()->query('SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id WHERE p.active = 1 AND p.bestseller = 1 ORDER BY p.name LIMIT 4')->fetchAll();
    if ($picks): ?>
      <div class="grid"><?php foreach ($picks as $pick) { product_card($pick); } ?></div>
    <?php endif; ?>
  <?php else: ?>
  <div class="cart-layout">
    <div class="cart-lines">
      <?php foreach ($quote['lines'] as $line):
        $product = $line['product'];
        $free = (int) ($line['free'] ?? available_trays($product));
        $max = max(1, min(50, $free > 0 ? $free : 50));
      ?>
      <form method="post" class="card cart-row" data-cart-row>
        <?= csrf_field() ?>
        <input type="hidden" name="slug" value="<?= e((string) $product['slug']) ?>">
        <a href="/product/<?= e((string) $product['slug']) ?>"><img src="<?= e(product_image((string) ($product['image_url'] ?? ''))) ?>" alt="" width="96" height="72"></a>
        <div>
          <strong><a href="/product/<?= e((string) $product['slug']) ?>"><?= e((string) $product['name']) ?></a></strong>
          <p class="muted"><?= e((string) ($product['variety'] ?? '')) ?></p>
          <p><?= inr((int) ($line['unit'] ?? $product['price_inr'])) ?> / tray (104 plants)</p>
          <?php if ($free > 0 && $free <= 5 && empty($line['warning'])): ?><p class="stock-note">Only <?= $free ?> left</p><?php endif; ?>
          <?php if (!empty($line['warning'])): ?><p class="flash bad" role="alert"><?= e((string) $line['warning']) ?></p><?php endif; ?>
        </div>
        <div class="stepper">
          <button class="step" type="button" data-step="minus" aria-label="Fewer trays">−</button>
          <input data-qty name="qty" type="number" min="1" max="<?= $max ?>" value="<?= (int) $line['qty'] ?>" inputmode="numeric" aria-label="Trays">
          <button class="step" type="button" data-step="plus" aria-label="More trays">+</button>
        </div>
        <strong class="line-total"><?= inr((int) $line['line']) ?></strong>
        <button class="text-link" name="action" value="remove" type="submit">Remove</button>
        <noscript><button class="btn" type="submit">Update</button></noscript>
      </form>
      <?php endforeach; ?>
    </div>
    <aside class="card summary">
      <h2>Order summary</h2>
      <p><span>Subtotal</span><span><?= inr((int) $quote['subtotal']) ?></span></p>
      <?php if ($quote['discount']): ?>
        <p><span>Discount (<?= e((string) ($quote['coupon']['code'] ?? $coupon)) ?>)</span><span>−<?= inr((int) $quote['discount']) ?></span></p>
      <?php endif; ?>
      <p><span>Shipping</span><span><?= (int) $quote['shipping'] === 0 ? 'Free' : inr((int) $quote['shipping']) ?></span></p>
      <p class="summary-total"><span>Total</span><span><?= inr((int) $quote['total']) ?></span></p>
      <div class="ship-bar" role="img" aria-label="<?= $after >= $freeOver ? 'You have free shipping' : 'Add ' . inr($freeOver - $after) . ' more for free shipping' ?>">
        <span style="width:<?= $progress ?>%"></span>
      </div>
      <p class="muted"><?= $after >= $freeOver ? 'You have free shipping' : 'Add ' . inr(max(0, $freeOver - $after)) . ' more for free shipping' ?></p>
      <?php if ($coupon !== '' && $quote['discount']): ?>
        <p class="chip"><?= e($coupon) ?>
          <button class="text-link" form="drop-coupon" type="submit">Remove</button>
        </p>
      <?php else: ?>
        <details class="coupon-box">
          <summary>Have a coupon?</summary>
          <form method="post" class="coupon-row">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="coupon">
            <input name="coupon" value="<?= e($coupon) ?>" autocomplete="off" aria-label="Coupon code">
            <button class="btn" type="submit">Apply</button>
          </form>
        </details>
      <?php endif; ?>
      <?php if ($paused): ?><p class="flash bad" role="alert">Orders are paused right now</p><?php endif; ?>
      <?php if ($quote['blocked'] || $paused): ?>
        <button class="btn btn-block" type="button" disabled>Checkout</button>
      <?php else: ?>
        <a class="btn btn-block" href="/checkout">Checkout</a>
      <?php endif; ?>
      <ul class="reassure">
        <li>Trays are hardened at the Theur nursery before they leave</li>
        <li>Delivery in 2–4 days after payment is confirmed</li>
        <?php if ($help !== ''): ?><li><a href="<?= e($help) ?>" target="_blank" rel="noopener">Need help? Chat on WhatsApp</a></li><?php endif; ?>
      </ul>
    </aside>
  </div>
  <form id="drop-coupon" method="post" hidden>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="coupon">
    <input type="hidden" name="coupon" value="">
  </form>
  <div class="sticky-checkout">
    <strong><?= inr((int) $quote['total']) ?></strong>
    <?php if (!$quote['blocked'] && !$paused): ?><a class="btn" href="/checkout">Checkout</a><?php else: ?><span class="btn" aria-disabled="true">Checkout</span><?php endif; ?>
  </div>
  <?php endif; ?>
</div></section>
<?php render_footer(); ?>
