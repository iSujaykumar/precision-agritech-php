<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$set = (string) ($_GET['set'] ?? 'en');
$lang = $set === 'mr' ? 'mr' : 'en';
$_SESSION['pa_lang'] = $lang;
setcookie('pa_lang', $lang, [
    'expires' => time() + 180 * 86400,
    'path' => '/',
    'httponly' => false,
    'samesite' => 'Lax',
    'secure' => request_is_https(),
]);
$next = (string) ($_SERVER['HTTP_REFERER'] ?? '/');
$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
$refHost = (string) parse_url($next, PHP_URL_HOST);
if ($refHost !== '' && $refHost !== $host) {
    $next = '/';
}
header('Location: ' . ($next !== '' ? $next : '/'));
exit;
