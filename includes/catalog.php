<?php
declare(strict_types=1);

function shop_new_slugs(): array
{
    return ['cosmos', 'aster', 'calendula', 'dahlia', 'verbena', 'portulaca', 'phlox', 'torenia', 'gerbera', 'alyssum'];
}

function bestseller_slugs(): array
{
    return ['marigold', 'petunia', 'zinnia', 'sunflower', 'salvia', 'vinca', 'impatiens', 'geranium'];
}

function purpose_choices(): array
{
    return [
        'beds' => 'Beds and borders',
        'pots' => 'Pots and balconies',
        'garlands' => 'Garlands and cut flowers',
        'shade' => 'Shade',
        'winter' => 'Winter colour',
        'summer' => 'Summer colour',
    ];
}

function purpose_for_slug(string $slug): string
{
    $map = [
        'marigold' => 'beds,garlands,summer',
        'petunia' => 'pots,beds,winter',
        'vinca' => 'beds,summer',
        'pansy' => 'beds,pots,winter',
        'geranium' => 'pots,winter',
        'zinnia' => 'beds,garlands,summer',
        'celosia' => 'beds,garlands,summer',
        'sunflower' => 'beds,garlands,summer',
        'antirrhinum' => 'beds,garlands,winter',
        'dianthus' => 'beds,pots,winter',
        'cineraria' => 'pots,shade,winter',
        'lobelia' => 'pots,winter',
        'gazania' => 'beds,pots,winter',
        'salvia' => 'beds,winter,summer',
        'begonia' => 'shade,beds,pots',
        'impatiens' => 'shade,beds,pots',
        'chrysanthemum' => 'pots,garlands,winter',
        'pentas' => 'beds,summer',
        'platycodon' => 'beds,pots',
        'ptilotus' => 'beds,pots',
        'cosmos' => 'beds,garlands,summer',
        'aster' => 'beds,garlands,winter',
        'calendula' => 'beds,pots,winter',
        'dahlia' => 'beds,garlands,winter',
        'verbena' => 'beds,pots,summer',
        'portulaca' => 'pots,summer',
        'phlox' => 'beds,pots,winter',
        'torenia' => 'shade,pots',
        'gerbera' => 'pots,garlands',
        'alyssum' => 'beds,pots,winter',
        'flowering-cabbage' => 'beds,pots,winter',
        'coleus' => 'shade,pots',
        'carnation' => 'garlands,beds,winter',
        'gomphrena' => 'beds,garlands,summer',
        'gloxinia' => 'shade,pots',
        'gaillardia' => 'beds,summer',
        'hypoestes' => 'shade,pots',
        'kochia' => 'beds,summer',
        'melampodium' => 'beds,summer',
        'osteospermum' => 'beds,pots,winter',
        'ornamental-chilli' => 'pots,summer',
        'rudbeckia' => 'beds,garlands,summer',
        'strawberry' => 'pots',
        'stock' => 'garlands,beds,winter',
        'papaya' => 'summer',
        'herbs' => 'pots',
    ];
    return $map[$slug] ?? 'beds';
}

