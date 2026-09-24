<?php
/**
 * Recurring Tasks Cron Job
 *
 * Run daily to create the next time-based recurring task.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

$disableCronLogs = (defined('_CRON_CENTRAL_MODE') && _CRON_CENTRAL_MODE) || getenv('CENTRAL_CRON') === '1';

$current_dir = dirname(__FILE__);
$project_root = dirname($current_dir);

require_once $project_root . '/includes/lib-initialize.php';
require_once $project_root . '/includes/task.php';
require_once $project_root . '/includes/task_recurrence_helper.php';

if (!function_exists('logMessage')) {
    function logMessage($message)
    {
        global $disableCronLogs;
        if ($disableCronLogs) {
            return;
        }
    }
}

logMessage('Starting recurring tasks cron job...');

try {
    $result = tasksession_recurrence_run_due_time_based();
    logMessage('Recurring tasks created: ' . (int) ($result['created'] ?? 0) . ', skipped: ' . (int) ($result['skipped'] ?? 0));
    if (!defined('_CRON_CENTRAL_MODE')) {
        exit(0);
    }
} catch (Throwable $e) {
    logMessage('Recurring tasks cron error: ' . $e->getMessage());
    if (!defined('_CRON_CENTRAL_MODE')) {
        exit(1);
    }
}
