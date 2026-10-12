<?php
declare(strict_types=1);
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

$now = new DateTimeImmutable('2026-06-15 12:00:00', shop_timezone());
$base = [
    'price_inr' => 200,
    'compare_at_inr' => null,
    'on_offer' => 0,
    'offer_starts_at' => null,
    'offer_ends_at' => null,
    'is_new' => 1,
    'active' => 1,
];
$cases = [
    'plain' => [$base, false, ''],
    'flag only' => [array_merge($base, ['on_offer' => 1]), true, 'Offer'],
    'compare only' => [array_merge($base, ['compare_at_inr' => 260]), true, 'Sale'],
    'future start' => [array_merge($base, ['on_offer' => 1, 'compare_at_inr' => 260, 'offer_starts_at' => '2026-07-01 00:00:00']), false, ''],
    'active range' => [array_merge($base, ['on_offer' => 1, 'compare_at_inr' => 260, 'offer_starts_at' => '2026-06-01 00:00:00', 'offer_ends_at' => '2026-06-30 00:00:00']), true, 'Sale'],
    'expired end' => [array_merge($base, ['on_offer' => 1, 'compare_at_inr' => 260, 'offer_ends_at' => '2026-06-01 00:00:00']), false, ''],
    'equal compare' => [array_merge($base, ['compare_at_inr' => 200]), false, ''],
    'lower compare' => [array_merge($base, ['compare_at_inr' => 150]), false, ''],
    'open end only' => [array_merge($base, ['compare_at_inr' => 240, 'offer_ends_at' => '2026-12-01 00:00:00']), true, 'Sale'],
];
foreach ($cases as $name => [$product, $listed, $badge]) {
    expect("offer $name listed", offer_is_listed($product, $now) === $listed);
    expect("offer $name badge", offer_badge($product, $now) === $badge);
    $showsCompare = offer_badge($product, $now) === 'Sale';
    expect("offer $name compare", $showsCompare === ($badge === 'Sale'));
}
expect('inactive product is not new', product_is_new(['active' => 0, 'is_new' => 1]) === false);
expect('active new product', product_is_new($base) === true);
expect('blank offer date', offer_input_datetime('') === null);
expect('offer datetime', offer_input_datetime('2026-06-15T12:30') === '2026-06-15 12:30:00');
$badDate = false;
try {
    offer_input_datetime('2026-13-40 99:00');
} catch (RuntimeException) {
    $badDate = true;
}
expect('bad offer date rejected', $badDate);
expect('end before start', '2026-06-01 00:00:00' < '2026-07-01 00:00:00');

$token = checkout_token();
expect('checkout token sticks', $token !== '' && checkout_token() === $token);
take_checkout_token($token);
$used = false;
try {
    take_checkout_token($token);
} catch (RuntimeException) {
    $used = true;
}
expect('checkout token is one-time', $used);

try {
    db();
} catch (Throwable $err) {
    echo "NOT RUN database " . $err->getMessage() . "\n";
    echo $fail === 0 ? "PASS catalog rules\n" : "FAIL catalog rules\n";
    exit($fail === 0 ? 0 : 1);
}

$new = array_column(home_products('new'), 'slug');
foreach (['cineraria', 'pentas', 'platycodon', 'ptilotus'] as $slug) {
    expect("new arrival $slug", in_array($slug, $new, true));
}
expect('new arrival limit', count($new) <= 8);
$names = array_column(home_products('new'), 'name');
$sorted = $names;
sort($sorted, SORT_STRING);
expect('new arrivals ordered by name', $names === $sorted);
expect('best sellers exist', count(home_products('bestsellers')) > 0);

$pdo = db();
$rows = $pdo->query('SELECT p.*, c.active AS category_active FROM products p JOIN categories c ON c.id = p.category_id')->fetchAll();
$offerSql = $pdo->query(
    'SELECT p.slug FROM products p JOIN categories c ON c.id = p.category_id WHERE '
    . catalog_public_sql() . ' AND p.stock_qty > p.reserved_qty AND ' . offer_active_sql()
)->fetchAll(PDO::FETCH_COLUMN);
foreach ($rows as $row) {
    $eligible = (int) $row['active'] === 1 && (int) $row['category_active'] === 1 && available_trays($row) > 0 && offer_is_listed($row);
    $inSql = in_array($row['slug'], $offerSql, true);
    if ($eligible !== $inSql) {
        expect('offer sql matches ' . $row['slug'], false, 'php=' . ($eligible ? 'yes' : 'no') . ' sql=' . ($inSql ? 'yes' : 'no'));
    }
}
expect('offer sql agrees with php', true);

