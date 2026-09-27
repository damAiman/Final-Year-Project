<?php
require_once __DIR__ . '/config/bootstrap.php';
require_admin();

$db = getDB();

$stmt = $db->query(
    "SELECT p.*,
            COALESCE(c.name,'Uncategorised') AS category_name,
            COALESCE(s.name,'—')            AS supplier_name
       FROM products p
       LEFT JOIN categories c ON c.id = p.category_id
       LEFT JOIN suppliers  s ON s.id = p.supplier_id
      WHERE p.current_stock < p.min_stock * 1.5
      ORDER BY (p.current_stock / GREATEST(p.min_stock,1)) ASC"
);
$alerts = $stmt->fetchAll();

$countOut = $countCritical = $countLow = 0;
foreach ($alerts as $a) {
    $st = stock_status((int) $a['current_stock'], (int) $a['min_stock']);
    if ($st['class'] === 'out')            { $countOut++; }
    elseif ($st['class'] === 'critical')   { $countCritical++; }
    else                                   { $countLow++; }
}

/* Value needed to bring every flagged product back up to its minimum level. */
$restockCost = 0.0;
foreach ($alerts as $a) {
    $shortfall = max(0, (int) $a['min_stock'] - (int) $a['current_stock']);
    $restockCost += $shortfall * (float) $a['price_per_unit'];
}

$pageTitle = 'Low Stock Alerts';
$pageCrumb = count($alerts) . ' product(s) need attention';
$activeNav = 'lowstock';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Summary banner -->
<?php if (empty($alerts)): ?>
  <div class="alert-banner ok mb-3">
    <div class="ab-icon"><i class="bi bi-check-lg"></i></div>
    <div>
      <h4>All products are within healthy stock levels</h4>
      <p>No product is currently below its minimum stock threshold.</p>
    </div>
  </div>
<?php else: ?>
  <div class="alert-banner mb-3">
    <div class="ab-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
    <div>
      <h4><?= count($alerts) ?> product<?= count($alerts) > 1 ? 's' : '' ?> need attention</h4>
      <p>
        <?= $countOut ?> out of stock &middot; <?= $countCritical ?> critical &middot; <?= $countLow ?> running low.
        Estimated restock cost to reach minimum levels: <strong>RM <?= e(number_format($restockCost, 2)) ?></strong>.
      </p>
    </div>
  </div>
<?php endif; ?>

<!-- Alert count cards -->
<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3">
    <div class="kpi-card h-100">
      <div class="d-flex justify-content-between align-items-start">
        <div class="icon" style="background:var(--red-bg);"><i class="bi bi-x-circle" style="color:var(--red);"></i></div>
      </div>
      <div class="value"><?= fmt_num($countOut) ?></div>
      <div class="label">Out of Stock</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="kpi-card h-100">
      <div class="d-flex justify-content-between align-items-start">
        <div class="icon" style="background:var(--red-bg);"><i class="bi bi-exclamation-octagon" style="color:var(--red);"></i></div>
      </div>
      <div class="value"><?= fmt_num($countCritical) ?></div>
      <div class="label">Critical</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="kpi-card h-100">
      <div class="d-flex justify-content-between align-items-start">
        <div class="icon" style="background:var(--amber-bg);"><i class="bi bi-exclamation-triangle" style="color:var(--amber);"></i></div>
      </div>
      <div class="value"><?= fmt_num($countLow) ?></div>
      <div class="label">Low Stock</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="kpi-card h-100">
      <div class="d-flex justify-content-between align-items-start">
        <div class="icon" style="background:var(--blue-100);"><i class="bi bi-cash-stack" style="color:var(--blue-600);"></i></div>
      </div>
      <div class="value" style="font-size:19px;">RM <?= e(number_format($restockCost, 2)) ?></div>
      <div class="label">Estimated Restock Cost</div>
    </div>
  </div>
</div>

<!-- Alert table -->
<div class="card-erp">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
      <h3 class="h6 fw-bold mb-1">Products below minimum stock</h3>
      <p class="text-muted small mb-0">Sorted by urgency — the most depleted products appear first.</p>
    </div>
    <a href="<?= e(base_url('reports/generate.php?type=low_stock&format=pdf')) ?>" target="_blank" class="btn btn-sm btn-erp-outline">
      <i class="bi bi-printer"></i> Print alert list
    </a>
  </div>

  <div class="table-responsive">
    <table class="table table-erp align-middle">
      <thead>
        <tr>
          <th>Image</th>
          <th>Product Name</th>
          <th>Category</th>
          <th>Supplier</th>
          <th>Current Stock</th>
          <th>Minimum Stock</th>
          <th>Shortfall</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($alerts)): ?>
          <tr><td colspan="9" class="text-center text-muted py-5">
            <i class="bi bi-check-circle" style="font-size:26px;color:var(--green);"></i>
            <div class="mt-2">Nothing to restock right now.</div>
          </td></tr>
        <?php endif; ?>
        <?php foreach ($alerts as $p):
          $st = stock_status((int) $p['current_stock'], (int) $p['min_stock']);
          $shortfall = max(0, (int) $p['min_stock'] - (int) $p['current_stock']);
          $pct = (int) $p['min_stock'] > 0
              ? min(100, round(((int) $p['current_stock'] / (int) $p['min_stock']) * 100))
              : 100;
        ?>
          <tr>
            <td><img src="<?= e(product_image_url($p['image'], $p['category_name'])) ?>" class="prod-thumb" alt="<?= e($p['name']) ?>"></td>
            <td>
              <div class="fw-semibold"><?= e($p['name']) ?></div>
              <div class="small text-muted mono"><?= e($p['sku']) ?></div>
            </td>
            <td><span class="badge-status instock" style="color:var(--blue-600);background:var(--blue-100);"><?= e($p['category_name']) ?></span></td>
            <td class="small"><?= e($p['supplier_name']) ?></td>
            <td>
              <div class="mono fw-semibold"><?= fmt_num($p['current_stock']) ?> <span class="text-muted small fw-normal"><?= e($p['unit']) ?></span></div>
              <div class="stock-bar mt-1"><span style="width:<?= $pct ?>%;background:<?= $st['class'] === 'low' ? 'var(--amber)' : 'var(--red)' ?>;"></span></div>
            </td>
            <td class="mono text-muted"><?= fmt_num($p['min_stock']) ?> <span class="small"><?= e($p['unit']) ?></span></td>
            <td class="mono fw-semibold" style="color:var(--red);">
              <?= $shortfall > 0 ? '-' . fmt_num($shortfall) : '—' ?>
            </td>
            <td><span class="badge-status <?= e($st['class']) ?>"><?= e($st['label']) ?></span></td>
            <td class="text-nowrap">
              <a href="<?= e(base_url('stock_in.php?product_id=' . (int) $p['id'])) ?>" class="btn btn-sm btn-erp-primary">
                <i class="bi bi-box-arrow-in-down"></i> Restock
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
