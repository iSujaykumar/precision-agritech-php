<?php
declare(strict_types=1);

require_once __DIR__ . '/qrcode.php';

function safe_shop_href(string $href, string $fallback): string
{
    $href = trim($href);
    if (!preg_match('#^/[A-Za-z0-9/_-]{0,80}$#', $href)) {
        return $fallback;
    }
    return $href;
}
function upi_id_ok(string $pa): bool
{
    return (bool) preg_match('/^[A-Za-z0-9.\-_]{2,40}@[A-Za-z]{2,20}$/', $pa);
}

function upi_pay_link(string $pa, string $pn, string $orderNumber, int $amount): string
{
    $pa = trim($pa);
    $note = preg_replace('/[^A-Za-z0-9\-]/', '', $orderNumber) ?? '';
    if (!upi_id_ok($pa) || $amount < 1 || $note === '') {
        return '';
    }
    $pn = trim($pn);
    if ($pn === '') {
        $pn = 'Precision Agritech';
    }
    return 'upi://pay?pa=' . rawurlencode($pa)
        . '&pn=' . rawurlencode($pn)
        . '&am=' . rawurlencode((string) $amount)
        . '&cu=INR&tn=' . rawurlencode($note);
}

function qr_symbol(string $text): QRCode
{
    if ($text === '' || strlen($text) > 500) {
        throw new RuntimeException('That QR text cannot be encoded.');
    }
    $qr = new QRCode();
    $qr->setErrorCorrectLevel(QR_ERROR_CORRECT_LEVEL_M);
    $qr->addData($text, QR_MODE_8BIT_BYTE);
    for ($version = 2; $version <= 10; $version++) {
        $qr->setTypeNumber($version);
        $failed = false;
        set_error_handler(static function () use (&$failed): bool {
            $failed = true;
            return true;
        });
        try {
            $qr->make();
        } catch (Throwable $err) {
            $failed = true;
        }
        restore_error_handler();
        if (!$failed) {
            return $qr;
        }
    }
    throw new RuntimeException('That QR text cannot be encoded.');
}

function qr_svg(string $text, string $label): string
{
    $qr = qr_symbol($text);
    $count = $qr->getModuleCount();
    $quiet = 4;
    $span = $count + ($quiet * 2);
    $rects = '';
    for ($row = 0; $row < $count; $row++) {
        $col = 0;
        while ($col < $count) {
            if (!$qr->isDark($row, $col)) {
                $col++;
                continue;
            }
            $start = $col;
            while ($col < $count && $qr->isDark($row, $col)) {
                $col++;
            }
            $rects .= '<rect x="' . ($start + $quiet) . '" y="' . ($row + $quiet) . '" width="' . ($col - $start) . '" height="1"/>';
        }
    }
    return '<svg class="upi-qr" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $span . ' ' . $span . '" role="img" aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">'
        . '<rect width="100%" height="100%" fill="#ffffff"/>'
        . '<g fill="#14221b">' . $rects . '</g></svg>';
}

function upi_qr_block(string $pa, string $pn, string $orderNumber, int $amount, string $mode, string $uploaded): string
{
    $link = upi_pay_link($pa, $pn, $orderNumber, $amount);
    if ($mode === 'upload' && $uploaded !== '') {
        $safe = htmlspecialchars($uploaded, ENT_QUOTES, 'UTF-8');
        return '<img class="upi-qr" src="' . $safe . '" alt="Uploaded UPI QR code" width="220" height="220">'
            . '<p>This is the nursery\'s own QR image. It may not include the amount. Pay <strong>' . htmlspecialchars((string) $amount, ENT_QUOTES, 'UTF-8') . '</strong> rupees and use ' . htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8') . ' as the note.</p>';
    }
    if ($link === '') {
        return '<p class="flash bad" role="alert">UPI is not configured. Please choose another method or contact us.</p>';
    }
    return qr_svg($link, 'UPI QR for ' . $orderNumber . ', ' . $amount . ' rupees');
}
