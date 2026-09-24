<?php
/**
 * Reusable list bulk-select toolbar + search (media-vault / documents pattern).
 */

/**
 * @param array<string,mixed> $options
 *   instance_id (string), bulk_label, back_label, select_all_label, delete_label,
 *   show_delete (bool), show_archive (bool), archive_label,
 *   archive_icon (unlink|archive), show_secondary (bool), secondary_label,
 *   secondary_icon (delete|retry), extra_class, action_icon (delete|retry)
 * @return string
 */
function tasksession_list_bulk_toolbar_html(array $options = array())
{
    global $lang;
    $instanceId = isset($options['instance_id']) ? (string) $options['instance_id'] : 'default';
    $idPrefix = 'tsListBulk_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $instanceId) . '_';
    $bulkLabel = isset($options['bulk_label'])
        ? (string) $options['bulk_label']
        : ($lang['Bulk Select'] ?? 'Bulk Select');
    $backLabel = isset($options['back_label'])
        ? (string) $options['back_label']
        : ($lang['Back'] ?? 'Back');
    $selectAllLabel = isset($options['select_all_label'])
        ? (string) $options['select_all_label']
        : ($lang['Select all'] ?? 'Select all');
    $deleteLabel = isset($options['delete_label'])
        ? (string) $options['delete_label']
        : ($lang['Delete'] ?? 'Delete');
    $archiveLabel = isset($options['archive_label'])
        ? (string) $options['archive_label']
        : ($lang['Archive'] ?? 'Archive');
    $showDelete = !isset($options['show_delete']) || !empty($options['show_delete']);
    $showArchive = !empty($options['show_archive']);
    $showSecondary = !empty($options['show_secondary']);
    $secondaryLabel = isset($options['secondary_label'])
        ? (string) $options['secondary_label']
        : ($lang['Delete'] ?? 'Delete');
    $extraClass = isset($options['extra_class']) ? (string) $options['extra_class'] : '';
    $actionIcon = isset($options['action_icon']) ? (string) $options['action_icon'] : 'delete';
    $archiveIcon = isset($options['archive_icon']) ? (string) $options['archive_icon'] : 'archive';
    $secondaryIcon = isset($options['secondary_icon']) ? (string) $options['secondary_icon'] : 'delete';
    $instanceEsc = htmlspecialchars($instanceId, ENT_QUOTES, 'UTF-8');
    $actionIconSvg = function_exists('ts_icon')
        ? ts_icon($actionIcon === 'retry' ? 'refresh' : 'delete', 'w-2')
        : '';
    $archiveIconSvg = function_exists('ts_icon')
        ? ts_icon($archiveIcon === 'unlink' ? 'unlink' : 'archive-box', 'w-2')
        : '';
    $secondaryIconSvg = function_exists('ts_icon')
        ? ts_icon($secondaryIcon === 'retry' ? 'refresh' : 'delete', 'w-2')
        : '';

    ob_start();
    ?>
    <div class="edit-overview-btn ts-list-bulk-toolbar-wrap d-none d-md-block<?php echo $extraClass !== '' ? ' ' . htmlspecialchars($extraClass, ENT_QUOTES, 'UTF-8') : ''; ?>">
        <div class="icon-container sep ts-list-bulk-toolbar" data-ts-list-bulk-toolbar="<?php echo $instanceEsc; ?>">
            <div class="pm-trash task-trash align-middle d-flex col-gap-5">
            <a href="#"
               onclick="TsListBulk.enter('<?php echo $instanceEsc; ?>'); return false;"
               class="bulk-delete-tab red border-btn-a"
               id="<?php echo $idPrefix; ?>bulkSelectTab"
               title="<?php echo htmlspecialchars($bulkLabel, ENT_QUOTES, 'UTF-8'); ?>"
               aria-label="<?php echo htmlspecialchars($bulkLabel, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo function_exists('ts_icon') ? ts_icon('duplicate', 'w-2') : ''; ?>
            </a>
            <a href="#"
               onclick="TsListBulk.exit('<?php echo $instanceEsc; ?>'); return false;"
               class="bulk-delete-tab border-btn-a"
               id="<?php echo $idPrefix; ?>bulkBackTab"
               style="display:none;">
                <?php echo function_exists('ts_icon') ? ts_icon('arrow-left', 'w-2') : ''; ?>
                <span><?php echo htmlspecialchars($backLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
            <a href="#"
               onclick="TsListBulk.selectAll('<?php echo $instanceEsc; ?>'); return false;"
               class="bulk-delete-tab border-btn-a"
               id="<?php echo $idPrefix; ?>bulkSelectAllTab"
               style="display:none;">
                <?php echo function_exists('ts_icon') ? ts_icon('check-circle', 'w-2') : ''; ?>
                <span id="<?php echo $idPrefix; ?>bulkSelectAllLabel"><?php echo htmlspecialchars($selectAllLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
            <?php if ($showArchive) : ?>
            <a href="#"
               onclick="TsListBulk.archiveSelected('<?php echo $instanceEsc; ?>'); return false;"
               class="bulk-delete-tab border-btn-a"
               id="<?php echo $idPrefix; ?>bulkArchiveTab"
               style="display:none;">
                <?php echo $archiveIconSvg; ?>
                <span id="<?php echo $idPrefix; ?>bulkArchiveLabel"><?php echo htmlspecialchars($archiveLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
            <?php endif; ?>
            <?php if ($showDelete) : ?>
            <a href="#"
               onclick="TsListBulk.deleteSelected('<?php echo $instanceEsc; ?>'); return false;"
               class="bulk-delete-tab border-btn-a"
               id="<?php echo $idPrefix; ?>bulkDeleteTab"
               style="display:none;">
                <?php echo $actionIconSvg; ?>
                <span id="<?php echo $idPrefix; ?>bulkDeleteLabel"><?php echo htmlspecialchars($deleteLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
            <?php endif; ?>
            <?php if ($showSecondary) : ?>
            <a href="#"
               onclick="TsListBulk.secondarySelected('<?php echo $instanceEsc; ?>'); return false;"
               class="bulk-delete-tab border-btn-a"
               id="<?php echo $idPrefix; ?>bulkSecondaryTab"
               style="display:none;">
                <?php echo $secondaryIconSvg; ?>
                <span id="<?php echo $idPrefix; ?>bulkSecondaryLabel"><?php echo htmlspecialchars($secondaryLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
            <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * @param array<string,mixed> $options
 *   action, method, placeholder, value, form_id, input_id, hidden_fields (array), clear_url
 * @return string
 */
function tasksession_list_search_toolbar_html(array $options = array())
{
    global $lang;
    $action = isset($options['action']) ? (string) $options['action'] : '';
    $method = isset($options['method']) ? (string) $options['method'] : 'get';
    $placeholder = isset($options['placeholder'])
        ? (string) $options['placeholder']
        : ($lang['Search'] ?? 'Search');
    $value = isset($options['value']) ? (string) $options['value'] : '';
    $formId = isset($options['form_id']) ? (string) $options['form_id'] : 'searchForm';
    $inputId = isset($options['input_id']) ? (string) $options['input_id'] : 'task-search';
    $inputName = isset($options['input_name']) ? (string) $options['input_name'] : 'search';
    $hiddenFields = isset($options['hidden_fields']) && is_array($options['hidden_fields'])
        ? $options['hidden_fields']
        : array();
    $clearUrl = isset($options['clear_url']) ? (string) $options['clear_url'] : '';

    $closeLabel = $lang['Close'] ?? 'Close';
    $closeHref = ($value !== '' && $clearUrl !== '') ? $clearUrl : '#';

    ob_start();
    ?>
    <div class="search" data-ts-ecom-search-wrap>
        <div class="search-icon border-btn-a" role="button" tabindex="0" aria-label="<?php echo htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo function_exists('ts_icon') ? ts_icon('search', 'w-2') : ''; ?>
        </div>
        <form method="<?php echo htmlspecialchars($method, ENT_QUOTES, 'UTF-8'); ?>" action="<?php echo htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?>" class="search-form<?php echo $value !== '' ? ' expanded' : ''; ?>" id="<?php echo htmlspecialchars($formId, ENT_QUOTES, 'UTF-8'); ?>" data-ts-ecom-search="1">
            <?php foreach ($hiddenFields as $hfName => $hfVal) : ?>
                <input type="hidden" name="<?php echo htmlspecialchars((string) $hfName, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars((string) $hfVal, ENT_QUOTES, 'UTF-8'); ?>">
            <?php endforeach; ?>
            <div class="input-group">
                <span class="search-field-icon">
                    <?php echo function_exists('ts_icon') ? ts_icon('search', 'w-2') : ''; ?>
                </span>
                <input type="text"
                       id="<?php echo htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8'); ?>"
                       name="<?php echo htmlspecialchars($inputName, ENT_QUOTES, 'UTF-8'); ?>"
                       class="form-control"
                       placeholder="<?php echo htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8'); ?>"
                       value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>">
                <a href="<?php echo htmlspecialchars($closeHref, ENT_QUOTES, 'UTF-8'); ?>"
                   class="cross"
                   title="<?php echo htmlspecialchars($closeLabel, ENT_QUOTES, 'UTF-8'); ?>"
                   aria-label="<?php echo htmlspecialchars($closeLabel, ENT_QUOTES, 'UTF-8'); ?>"
                   <?php if ($closeHref === '#') : ?>onclick="var f=this.closest('.search-form'); if(f){f.classList.remove('expanded');} return false;"<?php endif; ?>>
                    <?php echo function_exists('ts_icon') ? ts_icon('close', 'w-2') : ''; ?>
                </a>
            </div>
        </form>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * @return string
 */
function tasksession_list_bulk_checkbox_th_html()
{
    return '<th class="ts-list-bulk-checkbox-col text-center" aria-hidden="true"></th>';
}

/**
 * @param int|string $id
 * @param int $index
 * @return string
 */
function tasksession_list_bulk_checkbox_td_html($id, $index = 0)
{
    $idVal = htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8');
    $idx = (int) $index;
    return '<td class="bs-checkbox ts-list-bulk-checkbox-cell">'
        . '<input data-index="' . $idx . '" value="' . $idVal . '" name="btSelectItem" type="checkbox" aria-label="Select row">'
        . '</td>';
}

/**
 * Hidden POST form for bulk actions.
 *
 * @param array<string,mixed> $options
 * @return string
 */
function tasksession_list_bulk_form_html(array $options = array())
{
    $formId = isset($options['form_id']) ? (string) $options['form_id'] : 'tsListBulkForm';
    $actionField = isset($options['action_field']) ? (string) $options['action_field'] : 'ts_list_bulk_action';
    $idsField = isset($options['ids_field']) ? (string) $options['ids_field'] : 'bulk_ids';
    $action = isset($options['action']) ? (string) $options['action'] : '';
    $method = isset($options['method']) ? (string) $options['method'] : 'post';
    $hiddenFields = isset($options['hidden_fields']) && is_array($options['hidden_fields'])
        ? $options['hidden_fields']
        : array();

    ob_start();
    ?>
    <form method="<?php echo htmlspecialchars($method, ENT_QUOTES, 'UTF-8'); ?>"
          action="<?php echo htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?>"
          id="<?php echo htmlspecialchars($formId, ENT_QUOTES, 'UTF-8'); ?>"
          class="d-none ts-list-bulk-form">
        <input type="hidden" name="<?php echo htmlspecialchars($actionField, ENT_QUOTES, 'UTF-8'); ?>" value="">
        <input type="hidden" name="<?php echo htmlspecialchars($idsField, ENT_QUOTES, 'UTF-8'); ?>" value="">
        <?php foreach ($hiddenFields as $hfName => $hfVal) : ?>
            <input type="hidden" name="<?php echo htmlspecialchars((string) $hfName, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars((string) $hfVal, ENT_QUOTES, 'UTF-8'); ?>">
        <?php endforeach; ?>
    </form>
    <?php
    return (string) ob_get_clean();
}

/**
 * @return string
 */
function tasksession_list_bulk_styles_tag()
{
    global $url;
    return '<link rel="stylesheet" href="' . htmlspecialchars($url . 'assets/css/list-bulk.css', ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * @param array<string,mixed> $config Passed to TsListBulk.init()
 * @return string
 */
function tasksession_list_bulk_script_tag(array $config = array())
{
    global $url;
    $json = json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if ($json === false) {
        $json = '{}';
    }
    return tasksession_list_bulk_styles_tag()
        . '<script src="' . htmlspecialchars($url . 'assets/js/list-bulk.js', ENT_QUOTES, 'UTF-8') . '"></script>'
        . '<script>document.addEventListener("DOMContentLoaded",function(){if(window.TsListBulk){TsListBulk.init(' . $json . ');}});</script>';
}
