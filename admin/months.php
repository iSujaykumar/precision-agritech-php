<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
require_desk($admin, 'manager');
$names = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        for ($month = 1; $month <= 12; $month++) {
            $text = clean_text((string) ($_POST['m' . $month] ?? ''), 300);
            $before = setting('plant_month_' . $month, '');
            save_setting('plant_month_' . $month, $text);
            audit_log((int) $admin['id'], 'plant_month', 'settings', $month, $before, $text);
        }
        flash_set('Month list saved.');
        header('Location: /admin/months');
        exit;
    } catch (Throwable $err) {
        flash_set('That could not be saved.', false);
    }
}
admin_open('Plant this month', 'homepage');
echo '<form method="post">' . csrf_field();
foreach ($names as $month => $label) {
    echo '<label>' . e($label) . ' <input name="m' . $month . '" value="' . e(month_plant_text($month)) . '"></label>';
}
echo '<p class="muted">Example: Petunia, Pansy, Antirrhinum</p><button class="btn" type="submit">Save</button></form>';
admin_close();
