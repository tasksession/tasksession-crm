// User Analytics JavaScript - For user profile financial data
// Based on analytics.js but for user-specific data

// Check if Chart.js is loaded
if (typeof Chart === 'undefined') {
    console.error('Chart.js is not loaded. Please ensure Chart.js is included before user-analytics.js');
    document.addEventListener('DOMContentLoaded', function() {
        const chartContainer = document.getElementById('earningsLineChart');
        if (chartContainer) {
            chartContainer.style.display = 'flex';
            chartContainer.style.alignItems = 'center';
            chartContainer.style.justifyContent = 'center';
            chartContainer.style.height = '200px';
            chartContainer.style.backgroundColor = '#f8f9fa';
            chartContainer.style.border = '1px solid #dee2e6';
            chartContainer.style.borderRadius = '4px';
            chartContainer.innerHTML = '<div style="text-align: center; color: #6c757d;"><strong>Chart.js Error</strong><br>Chart library not loaded properly</div>';
        }
    });
    // Exit early if Chart.js is not available
    throw new Error('Chart.js is required but not loaded');
}

// Silence verbose logging for production in this file
(function(){
  try {
    if (typeof console !== 'undefined') {
      var noop = function(){};
      if (console.log) console.log = noop;
      if (console.warn) console.warn = noop;
      if (console.error) console.error = noop;
    }
  } catch (e) {}
})();

