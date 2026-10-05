<?php
declare(strict_types=1);

$defaults = [
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => '',
    'db_user' => '',
    'db_pass' => '',
    'site_url' => 'https://precisionagritech.in',
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $loaded = require $local;
    if (is_array($loaded)) {
        $defaults = array_replace($defaults, $loaded);
    }
}

return $defaults;
