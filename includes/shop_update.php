<?php
declare(strict_types=1);

function clear_old_terms(PDO $pdo): void
{
    $text = (string) $pdo->query("SELECT `value` FROM settings WHERE `key` = 'invoice_terms'")->fetchColumn();
    if ($text !== '' && (stripos($text, 'advance') !== false) && (stripos($text, 'jurisdiction') !== false) && strlen($text) > 180) {
        $pdo->prepare('UPDATE settings SET `value` = \'\' WHERE `key` = \'invoice_terms\'')->execute();
    }
}

function apply_shop_defaults(PDO $pdo): void
{
    $flag = (string) $pdo->query("SELECT `value` FROM settings WHERE `key` = 'v2_defaults_applied'")->fetchColumn();
    if ($flag === '1') {
        clear_old_terms($pdo);
        return;
    }
    $put = $pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');
    foreach ([
        'seasonal_discount_on' => '1',
        'seasonal_discount_percent' => '10',
        'seasonal_discount_min_inr' => '5000',
        'discount_basis' => 'rupees',
        'discount_min_seedlings' => '5000',
        'discount_banner' => '',
        'low_stock_threshold' => '10',
        'default_starting_stock' => '40',
        'invoice_terms' => '',
        'prices_dispatch_note' => '',
        'container_addon_on' => '0',
        'show_seedling_count' => '1',
        'v2_defaults_applied' => '1',
        'schema_version' => '2026.10.12-final',
    ] as $key => $value) {
        $put->execute([$key, $value]);
    }
    $GLOBALS['pa_settings_dirty'] = true;
    for ($month = 1; $month <= 12; $month++) {
        $pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `key` = `key`')
            ->execute(['plant_month_' . $month, month_defaults()[$month]]);
    }
}

