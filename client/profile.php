<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : profile.php
   Purpose : Manages client profiles (client area)
 ================================================================================
 */
ob_start();
require_once("../includes/lib-initialize.php");
require_once("../includes/reports_common_helper.php");
require_once("../includes/stat_sparkline_helper.php");
require_once("../includes/milestone_invoice_total.php");
require_once("../includes/company_profile_sales_helper.php");
$title = $lang['Profile'] . " | ". $syatem_title;
include("../templates/header.php");

// Only allow logged-in clients
if (!($session->isLoggedIn()) || $_SESSION['accountStatus'] != 2) {
    redirectTo($url."index.php");
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $session->userId;
// Clients can only access their own profile - redirect to their own profile
if ($user_id <= 0 || $user_id != $session->userId) {
    header('Location: profile?user_id=' . $session->userId);
    exit;
}
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'overview';

// Load task permissions for client
require_once("../includes/task_permission.php");
$taskPermissions = TaskPermission::getOrCreate($session->userId);
$settingsForModules = settings::findById(1);
$invoiceModuleEnabled = !empty($settingsForModules->module_invoices);
$fileManagementModuleEnabled = !empty($settingsForModules->module_file_management);
$isFreeEdition = function_exists('tasksession_is_free_edition') && tasksession_is_free_edition();
$profileMediaLocked = $isFreeEdition;
$profileMediaTabVisible = $fileManagementModuleEnabled || $isFreeEdition;
$canUseInvoiceWidgets = $invoiceModuleEnabled && !empty($taskPermissions->can_view_milestones);
if ($tab === 'invoice' && !$canUseInvoiceWidgets) {
    $tab = 'overview';
}
if ($tab === 'media' && !$profileMediaTabVisible) {
    $tab = 'overview';
}

$profileMediaCanView = false;
$canUploadFiles = false;
$canEditFiles = false;
$canDeleteFiles = false;
$canDeleteOthersFiles = false;
$canDeleteFolders = false;
$canMoveFiles = false;
$canEditOthersFiles = false;
$canDownloadFiles = false;
$canCreateFolders = false;
$canEditFolderPermissions = false;
if ($tab === 'media' && $fileManagementModuleEnabled && empty($profileMediaLocked)) {
    $profileMediaCanView = true; // client can only open own profile
    $canUploadFiles = true;
    $canEditFiles = true;
    $canDeleteFiles = true;
    $canDeleteOthersFiles = false;
    $canDeleteFolders = true;
    $canMoveFiles = true;
    $canEditOthersFiles = false;
    $canDownloadFiles = true;
    $canCreateFolders = true;
    $canEditFolderPermissions = false;
}

// Safe defaults for analytics JS variables
$defaultCurrency = 'USD,$';
$userPreferredCurrency = 'USD,$';

$user = User::findById((int)$id); //take the record of current user in an object array 	
$username=$user->firstName;;

// Get profile user data early for use in counter calculations
$profileUser = User::findById($user_id);

// Calculate user-specific project statistics for counter boxes
$user_projects = projects::findBySql("SELECT * FROM projects WHERE (c_id = $user_id OR main_client_id = $user_id OR FIND_IN_SET($user_id, c_ids) > 0) AND archive = 0 AND trash != 1");
if (!is_array($user_projects)) { $user_projects = []; }

// Calculate user-specific milestone counts for counter boxes (client only)
// Include both project invoices and direct client invoices (same scope as client dashboard).
$invoiceScope = "((m.p_id IS NOT NULL AND m.p_id > 0 AND p.p_id IS NOT NULL AND (p.c_id = {$user_id} OR p.main_client_id = {$user_id} OR FIND_IN_SET({$user_id}, p.c_ids) > 0)) OR ((m.p_id IS NULL OR m.p_id = 0) AND m.c_id = {$user_id}))";
$user_paid_milestones_count = 0;
$user_unpaid_milestones_count = 0;
$paidCountRow = $database->fetchArray($database->query(
    "SELECT COUNT(*) AS count FROM milestones m LEFT JOIN projects p ON m.p_id = p.p_id WHERE m.status = 1 AND {$invoiceScope}"
));
$user_paid_milestones_count = (int) ($paidCountRow['count'] ?? 0);
$unpaidCountRow = $database->fetchArray($database->query(
    "SELECT COUNT(*) AS count FROM milestones m LEFT JOIN projects p ON m.p_id = p.p_id WHERE m.status = 0 AND {$invoiceScope}"
));
$user_unpaid_milestones_count = (int) ($unpaidCountRow['count'] ?? 0);

$sparkUid = (int)$user_id;
$sparkFrom = $database->escapeValue(date('Y-m-01', strtotime('-11 months')));
$sparkProjects = statSparklineSeriesFromQuery(
    $database,
    "SELECT DATE_FORMAT(start_time, '%Y-%m') AS ym, COUNT(*) AS c
     FROM projects
     WHERE (c_id = {$sparkUid} OR main_client_id = {$sparkUid} OR FIND_IN_SET({$sparkUid}, c_ids) > 0)
       AND archive = 0 AND trash != 1
       AND start_time >= '{$sparkFrom}'
     GROUP BY ym"
);
$sparkTasks = statSparklineSeriesFromQuery(
    $database,
    "SELECT DATE_FORMAT(t.created_at, '%Y-%m') AS ym, COUNT(*) AS c
     FROM tasks t
     INNER JOIN projects p ON t.project_id = p.p_id
     WHERE (p.c_id = {$sparkUid} OR p.main_client_id = {$sparkUid} OR FIND_IN_SET({$sparkUid}, p.c_ids) > 0)
       AND t.created_at >= '{$sparkFrom}'
     GROUP BY ym"
);
$sparkUnpaid = statSparklineSeriesFromQuery(
    $database,
    "SELECT DATE_FORMAT(" . reports_sql_milestone_effective_date('m.') . ", '%Y-%m') AS ym, COUNT(*) AS c
     FROM milestones m
     LEFT JOIN projects p ON m.p_id = p.p_id
     WHERE m.status = 0
       AND {$invoiceScope}
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
       AND {$invoiceScope}
       AND m.releaseDate >= '{$sparkFrom}'
     GROUP BY ym"
);
$sparkSession = statSparklineSeriesFromQuery(
    $database,
    "SELECT DATE_FORMAT(attempt_time, '%Y-%m') AS ym, COUNT(*) AS c
     FROM login_attempts
     WHERE user_id = {$sparkUid} AND type = 'login' AND attempt_time >= '{$sparkFrom}'
     GROUP BY ym"
);

// Display-only meta for spark cards
$projectsNewThisMonth = !empty($sparkProjects) ? (int) end($sparkProjects) : 0;
$projectsNewLastMonth = (count($sparkProjects) > 1) ? (int) $sparkProjects[count($sparkProjects) - 2] : 0;
$projectsPctChange = $projectsNewLastMonth > 0
    ? (int) round((($projectsNewThisMonth - $projectsNewLastMonth) / $projectsNewLastMonth) * 100)
    : ($projectsNewThisMonth > 0 ? 100 : 0);
$tasksNewThisMonth = !empty($sparkTasks) ? (int) end($sparkTasks) : 0;

$invoiceOverviewTotal = (int) $user_paid_milestones_count + (int) $user_unpaid_milestones_count;
$paidAmountAll = 0.0;
$unpaidAmount = 0.0;
$invoicePaidPctChange = 0;
$dashCurrencyCode = 'USD';
$dashCurrencySymbol = '$';
$clientCurrencyRaw = '';
$clientInvoiceCurrencies = [];
$fmtDashMoney = static function ($amount, $symbol) {
    return $symbol . number_format((float) $amount, 2, '.', ',');
};

$lastSessionDateDisplay = 'No data';
$lastLoginQuery = "SELECT attempt_time FROM login_attempts WHERE user_id = " . (int) $user_id . " AND type = 'login' ORDER BY attempt_time DESC LIMIT 1";
$lastLoginResult = mysqli_query($connect, $lastLoginQuery);
if ($lastLoginResult) {
    $lastLogin = mysqli_fetch_assoc($lastLoginResult);
    if ($lastLogin && !empty($lastLogin['attempt_time'])) {
        $lastSessionDateDisplay = date('M j, Y', strtotime($lastLogin['attempt_time']));
    }
}

if ($canUseInvoiceWidgets) {
    $currRes = $database->query(
        "SELECT DISTINCT m.currency FROM milestones m
         LEFT JOIN projects p ON m.p_id = p.p_id
         WHERE {$invoiceScope}
           AND m.currency IS NOT NULL AND m.currency != ''
         ORDER BY m.currency"
    );
    if ($currRes) {
        while ($crow = $database->fetchArray($currRes)) {
            $cur = trim((string) ($crow['currency'] ?? ''));
            if ($cur !== '') {
                $clientInvoiceCurrencies[] = $cur;
            }
        }
    }

    if (isset($profileUser) && is_object($profileUser) && !empty($profileUser->currency)) {
        $clientCurrencyRaw = trim((string) $profileUser->currency);
    }
    if ($clientCurrencyRaw === '') {
        $clientCurrencyRaw = trim((string) ($settingsForModules->system_currency ?? 'USD,$'));
    }
    if ($clientCurrencyRaw === '') {
        $clientCurrencyRaw = 'USD,$';
    }

    if (!empty($clientInvoiceCurrencies)) {
        $clientInvoiceCurrencies = profile_sales_order_currencies($clientInvoiceCurrencies, $settingsForModules, $clientCurrencyRaw);
        $defaultOpt = profile_sales_default_currency_option($clientInvoiceCurrencies, $settingsForModules, $clientCurrencyRaw);
        $clientCurrencyRaw = $defaultOpt['value'];
    }

    $curParts = explode(',', $clientCurrencyRaw);
    $dashCurrencyCode = trim($curParts[0]) !== '' ? trim($curParts[0]) : 'USD';
    $dashCurrencySymbol = isset($curParts[1]) && trim($curParts[1]) !== '' ? trim($curParts[1]) : $dashCurrencyCode;
    $currencyLike = $database->escapeValue($dashCurrencyCode);
    $currencyCondition = " AND (m.currency LIKE '{$currencyLike},%' OR m.currency = '{$currencyLike}' OR m.currency LIKE '%,{$currencyLike}')";

    $sparkPaid = statSparklineSeriesFromQuery(
        $database,
        "SELECT DATE_FORMAT(m.releaseDate, '%Y-%m') AS ym, COUNT(*) AS c
         FROM milestones m
         LEFT JOIN projects p ON m.p_id = p.p_id
         WHERE m.status = 1 AND {$invoiceScope} AND m.releaseDate >= '{$sparkFrom}'
           {$currencyCondition}
         GROUP BY ym"
    );
    $sparkUnpaid = statSparklineSeriesFromQuery(
        $database,
        "SELECT DATE_FORMAT(" . reports_sql_milestone_effective_date('m.') . ", '%Y-%m') AS ym, COUNT(*) AS c
         FROM milestones m
         LEFT JOIN projects p ON m.p_id = p.p_id
         WHERE m.status = 0 AND {$invoiceScope}
           AND " . reports_sql_milestone_effective_date('m.') . " IS NOT NULL
           AND " . reports_sql_milestone_effective_date('m.') . " >= '{$sparkFrom}'
           {$currencyCondition}
         GROUP BY ym"
    );

    $paidMilestonesAll = milestone::findBySql(
        "SELECT m.* FROM milestones m LEFT JOIN projects p ON m.p_id = p.p_id
         WHERE (m.status = '1' OR m.status = 1) AND {$invoiceScope}{$currencyCondition}"
    );
    if (is_array($paidMilestonesAll)) {
        foreach ($paidMilestonesAll as $pm) {
            $paidAmountAll += milestone_calculate_invoice_total($pm);
        }
        $user_paid_milestones_count = count($paidMilestonesAll);
    }
    $unpaidMilestonesAll = milestone::findBySql(
        "SELECT m.* FROM milestones m LEFT JOIN projects p ON m.p_id = p.p_id
         WHERE (m.status = '0' OR m.status = 0) AND {$invoiceScope}{$currencyCondition}"
    );
    if (is_array($unpaidMilestonesAll)) {
        foreach ($unpaidMilestonesAll as $um) {
            $unpaidAmount += milestone_calculate_invoice_total($um);
        }
        $user_unpaid_milestones_count = count($unpaidMilestonesAll);
    }
    $invoiceOverviewTotal = (int) $user_paid_milestones_count + (int) $user_unpaid_milestones_count;

    $dashMetaMonthStart = date('Y-m-01');
    $dashMetaMonthEnd = date('Y-m-t');
    $dashMetaLastStart = date('Y-m-01', strtotime('-1 month'));
    $dashMetaLastEnd = date('Y-m-t', strtotime('-1 month'));
    $paidThisAmount = 0.0;
    $paidThisMilestones = milestone::findBySql(
        "SELECT m.* FROM milestones m LEFT JOIN projects p ON m.p_id = p.p_id
         WHERE (m.status = '1' OR m.status = 1)
           AND m.releaseDate IS NOT NULL
           AND m.releaseDate >= '" . $database->escapeValue($dashMetaMonthStart) . "'
           AND m.releaseDate <= '" . $database->escapeValue($dashMetaMonthEnd) . "'
           AND {$invoiceScope}{$currencyCondition}"
    );
    if (is_array($paidThisMilestones)) {
        foreach ($paidThisMilestones as $pm) {
            $paidThisAmount += milestone_calculate_invoice_total($pm);
        }
    }
    $paidLastAmount = 0.0;
    $paidLastMilestones = milestone::findBySql(
        "SELECT m.* FROM milestones m LEFT JOIN projects p ON m.p_id = p.p_id
         WHERE (m.status = '1' OR m.status = 1)
           AND m.releaseDate IS NOT NULL
           AND m.releaseDate >= '" . $database->escapeValue($dashMetaLastStart) . "'
           AND m.releaseDate <= '" . $database->escapeValue($dashMetaLastEnd) . "'
           AND {$invoiceScope}{$currencyCondition}"
    );
    if (is_array($paidLastMilestones)) {
        foreach ($paidLastMilestones as $pm) {
            $paidLastAmount += milestone_calculate_invoice_total($pm);
        }
    }
    $invoicePaidPctChange = $paidLastAmount > 0
        ? (int) round((($paidThisAmount - $paidLastAmount) / $paidLastAmount) * 100)
        : ($paidThisAmount > 0 ? 100 : 0);
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
<?php if ($tab === 'media' && $fileManagementModuleEnabled && empty($profileMediaLocked)): ?>
<?php
if (!function_exists('mv_vault_asset_ver')) {
    $__mv = __DIR__ . '/../includes/mv_vault_server_profile.php';
if (is_file($__mv)) { require_once $__mv; }
}
?>
<link rel="stylesheet" href="../vendor/google/gdrive/assets/css/file-sharing.css?v=<?php echo mv_vault_asset_ver('vendor/google/gdrive/assets/css/file-sharing.css'); ?>">
<?php endif; ?>
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
													 <?php if ($tab === 'media'): ?>
													 <link rel="stylesheet" href="../assets/css/kanban-bulk.css?v=9">
													 <?php endif; ?>
													 <?php if ($tab === 'overview'): ?>
													 <link rel="stylesheet" href="<?php echo $url; ?>assets/css/reports.css">
													 <?php endif; ?>
													 <div class="row">
														<div class="project-tabs-header">
															<div class="scrollable-tabs-container d-flex col-gap-40 col-gap-40-sep">
																	<div class="icon-container sep">
																	  <a href="profile?user_id=<?php echo $user_id; ?>&tab=overview"
																		 class="<?php echo ($tab == 'overview') ? 'active' : ''; ?>">
																		<?php echo ts_icon('chart-pie'); ?>
																	<?php echo $lang['Profile Stats']; ?>
																	  </a>
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
															</div>
													   </div>   
												   </div> 
								<?php if ($tab == 'media' && empty($profileMediaLocked)): ?>
							 <div class="search">
								<div class="search-icon border-btn-a" onclick="toggleSearch()">
									<?php echo ts_icon('search', 'w-2'); ?>
									</div>
									<form method="GET" action="profile" class="search-form" id="searchForm">
										<input type="hidden" name="user_id" value="<?php echo (int) $user_id; ?>">
										<input type="hidden" name="tab" value="media">
										<?php
										$__pmViewSearch = (isset($_GET['view']) && (string) $_GET['view'] === 'table') ? 'table' : 'grid';
										$__pmFolderSearch = isset($_GET['folder']) ? (int) $_GET['folder'] : 0;
										$__pmTypeSearch = isset($_GET['file_type']) ? (string) $_GET['file_type'] : '';
										?>
										<input type="hidden" name="view" value="<?php echo htmlspecialchars($__pmViewSearch, ENT_QUOTES, 'UTF-8'); ?>">
										<?php if ($__pmFolderSearch > 0): ?>
											<input type="hidden" name="folder" value="<?php echo (int) $__pmFolderSearch; ?>">
										<?php endif; ?>
										<?php if (in_array($__pmTypeSearch, ['image', 'document', 'other'], true)): ?>
											<input type="hidden" name="file_type" value="<?php echo htmlspecialchars($__pmTypeSearch, ENT_QUOTES, 'UTF-8'); ?>">
										<?php endif; ?>
										<div class="input-group">
										<span class="search-field-icon">
											<?php echo ts_icon('search', 'w-2'); ?>
										</span>
											<input type="text" id="client-search" name="search" class="form-control" placeholder="<?php echo htmlspecialchars($lang['Search Files'] ?? 'Search Files', ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
											<?php if (isset($_GET['search']) && (string) $_GET['search'] !== ''): ?>
											<?php
											$__clearMediaSearchQ = ['user_id' => (int) $user_id, 'tab' => 'media', 'view' => $__pmViewSearch];
											if ($__pmFolderSearch > 0) { $__clearMediaSearchQ['folder'] = $__pmFolderSearch; }
											if (in_array($__pmTypeSearch, ['image', 'document', 'other'], true)) { $__clearMediaSearchQ['file_type'] = $__pmTypeSearch; }
											$__clearMediaSearchHref = 'profile?' . http_build_query($__clearMediaSearchQ, '', '&', PHP_QUERY_RFC3986);
											?>
										<a href="<?php echo htmlspecialchars($__clearMediaSearchHref, ENT_QUOTES, 'UTF-8'); ?>" class="cross" title="<?php echo htmlspecialchars($lang['Close'] ?? 'Close', ENT_QUOTES, 'UTF-8'); ?>">
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
                                    $__pmBuildFilterHdr = static function ($type) use ($user_id, $__pmFolderHdr, $__pmViewHdr, $__pmSearchHdr) {
                                        $__q = ['user_id' => (int) $user_id, 'tab' => 'media', 'view' => $__pmViewHdr, 'page' => 1];
                                        if ($__pmFolderHdr > 0) { $__q['folder'] = $__pmFolderHdr; }
                                        if ($type !== null && in_array($type, ['image', 'document', 'other'], true)) { $__q['file_type'] = $type; }
                                        if ($__pmSearchHdr !== '') { $__q['search'] = $__pmSearchHdr; }
                                        return 'profile?' . http_build_query($__q, '', '&', PHP_QUERY_RFC3986);
                                    };
                                    ?>
                                    <div class="icon-container sep" id="profileMediaBulkToolbar">
                                        <div class="pm-trash task-trash align-middle d-flex col-gap-5">
                                            <?php if ($canDeleteFiles || $canMoveFiles || $canDownloadFiles): ?>
                                            <a href="#" onclick="enterBulkMode(); return false;" class="bulk-delete-tab red border-btn-a" id="bulkSelectTab" title="<?php echo htmlspecialchars($lang['Bulk Select'] ?? 'Bulk select', ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($lang['Bulk Select'] ?? 'Bulk select', ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo ts_icon('duplicate', 'w-2'); ?>
                                            </a>
                                            <?php endif; ?>
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
                                            <?php if ($canMoveFiles): ?>
                                            <a href="#" onclick="openMoveDialogForSelectedFiles(); return false;" class="bulk-delete-tab border-btn-a" id="bulkMoveTab" style="display:none;">
                                                <?php echo ts_icon('arrow-right', 'w-2'); ?>
                                                <span id="bulkMoveLabel"><?php echo $lang['Move'] ?? 'Move'; ?></span>
                                            </a>
                                            <?php endif; ?>
                                            <?php if ($canDeleteFiles): ?>
                                            <a href="#" onclick="deleteSelectedFiles(); return false;" class="bulk-delete-tab border-btn-a" id="bulkDeleteTab" style="display:none;">
                                                <?php echo ts_icon('delete', 'w-2'); ?>
                                                <span id="bulkDeleteLabel"><?php echo $lang['Delete'] ?? 'Delete'; ?></span>
                                            </a>
                                            <?php endif; ?>
                                            <?php if ($canDownloadFiles): ?>
                                            <a href="#" onclick="downloadSelectedAsZip(); return false;" class="bulk-delete-tab border-btn-a" id="bulkDownloadZipTab" style="display:none;">
                                                <?php echo ts_icon('download', 'w-2'); ?>
                                                <span id="bulkDownloadZipLabel"><?php echo $lang['Download all (ZIP)'] ?? 'Download all (ZIP)'; ?></span>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <!-- Date Range Filter Dropdown for Activity Tab -->
                                    <?php if ($tab == 'activity'): ?>
									<div class="action-toggle border-btn-a collapsed" data-bs-toggle="collapse" data-bs-target="#activityDateRangeDropdown" aria-expanded="false" role="button" tabindex="0">
                                        <span class="action-text"><?php echo $lang['Filters'] ?? 'Filters'; ?></span><span class="mobile-ellipsis"><?php echo ts_icon('ellipsis', 'w-2'); ?></span>
										<?php echo ts_icon('filter', 'w-2'); ?>
									</div>
									<div id="activityDateRangeDropdown" class="toggle-action justify collapse shadow-dept">
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
														<button class="btn primary-btn" id="primary-btn" onclick="selectCustomRange()"><?php echo $lang['Apply']; ?></button>
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
                                    <div class="action-toggle" data-bs-toggle="collapse" data-bs-target="#project-menu<?php echo $recentProject->p_id;?>">
                                        <span class="action-text"><?php echo $lang['Filters']; ?></span><span class="mobile-ellipsis"><?php echo ts_icon('ellipsis'); ?></span>
										<?php echo ts_icon('filter'); ?>
                                    </div>
									
									<div id="project-menu" class="toggle-action justify collapse shadow-dept">
                                        <ul>
										<?php if ($tab == 'overview'): ?>
											<li><a href="edit?editprofile=<?php echo $user_id; ?>" ><?php echo $lang['Edit Profile']; ?></a></li>
										<?php endif; ?>	
										<?php if ($tab == 'media'): ?>
											<?php if (!empty($canUploadFiles)): ?>
											<li><a href="#" onclick="openUploadDialog(); return false;"><?php echo $lang['Upload File'] ?? 'Upload File'; ?></a></li>
											<?php endif; ?>
											<?php if (!empty($canCreateFolders)): ?>
											<li><a href="#" onclick="openCreateFolderModal(); return false;"><?php echo $lang['Create Folder'] ?? 'Create Folder'; ?></a></li>
											<?php endif; ?>
										<?php endif; ?>
                                        </ul>
										</td>
									</div>
							   </div>
							<?php endif; ?>
							   
							<?php if ($tab == 'overview'): ?>
							<div class="d-none d-md-block">
						<a href="edit?editprofile=<?php echo $user_id; ?>" class="btn primary-btn"><?php echo $lang['Edit Profile']; ?></a>
					</div>
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
						<?php if (!empty($canUploadFiles) || !empty($canCreateFolders)): ?>
						<div class="edit-overview-btn d-none d-md-block">
							<div id="primary-btn" class="action-toggle primary-btn collapsed" data-bs-toggle="collapse" data-bs-target="#dropdown-menu" aria-expanded="false" role="button" tabindex="0">
								<span class="action-text"><?php echo $lang['Create New'] ?? 'Create New'; ?></span>
								<?php echo ts_icon('plus', 'w-2'); ?>
							</div>
							<div id="dropdown-menu" class="toggle-action collapse shadow-dept">
								<ul>
									<?php if (!empty($canCreateFolders)): ?>
									<li><a href="#" data-bs-toggle="modal" data-bs-target="#createFolderModal" onclick="return false;"><?php echo ts_icon('folder', 'tasksession-timer-log-menu-ico me-2'); ?><span><?php echo $lang['Create Folder'] ?? 'Create Folder'; ?></span></a></li>
									<?php endif; ?>
									<?php if (!empty($canUploadFiles)): ?>
									<li><a href="#" onclick="document.getElementById('uploadBtn').click(); return false;"><?php echo ts_icon('upload', 'tasksession-timer-log-menu-ico me-2'); ?><span><?php echo $lang['Upload Files'] ?? 'Upload Files'; ?></span></a></li>
									<?php endif; ?>
								</ul>
							</div>
						</div>
						<?php endif; ?>
						<?php endif; ?>
					</div>						
			   </div>
			</div>
		<?php if(isset($message) && (!empty($message))){echo $message;} ?>
							<?php
                                $user = User::findById($user_id);
                                if (($tab == 'overview' || !isset($_GET['tab'])) && $user) {
                                    // Get profile picture using global avatar function
                                    $avatarData = getUserAvatarData($user_id, $user->firstName, $user->lastName ?? '', 150, 150);
                                    $profilePic = $avatarData['type'] === 'image' ? $avatarData['url'] : '';
                                    // Get user statistics
                                    if ($user->accountStatus == 3 || $user->accountStatus == 1) { // staff or admin
                                        // Projects where staff/admin is assigned (s_ids contains user_id)
                                        $totalProjectsQuery = "SELECT COUNT(*) as total FROM projects WHERE FIND_IN_SET($user_id, s_ids)";
                                        $totalProjectsResult = $database->query($totalProjectsQuery);
                                        $totalProjects = $database->fetchArray($totalProjectsResult)['total'] ?? 0;
                                        // Tasks where staff/admin is assigned (assigned_to contains user_id)
                                        $totalTasksQuery = "SELECT COUNT(*) as total FROM tasks WHERE FIND_IN_SET($user_id, assigned_to)";
                                        $totalTasksResult = $database->query($totalTasksQuery);
                                        $totalTasks = $database->fetchArray($totalTasksResult)['total'] ?? 0;
                                    } else { // client
                                        $totalProjectsQuery = "SELECT COUNT(*) as total FROM projects WHERE (c_id = $user_id OR main_client_id = $user_id OR FIND_IN_SET($user_id, c_ids) > 0)";
                                        $totalProjectsResult = $database->query($totalProjectsQuery);
                                        $totalProjects = $database->fetchArray($totalProjectsResult)['total'] ?? 0;
                                        // Total Tasks for client - updated to include additional clients
                                        $projectIdsResult = $database->query("SELECT p_id FROM projects WHERE (c_id = $user_id OR main_client_id = $user_id OR FIND_IN_SET($user_id, c_ids) > 0)");
                                        $projectIds = [];
                                        while ($row = $database->fetchArray($projectIdsResult)) {
                                            $projectIds[] = $row['p_id'];
                                        }
                                        $totalTasks = 0;
                                        if (!empty($projectIds)) {
                                            $projectIdsStr = implode(',', $projectIds);
                                            $taskCountResult = $database->query("SELECT COUNT(DISTINCT t.id) as cnt FROM tasks t WHERE t.project_id IN ($projectIdsStr)");
                                            $taskCountRow = $database->fetchArray($taskCountResult);
                                            $totalTasks = $taskCountRow ? $taskCountRow['cnt'] : 0;
                                        }
                                    }
                                    // Get payment statistics with currency filter
                                    $statsCurrencyFilter = isset($_GET['stats_currency']) ? $_GET['stats_currency'] : 'all';
                                    
                                    
                                    // Get all currencies for this user
                                    $userCurrenciesQuery = "SELECT DISTINCT m.currency 
                                                           FROM milestones m 
                                                           INNER JOIN projects p ON m.p_id = p.p_id 
                                                           WHERE p.c_id = $user_id 
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
                                            
                                            $whereConditions = ["p.c_id = $user_id"];
                                            $whereConditions[] = "(m.currency LIKE '$currencySql,%' OR m.currency = '$currencySql' OR m.currency LIKE '%,$currencySql')";
                                            $whereClause = implode(' AND ', $whereConditions);
                                            
                                            $totalQuery = "SELECT SUM(m.budget) as total FROM milestones m 
                                                          INNER JOIN projects p ON m.p_id = p.p_id 
                                                          WHERE $whereClause";
                                            $totalResult = $database->query($totalQuery);
                                            $total = $database->fetchArray($totalResult)['total'] ?? 0;
                                            
                                            $paidQuery = "SELECT SUM(m.budget) as total FROM milestones m 
                                                         INNER JOIN projects p ON m.p_id = p.p_id 
                                                         WHERE $whereClause AND m.status = 1";
                                            $paidResult = $database->query($paidQuery);
                                            $paid = $database->fetchArray($paidResult)['total'] ?? 0;
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
                                        $statsWhereConditions = ["p.c_id = $user_id"];
                                        $statsWhereConditions[] = "(m.currency LIKE '$statsCurrencyFilterSql,%' OR m.currency = '$statsCurrencyFilterSql' OR m.currency LIKE '%,$statsCurrencyFilterSql')";
                                        $statsWhereClause = implode(' AND ', $statsWhereConditions);
                                        
                                        $totalAmountQuery = "SELECT SUM(m.budget) as total FROM milestones m 
                                                           INNER JOIN projects p ON m.p_id = p.p_id 
                                                           WHERE $statsWhereClause";
                                        $totalAmountResult = $database->query($totalAmountQuery);
                                        $totalAmount = $database->fetchArray($totalAmountResult)['total'] ?? 0;
                                        
                                        $paidAmountQuery = "SELECT SUM(m.budget) as total FROM milestones m 
                                                          INNER JOIN projects p ON m.p_id = p.p_id 
                                                          WHERE $statsWhereClause AND m.status = 1";
                                        $paidAmountResult = $database->query($paidAmountQuery);
                                        $paidAmount = $database->fetchArray($paidAmountResult)['total'] ?? 0;
                                        $unpaidAmount = $totalAmount - $paidAmount;
                                        
                                        $currencySymbol = getCurrencySymbol($statsCurrencyFilter);
                                    }
                                ?>
							<div class="center-col max-width-850 pd-30 admin-dashboard">
                       <div class="row counter-align">
                            <?php
                            $overviewSmallCardClass = $canUseInvoiceWidgets
                                ? 'col-lg-3 col-sm-6 col-6'
                                : 'col-xl-3 col-lg-6 col-md-6 col-sm-6 col-6';
                            $overviewInvoiceCardClass = 'col-lg-6 col-sm-12 col-12';
                            ?>
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
                                <a href="projects" class="widget-card__stretch-link" aria-label="<?php echo htmlspecialchars($lang['View all'], ENT_QUOTES, 'UTF-8'); ?>"></a>
                                        </div>
                                    </div>
								  <div class="<?php echo $overviewSmallCardClass; ?>"> 
								   <div class="widget-card dash-counter stat-spark-card stat-spark-card--staff widget-card--linked">    
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
									<a href="kanban" class="widget-card__stretch-link" aria-label="<?php echo htmlspecialchars($lang['View all'], ENT_QUOTES, 'UTF-8'); ?>"></a>
                                                </div>
                                                  </div>
								  <?php if ($canUseInvoiceWidgets): ?>
								  <div class="<?php echo $overviewInvoiceCardClass; ?>">
							<div class="widget-card stat-spark-card stat-spark-card--invoice-overview" id="invoice-overview-card">
								<div class="stat-invoice-head">
									<div class="grey"><span>Invoice Overview</span></div>
									<div class="stat-invoice-head-actions">
										<span class="stat-invoice-badge" id="invoice-overview-badge"><?php echo (int) $invoiceOverviewTotal; ?> total</span>
                                 </div>
                                 </div>
								<div class="stat-invoice-grid">
									<div class="stat-invoice-col">
										<a href="invoices?status=1" class="counts dash-rttb is-paid mt-0 stat-invoice-count-link" id="invoice-overview-paid-count" aria-label="<?php echo htmlspecialchars($lang['Paid Invoices'] ?? 'Paid Invoices', ENT_QUOTES, 'UTF-8'); ?>"><?php echo (int) $user_paid_milestones_count; ?></a>
										<div class="grey persent-count" id="invoice-overview-paid-meta"><?php echo $lang['Paid Invoices']; ?> &middot; <?php echo htmlspecialchars($fmtDashMoney($paidAmountAll, $dashCurrencySymbol), ENT_QUOTES, 'UTF-8'); ?></div>
                                 </div>
									<div class="stat-invoice-col">
										<a href="invoices?status=0" class="counts dash-rttb is-unpaid mt-0 stat-invoice-count-link" id="invoice-overview-unpaid-count" aria-label="<?php echo htmlspecialchars($lang['Unpaid Invoices'] ?? 'Unpaid Invoices', ENT_QUOTES, 'UTF-8'); ?>"><?php echo (int) $user_unpaid_milestones_count; ?></a>
										<div class="grey persent-count" id="invoice-overview-unpaid-meta"><?php echo $lang['Unpaid']; ?> &middot; <?php echo htmlspecialchars($fmtDashMoney($unpaidAmount, $dashCurrencySymbol), ENT_QUOTES, 'UTF-8'); ?></div>
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
							</div>
										</div>
								  <?php else: ?>
								  <div class="col-xl-6 col-lg-12 col-md-12 col-sm-12 col-12"> 
									<div class="widget-card stat-spark-card stat-spark-card--session widget-card--linked">
										<div class="grey"><span><?php echo $lang['Last Session']; ?></span></div>
										<div class="counts dash-rttb"><?php echo htmlspecialchars($lastSessionDateDisplay, ENT_QUOTES, 'UTF-8'); ?></div>
										<?php renderAdminStatSparkline('session', $sparkSession); ?>
										<a href="profile?user_id=<?php echo (int)$user_id; ?>&tab=activity" class="widget-card__stretch-link" aria-label="<?php echo htmlspecialchars($lang['Last Session'], ENT_QUOTES, 'UTF-8'); ?>"></a>
									</div>
								  </div>
								  <?php endif; ?>    
								</div> 
                                <?php } ?> 
                            <?php if ($tab == 'media' && !empty($profileMediaLocked) && function_exists('tasksession_render_pro_upgrade_embedded')): ?>
                                <?php tasksession_render_pro_upgrade_embedded('files_media'); ?>
                            <?php elseif ($tab == 'media' && $fileManagementModuleEnabled): ?>
                                <?php
                                $__mves = __DIR__ . '/../includes/media_vault_extended_shares.php';
if (is_file($__mves)) { require_once $__mves; }
                                $profileMediaOnlyExplicitShares = true;
                                $showShareWithProfileUserAction = false;
                                $__pmt = __DIR__ . '/../includes/profile_media_tab.php';
if (is_file($__pmt)) { require_once $__pmt; }
                                include __DIR__ . '/../templates/docs/profile-media-panel.php';
                                ?>
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
                                                        echo '<td><div class="tbl-ttl">' . datetime_to_text($activity['created_at']) . '</div></td>';
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
<?php if ($tab == 'activity'): ?>
<script src="../assets/js/task-date-range.js"></script>
<?php endif; ?>
<?php if ($tab == 'media' && $fileManagementModuleEnabled && empty($profileMediaLocked)): ?>
<?php include('../vendor/google/gdrive/templates/media-vault-move-modal.php'); ?>
<?php
$mvShareProfileScopeEnabled = false;
$mvShareFolderModalLinkOnly = false;
$mvShareFileModalLinkOnly = false;
?>
<?php include('../vendor/google/gdrive/templates/media-vault-share-folder-modal.php'); ?>
<?php include('../vendor/google/gdrive/templates/media-vault-share-file-modal.php'); ?>
<link rel="stylesheet" href="../vendor/google/gdrive/assets/css/file-sharing.css?v=<?php echo mv_vault_asset_ver('vendor/google/gdrive/assets/css/file-sharing.css'); ?>">
<script src="../vendor/google/gdrive/assets/js/media/video-modal.js"></script>
<script src="../vendor/google/gdrive/assets/js/image-modal.js"></script>
<script src="../vendor/google/gdrive/assets/js/pdf-lightbox.js"></script>
<?php $mvBulkJsV = @filemtime(__DIR__ . '/../vendor/google/gdrive/assets/js/bulk.js') ?: time(); ?>
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
window.currentFolderId = <?php echo isset($current_folder_id) && $current_folder_id ? (int) $current_folder_id : 'null'; ?>;
window.mediaVaultBulkZipPostUrl = <?php echo json_encode($mvMediaVaultAbs, JSON_UNESCAPED_SLASHES); ?>;
window.mediaVaultMarqueeEnabled = true;
window.mediaVaultBulkZipEnabled = true;
window.mediaVaultShowShareEmails = false;
window.mediaVaultViewMode = <?php echo json_encode((isset($mediaViewType) && $mediaViewType === 'table') ? 'table' : 'grid'); ?>;
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
    view: <?php echo json_encode(isset($mediaViewType) ? $mediaViewType : 'grid'); ?>
};
window.profileMediaBuildUrl = function (overrides) {
    const url = new URL(window.location.href);
    url.searchParams.set('user_id', <?php echo (int) $user_id; ?>);
    url.searchParams.set('tab', 'media');
    const baseView = <?php echo json_encode(isset($mediaViewType) ? $mediaViewType : 'grid'); ?>;
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
function syncProfileMediaSearchVisibilityForBulk() {
    var searchWrap = document.getElementById('profileMediaSearchWrap');
    if (!searchWrap) return;
    var inBulkMode = false;
    if (typeof window.bulkDeleteMode !== 'undefined') {
        inBulkMode = !!window.bulkDeleteMode;
    } else {
        var bulkBack = document.getElementById('bulkBackTab');
        inBulkMode = !!(bulkBack && bulkBack.style.display !== 'none' && bulkBack.style.display !== '');
    }
    searchWrap.style.display = inBulkMode ? 'none' : '';
}
document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.enterBulkMode === 'function') {
        var __originalEnterBulkMode = window.enterBulkMode;
        window.enterBulkMode = function () {
            var result = __originalEnterBulkMode.apply(this, arguments);
            syncProfileMediaSearchVisibilityForBulk();
            return result;
        };
    }
    if (typeof window.exitBulkMode === 'function') {
        var __originalExitBulkMode = window.exitBulkMode;
        window.exitBulkMode = function () {
            var result = __originalExitBulkMode.apply(this, arguments);
            syncProfileMediaSearchVisibilityForBulk();
            return result;
        };
    }
    initializeMediaCore(
        0,
        <?php echo isset($current_folder_id) && $current_folder_id ? (int) $current_folder_id : 'null'; ?>,
        <?php echo (int) (isset($currentPage) ? $currentPage : 1); ?>,
        <?php echo (int) (isset($totalPages) ? $totalPages : 1); ?>,
        <?php echo (int) $session->userId; ?>,
        <?php echo (int) ($_SESSION['accountStatus'] ?? 0); ?>
    );
    initializeFormHandlers();
    initializeImageLoading();
    setupFileThumbnailHandlers();
    setupFolderDoubleClick();
    initializeModalAutoOpen();
    hideDeleteOptionsForNonOwners();
    syncProfileMediaSearchVisibilityForBulk();
});
</script>
<?php endif; ?>
<?php $__aiCtx = __DIR__ . '/../includes/ai_contextual_snippet.php';
if (is_file($__aiCtx)) { require_once $__aiCtx; if (function_exists('ai_contextual_emit')) { ai_contextual_emit(); } } ?>
<?php  include("../templates/payment-footer.php"); 
if(isset($_POST["edit-mile"])){ ?>
<script type="text/javascript">
// Use Bootstrap 5 way to show modal
var myModal = document.getElementById('edit-milestone');
if (myModal) {
  var modal = new bootstrap.Modal(myModal);
  modal.show();
}
</script>
<?php } 
if(isset($_POST["edit-mile1"])){ ?>
<script type="text/javascript">
// Use Bootstrap 5 way to show modal
var myModal = document.getElementById('edit-milestone1');
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
					header('location:profile?user_id='.$user_id.'&tab=' . ($canUseInvoiceWidgets ? 'invoice' : 'overview') . '&message=updated'); 
				}
				else
				{
header('location:profile?user_id='.$user_id.'&tab=' . ($canUseInvoiceWidgets ? 'invoice' : 'overview') . '&message=notupdated'); 
				}
			}
		}

	// Handle milestone deletion (clients cannot permanently delete milestones from profile)
	if(isset($_POST['delete-mile']) && isset($_POST['delete_id']))
			{
				header('location:profile?user_id='.$user_id.'&tab=' . ($canUseInvoiceWidgets ? 'invoice' : 'overview') . '&message=delete_failed'); 
		exit;
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
    $chartCurrency = (($clientCurrencyRaw ?? '') !== '') ? $clientCurrencyRaw : ($defaultCurrency ?? 'all');
    if ($chartCurrency && $chartCurrency !== 'all') {
        $currency_parts = explode(',', $chartCurrency);
        $currency_code = trim($currency_parts[0]);
        $currency_condition = " AND (m.currency LIKE '{$currency_code},%' OR m.currency = '{$currency_code}' OR m.currency LIKE '%,{$currency_code}')";
    }
    
    if ($is_staff_profile) {
        // Staff: sales they created
        $user_paid_milestones = milestone::findBySql(
            "SELECT budget FROM milestones m 
             WHERE m.status = 1 
             AND YEAR(m.releaseDate) = " . (int)$present_year . " 
             AND MONTH(m.releaseDate) = " . (int)$m . "
             AND m.created_by = $user_id
             {$currency_condition}"
        );
        $user_unpaid_milestones = milestone::findBySql(
            "SELECT budget FROM milestones m 
             WHERE m.status = 0 
             AND YEAR(m.deadline) = " . (int)$present_year . " 
             AND MONTH(m.deadline) = " . (int)$m . "
             AND m.created_by = $user_id
             {$currency_condition}"
        );
    } else {
        // Client: milestones for their projects
        $user_paid_milestones = milestone::findBySql(
            "SELECT budget FROM milestones m 
             INNER JOIN projects p ON m.p_id = p.p_id 
             WHERE m.status = 1 
             AND YEAR(m.releaseDate) = " . (int)$present_year . " 
             AND MONTH(m.releaseDate) = " . (int)$m . "
             AND (p.c_id = $user_id OR p.main_client_id = $user_id OR FIND_IN_SET($user_id, p.c_ids) > 0)
             {$currency_condition}"
        );
        $user_unpaid_milestones = milestone::findBySql(
            "SELECT budget FROM milestones m 
             INNER JOIN projects p ON m.p_id = p.p_id 
             WHERE m.status = 0 
             AND YEAR(m.deadline) = " . (int)$present_year . " 
             AND MONTH(m.deadline) = " . (int)$m . "
             AND (p.c_id = $user_id OR p.main_client_id = $user_id OR FIND_IN_SET($user_id, p.c_ids) > 0)
             {$currency_condition}"
        );
    }
    
    $user_monthly_earnings[] = array_sum(array_column($user_paid_milestones, 'budget'));
    $user_monthly_unpaid[] = array_sum(array_column($user_unpaid_milestones, 'budget'));
}

