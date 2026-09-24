<?php 
/*
 ================================================================================
   Task Session – Project Management System
   File: Client Dashboard Template
 ================================================================================
 */
ob_start();
require_once("../includes/lib-initialize.php");
require_once("../includes/reports_common_helper.php");
require_once("../includes/stat_sparkline_helper.php");
require_once("../includes/milestone_invoice_total.php");
require_once("../includes/company_profile_sales_helper.php");
require_once("../includes/dashboard_empty_state.php");

$title = "Dashboard | " . $syatem_title;
include("../templates/header.php");

// Authentication checks
if(!($session->isLoggedIn())){
    redirectTo($url."index.php");
}
if($_SESSION['accountStatus'] == 1){
    redirectTo($url."admin/index.php");
}
if($_SESSION['accountStatus'] == 3){
    redirectTo($url."staff/index.php");
} 

// Set timezone
date_default_timezone_set($time_zone);
$settingsForModules = settings::findById(1);
$invoiceModuleEnabled = !empty($settingsForModules->module_invoices);

$toast_flash = null;
if (isset($_GET['payment_status']) && $_GET['payment_status'] === 'success') {
    $toast_flash = [
        'type' => 'success',
        'msg' => $lang['Weve received your payment. Thank you!.'] ?? "We've received your payment. Thank you!",
    ];
} elseif (isset($_GET['payment_status']) && $_GET['payment_status'] === 'fail') {
    $toast_flash = [
        'type' => 'error',
        'msg' => isset($_GET['payment_msg']) && $_GET['payment_msg'] !== ''
            ? (string) $_GET['payment_msg']
            : ($lang['Payment failed!'] ?? 'Payment failed.'),
    ];
}

// Get current user data with error handling
try {
    $id = $session->userId;
    $user = User::findById((int)$id);
    
    if (!$user) {
        throw new Exception("User not found");
    }
    
    $username = $user->firstName;
    $email = $user->email;
    $account_stat = $user->status;
    $month = date('m');
    $curr_year = date("Y");
} catch (Exception $e) {
    // Fallback values
    $username = 'User';
    $email = '';
    $account_stat = 0;
    $month = date('m');
    $curr_year = date("Y");
}

// Handle note saving
if (isset($_POST['savenote'])) {
    try {
        $usera = user::findById((int)$session->userId);
        $usera->id = $session->userId;
        $usera->note = htmlspecialchars($_POST['snote']); // Sanitize input
        if ($usera->save()) {
            header("Location: index.php");
            exit();
        }
    } catch (Exception $e) {
        // error_log("Note saving error: " . $e->getMessage());
    }
}

// Fetch client-specific project statistics with error handling
try {
    $client_id = $session->userId;
    // Get projects where client is main client or additional client
    $projects = projects::findBySql("SELECT * FROM projects WHERE (c_id = " . (int)$client_id . " OR main_client_id = " . (int)$client_id . " OR FIND_IN_SET(" . (int)$client_id . ", c_ids))");
    $projectsb = projects::findBySql("SELECT * FROM projects WHERE (c_id = " . (int)$client_id . " OR main_client_id = " . (int)$client_id . " OR FIND_IN_SET(" . (int)$client_id . ", c_ids)) AND MONTH(start_time) = " . (int)$month);

    // Get project IDs for this client
    $client_project_ids = [];
    if ($projects) {
        foreach ($projects as $project) {
            $client_project_ids[] = $project->p_id;
        }
    }

    // Include both project invoices and direct client invoices
    $invoiceScope = "((m.p_id IS NOT NULL AND m.p_id > 0 AND p.p_id IS NOT NULL AND (p.c_id = '$client_id' OR p.main_client_id = '$client_id' OR FIND_IN_SET('$client_id', p.c_ids))) OR ((m.p_id IS NULL OR m.p_id = 0) AND m.c_id = '$client_id'))";
    $invoice_budget = milestone::findBySql("SELECT m.budget FROM milestones m LEFT JOIN projects p ON m.p_id = p.p_id WHERE m.status = '1' AND $invoiceScope");
    $recvable = milestone::findBySql("SELECT m.budget FROM milestones m LEFT JOIN projects p ON m.p_id = p.p_id WHERE m.status = '0' AND $invoiceScope");
    $this_month = milestone::findBySql("SELECT m.budget FROM milestones m LEFT JOIN projects p ON m.p_id = p.p_id WHERE $invoiceScope AND YEAR(m.releaseDate) = " . (int)$curr_year . " AND MONTH(m.releaseDate) = " . (int)$month . " AND m.status = '1'");

    // Count project statuses for this client
    $projects_ip = 0;
    $projects_c = 0;
    if ($projects) {
        foreach ($projects as $project) {
            if ($project->status == 0) {
                $projects_ip++;
            }
            if ($project->status == 1) {
                $projects_c++;
            }
        }
    }

    // Calculate budgets for client's projects
    $current_m = array_sum(array_column((array)$this_month, 'budget'));
    $fbudget = array_sum(array_column((array)$invoice_budget, 'budget'));
    $rfbudget = array_sum(array_column((array)$recvable, 'budget'));
    $paidInvoiceCount = count((array)$invoice_budget);
    $unpaidInvoiceCount = count((array)$recvable);

    // Monthly statistics for client's projects
    $present_year = date('Y');
    $monthly_earnings = [];
    $monthly_unpaid = [];
    $month_labels = [];

    for ($m = 1; $m <= 12; $m++) {
        $month_labels[] = $present_year . ' ' . date('M', mktime(0, 0, 0, $m, 1));
        
        // Paid milestones for client's projects
        $milestones_paid = milestone::findBySql(
            "SELECT m.budget FROM milestones m INNER JOIN projects p ON m.p_id = p.p_id WHERE m.status = 1 AND (p.c_id = '$client_id' OR p.main_client_id = '$client_id' OR FIND_IN_SET('$client_id', p.c_ids)) AND YEAR(m.releaseDate) = " . (int)$present_year . " AND MONTH(m.releaseDate) = " . (int)$m
        );
        
        // Unpaid milestones for client's projects
        $milestones_unpaid = milestone::findBySql(
            "SELECT m.budget FROM milestones m INNER JOIN projects p ON m.p_id = p.p_id WHERE m.status = 0 AND (p.c_id = '$client_id' OR p.main_client_id = '$client_id' OR FIND_IN_SET('$client_id', p.c_ids)) AND YEAR(m.deadline) = " . (int)$present_year . " AND MONTH(m.deadline) = " . (int)$m
        );
        
        $monthly_earnings[] = array_sum(array_column($milestones_paid, 'budget'));
        $monthly_unpaid[] = array_sum(array_column($milestones_unpaid, 'budget'));
    }
} catch (Exception $e) {
    // Fallback values
    $projects = [];
    $projectsb = [];
    $client_project_ids = [];
    $invoice_budget = [];
    $recvable = [];
    $this_month = [];
    $projects_ip = 0;
    $projects_c = 0;
    $current_m = 0;
    $fbudget = 0;
    $rfbudget = 0;
    $paidInvoiceCount = 0;
    $unpaidInvoiceCount = 0;
    $monthly_earnings = array_fill(0, 12, 0);
    $monthly_unpaid = array_fill(0, 12, 0);
    $month_labels = [];
    for ($m = 1; $m <= 12; $m++) {
        $month_labels[] = date('Y') . ' ' . date('M', mktime(0, 0, 0, $m, 1));
    }
}

