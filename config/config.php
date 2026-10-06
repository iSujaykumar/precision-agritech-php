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
    'smtp_host' => '',
    'smtp_port' => 587,
    'smtp_user' => '',
    'smtp_pass' => '',
    'smtp_secure' => 'tls',
    'twilio_sid' => '',
    'twilio_token' => '',
    'twilio_verify' => '',
    'setup_token' => '',
    'debug' => false,
    'trust_proxy' => false,
    'turnstile_site_key' => '',
    'turnstile_secret' => '',
    'razorpay_key' => '',
    'razorpay_secret' => '',
    'cron_token' => '',
];

$above = dirname(__DIR__) . '/../config.local.php';
$local = __DIR__ . '/config.local.php';
foreach ([$above, $local] as $file) {
    if (is_file($file)) {
        $loaded = require $file;
        if (is_array($loaded)) {
            $defaults = array_replace($defaults, $loaded);
        }
        break;
    }
}

return $defaults;
