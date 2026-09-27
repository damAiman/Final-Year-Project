<?php
require_once __DIR__ . '/config/bootstrap.php';
require_login();

$db = getDB();

/* ---------------- KPI figures ---------------- */
$totalProducts = (int) $db->query('SELECT COUNT(*) FROM products')->fetchColumn();
$currentStock  = (int) $db->query('SELECT COALESCE(SUM(current_stock),0) FROM products')->fetchColumn();

$todayIn = (int) $db->query("SELECT COALESCE(SUM(quantity),0) FROM stock_in WHERE transaction_date = CURDATE()")->fetchColumn();
$todayOut = (int) $db->query("SELECT COALESCE(SUM(quantity),0) FROM stock_out WHERE transaction_date = CURDATE()")->fetchColumn();

$lowStockCount = (int) $db->query(
    'SELECT COUNT(*) FROM products WHERE current_stock < min_stock * 1.5'
)->fetchColumn();

/* ---------------- Financial totals ----------------*/
$inventoryValue = (float) $db->query(
    'SELECT COALESCE(SUM(current_stock * price_per_unit),0) FROM products'
)->fetchColumn();

$retailValue = (float) $db->query(
    'SELECT COALESCE(SUM(current_stock * selling_price),0) FROM products'
)->fetchColumn();

$totalSales = (float) $db->query(
    'SELECT COALESCE(SUM(so.quantity * p.selling_price),0)
       FROM stock_out so JOIN products p ON p.id = so.product_id'
)->fetchColumn();

$totalCogs = (float) $db->query(
    'SELECT COALESCE(SUM(so.quantity * p.price_per_unit),0)
       FROM stock_out so JOIN products p ON p.id = so.product_id'
)->fetchColumn();

$totalProfit = $totalSales - $totalCogs;

$monthSales = (float) $db->query(
    "SELECT COALESCE(SUM(so.quantity * p.selling_price),0)
       FROM stock_out so JOIN products p ON p.id = so.product_id
      WHERE DATE_FORMAT(so.transaction_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')"
)->fetchColumn();

$todaySales = (float) $db->query(
    'SELECT COALESCE(SUM(so.quantity * p.selling_price),0)
       FROM stock_out so JOIN products p ON p.id = so.product_id
      WHERE so.transaction_date = CURDATE()'
)->fetchColumn();

/* ---------------- Chart: monthly stock in vs stock out (last 6 months) ---------------- */
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $months[date('Y-m', strtotime("-$i months"))] = date('M', strtotime("-$i months"));
}

$monthlyIn = array_fill_keys(array_keys($months), 0);
$stmt = $db->query(
    "SELECT DATE_FORMAT(transaction_date, '%Y-%m') ym, SUM(quantity) qty FROM stock_in
     WHERE transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY ym"
);
foreach ($stmt->fetchAll() as $row) {
    if (isset($monthlyIn[$row['ym']])) {
        $monthlyIn[$row['ym']] = (int) $row['qty'];
    }
}

$monthlyOut = array_fill_keys(array_keys($months), 0);
$stmt = $db->query(
    "SELECT DATE_FORMAT(transaction_date, '%Y-%m') ym, SUM(quantity) qty FROM stock_out
     WHERE transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY ym"
);
foreach ($stmt->fetchAll() as $row) {
    if (isset($monthlyOut[$row['ym']])) {
        $monthlyOut[$row['ym']] = (int) $row['qty'];
    }
}

$chartMonthLabels = array_values($months);
$chartMonthlyIn   = array_values($monthlyIn);
$chartMonthlyOut  = array_values($monthlyOut);

/* ---------------- Chart: inventory by category ---------------- */
$stmt = $db->query(
    "SELECT COALESCE(c.name, 'Uncategorised') AS category, COALESCE(SUM(p.current_stock),0) AS total
     FROM products p LEFT JOIN categories c ON c.id = p.category_id
     GROUP BY category ORDER BY total DESC"
);
$categoryRows = $stmt->fetchAll();
$chartCategoryLabels = array_column($categoryRows, 'category');
$chartCategoryData   = array_map('intval', array_column($categoryRows, 'total'));

/* ---------------- Chart: best selling products (top 6 by stock-out qty) ---------------- */
$stmt = $db->query(
    "SELECT p.name, SUM(so.quantity) AS sold FROM stock_out so
     JOIN products p ON p.id = so.product_id
     GROUP BY p.id ORDER BY sold DESC LIMIT 6"
);
$bestSellingRows = $stmt->fetchAll();
$chartBestLabels = array_column($bestSellingRows, 'name');
$chartBestData   = array_map('intval', array_column($bestSellingRows, 'sold'));

