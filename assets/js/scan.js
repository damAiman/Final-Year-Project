/* SMART STOCK -- QR scanning via the browser camera.
   Uses jsQR to decode frames grabbed from a <video> element. No app install,
   no QR generation -- it only reads the codes already printed on products. */
document.addEventListener('DOMContentLoaded', function () {

  var idleBox   = document.getElementById('scanIdle');
  var camBox    = document.getElementById('scanCamera');
  var resultBox = document.getElementById('scanResult');
  if (!idleBox || !camBox || !resultBox) return;

  var video     = document.getElementById('scanVideo');
  var statusTxt = document.getElementById('scanStatusText');
  var errorBox  = document.getElementById('scanError');

  var canvas = document.createElement('canvas');
  var ctx    = canvas.getContext('2d', { willReadFrequently: true });

  var stream = null;
  var rafId  = null;
  var busy   = false;

  function show(which) {
    idleBox.style.display   = which === 'idle'   ? '' : 'none';
    camBox.style.display    = which === 'camera' ? '' : 'none';
    resultBox.style.display = which === 'result' ? '' : 'none';
  }

  function showError(msg) {
    errorBox.textContent = msg;
    errorBox.classList.remove('d-none');
  }
  function clearError() {
    errorBox.classList.add('d-none');
  }

  /* ---------------- camera lifecycle ---------------- */
  function startScan() {
    clearError();
    show('camera');
    statusTxt.textContent = 'Starting camera…';

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      show('idle');
      alert('This browser does not support camera access. Please use the manual code entry instead.');
      return;
    }

    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
      .then(function (s) {
        stream = s;
        video.srcObject = s;
        video.setAttribute('playsinline', true);
        return video.play();
      })
      .then(function () {
        statusTxt.textContent = 'Looking for a QR code…';
        busy = false;
        rafId = requestAnimationFrame(tick);
      })
      .catch(function () {
        show('idle');
        alert('Camera access was blocked or unavailable.\n\nAllow camera permission in your browser, or use the manual code entry link.');
      });
  }

  function stopScan() {
    if (rafId) { cancelAnimationFrame(rafId); rafId = null; }
    if (stream) {
      stream.getTracks().forEach(function (t) { t.stop(); });
      stream = null;
    }
    video.srcObject = null;
  }

  function tick() {
    if (busy) { rafId = requestAnimationFrame(tick); return; }

    if (video.readyState === video.HAVE_ENOUGH_DATA && window.jsQR) {
      canvas.width  = video.videoWidth;
      canvas.height = video.videoHeight;
      ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
      var img = ctx.getImageData(0, 0, canvas.width, canvas.height);
      var code = jsQR(img.data, img.width, img.height);
      if (code && code.data) {
        busy = true;
        statusTxt.textContent = 'QR code detected — looking up product…';
        lookup(code.data);
        return;
      }
    }
    rafId = requestAnimationFrame(tick);
  }

  /* ---------------- lookup + fill the form ---------------- */
  function lookup(codeText) {
    fetch(APP_BASE_URL + 'api/lookup_qr.php?code=' + encodeURIComponent(codeText), {
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok) {
          showError(data.message || 'That QR code was not recognised.');
          statusTxt.textContent = 'Looking for a QR code…';
          setTimeout(function () { clearError(); busy = false; }, 2500);
          return;
        }
        stopScan();
        fillResult(data.product);
        show('result');
      })
      .catch(function () {
        showError('Could not reach the server. Check that XAMPP is running.');
        setTimeout(function () { clearError(); busy = false; }, 2500);
      });
  }

  function fillResult(p) {
    document.getElementById('rsProductId').value = p.id;
    document.getElementById('rsImage').src       = p.image;
    document.getElementById('rsImage').alt       = p.name;
    document.getElementById('rsName').textContent     = p.name;
    document.getElementById('rsSku').textContent      = 'SKU: ' + p.sku;
    document.getElementById('rsCategory').textContent = p.category;
    document.getElementById('rsSupplier').textContent = p.supplier;
    document.getElementById('rsStock').textContent    = p.current_stock.toLocaleString() + ' ' + p.unit;

    var badge = document.getElementById('rsStatus');
    badge.textContent = p.status_label;
    badge.className   = 'badge-status ' + p.status_class;

    var qty  = document.getElementById('rsQty');
    var hint = document.getElementById('rsQtyHint');
    qty.value = '';
    if (SCAN_MODE === 'out') {
      qty.max = p.current_stock;
      hint.textContent = 'Available: ' + p.current_stock.toLocaleString() + ' ' + p.unit;
    } else {
      qty.removeAttribute('max');
      hint.textContent = '';
    }
    qty.focus();
  }

  /* ---------------- buttons ---------------- */
  document.getElementById('btnStartScan').addEventListener('click', startScan);

  document.getElementById('btnStopScan').addEventListener('click', function () {
    stopScan();
    show('idle');
  });

  document.getElementById('btnScanAgain').addEventListener('click', function (e) {
    e.preventDefault();
    show('idle');
  });

  document.getElementById('btnCancelResult').addEventListener('click', function () {
    show('idle');
  });

  document.getElementById('btnManualCode').addEventListener('click', function (e) {
    e.preventDefault();
    var entry = prompt('Enter the code printed on the product label (e.g. BTL-PET-500):');
    if (entry) {
      busy = true;
      lookup(entry.trim());
    }
  });

  /* Release the camera if the user navigates away or hides the tab. */
  window.addEventListener('beforeunload', stopScan);
  document.addEventListener('visibilitychange', function () {
    if (document.hidden && stream) {
      stopScan();
      show('idle');
    }
  });
});