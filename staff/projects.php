<?php
/*
************************ Task Session - Project Management System ***********************
======================== Staff Assigned Project Template ============================
*/
ob_start(); 
require_once("../includes/lib-initialize.php"); 
$title = ($lang['Projects'] ?? 'Projects') . " | " . $syatem_title;
include("../templates/header.php");

if(!($session->isLoggedIn())){
		redirectTo($url."index.php");
	}
if($_SESSION['accountStatus'] == 2){
	redirectTo($url."client/index.php");
}
if($_SESSION['accountStatus'] == 1){
	redirectTo($url."admin/index.php");
} 
//condition check for login

$id=$session->userId; //id of the current logged in user 
$user = User::findById((int)$id); //take the record of current user in an object array 	
$username=$user->firstName;;
$email=$user->email;;
$account_stat=$user->status;;
$user->regDate;

require_once("../includes/permissions.php");

if(isset($_POST['del_proj']))
{
	 if (!has_permission('project_delete')) {
		 header("Location:projects?message=fail");
		 exit;
	 }
	 $delProjId= (int)$_POST['del_id'];
	 $delProjval=$_POST['del_val'];
	
	  $deleteProj="UPDATE projects SET trash=$delProjval WHERE p_id=$delProjId";
	  $projDeleted=mysqli_query($connect, $deleteProj);
	  if($projDeleted){
		 header("Location:projects?message=psuccess");
	   } else{
			   header("Location:projects?message=fail");
	   }
}
if(isset($_POST['bulk_arc_proj']) && !empty($_POST['bulk_arc_id']))
{
    if (!has_permission('project_edit')) {
        header("Location:projects?message=fail");
        exit;
    }
    $bulk_arc_id = $_POST['bulk_arc_id'];
    $bulk_arc_val = isset($_POST['bulk_arc_val']) ? (int)$_POST['bulk_arc_val'] : 1;
    $bulk_arr = array_filter(array_map('intval', explode(',', $bulk_arc_id)));
    foreach ($bulk_arr as $bulk_id) {
        if ($bulk_id > 0) {
            $updateProj = "UPDATE projects SET archive=$bulk_arc_val WHERE p_id=$bulk_id";
            mysqli_query($connect, $updateProj);
        }
    }
    header("Location:projects?message=archive");
    exit;
}
if(isset($_POST['bulk_del_proj']) && !empty($_POST['bulk_del_id']))
{
	 if (!has_permission('project_delete')) {
		 header("Location:projects?message=fail");
		 exit;
	 }
 	 $bulk_del_id=$_POST['bulk_del_id'];
	 $bulk_del_val=$_POST['bulk_del_val'];
 $bulk_arr = explode(',', $bulk_del_id);
	foreach($bulk_arr as $bulk_id){
$deleteProj="UPDATE projects SET trash=$bulk_del_val WHERE p_id=$bulk_id";
	  $proj_bulk_Deleted=mysqli_query($connect, $deleteProj);
	}
    header("Location:projects?message=psuccess");
    exit;
}
if(isset($_POST['comp_proj']))
{
	 $comp_id=$_POST['comp_id'];
	 $comp_val=$_POST['comp_val'];
	 if($comp_val == 1){
	  $updateProj="UPDATE projects SET status=$comp_val WHERE p_id=$comp_id";
	  $comp_proj=mysqli_query($connect, $updateProj);
	  if($comp_proj){
		  // Create notification for project completion
		  require_once('../includes/notification_helper.php');
		  $project = Projects::findByProId($comp_id);
		  if ($project) {
			  NotificationHelper::projectUpdated($comp_id, $project->project_title, $session->userId);
		  }
		  header("Location:projects?message=completed");
	  } else{
		  header("Location:projects?message=fail");
	  }
	 } else{
	  $updateProj="UPDATE projects SET status=$comp_val WHERE p_id=$comp_id";
	  $comp_proj=mysqli_query($connect, $updateProj);
	  if($comp_proj){
		  // Create notification for project re-opening
		  require_once('../includes/notification_helper.php');
		  $project = Projects::findByProId($comp_id);
		  if ($project) {
			  NotificationHelper::projectUpdated($comp_id, $project->project_title, $session->userId);
		  }
		  header("Location:projects?message=reopen");
	  } else{
		  header("Location:projects?message=fail");
	  }		 
	 }
}
if(isset($_POST['arc_proj']))
{
	 if (!has_permission('project_edit')) {
		 header("Location:projects?message=fail");
		 exit;
	 }
	 $arc_id=$_POST['arc_id'];
	 $arc_val=$_POST['arc_val'];
	  $updateProj="UPDATE projects SET archive=$arc_val WHERE p_id=$arc_id";
	  $arc_proj=mysqli_query($connect, $updateProj);
	  if($arc_proj){
		  header("Location:projects?message=archive");
	  }else{
		   header("Location:projects?message=fail");
	  }
}
if(isset($_GET['message'])){
/** @var array{type:string,msg:string}|null assets/js/toast.js reads window.__toastFlash */
$toast_flash = null;
$msgstatus = $_GET['message'];

if ($msgstatus == 'success') {
    $toast_flash = array('type' => 'success', 'msg' => $lang['Record updated successfully']);
} elseif ($msgstatus == 'created') {
    $toast_flash = array('type' => 'success', 'msg' => $lang['Project has been created successfully!']);
} elseif ($msgstatus == 'fail') {
    $toast_flash = array('type' => 'error', 'msg' => $lang['Error! Please Try Again later.']);
} elseif ($msgstatus == 'psuccess') {
    $toast_flash = array('type' => 'success', 'msg' => $lang['Project has been deleted sucessfully']);
} elseif ($msgstatus == 'archive') {
    $toast_flash = array('type' => 'success', 'msg' => $lang['Project added to archive sucessfully']);
} elseif ($msgstatus == 'completed') {
    $toast_flash = array('type' => 'success', 'msg' => $lang['Project marked as Completed.']);
} elseif ($msgstatus == 'reopen') {
    $toast_flash = array('type' => 'success', 'msg' => $lang['Project status updated to re-open.']);
} elseif ($msgstatus == 'restore') {
    $toast_flash = array('type' => 'success', 'msg' => $lang['Project restored Successfully.']);
} elseif ($msgstatus == 'error_email') {
    $toast_flash = array('type' => 'error', 'msg' => $lang['Project has been created successfully! but Error sending the Email please contact site administrator.']);
}
}
$viewType = isset($_GET['view']) && $_GET['view'] === 'grid' ? 'grid' : 'table';

