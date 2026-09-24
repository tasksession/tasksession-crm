<?php
if (!isset($url) || !isset($integrations) || !isset($formsList)) {
    return;
}

require_once dirname(__DIR__, 2) . '/reports_common_helper.php';

$integration_id = isset($integration_id) ? (int) $integration_id : 0;
$form_id = isset($form_id) ? (int) $form_id : 0;
$canAddMapping = !empty($canAddMapping);
$showAddMappingPanel = !empty($showAddMappingPanel);
$lang = isset($lang) ? $lang : [];

$integrationOptions = [];
if (empty($integrations)) {
    $integrationOptions[] = [
        'value' => '',
        'label' => $lang['No integrations — create one first'] ?? 'No integrations — create one first',
    ];
} else {
    foreach ($integrations as $i) {
        $integrationOptions[] = [
            'value' => (string) (int) $i['id'],
            'label' => (string) $i['name'],
        ];
    }
}

$formOptions = [
    ['value' => '', 'label' => $lang['All forms'] ?? 'All forms'],
];
foreach ($formsList as $formRow) {
    $formOptions[] = [
        'value' => (string) (int) $formRow['id'],
        'label' => (string) ($formRow['name'] ?? ('Form #' . (int) $formRow['id'])),
    ];
}

$integrationSelectedValue = $integration_id > 0 ? (string) $integration_id : '';
$formSelectedValue = $form_id > 0 ? (string) $form_id : '';

$integrationLabel = $integrationOptions[0]['label'];
foreach ($integrationOptions as $opt) {
    if ($opt['value'] === $integrationSelectedValue) {
        $integrationLabel = $opt['label'];
        break;
    }
}

$formLabel = $formOptions[0]['label'];
foreach ($formOptions as $opt) {
    if ($opt['value'] === $formSelectedValue) {
        $formLabel = $opt['label'];
        break;
    }
}
?>
<div class="reports-page attendance-page forms-reports-filters-wrap forms-mappings-filters-wrap">
    <div class="reports-filters-panel pd-bt-0 forms-reports-filters pd-0">
        <div class="filters-row d-flex justify-content-between align-items-start flex-wrap row-gap-10 pd-bt-0 pd-0">
            <form
                id="formsMappingsFilterForm"
                method="get"
                action="<?php echo FormsSecurityHelper::escape($url); ?>admin/forms_mappings"
                class="d-flex align-items-end col-gap-10 flex-wrap task-reports-filters-form"
            >
                <?php
                reports_render_toolbar_dropdown([
                    'inputId' => 'formsMappingsIntegrationId',
                    'menuId' => 'formsMappingsIntegrationIdDropdown',
                    'btnTextId' => 'formsMappingsIntegrationIdBtnText',
                    'fieldName' => 'integration_id',
                    'fieldLabel' => $lang['Integration'] ?? 'Integration',
                    'options' => $integrationOptions,
                    'selectedValue' => $integrationSelectedValue,
                    'selectedLabel' => $integrationLabel,
                    'searchable' => count($integrationOptions) > 8,
                ]);
                reports_render_toolbar_dropdown([
                    'inputId' => 'formsMappingsFormId',
                    'menuId' => 'formsMappingsFormIdDropdown',
                    'btnTextId' => 'formsMappingsFormIdBtnText',
                    'fieldName' => 'form_id',
                    'fieldLabel' => $lang['Form'] ?? 'Form',
                    'options' => $formOptions,
                    'selectedValue' => $formSelectedValue,
                    'selectedLabel' => $formLabel,
                    'searchable' => count($formOptions) > 8,
                ]);
                ?>
                <div class="reports-filter-actions">
                    <button type="submit" class="btn primary-btn d-inline-flex align-items-center col-gap-5 task-reports-filter-btn">
                        <span><?php echo htmlspecialchars($lang['Filter'] ?? 'Filter', ENT_QUOTES, 'UTF-8'); ?></span>
                    </button>
                </div>
            </form>
            <?php if ($canAddMapping): ?>
            <div class="forms-mappings-toolbar-actions">
                <button
                    type="button"
                    class="primary-btn forms-mapping-add-toggle"
                    data-bs-toggle="collapse"
                    data-bs-target="#forms-mapping-add-panel"
                    aria-expanded="<?php echo $showAddMappingPanel ? 'true' : 'false'; ?>"
                    aria-controls="forms-mapping-add-panel"
                ><?php echo ts_icon('plus'); ?><span>Add new mapping</span></button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
