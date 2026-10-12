<?php
declare(strict_types=1);

function exact_tray_paise(int $seedlingPaise, int $seedlings): int
{
    if ($seedlingPaise < 0 || $seedlings < 1) {
        throw new InvalidArgumentException('Tray price needs a seedling rate and a tray size.');
    }
    return $seedlingPaise * $seedlings;
}

function inr_paise(int $paise): string
{
    $sign = $paise < 0 ? '-' : '';
    $paise = abs($paise);
    $rupees = intdiv($paise, 100);
    $rest = $paise % 100;
    $text = number_format($rupees);
    if ($rest !== 0) {
        $text .= '.' . str_pad((string) $rest, 2, '0', STR_PAD_LEFT);
    }
    return $sign . '₹' . $text;
}

function seedling_rate_text(?int $paise): string
{
    if ($paise === null || $paise < 0) {
        return '';
    }
    return '₹' . number_format($paise / 100, 2, '.', '');
}

function price_pair_text(array $sell): string
{
    if (!empty($sell['blocked'])) {
        return 'Price on request';
    }
    $trayPaise = (int) ($sell['tray_paise'] ?? ((int) ($sell['rupees'] ?? 0) * 100));
    $tray = (int) ($sell['tray'] ?? 150);
    if (empty($sell['paise'])) {
        return inr_paise($trayPaise) . ' per tray';
    }
    $text = inr_paise($trayPaise) . ' per tray (' . $tray . ' seedlings)';
    if (!empty($sell['paise'])) {
        $text = seedling_rate_text((int) $sell['paise']) . ' per seedling · ' . $text;
    }
    return $text;
}

function amount_paise_words(int $paise): string
{
    $sign = '';
    if ($paise < 0) {
        $sign = 'Minus ';
        $paise = -$paise;
    }
    $rupees = intdiv($paise, 100);
    $rest = $paise % 100;
    $words = amount_in_words($rupees);
    if ($rest === 0) {
        return $sign . $words;
    }
    $words = preg_replace('/ rupees only$/', '', $words) ?? $words;
    $words = preg_replace('/ rupee only$/', '', $words) ?? $words;
    return $sign . $words . ' rupees and ' . $rest . ' paise only';
}

function can_see_farmer(): bool
{
    if (customer_is_farmer()) {
        return true;
    }
    return (($_SESSION['role'] ?? '') === 'admin');
}

function catalog_card_sql(): string
{
    return catalog_public_sql() . ' AND p.parent_product_id IS NULL';
}

function variety_family(array $product): array
{
    $id = (int) ($product['id'] ?? 0);
    $parent = (int) ($product['parent_product_id'] ?? 0);
    $root = $parent > 0 ? $parent : $id;
    if ($root < 1) {
        return [];
    }
    $see = can_see_farmer() ? 1 : 0;
    $stmt = db()->prepare(
        'SELECT p.* FROM products p
         JOIN categories c ON c.id = p.category_id
         WHERE p.active = 1 AND c.active = 1 AND (p.farmer_only = 0 OR ? = 1)
           AND (p.id = ? OR p.parent_product_id = ?)
         ORDER BY (p.parent_product_id IS NULL) DESC, p.name, p.id'
    );
    $stmt->execute([$see, $root, $root]);
    return $stmt->fetchAll();
}

function business_details(): array
{
    $path = dirname(__DIR__) . '/data/business-details-from-pdf.json';
    $raw = is_file($path) ? file_get_contents($path) : false;
    $data = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($data) ? $data : [];
}

function sheet_csv(string $name): array
{
    $path = dirname(__DIR__) . '/data/' . $name;
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('Missing ' . $name);
    }
    $header = fgetcsv($handle);
    if (!is_array($header)) {
        fclose($handle);
        throw new RuntimeException($name . ' has no header.');
    }
    $header = array_map(static fn ($col) => trim((string) $col), $header);
    $rows = [];
    while (($cols = fgetcsv($handle)) !== false) {
        if ($cols === [null] || $cols === []) {
            continue;
        }
        $row = [];
        foreach ($header as $i => $key) {
            $row[$key] = trim((string) ($cols[$i] ?? ''));
        }
        if (($row['item'] ?? '') === '' && ($row['variety'] ?? '') === '') {
            continue;
        }
        $rows[] = $row;
    }
    fclose($handle);
    return $rows;
}

