<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
admin_boot();
global $config;
$checks = [];
$add = static function (string $label, bool $ok, string $detail = '') use (&$checks): void {
    $checks[] = [$label, $ok, $detail];
};
$add('PHP 8.1 or newer', PHP_VERSION_ID >= 80100, PHP_VERSION);
foreach (['gd', 'fileinfo', 'curl', 'mbstring', 'pdo_mysql'] as $ext) {
    $add($ext, extension_loaded($ext));
}
$bytes = static function (string $value): int {
    $value = trim($value);
    if ($value === '') {
        return 0;
    }
    $unit = strtoupper(substr($value, -1));
    $num = (float) $value;
    return (int) match ($unit) {
        'G' => $num * 1073741824,
        'M' => $num * 1048576,
        'K' => $num * 1024,
        default => $num,
    };
};
$upload = ini_get('upload_max_filesize') ?: '';
$post = ini_get('post_max_size') ?: '';
$add('Upload limit at least 8M', $bytes($upload) >= 8 * 1048576 && $bytes($post) >= 8 * 1048576, $upload . ' / ' . $post);
$dir = dirname(__DIR__) . '/uploads/products';
$add('Uploads folder is writable', is_dir($dir) && is_writable($dir));
$add('Mail is configured', setting('mail_from', '') !== '' || ($config['smtp_host'] ?? '') !== '');
$cron = setting('cron_last_run', '');
$add('Cron has run', $cron !== '', $cron !== '' ? $cron : 'Not yet');
$add('Debug is off', empty($config['debug']));
$add('Codes are not shown on screen', empty($config['show_otp_on_screen']));
$https = request_is_https() || str_starts_with((string) ($config['site_url'] ?? ''), 'https://');
$add('HTTPS site address', $https, (string) ($config['site_url'] ?? ''));
$add('UPI ID is set', setting('upi_id', '') !== '');
$add('Bank account is set', setting('bank_account_number', '') !== '' && setting('bank_ifsc', '') !== '');
$add('UPI QR is uploaded', setting('upi_qr_image', '') !== '');
admin_open('Health', 'settings');
echo '<ul class="health">';
foreach ($checks as [$label, $ok, $detail]) {
    echo '<li class="' . ($ok ? 'ok' : 'bad') . '">' . e($label) . ($detail !== '' ? ' · ' . e($detail) : '') . '</li>';
}
echo '</ul>';
if (trim(setting('gstin', '')) === '') {
    echo '<p class="flash">Confirm your GST status with your CA, then enter GSTIN in shop settings.</p>';
}
admin_close();
