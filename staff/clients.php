<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : clients.php
   Purpose : Displays and manages all client records
 ================================================================================
 */
ob_start();
require_once("../includes/lib-initialize.php");
$title = "Clients | ". $syatem_title;
include("../templates/header.php");
require_once("../includes/permissions.php");
if (! $session->isLoggedIn()) redirectTo($url."index.php");
if ($_SESSION['accountStatus'] == 2) redirectTo($url."client/index.php");
if ($_SESSION['accountStatus'] != 3) redirectTo($url."admin/index.php");
if (!has_permission('client_view')) {
	header("Location: ../admin/index.php");
	exit;
}
// Free edition: Companies is Pro
if (isset($_GET['company']) && function_exists('tasksession_serve_pro_upgrade_page')) {
	tasksession_serve_pro_upgrade_page('companies');
}
ensure_user_permissions($connect);
$companyCardCanMutate = has_permission('client_profile_update') || has_permission('client_create');
$companyCardCanDelete = has_permission('client_delete');
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
$settingsForModules = settings::findById(1);
$settings = $settingsForModules;
$invoiceModuleEnabled = !empty($settingsForModules->module_invoices);

$toast_flash = comon_list_page_toast_from_query($lang, 'client');
if(isset($_POST['del_user']))
{
	if (!has_permission('client_delete')) {
		header('Location:clients?message=fail');
		exit;
	}
	 $delUserId=$_POST['del_id'];
	if($delUserId!=$session->userId)
	{
	  $deleteUser="delete from users where id=$delUserId limit 1";
	  $userDeleted=mysqli_query($connect, $deleteUser);
	  if($userDeleted){
		 header("Location:clients?message=success");
	   } else{
header("Location:clients?message=fail");
	   }
	  }
	  else
	  {
header("Location:clients?message=fail");
	  }
	}

if(isset($_POST['bulk_del_user']))
{
	if (!has_permission('client_delete')) {
		header('Location:clients?message=fail');
		exit;
	}
	$client_ids=$_POST['bulk_del_uid'];
	$bulk_del_val=$_POST['bulk_del_val'];
$flag=0;
		if($flag==0)
		{
			$cli_arr = explode(',', $client_ids);
			foreach($cli_arr as $cli_id){
			$user = user::findById($cli_id); 
			// $user->id = $cli_id;
			$user->status=$bulk_del_val;
			$saveUser=$user->save();
			if ((int) $bulk_del_val === 1) {
				User::revokeRememberMeTokens((int) $cli_id);
			}
			}
			if($saveUser){
				header("Location:clients?message=success");
			}else {
header("Location:clients?message=fail");
			}
		}
}
require_once __DIR__ . '/../includes/user_search_helper.php';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchCondition = comon_user_list_search_sql($connect, $searchQuery, [
    'custom_field_entity_types' => ['client'],
    'include_company_membership' => false,
]);
$viewType = (isset($_GET['view']) && $_GET['view'] === 'table') ? 'table' : 'grid';
$isCompanyView = isset($_GET['company']);

$tabParams = [];
if ($searchQuery !== '') {
    $tabParams['search'] = $searchQuery;
}
if (!$isCompanyView && $viewType === 'table') {
    $tabParams['view'] = 'table';
}
$clientsTabQuery = $tabParams ? '?' . http_build_query($tabParams) : '';
$tabParamsCompany = $tabParams;
$tabParamsCompany['company'] = '';
$companyTabQuery = tasksession_build_list_query($tabParamsCompany);

$clearSearchParams = [];
if (!$isCompanyView && $viewType === 'table') {
    $clearSearchParams['view'] = 'table';
}
$clientsClearSearchQuery = $clearSearchParams ? '?' . http_build_query($clearSearchParams) : '';
$clearSearchParamsCompany = $clearSearchParams;
$clearSearchParamsCompany['company'] = '';
$companyClearSearchQuery = tasksession_build_list_query($clearSearchParamsCompany);

// Pagination (12 per page)
$limit = 12;
$page = isset($_GET["page"]) ? max(1, (int)$_GET["page"]) : 1;
$start_from = ($page - 1) * $limit;

$companiesTableReady = false;
$companyPinsEnabled = false;
$allCompanyRows = [];
if ($connect) {
	$ccChk = @mysqli_query($connect, "SHOW TABLES LIKE 'client_companies'");
	if ($ccChk && mysqli_num_rows($ccChk) > 0) {
		$companiesTableReady = true;
	}
	if ($companiesTableReady) {
		$pinChk = @mysqli_query($connect, "SHOW TABLES LIKE 'client_company_pins'");
		$companyPinsEnabled = $pinChk && mysqli_num_rows($pinChk) > 0;
	}
}
if ($companiesTableReady && $connect && $isCompanyView) {
	$coSearchSql = ' WHERE c.deleted_at IS NULL ';
	if ($searchQuery !== '') {
		$coSearchSql .= comon_company_list_search_sql($connect, $searchQuery, 'c');
	}
	$pinJoin = $companyPinsEnabled ? ' LEFT JOIN client_company_pins p ON p.company_id = c.id ' : '';
	$pinSelect = $companyPinsEnabled ? ', IF(p.company_id IS NOT NULL, 1, 0) AS is_pinned ' : ', 0 AS is_pinned ';
	$pinOrder = $companyPinsEnabled ? ' (p.company_id IS NOT NULL) DESC, p.pinned_at ASC, c.name ASC ' : ' c.name ASC ';
	$cq = mysqli_query(
		$connect,
		"SELECT c.id, c.name, c.logo_path, c.phone, c.currency, c.created_at, c.created_by,
		TRIM(COALESCE(u.firstName, '')) AS creator_name,
		(SELECT COUNT(*) FROM client_company_members m WHERE m.company_id = c.id) AS member_count
		$pinSelect
		FROM client_companies c
		LEFT JOIN users u ON u.id = c.created_by
		$pinJoin
		$coSearchSql
		ORDER BY $pinOrder"
	);
	if ($cq) {
		while ($row = mysqli_fetch_assoc($cq)) {
			$allCompanyRows[] = $row;
		}
	}
}

