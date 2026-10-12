<?php
declare(strict_types=1);

function tray_rupees(int $paise, int $seedlings): int
{
    if ($paise < 0 || $seedlings < 1) {
        throw new InvalidArgumentException('Tray price needs a positive seedling rate and tray size.');
    }
    return intdiv(($paise * $seedlings) + 50, 100);
}

function seedlings_per_tray(?array $product = null): int
{
    $override = (int) ($product['seedlings_override'] ?? 0);
    if ($override >= 1 && $override <= 1000) {
        return $override;
    }
    $global = (int) setting('seedlings_per_tray', '150');
    if ($global < 1 || $global > 1000) {
        return 150;
    }
    return $global;
}

function amount_in_words(int $rupees): string
{
    if ($rupees < 0) {
        return 'Minus ' . amount_in_words(-$rupees);
    }
    if ($rupees === 0) {
        return 'Zero rupees only';
    }
    $ones = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
    $tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
    $words = static function (int $n) use (&$words, $ones, $tens): string {
        if ($n < 20) {
            return $ones[$n];
        }
        if ($n < 100) {
            return trim($tens[intdiv($n, 10)] . ' ' . $ones[$n % 10]);
        }
        return trim($ones[intdiv($n, 100)] . ' hundred ' . $words($n % 100));
    };
    $crore = intdiv($rupees, 10000000);
    $rupees %= 10000000;
    $lakh = intdiv($rupees, 100000);
    $rupees %= 100000;
    $thousand = intdiv($rupees, 1000);
    $rest = $rupees % 1000;
    $parts = [];
    if ($crore) {
        $parts[] = $words($crore) . ' crore';
    }
    if ($lakh) {
        $parts[] = $words($lakh) . ' lakh';
    }
    if ($thousand) {
        $parts[] = $words($thousand) . ' thousand';
    }
    if ($rest) {
        $parts[] = $words($rest);
    }
    return ucfirst(trim(implode(' ', $parts))) . ($rupees === 1 ? ' rupee only' : ' rupees only');
}

function financial_year(?DateTimeImmutable $now = null): string
{
    $now = $now ?? new DateTimeImmutable('now', shop_timezone());
    $year = (int) $now->format('Y');
    $start = (int) $now->format('n') < 4 ? $year - 1 : $year;
    return $start . '-' . substr((string) ($start + 1), -2);
}

function round_half_up_percent(int $base, int $percent): int
{
    if ($base < 0 || $percent < 0) {
        return 0;
    }
    return intdiv(($base * $percent) + 50, 100);
}

function customer_is_farmer(): bool
{
    try {
        $user = current_user();
    } catch (Throwable $err) {
        return false;
    }
    return $user && ($user['role'] ?? '') !== 'admin' && (int) ($user['farmer'] ?? 0) === 1;
}

