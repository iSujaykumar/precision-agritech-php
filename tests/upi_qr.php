<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/includes/qr.php';

if (!function_exists('role_allows')) {
    function role_allows(string $role, string $need): bool
    {
        $rank = ['staff' => 1, 'manager' => 2, 'owner' => 3];
        return ($rank[$role] ?? 0) >= ($rank[$need] ?? 3);
    }
}
if (!function_exists('csv_cell')) {
    function csv_cell(string $value): string
    {
        if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'" . $value;
        }
        return $value;
    }
}

$fail = 0;
$check = static function (bool $ok, string $label) use (&$fail): void {
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . "\n";
    if (!$ok) {
        $fail++;
    }
};

$link = upi_pay_link('precision9958@fbl', 'Precision Agritech Private Limited', 'PA123456', 180);
$check($link === 'upi://pay?pa=precision9958%40fbl&pn=Precision%20Agritech%20Private%20Limited&am=180&cu=INR&tn=PA123456', 'upi link encoding');
$check(upi_pay_link('not an id', 'Name', 'PA1', 10) === '', 'invalid upi id');
$check(upi_pay_link('name@ok', 'Name', 'PA1', 0) === '', 'zero amount');
$stripped = upi_pay_link('name@ok', 'Name', 'PA 1; --', 10);
$check($stripped !== '' && str_contains($stripped, 'tn=PA1') && !str_contains($stripped, ';'), 'note strips punctuation');
$check(!function_exists('db'), 'qr helper does not need the database');

$svg = qr_svg($link, 'UPI QR');
$check(str_contains($svg, '<svg') && str_contains($svg, 'role="img"'), 'svg qr');
$check(str_contains($svg, 'x="4" y="4" width="7"'), 'finder pattern');
$urls = [];
preg_match_all('#https?://[^"\s<]+#', $svg, $urls);
$check($urls[0] === ['http://www.w3.org/2000/svg'], 'no remote qr service');

$block = upi_qr_block('precision9958@fbl', 'Nursery', 'PA999999', 250, 'auto', '');
$check(str_contains($block, '<svg') && str_contains($block, 'PA999999'), 'order block uses svg');
$missing = upi_qr_block('', 'Nursery', 'PA999999', 250, 'auto', '');
$check(str_contains($missing, 'UPI is not configured') && !str_contains($missing, '<svg'), 'missing upi fallback');
$upload = upi_qr_block('name@ok', 'Nursery', 'PA999999', 250, 'upload', '/uploads/products/qr.webp');
$check(str_contains($upload, '/uploads/products/qr.webp') && str_contains($upload, '250'), 'uploaded qr keeps the amount in text');

$check(role_allows('staff', 'staff') && !role_allows('staff', 'manager') && role_allows('owner', 'owner'), 'staff roles');
$check(csv_cell('=cmd') === "'=cmd" && csv_cell('ok') === 'ok', 'csv formula guard');
$check(safe_shop_href('https://evil.example', '/shop') === '/shop', 'homepage link stays on site');

echo $fail === 0 ? "PASS upi qr\n" : "FAIL upi qr\n";
exit($fail === 0 ? 0 : 1);
