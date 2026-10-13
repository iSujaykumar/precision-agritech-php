<?php
declare(strict_types=1);

function phone_directory(): array
{
    try {
        return db()->query('SELECT id, digits, label, sort_order, enabled, show_public, whatsapp_enabled FROM phone_numbers ORDER BY sort_order, id')->fetchAll();
    } catch (Throwable $err) {
        return [];
    }
}

function public_phone_rows(): array
{
    $rows = [];
    foreach (phone_directory() as $row) {
        if ((int) $row['enabled'] === 1 && (int) $row['show_public'] === 1) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function whatsapp_phone_rows(): array
{
    $rows = [];
    foreach (phone_directory() as $row) {
        if ((int) $row['enabled'] === 1 && (int) $row['whatsapp_enabled'] === 1 && preg_match('/^[6-9][0-9]{9}$/', (string) $row['digits'])) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function whatsapp_digits_for(string $context = 'session'): string
{
    $rows = whatsapp_phone_rows();
    if (!$rows) {
        return '';
    }
    $mode = 'random';
    $fixedId = 0;
    try {
        $modeRow = db()->prepare('SELECT `key`, `value` FROM settings WHERE `key` IN (\'whatsapp_mode\', \'whatsapp_fixed_id\')');
        $modeRow->execute();
        foreach ($modeRow as $item) {
            if ($item['key'] === 'whatsapp_mode' && $item['value'] !== '') {
                $mode = (string) $item['value'];
            }
            if ($item['key'] === 'whatsapp_fixed_id') {
                $fixedId = (int) $item['value'];
            }
        }
    } catch (Throwable $err) {
        $mode = shop_setting('whatsapp_mode', 'random');
        $fixedId = (int) shop_setting('whatsapp_fixed_id', '0');
    }
    if ($context === 'fixed' || $mode === 'fixed') {
        foreach ($rows as $row) {
            if ((int) $row['id'] === $fixedId) {
                return '91' . $row['digits'];
            }
        }
        return '91' . $rows[0]['digits'];
    }
    if ($context !== 'session') {
        return '91' . $rows[0]['digits'];
    }
    if ($mode === 'round_robin') {
        if (empty($_SESSION['wa_digits'])) {
            $n = (int) shop_setting('whatsapp_rr', '0');
            $pick = $rows[$n % count($rows)];
            $_SESSION['wa_digits'] = '91' . $pick['digits'];
            try {
                db()->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)')
                    ->execute(['whatsapp_rr', (string) ($n + 1)]);
            } catch (Throwable $err) {
                error_log('WhatsApp round robin skipped');
            }
        }
        return (string) $_SESSION['wa_digits'];
    }
    $allowed = array_map(static fn ($row) => '91' . $row['digits'], $rows);
    $current = (string) ($_SESSION['wa_digits'] ?? '');
    if ($current === '' || !in_array($current, $allowed, true)) {
        $pick = $rows[random_int(0, count($rows) - 1)];
        $_SESSION['wa_digits'] = '91' . $pick['digits'];
    }
    return (string) $_SESSION['wa_digits'];
}

function whatsapp_digits(): string
{
    return whatsapp_digits_for('session');
}

function wa_href(string $text): string
{
    $digits = whatsapp_digits();
    if ($digits === '') {
        return '';
    }
    return 'https://wa.me/' . $digits . '?text=' . rawurlencode($text);
}

function shop_phone_digits(): string
{
    $rows = public_phone_rows();
    if ($rows) {
        return (string) $rows[0]['digits'];
    }
    $digits = preg_replace('/\D+/', '', shop_setting('shop_phone', '8080370152')) ?? '';
    if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
        $digits = substr($digits, 2);
    }
    $allowed = ['8080370152', '8421577787', '8421577725'];
    if (!in_array($digits, $allowed, true)) {
        return '8080370152';
    }
    return $digits;
}

function shop_phone_links(bool $labels = true): string
{
    $rows = public_phone_rows();
    if (!$rows) {
        $digits = shop_phone_digits();
        $link = '<a href="tel:+91' . e($digits) . '">' . e($digits) . '</a>';
        return $labels ? $link : 'Sales<br>' . $link;
    }
    $parts = [];
    foreach ($rows as $row) {
        $digits = (string) $row['digits'];
        $link = '<a href="tel:+91' . e($digits) . '">' . e($digits) . '</a>';
        if ($labels) {
            $label = trim((string) $row['label']);
            $parts[] = $link . ($label !== '' ? ' <span class="muted">' . e($label) . '</span>' : '');
        } else {
            $parts[] = $link;
        }
    }
    if (!$labels) {
        return 'Sales<br>' . implode('<br>', $parts);
    }
    return implode('<br>', $parts);
}

