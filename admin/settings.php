<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $numberKeys = ['shipping_flat_inr', 'free_shipping_over_inr', 'cod_reserve_hours', 'bank_reserve_hours'];
        $textKeys = ['bank_account_name', 'bank_name', 'bank_account_number', 'bank_ifsc', 'upi_id', 'contact_phone', 'contact_email', 'whatsapp_number', 'business_hours'];
        foreach (array_merge($numberKeys, $textKeys) as $key) {
            $value = in_array($key, $numberKeys, true)
                ? (string) max(0, (int) ($_POST[$key] ?? 0))
                : trim((string) ($_POST[$key] ?? ''));
            $before = setting($key, '');
            db()->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)')->execute([$key, $value]);
            audit_log((int) $admin['id'], 'setting', 'setting', 0, $before, $key . '=' . $value);
        }
        flash('success', 'Settings saved.');
    } catch (Throwable $err) {
        flash('error', safe_error($err, 'The settings could not be saved.'));
    }
    header('Location: /admin/settings');
    exit;
}
admin_open('Settings');
echo '<form method="post" class="narrow">' . csrf_field();
$fields = [
    'shipping_flat_inr' => 'Delivery charge in rupees',
    'free_shipping_over_inr' => 'Free delivery from (rupees)',
    'cod_reserve_hours' => 'Hours a cash order holds trays',
    'bank_reserve_hours' => 'Hours a bank order holds trays',
    'bank_account_name' => 'Bank account name',
    'bank_name' => 'Bank name',
    'bank_account_number' => 'Account number',
    'bank_ifsc' => 'IFSC',
    'upi_id' => 'UPI ID',
    'contact_phone' => 'Phone shown on the site',
    'contact_email' => 'Email shown on the site',
    'whatsapp_number' => 'WhatsApp number',
    'business_hours' => 'Hours',
];
foreach ($fields as $key => $label) {
    echo '<label>' . e($label) . ' <input name="' . e($key) . '" value="' . e(setting($key, '')) . '"></label>';
}
echo '<button class="btn">Save</button></form>';
echo '<p class="muted">SMS, mail and online payment keys stay in config.local.php, not in this form. Tax is not added. The unused tax setting has been left out of the shop total.</p>';
admin_close();
