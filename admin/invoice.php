<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
$id = (int) ($_GET['order'] ?? 0);
$kind = (string) ($_GET['kind'] ?? 'pdf');
if (!in_array($kind, ['pdf', 'html', 'proforma'], true)) {
    $kind = 'pdf';
}
$stmt = db()->prepare('SELECT * FROM orders WHERE id = ?');
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) {
    http_response_code(404);
    admin_open('Invoice', 'orders');
    echo '<p>Order not found.</p>';
    admin_close();
    exit;
}
header('Cache-Control: private, no-store');
header('Pragma: no-cache');
$paid = (string) $order['payment_status'] === 'paid';
if ($kind !== 'proforma' && !$paid) {
    http_response_code(404);
    admin_open('Invoice', 'orders');
    echo '<p>A tax invoice is available only after the order is paid. Use the proforma link before that.</p>';
    admin_close();
    exit;
}
if ($paid && $kind !== 'proforma') {
    $inv = db()->prepare('SELECT * FROM invoices WHERE order_id = ?');
    $inv->execute([$id]);
    $invoice = $inv->fetch();
    if (!$invoice) {
        $pdo = db();
        $pdo->beginTransaction();
        issue_invoice($pdo, $id);
        $pdo->commit();
        $inv->execute([$id]);
        $invoice = $inv->fetch();
    }
    if (!$invoice) {
        http_response_code(404);
        exit('The invoice is not ready.');
    }
    $snapshot = json_decode((string) $invoice['snapshot_json'], true) ?: [];
    $text = invoice_lines_text($snapshot, (string) $invoice['invoice_number'], (string) $invoice['created_at']);
    $title = (string) $invoice['invoice_number'];
} else {
    $items = db()->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
    $items->execute([$id]);
    $snapshot = [
        'seller' => setting('seller_legal_name', 'Precision Agritech Private Limited'),
        'address' => setting('shop_address', ''),
        'phone' => setting('shop_phone', ''),
        'buyer' => $order['customer_name'],
        'buyer_address' => $order['address_line'] . ', ' . $order['city'] . ' ' . $order['postal_code'],
        'list' => (string) ($order['price_list_name'] ?? 'Seasonal (2026-27)'),
        'lines' => $items->fetchAll(),
        'subtotal' => (int) $order['subtotal_inr'],
        'discount' => (int) $order['discount_inr'],
        'seasonal' => (int) ($order['seasonal_discount_inr'] ?? 0),
        'shipping' => (int) $order['shipping_inr'],
        'total' => (int) $order['total_inr'],
        'words' => amount_in_words((int) $order['total_inr']),
        'gstin' => '',
    ];
    $text = invoice_lines_text($snapshot, 'PROFORMA', (string) $order['created_at']);
    array_unshift($text, 'PROFORMA / ESTIMATE - NOT A TAX INVOICE');
    $title = 'proforma-' . (string) $order['order_number'];
    $kind = 'proforma';
}
if ($kind === 'html') {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>' . e($title) . '</title>';
    echo '<style>body{font-family:sans-serif;margin:2rem;color:#14221b} p{margin:.25rem 0}</style></head><body>';
    foreach ($text as $line) {
        echo '<p>' . e($line) . '</p>';
    }
    foreach ($snapshot['lines'] ?? [] as $line) {
        if (!empty($line['marathi_name'])) {
            echo '<p>' . e((string) $line['marathi_name']) . '</p>';
        }
    }
    echo '<p><button type="button" onclick="print()">Print</button></p></body></html>';
    exit;
}
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '', $title) . '.pdf"');
echo pdf_document($text);
