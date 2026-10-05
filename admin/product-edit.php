<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
$error = null;
$id = (int) ($_GET['id'] ?? 0);
$categories = db()->query('SELECT id, name FROM categories ORDER BY sort_order')->fetchAll();
$product = null;
if ($id) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $product = $stmt->fetch();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $name = trim((string) ($_POST['name'] ?? ''));
        $slug = strtolower(trim((string) ($_POST['slug'] ?? '')));
        $price = (int) ($_POST['price_inr'] ?? 0);
        $compare = ($_POST['compare_at_inr'] ?? '') === '' ? null : (int) $_POST['compare_at_inr'];
        if ($name === '' || !preg_match('/^[a-z0-9-]+$/', $slug) || $price < 1) {
            throw new RuntimeException('Check the name, slug and price.');
        }
        if ($compare !== null && $compare <= $price) {
            throw new RuntimeException('The old price must be higher than the selling price.');
        }
        $fields = [
            $slug, strtoupper(trim((string) $_POST['sku'])), $name, trim((string) $_POST['scientific_name']), trim((string) $_POST['variety']),
            (int) $_POST['category_id'], trim((string) $_POST['short_description']), trim((string) $_POST['description']),
            trim((string) $_POST['planting_info']), trim((string) $_POST['care_info']), trim((string) $_POST['flowering_info']),
            trim((string) $_POST['colour']), trim((string) $_POST['uses']), trim((string) $_POST['image_url']), trim((string) $_POST['unit_label']),
            $price, $compare, (int) $_POST['min_order'], trim((string) $_POST['availability']), trim((string) $_POST['season_label']),
            isset($_POST['bestseller']) ? 1 : 0, isset($_POST['is_new']) ? 1 : 0, isset($_POST['on_offer']) ? 1 : 0,
            ($_POST['offer_starts_at'] ?? '') ?: null, ($_POST['offer_ends_at'] ?? '') ?: null, isset($_POST['active']) ? 1 : 0,
        ];
        if ($product) {
            db()->prepare('UPDATE products SET slug=?, sku=?, name=?, scientific_name=?, variety=?, category_id=?, short_description=?, description=?, planting_info=?, care_info=?, flowering_info=?, colour=?, uses=?, image_url=?, unit_label=?, price_inr=?, compare_at_inr=?, min_order=?, availability=?, season_label=?, bestseller=?, is_new=?, on_offer=?, offer_starts_at=?, offer_ends_at=?, active=? WHERE id=?')
                ->execute([...$fields, $id]);
            audit_log((int) $admin['id'], 'product_update', 'product', $id, (string) $product['price_inr'], (string) $price);
        } else {
            db()->prepare('INSERT INTO products (slug, sku, name, scientific_name, variety, category_id, short_description, description, planting_info, care_info, flowering_info, colour, uses, image_url, unit_label, price_inr, compare_at_inr, stock_qty, min_order, availability, season_label, bestseller, is_new, on_offer, offer_starts_at, offer_ends_at, active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,?,?,?,?,?,?,?,?,?)')
                ->execute($fields);
            $id = (int) db()->lastInsertId();
            audit_log((int) $admin['id'], 'product_create', 'product', $id, null, $name);
        }
        header('Location: /admin/products');
        exit;
    } catch (Throwable $err) {
        error_log($err->getMessage());
        $error = 'The product could not be saved.';
    }
}
$p = $product ?: ['slug'=>'','sku'=>'','name'=>'','scientific_name'=>'','variety'=>'','category_id'=>$categories[0]['id'] ?? 1,'short_description'=>'','description'=>'','planting_info'=>'','care_info'=>'','flowering_info'=>'','colour'=>'','uses'=>'','image_url'=>'/brand/seedling.webp','unit_label'=>'tray','price_inr'=>'','compare_at_inr'=>'','min_order'=>1,'availability'=>'in_stock','season_label'=>'','bestseller'=>0,'is_new'=>0,'on_offer'=>0,'offer_starts_at'=>'','offer_ends_at'=>'','active'=>1];
admin_open($product ? 'Edit product' : 'Add product');
if ($error) echo '<p class="flash">' . e($error) . '</p>';
?>
<form method="post" class="narrow">
  <?= csrf_field() ?>
  <label>Name <input name="name" required value="<?= e((string) $p['name']) ?>"></label>
  <label>Slug <input name="slug" required value="<?= e((string) $p['slug']) ?>"></label>
  <label>SKU <input name="sku" required value="<?= e((string) $p['sku']) ?>"></label>
  <label>Category <select name="category_id"><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (int) $p['category_id'] === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
  <label>Scientific name <input name="scientific_name" value="<?= e((string) $p['scientific_name']) ?>"></label>
  <label>Variety <input name="variety" value="<?= e((string) $p['variety']) ?>"></label>
  <label>Short description <textarea name="short_description"><?= e((string) $p['short_description']) ?></textarea></label>
  <label>Description <textarea name="description"><?= e((string) $p['description']) ?></textarea></label>
  <label>Planting <textarea name="planting_info"><?= e((string) $p['planting_info']) ?></textarea></label>
  <label>Care <textarea name="care_info"><?= e((string) $p['care_info']) ?></textarea></label>
  <label>Flowering <textarea name="flowering_info"><?= e((string) $p['flowering_info']) ?></textarea></label>
  <label>Colour <input name="colour" value="<?= e((string) $p['colour']) ?>"></label>
  <label>Uses <input name="uses" value="<?= e((string) $p['uses']) ?>"></label>
  <label>Image path <input name="image_url" value="<?= e((string) $p['image_url']) ?>"></label>
  <label>Unit <input name="unit_label" value="<?= e((string) $p['unit_label']) ?>"></label>
  <label>Price INR <input name="price_inr" type="number" min="1" required value="<?= e((string) $p['price_inr']) ?>"></label>
  <label>Old price INR <input name="compare_at_inr" type="number" value="<?= e((string) ($p['compare_at_inr'] ?? '')) ?>"></label>
  <label>Minimum trays <input name="min_order" type="number" min="1" value="<?= (int) $p['min_order'] ?>"></label>
  <label>Availability <input name="availability" value="<?= e((string) $p['availability']) ?>"></label>
  <label>Season <input name="season_label" value="<?= e((string) $p['season_label']) ?>"></label>
  <label>Offer starts <input name="offer_starts_at" type="datetime-local" value="<?= e(str_replace(' ', 'T', (string) ($p['offer_starts_at'] ?? ''))) ?>"></label>
  <label>Offer ends <input name="offer_ends_at" type="datetime-local" value="<?= e(str_replace(' ', 'T', (string) ($p['offer_ends_at'] ?? ''))) ?>"></label>
  <label><input type="checkbox" name="bestseller" <?= $p['bestseller'] ? 'checked' : '' ?>> Bestseller</label>
  <label><input type="checkbox" name="is_new" <?= $p['is_new'] ? 'checked' : '' ?>> New</label>
  <label><input type="checkbox" name="on_offer" <?= $p['on_offer'] ? 'checked' : '' ?>> On offer</label>
  <label><input type="checkbox" name="active" <?= $p['active'] ? 'checked' : '' ?>> Live on the shop</label>
  <button class="btn">Save product</button>
</form>
<?php admin_close(); ?>