function list_slug(string $text, int $max = 70): string
{
    $slug = slugify($text);
    if ($slug === '') {
        $slug = 'plant';
    }
    return substr($slug, 0, $max);
}

function category_id_for(PDO $pdo, string $name): int
{
    $map = [
        'Bedding annuals' => ['bedding', 'Bedding annuals'],
        'Winter colour' => ['winter', 'Winter colour'],
        'Shade beds' => ['shade', 'Shade beds'],
        'Specialty' => ['specialty', 'Specialty'],
        'Herbs and fruit' => ['herbs', 'Herbs and fruit'],
        'Marigold and Chrysanthemum' => ['marigold', 'Marigold and Chrysanthemum'],
    ];
    [$slug, $label] = $map[$name] ?? ['bedding', 'Bedding annuals'];
    $find = $pdo->prepare('SELECT id FROM categories WHERE slug = ?');
    $find->execute([$slug]);
    $id = (int) $find->fetchColumn();
    if ($id > 0) {
        return $id;
    }
    $sort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM categories')->fetchColumn();
    $pdo->prepare('INSERT INTO categories (slug, name, sort_order, active) VALUES (?, ?, ?, 1)')->execute([$slug, $label, $sort]);
    return (int) $pdo->lastInsertId();
}

function ensure_product_slug(PDO $pdo, string $base): string
{
    $slug = list_slug($base, 80);
    $try = $slug;
    $n = 2;
    $find = $pdo->prepare('SELECT id FROM products WHERE slug = ?');
    while (true) {
        $find->execute([$try]);
        if (!$find->fetchColumn()) {
            return $try;
        }
        $try = substr($slug, 0, 70) . '-' . $n;
        $n++;
    }
}

function sheet_copy(string $plant, string $variety, int $paise, int $tray): string
{
    $name = $variety !== '' ? $plant . ', ' . $variety : $plant;
    $short = $name . '. ' . seedling_rate_text($paise) . ' per seedling. ' . inr_paise(exact_tray_paise($paise, $tray)) . ' per tray of ' . $tray . ' seedlings. Contact us to confirm availability.';
    return strlen($short) > 790 ? substr($short, 0, 790) : $short;
}

function upsert_sheet_product(PDO $pdo, array $spec): array
{
    $sku = substr((string) $spec['sku'], 0, 40);
    $find = $pdo->prepare('SELECT * FROM products WHERE sku = ?');
    $find->execute([$sku]);
    $existing = $find->fetch() ?: null;
    $tray = (int) $spec['tray'];
    $paise = (int) $spec['paise'];
    $trayPaise = exact_tray_paise($paise, $tray);
    $rupees = tray_rupees($paise, $tray);
    if ($existing && !empty($spec['keep_identity'])) {
        $pdo->prepare('UPDATE products SET variety = ?, price_inr = ?, price_per_seedling_paise = ?, tray_paise = ?, unit_label = ? WHERE id = ?')
            ->execute([
                substr((string) $spec['variety'], 0, 160),
                $rupees,
                $paise,
                $trayPaise,
                'tray (' . $tray . ' seedlings)',
                (int) $existing['id'],
            ]);
        return ['id' => (int) $existing['id'], 'action' => 'matched', 'old' => (int) $existing['price_inr'], 'new' => $rupees, 'tray_paise' => $trayPaise, 'slug' => (string) $existing['slug']];
    }
    if ($existing) {
        $pdo->prepare('UPDATE products SET name = ?, variety = ?, price_inr = ?, price_per_seedling_paise = ?, tray_paise = ?, marathi_name = ?, parent_product_id = ?, farmer_only = ?, unit_label = ?, active = 1 WHERE id = ?')
            ->execute([
                substr((string) $spec['name'], 0, 120),
                substr((string) $spec['variety'], 0, 160),
                $rupees,
                $paise,
                $trayPaise,
                substr((string) ($spec['marathi'] ?? ''), 0, 160),
                $spec['parent_id'] ?? null,
                (int) ($spec['farmer_only'] ?? 0),
                'tray (' . $tray . ' seedlings)',
                (int) $existing['id'],
            ]);
        return ['id' => (int) $existing['id'], 'action' => 'updated', 'old' => (int) $existing['price_inr'], 'new' => $rupees, 'tray_paise' => $trayPaise, 'slug' => (string) $existing['slug']];
    }
    $copy = sheet_copy((string) $spec['name'], (string) $spec['variety'], $paise, $tray);
    $slug = ensure_product_slug($pdo, (string) $spec['slug']);
    $pdo->prepare('INSERT INTO products (slug, sku, name, scientific_name, variety, category_id, short_description, description, planting_info, care_info, flowering_info, colour, uses, image_url, unit_label, price_inr, stock_qty, reserved_qty, min_order, availability, season_label, bestseller, is_new, on_offer, active, farmer_only, price_per_seedling_paise, tray_paise, parent_product_id, marathi_name, needs_copy) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,0,1,?,?,0,0,0,1,?,?,?,?,?,1)')
        ->execute([
            $slug,
            $sku,
            substr((string) $spec['name'], 0, 120),
            '',
            substr((string) $spec['variety'], 0, 160),
            (int) $spec['category_id'],
            $copy,
            $copy,
            'Contact us to confirm availability.',
            'Contact us to confirm availability.',
            'Contact us to confirm availability.',
            '',
            'Contact us to confirm availability.',
            '/brand/seedling.webp',
            'tray (' . $tray . ' seedlings)',
            $rupees,
            'on_request',
            '2026-27',
            (int) ($spec['farmer_only'] ?? 0),
            $paise,
            $trayPaise,
            $spec['parent_id'] ?? null,
            substr((string) ($spec['marathi'] ?? ''), 0, 160),
        ]);
    return ['id' => (int) $pdo->lastInsertId(), 'action' => 'created', 'old' => null, 'new' => $rupees, 'tray_paise' => $trayPaise, 'slug' => $slug];
}

