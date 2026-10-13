<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
require_role($admin, 'manager');
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
        save_setting('calc_menu_on', isset($_POST['calc_menu_on']) ? '1' : '0');
        save_setting('browse_on', isset($_POST['browse_on']) ? '1' : '0');
        save_setting('browse_title', clean_text((string) ($_POST['browse_title'] ?? 'Browse all plants'), 80));
        save_setting('browse_chip_order', clean_text((string) ($_POST['browse_chip_order'] ?? ''), 240));
        save_setting('calc_label_en', clean_text((string) ($_POST['calc_label_en'] ?? ''), 40));
        save_setting('calc_label_mr', clean_text((string) ($_POST['calc_label_mr'] ?? ''), 40));
        $state = home_section_state();
        $posted = $_POST['section_on'] ?? [];
        if (!is_array($posted)) {
            $posted = [];
        }
        foreach ($state as $id => $was) {
            $state[$id] = isset($posted[$id]);
        }
        if (isset(home_section_defs()[(string) ($_POST['move_id'] ?? '')])) {
            $keys = array_keys($state);
            $id = (string) $_POST['move_id'];
            $index = array_search($id, $keys, true);
            $swap = (($_POST['move_dir'] ?? '') === 'up') ? $index - 1 : $index + 1;
            if ($index !== false && isset($keys[$swap])) {
                $tmp = $keys[$index];
                $keys[$index] = $keys[$swap];
                $keys[$swap] = $tmp;
                $next = [];
                foreach ($keys as $key) {
                    $next[$key] = $state[$key];
                }
                $state = $next;
            }
        }
        save_home_sections($state);
        audit_log((int) $admin['id'], 'settings', 'setting', 0, null, 'homepage');
        flash_set('Homepage saved');
        header('Location: /admin/homepage');
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'The homepage could not be saved.');
    }
}
admin_open('Homepage', 'homepage');
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
foreach (['rail_best_on' => 'Show best sellers', 'rail_new_on' => 'Show new arrivals', 'rail_offers_on' => 'Show offers', 'calc_menu_on' => 'Show Order calculator in the menu', 'browse_on' => 'Show Browse all plants'] as $key => $label) {
    echo '<label data-setting="' . e($label) . '"><input type="checkbox" name="' . e($key) . '" ' . (setting($key, '1') === '1' ? 'checked' : '') . '> ' . e($label) . '</label>';
}
echo '<label data-setting="browse title">Browse all plants title <input name="browse_title" value="' . e(setting('browse_title', 'Browse all plants')) . '"></label>';
echo '<label data-setting="category chip order">Category chip order, names separated by commas <input name="browse_chip_order" value="' . e(setting('browse_chip_order', '')) . '" placeholder="Flowers, Foliage"></label>';
echo '<p class="muted">Leave the chip order blank to use the shop category order.</p>';
echo '<label data-setting="calculator english">Order calculator label in English <input name="calc_label_en" value="' . e(setting('calc_label_en', '')) . '" placeholder="Order calculator"></label>';
echo '<label data-setting="calculator marathi">Order calculator label in Marathi <input name="calc_label_mr" value="' . e(setting('calc_label_mr', '')) . '" placeholder="ऑर्डर कॅल्क्युलेटर"></label>';
echo '<h2>Section order</h2><p class="muted">Untick a section to hide it. Up and Down move it. Save at the bottom.</p>';
foreach (home_section_state() as $id => $on) {
    $label = home_section_defs()[$id];
    echo '<p data-setting="' . e($label) . '"><label><input type="checkbox" name="section_on[' . e($id) . ']" ' . ($on ? 'checked' : '') . '> ' . e($label) . '</label> ';
    echo '<button class="btn" name="move_id" value="' . e($id) . '" type="submit" onclick="this.form.move_dir.value=\'up\'">Up</button> ';
    echo '<button class="btn" name="move_id" value="' . e($id) . '" type="submit" onclick="this.form.move_dir.value=\'down\'">Down</button></p>';
}
echo '<input type="hidden" name="move_dir" value="">';
echo '<button class="btn" type="submit">Save homepage</button></form>';
admin_close();