function plant_copy(string $slug, string $name): array
{
    $known = [
        'cosmos' => ['Cosmos bipinnatus', 'Pink, white, magenta', 'Summer beds and loose bunches', 'Sow or transplant into full sun after the soil is warm. Space about 25 cm.', 'Water at the base. Stake tall stems if the bed is windy.', 'Open daisy flowers through the warm months.', 'Warm season'],
        'aster' => ['Callistephus chinensis', 'Pink, purple, white', 'Winter and autumn beds and cut flowers', 'Transplant into sun. Space about 20 cm.', 'Even moisture. Good air keeps the leaves clean.', 'Double blooms in the cool season.', 'Cool season'],
        'calendula' => ['Calendula officinalis', 'Orange, yellow', 'Winter beds, pots and edging', 'Full sun to light shade. Space about 20 cm.', 'Do not keep the crown wet overnight.', 'Daisy flowers through the cool months.', 'October to February'],
        'dahlia' => ['Dahlia', 'Red, pink, yellow, white', 'Beds and cut flowers', 'Sun and rich soil. Space about 30 cm.', 'Water deeply. Pinch once for a bushier plant.', 'Blooms in the mild and cool months.', 'Mild season'],
        'verbena' => ['Verbena hybrida', 'Purple, red, white', 'Beds, baskets and sunny pots', 'Full sun. Space about 20 cm.', 'Let the surface dry slightly between waterings.', 'Clusters over a long stretch.', 'Mild and warm months'],
        'portulaca' => ['Portulaca grandiflora', 'Pink, orange, yellow, white', 'Hot sunny pots and dry edges', 'Full sun and sharp drainage. Space about 15 cm.', 'Keep it on the dry side once it is growing.', 'Flowers open in bright sun.', 'Warm season'],
        'phlox' => ['Phlox drummondii', 'Pink, red, white, purple', 'Winter beds and pots', 'Sun. Space about 15 cm.', 'Even moisture while the plants establish.', 'Rounded heads in the cool season.', 'October to February'],
        'torenia' => ['Torenia fournieri', 'Blue, pink', 'Shade pots and monsoon beds', 'Bright shade or morning sun. Space about 15 cm.', 'Do not let the pot dry hard.', 'Small faced flowers in mild weather.', 'Mild and monsoon shade'],
        'gerbera' => ['Gerbera jamesonii', 'Red, orange, pink, yellow', 'Pots and cut flowers', 'Sun and very sharp drainage. Keep the crown above the soil.', 'Water the soil, not the crown.', 'Long-stemmed daisies once established.', 'Mild months'],
        'alyssum' => ['Lobularia maritima', 'White, soft purple', 'Edging, pots and baskets', 'Sun to light shade. Space about 15 cm.', 'Trim lightly if it gets long.', 'A low carpet of tiny flowers.', 'Cool season'],
    ];
    if (isset($known[$slug])) {
        [$science, $colour, $uses, $plant, $care, $flower, $season] = $known[$slug];
    } else {
        $science = '';
        $colour = 'Mixed colours';
        $uses = 'Beds and pots';
        $plant = 'Transplant into prepared soil. Space the seedlings so each plant has light and air.';
        $care = 'Water at the base and keep the bed draining. Ask the nursery if you are unsure about the site.';
        $flower = 'Flowering time depends on the season and the variety.';
        $season = '2026-27 list';
    }
    $short = $name . ' seedlings from the Theur nursery. Sold by the tray of 150 seedlings.';
    $long = $name . ' is grown as a seedling tray at Theur, near Pune. The price list shows the rate per seedling and the price per tray. A tray is not split. This is general growing information, not a promise of a result.';
    return [
        'scientific_name' => $science,
        'colour' => $colour,
        'uses' => $uses,
        'planting_info' => $plant,
        'care_info' => $care,
        'flowering_info' => $flower,
        'season_label' => $season,
        'short_description' => $short,
        'description' => $long,
    ];
}

function month_defaults(): array
{
    return [
        1 => 'Petunia, Pansy, Antirrhinum, Dianthus, Calendula, Aster',
        2 => 'Petunia, Pansy, Dianthus, Salvia, Geranium',
        3 => 'Salvia, Marigold, Zinnia, Vinca',
        4 => 'Marigold, Zinnia, Vinca, Portulaca, Sunflower',
        5 => 'Marigold, Zinnia, Vinca, Portulaca, Sunflower',
        6 => 'Vinca, Portulaca, Impatiens, Torenia',
        7 => 'Vinca, Impatiens, Torenia, Begonia',
        8 => 'Impatiens, Torenia, Begonia, Celosia',
        9 => 'Marigold, Celosia, Salvia, Chrysanthemum',
        10 => 'Petunia, Pansy, Antirrhinum, Dianthus, Calendula, Aster',
        11 => 'Petunia, Pansy, Antirrhinum, Dianthus, Calendula, Aster',
        12 => 'Petunia, Pansy, Antirrhinum, Dianthus, Salvia, Geranium',
    ];
}

function month_plant_text(int $month): string
{
    $defaults = month_defaults();
    $fallback = $defaults[$month] ?? $defaults[10];
    return setting('plant_month_' . $month, $fallback);
}

function discount_banner_text(): string
{
    if (setting('seasonal_discount_on', '0') !== '1') {
        return '';
    }
    $custom = trim(setting('discount_banner', ''));
    if ($custom !== '') {
        return $custom;
    }
    $percent = (int) setting('seasonal_discount_percent', '10');
    if (setting('discount_basis', 'rupees') === 'seedlings') {
        return $percent . '% off on orders above ' . number_format((int) setting('discount_min_seedlings', '5000')) . ' seedlings';
    }
    return $percent . '% off on orders above Rs ' . number_format((int) setting('seasonal_discount_min_inr', '5000'));
}