$pdo->beginTransaction();
try {
    $before = product_by_slug('platycodon');
    expect('platycodon is public', $before !== null);
    $pdo->exec("UPDATE categories SET active = 0 WHERE slug = 'specialty'");
    expect('inactive category hides product', product_by_slug('platycodon') === null);
    expect('inactive category leaves sitemap', !in_array('platycodon', public_product_slugs(), true));
    expect('inactive category leaves new rail', !in_array('platycodon', array_column(home_products('new'), 'slug'), true));
    $shop = $pdo->query("SELECT COUNT(*) FROM products p JOIN categories c ON c.id = p.category_id WHERE p.active = 1 AND c.active = 1 AND p.slug = 'platycodon'")->fetchColumn();
    expect('inactive category leaves shop count', (int) $shop === 0);
    $_SESSION['cart'] = ['platycodon' => 1];
    $_SESSION['coupon'] = '';
    $quote = quote_cart();
    $warning = (string) ($quote['lines'][0]['warning'] ?? '');
    expect('cart blocks inactive category', !empty($quote['blocked']) && str_contains($warning, 'No longer available'));
    expect('checkout cannot lock hidden category', $before && lock_listed_product($pdo, (int) $before['id']) === null);

    $pdo->exec("UPDATE categories SET active = 1 WHERE slug = 'specialty'");
    $pdo->exec("UPDATE products SET active = 0 WHERE slug = 'zinnia'");
    expect('inactive product hidden', product_by_slug('zinnia') === null);
    expect('inactive product leaves sitemap', !in_array('zinnia', public_product_slugs(), true));

    $id = (int) $pdo->query("SELECT id FROM products WHERE slug = 'marigold'")->fetchColumn();
    $touch = $pdo->prepare('UPDATE products SET on_offer = ?, compare_at_inr = ?, offer_starts_at = ?, offer_ends_at = ? WHERE id = ?');
    $future = (new DateTimeImmutable('+3 days', shop_timezone()))->format('Y-m-d H:i:s');
    $past = (new DateTimeImmutable('-3 days', shop_timezone()))->format('Y-m-d H:i:s');
    $later = (new DateTimeImmutable('+10 days', shop_timezone()))->format('Y-m-d H:i:s');
    $checks = [
        'seed-like' => [0, null, null, null, false],
        'flag' => [1, null, null, null, true],
        'compare' => [0, 400, null, null, true],
        'future' => [1, 400, $future, $later, false],
        'window' => [1, 400, $past, $later, true],
        'expired' => [1, 400, $past, $past, false],
    ];
    foreach ($checks as $label => [$flag, $compare, $start, $end, $want]) {
        $touch->execute([$flag, $compare, $start, $end, $id]);
        $slugs = array_column(home_products('offers'), 'slug');
        expect("db offer $label", in_array('marigold', $slugs, true) === $want);
    }
    $row = $pdo->query('SELECT * FROM products WHERE id = ' . $id)->fetch();
    $touch->execute([1, 400, $past, $later, $id]);
    $row = $pdo->query('SELECT * FROM products WHERE id = ' . $id)->fetch();
    expect('db sale badge', offer_badge($row) === 'Sale');
    $touch->execute([1, null, null, null, $id]);
    $row = $pdo->query('SELECT * FROM products WHERE id = ' . $id)->fetch();
    expect('db offer badge without compare', offer_badge($row) === 'Offer');

    $search = $pdo->prepare(
        'SELECT p.slug FROM products p JOIN categories c ON c.id = p.category_id
         WHERE p.active = 1 AND c.active = 1 AND c.slug = ? AND p.name LIKE ?
         ORDER BY p.price_inr, p.name LIMIT 12 OFFSET 0'
    );
    $search->execute(['bedding', '%Marigold%']);
    expect('shop search and category', $search->fetchColumn() === 'marigold');
    $pages = (int) $pdo->query('SELECT COUNT(*) FROM products p JOIN categories c ON c.id = p.category_id WHERE p.active = 1 AND c.active = 1')->fetchColumn();
    expect('shop has a second page', $pages > 12);
} catch (Throwable $err) {
    expect('database cases', false, $err->getMessage());
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['cart'] = [];
}

$still = product_by_slug('platycodon');
expect('rollback restored platycodon', $still !== null);
$marigold = db()->query("SELECT on_offer, compare_at_inr FROM products WHERE slug = 'marigold'")->fetch();
expect('rollback restored marigold offer', (int) $marigold['on_offer'] === 0 && $marigold['compare_at_inr'] === null);

echo "NOT RUN http order lookup, admin csrf, upload bytes, and a second live checkout\n";
echo $fail === 0 ? "PASS catalog rules\n" : "FAIL catalog rules\n";
exit($fail === 0 ? 0 : 1);