function upsert_list_item(PDO $pdo, int $listId, array $row, int $productId, string $sku, string $audience): void
{
    $visibility = $audience === 'farmer' ? 'farmer' : 'everyone';
    $stmt = $pdo->prepare('SELECT id FROM price_list_items WHERE price_list_id = ? AND sku = ?');
    $stmt->execute([$listId, $sku]);
    $id = (int) $stmt->fetchColumn();
    $item = substr((string) $row['item'], 0, 120);
    $variety = substr((string) ($row['variety'] ?? ''), 0, 160);
    $marathi = substr((string) ($row['marathi_name'] ?? ''), 0, 160);
    $paise = price_rupees_to_paise((string) $row['price_per_seedling']);
    $notes = substr((string) ($row['notes'] ?? ''), 0, 255);
    if ($id > 0) {
        $pdo->prepare('UPDATE price_list_items SET product_id=?, item_name=?, variety=?, marathi_name=?, price_per_seedling_paise=?, seedlings_per_tray=150, notes=?, visibility=?, active=1 WHERE id=?')
            ->execute([$productId, $item, $variety, $marathi, $paise, $notes, $visibility, $id]);
        return;
    }
    $pdo->prepare('INSERT INTO price_list_items (price_list_id, product_id, sku, item_name, variety, marathi_name, price_per_seedling_paise, seedlings_per_tray, visibility, active, notes) VALUES (?,?,?,?,?,?,?,150,?,1,?)')
        ->execute([$listId, $productId, $sku, $item, $variety, $marathi, $paise, $visibility, $notes]);
}

