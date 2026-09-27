<?php

$user = current_user();
$initials = '';
foreach (explode(' ', trim($user['name'] ?? '')) as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2) ?: 'U';

/* Notifications -- real notification centre feed, newest first. */
$notifDb  = getDB();
$uidNotif = (int) ($_SESSION['user_id'] ?? 0);
$notifCount    = 0;
$notifications = [];
try {
    $stmt = $notifDb->prepare(
        'SELECT COUNT(*) FROM notifications WHERE is_read = 0 AND (user_id IS NULL OR user_id = ?)'
    );
    $stmt->execute([$uidNotif]);
    $notifCount = (int) $stmt->fetchColumn();

    $stmt = $notifDb->prepare(
        'SELECT * FROM notifications WHERE user_id IS NULL OR user_id = ?
         ORDER BY is_read ASC, created_at DESC LIMIT 6'
    );
    $stmt->execute([$uidNotif]);
    $notifications = $stmt->fetchAll();
} catch (Exception $e) {
    // Migration not run yet -- fall back to the low stock count only.
    try {
        $notifCount = (int) $notifDb->query(
            'SELECT COUNT(*) FROM products WHERE current_stock < min_stock * 1.5'
        )->fetchColumn();
    } catch (Exception $e2) { /* ignore */ }
}

/* Low stock badge for the sidebar */
$lowStockBadge = 0;
try {
    $lowStockBadge = (int) $notifDb->query(
        'SELECT COUNT(*) FROM products WHERE current_stock < min_stock * 1.5'
    )->fetchColumn();
} catch (Exception $e) { /* ignore */ }

