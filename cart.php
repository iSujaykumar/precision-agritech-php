<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $slug = (string) ($_POST['slug'] ?? '');
        if (($_POST['action'] ?? '') === 'coupon') {
            $_SESSION['coupon'] = strtoupper(trim((string) ($_POST['coupon'] ?? '')));
            flash('success', 'Coupon updated.');
        } elseif (($_POST['action'] ?? '') === 'remove') {
            remember_cart_line($slug, 0);
            flash('success', 'Removed from the cart.');
        } else {
            $qty = (int) ($_POST['qty'] ?? 0);
            if ($qty < 1) {
                remember_cart_line($slug, 0);
            } else {
                remember_cart_line($slug, $qty);
            }
            flash('success', 'Cart updated.');
        }
    } catch (Throwable $err) {
        flash('error', safe_error($err, 'The cart could not be updated.'));
    }
    header('Location: /cart');
    exit;
}
$coupon = (string) ($_SESSION['coupon'] ?? '');
try {
    $quote = quote_cart($coupon);
} catch (Throwable $err) {
    $quote = quote_cart();
    flash('error', safe_error($err, 'That coupon could not be applied.'));
}
$freeOver = (int) setting('free_shipping_over_inr', '4000');
$gap = max(0, $freeOver - ($quote['subtotal'] - $quote['discount']));
render_header('Cart | Precision Agritech');
?>
<section class="section"><div class="wrap">
  <h1>Cart</h1>
  <?php if (!$quote['lines']): ?>
    <p>Your cart is empty. <a href="/shop">Continue shopping</a></p>
  <?php else: ?>
    <div class="table-scroll">
    <table>
      <thead><tr><th>Tray</th><th>Price</th><th>Qty</th><th>Total</th></tr></thead>
      <tbody>
      <?php foreach ($quote['lines'] as $line): ?>
        <tr>
          <td>
            <?php if (!empty($line['product']['image_url'])): ?><img class="thumb" src="<?= e((string) $line['product']['image_url']) ?>" alt="" width="80" height="60"><?php endif; ?>
            <?= e((string) $line['product']['name']) ?>
            <?php if (!empty($line['warning'])): ?><br><span class="flash error"><?= e((string) $line['warning']) ?></span><?php endif; ?>
          </td>
          <td><?= inr((int) ($line['unit'] ?? effective_price($line['product']))) ?></td>
          <td>
            <form method="post" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="slug" value="<?= e((string) $line['product']['slug']) ?>">
              <input name="qty" type="number" min="0" max="50" value="<?= (int) $line['qty'] ?>" aria-label="Quantity">
              <button class="btn" type="submit">Update</button>
              <button name="action" value="remove" type="submit">Remove</button>
            </form>
          </td>
          <td><?= inr((int) $line['line']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <p>Trays <?= inr($quote['subtotal']) ?><?php if ($quote['discount']): ?> · Discount <?= inr($quote['discount']) ?><?php endif; ?> · Shipping <?= $quote['shipping'] === 0 ? 'Free' : inr($quote['shipping']) ?></p>
    <?php if ($gap > 0): ?><p>Add <?= inr($gap) ?> more for free shipping.</p><?php else: ?><p>Shipping is free on this order.</p><?php endif; ?>
    <p><strong>Total <?= inr($quote['total']) ?></strong></p>
    <form method="post" class="narrow">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="coupon">
      <label>Coupon <input name="coupon" value="<?= e($coupon) ?>"></label>
      <button class="btn" type="submit">Apply coupon</button>
    </form>
    <?php if (!empty($quote['blocked'])): ?>
      <p>Fix the trays marked above before checkout.</p>
    <?php else: ?>
      <p><a class="btn" href="/checkout?coupon=<?= e(rawurlencode($coupon)) ?>">Checkout</a></p>
    <?php endif; ?>
    <p><a href="/shop">Continue shopping</a></p>
  <?php endif; ?>
</div></section>
<?php render_footer(); ?>
