<?php

require_once __DIR__ . '/config/bootstrap.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('settings.php');
}
verify_csrf('settings.php');

if (empty($_POST['confirm'])) {
    set_flash('danger', 'Please tick the confirmation box before restoring.');
    redirect('settings.php');
}

if (empty($_FILES['backup_file']['name']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
    set_flash('danger', 'No backup file was uploaded, or the upload failed.');
    redirect('settings.php');
}

$tmp  = $_FILES['backup_file']['tmp_name'];
$name = $_FILES['backup_file']['name'];
$size = (int) $_FILES['backup_file']['size'];

if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'sql') {
    set_flash('danger', 'The backup must be a .sql file.');
    redirect('settings.php');
}
if ($size > 32 * 1024 * 1024) {
    set_flash('danger', 'That backup file is larger than the 32MB limit.');
    redirect('settings.php');
}

$sql = file_get_contents($tmp);
if ($sql === false || trim($sql) === '') {
    set_flash('danger', 'The backup file could not be read or is empty.');
    redirect('settings.php');
}

/* Sanity check: does this actually look like a SMART STOCK backup? */
if (stripos($sql, 'CREATE TABLE') === false || stripos($sql, 'products') === false) {
    set_flash('danger', 'That file does not look like a SMART STOCK database backup.');
    redirect('settings.php');
}

/* Remember who is restoring, so we can restore their session afterwards --
   the users table is about to be replaced underneath us. */
$currentUsername = $_SESSION['username'] ?? '';

$db = getDB();

/**
 * Split a dump into individual statements, respecting quoted strings so that
 * a semicolon inside a value (e.g. an address) doesn't split a statement.
 */
function split_sql_statements(string $sql): array
{
    $statements = [];
    $buffer     = '';
    $inString   = false;
    $stringChar = '';
    $len        = strlen($sql);

    for ($i = 0; $i < $len; $i++) {
        $char = $sql[$i];
        $prev = $i > 0 ? $sql[$i - 1] : '';

        // Skip full-line comments when not inside a string
        if (!$inString && $char === '-' && substr($sql, $i, 2) === '--' && ($buffer === '' || substr($buffer, -1) === "\n")) {
            $eol = strpos($sql, "\n", $i);
            if ($eol === false) { break; }
            $i = $eol;
            continue;
        }

        if ($inString) {
            if ($char === $stringChar && $prev !== '\\') {
                $inString = false;
            }
        } elseif ($char === "'" || $char === '"') {
            $inString   = true;
            $stringChar = $char;
        } elseif ($char === ';') {
            $trimmed = trim($buffer);
            if ($trimmed !== '') {
                $statements[] = $trimmed;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $trimmed = trim($buffer);
    if ($trimmed !== '') {
        $statements[] = $trimmed;
    }
    return $statements;
}

$statements = split_sql_statements($sql);
$executed = 0;
$failed   = 0;

try {
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');

    foreach ($statements as $stmt) {
        if ($stmt === '') {
            continue;
        }
        try {
            $db->exec($stmt);
            $executed++;
        } catch (PDOException $e) {
            $failed++;
            // Keep going: a single failed statement shouldn't abort an otherwise
            // valid restore, but we report the count back to the user.
        }
    }

    $db->exec('SET FOREIGN_KEY_CHECKS = 1');

} catch (Exception $e) {
    @$db->exec('SET FOREIGN_KEY_CHECKS = 1');
    set_flash('danger', 'The restore failed and was stopped. Your database may be in a partial state — restore a known-good backup via phpMyAdmin.');
    redirect('settings.php');
}

/* The users table was just replaced. Re-attach the session to the same username
   if it still exists in the restored data; otherwise force a fresh login. */
$stillExists = false;
if ($currentUsername !== '') {
    try {
        $stmt = $db->prepare('SELECT id, name, username, role, is_active FROM users WHERE username = ?');
        $stmt->execute([$currentUsername]);
        if ($row = $stmt->fetch()) {
            if ((int) ($row['is_active'] ?? 1) === 1) {
                $_SESSION['user_id']  = $row['id'];
                $_SESSION['name']     = $row['name'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['role']     = $row['role'];
                $stillExists = true;
            }
        }
    } catch (Exception $e) {
        // fall through to logout
    }
}

if (!$stillExists) {
    $_SESSION = [];
    session_destroy();
    session_start();
    set_flash('warning', 'Database restored. Your account was not present in that backup, so please log in again.');
    redirect('auth/login.php');
}

log_activity('Settings', 'Restored the database from backup file "' . $name . '"');
log_audit('Settings', 'RESTORE', 'Database', null, [
    'backup_file' => ['old' => null, 'new' => $name],
]);

if ($failed > 0) {
    set_flash('warning', 'Database restored with warnings: ' . $executed . ' statement(s) applied, ' . $failed . ' skipped. Please review your data.');
} else {
    set_flash('success', 'Database restored successfully from "' . $name . '" (' . $executed . ' statements applied).');
}

redirect('settings.php');
