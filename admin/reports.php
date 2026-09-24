<?php
ob_start();
require_once __DIR__ . '/../includes/lib-initialize.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/task_reports_helper.php';
require_once __DIR__ . '/../includes/reports_common_helper.php';

$title = ($lang['Reports'] ?? 'Reports') . ' | ' . $syatem_title;
include __DIR__ . '/../templates/header.php';

if (!$session->isLoggedIn()) {
    redirectTo($url . 'index');
}
if ((int)$_SESSION['accountStatus'] === 2) {
    redirectTo($url . 'client/index');
}
if ((int)$_SESSION['accountStatus'] === 3) {
    redirectTo($url . 'staff/index');
}
if ((int)$_SESSION['accountStatus'] !== 1) {
    redirectTo($url . 'admin/index');
}

ensure_user_permissions($connect);

if (!reports_module_enabled()) {
    redirectTo($url . 'admin/index');
}

// Ensure CSRF exists before releasing the lock (footer still reads it for JS).
if (function_exists('generate_csrf_token')) {
    generate_csrf_token();
}
// Release session lock so report AJAX endpoints can respond while the page renders.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    session_write_close();
}

$ecommerceHelpersLoaded = function_exists('reports_load_ecommerce_helpers') && reports_load_ecommerce_helpers();

$reportsAjaxTaskUrl = reports_ajax_endpoint_url((string)$url, 'task-reports.php');
$reportsAjaxProjectUrl = reports_ajax_endpoint_url((string)$url, 'project-reports.php');
$reportsAjaxInvoiceUrl = reports_ajax_endpoint_url((string)$url, 'invoice-reports.php');
$reportsAjaxEcommerceUrl = ($ecommerceHelpersLoaded && function_exists('tasksession_ecommerce_ajax_url'))
    ? tasksession_ecommerce_ajax_url('ecommerce-reports.php')
    : '';

// Get current user info
$id = $session->userId;
$user = User::findById((int)$id);
$username = $user->firstName;
$email = $user->email;

$reportType = 'task';
if (isset($_GET['projects'])) {
    $reportType = 'projects';
} elseif (isset($_GET['invoice'])) {
    $reportType = 'invoice';
} elseif (isset($_GET['ecommerce'])) {
    $reportType = 'ecommerce';
} elseif (isset($_GET['task'])) {
    $reportType = 'task';
}

$isFreeEditionReports = function_exists('tasksession_is_free_edition') && tasksession_is_free_edition();
$reportsProFeatureByType = array(
    'task' => 'task_reports',
    'projects' => 'project_reports',
    'invoice' => 'financial_reports',
);
$reportsProLocked = $isFreeEditionReports && isset($reportsProFeatureByType[$reportType]);

if (!class_exists('Settings')) {
    require_once __DIR__ . '/../includes/settings.php';
}
$settingsForModules = Settings::findById(1);
$isInvoicesEnabled = ($settingsForModules && !empty($settingsForModules->module_invoices));
$isEcommerceEnabled = reports_ecommerce_module_enabled();
if ($isFreeEditionReports) {
    // Show Financial Reports tab (notice) for Admin Free; Ecommerce stays live.
    $isInvoicesEnabled = true;
}

if ($reportType === 'invoice' && !$isInvoicesEnabled) {
    redirectTo($url . 'admin/reports?task');
}

if ($reportType === 'ecommerce' && !$isEcommerceEnabled) {
    redirectTo($url . 'admin/reports?task');
}

if ($reportType === 'ecommerce' && !has_permission('ecommerce_orders_view')) {
    redirectTo($url . 'admin/reports?task');
}

// Free: Pro report types keep Reports chrome + upgrade notice (no heavy data load).
if ($reportsProLocked) {
    $featureKey = $reportsProFeatureByType[$reportType];
    $reportIconTask = '' . ts_icon('tasks', 'h-6') . '';
    $reportIconProject = '' . ts_icon('folder', 'h-6') . '';
    $reportIconInvoice = '' . ts_icon('invoice', 'h-6') . '';
    $reportIconEcommerce = '' . ts_icon('cart', 'h-6') . '';
    $reportTabs = [
        [
            'key' => 'task',
            'label' => $lang['Task Reports'] ?? 'Task Reports',
            'href' => 'reports?task',
            'icon' => $reportIconTask,
        ],
        [
            'key' => 'projects',
            'label' => $lang['Project Reports'] ?? 'Project Reports',
            'href' => 'reports?projects',
            'icon' => $reportIconProject,
        ],
        [
            'key' => 'invoice',
            'label' => $lang['Financial Reports'] ?? 'Financial Reports',
            'href' => 'reports?invoice',
            'icon' => $reportIconInvoice,
        ],
    ];
    if ($isEcommerceEnabled && has_permission('ecommerce_orders_view')) {
        $reportTabs[] = [
            'key' => 'ecommerce',
            'label' => $lang['Ecommerce Reports'] ?? 'Ecommerce Reports',
            'href' => 'reports?ecommerce',
            'icon' => $reportIconEcommerce,
        ];
    }
    ?>
<link rel="stylesheet" href="<?php echo $url; ?>assets/css/attendance.css">
<link rel="stylesheet" href="<?php echo $url; ?>assets/css/reports.css">
<style>
.ts-pro-upgrade-wrap.ts-pro-upgrade-wrap--embedded {
    min-height: calc(100vh - 220px) !important;
    padding: 48px 20px !important;
}
</style>
<div class="page-container">
    <div class="container-fluid">
        <div class="row row-eq-height">
            <?php include __DIR__ . '/../templates/sidebar.php'; ?>
            <div class="page-content">
                <?php include __DIR__ . '/../templates/top-header.php'; ?>
                <div class="reports-page attendance-page">
                    <div class="row bg-grey">
                        <div class="col-md-12 project-tabs">
                            <div class="row">
                                <div class="project-tabs-header">
                                    <div class="scrollable-tabs-container d-flex col-gap-40 col-gap-40-sep">
                                        <div class="main-heading">
                                            <h1><?php echo $lang['Reports'] ?? 'Reports'; ?></h1>
                                        </div>
                                        <div class="icon-container sep">
                                            <?php foreach ($reportTabs as $tab) : ?>
                                            <a href="<?php echo htmlspecialchars($tab['href']); ?>" class="<?php echo $reportType === $tab['key'] ? 'active' : ''; ?>">
                                                <?php echo $tab['icon']; ?>
                                                <?php echo htmlspecialchars($tab['label']); ?>
                                            </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                    ob_start();
                    tasksession_render_pro_upgrade($featureKey);
                    $upgradeHtml = ob_get_clean();
                    echo str_replace(
                        'class="ts-pro-upgrade-wrap"',
                        'class="ts-pro-upgrade-wrap ts-pro-upgrade-wrap--embedded"',
                        $upgradeHtml
                    );
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
    <?php
    include __DIR__ . '/../templates/main-footer.php';
    exit;
}

// Financial Reports filter submit must include invoice; recover if only currency (etc.) was sent.
if ($reportType === 'task' && $isInvoicesEnabled && reports_module_enabled()) {
    $getInputEarly = isset($_GET) && is_array($_GET) ? $_GET : [];
    if (
        array_key_exists('currency', $getInputEarly)
        && trim((string)$getInputEarly['currency']) !== ''
        && !isset($getInputEarly['task'])
        && !isset($getInputEarly['user_id'])
    ) {
        $reportType = 'invoice';
    }
}

