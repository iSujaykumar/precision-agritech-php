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
require dirname(__DIR__) . '/includes/migrate.php';

$fail = 0;
function expect(string $name, bool $ok, string $detail = ''): void
{
    global $fail;
    if ($ok) {
        echo "PASS $name\n";
        return;
    }
    $fail++;
    echo "FAIL $name" . ($detail !== '' ? " — $detail" : '') . "\n";
}

$root = dirname(__DIR__);
foreach (['data/price-list-2026-27.csv' => 67, 'data/price-list-farmer.csv' => 33, 'database/price-list-2026-27.csv' => 67, 'database/price-list-farmer.csv' => 33] as $rel => $min) {
    $lines = file($root . '/' . $rel, FILE_IGNORE_NEW_LINES);
    $rows = max(0, count($lines ?: []) - 1);
    expect($rel . ' has ' . $min . ' rows', $rows >= $min, (string) $rows);
}

$oldPhone = '9011' . '975959';
$banned = [$oldPhone, '91' . $oldPhone, '9011 975' . '959', '9011-975' . '959', '+91' . $oldPhone, 'wa.me/91' . $oldPhone];
$termA = 'Pune juris' . 'diction';
$termB = 'minimum 50 percent adv' . 'ance';
$hits = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    $path = $file->getPathname();
    if (!preg_match('/\.(php|js|sql|txt|html|json|csv)$/', $path) || str_contains($path, '/tests/')) {
        continue;
    }
    $text = file_get_contents($path);
    if ($text === false) {
        continue;
    }
    foreach ($banned as $needle) {
        if (str_contains($text, $needle)) {
            $hits[] = str_replace($root . '/', '', $path);
        }
    }
    if (str_contains($text, $termA) || str_contains($text, $termB)) {
        $hits[] = str_replace($root . '/', '', $path) . ':terms';
    }
}
expect('old phone and terms are gone', $hits === [], implode(',', $hits));

$schema = file_get_contents($root . '/database/schema.sql') ?: '';
preg_match('/CREATE TABLE products \((.*?)\) ENGINE/s', $schema, $block);
$columns = [];
foreach (preg_split('/\n/', $block[1] ?? '') as $line) {
    if (preg_match('/^\s*`?([a-z_]+)`?\s+[A-Z]/', $line, $col)) {
        $columns[$col[1]] = true;
    }
}
$php = '';
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
    if (str_ends_with($file->getPathname(), '.php') && !str_contains($file->getPathname(), '/tests/')) {
        $php .= file_get_contents($file->getPathname()) . "\n";
    }
}
preg_match_all('/\bp\.([a-z_]+)/', $php, $used);
$missing = [];
foreach (array_unique($used[1]) as $name) {
    if (!isset($columns[$name])) {
        $missing[] = $name;
    }
}
expect('product columns used in PHP exist in schema', $missing === [], implode(',', $missing));

function trays_for(int $seedlings): int
{
    return (int) ceil($seedlings / 150);
}
expect('calc 1 seedling', trays_for(1) === 1);
expect('calc 149 seedlings', trays_for(149) === 1);
expect('calc 150 seedlings', trays_for(150) === 1);
expect('calc 151 seedlings', trays_for(151) === 2);
expect('calc 5000 seedlings', trays_for(5000) === 34);

$pdo = db();
$log = shop_upgrade($pdo);
expect('upgrade on prove db', is_array($log));
$again = shop_upgrade($pdo);
expect('upgrade again', is_array($again));

save_setting('seasonal_discount_on', '1');
save_setting('seasonal_discount_percent', '10');
save_setting('seasonal_discount_min_inr', '5000');
save_setting('discount_basis', 'rupees');
save_setting('discount_min_seedlings', '5000');
$below = seasonal_offer(4999, false, false, 100);
$exact = seasonal_offer(5000, false, false, 1200);
$above = seasonal_offer(6500, false, false, 1500);
expect('discount below 5000', $below['amount'] === 0 && $below['short_inr'] === 1, (string) $below['short_inr']);
expect('discount at 5000', $exact['amount'] === 500, (string) $exact['amount']);
expect('discount above 5000', $above['amount'] === 650, (string) $above['amount']);
$plants = 6000;
$packing = 180;
expect('packing is outside the discount', seasonal_offer($plants, false, false, 1500)['amount'] === 600 && ($plants - 600 + $packing) === 5580);

save_setting('discount_basis', 'seedlings');
$seedBelow = seasonal_offer(9000, false, false, 4999);
$seedExact = seasonal_offer(9000, false, false, 5000);
expect('seedling basis below', $seedBelow['amount'] === 0);
expect('seedling basis at 5000 seedlings', $seedExact['amount'] === 900, (string) $seedExact['amount']);
save_setting('discount_basis', 'rupees');

$pdo->beginTransaction();
try {
    $loaded = load_supplied_lists($pdo);
    expect('67 seasonal rows', (int) $pdo->query("SELECT COUNT(*) FROM price_list_items i JOIN price_lists l ON l.id=i.price_list_id WHERE l.slug='seasonal-2026-27'")->fetchColumn() === 67);
    expect('33 farmer rows', (int) $pdo->query("SELECT COUNT(*) FROM price_list_items i JOIN price_lists l ON l.id=i.price_list_id WHERE l.slug='farmer'")->fetchColumn() === 33);
    $cards = $pdo->query('SELECT slug, image_url, short_description, stock_qty, needs_copy, is_new, bestseller, list_only FROM products WHERE active=1 AND farmer_only=0 AND list_only=0 AND parent_product_id IS NULL')->fetchAll();
    expect('30 shop cards', count($cards) === 30, (string) count($cards));
    $bad = [];
    foreach ($cards as $card) {
        $img = (string) $card['image_url'];
        if ($img === '' || !is_file($root . $img) || trim((string) $card['short_description']) === '' || (int) $card['stock_qty'] < 1 || (int) $card['needs_copy'] === 1) {
            $bad[] = $card['slug'];
        }
    }
    expect('every shop card is filled', $bad === [], implode(',', $bad));
    $new = 0;
    $best = 0;
    foreach ($cards as $card) {
        $new += (int) $card['is_new'];
        $best += (int) $card['bestseller'];
    }
    expect('10 new plants', $new === 10, (string) $new);
    expect('8 best sellers', $best === 8, (string) $best);
    $only = (int) $pdo->query('SELECT COUNT(*) FROM products WHERE list_only=1 AND parent_product_id IS NULL AND farmer_only=0')->fetchColumn();
    expect('16 price-list-only plants', $only === 16, (string) $only);
    $terms = (string) $pdo->query("SELECT `value` FROM settings WHERE `key`='invoice_terms'")->fetchColumn();
    expect('terms cleared', $terms === '' || (stripos($terms, 'jurisdiction') === false));
    $pdo->rollBack();
} catch (Throwable $err) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    expect('prove import', false, $err->getMessage());
}

echo $fail === 0 ? "ALL PASS\n" : "FAILURES $fail\n";
exit($fail === 0 ? 0 : 1);
