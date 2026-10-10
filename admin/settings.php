<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
$fields = [
    'shop_phone' => 'Shop phone',
    'whatsapp_number' => 'WhatsApp number',
    'shop_email' => 'Email',
    'mail_from' => 'Mail from',
    'shop_address' => 'Address',
    'opening_hours' => 'Opening hours',
    'map_url' => 'Map link',
    'shipping_flat_inr' => 'Shipping flat INR',
    'free_shipping_over_inr' => 'Free shipping over INR',
    'min_order_inr' => 'Minimum order INR',
    'payment_instructions' => 'Payment instructions',
    'seller_legal_name' => 'Legal name',
    'gstin' => 'GSTIN',
    'cin' => 'CIN',
    'hsn_code' => 'HSN code',
    'gst_rate' => 'GST rate percent',
    'grievance_officer_name' => 'Grievance officer name',
    'grievance_officer_phone' => 'Grievance officer phone',
    'grievance_officer_email' => 'Grievance officer email',
    'announcement' => 'Announcement',
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        foreach (['shipping_flat_inr', 'free_shipping_over_inr', 'min_order_inr'] as $key) {
            save_setting($key, (string) max(0, (int) ($_POST[$key] ?? 0)));
        }
        foreach (['shop_phone', 'whatsapp_number', 'shop_email', 'mail_from', 'shop_address', 'opening_hours', 'map_url', 'payment_instructions', 'announcement', 'seller_legal_name', 'gstin', 'cin', 'hsn_code', 'gst_rate', 'grievance_officer_name', 'grievance_officer_phone', 'grievance_officer_email'] as $key) {
            save_setting($key, trim((string) ($_POST[$key] ?? '')));
        }
        save_setting('announcement_on', isset($_POST['announcement_on']) ? '1' : '0');
        save_setting('shop_paused', isset($_POST['shop_paused']) ? '1' : '0');
        audit_log((int) $admin['id'], 'settings', 'setting', 0, null, 'shop');
        flash_set('Settings saved');
        header('Location: /admin/settings');
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'Those settings could not be saved.');
    }
}
admin_open('Shop settings', 'settings');
if (!empty($error)) {
    echo '<p class="flash bad" role="alert">' . e($error) . '</p>';
}
echo '<form method="post" class="narrow">' . csrf_field();
foreach ($fields as $key => $label) {
    $value = setting($key, '');
    if ($key === 'payment_instructions' || $key === 'announcement' || $key === 'shop_address') {
        echo '<label>' . e($label) . ' <textarea name="' . e($key) . '">' . e($value) . '</textarea></label>';
    } else {
        $type = str_contains($key, 'inr') ? 'number' : 'text';
        echo '<label>' . e($label) . ' <input name="' . e($key) . '" type="' . $type . '" value="' . e($value) . '"></label>';
    }
}
echo '<label><input type="checkbox" name="announcement_on" ' . (setting('announcement_on', '0') === '1' ? 'checked' : '') . '> Show announcement</label>';
echo '<label><input type="checkbox" name="shop_paused" ' . (setting('shop_paused', '0') === '1' ? 'checked' : '') . '> Shop paused</label>';
echo '<button class="btn" type="submit">Save</button></form>';
admin_close();