if ($reportType === 'projects') {
    require_once __DIR__ . '/../includes/project_reports_helper.php';
}
if ($reportType === 'invoice') {
    require_once __DIR__ . '/../includes/invoice_reports_helper.php';
}
if ($reportType === 'ecommerce' && $isEcommerceEnabled) {
    $ecomReportsHelper = __DIR__ . '/../includes/ecommerce_reports_helper.php';
    $ecomDashHelper = __DIR__ . '/../includes/ecommerce_dashboard_helper.php';
    if (is_file($ecomReportsHelper)) {
        require_once $ecomReportsHelper;
    }
    if (is_file($ecomDashHelper)) {
        require_once $ecomDashHelper;
    }
} elseif ($reportType === 'ecommerce' && !$isEcommerceEnabled) {
    $reportType = 'task';
}

$statusTypes = task_reports_status_types();
$statusLabels = [
    'todo' => $lang['To Do'] ?? 'To Do',
    'inprogress' => $lang['In Progress'] ?? 'In Progress',
    'review' => $lang['In Review'] ?? 'In Review',
    'done' => $lang['Completed'] ?? 'Completed',
];

$columnNamesResult = $database->query("SELECT column_key, custom_name FROM project_columns WHERE project_id = 0");
if ($columnNamesResult && $database->numRows($columnNamesResult) > 0) {
    while ($columnRow = $database->fetchArray($columnNamesResult)) {
        $key = $columnRow['column_key'];
        if (isset($statusLabels[$key])) {
            $statusLabels[$key] = $columnRow['custom_name'];
        }
    }
}

$getInput = isset($_GET) && is_array($_GET) ? $_GET : [];
if ($reportType === 'projects') {
    $filters = project_reports_parse_filters($getInput);
} elseif ($reportType === 'invoice') {
    $filters = invoice_reports_parse_filters($getInput);
} elseif ($reportType === 'ecommerce') {
    $filters = ecommerce_reports_parse_filters($getInput);
} else {
    $filters = task_reports_parse_filters($getInput);
}
$datePreset = $filters['date_preset'];
$selectedUserId = isset($filters['user_id']) ? (int)$filters['user_id'] : 0;
$selectedProjectId = isset($filters['project_id']) ? (int)$filters['project_id'] : 0;
$selectedClientId = isset($filters['client_id']) ? (int)$filters['client_id'] : 0;
$selectedStatus = isset($filters['status']) ? (string)$filters['status'] : '';
$selectedCurrency = $reportType === 'invoice' ? (string)($filters['currency'] ?? '') : '';
$selectedStoreId = $reportType === 'ecommerce' ? (int)($filters['store_id'] ?? 0) : 0;
if ($reportType === 'ecommerce' && !array_key_exists('store_id', $getInput)) {
    $activeEcomStoreId = tasksession_ecommerce_get_active_store_id();
    if ($activeEcomStoreId > 0) {
        $selectedStoreId = $activeEcomStoreId;
        $filters['store_id'] = $activeEcomStoreId;
    }
}
$fromDate = $filters['from_date'];
$toDate = $filters['to_date'];

$currencyOptions = [];
$invoiceDefaultCurrency = '';
$currencyInUrl = false;
if ($reportType === 'invoice') {
    $settings = $settingsForModules ?: Settings::findById(1);
    $currencyOptions = invoice_reports_currency_options($settings);
    $invoiceDefaultCurrency = invoice_reports_default_currency($settings);
    $currencyInUrl = array_key_exists('currency', $getInput) && trim((string)$getInput['currency']) !== '';
}

$staffList = User::findBySql(
    "SELECT id, firstName FROM users WHERE status = 0 AND accountStatus IN (1, 3) ORDER BY firstName ASC"
);
$projectList = projects::findBySql(
    "SELECT p_id, project_title FROM projects WHERE archive = 0 AND trash != 1 ORDER BY project_title ASC"
);
$clientList = User::findBySql(
    "SELECT id, firstName FROM users WHERE status = 0 AND accountStatus = 2 ORDER BY firstName ASC"
);

$reportIconTask = '' . ts_icon('tasks', 'h-6') . '';
$reportIconProject = '' . ts_icon('folder', 'h-6') . '';
$reportIconInvoice = '' . ts_icon('invoice', 'h-6') . '';
$reportIconEcommerce = '' . ts_icon('cart', 'h-6') . '';

$reportTabs = [
    [
        'key' => 'task',
        'label' => $lang['Task Reports'] ?? 'Task Reports',
        'href' => 'reports?task',
        'icon' => $reportIconTask,
    ],
    [
        'key' => 'projects',
        'label' => $lang['Project Reports'] ?? 'Project Reports',
        'href' => 'reports?projects',
        'icon' => $reportIconProject,
    ],
];
if ($isInvoicesEnabled) {
    $reportTabs[] = [
        'key' => 'invoice',
        'label' => $lang['Financial Reports'] ?? 'Financial Reports',
        'href' => 'reports?invoice',
        'icon' => $reportIconInvoice,
    ];
}
if ($isEcommerceEnabled && has_permission('ecommerce_orders_view')) {
    $reportTabs[] = [
        'key' => 'ecommerce',
        'label' => $lang['Ecommerce Reports'] ?? 'Ecommerce Reports',
        'href' => 'reports?ecommerce',
        'icon' => $reportIconEcommerce,
    ];
}

$taskReportsLang = [
    'search' => 'Search...',
    'noResults' => $lang['No results found'] ?? 'No results',
    'loading' => $lang['Loading...'] ?? 'Loading...',
    'noData' => $lang['No task report data found for selected filters.'] ?? 'No task report data found for selected filters.',
    'loadError' => $lang['task_reports_load_error'] ?? 'Failed to load report data.',
    'open' => $lang['Open'] ?? 'Open',
    'completed' => $lang['Completed'] ?? 'Completed',
    'incomplete' => $lang['Incomplete'] ?? 'Incomplete',
    'openTasks' => $lang['Open Tasks'] ?? 'Open tasks',
    'completedTasks' => $lang['Completed Tasks'] ?? 'Completed Tasks',
    'incompleteTasks' => $lang['Incomplete Tasks'] ?? 'Incomplete Tasks',
    'tasks' => $lang['Tasks'] ?? 'Tasks',
    'noUserData' => $lang['No user data'] ?? 'No user data',
    'by' => $lang['task_reports_by_user'] ?? 'by',
    'others' => $lang['Others'] ?? 'Others',
    'total' => $lang['Total'] ?? 'Total',
    'viewTasks' => $lang['View Tasks'] ?? 'View Tasks',
    'viewProfile' => $lang['View Profile'] ?? 'View profile',
    'action' => $lang['Action'] ?? 'Action',
    'no' => $lang['No'] ?? 'No',
    'allStaff' => $lang['All Staff'] ?? 'All Staff',
    'totalTrackedTime' => $lang['Total Time Tracked'] ?? 'Total Time Tracked',
    'daily' => $lang['Daily'] ?? 'Daily',
    'weekly' => $lang['Weekly'] ?? 'Weekly',
    'monthly' => $lang['Monthly'] ?? 'Monthly',
    'week' => $lang['Week'] ?? 'Week',
    'dateRangeLabel' => $lang['Date Range'] ?? 'Date Range',
    'selectedRange' => $lang['Selected Range'] ?? 'Selected Range',
    'noChange' => $lang['task_reports_no_change'] ?? 'No change',
    'vsYesterday' => $lang['task_reports_vs_yesterday'] ?? 'vs yesterday',
    'vsDayBefore' => $lang['task_reports_vs_day_before'] ?? 'vs day before',
    'vsLastWeek' => $lang['task_reports_vs_last_week'] ?? 'vs last week',
    'vsPrevWeek' => $lang['task_reports_vs_prev_week'] ?? 'vs prev week',
    'vsLastMonth' => $lang['task_reports_vs_last_month'] ?? 'vs last month',
    'vsPrevMonth' => $lang['task_reports_vs_prev_month'] ?? 'vs prev month',
    'vsLastPeriod' => $lang['task_reports_vs_last_period'] ?? 'vs last period',
    'completionRate' => $lang['Completion Rate'] ?? 'Completion Rate',
];

