<?php
declare(strict_types=1);

// Mobile codes are not switched on. The login page is the only sign-in screen.
header('Location: /login', true, 301);
exit;
