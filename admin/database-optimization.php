<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : database-optimization.php
   Purpose : Database optimization management for administrators
 ================================================================================
 */
ob_start(); 
require_once("../includes/lib-initialize.php");
require_once("../includes/database_optimizer.php");
require_once("../includes/settings.php");
require_once("../includes/auth_helper.php");

$title = "Database Optimization | " . $syatem_title;
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

// load current logged-in user
$id = $session->userId;
$user = User::findById((int)$id);
$username = $user->firstName;
$email = $user->email;
$account_stat = $user->status;

$success_message = '';
if (!empty($_GET['toast'])) {
    switch ((string)$_GET['toast']) {
        case 'log_deleted':
            $success_message = $lang['Login attempt record deleted.'] ?? 'Record deleted.';
            break;
        case 'unblocked':
            $success_message = $lang['Failed login attempts cleared for this user.'] ?? 'User unblocked; they can try to log in again.';
            break;
        case 'ip_saved':
            $success_message = $lang['Settings saved successfully!'] ?? 'Settings saved.';
            break;
    }
}

// Handle manual optimization
if (isset($_POST['run_optimization'])) {
    $optimizer = new DatabaseOptimizer();
    $optimizer->runOptimization();
    $success_message = "Database optimization completed successfully!";
}

// Handle scheduled event creation
if (isset($_POST['create_scheduled_cleanup'])) {
    $optimizer = new DatabaseOptimizer();
    $optimizer->enableEventScheduler();
    $optimizer->createCleanupEvent();
    $success_message = "Scheduled cleanup event created successfully!";
}

if (isset($_POST['save_ip_restriction_settings'])) {
    global $database;
    $mod = isset($_POST['module_ip_restriction']) ? 1 : 0;
    $ga = isset($_POST['global_allowed_ips']) ? trim((string)$_POST['global_allowed_ips']) : '';
    $gaSql = ($ga === '') ? 'NULL' : "'" . $database->escapeValue($ga) . "'";
    $sql = "UPDATE `settings` SET `module_ip_restriction` = " . (int)$mod . ", `global_allowed_ips` = " . $gaSql . " WHERE `id` = 1 LIMIT 1";
    $database->query($sql);
    header('Location: database-optimization.php?ip_tab=1&toast=ip_saved');
    exit;
}

if (isset($_POST['dbopt_delete_login_attempt']) && !empty($connect)) {
    $aid = isset($_POST['attempt_id']) ? (int)$_POST['attempt_id'] : 0;
    if ($aid > 0) {
        $st = $connect->prepare('DELETE FROM login_attempts WHERE id = ? LIMIT 1');
        if ($st) {
            $st->bind_param('i', $aid);
            $st->execute();
        }
        header('Location: database-optimization.php?ip_tab=1&toast=log_deleted');
        exit;
    }
}

if (isset($_POST['dbopt_unblock_login_email'])) {
    $em = isset($_POST['unblock_email']) ? trim((string)$_POST['unblock_email']) : '';
    if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL)) {
        AuthHelper::clearFailedAttemptsForEmail($em);
        header('Location: database-optimization.php?ip_tab=1&toast=unblocked');
        exit;
    }
}

// Get database statistics
$optimizer = new DatabaseOptimizer();
$stats = $optimizer->getDatabaseStats();
$recommendations = $optimizer->getCleanupRecommendations();

$settingsForIpPanel = settings::findById(1);
$dboptIpTabActive = isset($_GET['ip_tab']) && (string)$_GET['ip_tab'] === '1';

