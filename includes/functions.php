<?php
/**
 * SMART STOCK - shared helper functions
 */

/** Turn a project-relative path into a full site URL, e.g. base_url('products/index.php') */
function base_url(string $path = ''): string
{
    return BASE_URL . ltrim($path, '/');
}

/** Shorthand for htmlspecialchars() used throughout the views */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Redirect helper */
function redirect(string $path): void
{
    header('Location: ' . base_url($path));
    exit;
}

/** Require an authenticated session, otherwise send the visitor to the login page */
function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        redirect('auth/login.php');
    }
}

/** Require a specific role (e.g. 'admin'). Staff attempting an admin-only action are bounced back with a message. */
function require_role(string $role): void
{
    require_login();
    if (($_SESSION['role'] ?? '') !== $role) {
        set_flash('danger', 'You do not have permission to perform that action.');
        redirect('dashboard.php');
    }
}

/** Currently logged-in user (or null) */
function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id'       => $_SESSION['user_id'],
        'name'     => $_SESSION['name'] ?? '',
        'username' => $_SESSION['username'] ?? '',
        'role'     => $_SESSION['role'] ?? 'staff',
    ];
}

/** Store a one-time flash message (shown on the next page load, then cleared) */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** Fetch + clear the flash message, if any. Returns null when there isn't one. */
function get_flash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/** Format a number with thousand separators, e.g. 18400 -> "18,400" */
function fmt_num($number): string
{
    return number_format((float) $number, 0);
}

/**
 * Work out a product's stock status from its current vs minimum stock.
 * Returns an array with a label and a Bootstrap-friendly CSS class suffix.
 */
function stock_status(int $current, int $min): array
{
    if ($current <= 0) {
        return ['key' => 'out', 'label' => 'Out of Stock', 'class' => 'out'];
    }
    if ($current < $min) {
        return ['key' => 'critical', 'label' => 'Critical', 'class' => 'critical'];
    }
    if ($current < $min * 1.5) {
        return ['key' => 'low', 'label' => 'Low Stock', 'class' => 'low'];
    }
    return ['key' => 'instock', 'label' => 'In Stock', 'class' => 'instock'];
}

/** Human friendly "time ago" string for a MySQL datetime */
function time_ago(string $datetime): string
{
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        $m = floor($diff / 60);
        return $m . ' minute' . ($m > 1 ? 's' : '') . ' ago';
    }
    if ($diff < 86400) {
        $h = floor($diff / 3600);
        return $h . ' hour' . ($h > 1 ? 's' : '') . ' ago';
    }
    if ($diff < 86400 * 7) {
        $d = floor($diff / 86400);
        return $d . ' day' . ($d > 1 ? 's' : '') . ' ago';
    }
    return date('d M Y', $timestamp);
}

/** Resolve the public URL of a product's image, falling back to a generated placeholder */
function product_image_url(?string $filename, string $category = ''): string
{
    if (!empty($filename) && file_exists(__DIR__ . '/../uploads/products/' . $filename)) {
        return base_url('uploads/products/' . rawurlencode($filename));
    }
    return base_url('assets/img/placeholder-product.svg');
}

/** Build a short, readable, unique SKU from a product name, e.g. "PET Bottle 500ml" -> "PET-BOT-500" */
function generate_sku(PDO $db, string $name): string
{
    $letters = '';
    foreach (preg_split('/\s+/', trim($name)) as $word) {
        $word = preg_replace('/[^A-Za-z0-9]/', '', $word);
        if ($word === '') {
            continue;
        }
        $letters .= strtoupper(substr($word, 0, 3)) . '-';
    }
    $letters = rtrim($letters, '-');
    if ($letters === '') {
        $letters = 'PROD';
    }
    $letters = substr($letters, 0, 24);

    $sku = $letters;
    $n = 1;
    $stmt = $db->prepare('SELECT COUNT(*) FROM products WHERE sku = ?');
    do {
        $stmt->execute([$sku]);
        $exists = (int) $stmt->fetchColumn() > 0;
        if ($exists) {
            $n++;
            $sku = $letters . '-' . $n;
        }
    } while ($exists);

    return $sku;
}


function all_settings(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $defaults = [
        'company_name'        => 'Botol Anggun Sdn. Bhd.',
        'company_reg_no'      => '',
        'company_address'     => '',
        'company_phone'       => '',
        'company_email'       => '',
        'company_logo'        => '',
        'language'            => 'en',
        'dark_mode'           => '0',
        'notify_low_stock'    => '1',
        'notify_stock_in'     => '1',
        'notify_stock_out'    => '1',
        'notify_reports'      => '1',
        'notify_users'        => '1',
        'session_timeout_min' => '30',
    ];

    try {
        $rows = getDB()->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
        foreach ($rows as $r) {
            $defaults[$r['setting_key']] = $r['setting_value'];
        }
    } catch (Exception $e) {
        // Table missing -> keep defaults.
    }

    $cache = $defaults;
    return $cache;
}

/** Read one setting */
function setting(string $key, string $fallback = ''): string
{
    $all = all_settings();
    return $all[$key] ?? $fallback;
}

/** Write one setting */
function set_setting(string $key, string $value): void
{
    $stmt = getDB()->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?,?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}

