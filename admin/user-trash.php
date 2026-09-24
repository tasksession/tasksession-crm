<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : user-trash.php
   Purpose : Manages and restores deleted (trashed) staff and client members in the system.
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
if(isset($_POST['user_id'])){
$SESSION['user_id'] = $_POST['user_id'];
}

//condition check for login

$id=$session->userId; //id of the current logged in user 
$user = User::findById((int)$id); //take the record of current user in an object array 	
$username=$user->firstName;
$email=$user->email;
$account_stat=$user->status;
$user->regDate;

if (isset($_POST['del_user'])) {
	$delUserId = (int) ($_POST['del_id'] ?? 0);
	$delProjects = !empty($_POST['del_projects']) ? 1 : 0;
	$delTasks = !empty($_POST['del_tasks']) ? 1 : 0;
	$delInvoices = !empty($_POST['del_invoices']) ? 1 : 0;
	$success = 0;
	$adminId = (int) $session->userId;
	if ((int) ($_SESSION['accountStatus'] ?? 0) !== 1) {
		$adminRow = User::findBySql('SELECT * FROM users WHERE accountStatus = 1 AND status = 0 ORDER BY id ASC LIMIT 1');
		if (!empty($adminRow[0]->id)) {
			$adminId = (int) $adminRow[0]->id;
		}
	}
	if ($adminId === $delUserId) {
		$adminRow = User::findBySql("SELECT * FROM users WHERE accountStatus = 1 AND status = 0 AND id != {$delUserId} ORDER BY id ASC LIMIT 1");
		if (!empty($adminRow[0]->id)) {
			$adminId = (int) $adminRow[0]->id;
		}
	}

	$stripCsvId = static function ($list, $removeId) {
		$removeId = (string) (int) $removeId;
		$parts = array_filter(array_map('trim', explode(',', (string) $list)), 'strlen');
		$parts = array_values(array_filter($parts, static function ($p) use ($removeId) {
			return (string) (int) $p !== $removeId;
		}));
		return implode(',', $parts);
	};

	if ($delUserId <= 0 || $delUserId === $adminId) {
		header('Location:user-trash?message=fail');
		exit;
	}

	$targetUser = User::findById($delUserId);
	if (!$targetUser) {
		header('Location:user-trash?message=fail');
		exit;
	}
	$isClient = ((int) $targetUser->accountStatus === 2);

	if ($isClient) {
		$clientProjectIds = [];
		$pq = mysqli_query(
			$connect,
			"SELECT p_id FROM projects WHERE c_id = {$delUserId} OR main_client_id = {$delUserId}"
		);
		if ($pq) {
			while ($row = mysqli_fetch_assoc($pq)) {
				$clientProjectIds[] = (int) $row['p_id'];
			}
		}
		$clientProjectIds = array_values(array_unique(array_filter($clientProjectIds)));
		$idsSql = !empty($clientProjectIds) ? implode(',', $clientProjectIds) : '';

		if (!empty($clientProjectIds) && ($delInvoices || $delProjects)) {
			mysqli_query($connect, "DELETE FROM milestones WHERE p_id IN ({$idsSql}) OR c_id = {$delUserId}");
		} elseif ($delInvoices) {
			mysqli_query($connect, "DELETE FROM milestones WHERE c_id = {$delUserId}");
		}

		if (!empty($clientProjectIds) && ($delTasks || $delProjects)) {
			mysqli_query($connect, "DELETE FROM tasks WHERE project_id IN ({$idsSql})");
		}

		if ($delProjects && !empty($clientProjectIds)) {
			mysqli_query($connect, "DELETE FROM messages WHERE Project_id IN ({$idsSql})");
			mysqli_query(
				$connect,
				"DELETE FROM projects WHERE c_id = {$delUserId} OR main_client_id = {$delUserId}"
			);
		} else {
			mysqli_query(
				$connect,
				"UPDATE projects SET c_id = {$adminId} WHERE c_id = {$delUserId}"
			);
			mysqli_query(
				$connect,
				"UPDATE projects SET main_client_id = NULL WHERE main_client_id = {$delUserId}"
			);
			$cidsProjects = projects::findBySql("SELECT * FROM projects WHERE FIND_IN_SET({$delUserId}, c_ids)");
			if ($cidsProjects) {
				foreach ($cidsProjects as $cidsProject) {
					$pid = (int) $cidsProject->p_id;
					$newCids = $stripCsvId($cidsProject->c_ids ?? '', $delUserId);
					$newCidsEsc = mysqli_real_escape_string($connect, $newCids);
					mysqli_query($connect, "UPDATE projects SET c_ids = '{$newCidsEsc}' WHERE p_id = {$pid}");
				}
			}
		}
	} else {
		$staffProjects = projects::findBySql("SELECT * FROM projects WHERE FIND_IN_SET({$delUserId}, s_ids)");
		if ($delProjects) {
			$staffProjectIds = [];
			if ($staffProjects) {
				foreach ($staffProjects as $sp) {
					$staffProjectIds[] = (int) $sp->p_id;
				}
			}
			$staffProjectIds = array_values(array_unique(array_filter($staffProjectIds)));
			if (!empty($staffProjectIds)) {
				$idsSql = implode(',', $staffProjectIds);
				mysqli_query($connect, "DELETE FROM milestones WHERE p_id IN ({$idsSql})");
				mysqli_query($connect, "DELETE FROM tasks WHERE project_id IN ({$idsSql})");
				mysqli_query($connect, "DELETE FROM messages WHERE Project_id IN ({$idsSql})");
				mysqli_query($connect, "DELETE FROM projects WHERE p_id IN ({$idsSql})");
			}
		} elseif ($staffProjects) {
			foreach ($staffProjects as $checkUserProject) {
				$userproject_id = (int) $checkUserProject->p_id;
				$output = $stripCsvId($checkUserProject->s_ids ?? '', $delUserId);
				$outputEsc = mysqli_real_escape_string($connect, $output);
				if ($userproject_id > 0) {
					$updateDone = mysqli_query(
						$connect,
						"UPDATE projects SET s_ids = '{$outputEsc}' WHERE p_id = {$userproject_id}"
					);
					if (!$updateDone) {
						$success = 1;
					}
				}
			}
		}

		$staffTasks = Task::findBySql("SELECT * FROM tasks WHERE FIND_IN_SET({$delUserId}, assigned_to)");
		if ($delTasks) {
			mysqli_query($connect, "DELETE FROM tasks WHERE FIND_IN_SET({$delUserId}, assigned_to)");
		} elseif ($staffTasks) {
			foreach ($staffTasks as $taskRow) {
				$taskId = (int) $taskRow->id;
				$output = $stripCsvId($taskRow->assigned_to ?? '', $delUserId);
				$outputEsc = mysqli_real_escape_string($connect, $output);
				if ($taskId > 0) {
					mysqli_query(
						$connect,
						"UPDATE tasks SET assigned_to = '{$outputEsc}' WHERE id = {$taskId}"
					);
				}
			}
		}
	}

	$allusers = user::findBySql('SELECT * FROM users');
	if ($allusers) {
		foreach ($allusers as $alluser) {
			$alluserbox = (int) $alluser->id;
			mysqli_query(
				$connect,
				"DELETE FROM messages WHERE (user_id = {$delUserId} AND receiver = {$alluserbox}) OR (user_id = {$alluserbox} AND receiver = {$delUserId})"
			);
		}
	}

	$userDeleted = mysqli_query($connect, "DELETE FROM users WHERE id = {$delUserId} LIMIT 1");
	if ($userDeleted && $success === 0) {
		header('Location:user-trash?message=success');
	} else {
		header('Location:user-trash?message=fail');
	}
	exit;
}