function load_supplied_lists(PDO $pdo): array
{
    $tray = 150;
    $report = [];
    $seasonalId = (int) $pdo->query("SELECT id FROM price_lists WHERE slug = 'seasonal-2026-27'")->fetchColumn();
    $farmerId = (int) $pdo->query("SELECT id FROM price_lists WHERE slug = 'farmer'")->fetchColumn();
    if ($seasonalId < 1 || $farmerId < 1) {
        throw new RuntimeException('Price lists are missing. Run the database update first.');
    }
    $pdo->prepare("UPDATE price_lists SET effective_from = '2026-04-01', effective_to = '2027-03-31', active = 1 WHERE slug = 'seasonal-2026-27'")->execute();
    $pdo->prepare("UPDATE price_lists SET effective_from = '2026-01-01', effective_to = NULL, active = 1 WHERE slug = 'farmer'")->execute();
    $parentVariety = [
        'antirrhinum' => 'Chimes Mix',
        'begonia' => 'Bada Boom/Bing Mix',
        'celosia' => 'Kimono Mix',
        'chrysanthemum' => 'Deconova Grandiflora',
        'cineraria' => 'Silver Dust',
        'dianthus' => 'Ideal Mix',
        'gazania' => 'Day Break/Kiss Mix',
        'geranium' => 'Ringo 2000/Bulls Eye Mix',
        'impatiens' => 'Imara XDR Mix',
        'lobelia' => 'Crystal',
        'marigold' => 'Inca - Orange / Yellow / Gold',
        'pansy' => 'Super Majestic Giants 2',
        'pentas' => 'Butterfly / Starla',
        'petunia' => 'Bravo/Supersascade Formula Mix',
        'platycodon' => 'Pop Star Mix',
        'salvia' => 'Flamex 2000 / Vista Red / Sahara Red',
        'sunflower' => 'Teddy Bear',
        'vinca' => 'Pacifica Mix',
        'zinnia' => 'Zahara / Zydeco / Profusion Double',
    ];
    $groups = [];
    foreach (sheet_csv('price-list-2026-27.csv') as $row) {
        $key = (string) (($row['plant_group'] ?? '') !== '' ? $row['plant_group'] : $row['item']);
        $groups[$key][] = $row;
    }
    $matchedParents = 0;
    $createdParents = 0;
    foreach ($groups as $plant => $rows) {
        usort($rows, static fn ($a, $b) => ((int) $a['sr_no']) <=> ((int) $b['sr_no']));
        $existingSlug = '';
        foreach ($rows as $row) {
            if (($row['existing_slug'] ?? '') !== '') {
                $existingSlug = (string) $row['existing_slug'];
                break;
            }
        }
        $wanted = $parentVariety[$existingSlug] ?? '';
        $parentRow = $rows[0];
        foreach ($rows as $row) {
            if ($wanted !== '' && $row['variety'] === $wanted) {
                $parentRow = $row;
                break;
            }
        }
        $paise = price_rupees_to_paise((string) $parentRow['price_per_seedling']);
        if ($existingSlug !== '') {
            $found = $pdo->prepare('SELECT * FROM products WHERE slug = ?');
            $found->execute([$existingSlug]);
            $product = $found->fetch();
            if (!$product) {
                throw new RuntimeException('Existing plant missing: ' . $existingSlug);
            }
            $saved = upsert_sheet_product($pdo, [
                'sku' => (string) $product['sku'],
                'keep_identity' => true,
                'variety' => (string) $parentRow['variety'],
                'paise' => $paise,
                'tray' => $tray,
            ]);
            $matchedParents++;
            $parentId = $saved['id'];
            $parentSku = (string) $product['sku'];
            $report[] = ['list' => 'seasonal', 'plant' => $plant, 'variety' => $parentRow['variety'], 'sr' => $parentRow['sr_no'], 'action' => 'matched-parent', 'slug' => $existingSlug, 'old_inr' => $saved['old'], 'new_inr' => $saved['new'], 'tray_paise' => $saved['tray_paise'], 'sku' => $parentSku];
        } else {
            $sku = substr('S26-' . list_slug($plant, 30), 0, 40);
            $saved = upsert_sheet_product($pdo, [
                'sku' => $sku,
                'slug' => list_slug($plant),
                'name' => $plant,
                'variety' => (string) $parentRow['variety'],
                'paise' => $paise,
                'tray' => $tray,
                'category_id' => category_id_for($pdo, (string) ($parentRow['category'] ?? '')),
                'farmer_only' => 0,
                'parent_id' => null,
            ]);
            if ($saved['action'] === 'created') {
                $createdParents++;
            }
            $parentId = $saved['id'];
            $parentSku = $sku;
            $report[] = ['list' => 'seasonal', 'plant' => $plant, 'variety' => $parentRow['variety'], 'sr' => $parentRow['sr_no'], 'action' => $saved['action'] . '-parent', 'slug' => $saved['slug'], 'old_inr' => $saved['old'], 'new_inr' => $saved['new'], 'tray_paise' => $saved['tray_paise'], 'sku' => $sku];
        }
        upsert_list_item($pdo, $seasonalId, $parentRow, $parentId, $parentSku, 'seasonal');
        $n = 1;
        foreach ($rows as $row) {
            if ($row['variety'] === $parentRow['variety'] && $row['sr_no'] === $parentRow['sr_no'] && $row['item'] === $parentRow['item']) {
                continue;
            }
            $childSku = substr('S26-' . list_slug($plant, 16) . '-' . str_pad((string) $row['sr_no'], 2, '0', STR_PAD_LEFT) . '-' . $n, 0, 40);
            $n++;
            $saved = upsert_sheet_product($pdo, [
                'sku' => $childSku,
                'slug' => list_slug($plant . ' ' . $row['variety']),
                'name' => $plant,
                'variety' => (string) $row['variety'],
                'paise' => price_rupees_to_paise((string) $row['price_per_seedling']),
                'tray' => $tray,
                'category_id' => category_id_for($pdo, (string) ($row['category'] ?? '')),
                'farmer_only' => 0,
                'parent_id' => $parentId,
            ]);
            upsert_list_item($pdo, $seasonalId, $row, $saved['id'], $childSku, 'seasonal');
            $report[] = ['list' => 'seasonal', 'plant' => $plant, 'variety' => $row['variety'], 'sr' => $row['sr_no'], 'action' => $saved['action'] . '-child', 'slug' => $saved['slug'], 'old_inr' => $saved['old'], 'new_inr' => $saved['new'], 'tray_paise' => $saved['tray_paise'], 'sku' => $childSku];
        }
    }
    $farmerParents = [];
    foreach (['Marigold' => ['farmer-marigold', 'F26-marigold', 'झेंडू'], 'Chrysanthemum' => ['farmer-chrysanthemum', 'F26-chrysanthemum', 'शेवंती']] as $item => $meta) {
        $saved = upsert_sheet_product($pdo, [
            'sku' => $meta[1],
            'slug' => $meta[0],
            'name' => $item . ' farmer rates',
            'variety' => 'Farmer list from 01.01.2026',
            'paise' => 300,
            'tray' => $tray,
            'category_id' => category_id_for($pdo, 'Marigold and Chrysanthemum'),
            'farmer_only' => 1,
            'parent_id' => null,
            'marathi' => $meta[2],
        ]);
        $farmerParents[$item] = $saved['id'];
    }
    $farmerN = 0;
    foreach (sheet_csv('price-list-farmer.csv') as $row) {
        $farmerN++;
        $item = (string) $row['item'];
        $sku = substr('F26-' . list_slug($item, 8) . '-' . str_pad((string) $row['sr_no'], 2, '0', STR_PAD_LEFT), 0, 40);
        $saved = upsert_sheet_product($pdo, [
            'sku' => $sku,
            'slug' => 'farmer-' . list_slug($item . ' ' . $row['variety']),
            'name' => $item,
            'variety' => (string) $row['variety'],
            'paise' => price_rupees_to_paise((string) $row['price_per_seedling']),
            'tray' => $tray,
            'category_id' => category_id_for($pdo, 'Marigold and Chrysanthemum'),
            'farmer_only' => 1,
            'parent_id' => $farmerParents[$item] ?? null,
            'marathi' => (string) ($row['marathi_name'] ?? ''),
        ]);
        upsert_list_item($pdo, $farmerId, $row, $saved['id'], $sku, 'farmer');
        $report[] = ['list' => 'farmer', 'plant' => $item, 'variety' => $row['variety'], 'sr' => $row['sr_no'], 'marathi' => $row['marathi_name'] ?? '', 'action' => $saved['action'] . '-farmer', 'slug' => $saved['slug'], 'old_inr' => $saved['old'], 'new_inr' => $saved['new'], 'tray_paise' => $saved['tray_paise'], 'sku' => $sku];
    }
    return ['rows' => $report, 'matched_parents' => $matchedParents, 'new_parents' => $createdParents, 'farmer_rows' => $farmerN, 'groups' => count($groups)];
}

