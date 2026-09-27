document.addEventListener('DOMContentLoaded', function () {
  Chart.defaults.font.family = "'Inter', sans-serif";
  Chart.defaults.font.size = 12;
  Chart.defaults.color = '#66748C';

  var monthlyEl = document.getElementById('chartMonthly');
  if (monthlyEl && typeof CHART_MONTH_LABELS !== 'undefined') {
    new Chart(monthlyEl, {
      type: 'line',
      data: {
        labels: CHART_MONTH_LABELS,
        datasets: [
          {
            label: 'Stock In',
            data: CHART_MONTHLY_IN,
            borderColor: '#7429A0',
            backgroundColor: 'rgba(20,98,214,0.08)',
            fill: true, tension: 0.35, pointRadius: 4, borderWidth: 2.5,
            pointBackgroundColor: '#7429A0', pointBorderColor: '#fff', pointBorderWidth: 2
          },
          {
            label: 'Stock Out',
            data: CHART_MONTHLY_OUT,
            borderColor: '#D9B65C',
            backgroundColor: 'rgba(240,153,123,0.08)',
            fill: true, tension: 0.35, pointRadius: 4, borderWidth: 2.5, borderDash: [6, 3],
            pointBackgroundColor: '#D9B65C', pointBorderColor: '#fff', pointBorderWidth: 2
          }
        ]
      },
      options: {
        responsive: true,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, pointStyle: 'circle' } } },
        scales: {
          y: { grid: { color: '#F0EAF6' }, border: { display: false } },
          x: { grid: { display: false }, border: { display: false } }
        }
      }
    });
  }

  var categoryEl = document.getElementById('chartCategory');
  if (categoryEl && typeof CHART_CATEGORY_LABELS !== 'undefined' && CHART_CATEGORY_LABELS.length) {
    var palette = ['#7429A0', '#8E3FBE', '#A96BCF', '#C6A0DF', '#DFC6EE', '#F0E6F8', '#7C4DDB', '#C5790E'];
    new Chart(categoryEl, {
      type: 'doughnut',
      data: {
        labels: CHART_CATEGORY_LABELS,
        datasets: [{ data: CHART_CATEGORY_DATA, backgroundColor: palette, borderWidth: 3, borderColor: '#fff' }]
      },
      options: {
        responsive: true, cutout: '68%',
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, font: { size: 10.5 } } } }
      }
    });
  }

  /* ---- Sales trend, last 7 days (real-time sales monitoring) ---- */
  var trendEl = document.getElementById('chartSalesTrend');
  if (trendEl && typeof CHART_TREND_DATA !== 'undefined') {
    new Chart(trendEl, {
      type: 'line',
      data: {
        labels: CHART_TREND_LABELS,
        datasets: [{
          label: 'Sales (RM)',
          data: CHART_TREND_DATA,
          borderColor: '#12966B',
          backgroundColor: 'rgba(18,150,107,0.14)',
          borderWidth: 2.5, tension: 0.35, fill: true,
          pointRadius: 4, pointBackgroundColor: '#12966B',
          pointBorderColor: '#fff', pointBorderWidth: 2
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#2F0E44', padding: 10, cornerRadius: 8,
            callbacks: {
              label: function (c) { return 'RM ' + Number(c.parsed.y).toLocaleString(undefined, { minimumFractionDigits: 2 }); }
            }
          }
        },
        scales: {
          y: { beginAtZero: true, grid: { color: '#F0EAF6' }, border: { display: false },
               ticks: { callback: function (v) { return 'RM ' + (v >= 1000 ? (v / 1000) + 'k' : v); } } },
          x: { grid: { display: false }, border: { display: false } }
        }
      }
    });
  }

  /* ---- Monthly inventory movement: running net stock level ---- */
  var netEl = document.getElementById('chartNetMovement');
  if (netEl && typeof CHART_NET_SERIES !== 'undefined') {
    new Chart(netEl, {
      type: 'line',
      data: {
        labels: CHART_MONTH_LABELS,
        datasets: [{
          label: 'Net Stock Level',
          data: CHART_NET_SERIES,
          borderColor: '#7429A0',
          backgroundColor: 'rgba(20,98,214,0.12)',
          borderWidth: 2.5, tension: 0.4, fill: true,
          pointRadius: 4, pointBackgroundColor: '#7429A0',
          pointBorderColor: '#fff', pointBorderWidth: 2
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false }, tooltip: { backgroundColor: '#2F0E44', padding: 10, cornerRadius: 8 } },
        scales: {
          y: { grid: { color: '#F0EAF6' }, border: { display: false },
               ticks: { callback: function (v) { return v >= 1000 ? (v / 1000) + 'k' : v; } } },
          x: { grid: { display: false }, border: { display: false } }
        }
      }
    });
  }

  /* ---- Top restocked products ---- */
  var restockEl = document.getElementById('chartRestocked');
  if (restockEl && typeof CHART_RESTOCK_LABELS !== 'undefined' && CHART_RESTOCK_LABELS.length) {
    new Chart(restockEl, {
      type: 'bar',
      data: {
        labels: CHART_RESTOCK_LABELS,
        datasets: [{ data: CHART_RESTOCK_DATA, backgroundColor: '#12966B', borderRadius: 6, maxBarThickness: 30 }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false }, tooltip: { backgroundColor: '#2F0E44', padding: 10, cornerRadius: 8 } },
        scales: {
          y: { beginAtZero: true, grid: { color: '#F0EAF6' }, border: { display: false },
               ticks: { callback: function (v) { return v >= 1000 ? (v / 1000) + 'k' : v; } } },
          x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 10 }, maxRotation: 25 } }
        }
      }
    });
  }
});