if(isset($_POST['restore_user']))
{
	$ru_id=$_POST['ru_id'];
	$ru_status=$_POST['ru_status'];
$flag=0;
		if($flag==0)
		{
			$user = user::findById($ru_id); 
			$user->status=$ru_status;
			$saveUser=$user->save();
			if($saveUser){
				header("Location:user-trash?message=successrestore");
			}else {
header("Location:user-trash?message=restorefail");
			}
		}
}

if (isset($_POST['restore_team_group']) && isset($connect) && (int) $_SESSION['accountStatus'] === 1) {
	$tgId = isset($_POST['team_group_id']) ? (int) $_POST['team_group_id'] : 0;
	if ($tgId > 0) {
		$st = mysqli_prepare($connect, 'UPDATE staff_team_groups SET deleted_at = NULL WHERE id = ? AND deleted_at IS NOT NULL LIMIT 1');
		if ($st) {
			mysqli_stmt_bind_param($st, 'i', $tgId);
			mysqli_stmt_execute($st);
			mysqli_stmt_close($st);
		}
	}
	header('Location: user-trash?message=team_group_restored');
	exit;
}

if (isset($_POST['purge_team_group']) && isset($connect) && (int) $_SESSION['accountStatus'] === 1) {
	$tgId = isset($_POST['team_group_id']) ? (int) $_POST['team_group_id'] : 0;
	if ($tgId > 0) {
		$st = mysqli_prepare($connect, 'DELETE FROM staff_team_groups WHERE id = ? AND deleted_at IS NOT NULL LIMIT 1');
		if ($st) {
			mysqli_stmt_bind_param($st, 'i', $tgId);
			mysqli_stmt_execute($st);
			mysqli_stmt_close($st);
		}
	}
	header('Location: user-trash?message=team_group_purged');
	exit;
}

if (isset($_POST['restore_client_company']) && isset($connect) && (int) $_SESSION['accountStatus'] === 1) {
	$ccId = isset($_POST['client_company_id']) ? (int) $_POST['client_company_id'] : 0;
	if ($ccId > 0) {
		$st = mysqli_prepare($connect, 'UPDATE client_companies SET deleted_at = NULL WHERE id = ? AND deleted_at IS NOT NULL LIMIT 1');
		if ($st) {
			mysqli_stmt_bind_param($st, 'i', $ccId);
			mysqli_stmt_execute($st);
			mysqli_stmt_close($st);
		}
	}
	header('Location: user-trash?message=client_company_restored');
	exit;
}

if (isset($_POST['purge_client_company']) && isset($connect) && (int) $_SESSION['accountStatus'] === 1) {
	$ccId = isset($_POST['client_company_id']) ? (int) $_POST['client_company_id'] : 0;
	if ($ccId > 0) {
		$st = mysqli_prepare($connect, 'DELETE FROM client_companies WHERE id = ? AND deleted_at IS NOT NULL LIMIT 1');
		if ($st) {
			mysqli_stmt_bind_param($st, 'i', $ccId);
			mysqli_stmt_execute($st);
			mysqli_stmt_close($st);
		}
	}
	header('Location: user-trash?message=client_company_purged');
	exit;
}

