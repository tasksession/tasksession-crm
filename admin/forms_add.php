<?php
ob_start();
require_once("../includes/lib-initialize.php");
$title = "Add Form | Forms | " . $syatem_title;
include("../templates/header.php");

if (!$session->isLoggedIn()) { redirectTo($url . "index"); }
if (!isset($_SESSION['accountStatus']) || (int)$_SESSION['accountStatus'] !== 1) { redirectTo($url . "admin/index"); }

$id = $session->userId;
$user = User::findById((int)$id);
$username = $user->firstName;

require_once(__DIR__ . "/../includes/forms/includes/bootstrap.php");
$formsConfig = require __DIR__ . "/../includes/forms/config/defaults.php";
$formsController = new FormsController($connect, $formsConfig);
$formService = new FormService($connect);
$statuses = $formsController->getLeadStatuses();
$sources = $formsController->getLeadSources();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_form'])) {
    $id = $formService->createForm($_POST);
    if ($id) {
        $_SESSION['message'] = 'Form created successfully.';
        redirectTo($url . "admin/forms_edit?id=" . $id);
    } else {
        $message = '<div class="alert alert-danger">Failed to create form.</div>';
    }
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
                        $formsPanelTitle = isset($lang['Add New']) ? ($lang['Add New'] . ' ' . ($lang['Forms'] ?? 'Form')) : 'Add Form';
                        $formsPanelSub = 'Create a new embeddable form and configure default lead routing.';
                        $formsPanelActionsHtml = '';
                        include __DIR__ . '/../includes/forms/views/panel_head.php';
                        ?>
                        <?php if (!empty($message)) echo $message; ?>
                        <form method="post" class="forms-settings-card settings-card">
                            <input type="hidden" name="save_form" value="1">
                            <div class="form-group">
                                <label>Name *</label>
                                <input type="text" name="name" class="form-control" required value="<?php echo FormsSecurityHelper::escape($_POST['name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Slug *</label>
                                <input type="text" name="slug" class="form-control" required value="<?php echo FormsSecurityHelper::escape($_POST['slug'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Title</label>
                                <input type="text" name="title" class="form-control" value="<?php echo FormsSecurityHelper::escape($_POST['title'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" class="form-control">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Submit button text</label>
                                <input type="text" name="submit_button_text" class="form-control" value="<?php echo FormsSecurityHelper::escape($_POST['submit_button_text'] ?? 'Submit'); ?>">
                            </div>
                            <div class="form-group">
                                <label>Success message</label>
                                <textarea name="success_message" class="form-control" rows="2"><?php echo FormsSecurityHelper::escape($_POST['success_message'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>Default lead source</label>
                                <select name="lead_source_id" class="form-control">
                                    <option value="">-- None --</option>
                                    <?php foreach ($sources as $s): ?>
                                    <option value="<?php echo (int)$s['id']; ?>"><?php echo FormsSecurityHelper::escape($s['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Default status</label>
                                <select name="assigned_status_id" class="form-control">
                                    <option value="">-- None --</option>
                                    <?php foreach ($statuses as $st): ?>
                                    <option value="<?php echo (int)$st['id']; ?>"><?php echo FormsSecurityHelper::escape($st['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Allowed domains (comma-separated)</label>
                                <input type="text" name="allowed_domains" class="form-control" value="<?php echo FormsSecurityHelper::escape($_POST['allowed_domains'] ?? ''); ?>">
                            </div>
                            <div class="forms-form-actions">
                                <button type="submit" class="primary-btn"><?php echo ts_icon('plus'); ?><span>Create Form</span></button>
                                <a href="<?php echo $url; ?>admin/forms" class="outline-btn">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include("../templates/main-footer.php"); ?>