$darkMode = setting('dark_mode', '0') === '1';
?>
<!DOCTYPE html>
<html lang="en"<?= $darkMode ? ' data-theme="dark"' : '' ?>>
<head>
<meta charset="UTF-8">
<title><?= e($pageTitle ?? 'Dashboard') ?> · SMART STOCK</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= e(base_url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="app-shell">

  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <img src="<?= e(base_url('assets/img/botol-anggun-mark.png')) ?>" alt="Botol Anggun" class="brand-logo">
      <div class="name">SMART STOCK</div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-label">Overview</div>
      <a href="<?= e(base_url('dashboard.php')) ?>" class="nav-link <?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
        Dashboard
      </a>

      <div class="nav-label">Inventory</div>
      <?php if (is_admin()): ?>
      <a href="<?= e(base_url('products/index.php')) ?>" class="nav-link <?= ($activeNav ?? '') === 'products' ? 'active' : '' ?>">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8L12 3 3 8l9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/></svg>
        Product Management
      </a>
      <?php endif; ?>
      <a href="<?= e(base_url('stock_in.php')) ?>" class="nav-link <?= ($activeNav ?? '') === 'stockin' ? 'active' : '' ?>">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M12 16V8"/><path d="M8 12l4-4 4 4"/></svg>
        Stock In
      </a>
      <a href="<?= e(base_url('stock_out.php')) ?>" class="nav-link <?= ($activeNav ?? '') === 'stockout' ? 'active' : '' ?>">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M12 8v8"/><path d="M8 12l4 4 4-4"/></svg>
        Stock Out
      </a>
      <a href="<?= e(base_url('scan.php')) ?>" class="nav-link <?= ($activeNav ?? '') === 'scan' ? 'active' : '' ?>">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3z"/><path d="M20 14v3"/><path d="M14 20h3"/><path d="M20 20h.01"/></svg>
        Scan QR Code
      </a>
      <?php if (is_admin()): ?>
      <a href="<?= e(base_url('low_stock.php')) ?>" class="nav-link <?= ($activeNav ?? '') === 'lowstock' ? 'active' : '' ?>">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        Low Stock Alerts
        <?php if ($lowStockBadge > 0): ?><span class="nav-badge"><?= (int) $lowStockBadge ?></span><?php endif; ?>
      </a>
      <?php endif; ?>

      <?php if (is_admin()): ?>
      <div class="nav-label">Insights</div>
      <a href="<?= e(base_url('reports/index.php')) ?>" class="nav-link <?= ($activeNav ?? '') === 'reports' ? 'active' : '' ?>">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><rect x="7" y="12" width="3" height="6"/><rect x="12" y="8" width="3" height="10"/><rect x="17" y="5" width="3" height="13"/></svg>
        Reports
      </a>
      <a href="<?= e(base_url('notifications.php')) ?>" class="nav-link <?= ($activeNav ?? '') === 'notifications' ? 'active' : '' ?>">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        Notifications
        <?php if ($notifCount > 0): ?><span class="nav-badge"><?= (int) $notifCount ?></span><?php endif; ?>
      </a>
      <?php endif; ?>

      <?php if (is_admin()): ?>
        <div class="nav-label">Administration</div>
        <a href="<?= e(base_url('users/index.php')) ?>" class="nav-link <?= ($activeNav ?? '') === 'users' ? 'active' : '' ?>">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          User Management
        </a>
        <a href="<?= e(base_url('logs/activity.php')) ?>" class="nav-link <?= ($activeNav ?? '') === 'activity' ? 'active' : '' ?>">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
          Activity Log
        </a>
        <a href="<?= e(base_url('settings.php')) ?>" class="nav-link <?= ($activeNav ?? '') === 'settings' ? 'active' : '' ?>">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
          Settings
        </a>
      <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
      <div class="user-row">
        <div class="avatar"><?= e($initials) ?></div>
        <div>
          <div class="uname"><?= e($user['name'] ?? '') ?></div>
          <div class="urole"><?= e(ucfirst($user['role'] ?? '')) ?></div>
        </div>
        <a href="<?= e(base_url('auth/logout.php')) ?>" class="logout-btn" title="Log out" onclick="return confirm('Log out of SMART STOCK?');">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </a>
      </div>
    </div>
  </aside>

  <main class="main-content">
    <div class="topbar">
      <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-erp-outline d-lg-none" id="sidebarToggle" type="button" aria-label="Toggle menu">
          <i class="bi bi-list"></i>
        </button>
        <div>
          <h1><?= e($pageTitle ?? '') ?></h1>
          <?php if (!empty($pageCrumb)): ?><div class="crumb"><?= e($pageCrumb) ?></div><?php endif; ?>
        </div>
      </div>
      <div class="d-flex align-items-center gap-3">
        <?php if (is_admin()): ?>
        <div class="dropdown">
          <button class="btn-icon-topbar" type="button" id="notifBell" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
            <i class="bi bi-bell"></i>
            <?php if ($notifCount > 0): ?><span class="notif-dot"></span><?php endif; ?>
          </button>
          <div class="dropdown-menu dropdown-menu-end notif-dropdown" aria-labelledby="notifBell">
            <div class="notif-head">
              <span>Notifications</span>
              <?php if ($notifCount > 0): ?><span class="notif-count-badge"><?= $notifCount ?></span><?php endif; ?>
            </div>
            <div class="notif-list">
              <?php if (empty($notifications)): ?>
                <div class="notif-empty">
                  <i class="bi bi-check-circle" style="font-size:22px;color:var(--green);"></i>
                  <div class="mt-2">You're all caught up — no new notifications.</div>
                </div>
              <?php else: ?>
                <?php foreach ($notifications as $n): $nst = notification_style($n['type']); ?>
                  <a href="<?= e($n['link'] ? base_url($n['link']) : base_url('notifications.php')) ?>"
                     class="notif-item <?= (int) $n['is_read'] === 0 ? 'unread' : '' ?>">
                    <span class="notif-item-icon <?= e($nst['class']) ?>"><i class="bi <?= e($nst['icon']) ?>"></i></span>
                    <span class="notif-item-body">
                      <span class="notif-item-title"><?= e($n['title']) ?></span>
                      <span class="notif-item-sub"><?= e($n['message'] ?: time_ago($n['created_at'])) ?></span>
                    </span>
                  </a>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <a href="<?= e(base_url('notifications.php')) ?>" class="notif-footer-link">View all notifications</a>
          </div>
        </div>
        <?php endif; ?>

        <!-- PROFILE DROPDOWN (Bootstrap Native Integration) -->
        <div class="dropdown">
          <button class="profile border-0 bg-transparent text-start p-0 d-flex align-items-center gap-2" type="button" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="cursor: pointer;">
            <div class="avatar"><?= e($initials) ?></div>
            <div>
              <div class="name"><?= e($user['name'] ?? '') ?></div>
              <div class="role"><?= e(ucfirst($user['role'] ?? '')) ?></div>
            </div>
            <i class="bi bi-chevron-down ms-1" style="font-size:12px; opacity:0.6;"></i>
          </button>

          <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="profileDropdown" style="border-radius:12px; margin-top:8px;">
            <?php if (is_admin()): ?>
            <li>
              <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="<?= e(base_url('settings.php')) ?>">
                <i class="bi bi-gear text-muted"></i> Profile Settings
              </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <?php endif; ?>
            <li>
              <a class="dropdown-item py-2 text-danger d-flex align-items-center gap-2" href="<?= e(base_url('auth/logout.php')) ?>" onclick="return confirm('Log out of SMART STOCK?');">
                <i class="bi bi-box-arrow-right"></i> Log Out
              </a>
            </li>
          </ul>
        </div>

      </div>
    </div>

    <div class="page-content">
      <?php $flash = get_flash(); ?>
      <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert" style="border-radius:12px;border:none;">
          <?= e($flash['message']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>