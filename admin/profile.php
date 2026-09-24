<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : profile.php
   Purpose : Manages staff / clients profiles
 ================================================================================
 */
ob_start();
require_once("../includes/lib-initialize.php");
require_once("../includes/reports_common_helper.php");
require_once("../includes/stat_sparkline_helper.php");
require_once("../includes/company_profile_sales_helper.php");
$body_class = '';
if (isset($_GET['tab']) && (string) $_GET['tab'] === 'notes') {
    $body_class = 'profile-page--notes';
} elseif (isset($_GET['tab']) && (string) $_GET['tab'] === 'media') {
    $body_class = 'profile-page--media';
} elseif (isset($_GET['tab']) && (string) $_GET['tab'] === 'tasks') {
    $body_class = 'profile-page--tasks';
}
$title = "Profile | ". $syatem_title;
include("../templates/header.php");
?>
<?php
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
$SESSION['user_id'] = $_POST['user_id'];
}
//condition check for login

$user = User::findById((int)$id); //take the record of current user in an object array 	
$username=$user->firstName;;

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($user_id <= 0) {
	echo '<div class="alert alert-danger">Invalid user ID.</div>';
	include("../templates/main-footer.php");
	exit;
}
$settingsForModules = settings::findById(1);
require_once __DIR__ . '/../includes/sidebar_navigation.php';
$invoiceModuleEnabled = !empty($settingsForModules->module_invoices);
$fileManagementModuleEnabled = !empty($settingsForModules->module_file_management);
$projectsModuleEnabled = comon_sidebar_projects_enabled($settingsForModules);
$tasksModuleEnabled = comon_sidebar_tasks_enabled($settingsForModules);
$anyProjectsTasksModuleEnabled = $projectsModuleEnabled || $tasksModuleEnabled;
$isFreeEdition = function_exists('tasksession_is_free_edition') && tasksession_is_free_edition();
$showSalesStatsWidget = $invoiceModuleEnabled || $isFreeEdition;
$salesStatsLocked = $isFreeEdition || !$invoiceModuleEnabled;
$profileInvoiceLocked = $isFreeEdition;
$profileMediaLocked = $isFreeEdition;
$profileInvoiceTabVisible = $invoiceModuleEnabled || $isFreeEdition;
$profileMediaTabVisible = $fileManagementModuleEnabled || $isFreeEdition;
$tab = isset($_GET['tab']) ? (string) $_GET['tab'] : comon_profile_default_tab($settingsForModules);
$tab = comon_profile_normalize_tab($tab, $settingsForModules, $profileInvoiceTabVisible, $profileMediaTabVisible);
$dateFilter = isset($_GET['date_filter']) ? (string) $_GET['date_filter'] : 'all';
$hasActiveActivityFilter = ($tab === 'activity' && $dateFilter !== '' && $dateFilter !== 'all');
$profileActivityClearFiltersUrl = 'profile?user_id=' . (int) $user_id . '&tab=activity';
$profileTaskStatusFilter = isset($_GET['status']) ? (string) $_GET['status'] : '';
$profileTaskInternalFilter = isset($_GET['internal']) && $_GET['internal'] == '1';
$profileTaskStartDateFilter = isset($_GET['start_date']) ? trim((string) $_GET['start_date']) : '';
$profileTaskEndDateFilter = isset($_GET['end_date']) ? trim((string) $_GET['end_date']) : '';
$hasActiveProfileTaskFilters = ($tab === 'tasks') && (
    $profileTaskStatusFilter !== '' || $profileTaskInternalFilter
    || $profileTaskStartDateFilter !== '' || $profileTaskEndDateFilter !== ''
);
$profileTaskClearFiltersUrl = 'profile?user_id=' . (int) $user_id . '&tab=tasks';
if (isset($_GET['search']) && trim((string) $_GET['search']) !== '') {
    $profileTaskClearFiltersUrl .= '&search=' . rawurlencode(trim((string) $_GET['search']));
}
$profileNotesCsrfToken = function_exists('generate_csrf_token') ? generate_csrf_token() : '';

/** @var array{type:string,msg:string}|null assets/js/toast.js reads window.__toastFlash */
$toast_flash = null;
if ($tab === 'tasks' && !empty($message)) {
    $toastType = 'success';
    $msgText = (string) $message;
    if (stripos($msgText, 'Partially successful') !== false) {
        $toastType = 'info';
    } elseif (stripos($msgText, 'No tasks were deleted') !== false || stripos($msgText, 'failed') !== false) {
        $toastType = 'error';
    }
    $toast_flash = array('type' => $toastType, 'msg' => $msgText);
    $message = '';
}

// Handle bulk delete tasks (same logic as kanban.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete_action']) && $_POST['bulk_delete_action'] === 'delete') {
    if (isset($_POST['bulk_delete_tasks']) && is_array($_POST['bulk_delete_tasks'])) {
        $taskIds = array_map('intval', $_POST['bulk_delete_tasks']);
        $taskIds = array_filter($taskIds); // Remove any 0 or negative values
        
        if (!empty($taskIds)) {
            $successCount = 0;
            $failedTasks = [];
            
            foreach ($taskIds as $taskId) {
                // Verify task exists and user has permission to delete it
                $task = Task::findById($taskId);
                
                if (!$task) {
                    $failedTasks[] = "Task ID $taskId not found";
                    continue;
                }
                
                // Check if user has permission to delete this task
                // Admin can delete any task, but let's add some safety checks
                if ($task->user_id != $session->userId && $_SESSION['accountStatus'] != 1) {
                    $failedTasks[] = "No permission to delete task ID $taskId";
                    continue;
                }
                
                // Delete the task
                if ($task->delete()) {
                    $successCount++;
                } else {
                    $failedTasks[] = "Failed to delete task ID $taskId";
                }
            }
            
            // Set message for display
            if ($successCount > 0) {
                if ($successCount === count($taskIds)) {
                    $_SESSION['message'] = "Successfully deleted $successCount task(s).";
                } else {
                    $_SESSION['message'] = "Partially successful: $successCount deleted, " . count($failedTasks) . " failed.";
                }
            } else {
                $_SESSION['message'] = "No tasks were deleted.";
            }
            
            // Redirect to avoid form resubmission
            $redirectUrl = "profile?user_id=$user_id&tab=tasks";
            header("Location: " . $redirectUrl);
            exit;
        }
    }
}

// Get profile user data early for use in counter calculations
$profileUser = User::findById($user_id);

// Calculate user-specific project statistics for counter boxes
$user_projects = projects::findBySql("SELECT * FROM projects WHERE (c_id = $user_id OR main_client_id = $user_id OR FIND_IN_SET($user_id, c_ids) > 0 OR FIND_IN_SET($user_id, s_ids) > 0) AND archive = 0 AND trash != 1");
if (!is_array($user_projects)) { $user_projects = []; }

// Calculate user-specific milestone counts for counter boxes
// For staff and admin: count invoices they created, for clients: count invoices for their projects
if ((int)$profileUser->accountStatus == 3 || (int)$profileUser->accountStatus == 1) { // Staff or Admin
    $user_paid_milestones_query = "SELECT COUNT(*) as count FROM milestones m WHERE m.status = 1 AND m.created_by = $user_id";
    $user_unpaid_milestones_query = "SELECT COUNT(*) as count FROM milestones m WHERE m.status = 0 AND m.created_by = $user_id";
} else { // Client
    // Include both project invoices and direct client invoices
    // For project invoices: only show for main client (not additional clients)
    // For direct client invoices: check m.c_id matches user_id AND p_id is NULL/0
    $user_paid_milestones_query = "SELECT COUNT(*) as count FROM milestones m LEFT JOIN projects p ON m.p_id = p.p_id WHERE m.status = 1 AND ((m.p_id IS NOT NULL AND m.p_id > 0 AND p.p_id IS NOT NULL AND (p.c_id = $user_id OR p.main_client_id = $user_id)) OR ((m.p_id IS NULL OR m.p_id = 0) AND m.c_id = $user_id))";
    $user_unpaid_milestones_query = "SELECT COUNT(*) as count FROM milestones m LEFT JOIN projects p ON m.p_id = p.p_id WHERE m.status = 0 AND ((m.p_id IS NOT NULL AND m.p_id > 0 AND p.p_id IS NOT NULL AND (p.c_id = $user_id OR p.main_client_id = $user_id)) OR ((m.p_id IS NULL OR m.p_id = 0) AND m.c_id = $user_id))";
}

$user_paid_milestones_result = $database->query($user_paid_milestones_query);
$user_paid_milestones_count = $database->fetchArray($user_paid_milestones_result)['count'] ?? 0;

$user_unpaid_milestones_result = $database->query($user_unpaid_milestones_query);
$user_unpaid_milestones_count = $database->fetchArray($user_unpaid_milestones_result)['count'] ?? 0;

$sparkUid = (int)$user_id;
$sparkFrom = $database->escapeValue(date('Y-m-01', strtotime('-11 months')));
$sparkAccountStatus = $profileUser ? (int)$profileUser->accountStatus : 0;
$sparkProjects = statSparklineSeriesFromQuery(
    $database,
    "SELECT DATE_FORMAT(start_time, '%Y-%m') AS ym, COUNT(*) AS c
     FROM projects
     WHERE (c_id = {$sparkUid} OR main_client_id = {$sparkUid} OR FIND_IN_SET({$sparkUid}, c_ids) > 0 OR FIND_IN_SET({$sparkUid}, s_ids) > 0)
       AND archive = 0 AND trash != 1
       AND start_time >= '{$sparkFrom}'
     GROUP BY ym"
);
if ($sparkAccountStatus === 3 || $sparkAccountStatus === 1) {
    $sparkTasks = statSparklineSeriesFromQuery(
        $database,
        "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS c
         FROM tasks
         WHERE FIND_IN_SET({$sparkUid}, assigned_to) > 0
           AND created_at >= '{$sparkFrom}'
         GROUP BY ym"
    );
    $sparkInvoiceUserWhere = "m.created_by = {$sparkUid}";
} else {
    $sparkTasks = statSparklineSeriesFromQuery(
        $database,
        "SELECT DATE_FORMAT(t.created_at, '%Y-%m') AS ym, COUNT(*) AS c
         FROM tasks t
         INNER JOIN projects p ON t.project_id = p.p_id
         WHERE (p.c_id = {$sparkUid} OR p.main_client_id = {$sparkUid})
           AND t.created_at >= '{$sparkFrom}'
         GROUP BY ym"
    );
    $sparkInvoiceUserWhere = "((m.p_id IS NOT NULL AND m.p_id > 0 AND p.p_id IS NOT NULL AND (p.c_id = {$sparkUid} OR p.main_client_id = {$sparkUid})) OR ((m.p_id IS NULL OR m.p_id = 0) AND m.c_id = {$sparkUid}))";
}
$sparkUnpaid = statSparklineSeriesFromQuery(
    $database,
    "SELECT DATE_FORMAT(" . reports_sql_milestone_effective_date('m.') . ", '%Y-%m') AS ym, COUNT(*) AS c
     FROM milestones m
     LEFT JOIN projects p ON m.p_id = p.p_id
     WHERE m.status = 0
       AND {$sparkInvoiceUserWhere}
       AND " . reports_sql_milestone_effective_date('m.') . " IS NOT NULL
       AND " . reports_sql_milestone_effective_date('m.') . " >= '{$sparkFrom}'
     GROUP BY ym"
);
$sparkPaid = statSparklineSeriesFromQuery(
    $database,
    "SELECT DATE_FORMAT(m.releaseDate, '%Y-%m') AS ym, COUNT(*) AS c
     FROM milestones m
     LEFT JOIN projects p ON m.p_id = p.p_id
     WHERE m.status = 1
       AND {$sparkInvoiceUserWhere}
       AND m.releaseDate >= '{$sparkFrom}'
     GROUP BY ym"
);

// Display-only meta for spark cards (from existing spark series)
$projectsNewThisMonth = !empty($sparkProjects) ? (int) end($sparkProjects) : 0;
$projectsNewLastMonth = (count($sparkProjects) > 1) ? (int) $sparkProjects[count($sparkProjects) - 2] : 0;
$projectsPctChange = $projectsNewLastMonth > 0
    ? (int) round((($projectsNewThisMonth - $projectsNewLastMonth) / $projectsNewLastMonth) * 100)
    : ($projectsNewThisMonth > 0 ? 100 : 0);
$tasksNewThisMonth = !empty($sparkTasks) ? (int) end($sparkTasks) : 0;
$tasksNewLastMonth = (count($sparkTasks) > 1) ? (int) $sparkTasks[count($sparkTasks) - 2] : 0;
$invoiceOverviewTotal = (int) $user_paid_milestones_count + (int) $user_unpaid_milestones_count;
$paidSparkThis = !empty($sparkPaid) ? (int) end($sparkPaid) : 0;
$paidSparkLast = (count($sparkPaid) > 1) ? (int) $sparkPaid[count($sparkPaid) - 2] : 0;
$invoicePaidPctChange = $paidSparkLast > 0
    ? (int) round((($paidSparkThis - $paidSparkLast) / $paidSparkLast) * 100)
    : ($paidSparkThis > 0 ? 100 : 0);
$sparkSession = statSparklineSeriesFromQuery(
    $database,
    "SELECT DATE_FORMAT(attempt_time, '%Y-%m') AS ym, COUNT(*) AS c
     FROM login_attempts
     WHERE user_id = {$sparkUid} AND type = 'login' AND attempt_time >= '{$sparkFrom}'
     GROUP BY ym"
);

// Last session meta for overview card (used when invoice module is disabled).
$lastSessionDateDisplay = 'No data';
$lastSessionDurationDisplay = 'N/A';
$lastSessionIsOnline = false;
$lastLoginQuery = "SELECT attempt_time FROM login_attempts WHERE user_id = $user_id AND type = 'login' ORDER BY attempt_time DESC LIMIT 1";
$lastLoginResult = mysqli_query($connect, $lastLoginQuery);
if ($lastLoginResult) {
    $lastLogin = mysqli_fetch_assoc($lastLoginResult);
    if ($lastLogin && !empty($lastLogin['attempt_time'])) {
        $lastLoginTime = $lastLogin['attempt_time'];
        $lastSessionDateDisplay = date('M j, Y', strtotime($lastLoginTime));

        $loginTime = strtotime($lastLoginTime);
        $logoutTs = null;
        $logoutStmt = $connect->prepare("SELECT attempt_time FROM login_attempts WHERE user_id = ? AND type = 'logout' AND attempt_time > ? ORDER BY attempt_time ASC LIMIT 1");
        if ($logoutStmt) {
            $logoutStmt->bind_param('is', $user_id, $lastLoginTime);
            $logoutStmt->execute();
            $logoutRes = $logoutStmt->get_result();
            if ($logoutRes && ($logoutRow = $logoutRes->fetch_assoc())) {
                $logoutTs = strtotime($logoutRow['attempt_time']);
            }
            $logoutStmt->close();
        }
        $seenUser = (isset($profileUser) && is_object($profileUser)) ? $profileUser : User::findById((int) $user_id);
        $lastSessionDurationDisplay = function_exists('user_presence_activity_duration_label')
            ? user_presence_activity_duration_label(
                $loginTime,
                $logoutTs,
                $seenUser->last_seen ?? 0,
                $seenUser->session_status ?? 'offline'
            )
            : 'N/A';
        $lastSessionIsOnline = ($lastSessionDurationDisplay === 'Active');
    }
}

/**
 * Calculate invoice total including tax and discount
 */
function calculateInvoiceTotal($milestone) {
    // Calculate subtotal from invoice items or fallback to budget
    require_once(__DIR__ . '/../includes/invoice_item.php');
    $subtotal = 0;
    $invoiceItems = InvoiceItem::findByMilestoneId($milestone['id']);
    if ($invoiceItems && count($invoiceItems) > 0) {
        foreach ($invoiceItems as $item) {
            $subtotal += floatval($item->rate) * floatval($item->quantity);
        }
    } else {
        $subtotal = floatval($milestone['budget'] ?? 0);
    }
    
    // Calculate discount
    $discount = isset($milestone['discount']) ? floatval($milestone['discount']) : 0;
    $discount_type = isset($milestone['discount_type']) ? $milestone['discount_type'] : 'percentage';
    
    $discount_amount = 0;
    if ($discount > 0 && $subtotal > 0) {
        if ($discount_type === 'percentage') {
            $discount_amount = ($subtotal * $discount) / 100;
        } else {
            $discount_amount = $discount;
        }
    }
    
    // Calculate amount after discount
    $amount_after_discount = $subtotal - $discount_amount;
    
    // Calculate tax on amount after discount
    $sales_tax = isset($milestone['sales_tax']) ? floatval($milestone['sales_tax']) : 0;
    $sales_tax_type = isset($milestone['sales_tax_type']) ? $milestone['sales_tax_type'] : 'percentage';
    
    $tax_amount = 0;
    if ($sales_tax > 0 && $amount_after_discount > 0) {
        if ($sales_tax_type === 'percentage') {
            $tax_amount = ($amount_after_discount * $sales_tax) / 100;
        } else {
            $tax_amount = $sales_tax;
        }
    }
    
    // Total = subtotal - discount + tax
    $total = $amount_after_discount + $tax_amount;
    
    return $total;
}

/**
 * Get currency symbol for display
 */
function getCurrencySymbol($currencyString) {
    if (empty($currencyString)) {
        return '$';
    }
    
    // If it's already just a symbol, return it
    if (strlen($currencyString) <= 3) {
        return $currencyString;
    }
    
    // Extract symbol from "CODE,SYMBOL" format
    $parts = explode(',', $currencyString);
    if (count($parts) > 1) {
                $symbol = trim($parts[1]);
        // Remove any colon or space from the symbol
        $symbol = str_replace([':', ' '], '', $symbol);
                return $symbol;
    }
    
    return '$';
}

/**
 * Get currency country name for display
 */
function getCurrencyCountryName($currencyCode) {
    if (empty($currencyCode)) {
        return 'United States ($)'; // Default
    }
    
    // Clean the currency code
    $cleanCurrencyCode = explode(',', $currencyCode)[0];
    
    // Currency to country mapping
    $currencyCountries = [
        'PKR' => 'Pakistan (Rs)',
        'Rs' => 'Pakistan (Rs)', // Handle Rs,Rs format
        'USD' => 'United States ($)',
        'CAD' => 'Canada (C$)',
        'EUR' => 'European Union (€)',
        'GBP' => 'United Kingdom (£)',
        'INR' => 'India (₹)',
        'JPY' => 'Japan (¥)',
        'AUD' => 'Australia (A$)',
        'CHF' => 'Switzerland (CHF)',
        'CNY' => 'China (¥)',
    ];
    
    $countryName = $currencyCountries[$cleanCurrencyCode] ?? 'United States ($)';
    
    // Ensure we don't return duplicates
    return $countryName;
}

