// Dashboard analytics and charts and graph.

var analyticsChartJsMissing = (typeof Chart === 'undefined');
if (analyticsChartJsMissing) {
    console.error('Chart.js is not loaded. Please ensure Chart.js is included before analytics.js');
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('#myPieChart, #earningsLineChart').forEach(function (container) {
            if (!container) {
                return;
            }
            container.style.display = 'flex';
            container.style.alignItems = 'center';
            container.style.justifyContent = 'center';
            container.style.height = '200px';
            container.style.backgroundColor = '#f8f9fa';
            container.style.border = '1px solid #dee2e6';
            container.style.borderRadius = '4px';
            container.innerHTML = '<div style="text-align: center; color: #6c757d;"><strong>Chart.js Error</strong><br>Chart library not loaded properly</div>';
        });
        if (window.__adminDashSkeleton) {
            window.__adminDashSkeleton.markPieReady();
            window.__adminDashSkeleton.markLineReady();
            window.__adminDashSkeleton.markRangeReady();
        }
    });
}

function setAdminDashboardLoading(loading) {
  var wrap = document.querySelector('.admin-dashboard .reports-page');
  var skel = document.getElementById('reportsDashboardSkeleton');
  var hasSkeleton = !!skel;
  if (wrap) {
    wrap.classList.toggle('reports-loading', !!loading && hasSkeleton);
  }
  if (skel) {
    skel.setAttribute('aria-hidden', loading ? 'false' : 'true');
    skel.setAttribute('aria-busy', loading ? 'true' : 'false');
  }
  if (!loading && hasSkeleton) {
    refreshAdminDashboardCharts();
  }
}

function refreshAdminDashboardCharts() {
  window.requestAnimationFrame(function () {
    window.requestAnimationFrame(function () {
      var charts = [window.earningsLineChart, window.myDoughnutChart];
      charts.forEach(function (chart) {
        if (chart && typeof chart.resize === 'function') {
          chart.resize();
          chart.update();
        }
      });
    });
  });
}

(function initAdminDashboardSkeleton() {
  if (!document.querySelector('.admin-dashboard') || !document.getElementById('reportsDashboardSkeleton')) {
    return;
  }

  setAdminDashboardLoading(true);

  var pieReady = false;
  var lineReady = false;
  var revealed = false;

  function revealNow() {
    if (revealed) return;
    revealed = true;
    setAdminDashboardLoading(false);
  }

  function tryFinishAdminDashboardLoading() {
    // Do not wait for monthly_range / invoice_overview — live HTML is already in the DOM.
    if (pieReady && lineReady) {
      revealNow();
    }
  }

  window.__adminDashSkeleton = {
    markPieReady: function () {
      pieReady = true;
      tryFinishAdminDashboardLoading();
    },
    markLineReady: function () {
      lineReady = true;
      tryFinishAdminDashboardLoading();
    },
    markRangeReady: function () {
      // Range charts are progressive; never block first paint on them.
      tryFinishAdminDashboardLoading();
    }
  };

  // Fail-safe: show live dashboard quickly even if Chart.js is slow
  window.setTimeout(revealNow, 1200);
})();

