<?php
/**
 * Central Cron Execution File
 * 
 * This file should be set up in cPanel cron area to run all configured cron jobs.
 * 
 * Example cron setup in cPanel:
 * * * * * * /usr/bin/php /path/to/your/project/cron.php
 * 
 * Or for specific intervals:
 * Every 5 minutes: *\/5 * * * * /usr/bin/php /path/to/your/project/cron.php
 * Every hour: 0 * * * * /usr/bin/php /path/to/your/project/cron.php
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (!defined('CRM_LIGHTWEIGHT_INIT')) {
    define('CRM_LIGHTWEIGHT_INIT', true);
}

// Get the project root directory
$current_dir = dirname(__FILE__);
$project_root = $current_dir;

// Logging control (set true to suppress all cron file logging in production)
$disableCronLogs = true;

// Set error log path
$log_dir = $project_root . '/logs';
if (!$disableCronLogs) {
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    ini_set('error_log', $log_dir . '/central_cron.log');
}

// Error handler for fatal errors
function handleFatalError() {
    global $log_dir;
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $message = "Fatal Error: {$error['message']} in {$error['file']} on line {$error['line']}";
        if (isset($log_dir)) {
            @file_put_contents($log_dir . '/central_cron.log', date('Y-m-d H:i:s') . " - " . $message . PHP_EOL, FILE_APPEND);
        }
        
        if (php_sapi_name() !== 'cli') {
            header('Content-Type: text/html; charset=utf-8');
            echo "<!DOCTYPE html><html><head><title>Central Cron Error</title></head><body>";
            echo "<h2>Error</h2>";
            echo "<p style='color: red;'>" . htmlspecialchars($message) . "</p>";
            echo "<p>Check the log file for more details: logs/central_cron.log</p>";
            echo "</body></html>";
        }
    }
}
register_shutdown_function('handleFatalError');

try {
    if (!file_exists($project_root . '/includes/cron_helper.php')) {
        throw new Exception("cron_helper.php not found at: " . $project_root . '/includes/cron_helper.php');
    }
    require_once($project_root . '/includes/cron_helper.php');
} catch (Throwable $e) {
    $errorMsg = "Failed to load cron_helper.php: " . $e->getMessage();
    @file_put_contents($log_dir . '/central_cron.log', date('Y-m-d H:i:s') . " - " . $errorMsg . PHP_EOL, FILE_APPEND);
    exit(1);
}

if (function_exists('cron_apply_shared_host_limits')) {
    cron_apply_shared_host_limits();
}
if (php_sapi_name() !== 'cli') {
    @ignore_user_abort(true);
}

$centralCronLock = cron_acquire_central_lock($project_root);
if ($centralCronLock === false) {
    if (php_sapi_name() !== 'cli') {
        $isAjaxEarly = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        if ($isAjaxEarly) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'message' => 'Skipped: previous central cron is still running',
                'skipped' => true,
            ]);
        }
    }
    exit(0);
}
register_shutdown_function(function () use ($centralCronLock) {
    if (function_exists('cron_release_db_lock')) {
        cron_release_db_lock();
    }
    if (function_exists('cron_release_central_lock')) {
        cron_release_central_lock($centralCronLock);
    }
});

try {
    if (!file_exists($project_root . '/includes/lib-initialize.php')) {
        throw new Exception("lib-initialize.php not found at: " . $project_root . '/includes/lib-initialize.php');
    }
    require_once($project_root . '/includes/lib-initialize.php');
} catch (Throwable $e) {
    $errorMsg = "Failed to load lib-initialize.php: " . $e->getMessage();
    @file_put_contents($log_dir . '/central_cron.log', date('Y-m-d H:i:s') . " - " . $errorMsg . PHP_EOL, FILE_APPEND);
    
    if (php_sapi_name() !== 'cli') {
        header('Content-Type: text/html; charset=utf-8');
        echo "<!DOCTYPE html><html><head><title>Central Cron Error</title></head><body>";
        echo "<h2>Error</h2>";
        echo "<p style='color: red;'>" . htmlspecialchars($errorMsg) . "</p>";
        echo "<p>Check the log file for more details: logs/central_cron.log</p>";
        echo "</body></html>";
    }
    exit(1);
}

if (function_exists('cron_release_session_lock')) {
    cron_release_session_lock();
}
if (function_exists('cron_apply_shared_host_limits')) {
    cron_apply_shared_host_limits();
}
if (function_exists('cron_acquire_db_lock') && cron_acquire_db_lock() === false) {
    if (php_sapi_name() !== 'cli') {
        $isAjaxEarly = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        if ($isAjaxEarly) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'message' => 'Skipped: previous central cron is still running',
                'skipped' => true,
            ]);
        }
    }
    exit(0);
}

// Log function
function logCronMessage($message) {
    global $log_dir, $disableCronLogs;
    if ($disableCronLogs) {
        return;
    }
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    
    // Write to log file
    @file_put_contents($log_dir . '/central_cron.log', $logMessage, FILE_APPEND | LOCK_EX);
    
    // Also output to console if running from command line
    if (php_sapi_name() === 'cli') {
        echo $logMessage;
    }
}

/**
 * Process scheduled marketing campaigns and the send queue.
 * Runs early in central cron so heavy jobs (IMAP sync, etc.) cannot block scheduled sends.
 *
 * @return array{executed:bool,status:string,execution_time:float,processed:int,sent:int,failed:int,error:?string}
 */
