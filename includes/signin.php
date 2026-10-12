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
        usleep(random_int(150000, 250000));
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
    usleep(random_int(150000, 250000));
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
    if (rate_limited('mailwrong:' . $email, 10, 60) || rate_limited('mailwrong-ip:' . client_ip(), 20, 60)) {
        throw new RuntimeException('Too many tries. Wait an hour and try again.');
    }
    $good = hash_equals((string) $row['code_hash'], hash('sha256', (string) $row['salt'] . $code));
    if (!$good) {
        $bump = db()->prepare('UPDATE email_codes SET attempts = attempts + 1 WHERE id = ? AND attempts < 5');
        $bump->execute([(int) $row['id']]);
        note_rate('mailwrong:' . $email);
        note_rate('mailwrong-ip:' . client_ip());
        if ($bump->rowCount() !== 1) {
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

function google_b64(string $value): string
{
    $value = strtr($value, '-_', '+/');
    $pad = strlen($value) % 4;
    if ($pad > 0) {
        $value .= str_repeat('=', 4 - $pad);
    }
    return (string) base64_decode($value, true);
}

function google_der_len(int $len): string
{
    if ($len < 128) {
        return chr($len);
    }
    $bin = ltrim(pack('N', $len), "\x00");
    return chr(0x80 | strlen($bin)) . $bin;
}

function google_jwk_pem(array $jwk): string
{
    $n = google_b64((string) ($jwk['n'] ?? ''));
    $e = google_b64((string) ($jwk['e'] ?? ''));
    $int = static function (string $bin): string {
        if ($bin !== '' && (ord($bin[0]) & 0x80)) {
            $bin = "\x00" . $bin;
        }
        return "\x02" . google_der_len(strlen($bin)) . $bin;
    };
    $rsa = $int($n) . $int($e);
    $rsa = "\x30" . google_der_len(strlen($rsa)) . $rsa;
    $algo = hex2bin('300d06092a864886f70d0101010500');
    $bit = "\x03" . google_der_len(strlen($rsa) + 1) . "\x00" . $rsa;
    $spki = "\x30" . google_der_len(strlen($algo) + strlen($bit)) . $algo . $bit;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

function google_certs(): array
{
    $file = dirname(__DIR__) . '/uploads/private/google-certs.json';
    if (is_file($file) && (time() - (int) filemtime($file)) < 3600) {
        $cached = json_decode((string) file_get_contents($file), true);
        if (is_array($cached) && !empty($cached['keys'])) {
            return $cached;
        }
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Google certificates are unavailable.');
    }
    $ch = curl_init('https://www.googleapis.com/oauth2/v3/certs');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8]);
    $raw = curl_exec($ch);
    curl_close($ch);
    $json = json_decode((string) $raw, true);
    if (!is_array($json) || empty($json['keys'])) {
        throw new RuntimeException('Google certificates are unavailable.');
    }
    if (!is_dir(dirname($file))) {
        mkdir(dirname($file), 0755, true);
    }
    file_put_contents($file, (string) $raw);
    return $json;
}

function google_verify_id_token(string $idToken, string $audience, string $nonce): array
{
    $parts = explode('.', $idToken);
    if (count($parts) !== 3) {
        throw new RuntimeException('That sign-in could not be checked.');
    }
    $header = json_decode(google_b64($parts[0]), true);
    $payload = json_decode(google_b64($parts[1]), true);
    if (!is_array($header) || !is_array($payload) || ($header['alg'] ?? '') !== 'RS256') {
        throw new RuntimeException('That sign-in could not be checked.');
    }
    $pem = '';
    foreach (google_certs()['keys'] as $key) {
        if (is_array($key) && (string) ($key['kid'] ?? '') === (string) ($header['kid'] ?? '')) {
            $pem = google_jwk_pem($key);
            break;
        }
    }
    if ($pem === '' || !function_exists('openssl_verify')) {
        throw new RuntimeException('Google certificates are unavailable.');
    }
    $sig = google_b64($parts[2]);
    $ok = openssl_verify($parts[0] . '.' . $parts[1], $sig, $pem, OPENSSL_ALGO_SHA256);
    if ($ok !== 1) {
        throw new RuntimeException('That sign-in could not be checked.');
    }
    $iss = (string) ($payload['iss'] ?? '');
    if (!in_array($iss, ['accounts.google.com', 'https://accounts.google.com'], true)) {
        throw new RuntimeException('That sign-in could not be checked.');
    }
    if (!hash_equals($audience, (string) ($payload['aud'] ?? ''))) {
        throw new RuntimeException('That sign-in could not be checked.');
    }
    if ((int) ($payload['exp'] ?? 0) < time() || (int) ($payload['iat'] ?? 0) > time() + 300) {
        throw new RuntimeException('That sign-in expired. Please try again.');
    }
    if ($nonce === '' || !hash_equals($nonce, (string) ($payload['nonce'] ?? ''))) {
        throw new RuntimeException('That sign-in could not be checked.');
    }
    $verified = $payload['email_verified'] ?? false;
    if ($verified !== true && $verified !== 'true') {
        throw new RuntimeException('Google has not verified that email.');
    }
    return $payload;
}
