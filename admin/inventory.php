<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
admin_boot();
if (($_GET['tab'] ?? '') === 'history') {
    header('Location: /admin/inventory-history', true, 302);
    exit;
}
header('Location: /admin/stock', true, 302);
exit;
