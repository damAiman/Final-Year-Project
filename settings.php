<?php
require_once __DIR__ . '/config/bootstrap.php';
require_role('admin');

$s = all_settings();

$db = getDB();
$dbStats = [
  'products'   => (int) $db->query('SELECT COUNT(*) FROM products')->fetchColumn(),
  'stock_in'   => (int) $db->query('SELECT COUNT(*) FROM stock_in')->fetchColumn(),
  'stock_out'  => (int) $db->query('SELECT COUNT(*) FROM stock_out')->fetchColumn(),
  'users'      => (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn(),
];

$pageTitle = 'System Settings';
$pageCrumb = 'Company profile, notifications, backup and appearance';
$activeNav = 'settings';

require_once __DIR__ . '/includes/header.php';
?>

<ul class="nav nav-pills settings-tabs mb-3" role="tablist">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-company" type="button"><i class="bi bi-building"></i> Company Profile</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-notify" type="button"><i class="bi bi-bell"></i> Notifications</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-appearance" type="button"><i class="bi bi-palette"></i> Appearance &amp; Language</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-backup" type="button"><i class="bi bi-database"></i> Backup &amp; Restore</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-security" type="button"><i class="bi bi-shield-lock"></i> Security</button></li>
</ul>

<div class="tab-content">

  <!-- ============ COMPANY PROFILE ============ -->
  <div class="tab-pane fade show active" id="tab-company">
    <form method="post" action="<?= e(base_url('settings_save.php')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="section" value="company">
      <div class="row g-3">
        <div class="col-lg-4">
          <div class="card-erp h-100">
            <h3 class="h6 fw-bold mb-1">Company Logo</h3>
            <p class="text-muted small mb-3">Shown on printed reports and the login page.</p>
            <div class="logo-drop" id="logoDrop">
              <?php if (!empty($s['company_logo']) && file_exists(__DIR__ . '/uploads/' . $s['company_logo'])): ?>
                <img src="<?= e(base_url('uploads/' . rawurlencode($s['company_logo']))) ?>" id="logoPreview" alt="Company logo">
              <?php else: ?>
                <img src="" id="logoPreview" alt="" style="display:none;">
                <div class="logo-hint" id="logoHint">
                  <i class="bi bi-cloud-arrow-up" style="font-size:26px;color:var(--blue-600);"></i>
                  <div class="mt-2 small fw-semibold">Click to upload a logo</div>
                  <div class="text-muted" style="font-size:11px;">PNG or JPG, up to 2MB</div>
                </div>
              <?php endif; ?>
              <input type="file" name="logo" id="logoInput" accept="image/png,image/jpeg" style="display:none;">
            </div>
            <?php if (!empty($s['company_logo'])): ?>
              <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" name="remove_logo" id="removeLogo" value="1">
                <label class="form-check-label small" for="removeLogo">Remove the current logo</label>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-lg-8">
          <div class="card-erp h-100">
            <h3 class="h6 fw-bold mb-3">Company Details</h3>
            <div class="row g-3">
              <div class="col-md-7">
                <label class="form-label-erp form-label">Company Name <span class="text-danger">*</span></label>
                <input type="text" name="company_name" class="form-control form-control-erp" required value="<?= e($s['company_name']) ?>">
              </div>
              <div class="col-md-5">
                <label class="form-label-erp form-label">Registration No.</label>
                <input type="text" name="company_reg_no" class="form-control form-control-erp" value="<?= e($s['company_reg_no']) ?>">
              </div>
              <div class="col-12">
                <label class="form-label-erp form-label">Address</label>
                <textarea name="company_address" class="form-control form-control-erp" rows="2"><?= e($s['company_address']) ?></textarea>
              </div>
              <div class="col-md-6">
                <label class="form-label-erp form-label">Phone</label>
                <input type="text" name="company_phone" class="form-control form-control-erp" value="<?= e($s['company_phone']) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label-erp form-label">Email</label>
                <input type="email" name="company_email" class="form-control form-control-erp" value="<?= e($s['company_email']) ?>">
              </div>
            </div>
            <div class="text-end mt-3">
              <button class="btn btn-erp-primary" type="submit"><i class="bi bi-check-lg"></i> Save Company Profile</button>
            </div>
          </div>
        </div>
      </div>
    </form>
  </div>

  <!-- ============ NOTIFICATIONS ============ -->
  <div class="tab-pane fade" id="tab-notify">
    <form method="post" action="<?= e(base_url('settings_save.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="section" value="notifications">
      <div class="card-erp">
        <h3 class="h6 fw-bold mb-1">Notification Settings</h3>
        <p class="text-muted small mb-3">Choose which events create an entry in the notification centre.</p>

        <?php
        $notifyOptions = [
            ['notify_low_stock', 'Low Stock Alerts', 'Notify when a product falls below its minimum stock level.', 'bi-exclamation-triangle', 'var(--red)'],
            ['notify_stock_in',  'Successful Stock In', 'Notify when incoming stock is recorded.', 'bi-box-arrow-in-down', 'var(--green)'],
            ['notify_stock_out', 'Successful Stock Out', 'Notify when outgoing stock is recorded.', 'bi-box-arrow-up', 'var(--red)'],
            ['notify_reports',   'Report Generated', 'Notify when a report is generated or exported.', 'bi-file-earmark-text', 'var(--blue-600)'],
            ['notify_users',     'User Account Changes', 'Notify when a user account is created or updated.', 'bi-person-plus', 'var(--amber)'],
        ];
        foreach ($notifyOptions as [$key, $label, $desc, $icon, $color]): ?>
          <div class="setting-row">
            <div class="sr-icon" style="color:<?= e($color) ?>;"><i class="bi <?= e($icon) ?>"></i></div>
            <div class="flex-grow-1">
              <div class="sr-title"><?= e($label) ?></div>
              <div class="sr-desc"><?= e($desc) ?></div>
            </div>
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="<?= e($key) ?>" value="1" <?= ($s[$key] ?? '1') === '1' ? 'checked' : '' ?>>
            </div>
          </div>
        <?php endforeach; ?>

        <div class="text-end mt-3">
          <button class="btn btn-erp-primary" type="submit"><i class="bi bi-check-lg"></i> Save Notification Settings</button>
        </div>
      </div>
    </form>
  </div>

  <!-- ============ APPEARANCE & LANGUAGE ============ -->
  <div class="tab-pane fade" id="tab-appearance">
    <form method="post" action="<?= e(base_url('settings_save.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="section" value="appearance">
      <div class="card-erp">
        <h3 class="h6 fw-bold mb-1">Appearance &amp; Language</h3>
        <p class="text-muted small mb-3">Applies to everyone using this SMART STOCK installation.</p>

        <div class="setting-row">
          <div class="sr-icon" style="color:var(--navy-900);"><i class="bi bi-moon-stars"></i></div>
          <div class="flex-grow-1">
            <div class="sr-title">Dark Mode</div>
            <div class="sr-desc">Switch the interface to a dark colour scheme, easier on the eyes in low light.</div>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="dark_mode" id="darkToggle" value="1" <?= ($s['dark_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
          </div>
        </div>

        <div class="setting-row">
          <div class="sr-icon" style="color:var(--blue-600);"><i class="bi bi-translate"></i></div>
          <div class="flex-grow-1">
            <div class="sr-title">Language</div>
            <div class="sr-desc">Interface language for date formats and labels.</div>
          </div>
          <select name="language" class="form-select form-control-erp" style="width:190px;">
            <option value="en" <?= $s['language'] === 'en' ? 'selected' : '' ?>>English</option>
            <option value="ms" <?= $s['language'] === 'ms' ? 'selected' : '' ?>>Bahasa Melayu</option>
          </select>
        </div>

        <div class="alert alert-info py-2 px-3 small mt-3 mb-0">
          <i class="bi bi-info-circle"></i>
          Bahasa Melayu currently switches date and number formatting. Full interface translation
          is a natural next step beyond this project's scope.
        </div>

        <div class="text-end mt-3">
          <button class="btn btn-erp-primary" type="submit"><i class="bi bi-check-lg"></i> Save Appearance</button>
        </div>
      </div>
    </form>
  </div>

  <!-- ============ BACKUP & RESTORE ============ -->
  <div class="tab-pane fade" id="tab-backup">
    <div class="row g-3">
      <div class="col-lg-6">
        <div class="card-erp h-100">
          <h3 class="h6 fw-bold mb-1"><i class="bi bi-download" style="color:var(--green);"></i> Backup Database</h3>
          <p class="text-muted small mb-3">Download a complete <code>.sql</code> dump of the SMART STOCK database. Keep it somewhere safe.</p>

          <div class="db-stats mb-3">
            <div class="db-stat"><span class="n"><?= fmt_num($dbStats['products']) ?></span><span class="l">Products</span></div>
            <div class="db-stat"><span class="n"><?= fmt_num($dbStats['stock_in']) ?></span><span class="l">Stock In</span></div>
            <div class="db-stat"><span class="n"><?= fmt_num($dbStats['stock_out']) ?></span><span class="l">Stock Out</span></div>
            <div class="db-stat"><span class="n"><?= fmt_num($dbStats['users']) ?></span><span class="l">Users</span></div>
          </div>

          <a href="<?= e(base_url('backup.php')) ?>" class="btn btn-erp-primary w-100">
            <i class="bi bi-download"></i> Download Backup (.sql)
          </a>
          <div class="form-text mt-2">The file includes every table structure and all current data.</div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card-erp h-100">
          <h3 class="h6 fw-bold mb-1"><i class="bi bi-upload" style="color:var(--amber);"></i> Restore Database</h3>
          <p class="text-muted small mb-3">Upload a previously downloaded backup file to restore the system to that point in time.</p>

          <div class="alert alert-warning py-2 px-3 small">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <strong>This overwrites current data.</strong> Every product, transaction and user
            in the backup replaces what is in the database now. Take a fresh backup first.
          </div>

          <form method="post" action="<?= e(base_url('restore.php')) ?>" enctype="multipart/form-data"
                onsubmit="return confirm('Restore the database from this backup file?\n\nAll current data will be replaced. This cannot be undone.');">
            <?= csrf_field() ?>
            <input type="file" name="backup_file" class="form-control form-control-erp mb-2" accept=".sql" required>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="confirm" id="confirmRestore" value="1" required>
              <label class="form-check-label small" for="confirmRestore">
                I understand this will replace all current data.
              </label>
            </div>
            <button type="submit" class="btn btn-erp-danger w-100">
              <i class="bi bi-upload"></i> Restore From Backup
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- ============ SECURITY ============ -->
  <div class="tab-pane fade" id="tab-security">
    <form method="post" action="<?= e(base_url('settings_save.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="section" value="security">
      <div class="card-erp">
        <h3 class="h6 fw-bold mb-1">Security</h3>
        <p class="text-muted small mb-3">Protections that are always active are listed for reference.</p>

        <div class="setting-row">
          <div class="sr-icon" style="color:var(--blue-600);"><i class="bi bi-clock-history"></i></div>
          <div class="flex-grow-1">
            <div class="sr-title">Session Timeout</div>
            <div class="sr-desc">Automatically sign a user out after this many minutes of inactivity. Set 0 to disable.</div>
          </div>
          <div class="input-group" style="width:170px;">
            <input type="number" name="session_timeout_min" class="form-control form-control-erp" min="0" max="480"
                   value="<?= e($s['session_timeout_min']) ?>">
            <span class="input-group-text" style="border-radius:0 10px 10px 0;">min</span>
          </div>
        </div>

        <?php
        $alwaysOn = [
            ['Password Encryption', 'All passwords are hashed with bcrypt via password_hash(). Plain text passwords are never stored.', 'bi-key-fill'],
            ['SQL Injection Protection', 'Every database query uses PDO prepared statements with bound parameters.', 'bi-database-lock'],
            ['Role Based Access Control', 'User Management, Settings and the logs are restricted to Admin accounts on the server side.', 'bi-person-badge'],
            ['Input Validation', 'All form input is validated server-side, and all output is escaped to prevent XSS.', 'bi-check2-square'],
            ['CSRF Protection', 'State-changing forms carry a one-time token that is verified before the action runs.', 'bi-shield-check'],
            ['Upload Hardening', 'Uploaded images are verified by real file content, and PHP execution is blocked in the uploads folder.', 'bi-file-earmark-lock'],
        ];
        foreach ($alwaysOn as [$title, $desc, $icon]): ?>
          <div class="setting-row">
            <div class="sr-icon" style="color:var(--green);"><i class="bi <?= e($icon) ?>"></i></div>
            <div class="flex-grow-1">
              <div class="sr-title"><?= e($title) ?></div>
              <div class="sr-desc"><?= e($desc) ?></div>
            </div>
            <span class="badge-status instock"><i class="bi bi-check-lg"></i> Active</span>
          </div>
        <?php endforeach; ?>

        <div class="text-end mt-3">
          <button class="btn btn-erp-primary" type="submit"><i class="bi bi-check-lg"></i> Save Security Settings</button>
        </div>
      </div>
    </form>
  </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var drop = document.getElementById('logoDrop');
  var input = document.getElementById('logoInput');
  if (drop && input) {
    drop.addEventListener('click', function () { input.click(); });
    input.addEventListener('change', function (e) {
      if (!e.target.files[0]) return;
      var reader = new FileReader();
      reader.onload = function (ev) {
        var img = document.getElementById('logoPreview');
        img.src = ev.target.result;
        img.style.display = 'block';
        var hint = document.getElementById('logoHint');
        if (hint) hint.style.display = 'none';
      };
      reader.readAsDataURL(e.target.files[0]);
    });
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