$projectReportsLang = [
    'search' => 'Search...',
    'noResults' => $lang['No results found'] ?? 'No results',
    'loading' => $lang['Loading...'] ?? 'Loading...',
    'noData' => $lang['project_reports_no_data'] ?? 'No project report data found for selected filters.',
    'loadError' => $lang['project_reports_load_error'] ?? 'Failed to load report data.',
    'open' => $lang['Active Projects'] ?? 'Active Projects',
    'completed' => $lang['Completed'] ?? 'Completed',
    'incomplete' => $lang['Overdue Projects'] ?? 'Overdue Projects',
    'openTasks' => $lang['Active Projects'] ?? 'Active Projects',
    'completedTasks' => $lang['Completed Projects'] ?? 'Completed projects',
    'incompleteTasks' => $lang['Overdue Projects'] ?? 'Overdue Projects',
    'tasks' => $lang['Projects'] ?? 'Projects',
    'noUserData' => $lang['No project data'] ?? 'No project data',
    'by' => $lang['task_reports_by_user'] ?? 'by',
    'others' => $lang['Others'] ?? 'Others',
    'total' => $lang['Total'] ?? 'Total',
    'viewTasks' => $lang['View Project'] ?? 'View project',
    'viewProfile' => $lang['View Client'] ?? 'View Client',
    'action' => $lang['Action'] ?? 'Action',
    'no' => $lang['No'] ?? 'No',
    'allStaff' => $lang['All Staff'] ?? 'All Staff',
    'totalTrackedTime' => $lang['Total Time Tracked'] ?? 'Total Time Tracked',
    'daily' => $lang['Daily'] ?? 'Daily',
    'weekly' => $lang['Weekly'] ?? 'Weekly',
    'monthly' => $lang['Monthly'] ?? 'Monthly',
    'week' => $lang['Week'] ?? 'Week',
    'dateRangeLabel' => $lang['Date Range'] ?? 'Date Range',
    'selectedRange' => $lang['Selected Range'] ?? 'Selected Range',
    'noChange' => $lang['task_reports_no_change'] ?? 'No change',
    'vsYesterday' => $lang['task_reports_vs_yesterday'] ?? 'vs yesterday',
    'vsDayBefore' => $lang['task_reports_vs_day_before'] ?? 'vs day before',
    'vsLastWeek' => $lang['task_reports_vs_last_week'] ?? 'vs last week',
    'vsPrevWeek' => $lang['task_reports_vs_prev_week'] ?? 'vs prev week',
    'vsLastMonth' => $lang['task_reports_vs_last_month'] ?? 'vs last month',
    'vsPrevMonth' => $lang['task_reports_vs_prev_month'] ?? 'vs prev month',
    'vsLastPeriod' => $lang['task_reports_vs_last_period'] ?? 'vs last period',
    'completionRate' => $lang['Completion Rate'] ?? 'Completion Rate',
];

$invoiceReportsLang = [
    'search' => 'Search...',
    'noResults' => $lang['No results found'] ?? 'No results',
    'loading' => $lang['Loading...'] ?? 'Loading...',
    'noData' => $lang['invoice_reports_no_data'] ?? 'No financial report data found for selected filters.',
    'barChart' => $lang['Bar Chart'] ?? 'Bar Chart',
    'lineChart' => $lang['Line Chart'] ?? 'Line Chart',
    'viewType' => $lang['View Type'] ?? 'View Type',
    'period' => $lang['Period'] ?? 'Period',
    'financialOverview' => $lang['Financial Overview'] ?? 'Financial overview',
    'financialPerformance' => $lang['Financial Performance'] ?? 'Financial Performance',
    'loadError' => $lang['invoice_reports_load_error'] ?? 'Failed to load report data.',
    'paid' => $lang['Paid'] ?? 'Paid',
    'unpaid' => $lang['Unpaid'] ?? 'Unpaid',
    'salesTax' => $lang['Sales Tax'] ?? 'Sales Tax',
    'overdue' => $lang['Overdue'] ?? 'Overdue',
    'cancelled' => $lang['Cancel'] ?? 'Cancel',
    'cancelInvoices' => $lang['Cancel Invoices'] ?? 'Cancel Invoices',
    'paidLabel' => $lang['Paid Invoices'] ?? 'Paid Invoices',
    'unpaidLabel' => $lang['Unpaid Invoices'] ?? 'Unpaid invoices',
    'overdueLabel' => $lang['Overdue'] ?? 'Overdue',
    'invoices' => $lang['Invoices'] ?? 'Invoices',
    'total' => $lang['Total'] ?? 'Total',
    'viewInvoice' => $lang['View Invoice'] ?? 'View Invoice',
    'editInvoice' => $lang['Edit Invoice'] ?? 'Edit Invoice',
    'action' => $lang['Action'] ?? 'Action',
    'no' => $lang['No'] ?? 'No',
    'earnings' => $lang['Earnings'] ?? 'Earnings',
    'dateRangeLabel' => $lang['Date Range'] ?? 'Date Range',
    'selectedRange' => $lang['Selected Range'] ?? 'Selected Range',
    'noChange' => $lang['task_reports_no_change'] ?? 'No change',
    'vsYesterday' => $lang['task_reports_vs_yesterday'] ?? 'vs yesterday',
    'vsDayBefore' => $lang['task_reports_vs_day_before'] ?? 'vs day before',
    'vsLastWeek' => $lang['task_reports_vs_last_week'] ?? 'vs last week',
    'vsPrevWeek' => $lang['task_reports_vs_prev_week'] ?? 'vs prev week',
    'vsLastMonth' => $lang['task_reports_vs_last_month'] ?? 'vs last month',
    'vsPrevMonth' => $lang['task_reports_vs_prev_month'] ?? 'vs prev month',
    'vsLastPeriod' => $lang['task_reports_vs_last_period'] ?? 'vs last period',
    'collectionRate' => $lang['Collection Rate'] ?? 'Collection Rate',
    'daily' => $lang['Daily'] ?? 'Daily',
    'weekly' => $lang['Weekly'] ?? 'Weekly',
    'monthly' => $lang['Monthly'] ?? 'Monthly',
];

