<?php
ob_start();
require_once("../includes/lib-initialize.php");
$title = "How it works | Forms | " . $syatem_title;
include("../templates/header.php");

if (!$session->isLoggedIn()) { redirectTo($url . "index.php"); }
if (!isset($_SESSION['accountStatus']) || (int)$_SESSION['accountStatus'] !== 1) { redirectTo($url . "admin/index.php"); }

$id = $session->userId;
$user = User::findById((int)$id);
$username = $user->firstName;

require_once(__DIR__ . "/../includes/forms/includes/bootstrap.php");
$formsConfig = require __DIR__ . "/../includes/forms/config/defaults.php";
$formsController = new FormsController($connect, $formsConfig);
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
                        $formsPanelTitle = isset($lang['How it works']) ? $lang['How it works'] : 'How it works';
                        $formsPanelSub = 'Set up integrations, mappings, and embeds to capture leads into your CRM.';
                        $formsPanelActionsHtml = '<a href="https://www.tasksession.com/docs/intregated-contact-form-7-with-task-session-forms-using-cf7-to-webhook/" target="_blank" rel="noopener" class="primary-btn">' . ts_icon('external-link') . '<span>Read full guide</span></a>';
                        include __DIR__ . '/../includes/forms/views/panel_head.php';
                        ?>
                        <p class="mb-0">This page explains the full process to set up and use the Forms / Lead Capture module. Follow the steps below to capture leads from external websites, webhooks, or API into your CRM.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include("../templates/main-footer.php"); ?>
