<?php
declare(strict_types=1);

$defaults = [
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => '',
    'db_user' => '',
    'db_pass' => '',
    'site_url' => 'https://precisionagritech.in',
    'mail_from' => 'info@precisionagritech.in',
    'twilio_sid' => '',
    'twilio_token' => '',
    'twilio_verify' => '',
    'show_otp_on_screen' => false,
    'debug' => false,
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $loaded = require $local;
    if (is_array($loaded)) {
        foreach ($loaded as $k => $v) {
            if ($v !== '' && $v !== null) {
                $defaults[$k] = $v;
            }
        }
    }
}

return $defaults;
