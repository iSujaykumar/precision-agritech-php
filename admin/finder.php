<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
admin_boot();
admin_open('Where do I change this?', 'help');
$items = [];
foreach (finder_items() as [$label, $href]) {
    $items[] = [strtolower($label), $href, $label];
}
$q = strtolower(trim((string) ($_GET['q'] ?? '')));
echo '<form method="get"><label>Search <input name="q" value="' . e($q) . '" placeholder="price, phone, photo"></label> <button class="btn" type="submit">Search</button></form><ul>';
foreach ($items as [$hay, $href, $label]) {
    if ($q !== '' && !str_contains($hay, $q)) {
        continue;
    }
    echo '<li><a class="btn" href="' . e($href) . '">' . e($label) . '</a></li>';
}
echo '</ul>';
admin_close();
