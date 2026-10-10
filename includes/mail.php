<?php
declare(strict_types=1);

function mail_header_safe(string $value): string
{
    return trim(str_replace(["\r", "\n", "%0a", "%0d", "\0"], '', $value));
}

function send_mail(string $to, string $subject, string $textBody, ?string $replyTo = null): bool
{
    try {
        global $config;
        $to = mail_header_safe($to);
        $subject = mail_header_safe($subject);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log('Mail skipped: bad recipient');
            return false;
        }
        $from = mail_header_safe((string) ($config['mail_from'] ?? 'info@precisionagritech.in'));
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $from = 'info@precisionagritech.in';
        }
        $name = 'Precision Agritech';
        try {
            $named = mail_header_safe(setting('seller_legal_name', 'Precision Agritech'));
            if ($named !== '') {
                $name = $named;
            }
            $configured = mail_header_safe(setting('mail_from', ''));
            if (filter_var($configured, FILTER_VALIDATE_EMAIL)) {
                $from = $configured;
            }
        } catch (Throwable $err) {
            error_log('Mail sender settings skipped');
        }
        $shop = $name . ', Theur, Pune.';
        $body = rtrim($textBody) . "\n\n--\n" . $shop . "\n";
        $reply = mail_header_safe((string) ($replyTo ?: $from));
        if (($config['smtp_host'] ?? '') !== '' && ($config['smtp_user'] ?? '') !== '') {
            return smtp_send($to, $from, $name, $subject, $body, $reply);
        }
        $headers = 'From: ' . $name . ' <' . $from . ">\r\nReply-To: " . $reply . "\r\nContent-Type: text/plain; charset=UTF-8";
        $ok = @mail($to, $subject, $body, $headers, '-f' . $from);
        if (!$ok) {
            error_log('mail() did not accept a message.');
        }
        return (bool) $ok;
    } catch (Throwable $err) {
        error_log('Mail failed: ' . $err->getMessage());
        return false;
    }
}

function smtp_send(string $to, string $from, string $fromName, string $subject, string $body, ?string $replyTo): bool
{
    global $config;
    $host = mail_header_safe((string) $config['smtp_host']);
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
        if (!str_starts_with($start, '220') || !stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
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
    $payload = 'From: ' . $fromName . ' <' . $from . ">\r\n"
        . 'To: ' . $to . "\r\n"
        . 'Reply-To: ' . ($replyTo ?: $from) . "\r\n"
        . 'Subject: ' . $subject . "\r\n"
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

function bank_details(): array
{
    return [
        'name' => setting('bank_account_name', ''),
        'bank' => setting('bank_name', ''),
        'number' => setting('bank_account_number', ''),
        'ifsc' => setting('bank_ifsc', ''),
        'upi' => setting('upi_id', 'precision9958@fbl'),
        'payee' => setting('upi_payee_name', 'Precision Agritech Private Limited'),
    ];
}

function bank_instructions(string $orderNumber, int $amount): string
{
    $bank = bank_details();
    $lines = [
        'Amount to pay: ' . inr($amount),
        'Payment reference: ' . $orderNumber,
        'UPI: ' . $bank['upi'],
        'Payee: ' . $bank['payee'],
    ];
    if ($bank['name'] !== '') {
        $lines[] = 'Account name: ' . $bank['name'];
        $lines[] = 'Bank: ' . $bank['bank'];
        $lines[] = 'Account number: ' . $bank['number'];
        $lines[] = 'IFSC: ' . $bank['ifsc'];
    }
    $extra = trim(setting('payment_instructions', ''));
    if ($extra !== '') {
        $lines[] = $extra;
    }
    $lines[] = 'Use ' . $orderNumber . ' as the payment reference.';
    return implode("\n", $lines);
}
