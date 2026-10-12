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
    echo "FAIL $name" . ($detail !== '' ? " $detail" : '') . "\n";
}

expect('425 x 150 rounds to 638', tray_rupees(425, 150) === 638);
expect('375 x 150 rounds to 563', tray_rupees(375, 150) === 563);
expect('325 x 150 rounds to 488', tray_rupees(325, 150) === 488);
expect('475 x 150 rounds to 713', tray_rupees(475, 150) === 713);
expect('310 x 150 rounds to 465', tray_rupees(310, 150) === 465);
expect('4.25 rupees is 425 paise', price_rupees_to_paise('4.25') === 425);
expect('3.10 rupees is 310 paise', price_rupees_to_paise('3.10') === 310);
expect('3 rupees is 300 paise', price_rupees_to_paise('3') === 300);
$badRate = false;
try {
    price_rupees_to_paise('-1');
} catch (InvalidArgumentException) {
    $badRate = true;
}
expect('negative rate rejected', $badRate);
expect('zero rupees in words', amount_in_words(0) === 'Zero rupees only');
expect('one rupee in words', amount_in_words(1) === 'One rupee only');
expect('one lakh in words', amount_in_words(100000) === 'One lakh rupees only');
expect('crore in words', amount_in_words(10000000) === 'One crore rupees only');
expect('1234567 in words', amount_in_words(1234567) === 'Twelve lakh thirty four thousand five hundred sixty seven rupees only');
$tz = shop_timezone();
expect('fy before April', financial_year(new DateTimeImmutable('2026-03-31 23:00:00', $tz)) === '2025-26');
expect('fy on 1 April', financial_year(new DateTimeImmutable('2026-04-01 00:30:00', $tz)) === '2026-27');
expect('percent half up', round_half_up_percent(5000, 10) === 500);
expect('percent below half', round_half_up_percent(49, 1) === 0);
$pdf = pdf_document(['Invoice PA/2026-27/0001', 'Total Rs 638']);
expect('pdf header', str_starts_with($pdf, '%PDF'));
expect('pdf has eof', str_contains($pdf, '%%EOF'));
expect('pdf strips rupee', !str_contains(pdf_document(['₹ 10']), '₹'));
$parsed = price_csv_parse("item,variety,marathi_name,price_per_seedling,source,sr_no,notes,sku\nMarigold,French,,3.75,sheet,16,note,MG-FR\n");
expect('csv one row', count($parsed['rows']) === 1 && $parsed['rows'][0]['paise'] === 375 && $parsed['invalid'] === []);
$badCsv = price_csv_parse("item,price_per_seedling\nBad,-2\n");
expect('csv invalid row', $badCsv['rows'] === [] && $badCsv['invalid'] !== []);
expect('guest selling price is retail', selling_price(['id' => 1, 'price_inr' => 220])['rupees'] === 220 && selling_price(['id' => 1, 'price_inr' => 220])['list'] === 'Seasonal (2026-27)');
expect('seasonal base skips overgrown by default', seasonal_base_amount([
    ['ok' => true, 'line' => 1000, 'product' => ['is_overgrown' => 0]],
    ['ok' => true, 'line' => 4000, 'product' => ['is_overgrown' => 1]],
]) === 1000);

try {
    db();
    expect('seasonal discount ships off', seasonal_offer(5000, false, false)['amount'] === 0);
    expect('farmer seasonal switch ships off', setting('seasonal_applies_farmer', '0') === '0');
    expect('seedling count display ships off', setting('show_seedling_count', '0') === '0');
    $pdo = db();
    $pdo->beginTransaction();
    $category = (int) $pdo->query('SELECT id FROM categories ORDER BY id LIMIT 1')->fetchColumn();
    $slug = 'farmer-only-test-' . bin2hex(random_bytes(3));
    $sku = 'FOT' . strtoupper(bin2hex(random_bytes(3)));
    $pdo->prepare('INSERT INTO products (slug, sku, name, scientific_name, variety, category_id, short_description, description, planting_info, care_info, flowering_info, colour, uses, image_url, unit_label, price_inr, stock_qty, min_order, availability, season_label, active, farmer_only) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,100,1,1,?,?,1,1)')
        ->execute([$slug, $sku, 'Farmer only test', '', 'Test', $category, 'x', 'x', '', '', '', '', '', '/brand/seedling.webp', 'tray', 'in_stock', '']);
    $hidden = !in_array($slug, public_product_slugs(), true);
    expect('farmer-only hidden from public slugs', $hidden);
    expect('farmer-only direct url misses', product_by_slug($slug) === null);
    $token = bin2hex(random_bytes(16));
    $pdo->prepare('INSERT INTO orders (order_number, lookup_token, customer_name, customer_phone, status, payment_status, payment_method, inventory_state, subtotal_inr, discount_inr, shipping_inr, total_inr, address_line, city, state_name, postal_code, price_list_name) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute(['PAINV' . strtoupper(bin2hex(random_bytes(3))), $token, 'Test Buyer', '9000000000', 'payment_confirmed', 'paid', 'upi', 'sold', 638, 0, 0, 638, 'Theur', 'Pune', 'Maharashtra', '412110', 'Seasonal (2026-27)']);
    $orderId = (int) $pdo->lastInsertId();
    $first = issue_invoice($pdo, $orderId);
    $again = issue_invoice($pdo, $orderId);
    expect('invoice is issued once', $first !== null && $first === $again);
    $number = $pdo->prepare('SELECT invoice_number, snapshot_json FROM invoices WHERE id = ?');
    $number->execute([$first]);
    $saved = $number->fetch();
    expect('invoice number shape', is_array($saved) && preg_match('#^PA/\d{4}-\d{2}/\d{4}$#', (string) $saved['invoice_number']) === 1);
    $snap = json_decode((string) ($saved['snapshot_json'] ?? ''), true);
    expect('invoice snapshot total', is_array($snap) && (int) ($snap['total'] ?? 0) === 638);
    $pdo->prepare('INSERT INTO orders (order_number, lookup_token, customer_name, customer_phone, status, payment_status, payment_method, inventory_state, subtotal_inr, discount_inr, shipping_inr, total_inr, address_line, city, state_name, postal_code) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute(['PAINV' . strtoupper(bin2hex(random_bytes(3))), bin2hex(random_bytes(16)), 'Test Buyer', '9000000001', 'payment_confirmed', 'paid', 'upi', 'sold', 100, 0, 0, 100, 'Theur', 'Pune', 'Maharashtra', '412110']);
    $secondId = (int) $pdo->lastInsertId();
    $second = issue_invoice($pdo, $secondId);
    $nums = $pdo->query('SELECT invoice_number FROM invoices WHERE id IN (' . (int) $first . ',' . (int) $second . ') ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    $gapless = count($nums) === 2 && substr((string) $nums[0], -4) !== '' && ((int) substr((string) $nums[1], -4) === (int) substr((string) $nums[0], -4) + 1);
    expect('invoice numbers step by one', $gapless, implode(',', $nums));
    $pdo->rollBack();
    echo "NOT RUN invoice concurrency on two connections\n";
    echo "NOT RUN MySQL 5.7 and MySQL 8\n";
} catch (Throwable $err) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $fail++;
    echo 'FAIL database pricing ' . $err->getMessage() . "\n";
}

echo $fail === 0 ? "PASS pricing rules\n" : "FAIL pricing rules\n";
exit($fail === 0 ? 0 : 1);