$ecommerceReportsLang = [
    'search' => 'Search...',
    'noResults' => $lang['No results found'] ?? 'No results',
    'loading' => $lang['Loading...'] ?? 'Loading...',
    'noData' => $lang['ecommerce_reports_no_data'] ?? 'No ecommerce report data found for selected filters.',
    'barChart' => $lang['Bar Chart'] ?? 'Bar Chart',
    'lineChart' => $lang['Line Chart'] ?? 'Line Chart',
    'loadError' => $lang['ecommerce_reports_load_error'] ?? 'Failed to load report data.',
    'complete' => $lang['Complete'] ?? 'Complete',
    'processing' => $lang['Processing'] ?? 'Processing',
    'pending' => $lang['Pending'] ?? 'Pending',
    'cancel' => $lang['Cancel'] ?? 'Cancel',
    'orders' => $lang['Orders'] ?? 'Orders',
    'total' => $lang['Total'] ?? 'Total',
    'viewProduct' => $lang['View Product'] ?? 'View Product',
    'action' => $lang['Action'] ?? 'Action',
    'no' => $lang['No'] ?? 'No',
    'dateRangeLabel' => $lang['Date Range'] ?? 'Date Range',
    'selectedRange' => $lang['Selected Range'] ?? 'Selected Range',
    'noChange' => $lang['task_reports_no_change'] ?? 'No change',
    'vsYesterday' => $lang['task_reports_vs_yesterday'] ?? 'vs yesterday',
    'vsDayBefore' => $lang['task_reports_vs_day_before'] ?? 'vs day before',
    'vsLastWeek' => $lang['task_reports_vs_last_week'] ?? 'vs last week',
    'vsPrevWeek' => $lang['task_reports_vs_prev_week'] ?? 'vs prev week',
    'vsLastMonth' => $lang['task_reports_vs_last_month'] ?? 'vs last month',
    'vsPrevMonth' => $lang['task_reports_vs_prev_month'] ?? 'vs prev month',
    'vsLastPeriod' => $lang['task_reports_vs_last_period'] ?? 'vs last period',
    'completionRate' => $lang['Completion Rate'] ?? 'Completion Rate',
    'daily' => $lang['Daily'] ?? 'Daily',
    'weekly' => $lang['Weekly'] ?? 'Weekly',
    'monthly' => $lang['Monthly'] ?? 'Monthly',
    'orderRevenueOverview' => $lang['Order Revenue Overview'] ?? 'Order Revenue Overview',
];

$invoiceStatusOptions = [
    ['value' => '', 'label' => $lang['All Status'] ?? 'All Status'],
    ['value' => '1', 'label' => $lang['Paid'] ?? 'Paid'],
    ['value' => '0', 'label' => $lang['Unpaid'] ?? 'Unpaid'],
    ['value' => '2', 'label' => $lang['Cancel'] ?? 'Cancel'],
];

$datePresets = [
    'today' => $lang['Today'] ?? 'Today',
    'yesterday' => $lang['Yesterday'] ?? 'Yesterday',
    'this_week' => $lang['This Week'] ?? 'This Week',
    'last_week' => $lang['Last Week'] ?? 'Last Week',
    'this_month' => $lang['This Month'] ?? 'This Month',
    'last_month' => $lang['Last Month'] ?? 'Last Month',
    'custom' => $lang['Custom Range'] ?? 'Custom Range',
];

if (!function_exists('reports_page_render_toolbar_dropdown')) {
    function reports_page_render_toolbar_dropdown(array $args): void
    {
        reports_render_toolbar_dropdown($args);
    }
}


$datePresetOptions = [];
foreach ($datePresets as $presetKey => $presetLabel) {
    $datePresetOptions[] = ['value' => $presetKey, 'label' => $presetLabel];
}

$userOptions = [['value' => '', 'label' => $lang['All Users'] ?? 'All Users']];
foreach ($staffList as $staff) {
    $userOptions[] = ['value' => (string)(int)$staff->id, 'label' => (string)$staff->firstName];
}

$clientOptions = [['value' => '', 'label' => $lang['All Clients'] ?? 'All Clients']];
foreach ($clientList as $client) {
    $clientOptions[] = ['value' => (string)(int)$client->id, 'label' => (string)$client->firstName];
}

$projectOptions = [['value' => '', 'label' => $lang['All Projects'] ?? 'All Projects']];
foreach ($projectList as $project) {
    $projectOptions[] = ['value' => (string)(int)$project->p_id, 'label' => (string)$project->project_title];
}

$ecommerceStoreOptions = [['value' => '', 'label' => $lang['All Stores'] ?? 'All Stores']];
if ($reportType === 'ecommerce' && $isEcommerceEnabled) {
    if (!function_exists('tasksession_ecommerce_list_stores')) {
        reports_load_ecommerce_helpers();
    }
    if (function_exists('tasksession_ecommerce_list_stores')) {
        foreach (tasksession_ecommerce_list_stores() as $ecomStore) {
            $ecommerceStoreOptions[] = [
                'value' => (string)(int)($ecomStore['id'] ?? 0),
                'label' => (string)($ecomStore['name'] ?? ('Store #' . (int)($ecomStore['id'] ?? 0))),
            ];
        }
    }
}

$statusOptions = [['value' => '', 'label' => $lang['All Status'] ?? 'All Status']];
foreach ($statusTypes as $statusKey) {
    $statusOptions[] = ['value' => $statusKey, 'label' => $statusLabels[$statusKey] ?? $statusKey];
}

$projectStatusOptions = [
    ['value' => '', 'label' => $lang['All Status'] ?? 'All Status'],
    ['value' => '0', 'label' => $lang['Active Projects'] ?? 'Active Projects'],
    ['value' => '1', 'label' => $lang['Completed'] ?? 'Completed'],
];
if ($reportType === 'projects') {
    $statusOptions = $projectStatusOptions;
}

$datePresetLabel = $datePresets[$datePreset] ?? ($lang['This Month'] ?? 'This Month');
$userLabel = $lang['All Users'] ?? 'All Users';
$clientLabel = $lang['All Clients'] ?? 'All Clients';
$projectLabel = $lang['All Projects'] ?? 'All Projects';
$statusLabel = $lang['All Status'] ?? 'All Status';
$currencyLabel = '';
$ecommerceStoreLabel = $lang['All Stores'] ?? 'All Stores';

foreach ($staffList as $staff) {
    if ((int)$staff->id === $selectedUserId) {
        $userLabel = (string)$staff->firstName;
        break;
    }
}
foreach ($clientList as $client) {
    if ((int)$client->id === $selectedClientId) {
        $clientLabel = (string)$client->firstName;
        break;
    }
}
foreach ($projectList as $project) {
    if ((int)$project->p_id === $selectedProjectId) {
        $projectLabel = (string)$project->project_title;
        break;
    }
}
if ($reportType === 'projects') {
    if ($selectedStatus === '0') {
        $statusLabel = $lang['Active Projects'] ?? 'Active Projects';
    } elseif ($selectedStatus === '1') {
        $statusLabel = $lang['Completed'] ?? 'Completed';
    }
} elseif ($reportType === 'invoice') {
    if ($selectedStatus === '1') {
        $statusLabel = $lang['Paid'] ?? 'Paid';
    } elseif ($selectedStatus === '0') {
        $statusLabel = $lang['Unpaid'] ?? 'Unpaid';
    }
} elseif ($selectedStatus !== '' && isset($statusLabels[$selectedStatus])) {
    $statusLabel = $statusLabels[$selectedStatus];
}

if ($reportType === 'invoice') {
    foreach ($currencyOptions as $opt) {
        if ((string)$opt['value'] === $selectedCurrency) {
            $currencyLabel = (string)$opt['label'];
            break;
        }
    }
    if ($currencyLabel === '' && !empty($currencyOptions)) {
        $currencyLabel = (string)$currencyOptions[0]['label'];
    }
}

