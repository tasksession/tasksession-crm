<?php
/**
 * Admin dashboard skeleton — mirrors admin/index.php layout.
 * Reuses reports-skel shimmer classes from reports.css.
 */

$showAiDashSkel = false;
if (!function_exists('comon_ai_module_enabled')) {
    $gate = dirname(__DIR__) . '/includes/ai_module_gate.php';
    if (is_file($gate)) {
        require_once $gate;
    }
}
if (!function_exists('comon_ai_tables_exist')) {
    $mig = dirname(__DIR__) . '/includes/ai_migration_helper.php';
    if (is_file($mig)) {
        require_once $mig;
    }
}
if (
    function_exists('comon_ai_module_enabled')
    && comon_ai_module_enabled()
    && function_exists('has_permission')
    && isset($connect)
    && $connect instanceof mysqli
    && function_exists('comon_ai_tables_exist')
    && comon_ai_tables_exist($connect)
    && (
        has_permission('ai_daily_brief')
        || has_permission('ai_agents')
        || has_permission('ai_access')
    )
) {
    $showAiDashSkel = true;
}
?>
<div class="reports-dashboard-skeleton reports-dashboard-skeleton--admin-dashboard" id="reportsDashboardSkeleton" aria-hidden="true">
    <?php if ($showAiDashSkel): ?>
    <div class="widget-card reports-skel-ai-dash">
        <div class="reports-skel-ai-dash__head">
            <div class="reports-skel-ai-dash__intro">
                <div class="reports-skel skel reports-skel-ai-dash__icon"></div>
                <div class="reports-skel-ai-dash__titles">
                    <div class="reports-skel skel reports-skel-ai-dash__title"></div>
                    <div class="reports-skel skel reports-skel-ai-dash__sub"></div>
                </div>
            </div>
            <div class="reports-skel-ai-dash__actions">
                <div class="reports-skel skel reports-skel-ai-dash__pill"></div>
                <div class="reports-skel skel reports-skel-ai-dash__pill"></div>
            </div>
        </div>
        <div class="reports-skel skel reports-skel-ai-dash__rule"></div>
        <div class="reports-skel-ai-dash__body">
            <div class="reports-skel-ai-dash__chips">
                <div class="reports-skel skel reports-skel-ai-dash__chip"></div>
                <div class="reports-skel skel reports-skel-ai-dash__chip"></div>
                <div class="reports-skel skel reports-skel-ai-dash__chip reports-skel-ai-dash__chip--short"></div>
            </div>
        </div>
        <div class="reports-skel skel reports-skel-ai-dash__spark" aria-hidden="true"></div>
    </div>
    <?php endif; ?>
    <div class="row">
        <div class="col-md-12 col-lg-4">
            <div class="widget-card shadow center-align pie-chart reports-skel-admin-pie-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="card-title">
                        <div class="reports-skel skel reports-skel-admin-pie-heading"></div>
                    </div>
                    <div class="reports-skel skel reports-skel-admin-view-btn"></div>
                </div>
                <div class="chart-pie pt-4 pb-2 reports-skel-admin-chart-pie">
                    <div class="reports-skel-admin-donut-wrap">
                        <div class="reports-skel skel reports-skel-admin-donut-ring" aria-hidden="true"></div>
                        <div class="reports-skel-admin-donut-center">
                            <div class="reports-skel skel reports-skel-admin-donut-value"></div>
                            <div class="reports-skel skel reports-skel-admin-donut-label"></div>
                        </div>
                    </div>
                </div>
                <div class="container counters-bottom">
                    <div class="row chart-footer justify-content-center col-gap-35 full-col-gap-35-sep">
                        <div class="foot-c-box">
                            <div class="reports-skel skel reports-skel-admin-foot-stat"></div>
                        </div>
                        <div class="foot-c-box green">
                            <div class="reports-skel skel reports-skel-admin-foot-stat"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row counter-align">
                <div class="col-sm-6 col-6">
                    <div class="widget-card reports-skel-admin-mini-card">
                        <div class="reports-skel skel reports-skel-card-title"></div>
                        <div class="reports-skel skel reports-skel-card-value"></div>
                        <div class="reports-skel skel reports-skel-admin-mini-trend"></div>
                        <div class="reports-skel skel reports-skel-card-meta"></div>
                    </div>
                </div>
                <div class="col-sm-6 col-6">
                    <div class="widget-card reports-skel-admin-mini-card">
                        <div class="reports-skel skel reports-skel-card-title"></div>
                        <div class="reports-skel skel reports-skel-card-value"></div>
                        <div class="reports-skel skel reports-skel-admin-mini-trend"></div>
                        <div class="reports-skel skel reports-skel-card-meta"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-12 col-lg-8">
            <div class="row counter-align">
                <div class="col-lg-3 col-sm-6 col-6">
                    <div class="widget-card dash-counter reports-skel-admin-counter-card stat-spark-card">
                        <div class="reports-skel skel reports-skel-card-title"></div>
                        <div class="reports-skel skel reports-skel-card-value"></div>
                        <div class="reports-skel skel reports-skel-admin-counter-spark"></div>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6 col-6">
                    <div class="widget-card dash-counter reports-skel-admin-counter-card stat-spark-card">
                        <div class="reports-skel skel reports-skel-card-title"></div>
                        <div class="reports-skel skel reports-skel-card-value"></div>
                        <div class="reports-skel skel reports-skel-admin-counter-spark"></div>
                    </div>
                </div>
                <div class="col-lg-6 col-sm-12 col-12">
                    <div class="widget-card dash-counter reports-skel-admin-counter-card stat-spark-card stat-spark-card--invoice-overview">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="reports-skel skel reports-skel-card-title"></div>
                            <div class="reports-skel skel reports-skel-chip"></div>
                        </div>
                        <div class="d-flex col-gap-20 mb-2">
                            <div class="flex-grow-1">
                                <div class="reports-skel skel reports-skel-card-value"></div>
                                <div class="reports-skel skel reports-skel-card-meta"></div>
                            </div>
                            <div class="flex-grow-1">
                                <div class="reports-skel skel reports-skel-card-value"></div>
                                <div class="reports-skel skel reports-skel-card-meta"></div>
                            </div>
                        </div>
                        <div class="reports-skel skel reports-skel-admin-counter-spark"></div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="widget-card shadow reports-skel-chart-card reports-skel-admin-sales-card">
                        <div class="d-flex justify-content-between align-items-center flex-wrap row-gap-10 mb-2">
                            <div class="reports-skel skel reports-skel-chart-title"></div>
                            <div class="d-flex col-gap-10">
                                <div class="reports-skel skel reports-skel-chip"></div>
                                <div class="reports-skel skel reports-skel-chip"></div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-start flex-wrap row-gap-10 mb-3">
                            <div class="reports-skel skel reports-skel-admin-sales-summary"></div>
                            <div class="d-flex col-gap-10">
                                <div class="reports-skel skel reports-skel-admin-legend-dot"></div>
                                <div class="reports-skel skel reports-skel-admin-legend-dot"></div>
                            </div>
                        </div>
                        <div class="reports-skel skel reports-skel-bar-area"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row db-container">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="db-box-wrap widget-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="reports-skel skel reports-skel-section-title"></div>
                    <div class="reports-skel skel reports-skel-admin-view-btn"></div>
                </div>
                <div class="d-flex col-gap-20 reports-skel-admin-projects-row">
                    <?php for ($p = 0; $p < 5; $p++) : ?>
                    <div class="project-x">
                        <div class="widget-card reports-skel-admin-project-card">
                            <div class="reports-skel skel reports-skel-admin-project-badge"></div>
                            <div class="reports-skel skel reports-skel-admin-project-title"></div>
                            <div class="d-flex col-gap-35 mb-3">
                                <div class="reports-skel-admin-project-team">
                                    <div class="reports-skel skel reports-skel-card-meta mb-2"></div>
                                    <div class="d-flex col-gap-5">
                                        <span class="reports-skel skel reports-skel-avatar"></span>
                                        <span class="reports-skel skel reports-skel-avatar"></span>
                                        <span class="reports-skel skel reports-skel-avatar"></span>
                                    </div>
                                </div>
                                <div class="reports-skel-admin-project-team">
                                    <div class="reports-skel skel reports-skel-card-meta mb-2"></div>
                                    <div class="d-flex col-gap-5">
                                        <span class="reports-skel skel reports-skel-avatar"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="reports-skel skel reports-skel-progress mb-2"></div>
                            <div class="reports-skel skel reports-skel-card-meta"></div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row align-items-stretch">
        <div class="col-lg-5 col-md-6 col-sm-12 d-flex">
            <div class="widget-card flex-grow reports-skel-admin-panel">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="reports-skel skel reports-skel-section-title"></div>
                    <div class="reports-skel skel reports-skel-admin-view-btn"></div>
                </div>
                <?php for ($t = 0; $t < 4; $t++) : ?>
                <div class="reports-skel-admin-list-row d-flex align-items-center">
                    <div class="flex-grow">
                        <div class="reports-skel skel reports-skel-td-name mb-2"></div>
                        <div class="reports-skel skel reports-skel-card-meta"></div>
                    </div>
                    <div class="d-flex col-gap-5">
                        <span class="reports-skel skel reports-skel-avatar"></span>
                        <span class="reports-skel skel reports-skel-avatar"></span>
                    </div>
                    <div class="reports-skel skel reports-skel-admin-list-action ms-2"></div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
        <div class="col-lg-4 col-md-6 col-sm-12 d-flex">
            <div class="widget-card flex-grow reports-skel-admin-panel">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="reports-skel skel reports-skel-section-title"></div>
                    <div class="reports-skel skel reports-skel-admin-view-btn"></div>
                </div>
                <?php for ($n = 0; $n < 4; $n++) : ?>
                <div class="reports-skel-admin-list-row d-flex align-items-center justify-content-between">
                    <div class="flex-grow">
                        <div class="reports-skel skel reports-skel-td-name mb-2"></div>
                        <div class="reports-skel skel reports-skel-card-meta"></div>
                    </div>
                    <div class="reports-skel skel reports-skel-admin-list-action"></div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
        <div class="col-lg-3 col-md-12 col-sm-12 d-flex">
            <div class="widget-card flex-grow reports-skel-admin-panel">
                <div class="reports-skel skel reports-skel-section-title mb-4"></div>
                <div class="reports-skel skel reports-skel-input reports-skel-admin-currency-field mb-3"></div>
                <div class="reports-skel skel reports-skel-input reports-skel-admin-currency-field mb-3"></div>
                <div class="reports-skel skel reports-skel-btn reports-skel-admin-currency-btn"></div>
            </div>
        </div>
    </div>
</div>
