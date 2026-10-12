<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
admin_boot();
admin_open('Help');
$jobs = [
    ['Change stock', 'Open Stock. Type the tray count or use plus and minus. Press Save all.', '/admin/stock'],
    ['Change a price', 'Open Prices. Type the rate per seedling. The tray price updates. Press Save.', '/admin/prices'],
    ['Add a photo', 'Open Photos. Name the file with the plant slug, then upload.', '/admin/photos'],
    ['Confirm a payment', 'Open Orders, then the order. Press the status that matches what you did.', '/admin/orders'],
    ['Pack an order', 'Open the order and press Packed. Print the packing slip from that page.', '/admin/orders'],
    ['Add a plant', 'Open Add product and fill the name, price and photo.', '/admin/product-edit'],
    ['Turn off a plant', 'Open Products and press Hide. Restore it later from Recently hidden.', '/admin/products'],
    ['Edit homepage text', 'Open Homepage. Change the title or the month list.', '/admin/homepage'],
    ['Change the discount', 'Open Invoices and prices. Set the percent, the minimum and on or off.', '/admin/invoices'],
    ['See back-in-stock requests', 'Open Back in stock.', '/admin/restock'],
];
echo '<ol>';
foreach ($jobs as [$title, $text, $href]) {
    echo '<li><a class="btn" href="' . e($href) . '">' . e($title) . '</a><p>' . e($text) . '</p></li>';
}
echo '</ol><p><a href="/admin/finder">Where do I change this?</a></p>';
admin_close();
