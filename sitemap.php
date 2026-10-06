<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
header('Content-Type: application/xml; charset=UTF-8');
$site = rtrim((string) ($config['site_url'] ?? 'https://precisionagritech.in'), '/');
echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
$paths = ['/', '/shop', '/about', '/nursery', '/faq', '/shipping', '/returns', '/privacy', '/terms', '/wholesale', '/contact'];
try {
    foreach (db()->query('SELECT slug FROM categories') as $row) {
        $paths[] = '/shop?category=' . rawurlencode((string) $row['slug']);
    }
    foreach (db()->query('SELECT slug FROM products WHERE active = 1') as $row) {
        $paths[] = '/product/' . rawurlencode((string) $row['slug']);
    }
} catch (Throwable $err) {
    error_log($err->getMessage());
}
foreach ($paths as $path) {
    echo '<url><loc>' . htmlspecialchars($site . $path, ENT_QUOTES, 'UTF-8') . '</loc></url>';
}
echo '</urlset>';
