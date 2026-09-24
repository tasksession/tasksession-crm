<?php
ob_start();
require_once("../includes/lib-initialize.php");

if (!$session->isLoggedIn()) { redirectTo($url . "index"); }
if (!isset($_SESSION['accountStatus']) || (int)$_SESSION['accountStatus'] !== 1) { redirectTo($url . "admin/index"); }

require_once(__DIR__ . "/../includes/forms/includes/bootstrap.php");
$formsConfig = require __DIR__ . "/../includes/forms/config/defaults.php";
$formsController = new FormsController($connect, $formsConfig);
$formService = new FormService($connect);

$formId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$fieldId = isset($_GET['field_id']) ? (int)$_GET['field_id'] : 0;
$isAdd = isset($_GET['add']) && (int)$_GET['add'] === 1;

$form = $formId > 0 ? $formService->getById($formId) : null;
if (!$form) {
    $_SESSION['message'] = 'Form not found.';
    redirectTo($url . 'admin/forms');
}

$field = null;
if (!$isAdd) {
    $field = $fieldId > 0 ? $formService->getFieldById($fieldId) : null;
    if (!$field || (int)$field['form_id'] !== $formId) {
        $_SESSION['message'] = 'Form or field not found.';
        redirectTo($url . 'admin/forms_edit?id=' . $formId);
    }
}

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

function forms_field_edit_parse_options_from_post()
{
    $type = trim((string)($_POST['type'] ?? 'text'));
    $optionsRaw = trim((string)($_POST['options'] ?? ''));
    $optionsJson = null;
    if (in_array($type, ['select', 'radio', 'checkbox'], true) && $optionsRaw !== '') {
        $lines = preg_split('/\r\n|\r|\n/', $optionsRaw, -1, PREG_SPLIT_NO_EMPTY);
        $opts = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (strpos($line, '=') !== false) {
                $parts = explode('=', $line, 2);
                $opts[] = ['value' => trim($parts[0]), 'label' => trim($parts[1])];
            } else {
                $opts[] = ['value' => $line, 'label' => $line];
            }
        }
        $optionsJson = $opts ? json_encode($opts) : null;
    }
    $_POST['options_json'] = $optionsJson;
    $_POST['width'] = in_array($_POST['width'] ?? 'full', ['full', 'half'], true) ? $_POST['width'] : 'full';
    return $type;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_field'])) {
    forms_field_edit_parse_options_from_post();
    $newFieldId = $formService->saveFormField($formId, $_POST);
    if ($newFieldId) {
        $_SESSION['message'] = 'Field added.';
        redirectTo($url . 'admin/forms_edit?id=' . $formId);
    }
    $message = '<div class="alert alert-danger">Failed to add field.</div>';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_field'])) {
    forms_field_edit_parse_options_from_post();
    $_POST['sort_order'] = (int)($field['sort_order'] ?? 0);

    if ($formService->updateFormField($fieldId, $_POST)) {
        if ($isAjax) {
            if (ob_get_level()) ob_clean();
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => true, 'message' => 'Field updated.']);
            exit;
        }
        $_SESSION['message'] = 'Field updated.';
        header('Location: ' . $url . 'admin/forms_edit?id=' . $formId);
        exit;
    }
    $message = '<div class="alert alert-danger">Update failed.</div>';
} else {
    $message = '';
}

$optionsText = '';
if ($field && !empty($field['options_json'])) {
    $decoded = json_decode($field['options_json'], true);
    if (is_array($decoded)) {
        $lines = [];
        foreach ($decoded as $opt) {
            if (is_array($opt)) {
                $v = $opt['value'] ?? $opt['label'] ?? '';
                $l = $opt['label'] ?? $v;
                $lines[] = ($v !== $l) ? ($v . '=' . $l) : $l;
            } else {
                $lines[] = $opt;
            }
        }
        $optionsText = implode("\n", $lines);
    }
}

