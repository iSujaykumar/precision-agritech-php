<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
require dirname(__DIR__) . '/includes/migrate.php';
$admin = admin_boot();
require_role($admin, 'owner');
$error = null;
$log = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $log = shop_upgrade(db());
        audit_log((int) $admin['id'], 'database_upgrade', 'database', 0, null, (string) count($log));
        flash_set('Database update finished. ' . count($log) . ' steps.');
    } catch (Throwable $err) {
        $error = safe_error($err, 'The database update stopped.');
    }
}
admin_open('Database update', 'settings');
if ($error) {
    echo '<p class="flash bad" role="alert">' . e($error) . '</p>';
}
?>
<p>Use this only if phpMyAdmin refuses <code>database/upgrade.sql</code> because stored procedures are not allowed. It applies the same file without creating procedures. It adds missing columns, indexes and tables. It does not delete orders or products.</p>
<p>If phpMyAdmin can run the SQL file, use that instead and run it twice. Do not run both at the same time.</p>
<form method="post">
  <?= csrf_field() ?>
  <button class="btn" type="submit" data-confirm="Update the shop database now?">Update the database</button>
</form>
<?php if ($log): ?>
<pre><?php foreach ($log as $line) { echo e($line) . "\n"; } ?></pre>
<?php endif;
admin_close();
?>
