<?php
/**
 * Reports dashboard skeleton (task-sidebar shimmer style).
 * Expects $reportsSkeletonVariant: task | projects | invoice
 */
$reportsSkeletonVariant = isset($reportsSkeletonVariant) ? (string)$reportsSkeletonVariant : 'task';
$tableCols = 10;
$legendItems = 3;
$nameColWithAvatar = ($reportsSkeletonVariant === 'task');
if ($reportsSkeletonVariant === 'projects') {
    $tableCols = 9;
} elseif ($reportsSkeletonVariant === 'invoice') {
    $tableCols = 8;
    $legendItems = 4;
} elseif ($reportsSkeletonVariant === 'ecommerce') {
    $tableCols = 7;
    $legendItems = 3;
} elseif ($reportsSkeletonVariant === 'ecommerce-dashboard') {
    $tableCols = 6;
    $legendItems = 4;
}
?>
<div class="reports-dashboard-skeleton reports-dashboard-skeleton--<?php echo htmlspecialchars($reportsSkeletonVariant, ENT_QUOTES, 'UTF-8'); ?>" id="reportsDashboardSkeleton" aria-hidden="true">
    <div class="reports-skel-row reports-skel-row--filters reports-toolbar-panel">
        <div class="reports-skel-filters d-flex justify-content-between align-items-end flex-wrap row-gap-10">
            <div class="reports-skel-filters-fields d-flex align-items-end col-gap-10 flex-wrap flex-grow-1">
                <?php for ($i = 0; $i < 5; $i++) : ?>
                <div class="reports-skel-filter-field">
                    <div class="reports-skel skel reports-skel-input"></div>
                </div>
                <?php endfor; ?>
            </div>
            <div class="reports-filter-actions reports-skel-filter-actions">
                <div class="reports-skel skel reports-skel-btn"></div>
                <div class="reports-skel skel reports-skel-btn reports-skel-btn--outline"></div>
            </div>
            <div class="reports-skel skel reports-skel-date-range"></div>
        </div>
    </div>

    <div class="row counter-align adjust-1 reports-skel-row reports-skel-row--cards task-reports-cards">
        <?php for ($c = 0; $c < 6; $c++) : ?>
        <div class="col-lg col-md-4 col-6">
            <div class="widget-card reports-skel-card">
                <div class="reports-skel skel reports-skel-card-title"></div>
                <div class="reports-skel skel reports-skel-card-value"></div>
                <div class="reports-skel skel reports-skel-card-meta"></div>
            </div>
        </div>
        <?php endfor; ?>
    </div>

    <div class="row pd-2 task-reports-charts reports-skel-row reports-skel-row--charts">
        <div class="col-md-4 mb-3 mb-md-0">
            <div class="widget-card shadow h-100 reports-skel-chart-card">
                <div class="reports-skel skel reports-skel-chart-title"></div>
                <div class="reports-skel skel reports-skel-chart-sub"></div>
                <div class="reports-skel-donut-wrap">
                    <div class="reports-skel skel reports-skel-donut"></div>
                </div>
                <div class="reports-skel-legend row">
                    <?php for ($l = 0; $l < $legendItems; $l++) : ?>
                    <div class="col reports-skel-legend-item">
                        <div class="reports-skel skel reports-skel-legend-num"></div>
                        <div class="reports-skel skel reports-skel-legend-label"></div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="widget-card shadow h-100 reports-skel-chart-card">
                <div class="d-flex justify-content-between align-items-start flex-wrap row-gap-10 mb-3">
                    <div class="reports-skel skel reports-skel-chart-title reports-skel-chart-title--wide"></div>
                    <div class="d-flex col-gap-10">
                        <div class="reports-skel skel reports-skel-chip"></div>
                        <div class="reports-skel skel reports-skel-chip"></div>
                    </div>
                </div>
                <div class="reports-skel skel reports-skel-chart-sub reports-skel-chart-sub--narrow"></div>
                <div class="reports-skel skel reports-skel-bar-area"></div>
            </div>
        </div>
    </div>

    <div class="pd-2 card plan-card reports-skel-table-card">
        <div class="reports-skel skel reports-skel-section-title"></div>
        <div class="table-responsive scroll-x">
            <table class="table table-new projectspage reports-skel-table">
                <thead>
                    <tr>
                        <?php for ($th = 0; $th < $tableCols; $th++) : ?>
                        <th><span class="reports-skel skel reports-skel-th"></span></th>
                        <?php endfor; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($r = 0; $r < 5; $r++) : ?>
                    <tr>
                        <td><span class="reports-skel skel reports-skel-td-sm"></span></td>
                        <td>
                            <?php if ($nameColWithAvatar) : ?>
                            <div class="d-flex align-items-center col-gap-10">
                                <span class="reports-skel skel reports-skel-avatar"></span>
                                <span class="reports-skel skel reports-skel-td-name"></span>
                            </div>
                            <?php else : ?>
                            <span class="reports-skel skel reports-skel-td-name reports-skel-td-name--wide"></span>
                            <?php endif; ?>
                        </td>
                        <?php for ($td = 2; $td < $tableCols - 1; $td++) : ?>
                        <td>
                            <?php if ($td === $tableCols - 2 && $reportsSkeletonVariant !== 'invoice') : ?>
                            <div class="reports-skel-progress-cell">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="reports-skel skel reports-skel-td-xs"></span>
                                    <span class="reports-skel skel reports-skel-td-xs"></span>
                                </div>
                                <span class="reports-skel skel reports-skel-progress"></span>
                            </div>
                            <?php else : ?>
                            <span class="reports-skel skel reports-skel-td-md"></span>
                            <?php endif; ?>
                        </td>
                        <?php endfor; ?>
                        <td><span class="reports-skel skel reports-skel-td-action"></span></td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
