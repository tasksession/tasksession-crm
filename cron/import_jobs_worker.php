<?php
/**
 * Processes queued CSV import jobs (leads, users, projects, tasks, …).
 * Register in cron_jobs (seeded by migration 3.23). Safe to run every 1–5 minutes.
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$current_dir = dirname(__FILE__);
$project_root = dirname($current_dir);
$disableCronLogs = (defined('_CRON_CENTRAL_MODE') && _CRON_CENTRAL_MODE) || getenv('CENTRAL_CRON') === '1';

require_once $project_root . '/includes/lib-initialize.php';
require_once $project_root . '/includes/import-export/bootstrap.php';

if (!isset($connect) || !($connect instanceof mysqli)) {
    return;
}

$table = @mysqli_query($connect, "SHOW TABLES LIKE 'import_jobs'");
if (!$table || mysqli_num_rows($table) === 0) {
    mysqli_free_result($table);
    return;
}
mysqli_free_result($table);

$result = Comon_IE_ImportBatchWorker::runOnce($connect);
if (!$disableCronLogs && !empty($result['ran'])) {
    @error_log('[import_jobs_worker] ' . json_encode($result));
}
