<?php

require_once __DIR__ . '/../config/bootstrap.php';
require_admin();

$db = getDB();

$type   = $_GET['type']   ?? 'inventory';
$format = $_GET['format'] ?? 'html';

$allowedTypes   = ['inventory', 'stock_in', 'stock_out', 'low_stock', 'category'];
$allowedFormats = ['html', 'pdf', 'excel'];
if (!in_array($type, $allowedTypes, true))     { $type = 'inventory'; }
if (!in_array($format, $allowedFormats, true)) { $format = 'html'; }

/* Date range, validated -- falls back to this month if malformed. */
function valid_date(?string $d, string $fallback): string {
    $dt = $d ? DateTime::createFromFormat('Y-m-d', $d) : false;
    return ($dt && $dt->format('Y-m-d') === $d) ? $d : $fallback;
}
$from = valid_date($_GET['from'] ?? null, date('Y-m-01'));
$to   = valid_date($_GET['to']   ?? null, date('Y-m-d'));
if ($from > $to) { [$from, $to] = [$to, $from]; }

$rangeLabel = date('d M Y', strtotime($from)) . ' – ' . date('d M Y', strtotime($to));

/* Build the report: $title, $subtitle, $columns, $rows, $totals*/
$columns = [];
$rows    = [];
$totals  = [];

