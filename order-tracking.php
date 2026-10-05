<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $number = strtoupper(trim((string) ($_POST['order_number'] ?? '')));
    if (!preg_match('/^PA[A-Z0-9]{6,12}$/', $number)) {
        $error = 'Enter the order number from your confirmation.';
    } else {
        header('Location: /order/' . rawurlencode($number));
        exit;
    }
}
render_header('Track an order | Precision Agritech');
?>
<section class="section"><div class="wrap narrow">
  <h1>Track an order</h1>
  <p>The order number shows status only. The delivery address stays on the private link.</p>
  <?php if ($error): ?><p class="flash" role="alert"><?= e($error) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Order number <input name="order_number" required autocomplete="off"></label>
    <button class="btn">Look up</button>
  </form>
</div></section>
<?php render_footer(); ?>