// Task and project statistics for client
try {
    $month_start = date('Y-m-01');
    $month_end = date('Y-m-t');
    $last_month_start = date('Y-m-01', strtotime('-1 month'));
    $last_month_end = date('Y-m-t', strtotime('-1 month'));

    // Open tasks for client's projects only (not assigned to client personally)
    $client_id = $session->userId;
    
    if (!empty($client_project_ids)) {
        $project_ids_str = implode(',', $client_project_ids);
        
        $open_tasks_active_this_month = Task::findBySql(
            "SELECT DISTINCT t.* FROM tasks t 
            WHERE t.status = 'todo' 
            AND t.project_id IN ($project_ids_str)
            AND t.start_date <= '" . $database->escapeValue($month_end) . "' 
            AND t.due_date >= '" . $database->escapeValue($month_start) . "'"
        );

        $open_tasks_active_last_month = Task::findBySql(
            "SELECT DISTINCT t.* FROM tasks t 
            WHERE t.status = 'todo' 
            AND t.project_id IN ($project_ids_str)
            AND t.start_date <= '" . $database->escapeValue($last_month_end) . "' 
            AND t.due_date >= '" . $database->escapeValue($last_month_start) . "'"
        );
    } else {
        // Client has no projects, so no tasks
        $open_tasks_active_this_month = [];
        $open_tasks_active_last_month = [];
    }

    // Calculate task changes
    $this_count = count($open_tasks_active_this_month);
    $last_count = count($open_tasks_active_last_month);
    $percent_change = $last_count > 0 ? 
        round((($this_count - $last_count) / $last_count) * 100, 2) : 
        ($this_count > 0 ? 100 : 0);

    // Open projects for client
    $open_projects_active_this_month = projects::findBySql(
        "SELECT * FROM projects WHERE (c_id = " . (int)$client_id . " OR main_client_id = " . (int)$client_id . " OR FIND_IN_SET(" . (int)$client_id . ", c_ids)) AND status = 0 AND start_time <= '" . $database->escapeValue($month_end) . "' AND end_time >= '" . $database->escapeValue($month_start) . "'"
    );

    $open_projects_active_last_month = projects::findBySql(
        "SELECT * FROM projects WHERE (c_id = " . (int)$client_id . " OR main_client_id = " . (int)$client_id . " OR FIND_IN_SET(" . (int)$client_id . ", c_ids)) AND status = 0 AND start_time <= '" . $database->escapeValue($last_month_end) . "' AND end_time >= '" . $database->escapeValue($last_month_start) . "'"
    );

    // Calculate project changes
    $open_projects_this_count = count($open_projects_active_this_month);
    $open_projects_last_count = count($open_projects_active_last_month);
    $open_projects_percent_change = $open_projects_last_count > 0 ? 
        round((($open_projects_this_count - $open_projects_last_count) / $open_projects_last_count) * 100, 2) : 
        ($open_projects_this_count > 0 ? 100 : 0);
} catch (Exception $e) {
    // Fallback values
    $open_tasks_active_this_month = [];
    $open_tasks_active_last_month = [];
    $this_count = 0;
    $last_count = 0;
    $percent_change = 0;
    $open_projects_active_this_month = [];
    $open_projects_active_last_month = [];
    $open_projects_this_count = 0;
    $open_projects_last_count = 0;
    $open_projects_percent_change = 0;
}

// Helper function for color generation
function getColorById($id) {
    $colors = ['#a81bcb', '#2a5fb7', '#36b9cc', '#42b72a', '#fe6094', '#4db6ad', '#fd7e14', '#20c997'];
    return $colors[$id % count($colors)];
}

// Fetch private notes for client
try {
    $user_id = $_SESSION['userId'];
    $notes = [];
    if (!empty($user_id)) {
        $sql = "SELECT * FROM private_notes WHERE user_id = " . (int)$user_id . " ORDER BY updated_at DESC LIMIT 4";
        $result = $database->query($sql);
        if ($result) {
            while ($row = $database->fetchArray($result)) {
                // Decrypt content for display
                if (!empty($row['content'])) {
                    $row['content'] = decryptString($row['content']);
                }
                $notes[] = $row;
            }
        }
    }
} catch (Exception $e) {
    // error_log("Client index error - Notes: " . $e->getMessage());
    $notes = [];
}

// Calculate total task count for client's projects only
try {
    $total_task_count = 0;
    $client_id = $session->userId;
    
    if (!empty($client_project_ids)) {
        $project_ids_str = implode(',', $client_project_ids);
        
        $task_count_result = $database->query(
            "SELECT COUNT(DISTINCT t.id) as total FROM tasks t 
            WHERE t.project_id IN ($project_ids_str)"
        );
        if ($task_count_result && $row = $database->fetchArray($task_count_result)) {
            $total_task_count = $row['total'];
        }
    } else {
        // Client has no projects, so no tasks
        $total_task_count = 0;
    }
} catch (Exception $e) {
    $total_task_count = 0;
}

// Calculate total invoice count for all client projects
try {
    $total_invoice_count = 0;
    if (!empty($client_project_ids)) {
        $project_ids_str = implode(',', $client_project_ids);
        $invoice_count_result = $database->query("SELECT COUNT(*) as total FROM milestones WHERE p_id IN ($project_ids_str)");
        if ($invoice_count_result && $row = $database->fetchArray($invoice_count_result)) {
            $total_invoice_count = $row['total'];
        }
    }
} catch (Exception $e) {
    // error_log("Client index error - Invoice count: " . $e->getMessage());
    $total_invoice_count = 0;
}

