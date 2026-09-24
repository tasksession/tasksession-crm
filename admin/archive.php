<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : archive.php
   Purpose : Manages archived projects and tasks
 ================================================================================
 */
ob_start(); 
require_once("../includes/lib-initialize.php");
$title = "Project Archive | ". $syatem_title;
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
	 $delProjId = mysqli_real_escape_string($connect, $_POST['del_id']);
	 $delProjval = mysqli_real_escape_string($connect, $_POST['del_val']);
	
	  $deleteProj = "UPDATE projects SET trash = '$delProjval' WHERE p_id = '$delProjId'";
	  $projDeleted = mysqli_query($connect, $deleteProj);
	  if($projDeleted){
		 header("Location:archive?message=psuccess");
	   } else{
			   header("Location:archive?message=fail");
	   }
}
if(isset($_POST['bulk_del_proj']))
{
	 $bulk_del_id = mysqli_real_escape_string($connect, $_POST['bulk_del_id']);
	 $bulk_del_val = mysqli_real_escape_string($connect, $_POST['bulk_del_val']);
	 $bulk_arr = explode(',', $bulk_del_id);
	 foreach($bulk_arr as $bulk_id){
		 $bulk_id = mysqli_real_escape_string($connect, $bulk_id);
		 if ($bulk_id === '') {
			 continue;
		 }
		 $deleteProj = "UPDATE projects SET trash = '$bulk_del_val' WHERE p_id = '$bulk_id'";
		 $projDeleted = mysqli_query($connect, $deleteProj);
	 }
	 header("Location:archive?message=psuccess");
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
		  header("Location:archive?message=completed");
	  } else{
		  header("Location:archive?message=fail");
	  }
	 } else{
	  $updateProj="UPDATE projects SET status=$comp_val WHERE p_id=$comp_id";
	  $comp_proj=mysqli_query($connect, $updateProj);
	  if($comp_proj){
		  header("Location:archive?message=reopen");
	  } else{
		  header("Location:archive?message=fail");
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
		  header("Location:archive?message=projects");
	  }else{
		   header("Location:archive?message=fail");
	  }
}
$msgstatus = isset($_GET['message']) ? $_GET['message'] : '';
$notmessagea = $lang['Record updated successfully'];
$notmessageb = $lang['Error! Please Try Again later.'];
$notmessagec = $lang['Project has been deleted sucessfully'];
$notmessaged = $lang['Moved to projects sucessfully'];
$notmessagee = $lang['Project marked as Completed.'];
$notmessagef = $lang['Project status updated to re-open.'];
$notmessageg = $lang['Project restored Successfully.'];
if($msgstatus == 'success'){
					$message="<p class='alert alert-success'>" . ts_icon('arrow-up-right') . " ".$notmessagea."</p>";
}
	if($msgstatus == 'fail'){		 
$message="<div class='container extra-top'><p class='col-md-12 alert alert-danger'>".ts_icon('close')." ".$notmessageb."</p></div>";
}
if($msgstatus == 'psuccess'){		 
		 $message= "<div class='container extra-top'><p class='col-md-12 alert alert-success'>" . ts_icon('arrow-up-right') . " ".$notmessagec."</p></div>";
}
if($msgstatus == 'projects'){		 
		 $message= "<div class='container extra-top'><p class='col-md-12 alert alert-success'>" . ts_icon('arrow-up-right') . " ".$notmessaged."</p></div>";
}
if($msgstatus == 'completed'){		 
		 $message= "<div class='container extra-top'><p class='col-md-12 alert alert-success'>" . ts_icon('arrow-up-right') . " ".$notmessagee."</p></div>";
}
if($msgstatus == 'reopen'){		 
		 $message= "<div class='container extra-top'><p class='col-md-12 alert alert-success'>" . ts_icon('arrow-up-right') . " ".$notmessagef."</p></div>";
}
if($msgstatus == 'restore'){		 
		 $message= "<div class='container extra-top'><p class='col-md-12 alert alert-success'>" . ts_icon('arrow-up-right') . " ".$notmessageg."</p></div>";
}
require_once("../includes/list-bulk-helpers.php");
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? (string) $_GET['status'] : '';
$archiveSearchClearUrl = 'archive.php';
if ($statusFilter !== '') {
	$archiveSearchClearUrl .= '?status=' . rawurlencode($statusFilter);
}
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
										<?php $id = $session->userId; $user = User::findById((int)$id); $archiveProjects = projects::findBySql("SELECT * FROM projects WHERE archive = 1 AND trash != 1"); ?>       <div class="main-heading"><h1><?php echo $lang['Archive Projects']; ?> <span>(<?php echo count($archiveProjects); ?>)</span></h1></div>		
											<div class="icon-container sep">
												<?php $id = $session->userId; $user = User::findById((int)$id); $projects = projects::findBySql("SELECT * FROM projects WHERE archive = 0 AND trash != 1");?>
										<a href="projects">
											<?php echo ts_icon('archive'); ?>
											<?php echo $lang['Show All Projects']; ?> (<?php echo count($projects); ?>)</a>
														<?php $recentProjects=projects::findBySql("select * from projects WHERE trash=1");?>
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
                                            <form method="GET" action="archive" class="search-form" id="searchForm">
                                                <?php if($statusFilter !== ''): ?>
                                                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                                                <?php endif; ?>
                                                <div class="input-group">
                                                    <span class="search-field-icon">
                                                        <?php echo ts_icon('search', 'w-2'); ?>
                                                    </span>
                                                   <input type="text" id="task-search" name="search" class="form-control" placeholder="<?php echo htmlspecialchars($lang['Search projects here...'] ?? 'Search projects here...', ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($searchQuery ?? ''); ?>">
                                                    <?php if (!empty($searchQuery)): ?>
                                                        <a href="<?php echo htmlspecialchars($archiveSearchClearUrl, ENT_QUOTES, 'UTF-8'); ?>" class="cross" title="<?php echo htmlspecialchars($lang['Close'] ?? 'Close', ENT_QUOTES, 'UTF-8'); ?>">
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
										<div class="icon-container sep ts-list-bulk-toolbar" data-ts-list-bulk-toolbar="archive">
											<div class="pm-trash task-trash align-middle d-flex col-gap-5">
												<a href="#" onclick="TsListBulk.enter('archive'); return false;" class="bulk-delete-tab red border-btn-a" id="tsListBulk_archive_bulkSelectTab" title="<?php echo htmlspecialchars($lang['Bulk Select'] ?? 'Bulk select', ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($lang['Bulk Select'] ?? 'Bulk select', ENT_QUOTES, 'UTF-8'); ?>">
													<?php echo ts_icon('duplicate', 'w-2'); ?>
												</a>
												<a href="#" onclick="TsListBulk.exit('archive'); return false;" class="bulk-delete-tab border-btn-a" id="tsListBulk_archive_bulkBackTab" style="display:none;">
													<?php echo ts_icon('arrow-left', 'w-2'); ?>
													<span><?php echo $lang['Back'] ?? 'Back'; ?></span>
												</a>
												<a href="#" onclick="TsListBulk.selectAll('archive'); return false;" class="bulk-delete-tab border-btn-a" id="tsListBulk_archive_bulkSelectAllTab" style="display:none;">
													<?php echo ts_icon('check-circle', 'w-2'); ?>
													<span id="tsListBulk_archive_bulkSelectAllLabel"><?php echo $lang['Select all'] ?? 'Select all'; ?></span>
												</a>
												<a href="#" onclick="TsListBulk.deleteSelected('archive'); return false;" class="bulk-delete-tab border-btn-a" id="tsListBulk_archive_bulkDeleteTab" style="display:none;">
													<?php echo ts_icon('delete', 'w-2'); ?>
													<span id="tsListBulk_archive_bulkDeleteLabel"><?php echo $lang['Delete'] ?? 'Delete'; ?></span>
												</a>
											</div>
										</div>
									</div>
							<div class="edit-overview-btn">
							 <td class="extra-height">
                                    <div class="action-toggle border-btn-a" data-bs-toggle="collapse" data-bs-target="#archive-menu-filters" role="button" tabindex="0">
                                        <span class="action-text"><?php echo $lang['Filters']; ?></span><span class="mobile-ellipsis"><?php echo ts_icon('ellipsis', 'w-2'); ?></span>
										<?php echo ts_icon('filter', 'w-2'); ?>
                                    </div>
							 <div id="archive-menu-filters" class="toggle-action collapse shadow-dept">
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
                                                <a href="projects">
                                                    <?php echo ts_icon('dot', 'tasksession-timer-log-menu-ico me-2'); ?>
                                                    <span><?php echo $lang['All Projects']; ?></span>
                                                </a>
                                            </li>
											
											<li class="<?php echo (isset($_GET['status']) && $_GET['status'] == 1) ? 'active' : ''; ?>">
                                                <a href="projects?status=1">
                                                    <?php echo ts_icon('dot', 'tasksession-timer-log-menu-ico me-2'); ?>
                                                    <span><?php echo $lang['Completed']; ?></span>
                                                </a>
                                            </li>
                                           
										   	<li class="<?php echo (isset($_GET['status']) && $_GET['status'] == 0) ? 'active' : ''; ?>">
                                                <a href="projects?status=0">
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
             </div>
			<div class="clearfix"></div>
					<?php 
					$limit = 10;
					$page = isset($_GET["page"]) ? max(1, (int)$_GET["page"]) : 1;
					$start_from = ($page - 1) * $limit;

					// Get search query from URL
					$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
					$searchCondition = '';

					if (!empty($searchQuery)) {
						$safeSearch = mysqli_real_escape_string($connect, $searchQuery);
						$searchCondition = " AND (project_title LIKE '%$safeSearch%')";
					}

					// Add status filter logic at the top, after $searchCondition
					$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
					$statusCondition = '';
					if ($statusFilter !== '' && ($statusFilter == '0' || $statusFilter == '1')) {
						$statusCondition = " AND status = $statusFilter";
					}

					// Get filtered projects for current page
					$recentProjects = projects::findBySql("
						SELECT * FROM projects 
						WHERE archive = 0 AND trash != 1 $searchCondition $statusCondition
						ORDER BY start_time DESC 
						LIMIT $start_from, $limit
					");

					// Get all filtered projects (for pagination)
					$recentProjects_page = projects::findBySql("
						SELECT * FROM projects 
						WHERE archive = 0 AND trash != 1 $searchCondition $statusCondition
					");

					$total_records = count($recentProjects_page);
					$total_pages = ceil($total_records / $limit);
					?>
							<div class="row">
								<?php if(isset($message) && (!empty($message))){echo $message;} ?>
										<div class="clearfix"></div>
										<div class="col-md-12 margin-top-10">
										<?php $recentProjects=projects::findBySql("select * from projects WHERE archive=1 AND trash != 1 ORDER BY start_time DESC");
											?>
											<div class="vh-100" data-ts-list-bulk-root="archive">
											<div class="table-responsive scroll-x vh-100">
												<table class="table table-new projectspage">
													<thead>
														<tr>
															<?php echo tasksession_list_bulk_checkbox_th_html(); ?>
															<th width="26%">
																<?php echo $lang['Project Name']; ?>
															</th>
															<th>
																<?php echo $lang['Assign Team']; ?>
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
														if($recentProjects == NULL){
															  echo '<tr><td colspan="9">'.$lang['There is no Project in Archive!'].'</td></tr>';
														  }
														  foreach($recentProjects as $recentProject){ ?>
															<tr>
																<?php echo tasksession_list_bulk_checkbox_td_html($recentProject->p_id, $counter); ?>
																	<td><div class="tbl-ttl">
																	<?php echo $recentProject->project_title;?>
																	</div>
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
															global $db;
															$query = $db->query("SELECT filename FROM profile_pics WHERE fkUserId = '$st_id'");
															 $row1 = mysqli_fetch_array($query);
															 $image = $row1['filename'];
															 echo '<div class="user-box">';
															 		echo '<form action="../discussion?project_id='.$recentProject->p_id .'" method="post"><input type="hidden" name="user_id" value="'.$st_id.'" /><input type="hidden" name="project_id" value="'.$recentProject->p_id .'" /><button name="chat" type="submit" data-bs-toggle="tooltip" data-bs-placement="top" title="'.$user2->firstName.'">';
																echo getUserAvatarHtml($st_id, $user2->firstName, $user2->lastName ?? '', 36, 36, '', $user2->firstName);
																echo '</button></form>';
															 
													// 		echo '<div class="user-n">'. $user2->firstName . '</div>';
															echo '</div>'; 
																 }
																  }
																  }
														 	if($counter > 3){
															  $more = $counter-3;
															   echo '<div class="plus-more">+'. $more .'<br>more</div>'; 
														  }
														  ?>
																</div></td>
																<td class="clients-rpt" style="text-align: center;">
																	<div class="d-flex align-items-center">
																	<?php 
																	// Display main client first (with crown icon)
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
																		
																		$clientCounter = 0; // Changed from $counter to avoid conflict
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
																</td>
																<td>
																	<?php echo $recentProject->end_time;?>
																</td>
																<td class="prostatus">
																	<?php $status = $recentProject->status;
																	  $archive = $recentProject->archive;
																	  $trash = $recentProject->trash;
																	  if($status == 0){ ?> <span class="badge inprogress"><?php echo $lang['In Progress']; ?></span>
																	   <?php } else { ?> <span class="badge completed"><?php echo $lang['COMPLETED']; ?></span>
																	<?php }?>
																</td>
																<td class="pro-bdgt">
																	<?php echo $currency_symbol . $recentProject->budget;?>
																</td>
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
																	<div class="action-toggle" data-toggle="collapse" data-target="#actionDropdown<?php echo $recentProject->p_id;?>">
																		<?php echo $lang['Action']; ?> <?php echo ts_icon('chevron-down'); ?>
																	</div>
																	<div id="actionDropdown<?php echo $recentProject->p_id;?>" class="toggle-action collapse shadow-dept">
																		<ul>
																			<li>
																				<form action="../discussion?project_id=<?php echo $recentProject->p_id;?>" method="post">
																					<input type="hidden" name="user_id" value="<?php echo $recentProject->c_id;?>" />
																					<input type="hidden" name="project_id" value="<?php echo $recentProject->p_id;?>" />
																					<button type="submit" name="chat">
																						<?php echo ts_icon('chat', 'tasksession-timer-log-menu-ico me-2'); ?> <?php echo $lang['Discussion']; ?>
																					</button>
																				</form>
																			</li>
																			<li>
																				<a href="edit-project?id=<?php echo $recentProject->p_id;?>"><?php echo ts_icon('edit', 'tasksession-timer-log-menu-ico me-2'); ?> <?php echo $lang['Edit Project']; ?></a>
																			</li>
																			<li>
																				<form method="post" action="#">
																					<input type="hidden" value="<?php echo $recentProject->p_id;?>" name="del_id" />
																					<input type="hidden" value="<?php if($trash == 0){ echo '1';}else { echo '0';} ?>" name="del_val" />
																					<button type="submit" name="del_proj">
																						<?php echo ts_icon('delete', 'tasksession-timer-log-menu-ico me-2'); ?> <?php echo $lang['Delete']; ?>
																					</button>
																				</form>
																			</li>
																			<li>
																				<form method="post" action="#">
																					<input type="hidden" value="<?php echo $recentProject->p_id;?>" name="comp_id" />
																					<input type="hidden" value="<?php if($status == 0){ echo '1';}else { echo '0';} ?>" name="comp_val" />
																					<button type="submit" name="comp_proj">
																						<?php if($status == 0){
																							echo ts_icon('check', 'tasksession-timer-log-menu-ico me-2') . ' ' . $lang['Mark as complete'];
																							}else{ 
																							echo ts_icon('check', 'tasksession-timer-log-menu-ico me-2') . $lang['Re-open'];
																							} ?>
																					</button>
																				</form>
																			</li>
																			<li>
																				<form method="post" action="#">
																					<input type="hidden" value="<?php echo $recentProject->p_id;?>" name="arc_id" />
																					<input type="hidden" value="<?php if($archive == 0){ echo '1';}else { echo '0';} ?>" name="arc_val" />
																					<button type="submit" name="arc_proj">
																						<?php if($archive == 0){
																							echo ts_icon('archive', 'tasksession-timer-log-menu-ico me-2') . ' ' . $lang['Move to Archive'];
																							}else{ 
																							echo ts_icon('archive', 'tasksession-timer-log-menu-ico me-2') . ' ' . $lang['Move to Projects'];
																							} ?>
																					</button>
																				</form>
																			</li>
																		</ul>
																	</div>
																</td>
															</tr>
															<?php 
															$counter++;	
															}?>
													</tbody>
												</table>
											</div>
											</div>
										</div>
									</div>
								</div>
							</div>
					   </div>
			      </div>
		    </div>
	   </div>
	<?php echo tasksession_list_bulk_form_html(array(
		'form_id' => 'tsListBulkFormArchive',
		'ids_field' => 'bulk_del_id',
		'hidden_fields' => array(
			'bulk_del_val' => '1',
			'bulk_del_proj' => '1',
		),
	)); ?>
