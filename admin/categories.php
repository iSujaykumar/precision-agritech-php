<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $action = (string) ($_POST['action'] ?? 'save');
        if ($action === 'up' || $action === 'down') {
            $id = (int) ($_POST['id'] ?? 0);
            $ids = array_map('intval', db()->query('SELECT id FROM categories ORDER BY sort_order, id')->fetchAll(PDO::FETCH_COLUMN));
            $index = array_search($id, $ids, true);
            $swap = $action === 'up' ? $index - 1 : $index + 1;
            if ($index === false || $swap < 0 || $swap >= count($ids)) {
                throw new RuntimeException('That category is already at the end of the list.');
            }
            [$ids[$index], $ids[$swap]] = [$ids[$swap], $ids[$index]];
            $stmt = db()->prepare('UPDATE categories SET sort_order = ? WHERE id = ?');
            foreach ($ids as $n => $catId) {
                $stmt->execute([$n + 1, $catId]);
            }
            audit_log((int) $admin['id'], 'category_order', 'category', $id, null, $action);
            flash_set('Order updated');
            header('Location: /admin/categories');
            exit;
        }
        if ($action === 'move') {
            $id = (int) ($_POST['id'] ?? 0);
            $to = (int) ($_POST['move_to'] ?? 0);
            if ($to < 1 || $to === $id) {
                throw new RuntimeException('Choose another category to move the products into.');
            }
            db()->prepare('UPDATE products SET category_id = ? WHERE category_id = ?')->execute([$to, $id]);
            audit_log((int) $admin['id'], 'category_move', 'category', $id, null, (string) $to);
            flash_set('Products moved. You can delete the empty category now.');
            header('Location: /admin/categories?edit=' . $id);
            exit;
        }
        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $count = db()->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
            $count->execute([$id]);
            if ((int) $count->fetchColumn() > 0) {
                throw new RuntimeException('Move its products first.');
            }
            db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
            renumber_categories();
            audit_log((int) $admin['id'], 'category_delete', 'category', $id, null, 'deleted');
            flash_set('Category deleted');
            header('Location: /admin/categories');
            exit;
        }
        if ($action === 'active') {
            $id = (int) ($_POST['id'] ?? 0);
            $active = (int) ($_POST['active'] ?? 0) === 1 ? 1 : 0;
            db()->prepare('UPDATE categories SET active = ? WHERE id = ?')->execute([$active, $id]);
            audit_log((int) $admin['id'], 'category_active', 'category', $id, null, (string) $active);
            flash_set('Category saved');
            header('Location: /admin/categories');
            exit;
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        $slug = slugify((string) ($_POST['slug'] ?? ''));
        if ($slug === '') {
            $slug = slugify($name);
        }
        if ($name === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
            throw new RuntimeException('Use a name and a slug made of letters, numbers and hyphens.');
        }
        $id = (int) ($_POST['id'] ?? 0);
        $taken = db()->prepare('SELECT id FROM categories WHERE slug = ? AND id <> ?');
        $taken->execute([$slug, $id]);
        if ($taken->fetch()) {
            throw new RuntimeException('That slug is already used.');
        }
        $active = isset($_POST['active']) ? 1 : 0;
        if ($id) {
            $old = db()->prepare('SELECT slug FROM categories WHERE id = ?');
            $old->execute([$id]);
            $oldSlug = (string) $old->fetchColumn();
            db()->prepare('UPDATE categories SET name = ?, slug = ?, active = ? WHERE id = ?')->execute([$name, $slug, $active, $id]);
            audit_log((int) $admin['id'], 'category_update', 'category', $id, $oldSlug, $slug);
            flash_set($oldSlug !== $slug ? 'Category saved. Old shop links for this category will stop working.' : 'Category saved');
        } else {
            $next = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM categories')->fetchColumn();
            db()->prepare('INSERT INTO categories (name, slug, sort_order, active) VALUES (?, ?, ?, ?)')->execute([$name, $slug, $next, $active]);
            renumber_categories();
            audit_log((int) $admin['id'], 'category_create', 'category', (int) db()->lastInsertId(), null, $name);
            flash_set('Category saved');
        }
        header('Location: /admin/categories');
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'The category could not be saved.');
    }
}