// Last session data (used when invoice module is disabled).
$lastSessionDateDisplay = 'No data';
$lastSessionDurationDisplay = 'N/A';
try {
    $lastLoginQuery = "SELECT attempt_time FROM login_attempts WHERE user_id = " . (int)$id . " AND type = 'login' ORDER BY attempt_time DESC LIMIT 1";
    $lastLoginResult = mysqli_query($connect, $lastLoginQuery);
    $lastLogin = $lastLoginResult ? mysqli_fetch_assoc($lastLoginResult) : null;
    if ($lastLogin && !empty($lastLogin['attempt_time'])) {
        $lastLoginTime = $lastLogin['attempt_time'];
        $lastSessionDateDisplay = date('M j, Y', strtotime($lastLoginTime));
        $loginTime = strtotime($lastLoginTime);
        $logoutTs = null;
        $uidForSession = (int) $id;
        $logoutStmt = $connect->prepare("SELECT attempt_time FROM login_attempts WHERE user_id = ? AND type = 'logout' AND attempt_time > ? ORDER BY attempt_time ASC LIMIT 1");
        if ($logoutStmt) {
            $logoutStmt->bind_param('is', $uidForSession, $lastLoginTime);
            $logoutStmt->execute();
            $logoutRes = $logoutStmt->get_result();
            if ($logoutRes && ($logoutRow = $logoutRes->fetch_assoc())) {
                $logoutTs = strtotime($logoutRow['attempt_time']);
            }
            $logoutStmt->close();
        }
        $seenUser = User::findById($uidForSession);
        $lastSessionDurationDisplay = function_exists('user_presence_activity_duration_label')
            ? user_presence_activity_duration_label(
                $loginTime,
                $logoutTs,
                $seenUser->last_seen ?? 0,
                $seenUser->session_status ?? 'offline'
            )
            : 'N/A';
    }
    $lastSessionIsOnline = ($lastSessionDurationDisplay === 'Active');
} catch (Exception $e) {
    // Keep fallback values
    $lastSessionIsOnline = false;
}

$sparkCid = (int)($client_id ?? $id);
$sparkFrom = $database->escapeValue(date('Y-m-01', strtotime('-11 months')));
$milestoneEffDate = reports_sql_milestone_effective_date('m.');
if (!isset($invoiceScope)) {
    $invoiceScope = "((m.p_id IS NOT NULL AND m.p_id > 0 AND p.p_id IS NOT NULL AND (p.c_id = '{$sparkCid}' OR p.main_client_id = '{$sparkCid}' OR FIND_IN_SET('{$sparkCid}', p.c_ids))) OR ((m.p_id IS NULL OR m.p_id = 0) AND m.c_id = '{$sparkCid}'))";
}
$sparkInvoices = statSparklineSeriesFromQuery(
    $database,
    "SELECT DATE_FORMAT({$milestoneEffDate}, '%Y-%m') AS ym, COUNT(*) AS c
     FROM milestones m
     LEFT JOIN projects p ON m.p_id = p.p_id
     WHERE {$invoiceScope}
       AND {$milestoneEffDate} IS NOT NULL
       AND {$milestoneEffDate} >= '{$sparkFrom}'
     GROUP BY ym"
);
$sparkPaid = statSparklineSeriesFromQuery(
    $database,
    "SELECT DATE_FORMAT(m.releaseDate, '%Y-%m') AS ym, COUNT(*) AS c
     FROM milestones m
     LEFT JOIN projects p ON m.p_id = p.p_id
     WHERE m.status = '1' AND {$invoiceScope} AND m.releaseDate >= '{$sparkFrom}'
     GROUP BY ym"
);
$sparkUnpaid = statSparklineSeriesFromQuery(
    $database,
    "SELECT DATE_FORMAT({$milestoneEffDate}, '%Y-%m') AS ym, COUNT(*) AS c
     FROM milestones m
     LEFT JOIN projects p ON m.p_id = p.p_id
     WHERE m.status = '0' AND {$invoiceScope}
       AND {$milestoneEffDate} IS NOT NULL
       AND {$milestoneEffDate} >= '{$sparkFrom}'
     GROUP BY ym"
);
$sparkProjectIds = [];
if (!empty($client_project_ids) && is_array($client_project_ids)) {
    foreach ($client_project_ids as $pid) {
        $pid = (int)$pid;
        if ($pid > 0) {
            $sparkProjectIds[] = $pid;
        }
    }
}
$sparkTasks = !empty($sparkProjectIds)
    ? statSparklineSeriesFromQuery(
        $database,
        "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS c
         FROM tasks
         WHERE project_id IN (" . implode(',', $sparkProjectIds) . ")
           AND created_at >= '{$sparkFrom}'
         GROUP BY ym"
    )
    : array_fill(0, 12, 0);
$sparkSession = statSparklineSeriesFromQuery(
    $database,
    "SELECT DATE_FORMAT(attempt_time, '%Y-%m') AS ym, COUNT(*) AS c
     FROM login_attempts
     WHERE user_id = {$sparkCid} AND type = 'login' AND attempt_time >= '{$sparkFrom}'
     GROUP BY ym"
);
$clientProjectSparkScope = "(c_id = {$sparkCid} OR main_client_id = {$sparkCid} OR FIND_IN_SET({$sparkCid}, c_ids)) AND archive = 0 AND trash != 1";
$sparkProjects = statSparklineSeriesFromQuery(
    $database,
    "SELECT DATE_FORMAT(start_time, '%Y-%m') AS ym, COUNT(*) AS c
     FROM projects
     WHERE {$clientProjectSparkScope}
       AND start_time >= '{$sparkFrom}'
     GROUP BY ym"
);

$clientTaskScope = !empty($sparkProjectIds)
    ? 'project_id IN (' . implode(',', $sparkProjectIds) . ')'
    : '1=0';
$taskInfoOnTimeSql = "(NOT " . reports_sql_valid_date('due_date') . " OR DATE(completed_at) <= DATE(due_date))";
$taskInfoLateSql = "(" . reports_sql_valid_date('due_date') . " AND DATE(completed_at) > DATE(due_date))";
$taskInfoStatsRow = method_exists($database, 'querySoft')
    ? $database->querySoft(
        "SELECT
        SUM(CASE WHEN status = 'done' AND completed_at IS NOT NULL AND DATE(completed_at) = CURDATE() THEN 1 ELSE 0 END) AS done_today_c,
        SUM(CASE WHEN status != 'done' AND " . reports_sql_valid_date('due_date') . " AND DATE(due_date) = CURDATE() THEN 1 ELSE 0 END) AS due_today_c,
        SUM(CASE WHEN status != 'done' AND " . reports_sql_valid_date('due_date') . " AND DATE(due_date) < CURDATE() THEN 1 ELSE 0 END) AS overdue_c
     FROM tasks
     WHERE {$clientTaskScope}"
    )
    : $database->query(
        "SELECT
        SUM(CASE WHEN status = 'done' AND completed_at IS NOT NULL AND DATE(completed_at) = CURDATE() THEN 1 ELSE 0 END) AS done_today_c,
        SUM(CASE WHEN status != 'done' AND " . reports_sql_valid_date('due_date') . " AND DATE(due_date) = CURDATE() THEN 1 ELSE 0 END) AS due_today_c,
        SUM(CASE WHEN status != 'done' AND " . reports_sql_valid_date('due_date') . " AND DATE(due_date) < CURDATE() THEN 1 ELSE 0 END) AS overdue_c
     FROM tasks
     WHERE {$clientTaskScope}"
    );
