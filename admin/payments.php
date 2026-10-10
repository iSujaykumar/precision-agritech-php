<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
$error = null;
if (($_GET['export'] ?? '') !== '') {
    $days = (int) ($_GET['days'] ?? 7) === 30 ? 30 : 7;
    $stmt = db()->prepare('SELECT order_number, created_at, payment_ref, total_inr, payment_status, payment_method FROM orders WHERE created_at >= (NOW() - INTERVAL ' . $days . ' DAY) ORDER BY id DESC');
    $stmt->execute();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="payments-' . $days . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Order', 'Date', 'Reference', 'Amount', 'Payment status', 'Method']);
    foreach ($stmt as $row) {
        fputcsv($out, [$row['order_number'], $row['created_at'], $row['payment_ref'], $row['total_inr'], $row['payment_status'], $row['payment_method']]);
    }
    fclose($out);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $action = (string) ($_POST['action'] ?? 'save');
        if ($action === 'test-mail') {
            $ok = notify_mail((string) $admin['email'], 'Payment email test', bank_instructions('PA-TEST', 1));
            flash_set($ok ? 'Test email sent' : 'The email could not be sent', $ok);
        } else {
            foreach (['upi_id', 'upi_payee_name', 'bank_account_name', 'bank_name', 'bank_account_number', 'bank_ifsc', 'payment_instructions'] as $key) {
                save_setting($key, clean_text((string) ($_POST[$key] ?? ''), 500));
            }
            save_setting('cod_enabled', isset($_POST['cod_enabled']) ? '1' : '0');
            save_setting('reservation_hours_bank', (string) max(1, (int) ($_POST['reservation_hours_bank'] ?? 48)));
            save_setting('reservation_hours_cod', (string) max(1, (int) ($_POST['reservation_hours_cod'] ?? 72)));
            save_setting('require_email', isset($_POST['require_email']) ? '1' : '0');
            if (!empty($_FILES['qr']['name'])) {
                $path = save_upload_webp($_FILES['qr'], '/uploads/products', 200, 200);
                save_setting('upi_qr_image', $path);
            }
            audit_log((int) $admin['id'], 'settings', 'setting', 0, null, 'payments');
            flash_set('Payment settings saved');
        }
        header('Location: /admin/payments');
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'Those settings could not be saved.');
    }
}
admin_open('Payments', 'settings');
if ($error) {
    echo '<p class="flash bad" role="alert">' . e($error) . '</p>';
}
echo '<form method="post" enctype="multipart/form-data" class="narrow">' . csrf_field();
foreach ([
    'upi_id' => 'UPI ID',
    'upi_payee_name' => 'UPI payee name',
    'bank_account_name' => 'Account name',
    'bank_name' => 'Bank',
    'bank_account_number' => 'Account number',
    'bank_ifsc' => 'IFSC',
] as $key => $label) {
    echo '<label>' . e($label) . ' <input name="' . e($key) . '" value="' . e(setting($key, '')) . '"></label>';
}
echo '<label>Payment note <textarea name="payment_instructions">' . e(setting('payment_instructions', '')) . '</textarea></label>';
echo '<label>Hours to reserve a UPI or bank order <input name="reservation_hours_bank" type="number" min="1" value="' . e(setting('reservation_hours_bank', '48')) . '"></label>';
echo '<label>Hours to reserve a cash on delivery order <input name="reservation_hours_cod" type="number" min="1" value="' . e(setting('reservation_hours_cod', '72')) . '"></label>';
echo '<label><input type="checkbox" name="cod_enabled" ' . (setting('cod_enabled', '1') === '1' ? 'checked' : '') . '> Cash on delivery</label>';
echo '<label><input type="checkbox" name="require_email" ' . (setting('require_email', '1') === '1' ? 'checked' : '') . '> Email is required at checkout</label>';
echo '<label>UPI QR image <input type="file" name="qr" accept="image/jpeg,image/png,image/webp"></label>';
$qr = setting('upi_qr_image', '');
if ($qr !== '') {
    echo '<p><img src="' . e($qr) . '" alt="Current UPI QR" width="160" height="160"></p>';
}
echo '<button class="btn" type="submit">Save</button></form>';
echo '<h2>Preview</h2><div class="card pay-box"><p>' . nl2br(e(bank_instructions('PA-TEST', 1))) . '</p></div>';
echo '<form method="post">' . csrf_field() . '<input type="hidden" name="action" value="test-mail"><button class="btn" type="submit">Send me the payment email</button></form>';
echo '<p><a href="/admin/payments?export=1&days=7">Download 7 days</a> · <a href="/admin/payments?export=1&days=30">Download 30 days</a></p>';
admin_close();