$fieldWidth = ($field && isset($field['width']) && $field['width'] === 'half') ? 'half' : 'full';
$fieldType = $field['type'] ?? 'text';
$title = ($isAdd ? 'Add Field' : 'Edit Field') . ' | Forms | ' . $syatem_title;
include("../templates/header.php");
?>
<div class="page-container">
    <div class="container-fluid">
        <div class="row row-eq-height">
            <?php include("../templates/sidebar.php"); ?>
            <div class="page-content" style="padding-bottom:0;">
                <?php include('../templates/top-header.php'); ?>
                <?php include __DIR__ . '/../includes/forms/views/settings_assets.php'; ?>
                <div class="row system-wrap ai-settings-shell">
                    <?php include("../templates/form-menu.php"); ?>
                    <div class="col-md-9 ss-right">
                        <?php
                        $formsPanelTitle = $isAdd ? 'Add field' : ('Edit field: ' . ($field['label'] ?? ''));
                        $formsPanelSub = 'Form: ' . ($form['name'] ?? '');
                        $formsPanelActionsHtml = '<a href="' . htmlspecialchars($url . 'admin/forms_edit?id=' . $formId, ENT_QUOTES, 'UTF-8') . '#form-fields" class="outline-btn">' . ts_icon('arrow-left') . '<span>Back to form</span></a>';
                        include __DIR__ . '/../includes/forms/views/panel_head.php';
                        ?>
                        <div id="edit-field-message" class="alert alert-success d-none mb-3"></div>
                        <?php echo $message; ?>
                        <form method="post" class="forms-settings-card settings-card" id="edit-field-form">
                            <?php if ($isAdd): ?>
                            <input type="hidden" name="add_field" value="1">
                            <?php else: ?>
                            <input type="hidden" name="update_field" value="1">
                            <?php endif; ?>
                            <div class="form-group">
                                <label>Label</label>
                                <input type="text" name="label" class="form-control" required value="<?php echo FormsSecurityHelper::escape($field['label'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Name (field name)</label>
                                <input type="text" name="name" class="form-control" required value="<?php echo FormsSecurityHelper::escape($field['name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Type</label>
                                <select name="type" class="form-control" id="field-type-select">
                                    <?php foreach (['text', 'email', 'phone', 'textarea', 'select', 'radio', 'checkbox', 'date', 'hidden'] as $typeOpt): ?>
                                    <option value="<?php echo $typeOpt; ?>" <?php echo $fieldType === $typeOpt ? 'selected' : ''; ?>><?php echo $typeOpt; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Placeholder</label>
                                <input type="text" name="placeholder" class="form-control" value="<?php echo FormsSecurityHelper::escape($field['placeholder'] ?? ''); ?>" placeholder="Optional placeholder text">
                            </div>
                            <div class="form-group" id="options-group">
                                <label>Options (for select, radio, checkbox)</label>
                                <textarea name="options" class="form-control" rows="4" placeholder="One option per line. value=Label for custom value"><?php echo FormsSecurityHelper::escape($optionsText); ?></textarea>
                                <small class="form-text text-muted">One option per line. Use value=Label for custom value and label.</small>
                            </div>
                            <div class="form-group">
                                <label>Width</label>
                                <select name="width" class="form-control">
                                    <option value="full" <?php echo $fieldWidth === 'full' ? 'selected' : ''; ?>>Full width (1 column)</option>
                                    <option value="half" <?php echo $fieldWidth === 'half' ? 'selected' : ''; ?>>Half width (2 columns)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="d-flex align-items-center col-gap">
                                    <div class="checkbox-wrapper-6">
                                        <input class="tgl tgl-light" id="edit_field_required" name="is_required" type="checkbox" value="1" <?php echo !empty($field['is_required']) ? 'checked' : ''; ?>>
                                        <label class="tgl-btn" for="edit_field_required"></label>
                                    </div>
                                    Required
                                </label>
                            </div>
                            <div class="forms-form-actions">
                                <button type="submit" class="primary-btn"><?php echo $isAdd ? 'Add field' : 'Update field'; ?></button>
                            </div>
                        </form>
                        <?php if (!$isAdd): ?>
                        <script>
                        (function(){
                            var typeSelect = document.getElementById('field-type-select');
                            var optionsGroup = document.getElementById('options-group');
                            function toggleOptions(){
                                var t = (typeSelect && typeSelect.value) || '';
                                optionsGroup.style.display = (t === 'select' || t === 'radio' || t === 'checkbox') ? 'block' : 'none';
                            }
                            if (typeSelect && optionsGroup) { typeSelect.onchange = toggleOptions; toggleOptions(); }

                            var form = document.getElementById('edit-field-form');
                            var msgEl = document.getElementById('edit-field-message');
                            if (form && msgEl) {
                                form.addEventListener('submit', function(e){
                                    e.preventDefault();
                                    var fd = new FormData(form);
                                    var xhr = new XMLHttpRequest();
                                    xhr.open('POST', form.action || window.location.href);
                                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                                    xhr.onload = function(){
                                        if (xhr.status === 200) {
                                            try {
                                                var r = JSON.parse(xhr.responseText);
                                                if (r.success) {
                                                    msgEl.textContent = r.message || 'Field updated.';
                                                    msgEl.classList.remove('d-none', 'alert-danger');
                                                    msgEl.classList.add('alert-success');
                                                } else {
                                                    msgEl.textContent = r.message || 'Update failed.';
                                                    msgEl.classList.remove('d-none', 'alert-success');
                                                    msgEl.classList.add('alert-danger');
                                                }
                                            } catch (err) {
                                                msgEl.textContent = 'Update failed.';
                                                msgEl.classList.remove('d-none', 'alert-success');
                                                msgEl.classList.add('alert-danger');
                                            }
                                        } else {
                                            msgEl.textContent = 'Update failed.';
                                            msgEl.classList.remove('d-none', 'alert-success');
                                            msgEl.classList.add('alert-danger');
                                        }
                                    };
                                    xhr.onerror = function(){ msgEl.textContent = 'Update failed.'; msgEl.classList.remove('d-none'); msgEl.classList.add('alert-danger'); };
                                    xhr.send(fd);
                                });
                            }
                        })();
                        </script>
                        <?php else: ?>
                        <script>
                        (function(){
                            var typeSelect = document.getElementById('field-type-select');
                            var optionsGroup = document.getElementById('options-group');
                            function toggleOptions(){
                                var t = (typeSelect && typeSelect.value) || '';
                                optionsGroup.style.display = (t === 'select' || t === 'radio' || t === 'checkbox') ? 'block' : 'none';
                            }
                            if (typeSelect && optionsGroup) { typeSelect.onchange = toggleOptions; toggleOptions(); }
                        })();
                        </script>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include("../templates/main-footer.php"); ?>