switch ($type) {

    case 'stock_in':
        $title    = 'Stock In Report';
        $subtitle = 'Incoming stock transactions · ' . $rangeLabel;
        $columns  = ['Date', 'Product', 'SKU', 'Category', 'Supplier', 'Quantity', 'Unit', 'Entry', 'Staff', 'Remarks'];

        $stmt = $db->prepare(
            "SELECT si.transaction_date, si.quantity, si.remarks, si.entry_method,
                    p.name, p.sku, p.unit,
                    COALESCE(c.name,'Uncategorised') AS category_name,
                    COALESCE(s.name,'-')             AS supplier_name,
                    COALESCE(u.name,'-')             AS staff_name
               FROM stock_in si
               JOIN products p        ON p.id = si.product_id
               LEFT JOIN categories c ON c.id = p.category_id
               LEFT JOIN suppliers  s ON s.id = p.supplier_id
               LEFT JOIN users u      ON u.id = si.user_id
              WHERE si.transaction_date BETWEEN ? AND ?
              ORDER BY si.transaction_date DESC, si.id DESC"
        );
        $stmt->execute([$from, $to]);
        $data = $stmt->fetchAll();

        $totalQty = 0;
        foreach ($data as $r) {
            $totalQty += (int) $r['quantity'];
            $rows[] = [
                date('d M Y', strtotime($r['transaction_date'])),
                $r['name'],
                $r['sku'],
                $r['category_name'],
                $r['supplier_name'],
                number_format((int) $r['quantity']),
                $r['unit'],
                strtoupper($r['entry_method'] ?? 'manual'),
                $r['staff_name'],
                $r['remarks'] ?: '-',
            ];
        }
        $totals = [
            'Transactions'    => number_format(count($data)),
            'Total Units In'   => number_format($totalQty),
        ];
        break;

    case 'stock_out':
        $title    = 'Stock Out Report';
        $subtitle = 'Outgoing stock transactions · ' . $rangeLabel;
        $columns  = ['Date', 'Product', 'SKU', 'Category', 'Quantity', 'Unit', 'Unit Price (RM)', 'Value (RM)', 'Entry', 'Staff', 'Remarks'];

        $stmt = $db->prepare(
            "SELECT so.transaction_date, so.quantity, so.remarks, so.entry_method,
                    p.name, p.sku, p.unit, p.selling_price,
                    COALESCE(c.name,'Uncategorised') AS category_name,
                    COALESCE(u.name,'-')             AS staff_name
               FROM stock_out so
               JOIN products p        ON p.id = so.product_id
               LEFT JOIN categories c ON c.id = p.category_id
               LEFT JOIN users u      ON u.id = so.user_id
              WHERE so.transaction_date BETWEEN ? AND ?
              ORDER BY so.transaction_date DESC, so.id DESC"
        );
        $stmt->execute([$from, $to]);
        $data = $stmt->fetchAll();

        $totalQty = 0; $totalValue = 0.0;
        foreach ($data as $r) {
            $lineValue = (int) $r['quantity'] * (float) $r['selling_price'];
            $totalQty += (int) $r['quantity'];
            $totalValue += $lineValue;
            $rows[] = [
                date('d M Y', strtotime($r['transaction_date'])),
                $r['name'],
                $r['sku'],
                $r['category_name'],
                number_format((int) $r['quantity']),
                $r['unit'],
                number_format((float) $r['selling_price'], 2),
                number_format($lineValue, 2),
                strtoupper($r['entry_method'] ?? 'manual'),
                $r['staff_name'],
                $r['remarks'] ?: '-',
            ];
        }
        $totals = [
            'Transactions'    => number_format(count($data)),
            'Total Units Out' => number_format($totalQty),
            'Total Sales'     => 'RM ' . number_format($totalValue, 2),
        ];
        break;

    case 'low_stock':
        $title    = 'Low Stock Report';
        $subtitle = 'Products at or below their minimum stock level · as at ' . date('d M Y');
        $columns  = ['Product', 'SKU', 'Category', 'Supplier', 'Current Stock', 'Minimum Stock', 'Shortfall', 'Unit', 'Restock Cost (RM)', 'Status'];

        $data = $db->query(
            "SELECT p.*, COALESCE(c.name,'Uncategorised') AS category_name,
                    COALESCE(s.name,'-') AS supplier_name
               FROM products p
               LEFT JOIN categories c ON c.id = p.category_id
               LEFT JOIN suppliers  s ON s.id = p.supplier_id
              WHERE p.current_stock < p.min_stock * 1.5
              ORDER BY (p.current_stock / GREATEST(p.min_stock,1)) ASC"
        )->fetchAll();

        $totalCost = 0.0;
        foreach ($data as $r) {
            $shortfall = max(0, (int) $r['min_stock'] - (int) $r['current_stock']);
            $cost = $shortfall * (float) $r['price_per_unit'];
            $totalCost += $cost;
            $st = stock_status((int) $r['current_stock'], (int) $r['min_stock']);
            $rows[] = [
                $r['name'],
                $r['sku'],
                $r['category_name'],
                $r['supplier_name'],
                number_format((int) $r['current_stock']),
                number_format((int) $r['min_stock']),
                $shortfall > 0 ? number_format($shortfall) : '-',
                $r['unit'],
                number_format($cost, 2),
                $st['label'],
            ];
        }
        $totals = [
            'Products Flagged'        => number_format(count($data)),
            'Estimated Restock Cost'  => 'RM ' . number_format($totalCost, 2),
        ];
        break;

    case 'category':
        $title    = 'Category Report';
        $subtitle = 'Stock and value grouped by product category · as at ' . date('d M Y');
        $columns  = ['Category', 'Products', 'Total Stock', 'Stock Value (RM)', 'Retail Value (RM)', 'Share of Units'];

        $data = $db->query(
            "SELECT COALESCE(c.name,'Uncategorised') AS category_name,
                    COUNT(p.id) AS product_count,
                    COALESCE(SUM(p.current_stock),0) AS total_stock,
                    COALESCE(SUM(p.current_stock * p.price_per_unit),0) AS stock_value,
                    COALESCE(SUM(p.current_stock * p.selling_price),0)  AS retail_value
               FROM products p
               LEFT JOIN categories c ON c.id = p.category_id
              GROUP BY c.name
              ORDER BY total_stock DESC"
        )->fetchAll();

        $grandUnits = 0; $grandValue = 0.0; $grandRetail = 0.0; $grandProducts = 0;
        foreach ($data as $r) {
            $grandUnits    += (int) $r['total_stock'];
            $grandValue    += (float) $r['stock_value'];
            $grandRetail   += (float) $r['retail_value'];
            $grandProducts += (int) $r['product_count'];
        }
        foreach ($data as $r) {
            $share = $grandUnits > 0 ? ((int) $r['total_stock'] / $grandUnits) * 100 : 0;
            $rows[] = [
                $r['category_name'],
                number_format((int) $r['product_count']),
                number_format((int) $r['total_stock']),
                number_format((float) $r['stock_value'], 2),
                number_format((float) $r['retail_value'], 2),
                number_format($share, 1) . '%',
            ];
        }
        $totals = [
            'Categories'    => number_format(count($data)),
            'Total Products'=> number_format($grandProducts),
            'Total Units'   => number_format($grandUnits),
            'Stock Value'   => 'RM ' . number_format($grandValue, 2),
            'Retail Value'  => 'RM ' . number_format($grandRetail, 2),
        ];
        break;

    case 'inventory':
    default:
        $title    = 'Inventory Report';
        $subtitle = 'Complete product listing · as at ' . date('d M Y');
        $columns  = ['Product', 'SKU', 'Category', 'Supplier', 'Unit', 'Cost (RM)', 'Selling (RM)', 'Current Stock', 'Min Stock', 'Stock Value (RM)', 'Status'];

        $data = $db->query(
            "SELECT p.*, COALESCE(c.name,'Uncategorised') AS category_name,
                    COALESCE(s.name,'-') AS supplier_name
               FROM products p
               LEFT JOIN categories c ON c.id = p.category_id
               LEFT JOIN suppliers  s ON s.id = p.supplier_id
              ORDER BY c.name ASC, p.name ASC"
        )->fetchAll();

        $totalUnits = 0; $totalValue = 0.0;
        foreach ($data as $r) {
            $value = (int) $r['current_stock'] * (float) $r['price_per_unit'];
            $totalUnits += (int) $r['current_stock'];
            $totalValue += $value;
            $st = stock_status((int) $r['current_stock'], (int) $r['min_stock']);
            $rows[] = [
                $r['name'],
                $r['sku'],
                $r['category_name'],
                $r['supplier_name'],
                $r['unit'],
                number_format((float) $r['price_per_unit'], 2),
                number_format((float) $r['selling_price'], 2),
                number_format((int) $r['current_stock']),
                number_format((int) $r['min_stock']),
                number_format($value, 2),
                $st['label'],
            ];
        }
        $totals = [
            'Total Products'  => number_format(count($data)),
            'Total Units'     => number_format($totalUnits),
            'Inventory Value' => 'RM ' . number_format($totalValue, 2),
        ];
        break;
}