$rows = db()->query('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count FROM categories c ORDER BY c.sort_order, c.id')->fetchAll();
$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
foreach ($rows as $row) {
    if ((int) $row['id'] === $editId) {
        $edit = $row;
    }
}
admin_open('Categories', 'products');
if ($error) {
    echo '<p class="flash bad" role="alert">' . e($error) . '</p>';
}
echo '<div class="table-wrap"><table><tr><th>Order</th><th>Name</th><th>Slug</th><th>Products</th><th>Shop</th><th>Actions</th></tr>';
$last = count($rows) - 1;
foreach ($rows as $i => $row) {
    $id = (int) $row['id'];
    echo '<tr><td>';
    if ($i > 0) {
        echo '<form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="action" value="up"><input type="hidden" name="id" value="' . $id . '"><button class="btn" type="submit">Up</button></form> ';
    }
    if ($i < $last) {
        echo '<form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="action" value="down"><input type="hidden" name="id" value="' . $id . '"><button class="btn" type="submit">Down</button></form>';
    }
    echo '</td><td>' . e($row['name']) . '</td><td>' . e($row['slug']) . '</td><td>' . (int) $row['product_count'] . '</td>';
    echo '<td><form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="action" value="active"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="active" value="' . ($row['active'] ? '0' : '1') . '"><button class="linkish" type="submit">' . ($row['active'] ? 'Shown' : 'Hidden') . '</button></form></td>';
    echo '<td><a href="/admin/categories?edit=' . $id . '">Edit</a></td></tr>';
}
echo '</table></div>';

$editing = $edit ?: ['id' => 0, 'name' => '', 'slug' => '', 'active' => 1, 'product_count' => 0];
echo '<h2>' . ($edit ? 'Edit category' : 'Add category') . '</h2>';
echo '<form method="post" class="narrow">' . csrf_field();
echo '<input type="hidden" name="action" value="save"><input type="hidden" name="id" value="' . (int) $editing['id'] . '">';
echo '<label>Name <input name="name" required value="' . e((string) $editing['name']) . '"></label>';
echo '<label>Slug <input name="slug" value="' . e((string) $editing['slug']) . '" placeholder="Filled from the name if left blank"></label>';
if ($edit) {
    echo '<p class="muted">If you change the slug, old shop links for this category will stop working.</p>';
}
echo '<label><input type="checkbox" name="active" ' . (!empty($editing['active']) ? 'checked' : '') . '> Show on shop</label>';
echo '<button class="btn" type="submit">' . ($edit ? 'Save category' : 'Add category') . '</button></form>';

if ($edit && (int) $edit['product_count'] > 0) {
    echo '<h2>Move its ' . (int) $edit['product_count'] . ' products first</h2>';
    echo '<form method="post" class="narrow">' . csrf_field() . '<input type="hidden" name="action" value="move"><input type="hidden" name="id" value="' . (int) $edit['id'] . '">';
    echo '<label>Move products to <select name="move_to">';
    foreach ($rows as $row) {
        if ((int) $row['id'] === (int) $edit['id']) {
            continue;
        }
        echo '<option value="' . (int) $row['id'] . '">' . e($row['name']) . '</option>';
    }
    echo '</select></label><button class="btn" type="submit">Move products</button></form>';
    echo '<p class="muted">This category still has products, so it cannot be deleted yet.</p>';
} elseif ($edit) {
    echo '<form method="post">' . csrf_field() . '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . (int) $edit['id'] . '">';
    echo '<button class="btn" type="submit" data-confirm="Delete this category?">Delete category</button></form>';
}
admin_close();