function fill_shop_card(PDO $pdo, int $id, array $spec): array
{
    $row = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $row->execute([$id]);
    $product = $row->fetch();
    if (!$product) {
        return ['stock_default' => false];
    }
    $stock = (int) $product['stock_qty'];
    $usedDefault = false;
    if ($stock < 1) {
        $stock = (int) ($spec['stock'] ?? default_start_stock());
        $usedDefault = true;
    }
    $copy = $spec['copy'] ?? null;
    $short = (string) $product['short_description'];
    $long = (string) $product['description'];
    $needs = (int) ($product['needs_copy'] ?? 0) === 1
        || str_contains($short, 'Contact us to confirm')
        || trim($short) === '';
    if ($needs && is_array($copy)) {
        $short = (string) $copy['short_description'];
        $long = (string) $copy['description'];
    }
    $short = scrub_tray_wording($short);
    $long = scrub_tray_wording($long);
    $plant = scrub_tray_wording((string) ($needs && is_array($copy) ? $copy['planting_info'] : $product['planting_info']));
    $care = scrub_tray_wording((string) ($needs && is_array($copy) ? $copy['care_info'] : $product['care_info']));
    $flower = scrub_tray_wording((string) ($needs && is_array($copy) ? $copy['flowering_info'] : $product['flowering_info']));
    $colour = (string) ($needs && is_array($copy) && ($product['colour'] ?? '') === '' ? $copy['colour'] : ($product['colour'] ?: ($copy['colour'] ?? '')));
    $uses = (string) ($needs && is_array($copy) && strlen((string) $product['uses']) < 3 ? $copy['uses'] : ($product['uses'] ?: ($copy['uses'] ?? '')));
    $season = (string) ($needs && is_array($copy) ? $copy['season_label'] : ($product['season_label'] ?: ($copy['season_label'] ?? '')));
    $science = (string) ($product['scientific_name'] !== '' ? $product['scientific_name'] : ($copy['scientific_name'] ?? ''));
    $image = (string) ($spec['image'] ?? $product['image_url']);
    $placeholder = (int) ($spec['placeholder'] ?? ($product['image_is_placeholder'] ?? 0));
    $root = dirname(__DIR__);
    $current = (string) $product['image_url'];
    $currentReal = $current !== '' && $current !== '/brand/seedling.webp' && is_file($root . $current);
    if ($currentReal && (int) ($product['image_is_placeholder'] ?? 0) === 0 && empty($spec['force_image'])) {
        $image = $current;
        $placeholder = 0;
    } elseif (!is_file($root . $image)) {
        $image = '/brand/seedling.webp';
        $placeholder = 1;
    }
    $flagsLocked = (string) $pdo->query("SELECT `value` FROM settings WHERE `key` = 'v2_flags_locked'")->fetchColumn() === '1';
    $isNew = $flagsLocked ? (int) $product['is_new'] : (int) ($spec['is_new'] ?? $product['is_new']);
    $best = $flagsLocked ? (int) $product['bestseller'] : (int) ($spec['bestseller'] ?? $product['bestseller']);
    $purposes = trim((string) ($product['purposes'] ?? '')) !== '' && $flagsLocked
        ? (string) $product['purposes']
        : (string) ($spec['purposes'] ?? purpose_for_slug((string) $product['slug']));
    $pdo->prepare('UPDATE products SET variety=?, price_inr=?, price_per_seedling_paise=?, tray_paise=?, unit_label=?, marathi_name=IF(marathi_name=\'\' OR ?, ?, marathi_name), active=?, list_only=?, farmer_only=?, needs_copy=0, stock_qty=?, availability=?, image_url=?, image_is_placeholder=?, short_description=?, description=?, planting_info=?, care_info=?, flowering_info=?, colour=?, uses=?, season_label=?, scientific_name=?, purposes=?, is_new=?, bestseller=? WHERE id=?')
        ->execute([
            substr((string) ($spec['variety'] ?? $product['variety']), 0, 160),
            (int) $spec['rupees'],
            (int) $spec['paise'],
            (int) $spec['tray_paise'],
            'tray (150 seedlings)',
            !empty($spec['marathi']) ? 1 : 0,
            substr((string) ($spec['marathi'] ?? ''), 0, 160),
            (int) ($spec['active'] ?? 1),
            (int) ($spec['list_only'] ?? 0),
            (int) ($spec['farmer_only'] ?? 0),
            $stock,
            'in_stock',
            $image,
            $placeholder,
            substr($short, 0, 790),
            $long,
            $plant,
            $care,
            $flower,
            substr($colour, 0, 160),
            substr($uses, 0, 400),
            substr($season, 0, 160),
            substr($science, 0, 160),
            substr($purposes, 0, 240),
            $isNew,
            $best,
            $id,
        ]);
    return ['stock_default' => $usedDefault, 'slug' => (string) $product['slug']];
}

