<?php
/**
 * User Management write handler -- add, edit, delete, reset password,
 * activate/deactivate. Admin only, CSRF protected, everything logged.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('users/index.php');
}
verify_csrf('users/index.php');

$db     = getDB();
$action = $_POST['action'] ?? '';
$id     = (int) ($_POST['id'] ?? 0);
$selfId = (int) $_SESSION['user_id'];

/* Load the target row up-front for the actions that need it. */
$target = null;
if ($id > 0) {
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $target = $stmt->fetch();
    if (!$target) {
        set_flash('danger', 'That user account no longer exists.');
        redirect('users/index.php');
    }
}

switch ($action) {

    /* ---------------- Add / Edit ---------------- */
    case 'save':
        $name     = trim($_POST['name'] ?? '');
        $username = strtolower(trim($_POST['username'] ?? ''));
        $email    = trim($_POST['email'] ?? '');
        $role     = ($_POST['role'] ?? 'staff') === 'admin' ? 'admin' : 'staff';
        $password = $_POST['password'] ?? '';

        $errors = [];
        if ($name === '')     { $errors[] = 'Full name is required.'; }
        if ($username === '') { $errors[] = 'Username is required.'; }
        if (!preg_match('/^[a-z0-9._-]{3,50}$/', $username)) {
            $errors[] = 'Username may only contain lowercase letters, numbers, dots, hyphens and underscores (3–50 characters).';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'That email address does not look valid.';
        }
        if ($id === 0 && strlen($password) < 6) {
            $errors[] = 'A password of at least 6 characters is required for a new user.';
        }
        if ($id > 0 && $password !== '' && strlen($password) < 6) {
            $errors[] = 'The new password must be at least 6 characters.';
        }

        // Username must stay unique
        $stmt = $db->prepare('SELECT id FROM users WHERE username = ? AND id <> ?');
        $stmt->execute([$username, $id]);
        if ($stmt->fetch()) {
            $errors[] = 'That username is already taken.';
        }

        // Never let the last active admin be demoted -- it would lock everyone out.
        if ($id > 0 && $target['role'] === 'admin' && $role !== 'admin') {
            $admins = (int) $db->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn();
            if ($admins <= 1) {
                $errors[] = 'This is the only active administrator — change another user to Admin first.';
            }
        }

        if ($errors) {
            set_flash('danger', implode(' ', $errors));
            redirect('users/index.php');
        }

        if ($id > 0) {
            /* ---- update ---- */
            $changes = [
                'name'     => ['old' => $target['name'],     'new' => $name],
                'username' => ['old' => $target['username'], 'new' => $username],
                'email'    => ['old' => $target['email'],    'new' => $email],
                'role'     => ['old' => $target['role'],     'new' => $role],
            ];

            if ($password !== '') {
                $stmt = $db->prepare('UPDATE users SET name=?, username=?, email=?, role=?, password=? WHERE id=?');
                $stmt->execute([$name, $username, $email ?: null, $role, password_hash($password, PASSWORD_DEFAULT), $id]);
                $changes['password'] = ['old' => '********', 'new' => '******** (changed)'];
            } else {
                $stmt = $db->prepare('UPDATE users SET name=?, username=?, email=?, role=? WHERE id=?');
                $stmt->execute([$name, $username, $email ?: null, $role, $id]);
            }

            log_activity('User Management', 'Updated the user account "' . $name . '"');
            log_audit('User Management', 'UPDATE', 'User', $id, $changes);
            notify('user', 'User account updated', $name . ' was updated by ' . current_user()['name'], 'users/index.php');
            set_flash('success', 'User "' . $name . '" has been updated.');

        } else {
            /* ---- insert ---- */
            $stmt = $db->prepare(
                'INSERT INTO users (name, username, email, password, role, is_active) VALUES (?,?,?,?,?,1)'
            );
            $stmt->execute([$name, $username, $email ?: null, password_hash($password, PASSWORD_DEFAULT), $role]);
            $newId = (int) $db->lastInsertId();

            log_activity('User Management', 'Created a new ' . $role . ' account for "' . $name . '"');
            log_audit('User Management', 'CREATE', 'User', $newId, [
                'name'     => ['old' => null, 'new' => $name],
                'username' => ['old' => null, 'new' => $username],
                'role'     => ['old' => null, 'new' => $role],
            ]);
            notify('user', 'New user created', $name . ' (' . ucfirst($role) . ') was added to SMART STOCK', 'users/index.php');
            set_flash('success', 'User "' . $name . '" has been created and can now log in.');
        }
        break;

    /* ---------------- Reset password ---------------- */
    case 'reset_password':
        $password = $_POST['password'] ?? '';
        if (strlen($password) < 6) {
            set_flash('danger', 'The new password must be at least 6 characters.');
            redirect('users/index.php');
        }
        $stmt = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id]);

        log_activity('User Management', 'Reset the password for "' . $target['name'] . '"');
        log_audit('User Management', 'RESET_PASSWORD', 'User', $id, [
            'password' => ['old' => '********', 'new' => '******** (reset)'],
        ]);
        set_flash('success', 'Password for "' . $target['name'] . '" has been reset.');
        break;

    /* ---------------- Activate / Deactivate ---------------- */
    case 'toggle':
        if ($id === $selfId) {
            set_flash('danger', 'You cannot deactivate your own account.');
            redirect('users/index.php');
        }
        $newState = (int) $target['is_active'] === 1 ? 0 : 1;

        // Don't allow deactivating the last active admin.
        if ($newState === 0 && $target['role'] === 'admin') {
            $admins = (int) $db->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn();
            if ($admins <= 1) {
                set_flash('danger', 'This is the only active administrator — you cannot deactivate it.');
                redirect('users/index.php');
            }
        }

        $stmt = $db->prepare('UPDATE users SET is_active = ? WHERE id = ?');
        $stmt->execute([$newState, $id]);

        $word = $newState === 1 ? 'Activated' : 'Deactivated';
        log_activity('User Management', $word . ' the account "' . $target['name'] . '"');
        log_audit('User Management', 'UPDATE', 'User', $id, [
            'is_active' => ['old' => $target['is_active'] ? 'Active' : 'Deactivated',
                            'new' => $newState ? 'Active' : 'Deactivated'],
        ]);
        set_flash('success', $word . ' the account for "' . $target['name'] . '".');
        break;

    /* ---------------- Delete ---------------- */
    case 'delete':
        if ($id === $selfId) {
            set_flash('danger', 'You cannot delete your own account.');
            redirect('users/index.php');
        }
        if ($target['role'] === 'admin') {
            $admins = (int) $db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
            if ($admins <= 1) {
                set_flash('danger', 'This is the only administrator account — it cannot be deleted.');
                redirect('users/index.php');
            }
        }

        /* Transactions keep their history: the FKs on stock_in/stock_out use
           ON DELETE SET NULL, so records survive with an unlinked user. */
        $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);

        log_activity('User Management', 'Deleted the user account "' . $target['name'] . '"');
        log_audit('User Management', 'DELETE', 'User', $id, [
            'name' => ['old' => $target['name'], 'new' => null],
        ]);
        set_flash('success', 'The account for "' . $target['name'] . '" has been deleted.');
        break;

    default:
        set_flash('danger', 'Unknown action.');
}

redirect('users/index.php');
