<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();
$rows = db()->query('SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id WHERE ' . catalog_public_sql() . ' AND p.is_overgrown = 1 ORDER BY p.name')->fetchAll();
render_header('Overgrown seedlings | Precision Agritech', 'Taller seedlings sold as-is.');
?>
<section class="section"><div class="wrap">
  <h1>Overgrown seedlings</h1>
  <p><?= e(setting('overgrown_intro', 'These trays grew past the usual transplant stage. They are sold as they are.')) ?></p>
  <?php if (!$rows): ?>
    <p>None are listed right now. <?php $help = wa_href('Hello Precision Agritech, do you have overgrown seedlings?'); if ($help !== ''): ?><a href="<?= e($help) ?>">WhatsApp the nursery</a><?php else: ?><a href="/contact">Contact the nursery</a><?php endif; ?>.</p>
  <?php else: ?>
    <div class="grid"><?php foreach ($rows as $row) { product_card($row); } ?></div>
  <?php endif; ?>
</div></section>
<?php render_footer(); ?>
