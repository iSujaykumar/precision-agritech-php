<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();

if (($_GET['export'] ?? '') === 'csv' || ($_GET['export'] ?? '') === 'tally') {
    $rows = db()->query('SELECT i.invoice_number, i.created_at, i.total_inr, i.snapshot_json, o.order_number, o.customer_name, o.payment_method, o.discount_inr, o.seasonal_discount_inr FROM invoices i JOIN orders o ON o.id = i.order_id ORDER BY i.id')->fetchAll();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="invoices.csv"');
    header('Cache-Control: private, no-store');
    $out = fopen('php://output', 'w');
    if (($_GET['export'] ?? '') === 'tally') {
        fputcsv($out, ['voucher_date', 'voucher_number', 'party_name', 'ledger_or_item', 'quantity', 'rate', 'amount', 'discount', 'tax', 'narration']);
        foreach ($rows as $row) {
            $snap = json_decode((string) $row['snapshot_json'], true) ?: [];
            $discount = (int) $row['discount_inr'] + (int) $row['seasonal_discount_inr'];
            $lines = $snap['lines'] ?? [];
            if (!$lines) {
                fputcsv($out, [substr((string) $row['created_at'], 0, 10), csv_cell((string) $row['invoice_number']), csv_cell((string) $row['customer_name']), 'Sales', 1, (int) $row['total_inr'], (int) $row['total_inr'], $discount, 0, csv_cell('Order ' . $row['order_number'])]);
                continue;
            }
            foreach ($lines as $line) {
                fputcsv($out, [
                    substr((string) $row['created_at'], 0, 10),
                    csv_cell((string) $row['invoice_number']),
                    csv_cell((string) $row['customer_name']),
                    csv_cell((string) ($line['product_name'] ?? 'Item')),
                    (int) ($line['quantity'] ?? 0),
                    (int) ($line['unit_price_inr'] ?? 0),
                    (int) ($line['line_total_inr'] ?? 0),
                    $discount,
                    0,
                    csv_cell('Order ' . $row['order_number'] . ' ' . (string) ($row['payment_method'] ?? '')),
                ]);
            }
        }
    } else {
        fputcsv($out, ['invoice_number', 'date', 'order_number', 'customer', 'payment', 'total_inr']);
        foreach ($rows as $row) {
            fputcsv($out, [csv_cell((string) $row['invoice_number']), (string) $row['created_at'], csv_cell((string) $row['order_number']), csv_cell((string) $row['customer_name']), csv_cell((string) $row['payment_method']), (int) $row['total_inr']]);
        }
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_desk($admin, 'manager');
    try {
        require_csrf();
        $keys = [
            'seedlings_per_tray' => trim((string) ($_POST['seedlings_per_tray'] ?? '')),
            'show_seedling_count' => isset($_POST['show_seedling_count']) ? '1' : '0',
            'seasonal_discount_on' => isset($_POST['seasonal_discount_on']) ? '1' : '0',
            'seasonal_discount_percent' => trim((string) ($_POST['seasonal_discount_percent'] ?? '10')),
            'seasonal_discount_min_inr' => trim((string) ($_POST['seasonal_discount_min_inr'] ?? '5000')),
            'discount_basis' => (($_POST['discount_basis'] ?? '') === 'seedlings') ? 'seedlings' : 'rupees',
            'discount_min_seedlings' => trim((string) ($_POST['discount_min_seedlings'] ?? '5000')),
            'discount_banner' => clean_text((string) ($_POST['discount_banner'] ?? ''), 160),
            'seasonal_discount_label' => clean_text((string) ($_POST['seasonal_discount_label'] ?? 'Seasonal discount'), 80),
            'seasonal_discount_start' => trim((string) ($_POST['seasonal_discount_start'] ?? '')),
            'seasonal_discount_end' => trim((string) ($_POST['seasonal_discount_end'] ?? '')),
            'seasonal_stacks_coupons' => isset($_POST['seasonal_stacks_coupons']) ? '1' : '0',
            'seasonal_applies_farmer' => isset($_POST['seasonal_applies_farmer']) ? '1' : '0',
            'seasonal_applies_cod' => isset($_POST['seasonal_applies_cod']) ? '1' : '0',
            'seasonal_applies_overgrown' => isset($_POST['seasonal_applies_overgrown']) ? '1' : '0',
            'farmer_fallback_seasonal' => isset($_POST['farmer_fallback_seasonal']) ? '1' : '0',
            'invoice_prefix' => strtoupper(trim((string) ($_POST['invoice_prefix'] ?? 'PA'))),
            'gst_enabled' => isset($_POST['gst_enabled']) ? '1' : '0',
            'invoice_email_on' => isset($_POST['invoice_email_on']) ? '1' : '0',
            'overgrown_nav' => isset($_POST['overgrown_nav']) ? '1' : '0',
            'overgrown_intro' => clean_text((string) ($_POST['overgrown_intro'] ?? ''), 500),
            'prices_dispatch_note' => clean_text((string) ($_POST['prices_dispatch_note'] ?? ''), 300),
            'invoice_terms' => clean_text((string) ($_POST['invoice_terms'] ?? ''), 2000),
            'gstin' => strtoupper(trim((string) ($_POST['gstin'] ?? ''))),
        ];
        $tray = (int) $keys['seedlings_per_tray'];
        if ($tray < 1 || $tray > 1000) {
            throw new RuntimeException('Seedlings per tray must be from 1 to 1000.');
        }
        $keys['seedlings_per_tray'] = (string) $tray;
        $percent = (int) $keys['seasonal_discount_percent'];
        if ($percent < 0 || $percent > 90) {
            throw new RuntimeException('Seasonal percent must be from 0 to 90.');
        }
        $keys['seasonal_discount_percent'] = (string) $percent;
        $min = (int) $keys['seasonal_discount_min_inr'];
        if ($min < 0 || $min > 1000000) {
            throw new RuntimeException('Seasonal minimum must be a whole number of rupees.');
        }
        $keys['seasonal_discount_min_inr'] = (string) $min;
        $seedMin = (int) $keys['discount_min_seedlings'];
        if ($seedMin < 0 || $seedMin > 1000000) {
            throw new RuntimeException('Seedling minimum must be a whole number.');
        }
        $keys['discount_min_seedlings'] = (string) $seedMin;
        foreach (['seasonal_discount_start', 'seasonal_discount_end'] as $dateKey) {
            if ($keys[$dateKey] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $keys[$dateKey])) {
                throw new RuntimeException('Season dates must be blank or YYYY-MM-DD.');
            }
        }
        if (!preg_match('/^[A-Z0-9]{1,8}$/', $keys['invoice_prefix'])) {
            throw new RuntimeException('Invoice prefix must be 1 to 8 letters or digits.');
        }
        if ($keys['gstin'] !== '' && !preg_match('/^[0-9A-Z]{15}$/', $keys['gstin'])) {
            throw new RuntimeException('GSTIN must be 15 letters and digits, or blank.');
        }
        if ($keys['gst_enabled'] === '1' && $keys['gstin'] === '') {
            throw new RuntimeException('Enter a GSTIN before turning GST on. Confirm the rate with your CA.');
        }
        foreach ($keys as $key => $value) {
            $before = setting($key, '');
            if ($before === $value) {
                continue;
            }
            save_setting($key, $value);
            audit_log((int) $admin['id'], 'setting', 'setting', 0, $key . '=' . $before, $key . '=' . $value);
        }
        flash_set('Invoice and pricing settings saved');
        header('Location: /admin/invoices');
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'Those settings could not be saved.');
    }
}