function farmer_price_map(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    $map = [];
    if (!customer_is_farmer()) {
        return $map;
    }
    try {
        $rows = db()->query("SELECT i.product_id, i.price_per_seedling_paise, i.marathi_name, i.seedlings_per_tray, l.effective_from, l.effective_to, l.active AS list_active, i.active
            FROM price_list_items i JOIN price_lists l ON l.id = i.price_list_id
            WHERE l.audience = 'farmer' AND i.product_id IS NOT NULL");
        $today = (new DateTimeImmutable('now', shop_timezone()))->format('Y-m-d');
        foreach ($rows as $row) {
            $open = (int) $row['list_active'] === 1 && (int) $row['active'] === 1
                && ($row['effective_from'] === null || $row['effective_from'] <= $today)
                && ($row['effective_to'] === null || $row['effective_to'] >= $today);
            $map[(int) $row['product_id']] = ['open' => $open, 'row' => $row];
        }
    } catch (Throwable $err) {
        $map = [];
    }
    return $map;
}

function selling_price(array $product): array
{
    $retail = (int) ($product['price_inr'] ?? 0);
    $tray = seedlings_per_tray($product);
    $base = ['rupees' => $retail, 'list' => 'Seasonal (2026-27)', 'paise' => null, 'tray' => $tray, 'marathi' => '', 'blocked' => false];
    if (!customer_is_farmer()) {
        return $base;
    }
    $hit = farmer_price_map()[(int) ($product['id'] ?? 0)] ?? null;
    if ($hit === null) {
        return $base;
    }
    if (!$hit['open']) {
        if (setting('farmer_fallback_seasonal', '0') === '1') {
            return $base;
        }
        $base['rupees'] = 0;
        $base['blocked'] = true;
        $base['list'] = 'Farmer';
        return $base;
    }
    $paise = (int) $hit['row']['price_per_seedling_paise'];
    $size = (int) ($hit['row']['seedlings_per_tray'] ?? 0);
    if ($size < 1) {
        $size = $tray;
    }
    return [
        'rupees' => tray_rupees($paise, $size),
        'list' => 'Farmer',
        'paise' => $paise,
        'tray' => $size,
        'marathi' => (string) ($hit['row']['marathi_name'] ?? ''),
        'blocked' => false,
    ];
}

function seasonal_offer(int $eligible, bool $farmer, bool $cod): array
{
    $off = ['amount' => 0, 'label' => '', 'percent' => 0, 'short' => 0];
    if (setting('seasonal_discount_on', '0') !== '1') {
        return $off;
    }
    if ($farmer && setting('seasonal_applies_farmer', '0') !== '1') {
        return $off;
    }
    if ($cod && setting('seasonal_applies_cod', '1') !== '1') {
        return $off;
    }
    $now = new DateTimeImmutable('now', shop_timezone());
    $start = trim(setting('seasonal_discount_start', ''));
    $end = trim(setting('seasonal_discount_end', ''));
    if ($start !== '' && $now < new DateTimeImmutable($start, shop_timezone())) {
        return $off;
    }
    if ($end !== '' && $now > new DateTimeImmutable($end . ' 23:59:59', shop_timezone())) {
        return $off;
    }
    $min = (int) setting('seasonal_discount_min_inr', '5000');
    $percent = (int) setting('seasonal_discount_percent', '10');
    $label = setting('seasonal_discount_label', 'Seasonal discount');
    if ($eligible < $min) {
        $off['short'] = $min - $eligible;
        $off['label'] = $label;
        $off['percent'] = $percent;
        return $off;
    }
    return ['amount' => round_half_up_percent($eligible, $percent), 'label' => $label, 'percent' => $percent, 'short' => 0];
}

function seasonal_base_amount(array $lines): int
{
    $include = setting('seasonal_applies_overgrown', '0') === '1';
    $sum = 0;
    foreach ($lines as $line) {
        if (array_key_exists('ok', $line) && empty($line['ok'])) {
            continue;
        }
        $product = $line['product'] ?? [];
        if (!$include && (int) ($product['is_overgrown'] ?? 0) === 1) {
            continue;
        }
        $sum += (int) ($line['line'] ?? 0);
    }
    return $sum;
}

function price_rupees_to_paise(string $raw): int
{
    $raw = trim($raw);
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $raw)) {
        throw new InvalidArgumentException('Rate must be a number with up to 2 decimals.');
    }
    [$rupees, $paise] = array_pad(explode('.', $raw, 2), 2, '0');
    $paise = str_pad(substr($paise, 0, 2), 2, '0');
    $value = ((int) $rupees) * 100 + (int) $paise;
    if ($value < 0 || $value > 100000) {
        throw new InvalidArgumentException('Rate is out of range.');
    }
    return $value;
}

