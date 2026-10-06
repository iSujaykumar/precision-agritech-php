<?php
declare(strict_types=1);

function send_mail(string $to, string $subject, string $textBody, ?string $replyTo = null): bool
{
    try {
        global $config;
        $from = (string) ($config['mail_from'] ?? 'info@precisionagritech.in');
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log('Mail skipped: bad recipient');
            return false;
        }
        $shop = 'Precision Agritech, Theur, Pune. 9011975959';
        $body = rtrim($textBody) . "\n\n--\n" . $shop . "\n";
        if (($config['smtp_host'] ?? '') !== '' && ($config['smtp_user'] ?? '') !== '') {
            return smtp_send($to, $from, $subject, $body, $replyTo);
        }
        $headers = 'From: ' . $from . "\r\nReply-To: " . ($replyTo ?: $from) . "\r\nContent-Type: text/plain; charset=UTF-8";
        $ok = @mail($to, $subject, $body, $headers, '-f' . $from);
        if (!$ok) {
            error_log('mail() did not accept a message to the nursery or a customer.');
        }
        return (bool) $ok;
    } catch (Throwable $err) {
        error_log('Mail failed: ' . $err->getMessage());
        return false;
    }
}

function smtp_send(string $to, string $from, string $subject, string $body, ?string $replyTo): bool
{
    global $config;
    $host = (string) $config['smtp_host'];
    $port = (int) ($config['smtp_port'] ?? 587);
    $secure = strtolower((string) ($config['smtp_secure'] ?? 'tls'));
    $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $fp = @stream_socket_client($remote, $errno, $errstr, 15);
    if (!$fp) {
        error_log('SMTP connect failed: ' . $errstr);
        return false;
    }
    stream_set_timeout($fp, 15);
    $read = static function () use ($fp): string {
        $data = '';
        while ($line = fgets($fp, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $cmd = static function (string $command) use ($fp, $read): string {
        fwrite($fp, $command . "\r\n");
        return $read();
    };
    $greet = $read();
    if (!str_starts_with($greet, '220')) {
        error_log('SMTP greeting failed');
        fclose($fp);
        return false;
    }
    $ehlo = $cmd('EHLO precisionagritech.in');
    if ($secure === 'tls') {
        $start = $cmd('STARTTLS');
        if (!str_starts_with($start, '220')) {
            error_log('SMTP STARTTLS failed');
            fclose($fp);
            return false;
        }
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            error_log('SMTP TLS failed');
            fclose($fp);
            return false;
        }
        $ehlo = $cmd('EHLO precisionagritech.in');
    }
    if (!str_starts_with($ehlo, '250')) {
        error_log('SMTP EHLO failed');
        fclose($fp);
        return false;
    }
    $user = (string) $config['smtp_user'];
    $pass = (string) $config['smtp_pass'];
    if (!str_starts_with($cmd('AUTH LOGIN'), '334')
        || !str_starts_with($cmd(base64_encode($user)), '334')
        || !str_starts_with($cmd(base64_encode($pass)), '235')) {
        error_log('SMTP login failed');
        fclose($fp);
        return false;
    }
    if (!str_starts_with($cmd('MAIL FROM:<' . $from . '>'), '250')
        || !str_starts_with($cmd('RCPT TO:<' . $to . '>'), '250')
        || !str_starts_with($cmd('DATA'), '354')) {
        error_log('SMTP envelope failed');
        fclose($fp);
        return false;
    }
    $safeSubject = str_replace(["\r", "\n"], '', $subject);
    $payload = 'From: ' . $from . "\r\n"
        . 'To: ' . $to . "\r\n"
        . 'Reply-To: ' . ($replyTo ?: $from) . "\r\n"
        . 'Subject: ' . $safeSubject . "\r\n"
        . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n"
        . str_replace("\n.", "\n..", $body) . "\r\n.";
    $sent = $cmd($payload);
    $cmd('QUIT');
    fclose($fp);
    if (!str_starts_with($sent, '250')) {
        error_log('SMTP data was refused');
        return false;
    }
    return true;
}

function send_verify_email(array $user): void
{
    $token = bin2hex(random_bytes(32));
    db()->prepare("INSERT INTO email_tokens (user_id, purpose, token_hash, expires_at) VALUES (?, 'verify', ?, DATE_ADD(NOW(), INTERVAL 1 DAY))")
        ->execute([(int) $user['id'], hash('sha256', $token)]);
    global $config;
    $link = rtrim((string) ($config['site_url'] ?? ''), '/') . '/verify-email?token=' . $token;
    send_mail((string) $user['email'], 'Confirm your Precision Agritech email', "Open this link within one day to confirm the email on your account.\n\n" . $link);
}

function bank_details(): array
{
    return [
        'name' => setting('bank_account_name', ''),
        'bank' => setting('bank_name', ''),
        'number' => setting('bank_account_number', ''),
        'ifsc' => setting('bank_ifsc', ''),
        'upi' => setting('upi_id', ''),
    ];
}

function bank_ready(): bool
{
    $bank = bank_details();
    return $bank['name'] !== '' && $bank['number'] !== '' && ($bank['ifsc'] !== '' || $bank['upi'] !== '');
}

function bank_instructions(string $orderNumber, int $amount): string
{
    $bank = bank_details();
    $lines = [
        'How to pay ' . inr($amount),
        'Account name: ' . $bank['name'],
        'Bank: ' . $bank['bank'],
        'Account number: ' . $bank['number'],
        'IFSC: ' . $bank['ifsc'],
    ];
    if ($bank['upi'] !== '') {
        $lines[] = 'UPI: ' . $bank['upi'];
    }
    $lines[] = 'Payment reference: ' . $orderNumber;
    $lines[] = 'The nursery confirms the payment after it arrives. Trays stay reserved until then.';
    return implode("\n", $lines);
}
