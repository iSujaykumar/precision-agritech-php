<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $name = trim((string) ($_POST['name'] ?? ''));
        $slug = strtolower(trim((string) ($_POST['slug'] ?? '')));
        $sort = (int) ($_POST['sort_order'] ?? 0);
        if ($name === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
            throw new RuntimeException('Check the category name and slug.');
        }
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            db()->prepare('UPDATE categories SET name = ?, slug = ?, sort_order = ? WHERE id = ?')->execute([$name, $slug, $sort, $id]);
            audit_log((int) $admin['id'], 'category_update', 'category', $id, null, $name);
        } else {
            db()->prepare('INSERT INTO categories (name, slug, sort_order) VALUES (?, ?, ?)')->execute([$name, $slug, $sort]);
            audit_log((int) $admin['id'], 'category_create', 'category', (int) db()->lastInsertId(), null, $name);
        }
        header('Location: /admin/categories');
        exit;
    } catch (Throwable $err) {
        error_log($err->getMessage());
        $error = 'The category could not be saved. It may still contain a duplicate slug.';
    }
}
admin_open('Categories');
if ($error) echo '<p class="flash">' . e($error) . '</p>';
$rows = db()->query('SELECT * FROM categories ORDER BY sort_order')->fetchAll();
echo '<div class="table-wrap"><table>';
foreach ($rows as $row) {
    echo '<tr><td colspan="3"><form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="id" value="' . (int) $row['id'] . '"><input name="name" value="' . e($row['name']) . '" aria-label="Category name"> <input name="slug" value="' . e($row['slug']) . '" aria-label="Slug"> <input name="sort_order" type="number" value="' . (int) $row['sort_order'] . '" aria-label="Sort order"> <button class="btn" type="submit">Save</button></form></td></tr>';
}
echo '</table></div><h2>Add</h2><form method="post" class="narrow">' . csrf_field() . '<label>Name <input name="name" required></label><label>Slug <input name="slug" required></label><label>Order <input name="sort_order" type="number" value="10"></label><button class="btn" type="submit">Add category</button></form>';
admin_close();
