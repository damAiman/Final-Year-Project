<?php
require_once __DIR__ . '/config/bootstrap.php';
require_admin();

$db  = getDB();
$uid = (int) $_SESSION['user_id'];

/* Mark-all-read action */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_all') {
    verify_csrf('notifications.php');
    $stmt = $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id IS NULL OR user_id = ?');
    $stmt->execute([$uid]);
    set_flash('success', 'All notifications marked as read.');
    redirect('notifications.php');
}

$type = trim($_GET['type'] ?? '');

$where  = ['(user_id IS NULL OR user_id = ?)'];
$params = [$uid];
if ($type !== '') {
    $where[] = 'type = ?';
    $params[] = $type;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$stmt = $db->prepare("SELECT * FROM notifications $whereSql ORDER BY created_at DESC LIMIT 100");
$stmt->execute($params);
$notifications = $stmt->fetchAll();

$unreadStmt = $db->prepare('SELECT COUNT(*) FROM notifications WHERE is_read = 0 AND (user_id IS NULL OR user_id = ?)');
$unreadStmt->execute([$uid]);
$unread = (int) $unreadStmt->fetchColumn();

$typeCounts = [];
$tcStmt = $db->prepare("SELECT type, COUNT(*) c FROM notifications WHERE user_id IS NULL OR user_id = ? GROUP BY type");
$tcStmt->execute([$uid]);
foreach ($tcStmt->fetchAll() as $r) {
    $typeCounts[$r['type']] = (int) $r['c'];
}

$typeLabels = [
    'low_stock' => 'Low Stock',
    'stock_in'  => 'Stock In',
    'stock_out' => 'Stock Out',
    'report'    => 'Reports',
    'user'      => 'Users',
    'system'    => 'System',
];

$pageTitle = 'Notifications';
$pageCrumb = $unread > 0 ? $unread . ' unread notification(s)' : 'You are all caught up';
$activeNav = 'notifications';

require_once __DIR__ . '/includes/header.php';
?>

<div class="card-erp">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= e(base_url('notifications.php')) ?>" class="filter-chip <?= $type === '' ? 'active' : '' ?>">
        All <span class="fc-count"><?= array_sum($typeCounts) ?></span>
      </a>
      <?php foreach ($typeLabels as $key => $label): if (empty($typeCounts[$key])) continue; ?>
        <a href="<?= e(base_url('notifications.php?type=' . urlencode($key))) ?>"
           class="filter-chip <?= $type === $key ? 'active' : '' ?>">
          <?= e($label) ?> <span class="fc-count"><?= (int) $typeCounts[$key] ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <?php if ($unread > 0): ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="mark_all">
        <button class="btn btn-sm btn-erp-outline" type="submit">
          <i class="bi bi-check2-all"></i> Mark all as read
        </button>
      </form>
    <?php endif; ?>
  </div>

  <?php if (empty($notifications)): ?>
    <div class="text-center text-muted py-5">
      <i class="bi bi-bell-slash" style="font-size:30px;"></i>
      <div class="mt-2 fw-semibold">No notifications yet</div>
      <p class="small mb-0">Stock movements, low stock warnings and account changes will appear here.</p>
    </div>
  <?php endif; ?>

  <?php foreach ($notifications as $n): $st = notification_style($n['type']); ?>
    <?php if ($n['link']): ?><a href="<?= e(base_url($n['link'])) ?>" class="notif-full <?= (int) $n['is_read'] === 0 ? 'unread' : '' ?>"><?php
      else: ?><div class="notif-full <?= (int) $n['is_read'] === 0 ? 'unread' : '' ?>"><?php endif; ?>
      <span class="nf-icon <?= e($st['class']) ?>"><i class="bi <?= e($st['icon']) ?>"></i></span>
      <span class="nf-body">
        <span class="nf-title"><?= e($n['title']) ?></span>
        <?php if ($n['message']): ?><span class="nf-msg"><?= e($n['message']) ?></span><?php endif; ?>
        <span class="nf-time"><i class="bi bi-clock"></i> <?= e(time_ago($n['created_at'])) ?></span>
      </span>
      <?php if ((int) $n['is_read'] === 0): ?><span class="nf-dot"></span><?php endif; ?>
    <?php if ($n['link']): ?></a><?php else: ?></div><?php endif; ?>
  <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