$allowedLogTypes = array('login', 'logout', 'remember_me', 'failed');
if (!empty($connect)) {
    $colRes = @$connect->query("SHOW COLUMNS FROM `login_attempts` LIKE 'type'");
    if ($colRes && ($colRow = $colRes->fetch_assoc()) && !empty($colRow['Type']) && preg_match_all("/'([^']*)'/", $colRow['Type'], $m)) {
        $allowedLogTypes = $m[1];
    }
}
$logTypeFilter = isset($_GET['log_type']) ? trim((string)$_GET['log_type']) : '';
if ($logTypeFilter !== '' && !in_array($logTypeFilter, $allowedLogTypes, true)) {
    $logTypeFilter = '';
}
$logPerPage = 10;
$logPage = isset($_GET['log_page']) ? max(1, (int)$_GET['log_page']) : 1;

$loginLogTotal = 0;
$loginLogRows = array();
$lockedEmailCounts = AuthHelper::getTemporarilyLockedEmailCounts();
$logTotalPages = 1;
$logOffset = 0;

if (!empty($connect)) {
    $whereExtra = '';
    if ($logTypeFilter !== '') {
        $whereExtra = " WHERE type = '" . $connect->real_escape_string($logTypeFilter) . "'";
    }
    $cntRes = @$connect->query("SELECT COUNT(*) AS c FROM login_attempts{$whereExtra}");
    if ($cntRes && ($cr = $cntRes->fetch_assoc())) {
        $loginLogTotal = (int)$cr['c'];
    }
    $logTotalPages = $loginLogTotal > 0 ? (int)ceil($loginLogTotal / $logPerPage) : 1;
    if ($logPage > $logTotalPages) {
        $logPage = $logTotalPages;
    }
    $logOffset = ($logPage - 1) * $logPerPage;
    $sqlLog = "SELECT id, user_id, email, success, type, ip_address, attempt_time FROM login_attempts{$whereExtra} ORDER BY attempt_time DESC LIMIT " . (int)$logPerPage . " OFFSET " . (int)$logOffset;
    $lr = @$connect->query($sqlLog);
    if ($lr) {
        while ($row = $lr->fetch_assoc()) {
            $loginLogRows[] = $row;
        }
    }
}

$dboptLogEmailByUserId = array();
if (!empty($loginLogRows) && !empty($connect)) {
    $uidNeed = array();
    foreach ($loginLogRows as $lrRow) {
        if (trim((string)($lrRow['email'] ?? '')) === '' && isset($lrRow['user_id']) && (int)$lrRow['user_id'] > 0) {
            $uidNeed[(int)$lrRow['user_id']] = true;
        }
    }
    if (!empty($uidNeed)) {
        $idCsv = implode(',', array_map('intval', array_keys($uidNeed)));
        if ($idCsv !== '') {
            $equ = @$connect->query("SELECT id, email FROM users WHERE id IN (" . $idCsv . ")");
            if ($equ) {
                while ($ur = $equ->fetch_assoc()) {
                    $dboptLogEmailByUserId[(int)$ur['id']] = trim((string)($ur['email'] ?? ''));
                }
            }
        }
    }
}

$dboptLogUrl = function ($page, $typeOverride = null) use ($logTypeFilter) {
    $t = $typeOverride !== null ? $typeOverride : $logTypeFilter;
    $q = array('ip_tab' => '1', 'log_page' => max(1, (int)$page));
    if ($t !== '') {
        $q['log_type'] = $t;
    }
    return 'database-optimization.php?' . http_build_query($q);
};

$currentRequestIp = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';
$dboptLockMax = AuthHelper::bruteForceMaxAttempts();
$dboptLockMin = (int)ceil(AuthHelper::bruteForceLockoutSeconds() / 60);
$logShowFrom = $loginLogTotal === 0 ? 0 : ($logOffset + 1);
$logShowTo = $loginLogTotal === 0 ? 0 : min($logOffset + count($loginLogRows), $loginLogTotal);
?>

