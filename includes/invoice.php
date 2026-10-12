<?php
declare(strict_types=1);

function issue_invoice(PDO $pdo, int $orderId): ?int
{
    $existing = $pdo->prepare('SELECT id FROM invoices WHERE order_id = ? LIMIT 1');
    $existing->execute([$orderId]);
    $found = $existing->fetchColumn();
    if ($found) {
        return (int) $found;
    }
    $order = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
    $order->execute([$orderId]);
    $row = $order->fetch();
    if (!$row || (string) $row['payment_status'] !== 'paid') {
        return null;
    }
    $fy = financial_year();
    $pdo->prepare('INSERT INTO invoice_counters (fy, last_no) VALUES (?, 0) ON DUPLICATE KEY UPDATE fy = fy')->execute([$fy]);
    $counter = $pdo->prepare('SELECT last_no FROM invoice_counters WHERE fy = ? FOR UPDATE');
    $counter->execute([$fy]);
    $next = (int) $counter->fetchColumn() + 1;
    $pdo->prepare('UPDATE invoice_counters SET last_no = ? WHERE fy = ?')->execute([$next, $fy]);
    $prefix = setting('invoice_prefix', 'PA');
    $number = $prefix . '/' . $fy . '/' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    $items = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
    $items->execute([$orderId]);
    $lines = $items->fetchAll();
    $snapshot = [
        'seller' => setting('seller_legal_name', 'Precision Agritech Private Limited'),
        'address' => setting('shop_address', ''),
        'phone' => setting('shop_phone', ''),
        'email' => setting('shop_email', ''),
        'gstin' => setting('gst_enabled', '0') === '1' ? setting('gstin', '') : '',
        'buyer' => $row['customer_name'],
        'buyer_phone' => $row['customer_phone'],
        'buyer_address' => $row['address_line'] . ', ' . $row['city'] . ', ' . $row['state_name'] . ' ' . $row['postal_code'],
        'list' => (string) ($row['price_list_name'] ?? 'Seasonal (2026-27)'),
        'lines' => $lines,
        'subtotal' => (int) $row['subtotal_inr'],
        'discount' => (int) $row['discount_inr'],
        'seasonal' => (int) ($row['seasonal_discount_inr'] ?? 0),
        'shipping' => (int) $row['shipping_inr'],
        'total' => (int) $row['total_inr'],
        'total_paise' => isset($row['total_paise']) ? (int) $row['total_paise'] : (int) $row['total_inr'] * 100,
        'words' => amount_paise_words(isset($row['total_paise']) ? (int) $row['total_paise'] : (int) $row['total_inr'] * 100),
        'terms' => setting('invoice_terms', ''),
    ];
    $pdo->prepare('INSERT INTO invoices (order_id, invoice_number, fy, total_inr, snapshot_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())')
        ->execute([$orderId, $number, $fy, (int) $row['total_inr'], json_encode($snapshot, JSON_UNESCAPED_UNICODE)]);
    $id = (int) $pdo->lastInsertId();
    audit_log(0, 'invoice_issued', 'invoice', $id, null, $number);
    return $id;
}

function issue_credit_note(PDO $pdo, int $orderId, int $amount, string $reference): ?int
{
    if ($amount < 1) {
        return null;
    }
    $inv = $pdo->prepare('SELECT id, invoice_number FROM invoices WHERE order_id = ?');
    $inv->execute([$orderId]);
    $invoice = $inv->fetch();
    if (!$invoice) {
        return null;
    }
    $fy = financial_year();
    $key = 'C' . $fy;
    $pdo->prepare('INSERT INTO invoice_counters (fy, last_no) VALUES (?, 0) ON DUPLICATE KEY UPDATE fy = fy')->execute([$key]);
    $counter = $pdo->prepare('SELECT last_no FROM invoice_counters WHERE fy = ? FOR UPDATE');
    $counter->execute([$key]);
    $next = (int) $counter->fetchColumn() + 1;
    $pdo->prepare('UPDATE invoice_counters SET last_no = ? WHERE fy = ?')->execute([$next, $key]);
    $number = 'CN/' . $fy . '/' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    $snapshot = [
        'invoice_number' => (string) $invoice['invoice_number'],
        'amount' => $amount,
        'reference' => $reference,
        'words' => amount_in_words($amount),
    ];
    $pdo->prepare('INSERT INTO credit_notes (order_id, invoice_id, credit_number, fy, amount_inr, snapshot_json, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())')
        ->execute([$orderId, (int) $invoice['id'], $number, $fy, $amount, json_encode($snapshot, JSON_UNESCAPED_UNICODE)]);
    $id = (int) $pdo->lastInsertId();
    audit_log(0, 'credit_note', 'credit_note', $id, (string) $invoice['invoice_number'], $number);
    return $id;
}

function invoice_lines_text(array $snapshot, string $number, string $when): array
{
    $lines = [
        (string) ($snapshot['seller'] ?? 'Precision Agritech'),
        (string) ($snapshot['address'] ?? ''),
        'Phone ' . (string) ($snapshot['phone'] ?? ''),
        'INVOICE ' . $number,
        'Date ' . $when,
        'Price list: ' . (string) ($snapshot['list'] ?? 'Seasonal (2026-27)'),
        'Bill to: ' . (string) ($snapshot['buyer'] ?? ''),
        (string) ($snapshot['buyer_address'] ?? ''),
        '----',
    ];
    foreach ($snapshot['lines'] ?? [] as $line) {
        $tray = (int) ($line['tray_size'] ?? 0);
        $paise = $line['seedling_paise'] ?? null;
        $rate = $paise !== null ? number_format(((int) $paise) / 100, 2, '.', '') : '';
        $money = isset($line['line_paise']) && $line['line_paise'] !== null ? inr_paise((int) $line['line_paise']) : ('Rs ' . (int) $line['line_total_inr']);
        $lines[] = (int) $line['quantity'] . ' x ' . $line['product_name']
            . ($tray > 0 ? ' (' . $tray . ' seedlings/tray' . ($rate !== '' ? ', Rs ' . $rate . ' each' : '') . ')' : '')
            . '  ' . $money;
    }
    $lines[] = 'Subtotal Rs ' . (int) ($snapshot['subtotal'] ?? 0);
    if ((int) ($snapshot['discount'] ?? 0) > 0) {
        $lines[] = 'Coupon Rs ' . (int) $snapshot['discount'];
    }
    if ((int) ($snapshot['seasonal'] ?? 0) > 0) {
        $lines[] = 'Seasonal discount Rs ' . (int) $snapshot['seasonal'];
    }
    $lines[] = 'Delivery Rs ' . (int) ($snapshot['shipping'] ?? 0);
    $lines[] = 'Total Rs ' . (int) ($snapshot['total'] ?? 0);
    $lines[] = (string) ($snapshot['words'] ?? '');
    $lines[] = 'This is a computer-generated invoice.';
    if ((string) ($snapshot['gstin'] ?? '') === '') {
        $lines[] = 'GST is not charged on this invoice.';
    }
    return $lines;
}