<?php
echo tasksession_list_bulk_script_tag(array(
	'instances' => array(
		'archive' => array(
			'rootSelector' => '[data-ts-list-bulk-root="archive"]',
			'formId' => 'tsListBulkFormArchive',
			'idsField' => 'bulk_del_id',
			'deleteConfirm' => $lang['Delete selected projects?'] ?? 'Delete selected projects?',
			'deleteLabel' => $lang['Delete'] ?? 'Delete',
			'selectAllLabel' => $lang['Select all'] ?? 'Select all',
			'deselectAllLabel' => $lang['Deselect all'] ?? 'Deselect all',
		),
	),
));
?>
	<?php  include("../templates/main-footer.php"); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var searchInput = document.getElementById('task-search');
    if (!searchInput) {
        return;
    }
    searchInput.addEventListener('keyup', function() {
        var input = this.value.toUpperCase();
        var table = document.querySelector('#projects-tbl');
        if (!table) {
            return;
        }
        var rows = table.getElementsByTagName('tr');
        for (var i = 0; i < rows.length; i++) {
            var projectName = rows[i].getElementsByTagName('td')[1];
            if (projectName) {
                var txtValue = projectName.textContent || projectName.innerText;
                if (txtValue.toUpperCase().indexOf(input) > -1) {
                    rows[i].style.display = '';
                } else {
                    rows[i].style.display = 'none';
                }
            }
        }
    });
});
</script>