function runCentralMarketingQueue($project_root) {
    $result = [
        'executed' => false,
        'status' => 'skipped',
        'execution_time' => 0.0,
        'processed' => 0,
        'sent' => 0,
        'failed' => 0,
        'error' => null,
    ];

    $gatePath = $project_root . '/includes/marketing_module_gate.php';
    $marketingFunctionsPath = $project_root . '/marketing/includes/marketing-functions.php';
    if (!is_file($gatePath) || !is_file($marketingFunctionsPath)) {
        return $result;
    }

    require_once $gatePath;
    if (!comon_marketing_module_enabled()) {
        logCronMessage('  — Marketing send queue skipped (module not enabled for this context — check addon license / CLI domain)');
        return $result;
    }

    logCronMessage('Processing: Marketing send queue (central integration)');
    $startTime = microtime(true);
    $result['executed'] = true;

    try {
        require_once $marketingFunctionsPath;
        $scheduledResult = marketing_process_scheduled_campaigns();
        $result['scheduled_processed'] = (int) ($scheduledResult['processed'] ?? 0);
        $result['scheduled_queued'] = (int) ($scheduledResult['queued'] ?? 0);
        $result['scheduled_failed'] = (int) ($scheduledResult['failed'] ?? 0);
        $batch = marketing_process_batch();
        $result['processed'] = (int) ($batch['processed'] ?? 0);
        $result['sent'] = (int) ($batch['sent'] ?? 0);
        $result['failed'] = (int) ($batch['failed'] ?? 0);
        $result['execution_time'] = round(microtime(true) - $startTime, 2);
        $result['status'] = 'success';
        if ($result['scheduled_processed'] > 0) {
            logCronMessage("  ✓ Marketing schedule: processed={$result['scheduled_processed']} queued={$result['scheduled_queued']} failed={$result['scheduled_failed']}");
        }
        if ($result['processed'] > 0) {
            logCronMessage("  ✓ Marketing batch: processed={$result['processed']} sent={$result['sent']} failed={$result['failed']} in {$result['execution_time']}s");
        } elseif ($result['scheduled_processed'] === 0) {
            logCronMessage('  — Marketing queue idle (no pending campaign emails)');
        }
    } catch (Throwable $e) {
        $result['execution_time'] = round(microtime(true) - $startTime, 2);
        $result['status'] = 'failed';
        $result['error'] = $e->getMessage();
        logCronMessage('  ✗ Marketing send queue failed: ' . $result['error']);
    }

    return $result;
}