function ensure_card(PDO $pdo, array $spec): array
{
    $find = $pdo->prepare('SELECT * FROM products WHERE slug = ? OR sku = ? LIMIT 1');
    $find->execute([(string) $spec['slug'], (string) $spec['sku']]);
    $existing = $find->fetch() ?: null;
    if ($existing) {
        $filled = fill_shop_card($pdo, (int) $existing['id'], $spec);
        if (array_key_exists('parent_id', $spec)) {
            $pdo->prepare('UPDATE products SET parent_product_id = ? WHERE id = ?')->execute([$spec['parent_id'], (int) $existing['id']]);
        }
        return ['id' => (int) $existing['id'], 'action' => 'updated', 'slug' => (string) $existing['slug'], 'stock_default' => $filled['stock_default']];
    }
    $copy = $spec['copy'];
    $pdo->prepare('INSERT INTO products (slug, sku, name, scientific_name, variety, category_id, short_description, description, planting_info, care_info, flowering_info, colour, uses, image_url, unit_label, price_inr, stock_qty, reserved_qty, min_order, availability, season_label, bestseller, is_new, on_offer, active, farmer_only, price_per_seedling_paise, tray_paise, parent_product_id, marathi_name, needs_copy, image_is_placeholder, purposes, list_only) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,1,?,?,?,?,0,?,?,?,?,?,?,0,?,?,?)')
        ->execute([
            $spec['slug'],
            $spec['sku'],
            substr((string) $spec['name'], 0, 120),
            substr((string) $copy['scientific_name'], 0, 160),
            substr((string) $spec['variety'], 0, 160),
            (int) $spec['category_id'],
            substr((string) $copy['short_description'], 0, 790),
            $copy['description'],
            $copy['planting_info'],
            $copy['care_info'],
            $copy['flowering_info'],
            substr((string) $copy['colour'], 0, 160),
            substr((string) $copy['uses'], 0, 400),
            $spec['image'],
            'tray (150 seedlings)',
            (int) $spec['rupees'],
            (int) $spec['stock'],
            'in_stock',
            substr((string) $copy['season_label'], 0, 160),
            (int) ($spec['bestseller'] ?? 0),
            (int) ($spec['is_new'] ?? 0),
            (int) ($spec['active'] ?? 1),
            (int) ($spec['farmer_only'] ?? 0),
            (int) $spec['paise'],
            (int) $spec['tray_paise'],
            $spec['parent_id'] ?? null,
            substr((string) ($spec['marathi'] ?? ''), 0, 160),
            (int) ($spec['placeholder'] ?? 0),
            substr((string) ($spec['purposes'] ?? ''), 0, 240),
            (int) ($spec['list_only'] ?? 0),
        ]);
    return ['id' => (int) $pdo->lastInsertId(), 'action' => 'created', 'slug' => (string) $spec['slug'], 'stock_default' => true];
}

function show_plant_in_shop(PDO $pdo, int $id, int $adminId): void
{
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) {
        throw new RuntimeException('That plant is not on the list.');
    }
    $slug = (string) $product['slug'];
    $copy = plant_copy($slug, (string) $product['name']);
    $image = (string) $product['image_url'];
    $placeholder = (int) ($product['image_is_placeholder'] ?? 1);
    if ($image === '' || !is_file(dirname(__DIR__) . $image)) {
        $image = '/products/' . $slug . '.webp';
        $placeholder = 1;
    }
    $stock = (int) $product['stock_qty'] > 0 ? (int) $product['stock_qty'] : default_start_stock();
    $pdo->prepare('UPDATE products SET active=1, list_only=0, needs_copy=0, stock_qty=?, availability=\'in_stock\', image_url=?, image_is_placeholder=?, short_description=IF(short_description=\'\' OR needs_copy=1, ?, short_description), description=IF(description=\'\' OR needs_copy=1, ?, description), purposes=IF(purposes=\'\', ?, purposes) WHERE id=?')
        ->execute([$stock, $image, $placeholder, $copy['short_description'], $copy['description'], purpose_for_slug($slug), $id]);
    audit_log($adminId, 'show_in_shop', 'product', $id, 'hidden', 'shown');
}

function backup_shop_tables(PDO $pdo): string
{
    $dir = dirname(__DIR__) . '/uploads/private';
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        return 'Backup folder could not be created.';
    }
    $file = $dir . '/backup-before-update-' . date('Ymd-His') . '.csv';
    $out = fopen($file, 'wb');
    if ($out === false) {
        return 'Backup file could not be written.';
    }
    fwrite($out, "table,payload\n");
    foreach (['settings', 'products'] as $table) {
        $rows = $pdo->query('SELECT * FROM ' . $table)->fetchAll();
        fputcsv($out, [$table, json_encode($rows, JSON_UNESCAPED_UNICODE)]);
    }
    fclose($out);
    return 'Backup saved.';
}