// Add filter for showing only staff's projects or all projects
$showAllProjects = isset($_GET['all_projects']) && $_GET['all_projects'] === '1';

// Security check: Prevent access to all_projects if user doesn't have permission
if ($showAllProjects && !has_permission('project_view_all')) {
    // Redirect to My Projects if user tries to access All Projects without permission
    $redirectUrl = 'projects';
    if ($viewType === 'grid') {
        $redirectUrl .= '?view=grid';
    }
    if (isset($_GET['status'])) {
        $redirectUrl .= ($viewType === 'grid' ? '&' : '?') . 'status=' . htmlspecialchars($_GET['status']);
    }
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $redirectUrl .= ((strpos($redirectUrl, '?') !== false) ? '&' : '?') . 'search=' . htmlspecialchars($_GET['search']);
    }
    redirectTo($redirectUrl);
}

// Pagination variables
$limit = 15;
$page = isset($_GET["page"]) ? max(1, (int)$_GET["page"]) : 1;
$start_from = ($page - 1) * $limit;

// Get search query from URL
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchPattern = ($searchQuery !== '') ? "%{$searchQuery}%" : '';
$searchCondition = '';
if (!empty($searchQuery)) {
    $safeSearch = mysqli_real_escape_string($connect, $searchQuery);
    $searchCondition = " AND project_title LIKE '%$safeSearch%'";
}

// Add status filter logic at the top, after $searchCondition
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$statusCondition = '';
if ($statusFilter !== '' && ($statusFilter == '0' || $statusFilter == '1')) {
    $statusCondition = " AND status = $statusFilter";
}

require_once("../includes/list-pagination.php");
require_once("../includes/list-bulk-helpers.php");

function buildProjectsListUrl(array $overrides = array()) {
    global $showAllProjects, $statusFilter, $searchQuery, $viewType;

    $params = array();
    if ($showAllProjects) {
        $params['all_projects'] = '1';
    }
    if ($statusFilter !== '' && ($statusFilter === '0' || $statusFilter === '1')) {
        $params['status'] = $statusFilter;
    }
    if ($searchQuery !== '') {
        $params['search'] = $searchQuery;
    }
    if ($viewType === 'grid') {
        $params['view'] = 'grid';
    }
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === false || $value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }
    $qs = http_build_query($params);
    return 'projects' . ($qs !== '' ? '?' . $qs : '');
}

$projectsGridViewUrl = buildProjectsListUrl(array('view' => 'grid'));
$projectsTableViewUrl = buildProjectsListUrl(array('view' => null));
$projectsSearchClearUrl = buildProjectsListUrl(array('search' => null));

