(function () {
    'use strict';

    var cfg = window.ecommerceReportsConfig || window.invoiceReportsConfig || window.projectReportsConfig || window.taskReportsConfig || {};
    var isEcommerce = cfg.type === 'ecommerce' || (cfg.ajaxUrl && cfg.ajaxUrl.indexOf('ecommerce-reports') !== -1);
    var isInvoice = !isEcommerce && (cfg.type === 'invoice' || (cfg.ajaxUrl && cfg.ajaxUrl.indexOf('invoice-reports') !== -1));
    var isProject = !isEcommerce && !isInvoice && (cfg.type === 'projects' || (cfg.ajaxUrl && cfg.ajaxUrl.indexOf('project-reports') !== -1));
    var isMarketing = !isEcommerce && !isInvoice && !isProject && cfg.ajaxUrl && cfg.ajaxUrl.indexOf('marketing/') !== -1;
    if (!cfg.type) {
        cfg.type = isEcommerce ? 'ecommerce' : (isInvoice ? 'invoice' : (isProject ? 'projects' : 'task'));
    }
    var lang = cfg.lang || {};
    var ajaxUrl = cfg.ajaxUrl || '';
    var donutChart = null;
    var salesLineChart = null;
    var invoiceSalesLineChart = null;
    var barChart = null;
    var barRaw = { entries: [], staff: [] };
    var barComparison = null;
    var lastFilters = {};
    var chartBarState = { userId: '', grouping: 'weekly' };
    var invoiceSalesBarChart = null;
    var invoiceSalesLineData = null;
    var invoiceSalesComparison = null;
    var invoiceChartState = { view: 'line', grouping: 'weekly' };
    var invoiceLineSmooth = 0.4;
    var ecommerceSalesBarChart = null;
    var ecommerceSalesLineChart = null;
    var ecommerceSalesLineData = null;
    var ecommerceSalesComparison = null;
    var ecommerceChartState = { view: 'line', grouping: 'weekly' };
    var ecommerceLineSmooth = 0.4;
    var ecommerceCurrencyCode = '';
    var ecommerceCurrencySymbol = '$';
    var barRoundedDrawInstalled = false;
    var BAR_CHART_INLINE_HEIGHT = 330;

    function reportsTsIcon(name, extraClass) {
        if (typeof window.tsIcon === 'function') {
            return window.tsIcon(name, extraClass);
        }
        return '';
    }

    function reportsMenuIcon(name) {
        return reportsTsIcon(name, 'tasksession-timer-log-menu-ico me-2');
    }

    function installComonRoundedDonutPlugin() {
        if (typeof Chart === 'undefined' || Chart._comonDashboardRoundedDonut) {
            return;
        }
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
    window.installComonRoundedDonutPlugin = installComonRoundedDonutPlugin;

    function applyBarChartInlineHeight(canvas) {
        if (!canvas) {
            return;
        }
        var h = BAR_CHART_INLINE_HEIGHT;
        var wrap = canvas.parentElement;
        if (wrap && wrap.clientHeight > 0) {
            h = wrap.clientHeight;
        }
        canvas.style.display = 'block';
        canvas.style.width = '100%';
        canvas.style.height = h + 'px';
        canvas.style.maxHeight = h + 'px';
    }

    function patchBarChartResize(chart) {
        if (!chart || chart._barInlineHeightPatched) {
            return;
        }
        chart._barInlineHeightPatched = true;
        var originalResize = chart.resize;
        chart.resize = function () {
            originalResize.apply(this, arguments);
            applyBarChartInlineHeight(this.canvas);
        };
    }

    function $(id) {
        return document.getElementById(id);
    }

    function getForm() {
        if (isEcommerce) {
            if (cfg.dashboardChartsOnly && $('ecomDashFilterForm')) {
                return $('ecomDashFilterForm');
            }
            return $('ecommerceReportsFilterForm');
        }
        if (isInvoice) {
            return $('invoiceReportsFilterForm');
        }
        return $(isProject ? 'projectReportsFilterForm' : 'taskReportsFilterForm');
    }

    function getFilterParams() {
        var form = getForm();
        if (!form) {
            return '';
        }
        var fd = new FormData(form);
        if (isInvoice) {
            var currencyInput = $('invoiceReportsCurrency');
            if (currencyInput && currencyInput.value) {
                fd.set('currency', currencyInput.value);
            }
        }
        var parts = [];
        if (isEcommerce) {
            var chartGrouping = $('ecommerceReportsChartGrouping');
            if (!chartGrouping && cfg.dashboardChartsOnly) {
                chartGrouping = $('ecomDashChartGrouping');
            }
            if (chartGrouping && chartGrouping.value) {
                fd.set('chart_grouping', chartGrouping.value);
            }
        } else if (isInvoice) {
            var invoiceChartGrouping = $('invoiceReportsChartGrouping');
            if (invoiceChartGrouping && invoiceChartGrouping.value) {
                fd.set('chart_grouping', invoiceChartGrouping.value);
            }
        }
        fd.forEach(function (value, key) {
            parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(value));
        });
        return parts.join('&');
    }

    function syncInvoiceCurrencyQueryParam(value) {
        if (!isInvoice) {
            return;
        }
        var input = $('invoiceReportsCurrency');
        if (!input) {
            return;
        }
        var def = cfg.defaultCurrency || '';
        if (value && value !== def) {
            input.setAttribute('name', 'currency');
        } else {
            input.removeAttribute('name');
        }
    }

    function getFilterInputId(field) {
        if (!isInvoice) {
            return '';
        }
        var map = {
            date_preset: 'invoiceReportsDatePreset',
            client_id: 'invoiceReportsClientId',
            project_id: 'invoiceReportsProjectId',
            currency: 'invoiceReportsCurrency',
            status: 'invoiceReportsStatus'
        };
        return map[field] || '';
    }

    function getFilterInput(form, field) {
        if (!form) {
            return null;
        }
        var named = form.querySelector('[name="' + field + '"]');
        if (named) {
            return named;
        }
        var id = getFilterInputId(field);
        return id ? $(id) : null;
    }

    function showAlert(message, type) {
        var el = $(isEcommerce ? 'ecommerceReportsAlert' : (isInvoice ? 'invoiceReportsAlert' : (isProject ? 'projectReportsAlert' : 'taskReportsAlert')));
        if (!el) {
            return;
        }
        el.className = 'alert alert-' + (type || 'danger') + ' mb-3';
        el.textContent = message;
        el.classList.remove('d-none');
    }

    function hideAlert() {
        var el = $(isEcommerce ? 'ecommerceReportsAlert' : (isInvoice ? 'invoiceReportsAlert' : (isProject ? 'projectReportsAlert' : 'taskReportsAlert')));
        if (el) {
            el.classList.add('d-none');
        }
    }

    function setLoading(loading) {
        var wrap = document.querySelector('.reports-page');
        var skel = document.getElementById('reportsDashboardSkeleton');
        var hasSkeleton = !!skel;
        if (wrap) {
            wrap.classList.toggle('reports-loading', !!loading && hasSkeleton);
        }
        if (skel) {
            skel.setAttribute('aria-hidden', loading ? 'false' : 'true');
            skel.setAttribute('aria-busy', loading ? 'true' : 'false');
        }
    }

    // Keep skeleton visible from first paint until loadData() finishes (page may already have reports-loading from PHP).
    if (document.getElementById('reportsDashboardSkeleton')) {
        setLoading(true);
    }

    function sumBarSeconds(entries, userId) {
        var total = 0;
        (entries || []).forEach(function (entry) {
            if (userId && String(entry.user_id) !== String(userId)) {
                return;
            }
            total += entry.seconds || 0;
        });
        return total;
    }

    function calcPercentChange(current, previous) {
        current = current || 0;
        previous = previous || 0;
        if (previous === 0) {
            return current > 0 ? 100 : 0;
        }
        var pct = ((current - previous) / previous) * 100;
        if (pct > 999) {
            return 999;
        }
        if (pct < -999) {
            return -999;
        }
        return Math.round(pct);
    }

    function formatDurationSummaryHtml(seconds) {
        if (!seconds || seconds <= 0) {
            return '<span class="task-reports-bar-summary-num">0</span><span class="task-reports-bar-summary-unit">h</span> <span class="task-reports-bar-summary-num">00</span><span class="task-reports-bar-summary-unit">min</span>';
        }
        var h = Math.floor(seconds / 3600);
        var m = Math.floor((seconds % 3600) / 60);
        return '<span class="task-reports-bar-summary-num">' + h + '</span><span class="task-reports-bar-summary-unit">h</span> ' +
            '<span class="task-reports-bar-summary-num">' + String(m).padStart(2, '0') + '</span><span class="task-reports-bar-summary-unit">min</span>';
    }

    function formatCountSummaryHtml(count) {
        var num = parseInt(count, 10);
        if (isNaN(num) || num < 0) {
            num = 0;
        }
        var unit = (lang.totalTrackedTime || lang.emailsSent || 'Emails Sent');
        return '<span class="task-reports-bar-summary-num">' + num.toLocaleString() + '</span> ' +
            '<span class="task-reports-bar-summary-unit">' + escapeHtml(unit) + '</span>';
    }

    function formatTooltipCount(count) {
        var num = parseInt(count, 10);
        if (isNaN(num) || num < 0) {
            num = 0;
        }
        return num.toLocaleString();
    }

    function formatSharePct(seconds, totalSeconds) {
        if (!totalSeconds || totalSeconds <= 0) {
            return '0%';
        }
        var pct = (seconds / totalSeconds) * 100;
        return (Math.round(pct * 100) / 100).toFixed(2) + '%';
    }


    function formatCompletionRateValue(rate) {
        var num = parseFloat(rate);
        if (isNaN(num) || num <= 0) {
            return '0%';
        }
        if (num % 1 === 0) {
            return String(Math.round(num)) + '%';
        }
        return num.toFixed(2) + '%';
    }

    function renderSummaryComparison(arrowEl, pctEl, sepEl, vsEl, comparison, metricKey) {
        if (!arrowEl || !pctEl || !vsEl) {
            return;
        }

        arrowEl.innerHTML = '';
        arrowEl.className = 'task-reports-bar-summary-arrow';
        pctEl.textContent = '';
        pctEl.className = 'task-reports-bar-summary-pct';
        if (sepEl) {
            sepEl.textContent = '·';
            sepEl.style.display = '';
        }

        if (!comparison || !comparison.metrics || !comparison.metrics[metricKey]) {
            vsEl.textContent = '';
            if (sepEl) {
                sepEl.style.display = 'none';
            }
            return;
        }

        var metric = comparison.metrics[metricKey];
        var changePct = metric.percent != null ? metric.percent : 0;
        var direction = metric.direction || 'flat';
        var vsLabel = getComparisonVsLabel(comparison);

        if (direction === 'flat' || changePct === 0) {
            arrowEl.classList.add('is-flat');
            arrowEl.innerHTML = reportsTsIcon('arrows-up-down');
            pctEl.textContent = lang.noChange || 'No change';
            pctEl.classList.add('is-flat');
            vsEl.textContent = vsLabel;
            return;
        }

        var pctAbs = Math.abs(changePct);
        if (direction === 'up') {
            arrowEl.classList.add('is-up');
            arrowEl.innerHTML = reportsTsIcon('arrow-up-right');
            pctEl.classList.add('is-up');
            pctEl.textContent = pctAbs + '%';
        } else {
            arrowEl.classList.add('is-down');
            arrowEl.innerHTML = reportsTsIcon('arrow-down-right');
            pctEl.classList.add('is-down');
            pctEl.textContent = pctAbs + '%';
        }
        vsEl.textContent = vsLabel;
    }

    function updateBarSummary() {
        var mainEl = $(isProject ? 'projectReportsBarSummaryMain' : 'taskReportsBarSummaryMain');
        var arrowEl = $(isProject ? 'projectReportsBarSummaryArrow' : 'taskReportsBarSummaryArrow');
        var pctEl = $(isProject ? 'projectReportsBarSummaryPct' : 'taskReportsBarSummaryPct');
        var sepEl = $(isProject ? 'projectReportsBarSummarySep' : 'taskReportsBarSummarySep');
        var vsEl = $(isProject ? 'projectReportsBarSummaryVs' : 'taskReportsBarSummaryVs');
        if (!mainEl) {
            return;
        }

        var chartUserId = chartBarState.userId || '';
        var currentEntries = barRaw.entries || [];
        var prevEntries = (barComparison && barComparison.entries) ? barComparison.entries : [];
        var currentAll = sumBarSeconds(currentEntries, '');
        var currentScoped = sumBarSeconds(currentEntries, chartUserId);
        var prevScoped = sumBarSeconds(prevEntries, chartUserId);

        if (chartUserId && currentAll > 0) {
            mainEl.innerHTML = '<span class="task-reports-bar-summary-pct-main">' + formatSharePct(currentScoped, currentAll) + '</span>';
        } else if (isMarketing) {
            mainEl.innerHTML = formatCountSummaryHtml(currentScoped);
        } else {
            mainEl.innerHTML = formatDurationSummaryHtml(currentScoped);
        }

        if (!barComparison) {
            if (vsEl) {
                vsEl.textContent = '';
            }
            if (sepEl) {
                sepEl.style.display = 'none';
            }
            if (arrowEl) {
                arrowEl.innerHTML = '';
                arrowEl.className = 'task-reports-bar-summary-arrow';
            }
            if (pctEl) {
                pctEl.textContent = '';
                pctEl.className = 'task-reports-bar-summary-pct';
            }
            return;
        }

        var changePct = calcPercentChange(currentScoped, prevScoped);
        var direction = 'flat';
        if (changePct > 0) {
            direction = 'up';
        } else if (changePct < 0) {
            direction = 'down';
        }
        renderSummaryComparison(arrowEl, pctEl, sepEl, vsEl, {
            label_key: barComparison.label_key,
            metrics: {
                total_time_sec: { percent: changePct, direction: direction }
            }
        }, 'total_time_sec');
    }

    function updateDonutSummary(donut, cards) {
        var mainEl = $(isEcommerce ? 'ecommerceReportsDonutSummaryMain' : (isInvoice ? 'invoiceReportsDonutSummaryMain' : (isProject ? 'projectReportsDonutSummaryMain' : 'taskReportsDonutSummaryMain')));
        var arrowEl = $(isEcommerce ? 'ecommerceReportsDonutSummaryArrow' : (isInvoice ? 'invoiceReportsDonutSummaryArrow' : (isProject ? 'projectReportsDonutSummaryArrow' : 'taskReportsDonutSummaryArrow')));
        var pctEl = $(isEcommerce ? 'ecommerceReportsDonutSummaryPct' : (isInvoice ? 'invoiceReportsDonutSummaryPct' : (isProject ? 'projectReportsDonutSummaryPct' : 'taskReportsDonutSummaryPct')));
        var sepEl = $(isEcommerce ? 'ecommerceReportsDonutSummarySep' : (isInvoice ? 'invoiceReportsDonutSummarySep' : (isProject ? 'projectReportsDonutSummarySep' : 'taskReportsDonutSummarySep')));
        var vsEl = $(isEcommerce ? 'ecommerceReportsDonutSummaryVs' : (isInvoice ? 'invoiceReportsDonutSummaryVs' : (isProject ? 'projectReportsDonutSummaryVs' : 'taskReportsDonutSummaryVs')));
        if (!mainEl) {
            return;
        }

        var total = donut && donut.total != null ? parseInt(donut.total, 10) : 0;
        var rate;
        if (isEcommerce) {
            rate = donut && donut.completion_pct != null ? parseFloat(donut.completion_pct) : 0;
        } else {
            var completed = isInvoice
                ? (donut && donut.paid != null ? parseInt(donut.paid, 10) : 0)
                : (donut && donut.completed != null ? parseInt(donut.completed, 10) : 0);
            rate = isMarketing && cards && cards.open_rate != null
                ? parseFloat(cards.open_rate)
                : (cards && cards.collection_rate != null
                    ? parseFloat(cards.collection_rate)
                    : (cards && cards.completion_rate != null
                        ? parseFloat(cards.completion_rate)
                        : (total > 0 ? Math.round((completed / total) * 10000) / 100 : 0)));
        }

        mainEl.innerHTML =
            '<span class="task-reports-bar-summary-pct-main">' + formatCompletionRateValue(rate) + '</span> ' +
            '<span class="task-reports-donut-summary-label">' + escapeHtml(isEcommerce ? (lang.completionRate || 'Completion Rate') : (lang.collectionRate || lang.completionRate || 'Collection Rate')) + '</span>';

        var metricKey = isEcommerce ? 'total_orders' : 'completion_rate';
        var comparison = cards && cards.comparison ? cards.comparison : null;
        if (isInvoice && comparison && comparison.metrics) {
            if (comparison.metrics.completion_rate) {
                metricKey = 'completion_rate';
            } else if (comparison.metrics.paid_invoices) {
                metricKey = 'paid_invoices';
            }
        }
        renderSummaryComparison(arrowEl, pctEl, sepEl, vsEl, comparison, metricKey);
    }

    function formatTooltipHours(hours) {
        if (!hours || hours <= 0) {
            return '0m';
        }
        var totalMin = Math.round(hours * 60);
        var h = Math.floor(totalMin / 60);
        var m = totalMin % 60;
        if (h > 0) {
            return h + 'h ' + m + 'm';
        }
        return m + 'm';
    }

    function parseYmd(ymd) {
        var p = String(ymd).split('-');
        return new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
    }

    function formatYmd(d) {
        var y = d.getFullYear();
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + day;
    }

    function daysInRange(from, to) {
        var a = parseYmd(from);
        var b = parseYmd(to);
        return Math.round((b - a) / 86400000) + 1;
    }

    function formatRangeLabel(from, to) {
        if (!from || !to) {
            return '';
        }
        var opts = { month: 'short', day: 'numeric', year: 'numeric' };
        try {
            if (from === to) {
                return parseYmd(from).toLocaleDateString(undefined, opts);
            }
            var a = parseYmd(from).toLocaleDateString(undefined, opts);
            var b = parseYmd(to).toLocaleDateString(undefined, opts);
            return a + ' – ' + b;
        } catch (e) {
            return from === to ? from : from + ' – ' + to;
        }
    }


    function getDatePresetLabel(preset) {
        var presets = cfg.datePresets || {};
        if (preset && presets[preset]) {
            return presets[preset];
        }
        return '';
    }

    function updateActiveRangeLabel(filters) {
        var wrap = $(isEcommerce ? 'ecommerceReportsActiveRangeWrap' : (isInvoice ? 'invoiceReportsActiveRangeWrap' : (isProject ? 'projectReportsActiveRangeWrap' : 'taskReportsActiveRangeWrap')));
        var el = $(isEcommerce ? 'ecommerceReportsActiveRange' : (isInvoice ? 'invoiceReportsActiveRange' : (isProject ? 'projectReportsActiveRange' : 'taskReportsActiveRange')));
        if (!el || !filters) {
            if (wrap) {
                wrap.classList.add('d-none');
            }
            return;
        }
        var rangeText = formatRangeLabel(filters.from_date, filters.to_date);
        if (!rangeText) {
            el.innerHTML = '';
            if (wrap) {
                wrap.classList.add('d-none');
            }
            return;
        }
        var presetLabel = getDatePresetLabel(filters.date_preset || '');
        var topLabel = presetLabel || (lang.selectedRange || 'Selected Range');
        var html = '<span class="task-reports-active-range-inner">' +
            '<span class="task-reports-active-range-icon-wrap">' + reportsTsIcon('calendar', 'task-reports-active-range-icon') + '</span>' +
            '<span class="task-reports-active-range-text">' +
            '<span class="task-reports-active-range-preset">' + escapeHtml(topLabel) + '</span>' +
            '<span class="task-reports-active-range-dates">' + escapeHtml(rangeText) + '</span>' +
            '</span></span>';
        el.innerHTML = html;
        if (wrap) {
            wrap.classList.remove('d-none');
        }
    }

    function getDefaultGrouping(filters) {
        var presetEl = $(isProject ? 'projectReportsDatePreset' : 'taskReportsDatePreset');
        var preset = presetEl ? presetEl.value : (filters && filters.date_preset) || 'this_month';
        if (preset === 'this_month' || preset === 'last_month') {
            return 'weekly';
        }
        if (preset === 'today' || preset === 'yesterday' || preset === 'this_week' || preset === 'last_week') {
            return 'daily';
        }
        if (filters && filters.from_date && filters.to_date) {
            var days = daysInRange(filters.from_date, filters.to_date);
            if (days <= 7) {
                return 'daily';
            }
            if (days <= 45) {
                return 'weekly';
            }
            return 'monthly';
        }
        return 'weekly';
    }

    function getGroupingLabel(grouping) {
        if (grouping === 'daily') {
            return lang.daily || 'Daily';
        }
        if (grouping === 'monthly') {
            return lang.monthly || 'Monthly';
        }
        return lang.weekly || 'Weekly';
    }

    function setMenuActiveState(menuId, attr, value) {
        var menu = $(menuId);
        if (!menu) {
            return;
        }
        menu.querySelectorAll('[' + attr + ']').forEach(function (item) {
            item.classList.toggle('active', String(item.getAttribute(attr)) === String(value));
        });
    }

    function closeReportsDropdowns() {
        document.querySelectorAll('.reports-page .task-reports-toolbar-menu').forEach(function (el) {
            el.classList.remove('active');
            resetReportsDropdownSearch(el);
        });
        document.querySelectorAll('.reports-page .task-reports-toolbar-toggle').forEach(function (btn) {
            btn.setAttribute('aria-expanded', 'false');
        });
    }

    function wrapReportsDropdownMenu(menu) {
        if (!menu || menu.querySelector('.task-reports-toolbar-menu-list')) {
            return;
        }
        var buttons = Array.prototype.slice.call(menu.querySelectorAll(':scope > button'));
        if (buttons.length === 0) {
            return;
        }
        var list = document.createElement('div');
        list.className = 'task-reports-toolbar-menu-list';
        buttons.forEach(function (btn) {
            list.appendChild(btn);
        });
        menu.appendChild(list);
    }

    function filterReportsDropdownMenu(menu, query) {
        if (!menu) {
            return;
        }
        var list = menu.querySelector('.task-reports-toolbar-menu-list') || menu;
        var q = String(query || '').trim().toLowerCase();
        var visible = 0;
        list.querySelectorAll('button').forEach(function (btn) {
            var labelNode = btn.querySelector('span');
            var label = (labelNode ? labelNode.textContent : btn.textContent).trim().toLowerCase();
            var show = q === '' || label.indexOf(q) !== -1;
            btn.style.display = show ? '' : 'none';
            if (show) {
                visible++;
            }
        });
        var emptyEl = menu.querySelector('.task-reports-toolbar-menu-empty');
        if (menu.querySelector('.task-reports-toolbar-menu-search')) {
            if (!emptyEl) {
                emptyEl = document.createElement('div');
                emptyEl.className = 'task-reports-toolbar-menu-empty';
                emptyEl.textContent = lang.noResults || 'No results';
                list.appendChild(emptyEl);
            }
            emptyEl.style.display = visible === 0 ? 'block' : 'none';
        } else if (emptyEl) {
            emptyEl.style.display = 'none';
        }
    }

    function resetReportsDropdownSearch(menu) {
        if (!menu) {
            return;
        }
        var input = menu.querySelector('.task-reports-toolbar-menu-search');
        if (input) {
            input.value = '';
        }
        if (menu.getAttribute('data-server-search') === '1' || (input && input.getAttribute('data-server-search') === '1')) {
            return;
        }
        filterReportsDropdownMenu(menu, '');
    }

    function initReportsDropdownSearch() {
        document.querySelectorAll('.reports-page .task-reports-toolbar-menu-search').forEach(function (input) {
            if (input._reportsSearchBound) {
                return;
            }
            input._reportsSearchBound = true;
            input.addEventListener('input', function () {
                if (input.getAttribute('data-server-search') === '1') {
                    return;
                }
                filterReportsDropdownMenu(input.closest('.task-reports-toolbar-menu'), input.value);
            });
            input.addEventListener('click', function (e) {
                e.stopPropagation();
            });
            input.addEventListener('keydown', function (e) {
                e.stopPropagation();
                if (e.key === 'Escape') {
                    closeReportsDropdowns();
                }
            });
        });
    }

    function initReportsDropdownMenus() {
        document.querySelectorAll('.reports-page .task-reports-toolbar-menu').forEach(function (menu) {
            wrapReportsDropdownMenu(menu);
        });
        initReportsDropdownSearch();
    }

    function toggleReportsDropdown(menuId, event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        var dropdown = $(menuId);
        if (!dropdown) {
            return;
        }
        var willOpen = !dropdown.classList.contains('active');
        closeReportsDropdowns();
        if (willOpen) {
            wrapReportsDropdownMenu(dropdown);
            initReportsDropdownSearch();
            dropdown.classList.add('active');
            resetReportsDropdownSearch(dropdown);
            var searchInput = dropdown.querySelector('.task-reports-toolbar-menu-search');
            if (searchInput) {
                window.setTimeout(function () {
                    searchInput.focus();
                }, 0);
            }
            var toggle = document.querySelector('.reports-page [data-reports-dropdown="' + menuId + '"]');
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'true');
            }
        }
    }

    function getFilterBtnTextMap() {
        if (isInvoice) {
            return {
                date_preset: 'invoiceReportsDatePresetBtnText',
                client_id: 'invoiceReportsClientIdBtnText',
                project_id: 'invoiceReportsProjectIdBtnText',
                currency: 'invoiceReportsCurrencyBtnText',
                status: 'invoiceReportsStatusBtnText'
            };
        }
        var p = isProject ? 'projectReports' : 'taskReports';
        return {
            date_preset: p + 'DatePresetBtnText',
            user_id: p + 'UserIdBtnText',
            client_id: p + 'ClientIdBtnText',
            project_id: p + 'ProjectIdBtnText',
            campaign_id: 'taskReportsCampaignIdBtnText',
            status: p + 'StatusBtnText'
        };
    }

    function getFilterMenuMap() {
        if (isInvoice) {
            return {
                date_preset: 'invoiceReportsDatePresetDropdown',
                client_id: 'invoiceReportsClientIdDropdown',
                project_id: 'invoiceReportsProjectIdDropdown',
                currency: 'invoiceReportsCurrencyDropdown',
                status: 'invoiceReportsStatusDropdown'
            };
        }
        var p = isProject ? 'projectReports' : 'taskReports';
        return {
            date_preset: p + 'DatePresetDropdown',
            user_id: p + 'UserIdDropdown',
            client_id: p + 'ClientIdDropdown',
            project_id: p + 'ProjectIdDropdown',
            campaign_id: 'taskReportsCampaignIdDropdown',
            status: p + 'StatusDropdown'
        };
    }

    function setFilterField(field, value, label) {
        var form = getForm();
        if (!form) {
            return;
        }
        var input = getFilterInput(form, field);
        if (input) {
            input.value = value;
        }
        if (isInvoice && field === 'currency') {
            syncInvoiceCurrencyQueryParam(value);
        }
        var btnTextId = getFilterBtnTextMap()[field];
        if (btnTextId) {
            var btnText = $(btnTextId);
            if (btnText) {
                btnText.textContent = label;
            }
        }
        if (isEcommerce && cfg.dashboardChartsOnly && field === 'date_preset') {
            var dashDateBtn = $('ecomDashDatePresetBtnText');
            if (dashDateBtn) {
                dashDateBtn.textContent = label;
            }
        }
        var menuId = getFilterMenuMap()[field];
        if (menuId) {
            var menu = $(menuId);
            if (menu) {
                menu.querySelectorAll('[data-filter-field="' + field + '"]').forEach(function (item) {
                    item.classList.toggle('active', String(item.getAttribute('data-filter-value')) === String(value));
                });
            }
        }
        if (field === 'date_preset') {
            toggleCustomDates();
        }
        closeReportsDropdowns();
    }

    function setChartUserButton(label) {
        var el = $(isProject ? 'projectBarChartUserBtnText' : 'taskBarChartUserBtnText');
        if (el) {
            el.textContent = label;
        }
    }

    function setChartGroupButton(grouping) {
        var el = $(isProject ? 'projectBarChartGroupBtnText' : 'taskBarChartGroupBtnText');
        if (el) {
            el.textContent = getGroupingLabel(grouping);
        }
    }

    function setChartUser(userId, label) {
        chartBarState.userId = userId == null ? '' : String(userId);
        setChartUserButton(label || (lang.allStaff || 'All Staff'));
        setMenuActiveState((isProject ? 'projectBarChartUserMenu' : 'taskBarChartUserMenu'), 'data-chart-user', chartBarState.userId);
        renderBarChart();
        updateBarSummary();
    }

    function setChartGrouping(grouping) {
        chartBarState.grouping = grouping || 'weekly';
        setChartGroupButton(chartBarState.grouping);
        setMenuActiveState((isProject ? 'projectBarChartGroupMenu' : 'taskBarChartGroupMenu'), 'data-chart-group', chartBarState.grouping);
        renderBarChart();
        updateBarSummary();
    }

    function populateChartUserMenu(staff) {
        var menu = $(isProject ? 'projectBarChartUserMenu' : 'taskBarChartUserMenu');
        if (!menu) {
            return;
        }
        var current = chartBarState.userId;
        var staffItems = staff || [];
        var html = '<button type="button" class="first' + (current === '' ? ' active' : '') + '" data-chart-user=""><span>' + escapeHtml(lang.allStaff || 'All Staff') + '</span></button>';
        var label = lang.allStaff || 'All Staff';
        var found = current === '';
        staffItems.forEach(function (s, index) {
            var id = String(s.id);
            if (current === id) {
                found = true;
                label = s.name;
            }
            var btnClass = index === staffItems.length - 1 ? 'last' : '';
            if (current === id) {
                btnClass += (btnClass ? ' ' : '') + 'active';
            }
            html += '<button type="button" class="' + btnClass + '" data-chart-user="' + escapeHtml(id) + '"><span>' + escapeHtml(s.name) + '</span></button>';
        });
        menu.innerHTML = html;
        if (!found) {
            chartBarState.userId = '';
            label = lang.allStaff || 'All Staff';
        }
        setChartUserButton(label);
    }

    function chartHasPositiveValues(values) {
        return (values || []).some(function (v) {
            return Number(v) > 0;
        });
    }

    function getReportsBarSizeOptions(labelCount) {
        if (labelCount <= 1) {
            return { barPercentage: 0.55, categoryPercentage: 0.4 };
        }
        if (labelCount <= 3) {
            return { barPercentage: 0.8, categoryPercentage: 0.7 };
        }
        return { barPercentage: 0.85, categoryPercentage: 0.75 };
    }

    function getReportsBarXAxisOptions(grouping, labelCount) {
        var singleCategory = labelCount <= 1;
        return {
            stacked: false,
            offset: !singleCategory,
            gridLines: {
                display: false,
                offsetGridLines: false,
                drawBorder: false
            },
            ticks: Object.assign({
                autoSkip: true,
                maxTicksLimit: grouping === 'daily' ? 10 : 8,
                maxRotation: 0,
                minRotation: 0,
                padding: 2
            }, getAnalyticsChartTickStyle())
        };
    }

    function isReportsInvoiceStackedBarChart(chart) {
        if (!chart || !chart.canvas) {
            return false;
        }
        var id = chart.canvas.id;
        return id === 'invoiceSalesBarChart' || id === 'ecommerceSalesBarChart';
    }

    function isReportsStackBarTopSegment(chart, datasetIndex, dataIndex) {
        if (!chart || !chart.data || !chart.data.datasets) {
            return true;
        }
        for (var d = datasetIndex + 1; d < chart.data.datasets.length; d++) {
            var val = chart.data.datasets[d].data[dataIndex];
            if (Number(val) > 0) {
                return false;
            }
        }
        return true;
    }

    function isReportsRoundedBarChart(chart) {
        if (!chart || !chart.canvas) {
            return false;
        }
        var id = chart.canvas.id;
        return id === 'taskTimeBarChart' || id === 'projectTimeBarChart';
    }

    function getReportsInvoiceStackedBarXAxisOptions(grouping, labelCount) {
        var opts = getReportsBarXAxisOptions(grouping, labelCount);
        opts.stacked = true;
        return opts;
    }

    function getReportsFinancialBarTooltipOptions(symbol, currencyCode) {
        var tooltipId = 'reportsFinancialBarTooltip';
        return {
            enabled: false,
            mode: 'index',
            intersect: false,
            filter: function (tooltipItem, data) {
                var val = data.datasets[tooltipItem.datasetIndex].data[tooltipItem.index];
                return Number(val) > 0;
            },
            custom: function (tooltipModel) {
                var tooltipEl = document.getElementById(tooltipId);
                if (!tooltipEl) {
                    tooltipEl = document.createElement('div');
                    tooltipEl.id = tooltipId;
                    tooltipEl.className = 'reports-financial-chart-tooltip';
                    document.body.appendChild(tooltipEl);
                }
                if (!tooltipModel || tooltipModel.opacity === 0) {
                    tooltipEl.style.opacity = '0';
                    tooltipEl.style.pointerEvents = 'none';
                    return;
                }
                var title = (tooltipModel.title || []).join('');
                var bodyHtml = '';
                (tooltipModel.body || []).forEach(function (bodyItem, i) {
                    var colors = tooltipModel.labelColors[i] || {};
                    var swatchColor = colors.backgroundColor || colors.borderColor || '#ccc';
                    var safeColor = String(swatchColor).replace(/[<>"']/g, '');
                    bodyHtml += '<div class="reports-financial-chart-tooltip__row">' +
                        '<span class="reports-financial-chart-tooltip__swatch" style="background:' + safeColor + ';"></span>' +
                        '<span class="reports-financial-chart-tooltip__text">' + escapeHtml((bodyItem.lines || []).join(' ')) + '</span>' +
                        '</div>';
                });
                tooltipEl.innerHTML = '<div class="reports-financial-chart-tooltip__title">' + escapeHtml(title) + '</div>' + bodyHtml;
                var canvas = this._chart.canvas;
                var rect = canvas.getBoundingClientRect();
                tooltipEl.style.opacity = '1';
                tooltipEl.style.pointerEvents = 'none';
                var offsetX = 14;
                var offsetY = 12;
                var placeLeft = tooltipModel.xAlign === 'right' ||
                    (tooltipModel.xAlign !== 'left' && tooltipModel.caretX > rect.width * 0.55);
                tooltipEl.style.left = (rect.left + window.pageXOffset + tooltipModel.caretX) + 'px';
                tooltipEl.style.top = (rect.top + window.pageYOffset + tooltipModel.caretY) + 'px';
                tooltipEl.style.transform = placeLeft
                    ? 'translate(calc(-100% - ' + offsetX + 'px), calc(-100% - ' + offsetY + 'px))'
                    : 'translate(' + offsetX + 'px, calc(-100% - ' + offsetY + 'px))';
            },
            callbacks: {
                label: function (tooltipItem, data) {
                    var label = data.datasets[tooltipItem.datasetIndex].label || '';
                    var value = data.datasets[tooltipItem.datasetIndex].data[tooltipItem.index] || 0;
                    return label + ': ' + formatMoneyAmount(value, symbol, currencyCode);
                }
            }
        };
    }

    function installRoundedBarDraw() {
        if (barRoundedDrawInstalled || typeof Chart === 'undefined' || !Chart.elements || !Chart.elements.Rectangle) {
            return;
        }
        barRoundedDrawInstalled = true;
        var originalDraw = Chart.elements.Rectangle.prototype.draw;
        Chart.elements.Rectangle.prototype.draw = function () {
            var chart = this._chart;
            var vm = this._view;
            if (!vm) {
                originalDraw.call(this);
                return;
            }
            if (isReportsInvoiceStackedBarChart(chart)) {
                if (vm.horizontal) {
                    originalDraw.call(this);
                    return;
                }
                var ctxStack = chart.ctx;
                var xStack = vm.x;
                var widthStack = vm.width;
                var leftStack = xStack - widthStack / 2;
                var rightStack = xStack + widthStack / 2;
                var segTop = Math.min(vm.y, vm.base);
                var segBottom = Math.max(vm.y, vm.base);
                var heightStack = segBottom - segTop;
                if (heightStack <= 0) {
                    return;
                }
                var datasetIndex = this._datasetIndex;
                var dataIndex = this._index;
                var isTop = isReportsStackBarTopSegment(chart, datasetIndex, dataIndex);
                var radiusStack = Math.min(10, widthStack / 2, heightStack);
                ctxStack.save();
                ctxStack.fillStyle = vm.backgroundColor;
                ctxStack.beginPath();
                if (isTop) {
                    ctxStack.moveTo(leftStack, segBottom);
                    ctxStack.lineTo(leftStack, segTop + radiusStack);
                    ctxStack.quadraticCurveTo(leftStack, segTop, leftStack + radiusStack, segTop);
                    ctxStack.lineTo(rightStack - radiusStack, segTop);
                    ctxStack.quadraticCurveTo(rightStack, segTop, rightStack, segTop + radiusStack);
                    ctxStack.lineTo(rightStack, segBottom);
                    ctxStack.closePath();
                } else {
                    ctxStack.rect(leftStack, segTop, widthStack, heightStack);
                }
                ctxStack.fill();
                if (vm.borderWidth) {
                    ctxStack.strokeStyle = vm.borderColor;
                    ctxStack.lineWidth = vm.borderWidth;
                    ctxStack.stroke();
                }
                ctxStack.restore();
                return;
            }
            if (!isReportsRoundedBarChart(chart)) {
                originalDraw.call(this);
                return;
            }
            if (vm.horizontal) {
                originalDraw.call(this);
                return;
            }
            var ctx = chart.ctx;
            var x = vm.x;
            var width = vm.width;
            var left = x - width / 2;
            var right = x + width / 2;
            var bottom = vm.base;
            var yScale = chart.scales['y-axis-0'];
            if (yScale) {
                bottom = yScale.getPixelForValue(0);
            }
            var top = Math.min(vm.y, vm.base);
            if (top > bottom) {
                top = bottom;
            }
            var height = bottom - top;
            if (height <= 0) {
                return;
            }
            var radius = Math.min(10, width / 2, height);
            ctx.save();
            ctx.fillStyle = vm.backgroundColor;
            ctx.beginPath();
            ctx.moveTo(left, bottom);
            ctx.lineTo(left, top + radius);
            ctx.quadraticCurveTo(left, top, left + radius, top);
            ctx.lineTo(right - radius, top);
            ctx.quadraticCurveTo(right, top, right, top + radius);
            ctx.lineTo(right, bottom);
            ctx.closePath();
            ctx.fill();
            if (vm.borderWidth) {
                ctx.strokeStyle = vm.borderColor;
                ctx.lineWidth = vm.borderWidth;
                ctx.stroke();
            }
            ctx.restore();
        };
    }

    function buildBuckets(from, to, grouping) {
        var buckets = [];
        var start = parseYmd(from);
        var end = parseYmd(to);
        if (grouping === 'daily') {
            var d = new Date(start.getTime());
            while (d <= end) {
                buckets.push({
                    key: formatYmd(d),
                    label: d.toLocaleDateString(undefined, { weekday: 'short' }),
                    start: formatYmd(d),
                    end: formatYmd(d)
                });
                d.setDate(d.getDate() + 1);
            }
            return buckets;
        }
        if (grouping === 'weekly') {
            var w = 1;
            var cursor = new Date(start.getTime());
            while (cursor <= end) {
                var chunkEnd = new Date(cursor.getTime());
                chunkEnd.setDate(chunkEnd.getDate() + 6);
                if (chunkEnd > end) {
                    chunkEnd.setTime(end.getTime());
                }
                buckets.push({
                    key: 'w' + w,
                    label: (lang.week || 'Week') + ' ' + w,
                    start: formatYmd(cursor),
                    end: formatYmd(chunkEnd)
                });
                w += 1;
                cursor = new Date(chunkEnd.getTime());
                cursor.setDate(cursor.getDate() + 1);
            }
            return buckets;
        }
        var m = new Date(start.getFullYear(), start.getMonth(), 1);
        var lastMonth = new Date(end.getFullYear(), end.getMonth(), 1);
        while (m <= lastMonth) {
            var monthEnd = new Date(m.getFullYear(), m.getMonth() + 1, 0);
            var rangeStart = m < start ? start : m;
            var rangeEnd = monthEnd > end ? end : monthEnd;
            buckets.push({
                key: m.getFullYear() + '-' + String(m.getMonth() + 1).padStart(2, '0'),
                label: m.toLocaleDateString(undefined, { month: 'short', year: 'numeric' }),
                start: formatYmd(rangeStart),
                end: formatYmd(rangeEnd)
            });
            m = new Date(m.getFullYear(), m.getMonth() + 1, 1);
        }
        return buckets;
    }

    function entryInBucket(entry, bucket) {
        return entry.date >= bucket.start && entry.date <= bucket.end;
    }

    function buildChartDataset(entries, buckets, chartUserId, datasetLabel, color, barSize) {
        var size = barSize || getReportsBarSizeOptions(buckets.length);
        var data = buckets.map(function (bucket) {
            var sec = 0;
            entries.forEach(function (entry) {
                if (!entryInBucket(entry, bucket)) {
                    return;
                }
                if (chartUserId && String(entry.user_id) !== String(chartUserId)) {
                    return;
                }
                sec += entry.seconds || 0;
            });
            if (isMarketing) {
                return sec;
            }
            return Math.round((sec / 3600) * 100) / 100;
        });
        return {
            label: datasetLabel,
            data: data,
            backgroundColor: color,
            borderColor: color,
            borderWidth: 1,
            barPercentage: size.barPercentage,
            categoryPercentage: size.categoryPercentage
        };
    }

    function renderBarChart() {
        if (typeof Chart === 'undefined') {
            return;
        }
        var canvas = $(isProject ? 'projectTimeBarChart' : 'taskTimeBarChart');
        if (!canvas || !lastFilters.from_date || !lastFilters.to_date) {
            return;
        }

        installRoundedBarDraw();
        var grouping = chartBarState.grouping || getDefaultGrouping(lastFilters);
        var chartUserId = chartBarState.userId;

        var buckets = buildBuckets(lastFilters.from_date, lastFilters.to_date, grouping);
        var labels = buckets.map(function (b) {
            return b.label;
        });
        var labelCount = labels.length;
        var barSize = getReportsBarSizeOptions(labelCount);

        var colors = getChartColors();
        var entries = barRaw.entries || [];
        var datasetLabel;
        if (!chartUserId) {
            datasetLabel = lang.totalTrackedTime || 'Total Time Tracked';
        } else {
            var staffMatch = (barRaw.staff || []).filter(function (s) {
                return String(s.id) === String(chartUserId);
            });
            datasetLabel = staffMatch.length ? staffMatch[0].name : (lang.totalTrackedTime || 'Total Time Tracked');
        }

        var datasets = [buildChartDataset(entries, buckets, chartUserId, datasetLabel, colors.primary, barSize)];
        var axisColors = getReportsChartAxisColors();

        if (barChart) {
            barChart.destroy();
        }

        barChart = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { display: false },
                layout: {
                    padding: {
                        left: 4,
                        right: 4,
                        top: 4,
                        bottom: 0
                    }
                },
                scales: {
                    xAxes: [getReportsBarXAxisOptions(grouping, labelCount)],
                    yAxes: [{
                        stacked: false,
                        gridLines: getReportsChartYGridLineOptions(axisColors),
                        ticks: Object.assign({
                            beginAtZero: true,
                            min: 0,
                            maxTicksLimit: 6,
                            callback: function (v) {
                                if (isMarketing) {
                                    return v;
                                }
                                return v + 'h';
                            }
                        }, getAnalyticsChartYTickStyle())
                    }]
                },
                tooltips: {
                    mode: 'nearest',
                    intersect: true,
                    backgroundColor: '#fff',
                    titleFontColor: '#888',
                    titleFontSize: 14,
                    titleFontStyle: 'normal',
                    bodyFontColor: '#222',
                    bodyFontSize: 14,
                    bodyFontStyle: 'bold',
                    borderColor: '#eee',
                    borderWidth: 1,
                    xPadding: 16,
                    yPadding: 12,
                    caretPadding: 10,
                    displayColors: false,
                    cornerRadius: 10,
                    callbacks: {
                        title: function (items) {
                            if (!items.length) {
                                return '';
                            }
                            return buckets[items[0].index] ? buckets[items[0].index].label : '';
                        },
                        label: function (tooltipItem) {
                            var val = tooltipItem.yLabel;
                            if (isMarketing) {
                                return (datasetLabel || '') + ': ' + formatTooltipCount(val);
                            }
                            return (datasetLabel || '') + ': ' + formatTooltipHours(val);
                        }
                    }
                }
            }
        });
        patchBarChartResize(barChart);
        barChart.resize();
        applyBarChartInlineHeight(canvas);
    }


    function getComparisonVsLabel(comparison) {
        var key = (comparison && comparison.label_key) ? comparison.label_key : 'since_previous_period';
        var map = {
            since_yesterday: lang.vsYesterday || 'vs yesterday',
            since_day_before: lang.vsDayBefore || 'vs day before',
            since_last_week: lang.vsLastWeek || 'vs last week',
            since_previous_week: lang.vsPrevWeek || 'vs prev week',
            since_last_month: lang.vsLastMonth || 'vs last month',
            since_previous_month: lang.vsPrevMonth || 'vs prev month',
            since_previous_period: lang.vsLastPeriod || 'vs last period'
        };
        return map[key] || (lang.vsLastPeriod || 'vs last period');
    }

    function renderCardComparison(arrowId, compareId, metricKey, comparison, suffixUser) {
        var arrowEl = $(arrowId);
        var compareEl = $(compareId);
        if (!arrowEl || !compareEl) {
            return;
        }
        arrowEl.innerHTML = '';
        compareEl.textContent = '';
        if (!comparison || !comparison.metrics || !comparison.metrics[metricKey]) {
            return;
        }
        var metric = comparison.metrics[metricKey];
        var pct = metric.percent != null ? metric.percent : 0;
        var direction = metric.direction || 'flat';
        var vsLabel = getComparisonVsLabel(comparison);
        var userSuffix = suffixUser
            ? ' ' + escapeHtml(lang.by || 'by') + ' ' + escapeHtml(suffixUser)
            : '';

        if (direction === 'up') {
            arrowEl.style.color = 'green';
            arrowEl.innerHTML = reportsTsIcon('arrow-up-right');
        } else if (direction === 'down') {
            arrowEl.style.color = 'red';
            arrowEl.innerHTML = reportsTsIcon('arrow-down-right');
        } else {
            arrowEl.style.color = '';
            arrowEl.innerHTML = reportsTsIcon('arrows-up-down');
        }

        if (direction === 'flat' || pct === 0) {
            compareEl.innerHTML =
                '<span class="task-reports-compare-flat">' +
                escapeHtml(lang.noChange || 'No change') + ' ' + escapeHtml(vsLabel) +
                '</span>' + userSuffix;
            return;
        }

        var pctAbs = Math.abs(pct);
        var pctNum = String(Math.round(pctAbs));
        var sign = direction === 'up' ? '+' : '-';
        var color = direction === 'up' ? 'green' : 'red';
        var pctPart = sign + pctNum + '%';

        compareEl.innerHTML =
            '<span style="color:' + color + ';">' + escapeHtml(pctPart) + '</span> ' +
            escapeHtml(vsLabel) + userSuffix;
    }

    function renderEcommerceCardComparison(arrowId, compareId, metricKey, comparison) {
        var arrowEl = $(arrowId);
        var compareEl = $(compareId);
        if (!arrowEl || !compareEl) {
            return;
        }
        arrowEl.innerHTML = '';
        compareEl.textContent = '';
        if (!comparison || !comparison.metrics || !comparison.metrics[metricKey]) {
            return;
        }
        var metric = comparison.metrics[metricKey];
        var pct = metric.percent != null ? metric.percent : 0;
        var direction = metric.direction || 'flat';
        var vsLabel = getComparisonVsLabel(comparison);
        if (direction === 'up') {
            arrowEl.style.color = 'green';
            arrowEl.innerHTML = reportsTsIcon('arrow-up-right');
        } else if (direction === 'down') {
            arrowEl.style.color = 'red';
            arrowEl.innerHTML = reportsTsIcon('arrow-down-right');
        } else {
            arrowEl.style.color = '';
            arrowEl.innerHTML = reportsTsIcon('arrows-up-down');
        }
        var pctAbs = Math.abs(pct);
        var pctText = pct === 0 ? (lang.noChange || 'No change') : (pctAbs + '%');
        compareEl.textContent = pctText + ' ' + vsLabel;
    }

    function updateEcommerceKpis(kpis) {
        if (!kpis || !isEcommerce) {
            return;
        }
        var comparison = kpis.comparison || null;
        if ($('dashCardTotalSales')) {
            $('dashCardTotalSales').textContent = kpis.total_sales_formatted || kpis.total_sales || '0';
        }
        renderEcommerceCardComparison('dashCardTotalSalesArrow', 'dashCardTotalSalesCompare', 'total_sales', comparison);
        if ($('dashCardNetSales')) {
            $('dashCardNetSales').textContent = kpis.net_sales_formatted || kpis.net_sales || '0';
        }
        renderEcommerceCardComparison('dashCardNetSalesArrow', 'dashCardNetSalesCompare', 'net_sales', comparison);
        if ($('dashCardAov')) {
            $('dashCardAov').textContent = kpis.aov_formatted || kpis.aov || '0';
        }
        renderEcommerceCardComparison('dashCardAovArrow', 'dashCardAovCompare', 'aov', comparison);
        if ($('dashCardRefundedOrdersCount')) {
            var refundedCount = kpis.refunded_orders != null ? kpis.refunded_orders : 0;
            $('dashCardRefundedOrdersCount').textContent = '(' + refundedCount + ')';
        }
        if ($('dashCardRefundedAmount')) {
            $('dashCardRefundedAmount').textContent = kpis.refund_amount_formatted || kpis.refund_amount || '0';
        }
        renderEcommerceCardComparison('dashCardRefundedOrdersArrow', 'dashCardRefundedOrdersCompare', 'refunded_orders', comparison);
        if ($('dashCardShipping')) {
            $('dashCardShipping').textContent = kpis.shipping_formatted || kpis.shipping || '0';
        }
        renderEcommerceCardComparison('dashCardShippingArrow', 'dashCardShippingCompare', 'shipping', comparison);
        if ($('dashCardProductsSold')) {
            $('dashCardProductsSold').textContent = kpis.products_sold != null ? kpis.products_sold : 0;
        }
        renderEcommerceCardComparison('dashCardProductsSoldArrow', 'dashCardProductsSoldCompare', 'products_sold', comparison);
    }

    function updateCards(cards) {
        if (!cards) {
            return;
        }
        var comparison = cards.comparison || null;
        if (isEcommerce) {
            return;
        }
        if (isInvoice) {
            if ($('cardTotalInvoices')) {
                $('cardTotalInvoices').textContent = cards.total_invoices != null ? cards.total_invoices : 0;
            }
            renderCardComparison('cardTotalInvoicesArrow', 'cardTotalInvoicesCompare', 'total_invoices', comparison);
            if ($('cardPaidInvoices')) {
                $('cardPaidInvoices').textContent = cards.paid_invoices != null ? cards.paid_invoices : 0;
            }
            renderCardComparison('cardPaidInvoicesArrow', 'cardPaidInvoicesCompare', 'paid_invoices', comparison);
            if ($('cardUnpaidInvoices')) {
                $('cardUnpaidInvoices').textContent = cards.unpaid_invoices != null ? cards.unpaid_invoices : 0;
            }
            renderCardComparison('cardUnpaidInvoicesArrow', 'cardUnpaidInvoicesCompare', 'unpaid_invoices', comparison);
            if ($('cardOverdueInvoices')) {
                $('cardOverdueInvoices').textContent = cards.overdue_invoices != null ? cards.overdue_invoices : 0;
            }
            renderCardComparison('cardOverdueInvoicesArrow', 'cardOverdueInvoicesCompare', 'overdue_invoices', comparison);
            if ($('cardIssuedInvoices')) {
                $('cardIssuedInvoices').textContent = cards.issued_invoices != null ? cards.issued_invoices : 0;
            }
            renderCardComparison('cardIssuedInvoicesArrow', 'cardIssuedInvoicesCompare', 'issued_invoices', comparison);
            if ($('cardCancelledInvoices')) {
                $('cardCancelledInvoices').textContent = cards.cancelled_invoices != null ? cards.cancelled_invoices : 0;
            }
            renderCardComparison('cardCancelledInvoicesArrow', 'cardCancelledInvoicesCompare', 'cancelled_invoices', comparison);
            return;
        }
        if (isProject) {
            if ($('cardTotalProjects')) {
                $('cardTotalProjects').textContent = cards.total_projects != null ? cards.total_projects : 0;
            }
            renderCardComparison('cardTotalProjectsArrow', 'cardTotalProjectsCompare', 'total_projects', comparison);
            if ($('cardCompletedProjects')) {
                $('cardCompletedProjects').textContent = cards.completed_projects != null ? cards.completed_projects : 0;
            }
            renderCardComparison('cardCompletedProjectsArrow', 'cardCompletedProjectsCompare', 'completed_projects', comparison);
            if ($('cardActiveProjects')) {
                $('cardActiveProjects').textContent = cards.active_projects != null ? cards.active_projects : 0;
            }
            renderCardComparison('cardActiveProjectsArrow', 'cardActiveProjectsCompare', 'active_projects', comparison);
            if ($('cardOverdueProjects')) {
                $('cardOverdueProjects').textContent = cards.overdue_projects != null ? cards.overdue_projects : 0;
            }
            renderCardComparison('cardOverdueProjectsArrow', 'cardOverdueProjectsCompare', 'overdue_projects', comparison);
            if ($('cardTotalTime')) {
                $('cardTotalTime').textContent = cards.total_time || '0m';
            }
            renderCardComparison('cardTotalTimeArrow', 'cardTotalTimeCompare', 'total_time_sec', comparison);
            if ($('cardAvgTimePerProject')) {
                $('cardAvgTimePerProject').textContent = cards.avg_time_per_project || '0m';
            }
            renderCardComparison('cardAvgTimePerProjectArrow', 'cardAvgTimePerProjectCompare', 'avg_time_per_project_sec', comparison);
            return;
        }
        if ($('cardTotalTasks')) {
            $('cardTotalTasks').textContent = cards.total_tasks != null ? cards.total_tasks : 0;
        }
        renderCardComparison('cardTotalTasksArrow', 'cardTotalTasksCompare', 'total_tasks', comparison);
        if ($('cardCompletedTasks')) {
            $('cardCompletedTasks').textContent = cards.completed_tasks != null ? cards.completed_tasks : 0;
        }
        renderCardComparison('cardCompletedTasksArrow', 'cardCompletedTasksCompare', 'completed_tasks', comparison);
        if ($('cardPendingTasks')) {
            $('cardPendingTasks').textContent = cards.pending_tasks != null ? cards.pending_tasks : 0;
        }
        renderCardComparison('cardPendingTasksArrow', 'cardPendingTasksCompare', 'pending_tasks', comparison);
        if ($('cardOverdueTasks')) {
            $('cardOverdueTasks').textContent = cards.overdue_tasks != null ? cards.overdue_tasks : 0;
        }
        renderCardComparison('cardOverdueTasksArrow', 'cardOverdueTasksCompare', 'overdue_tasks', comparison);
        if ($('cardTotalTime')) {
            $('cardTotalTime').textContent = cards.total_time || '0m';
        }
        renderCardComparison('cardTotalTimeArrow', 'cardTotalTimeCompare', 'total_time_sec', comparison);
        if ($('cardAvgTimePerTask')) {
            $('cardAvgTimePerTask').textContent = cards.avg_time_per_task || '0m';
        }
        renderCardComparison('cardAvgTimePerTaskArrow', 'cardAvgTimePerTaskCompare', 'avg_time_per_task_sec', comparison);
    }

    function getChartColors() {
        var root = getComputedStyle(document.documentElement);
        var borderColor = (root.getPropertyValue('--border-color') || '#c0c0c0').trim();
        return {
            primary: (root.getPropertyValue('--primary-color') || '#0088ff').trim(),
            secondary: (root.getPropertyValue('--secondary-color') || '#1cc88a').trim(),
            border: borderColor,
            tick: '#c0c0c0',
            card: (root.getPropertyValue('--card-body-color') || '#fff').trim()
        };
    }

    function darkenColor(color, amount) {
        var ratio = amount == null ? 0.2 : amount;
        if (!color) {
            return color;
        }
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
            hex = hex.split('').map(function (ch) {
                return ch + ch;
            }).join('');
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

    function getReportsChartAxisColors() {
        var colors = getChartColors();
        var tickBase = colors.tick || colors.border || '#c0c0c0';
        return {
            border: darkenColor(colors.border, 0.2),
            tick: darkenColor(tickBase, 0.2)
        };
    }

    function getReportsChartYGridLineOptions(axisColors) {
        var colors = axisColors || getReportsChartAxisColors();
        return {
            color: colors.border,
            zeroLineColor: colors.border,
            borderColor: colors.border,
            drawBorder: false,
            lineWidth: 1,
            borderDash: [3, 4],
            zeroLineBorderDash: [3, 4],
            drawOnChartArea: true,
            offsetGridLines: false
        };
    }

    function getAnalyticsChartTickStyle() {
        var axisColors = getReportsChartAxisColors();
        return {
            fontColor: darkenColor(axisColors.tick, 0.2),
            fontSize: 12
        };
    }

    function getAnalyticsChartYTickStyle() {
        return Object.assign({}, getAnalyticsChartTickStyle(), { padding: 10 });
    }

    function getProjectLegendColor(key) {
        var el = $('projectReportsLegendColor-' + key);
        if (!el) {
            return '';
        }
        var val = getComputedStyle(el).color;
        return val && val !== 'rgba(0, 0, 0, 0)' ? val.trim() : '';
    }

    function getInvoiceLegendColor(key) {
        var el = $('invoiceReportsLegendColor-' + key);
        if (!el) {
            return '';
        }
        var val = getComputedStyle(el).color;
        return val && val !== 'rgba(0, 0, 0, 0)' ? val.trim() : '';
    }

    /** Paid / Unpaid / Cancel — shared by Financial Overview charts and Payment Status donut. */
    function getInvoiceFinancialSeriesColors() {
        return {
            paid: getInvoiceLegendColor('paid') || '#28a745',
            unpaid: getInvoiceLegendColor('unpaid') || '#dc3545',
            cancelled: getInvoiceLegendColor('cancelled') || '#adb5bd'
        };
    }

    function getInvoiceDonutColors() {
        var financial = getInvoiceFinancialSeriesColors();
        return {
            paid: financial.paid,
            unpaid: financial.unpaid,
            overdue: getInvoiceLegendColor('overdue') || '#fd7e14',
            cancelled: financial.cancelled
        };
    }

    function applyInvoiceLegendColors() {
        var donutColors = getInvoiceDonutColors();
        var map = {
            paid: 'legendPaid',
            unpaid: 'legendUnpaid',
            overdue: 'legendOverdueInv',
            cancelled: 'legendCancelled'
        };
        Object.keys(map).forEach(function (key) {
            var el = $(map[key]);
            if (!el) {
                return;
            }
            var color = donutColors[key];
            if (color) {
                el.style.color = color;
            }
        });
    }

    function getEcommerceLegendColor(key) {
        var el = $('ecommerceReportsLegendColor-' + key);
        if (!el) {
            return '';
        }
        var val = getComputedStyle(el).color;
        return val && val !== 'rgba(0, 0, 0, 0)' ? val.trim() : '';
    }

    function getEcommerceFinancialSeriesColors() {
        return {
            complete: getEcommerceLegendColor('complete') || '#28a745',
            processing: getEcommerceLegendColor('processing') || '#0088ff',
            pending: getEcommerceLegendColor('pending') || '#f2711c',
            cancel: getEcommerceLegendColor('cancel') || '#dc3545'
        };
    }

    function applyEcommerceLegendColors() {
        var colors = getEcommerceFinancialSeriesColors();
        var map = {
            complete: 'legendComplete',
            processing: 'legendProcessing',
            pending: 'legendPending',
            cancel: 'legendCancel'
        };
        Object.keys(map).forEach(function (key) {
            var el = $(map[key]);
            if (!el) {
                return;
            }
            var color = colors[key];
            if (color) {
                el.style.color = color;
            }
        });
    }

    function getEcommerceDefaultGrouping(filters) {
        var presetEl = $('ecommerceReportsDatePreset') || (cfg.dashboardChartsOnly ? $('ecomDashDatePreset') : null);
        var preset = presetEl ? presetEl.value : (filters && filters.date_preset) || 'this_month';
        if (preset === 'this_month' || preset === 'last_month') {
            return 'weekly';
        }
        if (preset === 'today' || preset === 'yesterday' || preset === 'this_week' || preset === 'last_week') {
            return 'daily';
        }
        if (filters && filters.from_date && filters.to_date) {
            var days = daysInRange(filters.from_date, filters.to_date);
            if (days <= 7) {
                return 'daily';
            }
            if (days <= 45) {
                return 'weekly';
            }
            return 'monthly';
        }
        return 'weekly';
    }

    function setEcommerceChartViewButton(label) {
        var el = $('ecommerceChartViewBtnText');
        if (el) {
            el.textContent = label;
        }
    }

    function setEcommerceChartGroupButton(grouping) {
        var el = $('ecommerceChartGroupBtnText');
        if (el) {
            el.textContent = getGroupingLabel(grouping);
        }
    }

    function setEcommerceChartView(view) {
        ecommerceChartState.view = view || 'bar';
        var labels = {
            bar: lang.barChart || 'Bar Chart',
            line: lang.lineChart || 'Line Chart'
        };
        setEcommerceChartViewButton(labels[ecommerceChartState.view] || labels.bar);
        setMenuActiveState('ecommerceChartViewMenu', 'data-chart-view', ecommerceChartState.view);

        var barPanel = $('ecommerceReportsChartBarPanel');
        var linePanel = $('ecommerceReportsChartLinePanel');
        var isBar = ecommerceChartState.view === 'bar';
        var isLine = ecommerceChartState.view === 'line';

        if (barPanel) {
            barPanel.classList.toggle('d-none', !isBar);
        }
        if (linePanel) {
            linePanel.classList.toggle('d-none', !isLine);
        }

        applyEcommerceSalesCharts();
    }

    function setEcommerceChartGrouping(grouping) {
        ecommerceChartState.grouping = grouping || 'weekly';
        var input = $('ecommerceReportsChartGrouping');
        if (!input && cfg.dashboardChartsOnly) {
            input = $('ecomDashChartGrouping');
        }
        if (input) {
            input.value = ecommerceChartState.grouping;
        }
        var dashGrouping = $('ecomDashChartGrouping');
        if (dashGrouping && dashGrouping !== input) {
            dashGrouping.value = ecommerceChartState.grouping;
        }
        setEcommerceChartGroupButton(ecommerceChartState.grouping);
        setMenuActiveState('ecommerceChartGroupMenu', 'data-chart-group', ecommerceChartState.grouping);
        loadData();
    }

    function initEcommerceChartState() {
        var input = $('ecommerceReportsChartGrouping');
        if (!input && cfg.dashboardChartsOnly) {
            input = $('ecomDashChartGrouping');
        }
        var grouping = (input && input.value) || (cfg.filters && cfg.filters.chart_grouping) || '';
        if (!grouping) {
            grouping = getEcommerceDefaultGrouping(cfg.filters || {});
        }
        ecommerceChartState.grouping = grouping;
        if (input) {
            input.value = grouping;
        }
        setEcommerceChartGroupButton(grouping);
        setMenuActiveState('ecommerceChartGroupMenu', 'data-chart-group', grouping);
        setEcommerceChartView('line');
    }

    function initEcommerceChartToggles() {
        function toggleDatasetFromSummary(item) {
            var chart = getActiveEcommerceFinancialChart();
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
            if (!meta.hidden) {
                item.classList.remove('is-inactive');
            } else {
                item.classList.add('is-inactive');
            }
        }

        var toggles = document.querySelectorAll('#ecommerceReportsSummaryMain .invoice-financial-summary-item.is-chart-toggle');
        toggles.forEach(function (item) {
            item.style.cursor = 'pointer';
            item.addEventListener('click', function () {
                toggleDatasetFromSummary(item);
            });
            item.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggleDatasetFromSummary(item);
                }
            });
        });
    }

    function getActiveEcommerceFinancialChart() {
        if (ecommerceChartState.view === 'bar') {
            return ecommerceSalesBarChart;
        }
        return ecommerceSalesLineChart;
    }

    function updateEcommerceBarSummary(salesLine, salesComparison) {
        var completeEl = $('ecommerceReportsBarSummaryComplete');
        var processingEl = $('ecommerceReportsBarSummaryProcessing');
        var cancelEl = $('ecommerceReportsBarSummaryCancel');
        var arrowEl = $('ecommerceReportsBarSummaryArrow');
        var pctEl = $('ecommerceReportsBarSummaryPct');
        var sepEl = $('ecommerceReportsBarSummarySep');
        var vsEl = $('ecommerceReportsBarSummaryVs');
        if (!completeEl || !processingEl || !cancelEl || !salesLine) {
            return;
        }
        syncEcommerceCurrencyContext(salesLine);
        var symbol = salesLine.currency_symbol || ecommerceCurrencySymbol || '$';
        var currencyCode = salesLine.currency_code || ecommerceCurrencyCode || '';
        completeEl.textContent = formatMoneyAmount(salesLine.complete_total, symbol, currencyCode);
        processingEl.textContent = formatMoneyAmount(salesLine.processing_total, symbol, currencyCode);
        cancelEl.textContent = formatMoneyAmount(salesLine.cancel_total, symbol, currencyCode);

        if (!salesComparison) {
            if (vsEl) {
                vsEl.textContent = '';
            }
            if (sepEl) {
                sepEl.style.display = 'none';
            }
            if (arrowEl) {
                arrowEl.innerHTML = '';
                arrowEl.className = 'task-reports-bar-summary-arrow';
            }
            if (pctEl) {
                pctEl.textContent = '';
                pctEl.className = 'task-reports-bar-summary-pct';
            }
            return;
        }

        var current = salesLine.complete_total || 0;
        var previous = salesComparison.complete_total || 0;
        var changePct = calcPercentChange(current, previous);
        var direction = 'flat';
        if (changePct > 0) {
            direction = 'up';
        } else if (changePct < 0) {
            direction = 'down';
        }
        renderSummaryComparison(arrowEl, pctEl, sepEl, vsEl, {
            label_key: salesComparison.label_key,
            metrics: {
                complete_total: { percent: changePct, direction: direction }
            }
        }, 'complete_total');
    }

    function renderEcommerceSalesBarChart(salesLine) {
        if (!isEcommerce || typeof Chart === 'undefined') {
            return;
        }
        var canvas = $('ecommerceSalesBarChart');
        if (!canvas || !salesLine) {
            return;
        }

        installRoundedBarDraw();
        var axisColors = getReportsChartAxisColors();
        var financialColors = getEcommerceFinancialSeriesColors();
        syncEcommerceCurrencyContext(salesLine);
        var symbol = salesLine.currency_symbol || ecommerceCurrencySymbol || '$';
        var currencyCode = salesLine.currency_code || ecommerceCurrencyCode || '';
        var grouping = ecommerceChartState.grouping || 'weekly';
        var chartLabels = salesLine.labels || [];
        var labelCount = chartLabels.length;
        var barSize = getReportsBarSizeOptions(labelCount);
        var datasetDefs = [
            {
                label: lang.complete || 'Complete',
                data: salesLine.complete || [],
                backgroundColor: financialColors.complete,
                borderColor: financialColors.complete,
                stack: 'financial'
            },
            {
                label: lang.processing || 'Processing',
                data: salesLine.processing || [],
                backgroundColor: financialColors.processing,
                borderColor: financialColors.processing,
                stack: 'financial'
            },
            {
                label: lang.cancel || 'Cancel',
                data: salesLine.cancel || [],
                backgroundColor: financialColors.cancel,
                borderColor: financialColors.cancel,
                stack: 'financial'
            }
        ];
        var datasets = datasetDefs.map(function (def) {
            return {
                label: def.label,
                data: def.data,
                backgroundColor: def.backgroundColor,
                borderColor: def.borderColor,
                borderWidth: 0,
                stack: def.stack,
                barPercentage: barSize.barPercentage,
                categoryPercentage: barSize.categoryPercentage
            };
        });

        if (ecommerceSalesBarChart) {
            ecommerceSalesBarChart.data.labels = chartLabels;
            ecommerceSalesBarChart.data.datasets[0].data = salesLine.complete || [];
            ecommerceSalesBarChart.data.datasets[1].data = salesLine.processing || [];
            if (ecommerceSalesBarChart.data.datasets[2]) {
                ecommerceSalesBarChart.data.datasets[2].data = salesLine.cancel || [];
            }
            ecommerceSalesBarChart.update();
            patchBarChartResize(ecommerceSalesBarChart);
            ecommerceSalesBarChart.resize();
            applyBarChartInlineHeight(canvas);
            return;
        }

        ecommerceSalesBarChart = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { display: false },
                layout: {
                    padding: {
                        left: 4,
                        right: 4,
                        top: 4,
                        bottom: 0
                    }
                },
                scales: {
                    xAxes: [getReportsInvoiceStackedBarXAxisOptions(grouping, labelCount)],
                    yAxes: [{
                        stacked: true,
                        gridLines: getReportsChartYGridLineOptions(axisColors),
                        ticks: Object.assign({
                            beginAtZero: true,
                            min: 0,
                            maxTicksLimit: 6,
                            callback: function (v) {
                                return isEcommerce ? formatEcommerceMoneyAmount(v, symbol, currencyCode) : (symbol + v);
                            }
                        }, getAnalyticsChartYTickStyle())
                    }]
                },
                tooltips: getReportsFinancialBarTooltipOptions(symbol, currencyCode)
            }
        });
        patchBarChartResize(ecommerceSalesBarChart);
        ecommerceSalesBarChart.resize();
        applyBarChartInlineHeight(canvas);
    }

    function destroyEcommerceSalesLineChart() {
        if (ecommerceSalesLineChart) {
            ecommerceSalesLineChart.destroy();
            ecommerceSalesLineChart = null;
        }
    }

    function renderEcommerceSalesLineChart(salesLine) {
        if (!isEcommerce || typeof Chart === 'undefined') {
            return;
        }
        var canvas = $('ecommerceSalesLineChart');
        if (!canvas || !salesLine) {
            return;
        }
        var colors = getChartColors();
        var axisColors = getReportsChartAxisColors();
        var financialColors = getEcommerceFinancialSeriesColors();
        var completeColor = financialColors.complete;
        var processingColor = financialColors.processing;
        var cancelColor = financialColors.cancel;
        var cardBodyColor = colors.card;
        syncEcommerceCurrencyContext(salesLine);
        var symbol = salesLine.currency_symbol || ecommerceCurrencySymbol || '$';
        var currencyCode = salesLine.currency_code || ecommerceCurrencyCode || '';
        var chartLabels = salesLine.labels || [];
        var completeLabel = lang.complete || 'Complete';
        var processingLabel = lang.processing || 'Processing';
        var cancelLabel = lang.cancel || 'Cancel';

        if (ecommerceSalesLineChart) {
            ecommerceSalesLineChart.data.labels = chartLabels;
            ecommerceSalesLineChart.data.datasets[0].data = salesLine.complete || [];
            ecommerceSalesLineChart.data.datasets[1].data = salesLine.processing || [];
            if (ecommerceSalesLineChart.data.datasets[2]) {
                ecommerceSalesLineChart.data.datasets[2].data = salesLine.cancel || [];
            }
            ecommerceSalesLineChart.update();
            return;
        }

        destroyEcommerceSalesLineChart();
        var ctx = canvas.getContext('2d');
        if (!ctx) {
            return;
        }

        var chartHeight = canvas.height || canvas.offsetHeight || 300;
        ecommerceSalesLineChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [
                    {
                        label: completeLabel,
                        data: salesLine.complete || [],
                        borderColor: completeColor,
                        backgroundColor: createInvoiceSparkAreaGradient(ctx, completeColor, chartHeight),
                        pointBackgroundColor: cardBodyColor,
                        pointBorderColor: completeColor,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointHitRadius: 10,
                        pointBorderWidth: 1.5,
                        fill: true,
                        lineTension: ecommerceLineSmooth,
                        borderWidth: 1.5,
                        borderCapStyle: 'round',
                        borderJoinStyle: 'round'
                    },
                    {
                        label: processingLabel,
                        data: salesLine.processing || [],
                        borderColor: processingColor,
                        backgroundColor: createInvoiceSparkAreaGradient(ctx, processingColor, chartHeight),
                        pointBackgroundColor: cardBodyColor,
                        pointBorderColor: processingColor,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointHitRadius: 10,
                        pointBorderWidth: 1.5,
                        fill: true,
                        lineTension: ecommerceLineSmooth,
                        borderWidth: 1.5,
                        borderCapStyle: 'round',
                        borderJoinStyle: 'round'
                    },
                    {
                        label: cancelLabel,
                        data: salesLine.cancel || [],
                        borderColor: cancelColor,
                        backgroundColor: createInvoiceSparkAreaGradient(ctx, cancelColor, chartHeight),
                        pointBackgroundColor: cardBodyColor,
                        pointBorderColor: cancelColor,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointHitRadius: 10,
                        pointBorderWidth: 1.5,
                        fill: false,
                        lineTension: ecommerceLineSmooth,
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
                        title: function (tooltipItems, data) {
                            if (!tooltipItems || !tooltipItems.length) {
                                return '';
                            }
                            return data.labels[tooltipItems[0].index] || '';
                        },
                        label: function (tooltipItem, data) {
                            var label = data.datasets[tooltipItem.datasetIndex].label || '';
                            var value = data.datasets[tooltipItem.datasetIndex].data[tooltipItem.index] || 0;
                            return label + ' ' + formatEcommerceMoneyAmount(value, symbol, currencyCode);
                        }
                    }
                },
                scales: {
                    yAxes: [{
                        ticks: Object.assign({
                            beginAtZero: true,
                            callback: function (value) {
                                return formatEcommerceMoneyAmount(value, symbol, currencyCode);
                            }
                        }, getAnalyticsChartYTickStyle()),
                        gridLines: getReportsChartYGridLineOptions(axisColors)
                    }],
                    xAxes: [{
                        ticks: Object.assign({}, getAnalyticsChartTickStyle(), { fontSize: 10 }),
                        gridLines: { display: false }
                    }]
                },
                elements: {
                    line: {
                        tension: ecommerceLineSmooth,
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
    }

    function applyEcommerceSalesCharts() {
        if (!isEcommerce || !ecommerceSalesLineData) {
            return;
        }
        updateEcommerceBarSummary(ecommerceSalesLineData, ecommerceSalesComparison);
        var renderCharts = function () {
            if (ecommerceChartState.view === 'bar') {
                renderEcommerceSalesBarChart(ecommerceSalesLineData);
            } else {
                renderEcommerceSalesLineChart(ecommerceSalesLineData);
            }
        };
        if (typeof requestAnimationFrame === 'function') {
            requestAnimationFrame(renderCharts);
        } else {
            renderCharts();
        }
    }

    function getProjectDonutColors() {
        var colors = getChartColors();
        return {
            active: colors.primary,
            completed: getProjectLegendColor('completed') || '#28a745',
            overdue: getProjectLegendColor('overdue') || '#dc3545'
        };
    }

    function applyProjectLegendColors() {
        var donutColors = getProjectDonutColors();
        var map = {
            active: 'legendActive',
            completed: 'legendCompleted',
            overdue: 'legendOverdue'
        };
        Object.keys(map).forEach(function (key) {
            var el = $(map[key]);
            if (!el) {
                return;
            }
            var color = donutColors[key];
            if (color) {
                el.style.color = color;
            }
        });
    }

    function hexToRgba(hex, alpha) {
        var c = String(hex || '').trim();
        var rgbMatch = c.match(/^rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)/i);
        if (rgbMatch) {
            return 'rgba(' + rgbMatch[1] + ',' + rgbMatch[2] + ',' + rgbMatch[3] + ',' + alpha + ')';
        }
        c = c.replace('#', '');
        if (c.length === 3) {
            c = c.split('').map(function (h) { return h + h; }).join('');
        }
        var r = parseInt(c.substring(0, 2), 16);
        var g = parseInt(c.substring(2, 4), 16);
        var b = parseInt(c.substring(4, 6), 16);
        if (isNaN(r) || isNaN(g) || isNaN(b)) {
            return 'rgba(0,0,0,' + alpha + ')';
        }
        return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
    }

    function syncEcommerceCurrencyContext(source) {
        if (!source) {
            return;
        }
        if (source.currency_code) {
            ecommerceCurrencyCode = String(source.currency_code);
        }
        if (source.currency_symbol) {
            ecommerceCurrencySymbol = String(source.currency_symbol);
        }
    }

    function prefersLacCrCurrency(currencyCode) {
        var code = String(currencyCode || ecommerceCurrencyCode || '').toUpperCase();
        return code === 'PKR' || code === 'INR';
    }

    function compactDecimalLabel(value, maxDecimals) {
        var n = Number(value);
        if (!isFinite(n)) {
            n = 0;
        }
        return String(n.toFixed(maxDecimals)).replace(/\.?0+$/, '');
    }

    function ecommerceDisplaySymbol(currencyCode, symbol) {
        var code = String(currencyCode || ecommerceCurrencyCode || '').toUpperCase();
        var map = {
            PKR: 'Rs',
            INR: '₹',
            USD: '$',
            EUR: '€',
            GBP: '£',
            CAD: 'C$',
            AUD: 'A$'
        };
        if (map[code]) {
            return map[code];
        }
        var sym = String(symbol || ecommerceCurrencySymbol || '$').replace(/:$/, '').trim();
        return sym || '$';
    }

    function ecommerceAmountPrefix(currencyCode, symbol) {
        var code = String(currencyCode || ecommerceCurrencyCode || '').toUpperCase();
        var displaySymbol = ecommerceDisplaySymbol(currencyCode, symbol);
        if (code === 'PKR') {
            return displaySymbol + ' ';
        }
        return displaySymbol;
    }

    function formatEcommerceMoneyAmount(amount, symbol, currencyCode) {
        var code = String(currencyCode || ecommerceCurrencyCode || '').toUpperCase();
        var prefix = ecommerceAmountPrefix(code, symbol);
        var val = Math.max(0, parseFloat(amount) || 0);

        if (prefersLacCrCurrency(code)) {
            if (val >= 10000000) {
                return prefix + compactDecimalLabel(val / 10000000, 2) + ' Cr';
            }
            if (val >= 100000) {
                return prefix + compactDecimalLabel(val / 100000, 2) + ' Lac';
            }
            if (val >= 10000) {
                return prefix + compactDecimalLabel(val / 1000, 1) + 'K';
            }
            return prefix + val.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        }

        if (val >= 1000000000000) {
            return prefix + compactDecimalLabel(val / 1000000000000, 2) + 'T';
        }
        if (val >= 1000000000) {
            return prefix + compactDecimalLabel(val / 1000000000, 2) + 'B';
        }
        if (val >= 1000000) {
            return prefix + compactDecimalLabel(val / 1000000, 2) + 'M';
        }
        if (val >= 10000) {
            return prefix + compactDecimalLabel(val / 1000, 1) + 'K';
        }
        return prefix + val.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }

    function formatMoneyAmount(amount, symbol, currencyCode) {
        if (isEcommerce) {
            return formatEcommerceMoneyAmount(amount, symbol, currencyCode);
        }
        var sym = symbol || '$';
        var val = parseFloat(amount) || 0;
        return sym + val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function getInvoiceDefaultGrouping(filters) {
        var presetEl = $('invoiceReportsDatePreset');
        var preset = presetEl ? presetEl.value : (filters && filters.date_preset) || 'this_month';
        if (preset === 'this_month' || preset === 'last_month') {
            return 'weekly';
        }
        if (preset === 'today' || preset === 'yesterday' || preset === 'this_week' || preset === 'last_week') {
            return 'daily';
        }
        if (filters && filters.from_date && filters.to_date) {
            var days = daysInRange(filters.from_date, filters.to_date);
            if (days <= 7) {
                return 'daily';
            }
            if (days <= 45) {
                return 'weekly';
            }
            return 'monthly';
        }
        return 'weekly';
    }

    function setInvoiceChartViewButton(label) {
        var el = $('invoiceChartViewBtnText');
        if (el) {
            el.textContent = label;
        }
    }

    function setInvoiceChartGroupButton(grouping) {
        var el = $('invoiceChartGroupBtnText');
        if (el) {
            el.textContent = getGroupingLabel(grouping);
        }
    }

    function setInvoiceChartView(view) {
        invoiceChartState.view = view || 'line';
        var labels = {
            bar: lang.barChart || 'Bar Chart',
            line: lang.lineChart || 'Line Chart'
        };
        setInvoiceChartViewButton(labels[invoiceChartState.view] || labels.line);
        setMenuActiveState('invoiceChartViewMenu', 'data-chart-view', invoiceChartState.view);

        var barPanel = $('invoiceReportsChartBarPanel');
        var linePanel = $('invoiceReportsChartLinePanel');
        var isBar = invoiceChartState.view === 'bar';
        var isLine = invoiceChartState.view === 'line';

        if (barPanel) {
            barPanel.classList.toggle('d-none', !isBar);
        }
        if (linePanel) {
            linePanel.classList.toggle('d-none', !isLine);
        }

        applyInvoiceSalesCharts();
    }

    function setInvoiceChartGrouping(grouping) {
        invoiceChartState.grouping = grouping || 'weekly';
        var input = $('invoiceReportsChartGrouping');
        if (input) {
            input.value = invoiceChartState.grouping;
        }
        setInvoiceChartGroupButton(invoiceChartState.grouping);
        setMenuActiveState('invoiceChartGroupMenu', 'data-chart-group', invoiceChartState.grouping);
        loadData();
    }

    function initInvoiceChartState() {
        var input = $('invoiceReportsChartGrouping');
        var grouping = (input && input.value) || (cfg.filters && cfg.filters.chart_grouping) || '';
        if (!grouping) {
            grouping = getInvoiceDefaultGrouping(cfg.filters || {});
        }
        invoiceChartState.grouping = grouping;
        if (input) {
            input.value = grouping;
        }
        setInvoiceChartGroupButton(grouping);
        setMenuActiveState('invoiceChartGroupMenu', 'data-chart-group', grouping);
        setInvoiceChartView('line');
    }

    function createInvoiceSparkAreaGradient(chartCtx, color, height) {
        var gradient = chartCtx.createLinearGradient(0, 0, 0, height || chartCtx.canvas.height);
        gradient.addColorStop(0, hexToRgba(color, 0.35));
        gradient.addColorStop(1, hexToRgba(color, 0));
        return gradient;
    }

    function getActiveInvoiceFinancialChart() {
        if (invoiceChartState.view === 'bar') {
            return invoiceSalesBarChart;
        }
        return invoiceSalesLineChart;
    }

    function initInvoiceChartToggles() {
        function toggleDatasetFromSummary(item) {
            var chart = getActiveInvoiceFinancialChart();
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
            if (!meta.hidden) {
                item.classList.remove('is-inactive');
            } else {
                item.classList.add('is-inactive');
            }
        }

        var toggles = document.querySelectorAll('#invoiceReportsSummaryMain .invoice-financial-summary-item.is-chart-toggle');
        toggles.forEach(function (item) {
            item.style.cursor = 'pointer';
            item.addEventListener('click', function () {
                toggleDatasetFromSummary(item);
            });
            item.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggleDatasetFromSummary(item);
                }
            });
        });
    }

    function updateInvoiceBarSummary(salesLine, salesComparison) {
        var paidEl = $('invoiceReportsBarSummaryPaid');
        var unpaidEl = $('invoiceReportsBarSummaryUnpaid');
        var taxEl = $('invoiceReportsBarSummaryTax');
        var cancelledEl = $('invoiceReportsBarSummaryCancelled');
        var arrowEl = $('invoiceReportsBarSummaryArrow');
        var pctEl = $('invoiceReportsBarSummaryPct');
        var sepEl = $('invoiceReportsBarSummarySep');
        var vsEl = $('invoiceReportsBarSummaryVs');
        if (!paidEl || !unpaidEl || !cancelledEl || !salesLine) {
            return;
        }
        var symbol = salesLine.currency_symbol || '$';
        paidEl.textContent = formatMoneyAmount(salesLine.paid_total, symbol);
        unpaidEl.textContent = formatMoneyAmount(salesLine.unpaid_total, symbol);
        if (taxEl) {
            taxEl.textContent = formatMoneyAmount(salesLine.sales_tax_total, symbol);
        }
        cancelledEl.textContent = formatMoneyAmount(salesLine.cancelled_total, symbol);

        var paidLabel = document.querySelector('#invoiceReportsSummaryMain .invoice-financial-summary-label--paid');
        var unpaidLabel = document.querySelector('#invoiceReportsSummaryMain .invoice-financial-summary-label--unpaid');
        var taxLabel = document.querySelector('#invoiceReportsSummaryMain .invoice-financial-summary-label--tax');
        var cancelledLabel = document.querySelector('#invoiceReportsSummaryMain .invoice-financial-summary-label--cancelled');
        if (paidLabel) {
            paidLabel.style.color = '#22c55e';
        }
        if (unpaidLabel) {
            unpaidLabel.style.color = '#ef4444';
        }
        if (taxLabel) {
            taxLabel.style.color = '#fd7e14';
        }
        if (cancelledLabel) {
            cancelledLabel.style.color = '#adb5bd';
        }

        if (!salesComparison) {
            if (vsEl) {
                vsEl.textContent = '';
            }
            if (sepEl) {
                sepEl.style.display = 'none';
            }
            if (arrowEl) {
                arrowEl.innerHTML = '';
                arrowEl.className = 'task-reports-bar-summary-arrow';
            }
            if (pctEl) {
                pctEl.textContent = '';
                pctEl.className = 'task-reports-bar-summary-pct';
            }
            return;
        }

        var current = salesLine.paid_total || 0;
        var previous = salesComparison.paid_total || 0;
        var changePct = calcPercentChange(current, previous);
        var direction = 'flat';
        if (changePct > 0) {
            direction = 'up';
        } else if (changePct < 0) {
            direction = 'down';
        }
        renderSummaryComparison(arrowEl, pctEl, sepEl, vsEl, {
            label_key: salesComparison.label_key,
            metrics: {
                paid_total: { percent: changePct, direction: direction }
            }
        }, 'paid_total');
    }

    function destroyInvoiceSalesLineChart() {
        if (invoiceSalesLineChart) {
            invoiceSalesLineChart.destroy();
            invoiceSalesLineChart = null;
        }
    }

    function applyInvoiceSalesCharts() {
        if (!isInvoice || !invoiceSalesLineData) {
            return;
        }
        updateInvoiceBarSummary(invoiceSalesLineData, invoiceSalesComparison);
        var renderCharts = function () {
            if (invoiceChartState.view === 'bar') {
                renderInvoiceSalesBarChart(invoiceSalesLineData);
            } else {
                renderSalesLineChart(invoiceSalesLineData);
            }
        };
        if (typeof requestAnimationFrame === 'function') {
            requestAnimationFrame(renderCharts);
        } else {
            renderCharts();
        }
    }

    function renderInvoiceSalesBarChart(salesLine) {
        if (!isInvoice || typeof Chart === 'undefined') {
            return;
        }
        var canvas = $('invoiceSalesBarChart');
        if (!canvas || !salesLine) {
            return;
        }

        installRoundedBarDraw();
        var axisColors = getReportsChartAxisColors();
        var financialColors = getInvoiceFinancialSeriesColors();
        var paidColor = financialColors.paid;
        var unpaidColor = financialColors.unpaid;
        var cancelledColor = financialColors.cancelled;
        var symbol = salesLine.currency_symbol || '$';
        var grouping = invoiceChartState.grouping || 'weekly';
        var chartLabels = salesLine.labels || [];
        var labelCount = chartLabels.length;
        var barSize = getReportsBarSizeOptions(labelCount);
        var datasetDefs = [
            {
                label: lang.paid || lang.earnings || 'Paid',
                data: salesLine.paid || [],
                backgroundColor: paidColor,
                borderColor: paidColor,
                stack: 'financial'
            },
            {
                label: lang.unpaid || 'Unpaid',
                data: salesLine.unpaid || [],
                backgroundColor: unpaidColor,
                borderColor: unpaidColor,
                stack: 'financial'
            },
            {
                label: lang.cancelled || 'Cancel',
                data: salesLine.cancelled || [],
                backgroundColor: cancelledColor,
                borderColor: cancelledColor,
                stack: 'financial'
            }
        ];
        var datasets = datasetDefs.map(function (def) {
            return {
                label: def.label,
                data: def.data,
                backgroundColor: def.backgroundColor,
                borderColor: def.borderColor,
                borderWidth: 0,
                stack: def.stack,
                barPercentage: barSize.barPercentage,
                categoryPercentage: barSize.categoryPercentage
            };
        });

        if (invoiceSalesBarChart) {
            invoiceSalesBarChart.data.labels = chartLabels;
            invoiceSalesBarChart.data.datasets[0].data = salesLine.paid || [];
            invoiceSalesBarChart.data.datasets[1].data = salesLine.unpaid || [];
            if (invoiceSalesBarChart.data.datasets[2]) {
                invoiceSalesBarChart.data.datasets[2].data = salesLine.cancelled || [];
            }
            invoiceSalesBarChart.update();
            patchBarChartResize(invoiceSalesBarChart);
            invoiceSalesBarChart.resize();
            applyBarChartInlineHeight(canvas);
            return;
        }

        invoiceSalesBarChart = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { display: false },
                layout: {
                    padding: {
                        left: 4,
                        right: 4,
                        top: 4,
                        bottom: 0
                    }
                },
                scales: {
                    xAxes: [getReportsInvoiceStackedBarXAxisOptions(grouping, labelCount)],
                    yAxes: [{
                        stacked: true,
                        gridLines: getReportsChartYGridLineOptions(axisColors),
                        ticks: Object.assign({
                            beginAtZero: true,
                            min: 0,
                            maxTicksLimit: 6,
                            callback: function (v) {
                                return formatMoneyAmount(v, symbol);
                            }
                        }, getAnalyticsChartYTickStyle())
                    }]
                },
                tooltips: getReportsFinancialBarTooltipOptions(symbol)
            }
        });
        patchBarChartResize(invoiceSalesBarChart);
        invoiceSalesBarChart.resize();
        applyBarChartInlineHeight(canvas);
    }

    function renderSalesLineChart(salesLine) {
        if (!isInvoice || typeof Chart === 'undefined') {
            return;
        }
        var canvas = $('invoiceSalesLineChart');
        if (!canvas || !salesLine) {
            return;
        }
        var colors = getChartColors();
        var axisColors = getReportsChartAxisColors();
        var financialColors = getInvoiceFinancialSeriesColors();
        var paidColor = financialColors.paid;
        var unpaidColor = financialColors.unpaid;
        var cancelledColor = financialColors.cancelled;
        var cardBodyColor = colors.card;
        var symbol = salesLine.currency_symbol || '$';
        var chartLabels = salesLine.labels || [];
        var paidLabel = lang.paid || lang.earnings || 'Paid';
        var unpaidLabel = lang.unpaid || 'Unpaid';
        var cancelledLabel = lang.cancelled || 'Cancel';

        if (invoiceSalesLineChart) {
            invoiceSalesLineChart.data.labels = chartLabels;
            invoiceSalesLineChart.data.datasets[0].data = salesLine.paid || [];
            invoiceSalesLineChart.data.datasets[1].data = salesLine.unpaid || [];
            if (invoiceSalesLineChart.data.datasets[2]) {
                invoiceSalesLineChart.data.datasets[2].data = salesLine.cancelled || [];
            }
            invoiceSalesLineChart.update();
            return;
        }

        destroyInvoiceSalesLineChart();
        var ctx = canvas.getContext('2d');
        if (!ctx) {
            return;
        }

        var chartHeight = canvas.height || canvas.offsetHeight || 300;
        invoiceSalesLineChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [
                    {
                        label: paidLabel,
                        data: salesLine.paid || [],
                        borderColor: paidColor,
                        backgroundColor: createInvoiceSparkAreaGradient(ctx, paidColor, chartHeight),
                        pointBackgroundColor: cardBodyColor,
                        pointBorderColor: paidColor,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointHitRadius: 10,
                        pointBorderWidth: 1.5,
                        fill: true,
                        lineTension: invoiceLineSmooth,
                        borderWidth: 1.5,
                        borderCapStyle: 'round',
                        borderJoinStyle: 'round'
                    },
                    {
                        label: unpaidLabel,
                        data: salesLine.unpaid || [],
                        borderColor: unpaidColor,
                        backgroundColor: createInvoiceSparkAreaGradient(ctx, unpaidColor, chartHeight),
                        pointBackgroundColor: cardBodyColor,
                        pointBorderColor: unpaidColor,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointHitRadius: 10,
                        pointBorderWidth: 1.5,
                        fill: true,
                        lineTension: invoiceLineSmooth,
                        borderWidth: 1.5,
                        borderCapStyle: 'round',
                        borderJoinStyle: 'round'
                    },
                    {
                        label: cancelledLabel,
                        data: salesLine.cancelled || [],
                        borderColor: cancelledColor,
                        backgroundColor: createInvoiceSparkAreaGradient(ctx, cancelledColor, chartHeight),
                        pointBackgroundColor: cardBodyColor,
                        pointBorderColor: cancelledColor,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointHitRadius: 10,
                        pointBorderWidth: 1.5,
                        fill: false,
                        lineTension: invoiceLineSmooth,
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
                        title: function (tooltipItems, data) {
                            if (!tooltipItems || !tooltipItems.length) {
                                return '';
                            }
                            return data.labels[tooltipItems[0].index] || '';
                        },
                        label: function (tooltipItem, data) {
                            var label = data.datasets[tooltipItem.datasetIndex].label || '';
                            var value = data.datasets[tooltipItem.datasetIndex].data[tooltipItem.index] || 0;
                            return label + ' ' + formatMoneyAmount(value, symbol);
                        }
                    }
                },
                scales: {
                    yAxes: [{
                        ticks: Object.assign({
                            beginAtZero: true,
                            callback: function (value) {
                                return formatMoneyAmount(value, symbol);
                            }
                        }, getAnalyticsChartYTickStyle()),
                        gridLines: getReportsChartYGridLineOptions(axisColors)
                    }],
                    xAxes: [{
                        ticks: Object.assign({}, getAnalyticsChartTickStyle(), { fontSize: 10 }),
                        gridLines: { display: false }
                    }]
                },
                elements: {
                    line: {
                        tension: invoiceLineSmooth,
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
    }

    function getKanbanStatusColor(statusKey, cssProp) {
        var prop = cssProp || 'backgroundColor';
        var refId = prop === 'color' ? 'taskReportsKanbanText-' + statusKey : 'taskReportsKanbanColor-' + statusKey;
        var el = $(refId);
        if (el) {
            var val = getComputedStyle(el)[prop];
            if (val && val !== 'rgba(0, 0, 0, 0)' && val !== 'transparent') {
                return val.trim();
            }
        }
        var fallbacks = {
            todo: { backgroundColor: '#dc3545', color: '#dc3545' },
            inprogress: { backgroundColor: '#17a2b8', color: '#17a2b8' },
            review: { backgroundColor: '#f2711c', color: '#f2711c' },
            done: { backgroundColor: '#28a745', color: '#28a745' }
        };
        var fb = fallbacks[statusKey];
        if (!fb) {
            return '';
        }
        return fb[prop] || fb.backgroundColor;
    }

    function updateDonut(donut) {
        if (typeof Chart === 'undefined') {
            return;
        }
        var canvas = $(isEcommerce ? 'ecommerceStatusDonutChart' : (isInvoice ? 'invoiceStatusDonutChart' : (isProject ? 'projectStatusDonutChart' : 'taskStatusPieChart')));
        if (!canvas) {
            return;
        }
        var colors = getChartColors();
        var total;
        var seg1;
        var completed;
        var seg3;
        var seg4 = 0;
        if (isEcommerce) {
            var complete = donut && donut.complete != null ? donut.complete : 0;
            var processing = donut && donut.processing != null ? donut.processing : 0;
            var pending = donut && donut.pending != null ? donut.pending : 0;
            var cancel = donut && donut.cancel != null ? donut.cancel : 0;
            var segmentTotal = complete + processing + pending + cancel;
            total = segmentTotal > 0 ? segmentTotal : (donut && donut.total != null ? donut.total : 0);
            seg1 = complete;
            completed = processing;
            seg3 = pending;
            seg4 = cancel;
        } else {
            total = donut && donut.total != null ? donut.total : 0;
            if (isInvoice) {
                seg1 = donut && donut.paid != null ? donut.paid : 0;
                completed = donut && donut.unpaid != null ? donut.unpaid : 0;
                seg3 = donut && donut.overdue != null ? donut.overdue : 0;
                seg4 = donut && donut.cancelled != null ? donut.cancelled : 0;
            } else {
                seg1 = isProject ? (donut && donut.active != null ? donut.active : 0) : (donut && donut.open != null ? donut.open : 0);
                completed = donut && donut.completed != null ? donut.completed : 0;
                seg3 = isProject ? (donut && donut.overdue != null ? donut.overdue : 0) : (donut && donut.incomplete != null ? donut.incomplete : 0);
            }
        }

        if ($(isEcommerce ? 'ecommerceDonutTotal' : (isInvoice ? 'invoiceDonutTotal' : (isProject ? 'projectDonutTotal' : 'taskDonutTotal')))) {
            $(isEcommerce ? 'ecommerceDonutTotal' : (isInvoice ? 'invoiceDonutTotal' : (isProject ? 'projectDonutTotal' : 'taskDonutTotal'))).textContent = total;
        }
        if (isEcommerce) {
            if ($('legendComplete')) {
                $('legendComplete').textContent = seg1;
            }
            if ($('legendProcessing')) {
                $('legendProcessing').textContent = completed;
            }
            if ($('legendPending')) {
                $('legendPending').textContent = seg3;
            }
            if ($('legendCancel')) {
                $('legendCancel').textContent = seg4;
            }
            applyEcommerceLegendColors();
        } else if (isInvoice) {
            if ($('legendPaid')) {
                $('legendPaid').textContent = seg1;
            }
            if ($('legendUnpaid')) {
                $('legendUnpaid').textContent = completed;
            }
            if ($('legendOverdueInv')) {
                $('legendOverdueInv').textContent = seg3;
            }
            if ($('legendCancelled')) {
                $('legendCancelled').textContent = seg4;
            }
            applyInvoiceLegendColors();
        } else {
            if ($(isProject ? 'legendActive' : 'legendOpen')) {
                $(isProject ? 'legendActive' : 'legendOpen').textContent = seg1;
            }
            if ($('legendCompleted')) {
                $('legendCompleted').textContent = completed;
            }
            if ($(isProject ? 'legendOverdue' : 'legendIncomplete')) {
                $(isProject ? 'legendOverdue' : 'legendIncomplete').textContent = seg3;
            }
            if (isProject) {
                applyProjectLegendColors();
            }
        }

        var data, labels, bg;
        if (total === 0) {
            labels = [lang.noData || 'No data'];
            data = [1];
            bg = ['#e9ecef'];
        } else if (isEcommerce) {
            labels = [
                lang.complete || 'Complete',
                lang.processing || 'Processing',
                lang.pending || 'Pending',
                lang.cancel || 'Cancel'
            ];
            data = [seg1, completed, seg3, seg4];
            var ecomColors = getEcommerceFinancialSeriesColors();
            bg = [ecomColors.complete, ecomColors.processing, ecomColors.pending, ecomColors.cancel];
        } else if (isInvoice) {
            labels = [
                lang.paid || 'Paid',
                lang.unpaid || 'Unpaid',
                lang.overdue || 'Overdue',
                lang.cancelled || 'Cancel'
            ];
            data = [seg1, completed, seg3, seg4];
            var donutColors = getInvoiceDonutColors();
            bg = [donutColors.paid, donutColors.unpaid, donutColors.overdue, donutColors.cancelled];
        } else if (isProject) {
            var projectColors = getProjectDonutColors();
            labels = [lang.open || 'Active', lang.completed || 'Completed', lang.incomplete || 'Overdue'];
            data = [seg1, completed, seg3];
            bg = [projectColors.active, projectColors.completed, projectColors.overdue];
        } else {
            labels = [lang.open || 'Open', lang.completed || 'Completed', lang.incomplete || 'Incomplete'];
            data = [seg1, completed, seg3];
            var todoColor = getKanbanStatusColor('todo', 'backgroundColor') || '#dc3545';
            var doneColor = getKanbanStatusColor('done', 'backgroundColor') || '#4caf50';
            bg = [colors.primary, doneColor, todoColor];
        }

        var useRoundedSegments = total > 0;
        installComonRoundedDonutPlugin();

        if (donutChart) {
            donutChart.destroy();
        }
        var donutOptions = {
            cutoutPercentage: 77,
            responsive: true,
            maintainAspectRatio: false,
            legend: { display: false },
            comonRoundedDoughnut: useRoundedSegments,
            comonRoundedDoughnutGap: 2,
            comonRoundedDoughnutCapScale: 0.5,
            tooltips: { enabled: total > 0 }
        };
        if (isEcommerce && total > 0) {
            donutOptions.tooltips.callbacks = {
                label: function (tooltipItem, chartData) {
                    var value = chartData.datasets[0].data[tooltipItem.index];
                    var pct = total > 0 ? Math.round((value / total) * 1000) / 10 : 0;
                    return chartData.labels[tooltipItem.index] + ': ' + value + ' (' + pct + '%)';
                }
            };
        }
        donutChart = new Chart(canvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: bg,
                    hoverBackgroundColor: bg,
                    borderColor: colors.card,
                    hoverBorderColor: colors.card,
                    borderWidth: useRoundedSegments ? 0 : 1
                }]
            },
            options: donutOptions
        });
    }

    function updateBarFromResponse(bar, filters, comparison) {
        barRaw = bar && typeof bar === 'object' ? bar : { entries: [], staff: [] };
        if (!barRaw.entries) {
            barRaw.entries = [];
        }
        if (!barRaw.staff) {
            barRaw.staff = [];
        }
        barComparison = comparison && comparison.entries ? comparison : null;
        lastFilters = filters || {};

        populateChartUserMenu(barRaw.staff);

        chartBarState.grouping = getDefaultGrouping(lastFilters);
        setChartGroupButton(chartBarState.grouping);
        setMenuActiveState((isProject ? 'projectBarChartGroupMenu' : 'taskBarChartGroupMenu'), 'data-chart-group', chartBarState.grouping);

        renderBarChart();
        updateBarSummary();
    }

    function buildMarketingCampaignCell(row) {
        var name = escapeHtml(row.name || '');
        return '<td class="marketing-dashboard-col-campaign extra-height">' +
            '<div class="tbl-ttl min-width-250">' + name + '</div></td>';
    }

    function buildMarketingActionCell(row) {
        var dropdownId = 'actionDropdownMarketing' + row.id;
        var reportUrl = row.view_tasks_url || ('marketing/report.php?id=' + row.id);
        var editUrl = row.view_profile_url || ('marketing/campaign-edit?id=' + row.id);
        var actionLabel = lang.options || lang.action || 'Options';
        var arrowSvg = reportsTsIcon('chevron-down');

        return '<td class="extra-height">' +
            '<div class="action-toggle" data-bs-toggle="collapse" data-bs-target="#' + dropdownId + '">' +
            escapeHtml(actionLabel) + arrowSvg +
            '</div>' +
            '<div id="' + dropdownId + '" class="toggle-action collapse shadow-dept">' +
            '<ul>' +
            '<li><a href="' + escapeHtml(reportUrl) + '">' + reportsMenuIcon('info') + '<span>' + escapeHtml(lang.viewTasks || 'View Report') + '</span></a></li>' +
            '<li><a href="' + escapeHtml(editUrl) + '">' + reportsMenuIcon('edit') + '<span>' + escapeHtml(lang.viewProfile || 'Edit Campaign') + '</span></a></li>' +
            '</ul></div></td>';
    }

    function buildStaffUserCell(row) {
        var profileUrl = row.view_profile_url || ('profile.php?user_id=' + row.id);
        var avatarHtml = row.avatar_html || '';
        return '<td class="clients-rpt extra-height">' +
            '<div class="avatar-wrapper mt-0 d-flex align-items-center">' +
            '<a href="' + escapeHtml(profileUrl) + '" class="d-flex align-items-center" style="gap: 10px;">' +
            '<div class="user-box">' + avatarHtml + '</div>' +
            '<span class="avatar-name">' + escapeHtml(row.name || '') + '</span>' +
            '</a></div></td>';
    }

    function buildProjectNameCell(row) {
        var projectUrl = row.view_project_url || ('overview.php?projectId=' + row.id);
        return '<td class="project-reports-project-cell extra-height">' +
            '<div class="min-width-200">' +
            '<a href="' + escapeHtml(projectUrl) + '" class="avatar-name">' + escapeHtml(row.name || '') + '</a>' +
            '</div></td>';
    }

    function buildProjectClientCell(row) {
        var avatarHtml = row.avatar_html || '';
        return '<td class="clients-rpt extra-height">' +
            '<div class="avatar-wrapper mt-0 d-flex align-items-center">' +
            '<div class="user-box">' + avatarHtml + '</div>' +
            '<span class="avatar-name">' + escapeHtml(row.client_name || '') + '</span>' +
            '</div></td>';
    }

    function buildProjectActionCell(row) {
        var dropdownId = 'actionDropdownProject' + row.id;
        var projectUrl = row.view_project_url || ('overview.php?projectId=' + row.id);
        var actionLabel = lang.action || 'Action';
        var arrowSvg = reportsTsIcon('chevron-down');
        return '<td class="extra-height">' +
            '<div class="action-toggle" data-bs-toggle="collapse" data-bs-target="#' + dropdownId + '">' +
            escapeHtml(actionLabel) + arrowSvg +
            '</div>' +
            '<div id="' + dropdownId + '" class="toggle-action collapse shadow-dept">' +
            '<ul><li><a href="' + escapeHtml(projectUrl) + '" target="_blank" rel="noopener noreferrer">' +
            reportsMenuIcon('folder') + '<span>' + escapeHtml(lang.viewTasks || 'View Project') + '</span></a></li></ul></div></td>';
    }

    function buildStaffActionCell(row) {
        var uid = row.id;
        var dropdownId = 'actionDropdownReport' + uid;
        var tasksUrl = row.view_tasks_url || ('profile.php?user_id=' + uid + '&tab=tasks');
        var profileUrl = row.view_profile_url || ('profile.php?user_id=' + uid);
        var actionLabel = lang.action || 'Action';
        var arrowSvg = reportsTsIcon('chevron-down');

        return '<td class="extra-height">' +
            '<div class="action-toggle" data-bs-toggle="collapse" data-bs-target="#' + dropdownId + '">' +
            escapeHtml(actionLabel) + arrowSvg +
            '</div>' +
            '<div id="' + dropdownId + '" class="toggle-action collapse shadow-dept">' +
            '<ul>' +
            '<li><a href="' + escapeHtml(tasksUrl) + '" target="_blank" rel="noopener noreferrer">' + reportsMenuIcon('list') + '<span>' + escapeHtml(lang.viewTasks || 'View Tasks') + '</span></a></li>' +
            '<li><a href="' + escapeHtml(profileUrl) + '" target="_blank" rel="noopener noreferrer">' + reportsMenuIcon('profile') + '<span>' + escapeHtml(lang.viewProfile || 'View Profile') + '</span></a></li>' +
            '</ul></div></td>';
    }

    function getProgressBarColor(percent) {
        if (percent < 30) {
            return '#f66';
        }
        if (percent < 70) {
            return '#f9b233';
        }
        return '#4caf50';
    }

    function buildInvoiceNameCell(row) {
        var url = row.edit_url || row.view_url || '#';
        return '<td class="invoice-reports-invoice-cell extra-height"> <div class="min-width-200">' +
            '<a href="' + escapeHtml(url) + '" class="avatar-name">' + escapeHtml(row.title || '') + '</a>' +
            '</div></td>';
    }

    function buildInvoiceClientCell(row) {
        var avatarHtml = row.avatar_html || '';
        return '<td class="clients-rpt extra-height">' +
            '<div class="avatar-wrapper mt-0 d-flex align-items-center">' +
            '<div class="user-box">' + avatarHtml + '</div>' +
            '<span class="avatar-name">' + escapeHtml(row.client_name || '') + '</span>' +
            '</div></td>';
    }

    function buildInvoiceStatusCell(row) {
        var isPaid = row.status === 'paid' || row.status === 1 || row.status === '1';
        var isCancelled = row.status === 'cancelled' || row.status === 2 || row.status === '2';
        var cls = isPaid ? 'badge-success' : (isCancelled ? 'badge-secondary' : 'badge-warning');
        var label = row.status_label || (isPaid ? (lang.paid || 'Paid') : (isCancelled ? (lang.cancelled || 'Cancel') : (lang.unpaid || 'Unpaid')));
        return '<td><span class="badge ' + cls + '">' + escapeHtml(label) + '</span></td>';
    }

    function buildEcommerceProductNameCell(row) {
        if (row.name_html) {
            return '<td class="min-width-200 ts-ecom-product-name-cell extra-height">' + row.name_html + '</td>';
        }
        var name = row.name || row.sku || '—';
        if (row.product_url) {
            return '<td class="min-width-200 ts-ecom-product-name-cell extra-height"><a href="' + escapeHtml(row.product_url) + '" class="ts-ecom-prod-name-link">' + escapeHtml(name) + '</a></td>';
        }
        return '<td class="min-width-200 ts-ecom-product-name-cell extra-height">' + escapeHtml(name) + '</td>';
    }

    function buildEcommerceProductActionCell(row) {
        if (row.action_html) {
            return row.action_html;
        }
        if (!row.product_url) {
            return '<td class="extra-height">—</td>';
        }
        var viewProductIcon = reportsMenuIcon('eye');
        var dropdownId = 'actionDropdownEcomProduct' + String(row.sku || '').replace(/[^a-zA-Z0-9]/g, '');
        var actionLabel = lang.action || 'Action';
        var arrowSvg = reportsTsIcon('chevron-down');
        return '<td class="extra-height">' +
            '<div class="action-toggle" data-bs-toggle="collapse" data-bs-target="#' + dropdownId + '">' +
            escapeHtml(actionLabel) + arrowSvg +
            '</div>' +
            '<div id="' + dropdownId + '" class="toggle-action collapse shadow-dept">' +
            '<ul><li><a href="' + escapeHtml(row.product_url) + '">' + viewProductIcon + '<span>' + escapeHtml(lang.viewProduct || 'View Product') + '</span></a></li></ul>' +
            '</div></td>';
    }

    function buildInvoiceActionCell(row) {
        var dropdownId = 'actionDropdownInvoice' + row.id;
        var editUrl = row.edit_url || row.view_url || '#';
        var actionLabel = lang.action || 'Action';
        var arrowSvg = reportsTsIcon('chevron-down');
        return '<td class="extra-height">' +
            '<div class="action-toggle" data-bs-toggle="collapse" data-bs-target="#' + dropdownId + '">' +
            escapeHtml(actionLabel) + arrowSvg +
            '</div>' +
            '<div id="' + dropdownId + '" class="toggle-action collapse shadow-dept">' +
            '<ul>' +
            '<li><a href="' + escapeHtml(editUrl) + '">' + reportsMenuIcon('invoice') + '<span>' + escapeHtml(lang.viewInvoice || 'View Invoice') + '</span></a></li>' +
            '<li><a href="' + escapeHtml(editUrl) + '">' + reportsMenuIcon('edit') + '<span>' + escapeHtml(lang.editInvoice || 'Edit Invoice') + '</span></a></li>' +
            '</ul></div></td>';
    }

    function buildCompletionCell(row) {
        var total = row.total_tasks != null ? parseInt(row.total_tasks, 10) : 0;
        var completed = isMarketing && row.delivery_done != null
            ? parseInt(row.delivery_done, 10)
            : (row.completed != null ? parseInt(row.completed, 10) : 0);
        var percent = row.completion_pct != null ? parseInt(row.completion_pct, 10) : 0;
        if (isNaN(percent)) {
            percent = 0;
        }
        if (percent < 0) {
            percent = 0;
        }
        if (percent > 100) {
            percent = 100;
        }
        var color = getProgressBarColor(percent);
        return '<td class="tbl-tasks extra-height">' +
            '<div class="mb-1 d-flex col-gap-5">' + completed + '/' + total +
            ' <span class="text-align-right flex-grow">' + percent + '%</span></div>' +
            '<div class="progress" style="height:8px;">' +
            '<div class="progress-bar" role="progressbar" style="width:' + percent + '%;background:' + color + ';" aria-valuenow="' + percent + '" aria-valuemin="0" aria-valuemax="100"></div>' +
            '</div></td>';
    }

    function updateTable(rows) {
        var tbody = $('projects-tbl');
        if (!tbody) {
            return;
        }
        var colSpan = isEcommerce ? 7 : (isInvoice ? 9 : (isProject ? 9 : 10));
        tbody.innerHTML = '';
        if (!rows || !rows.length) {
            var tr = document.createElement('tr');
            tr.innerHTML = '<td colspan="' + colSpan + '" class="text-center">' + (lang.noData || '') + '</td>';
            tbody.appendChild(tr);
            return;
        }
        rows.forEach(function (row, index) {
            var tr = document.createElement('tr');
            if (isEcommerce) {
                var itemsSold = row.items_sold != null ? row.items_sold : 0;
                tr.innerHTML =
                    '<td>' + (index + 1) + '</td>' +
                    buildEcommerceProductNameCell(row) +
                    '<td class="min-width-160 ts-ecom-product-sku-cell extra-height text-start">' + escapeHtml(row.sku || '—') + '</td>' +
                    '<td>' + itemsSold + '</td>' +
                    '<td>' + escapeHtml(row.revenue_formatted || row.revenue || '') + '</td>' +
                    '<td>' + (row.orders != null ? row.orders : 0) + '</td>' +
                    buildEcommerceProductActionCell(row);
            } else if (isInvoice) {
                tr.innerHTML =
                    '<td>' + (index + 1) + '</td>' +
                    buildInvoiceNameCell(row) +
                    buildInvoiceClientCell(row) +
                    '<td>' + escapeHtml(row.amount || '') + '</td>' +
                    '<td>' + escapeHtml(row.sales_tax || '—') + '</td>' +
                    buildInvoiceStatusCell(row) +
                    '<td>' + escapeHtml(row.due_date || '') + '</td>' +
                    '<td>' + escapeHtml(row.paid_date || '') + '</td>' +
                    buildInvoiceActionCell(row);
            } else if (isProject) {
                tr.innerHTML =
                    '<td>' + (index + 1) + '</td>' +
                    buildProjectNameCell(row) +
                    buildProjectClientCell(row) +
                    '<td>' + row.total_tasks + '</td>' +
                    '<td>' + row.completed + '</td>' +
                    '<td>' + escapeHtml(row.total_time) + '</td>' +
                    '<td>' + escapeHtml(row.avg_time) + '</td>' +
                    buildCompletionCell(row) +
                    buildProjectActionCell(row);
            } else {
                tr.innerHTML =
                    '<td>' + (index + 1) + '</td>' +
                    (isMarketing ? buildMarketingCampaignCell(row) : buildStaffUserCell(row)) +
                    '<td>' + row.total_tasks + '</td>' +
                    '<td>' + row.completed + '</td>' +
                    '<td>' + row.pending + '</td>' +
                    '<td>' + row.overdue + '</td>' +
                    '<td>' + escapeHtml(row.total_time) + '</td>' +
                    '<td>' + escapeHtml(row.avg_time) + '</td>' +
                    buildCompletionCell(row) +
                    (isMarketing ? buildMarketingActionCell(row) : buildStaffActionCell(row));
            }
            tbody.appendChild(tr);
        });
    }

    function escapeHtml(str) {
        if (str == null) {
            return '';
        }
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function fetchReportData() {
        var qs = getFilterParams();
        return fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: qs || ''
        });
    }

    function loadData() {
        if (!ajaxUrl) {
            return;
        }
        if (isEcommerce && cfg.dashboardChartsOnly && typeof window.ecommerceDashboardLoadData === 'function') {
            window.ecommerceDashboardLoadData();
            return;
        }
        hideAlert();
        setLoading(true);
        fetchReportData()
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('HTTP ' + r.status);
                }
                return r.text();
            })
            .then(function (text) {
                var raw = (text || '').trim();
                if (!raw) {
                    throw new Error('Empty response');
                }
                var data;
                try {
                    data = JSON.parse(raw);
                } catch (parseErr) {
                    var preview = raw.slice(0, 200);
                    throw new Error('Invalid JSON: ' + (parseErr && parseErr.message ? parseErr.message : parseErr) + (preview ? ' | ' + preview : ''));
                }
                return data;
            })
            .then(function (data) {
                setLoading(false);
                if (!data || data.status === 'error') {
                    showAlert((data && data.message) || lang.loadError || 'Error', 'danger');
                    return;
                }
                try {
                if (isEcommerce && cfg.dashboardChartsOnly) {
                    if (typeof window.ecommerceDashboardApplyPayload === 'function') {
                        window.ecommerceDashboardApplyPayload(data);
                    } else {
                        syncEcommerceCurrencyContext(data);
                        ecommerceSalesLineData = data.sales_line;
                        ecommerceSalesComparison = data.sales_comparison || null;
                        if (data.sales_line) {
                            syncEcommerceCurrencyContext(data.sales_line);
                        }
                        applyEcommerceSalesCharts();
                        updateEcommerceBarSummary(data.sales_line, data.sales_comparison);
                    }
                    updateActiveRangeLabel(data.filters);
                    return;
                }
                if (isEcommerce) {
                    syncEcommerceCurrencyContext(data);
                    updateEcommerceKpis(data.kpis);
                } else {
                    updateCards(data.cards);
                }
                updateDonut(data.donut);
                updateDonutSummary(data.donut, isEcommerce ? data.kpis : data.cards);
                if (isEcommerce) {
                    if (data.sales_line) {
                        syncEcommerceCurrencyContext(data.sales_line);
                    }
                    ecommerceSalesLineData = data.sales_line;
                    ecommerceSalesComparison = data.sales_comparison || null;
                    applyEcommerceSalesCharts();
                    updateTable(data.top_products);
                } else if (isInvoice) {
                    invoiceSalesLineData = data.sales_line;
                    invoiceSalesComparison = data.sales_comparison || null;
                    applyInvoiceSalesCharts();
                    updateTable(data.invoices);
                } else {
                    updateBarFromResponse(data.bar, data.filters, data.bar_comparison);
                    if (isMarketing && cfg.campaignRecipientsTable && typeof window.marketingRenderCampaignRecipients === 'function') {
                        window.marketingRenderCampaignRecipients(data);
                    } else {
                        updateTable(isProject ? data.projects : data.users);
                    }
                }
                updateActiveRangeLabel(data.filters);
                } catch (processingErr) {
                    console.error('[reports-dashboard] payload processing failed', processingErr);
                    showAlert(lang.loadError || 'Error', 'danger');
                }
            })
            .catch(function (err) {
                setLoading(false);
                console.error('[reports-dashboard] fetch failed', err);
                showAlert(lang.loadError || 'Error', 'danger');
            });
    }

    function toggleCustomDates() {
        var preset = $(isEcommerce ? 'ecommerceReportsDatePreset' : (isInvoice ? 'invoiceReportsDatePreset' : (isProject ? 'projectReportsDatePreset' : 'taskReportsDatePreset')));
        if (!preset && isEcommerce && cfg.dashboardChartsOnly) {
            preset = $('ecomDashDatePreset');
        }
        var box = $(isEcommerce ? 'ecommerceReportsCustomDates' : (isInvoice ? 'invoiceReportsCustomDates' : (isProject ? 'projectReportsCustomDates' : 'taskReportsCustomDates')));
        if (!box && isEcommerce && cfg.dashboardChartsOnly) {
            box = $('ecomDashCustomDates');
        }
        if (!preset || !box) {
            return;
        }
        if (preset.value === 'custom') {
            box.classList.remove('d-none');
            box.classList.add('d-flex');
        } else {
            box.classList.remove('d-flex');
            box.classList.add('d-none');
        }
    }

    function resetFilters() {
        var form = getForm();
        if (!form) {
            return;
        }
        if ($(isEcommerce ? 'ecommerceReportsDatePreset' : (isInvoice ? 'invoiceReportsDatePreset' : (isProject ? 'projectReportsDatePreset' : 'taskReportsDatePreset')))) {
            $(isEcommerce ? 'ecommerceReportsDatePreset' : (isInvoice ? 'invoiceReportsDatePreset' : (isProject ? 'projectReportsDatePreset' : 'taskReportsDatePreset'))).value = 'this_month';
        }
        if (form.user_id) {
            form.user_id.value = '';
        }
        if (form.campaign_id) {
            form.campaign_id.value = '';
        }
        if (form.project_id) {
            form.project_id.value = '';
        }
        if (form.client_id) {
            form.client_id.value = '';
        }
        if (form.status) {
            form.status.value = '';
        }
        if (isInvoice) {
            var currencyInput = $('invoiceReportsCurrency');
            if (currencyInput) {
                currencyInput.value = cfg.defaultCurrency || '';
                syncInvoiceCurrencyQueryParam(currencyInput.value);
            }
        } else if (form.currency) {
            form.currency.value = '';
        }
        toggleCustomDates();
        window.location.href = cfg.resetUrl || (isEcommerce ? 'reports.php?ecommerce' : (isInvoice ? 'reports.php?invoice' : (isProject ? 'reports.php?projects' : 'reports.php?task')));
    }

    function exportCsv() {
        var qs = getFilterParams();
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = ajaxUrl;
        form.target = '_blank';
        form.style.display = 'none';
        if (qs) {
            qs.split('&').forEach(function (pair) {
                if (!pair) {
                    return;
                }
                var eq = pair.indexOf('=');
                var key = eq >= 0 ? pair.slice(0, eq) : pair;
                var val = eq >= 0 ? pair.slice(eq + 1) : '';
                if (!key) {
                    return;
                }
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = decodeURIComponent(key.replace(/\+/g, ' '));
                input.value = decodeURIComponent(val.replace(/\+/g, ' '));
                form.appendChild(input);
            });
        }
        var actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'export';
        form.appendChild(actionInput);
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var form = getForm();
        if (form) {
            form.addEventListener('submit', function () {
                toggleCustomDates();
            });
        }
        toggleCustomDates();
        initReportsDropdownMenus();

        document.querySelectorAll('.reports-page .task-reports-toolbar-toggle').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                var menuId = btn.getAttribute('data-reports-dropdown');
                if (menuId) {
                    toggleReportsDropdown(menuId, e);
                }
            });
        });

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.task-reports-filter-dropdown') && !e.target.closest('.task-reports-chart-dropdown')) {
                closeReportsDropdowns();
            }
        });

        if (form) {
            form.addEventListener('click', function (e) {
                var item = e.target.closest('[data-filter-field]');
                if (!item) {
                    return;
                }
                e.preventDefault();
                var field = item.getAttribute('data-filter-field');
                var value = item.getAttribute('data-filter-value');
                var labelNode = item.querySelector('span');
                var label = labelNode ? labelNode.textContent.trim() : item.textContent.trim();
                setFilterField(field, value, label);
            });
        }
        var resetBtn = $(isEcommerce ? 'ecommerceReportsResetBtn' : (isInvoice ? 'invoiceReportsResetBtn' : (isProject ? 'projectReportsResetBtn' : 'taskReportsResetBtn')));
        if (resetBtn) {
            resetBtn.addEventListener('click', function (e) {
                e.preventDefault();
                resetFilters();
            });
        }
        document.querySelectorAll('.reports-page [data-reports-action="refresh"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var menu = $('reportsToolbarMenu');
                if (menu && typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                    var inst = bootstrap.Collapse.getInstance(menu);
                    if (inst) {
                        inst.hide();
                    }
                }
                loadData();
            });
        });
        document.querySelectorAll('.reports-page [data-reports-action="export"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var menu = $('reportsToolbarMenu');
                if (menu && typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                    var inst = bootstrap.Collapse.getInstance(menu);
                    if (inst) {
                        inst.hide();
                    }
                }
                exportCsv();
            });
        });
        if (isEcommerce) {
            initEcommerceChartState();
            if (!cfg.dashboardChartsOnly) {
                initEcommerceChartToggles();
            }
            var ecommerceViewMenu = $('ecommerceChartViewMenu');
            if (ecommerceViewMenu) {
                ecommerceViewMenu.addEventListener('click', function (e) {
                    var item = e.target.closest('[data-chart-view]');
                    if (!item) {
                        return;
                    }
                    e.preventDefault();
                    setEcommerceChartView(item.getAttribute('data-chart-view'));
                    closeReportsDropdowns();
                });
            }
            var ecommerceGroupMenu = $('ecommerceChartGroupMenu');
            if (ecommerceGroupMenu) {
                ecommerceGroupMenu.addEventListener('click', function (e) {
                    var item = e.target.closest('[data-chart-group]');
                    if (!item) {
                        return;
                    }
                    e.preventDefault();
                    setEcommerceChartGrouping(item.getAttribute('data-chart-group'));
                    closeReportsDropdowns();
                });
            }
            if (cfg.filters && !cfg.dashboardChartsOnly) {
                updateActiveRangeLabel(cfg.filters);
            }
            if (getForm() && !cfg.dashboardChartsOnly) {
                loadData();
            }
            return;
        }
        if (isInvoice) {
            initInvoiceChartState();
            initInvoiceChartToggles();
            var invoiceViewMenu = $('invoiceChartViewMenu');
            if (invoiceViewMenu) {
                invoiceViewMenu.addEventListener('click', function (e) {
                    var item = e.target.closest('[data-chart-view]');
                    if (!item) {
                        return;
                    }
                    e.preventDefault();
                    setInvoiceChartView(item.getAttribute('data-chart-view'));
                    closeReportsDropdowns();
                });
            }
            var invoiceGroupMenu = $('invoiceChartGroupMenu');
            if (invoiceGroupMenu) {
                invoiceGroupMenu.addEventListener('click', function (e) {
                    var item = e.target.closest('[data-chart-group]');
                    if (!item) {
                        return;
                    }
                    e.preventDefault();
                    setInvoiceChartGrouping(item.getAttribute('data-chart-group'));
                    closeReportsDropdowns();
                });
            }
            if (cfg.filters) {
                updateActiveRangeLabel(cfg.filters);
            }
            if (isInvoice) {
                var currencyInput = $('invoiceReportsCurrency');
                if (currencyInput) {
                    syncInvoiceCurrencyQueryParam(currencyInput.value);
                }
            }
            if (getForm() && cfg.moduleEnabled !== false) {
                loadData();
            } else if (cfg.moduleEnabled === false) {
                showAlert(lang.loadError || 'Invoices module is disabled.', 'warning');
            }
            return;
        }
        var userMenu = $(isProject ? 'projectBarChartUserMenu' : 'taskBarChartUserMenu');
        if (userMenu) {
            userMenu.addEventListener('click', function (e) {
                var item = e.target.closest('[data-chart-user]');
                if (!item) {
                    return;
                }
                e.preventDefault();
                var labelNode = item.querySelector('span');
                setChartUser(item.getAttribute('data-chart-user'), labelNode ? labelNode.textContent.trim() : item.textContent.trim());
                closeReportsDropdowns();
            });
        }
        var groupMenu = $(isProject ? 'projectBarChartGroupMenu' : 'taskBarChartGroupMenu');
        if (groupMenu) {
            groupMenu.addEventListener('click', function (e) {
                var item = e.target.closest('[data-chart-group]');
                if (!item) {
                    return;
                }
                e.preventDefault();
                setChartGrouping(item.getAttribute('data-chart-group'));
                closeReportsDropdowns();
            });
        }
        if (cfg.filters) {
            updateActiveRangeLabel(cfg.filters);
        }
        if (getForm()) {
            if (!(isEcommerce && cfg.dashboardChartsOnly)) {
                loadData();
            }
        }
    });

    window.reportsDashboardApplyEcommerceCharts = function (data) {
        if (!isEcommerce || !data) {
            return;
        }
        syncEcommerceCurrencyContext(data);
        ecommerceSalesLineData = data.sales_line || null;
        ecommerceSalesComparison = data.sales_comparison || null;
        if (data.sales_line) {
            syncEcommerceCurrencyContext(data.sales_line);
        }
        applyEcommerceSalesCharts();
        updateEcommerceBarSummary(data.sales_line, data.sales_comparison);
    };

    window.reportsDashboardReloadEcommerceCharts = function () {
        if (!isEcommerce || !ajaxUrl) {
            return;
        }
        if (cfg.dashboardChartsOnly && typeof window.ecommerceDashboardLoadData === 'function') {
            window.ecommerceDashboardLoadData();
            return;
        }
        var qs = getFilterParams();
        fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: qs || ''
        })
            .then(function (r) {
                return r.text().then(function (text) {
                    var raw = (text || '').trim();
                    if (!raw) {
                        throw new Error('Empty response');
                    }
                    return JSON.parse(raw);
                });
            })
            .then(function (data) {
                if (!data || data.status === 'error') {
                    return;
                }
                if (cfg.dashboardChartsOnly && typeof window.ecommerceDashboardApplyPayload === 'function') {
                    window.ecommerceDashboardApplyPayload(data);
                    return;
                }
                window.reportsDashboardApplyEcommerceCharts(data);
            })
            .catch(function () {});
    };
})();
