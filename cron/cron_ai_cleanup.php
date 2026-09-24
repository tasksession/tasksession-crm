<?php
/**
 * AI retention cleanup cron (Phase 9).
 *
 * Registered in cron_jobs as cron/cron_ai_cleanup.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

$disableCronLogs = (defined('_CRON_CENTRAL_MODE') && _CRON_CENTRAL_MODE) || getenv('CENTRAL_CRON') === '1';

$current_dir = dirname(__FILE__);
$project_root = dirname($current_dir);

require_once $project_root . '/includes/lib-initialize.php';
if (!is_file($project_root . '/ai/helpers.php')) {
    if (!$disableCronLogs) {
        echo "AI addon not installed; skipping cleanup.\n";
    }
    exit(0);
}
require_once $project_root . '/ai/helpers.php';

if (!function_exists('logMessage')) {
    function logMessage($message)
    {
        global $disableCronLogs, $project_root;
        if ($disableCronLogs) {
            return;
        }
        $logDir = $project_root . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        @file_put_contents(
            $logDir . '/ai-cleanup.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}

// Per-job lock so overlapping AI cleanup runs do not stack
$lockPath = $project_root . '/logs/ai_cleanup.lock';
$lockFp = @fopen($lockPath, 'c+');
if ($lockFp && !@flock($lockFp, LOCK_EX | LOCK_NB)) {
    logMessage('AI cleanup skipped — another run holds the lock');
    if (!defined('_CRON_CENTRAL_MODE')) {
        exit(0);
    }
    return;
}

logMessage('Starting AI retention cleanup...');

try {
    if (!ai_is_module_enabled()) {
        logMessage('AI module disabled — skip cleanup');
    } else {
        $stats = ai_security_run_cleanup($connect, 200);
        logMessage('Cleanup done: ' . json_encode($stats));
    }
    if ($lockFp) {
        @flock($lockFp, LOCK_UN);
        @fclose($lockFp);
    }
    if (!defined('_CRON_CENTRAL_MODE')) {
        exit(0);
    }
} catch (Throwable $e) {
    logMessage('AI cleanup error: ' . $e->getMessage());
    if ($lockFp) {
        @flock($lockFp, LOCK_UN);
        @fclose($lockFp);
    }
    if (!defined('_CRON_CENTRAL_MODE')) {
        exit(1);
    }
}