// PIE CHART (Total Projects)
document.addEventListener('DOMContentLoaded', function() {
  if (analyticsChartJsMissing || typeof Chart === 'undefined') {
    if (window.__adminDashSkeleton) {
      window.__adminDashSkeleton.markPieReady();
    }
    return;
  }
  try {
  var rootStyles = getComputedStyle(document.documentElement);
  var completedColor = rootStyles.getPropertyValue('--secondary-color').trim() || '#1cc88a';
  var inProgressColor = rootStyles.getPropertyValue('--primary-color').trim() || '#e74a3b';
  var borderColor = rootStyles.getPropertyValue('--card-body-color').trim() || '#c0c0c0';

  var myPieChart = document.getElementById('myPieChart');
  if (!myPieChart) {
    if (window.__adminDashSkeleton) {
      window.__adminDashSkeleton.markPieReady();
    }
    return;
  }

  var ctx = myPieChart.getContext('2d');
  var totalProjects = window.totalProjects;
  var projectsCompleted = window.projectsCompleted;
  var projectsInProgress = window.projectsInProgress;
  var completedLabel = window.completedLabel;
  var inprogressLabel = window.inprogressLabel;

  var chartData, chartLabels, chartColors, tooltipsEnabled;
  var useRoundedSegments = totalProjects > 0 && !!document.querySelector('.admin-dashboard, .client-dashboard, .staff-dashboard');
  if (totalProjects === 0) {
    chartLabels = ['No Projects'];
    chartData = [1];
    chartColors = ['#e9ecef'];
    tooltipsEnabled = false;
  } else {
    chartLabels = [completedLabel, inprogressLabel];
    chartData = [projectsCompleted, projectsInProgress];
    chartColors = [completedColor, inProgressColor];
    tooltipsEnabled = true;
  }

  if (useRoundedSegments && !Chart._comonDashboardRoundedDonut) {
    Chart._comonDashboardRoundedDonut = true;

    function limitValue(v, min, max) {
      return Math.max(min, Math.min(max, v));
    }

    function drawAnnularSegmentBorderRadius(ctx, x, y, innerR, outerR, startAngle, endAngle, color, gapRad, borderRadius) {
      var halfThick = (outerR - innerR) / 2;
      var br = Math.min(borderRadius, halfThick);
      var start = startAngle + gapRad / 2;
      var end = endAngle - gapRad / 2;
      if (end <= start) {
        return;
      }

      var angleDelta = end - start;
      var innerLimit = Math.min(halfThick, angleDelta * innerR / 2);
      var computeOuterLimit = function (val) {
        var outerArcLimit = (outerR - Math.min(halfThick, val)) * angleDelta / 2;
        return limitValue(val, 0, Math.min(halfThick, outerArcLimit));
      };
      var outerStart = computeOuterLimit(br);
      var outerEnd = computeOuterLimit(br);
      var innerStart = limitValue(br, 0, innerLimit);
      var innerEnd = limitValue(br, 0, innerLimit);

      var outerStartAdjR = outerR - outerStart;
      var outerEndAdjR = outerR - outerEnd;
      var outerStartAdjAngle = start + outerStart / outerStartAdjR;
      var outerEndAdjAngle = end - outerEnd / outerEndAdjR;
      var innerStartAdjR = innerR + innerStart;
      var innerEndAdjR = innerR + innerEnd;
      var innerStartAdjAngle = start + innerStart / innerStartAdjR;
      var innerEndAdjAngle = end - innerEnd / innerEndAdjR;
      var HALF_PI = Math.PI / 2;

      function rThetaToXY(r, theta) {
        return { x: x + r * Math.cos(theta), y: y + r * Math.sin(theta) };
      }

      ctx.fillStyle = color;
      ctx.beginPath();
      ctx.arc(x, y, outerR, outerStartAdjAngle, outerEndAdjAngle);

      if (outerEnd > 0) {
        var pCenterEnd = rThetaToXY(outerEndAdjR, outerEndAdjAngle);
        ctx.arc(pCenterEnd.x, pCenterEnd.y, outerEnd, outerEndAdjAngle, end + HALF_PI);
      }

      var pInnerEnd = rThetaToXY(innerEndAdjR, end);
      ctx.lineTo(pInnerEnd.x, pInnerEnd.y);

      if (innerEnd > 0) {
        var pCenterInnerEnd = rThetaToXY(innerEndAdjR, innerEndAdjAngle);
        ctx.arc(pCenterInnerEnd.x, pCenterInnerEnd.y, innerEnd, end + HALF_PI, innerEndAdjAngle + Math.PI);
      }

      ctx.arc(x, y, innerR, innerEndAdjAngle, innerStartAdjAngle, true);

      if (innerStart > 0) {
        var pCenterInnerStart = rThetaToXY(innerStartAdjR, innerStartAdjAngle);
        ctx.arc(pCenterInnerStart.x, pCenterInnerStart.y, innerStart, innerStartAdjAngle + Math.PI, start - HALF_PI);
      }

      var pOuterStart = rThetaToXY(outerStartAdjR, start);
      ctx.lineTo(pOuterStart.x, pOuterStart.y);

      if (outerStart > 0) {
        var pCenterOuterStart = rThetaToXY(outerStartAdjR, outerStartAdjAngle);
        ctx.arc(pCenterOuterStart.x, pCenterOuterStart.y, outerStart, start - HALF_PI, outerStartAdjAngle);
      }

      ctx.closePath();
      ctx.fill();
    }

    Chart.pluginService.register({
      beforeDatasetsDraw: function (chart) {
        if (!chart.config.options.comonRoundedDoughnut) {
          return;
        }
        var meta = chart.getDatasetMeta(0);
        if (!meta || !meta.data) {
          return;
        }
        meta.data.forEach(function (arc) {
          if (!arc || !arc._model) {
            return;
          }
          if (!arc._comonSavedBg) {
            arc._comonSavedBg = arc._model.backgroundColor;
          }
          arc._model.backgroundColor = 'rgba(0,0,0,0)';
          arc._model.borderWidth = 0;
        });
      },
      afterDatasetsDraw: function (chart) {
        if (!chart.config.options.comonRoundedDoughnut) {
          return;
        }
        var gapDeg = chart.config.options.comonRoundedDoughnutGap || 2;
        var gapRad = gapDeg * Math.PI / 180;
        var capScale = chart.config.options.comonRoundedDoughnutCapScale;
        if (capScale == null) {
          capScale = 0.5;
        }
        var ctx = chart.chart.ctx;
        var meta = chart.getDatasetMeta(0);
        if (!meta || !meta.data) {
          return;
        }
        var arcs = meta.data.filter(function (arc) {
          return arc && !arc.hidden && arc._view && arc._view.circumference > 0;
        }).sort(function (a, b) {
          return a._view.circumference - b._view.circumference;
        });
        arcs.forEach(function (arc) {
          var vm = arc._view;
          var thickness = vm.outerRadius - vm.innerRadius;
          if (thickness <= 0) {
            return;
          }
          var borderRadius = (thickness / 2) * capScale;
          var strokeColor = arc._comonSavedBg;
          if (!strokeColor) {
            var bg = chart.data.datasets[0].backgroundColor;
            strokeColor = Array.isArray(bg) ? bg[arc._index] : bg;
          }
          ctx.save();
          drawAnnularSegmentBorderRadius(
            ctx,
            vm.x,
            vm.y,
            vm.innerRadius,
            vm.outerRadius,
            vm.startAngle,
            vm.endAngle,
            strokeColor,
            gapRad,
            borderRadius
          );
          ctx.restore();
        });
      }
    });
  }

  window.myDoughnutChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: chartLabels,
      datasets: [{
        data: chartData,
        backgroundColor: chartColors,
        hoverBackgroundColor: chartColors,
        borderColor: borderColor,
        hoverBorderColor: borderColor,
        borderWidth: useRoundedSegments ? 0 : 1
      }]
    },
    options: {
      cutoutPercentage: 77,
      responsive: true,
      maintainAspectRatio: false,
      legend: { display: false },
      comonRoundedDoughnut: useRoundedSegments,
      comonRoundedDoughnutGap: 2,
      comonRoundedDoughnutCapScale: 0.5,
      tooltips: {
        enabled: tooltipsEnabled,
        backgroundColor: '#fff',
        titleFontColor: '#888',
        titleFontSize: 14,
        titleFontStyle: 'normal',
        bodyFontColor: '#222',
        bodyFontSize: 12,
        bodyFontStyle: 'bold',
        borderColor: '#eee',
        borderWidth: 1,
        xPadding: 12,
        yPadding: 12,
        caretPadding: 10,
        displayColors: false,
        cornerRadius: 10,
        callbacks: {
          title: function() { return ''; },
          label: function(tooltipItem, data) {
            var label = data.labels[tooltipItem.index] || '';
            var value = data.datasets[0].data[tooltipItem.index] || '';
            return label + ' ' + value;
          }
        }
      }
    }
  });
  if (window.__adminDashSkeleton) {
    window.__adminDashSkeleton.markPieReady();
  }
  } catch (pieErr) {
    console.error('Admin dashboard pie chart failed:', pieErr);
    if (window.__adminDashSkeleton) {
      window.__adminDashSkeleton.markPieReady();
    }
  }
});

