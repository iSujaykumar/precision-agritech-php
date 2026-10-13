<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
require_role($admin, 'owner');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        if (isset($_POST['reset'])) {
            save_setting('ui_labels_en', '');
            save_setting('ui_labels_mr', '');
            audit_log((int) $admin['id'], 'labels_reset', 'settings', 0, '', '');
            flash_set('Labels reset to the defaults.');
        } else {
            $lang = ($_POST['lang'] ?? '') === 'mr' ? 'mr' : 'en';
            $out = [];
            foreach (ui_label_defaults()[$lang] as $key => $unused) {
                $out[$key] = clean_text((string) ($_POST[$key] ?? ''), 80);
            }
            save_setting('ui_labels_' . $lang, json_encode($out, JSON_UNESCAPED_UNICODE));
            audit_log((int) $admin['id'], 'labels', 'settings', 0, $lang, 'saved');
            flash_set('Labels saved.');
        }
        header('Location: /admin/labels');
        exit;
    } catch (Throwable $err) {
        flash_set('That could not be saved.', false);
    }
}
admin_open('Shop labels', 'homepage');
foreach (['en' => 'English', 'mr' => 'Marathi'] as $lang => $title) {
    $custom = json_decode(setting('ui_labels_' . $lang, ''), true);
    if (!is_array($custom)) {
        $custom = [];
    }
    echo '<h2>' . e($title) . '</h2><form method="post">' . csrf_field() . '<input type="hidden" name="lang" value="' . e($lang) . '">';
    foreach (ui_label_defaults()[$lang] as $key => $fallback) {
        echo '<label>' . e($key) . ' <input name="' . e($key) . '" value="' . e((string) ($custom[$key] ?? '')) . '" placeholder="' . e($fallback) . '"></label>';
    }
    echo '<button class="btn" type="submit">Save ' . e($title) . '</button></form>';
}
echo '<form method="post">' . csrf_field() . '<button class="btn" name="reset" value="1" type="submit">Reset to default</button></form>';
admin_close();