// LINE CHART (User-specific Earnings/Unpaid) - Same as analytics.js
var userEarningsLineChart;
document.addEventListener('DOMContentLoaded', function() {
  var rootStyles = getComputedStyle(document.documentElement);
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
  
  
  // Check if the chart container exists
  var chartContainer = document.querySelector('.stats-graph');
  
  if (chartContainer) {
    
  }
  
  if (earningsCanvas) {
    
    var ctx = earningsCanvas.getContext('2d');
    
    try {
      userEarningsLineChart = new Chart(ctx, {
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
      labels: window.monthLabels || ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
      datasets: [
        {
          label: window.salesPaidLabel || 'Paid',
          data: window.monthlyEarnings || [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
          borderColor: primaryColor,
          backgroundColor: createSparkAreaGradient(ctx, primaryColor, earningsCanvas.height),
          pointBackgroundColor: cardBodyColor,
          pointBorderColor: primaryColor,
          pointRadius: 4,
          pointHoverRadius: 6,
          pointHitRadius: 10,
          pointBorderWidth: 1.5,
          fill: true,
          lineTension: earningsLineSmooth,
          borderWidth: 1.5,
          borderCapStyle: 'round',
          borderJoinStyle: 'round'
        },
        {
          label: window.salesUnpaidLabel || 'Unpaid',
          data: window.monthlyUnpaid || [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
          borderColor: secondaryColor,
          backgroundColor: createSparkAreaGradient(ctx, secondaryColor, earningsCanvas.height),
          pointBackgroundColor: cardBodyColor,
          pointBorderColor: secondaryColor,
          pointRadius: 4,
          pointHoverRadius: 6,
          pointHitRadius: 10,
          pointBorderWidth: 1.5,
          fill: true,
          lineTension: earningsLineSmooth,
          borderWidth: 1.5,
          borderCapStyle: 'round',
          borderJoinStyle: 'round'
        },
        {
          label: window.salesCancelledLabel || 'Cancel',
          data: window.monthlyCancelled || [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
          borderColor: cancelledColor,
          backgroundColor: createSparkAreaGradient(ctx, cancelledColor, earningsCanvas.height),
          pointBackgroundColor: cardBodyColor,
          pointBorderColor: cancelledColor,
          pointRadius: 4,
          pointHoverRadius: 6,
          pointHitRadius: 10,
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
  window.earningsLineChart = userEarningsLineChart;
           } catch (error) {
             console.error('Error creating user earnings chart:', error);
           }
         } else {
           console.error('earningsLineChart canvas element not found!');
         }

  // Load this year's range immediately (same as admin dashboard)
  if (document.getElementById('month-rps')) {
    applyDefaultSalesCurrency();
    selectQuickRange('thisYear');
  }
});

function userDataAjaxUrl() {
    var root = (typeof window.siteRootUrl === 'string' && window.siteRootUrl.length)
        ? window.siteRootUrl.replace(/\/$/, '')
        : '';
    return root ? (root + '/ajax/user-data.php') : '../ajax/user-data.php';
}

function invoiceOverviewAjaxUrl() {
    var root = (typeof window.siteRootUrl === 'string' && window.siteRootUrl.length)
        ? window.siteRootUrl.replace(/\/$/, '')
        : '';
    return root ? (root + '/ajax/invoice_overview.php') : '../ajax/invoice_overview.php';
}

function applyInvoiceOverviewData(data) {
    if (!data || !document.getElementById('invoice-overview-card')) {
        return;
    }
    var badge = document.getElementById('invoice-overview-badge');
    var paidCount = document.getElementById('invoice-overview-paid-count');
    var unpaidCount = document.getElementById('invoice-overview-unpaid-count');
    var paidMeta = document.getElementById('invoice-overview-paid-meta');
    var unpaidMeta = document.getElementById('invoice-overview-unpaid-meta');
    var pctEl = document.getElementById('invoice-overview-pct');
    var paidLabel = window.invoicePaidLabel || 'Paid Invoices';
    var unpaidLabel = window.invoiceUnpaidLabel || 'Unpaid';
    var symbol = data.currency_symbol || window.currentCurrencySymbol || '$';

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
    if (unpaidCount && typeof data.unpaid_count !== 'undefined') {
        unpaidCount.textContent = String(data.unpaid_count || 0);
    }

    var paidFmt = data.paid_amount_fmt;
    var unpaidFmt = data.unpaid_amount_fmt;
    if (!paidFmt && typeof data.paid_total !== 'undefined') {
        paidFmt = symbol + Number(data.paid_total || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    if (!unpaidFmt && typeof data.unpaid_total !== 'undefined') {
        unpaidFmt = symbol + Number(data.unpaid_total || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    if (paidMeta && paidFmt) {
        paidMeta.textContent = paidLabel + ' \u00B7 ' + paidFmt;
    }
    if (unpaidMeta && unpaidFmt) {
        unpaidMeta.textContent = unpaidLabel + ' \u00B7 ' + unpaidFmt;
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

function refreshInvoiceOverview(startDate, endDate) {
    var card = document.getElementById('invoice-overview-card');
    if (!card) {
        return;
    }
    var start = startDate || (window.currentDateRange && window.currentDateRange.start) || '';
    var end = endDate || (window.currentDateRange && window.currentDateRange.end) || '';
    var userId = getUserIdFromUrl() || '';
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
            // Sparks + % only — amounts/counts stay locked to Sales stats (user-data).
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
        'currency=' + encodeURIComponent(window.selectedCurrency || 'USD,$') +
        '&start_date=' + encodeURIComponent(start) +
        '&end_date=' + encodeURIComponent(end) +
        '&user_id=' + encodeURIComponent(userId)
    );
}

// RANGE FILTERS AND AJAX - User-specific version
function updateUserRangeTotals(startDate, endDate) {
    
    
    // Store current date range for currency updates
    window.currentDateRange = { start: startDate, end: endDate };
    
    // Make AJAX call to get filtered data
    const userId = getUserIdFromUrl();
    const currency = window.selectedCurrency || 'all';
    
    if (userId) {
        fetch(`${userDataAjaxUrl()}?user_id=${userId}&start_date=${startDate}&end_date=${endDate}&currency=${encodeURIComponent(currency)}`)
            .then(response => response.json())
            .then(data => {
                
                if (data.success) {
                    // Update chart data
                    if (userEarningsLineChart) {
                        userEarningsLineChart.data.labels = data.monthLabels || data.labels || [];
                        userEarningsLineChart.data.datasets[0].data = data.monthlyEarnings || data.paid || [];
                        userEarningsLineChart.data.datasets[1].data = data.monthlyUnpaid || data.unpaid || [];
                        if (userEarningsLineChart.data.datasets[2]) {
                          userEarningsLineChart.data.datasets[2].data = data.monthlyCancelled || data.cancelled || [];
                        }
                        userEarningsLineChart.update();
                    }
                    
                    // Update totals
                    if (data.totalPaid !== undefined) {
                        window.userTotalPaid = data.totalPaid;
                    } else if (data.paid_total !== undefined) {
                        window.userTotalPaid = data.paid_total;
                    }
                    if (data.totalUnpaid !== undefined) {
                        window.userTotalUnpaid = data.totalUnpaid;
                    } else if (data.unpaid_total !== undefined) {
                        window.userTotalUnpaid = data.unpaid_total;
                    }
                    window.userTotalCancelled = data.cancelled_total || 0;
                    window.userTotalTax = data.sales_tax_total || 0;
                    window.userPaidPctChange = data.paid_pct_change;
                    window.userVsLabel = data.vs_label;
                    
                    // Update currency symbol if provided
                    if (data.currency_symbol) {
                        window.currentCurrencySymbol = data.currency_symbol;
                    }
                    updateSalesStatsSummary(data);

                    // Keep Invoice Overview in lockstep with Sales stats (same totals + range)
                    applyInvoiceOverviewData({
                        paid_total: data.paid_total != null ? data.paid_total : data.totalPaid,
                        unpaid_total: data.unpaid_total != null ? data.unpaid_total : data.totalUnpaid,
                        paid_count: data.paid_count,
                        unpaid_count: data.unpaid_count,
                        total_count: data.total_count,
                        currency_symbol: data.currency_symbol
                    });
                    refreshInvoiceOverview(startDate, endDate);
                } else {
                    console.error('API Error:', data.error);
                }
            })
            .catch(error => {
                console.error('Error fetching filtered data:', error);
                // Fallback to updating chart with existing data
                if (userEarningsLineChart) {
                    userEarningsLineChart.update();
                }
            });
    } else {
        // Fallback if no user ID
        if (userEarningsLineChart) {
            userEarningsLineChart.update();
        }
    }
}

// Helper function to get user ID from URL
function getUserIdFromUrl() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('user_id');
}

// Function to convert HTML entities to actual symbols
function convertHtmlEntities(text) {
    const entityMap = {
        '&euro;': '€',
        '&pound;': '£',
        '&yen;': '¥',
        '&dollar;': '$',
        '&cent;': '¢',
        '&rsquo;': "'",
        '&lsquo;': "'",
        '&rdquo;': '"',
        '&ldquo;': '"',
        '&amp;': '&',
        '&lt;': '<',
        '&gt;': '>',
        '&quot;': '"',
        '&apos;': "'"
    };
    
    let result = text;
    for (const [entity, symbol] of Object.entries(entityMap)) {
        result = result.replace(new RegExp(entity, 'g'), symbol);
    }
    return result;
}

// Function to update currency symbols
function updateCurrencySymbols(currencySymbol) {
    
    // Convert HTML entities to actual symbols
    const cleanSymbol = convertHtmlEntities(currencySymbol);
    window.currentCurrencySymbol = cleanSymbol;
    updateUserTotals();
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
    var vsLabel = data.vs_label || 'vs last period';

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

function updateSalesStatsSummary(data) {
    var currencySymbol = data.currency_symbol || window.currentCurrencySymbol || getCurrencySymbol(window.selectedCurrency || 'all');
    currencySymbol = convertHtmlEntities(currencySymbol);
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
    if (paidEl) paidEl.textContent = fmtMoney(data.paid_total != null ? data.paid_total : data.totalPaid);
    if (unpaidEl) unpaidEl.textContent = fmtMoney(data.unpaid_total != null ? data.unpaid_total : data.totalUnpaid);
    if (cancelledEl) cancelledEl.textContent = fmtMoney(data.cancelled_total);
    if (taxEl) taxEl.textContent = fmtMoney(data.sales_tax_total);
    if (paidLabel) paidLabel.style.color = '#22c55e';
    if (unpaidLabel) unpaidLabel.style.color = '#ef4444';
    if (cancelledLabel) cancelledLabel.style.color = '#adb5bd';
    if (taxLabel) taxLabel.style.color = '#fd7e14';
    updateSalesStatsComparison(data);
}

// Test function to manually update currency symbols
function testCurrencyUpdate() {
    updateSalesStatsSummary({
        paid_total: 0,
        unpaid_total: 100000,
        cancelled_total: 0,
        sales_tax_total: 0,
        currency_symbol: 'Rs',
        paid_pct_change: 0,
        vs_label: 'vs last period'
    });
}

// Make test function available globally
window.testCurrencyUpdate = testCurrencyUpdate;
window.updateCurrencySymbols = updateCurrencySymbols;

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
  } else if (range === 'february2025') {
    // Specific range for February 2025 to show the payment on 2025-02-05
    start = new Date('2025-02-01');
    end = new Date('2025-02-28');
    label = 'February 2025';
  }
  updateUserRangeTotals(formatDate(start), formatDate(end));
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
      updateUserRangeTotals(start, end);
      var customRangeDropdown = document.getElementById('customRangeDropdown');
      if (customRangeDropdown) {
        customRangeDropdown.innerText = `${start} – ${end}`;
      }
    }
  }
}

function formatDate(date) {
  if (typeof date === 'string') return date;
  return date.toISOString().slice(0, 10);
}

function resolveFirstSalesCurrency() {
    var menu = document.querySelector('[aria-labelledby="currencyFilterDropdown"]');
    if (!menu) {
        return null;
    }
    var firstItem = menu.querySelector('.dropdown-item[onclick*="selectCurrency"]');
    if (!firstItem) {
        return null;
    }
    var onclick = firstItem.getAttribute('onclick') || '';
    var match = onclick.match(/selectCurrency\('((?:\\'|[^'])*)'\)/);
    if (!match || !match[1]) {
        return null;
    }
    return {
        value: match[1].replace(/\\'/g, "'"),
        label: (firstItem.textContent || '').trim()
    };
}

function applyDefaultSalesCurrency() {
    if (window.defaultCurrency) {
        window.selectedCurrency = window.defaultCurrency;
        var currencyDropdown = document.getElementById('currencyFilterDropdown');
        if (currencyDropdown) {
            currencyDropdown.innerText = getCurrencyDisplayName(window.defaultCurrency);
        }
        return;
    }
    var firstCurrency = resolveFirstSalesCurrency();
    if (firstCurrency && firstCurrency.value) {
        window.defaultCurrency = firstCurrency.value;
        window.selectedCurrency = firstCurrency.value;
        var currencyDropdown = document.getElementById('currencyFilterDropdown');
        if (currencyDropdown && firstCurrency.label) {
            currencyDropdown.innerText = firstCurrency.label;
        }
        return;
    }
    if (!window.selectedCurrency && window.defaultCurrency) {
        window.selectedCurrency = window.defaultCurrency;
    }
}

// Currency selection function
function selectCurrency(currency) {
    window.selectedCurrency = currency;
    
    // Update dropdown button text
    var currencyDropdown = document.getElementById('currencyFilterDropdown');
    if (currencyDropdown) {
        // Get the display text for the selected currency
        var buttonText = getCurrencyDisplayName(currency);
        currencyDropdown.innerText = buttonText;
    }
    
    // Trigger data update with current date range and new currency
    if (window.currentDateRange && window.currentDateRange.start && window.currentDateRange.end) {
        updateUserRangeTotals(window.currentDateRange.start, window.currentDateRange.end);
    } else {
        // If no date range set, use current year
        const today = new Date();
        const startOfYear = new Date(today.getFullYear(), 0, 1);
        const endOfYear = new Date(today.getFullYear(), 11, 31);
        updateUserRangeTotals(formatDate(startOfYear), formatDate(endOfYear));
    }
}

// Helper function to get currency display name
function getCurrencyDisplayName(currencyString) {
    if (!currencyString || currencyString === 'all') {
        return 'All Currencies';
    }
    
    // Extract currency code and symbol
    const parts = currencyString.split(',');
    const currencyCode = parts[0].trim();
    const currencySymbol = parts.length > 1 ? parts[1].trim() : currencyCode;
    
    // Map currency codes to country names
    const countryMap = {
        'USD': 'United States',
        'PKR': 'Pakistan',
        'Rs': 'Pakistan',
        'CAD': 'Canada',
        'EUR': 'European Union',
        'GBP': 'United Kingdom',
        'INR': 'India',
        'JPY': 'Japan',
        'AUD': 'Australia',
        'CHF': 'Switzerland',
        'CNY': 'China'
    };
    
    const countryName = countryMap[currencyCode] || currencyCode;
    return `${countryName} (${currencySymbol})`;
}

// Calculate and display user totals
function updateUserTotals() {
    updateSalesStatsSummary({
        paid_total: window.userTotalPaid || 0,
        unpaid_total: window.userTotalUnpaid || 0,
        cancelled_total: window.userTotalCancelled || 0,
        sales_tax_total: window.userTotalTax || 0,
        currency_symbol: window.currentCurrencySymbol || getCurrencySymbol(window.selectedCurrency || 'all'),
        paid_pct_change: window.userPaidPctChange,
        vs_label: window.userVsLabel
    });
}

// Helper function to get currency symbol
function getCurrencySymbol(currencyString) {
    
    if (!currencyString || currencyString === 'all') {
        
        return '$'; // Default to dollar sign
    }
    
    // If it's already just a symbol, return it
    if (currencyString.length <= 3) {
        
        return currencyString;
    }
    
    // Extract symbol from "CODE,SYMBOL" format
    const parts = currencyString.split(',');
    
    
    if (parts.length > 1) {
        const symbol = parts[1].trim();
        // Remove any colon or space from the symbol
        const cleanedSymbol = symbol.replace(/[: ]/g, '');
        return cleanedSymbol;
    }
    
    
    return '$'; // Default fallback
}

// SUMMARY AMOUNT TOGGLE (Paid / Unpaid / Cancel)
document.addEventListener('DOMContentLoaded', function() {
  function toggleDatasetFromSummary(item) {
    var chart = userEarningsLineChart || window.earningsLineChart;
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