$taskInfoStats = $taskInfoStatsRow ? ($database->fetchArray($taskInfoStatsRow) ?: array()) : array();
$taskInfoMonthStart = $database->escapeValue(date('Y-m-01'));
$taskInfoMonthEnd = $database->escapeValue(date('Y-m-t'));
$taskInfoLastStart = $database->escapeValue(date('Y-m-01', strtotime('-1 month')));
$taskInfoLastEnd = $database->escapeValue(date('Y-m-t', strtotime('-1 month')));
$taskInfoPerfThis = $database->fetchArray($database->query(
    "SELECT COUNT(*) AS total_c,
            SUM(CASE WHEN {$taskInfoOnTimeSql} THEN 1 ELSE 0 END) AS on_time_c
     FROM tasks
     WHERE {$clientTaskScope}
       AND status = 'done'
       AND completed_at IS NOT NULL
       AND completed_at >= '{$taskInfoMonthStart}'
       AND completed_at <= '{$taskInfoMonthEnd} 23:59:59'"
));
$taskInfoPerfLast = $database->fetchArray($database->query(
    "SELECT COUNT(*) AS total_c,
            SUM(CASE WHEN {$taskInfoOnTimeSql} THEN 1 ELSE 0 END) AS on_time_c
     FROM tasks
     WHERE {$clientTaskScope}
       AND status = 'done'
       AND completed_at IS NOT NULL
       AND completed_at >= '{$taskInfoLastStart}'
       AND completed_at <= '{$taskInfoLastEnd} 23:59:59'"
));
$taskInfoDoneThisMonth = (int) ($taskInfoPerfThis['total_c'] ?? 0);
$taskInfoDoneLastMonth = (int) ($taskInfoPerfLast['total_c'] ?? 0);
$taskInfoOnTimeThisMonth = (int) ($taskInfoPerfThis['on_time_c'] ?? 0);
$taskInfoOnTimeLastMonth = (int) ($taskInfoPerfLast['on_time_c'] ?? 0);
$taskInfoOnTimePctThisMonth = $taskInfoDoneThisMonth > 0
    ? (int) round(($taskInfoOnTimeThisMonth / $taskInfoDoneThisMonth) * 100)
    : 0;
$taskInfoOnTimePctLast = $taskInfoDoneLastMonth > 0
    ? (int) round(($taskInfoOnTimeLastMonth / $taskInfoDoneLastMonth) * 100)
    : 0;
$taskInfoOnTimePctChange = $taskInfoOnTimePctThisMonth - $taskInfoOnTimePctLast;
$taskInfoPerfAll = $database->fetchArray($database->query(
    "SELECT COUNT(*) AS total_c,
            SUM(CASE WHEN {$taskInfoOnTimeSql} THEN 1 ELSE 0 END) AS on_time_c
     FROM tasks
     WHERE {$clientTaskScope}
       AND status = 'done'
       AND completed_at IS NOT NULL"
));
$taskInfoDoneAllRow = $database->fetchArray($database->query(
    "SELECT COUNT(*) AS c
     FROM tasks
     WHERE {$clientTaskScope}
       AND status = 'done'"
));
$taskInfoDoneAll = (int) ($taskInfoDoneAllRow['c'] ?? 0);
$taskInfoDoneForPerf = (int) ($taskInfoPerfAll['total_c'] ?? 0);
$taskInfoOnTimeAll = (int) ($taskInfoPerfAll['on_time_c'] ?? 0);
$taskInfoOnTimePct = $taskInfoDoneForPerf > 0
    ? (int) round(($taskInfoOnTimeAll / $taskInfoDoneForPerf) * 100)
    : 0;
$taskInfoFirstYmRow = $database->fetchArray($database->query(
    "SELECT DATE_FORMAT(MIN(CASE
        WHEN UNIX_TIMESTAMP(completed_at) > 0 THEN completed_at
        WHEN UNIX_TIMESTAMP(created_at) > 0 THEN created_at
        ELSE NULL
    END), '%Y-%m') AS first_ym
     FROM tasks
     WHERE {$clientTaskScope}"
));
$taskInfoSparkMonths = statSparklineMonthsSince($taskInfoFirstYmRow['first_ym'] ?? '');
$taskInfoDueValid = reports_sql_valid_date('due_date');
$taskInfoMissedSql = "(
    (status = 'done' AND completed_at IS NOT NULL AND {$taskInfoDueValid} AND DATE(completed_at) > DATE(due_date))
    OR (status != 'done' AND {$taskInfoDueValid} AND DATE(due_date) < CURDATE())
)";
$sparkTaskOnTime = statSparklineSeriesFromQuery(
    $database,
    "SELECT DATE_FORMAT(completed_at, '%Y-%m') AS ym, COUNT(*) AS c
     FROM tasks
     WHERE {$clientTaskScope}
       AND status = 'done'
       AND completed_at IS NOT NULL
       AND {$taskInfoOnTimeSql}
     GROUP BY ym",
    $taskInfoSparkMonths
);
$sparkTaskLate = statSparklineSeriesFromQuery(
    $database,
    "SELECT DATE_FORMAT(due_date, '%Y-%m') AS ym, COUNT(*) AS c
     FROM tasks
     WHERE {$clientTaskScope}
       AND {$taskInfoDueValid}
       AND {$taskInfoMissedSql}
     GROUP BY ym",
    $taskInfoSparkMonths
);
$taskInfoCardData = [
    'assigned' => (int) $total_task_count,
    'completed' => $taskInfoDoneAll,
    'due_today' => (int) ($taskInfoStats['due_today_c'] ?? 0),
    'overdue' => (int) ($taskInfoStats['overdue_c'] ?? 0),
];
$clientSessionProfileUrl = $url . 'client/profile?user_id=' . (int) $id . '&tab=activity';
$clientTaskInfoOptions = [
    'card_id' => 'task-information-card',
    'title' => $lang['Task Information'] ?? 'Task information',
    'badge_count' => (int) $total_task_count,
    'badge_label' => $lang['Total Task'] ?? 'Total Tasks',
    'link_url' => $url . 'client/kanban',
    'view_label' => $lang['View Task'] ?? 'View Tasks',
];

$invoice_overview_total = (int) ($paidInvoiceCount ?? 0) + (int) ($unpaidInvoiceCount ?? 0);
$paidAmountAll = 0.0;
$unpaidAmount = 0.0;
$overdueInvoiceCount = 0;
$overdueAmount = 0.0;
$invoicePaidPctChange = 0;
$dashCurrencyCode = 'USD';
$dashCurrencySymbol = '$';
$clientCurrencyRaw = '';
$clientInvoiceCurrencies = [];
$fmtDashMoney = static function ($amount, $symbol) {
    return $symbol . number_format((float) $amount, 2, '.', ',');
};

