<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : overview.php
   Purpose : Displays project and system overview information
 ================================================================================
 */
ob_start(); 
require_once("../includes/lib-initialize.php");
$title = ($lang['Project Overview'] ?? 'Project overview') . " | ". $syatem_title;
include("../templates/header.php");

if(!($session->isLoggedIn())){
    redirectTo($url."index.php");
}
if($_SESSION['accountStatus'] == 2){
    redirectTo($url."client/index.php");
}
if($_SESSION['accountStatus'] == 3){
    redirectTo($url."staff/index.php");
} 

$id = $session->userId;
$user = isset($userb) && $userb ? $userb : User::findById((int)$id);
$username = $user->firstName;
$email = $user->email;

$projectId = isset($_GET['projectId']) ? (int)$_GET['projectId'] : 0;

if(!$projectId){
    redirectTo($url."admin/projects.php");
}

include(__DIR__ . '/../includes/project-sidebar-data.php');

include(__DIR__ . '/../includes/project-overview-data.php');
?>
<link rel="stylesheet" href="../assets/css/project-overview.css?v=28">

<div class="page-container vh-100">
    <div class="container-fluid vh-100">
        <div class="row row-eq-height vh-100">
            <?php include("../templates/sidebar.php"); ?>
            <div class="page-content">
                <?php include('../templates/top-header.php'); ?>
                <div class="row bg-grey">
                    <div class="col-md-12 margin-top-10 clients project-tabs">
                        <div class="row">
                            <?php 
                            // Set project_id variable for project-tabs.php
                            $project_id = $projectId;
                            if(isset($project_id) && $project_id > 0) {
                                include('../templates/project-tabs.php');
                            } else {
                                echo "<!-- Project tabs not shown: project_id not set or is zero -->";
                            }
                            ?>
                            <?php include(__DIR__ . '/../templates/project-action.php'); ?>
                        </div>
                    </div>
                </div>
                <div class="clearfix"></div>
                <div class="row vh-100">
                    <!-- Modern UI Layout -->
                    <div class="container-fluid vh-100">
                        <div class="row vh-100">
                            <?php include("../templates/project-sidebar.php"); ?>
                            <div class="col-lg-8 center-col">
                                <?php include(__DIR__ . '/../templates/partials/project-overview-cards.php'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$__po_js = __DIR__ . '/../assets/js/project-overview.js';
$__po_js_v = is_file($__po_js) ? (int) filemtime($__po_js) : 1;
echo '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . 'assets/js/project-overview.js?v=' . $__po_js_v . '"></script>';

if (!empty($overviewLoadCompanyModals)) {
    if (empty($companyPickableClients)) {
        $companyPickableClients = [];
    }
    $companyPickableCompanies = [];
    if (isset($connect) && $connect instanceof mysqli) {
        $pq = mysqli_query($connect, 'SELECT id, name, logo_path, vat_number, email FROM client_companies WHERE deleted_at IS NULL ORDER BY name ASC');
        if ($pq) {
            while ($pr = mysqli_fetch_assoc($pq)) {
                $pid = (int) ($pr['id'] ?? 0);
                if ($pid <= 0) continue;
                $pname = (string) ($pr['name'] ?? '');
                $pvat = (string) ($pr['vat_number'] ?? '');
                $pemail = (string) ($pr['email'] ?? '');
                $companyPickableCompanies[] = [
                    'id' => $pid,
                    'name' => htmlspecialchars($pname, ENT_QUOTES, 'UTF-8'),
                    'vat' => htmlspecialchars($pvat, ENT_QUOTES, 'UTF-8'),
                    'email' => htmlspecialchars($pemail, ENT_QUOTES, 'UTF-8'),
                    'search' => strtolower($pname . ' ' . $pvat . ' ' . $pemail),
                ];
            }
            mysqli_free_result($pq);
        }
    }
    include __DIR__ . '/../templates/modals/client-companies-modal.php';
    include __DIR__ . '/../templates/modals/client-company-view-modal.php';
    $__upcf_js = __DIR__ . '/../assets/js/user-profile-custom-fields-form.js';
    $__upcf_js_v = is_file($__upcf_js) ? (int) filemtime($__upcf_js) : 1;
    echo '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . 'assets/js/user-profile-custom-fields-form.js?v=' . $__upcf_js_v . '"></script>';
    $__cc_js = __DIR__ . '/../assets/js/client-companies-modals.js';
    $__cc_js_v = is_file($__cc_js) ? (int) filemtime($__cc_js) : 1;
    echo '<script>window.clientCompanyViewTarget = "modal";</script>';
    echo '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . 'assets/js/client-companies-modals.js?v=' . $__cc_js_v . '"></script>';
}
?>
<?php include("../templates/main-footer.php"); ?> 