function run_shop_update(PDO $pdo, bool $force = false): array
{
    require_once __DIR__ . '/migrate.php';
    $locked = $pdo->query("SELECT GET_LOCK('pa_shop_update', 30)")->fetchColumn();
    if ((string) $locked !== '1') {
        throw new RuntimeException('Another update is already running. Wait a minute and try again.');
    }
    try {
        $backup = 'Backup skipped.';
        $done = (string) $pdo->query("SELECT `value` FROM settings WHERE `key` = 'v2_backup_done'")->fetchColumn();
        if ($done !== '1') {
            $backup = backup_shop_tables($pdo);
            $pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (\'v2_backup_done\', \'1\') ON DUPLICATE KEY UPDATE `value` = \'1\'')->execute();
        }
        $upgrade = shop_upgrade($pdo);
        apply_shop_defaults($pdo);
        $loaded = load_supplied_lists($pdo);
        load_business_settings($pdo);
        clear_old_terms($pdo);
        $pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (\'lists_loaded\', \'1\') ON DUPLICATE KEY UPDATE `value` = \'1\'')->execute();
        $pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (\'schema_version\', \'2026.10.12-final\') ON DUPLICATE KEY UPDATE `value` = \'2026.10.12-final\'')->execute();
        return ['ok' => true, 'backup' => $backup, 'upgrade' => count($upgrade), 'loaded' => $loaded];
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('pa_shop_update')");
    }
}

function maybe_auto_update(): void
{
    if (PHP_SAPI === 'cli' || (($_SESSION['role'] ?? '') !== 'admin')) {
        return;
    }
    try {
        $version = setting('schema_version', '');
        $loaded = setting('lists_loaded', '0');
        if ($version === '2026.10.12-final' && $loaded === '1' && schema_ready()) {
            return;
        }
        run_shop_update(db());
    } catch (Throwable $err) {
        error_log('Auto update failed: ' . $err->getMessage());
        $_SESSION['update_error'] = 'The shop update did not finish. Open Update everything and try the button.';
    }
}

function admin_help_text(): string
{
    $file = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $map = [
        'index.php' => 'Today shows what needs a person. Open a card to see that list.',
        'stock.php' => 'Type the new tray count, or use plus and minus, then Save all.',
        'prices.php' => 'Change the rate per seedling. The tray price updates before you save.',
        'photos.php' => 'Name each file with the plant slug, for example cosmos.jpg, then upload.',
        'orders.php' => 'Search by name, mobile or order number. Open an order to change its status.',
        'help.php' => 'Short steps for the jobs staff do most often.',
        'update.php' => 'One button loads the price sheets and the database changes. It is safe to press again.',
        'attention.php' => 'These plants are missing something. Use the button on the row to fix it.',
        'finder.php' => 'Search for the job, then open the page.',
        'months.php' => 'Type the plants for each month. They show on the homepage.',
        'labels.php' => 'These words are the shop buttons. Leave a box empty to keep the default.',
        'settings.php' => 'Each box has a plain label. Save at the bottom of the group you changed.',
        'products.php' => 'Hide a plant to take it off the shop. Restore brings it back.',
        'price-lists.php' => 'Seasonal rates are public. Farmer rates show only for a customer marked Farmer.',
        'invoices.php' => 'Turn the bulk discount on or off here. Empty terms show nothing on the invoice.',
        'restock.php' => 'People who asked to hear when a plant is back.',
    ];
    return $map[$file] ?? '';
}

function order_plain_status(string $status): string
{
    return match ($status) {
        'placed', 'payment_pending' => 'Received',
        'payment_submitted' => 'Payment sent, waiting for the nursery to confirm',
        'payment_confirmed', 'confirmed' => 'Payment confirmed',
        'preparing', 'packed' => 'Packed',
        'out_for_delivery', 'dispatched' => 'Dispatched',
        'delivered' => 'Delivered',
        'ready_for_pickup' => 'Ready for pickup',
        'cancelled' => 'Cancelled',
        default => ucfirst(str_replace('_', ' ', $status)),
    };
}

