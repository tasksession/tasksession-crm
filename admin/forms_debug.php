<?php
ob_start();
require_once("../includes/lib-initialize.php");
$title = "Forms Debug | " . $syatem_title;
include("../templates/header.php");

if (!$session->isLoggedIn()) { redirectTo($url . "index.php"); }
if (!isset($_SESSION['accountStatus']) || (int)$_SESSION['accountStatus'] !== 1) { redirectTo($url . "admin/index.php"); }

$id = $session->userId;
$user = User::findById((int)$id);
$username = $user->firstName;

require_once(__DIR__ . "/../includes/forms/includes/bootstrap.php");
$formsConfig = require __DIR__ . "/../includes/forms/config/defaults.php";
$formsController = new FormsController($connect, $formsConfig);

// Determine current debug enabled state from settings (fallback: enabled)
$formsDebugEnabled = 1;
$tableCheck = mysqli_query($connect, "SHOW TABLES LIKE 'settings'");
if ($tableCheck && mysqli_fetch_assoc($tableCheck)) {
    $colCheck = mysqli_query($connect, "SHOW COLUMNS FROM settings LIKE 'forms_debug_enabled'");
    if (!$colCheck || !mysqli_fetch_assoc($colCheck)) {
        @mysqli_query($connect, "ALTER TABLE settings ADD COLUMN forms_debug_enabled TINYINT(1) NOT NULL DEFAULT 1");
        $formsDebugEnabled = 1;
    } else {
        $res = mysqli_query($connect, "SELECT forms_debug_enabled FROM settings WHERE id = 1 LIMIT 1");
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $formsDebugEnabled = (int)$row['forms_debug_enabled'];
        }
    }
}

// Handle toggle / clear actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear_debug'])) {
        // Clear database logs
        @mysqli_query($connect, "TRUNCATE TABLE forms_debug_logs");
        // Remove log file if it exists
        $logFile = isset($formsConfig['debug_log_file']) ? $formsConfig['debug_log_file'] : (SITE_ROOT . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'forms_debug.log');
        if (is_string($logFile) && $logFile !== '' && file_exists($logFile)) {
            @unlink($logFile);
        }
    } elseif (isset($_POST['toggle_debug'])) {
        // Checkbox posts 1 when checked, 0 (via hidden input) when unchecked
        $newVal = (int)$_POST['toggle_debug'] === 1 ? 1 : 0;
        mysqli_query($connect, "UPDATE settings SET forms_debug_enabled = " . (int)$newVal . " WHERE id = 1 LIMIT 1");
        $formsDebugEnabled = $newVal;
        // Also update current request logger state
        if (class_exists('FormsLoggerHelper')) {
            FormsLoggerHelper::setEnabled($formsDebugEnabled === 1);
        }
    }
    // Reload data after changes
    $level = isset($_GET['level']) ? trim((string)$_GET['level']) : null;
    $source = isset($_GET['source']) ? trim((string)$_GET['source']) : null;
    $requestId = isset($_GET['request_id']) ? trim((string)$_GET['request_id']) : null;
    $dateFrom = isset($_GET['date_from']) ? trim((string)$_GET['date_from']) : null;
    $dateTo = isset($_GET['date_to']) ? trim((string)$_GET['date_to']) : null;
}

$level = isset($_GET['level']) ? trim((string)$_GET['level']) : null;
$source = isset($_GET['source']) ? trim((string)$_GET['source']) : null;
$requestId = isset($_GET['request_id']) ? trim((string)$_GET['request_id']) : null;
$dateFrom = isset($_GET['date_from']) ? trim((string)$_GET['date_from']) : null;
$dateTo = isset($_GET['date_to']) ? trim((string)$_GET['date_to']) : null;

$debugLogs = $formsController->getDebugLogs(100, $level, $source, $requestId, $dateFrom, $dateTo);
$summary = $formsController->getSubmissionSummary(7);
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
                                                <?php
                        $formsPanelTitle = isset($lang['Forms Debug']) ? $lang['Forms Debug'] : 'Forms debug logs';
                        $formsPanelSub = 'Inspect routing, payload parsing, and submission pipeline events.';
                        ob_start();
                        ?>
                        <form method="post" class="d-flex align-items-center" style="gap:10px; margin:0;">
                            <div class="checkbox-wrapper-6">
                                <input type="hidden" name="toggle_debug" value="0">
                                <input class="tgl tgl-light" id="forms_debug_toggle" name="toggle_debug" type="checkbox" value="1" <?php echo $formsDebugEnabled ? 'checked' : ''; ?> onchange="this.form.submit();">
                                <label class="tgl-btn" for="forms_debug_toggle"></label>
                            </div>
                            <button type="submit" name="clear_debug" value="1" class="outline-btn" onclick="return confirm('Clear all forms debug logs (DB + file)?');">
                                <?php echo ts_icon('trash-box'); ?><span>Clear debug</span>
                            </button>
                        </form>
                        <?php
                        $formsPanelActionsHtml = ob_get_clean();
                        include __DIR__ . '/../includes/forms/views/panel_head.php';
                        ?>
                        <div class="forms-settings-card settings-card mb-4">
                            <h6>Submission summary (last 7 days)</h6>
                            <p>Success: <?php echo (int)$summary['success']; ?> | Failed: <?php echo (int)$summary['failed']; ?></p>
                        </div>
                        <?php include __DIR__ . '/../includes/forms/views/debug_filters.php'; ?>
                        <?php include(__DIR__ . '/../includes/forms/views/debug_list.php'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/forms/views/reports_filter_scripts.php'; ?>
<?php include("../templates/main-footer.php"); ?>
