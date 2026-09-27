<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_admin();

$pageTitle = 'Reports';
$pageCrumb = 'Generate, preview, print and export inventory reports';
$activeNav = 'reports';

/* No default date range anymore -- stays empty until the user picks one. */
$from = trim($_GET['from'] ?? '');
$to   = trim($_GET['to'] ?? '');
$hasRange = ($from !== '' && $to !== '');

$reports = [
    [
        'type'  => 'inventory',
        'title' => 'Inventory Report',
        'desc'  => 'Full snapshot of every product with stock levels, prices and total stock value.',
        'icon'  => 'bi-box-seam',
        'bg'    => 'var(--blue-100)',
        'color' => 'var(--blue-600)',
        'dated' => false,
    ],
    [
        'type'  => 'stock_in',
        'title' => 'Stock In Report',
        'desc'  => 'All incoming stock transactions for the selected period, with staff and remarks.',
        'icon'  => 'bi-box-arrow-in-down',
        'bg'    => 'var(--green-bg)',
        'color' => 'var(--green)',
        'dated' => true,
    ],
    [
        'type'  => 'stock_out',
        'title' => 'Stock Out Report',
        'desc'  => 'All outgoing stock transactions for the period, valued at selling price.',
        'icon'  => 'bi-box-arrow-up',
        'bg'    => 'var(--red-bg)',
        'color' => 'var(--red)',
        'dated' => true,
    ],
    [
        'type'  => 'low_stock',
        'title' => 'Low Stock Report',
        'desc'  => 'Products below minimum stock, with shortfall quantities and restock cost.',
        'icon'  => 'bi-exclamation-triangle',
        'bg'    => 'var(--amber-bg)',
        'color' => 'var(--amber)',
        'dated' => false,
    ],
    [
        'type'  => 'category',
        'title' => 'Category Report',
        'desc'  => 'Stock and value grouped by product category, with share of total inventory.',
        'icon'  => 'bi-diagram-3',
        'bg'    => '#EFE7FC',
        'color' => '#7C4DDB',
        'dated' => false,
    ],
];

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Date range applies to the transaction-based reports -->
<div class="card-erp mb-3">
  <form method="get" class="row g-3 align-items-end">
    <div class="col-md-3">
      <label class="form-label-erp form-label">From Date</label>
      <input type="date" name="from" class="form-control form-control-erp" value="<?= e($from) ?>" max="<?= e(date('Y-m-d')) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label-erp form-label">To Date</label>
      <input type="date" name="to" class="form-control form-control-erp" value="<?= e($to) ?>" max="<?= e(date('Y-m-d')) ?>">
    </div>
    <div class="col-md-6">
      <button type="submit" class="btn btn-erp-primary"><i class="bi bi-funnel"></i> Apply date range</button>
      <a href="<?= e(base_url('reports/index.php')) ?>" class="btn btn-erp-outline">Reset</a>
      <span class="small text-muted ms-2 d-block d-md-inline mt-2 mt-md-0">
        Applies to Stock In &amp; Stock Out reports.
      </span>
    </div>
  </form>
</div>

<div class="row g-3">
  <?php foreach ($reports as $r):
    $qs = 'type=' . $r['type'] . (($r['dated'] && $hasRange) ? '&from=' . urlencode($from) . '&to=' . urlencode($to) : '');
  ?>
    <div class="col-md-6 col-xl-4">
      <div class="report-card h-100">
        <div class="report-icon" style="background:<?= e($r['bg']) ?>;color:<?= e($r['color']) ?>;">
          <i class="bi <?= e($r['icon']) ?>"></i>
        </div>
        <h3><?= e($r['title']) ?></h3>
        <p><?= e($r['desc']) ?></p>
        <div class="report-meta">
          <?php if ($r['dated']): ?>
            <?php if ($hasRange): ?>
              <i class="bi bi-calendar3"></i> <?= e(date('d M Y', strtotime($from))) ?> &ndash; <?= e(date('d M Y', strtotime($to))) ?>
            <?php else: ?>
              <i class="bi bi-calendar3"></i> No date selected
            <?php endif; ?>
          <?php else: ?>
            <i class="bi bi-lightning-charge"></i> Live snapshot
          <?php endif; ?>
        </div>
        <div class="report-actions">
          <a href="<?= e(base_url('reports/generate.php?' . $qs . '&format=html')) ?>" target="_blank" class="btn btn-sm btn-erp-primary">
            <i class="bi bi-eye"></i> Preview
          </a>
          <a href="<?= e(base_url('reports/generate.php?' . $qs . '&format=pdf')) ?>" target="_blank" class="btn btn-sm btn-erp-outline">
            <i class="bi bi-printer"></i> Print / PDF
          </a>
          <a href="<?= e(base_url('reports/generate.php?' . $qs . '&format=excel')) ?>" class="btn btn-sm btn-erp-outline">
            <i class="bi bi-file-earmark-spreadsheet"></i> Excel
          </a>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>