$total_company_records = count($allCompanyRows);
$total_company_pages = max(1, (int) ceil($total_company_records / $limit));
$pagedCompanies = ($isCompanyView && $companiesTableReady) ? array_slice($allCompanyRows, $start_from, $limit) : [];

// Total records (for pagination) — clients list
$countRes = mysqli_query($connect, "SELECT COUNT(*) AS total FROM users WHERE accountStatus = 2 AND status = 0 $searchCondition");
$countRow = $countRes ? mysqli_fetch_assoc($countRes) : null;
$total_records = $countRow && isset($countRow['total']) ? (int)$countRow['total'] : 0;
$total_pages = (int)ceil($total_records / $limit);
$start_from_b = ($total_records > 0) ? ($start_from + 1) : 0;

$display_total_records = $isCompanyView ? $total_company_records : $total_records;
$display_total_pages = $isCompanyView ? $total_company_pages : $total_pages;
$pagedUsersCount = 0;
if (!$isCompanyView) {
	$recentlyRegisteredUsers = user::findBySql("SELECT * FROM users WHERE accountStatus = 2 AND status = 0 $searchCondition ORDER BY id DESC LIMIT $start_from, $limit");
	$pagedUsersCount = is_array($recentlyRegisteredUsers) ? count($recentlyRegisteredUsers) : 0;
} else {
	$recentlyRegisteredUsers = [];
}
$display_page_count = $isCompanyView ? count($pagedCompanies) : $pagedUsersCount;
$end_val_display = ($display_total_records > 0) ? min($start_from + $display_page_count, $display_total_records) : 0;

$trashcountUsers = user::findBySql("SELECT * FROM users WHERE accountStatus = 2 AND status = 1");

$companyPickableClients = [];
$pickClients = user::findBySql("SELECT * FROM users WHERE accountStatus = 2 AND status = 0 ORDER BY firstName ASC");
$pickClientIds = array_map(static function ($pc) {
	return (int) $pc->id;
}, is_array($pickClients) ? $pickClients : []);
$pickerSearchExtras = comon_prefetch_client_picker_search_extras($connect, $pickClientIds);
foreach ($pickClients as $pc) {
	$fn = trim(($pc->firstName ?? '') . ' ' . ($pc->lastName ?? ($pc->last_name ?? '')));
	$email = (string) ($pc->email ?? '');
	$companyPickableClients[] = [
		'id' => (int) $pc->id,
		'name' => htmlspecialchars($fn !== '' ? $fn : ('Client #' . (int) $pc->id), ENT_QUOTES, 'UTF-8'),
		'email' => htmlspecialchars($email, ENT_QUOTES, 'UTF-8'),
		'image' => getUserAvatarHtml($pc->id, $pc->firstName ?? '', $pc->lastName ?? ($pc->last_name ?? ''), 32, 32, 'rounded-circle', $pc->firstName ?? ''),
		'search' => comon_build_client_picker_search_text($pc, $pickerSearchExtras),
	];
}
$tsMenuIco = 'tasksession-timer-log-menu-ico me-2';
require_once __DIR__ . '/../includes/list-bulk-helpers.php';
$canDeleteClients = has_permission('client_delete');
$showClientsBulkToolbar = !$isCompanyView && $canDeleteClients;
$clientsSearchPlaceholder = $isCompanyView
	? ($lang['Search companies'] ?? 'Search companies')
	: ($lang['Search Clients'] ?? 'Search clients');
