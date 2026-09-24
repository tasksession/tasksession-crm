<?php
if (!isset($url) || !isset($formsList) || !isset($integrations)) {
    return;
}

require_once dirname(__DIR__, 2) . '/reports_common_helper.php';

$formsSummFormId = isset($formsSummFormId) ? (int) $formsSummFormId : 0;
$integrationIdVal = isset($integrationId) && $integrationId !== null ? (int) $integrationId : 0;
$statusVal = isset($status) ? trim((string) $status) : '';
$dateFrom = isset($dateFrom) ? trim((string) $dateFrom) : '';
$dateTo = isset($dateTo) ? trim((string) $dateTo) : '';
$lang = isset($lang) ? $lang : [];

$formOptions = [
    ['value' => '', 'label' => $lang['All forms'] ?? 'All forms'],
];
foreach ($formsList as $formRow) {
    $formOptions[] = [
        'value' => (string) (int) $formRow['id'],
        'label' => (string) ($formRow['name'] ?? ('Form #' . (int) $formRow['id'])),
    ];
}

$integrationOptions = [
    ['value' => '', 'label' => $lang['All integrations'] ?? 'All integrations'],
];
foreach ($integrations as $i) {
    $integrationOptions[] = [
        'value' => (string) (int) $i['id'],
        'label' => (string) $i['name'],
    ];
}

$statusOptions = [
    ['value' => '', 'label' => $lang['All statuses'] ?? 'All statuses'],
    ['value' => 'success', 'label' => $lang['Success'] ?? 'Success'],
    ['value' => 'failed', 'label' => $lang['Failed'] ?? 'Failed'],
];

$formSelectedValue = $formsSummFormId > 0 ? (string) $formsSummFormId : '';
$integrationSelectedValue = $integrationIdVal > 0 ? (string) $integrationIdVal : '';

$formLabel = $formOptions[0]['label'];
foreach ($formOptions as $opt) {
    if ($opt['value'] === $formSelectedValue) {
        $formLabel = $opt['label'];
        break;
    }
}

$integrationLabel = $integrationOptions[0]['label'];
foreach ($integrationOptions as $opt) {
    if ($opt['value'] === $integrationSelectedValue) {
        $integrationLabel = $opt['label'];
        break;
    }
}

$statusLabel = $statusOptions[0]['label'];
foreach ($statusOptions as $opt) {
    if ($opt['value'] === $statusVal) {
        $statusLabel = $opt['label'];
        break;
    }
}

?>
<div class="reports-page attendance-page forms-reports-filters-wrap">
    <div class="reports-filters-panel pd-bt-0 forms-reports-filters pd-0">
        <div class="filters-row d-flex justify-content-between align-items-start flex-wrap row-gap-10 pd-bt-0 pd-0">
            <form
                id="formsSubmissionsFilterForm"
                method="get"
                action="<?php echo FormsSecurityHelper::escape($url); ?>admin/forms_submissions"
                class="d-flex align-items-end col-gap-10 flex-wrap task-reports-filters-form"
            >
                <?php
                reports_render_toolbar_dropdown([
                    'inputId' => 'formsSubmissionsFormId',
                    'menuId' => 'formsSubmissionsFormIdDropdown',
                    'btnTextId' => 'formsSubmissionsFormIdBtnText',
                    'fieldName' => 'form_id',
                    'fieldLabel' => $lang['Form'] ?? 'Form',
                    'options' => $formOptions,
                    'selectedValue' => $formSelectedValue,
                    'selectedLabel' => $formLabel,
                    'searchable' => count($formOptions) > 8,
                ]);
                reports_render_toolbar_dropdown([
                    'inputId' => 'formsSubmissionsIntegrationId',
                    'menuId' => 'formsSubmissionsIntegrationIdDropdown',
                    'btnTextId' => 'formsSubmissionsIntegrationIdBtnText',
                    'fieldName' => 'integration_id',
                    'fieldLabel' => $lang['Integration'] ?? 'Integration',
                    'options' => $integrationOptions,
                    'selectedValue' => $integrationSelectedValue,
                    'selectedLabel' => $integrationLabel,
                    'searchable' => count($integrationOptions) > 8,
                ]);
                reports_render_toolbar_dropdown([
                    'inputId' => 'formsSubmissionsStatus',
                    'menuId' => 'formsSubmissionsStatusDropdown',
                    'btnTextId' => 'formsSubmissionsStatusBtnText',
                    'fieldName' => 'status',
                    'fieldLabel' => $lang['Status'] ?? 'Status',
                    'options' => $statusOptions,
                    'selectedValue' => $statusVal,
                    'selectedLabel' => $statusLabel,
                ]);
                ?>
                <div class="floating-filter-field">
                    <label class="floating-label" for="formsSubmissionsDateFrom"><?php echo htmlspecialchars($lang['Start Date'] ?? 'Start date', ENT_QUOTES, 'UTF-8'); ?></label>
                    <input type="date" name="date_from" id="formsSubmissionsDateFrom" class="form-control" value="<?php echo FormsSecurityHelper::escape($dateFrom); ?>">
                </div>
                <div class="floating-filter-field">
                    <label class="floating-label" for="formsSubmissionsDateTo"><?php echo htmlspecialchars($lang['End Date'] ?? 'End Date', ENT_QUOTES, 'UTF-8'); ?></label>
                    <input type="date" name="date_to" id="formsSubmissionsDateTo" class="form-control" value="<?php echo FormsSecurityHelper::escape($dateTo); ?>">
                </div>
                <div class="reports-filter-actions">
                    <button type="submit" class="btn primary-btn d-inline-flex align-items-center col-gap-5 task-reports-filter-btn">
                        <span><?php echo htmlspecialchars($lang['Filter'] ?? 'Filter', ENT_QUOTES, 'UTF-8'); ?></span>
                    </button>
                </div>
            </form>
            <?php if (!empty($aiPrefillButtons)): ?>
            <div class="forms-submissions-ai-actions">
                <?php include dirname(__DIR__, 3) . '/templates/partials/ai-prefill-buttons.php'; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