// Project queries based on permission
if (has_permission('project_view_all')) {
    // Show all projects (admin logic)
    $projectFilterCondition = '';
    if (!$showAllProjects) {
        // Show only projects where current staff is assigned
        $projectFilterCondition = " AND find_in_set($session->userId, s_ids)";
    }
    
    $projects = projects::findBySql("SELECT * FROM projects WHERE archive = 0 AND trash != 1" . $projectFilterCondition);
    $archiveProjects = projects::findBySql("SELECT * FROM projects WHERE archive = 1 AND trash != 1" . $projectFilterCondition);
    $recentProjects = projects::findBySql("
        SELECT * FROM projects 
        WHERE archive = 0 AND trash != 1 $projectFilterCondition $searchCondition $statusCondition
        ORDER BY p_id DESC 
        LIMIT $start_from, $limit
    ");
    $recentProjects_page = projects::findBySql("
        SELECT * FROM projects 
        WHERE archive = 0 AND trash != 1 $projectFilterCondition $searchCondition $statusCondition
        ORDER BY p_id DESC
    ");
} else {
    // Only assigned projects (staff logic) - Force show only assigned projects regardless of URL parameters
    $id = $session->userId;
    $projectFilterCondition = " AND find_in_set($id, s_ids)";
    $showAllProjects = false; // Force to false for users without permission
    
    $projects = projects::findBySql("SELECT * FROM projects WHERE archive = 0 AND trash != 1" . $projectFilterCondition);
    $archiveProjects = projects::findBySql("SELECT * FROM projects WHERE archive = 1 AND trash != 1" . $projectFilterCondition);
    $recentProjects = projects::findBySql("
        SELECT * FROM projects 
        WHERE archive = 0 AND trash != 1 $projectFilterCondition $searchCondition $statusCondition
        ORDER BY p_id DESC 
        LIMIT $start_from, $limit
    ");
    $recentProjects_page = projects::findBySql("
        SELECT * FROM projects 
        WHERE archive = 0 AND trash != 1 $projectFilterCondition $searchCondition $statusCondition
        ORDER BY p_id DESC
    ");
}

// Archive projects count
if (has_permission('project_view_all')) {
    $archiveProjects = projects::findBySql("SELECT * FROM projects WHERE archive = 1 AND trash != 1");
    $recentProjectsTrash = projects::findBySql("SELECT * FROM projects WHERE trash=1");
} else {
    $id = $session->userId;
    $archiveProjects = projects::findBySql("SELECT * FROM projects WHERE find_in_set($id, s_ids) AND archive = 1 AND trash != 1");
    $recentProjectsTrash = projects::findBySql("SELECT * FROM projects WHERE find_in_set($id, s_ids) AND trash=1");
}

// Get currency symbol for budget display (only if milestone_view permission exists)
$currency_symbol = '$';
if (has_permission('milestone_view')) {
    $settings = settings::findById(1);
    $default_currency = isset($settings->system_currency) && !empty($settings->system_currency) ? $settings->system_currency : null;
    if ($default_currency) {
        $currency_parts = explode(',', $default_currency);
        if (count($currency_parts) >= 2) {
            $currency_symbol = trim($currency_parts[1]);
        }
    }
}
$canCreateProject = has_permission('project_create');
$canDeleteProject = has_permission('project_delete');
$canArchiveProject = has_permission('project_edit');
$showProjectBulkToolbar = $canDeleteProject || $canArchiveProject;
?>
	<div class="page-container">
		<div class="container-fluid">
			<div class="row row-eq-height">
				<?php  include("../templates/sidebar.php"); ?>
					<div class="page-content">
						<?php include('../templates/top-header.php'); ?>
                <link rel="stylesheet" href="../assets/css/kanban-bulk.css?v=9">
							<div class="row bg-grey">
								<div class="col-md-12 project-tabs">
									<div class="row">
										<div class="project-tabs-header">
											<div class="scrollable-tabs-container d-flex col-gap-40 col-gap-40-sep">
												<?php 
												?>
											   <div class="main-heading"><h1><?php echo $lang['Projects']; ?> <span>(<?php echo count($projects); ?>)</span></h1></div>
												<div class="icon-container sep">
												<!-- Project Filter Buttons -->
												<?php 
												// Get counts for filter buttons
												$myProjectsCount = projects::findBySql("SELECT * FROM projects WHERE archive = 0 AND trash != 1 AND find_in_set($session->userId, s_ids)");
												?>
												<a href="<?php echo htmlspecialchars(buildProjectsListUrl(array('all_projects' => null)), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo !$showAllProjects ? 'active' : ''; ?>">
													<?php echo ts_icon('tasks', 'h-6'); ?>
													<?php echo $lang['My Projects']; ?> (<?php echo count($myProjectsCount); ?>)
												</a>
												<?php if (has_permission('project_view_all')): ?>
												<?php 
												// Get count for all projects (only if permission exists)
												$allProjectsCount = projects::findBySql("SELECT * FROM projects WHERE archive = 0 AND trash != 1");
												?>
												<a href="<?php echo htmlspecialchars(buildProjectsListUrl(array('all_projects' => '1')), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $showAllProjects ? 'active' : ''; ?>">
													<?php echo ts_icon('inbox-stack', 'h-6'); ?>
													<?php echo $lang['All Projects']; ?> (<?php echo count($allProjectsCount); ?>)
												</a>
												<?php endif; ?>
													<a href="archive">
														<?php echo ts_icon('archive-box'); ?>
												<?php echo $lang['Archive Projects']; ?> (<?php echo count($archiveProjects); ?>) </a>
													<a href="trash" class="pm-trashbox">
													<?php echo ts_icon('trash-box'); ?>
												<?php echo $lang['Trash Projects']; ?> (<?php echo count($recentProjectsTrash); ?>) </a>
												</div>
											</div>
										</div>
										<div class="search">
							   <div class="search-icon border-btn-a" onclick="toggleSearch()">
									 <?php echo ts_icon('search', 'w-2'); ?>
                                            </div>
                                            <form method="GET" action="projects" class="search-form" id="searchForm">
                                                <input type="hidden" name="page" value="1">
                                                <?php if($statusFilter !== ''): ?>
                                                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                                                <?php endif; ?>
                                                <?php if($showAllProjects): ?>
                                                    <input type="hidden" name="all_projects" value="1">
                                                <?php endif; ?>
                                                <?php if($viewType === 'grid'): ?>
                                                    <input type="hidden" name="view" value="grid">
                                                <?php endif; ?>
                                                <div class="input-group">
                                                    <span class="search-field-icon">
                                                        <?php echo ts_icon('search', 'w-2'); ?>
                                                    </span>
                                                   <input type="text" id="task-search" name="search" class="form-control" placeholder="<?php echo htmlspecialchars($lang['Search projects here...'] ?? 'Search projects here...', ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($searchQuery ?? ''); ?>">
                                                    <?php if (!empty($searchQuery)): ?>
                                                        <a href="<?php echo htmlspecialchars($projectsSearchClearUrl, ENT_QUOTES, 'UTF-8'); ?>" class="cross" title="<?php echo htmlspecialchars($lang['Close'] ?? 'Close', ENT_QUOTES, 'UTF-8'); ?>">
                                                            <?php echo ts_icon('close', 'w-2'); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="#" class="cross" onclick="toggleSearch(); return false;" title="<?php echo htmlspecialchars($lang['Close'] ?? 'Close', ENT_QUOTES, 'UTF-8'); ?>">
                                                            <?php echo ts_icon('close', 'w-2'); ?>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </form>
                                        </div>
									<?php if ($showProjectBulkToolbar): ?>
									<div class="edit-overview-btn ts-list-bulk-toolbar-wrap d-none d-md-block">
										<div class="icon-container sep ts-list-bulk-toolbar" data-ts-list-bulk-toolbar="projects">
											<div class="pm-trash task-trash align-middle d-flex col-gap-5">
												<a href="#" onclick="TsListBulk.enter('projects'); return false;" class="bulk-delete-tab red border-btn-a" id="tsListBulk_projects_bulkSelectTab" title="<?php echo htmlspecialchars($lang['Bulk Select'] ?? 'Bulk select', ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($lang['Bulk Select'] ?? 'Bulk select', ENT_QUOTES, 'UTF-8'); ?>">
													<?php echo ts_icon('duplicate', 'w-2'); ?>
												</a>
												<div class="media-view-toggle" id="projectsViewToggle" role="group" aria-label="View mode">
													<a href="<?php echo htmlspecialchars($projectsGridViewUrl, ENT_QUOTES, 'UTF-8'); ?>" class="media-view-toggle__btn border-btn-a<?php echo $viewType === 'grid' ? ' is-active' : ''; ?>" title="<?php echo htmlspecialchars($lang['Grid View'] ?? 'Grid view', ENT_QUOTES, 'UTF-8'); ?>">
														<?php echo ts_icon('view-grid', 'w-2'); ?>
													</a>
													<a href="<?php echo htmlspecialchars($projectsTableViewUrl, ENT_QUOTES, 'UTF-8'); ?>" class="media-view-toggle__btn border-btn-a<?php echo $viewType !== 'grid' ? ' is-active' : ''; ?>" title="<?php echo htmlspecialchars($lang['Table View'] ?? 'Table view', ENT_QUOTES, 'UTF-8'); ?>">
														<?php echo ts_icon('table', 'w-2'); ?>
													</a>
												</div>
												<a href="#" onclick="TsListBulk.exit('projects'); return false;" class="bulk-delete-tab border-btn-a" id="tsListBulk_projects_bulkBackTab" style="display:none;">
													<?php echo ts_icon('arrow-left', 'w-2'); ?>
													<span><?php echo $lang['Back'] ?? 'Back'; ?></span>
												</a>
												<a href="#" onclick="TsListBulk.selectAll('projects'); return false;" class="bulk-delete-tab border-btn-a" id="tsListBulk_projects_bulkSelectAllTab" style="display:none;">
													<?php echo ts_icon('check-circle', 'w-2'); ?>
													<span id="tsListBulk_projects_bulkSelectAllLabel"><?php echo $lang['Select all'] ?? 'Select all'; ?></span>
												</a>
												<?php if ($canDeleteProject): ?>
												<a href="#" onclick="TsListBulk.deleteSelected('projects'); return false;" class="bulk-delete-tab border-btn-a" id="tsListBulk_projects_bulkDeleteTab" style="display:none;">
													<?php echo ts_icon('delete', 'w-2'); ?>
													<span id="tsListBulk_projects_bulkDeleteLabel"><?php echo $lang['Delete'] ?? 'Delete'; ?></span>
												</a>
												<?php endif; ?>
												<?php if ($canArchiveProject): ?>
												<a href="#" onclick="TsListBulk.archiveSelected('projects'); return false;" class="bulk-delete-tab border-btn-a" id="tsListBulk_projects_bulkArchiveTab" style="display:none;">
													<?php echo ts_icon('archive-box', 'w-2'); ?>
													<span id="tsListBulk_projects_bulkArchiveLabel"><?php echo $lang['Move to Archive'] ?? 'Move to archive'; ?></span>
												</a>
												<?php endif; ?>
											</div>
										</div>
									</div>
									<?php else: ?>
									<div class="edit-overview-btn ts-list-bulk-toolbar-wrap d-none d-md-block">
										<div class="icon-container sep">
											<div class="pm-trash task-trash align-middle d-flex col-gap-5">
												<div class="media-view-toggle" id="projectsViewToggle" role="group" aria-label="View mode">
													<a href="<?php echo htmlspecialchars($projectsGridViewUrl, ENT_QUOTES, 'UTF-8'); ?>" class="media-view-toggle__btn border-btn-a<?php echo $viewType === 'grid' ? ' is-active' : ''; ?>" title="<?php echo htmlspecialchars($lang['Grid View'] ?? 'Grid view', ENT_QUOTES, 'UTF-8'); ?>">
														<?php echo ts_icon('view-grid', 'w-2'); ?>
													</a>
													<a href="<?php echo htmlspecialchars($projectsTableViewUrl, ENT_QUOTES, 'UTF-8'); ?>" class="media-view-toggle__btn border-btn-a<?php echo $viewType !== 'grid' ? ' is-active' : ''; ?>" title="<?php echo htmlspecialchars($lang['Table View'] ?? 'Table view', ENT_QUOTES, 'UTF-8'); ?>">
														<?php echo ts_icon('table', 'w-2'); ?>
													</a>
												</div>
											</div>
										</div>
									</div>
									<?php endif; ?>
							<div class="edit-overview-btn">
								 <td class="extra-height">
                                    <div class="action-toggle border-btn-a" data-bs-toggle="collapse" data-bs-target="#project-menu-filters">
                                        <span class="action-text"><?php echo $lang['Filters']; ?></span><span class="mobile-ellipsis"><?php echo ts_icon('ellipsis', 'w-2'); ?></span>
										<?php echo ts_icon('filter', 'w-2'); ?>
                                    </div>
										<div id="project-menu-filters" class="toggle-action collapse shadow-dept">
                                        <ul>
										<?php if ($canCreateProject): ?>
										<li class="primary-btn d-block d-md-none">
                                                <a href="add-new-project">
                                                    <span>	<?php echo $lang['Create project']; ?> <?php echo ts_icon('plus', 'dropdown-toggle-icon h-6'); ?></span>
                                                </a>
										</li>
										<li class="menu-divider d-block d-md-none">
											<hr class="dropdown-divider">
										</li>
										<?php endif; ?>
                                            <li class="<?php echo !isset($_GET['status']) ? 'active' : ''; ?>" data-status="all">
                                                <a href="<?php echo htmlspecialchars(buildProjectsListUrl(array('status' => null)), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php echo ts_icon('dot', 'tasksession-timer-log-menu-ico me-2'); ?>
                                                    <span><?php echo $lang['All Projects']; ?></span>
                                                </a>
                                            </li>
											
											<li class="<?php echo (isset($_GET['status']) && $_GET['status'] == 1) ? 'active' : ''; ?>">
                                                <a href="<?php echo htmlspecialchars(buildProjectsListUrl(array('status' => '1')), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php echo ts_icon('dot', 'tasksession-timer-log-menu-ico me-2'); ?>
                                                    <span><?php echo $lang['Completed']; ?></span>
                                                </a>
                                            </li>
                                           
										   	<li class="<?php echo (isset($_GET['status']) && $_GET['status'] == 0) ? 'active' : ''; ?>">
                                                <a href="<?php echo htmlspecialchars(buildProjectsListUrl(array('status' => '0')), ENT_QUOTES, 'UTF-8'); ?>">
													 <?php echo ts_icon('dot', 'tasksession-timer-log-menu-ico me-2'); ?>
													 <span><?php echo $lang['In Progress']; ?></span>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </div>
						<div class="d-none d-md-block">
							<?php if ($canCreateProject): ?>
							<a href="add-new-project" class="primary-btn">
							<?php echo $lang['Create project']; ?> <?php echo ts_icon('plus', 'w-2'); ?></a>
							<?php endif; ?>
						</div>
					 </div>
                    </div>
                  </div>
							<?php 
								$total_records = is_array($recentProjects_page) ? count($recentProjects_page) : 0;
								$total_pages = max(1, (int) ceil($total_records / $limit));
								$projectsPageRowCount = is_array($recentProjects) ? count($recentProjects) : 0;
								$listPaginationParams = array();
								if ($showAllProjects) {
									$listPaginationParams['all_projects'] = 1;
								}
								if ($statusFilter !== '') {
									$listPaginationParams['status'] = $statusFilter;
								}
								if ($viewType === 'grid') {
									$listPaginationParams['view'] = 'grid';
								}
								if (!empty($searchQuery)) {
									$listPaginationParams['search'] = $searchQuery;
								}
								$projectsPaginationMeta = tasksession_list_pagination_meta(
									$total_records,
									$start_from,
									$projectsPageRowCount,
									$listPaginationParams
								);
								?>
											<div class="vh-100" data-ts-list-bulk-root="projects">
												<?php if ($viewType === 'table'): ?>
													<div class="table-responsive scroll-x vh-100">
														<table class="table table-new projectspage">
															<thead>
																<tr>
																	<?php if ($showProjectBulkToolbar): ?>
																		<?php echo tasksession_list_bulk_checkbox_th_html(); ?>
																	<?php else: ?>
																		<th class="text-center text-muted" style="width:2.75rem;"><?php echo htmlspecialchars($lang['No.'] ?? 'No.', ENT_QUOTES, 'UTF-8'); ?></th>
																	<?php endif; ?>
																	<th width="26%">
																		<?php echo $lang['Project Name']; ?>
																	</th>
																	<th>
																		<?php echo $lang['Assigned Team']; ?>
																	</th>
																	<th>
																		<?php echo $lang['Client']; ?>
																	</th>
																	<th>
																		<?php echo $lang['Deadline']; ?>
																	</th>
																	<th>
																		<?php echo $lang['Status']; ?>
																	</th>
																	<?php if (has_permission('milestone_view')): ?>
																	<th>
																		<?php echo $lang['Budget']; ?>
																	</th>
																	<?php endif; ?>
																	<th>
																		<?php echo $lang['Task']; ?>
																	</th>
																	<th>
																		<?php echo $lang['Options']; ?>
																	</th>
																</tr>
															</thead>
															<tbody id="projects-tbl">
																<?php 
											  $counter=1;
										if($recentProjects == NULL){
											  $colspan = has_permission('milestone_view') ? 9 : 8;
											  echo '<tr><td colspan="'.$colspan.'">'.$lang['No projects available. Create a new project to proceed.'].'</td></tr>';
										  }
										  foreach($recentProjects as $recentProject){ ?>
																<tr>
																	<?php if ($showProjectBulkToolbar): ?>
																	<td class="bs-checkbox ts-list-bulk-checkbox-cell">
																		<input data-index="<?php echo $counter; ?>" value="<?php echo $recentProject->p_id ?>" name="btSelectItem" type="checkbox">
																	</td>
																	<?php else: ?>
																	<td class="text-center text-muted"><?php echo (int) ($start_from + $counter); ?></td>
																	<?php endif; ?>
																	<td><div class="tbl-ttl">
																		<?php echo $recentProject->project_title;?><div>
																	</td>
																	<td class="clients-rpt" style="text-align: left;">
																 <div class="d-flex align-items-center">
																<?php 
															  $s_ids = $recentProject->s_ids;
															  $st_ids = explode(',', $s_ids);
															  $team_counter = 0;
															  foreach($st_ids as $st_id){
																  if($st_id != $recentProject->c_id && $st_id != 0){
																	  $team_counter++;
																	  if($team_counter > 3){} else {

																$user2 = user::findById($st_id); 
																echo '<div class="user-box">';
																echo '<form action="../discussion?project_id='.$recentProject->p_id .'" method="post"><input type="hidden" name="user_id" value="'.$st_id.'" /><input type="hidden" name="project_id" value="'.$recentProject->p_id .'" /><button name="chat" type="submit" data-bs-toggle="tooltip" data-bs-placement="top" title="'.$user2->firstName.'">';
																echo getUserAvatarHtml($st_id, $user2->firstName, $user2->lastName ?? '', 36, 36, '', $user2->firstName);
																echo '</button></form>';
																echo '</div>'; 
															  }
																  }
																  }
																 
																  if($team_counter > 3){
																	  $more = $team_counter-3;
																	   echo '<div class="plus-more">+'. $more .'</div>'; 
																  }
																  ?>
																</td>
																<td class="clients-rpt">
																 <div class="d-flex align-items-center">
																	<?php 
																	// Get all clients (main + additional)
																	$allClients = array();
																	
																	// Add main client first
																	$mainClient = user::findById($recentProject->main_client_id ?: $recentProject->c_id); 
																	if ($mainClient) {
																		$allClients[] = array(
																			'id' => $mainClient->id,
																			'firstName' => $mainClient->firstName,
																			'lastName' => $mainClient->lastName ?? '',
																			'isMain' => true
																		);
																	}
																	
																	// Add additional clients
																	if (!empty($recentProject->c_ids)) {
																		$allClientIds = array_filter(explode(',', $recentProject->c_ids));
																		$additionalClients = array_filter($allClientIds, function($id) use ($recentProject) {
																			return $id != $recentProject->main_client_id && $id != $recentProject->c_id;
																		});
																		
																		foreach($additionalClients as $clientId) {
																			$client = user::findById($clientId);
																			if ($client) {
																				$allClients[] = array(
																					'id' => $client->id,
																					'firstName' => $client->firstName,
																					'lastName' => $client->lastName ?? '',
																					'isMain' => false
																				);
																			}
																		}
																	}
																	
																	// Display only first 2 clients
																	$displayedCount = 0;
																	foreach($allClients as $client) {
																		if($displayedCount >= 2) break;
																		
																		$title = $client['isMain'] ? $client['firstName'] . ' (Main)' : $client['firstName'];
																		echo '<div class="user-box">';
																		echo '<form action="../discussion?project_id='.$recentProject->p_id .'" method="post"><input type="hidden" name="user_id" value="'.$client['id'].'" /><input type="hidden" name="project_id" value="'.$recentProject->p_id .'" /><button name="chat" type="submit" data-bs-toggle="tooltip" data-bs-placement="top" title="'.$title.'">';
																		echo getUserAvatarHtml($client['id'], $client['firstName'], $client['lastName'], 36, 36, 'img-fluid rounded-circle', $client['firstName']);
																		echo '</button></form>';
																		echo '</div>';
																		$displayedCount++;
																	}
																	
																	// Show + indicator if there are more than 2 clients
																	if(count($allClients) > 2) {
																		$more = count($allClients) - 2;
																		echo '<div class="plus-more shadow-dept">+'. $more .'</div>';
																	}
																	?>
																	</div>
																</td>
																<td class="red-text">
																	<?php echo $recentProject->end_time;?>
																</td>
																<td class="prostatus">
																	<?php $status = $recentProject->status;
															  $archive = $recentProject->archive;
															  $trash = $recentProject->trash;
															  if($status == 0){ ?> <span class="badge inprogress"><?php echo $lang['In Progress']; ?></span>
																				<?php } else { ?> <span class="badge completed"><?php echo $lang['Completed']; ?></span>
																					<?php }?>
																</td>
																<?php if (has_permission('milestone_view')): ?>
																<td class="pro-bdgt">
																	<?php echo $currency_symbol . $recentProject->budget;?>
																</td>
																<?php endif; ?>
																<td class="tbl-tasks extra-height">
																	<?php 
																		$projectId = $recentProject->p_id;
																		global $database;
																		$taskQuery = "SELECT COUNT(*) as task_count FROM tasks WHERE project_id = $projectId";
																		$taskResult = $database->query($taskQuery);
																		$taskCount = 0;
																		if($taskRow = $database->fetchArray($taskResult)) {
																			$taskCount = $taskRow['task_count'];
																		}
																		$completedTaskQuery = "SELECT COUNT(*) as completed_count FROM tasks WHERE project_id = $projectId AND status = 'done'";
																		$completedTaskResult = $database->query($completedTaskQuery);
																		$completedTaskCount = 0;
																		if($completedTaskRow = $database->fetchArray($completedTaskResult)) {
																			$completedTaskCount = $completedTaskRow['completed_count'];
																		}
																		$percent = ($taskCount > 0) ? round(($completedTaskCount / $taskCount) * 100) : 0;
																	?>
																	<div class="mb-1 d-flex col-gap-5"> <?php echo $completedTaskCount; ?>/<?php echo $taskCount; ?> <span class="text-align-right flex-grow"><?php echo $percent; ?>%</span></div>
																	<div class="progress" style="height:8px;">
																		<div class="progress-bar" role="progressbar" style="width: <?php echo $percent; ?>%; background: <?php echo $percent < 30 ? '#f66' : ($percent < 70 ? '#f9b233' : '#4caf50'); ?>;" aria-valuenow="<?php echo $percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
																	</div>
																</td>
																<td class="extra-height">
																	<div class="action-toggle" data-bs-toggle="collapse" data-bs-target="#actionDropdown<?php echo $recentProject->p_id;?>">
																		<?php echo $lang['Action']; ?><?php echo ts_icon('chevron-down'); ?></div>
																	<div id="actionDropdown<?php echo $recentProject->p_id;?>" class="toggle-action collapse  shadow-dept">
																		<ul>
																			<li>

																				<a href="overview?projectId=<?php echo $recentProject->p_id;?>">
																					<?php echo ts_icon('info', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Overview']; ?>
																				</a>
																			</li>
																			<li>
																				<form action="../discussion?project_id=<?php echo $recentProject->p_id;?>" method="post">
																					<input type="hidden" name="user_id" value="<?php echo $recentProject->c_id;?>" />
																					<input type="hidden" name="project_id" value="<?php echo $recentProject->p_id;?>" />
																					<button type="submit" name="chat" class="dropdown-item">
																						<?php echo ts_icon('chat', 'tasksession-timer-log-menu-ico me-2'); ?> <?php echo $lang['Discussion']; ?>
																					</button>
																				</form>
																			</li>
																			<li>
																				<a href="task?projectId=<?php echo $recentProject->p_id; ?>">
																					<?php echo ts_icon('tasks', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Tasks']; ?>
																				</a>
																			</li>	
																			<?php if (has_permission('project_edit')): ?>
																			<li>
																					<a href="edit-project?id=<?php echo $recentProject->p_id;?>"><?php echo ts_icon('edit', 'tasksession-timer-log-menu-ico me-2'); ?> <?php echo $lang['Edit Project']; ?></a>
																				
																			</li>
																			<?php endif; ?>
																			<li>
																				<form method="post" action="#">
																					<input type="hidden" value="<?php echo $recentProject->p_id;?>" name="comp_id" />
																					<input type="hidden" value="<?php if($status == 0){ echo '1';}else { echo '0';} ?>" name="comp_val" />
																					<button type="submit" name="comp_proj">
																						<?php if($status == 0){
																echo ts_icon('check-circle', 'tasksession-timer-log-menu-ico me-2') . ' ' . $lang['Mark as complete'];
																}else{ 
																echo ts_icon('check', 'tasksession-timer-log-menu-ico me-2') . $lang['Re-open'];
																} ?> </button>
																				</form>
																			</li>
																			<li>
																				<form method="post" action="#">
																					<input type="hidden" value="<?php echo $recentProject->p_id;?>" name="arc_id" />
																					<input type="hidden" value="<?php if($archive == 0){ echo '1';}else { echo '0';} ?>" name="arc_val" />
																					<button type="submit" name="arc_proj" class="dropdown-item">
																						<?php if($archive == 0){
																echo ts_icon('archive', 'tasksession-timer-log-menu-ico me-2') . ' ' . $lang['Move to Archive'];
																}else{ 
																echo ts_icon('archive', 'tasksession-timer-log-menu-ico me-2') . ' ' . $lang['Move to Projects'];
																} ?> </button>
																				</form>
																			</li>
																			<?php if (has_permission('project_delete')): ?>
																			<li>
																					<form method="post" action="#">
																						<input type="hidden" value="<?php echo $recentProject->p_id;?>" name="del_id" />
																						<input type="hidden" value="<?php if($trash == 0){ echo '1';}else { echo '0';} ?>" name="del_val" />
																						<button type="submit" name="del_proj">
																							<?php echo ts_icon('delete', 'tasksession-timer-log-menu-ico me-2'); ?> <?php echo $lang['Delete Project']; ?>
																						</button>
																					</form>
																				</li>
																				<?php endif; ?>
																		</ul>
																	</div>
																</td>
															</tr>
															<?php 
														 			$counter++;	
																} ?>
															</tbody>
														</table>
													</div>
													<?php
													if ($total_records > $limit) {
														tasksession_render_list_pagination(
															$projectsPaginationMeta,
															$total_records,
															$total_pages,
															$page
														);
													}
													?>
												<?php else: ?>
													<div class="row" id="project-grid">
													
														<?php foreach($recentProjects as $recentProject): ?>
															<?php
															$projectId = $recentProject->p_id;
															global $database;
															// Total tasks
															$taskQuery = "SELECT COUNT(*) as task_count FROM tasks WHERE project_id = $projectId";
															$taskResult = $database->query($taskQuery);
															$taskCount = 0;
															if($taskRow = $database->fetchArray($taskResult)) {
																$taskCount = $taskRow['task_count'];
															}
															// Completed tasks
															$completedTaskQuery = "SELECT COUNT(*) as completed_count FROM tasks WHERE project_id = $projectId AND status = 'done'";
															$completedTaskResult = $database->query($completedTaskQuery);
															$completedTaskCount = 0;
															if($completedTaskRow = $database->fetchArray($completedTaskResult)) {
																$completedTaskCount = $completedTaskRow['completed_count'];
															}
															$percent = ($taskCount > 0) ? round(($completedTaskCount / $taskCount) * 100) : 0;
															?>
															<div class="col-md-4  col-lg-4 col-xl-3 mb-3">
																<div class="card project-card" style="position: relative;">
																	<!-- 2-dot dropdown menu -->
																	<div class="dropdown card-action-dropdown" style="position: absolute; top: 12px; right: 16px;">
																		<button class="btn btn-link dropdown-toggle" type="button" id="dropdownMenu<?php echo $recentProject->p_id; ?>" data-bs-toggle="dropdown" aria-expanded="false" style="color: #333; font-size: 20px; text-decoration: none;">
																			<span class="light-grey" style="font-size: 20px; letter-spacing: &#8226;">&#8226;&#8226;&#8226;</span>
																		</button>
																		<ul class="dropdown-menu" aria-labelledby="dropdownMenu<?php echo $recentProject->p_id; ?>">
																			<li>
																				<a href="overview?projectId=<?php echo $recentProject->p_id; ?>">
																					<?php echo ts_icon('info', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Overview']; ?>
																				</a>
																			</li>
																			<li>
																				<form action="../discussion?project_id=<?php echo $recentProject->p_id;?>" method="post" style="display:inline;">
																					<input type="hidden" name="user_id" value="<?php echo $recentProject->c_id;?>" />
																					<input type="hidden" name="project_id" value="<?php echo $recentProject->p_id;?>" />
																					<button type="submit" name="chat" class="dropdown-item">
																						<?php echo ts_icon('chat', 'tasksession-timer-log-menu-ico me-2'); ?> <?php echo $lang['Discussion']; ?>
																					</button>
																				</form>
																			</li>
																			<li>
																				<a href="task?projectId=<?php echo $recentProject->p_id; ?>">
																					<?php echo ts_icon('tasks', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Tasks']; ?>
																				</a>
																			</li>
																			<li>
																				<?php if (has_permission('project_edit')): ?>
																					<a href="edit-project?id=<?php echo $recentProject->p_id;?>" class="dropdown-item"><?php echo ts_icon('edit', 'tasksession-timer-log-menu-ico me-2'); ?> <?php echo $lang['Edit Project']; ?></a>
																				<?php endif; ?>
																			</li>
																			<li>
																				<form method="post" action="#">
																					<input type="hidden" value="<?php echo $recentProject->p_id;?>" name="comp_id" />
																					<input type="hidden" value="<?php if($status == 0){ echo '1';}else { echo '0';} ?>" name="comp_val" />
																					<button type="submit" name="comp_proj" class="dropdown-item">
																						<?php if($status == 0){
																echo ts_icon('check-circle', 'tasksession-timer-log-menu-ico me-2') . ' ' . $lang['Mark as complete'];
																}else{ 
																echo ts_icon('check', 'tasksession-timer-log-menu-ico me-2') . $lang['Re-open'];
																} ?> </button>
																				</form>
																			</li>
																			<li>
																				<form method="post" action="#">
																					<input type="hidden" value="<?php echo $recentProject->p_id;?>" name="arc_id" />
																					<input type="hidden" value="<?php if($archive == 0){ echo '1';}else { echo '0';} ?>" name="arc_val" />
																					<button type="submit" name="arc_proj" class="dropdown-item">
																						<?php if($archive == 0){
																echo ts_icon('archive', 'tasksession-timer-log-menu-ico me-2') . ' ' . $lang['Move to Archive'];
																}else{ 
																echo ts_icon('archive', 'tasksession-timer-log-menu-ico me-2') . ' ' . $lang['Move to Projects'];
																} ?> </button>
																				</form>
																			</li>
																			<li>
																				<?php if (has_permission('project_delete')): ?>
																					<form method="post" action="#" style="display:inline;">
																						<input type="hidden" value="<?php echo $recentProject->p_id;?>" name="del_id" />
																						<input type="hidden" value="<?php if($trash == 0){ echo '1';}else { echo '0';} ?>" name="del_val" />
																						<button type="submit" name="del_proj" class="dropdown-item">
																							<?php echo ts_icon('delete', 'tasksession-timer-log-menu-ico me-2'); ?> <?php echo $lang['Delete Project']; ?>
																						</button>
																					</form>
																				<?php endif; ?>
																			</li>
																		</ul>
																	</div>
																	<div class="card-body">
																		<!-- Due Date -->
																		<div class="due-date mb-2">
																			<?php
																				$due = strtotime($recentProject->end_time);
																				$now = strtotime(date('Y-m-d'));
																				$daysLeft = ceil(($due - $now) / 86400);
																				if ($daysLeft > 1) {
																					echo "<span class='badge success'>DUE: $daysLeft DAY LEFT</span>";
																				} elseif ($daysLeft == 1) {
																					echo "<span class='badge red-badge'>DUE: 1 DAY LEFT</span>";
																				} elseif ($daysLeft == 0) {
																					echo "<span class='badge red-badge'>DUE: TODAY</span>";
																				} else {
																					echo "<span class='badge red-badge'>DUE PASSED</span>";
																				}
																			?>
																		</div>
																		<!-- Project Title -->
																		<h5 class="card-title mb-4"><?php echo htmlspecialchars($recentProject->project_title); ?></h5>
																		<!-- Assigned Team -->
																		
																<div class="d-flex col-gap-40 mb-4 flex-wrap" style="text-align: left;">

																		<div class="clients-rpt" style="text-align: left;">
																			<div class="title-head mb-2"><?php echo $lang['Assigned Team']; ?></div>
														 <div class="d-flex align-items-center">

																																				<?php 
															  $s_ids = $recentProject->s_ids;
															  $st_ids = explode(',', $s_ids);
															  $team_counter = 0;
															  foreach($st_ids as $st_id){
																  if($st_id != $recentProject->c_id && $st_id != 0){
																	  $team_counter++;
																	  if($team_counter > 3){} else {

																$user2 = user::findById($st_id); 
																echo '<div class="user-box">';
																echo '<form action="../discussion?project_id='.$recentProject->p_id .'" method="post"><input type="hidden" name="user_id" value="'.$st_id.'" /><input type="hidden" name="project_id" value="'.$recentProject->p_id .'" /><button name="chat" type="submit" data-bs-toggle="tooltip" data-bs-placement="top" title="'.$user2->firstName.'">';
																echo getUserAvatarHtml($st_id, $user2->firstName, $user2->lastName ?? '', 36, 36, '', $user2->firstName);
																echo '</button></form>';
																echo '</div>'; 
															  }
																  }
																  }
																 
																  if($team_counter > 3){
																	  $more = $team_counter-3;
																	   echo '<div class="plus-more shadow-dept">+'. $more .'</div>'; 
																  }
																  ?>
																	</div>		</div>
																		<!-- Clients -->
																		<div class="clients mb-2">
																	<div class="title-head mb-2"><?php echo $lang['Clients']; ?></div>
														 <div class="d-flex align-items-center">

														<?php 
														// Get all clients (main + additional)
														$allClients = array();
														
														// Add main client first
														$mainClient = user::findById($recentProject->main_client_id ?: $recentProject->c_id); 
														if ($mainClient) {
															$allClients[] = array(
																'id' => $mainClient->id,
																'firstName' => $mainClient->firstName,
																'lastName' => $mainClient->lastName ?? '',
																'isMain' => true
															);
														}
														
														// Add additional clients
														if (!empty($recentProject->c_ids)) {
															$allClientIds = array_filter(explode(',', $recentProject->c_ids));
															$additionalClients = array_filter($allClientIds, function($id) use ($recentProject) {
																return $id != $recentProject->main_client_id && $id != $recentProject->c_id;
															});
															
															foreach($additionalClients as $clientId) {
																$client = user::findById($clientId);
																if ($client) {
																	$allClients[] = array(
																		'id' => $client->id,
																		'firstName' => $client->firstName,
																		'lastName' => $client->lastName ?? '',
																		'isMain' => false
																	);
																}
															}
														}
														
														// Display only first 2 clients
														$displayedCount = 0;
														foreach($allClients as $client) {
															if($displayedCount >= 2) break;
															
															$title = $client['isMain'] ? $client['firstName'] . ' (Main)' : $client['firstName'];
															echo '<div class="user-box">';
															echo '<form action="../discussion?project_id='.$recentProject->p_id .'" method="post"><input type="hidden" name="user_id" value="'.$client['id'].'" /><input type="hidden" name="project_id" value="'.$recentProject->p_id .'" /><button name="chat" type="submit" data-bs-toggle="tooltip" data-bs-placement="top" title="'.$title.'">';
															echo getUserAvatarHtml($client['id'], $client['firstName'], $client['lastName'], 36, 36, 'img-fluid rounded-circle', $client['firstName']);
															echo '</button></form>';
															echo '</div>';
															$displayedCount++;
														}
														
														// Show + indicator if there are more than 2 clients
														if(count($allClients) > 2) {
															$more = count($allClients) - 2;
															echo '<div class="plus-more shadow-dept">+'. $more .'</div>';
														}
														?>
																		</div>
																		</div>
																		</div>
																		<!-- Progress Bar and Task Completion (dynamic) -->
																		<div class="progress mb-2" style="height:8px;">
																			<div class="progress-bar" role="progressbar" style="width: <?php echo $percent; ?>%; background: <?php echo $percent < 30 ? '#f66' : ($percent < 70 ? '#f9b233' : '#4caf50'); ?>;" aria-valuenow="<?php echo $percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
																		</div>
																		<div class="mb-2 d-flex col-gap-5"><div class="grey bold"><?php echo $lang['Task']; ?></div> <?php echo $completedTaskCount; ?>/<?php echo $taskCount; ?> <span class="text-align-right flex-grow"><?php echo $percent; ?>%</span></div>
																	</div>
																</div>
															</div>
														<?php endforeach; ?>
													</div>
													<?php
													if ($total_records > $limit) {
														tasksession_render_list_pagination(
															$projectsPaginationMeta,
															$total_records,
															$total_pages,
															$page
														);
													}
													?>
												<?php endif; ?>
											</div>
									   </div>
								</div>
					 		</div>
					   </div>
			      </div>
		    </div>
	   </div>
<script>
  window.ajaxProjectSearchEnabled = true;
  window.ajaxProjectSearchContext = 'staff';
</script>
<?php echo tasksession_list_bulk_form_html(array(
    'form_id' => 'tsListBulkFormProjects',
    'ids_field' => 'bulk_del_id',
    'hidden_fields' => array(
        'bulk_del_val' => '1',
        'bulk_del_proj' => '1',
        'bulk_arc_id' => '',
        'bulk_arc_val' => '1',
        'bulk_arc_proj' => '1',
    ),
)); ?>
<?php
echo tasksession_list_bulk_script_tag(array(
    'instances' => array(
        'projects' => array(
            'rootSelector' => '[data-ts-list-bulk-root="projects"]',
            'formId' => 'tsListBulkFormProjects',
            'idsField' => 'bulk_del_id',
            'deleteConfirm' => $lang['Delete selected projects?'] ?? 'Delete selected projects?',
            'deleteLabel' => $lang['Delete'] ?? 'Delete',
            'archiveConfirm' => $lang['Archive selected projects?'] ?? 'Archive selected projects?',
            'archiveLabel' => $lang['Move to Archive'] ?? 'Move to archive',
            'archiveIdsField' => 'bulk_arc_id',
            'selectAllLabel' => $lang['Select all'] ?? 'Select all',
            'deselectAllLabel' => $lang['Deselect all'] ?? 'Deselect all',
        ),
    ),
));
?>
<script src="../assets/js/features.js"></script>
<script>window.__toastFlash=<?php echo json_encode(isset($toast_flash) ? $toast_flash : null, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
<script src="<?php echo htmlspecialchars(rtrim((string) $url, '/') . '/assets/js/toast.js', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php  include("../templates/main-footer.php"); ?>