<?php
declare(strict_types=1);

function twilio_call(string $path, array $fields): array
{
    global $config;
    if (!twilio_configured()) {
        throw new RuntimeException('SMS codes are not set up on this server yet.');
    }
    $url = 'https://verify.twilio.com/v2/Services/' . rawurlencode((string) $config['twilio_verify']) . '/' . $path;
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('The SMS service could not be reached.');
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_USERPWD => $config['twilio_sid'] . ':' . $config['twilio_token'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode((string) $raw, true);
    if (!is_array($data) || $status >= 400) {
        error_log('Twilio Verify HTTP ' . $status);
        if ($status === 404) {
            throw new RuntimeException('That code has expired. Ask for a new one.');
        }
        throw new RuntimeException('The SMS service could not complete that request.');
    }
    return $data;
}

function otp_send_allowed(string $phone): void
{
    $recent = db()->prepare('SELECT COUNT(*) FROM mobile_verifications WHERE phone = ? AND created_at > (NOW() - INTERVAL 45 SECOND)');
    $recent->execute([$phone]);
    if ((int) $recent->fetchColumn() > 0) {
        throw new RuntimeException('Wait a moment before asking for another code.');
    }
    $hour = db()->prepare('SELECT COUNT(*) FROM mobile_verifications WHERE phone = ? AND created_at > (NOW() - INTERVAL 1 HOUR)');
    $hour->execute([$phone]);
    if ((int) $hour->fetchColumn() >= 5) {
        throw new RuntimeException('Too many codes were requested. Try again later.');
    }
    $ip = db()->prepare('SELECT COUNT(*) FROM mobile_verifications WHERE ip = ? AND created_at > (NOW() - INTERVAL 1 HOUR)');
    $ip->execute([client_ip()]);
    if ((int) $ip->fetchColumn() >= 10) {
        throw new RuntimeException('Too many codes were requested. Try again later.');
    }
}

function record_decoy_otp(string $phone): void
{
    otp_send_allowed($phone);
    db()->prepare("INSERT INTO mobile_verifications (phone, purpose, status, expires_at, ip) VALUES (?, 'login', 'decoy', DATE_ADD(NOW(), INTERVAL 10 MINUTE), ?)")
        ->execute([$phone, client_ip()]);
    $_SESSION['otp_phone'] = $phone;
    $_SESSION['otp_purpose'] = 'login';
    $_SESSION['otp_id'] = 0;
    $_SESSION['otp_decoy'] = 1;
}

function start_mobile_code(string $phone, string $purpose, ?int $userId): void
{
    otp_send_allowed($phone);
    db()->prepare("UPDATE mobile_verifications SET status = 'expired' WHERE phone = ? AND purpose = ? AND status = 'pending'")
        ->execute([$phone, $purpose]);
    $result = twilio_call('Verifications', [
        'To' => phone_e164($phone),
        'Channel' => 'sms',
    ]);
    db()->prepare('INSERT INTO mobile_verifications (phone, purpose, provider_sid, status, expires_at, ip, user_id) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), ?, ?)')
        ->execute([$phone, $purpose, (string) ($result['sid'] ?? ''), 'pending', client_ip(), $userId]);
    $_SESSION['otp_id'] = (int) db()->lastInsertId();
    $_SESSION['otp_phone'] = $phone;
    $_SESSION['otp_purpose'] = $purpose;
    unset($_SESSION['otp_decoy']);
}

function check_mobile_code(string $code, string $purpose): string
{
    $id = (int) ($_SESSION['otp_id'] ?? 0);
    $phone = (string) ($_SESSION['otp_phone'] ?? '');
    if ($phone === '' || (string) ($_SESSION['otp_purpose'] ?? '') !== $purpose || !preg_match('/^[0-9]{4,10}$/', $code)) {
        throw new RuntimeException('That code is not right.');
    }
    if (!empty($_SESSION['otp_decoy']) || $id < 1) {
        note_login_failure('otp:' . $phone);
        throw new RuntimeException('That code is not right.');
    }
    $burn = db()->prepare("UPDATE mobile_verifications SET attempts = attempts + 1 WHERE id = ? AND phone = ? AND purpose = ? AND status = 'pending' AND attempts < 5 AND expires_at > NOW()");
    $burn->execute([$id, $phone, $purpose]);
    if ($burn->rowCount() !== 1) {
        db()->prepare("UPDATE mobile_verifications SET status = 'locked' WHERE id = ? AND status = 'pending' AND attempts >= 5")->execute([$id]);
        throw new RuntimeException('That code is not right. Ask for a new one.');
    }
    $result = twilio_call('VerificationCheck', [
        'To' => phone_e164($phone),
        'Code' => $code,
    ]);
    if (($result['status'] ?? '') !== 'approved') {
        throw new RuntimeException('That code is not right.');
    }
    $done = db()->prepare("UPDATE mobile_verifications SET status = 'approved', verified_at = NOW() WHERE id = ? AND status = 'pending'");
    $done->execute([$id]);
    if ($done->rowCount() !== 1) {
        throw new RuntimeException('That code is not right.');
    }
    unset($_SESSION['otp_id'], $_SESSION['otp_phone'], $_SESSION['otp_purpose'], $_SESSION['otp_decoy']);
    return $phone;
}
