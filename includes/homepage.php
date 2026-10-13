<?php
declare(strict_types=1);

function nursery_min_seedling_paise(): int
{
    $n = (int) setting('nursery_min_seedling_paise', '100');
    return $n < 1 ? 100 : $n;
}

function offer_cut_paise(int $current, string $mode, int $amount): int
{
    $min = nursery_min_seedling_paise();
    if ($mode === 'flat') {
        $next = $amount;
    } else {
        $pct = max(1, min(50, $amount));
        $next = intdiv(max(0, $current) * (100 - $pct) + 50, 100);
    }
    if ($next < $min) {
        $next = $min;
    }
    return $next;
}

function rupees_input_to_paise(string $raw): int
{
    $raw = trim(str_replace(',', '', $raw));
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $raw)) {
        throw new RuntimeException('Enter a price like 3.50.');
    }
    [$rupees, $rest] = array_pad(explode('.', $raw, 2), 2, '0');
    $rest = str_pad(substr($rest, 0, 2), 2, '0');
    return ((int) $rupees) * 100 + (int) $rest;
}

function calculator_math(int $seedlings, int $seedPaise, int $perTray = 150, bool $offerLive = false): array
{
    $seedlings = max(1, $seedlings);
    $perTray = max(1, $perTray);
    $trays = (int) ceil($seedlings / $perTray);
    $billed = $trays * $perTray;
    $total = max(0, $seedPaise) * $billed;
    $allow = setting('offer_allows_bulk', '1') === '1';
    $on = setting('seasonal_discount_on', '0') === '1' && (!$offerLive || $allow);
    $basis = setting('discount_basis', 'rupees') === 'seedlings' ? 'seedlings' : 'rupees';
    $minInr = (int) setting('seasonal_discount_min_inr', '5000');
    $minSeed = (int) setting('discount_min_seedlings', '5000');
    $percent = (int) setting('seasonal_discount_percent', '10');
    $rupees = intdiv($total, 100);
    $reached = $on && $percent > 0 && ($basis === 'seedlings' ? $billed >= $minSeed : $rupees >= $minInr);
    $off = $reached ? intdiv($total * $percent + 50, 100) : 0;
    return [
        'trays' => $trays,
        'billed' => $billed,
        'total_paise' => $total,
        'discount_paise' => $off,
        'applies' => $off > 0,
        'percent' => $percent,
        'on' => $on,
    ];
}

function calc_menu_on(): bool
{
    return setting('calc_menu_on', '1') !== '0';
}

function calc_label(): string
{
    $key = shop_lang() === 'mr' ? 'calc_label_mr' : 'calc_label_en';
    $custom = trim(setting($key, ''));
    if ($custom !== '') {
        return $custom;
    }
    return ui_label('calculator');
}

function calc_discount_attrs(): string
{
    $on = setting('seasonal_discount_on', '0') === '1' ? '1' : '0';
    return ' data-min-inr="' . (int) setting('seasonal_discount_min_inr', '5000') . '"'
        . ' data-min-seed="' . (int) setting('discount_min_seedlings', '5000') . '"'
        . ' data-percent="' . (int) setting('seasonal_discount_percent', '10') . '"'
        . ' data-basis="' . e(setting('discount_basis', 'rupees') === 'seedlings' ? 'seedlings' : 'rupees') . '"'
        . ' data-on="' . $on . '"'
        . ' data-offer-bulk="' . (setting('offer_allows_bulk', '1') === '1' ? '1' : '0') . '"';
}

function home_section_defs(): array
{
    return [
        'hero' => 'Hero',
        'trust' => 'Trust strip',
        'banner' => 'Discount banner',
        'best' => 'Best sellers',
        'new' => 'New arrivals',
        'offers' => 'Offers',
        'month' => 'Plant this month',
        'browse' => 'Browse all plants',
        'why' => 'Why Precision Agritech',
        'bulk' => 'Bulk quote band',
        'reviews' => 'Reviews',
        'ready' => 'Ready to grow',
    ];
}

function home_section_state(): array
{
    $defs = home_section_defs();
    $raw = json_decode(setting('home_sections', ''), true);
    $ordered = [];
    if (is_array($raw)) {
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (string) ($row['id'] ?? '');
            if (!isset($defs[$id]) || array_key_exists($id, $ordered)) {
                continue;
            }
            $ordered[$id] = ((int) ($row['on'] ?? 1)) === 1;
        }
    }
    foreach ($defs as $id => $label) {
        if (!array_key_exists($id, $ordered)) {
            $ordered[$id] = true;
        }
    }
    return $ordered;
}