function price_csv_parse(string $text): array
{
    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
    $handle = fopen('php://temp', 'r+');
    if ($handle === false) {
        throw new RuntimeException('The price file could not be read.');
    }
    fwrite($handle, $text);
    rewind($handle);
    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        throw new RuntimeException('The price file needs a header row.');
    }
    $map = [];
    foreach ($header as $index => $name) {
        $map[strtolower(trim((string) $name))] = $index;
    }
    foreach (['item', 'price_per_seedling'] as $need) {
        if (!isset($map[$need])) {
            fclose($handle);
            throw new RuntimeException('The price file needs an ' . $need . ' column.');
        }
    }
    $rows = [];
    $invalid = [];
    $lineNo = 1;
    while (($cols = fgetcsv($handle)) !== false) {
        $lineNo++;
        if ($cols === [null] || (count($cols) === 1 && trim((string) $cols[0]) === '')) {
            continue;
        }
        $pick = static function (string $key) use ($map, $cols): string {
            if (!isset($map[$key])) {
                return '';
            }
            return trim((string) ($cols[$map[$key]] ?? ''));
        };
        $item = $pick('item');
        $rate = $pick('price_per_seedling');
        if ($item === '' && $rate === '') {
            continue;
        }
        try {
            if ($item === '' || strlen($item) > 120) {
                throw new InvalidArgumentException('Item name is missing or too long.');
            }
            $variety = $pick('variety');
            if (strlen($variety) > 160) {
                throw new InvalidArgumentException('Variety is too long.');
            }
            $rows[] = [
                'item' => $item,
                'variety' => $variety,
                'marathi_name' => $pick('marathi_name'),
                'paise' => price_rupees_to_paise($rate),
                'source' => substr($pick('source'), 0, 160),
                'sr_no' => substr($pick('sr_no'), 0, 20),
                'notes' => substr($pick('notes'), 0, 255),
                'sku' => strtoupper(substr($pick('sku'), 0, 40)),
                'line' => $lineNo,
            ];
        } catch (InvalidArgumentException $err) {
            $invalid[] = 'Line ' . $lineNo . ': ' . $err->getMessage();
        }
    }
    fclose($handle);
    return ['rows' => $rows, 'invalid' => $invalid];
}

function price_import_preview(int $listId, array $rows): array
{
    $list = db()->prepare('SELECT * FROM price_lists WHERE id = ?');
    $list->execute([$listId]);
    if (!$list->fetch()) {
        throw new RuntimeException('That price list was not found.');
    }
    $new = 0;
    $changed = 0;
    $same = 0;
    $preview = [];
    foreach ($rows as $row) {
        $sku = $row['sku'] !== '' ? $row['sku'] : '';
        $found = null;
        if ($sku !== '') {
            $stmt = db()->prepare('SELECT id, price_per_seedling_paise, active FROM price_list_items WHERE price_list_id = ? AND sku = ?');
            $stmt->execute([$listId, $sku]);
            $found = $stmt->fetch() ?: null;
        }
        if (!$found) {
            $stmt = db()->prepare('SELECT id, price_per_seedling_paise, active, sku FROM price_list_items WHERE price_list_id = ? AND item_name = ? AND variety = ?');
            $stmt->execute([$listId, $row['item'], $row['variety']]);
            $found = $stmt->fetch() ?: null;
        }
        $state = 'new';
        if ($found) {
            $state = (int) $found['price_per_seedling_paise'] === (int) $row['paise'] ? 'unchanged' : 'changed';
        }
        if ($state === 'new') {
            $new++;
        } elseif ($state === 'changed') {
            $changed++;
        } else {
            $same++;
        }
        $preview[] = $row + ['state' => $state];
    }
    return ['new' => $new, 'changed' => $changed, 'unchanged' => $same, 'rows' => $preview];
}

