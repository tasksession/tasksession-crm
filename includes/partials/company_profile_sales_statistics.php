<?php
/**
 * Sales Statistics widget for company profile overview.
 */
require_once __DIR__ . '/../company_profile_sales_helper.php';
?>
<div class="profile-content mb-4">
    <div class="financials-section sales-stats-content">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="card-title">
                <h3><?php echo htmlspecialchars($lang['Sales Statistics'] ?? 'Sales statistics', ENT_QUOTES, 'UTF-8'); ?></h3>
            </div>
            <div class="d-flex align-items-center col-gap-10">
                <div class="dropdown-btn">
                    <div class="dropdown">
                        <button class="btn btn-light dropdown-toggle" type="button" id="currencyFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php echo htmlspecialchars($companySalesDefaultCurrencyDisplay ?? 'United States ($)', ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                        <ul class="dropdown-menu p-3" aria-labelledby="currencyFilterDropdown" style="min-width: 200px;">
                            <?php
                            $orderedCurrencies = $companySalesOrderedCurrencies ?? [];
                            if (empty($orderedCurrencies) && !empty($companySalesDefaultCurrency)) {
                                $orderedCurrencies = [(string) $companySalesDefaultCurrency];
                            }
                            foreach ($orderedCurrencies as $currency) {
                                $currency = trim((string) $currency);
                                if ($currency === '') {
                                    continue;
                                }
                                $currencyCode = $currency;
                                $currencySymbol = '';
                                if (strpos($currency, ',') !== false) {
                                    $currencyParts = explode(',', $currency);
                                    $currencyCode = trim($currencyParts[0]);
                                    $currencySymbol = isset($currencyParts[1]) ? trim($currencyParts[1]) : $currencyCode;
                                } else {
                                    $settings = settings::findById(1);
                                    $symbolText = $settings->currency_symbols[$currencyCode] ?? $currencyCode;
                                    if (preg_match('/\((.*?)\)/', $symbolText, $m)) {
                                        $currencySymbol = $m[1];
                                    } else {
                                        $currencySymbol = $currencyCode;
                                    }
                                }
                                $displayName = company_sales_currency_display_name($currencyCode);
                                if (strpos($displayName, '(') === false && $currencySymbol !== '') {
                                    switch ($currencyCode) {
                                        case 'USD': $countryName = 'United States'; break;
                                        case 'PKR': $countryName = 'Pakistan'; break;
                                        case 'Rs': $countryName = 'Pakistan'; break;
                                        case 'CAD': $countryName = 'Canada'; break;
                                        case 'EUR': $countryName = 'European Union'; break;
                                        case 'GBP': $countryName = 'United Kingdom'; break;
                                        case 'INR': $countryName = 'India'; break;
                                        case 'JPY': $countryName = 'Japan'; break;
                                        case 'AUD': $countryName = 'Australia'; break;
                                        case 'CHF': $countryName = 'Switzerland'; break;
                                        case 'CNY': $countryName = 'China'; break;
                                        default: $countryName = $currencyCode;
                                    }
                                    $displayName = $countryName . ' (' . $currencySymbol . ')';
                                }
                                $encodedValue = (strpos($currency, ',') !== false) ? $currency : ($currencyCode . ',' . $currencySymbol);
                                echo '<li><button class="dropdown-item" type="button" onclick="selectCurrency(\'' . htmlspecialchars($encodedValue, ENT_QUOTES, 'UTF-8') . '\')">' . htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') . '</button></li>';
                            }
                            ?>
                        </ul>
                    </div>
                </div>
                <div class="dropdown-btn">
                    <div class="dropdown">
                        <button class="btn btn-light dropdown-toggle" type="button" id="customRangeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php echo htmlspecialchars($lang['This Year (Jan - Dec)'] ?? 'This Year (Jan - Dec)', ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                        <ul class="dropdown-menu p-3" aria-labelledby="customRangeDropdown" style="min-width: 300px;">
                            <li><button class="dropdown-item" type="button" onclick="selectQuickRange('last7')"><?php echo htmlspecialchars($lang['Last 7 days'] ?? 'Last 7 days', ENT_QUOTES, 'UTF-8'); ?></button></li>
                            <li><button class="dropdown-item" type="button" onclick="selectQuickRange('last30')"><?php echo htmlspecialchars($lang['Last 30 days'] ?? 'Last 30 days', ENT_QUOTES, 'UTF-8'); ?></button></li>
                            <li><button class="dropdown-item" type="button" onclick="selectQuickRange('last90')"><?php echo htmlspecialchars($lang['Last 90 days'] ?? 'Last 90 days', ENT_QUOTES, 'UTF-8'); ?></button></li>
                            <li><button class="dropdown-item" type="button" onclick="selectQuickRange('last6months')"><?php echo htmlspecialchars($lang['Last 6 months'] ?? 'Last 6 months', ENT_QUOTES, 'UTF-8'); ?></button></li>
                            <li><button class="dropdown-item" type="button" onclick="selectQuickRange('thisYear')"><?php echo htmlspecialchars($lang['This Year (Jan - Dec)'] ?? 'This Year (Jan - Dec)', ENT_QUOTES, 'UTF-8'); ?></button></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <div class="px-2 sales-stats-custom-range">
                                    <label><?php echo htmlspecialchars($lang['Custom'] ?? 'Custom', ENT_QUOTES, 'UTF-8'); ?></label>
                                    <input type="date" id="customStart" class="form-control mb-2">
                                    <input type="date" id="customEnd" class="form-control mb-2">
                                    <button class="primary-btn w-100" type="button" onclick="selectCustomRange()"><?php echo htmlspecialchars($lang['Apply'] ?? 'Apply', ENT_QUOTES, 'UTF-8'); ?></button>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="monthly-rev mb-4">
            <div class="task-reports-bar-summary sales-stats-bar-summary" id="salesStatsBarSummary">
                <div class="month-rps flex-grow invoice-financial-summary-main" id="month-rps">
                    <div class="invoice-financial-summary-item is-chart-toggle" data-dataset-index="0" role="button" tabindex="0" title="<?php echo htmlspecialchars($lang['Paid'] ?? 'Paid', ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="invoice-financial-summary-label invoice-financial-summary-label--paid"><?php echo htmlspecialchars($lang['Paid'] ?? 'Paid', ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="invoice-financial-summary-value" id="salesStatsPaid">—</span>
                    </div>
                    <div class="invoice-financial-summary-item is-chart-toggle" data-dataset-index="1" role="button" tabindex="0" title="<?php echo htmlspecialchars($lang['Unpaid'] ?? 'Unpaid', ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="invoice-financial-summary-label invoice-financial-summary-label--unpaid"><?php echo htmlspecialchars($lang['Unpaid'] ?? 'Unpaid', ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="invoice-financial-summary-value" id="salesStatsUnpaid">—</span>
                    </div>
                    <div class="invoice-financial-summary-item">
                        <span class="invoice-financial-summary-label invoice-financial-summary-label--tax"><?php echo htmlspecialchars($lang['Sales Tax'] ?? 'Sales Tax', ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="invoice-financial-summary-value" id="salesStatsTax">—</span>
                    </div>
                    <div class="invoice-financial-summary-item is-chart-toggle" data-dataset-index="2" role="button" tabindex="0" title="<?php echo htmlspecialchars($lang['Cancel'] ?? 'Cancel', ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="invoice-financial-summary-label invoice-financial-summary-label--cancelled"><?php echo htmlspecialchars($lang['Cancel'] ?? 'Cancel', ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="invoice-financial-summary-value" id="salesStatsCancelled">—</span>
                    </div>
                </div>
                <div class="task-reports-bar-summary-meta" id="salesStatsBarSummaryMeta">
                    <span class="task-reports-bar-summary-arrow" id="salesStatsBarSummaryArrow"></span>
                    <span class="task-reports-bar-summary-pct" id="salesStatsBarSummaryPct"></span>
                    <span class="task-reports-bar-summary-sep" id="salesStatsBarSummarySep" aria-hidden="true">·</span>
                    <span class="task-reports-bar-summary-vs" id="salesStatsBarSummaryVs"></span>
                </div>
            </div>
        </div>
        <div class="stats-graph">
            <canvas id="earningsLineChart" height="300"></canvas>
        </div>
    </div>
</div>
