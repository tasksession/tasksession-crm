<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : cron.php
   Purpose : Central cron management panel for configuring and running cron jobs
 ================================================================================
*/
ob_start(); 
require_once("../includes/lib-initialize.php");
require_once("../includes/cron_helper.php");
$title = "Cron Management | ". $syatem_title;
include("../templates/header.php");

if (! $session->isLoggedIn()) {
    redirectTo($url . "index.php");
}
if ($_SESSION['accountStatus'] == 2) {
    redirectTo($url . "client/index.php");
}
if ($_SESSION['accountStatus'] == 3) {
    redirectTo($url . "staff/index.php");
}

$id           = $session->userId;
$user         = User::findById((int)$id);
$username     = $user->firstName;
$email        = $user->email;
$account_stat = $user->status;

$settingsForCron = (isset($dash_settings) && $dash_settings) ? $dash_settings : settings::findById(1);
$attendanceModuleEnabled = $settingsForCron && !empty($settingsForCron->module_attendance);
if (!function_exists('comon_ecommerce_module_enabled')) {
    require_once __DIR__ . '/../includes/addon_registry.php';
}
$ecommerceModuleEnabledForCron = comon_ecommerce_module_enabled();

$isFreeEditionCron = function_exists('tasksession_is_free_edition') && tasksession_is_free_edition();
$freeHiddenCronCategories = array('Google Drive', 'Invoice', 'Messages', 'Email', 'Attendance');
if ($isFreeEditionCron) {
    $attendanceModuleEnabled = false;
}

/**
 * Free edition: skip Pro-only cron jobs from UI / run-all list.
 */
$cronJobIsFreeHidden = static function (array $row) use ($isFreeEditionCron, $freeHiddenCronCategories): bool {
    if (!$isFreeEditionCron) {
        return false;
    }
    $category = isset($row['category']) ? (string) $row['category'] : '';
    $fp = strtolower(str_replace('\\', '/', (string) ($row['file_path'] ?? '')));
    if (in_array($category, $freeHiddenCronCategories, true)) {
        return true;
    }
    $bannedNeedles = array(
        'gdrive',
        'g-calender-cron',
        'google/gdrive',
        'cron_recurring_invoices',
        'payment_reminders',
        'email_sync',
        'email_report',
        'email_tracking',
        'group_chat_batch',
        'discussion_chat_batch',
        'one_to_one_chat_batch',
        'task_chat_batch',
        'attendance_auto_absent',
    );
    foreach ($bannedNeedles as $needle) {
        if ($needle !== '' && strpos($fp, $needle) !== false) {
            return true;
        }
    }
    return false;
};

// Get all cron jobs from database
global $database;
$cronJobs = [];
$jobsByCategory = [
    'Command' => [],
    'Google Drive' => [],
    'Invoice' => [],
    'Messages' => [],
    'Tasks' => [],
    'Email' => [],
    'Attendance' => [],
    'Ecommerce' => [],
    'Session' => []
];

$sql = "SELECT * FROM cron_jobs ORDER BY category, name";
$result = method_exists($database, 'querySoft')
    ? $database->querySoft($sql)
    : (isset($connect) && $connect instanceof mysqli ? @mysqli_query($connect, $sql) : false);
