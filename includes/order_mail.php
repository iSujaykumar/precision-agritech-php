<?php
declare(strict_types=1);

function notify_mail(string $to, string $subject, string $body): bool
{
    return send_mail($to, $subject, $body);
}

function mail_order_customer(array $order, string $intro): void
{
    $email = trim((string) ($order['customer_email'] ?? ''));
    if ($email === '') {
        return;
    }
    $lines = $intro . "\n\nOrder " . ($order['order_number'] ?? '') . "\nTotal " . inr((int) ($order['total_inr'] ?? 0)) . "\n";
    if (!empty($order['reserved_until'])) {
        $lines .= 'Reserved until ' . fmt_when((string) $order['reserved_until']) . "\n";
    }
    $method = (string) ($order['payment_method'] ?? '');
    if (in_array($method, ['upi', 'bank_transfer'], true) && ($order['payment_status'] ?? '') !== 'paid') {
        $lines .= "\n" . bank_instructions((string) $order['order_number'], (int) $order['total_inr']) . "\n";
        $token = (string) ($order['lookup_token'] ?? '');
        global $config;
        $base = rtrim((string) ($config['site_url'] ?? ''), '/');
        if ($token !== '' && $base !== '') {
            $lines .= "Order page: " . $base . "/order/" . $token . "\n";
            $lines .= "The QR code is on that page. This email does not include a payment image.\n";
        }
    }
    if (($order['payment_status'] ?? '') === 'paid' && setting('invoice_email_on', '1') === '1') {
        $token = (string) ($order['lookup_token'] ?? '');
        global $config;
        $base = rtrim((string) ($config['site_url'] ?? ''), '/');
        if ($token !== '' && $base !== '') {
            $lines .= "Invoice: " . $base . "/order/" . $token . "?invoice=pdf\n";
        }
    }
    notify_mail($email, 'Order ' . ($order['order_number'] ?? '') . ' - Precision Agritech', $lines);
}

function mail_staff(string $subject, string $body): void
{
    notify_mail(shop_setting('shop_email', 'info@precisionagritech.in'), $subject, $body);
}

