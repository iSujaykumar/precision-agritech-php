<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
require_desk($admin, 'manager');
$error = null;
$preview = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'undo') {
            $raw = json_decode(setting('last_bulk_offer', ''), true);
            if (!is_array($raw) || !$raw) {
                throw new RuntimeException('There is no bulk offer to undo.');
            }
            $upd = db()->prepare('UPDATE products SET on_offer = ?, compare_at_inr = ?, offer_starts_at = ?, offer_ends_at = ?, offer_seedling_paise = ? WHERE id = ?');
            foreach ($raw as $row) {
                $upd->execute([
                    (int) ($row['on_offer'] ?? 0),
                    $row['compare_at_inr'] === null || $row['compare_at_inr'] === '' ? null : (int) $row['compare_at_inr'],
                    ($row['offer_starts_at'] ?? '') !== '' ? $row['offer_starts_at'] : null,
                    ($row['offer_ends_at'] ?? '') !== '' ? $row['offer_ends_at'] : null,
                    $row['offer_seedling_paise'] === null || $row['offer_seedling_paise'] === '' ? null : (int) $row['offer_seedling_paise'],
                    (int) $row['id'],
                ]);
                audit_log((int) $admin['id'], 'offer_undo', 'product', (int) $row['id'], null, 'restored');
            }
            save_setting('last_bulk_offer', '');
            flash_set('The last bulk offer was undone.');
            header('Location: /admin/offers');
            exit;
        }
        $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
        if ($action === 'end' && !$ids) {
            $ids = array_map('intval', db()->query('SELECT id FROM products WHERE offer_seedling_paise IS NOT NULL AND on_offer = 1')->fetchAll(PDO::FETCH_COLUMN));
            if (!$ids) {
                throw new RuntimeException('There is no live price offer to end.');
            }
        }
        if (!$ids) {
            throw new RuntimeException('Choose at least one plant.');
        }
        $start = trim((string) ($_POST['offer_starts_at'] ?? ''));
        $end = trim((string) ($_POST['offer_ends_at'] ?? ''));
        $startAt = $start === '' ? null : offer_moment($start);
        $endAt = $end === '' ? null : offer_moment($end);
        if (($start !== '' && $startAt === null) || ($end !== '' && $endAt === null)) {
            throw new RuntimeException('Use dates like 2026-11-01 09:00.');
        }
        if ($startAt && $endAt && $endAt < $startAt) {
            throw new RuntimeException('The offer end must be on or after the start.');
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = db()->prepare('SELECT * FROM products WHERE id IN (' . $placeholders . ')');
        $rows->execute($ids);
        $products = $rows->fetchAll();
        if (count($products) !== count($ids)) {
            throw new RuntimeException('One of those plants was not found.');
        }
        if ($action === 'compare') {
            $compare = (int) ($_POST['compare_at_inr'] ?? 0);
            foreach ($products as $product) {
                if ($compare <= (int) $product['price_inr']) {
                    throw new RuntimeException($product['name'] . ' needs an old price higher than ' . (int) $product['price_inr'] . '.');
                }
            }
        }
        if ($action === 'clear' || $action === 'end') {
            $upd = db()->prepare('UPDATE products SET on_offer = 0, compare_at_inr = NULL, offer_starts_at = NULL, offer_ends_at = NULL, offer_seedling_paise = NULL WHERE id = ?');
            foreach ($ids as $id) {
                $upd->execute([$id]);
                audit_log((int) $admin['id'], $action === 'end' ? 'offer_end' : 'offer_clear', 'product', $id, null, 'cleared');
            }
            flash_set($action === 'end' ? 'The offer has ended.' : 'Offers cleared.');
        } elseif ($action === 'badge' || $action === 'compare') {
            $compare = (int) ($_POST['compare_at_inr'] ?? 0);
            if ($action === 'badge') {
                $upd = db()->prepare('UPDATE products SET on_offer = 1, offer_starts_at = ?, offer_ends_at = ? WHERE id = ?');
            } else {
                $upd = db()->prepare('UPDATE products SET on_offer = 1, compare_at_inr = ?, offer_starts_at = ?, offer_ends_at = ? WHERE id = ?');
            }
            foreach ($products as $product) {
                if ($action === 'compare') {
                    $upd->execute([$compare, $startAt?->format('Y-m-d H:i:s'), $endAt?->format('Y-m-d H:i:s'), (int) $product['id']]);
                } else {
                    $upd->execute([$startAt?->format('Y-m-d H:i:s'), $endAt?->format('Y-m-d H:i:s'), (int) $product['id']]);
                }
                audit_log((int) $admin['id'], 'offer_set', 'product', (int) $product['id'], null, $action);
            }
            flash_set('Offers saved.');
        } elseif ($action === 'preview' || $action === 'create') {
            $mode = (($_POST['offer_mode'] ?? '') === 'flat') ? 'flat' : 'percent';
            $amount = $mode === 'flat' ? rupees_input_to_paise((string) ($_POST['flat_rupees'] ?? '')) : (int) ($_POST['percent_off'] ?? 0);
            if ($mode === 'percent' && ($amount < 1 || $amount > 50)) {
                throw new RuntimeException('Percent off must be from 1 to 50.');
            }
            $snap = [];
            foreach ($products as $product) {
                $current = (int) ($product['price_per_seedling_paise'] ?? 0);
                if ($current < 1) {
                    throw new RuntimeException($product['name'] . ' has no per-seedling price.');
                }
                $next = offer_cut_paise($current, $mode, $amount);
                $tray = seedlings_per_tray($product);
                $preview[] = [
                    'name' => (string) $product['name'],
                    'old' => $current,
                    'new' => $next,
                    'old_tray' => exact_tray_paise($current, $tray),
                    'new_tray' => exact_tray_paise($next, $tray),
                    'clamped' => $next === nursery_min_seedling_paise() && $next !== ($mode === 'flat' ? $amount : intdiv($current * (100 - max(1, min(50, $amount))) + 50, 100)),
                ];
                $snap[] = [
                    'id' => (int) $product['id'],
                    'on_offer' => (int) $product['on_offer'],
                    'compare_at_inr' => $product['compare_at_inr'],
                    'offer_starts_at' => $product['offer_starts_at'],
                    'offer_ends_at' => $product['offer_ends_at'],
                    'offer_seedling_paise' => $product['offer_seedling_paise'],
                ];
            }
            if ($action === 'create') {
                $upd = db()->prepare('UPDATE products SET on_offer = 1, offer_seedling_paise = ?, offer_starts_at = ?, offer_ends_at = ? WHERE id = ?');
                foreach ($products as $i => $product) {
                    $upd->execute([$preview[$i]['new'], $startAt?->format('Y-m-d H:i:s'), $endAt?->format('Y-m-d H:i:s'), (int) $product['id']]);
                    audit_log((int) $admin['id'], 'offer_bulk', 'product', (int) $product['id'], (string) $preview[$i]['old'], (string) $preview[$i]['new']);
                }
                save_setting('last_bulk_offer', json_encode($snap, JSON_UNESCAPED_UNICODE));
                flash_set('Bulk offer saved.');
                header('Location: /admin/offers');
                exit;
            }
        } else {
            throw new RuntimeException('That offer change is not available.');
        }
        if ($action !== 'preview') {
            header('Location: /admin/offers');
            exit;
        }
    } catch (Throwable $err) {
        $error = safe_error($err, 'Those offers could not be saved.');
    }
}
$liveOnly = (string) ($_GET['live'] ?? '') === '1';
if ($liveOnly) {
    $rows = live_offer_rows();
} else {
    $rows = db()->query('SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id WHERE p.parent_product_id IS NULL AND p.farmer_only = 0 ORDER BY p.name')->fetchAll();
}
$now = new DateTimeImmutable('now', shop_timezone());
$rule = (int) setting('seasonal_discount_percent', '10') . '% bulk discount applies on these offer prices when it is turned on. It never goes below the nursery minimum of ' . seedling_rate_text(nursery_min_seedling_paise()) . ' per seedling. Packing and transport are not discounted.';
if (setting('offer_allows_bulk', '1') !== '1') {
    $rule = 'Bulk discount is set to skip plants that already have an offer price. Packing and transport are not discounted.';
}
admin_open('Offers', 'offers');
if ($error) {
    echo '<p class="flash bad" role="alert">' . e($error) . '</p>';
}
echo '<p>' . e($rule) . ' Change that under Settings, Prices and discount.</p>';
echo '<h2>Bulk offer</h2>';
echo '<form method="post" class="narrow">' . csrf_field();
echo '<label>Percent off (1 to 50) <input name="percent_off" type="number" min="1" max="50" value="' . e((string) ($_POST['percent_off'] ?? '10')) . '"></label>';
echo '<label>Or a flat price per seedling, in rupees <input name="flat_rupees" inputmode="decimal" placeholder="3.50" value="' . e((string) ($_POST['flat_rupees'] ?? '')) . '"></label>';
echo '<label>Use <select name="offer_mode"><option value="percent">Percent off</option><option value="flat"' . ((($_POST['offer_mode'] ?? '') === 'flat') ? ' selected' : '') . '>Flat price</option></select></label>';
echo '<label>Start <input name="offer_starts_at" placeholder="2026-11-01 09:00" value="' . e((string) ($_POST['offer_starts_at'] ?? '')) . '"></label>';
echo '<label>End <input name="offer_ends_at" placeholder="2026-11-30 18:00" value="' . e((string) ($_POST['offer_ends_at'] ?? '')) . '"></label>';
echo '<p class="btn-row"><button class="btn" name="action" value="preview" type="submit">Preview</button> <button class="btn" name="action" value="create" type="submit">Create</button> <button class="btn" name="action" value="end" type="submit">End this offer now</button> <button class="btn" name="action" value="undo" type="submit">Undo last bulk offer</button></p>';
if ($preview) {
    echo '<div class="table-wrap"><table><tr><th>Plant</th><th>Per seedling</th><th>Per tray</th></tr>';
    foreach ($preview as $line) {
        echo '<tr><td>' . e($line['name']) . ($line['clamped'] ? ' <span class="muted">held at the nursery minimum</span>' : '') . '</td><td>' . e(seedling_rate_text($line['old'])) . ' → ' . e(seedling_rate_text($line['new'])) . '</td><td>' . e(inr_paise($line['old_tray'])) . ' → ' . e(inr_paise($line['new_tray'])) . '</td></tr>';
    }
    echo '</table></div><p class="muted">Check this preview, then press Create. Nothing is saved until then.</p>';
}
echo '<div class="table-wrap"><table><tr><th></th><th>Plant</th></tr>';
foreach ($rows as $row) {
    if ((int) ($row['list_only'] ?? 0) === 1) {
        continue;
    }
    $checked = in_array((string) $row['id'], array_map('strval', (array) ($_POST['ids'] ?? [])), true) ? ' checked' : '';
    echo '<tr><td><input type="checkbox" name="ids[]" value="' . (int) $row['id'] . '"' . $checked . '></td><td>' . e($row['name']) . '</td></tr>';
}
echo '</table></div></form>';
echo '<h2>Single offers</h2>';
echo '<form method="post">' . csrf_field();
echo '<label>Start <input name="offer_starts_at" placeholder="2026-11-01 09:00"></label>';
echo '<label>End <input name="offer_ends_at" placeholder="2026-11-30 18:00"></label>';
echo '<label>Old price, only for a sale <input name="compare_at_inr" type="number" min="1"></label>';
echo '<p><button class="btn" name="action" value="badge" type="submit">Offer badge only</button> ';
echo '<button class="btn" name="action" value="compare" type="submit">Set old price</button> ';
echo '<button class="btn" name="action" value="clear" type="submit" data-confirm="Clear the offer on the selected plants?">Hide offer</button></p>';
echo '<p><a href="/admin/offers">All plants</a> · <a href="/admin/offers?live=1">Live offers only</a></p>';
echo '<div class="table-wrap"><table><tr><th></th><th>Plant</th><th>Price</th><th>State</th></tr>';
foreach ($rows as $row) {
    $state = 'Not an offer';
    if (offer_is_listed($row, $now)) {
        $state = 'Live · ' . offer_badge($row, $now);
    } else {
        $start = offer_moment((string) ($row['offer_starts_at'] ?? ''));
        $flagged = (int) $row['on_offer'] === 1 || $row['compare_at_inr'] !== null;
        if ($flagged && $start !== null && $now < $start) {
            $state = 'Scheduled';
        } elseif ($flagged && !offer_window_open($row, $now)) {
            $state = 'Expired';
        }
    }
    $sell = selling_price($row);
    echo '<tr><td><input type="checkbox" name="ids[]" value="' . (int) $row['id'] . '"></td><td>' . e($row['name']) . '</td><td>' . e(price_pair_text($sell)) . '</td><td>' . e($state) . '</td></tr>';
}
echo '</table></div></form>';
admin_close();
