<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        if (($_POST['action'] ?? '') === 'off') {
            db()->prepare('UPDATE coupons SET active = 0 WHERE id = ?')->execute([(int) $_POST['id']]);
            audit_log((int) $admin['id'], 'coupon_off', 'coupon', (int) $_POST['id'], null, 'inactive');
        } else {
            $code = strtoupper(trim((string) $_POST['code']));
            $kind = ($_POST['kind'] ?? '') === 'percent' ? 'percent' : 'fixed';
            $amount = (int) $_POST['amount'];
            if ($code === '' || $amount < 1 || ($kind === 'percent' && $amount > 90)) {
                throw new RuntimeException('Check the coupon.');
            }
            db()->prepare('INSERT INTO coupons (code, kind, amount, min_order_inr, usage_limit, active) VALUES (?, ?, ?, ?, ?, 1)')
                ->execute([$code, $kind, $amount, (int) $_POST['min_order'], ($_POST['usage_limit'] ?? '') === '' ? null : (int) $_POST['usage_limit']]);
            audit_log((int) $admin['id'], 'coupon_create', 'coupon', (int) db()->lastInsertId(), null, $code);
        }
        header('Location: /admin/coupons');
        exit;
    } catch (Throwable $err) {
        error_log($err->getMessage());
        $error = 'The coupon could not be saved.';
    }
}
admin_open('Coupons');
if ($error) echo '<p class="flash">' . e($error) . '</p>';
foreach (db()->query('SELECT * FROM coupons ORDER BY id DESC') as $row) {
    echo '<p>' . e($row['code']) . ' · ' . e($row['kind']) . ' ' . (int) $row['amount'] . ' · ' . ($row['active'] ? 'active' : 'off') . '</p>';
    if ($row['active']) {
        echo '<form method="post">' . csrf_field() . '<input type="hidden" name="action" value="off"><input type="hidden" name="id" value="' . (int) $row['id'] . '"><button>Turn off</button></form>';
    }
}
echo '<h2>New coupon</h2><form method="post" class="narrow">' . csrf_field() . '<label>Code <input name="code" required></label><label>Kind <select name="kind"><option value="fixed">Fixed rupees</option><option value="percent">Percent</option></select></label><label>Amount <input name="amount" type="number" required></label><label>Minimum order <input name="min_order" type="number" value="0"></label><label>Use limit <input name="usage_limit" type="number"></label><button class="btn">Save coupon</button></form>';
admin_close();
