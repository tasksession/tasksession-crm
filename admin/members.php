<?php 
/*
 ================================================================================
   Task Session – Project Management System
   File    : Members.php
   Purpose : Displays and manages all staff records
 ================================================================================
 */
ob_start();
require_once("../includes/lib-initialize.php");
$title = "Members | ". $syatem_title;
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
if(isset($_POST['user_id'])){
$_SESSION['user_id'] = $_POST['user_id'];
}

//condition check for login

$id=$session->userId; //id of the current logged in user 
$user = User::findById((int)$id); //take the record of current user in an object array 	
$username=$user->firstName;;
$email=$user->email;;
$account_stat=$user->status;;
$user->regDate;

$toast_flash = comon_list_page_toast_from_query($lang, 'staff');
if(isset($_POST['del_user']))
{
	 $delUserId=$_POST['del_id'];
	if($delUserId!=$session->userId)
	{
	  $deleteUser="delete from users where id=$delUserId limit 1";
	  $userDeleted=mysqli_query($connect, $deleteUser);
	  if($userDeleted){
header("Location:members?message=success");
		 
	   } else{
header("Location:members?message=fail");
	   }
	  }
	  else
	  {
header("Location:members?message=fail");
	  }	
}
if(isset($_POST['bulk_del_user']))
{
	$staff_ids=$_POST['bulk_del_uid'];
	$bulk_del_val=$_POST['bulk_del_val'];
$flag=0;
		if($flag==0)
		{
			$staff_arr = explode(',', $staff_ids);
			foreach($staff_arr as $staff_id){
			$user = user::findById($staff_id); 
			// $user->id = $cli_id;
			$user->status=$bulk_del_val;
			$saveUser=$user->save();
			if ((int) $bulk_del_val === 1) {
				User::revokeRememberMeTokens((int) $staff_id);
			}
			}
			if($saveUser){
				header("Location:members?message=success");
			}else {
				header("Location:members?message=fail");
			}
		}
	
}
require_once __DIR__ . '/../includes/user_search_helper.php';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchCondition = comon_user_list_search_sql($connect, $searchQuery, [
    'custom_field_entity_types' => ['staff', 'admin'],
]);

$viewType = (isset($_GET['view']) && $_GET['view'] === 'table') ? 'table' : 'grid';

$limit = 12;
$page = isset($_GET["page"]) ? max(1, (int)$_GET["page"]) : 1;
$start_from = ($page - 1) * $limit;
// Fetch all users: super admin, admins, and staff
$superAdmin = user::findBySql("SELECT * FROM users WHERE id = 1 $searchCondition");
$admins = user::findBySql("SELECT * FROM users WHERE accountStatus = 1 AND id != 1 AND status = 0 $searchCondition");
$staffs = user::findBySql("SELECT * FROM users WHERE accountStatus = 3 AND status = 0 $searchCondition");
$totalAdminsAndStaff = count($admins) + count($staffs) + count($superAdmin);

$userTypeFilter = isset($_GET['user_type']) ? $_GET['user_type'] : '';

if ($userTypeFilter === 'admin') {
    $allUsers = $admins;
} elseif ($userTypeFilter === 'staff') {
    $allUsers = $staffs;
} else {
    $allUsers = array_merge($superAdmin, $admins, $staffs);
}

// Badge helper (used in both table + grid views)
if (!function_exists('userTypeBadge')) {
	function userTypeBadge($user, $isTableView = false) {
		$base = $isTableView ? 'badge ' : 'fixed-badge ';
		if (isset($user->id) && (int)$user->id === 1) return '<span class="'.$base.'color-done review done-bg-op">Super Admin</span>';
		if (isset($user->accountStatus) && (int)$user->accountStatus === 1) return '<span class="'.$base.'color-inprogress inprogress-bg-op ">Admin</span>';
		if (isset($user->accountStatus) && (int)$user->accountStatus === 3) return '<span class="'.$base.'color-review review-bg-op">Staff</span>';
		return '';
	}
}

$rolesMap = [];
if ($connect) {
	$rolesRes = mysqli_query($connect, 'SELECT id, name FROM roles');
	if ($rolesRes) {
		while ($roleRow = mysqli_fetch_assoc($rolesRes)) {
			$rolesMap[(int)$roleRow['id']] = $roleRow['name'];
		}
	}
}

if (!function_exists('membersRoleDisplayName')) {
	function membersRoleDisplayName($user, array $rolesMap, $lang) {
		if (!isset($user->id)) {
			return '';
		}
		$acct = isset($user->accountStatus) ? (int)$user->accountStatus : 0;
		if ($acct === 1) {
			return $lang['Admin - Full access'] ?? 'Admin - Full access';
		}
		if ($acct === 3) {
			$roleId = isset($user->role_id) ? (int)$user->role_id : 0;
			if ($roleId > 0 && isset($rolesMap[$roleId]) && $rolesMap[$roleId] !== '') {
				return $rolesMap[$roleId];
			}
			return $lang['No role assigned'] ?? 'No role assigned';
		}
		return '—';
	}
}

$isGroupsView = isset($_GET['groups']);

$tabParams = [];
if ($searchQuery !== '') {
	$tabParams['search'] = $searchQuery;
}
if (!$isGroupsView && $viewType === 'table') {
	$tabParams['view'] = 'table';
}
if (!$isGroupsView && $userTypeFilter !== '') {
	$tabParams['user_type'] = $userTypeFilter;
}
$membersTabQuery = $tabParams ? '?' . http_build_query($tabParams) : '';
$tabParamsGroups = $tabParams;
$tabParamsGroups['groups'] = '';
$teamGroupsTabQuery = '?' . http_build_query($tabParamsGroups);