/**
 * Record a business activity ("Ali received 500 pcs of PET Bottle 500ml").
 * Deliberately never throws -- a logging failure must not break the action
 * the user was actually performing.
 */
function log_activity(
    string $module,
    string $activity,
    ?int $productId = null,
    ?string $productName = null,
    ?int $quantity = null
): void {
    try {
        $user = current_user();
        $stmt = getDB()->prepare(
            'INSERT INTO activity_log (user_id, user_name, module, activity, product_id, product_name, quantity)
             VALUES (?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $user['id']   ?? null,
            $user['name'] ?? 'System',
            $module,
            $activity,
            $productId,
            $productName,
            $quantity,
        ]);
    } catch (Exception $e) {
        // Swallow -- logging is best-effort.
    }
}

/**
 * Record a field-level change for the audit trail.
 * Pass $changes as ['field' => ['old' => ..., 'new' => ...], ...] to write
 * one row per changed field, or leave it empty for actions with no diff
 * (LOGIN, DELETE, BACKUP, ...).
 */
function log_audit(
    string $module,
    string $action,
    ?string $recordType = null,
    ?int $recordId = null,
    array $changes = []
): void {
    try {
        $user = current_user();
        $db   = getDB();
        $stmt = $db->prepare(
            'INSERT INTO audit_log (user_id, user_name, module, action, record_type, record_id, field_name, old_value, new_value)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );

        if (empty($changes)) {
            $stmt->execute([
                $user['id'] ?? null, $user['name'] ?? 'System',
                $module, $action, $recordType, $recordId, null, null, null,
            ]);
            return;
        }

        foreach ($changes as $field => $pair) {
            $old = is_array($pair) ? ($pair['old'] ?? null) : null;
            $new = is_array($pair) ? ($pair['new'] ?? null) : $pair;
            if ((string) $old === (string) $new) {
                continue; // nothing actually changed -- don't log noise
            }
            $stmt->execute([
                $user['id'] ?? null, $user['name'] ?? 'System',
                $module, $action, $recordType, $recordId,
                $field,
                $old === null ? null : (string) $old,
                $new === null ? null : (string) $new,
            ]);
        }
    } catch (Exception $e) {
        // Best-effort.
    }
}

/**
 * Push a notification into the notification centre.
 * $userId null = broadcast to everyone. Respects the per-type on/off
 * switches in System Settings.
 */
function notify(string $type, string $title, ?string $message = null, ?string $link = null, ?int $userId = null): void
{
    $switch = [
        'low_stock' => 'notify_low_stock',
        'stock_in'  => 'notify_stock_in',
        'stock_out' => 'notify_stock_out',
        'report'    => 'notify_reports',
        'user'      => 'notify_users',
    ];
    if (isset($switch[$type]) && setting($switch[$type], '1') !== '1') {
        return; // this notification type is switched off
    }

    try {
        $stmt = getDB()->prepare(
            'INSERT INTO notifications (user_id, type, title, message, link) VALUES (?,?,?,?,?)'
        );
        $stmt->execute([$userId, $type, $title, $message, $link]);
    } catch (Exception $e) {
        // Best-effort.
    }
}

/** Icon + colour for each notification type (used by the bell dropdown) */
function notification_style(string $type): array
{
    switch ($type) {
        case 'low_stock': return ['icon' => 'bi-exclamation-triangle-fill', 'class' => 'critical'];
        case 'stock_in':  return ['icon' => 'bi-box-arrow-in-down',         'class' => 'instock'];
        case 'stock_out': return ['icon' => 'bi-box-arrow-up',              'class' => 'out'];
        case 'report':    return ['icon' => 'bi-file-earmark-text',         'class' => 'info'];
        case 'user':      return ['icon' => 'bi-person-plus',               'class' => 'info'];
        default:          return ['icon' => 'bi-bell',                      'class' => 'info'];
    }
}

/**
 * Enforce an idle session timeout. Called from bootstrap on every request.
 * Any request more than N minutes after the last one logs the user out.
 */
function enforce_session_timeout(): void
{
    if (empty($_SESSION['user_id'])) {
        return;
    }

    $limit = (int) setting('session_timeout_min', '30');
    if ($limit <= 0) {
        return; // 0 = never time out
    }
    $limitSeconds = $limit * 60;

    if (isset($_SESSION['last_seen']) && (time() - $_SESSION['last_seen']) > $limitSeconds) {
        $_SESSION = [];
        session_destroy();
        session_start();
        set_flash('warning', 'You were signed out automatically after ' . $limit . ' minutes of inactivity.');
        redirect('auth/login.php');
    }

    $_SESSION['last_seen'] = time();
}

/** True when the logged-in user is an administrator */
function is_admin(): bool
{
    return ($_SESSION['role'] ?? '') === 'admin';
}

/**
 * Validate a CSRF token for state-changing POSTs.
 * Pairs with csrf_field() in the forms.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input carrying the CSRF token */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Abort the request if the submitted CSRF token is missing or wrong */
function verify_csrf(string $redirectTo = 'dashboard.php'): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sent)) {
        set_flash('danger', 'Your session expired or the form was submitted twice. Please try again.');
        redirect($redirectTo);
    }
}

/** Shortcut: page is for administrators only */
function require_admin(): void
{
    require_role('admin');
}
