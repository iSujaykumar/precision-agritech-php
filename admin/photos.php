<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
require_desk($admin, 'staff');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $files = $_FILES['photos'] ?? null;
        if (!is_array($files) || !isset($files['name']) || !is_array($files['name'])) {
            throw new RuntimeException('Choose one or more images.');
        }
        $done = 0;
        foreach ($files['name'] as $i => $name) {
            if ((int) ($files['error'][$i] ?? 1) !== UPLOAD_ERR_OK) {
                continue;
            }
            $slug = list_slug((string) pathinfo((string) $name, PATHINFO_FILENAME));
            save_plant_photo([
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i],
                'name' => $name,
            ], $slug);
            audit_log((int) $admin['id'], 'photo', 'product', 0, '', $slug);
            $done++;
        }
        flash_set($done . ' photo' . ($done === 1 ? '' : 's') . ' saved.');
        header('Location: /admin/photos');
        exit;
    } catch (Throwable $err) {
        flash_set($err->getMessage(), false);
    }
}
$only = (string) ($_GET['filter'] ?? '') === 'placeholder';
$rows = photo_rows($only);
admin_open('Plant photos', 'plants');
echo '<p><a href="/admin/photos">All</a> · <a href="/admin/photos?filter=placeholder">Plants with placeholder photos</a></p>';
echo '<form method="post" enctype="multipart/form-data">' . csrf_field();
echo '<label>Choose many files. The file name must be the plant slug. <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required></label>';
echo '<button class="btn" type="submit">Upload</button></form>';
echo '<ul>';
foreach ($rows as $row) {
    echo '<li>' . e($row['name']) . ' <span class="muted">' . e($row['slug']) . '</span>';
    if ((int) $row['image_is_placeholder'] === 1) {
        echo ' placeholder';
    }
    echo '</li>';
}
echo '</ul>';
admin_close();
