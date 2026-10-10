<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
$error = null;

function coupon_status(array $row, int $used): string
{
    if (!empty($row['ends_at']) && strtotime((string) $row['ends_at']) < time()) {
        return 'Expired';
    }
    if ($row['usage_limit'] !== null && $used >= (int) $row['usage_limit']) {
        return 'Used up';
    }
    if (!(int) $row['active']) {
        return 'Off';
    }
    return 'Active';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $action = (string) ($_POST['action'] ?? 'save');
        $id = (int) ($_POST['id'] ?? 0);
        if ($action === 'toggle') {
            $on = (int) ($_POST['active'] ?? 0) === 1 ? 1 : 0;
            db()->prepare('UPDATE coupons SET active = ? WHERE id = ?')->execute([$on, $id]);
            audit_log((int) $admin['id'], $on ? 'coupon_on' : 'coupon_off', 'coupon', $id, null, (string) $on);
            flash_set($on ? 'Coupon turned on' : 'Coupon turned off');
            header('Location: /admin/coupons');
            exit;
        }
        if ($action === 'delete') {
            $used = db()->prepare('SELECT COUNT(*) FROM coupon_redemptions WHERE coupon_id = ?');
            $used->execute([$id]);
            $times = (int) $used->fetchColumn();
            if ($times > 0) {
                throw new RuntimeException('Used ' . $times . ' times - turn it off instead');
            }
            db()->prepare('DELETE FROM coupons WHERE id = ?')->execute([$id]);
            audit_log((int) $admin['id'], 'coupon_delete', 'coupon', $id, null, 'deleted');
            flash_set('Coupon deleted');
            header('Location: /admin/coupons');
            exit;
        }
        $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
        $kind = ($_POST['kind'] ?? '') === 'percent' ? 'percent' : 'fixed';
        $amount = (int) ($_POST['amount'] ?? 0);
        if ($code === '' || !preg_match('/^[A-Z0-9-]{2,40}$/', $code)) {
            throw new RuntimeException('Enter a coupon code.');
        }
        if ($amount < 1) {
            throw new RuntimeException('Enter a discount of at least 1.');
        }
        if ($kind === 'percent' && $amount > 90) {
            throw new RuntimeException('Percent discount cannot be more than 90.');
        }
        $taken = db()->prepare('SELECT id FROM coupons WHERE code = ? AND id <> ?');
        $taken->execute([$code, $id]);
        if ($taken->fetch()) {
            throw new RuntimeException('That code is already used.');
        }
        $min = max(0, (int) ($_POST['min_order'] ?? 0));
        $limitRaw = trim((string) ($_POST['usage_limit'] ?? ''));
        $limit = $limitRaw === '' ? null : max(1, (int) $limitRaw);
        $starts = trim((string) ($_POST['starts_at'] ?? ''));
        $ends = trim((string) ($_POST['ends_at'] ?? ''));
        $starts = $starts === '' ? null : str_replace('T', ' ', $starts);
        $ends = $ends === '' ? null : str_replace('T', ' ', $ends);
        $active = isset($_POST['active']) ? 1 : 0;
        if ($id) {
            db()->prepare('UPDATE coupons SET code=?, kind=?, amount=?, min_order_inr=?, usage_limit=?, active=?, starts_at=?, ends_at=? WHERE id=?')
                ->execute([$code, $kind, $amount, $min, $limit, $active, $starts, $ends, $id]);
            audit_log((int) $admin['id'], 'coupon_update', 'coupon', $id, null, $code);
        } else {
            db()->prepare('INSERT INTO coupons (code, kind, amount, min_order_inr, usage_limit, active, starts_at, ends_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$code, $kind, $amount, $min, $limit, $active, $starts, $ends]);
            audit_log((int) $admin['id'], 'coupon_create', 'coupon', (int) db()->lastInsertId(), null, $code);
        }
        flash_set('Coupon saved');
        header('Location: /admin/coupons');
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'The coupon could not be saved.');
    }
}

