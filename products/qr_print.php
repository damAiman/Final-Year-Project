<?php
/**
 * Printable QR code labels.
 *
 *   qr_print.php            -> labels for every product
 *   qr_print.php?id=12      -> a single product
 *   qr_print.php?q=bottle   -> everything matching a search
 *
 * The QR payload is the product's `qr_code` value (seeded from the SKU), which
 * is exactly what api/lookup_qr.php matches on when scanning. So a label
 * printed here will always resolve correctly in the scanner.
 *
 * Note: this prints labels for stock that does not have a manufacturer code
 * yet. Where Botol Anggun already has its own printed QR on the product, keep
 * using that one -- just make sure the product's `qr_code` field holds the
 * same value.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_admin();

$db = getDB();

$id = (int) ($_GET['id'] ?? 0);
$q  = trim($_GET['q'] ?? '');

if ($id > 0) {
    $stmt = $db->prepare(
        "SELECT p.*, COALESCE(c.name,'Uncategorised') AS category_name
           FROM products p LEFT JOIN categories c ON c.id = p.category_id
          WHERE p.id = ?"
    );
    $stmt->execute([$id]);
    $scope = 'single';
} elseif ($q !== '') {
    $stmt = $db->prepare(
        "SELECT p.*, COALESCE(c.name,'Uncategorised') AS category_name
           FROM products p LEFT JOIN categories c ON c.id = p.category_id
          WHERE p.name LIKE ? OR p.sku LIKE ?
          ORDER BY p.name"
    );
    $like = '%' . $q . '%';
    $stmt->execute([$like, $like]);
    $scope = 'search';
} else {
    $stmt = $db->query(
        "SELECT p.*, COALESCE(c.name,'Uncategorised') AS category_name
           FROM products p LEFT JOIN categories c ON c.id = p.category_id
          ORDER BY c.name, p.name"
    );
    $scope = 'all';
}
$products = $stmt->fetchAll();

$companyName = setting('company_name', 'Botol Anggun Sdn. Bhd.');

log_activity('Product Management', 'Printed QR label(s) — ' . count($products) . ' product(s)');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>QR Labels — SMART STOCK</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<style>
  :root{
    --purple-800:#43135A; --purple-600:#7429A0; --gold:#D9B65C;
    --ink-900:#1D1226; --ink-500:#7A6B85; --border:#E7DCF0; --bg:#F7F4FB;
  }
  *{box-sizing:border-box;}
  body{margin:0;background:var(--bg);color:var(--ink-900);
       font-family:'Inter',system-ui,sans-serif;font-size:13px;}

  /* ---------------- on-screen toolbar ---------------- */
  .toolbar{
    background:#fff;border-bottom:1px solid var(--border);padding:14px 22px;
    display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;
    position:sticky;top:0;z-index:5;
  }
  .toolbar h1{font-family:'Poppins',sans-serif;font-size:16px;margin:0;}
  .toolbar .sub{font-size:11.5px;color:var(--ink-500);margin-top:2px;}
  .tb-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
  .btn{
    font-family:inherit;font-size:13px;font-weight:600;padding:8px 15px;border-radius:9px;
    border:1px solid var(--border);background:#fff;color:var(--ink-900);cursor:pointer;
    text-decoration:none;display:inline-flex;align-items:center;gap:6px;
  }
  .btn:hover{border-color:var(--purple-600);color:var(--purple-600);}
  .btn.primary{background:var(--purple-600);border-color:var(--purple-600);color:#fff;}
  .btn.primary:hover{background:var(--purple-800);color:#fff;}
  .size-group{display:flex;align-items:center;gap:6px;font-size:12.5px;color:var(--ink-500);}
  .size-group select{
    font-family:inherit;font-size:12.5px;padding:7px 9px;border-radius:8px;
    border:1px solid var(--border);background:#fff;color:var(--ink-900);
  }

  .hint{
    max-width:1100px;margin:16px auto 0;padding:10px 14px;
    background:#EFE7FC;border:1px solid #DCC9EC;border-radius:10px;
    font-size:12px;color:#4A2A5E;line-height:1.55;
  }

  /* ---------------- label sheet ---------------- */
  .sheet{max-width:1100px;margin:16px auto 40px;padding:0 16px;}
  .labels{display:grid;gap:10px;grid-template-columns:repeat(auto-fill,minmax(178px,1fr));}
  .labels.size-lg{grid-template-columns:repeat(auto-fill,minmax(240px,1fr));}
  .labels.size-sm{grid-template-columns:repeat(auto-fill,minmax(140px,1fr));}

  .label{
    background:#fff;border:1px solid var(--border);border-radius:10px;
    padding:12px 10px;text-align:center;break-inside:avoid;page-break-inside:avoid;
  }
  .label .co{
    font-size:7.5px;letter-spacing:1px;text-transform:uppercase;color:var(--ink-500);
    font-weight:700;margin-bottom:6px;
  }
  .label .qr{display:flex;align-items:center;justify-content:center;margin-bottom:8px;}
  .label .qr img,.label .qr canvas{display:block;}
  .label .pname{
    font-size:11.5px;font-weight:700;line-height:1.25;margin-bottom:3px;
    overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;
  }
  .label .psku{
    font-family:'JetBrains Mono',monospace;font-size:10px;font-weight:600;
    color:var(--purple-600);letter-spacing:.3px;
  }
  .label .pcat{font-size:9px;color:var(--ink-500);margin-top:3px;}

  .empty{text-align:center;color:var(--ink-500);padding:60px 20px;}

  /* ---------------- print ---------------- */
  @media print{
    body{background:#fff;}
    .toolbar,.hint{display:none !important;}
    .sheet{margin:0;padding:0;max-width:none;}
    .labels{gap:6px;}
    .label{border:1px dashed #999;border-radius:6px;}
    @page{margin:10mm;}
  }
</style>
</head>
<body>

<div class="toolbar">
  <div>
    <h1>QR Labels</h1>
    <div class="sub">
      <?= count($products) ?> label(s) ·
      <?= $scope === 'single' ? 'single product' : ($scope === 'search' ? 'search: "' . e($q) . '"' : 'entire catalogue') ?>
    </div>
  </div>
  <div class="tb-actions">
    <div class="size-group">
      <label for="sizeSel">Label size</label>
      <select id="sizeSel">
        <option value="size-sm">Small</option>
        <option value="" selected>Medium</option>
        <option value="size-lg">Large</option>
      </select>
    </div>
    <a href="<?= e(base_url('products/index.php')) ?>" class="btn">&larr; Back to Products</a>
    <button type="button" class="btn primary" onclick="window.print();">
      <i>&#128424;</i> Print labels
    </button>
  </div>
</div>

<div class="hint">
  <strong>How to use these:</strong> print this page, cut along the dashed lines and stick each label
  on its product or storage bin. Scanning a label in <strong>Stock In</strong> or <strong>Stock Out</strong>
  will pull up that exact product. If a product already carries a manufacturer QR code, keep that one
  instead and paste its value into the product's QR field.
</div>

<div class="sheet">
  <?php if (empty($products)): ?>
    <div class="empty">No products to print labels for.</div>
  <?php else: ?>
    <div class="labels" id="labels">
      <?php foreach ($products as $p):
        $payload = $p['qr_code'] !== null && $p['qr_code'] !== '' ? $p['qr_code'] : $p['sku'];
      ?>
        <div class="label">
          <div class="co"><?= e($companyName) ?></div>
          <div class="qr" data-code="<?= e($payload) ?>"></div>
          <div class="pname"><?= e($p['name']) ?></div>
          <div class="psku"><?= e($payload) ?></div>
          <div class="pcat"><?= e($p['category_name']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
  var SIZES = { 'size-sm': 92, '': 118, 'size-lg': 150 };

  function render(sizeClass) {
    var px = SIZES[sizeClass];
    document.querySelectorAll('.qr').forEach(function (box) {
      box.innerHTML = '';
      var code = box.dataset.code || '';
      if (!code) { box.textContent = '—'; return; }
      if (typeof QRCode === 'undefined') {
        box.innerHTML = '<span style="font-size:10px;color:#999;">QR library offline</span>';
        return;
      }
      new QRCode(box, {
        text: code,
        width: px,
        height: px,
        colorDark: '#000000',      // pure black scans most reliably on paper
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
      });
    });
  }

  var sel = document.getElementById('sizeSel');
  var grid = document.getElementById('labels');

  function apply() {
    if (!grid) return;
    grid.className = 'labels ' + sel.value;
    render(sel.value);
  }

  if (sel && grid) {
    sel.addEventListener('change', apply);
    apply();
  }
</script>
</body>
</html>
