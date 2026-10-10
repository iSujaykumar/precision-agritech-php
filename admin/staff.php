<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$admin = admin_boot();
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'add') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $phone = normalize_phone((string) ($_POST['phone'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            if (strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
                throw new RuntimeException('Use a name, email, mobile and a password of at least 12 characters.');
            }
            db()->prepare('INSERT INTO users (name, email, phone, password_hash, role, status, must_change_password) VALUES (?, ?, ?, ?, ?, ?, 1)')
                ->execute([$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT), 'admin', 'active']);
            $newId = (int) db()->lastInsertId();
            audit_log((int) $admin['id'], 'staff_add', 'user', $newId, null, $email);
            flash_set('Staff account created. They must change the password at first sign-in.');
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            $target = db()->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'");
            $target->execute([$id]);
            $row = $target->fetch();
            if (!$row) {
                throw new RuntimeException('That staff account was not found.');
            }
            if ($action === 'disable' || $action === 'enable') {
                $others = db()->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active' AND id <> ?");
                $others->execute([$id]);
                $activeOthers = (int) $others->fetchColumn();
                if ($action === 'disable' && ($row['status'] ?? '') === 'active' && $activeOthers < 1) {
                    throw new RuntimeException('The last active staff account cannot be disabled.');
                }
                if ($id === (int) $admin['id'] && $action === 'disable') {
                    throw new RuntimeException('You cannot disable your own account.');
                }
                $status = $action === 'disable' ? 'disabled' : 'active';
                db()->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$status, $id]);
                audit_log((int) $admin['id'], 'staff_' . $action, 'user', $id, null, $status);
                flash_set($status === 'active' ? 'Staff account enabled' : 'Staff account disabled');
            } elseif ($action === 'reset') {
                $password = (string) ($_POST['password'] ?? '');
                if (strlen($password) < 12) {
                    throw new RuntimeException('Use a temporary password of at least 12 characters.');
                }
                db()->prepare('UPDATE users SET password_hash = ?, password_changed_at = NOW(), must_change_password = 1 WHERE id = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
                audit_log((int) $admin['id'], 'staff_reset', 'user', $id, null, 'reset');
                flash_set('Password reset. Share the temporary password with them. They must change it at the next sign-in.');
            } else {
                throw new RuntimeException('That action is not available.');
            }
        }
        header('Location: /admin/staff');
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'That staff change could not be saved.');
    }
}
$rows = db()->query("SELECT id, name, email, phone, status, last_login_at FROM users WHERE role = 'admin' ORDER BY id")->fetchAll();
admin_open('Staff', 'settings');
if ($error) {
    echo '<p class="flash bad" role="alert">' . e($error) . '</p>';
}
echo '<div class="table-wrap"><table><tr><th>Name</th><th>Email</th><th>Mobile</th><th>Status</th><th>Last sign-in</th><th></th></tr>';
foreach ($rows as $row) {
    $id = (int) $row['id'];
    echo '<tr><td>' . e($row['name']) . '</td><td>' . e($row['email']) . '</td><td>' . e((string) $row['phone']) . '</td><td>' . e((string) $row['status']) . '</td><td>' . e((string) ($row['last_login_at'] ?: '—')) . '</td><td>';
    if ($id !== (int) $admin['id']) {
        $verb = $row['status'] === 'disabled' ? 'enable' : 'disable';
        echo '<form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="action" value="' . $verb . '"><input type="hidden" name="id" value="' . $id . '"><button class="linkish" type="submit" data-confirm="Change this staff account?">' . ucfirst($verb) . '</button></form>';
    }
    echo '</td></tr>';
}
echo '</table></div>';
echo '<h2>Add staff</h2><form method="post" class="narrow">' . csrf_field() . '<input type="hidden" name="action" value="add">';
echo '<label>Name <input name="name" required></label><label>Email <input name="email" type="email" required></label><label>Mobile <input name="phone" required></label><label>Temporary password <input name="password" type="password" minlength="12" required></label><button class="btn" type="submit">Add staff</button></form>';
echo '<h2>Reset a password</h2><form method="post" class="narrow">' . csrf_field() . '<input type="hidden" name="action" value="reset">';
echo '<label>Staff <select name="id">';
foreach ($rows as $row) {
    echo '<option value="' . (int) $row['id'] . '">' . e($row['name']) . '</option>';
}
echo '</select></label><label>Temporary password <input name="password" type="password" minlength="12" required></label><button class="btn" type="submit" data-confirm="Reset this password?">Reset password</button></form>';
admin_close();
