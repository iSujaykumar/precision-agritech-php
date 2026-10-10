<?php
declare(strict_types=1);

function google_ready(): bool
{
    global $config;
    return ($config['google_client_id'] ?? '') !== '' && ($config['google_client_secret'] ?? '') !== '';
}

function sms_ready(): bool
{
    global $config;
    $provider = (string) ($config['sms_provider'] ?? '');
    if ($provider === 'twilio') {
        return twilio_configured();
    }
    if ($provider === 'msg91') {
        return ($config['msg91_authkey'] ?? '') !== '' && ($config['msg91_template_id'] ?? '') !== '';
    }
    return false;
}

function email_code_issue(string $email, string $purpose = 'login'): void
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Enter a valid email address.');
    }
    $admin = db()->prepare('SELECT id FROM users WHERE email = ? AND role = "admin" LIMIT 1');
    $admin->execute([$email]);
    if ($admin->fetch()) {
        audit_log(0, 'customer_signin_refused', 'user', 0, $email, 'staff email');
        if ($purpose === 'login') {
            $_SESSION['email_login'] = $email;
            $_SESSION['email_blocked'] = 1;
            $_SESSION['email_sent_at'] = time();
            unset($_SESSION['email_debug']);
        } else {
            $_SESSION['account_email'] = $email;
            $_SESSION['account_email_blocked'] = 1;
        }
        return;
    }
    if (rate_limited('mailcode-ip:' . client_ip(), 10, 60) || rate_limited('mailcode:' . $email, 5, 60)) {
        throw new RuntimeException('Too many codes. Wait an hour and try again.');
    }
    $recent = db()->prepare('SELECT created_at FROM email_codes WHERE email = ? ORDER BY id DESC LIMIT 1');
    $recent->execute([$email]);
    $last = $recent->fetchColumn();
    if ($last && time() - strtotime((string) $last) < 45) {
        throw new RuntimeException('Wait a moment before asking for another code.');
    }
    $code = (string) random_int(100000, 999999);
    $salt = bin2hex(random_bytes(8));
    db()->prepare('INSERT INTO email_codes (email, purpose, code_hash, salt, expires_at, ip) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), ?)')
        ->execute([$email, $purpose, hash('sha256', $salt . $code), $salt, client_ip()]);
    note_rate('mailcode:' . $email);
    note_rate('mailcode-ip:' . client_ip());
    if ($purpose === 'login') {
        $_SESSION['email_login'] = $email;
        $_SESSION['email_sent_at'] = time();
        unset($_SESSION['email_debug'], $_SESSION['email_blocked']);
    } else {
        $_SESSION['account_email'] = $email;
        unset($_SESSION['account_email_blocked']);
    }
    global $config;
    if (!empty($config['show_otp_on_screen']) && $purpose === 'login') {
        $_SESSION['email_debug'] = $code;
    }
    if (!empty($config['show_otp_on_screen']) && $purpose !== 'login') {
        $_SESSION['account_email_debug'] = $code;
    }
    send_mail($email, 'Your Precision Agritech sign-in code', "Your sign-in code is " . $code . "\n\nIt works for 10 minutes. If you did not ask for it, ignore this message.");
}

function email_code_check(string $email, string $code, string $purpose = 'login'): void
{
    if ($purpose === 'login' && !empty($_SESSION['email_blocked'])) {
        throw new RuntimeException('That code is not right.');
    }
    if ($purpose !== 'login' && !empty($_SESSION['account_email_blocked'])) {
        throw new RuntimeException('That code is not right.');
    }
    $code = preg_replace('/\D+/', '', $code) ?? '';
    $stmt = db()->prepare('SELECT * FROM email_codes WHERE email = ? AND purpose = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
    $stmt->execute([$email, $purpose]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('That code has expired. Ask for a new one.');
    }
    if ((int) $row['attempts'] >= 5) {
        throw new RuntimeException('Too many tries. Ask for a new code later.');
    }
    $good = hash_equals((string) $row['code_hash'], hash('sha256', (string) $row['salt'] . $code));
    if (!$good) {
        db()->prepare('UPDATE email_codes SET attempts = attempts + 1 WHERE id = ?')->execute([(int) $row['id']]);
        $left = 4 - (int) $row['attempts'];
        if ($left <= 0) {
            throw new RuntimeException('Too many tries. Ask for a new code later.');
        }
        throw new RuntimeException('That code is not right.');
    }
    db()->prepare('DELETE FROM email_codes WHERE email = ?')->execute([$email]);
}

function finish_customer(array $user): void
{
    if (($user['role'] ?? '') === 'admin' || ($user['status'] ?? '') === 'disabled') {
        audit_log(0, 'customer_signin_refused', 'user', (int) ($user['id'] ?? 0), (string) ($user['email'] ?? ''), 'refused');
        throw new RuntimeException('That sign-in is not right.');
    }
    sign_in_user($user);
}

function google_redirect_uri(): string
{
    global $config;
    return rtrim((string) ($config['site_url'] ?? ''), '/') . '/auth/google/callback';
}
