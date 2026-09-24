<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : projects.php
   Purpose : Displays and manages all projects in the system, including archiving and deleting projects.
 ================================================================================
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
if($_SESSION['accountStatus'] == 3){
	redirectTo($url."staff/index.php");
} 
//condition check for login

$id=$session->userId; //id of the current logged in user 
$user = User::findById((int)$id); //take the record of current user in an object array 	
$username=$user->firstName;;
$email=$user->email;;
// $account_stat=$user->status;
$user->regDate;

if(isset($_POST['del_proj']))
{
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

// Add filter for showing only admin's projects or all projects
$showAllProjects = isset($_GET['all_projects']) && $_GET['all_projects'] === '1';
$searchQuery = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? (string)$_GET['status'] : '';
$projectFilterCondition = '';
$projectFilterConditionAlias = '';
if (!$showAllProjects) {
    // Show only projects where current admin is assigned
    $currentUserId = $session->userId;
    $projectFilterCondition = " AND FIND_IN_SET($currentUserId, s_ids)";
    $projectFilterConditionAlias = " AND FIND_IN_SET($currentUserId, p.s_ids)";
}

$projectsGridViewUrl = buildProjectsListUrl(array('view' => 'grid'));
$projectsTableViewUrl = buildProjectsListUrl(array('view' => null));
$projectsSearchClearUrl = buildProjectsListUrl(array('search' => null));
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
												<?php $id = $session->userId; $user = User::findById((int)$id); $projects = projects::findBySql("SELECT * FROM projects WHERE archive = 0 AND trash != 1" . $projectFilterCondition);?>
											   <div class="main-heading"><h1><?php echo $lang['Projects']; ?> <span>(<?php echo count($projects); ?>)</span></h1></div>
												<div class="icon-container sep">
												<!-- Project Filter Buttons -->
												<?php 
												// Get counts for filter buttons
												$myProjectsCount = projects::findBySql("SELECT * FROM projects WHERE archive = 0 AND trash != 1 AND FIND_IN_SET($session->userId, s_ids)");
												$allProjectsCount = projects::findBySql("SELECT * FROM projects WHERE archive = 0 AND trash != 1");
												
												// Build URL parameters for My Projects / All Projects
												$myProjectsUrl = buildProjectsListUrl();
												$allProjectsUrl = buildProjectsListUrl(array('all_projects' => '1'));
												?>
												<a href="<?php echo $myProjectsUrl; ?>" class="<?php echo !$showAllProjects ? 'active' : ''; ?>">
													<?php echo ts_icon('briefcase'); ?>
													<?php echo $lang['My Projects']; ?> (<?php echo count($myProjectsCount); ?>)
												</a>
												<a href="<?php echo htmlspecialchars($allProjectsUrl, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $showAllProjects ? 'active' : ''; ?>">
													<?php echo ts_icon('inbox-stack'); ?>
													<?php echo $lang['All Projects']; ?> (<?php echo count($allProjectsCount); ?>)
												</a>
												<?php $id = $session->userId; $user = User::findById((int)$id); $archiveProjects = projects::findBySql("SELECT * FROM projects WHERE archive = 1 AND trash != 1" . $projectFilterCondition); ?>       
													<a href="archive">
														<?php echo ts_icon('archive-box'); ?>
												<?php echo $lang['Archive Projects']; ?> (<?php echo count($archiveProjects); ?>) </a>
												<?php $recentProjects=projects::findBySql("select * from projects WHERE trash=1" . $projectFilterCondition);?>
													<a href="trash" class="pm-trashbox">
													<?php echo ts_icon('trash-box'); ?>
												<?php echo $lang['Trash Projects']; ?> (<?php echo count($recentProjects); ?>) </a>
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
												<a href="#" onclick="TsListBulk.deleteSelected('projects'); return false;" class="bulk-delete-tab border-btn-a" id="tsListBulk_projects_bulkDeleteTab" style="display:none;">
													<?php echo ts_icon('delete', 'w-2'); ?>
													<span id="tsListBulk_projects_bulkDeleteLabel"><?php echo $lang['Delete'] ?? 'Delete'; ?></span>
												</a>
												<a href="#" onclick="TsListBulk.archiveSelected('projects'); return false;" class="bulk-delete-tab border-btn-a" id="tsListBulk_projects_bulkArchiveTab" style="display:none;">
													<?php echo ts_icon('archive-box', 'w-2'); ?>
													<span id="tsListBulk_projects_bulkArchiveLabel"><?php echo $lang['Move to Archive'] ?? 'Move to archive'; ?></span>
												</a>
											</div>
										</div>
									</div>
						<div class="edit-overview-btn">
							 <td class="extra-height">
                                    <div class="action-toggle border-btn-a" data-bs-toggle="collapse" data-bs-target="#project-menu-filters">
                                        <span class="action-text"><?php echo $lang['Filters']; ?></span><span class="mobile-ellipsis"><?php echo ts_icon('ellipsis', 'w-2'); ?></span>
										<?php echo ts_icon('filter', 'w-2'); ?>
                                    </div>
										<div id="project-menu-filters" class="toggle-action collapse shadow-dept">
                                        <ul>
										<li class="primary-btn d-block d-md-none">
                                                <a href="add-new-project">
                                                    <span>	<?php echo $lang['Create project']; ?> <?php echo ts_icon('plus', 'dropdown-toggle-icon h-6'); ?></span>
                                                </a>
										</li>
										<li class="menu-divider d-block d-md-none">
											<hr class="dropdown-divider">
										</li>
                                            <li class="<?php echo !isset($_GET['status']) ? 'active' : ''; ?>" data-status="all">
                                                <a href="projects<?php echo $showAllProjects ? '?all_projects=1' : ''; ?>">
                                                    <?php echo ts_icon('dot', 'tasksession-timer-log-menu-ico me-2'); ?>
                                                    <span><?php echo $lang['All Projects']; ?></span>
                                                </a>
                                            </li>
											
											<li class="<?php echo (isset($_GET['status']) && $_GET['status'] == 1) ? 'active' : ''; ?>">
                                                <a href="projects?status=1<?php echo $showAllProjects ? '&all_projects=1' : ''; ?>">
                                                    <?php echo ts_icon('dot', 'tasksession-timer-log-menu-ico me-2'); ?>
                                                    <span><?php echo $lang['Completed']; ?></span>
                                                </a>
                                            </li>
                                           
										   	<li class="<?php echo (isset($_GET['status']) && $_GET['status'] == 0) ? 'active' : ''; ?>">
                                                <a href="projects?status=0<?php echo $showAllProjects ? '&all_projects=1' : ''; ?>">
													 <?php echo ts_icon('dot', 'tasksession-timer-log-menu-ico me-2'); ?>
													 <span><?php echo $lang['In Progress']; ?></span>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </div>
						<div class="d-none d-md-block">
							<a href="add-new-project" class="primary-btn">
							<?php echo $lang['Create project']; ?> <?php echo ts_icon('plus', 'w-2'); ?></a>
						</div>
					 </div>
                    </div>
                  </div><div class="clearfix"></div>
							<?php 
								$limit = 15;
								$page = isset($_GET["page"]) ? max(1, (int)$_GET["page"]) : 1;
								$start_from = ($page - 1) * $limit;

								$joinSql = '';
								$searchCondition = '';
								if (!empty($searchQuery)) {
									global $database;
									$safeSearch = $database->escapeValue($searchQuery);
									$like = "%{$safeSearch}%";
									$joinSql = " LEFT JOIN users u ON (u.id = p.c_id OR u.id = p.main_client_id OR FIND_IN_SET(u.id, p.c_ids)) ";
									$searchCondition = " AND (
										p.project_title LIKE '{$like}'
										OR u.firstName LIKE '{$like}'
									)";
								}

								$statusCondition = '';
								if ($statusFilter !== '' && ($statusFilter === '0' || $statusFilter === '1')) {
									$statusCondition = " AND p.status = " . (int)$statusFilter;
								}

								$sqlBase =
									" FROM projects p " .
									$joinSql .
									" WHERE p.archive = 0 AND p.trash != 1 " .
									$projectFilterConditionAlias .
									$statusCondition .
									$searchCondition;

								// Get filtered projects for current page
								$recentProjects = projects::findBySql("
									SELECT DISTINCT p.* 
									$sqlBase
									ORDER BY p.p_id DESC 
									LIMIT $start_from, $limit
								");

								// Total records (fast COUNT instead of selecting all rows)
								global $database;
								$countRes = $database->query("SELECT COUNT(DISTINCT p.p_id) AS total $sqlBase");
								$countRow = $database->fetchArray($countRes);
								$total_records = isset($countRow['total']) ? (int)$countRow['total'] : 0;
								$total_pages = (int)ceil($total_records / $limit);

								// Showing range
								$start_from_b = ($total_records > 0) ? ($start_from + 1) : 0;
								$end_val = ($total_records > 0) ? min($start_from + count($recentProjects), $total_records) : 0;
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
																	<?php echo tasksession_list_bulk_checkbox_th_html(); ?>
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
																	<th>
																		<?php echo $lang['Budget']; ?>
																	</th>
																	<th>
																		<?php echo $lang['Task']; ?>
																	</th>
																	<th>
																		<?php echo $lang['Options']; ?>
																	</th>
																</tr>
															</thead>
															<tbody id="projects-tbl">
																<?php include(__DIR__ . '/../partials/projects_table_rows.php'); ?>
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
														<?php include(__DIR__ . '/../partials/projects_grid_cards.php'); ?>
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
<script>
  // Enable server-side (AJAX) project search while typing
  window.ajaxProjectSearchEnabled = true;
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