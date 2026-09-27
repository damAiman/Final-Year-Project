<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_admin();

$db = getDB();

$q = trim($_GET['q'] ?? '');

if ($q !== '') {
    $stmt = $db->prepare(
        "SELECT p.*, COALESCE(c.name,'Uncategorised') AS category_name, COALESCE(s.name,'—') AS supplier_name
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         LEFT JOIN suppliers s ON s.id = p.supplier_id
         WHERE p.name LIKE :q1 OR p.sku LIKE :q2 OR c.name LIKE :q3 OR s.name LIKE :q4
         ORDER BY p.name ASC"
    );
    $searchTerm = '%' . $q . '%';
    $stmt->execute([
        'q1' => $searchTerm,
        'q2' => $searchTerm,
        'q3' => $searchTerm,
        'q4' => $searchTerm
    ]);
} else {
    $stmt = $db->query(
        "SELECT p.*, COALESCE(c.name,'Uncategorised') AS category_name, COALESCE(s.name,'—') AS supplier_name
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         LEFT JOIN suppliers s ON s.id = p.supplier_id
         ORDER BY p.name ASC"
    );
}
$products = $stmt->fetchAll();

$categories = $db->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$suppliers  = $db->query('SELECT id, name FROM suppliers ORDER BY name')->fetchAll();

$pageTitle = 'Product Management';
$pageCrumb = count($products) . ' product' . (count($products) === 1 ? '' : 's') . ' in the catalogue';
$activeNav = 'products';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card-erp">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <form class="d-flex align-items-center gap-2" method="get" action="<?= e(base_url('products/index.php')) ?>" style="max-width:320px;flex:1;">
      <div class="input-wrap flex-grow-1" style="position:relative;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--ink-400);"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.6" y2="16.6"/></svg>
        <input type="text" name="q" class="form-control form-control-erp" style="padding-left:38px;" placeholder="Search name, SKU, category..." value="<?= e($q) ?>">
      </div>
      <?php if ($q !== ''): ?>
        <a href="<?= e(base_url('products/index.php')) ?>" class="btn btn-erp-outline btn-sm">Clear</a>
      <?php endif; ?>
    </form>
    <div class="d-flex gap-2">
      <a href="<?= e(base_url('products/qr_print.php' . ($q !== '' ? '?q=' . urlencode($q) : ''))) ?>"
         target="_blank" class="btn btn-erp-outline"
         title="<?= $q !== '' ? 'Print QR labels for the current search results' : 'Print QR labels for every product' ?>">
        <i class="bi bi-qr-code me-1"></i> Print QR Labels
      </a>
      <button type="button" class="btn btn-erp-primary" data-bs-toggle="modal" data-bs-target="#productModal" id="btnAddProduct">
        <i class="bi bi-plus-lg me-1"></i> Add Product
      </button>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-erp align-middle">
      <thead>
        <tr>
          <th>Image</th>
          <th>Product Name</th>
          <th>Category</th>
          <th>Supplier</th>
          <th>Cost / Unit</th>
          <th>Selling / Unit</th>
          <th>Current Stock</th>
          <th>Minimum Stock</th>
          <th>Status</th>
          <th>Last Updated</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($products)): ?>
          <tr><td colspan="11" class="text-center text-muted py-5">
            <?= $q !== '' ? 'No products match "' . e($q) . '".' : 'No products yet. Click "Add Product" to create the first one.' ?>
          </td></tr>
        <?php endif; ?>
        <?php foreach ($products as $p): $status = stock_status((int) $p['current_stock'], (int) $p['min_stock']); ?>
          <tr>
            <td><img src="<?= e(product_image_url($p['image'], $p['category_name'])) ?>" class="prod-thumb" alt="<?= e($p['name']) ?>"></td>
            <td>
              <div class="fw-semibold"><?= e($p['name']) ?></div>
              <div class="small text-muted mono"><?= e($p['sku']) ?></div>
            </td>
            <td><span class="badge-status instock" style="color:var(--blue-600);background:var(--blue-100);"><?= e($p['category_name']) ?></span></td>
            <td><?= e($p['supplier_name']) ?></td>
            <td class="mono text-muted">RM <?= e(number_format((float) $p['price_per_unit'], 2)) ?></td>
            <td class="mono">
              RM <?= e(number_format((float) $p['selling_price'], 2)) ?>
              <?php
                $cost = (float) $p['price_per_unit'];
                $sell = (float) $p['selling_price'];
                if ($cost > 0 && $sell > 0):
                  $margin = (($sell - $cost) / $cost) * 100;
              ?>
                <div class="small <?= $margin >= 0 ? 'text-success' : 'text-danger' ?>"><?= e(($margin >= 0 ? '+' : '') . number_format($margin, 0)) ?>%</div>
              <?php endif; ?>
            </td>
            <td class="mono"><?= fmt_num($p['current_stock']) ?> <span class="text-muted small"><?= e($p['unit']) ?></span></td>
            <td class="mono text-muted"><?= fmt_num($p['min_stock']) ?> <span class="small"><?= e($p['unit']) ?></span></td>
            <td><span class="badge-status <?= e($status['class']) ?>"><?= e($status['label']) ?></span></td>
            <td class="text-nowrap">
              <div class="small"><?= e(date('d M Y', strtotime($p['updated_at']))) ?></div>
              <div class="small text-muted"><?= e(time_ago($p['updated_at'])) ?></div>
            </td>
            <td class="text-nowrap">
              <button type="button" class="btn btn-sm btn-erp-outline btn-edit-product"
                data-id="<?= e($p['id']) ?>"
                data-name="<?= e($p['name']) ?>"
                data-category="<?= e($p['category_id']) ?>"
                data-supplier="<?= e($p['supplier_id']) ?>"
                data-unit="<?= e($p['unit']) ?>"
                data-price="<?= e(number_format((float) $p['price_per_unit'], 2, '.', '')) ?>"
                data-selling="<?= e(number_format((float) $p['selling_price'], 2, '.', '')) ?>"
                data-current="<?= e($p['current_stock']) ?>"
                data-min="<?= e($p['min_stock']) ?>"
                data-location="<?= e($p['storage_location']) ?>"
                data-qr="<?= e($p['qr_code']) ?>"
                data-description="<?= e($p['description']) ?>"
                data-image="<?= e(product_image_url($p['image'], $p['category_name'])) ?>"
                title="Edit">
                <i class="bi bi-pencil"></i>
              </button>
              <a href="<?= e(base_url('products/qr_print.php?id=' . (int) $p['id'])) ?>" target="_blank"
                 class="btn btn-sm btn-erp-outline" title="Print QR label for this product">
                <i class="bi bi-qr-code"></i>
              </a>
              <form method="post" action="<?= e(base_url('products/delete.php')) ?>" class="d-inline" onsubmit="return confirm('Delete &quot;<?= e($p['name']) ?>&quot;? This cannot be undone.');">
                <input type="hidden" name="id" value="<?= e($p['id']) ?>">
                <button type="submit" class="btn btn-sm btn-erp-danger-outline" title="Delete"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add / Edit product modal -->
