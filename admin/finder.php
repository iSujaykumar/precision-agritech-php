<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
admin_boot();
admin_open('Where do I change this?');
$items = [
    ['change price', '/admin/prices'],
    ['change phone number', '/admin/phones'],
    ['change discount', '/admin/invoices'],
    ['add photo', '/admin/photos'],
    ['edit homepage', '/admin/homepage'],
    ['plant this month', '/admin/months'],
    ['change stock', '/admin/stock'],
    ['add a plant', '/admin/product-edit'],
    ['hide a plant', '/admin/products'],
    ['show a price-list plant in the shop', '/admin/attention'],
    ['mark a customer as farmer', '/admin/customers'],
    ['bank account', '/admin/banks'],
    ['UPI and QR', '/admin/payments'],
    ['terms', '/admin/invoices'],
    ['shipping and returns pages', '/admin/pages'],
    ['language labels', '/admin/labels'],
    ['orders', '/admin/orders'],
    ['back in stock requests', '/admin/restock'],
    ['staff', '/admin/staff'],
    ['update the shop', '/admin/update'],
];
$q = strtolower(trim((string) ($_GET['q'] ?? '')));
echo '<form method="get"><label>Search <input name="q" value="' . e($q) . '" placeholder="price, phone, photo"></label> <button class="btn" type="submit">Search</button></form><ul>';
foreach ($items as [$label, $href]) {
    if ($q !== '' && !str_contains($label, $q)) {
        continue;
    }
    echo '<li><a class="btn" href="' . e($href) . '">' . e($label) . '</a></li>';
}
echo '</ul>';
admin_close();
