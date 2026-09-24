<?php
/**
 * Client project overview — includes use __DIR__ (compatible with any PHP-FPM cwd).
 */
ob_start();

$base = __DIR__ . '/..';
require_once $base . '/includes/lib-initialize.php';

$title = ($lang['Project Overview'] ?? 'Project overview') . ' | ' . $syatem_title;
include $base . '/templates/header.php';

if (!($session->isLoggedIn())) {
    redirectTo($url . 'index.php');
}
if ((int) $_SESSION['accountStatus'] === 1) {
    redirectTo($url . 'client/index.php');
}
if ((int) $_SESSION['accountStatus'] === 3) {
    redirectTo($url . 'admin/index.php');
}

$id = (int) $session->userId;
$user = User::findById($id);
if (!$user) {
    if (isset($session) && is_object($session) && method_exists($session, 'logout')) {
        $session->logout();
    }
    redirectTo($url . 'index.php');
}

$username = $user->firstName;
$email = $user->email;
$account_stat = $user->status;
$user->regDate;

$projectId = isset($_GET['projectId']) ? (int) $_GET['projectId'] : 0;
if ($projectId <= 0) {
    redirectTo($url . 'client/projects.php');
}

$project = projects::findByProjectId($projectId);
if (!$project) {
    redirectTo($url . 'client/projects.php');
}

$isClient = false;
if ((int) $project->c_id === $id || (int) $project->main_client_id === $id) {
    $isClient = true;
} elseif (!empty($project->c_ids)) {
    $extra = array_filter(array_map('intval', explode(',', (string) $project->c_ids)));
    if (in_array($id, $extra, true)) {
        $isClient = true;
    }
}

if (!$isClient) {
    redirectTo($url . 'client/projects.php?message=unauthorized');
}

include $base . '/includes/project-sidebar-data.php';

$overviewPortal = 'client';
include $base . '/includes/project-overview-data.php';
?>
<link rel="stylesheet" href="../assets/css/project-overview.css?v=28">

<div class="page-container vh-100">
    <div class="container-fluid vh-100">
        <div class="row row-eq-height vh-100">
            <?php include $base . '/templates/sidebar.php'; ?>
            <div class="page-content">
                <?php include $base . '/templates/top-header.php'; ?>
                <div class="row bg-grey">
                    <div class="col-md-12 margin-top-10 clients project-tabs">
                        <div class="row">
                            <?php
                            $project_id = $projectId;
                            if ($project_id > 0) {
                                include $base . '/templates/project-tabs.php';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <div class="clearfix"></div>
                <div class="row vh-100">
                    <div class="container-fluid vh-100">
                        <div class="row vh-100">
                            <?php include $base . '/templates/project-sidebar.php'; ?>
                            <div class="col-lg-8 center-col">
                                <?php include $base . '/templates/partials/project-overview-cards.php'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$__po_js = $base . '/assets/js/project-overview.js';
$__po_js_v = is_file($__po_js) ? (int) filemtime($__po_js) : 1;
echo '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . 'assets/js/project-overview.js?v=' . $__po_js_v . '"></script>';
?>
<?php include $base . '/templates/main-footer.php'; ?>
