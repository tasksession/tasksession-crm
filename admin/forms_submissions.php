<?php
ob_start();
require_once("../includes/lib-initialize.php");
$title = "Forms Submissions | " . $syatem_title;
include("../templates/header.php");

if (!$session->isLoggedIn()) { redirectTo($url . "index"); }
if (!isset($_SESSION['accountStatus']) || (int)$_SESSION['accountStatus'] !== 1) { redirectTo($url . "admin/index"); }
$id = $session->userId;
$user = User::findById((int)$id);
$username = $user->firstName;
require_once(__DIR__ . "/../includes/forms/includes/bootstrap.php");
$formsConfig = require __DIR__ . "/../includes/forms/config/defaults.php";
$formsController = new FormsController($connect, $formsConfig);
$submissionLogService = new SubmissionLogService($connect);
$integrations = $formsController->getIntegrations();

if (isset($_GET['delete']) && (int)$_GET['delete'] > 0) {
    $submissionLogService->delete((int)$_GET['delete']);
    $_SESSION['message'] = 'Submission deleted.';
    $redirectUrl = $url . 'admin/forms_submissions';
    $params = [];
    if (isset($_GET['integration_id']) && $_GET['integration_id'] !== '') $params['integration_id'] = (int)$_GET['integration_id'];
    if (isset($_GET['form_id']) && $_GET['form_id'] !== '') $params['form_id'] = (int)$_GET['form_id'];
    if (isset($_GET['status']) && $_GET['status'] !== '') $params['status'] = trim((string)$_GET['status']);
    if (isset($_GET['date_from']) && $_GET['date_from'] !== '') $params['date_from'] = trim((string)$_GET['date_from']);
    if (isset($_GET['date_to']) && $_GET['date_to'] !== '') $params['date_to'] = trim((string)$_GET['date_to']);
    if (!empty($params)) $redirectUrl .= '?' . http_build_query($params);
    redirectTo($redirectUrl);
}

$integrationId = isset($_GET['integration_id']) ? (int)$_GET['integration_id'] : null;
$formId = isset($_GET['form_id']) ? (int)$_GET['form_id'] : null;
$status = isset($_GET['status']) ? trim((string)$_GET['status']) : null;
$dateFrom = isset($_GET['date_from']) ? trim((string)$_GET['date_from']) : null;
$dateTo = isset($_GET['date_to']) ? trim((string)$_GET['date_to']) : null;
$viewId = isset($_GET['view']) ? (int)$_GET['view'] : 0;

$submissions = $formsController->getSubmissions(100, $integrationId, $formId, $status, $dateFrom, $dateTo);
$viewSubmission = null;
if ($viewId > 0) {
    $stmt = $connect->prepare("SELECT * FROM form_submissions WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $viewId);
    $stmt->execute();
    $res = $stmt->get_result();
    $viewSubmission = $res ? $res->fetch_assoc() : null;
    $stmt->close();
}
$formsReportsFilterAssets = ($viewId <= 0);
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
                                                <?php if ($viewSubmission): ?>
                        <?php
                        $formsPanelTitle = 'Submission #' . (int)$viewSubmission['id'];
                        $formsPanelSub = 'Review parsed payload and processing status.';
                        $formsPanelActionsHtml = '<a href="' . htmlspecialchars($url . 'admin/forms_submissions', ENT_QUOTES, 'UTF-8') . '" class="outline-btn">' . ts_icon('arrow-left') . '<span>Back to list</span></a>';
                        include __DIR__ . '/../includes/forms/views/panel_head.php';
                        ?>
                        <div class="forms-settings-card settings-card mb-4">
                            <p><strong>Status:</strong> <?php echo FormsSecurityHelper::escape($viewSubmission['status']); ?>
                            <strong>Lead ID:</strong> <?php echo !empty($viewSubmission['lead_id']) ? '<a href="' . $url . 'admin/leads">' . (int)$viewSubmission['lead_id'] . '</a>' : '-'; ?></p>
                            <p><strong>Created:</strong> <?php echo FormsSecurityHelper::escape($viewSubmission['created_at']); ?></p>
                            <?php if (!empty($viewSubmission['error_message'])): ?>
                            <p><strong>Error:</strong> <?php echo FormsSecurityHelper::escape($viewSubmission['error_message']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($viewSubmission['parsed_payload'])): ?>
                            <h6>Parsed payload</h6>
                            <div class="p-2 small"><?php echo FormsSecurityHelper::escape($viewSubmission['parsed_payload']); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <?php if (!empty($_SESSION['message'])) { echo '<div class="alert alert-success mb-3">' . FormsSecurityHelper::escape($_SESSION['message']) . '</div>'; unset($_SESSION['message']); } ?>
                        <?php
                        $formsPanelTitle = isset($lang['Submission Logs']) ? $lang['Submission Logs'] : 'Submission logs';
                        $formsPanelSub = 'Filter and review incoming form submissions and processing results.';
                        $formsPanelActionsHtml = '';
                        include __DIR__ . '/../includes/forms/views/panel_head.php';

                        $formsList = method_exists($formsController, 'getForms') ? $formsController->getForms() : [];
                        $formsSummFormId = $formId !== null ? (int)$formId : 0;
                        $aiPrefillButtons = [];
                        if ($formsSummFormId > 0) {
                            $aiPrefillButtons[] = [
                                'tool' => 'forms.summarize_submissions',
                                'payload' => ['form_id' => $formsSummFormId],
                                'label' => $lang['Summarize submissions (AI)'] ?? 'Summarize submissions (AI)',
                            ];
                            $aiPrefillButtons[] = [
                                'tool' => 'forms.followup_suggest',
                                'payload' => ['form_id' => $formsSummFormId],
                                'label' => $lang['Suggest follow-up (AI)'] ?? 'Suggest follow-up (AI)',
                            ];
                        }
                        $aiPrefillBtnClass = 'outline-btn';
                        include __DIR__ . '/../includes/forms/views/submissions_filters.php';
                        ?>
                        <?php include(__DIR__ . '/../includes/forms/views/submissions_list.php'); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/forms/views/reports_filter_scripts.php'; ?>
<?php include("../templates/main-footer.php"); ?>