$teamGroupsTableReady = false;
$allTeamGroupsRows = [];
if ($connect) {
	$tgChk = @mysqli_query($connect, "SHOW TABLES LIKE 'staff_team_groups'");
	if ($tgChk && mysqli_num_rows($tgChk) > 0) {
		$teamGroupsTableReady = true;
	}
}
if ($teamGroupsTableReady && $connect) {
	$tgDeletedAtCol = false;
	$tgColChk = @mysqli_query($connect, "SHOW COLUMNS FROM staff_team_groups LIKE 'deleted_at'");
	if ($tgColChk && mysqli_num_rows($tgColChk) > 0) {
		$tgDeletedAtCol = true;
	}
	$groupSearchSql = $tgDeletedAtCol ? ' WHERE g.deleted_at IS NULL ' : ' WHERE 1=1 ';
	if ($isGroupsView && $searchQuery !== '') {
		$safeG = mysqli_real_escape_string($connect, $searchQuery);
		$groupSearchSql .= " AND g.name LIKE '%$safeG%' ";
	}
	$gq = mysqli_query(
		$connect,
		"SELECT g.id, g.name, g.created_at, g.created_by,
			TRIM(COALESCE(u.firstName, '')) AS creator_name,
			(SELECT COUNT(*) FROM staff_team_group_members m WHERE m.group_id = g.id) AS member_count
			FROM staff_team_groups g
			LEFT JOIN users u ON u.id = g.created_by
			$groupSearchSql
			ORDER BY g.name ASC"
	);
	if ($gq) {
		while ($row = mysqli_fetch_assoc($gq)) {
			$allTeamGroupsRows[] = $row;
		}
	}
}

$total_group_records = count($allTeamGroupsRows);
$total_group_pages = max(1, (int) ceil($total_group_records / $limit));

$total_records = is_array($allUsers) ? count($allUsers) : 0;
$total_pages = (int) ceil($total_records / $limit);

$pagedUsers = (!$isGroupsView && is_array($allUsers)) ? array_slice($allUsers, $start_from, $limit) : [];
$pagedTeamGroups = ($isGroupsView && $teamGroupsTableReady) ? array_slice($allTeamGroupsRows, $start_from, $limit) : [];

$display_total_records = $isGroupsView ? $total_group_records : $total_records;
$display_total_pages = $isGroupsView ? $total_group_pages : $total_pages;
$display_page_count = $isGroupsView ? count($pagedTeamGroups) : count($pagedUsers);
$start_from_b = ($display_total_records > 0) ? ($start_from + 1) : 0;
$end_val_display = ($display_total_records > 0) ? min($start_from + $display_page_count, $display_total_records) : 0;

// Batch project/task counts for visible page (2 table scans instead of 2×N FIND_IN_SET queries)
$membersProjectCountMap = [];
$membersTaskCountMap = [];
$membersPagedIds = [];
if (!$isGroupsView && !empty($pagedUsers) && $connect) {
	foreach ($pagedUsers as $puRow) {
		$pid = isset($puRow->id) ? (int) $puRow->id : 0;
		if ($pid > 0) {
			$membersPagedIds[] = $pid;
			$membersProjectCountMap[$pid] = 0;
			$membersTaskCountMap[$pid] = 0;
		}
	}
	$membersPagedIds = array_values(array_unique($membersPagedIds));
	$idLookup = array_fill_keys($membersPagedIds, true);
	if (!empty($membersPagedIds)) {
		$projScan = @mysqli_query($connect, "SELECT s_ids FROM projects WHERE s_ids IS NOT NULL AND s_ids != ''");
		if ($projScan) {
			while ($pr = mysqli_fetch_assoc($projScan)) {
				foreach (array_filter(array_map('intval', explode(',', (string) ($pr['s_ids'] ?? '')))) as $sid) {
					if (isset($idLookup[$sid])) {
						$membersProjectCountMap[$sid]++;
					}
				}
			}
		}
		$taskScan = @mysqli_query($connect, "SELECT assigned_to FROM tasks WHERE assigned_to IS NOT NULL AND assigned_to != ''");
		if ($taskScan) {
			while ($tr = mysqli_fetch_assoc($taskScan)) {
				foreach (array_filter(array_map('intval', explode(',', (string) ($tr['assigned_to'] ?? '')))) as $tid) {
					if (isset($idLookup[$tid])) {
						$membersTaskCountMap[$tid]++;
					}
				}
			}
		}
	}
}

