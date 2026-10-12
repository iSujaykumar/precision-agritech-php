<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();
if (!can_see_farmer()) {
    http_response_code(404);
    render_header('Page not found | Precision Agritech');
    echo '<section class="section"><div class="wrap"><h1>That page is not listed.</h1><p><a href="/shop">Browse the shop</a></p></div></section>';
    render_footer();
    exit;
}
$rows = db()->query("SELECT i.item_name, i.variety, i.marathi_name, i.price_per_seedling_paise
    FROM price_list_items i JOIN price_lists l ON l.id = i.price_list_id
    WHERE l.slug = 'farmer' AND i.active = 1 ORDER BY i.id")->fetchAll();
$traySize = (int) setting('seedlings_per_tray', '150');
render_header('Farmer rates | Precision Agritech', 'Farmer rates for marigold and chrysanthemum.');
?>
<section class="section"><div class="wrap">
  <h1>Farmer rates</h1>
  <p class="muted">These rates show only for a customer marked Farmer. 1 tray = <?= (int) $traySize ?> seedlings.</p>
  <div class="table-wrap"><table>
    <tr><th>Item</th><th>Variety</th><th>Marathi</th><th>Per seedling</th><th>Per tray</th></tr>
    <?php foreach ($rows as $row):
      $paise = (int) $row['price_per_seedling_paise'];
    ?>
    <tr>
      <td><?= e((string) $row['item_name']) ?></td>
      <td><?= e((string) $row['variety']) ?></td>
      <td><?= e((string) $row['marathi_name']) ?></td>
      <td><?= e(seedling_rate_text($paise)) ?></td>
      <td><?= e(inr_paise(exact_tray_paise($paise, $traySize))) ?></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?php if (!$rows): ?><p>The farmer list has not been loaded yet.</p><?php endif; ?>
</div></section>
<?php render_footer(); ?>