if ($reportType === 'ecommerce') {
    foreach ($ecommerceStoreOptions as $opt) {
        if ((string)$opt['value'] === (string)$selectedStoreId && (string)$opt['value'] !== '') {
            $ecommerceStoreLabel = (string)$opt['label'];
            break;
        }
    }
}

$reportsAlertId = 'taskReportsAlert';
$reportsExportBtnId = 'taskReportsExportBtn';
$reportsRefreshBtnId = 'taskReportsRefreshBtn';
if ($reportType === 'projects') {
    $reportsAlertId = 'projectReportsAlert';
    $reportsExportBtnId = 'projectReportsExportBtn';
    $reportsRefreshBtnId = 'projectReportsRefreshBtn';
} elseif ($reportType === 'invoice') {
    $reportsAlertId = 'invoiceReportsAlert';
    $reportsExportBtnId = 'invoiceReportsExportBtn';
    $reportsRefreshBtnId = 'invoiceReportsRefreshBtn';
} elseif ($reportType === 'ecommerce') {
    $reportsAlertId = 'ecommerceReportsAlert';
    $reportsExportBtnId = 'ecommerceReportsExportBtn';
    $reportsRefreshBtnId = 'ecommerceReportsRefreshBtn';
}

$reportsLoadingTypes = ['task', 'projects', 'invoice', 'ecommerce'];
$reportsSkeletonPath = __DIR__ . '/../partials/reports_dashboard_skeleton.inc.php';
$reportsShowSkeletonLoading = is_file($reportsSkeletonPath) && in_array($reportType, $reportsLoadingTypes, true);

