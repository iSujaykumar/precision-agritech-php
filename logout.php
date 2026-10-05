<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    render_header('Sign out | Precision Agritech');
    echo '<section class="section"><div class="wrap narrow"><h1>Sign out</h1><form method="post">' . csrf_field() . '<button class="btn">Sign out</button></form></div></section>';
    render_footer();
    exit;
}
require_csrf();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], true);
}
session_destroy();
header('Location: /');
exit;
