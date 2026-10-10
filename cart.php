<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $action = (string) ($_POST['action'] ?? 'set');
        if ($action === 'coupon') {
            $code = strtoupper(trim((string) ($_POST['coupon'] ?? '')));
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
                $product = product_by_slug($slug);
                if (!$product) {
                    throw new RuntimeException('That tray is not listed.');
                }
                $have = (int) (cart()[$slug] ?? 0);
                if ($action === 'add') {
                    $next = $have + tray_qty((int) ($_POST['qty'] ?? 1));
                } else {
                    $qty = (int) ($_POST['qty'] ?? 0);
                    if ($qty < 1) {
                        unset($_SESSION['cart'][$slug]);
                        $next = 0;
                    } else {
                        $next = tray_qty($qty);
                    }
                }
                if ($next > 0) {
                    if ($next > 50) {
                        throw new RuntimeException('Choose between 1 and 50 trays.');
                    }
                    if ($next > available_trays($product)) {
                        throw new RuntimeException('There are not that many trays left.');
                    }
                    $_SESSION['cart'][$slug] = $next;
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
render_header('Cart | Precision Agritech');
?>
<section class="section"><div class="wrap narrow">
  <h1>Cart</h1>
  <?php if ($error !== ''): ?><p class="flash" role="alert"><?= e($error) ?></p><?php endif; ?>
  <?php if (!$quote['lines']): ?><p>Your cart is empty. <a href="/shop">Shop trays</a>.</p><?php endif; ?>
  <?php foreach ($quote['lines'] as $line): ?>
    <form method="post" class="card line-card">
      <?= csrf_field() ?>
      <input type="hidden" name="slug" value="<?= e($line['product']['slug']) ?>">
      <strong><?= e($line['product']['name']) ?></strong>
      <span><?= inr($line['line']) ?></span>
      <label>Trays <input name="qty" type="number" min="0" max="50" value="<?= (int) $line['qty'] ?>"></label>
      <button class="btn" type="submit">Update</button>
      <button class="btn" name="action" value="remove" type="submit">Remove</button>
    </form>
  <?php endforeach; ?>
  <?php if ($quote['lines']): ?>
    <p>Subtotal <?= inr($quote['subtotal']) ?><?php if ($quote['discount']): ?> · Discount <?= inr($quote['discount']) ?><?php endif; ?> · Shipping <?= inr($quote['shipping']) ?> · Total <?= inr($quote['total']) ?></p>
    <p class="muted">Shipping is free over <?= inr($freeOver) ?>.</p>
    <form method="post" class="inline">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="coupon">
      <label>Coupon <input name="coupon" value="<?= e($coupon) ?>" autocomplete="off"></label>
      <button class="btn" type="submit">Apply</button>
    </form>
    <p><a class="btn" href="/checkout">Checkout</a></p>
  <?php endif; ?>
</div></section>
<?php render_footer(); ?>