function load_business_settings(PDO $pdo): void
{
    $data = business_details();
    $phones = $data['phones'] ?? ['8080370152', '8421577787', '8421577725'];
    $labels = ['Nursery', 'Office', 'Desk'];
    $ins = $pdo->prepare('INSERT INTO phone_numbers (digits, label, sort_order, enabled, show_public, whatsapp_enabled) SELECT ?, ?, ?, 1, 1, 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM phone_numbers WHERE digits = ?)');
    foreach (array_values($phones) as $i => $digits) {
        $digits = preg_replace('/\D+/', '', (string) $digits) ?? '';
        if (strlen($digits) !== 10) {
            continue;
        }
        $ins->execute([$digits, $labels[$i] ?? 'Phone', $i + 1, $digits]);
    }
    $pdo->exec("UPDATE settings SET `value` = '8080370152' WHERE `key` = 'shop_phone' AND `value` IN ('9011975959', '919011975959', '')");
    $pdo->exec("UPDATE settings SET `value` = '918080370152' WHERE `key` = 'whatsapp_number' AND `value` IN ('9011975959', '919011975959', '')");
    $pdo->exec("UPDATE settings SET `value` = '8080370152' WHERE `key` = 'grievance_officer_phone' AND `value` IN ('9011975959', '919011975959')");
    $put = $pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `key` = `key`');
    foreach ([
        'whatsapp_mode' => 'random',
        'whatsapp_fixed_id' => '0',
        'whatsapp_rr' => '0',
        'seasonal_list_public' => '1',
        'show_marathi_names' => '1',
        'shop_tagline' => (string) ($data['tagline'] ?? ''),
        'footer_line' => (string) ($data['footer'] ?? ''),
    ] as $key => $value) {
        $put->execute([$key, $value]);
    }
    $legal = (string) ($data['company'] ?? '');
    if ($legal !== '') {
        $pdo->prepare("UPDATE settings SET `value` = ? WHERE `key` = 'seller_legal_name' AND `value` IN ('', 'Precision Agritech Private Limited', 'Precision Agritech')")->execute([$legal]);
        $put->execute(['seller_legal_name', $legal]);
    }
    $terms = $data['terms'] ?? [];
    if (is_array($terms) && $terms) {
        $text = implode("\n", array_map(static fn ($line) => trim((string) $line), $terms));
        $old = 'Confirmation in writing or by phone with a minimum 50 percent advance. The advance covers box packing, transport and freight. Discount is not applicable on packaging and transportation. The price at dispatch is final. A variety may be replaced with a suitable alternative. Packing and transport are extra at actual cost. Prices are subject to change. Disputes are under Pune jurisdiction.';
        $pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (\'invoice_terms\', ?) ON DUPLICATE KEY UPDATE `value` = IF(`value` IN (\'\', ?), VALUES(`value`), `value`)')->execute([$text, $old]);
    }
    $bankIns = $pdo->prepare('INSERT INTO bank_accounts (bank, branch, account_name, account_no, ifsc, sort_order, verified, show_public) SELECT ?, ?, ?, ?, ?, ?, 0, 0 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM bank_accounts WHERE account_no = ?)');
    foreach (array_values($data['banks'] ?? []) as $i => $bank) {
        if (!is_array($bank)) {
            continue;
        }
        $no = preg_replace('/\D+/', '', (string) ($bank['account_no'] ?? '')) ?? '';
        if ($no === '') {
            continue;
        }
        $bankIns->execute([
            substr((string) ($bank['bank'] ?? ''), 0, 80),
            substr((string) ($bank['branch'] ?? ''), 0, 80),
            substr((string) ($bank['account_name'] ?? ''), 0, 120),
            $no,
            strtoupper(substr((string) ($bank['ifsc'] ?? ''), 0, 20)),
            $i + 1,
            $no,
        ]);
    }
}

