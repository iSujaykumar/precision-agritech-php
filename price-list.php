<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();
if (setting('seasonal_list_public', '1') !== '1') {
    http_response_code(404);
    render_header('Page not found | Precision Agritech');
    echo '<section class="section"><div class="wrap narrow"><h1>Page not found</h1><p>That page is not on the nursery site.</p></div></section>';
    render_footer();
    exit;
}
$rows = db()->query("SELECT i.item_name, i.variety, i.price_per_seedling_paise, i.notes, p.slug
    FROM price_list_items i
    JOIN price_lists l ON l.id = i.price_list_id
    LEFT JOIN products p ON p.id = i.product_id
    WHERE l.slug = 'seasonal-2026-27' AND i.active = 1 AND (p.id IS NULL OR (p.active = 1 AND p.farmer_only = 0))
    ORDER BY i.id")->fetchAll();
if (isset($_GET['pdf'])) {
    $lines = ['Seasonal price list 2026-27', '1 tray = ' . setting('seedlings_per_tray', '150') . ' seedlings'];
    foreach ($rows as $row) {
        $paise = (int) $row['price_per_seedling_paise'];
        $lines[] = $row['item_name'] . ' / ' . $row['variety'] . '  ' . seedling_rate_text($paise) . '  tray ' . inr_paise(exact_tray_paise($paise, 150));
    }
    foreach (invoice_term_lines() as $term) {
        $lines[] = $term;
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="seasonal-price-list-2026-27.pdf"');
    echo pdf_document($lines);
    exit;
}
render_header('Seasonal price list 2026-27 | Precision Agritech', 'Per seedling and per tray rates for the 2026-27 season.');
?>
<section class="section"><div class="wrap">
  <h1>Seasonal price list 2026-27</h1>
  <p class="muted">1 tray = <?= (int) setting('seedlings_per_tray', '150') ?> seedlings. Rates are from the nursery sheet. Ask us to confirm what is ready.</p>
  <div class="table-wrap"><table>
    <tr><th>Plant</th><th>Variety</th><th>Per seedling</th><th>Per tray</th></tr>
    <?php foreach ($rows as $row):
      $paise = (int) $row['price_per_seedling_paise'];
      $tray = exact_tray_paise($paise, (int) setting('seedlings_per_tray', '150'));
    ?>
    <tr>
      <td><?= e((string) $row['item_name']) ?></td>
      <td><?= e((string) $row['variety']) ?></td>
      <td><?= e(seedling_rate_text($paise)) ?></td>
      <td><?= e(inr_paise($tray)) ?></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?php if (!$rows): ?><p>The seasonal list has not been loaded yet.</p><?php endif; ?>
  <h2>Terms</h2>
  <ol><?php foreach (invoice_term_lines() as $term): ?><li><?= e($term) ?></li><?php endforeach; ?></ol>
</div></section>
<?php
render_footer();
