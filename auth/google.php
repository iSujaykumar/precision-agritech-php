<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
connect_or_explain();
if (!google_ready()) {
    header('Location: /login');
    exit;
}
$next = safe_next((string) ($_GET['next'] ?? ($_SESSION['login_next'] ?? '')), '/account');
$_SESSION['login_next'] = $next;
$_SESSION['google_state'] = bin2hex(random_bytes(16));
$_SESSION['google_nonce'] = bin2hex(random_bytes(16));
global $config;
$query = http_build_query([
    'client_id' => $config['google_client_id'],
    'redirect_uri' => google_redirect_uri(),
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $_SESSION['google_state'],
    'nonce' => $_SESSION['google_nonce'],
    'prompt' => 'select_account',
]);
header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $query);
exit;