admin_open('Invoices', 'orders');
if (!empty($error)) {
    echo '<p class="flash bad" role="alert">' . e($error) . '</p>';
}
echo '<p class="muted">Seasonal discount is off until you turn it on here. It uses the plant subtotal before this discount and after item prices, and it never reduces delivery. If a coupon is also used, the customer gets the larger one unless you allow stacking. Farmer orders are excluded unless “Applies to farmer price list” is on. The Rs 5,000 test uses the eligible plant subtotal after any item offer price, not after the seasonal discount. The container add-on switch is stored only and does not change cart totals. Confirm GST with your CA before enabling it. Invoice numbers cannot be moved backwards.</p>';
echo '<p><a href="/admin/invoices?export=csv">Export register CSV</a> · <a href="/admin/invoices?export=tally">Tally-style CSV (not tested in Tally)</a></p>';
$mode = (string) ($_GET['mode'] ?? '');
$where = '';
$args = [];
if ($mode !== '') {
    $where = ' WHERE o.payment_method = ?';
    $args[] = $mode;
}
$list = db()->prepare('SELECT i.*, o.order_number, o.customer_name, o.payment_method FROM invoices i JOIN orders o ON o.id = i.order_id' . $where . ' ORDER BY i.id DESC LIMIT 50');
$list->execute($args);
echo '<div class="table-wrap"><table><tr><th>Invoice</th><th>Order</th><th>Customer</th><th>Total</th><th></th></tr>';
foreach ($list as $row) {
    echo '<tr><td>' . e((string) $row['invoice_number']) . '</td><td><a href="/admin/order-view?id=' . (int) $row['order_id'] . '">' . e((string) $row['order_number']) . '</a></td><td>' . e((string) $row['customer_name']) . '</td><td>' . inr((int) $row['total_inr']) . '</td><td><a href="/admin/invoice?order=' . (int) $row['order_id'] . '&kind=pdf">PDF</a> · <a href="/admin/invoice?order=' . (int) $row['order_id'] . '&kind=html">Print</a></td></tr>';
}
echo '</table></div>';
$notes = db()->query('SELECT credit_number, amount_inr, order_id, created_at FROM credit_notes ORDER BY id DESC LIMIT 20')->fetchAll();
if ($notes) {
    echo '<h2>Credit notes</h2><div class="table-wrap"><table>';
    foreach ($notes as $note) {
        echo '<tr><td>' . e((string) $note['credit_number']) . '</td><td>' . inr((int) $note['amount_inr']) . '</td><td><a href="/admin/order-view?id=' . (int) $note['order_id'] . '">Order</a></td><td>' . e((string) $note['created_at']) . '</td></tr>';
    }
    echo '</table></div>';
}
$flag = static function (string $key): string {
    return setting($key, '0') === '1' ? ' checked' : '';
};
echo '<h2>Pricing and invoice settings</h2><form method="post" class="narrow">' . csrf_field();
echo '<label>Seedlings per tray <input name="seedlings_per_tray" type="number" min="1" max="1000" value="' . e(setting('seedlings_per_tray', '150')) . '"></label>';
echo '<label><input type="checkbox" name="show_seedling_count" value="1"' . $flag('show_seedling_count') . '> Show the seedling count next to the tray price</label>';
echo '<label><input type="checkbox" name="seasonal_discount_on" value="1"' . $flag('seasonal_discount_on') . '> Bulk discount on</label>';
echo '<label>Label <input name="seasonal_discount_label" value="' . e(setting('seasonal_discount_label', 'Bulk discount')) . '"></label>';
echo '<label>Percent <input name="seasonal_discount_percent" type="number" min="0" max="90" value="' . e(setting('seasonal_discount_percent', '10')) . '"></label>';
echo '<label>Discount basis
  <select name="discount_basis">
    <option value="rupees"' . (setting('discount_basis', 'rupees') === 'rupees' ? ' selected' : '') . '>Order value in rupees</option>
    <option value="seedlings"' . (setting('discount_basis', 'rupees') === 'seedlings' ? ' selected' : '') . '>Number of seedlings</option>
  </select>
