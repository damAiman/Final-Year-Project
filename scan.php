<?php
require_once __DIR__ . '/config/bootstrap.php';
require_login();

/* mode=in (default) or mode=out -- decides which save handler the form posts to. */
$mode = ($_GET['mode'] ?? 'in') === 'out' ? 'out' : 'in';

$pageTitle = 'Scan QR Code';
$pageCrumb = $mode === 'in'
    ? 'Scan a product QR code to record incoming stock'
    : 'Scan a product QR code to record outgoing stock';
$activeNav = 'scan';

$pageScripts = [
    'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js',
    'assets/js/scan.js',
];
$inlineFooterScript = "const APP_BASE_URL = " . json_encode(base_url())
    . "; const SCAN_MODE = " . json_encode($mode) . ";";

require_once __DIR__ . '/includes/header.php';
?>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card-erp">

      <!-- Mode switch: same page serves Stock In and Stock Out -->
      <div class="scan-mode-switch mb-3">
        <a href="<?= e(base_url('scan.php?mode=in')) ?>" class="scan-mode-btn <?= $mode === 'in' ? 'active in' : '' ?>">
          <i class="bi bi-box-arrow-in-down"></i> Stock In
        </a>
        <a href="<?= e(base_url('scan.php?mode=out')) ?>" class="scan-mode-btn <?= $mode === 'out' ? 'active out' : '' ?>">
          <i class="bi bi-box-arrow-up"></i> Stock Out
        </a>
      </div>

      <!-- STATE 1: idle -->
      <div id="scanIdle">
        <div class="text-center py-4">
          <div class="scan-icon-circle <?= $mode === 'out' ? 'out' : '' ?> mx-auto mb-3">
            <i class="bi bi-qr-code-scan" style="font-size:30px;"></i>
          </div>
          <h2 class="h5 fw-bold mb-2">Scan the product QR code</h2>
          <p class="text-muted small mb-4" style="max-width:380px;margin:0 auto;">
            Point your phone or laptop camera at the QR code already printed on the product label.
            SMART STOCK will look it up and fill in the details for you.
          </p>
          <button type="button" class="btn <?= $mode === 'out' ? 'btn-erp-danger' : 'btn-erp-primary' ?> btn-lg" id="btnStartScan">
            <i class="bi bi-camera"></i> Open camera &amp; scan
          </button>
          <div class="mt-3 small text-muted">
            Camera unavailable? <a href="#" id="btnManualCode">Type the code printed under the QR</a>
          </div>
        </div>
      </div>

      <!-- STATE 2: camera live -->
      <div id="scanCamera" style="display:none;">
        <div class="scan-camera-wrap">
          <video id="scanVideo" playsinline muted></video>
          <div class="scan-frame">
            <span class="c tl"></span><span class="c tr"></span>
            <span class="c bl"></span><span class="c br"></span>
            <div class="scan-laser"></div>
          </div>
        </div>
        <p class="text-center small text-muted mt-3 mb-2" id="scanStatusText">Looking for a QR code…</p>
        <div class="alert alert-danger py-2 px-3 small d-none" id="scanError"></div>
        <div class="text-center">
          <button type="button" class="btn btn-erp-outline" id="btnStopScan">Cancel</button>
        </div>
      </div>

      <!-- STATE 3: product found -> transaction form -->
      <div id="scanResult" style="display:none;">
        <a href="#" class="back-link" id="btnScanAgain">
          <i class="bi bi-arrow-left"></i> Scan another product
        </a>
        <h2 class="h6 fw-bold mb-3">
          <?= $mode === 'in' ? 'Record stock received' : 'Record stock issued' ?>
        </h2>

        <div class="product-panel mb-3">
          <img src="" id="rsImage" class="prod-thumb-lg" alt="">
          <div class="flex-grow-1">
            <div class="fw-bold" style="font-size:15px;" id="rsName">—</div>
            <div class="small text-muted mono mb-2" id="rsSku">—</div>
            <div class="row g-2">
              <div class="col-6"><div class="pd-label">Category</div><div class="pd-value" id="rsCategory">—</div></div>
              <div class="col-6"><div class="pd-label">Supplier</div><div class="pd-value" id="rsSupplier">—</div></div>
              <div class="col-6"><div class="pd-label">Current Stock</div><div class="pd-value" id="rsStock">—</div></div>
              <div class="col-6"><div class="pd-label">Status</div><div class="pd-value"><span class="badge-status" id="rsStatus">—</span></div></div>
            </div>
          </div>
        </div>

        <form method="post" action="<?= e(base_url($mode === 'in' ? 'stock_in_save.php' : 'stock_out_save.php')) ?>" id="scanForm">
          <input type="hidden" name="product_id" id="rsProductId" value="">
          <input type="hidden" name="entry_method" value="qr">
          <input type="hidden" name="redirect_to" value="scan.php?mode=<?= e($mode) ?>">
          <div class="mb-3">
            <label class="form-label-erp form-label">Date <span class="text-danger">*</span></label>
            <input type="date" name="transaction_date" class="form-control form-control-erp" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label-erp form-label">
              <?= $mode === 'in' ? 'Quantity Received' : 'Quantity to Issue' ?> <span class="text-danger">*</span>
            </label>
            <input type="number" name="quantity" id="rsQty" class="form-control form-control-erp" min="1" required placeholder="0">
            <div class="form-text" id="rsQtyHint"></div>
          </div>
          <div class="mb-3">
            <label class="form-label-erp form-label">Remarks</label>
            <textarea name="remarks" class="form-control form-control-erp" rows="2"
              placeholder="<?= $mode === 'in' ? 'Optional — e.g. delivery note number' : 'Optional — e.g. order number or destination' ?>"></textarea>
          </div>
          <div class="d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-erp-outline" id="btnCancelResult">Cancel</button>
            <button type="submit" class="btn <?= $mode === 'out' ? 'btn-erp-danger' : 'btn-erp-primary' ?>">
              <?= $mode === 'in' ? 'Save Stock In' : 'Save Stock Out' ?>
            </button>
          </div>
        </form>
      </div>

    </div>
  </div>

  <div class="col-lg-5">

    <div class="card-erp h-100">
      <h3 class="h6 fw-bold mb-1">How scanning works</h3>
      <p class="text-muted small mb-3">SMART STOCK reads the QR codes already printed on your products — it does not create new ones.</p>
      <ol class="scan-steps">
        <li><span>Choose <strong>Stock In</strong> or <strong>Stock Out</strong> at the top.</span></li>
        <li><span>Tap <strong>Open camera &amp; scan</strong> and allow camera access when the browser asks.</span></li>
        <li><span>Hold the product label steady inside the frame — the scan is automatic.</span></li>
        <li><span>Check the product details, enter the quantity, then save.</span></li>
      </ol>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>