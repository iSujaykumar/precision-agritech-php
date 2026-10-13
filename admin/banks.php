<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
require_role($admin, 'owner');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        $ids = $_POST['id'] ?? [];
        foreach ($ids as $id) {
            $id = (int) $id;
            $verified = isset($_POST['verified'][$id]) ? 1 : 0;
            $show = isset($_POST['show_public'][$id]) ? 1 : 0;
            if ($show === 1 && $verified !== 1) {
                throw new RuntimeException('Tick "I verified these bank details" before a customer can see that account.');
            }
            db()->prepare('UPDATE bank_accounts SET verified=?, show_public=? WHERE id=?')->execute([$verified, $show, $id]);
            audit_log((int) $admin['id'], 'bank_verify', 'bank', $id, null, $verified ? 'verified' : 'unverified');
        }
        flash_set('Bank accounts saved. Unverified accounts stay hidden.');
    } catch (Throwable $err) {
        flash_set(safe_error($err, 'Could not save the bank accounts.'), false);
    }
    header('Location: /admin/banks');
    exit;
}
admin_open('Bank accounts', 'settings');
$rows = db()->query('SELECT * FROM bank_accounts ORDER BY sort_order, id')->fetchAll();
$flash = flash_take();
if ($flash) {
    echo '<p class="flash ' . e($flash['kind']) . '">' . e($flash['message']) . '</p>';
}
echo '<p class="admin-warn">These account numbers were read from the price-list PDF. Check every digit against the bank or a cheque before you show them. Nothing here is shown to customers until you tick the verification box.</p>';
echo '<form method="post">' . csrf_field();
echo '<div class="table-wrap"><table><tr><th>Bank</th><th>Account</th><th>IFSC</th><th>I verified these bank details against the bank/cheque</th><th>Show</th></tr>';
foreach ($rows as $row) {
    $id = (int) $row['id'];
    echo '<tr><td><input type="hidden" name="id[]" value="' . $id . '">' . e((string) $row['bank']) . '<br><span class="muted">' . e((string) $row['branch']) . '</span></td>';
    echo '<td>' . e((string) $row['account_name']) . '<br>' . e((string) $row['account_no']) . '</td>';
    echo '<td>' . e((string) $row['ifsc']) . '</td>';
    echo '<td><input type="checkbox" name="verified[' . $id . ']" ' . ((int) $row['verified'] ? 'checked' : '') . '></td>';
    echo '<td><input type="checkbox" name="show_public[' . $id . ']" ' . ((int) $row['show_public'] ? 'checked' : '') . '></td></tr>';
}
echo '</table></div><button class="btn" type="submit">Save bank accounts</button></form>';
admin_close();
