<?php
ob_start();
require_once("../includes/lib-initialize.php");

if (!$session->isLoggedIn()) { redirectTo($url . "index"); }
if (!isset($_SESSION['accountStatus']) || (int)$_SESSION['accountStatus'] !== 1) { redirectTo($url . "admin/index"); }

$id = $session->userId;
$user = User::findById((int)$id);
$username = $user->firstName;

require_once(__DIR__ . "/../includes/forms/includes/bootstrap.php");
$formsConfig = require __DIR__ . "/../includes/forms/config/defaults.php";
$formsController = new FormsController($connect, $formsConfig);
$formService = new FormService($connect);
$formId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Delete field first (before loading $form) so we never redirect to forms.php by mistake
if (isset($_GET['delete_field']) && (int)$_GET['delete_field'] > 0 && $formId > 0) {
    $formService->deleteFormField((int)$_GET['delete_field']);
    $_SESSION['message'] = 'Field deleted.';
    header('Location: ' . $url . 'admin/forms_edit?id=' . $formId);
    exit;
}

$form = $formService->getById($formId);
if (!$form) {
    $_SESSION['message'] = 'Form not found.';
    redirectTo($url . "admin/forms");
}

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_form'])) {
        $formSettings = [];
        if (!empty($form['settings_json'])) {
            $decoded = json_decode($form['settings_json'], true);
            if (is_array($decoded)) {
                $formSettings = $decoded;
            }
        }
        $formSettings['embed_integration_id'] = isset($_POST['embed_integration_id']) ? (int)$_POST['embed_integration_id'] : 0;
        $_POST['settings_json'] = json_encode($formSettings);
        $formService->updateForm($formId, $_POST);
        if ($isAjax) {
            if (ob_get_level()) ob_clean();
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => true, 'message' => 'Form updated.']);
            exit;
        }
        $_SESSION['message'] = 'Form updated.';
        redirectTo($url . "admin/forms_edit?id=" . $formId);
    }
    if (isset($_POST['add_field'])) {
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
        $newFieldId = $formService->saveFormField($formId, $_POST);
        if ($isAjax) {
            $label = trim((string)($_POST['label'] ?? ''));
            $name = trim((string)($_POST['name'] ?? ''));
            $isRequired = !empty($_POST['is_required']) ? 1 : 0;
            if (ob_get_level()) ob_clean();
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success' => true,
                'message' => 'Field added.',
                'field' => ['id' => (int)$newFieldId, 'label' => $label, 'name' => $name, 'type' => $type, 'is_required' => $isRequired]
            ]);
            exit;
        }
        $_SESSION['message'] = 'Field added.';
        redirectTo($url . "admin/forms_edit?id=" . $formId);
    }
}

$title = "Edit Form | Forms | " . $syatem_title;
include("../templates/header.php");

