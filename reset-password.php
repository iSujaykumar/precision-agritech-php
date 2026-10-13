<?php
declare(strict_types=1);

// Staff change their password after they are signed in. This address only redirects.
header('Location: /login', true, 301);
exit;
