<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
require_desk($admin, 'manager');
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
    'max_unpaid_phone' => 'Max open unpaid orders per phone',
    'max_unpaid_ip' => 'Max open unpaid orders per network',
    'max_trays_phone' => 'Max trays held unpaid per phone',
    'cod_fail_limit' => 'Failed deliveries before cash is blocked',
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $percent = (int) ($_POST['seasonal_discount_percent'] ?? 10);
        $minInr = (int) ($_POST['seasonal_discount_min_inr'] ?? 5000);
        $minSeed = (int) ($_POST['discount_min_seedlings'] ?? 5000);
        $floor = (int) ($_POST['nursery_min_seedling_paise'] ?? 100);
        if ($percent < 0 || $percent > 50 || $minInr < 0 || $minSeed < 0 || $floor < 1) {
            throw new RuntimeException('Check the discount numbers. Percent is 0 to 50. The nursery minimum is at least 1 paise.');
        }
        $basis = (($_POST['discount_basis'] ?? '') === 'seedlings') ? 'seedlings' : 'rupees';
        save_setting('seasonal_discount_on', isset($_POST['seasonal_discount_on']) ? '1' : '0');
        save_setting('seasonal_discount_percent', (string) $percent);
        save_setting('seasonal_discount_min_inr', (string) $minInr);
        save_setting('discount_min_seedlings', (string) $minSeed);
        save_setting('discount_basis', $basis);
        save_setting('offer_allows_bulk', isset($_POST['offer_allows_bulk']) ? '1' : '0');
        save_setting('nursery_min_seedling_paise', (string) $floor);
        save_setting('discount_banner', clean_text((string) ($_POST['discount_banner'] ?? ''), 180));
        $changed = ['discount'];
        foreach (['shipping_flat_inr', 'free_shipping_over_inr', 'min_order_inr', 'max_unpaid_phone', 'max_unpaid_ip', 'max_trays_phone', 'cod_fail_limit'] as $key) {
            $next = checked_setting($key, (string) ($_POST[$key] ?? '0'));
            save_setting($key, $next);
            $changed[] = $key;
        }
        foreach (['shop_phone', 'whatsapp_number', 'shop_email', 'mail_from', 'shop_address', 'opening_hours', 'map_url', 'payment_instructions', 'announcement', 'seller_legal_name', 'gstin', 'cin', 'hsn_code', 'gst_rate', 'grievance_officer_name', 'grievance_officer_phone', 'grievance_officer_email'] as $key) {
            $next = checked_setting($key, (string) ($_POST[$key] ?? ''));
            save_setting($key, $next);
            $changed[] = $key;
        }
        save_setting('announcement_on', isset($_POST['announcement_on']) ? '1' : '0');
        save_setting('shop_paused', isset($_POST['shop_paused']) ? '1' : '0');
        audit_log((int) $admin['id'], 'settings', 'setting', 0, null, implode(',', $changed));
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
echo '<form method="post" class="narrow" data-settings>' . csrf_field();
echo '<label class="search-label">Search settings <input type="search" data-settings-search placeholder="phone, discount, GST"></label>';
$groups = [
    'Shop' => ['shop_email', 'mail_from', 'shop_address', 'opening_hours', 'map_url', 'seller_legal_name', 'gstin', 'cin', 'hsn_code', 'gst_rate', 'announcement'],
    'Prices and discount' => [],
    'Phone numbers and WhatsApp' => ['shop_phone', 'whatsapp_number', 'grievance_officer_name', 'grievance_officer_phone', 'grievance_officer_email'],
    'Bank and UPI' => [],
    'Delivery' => ['shipping_flat_inr', 'free_shipping_over_inr', 'min_order_inr'],
    'Messages' => ['payment_instructions', 'max_unpaid_phone', 'max_unpaid_ip', 'max_trays_phone', 'cod_fail_limit'],
];
$help = [
    'shop_phone' => 'Example: 8080370152. This is the number printed on the site.',
    'whatsapp_number' => 'Example: 8080370152. WhatsApp may still pick at random from the three saved numbers.',
    'seasonal_discount_min_inr' => 'Example: 5000. The 10% starts when the plant total reaches this.',
    'shipping_flat_inr' => 'Example: 180. This is packing and transport. It is not discounted.',
];
echo '<section class="set-card" data-setting="prices discount bulk offer minimum"><h2>Prices and discount</h2>';
echo '<p class="muted">The bulk discount is on plants only. Packing and transport stay full price.</p>';
echo '<label><input type="checkbox" name="seasonal_discount_on" ' . (setting('seasonal_discount_on', '1') === '1' ? 'checked' : '') . '> Bulk discount on</label>';
echo '<label>Percent <input name="seasonal_discount_percent" type="number" min="0" max="50" value="' . e(setting('seasonal_discount_percent', '10')) . '"></label>';
echo '<label>Minimum in rupees <input name="seasonal_discount_min_inr" type="number" min="0" value="' . e(setting('seasonal_discount_min_inr', '5000')) . '"></label>';
echo '<p class="muted">Example: 5000 means the discount starts at Rs 5,000 of plants.</p>';
echo '<label>Or count seedlings when the basis is seedlings <input name="discount_min_seedlings" type="number" min="0" value="' . e(setting('discount_min_seedlings', '5000')) . '"></label>';
echo '<label>Basis <select name="discount_basis"><option value="rupees"' . (setting('discount_basis', 'rupees') === 'rupees' ? ' selected' : '') . '>Rupees</option><option value="seedlings"' . (setting('discount_basis', 'rupees') === 'seedlings' ? ' selected' : '') . '>Number of seedlings</option></select></label>';
echo '<label><input type="checkbox" name="offer_allows_bulk" ' . (setting('offer_allows_bulk', '1') === '1' ? 'checked' : '') . '> Allow the bulk discount on plants that already have an offer price</label>';
echo '<label>Nursery minimum, paise per seedling <input name="nursery_min_seedling_paise" type="number" min="1" value="' . e(setting('nursery_min_seedling_paise', '100')) . '"></label>';
echo '<p class="muted">100 paise is Rs 1.00. An offer price will not go under this.</p>';
echo '<label>Banner text, leave blank for the automatic sentence <input name="discount_banner" value="' . e(setting('discount_banner', '')) . '"></label>';
echo '</section>';
echo '<section class="set-card" data-setting="bank upi"><h2>Bank and UPI</h2><p class="muted">Bank accounts stay hidden until you mark one verified.</p><p class="btn-row"><a class="btn" href="/admin/banks">Bank accounts</a> <a class="btn" href="/admin/payments">UPI and QR</a></p></section>';
foreach ($groups as $title => $keysIn) {
    if (!$keysIn) {
        continue;
    }
    echo '<section class="set-card" data-setting="' . e(strtolower($title)) . '"><h2>' . e($title) . '</h2>';
    if ($title === 'Phone numbers and WhatsApp') {
        echo '<p class="muted">The shop only uses 8080370152, 8421577787 and 8421577725. Change the list on the Phones page.</p><p><a href="/admin/phones">Open phone numbers</a></p>';
    }
    foreach ($keysIn as $key) {
        $label = $fields[$key] ?? $key;
        $value = array_key_exists($key, $_POST) ? (string) $_POST[$key] : setting($key, '');
        echo '<div data-setting="' . e(strtolower($title . ' ' . $label)) . '">';
        if ($key === 'payment_instructions' || $key === 'announcement' || $key === 'shop_address') {
            echo '<label>' . e($label) . ' <textarea name="' . e($key) . '">' . e($value) . '</textarea></label>';
        } else {
            $type = str_contains($key, 'inr') || str_contains($key, 'max_') || $key === 'cod_fail_limit' ? 'number' : 'text';
            echo '<label>' . e($label) . ' <input name="' . e($key) . '" type="' . $type . '" value="' . e($value) . '"></label>';
        }
        if (isset($help[$key])) {
            echo '<p class="muted">' . e($help[$key]) . '</p>';
        }
        echo '</div>';
    }
    if ($title === 'Shop') {
        echo '<label data-setting="announcement"><input type="checkbox" name="announcement_on" ' . (setting('announcement_on', '0') === '1' ? 'checked' : '') . '> Show announcement</label>';
        echo '<label data-setting="shop paused"><input type="checkbox" name="shop_paused" ' . (setting('shop_paused', '0') === '1' ? 'checked' : '') . '> Shop paused</label>';
    }
    echo '</section>';
}
echo '<button class="btn" type="submit">Save</button></form>';
admin_close();