$clientsSearchClearHref = 'clients' . ($isCompanyView ? $companyClearSearchQuery : $clientsClearSearchQuery);
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
														$allClients = user::findBySql("SELECT * FROM users WHERE accountStatus = 2 AND status = 0");
														$trashClients = user::findBySql("SELECT * FROM users WHERE accountStatus = 2 AND status = 1");
														$viewToggleParams = $tabParams;
														unset($viewToggleParams['view']);
														if ($isCompanyView) {
															$viewToggleParams['company'] = '';
														}
														$clientsGridViewHref = 'clients' . tasksession_build_list_query($viewToggleParams);
														$clientsTableViewParams = $viewToggleParams;
														$clientsTableViewParams['view'] = 'table';
														$clientsTableViewHref = 'clients' . tasksession_build_list_query($clientsTableViewParams);
														?>
													<div class="main-heading"><h1><?php echo $isCompanyView ? htmlspecialchars($lang['Company'] ?? 'Company') : htmlspecialchars($lang['Clients']); ?> <span>(<?php echo $isCompanyView ? (int) $total_company_records : ($searchQuery !== '' ? (int) $total_records : count($allClients)); ?>)</span></h1></div>
														<div class="icon-container sep">
															<div class="icon-container members-team-tabs">
																<a href="clients<?php echo htmlspecialchars($clientsTabQuery, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo !$isCompanyView ? 'active' : ''; ?>">
																	<?php echo ts_icon('clients'); ?><?php echo htmlspecialchars($lang['Clients'] ?? 'Clients'); ?>
																</a>
																<a href="clients<?php echo htmlspecialchars($companyTabQuery, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isCompanyView ? 'active' : ''; ?>">
																	<?php echo ts_icon('app-grid'); ?><?php echo htmlspecialchars($lang['Company'] ?? 'Company'); ?>
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
                                           	<form method="GET" action="clients" class="search-form" id="searchForm">
															<input type="hidden" name="page" value="1">
															<?php if ($viewType === 'table'): ?>
																<input type="hidden" name="view" value="table">
															<?php endif; ?>
															<?php if ($isCompanyView): ?>
																<input type="hidden" name="company" value="">
															<?php endif; ?>
															<div class="input-group">
																<span class="search-field-icon">
																	<?php echo ts_icon('search', 'w-2'); ?>
																</span>
																<input type="text" id="client-search" name="search" class="form-control" placeholder="<?php echo htmlspecialchars($clientsSearchPlaceholder, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($searchQuery ?? ''); ?>">
																<?php if ($searchQuery !== ''): ?>
																	<a href="<?php echo htmlspecialchars($clientsSearchClearHref, ENT_QUOTES, 'UTF-8'); ?>" class="cross" title="<?php echo htmlspecialchars($lang['Close'] ?? 'Close', ENT_QUOTES, 'UTF-8'); ?>">
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
											<?php if ($showClientsBulkToolbar): ?>
											<div class="edit-overview-btn ts-list-bulk-toolbar-wrap d-none d-md-block">
												<div class="icon-container sep ts-list-bulk-toolbar" data-ts-list-bulk-toolbar="clients">
													<div class="pm-trash task-trash align-middle d-flex col-gap-5">
														<a href="#" onclick="TsListBulk.enter('clients'); return false;" class="bulk-delete-tab red border-btn-a" id="tsListBulk_clients_bulkSelectTab" title="<?php echo htmlspecialchars($lang['Bulk Select'] ?? 'Bulk select', ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($lang['Bulk Select'] ?? 'Bulk select', ENT_QUOTES, 'UTF-8'); ?>">
															<?php echo ts_icon('duplicate', 'w-2'); ?>
														</a>
														<div class="media-view-toggle" id="clientsViewToggle" role="group" aria-label="View mode">
															<a href="<?php echo htmlspecialchars($clientsGridViewHref, ENT_QUOTES, 'UTF-8'); ?>" class="media-view-toggle__btn border-btn-a<?php echo $viewType !== 'table' ? ' is-active' : ''; ?>" title="<?php echo htmlspecialchars($lang['Grid View'] ?? 'Grid view', ENT_QUOTES, 'UTF-8'); ?>">
																<?php echo ts_icon('view-grid', 'w-2'); ?>
															</a>
															<a href="<?php echo htmlspecialchars($clientsTableViewHref, ENT_QUOTES, 'UTF-8'); ?>" class="media-view-toggle__btn border-btn-a<?php echo $viewType === 'table' ? ' is-active' : ''; ?>" title="<?php echo htmlspecialchars($lang['Table View'] ?? 'Table view', ENT_QUOTES, 'UTF-8'); ?>">
																<?php echo ts_icon('table', 'w-2'); ?>
															</a>
														</div>
														<a href="#" onclick="TsListBulk.exit('clients'); return false;" class="bulk-delete-tab border-btn-a" id="tsListBulk_clients_bulkBackTab" style="display:none;">
															<?php echo ts_icon('arrow-left', 'w-2'); ?>
															<span><?php echo $lang['Back'] ?? 'Back'; ?></span>
														</a>
														<a href="#" onclick="TsListBulk.selectAll('clients'); return false;" class="bulk-delete-tab border-btn-a" id="tsListBulk_clients_bulkSelectAllTab" style="display:none;">
															<?php echo ts_icon('check-circle', 'w-2'); ?>
															<span id="tsListBulk_clients_bulkSelectAllLabel"><?php echo $lang['Select all'] ?? 'Select all'; ?></span>
														</a>
														<a href="#" onclick="TsListBulk.deleteSelected('clients'); return false;" class="bulk-delete-tab border-btn-a" id="tsListBulk_clients_bulkDeleteTab" style="display:none;">
															<?php echo ts_icon('delete', 'w-2'); ?>
															<span id="tsListBulk_clients_bulkDeleteLabel"><?php echo $lang['Delete'] ?? 'Delete'; ?></span>
														</a>
													</div>
												</div>
											</div>
											<?php else: ?>
											<div class="edit-overview-btn ts-list-bulk-toolbar-wrap d-none d-md-block">
												<div class="icon-container sep">
													<div class="pm-trash task-trash align-middle d-flex col-gap-5">
														<div class="media-view-toggle" id="clientsViewToggle" role="group" aria-label="View mode">
															<a href="<?php echo htmlspecialchars($clientsGridViewHref, ENT_QUOTES, 'UTF-8'); ?>" class="media-view-toggle__btn border-btn-a<?php echo $viewType !== 'table' ? ' is-active' : ''; ?>" title="<?php echo htmlspecialchars($lang['Grid View'] ?? 'Grid view', ENT_QUOTES, 'UTF-8'); ?>">
																<?php echo ts_icon('view-grid', 'w-2'); ?>
															</a>
															<a href="<?php echo htmlspecialchars($clientsTableViewHref, ENT_QUOTES, 'UTF-8'); ?>" class="media-view-toggle__btn border-btn-a<?php echo $viewType === 'table' ? ' is-active' : ''; ?>" title="<?php echo htmlspecialchars($lang['Table View'] ?? 'Table view', ENT_QUOTES, 'UTF-8'); ?>">
																<?php echo ts_icon('table', 'w-2'); ?>
															</a>
														</div>
													</div>
												</div>
											</div>
											<?php endif; ?>
											<div class="edit-overview-btn d-block d-md-none">
													 <td class="extra-height">
															<div class="action-toggle border-btn-a" data-bs-toggle="collapse" data-bs-target="#client-menu" role="button" tabindex="0">
																<span class="action-text"><?php echo $lang['Filters']; ?></span><span class="mobile-ellipsis"><?php echo ts_icon('ellipsis', 'w-2'); ?></span>
																<?php echo ts_icon('filter', 'w-2'); ?>
															</div>
													 <div id="client-menu" class="toggle-action collapse shadow-dept">
																<ul>
																<?php if (has_permission('client_create')): ?>
																<li>
																		<a href="add-client">
																			<?php echo ts_icon('user-plus', $tsMenuIco); ?>
																			<span><?php echo htmlspecialchars($lang['Add new client'] ?? 'Add new client'); ?></span>
																		</a>
																	</li>
																	<?php if ($companiesTableReady): ?>
																<li>
																		<a href="#" class="js-open-client-company-create">
																			<?php echo ts_icon('user-group', $tsMenuIco); ?>
																			<span><?php echo htmlspecialchars($lang['Add new company'] ?? 'Add new company'); ?></span>
																		</a>
																	</li>
																	<?php endif; ?>
																	<?php endif; ?>
																</ul>
															</div>
														</td>
													</div>
												<?php if (has_permission('client_create')): ?>
												<div class="d-none d-md-block">
													<div id="primary-btn" class="action-toggle primary-btn" data-bs-toggle="collapse" data-bs-target="#dropdown-menu" role="button" tabindex="0">
														<span class="action-text"><?php echo htmlspecialchars($lang['Add new'] ?? 'Add new'); ?></span>
														<?php echo ts_icon('plus', 'w-2'); ?>
													</div>
													<div id="dropdown-menu" class="toggle-action collapse shadow-dept">
														<ul>
															<li>
																<a href="add-client">
																	<?php echo ts_icon('user-plus', $tsMenuIco); ?>
																	<span><?php echo htmlspecialchars($lang['Add new client'] ?? 'Add new client'); ?> </span>
																</a>
															</li>
															<?php if ($companiesTableReady): ?>
															<li>
																<a href="#" class="js-open-client-company-create">
																	<?php echo ts_icon('user-group', $tsMenuIco); ?>
																	<span><?php echo htmlspecialchars($lang['Add new company'] ?? 'Add new company'); ?> </span>
																</a>
															</li>
															<?php endif; ?>
														</ul>
													</div>
												</div>
												<?php endif; ?>
										</div>
                                      </div>
									</div>
										<?php if (!$isCompanyView && $viewType === 'table'): ?>
											<div class="clearfix"></div>
											<div data-ts-list-bulk-root="clients">
											<div class="table-responsive scroll-x vh-100">
												<table class="table table-new table-invoice h-100">
													<thead>
														<tr>
															<?php if ($canDeleteClients) { echo tasksession_list_bulk_checkbox_th_html(); } ?>
															<th style="min-width: 270px; text-align: left;"><?php echo $lang['Client']; ?></th>
															<th style="text-align: left;"><?php echo $lang['Email']; ?></th>
															<th><?php echo $lang['Projects']; ?></th>
															<th><?php echo $lang['Task']; ?></th>
                                                            <?php if ($invoiceModuleEnabled): ?>
															<th><?php echo $lang['Invoice Activity'] ?? 'Invoice activity'; ?></th>
                                                            <?php endif; ?>
															<th><?php echo $lang['Last login'] ?? 'Last login'; ?></th>
															<th class="min-width-200"><?php echo $lang['Action']; ?></th>
														</tr>
													</thead>
													<tbody id="projects-tbl">
														<?php
														$clientsTableLinkBase = '';
														$clientsTableShowBulkCheckbox = $canDeleteClients;
														$clientsTableShowSerial = false;
														$clientsTableBulkCheckboxDisabled = false;
														$clientsTableCtx = ['settings' => $settingsForModules ?? null, 'lang' => $lang];
														include dirname(__DIR__) . '/partials/clients_table_rows.php';
														?>
													</tbody>
												</table>
											</div>
											</div>
										<?php elseif ($isCompanyView && $viewType === 'table'): ?>
											<div class="vh-100 ">
												<div class="table-responsive scroll-x vh-100">
													<table class="table table-new projectspage client-companies-table" style="text-align: left;">
														<thead>
															<tr>
																<th width="26%"><?php echo htmlspecialchars($lang['Company'] ?? 'Company'); ?></th>
																<th><?php echo htmlspecialchars($lang['Clients'] ?? 'Clients'); ?></th>
																<th><?php echo htmlspecialchars($lang['Phone'] ?? 'Phone'); ?></th>
																<th><?php echo htmlspecialchars($lang['Currency'] ?? 'Currency'); ?></th>
																<th><?php echo htmlspecialchars($lang['Created by'] ?? 'Created by'); ?></th>
																<th><?php echo htmlspecialchars($lang['Created'] ?? 'Created'); ?></th>
																<th><?php echo htmlspecialchars($lang['Options'] ?? ($lang['Action'] ?? 'Options')); ?></th>
															</tr>
														</thead>
														<tbody id="projects-tbl">
														<?php
														if (!$companiesTableReady) {
															echo '<tr><td colspan="7">' . htmlspecialchars($lang['No companies yet'] ?? 'No companies yet.') . '</td></tr>';
														} elseif (empty($pagedCompanies)) {
															echo '<tr><td colspan="7">' . htmlspecialchars($lang['No companies yet'] ?? 'No companies yet.') . '</td></tr>';
														} else {
															foreach ($pagedCompanies as $idx => $crow) {
																$cid = (int) $crow['id'];
																$cname = (string) $crow['name'];
																$mc = (int) $crow['member_count'];
																$phone = trim((string) ($crow['phone'] ?? ''));
																$cur = (string) $crow['currency'];
																$creatorName = trim((string) ($crow['creator_name'] ?? ''));
																$createdRaw = isset($crow['created_at']) ? (string) $crow['created_at'] : '';
																$createdDisp = $createdRaw !== '' && strtotime($createdRaw) ? date('M j, Y', strtotime($createdRaw)) : '—';
																$memberIds = [];
																if ($connect) {
																	$uq = mysqli_query($connect, 'SELECT user_id FROM client_company_members WHERE company_id=' . $cid . ' ORDER BY user_id ASC LIMIT 6');
																	if ($uq) {
																		while ($ur = mysqli_fetch_assoc($uq)) {
																			$memberIds[] = (int) $ur['user_id'];
																		}
																	}
																}
																?>
																<tr>
																	<td style="text-align: left;">
																		<div class="d-flex align-items-center col-gap-10">
																			<?php
																			$logoRelTbl = isset($crow['logo_path']) ? trim((string) $crow['logo_path']) : '';
																			if ($logoRelTbl !== '') {
																				$logoUrlTbl = rtrim($url, '/') . '/' . ltrim($logoRelTbl, '/');
																				echo '<img src="' . htmlspecialchars($logoUrlTbl, ENT_QUOTES, 'UTF-8') . '" alt="" class="rounded-circle" style="width:36px;height:36px;object-fit:cover;">';
																			}
																			?>
																			<span><?php echo htmlspecialchars($cname); ?></span>
																		</div>
																	</td>
																	<td class="clients-rpt" style="text-align:left;">
																		<div class="d-flex align-items-center">
																			<?php
																			if (!empty($memberIds)) {
																				$clientCounter = 0;
																				foreach ($memberIds as $mid) {
																					$uobj = user::findById($mid);
																					if (!$uobj) {
																						continue;
																					}
																					$clientCounter++;
																					if ($clientCounter > 3) {
																						continue;
																					}
																					$tip = trim((string) ($uobj->firstName ?? ''));
																					$tip = $tip !== '' ? $tip : ((string) ($uobj->email ?? ('Client #' . (int) $mid)));
																					echo '<div class="user-box">';
																					echo '<button type="button" class="border-0 p-0 bg-transparent" data-bs-toggle="tooltip" data-bs-placement="top" title="' . htmlspecialchars($tip, ENT_QUOTES, 'UTF-8') . '">';
																					echo getUserAvatarHtml($mid, $uobj->firstName ?? '', $uobj->lastName ?? '', 36, 36, 'img-fluid rounded-circle', $uobj->firstName ?? '');
																					echo '</button>';
																					echo '</div>';
																				}
																				if ($mc > 3) {
																					echo '<div class="plus-more">+' . (int) ($mc - 3) . '</div>';
																				}
																			} else {
																				echo '<span class="text-muted">—</span>';
																			}
																			?>
																		</div>
																	</td>
																	<td><?php echo htmlspecialchars($phone !== '' ? $phone : '—'); ?></td>
																	<td><?php echo htmlspecialchars($cur); ?></td>
																	<td><?php echo htmlspecialchars($creatorName !== '' ? $creatorName : '—'); ?></td>
																	<td><?php echo htmlspecialchars($createdDisp); ?></td>
																	<td class="extra-height">
																		<div class="action-toggle" data-bs-toggle="collapse" data-bs-target="#actionDropdownCompany<?php echo $cid; ?>">
																			<?php echo htmlspecialchars($lang['Action'] ?? 'Action'); ?><?php echo ts_icon('chevron-down'); ?>
																		</div>
																		<div id="actionDropdownCompany<?php echo $cid; ?>" class="toggle-action collapse shadow-dept">
																			<ul>
																				<li>
																					<button type="button" class="dropdown-item js-view-client-company" data-company-id="<?php echo $cid; ?>">
																						<?php echo ts_icon('eye', 'tasksession-timer-log-menu-ico me-2'); ?>
																						<?php echo htmlspecialchars($lang['View company'] ?? 'View company'); ?>
																					</button>
																				</li>
																				<?php if ($companyCardCanMutate): ?>
																				<li>
																					<button type="button" class="dropdown-item js-edit-client-company" data-company-id="<?php echo $cid; ?>">
																						<?php echo ts_icon('edit', 'tasksession-timer-log-menu-ico me-2'); ?>
																						<?php echo htmlspecialchars($lang['Edit company'] ?? 'Edit company'); ?>
																					</button>
																				</li>
																				<?php
																				$ccIsPinned = !empty($crow['is_pinned']);
																				include __DIR__ . '/../includes/partials/client_company_pin_dropdown_item.php';
																				?>
																				<li>
																					<button type="button" class="dropdown-item js-clone-client-company" data-company-id="<?php echo $cid; ?>">
																						<?php echo ts_icon('duplicate', 'tasksession-timer-log-menu-ico me-2'); ?>
																						<?php echo htmlspecialchars($lang['Clone company'] ?? 'Clone company'); ?>
																					</button>
																				</li>
																				<?php endif; ?>
																				<?php if ($companyCardCanDelete): ?>
																				<li>
																					<button type="button" class="dropdown-item js-delete-client-company text-danger" data-company-id="<?php echo $cid; ?>" data-company-name="<?php echo htmlspecialchars($cname, ENT_QUOTES, 'UTF-8'); ?>">
																						<?php echo ts_icon('delete', 'tasksession-timer-log-menu-ico me-2'); ?>
																						<?php echo htmlspecialchars($lang['Delete company'] ?? 'Delete company'); ?>
																					</button>
																				</li>
																				<?php endif; ?>
																			</ul>
																		</div>
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
										<?php elseif ($isCompanyView): ?>
											<div class="row clients-row">
												<?php
												if (!$companiesTableReady) {
													echo '<div class="col-12"><div class="empty-box">' . htmlspecialchars($lang['No companies yet'] ?? 'No companies yet.') . '</div></div>';
												} elseif (empty($pagedCompanies)) {
													echo '<div class="col-12"><div class="empty-box">' . htmlspecialchars($lang['No companies yet'] ?? 'No companies yet.') . '</div></div>';
												} else {
													foreach ($pagedCompanies as $crow) {
														$cid = (int) $crow['id'];
														$cname = (string) $crow['name'];
														$mc = (int) $crow['member_count'];
														$logoRel = isset($crow['logo_path']) ? trim((string) $crow['logo_path']) : '';
														$uids = [];
														if ($connect) {
															$uq = mysqli_query($connect, 'SELECT user_id FROM client_company_members WHERE company_id=' . $cid . ' ORDER BY user_id ASC LIMIT 12');
															if ($uq) {
																while ($ur = mysqli_fetch_assoc($uq)) {
																	$uids[] = (int) $ur['user_id'];
																}
															}
														}
														$showAv = array_slice($uids, 0, 5);
														$extra = max(0, $mc - count($showAv));
														$ccCreatedRaw = isset($crow['created_at']) ? (string) $crow['created_at'] : '';
														$ccCreatedDisp = ($ccCreatedRaw !== '' && strtotime($ccCreatedRaw)) ? date('M j, Y', strtotime($ccCreatedRaw)) : '—';
														$ccCreatorName = trim((string) ($crow['creator_name'] ?? ''));
														$ccIsPinned = !empty($crow['is_pinned']);
														include __DIR__ . '/../includes/partials/client_company_grid_card.php';
													}
												}
												?>
											</div>
										<?php else: ?>
											<div class="row clients-row" data-ts-list-bulk-root="clients">
												<?php  
												   $urlB = $url;
												   if($recentlyRegisteredUsers == NULL){
													   echo '<div class="empty-box">No Clients available!</div>';
												   }
														 foreach($recentlyRegisteredUsers as $recentlyRegisteredUser){ 
														  
															$profilePictureObj=profilePicture::findByfkUserId($recentlyRegisteredUser->id);
											
																	// Default avatar data for display
																	$avatarData = ['type' => 'initials', 'url' => ''];
																	if($profilePictureObj)
																	{
																		foreach($profilePictureObj as $displayPicture)
																		{
																		 $filenamePic = $displayPicture->filename;
																		 $filenamePicture = getProfilePicUrl($filenamePic, 130, 130);
																		}
																		if (!empty($filenamePicture)) {
																			$avatarData = ['type' => 'image', 'url' => $filenamePicture];
																		}
																	 }
																	 else
																	 {
																		 // Use global avatar function for initials
																		 $avatarData = getUserAvatarData($recentlyRegisteredUser->id, $recentlyRegisteredUser->firstName, $recentlyRegisteredUser->lastName ?? '', 130, 130);
																		 $filenamePicture = $avatarData['type'] === 'image' ? $avatarData['url'] : '';
																	 } 
																	 $cli_name = $recentlyRegisteredUser->firstName;
													  
													  // Online indicator logic: show only if session_status is 'online' and last_seen is within 5 minutes
														  $isOnline = function_exists('user_presence_from_user')
														  ? user_presence_from_user($recentlyRegisteredUser)
														  : (
															  isset($recentlyRegisteredUser->last_seen) &&
															  isset($recentlyRegisteredUser->session_status) &&
															  $recentlyRegisteredUser->session_status === 'online' &&
															  (time() - (int)$recentlyRegisteredUser->last_seen) < 300
														  );
													  // Project and task counts using direct SQL
													  $clientId = $recentlyRegisteredUser->id;
													  $projectCountResult = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM projects WHERE c_id = $clientId");
													  $projectCountRow = mysqli_fetch_assoc($projectCountResult);
													  $projectCount = $projectCountRow ? $projectCountRow['cnt'] : 0;
													  // Get all project IDs for this client
													  $projectIdsResult = mysqli_query($connect, "SELECT p_id FROM projects WHERE c_id = $clientId");
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
													  
													  // Get invoice count for this client from milestones table
													  // Project invoices: only show for main client (not additional clients)
													  // Direct client invoices: show if m.c_id matches
													  $invoiceCountQuery = "SELECT COUNT(*) as cnt FROM milestones m 
													                      LEFT JOIN projects p ON m.p_id = p.p_id 
													                      WHERE ((m.p_id IS NOT NULL AND m.p_id > 0 AND p.p_id IS NOT NULL AND (p.c_id = $clientId OR p.main_client_id = $clientId)) 
													                      OR ((m.p_id IS NULL OR m.p_id = 0) AND m.c_id = $clientId))";
													  $invoiceCountResult = mysqli_query($connect, $invoiceCountQuery);
													  $invoiceCountRow = mysqli_fetch_assoc($invoiceCountResult);
													  $invoiceCount = $invoiceCountRow ? $invoiceCountRow['cnt'] : 0;
													  
													  ?>
												<div class="col-md-4 col-lg-4 col-xl-3 mb-2 staff user-imgbox img-circle">
													<div class="client-card">
														<?php if ($canDeleteClients): ?>
														<input type="checkbox" value="<?php echo $recentlyRegisteredUser->id;?>" name="client-checkbox" class="client-bulk-checkbox" style="position:absolute; top:12px; left:12px; z-index:3;">
														<?php endif; ?>
														
														<div class="d-flex col-gap-20 align-items-center flex-wrap">
														
														<div class="profile-img-wrapper" style="position:relative; display:inline-block;">
															<span class="online-dot<?php echo $isOnline ? ' online' : ' offline'; ?>"></span>
															<?php 
															if ($avatarData['type'] === 'image') {
																echo '<img src="' . $filenamePicture . '" class="img-fluid profile-img" />';
															} else {
																echo getUserAvatarHtml($recentlyRegisteredUser->id, $recentlyRegisteredUser->firstName, $recentlyRegisteredUser->lastName ?? '', 130, 130, 'img-fluid profile-img', $recentlyRegisteredUser->firstName);
															}
															?>
														</div>
														<div class="client-info">
															<div class="font-size-20"><?php echo $recentlyRegisteredUser->firstName;?></div>
															<div class="client-email mb-1"><?php echo $recentlyRegisteredUser->email;?></div>
																<?php
															$memberSinceText = '';
															if (isset($recentlyRegisteredUser->regDate) && !empty($recentlyRegisteredUser->regDate)) {
																$ts = strtotime((string)$recentlyRegisteredUser->regDate);
																if ($ts) {
																	$memberSinceText = date('F d, Y', $ts);
																}
															}
															if ($memberSinceText !== '') {
																echo '<div class="grey font-size-12">'.($lang['Member since'] ?? 'Member since').': '.$memberSinceText.'</div>';
															}
															?>
														</div>
														</div>
														<div class="client-stats d-flex ">
															<div>
															<div class="stat-value"><?php echo str_pad($projectCount, 2, '0', STR_PAD_LEFT); ?></div>
																<div class="stat-label grey"><?php echo $lang['Projects']; ?>
																</div>
															</div>
															<div class="border-lf-rt"<?php echo !$invoiceModuleEnabled ? ' style="border-right:0 !important;"' : ''; ?>>
															<div class="stat-value"><?php echo str_pad($taskCount, 2, '0', STR_PAD_LEFT); ?></div>
																<div class="stat-label grey"><?php echo $lang['Task']; ?>
																</div>
															</div>
                                                            <?php if ($invoiceModuleEnabled): ?>
															<div>
															<div class="stat-value"><?php echo str_pad($invoiceCount, 2, '0', STR_PAD_LEFT); ?></div>
																<div class="stat-label grey"><?php echo $lang['Invoice']; ?>
																</div>
															</div>
                                                            <?php endif; ?>
														</div>
														<a href="profile?user_id=<?php echo $recentlyRegisteredUser->id; ?>" class="stretch-btn">
															<?php echo $lang['View Profile']; ?><?php echo ts_icon('arrow-right-circle'); ?>
														</a>
													</div>
												</div>
												<?php } ?>
												<div class="norecords" style="display: none;">
													<?php echo $lang['No records Found!']; ?>
												</div>
											</div>
										<?php endif; ?>

										<!-- Pagination -->
										<?php
										$listPaginationParams = array();
										if ($searchQuery !== '') {
											$listPaginationParams['search'] = $searchQuery;
										}
										if ($viewType === 'table') {
											$listPaginationParams['view'] = 'table';
										}
										if ($isCompanyView) {
											$listPaginationParams['company'] = '';
										}
										$clientsPaginationMeta = tasksession_list_pagination_meta(
											$display_total_records,
											$start_from,
											$display_page_count,
											$listPaginationParams
										);
										if ($display_total_records > $limit) {
											tasksession_render_list_pagination(
												$clientsPaginationMeta,
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
if ($companiesTableReady) {
	$companyPickableCompanies = [];
	if ($connect instanceof mysqli) {
		$companySelectCols = comon_companies_searchable_columns($connect);
		if ($companySelectCols === []) {
			$companySelectCols = ['name', 'vat_number', 'phone', 'email'];
		}
		$selectList = 'id, logo_path, ' . implode(', ', array_unique(array_merge(['name', 'vat_number', 'email'], $companySelectCols)));
		$pq = mysqli_query($connect, 'SELECT ' . $selectList . ' FROM client_companies WHERE deleted_at IS NULL ORDER BY name ASC');
		$pickCompanyRows = [];
		if ($pq) {
			while ($pr = mysqli_fetch_assoc($pq)) {
				$pickCompanyRows[] = $pr;
			}
			mysqli_free_result($pq);
		}
		$pickCompanyIds = array_map(static function ($pr) {
			return (int) ($pr['id'] ?? 0);
		}, $pickCompanyRows);
		$companyPickerExtras = comon_prefetch_company_picker_search_extras($connect, $pickCompanyIds);
		foreach ($pickCompanyRows as $pr) {
			$pid = (int) ($pr['id'] ?? 0);
			if ($pid <= 0) {
				continue;
			}
			$pname = (string) ($pr['name'] ?? '');
			$pvat = (string) ($pr['vat_number'] ?? '');
			$pemail = (string) ($pr['email'] ?? '');
			$companyPickableCompanies[] = [
				'id' => $pid,
				'name' => htmlspecialchars($pname, ENT_QUOTES, 'UTF-8'),
				'vat' => htmlspecialchars($pvat, ENT_QUOTES, 'UTF-8'),
				'email' => htmlspecialchars($pemail, ENT_QUOTES, 'UTF-8'),
				'search' => comon_build_company_picker_search_text($connect, $pr, $companyPickerExtras),
			];
		}
	}
	include dirname(__DIR__) . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'modals' . DIRECTORY_SEPARATOR . 'client-companies-modal.php';
	echo '<script>window.clientCompanyViewTarget = "page";</script>';
	$__upcf_js = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'user-profile-custom-fields-form.js';
	$__upcf_js_v = is_file($__upcf_js) ? (int) filemtime($__upcf_js) : 1;
	echo '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . 'assets/js/user-profile-custom-fields-form.js?v=' . $__upcf_js_v . '"></script>';
	$__cc_js = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'client-companies-modals.js';
	$__cc_js_v = is_file($__cc_js) ? (int) filemtime($__cc_js) : 1;
	echo '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . 'assets/js/client-companies-modals.js?v=' . $__cc_js_v . '"></script>';
}
?>
<script>window.__toastFlash=<?php echo json_encode($toast_flash, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
<script src="<?php echo htmlspecialchars(rtrim((string) $url, '/') . '/assets/js/toast.js', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php
if ($showClientsBulkToolbar) {
	echo tasksession_list_bulk_form_html(array(
		'form_id' => 'tsListBulkFormClients',
		'ids_field' => 'bulk_del_uid',
		'hidden_fields' => array(
			'bulk_del_val' => '1',
			'bulk_del_user' => '1',
		),
	));
	echo tasksession_list_bulk_script_tag(array(
		'instances' => array(
			'clients' => array(
				'rootSelector' => '[data-ts-list-bulk-root="clients"]',
				'formId' => 'tsListBulkFormClients',
				'idsField' => 'bulk_del_uid',
				'checkboxName' => 'client-checkbox',
				'deleteConfirm' => $lang['Delete selected clients?'] ?? 'Delete selected clients?',
				'deleteLabel' => $lang['Delete'] ?? 'Delete',
				'selectAllLabel' => $lang['Select all'] ?? 'Select all',
				'deselectAllLabel' => $lang['Deselect all'] ?? 'Deselect all',
			),
		),
	));
}
?>
<?php  include("../templates/main-footer.php"); ?>