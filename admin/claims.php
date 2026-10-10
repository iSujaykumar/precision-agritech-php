<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $id = (int) ($_POST['id'] ?? 0);
        $status = (string) ($_POST['status'] ?? 'new');
        if (!in_array($status, ['new', 'replace', 'refund', 'closed'], true)) {
            throw new RuntimeException('Choose a status.');
        }
        $reply = clean_text((string) ($_POST['reply'] ?? ''), 500);
        db()->prepare('UPDATE claims SET status = ?, staff_reply = ? WHERE id = ?')->execute([$status, $reply, $id]);
        $claim = db()->prepare('SELECT c.*, o.customer_email, o.order_number FROM claims c JOIN orders o ON o.id = c.order_id WHERE c.id = ?');
        $claim->execute([$id]);
        $row = $claim->fetch();
        if ($row && $reply !== '') {
            mail_order_customer(['order_number' => $row['order_number'], 'customer_email' => $row['customer_email'], 'total_inr' => 0], $reply);
        }
        audit_log((int) $admin['id'], 'claim', 'claim', $id, null, $status);
        flash_set('Claim updated');
        header('Location: /admin/claims');
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'That claim could not be updated.');
    }
}
$rows = db()->query('SELECT c.*, o.order_number FROM claims c JOIN orders o ON o.id = c.order_id ORDER BY c.id DESC LIMIT 50')->fetchAll();
admin_open('Claims');
if (!empty($error)) {
    echo '<p class="flash bad">' . e($error) . '</p>';
}
foreach ($rows as $row) {
    echo '<article class="card card-body"><p><strong>' . e($row['order_number']) . '</strong> · ' . e($row['status']) . '</p><p>' . e($row['reason']) . '</p>';
    foreach (array_filter(explode('|', (string) $row['photos'])) as $photo) {
        echo '<p><a href="/admin/proof?file=' . rawurlencode($photo) . '">Photo</a></p>';
    }
    echo '<form method="post">' . csrf_field() . '<input type="hidden" name="id" value="' . (int) $row['id'] . '">';
    echo '<label>Status <select name="status">';
    foreach (['new' => 'New', 'replace' => 'Replace', 'refund' => 'Refund', 'closed' => 'Closed'] as $key => $label) {
        echo '<option value="' . e($key) . '"' . ($row['status'] === $key ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    echo '</select></label><label>Reply <textarea name="reply">' . e((string) $row['staff_reply']) . '</textarea></label><button class="btn" type="submit">Save</button></form></article>';
}
if (!$rows) {
    echo '<p>No reports yet.</p>';
}
admin_close();