function discount_progress_text(array $offer): string
{
    if ((int) ($offer['amount'] ?? 0) > 0 || (int) ($offer['amount_paise'] ?? 0) > 0) {
        return (int) ($offer['percent'] ?? 10) . '% bulk discount applied. Packing and transport are not included.';
    }
    if (setting('seasonal_discount_on', '0') !== '1') {
        return '';
    }
    $inr = (int) ($offer['short_inr'] ?? $offer['short'] ?? 0);
    $seed = (int) ($offer['short_seedlings'] ?? 0);
    if ($inr <= 0 && $seed <= 0) {
        return '';
    }
    return 'Add Rs ' . number_format(max(0, $inr)) . ' more (or ' . number_format(max(0, $seed)) . ' more seedlings) to get ' . (int) ($offer['percent'] ?? 10) . '% off. Packing and transport are not discounted.';
}

function low_stock_limit(): int
{
    $n = (int) setting('low_stock_threshold', '10');
    if ($n < 0 || $n > 500) {
        return 10;
    }
    return $n;
}

function default_start_stock(): int
{
    $n = (int) setting('default_starting_stock', '40');
    if ($n < 1 || $n > 5000) {
        return 40;
    }
    return $n;
}

function wa_order_href(array $product, int $trays = 1): string
{
    $name = (string) ($product['name'] ?? 'a plant');
    $variety = trim((string) ($product['variety'] ?? ''));
    $text = 'Hello Precision Agritech. Plant: ' . $name . '.';
    if ($variety !== '') {
        $text .= ' Variety: ' . $variety . '.';
    }
    $text .= ' Trays wanted: ' . max(1, $trays) . '.';
    return wa_href($text);
}

