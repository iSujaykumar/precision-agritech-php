<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$_SERVER['REQUEST_URI'] = '/admin';
$_SERVER['HTTP_HOST'] = '127.0.0.1';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTPS'] = 'off';
require dirname(__DIR__) . '/includes/bootstrap.php';

$fail = 0;
function expect(string $name, bool $ok, string $detail = ''): void
{
    global $fail;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . ($ok || $detail === '' ? '' : ' ' . $detail) . "\n";
    if (!$ok) {
        $fail++;
    }
}

$pdo = db();
$beforeStock = (int) $pdo->query("SELECT stock_qty FROM products WHERE slug = 'marigold'")->fetchColumn();
$beforePaise = (int) $pdo->query("SELECT price_per_seedling_paise FROM products WHERE slug = 'marigold'")->fetchColumn();
$beforeMin = setting('seasonal_discount_min_inr', '5000');
$beforeCalc = setting('calc_menu_on', '1');
$beforeOrder = setting('home_sections', '');
$pdo->beginTransaction();
try {
    $id = (int) $pdo->query("SELECT id FROM products WHERE slug = 'marigold'")->fetchColumn();
    $pdo->prepare('UPDATE products SET stock_qty = stock_qty + 1 WHERE id = ?')->execute([$id]);
    $nowStock = (int) $pdo->query("SELECT stock_qty FROM products WHERE id = $id")->fetchColumn();
    expect('stock change is visible to the shop query', $nowStock === $beforeStock + 1);

    $pdo->prepare('UPDATE products SET price_per_seedling_paise = ?, tray_paise = ? WHERE id = ?')->execute([400, 60000, $id]);
    $row = $pdo->query('SELECT * FROM products WHERE id = ' . $id)->fetch();
    $sell = selling_price($row);
    expect('price change shows per seedling and per tray', (int) $sell['paise'] === 400 && (int) $sell['tray_paise'] === 60000, (string) $sell['paise'] . '/' . (string) $sell['tray_paise']);

    $ids = $pdo->query("SELECT id FROM products WHERE slug IN ('cosmos','aster','calendula') ORDER BY slug")->fetchAll(PDO::FETCH_COLUMN);
    expect('three plants picked for the bulk offer', count($ids) === 3);
    $upd = $pdo->prepare('UPDATE products SET on_offer = 1, offer_seedling_paise = ? WHERE id = ?');
    foreach ($ids as $offerId) {
        $current = (int) $pdo->query('SELECT price_per_seedling_paise FROM products WHERE id = ' . (int) $offerId)->fetchColumn();
        $upd->execute([offer_cut_paise($current, 'percent', 10), (int) $offerId]);
    }
    $live = 0;
    foreach ($ids as $offerId) {
        $product = $pdo->query('SELECT * FROM products WHERE id = ' . (int) $offerId)->fetch();
        if (offer_is_listed($product) && (int) selling_price($product)['paise'] < (int) $product['price_per_seedling_paise']) {
            $live++;
        }
    }
    expect('bulk offer is live on 3 plants', $live === 3, (string) $live);
    $pdo->prepare('UPDATE products SET on_offer = 0, offer_seedling_paise = NULL, compare_at_inr = NULL, offer_starts_at = NULL, offer_ends_at = NULL WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')')->execute();
    $gone = 0;
    foreach ($ids as $offerId) {
        $product = $pdo->query('SELECT * FROM products WHERE id = ' . (int) $offerId)->fetch();
        if (!offer_is_listed($product)) {
            $gone++;
        }
    }
    expect('ending the offer removes the 3 plants', $gone === 3);

    save_setting('seasonal_discount_min_inr', '9000');
    $quiet = seasonal_offer(6000, false, false, 0);
    save_setting('seasonal_discount_min_inr', '5000');
    $loud = seasonal_offer(6000, false, false, 0);
    expect('raising the minimum removes the discount message', (int) $quiet['amount'] === 0 && (int) $loud['amount'] > 0, 'quiet ' . $quiet['amount'] . ' loud ' . $loud['amount']);

    save_setting('calc_menu_on', '0');
    $off = calc_menu_on();
    save_setting('calc_menu_on', '1');
    expect('calculator menu can be turned off and on', $off === false && calc_menu_on() === true);

    $keys = array_keys(home_section_state());
    move_home_section('ready', 'up');
    $moved = array_keys(home_section_state());
    expect('homepage section moves up', array_search('ready', $moved, true) === array_search('ready', $keys, true) - 1);
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
$GLOBALS['pa_settings_dirty'] = true;
$afterStock = (int) db()->query("SELECT stock_qty FROM products WHERE slug = 'marigold'")->fetchColumn();
$afterPaise = (int) db()->query("SELECT price_per_seedling_paise FROM products WHERE slug = 'marigold'")->fetchColumn();
expect('stock was put back', $afterStock === $beforeStock, (string) $afterStock);
expect('price was put back', $afterPaise === $beforePaise, (string) $afterPaise);
expect('discount minimum was put back', setting('seasonal_discount_min_inr', '') === $beforeMin, setting('seasonal_discount_min_inr', ''));
expect('calculator switch was put back', setting('calc_menu_on', '') === $beforeCalc);
expect('section order was put back', setting('home_sections', '') === $beforeOrder);
if ($fail > 0) {
    echo "FAIL desk proof ($fail)\n";
    exit(1);
}
echo "PASS desk proof\n";
