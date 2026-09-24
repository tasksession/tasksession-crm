<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : trash.php
   Purpose : Manages and restores deleted (trashed) projects in the system.
 ================================================================================
*/
ob_start();
require_once("../includes/lib-initialize.php");
$title = "Trash | ". $syatem_title;
include("../templates/header.php");
 if(!($session->isLoggedIn())){
		redirectTo($url."");
	}
if($_SESSION['accountStatus'] == 2){
	redirectTo($url."client/dashboard");
}
if($_SESSION['accountStatus'] == 3){
	redirectTo($url."staff/dashboard");
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
	 $delProjId=$_POST['del_id'];
	
	  $deleteProj="delete from projects where p_id=$delProjId limit 1";
	  $projDeleted=mysqli_query($connect, $deleteProj);
	  $deleteProjChat="delete from messages where Project_id=$delProjId";
	  $projChatDeleted=mysqli_query($connect, $deleteProjChat);
	  if($projDeleted){
		 header("Location:trash?message=trash");
	   } else{
			   header("Location:trash?message=fail");
	   }
 }
if(isset($_POST['comp_proj']))
{
	 $comp_id=$_POST['comp_id'];
	 $comp_val=$_POST['comp_val'];
	  $updateProj="UPDATE projects SET status=$comp_val WHERE p_id=$comp_id";
	  $comp_proj=mysqli_query($connect, $updateProj);
if($comp_proj){
		  header("Location:projects?message=restore");
	  } else{
		  header("Location:projects?message=fail");
	  }
}
if(isset($_POST['arc_proj']))
{
	 $arc_id=$_POST['arc_id'];
	 $arc_val=$_POST['arc_val'];
	 $arc_del_val=$_POST['arc_del_val'];
	  $updateProj="UPDATE projects SET archive=$arc_val, trash=$arc_del_val WHERE p_id=$arc_id";
	  $arc_proj=mysqli_query($connect, $updateProj);
	  if($arc_proj){
		  header("Location:projects?message=restore");
	  }else{
		   header("Location:projects?message=fail");
	  }
}
if(isset($_GET['message'])){
$msgstatus = isset($_GET['message']) ? $_GET['message'] : '';
$notmessagea = $lang['Record updated successfully'];
$notmessageb = $lang['Error! Please Try Again later.'];
$notmessagec = $lang['Project moved to archive sucessfully'];
$notmessaged = $lang['Project marked as Completed.'];
$notmessagee = $lang['Project restored Successfully.'];
$notmessagef = $lang['Project deleted Successfully.'];
if($msgstatus == 'success'){
					$message="<p class='alert alert-success'>" . ts_icon('arrow-up-right') . " ".$notmessagea."</p>";
}
if($msgstatus == 'fail'){		 
$message="<div class='container extra-top'><p class='col-md-12 alert alert-danger'>".ts_icon('close')." ".$notmessageb."</p></div>";
}
if($msgstatus == 'archive'){		 
		 $message= "<div class='container extra-top'><p class='col-md-12 alert alert-success'>" . ts_icon('arrow-up-right') . " ".$notmessagec."</p></div>";
}
if($msgstatus == 'completed'){		 
		 $message= "<div class='container extra-top'><p class='col-md-12 alert alert-success'>" . ts_icon('arrow-up-right') . " ".$notmessaged."</p></div>";
}
if($msgstatus == 'restore'){		 
		 $message= "<div class='container extra-top'><p class='col-md-12 alert alert-success'>" . ts_icon('arrow-up-right') . " ".$notmessagee."</p></div>";
}
if($msgstatus == 'trash'){		 
		 $message= "<div class='container extra-top'><p class='col-md-12 alert alert-success'>" . ts_icon('arrow-up-right') . " ".$notmessagef."</p></div>";
}
}
?>
	<div class="page-container">
		<div class="container-fluid">
			<div class="row row-eq-height">
				<?php  include("../templates/sidebar.php"); ?>
					<div class="page-content">
						<?php include('../templates/top-header.php'); ?>
							<div class="row">
								<div class="col-md-12 project-tabs">
									<div class="row">
										<div class="project-tabs-header">
											<div class="scrollable-tabs-container d-flex col-gap-40 col-gap-40-sep">
												<?php $id = $session->userId; $user = User::findById((int)$id); $trashProjects = projects::findBySql("SELECT * FROM projects WHERE trash = 1"); ?>
												<div class="main-heading"><h1><?php echo $lang['Trash']; ?> <span>(<?php echo count($trashProjects); ?>)</span></h1></div>
												<div class="icon-container sep">
												
												
												
												  	<?php $id = $session->userId; $user = User::findById((int)$id); $projects = projects::findBySql("SELECT * FROM projects WHERE archive = 0 AND trash != 1");?>

													<a href="projects">
														<?php echo ts_icon('folder'); ?>

														<?php echo $lang['Show All Projects']; ?> (<?php echo count($projects); ?>)</a>
														<?php $id = $session->userId; $user = User::findById((int)$id); $archiveProjects = projects::findBySql("SELECT * FROM projects WHERE archive = 1 AND trash != 1"); ?>       
													<a href="archive">
														<?php echo ts_icon('archive-box'); ?>
														<?php echo $lang['Archive Projects']; ?> (<?php echo count($archiveProjects); ?>) </a>
												</div>
											</div>
										</div>	
						<div class="edit-overview-btn d-block d-md-none">
							 <td class="extra-height">
                                    <div class="action-toggle border-btn-a" data-toggle="collapse" data-target="#project-menu<?php echo $recentProject->p_id;?>">
                                        <span class="action-text"><?php echo $lang['Filters']; ?></span><span class="mobile-ellipsis"><?php echo ts_icon('ellipsis', 'w-2'); ?></span>
										<?php echo ts_icon('filter', 'w-2'); ?>
                                    </div>
							 <div id="project-menu<?php echo $recentProject->p_id;?>" class="toggle-action justify collapse shadow-dept">
                                        <ul>
										<li class="primary-btn">
                                                <a href="add-new-project">
                                                    <span>	<?php echo $lang['Create project']; ?> <?php echo ts_icon('plus', 'dropdown-toggle-icon h-6 tasksession-timer-log-menu-ico me-2'); ?></span>
                                                </a>
                                        </ul>
                                    </div>
                                </td>
                            </div>
						<div class="d-none d-md-block">
							<a href="add-new-project" class="primary-btn">
								<span  class="mb-none"><?php echo $lang['Create project']; ?></span> <?php echo ts_icon('plus', 'dropdown-toggle-icon h-6'); ?></a>
						</div>
						</div>
								</div>
								<div class="col-md-12 margin-top-10">
									<div class="row">
									<?php $recentProjects=projects::findBySql("select * from projects WHERE trash=1 ORDER BY start_time DESC");?>
										<div class="clearfix"></div>
										<div class="col-md-12 margin-top-10">
											<?php if(isset($message) && (!empty($message))){echo $message;} ?>
										
										<div class="table-responsive scroll-x vh-100">
											<table class="table table-new projectspaget">
												<thead>
													<tr>
														<!--<th><input name="btSelectAll" type="checkbox"></th>-->
														<th class="text-center">
															<?php echo $lang['No.']; ?>
														</th>
														<th class="text-left" width="26%">
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
													  echo '<tr><td colspan="9">'.$lang['Trash is Empty!'].'</td></tr>';
													}
													foreach($recentProjects as $recentProject){   ?>
														<tr>
															<td class="text-center">
																<?php echo $counter; ?>
															</td>
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
																  if($status == 0){ ?> <span class="badge inprogress"><?php echo $lang['In Progress']; ?></span>
																	<?php } else { ?> <span class="badge completed"><?php echo $lang['completed']; ?></span>
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
																	<?php echo $lang['Action']; ?> <?php echo ts_icon('chevron-down'); ?></div>
																<div id="actionDropdown<?php echo $recentProject->p_id;?>" class="toggle-action collapse shadow-dept">
																	<ul>
																		<li>
																			<form method="post" action="#">
																				<input type="hidden" value="<?php echo $recentProject->p_id;?>" name="arc_id" />
																				<input type="hidden" value="0" name="arc_val" />
																				<input type="hidden" value="0" name="arc_del_val" />
																				<button type="submit" name="arc_proj">
																					<?php echo ts_icon('archive', 'tasksession-timer-log-menu-ico me-2'); ?> <?php echo $lang['Restore']; ?>
																			</button>
																			</form>
																		</li>
																		<li>
																			<button type="button" class="btn-delete-permanent" data-project-id="<?php echo $recentProject->p_id; ?>">
																				<?php echo ts_icon('delete', 'tasksession-timer-log-menu-ico me-2'); ?> <?php echo $lang['Delete Permanently']; ?>
																			</button>
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
	<?php  include("../templates/main-footer.php"); ?>
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="card-title" id="deleteModalLabel"><?php echo $lang['Delete Project']; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><?php echo ts_icon('close'); ?></button>
            </div>
            <div class="modal-body text-center font-size-20">
                <p><?php echo $lang['Are you sure you want to delete this project permanently?']; ?></p>
            </div>
            <div class="modal-footer">
                              <form method="post" action="#" id="deleteForm">
                    <input type="hidden" name="del_id" id="deleteProjectId" value="" />
                    <button type="submit" name="del_proj" class="btn btn-danger"><?php echo $lang['Delete Permanently']; ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle delete button clicks
    const deleteButtons = document.querySelectorAll('.btn-delete-permanent');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const projectId = this.getAttribute('data-project-id');
            document.getElementById('deleteProjectId').value = projectId;
            // Show the modal using Bootstrap 5
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        });
    });
});
</script>