// LINE CHART (Earnings/Unpaid)
var earningsLineChart;
document.addEventListener('DOMContentLoaded', function() {
  if (analyticsChartJsMissing || typeof Chart === 'undefined') {
    if (window.__adminDashSkeleton) {
      window.__adminDashSkeleton.markLineReady();
    }
    return;
  }
  try {
  var rootStyles = getComputedStyle(document.documentElement);
  // Match sparkline widget colors (paid green / unpaid red)
  var primaryColor = '#22c55e';
  var secondaryColor = '#ef4444';
  var cancelledColor = '#adb5bd';
  var borderColorRaw = rootStyles.getPropertyValue('--border-color').trim() || '#c0c0c0';
  var cardBodyColor = rootStyles.getPropertyValue('--card-body-color').trim() || '#fff';

  function darkenHexColor(color, amount) {
    var ratio = amount == null ? 0.2 : amount;
    if (!color) return color;
    var c = String(color).trim();
    var rgbMatch = c.match(/^rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)/i);
    if (rgbMatch) {
      var factor = 1 - ratio;
      return 'rgb(' + Math.round(Number(rgbMatch[1]) * factor) + ',' +
        Math.round(Number(rgbMatch[2]) * factor) + ',' +
        Math.round(Number(rgbMatch[3]) * factor) + ')';
    }
    var hex = c.replace('#', '');
    if (hex.length === 3) {
      hex = hex.split('').map(function (ch) { return ch + ch; }).join('');
    }
    if (!/^[0-9a-fA-F]{6}$/.test(hex)) {
      return c;
    }
    var f = 1 - ratio;
    var r = Math.round(parseInt(hex.slice(0, 2), 16) * f);
    var g = Math.round(parseInt(hex.slice(2, 4), 16) * f);
    var b = Math.round(parseInt(hex.slice(4, 6), 16) * f);
    return '#' + ('0' + r.toString(16)).slice(-2) +
      ('0' + g.toString(16)).slice(-2) +
      ('0' + b.toString(16)).slice(-2);
  }

  var borderColor = darkenHexColor(borderColorRaw, 0.2);
  var tickColor = darkenHexColor('#c0c0c0', 0.2);

  function hexToRgba(hex, alpha) {
    hex = hex.replace('#', '');
    if (hex.length === 3) {
      hex = hex.split('').map(function (h) { return h + h; }).join('');
    }
    var r = parseInt(hex.substring(0,2), 16);
    var g = parseInt(hex.substring(2,4), 16);
    var b = parseInt(hex.substring(4,6), 16);
    return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
  }

  function createSparkAreaGradient(chartCtx, color, height) {
    var gradient = chartCtx.createLinearGradient(0, 0, 0, height || chartCtx.canvas.height);
    gradient.addColorStop(0, hexToRgba(color, 0.35));
    gradient.addColorStop(1, hexToRgba(color, 0));
    return gradient;
  }

  var earningsLineSmooth = 0.4;

  var earningsCanvas = document.getElementById('earningsLineChart');
  if (earningsCanvas) {
    var ctx = earningsCanvas.getContext('2d');
    earningsLineChart = new Chart(ctx, {
    type: 'line',
    plugins: [{
      beforeRender: function(chart) {
        var area = chart.chartArea;
        var height = area ? area.bottom : chart.height;
        chart.data.datasets[0].backgroundColor = createSparkAreaGradient(chart.ctx, primaryColor, height);
        chart.data.datasets[1].backgroundColor = createSparkAreaGradient(chart.ctx, secondaryColor, height);
        if (chart.data.datasets[2]) {
          chart.data.datasets[2].backgroundColor = createSparkAreaGradient(chart.ctx, cancelledColor, height);
        }
      }
    }],
    data: {
      labels: window.monthLabels,
      datasets: [
        {
          label: window.salesPaidLabel || 'Paid',
          data: window.monthlyEarnings,
          borderColor: primaryColor,
          backgroundColor: createSparkAreaGradient(ctx, primaryColor, earningsCanvas.height),
          pointRadius: 4,
          pointHoverRadius: 6,
          pointHitRadius: 10,
          pointBackgroundColor: cardBodyColor,
          pointBorderColor: primaryColor,
          pointBorderWidth: 1.5,
          fill: true,
          lineTension: earningsLineSmooth,
          borderWidth: 1.5,
          borderCapStyle: 'round',
          borderJoinStyle: 'round'
        },
        {
          label: window.salesUnpaidLabel || 'Unpaid',
          data: window.monthlyUnpaid,
          borderColor: secondaryColor,
          backgroundColor: createSparkAreaGradient(ctx, secondaryColor, earningsCanvas.height),
          pointRadius: 4,
          pointHoverRadius: 6,
          pointHitRadius: 10,
          pointBackgroundColor: cardBodyColor,
          pointBorderColor: secondaryColor,
          pointBorderWidth: 1.5,
          fill: true,
          lineTension: earningsLineSmooth,
          borderWidth: 1.5,
          borderCapStyle: 'round',
          borderJoinStyle: 'round'
        },
        {
          label: window.salesCancelledLabel || 'Cancel',
          data: window.monthlyCancelled || [],
          borderColor: cancelledColor,
          backgroundColor: createSparkAreaGradient(ctx, cancelledColor, earningsCanvas.height),
          pointRadius: 4,
          pointHoverRadius: 6,
          pointHitRadius: 10,
          pointBackgroundColor: cardBodyColor,
          pointBorderColor: cancelledColor,
          pointBorderWidth: 1.5,
          fill: false,
          lineTension: earningsLineSmooth,
          borderWidth: 1.5,
          borderDash: [5, 4],
          borderCapStyle: 'round',
          borderJoinStyle: 'round'
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      legend: { display: false },
      tooltips: {
        enabled: true,
        backgroundColor: '#fff',
        titleFontColor: '#888',
        titleFontSize: 14,
        bodyFontColor: '#222',
        bodyFontSize: 14,
        borderColor: '#eee',
        borderWidth: 1,
        xPadding: 16,
        yPadding: 12,
        caretPadding: 10,
        displayColors: false,
        cornerRadius: 10,
        callbacks: {
          title: function(tooltipItem, data) {
            return data.labels[tooltipItem[0].index];
          },
          label: function(tooltipItem, data) {
            var label = data.datasets[tooltipItem.datasetIndex].label || '';
            var value = data.datasets[tooltipItem.datasetIndex].data[tooltipItem.index] || 0;
            var currencySymbol = window.selectedCurrency && window.selectedCurrency !== 'all' ? 
                (window.selectedCurrency.includes(',') ? window.selectedCurrency.split(',')[1] : window.selectedCurrency) : '$';
            return label + ' ' + currencySymbol + value;
          }
        }
      },
      scales: {
        yAxes: [{
          ticks: {
            beginAtZero: true,
            fontColor: tickColor,
            fontSize: 12,
            padding: 10,
            callback: function(value) {
              var currencySymbol = window.selectedCurrency && window.selectedCurrency !== 'all' ? 
                  (window.selectedCurrency.includes(',') ? window.selectedCurrency.split(',')[1] : window.selectedCurrency) : '$';
              return currencySymbol + value;
            }
          },
          gridLines: {
            color: borderColor,
            zeroLineColor: borderColor,
            drawBorder: false,
            lineWidth: 1,
            borderDash: [3, 4],
            zeroLineBorderDash: [3, 4],
            drawOnChartArea: true,
            offsetGridLines: false
          }
        }],
        xAxes: [{
          ticks: {
            fontColor: tickColor,
            fontSize: 10
          },
          gridLines: {
            display: false
          }
        }]
      },
      elements: {
        line: {
          tension: earningsLineSmooth,
          borderCapStyle: 'round',
          borderJoinStyle: 'round',
          borderWidth: 1.5
        },
        point: {
          radius: 4,
          hoverRadius: 6,
          hitRadius: 10,
          backgroundColor: cardBodyColor,
          borderWidth: 1.5
        }
      }
    }
  });
  window.earningsLineChart = earningsLineChart;
  if (window.__adminDashSkeleton) {
    window.__adminDashSkeleton.markLineReady();
  }
  window.requestAnimationFrame(function () {
    if (window.earningsLineChart && typeof window.earningsLineChart.resize === 'function') {
      window.earningsLineChart.resize();
    }
  });
  } else if (window.__adminDashSkeleton) {
    window.__adminDashSkeleton.markLineReady();
  }
  } catch (lineErr) {
    console.error('Admin dashboard line chart failed:', lineErr);
    if (window.__adminDashSkeleton) {
      window.__adminDashSkeleton.markLineReady();
    }
  }
});

// RANGE FILTERS AND AJAX
/** Resolve AJAX script URL (site root from settings avoids ../ issues with rewrites/subfolders). */
function monthlyRangeAjaxUrl() {
    var root = (typeof window.siteRootUrl === 'string' && window.siteRootUrl.length)
        ? window.siteRootUrl.replace(/\/$/, '')
        : '';
    return root ? (root + '/ajax/monthly_range.php') : '../ajax/monthly_range.php';
}

function invoiceOverviewAjaxUrl() {
    var root = (typeof window.siteRootUrl === 'string' && window.siteRootUrl.length)
        ? window.siteRootUrl.replace(/\/$/, '')
        : '';
    return root ? (root + '/ajax/invoice_overview.php') : '../ajax/invoice_overview.php';
}

function refreshInvoiceOverview(startDate, endDate) {
    var card = document.getElementById('invoice-overview-card');
    if (!card) {
        return;
    }
    var start = startDate || (window.currentDateRange && window.currentDateRange.start) || '';
    var end = endDate || (window.currentDateRange && window.currentDateRange.end) || '';
    var xhr = new XMLHttpRequest();
    xhr.open('POST', invoiceOverviewAjaxUrl(), true);
    xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status !== 200) {
            return;
        }
        try {
            var data = JSON.parse(xhr.responseText);
            if (!data || data.error) {
                return;
            }
            // Sparks + % only — amounts/counts stay locked to Sales stats (monthly_range).
            applyInvoiceOverviewData({
                pct_change: data.pct_change,
                paid_line: data.paid_line,
                unpaid_line: data.unpaid_line,
                paid_area: data.paid_area
            });
        } catch (e) {
            console.error('Invoice overview parse error:', e);
        }
    };
    xhr.send(
        buildAnalyticsPostData(start, end)
    );
}