$statuses = $formsController->getLeadStatuses();
$sources = $formsController->getLeadSources();
$integrations = $formsController->getIntegrations();
$formFields = $formService->getFieldsByFormId($formId);
$formSettings = [];
if (!empty($form['settings_json'])) {
    $decoded = json_decode($form['settings_json'], true);
    if (is_array($decoded)) {
        $formSettings = $decoded;
    }
}
if (!empty($_SESSION['message'])) {
    $message = '<div class="alert alert-success">' . FormsSecurityHelper::escape($_SESSION['message']) . '</div>';
    unset($_SESSION['message']);
} else {
    $message = '';
}
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
                        $formsPanelTitle = 'Edit Form: ' . ($form['name'] ?? '');
                        $formsPanelSub = 'Update form settings, fields, and website embed code.';
                        $formsPanelActionsHtml = '<a href="' . htmlspecialchars($url . 'admin/forms', ENT_QUOTES, 'UTF-8') . '" class="outline-btn">' . ts_icon('arrow-left') . '<span>Back to list</span></a>';
                        include __DIR__ . '/../includes/forms/views/panel_head.php';
                        ?>
                        <?php echo $message; ?>

                        <ul class="nav nav-tabs" id="formEditTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="edit-form-tab" data-bs-toggle="tab" data-bs-target="#edit-form" type="button" role="tab" aria-controls="edit-form" aria-selected="true">Edit form</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="form-fields-tab" data-bs-toggle="tab" data-bs-target="#form-fields" type="button" role="tab" aria-controls="form-fields" aria-selected="false">Form fields</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="embed-tab" data-bs-toggle="tab" data-bs-target="#embed" type="button" role="tab" aria-controls="embed" aria-selected="false">Embed on your website</button>
                            </li>
                        </ul>

                        <div class="tab-content bg-white border border-top-0 p-4" id="formEditTabContent">
                            <div class="tab-pane fade show active" id="edit-form" role="tabpanel" aria-labelledby="edit-form-tab">
                                <div id="edit-form-message" class="alert alert-success d-none mb-3"></div>
                                <form method="post" class="bg-grey pd-20 mb-4" id="edit-form-form">
                                    <input type="hidden" name="save_form" value="1">
                                    <div class="form-group">
                                        <label>Name *</label>
                                        <input type="text" name="name" class="form-control" required value="<?php echo FormsSecurityHelper::escape($form['name']); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Slug *</label>
                                        <input type="text" name="slug" class="form-control" required value="<?php echo FormsSecurityHelper::escape($form['slug']); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Status</label>
                                        <select name="status" class="form-control">
                                            <option value="active" <?php echo $form['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $form['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Default lead source</label>
                                        <select name="lead_source_id" class="form-control">
                                            <option value="">-- None --</option>
                                            <?php foreach ($sources as $s): ?>
                                            <option value="<?php echo (int)$s['id']; ?>" <?php echo (int)($form['lead_source_id'] ?? 0) === (int)$s['id'] ? 'selected' : ''; ?>><?php echo FormsSecurityHelper::escape($s['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Default status</label>
                                        <select name="assigned_status_id" class="form-control">
                                            <option value="">-- None --</option>
                                            <?php foreach ($statuses as $st): ?>
                                            <option value="<?php echo (int)$st['id']; ?>" <?php echo (int)($form['assigned_status_id'] ?? 0) === (int)$st['id'] ? 'selected' : ''; ?>><?php echo FormsSecurityHelper::escape($st['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Integration for embed</label>
                                        <select name="embed_integration_id" class="form-control">
                                            <option value="">— None (required to show form on website) —</option>
                                            <?php foreach ($integrations as $i): ?>
                                            <option value="<?php echo (int)$i['id']; ?>" <?php echo (int)($formSettings['embed_integration_id'] ?? 0) === (int)$i['id'] ? 'selected' : ''; ?>><?php echo FormsSecurityHelper::escape($i['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="form-text text-muted">Choose which integration to use when this form is embedded. Create one in Form settings → Integrations (e.g. name "Website form", source key "embed") and set Field Mappings for this form.</small>
                                    </div>
                                    <div class="forms-form-actions">
                                        <button type="submit" class="primary-btn">Update Form</button>
                                        <a href="<?php echo $url; ?>admin/forms" class="outline-btn">Back to list</a>
                                    </div>
                                </form>
                            </div>

                            <div class="tab-pane fade" id="form-fields" role="tabpanel" aria-labelledby="form-fields-tab">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <h5 class="mb-0">Form fields</h5>
                            <a href="<?php echo htmlspecialchars($url . 'admin/forms_field_edit?id=' . (int) $formId . '&add=1', ENT_QUOTES, 'UTF-8'); ?>" class="primary-btn"><?php echo ts_icon('plus'); ?><span>Add field</span></a>
                        </div>
                        <div class="mb-0">
                            <?php include __DIR__ . '/../includes/forms/views/list_table_open.php'; ?>
                                <thead>
                                    <tr>
                                        <th><?php echo isset($lang['No.']) ? FormsSecurityHelper::escape($lang['No.']) : 'No'; ?></th>
                                        <th>Label</th>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Required</th>
                                        <th><?php echo isset($lang['Options']) ? FormsSecurityHelper::escape($lang['Options']) : 'Options'; ?></th>
                                    </tr>
                                </thead>
                                <tbody id="form-fields-tbody" data-form-id="<?php echo (int)$formId; ?>" data-url="<?php echo FormsSecurityHelper::escape($url); ?>">
                                    <?php
                                    $fieldEditIcon = ts_icon('edit');
                                    $fieldDeleteIcon = ts_icon('delete');
                                    foreach ($formFields as $index => $f):
                                        $fieldActions = array(
                                            array(
                                                'type' => 'link',
                                                'href' => $url . 'admin/forms_field_edit?id=' . $formId . '&field_id=' . (int) $f['id'],
                                                'label' => $lang['Edit'] ?? 'Edit',
                                                'icon' => $fieldEditIcon,
                                            ),
                                            array(
                                                'type' => 'link',
                                                'href' => $url . 'admin/forms_edit?id=' . $formId . '&delete_field=' . (int) $f['id'],
                                                'label' => $lang['Delete'] ?? 'Delete',
                                                'icon' => $fieldDeleteIcon,
                                                'confirm' => 'Delete this field?',
                                            ),
                                        );
                                    ?>
                                    <tr>
                                        <td><?php echo (int)($index + 1); ?></td>
                                        <td><div class="tbl-ttl"><?php echo FormsSecurityHelper::escape($f['label']); ?></div></td>
                                        <td><?php echo FormsSecurityHelper::escape($f['name']); ?></td>
                                        <td><?php echo FormsSecurityHelper::escape($f['type']); ?></td>
                                        <td><?php echo forms_status_badge_html(!empty($f['is_required']) ? 'yes' : 'no'); ?></td>
                                        <?php forms_render_action_toggle('field' . (int) $f['id'], $fieldActions); ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            <?php include __DIR__ . '/../includes/forms/views/list_table_close.php'; ?>
                        </div>
                        <script>
                        (function(){
                            var editForm = document.getElementById('edit-form-form');
                            var editMsg = document.getElementById('edit-form-message');
                            if (editForm && editMsg) {
                                editForm.addEventListener('submit', function(e){
                                    e.preventDefault();
                                    var btn = editForm.querySelector('button[type="submit"]');
                                    if (btn) btn.disabled = true;
                                    var fd = new FormData(editForm);
                                    fetch(editForm.action || window.location.href, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                                        .then(function(r){ return r.json(); })
                                        .then(function(data){
                                            editMsg.textContent = data.message || 'Form updated.';
                                            editMsg.classList.remove('d-none','alert-danger');
                                            editMsg.classList.add('alert-success');
                                            setTimeout(function(){ editMsg.classList.add('d-none'); }, 4000);
                                        })
                                        .catch(function(){
                                            editMsg.textContent = 'Update failed.';
                                            editMsg.classList.remove('d-none','alert-success');
                                            editMsg.classList.add('alert-danger');
                                        })
                                        .finally(function(){ if (btn) btn.disabled = false; });
                                });
                            }

                        })();
                        </script>
                            </div>

                            <div class="tab-pane fade" id="embed" role="tabpanel" aria-labelledby="embed-tab">
                        <?php
                        $embedRootPath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
                        $embedFormUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $embedRootPath . '/includes/forms/public/form.php?slug=' . rawurlencode($form['slug']);
                        ?>
                                <div class="bg-grey pd-20">
                                    <h5 class="mb-3">Embed on your website</h5>
                                    <p class="text-muted small mb-2">Form module is for capturing leads into your CRM. To show this form on your site, use the integration above (Edit form tab) and one of the options below.</p>
                                    <div class="form-group mb-2">
                                        <label class="small font-weight-bold">Form URL (link or iframe)</label>
                                        <input type="text" class="form-control font-monospace small" readonly value="<?php echo FormsSecurityHelper::escape($embedFormUrl); ?>" onclick="this.select();">
                                    </div>
                                    <div class="form-group mb-0">
                                        <label class="small font-weight-bold">iframe code</label>
                                        <textarea class="form-control font-monospace small" rows="3" readonly onclick="this.select();">&lt;iframe src="<?php echo FormsSecurityHelper::escape($embedFormUrl); ?>" width="100%" height="500" frameborder="0" title="Contact form"&gt;&lt;/iframe&gt;</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include("../templates/main-footer.php"); ?>
