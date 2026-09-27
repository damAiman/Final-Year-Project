<?php
require_once __DIR__ . '/config/bootstrap.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('settings.php');
}
verify_csrf('settings.php');

$section = $_POST['section'] ?? '';
$before  = all_settings();
$changes = [];

/** Save a setting and remember the change for the audit log */
function put(string $key, string $value, array $before, array &$changes): void
{
    if (($before[$key] ?? '') !== $value) {
        $changes[$key] = ['old' => $before[$key] ?? '', 'new' => $value];
    }
    set_setting($key, $value);
}

switch ($section) {

    case 'company':
        $name = trim($_POST['company_name'] ?? '');
        if ($name === '') {
            set_flash('danger', 'Company name is required.');
            redirect('settings.php');
        }
        $email = trim($_POST['company_email'] ?? '');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('danger', 'That company email address does not look valid.');
            redirect('settings.php');
        }

        put('company_name',    $name,                                  $before, $changes);
        put('company_reg_no',  trim($_POST['company_reg_no'] ?? ''),    $before, $changes);
        put('company_address', trim($_POST['company_address'] ?? ''),   $before, $changes);
        put('company_phone',   trim($_POST['company_phone'] ?? ''),     $before, $changes);
        put('company_email',   $email,                                  $before, $changes);

        /* ---- logo upload ---- */
        if (!empty($_POST['remove_logo'])) {
            $old = $before['company_logo'] ?? '';
            if ($old !== '' && file_exists(__DIR__ . '/uploads/' . $old)) {
                @unlink(__DIR__ . '/uploads/' . $old);
            }
            put('company_logo', '', $before, $changes);
        } elseif (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $tmp  = $_FILES['logo']['tmp_name'];
            $size = (int) $_FILES['logo']['size'];

            if ($size > 2 * 1024 * 1024) {
                set_flash('danger', 'The logo must be 2MB or smaller.');
                redirect('settings.php');
            }

            // Verify by real content, not just the extension.
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $tmp);
            finfo_close($finfo);

            $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg'];
            if (!isset($allowed[$mime])) {
                set_flash('danger', 'The logo must be a PNG or JPG image.');
                redirect('settings.php');
            }

            $filename = 'logo_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
            if (!move_uploaded_file($tmp, __DIR__ . '/uploads/' . $filename)) {
                set_flash('danger', 'The logo could not be saved. Check the uploads folder is writable.');
                redirect('settings.php');
            }

            $old = $before['company_logo'] ?? '';
            if ($old !== '' && file_exists(__DIR__ . '/uploads/' . $old)) {
                @unlink(__DIR__ . '/uploads/' . $old);
            }
            put('company_logo', $filename, $before, $changes);
        }

        log_activity('Settings', 'Updated the company profile');
        set_flash('success', 'Company profile has been saved.');
        break;

    case 'notifications':
        foreach (['notify_low_stock', 'notify_stock_in', 'notify_stock_out', 'notify_reports', 'notify_users'] as $key) {
            put($key, isset($_POST[$key]) ? '1' : '0', $before, $changes);
        }
        log_activity('Settings', 'Updated notification settings');
        set_flash('success', 'Notification settings have been saved.');
        break;

    case 'appearance':
        put('dark_mode', isset($_POST['dark_mode']) ? '1' : '0', $before, $changes);
        $lang = ($_POST['language'] ?? 'en') === 'ms' ? 'ms' : 'en';
        put('language', $lang, $before, $changes);
        log_activity('Settings', 'Updated appearance and language settings');
        set_flash('success', 'Appearance settings have been saved.');
        break;

    case 'security':
        $timeout = (int) ($_POST['session_timeout_min'] ?? 30);
        $timeout = max(0, min(480, $timeout));
        put('session_timeout_min', (string) $timeout, $before, $changes);
        log_activity('Settings', 'Updated security settings (session timeout: ' . $timeout . ' min)');
        set_flash('success', 'Security settings have been saved.');
        break;

    default:
        set_flash('danger', 'Unknown settings section.');
        redirect('settings.php');
}

if ($changes) {
    log_audit('Settings', 'UPDATE', 'Setting', null, $changes);
}

redirect('settings.php');