<div class="page-container">
    <div class="container-fluid">
        <div class="row row-eq-height">
            <?php include("../templates/sidebar.php"); ?>
            <div class="page-content" style="padding-bottom:0;">
                <?php include('../templates/top-header.php'); ?>
                <div class="row system-wrap">
                    <?php include("../templates/system-nav.php"); ?>
                    <div class="col-md-9 ss-right">
                        <?php if (isset($success_message) && $success_message !== ''): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert" style="margin-top:10px;">
                            <?php echo htmlspecialchars($success_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?php echo htmlspecialchars($lang['Close'] ?? 'Close', ENT_QUOTES, 'UTF-8'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        <?php endif; ?>
                        <ul class="nav nav-tabs mt-3 mb-0" role="tablist">
                            <li class="nav-item" role="presentation">
                                <a class="nav-link<?php echo !$dboptIpTabActive ? ' active' : ''; ?>" id="db-opt-database-tab" href="database-optimization.php"><?php echo $lang['Database'] ?? 'Database'; ?></a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link<?php echo $dboptIpTabActive ? ' active' : ''; ?>" id="db-opt-ip-tab" href="database-optimization.php?ip_tab=1"><?php echo $lang['IP Restriction'] ?? 'IP Restriction'; ?></a>
                            </li>
                        </ul>
                        <div class="tab-content">
                        <div class="tab-pane fade<?php echo !$dboptIpTabActive ? ' show active' : ''; ?>" id="db-opt-database-pane" role="tabpanel" aria-labelledby="db-opt-database-tab">
                                                 <h2 class="page-title d-flex justify-content-between align-items-center">
                             <?php echo $lang['Database Optimization'] ?? 'Database Optimization'; ?>
                             <a href="database-optimization.php" class="primary-btn">
                                 <?php echo $lang['Refresh']; ?>
                             </a>
                         </h2>
                        
                        <div class="system-settings-container">
                            <div class="settings-grid">
                                <div class="settings-main">
                                    <!-- Database Statistics Overview -->
                                    <div class="form-group">
                                        <div class="cache-static widget-card">
                                            <div class="card-title mb-3"><?php echo $lang['Database Statistics']; ?></div>
                                            
                                            <!-- Database Statistics -->
                                            <div class="stats-section">
                                                <div class="row mb-3">
                                                                                                         <div class="col-md-6">
                                                         <div class="stat-item">
                                                             <div class="stat-label"><?php echo $lang['Login Attempts']; ?></div>
                                                             <div class="stat-value"><?php echo number_format($stats['login_attempts']['rows'] ?? 0); ?></div>
                                                         </div>
                                                     </div>
                                                                                                         <div class="col-md-6">
                                                         <div class="stat-item">
                                                             <div class="stat-label"><?php echo $lang['Security Logs']; ?></div>
                                                             <div class="stat-value"><?php echo number_format($stats['security_logs']['rows'] ?? 0); ?></div>
                                                         </div>
                                                     </div>
                                                </div>
                                                <div class="row">
                                                                                                         <div class="col-md-6">
                                                         <div class="stat-item">
                                                             <div class="stat-label"><?php echo $lang['Remember Me Tokens']; ?></div>
                                                             <div class="stat-value"><?php echo number_format($stats['remember_me_tokens']['rows'] ?? 0); ?></div>
                                                         </div>
                                                     </div>
                                                    <div class="col-md-6">
                                                        <div class="stat-item total">
                                                            <div class="stat-label"><?php echo $lang['Total Size']; ?></div>
                                                            <div class="stat-value"><?php 
                                                                $totalSize = 0;
                                                                foreach ($stats as $table) {
                                                                    $totalSize += $table['size_mb'] ?? 0;
                                                                }
                                                                echo number_format($totalSize, 2) . ' MB';
                                                            ?></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Optimization Policy -->
                                    <div class="form-group">
                                        <div class="settings-card">
                                            <div class="card-header">
                                                <h4>
                                                    <?php echo $lang['Optimization Policy']; ?>
                                                </h4>
                                                <p><?php echo $lang['Data retention and cleanup policies']; ?></p>
                                            </div>
                                            <div class="card-body">
                                                <div class="license-details">
                                                    <div class="detail-row">
                                                        <div class="detail-label">
                                                            <svg class="detail-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                                                <polyline points="12,6 12,12 16,14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                            </svg>
                                                            <?php echo $lang['Failed Login Attempts']; ?>
                                                        </div>
                                                        <div class="detail-value">7 <?php echo $lang['days']; ?></div>
                                                    </div>
                                                    <div class="detail-row">
                                                        <div class="detail-label">
                                                            <svg class="detail-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                                                <polyline points="12,6 12,12 16,14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                            </svg>
                                                            <?php echo $lang['Successful Logins']; ?>
                                                        </div>
                                                        <div class="detail-value">30 <?php echo $lang['days']; ?></div>
                                                    </div>
                                                    <div class="detail-row">
                                                        <div class="detail-label">
                                                            <svg class="detail-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                                                <polyline points="12,6 12,12 16,14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                            </svg>
                                                            <?php echo $lang['Security Logs']; ?>
                                                        </div>
                                                        <div class="detail-value">30 <?php echo $lang['days']; ?></div>
                                                    </div>
                                                    <div class="detail-row">
                                                        <div class="detail-label">
                                                            <svg class="detail-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                                                <polyline points="12,6 12,12 16,14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                            </svg>
                                                            <?php echo $lang['Remember Me Tokens']; ?>
                                                        </div>
                                                        <div class="detail-value"><?php echo $lang['Auto-expire']; ?></div>
                                                    </div>
                                                    <div class="detail-row">
                                                        <div class="detail-label">
                                                            <svg class="detail-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                                                <polyline points="12,6 12,12 16,14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                            </svg>
                                                            <?php echo $lang['Table Optimization']; ?>
                                                        </div>
                                                        <div class="detail-value"><?php echo $lang['Weekly']; ?></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Table Details -->
                                    <div class="form-group">
                                        <div class="settings-card">
                                            <div class="card-header">
                                                <h4>
                                                    <?php echo $lang['Table Details']; ?>
                                                </h4>
                                                <p><?php echo $lang['Detailed information about database tables']; ?></p>
                                            </div>
                                            <div class="card-body">
                                                 <div class="scroll-x">
                                                    <table class="table table-fancy">
                                                        <thead>
                                                            <tr>
                                                                <th><?php echo $lang['Table Name']; ?></th>
                                                                <th><?php echo $lang['Records']; ?></th>
                                                                <th><?php echo $lang['Size (MB)']; ?></th>
                                                                <th><?php echo $lang['Status']; ?></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($stats as $tableName => $tableStats): ?>
                                                            <tr>
                                                                <td>
                                                                    <?php echo ucwords(str_replace('_', ' ', $tableName)); ?>
                                                                </td>
                                                                <td>
                                                                    <span class="badge completed"><?php echo number_format($tableStats['rows'] ?? 0); ?></span>
                                                                </td>
                                                                <td>
                                                                    <span class="badge inprogress"><?php echo number_format($tableStats['size_mb'] ?? 0, 2); ?> MB</span>
                                                                </td>
                                                                <td>
                                                                    <span class="status-badge valid">
                                                                        <span class="status-dot"></span>
                                                                        <span class="status-text"><?php echo $lang['Auto-managed']; ?></span>
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Recommendations -->
                                    <?php if (!empty($recommendations)): ?>
                                    <div class="form-group">
                                        <div class="settings-card">
                                            <div class="card-header">
                                                <h4>
                                                    <?php echo $lang['Optimization Recommendations']; ?>
                                                </h4>
                                                <p><?php echo $lang['Suggested actions for database optimization']; ?></p>
                                            </div>
                                            <div class="card-body">
                                                <div class="recommendations-list">
                                                    <?php foreach ($recommendations as $recommendation): ?>
                                                    <div class="recommendation-item">
                                                        <svg class="recommendation-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                                            <path d="M12 16v-4M12 8h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                        </svg>
                                                        <span><?php echo htmlspecialchars($recommendation); ?></span>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="settings-sidebar">
                                    <!-- Database Optimization -->
                                    <div class="form-group mb-4">
                                        <div class="settings-card">
                                            <div class="card-header">
                                                <h4>
                                                  <?php echo $lang['Login Attempts']; ?>
                                                </h4>
                                                <p><?php echo $lang['Cleanup and optimize database tables']; ?></p>
                                            </div>
                                            <div class="card-body">
                                                <form method="post">
                                                    <div class="button-group">
                                                        <button type="submit" name="run_optimization" class="btn primary-btn">
                                                            <svg class="btn-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                <path d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                            </svg>
                                                            <?php echo $lang['Run Optimization']; ?>
                                                        </button>
                                                        <button type="submit" name="create_scheduled_cleanup" class="btn outline-btn">
                                                            <svg class="btn-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                <path d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                            </svg>
                                                            <?php echo $lang['Enable Auto-Cleanup']; ?>
                                                        </button>
                                                    </div>
                                                </form>
                                                
                                                <div class="settings-info mt-3">
                                                    <div class="info-item">
                                                        <svg class="info-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                                            <path d="M12 16v-4M12 8h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                        </svg>
                                                        <span><?php echo $lang['Database optimization helps maintain performance']; ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </div>
                        <div class="tab-pane fade<?php echo $dboptIpTabActive ? ' show active' : ''; ?>" id="db-opt-ip-pane" role="tabpanel" aria-labelledby="db-opt-ip-tab">
                            <h2 class="page-title mb-3"><?php echo $lang['IP Restriction'] ?? 'IP Restriction'; ?></h2>
                            <form method="post" action="">
                                <?php
                                $settings = $settingsForIpPanel;
                                $fragmentFormId = 'dbopt-ip';
                                $fragmentRequestIp = $currentRequestIp;
                                include dirname(__DIR__) . '/includes/ip_restriction_settings_fragment.php';
                                ?>
                            </form>
                            <div class="widget-card mt-4">
                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                                    <div class="card-title mb-0"><?php echo $lang['Login activity log'] ?? 'Login activity log'; ?></div>
                                </div>
                                <p class="text-muted small mb-3"><?php echo sprintf($lang['Brute-force lockout policy'] ?? 'Accounts are temporarily blocked after %1$d failed attempts within %2$d minutes (same as login).', (int)$dboptLockMax, (int)$dboptLockMin); ?></p>
                                <?php if (!empty($lockedEmailCounts)): ?>
                                <div class="alert alert-warning py-2 px-3 small mb-3">
                                    <strong><?php echo $lang['Currently locked emails'] ?? 'Currently over failure limit'; ?>:</strong>
                                    <?php
                                    $lockedParts = array();
                                    foreach ($lockedEmailCounts as $lem => $lcnt) {
                                        $lockedParts[] = htmlspecialchars($lem) . ' <span class="text-muted">(' . (int)$lcnt . ')</span>';
                                    }
                                    echo implode(', ', $lockedParts);
                                    ?>
                                </div>
                                <?php endif; ?>
                                <form method="get" action="database-optimization.php" class="d-flex align-items-end col-gap-10 flex-wrap mb-3">
                                    <input type="hidden" name="ip_tab" value="1">
                                    <div class="floating-filter-field">
                                        <label class="floating-label" for="dbopt_log_type"><?php echo htmlspecialchars($lang['Filter by type'] ?? 'Filter by type', ENT_QUOTES, 'UTF-8'); ?></label>
                                        <select name="log_type" id="dbopt_log_type" class="form-control" onchange="this.form.submit()">
                                            <option value=""><?php echo $lang['All types'] ?? 'All types'; ?></option>
                                            <?php foreach ($allowedLogTypes as $lt): ?>
                                                <option value="<?php echo htmlspecialchars($lt, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $logTypeFilter === $lt ? ' selected' : ''; ?>><?php echo htmlspecialchars($lt); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </form>
                                <div class="table-responsive scroll-x">
                                    <table class="table table-new projectspage mb-0">
                                        <thead>
                                            <tr>
                                                <th class="text-muted" style="width:2.75rem;"><?php echo $lang['No.'] ?? 'No.'; ?></th>
                                                <th><?php echo $lang['Email'] ?? 'Email'; ?></th>
                                                <th><?php echo $lang['Success'] ?? 'Success'; ?></th>
                                                <th><?php echo $lang['Type'] ?? 'Type'; ?></th>
                                                <th><?php echo $lang['IP Address'] ?? 'IP'; ?></th>
                                                <th><?php echo $lang['Time'] ?? 'Time'; ?></th>
                                                <th class="text-end text-nowrap" style="width:10rem;"><?php echo $lang['Actions'] ?? 'Actions'; ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($loginLogRows)): ?>
                                                <tr><td colspan="7" class="text-center text-muted py-4"><?php echo $lang['No data found.'] ?? 'No records.'; ?></td></tr>
                                            <?php else: ?>
                                                <?php $logRowNum = $logOffset; foreach ($loginLogRows as $lr): $logRowNum++; ?>
                                                    <?php
                                                    $lrEmail = trim((string)($lr['email'] ?? ''));
                                                    if ($lrEmail === '' && !empty($lr['user_id'])) {
                                                        $lrUid = (int)$lr['user_id'];
                                                        if ($lrUid > 0 && isset($dboptLogEmailByUserId[$lrUid])) {
                                                            $lrEmail = $dboptLogEmailByUserId[$lrUid];
                                                        }
                                                    }
                                                    $lrLocked = ($lrEmail !== '' && isset($lockedEmailCounts[$lrEmail]));
                                                    $ok = !empty($lr['success']);
                                                    ?>
                                                    <tr>
                                                        <td class="text-muted"><?php echo (int)$logRowNum; ?></td>
                                                        <td><span class="fw-medium"><?php echo $lrEmail !== '' ? htmlspecialchars($lrEmail) : '—'; ?></span></td>
                                                        <td><?php if ($ok): ?><span class="badge bg-success"><?php echo $lang['Success'] ?? 'OK'; ?></span><?php else: ?><span class="badge bg-danger"><?php echo $lang['Failed'] ?? 'Fail'; ?></span><?php endif; ?></td>
                                                        <td><span class="badge bg-secondary bg-opacity-25 text-dark"><?php echo htmlspecialchars((string)($lr['type'] ?? '—')); ?></span></td>
                                                        <td><code class="small"><?php echo htmlspecialchars((string)($lr['ip_address'] ?? '')); ?></code></td>
                                                        <td class="text-nowrap small"><?php echo htmlspecialchars((string)($lr['attempt_time'] ?? '')); ?></td>
                                                        <td class="text-end text-nowrap">
                                                            <?php if ($lrLocked && $lrEmail !== ''): ?>
                                                            <form method="post" class="d-inline-block me-1" action="database-optimization.php?ip_tab=1" onsubmit="return confirm('<?php echo htmlspecialchars($lang['Confirm unblock email'] ?? 'Clear failed attempts for this email?', ENT_QUOTES, 'UTF-8'); ?>');">
                                                                <input type="hidden" name="dbopt_unblock_login_email" value="1">
                                                                <input type="hidden" name="unblock_email" value="<?php echo htmlspecialchars($lrEmail, ENT_QUOTES, 'UTF-8'); ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-success"><?php echo $lang['Unblock'] ?? 'Unblock'; ?></button>
                                                            </form>
                                                            <?php endif; ?>
                                                            <div class="border-btn d-inline-block">
                                                                <a href="#" class="delete-field-btn" data-id="<?php echo (int)($lr['id'] ?? 0); ?>"><?php echo $lang['Delete'] ?? 'Delete'; ?></a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if ($loginLogTotal > 0): ?>
                                <div class="row pagination-box mt-3">
                                    <div class="col-md-6 resilts-txt">
                                        <?php echo $lang['Showing'] ?? 'Showing'; ?> <span class="start_val"><?php echo (int)$logShowFrom; ?></span>
                                        <?php echo $lang['to'] ?? 'to'; ?> <span class="end_val"><?php echo (int)$logShowTo; ?></span>
                                        <?php echo $lang['of'] ?? 'of'; ?>
                                        <span class="total_val"><?php echo (int)$loginLogTotal; ?></span>
                                        <?php echo $lang['entries'] ?? 'entries'; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <?php if ($logTotalPages > 1): ?>
                                        <nav aria-label="<?php echo htmlspecialchars($lang['Pagination'] ?? 'Pagination'); ?>">
                                            <ul class="pagination justify-content-end mb-0">
                                                <li class="page-item<?php echo $logPage <= 1 ? ' disabled' : ''; ?>">
                                                    <?php if ($logPage <= 1): ?>
                                                    <span class="page-link"><span aria-hidden="true">&laquo;</span></span>
                                                    <?php else: ?>
                                                    <a class="page-link" href="<?php echo htmlspecialchars($dboptLogUrl(1)); ?>" aria-label="<?php echo htmlspecialchars($lang['Previous'] ?? 'First'); ?>"><span aria-hidden="true">&laquo;</span></a>
                                                    <?php endif; ?>
                                                </li>
                                                <?php
                                                $winStart = max(1, $logPage - 2);
                                                $winEnd = min($logTotalPages, $logPage + 2);
                                                for ($pi = $winStart; $pi <= $winEnd; $pi++):
                                                ?>
                                                <li class="page-item<?php echo $pi === $logPage ? ' active' : ''; ?>">
                                                    <?php if ($pi === $logPage): ?>
                                                    <span class="page-link"><?php echo (int)$pi; ?></span>
                                                    <?php else: ?>
                                                    <a class="page-link" href="<?php echo htmlspecialchars($dboptLogUrl($pi)); ?>"><?php echo (int)$pi; ?></a>
                                                    <?php endif; ?>
                                                </li>
                                                <?php endfor; ?>
                                                <li class="page-item<?php echo $logPage >= $logTotalPages ? ' disabled' : ''; ?>">
                                                    <?php if ($logPage >= $logTotalPages): ?>
                                                    <span class="page-link"><span aria-hidden="true">&raquo;</span></span>
                                                    <?php else: ?>
                                                    <a class="page-link" href="<?php echo htmlspecialchars($dboptLogUrl($logTotalPages)); ?>" aria-label="<?php echo htmlspecialchars($lang['Next'] ?? 'Last'); ?>"><span aria-hidden="true">&raquo;</span></a>
                                                    <?php endif; ?>
                                                </li>
                                            </ul>
                                        </nav>
                                        <?php endif; ?>
                                    </div>
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
<form id="dbopt-delete-login-attempt-form" method="post" action="database-optimization.php?ip_tab=1" class="d-none" aria-hidden="true">
    <input type="hidden" name="dbopt_delete_login_attempt" value="1">
    <input type="hidden" name="attempt_id" id="dbopt_delete_login_attempt_id" value="">
</form>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var pane = document.getElementById('db-opt-ip-pane');
    if (!pane) return;
    var confirmMsg = <?php echo json_encode($lang['Confirm delete login attempt'] ?? 'Delete this log row?', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var form = document.getElementById('dbopt-delete-login-attempt-form');
    var idInput = document.getElementById('dbopt_delete_login_attempt_id');
    pane.querySelectorAll('a.delete-field-btn[data-id]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            if (!confirm(confirmMsg)) return;
            var id = el.getAttribute('data-id');
            if (!id || !form || !idInput) return;
            idInput.value = id;
            form.submit();
        });
    });
});
</script>
<?php include("../templates/main-footer.php"); ?> 