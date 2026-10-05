<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $slug = (string) ($_POST['slug'] ?? '');
    if (($_POST['action'] ?? '') === 'remove') {
        unset($_SESSION['cart'][$slug]);
    } else {
        $qty = (int) ($_POST['qty'] ?? 0);
        if ($qty < 1) {
            unset($_SESSION['cart'][$slug]);
        } else {
            $_SESSION['cart'][$slug] = $qty;
        }
    }
    header('Location: /cart');
    exit;
}
$quote = quote_cart();
render_header('Cart | Precision Agritech');
?>
<section class="section"><div class="wrap">
  <h1>Cart</h1>
  <?php if (!$quote['lines']): ?><p>Your cart is empty. <a href="/shop">Shop trays</a>.</p><?php endif; ?>
  <?php foreach ($quote['lines'] as $line): ?>
    <form method="post" class="card" style="padding:1rem;margin-bottom:0.7rem">
      <?= csrf_field() ?>
      <input type="hidden" name="slug" value="<?= e($line['product']['slug']) ?>">
      <strong><?= e($line['product']['name']) ?></strong>
      <span><?= inr($line['line']) ?></span>
      <input name="qty" type="number" min="0" value="<?= (int) $line['qty'] ?>">
      <button class="btn">Update</button>
      <button class="btn" name="action" value="remove">Remove</button>
    </form>
  <?php endforeach; ?>
  <?php if ($quote['lines']): ?>
    <p>Subtotal <?= inr($quote['subtotal']) ?> · Shipping <?= inr($quote['shipping']) ?> · Total <?= inr($quote['total']) ?></p>
    <a class="btn" href="/checkout">Checkout</a>
  <?php endif; ?>
</div></section>
<?php render_footer(); ?>
