<div class="template-adjust">
                        <?php reports_render_dashboard_skeleton('invoice'); ?>
                        <div class="reports-dashboard-live">
                        <div class="reports-filters-panel pd-bt-0">
                            <div class="filters-row invoice-reports-filters-row d-flex justify-content-between align-items-start flex-wrap row-gap-10 pd-bt-0">
                                <form id="invoiceReportsFilterForm" method="get" action="reports" class="d-flex align-items-end col-gap-10 flex-wrap task-reports-filters-form">
                                    <input type="hidden" name="invoice" value="">
                                    <?php
                                    reports_page_render_toolbar_dropdown([
                                        'inputId' => 'invoiceReportsDatePreset',
                                        'menuId' => 'invoiceReportsDatePresetDropdown',
                                        'btnTextId' => 'invoiceReportsDatePresetBtnText',
                                        'fieldName' => 'date_preset',
                                        'fieldLabel' => $lang['Date Range'] ?? 'Date Range',
                                        'options' => $datePresetOptions,
                                        'selectedValue' => $datePreset,
                                        'selectedLabel' => $datePresetLabel,
                                    ]);
                                    ?>
                                    <div id="invoiceReportsCustomDates" class="align-items-end col-gap-10 flex-wrap<?php echo $datePreset === 'custom' ? ' d-flex' : ' d-none'; ?>">
                                        <div class="floating-filter-field">
                                            <label class="floating-label" for="invoiceReportsFromDate"><?php echo $lang['Start Date'] ?? 'Start date'; ?></label>
                                            <input type="date" name="from_date" id="invoiceReportsFromDate" class="form-control" value="<?php echo htmlspecialchars($fromDate); ?>">
                                        </div>
                                        <div class="floating-filter-field">
                                            <label class="floating-label" for="invoiceReportsToDate"><?php echo $lang['End Date'] ?? 'End Date'; ?></label>
                                            <input type="date" name="to_date" id="invoiceReportsToDate" class="form-control" value="<?php echo htmlspecialchars($toDate); ?>">
                                        </div>
                                    </div>
                                    <?php
                                    reports_page_render_toolbar_dropdown([
                                        'inputId' => 'invoiceReportsClientId',
                                        'menuId' => 'invoiceReportsClientIdDropdown',
                                        'btnTextId' => 'invoiceReportsClientIdBtnText',
                                        'fieldName' => 'client_id',
                                        'fieldLabel' => $lang['Client'] ?? 'Client',
                                        'options' => $clientOptions,
                                        'selectedValue' => $selectedClientId ? (string)$selectedClientId : '',
                                        'selectedLabel' => $clientLabel,
                                    ]);
                                    ?>
                                    <?php
                                    reports_page_render_toolbar_dropdown([
                                        'inputId' => 'invoiceReportsProjectId',
                                        'menuId' => 'invoiceReportsProjectIdDropdown',
                                        'btnTextId' => 'invoiceReportsProjectIdBtnText',
                                        'fieldName' => 'project_id',
                                        'fieldLabel' => $lang['Project'] ?? 'Project',
                                        'options' => $projectOptions,
                                        'selectedValue' => $selectedProjectId ? (string)$selectedProjectId : '',
                                        'selectedLabel' => $projectLabel,
                                    ]);
                                    ?>
                                    <?php
                                    reports_page_render_toolbar_dropdown([
                                        'inputId' => 'invoiceReportsCurrency',
                                        'menuId' => 'invoiceReportsCurrencyDropdown',
                                        'btnTextId' => 'invoiceReportsCurrencyBtnText',
                                        'fieldName' => 'currency',
                                        'fieldLabel' => $lang['Currency'] ?? 'Currency',
                                        'options' => $currencyOptions,
                                        'selectedValue' => $selectedCurrency,
                                        'selectedLabel' => $currencyLabel,
                                        'includeInQuery' => $currencyInUrl,
                                    ]);
                                    ?>
                                    <?php
                                    reports_page_render_toolbar_dropdown([
                                        'inputId' => 'invoiceReportsStatus',
                                        'menuId' => 'invoiceReportsStatusDropdown',
                                        'btnTextId' => 'invoiceReportsStatusBtnText',
                                        'fieldName' => 'status',
                                        'fieldLabel' => $lang['Payment Status'] ?? 'Payment status',
                                        'options' => $invoiceStatusOptions,
                                        'selectedValue' => $selectedStatus,
                                        'selectedLabel' => $statusLabel,
                                    ]);
                                    ?>
                                    <div class="reports-filter-actions">
                                        <button type="submit" class="btn primary-btn d-inline-flex align-items-center col-gap-5 task-reports-filter-btn">
                                            <?php echo ts_icon('filter', 'dropdown-toggle-icon h-6'); ?>
                                            <span><?php echo $lang['Apply Filter'] ?? 'Apply Filter'; ?></span>
                                        </button>
                                        <button type="button" class="btn outline-btn d-inline-flex align-items-center col-gap-5 task-reports-filter-btn" id="invoiceReportsResetBtn">
                                            <?php echo ts_icon('close', 'dropdown-toggle-icon h-6'); ?>
                                            <span><?php echo $lang['Reset'] ?? 'Reset'; ?></span>
                                        </button>
                                    </div>
                                </form>
                                <div class="task-reports-active-range-wrap" id="invoiceReportsActiveRangeWrap">
                                    <div class="task-reports-active-range" id="invoiceReportsActiveRange" role="status" aria-live="polite"></div>
                                </div>
                            </div>
                        </div>

                        <div id="invoiceReportsCards" class="pd-bt-0 row counter-align adjust-1 pd-2 task-reports-cards" style="padding-bottom: 0 !important;">
                            <div class="col-lg col-md-4 col-6">
                                <div class="widget-card">
                                    <div class="grey"><?php echo $lang['Total Invoices'] ?? 'Total Invoices'; ?></div>
                                    <div class="counts dash-rttb" id="cardTotalInvoices">—</div>
                                    <div class="up-down" id="cardTotalInvoicesArrow"></div>
                                    <div class="grey persent-count" id="cardTotalInvoicesCompare"></div>
                                </div>
                            </div>
                            <div class="col-lg col-md-4 col-6">
                                <div class="widget-card">
                                    <div class="grey"><?php echo $lang['Paid Invoices'] ?? 'Paid Invoices'; ?></div>
                                    <div class="counts dash-rttb" id="cardPaidInvoices">—</div>
                                    <div class="up-down" id="cardPaidInvoicesArrow"></div>
                                    <div class="grey persent-count" id="cardPaidInvoicesCompare"></div>
                                </div>
                            </div>
                            <div class="col-lg col-md-4 col-6">
                                <div class="widget-card">
                                    <div class="grey"><?php echo $lang['Unpaid Invoices'] ?? 'Unpaid invoices'; ?></div>
                                    <div class="counts dash-rttb" id="cardUnpaidInvoices">—</div>
                                    <div class="up-down" id="cardUnpaidInvoicesArrow"></div>
                                    <div class="grey persent-count" id="cardUnpaidInvoicesCompare"></div>
                                </div>
                            </div>
                            <div class="col-lg col-md-4 col-6">
                                <div class="widget-card">
                                    <div class="grey"><?php echo $lang['Overdue'] ?? 'Overdue'; ?></div>
                                    <div class="counts dash-rttb" id="cardOverdueInvoices">—</div>
                                    <div class="up-down" id="cardOverdueInvoicesArrow"></div>
                                    <div class="grey persent-count" id="cardOverdueInvoicesCompare"></div>
                                </div>
                            </div>
                            <div class="col-lg col-md-4 col-6">
                                <div class="widget-card">
                                    <div class="grey"><?php echo $lang['Issued in Period'] ?? 'Issued in Period'; ?></div>
                                    <div class="counts dash-rttb" id="cardIssuedInvoices">—</div>
                                    <div class="up-down" id="cardIssuedInvoicesArrow"></div>
                                    <div class="grey persent-count" id="cardIssuedInvoicesCompare"></div>
                                </div>
                            </div>
                            <div class="col-lg col-md-4 col-6">
                                <div class="widget-card">
                                    <div class="grey"><?php echo $lang['Cancel Invoices'] ?? 'Cancel Invoices'; ?></div>
                                    <div class="counts dash-rttb" id="cardCancelledInvoices">—</div>
                                    <div class="up-down" id="cardCancelledInvoicesArrow"></div>
                                    <div class="grey persent-count" id="cardCancelledInvoicesCompare"></div>
                                </div>
                            </div>
                        </div>

                        <div class="row pd-2 task-reports-charts invoice-reports-charts" style="padding-top: 0 !important;">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <div class="widget-card shadow center-align pie-chart h-100 reports-status-pie-card">
                                    <div class="card-title text-start w-100 mb-2">
                                        <h3 class="mb-0"><?php echo $lang['Payment Status Overview'] ?? 'Payment status overview'; ?></h3>
                                    </div>
                                    <div class="task-reports-bar-summary task-reports-donut-summary" id="invoiceReportsDonutSummary">
                                        <div class="task-reports-bar-summary-main" id="invoiceReportsDonutSummaryMain">—</div>
                                        <div class="task-reports-bar-summary-meta">
                                            <span class="task-reports-bar-summary-arrow" id="invoiceReportsDonutSummaryArrow"></span>
                                            <span class="task-reports-bar-summary-pct" id="invoiceReportsDonutSummaryPct"></span>
                                            <span class="task-reports-bar-summary-sep" id="invoiceReportsDonutSummarySep" aria-hidden="true">·</span>
                                            <span class="task-reports-bar-summary-vs" id="invoiceReportsDonutSummaryVs"></span>
                                        </div>
                                    </div>
                                    <div class="d-none invoice-reports-legend-refs" aria-hidden="true">
                                        <span id="invoiceReportsLegendColor-paid" style="color:#28a745"></span>
                                        <span id="invoiceReportsLegendColor-unpaid" style="color:#dc3545"></span>
                                        <span id="invoiceReportsLegendColor-overdue" style="color:#fd7e14"></span>
                                        <span id="invoiceReportsLegendColor-cancelled" style="color:#adb5bd"></span>
                                    </div>
                                    <div class="chart-pie pt-4 pb-2 task-reports-pie-wrap">
                                        <canvas id="invoiceStatusDonutChart" width="350" height="350"></canvas>
                                        <div class="total-pro">
                                            <h2 id="invoiceDonutTotal">0</h2>
                                            <span><?php echo $lang['Invoices'] ?? 'Invoices'; ?></span>
                                        </div>
                                    </div>
                                    <div class="container counters-bottom reports-status-counters">
                                        <div class="row chart-footer justify-content-center col-gap-35 reports-status-footer">
                                            <div class="foot-c-box">
                                                <span class="task-reports-legend-num" id="legendPaid">0</span>
                                                <span class="task-reports-legend-label fctxt"><?php echo $lang['Paid'] ?? 'Paid'; ?></span>
                                            </div>
                                            <div class="foot-c-box">
                                                <span class="task-reports-legend-num" id="legendUnpaid">0</span>
                                                <span class="task-reports-legend-label fctxt"><?php echo $lang['Unpaid'] ?? 'Unpaid'; ?></span>
                                            </div>
                                            <div class="foot-c-box">
                                                <span class="task-reports-legend-num" id="legendOverdueInv">0</span>
                                                <span class="task-reports-legend-label fctxt"><?php echo $lang['Overdue'] ?? 'Overdue'; ?></span>
                                            </div>
                                            <div class="foot-c-box">
                                                <span class="task-reports-legend-num" id="legendCancelled">0</span>
                                                <span class="task-reports-legend-label fctxt"><?php echo $lang['Cancel'] ?? 'Cancel'; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="widget-card shadow h-100 <?php echo !$isInvoicesEnabled ? 'sales-stats-disabled' : ''; ?>">
                                    <?php if (!$isInvoicesEnabled) : ?>
                                    <div class="sales-stats-overlay">
                                        <div class="overlay-card">
                                            <h3><?php echo $lang['Payments module disabled'] ?? 'Payments module disabled'; ?></h3>
                                            <p><?php echo $lang['invoice_reports_module_overlay'] ?? 'Enable invoicing and payment features to manage your business transactions efficiently.'; ?></p>
                                            <a href="<?php echo $url; ?>admin/system-settings" class="primary-btn"><?php echo $lang['Enable Now'] ?? 'Enable Now'; ?></a>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <div class="sales-stats-content financials-section">
                                        <div class="d-flex justify-content-between align-items-start flex-wrap row-gap-10 mb-2">
                                            <div class="card-title mb-0">
                                                <h3 class="mb-0"><?php echo $lang['Financial Overview'] ?? 'Financial overview'; ?></h3>
                                            </div>
                                            <div class="d-flex align-items-center col-gap-10 task-reports-chart-controls flex-wrap">
                                                <div class="toolbar-dropdown-wrapper task-reports-chart-dropdown">
                                                    <button type="button" class="border-btn-a task-reports-toolbar-toggle" id="invoiceChartViewDropdown" data-reports-dropdown="invoiceChartViewMenu" aria-expanded="false">
                                                        <span class="task-reports-filter-selected" id="invoiceChartViewBtnText"><?php echo $lang['Line Chart'] ?? 'Line Chart'; ?></span>
                                                        <?php echo ts_icon('chevron-down', 'icon-caret-down'); ?>
                                                    </button>
                                                    <div id="invoiceChartViewMenu" class="task-reports-toolbar-menu" style="min-width: 160px;">
                                                        <button type="button" class="first" data-chart-view="bar"><span><?php echo $lang['Bar Chart'] ?? 'Bar Chart'; ?></span></button>
                                                        <button type="button" class="last active" data-chart-view="line"><span><?php echo $lang['Line Chart'] ?? 'Line Chart'; ?></span></button>
                                                    </div>
                                                </div>
                                                <div class="toolbar-dropdown-wrapper task-reports-chart-dropdown" id="invoiceChartGroupDropdownWrap">
                                                    <button type="button" class="border-btn-a task-reports-toolbar-toggle" id="invoiceChartGroupDropdown" data-reports-dropdown="invoiceChartGroupMenu" aria-expanded="false">
                                                        <span class="task-reports-filter-selected" id="invoiceChartGroupBtnText"><?php echo $lang['Weekly'] ?? 'Weekly'; ?></span>
                                                        <?php echo ts_icon('chevron-down', 'icon-caret-down'); ?>
                                                    </button>
                                                    <div id="invoiceChartGroupMenu" class="task-reports-toolbar-menu" style="min-width: 160px;">
                                                        <button type="button" class="first" data-chart-group="daily"><span><?php echo $lang['Daily'] ?? 'Daily'; ?></span></button>
                                                        <button type="button" class="active" data-chart-group="weekly"><span><?php echo $lang['Weekly'] ?? 'Weekly'; ?></span></button>
                                                        <button type="button" class="last" data-chart-group="monthly"><span><?php echo $lang['Monthly'] ?? 'Monthly'; ?></span></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <input type="hidden" name="chart_grouping" id="invoiceReportsChartGrouping" value="<?php echo htmlspecialchars(!empty($filters['chart_grouping']) ? (string)$filters['chart_grouping'] : 'weekly'); ?>">
                                        <div class="monthly-rev mb-4">
                                            <div class="task-reports-bar-summary sales-stats-bar-summary" id="invoiceReportsBarSummary">
                                                <div class="month-rps flex-grow invoice-financial-summary-main" id="invoiceReportsSummaryMain">
                                                    <div class="invoice-financial-summary-item is-chart-toggle" data-dataset-index="0" role="button" tabindex="0" title="<?php echo htmlspecialchars($lang['Paid'] ?? 'Paid', ENT_QUOTES, 'UTF-8'); ?>">
                                                        <span class="invoice-financial-summary-label invoice-financial-summary-label--paid"><?php echo $lang['Paid'] ?? 'Paid'; ?></span>
                                                        <span class="invoice-financial-summary-value" id="invoiceReportsBarSummaryPaid">—</span>
                                                    </div>
                                                    <div class="invoice-financial-summary-item is-chart-toggle" data-dataset-index="1" role="button" tabindex="0" title="<?php echo htmlspecialchars($lang['Unpaid'] ?? 'Unpaid', ENT_QUOTES, 'UTF-8'); ?>">
                                                        <span class="invoice-financial-summary-label invoice-financial-summary-label--unpaid"><?php echo $lang['Unpaid'] ?? 'Unpaid'; ?></span>
                                                        <span class="invoice-financial-summary-value" id="invoiceReportsBarSummaryUnpaid">—</span>
                                                    </div>
                                                    <div class="invoice-financial-summary-item" title="<?php echo htmlspecialchars($lang['Sales Tax'] ?? 'Sales Tax', ENT_QUOTES, 'UTF-8'); ?>">
                                                        <span class="invoice-financial-summary-label invoice-financial-summary-label--tax"><?php echo $lang['Sales Tax'] ?? 'Sales Tax'; ?></span>
                                                        <span class="invoice-financial-summary-value" id="invoiceReportsBarSummaryTax">—</span>
                                                    </div>
                                                    <div class="invoice-financial-summary-item is-chart-toggle" data-dataset-index="2" role="button" tabindex="0" title="<?php echo htmlspecialchars($lang['Cancel'] ?? 'Cancel', ENT_QUOTES, 'UTF-8'); ?>">
                                                        <span class="invoice-financial-summary-label invoice-financial-summary-label--cancelled"><?php echo $lang['Cancel'] ?? 'Cancel'; ?></span>
                                                        <span class="invoice-financial-summary-value" id="invoiceReportsBarSummaryCancelled">—</span>
                                                    </div>
                                                </div>
                                                <div class="task-reports-bar-summary-meta" id="invoiceReportsBarSummaryMeta">
                                                    <span class="task-reports-bar-summary-arrow" id="invoiceReportsBarSummaryArrow"></span>
                                                    <span class="task-reports-bar-summary-pct" id="invoiceReportsBarSummaryPct"></span>
                                                    <span class="task-reports-bar-summary-sep" id="invoiceReportsBarSummarySep" aria-hidden="true">·</span>
                                                    <span class="task-reports-bar-summary-vs" id="invoiceReportsBarSummaryVs"></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="invoiceReportsChartBarPanel" class="task-reports-bar-wrap invoice-reports-bar-wrap d-none">
                                            <canvas id="invoiceSalesBarChart"></canvas>
                                        </div>
                                        <div id="invoiceReportsChartLinePanel" class="stats-graph invoice-reports-stats-graph">
                                            <canvas id="invoiceSalesLineChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pd-2 card plan-card">
                            <div class="page-title">
                                <h2><?php echo $lang['Financial Performance'] ?? 'Financial Performance'; ?></h2>
                            </div>
                            <div class="table-responsive scroll-x vh-100">
                                <table class="table table-new projectspage invoice-reports-performance-table" data-pagination="true" data-page-size="5">
                                    <thead>
                                        <tr>
                                            <th><?php echo $lang['No'] ?? 'No'; ?></th>
                                            <th class="invoice-reports-invoice-col"><?php echo $lang['Invoice'] ?? 'Invoice'; ?></th>
                                            <th><?php echo $lang['Client'] ?? 'Client'; ?></th>
                                            <th><?php echo $lang['Amount'] ?? 'Amount'; ?></th>
                                            <th><?php echo $lang['Sales Tax'] ?? 'Sales Tax'; ?></th>
                                            <th><?php echo $lang['Status'] ?? 'Status'; ?></th>
                                            <th><?php echo $lang['Due Date'] ?? 'Due date'; ?></th>
                                            <th><?php echo $lang['Paid Date'] ?? 'Paid Date'; ?></th>
                                            <th><?php echo $lang['Options'] ?? ($lang['Action'] ?? 'Options'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="projects-tbl">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        </div>
                    </div>