// Check if this is an AJAX request
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Register shutdown function for AJAX requests to ensure JSON is always sent
if ($isAjax && php_sapi_name() !== 'cli') {
    $GLOBALS['_cron_script_started'] = true;
    register_shutdown_function(function() {
        // Only run if we haven't sent JSON yet
        if (!isset($GLOBALS['_cron_json_sent'])) {
            $lastError = error_get_last();
            $hasFatalError = $lastError && in_array($lastError['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR]);
            
            // Clean any output buffers
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            // Send error JSON
            header('Content-Type: application/json; charset=utf-8', true);
            
            if ($hasFatalError) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Fatal error occurred',
                    'error' => $lastError['message'] . ' in ' . $lastError['file'] . ' on line ' . $lastError['line'],
                    'log_file' => 'logs/central_cron.log'
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                // Script exited unexpectedly - likely a cron file called die() or exit()
                // Check the log to see which job was being processed
                $lastLogEntry = '';
                $logFile = $GLOBALS['log_dir'] . '/central_cron.log';
                if (isset($logFile) && is_file($logFile)) {
                    $fp = @fopen($logFile, 'rb');
                    if ($fp) {
                        $size = @filesize($logFile);
                        $read = ($size !== false) ? min((int) $size, 4096) : 4096;
                        if ($read > 0 && $size > $read) {
                            @fseek($fp, -$read, SEEK_END);
                        }
                        $chunk = (string) @stream_get_contents($fp);
                        @fclose($fp);
                        $lines = preg_split("/\r\n|\n|\r/", $chunk);
                        $lines = array_values(array_filter($lines, 'strlen'));
                        $lastLogEntry = implode("\n", array_slice($lines, -5));
                    }
                }
                
                echo json_encode([
                    'success' => false,
                    'message' => 'Script exited unexpectedly',
                    'error' => 'A cron file called die() or exit(), which terminated the entire script. This prevents other cron jobs from running. Check which job was processing in the log file.',
                    'log_file' => 'logs/central_cron.log',
                    'last_log_entries' => $lastLogEntry,
                    'note' => 'Cron files should not call die() or exit() when run via the central cron. They should return instead.',
                    'debug_info' => [
                        'headers_sent' => headers_sent(),
                        'last_error' => $lastError ? [
                            'type' => $lastError['type'],
                            'message' => $lastError['message'],
                            'file' => $lastError['file'],
                            'line' => $lastError['line']
                        ] : null
                    ]
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
    });
}

// For non-AJAX web access, suppress output (production)
// AJAX responses will be JSON; CLI remains silent.

logCronMessage("=========================================");
logCronMessage("Starting central cron execution");
logCronMessage("=========================================");
logCronMessage("AJAX Request: " . ($isAjax ? 'YES' : 'NO'));
logCronMessage("PHP SAPI: " . php_sapi_name());

try {
    // Get all enabled cron jobs from database
    global $database;
    logCronMessage("Database object available: " . (isset($database) ? 'YES' : 'NO'));
    
    // Check if cron_jobs table exists using direct mysqli to avoid die() in confirmQuery
    if (isset($database->connection)) {
        $tableCheck = @mysqli_query($database->connection, "SHOW TABLES LIKE 'cron_jobs'");
        if (!$tableCheck || mysqli_num_rows($tableCheck) == 0) {
            logCronMessage("ERROR: cron_jobs table does not exist. Please run the migration script first: admin/migrate-cron-jobs-table.php");
            if ($isAjax && php_sapi_name() !== 'cli') {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'message' => 'cron_jobs table does not exist',
                    'error' => 'Please run the migration script first: admin/migrate-cron-jobs-table.php'
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $GLOBALS['_cron_json_sent'] = true;
            }
            exit(1);
        }
        mysqli_free_result($tableCheck);
    }
    
    // Now query the table (this will work since table exists)
    // Use direct mysqli to avoid die() in confirmQuery
    $sql = "SELECT * FROM cron_jobs WHERE enabled = 1 ORDER BY category, name";
    
    // Use direct mysqli_query to avoid die() calls from database class
    $jobs = [];
    if (isset($database->connection)) {
        $result = @mysqli_query($database->connection, $sql);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $jobs[] = $row;
            }
            mysqli_free_result($result);
        } else {
            $error = mysqli_error($database->connection);
            logCronMessage("ERROR: Database query failed: " . $error);
            if (php_sapi_name() !== 'cli') {
                if ($isAjax) {
                    while (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    header('Content-Type: application/json; charset=utf-8', true);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Database query failed',
                        'error' => 'Query execution failed: ' . $error,
                        'log_file' => 'logs/central_cron.log'
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $GLOBALS['_cron_json_sent'] = true;
                    exit;
                } else {
                    echo "</pre><p style='color: red;'><strong>ERROR:</strong> Database query failed: " . htmlspecialchars($error) . "</p>";
                    echo "<p>Check the log file for more details: logs/central_cron.log</p></body></html>";
                    exit(1);
                }
            }
        }
    } else {
        logCronMessage("ERROR: Database connection not available");
        if (php_sapi_name() !== 'cli') {
            if ($isAjax) {
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                header('Content-Type: application/json; charset=utf-8', true);
                echo json_encode([
                    'success' => false,
                    'message' => 'Database connection not available',
                    'error' => 'Unable to connect to database',
                    'log_file' => 'logs/central_cron.log'
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $GLOBALS['_cron_json_sent'] = true;
                exit;
            } else {
                echo "</pre><p style='color: red;'><strong>ERROR:</strong> Database connection not available.</p>";
                echo "<p>Check the log file for more details: logs/central_cron.log</p></body></html>";
                exit(1);
            }
        }
    }

    if (!function_exists('comon_ecommerce_module_enabled')) {
        require_once __DIR__ . '/includes/addon_registry.php';
    }
    if (!function_exists('tasksession_is_free_edition') && is_readable(__DIR__ . '/includes/free_edition.php')) {
        require_once __DIR__ . '/includes/free_edition.php';
    }
    if (!function_exists('installer_free_is_premium_cron_path') && is_readable(__DIR__ . '/install/free_edition_filter.php')) {
        require_once __DIR__ . '/install/free_edition_filter.php';
    }
    $moduleEcommerceCentral = comon_ecommerce_module_enabled();
    $isFreeEditionCentralCron = function_exists('tasksession_is_free_edition') && tasksession_is_free_edition();
    $freeHiddenCronCategories = array('Google Drive', 'Invoice', 'Messages', 'Email', 'Attendance');
    $jobs = array_values(array_filter($jobs, function ($j) use ($moduleEcommerceCentral, $isFreeEditionCentralCron, $freeHiddenCronCategories) {
        $cat = isset($j['category']) ? (string) $j['category'] : '';
        $fp = isset($j['file_path']) ? (string) $j['file_path'] : '';
        if (strpos($fp, 'marketing_send_queue.php') !== false) {
            return false;
        }
        $isEcom = ($cat === 'Ecommerce' || strpos($fp, 'woo_') !== false);
        if ($isEcom && !$moduleEcommerceCentral) {
            return false;
        }
        if ($isFreeEditionCentralCron) {
            if (in_array($cat, $freeHiddenCronCategories, true)) {
                return false;
            }
            if (function_exists('installer_free_is_premium_cron_path') && installer_free_is_premium_cron_path($fp)) {
                return false;
            }
        }
        return true;
    }));
    
    if (empty($jobs)) {
        logCronMessage("No enabled cron jobs found (marketing queue will still run).");
    } else {
        logCronMessage("Found " . count($jobs) . " enabled cron job(s) after module filters");
    }

    $totalExecuted = 0;
    $totalSuccess = 0;
    $totalFailed = 0;
    $executedJobs = []; // Track execution results for JSON response
    $cronTickStarted = microtime(true);

    // Run marketing before heavy cron jobs so scheduled campaigns are not blocked by IMAP/timeouts.
    require_once $project_root . '/includes/crm_mail_queue_helper.php';
    $crmMailQueueResult = crm_process_mail_queue($database, 8);
    if (!empty($crmMailQueueResult['processed'])) {
        logCronMessage("  ✓ CRM mail queue: processed={$crmMailQueueResult['processed']} sent={$crmMailQueueResult['sent']} failed={$crmMailQueueResult['failed']}");
    }

    $mvVaultCleanupPath = $project_root . '/cron/mv_vault_cleanup.php';
    if (is_file($mvVaultCleanupPath)) {
        include $mvVaultCleanupPath;
        logCronMessage('  ✓ Media Vault temp cleanup completed');
    }

    $marketingResult = runCentralMarketingQueue($project_root);
    if (!empty($marketingResult['executed'])) {
        $executedJobs[] = [
            'id' => 0,
            'name' => 'Marketing: Send queue',
            'category' => 'Central',
            'file_path' => 'marketing_process_batch (integrated)',
            'status' => $marketingResult['status'] === 'success' ? 'success' : 'failed',
            'execution_time' => $marketingResult['execution_time'],
            'error' => $marketingResult['error'],
            'processed' => $marketingResult['processed'],
            'sent' => $marketingResult['sent'],
            'failed' => $marketingResult['failed'],
        ];
        $totalExecuted++;
        if ($marketingResult['status'] === 'success') {
            $totalSuccess++;
        } else {
            $totalFailed++;
        }
    }
    
    // If logging is disabled, stub logMessage before cron files define it
    if ($disableCronLogs && !function_exists('logMessage')) {
        function logMessage($message) {
            return;
        }
    }

    $tickLimits = function_exists('cron_tick_limits')
        ? cron_tick_limits($isAjax)
        : ['max_jobs' => 6, 'time_budget' => 90];
    $jobsThisTick = (!empty($marketingResult['executed']) && ((int) ($marketingResult['processed'] ?? 0) > 0 || (int) ($marketingResult['scheduled_processed'] ?? 0) > 0))
        ? 1
        : 0;

    $isScheduledRun = (php_sapi_name() === 'cli') || empty($isAjax);
    $priorityCategories = function_exists('cron_priority_job_categories') ? cron_priority_job_categories() : ['Messages'];
    $priorityJobs = [];
    $regularJobs = [];
    foreach ($jobs as $job) {
        if (empty($job)) {
            continue;
        }
        $cat = isset($job['category']) ? (string) $job['category'] : '';
        if (in_array($cat, $priorityCategories, true)) {
            $priorityJobs[] = $job;
        } else {
            $regularJobs[] = $job;
        }
    }

    // Messages batch emails run every tick when due — not blocked by the per-tick job cap.
    $priorityBudgetSeconds = 30.0;
    if (!empty($priorityJobs) && function_exists('cron_central_execute_job_row')) {
        logCronMessage('Running priority jobs (' . count($priorityJobs) . ' Messages batch email job(s))…');
        foreach ($priorityJobs as $job) {
            if ((microtime(true) - $cronTickStarted) >= $priorityBudgetSeconds) {
                logCronMessage('  — Priority jobs deferred (priority time budget)');
                break;
            }
            $jobName = (string) ($job['name'] ?? '');
            $category = (string) ($job['category'] ?? '');
            if (function_exists('cron_central_job_is_due') && !cron_central_job_is_due($job, $isScheduledRun)) {
                logCronMessage("  — Skipped priority: {$jobName} (ran {$job['last_run']}, duration {$job['duration']})");
                continue;
            }
            logCronMessage("Processing priority: {$jobName} (Category: {$category})");
            $result = cron_central_execute_job_row($job, $project_root);
            if ($result['status'] === 'success') {
                logCronMessage('  ✓ Successfully executed in ' . $result['execution_time'] . 's');
                if (!empty($result['output'])) {
                    logCronMessage('  Output: ' . substr(trim($result['output']), 0, 200));
                }
                $totalSuccess++;
            } else {
                logCronMessage('  ✗ Failed to execute: ' . ($result['error'] ?? 'unknown'));
                $totalFailed++;
            }
            $executedJobs[] = $result;
            $totalExecuted++;
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
    }

    // Execute remaining cron jobs (cap per tick for shared-host safety).
    foreach ($regularJobs as $job) {
        if (empty($job)) {
            continue;
        }
        if ($jobsThisTick >= (int) $tickLimits['max_jobs']) {
            logCronMessage('  — Remaining jobs deferred (max ' . (int) $tickLimits['max_jobs'] . ' per tick)');
            break;
        }
        if ((microtime(true) - $cronTickStarted) >= (float) $tickLimits['time_budget']) {
            logCronMessage('  — Remaining jobs deferred (time budget)');
            break;
        }
        $jobId = $job['id'];
        $jobName = $job['name'];
        $filePath = $job['file_path'];
        $category = $job['category'];
        
        logCronMessage("Processing: {$jobName} (Category: {$category})");

        if (function_exists('cron_central_job_is_due') && !cron_central_job_is_due($job, $isScheduledRun)) {
            logCronMessage("  — Skipped (ran {$job['last_run']}, duration {$job['duration']})");
            continue;
        }

        if (function_exists('cron_central_execute_job_row')) {
            $result = cron_central_execute_job_row($job, $project_root);
            if ($result['status'] === 'success') {
                logCronMessage('  ✓ Successfully executed in ' . $result['execution_time'] . 's');
                if (!empty($result['output'])) {
                    logCronMessage('  Output: ' . substr(trim($result['output']), 0, 200));
                }
                $totalSuccess++;
            } else {
                logCronMessage('  ✗ Failed to execute: ' . ($result['error'] ?? 'unknown'));
                $totalFailed++;
            }
            $executedJobs[] = $result;
            $totalExecuted++;
            $jobsThisTick++;
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
            continue;
        }
        
        // Fallback: legacy inline execution (should not run when cron_helper is loaded).
        if (empty($filePath) || strpos($filePath, '..') !== false) {
            logCronMessage("  ERROR: Invalid file path (contains ..): {$filePath}");
            $totalFailed++;
            
            // Update database with failed status
            $updateSql = "UPDATE cron_jobs SET last_run = NOW(), last_run_status = 'failed' WHERE id = " . (int)$jobId;
            $database->query($updateSql);
            
            // Track execution result
            $executedJobs[] = [
                'id' => $jobId,
                'name' => $jobName,
                'category' => $category,
                'file_path' => $filePath,
                'status' => 'failed',
                'execution_time' => 0,
                'error' => 'Invalid file path (contains ..)'
            ];
            $totalExecuted++;
            continue;
        }
        
        // Construct full path
        $fullPath = $project_root . '/' . $filePath;
        
        // Normalize path separators for Windows
        $fullPath = str_replace('\\', '/', $fullPath);
        $resolvedPath = realpath($fullPath);
        
        // Check if file exists
        if ($resolvedPath === false || !file_exists($resolvedPath)) {
            logCronMessage("  ERROR: File not found: {$fullPath}");
            $totalFailed++;
            
            // Update database with failed status
            $updateSql = "UPDATE cron_jobs SET last_run = NOW(), last_run_status = 'failed' WHERE id = " . (int)$jobId;
            $database->query($updateSql);
            
            // Track execution result
            $executedJobs[] = [
                'id' => $jobId,
                'name' => $jobName,
                'category' => $category,
                'file_path' => $filePath,
                'status' => 'failed',
                'execution_time' => 0,
                'error' => 'File not found: ' . $fullPath
            ];
            $totalExecuted++;
            continue;
        }
        
        // Final security check - ensure file is within project root
        $baseRealPath = realpath($project_root);
        if ($baseRealPath === false || strpos($resolvedPath, $baseRealPath) !== 0) {
            logCronMessage("  ERROR: File path is outside project root: {$resolvedPath}");
            $totalFailed++;
            
            // Update database with failed status
            $updateSql = "UPDATE cron_jobs SET last_run = NOW(), last_run_status = 'failed' WHERE id = " . (int)$jobId;
            $database->query($updateSql);
            
            // Track execution result
            $executedJobs[] = [
                'id' => $jobId,
                'name' => $jobName,
                'category' => $category,
                'file_path' => $filePath,
                'status' => 'failed',
                'execution_time' => 0,
                'error' => 'File path is outside project root'
            ];
            $totalExecuted++;
            continue;
        }
        
        // Use resolved path for execution
        $fullPath = $resolvedPath;
        
        // Execute the cron file (same inline path as admin "Run all cron jobs" — reliable for Woo sync)
        $startTime = microtime(true);
        $executionSuccess = false;
        $output = '';
        $errorOutput = '';

        if (!defined('_CRON_CENTRAL_MODE')) {
            define('_CRON_CENTRAL_MODE', true);
        }
        if (function_exists('putenv')) {
            @putenv('CENTRAL_CRON=1');
        }

        if (function_exists('cron_manual_run_inline')) {
            $inlineResult = cron_manual_run_inline($fullPath);
            $output = (string) ($inlineResult['output'] ?? '');
            $executionSuccess = !empty($inlineResult['success']);
            $errorOutput = $executionSuccess ? '' : (string) ($inlineResult['error'] ?? 'Cron execution failed');
        } else {
            $isWindows = stripos(PHP_OS, 'WIN') === 0;
            $canExec = function_exists('exec') && !$isWindows;
            $phpBinary = function_exists('cron_resolve_php_binary')
                ? cron_resolve_php_binary()
                : ((defined('PHP_BINARY') && PHP_BINARY) ? PHP_BINARY : 'php');
            $bootstrap = $project_root . '/cron/central_cli_bootstrap.php';

            if ($canExec && is_file($bootstrap)) {
                $cmd = escapeshellarg($phpBinary) . ' '
                    . escapeshellarg($bootstrap) . ' '
                    . escapeshellarg($fullPath) . ' 2>&1';
                $outputLines = [];
                $returnVar = 0;
                exec($cmd, $outputLines, $returnVar);
                $output = implode("\n", $outputLines);
                $executionSuccess = ($returnVar === 0);
                if (!$executionSuccess) {
                    $errorOutput = "Cron file exited with code: {$returnVar}";
                    if ($output !== '') {
                        $errorOutput .= "\nOutput: " . substr($output, 0, 500);
                    }
                }
            } else {
                try {
                    ob_start();
                    $oldCwd = getcwd();
                    @chdir($project_root);
                    if (!empty($GLOBALS) && is_array($GLOBALS)) {
                        extract($GLOBALS, EXTR_SKIP);
                    }
                    @include($fullPath);
                    @chdir($oldCwd ?: $project_root);
                    $output = (string) ob_get_clean();
                    $executionSuccess = !function_exists('cron_manual_output_indicates_failure')
                        || !cron_manual_output_indicates_failure($output);
                    if (!$executionSuccess) {
                        $errorOutput = 'Cron script reported an error';
                    }
                } catch (Throwable $e) {
                    $errorOutput = $e->getMessage();
                    $executionSuccess = false;
                    if (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    if (isset($oldCwd)) {
                        @chdir($oldCwd);
                    }
                }
            }
        }
        
        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);
        
        // Update database
        $status = $executionSuccess ? 'success' : 'failed';
        $updateSql = "UPDATE cron_jobs SET last_run = NOW(), last_run_status = '{$status}' WHERE id = " . (int)$jobId;
        $database->query($updateSql);
        
        if ($executionSuccess) {
            logCronMessage("  ✓ Successfully executed in {$executionTime}s");
            if (!empty($output)) {
                logCronMessage("  Output: " . substr(trim($output), 0, 200));
            }
            $totalSuccess++;
        } else {
            logCronMessage("  ✗ Failed to execute: " . $errorOutput);
            $totalFailed++;
        }
        
        // Track execution result for JSON response
        $executedJobs[] = [
            'id' => $jobId,
            'name' => $jobName,
            'category' => $category,
            'file_path' => $filePath,
            'status' => $executionSuccess ? 'success' : 'failed',
            'execution_time' => $executionTime,
            'error' => $executionSuccess ? null : $errorOutput
        ];
        
        $totalExecuted++;
        $jobsThisTick++;
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    // Paid addon expiration (optional — also runs on page access without cron).
    $addonGatePath = $project_root . '/includes/addon_module_gate.php';
    if (is_file($addonGatePath)) {
        require_once $addonGatePath;
        comon_run_addon_expiration_checks();
    }

    $aiSched = $project_root . '/ai/includes/agent-scheduler.php';
    if (is_file($aiSched)) {
        require_once $aiSched;
        if (function_exists('ai_agent_scheduler_tick')) {
            @ai_agent_scheduler_tick(null, null, 5);
        }
    }

    $aiAutoReply = $project_root . '/ai/includes/agent-email-auto-reply.php';
    if (is_file($aiAutoReply)) {
        require_once $aiAutoReply;
        if (function_exists('ai_email_auto_reply_retry_pending')) {
            @ai_email_auto_reply_retry_pending($connect ?? null, 20);
        }
    }

    // Log summary
    logCronMessage("=========================================");
    logCronMessage("Central cron execution completed");
    logCronMessage("Total executed: {$totalExecuted}");
    logCronMessage("Successful: {$totalSuccess}");
    logCronMessage("Failed: {$totalFailed}");
    logCronMessage("=========================================");
    
    // Exit with appropriate code
    if ($isAjax && php_sapi_name() !== 'cli') {
        // Clean any output buffers that might have been created
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Return JSON for AJAX requests
        header('Content-Type: application/json; charset=utf-8', true);
        
        $jsonResponse = json_encode([
            'success' => $totalFailed == 0,
            'message' => "Executed {$totalExecuted} cron job(s). Successful: {$totalSuccess}, Failed: {$totalFailed}",
            'total_executed' => $totalExecuted,
            'total_success' => $totalSuccess,
            'total_failed' => $totalFailed,
            'jobs' => $executedJobs,
            'log_file' => 'logs/central_cron.log'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if ($jsonResponse === false) {
            // JSON encoding failed
            header('Content-Type: application/json; charset=utf-8', true);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to encode response',
                'error' => json_last_error_msg()
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            echo $jsonResponse;
        }
        $GLOBALS['_cron_json_sent'] = true; // Mark that JSON was sent
        exit; // Exit immediately after sending JSON
    } else {
        // CLI or non-AJAX web: no output; rely on exit code and logs
        exit($totalFailed > 0 ? 1 : 0);
    }
    
} catch (Throwable $e) {
    logCronMessage("FATAL ERROR: " . $e->getMessage());
    logCronMessage("Stack trace: " . $e->getTraceAsString());
    
    if (php_sapi_name() === 'cli') {
        exit(1);
    } else {
        // Clean any output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8', true);
            echo json_encode([
                'success' => false,
                'message' => 'Fatal error occurred',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'log_file' => 'logs/central_cron.log'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        } else {
            echo "</pre>";
            echo "<p style='color: red;'><strong>FATAL ERROR:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p>File: " . htmlspecialchars($e->getFile()) . " on line " . $e->getLine() . "</p>";
            echo "<p>Check the log file for more details: logs/central_cron.log</p>";
            echo "</body></html>";
        }
    }
}