function save_home_sections(array $state): void
{
    $rows = [];
    foreach ($state as $id => $on) {
        if (!isset(home_section_defs()[$id])) {
            continue;
        }
        $rows[] = ['id' => $id, 'on' => $on ? 1 : 0];
    }
    save_setting('home_sections', json_encode($rows, JSON_UNESCAPED_UNICODE));
}

function move_home_section(string $id, string $dir): void
{
    $state = home_section_state();
    $keys = array_keys($state);
    $index = array_search($id, $keys, true);
    if ($index === false) {
        return;
    }
    $swap = $dir === 'up' ? $index - 1 : $index + 1;
    if (!isset($keys[$swap])) {
        return;
    }
    $tmp = $keys[$index];
    $keys[$index] = $keys[$swap];
    $keys[$swap] = $tmp;
    $next = [];
    foreach ($keys as $key) {
        $next[$key] = $state[$key];
    }
    save_home_sections($next);
}

function render_calc_panel(string $mode = 'dialog'): void
{
    $page = $mode === 'page';
    $plants = shop_cards();
    $digits = '';
    try {
        $digits = whatsapp_digits();
    } catch (Throwable $err) {
        $digits = '';
    }
    $hidden = $page ? '' : ' hidden';
    $role = $page ? '' : ' role="dialog" aria-modal="true" aria-labelledby="calc-title"';
    echo '<div id="calc-panel" class="calc-panel' . ($page ? ' calc-page' : '') . '"' . $hidden . $role . '>';
    if (!$page) {
        echo '<button class="calc-close" type="button" data-calc-close aria-label="Close">×</button>';
    }
    echo '<h2 id="calc-title">' . e(calc_label()) . '</h2>';
    echo '<p class="muted">Trays are sold whole. 150 seedlings is one tray. A part tray is rounded up.</p>';
    echo '<form class="calc" method="post" action="/cart" data-calc' . calc_discount_attrs() . '>';
    echo csrf_field();
    echo '<input type="hidden" name="action" value="add">';
    echo '<input type="hidden" name="slug" value="" data-calc-slug>';
    echo '<input type="hidden" name="qty" value="1" data-calc-trays>';
    echo '<label>Find a plant <input type="search" data-calc-search placeholder="Type a plant name" autocomplete="off"></label>';
    echo '<label>Plant <select data-calc-plant required>';
    $first = true;
    foreach ($plants as $plant) {
        $sell = selling_price($plant);
        if (!empty($sell['blocked']) || (int) ($sell['paise'] ?? 0) < 1) {
            continue;
        }
        $offer = !empty($sell['offer']) ? '1' : '0';
        echo '<option value="' . e((string) $plant['slug']) . '" data-paise="' . (int) $sell['paise'] . '" data-tray="' . (int) $sell['tray'] . '" data-slug="' . e((string) $plant['slug']) . '" data-offer="' . $offer . '" data-name="' . e((string) $plant['name']) . '"' . ($first ? ' selected' : '') . '>' . e((string) $plant['name']) . '</option>';
        $first = false;
    }
    echo '</select></label>';
    echo '<label>Number of seedlings <input data-calc-qty type="number" min="1" value="150"></label>';
    echo '<p data-calc-out class="price"></p>';
    echo '<p class="btn-row"><button class="btn" type="submit">' . e(ui_label('add')) . '</button>';
    if ($digits !== '') {
        echo '<a class="btn" data-calc-wa data-wa-base="https://wa.me/' . e($digits) . '" href="https://wa.me/' . e($digits) . '" target="_blank" rel="noopener">Order on WhatsApp</a>';
    }
    echo '</p></form></div>';
}

function render_offers_rail(array $cards): void
{
    $title = setting('rail_offers_title', 'Offers');
    $discountOn = setting('seasonal_discount_on', '0') === '1';
    $live = [];
    foreach ($cards as $card) {
        if ((int) $card['stock_qty'] <= (int) $card['reserved_qty']) {
            continue;
        }
        if (offer_is_listed($card)) {
            $live[] = $card;
        }
    }
    if (!$discountOn && !$live) {
        return;
    }
    $banner = discount_banner_text();
    echo '<section class="section" id="offers">';
    echo '<div class="wrap"><div class="head"><h2>' . e($title !== '' ? $title : 'Offers') . '</h2><a href="/shop?flag=offer">Shop offers</a></div>';
    echo '<div class="rail-wrap"><button class="rail-btn rail-prev" type="button" aria-label="Previous">&#8249;</button>';
    echo '<div class="rail" tabindex="0">';
    if ($discountOn) {
        $text = $banner !== '' ? $banner : '10% off on orders above Rs 5,000';
        echo '<article class="card promo-card"><div class="card-body"><h3>' . e($text) . '</h3>';
        echo '<p class="muted">Packing and transport are not included.</p>';
        echo '<button class="btn" type="button" data-calc-open>Open the order calculator</button></div></article>';
    }
    foreach ($live as $plant) {
        product_card($plant);
    }
    echo '</div><button class="rail-btn rail-next" type="button" aria-label="Next">&#8250;</button></div>';
    echo '</div></section>';
}