?>
<!-- Invoice modal loaded via client-invoice-view-modal-bundle.php -->
		<!-- Add Milestone Modal -->
	<div id="add-milestone" class="modal fade" tabindex="-1" aria-labelledby="add-milestone-label" aria-hidden="true">
		<div class="modal-dialog">
			<!-- Modal content-->
			<div class="modal-content">
				<div class="modal-header">
					<h4 class="modal-title modal-left" id="add-milestone-label"><?php echo $lang['Add Project Milestone']; ?> </h4>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
							<?php echo ts_icon('close'); ?>
						</button>
				</div>
				<div class="modal-body">
					<form method="post" class="milestonefrm1" action="#" enctype="multipart/form-data">
						<div class="form-group">
							<div class="col-sm-12">
								<div class="field-label">
									<label for="firstName">
										<?php echo $lang['Project title']; ?>*</label>
								</div>
								<input type="text" name="title" class="form-control only-alpha" required> </div>
							<div class="clearfix"></div>
						</div>
						<div class="form-group">
							<div class="col-sm-12">
								<div class="field-label">
									<label for="amount">
										<?php echo $lang['Amount']; ?> (
											<?php echo $currency_symbol ; ?>)</label>
								</div>
								<input type="number" name="amount" class="form-control" required> </div>
							<div class="clearfix"></div>
						</div>
						<div class="form-group">
							<div class="col-sm-12">
								<div class="field-label">
									<label for="status">
										<?php echo $lang['Status']; ?>
									</label>
								</div>
								<select class="ui dropdown form-control status1" name="status">
									<option value="0">
										<?php echo $lang['Unpaid']; ?>
									</option>
									<option value="1">
										<?php echo $lang['Paid manually']; ?>
									</option>
								</select>
							</div>
							<div class="clearfix"></div>
						</div>
						<div class="form-group">
							<div class="col-sm-12">
								<div class="field-label">
									<label for="deadline">
										<?php echo $lang['Deadline']; ?>
									</label>
								</div>
								<input type="text" name="deadline" autocomplete="off" class="form-control datepicker" required>
								<input type="hidden" name="releaseDate" class="form-control datepicker releaseDate" required value="1970-01-01"> </div>
							<div class="clearfix"></div>
						</div>
						<div class="form-group">
							<div class="col-sm-12">
								<input type="hidden" name="projId" value="<?php echo $projectId ;?>" />
								<input type="hidden" name="clientId" value="<?php echo $clientId ;?>" />
								<input type="submit" name="add-milestone" value="<?php echo $lang['Add milestone']; ?>" class="btn bigbutton" /> </div>
						</div>
						<div class="clearfix"></div>
					</form>
				</div>
			</div>
		</div>
	</div>
	<!-- Modal -->
	<div id="edit-milestone" class="modal fade" tabindex="-1" aria-labelledby="edit-milestone-label" aria-hidden="true">
		<div class="modal-dialog">
			<!-- Modal content-->
			<div class="modal-content">
				<div class="modal-header">
					<h4 class="card-title" id="edit-milestone-label"><?php echo $lang['Edit Invoice']; ?></h4>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
							<?php echo ts_icon('close'); ?>
						</button>
				</div>
				<div class="modal-body">
				<?php if(isset($_POST['edit_id'])){
					  $edit_id=$_POST['edit_id'];
					  $latestMile=milestone::findByMilestoneId($edit_id);
					  ?>
						<form method="post" class="milestonefrm1" action="#" enctype="multipart/form-data">
							<div class="form-group">
								<div class="col-sm-12">
									<div class="field-label">
										<label for="firstName">
											<?php echo $lang['Project title'];?>*</label>
									</div>
									<input type="text" name="title1" class="form-control" value="<?php echo $latestMile->title;?>" required> </div>
								<div class="clearfix"></div>
							</div>
							<div class="form-group">
								<div class="col-sm-12">
									<div class="field-label">
										<label for="amount">
											<?php echo $lang['Amount']; ?> (
												<?php echo getCurrencySymbol($latestMile->currency); ?>)</label>
									</div>
									<input type="number" name="amount1" class="form-control" value="<?php echo $latestMile->budget;?>" required> </div>
								<div class="clearfix"></div>
							</div>
							<div class="form-group">
								<div class="col-sm-12">
									<div class="field-label">
										<label for="status">
											<?php echo $lang['Status']; ?>
										</label>
									</div>
									<select class="ui dropdown form-control status1" name="status1">
										<?php if($latestMile->status==0){?>
											<option value="0" checked>
												<?php echo $lang['Unpaid']; ?>
											</option>
											<option value="1">
												<?php echo $lang['Paid manually']; ?>
											</option>
											<?php }else{?>
												<option value="0">
													<?php echo $lang['Unpaid']; ?>
												</option>
												<option value="1" checked>
													<?php echo $lang['Paid manually']; ?>
												</option>
												<?php } ?>
									</select>
								</div>
								<div class="clearfix"></div>
							</div>
							<div class="form-group">
								<div class="col-sm-12">
									<div class="field-label">
										<label for="deadline">
											<?php echo $lang['Deadline']; ?>
										</label>
									</div>
									<input type="text" name="deadline1" class="form-control datepicker" required value="<?php echo $latestMile->deadline; ?>">
									<?php 
									$relDate = $latestMile->releaseDate;
									?>
										<input type="hidden" name="releaseDate" class="form-control datepicker releaseDate" required value="<?php if($relDate){echo $latestMile->releaseDate;} else{ echo '1970-01-01';} ?>"> </div>
								<div class="clearfix"></div>
							</div>
							<div class="form-group">
								<div class="col-sm-12">
									<input type="hidden" name="editId" value="<?php echo  $edit_id ;?>" />
									<!-- Email Notification Toggle -->
									<div class="input-notify">
										<div class="form-group d-block d-md-flex justify-content-between">
											<div class="d-flex col-gap align-items-center">
												<div class="checkbox-wrapper-6">
													<input class="tgl tgl-light" id="notifyMilestonePaid" name="notifyMilestonePaid" type="checkbox" checked />
													<label class="tgl-btn" for="notifyMilestonePaid"></label>
												</div>
												<div>
													<label for="notifyMilestonePaid" class="permission-label"><?php echo $lang['Email Notification']; ?></label>
													<p class="permission-description"><?php echo $lang['Notify the client invoice is paid']; ?></p>
												</div>
											</div>
											<input type="submit" name="edit-milestone-1" value="<?php echo $lang['Update']; ?>" class="btn bigbutton" />
										</div>
									</div>
								</div>
							</div>
						</form>
						<?php
					}?>
				</div>
			</div>
		</div>
	</div>
		<div class="page-container vh-100">
		<div class="container-fluid vh-100">
			<div class="row row-eq-height vh-100">
				<?php  include("../templates/sidebar.php"); ?>
					<div class="page-content">
						<?php include('../templates/top-header.php'); ?>
						
						<!-- User Profile Layout with Sidebar -->
						<div class="row profile sidebar-shrunk vh-100" id="project-layout-row">
							<!-- Modern UI Layout -->
							<div class="container-fluid">
								<div class="row">
									<?php include("../templates/user-profile-sidebar.php"); ?>
									<div class="col-xl-9 col-lg-8 col-md-12 flex-grow-1 fill-rest faded-right right-col pd-0">
										<!-- Profile Details -->
										<div class="vh-100 ">
											<div class=" Project-details">
							<div class="row bg-grey">
								<div class="col-md-12 project-tabs">
								<?php if ($tab === 'tasks' || $tab === 'media'): ?>
								<link rel="stylesheet" href="../assets/css/kanban-bulk.css?v=9">
								<?php endif; ?>
								<?php if ($tab === 'overview'): ?>
								<link rel="stylesheet" href="<?php echo $url; ?>assets/css/reports.css">
								<?php endif; ?>
								 <div class="row">
									<div class="project-tabs-header">
										<div class="scrollable-tabs-container d-flex col-gap-40 col-gap-40-sep">
											<div class="icon-container sep">
											  <?php if ($anyProjectsTasksModuleEnabled) : ?>
											  <a href="profile?user_id=<?php echo $user_id; ?>"
												 class="<?php echo ($tab == 'overview') ? 'active' : ''; ?>">
												<?php echo ts_icon('chart-pie'); ?>
											<?php echo $lang['Profile Stats']; ?>
											  </a>
											  <?php endif; ?>
											  <?php if ($projectsModuleEnabled) : ?>
											  <a href="profile?user_id=<?php echo $user_id; ?>&tab=projects"
												 class="<?php echo ($tab == 'projects') ? 'active' : ''; ?>">
												<?php echo ts_icon('folder'); ?><?php echo $lang['Projects']; ?>
											  </a>
											  <?php endif; ?>
											  <?php if ($tasksModuleEnabled) : ?>
											  <a href="profile?user_id=<?php echo $user_id; ?>&tab=tasks"
												 class="<?php echo ($tab == 'tasks') ? 'active' : ''; ?>">
												<?php echo ts_icon('tasks', 'h-6'); ?><?php echo $lang['Tasks']; ?>
													  </a>
											  <?php endif; ?>
										<?php if ($profileInvoiceTabVisible): ?>
										<a href="profile?user_id=<?php echo $user_id; ?>&tab=invoice"
										   class="<?php echo ($tab == 'invoice') ? 'active' : ''; ?>">
										  <?php echo ts_icon('bookmark'); ?>
										  <?php echo $lang['Invoice']; ?>
										</a>
										<?php endif; ?>
									  <a href="profile?user_id=<?php echo $user_id; ?>&tab=activity"
										 class="<?php echo ($tab == 'activity') ? 'active' : ''; ?>">
										<?php echo ts_icon('clock'); ?>
												<?php echo $lang['Activity']; ?>
											  </a>
										<?php if ($profileMediaTabVisible): ?>
										  <a href="profile?user_id=<?php echo $user_id; ?>&tab=media"
											 class="<?php echo ($tab == 'media') ? 'active' : ''; ?>">
											<?php echo ts_icon('folder'); ?>
											<?php echo $lang['Media'] ?? 'Media'; ?>
										  </a>
										<?php endif; ?>
											  <a href="profile?user_id=<?php echo $user_id; ?>&tab=notes"
												 class="<?php echo ($tab == 'notes') ? 'active' : ''; ?>">
												<?php echo ts_icon('edit'); ?>
												<?php echo $lang['Notes']; ?>
									  </a>
									  
									</div>
					       </div>   
					   </div> 
					 <div class="headers-icons d-flex">
						<div class="icon-container">
							<a href="kanban" class="ms-2">
							</a>
						</div>
					</div>
					<?php if ($tab != 'activity' && $tab != 'overview' && !($tab === 'invoice' && !empty($profileInvoiceLocked)) && !($tab === 'media' && !empty($profileMediaLocked))): ?>
					<div class="search">
							<?php if ($tab === 'notes') : ?><span id="noteAutosaveStatus" class="me-2 d-none" aria-live="polite">Saving...</span><?php endif; ?>
						   <div class="search-icon border-btn-a" onclick="toggleSearch()">
							 <?php echo ts_icon('search', 'w-2'); ?>
							</div>
							  <form method="GET" action="profile" class="search-form" id="searchForm">
								<input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
								<input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
								<?php if ($tab === 'notes') : ?>
									<?php
									$__pnC = isset($_GET['color_filter']) && in_array((string) $_GET['color_filter'], ['blue', 'green', 'yellow'], true) ? (string) $_GET['color_filter'] : '';
									$__pnS = isset($_GET['scope_filter']) && (string) $_GET['scope_filter'] === 'recent' ? '1' : '';
									$__pnA = isset($_GET['profile_notes_list']) && (string) $_GET['profile_notes_list'] === 'archive';
									if ($__pnC !== '') {
										echo '<input type="hidden" name="color_filter" value="' . htmlspecialchars($__pnC, ENT_QUOTES, 'UTF-8') . '">';
									}
									if ($__pnS === '1') {
										echo '<input type="hidden" name="scope_filter" value="recent">';
									}
									if ($__pnA) {
										echo '<input type="hidden" name="profile_notes_list" value="archive">';
									}
									?>
								<?php endif; ?>
								<?php if ($tab === 'media') : ?>
									<?php
									$__pmView = (isset($_GET['view']) && (string) $_GET['view'] === 'table') ? 'table' : 'grid';
									$__pmFolder = isset($_GET['folder']) ? (int) $_GET['folder'] : 0;
									$__pmType = isset($_GET['file_type']) ? (string) $_GET['file_type'] : '';
									echo '<input type="hidden" name="view" value="' . htmlspecialchars($__pmView, ENT_QUOTES, 'UTF-8') . '">';
									if ($__pmFolder > 0) {
										echo '<input type="hidden" name="folder" value="' . (int) $__pmFolder . '">';
									}
									if (in_array($__pmType, ['image', 'document', 'other'], true)) {
										echo '<input type="hidden" name="file_type" value="' . htmlspecialchars($__pmType, ENT_QUOTES, 'UTF-8') . '">';
									}
									?>
								<?php endif; ?>
								<div class="input-group">
								<span class="search-field-icon">
									<?php echo ts_icon('search', 'w-2'); ?>
								</span>
								<input type="text" id="client-search" name="search" class="form-control" placeholder="<?php echo $tab === 'notes' ? htmlspecialchars($lang['Search notes...'] ?? 'Search notes...', ENT_QUOTES, 'UTF-8') : ($tab === 'media' ? htmlspecialchars($lang['Search Files'] ?? 'Search Files', ENT_QUOTES, 'UTF-8') : htmlspecialchars($lang['Search'] ?? 'Search', ENT_QUOTES, 'UTF-8')); ?>" value="<?php echo htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
								<?php if (isset($_GET['search']) && (string) $_GET['search'] !== '') : ?>
								<?php
								$__clearHref = 'profile?user_id=' . (int) $user_id . '&tab=' . rawurlencode((string) $tab);
								if ($tab === 'notes') {
									$__q = [
										'user_id' => (int) $user_id,
										'tab' => 'notes',
									];
									$__pnC0 = isset($_GET['color_filter']) && in_array((string) $_GET['color_filter'], ['blue', 'green', 'yellow'], true) ? (string) $_GET['color_filter'] : '';
									$__pnS0 = isset($_GET['scope_filter']) && (string) $_GET['scope_filter'] === 'recent';
									$__pnA0 = isset($_GET['profile_notes_list']) && (string) $_GET['profile_notes_list'] === 'archive';
									if ($__pnC0 !== '') {
										$__q['color_filter'] = $__pnC0;
									}
									if ($__pnS0) {
										$__q['scope_filter'] = 'recent';
									}
									if ($__pnA0) {
										$__q['profile_notes_list'] = 'archive';
									}
									$__clearHref = 'profile?' . http_build_query($__q, '', '&', PHP_QUERY_RFC3986);
								} elseif ($tab === 'media') {
									$__q = [
										'user_id' => (int) $user_id,
										'tab' => 'media',
										'view' => (isset($_GET['view']) && (string) $_GET['view'] === 'table') ? 'table' : 'grid',
									];
									$__pmFolder0 = isset($_GET['folder']) ? (int) $_GET['folder'] : 0;
									$__pmType0 = isset($_GET['file_type']) ? (string) $_GET['file_type'] : '';
									if ($__pmFolder0 > 0) {
										$__q['folder'] = $__pmFolder0;
									}
									if (in_array($__pmType0, ['image', 'document', 'other'], true)) {
										$__q['file_type'] = $__pmType0;
									}
									$__clearHref = 'profile?' . http_build_query($__q, '', '&', PHP_QUERY_RFC3986);
								}
								?>
								<a href="<?php echo htmlspecialchars($__clearHref, ENT_QUOTES, 'UTF-8'); ?>" class="cross" title="<?php echo htmlspecialchars($lang['Close'] ?? 'Close', ENT_QUOTES, 'UTF-8'); ?>">
								<?php echo ts_icon('close', 'w-2'); ?></a>
								<?php else : ?>
								<a href="#" class="cross" onclick="toggleSearch(); return false;" title="<?php echo htmlspecialchars($lang['Close'] ?? 'Close', ENT_QUOTES, 'UTF-8'); ?>">
								<?php echo ts_icon('close', 'w-2'); ?></a>
								<?php endif; ?>
								</div>
							</form>
					  </div>
					<?php endif; ?>
					  		 <div class="edit-overview-btn d-none d-md-block">
									<?php if ($tab == 'media' && empty($profileMediaLocked)): ?>
									<?php
									$__pmViewHdr = (isset($_GET['view']) && (string) $_GET['view'] === 'table') ? 'table' : 'grid';
									$__pmFolderHdr = isset($_GET['folder']) ? (int) $_GET['folder'] : 0;
									$__pmTypeHdr = isset($_GET['file_type']) ? (string) $_GET['file_type'] : '';
									$__pmSearchHdr = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
									$__pmBuildViewHdr = static function ($viewMode) use ($user_id, $__pmFolderHdr, $__pmTypeHdr, $__pmSearchHdr) {
										$__q = ['user_id' => (int) $user_id, 'tab' => 'media', 'view' => $viewMode, 'page' => 1];
										if ($__pmFolderHdr > 0) { $__q['folder'] = $__pmFolderHdr; }
										if (in_array($__pmTypeHdr, ['image', 'document', 'other'], true)) { $__q['file_type'] = $__pmTypeHdr; }
										if ($__pmSearchHdr !== '') { $__q['search'] = $__pmSearchHdr; }
										return 'profile?' . http_build_query($__q, '', '&', PHP_QUERY_RFC3986);
									};
									?>
									<div class="icon-container sep" id="profileMediaBulkToolbar">
										<div class="pm-trash task-trash align-middle d-flex col-gap-5">
											<a href="#" onclick="enterBulkMode(); return false;" class="bulk-delete-tab red border-btn-a" id="bulkSelectTab" title="<?php echo htmlspecialchars($lang['Bulk Select'] ?? 'Bulk select', ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($lang['Bulk Select'] ?? 'Bulk select', ENT_QUOTES, 'UTF-8'); ?>">
												<?php echo ts_icon('duplicate', 'w-2'); ?>
											</a>
											<div class="media-view-toggle" id="profileMediaViewToggle" role="group" aria-label="View mode">
												<a href="<?php echo htmlspecialchars($__pmBuildViewHdr('grid'), ENT_QUOTES, 'UTF-8'); ?>" class="media-view-toggle__btn border-btn-a<?php echo $__pmViewHdr === 'grid' ? ' is-active' : ''; ?>" title="<?php echo htmlspecialchars($lang['Grid View'] ?? 'Grid view', ENT_QUOTES, 'UTF-8'); ?>">
													<?php echo ts_icon('view-grid', 'w-2'); ?>
												</a>
												<a href="<?php echo htmlspecialchars($__pmBuildViewHdr('table'), ENT_QUOTES, 'UTF-8'); ?>" class="media-view-toggle__btn border-btn-a<?php echo $__pmViewHdr === 'table' ? ' is-active' : ''; ?>" title="<?php echo htmlspecialchars($lang['Table View'] ?? 'Table view', ENT_QUOTES, 'UTF-8'); ?>">
													<?php echo ts_icon('table', 'w-2'); ?>
												</a>
											</div>
											<a href="#" onclick="exitBulkMode(); return false;" class="bulk-delete-tab border-btn-a" id="bulkBackTab" style="display:none;">
												<?php echo ts_icon('arrow-left', 'w-2'); ?>
												<span><?php echo $lang['Back'] ?? 'Back'; ?></span>
											</a>
											<a href="#" onclick="selectAllTasks(); return false;" class="bulk-delete-tab border-btn-a" id="bulkSelectAllTab" style="display:none;">
												<?php echo ts_icon('check-circle', 'w-2'); ?>
												<span id="bulkSelectAllLabel"><?php echo $lang['Select all'] ?? 'Select all'; ?></span>
											</a>
											<a href="#" onclick="openMoveDialogForSelectedFiles(); return false;" class="bulk-delete-tab border-btn-a" id="bulkMoveTab" style="display:none;">
												<?php echo ts_icon('arrow-right', 'w-2'); ?>
												<span id="bulkMoveLabel"><?php echo $lang['Move'] ?? 'Move'; ?></span>
											</a>
											<a href="#" onclick="deleteSelectedFiles(); return false;" class="bulk-delete-tab border-btn-a" id="bulkDeleteTab" style="display:none;">
												<?php echo ts_icon('delete', 'w-2'); ?>
												<span id="bulkDeleteLabel"><?php echo $lang['Delete'] ?? 'Delete'; ?></span>
											</a>
											<a href="#" onclick="downloadSelectedAsZip(); return false;" class="bulk-delete-tab border-btn-a" id="bulkDownloadZipTab" style="display:none;">
												<?php echo ts_icon('download', 'w-2'); ?>
												<span id="bulkDownloadZipLabel"><?php echo $lang['Download all (ZIP)'] ?? 'Download all (ZIP)'; ?></span>
											</a>
										</div>
									</div>
									<?php endif; ?>
							        <?php if ($tab == 'tasks'): ?>
							        <div class="icon-container sep" id="profileTasksBulkToolbar">
							        	<div class="pm-trash task-trash align-middle d-flex col-gap-5">
							        		<a href="#" onclick="enterBulkMode(); return false;" class="bulk-delete-tab red border-btn-a" id="bulkSelectTab" title="<?php echo htmlspecialchars($lang['Bulk Select'] ?? 'Bulk select', ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($lang['Bulk Select'] ?? 'Bulk select', ENT_QUOTES, 'UTF-8'); ?>">
							        			<?php echo ts_icon('duplicate', 'w-2'); ?>
							        		</a>
							        		<a href="#" onclick="exitBulkMode(); return false;" class="bulk-delete-tab border-btn-a" id="bulkBackTab" style="display:none;">
							        			<?php echo ts_icon('arrow-left', 'w-2'); ?>
							        			<span><?php echo $lang['Back'] ?? 'Back'; ?></span>
							        		</a>
							        		<a href="#" onclick="selectAllTasks(); return false;" class="bulk-delete-tab border-btn-a" id="bulkSelectAllTab" style="display:none;">
							        			<?php echo ts_icon('check-circle', 'w-2'); ?>
							        			<span id="bulkSelectAllLabel"><?php echo $lang['Select all'] ?? 'Select all'; ?></span>
							        		</a>
							        		<a href="#" onclick="confirmBulkDelete(); return false;" class="bulk-delete-tab border-btn-a" id="bulkDeleteTab" style="display:none;">
							        			<?php echo ts_icon('delete', 'w-2'); ?>
							        			<span id="bulkDeleteLabel"><?php echo $lang['Delete'] ?? 'Delete'; ?></span>
							        		</a>
							        	</div>
							        </div>
							        <?php endif; ?>
                                    
									<?php if ($tab == 'notes') : ?>
									<?php include __DIR__ . '/../templates/docs/profile-notes-filters-data.php'; ?>
									<div class="action-toggle border-btn-a collapsed<?php echo !empty($profileNotesFilterActive) ? ' has-active-filters' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#profileNotesFilterDropdown" aria-expanded="false" role="button" tabindex="0">
                                        <span class="action-text"><?php echo $lang['Filters'] ?? 'Filters'; ?></span><span class="mobile-ellipsis"><?php echo ts_icon('ellipsis', 'w-2'); ?></span>
										<?php echo ts_icon('filter', 'w-2'); ?>
										<?php if (!empty($profileNotesFilterActive)) : ?>
							<a href="profile?user_id=<?php echo (int) $user_id; ?>&tab=notes" class="kanban-filter-clear" title="<?php echo htmlspecialchars($lang['Clear all filters'] ?? 'Clear all filters', ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($lang['Clear all filters'] ?? 'Clear all filters', ENT_QUOTES, 'UTF-8'); ?>" onclick="event.stopPropagation();">
								<?php echo ts_icon('close'); ?>
							</a>
										<?php endif; ?>
                                    </div>
									<?php include __DIR__ . '/../templates/docs/profile-notes-filters-dropdown.php'; ?>
									<?php endif; ?>
									
                                    
                                    <!-- Date Range Filter Dropdown for Activity Tab -->
                                    <?php if ($tab == 'activity'): ?>
                                    <div class="action-toggle border-btn-a collapsed<?php echo $hasActiveActivityFilter ? ' has-active-filters' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#activityDateRangeDropdown" aria-expanded="false" role="button" tabindex="0">
                                        <span class="action-text"><?php echo $lang['Filters'] ?? 'Filters'; ?></span><span class="mobile-ellipsis"><?php echo ts_icon('ellipsis', 'w-2'); ?></span>
										<?php echo ts_icon('filter', 'w-2'); ?>
										<?php if ($hasActiveActivityFilter): ?>
										<a href="<?php echo htmlspecialchars($profileActivityClearFiltersUrl, ENT_QUOTES, 'UTF-8'); ?>" class="kanban-filter-clear" title="<?php echo htmlspecialchars($lang['Clear'] ?? 'Clear', ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($lang['Clear filters'] ?? 'Clear filters', ENT_QUOTES, 'UTF-8'); ?>" onclick="event.stopPropagation();">
											<?php echo ts_icon('close'); ?>
										</a>
										<?php endif; ?>
									</div>
									<div id="activityDateRangeDropdown" class="toggle-action collapse shadow-dept">
										<ul>
											<li class="<?php echo !$dateFilter || $dateFilter === 'all' ? 'active' : ''; ?>">
												<a href="?user_id=<?php echo $user_id; ?>&tab=activity">
                                                    <span><?php echo $lang['All Time']; ?></span>
                                                </a>
                                            </li>
                                            <li class="<?php echo $dateFilter === 'today' ? 'active' : ''; ?>">
                                                <a href="?user_id=<?php echo $user_id; ?>&tab=activity&date_filter=today">
                                                    <span><?php echo $lang['Today']; ?></span>
                                                </a>
                                            </li>
                                            <li class="<?php echo $dateFilter === 'yesterday' ? 'active' : ''; ?>">
                                                <a href="?user_id=<?php echo $user_id; ?>&tab=activity&date_filter=yesterday">
                                                    <span><?php echo $lang['Yesterday']; ?></span>
                                                </a>
                                            </li>
                                            <li class="<?php echo $dateFilter === 'this_week' ? 'active' : ''; ?>">
                                                <a href="?user_id=<?php echo $user_id; ?>&tab=activity&date_filter=this_week">
                                                    <span><?php echo $lang['This Week']; ?></span>
                                                </a>
                                            </li>
                                            <li class="<?php echo $dateFilter === 'last_week' ? 'active' : ''; ?>">
                                                <a href="?user_id=<?php echo $user_id; ?>&tab=activity&date_filter=last_week">
                                                    <span><?php echo $lang['Last Week']; ?></span>
                                                </a>
                                            </li>
                                            <li class="<?php echo $dateFilter === 'this_month' ? 'active' : ''; ?>">
                                                <a href="?user_id=<?php echo $user_id; ?>&tab=activity&date_filter=this_month">
                                                    <span><?php echo $lang['This Month']; ?></span>
                                                </a>
                                            </li>
                                            <li class="<?php echo $dateFilter === 'last_month' ? 'active' : ''; ?>">
                                                <a href="?user_id=<?php echo $user_id; ?>&tab=activity&date_filter=last_month">
                                                    <span><?php echo $lang['Last Month']; ?></span>
                                                </a>
                                            </li>
                                            <li class="<?php echo $dateFilter === 'this_year' ? 'active' : ''; ?>">
                                                <a href="?user_id=<?php echo $user_id; ?>&tab=activity&date_filter=this_year">
                                                    <span><?php echo $lang['This Year']; ?></span>
                                                </a>
                                            </li>
											
												<hr>
												<li>
													<a class="secondary-btn-a" href="#" onclick="toggleDateRangeCard(); return false;">
														<span>
															<?php echo $lang['Select Date Range']; ?> 
 														</span>
													</a>
												</li>
											<!-- Date Range Card -->
											<div class="date-range card" style="display: none;">
												<div class="card-body">
													<div class="mb-3">
														<div class="date-f">
															<label class="form-label font-size-10"><?php echo $lang['Start Date']; ?></label>
															<input type="date" id="customStart" class="form-control">
														</div>
														<div class="date-f">
															<label class="form-label font-size-10"><?php echo $lang['End Date']; ?></label>
															<input type="date" id="customEnd" class="form-control">
														</div>
													</div>
													
													<div class="d-flex col-gap-10">
														<button class="primary-btn" id="primary-btn" onclick="selectCustomRange()"><?php echo $lang['Apply']; ?></button>
														<button class="btn border-btn-a" onclick="clearDateRange()"><?php echo $lang['Clear']; ?></button>
													</div>
												</div>
											</div>
										</ul>
                                    </div>
                                    <?php endif; ?>
                            </div>
                            
                            <!-- JavaScript for Date Range Filter -->
                            <script>
                            function showCustomDateRange() {
                                document.getElementById('customDateRangeForm').style.display = 'block';
                            }
                            
                            function hideCustomDateRange() {
                                document.getElementById('customDateRangeForm').style.display = 'none';
                            }
                            </script>
							
							<?php if ($tab == 'media' && empty($profileMediaLocked)): ?>
							<div class="edit-overview-btn d-flex d-md-none align-items-center ms-2">
								<button type="button" class="mobile-filters-toggle-btn" id="mvMediaVaultFiltersOpenBtn" aria-label="<?php echo htmlspecialchars($lang['Files & options'] ?? 'Files & options', ENT_QUOTES, 'UTF-8'); ?>">
									<?php echo ts_icon('dots-vertical'); ?>
								</button>
							</div>
							<?php else: ?>
							<div class="edit-overview-btn  d-block d-md-none">
							 <td class="extra-height">
                                    <div class="action-toggle border-btn-a" data-bs-toggle="collapse" data-bs-target="#project-menu<?php echo $recentProject->p_id;?>" role="button" tabindex="0">
                                        <span class="action-text"><?php echo $lang['Filters']; ?></span><span class="mobile-ellipsis"><?php echo ts_icon('ellipsis', 'w-2'); ?></span>
										<?php echo ts_icon('filter', 'w-2'); ?>
                                    </div>
									
									<div id="project-menu" class="toggle-action collapse shadow-dept">
                                        <ul>
										<?php if ($tab == 'overview'): ?>
											<li><a href="edit?editprofile=<?php echo $user_id; ?>"><?php echo ts_icon('edit', 'tasksession-timer-log-menu-ico me-2'); ?><span><?php echo $lang['Edit Profile']; ?></span></a></li>
										<?php endif; ?>	
										
										<?php if ($invoiceModuleEnabled && empty($profileInvoiceLocked) && $tab == 'invoice'): ?>
											<li> <a href="add-invoice" class="primary-btn" target="_blank"><?php echo $lang['Add Invoice']; ?></a></li>
										<?php endif; ?>	
										
										<?php if ($tab == 'projects'): ?>
											<li><a href="add-new-project" class="primary-btn" target="_blank"><?php echo $lang['Create project']; ?></a></li>
										<?php endif; ?>	
										
										<?php if ($tab == 'tasks'): ?>
											<li><a href="add_task?source=profile&user_id=<?= $user_id ?>" class="primary-btn" target="_blank"><?php echo $lang['Add New Task']; ?></a></li>
										<?php endif; ?>	
										
										<?php if ($tab == 'notes'): ?>
											<?php include __DIR__ . '/../templates/docs/profile-notes-filters-data.php'; ?>
											<?php include __DIR__ . '/../templates/docs/profile-notes-filters-hamburger.php'; ?>
											<?php include __DIR__ . '/../templates/docs/profile-notes-toolbar.php'; ?>
										<?php endif; ?>	
										<?php if ($tab == 'media' && empty($profileMediaLocked)): ?>
											<li><a href="#" data-bs-toggle="modal" data-bs-target="#createFolderModal" onclick="return false;"><?php echo $lang['Create Folder'] ?? 'Create Folder'; ?></a></li>
											<li><a href="#" onclick="document.getElementById('uploadBtn').click(); return false;"><?php echo $lang['Upload Files'] ?? 'Upload Files'; ?></a></li>
										<?php endif; ?>	
									
                                        </ul>
										</td>
									</div>
							   </div>
							<?php endif; ?>
							   
							<?php if ($tab == 'overview'): ?>
							<div class="d-none d-md-block">
							<a href="edit?editprofile=<?php echo $user_id; ?>" class="primary-btn"><?php echo $lang['Edit Profile']; ?></a>
							</div>
							<?php endif; ?>	
							
							<?php if ($invoiceModuleEnabled && empty($profileInvoiceLocked) && $tab == 'invoice'): ?>
							    <div class="d-none d-md-block">
								   <a href="add-invoice" class="primary-btn" target="_blank"><?php echo $lang['Add Invoice']; ?> <?php echo ts_icon('plus', 'w-2'); ?></a>
							</div>
							<?php endif; ?>	
							
							<?php if ($tab == 'projects'): ?>
							   <div class="d-none d-md-block">
								   <a href="add-new-project" class="primary-btn" target="_blank"><?php echo $lang['Create project']; ?> <?php echo ts_icon('plus', 'w-2'); ?></a>
							</div>
							<?php endif; ?>	
							
							
							<?php if ($tab == 'tasks'): ?>
							<div class="edit-overview-btn d-none d-md-block profile-task-filters kanban-header-filters">
								<td class="extra-height">
								<div class="action-toggle border-btn-a collapsed<?php echo $hasActiveProfileTaskFilters ? ' has-active-filters' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#taskFilterDropdown" aria-expanded="false" role="button" tabindex="0">
									<span class="action-text"><?php echo $lang['Filters']; ?></span><span class="mobile-ellipsis"><?php echo ts_icon('ellipsis', 'w-2'); ?></span>
									<?php echo ts_icon('filter', 'w-2'); ?>
									<?php if ($hasActiveProfileTaskFilters): ?>
									<a href="<?php echo htmlspecialchars($profileTaskClearFiltersUrl, ENT_QUOTES, 'UTF-8'); ?>" class="kanban-filter-clear" title="<?php echo htmlspecialchars($lang['Clear'] ?? 'Clear', ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($lang['Clear filters'] ?? 'Clear filters', ENT_QUOTES, 'UTF-8'); ?>" onclick="event.stopPropagation();">
										<?php echo ts_icon('close'); ?>
									</a>
									<?php endif; ?>
								</div>
								<div id="taskFilterDropdown" class="toggle-action collapse shadow-dept">
									<ul>
										<?php
										$statusTypes = ['todo', 'inprogress', 'review', 'done'];
										$statusLabels = [
											'todo' => 'To Do',
											'inprogress' => 'In Progress',
											'review' => 'In Review',
											'done' => 'Completed'
										];
										$statusFilter = isset($_GET['status']) && (in_array($_GET['status'], $statusTypes) || in_array($_GET['status'], ['overdue', 'due_today', 'task_pro', 'on_time'])) ? $_GET['status'] : null;
										$internalFilter = isset($_GET['internal']) && $_GET['internal'] == '1';
										$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
										$startDateFilter = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
										$endDateFilter = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
										$statusIcons = [
											'todo' => 'color-todo-bg',
											'inprogress' => 'color-inprogress-bg',
											'review' => 'color-review-bg',
											'done' => 'color-done-bg',
										];
										?>
										<li class="<?php echo !$statusFilter ? 'active' : ''; ?>" data-status="all">
											<a href="?user_id=<?php echo $user_id; ?>&tab=tasks<?php echo $startDateFilter ? '&start_date=' . htmlspecialchars($startDateFilter) : ''; ?><?php echo $endDateFilter ? '&end_date=' . htmlspecialchars($endDateFilter) : ''; ?>">
												<span><b><?php echo $lang['All Task']; ?></b></span>
											</a>
										</li>
										<li class="<?php echo $internalFilter ? 'active' : ''; ?>" data-status="internal">
											<a href="?user_id=<?php echo $user_id; ?>&tab=tasks&internal=1<?php echo $startDateFilter ? '&start_date=' . htmlspecialchars($startDateFilter) : ''; ?><?php echo $endDateFilter ? '&end_date=' . htmlspecialchars($endDateFilter) : ''; ?>">
												<span><?php echo $lang['Internal Task']; ?> <i class="dots text-secondary object-align-right"></i></span>
											</a>
										</li>
										<?php foreach ($statusTypes as $status): ?>
										<li class="<?php echo $statusFilter === $status ? 'active' : ''; ?>" data-status="<?php echo $status; ?>">
											<a href="?user_id=<?php echo $user_id; ?>&tab=tasks&status=<?php echo $status; ?><?php echo $startDateFilter ? '&start_date=' . htmlspecialchars($startDateFilter) : ''; ?><?php echo $endDateFilter ? '&end_date=' . htmlspecialchars($endDateFilter) : ''; ?>">
												<span><?php echo $statusLabels[$status]; ?> <i class="dots <?php echo $statusIcons[$status] ?? 'text-secondary'; ?> object-align-right"></i></span>
											</a>
										</li>
										<?php endforeach; ?>
										<hr>
										<li><b><?php echo $lang['By Status']; ?> </b></li>
										<li class="<?php echo $statusFilter === 'overdue' ? 'active' : ''; ?>" data-status="overdue">
											<a href="?user_id=<?php echo $user_id; ?>&tab=tasks&status=overdue<?php echo $startDateFilter ? '&start_date=' . htmlspecialchars($startDateFilter) : ''; ?><?php echo $endDateFilter ? '&end_date=' . htmlspecialchars($endDateFilter) : ''; ?>">
												<span><?php echo $lang['Overdue']; ?></span>
											</a>
										</li>
										<li class="<?php echo $statusFilter === 'due_today' ? 'active' : ''; ?>" data-status="due_today">
											<a href="?user_id=<?php echo $user_id; ?>&tab=tasks&status=due_today<?php echo $startDateFilter ? '&start_date=' . htmlspecialchars($startDateFilter) : ''; ?><?php echo $endDateFilter ? '&end_date=' . htmlspecialchars($endDateFilter) : ''; ?>">
												<span><?php echo $lang['Due Today']; ?></span>
											</a>
										</li>
										<li class="<?php echo $statusFilter === 'task_pro' ? 'active' : ''; ?>" data-status="task_pro">
											<a href="?user_id=<?php echo $user_id; ?>&tab=tasks&status=task_pro<?php echo $startDateFilter ? '&start_date=' . htmlspecialchars($startDateFilter) : ''; ?><?php echo $endDateFilter ? '&end_date=' . htmlspecialchars($endDateFilter) : ''; ?>">
												<span><?php echo $lang['Task Pro']; ?></span>
											</a>
										</li>
										<li class="<?php echo $statusFilter === 'on_time' ? 'active' : ''; ?>" data-status="on_time">
											<a href="?user_id=<?php echo $user_id; ?>&tab=tasks&status=on_time<?php echo $startDateFilter ? '&start_date=' . htmlspecialchars($startDateFilter) : ''; ?><?php echo $endDateFilter ? '&end_date=' . htmlspecialchars($endDateFilter) : ''; ?>">
												<span><?php echo $lang['On Time']; ?></span>
											</a>
										</li>
										<hr>
										<li>
											<a class="secondary-btn-a" href="#" onclick="toggleDateRangeCard(); return false;">
												<span><?php echo $lang['Select Date Range']; ?></span>
											</a>
										</li>
										<div class="date-range card" style="display: none;">
											<div class="card-body">
												<b class="mb-2 d-block"><?php echo $lang['Quick Range']; ?></b>
												<div>
													<li><button class="dropdown-item" onclick="selectQuickRange('last7')"><?php echo $lang['Last 7 days']; ?></button></li>
													<li><button class="dropdown-item" onclick="selectQuickRange('last30')"><?php echo $lang['Last 30 days']; ?></button></li>
													<li><button class="dropdown-item" onclick="selectQuickRange('last90')"><?php echo $lang['Last 90 days']; ?></button></li>
													<li><button class="dropdown-item" onclick="selectQuickRange('last6months')"><?php echo $lang['Last 6 months']; ?></button></li>
													<li><button class="dropdown-item" onclick="selectQuickRange('thisYear')"><?php echo $lang['This Year (Jan - Dec)']; ?></button></li>
												</div>
												<hr>
												<div class="mb-3">
													<div class="date-f">
														<label class="form-label font-size-10"><?php echo $lang['Start Date']; ?></label>
														<input type="date" id="customStart" class="form-control">
													</div>
													<div class="date-f">
														<label class="form-label font-size-10"><?php echo $lang['End Date']; ?></label>
														<input type="date" id="customEnd" class="form-control">
													</div>
												</div>
												<div class="d-flex col-gap-10">
													<button class="primary-btn" id="primary-btn" onclick="selectCustomRange()"><?php echo $lang['Apply']; ?></button>
													<button class="btn border-btn-a" onclick="clearDateRange()"><?php echo $lang['Clear']; ?></button>
												</div>
											</div>
										</div>
									</ul>
								</div>
								</td>
							</div>
							<div class="edit-overview-btn d-none d-md-block profile-tasks-add-btn">
								<a href="add_task?source=profile&user_id=<?= $user_id ?>" class="primary-btn" target="_blank"><?php echo $lang['Add New Task']; ?> <?php echo ts_icon('plus', 'w-2'); ?></a>
							</div>
							<?php endif; ?>
							<?php if ($tab == 'notes'): ?>
							    <?php include __DIR__ . '/../templates/docs/profile-notes-toolbar-desktop.php'; ?>
							<?php endif; ?>								
							<?php if ($tab == 'media' && empty($profileMediaLocked)): ?>
							   <?php
							   $__pmView = (isset($_GET['view']) && (string) $_GET['view'] === 'table') ? 'table' : 'grid';
							   $__pmFolder = isset($_GET['folder']) ? (int) $_GET['folder'] : 0;
							   $__pmType = isset($_GET['file_type']) ? (string) $_GET['file_type'] : '';
							   $__pmSearch = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
							   $__pmBuildFilter = static function ($type = null) use ($user_id, $__pmView, $__pmFolder, $__pmSearch) {
								   $__q = ['user_id' => (int) $user_id, 'tab' => 'media', 'view' => $__pmView, 'page' => 1];
								   if ($__pmFolder > 0) { $__q['folder'] = $__pmFolder; }
								   if ($__pmSearch !== '') { $__q['search'] = $__pmSearch; }
								   if ($type !== null && in_array($type, ['image', 'document', 'other'], true)) { $__q['file_type'] = $type; }
								   return 'profile?' . http_build_query($__q, '', '&', PHP_QUERY_RFC3986);
							   };
							   ?>
							   <div class="edit-overview-btn d-none d-md-block kanban-header-filters">
							   		<td class="extra-height">
									<div class="action-toggle border-btn-a collapsed<?php echo ($__pmType !== '') ? ' has-active-filters' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#file-filters-menu" aria-expanded="false" role="button" tabindex="0">
										<span class="action-text"><?php echo $lang['Filters'] ?? 'Filters'; ?></span>
										<span class="mobile-ellipsis"><?php echo ts_icon('ellipsis', 'w-2'); ?></span>
										<?php echo ts_icon('filter', 'w-2'); ?>
										<?php if ($__pmType !== ''): ?>
										<a href="<?php echo htmlspecialchars($__pmBuildFilter(null), ENT_QUOTES, 'UTF-8'); ?>" class="kanban-filter-clear" title="<?php echo htmlspecialchars($lang['Clear'] ?? 'Clear', ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($lang['Clear filters'] ?? 'Clear filters', ENT_QUOTES, 'UTF-8'); ?>" onclick="event.stopPropagation();">
											<?php echo ts_icon('close'); ?>
										</a>
										<?php endif; ?>
									</div>
									<div id="file-filters-menu" class="toggle-action collapse shadow-dept">
										<ul>
											<li class="<?php echo $__pmType === '' ? 'active' : ''; ?>"><a href="<?php echo htmlspecialchars($__pmBuildFilter(null), ENT_QUOTES, 'UTF-8'); ?>"><span><?php echo $lang['All Files'] ?? 'All files'; ?></span></a></li>
											<li class="<?php echo $__pmType === 'image' ? 'active' : ''; ?>"><a href="<?php echo htmlspecialchars($__pmBuildFilter('image'), ENT_QUOTES, 'UTF-8'); ?>"><span><?php echo $lang['Images'] ?? 'Images'; ?></span></a></li>
											<li class="<?php echo $__pmType === 'document' ? 'active' : ''; ?>"><a href="<?php echo htmlspecialchars($__pmBuildFilter('document'), ENT_QUOTES, 'UTF-8'); ?>"><span><?php echo $lang['Documents'] ?? 'Documents'; ?></span></a></li>
											<li class="<?php echo $__pmType === 'other' ? 'active' : ''; ?>"><a href="<?php echo htmlspecialchars($__pmBuildFilter('other'), ENT_QUOTES, 'UTF-8'); ?>"><span><?php echo $lang['Other Files'] ?? 'Other files'; ?></span></a></li>
										</ul>
									</div>
									</td>
								</div>
								<div class="edit-overview-btn d-none d-md-block">
									<div id="primary-btn" class="action-toggle primary-btn collapsed" data-bs-toggle="collapse" data-bs-target="#dropdown-menu" aria-expanded="false" role="button" tabindex="0">
										<span class="action-text"><?php echo $lang['Create New'] ?? 'Create New'; ?></span>
										<?php echo ts_icon('plus', 'w-2'); ?>
									</div>
									<div id="dropdown-menu" class="toggle-action collapse shadow-dept">
										<ul>
											<li><a href="#" data-bs-toggle="modal" data-bs-target="#createFolderModal" onclick="return false;"><?php echo ts_icon('folder', 'tasksession-timer-log-menu-ico me-2'); ?><span><?php echo $lang['Create Folder'] ?? 'Create Folder'; ?></span></a></li>
											<li><a href="#" onclick="document.getElementById('uploadBtn').click(); return false;"><?php echo ts_icon('upload', 'tasksession-timer-log-menu-ico me-2'); ?><span><?php echo $lang['Upload Files'] ?? 'Upload Files'; ?></span></a></li>
										</ul>
									</div>
								</div>
							<?php endif; ?>
						</div>						
				   </div>
				</div>
		<?php if(isset($message) && (!empty($message))){echo $message;} ?>
							<?php
                                $user = User::findById($user_id);
                                if ($tab == 'overview' && $anyProjectsTasksModuleEnabled && $user) {
                                    // Get profile picture using global avatar function
                                    $avatarData = getUserAvatarData($user_id, $user->firstName, $user->lastName ?? '', 150, 150);
                                    $profilePic = $avatarData['type'] === 'image' ? $avatarData['url'] : '';
                                    // Get user statistics
                                    if ($user->accountStatus == 3 || $user->accountStatus == 1) { // staff or admin
                                        // Projects where staff/admin is assigned (s_ids contains user_id)
                                        $stmt = $connect->prepare("SELECT COUNT(*) as total FROM projects WHERE FIND_IN_SET(?, s_ids)");
                                        $stmt->bind_param("i", $user_id);
                                        $stmt->execute();
                                        $totalProjectsResult = $stmt->get_result();
                                        $totalProjects = $totalProjectsResult->fetch_assoc()['total'] ?? 0;
                                        $stmt->close();
                                        
                                        // Tasks where staff/admin is assigned (assigned_to contains user_id)
                                        $stmt = $connect->prepare("SELECT COUNT(*) as total FROM tasks WHERE FIND_IN_SET(?, assigned_to)");
                                        $stmt->bind_param("i", $user_id);
                                        $stmt->execute();
                                        $totalTasksResult = $stmt->get_result();
                                        $totalTasks = $totalTasksResult->fetch_assoc()['total'] ?? 0;
                                        $stmt->close();
                                    } else { // client
                                        $stmt = $connect->prepare("SELECT COUNT(*) as total FROM projects WHERE c_id = ?");
                                        $stmt->bind_param("i", $user_id);
                                        $stmt->execute();
                                        $totalProjectsResult = $stmt->get_result();
                                        $totalProjects = $totalProjectsResult->fetch_assoc()['total'] ?? 0;
                                        $stmt->close();
                                        
                                        // Total Tasks
                                        $stmt = $connect->prepare("SELECT p_id FROM projects WHERE c_id = ?");
                                        $stmt->bind_param("i", $user_id);
                                        $stmt->execute();
                                        $projectIdsResult = $stmt->get_result();
                                        $projectIds = [];
                                        while ($row = $projectIdsResult->fetch_assoc()) {
                                            $projectIds[] = $row['p_id'];
                                        }
                                        $stmt->close();
                                        
                                        $totalTasks = 0;
                                        if (!empty($projectIds)) {
                                            $placeholders = str_repeat('?,', count($projectIds) - 1) . '?';
                                            $types = str_repeat('i', count($projectIds));
                                            $stmt = $connect->prepare("SELECT COUNT(*) as cnt FROM tasks WHERE project_id IN ($placeholders)");
                                            $stmt->bind_param($types, ...$projectIds);
                                            $stmt->execute();
                                            $taskCountResult = $stmt->get_result();
                                            $taskCountRow = $taskCountResult->fetch_assoc();
                                            $totalTasks = $taskCountRow ? $taskCountRow['cnt'] : 0;
                                            $stmt->close();
                                        }
                                    }
                                    // Get payment statistics with currency filter
                                    $statsCurrencyFilter = isset($_GET['stats_currency']) ? $_GET['stats_currency'] : 'all';
                                    
                                    // Get task statistics
                                    if ($profileUser->accountStatus == 3 || $profileUser->accountStatus == 1) { // staff or admin
                                        $searchPattern = "%$user_id%";
                                        $stmt = $connect->prepare("SELECT COUNT(*) as total FROM tasks WHERE assigned_to LIKE ? AND status = 'inprogress'");
                                        $stmt->bind_param("s", $searchPattern);
                                        $stmt->execute();
                                        $inProgressResult = $stmt->get_result();
                                        $inProgressRow = $inProgressResult->fetch_assoc();
                                        $inProgressTasks = $inProgressRow ? $inProgressRow['total'] : 0;
                                        $stmt->close();
                                        
                                        $stmt = $connect->prepare("SELECT COUNT(*) as total FROM tasks WHERE assigned_to LIKE ? AND status = 'done'");
                                        $stmt->bind_param("s", $searchPattern);
                                        $stmt->execute();
                                        $completedResult = $stmt->get_result();
                                        $completedRow = $completedResult->fetch_assoc();
                                        $completedTasks = $completedRow ? $completedRow['total'] : 0;
                                        $stmt->close();
                                    } else { // client
                                        $stmt = $connect->prepare("SELECT p_id FROM projects WHERE c_id = ? OR main_client_id = ? OR FIND_IN_SET(?, c_ids) > 0");
                                        $stmt->bind_param("iii", $user_id, $user_id, $user_id);
                                        $stmt->execute();
                                        $projectIdsResult = $stmt->get_result();
                                        $projectIds = [];
                                        while ($row = $projectIdsResult->fetch_assoc()) {
                                            $projectIds[] = $row['p_id'];
                                        }
                                        $stmt->close();
                                        
                                        $inProgressTasks = 0;
                                        $completedTasks = 0;
                                        if (!empty($projectIds)) {
                                            $placeholders = str_repeat('?,', count($projectIds) - 1) . '?';
                                            $types = str_repeat('i', count($projectIds));
                                            
                                            $stmt = $connect->prepare("SELECT COUNT(*) as total FROM tasks WHERE project_id IN ($placeholders) AND status = 'inprogress'");
                                            $stmt->bind_param($types, ...$projectIds);
                                            $stmt->execute();
                                            $inProgressResult = $stmt->get_result();
                                            $inProgressRow = $inProgressResult->fetch_assoc();
                                            $inProgressTasks = $inProgressRow ? $inProgressRow['total'] : 0;
                                            $stmt->close();
                                            
                                            $stmt = $connect->prepare("SELECT COUNT(*) as total FROM tasks WHERE project_id IN ($placeholders) AND status = 'done'");
                                            $stmt->bind_param($types, ...$projectIds);
                                            $stmt->execute();
                                            $completedResult = $stmt->get_result();
                                            $completedRow = $completedResult->fetch_assoc();
                                            $completedTasks = $completedRow ? $completedRow['total'] : 0;
                                            $stmt->close();
                                        }
                                    }
                                    
                                    if ($profileUser->accountStatus == 3) {
                                        // Already set above in the if block, no need to query again
                                        // $inProgressTasks and $completedTasks are already set
                                    }
                                    
                                    // Get all currencies for this user (both project and direct client invoices)
                                    // Project invoices: only show for main client (not additional clients)
                                    $userCurrenciesQuery = "SELECT DISTINCT m.currency 
                                                           FROM milestones m 
                                                           LEFT JOIN projects p ON m.p_id = p.p_id 
                                                           WHERE ((m.p_id IS NOT NULL AND m.p_id > 0 AND p.p_id IS NOT NULL AND (p.c_id = $user_id OR p.main_client_id = $user_id)) OR ((m.p_id IS NULL OR m.p_id = 0) AND m.c_id = $user_id))
                                                           AND m.currency IS NOT NULL 
                                                           AND m.currency != ''
                                                           ORDER BY m.currency";
                                    $userCurrenciesResult = $database->query($userCurrenciesQuery);
                                    $userCurrencies = [];
                                    while ($row = $database->fetchArray($userCurrenciesResult)) {
                                        $userCurrencies[] = $row['currency'];
                                    }
                                    
                                    // If "all" is selected, we'll show separate rows for each currency
                                    if ($statsCurrencyFilter === 'all') {
                                        $currencyStats = [];
                                        foreach ($userCurrencies as $currency) {
                                            $cleanCurrency = explode(',', $currency)[0];
                                            $currencySql = $database->escapeValue($cleanCurrency);
                                            
                                            // Include both project invoices and direct client invoices
                                            // Project invoices: only show for main client (not additional clients)
                                            $whereConditions = ["((m.p_id IS NOT NULL AND m.p_id > 0 AND p.p_id IS NOT NULL AND (p.c_id = $user_id OR p.main_client_id = $user_id)) OR ((m.p_id IS NULL OR m.p_id = 0) AND m.c_id = $user_id))"];
                                            $whereConditions[] = "(m.currency LIKE '$currencySql,%' OR m.currency = '$currencySql' OR m.currency LIKE '%,$currencySql')";
                                            $whereClause = implode(' AND ', $whereConditions);
                                            
                                            // Calculate totals using calculateInvoiceTotal function
                                            $totalQuery = "SELECT m.* FROM milestones m 
                                                          LEFT JOIN projects p ON m.p_id = p.p_id 
                                                          WHERE $whereClause";
                                            $totalResult = $database->query($totalQuery);
                                            $total = 0;
                                            while ($row = $database->fetchArray($totalResult)) {
                                                $total += calculateInvoiceTotal($row);
                                            }
                                            
                                            $paidQuery = "SELECT m.* FROM milestones m 
                                                         LEFT JOIN projects p ON m.p_id = p.p_id 
                                                         WHERE $whereClause AND m.status = 1";
                                            $paidResult = $database->query($paidQuery);
                                            $paid = 0;
                                            while ($row = $database->fetchArray($paidResult)) {
                                                $paid += calculateInvoiceTotal($row);
                                            }
                                            $unpaid = $total - $paid;
                                            
                                            if ($total > 0) {
                                                $currencyStats[] = [
                                                    'currency' => $currency,
                                                    'symbol' => getCurrencySymbol($currency),
                                                    'total' => $total,
                                                    'paid' => $paid,
                                                    'unpaid' => $unpaid
                                                ];
                                            }
                                        }
                                    } else {
                                        // Single currency filter
                                        $cleanCurrencyFilter = explode(',', $statsCurrencyFilter)[0];
                                        $statsCurrencyFilterSql = $database->escapeValue($cleanCurrencyFilter);
                                        // Include both project invoices and direct client invoices
                                        // Project invoices: only show for main client (not additional clients)
                                        $statsWhereConditions = ["((m.p_id IS NOT NULL AND m.p_id > 0 AND p.p_id IS NOT NULL AND (p.c_id = $user_id OR p.main_client_id = $user_id)) OR ((m.p_id IS NULL OR m.p_id = 0) AND m.c_id = $user_id))"];
                                        $statsWhereConditions[] = "(m.currency LIKE '$statsCurrencyFilterSql,%' OR m.currency = '$statsCurrencyFilterSql' OR m.currency LIKE '%,$statsCurrencyFilterSql')";
                                        $statsWhereClause = implode(' AND ', $statsWhereConditions);
                                        
                                        // Calculate totals using calculateInvoiceTotal function
                                        $totalAmountQuery = "SELECT m.* FROM milestones m 
                                                           LEFT JOIN projects p ON m.p_id = p.p_id 
                                                           WHERE $statsWhereClause";
                                        $totalAmountResult = $database->query($totalAmountQuery);
                                        $totalAmount = 0;
                                        while ($row = $database->fetchArray($totalAmountResult)) {
                                            $totalAmount += calculateInvoiceTotal($row);
                                        }
                                        
                                        $paidAmountQuery = "SELECT m.* FROM milestones m 
                                                          LEFT JOIN projects p ON m.p_id = p.p_id 
                                                          WHERE $statsWhereClause AND m.status = 1";
                                        $paidAmountResult = $database->query($paidAmountQuery);
                                        $paidAmount = 0;
                                        while ($row = $database->fetchArray($paidAmountResult)) {
                                            $paidAmount += calculateInvoiceTotal($row);
                                        }
                                        $unpaidAmount = $totalAmount - $paidAmount;
                                        
                                        $currencySymbol = getCurrencySymbol($statsCurrencyFilter);
                                    }
                                ?>
					<div class="center-col max-width-850 pd-30 admin-dashboard">
                       <div class="row counter-align">
                            <?php
                            $overviewSmallCardClass = ($invoiceModuleEnabled && $projectsModuleEnabled && $tasksModuleEnabled)
                                ? 'col-lg-3 col-sm-6 col-6'
                                : 'col-xl-3 col-lg-6 col-md-6 col-sm-6 col-6';
                            $overviewInvoiceCardClass = ($projectsModuleEnabled && $tasksModuleEnabled)
                                ? 'col-lg-6 col-sm-12 col-12'
                                : 'col-xl-6 col-lg-6 col-md-12 col-sm-12 col-12';
                            ?>
                            <?php if ($projectsModuleEnabled): ?>
							<div class="<?php echo $overviewSmallCardClass; ?>"> 
                              <div class="widget-card dash-counter stat-spark-card stat-spark-card--clients widget-card--linked">    
							   <div class="grey"><span><?php echo $lang['Total Projects']; ?></span></div>
									<div class="counts dash-rttb"> <?php echo is_array($user_projects) ? count($user_projects) : 0; ?></div>
									<div class="grey persent-count">
										<?php
										if ($projectsPctChange > 0) {
											echo '<span style="color:green;">' . ts_icon('arrow-up-right', 'w-4') . ' ' . (int) $projectsPctChange . '%</span> vs last month';
										} elseif ($projectsPctChange < 0) {
											echo '<span style="color:red;">' . ts_icon('arrow-down-right', 'w-4') . ' ' . abs((int) $projectsPctChange) . '%</span> vs last month';
										} else {
											echo ts_icon('arrows-up-down', 'w-4') . ' 0% vs last month';
										}
										?>
									</div>
									<?php renderAdminStatSparkline('projects', $sparkProjects); ?>
									<a href="profile?user_id=<?php echo (int)$user_id; ?>&tab=projects" class="widget-card__stretch-link" aria-label="<?php echo htmlspecialchars($lang['View all'], ENT_QUOTES, 'UTF-8'); ?>"></a>
									</div>
                                    </div>
                            <?php endif; ?>
                            <?php if ($tasksModuleEnabled): ?>
								  <div class="<?php echo $overviewSmallCardClass; ?>"> 
							<div class="widget-card stat-spark-card stat-spark-card--staff widget-card--linked">    
							   <div class="grey"><span><?php echo $lang['Total Tasks']; ?></span></div>
									<div class="counts dash-rttb"><?php echo $totalTasks; ?></div>
									<div class="grey persent-count">
										<?php
										if ($tasksNewThisMonth > 0) {
											echo '<span style="color:green;">' . ts_icon('arrow-up-right', 'w-4') . ' ' . (int) $tasksNewThisMonth . ' new</span> · this month';
										} else {
											echo ts_icon('arrows-up-down', 'w-4') . ' No change · this month';
										}
										?>
									</div>
									<?php renderAdminStatSparkline('tasks', $sparkTasks); ?>
									<a href="profile?user_id=<?php echo (int)$user_id; ?>&tab=tasks" class="widget-card__stretch-link" aria-label="<?php echo htmlspecialchars($lang['View all'], ENT_QUOTES, 'UTF-8'); ?>"></a>
                                                </div>
                                                </div>
                            <?php endif; ?>
                                  <?php if ($invoiceModuleEnabled): ?>
								  <div class="<?php echo $overviewInvoiceCardClass; ?>"> 
							<div class="widget-card stat-spark-card stat-spark-card--invoice-overview widget-card--linked" id="invoice-overview-card">
								<div class="stat-invoice-head">
									<div class="grey"><span>Invoice Overview</span></div>
									<span class="stat-invoice-badge" id="invoice-overview-badge"><?php echo (int) $invoiceOverviewTotal; ?> total</span>
								</div>
								<div class="stat-invoice-grid">
									<div class="stat-invoice-col">
										<div class="counts dash-rttb is-paid mt-0" id="invoice-overview-paid-count"><?php echo (int) $user_paid_milestones_count; ?></div>
										<div class="grey persent-count" id="invoice-overview-paid-meta"><?php echo $lang['Paid Invoices']; ?></div>
									</div>
									<div class="stat-invoice-col">
										<div class="counts dash-rttb is-unpaid mt-0" id="invoice-overview-unpaid-count"><?php echo (int) $user_unpaid_milestones_count; ?></div>
										<div class="grey persent-count" id="invoice-overview-unpaid-meta"><?php echo $lang['Unpaid']; ?></div>
									</div>
								</div>
								<?php renderAdminInvoiceDualSparkline($sparkPaid, $sparkUnpaid); ?>
								<div class="stat-invoice-foot">
									<div class="stat-invoice-legend">
										<span><i class="dot bg-green"></i> Paid trend</span>
										<span><i class="dot bg-red"></i> Unpaid trend</span>
									</div>
									<div class="grey persent-count" id="invoice-overview-pct">
										<?php
										if ($invoicePaidPctChange > 0) {
											echo "<span style=\"color:green;\">+" . (int) $invoicePaidPctChange . "%</span> vs last month";
										} elseif ($invoicePaidPctChange < 0) {
											echo "<span style=\"color:red;\">" . abs((int) $invoicePaidPctChange) . "%</span> vs last month";
										} else {
											echo '0% vs last month';
										}
										?>
									</div>
								</div>
								<a href="profile?user_id=<?php echo (int)$user_id; ?>&tab=invoice" class="widget-card__stretch-link" aria-label="<?php echo htmlspecialchars($lang['View Invoices'], ENT_QUOTES, 'UTF-8'); ?>"></a>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!$invoiceModuleEnabled): ?>
                                        <div class="<?php echo $overviewInvoiceCardClass; ?>">
                                            <div class="widget-card stat-spark-card stat-spark-card--session widget-card--linked">
                                                <div class="grey"><span><?php echo $lang['Last Session']; ?></span></div>
                                                <div class="counts dash-rttb"><?php echo $lastSessionDateDisplay; ?></div>
                                                <div class="grey persent-count">
                                                    <?php if (!empty($lastSessionIsOnline)): ?>
                                                        <span class="stat-session-status is-online">
                                                            <?php echo ts_icon('dot', 'stat-session-dot-icon'); ?>
                                                            <span><?php echo htmlspecialchars($lang['Active'] ?? 'Active', ENT_QUOTES, 'UTF-8'); ?></span>
                                                        </span>
                                                    <?php else: ?>
                                                        <?php echo htmlspecialchars($lastSessionDurationDisplay, ENT_QUOTES, 'UTF-8'); ?>
                                                    <?php endif; ?>
                                                </div>
                                                <?php renderAdminStatSparkline('session', $sparkSession); ?>
                                                <a href="profile?user_id=<?php echo (int)$user_id; ?>&tab=activity" class="widget-card__stretch-link" aria-label="<?php echo htmlspecialchars($lang['Last Session'], ENT_QUOTES, 'UTF-8'); ?>"></a>
                                            </div>
                                        </div>
                                        <?php endif; ?>
									
									</div> 
									
                                    <?php if ($showSalesStatsWidget): ?>
                                    <div class="profile-content mb-4">
                                    <?php if ($salesStatsLocked && $isFreeEdition && function_exists('tasksession_render_pro_widget_overlay')): ?>
                                    <div class="widget-card sales-stats-pro-lock">
                                        <?php tasksession_render_pro_widget_overlay('payments'); ?>
                                    </div>
                                    <?php else: ?>
                                    <div class="widget-card <?php echo $salesStatsLocked ? 'sales-stats-disabled' : ''; ?>">
                                    <?php
                                    if ($salesStatsLocked) {
                                            ?>
                                            <div class="sales-stats-overlay">
                                                <div class="overlay-card">
                                                    <h3><?php echo htmlspecialchars($lang['Payments module disabled'] ?? 'Payments module disabled', ENT_QUOTES, 'UTF-8'); ?></h3>
                                                    <p><?php echo htmlspecialchars($lang['invoice_reports_module_overlay'] ?? 'Enable invoicing and payment features to manage your business transactions efficiently.', ENT_QUOTES, 'UTF-8'); ?></p>
                                                    <a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>admin/system-settings" class="primary-btn"><?php echo htmlspecialchars($lang['Enable Now'] ?? 'Enable Now', ENT_QUOTES, 'UTF-8'); ?></a>
                                                </div>
                                            </div>
                                            <?php
                                    }
                                    ?>
                                    <!-- Financials Section -->
                                    <div class="financials-section sales-stats-content">
                                      <div class="d-flex justify-content-between align-items-center mb-2">

                                            <div class="card-title">
                                                <h3><?php echo $lang['Sales Statistics']; ?></h3>
                                                    </div>

                                            <div class="d-flex align-items-center col-gap-10">
                                        <!-- Currency Filter Dropdown -->
                                        <div class="dropdown-btn">
                                            <div class="dropdown">
                                    <?php
                                                // Currency source depends on profile type
                                                if (isset($profileUser) && ((int)$profileUser->accountStatus === 3 || (int)$profileUser->accountStatus === 1)) {
                                                    // Staff profile: show all currencies enabled by admin in system settings
                                                    $settings = settings::findById(1);
                                                    $enabledCurrencies = is_array($settings->getMultipleCurrencies()) ? $settings->getMultipleCurrencies() : [];
                                                    $orderedCurrencies = profile_sales_order_currencies($enabledCurrencies, $settings);
                                                } else {
                                                    // Client profile: currencies used by this user's milestones only
                                                    // Include both project invoices and direct client invoices
                                                    // Project invoices: only show for main client (not additional clients)
                                                    $userCurrenciesQuery = "SELECT DISTINCT m.currency 
                                                           FROM milestones m 
                                                           LEFT JOIN projects p ON m.p_id = p.p_id 
                                                           WHERE ((m.p_id IS NOT NULL AND m.p_id > 0 AND p.p_id IS NOT NULL AND (p.c_id = $user_id OR p.main_client_id = $user_id)) OR ((m.p_id IS NULL OR m.p_id = 0) AND m.c_id = $user_id))
                                                           AND m.currency IS NOT NULL 
                                                           AND m.currency != ''
                                                           ORDER BY m.currency";
                                                    $userCurrenciesResult = $database->query($userCurrenciesQuery);
                                                    $userCurrencies = [];
                                                    while ($row = $database->fetchArray($userCurrenciesResult)) {
                                                        $userCurrencies[] = $row['currency'];
                                                    }
                                                    
                                                    // Get user's preferred currency (from add-client.php logic)
                                                    $userPreferredCurrency = $profileUser->currency ?: 'USD,$';
                                                    
                                                    // If user has invoices, prioritize their preferred currency
                                                    $orderedCurrencies = [];
                                                    if ($userPreferredCurrency && in_array($userPreferredCurrency, $userCurrencies)) {
                                                        $orderedCurrencies[] = $userPreferredCurrency;
                                                    }
                                                    foreach ($userCurrencies as $currency) {
                                                        if ($currency !== $userPreferredCurrency) {
                                                            $orderedCurrencies[] = $currency;
                                                        }
                                                    }
                                                }

                                                $firstSalesCurrency = profile_sales_default_currency_option($orderedCurrencies ?? [], $settings ?? null, $userPreferredCurrency ?? '');
                                                $defaultCurrency = $firstSalesCurrency['value'];
                                                $defaultCurrencyDisplay = $firstSalesCurrency['display'];
                                                ?>
                                                <button class="btn btn-light dropdown-toggle" type="button" id="currencyFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <?php echo $defaultCurrencyDisplay; ?>
                                                </button>
                                                <ul class="dropdown-menu p-3" aria-labelledby="currencyFilterDropdown" style="min-width: 200px;">
                                                    <?php
                                                    foreach ($orderedCurrencies as $currency) {
                                                        // For staff, $currency is a code like "USD"; for clients it may be "USD,$"
                                                        $currency = trim($currency);
                                                        if (!empty($currency)) {
                                                            $currencyCode = $currency;
                                                            $currencySymbol = '';
                                                            if (strpos($currency, ',') !== false) {
                                                                $currencyParts = explode(',', $currency);
                                                                $currencyCode = trim($currencyParts[0]);
                                                                $currencySymbol = isset($currencyParts[1]) ? trim($currencyParts[1]) : $currencyCode;
                                                            } else {
                                                                // Lookup symbol from settings when only code is given
                                                                if (!isset($settings)) { $settings = settings::findById(1); }
                                                                $symbolText = $settings->currency_symbols[$currencyCode] ?? $currencyCode;
                                                                // Extract the symbol between parentheses from text like USD($)
                                                                if (preg_match('/\((.*?)\)/', $symbolText, $m)) {
                                                                    $currencySymbol = $m[1];
                                                                } else {
                                                                    $currencySymbol = $currencyCode;
                                                                }
                                                            }
                                                            
                                                            // Get country name for display
                                                            $countryName = '';
                                                            switch ($currencyCode) {
                                                                case 'USD': $countryName = 'United States'; break;
                                                                case 'PKR': $countryName = 'Pakistan'; break;
                                                                case 'Rs':  $countryName = 'Pakistan'; break;
                                                                case 'CAD': $countryName = 'Canada'; break;
                                                                case 'EUR': $countryName = 'European Union'; break;
                                                                case 'GBP': $countryName = 'United Kingdom'; break;
                                                                case 'INR': $countryName = 'India'; break;
                                                                case 'JPY': $countryName = 'Japan'; break;
                                                                case 'AUD': $countryName = 'Australia'; break;
                                                                case 'CHF': $countryName = 'Switzerland'; break;
                                                                case 'CNY': $countryName = 'China'; break;
                                                                default:    $countryName = $currencyCode;
                                                            }
                                                            
                                                            $displayName = $countryName . ' (' . $currencySymbol . ')';
                                                            $encodedValue = (strpos($currency, ',') !== false) ? $currency : ($currencyCode . ',' . $currencySymbol);
                                                            echo '<li><button class="dropdown-item" type="button" onclick="selectCurrency(\'' . $encodedValue . '\')">' . $displayName . '</button></li>';
                                                        }
                                                    }
                                                    ?>
                                                </ul>
                                                    </div>
                                                    </div>
                                                
                                                <!-- Date Range Dropdown -->
                                                <div class="dropdown-btn">
                                                    <div class="dropdown">
                                                        <button class="btn btn-light dropdown-toggle" type="button" id="customRangeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <?php echo $lang['This Year (Jan - Dec)']; ?>
                                                        </button>
                                                        <ul class="dropdown-menu p-3" aria-labelledby="customRangeDropdown" style="min-width: 300px;">
                                                            <li><button class="dropdown-item" type="button" onclick="selectQuickRange('last7')"><?php echo $lang['Last 7 days']; ?></button></li>
                                                            <li><button class="dropdown-item" type="button" onclick="selectQuickRange('last30')"><?php echo $lang['Last 30 days']; ?></button></li>
                                                            <li><button class="dropdown-item" type="button" onclick="selectQuickRange('last90')"><?php echo $lang['Last 90 days']; ?></button></li>
                                                            <li><button class="dropdown-item" type="button" onclick="selectQuickRange('last6months')"><?php echo $lang['Last 6 months']; ?></button></li>
                                                            <li><button class="dropdown-item" type="button" onclick="selectQuickRange('thisYear')"><?php echo $lang['This Year (Jan - Dec)']; ?></button></li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li>
                                                                <div class="px-2 sales-stats-custom-range">
                                                                    <label><?php echo $lang['Custom']; ?></label>
                                                                    <input type="date" id="customStart" class="form-control mb-2">
                                                                    <input type="date" id="customEnd" class="form-control mb-2">
                                                                    <button class="primary-btn w-100" type="button" onclick="selectCustomRange()"> <?php echo $lang['Apply']; ?></button>
																</div>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                         </div>
                                        <div class="monthly-rev mb-4">
                                          <div class="task-reports-bar-summary sales-stats-bar-summary" id="salesStatsBarSummary">
                                            <div class="month-rps flex-grow invoice-financial-summary-main" id="month-rps">
                                                <div class="invoice-financial-summary-item is-chart-toggle" data-dataset-index="0" role="button" tabindex="0" title="<?php echo htmlspecialchars($lang['Paid'] ?? 'Paid', ENT_QUOTES, 'UTF-8'); ?>">
                                                    <span class="invoice-financial-summary-label invoice-financial-summary-label--paid"><?php echo $lang['Paid'] ?? 'Paid'; ?></span>
                                                    <span class="invoice-financial-summary-value" id="salesStatsPaid">—</span>
                                                </div>
                                                <div class="invoice-financial-summary-item is-chart-toggle" data-dataset-index="1" role="button" tabindex="0" title="<?php echo htmlspecialchars($lang['Unpaid'] ?? 'Unpaid', ENT_QUOTES, 'UTF-8'); ?>">
                                                    <span class="invoice-financial-summary-label invoice-financial-summary-label--unpaid"><?php echo $lang['Unpaid'] ?? 'Unpaid'; ?></span>
                                                    <span class="invoice-financial-summary-value" id="salesStatsUnpaid">—</span>
                                                </div>
                                                <div class="invoice-financial-summary-item">
                                                    <span class="invoice-financial-summary-label invoice-financial-summary-label--tax"><?php echo $lang['Sales Tax'] ?? 'Sales Tax'; ?></span>
                                                    <span class="invoice-financial-summary-value" id="salesStatsTax">—</span>
                                                </div>
                                                <div class="invoice-financial-summary-item is-chart-toggle" data-dataset-index="2" role="button" tabindex="0" title="<?php echo htmlspecialchars($lang['Cancel'] ?? 'Cancel', ENT_QUOTES, 'UTF-8'); ?>">
                                                    <span class="invoice-financial-summary-label invoice-financial-summary-label--cancelled"><?php echo $lang['Cancel'] ?? 'Cancel'; ?></span>
                                                    <span class="invoice-financial-summary-value" id="salesStatsCancelled">—</span>
                                                </div>
                                            </div>
                                            <div class="task-reports-bar-summary-meta" id="salesStatsBarSummaryMeta">
                                                <span class="task-reports-bar-summary-arrow" id="salesStatsBarSummaryArrow"></span>
                                                <span class="task-reports-bar-summary-pct" id="salesStatsBarSummaryPct"></span>
                                                <span class="task-reports-bar-summary-sep" id="salesStatsBarSummarySep" aria-hidden="true">·</span>
                                                <span class="task-reports-bar-summary-vs" id="salesStatsBarSummaryVs"></span>
                                            </div>
                                          </div>
                                        </div>
											<div class="stats-graph">
												<canvas id="earningsLineChart" height="300"></canvas>
                                            </div>
                                           </div>
										 </div>
                                    </div>
                                    <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
						<?php if ($projectsModuleEnabled): ?>
						<div class="profile-content">
                            <div class="db-box-wrap ">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div class="card-title">
                                        <h3><?php echo $lang['Running Projects']; ?></h3>
                                                </div>
                                    <div class="border-btn">
                                        <a href="<?php echo $url; ?>admin/profile?user_id=<?php echo $user_id; ?>&tab=projects">
                                            <?php echo $lang['View all']; ?>
                                        </a>
                                            </div>
                                        </div>
                                            <div class="col-12">
								                                    <?php 
                                    // Get user-specific projects instead of all projects
                                    if ($profileUser->accountStatus == 3 || $profileUser->accountStatus == 1) {
                                        // Staff or Admin - projects where they are assigned (s_ids contains user_id)
                                        $recentProjects = projects::findBySql("SELECT * FROM projects WHERE FIND_IN_SET($user_id, s_ids) AND archive=0 AND trash != 1 ORDER BY start_time DESC LIMIT 5");
                                    } else {
                                        // Client - projects where they are main client or additional client
                                        $recentProjects = projects::findBySql("SELECT * FROM projects WHERE (c_id = $user_id OR main_client_id = $user_id OR FIND_IN_SET($user_id, c_ids) > 0) AND archive=0 AND trash != 1 ORDER BY start_time DESC LIMIT 5");
                                    }
                                    ?>
                                    <div class="row"> <div class="d-flex col-gap-20 xx">
                                        <?php if (empty($recentProjects)): ?>
                                            <div class="col-12">
                                                <div class="text-center text-muted" style="padding: 20px 10px;">
                                                    <?php echo $lang['No projects found.']; ?>
                                            </div>
                                        </div>
                                        <?php else: ?>
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
                                                <div class="project-x">                               
                                                    <div class="card project-card" style="position: relative;">
                                                        <!-- 3-dot dropdown menu -->
                                                        <div class="dropdown card-action-dropdown" style="position: absolute; top: 12px; right: 16px;">
                                                            <button class="btn btn-link dropdown-toggle" type="button" id="dropdownMenu<?php echo $recentProject->p_id; ?>" data-bs-toggle="dropdown" aria-expanded="false" style="color: #333; font-size: 20px; text-decoration: none;">
                                                                <span class="light-grey" style="font-size: 20px; letter-spacing: &#8226;">&#8226;&#8226;&#8226;</span>
                                                            </button>
                                                            <ul class="dropdown-menu" aria-labelledby="dropdownMenu<?php echo $recentProject->p_id; ?>">
                                                                <li>
                                                                    <a class="dropdown-item" href="overview?projectId=<?php echo $recentProject->p_id; ?>">
                                                        <?php echo ts_icon('clock', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Overview']; ?>
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                    <form action="../discussion?project_id=<?php echo $recentProject->p_id;?>" method="post" style="display:inline;">
                                                                        <input type="hidden" name="user_id" value="<?php echo $recentProject->c_id;?>" />
                                                                        <input type="hidden" name="project_id" value="<?php echo $recentProject->p_id;?>" />
                                                                        <button type="submit" name="chat" class="dropdown-item">
                                                                          <?php echo ts_icon('chat', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Discussion']; ?>
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                                <li>
                                                                    <a class="dropdown-item" href="task?projectId=<?php echo $recentProject->p_id; ?>">
                                                                        <?php echo ts_icon('tasks', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Tasks']; ?>
                                                                    </a>
                                                                </li>
                                                                 <li>
                                                                     <a href="edit-project?id=<?php echo $recentProject->p_id;?>" class="dropdown-item"><?php echo ts_icon('edit', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Edit Project']; ?></a>
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
                                                            <div class="d-flex col-gap-35 mb-4 flex-wrap" style="text-align: left;">
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
                                                                            echo getUserAvatarHtml($st_id, $user2->firstName, $user2->lastName ?? '', 36, 36, 'img-fluid rounded-circle', $user2->firstName);
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
                                                </div>
                                            </div>
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
                                                    </div>
                                                </div>
                                                            <!-- Progress Bar and Task Completion (dynamic) -->
                                                            <div class="progress mb-2" style="height:8px;">
                                                                <div class="progress-bar" role="progressbar" style="width: <?php echo $percent; ?>%; background: <?php echo $percent < 30 ? '#f66' : ($percent < 70 ? '#f9b233' : '#4caf50'); ?>;" aria-valuenow="<?php echo $percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                                            <div class="mb-2 d-flex col-gap-5"><div class="grey bold"><?php echo $lang['Tasks']; ?></div> <?php echo $completedTaskCount; ?>/<?php echo $taskCount; ?> <span class="text-align-right flex-grow"><?php echo $percent; ?>%</span></div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                                    </div>
                                                    </div>
                                                </div>
                                            </div>
                                          </div>
									
                                </div>  
						<?php endif; ?>
                                <?php } ?> 
                            <?php if ($tab == 'invoice'): ?>
                            <?php if (!empty($profileInvoiceLocked) && function_exists('tasksession_render_pro_upgrade_embedded')): ?>
                                <?php tasksession_render_pro_upgrade_embedded('invoices'); ?>
                            <?php else: ?>
                            <div class="row vh-100">
                                <div class="container-fluid vh-100">
                                    <div class="row vh-100">
                                        <div class="col-12">
                                            
                                            <?php
                                            // Invoice tab flash → toast (no page alert banners)
                                            if (isset($_GET['message'])) {
                                                $message = $_GET['message'];
                                                if ($message == 'deleted') {
                                                    $toast_flash = array('msg' => 'Success! Milestone has been deleted successfully.', 'type' => 'success');
                                                } elseif ($message == 'updated') {
                                                    $toast_flash = array('msg' => 'Success! Milestone has been updated successfully.', 'type' => 'success');
                                                } elseif ($message == 'marked_as_paid') {
                                                    $toast_flash = array(
                                                        'msg' => 'Success! ' . (isset($lang['Invoice marked as paid successfully']) ? $lang['Invoice marked as paid successfully'] : 'Invoice marked as paid successfully'),
                                                        'type' => 'success',
                                                    );
                                                } elseif ($message == 'cancelled') {
                                                    $toast_flash = array(
                                                        'msg' => 'Success! ' . (isset($lang['Invoice cancelled successfully']) ? $lang['Invoice cancelled successfully'] : 'Invoice cancelled successfully'),
                                                        'type' => 'success',
                                                    );
                                                } elseif ($message == 'update_failed') {
                                                    $toast_flash = array(
                                                        'msg' => 'Error! ' . (isset($lang['Failed to update invoice']) ? $lang['Failed to update invoice'] : 'Failed to update invoice'),
                                                        'type' => 'error',
                                                    );
                                                } elseif ($message == 'delete_failed') {
                                                    $toast_flash = array('msg' => 'Error! Failed to delete milestone. Please try again.', 'type' => 'error');
                                                } elseif ($message == 'not_found') {
                                                    $toast_flash = array('msg' => 'Warning! Milestone not found.', 'type' => 'info');
                                                }
                                            }
                                            ?>

                                            
                                            <div class="row  pd-30" id="invoice-grid">
                                                <?php
                                                $search = isset($_GET['search']) ? trim($_GET['search']) : '';
                                                $currencyFilter = isset($_GET['currency_filter']) ? $_GET['currency_filter'] : 'all';
                                                
                                                // Build the WHERE clause based on user role
                                                if ((int)$profileUser->accountStatus == 3 || (int)$profileUser->accountStatus == 1) { // Staff or Admin
                                                    $whereConditions = ["m.created_by = $user_id"];
                                                } else { // Client - include both project invoices and direct client invoices
                                                    // For project invoices: only show for main client (not additional clients)
                                                    // For direct client invoices: check m.c_id matches user_id AND p_id is NULL/0
                                                    // Ensure we only show invoices for THIS specific client
                                                    $whereConditions = [
                                                        "(" .
                                                        "(m.p_id IS NOT NULL AND m.p_id > 0 AND p.p_id IS NOT NULL AND (p.c_id = $user_id OR p.main_client_id = $user_id))" .
                                                        " OR " .
                                                        "((m.p_id IS NULL OR m.p_id = 0) AND m.c_id = $user_id)" .
                                                        ")"
                                                    ];
                                                }
                                                
                                                // Build WHERE conditions and parameters for prepared statement
                                                $whereParts = [];
                                                $whereParams = [];
                                                $whereTypes = '';
                                                
                                                // Base condition
                                                $whereParts[] = "((m.p_id IS NOT NULL AND m.p_id > 0 AND p.p_id IS NOT NULL AND (p.c_id = ? OR p.main_client_id = ?)) OR ((m.p_id IS NULL OR m.p_id = 0) AND m.c_id = ?))";
                                                $whereParams[] = $user_id;
                                                $whereParams[] = $user_id;
                                                $whereParams[] = $user_id;
                                                $whereTypes .= 'iii';
                                                
                                                if (!empty($search)) {
                                                    $searchPattern = "%{$search}%";
                                                    // Handle NULL project titles for client invoices
                                                    if ((int)$profileUser->accountStatus == 2) { // Client
                                                        $whereParts[] = "(m.title LIKE ? OR (p.project_title IS NOT NULL AND p.project_title LIKE ?))";
                                                        $whereParams[] = $searchPattern;
                                                        $whereParams[] = $searchPattern;
                                                        $whereTypes .= 'ss';
                                                    } else {
                                                        $whereParts[] = "(m.title LIKE ? OR p.project_title LIKE ?)";
                                                        $whereParams[] = $searchPattern;
                                                        $whereParams[] = $searchPattern;
                                                        $whereTypes .= 'ss';
                                                    }
                                                }
                                                
                                                if ($currencyFilter !== 'all') {
                                                    // Clean the currency filter to get just the code
                                                    $cleanCurrencyFilter = explode(',', $currencyFilter)[0];
                                                    $currencyPattern1 = "{$cleanCurrencyFilter},%";
                                                    $currencyPattern2 = "%,{$cleanCurrencyFilter}";
                                                    // Match both formats: "PKR,Rs" and "PKR"
                                                    $whereParts[] = "(m.currency LIKE ? OR m.currency = ? OR m.currency LIKE ?)";
                                                    $whereParams[] = $currencyPattern1;
                                                    $whereParams[] = $cleanCurrencyFilter;
                                                    $whereParams[] = $currencyPattern2;
                                                    $whereTypes .= 'sss';
                                                }
                                                
                                                $whereClause = implode(' AND ', $whereParts);
                                                
                                                // Build query based on user role
                                                if ((int)$profileUser->accountStatus == 3 || (int)$profileUser->accountStatus == 1) { // Staff or Admin
                                                    $milestonesQuery = "SELECT m.*, m.currency, m.sales_tax, m.sales_tax_type, m.discount, m.discount_type, p.project_title FROM milestones m LEFT JOIN projects p ON m.p_id = p.p_id WHERE $whereClause ORDER BY m.deadline DESC";
                                                } else { // Client - use LEFT JOIN to include direct client invoices
                                                    $milestonesQuery = "SELECT m.*, m.currency, m.sales_tax, m.sales_tax_type, m.discount, m.discount_type, p.project_title FROM milestones m LEFT JOIN projects p ON m.p_id = p.p_id WHERE $whereClause ORDER BY m.deadline DESC";
                                                }
                                                
                                                $stmt = $connect->prepare($milestonesQuery);
                                                if ($stmt) {
                                                    $stmt->bind_param($whereTypes, ...$whereParams);
                                                    $stmt->execute();
                                                    $milestonesResult = $stmt->get_result();
                                                    $milestones = [];
                                                    while ($row = $milestonesResult->fetch_assoc()) {
                                                        $milestones[] = $row;
                                                    }
                                                    $stmt->close();
                                                } else {
                                                    $milestones = [];
                                                }
                                                if (empty($milestones)) {
                                                    echo '<div class="col-12"><div class="empty-box">' . $lang['No invoices found'] . '</div></div>';
                                                }
                                                foreach ($milestones as $milestone) {
                                                ?>
                                                <div class="col-md-6 col-lg-6 col-xl-4">
                                                    <div class="card shadow-sm card-style">
                                                        <div class="card-body">
                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                <span class="size-12 badge <?php 
                                                                    if ($milestone['status'] == 1) {
                                                                        echo 'success';
                                                                    } elseif ($milestone['status'] == 2) {
                                                                        echo '';
                                                                    } else {
                                                                        echo 'red-badge';
                                                                    }
                                                                ?>" style="<?= $milestone['status']==2 ? 'background-color: #6c757d; color: white; text-transform: uppercase;' : ''; ?>">
                                                                    <?php 
                                                                    if ($milestone['status'] == 1) {
                                                                        echo $lang['Paid'];
                                                                    } elseif ($milestone['status'] == 2) {
                                                                        echo $lang['Cancel'];
                                                                    } else {
                                                                        echo $lang['Unpaid'];
                                                                    }
                                                                    ?>
                                                                </span>
                                                                <div class="dropdown">
                                                                    <button class="btn-dots" type="button" id="dropdownMenu<?php echo $milestone['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                                       <?php echo ts_icon('dots-vertical', 'w-6'); ?>
                                                                    </button>
                                                                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenu<?php echo $milestone['id']; ?>">
                                                                        <?php if ($milestone['status'] == 1): ?>
                                                                        <li>
                                                                            <form action="<?php echo htmlspecialchars(tasksession_download_pdf_href(), ENT_QUOTES, 'UTF-8'); ?>" method="post" target="_blank" enctype="multipart/form-data">
                                                                                <input type="hidden" value="<?php echo $milestone['id']; ?>" name="milestone_id" />
                                                                                <button type="submit" name="mile_submit" class="dropdown-item">
                                                                                    <?php echo ts_icon('download', 'me-2 tasksession-timer-log-menu-ico'); ?>
                                                                                    <?php echo isset($lang['Download PDF']) ? $lang['Download PDF'] : 'Download PDF'; ?>
                                                                                </button>
                                                                            </form>
                                                                        </li>
                                                                        <?php else: // Unpaid invoice - show all options except View Project for direct client invoices ?>
                                                                        <?php if (!empty($milestone['p_id']) && $milestone['p_id'] > 0): // Only show "View Project" for project invoices ?>
                                                                        <li>
                                                                            <a class="dropdown-item" href="payments?projectId=<?php echo $milestone['p_id']; ?>">
                                                                                <?php echo ts_icon('archive', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['View Project']; ?>
                                                                            </a>
                                                                        </li>
                                                                        <?php endif; ?>
                                                                        <li>
                                                                            <a href="edit-invoice?id=<?php echo $milestone['id']; ?>&return_url=<?php echo urlencode('profile?user_id=' . $user_id . '&tab=invoice'); ?>" class="dropdown-item">
                                                                                <?php echo ts_icon('edit', 'tasksession-timer-log-menu-ico me-2'); ?> <?php echo $lang['Edit Invoice']; ?>
                                                                            </a>
                                                                        </li>
                                                                        <li>
                                                                            <form method="post" action="#" class="d-inline">
                                                                                <input type="hidden" name="invoice_id" value="<?php echo $milestone['id']; ?>">
                                                                                <button type="submit" class="dropdown-item text-success" name="mark_as_paid" onclick="return confirm('<?php echo isset($lang['Are you sure you want to mark this invoice as paid?']) ? $lang['Are you sure you want to mark this invoice as paid?'] : 'Are you sure you want to mark this invoice as paid?'; ?>');">
                                                                                    <?php echo ts_icon('check-circle', 'me-2 tasksession-timer-log-menu-ico'); ?>
                                                                                    <?php echo isset($lang['Mark as Paid']) ? $lang['Mark as Paid'] : 'Mark as Paid'; ?>
                                                                                </button>
                                                                            </form>
                                                                        </li>
                                                                        <?php if ($milestone['status'] != 2): // Only show "Cancel Invoice" for unpaid invoices (not cancelled) ?>
                                                                        <li>
                                                                            <form method="post" action="#" class="d-inline">
                                                                                <input type="hidden" name="invoice_id" value="<?php echo $milestone['id']; ?>">
                                                                                <button type="submit" class="dropdown-item text-warning" name="cancel_invoice" onclick="return confirm('<?php echo isset($lang['Are you sure you want to cancel this invoice?']) ? $lang['Are you sure you want to cancel this invoice?'] : 'Are you sure you want to cancel this invoice?'; ?>');">
                                                                                    <?php echo ts_icon('close', 'me-2 tasksession-timer-log-menu-ico'); ?>
                                                                                    <?php echo isset($lang['Cancel Invoice']) ? $lang['Cancel Invoice'] : 'Cancel Invoice'; ?>
                                                                                </button>
                                                                            </form>
                                                                        </li>
                                                                        <?php endif; ?>
                                                                        <?php if ($milestone['status'] != 2): // Only show "Copy payment link" for unpaid invoices (not cancelled) ?>
                                                                        <li>
                                                                            <a href="#" class="dropdown-item" onclick="copyPaymentLink(<?php echo $milestone['id']; ?>); return false;">
                                                                                <?php echo ts_icon('link', 'me-2 tasksession-timer-log-menu-ico'); ?>
                                                                                <?php echo isset($lang['Copy payment link']) ? $lang['Copy payment link'] : 'Copy payment link'; ?>
                                                                            </a>
                                                                        </li>
                                                                        <?php endif; ?>
                                                                        <li>
                                                                            <form action="<?php echo htmlspecialchars(tasksession_download_pdf_href(), ENT_QUOTES, 'UTF-8'); ?>" method="post" target="_blank" enctype="multipart/form-data">
                                                                                <input type="hidden" value="<?php echo $milestone['id']; ?>" name="milestone_id" />
                                                                                <button type="submit" name="mile_submit" class="dropdown-item">
                                                                                    <?php echo ts_icon('download', 'me-2 tasksession-timer-log-menu-ico'); ?>
                                                                                    <?php echo isset($lang['Download PDF']) ? $lang['Download PDF'] : 'Download PDF'; ?>
                                                                                </button>
                                                                            </form>
                                                                        </li>
                                                                        <li>
                                                                            <form method="post" action="#">
                                                                                <input type="hidden" value="<?php echo $milestone['id'];?>" name="delete_id" />
                                                                                <button type="submit" class="dropdown-item text-danger" name="delete-mile" onclick="return confirm('Are you sure you want to delete this milestone?');">
                                                                                    <?php echo ts_icon('delete', 'tasksession-timer-log-menu-ico me-2'); ?> <?php echo $lang['Delete']; ?>
                                                                                </button>
                                                                            </form>
                                                                        </li>
                                                                        <?php endif; ?>
                                                                    </ul>
                                                                </div>
                                                            </div>
                                                            <h5 class="card-title grey"><?php echo htmlspecialchars($milestone['title']); ?></h5>
                                                            <h3 class="big-text"><?php 
                                                                $totalAmount = calculateInvoiceTotal($milestone);
                                                                echo getCurrencySymbol($milestone['currency']) . number_format($totalAmount, 2); 
                                                            ?></h3>
                                                            <div class="task-date grey">
                                                                <div><b class="dark"><?php echo $lang['Due date']; ?>:</b> <?php echo $milestone['deadline']; ?></div>
                                                                <div><b class="dark"><?php echo $lang['Invoice']; ?>: #</b> <?php echo $milestone['p_id'] . $milestone['id']; ?></div>
                                                                <div><b class="dark"><?php echo $lang['Invoice type']; ?>:</b> 
                                                                    <?php if (isset($milestone['is_recurring']) && $milestone['is_recurring'] == 1): ?>
                                                                        <?php echo $lang['Recurring Invoice']; ?>
                                                                    <?php else: ?>
                                                                        <?php echo $lang['Single Invoice']; ?>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                            
                                                            <?php
                                                            // Get the user who created the invoice
                                                            $creator = User::findById($milestone['created_by']);
                                                            if ($creator) {
                                                                $creatorName = trim($creator->firstName . ' ' . $creator->lastName);
                                                                $avatarData = getUserAvatarData($milestone['created_by'], $creator->firstName, $creator->lastName ?? '', 30, 30);
                                                                
                                                                // Use the same color index logic as sidebar for consistency
                                                                $colorIndex = ($milestone['created_by'] % 8) + 1;
                                                            ?>
                                                            <div class="invoice-created mt-2 mb-2">
                                                                <div class="avatar-wrapper d-flex align-items-center">
                                                                    <?php if ($avatarData['type'] === 'image' && !empty($avatarData['url'])): ?>
                                                                        <img src="<?php echo $avatarData['url']; ?>" alt="<?php echo htmlspecialchars($creatorName); ?>" title="<?php echo htmlspecialchars($creatorName); ?>" class="avatar rounded-circle me-2" style="width: 30px; height: 30px; object-fit: cover;">
                                                                    <?php else: ?>
                                                                        <div class="avatar-initials color-<?php echo $colorIndex; ?> rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; color: white; font-weight: bold; font-size: 12px;" title="<?php echo htmlspecialchars($creatorName); ?>">
                                                                            <?php echo $avatarData['initials']; ?>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                    <span class="avatar-name"><?php echo htmlspecialchars($creatorName); ?></span>
                                                                </div>
                                                            </div>
                                                            <?php } ?>
                                                            <div>
                                                                <button type="button"
                                                                        class="btn btn-outline-grey client-invoice-view-trigger"
                                                                        data-invoice-id="<?php echo (int) $milestone['id']; ?>">
                                                                    <?php echo $lang['View invoice']; ?>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php endif; ?>
							   <?php if ($tab == 'projects'): ?>
                                    <div class="row" id="project-grid">
                                        <?php
                                        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
                                        if ($user->accountStatus == 3 || $user->accountStatus == 1) { // staff or admin
                                            if (!empty($search)) {
                                                $searchPattern = "%{$search}%";
                                                $stmt = $connect->prepare("SELECT * FROM projects WHERE FIND_IN_SET(?, s_ids) AND archive = 0 AND trash != 1 AND project_title LIKE ?");
                                                $stmt->bind_param("is", $user_id, $searchPattern);
                                                $stmt->execute();
                                                $result = $stmt->get_result();
                                                $userProjects = [];
                                                while ($row = $result->fetch_assoc()) {
                                                    $userProjects[] = projects::instantiate($row);
                                                }
                                                $stmt->close();
                                            } else {
                                                $userProjects = projects::findBySql("SELECT * FROM projects WHERE FIND_IN_SET($user_id, s_ids) AND archive = 0 AND trash != 1");
                                            }
                                        } else { // client
                                            if (!empty($search)) {
                                                $searchPattern = "%{$search}%";
                                                $stmt = $connect->prepare("SELECT * FROM projects WHERE c_id = ? AND archive = 0 AND trash != 1 AND project_title LIKE ?");
                                                $stmt->bind_param("is", $user_id, $searchPattern);
                                                $stmt->execute();
                                                $result = $stmt->get_result();
                                                $userProjects = [];
                                                while ($row = $result->fetch_assoc()) {
                                                    $userProjects[] = projects::instantiate($row);
                                                }
                                                $stmt->close();
                                            } else {
                                                $userProjects = projects::findBySql("SELECT * FROM projects WHERE c_id = $user_id AND archive = 0 AND trash != 1");
                                            }
                                        }
                                        if ($userProjects):
                                        ?>
                                            <?php foreach ($userProjects as $recentProject):
                                                // Task counts
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
                                               <div class="col-xl-4 col-lg-6 col-md-6 col-sm-6 col-12">


                                                <div class="card project-card" style="position: relative;">
                                                    <!-- 3-dots dropdown menu -->
                                                    <div class="dropdown card-action-dropdown" style="position: absolute; top: 12px; right: 16px;">
                                                        <button class="btn btn-link dropdown-toggle" type="button" id="dropdownMenu<?php echo $recentProject->p_id; ?>" data-bs-toggle="dropdown" aria-expanded="false" style="color: #333; font-size: 20px; text-decoration: none;">
                                                            <span class="light-grey" style="font-size: 20px; letter-spacing: &#8226;">&#8226;&#8226;&#8226;</span>
                                                        </button>
                                                        <ul class="dropdown-menu" aria-labelledby="dropdownMenu<?php echo $recentProject->p_id; ?>">
                                                            <li>
                                                                <a class="dropdown-item" href="overview?projectId=<?php echo $recentProject->p_id; ?>">
                                                                    <?php echo ts_icon('clock', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Overview']; ?>
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <form action="../discussion?project_id=<?php echo $recentProject->p_id;?>" method="post" style="display:inline;">
                                                                    <input type="hidden" name="user_id" value="<?php echo $recentProject->c_id;?>" />
                                                                    <input type="hidden" name="project_id" value="<?php echo $recentProject->p_id;?>" />
                                                                    <button type="submit" name="chat" class="dropdown-item">
                                                                        <?php echo ts_icon('document-text', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Discussion']; ?>
                                                                    </button>
                                                                </form>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item" href="task?projectId=<?php echo $recentProject->p_id; ?>">
                                                                    <?php echo ts_icon('inbox-stack', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Tasks']; ?>
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a href="edit-project?id=<?php echo $recentProject->p_id;?>" class="dropdown-item"><?php echo ts_icon('edit', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Edit Project']; ?></a>
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
                                                        <!-- Assigned Team and Clients -->
                                                        <div class="d-flex col-gap-40 mb-4 flex-wrap" style="text-align: left;">
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
                                                                                echo getUserAvatarHtml($st_id, $user2->firstName, $user2->lastName ?? '', 36, 36, '', $user2->firstName);
                                                                                echo '</div>'; 
                                                                            }
                                                                        }
                                                                    }
                                                                    if($counter > 3){
                                                                        $more = $counter-3;
                                                                        echo '<div class="plus-more shadow-dept">+'. $more .'</div>'; 
                                                                    }
                                                                    ?>
                                                                </div>
                                                            </div>
                                                            <!-- Clients -->
                                                            <div class="clients mb-2">
                                                                <div class="title-head mb-2"><?php echo $lang['Clients']; ?></div>
                                                                <div class="d-flex align-items-center">
                                                                    <?php 
                                                                    // Display main client first
                                                                    $mainClient = user::findById($recentProject->main_client_id ?: $recentProject->c_id);
                                                                    if ($mainClient) {
                                                                        echo '<div class="user-box">';
                                                                        echo getUserAvatarHtml($mainClient->id, $mainClient->firstName, $mainClient->lastName ?? '', 36, 36, '', $mainClient->firstName);
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
                                                                                echo getUserAvatarHtml($client->id, $client->firstName, $client->lastName ?? '', 36, 36, '', $client->firstName);
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
                                                        <!-- Progress Bar and Task Completion -->
                                                        <div class="progress mb-2" style="height:8px;">
                                                            <div class="progress-bar" role="progressbar" style="width: <?php echo $percent; ?>%; background: <?php echo $percent < 30 ? '#f66' : ($percent < 70 ? '#f9b233' : '#4caf50'); ?>;" aria-valuenow="<?php echo $percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                        </div>
                                                        <div class="mb-2 d-flex col-gap-5">
                                                            <div class="grey bold"><?php echo $lang['TASK']; ?></div>
                                                            <?php echo $completedTaskCount; ?>/<?php echo $taskCount; ?>
                                                            <span class="text-align-right flex-grow"><?php echo $percent; ?>%</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php else: ?>
                                            <div class="empty-box"><?php echo $lang['No projects found.']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php if ($tab == 'activity'): ?>
                                <div class="vh-100 profile-activity-table-wrap">
                                    <div class="table-responsive scroll-x vh-100">
                                        <table class="table table-new projectspage" data-pagination="true" data-page-size="15">
                                            <thead>
                                                <tr>
                                                    <th class="text-center" width="5%">
                                                        <?php echo $lang['No.']; ?>
                                                    </th>
                                                    <th width="8%">
                                                        <?php echo $lang['Type']; ?>
                                                    </th>
                                                    <th width="15%">
                                                        <?php echo $lang['Event']; ?>
                                                    </th>
                                                    <th width="25%">
                                                        <?php echo $lang['Description']; ?>
                                                    </th>
                                                    <th width="30%">
                                                        <?php echo $lang['IP Address']; ?>
                                                    </th>
                                                    <th width="15%">
                                                        <?php echo $lang['Date & Time']; ?>
                                                    </th>
                                                    <th width="5%">
                                                        <?php echo $lang['Session Duration']; ?>
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody id="projects-tbl">
                                                <?php
                                                // Check for inactive sessions and clean them up
                                                require_once('../includes/activity_logger.php');
                                                ActivityLogger::checkInactiveSessions();
                                                
                                                // Get user activity data
                                                $activityData = [];
                                                
                                                // Apply date filtering
                                                $dateFilter = isset($_GET['date_filter']) ? $_GET['date_filter'] : 'all';
                                                $loginDateWhereClause = "";
                                                $securityDateWhereClause = "";
                                                
                                                // Check if custom date range is provided via start_date and end_date
                                                if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
                                                    $fromDate = $_GET['start_date'];
                                                    $toDate = $_GET['end_date'];
                                                    $loginDateWhereClause = " AND DATE(attempt_time) >= '$fromDate' AND DATE(attempt_time) <= '$toDate'";
                                                    $securityDateWhereClause = " AND DATE(created_at) >= '$fromDate' AND DATE(created_at) <= '$toDate'";
                                                } elseif ($dateFilter !== 'all') {
                                                    $currentDate = date('Y-m-d');
                                                    
                                                    switch ($dateFilter) {
                                                        case 'today':
                                                            $loginDateWhereClause = " AND DATE(attempt_time) = '$currentDate'";
                                                            $securityDateWhereClause = " AND DATE(created_at) = '$currentDate'";
                                                            break;
                                                        case 'yesterday':
                                                            $yesterday = date('Y-m-d', strtotime('-1 day'));
                                                            $loginDateWhereClause = " AND DATE(attempt_time) = '$yesterday'";
                                                            $securityDateWhereClause = " AND DATE(created_at) = '$yesterday'";
                                                            break;
                                                        case 'this_week':
                                                            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
                                                            $loginDateWhereClause = " AND DATE(attempt_time) >= '$startOfWeek'";
                                                            $securityDateWhereClause = " AND DATE(created_at) >= '$startOfWeek'";
                                                            break;
                                                        case 'last_week':
                                                            $startLastWeek = date('Y-m-d', strtotime('monday last week'));
                                                            $endLastWeek = date('Y-m-d', strtotime('sunday last week'));
                                                            $loginDateWhereClause = " AND DATE(attempt_time) >= '$startLastWeek' AND DATE(attempt_time) <= '$endLastWeek'";
                                                            $securityDateWhereClause = " AND DATE(created_at) >= '$startLastWeek' AND DATE(created_at) <= '$endLastWeek'";
                                                            break;
                                                        case 'this_month':
                                                            $startOfMonth = date('Y-m-01');
                                                            $loginDateWhereClause = " AND DATE(attempt_time) >= '$startOfMonth'";
                                                            $securityDateWhereClause = " AND DATE(created_at) >= '$startOfMonth'";
                                                            break;
                                                        case 'last_month':
                                                            $startLastMonth = date('Y-m-01', strtotime('first day of last month'));
                                                            $endLastMonth = date('Y-m-t', strtotime('last day of last month'));
                                                            $loginDateWhereClause = " AND DATE(attempt_time) >= '$startLastMonth' AND DATE(attempt_time) <= '$endLastMonth'";
                                                            $securityDateWhereClause = " AND DATE(created_at) >= '$startLastMonth' AND DATE(created_at) <= '$endLastMonth'";
                                                            break;
                                                        case 'this_year':
                                                            $startOfYear = date('Y-01-01');
                                                            $loginDateWhereClause = " AND DATE(attempt_time) >= '$startOfYear'";
                                                            $securityDateWhereClause = " AND DATE(created_at) >= '$startOfYear'";
                                                            break;
                                                        case 'custom':
                                                            $fromDate = isset($_GET['from_date']) ? $_GET['from_date'] : '';
                                                            $toDate = isset($_GET['to_date']) ? $_GET['to_date'] : '';
                                                            // Also check for start_date and end_date parameters from custom range dropdown
                                                            if (!$fromDate) $fromDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
                                                            if (!$toDate) $toDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
                                                            if ($fromDate && $toDate) {
                                                                $loginDateWhereClause = " AND DATE(attempt_time) >= '$fromDate' AND DATE(attempt_time) <= '$toDate'";
                                                                $securityDateWhereClause = " AND DATE(created_at) >= '$fromDate' AND DATE(created_at) <= '$toDate'";
                                                            }
                                                            break;
                                                    }
                                                }
                                                
                                                // Get login attempts
                                                $loginQuery = "SELECT 'login' as type, 
                                                                    CASE 
                                                                        WHEN type = 'logout' THEN 'logout'
                                                                        WHEN success = 1 THEN 'user.login'
                                                                        ELSE 'user.login_failed'
                                                                    END as event,
                                                                    attempt_time as created_at,
                                                                    ip_address,
                                                                    user_agent,
                                                                    CASE 
                                                                        WHEN type = 'logout' THEN 'User logged out'
                                                                        WHEN success = 1 THEN 'Successful login'
                                                                        ELSE 'Failed login attempt'
                                                                    END as description
                                                               FROM login_attempts 
                                                               WHERE user_id = ? $loginDateWhereClause
                                                               ORDER BY attempt_time DESC 
                                                               LIMIT 50";
                                                $stmt = $connect->prepare($loginQuery);
                                                $stmt->bind_param("i", $user_id);
                                                $stmt->execute();
                                                $loginResult = $stmt->get_result();
                                                
                                                while ($row = $loginResult->fetch_assoc()) {
                                                    $activityData[] = $row;
                                                }
                                                
                                                // Get security logs
                                                $securityQuery = "SELECT 'security' as type,
                                                                      event,
                                                                      created_at,
                                                                      ip_address,
                                                                      user_agent,
                                                                      details as description
                                                                 FROM security_logs 
                                                                 WHERE user_id = ? $securityDateWhereClause
                                                                 ORDER BY created_at DESC 
                                                                 LIMIT 50";
                                                $stmt = $connect->prepare($securityQuery);
                                                $stmt->bind_param("i", $user_id);
                                                $stmt->execute();
                                                $securityResult = $stmt->get_result();
                                                
                                                while ($row = $securityResult->fetch_assoc()) {
                                                    $activityData[] = $row;
                                                }
                                                
                                                // Sort all activities by date
                                                usort($activityData, function($a, $b) {
                                                    return strtotime($b['created_at']) - strtotime($a['created_at']);
                                                });
                                                
                                                // Pagination logic
                                                $page_size = 15;
                                                $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                                                $total_records = count($activityData);
                                                $total_pages = ceil($total_records / $page_size);
                                                
                                                // Apply pagination
                                                $start_index = ($current_page - 1) * $page_size;
                                                $paginatedData = array_slice($activityData, $start_index, $page_size);
                                                
                                                if (empty($paginatedData)) {
                                                    echo '<tr><td colspan="7" class="text-center">' . $lang['No activity found for this user.'] . '</td></tr>';
                                                } else {
                                                    $counter = $start_index + 1;
                                                    foreach ($paginatedData as $activity) {
                                                        $badgeClass = '';
                                                        $eventText = '';
                                                        
                                                        switch ($activity['event']) {
                                                            case 'user.login':
                                                                $badgeClass = 'badge-success';
                                                                $eventText = $lang['Login Successful'];
                                                                break;
                                                            case 'user.login_failed':
                                                                $badgeClass = 'badge-danger';
                                                                $eventText = $lang['Login Failed'];
                                                                break;
                                                            case 'password.change':
                                                                $badgeClass = 'badge-warning';
                                                                $eventText = $lang['Password Changed'];
                                                                break;
                                                            case 'logout':
                                                                $badgeClass = 'badge-info';
                                                                $eventText = $lang['Logout'];
                                                                break;
                                                            default:
                                                                $badgeClass = 'badge-info';
                                                                $eventText = $activity['event'];
                                                                break;
                                                        }
                                                        
                                                        // Calculate session duration for login events
                                                        $sessionDuration = '-';
                                                        if ($activity['event'] == 'user.login') {
                                                            // Find the next logout event for this user after this login
                                                            $loginTime = strtotime($activity['created_at']);
                                                            $logoutQuery = "SELECT attempt_time FROM login_attempts 
                                                                          WHERE user_id = ? AND type = 'logout' AND attempt_time > ? 
                                                                          ORDER BY attempt_time ASC LIMIT 1";
                                                            $logoutStmt = $connect->prepare($logoutQuery);
                                                            $logoutStmt->bind_param("is", $user_id, $activity['created_at']);
                                                            $logoutStmt->execute();
                                                            $logoutResult = $logoutStmt->get_result();
                                                            
                                                            if ($logoutRow = $logoutResult->fetch_assoc()) {
                                                                $logoutTime = strtotime($logoutRow['attempt_time']);
                                                                $sessionDuration = function_exists('user_presence_activity_duration_label')
                                                                    ? user_presence_activity_duration_label($loginTime, $logoutTime, 0, 'offline')
                                                                    : user_presence_format_duration($logoutTime - $loginTime);
                                                            } else {
                                                                $seenUser = (isset($profileUser) && is_object($profileUser)) ? $profileUser : User::findById((int)$user_id);
                                                                $sessionDuration = function_exists('user_presence_activity_duration_label')
                                                                    ? user_presence_activity_duration_label(
                                                                        $loginTime,
                                                                        null,
                                                                        $seenUser->last_seen ?? 0,
                                                                        $seenUser->session_status ?? 'offline'
                                                                    )
                                                                    : 'Active';
                                                            }
                                                        } elseif ($activity['event'] == 'logout') {
                                                            // Find the previous login event for this user before this logout
                                                            $logoutTime = strtotime($activity['created_at']);
                                                            $loginQuery = "SELECT attempt_time FROM login_attempts 
                                                                         WHERE user_id = ? AND success = 1 AND type = 'login' AND attempt_time < ? 
                                                                         ORDER BY attempt_time DESC LIMIT 1";
                                                            $loginStmt = $connect->prepare($loginQuery);
                                                            $loginStmt->bind_param("is", $user_id, $activity['created_at']);
                                                            $loginStmt->execute();
                                                            $loginResult = $loginStmt->get_result();
                                                            
                                                            if ($loginRow = $loginResult->fetch_assoc()) {
                                                                $loginTime = strtotime($loginRow['attempt_time']);
                                                                $duration = $logoutTime - $loginTime;
                                                                
                                                                if ($duration < 60) {
                                                                    $sessionDuration = $duration . ' seconds';
                                                                } elseif ($duration < 3600) {
                                                                    $minutes = floor($duration / 60);
                                                                    $seconds = $duration % 60;
                                                                    $sessionDuration = $minutes . 'm ' . $seconds . 's';
                                                                } else {
                                                                    $hours = floor($duration / 3600);
                                                                    $minutes = floor(($duration % 3600) / 60);
                                                                    $sessionDuration = $hours . 'h ' . $minutes . 'm';
                                                                }
                                                            }
                                                        }
                                                        
                                                        echo '<tr>';
                                                        echo '<td class="text-center">' . $counter . '</td>';
                                                        echo '<td><span class="badge ' . $badgeClass . '">' . ucfirst($activity['type']) . '</span></td>';
                                                        echo '<td><div class="tbl-ttl">' . htmlspecialchars($eventText) . '</div></td>';
                                                        echo '<td><div class="tbl-ttl">' . htmlspecialchars($activity['description']) . '</div></td>';
                                                        echo '<td><div class="tbl-ttl">' . htmlspecialchars($activity['ip_address']) . '</div></td>';
                                                        // Convert from SERVER timezone to System Settings timezone and render in 12-hour format
                                                        try {
                                                            $settingsTz = null;
                                                            if (class_exists('settings')) {
                                                                $sys = settings::findById(1);
                                                                if ($sys && !empty($sys->time_zone)) {
                                                                    $settingsTz = $sys->time_zone;
                                                                }
                                                            }
                                                            $targetTz = $settingsTz ?: (isset($time_zone) && !empty($time_zone) ? $time_zone : date_default_timezone_get());
                                                            // Determine server/source timezone from php.ini (fallback UTC)
                                                            $serverTz = ini_get('date.timezone');
                                                            if (!$serverTz) { $serverTz = 'UTC'; }
                                                            // Interpret DB datetime in server timezone, then convert to target
                                                            $dt = new DateTime($activity['created_at'], new DateTimeZone($serverTz));
                                                            $dt->setTimezone(new DateTimeZone($targetTz));
                                                            // Add 3 hours to match your expected timezone
                                                            $dt->add(new DateInterval('PT3H'));
                                                            $formatted_time = $dt->format('M j, Y \\a\\t g:i A');
                                                        } catch (Exception $e) {
                                                            $formatted_time = date('M j, Y \\a\\t g:i A', strtotime($activity['created_at']));
                                                        }
                                                        echo '<td><div class="tbl-ttl">' . $formatted_time . '</div></td>';
                                                        if ($sessionDuration == 'Active') {
                                                            $durationClass = 'session-duration active';
                                                        } elseif (strpos($sessionDuration, 'Timeout') !== false) {
                                                            $durationClass = 'session-duration timeout';
                                                        } else {
                                                            $durationClass = 'session-duration';
                                                        }
                                                        echo '<td><div class="' . $durationClass . '">' . $sessionDuration . '</div></td>';
                                                        echo '</tr>';
                                                        $counter++;
                                                    }
                                                }
                                                ?>
                                            </tbody>
                                        </table>
							</div>
                                    
                                    <?php if ($total_records > $page_size) : ?>
                                    <?php
                                    $paginationParams = array('user_id' => $user_id, 'tab' => 'activity');
                                    if (!empty($dateFilter) && $dateFilter !== 'all') {
                                        $paginationParams['date_filter'] = $dateFilter;
                                    }
                                    if (!empty($_GET['start_date'])) {
                                        $paginationParams['start_date'] = (string) $_GET['start_date'];
                                    }
                                    if (!empty($_GET['end_date'])) {
                                        $paginationParams['end_date'] = (string) $_GET['end_date'];
                                    }
                                    $activityPaginationHref = static function ($p) use ($paginationParams) {
                                        $params = $paginationParams;
                                        $params['page'] = (int) $p;
                                        return tasksession_build_list_query($params);
                                    };
                                    tasksession_render_windowed_pagination_box($total_records, $page_size, $current_page, $activityPaginationHref);
                                    ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($tab == 'media'): ?>
                            <?php if (!empty($profileMediaLocked) && function_exists('tasksession_render_pro_upgrade_embedded')): ?>
                                <?php tasksession_render_pro_upgrade_embedded('files_media'); ?>
                            <?php else: ?>
                                <div class="profile-media-content-wrap">
                                <?php
                                $profileMediaOnlyExplicitShares = true;
                                $__pmt = __DIR__ . '/../includes/profile_media_tab.php';
if (is_file($__pmt)) { require_once $__pmt; }
                                include __DIR__ . '/../templates/docs/profile-media-panel.php';
                                ?>
                                </div>
                            <?php endif; ?>
                            <?php endif; ?>

                            <?php if ($tab == 'notes'): ?>
                                <div class="profile-notes-content-wrap">
                                <?php
                                $profileNotesCreatorType = 'admin';
                                require_once __DIR__ . '/../includes/profile_notes_tab.php';
                                include __DIR__ . '/../templates/docs/profile-notes-panel.php';
                                ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($tab == 'tasks'): ?>
                                <?php
                                // Load task model
                                require_once("../includes/task.php");
                                
                                // Define status types and labels
                                $statusTypes = ['todo', 'inprogress', 'review', 'done'];
                                $statusLabels = [
                                    'todo' => 'To Do',
                                    'inprogress' => 'In Progress', 
                                    'review' => 'Review',
                                    'done' => 'Done'
                                ];
                                $statusColors = [
                                    'todo' => 'color-todo-bg',
                                    'inprogress' => 'color-inprogress-bg',
                                    'review' => 'color-review-bg',
                                    'done' => 'color-done-bg'
                                ];
                                
                                // Load custom column names from database
                                $columnNamesQuery = "SELECT column_key, custom_name FROM project_columns WHERE project_id = 0";
                                $columnNamesResult = $database->query($columnNamesQuery);
                                
                                if ($columnNamesResult && $database->numRows($columnNamesResult) > 0) {
                                    while ($columnRow = $database->fetchArray($columnNamesResult)) {
                                        $key = $columnRow['column_key'];
                                        if (isset($statusLabels[$key])) {
                                            $statusLabels[$key] = $columnRow['custom_name'];
                                        }
                                    }
                                }
                                
                                // Get tasks for current user with project info
                                // For staff and admin: tasks assigned to them
                                // For clients: tasks from projects where they are main client or additional client
                                if ($profileUser->accountStatus == 3 || $profileUser->accountStatus == 1) {
                                    // Staff/Admin - tasks assigned to them
                                    $tasksQuery = "SELECT t.*, 
                                        CASE 
                                            WHEN t.project_id > 0 THEN p.project_title 
                                            ELSE 'Internal Task' 
                                        END as project_title,
                                        CASE 
                                            WHEN t.project_id > 0 THEN p.p_id 
                                            ELSE 0 
                                        END as p_id,
                                        CASE 
                                            WHEN t.project_id = 0 THEN 1 
                                            ELSE 0 
                                        END as is_internal,
                                        p.c_id as project_client_id,
                                        u.firstName as client_first_name,
                                        cp.filename as client_image
                                        FROM tasks t 
                                        LEFT JOIN projects p ON t.project_id = p.p_id
                                        LEFT JOIN users u ON p.c_id = u.id
                                        LEFT JOIN profile_pics cp ON cp.fkUserId = u.id
                                        WHERE FIND_IN_SET($user_id, t.assigned_to) > 0";
                                        
                                        // Add filters
                                        if ($internalFilter) {
                                            $tasksQuery .= " AND t.project_id = 0";
                                        }
                                        
                                        // Add regular status filters
                                        if ($statusFilter && in_array($statusFilter, $statusTypes)) {
                                            $tasksQuery .= " AND t.status = '$statusFilter'";
                                        }
                                        
                                        // Add computed status filters
                                        if ($statusFilter && in_array($statusFilter, ['overdue', 'due_today', 'task_pro', 'on_time'])) {
                                            switch ($statusFilter) {
                                                case 'overdue':
                                                    $tasksQuery .= " AND t.due_date < CURDATE() AND t.status != 'done'";
                                                    break;
                                                case 'due_today':
                                                    $tasksQuery .= " AND DATE(t.due_date) = CURDATE()";
                                                    break;
                                                case 'task_pro':
                                                    $tasksQuery .= " AND t.status = 'done' AND t.due_date > CURDATE()";
                                                    break;
                                                case 'on_time':
                                                    $tasksQuery .= " AND t.status = 'done' AND DATE(t.due_date) = DATE(t.completed_at)";
                                                    break;
                                            }
                                        }
                                        
                                        // Add date range filters
                                        if ($startDateFilter || $endDateFilter) {
                                            if ($startDateFilter && $endDateFilter) {
                                                $tasksQuery .= " AND ((t.start_date >= '$startDateFilter' AND t.start_date <= '$endDateFilter') OR (t.due_date >= '$startDateFilter' AND t.due_date <= '$endDateFilter'))";
                                            } elseif ($startDateFilter) {
                                                $tasksQuery .= " AND (t.start_date >= '$startDateFilter' OR t.due_date >= '$startDateFilter')";
                                            } elseif ($endDateFilter) {
                                                $tasksQuery .= " AND (t.start_date <= '$endDateFilter' OR t.due_date <= '$endDateFilter')";
                                            }
                                        }
                                        
                                        // Add search filter
                                        if (!empty($searchQuery)) {
                                            $searchEscaped = $database->escapeValue($searchQuery);
                                            $tasksQuery .= " AND (t.title LIKE '%$searchEscaped%' OR t.description LIKE '%$searchEscaped%')";
                                        }
                                        
                                        $tasksQuery .= " ORDER BY t.created_at DESC";
                                } else {
                                    // Client - tasks from projects where they are main client or additional client
                                    $tasksQuery = "SELECT t.*, 
                                        CASE 
                                            WHEN t.project_id > 0 THEN p.project_title 
                                            ELSE 'Internal Task' 
                                        END as project_title,
                                        CASE 
                                            WHEN t.project_id > 0 THEN p.p_id 
                                            ELSE 0 
                                        END as p_id,
                                        CASE 
                                            WHEN t.project_id = 0 THEN 1 
                                            ELSE 0 
                                        END as is_internal,
                                        p.c_id as project_client_id,
                                        u.firstName as client_first_name,
                                        cp.filename as client_image
                                        FROM tasks t 
                                        LEFT JOIN projects p ON t.project_id = p.p_id
                                        LEFT JOIN users u ON p.c_id = u.id
                                        LEFT JOIN profile_pics cp ON cp.fkUserId = u.id
                                        WHERE t.project_id > 0 AND (
                                            p.c_id = $user_id OR 
                                            p.main_client_id = $user_id OR 
                                            FIND_IN_SET($user_id, p.c_ids) > 0
                                        )";
                                        
                                        // Add filters
                                        if ($internalFilter) {
                                            $tasksQuery .= " AND t.project_id = 0";
                                        }
                                        
                                        // Add regular status filters
                                        if ($statusFilter && in_array($statusFilter, $statusTypes)) {
                                            $tasksQuery .= " AND t.status = '$statusFilter'";
                                        }
                                        
                                        // Add computed status filters
                                        if ($statusFilter && in_array($statusFilter, ['overdue', 'due_today', 'task_pro', 'on_time'])) {
                                            switch ($statusFilter) {
                                                case 'overdue':
                                                    $tasksQuery .= " AND t.due_date < CURDATE() AND t.status != 'done'";
                                                    break;
                                                case 'due_today':
                                                    $tasksQuery .= " AND DATE(t.due_date) = CURDATE()";
                                                    break;
                                                case 'task_pro':
                                                    $tasksQuery .= " AND t.status = 'done' AND t.due_date > CURDATE()";
                                                    break;
                                                case 'on_time':
                                                    $tasksQuery .= " AND t.status = 'done' AND DATE(t.due_date) = DATE(t.completed_at)";
                                                    break;
                                            }
                                        }
                                        
                                        // Add date range filters
                                        if ($startDateFilter || $endDateFilter) {
                                            if ($startDateFilter && $endDateFilter) {
                                                $tasksQuery .= " AND ((t.start_date >= '$startDateFilter' AND t.start_date <= '$endDateFilter') OR (t.due_date >= '$startDateFilter' AND t.due_date <= '$endDateFilter'))";
                                            } elseif ($startDateFilter) {
                                                $tasksQuery .= " AND (t.start_date >= '$startDateFilter' OR t.due_date >= '$startDateFilter')";
                                            } elseif ($endDateFilter) {
                                                $tasksQuery .= " AND (t.start_date <= '$endDateFilter' OR t.due_date <= '$endDateFilter')";
                                            }
                                        }
                                        
                                        // Add search filter
                                        if (!empty($searchQuery)) {
                                            $searchEscaped = $database->escapeValue($searchQuery);
                                            $tasksQuery .= " AND (t.title LIKE '%$searchEscaped%' OR t.description LIKE '%$searchEscaped%')";
                                        }
                                        
                                        $tasksQuery .= " ORDER BY t.created_at DESC";
                                }
                                
                                $result = $database->query($tasksQuery);
                                $tasks = [];
                                
                                while($row = $database->fetchArray($result)) {
                                    $tasks[] = $row;
                                }
                                
                                // Group tasks by status for Kanban display
                                $columns = ['todo'=>[], 'inprogress'=>[], 'review'=>[], 'done'=>[]];
                                
                                // Handle special status filters (put all filtered tasks in 'done' column)
                                if ($statusFilter && in_array($statusFilter, ['overdue', 'due_today', 'task_pro', 'on_time'])) {
                                    // For special status filters, put all tasks in 'done' column
                                    foreach ($tasks as $task) {
                                        $columns['done'][] = $task;
                                    }
                                } else {
                                    // Normal grouping by status
                                    foreach ($tasks as $task) {
                                        $status = $task['status'];
                                        if (!in_array($status, $statusTypes)) {
                                            $status = 'todo'; // Default fallback
                                        }
                                        $columns[$status][] = $task;
                                    }
                                }
                                
                                // Store original task counts for Load More button logic
                                $originalColumnCounts = [];
                                foreach ($columns as $columnKey => $columnTasks) {
                                    $originalColumnCounts[$columnKey] = count($columnTasks);
                                }
                                
                                // Limit initial display to 10 tasks per column
                                foreach ($columns as $columnKey => $columnTasks) {
                                    if (count($columnTasks) > 10) {
                                        $columns[$columnKey] = array_slice($columnTasks, 0, 10);
                                    }
                                }
                                
                                // Get project staff for avatars
                                $projectStaff = [];
                                if ($profileUser->accountStatus == 3 || $profileUser->accountStatus == 1) {
                                    // For staff/admin, get all staff members from projects they're assigned to
                                    $staffQuery = "SELECT DISTINCT s_ids FROM projects WHERE FIND_IN_SET($user_id, s_ids)";
                                    $staffResult = $database->query($staffQuery);
                                    while ($staffRow = $database->fetchArray($staffResult)) {
                                        $s_ids = $staffRow['s_ids'];
                                        $staffIds = explode(',', $s_ids);
                                        foreach ($staffIds as $staffId) {
                                            if ($staffId > 0) {
                                                $staffUser = User::findById($staffId);
                                                if ($staffUser && ($staffUser->accountStatus == 3 || $staffUser->accountStatus == 1)) {
                                                    $projectStaff[$staffId] = $staffUser;
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    // For clients, get staff from their projects
                                    $clientProjectsQuery = "SELECT s_ids FROM projects WHERE c_id = $user_id OR main_client_id = $user_id OR FIND_IN_SET($user_id, c_ids) > 0";
                                    $clientProjectsResult = $database->query($clientProjectsQuery);
                                    while ($projectRow = $database->fetchArray($clientProjectsResult)) {
                                        $s_ids = $projectRow['s_ids'];
                                        $staffIds = explode(',', $s_ids);
                                        foreach ($staffIds as $staffId) {
                                            if ($staffId > 0) {
                                                $staffUser = User::findById($staffId);
                                                if ($staffUser && ($staffUser->accountStatus == 3 || $staffUser->accountStatus == 1)) {
                                                    $projectStaff[$staffId] = $staffUser;
                                                }
                                            }
                                        }
                                    }
                                }
                                
                                // Helper function for text truncation
                                function first_n_words($text, $limit = 15) {
                                    $plain = strip_tags($text);
                                    $words = preg_split('/\s+/', $plain);
                                    if (count($words) > $limit) {
                                        $short = implode(' ', array_slice($words, 0, $limit)) . '...';
                                        return $short;
                                    }
                                    return $plain;
                                }
                                ?>
                                
                                <!-- Kanban Board -->
                                <div class="board-wrap">
                                    <div class="board d-flex">
                                        <?php 
                                        // Always show all columns, but update title for special filters
                                        $columnsToShow = $statusTypes;
                                        $activeFilterName = '';
                                        
                                        if ($statusFilter && in_array($statusFilter, ['overdue', 'due_today', 'task_pro', 'on_time'])) {
                                            // Set the filter name for display
                                            switch ($statusFilter) {
                                                case 'overdue':
                                                    $activeFilterName = 'Overdue Tasks';
                                                    break;
                                                case 'due_today':
                                                    $activeFilterName = 'Due Today';
                                                    break;
                                                case 'task_pro':
                                                    $activeFilterName = 'Task Pro';
                                                    break;
                                                case 'on_time':
                                                    $activeFilterName = 'On Time';
                                                    break;
                                            }
                                        }
                                        
                                        foreach ($columnsToShow as $status): 
                                            $columnTasks = $columns[$status];
                                        ?>
                                            <div class="board-column" 
                                                 id="<?= $status ?>-column" 
                                                 ondrop="drop(event)" 
                                                 ondragover="allowDrop(event)" 
                                                 ondragleave="dragLeave(event)">
                                                <h2 class="h6 d-flex align-items-center">
                                                    <i class="dots <?= $statusColors[$status] ?> me-1"></i>
                                                    <span class="column-title" data-column="<?= $status ?>">
                                                        <?php 
                                                        if ($activeFilterName && $status === 'done') {
                                                            echo $activeFilterName;
                                                        } else {
                                                            echo $statusLabels[$status];
                                                        }
                                                        ?>
                                                    </span>
                                                    <span class="ms-2 badge"><?= isset($originalColumnCounts[$status]) ? $originalColumnCounts[$status] : count($columnTasks) ?></span>
                                                </h2>

                                                <?php if (empty($columnTasks)): ?>
                                                    <div class="alert alert-light text-center">
                                                        <?php echo $lang['No tasks in this column']; ?>
							</div>
                                                <?php endif; ?>

                                                <?php if (!empty($columnTasks)): ?>
                                                    <?php foreach ($columnTasks as $task): ?>
                                                        <?php 
                                                        try {
                                                            $taskId = isset($task['id']) ? $task['id'] : 0;
                                                            $projIdFor = isset($task['project_id']) ? $task['project_id'] : 0;
                                                            $taskTitle = isset($task['title']) ? htmlspecialchars($task['title']) : 'Untitled Task';
                                                        ?>
                                                        <div class="card mb-2 task-card" 
                                                             draggable="true"
                                                             data-id="<?= $taskId ?>"
                                                             data-project-id="<?= $projIdFor ?>"
                                                             ondragstart="drag(event)"
                                                             ondragend="dragEnd(event)">
                                                            <div class="card-body p-2">
                                                                <!-- Bulk Delete Checkbox -->
                                                                <div class="bulk-delete-checkbox" style="display: none;">
                                                                    <div class="form-check">
                                                                        <input class="form-check-input task-checkbox" 
                                                                               type="checkbox" 
                                                                               value="<?= $taskId ?>" 
                                                                               id="task_<?= $taskId ?>">
                                                                        <label class="form-check-label" for="task_<?= $taskId ?>">
                                                                            Select for deletion
                                                                        </label>
                                                                    </div>
                                                                </div>
                                                                <div class="d-flex justify-content-between align-items-center">
                                                                    <div>
                                                                        <?php if (isset($task['is_internal']) && $task['is_internal'] == 1): ?>
                                                                            <span class="internal-badge mb-2">
                                                                                <?php echo $lang['Internal Task']; ?>
                                                                            </span>
                                                                        <?php endif; ?>
                                                                        <h5 class="card-title mb-1"><?= $taskTitle ?></h5>
                                                                    </div>
                                                                    <div class="dropdown">
                                                                        <button class="btn-dots text-muted p-0" 
                                                                                type="button" 
                                                                                data-bs-toggle="dropdown">
                                                                             <?php echo ts_icon('dots-vertical', 'w-6'); ?>
                                                                        </button>
                                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                                            <li>
                                                                                <a class="dropdown-item" href="#" 
                                                                                   onclick="openTaskSidebar(<?= $taskId ?>); return false;">
                                                                                    <?php echo ts_icon('eye', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['View Task']; ?>
                                                                                </a>
                                                                            </li>
                                                                            <li>
                                                                                <a class="dropdown-item" href="edit_task?id=<?= $taskId ?>">
                                                                                    <?php echo ts_icon('edit', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Edit Task']; ?>
                                                                                </a>
                                                                            </li>
                                                                            <li>
                                                                                <a class="dropdown-item text-danger" href="#" 
                                                                                   onclick="deleteTask(<?= $taskId ?>)">
                                                                                    <?php echo ts_icon('delete', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Delete Task']; ?>
                                                                                </a>
                                                                            </li>
                                                                            <li>
                                                                                <a class="dropdown-item" href="../includes/clone-task.php?id=<?= $taskId ?>&redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>">
                                                                                    <?php echo ts_icon('duplicate', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Clone Task']; ?>
                                                                                </a>
                                                                            </li>
                                                                        </ul>
                                                                    </div>
                                                                </div>

                                                                <?php if (isset($task['description']) && !empty($task['description'])): ?>
                                                                    <div class="task-description mb-2">
                                                                        <?= first_n_words($task['description'], 15) ?>
                                                                    </div>
                                                                <?php endif; ?>

                                                                <?php if (!empty($task['start_date']) || !empty($task['due_date'])): ?>
                                                                    <div class="task-date d-flex">
                                                                        <?php if (!empty($task['start_date'])): ?>
                                                                            <div class="start-date">
                                                                                <b><?php echo $lang['Start']; ?>:</b> 
                                                                                <?= date('M j, Y', strtotime($task['start_date'])) ?>
                                                                            </div>
                                                                        <?php endif; ?>

                                                                        <?php if (!empty($task['due_date'])): ?>
                                                                            <?php 
                                                                            $dueDate = strtotime($task['due_date']);
                                                                            $today = strtotime('today');
                                                                            $isOverdue = $dueDate < $today;
                                                                            $isDueToday = $dueDate == $today;
                                                                            ?>
                                                                            <div class="<?= $isOverdue ? 'text-danger' : ($isDueToday ? 'color-review' : '') ?>">
                                                                                <b><?php echo $lang['Due']; ?>:</b> 
                                                                                <?= date('M j, Y', strtotime($task['due_date'])) ?>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php endif; ?>

                                                                <?php if (!empty($task['assigned_to'])): ?>
                                                                    <div class="team-col d-flex align-items-baseline">
                                                                        <div class="d-flex avatar-head">
                                                                            <?php
                                                                            $assignedStaffIds = explode(',', $task['assigned_to']);
                                                                            foreach ($assignedStaffIds as $staffId):
                                                                                if (!empty($staffId) && isset($projectStaff[$staffId])):
                                                                                    $staffUser = $projectStaff[$staffId];
                                                                            ?>
                                                                                <div class="avatar-overlap" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($staffUser->firstName . ' ' . ($staffUser->lastName ?? '')); ?>">
                                                                                    <?php 
                                                                                    echo getUserAvatarHtml($staffId, $staffUser->firstName, $staffUser->lastName ?? '', 30, 30, 'rounded-circle', $staffUser->firstName);
                                                                                    ?>
                                                                                </div>
                                                                            <?php 
                                                                                endif;
                                                                            endforeach;
                                                                            ?>
                                                                        </div>
                                                                        <div class="due-badge">
                                                                             <?php 
                                                                             if (!empty($task['due_date'])):
                                                                                 $dueDate = strtotime($task['due_date']);
                                                                                 $today = strtotime('today');
                                                                                 
                                                                                 if ($task['status'] == 'done' && !empty($task['completed_at'])) {
                                                                                     // For completed tasks, use completion date to determine badge (locked badge)
                                                                                     // completed_at is updated every time task moves to 'done' to prevent cheating
                                                                                     $completedDate = strtotime($task['completed_at']);
                                                                                     $completedDateOnly = strtotime(date('Y-m-d', $completedDate));
                                                                                     
                                                                                     if ($completedDateOnly < $dueDate) {
                                                                                         // Completed before due date = Task Pro
                                                                                         echo '<span class="badge color-done review done-bg-op">' . $lang['Task Pro'] . '</span>';
                                                                                     } elseif ($completedDateOnly == $dueDate) {
                                                                                         // Completed on due date = On Time (frozen badge)
                                                                                         echo '<span class="badge color-review review-bg-op">' . $lang['On Time'] . '</span>';
                                                                                     } else {
                                                                                         // Completed after due date = Overdue
                                                                                         echo '<span class="badge">' . $lang['Overdue'] . '</span>';
                                                                                     }
                                                                                 } else {
                                                                                     // For non-completed tasks, use current date logic
                                                                                     $isOverdue = $dueDate < $today;
                                                                                     $isDueToday = $dueDate == $today;
                                                                                     
                                                                                     if ($isOverdue): ?>
                                                                                         <span class="badge"><?php echo $lang['Overdue']; ?></span>
                                                                                     <?php elseif ($isDueToday): ?>
                                                                                         <span class="badge color-review review-bg-op"><?php echo $lang['Due Today']; ?></span>
                                                                                     <?php endif;
                                                                                 }
                                                                             endif;
                                                                             ?>
                                                                         </div>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <?php 
                                                        } catch (Exception $e) {
                                                            echo '<div class="alert alert-danger">Error rendering task: ' . $e->getMessage() . '</div>';
                                                        }
                                                        ?>
                                                    <?php endforeach; ?>
                                                    
                                                    <!-- Load More Button -->
                                                    <?php if (isset($originalColumnCounts[$status]) && $originalColumnCounts[$status] > 10): ?>
                                                    <div class="load-more-container" id="load-more-<?= $status ?>" style="display: block;">
                                                        <button class="primary-btn w-100 load-more-btn" 
                                                                data-status="<?= $status ?>" 
                                                                data-page="2">
                                                            <span class="load-more-text"><?php echo $lang['Load more tasks']; ?></span>
                                                            <span class="load-more-count" style="display: inline;">(<?= $originalColumnCounts[$status] - 10 ?>)</span>
                                                            <span class="spinner-border spinner-border-sm ms-2" style="display: none;"></span>
                                                        </button>
                                                    </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
							</div>
						</div>
					</div>
				</div>
			<!-- row -->
		</div>
	
		</div> <!-- col-xl-9 col-lg-8 col-md-12 -->
		</div> <!-- row -->
		</div> <!-- container-fluid -->
		</div> <!-- row profile sidebar-shrunk -->
		</div>
		</div>
	</div>
</div>
</div>
</div>
</div>
</div>
  </div>
  <script src="../assets/js/Chart.js"></script>
<script src="../assets/js/user-analytics.js"></script>
<?php if ($tab == 'tasks' || $tab == 'activity'): ?>
<script src="../assets/js/task-date-range.js"></script>
<?php endif; ?>
<?php if ($tab == 'tasks'): ?>
<script>
window.kanbanCanDelete = true;
window.kanbanCanArchive = false;
window.langSelectAll = <?php echo json_encode($lang['Select all'] ?? 'Select all'); ?>;
window.langDeselectAll = <?php echo json_encode($lang['Deselect all'] ?? 'Deselect all'); ?>;
window.langBulkDeleteConfirm = <?php echo json_encode($lang['Bulk delete confirm'] ?? 'Are you sure you want to delete %d task(s)? This action cannot be undone.'); ?>;
window.langBulkDeleteNone = <?php echo json_encode($lang['Bulk delete none'] ?? 'Please select at least one task to delete.'); ?>;
window.__toastFlash=<?php echo json_encode($toast_flash, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
</script>
<script src="../assets/js/task-bulk-delete.js"></script>
<script src="<?php echo htmlspecialchars(rtrim((string) $url, '/') . '/assets/js/toast.js', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<script src="../assets/js/kanban-sticky-headers.js?v=4"></script>
<?php
if (!(function_exists('tasksession_is_free_edition') && tasksession_is_free_edition()) && !defined('TASK_CHAT_JS_V1')) {
    define('TASK_CHAT_JS_V1', true);
    if (!defined('LOAD_TASK_CHAT_JS')) {
        define('LOAD_TASK_CHAT_JS', true);
    }
    if (!defined('CHAT_VOICE_RECEIPTS_JS_V1')) {
        define('CHAT_VOICE_RECEIPTS_JS_V1', true);
        echo '<script src="' . htmlspecialchars(rtrim((string) $url, '/') . '/assets/js/chat-receipts.js', ENT_QUOTES, 'UTF-8') . '?v=' . (int) @filemtime(SITE_ROOT . DS . 'assets' . DS . 'js' . DS . 'chat-receipts.js') . '"></script>' . "\n";
        echo '<script src="' . htmlspecialchars(rtrim((string) $url, '/') . '/assets/js/chat-voice.js', ENT_QUOTES, 'UTF-8') . '?v=' . (int) @filemtime(SITE_ROOT . DS . 'assets' . DS . 'js' . DS . 'chat-voice.js') . '"></script>' . "\n";
    }
    echo '<link rel="stylesheet" href="' . htmlspecialchars(rtrim((string) $url, '/') . '/assets/css/lightbox.css', ENT_QUOTES, 'UTF-8') . '?v=' . (int) @filemtime(SITE_ROOT . DS . 'assets' . DS . 'css' . DS . 'lightbox.css') . '" data-chat-lightbox-css="1">' . "\n";
    if (!defined('CHAT_MEDIA_ALBUM_JS_V1')) {
        define('CHAT_MEDIA_ALBUM_JS_V1', true);
        echo '<script src="' . htmlspecialchars(rtrim((string) $url, '/') . '/assets/js/chat-media-album.js', ENT_QUOTES, 'UTF-8') . '?v=' . (int) @filemtime(SITE_ROOT . DS . 'assets' . DS . 'js' . DS . 'chat-media-album.js') . '" type="text/javascript"></script>' . "\n";
    }
    if (!defined('CHAT_LIGHTBOX_NAV_JS_V1')) {
        define('CHAT_LIGHTBOX_NAV_JS_V1', true);
        echo '<script src="' . htmlspecialchars(rtrim((string) $url, '/') . '/assets/js/chat-lightbox-nav.js', ENT_QUOTES, 'UTF-8') . '?v=' . (int) @filemtime(SITE_ROOT . DS . 'assets' . DS . 'js' . DS . 'chat-lightbox-nav.js') . '" type="text/javascript"></script>' . "\n";
    }
    if (!defined('TASK_CHAT_DROPPER_JS_V1')) {
        define('TASK_CHAT_DROPPER_JS_V1', true);
        echo '<script src="' . htmlspecialchars(rtrim((string) $url, '/') . '/assets/js/task_chat_dropper.js', ENT_QUOTES, 'UTF-8') . '?v=' . (int) @filemtime(SITE_ROOT . DS . 'assets' . DS . 'js' . DS . 'task_chat_dropper.js') . '" type="text/javascript"></script>' . "\n";
    }
    if (!defined('CHAT_EMOTICONS_JS_V1')) {
        define('CHAT_EMOTICONS_JS_V1', true);
        echo '<script src="' . htmlspecialchars(rtrim((string) $url, '/') . '/assets/js/emoticons.js', ENT_QUOTES, 'UTF-8') . '?v=' . (int) @filemtime(SITE_ROOT . DS . 'assets' . DS . 'js' . DS . 'emoticons.js') . '" type="text/javascript"></script>' . "\n";
    }
    echo '<script src="' . htmlspecialchars(rtrim((string) $url, '/') . '/assets/js/task-chat.js', ENT_QUOTES, 'UTF-8') . '?v=' . (int) @filemtime(SITE_ROOT . DS . 'assets' . DS . 'js' . DS . 'task-chat.js') . '" type="text/javascript"></script>' . "\n";
}
?>
<?php endif; ?>
<?php $richEditorV = @filemtime(__DIR__ . '/../assets/js/rich-editor.js') ?: time(); ?>
<script src="../assets/js/rich-editor.js?v=<?php echo (int)$richEditorV; ?>"></script>
<?php if ($tab == 'media' && empty($profileMediaLocked) && is_dir(__DIR__ . '/../vendor/google/gdrive')): ?>
<?php
$__gdriveTpl = __DIR__ . '/../vendor/google/gdrive/templates/media-vault-move-modal.php';
if (is_file($__gdriveTpl)) {
    include $__gdriveTpl;
}
$mvShareProfileScopeEnabled = true;
$mvShareFolderModalLinkOnly = false;
$mvShareFileModalLinkOnly = false;
$__shareFolder = __DIR__ . '/../vendor/google/gdrive/templates/media-vault-share-folder-modal.php';
$__shareFile = __DIR__ . '/../vendor/google/gdrive/templates/media-vault-share-file-modal.php';
if (is_file($__shareFolder)) {
    include $__shareFolder;
}
if (is_file($__shareFile)) {
    include $__shareFile;
}
$__fsCss = __DIR__ . '/../vendor/google/gdrive/assets/css/file-sharing.css';
if (is_file($__fsCss)) {
    echo '<link rel="stylesheet" href="../vendor/google/gdrive/assets/css/file-sharing.css">' . "\n";
}
?>
<script src="../vendor/google/gdrive/assets/js/media/video-modal.js"></script>
<script src="../vendor/google/gdrive/assets/js/image-modal.js"></script>
<script src="../vendor/google/gdrive/assets/js/pdf-lightbox.js"></script>
<?php
if (!function_exists('mv_vault_asset_ver')) {
    $__mv = __DIR__ . '/../includes/mv_vault_server_profile.php';
if (is_file($__mv)) { require_once $__mv; }
}
$mvBulkJsV = @filemtime(__DIR__ . '/../vendor/google/gdrive/assets/js/bulk.js') ?: time();
?>
<script>window.mvVaultConfigBaseUrl = '../vendor/google/gdrive/includes/mv_vault_config.php';</script>
<script src="../vendor/google/gdrive/assets/js/mv-vault-config.js?v=<?php echo mv_vault_asset_ver('vendor/google/gdrive/assets/js/mv-vault-config.js'); ?>"></script>
<script src="../vendor/google/gdrive/assets/js/mv-upload-fingerprint.js?v=<?php echo mv_vault_asset_ver('vendor/google/gdrive/assets/js/mv-upload-fingerprint.js'); ?>"></script>
<script src="../vendor/google/gdrive/assets/js/mv-gdrive-resumable-engine.js?v=<?php echo mv_vault_asset_ver('vendor/google/gdrive/assets/js/mv-gdrive-resumable-engine.js'); ?>"></script>
<script src="../vendor/google/gdrive/assets/js/mv-gdrive-direct-download.js?v=<?php echo mv_vault_asset_ver('vendor/google/gdrive/assets/js/mv-gdrive-direct-download.js'); ?>"></script>
<script src="../vendor/google/gdrive/assets/js/mv-transfer-manager.js?v=<?php echo mv_vault_asset_ver('vendor/google/gdrive/assets/js/mv-transfer-manager.js'); ?>"></script>
<script src="../vendor/google/gdrive/assets/js/mv-upload-ui.js?v=<?php echo mv_vault_asset_ver('vendor/google/gdrive/assets/js/mv-upload-ui.js'); ?>"></script>
<script src="../vendor/google/gdrive/assets/js/bulk.js?v=<?php echo (int) $mvBulkJsV; ?>"></script>
<?php $mvMediaDroperV = mv_vault_asset_ver('vendor/google/gdrive/assets/js/media/media-droper.js'); ?>
<script src="../vendor/google/gdrive/assets/js/media/media-droper.js?v=<?php echo htmlspecialchars($mvMediaDroperV); ?>"></script>
<script src="../vendor/google/gdrive/assets/js/media/media-core.js"></script>
<script src="../vendor/google/gdrive/assets/js/media/media-search.js"></script>
<script src="../vendor/google/gdrive/assets/js/media/media-actions.js"></script>
<script src="../vendor/google/gdrive/assets/js/media/media-vault-marquee.js"></script>
<script src="../vendor/google/gdrive/assets/js/media/move-modal.js"></script>
<script src="../vendor/google/gdrive/assets/js/navigation.js"></script>
<script src="../vendor/google/gdrive/assets/js/utilities.js"></script>
<script src="../vendor/google/gdrive/assets/js/media-vault-share-extended.js"></script>
<script src="../vendor/google/gdrive/assets/js/folder-sharing.js"></script>
<script src="../vendor/google/gdrive/assets/js/file-sharing-vault.js"></script>
<script src="../vendor/google/gdrive/assets/js/media/media-navigation.js"></script>
<script src="../vendor/google/gdrive/assets/js/media/media-forms.js"></script>
<script src="../vendor/google/gdrive/assets/js/media/media-modals.js"></script>
<script>
<?php
$mvProfileScriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$mvMediaVaultAbs = ($mvProfileScriptDir === '' ? '' : $mvProfileScriptDir) . '/media-vault.php';
$mvProfileMediaActionsAbs = ($mvProfileScriptDir === '' ? '' : $mvProfileScriptDir) . '/profile-media-actions.php';
?>
window.currentUserId = <?php echo (int) $session->userId; ?>;
window.accountStatus = <?php echo (int) ($_SESSION['accountStatus'] ?? 0); ?>;
window.currentProjectId = 0;
window.currentFolderId = <?php echo $current_folder_id ? (int) $current_folder_id : 'null'; ?>;
window.mediaVaultBulkZipPostUrl = <?php echo json_encode($mvMediaVaultAbs, JSON_UNESCAPED_SLASHES); ?>;
window.mediaVaultMarqueeEnabled = true;
window.mediaVaultBulkZipEnabled = true;
window.mediaVaultShowShareEmails = true;
window.mediaVaultViewMode = <?php echo json_encode($mediaViewType === 'table' ? 'table' : 'grid'); ?>;
window.mediaProjectBulkPermissions = true;
window.canDownloadFiles = <?php echo !empty($canDownloadFiles) ? 'true' : 'false'; ?>;
window.canDeleteFiles = <?php echo !empty($canDeleteFiles) ? 'true' : 'false'; ?>;
window.canDeleteOthersFiles = <?php echo !empty($canDeleteOthersFiles) ? 'true' : 'false'; ?>;
window.canMoveFiles = <?php echo !empty($canMoveFiles) ? 'true' : 'false'; ?>;
window.canEditOthersFiles = <?php echo !empty($canEditOthersFiles) ? 'true' : 'false'; ?>;
window.canMoveOthersFiles = <?php echo (!empty($canEditOthersFiles) || !empty($canDeleteOthersFiles)) ? 'true' : 'false'; ?>;
window.moveEndpoint = <?php echo json_encode($mvMediaVaultAbs, JSON_UNESCAPED_SLASHES); ?>;
window.mediaDownloadEndpoint = <?php echo json_encode($mvMediaVaultAbs, JSON_UNESCAPED_SLASHES); ?>;
window.mvMediaVaultShareEndpoint = <?php echo json_encode($mvMediaVaultAbs, JSON_UNESCAPED_SLASHES); ?>;
window.mediaUploadEndpoint = <?php echo json_encode($mvProfileMediaActionsAbs, JSON_UNESCAPED_SLASHES); ?>;
window.profileMediaCreateFolderEndpoint = <?php echo json_encode($mvProfileMediaActionsAbs, JSON_UNESCAPED_SLASHES); ?>;
window.moveProjectId = 0;
window.profileMediaContext = {
    profileUserId: <?php echo (int) $user_id; ?>,
    view: <?php echo json_encode($mediaViewType); ?>
};
window.profileMediaBuildUrl = function (overrides) {
    const url = new URL(window.location.href);
    url.searchParams.set('user_id', <?php echo (int) $user_id; ?>);
    url.searchParams.set('tab', 'media');
    const baseView = <?php echo json_encode($mediaViewType); ?>;
    url.searchParams.set('view', baseView === 'table' ? 'table' : 'grid');
    if (overrides && typeof overrides === 'object') {
        Object.keys(overrides).forEach(function (key) {
            const value = overrides[key];
            if (value === null || value === '' || typeof value === 'undefined') {
                url.searchParams.delete(key);
            } else {
                url.searchParams.set(key, String(value));
            }
        });
    }
    return url.toString();
};

function openFolder(folderId) {
    window.location.href = window.profileMediaBuildUrl({ folder: folderId, page: 1 });
}
function filterByFileType(fileType) {
    const nextType = (!fileType || fileType === 'all') ? null : fileType;
    window.location.href = window.profileMediaBuildUrl({ file_type: nextType, page: 1 });
}
function shareFolder(folderId, folderName) {
    if (window.folderSharing && typeof window.folderSharing.openShareModal === 'function') {
        window.folderSharing.openShareModal(folderId, folderName);
    } else if (window.mediaFolderSharing && typeof window.mediaFolderSharing.openShareModal === 'function') {
        window.mediaFolderSharing.openShareModal(folderId, folderName || '');
    }
}
function shareFile(fileId) {
    if (window.fileVaultSharing && typeof window.fileVaultSharing.openShareModal === 'function') {
        var el = document.querySelector('[data-id="' + fileId + '"][data-type="file"]');
        var nameEl = el ? (el.querySelector('.file-name') || el.querySelector('.tbl-ttl')) : null;
        var fileName = (nameEl && nameEl.textContent) ? nameEl.textContent.trim() : 'File';
        window.fileVaultSharing.openShareModal(fileId, fileName);
    }
}
function shareWithProfileUser(folderId) {
    var fd = new FormData();
    fd.append('action', 'share_with_profile_user');
    fd.append('folder_id', String(folderId || 0));
    fd.append('profile_user_id', String((window.profileMediaContext && window.profileMediaContext.profileUserId) || 0));
    fetch('profile-media-actions.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data || !data.success) {
                alert('Error: ' + ((data && data.error) ? data.error : 'Could not share with client'));
                return;
            }
            window.location.reload();
        })
        .catch(function () {
            alert('Error: Could not share with client');
        });
}
function shareFileWithProfileUser(fileId) {
    var fd = new FormData();
    fd.append('action', 'share_file_with_profile_user');
    fd.append('file_id', String(fileId || 0));
    fd.append('profile_user_id', String((window.profileMediaContext && window.profileMediaContext.profileUserId) || 0));
    fetch('profile-media-actions.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data || !data.success) {
                alert('Error: ' + ((data && data.error) ? data.error : 'Could not share file with client'));
                return;
            }
            window.location.reload();
        })
        .catch(function () {
            alert('Error: Could not share file with client');
        });
}
function removeSharedWithProfileUser(folderId) {
    var fd = new FormData();
    fd.append('action', 'remove_shared_with_profile_user');
    fd.append('folder_id', String(folderId || 0));
    fd.append('profile_user_id', String((window.profileMediaContext && window.profileMediaContext.profileUserId) || 0));
    fetch('profile-media-actions.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data || !data.success) {
                alert('Error: ' + ((data && data.error) ? data.error : 'Could not remove shared state'));
                return;
            }
            window.location.reload();
        })
        .catch(function () {
            alert('Error: Could not remove shared state');
        });
}
function removeFileSharedWithProfileUser(fileId) {
    var fd = new FormData();
    fd.append('action', 'remove_file_shared_with_profile_user');
    fd.append('file_id', String(fileId || 0));
    fd.append('profile_user_id', String((window.profileMediaContext && window.profileMediaContext.profileUserId) || 0));
    fetch('profile-media-actions.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data || !data.success) {
                alert('Error: ' + ((data && data.error) ? data.error : 'Could not remove shared state'));
                return;
            }
            window.location.reload();
        })
        .catch(function () {
            alert('Error: Could not remove shared state');
        });
}

document.addEventListener('DOMContentLoaded', function () {
    initializeMediaCore(
        0,
        <?php echo $current_folder_id ? (int) $current_folder_id : 'null'; ?>,
        <?php echo (int) $currentPage; ?>,
        <?php echo (int) $totalPages; ?>,
        <?php echo (int) $session->userId; ?>,
        <?php echo (int) ($_SESSION['accountStatus'] ?? 0); ?>
    );
    initializeFormHandlers();
    initializeImageLoading();
    setupFileThumbnailHandlers();
    setupFolderDoubleClick();
    initializeModalAutoOpen();
    hideDeleteOptionsForNonOwners();

});
</script>
<?php endif; ?>
<?php if ($tab == 'notes'): ?>
<?php
if (empty($url) && class_exists('settings')) {
	$__ds = settings::findById(1);
	$url = $__ds && !empty($__ds->url) ? (string) $__ds->url : '';
}
$__urlBase = isset($url) && $url !== '' ? rtrim((string) $url, '/') . '/' : '';
?>
<script>window.baseUrl = <?php echo json_encode($__urlBase, JSON_UNESCAPED_SLASHES); ?>; window.csrfToken = <?php echo json_encode($profileNotesCsrfToken, JSON_UNESCAPED_UNICODE); ?>;</script>
<?php
$__mediaReplaceModal = __DIR__ . '/../templates/modals/media-replace-modal.php';
$__mediaReplaceJs = __DIR__ . '/../assets/js/media-replace-modal.js';
if (empty($profileMediaLocked) && is_file($__mediaReplaceModal) && is_file($__mediaReplaceJs)) {
    include $__mediaReplaceModal;
    echo '<script src="../assets/js/media-replace-modal.js?v=' . (int) @filemtime($__mediaReplaceJs) . '"></script>' . "\n";
}
?>
<?php
$pnNoteId = 0;
if (isset($edit_note) && is_array($edit_note) && !empty($edit_note['id'])) {
    $pnNoteId = (int) $edit_note['id'];
} elseif (isset($_GET['note_id']) && (int) $_GET['note_id'] > 0) {
    $pnNoteId = (int) $_GET['note_id'];
}
?>
<script>
window.PROFILE_AUTOSAVE = {
	enabled: true,
	profileUserId: <?php echo (int) $user_id; ?>,
	noteId: <?php echo (int) $pnNoteId; ?>,
	csrfToken: <?php echo json_encode($profileNotesCsrfToken, JSON_UNESCAPED_UNICODE); ?>,
	ajaxBase: <?php echo json_encode('../ajax/profile_notes/'); ?>,
	profileUrlBase: <?php echo json_encode('profile'); ?>
};
</script>
<?php $pnaV = @filemtime(__DIR__ . '/../assets/js/profile-autosave.js') ?: time(); ?>
<script src="../assets/js/profile-autosave.js?v=<?php echo (int) $pnaV; ?>"></script>
<?php require_once __DIR__ . '/../includes/private_notes/share_note_modal.php'; ?>
<?php $pnShareV = @filemtime(__DIR__ . '/../assets/js/profile-notes-share.js') ?: time(); ?>
<script>
window.PROFILE_NOTE_SHARE = {
	subjectUserId: <?php echo (int) $user_id; ?>,
	profileNoteId: 0,
	ajaxBase: <?php echo json_encode('../ajax/profile_notes/'); ?>,
	csrfToken: <?php echo json_encode($profileNotesCsrfToken, JSON_UNESCAPED_UNICODE); ?>
};
</script>
<script src="../assets/js/profile-notes-share.js?v=<?php echo (int) $pnShareV; ?>"></script>
<?php endif; ?>

<?php $__aiCtx = __DIR__ . '/../includes/ai_contextual_snippet.php';
if (is_file($__aiCtx)) { require_once $__aiCtx; if (function_exists('ai_contextual_emit')) { ai_contextual_emit(); } } ?>
<?php  include("../templates/payment-footer.php"); ?>
<script src="<?php echo $url; ?>assets/js/copy-link.js"></script>
<?php if ($tab === 'invoice'): ?>
<script src="<?php echo htmlspecialchars(rtrim((string) $url, '/') . '/assets/js/toast.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php if (!empty($toast_flash)): ?>
<script>window.__toastFlash=<?php echo json_encode($toast_flash, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;</script>
<script>if (typeof consumeToastFlash === 'function') { consumeToastFlash(); }</script>
<?php endif; ?>
<?php include("../templates/client-invoice-view-modal-bundle.php"); ?>
<?php endif; ?>

<script>
// Pass profile user ID to JavaScript for Load More functionality
window.profileUserId = <?= $user_id ?>;
</script> 

<?php if(isset($_POST["edit-mile"])){ ?>
<script type="text/javascript">
// Use Bootstrap 5 way to show modal
var myModal = document.getElementById('edit-milestone');
if (myModal) {
  var modal = new bootstrap.Modal(myModal);
  modal.show();
}
</script>
<?php } ?>
<?php
$message = "";
	if(isset($_POST['edit-milestone-1']))
	{
		$mile = milestone::findById($_POST['editId']); 
		
		$flag=0;
		if($flag==0)
		{
			
			$mile->id        		=  $_POST['editId'];
			$mile->title	=$_POST['title1'];
				$mile->budget		=$_POST['amount1'];
				$mile->deadline		=$_POST['deadline1'];
				$mile->releaseDate		= $_POST['releaseDate'];
				$mile->status	= (int)$_POST['status1'];
				
				$saveMile=$mile->save();
	
				if($saveMile)
				{
					// Send notification if invoice is marked as paid
					if (isset($_POST['status1']) && (int)$_POST['status1'] === 1) {
						require_once('../includes/notification_helper.php');
						NotificationHelper::invoicePaid($mile->id, $mile->title, $session->userId, $mile->p_id);
					}
					header('location:profile?user_id='.$user_id.'&tab=invoice&message=updated'); 
				}
				else
				{
header('location:profile?user_id='.$user_id.'&tab=invoice&message=notupdated'); 
				}
			}
		}

	// Handle "Mark as Paid"
	if (isset($_POST['mark_as_paid']) && isset($_POST['invoice_id'])) {
		$invoice_id = (int)$_POST['invoice_id'];
		$milestone = milestone::findById($invoice_id);
		if ($milestone) {
			$milestone->status = 1; // Paid
			if ($milestone->save()) {
				// Send notification
				require_once('../includes/notification_helper.php');
				NotificationHelper::invoicePaid($milestone->id, $milestone->title, $session->userId, $milestone->p_id);
				header('Location: profile?user_id='.$user_id.'&tab=invoice&message=marked_as_paid');
			} else {
				header('Location: profile?user_id='.$user_id.'&tab=invoice&message=update_failed');
			}
			exit;
		}
	}

	// Handle "Cancel Invoice"
	if (isset($_POST['cancel_invoice']) && isset($_POST['invoice_id'])) {
		$invoice_id = (int)$_POST['invoice_id'];
		$milestone = milestone::findById($invoice_id);
		if ($milestone) {
			$milestone->status = 2; // Cancel
			if ($milestone->save()) {
				header('Location: profile?user_id='.$user_id.'&tab=invoice&message=cancelled');
			} else {
				header('Location: profile?user_id='.$user_id.'&tab=invoice&message=update_failed');
			}
			exit;
		}
	}

	// Handle milestone deletion
	if(isset($_POST['delete-mile']) && isset($_POST['delete_id']))
	{
		$delete_id = (int)$_POST['delete_id'];
		$milestone = milestone::findById($delete_id);
		
		if($milestone)
		{
			$delete_result = $milestone->delete();
			if($delete_result)
			{
				header('location:profile?user_id='.$user_id.'&tab=invoice&message=deleted'); 
			}
			else
			{
				header('location:profile?user_id='.$user_id.'&tab=invoice&message=delete_failed'); 
			}
		}
		else
		{
			header('location:profile?user_id='.$user_id.'&tab=invoice&message=not_found'); 
		}
	}

?>

<script>

// User-specific financial data for profile page
<?php
// Calculate user-specific monthly financial data
$present_year = date('Y');
$user_monthly_earnings = [];
$user_monthly_unpaid = [];
$user_month_labels = [];

$is_staff_profile = (isset($profileUser) && ((int)$profileUser->accountStatus === 3 || (int)$profileUser->accountStatus === 1));

for ($m = 1; $m <= 12; $m++) {
    $user_month_labels[] = $present_year . ' ' . date('M', mktime(0, 0, 0, $m, 1));
    
    $currency_condition = '';
    if ($defaultCurrency && $defaultCurrency !== 'all') {
        $currency_parts = explode(',', $defaultCurrency);
        $currency_code = trim($currency_parts[0]);
        $currency_condition = " AND (m.currency LIKE '{$currency_code},%' OR m.currency = '{$currency_code}' OR m.currency LIKE '%,{$currency_code}')";
    }
    
    if ($is_staff_profile) {
        // Staff: sales they created
        $user_paid_milestones = milestone::findBySql(
            "SELECT m.*, m.budget, m.sales_tax, m.sales_tax_type, m.discount, m.discount_type FROM milestones m 
             WHERE m.status = 1 
             AND YEAR(m.releaseDate) = " . (int)$present_year . " 
             AND MONTH(m.releaseDate) = " . (int)$m . "
             AND m.created_by = $user_id
             {$currency_condition}"
        );
        $user_unpaid_milestones = milestone::findBySql(
            "SELECT m.*, m.budget, m.sales_tax, m.sales_tax_type, m.discount, m.discount_type FROM milestones m 
             WHERE m.status = 0 
             AND YEAR(m.deadline) = " . (int)$present_year . " 
             AND MONTH(m.deadline) = " . (int)$m . "
             AND m.created_by = $user_id
             {$currency_condition}"
        );
    } else {
        // Client: milestones for their projects AND direct client invoices
        // Project invoices: only show for main client (not additional clients)
        $user_paid_milestones = milestone::findBySql(
            "SELECT m.*, m.budget, m.sales_tax, m.sales_tax_type, m.discount, m.discount_type FROM milestones m 
             LEFT JOIN projects p ON m.p_id = p.p_id 
             WHERE m.status = 1 
             AND YEAR(m.releaseDate) = " . (int)$present_year . " 
             AND MONTH(m.releaseDate) = " . (int)$m . "
             AND ((m.p_id IS NOT NULL AND m.p_id > 0 AND p.p_id IS NOT NULL AND (p.c_id = $user_id OR p.main_client_id = $user_id)) OR ((m.p_id IS NULL OR m.p_id = 0) AND m.c_id = $user_id))
             {$currency_condition}"
        );
        $user_unpaid_milestones = milestone::findBySql(
            "SELECT m.*, m.budget, m.sales_tax, m.sales_tax_type, m.discount, m.discount_type FROM milestones m 
             LEFT JOIN projects p ON m.p_id = p.p_id 
             WHERE m.status = 0 
             AND YEAR(m.deadline) = " . (int)$present_year . " 
             AND MONTH(m.deadline) = " . (int)$m . "
             AND ((m.p_id IS NOT NULL AND m.p_id > 0 AND p.p_id IS NOT NULL AND (p.c_id = $user_id OR p.main_client_id = $user_id)) OR ((m.p_id IS NULL OR m.p_id = 0) AND m.c_id = $user_id))
             {$currency_condition}"
        );
    }
    
    // Calculate totals for paid milestones
    $paid_total = 0;
    if ($user_paid_milestones) {
        foreach ($user_paid_milestones as $milestone) {
            // Convert milestone object to array for calculateInvoiceTotal function
            $milestoneArray = [
                'id' => $milestone->id,
                'budget' => $milestone->budget,
                'sales_tax' => $milestone->sales_tax ?? 0,
                'sales_tax_type' => $milestone->sales_tax_type ?? 'percentage',
                'discount' => $milestone->discount ?? 0,
                'discount_type' => $milestone->discount_type ?? 'percentage'
            ];
            $paid_total += calculateInvoiceTotal($milestoneArray);
        }
    }
    
    // Calculate totals for unpaid milestones
    $unpaid_total = 0;
    if ($user_unpaid_milestones) {
        foreach ($user_unpaid_milestones as $milestone) {
            // Convert milestone object to array for calculateInvoiceTotal function
            $milestoneArray = [
                'id' => $milestone->id,
                'budget' => $milestone->budget,
                'sales_tax' => $milestone->sales_tax ?? 0,
                'sales_tax_type' => $milestone->sales_tax_type ?? 'percentage',
                'discount' => $milestone->discount ?? 0,
                'discount_type' => $milestone->discount_type ?? 'percentage'
            ];
            $unpaid_total += calculateInvoiceTotal($milestoneArray);
        }
    }
    
    $user_monthly_earnings[] = $paid_total;
    $user_monthly_unpaid[] = $unpaid_total;
}

// Calculate user totals from monthly arrays
$user_total_paid = array_sum($user_monthly_earnings);
$user_total_unpaid = array_sum($user_monthly_unpaid);

// For client profiles, also calculate totals without date filtering to ensure all invoices are included
if (!$is_staff_profile) {
    // Recalculate totals for all invoices (not just current year) to ensure direct client invoices are included
    $all_paid_query = "SELECT m.*, m.budget, m.sales_tax, m.sales_tax_type, m.discount, m.discount_type FROM milestones m 
                       LEFT JOIN projects p ON m.p_id = p.p_id 
                       WHERE m.status = 1 
                       AND ((m.p_id IS NOT NULL AND m.p_id > 0 AND p.p_id IS NOT NULL AND (p.c_id = $user_id OR p.main_client_id = $user_id)) OR ((m.p_id IS NULL OR m.p_id = 0) AND m.c_id = $user_id))";
    
    if ($defaultCurrency && $defaultCurrency !== 'all') {
        $currency_parts = explode(',', $defaultCurrency);
        $currency_code = trim($currency_parts[0]);
        $all_paid_query .= " AND (m.currency LIKE '{$currency_code},%' OR m.currency = '{$currency_code}' OR m.currency LIKE '%,{$currency_code}')";
    }
    
    $all_unpaid_query = "SELECT m.*, m.budget, m.sales_tax, m.sales_tax_type, m.discount, m.discount_type FROM milestones m 
                         LEFT JOIN projects p ON m.p_id = p.p_id 
                         WHERE m.status = 0 
                         AND ((m.p_id IS NOT NULL AND m.p_id > 0 AND p.p_id IS NOT NULL AND (p.c_id = $user_id OR p.main_client_id = $user_id)) OR ((m.p_id IS NULL OR m.p_id = 0) AND m.c_id = $user_id))";
    
    if ($defaultCurrency && $defaultCurrency !== 'all') {
        $currency_parts = explode(',', $defaultCurrency);
        $currency_code = trim($currency_parts[0]);
        $all_unpaid_query .= " AND (m.currency LIKE '{$currency_code},%' OR m.currency = '{$currency_code}' OR m.currency LIKE '%,{$currency_code}')";
    }
    
    $all_paid_milestones = milestone::findBySql($all_paid_query);
    $all_unpaid_milestones = milestone::findBySql($all_unpaid_query);
    
    // Recalculate totals from all invoices
    $all_paid_total = 0;
    if ($all_paid_milestones) {
        foreach ($all_paid_milestones as $milestone) {
            $milestoneArray = [
                'id' => $milestone->id,
                'budget' => $milestone->budget,
                'sales_tax' => $milestone->sales_tax ?? 0,
                'sales_tax_type' => $milestone->sales_tax_type ?? 'percentage',
                'discount' => $milestone->discount ?? 0,
                'discount_type' => $milestone->discount_type ?? 'percentage'
            ];
            $all_paid_total += calculateInvoiceTotal($milestoneArray);
        }
    }
    
    $all_unpaid_total = 0;
    if ($all_unpaid_milestones) {
        foreach ($all_unpaid_milestones as $milestone) {
            $milestoneArray = [
                'id' => $milestone->id,
                'budget' => $milestone->budget,
                'sales_tax' => $milestone->sales_tax ?? 0,
                'sales_tax_type' => $milestone->sales_tax_type ?? 'percentage',
                'discount' => $milestone->discount ?? 0,
                'discount_type' => $milestone->discount_type ?? 'percentage'
            ];
            $all_unpaid_total += calculateInvoiceTotal($milestoneArray);
        }
    }
    
    // Use the totals from all invoices (not just current year) for the display
    $user_total_paid = $all_paid_total;
    $user_total_unpaid = $all_unpaid_total;
}
?>

window.monthLabels = <?php echo json_encode($user_month_labels); ?>;
window.monthlyEarnings = <?php echo json_encode($user_monthly_earnings); ?>;
window.monthlyUnpaid = <?php echo json_encode($user_monthly_unpaid); ?>;
window.monthlyCancelled = <?php echo json_encode(array_fill(0, count($user_month_labels), 0)); ?>;
window.userTotalPaid = <?php echo $user_total_paid; ?>;
window.userTotalUnpaid = <?php echo $user_total_unpaid; ?>;
window.userTotalCancelled = 0;
window.userTotalTax = 0;
window.defaultCurrency = <?php echo json_encode($defaultCurrency ?? 'USD,$'); ?>;
window.userPreferredCurrency = <?php echo json_encode($userPreferredCurrency ?? ($defaultCurrency ?? 'USD,$')); ?>;
window.salesPaidLabel = <?php echo json_encode($lang['Paid'] ?? 'Paid'); ?>;
window.salesUnpaidLabel = <?php echo json_encode($lang['Unpaid'] ?? 'Unpaid'); ?>;
window.salesCancelledLabel = <?php echo json_encode($lang['Cancel'] ?? 'Cancel'); ?>;
window.salesTaxLabel = <?php echo json_encode($lang['Sales Tax'] ?? 'Sales Tax'); ?>;
window.invoicePaidLabel = <?php echo json_encode($lang['Paid Invoices'] ?? 'Paid Invoices'); ?>;
window.invoiceUnpaidLabel = <?php echo json_encode($lang['Unpaid'] ?? 'Unpaid'); ?>;
window.selectedCurrency = window.defaultCurrency;

</script>
