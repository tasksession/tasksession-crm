<?php
ob_start();
require_once("../includes/lib-initialize.php");
$title = "Forms Integrations | " . $syatem_title;
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
$integrations = $formsController->getIntegrations();

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit = $editId ? $integrationService->getById($editId) : null;
$add = isset($_GET['add']) ? true : false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_integration'])) {
        if (!empty($_POST['id'])) {
            $integrationService->update((int)$_POST['id'], $_POST);
            $_SESSION['message'] = 'Integration updated.';
        } else {
            $integrationService->create($_POST);
            $_SESSION['message'] = 'Integration created.';
        }
        redirectTo($url . "admin/forms_integrations");
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
                                                <?php echo $message; ?>
                        <?php if ($add || $edit): ?>
                        <?php
                        $formsPanelTitle = $edit ? 'Edit Integration' : 'Add Integration';
                        $formsPanelSub = 'Connect external sources and webhook authentication.';
                        $formsPanelActionsHtml = '';
                        include __DIR__ . '/../includes/forms/views/panel_head.php';
                        ?>
                        <form method="post" class="forms-settings-card settings-card">
                            <input type="hidden" name="save_integration" value="1">
                            <?php if ($edit): ?><input type="hidden" name="id" value="<?php echo (int)$edit['id']; ?>"><?php endif; ?>
                            <div class="form-group">
                                <label>Name *</label>
                                <input type="text" name="name" class="form-control" required value="<?php echo FormsSecurityHelper::escape($edit['name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Source key * (e.g. wordpress, shopify)</label>
                                <input type="text" name="source_key" class="form-control" required value="<?php echo FormsSecurityHelper::escape($edit['source_key'] ?? ''); ?>" <?php echo $edit ? 'readonly' : ''; ?>>
                            </div>
                            <div class="form-group">
                                <label>Auth token * (used for API auth)</label>
                                <input type="password" name="auth_token" class="form-control" value="<?php echo FormsSecurityHelper::escape($edit['auth_token'] ?? ''); ?>" placeholder="<?php echo $edit ? 'Leave blank to keep current' : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" class="form-control">
                                    <option value="active" <?php echo ($edit['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo ($edit['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Allowed method</label>
                                <input type="text" name="allowed_method" class="form-control" value="<?php echo FormsSecurityHelper::escape($edit['allowed_method'] ?? 'POST'); ?>">
                            </div>
                            <div class="form-group">
                                <label>Notes</label>
                                <textarea name="notes" class="form-control" rows="2"><?php echo FormsSecurityHelper::escape($edit['notes'] ?? ''); ?></textarea>
                            </div>
                            <div class="forms-form-actions">
                                <button type="submit" class="primary-btn">Save</button>
                                <a href="<?php echo $url; ?>admin/forms_integrations" class="outline-btn">Cancel</a>
                            </div>
                        </form>
                        <?php else: ?>
                        <?php
                        $formsPanelTitle = isset($lang['Forms Integrations']) ? $lang['Forms Integrations'] : 'Integrations';
                        $formsPanelSub = 'Configure webhook sources and API tokens for incoming submissions.';
                        $formsPanelActionsHtml = '<a href="' . htmlspecialchars($url . 'admin/forms_integrations?add=1', ENT_QUOTES, 'UTF-8') . '" class="primary-btn">' . ts_icon('plus') . '<span>Add Integration</span></a>';
                        include __DIR__ . '/../includes/forms/views/panel_head.php';
                        include __DIR__ . '/../includes/forms/views/integrations_list.php';
                        ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include("../templates/main-footer.php"); ?>
