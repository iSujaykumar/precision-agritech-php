<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
connect_or_explain();

function google_fail(string $message): void
{
    $_SESSION['flash'] = $message;
    header('Location: /login');
    exit;
}

try {
    if (!google_ready()) {
        google_fail('Google sign-in is not available right now.');
    }
    if (isset($_GET['error'])) {
        google_fail('Google sign-in was cancelled. You can try again or use email.');
    }
    $state = (string) ($_GET['state'] ?? '');
    $known = (string) ($_SESSION['google_state'] ?? '');
    unset($_SESSION['google_state']);
    if ($state === '' || $known === '' || !hash_equals($known, $state)) {
        google_fail('That sign-in expired. Please try again.');
    }
    global $config;
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_POSTFIELDS => http_build_query([
            'code' => (string) ($_GET['code'] ?? ''),
            'client_id' => $config['google_client_id'],
            'client_secret' => $config['google_client_secret'],
            'redirect_uri' => google_redirect_uri(),
            'grant_type' => 'authorization_code',
        ]),
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    $token = json_decode((string) $raw, true);
    $idToken = is_array($token) ? (string) ($token['id_token'] ?? '') : '';
    if ($idToken === '') {
        google_fail('Google sign-in is unavailable right now. Use email instead.');
    }
    $check = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($idToken));
    curl_setopt_array($check, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12]);
    $info = json_decode((string) curl_exec($check), true);
    curl_close($check);
    if (!is_array($info)) {
        google_fail('Google sign-in is unavailable right now. Use email instead.');
    }
    $iss = (string) ($info['iss'] ?? '');
    if (!in_array($iss, ['accounts.google.com', 'https://accounts.google.com'], true)) {
        google_fail('That sign-in could not be checked.');
    }
    if (!hash_equals((string) $config['google_client_id'], (string) ($info['aud'] ?? ''))) {
        google_fail('That sign-in could not be checked.');
    }
    if ((int) ($info['exp'] ?? 0) < time()) {
        google_fail('That sign-in expired. Please try again.');
    }
    $parts = explode('.', $idToken);
    $payload = [];
    if (isset($parts[1])) {
        $payload = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/')), true);
        if (!is_array($payload)) {
            $payload = [];
        }
    }
    if (($payload['sub'] ?? '') !== ($info['sub'] ?? '') || (string) ($payload['aud'] ?? '') !== (string) ($info['aud'] ?? '')) {
        google_fail('That sign-in could not be checked.');
    }
    $nonce = (string) ($_SESSION['google_nonce'] ?? '');
    unset($_SESSION['google_nonce']);
    $tokenNonce = (string) ($info['nonce'] ?? $payload['nonce'] ?? '');
    if ($nonce === '' || !hash_equals($nonce, $tokenNonce)) {
        google_fail('That sign-in could not be checked.');
    }
    if (($info['email_verified'] ?? '') !== 'true' && ($info['email_verified'] ?? false) !== true) {
        google_fail('Google has not verified that email.');
    }
    $email = strtolower(trim((string) ($info['email'] ?? '')));
    $sub = substr((string) ($info['sub'] ?? ''), 0, 64);
    $name = clean_text((string) ($info['name'] ?? 'Customer'), 120);
    if ($email === '' || $sub === '') {
        google_fail('Google did not share an email.');
    }
    $byGoogle = db()->prepare('SELECT * FROM users WHERE google_id = ? LIMIT 1');
    $byGoogle->execute([$sub]);
    $user = $byGoogle->fetch();
    if (!$user) {
        $byEmail = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $byEmail->execute([$email]);
        $user = $byEmail->fetch();
        if ($user && (($user['role'] ?? '') === 'admin' || ($user['status'] ?? '') === 'disabled')) {
            audit_log(0, 'customer_signin_refused', 'user', (int) $user['id'], $email, 'google');
            google_fail('That sign-in is not right.');
        }
        if ($user) {
            db()->prepare('UPDATE users SET google_id = ?, email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ? AND google_id IS NULL')
                ->execute([$sub, (int) $user['id']]);
            $user['google_id'] = $sub;
        } else {
            $hash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
            db()->prepare('INSERT INTO users (name, email, phone, password_hash, google_id, role, status, email_verified_at) VALUES (?, ?, NULL, ?, ?, ?, ?, NOW())')
                ->execute([$name !== '' ? $name : 'Customer', $email, $hash, $sub, 'customer', 'active']);
            $again = db()->prepare('SELECT * FROM users WHERE id = ?');
            $again->execute([(int) db()->lastInsertId()]);
            $user = $again->fetch();
        }
    }
    finish_customer($user);
    if (trim((string) ($user['phone'] ?? '')) === '') {
        header('Location: /account?need=mobile');
        exit;
    }
    header('Location: ' . login_destination());
    exit;
} catch (Throwable $err) {
    error_log('Google sign-in: ' . $err->getMessage());
    google_fail('Google sign-in is unavailable right now. Use email instead.');
}
