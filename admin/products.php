<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $id = (int) ($_POST['id'] ?? 0);
        $action = (string) ($_POST['action'] ?? '');
        $product = db()->prepare('SELECT * FROM products WHERE id = ?');
        $product->execute([$id]);
        $row = $product->fetch();
        if (!$row) {
            throw new RuntimeException('That tray was not found.');
        }
        if ($action === 'delete') {
            $orders = db()->prepare('SELECT COUNT(*) FROM order_items WHERE product_id = ?');
            $orders->execute([$id]);
            if ((int) $orders->fetchColumn() > 0) {
                db()->prepare('UPDATE products SET active = 0 WHERE id = ?')->execute([$id]);
                audit_log((int) $admin['id'], 'product_hide', 'product', $id, null, 'hidden');
                flash_set('This tray is on an order, so it was hidden from the shop instead of deleted.');
            } else {
                remove_managed_image((string) $row['image_url']);
                db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
                audit_log((int) $admin['id'], 'product_delete', 'product', $id, $row['name'], 'deleted');
                flash_set('Product deleted');
            }
        } elseif (in_array($action, ['active', 'bestseller', 'on_offer'], true)) {
            $value = (int) ($_POST['value'] ?? 0) === 1 ? 1 : 0;
            db()->prepare('UPDATE products SET ' . $action . ' = ? WHERE id = ?')->execute([$value, $id]);
            audit_log((int) $admin['id'], 'product_' . $action, 'product', $id, null, (string) $value);
            flash_set('Product saved');
        } else {
            throw new RuntimeException('That change is not available.');
        }
        header('Location: /admin/products');
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'That change could not be saved.');
    }
}
$q = trim((string) ($_GET['q'] ?? ''));
$category = (int) ($_GET['category'] ?? 0);
$where = ' WHERE 1=1';
$params = [];
if ($q !== '') {
    $where .= ' AND (p.name LIKE ? OR p.sku LIKE ?)';
    $like = '%' . str_replace(['%', '_'], '', $q) . '%';
    $params[] = $like;
    $params[] = $like;
}
if ($category > 0) {
    $where .= ' AND p.category_id = ?';
    $params[] = $category;
}
$stmt = db()->prepare('SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id' . $where . ' ORDER BY p.name');
$stmt->execute($params);
$rows = $stmt->fetchAll();
$categories = db()->query('SELECT id, name FROM categories ORDER BY sort_order, name')->fetchAll();
admin_open('Products', 'products');
if ($error) {
    echo '<p class="flash bad" role="alert">' . e($error) . '</p>';
}
echo '<p><a class="btn" href="/admin/product-edit">Add product</a></p>';
echo '<form method="get" class="inline filters">';
echo '<label>Search <input name="q" value="' . e($q) . '"></label>';
echo '<label>Category <select name="category"><option value="0">All</option>';
foreach ($categories as $cat) {
    echo '<option value="' . (int) $cat['id'] . '"' . ($category === (int) $cat['id'] ? ' selected' : '') . '>' . e($cat['name']) . '</option>';
}
echo '</select></label><button class="btn" type="submit">Filter</button></form>';
echo '<div class="table-wrap"><table><tr><th></th><th>Name</th><th>SKU</th><th>Category</th><th>Price</th><th>Stock</th><th>Active</th><th>Bestseller</th><th>On offer</th><th></th></tr>';
foreach ($rows as $row) {
    $free = (int) $row['stock_qty'] - (int) $row['reserved_qty'];
    $id = (int) $row['id'];
    echo '<tr><td><img class="thumb" src="' . e(product_image((string) $row['image_url'])) . '" alt=""></td>';
    echo '<td><a href="/admin/product-edit?id=' . $id . '">' . e($row['name']) . '</a>';
    if ($free <= 5) {
        echo ' <span class="pill pill-used-up">Low stock</span>';
    }
    echo '</td><td>' . e($row['sku']) . '</td><td>' . e($row['category_name']) . '</td><td>' . inr((int) $row['price_inr']) . '</td><td>' . $free . ' free</td>';
    foreach (['active' => 'Active', 'bestseller' => 'Bestseller', 'on_offer' => 'On offer'] as $field => $label) {
        $on = (int) $row[$field] === 1;
        echo '<td><form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="action" value="' . $field . '"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="value" value="' . ($on ? '0' : '1') . '"><button class="linkish" type="submit">' . ($on ? 'On' : 'Off') . '</button></form></td>';
    }
    echo '<td><a href="/admin/product-edit?id=' . $id . '">Edit</a> ';
    echo '<form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . $id . '"><button class="linkish" type="submit" data-confirm="Delete or hide this tray?">Delete</button></form></td></tr>';
}
echo '</table></div>';
admin_close();