if ($invoiceModuleEnabled && !empty($invoiceScope)) {
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

    if (isset($user) && is_object($user) && !empty($user->currency)) {
        $clientCurrencyRaw = trim((string) $user->currency);
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
         WHERE m.status = '1' AND {$invoiceScope} AND m.releaseDate >= '{$sparkFrom}'
           {$currencyCondition}
         GROUP BY ym"
    );
    $sparkUnpaid = statSparklineSeriesFromQuery(
        $database,
        "SELECT DATE_FORMAT({$milestoneEffDate}, '%Y-%m') AS ym, COUNT(*) AS c
         FROM milestones m
         LEFT JOIN projects p ON m.p_id = p.p_id
         WHERE m.status = '0' AND {$invoiceScope}
           AND {$milestoneEffDate} IS NOT NULL
           AND {$milestoneEffDate} >= '{$sparkFrom}'
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
        $paidInvoiceCount = count($paidMilestonesAll);
    }
    $unpaidMilestonesAll = milestone::findBySql(
        "SELECT m.* FROM milestones m LEFT JOIN projects p ON m.p_id = p.p_id
         WHERE (m.status = '0' OR m.status = 0) AND {$invoiceScope}{$currencyCondition}"
    );
    if (is_array($unpaidMilestonesAll)) {
        foreach ($unpaidMilestonesAll as $um) {
            $unpaidAmount += milestone_calculate_invoice_total($um);
        }
        $unpaidInvoiceCount = count($unpaidMilestonesAll);
    }
    $overdueMilestoneSql = ' AND ' . reports_sql_valid_date('m.deadline') . ' AND DATE(m.deadline) < CURDATE()';
    $overdueMilestonesAll = milestone::findBySql(
        "SELECT m.* FROM milestones m LEFT JOIN projects p ON m.p_id = p.p_id
         WHERE (m.status = '0' OR m.status = 0) AND {$invoiceScope}{$currencyCondition}{$overdueMilestoneSql}"
    );
    if (is_array($overdueMilestonesAll)) {
        foreach ($overdueMilestonesAll as $om) {
            $overdueAmount += milestone_calculate_invoice_total($om);
        }
        $overdueInvoiceCount = count($overdueMilestonesAll);
    }
    $invoice_overview_total = (int) $paidInvoiceCount + (int) $unpaidInvoiceCount;

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
?>
<link rel="stylesheet" href="<?php echo $url; ?>assets/css/reports.css">
<div class="page-container client-dashboard">
  <div class="container-fluid">
    <div class="row row-eq-height">
      <?php include("../templates/sidebar.php"); ?>
		<div class="page-content dashboard-page" style="padding-bottom:0px !important;">
		  <?php include('../templates/top-header.php'); ?>
			  <div class="extra-page-pd">
				<div class="row client-dash-stats-row">
                   <div class="col-md-12 col-lg-4">
                     <div class="widget-card shadow center-align pie-chart">
                              <div class="d-flex justify-content-between align-items-center">
                                    <div class="card-title">
                                        <h3><?php echo $lang['Total Projects']; ?></h3>
                                    </div>
                                    <div class="border-btn">
                                        <a href="<?php echo $url; ?>client/projects">
                                            <?php echo $lang['View all']; ?>
                                        </a>
                                    </div>
                                </div>
                                      <div class="chart-pie mb-2  " style="width: 200px; height: 200px; margin: 0 auto;">
                                        <canvas id="myPieChart" width="350" height="350"></canvas>
				                         <div class="total-pro total-pro-client">
                                         <h2><?php echo count($projects); ?></h2><span><?php echo $lang['Projects']; ?></span>
                                    </div>
                                </div>
				                       <div class="container counters-bottom">
                                    <div class="row chart-footer justify-content-center col-gap-35 full-col-gap-35-sep ">
                                        <div class=" foot-c-box">
										<div class="d-flex align-items-center justify-content-end col-gap-10">
										        <span class="fctxt " style="text-align: right;"><?php echo $lang['Inprogress Projects']; ?></span> 
	                                         <div class="font-size-30  primary">  
										 <?php echo $projects_ip; ?></div>
                                        </div>
										</div>									
										 <div class="foot-c-box green">
										<div class="d-flex align-items-center col-gap-10">
										 <div class="font-size-30"> <?php echo $projects_c; ?></div>
                                           <span class="fctxt"><?php echo $lang['Completed Projects']; ?></span> 
                                        </div>
                                        </div>
                                    </div>
                                   </div>
                                </div>
                               </div>
						   <div class="col-md-12 col-lg-8">
							   <div class="client-dash-right-stack">
						<?php
						$dashCol6TaskInfo = 'col-12 col-lg-6 order-1 order-sm-0 staff-dash-stat-col-half';
						$dashCol3Project = 'col-6 col-lg-3 order-2 order-sm-0';
						$dashCol3Task = 'col-6 col-lg-3 order-3 order-sm-0';
						if ($invoiceModuleEnabled): ?>
							   <div class="row counter-align client-dash-invoice-row">
						<div class="col-12">
							<div class="widget-card stat-spark-card stat-spark-card--invoice-overview widget-card--linked" id="invoice-overview-card">
								<div class="stat-invoice-head">
									<div class="grey"><span>Invoice Overview</span></div>
									<div class="stat-invoice-head-actions">
										<?php if (count($clientInvoiceCurrencies) > 1): ?>
										<select id="invoice-overview-currency" class="stat-invoice-currency-select" aria-label="<?php echo htmlspecialchars($lang['Currency'] ?? 'Currency', ENT_QUOTES, 'UTF-8'); ?>">
											<?php
											foreach ($clientInvoiceCurrencies as $currencyOption) {
												$opt = profile_sales_encode_currency_option((string) $currencyOption, $settingsForModules);
												$code = trim(explode(',', $opt['value'])[0]);
												$sym = '';
												if (strpos($opt['value'], ',') !== false) {
													$sym = trim(explode(',', $opt['value'], 2)[1]);
												}
												$simpleLabel = $code . ($sym !== '' ? ' (' . $sym . ')' : '');
												$selected = ($code === $dashCurrencyCode) ? ' selected' : '';
												echo '<option value="' . htmlspecialchars($opt['value'], ENT_QUOTES, 'UTF-8') . '"' . $selected . '>'
													. htmlspecialchars($simpleLabel, ENT_QUOTES, 'UTF-8')
													. '</option>';
											}
											?>
										</select>
										<?php endif; ?>
										<span class="stat-invoice-badge" id="invoice-overview-badge"><?php echo (int) $invoice_overview_total; ?> total</span>
									</div>
								</div>
								<div class="stat-invoice-grid stat-invoice-grid--triple">
									<div class="stat-invoice-col">
										<a href="<?php echo $url; ?>client/invoices?status=1" class="counts dash-rttb is-paid mt-0 stat-invoice-count-link" id="invoice-overview-paid-count" aria-label="<?php echo htmlspecialchars($lang['Paid Invoices'] ?? 'Paid Invoices', ENT_QUOTES, 'UTF-8'); ?>"><?php echo (int) $paidInvoiceCount; ?></a>
										<div class="stat-invoice-meta" id="invoice-overview-paid-meta">
											<div class="grey persent-count stat-invoice-meta__label"><?php echo $lang['Paid Invoices']; ?></div>
											<div class="grey persent-count stat-invoice-meta__amount" id="invoice-overview-paid-amount"><?php echo htmlspecialchars($fmtDashMoney($paidAmountAll, $dashCurrencySymbol), ENT_QUOTES, 'UTF-8'); ?></div>
										</div>
									</div>
									<div class="stat-invoice-col">
										<a href="<?php echo $url; ?>client/invoices?status=0" class="counts dash-rttb is-unpaid mt-0 stat-invoice-count-link" id="invoice-overview-unpaid-count" aria-label="<?php echo htmlspecialchars($lang['Unpaid Invoices'] ?? 'Unpaid Invoices', ENT_QUOTES, 'UTF-8'); ?>"><?php echo (int) $unpaidInvoiceCount; ?></a>
										<div class="stat-invoice-meta" id="invoice-overview-unpaid-meta">
											<div class="grey persent-count stat-invoice-meta__label"><?php echo $lang['Unpaid']; ?></div>
											<div class="grey persent-count stat-invoice-meta__amount" id="invoice-overview-unpaid-amount"><?php echo htmlspecialchars($fmtDashMoney($unpaidAmount, $dashCurrencySymbol), ENT_QUOTES, 'UTF-8'); ?></div>
										</div>
									</div>
									<div class="stat-invoice-col">
										<a href="<?php echo $url; ?>client/invoices?status=0" class="counts dash-rttb is-overdue mt-0 stat-invoice-count-link" id="invoice-overview-overdue-count" aria-label="<?php echo htmlspecialchars($lang['Overdue'] ?? 'Overdue', ENT_QUOTES, 'UTF-8'); ?>"><?php echo (int) $overdueInvoiceCount; ?></a>
										<div class="stat-invoice-meta" id="invoice-overview-overdue-meta">
											<div class="grey persent-count stat-invoice-meta__label"><?php echo $lang['Overdue'] ?? 'Overdue'; ?></div>
											<div class="grey persent-count stat-invoice-meta__amount" id="invoice-overview-overdue-amount"><?php echo htmlspecialchars($fmtDashMoney($overdueAmount, $dashCurrencySymbol), ENT_QUOTES, 'UTF-8'); ?></div>
										</div>
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
							   </div>
							   <div class="row counter-align client-dash-cards-row">
						<div class="<?php echo $dashCol6TaskInfo; ?>">
							<?php renderDashWorkOverviewCard($url, $lang, $taskInfoCardData, $sparkTaskOnTime, $sparkTaskLate, $taskInfoOnTimePct, $taskInfoOnTimePctChange, $clientTaskInfoOptions); ?>
						</div>
						<div class="<?php echo $dashCol3Project; ?>">
							<?php renderDashOpenProjectCard($url, $lang, $open_projects_this_count, $open_projects_percent_change, $sparkProjects, 'client/projects'); ?>
						</div>
						<div class="<?php echo $dashCol3Task; ?>">
							<?php renderDashOpenTaskCard($url, $lang, $this_count, $percent_change, $sparkTasks, 'client/kanban'); ?>
						</div>
							   </div>
							   </div>
                        <?php else: ?>
							   <div class="row counter-align client-dash-session-row">
						<div class="col-12">
							<?php renderDashSessionCard($url, $lang, $lastSessionDateDisplay, $lastSessionDurationDisplay, $lastSessionIsOnline, $sparkSession, $clientSessionProfileUrl); ?>
						</div>
							   </div>
							   <div class="row counter-align client-dash-cards-row">
						<div class="<?php echo $dashCol6TaskInfo; ?>">
							<?php renderDashWorkOverviewCard($url, $lang, $taskInfoCardData, $sparkTaskOnTime, $sparkTaskLate, $taskInfoOnTimePct, $taskInfoOnTimePctChange, $clientTaskInfoOptions); ?>
						</div>
						<div class="<?php echo $dashCol3Project; ?>">
							<?php renderDashOpenProjectCard($url, $lang, $open_projects_this_count, $open_projects_percent_change, $sparkProjects, 'client/projects'); ?>
						</div>
						<div class="<?php echo $dashCol3Task; ?>">
							<?php renderDashOpenTaskCard($url, $lang, $this_count, $percent_change, $sparkTasks, 'client/kanban'); ?>
						</div>
							   </div>
							   </div>
                        <?php endif; ?>
				 </div>
			  </div>
				<div class="row db-container ">
                        <div class="col-lg-12 col-md-12 col-sm-12">
                            <div class="db-box-wrap widget-card ">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div class="card-title">
                                        <h3><?php echo $lang['Running Projects']; ?></h3>
                                    </div>
                                    <div class="border-btn">
                                        <a href="<?php echo $url; ?>client/projects">
                                            <?php echo $lang['View all']; ?>
                                        </a>
                                    </div>
                                </div>
                                <div class="col-12">
								                                    <?php 
                                    try {
                                        // Get projects where client is main client or additional client
                                        $recentProjects = projects::findBySql("SELECT * FROM projects WHERE archive=0 AND trash != 1 AND (c_id = " . (int)$client_id . " OR main_client_id = " . (int)$client_id . " OR FIND_IN_SET(" . (int)$client_id . ", c_ids)) ORDER BY start_time DESC LIMIT 5"); 
                                    } catch (Exception $e) {
                                        // error_log("Client index error - Recent projects: " . $e->getMessage());
                                        $recentProjects = [];
                                    }
                                    ?>
                                    <div class="row"> <div class="d-flex col-gap-20 xx">
                                        <?php if (empty($recentProjects)): ?>
                                            <div class="col-12">
                                                <?php renderDashboardEmptyState('projects', $lang['No projects found.'] ?? 'No projects found.', ['list_item' => false]); ?>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach($recentProjects as $recentProject): ?>
                                                <?php
                                                try {
                                                    $projectId = $recentProject->p_id;
                                                    global $database;
                                                    // Total tasks
                                                    $taskQuery = "SELECT COUNT(*) as task_count FROM tasks WHERE project_id = $projectId";
                                                    $taskResult = $database->query($taskQuery);
                                                    $taskCount = 0;
                                                    if($taskResult && $taskRow = $database->fetchArray($taskResult)) {
                                                        $taskCount = $taskRow['task_count'];
                                                    }
                                                    // Completed tasks
                                                    $completedTaskQuery = "SELECT COUNT(*) as completed_count FROM tasks WHERE project_id = $projectId AND status = 'done'";
                                                    $completedTaskResult = $database->query($completedTaskQuery);
                                                    $completedTaskCount = 0;
                                                    if($completedTaskResult && $completedTaskRow = $database->fetchArray($completedTaskResult)) {
                                                        $completedTaskCount = $completedTaskRow['completed_count'];
                                                    }
                                                    $percent = ($taskCount > 0) ? round(($completedTaskCount / $taskCount) * 100) : 0;
                                                } catch (Exception $e) {
                                                    // error_log("Client index error - Project stats: " . $e->getMessage());
                                                    $taskCount = 0;
                                                    $completedTaskCount = 0;
                                                    $percent = 0;
                                                }
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
                                                                    <a class="dropdown-item" href="media?projectId=<?php echo (int)$recentProject->p_id; ?>">
                                                                        <?php echo ts_icon('folder', 'h-6 tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Media & files'] ?? 'Media & files'; ?>
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                    <a class="dropdown-item" href="payments?projectId=<?php echo (int)$recentProject->p_id; ?>">
                                                                        <?php echo ts_icon('payments', 'h-6 tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Payments'] ?? 'Payments'; ?>
                                                                    </a>
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
                                                                            try {
                                                                                $user2 = user::findById($st_id); 
                                                                                if ($user2) {
                                                                                    echo '<div class="user-box">';
                                                                                    echo '<form action="../discussion?project_id='.$recentProject->p_id .'" method="post"><input type="hidden" name="user_id" value="'.$st_id.'" /><input type="hidden" name="project_id" value="'.$recentProject->p_id .'" /><button name="chat" type="submit" data-bs-toggle="tooltip" data-bs-placement="top" title="'.$user2->firstName.'">';
                                                                                    echo getUserAvatarHtml($st_id, $user2->firstName, $user2->lastName ?? '', 36, 36, '', $user2->firstName);
                                                                                    echo '</button></form>';
                                                                                    echo '</div>'; 
                                                                                }
                                                                            } catch (Exception $e) {
                                                                                // error_log("Client index error - Staff avatar for $st_id: " . $e->getMessage());
                                                                            }
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
                                                                try {
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
                                                                } catch (Exception $e) {
                                                                    // error_log("Client index error - Client avatar: " . $e->getMessage());
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
					<div class="row align-items-stretch">
						 <div class="col-lg-6 col-md-6 col-sm-12 d-flex">
                            <div class="widget-card flex-grow ">
								<?php
								// Fetch latest 5 tasks for current client only
								try {
									// For client users, only show tasks from their projects
									// If client has no projects, show no tasks
									$client_id = $session->userId;
									
									if (!empty($client_project_ids)) {
										$project_ids_str = implode(',', $client_project_ids);
										
										// Only show active/running tasks from client's projects (not completed)
										$recent_tasks = Task::findBySql(
											"SELECT DISTINCT t.* FROM tasks t 
											WHERE t.project_id IN ($project_ids_str)
											AND t.status != 'done'
											ORDER BY t.created_at DESC LIMIT 4"
										);
									} else {
										// Client has no projects, so show no tasks
										$recent_tasks = [];
									}
									
								} catch (Exception $e) {
									// error_log("Client index error - Recent tasks: " . $e->getMessage());
									$recent_tasks = [];
								}
								?>
								<div class="task-widget">
								  <div class="d-flex justify-content-between align-items-center mb-4">
									<div class="card-title">
										<h3><?php echo $lang['Running Task']; ?></h3>
									</div>
									<div class="border-btn">
										<a href="<?php echo $url; ?>client/kanban">
											<?php echo $lang['View all']; ?>
										</a>
									</div>
								  </div>

								  <div>
									<?php if (!empty($recent_tasks)): ?>
									  <?php foreach ($recent_tasks as $task): ?>
										<div class="d-flex align-items-center dash-task" style="gap: 12px;">
										  <div class="flex-grow text-align-left">
											<div class="title font-size-14 mb-2"><?php echo htmlspecialchars($task->title); ?></div>
											<div class="font-size-12 grey">
											  <?php
												$start = $task->start_date ? date('M j', strtotime($task->start_date)) : '';
												$end = $task->due_date ? date('M j', strtotime($task->due_date)) : '';
												if ($start && $end) {
													$today = new DateTime();
													$dueDate = new DateTime($task->due_date);
													echo "Start: $start &nbsp;|&nbsp;  ";
													echo $dueDate < $today ? "<span style='color: #e74a3b;'>" . $lang['Due Date'] . ": $end</span>" : $lang['Due Date'] . ": $end";
												} elseif ($end) {
													$today = new DateTime();
													$dueDate = new DateTime($task->due_date);
													echo $dueDate < $today ? "<span style='color: #e74a3b;'>" . $lang['Due Date'] . ": $end</span>" : $lang['Due Date'] . ": $end";
												} elseif ($start) {
													echo "Start: $start";
												}
											  ?>
											</div>
										  </div>
										  <div class="d-flex align-items-center" style="gap: 2px;">
											<?php
											  $assigned_ids = array_filter(explode(',', $task->assigned_to));
											  $shown = 0;
											  foreach ($assigned_ids as $uid) {
												  if ($shown >= 3) break;
												  try {
													  $user = user::findById($uid);
													  if ($user) {
														  echo "<div class='user-box' data-bs-toggle='tooltip' data-bs-placement='top' title='".htmlspecialchars($user->firstName)."'>";
														  echo getUserAvatarHtml($uid, $user->firstName, $user->lastName ?? '', 32, 32, 'avatar', $user->firstName);
														  echo "</div>";
														  $shown++;
													  }
												  } catch (Exception $e) {
													  // Skip this user if there's an error
												  }
											  }
											  if (count($assigned_ids) > 3) {
												  echo "<div class='plus-more'>+".(count($assigned_ids)-3)."</div>";
											  }
											?>
											</div>
											  <div class="dropdown ms-2">
												<button class="btn-dots " type="button" data-bs-toggle="dropdown" aria-expanded="false">
												  <?php echo ts_icon('dots-vertical'); ?>
												</button>
												<ul class="dropdown-menu">
												  <li>
													<a class="dropdown-item view-task-btn" href="#" data-task-id="<?php echo $task->id; ?>">
													  <?php echo ts_icon('eye', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['View Task']; ?>
													</a>
												  </li>
												  <?php if (!empty($task->project_id) && $task->project_id != 0): ?>
												  <li>
													<a class="dropdown-item" href="overview?projectId=<?php echo $task->project_id; ?>">
													  <?php echo ts_icon('archive', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['View Project']; ?>
													</a>
												  </li>
												  <?php endif; ?>
												</ul>
											  </div>
											</div>
										  <?php endforeach; ?>
										<?php else: ?>
										  <?php renderDashboardEmptyState('tasks', $lang['No tasks found.'] ?? 'No tasks found.'); ?>
										<?php endif; ?>
									  </div>
									</div>
                            </div>
                        </div>
                         <div class="col-lg-6 col-md-6 col-sm-12 d-flex">
                           	<div class="widget-card flex-grow">
                              <div class="d-flex justify-content-between align-items-center">
                                    <div class="card-title">
                                        <h3><?php echo $lang['Documents']; ?></h3>
                                    </div>
									  <div class="border-btn">
                                        <a href="<?php echo $url; ?>client/documents">
                                            <?php echo $lang['View all']; ?>
                                        </a>
                                    </div>
                              </div>
					 	<div class="list-group dash-docs-list">
                        <?php if (empty($notes)): ?>
                            <?php renderDashboardEmptyState('documents', $lang['No notes found.'] ?? 'No notes found.'); ?>
                        <?php else: ?>
                            <?php
                            $docCalIcon = function_exists('ts_icon') ? ts_icon('calendar', 'dash-task-meta-ico') : '';
                            foreach ($notes as $note):
                                $createdTs = !empty($note['created_at']) ? strtotime((string) $note['created_at']) : 0;
                                $updatedTs = !empty($note['updated_at']) ? strtotime((string) $note['updated_at']) : 0;
                                $createdLabel = $createdTs > 0 ? date('M j', $createdTs) : '';
                                $updatedLabel = $updatedTs > 0 ? date('M j', $updatedTs) : '';
                                $showUpdated = ($createdLabel !== '' && $updatedLabel !== '' && (string) $note['created_at'] !== (string) $note['updated_at']);
                            ?>
                                <div class="list-group-item note-card dash-task-card d-flex align-items-center justify-content-between" style="position: relative; cursor:pointer;">
                                    <a href="documents?note_id=<?php echo (int) $note['id']; ?>" class="dash-task-link flex-grow text-align-left">
                                        <div class="dash-task-title font-size-14"><?php echo htmlspecialchars($note['title']); ?></div>
                                        <div class="dash-task-meta font-size-12 grey">
                                            <?php if ($createdLabel !== '') : ?>
                                            <span class="dash-task-meta-date">
                                                <?php echo $docCalIcon; ?>
                                                <?php echo htmlspecialchars(($lang['Created'] ?? 'Created') . ': ' . $createdLabel, ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                            <?php endif; ?>
                                            <?php if ($showUpdated) : ?>
                                            <?php if ($createdLabel !== '') : ?><span class="dash-task-meta-sep" aria-hidden="true"></span><?php endif; ?>
                                            <span class="dash-task-meta-date">
                                                <?php echo $docCalIcon; ?>
                                                <?php echo htmlspecialchars(($lang['Updated'] ?? 'Updated') . ': ' . $updatedLabel, ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                    <div class="dash-task-aside d-flex align-items-center">
                                    <div class="dropdown ms-2 dash-task-menu">
                                        <button class="btn-dots" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?php echo htmlspecialchars($lang['Actions'] ?? 'Actions', ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo ts_icon('dots-vertical'); ?>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                               <a class="dropdown-item" href="documents?note_id=<?php echo (int) $note['id']; ?>">
                                                  <?php echo ts_icon('eye', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['View Note']; ?>
                                                </a>
                                            </li>
                                            <li>
                                               <a class="dropdown-item" href="documents?note_id=<?php echo (int) $note['id']; ?>">
                                                  <?php echo ts_icon('edit', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Edit Note']; ?>
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item text-danger" href="documents?delete=<?php echo (int) $note['id']; ?>" onclick="return confirm('Delete this note?')">
                                                  <?php echo ts_icon('delete', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Delete']; ?>
                                                </a>
                                            </li>
                                        </ul>
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
      </div>
   </div>
</div>
<script>
window.totalProjects = <?php echo (int)count($projects); ?>;
window.projectsCompleted = <?php echo (int)$projects_c; ?>;
window.projectsInProgress = <?php echo (int)$projects_ip; ?>;
window.completedLabel = <?php echo json_encode($lang['Completed Projects']); ?>;
window.inprogressLabel = <?php echo json_encode($lang['Inprogress Projects']); ?>;
window.invoicePaidLabel = <?php echo json_encode($lang['Paid Invoices'] ?? 'Paid Invoices'); ?>;
window.invoiceOverdueLabel = <?php echo json_encode($lang['Overdue'] ?? 'Overdue'); ?>;
window.invoiceUnpaidLabel = <?php echo json_encode($lang['Unpaid'] ?? 'Unpaid'); ?>;
window.defaultCurrency = <?php echo json_encode($clientCurrencyRaw !== '' ? $clientCurrencyRaw : ($dashCurrencyCode . ',' . $dashCurrencySymbol)); ?>;
window.selectedCurrency = window.defaultCurrency;
window.profileUserId = <?php echo (int) ($id ?? 0); ?>;
</script>
<script src="../assets/js/Chart.js"></script>
<script src="../assets/js/analytics.js"></script>
<?php if ($invoiceModuleEnabled && count($clientInvoiceCurrencies) > 1): ?>
<script>
(function () {
  var selectEl = document.getElementById('invoice-overview-currency');
  if (!selectEl || typeof applyInvoiceOverviewData !== 'function') {
    return;
  }

  function invoiceOverviewAjaxUrl() {
    var root = (typeof window.siteRootUrl === 'string' && window.siteRootUrl.length)
      ? window.siteRootUrl.replace(/\/$/, '')
      : '';
    return root ? (root + '/ajax/invoice_overview.php') : '../ajax/invoice_overview.php';
  }

  function loadClientInvoiceOverview(currency) {
    window.selectedCurrency = currency;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', invoiceOverviewAjaxUrl(), true);
    xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    xhr.onload = function () {
      if (xhr.status !== 200) {
        return;
      }
      try {
        var data = JSON.parse(xhr.responseText);
        if (!data || data.error) {
          return;
        }
        applyInvoiceOverviewData(data);
        var totalEl = document.getElementById('client-total-invoices-count');
        if (totalEl && typeof data.total_count !== 'undefined') {
          totalEl.textContent = String(data.total_count || 0);
        }
      } catch (e) {}
    };
    xhr.send(
      'currency=' + encodeURIComponent(currency) +
      '&user_id=' + encodeURIComponent(String(window.profileUserId || 0)) +
      '&all_time=1'
    );
  }

  selectEl.addEventListener('change', function () {
    loadClientInvoiceOverview(selectEl.value);
  });
})();
</script>
<?php endif; ?>
<?php
if ($invoiceModuleEnabled && empty($_SESSION['client_outstanding_invoices_dismissed'])) {
    require_once('../includes/task_permission.php');
    $taskPermissions = TaskPermission::getOrCreate($session->userId);
    if ($taskPermissions->can_view_milestones) {
        require_once('../includes/client_outstanding_invoices_helper.php');
        $outstandingInvoicesPayload = client_outstanding_invoices_payload((int) $id, $username);
        if ($outstandingInvoicesPayload) {
            ob_start();
            include('../templates/modals/client-outstanding-invoices-modal.php');
            include('../templates/client-invoice-view-modal-bundle.php');
            include('../templates/client-payment-modal-bundle.php');
            echo '<script src="' . htmlspecialchars(rtrim($url, '/') . '/assets/js/client-outstanding-invoices-modal.js', ENT_QUOTES, 'UTF-8') . '"></script>';
            $GLOBALS['comon_before_body_close_html'] = ob_get_clean();
        }
    }
}
?>
<script>window.__toastFlash=<?php echo json_encode(isset($toast_flash) ? $toast_flash : null, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
<script src="<?php echo htmlspecialchars(rtrim((string) $url, '/') . '/assets/js/toast.js', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php if (isset($_GET['payment_status'])) { ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (window.history && window.history.replaceState) {
    var url = new URL(window.location.href);
    url.searchParams.delete('payment_status');
    url.searchParams.delete('payment_msg');
    window.history.replaceState({}, document.title, url.pathname + url.search + url.hash);
  }
});
</script>
<?php } ?>
<?php
include("../templates/main-footer.php"); ?>