function render_browse_rail(array $cards): void
{
    $title = trim(setting('browse_title', 'Browse all plants'));
    if ($title === '') {
        $title = 'Browse all plants';
    }
    $chips = [];
    foreach ($cards as $card) {
        $slug = (string) ($card['category_slug'] ?? '');
        $name = (string) ($card['category_name'] ?? 'Plants');
        if ($slug === '') {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $name) ?? 'plants');
        }
        $chips[$slug] = $name;
    }
    $order = array_filter(array_map('trim', explode(',', setting('browse_chip_order', ''))));
    if ($order) {
        $sorted = [];
        foreach ($order as $name) {
            foreach ($chips as $slug => $label) {
                if (strcasecmp($label, $name) === 0 || strcasecmp($slug, $name) === 0) {
                    $sorted[$slug] = $label;
                }
            }
        }
        foreach ($chips as $slug => $label) {
            if (!isset($sorted[$slug])) {
                $sorted[$slug] = $label;
            }
        }
        $chips = $sorted;
    }
    echo '<section class="section" id="browse-all"><div class="wrap">';
    echo '<div class="head"><h2>' . e($title) . '</h2><a href="/price-list">Full price list</a></div>';
    echo '<div class="filters" data-browse-chips><button type="button" data-cat="all" aria-pressed="true">All</button>';
    foreach ($chips as $slug => $label) {
        echo '<button type="button" data-cat="' . e($slug) . '" aria-pressed="false">' . e($label) . '</button>';
    }
    echo '</div>';
    echo '<div class="rail-wrap" data-browse><button class="rail-btn rail-prev" type="button" aria-label="Previous">&#8249;</button>';
    echo '<div class="rail" tabindex="0">';
    foreach ($cards as $plant) {
        product_card($plant);
    }
    echo '</div><button class="rail-btn rail-next" type="button" aria-label="Next">&#8250;</button></div>';
    echo '<noscript><p>Scroll this row sideways to see every plant. Category chips need JavaScript. The full list is also on the price list.</p></noscript>';
    if (!$cards) {
        echo '<p>The plant list will appear after the shop update.</p>';
    }
    echo '<p class="btn-row"><a class="btn" href="/price-list?pdf=en">Download price list PDF</a> <a class="btn" href="/price-list?pdf=mr">Download Marathi PDF</a></p>';
    echo '</div></section>';
}