/* ---------------- Chart: monthly inventory movement (running net level) ---------------- */
$netSeries = [];
$runningNet = 0;
foreach (array_keys($months) as $ym) {
    $runningNet += $monthlyIn[$ym] - $monthlyOut[$ym];
    $netSeries[] = $runningNet;
}

/* ---------------- Chart: top restocked products (top 6 by stock-in qty) ---------------- */
$stmt = $db->query(
    "SELECT p.name, SUM(si.quantity) AS received FROM stock_in si
     JOIN products p ON p.id = si.product_id
     GROUP BY p.id ORDER BY received DESC LIMIT 6"
);
$restockRows = $stmt->fetchAll();
$chartRestockLabels = array_column($restockRows, 'name');
$chartRestockData   = array_map('intval', array_column($restockRows, 'received'));

/* ---------------- Real-time sales monitoring ---------------- */
$weekSales = (float) $db->query(
    'SELECT COALESCE(SUM(so.quantity * p.selling_price),0)
       FROM stock_out so JOIN products p ON p.id = so.product_id
      WHERE so.transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)'
)->fetchColumn();

$weekUnits = (int) $db->query(
    'SELECT COALESCE(SUM(quantity),0) FROM stock_out
      WHERE transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)'
)->fetchColumn();

/* Sales trend: value sold on each of the last 7 days */
$trendLabels = [];
$trendData   = [];
$salesByDay  = [];
$stmt = $db->query(
    "SELECT so.transaction_date AS d, COALESCE(SUM(so.quantity * p.selling_price),0) AS val
       FROM stock_out so JOIN products p ON p.id = so.product_id
      WHERE so.transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
      GROUP BY so.transaction_date"
);
foreach ($stmt->fetchAll() as $r) {
    $salesByDay[$r['d']] = (float) $r['val'];
}
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $trendLabels[] = date('D', strtotime($day));
    $trendData[]   = round($salesByDay[$day] ?? 0, 2);
}