function public_bank_accounts(): array
{
    try {
        return db()->query('SELECT bank, branch, account_name, account_no, ifsc FROM bank_accounts WHERE verified = 1 AND show_public = 1 ORDER BY sort_order, id')->fetchAll();
    } catch (Throwable $err) {
        return [];
    }
}

function invoice_term_lines(): array
{
    $text = trim(setting('invoice_terms', ''));
    if ($text === '') {
        return [];
    }
    $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
    return array_values(array_filter(array_map('trim', $lines), static fn ($line) => $line !== ''));
}

function schema_ready(): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $cols = db()->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME IN ('farmer_only', 'parent_product_id')")->fetchAll(PDO::FETCH_COLUMN);
        $table = db()->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'phone_numbers'")->fetchColumn();
        $ok = count($cols) === 2 && (bool) $table;
    } catch (Throwable $err) {
        $ok = true;
    }
    return $ok;
}

function schema_guard(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    if (str_contains($script, 'migrate.php') || str_contains($script, 'setup.php')) {
        return;
    }
    if (schema_ready()) {
        return;
    }
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    $staff = (($_SESSION['role'] ?? '') === 'admin');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Precision Agritech</title></head><body style="font-family:sans-serif;margin:2rem;line-height:1.5">';
    if ($staff) {
        echo '<h1>Database update needed</h1><p>The shop database is missing the latest columns. Open the database update page and run it. It is safe to run again.</p><p><a href="/admin/migrate">Open database update</a></p>';
    } else {
        echo '<h1>We are updating the shop</h1><p>Please check back shortly.</p><p>You can call <a href="tel:+918080370152">8080370152</a>, <a href="tel:+918421577787">8421577787</a> or <a href="tel:+918421577725">8421577725</a>.</p>';
    }
    echo '</body></html>';
    exit;
}
