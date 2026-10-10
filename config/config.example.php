<?php
declare(strict_types=1);

// Copy this file to config.local.php and fill the values there.
// Do not put the filled file in a public repository.
// Hostinger: database host, name, user and password come from hPanel.
// The public site URL must be the https address of the live site.
// Twilio Verify keys stay empty until SMS codes are switched on.
return [
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
    // true = show the OTP on screen (local testing only). Must be false on the live site.
    'show_otp_on_screen' => false,
    // true = show PHP errors. Must be false on the live site.
    'debug' => false,
];