if(isset($_GET['message'])){
$msgstatus = $_GET['message'];
$notmessagea = $lang['User Restored sucessfully.'];
$notmessageb = $lang['Error restoring user. Please Try Again later.'];
$notmessagec = $lang['User has been deleted sucessfully.'];
$notmessaged = $lang['Can not delete user at this time.'];
if($msgstatus == 'successrestore'){
		 $message= "<p class='alert alert-success'>".$notmessagea."</p>";
}
if($msgstatus == 'restorefail'){		 
				$message="<p class='alert alert-danger'>".$notmessageb."</p>";
}
if($msgstatus == 'success'){		 
		 $message= "<p class='alert alert-success'>".$notmessagec."</p>";
}
if($msgstatus == 'fail'){		 
			  $message="<p class='alert alert-danger'>".$notmessaged."</p>";
}
if ($msgstatus == 'team_group_restored') {
	$message = '<p class="alert alert-success">' . htmlspecialchars($lang['Team group restored'] ?? 'Team group restored.', ENT_QUOTES, 'UTF-8') . '</p>';
}
if ($msgstatus == 'team_group_purged') {
	$message = '<p class="alert alert-success">' . htmlspecialchars($lang['Team group permanently deleted'] ?? 'Team group permanently deleted.', ENT_QUOTES, 'UTF-8') . '</p>';
}
if ($msgstatus == 'client_company_restored') {
	$message = '<p class="alert alert-success">' . htmlspecialchars($lang['Company restored'] ?? 'Company restored.', ENT_QUOTES, 'UTF-8') . '</p>';
}
if ($msgstatus == 'client_company_purged') {
	$message = '<p class="alert alert-success">' . htmlspecialchars($lang['Company permanently deleted'] ?? 'Company permanently deleted.', ENT_QUOTES, 'UTF-8') . '</p>';
}
}

$trashedTeamGroups = [];
$teamGroupTrashTableReady = false;
if (isset($connect) && (int) $_SESSION['accountStatus'] === 1) {
	$tgCol = @mysqli_query($connect, "SHOW COLUMNS FROM staff_team_groups LIKE 'deleted_at'");
	if ($tgCol && mysqli_num_rows($tgCol) > 0) {
		$teamGroupTrashTableReady = true;
		$tq = mysqli_query($connect, 'SELECT id, name, deleted_at FROM staff_team_groups WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC');
		if ($tq) {
			while ($trow = mysqli_fetch_assoc($tq)) {
				$trashedTeamGroups[] = $trow;
			}
		}
	}
}

$trashedClientCompanies = [];
$companyTrashTableReady = false;
if (isset($connect) && (int) $_SESSION['accountStatus'] === 1) {
	$ccTbl = @mysqli_query($connect, "SHOW TABLES LIKE 'client_companies'");
	if ($ccTbl && mysqli_num_rows($ccTbl) > 0) {
		$companyTrashTableReady = true;
		$cq = mysqli_query($connect, 'SELECT id, name, deleted_at FROM client_companies WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC');
		if ($cq) {
			while ($crow = mysqli_fetch_assoc($cq)) {
				$trashedClientCompanies[] = $crow;
			}
		}
	}
}

require_once __DIR__ . '/../includes/user_search_helper.php';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$clientSearchCondition = comon_user_list_search_sql($connect, $searchQuery, [
    'custom_field_entity_types' => ['client'],
    'include_company_membership' => false,
]);
$staffSearchCondition = comon_user_list_search_sql($connect, $searchQuery, [
    'custom_field_entity_types' => ['staff'],
]);
$adminSearchCondition = comon_user_list_search_sql($connect, $searchQuery, [
    'custom_field_entity_types' => ['admin'],
]);

// Get trashed staff, admins, and clients
$trashedStaff = user::findBySql("SELECT * FROM users WHERE accountStatus = 3 AND status = 1 $staffSearchCondition");
$trashedAdmins = user::findBySql("SELECT * FROM users WHERE accountStatus = 1 AND id != 1 AND status = 1 $adminSearchCondition");
$trashedClients = user::findBySql("SELECT * FROM users WHERE accountStatus = 2 AND status = 1 $clientSearchCondition");
$allTrashedUsers = array_merge($trashedStaff, $trashedAdmins, $trashedClients);

// Filter trashed users by user_type if set
$userTypeFilter = isset($_GET['user_type']) ? $_GET['user_type'] : '';
if ($userTypeFilter === 'staff') {
    $allTrashedUsers = $trashedStaff;
} elseif ($userTypeFilter === 'admin') {
    $allTrashedUsers = $trashedAdmins;
} elseif ($userTypeFilter === 'client') {
    $allTrashedUsers = $trashedClients;
}

// Add userTypeBadge function for badge consistency
function userTypeBadge($user) {
    if ($user->id == 1) return '<span class="fixed-badge color-done review done-bg-op">Super Admin</span>';
    if ($user->accountStatus == 1) return '<span class="fixed-badge color-inprogress inprogress-bg-op ">Admin</span>';
    if ($user->accountStatus == 3) return '<span class="fixed-badge color-review review-bg-op">Staff</span>';
    return '';
}

$trashTotalCount = count($allTrashedUsers) + ($teamGroupTrashTableReady ? count($trashedTeamGroups) : 0) + ($companyTrashTableReady ? count($trashedClientCompanies) : 0);
$trashUsersEmpty = empty($allTrashedUsers);
$trashGroupsEmpty = !$teamGroupTrashTableReady || empty($trashedTeamGroups);