/* Top selling products this month, valued at selling price */
$topSellers = $db->query(
    "SELECT p.name, p.unit, p.image,
            COALESCE(c.name,'Uncategorised') AS category_name,
            SUM(so.quantity) AS qty,
            SUM(so.quantity * p.selling_price) AS revenue
       FROM stock_out so
       JOIN products p        ON p.id = so.product_id
       LEFT JOIN categories c ON c.id = p.category_id
      WHERE DATE_FORMAT(so.transaction_date,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')
      GROUP BY p.id, p.name, p.unit, p.image, c.name
      ORDER BY revenue DESC
      LIMIT 5"
)->fetchAll();

/* ---------------- Recent transactions (stock in + stock out, combined) ---------------- */
$stmt = $db->query(
    "SELECT 'in' AS type, si.transaction_date, si.created_at, p.name AS product, si.quantity, u.name AS staff, si.remarks
       FROM stock_in si
       JOIN products p ON p.id = si.product_id
       LEFT JOIN users u ON u.id = si.user_id
     UNION ALL
     SELECT 'out' AS type, so.transaction_date, so.created_at, p.name AS product, so.quantity, u.name AS staff, so.remarks
       FROM stock_out so
       JOIN products p ON p.id = so.product_id
       LEFT JOIN users u ON u.id = so.user_id
     ORDER BY created_at DESC
     LIMIT 8"
);
$recentTransactions = $stmt->fetchAll();

/* ---------------- Low stock products table ---------------- */
$stmt = $db->query(
    "SELECT p.*, COALESCE(c.name,'Uncategorised') AS category_name
     FROM products p LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.current_stock < p.min_stock * 1.5
     ORDER BY (p.current_stock / GREATEST(p.min_stock,1)) ASC
     LIMIT 8"
);
$lowStockProducts = $stmt->fetchAll();

$pageTitle = 'Dashboard';
$pageCrumb = 'Overview of stock activity today, ' . date('d M Y');
$activeNav = 'dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<?php if (is_admin()): /* ADMIN SAHAJA - mula */ ?>

<?php if (!empty($lowStockProducts)): ?>
<!-- Low stock notification cards -->
<div class="notif-cards mb-3">
  <div class="notif-cards-head">
    <div>
      <h3 class="h6 fw-bold mb-1">
        <i class="bi bi-exclamation-triangle-fill" style="color:var(--red);"></i>
        Low Stock Alerts
        <span class="notif-count-badge ms-1"><?= (int) $lowStockCount ?></span>
      </h3>
      <p class="text-muted small mb-0">These products have fallen below their minimum stock level.</p>
    </div>
    <a href="<?= e(base_url('low_stock.php')) ?>" class="btn btn-sm btn-erp-outline">
      View all alerts <i class="bi bi-arrow-right"></i>
    </a>
  </div>
  <div class="row g-2">
    <?php foreach (array_slice($lowStockProducts, 0, 4) as $p):
      $st = stock_status((int) $p['current_stock'], (int) $p['min_stock']);
      $shortfall = max(0, (int) $p['min_stock'] - (int) $p['current_stock']);
    ?>
      <div class="col-md-6 col-xl-3">
        <a href="<?= e(base_url('stock_in.php?product_id=' . (int) $p['id'])) ?>" class="alert-mini <?= e($st['class']) ?>">
          <div class="am-top">
            <span class="badge-status <?= e($st['class']) ?>"><?= e($st['label']) ?></span>
            <i class="bi bi-box-arrow-in-down am-action" title="Restock"></i>
          </div>
          <div class="am-name"><?= e($p['name']) ?></div>
          <div class="am-meta">
            <?= fmt_num($p['current_stock']) ?> / <?= fmt_num($p['min_stock']) ?> <?= e($p['unit']) ?>
            <?php if ($shortfall > 0): ?>
              <span class="am-short">short by <?= fmt_num($shortfall) ?></span>
            <?php endif; ?>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- KPI cards -->
<div class="row g-3 mb-3">
  <div class="col-6 col-lg">
    <div class="kpi-card h-100">
      <div class="d-flex justify-content-between align-items-start">
        <div class="icon" style="background:var(--blue-100);"><i class="bi bi-box-seam" style="color:var(--blue-600);"></i></div>
      </div>
      <div class="value"><?= fmt_num($totalProducts) ?></div>
      <div class="label">Total Products</div>
    </div>
  </div>
  <div class="col-6 col-lg">
    <div class="kpi-card h-100">
      <div class="d-flex justify-content-between align-items-start">
        <div class="icon" style="background:var(--green-bg);"><i class="bi bi-clipboard-data" style="color:var(--green);"></i></div>
      </div>
      <div class="value"><?= fmt_num($currentStock) ?></div>
      <div class="label">Current Stock (units)</div>
    </div>
  </div>
  <div class="col-6 col-lg">
    <div class="kpi-card h-100">
      <div class="d-flex justify-content-between align-items-start">
        <div class="icon" style="background:var(--green-bg);"><i class="bi bi-box-arrow-in-down" style="color:var(--green);"></i></div>
      </div>
      <div class="value"><?= fmt_num($todayIn) ?></div>
      <div class="label">Today's Stock In</div>
    </div>
  </div>
  <div class="col-6 col-lg">
    <div class="kpi-card h-100">
      <div class="d-flex justify-content-between align-items-start">
        <div class="icon" style="background:var(--red-bg);"><i class="bi bi-box-arrow-up" style="color:var(--red);"></i></div>
      </div>
      <div class="value"><?= fmt_num($todayOut) ?></div>
      <div class="label">Today's Stock Out</div>
    </div>
  </div>
  <div class="col-6 col-lg">
    <div class="kpi-card h-100">
      <div class="d-flex justify-content-between align-items-start">
        <div class="icon" style="background:var(--amber-bg);"><i class="bi bi-exclamation-triangle" style="color:var(--amber);"></i></div>
      </div>
      <div class="value"><?= fmt_num($lowStockCount) ?></div>
      <div class="label">Low Stock Products</div>
    </div>
  </div>
</div>

<!-- Financial totals -->
<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="card-erp h-100">
      <h3 class="h6 fw-bold mb-1">Sales Summary</h3>
      <p class="text-muted small mb-3">Calculated from every Stock Out transaction, priced at each product's selling price.</p>
      <div class="row g-3">
        <div class="col-md-4">
          <div class="total-tile sales">
            <div class="tl">Total Sales</div>
            <div class="tv">RM <?= e(number_format($totalSales, 2)) ?></div>
            <div class="ts">All time</div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="total-tile cost">
            <div class="tl">Cost of Goods Sold</div>
            <div class="tv">RM <?= e(number_format($totalCogs, 2)) ?></div>
            <div class="ts">All time</div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="total-tile <?= $totalProfit >= 0 ? 'profit' : 'loss' ?>">
            <div class="tl">Gross Profit</div>
            <div class="tv">RM <?= e(number_format($totalProfit, 2)) ?></div>
            <div class="ts">
              <?php $marginPct = $totalSales > 0 ? ($totalProfit / $totalSales) * 100 : 0; ?>
              <?= e(number_format($marginPct, 1)) ?>% margin
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="total-mini">
            <span class="ml">Sales this month</span>
            <span class="mv">RM <?= e(number_format($monthSales, 2)) ?></span>
          </div>
        </div>
        <div class="col-md-6">
          <div class="total-mini">
            <span class="ml">Sales today</span>
            <span class="mv">RM <?= e(number_format($todaySales, 2)) ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card-erp h-100">
      <h3 class="h6 fw-bold mb-1">Inventory Value</h3>
      <p class="text-muted small mb-3">Value of stock currently on hand.</p>
      <div class="total-tile stock mb-3">
        <div class="tl">At Cost Price</div>
        <div class="tv">RM <?= e(number_format($inventoryValue, 2)) ?></div>
        <div class="ts"><?= fmt_num($currentStock) ?> units in stock</div>
      </div>
      <div class="total-tile retail">
        <div class="tl">At Selling Price</div>
        <div class="tv">RM <?= e(number_format($retailValue, 2)) ?></div>
        <div class="ts">Potential revenue if all sold</div>
      </div>
    </div>
  </div>
</div>

<!-- Real-time Sales Monitoring -->
<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="card-erp h-100">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-1">
        <div>
          <h3 class="h6 fw-bold mb-1">Real-Time Sales Monitoring</h3>
          <p class="text-muted small mb-0">Every Stock Out transaction valued at its product's selling price.</p>
        </div>
        <span class="live-pill"><span class="live-dot"></span> Live from MySQL</span>
      </div>
      <div class="row g-3 mt-1 mb-3">
        <div class="col-md-4">
          <div class="total-tile sales">
            <div class="tl">Today's Sales</div>
            <div class="tv">RM <?= e(number_format($todaySales, 2)) ?></div>
            <div class="ts"><?= fmt_num($todayOut) ?> units out today</div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="total-tile stock">
            <div class="tl">Weekly Sales</div>
            <div class="tv">RM <?= e(number_format($weekSales, 2)) ?></div>
            <div class="ts"><?= fmt_num($weekUnits) ?> units · last 7 days</div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="total-tile profit">
            <div class="tl">Monthly Sales</div>
            <div class="tv">RM <?= e(number_format($monthSales, 2)) ?></div>
            <div class="ts"><?= e(date('F Y')) ?></div>
          </div>
        </div>
      </div>
      <h4 class="small fw-bold text-muted text-uppercase mb-2" style="letter-spacing:.5px;font-size:11px;">Sales Trend — Last 7 Days</h4>
      <canvas id="chartSalesTrend" height="90"></canvas>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card-erp h-100">
      <h3 class="h6 fw-bold mb-1">Top Selling Products</h3>
      <p class="text-muted small mb-3">By revenue this month.</p>
      <?php if (empty($topSellers)): ?>
        <p class="text-muted small mb-0">No sales recorded this month yet.</p>
      <?php else: ?>
        <?php foreach ($topSellers as $i => $ts): ?>
          <div class="seller-row">
            <span class="seller-rank"><?= $i + 1 ?></span>
            <img src="<?= e(product_image_url($ts['image'], $ts['category_name'])) ?>" class="prod-thumb-sm" alt="">
            <div class="flex-grow-1 min-w-0">
              <div class="fw-semibold small text-truncate"><?= e($ts['name']) ?></div>
              <div class="text-muted" style="font-size:11px;"><?= fmt_num($ts['qty']) ?> <?= e($ts['unit']) ?> sold</div>
            </div>
            <div class="mono fw-bold small" style="color:var(--green);">RM <?= e(number_format((float) $ts['revenue'], 2)) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php endif; /* ADMIN SAHAJA - tamat */ ?>

<!-- Charts -->
<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="card-erp h-100">
      <h3 class="h6 fw-bold mb-3">Monthly Stock In vs Stock Out</h3>
      <canvas id="chartMonthly" height="110"></canvas>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card-erp h-100">
      <h3 class="h6 fw-bold mb-3">Inventory by Category</h3>
      <canvas id="chartCategory" height="180"></canvas>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <div class="card-erp h-100">
      <h3 class="h6 fw-bold mb-1">Monthly Inventory Movement</h3>
      <p class="text-muted small mb-3">Running net stock level over the last 6 months.</p>
      <canvas id="chartNetMovement" height="130"></canvas>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card-erp h-100">
      <h3 class="h6 fw-bold mb-1">Top Restocked Products</h3>
      <p class="text-muted small mb-3">Highest incoming volume recorded.</p>
      <?php if (empty($chartRestockLabels)): ?>
        <p class="text-muted small mb-0">No stock-in transactions recorded yet.</p>
      <?php else: ?>
        <canvas id="chartRestocked" height="150"></canvas>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-12">
    <div class="card-erp">
      <h3 class="h6 fw-bold mb-3">Best Selling Products</h3>
      <?php if (empty($chartBestLabels)): ?>
        <p class="text-muted small mb-0">No stock-out transactions recorded yet.</p>
      <?php else: ?>
        <canvas id="chartBestSelling" height="90"></canvas>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Tables -->
<div class="row g-3">
  <div class="col-lg-7">
    <div class="card-erp h-100">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="h6 fw-bold mb-0">Recent Transactions</h3>
      </div>
      <div class="table-responsive">
        <table class="table table-erp">
          <thead><tr><th>Date</th><th>Product</th><th>Type</th><th>Qty</th><th>Staff</th></tr></thead>
          <tbody>
            <?php if (empty($recentTransactions)): ?>
              <tr><td colspan="5" class="text-center text-muted py-4">No transactions recorded yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($recentTransactions as $t): ?>
              <tr>
                <td class="text-muted small mono"><?= e(date('d M Y', strtotime($t['transaction_date']))) ?></td>
                <td class="fw-semibold"><?= e($t['product']) ?></td>
                <td><span class="badge-type-<?= $t['type'] ?>"><?= $t['type'] === 'in' ? 'Stock In' : 'Stock Out' ?></span></td>
                <td class="mono"><?= $t['type'] === 'in' ? '+' : '-' ?><?= fmt_num($t['quantity']) ?></td>
                <td><?= e($t['staff'] ?? '—') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card-erp h-100">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="h6 fw-bold mb-0">Low Stock Products</h3>
        <?php if (is_admin()): ?>
        <a href="<?= e(base_url('products/index.php')) ?>" class="small fw-semibold" style="color:var(--blue-600);">View all</a>
        <?php endif; ?>
      </div>
      <div class="table-responsive">
        <table class="table table-erp">
          <thead><tr><th>Product</th><th>Stock</th><th>Status</th></tr></thead>
          <tbody>
            <?php if (empty($lowStockProducts)): ?>
              <tr><td colspan="3" class="text-center text-muted py-4">All products are within healthy stock levels.</td></tr>
            <?php endif; ?>
            <?php foreach ($lowStockProducts as $p): $status = stock_status((int) $p['current_stock'], (int) $p['min_stock']); ?>
              <tr>
                <td>
                  <div class="fw-semibold"><?= e($p['name']) ?></div>
                  <div class="small text-muted"><?= e($p['category_name']) ?></div>
                </td>
                <td class="mono"><?= fmt_num($p['current_stock']) ?> <span class="text-muted small"><?= e($p['unit']) ?></span></td>
                <td><span class="badge-status <?= e($status['class']) ?>"><?= e($status['label']) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php
$inlineFooterScript = 'const CHART_MONTH_LABELS = ' . json_encode($chartMonthLabels) . ';'
    . 'const CHART_MONTHLY_IN = ' . json_encode($chartMonthlyIn) . ';'
    . 'const CHART_MONTHLY_OUT = ' . json_encode($chartMonthlyOut) . ';'
    . 'const CHART_CATEGORY_LABELS = ' . json_encode($chartCategoryLabels) . ';'
    . 'const CHART_CATEGORY_DATA = ' . json_encode($chartCategoryData) . ';'
    . 'const CHART_BEST_LABELS = ' . json_encode($chartBestLabels) . ';'
    . 'const CHART_BEST_DATA = ' . json_encode($chartBestData) . ';'
    . 'const CHART_NET_SERIES = ' . json_encode($netSeries) . ';'
    . 'const CHART_RESTOCK_LABELS = ' . json_encode($chartRestockLabels) . ';'
    . 'const CHART_RESTOCK_DATA = ' . json_encode($chartRestockData) . ';';

if (is_admin()) {
    $inlineFooterScript .= 'const CHART_TREND_LABELS = ' . json_encode($trendLabels) . ';'
        . 'const CHART_TREND_DATA = ' . json_encode($trendData) . ';';
}

$pageScripts = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js',
    'assets/js/charts.js',
];
require_once __DIR__ . '/includes/footer.php';
?>
