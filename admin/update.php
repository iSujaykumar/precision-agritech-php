<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
require_desk($admin, 'manager');
$result = null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $result = run_shop_update(db(), true);
        audit_log((int) $admin['id'], 'shop_update', 'settings', 0, '', 'updated');
        flash_set('The shop update finished. ' . (string) ($result['backup'] ?? ''));
    } catch (Throwable $err) {
        $error = 'The update did not finish. Nothing was half-saved if the database rejected it. Try again.';
        error_log('Update button: ' . $err->getMessage());
    }
}
admin_open('Update everything', 'settings');
if ($error) {
    echo '<p class="flash bad" role="alert">' . e($error) . '</p>';
}
if (!empty($_SESSION['update_error'])) {
    echo '<p class="flash bad">' . e((string) $_SESSION['update_error']) . '</p>';
    unset($_SESSION['update_error']);
}
$version = setting('schema_version', '');
$loaded = setting('lists_loaded', '0');
echo '<p>Database version: ' . e($version !== '' ? $version : 'not updated yet') . '. Price sheets loaded: ' . ($loaded === '1' ? 'yes' : 'no') . '.</p>';
if ($version !== '2026.10.12-final' || $loaded !== '1') {
    echo '<p class="desk-warn">The update has not finished. Press the button.</p>';
}
echo '<form method="post">' . csrf_field();
echo '<button class="btn" type="submit">Update everything</button></form>';
echo '<p class="muted">This adds any missing database columns and loads the 2026-27 and farmer sheets. Pressing it again does not duplicate plants.</p>';
admin_close();
