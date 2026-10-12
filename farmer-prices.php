<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();
if (!can_see_farmer()) {
    http_response_code(404);
    render_header('Page not found | Precision Agritech');
    echo '<section class="section"><div class="wrap narrow"><h1>Page not found</h1><p>That page is not on the nursery site.</p></div></section>';
    render_footer();
    exit;
}
$rows = db()->query("SELECT p.* FROM products p WHERE p.active = 1 AND p.farmer_only = 1 AND p.parent_product_id IS NOT NULL ORDER BY p.name, p.id")->fetchAll();
$columns = ['Marigold' => [], 'Chrysanthemum' => []];
foreach ($rows as $row) {
    $key = stripos((string) $row['name'], 'chrys') !== false || stripos((string) $row['sku'], 'chrys') !== false ? 'Chrysanthemum' : 'Marigold';
    if (stripos((string) $row['name'], 'Marigold') !== false) {
        $key = 'Marigold';
    }
    $columns[$key][] = $row;
}
render_header('Farmer rates | Precision Agritech', 'Marigold and chrysanthemum rates from 1 January 2026.');
?>
<section class="section"><div class="wrap">
  <h1>दि ०१.०१.२०२६ पासून झेंडू आणि शेवंतीचे नवीन दरपत्रक</h1>
  <p>New rate sheet for marigold and chrysanthemum from 01.01.2026</p>
  <p class="muted">1 tray = 150 seedlings. These rates are for approved farmer accounts.</p>
  <div class="grid">
    <?php foreach ($columns as $title => $items): ?>
    <div>
      <h2><?= e($title) ?></h2>
      <div class="table-wrap"><table>
        <tr><th>Variety</th><th>Rate</th><th></th></tr>
        <?php foreach ($items as $item):
          $sell = selling_price($item);
          $name = (string) $item['variety'];
          if (setting('show_marathi_names', '1') === '1' && trim((string) $item['marathi_name']) !== '') {
              $name = (string) $item['marathi_name'] . ' (' . $name . ')';
          }
        ?>
        <tr>
          <td><?= e($name) ?></td>
          <td><?= e(price_pair_text($sell)) ?></td>
          <td>
            <form method="post" action="/cart">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="add">
              <input type="hidden" name="slug" value="<?= e((string) $item['slug']) ?>">
              <input type="hidden" name="qty" value="1">
              <button class="btn" type="submit">Add</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </table></div>
    </div>
    <?php endforeach; ?>
  </div>
</div></section>
<?php render_footer();