if (!$result && isset($connect) && $connect instanceof mysqli) {
    // Incomplete install: create cron_jobs + seed Free edition jobs.
    @mysqli_query($connect, "CREATE TABLE IF NOT EXISTS `cron_jobs` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(255) NOT NULL,
        `file_path` VARCHAR(500) NOT NULL,
        `category` VARCHAR(50) NOT NULL,
        `duration` VARCHAR(50) DEFAULT '1 hour',
        `enabled` TINYINT(1) DEFAULT 1,
        `last_run` DATETIME DEFAULT NULL,
        `last_run_status` VARCHAR(20) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_category` (`category`),
        KEY `idx_enabled` (`enabled`),
        KEY `idx_last_run` (`last_run`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");
    $seedJobs = array(
        array('Task Reminders', 'cron/task_reminders.php', 'Tasks', '1 day'),
        array('Recurring Tasks', 'cron/cron_recurring_tasks.php', 'Tasks', '1 day'),
        array('Import Jobs Worker', 'cron/import_jobs_worker.php', 'System', '2 minutes'),
        array('Cleanup Inactive Sessions', 'cleanup_inactive_sessions.php', 'Session', '1 hour'),
    );
    foreach ($seedJobs as $sj) {
        $n = mysqli_real_escape_string($connect, $sj[0]);
        $fp = mysqli_real_escape_string($connect, $sj[1]);
        $cat = mysqli_real_escape_string($connect, $sj[2]);
        $dur = mysqli_real_escape_string($connect, $sj[3]);
        @mysqli_query(
            $connect,
            "INSERT INTO cron_jobs (name, file_path, category, duration, enabled)
             SELECT '{$n}', '{$fp}', '{$cat}', '{$dur}', 1 FROM DUAL
             WHERE NOT EXISTS (SELECT 1 FROM cron_jobs WHERE file_path = '{$fp}' LIMIT 1)"
        );
    }
    $result = method_exists($database, 'querySoft')
        ? $database->querySoft($sql)
        : @mysqli_query($connect, $sql);
}

// Paths for central cron
$project_root_display = str_replace('\\', '/', realpath(dirname(__DIR__)));
$cron_web = $url . 'cron.php';

if ($result) {
while ($row = $database->fetchArray($result)) {
    $category = $row['category'];
    
    // Auto-fix: Move Google Drive Token Refresh to Google Drive category if it's in Command
    if ($row['file_path'] == 'vendor/google/gdrive/cron_refresh_tokens.php' && $category == 'Command') {
        $updateSql = "UPDATE cron_jobs SET category = 'Google Drive' WHERE id = " . (int)$row['id'];
        if (method_exists($database, 'querySoft')) {
            $database->querySoft($updateSql);
        } else {
            $database->query($updateSql);
        }
        $category = 'Google Drive';
        $row['category'] = 'Google Drive';
    }

    $fp = isset($row['file_path']) ? (string)$row['file_path'] : '';
    $isAttendanceCron = ($category === 'Attendance' || strpos($fp, 'attendance_auto_absent.php') !== false);
    if ($isAttendanceCron && !$attendanceModuleEnabled) {
        continue;
    }
    $isEcommerceCron = ($category === 'Ecommerce' || strpos($fp, 'woo_') !== false);
    if ($isEcommerceCron && !$ecommerceModuleEnabledForCron) {
        continue;
    }
    if ($cronJobIsFreeHidden($row)) {
        continue;
    }

    $cronJobs[] = $row;
    
    if (isset($jobsByCategory[$category])) {
        $jobsByCategory[$category][] = $row;
    }
}
} // end if ($result)

// Overall server cron health: recent last_run means crontab is hitting cron.php
$cronServerLatestRun = null;
$cronServerActiveJobs = 0;
$cronServerEnabledJobs = 0;
foreach ($cronJobs as $job) {
    if (empty($job['enabled'])) {
        continue;
    }
    $cronServerEnabledJobs++;
    if (!empty($job['last_run']) && ($cronServerLatestRun === null || $job['last_run'] > $cronServerLatestRun)) {
        $cronServerLatestRun = $job['last_run'];
    }
    if (isCronActive($job['last_run'], $job['duration'])) {
        $cronServerActiveJobs++;
    }
}
// Recommended setup is every 5 minutes; allow ~3 missed ticks before marking inactive
$cronServerFreshSeconds = 15 * 60;
$cronServerElapsed = cron_seconds_since_last_run($cronServerLatestRun);
$cronServerIsActive = ($cronServerElapsed !== null && $cronServerElapsed <= $cronServerFreshSeconds)
    || ($cronServerActiveJobs > 0);

$centralCronRunSteps = [];
if ($settingsForCron && !empty($settingsForCron->module_marketing)) {
    $centralCronRunSteps[] = ['type' => 'marketing', 'name' => 'Marketing: Send queue'];
}
foreach ($cronJobs as $job) {
    if (empty($job['enabled'])) {
        continue;
    }
    $fp = isset($job['file_path']) ? (string) $job['file_path'] : '';
    $cat = isset($job['category']) ? (string) $job['category'] : '';
    if (strpos($fp, 'marketing_send_queue.php') !== false) {
        continue;
    }
    $isAttendanceCron = ($cat === 'Attendance' || strpos($fp, 'attendance_auto_absent.php') !== false);
    if ($isAttendanceCron && !$attendanceModuleEnabled) {
        continue;
    }
    $isEcommerceCron = ($cat === 'Ecommerce' || strpos($fp, 'woo_') !== false);
    if ($isEcommerceCron && !$ecommerceModuleEnabledForCron) {
        continue;
    }
    if ($cronJobIsFreeHidden($job)) {
        continue;
    }
    $centralCronRunSteps[] = [
        'type' => 'job',
        'id' => (int) $job['id'],
        'name' => (string) $job['name'],
    ];
}

/** @var array{type:string,msg:string}|null assets/js/toast.js reads window.__toastFlash */
$toast_flash = null;
if (isset($_GET['message'])) {
    if ($_GET['message'] == 'success') {
        $toast_flash = array('type' => 'success', 'msg' => 'Settings updated successfully!');
    } elseif ($_GET['message'] == 'error') {
        $errorMsg = isset($_GET['error_msg']) ? urldecode($_GET['error_msg']) : 'An error occurred';
        $toast_flash = array('type' => 'error', 'msg' => $errorMsg);
    }
}
?>

<div class="page-container">
    <div class="container-fluid">
        <div class="row row-eq-height">
            <?php include("../templates/sidebar.php"); ?>
            <div class="page-content" style="padding-bottom:0;">
                <?php include('../templates/top-header.php'); ?>
                <div class="row system-wrap h-100">
                    <?php include("../templates/system-nav.php"); ?>
                    <!-- Main Content -->
                    <div class="col-md-9 ss-right h-100">
                        <h2 class="page-title mb-4">Cron Management</h2>

                        <!-- Modern Tabs Navigation -->
                        <ul class="nav nav-tabs" id="cronTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="command-tab" data-bs-toggle="tab" data-bs-target="#command" type="button" role="tab" aria-controls="command" aria-selected="true">Command</button>
                            </li>
                            <?php if (!$isFreeEditionCron): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="google-drive-tab" data-bs-toggle="tab" data-bs-target="#google-drive" type="button" role="tab" aria-controls="google-drive" aria-selected="false">Google Sync</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="invoice-tab" data-bs-toggle="tab" data-bs-target="#invoice" type="button" role="tab" aria-controls="invoice" aria-selected="false">Invoice</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="messages-tab" data-bs-toggle="tab" data-bs-target="#messages" type="button" role="tab" aria-controls="messages" aria-selected="false">Messages</button>
                            </li>
                            <?php endif; ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tasks-tab" data-bs-toggle="tab" data-bs-target="#tasks" type="button" role="tab" aria-controls="tasks" aria-selected="false">Tasks</button>
                            </li>
                            <?php if (!$isFreeEditionCron): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email" type="button" role="tab" aria-controls="email" aria-selected="false">Email</button>
                            </li>
                            <?php endif; ?>
                            <?php if ($attendanceModuleEnabled): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance-cron" type="button" role="tab" aria-controls="attendance-cron" aria-selected="false">Attendance</button>
                            </li>
                            <?php endif; ?>
                            <?php if ($ecommerceModuleEnabledForCron): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="ecommerce-cron-tab" data-bs-toggle="tab" data-bs-target="#ecommerce-cron" type="button" role="tab" aria-controls="ecommerce-cron" aria-selected="false"><?php echo isset($lang['ecommerce']) ? htmlspecialchars($lang['ecommerce']) : 'Ecommerce'; ?></button>
                            </li>
                            <?php endif; ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="session-tab" data-bs-toggle="tab" data-bs-target="#session" type="button" role="tab" aria-controls="session" aria-selected="false">Session</button>
                            </li>
                        </ul>

                        <div class="tab-content" id="cronTabsContent">
                            <!-- Command Tab -->
                            <div class="tab-pane fade show active" id="command" role="tabpanel" aria-labelledby="command-tab">
                                <div class="mt-4">
                                    <!-- Run Main Cron Button -->
                                    <div class="card mb-4" id="central-cron-run-card" style="background-color: #f8f9fa; border-left: 4px solid #007bff;">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                                <div class="flex-grow-1">
                                                    <h5 class="mb-1"><?php echo htmlspecialchars($lang['Run all cron jobs'] ?? 'Run all cron jobs', ENT_QUOTES, 'UTF-8'); ?></h5>
                                                    <p class="mb-0 text-muted" style="font-size: 14px;">
                                                        Execute the main cron file which will run all enabled cron jobs: <code><?php echo $url; ?>cron.php</code>
                                                    </p>
                                                </div>
                                                <div class="ms-auto flex-shrink-0">
                                                    <button type="button" class="bigbutton ss-btn alert-savestn run-main-cron-btn">
                                                        Run Manually
                                                    </button>
                                                </div>
                                            </div>
                                            <div id="central-cron-progress" class="central-cron-progress mt-3" hidden>
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <small id="central-cron-progress-label" class="text-muted">Preparing…</small>
                                                    <small id="central-cron-progress-count" class="fw-semibold">0 / 0</small>
                                                </div>
                                                <div class="progress central-cron-progress-track">
                                                    <div id="central-cron-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                                <ul id="central-cron-progress-steps" class="central-cron-progress-steps list-unstyled small mb-0 mt-2"></ul>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if ($cronServerIsActive): ?>
                                    <div class="card mb-4 cron-server-status-card cron-server-status-card--active" style="border-left: 4px solid #22c55e; background: color-mix(in srgb, #22c55e 10%, var(--card-body-color, #f8f9fa));">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                                <div class="flex-grow-1">
                                                    <h5 class="mb-1" style="color: #4ade80; font-weight: 600;">
                                                        <span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:#22c55e;box-shadow:0 0 8px rgba(34,197,94,.7);margin-right:8px;vertical-align:middle;"></span>
                                                        Cron Active &amp; Running
                                                    </h5>
                                                    <p class="mb-0 text-muted" style="font-size: 14px;">
                                                        Server cron is set up and hitting <code><?php echo htmlspecialchars($cron_web); ?></code>.
                                                        Last activity: <strong><?php echo htmlspecialchars(getTimeSinceLastRun($cronServerLatestRun)); ?></strong>
                                                        <?php if ($cronServerEnabledJobs > 0): ?>
                                                            · <?php echo (int) $cronServerActiveJobs; ?> / <?php echo (int) $cronServerEnabledJobs; ?> enabled jobs in schedule window
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                                <div class="ms-auto flex-shrink-0" style="margin-top: 12px;">
                                                    <button type="button" class="bigbutton ss-btn" style="background:#22c55e !important; border-color:#16a34a !important; color:#fff !important; box-shadow:0 0 14px rgba(34,197,94,.45); pointer-events:none; cursor:default;">
                                                        Active
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <div class="card mb-4 cron-server-status-card cron-server-status-card--inactive" style="border-left: 4px solid #ef4444; background: color-mix(in srgb, #ef4444 12%, var(--card-body-color, #f8f9fa));">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                                <div class="flex-grow-1">
                                                    <h5 class="mb-1" style="color: #f87171; font-weight: 600;">
                                                        <span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:#ef4444;box-shadow:0 0 8px rgba(239,68,68,.75);margin-right:8px;vertical-align:middle;"></span>
                                                        Need to Configure Cron
                                                    </h5>
                                                    <p class="mb-2 text-muted" style="font-size: 14px;">
                                                        Server cron is not running (or has not run recently).
                                                        Last activity: <strong><?php echo htmlspecialchars(getTimeSinceLastRun($cronServerLatestRun)); ?></strong>.
                                                    </p>
                                                    <p class="mb-0" style="font-size: 13px; color: var(--body-font-color, inherit); opacity: .9;">
                                                        Without cron, <strong style="color:#f87171;">all automation tasks will not run</strong>
                                                        (payment reminders, task reminders, chat email batches, session cleanup, Google Drive sync, ecommerce queues, and more).
                                                        Set up a crontab for <code><?php echo htmlspecialchars($cron_web); ?></code> (every 5 minutes) using the setup examples below.
                                                    </p>
                                                </div>
                                                <div class="ms-auto flex-shrink-0" style="margin-top: 12px;">
                                                    <button type="button" class="bigbutton ss-btn" style="background:#ef4444 !important; border-color:#dc2626 !important; color:#fff !important; box-shadow:0 0 16px rgba(239,68,68,.55); pointer-events:none; cursor:default;">
                                                        Not configured
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <div class="license-details" style="margin-top: 20px; padding: 15px; border-left: 4px solid #17a2b8; background-color: #d1ecf1; border-radius: 4px;">
                                        <h5 style="margin-top: 0; margin-bottom: 10px;">
                                            Central Cron Setup
                                        </h5>
                                        <p style="margin-bottom: 10px; font-size: 14px;">
                                            <strong>Cron Path</strong>
                                            <code style="background-color: #fff; padding: 4px 8px; border-radius: 3px; font-size: 13px; word-break: break-all; display: inline-block; margin-left: 5px;">
                                                <?php echo $cron_web; ?>
                                            </code>
                                        </p>
                                        <p style="margin-bottom: 10px; font-size: 13px;">
                                            <strong>PHP Command Example:</strong><br>
                                            <code style="background-color: #fff; padding: 4px 8px; border-radius: 3px; font-size: 12px; display: block; margin-top: 5px;">
                                                /usr/local/bin/php <?php echo $project_root_display; ?>/cron.php
                                            </code>
                                        </p>
                                        <p style="margin-bottom: 0; font-size: 13px;">
                                            <strong>Linux Cron Example:</strong><br>
                                            <code style="background-color: #fff; padding: 4px 8px; border-radius: 3px; font-size: 12px; display: block; margin-top: 5px;">
                                                */5 * * * * /usr/local/bin/php <?php echo $project_root_display; ?>/cron.php &gt; /dev/null 2&gt;&amp;1
                                            </code>
                                        </p>
                                    </div>
                                        <?php if (!empty($jobsByCategory['Command'])): ?>
                                        <?php foreach ($jobsByCategory['Command'] as $job): 
                                            $isActive = isCronActive($job['last_run'], $job['duration']);
                                            $statusClass = $isActive ? 'success' : 'danger';
                                            $statusIcon = $isActive ? '✓' : '✗';
                                            $statusText = $isActive ? 'Active' : 'Not Set Up';
                                        ?>
                                            <div class="card mb-3">
                                                <div class="card-body">
                                                    <div class="row align-items-center">
                                                        <div class="col-md-4">
                                                            <h5 class="mb-1"><?php echo htmlspecialchars($job['name']); ?></h5>
                                                            <small class="text-muted"><?php echo htmlspecialchars($job['file_path']); ?></small>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label small">Duration</label>
                                                            <input type="text" class="form-control form-control-sm duration-input" 
                                                                   data-cron-id="<?php echo $job['id']; ?>" 
                                                                   value="<?php echo htmlspecialchars($job['duration']); ?>" 
                                                                   placeholder="e.g., 5 minutes, 1 hour">
                                                            <small class="text-muted">Format: "5 minutes", "1 hour", "1 day"</small>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <label class="form-label small">Enabled</label>
                                                            <div class="checkbox-wrapper-6">
                                                                <input class="tgl tgl-light enabled-toggle" id="cron_enabled_<?php echo $job['id']; ?>" type="checkbox" 
                                                                       data-cron-id="<?php echo $job['id']; ?>" 
                                                                       <?php echo $job['enabled'] ? 'checked' : ''; ?>>
                                                                <label class="tgl-btn" for="cron_enabled_<?php echo $job['id']; ?>"></label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <button class="bigbutton ss-btn alert-savestn manual-run-btn" 
                                                                    data-cron-id="<?php echo $job['id']; ?>"
                                                                    data-cron-name="<?php echo htmlspecialchars($job['name']); ?>">
                                                                Run Now
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div class="row mt-2 pt-2" style="border-top: 1px solid var(--border-color);">
                                                        <div class="col-md-12">
                                                            <small class="text-muted">
                                                                Last run: <?php echo getTimeSinceLastRun($job['last_run']); ?> | 
                                                                Status: <?php echo htmlspecialchars($job['last_run_status'] ?? 'N/A'); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if (!$isFreeEditionCron): ?>
                            <!-- Google Drive Tab -->
                            <div class="tab-pane fade" id="google-drive" role="tabpanel" aria-labelledby="google-drive-tab">
                                <div class="mt-4">
                                    <?php if (!empty($jobsByCategory['Google Drive'])): ?>
                                        <?php foreach ($jobsByCategory['Google Drive'] as $job): 
                                            $isActive = isCronActive($job['last_run'], $job['duration']);
                                            $statusClass = $isActive ? 'success' : 'danger';
                                            $statusIcon = $isActive ? '✓' : '✗';
                                            $statusText = $isActive ? 'Active' : 'Not Set Up';
                                        ?>
                                            <div class="card mb-3">
                                                <div class="card-body">
                                                    <div class="row align-items-center">
                                                        <div class="col-md-4">
                                                            <h5 class="mb-1"><?php echo htmlspecialchars($job['name']); ?></h5>
                                                            <small class="text-muted"><?php echo htmlspecialchars($job['file_path']); ?></small>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label small">Duration</label>
                                                            <input type="text" class="form-control form-control-sm duration-input" 
                                                                   data-cron-id="<?php echo $job['id']; ?>" 
                                                                   value="<?php echo htmlspecialchars($job['duration']); ?>" 
                                                                   placeholder="e.g., 5 minutes, 1 hour">
                                                            <small class="text-muted">Format: "5 minutes", "1 hour", "1 day"</small>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <label class="form-label small">Enabled</label>
                                                            <div class="checkbox-wrapper-6">
                                                                <input class="tgl tgl-light enabled-toggle" id="cron_enabled_<?php echo $job['id']; ?>" type="checkbox" 
                                                                       data-cron-id="<?php echo $job['id']; ?>" 
                                                                       <?php echo $job['enabled'] ? 'checked' : ''; ?>>
                                                                <label class="tgl-btn" for="cron_enabled_<?php echo $job['id']; ?>"></label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <button class="bigbutton ss-btn alert-savestn manual-run-btn" 
                                                                    data-cron-id="<?php echo $job['id']; ?>"
                                                                    data-cron-name="<?php echo htmlspecialchars($job['name']); ?>">
                                                                Run Now
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div class="row mt-2 pt-2" style="border-top: 1px solid var(--border-color);">
                                                        <div class="col-md-12">
                                                            <small class="text-muted">
                                                                Last run: <?php echo getTimeSinceLastRun($job['last_run']); ?> | 
                                                                Status: <?php echo htmlspecialchars($job['last_run_status'] ?? 'N/A'); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Invoice Tab -->
                            <div class="tab-pane fade" id="invoice" role="tabpanel" aria-labelledby="invoice-tab">
                                <div class="mt-4">
                                    <?php if (!empty($jobsByCategory['Invoice'])): ?>
                                        <?php foreach ($jobsByCategory['Invoice'] as $job): 
                                            $isActive = isCronActive($job['last_run'], $job['duration']);
                                            $statusClass = $isActive ? 'success' : 'danger';
                                            $statusIcon = $isActive ? '✓' : '✗';
                                            $statusText = $isActive ? 'Active' : 'Not Set Up';
                                        ?>
                                            <div class="card mb-3">
                                                <div class="card-body">
                                                    <div class="row align-items-center">
                                                        <div class="col-md-4">
                                                            <h5 class="mb-1"><?php echo htmlspecialchars($job['name']); ?></h5>
                                                            <small class="text-muted"><?php echo htmlspecialchars($job['file_path']); ?></small>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label small">Duration</label>
                                                            <input type="text" class="form-control form-control-sm duration-input" 
                                                                   data-cron-id="<?php echo $job['id']; ?>" 
                                                                   value="<?php echo htmlspecialchars($job['duration']); ?>" 
                                                                   placeholder="e.g., 5 minutes, 1 hour">
                                                            <small class="text-muted">Format: "5 minutes", "1 hour", "1 day"</small>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <label class="form-label small">Enabled</label>
                                                            <div class="checkbox-wrapper-6">
                                                                <input class="tgl tgl-light enabled-toggle" id="cron_enabled_<?php echo $job['id']; ?>" type="checkbox" 
                                                                       data-cron-id="<?php echo $job['id']; ?>" 
                                                                       <?php echo $job['enabled'] ? 'checked' : ''; ?>>
                                                                <label class="tgl-btn" for="cron_enabled_<?php echo $job['id']; ?>"></label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <button class="bigbutton ss-btn alert-savestn manual-run-btn" 
                                                                    data-cron-id="<?php echo $job['id']; ?>"
                                                                    data-cron-name="<?php echo htmlspecialchars($job['name']); ?>">
                                                                Run Now
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div class="row mt-2 pt-2" style="border-top: 1px solid var(--border-color);">
                                                        <div class="col-md-12">
                                                            <small class="text-muted">
                                                                Last run: <?php echo getTimeSinceLastRun($job['last_run']); ?> | 
                                                                Status: <?php echo htmlspecialchars($job['last_run_status'] ?? 'N/A'); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Messages Tab -->
                            <div class="tab-pane fade" id="messages" role="tabpanel" aria-labelledby="messages-tab">
                                <div class="mt-4">
                                    <?php if (!empty($jobsByCategory['Messages'])): ?>
                                        <?php foreach ($jobsByCategory['Messages'] as $job): 
                                            $isActive = isCronActive($job['last_run'], $job['duration']);
                                            $statusClass = $isActive ? 'success' : 'danger';
                                            $statusIcon = $isActive ? '✓' : '✗';
                                            $statusText = $isActive ? 'Active' : 'Not Set Up';
                                        ?>
                                            <div class="card mb-3">
                                                <div class="card-body">
                                                    <div class="row align-items-center">
                                                        <div class="col-md-4">
                                                            <h5 class="mb-1"><?php echo htmlspecialchars($job['name']); ?></h5>
                                                            <small class="text-muted"><?php echo htmlspecialchars($job['file_path']); ?></small>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label small">Duration</label>
                                                            <input type="text" class="form-control form-control-sm duration-input" 
                                                                   data-cron-id="<?php echo $job['id']; ?>" 
                                                                   value="<?php echo htmlspecialchars($job['duration']); ?>" 
                                                                   placeholder="e.g., 5 minutes, 1 hour">
                                                            <small class="text-muted">Format: "5 minutes", "1 hour", "1 day"</small>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <label class="form-label small">Enabled</label>
                                                            <div class="checkbox-wrapper-6">
                                                                <input class="tgl tgl-light enabled-toggle" id="cron_enabled_<?php echo $job['id']; ?>" type="checkbox" 
                                                                       data-cron-id="<?php echo $job['id']; ?>" 
                                                                       <?php echo $job['enabled'] ? 'checked' : ''; ?>>
                                                                <label class="tgl-btn" for="cron_enabled_<?php echo $job['id']; ?>"></label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <button class="bigbutton ss-btn alert-savestn manual-run-btn" 
                                                                    data-cron-id="<?php echo $job['id']; ?>"
                                                                    data-cron-name="<?php echo htmlspecialchars($job['name']); ?>">
                                                                Run Now
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div class="row mt-2 pt-2" style="border-top: 1px solid var(--border-color);">
                                                        <div class="col-md-12">
                                                            <small class="text-muted">
                                                                Last run: <?php echo getTimeSinceLastRun($job['last_run']); ?> | 
                                                                Status: <?php echo htmlspecialchars($job['last_run_status'] ?? 'N/A'); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Tasks Tab -->
                            <div class="tab-pane fade" id="tasks" role="tabpanel" aria-labelledby="tasks-tab">
                                <div class="mt-4">
                                    <?php if (!empty($jobsByCategory['Tasks'])): ?>
                                        <?php foreach ($jobsByCategory['Tasks'] as $job): 
                                            $isActive = isCronActive($job['last_run'], $job['duration']);
                                            $statusClass = $isActive ? 'success' : 'danger';
                                            $statusIcon = $isActive ? '✓' : '✗';
                                            $statusText = $isActive ? 'Active' : 'Not Set Up';
                                        ?>
                                            <div class="card mb-3">
                                                <div class="card-body">
                                                    <div class="row align-items-center">
                                                        <div class="col-md-4">
                                                            <h5 class="mb-1"><?php echo htmlspecialchars($job['name']); ?></h5>
                                                            <small class="text-muted"><?php echo htmlspecialchars($job['file_path']); ?></small>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label small">Duration</label>
                                                            <input type="text" class="form-control form-control-sm duration-input" 
                                                                   data-cron-id="<?php echo $job['id']; ?>" 
                                                                   value="<?php echo htmlspecialchars($job['duration']); ?>" 
                                                                   placeholder="e.g., 5 minutes, 1 hour">
                                                            <small class="text-muted">Format: "5 minutes", "1 hour", "1 day"</small>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <label class="form-label small">Enabled</label>
                                                            <div class="checkbox-wrapper-6">
                                                                <input class="tgl tgl-light enabled-toggle" id="cron_enabled_<?php echo $job['id']; ?>" type="checkbox" 
                                                                       data-cron-id="<?php echo $job['id']; ?>" 
                                                                       <?php echo $job['enabled'] ? 'checked' : ''; ?>>
                                                                <label class="tgl-btn" for="cron_enabled_<?php echo $job['id']; ?>"></label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <button class="bigbutton ss-btn alert-savestn manual-run-btn" 
                                                                    data-cron-id="<?php echo $job['id']; ?>"
                                                                    data-cron-name="<?php echo htmlspecialchars($job['name']); ?>">
                                                                Run Now
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div class="row mt-2 pt-2" style="border-top: 1px solid var(--border-color);">
                                                        <div class="col-md-12">
                                                            <small class="text-muted">
                                                                Last run: <?php echo getTimeSinceLastRun($job['last_run']); ?> | 
                                                                Status: <?php echo htmlspecialchars($job['last_run_status'] ?? 'N/A'); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if (!$isFreeEditionCron): ?>
                            <!-- Email Tab -->
                            <div class="tab-pane fade" id="email" role="tabpanel" aria-labelledby="email-tab">
                                <div class="mt-4">
                                    <?php if (!empty($jobsByCategory['Email'])): ?>
                                        <?php 
                                        // Get email report frequency from settings
                                        require_once(__DIR__ . '/../includes/settings.php');
                                        $settings = settings::findById(1);
                                        $emailReportFrequency = $settings->email_report_frequency ?? '1hour';
                                        
                                        $emailReportJobExists = false;
                                        foreach ($jobsByCategory['Email'] as $checkJob) {
                                            if (($checkJob['file_path'] == 'cron/email_report.php' || basename($checkJob['file_path']) == 'email_report.php') || 
                                                stripos($checkJob['name'], 'Email Report') !== false) {
                                                $emailReportJobExists = true;
                                                break;
                                            }
                                        }
                                        
                                        foreach ($jobsByCategory['Email'] as $job): 
                                            $isActive = isCronActive($job['last_run'], $job['duration']);
                                            $statusClass = $isActive ? 'success' : 'danger';
                                            $statusIcon = $isActive ? '✓' : '✗';
                                            $statusText = $isActive ? 'Active' : 'Not Set Up';
                                            
                                            // Check if this is Email Report cron job
                                            // Check both file_path and name to be safe
                                            $isEmailReport = (
                                                (isset($job['file_path']) && (
                                                    $job['file_path'] == 'cron/email_report.php' || 
                                                    basename($job['file_path']) == 'email_report.php'
                                                )) || 
                                                (isset($job['name']) && stripos($job['name'], 'Email Report') !== false)
                                            );
                                        ?>
                                            <div class="card mb-3">
                                                <div class="card-body">
                                                    <div class="row align-items-center">
                                                        <div class="col-md-4">
                                                            <h5 class="mb-1"><?php echo htmlspecialchars($job['name']); ?></h5>
                                                            <small class="text-muted"><?php echo htmlspecialchars($job['file_path']); ?></small>
                                                        </div>
                                                        <?php if ($isEmailReport): ?>
                                                            <!-- Email Report: Show Frequency dropdown instead of Duration -->
                                                            <div class="col-md-3">
                                                                <label class="form-label small">Report Frequency</label>
                                                                <select class="form-control form-control-sm email-report-frequency-select" 
                                                                        data-cron-id="<?php echo $job['id']; ?>">
                                                                    <option value="30mins" <?php echo ($emailReportFrequency == '30mins') ? 'selected' : ''; ?>>Every 30 minutes</option>
                                                                    <option value="1hour" <?php echo ($emailReportFrequency == '1hour') ? 'selected' : ''; ?>>Every 1 hour</option>
                                                                    <option value="6hours" <?php echo ($emailReportFrequency == '6hours') ? 'selected' : ''; ?>>Every 6 hours</option>
                                                                    <option value="12hours" <?php echo ($emailReportFrequency == '12hours') ? 'selected' : ''; ?>>Every 12 hours</option>
                                                                    <option value="24hours" <?php echo ($emailReportFrequency == '24hours') ? 'selected' : ''; ?>>Every 24 hours</option>
                                                                    <option value="weekly" <?php echo ($emailReportFrequency == 'weekly') ? 'selected' : ''; ?>>Every week</option>
                                                                    <option value="monthly" <?php echo ($emailReportFrequency == 'monthly') ? 'selected' : ''; ?>>Every month</option>
                                                                </select>
                                                                <small class="text-muted">Note: Reports are sent based on this frequency, not cron duration</small>
                                                            </div>
                                                        <?php else: ?>
                                                            <!-- Other Email jobs: Show Duration input -->
                                                            <div class="col-md-3">
                                                                <label class="form-label small">Duration</label>
                                                                <input type="text" class="form-control form-control-sm duration-input" 
                                                                       data-cron-id="<?php echo $job['id']; ?>" 
                                                                       value="<?php echo htmlspecialchars($job['duration']); ?>" 
                                                                       placeholder="e.g., 5 minutes, 1 hour">
                                                                <small class="text-muted">Format: "5 minutes", "1 hour", "1 day"</small>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="col-md-2">
                                                            <label class="form-label small">Enabled</label>
                                                            <div class="checkbox-wrapper-6">
                                                                <input class="tgl tgl-light enabled-toggle" id="cron_enabled_<?php echo $job['id']; ?>" type="checkbox" 
                                                                       data-cron-id="<?php echo $job['id']; ?>" 
                                                                       <?php echo $job['enabled'] ? 'checked' : ''; ?>>
                                                                <label class="tgl-btn" for="cron_enabled_<?php echo $job['id']; ?>"></label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <button class="bigbutton ss-btn alert-savestn manual-run-btn" 
                                                                    data-cron-id="<?php echo $job['id']; ?>"
                                                                    data-cron-name="<?php echo htmlspecialchars($job['name']); ?>">
                                                                Run Now
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div class="row mt-2 pt-2" style="border-top: 1px solid var(--border-color);">
                                                        <div class="col-md-12">
                                                            <small class="text-muted">
                                                                Last run: <?php echo getTimeSinceLastRun($job['last_run']); ?> | 
                                                                Status: <?php echo htmlspecialchars($job['last_run_status'] ?? 'N/A'); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        
                                        <?php if (!$emailReportJobExists): ?>
                                            <div class="alert alert-warning mt-3">
                                                <strong>Email Report Cron Job Not Found</strong><br>
                                                The Email Report cron job is not registered. Please run <a href="<?php echo $url; ?>admin/register-email-sync-cron.php" target="_blank">register-email-sync-cron.php</a> to register it.
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <p>No email cron jobs found. On a fresh install they are registered automatically. If this is an older installation, run <a href="<?php echo $url; ?>admin/register-email-sync-cron.php" target="_blank">register-email-sync-cron.php</a> to add Email Sync and Email Report, and <a href="<?php echo $url; ?>admin/register-email-tracking-cron.php" target="_blank">register-email-tracking-cron.php</a> to add Email Tracking Update.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($attendanceModuleEnabled): ?>
                            <!-- Attendance Tab -->
                            <div class="tab-pane fade" id="attendance-cron" role="tabpanel" aria-labelledby="attendance-tab">
                                <div class="mt-4">
                                    <?php if (!empty($jobsByCategory['Attendance'])): ?>
                                        <?php foreach ($jobsByCategory['Attendance'] as $job):
                                            $isActive = isCronActive($job['last_run'], $job['duration']);
                                            $statusClass = $isActive ? 'success' : 'danger';
                                            $statusIcon = $isActive ? '✓' : '✗';
                                            $statusText = $isActive ? 'Active' : 'Not Set Up';
                                        ?>
                                            <div class="card mb-3">
                                                <div class="card-body">
                                                    <div class="row align-items-center">
                                                        <div class="col-md-4">
                                                            <h5 class="mb-1"><?php echo htmlspecialchars($job['name']); ?></h5>
                                                            <small class="text-muted"><?php echo htmlspecialchars($job['file_path']); ?></small>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label small">Duration</label>
                                                            <input type="text" class="form-control form-control-sm duration-input"
                                                                   data-cron-id="<?php echo $job['id']; ?>"
                                                                   value="<?php echo htmlspecialchars($job['duration']); ?>"
                                                                   placeholder="e.g., 5 minutes, 1 hour">
                                                            <small class="text-muted">Format: "5 minutes", "1 hour", "1 day"</small>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <label class="form-label small">Enabled</label>
                                                            <div class="checkbox-wrapper-6">
                                                                <input class="tgl tgl-light enabled-toggle" id="cron_enabled_<?php echo $job['id']; ?>" type="checkbox"
                                                                       data-cron-id="<?php echo $job['id']; ?>"
                                                                       <?php echo $job['enabled'] ? 'checked' : ''; ?>>
                                                                <label class="tgl-btn" for="cron_enabled_<?php echo $job['id']; ?>"></label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <button class="bigbutton ss-btn alert-savestn manual-run-btn"
                                                                    data-cron-id="<?php echo $job['id']; ?>"
                                                                    data-cron-name="<?php echo htmlspecialchars($job['name']); ?>">
                                                                Run Now
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div class="row mt-2 pt-2" style="border-top: 1px solid var(--border-color);">
                                                        <div class="col-md-12">
                                                            <small class="text-muted">
                                                                Last run: <?php echo getTimeSinceLastRun($job['last_run']); ?> |
                                                                Status: <?php echo htmlspecialchars($job['last_run_status'] ?? 'N/A'); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <p class="mb-0">No attendance cron jobs are registered. Run the migration <code>includes/migrations/3.22.sql</code> (or insert the Attendance Auto Absent row into <code>cron_jobs</code>).</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($ecommerceModuleEnabledForCron): ?>
                            <div class="tab-pane fade" id="ecommerce-cron" role="tabpanel" aria-labelledby="ecommerce-cron-tab">
                                <div class="mt-4">
                                    <?php if (!empty($jobsByCategory['Ecommerce'])): ?>
                                        <?php foreach ($jobsByCategory['Ecommerce'] as $job):
                                            $isActive = isCronActive($job['last_run'], $job['duration']);
                                        ?>
                                            <div class="card mb-3">
                                                <div class="card-body">
                                                    <div class="row align-items-center">
                                                        <div class="col-md-4">
                                                            <h5 class="mb-1"><?php echo htmlspecialchars($job['name']); ?></h5>
                                                            <small class="text-muted"><?php echo htmlspecialchars($job['file_path']); ?></small>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label small">Duration</label>
                                                            <input type="text" class="form-control form-control-sm duration-input"
                                                                   data-cron-id="<?php echo $job['id']; ?>"
                                                                   value="<?php echo htmlspecialchars($job['duration']); ?>"
                                                                   placeholder="e.g., 5 minutes, 1 hour">
                                                            <small class="text-muted">Format: "5 minutes", "1 hour", "1 day"</small>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <label class="form-label small">Enabled</label>
                                                            <div class="checkbox-wrapper-6">
                                                                <input class="tgl tgl-light enabled-toggle" id="cron_enabled_<?php echo $job['id']; ?>" type="checkbox"
                                                                       data-cron-id="<?php echo $job['id']; ?>"
                                                                       <?php echo $job['enabled'] ? 'checked' : ''; ?>>
                                                                <label class="tgl-btn" for="cron_enabled_<?php echo $job['id']; ?>"></label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <button class="bigbutton ss-btn alert-savestn manual-run-btn"
                                                                    data-cron-id="<?php echo $job['id']; ?>"
                                                                    data-cron-name="<?php echo htmlspecialchars($job['name']); ?>">
                                                                Run Now
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div class="row mt-2 pt-2" style="border-top: 1px solid var(--border-color);">
                                                        <div class="col-md-12">
                                                            <small class="text-muted">
                                                                Last run: <?php echo getTimeSinceLastRun($job['last_run']); ?> |
                                                                Status: <?php echo htmlspecialchars($job['last_run_status'] ?? 'N/A'); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <p class="mb-0"><?php echo isset($lang['ecommerce_cron_none']) ? htmlspecialchars($lang['ecommerce_cron_none']) : 'No Ecommerce cron jobs found.'; ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Session Tab -->
                            <div class="tab-pane fade" id="session" role="tabpanel" aria-labelledby="session-tab">
                                <div class="mt-4">
                                    <?php if (!empty($jobsByCategory['Session'])): ?>
                                        <?php foreach ($jobsByCategory['Session'] as $job): 
                                            $isActive = isCronActive($job['last_run'], $job['duration']);
                                            $statusClass = $isActive ? 'success' : 'danger';
                                            $statusIcon = $isActive ? '✓' : '✗';
                                            $statusText = $isActive ? 'Active' : 'Not Set Up';
                                        ?>
                                            <div class="card mb-3">
                                                <div class="card-body">
                                                    <div class="row align-items-center">
                                                        <div class="col-md-4">
                                                            <h5 class="mb-1"><?php echo htmlspecialchars($job['name']); ?></h5>
                                                            <small class="text-muted"><?php echo htmlspecialchars($job['file_path']); ?></small>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label small">Duration</label>
                                                            <input type="text" class="form-control form-control-sm duration-input" 
                                                                   data-cron-id="<?php echo $job['id']; ?>" 
                                                                   value="<?php echo htmlspecialchars($job['duration']); ?>" 
                                                                   placeholder="e.g., 5 minutes, 1 hour">
                                                            <small class="text-muted">Format: "5 minutes", "1 hour", "1 day"</small>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <label class="form-label small">Enabled</label>
                                                            <div class="checkbox-wrapper-6">
                                                                <input class="tgl tgl-light enabled-toggle" id="cron_enabled_<?php echo $job['id']; ?>" type="checkbox" 
                                                                       data-cron-id="<?php echo $job['id']; ?>" 
                                                                       <?php echo $job['enabled'] ? 'checked' : ''; ?>>
                                                                <label class="tgl-btn" for="cron_enabled_<?php echo $job['id']; ?>"></label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <button class="bigbutton ss-btn alert-savestn manual-run-btn" 
                                                                    data-cron-id="<?php echo $job['id']; ?>"
                                                                    data-cron-name="<?php echo htmlspecialchars($job['name']); ?>">
                                                                Run Now
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div class="row mt-2 pt-2" style="border-top: 1px solid var(--border-color);">
                                                        <div class="col-md-12">
                                                            <small class="text-muted">
                                                                Last run: <?php echo getTimeSinceLastRun($job['last_run']); ?> | 
                                                                Status: <?php echo htmlspecialchars($job['last_run_status'] ?? 'N/A'); ?>
                                                            </small>
                                                        </div>
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

<style>
.central-cron-progress-track {
    height: 8px;
    background-color: #e9ecef;
    border-radius: 4px;
}
.central-cron-progress-steps li {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 4px 0;
    border-bottom: 1px solid #eee;
}
.central-cron-progress-steps li:last-child {
    border-bottom: none;
}
.central-cron-step-icon {
    flex-shrink: 0;
    width: 16px;
    height: 16px;
    margin-top: 1px;
}
.central-cron-step-body {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}
.central-cron-step-name {
    line-height: 1.3;
    word-break: break-word;
}
.central-cron-step-error {
    color: #c0392b;
    font-size: 12px;
    line-height: 1.3;
    word-break: break-word;
}
.central-cron-progress-steps li.is-success .central-cron-step-name {
    color: #0d7a3e;
}
.central-cron-progress-steps li.is-failed .central-cron-step-name {
    color: #c0392b;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cronManualRunUrl = '<?php echo $url; ?>ajax/cron-manual-run.php';
    const cronMarketingRunUrl = '<?php echo $url; ?>ajax/cron-marketing-run.php';
    const cronAjaxHeaders = { 'X-Requested-With': 'XMLHttpRequest' };
    const centralCronRunSteps = <?php echo json_encode($centralCronRunSteps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    function parseCronFetchJson(response, contextLabel) {
        const label = contextLabel || 'Request';
        return response.text().then(function (text) {
            const raw = text || '';
            var data = null;
            if (raw) {
                try {
                    data = JSON.parse(raw);
                } catch (e) {
                    throw new Error(label + ': invalid server response');
                }
            }

            if (!response.ok) {
                var errMsg = (data && (data.error || data.message)) ? (data.error || data.message) : ('HTTP ' + response.status);
                throw new Error(errMsg);
            }
            if (!data) {
                throw new Error(label + ': empty response');
            }
            return data;
        });
    }

    async function cronFetchJson(url, options, contextLabel) {
        const opts = Object.assign({}, options || {});
        const stepTimeout = parseInt(opts.cronStepTimeoutMs, 10) || 0;
        delete opts.cronStepTimeoutMs;
        let controller = null;
        let timeoutId = null;
        if (stepTimeout > 0 && typeof AbortController !== 'undefined') {
            controller = new AbortController();
            opts.signal = controller.signal;
            timeoutId = setTimeout(function () {
                controller.abort();
            }, stepTimeout);
        }
        try {
            const response = await fetch(url, opts);
            return await parseCronFetchJson(response, contextLabel || url);
        } catch (err) {
            if (controller && controller.signal && controller.signal.aborted) {
                throw new Error((contextLabel || 'Cron step') + ' timed out after ' + Math.round(stepTimeout / 1000) + 's');
            }
            throw err;
        } finally {
            if (timeoutId) {
                clearTimeout(timeoutId);
            }
        }
    }

    function centralCronStepIconSvg(status) {
        if (status === 'success') {
            return '<svg class="central-cron-step-icon" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><circle cx="8" cy="8" r="7" stroke="#0d7a3e" stroke-width="1.5"/><path d="M5 8l2 2 4-4" stroke="#0d7a3e" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        }
        if (status === 'failed') {
            return '<svg class="central-cron-step-icon" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><circle cx="8" cy="8" r="7" stroke="#c0392b" stroke-width="1.5"/><path d="M5.5 5.5l5 5M10.5 5.5l-5 5" stroke="#c0392b" stroke-width="1.5" stroke-linecap="round"/></svg>';
        }
        if (status === 'running') {
            return '<svg class="central-cron-step-icon" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><circle cx="8" cy="8" r="6" stroke="#007bff" stroke-width="1.5" stroke-dasharray="28" stroke-dashoffset="8"><animateTransform attributeName="transform" type="rotate" from="0 8 8" to="360 8 8" dur="0.8s" repeatCount="indefinite"/></circle></svg>';
        }
        return '<svg class="central-cron-step-icon" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><circle cx="8" cy="8" r="6" stroke="#adb5bd" stroke-width="1.5"/></svg>';
    }

    function resetCentralCronProgress() {
        const wrap = document.getElementById('central-cron-progress');
        const bar = document.getElementById('central-cron-progress-bar');
        const label = document.getElementById('central-cron-progress-label');
        const count = document.getElementById('central-cron-progress-count');
        const stepsList = document.getElementById('central-cron-progress-steps');
        if (!wrap || !bar || !label || !count || !stepsList) {
            return;
        }
        wrap.hidden = false;
        bar.style.width = '0%';
        bar.setAttribute('aria-valuenow', '0');
        bar.classList.add('progress-bar-striped', 'progress-bar-animated');
        label.textContent = 'Preparing…';
        count.textContent = '0 / 0';
        stepsList.innerHTML = '';
    }

    function setCentralCronProgress(completed, total, stepName) {
        const bar = document.getElementById('central-cron-progress-bar');
        const label = document.getElementById('central-cron-progress-label');
        const count = document.getElementById('central-cron-progress-count');
        if (!bar || !label || !count) {
            return;
        }
        const done = Math.max(0, Math.min(completed, total));
        const pct = total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0;
        bar.style.width = (done >= total ? '100' : Math.max(pct, 8)) + '%';
        bar.setAttribute('aria-valuenow', String(done >= total ? 100 : pct));
        bar.classList.add('progress-bar-striped', 'progress-bar-animated');
        if (stepName) {
            label.textContent = 'Running: ' + stepName;
        } else if (done >= total) {
            label.textContent = 'Finishing…';
        } else {
            label.textContent = 'Running…';
        }
        count.textContent = done + ' / ' + total;
    }

    function setCentralCronProgressActive(stepIndex, total, stepName) {
        const bar = document.getElementById('central-cron-progress-bar');
        const label = document.getElementById('central-cron-progress-label');
        const count = document.getElementById('central-cron-progress-count');
        if (!bar || !label || !count) {
            return;
        }
        const active = Math.max(1, stepIndex + 1);
        const pct = total > 0 ? Math.min(95, Math.round((stepIndex / total) * 100)) : 8;
        bar.style.width = Math.max(pct, 8) + '%';
        bar.setAttribute('aria-valuenow', String(pct));
        bar.classList.add('progress-bar-striped', 'progress-bar-animated');
        label.textContent = stepName ? ('Running: ' + stepName) : 'Running…';
        count.textContent = active + ' / ' + total;
    }

    function appendCentralCronStepResult(name, status, errorMsg) {
        const stepsList = document.getElementById('central-cron-progress-steps');
        if (!stepsList) {
            return;
        }
        const li = document.createElement('li');
        li.className = status === 'success' ? 'is-success' : 'is-failed';
        let bodyHtml = '<span class="central-cron-step-name">' + String(name).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</span>';
        if (status !== 'success' && errorMsg) {
            bodyHtml += '<span class="central-cron-step-error">' + String(errorMsg).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</span>';
        }
        li.innerHTML = centralCronStepIconSvg(status) + '<span class="central-cron-step-body">' + bodyHtml + '</span>';
        stepsList.appendChild(li);
    }

    function finishCentralCronProgress(total, failedCount) {
        const bar = document.getElementById('central-cron-progress-bar');
        const label = document.getElementById('central-cron-progress-label');
        const count = document.getElementById('central-cron-progress-count');
        if (!bar || !label || !count) {
            return;
        }
        bar.style.width = '100%';
        bar.setAttribute('aria-valuenow', '100');
        bar.classList.remove('progress-bar-striped', 'progress-bar-animated');
        label.textContent = failedCount > 0 ? 'Completed with errors' : 'Completed';
        count.textContent = total + ' / ' + total;
    }

    // Duration input change handler with debounce
    let durationTimeouts = {};
    document.querySelectorAll('.duration-input').forEach(function(input) {
        input.addEventListener('blur', function() {
            const cronId = this.getAttribute('data-cron-id');
            const duration = this.value.trim();
            
            if (duration === '') {
                alert('Duration cannot be empty');
                return;
            }
            
            // Update via AJAX
            const formData = new FormData();
            formData.append('cron_id', cronId);
            formData.append('duration', duration);
            if (window.tasksessionAppendCsrf) { window.tasksessionAppendCsrf(formData); }
            
            fetch('<?php echo $url; ?>ajax/cron-update-settings.php', {
                method: 'POST',
                body: formData,
                headers: window.tasksessionCsrfHeaders ? window.tasksessionCsrfHeaders() : {}
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    showMessage('Duration updated successfully', 'success');
                    // Reload page to update status indicators
                    setTimeout(() => location.reload(), 1000);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('An error occurred while updating duration');
            });
        });
    });
    
    // Email Report Frequency dropdown handler
    document.querySelectorAll('.email-report-frequency-select').forEach(function(select) {
        select.addEventListener('change', function() {
            const frequency = this.value;
            const originalValue = this.getAttribute('data-original-value') || this.options[this.selectedIndex].text;
            
            // Disable dropdown while saving
            this.disabled = true;
            
            // Update frequency in settings table via AJAX
            const formData = new FormData();
            formData.append('email_report_frequency', frequency);
            formData.append('update_frequency_only', '1');
            
            fetch('<?php echo $url; ?>admin/email-setting.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                // Check if response is JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    return response.text().then(text => {
                        console.error('Non-JSON response:', text.substring(0, 200));
                        throw new Error('Server returned non-JSON response');
                    });
                }
                return response.json();
            })
            .then(data => {
                this.disabled = false;
                if (data.success) {
                    showMessage('Report frequency updated successfully', 'success');
                    // Update original value
                    this.setAttribute('data-original-value', frequency);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    alert('Error: ' + (data.message || 'Failed to update frequency'));
                    // Revert to original value
                    this.value = originalValue;
                }
            })
            .catch(error => {
                this.disabled = false;
                console.error('Error:', error);
                alert('An error occurred while updating frequency: ' + error.message);
                // Revert to original value
                this.value = originalValue;
            });
        });
    });
    
    // Enabled toggle handler
    document.querySelectorAll('.enabled-toggle').forEach(function(toggle) {
        toggle.addEventListener('change', function() {
            const cronId = this.getAttribute('data-cron-id');
            const enabled = this.checked ? 1 : 0;
            
            const formData = new FormData();
            formData.append('cron_id', cronId);
            formData.append('enabled', enabled);
            if (window.tasksessionAppendCsrf) { window.tasksessionAppendCsrf(formData); }
            
            fetch('<?php echo $url; ?>ajax/cron-update-settings.php', {
                method: 'POST',
                body: formData,
                headers: window.tasksessionCsrfHeaders ? window.tasksessionCsrfHeaders() : {}
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('Settings updated successfully', 'success');
                } else {
                    alert('Error: ' + data.message);
                    // Revert toggle
                    this.checked = !this.checked;
                }
            })
            .catch(error => {
                alert('An error occurred while updating settings');
                // Revert toggle
                this.checked = !this.checked;
            });
        });
    });
    
    function showCronToast(message, type) {
        var toastType = 'info';
        if (type === 'success') {
            toastType = 'success';
        } else if (type === 'danger') {
            toastType = 'error';
        }
        if (typeof window.showToast === 'function') {
            window.showToast(message, toastType, String(message).length > 100 ? 6000 : 3500);
            return;
        }
        showMessage(message, type);
    }

    function showCentralCronSummary(data, jobs, total, failedCount) {
        const marketingJob = jobs.find(function (j) { return j.name === 'Marketing: Send queue'; });
        let message = (data && data.message) ? data.message : 'Cron execution completed';
        if (failedCount > 0) {
            const failedJobs = jobs.filter(function (j) { return j.status === 'failed'; });
            message += '. Failed jobs: ' + failedJobs.map(function (j) {
                const err = j.error ? ' — ' + j.error : '';
                return j.name + err;
            }).join('; ');
        }
        if (marketingJob && marketingJob.status === 'success' && (marketingJob.sent > 0 || marketingJob.processed > 0)) {
            message += ' Marketing: ' + (marketingJob.sent || 0) + ' email(s) sent.';
        }

        if (!data || data.success === false) {
            if (failedCount > 0 && failedCount < total) {
                showCronToast(message, 'warning');
            } else {
                showCronToast('Cron execution failed: ' + ((data && (data.error || data.message)) || message), 'danger');
            }
        } else if (failedCount > 0) {
            showCronToast(message, 'warning');
        } else {
            showCronToast(message, 'success');
        }
    }

    async function runMarketingStep() {
        return cronFetchJson(cronMarketingRunUrl, { method: 'POST', headers: cronAjaxHeaders }, 'Marketing queue');
    }

    async function runOneCronJobStep(step) {
        const formData = new FormData();
        formData.append('cron_id', step.id);
        formData.append('central_batch', '1');
        return cronFetchJson(cronManualRunUrl, {
            method: 'POST',
            body: formData,
            headers: cronAjaxHeaders,
            cronStepTimeoutMs: 150000,
        }, step.name || ('job #' + step.id));
    }

    async function runCentralCronStep(step) {
        if (step.type === 'marketing') {
            return runMarketingStep();
        }
        return runOneCronJobStep(step);
    }

    async function runCentralCronWithProgress(runBtn, originalText) {
        resetCentralCronProgress();

        const steps = Array.isArray(centralCronRunSteps) ? centralCronRunSteps : [];
        const total = steps.length > 0 ? steps.length : 1;
        const allJobs = [];
        let failedCount = 0;

        if (total === 0) {
            showCronToast('No enabled cron jobs to run', 'warning');
            runBtn.disabled = false;
            runBtn.textContent = originalText;
            return;
        }

        try {
            for (let i = 0; i < steps.length; i++) {
                const step = steps[i];
                setCentralCronProgressActive(i, total, step.name);

                let data;
                let ok = false;
                let errText = '';
                try {
                    data = await runCentralCronStep(step);
                    ok = !!data.success;
                    errText = data.error || data.message || '';
                } catch (stepError) {
                    ok = false;
                    errText = stepError.message || 'Request failed';
                }

                appendCentralCronStepResult(step.name, ok ? 'success' : 'failed', ok ? '' : errText);
                allJobs.push({
                    name: step.name,
                    status: ok ? 'success' : 'failed',
                    error: ok ? null : errText,
                    sent: (data && data.sent) ? data.sent : 0,
                    processed: (data && data.processed) ? data.processed : 0,
                });
                if (!ok) {
                    failedCount++;
                }
                setCentralCronProgress(i + 1, total, '');
            }

            finishCentralCronProgress(total, failedCount);
            showCentralCronSummary({ success: failedCount === 0 }, allJobs, total, failedCount);

            runBtn.disabled = false;
            runBtn.textContent = originalText;
            if (failedCount === 0) {
                setTimeout(function () { location.reload(); }, 3000);
            }
        } catch (error) {
            finishCentralCronProgress(total, Math.max(1, failedCount));
            appendCentralCronStepResult('Run all cron jobs', 'failed', error.message || 'Request failed');
            runBtn.disabled = false;
            runBtn.textContent = originalText;
            showCronToast(error.message || 'An error occurred while running the cron job', 'danger');
        }
    }

    // Run main cron button handler
    document.querySelectorAll('.run-main-cron-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!confirm('Are you sure you want to run all enabled cron jobs now? This will execute the main cron.php file.')) {
                return;
            }

            const originalText = this.textContent;
            const runBtn = this;
            runBtn.disabled = true;
            runBtn.textContent = 'Running...';
            runCentralCronWithProgress(runBtn, originalText);
        });
    });
    
    // Manual run button handler
    document.querySelectorAll('.manual-run-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const cronId = this.getAttribute('data-cron-id');
            const cronName = this.getAttribute('data-cron-name');
            
            if (!confirm('Are you sure you want to run "' + cronName + '" now?')) {
                return;
            }
            
            // Disable button and show loading
            const originalText = this.textContent;
            this.disabled = true;
            this.textContent = 'Running...';
            
            const formData = new FormData();
            formData.append('cron_id', cronId);

            cronFetchJson(cronManualRunUrl, { method: 'POST', body: formData, headers: cronAjaxHeaders }, cronName || ('job #' + cronId))
            .then(data => {
                this.disabled = false;
                this.textContent = originalText;

                if (data.success) {
                    if (data.no_job_available || (data.message && data.message.includes('No job available'))) {
                        showMessage('No job available at this time', 'info');
                    } else {
                        var detail = (data.output && String(data.output).trim()) ? String(data.output).trim() : '';
                        var successMsg = detail !== ''
                            ? detail + ' (' + data.execution_time + 's)'
                            : 'Cron job executed successfully in ' + data.execution_time + 's';
                        showMessage(successMsg, 'success');
                    }
                    setTimeout(() => location.reload(), 1500);
                } else {
                    const errorMsg = data.error || data.message || 'Unknown error occurred';
                    showMessage('Cron job execution failed: ' + errorMsg, 'danger');
                }
            })
            .catch(error => {
                this.disabled = false;
                this.textContent = originalText;
                showMessage('An error occurred while running the cron job: ' + error.message, 'danger');
            });
        });
    });
    
    // Tab persistence
    const lastTab = localStorage.getItem('cronManagementActiveTab');
    if (lastTab) {
        const tabTrigger = document.querySelector('[data-bs-target="' + lastTab + '"]');
        if (tabTrigger) {
            const tab = new bootstrap.Tab(tabTrigger);
            tab.show();
        }
    }
    
    const tabLinks = document.querySelectorAll('#cronTabs button[data-bs-toggle="tab"]');
    tabLinks.forEach(function(tabLink) {
        tabLink.addEventListener('shown.bs.tab', function(event) {
            localStorage.setItem('cronManagementActiveTab', event.target.getAttribute('data-bs-target'));
        });
    });
    
    // Helper function to show messages
    function showMessage(message, type) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-' + type + ' alert-dismissible fade show';
        alertDiv.setAttribute('role', 'alert');
        alertDiv.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        
        const pageTitle = document.querySelector('.page-title');
        if (pageTitle && pageTitle.nextElementSibling) {
            pageTitle.nextElementSibling.insertAdjacentElement('afterend', alertDiv);
        } else {
            pageTitle.insertAdjacentElement('afterend', alertDiv);
        }
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
});
</script>
<script>window.__toastFlash=<?php echo json_encode($toast_flash, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
<script src="<?php echo htmlspecialchars(rtrim((string) $url, '/') . '/assets/js/toast.js', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php include("../templates/main-footer.php"); ?>

