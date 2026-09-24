<?php
ob_start();
require_once("../includes/lib-initialize.php");
$title = "Forms | " . $syatem_title;
include("../templates/header.php");

if (!$session->isLoggedIn()) {
    redirectTo($url . "index");
}
if (!isset($_SESSION['accountStatus']) || (int)$_SESSION['accountStatus'] !== 1) {
    redirectTo($url . "admin/index");
}

$id = $session->userId;
$user = User::findById((int)$id);
$username = $user->firstName;

require_once(__DIR__ . "/../includes/forms/includes/bootstrap.php");
$formsConfig = require __DIR__ . "/../includes/forms/config/defaults.php";
$formsController = new FormsController($connect, $formsConfig);
$formService = new FormService($connect);

if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    if ($deleteId > 0 && $formService->deleteForm($deleteId)) {
        $_SESSION['message'] = isset($lang['Deleted successfully']) ? $lang['Deleted successfully'] : 'Form deleted successfully.';
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = isset($lang['Delete failed']) ? $lang['Delete failed'] : 'Could not delete form.';
        $_SESSION['message_type'] = 'danger';
    }
    redirectTo($url . "admin/forms");
}

$forms = $formsController->getForms();
if (!empty($_SESSION['message'])) {
    $alertClass = (!empty($_SESSION['message_type']) && $_SESSION['message_type'] === 'danger') ? 'alert-danger' : 'alert-success';
    $message = '<div class="alert ' . $alertClass . '">' . htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8') . '</div>';
    unset($_SESSION['message'], $_SESSION['message_type']);
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
                        <?php if (!empty($message)) echo $message; ?>
                        <?php
                        $formsPanelTitle = isset($lang['Forms']) ? $lang['Forms'] : 'Forms';
                        $formsPanelSub = 'Manage lead capture forms, fields, and embed settings.';
                        $formsPanelActionsHtml = '<a href="' . htmlspecialchars($url . 'admin/forms_add', ENT_QUOTES, 'UTF-8') . '" class="primary-btn">' . ts_icon('plus') . '<span>Create Form</span></a>';
                        include __DIR__ . '/../includes/forms/views/panel_head.php';
                        include __DIR__ . '/../includes/forms/views/forms_list.php';
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include("../templates/main-footer.php"); ?>
