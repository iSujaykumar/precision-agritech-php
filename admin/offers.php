<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
require_desk($admin, 'manager');
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
        if (!$ids) {
            throw new RuntimeException('Choose at least one tray.');
        }
        $action = (string) ($_POST['action'] ?? '');
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
        $rows = db()->prepare('SELECT id, name, price_inr FROM products WHERE id IN (' . $placeholders . ')');
        $rows->execute($ids);
        $products = $rows->fetchAll();
        if (count($products) !== count($ids)) {
            throw new RuntimeException('One of those trays was not found.');
        }
        if ($action === 'compare') {
            $compare = (int) ($_POST['compare_at_inr'] ?? 0);
            foreach ($products as $product) {
                if ($compare <= (int) $product['price_inr']) {
                    throw new RuntimeException($product['name'] . ' needs an old price higher than ' . (int) $product['price_inr'] . '.');
                }
            }
        }
        if ($action === 'clear') {
            $upd = db()->prepare('UPDATE products SET on_offer = 0, compare_at_inr = NULL, offer_starts_at = NULL, offer_ends_at = NULL WHERE id = ?');
            foreach ($ids as $id) {
                $upd->execute([$id]);
                audit_log((int) $admin['id'], 'offer_clear', 'product', $id, null, 'cleared');
            }
            flash_set('Offers cleared');
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
            flash_set('Offers saved');
        } else {
            throw new RuntimeException('That offer change is not available.');
        }
        header('Location: /admin/offers');
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'Those offers could not be saved.');
    }
}
$rows = db()->query('SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id ORDER BY p.name')->fetchAll();
$now = new DateTimeImmutable('now', shop_timezone());
admin_open('Offers', 'products');
if ($error) {
    echo '<p class="flash bad" role="alert">' . e($error) . '</p>';
}
echo '<form method="post">' . csrf_field();
echo '<label>Start <input name="offer_starts_at" placeholder="2026-11-01 09:00"></label>';
echo '<label>End <input name="offer_ends_at" placeholder="2026-11-30 18:00"></label>';
echo '<label>Old price, only for a sale <input name="compare_at_inr" type="number" min="1"></label>';
echo '<p><button class="btn" name="action" value="badge" type="submit">Offer badge only</button> ';
echo '<button class="btn" name="action" value="compare" type="submit">Set old price</button> ';
echo '<button class="btn" name="action" value="clear" type="submit" data-confirm="Clear the offer on the selected trays?">Clear offer</button></p>';
echo '<div class="table-wrap"><table><tr><th></th><th>Tray</th><th>Price</th><th>State</th></tr>';
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
    echo '<tr><td><input type="checkbox" name="ids[]" value="' . (int) $row['id'] . '"></td><td>' . e($row['name']) . '</td><td>' . inr((int) $row['price_inr']) . '</td><td>' . e($state) . '</td></tr>';
}
echo '</table></div></form>';
admin_close();