// Group modal picker: slim columns + one profile_pics batch (avoid SELECT * + per-user avatar queries)
$teamGroupPickableUsers = [];
$pickSelectCols = 'id, firstName, email';
if (isset($connect) && function_exists('comon_users_searchable_columns')) {
	$usersCols = comon_users_searchable_columns($connect);
	if (in_array('last_name', $usersCols, true)) {
		$pickSelectCols = 'id, firstName, last_name, email';
	} elseif (in_array('lastName', $usersCols, true)) {
		// Legacy column — alias into User::$last_name
		$pickSelectCols = 'id, firstName, lastName AS last_name, email';
	}
}
$pickListUsers = user::findBySql(
	"SELECT {$pickSelectCols} FROM users WHERE status = 0 AND (id = 1 OR accountStatus = 1 OR accountStatus = 3) ORDER BY firstName ASC"
);
$pickAvatarUrls = [];
if (is_array($pickListUsers) && $pickListUsers !== [] && function_exists('crm_batch_profile_pic_urls')) {
	$pickIds = [];
	foreach ($pickListUsers as $pu) {
		$pickIds[] = (int) ($pu->id ?? 0);
	}
	$pickAvatarUrls = crm_batch_profile_pic_urls($pickIds, 32, 32);
}
if (is_array($pickListUsers)) {
	foreach ($pickListUsers as $pu) {
		$uid = (int) ($pu->id ?? 0);
		$lastName = function_exists('crm_user_last_name') ? crm_user_last_name($pu) : (string) ($pu->last_name ?? '');
		$fn = trim(($pu->firstName ?? '') . ' ' . $lastName);
		$email = (string) ($pu->email ?? '');
		$imgUrl = $pickAvatarUrls[$uid] ?? '';
		$useImg = ($imgUrl !== '' && strpos($imgUrl, 'upload-img.jpg') === false);
		$imageHtml = $useImg
			? '<img src="' . htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8') . '" width="32" height="32" class="rounded-circle" alt="">'
			: getUserAvatarHtml($uid, $pu->firstName ?? '', $lastName, 32, 32, 'rounded-circle', $pu->firstName ?? '');
		$teamGroupPickableUsers[] = [
			'id' => $uid,
			'name' => htmlspecialchars($fn !== '' ? $fn : ('User #' . $uid), ENT_QUOTES, 'UTF-8'),
			'email' => htmlspecialchars($email, ENT_QUOTES, 'UTF-8'),
			'image' => $imageHtml,
			'search' => strtolower($fn . ' ' . $email),
		];
	}
}
require_once __DIR__ . '/../includes/list-bulk-helpers.php';
$showMembersBulkToolbar = !$isGroupsView;
$viewToggleParams = array();
if ($searchQuery !== '') {
	$viewToggleParams['search'] = $searchQuery;
}
if (!$isGroupsView && $userTypeFilter !== '') {
	$viewToggleParams['user_type'] = $userTypeFilter;
}
if ($isGroupsView) {
	$viewToggleParams['groups'] = '';
}
$membersGridHref = 'members' . tasksession_build_list_query($viewToggleParams);
$membersTableParams = $viewToggleParams;
$membersTableParams['view'] = 'table';
$membersTableHref = 'members' . tasksession_build_list_query($membersTableParams);
$membersSearchClearParams = $viewToggleParams;
unset($membersSearchClearParams['search']);
if (!$isGroupsView && $viewType === 'table') {
	$membersSearchClearParams['view'] = 'table';
}
$membersSearchClearHref = 'members' . tasksession_build_list_query($membersSearchClearParams);
$membersFilterBase = array();
if ($searchQuery !== '') {
	$membersFilterBase['search'] = $searchQuery;
}
if ($viewType === 'table') {
	$membersFilterBase['view'] = 'table';
}
$membersSearchPlaceholder = $isGroupsView
	? ($lang['Search groups'] ?? 'Search groups')
	: ($lang['Search Staff'] ?? 'Search staff');
