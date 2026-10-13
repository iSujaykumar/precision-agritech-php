<?php
declare(strict_types=1);

// There is no separate registration form. The login page creates the customer.
header('Location: /login', true, 301);
exit;
