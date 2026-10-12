<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();

$phone = shop_phone_digits();
$GLOBALS['json_ld'] = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'LocalBusiness',
    'name' => 'Precision Agritech',
    'telephone' => array_map(static fn ($row) => '+91' . $row['digits'], public_phone_rows()) ?: $phone,
    'email' => shop_setting('shop_email', 'info@precisionagritech.in'),
    'address' => shop_setting('shop_address', 'Survey No. 44/2, Theur Naygaon Road, Gaikwadvasti, Pune 412110'),
    'image' => '/brand/greenhouse-hero.webp',
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);

render_header('Precision Agritech | Flower seedling trays from Theur, Pune', 'Order flower seedling trays from Precision Agritech, Theur. Sold by the tray.');
render_homepage();
render_footer();