function applyInvoiceOverviewData(data) {
    if (!data || !document.getElementById('invoice-overview-card')) {
        return;
    }
    var badge = document.getElementById('invoice-overview-badge');
    var paidCount = document.getElementById('invoice-overview-paid-count');
    var overdueCount = document.getElementById('invoice-overview-overdue-count');
    var unpaidCount = document.getElementById('invoice-overview-unpaid-count');
    var paidMeta = document.getElementById('invoice-overview-paid-meta');
    var overdueMeta = document.getElementById('invoice-overview-overdue-meta');
    var unpaidMeta = document.getElementById('invoice-overview-unpaid-meta');
    var pctEl = document.getElementById('invoice-overview-pct');
    var paidLabel = window.invoicePaidLabel || 'Paid Invoices';
    var overdueLabel = window.invoiceOverdueLabel || 'Overdue';
    var unpaidLabel = window.invoiceUnpaidLabel || 'Unpaid';
    var symbol = data.currency_symbol || '$';

    var hasCountData = (typeof data.total_count !== 'undefined')
        || (typeof data.paid_count !== 'undefined')
        || (typeof data.unpaid_count !== 'undefined');
    if (badge && hasCountData) {
        var totalCount = (typeof data.total_count !== 'undefined')
            ? Number(data.total_count) || 0
            : ((Number(data.paid_count) || 0) + (Number(data.unpaid_count) || 0));
        badge.textContent = totalCount + ' total';
    }
    if (paidCount && typeof data.paid_count !== 'undefined') {
        paidCount.textContent = String(data.paid_count || 0);
    }
    if (overdueCount && typeof data.overdue_count !== 'undefined') {
        overdueCount.textContent = String(data.overdue_count || 0);
    }
    if (unpaidCount && typeof data.unpaid_count !== 'undefined') {
        unpaidCount.textContent = String(data.unpaid_count || 0);
    }

    var paidFmt = data.paid_amount_fmt;
    var overdueFmt = data.overdue_amount_fmt;
    var unpaidFmt = data.unpaid_amount_fmt;
    if (!paidFmt && typeof data.paid_total !== 'undefined') {
        paidFmt = symbol + Number(data.paid_total || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    if (!overdueFmt && typeof data.overdue_amount !== 'undefined') {
        overdueFmt = symbol + Number(data.overdue_amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    if (!unpaidFmt && typeof data.unpaid_total !== 'undefined') {
        unpaidFmt = symbol + Number(data.unpaid_total || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    if (paidMeta && paidFmt) {
        var paidAmountEl = document.getElementById('invoice-overview-paid-amount');
        if (paidAmountEl) {
            paidAmountEl.textContent = paidFmt;
        } else {
            paidMeta.textContent = paidLabel + ' \u00B7 ' + paidFmt;
        }
    }
    if (overdueMeta && overdueFmt) {
        var overdueAmountEl = document.getElementById('invoice-overview-overdue-amount');
        if (overdueAmountEl) {
            overdueAmountEl.textContent = overdueFmt;
        } else {
            overdueMeta.textContent = overdueLabel + ' \u00B7 ' + overdueFmt;
        }
    }
    if (unpaidMeta && unpaidFmt) {
        var unpaidAmountEl = document.getElementById('invoice-overview-unpaid-amount');
        if (unpaidAmountEl) {
            unpaidAmountEl.textContent = unpaidFmt;
        } else {
            unpaidMeta.textContent = unpaidLabel + ' \u00B7 ' + unpaidFmt;
        }
    }

    if (pctEl && typeof data.pct_change !== 'undefined') {
        var pct = parseInt(data.pct_change, 10) || 0;
        if (pct > 0) {
            pctEl.innerHTML = '<span style="color:green;">+' + pct + '%</span> vs last month';
        } else if (pct < 0) {
            pctEl.innerHTML = '<span style="color:red;">' + Math.abs(pct) + '%</span> vs last month';
        } else {
            pctEl.textContent = '0% vs last month';
        }
    }

    var areaPaid = document.getElementById('invoice-overview-area-paid');
    var linePaid = document.getElementById('invoice-overview-line-paid');
    var lineUnpaid = document.getElementById('invoice-overview-line-unpaid');
    if (areaPaid && data.paid_area) {
        areaPaid.setAttribute('d', data.paid_area);
    }
    if (linePaid && data.paid_line) {
        linePaid.setAttribute('d', data.paid_line);
    }
    if (lineUnpaid && data.unpaid_line) {
        lineUnpaid.setAttribute('d', data.unpaid_line);
    }
}

function buildAnalyticsPostData(startDate, endDate) {
    var currency = window.selectedCurrency || 'USD,$';
    var code = currency;
    var symbol = '$';
    if (String(currency).indexOf(',') !== -1) {
        var parts = String(currency).split(',');
        code = (parts[0] || 'USD').trim();
        symbol = parts.slice(1).join(',').trim() || '$';
    }
    return 'start_date=' + encodeURIComponent(startDate) +
        '&end_date=' + encodeURIComponent(endDate) +
        '&currency_code=' + encodeURIComponent(code) +
        '&currency_symbol=' + encodeURIComponent(symbol);
}

function markAdminDashboardRangeReady() {
    if (window.__adminDashSkeleton) {
        window.__adminDashSkeleton.markRangeReady();
    }
}

function salesStatsTsIcon(name) {
    return '<span class="ts-icon ts-icon-' + name + '" aria-hidden="true"></span>';
}

function updateSalesStatsComparison(data) {
    var arrowEl = document.getElementById('salesStatsBarSummaryArrow');
    var pctEl = document.getElementById('salesStatsBarSummaryPct');
    var sepEl = document.getElementById('salesStatsBarSummarySep');
    var vsEl = document.getElementById('salesStatsBarSummaryVs');
    if (!arrowEl || !pctEl || !vsEl) {
        return;
    }

    var pct = parseInt(data.paid_pct_change, 10);
    if (isNaN(pct)) {
        pct = 0;
    }
    var vsLabel = data.vs_label || 'vs last month';

    arrowEl.innerHTML = '';
    arrowEl.className = 'task-reports-bar-summary-arrow';
    pctEl.textContent = '';
    pctEl.className = 'task-reports-bar-summary-pct';
    if (sepEl) {
        sepEl.style.display = '';
        sepEl.textContent = '\u00B7';
    }
    vsEl.textContent = vsLabel;

    if (pct === 0) {
        arrowEl.classList.add('is-flat');
        arrowEl.innerHTML = salesStatsTsIcon('arrows-up-down');
        pctEl.classList.add('is-flat');
        pctEl.textContent = 'No change';
        return;
    }

    if (pct > 0) {
        arrowEl.classList.add('is-up');
        arrowEl.innerHTML = salesStatsTsIcon('arrow-up-right');
        pctEl.classList.add('is-up');
        pctEl.textContent = Math.abs(pct) + '%';
    } else {
        arrowEl.classList.add('is-down');
        arrowEl.innerHTML = salesStatsTsIcon('arrow-down-right');
        pctEl.classList.add('is-down');
        pctEl.textContent = Math.abs(pct) + '%';
    }
}

function updateRangeTotals(startDate, endDate) {
    console.log('Updating range totals for:', startDate, 'to', endDate);
    
    // Store current date range for currency updates
    window.currentDateRange = { start: startDate, end: endDate };
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', monthlyRangeAjaxUrl(), true);
    xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var raw = (xhr.responseText || '').trim();
                if (!raw) {
                    throw new Error('Empty response');
                }
                var data = JSON.parse(raw);
                console.log('Analytics data received:', data);
                
                var monthRpsElement = document.getElementById('month-rps');
                if (monthRpsElement) {
                    var currencySymbol = data.currency_symbol || '$';
                    var fmtMoney = function(amount) {
                        return currencySymbol + Number(amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    };
                    var paidEl = document.getElementById('salesStatsPaid');
                    var unpaidEl = document.getElementById('salesStatsUnpaid');
                    var cancelledEl = document.getElementById('salesStatsCancelled');
                    var taxEl = document.getElementById('salesStatsTax');
                    var paidLabel = document.querySelector('#month-rps .invoice-financial-summary-label--paid');
                    var unpaidLabel = document.querySelector('#month-rps .invoice-financial-summary-label--unpaid');
                    var cancelledLabel = document.querySelector('#month-rps .invoice-financial-summary-label--cancelled');
                    var taxLabel = document.querySelector('#month-rps .invoice-financial-summary-label--tax');
                    if (paidEl) paidEl.textContent = fmtMoney(data.paid_total);
                    if (unpaidEl) unpaidEl.textContent = fmtMoney(data.unpaid_total);
                    if (cancelledEl) cancelledEl.textContent = fmtMoney(data.cancelled_total);
                    if (taxEl) taxEl.textContent = fmtMoney(data.sales_tax_total);
                    if (paidLabel) paidLabel.style.color = '#22c55e';
                    if (unpaidLabel) unpaidLabel.style.color = '#ef4444';
                    if (cancelledLabel) cancelledLabel.style.color = '#adb5bd';
                    if (taxLabel) taxLabel.style.color = '#fd7e14';
                    updateSalesStatsComparison(data);
                }
                // Update Chart.js with new data
                if (window.earningsLineChart) {
                  earningsLineChart.data.labels = data.labels;
                  earningsLineChart.data.datasets[0].data = data.paid;
                  earningsLineChart.data.datasets[1].data = data.unpaid;
                  if (earningsLineChart.data.datasets[2]) {
                    earningsLineChart.data.datasets[2].data = data.cancelled || [];
                  }
                  earningsLineChart.update();
                }
                // Keep Invoice Overview in lockstep with Sales stats (same totals + range)
                applyInvoiceOverviewData({
                    paid_total: data.paid_total,
                    unpaid_total: data.unpaid_total,
                    paid_count: data.paid_count,
                    unpaid_count: data.unpaid_count,
                    total_count: (data.paid_count || 0) + (data.unpaid_count || 0),
                    currency_symbol: data.currency_symbol
                });
                refreshInvoiceOverview(startDate, endDate);
            } catch (e) {
                console.error('Error parsing response:', e);
                var monthRpsElement = document.getElementById('month-rps');
                if (monthRpsElement) {
                    monthRpsElement.innerHTML = 'Error parsing data.';
                }
            }
        } else {
            var monthRpsElement = document.getElementById('month-rps');
            if (monthRpsElement) {
                monthRpsElement.innerHTML = 'Error loading data.';
            }
        }
        markAdminDashboardRangeReady();
    };
    xhr.onerror = function() {
        var monthRpsElement = document.getElementById('month-rps');
        if (monthRpsElement) {
            monthRpsElement.innerHTML = 'Network error.';
        }
        markAdminDashboardRangeReady();
    };
    xhr.timeout = 15000;
    xhr.ontimeout = function () {
        markAdminDashboardRangeReady();
    };

    xhr.send(buildAnalyticsPostData(startDate, endDate));
}

function selectQuickRange(range) {
  let start, end, label;
  const today = new Date();
  end = today;
  if (range === 'last7') {
    start = new Date();
    start.setDate(today.getDate() - 6);
    label = 'Last 7 days';
  } else if (range === 'last30') {
    start = new Date();
    start.setDate(today.getDate() - 29);
    label = 'Last 30 days';
  } else if (range === 'last90') {
    start = new Date();
    start.setDate(today.getDate() - 89);
    label = 'Last 90 days';
  } else if (range === 'last6months') {
    start = new Date();
    start.setMonth(today.getMonth() - 5);
    start.setDate(1);
    label = 'Last 6 months';
  } else if (range === 'thisYear') {
    start = new Date(today.getFullYear(), 0, 1);
    end = new Date(today.getFullYear(), 11, 31); // End of year instead of today
    label = 'This Year (Jan - Dec)';
  }
  updateRangeTotals(formatDate(start), formatDate(end));
  var customRangeDropdown = document.getElementById('customRangeDropdown');
  if (customRangeDropdown) {
    customRangeDropdown.innerText = label;
  }
}

function selectCustomRange() {
  const startInput = document.getElementById('customStart');
  const endInput = document.getElementById('customEnd');
  if (startInput && endInput) {
    const start = startInput.value;
    const end = endInput.value;
    if (start && end) {
      updateRangeTotals(start, end);
      var customRangeDropdown = document.getElementById('customRangeDropdown');
      if (customRangeDropdown) {
        customRangeDropdown.innerText = `${start} – ${end}`;
      }
    }
  }
}

function formatDate(date) {
  if (typeof date === 'string') return date;
  var y = date.getFullYear();
  var m = String(date.getMonth() + 1).padStart(2, '0');
  var d = String(date.getDate()).padStart(2, '0');
  return y + '-' + m + '-' + d;
}

// Debug function to test specific payment date
function testPaymentDate() {
  console.log('Testing payment date for 2025-02-05');
  updateRangeTotals('2025-02-01', '2025-02-28');
}

// Make functions available globally for debugging
window.testPaymentDate = testPaymentDate;
window.updateRangeTotals = updateRangeTotals;
window.refreshInvoiceOverview = refreshInvoiceOverview;
window.applyInvoiceOverviewData = applyInvoiceOverviewData;

window.addEventListener('DOMContentLoaded', function() {
  // Only initialize range selection if the required elements exist
  var monthRpsElement = document.getElementById('month-rps');
  if (monthRpsElement) {
    // Default to this year
    selectQuickRange('thisYear');
  } else if (window.__adminDashSkeleton) {
    window.__adminDashSkeleton.markRangeReady();
  }
});

// TOOLTIP INIT AND TASK SIDEBAR
document.addEventListener('DOMContentLoaded', function () {
  // Tooltips are now handled by general.js
  document.querySelectorAll('.view-task-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      var taskId = this.getAttribute('data-task-id');
      openTaskSidebar(taskId);
    });
  });
});

// SUMMARY AMOUNT TOGGLE (Paid / Unpaid / Cancel)
document.addEventListener('DOMContentLoaded', function() {
  function toggleDatasetFromSummary(item) {
    var chart = window.earningsLineChart;
    if (!chart || !item) {
      return;
    }
    var index = parseInt(item.getAttribute('data-dataset-index'), 10);
    if (isNaN(index) || !chart.data.datasets[index]) {
      return;
    }
    var meta = chart.getDatasetMeta(index);
    meta.hidden = meta.hidden === null ? !chart.data.datasets[index].hidden : null;
    chart.update();
    var isActive = !meta.hidden;
    if (isActive) {
      item.classList.remove('is-inactive');
    } else {
      item.classList.add('is-inactive');
    }
  }

  var toggles = document.querySelectorAll('#month-rps .invoice-financial-summary-item.is-chart-toggle');
  toggles.forEach(function(item) {
    item.style.cursor = 'pointer';
    item.addEventListener('click', function() {
      toggleDatasetFromSummary(item);
    });
    item.addEventListener('keydown', function(e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        toggleDatasetFromSummary(item);
      }
    });
  });
});