$tsMenuIco = 'tasksession-timer-log-menu-ico me-2';
$membersFilterAllHref = 'members' . tasksession_build_list_query($membersFilterBase);
$membersFilterAdminParams = $membersFilterBase;
$membersFilterAdminParams['user_type'] = 'admin';
$membersFilterAdminHref = 'members' . tasksession_build_list_query($membersFilterAdminParams);
$membersFilterStaffParams = $membersFilterBase;
$membersFilterStaffParams['user_type'] = 'staff';
$membersFilterStaffHref = 'members' . tasksession_build_list_query($membersFilterStaffParams);
?>
	<div class="page-container">
		<div class="container-fluid">
			<div class="row row-eq-height">
				<?php  include("../templates/sidebar.php"); ?>
					<div class="page-content">
						<?php include('../templates/top-header.php'); ?>
                <link rel="stylesheet" href="../assets/css/kanban-bulk.css?v=9">
							<div class="row">
								<div class="col-md-12 margin-top-10">
									<div class="row bg-grey">
										<div class="col-md-12 project-tabs">
											<div class="row">
												<div class="project-tabs-header">
													<div class="scrollable-tabs-container d-flex col-gap-40 col-gap-40-sep">
														<?php
														$allStaff = user::findBySql("SELECT * FROM users WHERE accountStatus = 3 AND status = 0");
														?>
														<div class="main-heading"><h1><?php echo $isGroupsView ? htmlspecialchars($lang['Team Groups'] ?? 'Team groups') : htmlspecialchars($lang['Admins & Staff']); ?> <span>(<?php echo $isGroupsView ? (int) $total_group_records : (int) $totalAdminsAndStaff; ?>)</span></h1></div>
														<div class="icon-container sep">
															<div class="icon-container members-team-tabs">
																<a href="members<?php echo htmlspecialchars($membersTabQuery, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo !$isGroupsView ? 'active' : ''; ?>">
																	<?php echo ts_icon('user-group', 'w-5'); ?><?php echo htmlspecialchars($lang['Members tab'] ?? 'Members'); ?>
																</a>
																<a href="members<?php echo htmlspecialchars($teamGroupsTabQuery, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isGroupsView ? 'active' : ''; ?>">
																	<?php echo ts_icon('view-grid', 'w-5'); ?><?php echo htmlspecialchars($lang['Team Groups'] ?? 'Team groups'); ?>
																</a>
															</div>
															<a href="user-trash">
															<?php echo ts_icon('trash-box'); ?>
															<?php echo $lang['Show Trash Users']; ?> </a>
														</div>
													</div>
												</div>
											
											<div class="search">
												<div class="search-icon border-btn-a" onclick="toggleSearch()">
													<?php echo ts_icon('search', 'w-2'); ?>
												</div>
												<form method="GET" action="members" class="search-form" id="searchForm">
													<input type="hidden" name="page" value="1">
													<?php if ($isGroupsView): ?>
														<input type="hidden" name="groups" value="">
													<?php endif; ?>
													<?php if (!$isGroupsView && $viewType === 'table'): ?>
														<input type="hidden" name="view" value="table">
													<?php endif; ?>
													<?php if (!$isGroupsView && $userTypeFilter !== ''): ?>
														<input type="hidden" name="user_type" value="<?php echo htmlspecialchars($userTypeFilter, ENT_QUOTES, 'UTF-8'); ?>">
													<?php endif; ?>
													<div class="input-group">
														<span class="search-field-icon">
															<?php echo ts_icon('search', 'w-2'); ?>
														</span>
														<input type="text" id="staff-search" name="search" class="form-control" placeholder="<?php echo htmlspecialchars($membersSearchPlaceholder, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($searchQuery ?? ''); ?>">
														<?php if ($searchQuery !== ''): ?>
															<a href="<?php echo htmlspecialchars($membersSearchClearHref, ENT_QUOTES, 'UTF-8'); ?>" class="cross" title="<?php echo htmlspecialchars($lang['Close'] ?? 'Close', ENT_QUOTES, 'UTF-8'); ?>">
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
											<?php if ($showMembersBulkToolbar): ?>
											<div class="edit-overview-btn ts-list-bulk-toolbar-wrap d-none d-md-block">
												<div class="icon-container sep ts-list-bulk-toolbar" data-ts-list-bulk-toolbar="members">
													<div class="pm-trash task-trash align-middle d-flex col-gap-5">
														<a href="#" onclick="TsListBulk.enter('members'); return false;" class="bulk-delete-tab red border-btn-a" id="tsListBulk_members_bulkSelectTab" title="<?php echo htmlspecialchars($lang['Bulk Select'] ?? 'Bulk select', ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($lang['Bulk Select'] ?? 'Bulk select', ENT_QUOTES, 'UTF-8'); ?>">
															<?php echo ts_icon('duplicate', 'w-2'); ?>
														</a>
														<div class="media-view-toggle" id="membersViewToggle" role="group" aria-label="View mode">
															<a href="<?php echo htmlspecialchars($membersGridHref, ENT_QUOTES, 'UTF-8'); ?>" class="media-view-toggle__btn border-btn-a<?php echo $viewType !== 'table' ? ' is-active' : ''; ?>" title="<?php echo htmlspecialchars($lang['Grid View'] ?? 'Grid view', ENT_QUOTES, 'UTF-8'); ?>">
																<?php echo ts_icon('view-grid', 'w-2'); ?>
															</a>
															<a href="<?php echo htmlspecialchars($membersTableHref, ENT_QUOTES, 'UTF-8'); ?>" class="media-view-toggle__btn border-btn-a<?php echo $viewType === 'table' ? ' is-active' : ''; ?>" title="<?php echo htmlspecialchars($lang['Table View'] ?? 'Table view', ENT_QUOTES, 'UTF-8'); ?>">
																<?php echo ts_icon('table', 'w-2'); ?>
															</a>
														</div>
														<a href="#" onclick="TsListBulk.exit('members'); return false;" class="bulk-delete-tab border-btn-a" id="tsListBulk_members_bulkBackTab" style="display:none;">
															<?php echo ts_icon('arrow-left', 'w-2'); ?>
															<span><?php echo $lang['Back'] ?? 'Back'; ?></span>
														</a>
														<a href="#" onclick="TsListBulk.selectAll('members'); return false;" class="bulk-delete-tab border-btn-a" id="tsListBulk_members_bulkSelectAllTab" style="display:none;">
															<?php echo ts_icon('check-circle', 'w-2'); ?>
															<span id="tsListBulk_members_bulkSelectAllLabel"><?php echo $lang['Select all'] ?? 'Select all'; ?></span>
														</a>
														<a href="#" onclick="TsListBulk.deleteSelected('members'); return false;" class="bulk-delete-tab border-btn-a" id="tsListBulk_members_bulkDeleteTab" style="display:none;">
															<?php echo ts_icon('delete', 'w-2'); ?>
															<span id="tsListBulk_members_bulkDeleteLabel"><?php echo $lang['Delete'] ?? 'Delete'; ?></span>
														</a>
													</div>
												</div>
											</div>
											<?php else: ?>
											<div class="edit-overview-btn ts-list-bulk-toolbar-wrap d-none d-md-block">
												<div class="icon-container sep">
													<div class="pm-trash task-trash align-middle d-flex col-gap-5">
														<div class="media-view-toggle" id="membersViewToggle" role="group" aria-label="View mode">
															<a href="<?php echo htmlspecialchars($membersGridHref, ENT_QUOTES, 'UTF-8'); ?>" class="media-view-toggle__btn border-btn-a<?php echo $viewType !== 'table' ? ' is-active' : ''; ?>" title="<?php echo htmlspecialchars($lang['Grid View'] ?? 'Grid view', ENT_QUOTES, 'UTF-8'); ?>">
																<?php echo ts_icon('view-grid', 'w-2'); ?>
															</a>
															<a href="<?php echo htmlspecialchars($membersTableHref, ENT_QUOTES, 'UTF-8'); ?>" class="media-view-toggle__btn border-btn-a<?php echo $viewType === 'table' ? ' is-active' : ''; ?>" title="<?php echo htmlspecialchars($lang['Table View'] ?? 'Table view', ENT_QUOTES, 'UTF-8'); ?>">
																<?php echo ts_icon('table', 'w-2'); ?>
															</a>
														</div>
													</div>
												</div>
											</div>
											<?php endif; ?>
											<?php if (!$isGroupsView): ?>
											<div class="edit-overview-btn">
												<td class="extra-height">
													<div class="action-toggle border-btn-a" data-bs-toggle="collapse" data-bs-target="#user-type-menu" role="button" tabindex="0">
														<span class="action-text"><?php echo $lang['Filters']; ?></span><span class="mobile-ellipsis"><?php echo ts_icon('ellipsis', 'w-2'); ?></span>
														<?php echo ts_icon('filter', 'w-2'); ?>
													</div>
													<div id="user-type-menu" class="toggle-action collapse shadow-dept">
														<ul>
														<li class="d-block d-md-none">
															<a href="add-admin">
																<?php echo ts_icon('user-plus', $tsMenuIco); ?>
																<span><?php echo $lang['Create Admin']; ?></span>
															</a>
														</li>
														<li class="d-block d-md-none">
															<a href="add-staff">
																<?php echo ts_icon('user-group', $tsMenuIco); ?>
																<span><?php echo $lang['Create Staff']; ?></span>
															</a>
														</li>
														<li class="d-block d-md-none">
															<a href="#" data-bs-toggle="modal" data-bs-target="#teamGroupCreateModal">
																<?php echo ts_icon('user-group', $tsMenuIco); ?>
																<span><?php echo htmlspecialchars($lang['Create Group'] ?? 'Create group'); ?></span>
															</a>
														</li>
														<li class="menu-divider d-block d-md-none">
															<hr class="dropdown-divider">
														</li>
															<li class="<?php echo $userTypeFilter === '' ? 'active' : ''; ?>">
																<a href="<?php echo htmlspecialchars($membersFilterAllHref, ENT_QUOTES, 'UTF-8'); ?>">
																	<?php echo ts_icon('dot', $tsMenuIco); ?>
																	<span><?php echo $lang['All Users']; ?></span>
																</a>
															</li>
															<li class="<?php echo $userTypeFilter === 'admin' ? 'active' : ''; ?>">
																<a href="<?php echo htmlspecialchars($membersFilterAdminHref, ENT_QUOTES, 'UTF-8'); ?>">
																	<?php echo ts_icon('dot', $tsMenuIco); ?>
																	<span><?php echo $lang['Admin']; ?></span>
																</a>
															</li>
															<li class="<?php echo $userTypeFilter === 'staff' ? 'active' : ''; ?>">
																<a href="<?php echo htmlspecialchars($membersFilterStaffHref, ENT_QUOTES, 'UTF-8'); ?>">
																	<?php echo ts_icon('dot', $tsMenuIco); ?>
																	<span><?php echo $lang['Staff']; ?></span>
																</a>
															</li>
														</ul>
													</div>
												</td>
											</div>
											<?php endif; ?>
											<div class="d-none d-md-block">
												<div id="primary-btn" class="action-toggle primary-btn" data-bs-toggle="collapse" data-bs-target="#dropdown-menu" role="button" tabindex="0">
													<span class="action-text"><?php echo htmlspecialchars($lang['Create new'] ?? $lang['Create New User'] ?? 'Create new'); ?></span>
													<?php echo ts_icon('plus', 'w-2'); ?>
												</div>
												<div id="dropdown-menu" class="toggle-action collapse shadow-dept">
													<ul>
														<li>
															<a href="add-admin">
															<?php echo ts_icon('user-plus', $tsMenuIco); ?>
															<span><?php echo $lang['Create Admin']; ?> </span>
															</a>
														</li>
														<li>
															<a href="add-staff">
															<?php echo ts_icon('user-group', $tsMenuIco); ?>
															<span><?php echo $lang['Create Staff']; ?> </span>
															</a>
														</li>
														<li>
															<a href="#" data-bs-toggle="modal" data-bs-target="#teamGroupCreateModal">
															<?php echo ts_icon('user-group', $tsMenuIco); ?>
															<span><?php echo htmlspecialchars($lang['Create Group'] ?? 'Create group'); ?> </span>
															</a>
														</li>
													</ul>
												</div>
											</div>
										</div>
								</div>
							</div>					
								<?php if (!$isGroupsView && $viewType === 'table'): ?>
									<div class="clearfix"></div>
									<div data-ts-list-bulk-root="members">
									<div class="table-responsive scroll-x vh-100">
										<table class="table table-new table-invoice h-100">
											<thead>
												<tr>
													<th class="ts-list-bulk-checkbox-col text-center" aria-hidden="true"></th>
													<th style="min-width: 270px; text-align: left;"><?php echo $lang['Members'] ?? 'Members'; ?></th>
													<th style="text-align: left;"><?php echo $lang['Email']; ?></th>
													<th><?php echo $lang['Role'] ?? 'Role'; ?></th>
													<th><?php echo $lang['Projects']; ?></th>
													<th><?php echo $lang['Task']; ?></th>
													<th><?php echo $lang['Last login'] ?? 'Last login'; ?></th>
													<th class="min-width-200"><?php echo $lang['Action']; ?></th>
												</tr>
											</thead>
											<tbody id="projects-tbl">
												<?php
												if (empty($pagedUsers)) {
													echo '<tr><td colspan="8">'.$lang['No records Found!'].'</td></tr>';
												} else {
													// Last login map
													$userIds = [];
													foreach ($pagedUsers as $u) { if (isset($u->id)) $userIds[] = (int)$u->id; }
													$lastLoginMap = [];
													if (!empty($userIds)) {
														$idList = implode(',', array_unique($userIds));
														$loginRes = mysqli_query($connect, "SELECT user_id, MAX(attempt_time) AS last_login
															FROM login_attempts
															WHERE success = 1 AND type = 'login' AND user_id IN ($idList)
															GROUP BY user_id");
														if ($loginRes) {
															while ($lr = mysqli_fetch_assoc($loginRes)) {
																$uid = isset($lr['user_id']) ? (int)$lr['user_id'] : 0;
																if ($uid > 0 && !empty($lr['last_login'])) {
																	$lastLoginMap[$uid] = $lr['last_login'];
																}
															}
														}
													}
													$settingsTz = null;
													if (class_exists('settings')) {
														$sys = settings::findById(1);
														if ($sys && !empty($sys->time_zone)) { $settingsTz = $sys->time_zone; }
													}
													$targetTz = $settingsTz ?: (isset($time_zone) && !empty($time_zone) ? $time_zone : date_default_timezone_get());
													$serverTz = ini_get('date.timezone');
													if (!$serverTz) { $serverTz = 'UTC'; }

													foreach ($pagedUsers as $idx => $recentlyRegisteredUser) {
														$profilePictureObj = profilePicture::findByfkUserId($recentlyRegisteredUser->id);
														$avatarData = ['type' => 'initials', 'url' => ''];
														$filenamePicture = '';
														if ($profilePictureObj) {
															foreach ($profilePictureObj as $displayPicture) {
																$filenamePic = $displayPicture->filename;
																$filenamePicture = getProfilePicUrl($filenamePic, 130, 130);
															}
															if (!empty($filenamePicture)) {
																$avatarData = ['type' => 'image', 'url' => $filenamePicture];
															}
														} else {
															$avatarData = getUserAvatarData($recentlyRegisteredUser->id, $recentlyRegisteredUser->firstName, function_exists('crm_user_last_name') ? crm_user_last_name($recentlyRegisteredUser) : (string) ($recentlyRegisteredUser->last_name ?? ''), 130, 130);
															$filenamePicture = $avatarData['type'] === 'image' ? $avatarData['url'] : '';
														}

														$isOnline = function_exists('user_presence_from_user')
															? user_presence_from_user($recentlyRegisteredUser)
															: (
																isset($recentlyRegisteredUser->last_seen) &&
																isset($recentlyRegisteredUser->session_status) &&
																$recentlyRegisteredUser->session_status === 'online' &&
																(time() - (int)$recentlyRegisteredUser->last_seen) < 300
															);

														$userId = (int)$recentlyRegisteredUser->id;
														$projectCount = (int) ($membersProjectCountMap[$userId] ?? 0);
														$taskCount = (int) ($membersTaskCountMap[$userId] ?? 0);

														$memberSinceText = '';
														if (isset($recentlyRegisteredUser->regDate) && !empty($recentlyRegisteredUser->regDate)) {
															$ts = strtotime((string)$recentlyRegisteredUser->regDate);
															if ($ts) { $memberSinceText = date('F d, Y', $ts); }
														}

														$lastLoginRaw = $lastLoginMap[$userId] ?? '';
														$lastLoginText = '-';
														if (!empty($lastLoginRaw)) {
															try {
																$dt = new DateTime($lastLoginRaw, new DateTimeZone($serverTz));
																$dt->setTimezone(new DateTimeZone($targetTz));
																$dt->add(new DateInterval('PT3H'));
																$lastLoginText = $dt->format('M j, Y \\a\\t g:i A');
															} catch (Exception $e) {
																$lastLoginText = date('M j, Y \\a\\t g:i A', strtotime($lastLoginRaw));
															}
														}
														?>
														<tr>
															<td>
																<div class="checkbox check-invoice">
																	<?php if ($recentlyRegisteredUser->id != 1): ?>
																		<input type="checkbox" id="client-<?php echo $recentlyRegisteredUser->id; ?>" name="client-checkbox" class="client-bulk-checkbox" value="<?php echo $recentlyRegisteredUser->id; ?>">
																		<label for="client-<?php echo $recentlyRegisteredUser->id; ?>"></label>
																	<?php endif; ?>
																</div>
															</td>
															<td style="text-align: left;">
																<div class="d-flex align-items-center col-gap-10" style="min-width: 270px; text-align: left;">
																	<div class="profile-img-wrapper" style="position:relative; display:inline-block;">
																		<span class="online-dot<?php echo $isOnline ? ' online' : ' offline'; ?>"></span>
																		<?php
																		if ($avatarData['type'] === 'image') {
																			echo '<img src="' . $filenamePicture . '" class="img-fluid profile-img" style="width:36px; height:36px; border-radius:50%;" />';
																		} else {
																			echo getUserAvatarHtml($recentlyRegisteredUser->id, $recentlyRegisteredUser->firstName, function_exists('crm_user_last_name') ? crm_user_last_name($recentlyRegisteredUser) : (string) ($recentlyRegisteredUser->last_name ?? ''), 36, 36, 'img-fluid rounded-circle', $recentlyRegisteredUser->firstName);
																		}
																		?>
																	</div>
																	<div style="text-align: left;">
																		<div><?php echo $recentlyRegisteredUser->firstName; ?></div>
																		<?php if ($memberSinceText !== ''): ?>
																			<div class="grey font-size-12"><?php echo ($lang['Member since'] ?? 'Member since'); ?>: <?php echo $memberSinceText; ?></div>
																		<?php endif; ?>
																	</div>
																</div>
															</td>
															<td style="text-align: left;">
																<?php echo htmlspecialchars($recentlyRegisteredUser->email); ?>
																<div class="member-role-line grey font-size-12 mt-2"><?php echo htmlspecialchars($lang['Role'] ?? 'Role'); ?>: <?php echo htmlspecialchars(membersRoleDisplayName($recentlyRegisteredUser, $rolesMap, $lang)); ?></div>
															</td>
															<td><?php echo userTypeBadge($recentlyRegisteredUser, true); ?></td>
															<td><?php echo $projectCount; ?></td>
															<td><?php echo $taskCount; ?></td>
															<td><?php echo $lastLoginText; ?></td>
															<td>
																<a href="profile?user_id=<?php echo $recentlyRegisteredUser->id; ?>" class="btn btn-outline-grey justify-content-center">
																	<?php echo $lang['View Profile']; ?>
																</a>
															</td>
														</tr>
													<?php
													}
												}
												?>
											</tbody>
										</table>
									</div>
									</div>
								<?php elseif (!$isGroupsView): ?>
										<div class="row clients-row" data-ts-list-bulk-root="members">
											<?php
												  $urlB = $url;
												   // userTypeBadge() is declared above (shared by table + grid)
													if (empty($pagedUsers)) {
														echo '<div class="empty-box">No users available!</div>';
													}
														 foreach($pagedUsers as $recentlyRegisteredUser){ 
														  
															$profilePictureObj=profilePicture::findByfkUserId($recentlyRegisteredUser->id);
											
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
																		 // Use global avatar function for initials
																		 $avatarData = getUserAvatarData($recentlyRegisteredUser->id, $recentlyRegisteredUser->firstName, function_exists('crm_user_last_name') ? crm_user_last_name($recentlyRegisteredUser) : (string) ($recentlyRegisteredUser->last_name ?? ''), 130, 130);
																		 $profilePic = $avatarData['type'] === 'image' ? $avatarData['url'] : '';
																		 $filenamePicture = $avatarData['type'] === 'image' ? $avatarData['url'] : '';
																	 } 
															$staff_name = $recentlyRegisteredUser->firstName;
															$isOnline = function_exists('user_presence_from_user')
																? user_presence_from_user($recentlyRegisteredUser)
																: (
																	isset($recentlyRegisteredUser->last_seen) &&
																	isset($recentlyRegisteredUser->session_status) &&
																	$recentlyRegisteredUser->session_status === 'online' &&
																	(time() - (int)$recentlyRegisteredUser->last_seen) < 300
																);
															$staffId = (int) $recentlyRegisteredUser->id;
															$projectCount = (int) ($membersProjectCountMap[$staffId] ?? 0);
															$taskCount = (int) ($membersTaskCountMap[$staffId] ?? 0);
														  ?>
												<div class="col-md-4 col-lg-4 col-xl-3 mb-2 staff user-imgbox img-circle">
													<div class="client-card">
														<?php if ($recentlyRegisteredUser->id != 1): ?>
															<input type="checkbox" value="<?php echo $recentlyRegisteredUser->id;?>" name="client-checkbox" class="client-bulk-checkbox" style="position:absolute; top:12px; left:12px; z-index:3;">
														<?php endif; ?>
														<div class="d-flex col-gap-20 align-items-center flex-wrap">
															<div class="profile-img-wrapper" style="position:relative; display:inline-block;">
																<span class="online-dot<?php echo $isOnline ? ' online' : ' offline'; ?>"></span>
																<?php 
																if ($avatarData['type'] === 'image') {
																	echo '<img src="' . $filenamePicture . '" class="img-fluid profile-img" />';
																} else {
																	echo getUserAvatarHtml($recentlyRegisteredUser->id, $recentlyRegisteredUser->firstName, function_exists('crm_user_last_name') ? crm_user_last_name($recentlyRegisteredUser) : (string) ($recentlyRegisteredUser->last_name ?? ''), 130, 130, 'img-fluid profile-img', $recentlyRegisteredUser->firstName);
																}
																?>
															</div>
															<div class="client-info">
															<?php echo userTypeBadge($recentlyRegisteredUser, false); ?>
																<div class="grey font-size-20"><?php echo $recentlyRegisteredUser->firstName;?> </div>
																<div class="client-email"><?php echo htmlspecialchars($recentlyRegisteredUser->email); ?></div>
																<div class="member-role-line grey font-size-12 mt-2"><?php echo htmlspecialchars($lang['Role'] ?? 'Role'); ?>: <?php echo htmlspecialchars(membersRoleDisplayName($recentlyRegisteredUser, $rolesMap, $lang)); ?></div>
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
														</div>
														<a href="profile?user_id=<?php echo $recentlyRegisteredUser->id; ?>" class="stretch-btn">
															<?php echo $lang['View Profile']; ?>
															<?php echo ts_icon('arrow-right-circle'); ?>
														</a>
													</div>
												</div>
										      <?php } ?>
												<div class="norecords" style="display: none;">
											<?php echo $lang['No records Found!']; ?>
										</div>
									</div>
								<?php elseif ($isGroupsView): ?>
									<div class="row clients-row">
										<?php
										if (!$teamGroupsTableReady) {
											echo '<div class="col-12"><div class="empty-box">' . htmlspecialchars($lang['No team groups yet'] ?? 'No team groups yet.') . '</div></div>';
										} elseif (empty($pagedTeamGroups)) {
											echo '<div class="col-12"><div class="empty-box">' . htmlspecialchars($lang['No team groups yet'] ?? 'No team groups yet.') . '</div></div>';
										} else {
											foreach ($pagedTeamGroups as $grow) {
												$gid = (int) $grow['id'];
												$gname = (string) $grow['name'];
												$mc = (int) $grow['member_count'];
												$createdRaw = isset($grow['created_at']) ? (string) $grow['created_at'] : '';
												$createdDisp = '';
												if ($createdRaw !== '') {
													$cts = strtotime($createdRaw);
													if ($cts) {
														$createdDisp = sprintf(
															$lang['Team group created on'] ?? 'Created on %s',
															date('M j, Y', $cts)
														);
													}
												}
												$creatorNameRaw = isset($grow['creator_name']) ? trim((string) $grow['creator_name']) : '';
												$creatorByDisp = '';
												if ($creatorNameRaw !== '') {
													$creatorByDisp = sprintf(
														$lang['Team group created by'] ?? 'Created by %s',
														$creatorNameRaw
													);
												}
												$uids = [];
												if ($connect) {
													$uq = mysqli_query($connect, 'SELECT user_id FROM staff_team_group_members WHERE group_id=' . $gid . ' ORDER BY user_id ASC LIMIT 12');
													if ($uq) {
														while ($ur = mysqli_fetch_assoc($uq)) {
															$uids[] = (int) $ur['user_id'];
														}
													}
												}
												$showAv = array_slice($uids, 0, 5);
												$extra = max(0, $mc - count($showAv));
												?>
												<div class="col-md-4 col-lg-4 col-xl-3 mb-2">
													<div class="client-card team-group-card h-100 d-flex flex-column">
														<div class="file-actions-dropdown">
															<div class="dropdown">
																<button class="btn-dots dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="<?php echo htmlspecialchars($lang['Actions'] ?? 'Actions'); ?>">
																	<?php echo ts_icon('dots-vertical', 'w-6'); ?>
																</button>
																<ul class="dropdown-menu dropdown-menu-end shadow">
																	<li>
																		<button type="button" class="dropdown-item d-flex align-items-center js-edit-team-group" data-group-id="<?php echo $gid; ?>">
																			<?php echo ts_icon('edit', 'w-4 me-2 tasksession-timer-log-menu-ico'); ?>
																			<?php echo htmlspecialchars($lang['Edit'] ?? 'Edit'); ?>
																		</button>
																	</li>
																	<li>
																		<button type="button" class="dropdown-item d-flex align-items-center js-clone-team-group" data-group-id="<?php echo $gid; ?>">
																			<?php echo ts_icon('duplicate', 'w-4 me-2 tasksession-timer-log-menu-ico'); ?>
																			<?php echo htmlspecialchars($lang['Clone'] ?? 'Clone'); ?>
																		</button>
																	</li>
																	<li>
																		<button type="button" class="dropdown-item d-flex align-items-center text-danger js-delete-team-group" data-group-id="<?php echo $gid; ?>" data-group-name="<?php echo htmlspecialchars($gname, ENT_QUOTES, 'UTF-8'); ?>">
																			<?php echo ts_icon('delete', 'w-4 me-2 tasksession-timer-log-menu-ico'); ?>
																			<?php echo htmlspecialchars($lang['Delete'] ?? 'Delete'); ?>
																		</button>
																	</li>
																</ul>
															</div>
														</div>
														<div class="grey font-size-20 mb-1 team-group-card__title team-group-card__title--with-actions"><?php echo htmlspecialchars($gname); ?></div>
														<div class="grey font-size-12 mb-2 team-group-card__meta">
															<?php
															if ($mc === 1) {
																echo '1 ' . htmlspecialchars($lang['member'] ?? 'member');
															} else {
																echo (int) $mc . ' ' . htmlspecialchars($lang['members'] ?? 'members');
															}
															?>
														</div>
														<?php if ($createdDisp !== '') { ?>
															<div class="grey font-size-11 team-group-card__created<?php echo $creatorByDisp !== '' ? ' mb-1' : ' mb-2'; ?>"><?php echo htmlspecialchars($createdDisp); ?></div>
														<?php } ?>
														<?php if ($creatorByDisp !== '') { ?>
															<div class="grey font-size-11 mb-2 team-group-card__creator"><?php echo htmlspecialchars($creatorByDisp); ?></div>
														<?php } ?>
														<div class="d-flex flex-wrap clients-rpt">
															<?php
															foreach ($showAv as $avUid) {
																$uobj = User::findById($avUid);
																if (!$uobj) {
																	continue;
																}
																$tip = htmlspecialchars(trim(($uobj->firstName ?? '') . ' ' . (function_exists('crm_user_last_name') ? crm_user_last_name($uobj) : (string) ($uobj->last_name ?? ''))));
																$thumb = getUserAvatarHtml($uobj->id, $uobj->firstName ?? '', function_exists('crm_user_last_name') ? crm_user_last_name($uobj) : (string) ($uobj->last_name ?? ''), 40, 40, 'rounded-circle img-fluid', $uobj->firstName ?? '');
																echo '<div class="avatar-circle client-avatar" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="' . $tip . '" data-bs-original-title="' . $tip . '">';
																echo '<span class="online-dot offline"></span>';
																echo $thumb;
																echo '</div>';
															}
															if ($extra > 0) {
																echo '<div class="avatar-circle client-avatar d-flex align-items-center justify-content-center" style="margin-left:-10px;font-size:12px;font-weight:600;">+' . (int) $extra . '</div>';
															}
															?>
														</div>
													</div>
												</div>
												<?php
											}
										}
										?>
									</div>
								<?php endif; ?>

								<!-- Pagination -->
								<?php
								$listPaginationParams = array();
								if ($searchQuery !== '') {
									$listPaginationParams['search'] = $searchQuery;
								}
								if ($isGroupsView) {
									$listPaginationParams['groups'] = '';
								}
								if (!$isGroupsView && $userTypeFilter !== '') {
									$listPaginationParams['user_type'] = $userTypeFilter;
								}
								if (!$isGroupsView && $viewType === 'table') {
									$listPaginationParams['view'] = 'table';
								}
								$membersPaginationMeta = tasksession_list_pagination_meta(
									$display_total_records,
									$start_from,
									$display_page_count,
									$listPaginationParams
								);
								if ($display_total_records > $limit) {
									tasksession_render_list_pagination(
										$membersPaginationMeta,
										$display_total_records,
										$display_total_pages,
										$page
									);
								}
								?>
								</div>
							</div>
					    </div>
					</div>
				</div>
			</div>
