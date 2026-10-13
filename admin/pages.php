<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
require_role($admin, 'manager');
$keys = ['about', 'nursery', 'faq', 'shipping', 'returns', 'cancellation', 'privacy', 'terms', 'wholesale'];
$error = null;
$current = (string) ($_GET['page'] ?? 'shipping');
if (!in_array($current, $keys, true)) {
    $current = 'shipping';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $page = (string) ($_POST['page'] ?? '');
        if (!in_array($page, $keys, true)) {
            throw new RuntimeException('That page is not editable.');
        }
        $body = trim((string) ($_POST['body'] ?? ''));
        $body = strip_tags($body);
        if (strlen($body) > 8000) {
            throw new RuntimeException('Keep the page under 8000 characters.');
        }
        save_setting('page_' . $page, $body);
        audit_log((int) $admin['id'], 'page_text', 'setting', 0, null, $page);
        flash_set('Page saved');
        header('Location: /admin/pages?page=' . rawurlencode($page));
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'That page could not be saved.');
    }
}
admin_open('Pages', 'homepage');
if ($error) {
    echo '<p class="flash bad" role="alert">' . e($error) . '</p>';
}
echo '<p class="muted">One paragraph per line. Leave the box empty and save to go back to the original wording. Scripts are removed.</p>';
echo '<p>';
foreach ($keys as $key) {
    echo '<a href="/admin/pages?page=' . e($key) . '">' . e($key) . '</a> ';
}
echo '</p>';
echo '<form method="post" class="narrow">' . csrf_field();
echo '<input type="hidden" name="page" value="' . e($current) . '">';
echo '<label>' . e($current) . ' <textarea name="body" rows="14">' . e(setting('page_' . $current, '')) . '</textarea></label>';
echo '<button class="btn" type="submit">Save page</button></form>';
admin_close();
