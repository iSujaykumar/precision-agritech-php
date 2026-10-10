<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
admin_boot();
$file = (string) ($_GET['file'] ?? '');
if (!preg_match('#^/uploads/private/[a-z0-9/_-]+\.webp$#', $file)) {
    http_response_code(404);
    exit('Not found');
}
$path = dirname(__DIR__) . $file;
if (!is_file($path)) {
    http_response_code(404);
    exit('Not found');
}
header('Content-Type: image/webp');
header('X-Robots-Tag: noindex');
header('Cache-Control: private, no-store');
readfile($path);
exit;