$rows = db()->query('SELECT c.*, (SELECT COUNT(*) FROM coupon_redemptions r WHERE r.coupon_id = c.id) AS used_count FROM coupons c ORDER BY c.id DESC')->fetchAll();
$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
foreach ($rows as $row) {
    if ((int) $row['id'] === $editId) {
        $edit = $row;
    }
}
admin_open('Coupons');
if ($error) {
    echo '<p class="flash bad" role="alert">' . e($error) . '</p>';
}
echo '<div class="table-wrap"><table><tr><th>Code</th><th>Discount</th><th>Min order</th><th>Used / Limit</th><th>Valid until</th><th>Status</th><th></th></tr>';
foreach ($rows as $row) {
    $used = (int) $row['used_count'];
    $status = coupon_status($row, $used);
    $discount = $row['kind'] === 'percent' ? ((int) $row['amount'] . '%') : ('Rs ' . (int) $row['amount'] . ' off');
    $limit = $row['usage_limit'] === null ? 'No limit' : (string) (int) $row['usage_limit'];
    echo '<tr><td>' . e($row['code']) . '</td><td>' . e($discount) . '</td><td>' . inr((int) $row['min_order_inr']) . '</td><td>' . $used . ' / ' . e($limit) . '</td><td>' . e((string) ($row['ends_at'] ?: '—')) . '</td>';
    echo '<td><span class="pill pill-' . e(strtolower(str_replace(' ', '-', $status))) . '">' . e($status) . '</span></td><td>';
    $next = $row['active'] ? 0 : 1;
    echo '<form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="' . (int) $row['id'] . '"><input type="hidden" name="active" value="' . $next . '"><button class="linkish" type="submit">' . ($row['active'] ? 'Turn off' : 'Turn on') . '</button></form> ';
    echo '<a href="/admin/coupons?edit=' . (int) $row['id'] . '">Edit</a> ';
    if ($used === 0) {
        echo '<form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . (int) $row['id'] . '"><button class="linkish" type="submit" data-confirm="Delete this coupon?">Delete</button></form>';
    }
    echo '</td></tr>';
}
echo '</table></div>';
$c = $edit ?: ['id' => 0, 'code' => '', 'kind' => 'fixed', 'amount' => '', 'min_order_inr' => 0, 'usage_limit' => '', 'active' => 1, 'starts_at' => '', 'ends_at' => ''];
echo '<h2>' . ($edit ? 'Edit coupon' : 'New coupon') . '</h2><form method="post" class="narrow">' . csrf_field();
echo '<input type="hidden" name="id" value="' . (int) $c['id'] . '">';
echo '<label>Code <input name="code" required value="' . e((string) $c['code']) . '"></label>';
echo '<label>Kind <select name="kind"><option value="fixed"' . ($c['kind'] === 'fixed' ? ' selected' : '') . '>Fixed rupees</option><option value="percent"' . ($c['kind'] === 'percent' ? ' selected' : '') . '>Percent</option></select></label>';
echo '<label>Amount <input name="amount" type="number" min="1" required value="' . e((string) $c['amount']) . '"></label>';
echo '<label>Minimum order <input name="min_order" type="number" min="0" value="' . (int) $c['min_order_inr'] . '"></label>';
echo '<label>Use limit <input name="usage_limit" type="number" min="1" value="' . e((string) ($c['usage_limit'] ?? '')) . '" placeholder="Blank for no limit"></label>';
echo '<label>Valid from <input name="starts_at" type="datetime-local" value="' . e(str_replace(' ', 'T', substr((string) ($c['starts_at'] ?? ''), 0, 16))) . '"></label>';
echo '<label>Valid until <input name="ends_at" type="datetime-local" value="' . e(str_replace(' ', 'T', substr((string) ($c['ends_at'] ?? ''), 0, 16))) . '"></label>';
echo '<label><input type="checkbox" name="active" ' . (!empty($c['active']) ? 'checked' : '') . '> Active</label>';
echo '<button class="btn" type="submit">Save coupon</button></form>';
admin_close();
