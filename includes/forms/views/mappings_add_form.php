<?php
if (!isset($url) || !isset($integration_id) || !isset($leadColumns) || !isset($customFields)) return;
$formFields = isset($formFields) ? $formFields : [];
$mappedSourceFields = isset($mappedSourceFields) ? $mappedSourceFields : [];
$form_id = isset($form_id) ? (int) $form_id : 0;
?>
<form method="post" action="" class="forms-mapping-form-fields">
    <input type="hidden" name="integration_id" value="<?php echo (int)$integration_id; ?>">
    <input type="hidden" name="form_id" value="<?php echo $form_id; ?>">
    <div class="row">
        <div class="col-md-4">
            <div class="form-group field-label">
                <label for="source_field">Source field</label>
                <?php if (!empty($formFields)): ?>
                <select name="source_field" id="source_field" class="form-control" required>
                    <option value="">Select form field</option>
                    <?php foreach ($formFields as $f): ?>
                    <?php if (!in_array($f['name'], $mappedSourceFields, true)): ?>
                    <option value="<?php echo FormsSecurityHelper::escape($f['name']); ?>"><?php echo FormsSecurityHelper::escape($f['label'] ?? $f['name']); ?> (<?php echo FormsSecurityHelper::escape($f['name']); ?>)</option>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <?php else: ?>
                <input type="text" name="source_field" id="source_field" class="form-control" placeholder="e.g. email, full_name, phone" required>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group field-label">
                <label for="destination_type">Destination type</label>
                <select name="destination_type" id="destination_type" class="form-control">
                    <option value="lead_column">Lead column</option>
                    <option value="custom_field">Custom field</option>
                </select>
            </div>
        </div>
        <div class="col-md-5">
            <div class="form-group field-label">
                <label for="dest_field_select">Destination</label>
                <select name="destination_field" class="form-control" id="dest_field_select">
                    <optgroup label="Lead columns">
                        <?php foreach ($leadColumns as $col): ?>
                        <option value="<?php echo FormsSecurityHelper::escape($col); ?>"><?php echo FormsSecurityHelper::escape($col); ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php if (!empty($customFields)): ?>
                    <optgroup label="Custom fields">
                        <?php foreach ($customFields as $cf): ?>
                        <option value="cf_<?php echo (int)$cf['id']; ?>"><?php echo FormsSecurityHelper::escape($cf['label']); ?></option>
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
                    <input class="tgl tgl-light" id="mapping_required" name="is_required" type="checkbox" value="1">
                    <label class="tgl-btn" for="mapping_required"></label>
                </div>
                This field must be present in the submission
            </label>
        </div>
        <div class="col-md-4">
            <div class="forms-form-actions justify-content-md-end mt-3 mt-md-0">
                <button type="button" class="outline-btn forms-mapping-add-cancel" data-bs-toggle="collapse" data-bs-target="#forms-mapping-add-panel">Cancel</button>
                <button type="submit" name="add_mapping" class="primary-btn" <?php echo (int)$integration_id <= 0 ? 'disabled' : ''; ?>><?php echo ts_icon('plus'); ?><span>Add mapping</span></button>
            </div>
        </div>
    </div>
</form>
