<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$_SESSION = [];
session_destroy();
header('Location: /');
exit;
