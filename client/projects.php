<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : Projects.php
 ================================================================================
 */
ob_start(); 
require_once("../includes/lib-initialize.php"); 
$title = "Projects | ". $syatem_title;
include("../templates/header.php");

 if(!($session->isLoggedIn())){
		redirectTo($url."index.php");
	}
if($_SESSION['accountStatus'] == 1){
	redirectTo($url."client/index.php");
}
if($_SESSION['accountStatus'] == 3){
	redirectTo($url."admin/index.php");
} 
//condition check for login

$id=$session->userId; //id of the current logged in user 
$user = User::findById((int)$id); //take the record of current user in an object array 	
$username=$user->firstName;;
$email=$user->email;;
$account_stat=$user->status;;
$user->regDate;



if(isset($_GET['message'])){
$msgstatus = $_GET['message'];
$notmsga = $lang['Record updated successfully'];
$notmsgb = $lang['Project has been created successfully!']; 
$notmsgc = $lang['Error! Please Try Again later.'];
$notmsgd = $lang['Project has been deleted sucessfully'];
$notmsge = $lang['Project added to archive sucessfully'];
$notmsgf = $lang['Project marked as Completed.'];
$notmsgg = $lang['Project status updated to re-open.'];
$notmsgh = $lang['Project restored Successfully.'];
$notmsgi = $lang['Project has been created successfully! but Error sending the Email please contact site administrator.'];
if($msgstatus == 'success'){
					$message="<p class='alert alert-success'>".$notmsga."</p>";
}
if($msgstatus == 'created'){
					$message="<div class='container extra-top'><p class='alert alert-success'>".$notmsgb."</p></div>";
}
if($msgstatus == 'fail'){		 
$message="<div class='container extra-top'><p class='col-md-12 alert alert-danger'>".$notmsgc."</p></div>";
}
if($msgstatus == 'psuccess'){		 
		 $message= "<div class='container extra-top'><p class='col-md-12 alert alert-success'>".$notmsgd."</p></div>";
}
if($msgstatus == 'archive'){		 
		 $message= "<div class='container extra-top'><p class='col-md-12 alert alert-success'>".$notmsge."</p></div>";
}
if($msgstatus == 'completed'){		 
		 $message= "<div class='container extra-top'><p class='col-md-12 alert alert-success'>".$notmsgf."</p></div>";
}
if($msgstatus == 'reopen'){		 
		 $message= "<div class='container extra-top'><p class='col-md-12 alert alert-success'>".$notmsgg."</p></div>";
}
if($msgstatus == 'restore'){		 
		 $message= "<div class='container extra-top'><p class='col-md-12 alert alert-success'>".$notmsgh."</p></div>";
}
if($msgstatus == 'error_email'){		 
		 $message= "<div class='container extra-top'><p class='col-md-12 alert alert-danger'>".$notmsgi."</p></div>";
}
if($msgstatus == 'permission_denied'){		 
		 $message= "<div class='container extra-top'><p class='col-md-12 alert alert-danger'>".$lang['You do not have permission to perform this action. Please contact your administrator.']."</p></div>";
}
if($msgstatus == 'task_not_found'){		 
		 $message= "<div class='container extra-top'><p class='col-md-12 alert alert-danger'>".$lang['Task not found or you do not have access to it.']."</p></div>";
}
if($msgstatus == 'invalid_access'){		 
		 $message= "<div class='container extra-top'><p class='col-md-12 alert alert-danger'>".$lang['You do not have permission to access this resource.']."</p></div>";
}
}
$viewType = isset($_GET['view']) && $_GET['view'] === 'grid' ? 'grid' : 'table';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? (string) $_GET['status'] : '';
if (!function_exists('buildClientProjectsListUrl')) {
	function buildClientProjectsListUrl(array $overrides = array()) {
		global $statusFilter, $searchQuery, $viewType;
		$params = array();
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
}
$projectsGridViewUrl = buildClientProjectsListUrl(array('view' => 'grid'));
$projectsTableViewUrl = buildClientProjectsListUrl(array('view' => null));
$projectsSearchClearUrl = buildClientProjectsListUrl(array('search' => null));
?>
	<div class="page-container">
		<div class="container-fluid">
			<div class="row row-eq-height">
				<?php  include("../templates/sidebar.php"); ?>
					<div class="page-content">
						<?php include('../templates/top-header.php'); ?><?php if(isset($message) && (!empty($message))){echo $message;} ?>
                <link rel="stylesheet" href="../assets/css/kanban-bulk.css?v=9">
							<div class="row bg-grey">
								<div class="col-md-12 project-tabs">
									<div class="row">
										<div class="project-tabs-header">
											<div class="scrollable-tabs-container d-flex col-gap-40 col-gap-40-sep">
												<?php $id = $session->userId; $user = User::findById((int)$id); $projects = projects::findBySql("SELECT * FROM projects WHERE (c_id = $id OR main_client_id = $id OR FIND_IN_SET($id, c_ids)) AND archive = 0 AND trash != 1");?>
											   <div class="main-heading"><h1><?php echo $lang['Projects']; ?> <span>(<?php echo count($projects); ?>)</span></h1></div>
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
									<div class="edit-overview-btn ts-list-bulk-toolbar-wrap">
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
						<div class="edit-overview-btn">
							 <td class="extra-height">
                                    <div class="action-toggle border-btn-a" data-bs-toggle="collapse" data-bs-target="#project-menu-filters">
                                        <span class="action-text"><?php echo $lang['Filters']; ?></span><span class="mobile-ellipsis"><?php echo ts_icon('ellipsis', 'w-2'); ?></span>
										<?php echo ts_icon('filter', 'w-2'); ?>
                                    </div>
										<div id="project-menu-filters" class="toggle-action collapse shadow-dept">
                                        <ul>
                                            <li class="<?php echo !isset($_GET['status']) ? 'active' : ''; ?>" data-status="all">
                                                <a href="<?php echo htmlspecialchars(buildClientProjectsListUrl(array('status' => null)), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php echo ts_icon('dot', 'tasksession-timer-log-menu-ico me-2'); ?>
                                                    <span><?php echo $lang['All Projects']; ?></span>
                                                </a>
                                            </li>
											
											<li class="<?php echo (isset($_GET['status']) && $_GET['status'] == 1) ? 'active' : ''; ?>">
                                                <a href="<?php echo htmlspecialchars(buildClientProjectsListUrl(array('status' => '1')), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php echo ts_icon('dot', 'tasksession-timer-log-menu-ico me-2'); ?>
                                                    <span><?php echo $lang['Completed']; ?></span>
                                                </a>
                                            </li>
                                           
										   	<li class="<?php echo (isset($_GET['status']) && $_GET['status'] == 0) ? 'active' : ''; ?>">
                                                <a href="<?php echo htmlspecialchars(buildClientProjectsListUrl(array('status' => '0')), ENT_QUOTES, 'UTF-8'); ?>">
													 <?php echo ts_icon('dot', 'tasksession-timer-log-menu-ico me-2'); ?>
													 <span><?php echo $lang['In Progress']; ?></span>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </div>
					 </div>
                    </div>
                  </div><div class="clearfix"></div>
							<?php 
								$limit = 15;
								$page = isset($_GET["page"]) ? max(1, (int)$_GET["page"]) : 1;
								$start_from = ($page - 1) * $limit;

								// Get search query from URL
								$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
								$searchPattern = '';
								if (!empty($searchQuery)) {
									$searchPattern = "%{$searchQuery}%";
								}

								// Add status filter logic at the top, after $searchCondition
								$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
								$statusCondition = '';
								if ($statusFilter !== '' && ($statusFilter == '0' || $statusFilter == '1')) {
									$statusCondition = " AND status = $statusFilter";
								}

								// Get filtered projects for current page - INCLUDES MULTIPLE CLIENTS
								$recentProjectsSql = "SELECT * FROM projects WHERE (c_id = ? OR main_client_id = ? OR FIND_IN_SET(?, c_ids)) AND archive = 0 AND trash != 1";
								$recentProjectsParams = [$id, $id, $id];
								$recentProjectsTypes = 'iii';
								
								if ($searchPattern !== '') {
									$recentProjectsSql .= " AND project_title LIKE ?";
									$recentProjectsParams[] = $searchPattern;
									$recentProjectsTypes .= 's';
								}
								if ($statusCondition !== '') {
									$recentProjectsSql .= $statusCondition; // statusCondition is safe (validated integer)
								}
								$recentProjectsSql .= " ORDER BY p_id DESC LIMIT ? OFFSET ?";
								$recentProjectsParams[] = $limit;
								$recentProjectsParams[] = $start_from;
								$recentProjectsTypes .= 'ii';
								
								$recentProjectsStmt = $connect->prepare($recentProjectsSql);
								$recentProjectsStmt->bind_param($recentProjectsTypes, ...$recentProjectsParams);
								$recentProjectsStmt->execute();
								$recentProjectsResult = $recentProjectsStmt->get_result();
								$recentProjects = [];
								while ($row = $recentProjectsResult->fetch_assoc()) {
									$projectObj = new projects();
									foreach ($row as $key => $value) {
										if (property_exists($projectObj, $key)) {
											$projectObj->$key = $value;
										}
									}
									$recentProjects[] = $projectObj;
								}
								$recentProjectsStmt->close();
								
								// Get all filtered projects (for pagination) - INCLUDES MULTIPLE CLIENTS
								$recentProjectsPageSql = "SELECT * FROM projects WHERE (c_id = ? OR main_client_id = ? OR FIND_IN_SET(?, c_ids)) AND archive = 0 AND trash != 1";
								$recentProjectsPageParams = [$id, $id, $id];
								$recentProjectsPageTypes = 'iii';
								
								if ($searchPattern !== '') {
									$recentProjectsPageSql .= " AND project_title LIKE ?";
									$recentProjectsPageParams[] = $searchPattern;
									$recentProjectsPageTypes .= 's';
								}
								if ($statusCondition !== '') {
									$recentProjectsPageSql .= $statusCondition;
								}
								$recentProjectsPageSql .= " ORDER BY p_id DESC";
								
								$recentProjectsPageStmt = $connect->prepare($recentProjectsPageSql);
								$recentProjectsPageStmt->bind_param($recentProjectsPageTypes, ...$recentProjectsPageParams);
								$recentProjectsPageStmt->execute();
								$recentProjectsPageResult = $recentProjectsPageStmt->get_result();
								$recentProjects_page = [];
								while ($row = $recentProjectsPageResult->fetch_assoc()) {
									$projectObj = new projects();
									foreach ($row as $key => $value) {
										if (property_exists($projectObj, $key)) {
											$projectObj->$key = $value;
										}
									}
									$recentProjects_page[] = $projectObj;
								}
								$recentProjectsPageStmt->close();

								$total_records = count($recentProjects_page);
								$total_pages = ceil($total_records / $limit);

								?>
											<div class="vh-100 ">
												<?php if ($viewType === 'table'): ?>
													<div class="table-responsive scroll-x vh-100">
														<table class="table table-new projectspage" data-pagination="true" data-page-size="15">
															<thead>
																<tr>
																	<th><?php echo htmlspecialchars($lang['No'] ?? 'No'); ?></th>
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
																<?php 
											  $counter=1;
											  $rowSerial = isset($start_from) ? (int) $start_from : 0;
										if($recentProjects == NULL){
											  echo '<tr><td colspan="9">'.$lang['No projects available. Create a new project to proceed.'].'</td></tr>';
										  }
										  foreach($recentProjects as $recentProject){
											  $rowSerial++;
										  ?>
																<tr>
																	<td><?php echo (int) $rowSerial; ?></td>
																	<td><div class="tbl-ttl">
																		<?php echo $recentProject->project_title;?><div>
																	</td>
																	<td class="clients-rpt" style="text-align: left;">
																 <div class="d-flex align-items-center">
																<?php 
															  $s_ids = $recentProject->s_ids;
															  $st_ids = explode(',', $s_ids);
															  $counter = 0;
															  foreach($st_ids as $st_id){
																  if($st_id != $recentProject->c_id && $st_id != 0){
																	  $counter++;
																	  if($counter > 3){} else {

																$user2 = user::findById($st_id); 
																echo '<div class="user-box">';
																echo '<form action="../discussion?project_id='.$recentProject->p_id .'" method="post"><input type="hidden" name="user_id" value="'.$st_id.'" /><input type="hidden" name="project_id" value="'.$recentProject->p_id .'" /><button name="chat" type="submit" data-bs-toggle="tooltip" data-bs-placement="top" title="'.$user2->firstName.'">';
																echo getUserAvatarHtml($st_id, $user2->firstName, $user2->lastName ?? '', 36, 36, '', $user2->firstName);
																echo '</button></form>';
																echo '</div>'; 
															  }
																  }
																  }
																 
																  if($counter > 3){
																	  $more = $counter-3;
																	   echo '<div class="plus-more">+'. $more .'</div>'; 
																  }
																  ?>
																</td><div>
																<td class="clients-rpt">
																 <div class="d-flex align-items-center">
																	<?php 
																// Display main client first
																$mainClient = user::findById($recentProject->main_client_id ?: $recentProject->c_id);
																if ($mainClient) {
																	echo '<div class="user-box">';
																	echo '<form action="../discussion?project_id='.$recentProject->p_id .'" method="post"><input type="hidden" name="user_id" value="'.$mainClient->id.'" /><input type="hidden" name="project_id" value="'.$recentProject->p_id .'" /><button name="chat" type="submit" data-bs-toggle="tooltip" data-bs-placement="top" title="'.$mainClient->firstName.' (Main)">';
																	echo getUserAvatarHtml($mainClient->id, $mainClient->firstName, $mainClient->lastName ?? '', 36, 36, 'img-fluid rounded-circle', $mainClient->firstName);
																	echo '</button></form>';
																	echo '</div>';
																}
																
																// Display additional clients if any
																if (!empty($recentProject->c_ids)) {
																	$allClientIds = array_filter(explode(',', $recentProject->c_ids));
																	$additionalClients = array_filter($allClientIds, function($id) use ($recentProject) {
																		return $id != $recentProject->main_client_id && $id != $recentProject->c_id;
																	});
																	
																	$clientCounter = 0;
																	foreach($additionalClients as $clientId) {
																		$clientCounter++;
																		if($clientCounter > 1) break; // Show max 1 additional client (total 2 clients)
																		
																		$client = user::findById($clientId);
																		if ($client) {
																			echo '<div class="user-box">';
																			echo '<form action="../discussion?project_id='.$recentProject->p_id .'" method="post"><input type="hidden" name="user_id" value="'.$client->id.'" /><input type="hidden" name="project_id" value="'.$recentProject->p_id .'" /><button name="chat" type="submit" data-bs-toggle="tooltip" data-bs-placement="top" title="'.$client->firstName.'">';
																			echo getUserAvatarHtml($client->id, $client->firstName, $client->lastName ?? '', 36, 36, 'img-fluid rounded-circle', $client->firstName);
																			echo '</button></form>';
																			echo '</div>';
																		}
																	}
																	
																	if(count($additionalClients) > 1) {
																		$more = count($additionalClients) - 1;
																		echo '<div class="plus-more shadow-dept">+'. $more .'</div>';
																	}
																}
																?></div>
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
																<td class="pro-bdgt">
																	<?php echo $currency_symbol . $recentProject->budget;?>
																</td>
																<td class="tbl-tasks extra-height">
																	<?php 
																		$projectId = $recentProject->p_id;
																		$stmt = $connect->prepare("SELECT COUNT(*) as task_count FROM tasks WHERE project_id = ?");
																		$stmt->bind_param("i", $projectId);
																		$stmt->execute();
																		$taskResult = $stmt->get_result();
																		$taskCount = 0;
																		if($taskRow = $taskResult->fetch_assoc()) {
																			$taskCount = $taskRow['task_count'];
																		}
																		$stmt->close();
																		
																		$stmt = $connect->prepare("SELECT COUNT(*) as completed_count FROM tasks WHERE project_id = ? AND status = 'done'");
																		$stmt->bind_param("i", $projectId);
																		$stmt->execute();
																		$completedTaskResult = $stmt->get_result();
																		$completedTaskCount = 0;
																		if($completedTaskRow = $completedTaskResult->fetch_assoc()) {
																			$completedTaskCount = $completedTaskRow['completed_count'];
																		}
																		$stmt->close();
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
																	<div id="actionDropdown<?php echo $recentProject->p_id;?>" class="toggle-action collapse shadow-dept">
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
																			<?php if ($taskPermissions->can_view_milestones): ?>
																			<li>
																				<a href="payments?projectId=<?php echo $recentProject->p_id; ?>">
																					<?php echo ts_icon('payments', 'h-6 tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Payments']; ?>
																				</a>
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
													<?php $total_records = count($recentProjects_page);  
													$total_pages = ceil($total_records / $limit); ?>
													<?php if ((int) $total_records > $limit): ?>
													<div class="row pagination-box">
														<div class="col-md-6 resilts-txt">
															<?php echo $lang['Showing']; ?> <span class="start_val"><?php echo $start_from_b;?></span>
																<?php echo $lang['to']; ?> <span class="end_val"><?php echo $limit; ?></span>
																	<?php echo $lang['of']; ?>
																		<?php echo $total_records;?>
																			<?php echo $lang['entries']; ?>
														</div>
														<div class="col-md-6">
															<?php
																echo '<nav aria-label="Page navigation"><ul class="pagination justify-content-end">';
																echo '<li class="page-item">
																	  <a class="page-link" href="?page=1" aria-label="Previous">
																		<span aria-hidden="true">&laquo;</span>
																		<span class="sr-only">'.$lang['Previous'].'</span>
																	  </a>
																	</li>
																';
																for ($i=1; $i<=$total_pages; $i++) {

																	$pagLink .= "<li class='page-item'><a class='page-link' href='?page=".$i."'>".$i."</a></li>";
																};  
																echo $pagLink . '
																<li class="page-item">
																	  <a class="page-link" href="?page='.$total_pages.'" aria-label="Next">
																		<span aria-hidden="true">&raquo;</span>
																		<span class="sr-only">'.$lang['Next'].'</span>
																	  </a>
																	</li>
																</ul></nav>';  
																?>
														</div>
													</div>
													<?php endif; ?>
												<?php else: ?>
													<div class="row" id="project-grid">
													
														<?php foreach($recentProjects as $recentProject): ?>
															<?php
															$projectId = $recentProject->p_id;
															// Total tasks
															$stmt = $connect->prepare("SELECT COUNT(*) as task_count FROM tasks WHERE project_id = ?");
															$stmt->bind_param("i", $projectId);
															$stmt->execute();
															$taskResult = $stmt->get_result();
															$taskCount = 0;
															if($taskRow = $taskResult->fetch_assoc()) {
																$taskCount = $taskRow['task_count'];
															}
															$stmt->close();
															
															// Completed tasks
															$stmt = $connect->prepare("SELECT COUNT(*) as completed_count FROM tasks WHERE project_id = ? AND status = 'done'");
															$stmt->bind_param("i", $projectId);
															$stmt->execute();
															$completedTaskResult = $stmt->get_result();
															$completedTaskCount = 0;
															if($completedTaskRow = $completedTaskResult->fetch_assoc()) {
																$completedTaskCount = $completedTaskRow['completed_count'];
															}
															$stmt->close();
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
																					<?php echo ts_icon('tasks', 'tasksession-timer-log-menu-ico me-2'); ?> <?php echo $lang['Tasks']; ?>
																				</a>
																			</li>
																			<?php if ($taskPermissions->can_view_milestones): ?>
																			<li>
																				<a href="payments?projectId=<?php echo $recentProject->p_id; ?>">
																					<?php echo ts_icon('payments', 'h-6 tasksession-timer-log-menu-ico me-2'); ?> <?php echo $lang['Payments']; ?>
																				</a>
																			</li>
																			<?php endif; ?>
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
																		<h5 class="card-title  mb-4""><?php echo htmlspecialchars($recentProject->project_title); ?></h5>
																		<!-- Assigned Team -->
																		
																<div class="d-flex col-gap-40 mb-4 flex-wrap style="text-align: left;">

																		<div class="clients-rpt" style="text-align: left;">
																			<div class="title-head mb-2"><?php echo $lang['Assigned Team']; ?></div>
																			<div class="d-flex align-items-center">

																																				<?php 
															  $s_ids = $recentProject->s_ids;
															  $st_ids = explode(',', $s_ids);
															  $counter = 0;
															  foreach($st_ids as $st_id){
																  if($st_id != $recentProject->c_id && $st_id != 0){
																	  $counter++;
																	  if($counter > 3){} else {

																$user2 = user::findById($st_id); 
																echo '<div class="user-box">';
																echo '<form action="../discussion?project_id='.$recentProject->p_id .'" method="post"><input type="hidden" name="user_id" value="'.$st_id.'" /><input type="hidden" name="project_id" value="'.$recentProject->p_id .'" /><button name="chat" type="submit" data-bs-toggle="tooltip" data-bs-placement="top" title="'.$user2->firstName.'">';
																echo getUserAvatarHtml($st_id, $user2->firstName, $user2->lastName ?? '', 36, 36, '', $user2->firstName);
																echo '</button></form>';
																echo '</div>'; 
															  }
																  }
																  }
																 
																  if($counter > 3){
																	  $more = $counter-3;
																	   echo '<div class="plus-more shadow-dept">+'. $more .'</div>'; 
																  }
																  ?>
																	</div>		</div>
																		<!-- Clients -->
																		<div class="clients mb-2">
																	<div class="title-head mb-2"><?php echo $lang['Clients']; ?></div>
														 <div class="d-flex align-items-center">

														<?php 
																// Display main client first
																$mainClient = user::findById($recentProject->main_client_id ?: $recentProject->c_id);
																if ($mainClient) {
																	echo '<div class="user-box">';
																	echo '<form action="../discussion?project_id='.$recentProject->p_id .'" method="post"><input type="hidden" name="user_id" value="'.$mainClient->id.'" /><input type="hidden" name="project_id" value="'.$recentProject->p_id .'" /><button name="chat" type="submit" data-bs-toggle="tooltip" data-bs-placement="top" title="'.$mainClient->firstName.' (Main)">';
																	echo getUserAvatarHtml($mainClient->id, $mainClient->firstName, $mainClient->lastName ?? '', 36, 36, 'img-fluid rounded-circle', $mainClient->firstName);
																	echo '</button></form>';
																	echo '</div>';
																}
																
																// Display additional clients if any
																if (!empty($recentProject->c_ids)) {
																	$allClientIds = array_filter(explode(',', $recentProject->c_ids));
																	$additionalClients = array_filter($allClientIds, function($id) use ($recentProject) {
																		return $id != $recentProject->main_client_id && $id != $recentProject->c_id;
																	});
																	
																	$clientCounter = 0;
																	foreach($additionalClients as $clientId) {
																		$clientCounter++;
																		if($clientCounter > 1) break; // Show max 1 additional client (total 2 clients)
																		
																		$client = user::findById($clientId);
																		if ($client) {
																			echo '<div class="user-box">';
																			echo '<form action="../discussion?project_id='.$recentProject->p_id .'" method="post"><input type="hidden" name="user_id" value="'.$client->id.'" /><input type="hidden" name="project_id" value="'.$recentProject->p_id .'" /><button name="chat" type="submit" data-bs-toggle="tooltip" data-bs-placement="top" title="'.$client->firstName.'">';
																			echo getUserAvatarHtml($client->id, $client->firstName, $client->lastName ?? '', 36, 36, 'img-fluid rounded-circle', $client->firstName);
																			echo '</button></form>';
																			echo '</div>';
																		}
																	}
																	
																	if(count($additionalClients) > 1) {
																		$more = count($additionalClients) - 1;
																		echo '<div class="plus-more shadow-dept">+'. $more .'</div>';
																	}
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
													<?php $total_records = count($recentProjects_page);  
													$total_pages = ceil($total_records / $limit); ?>
													<?php if ((int) $total_records > $limit): ?>
													<div class="row pagination-box">
														<div class="col-md-6 resilts-txt">
															<?php echo $lang['Showing']; ?> <span class="start_val"><?php echo $start_from_b;?></span>
																<?php echo $lang['to']; ?> <span class="end_val"><?php echo $limit; ?></span>
																	<?php echo $lang['of']; ?>
																		<?php echo $total_records;?>
																			<?php echo $lang['entries']; ?>
														</div>
														<div class="col-md-6">
															<?php
																// Build pagination parameters
																$paginationParams = [];
																if($statusFilter !== '') {
																	$paginationParams[] = 'status=' . htmlspecialchars($statusFilter);
																}
																if($viewType === 'grid') {
																	$paginationParams[] = 'view=grid';
																}
																if(isset($searchQuery) && !empty($searchQuery)) {
																	$paginationParams[] = 'search=' . urlencode($searchQuery);
																}
																$paginationQueryString = !empty($paginationParams) ? '&' . implode('&', $paginationParams) : '';
																
																echo '<nav aria-label="Page navigation"><ul class="pagination justify-content-end">';
																echo '<li class="page-item">
																	  <a class="page-link" href="?page=1'.$paginationQueryString.'" aria-label="Previous">
																		<span aria-hidden="true">&laquo;</span>
																		<span class="sr-only">'.$lang['Previous'].'</span>
																	  </a>
																	</li>
																';
																for ($i=1; $i<=$total_pages; $i++) {

																	$pagLink .= "<li class='page-item'><a class='page-link' href='?page=".$i.$paginationQueryString."'>".$i."</a></li>";
																};  
																echo $pagLink . '
																<li class="page-item">
																	  <a class="page-link" href="?page='.$total_pages.$paginationQueryString.'" aria-label="Next">
																		<span aria-hidden="true">&raquo;</span>
																		<span class="sr-only">'.$lang['Next'].'</span>
																	  </a>
																	</li>
																</ul></nav>';  
																?>
														</div>
													</div>
													<?php endif; ?>
												<?php endif; ?>
											</div>
									</div>
								</div>
								<!-- row -->
							</div>
							<div class="clearfix"></div>
					</div>
			</div>
		</div>
	</div>


<script>
  window.ajaxProjectSearchEnabled = true;
  window.ajaxProjectSearchContext = 'client';
</script>
<script src="../assets/js/features.js"></script>
<?php  include("../templates/main-footer.php"); ?>