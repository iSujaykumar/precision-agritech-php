<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
require_desk($admin, 'manager');
$error = null;
$keys = [
    'hero_kicker' => 'Eyebrow',
    'hero_title' => 'Headline',
    'hero_text' => 'Intro',
    'hero_primary_label' => 'First button',
    'hero_primary_href' => 'First button link',
    'hero_secondary_label' => 'Second button',
    'hero_secondary_href' => 'Second button link',
    'rail_best_title' => 'Best sellers title',
    'rail_new_title' => 'New arrivals title',
    'rail_offers_title' => 'Offers title',
    'rail_limit' => 'Trays in each rail',
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $limit = (int) ($_POST['rail_limit'] ?? 8);
        if ($limit < 4 || $limit > 24) {
            throw new RuntimeException('Each rail can show 4 to 24 trays.');
        }
        foreach (['hero_primary_href', 'hero_secondary_href'] as $href) {
            if (safe_shop_href((string) ($_POST[$href] ?? ''), '') === '') {
                throw new RuntimeException('Button links must start with / and stay on this site.');
            }
        }
        if (trim((string) ($_POST['hero_title'] ?? '')) === '') {
            throw new RuntimeException('The headline cannot be empty.');
        }
        foreach (array_keys($keys) as $key) {
            $value = $key === 'rail_limit' ? (string) $limit : clean_text((string) ($_POST[$key] ?? ''), 180);
            save_setting($key, $value);
        }
        save_setting('rail_best_on', isset($_POST['rail_best_on']) ? '1' : '0');
        save_setting('rail_new_on', isset($_POST['rail_new_on']) ? '1' : '0');
        save_setting('rail_offers_on', isset($_POST['rail_offers_on']) ? '1' : '0');
        audit_log((int) $admin['id'], 'settings', 'setting', 0, null, 'homepage');
        flash_set('Homepage saved');
        header('Location: /admin/homepage');
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'The homepage could not be saved.');
    }
}
admin_open('Homepage', 'settings');
if ($error) {
    echo '<p class="flash bad" role="alert">' . e($error) . '</p>';
}
echo '<p class="muted">Empty values are not used. The shop falls back to the original wording.</p>';
echo '<form method="post" class="narrow">' . csrf_field();
foreach ($keys as $key => $label) {
    $value = array_key_exists($key, $_POST) ? (string) $_POST[$key] : setting($key, '');
    $type = $key === 'rail_limit' ? 'number' : 'text';
    echo '<label>' . e($label) . ' <input name="' . e($key) . '" type="' . $type . '" value="' . e($value) . '"></label>';
}
foreach (['rail_best_on' => 'Show best sellers', 'rail_new_on' => 'Show new arrivals', 'rail_offers_on' => 'Show offers'] as $key => $label) {
    echo '<label><input type="checkbox" name="' . e($key) . '" ' . (setting($key, '1') === '1' ? 'checked' : '') . '> ' . e($label) . '</label>';
}
echo '<button class="btn" type="submit">Save homepage</button></form>';
admin_close();
