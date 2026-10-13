<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
require_role($admin, 'manager');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        $mode = (string) ($_POST['whatsapp_mode'] ?? 'random');
        if (!in_array($mode, ['random', 'round_robin', 'fixed'], true)) {
            $mode = 'random';
        }
        save_setting('whatsapp_mode', $mode);
        save_setting('whatsapp_fixed_id', (string) max(0, (int) ($_POST['whatsapp_fixed_id'] ?? 0)));
        save_setting('seasonal_list_public', isset($_POST['seasonal_list_public']) ? '1' : '0');
        $ids = $_POST['id'] ?? [];
        $upd = db()->prepare('UPDATE phone_numbers SET label=?, sort_order=?, enabled=?, show_public=?, whatsapp_enabled=? WHERE id=?');
        foreach ($ids as $i => $id) {
            $digits = preg_replace('/\D+/', '', (string) ($_POST['digits'][$i] ?? '')) ?? '';
            if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
                $digits = substr($digits, 2);
            }
            if (!preg_match('/^[6-9][0-9]{9}$/', $digits)) {
                throw new RuntimeException('Each phone must be a 10-digit Indian mobile number.');
            }
            db()->prepare('UPDATE phone_numbers SET digits=? WHERE id=?')->execute([$digits, (int) $id]);
            $upd->execute([
                substr(trim((string) ($_POST['label'][$i] ?? '')), 0, 40),
                (int) ($_POST['sort'][$i] ?? $i),
                isset($_POST['enabled'][$id]) ? 1 : 0,
                isset($_POST['show_public'][$id]) ? 1 : 0,
                isset($_POST['whatsapp_enabled'][$id]) ? 1 : 0,
                (int) $id,
            ]);
        }
        audit_log((int) $admin['id'], 'phones', 'settings', 0, null, $mode);
        flash_set('Phone numbers saved.');
    } catch (Throwable $err) {
        flash_set(safe_error($err, 'Could not save the phone numbers.'), false);
    }
    header('Location: /admin/phones');
    exit;
}
admin_open('Phone numbers', 'settings');
$rows = db()->query('SELECT * FROM phone_numbers ORDER BY sort_order, id')->fetchAll();
$flash = flash_take();
if ($flash) {
    echo '<p class="flash ' . e($flash['kind']) . '">' . e($flash['message']) . '</p>';
}
echo '<form method="post">' . csrf_field();
echo '<label>WhatsApp mode <select name="whatsapp_mode">';
foreach (['random' => 'Random (sticky per visitor)', 'round_robin' => 'Round robin', 'fixed' => 'Fixed number'] as $value => $label) {
    $on = setting('whatsapp_mode', 'random') === $value ? ' selected' : '';
    echo '<option value="' . e($value) . '"' . $on . '>' . e($label) . '</option>';
}
echo '</select></label>';
echo '<label>Fixed WhatsApp id <input name="whatsapp_fixed_id" value="' . e(setting('whatsapp_fixed_id', '0')) . '"></label>';
echo '<label><input type="checkbox" name="seasonal_list_public" ' . (setting('seasonal_list_public', '1') === '1' ? 'checked' : '') . '> Show the public seasonal price list</label>';
echo '<div class="table-wrap"><table><tr><th>Number</th><th>Label</th><th>Order</th><th>On</th><th>Public</th><th>WhatsApp</th></tr>';
foreach ($rows as $i => $row) {
    $id = (int) $row['id'];
    echo '<tr><td><input type="hidden" name="id[]" value="' . $id . '"><input name="digits[]" value="' . e((string) $row['digits']) . '"></td>';
    echo '<td><input name="label[]" value="' . e((string) $row['label']) . '"></td>';
    echo '<td><input name="sort[]" value="' . (int) $row['sort_order'] . '"></td>';
    echo '<td><input type="checkbox" name="enabled[' . $id . ']" ' . ((int) $row['enabled'] ? 'checked' : '') . '></td>';
    echo '<td><input type="checkbox" name="show_public[' . $id . ']" ' . ((int) $row['show_public'] ? 'checked' : '') . '></td>';
    echo '<td><input type="checkbox" name="whatsapp_enabled[' . $id . ']" ' . ((int) $row['whatsapp_enabled'] ? 'checked' : '') . '></td></tr>';
}
echo '</table></div><button class="btn" type="submit">Save phones</button></form>';
admin_close();
