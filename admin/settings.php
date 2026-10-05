<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    foreach (['shipping_flat_inr', 'free_shipping_over_inr'] as $key) {
        $value = (string) max(0, (int) ($_POST[$key] ?? 0));
        db()->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)')->execute([$key, $value]);
        audit_log((int) $admin['id'], 'setting', 'setting', 0, $key, $value);
    }
    header('Location: /admin/settings');
    exit;
}
admin_open('Settings');
echo '<form method="post" class="narrow">' . csrf_field();
echo '<label>Shipping flat INR <input name="shipping_flat_inr" type="number" value="' . e(setting('shipping_flat_inr', '180')) . '"></label>';
echo '<label>Free shipping over INR <input name="free_shipping_over_inr" type="number" value="' . e(setting('free_shipping_over_inr', '4000')) . '"></label>';
echo '<button class="btn">Save</button></form>';
echo '<p class="muted">SMS codes use the Twilio keys in config/config.local.php. They are not stored in this form.</p>';
admin_close();