function render_homepage(): void
{
    $bundle = home_bundle();
    $cards = $bundle['cards'];
    $monthNo = (int) (new DateTimeImmutable('now', shop_timezone()))->format('n');
    $monthNames = array_map('trim', explode(',', month_plant_text($monthNo)));
    $monthPlants = [];
    foreach ($cards as $card) {
        foreach ($monthNames as $wanted) {
            if ($wanted !== '' && strcasecmp((string) $card['name'], $wanted) === 0) {
                $monthPlants[] = $card;
            }
        }
    }
    $reviews = db()->query(
        "SELECT r.author_name, r.rating, r.body, r.created_at, p.name AS product_name
         FROM reviews r LEFT JOIN products p ON p.id = r.product_id
         WHERE r.status = 'approved' ORDER BY r.id DESC LIMIT 6"
    )->fetchAll();
    $phone = shop_phone_digits();
    $tel = strlen($phone) === 10 ? '91' . $phone : $phone;
    foreach (home_section_state() as $id => $on) {
        if (!$on) {
            continue;
        }
        if ($id === 'hero') {
            echo '<section class="hero" id="order-calculator">';
            echo '<img src="/brand/greenhouse-hero.webp" alt="Precision Agritech greenhouse at Theur" width="1600" height="1067" decoding="async" fetchpriority="high">';
            echo '<div class="hero-shade"></div><div class="hero-panel">';
            echo '<p class="kicker">' . e(setting('hero_kicker', 'Precision Agritech · Theur, Pune')) . '</p>';
            echo '<h1>' . e(setting('hero_title', 'Healthy seedlings. Better blooms.')) . '</h1>';
            echo '<p>' . e(setting('hero_text', 'Nursery-grown flower seedlings from Theur, Pune. Sold by the tray.')) . '</p>';
            echo '<div class="btn-row">';
            echo '<a class="btn light" href="' . e(safe_shop_href(setting('hero_primary_href', '/shop'), '/shop')) . '">' . e(setting('hero_primary_label', 'Shop seedlings')) . '</a>';
            echo '<a class="btn ghost" href="' . e(safe_shop_href(setting('hero_secondary_href', '/nursery'), '/nursery')) . '">' . e(setting('hero_secondary_label', 'Explore our nursery')) . '</a>';
            echo '<button class="btn ghost" type="button" data-calc-open>' . e(calc_label()) . '</button>';
            echo '</div></div></section>';
        } elseif ($id === 'trust') {
            echo '<section class="trust">';
            echo '<p><strong>Nursery grown</strong><br><span class="muted">Raised at Theur, not bought in.</span></p>';
            echo '<p><strong>Theur, Pune</strong><br><span class="muted">Gaikwadvasti, 412110.</span></p>';
            echo '<p><strong>Sold by the tray</strong><br><span class="muted">The plant count is on each variety.</span></p>';
            echo '<p><strong>Bulk by phone</strong><br><a href="tel:+' . e($tel) . '">' . e($phone) . '</a></p>';
            echo '</section>';
        } elseif ($id === 'banner') {
            $banner = discount_banner_text();
            if ($banner !== '') {
                echo '<p class="announce">' . e($banner) . '</p>';
            }
        } elseif ($id === 'best' && setting('rail_best_on', '1') === '1') {
            render_product_rail('best-sellers', setting('rail_best_title', 'Best sellers'), $bundle['bestsellers'], 'No best sellers are ready to ship right now.', '/shop?sort=popular');
        } elseif ($id === 'new' && setting('rail_new_on', '1') === '1') {
            render_product_rail('new-arrivals', setting('rail_new_title', 'New arrivals'), $bundle['new'], 'No new trays are ready to ship right now.', '/shop?filter=new');
        } elseif ($id === 'offers' && setting('rail_offers_on', '1') === '1') {
            render_offers_rail($cards);
        } elseif ($id === 'month') {
            echo '<section class="section" id="plant-this-month"><div class="wrap"><div class="head"><h2>Plant this month</h2><a href="/shop">Browse all plants</a></div>';
            echo '<p class="muted">Suited to this month around Pune.</p>';
            if ($monthPlants) {
                echo '<div class="rail-wrap"><button class="rail-btn rail-prev" type="button" aria-label="Previous">&#8249;</button><div class="rail rail-mini" tabindex="0">';
                foreach ($monthPlants as $plant) {
                    product_card($plant);
                }
                echo '</div><button class="rail-btn rail-next" type="button" aria-label="Next">&#8250;</button></div>';
            } else {
                echo '<p class="muted">The nursery has not listed this month yet.</p>';
            }
            echo '</div></section>';
        } elseif ($id === 'browse' && setting('browse_on', '1') !== '0') {
            render_browse_rail($cards);
        } elseif ($id === 'why') {
            echo '<section class="section why-section"><div class="wrap why"><h2>Why Precision Agritech</h2>';
            echo '<p><strong>Named varieties</strong><span class="muted">Each tray is a listed flower line, with colour, season and a plant count.</span></p>';
            echo '<p><strong>Held, then sold</strong><span class="muted">An order reserves trays. The nursery confirms payment before it becomes a sale.</span></p>';
            echo '<p><strong>Local, and by the tray</strong><span class="muted">The nursery is in Theur. Large lots are confirmed on ' . e($phone) . '.</span></p>';
            echo '</div></section>';
        } elseif ($id === 'bulk') {
            echo '<section class="section band"><div class="wrap split"><div><h2>Growing at scale?</h2><p>For landscapers, growers and commercial projects. A form does not reserve trays.</p></div>';
            echo '<p class="band-action"><a class="btn light" href="/wholesale">Request a bulk quote</a></p></div></section>';
        } elseif ($id === 'reviews' && $reviews) {
            echo '<section class="section"><div class="wrap"><div class="head"><h2>What customers say</h2><a href="/reviews">All reviews</a></div>';
            echo '<div class="rail-wrap"><button class="rail-btn rail-prev" type="button" aria-label="Previous">&#8249;</button><div class="rail" tabindex="0">';
            foreach ($reviews as $review) {
                echo '<article class="card"><div class="card-body"><p>' . stars_markup((float) $review['rating']) . '</p>';
                echo '<p>' . e($review['body']) . '</p>';
                echo '<p class="muted">' . e(public_reviewer((string) $review['author_name'])) . ' · ' . e((string) ($review['product_name'] ?: 'The nursery')) . ' · ' . e(date('d M Y', strtotime((string) $review['created_at']))) . '</p>';
                echo '</div></article>';
            }
            echo '</div><button class="rail-btn rail-next" type="button" aria-label="Next">&#8250;</button></div></div></section>';
        } elseif ($id === 'ready') {
            echo '<section class="section"><div class="wrap head"><div><h2>Ready to grow?</h2><p class="muted">The full list of trays is in the shop.</p></div><a class="btn" href="/shop">Shop seedlings</a></div></section>';
        }
    }
}

