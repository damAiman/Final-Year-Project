<?php

require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');

$db = getDB();

$view = ($_GET['view'] ?? 'activity') === 'changes' ? 'changes' : 'activity';

/* ---- filters ---- */
$q       = trim($_GET['q'] ?? '');
$module  = trim($_GET['module'] ?? '');
$user    = trim($_GET['user'] ?? '');
$action  = trim($_GET['action'] ?? '');
$from    = trim($_GET['from'] ?? '');
$to      = trim($_GET['to'] ?? '');
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;

/* Table name is chosen from a fixed pair -- never from raw user input. */
$table  = $view === 'changes' ? 'audit_log' : 'activity_log';
$where  = [];
$params = [];

if ($q !== '') {
    $like = '%' . $q . '%';
    if ($view === 'changes') {
        $where[] = '(user_name LIKE ? OR record_type LIKE ? OR field_name LIKE ? OR old_value LIKE ? OR new_value LIKE ?)';
        array_push($params, $like, $like, $like, $like, $like);
    } else {
        $where[] = '(activity LIKE ? OR product_name LIKE ? OR user_name LIKE ?)';
        array_push($params, $like, $like, $like);
    }
}
if ($module !== '') { $where[] = 'module = ?';    $params[] = $module; }
if ($user !== '')   { $where[] = 'user_name = ?'; $params[] = $user; }
if ($view === 'changes' && $action !== '') { $where[] = 'action = ?'; $params[] = $action; }
if ($from !== '')   { $where[] = 'DATE(created_at) >= ?'; $params[] = $from; }
if ($to !== '')     { $where[] = 'DATE(created_at) <= ?'; $params[] = $to; }

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $db->prepare("SELECT COUNT(*) FROM $table $whereSql");
$countStmt->execute($params);
$totalRows  = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page   = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("SELECT * FROM $table $whereSql ORDER BY created_at DESC, id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$rows = $stmt->fetchAll();

/* ---- dropdown options + headline counts ---- */
$modules  = $db->query("SELECT DISTINCT module FROM $table ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);
$userList = $db->query("SELECT DISTINCT user_name FROM $table ORDER BY user_name")->fetchAll(PDO::FETCH_COLUMN);
$actions  = $view === 'changes'
    ? $db->query('SELECT DISTINCT action FROM audit_log ORDER BY action')->fetchAll(PDO::FETCH_COLUMN)
    : [];

$activityTotal = (int) $db->query('SELECT COUNT(*) FROM activity_log')->fetchColumn();
$changesTotal  = (int) $db->query('SELECT COUNT(*) FROM audit_log')->fetchColumn();
$todayCount    = (int) $db->query("SELECT COUNT(*) FROM $table WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$weekCount     = (int) $db->query("SELECT COUNT(*) FROM $table WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

function module_icon(string $m): string
{
    switch ($m) {
        case 'Stock In':           return 'bi-box-arrow-in-down';
        case 'Stock Out':          return 'bi-box-arrow-up';
        case 'Product Management': return 'bi-box-seam';
        case 'User Management':    return 'bi-people';
        case 'Reports':            return 'bi-file-earmark-text';
        case 'Authentication':     return 'bi-shield-lock';
        case 'Settings':           return 'bi-gear';
        default:                   return 'bi-activity';
    }
}
function action_class(string $a): string
{
    switch ($a) {
        case 'CREATE': return 'instock';
        case 'DELETE': return 'critical';
        case 'UPDATE': return 'low';
        default:       return 'info';
    }
}
/** Rebuild the current query string with some parameters replaced */
function qs_with(array $overrides): string
{
    $qs = array_merge($_GET, $overrides);
    $qs = array_filter($qs, function ($v) { return $v !== '' && $v !== null; });
    return http_build_query($qs);
}

$pageTitle = 'Activity Log';
$pageCrumb = $view === 'changes'
    ? fmt_num($totalRows) . ' recorded field change(s)'
    : fmt_num($totalRows) . ' recorded activity record(s)';
$activeNav = 'activity';

require_once __DIR__ . '/../includes/header.php';
?>

<!-- One page, two views -- replaces what used to be two near-identical pages -->
<div class="log-switch mb-3">
  <a href="?<?= e(qs_with(['view' => 'activity', 'page' => 1, 'action' => ''])) ?>"
     class="log-switch-btn <?= $view === 'activity' ? 'active' : '' ?>">
    <i class="bi bi-activity"></i>
    <span class="ls-txt">
      <strong>Activity</strong>
      <em>What people did</em>
    </span>
    <span class="ls-count"><?= fmt_num($activityTotal) ?></span>
  </a>
  <a href="?<?= e(qs_with(['view' => 'changes', 'page' => 1, 'user' => ''])) ?>"
     class="log-switch-btn <?= $view === 'changes' ? 'active' : '' ?>">
    <i class="bi bi-shield-check"></i>
    <span class="ls-txt">
      <strong>Field Changes</strong>
      <em>What values changed</em>
    </span>
    <span class="ls-count"><?= fmt_num($changesTotal) ?></span>
  </a>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-4"><div class="kpi-card h-100">
    <div class="icon" style="background:var(--purple-100);"><i class="bi bi-list-ul" style="color:var(--purple-600);"></i></div>
    <div class="value"><?= fmt_num($totalRows) ?></div><div class="label">Matching Records</div></div></div>
  <div class="col-6 col-lg-4"><div class="kpi-card h-100">
    <div class="icon" style="background:var(--green-bg);"><i class="bi bi-calendar-day" style="color:var(--green);"></i></div>
    <div class="value"><?= fmt_num($todayCount) ?></div><div class="label">Recorded Today</div></div></div>
  <div class="col-6 col-lg-4"><div class="kpi-card h-100">
    <div class="icon" style="background:var(--amber-bg);"><i class="bi bi-calendar-week" style="color:var(--amber);"></i></div>
    <div class="value"><?= fmt_num($weekCount) ?></div><div class="label">Last 7 Days</div></div></div>
</div>

<div class="card-erp">
  <form method="get" class="row g-2 align-items-end mb-3">
    <input type="hidden" name="view" value="<?= e($view) ?>">
    <div class="col-md-3">
      <label class="form-label-erp form-label">Search</label>
      <input type="text" name="q" class="form-control form-control-erp"
             placeholder="<?= $view === 'changes' ? 'User, field or value…' : 'Activity, product or user…' ?>"
             value="<?= e($q) ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label-erp form-label">Module</label>
      <select name="module" class="form-select form-control-erp">
        <option value="">All modules</option>
        <?php foreach ($modules as $m): ?>
          <option value="<?= e($m) ?>" <?= $module === $m ? 'selected' : '' ?>><?= e($m) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($view === 'changes'): ?>
      <div class="col-md-2">
        <label class="form-label-erp form-label">Action</label>
        <select name="action" class="form-select form-control-erp">
          <option value="">All actions</option>
          <?php foreach ($actions as $a): ?>
            <option value="<?= e($a) ?>" <?= $action === $a ? 'selected' : '' ?>><?= e($a) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php else: ?>
      <div class="col-md-2">
        <label class="form-label-erp form-label">User</label>
        <select name="user" class="form-select form-control-erp">
          <option value="">All users</option>
          <?php foreach ($userList as $u): ?>
            <option value="<?= e($u) ?>" <?= $user === $u ? 'selected' : '' ?>><?= e($u) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>
    <div class="col-md-2">
      <label class="form-label-erp form-label">From</label>
      <input type="date" name="from" class="form-control form-control-erp" value="<?= e($from) ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label-erp form-label">To</label>
      <input type="date" name="to" class="form-control form-control-erp" value="<?= e($to) ?>">
    </div>
    <div class="col-md-1 d-flex gap-1">
      <button class="btn btn-erp-primary w-100" type="submit"><i class="bi bi-funnel"></i></button>
      <a href="<?= e(base_url('logs/activity.php?view=' . $view)) ?>" class="btn btn-erp-outline" title="Reset filters"><i class="bi bi-x-lg"></i></a>
    </div>
  </form>

  <div class="table-responsive">
    <?php if ($view === 'activity'): ?>
      <table class="table table-erp align-middle">
        <thead>
          <tr><th>Date</th><th>Time</th><th>User</th><th>Module</th><th>Activity</th><th>Product</th><th>Quantity</th></tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="7" class="text-center text-muted py-5">
              <i class="bi bi-inbox" style="font-size:26px;"></i>
              <div class="mt-2">No activity matches these filters yet.</div>
            </td></tr>
          <?php endif; ?>
          <?php foreach ($rows as $l): ?>
            <tr>
              <td class="mono small text-muted"><?= e(date('d M Y', strtotime($l['created_at']))) ?></td>
              <td class="mono small text-muted"><?= e(date('h:i A', strtotime($l['created_at']))) ?></td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div class="avatar-sm"><?= e(strtoupper(substr($l['user_name'], 0, 2))) ?></div>
                  <span class="small fw-semibold"><?= e($l['user_name']) ?></span>
                </div>
              </td>
              <td><span class="module-chip"><i class="bi <?= e(module_icon($l['module'])) ?>"></i> <?= e($l['module']) ?></span></td>
              <td class="small"><?= e($l['activity']) ?></td>
              <td class="small text-muted"><?= e($l['product_name'] ?: '—') ?></td>
              <td class="mono small">
                <?php if ($l['quantity'] !== null): ?>
                  <span style="color:<?= $l['module'] === 'Stock Out' ? 'var(--red)' : 'var(--green)' ?>;">
                    <?= $l['module'] === 'Stock Out' ? '-' : '+' ?><?= fmt_num($l['quantity']) ?>
                  </span>
                <?php else: ?>—<?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

    <?php else: ?>
      <table class="table table-erp align-middle">
        <thead>
          <tr><th>Date</th><th>Time</th><th>User</th><th>Module</th><th>Action</th><th>Field</th><th>Old Value</th><th>New Value</th></tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="8" class="text-center text-muted py-5">
              <i class="bi bi-shield-slash" style="font-size:26px;"></i>
              <div class="mt-2">No field changes match these filters yet.</div>
            </td></tr>
          <?php endif; ?>
          <?php foreach ($rows as $l): ?>
            <tr>
              <td class="mono small text-muted"><?= e(date('d M Y', strtotime($l['created_at']))) ?></td>
              <td class="mono small text-muted"><?= e(date('h:i A', strtotime($l['created_at']))) ?></td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div class="avatar-sm"><?= e(strtoupper(substr($l['user_name'], 0, 2))) ?></div>
                  <span class="small fw-semibold"><?= e($l['user_name']) ?></span>
                </div>
              </td>
              <td class="small"><?= e($l['module']) ?></td>
              <td><span class="badge-status <?= e(action_class($l['action'])) ?>"><?= e($l['action']) ?></span></td>
              <td class="small">
                <?php if ($l['field_name']): ?>
                  <span class="field-chip"><?= e($l['field_name']) ?></span>
                  <?php if ($l['record_type']): ?>
                    <div class="text-muted" style="font-size:10.5px;"><?= e($l['record_type']) ?> #<?= (int) $l['record_id'] ?></div>
                  <?php endif; ?>
                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
              </td>
              <td class="small"><span class="val-old"><?= e($l['old_value'] !== null && $l['old_value'] !== '' ? $l['old_value'] : '—') ?></span></td>
              <td class="small"><span class="val-new"><?= e($l['new_value'] !== null && $l['new_value'] !== '' ? $l['new_value'] : '—') ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <?php if ($totalPages > 1): ?>
    <div class="d-flex justify-content-between align-items-center pt-3 mt-2 border-top">
      <div class="small text-muted">
        Showing <?= fmt_num($offset + 1) ?>–<?= fmt_num(min($offset + $perPage, $totalRows)) ?> of <?= fmt_num($totalRows) ?>
      </div>
      <div class="d-flex gap-1">
        <a class="btn btn-sm btn-erp-outline <?= $page <= 1 ? 'disabled' : '' ?>"
           href="?<?= e(qs_with(['page' => $page - 1])) ?>"><i class="bi bi-chevron-left"></i></a>
        <span class="btn btn-sm btn-erp-outline disabled">Page <?= $page ?> / <?= $totalPages ?></span>
        <a class="btn btn-sm btn-erp-outline <?= $page >= $totalPages ? 'disabled' : '' ?>"
           href="?<?= e(qs_with(['page' => $page + 1])) ?>"><i class="bi bi-chevron-right"></i></a>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
