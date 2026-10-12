<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
global $config;
$given = (string) ($_GET['token'] ?? '');
$expected = (string) ($config['cron_token'] ?? '');
if ($expected === '' || !hash_equals($expected, $given)) {
    http_response_code(404);
    exit('Not found');
}
connect_or_explain();
$expired = expire_reservations();
db()->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 2 DAY)');
db()->exec('DELETE FROM lookup_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
db()->exec('DELETE FROM order_attempts WHERE created_at < (NOW() - INTERVAL 2 DAY)');
db()->exec("DELETE FROM mobile_verifications WHERE created_at < (NOW() - INTERVAL 2 DAY)");
db()->exec('DELETE FROM email_codes WHERE created_at < (NOW() - INTERVAL 2 DAY)');
save_setting('cron_last_run', date('Y-m-d H:i:s'));
save_setting('cleanup_last_run', (string) time());
header('Content-Type: text/plain');
echo 'Released ' . $expired . " reserved orders.\n";
