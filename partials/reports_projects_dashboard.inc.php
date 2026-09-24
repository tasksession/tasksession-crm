<div class="template-adjust">
                        <?php reports_render_dashboard_skeleton('projects'); ?>
                        <div class="reports-dashboard-live">
                        <div class="reports-filters-panel pd-bt-0">
                            <div class="filters-row d-flex justify-content-between align-items-start flex-wrap row-gap-10 pd-bt-0">
                                <form id="projectReportsFilterForm" method="get" action="reports" class="d-flex align-items-end col-gap-10 flex-wrap task-reports-filters-form">
                                    <input type="hidden" name="projects" value="1">
                                    <?php
                                    reports_page_render_toolbar_dropdown([
                                        'inputId' => 'projectReportsDatePreset',
                                        'menuId' => 'projectReportsDatePresetDropdown',
                                        'btnTextId' => 'projectReportsDatePresetBtnText',
                                        'fieldName' => 'date_preset',
                                        'fieldLabel' => $lang['Date Range'] ?? 'Date Range',
                                        'options' => $datePresetOptions,
                                        'selectedValue' => $datePreset,
                                        'selectedLabel' => $datePresetLabel,
                                    ]);
                                    ?>
                                    <div id="projectReportsCustomDates" class="align-items-end col-gap-10 flex-wrap<?php echo $datePreset === 'custom' ? ' d-flex' : ' d-none'; ?>">
                                        <div class="floating-filter-field">
                                            <label class="floating-label" for="projectReportsFromDate"><?php echo $lang['Start Date'] ?? 'Start date'; ?></label>
                                            <input type="date" name="from_date" id="projectReportsFromDate" class="form-control" value="<?php echo htmlspecialchars($fromDate); ?>">
                                        </div>
                                        <div class="floating-filter-field">
                                            <label class="floating-label" for="projectReportsToDate"><?php echo $lang['End Date'] ?? 'End Date'; ?></label>
                                            <input type="date" name="to_date" id="projectReportsToDate" class="form-control" value="<?php echo htmlspecialchars($toDate); ?>">
                                        </div>
                                    </div>
                                    <?php
                                    reports_page_render_toolbar_dropdown([
                                        'inputId' => 'projectReportsUserId',
                                        'menuId' => 'projectReportsUserIdDropdown',
                                        'btnTextId' => 'projectReportsUserIdBtnText',
                                        'fieldName' => 'user_id',
                                        'fieldLabel' => $lang['User / Staff'] ?? 'User / Staff',
                                        'options' => $userOptions,
                                        'selectedValue' => $selectedUserId ? (string)$selectedUserId : '',
                                        'selectedLabel' => $userLabel,
                                    ]);
                                    ?>
                                    <?php
                                    reports_page_render_toolbar_dropdown([
                                        'inputId' => 'projectReportsClientId',
                                        'menuId' => 'projectReportsClientIdDropdown',
                                        'btnTextId' => 'projectReportsClientIdBtnText',
                                        'fieldName' => 'client_id',
                                        'fieldLabel' => $lang['Client'] ?? 'Client',
                                        'options' => $clientOptions,
                                        'selectedValue' => $selectedClientId ? (string)$selectedClientId : '',
                                        'selectedLabel' => $clientLabel,
                                    ]);
                                    ?>
                                    <?php
                                    reports_page_render_toolbar_dropdown([
                                        'inputId' => 'projectReportsProjectId',
                                        'menuId' => 'projectReportsProjectIdDropdown',
                                        'btnTextId' => 'projectReportsProjectIdBtnText',
                                        'fieldName' => 'project_id',
                                        'fieldLabel' => $lang['Project'] ?? 'Project',
                                        'options' => $projectOptions,
                                        'selectedValue' => $selectedProjectId ? (string)$selectedProjectId : '',
                                        'selectedLabel' => $projectLabel,
                                    ]);
                                    ?>
                                    <?php
                                    reports_page_render_toolbar_dropdown([
                                        'inputId' => 'projectReportsStatus',
                                        'menuId' => 'projectReportsStatusDropdown',
                                        'btnTextId' => 'projectReportsStatusBtnText',
                                        'fieldName' => 'status',
                                        'fieldLabel' => $lang['Project Status'] ?? 'Project Status',
                                        'options' => $projectStatusOptions,
                                        'selectedValue' => $selectedStatus,
                                        'selectedLabel' => $statusLabel,
                                    ]);
                                    ?>
                                    <div class="reports-filter-actions">
                                        <button type="submit" class="btn primary-btn d-inline-flex align-items-center col-gap-5 task-reports-filter-btn">
                                            <?php echo ts_icon('filter', 'dropdown-toggle-icon h-6'); ?>
                                            <span><?php echo $lang['Apply Filter'] ?? 'Apply Filter'; ?></span>
                                        </button>
                                        <button type="button" class="btn outline-btn d-inline-flex align-items-center col-gap-5 task-reports-filter-btn" id="projectReportsResetBtn">
                                            <?php echo ts_icon('close', 'dropdown-toggle-icon h-6'); ?>
                                            <span><?php echo $lang['Reset'] ?? 'Reset'; ?></span>
                                        </button>
                                    </div>
                                </form>
                                <div class="task-reports-active-range-wrap" id="projectReportsActiveRangeWrap">
                                    <div class="task-reports-active-range" id="projectReportsActiveRange" role="status" aria-live="polite"></div>
                                </div>
                            </div>
                        </div>

                        <div id="projectReportsCards" class="pd-bt-0 row counter-align adjust-1 pd-2 task-reports-cards" style="padding-bottom: 0 !important;">
                            <div class="col-lg col-md-4 col-6">
                                <div class="widget-card">
                                    <div class="grey"><?php echo $lang['Total Projects'] ?? 'Total projects'; ?></div>
                                    <div class="counts dash-rttb" id="cardTotalProjects">—</div>
                                    <div class="up-down" id="cardTotalProjectsArrow"></div>
                                    <div class="grey persent-count" id="cardTotalProjectsCompare"></div>
                                </div>
                            </div>
                            <div class="col-lg col-md-4 col-6">
                                <div class="widget-card">
                                    <div class="grey"><?php echo $lang['Completed Projects'] ?? 'Completed projects'; ?></div>
                                    <div class="counts dash-rttb" id="cardCompletedProjects">—</div>
                                    <div class="up-down" id="cardCompletedProjectsArrow"></div>
                                    <div class="grey persent-count" id="cardCompletedProjectsCompare"></div>
                                </div>
                            </div>
                            <div class="col-lg col-md-4 col-6">
                                <div class="widget-card">
                                    <div class="grey"><?php echo $lang['Active Projects'] ?? 'Active Projects'; ?></div>
                                    <div class="counts dash-rttb" id="cardActiveProjects">—</div>
                                    <div class="up-down" id="cardActiveProjectsArrow"></div>
                                    <div class="grey persent-count" id="cardActiveProjectsCompare"></div>
                                </div>
                            </div>
                            <div class="col-lg col-md-4 col-6">
                                <div class="widget-card">
                                    <div class="grey"><?php echo $lang['Overdue'] ?? 'Overdue'; ?></div>
                                    <div class="counts dash-rttb" id="cardOverdueProjects">—</div>
                                    <div class="up-down" id="cardOverdueProjectsArrow"></div>
                                    <div class="grey persent-count" id="cardOverdueProjectsCompare"></div>
                                </div>
                            </div>
                            <div class="col-lg col-md-4 col-6">
                                <div class="widget-card">
                                    <div class="grey"><?php echo $lang['Total Time Tracked'] ?? 'Total Time Tracked'; ?></div>
                                    <div class="counts dash-rttb" id="cardTotalTime">—</div>
                                    <div class="up-down" id="cardTotalTimeArrow"></div>
                                    <div class="grey persent-count" id="cardTotalTimeCompare"></div>
                                </div>
                            </div>
                            <div class="col-lg col-md-4 col-6">
                                <div class="widget-card">
                                    <div class="grey"><?php echo $lang['Avg Time / Project'] ?? 'Avg Time / Project'; ?></div>
                                    <div class="counts dash-rttb" id="cardAvgTimePerProject">—</div>
                                    <div class="up-down" id="cardAvgTimePerProjectArrow"></div>
                                    <div class="grey persent-count" id="cardAvgTimePerProjectCompare"></div>
                                </div>
                            </div>
                        </div>

                        <div class="row pd-2 task-reports-charts" style="padding-top: 0 !important;">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <div class="widget-card shadow center-align pie-chart h-100 reports-status-pie-card">
                                    <div class="card-title text-start w-100 mb-2">
                                        <h3 class="mb-0"><?php echo $lang['Project Status Overview'] ?? 'Project status overview'; ?></h3>
                                    </div>
                                    <div class="task-reports-bar-summary task-reports-donut-summary" id="projectReportsDonutSummary">
                                        <div class="task-reports-bar-summary-main" id="projectReportsDonutSummaryMain">—</div>
                                        <div class="task-reports-bar-summary-meta">
                                            <span class="task-reports-bar-summary-arrow" id="projectReportsDonutSummaryArrow"></span>
                                            <span class="task-reports-bar-summary-pct" id="projectReportsDonutSummaryPct"></span>
                                            <span class="task-reports-bar-summary-sep" id="projectReportsDonutSummarySep" aria-hidden="true">•</span>
                                            <span class="task-reports-bar-summary-vs" id="projectReportsDonutSummaryVs"></span>
                                        </div>
                                    </div>
                                    <div class="d-none project-reports-legend-refs" aria-hidden="true">
                                        <span id="projectReportsLegendColor-active" style="color:var(--primary-color)"></span>
                                        <span id="projectReportsLegendColor-completed" style="color:#28a745"></span>
                                        <span id="projectReportsLegendColor-overdue" style="color:#dc3545"></span>
                                    </div>
                                    <div class="chart-pie pt-4 pb-2 task-reports-pie-wrap">
                                        <canvas id="projectStatusDonutChart" width="350" height="350"></canvas>
                                        <div class="total-pro">
                                            <h2 id="projectDonutTotal">0</h2>
                                            <span><?php echo $lang['Projects'] ?? 'Projects'; ?></span>
                                        </div>
                                    </div>
                                    <div class="container counters-bottom reports-status-counters">
                                        <div class="row chart-footer justify-content-center col-gap-35 reports-status-footer">
                                            <div class="foot-c-box">
                                                <span class="task-reports-legend-num" id="legendActive">0</span>
                                                <span class="task-reports-legend-label fctxt"><?php echo $lang['Active Projects'] ?? 'Active Projects'; ?></span>
                                            </div>
                                            <div class="foot-c-box">
                                                <span class="task-reports-legend-num" id="legendCompleted">0</span>
                                                <span class="task-reports-legend-label fctxt"><?php echo $lang['Completed Projects'] ?? 'Completed projects'; ?></span>
                                            </div>
                                            <div class="foot-c-box">
                                                <span class="task-reports-legend-num" id="legendOverdue">0</span>
                                                <span class="task-reports-legend-label fctxt"><?php echo $lang['Overdue Projects'] ?? 'Overdue Projects'; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="widget-card shadow h-100">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap row-gap-10">
                                        <div class="card-title mb-0">
                                            <h3 class="mb-0"><?php echo $lang['Time Tracking Analytics'] ?? 'Time tracking analytics'; ?></h3>
                                        </div>
                                        <div class="d-flex align-items-center col-gap-10 task-reports-chart-controls">
                                            <div class="toolbar-dropdown-wrapper task-reports-chart-dropdown">
                                                <button type="button" class="border-btn-a task-reports-toolbar-toggle" id="projectBarChartUserDropdown" data-reports-dropdown="projectBarChartUserMenu" aria-expanded="false">
                                                    <span class="task-reports-filter-selected" id="projectBarChartUserBtnText"><?php echo $lang['All Staff'] ?? 'All Staff'; ?></span>
                                                    <?php echo ts_icon('chevron-down', 'icon-caret-down'); ?>
                                                </button>
                                                <div id="projectBarChartUserMenu" class="task-reports-toolbar-menu" style="min-width: 200px;">
                                                    <button type="button" class="first active" data-chart-user=""><span><?php echo $lang['All Staff'] ?? 'All Staff'; ?></span></button>
                                                    <?php
                                                    $staffCount = count($staffList);
                                                    foreach ($staffList as $i => $staff) :
                                                        $isLast = ($i === $staffCount - 1);
                                                    ?>
                                                    <button type="button" class="<?php echo $isLast ? 'last' : ''; ?>" data-chart-user="<?php echo (int)$staff->id; ?>"><span><?php echo htmlspecialchars((string)$staff->firstName); ?></span></button>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <div class="toolbar-dropdown-wrapper task-reports-chart-dropdown">
                                                <button type="button" class="border-btn-a task-reports-toolbar-toggle" id="projectBarChartGroupDropdown" data-reports-dropdown="projectBarChartGroupMenu" aria-expanded="false">
                                                    <span class="task-reports-filter-selected" id="projectBarChartGroupBtnText"><?php echo $lang['Weekly'] ?? 'Weekly'; ?></span>
                                                    <?php echo ts_icon('chevron-down', 'icon-caret-down'); ?>
                                                </button>
                                                <div id="projectBarChartGroupMenu" class="task-reports-toolbar-menu" style="min-width: 160px;">
                                                    <button type="button" class="first" data-chart-group="daily"><span><?php echo $lang['Daily'] ?? 'Daily'; ?></span></button>
                                                    <button type="button" class="active" data-chart-group="weekly"><span><?php echo $lang['Weekly'] ?? 'Weekly'; ?></span></button>
                                                    <button type="button" class="last" data-chart-group="monthly"><span><?php echo $lang['Monthly'] ?? 'Monthly'; ?></span></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="task-reports-bar-summary" id="projectReportsBarSummary">
                                        <div class="task-reports-bar-summary-main" id="projectReportsBarSummaryMain">—</div>
                                        <div class="task-reports-bar-summary-meta">
                                            <span class="task-reports-bar-summary-arrow" id="projectReportsBarSummaryArrow"></span>
                                            <span class="task-reports-bar-summary-pct" id="projectReportsBarSummaryPct"></span>
                                            <span class="task-reports-bar-summary-sep" id="projectReportsBarSummarySep" aria-hidden="true">•</span>
                                            <span class="task-reports-bar-summary-vs" id="projectReportsBarSummaryVs"></span>
                                        </div>
                                    </div>
                                    <div class="task-reports-bar-wrap">
                                        <canvas id="projectTimeBarChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pd-2 card plan-card">
                            <div class="page-title">
                                <h2><?php echo $lang['Project Performance'] ?? 'Project Performance'; ?></h2>
                            </div>
                            <div class="table-responsive scroll-x vh-100">
                                <table class="table table-new projectspage project-reports-performance-table" data-pagination="true" data-page-size="5">
                                    <thead>
                                        <tr>
                                            <th><?php echo $lang['No'] ?? 'No'; ?></th>
                                            <th class="project-reports-project-col"><?php echo $lang['Project'] ?? 'Project'; ?></th>
                                            <th><?php echo $lang['Client'] ?? 'Client'; ?></th>
                                            <th><?php echo $lang['Total Tasks'] ?? 'Total tasks'; ?></th>
                                            <th><?php echo $lang['Completed'] ?? 'Completed'; ?></th>
                                            <th><?php echo $lang['Total Time'] ?? 'Total Time'; ?></th>
                                            <th><?php echo $lang['Avg Time / Project'] ?? 'Avg Time / Project'; ?></th>
                                            <th><?php echo $lang['Completion %'] ?? 'Completion %'; ?></th>
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
