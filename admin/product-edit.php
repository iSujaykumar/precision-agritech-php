<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
$error = null;
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$categories = db()->query('SELECT id, name FROM categories ORDER BY sort_order, name')->fetchAll();
$product = null;
if ($id) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $product = $stmt->fetch() ?: null;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $name = trim((string) ($_POST['name'] ?? ''));
        $slug = slugify((string) ($_POST['slug'] ?? ''));
        if ($slug === '') {
            $slug = slugify($name);
        }
        $sku = strtoupper(trim((string) ($_POST['sku'] ?? '')));
        $price = (int) ($_POST['price_inr'] ?? 0);
        $paiseRaw = trim((string) ($_POST['price_per_seedling_paise'] ?? ''));
        $paise = null;
        $trayPaise = null;
        if ($paiseRaw !== '') {
            if (!preg_match('/^\d+$/', $paiseRaw) || (int) $paiseRaw > 100000) {
                throw new RuntimeException('Per-seedling price is whole paise, from 0 to 100000.');
            }
            $paise = (int) $paiseRaw;
            if ($paise > 0) {
                $trayPaise = exact_tray_paise($paise, 150);
                $price = intdiv($trayPaise + 50, 100);
            }
        }
        $compareRaw = trim((string) ($_POST['compare_at_inr'] ?? ''));
        if ($compareRaw !== '' && !preg_match('/^[1-9][0-9]*$/', $compareRaw)) {
            throw new RuntimeException('The old price must be a whole number of rupees, or left blank.');
        }
        $compare = $compareRaw === '' ? null : (int) $compareRaw;
        $stock = (int) ($_POST['stock_qty'] ?? ($product['stock_qty'] ?? 0));
        $minOrder = (int) ($_POST['min_order'] ?? 1);
        if ($name === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
            throw new RuntimeException('Use a name. The link is filled from the name if you leave it blank.');
        }
        if ($sku === '') {
            throw new RuntimeException('Enter a SKU.');
        }
        if ($price < 1) {
            throw new RuntimeException('Price must be at least 1.');
        }
        if ($compare !== null && $compare <= $price) {
            throw new RuntimeException('The old price must be higher than the selling price.');
        }
        if ($stock < 0) {
            throw new RuntimeException('Stock cannot be below zero.');
        }
        if ($minOrder < 1) {
            throw new RuntimeException('Minimum order must be at least 1.');
        }
        $starts = offer_input_datetime((string) ($_POST['offer_starts_at'] ?? ''));
        $ends = offer_input_datetime((string) ($_POST['offer_ends_at'] ?? ''));
        if ($starts !== null && $ends !== null && $ends < $starts) {
            throw new RuntimeException('The offer end cannot be before the start.');
        }
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $categoryOk = db()->prepare('SELECT id FROM categories WHERE id = ?');
        $categoryOk->execute([$categoryId]);
        if (!$categoryOk->fetch()) {
            throw new RuntimeException('Choose a category that exists.');
        }
        $slugTaken = db()->prepare('SELECT id FROM products WHERE slug = ? AND id <> ?');
        $slugTaken->execute([$slug, $id]);
        if ($slugTaken->fetch()) {
            throw new RuntimeException('That product link is already used.');
        }
        $skuTaken = db()->prepare('SELECT id FROM products WHERE sku = ? AND id <> ?');
        $skuTaken->execute([$sku, $id]);
        if ($skuTaken->fetch()) {
            throw new RuntimeException('That SKU is already used.');
        }
        $image = (string) ($product['image_url'] ?? '');
        if (!empty($_POST['remove_image'])) {
            remove_managed_image($image);
            $image = '';
        }
        if (!empty($_FILES['image']['name'])) {
            $saved = save_product_image($_FILES['image']);
            if ($image !== $saved) {
                remove_managed_image($image);
            }
            $image = $saved;
        }
        $overrideRaw = trim((string) ($_POST['seedlings_override'] ?? ''));
        $override = null;
        if ($overrideRaw !== '') {
            $override = (int) $overrideRaw;
            if ($override < 1 || $override > 1000) {
                throw new RuntimeException('Seedlings per tray must be from 1 to 1000, or left blank to use the shop setting.');
            }
        }
        $farmerOnly = isset($_POST['farmer_only']) ? 1 : 0;
        $overgrown = isset($_POST['is_overgrown']) ? 1 : 0;
        $marathi = clean_text((string) ($_POST['marathi_name'] ?? ''), 160);
        $picked = $_POST['purposes'] ?? [];
        if (!is_array($picked)) {
            $picked = [];
        }
        $picked = array_values(array_intersect(array_keys(purpose_choices()), array_map('strval', $picked)));
        $purposes = implode(',', $picked);
        $listOnly = isset($_POST['shop_card']) ? 0 : 1;
        $placeholder = ($image === '' || $image === '/brand/seedling.webp') ? 1 : 0;
        $extra = [$paise, $override, $farmerOnly, $overgrown, $marathi, $purposes, $listOnly, $trayPaise, $placeholder, 0];
        $fields = [
            $slug, $sku, $name, trim((string) $_POST['scientific_name']), trim((string) $_POST['variety']),
            $categoryId, trim((string) $_POST['short_description']), trim((string) $_POST['description']),
            trim((string) $_POST['planting_info']), trim((string) $_POST['care_info']), trim((string) $_POST['flowering_info']),
            trim((string) $_POST['colour']), trim((string) $_POST['uses']), $image, trim((string) $_POST['unit_label']),
            $price, $compare, $minOrder, trim((string) $_POST['availability']), trim((string) $_POST['season_label']),
            isset($_POST['bestseller']) ? 1 : 0, isset($_POST['is_new']) ? 1 : 0, isset($_POST['on_offer']) ? 1 : 0,
            $starts,
            $ends,
            isset($_POST['active']) ? 1 : 0,
        ];
        $pdo = db();
        $pdo->beginTransaction();
        if ($product) {
            $pdo->prepare('UPDATE products SET slug=?, sku=?, name=?, scientific_name=?, variety=?, category_id=?, short_description=?, description=?, planting_info=?, care_info=?, flowering_info=?, colour=?, uses=?, image_url=?, unit_label=?, price_inr=?, compare_at_inr=?, min_order=?, availability=?, season_label=?, bestseller=?, is_new=?, on_offer=?, offer_starts_at=?, offer_ends_at=?, active=?, price_per_seedling_paise=?, seedlings_override=?, farmer_only=?, is_overgrown=?, marathi_name=?, purposes=?, list_only=?, tray_paise=?, image_is_placeholder=?, needs_copy=? WHERE id=?')
                ->execute([...$fields, ...$extra, $id]);
            $delta = $stock - (int) $product['stock_qty'];
            if ($delta !== 0) {
                adjust_stock($pdo, $id, $delta, 'Set from the product form', (int) $admin['id']);
            }
            audit_log((int) $admin['id'], 'product_update', 'product', $id, (string) $product['price_inr'], (string) $price . ' paise ' . (string) ($paise ?? ''));
        } else {
            $pdo->prepare('INSERT INTO products (slug, sku, name, scientific_name, variety, category_id, short_description, description, planting_info, care_info, flowering_info, colour, uses, image_url, unit_label, price_inr, compare_at_inr, stock_qty, min_order, availability, season_label, bestseller, is_new, on_offer, offer_starts_at, offer_ends_at, active, price_per_seedling_paise, seedlings_override, farmer_only, is_overgrown, marathi_name, purposes, list_only, tray_paise, image_is_placeholder, needs_copy) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([...$fields, ...$extra]);
            $id = (int) $pdo->lastInsertId();
            if ($stock > 0) {
                adjust_stock($pdo, $id, $stock, 'Opening stock', (int) $admin['id']);
            }
            audit_log((int) $admin['id'], 'product_create', 'product', $id, null, $name);
        }
        if (!isset($_POST['on_offer'])) {
            $pdo->prepare('UPDATE products SET offer_seedling_paise = NULL WHERE id = ?')->execute([$id]);
        }
        $pdo->commit();
        flash_set('Product saved');
        header('Location: /admin/products');
        exit;
    } catch (Throwable $err) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = safe_error($err, 'The product could not be saved.');
    }
}
$p = $product ?: [
    'slug' => '', 'sku' => '', 'name' => '', 'scientific_name' => '', 'variety' => '',
    'category_id' => $categories[0]['id'] ?? 1, 'short_description' => '', 'description' => '',
    'planting_info' => '', 'care_info' => '', 'flowering_info' => '', 'colour' => '', 'uses' => '',
    'image_url' => '', 'unit_label' => 'tray', 'price_inr' => '', 'compare_at_inr' => '',
    'stock_qty' => 0, 'min_order' => 1, 'availability' => 'in_stock', 'season_label' => '',
    'bestseller' => 0, 'is_new' => 0, 'on_offer' => 0, 'offer_starts_at' => '', 'offer_ends_at' => '', 'active' => 1,
    'price_per_seedling_paise' => '', 'seedlings_override' => '', 'farmer_only' => 0, 'is_overgrown' => 0,
    'marathi_name' => '', 'purposes' => '', 'list_only' => 0, 'tray_paise' => '',
];
admin_open($product ? 'Edit product' : 'Add product', 'plants');
if ($error) {
    echo '<p class="flash bad" role="alert">' . e($error) . '</p>';
}
?>
<form method="post" class="narrow" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) ($product['id'] ?? 0) ?>">
  <label>Name <input name="name" required value="<?= e((string) $p['name']) ?>"></label>
  <label>Slug <input name="slug" value="<?= e((string) $p['slug']) ?>" placeholder="Filled from the name if left blank"></label>
  <label>SKU <input name="sku" required value="<?= e((string) $p['sku']) ?>"></label>
  <label>Category <select name="category_id"><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (int) $p['category_id'] === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
  <label>Scientific name <input name="scientific_name" value="<?= e((string) $p['scientific_name']) ?>"></label>
  <label>Marathi name <input name="marathi_name" value="<?= e((string) ($p['marathi_name'] ?? '')) ?>" placeholder="झेंडू"></label>
  <label>Variety <input name="variety" value="<?= e((string) $p['variety']) ?>"></label>
  <label>Short description <textarea name="short_description"><?= e((string) $p['short_description']) ?></textarea></label>
  <label>Description <textarea name="description"><?= e((string) $p['description']) ?></textarea></label>
  <label>Planting <textarea name="planting_info"><?= e((string) $p['planting_info']) ?></textarea></label>
  <label>Care <textarea name="care_info"><?= e((string) $p['care_info']) ?></textarea></label>
  <label>Flowering <textarea name="flowering_info"><?= e((string) $p['flowering_info']) ?></textarea></label>
  <label>Colour <input name="colour" value="<?= e((string) $p['colour']) ?>"></label>
  <label>Uses <input name="uses" value="<?= e((string) $p['uses']) ?>"></label>
  <p><img class="preview" src="<?= e(product_image((string) $p['image_url'])) ?>" alt="Current tray photo"></p>
  <label>Photo <input name="image" type="file" accept="image/jpeg,image/png,image/webp"></label>
  <?php if ($product && str_starts_with((string) $p['image_url'], '/uploads/products/')): ?>
  <label><input type="checkbox" name="remove_image" value="1"> Remove image</label>
  <?php endif; ?>
  <label>Unit <input name="unit_label" value="<?= e((string) $p['unit_label']) ?>"></label>
  <label>Price INR <input name="price_inr" type="number" min="1" required value="<?= e((string) $p['price_inr']) ?>"></label>
  <label>Per seedling paise <input name="price_per_seedling_paise" type="number" min="0" max="100000" value="<?= e((string) ($p['price_per_seedling_paise'] ?? '')) ?>" placeholder="425 means Rs 4.25" data-seed-paise></label>
  <p class="muted" data-tray-live>Price per tray (150 seedlings) updates as you type. Example: 425 paise is Rs 637.50 per tray.</p>
  <label>Seedlings per tray override <input name="seedlings_override" type="number" min="1" max="1000" value="<?= e((string) ($p['seedlings_override'] ?? '')) ?>" placeholder="Blank uses the shop setting"></label>
  <?php
    $shownPaise = (int) ($p['price_per_seedling_paise'] ?? 0);
    if ($shownPaise > 0) {
        $shownTray = tray_rupees($shownPaise, seedlings_per_tray($p));
        echo '<p class="muted">Calculated tray price from the per-seedling rate: ' . inr($shownTray) . '. The Price INR field is what the shop charges until you change it. Half a rupee rounds up.</p>';
        if ($shownTray > (int) $p['price_inr']) {
            echo '<p class="flash">The calculated tray price is higher than Price INR. Save only if that is what you want.</p>';
        }
    }
  ?>
  <label>Old price INR <input name="compare_at_inr" type="number" value="<?= e((string) ($p['compare_at_inr'] ?? '')) ?>"></label>
  <label>Trays on hand <input name="stock_qty" type="number" min="0" value="<?= (int) $p['stock_qty'] ?>"></label>
  <label>Minimum trays <input name="min_order" type="number" min="1" value="<?= (int) $p['min_order'] ?>"></label>
  <label>Availability <input name="availability" value="<?= e((string) $p['availability']) ?>"></label>
  <label>Season <input name="season_label" value="<?= e((string) $p['season_label']) ?>"></label>
  <label>Offer starts <input name="offer_starts_at" type="datetime-local" value="<?= e(substr(str_replace(' ', 'T', (string) ($p['offer_starts_at'] ?? '')), 0, 16)) ?>"></label>
  <label>Offer ends <input name="offer_ends_at" type="datetime-local" value="<?= e(substr(str_replace(' ', 'T', (string) ($p['offer_ends_at'] ?? '')), 0, 16)) ?>"></label>
  <fieldset>
    <legend>Shop by purpose</legend>
    <?php $have = array_filter(array_map('trim', explode(',', (string) ($p['purposes'] ?? '')))); ?>
    <?php foreach (purpose_choices() as $key => $label): ?>
      <label><input type="checkbox" name="purposes[]" value="<?= e($key) ?>" <?= in_array($key, $have, true) ? 'checked' : '' ?>> <?= e($label) ?></label>
    <?php endforeach; ?>
  </fieldset>
  <label><input type="checkbox" name="shop_card" value="1" <?= (int) ($p['list_only'] ?? 0) === 0 ? 'checked' : '' ?>> Show this plant in the shop</label>
  <label><input type="checkbox" name="bestseller" <?= $p['bestseller'] ? 'checked' : '' ?>> Bestseller</label>
  <label><input type="checkbox" name="is_new" <?= $p['is_new'] ? 'checked' : '' ?>> New</label>
  <label><input type="checkbox" name="on_offer" <?= $p['on_offer'] ? 'checked' : '' ?>> On offer</label>
  <label><input type="checkbox" name="farmer_only" <?= !empty($p['farmer_only']) ? 'checked' : '' ?>> Farmers only</label>
  <label><input type="checkbox" name="is_overgrown" <?= !empty($p['is_overgrown']) ? 'checked' : '' ?>> Overgrown / as-is</label>
  <label><input type="checkbox" name="active" <?= $p['active'] ? 'checked' : '' ?>> Live on the shop</label>
  <button class="btn" type="submit">Save product</button>
</form>
<?php admin_close(); ?>