function plant_varieties(int $productId): array
{
    if ($productId < 1) {
        return [];
    }
    try {
        $stmt = db()->prepare("SELECT i.item_name, i.variety, i.marathi_name, i.price_per_seedling_paise, i.seedlings_per_tray
            FROM price_list_items i
            JOIN price_lists l ON l.id = i.price_list_id
            WHERE i.product_id = ? AND i.active = 1 AND l.audience <> 'farmer'
            ORDER BY i.id");
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    } catch (Throwable $err) {
        return [];
    }
}

function seasonal_rows_public(): array
{
    return db()->query("SELECT i.id, i.item_name, i.variety, i.marathi_name, i.price_per_seedling_paise, i.sku, i.product_id, p.slug
        FROM price_list_items i
        JOIN price_lists l ON l.id = i.price_list_id
        LEFT JOIN products p ON p.id = i.product_id
        WHERE l.slug = 'seasonal-2026-27' AND i.active = 1
        ORDER BY i.id")->fetchAll();
}

function shop_cards(): array
{
    return db()->query('SELECT p.*, c.name AS category_name, c.slug AS category_slug
        FROM products p JOIN categories c ON c.id = p.category_id
        WHERE ' . catalog_card_sql() . '
        ORDER BY c.sort_order, c.name, p.name, p.id')->fetchAll();
}

function home_bundle(): array
{
    $cards = shop_cards();
    $limit = (int) setting('rail_limit', '8');
    if ($limit < 4 || $limit > 24) {
        $limit = 8;
    }
    $inStock = static function (array $row): bool {
        return (int) $row['stock_qty'] > (int) $row['reserved_qty'];
    };
    $pick = static function (array $rows) use ($limit): array {
        return array_slice($rows, 0, $limit);
    };
    $best = [];
    $new = [];
    $offers = [];
    foreach ($cards as $row) {
        if (!$inStock($row)) {
            continue;
        }
        if ((int) ($row['bestseller'] ?? 0) === 1) {
            $best[] = $row;
        }
        if ((int) ($row['is_new'] ?? 0) === 1) {
            $new[] = $row;
        }
        if (offer_is_listed($row)) {
            $offers[] = $row;
        }
    }
    $recent = $cards;
    usort($recent, static fn ($a, $b) => ((int) $b['id']) <=> ((int) $a['id']));
    $recent = array_values(array_filter($recent, $inStock));
    if (!$best) {
        $best = $recent;
    }
    if (!$new) {
        $new = $recent;
    }
    return [
        'cards' => $cards,
        'bestsellers' => $pick($best),
        'new' => $pick($new),
        'offers' => $pick($offers),
    ];
}

function scrub_tray_wording(string $text): string
{
    $pairs = [
        '104' . ' plants' => '150 seedlings',
        '104' . ' young plants' => '150 seedlings',
        '104' . ' plugs' => '150 seedlings',
        'per tray of ' . '104' => 'per tray of 150 seedlings',
        'One tray is ' . '104' => 'One tray is 150',
        'tray of ' . '104' => 'tray of 150 seedlings',
    ];
    return str_replace(array_keys($pairs), array_values($pairs), $text);
}

function ui_label(string $key): string
{
    $lang = shop_lang();
    $defaults = ui_label_defaults();
    $custom = [];
    $raw = setting('ui_labels_' . $lang, '');
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $custom = $decoded;
        }
    }
    $base = $defaults[$lang][$key] ?? $defaults['en'][$key] ?? $key;
    $value = trim((string) ($custom[$key] ?? ''));
    return $value !== '' ? $value : $base;
}

function shop_lang(): string
{
    $lang = (string) ($_COOKIE['pa_lang'] ?? $_SESSION['pa_lang'] ?? 'en');
    return $lang === 'mr' ? 'mr' : 'en';
}

function ui_label_defaults(): array
{
    return [
        'en' => [
            'shop' => 'Shop',
            'cart' => 'Cart',
            'sign_in' => 'Sign in',
            'sign_out' => 'Sign out',
            'account' => 'Account',
            'add' => 'Add to cart',
            'checkout' => 'Checkout',
            'continue' => 'Continue shopping',
            'seedling' => 'Rate per seedling',
            'tray' => 'Price per tray',
            'search' => 'Search plants',
            'whatsapp' => 'WhatsApp',
            'details' => 'Details',
            'pay' => 'Pay',
            'done' => 'Done',
            'lang' => 'मराठी',
            'calculator' => 'Order calculator',
        ],
        'mr' => [
            'shop' => 'दुकान',
            'cart' => 'कार्ट',
            'sign_in' => 'साइन इन',
            'sign_out' => 'साइन आउट',
            'account' => 'खाते',
            'add' => 'कार्टमध्ये टाका',
            'checkout' => 'चेकआउट',
            'continue' => 'खरेदी सुरू ठेवा',
            'seedling' => 'प्रति रोप दर',
            'tray' => 'प्रति ट्रे किंमत',
            'search' => 'झाडे शोधा',
            'whatsapp' => 'व्हाट्सअॅप',
            'details' => 'माहिती',
            'pay' => 'पेमेंट',
            'done' => 'पूर्ण',
            'lang' => 'English',
            'calculator' => 'ऑर्डर कॅल्क्युलेटर',
        ],
    ];
}

function save_plant_photo(array $file, string $slug): void
{
    $slug = list_slug($slug, 80);
    if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
        throw new RuntimeException('Name the file with the plant slug, for example cosmos.webp.');
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    assert_image_safe($tmp);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $loaders = [
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png' => 'imagecreatefrompng',
        'image/webp' => 'imagecreatefromwebp',
    ];
    if (!is_string($mime) || !isset($loaders[$mime]) || !function_exists($loaders[$mime])) {
        throw new RuntimeException('Use a JPEG, PNG or WebP image.');
    }
    $src = @$loaders[$mime]($tmp);
    if (!$src) {
        throw new RuntimeException('That image could not be opened.');
    }
    $root = dirname(__DIR__);
    try {
        write_webp_scaled($src, $root . '/products/' . $slug . '.webp', 1200, 82);
        foreach ([400 => 78, 800 => 80, 1200 => 82] as $width => $quality) {
            write_webp_scaled($src, $root . '/products/sm/' . $slug . '-' . $width . '.webp', $width, $quality);
        }
    } finally {
        imagedestroy($src);
    }
    $stmt = db()->prepare('UPDATE products SET image_url = ?, image_is_placeholder = 0 WHERE slug = ?');
    $stmt->execute(['/products/' . $slug . '.webp', $slug]);
    if ($stmt->rowCount() < 1) {
        throw new RuntimeException('No plant uses the slug ' . $slug . '.');
    }
}