$trashNavParams = [];
if ($searchQuery !== '') {
	$trashNavParams['search'] = $searchQuery;
}
if ($userTypeFilter !== '') {
	$trashNavParams['user_type'] = $userTypeFilter;
}
$trashNavQuery = $trashNavParams ? '?' . http_build_query($trashNavParams) : '';
$trashClearSearchHref = 'user-trash' . ($userTypeFilter !== '' ? '?user_type=' . rawurlencode($userTypeFilter) : '');
?>
	<div class="page-container">
		<div class="container-fluid">
			<div class="row row-eq-height">
				<?php  include("../templates/sidebar.php"); ?>
					<div class="page-content">
						<?php include('../templates/top-header.php'); ?>
			
							<div class="row">
								<div class="col-md-12 margin-top-10 clients">
									<div class="row bg-grey">
										<div class="col-md-12 project-tabs">
											<div class="row">
												<div class="project-tabs-header">
													<div class="scrollable-tabs-container d-flex col-gap-40 col-gap-40-sep">
														<div class="main-heading"><h1><?php echo $lang['Trash']; ?> <span>(<?php echo (int) $trashTotalCount; ?>)</span></h1></div>
														<div class="icon-container sep">
															<div class="icon-container members-team-tabs">
																<a href="members">
																	<?php echo ts_icon('user-group', 'w-5'); ?><?php echo htmlspecialchars($lang['Members tab'] ?? 'Members'); ?>
																</a>
																<a href="members?groups=">
																	<?php echo ts_icon('view-grid', 'w-5'); ?><?php echo htmlspecialchars($lang['Team Groups'] ?? 'Team groups'); ?>
																</a>
																<a href="clients">
																	<?php echo ts_icon('clients', 'w-5'); ?><?php echo htmlspecialchars($lang['Clients'] ?? 'Clients'); ?>
																</a>
																<a href="clients?company">
																	<?php echo ts_icon('app-grid', 'w-5'); ?><?php echo htmlspecialchars($lang['Company'] ?? 'Company'); ?>
																</a>
															</div>
															<a href="user-trash<?php echo htmlspecialchars($trashNavQuery, ENT_QUOTES, 'UTF-8'); ?>" class="active">
																<?php echo ts_icon('trash-box'); ?>
																<?php echo $lang['Show Trash Users']; ?>
															</a>
														</div>
													</div>
												</div>
												<div class="search">
													<div class="search-icon border-btn-a" onclick="toggleSearch()">
														<?php echo ts_icon('search', 'w-2'); ?>
													</div>
													<form method="GET" action="user-trash" class="search-form" id="searchForm">
														<input type="hidden" name="page" value="1">
														<?php if ($userTypeFilter !== ''): ?>
															<input type="hidden" name="user_type" value="<?php echo htmlspecialchars($userTypeFilter, ENT_QUOTES, 'UTF-8'); ?>">
														<?php endif; ?>
														<div class="input-group">
															<span class="search-field-icon">
																<?php echo ts_icon('search', 'w-2'); ?>
															</span>
															<input type="text" id="user-search" name="search" class="form-control" placeholder="<?php echo htmlspecialchars($lang['Search Users'] ?? 'Search users', ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($searchQuery ?? ''); ?>">
															<?php if ($searchQuery !== ''): ?>
																<a href="<?php echo htmlspecialchars($trashClearSearchHref, ENT_QUOTES, 'UTF-8'); ?>" class="cross" title="<?php echo htmlspecialchars($lang['Close'] ?? 'Close', ENT_QUOTES, 'UTF-8'); ?>">
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
												<div class="edit-overview-btn">
													<td class="extra-height">
														<div class="action-toggle border-btn-a" data-bs-toggle="collapse" data-bs-target="#user-type-menu" role="button" tabindex="0">
															<span class="action-text"><?php echo $lang['Filters']; ?></span><span class="mobile-ellipsis"><?php echo ts_icon('ellipsis', 'w-2'); ?></span>
															<?php echo ts_icon('filter', 'w-2'); ?>
														</div>
														<div id="user-type-menu" class="toggle-action collapse shadow-dept">
															<ul>
																<li class="<?php echo $userTypeFilter === '' ? 'active' : ''; ?>">
																	<a href="user-trash<?php echo $searchQuery !== '' ? '?search=' . urlencode($searchQuery) : ''; ?>">
																		<?php echo ts_icon('dot', 'tasksession-timer-log-menu-ico me-2'); ?>
																		<span><?php echo $lang['All Users']; ?></span>
																	</a>
																</li>
																<li class="<?php echo $userTypeFilter === 'staff' ? 'active' : ''; ?>">
																	<a href="user-trash?user_type=staff<?php echo $searchQuery !== '' ? '&search=' . urlencode($searchQuery) : ''; ?>">
																		<?php echo ts_icon('dot', 'tasksession-timer-log-menu-ico me-2'); ?>
																		<span><?php echo $lang['Staff']; ?></span>
																	</a>
																</li>
																<li class="<?php echo $userTypeFilter === 'admin' ? 'active' : ''; ?>">
																	<a href="user-trash?user_type=admin<?php echo $searchQuery !== '' ? '&search=' . urlencode($searchQuery) : ''; ?>">
																		<?php echo ts_icon('dot', 'tasksession-timer-log-menu-ico me-2'); ?>
																		<span><?php echo $lang['Admin']; ?></span>
																	</a>
																</li>
																<li class="<?php echo $userTypeFilter === 'client' ? 'active' : ''; ?>">
																	<a href="user-trash?user_type=client<?php echo $searchQuery !== '' ? '&search=' . urlencode($searchQuery) : ''; ?>">
																		<?php echo ts_icon('dot', 'tasksession-timer-log-menu-ico me-2'); ?>
																		<span><?php echo $lang['Client']; ?></span>
																	</a>
																</li>
															</ul>
														</div>
													</td>
												</div>
											</div>	
										</div>
									</div>
								<?php if(isset($message) && (!empty($message))){echo $message;} ?>
										<div class="row clients-row">
										<?php
										  $urlB = $url;
										   if ($trashUsersEmpty && $trashGroupsEmpty) {
											   echo '<div class="empty-box">'.$lang['Trash Empty!'].'</div>';
										   }
												 foreach($allTrashedUsers as $trashedUser){ 
												  
													$profilePictureObj=profilePicture::findByfkUserId($trashedUser->id);
									
															if($profilePictureObj)
															{
																foreach($profilePictureObj as $displayPicture)
																{
																 $profilePic=$displayPicture->thumbnailPath();
																 $filenamePic = $displayPicture->filename;
																 $filenamePicture = getProfilePicUrl($filenamePic, 130, 130);
																}
																
															 }
															 else
															 {
																 $profilePic ="../assets/images/upload-img.jpg";
																 $filenamePicture ="../assets/images/upload-img.jpg";
															 } 
													$user_name = $trashedUser->firstName;
													$isOnline = function_exists('user_presence_from_user')
														? user_presence_from_user($trashedUser)
														: (
															isset($trashedUser->last_seen) &&
															isset($trashedUser->session_status) &&
															$trashedUser->session_status === 'online' &&
															(time() - (int)$trashedUser->last_seen) < 300
														);
													$userId = $trashedUser->id;
													$userType = $trashedUser->accountStatus == 2 ? 'client' : 'staff';
													
													// Get counts based on user type
													if($userType == 'staff') {
														// Project count for staff (as assigned in s_ids)
														$projectCountResult = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM projects WHERE FIND_IN_SET($userId, s_ids)");
														$projectCountRow = mysqli_fetch_assoc($projectCountResult);
														$projectCount = $projectCountRow ? $projectCountRow['cnt'] : 0;
														// Task count for staff (as assigned)
														$taskCountResult = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM tasks WHERE FIND_IN_SET($userId, assigned_to)");
														$taskCountRow = mysqli_fetch_assoc($taskCountResult);
														$taskCount = $taskCountRow ? $taskCountRow['cnt'] : 0;
														$invoiceCount = 0;
													} else {
														// Project count for client
														$projectCountResult = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM projects WHERE c_id = $userId");
														$projectCountRow = mysqli_fetch_assoc($projectCountResult);
														$projectCount = $projectCountRow ? $projectCountRow['cnt'] : 0;
														// Get all project IDs for this client
														$projectIdsResult = mysqli_query($connect, "SELECT p_id FROM projects WHERE c_id = $userId");
														$projectIds = [];
														while ($row = mysqli_fetch_assoc($projectIdsResult)) {
															$projectIds[] = $row['p_id'];
														}
														$taskCount = 0;
														if (!empty($projectIds)) {
															$projectIdsStr = implode(',', $projectIds);
															$taskCountResult = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM tasks WHERE project_id IN ($projectIdsStr)");
															$taskCountRow = mysqli_fetch_assoc($taskCountResult);
															$taskCount = $taskCountRow ? $taskCountRow['cnt'] : 0;
														}
														// Get invoice count for this client
														$invoiceCountResult = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM milestones m JOIN projects p ON m.p_id = p.p_id WHERE p.c_id = $userId");
														$invoiceCountRow = mysqli_fetch_assoc($invoiceCountResult);
														$invoiceCount = $invoiceCountRow ? $invoiceCountRow['cnt'] : 0;
													}
												  ?>
												<div class="col-md-4 col-lg-4 col-xl-3 mb-2 staff user-imgbox img-circle">
													<div class="client-card">
														<div class="d-flex col-gap-20 align-items-center flex-wrap">
															<div class="profile-img-wrapper" style="position:relative; display:inline-block;">
																<span class="online-dot<?php echo $isOnline ? ' online' : ' offline'; ?>"></span>
																<img src="<?php echo $filenamePicture; ?>" class="img-fluid profile-img" />
															</div>
															<div class="client-info">
																<?php echo userTypeBadge($trashedUser); ?>
																<div class="grey font-size-20"><?php echo $trashedUser->firstName;?></div>
																<div class="client-email"><?php echo $trashedUser->email;?></div>
															</div>
														</div>
														<div class="client-stats d-flex ">
															<div>
															<div class="stat-value"><?php echo str_pad($projectCount, 2, '0', STR_PAD_LEFT); ?></div>
																<div class="stat-label grey"><?php echo $lang['Projects']; ?></div>
															</div>
															<div class="border-lf">
													<div class="stat-value"><?php echo str_pad($taskCount, 2, '0', STR_PAD_LEFT); ?></div>
																<div class="stat-label grey"><?php echo $lang['Task']; ?></div>
															</div>
															<?php if($userType == 'client'): ?>
															<div>
																<div class="stat-value"><?php echo str_pad($invoiceCount, 2, '0', STR_PAD_LEFT); ?></div>
																<div class="stat-label grey"><?php echo $lang['Invoice']; ?></div>
															</div>
															<?php endif; ?>
														</div>
														<div class="btn-wrapstf user-trash-card-actions">
															<form method="post" action="#" class="restore_user chat-frm">
																<input type="hidden" value="<?php echo $trashedUser->id;?>" name="ru_id" />
																<input type="hidden" value="0" name="ru_status" />
																<input type="submit" name="restore_user" value="<?php echo $lang['RESTORE']; ?>" class="btn user-trash-action-btn" />
															</form>
															<button
																type="button"
																class="btn alert-savestn user-trash-action-btn btn-user-delete-permanent"
																data-user-id="<?php echo (int) $trashedUser->id; ?>"
																data-user-name="<?php echo htmlspecialchars($trashedUser->firstName, ENT_QUOTES, 'UTF-8'); ?>"
																data-user-type="<?php echo htmlspecialchars($userType, ENT_QUOTES, 'UTF-8'); ?>"
																data-project-count="<?php echo (int) $projectCount; ?>"
																data-task-count="<?php echo (int) $taskCount; ?>"
																data-invoice-count="<?php echo (int) $invoiceCount; ?>"
															><?php echo $lang['DELETE']; ?></button>
														</div>
													</div>
												</div>
												<?php } unset($allTrashedUsers); ?>
										<?php if ($teamGroupTrashTableReady && !empty($trashedTeamGroups)) { ?>
										<div class="col-12 mt-3 mb-2">
											<h5 class="grey font-size-16"><?php echo htmlspecialchars($lang['Trashed team groups'] ?? 'Team groups (trash)'); ?></h5>
										</div>
										<?php foreach ($trashedTeamGroups as $tg) {
											$tgid = (int) $tg['id'];
											$tgname = (string) ($tg['name'] ?? '');
											$delAt = isset($tg['deleted_at']) ? (string) $tg['deleted_at'] : '';
											$delLabel = '';
											if ($delAt !== '') {
												$tts = strtotime($delAt);
												if ($tts) {
													$delLabel = sprintf($lang['Team group deleted on'] ?? 'Deleted on %s', date('M j, Y H:i', $tts));
												}
											}
											$uids = [];
											$memberCount = 0;
											if (isset($connect) && $tgid > 0) {
												$cntq = @mysqli_query($connect, 'SELECT COUNT(*) AS c FROM staff_team_group_members WHERE group_id=' . $tgid);
												if ($cntq && ($cr = mysqli_fetch_assoc($cntq))) {
													$memberCount = (int) ($cr['c'] ?? 0);
												}
												$uq = @mysqli_query($connect, 'SELECT user_id FROM staff_team_group_members WHERE group_id=' . $tgid . ' ORDER BY user_id ASC LIMIT 12');
												if ($uq) {
													while ($ur = mysqli_fetch_assoc($uq)) {
														$uids[] = (int) $ur['user_id'];
													}
												}
											}
											$showAv = array_slice($uids, 0, 5);
											$extra = max(0, $memberCount - count($showAv));
											?>
										<div class="col-md-4 col-lg-4 col-xl-3 mb-2">
											<div class="client-card team-group-card">
												<div class="grey font-size-20 mb-1 text-center"><?php echo htmlspecialchars($tgname); ?></div>
												<?php if ($delLabel !== '') { ?>
												<div class="grey font-size-12 mb-2 text-center"><?php echo htmlspecialchars($delLabel); ?></div>
												<?php } ?>
												<div class="d-flex flex-wrap clients-rpt justify-content-center mb-3">
													<?php
													if (!empty($showAv)) {
														foreach ($showAv as $avUid) {
															$uobj = User::findById($avUid);
															if (!$uobj) {
																continue;
															}
															$tip = htmlspecialchars(trim(($uobj->firstName ?? '') . ' ' . ($uobj->lastName ?? '')));
															$thumb = getUserAvatarHtml($uobj->id, $uobj->firstName ?? '', $uobj->lastName ?? '', 40, 40, 'rounded-circle img-fluid', $uobj->firstName ?? '');
															echo '<div class="avatar-circle client-avatar" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="' . $tip . '" data-bs-original-title="' . $tip . '">';
															echo '<span class="online-dot offline"></span>';
															echo $thumb;
															echo '</div>';
														}
														if ($extra > 0) {
															echo '<div class="avatar-circle client-avatar d-flex align-items-center justify-content-center" style="margin-left:-10px;font-size:12px;font-weight:600;">+' . (int) $extra . '</div>';
														}
													} elseif (function_exists('getSimpleAvatar')) {
														echo '<div class="d-flex justify-content-center w-100">' . getSimpleAvatar($tgname !== '' ? $tgname : 'G', 72) . '</div>';
													} else {
														echo '<div class="d-flex justify-content-center w-100"><img src="../assets/images/upload-img.jpg" class="img-fluid rounded-circle" style="width:72px;height:72px;object-fit:cover;" alt="" /></div>';
													}
													?>
												</div>
												<div class="btn-wrapstf d-flex w-100 col-gap-10 align-items-stretch justify-content-center">
													<form method="post" action="#" class="restore_user chat-frm flex-fill m-0" style="min-width:0">
														<input type="hidden" name="team_group_id" value="<?php echo $tgid; ?>" />
														<input type="submit" name="restore_team_group" value="<?php echo htmlspecialchars($lang['RESTORE'] ?? 'Restore'); ?>" class="form-control btn w-100 text-center" />
													</form>
													<form method="post" action="#" class="del-form chat-frm flex-fill m-0" style="min-width:0" onsubmit="return confirm(<?php echo json_encode($lang['Permanently delete team group confirm'] ?? 'Permanently delete this team group? This cannot be undone.', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>);">
														<input type="hidden" name="team_group_id" value="<?php echo $tgid; ?>" />
														<input type="submit" name="purge_team_group" value="<?php echo htmlspecialchars($lang['DELETE'] ?? 'Delete'); ?>" class="form-control btn alert-savestn w-100 text-center" />
													</form>
												</div>
											</div>
										</div>
										<?php } ?>
										<?php } ?>
										<?php if ($companyTrashTableReady && !empty($trashedClientCompanies)) { ?>
										<div class="col-12 mt-3 mb-2">
											<h5 class="grey font-size-16"><?php echo htmlspecialchars($lang['Trashed companies'] ?? 'Companies (trash)'); ?></h5>
										</div>
										<?php foreach ($trashedClientCompanies as $cc) {
											$ccid = (int) $cc['id'];
											$ccname = (string) ($cc['name'] ?? '');
											$delAt = isset($cc['deleted_at']) ? (string) $cc['deleted_at'] : '';
											$delLabel = '';
											if ($delAt !== '') {
												$tts = strtotime($delAt);
												if ($tts) {
													$delLabel = sprintf($lang['Company deleted on'] ?? 'Deleted on %s', date('M j, Y H:i', $tts));
												}
											}
											$uids = [];
											$memberCount = 0;
											if (isset($connect) && $ccid > 0) {
												$cntq = @mysqli_query($connect, 'SELECT COUNT(*) AS c FROM client_company_members WHERE company_id=' . $ccid);
												if ($cntq && ($cr = mysqli_fetch_assoc($cntq))) {
													$memberCount = (int) ($cr['c'] ?? 0);
												}
												$uq = @mysqli_query($connect, 'SELECT user_id FROM client_company_members WHERE company_id=' . $ccid . ' ORDER BY user_id ASC LIMIT 12');
												if ($uq) {
													while ($ur = mysqli_fetch_assoc($uq)) {
														$uids[] = (int) $ur['user_id'];
													}
												}
											}
											$showAv = array_slice($uids, 0, 5);
											$extra = max(0, $memberCount - count($showAv));
											?>
										<div class="col-md-4 col-lg-4 col-xl-3 mb-2">
											<div class="client-card team-group-card">
												<div class="grey font-size-20 mb-1 text-center"><?php echo htmlspecialchars($ccname); ?></div>
												<?php if ($delLabel !== '') { ?>
												<div class="grey font-size-12 mb-2 text-center"><?php echo htmlspecialchars($delLabel); ?></div>
												<?php } ?>
												<div class="d-flex flex-wrap clients-rpt justify-content-center mb-3">
													<?php
													if (!empty($showAv)) {
														foreach ($showAv as $avUid) {
															$uobj = User::findById($avUid);
															if (!$uobj) {
																continue;
															}
															$tip = htmlspecialchars(trim(($uobj->firstName ?? '') . ' ' . ($uobj->lastName ?? '')));
															$thumb = getUserAvatarHtml($uobj->id, $uobj->firstName ?? '', $uobj->lastName ?? '', 40, 40, 'rounded-circle img-fluid', $uobj->firstName ?? '');
															echo '<div class="avatar-circle client-avatar" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="' . $tip . '" data-bs-original-title="' . $tip . '">';
															echo '<span class="online-dot offline"></span>';
															echo $thumb;
															echo '</div>';
														}
														if ($extra > 0) {
															echo '<div class="avatar-circle client-avatar d-flex align-items-center justify-content-center" style="margin-left:-10px;font-size:12px;font-weight:600;">+' . (int) $extra . '</div>';
														}
													} elseif (function_exists('getSimpleAvatar')) {
														echo '<div class="d-flex justify-content-center w-100">' . getSimpleAvatar($ccname !== '' ? $ccname : 'C', 72) . '</div>';
													} else {
														echo '<div class="d-flex justify-content-center w-100"><img src="../assets/images/upload-img.jpg" class="img-fluid rounded-circle" style="width:72px;height:72px;object-fit:cover;" alt="" /></div>';
													}
													?>
												</div>
												<div class="btn-wrapstf d-flex w-100 col-gap-10 align-items-stretch justify-content-center">
													<form method="post" action="#" class="restore_user chat-frm flex-fill m-0" style="min-width:0">
														<input type="hidden" name="client_company_id" value="<?php echo $ccid; ?>" />
														<input type="submit" name="restore_client_company" value="<?php echo htmlspecialchars($lang['RESTORE'] ?? 'Restore'); ?>" class="form-control btn w-100 text-center" />
													</form>
													<form method="post" action="#" class="del-form chat-frm flex-fill m-0" style="min-width:0" onsubmit="return confirm(<?php echo json_encode($lang['Permanently delete company confirm'] ?? 'Permanently delete this company? This cannot be undone.', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>);">
														<input type="hidden" name="client_company_id" value="<?php echo $ccid; ?>" />
														<input type="submit" name="purge_client_company" value="<?php echo htmlspecialchars($lang['DELETE'] ?? 'Delete'); ?>" class="form-control btn alert-savestn w-100 text-center" />
													</form>
												</div>
											</div>
										</div>
										<?php } ?>
										<?php } ?>
											<div class="norecords" style="display: none;">
										<?php echo $lang['No records Found!']; ?>
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
<div class="modal fade" id="userDeleteModal" tabindex="-1" aria-labelledby="userDeleteModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content user-delete-modal-content">
			<div class="modal-header">
				<h5 class="card-title mb-0" id="userDeleteModalLabel"><?php echo htmlspecialchars($lang['Delete Permanently'] ?? 'Delete Permanently'); ?></h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><?php echo function_exists('ts_icon') ? ts_icon('close') : ''; ?></button>
			</div>
			<form method="post" action="#" id="userDeleteForm">
				<div class="modal-body">
					<p class="font-size-16 mb-2" id="userDeleteModalMessage"></p>
					<p class="grey font-size-13 mb-3"><?php echo htmlspecialchars($lang['This action cannot be undone.'] ?? 'This action cannot be undone.'); ?></p>
					<p class="grey font-size-13 mb-3"><?php echo htmlspecialchars($lang['Related data'] ?? 'Related data'); ?> — <?php echo htmlspecialchars($lang['Off = unlink / keep · On = permanently delete'] ?? 'Off = unlink / keep · On = permanently delete'); ?></p>

					<div class="user-delete-toggle-row">
						<div class="user-delete-toggle-copy">
							<label class="mb-0" for="del_projects_switch">
								<span class="user-delete-toggle-title"><?php echo htmlspecialchars($lang['Projects'] ?? 'Projects'); ?></span>
								<span class="grey" id="userDeleteProjectCount">(0)</span>
							</label>
							<div class="grey font-size-12"><?php echo htmlspecialchars($lang['Off = unlink only · On = delete projects'] ?? 'Off = unlink only · On = delete projects'); ?></div>
						</div>
						<div class="checkbox-wrapper-6">
							<input class="tgl tgl-light" id="del_projects_switch" name="del_projects" type="checkbox" value="1">
							<label class="tgl-btn" for="del_projects_switch"></label>
						</div>
					</div>

					<div class="user-delete-toggle-row">
						<div class="user-delete-toggle-copy">
							<label class="mb-0" for="del_tasks_switch">
								<span class="user-delete-toggle-title"><?php echo htmlspecialchars($lang['Task'] ?? 'Tasks'); ?></span>
								<span class="grey" id="userDeleteTaskCount">(0)</span>
							</label>
							<div class="grey font-size-12"><?php echo htmlspecialchars($lang['Off = unassign · On = delete tasks'] ?? 'Off = unassign · On = delete tasks'); ?></div>
						</div>
						<div class="checkbox-wrapper-6">
							<input class="tgl tgl-light" id="del_tasks_switch" name="del_tasks" type="checkbox" value="1">
							<label class="tgl-btn" for="del_tasks_switch"></label>
						</div>
					</div>

					<div class="user-delete-toggle-row" id="userDeleteInvoiceRow" hidden>
						<div class="user-delete-toggle-copy">
							<label class="mb-0" for="del_invoices_switch">
								<span class="user-delete-toggle-title"><?php echo htmlspecialchars($lang['Invoice'] ?? 'Invoices'); ?></span>
								<span class="grey" id="userDeleteInvoiceCount">(0)</span>
							</label>
							<div class="grey font-size-12"><?php echo htmlspecialchars($lang['Off = keep · On = delete invoices'] ?? 'Off = keep · On = delete invoices'); ?></div>
						</div>
						<div class="checkbox-wrapper-6">
							<input class="tgl tgl-light" id="del_invoices_switch" name="del_invoices" type="checkbox" value="1">
							<label class="tgl-btn" for="del_invoices_switch"></label>
						</div>
					</div>

					<p class="grey font-size-12 mt-3 mb-0"><?php echo htmlspecialchars($lang['Deleting projects also removes their tasks and invoices.'] ?? 'Deleting projects also removes their tasks and invoices.'); ?></p>
					<input type="hidden" name="del_id" id="userDeleteId" value="" />
				</div>
				<div class="modal-footer user-delete-modal-footer">
					<button type="button" class="btn btn-secondary user-delete-cancel-btn" data-bs-dismiss="modal"><?php echo htmlspecialchars($lang['Cancel'] ?? 'Cancel'); ?></button>
					<button type="submit" name="del_user" value="1" class="btn btn-danger user-delete-confirm-btn"><?php echo htmlspecialchars($lang['Delete Permanently'] ?? 'Delete Permanently'); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
	var modalEl = document.getElementById('userDeleteModal');
	if (!modalEl) return;
	var deleteModal = new bootstrap.Modal(modalEl);
	var msgPrefix = <?php echo json_encode(($lang['Are you sure you want to permanently delete this'] ?? 'Are you sure you want to permanently delete this') . ' '); ?>;
	var nameLabel = <?php echo json_encode(($lang['Name'] ?? 'Name') . ': '); ?>;

	document.querySelectorAll('.btn-user-delete-permanent').forEach(function (button) {
		button.addEventListener('click', function () {
			var userId = this.getAttribute('data-user-id') || '';
			var userName = this.getAttribute('data-user-name') || '';
			var userType = this.getAttribute('data-user-type') || 'user';
			var projectCount = this.getAttribute('data-project-count') || '0';
			var taskCount = this.getAttribute('data-task-count') || '0';
			var invoiceCount = this.getAttribute('data-invoice-count') || '0';

			document.getElementById('userDeleteId').value = userId;
			document.getElementById('userDeleteModalMessage').textContent = msgPrefix + userType + '. ' + nameLabel + userName;
			document.getElementById('userDeleteProjectCount').textContent = '(' + projectCount + ')';
			document.getElementById('userDeleteTaskCount').textContent = '(' + taskCount + ')';
			document.getElementById('userDeleteInvoiceCount').textContent = '(' + invoiceCount + ')';

			document.getElementById('del_projects_switch').checked = false;
			document.getElementById('del_tasks_switch').checked = false;
			document.getElementById('del_invoices_switch').checked = false;

			var invoiceRow = document.getElementById('userDeleteInvoiceRow');
			if (userType === 'client') {
				invoiceRow.hidden = false;
			} else {
				invoiceRow.hidden = true;
			}

			deleteModal.show();
		});
	});
});
</script>
<style>
.user-trash-card-actions {
	display: flex !important;
	align-items: stretch;
	width: 100%;
	margin: 0;
	padding-top: 0;
	border-top: 0 !important;
	overflow: visible;
	clear: both;
	float: none;
}
.user-trash-card-actions::after {
	display: none !important;
	content: none !important;
}
.client-card .client-stats {
	margin-bottom: 0;
}
.user-trash-card-actions .restore_user.chat-frm,
.user-trash-card-actions .btn-user-delete-permanent {
	flex: 1 1 50%;
	width: 50% !important;
	max-width: 50%;
	margin: 0 !important;
	float: none !important;
	min-width: 0;
}
.user-trash-card-actions .user-trash-action-btn {
	display: block;
	width: 100% !important;
	min-height: 44px;
	margin: 0;
	padding: 12px 10px !important;
	border: 0;
	border-radius: 0;
	background: transparent;
	box-shadow: none;
	text-align: center !important;
	white-space: nowrap;
	overflow: visible;
	text-overflow: clip;
	font-size: 13px;
	font-weight: 600;
	letter-spacing: 0.02em;
	line-height: 1.2;
}
.user-trash-card-actions .restore_user {
	border-right: 1px solid #dfdfdf;
}
.user-trash-card-actions .alert-savestn {
	color: inherit;
}
#userDeleteModal .user-delete-toggle-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	padding: 12px 0;
	border-bottom: 1px solid rgba(0, 0, 0, 0.06);
}
#userDeleteModal .user-delete-toggle-row:last-of-type {
	border-bottom: 0;
}
#userDeleteModal .user-delete-toggle-copy {
	flex: 1 1 auto;
	min-width: 0;
}
#userDeleteModal .user-delete-toggle-title {
	font-weight: 600;
	margin-right: 4px;
}
#userDeleteModal .checkbox-wrapper-6 {
	flex: 0 0 auto;
}
#userDeleteModal .user-delete-modal-footer {
	display: flex;
	justify-content: flex-end;
	align-items: center;
	gap: 10px;
	flex-wrap: wrap;
}
#userDeleteModal .user-delete-cancel-btn,
#userDeleteModal .user-delete-confirm-btn {
	min-width: 120px;
	padding: 8px 16px;
	text-align: center;
}
#userDeleteModal .user-delete-confirm-btn {
	white-space: nowrap;
}
</style>