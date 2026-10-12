<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
header('Content-Type: text/plain; charset=UTF-8');
$base = '';
try {
    global $config;
    $base = rtrim((string) ($config['site_url'] ?? ''), '/');
} catch (Throwable $err) {
    $base = '';
}
echo "User-agent: *\n";
echo "Disallow: /admin\n";
echo "Disallow: /setup\n";
echo "Disallow: /account\n";
echo "Disallow: /cart\n";
echo "Disallow: /checkout\n";
echo "Disallow: /login\n";
if ($base !== '' && !preg_match('#localhost|127\.0\.0\.1#', $base)) {
    echo "\nSitemap: " . $base . "/sitemap.xml\n";
}
