<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');   // admin-only module

$db = getDB();

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $stmt = $db->prepare(
        'SELECT * FROM users WHERE name LIKE ? OR username LIKE ? OR email LIKE ? ORDER BY role ASC, name ASC'
    );
    $like = '%' . $q . '%';
    $stmt->execute([$like, $like, $like]);
} else {
    $stmt = $db->query('SELECT * FROM users ORDER BY role ASC, name ASC');
}
$users = $stmt->fetchAll();

$totalUsers  = count($users);
$adminCount  = 0;
$activeCount = 0;
foreach ($users as $u) {
    if ($u['role'] === 'admin')   { $adminCount++; }
    if ((int) $u['is_active'] === 1) { $activeCount++; }
}

$pageTitle = 'User Management';
$pageCrumb = $totalUsers . ' user account(s)';
$activeNav = 'users';
$pageScripts = ['assets/js/users.js'];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3"><div class="kpi-card h-100">
    <div class="icon" style="background:var(--blue-100);"><i class="bi bi-people" style="color:var(--blue-600);"></i></div>
    <div class="value"><?= fmt_num($totalUsers) ?></div><div class="label">Total Users</div></div></div>
  <div class="col-6 col-lg-3"><div class="kpi-card h-100">
    <div class="icon" style="background:var(--green-bg);"><i class="bi bi-person-check" style="color:var(--green);"></i></div>
    <div class="value"><?= fmt_num($activeCount) ?></div><div class="label">Active Accounts</div></div></div>
  <div class="col-6 col-lg-3"><div class="kpi-card h-100">
    <div class="icon" style="background:var(--amber-bg);"><i class="bi bi-shield-lock" style="color:var(--amber);"></i></div>
    <div class="value"><?= fmt_num($adminCount) ?></div><div class="label">Administrators</div></div></div>
  <div class="col-6 col-lg-3"><div class="kpi-card h-100">
    <div class="icon" style="background:var(--red-bg);"><i class="bi bi-person-dash" style="color:var(--red);"></i></div>
    <div class="value"><?= fmt_num($totalUsers - $activeCount) ?></div><div class="label">Deactivated</div></div></div>
</div>

<div class="card-erp">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <form method="get" class="d-flex gap-2 flex-grow-1" style="max-width:340px;">
      <input type="text" name="q" class="form-control form-control-erp" placeholder="Search name, username or email..." value="<?= e($q) ?>">
      <button class="btn btn-erp-outline" type="submit"><i class="bi bi-search"></i></button>
    </form>
    <button class="btn btn-erp-primary" id="btnAddUser">
      <i class="bi bi-person-plus"></i> Add User
    </button>
  </div>

  <div class="table-responsive">
    <table class="table table-erp align-middle">
      <thead>
        <tr>
          <th>User</th><th>Username</th><th>Email</th><th>Role</th>
          <th>Status</th><th>Last Login</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($users)): ?>
          <tr><td colspan="7" class="text-center text-muted py-5">No users match your search.</td></tr>
        <?php endif; ?>
        <?php foreach ($users as $u):
          $initials = '';
          foreach (explode(' ', trim($u['name'])) as $part) { $initials .= strtoupper(substr($part, 0, 1)); }
          $initials = substr($initials, 0, 2);
          $isSelf = (int) $u['id'] === (int) $_SESSION['user_id'];
        ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="avatar-sm"><?= e($initials) ?></div>
                <div>
                  <div class="fw-semibold"><?= e($u['name']) ?><?= $isSelf ? ' <span class="you-tag">you</span>' : '' ?></div>
                  <div class="small text-muted">Added <?= e(date('d M Y', strtotime($u['created_at']))) ?></div>
                </div>
              </div>
            </td>
            <td class="mono small"><?= e($u['username']) ?></td>
            <td class="small text-muted"><?= e($u['email'] ?: '—') ?></td>
            <td>
              <span class="role-chip <?= $u['role'] === 'admin' ? 'admin' : 'staff' ?>">
                <i class="bi <?= $u['role'] === 'admin' ? 'bi-shield-lock' : 'bi-person' ?>"></i>
                <?= e(ucfirst($u['role'])) ?>
              </span>
            </td>
            <td>
              <?php if ((int) $u['is_active'] === 1): ?>
                <span class="badge-status instock">Active</span>
              <?php else: ?>
                <span class="badge-status out">Deactivated</span>
              <?php endif; ?>
            </td>
            <td class="small text-muted">
              <?= $u['last_login'] ? e(time_ago($u['last_login'])) : 'Never' ?>
            </td>
            <td class="text-nowrap">
              <button class="act-btn btn-edit-user" title="Edit user"
                data-id="<?= (int) $u['id'] ?>"
                data-name="<?= e($u['name']) ?>"
                data-username="<?= e($u['username']) ?>"
                data-email="<?= e($u['email']) ?>"
                data-role="<?= e($u['role']) ?>">
                <i class="bi bi-pencil"></i>
              </button>

              <button class="act-btn btn-reset-pw" title="Reset password"
                data-id="<?= (int) $u['id'] ?>" data-name="<?= e($u['name']) ?>">
                <i class="bi bi-key"></i>
              </button>

              <?php if (!$isSelf): ?>
                <form method="post" action="<?= e(base_url('users/save.php')) ?>" class="d-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                  <button class="act-btn" type="submit"
                    title="<?= (int) $u['is_active'] === 1 ? 'Deactivate account' : 'Activate account' ?>"
                    onclick="return confirm('<?= (int) $u['is_active'] === 1 ? 'Deactivate' : 'Activate' ?> <?= e(addslashes($u['name'])) ?>?');">
                    <i class="bi <?= (int) $u['is_active'] === 1 ? 'bi-toggle-on' : 'bi-toggle-off' ?>"
                       style="color:<?= (int) $u['is_active'] === 1 ? 'var(--green)' : 'var(--ink-400)' ?>;"></i>
                  </button>
                </form>

                <form method="post" action="<?= e(base_url('users/save.php')) ?>" class="d-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                  <button class="act-btn danger" type="submit" title="Delete user"
                    onclick="return confirm('Delete the account for <?= e(addslashes($u['name'])) ?>?\n\nTheir past transactions are kept, but will no longer be linked to a user.');">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              <?php else: ?>
                <span class="act-btn disabled" title="You cannot deactivate or delete your own account"><i class="bi bi-lock"></i></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add / Edit user modal -->