$generatedBy = current_user()['name'] ?? 'System';
$generatedAt = date('d M Y, h:i A');

/* Demo 3: record that a report was produced. */
log_activity('Reports', 'Generated the ' . $title . ' (' . strtoupper($format) . ')');
notify('report', 'Report generated', $title . ' was generated as ' . strtoupper($format) . ' by ' . $generatedBy, 'reports/index.php');

/* =====================================================================
   EXCEL: an HTML table sent with Excel headers. Excel opens this natively
   and it keeps formatting -- no external library needed on XAMPP.
   ===================================================================== */
if ($format === 'excel') {
    $filename = 'SMART_STOCK_' . strtoupper($type) . '_' . date('Ymd_His') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF"; // BOM so Excel reads UTF-8 correctly
    ?>
    <table border="1">
      <tr><td colspan="<?= count($columns) ?>" style="font-size:16pt;font-weight:bold;">SMART STOCK — <?= e($title) ?></td></tr>
      <tr><td colspan="<?= count($columns) ?>">Botol Anggun Sdn. Bhd.</td></tr>
      <tr><td colspan="<?= count($columns) ?>"><?= e($subtitle) ?></td></tr>
      <tr><td colspan="<?= count($columns) ?>">Generated by <?= e($generatedBy) ?> on <?= e($generatedAt) ?></td></tr>
      <tr><td colspan="<?= count($columns) ?>"></td></tr>
      <tr>
        <?php foreach ($columns as $c): ?>
          <th style="background:#7429A0;color:#ffffff;font-weight:bold;"><?= e($c) ?></th>
        <?php endforeach; ?>
      </tr>
      <?php foreach ($rows as $r): ?>
        <tr><?php foreach ($r as $cell): ?><td><?= e($cell) ?></td><?php endforeach; ?></tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
        <tr><td colspan="<?= count($columns) ?>">No records found for this report.</td></tr>
      <?php endif; ?>
      <tr><td colspan="<?= count($columns) ?>"></td></tr>
      <?php foreach ($totals as $label => $value): ?>
        <tr>
          <td style="font-weight:bold;"><?= e($label) ?></td>
          <td style="font-weight:bold;"><?= e($value) ?></td>
          <?php for ($i = 2; $i < count($columns); $i++): ?><td></td><?php endfor; ?>
        </tr>
      <?php endforeach; ?>
    </table>
    <?php
    exit;
}

