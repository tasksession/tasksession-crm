<?php
ob_start();
require_once("../includes/lib-initialize.php");
$title = "Forms Webhooks | " . $syatem_title;
include("../templates/header.php");

if (!$session->isLoggedIn()) { redirectTo($url . "index"); }
if (!isset($_SESSION['accountStatus']) || (int)$_SESSION['accountStatus'] !== 1) { redirectTo($url . "admin/index"); }

$id = $session->userId;
$user = User::findById((int)$id);
$username = $user->firstName;

require_once(__DIR__ . "/../includes/forms/includes/bootstrap.php");
$formsConfig = require __DIR__ . "/../includes/forms/config/defaults.php";
$formsController = new FormsController($connect, $formsConfig);
$integrations = $formsController->getIntegrations();
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$submitUrl = rtrim($baseUrl, '/') . '/includes/forms/api/submit.php';
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
                        $formsPanelTitle = isset($lang['Webhooks']) ? $lang['Webhooks'] : 'Webhook / API endpoints';
                        $formsPanelSub = 'POST JSON or form data to these URLs with your integration source key and token.';
                        $formsPanelActionsHtml = '';
                        include __DIR__ . '/../includes/forms/views/panel_head.php';
                        ?>

                        <div class="forms-settings-card mb-4">
                            <h5 class="mb-2">Endpoint URL</h5>
                            <p class="mb-2">
                                Send POST requests (JSON or form-urlencoded) to this URL. Always include
                                <code>integration=SOURCE_KEY</code> and your auth token (Authorization: Bearer or body <code>token</code>).
                            </p>
                            <div class="input-group">
                                <input
                                    type="text"
                                    id="forms-webhook-url"
                                    class="form-control"
                                    readonly
                                    value="<?php echo FormsSecurityHelper::escape($submitUrl); ?>"
                                    onclick="this.select();"
                                >
                                <button type="button" class="outline-btn" onclick="copyFormsWebhookUrl()">
                                    <?php echo ts_icon('duplicate'); ?><span>Copy</span>
                                </button>
                            </div>
                            <p class="mt-2 mb-0">
                                Example: <?php echo FormsSecurityHelper::escape($submitUrl); ?>?integration=wordpress
                            </p>
                        </div>

                        <div class="mb-4">
                            <h5 class="mb-2">Integrations (source keys)</h5>
                            <p class="mb-2">
                                Each integration defines how incoming data is mapped to CRM leads.
                                Use the source key in your webhook requests.
                            </p>
                            <ul class="mb-0">
                                <?php foreach ($integrations as $i): ?>
                                <li>
                                    <code><?php echo FormsSecurityHelper::escape($i['source_key']); ?></code>
                                    – <?php echo FormsSecurityHelper::escape($i['name']); ?>
                                </li>
                                <?php endforeach; ?>
                                <?php if (empty($integrations)): ?>
                                <li>No integrations yet. Configure one in <a href="<?php echo $url; ?>admin/forms_integrations">Integrations</a>.</li>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <div class="mb-4">
                            <h5 class="mb-2">Example payload (JSON)</h5>
                            <pre class="bg-dark text-light p-3 mb-2">{
  "name": "John Smith",
  "email": "john.smith@example.com",
  "phone": "+1 (555) 123-4567",
  "message": "I’m interested in your services."
}</pre>
                            <p class="mb-0">
                                Configure field mappings in
                                <a href="<?php echo $url; ?>admin/forms_mappings">Field Mappings</a>
                                so incoming keys map correctly to CRM lead fields.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function copyFormsWebhookUrl() {
    var input = document.getElementById('forms-webhook-url');
    if (!input) return;
    input.select();
    input.setSelectionRange(0, 99999);
    try {
        document.execCommand('copy');
    } catch (e) {
        // ignore
    }
}
</script>
<?php include("../templates/main-footer.php"); ?>