<div class="modal fade" id="userModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:16px;border:none;">
      <form method="post" action="<?= e(base_url('users/save.php')) ?>" id="userForm">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="u-id" value="">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold" id="userModalTitle">Add User</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label-erp form-label">Full Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="u-name" class="form-control form-control-erp" required placeholder="e.g. Ali bin Rahman">
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label-erp form-label">Username <span class="text-danger">*</span></label>
              <input type="text" name="username" id="u-username" class="form-control form-control-erp" required placeholder="e.g. ali.rahman">
            </div>
            <div class="col-md-6">
              <label class="form-label-erp form-label">Role <span class="text-danger">*</span></label>
              <select name="role" id="u-role" class="form-select form-control-erp" required>
                <option value="staff">Staff</option>
                <option value="admin">Admin</option>
              </select>
            </div>
          </div>
          <div class="mt-3">
            <label class="form-label-erp form-label">Email</label>
            <input type="email" name="email" id="u-email" class="form-control form-control-erp" placeholder="e.g. ali.rahman@botolanggun.com.my">
          </div>
          <div class="mt-3" id="pwWrap">
            <label class="form-label-erp form-label">Password <span class="text-danger" id="pwStar">*</span></label>
            <input type="password" name="password" id="u-password" class="form-control form-control-erp" placeholder="At least 6 characters">
            <div class="form-text" id="pwHint">Minimum 6 characters. The user can change it later.</div>
          </div>
          <div class="alert alert-info py-2 px-3 small mt-3 mb-0">
            <i class="bi bi-shield-check"></i>
            <strong>Admin</strong> can manage users, settings and all inventory.
            <strong>Staff</strong> can record stock movements and view reports only.
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-erp-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-erp-primary">Save User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Reset password modal -->
<div class="modal fade" id="resetModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content" style="border-radius:16px;border:none;">
      <form method="post" action="<?= e(base_url('users/save.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="id" id="r-id" value="">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold">Reset Password</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="small text-muted">Set a new password for <strong id="r-name">this user</strong>.</p>
          <label class="form-label-erp form-label">New Password <span class="text-danger">*</span></label>
          <input type="text" name="password" id="r-password" class="form-control form-control-erp" required minlength="6" placeholder="At least 6 characters">
          <div class="form-text">Shown in plain text so you can pass it to the user, then it is stored hashed.</div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-erp-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-erp-primary">Reset</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
