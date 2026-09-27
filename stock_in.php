<?php
require_once __DIR__ . '/config/bootstrap.php';
require_login();

$pageTitle = 'Stock In';
$pageCrumb = 'Search for a product to record incoming stock';
$activeNav = 'stockin';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card-erp">

      <!-- STATE: search -->
      <div id="stateSearch">
        <div class="d-flex align-items-start justify-content-between gap-3 mb-3 flex-wrap">
          <div>
            <h2 class="h6 fw-bold mb-1">Search product</h2>
            <p class="small text-muted mb-0">Find the product you want to receive stock for, then select it from the list below.</p>
          </div>
          <a href="<?= e(base_url('scan.php?mode=in')) ?>" class="btn btn-erp-outline">
            <i class="bi bi-qr-code-scan"></i> Scan QR Code
          </a>
        </div>

        <div class="search-combo-wrap mb-1">
          <div class="search-combo-input">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.6" y2="16.6"/></svg>
            <input type="text" id="searchInput" placeholder="Search by product name — e.g. PET Bottle 500ml" autocomplete="off">
            <button type="button" class="search-clear-btn" id="searchClear" style="display:none;">&times;</button>
          </div>
          <div class="search-combo-dropdown" id="searchDropdown"></div>
        </div>
        <div class="small text-muted">Start typing, or click the search box to browse all products.</div>
      </div>

      <!-- STATE: product selected -->
      <div id="stateSelected" style="display:none;">
        <a href="#" id="backToSearch" class="small fw-semibold d-inline-flex align-items-center gap-1 mb-3" style="color:var(--blue-600);">
          <i class="bi bi-arrow-left"></i> Back to search
        </a>
        <h2 class="h6 fw-bold mb-3">Record stock received</h2>

        <div class="confirm-product-card mb-4">
          <img id="pImage" src="" alt="">
          <dl class="mb-0 flex-grow-1">
            <dd class="fw-bold mb-0" style="font-size:15px;" id="pName">—</dd>
            <div class="small text-muted mono mb-2" id="pSku">—</div>
            <div class="row row-cols-2 g-2">
              <div><dt>Category</dt><dd id="pCategory">—</dd></div>
              <div><dt>Supplier</dt><dd id="pSupplier">—</dd></div>
              <div><dt>Current Stock</dt><dd id="pStock">—</dd></div>
              <div><dt>Unit</dt><dd id="pUnit">—</dd></div>
              <div><dt>Storage Location</dt><dd id="pLocation">—</dd></div>
              <div><dt>Status</dt><dd><span class="badge-status" id="pStatus">—</span></dd></div>
            </div>
          </dl>
        </div>

        <form method="post" action="<?= e(base_url('stock_in_save.php')) ?>" id="stockForm">
          <input type="hidden" name="product_id" id="productId" value="">
          <div class="mb-3">
            <label class="form-label-erp form-label">Date <span class="text-danger">*</span></label>
            <input type="date" name="transaction_date" class="form-control form-control-erp" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label-erp form-label">Quantity Received <span class="text-danger">*</span></label>
            <input type="number" name="quantity" class="form-control form-control-erp" min="1" required placeholder="0">
          </div>
          <div class="mb-3">
            <label class="form-label-erp form-label">Remarks</label>
            <textarea name="remarks" class="form-control form-control-erp" rows="2" placeholder="Optional — e.g. delivery note number, condition of goods"></textarea>
          </div>
          <div class="d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-erp-outline" id="cancelBtn">Cancel</button>
            <button type="submit" class="btn btn-erp-primary">Save Stock In</button>
          </div>
        </form>
      </div>

    </div>
  </div>

  <div class="col-lg-5">
    <div class="card-erp h-100">
      <h3 class="h6 fw-bold mb-3">Product Details</h3>
      <div id="sidePlaceholder" class="text-center text-muted py-5">
        <i class="bi bi-search fs-2 d-block mb-2" style="color:var(--blue-100);"></i>
        <p class="small mb-0">Select a product to see its details here before recording stock in.</p>
      </div>
      <div id="sideDetails" style="display:none;">
        <div class="text-center mb-3">
          <img id="sImage" src="" alt="" style="width:96px;height:96px;border-radius:16px;object-fit:cover;background:var(--blue-100);">
        </div>
        <h4 class="h6 fw-bold text-center mb-1" id="sName">—</h4>
        <p class="text-center text-muted small mono mb-3" id="sSku">—</p>
        <dl class="row row-cols-2 g-2 confirm-grid mb-0">
          <div><dt>Category</dt><dd id="sCategory">—</dd></div>
          <div><dt>Supplier</dt><dd id="sSupplier">—</dd></div>
          <div><dt>Current Stock</dt><dd id="sStock">—</dd></div>
          <div><dt>Status</dt><dd><span class="badge-status" id="sStatus">—</span></dd></div>
        </dl>
      </div>
    </div>
  </div>
</div>

<?php

$preselectId = (int) ($_GET['product_id'] ?? 0);
$preselect = null;
if ($preselectId > 0) {
    $stmt = getDB()->prepare(
        "SELECT p.id, p.sku, p.name, p.unit, p.current_stock, p.min_stock, p.storage_location,
                COALESCE(c.name,'Uncategorised') AS category_name,
                COALESCE(s.name,'-')            AS supplier_name,
                p.image
           FROM products p
           LEFT JOIN categories c ON c.id = p.category_id
           LEFT JOIN suppliers  s ON s.id = p.supplier_id
          WHERE p.id = ? LIMIT 1"
    );
    $stmt->execute([$preselectId]);
    $row = $stmt->fetch();
    if ($row) {
        $st = stock_status((int) $row['current_stock'], (int) $row['min_stock']);
        $preselect = [
            'id'            => (int) $row['id'],
            'sku'           => $row['sku'],
            'name'          => $row['name'],
            'category'      => $row['category_name'],
            'supplier'      => $row['supplier_name'],
            'unit'          => $row['unit'],
            'current_stock' => (int) $row['current_stock'],
            'location'      => $row['storage_location'],
            'image'         => product_image_url($row['image'], $row['category_name']),
            'status_label'  => $st['label'],
            'status_class'  => $st['class'],
        ];
    }
}

$inlineFooterScript = "const APP_BASE_URL = " . json_encode(base_url()) . "; const STOCK_MODE = 'in'; const PRESELECT_PRODUCT = " . json_encode($preselect) . ";";
$pageScripts = ['assets/js/stock.js'];
require_once __DIR__ . '/includes/footer.php';
?>
