document.addEventListener('DOMContentLoaded', function () {
  var modalEl   = document.getElementById('productModal');
  var modal     = modalEl ? new bootstrap.Modal(modalEl) : null;
  var form      = document.getElementById('productForm');
  var titleEl   = document.getElementById('productModalTitle');
  var uploadZone    = document.getElementById('uploadZone');
  var uploadInput   = document.getElementById('f-image');
  var uploadPreview = document.getElementById('uploadPreview');

  var fields = {
    id: document.getElementById('f-id'),
    name: document.getElementById('f-name'),
    category: document.getElementById('f-category'),
    supplier: document.getElementById('f-supplier'),
    unit: document.getElementById('f-unit'),
    price: document.getElementById('f-price'),
    selling: document.getElementById('f-selling'),
    current: document.getElementById('f-current'),
    min: document.getElementById('f-min'),
    location: document.getElementById('f-location'),
    qr: document.getElementById('f-qr'),
    description: document.getElementById('f-description'),
  };

  function resetForm() {
    form.reset();
    fields.id.value = '';
    uploadZone.classList.remove('has-image');
    uploadPreview.src = '';
    titleEl.textContent = 'Add Product';
    updateMargin();
  }

  /* "Add Product" button always opens a clean form */
  var addBtn = document.getElementById('btnAddProduct');
  if (addBtn) {
    addBtn.addEventListener('click', resetForm);
  }

  /* Populate the modal from the row's data-* attributes when editing */
  document.querySelectorAll('.btn-edit-product').forEach(function (btn) {
    btn.addEventListener('click', function () {
      resetForm();
      titleEl.textContent = 'Edit Product';
      fields.id.value          = btn.dataset.id || '';
      fields.name.value        = btn.dataset.name || '';
      fields.category.value    = btn.dataset.category || '';
      fields.supplier.value    = btn.dataset.supplier || '';
      fields.unit.value        = btn.dataset.unit || 'pcs';
      fields.price.value       = btn.dataset.price || '0.00';
      fields.selling.value     = btn.dataset.selling || '0.00';
      fields.current.value     = btn.dataset.current || 0;
      fields.min.value         = btn.dataset.min || 0;
      fields.location.value    = btn.dataset.location || '';
      fields.qr.value          = btn.dataset.qr || '';
      fields.description.value = btn.dataset.description || '';
      updateMargin();

      if (btn.dataset.image && btn.dataset.image.indexOf('placeholder-product.svg') === -1) {
        uploadPreview.src = btn.dataset.image;
        uploadZone.classList.add('has-image');
      }

      modal.show();
    });
  });

  /* Image upload zone: click to browse, live preview, drag & drop */
  if (uploadZone && uploadInput) {
    uploadZone.addEventListener('click', function () { uploadInput.click(); });

    uploadZone.addEventListener('dragover', function (e) {
      e.preventDefault();
      uploadZone.style.borderColor = '#8E3FBE';
    });
    uploadZone.addEventListener('dragleave', function () {
      uploadZone.style.borderColor = '';
    });
    uploadZone.addEventListener('drop', function (e) {
      e.preventDefault();
      uploadZone.style.borderColor = '';
      if (e.dataTransfer.files && e.dataTransfer.files[0]) {
        uploadInput.files = e.dataTransfer.files;
        previewFile(e.dataTransfer.files[0]);
      }
    });

    uploadInput.addEventListener('change', function () {
      if (uploadInput.files && uploadInput.files[0]) {
        previewFile(uploadInput.files[0]);
      }
    });
  }

  /* Live profit margin hint under the selling price field */
  var marginHint = document.getElementById('marginHint');
  function updateMargin() {
    if (!marginHint) return;
    var cost = parseFloat(fields.price.value) || 0;
    var sell = parseFloat(fields.selling.value) || 0;
    if (cost <= 0 || sell <= 0) {
      marginHint.textContent = 'What the customer pays.';
      marginHint.className = 'form-text';
      return;
    }
    var profit = sell - cost;
    var margin = (profit / cost) * 100;
    marginHint.textContent = 'Profit: RM ' + profit.toFixed(2) + ' per unit (' +
      (margin >= 0 ? '+' : '') + margin.toFixed(0) + '%)';
    marginHint.className = 'form-text fw-semibold ' + (profit >= 0 ? 'text-success' : 'text-danger');
  }
  if (fields.price && fields.selling) {
    fields.price.addEventListener('input', updateMargin);
    fields.selling.addEventListener('input', updateMargin);
  }

  function previewFile(file) {
    var reader = new FileReader();
    reader.onload = function (e) {
      uploadPreview.src = e.target.result;
      uploadZone.classList.add('has-image');
    };
    reader.readAsDataURL(file);
  }
});