// Calculate user totals
$user_total_paid = array_sum($user_monthly_earnings);
$user_total_unpaid = array_sum($user_monthly_unpaid);
?>

window.monthLabels = <?php echo json_encode($user_month_labels); ?>;
window.monthlyEarnings = <?php echo json_encode($user_monthly_earnings); ?>;
window.monthlyUnpaid = <?php echo json_encode($user_monthly_unpaid); ?>;
window.monthlyCancelled = <?php echo json_encode(array_fill(0, count($user_month_labels), 0)); ?>;
window.userTotalPaid = <?php echo $user_total_paid; ?>;
window.userTotalUnpaid = <?php echo $user_total_unpaid; ?>;
window.userTotalCancelled = 0;
window.userTotalTax = 0;
window.defaultCurrency = <?php echo json_encode(($clientCurrencyRaw ?? '') !== '' ? $clientCurrencyRaw : 'USD,$'); ?>;
window.userPreferredCurrency = <?php echo json_encode($userPreferredCurrency ?? ($clientCurrencyRaw ?? 'USD,$')); ?>;
window.selectedCurrency = window.defaultCurrency;
window.profileUserId = <?php echo (int) $user_id; ?>;
window.salesPaidLabel = <?php echo json_encode($lang['Paid'] ?? 'Paid'); ?>;
window.salesUnpaidLabel = <?php echo json_encode($lang['Unpaid'] ?? 'Unpaid'); ?>;
window.salesCancelledLabel = <?php echo json_encode($lang['Cancel'] ?? 'Cancel'); ?>;
window.salesTaxLabel = <?php echo json_encode($lang['Sales Tax'] ?? 'Sales Tax'); ?>;
window.invoicePaidLabel = <?php echo json_encode($lang['Paid Invoices'] ?? 'Paid Invoices'); ?>;
window.invoiceUnpaidLabel = <?php echo json_encode($lang['Unpaid'] ?? 'Unpaid'); ?>;
</script>
