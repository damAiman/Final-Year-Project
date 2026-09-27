document.addEventListener('DOMContentLoaded', function () {
  var searchInput    = document.getElementById('searchInput');
  var searchClear    = document.getElementById('searchClear');
  var dropdown       = document.getElementById('searchDropdown');
  var stateSearch     = document.getElementById('stateSearch');
  var stateSelected   = document.getElementById('stateSelected');
  var backToSearch    = document.getElementById('backToSearch');
  var cancelBtn        = document.getElementById('cancelBtn');
  var productIdInput  = document.getElementById('productId');
  var qtyInput         = document.getElementById('qtyInput'); // only present on Stock Out
  var qtyHint          = document.getElementById('qtyHint');   // only present on Stock Out

  if (!searchInput || typeof STOCK_MODE === 'undefined') {
    return;
  }

  var searchTimer = null;

  function statusBadgeClass(cls) {
    return 'badge-status ' + cls;
  }

  function renderResults(items) {
    if (!items.length) {
      dropdown.innerHTML = '<div class="search-empty">No products match your search. Try a different name.</div>';
      dropdown.classList.add('open');
      return;
    }
    dropdown.innerHTML = items.map(function (p) {
      return (
        '<div class="search-result-item" data-product=\'' + JSON.stringify(p).replace(/'/g, '&#39;') + '\'>' +
          '<img class="sr-thumb" src="' + p.image + '" alt="">' +
          '<div class="flex-grow-1" style="min-width:0;">' +
            '<div class="sr-name">' + escapeHtml(p.name) + '</div>' +
            '<div class="sr-meta">' + escapeHtml(p.category) + ' &middot; ' + escapeHtml(p.supplier) + '</div>' +
          '</div>' +
          '<div class="sr-stock">' + p.current_stock.toLocaleString() + ' ' + escapeHtml(p.unit) + '</div>' +
        '</div>'
      );
    }).join('');
    dropdown.classList.add('open');

    dropdown.querySelectorAll('.search-result-item').forEach(function (item) {
      item.addEventListener('click', function () {
        var product = JSON.parse(item.dataset.product.replace(/&#39;/g, "'"));
        selectProduct(product);
      });
    });
  }

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
  }

  function fetchResults(q) {
    fetch(APP_BASE_URL + 'api/search_products.php?q=' + encodeURIComponent(q))
      .then(function (res) { return res.json(); })
      .then(renderResults)
      .catch(function () {
        dropdown.innerHTML = '<div class="search-empty">Could not load products. Please check your connection.</div>';
        dropdown.classList.add('open');
      });
  }

  searchInput.addEventListener('input', function () {
    searchClear.style.display = searchInput.value ? 'flex' : 'none';
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function () { fetchResults(searchInput.value); }, 180);
  });
  searchInput.addEventListener('focus', function () { fetchResults(searchInput.value); });
  searchClear.addEventListener('click', function () {
    searchInput.value = '';
    searchClear.style.display = 'none';
    fetchResults('');
    searchInput.focus();
  });
  document.addEventListener('click', function (e) {
    if (!e.target.closest('.search-combo-wrap')) {
      dropdown.classList.remove('open');
    }
  });
  searchInput.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') dropdown.classList.remove('open');
  });

  function selectProduct(p) {
    dropdown.classList.remove('open');
    productIdInput.value = p.id;

    // Main confirmation card
    setText('pName', p.name);
    setText('pSku', 'SKU: ' + p.sku);
    setText('pCategory', p.category);
    setText('pSupplier', p.supplier);
    setText('pStock', p.current_stock.toLocaleString() + ' ' + p.unit);
    setText('pUnit', p.unit);
    setText('pLocation', p.location || '—');
    var pImage = document.getElementById('pImage');
    if (pImage) pImage.src = p.image;
    var pStatus = document.getElementById('pStatus');
    if (pStatus) { pStatus.textContent = p.status_label; pStatus.className = statusBadgeClass(p.status_class); }

    // Side panel preview (mirrors the same data)
    setText('sName', p.name);
    setText('sSku', 'SKU: ' + p.sku);
    setText('sCategory', p.category);
    setText('sSupplier', p.supplier);
    setText('sStock', p.current_stock.toLocaleString() + ' ' + p.unit);
    var sImage = document.getElementById('sImage');
    if (sImage) sImage.src = p.image;
    var sStatus = document.getElementById('sStatus');
    if (sStatus) { sStatus.textContent = p.status_label; sStatus.className = statusBadgeClass(p.status_class); }
    document.getElementById('sidePlaceholder').style.display = 'none';
    document.getElementById('sideDetails').style.display = 'block';

    if (STOCK_MODE === 'out' && qtyInput) {
      qtyInput.max = p.current_stock;
      qtyHint.textContent = p.current_stock.toLocaleString() + ' ' + p.unit + ' currently available.';
    }

    stateSearch.style.display = 'none';
    stateSelected.style.display = 'block';
  }

  function setText(id, value) {
    var el = document.getElementById(id);
    if (el) el.textContent = value;
  }

  function resetToSearch(e) {
    if (e) e.preventDefault();
    stateSelected.style.display = 'none';
    stateSearch.style.display = 'block';
    productIdInput.value = '';
    searchInput.value = '';
    searchInput.focus();
  }

  backToSearch.addEventListener('click', resetToSearch);
  cancelBtn.addEventListener('click', resetToSearch);

  // Client-side sanity check for Stock Out (server re-validates authoritatively)
  var stockForm = document.getElementById('stockForm');
  if (STOCK_MODE === 'out' && stockForm) {
    stockForm.addEventListener('submit', function (e) {
      var qty = parseInt(qtyInput.value, 10);
      var max = parseInt(qtyInput.max, 10);
      if (!isNaN(max) && qty > max) {
        e.preventDefault();
        alert('Quantity exceeds available stock (' + max.toLocaleString() + ' available).');
      }
    });
  }

  /* Deep link support: ?product_id=12 (used by the Restock button on the
     Low Stock Alerts page) opens straight into the transaction form. */
  if (typeof PRESELECT_PRODUCT !== 'undefined' && PRESELECT_PRODUCT) {
    selectProduct(PRESELECT_PRODUCT);
  }
});
