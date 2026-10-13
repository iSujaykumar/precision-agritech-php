<?php
declare(strict_types=1);

// Customers sign in with a code, not a password. Old bookmarks land on the login page.
header('Location: /login', true, 301);
exit;
