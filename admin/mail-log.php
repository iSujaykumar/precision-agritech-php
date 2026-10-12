<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
$failed = (string) ($_GET['failed'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $id = (int) ($_POST['id'] ?? 0);
        $row = db()->prepare('SELECT to_email, subject FROM mail_log WHERE id = ?');
        $row->execute([$id]);
        $mail = $row->fetch();
        if (!$mail) {
            throw new RuntimeException('That message was not found.');
        }
        $ok = send_mail((string) $mail['to_email'], 'Resent: ' . (string) $mail['subject'], 'This is a resend of a shop message. If you still need the order details, open your order link or reply to this email.');
        audit_log((int) $admin['id'], 'mail_resend', 'mail', $id, null, $ok ? 'sent' : 'failed');
        flash_set($ok ? 'Email resent' : 'The email could not be sent', $ok);
        header('Location: /admin/mail-log');
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'That email could not be sent.');
    }
}
$where = $failed === '1' ? ' WHERE ok = 0' : '';
$rows = db()->query('SELECT * FROM mail_log' . $where . ' ORDER BY id DESC LIMIT 200')->fetchAll();
admin_open('Email log', 'settings');
if (!empty($error)) {
    echo '<p class="flash bad" role="alert">' . e($error) . '</p>';
}
echo '<p><a href="/admin/mail-log">All</a> · <a href="/admin/mail-log?failed=1">Failed</a></p>';
echo '<div class="table-wrap"><table><tr><th>When</th><th>To</th><th>Subject</th><th>Result</th><th></th></tr>';
foreach ($rows as $row) {
    echo '<tr><td>' . e((string) $row['created_at']) . '</td><td>' . e((string) $row['to_email']) . '</td><td>' . e((string) $row['subject']) . '</td><td>' . ((int) $row['ok'] === 1 ? 'Sent' : 'Failed') . '</td><td>';
    echo '<form method="post">' . csrf_field() . '<input type="hidden" name="id" value="' . (int) $row['id'] . '"><button class="btn" type="submit">Resend</button></form>';
    echo '</td></tr>';
}
echo '</table></div>';
admin_close();
