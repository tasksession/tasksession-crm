<?php
ob_start();
require_once("../includes/lib-initialize.php");
$title = "Forms Field Mappings | " . $syatem_title;
include("../templates/header.php");

if (!$session->isLoggedIn()) { redirectTo($url . "index"); }
if (!isset($_SESSION['accountStatus']) || (int)$_SESSION['accountStatus'] !== 1) { redirectTo($url . "admin/index"); }

$id = $session->userId;
$user = User::findById((int)$id);
$username = $user->firstName;

require_once(__DIR__ . "/../includes/forms/includes/bootstrap.php");
$formsConfig = require __DIR__ . "/../includes/forms/config/defaults.php";
$formsController = new FormsController($connect, $formsConfig);
$integrationService = new IntegrationService($connect);

$integration_id = isset($_GET['integration_id']) ? (int)$_GET['integration_id'] : 0;
$form_id = isset($_GET['form_id']) ? (int)$_GET['form_id'] : 0;
if ($integration_id <= 0 && $form_id > 0) {
    $form = $formsController->getFormById($form_id);
    $integration_id = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_mapping'])) {
    $dest = trim((string)($_POST['destination_field'] ?? ''));
    $destType = trim((string)($_POST['destination_type'] ?? 'lead_column'));
    if (strpos($dest, 'cf_') === 0) {
        $destType = 'custom_field';
        $dest = (string)(int)substr($dest, 3);
        if ($dest === '0') $dest = '';
    }
    $integrationIdPost = (int)($_POST['integration_id'] ?? 0);
    $valid = false;
    if ($integrationIdPost > 0 && $dest !== '') {
        if ($destType === 'lead_column') {
            $leadColumns = $formsController->getLeadColumnsWhitelist();
            $valid = in_array($dest, $leadColumns, true);
        } else {
            $customFields = $formsController->getLeadCustomFields();
            $allowedIds = array_map(function ($cf) { return (string)(int)$cf['id']; }, $customFields);
            $valid = in_array($dest, $allowedIds, true);
        }
    }
    if ($valid) {
        $_POST['destination_type'] = $destType;
        $_POST['destination_field'] = $dest;
        $_POST['integration_id'] = $integrationIdPost;
        $_POST['form_id'] = !empty($_POST['form_id']) ? (int)$_POST['form_id'] : null;
        $integrationService->saveMapping($_POST);
        $_SESSION['message'] = 'Mapping added.';
    } else {
        $_SESSION['message'] = 'Invalid mapping or integration.';
        $_SESSION['message_type'] = 'danger';
        redirectTo($url . "admin/forms_mappings?integration_id=" . $integrationIdPost . ($form_id ? "&form_id=$form_id" : '') . '&add_mapping=1');
    }
    redirectTo($url . "admin/forms_mappings?integration_id=" . $integrationIdPost . ($form_id ? "&form_id=$form_id" : ''));
}
if (isset($_GET['delete_mapping'])) {
    $integrationService->deleteMapping((int)$_GET['delete_mapping']);
    $_SESSION['message'] = 'Mapping deleted.';
    redirectTo($url . "admin/forms_mappings?integration_id=" . $integration_id . ($form_id ? "&form_id=$form_id" : ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_mapping'])) {
    $mappingId = (int)($_POST['mapping_id'] ?? 0);
    $existing = $mappingId > 0 ? $integrationService->getMappingById($mappingId) : null;
    if ($existing && (int)$existing['integration_id'] === $integration_id) {
        $dest = trim((string)($_POST['destination_field'] ?? ''));
        $destType = trim((string)($_POST['destination_type'] ?? 'lead_column'));
        if (strpos($dest, 'cf_') === 0) {
            $destType = 'custom_field';
            $dest = (string)(int)substr($dest, 3);
            if ($dest === '0') $dest = '';
        }
        $valid = false;
        if ($dest !== '') {
            if ($destType === 'lead_column') {
                $leadColumns = $formsController->getLeadColumnsWhitelist();
                $valid = in_array($dest, $leadColumns, true);
            } else {
                $customFields = $formsController->getLeadCustomFields();
                $allowedIds = array_map(function ($cf) { return (string)(int)$cf['id']; }, $customFields);
                $valid = in_array($dest, $allowedIds, true);
            }
        }
        if ($valid) {
            $_POST['destination_type'] = $destType;
            $_POST['destination_field'] = $dest;
            $integrationService->updateMapping($mappingId, $_POST);
            $_SESSION['message'] = 'Mapping updated.';
        } else {
            $_SESSION['message'] = 'Invalid destination.';
            $_SESSION['message_type'] = 'danger';
        }
    }
    redirectTo($url . "admin/forms_mappings?integration_id=" . $integration_id . ($form_id ? "&form_id=$form_id" : ''));
}

$integrations = $formsController->getIntegrations();
$formsList = method_exists($formsController, 'getForms') ? $formsController->getForms() : [];
if ($integration_id <= 0 && !empty($integrations)) {
    $integration_id = (int)$integrations[0]['id'];
}
$mappings = $integration_id > 0 ? $formsController->getMappings($integration_id, $form_id ?: null) : [];
$leadColumns = $formsController->getLeadColumnsWhitelist();
$customFields = $formsController->getLeadCustomFields();
$formFields = $form_id > 0 ? $formsController->getFormFields($form_id) : [];
$editMapping = null;
if (isset($_GET['edit_mapping']) && (int)$_GET['edit_mapping'] > 0 && $integration_id > 0) {
    $editMapping = $integrationService->getMappingById((int)$_GET['edit_mapping']);
    if ($editMapping && (int)$editMapping['integration_id'] !== $integration_id) {
        $editMapping = null;
    }
}
if (!empty($_SESSION['message'])) {
    $alertClass = (!empty($_SESSION['message_type']) && $_SESSION['message_type'] === 'danger') ? 'alert-danger' : 'alert-success';
    $message = '<div class="alert ' . $alertClass . '">' . FormsSecurityHelper::escape($_SESSION['message']) . '</div>';
    unset($_SESSION['message'], $_SESSION['message_type']);
} else {
    $message = '';
}
$showAddMappingPanel = isset($_GET['add_mapping']) && !$editMapping;
$canAddMapping = (int) $integration_id > 0 && empty($editMapping);
$formsReportsFilterAssets = true;
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
                        <?php echo $message; ?>
                        <?php
                        $formsPanelTitle = isset($lang['Field Mappings']) ? $lang['Field Mappings'] : 'Field Mappings';
                        $formsPanelSub = 'Step 1: choose integration and form. Step 2: review mappings. Step 3: add or edit a mapping.';
                        $formsPanelActionsHtml = '';
                        include __DIR__ . '/../includes/forms/views/panel_head.php';
                        ?>

                        <?php include __DIR__ . '/../includes/forms/views/mappings_filters.php'; ?>

                        <div class="forms-mappings-step-table">
                            <?php include __DIR__ . '/../includes/forms/views/mappings_list.php'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/forms/views/reports_filter_scripts.php'; ?>
<?php include("../templates/main-footer.php"); ?>
