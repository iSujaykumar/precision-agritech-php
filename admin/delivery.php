<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $action = (string) ($_POST['action'] ?? 'save');
        if ($action === 'driver') {
            $name = clean_text((string) ($_POST['name'] ?? ''), 80);
            $phone = normalize_phone((string) ($_POST['phone'] ?? ''));
            $vehicle = clean_text((string) ($_POST['vehicle'] ?? ''), 20);
            if (strlen($name) < 2 || strlen($vehicle) < 2) {
                throw new RuntimeException('Enter the driver name, mobile and vehicle number.');
            }
            db()->prepare('INSERT INTO drivers (name, phone, vehicle_no) VALUES (?, ?, ?)')->execute([$name, $phone, $vehicle]);
            flash_set('Driver added');
        } elseif ($action === 'driver-off') {
            db()->prepare('UPDATE drivers SET active = 0 WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
            flash_set('Driver hidden');
        } else {
            save_setting('delivery_pins', trim((string) ($_POST['delivery_pins'] ?? '')));
            save_setting('delivery_area_text', clean_text((string) ($_POST['delivery_area_text'] ?? ''), 300));
            save_setting('zone_shipping', trim((string) ($_POST['zone_shipping'] ?? '')));
            save_setting('shipping_flat_inr', (string) max(0, (int) ($_POST['shipping_flat_inr'] ?? 0)));
            save_setting('free_shipping_over_inr', (string) max(0, (int) ($_POST['free_shipping_over_inr'] ?? 0)));
            save_setting('cod_min_inr', (string) max(0, (int) ($_POST['cod_min_inr'] ?? 0)));
            save_setting('cod_max_inr', (string) max(0, (int) ($_POST['cod_max_inr'] ?? 0)));
            save_setting('require_phone_confirm', isset($_POST['require_phone_confirm']) ? '1' : '0');
            save_setting('show_driver_phone', isset($_POST['show_driver_phone']) ? '1' : '0');
            save_setting('claim_hours', (string) max(1, (int) ($_POST['claim_hours'] ?? 48)));
            audit_log((int) $admin['id'], 'settings', 'setting', 0, null, 'delivery');
            flash_set('Delivery settings saved');
        }
        header('Location: /admin/delivery');
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'That could not be saved.');
    }
}
$drivers = db()->query('SELECT * FROM drivers ORDER BY active DESC, name')->fetchAll();
admin_open('Delivery', 'settings');
if (!empty($error)) {
    echo '<p class="flash bad" role="alert">' . e($error) . '</p>';
}
if (trim(setting('delivery_pins', '')) === '') {
    echo '<p class="flash bad">Set your delivery PIN codes. Until then every PIN is allowed.</p>';
}
echo '<form method="post" class="narrow">' . csrf_field();
echo '<label>PIN codes, one per line or as a range <textarea name="delivery_pins">' . e(setting('delivery_pins', '')) . '</textarea></label>';
echo '<label>We deliver to <textarea name="delivery_area_text">' . e(setting('delivery_area_text', '')) . '</textarea></label>';
echo '<label>Zone charges, for example 411001-411040=80 <textarea name="zone_shipping">' . e(setting('zone_shipping', '')) . '</textarea></label>';
echo '<label>Flat delivery charge <input name="shipping_flat_inr" type="number" value="' . e(setting('shipping_flat_inr', '180')) . '"></label>';
echo '<label>Free delivery over <input name="free_shipping_over_inr" type="number" value="' . e(setting('free_shipping_over_inr', '4000')) . '"></label>';
echo '<label>Minimum cash on delivery (0 means no minimum) <input name="cod_min_inr" type="number" value="' . e(setting('cod_min_inr', '0')) . '"></label>';
echo '<label>Maximum cash on delivery (0 means no maximum) <input name="cod_max_inr" type="number" value="' . e(setting('cod_max_inr', '0')) . '"></label>';
echo '<label>Hours to report a problem <input name="claim_hours" type="number" value="' . e(setting('claim_hours', '48')) . '"></label>';
echo '<label><input type="checkbox" name="require_phone_confirm" ' . (setting('require_phone_confirm', '1') === '1' ? 'checked' : '') . '> Confirm cash orders by phone before dispatch</label>';
echo '<label><input type="checkbox" name="show_driver_phone" ' . (setting('show_driver_phone', '0') === '1' ? 'checked' : '') . '> Show the driver mobile to the customer</label>';
echo '<button class="btn" type="submit">Save</button></form>';
echo '<h2>Drivers</h2><form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="action" value="driver">';
echo '<label>Name <input name="name" required></label><label>Mobile <input name="phone" required></label><label>Vehicle <input name="vehicle" required></label><button class="btn" type="submit">Add driver</button></form>';
echo '<div class="table-wrap"><table><tr><th>Name</th><th>Mobile</th><th>Vehicle</th><th></th></tr>';
foreach ($drivers as $driver) {
    echo '<tr><td>' . e($driver['name']) . '</td><td>' . e($driver['phone']) . '</td><td>' . e($driver['vehicle_no']) . '</td><td>';
    if ((int) $driver['active'] === 1) {
        echo '<form method="post">' . csrf_field() . '<input type="hidden" name="action" value="driver-off"><input type="hidden" name="id" value="' . (int) $driver['id'] . '"><button class="text-link" type="submit">Hide</button></form>';
    }
    echo '</td></tr>';
}
echo '</table></div><p><a href="/admin/cod-block">Cash on delivery block list</a> · <a href="/admin/deliveries">Today\'s deliveries</a></p>';
admin_close();
