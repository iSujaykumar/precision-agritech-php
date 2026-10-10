<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $id = (int) ($_POST['id'] ?? 0);
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'delete') {
            db()->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
            audit_log((int) $admin['id'], 'review_delete', 'review', $id, null, 'deleted');
            flash_set('Review deleted');
        } elseif ($action === 'reply') {
            $reply = clean_review_body((string) ($_POST['reply'] ?? ''));
            db()->prepare('UPDATE reviews SET reply = ? WHERE id = ?')->execute([$reply === '' ? null : substr($reply, 0, 600), $id]);
            audit_log((int) $admin['id'], 'review_reply', 'review', $id, null, 'reply');
            flash_set('Reply saved');
        } else {
            $status = in_array($_POST['status'] ?? '', ['approved', 'rejected'], true) ? (string) $_POST['status'] : '';
            if ($status === '') {
                throw new RuntimeException('Choose approve or reject.');
            }
            db()->prepare('UPDATE reviews SET status = ? WHERE id = ?')->execute([$status, $id]);
            audit_log((int) $admin['id'], 'review', 'review', $id, null, $status);
            flash_set($status === 'approved' ? 'Review approved' : 'Review rejected');
        }
        header('Location: /admin/reviews');
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'That review could not be updated.');
    }
}
$filter = (string) ($_GET['status'] ?? '');
$where = '';
$params = [];
if (in_array($filter, ['pending', 'approved', 'rejected'], true)) {
    $where = ' WHERE r.status = ?';
    $params[] = $filter;
}
$count = db()->prepare('SELECT COUNT(*) FROM reviews r' . $where);
$count->execute($params);
[$page, $pages, $offset] = list_bounds((int) $count->fetchColumn(), 25);
$sql = 'SELECT r.*, p.name AS product_name, u.name AS customer_name FROM reviews r LEFT JOIN products p ON p.id = r.product_id LEFT JOIN users u ON u.id = r.user_id' . $where . " ORDER BY (r.status = 'pending') DESC, r.id DESC LIMIT 25 OFFSET " . (int) $offset;
$stmt = db()->prepare($sql);
$stmt->execute($params);
admin_open('Reviews');
if ($error) {
    echo '<p class="flash bad" role="alert">' . e($error) . '</p>';
}
echo '<nav class="subnav"><a href="/admin/reviews">All</a><a href="/admin/reviews?status=pending">Pending</a><a href="/admin/reviews?status=approved">Approved</a><a href="/admin/reviews?status=rejected">Rejected</a></nav>';
echo '<div class="table-wrap"><table><tr><th>When</th><th>Product</th><th>Customer</th><th>Rating</th><th>Review</th><th>Status</th><th></th></tr>';
foreach ($stmt as $row) {
    echo '<tr><td>' . e((string) $row['created_at']) . '</td><td>' . e((string) ($row['product_name'] ?: 'The nursery')) . '</td><td>' . e((string) ($row['customer_name'] ?: $row['author_name'])) . '</td><td>' . (int) $row['rating'] . '</td><td>' . e($row['body']);
    if (!empty($row['reply'])) {
        echo '<br><span class="muted">Reply: ' . e((string) $row['reply']) . '</span>';
    }
    echo '</td><td>' . e((string) $row['status']) . '</td><td>';
    echo '<form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="id" value="' . (int) $row['id'] . '"><button name="status" value="approved">Approve</button> <button name="status" value="rejected">Reject</button></form> ';
    echo '<form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . (int) $row['id'] . '"><button type="submit" data-confirm="Delete this review?">Delete</button></form>';
    echo '<form method="post">' . csrf_field() . '<input type="hidden" name="action" value="reply"><input type="hidden" name="id" value="' . (int) $row['id'] . '"><label>Reply as nursery <input name="reply" value="' . e((string) ($row['reply'] ?? '')) . '"></label><button class="btn" type="submit">Save reply</button></form>';
    echo '</td></tr>';
}
echo '</table></div>';
pager_nav($page, $pages);
admin_close();
