<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
admin_boot();
header('Location: /admin/inventory?tab=history');
exit;
