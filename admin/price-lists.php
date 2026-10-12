<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();

if (isset($_GET['export'])) {
    $id = (int) $_GET['export'];
    $list = db()->prepare('SELECT * FROM price_lists WHERE id = ?');
    $list->execute([$id]);
    $head = $list->fetch();
    if (!$head) {
        http_response_code(404);
        exit('List not found');
    }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9._-]/', '', (string) $head['slug']) . '.csv"');
    header('Cache-Control: private, no-store');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['item', 'variety', 'marathi_name', 'price_per_seedling', 'source', 'sr_no', 'notes', 'sku']);
    $items = db()->prepare('SELECT * FROM price_list_items WHERE price_list_id = ? ORDER BY id');
    $items->execute([$id]);
    foreach ($items as $item) {
        $rate = number_format(((int) $item['price_per_seedling_paise']) / 100, 2, '.', '');
        fputcsv($out, [
            csv_cell((string) $item['item_name']),
            csv_cell((string) $item['variety']),
            csv_cell((string) $item['marathi_name']),
            $rate,
            csv_cell((string) $head['source_ref']),
            '',
            csv_cell((string) $item['notes']),
            csv_cell((string) $item['sku']),
        ]);
    }
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_desk($admin, 'manager');
    try {
        require_csrf();
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'save_list') {
            $id = (int) ($_POST['id'] ?? 0);
            $from = trim((string) ($_POST['effective_from'] ?? ''));
            $to = trim((string) ($_POST['effective_to'] ?? ''));
            foreach (['from' => $from, 'to' => $to] as $label => $date) {
                if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    throw new RuntimeException('Dates must be blank or YYYY-MM-DD.');
                }
            }
            $before = db()->prepare('SELECT active, effective_from, effective_to FROM price_lists WHERE id = ?');
            $before->execute([$id]);
            $old = $before->fetch();
            if (!$old) {
                throw new RuntimeException('That price list was not found.');
            }
            $active = isset($_POST['active']) ? 1 : 0;
            db()->prepare('UPDATE price_lists SET effective_from = ?, effective_to = ?, active = ?, notes = ? WHERE id = ?')
                ->execute([$from !== '' ? $from : null, $to !== '' ? $to : null, $active, clean_text((string) ($_POST['notes'] ?? ''), 500), $id]);
            audit_log((int) $admin['id'], 'price_list', 'price_list', $id, (string) $old['active'], (string) $active);
            flash_set('Price list saved');
        } elseif ($action === 'toggle') {
            $itemId = (int) ($_POST['item_id'] ?? 0);
            $on = (int) ($_POST['active'] ?? 0) === 1 ? 1 : 0;
            db()->prepare('UPDATE price_list_items SET active = ? WHERE id = ?')->execute([$on, $itemId]);
            audit_log((int) $admin['id'], 'price_item', 'price_list_item', $itemId, null, (string) $on);
            flash_set($on === 1 ? 'Rate activated' : 'Rate set back to draft');
        } elseif ($action === 'preview') {
            $id = (int) ($_POST['list_id'] ?? 0);
            $file = $_FILES['csv'] ?? null;
            if (!$file || (int) ($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Choose a CSV file.');
            }
            if ((int) $file['size'] > 200000) {
                throw new RuntimeException('The CSV must be under 200 KB.');
            }
            $text = (string) file_get_contents((string) $file['tmp_name']);
            $parsed = price_csv_parse($text);
            $preview = price_import_preview($id, $parsed['rows']);
            $_SESSION['price_csv'] = ['list_id' => $id, 'csv' => $text, 'token' => bin2hex(random_bytes(8))];
            $_SESSION['price_preview'] = ['invalid' => $parsed['invalid'], 'summary' => $preview, 'token' => $_SESSION['price_csv']['token']];
            flash_set('Preview ready. Nothing was saved yet.');
        } elseif ($action === 'confirm') {
            $stored = $_SESSION['price_csv'] ?? null;
            $token = (string) ($_POST['token'] ?? '');
            if (!is_array($stored) || !hash_equals((string) ($stored['token'] ?? ''), $token)) {
                throw new RuntimeException('The preview expired. Upload the file again.');
            }
            $parsed = price_csv_parse((string) $stored['csv']);
            if ($parsed['invalid']) {
                throw new RuntimeException('Fix the invalid rows before importing.');
            }
            $count = price_import_apply((int) $stored['list_id'], $parsed['rows'], (int) $admin['id']);
            unset($_SESSION['price_csv'], $_SESSION['price_preview']);
            flash_set('Imported ' . $count . ' rows as drafts. Activate each one when the photo and description are ready.');
        } else {
            throw new RuntimeException('Unknown action.');
        }
        header('Location: /admin/price-lists');
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'The price list could not be saved.');
    }
}