$aiReportExplainUrl = '';
$aiTimeWeekUrl = '';
$aiHelpersPath = __DIR__ . '/../ai/helpers.php';
if (is_file($aiHelpersPath)) {
    require_once $aiHelpersPath;
}
if (function_exists('ai_contextual_button_allowed') && ai_contextual_button_allowed()) {
    if ($reportType === 'invoice') {
        $aiExplainPayload = [
            'filters' => [
                'date_preset' => $datePreset,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'client_id' => $selectedClientId,
                'project_id' => $selectedProjectId,
                'currency' => $selectedCurrency,
                'status' => $selectedStatus,
            ],
        ];
        $aiExplainPrefill = '/tool reports.explain ' . json_encode($aiExplainPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $aiReportExplainUrl = function_exists('ai_assistant_url')
            ? ai_assistant_url('', ['prefill' => $aiExplainPrefill])
            : (rtrim((string) $url, '/') . '/ai/assistant?prefill=' . rawurlencode($aiExplainPrefill));
    }
    if (($reportType === 'task' || $reportType === 'projects')
        && function_exists('tasksession_time_tracking_enabled')
        && tasksession_time_tracking_enabled()
        && function_exists('ai_assistant_tool_prefill_url')) {
        $aiTimeWeekUrl = ai_assistant_tool_prefill_url('time_tracking.week_summary', []);
    }
}
?>
<link rel="stylesheet" href="<?php echo $url; ?>assets/css/attendance.css">
<link rel="stylesheet" href="<?php echo $url; ?>assets/css/reports.css">
<?php if ($reportsShowSkeletonLoading) : ?>
<style id="reports-critical-loading-css">
.reports-page.reports-loading .reports-dashboard-live{display:none!important}
.reports-page:not(.reports-loading) .reports-dashboard-skeleton{display:none!important}
</style>
<?php endif; ?>
<?php if ($reportType === 'ecommerce' && is_file(__DIR__ . '/../vendor/woocommerce/css/woo-orders.css')) : ?>
<link rel="stylesheet" href="<?php echo $url; ?>vendor/woocommerce/css/woo-orders.css">
<link rel="stylesheet" href="<?php echo $url; ?>vendor/woocommerce/css/woo-dashboard.css">
<?php endif; ?>
<div class="page-container">
    <div class="container-fluid">
        <div class="row row-eq-height">
            <?php include __DIR__ . '/../templates/sidebar.php'; ?>
            <div class="page-content">
                <?php include __DIR__ . '/../templates/top-header.php'; ?>
                <div class="reports-page attendance-page<?php echo $reportsShowSkeletonLoading ? ' reports-loading' : ''; ?>">
                    <div id="<?php echo htmlspecialchars($reportsAlertId); ?>" class="d-none"></div>
                    <div class="row bg-grey">
                        <div class="col-md-12 project-tabs">
                            <div class="row">
                                <div class="project-tabs-header">
                                    <div class="scrollable-tabs-container d-flex col-gap-40 col-gap-40-sep">
                                        <div class="main-heading">
                                            <h1><?php echo $lang['Reports'] ?? 'Reports'; ?></h1>
                                        </div>
                                        <div class="icon-container sep">
                                            <?php foreach ($reportTabs as $tab) : ?>
                                            <a href="<?php echo htmlspecialchars($tab['href']); ?>" class="<?php echo $reportType === $tab['key'] ? 'active' : ''; ?>">
                                                <?php echo $tab['icon']; ?>
                                                <?php echo htmlspecialchars($tab['label']); ?>
                                            </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php if (in_array($reportType, $reportsLoadingTypes, true)) : ?>
                                <div class="project-tabs-toolbar-end reports-page-toolbar">
                                    <div class="d-none d-md-flex col-gap-10 flex-wrap">
                                    <button type="button" class="btn outline-btn d-inline-flex align-items-center col-gap-5 task-reports-filter-btn" id="<?php echo htmlspecialchars($reportsExportBtnId); ?>" data-reports-action="export">
                                        <?php echo ts_icon('download', 'dropdown-toggle-icon h-6'); ?>
                                        <span><?php echo $lang['Export CSV'] ?? 'Export CSV'; ?></span>
                                    </button>
                                    <button type="button" class="btn outline-btn d-inline-flex align-items-center col-gap-5 task-reports-filter-btn" id="<?php echo htmlspecialchars($reportsRefreshBtnId); ?>" data-reports-action="refresh">
                                        <?php echo ts_icon('refresh', 'dropdown-toggle-icon h-6'); ?>
                                        <span><?php echo $lang['Refresh'] ?? 'Refresh'; ?></span>
                                    </button>
                                    <?php if ($aiReportExplainUrl !== '') : ?>
                                    <a class="btn outline-btn d-inline-flex align-items-center col-gap-5 task-reports-filter-btn" href="<?php echo htmlspecialchars($aiReportExplainUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                        <span><?php echo htmlspecialchars($lang['Explain with AI'] ?? 'Explain with AI', ENT_QUOTES, 'UTF-8'); ?></span>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (!empty($aiTimeWeekUrl)) : ?>
                                    <a class="btn outline-btn d-inline-flex align-items-center col-gap-5 task-reports-filter-btn" href="<?php echo htmlspecialchars($aiTimeWeekUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                        <span><?php echo htmlspecialchars($lang['My week (AI)'] ?? 'My week (AI)', ENT_QUOTES, 'UTF-8'); ?></span>
                                    </a>
                                    <?php endif; ?>
                                    </div>
                                    <div class="edit-overview-btn d-md-none">
                                        <div class="action-toggle" data-bs-toggle="collapse" data-bs-target="#reportsToolbarMenu" aria-expanded="false" aria-controls="reportsToolbarMenu">
                                            <span class="action-text"><?php echo $lang['Options'] ?? 'Options'; ?></span>
                                            <span class="mobile-ellipsis">
                                                <?php echo ts_icon('ellipsis'); ?>
                                            </span>
                                        </div>
                                        <div id="reportsToolbarMenu" class="toggle-action collapse shadow-dept">
                                            <ul>
                                                <li>
                                                    <button type="button" data-reports-action="export">
                                                        <?php echo ts_icon('download', 'tasksession-timer-log-menu-ico me-2'); ?>
                                                        <span><?php echo $lang['Export CSV'] ?? 'Export CSV'; ?></span>
                                                    </button>
                                                </li>
                                                <li>
                                                    <button type="button" data-reports-action="refresh">
                                                        <?php echo ts_icon('refresh', 'tasksession-timer-log-menu-ico me-2'); ?>
                                                        <span><?php echo $lang['Refresh'] ?? 'Refresh'; ?></span>
                                                    </button>
                                                </li>
                                                <?php if ($aiReportExplainUrl !== '') : ?>
                                                <li>
                                                    <a href="<?php echo htmlspecialchars($aiReportExplainUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                                        <span><?php echo htmlspecialchars($lang['Explain with AI'] ?? 'Explain with AI', ENT_QUOTES, 'UTF-8'); ?></span>
                                                    </a>
                                                </li>
                                                <?php endif; ?>
                                                <?php if (!empty($aiTimeWeekUrl)) : ?>
                                                <li>
                                                    <a href="<?php echo htmlspecialchars($aiTimeWeekUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                                        <span><?php echo htmlspecialchars($lang['My week (AI)'] ?? 'My week (AI)', ENT_QUOTES, 'UTF-8'); ?></span>
                                                    </a>
                                                </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($reportType === 'invoice') : ?>
                    <?php include __DIR__ . '/../partials/reports_invoice_dashboard.inc.php'; ?>
                    <?php elseif ($reportType === 'projects') : ?>
                    <?php include __DIR__ . '/../partials/reports_projects_dashboard.inc.php'; ?>
                    <?php elseif ($reportType === 'ecommerce' && is_file(__DIR__ . '/../partials/reports_ecommerce_dashboard.inc.php')) : ?>
                    <?php include __DIR__ . '/../partials/reports_ecommerce_dashboard.inc.php'; ?>
                    <?php else : ?>
                    <div class="template-adjust">
                        <?php reports_render_dashboard_skeleton('task'); ?>
                        <div class="reports-dashboard-live">
                        <div class="reports-filters-panel pd-bt-0">
                            <div class="filters-row d-flex justify-content-between align-items-start flex-wrap row-gap-10 pd-bt-0">
                                <form id="taskReportsFilterForm" method="get" action="reports" class="d-flex align-items-end col-gap-10 flex-wrap task-reports-filters-form">
                                    <input type="hidden" name="task" value="1">
                                    <?php
                                    reports_page_render_toolbar_dropdown([
                                        'inputId' => 'taskReportsDatePreset',
                                        'menuId' => 'taskReportsDatePresetDropdown',
                                        'btnTextId' => 'taskReportsDatePresetBtnText',
                                        'fieldName' => 'date_preset',
                                        'fieldLabel' => $lang['Date Range'] ?? 'Date Range',
                                        'options' => $datePresetOptions,
                                        'selectedValue' => $datePreset,
                                        'selectedLabel' => $datePresetLabel,
                                    ]);
                                    ?>
                                    <div id="taskReportsCustomDates" class="align-items-end col-gap-10 flex-wrap<?php echo $datePreset === 'custom' ? ' d-flex' : ' d-none'; ?>">
                                        <div class="floating-filter-field">
                                            <label class="floating-label" for="taskReportsFromDate"><?php echo $lang['Start Date'] ?? 'Start date'; ?></label>
                                            <input type="date" name="from_date" id="taskReportsFromDate" class="form-control" value="<?php echo htmlspecialchars($fromDate); ?>">
                                        </div>
                                        <div class="floating-filter-field">
                                            <label class="floating-label" for="taskReportsToDate"><?php echo $lang['End Date'] ?? 'End Date'; ?></label>
                                            <input type="date" name="to_date" id="taskReportsToDate" class="form-control" value="<?php echo htmlspecialchars($toDate); ?>">
                                        </div>
                                    </div>
                                    <?php
                                    reports_page_render_toolbar_dropdown([
                                        'inputId' => 'taskReportsUserId',
                                        'menuId' => 'taskReportsUserIdDropdown',
                                        'btnTextId' => 'taskReportsUserIdBtnText',
                                        'fieldName' => 'user_id',
                                        'fieldLabel' => $lang['User / Staff'] ?? 'User / Staff',
                                        'options' => $userOptions,
                                        'selectedValue' => $selectedUserId ? (string)$selectedUserId : '',
                                        'selectedLabel' => $userLabel,
                                    ]);
                                    ?>
                                    <?php
                                    reports_page_render_toolbar_dropdown([
                                        'inputId' => 'taskReportsClientId',
                                        'menuId' => 'taskReportsClientIdDropdown',
                                        'btnTextId' => 'taskReportsClientIdBtnText',
                                        'fieldName' => 'client_id',
                                        'fieldLabel' => $lang['Client'] ?? 'Client',
                                        'options' => $clientOptions,
                                        'selectedValue' => $selectedClientId ? (string)$selectedClientId : '',
                                        'selectedLabel' => $clientLabel,
                                    ]);
                                    ?>
                                    <?php
                                    reports_page_render_toolbar_dropdown([
                                        'inputId' => 'taskReportsProjectId',
                                        'menuId' => 'taskReportsProjectIdDropdown',
                                        'btnTextId' => 'taskReportsProjectIdBtnText',
                                        'fieldName' => 'project_id',
                                        'fieldLabel' => $lang['Project'] ?? 'Project',
                                        'options' => $projectOptions,
                                        'selectedValue' => $selectedProjectId ? (string)$selectedProjectId : '',
                                        'selectedLabel' => $projectLabel,
                                    ]);
                                    ?>
                                    <?php
                                    reports_page_render_toolbar_dropdown([
                                        'inputId' => 'taskReportsStatus',
                                        'menuId' => 'taskReportsStatusDropdown',
                                        'btnTextId' => 'taskReportsStatusBtnText',
                                        'fieldName' => 'status',
                                        'fieldLabel' => $lang['Task Status'] ?? 'Task Status',
                                        'options' => $statusOptions,
                                        'selectedValue' => $selectedStatus,
                                        'selectedLabel' => $statusLabel,
                                    ]);
                                    ?>
                                    <div class="reports-filter-actions">
                                        <button type="submit" class="btn primary-btn d-inline-flex align-items-center col-gap-5 task-reports-filter-btn">
                                            <?php echo ts_icon('filter', 'dropdown-toggle-icon h-6'); ?>
                                            <span><?php echo $lang['Apply Filter'] ?? 'Apply Filter'; ?></span>
                                        </button>
                                        <button type="button" class="btn outline-btn d-inline-flex align-items-center col-gap-5 task-reports-filter-btn" id="taskReportsResetBtn">
                                            <?php echo ts_icon('close', 'dropdown-toggle-icon h-6'); ?>
                                            <span><?php echo $lang['Reset'] ?? 'Reset'; ?></span>
                                        </button>
                                    </div>
                                </form>
                                <div class="task-reports-active-range-wrap" id="taskReportsActiveRangeWrap">
                                    <div class="task-reports-active-range" id="taskReportsActiveRange" role="status" aria-live="polite"></div>
                                </div>
                            </div>
                        </div>

                        <div id="taskReportsCards" class="pd-bt-0 row counter-align adjust-1 pd-2 task-reports-cards" style="padding-bottom: 0 !important;">
                            <div class="col-lg col-md-4 col-6">
                                <div class="widget-card">
                                    <div class="grey"><?php echo $lang['Total Tasks'] ?? 'Total tasks'; ?></div>
                                    <div class="counts dash-rttb" id="cardTotalTasks">—</div>
                                    <div class="up-down" id="cardTotalTasksArrow"></div>
                                    <div class="grey persent-count" id="cardTotalTasksCompare"></div>
                                </div>
                            </div>
                            <div class="col-lg col-md-4 col-6">
                                <div class="widget-card">
                                    <div class="grey"><?php echo $lang['Completed Tasks'] ?? 'Completed Tasks'; ?></div>
                                    <div class="counts dash-rttb" id="cardCompletedTasks">—</div>
                                    <div class="up-down" id="cardCompletedTasksArrow"></div>
                                    <div class="grey persent-count" id="cardCompletedTasksCompare"></div>
                                </div>
                            </div>
                            <div class="col-lg col-md-4 col-6">
                                <div class="widget-card">
                                    <div class="grey"><?php echo $lang['Pending Tasks'] ?? 'Pending Tasks'; ?></div>
                                    <div class="counts dash-rttb" id="cardPendingTasks">—</div>
                                    <div class="up-down" id="cardPendingTasksArrow"></div>
                                    <div class="grey persent-count" id="cardPendingTasksCompare"></div>
                                </div>
                            </div>
                            <div class="col-lg col-md-4 col-6">
                                <div class="widget-card">
                                    <div class="grey"><?php echo $lang['Overdue'] ?? 'Overdue'; ?></div>
                                    <div class="counts dash-rttb" id="cardOverdueTasks">—</div>
                                    <div class="up-down" id="cardOverdueTasksArrow"></div>
                                    <div class="grey persent-count" id="cardOverdueTasksCompare"></div>
                                </div>
                            </div>
                            <div class="col-lg col-md-4 col-6">
                                <div class="widget-card">
                                    <div class="grey"><?php echo $lang['Total Time Tracked'] ?? 'Total Time Tracked'; ?></div>
                                    <div class="counts dash-rttb" id="cardTotalTime">—</div>
                                    <div class="up-down" id="cardTotalTimeArrow"></div>
                                    <div class="grey persent-count" id="cardTotalTimeCompare"></div>
                                </div>
                            </div>
                            <div class="col-lg col-md-4 col-6">
                                <div class="widget-card">
                                    <div class="grey"><?php echo $lang['Avg Time / Task'] ?? 'Avg Time / Task'; ?></div>
                                    <div class="counts dash-rttb" id="cardAvgTimePerTask">—</div>
                                    <div class="up-down" id="cardAvgTimePerTaskArrow"></div>
                                    <div class="grey persent-count" id="cardAvgTimePerTaskCompare"></div>
                                </div>
                            </div>
                        </div>

                        <div class="row pd-2 task-reports-charts" style="padding-top: 0 !important;">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <div class="widget-card shadow center-align pie-chart h-100 reports-status-pie-card">
                                    <div class="card-title text-start w-100 mb-2">
                                        <h3 class="mb-0"><?php echo $lang['Task Status Overview'] ?? 'Task status overview'; ?></h3>
                                    </div>
                                    <div class="task-reports-bar-summary task-reports-donut-summary" id="taskReportsDonutSummary">
                                        <div class="task-reports-bar-summary-main" id="taskReportsDonutSummaryMain">—</div>
                                        <div class="task-reports-bar-summary-meta">
                                            <span class="task-reports-bar-summary-arrow" id="taskReportsDonutSummaryArrow"></span>
                                            <span class="task-reports-bar-summary-pct" id="taskReportsDonutSummaryPct"></span>
                                            <span class="task-reports-bar-summary-sep" id="taskReportsDonutSummarySep" aria-hidden="true">•</span>
                                            <span class="task-reports-bar-summary-vs" id="taskReportsDonutSummaryVs"></span>
                                        </div>
                                    </div>
                                    <div class="task-reports-kanban-color-refs" aria-hidden="true">
                                        <i class="dots color-todo-bg" id="taskReportsKanbanColor-todo"></i>
                                        <span class="color-todo" id="taskReportsKanbanText-todo"></span>
                                    </div>
                                    <div class="chart-pie pt-4 pb-2 task-reports-pie-wrap">
                                        <canvas id="taskStatusPieChart" width="350" height="350"></canvas>
                                        <div class="total-pro">
                                            <h2 id="taskDonutTotal">0</h2>
                                            <span><?php echo $lang['Tasks'] ?? 'Tasks'; ?></span>
                                        </div>
                                    </div>
                                    <div class="container counters-bottom reports-status-counters">
                                        <div class="row chart-footer justify-content-center col-gap-35 reports-status-footer">
                                            <div class="foot-c-box">
                                                <span class="task-reports-legend-num primary" id="legendOpen">0</span>
                                                <span class="task-reports-legend-label fctxt"><?php echo $lang['Open Tasks'] ?? 'Open tasks'; ?></span>
                                            </div>
                                            <div class="foot-c-box">
                                                <span class="task-reports-legend-num task-reports-legend-num--done" id="legendCompleted">0</span>
                                                <span class="task-reports-legend-label fctxt"><?php echo $lang['Completed Tasks'] ?? 'Completed Tasks'; ?></span>
                                            </div>
                                            <div class="foot-c-box">
                                                <span class="task-reports-legend-num color-todo" id="legendIncomplete">0</span>
                                                <span class="task-reports-legend-label fctxt"><?php echo $lang['Incomplete Tasks'] ?? 'Incomplete Tasks'; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="widget-card shadow h-100">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap row-gap-10">
                                        <div class="card-title mb-0">
                                            <h3 class="mb-0"><?php echo $lang['Time Tracking Analytics'] ?? 'Time tracking analytics'; ?></h3>
                                        </div>
                                        <div class="d-flex align-items-center col-gap-10 task-reports-chart-controls">
                                            <div class="toolbar-dropdown-wrapper task-reports-chart-dropdown">
                                                <button type="button" class="border-btn-a task-reports-toolbar-toggle" id="taskBarChartUserDropdown" data-reports-dropdown="taskBarChartUserMenu" aria-expanded="false">
                                                    <span class="task-reports-filter-selected" id="taskBarChartUserBtnText"><?php echo $lang['All Staff'] ?? 'All Staff'; ?></span>
                                                    <?php echo ts_icon('chevron-down', 'icon-caret-down'); ?>
                                                </button>
                                                <div id="taskBarChartUserMenu" class="task-reports-toolbar-menu" style="min-width: 200px;">
                                                    <button type="button" class="first active" data-chart-user=""><span><?php echo $lang['All Staff'] ?? 'All Staff'; ?></span></button>
                                                    <?php
                                                    $staffCount = count($staffList);
                                                    foreach ($staffList as $i => $staff) :
                                                        $isLast = ($i === $staffCount - 1);
                                                    ?>
                                                    <button type="button" class="<?php echo $isLast ? 'last' : ''; ?>" data-chart-user="<?php echo (int)$staff->id; ?>"><span><?php echo htmlspecialchars((string)$staff->firstName); ?></span></button>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <div class="toolbar-dropdown-wrapper task-reports-chart-dropdown">
                                                <button type="button" class="border-btn-a task-reports-toolbar-toggle" id="taskBarChartGroupDropdown" data-reports-dropdown="taskBarChartGroupMenu" aria-expanded="false">
                                                    <span class="task-reports-filter-selected" id="taskBarChartGroupBtnText"><?php echo $lang['Weekly'] ?? 'Weekly'; ?></span>
                                                    <?php echo ts_icon('chevron-down', 'icon-caret-down'); ?>
                                                </button>
                                                <div id="taskBarChartGroupMenu" class="task-reports-toolbar-menu" style="min-width: 160px;">
                                                    <button type="button" class="first" data-chart-group="daily"><span><?php echo $lang['Daily'] ?? 'Daily'; ?></span></button>
                                                    <button type="button" class="active" data-chart-group="weekly"><span><?php echo $lang['Weekly'] ?? 'Weekly'; ?></span></button>
                                                    <button type="button" class="last" data-chart-group="monthly"><span><?php echo $lang['Monthly'] ?? 'Monthly'; ?></span></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="task-reports-bar-summary" id="taskReportsBarSummary">
                                        <div class="task-reports-bar-summary-main" id="taskReportsBarSummaryMain">—</div>
                                        <div class="task-reports-bar-summary-meta">
                                            <span class="task-reports-bar-summary-arrow" id="taskReportsBarSummaryArrow"></span>
                                            <span class="task-reports-bar-summary-pct" id="taskReportsBarSummaryPct"></span>
                                            <span class="task-reports-bar-summary-sep" id="taskReportsBarSummarySep" aria-hidden="true">•</span>
                                            <span class="task-reports-bar-summary-vs" id="taskReportsBarSummaryVs"></span>
                                        </div>
                                    </div>
                                    <div class="task-reports-bar-wrap">
                                        <canvas id="taskTimeBarChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pd-2 card plan-card">
                            <div class="page-title">
                                <h2><?php echo $lang['Staff Task Performance'] ?? 'Staff Task Performance'; ?></h2>
                            </div>
                            <div class="table-responsive scroll-x vh-100">
                                <table class="table table-new projectspage" data-pagination="true" data-page-size="5">
                                    <thead>
                                        <tr>
                                            <th><?php echo $lang['No'] ?? 'No'; ?></th>
                                            <th><?php echo $lang['User'] ?? 'User'; ?></th>
                                            <th><?php echo $lang['Total Tasks'] ?? 'Total tasks'; ?></th>
                                            <th><?php echo $lang['Completed'] ?? 'Completed'; ?></th>
                                            <th><?php echo $lang['Pending'] ?? 'Pending'; ?></th>
                                            <th><?php echo $lang['Overdue'] ?? 'Overdue'; ?></th>
                                            <th><?php echo $lang['Total Time'] ?? 'Total Time'; ?></th>
                                            <th><?php echo $lang['Avg Time / Task'] ?? 'Avg Time / Task'; ?></th>
                                            <th><?php echo $lang['Completion %'] ?? 'Completion %'; ?></th>
                                            <th><?php echo $lang['Options'] ?? ($lang['Action'] ?? 'Options'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="projects-tbl">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($reportType === 'projects') : ?>
<script>
window.projectReportsConfig = {
    type: 'projects',
    ajaxUrl: <?php echo json_encode($reportsAjaxProjectUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    baseUrl: <?php echo json_encode($url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    datePresets: <?php echo json_encode($datePresets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    lang: <?php echo json_encode($projectReportsLang, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    resetUrl: <?php echo json_encode('reports?projects', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    filters: <?php echo json_encode([
        'date_preset' => $datePreset,
        'from_date' => $fromDate,
        'to_date' => $toDate,
        'user_id' => $selectedUserId,
        'project_id' => $selectedProjectId,
        'client_id' => $selectedClientId,
        'status' => $selectedStatus,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
};
</script>
<?php elseif ($reportType === 'invoice') : ?>
<script>
window.invoiceReportsConfig = {
    type: 'invoice',
    ajaxUrl: <?php echo json_encode($reportsAjaxInvoiceUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    baseUrl: <?php echo json_encode($url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    datePresets: <?php echo json_encode($datePresets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    lang: <?php echo json_encode($invoiceReportsLang, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    resetUrl: <?php echo json_encode('reports?invoice', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    defaultCurrency: <?php echo json_encode($invoiceDefaultCurrency, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    moduleEnabled: <?php echo $isInvoicesEnabled ? 'true' : 'false'; ?>,
    filters: <?php echo json_encode([
        'date_preset' => $datePreset,
        'from_date' => $fromDate,
        'to_date' => $toDate,
        'client_id' => $selectedClientId,
        'project_id' => $selectedProjectId,
        'currency' => $selectedCurrency,
        'status' => $selectedStatus,
        'chart_grouping' => isset($filters['chart_grouping']) ? (string)$filters['chart_grouping'] : '',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
};
</script>
<?php elseif ($reportType === 'ecommerce') : ?>
<script>
window.ecommerceReportsConfig = {
    type: 'ecommerce',
    ajaxUrl: <?php echo json_encode($reportsAjaxEcommerceUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    baseUrl: <?php echo json_encode($url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    datePresets: <?php echo json_encode($datePresets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    lang: <?php echo json_encode($ecommerceReportsLang, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    resetUrl: <?php echo json_encode('reports?ecommerce', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    filters: <?php echo json_encode([
        'date_preset' => $datePreset,
        'from_date' => $fromDate,
        'to_date' => $toDate,
        'store_id' => $selectedStoreId,
        'chart_grouping' => isset($filters['chart_grouping']) ? (string)$filters['chart_grouping'] : '',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
};
</script>
<?php elseif ($reportType === 'task') : ?>
<script>
window.taskReportsConfig = {
    type: 'task',
    ajaxUrl: <?php echo json_encode($reportsAjaxTaskUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    baseUrl: <?php echo json_encode($url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    datePresets: <?php echo json_encode($datePresets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    lang: <?php echo json_encode($taskReportsLang, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    resetUrl: <?php echo json_encode('reports?task', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    filters: <?php echo json_encode([
        'date_preset' => $datePreset,
        'from_date' => $fromDate,
        'to_date' => $toDate,
        'user_id' => $selectedUserId,
        'project_id' => $selectedProjectId,
        'client_id' => $selectedClientId,
        'status' => $selectedStatus,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
};
</script>
<?php endif; ?>
<?php if (in_array($reportType, $reportsLoadingTypes, true)) : ?>
<script src="../assets/js/Chart.js"></script>
<?php
$reportsDashboardJsPath = dirname(__DIR__) . '/assets/js/reports-dashboard.js';
$reportsDashboardJsVer = is_file($reportsDashboardJsPath) ? (string) filemtime($reportsDashboardJsPath) : '1';
?>
<script src="../assets/js/reports-dashboard.js?v=<?php echo htmlspecialchars($reportsDashboardJsVer, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endif; ?>
<?php include __DIR__ . '/../templates/main-footer.php'; ?>