/*HTML preview / print view*/
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SMART STOCK — <?= e($title) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --blue:#7429A0; --navy:#43135A; --gold:#D9B65C; --ink:#1D1226; --muted:#7A6B85;
    --border:#E7DCF0; --bg:#F7F4FB; --green:#12966B; --amber:#C5790E; --red:#D0392C;
  }
  *{box-sizing:border-box;}
  body{margin:0;background:var(--bg);color:var(--ink);font-family:'Inter',system-ui,sans-serif;font-size:13px;}
  .sheet{max-width:1100px;margin:24px auto;background:#fff;padding:34px 38px;border-radius:14px;
         box-shadow:0 8px 24px -8px rgba(16,27,45,0.12);}
  .rpt-head{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;
            border-bottom:2px solid var(--navy);padding-bottom:16px;margin-bottom:20px;
            box-shadow:0 2px 0 0 var(--gold);}
  .brand{display:flex;align-items:center;gap:11px;}
  .brand .mark{width:40px;height:40px;border-radius:10px;display:flex;
               align-items:center;justify-content:center;color:#fff;flex-shrink:0;}
  .brand .bn{font-family:'Poppins',sans-serif;font-weight:700;font-size:17px;letter-spacing:0.3px;}
  .brand .bc{font-size:11px;color:var(--muted);}
  .rpt-title{text-align:right;}
  .rpt-title h1{font-family:'Poppins',sans-serif;font-size:19px;margin:0 0 3px;}
  .rpt-title .sub{font-size:12px;color:var(--muted);}
  .meta-row{display:flex;gap:26px;flex-wrap:wrap;font-size:11.5px;color:var(--muted);margin-bottom:18px;}
  .meta-row b{color:var(--ink);font-weight:600;}
  table{width:100%;border-collapse:collapse;margin-bottom:18px;}
  thead th{background:var(--navy);color:#fff;font-size:10.5px;text-transform:uppercase;letter-spacing:0.4px;
           padding:9px 8px;text-align:left;font-weight:600;}
  tbody td{padding:8px;border-bottom:1px solid var(--border);font-size:11.8px;}
  tbody tr:nth-child(even){background:#FAFCFF;}
  .num{text-align:right;font-variant-numeric:tabular-nums;}
  .empty{text-align:center;color:var(--muted);padding:34px 0;}
  .totals{display:flex;flex-wrap:wrap;gap:12px;border-top:2px solid var(--navy);padding-top:16px;}
  .total-box{background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:11px 16px;min-width:150px;}
  .total-box .tl{font-size:10px;text-transform:uppercase;letter-spacing:0.5px;color:var(--muted);font-weight:600;}
  .total-box .tv{font-size:16px;font-weight:700;margin-top:3px;}
  .rpt-foot{margin-top:22px;padding-top:12px;border-top:1px solid var(--border);
            font-size:10.5px;color:var(--muted);display:flex;justify-content:space-between;gap:12px;}
  .toolbar{max-width:1100px;margin:20px auto 0;display:flex;gap:9px;padding:0 8px;}
  .toolbar a,.toolbar button{font-family:inherit;font-size:13px;font-weight:600;padding:9px 16px;border-radius:9px;
    border:1px solid var(--border);background:#fff;color:var(--ink);text-decoration:none;cursor:pointer;
    display:inline-flex;align-items:center;gap:6px;}
  .toolbar .primary{background:var(--blue);border-color:var(--blue);color:#fff;}
  .toolbar a:hover,.toolbar button:hover{border-color:var(--blue);color:#7429A0;}
  .toolbar .primary:hover{background:#0E52B8;color:#fff;}
  .st{font-weight:600;}
  .st-in{color:var(--green);} .st-low{color:var(--amber);}
  .st-crit,.st-out{color:var(--red);}

  @media print{
    body{background:#fff;}
    .toolbar{display:none !important;}
    .sheet{box-shadow:none;border-radius:0;margin:0;max-width:none;padding:0;}
    thead{display:table-header-group;}
    tr{break-inside:avoid;}
    @page{margin:14mm;size:<?= count($columns) > 8 ? 'A4 landscape' : 'A4 portrait' ?>;}
  }
</style>
</head>
<body>

<div class="toolbar">
  <a href="<?= e(base_url('reports/index.php')) ?>">&larr; Back to Reports</a>
  <button type="button" class="primary" onclick="window.print();">Print / Save as PDF</button>
  <a href="<?= e(base_url('reports/generate.php?type=' . urlencode($type) . '&from=' . urlencode($from) . '&to=' . urlencode($to) . '&format=excel')) ?>">Download Excel</a>
</div>

<div class="sheet">
  <div class="rpt-head">
    <div class="brand">
      <div class="mark">
        <img src="<?= e(base_url('assets/img/botol-anggun-mark.png')) ?>" alt="Botol Anggun Logo" style="width: 40px; height: 40px; object-fit: contain; border-radius: 10px;">
      </div>
      <div>
        <div class="bn">SMART STOCK</div>
        <div class="bc">Botol Anggun Sdn. Bhd.</div>
      </div>
    </div>
    <div class="rpt-title">
      <h1><?= e($title) ?></h1>
      <div class="sub"><?= e($subtitle) ?></div>
    </div>
  </div>

  <div class="meta-row">
    <span>Generated by <b><?= e($generatedBy) ?></b></span>
    <span>Generated on <b><?= e($generatedAt) ?></b></span>
    <span>Records: <b><?= number_format(count($rows)) ?></b></span>
  </div>

  <table>
    <thead>
      <tr><?php foreach ($columns as $c): ?><th><?= e($c) ?></th><?php endforeach; ?></tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="<?= count($columns) ?>" class="empty">No records found for this report<?= in_array($type, ['stock_in','stock_out'], true) ? ' in the selected date range' : '' ?>.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $r): ?>
        <tr>
          <?php foreach ($r as $i => $cell):
            $isNum = preg_match('/^-?[\d,]+(\.\d+)?$/', (string) $cell) === 1;
            $cls = $isNum ? 'num' : '';
            if ($i === count($r) - 1 && in_array($type, ['inventory','low_stock'], true)) {
                $map = ['In Stock' => 'st st-in', 'Low Stock' => 'st st-low', 'Critical' => 'st st-crit', 'Out of Stock' => 'st st-out'];
                $cls = $map[$cell] ?? '';
            }
          ?>
            <td class="<?= e($cls) ?>"><?= e($cell) ?></td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php if (!empty($totals)): ?>
    <div class="totals">
      <?php foreach ($totals as $label => $value): ?>
        <div class="total-box">
          <div class="tl"><?= e($label) ?></div>
          <div class="tv"><?= e($value) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="rpt-foot">
    <span>SMART STOCK — Digital Inventory Management System · Botol Anggun Sdn. Bhd.</span>
    <span>This report was generated automatically from live system data.</span>
  </div>
</div>

<?php if ($format === 'pdf'): ?>
<script>
  /* "Print / PDF" opens the print dialog straight away -- the user picks
     "Save as PDF" as the destination. */
  window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });
</script>
<?php endif; ?>

</body>
</html>