function price_import_apply(int $listId, array $rows, int $adminId): int
{
    $listStmt = db()->prepare('SELECT * FROM price_lists WHERE id = ?');
    $listStmt->execute([$listId]);
    $list = $listStmt->fetch();
    if (!$list) {
        throw new RuntimeException('That price list was not found.');
    }
    $farmerList = (string) $list['audience'] === 'farmer';
    $applied = 0;
    $pdo = db();
    $pdo->beginTransaction();
    try {
        foreach ($rows as $row) {
            $product = null;
            if ($row['sku'] !== '') {
                $bySku = $pdo->prepare('SELECT * FROM products WHERE sku = ?');
                $bySku->execute([$row['sku']]);
                $product = $bySku->fetch() ?: null;
            }
            if (!$product) {
                $byName = $pdo->prepare('SELECT * FROM products WHERE name = ? AND variety = ?');
                $byName->execute([$row['item'], $row['variety']]);
                $product = $byName->fetch() ?: null;
            }
            if (!$product) {
                $sku = $row['sku'] !== '' ? $row['sku'] : strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($list['slug'] . $row['sr_no'] . $row['variety'])) ?? 'PL', 0, 40));
                if ($sku === '') {
                    $sku = 'PL' . $listId . bin2hex(random_bytes(3));
                }
                $slug = slugify($row['item'] . ' ' . $row['variety']);
                if ($slug === '') {
                    $slug = 'seedling-' . bin2hex(random_bytes(3));
                }
                $taken = $pdo->prepare('SELECT id FROM products WHERE slug = ? OR sku = ?');
                $taken->execute([$slug, $sku]);
                if ($taken->fetch()) {
                    $slug .= '-' . bin2hex(random_bytes(2));
                }
                $categoryName = $row['item'];
                $cat = $pdo->prepare('SELECT id FROM categories WHERE name = ?');
                $cat->execute([$categoryName]);
                $categoryId = (int) ($cat->fetchColumn() ?: 0);
                if ($categoryId < 1) {
                    $catSlug = slugify($categoryName);
                    $pdo->prepare('INSERT INTO categories (slug, name, sort_order, active) VALUES (?, ?, 50, 0)')->execute([$catSlug !== '' ? $catSlug : 'draft-' . bin2hex(random_bytes(2)), $categoryName]);
                    $categoryId = (int) $pdo->lastInsertId();
                }
                $tray = tray_rupees((int) $row['paise'], seedlings_per_tray(null));
                $pdo->prepare('INSERT INTO products (slug, sku, name, scientific_name, variety, category_id, short_description, description, planting_info, care_info, flowering_info, colour, uses, image_url, unit_label, price_inr, stock_qty, reserved_qty, min_order, availability, season_label, bestseller, is_new, on_offer, active, farmer_only, price_per_seedling_paise) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,0,1,?,?,0,0,0,0,?,?)')
                    ->execute([
                        $slug, $sku, $row['item'], '', $row['variety'], $categoryId,
                        'Needs a photo and a description.', 'Needs a photo and a description.', '', '', '', '', '',
                        '/brand/seedling.webp', 'tray', $tray, 'in_stock', (string) ($list['name'] ?? ''),
                        $farmerList ? 1 : 0, (int) $row['paise'],
                    ]);
                $productId = (int) $pdo->lastInsertId();
                $productSku = $sku;
            } else {
                $productId = (int) $product['id'];
                $productSku = (string) $product['sku'];
            }
            $itemSku = $row['sku'] !== '' ? $row['sku'] : $productSku;
            $existing = $pdo->prepare('SELECT id, active FROM price_list_items WHERE price_list_id = ? AND sku = ?');
            $existing->execute([$listId, $itemSku]);
            $have = $existing->fetch();
            $notes = trim($row['notes'] . ($row['source'] !== '' ? ' Source: ' . $row['source'] : '') . ($row['sr_no'] !== '' ? ' Sr ' . $row['sr_no'] : ''));
            if ($have) {
                $pdo->prepare('UPDATE price_list_items SET product_id = ?, item_name = ?, variety = ?, marathi_name = ?, price_per_seedling_paise = ?, notes = ? WHERE id = ?')
                    ->execute([$productId, $row['item'], $row['variety'], $row['marathi_name'], (int) $row['paise'], substr($notes, 0, 255), (int) $have['id']]);
            } else {
                $pdo->prepare('INSERT INTO price_list_items (price_list_id, product_id, sku, item_name, variety, marathi_name, price_per_seedling_paise, visibility, active, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?)')
                    ->execute([$listId, $productId, $itemSku, $row['item'], $row['variety'], $row['marathi_name'], (int) $row['paise'], $farmerList ? 'farmers' : 'everyone', substr($notes, 0, 255)]);
            }
            $applied++;
        }
        $pdo->commit();
    } catch (Throwable $err) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $err;
    }
    audit_log($adminId, 'price_import', 'price_list', $listId, null, (string) $applied);
    return $applied;
}