<?php
include dirname(__DIR__) . '/templates/modals/team-groups-modals.php';
?>
<script>window.__toastFlash=<?php echo json_encode($toast_flash, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
<script src="<?php echo htmlspecialchars(rtrim((string) $url, '/') . '/assets/js/toast.js', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php
if ($showMembersBulkToolbar) {
	echo tasksession_list_bulk_form_html(array(
		'form_id' => 'tsListBulkFormMembers',
		'ids_field' => 'bulk_del_uid',
		'hidden_fields' => array(
			'bulk_del_val' => '1',
			'bulk_del_user' => '1',
		),
	));
	echo tasksession_list_bulk_script_tag(array(
		'instances' => array(
			'members' => array(
				'rootSelector' => '[data-ts-list-bulk-root="members"]',
				'formId' => 'tsListBulkFormMembers',
				'idsField' => 'bulk_del_uid',
				'checkboxName' => 'client-checkbox',
				'deleteConfirm' => $lang['Delete selected members?'] ?? 'Delete selected members?',
				'deleteLabel' => $lang['Delete'] ?? 'Delete',
				'selectAllLabel' => $lang['Select all'] ?? 'Select all',
				'deselectAllLabel' => $lang['Deselect all'] ?? 'Deselect all',
			),
		),
	));
}
include '../templates/main-footer.php';
?>
<script src="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>assets/js/team-groups-modals.js"></script>