</label>';
echo '<label>Minimum order value in rupees (default 5000) <input name="seasonal_discount_min_inr" type="number" min="0" value="' . e(setting('seasonal_discount_min_inr', '5000')) . '"></label>';
echo '<label>Minimum seedlings (default 5000) <input name="discount_min_seedlings" type="number" min="0" value="' . e(setting('discount_min_seedlings', '5000')) . '"></label>';
echo '<label>Banner text <input name="discount_banner" value="' . e(setting('discount_banner', '')) . '" placeholder="10% off on orders above Rs 5,000"></label>';
echo '<label>Season start <input name="seasonal_discount_start" value="' . e(setting('seasonal_discount_start', '')) . '" placeholder="YYYY-MM-DD"></label>';
echo '<label>Season end <input name="seasonal_discount_end" value="' . e(setting('seasonal_discount_end', '')) . '" placeholder="YYYY-MM-DD"></label>';
echo '<label><input type="checkbox" name="seasonal_stacks_coupons" value="1"' . $flag('seasonal_stacks_coupons') . '> Stack with coupons</label>';
echo '<label><input type="checkbox" name="seasonal_applies_farmer" value="1"' . $flag('seasonal_applies_farmer') . '> Applies to farmer price list</label>';
echo '<label><input type="checkbox" name="seasonal_applies_cod" value="1"' . $flag('seasonal_applies_cod') . '> Applies to cash on delivery</label>';
echo '<label><input type="checkbox" name="seasonal_applies_overgrown" value="1"' . $flag('seasonal_applies_overgrown') . '> Applies to overgrown trays</label>';
echo '<label><input type="checkbox" name="farmer_fallback_seasonal" value="1"' . $flag('farmer_fallback_seasonal') . '> If a farmer rate has expired, sell the seasonal tray price</label>';
echo '<label>Invoice prefix <input name="invoice_prefix" value="' . e(setting('invoice_prefix', 'PA')) . '"></label>';
echo '<label><input type="checkbox" name="invoice_email_on" value="1"' . $flag('invoice_email_on') . '> Email the private invoice link when payment is received</label>';
echo '<label><input type="checkbox" name="gst_enabled" value="1"' . $flag('gst_enabled') . '> GST on (confirm with your CA first)</label>';
echo '<label>GSTIN <input name="gstin" value="' . e(setting('gstin', '')) . '"></label>';
echo '<label><input type="checkbox" name="overgrown_nav" value="1"' . $flag('overgrown_nav') . '> Show overgrown seedlings in the footer</label>';
echo '<label>Overgrown explanation <textarea name="overgrown_intro">' . e(setting('overgrown_intro', '')) . '</textarea></label>';
echo '<label>Cart price note <textarea name="prices_dispatch_note">' . e(setting('prices_dispatch_note', '')) . '</textarea></label>';
echo '<label>Invoice terms <textarea name="invoice_terms">' . e(setting('invoice_terms', '')) . '</textarea></label>';
echo '<button class="btn" type="submit">Save</button></form>';
admin_close();
