<?php
if (!isset($url)) {
    return;
}

require_once dirname(__DIR__, 2) . '/reports_common_helper.php';

$level = isset($level) ? trim((string) $level) : '';
$source = isset($source) ? trim((string) $source) : '';
$requestId = isset($requestId) ? trim((string) $requestId) : '';
$dateFrom = isset($dateFrom) ? trim((string) $dateFrom) : '';
$dateTo = isset($dateTo) ? trim((string) $dateTo) : '';
$lang = isset($lang) ? $lang : [];

$levelOptions = [
    ['value' => '', 'label' => $lang['All levels'] ?? 'All levels'],
    ['value' => 'info', 'label' => 'info'],
    ['value' => 'warning', 'label' => 'warning'],
    ['value' => 'error', 'label' => 'error'],
    ['value' => 'debug', 'label' => 'debug'],
];

$levelLabel = $levelOptions[0]['label'];
foreach ($levelOptions as $opt) {
    if ($opt['value'] === $level) {
        $levelLabel = $opt['label'];
        break;
    }
}
?>
<div class="reports-page attendance-page forms-reports-filters-wrap">
    <div class="reports-filters-panel pd-bt-0 forms-reports-filters pd-0">
        <div class="filters-row d-flex justify-content-between align-items-start flex-wrap row-gap-10 pd-bt-0 pd-0">
            <form
                id="formsDebugFilterForm"
                method="get"
                action="<?php echo FormsSecurityHelper::escape($url); ?>admin/forms_debug"
                class="d-flex align-items-end col-gap-10 flex-wrap task-reports-filters-form"
            >
                <?php
                reports_render_toolbar_dropdown([
                    'inputId' => 'formsDebugLevel',
                    'menuId' => 'formsDebugLevelDropdown',
                    'btnTextId' => 'formsDebugLevelBtnText',
                    'fieldName' => 'level',
                    'fieldLabel' => $lang['Level'] ?? 'Level',
                    'options' => $levelOptions,
                    'selectedValue' => $level,
                    'selectedLabel' => $levelLabel,
                ]);
                ?>
                <div class="floating-filter-field forms-reports-filter-text">
                    <label class="floating-label" for="formsDebugSource"><?php echo htmlspecialchars($lang['Source'] ?? 'Source', ENT_QUOTES, 'UTF-8'); ?></label>
                    <input type="text" name="source" id="formsDebugSource" class="form-control" placeholder="Source" value="<?php echo FormsSecurityHelper::escape($source); ?>">
                </div>
                <div class="floating-filter-field forms-reports-filter-text">
                    <label class="floating-label" for="formsDebugRequestId"><?php echo htmlspecialchars($lang['Request ID'] ?? 'Request ID', ENT_QUOTES, 'UTF-8'); ?></label>
                    <input type="text" name="request_id" id="formsDebugRequestId" class="form-control" placeholder="Request ID" value="<?php echo FormsSecurityHelper::escape($requestId); ?>">
                </div>
                <div class="floating-filter-field">
                    <label class="floating-label" for="formsDebugDateFrom"><?php echo htmlspecialchars($lang['Start Date'] ?? 'Start date', ENT_QUOTES, 'UTF-8'); ?></label>
                    <input type="date" name="date_from" id="formsDebugDateFrom" class="form-control" value="<?php echo FormsSecurityHelper::escape($dateFrom); ?>">
                </div>
                <div class="floating-filter-field">
                    <label class="floating-label" for="formsDebugDateTo"><?php echo htmlspecialchars($lang['End Date'] ?? 'End Date', ENT_QUOTES, 'UTF-8'); ?></label>
                    <input type="date" name="date_to" id="formsDebugDateTo" class="form-control" value="<?php echo FormsSecurityHelper::escape($dateTo); ?>">
                </div>
                <div class="reports-filter-actions">
                    <button type="submit" class="btn primary-btn d-inline-flex align-items-center col-gap-5 task-reports-filter-btn">
                        <span><?php echo htmlspecialchars($lang['Filter'] ?? 'Filter', ENT_QUOTES, 'UTF-8'); ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
