<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();
header('Content-Type: application/xml; charset=UTF-8');
global $config;
$base = rtrim((string) ($config['site_url'] ?? 'https://precisionagritech.in'), '/');
$paths = ['/', '/shop', '/reviews', '/about', '/nursery', '/faq', '/shipping', '/returns', '/privacy', '/terms', '/wholesale', '/contact'];
foreach (public_product_slugs() as $slug) {
    $paths[] = '/product/' . $slug;
}
$overgrown = (int) db()->query('SELECT COUNT(*) FROM products p JOIN categories c ON c.id = p.category_id WHERE ' . catalog_public_sql() . ' AND p.is_overgrown = 1')->fetchColumn();
if ($overgrown > 0) {
    $paths[] = '/overgrown';
}
echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach ($paths as $path) {
    echo '<url><loc>' . htmlspecialchars($base . $path, ENT_XML1) . '</loc></url>';
}
echo '</urlset>';
