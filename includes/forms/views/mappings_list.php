<?php
if (!isset($mappings) || !isset($integrations) || !isset($leadColumns) || !isset($customFields) || !isset($url)) return;
$formFields = isset($formFields) ? $formFields : [];
$mappedSourceFields = array_column($mappings, 'source_field');
$lang = isset($lang) ? $lang : [];
$editIcon = ts_icon('edit');
$deleteIcon = ts_icon('delete');
$mappingQueryBase = 'integration_id=' . (int)($integration_id ?? 0) . (!empty($form_id) ? '&form_id=' . (int)$form_id : '');
$showAddMappingPanel = !empty($showAddMappingPanel);
$isEditingMapping = !empty($editMapping);
?>
<?php include __DIR__ . '/list_table_open.php'; ?>
    <thead>
        <tr>
            <th><?php echo isset($lang['No.']) ? FormsSecurityHelper::escape($lang['No.']) : 'No'; ?></th>
            <th>Source Field</th>
            <th>Destination</th>
            <th>Required</th>
            <th><?php echo isset($lang['Options']) ? FormsSecurityHelper::escape($lang['Options']) : 'Options'; ?></th>
        </tr>
    </thead>
    <tbody id="projects-tbl">
        <?php if (empty($mappings)): ?>
        <tr>
            <td colspan="5" class="text-center"><?php echo isset($lang['No Data']) ? FormsSecurityHelper::escape($lang['No Data']) : 'No mappings for this integration yet.'; ?></td>
        </tr>
        <?php else: ?>
        <?php foreach ($mappings as $index => $m):
            $mappingActions = array(
                array(
                    'type' => 'link',
                    'href' => $url . 'admin/forms_mappings?' . $mappingQueryBase . '&edit_mapping=' . (int) $m['id'],
                    'label' => $lang['Edit'] ?? 'Edit',
                    'icon' => $editIcon,
                ),
                array(
                    'type' => 'link',
                    'href' => $url . 'admin/forms_mappings?' . $mappingQueryBase . '&delete_mapping=' . (int) $m['id'],
                    'label' => $lang['Delete'] ?? 'Delete',
                    'icon' => $deleteIcon,
                    'confirm' => 'Delete this mapping?',
                ),
            );
            $destLabel = $m['destination_type'] === 'custom_field'
                ? 'custom_field:' . $m['destination_field']
                : $m['destination_field'];
        ?>
        <tr>
            <td><?php echo (int)($index + 1); ?></td>
            <td><div class="tbl-ttl"><?php echo FormsSecurityHelper::escape($m['source_field']); ?></div></td>
            <td><?php echo FormsSecurityHelper::escape($destLabel); ?></td>
            <td><?php echo forms_status_badge_html(!empty($m['is_required']) ? 'yes' : 'no'); ?></td>
            <?php forms_render_action_toggle('mapping' . (int) $m['id'], $mappingActions); ?>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
<?php include __DIR__ . '/list_table_close.php'; ?>

<?php if ($isEditingMapping):
    $editDestValue = ($editMapping['destination_type'] ?? '') === 'custom_field'
        ? 'cf_' . (int)$editMapping['destination_field']
        : ($editMapping['destination_field'] ?? '');
?>
<div class="forms-settings-card forms-mapping-form forms-mapping-form--edit mt-4">
    <div class="ai-settings-block-head">
        <h3 class="ai-settings-block-title">Edit mapping</h3>
        <p class="ai-settings-block-sub">Update the CRM destination for <code><?php echo FormsSecurityHelper::escape($editMapping['source_field']); ?></code>.</p>
    </div>
    <form method="post" action="">
        <input type="hidden" name="mapping_id" value="<?php echo (int)$editMapping['id']; ?>">
        <input type="hidden" name="update_mapping" value="1">
        <div class="row">
            <div class="col-md-4">
                <div class="form-group field-label">
                    <label>Source field</label>
                    <input type="text" class="form-control" value="<?php echo FormsSecurityHelper::escape($editMapping['source_field']); ?>" readonly>
                    <input type="hidden" name="source_field" value="<?php echo FormsSecurityHelper::escape($editMapping['source_field']); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group field-label">
                    <label for="edit_destination_type">Destination type</label>
                    <select name="destination_type" id="edit_destination_type" class="form-control">
                        <option value="lead_column" <?php echo ($editMapping['destination_type'] ?? '') === 'lead_column' ? 'selected' : ''; ?>>Lead column</option>
                        <option value="custom_field" <?php echo ($editMapping['destination_type'] ?? '') === 'custom_field' ? 'selected' : ''; ?>>Custom field</option>
                    </select>
                </div>
            </div>
            <div class="col-md-5">
                <div class="form-group field-label">
                    <label for="edit_dest_field_select">Destination</label>
                    <select name="destination_field" class="form-control" id="edit_dest_field_select">
                        <optgroup label="Lead columns">
                            <?php foreach ($leadColumns as $col): ?>
                            <option value="<?php echo FormsSecurityHelper::escape($col); ?>" <?php echo ($editDestValue === $col) ? 'selected' : ''; ?>><?php echo FormsSecurityHelper::escape($col); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php if (!empty($customFields)): ?>
                        <optgroup label="Custom fields">
                            <?php foreach ($customFields as $cf): ?>
                            <option value="cf_<?php echo (int)$cf['id']; ?>" <?php echo ($editDestValue === 'cf_' . $cf['id']) ? 'selected' : ''; ?>><?php echo FormsSecurityHelper::escape($cf['label']); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
        </div>
        <div class="row align-items-center">
            <div class="col-md-8">
                <label class="d-flex align-items-center col-gap mb-0">
                    <div class="checkbox-wrapper-6">
                        <input class="tgl tgl-light" id="edit_mapping_required" name="is_required" type="checkbox" value="1" <?php echo !empty($editMapping['is_required']) ? 'checked' : ''; ?>>
                        <label class="tgl-btn" for="edit_mapping_required"></label>
                    </div>
                    This field must be present in the submission
                </label>
            </div>
            <div class="col-md-4">
                <div class="forms-form-actions justify-content-md-end mt-3 mt-md-0">
                    <a href="<?php echo FormsSecurityHelper::escape($url); ?>admin/forms_mappings?<?php echo htmlspecialchars($mappingQueryBase, ENT_QUOTES, 'UTF-8'); ?>" class="outline-btn">Cancel</a>
                    <button type="submit" class="primary-btn">Update</button>
                </div>
            </div>
        </div>
    </form>
</div>
<?php elseif (!$isEditingMapping): ?>
<div class="collapse forms-mapping-add-collapse<?php echo $showAddMappingPanel ? ' show' : ''; ?>" id="forms-mapping-add-panel">
    <div class="forms-settings-card forms-mapping-form forms-mapping-form--add mt-4">
        <div class="ai-settings-block-head">
            <h3 class="ai-settings-block-title">Add new mapping</h3>
            <p class="ai-settings-block-sub">
                <?php if (!empty($formFields)): ?>
                Pick a form field and map it to a CRM lead column or custom field.
                <?php else: ?>
                Enter the payload key from your integration, then choose a CRM destination.
                <?php endif; ?>
            </p>
        </div>
        <?php include __DIR__ . '/mappings_add_form.php'; ?>
    </div>
</div>
<?php endif; ?>