admin_open('Price lists', 'products');
if ($error) {
    echo '<p class="flash bad" role="alert">' . e($error) . '</p>';
}
echo '<p class="muted">Seasonal (2026-27) is the normal tray price. Farmer rates show only after you mark that customer as a Farmer. New import rows stay inactive. The PDF and the Marathi photo were not in this workspace, so no rates were typed in from those files.</p>';
$lists = db()->query('SELECT * FROM price_lists ORDER BY id')->fetchAll();
foreach ($lists as $list) {
    $id = (int) $list['id'];
    $counts = db()->prepare('SELECT COUNT(*) AS n, SUM(active = 0) AS drafts FROM price_list_items WHERE price_list_id = ?');
    $counts->execute([$id]);
    $count = $counts->fetch();
    $today = (new DateTimeImmutable('now', shop_timezone()))->format('Y-m-d');
    $expired = ($list['effective_to'] !== null && $list['effective_to'] < $today);
    echo '<article class="card card-body">';
    echo '<h2>' . e((string) $list['name']) . '</h2>';
    echo '<p>' . e((string) $list['audience']) . ' · ' . e((string) $list['source_ref']) . ' · ' . (int) ($count['n'] ?? 0) . ' items · ' . (int) ($count['drafts'] ?? 0) . ' drafts';
    if ($expired) {
        echo ' · <strong>Effective dates have ended</strong>';
    }
    echo '</p>';
    echo '<p><a href="/admin/price-lists?export=' . $id . '">Export CSV</a></p>';
    echo '<form method="post" class="narrow">' . csrf_field() . '<input type="hidden" name="action" value="save_list"><input type="hidden" name="id" value="' . $id . '">';
    echo '<label>From <input name="effective_from" value="' . e((string) ($list['effective_from'] ?? '')) . '"></label>';
    echo '<label>To <input name="effective_to" value="' . e((string) ($list['effective_to'] ?? '')) . '"></label>';
    echo '<label>Notes <input name="notes" value="' . e((string) $list['notes']) . '"></label>';
    echo '<label><input type="checkbox" name="active" value="1"' . ((int) $list['active'] === 1 ? ' checked' : '') . '> List active</label>';
    echo '<button class="btn" type="submit">Save list</button></form>';
    $items = db()->prepare('SELECT * FROM price_list_items WHERE price_list_id = ? ORDER BY item_name, variety LIMIT 80');
    $items->execute([$id]);
    echo '<div class="table-wrap"><table><tr><th>Item</th><th>Variety</th><th>Paise</th><th>Tray</th><th></th></tr>';
    foreach ($items as $item) {
        $tray = tray_rupees((int) $item['price_per_seedling_paise'], seedlings_per_tray(null));
        echo '<tr><td>' . e((string) $item['item_name']) . '</td><td>' . e((string) $item['variety']) . '</td><td>' . (int) $item['price_per_seedling_paise'] . '</td><td>' . inr($tray) . '</td><td>';
        echo '<form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="action" value="toggle"><input type="hidden" name="item_id" value="' . (int) $item['id'] . '"><input type="hidden" name="active" value="' . ((int) $item['active'] === 1 ? '0' : '1') . '"><button class="text-link" type="submit">' . ((int) $item['active'] === 1 ? 'Deactivate' : 'Activate') . '</button></form>';
        echo '</td></tr>';
    }
    echo '</table></div>';
    echo '<form method="post" enctype="multipart/form-data">' . csrf_field() . '<input type="hidden" name="action" value="preview"><input type="hidden" name="list_id" value="' . $id . '"><label>CSV <input type="file" name="csv" accept=".csv,text/csv" required></label><button class="btn" type="submit">Preview import</button></form>';
    echo '</article>';
}
$preview = $_SESSION['price_preview'] ?? null;
if (is_array($preview)) {
    $summary = $preview['summary'];
    echo '<h2>Import preview</h2>';
    echo '<p>New ' . (int) $summary['new'] . ' · changed ' . (int) $summary['changed'] . ' · unchanged ' . (int) $summary['unchanged'] . ' · invalid ' . count($preview['invalid']) . '</p>';
    foreach ($preview['invalid'] as $line) {
        echo '<p class="flash bad">' . e((string) $line) . '</p>';
    }
    if (!$preview['invalid']) {
        echo '<form method="post">' . csrf_field() . '<input type="hidden" name="action" value="confirm"><input type="hidden" name="token" value="' . e((string) $preview['token']) . '"><button class="btn" type="submit">Confirm import</button></form>';
    }
}
admin_close();
