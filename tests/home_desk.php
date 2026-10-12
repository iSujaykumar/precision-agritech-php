<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = '127.0.0.1';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTPS'] = 'off';
require dirname(__DIR__) . '/includes/bootstrap.php';

$fail = 0;
function expect(string $name, bool $ok, string $detail = ''): void
{
    global $fail;
    if ($ok) {
        echo "PASS $name\n";
        return;
    }
    $fail++;
    echo "FAIL $name" . ($detail !== '' ? ' ' . $detail : '') . "\n";
}

foreach ([1 => 1, 149 => 1, 150 => 1, 151 => 2, 5000 => 34] as $seedlings => $trays) {
    $math = calculator_math($seedlings, 425, 150, false);
    expect("trays for $seedlings seedlings", $math['trays'] === $trays, 'got ' . $math['trays']);
}
$below = calculator_math(1, 425, 150, false);
expect('one tray is under the rupee minimum', $below['applies'] === false);
$above = calculator_math(5000, 425, 150, false);
expect('5000 seedlings reaches the bulk discount', $above['applies'] === true && $above['discount_paise'] === intdiv($above['total_paise'] * 10 + 50, 100));
save_setting('offer_allows_bulk', '0');
$blocked = calculator_math(5000, 425, 150, true);
expect('offer plant skips bulk discount when the setting is off', $blocked['applies'] === false);
save_setting('offer_allows_bulk', '1');
$allowed = calculator_math(5000, 425, 150, true);
expect('offer plant keeps bulk discount when the setting is on', $allowed['applies'] === true);
$cut = offer_cut_paise(200, 'percent', 50);
expect('50 percent of Rs 2.00 stays at or above the nursery minimum', $cut >= nursery_min_seedling_paise());
$flat = offer_cut_paise(500, 'flat', 50);
expect('flat price under the minimum is lifted', $flat === nursery_min_seedling_paise());

$root = dirname(__DIR__);
$missing = [];
foreach (glob($root . '/admin/*.php') ?: [] as $file) {
    $text = file_get_contents($file);
    if ($text === false || !str_contains($text, 'admin_open(')) {
        continue;
    }
    if (preg_match('/admin_open\([^;\n]*\)/', $text, $m) && !str_contains($m[0], ',')) {
        $missing[] = basename($file);
    }
}
$desk = file_get_contents($root . '/includes/desk.php') ?: '';
expect('not-allowed page has a group', str_contains($desk, "admin_open('Not allowed', 'today')"));
expect('every desk page passes a group', $missing === [], implode(',', $missing));
$inventory = file_get_contents($root . '/admin/inventory.php') ?: '';
expect('old stock page redirects to the one stock page', str_contains($inventory, "Location: /admin/stock"));
$home = file_get_contents($root . '/includes/home_desk.php') ?: '';
expect('browse rail has a no-JavaScript note', str_contains($home, '<noscript>'));
expect('offers rail has the promo card', str_contains($home, 'promo-card'));
$css = file_get_contents($root . '/assets/css/site.css') ?: '';
expect('phone rails show two cards', str_contains($css, 'calc((100% - 0.75rem) / 2)'));
expect('page does not scroll sideways', str_contains($css, 'overflow-x: hidden'));

foreach (desk_metrics() as $row) {
    $again = desk_href_count($row['href']);
    expect('count matches list for ' . $row['label'], (int) $row['count'] === $again, (string) $row['count'] . ' vs ' . $again);
}
$orders = file_get_contents($root . '/admin/orders.php') ?: '';
$stock = file_get_contents($root . '/admin/stock.php') ?: '';
$photos = file_get_contents($root . '/admin/photos.php') ?: '';
expect('orders list uses the shared filter', str_contains($orders, 'orders_filter_sql'));
expect('stock list uses the shared rows', str_contains($stock, 'stock_rows'));
expect('photo list uses the shared rows', str_contains($photos, 'photo_rows'));

ob_start();
render_header('Test', 'Test');
render_homepage();
render_footer();
$html = ob_get_clean();
expect('homepage has the calculator control', str_contains($html, 'data-calc-open') && str_contains($html, 'id="calc-panel"'));
$panelAt = strpos($html, 'id="calc-panel"');
$scriptAt = strpos($html, '/assets/js/calc.js');
expect('calculator panel is in the page before its script', $panelAt !== false && $scriptAt !== false && $panelAt < $scriptAt);
expect('homepage browse is a carousel', str_contains($html, 'id="browse-all"') && str_contains($html, 'data-browse-chips') && str_contains($html, 'class="rail"'));
expect('homepage has no stacked category grids', !str_contains($html, '<div class="grid">'));
if (setting('seasonal_discount_on', '0') === '1') {
    expect('offers rail is not empty while the discount is on', str_contains($html, 'promo-card') && str_contains($html, 'id="offers"'));
} else {
    echo "NOT RUN offers rail, discount is off\n";
}
expect('plant this month is a card rail or an empty note', str_contains($html, 'id="plant-this-month"'));
expect('old phone is absent', !str_contains($html, '9011' . '975959'));

$offer = [
    'price_inr' => 638,
    'price_per_seedling_paise' => 425,
    'tray_paise' => 63750,
    'on_offer' => 1,
    'offer_seedling_paise' => 300,
    'offer_starts_at' => null,
    'offer_ends_at' => null,
    'farmer_only' => 0,
    'marathi_name' => '',
];
$sell = selling_price($offer);
expect('live offer price is the cut seedling rate', (int) $sell['paise'] === 300 && (int) $sell['tray_paise'] === 45000);

if ($fail > 0) {
    echo "FAIL home desk ($fail)\n";
    exit(1);
}
echo "PASS home desk\n";
