<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();
if (setting('seasonal_list_public', '1') !== '1') {
    http_response_code(404);
    render_header('Page not found | Precision Agritech');
    echo '<section class="section"><div class="wrap narrow"><h1>Page not found</h1><p>That page is not on the nursery site. <a href="/shop">Browse the shop</a>.</p></div></section>';
    render_footer();
    exit;
}
$rows = seasonal_rows_public();
$traySize = (int) setting('seedlings_per_tray', '150');
if (isset($_GET['pdf'])) {
    $marathi = (string) $_GET['pdf'] === 'mr';
    $lines = [$marathi ? 'भावपत्रक 2026-27' : 'Seasonal price list 2026-27', '1 tray = ' . $traySize . ' seedlings'];
    $lines[] = 'Sr | Item | Variety | Rate per seedling | Price per tray';
    $n = 1;
    foreach ($rows as $row) {
        $paise = (int) $row['price_per_seedling_paise'];
        $name = (string) $row['item_name'];
        $variety = (string) $row['variety'];
        if ($marathi && trim((string) $row['marathi_name']) !== '') {
            $variety .= ' / ' . $row['marathi_name'];
        }
        $lines[] = $n . '  ' . $name . '  ' . $variety . '  ' . seedling_rate_text($paise) . '  ' . inr_paise(exact_tray_paise($paise, $traySize));
        $n++;
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . ($marathi ? 'price-list-marathi.pdf' : 'price-list-2026-27.pdf') . '"');
    echo pdf_document($lines);
    exit;
}
render_header('Seasonal price list 2026-27 | Precision Agritech', 'Per seedling and per tray rates for the 2026-27 season.');
?>
<section class="section"><div class="wrap">
  <h1>Seasonal price list 2026-27</h1>
  <p class="muted">1 tray = <?= (int) $traySize ?> seedlings. Rates are from the nursery sheet.</p>
  <p class="btn-row">
    <a class="btn" href="/price-list?pdf=en">Download as PDF</a>
    <a class="btn" href="/price-list?pdf=mr">Marathi PDF</a>
  </p>
  <div class="table-wrap"><table>
    <tr><th>Sr no</th><th>Item</th><th>Variety</th><th>Marathi name</th><th><?= e(ui_label('seedling')) ?></th><th><?= e(ui_label('tray')) ?> (<?= (int) $traySize ?>)</th></tr>
    <?php $n = 1; $last = ''; foreach ($rows as $row):
      $paise = (int) $row['price_per_seedling_paise'];
      $tray = exact_tray_paise($paise, $traySize);
      if ($row['item_name'] !== $last) {
          echo '<tr><td colspan="6"><strong>' . e((string) $row['item_name']) . '</strong></td></tr>';
          $last = (string) $row['item_name'];
      }
    ?>
    <tr>
      <td><?= $n ?></td>
      <td><?= e((string) $row['item_name']) ?></td>
      <td><?php if (!empty($row['slug'])): ?><a href="/product/<?= e((string) $row['slug']) ?>"><?= e((string) $row['variety']) ?></a><?php else: ?><?= e((string) $row['variety']) ?><?php endif; ?></td>
      <td><?= e((string) $row['marathi_name']) ?></td>
      <td><?= e(seedling_rate_text($paise)) ?></td>
      <td><?= e(inr_paise($tray)) ?></td>
    </tr>
    <?php $n++; endforeach; ?>
  </table></div>
  <?php if (!$rows): ?><p>The seasonal list has not been loaded yet. Ask the nursery desk to press Update everything.</p><?php endif; ?>
  <?php $terms = invoice_term_lines(); if ($terms): ?>
  <h2>Terms</h2>
  <ol><?php foreach ($terms as $term): ?><li><?= e($term) ?></li><?php endforeach; ?></ol>
  <?php endif; ?>
</div></section>
<?php render_footer(); ?>