<div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="<?= e(base_url('products/save.php')) ?>" enctype="multipart/form-data" id="productForm">
        <div class="modal-header">
          <h5 class="modal-title" id="productModalTitle">Add Product</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="f-id" value="">

          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label-erp form-label">Product Image</label>
              <div class="upload-zone" id="uploadZone">
                <img id="uploadPreview" src="" alt="Preview">
                <div class="upload-hint">
                  <i class="bi bi-cloud-arrow-up fs-3" style="color:var(--blue-600);"></i>
                  <div class="small fw-semibold mt-1">Click to upload</div>
                  <div class="small text-muted">PNG or JPG, up to 5MB</div>
                </div>
              </div>
              <input type="file" name="image" id="f-image" accept="image/png,image/jpeg,image/webp" class="d-none">
            </div>

            <div class="col-md-8">
              <div class="mb-3">
                <label class="form-label-erp form-label">Product Name <span class="text-danger">*</span></label>
                <input type="text" name="name" id="f-name" class="form-control form-control-erp" required placeholder="e.g. PET Bottle 500ml">
              </div>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label-erp form-label">Category <span class="text-danger">*</span></label>
                  <select name="category_id" id="f-category" class="form-select form-control-erp" required>
                    <option value="">Select category</option>
                    <?php foreach ($categories as $c): ?>
                      <option value="<?= e($c['id']) ?>"><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label-erp form-label">Supplier <span class="text-danger">*</span></label>
                  <select name="supplier_id" id="f-supplier" class="form-select form-control-erp" required>
                    <option value="">Select supplier</option>
                    <?php foreach ($suppliers as $s): ?>
                      <option value="<?= e($s['id']) ?>"><?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>
          </div>

          <hr class="my-3">

          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label-erp form-label">Unit</label>
              <select name="unit" id="f-unit" class="form-select form-control-erp">
                <option value="pcs">pcs</option>
                <option value="box">box</option>
                <option value="roll">roll</option>
                <option value="carton">carton</option>
                <option value="kg">kg</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label-erp form-label">Cost Price / Unit (RM) <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text price-prefix">RM</span>
                <input type="number" name="price_per_unit" id="f-price" class="form-control form-control-erp price-input" min="0" step="0.01" required value="0.00" placeholder="0.00">
              </div>
              <div class="form-text">What you pay the supplier.</div>
            </div>
            <div class="col-md-3">
              <label class="form-label-erp form-label">Selling Price / Unit (RM) <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text price-prefix selling">RM</span>
                <input type="number" name="selling_price" id="f-selling" class="form-control form-control-erp price-input" min="0" step="0.01" required value="0.00" placeholder="0.00">
              </div>
              <div class="form-text" id="marginHint">What the customer pays.</div>
            </div>
            <div class="col-md-3">
              <label class="form-label-erp form-label">Storage Location</label>
              <input type="text" name="storage_location" id="f-location" class="form-control form-control-erp" placeholder="e.g. Warehouse A - Rack 3">
            </div>
            <div class="col-md-3">
              <label class="form-label-erp form-label">QR Code</label>
              <input type="text" name="qr_code" id="f-qr" class="form-control form-control-erp" placeholder="Leave blank to use the SKU">
              <div class="form-text">Only fill this in if the product already carries a printed QR code with a different value.</div>
            </div>
          </div>

          <div class="row g-3 mt-0">
            <div class="col-md-3">
              <label class="form-label-erp form-label">Current Stock <span class="text-danger">*</span></label>
              <input type="number" name="current_stock" id="f-current" class="form-control form-control-erp" min="0" required value="0">
            </div>
            <div class="col-md-3">
              <label class="form-label-erp form-label">Minimum Stock <span class="text-danger">*</span></label>
              <input type="number" name="min_stock" id="f-min" class="form-control form-control-erp" min="0" required value="0">
            </div>
          </div>

          <div class="mt-3">
            <label class="form-label-erp form-label">Description</label>
            <textarea name="description" id="f-description" class="form-control form-control-erp" rows="3" placeholder="Short notes about this product — material, size, or usage."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-erp-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-erp-primary">Save Product</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$pageScripts = ['assets/js/products.js'];
require_once __DIR__ . '/../includes/footer.php';
?>