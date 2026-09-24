<?php
/**
 * Cron Helper Functions
 * Provides utility functions for cron job management
 */

/**
 * Release PHP session lock so long cron runs do not block other browser tabs.
 */
function cron_release_session_lock()
{
    if (function_exists('session_write_close') && session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

/**
 * Exclusive lock so overlapping cron.php runs cannot stack PHP processes (1GB hosts).
 *
 * @return resource|false Lock handle to keep until shutdown, or false if another run is active.
 */
function cron_acquire_central_lock($projectRoot)
{
    $projectRoot = rtrim(str_replace('\\', '/', (string) $projectRoot), '/');
    $lockDir = $projectRoot . '/logs';
    if (!is_dir($lockDir)) {
        @mkdir($lockDir, 0755, true);
    }
    $lockFile = $lockDir . '/central_cron.lock';
    $fp = @fopen($lockFile, 'c+');
    if (!$fp) {
        return false;
    }
    if (!@flock($fp, LOCK_EX | LOCK_NB)) {
        fclose($fp);
        return false;
    }
    ftruncate($fp, 0);
    fwrite($fp, (string) getmypid() . ' ' . date('c'));
    fflush($fp);
    return $fp;
}

function cron_release_central_lock($fp)
{
    if (!$fp) {
        return;
    }
    @flock($fp, LOCK_UN);
    @fclose($fp);
}

/**
 * Keep a single cron.php under ~192MB / 90s so a 1GB cPanel account is not filled.
 */
function cron_apply_shared_host_limits()
{
    @ini_set('memory_limit', '192M');
    @ini_set('max_execution_time', '90');
    @set_time_limit(90);
    $mem = strtoupper(trim((string) ini_get('memory_limit')));
    if ($mem !== '' && $mem !== '192M' && $mem !== '-1') {
        @ini_set('memory_limit', '192M');
    }
}

/**
 * MySQL named lock — flock is unreliable on some NFS/cPanel filesystems.
 *
 * @return bool false if another cron already holds the lock
 */
function cron_acquire_db_lock()
{
    global $database;
    if (!isset($database->connection) || !($database->connection instanceof mysqli)) {
        return true;
    }
    $res = @mysqli_query($database->connection, "SELECT GET_LOCK('comon_central_cron', 0)");
    if (!$res) {
        return true;
    }
    $row = mysqli_fetch_row($res);
    mysqli_free_result($res);
    if (isset($row[0]) && (string) $row[0] === '0') {
        return false;
    }
    return true;
}

function cron_release_db_lock()
{
    global $database;
    if (!isset($database->connection) || !($database->connection instanceof mysqli)) {
        return;
    }
    @mysqli_query($database->connection, "SELECT RELEASE_LOCK('comon_central_cron')");
}

/**
 * Scheduled cPanel ticks: few jobs, short budget. Admin AJAX can do a bit more.
 *
 * @return array{max_jobs:int,time_budget:int}
 */
function cron_tick_limits($isAjax)
{
    $scheduled = (php_sapi_name() === 'cli') || empty($isAjax);
    if ($scheduled) {
        // Enough slots per 5-min tick for Messages + Email + other automation on shared hosts.
        return ['max_jobs' => 6, 'time_budget' => 90];
    }
    return ['max_jobs' => 8, 'time_budget' => 120];
}

/**
 * Categories that always run before the main job cap (chat batch emails, etc.).
 *
 * @return string[]
 */
function cron_priority_job_categories()
{
    return ['Messages'];
}

/**
 * Whether a scheduled tick should skip this job (still inside its duration window).
 */
function cron_central_job_is_due($job, $isScheduledRun)
{
    if (!$isScheduledRun) {
        return true;
    }
    if (empty($job['last_run']) || empty($job['duration'])) {
        return true;
    }
    if (!function_exists('isCronActive')) {
        return true;
    }

    return !isCronActive($job['last_run'], $job['duration']);
}

/**
 * Resolve and validate a cron_jobs.file_path under project root.
 *
 * @return array{ok:bool,fullPath?:string,error?:string}
 */
function cron_central_resolve_job_path($job, $projectRoot)
{
    $filePath = isset($job['file_path']) ? (string) $job['file_path'] : '';
    if ($filePath === '' || strpos($filePath, '..') !== false) {
        return ['ok' => false, 'error' => 'Invalid file path (contains .. or empty)'];
    }

    $projectRoot = rtrim(str_replace('\\', '/', (string) $projectRoot), '/');
    $fullPath = $projectRoot . '/' . $filePath;
    $fullPath = str_replace('\\', '/', $fullPath);
    $resolvedPath = realpath($fullPath);
    if ($resolvedPath === false || !is_file($resolvedPath)) {
        return ['ok' => false, 'error' => 'File not found: ' . $fullPath];
    }

    $baseRealPath = realpath($projectRoot);
    if ($baseRealPath === false || strpos($resolvedPath, $baseRealPath) !== 0) {
        return ['ok' => false, 'error' => 'File path is outside project root'];
    }

    return ['ok' => true, 'fullPath' => $resolvedPath];
}

/**
 * Execute one cron_jobs row (inline/subprocess) and update last_run.
 *
 * @return array{id:int,name:string,category:string,file_path:string,status:string,execution_time:float,error:?string,output:string}
 */
function cron_central_execute_job_row(array $job, $projectRoot)
{
    $jobId = (int) ($job['id'] ?? 0);
    $jobName = (string) ($job['name'] ?? '');
    $filePath = (string) ($job['file_path'] ?? '');
    $category = (string) ($job['category'] ?? '');

    $resolved = cron_central_resolve_job_path($job, $projectRoot);
    if (empty($resolved['ok'])) {
        if (function_exists('cron_manual_mark_job_run')) {
            cron_manual_mark_job_run($jobId, false);
        }
        return [
            'id' => $jobId,
            'name' => $jobName,
            'category' => $category,
            'file_path' => $filePath,
            'status' => 'failed',
            'execution_time' => 0,
            'error' => $resolved['error'] ?? 'Invalid job path',
            'output' => '',
        ];
    }

    $fullPath = $resolved['fullPath'];
    $exec = function_exists('cron_manual_exec_job')
        ? cron_manual_exec_job($fullPath, $filePath)
        : cron_manual_run_inline($fullPath);

    $executionSuccess = !empty($exec['success']);
    if (function_exists('cron_manual_mark_job_run')) {
        cron_manual_mark_job_run($jobId, $executionSuccess);
    }

    return [
        'id' => $jobId,
        'name' => $jobName,
        'category' => $category,
        'file_path' => $filePath,
        'status' => $executionSuccess ? 'success' : 'failed',
        'execution_time' => (float) ($exec['execution_time'] ?? 0),
        'error' => $executionSuccess ? null : (string) ($exec['error'] ?? 'Cron execution failed'),
        'output' => (string) ($exec['output'] ?? ''),
    ];
}

/**
 * Parse duration string to seconds
 * Examples: "5 minutes", "1 hour", "30 minutes", "6 hours", "1 day"
 * 
 * @param string $duration Duration string (e.g., "5 minutes", "1 hour")
 * @return int Duration in seconds, or 0 if invalid
 */
function parseDurationToSeconds($duration) {
    if (empty($duration)) {
        return 0;
    }
    
    $duration = trim(strtolower($duration));
    
    // Pattern: number followed by time unit
    if (preg_match('/^(\d+)\s*(minute|minutes|min|mins|hour|hours|hr|hrs|day|days|d)$/i', $duration, $matches)) {
        $number = (int)$matches[1];
        $unit = strtolower($matches[2]);
        
        // Normalize unit
        if (in_array($unit, ['minute', 'minutes', 'min', 'mins'])) {
            return $number * 60;
        } elseif (in_array($unit, ['hour', 'hours', 'hr', 'hrs'])) {
            return $number * 3600;
        } elseif (in_array($unit, ['day', 'days', 'd'])) {
            return $number * 86400;
        }
    }
    
    return 0;
}

/**
 * Seconds since last_run using MySQL NOW() (same clock as last_run = NOW()).
 *
 * @return int|null
 */
function cron_seconds_since_last_run($lastRun)
{
    if ($lastRun === null || $lastRun === '' || $lastRun === '0000-00-00 00:00:00') {
        return null;
    }
    global $database, $connect;
    $mysqli = null;
    if (isset($database) && isset($database->connection) && $database->connection instanceof mysqli) {
        $mysqli = $database->connection;
    } elseif (isset($connect) && $connect instanceof mysqli) {
        $mysqli = $connect;
    }
    if ($mysqli) {
        $escaped = mysqli_real_escape_string($mysqli, (string) $lastRun);
        $res = @mysqli_query($mysqli, "SELECT TIMESTAMPDIFF(SECOND, '{$escaped}', NOW()) AS s");
        if ($res) {
            $row = mysqli_fetch_assoc($res);
            mysqli_free_result($res);
            if (isset($row['s']) && $row['s'] !== null && $row['s'] !== '') {
                return (int) $row['s'];
            }
        }
    }
    $ts = strtotime((string) $lastRun);
    if ($ts === false) {
        return null;
    }
    return time() - $ts;
}

/**
 * Check if cron job is active (last run is within duration)
 * 
 * @param string|null $lastRun Last run timestamp (DATETIME format or null)
 * @param string $duration Duration string (e.g., "5 minutes", "1 hour")
 * @return bool True if cron is active (last run within duration), false otherwise
 */
function isCronActive($lastRun, $duration) {
    $elapsed = cron_seconds_since_last_run($lastRun);
    if ($elapsed === null) {
        return false;
    }
    
    $durationSeconds = parseDurationToSeconds($duration);
    if ($durationSeconds == 0) {
        return false;
    }
    
    return $elapsed <= $durationSeconds;
}

/**
 * Get human-readable time since last run
 * 
 * @param string|null $lastRun Last run timestamp (DATETIME format or null)
 * @return string Human-readable string (e.g., "2 minutes ago", "Never")
 */
function getTimeSinceLastRun($lastRun) {
    if (empty($lastRun)) {
        return "Never";
    }

    $elapsed = cron_seconds_since_last_run($lastRun);
    if ($elapsed === null) {
        return "Invalid date";
    }
    if ($elapsed < 0) {
        $elapsed = 0;
    }

    if ($elapsed < 60) {
        return $elapsed . ' seconds ago';
    }
    if ($elapsed < 3600) {
        $m = (int) round($elapsed / 60);
        return $m . ' minute' . ($m === 1 ? '' : 's') . ' ago';
    }
    if ($elapsed < 86400) {
        $h = (int) floor($elapsed / 3600);
        $m = (int) round(($elapsed % 3600) / 60);
        return $h . ' hour' . ($h === 1 ? '' : 's') . ($m > 0 ? ' ' . $m . ' min' : '') . ' ago';
    }
    $d = (int) floor($elapsed / 86400);
    return $d . ' day' . ($d === 1 ? '' : 's') . ' ago';
}

/**
 * Validate file path to prevent directory traversal
 * 
 * @param string $filePath File path to validate
 * @param string $basePath Base path (project root)
 * @return bool True if path is valid, false otherwise
 */
function validateCronFilePath($filePath, $basePath = null) {
    if (empty($filePath)) {
        return false;
    }
    
    // Prevent directory traversal
    if (strpos($filePath, '..') !== false) {
        return false;
    }
    
    // If base path is provided, check if file exists within base path
    if ($basePath !== null) {
        // Normalize path separators
        $filePath = str_replace('\\', '/', $filePath);
        $basePath = str_replace('\\', '/', $basePath);
        
        // Construct full path
        $fullPath = rtrim($basePath, '/') . '/' . ltrim($filePath, '/');
        
        // Try to resolve real path
        $resolvedPath = realpath($fullPath);
        $baseRealPath = realpath($basePath);
        
        // If realpath fails, check if the constructed path is within base path
        if ($resolvedPath === false) {
            // Check if the path structure is valid (even if file doesn't exist)
            $normalizedFull = str_replace('\\', '/', $fullPath);
            $normalizedBase = str_replace('\\', '/', $baseRealPath ? $baseRealPath : $basePath);
            return strpos($normalizedFull, $normalizedBase) === 0;
        }
        
        if ($baseRealPath === false) {
            return false;
        }
        
        // Check if resolved path is within base path
        $normalizedResolved = str_replace('\\', '/', $resolvedPath);
        $normalizedBase = str_replace('\\', '/', $baseRealPath);
        return strpos($normalizedResolved, $normalizedBase) === 0;
    }
    
    return true;
}

/**
 * PHP CLI binary for isolated cron subprocess (Apache PHP_BINARY is often httpd).
 */
function cron_resolve_php_binary()
{
    $candidates = [];

    // XAMPP-style layout: .../htdocs/project → .../php/php.exe
    $projectRoot = dirname(__DIR__);
    $xamppPhp = dirname(dirname($projectRoot)) . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'php.exe';
    if (is_file($xamppPhp)) {
        $candidates[] = $xamppPhp;
    }

    if (defined('PHP_BINDIR')) {
        $candidates[] = rtrim((string) PHP_BINDIR, '/\\') . DIRECTORY_SEPARATOR
            . (stripos(PHP_OS, 'WIN') === 0 ? 'php.exe' : 'php');
    }
    if (defined('PHP_BINARY') && PHP_BINARY) {
        $candidates[] = PHP_BINARY;
    }

    foreach ($candidates as $bin) {
        if (is_string($bin) && $bin !== '' && is_file($bin) && preg_match('/php(\.exe)?$/i', basename($bin))) {
            return $bin;
        }
    }

    return 'php';
}

/**
 * Per-job timeout when running many crons from admin (seconds).
 */
function cron_manual_timeout_for_file($filePath)
{
    $filePath = str_replace('\\', '/', strtolower((string) $filePath));
    if (strpos($filePath, 'email_sync') !== false) {
        return 120;
    }
    if (strpos($filePath, 'email_report') !== false || strpos($filePath, 'email_tracking') !== false) {
        return 120;
    }
    if (strpos($filePath, 'imap') !== false || strpos($filePath, 'inbox') !== false) {
        return 120;
    }

    return 300;
}

/**
 * Whether isolated CLI subprocess can run on this host.
 */
function cron_subprocess_available()
{
    if (!function_exists('cron_exec_isolated_script')) {
        return false;
    }
    $bootstrap = dirname(__DIR__) . '/cron/central_cli_bootstrap.php';
    if (!is_file($bootstrap)) {
        return false;
    }

    return function_exists('proc_open') || function_exists('exec');
}

/**
 * When included from admin AJAX / central cron, return instead of killing the request.
 */
function cron_should_return_instead_of_exit()
{
    return (defined('_CRON_CENTRAL_MODE') && _CRON_CENTRAL_MODE)
        || (defined('_CRON_AJAX_MODE') && _CRON_AJAX_MODE);
}

/**
 * @param int $code
 * @return never|void
 */
function cron_exit_or_return($code = 0)
{
    if (cron_should_return_instead_of_exit()) {
        return;
    }
    exit((int) $code);
}

/**
 * Heavy jobs that should run in an isolated subprocess (IMAP, etc.).
 * Admin AJAX always uses inline — matches production (no proc_open) and avoids Windows hangs.
 */
function cron_central_batch_use_subprocess($filePath)
{
    if (defined('_CRON_AJAX_MODE') && _CRON_AJAX_MODE) {
        return false;
    }

    $filePath = str_replace('\\', '/', strtolower((string) $filePath));

    return strpos($filePath, 'email_sync') !== false;
}

/**
 * Detect HTML/ fatal output from an included cron script.
 */
function cron_manual_output_indicates_failure($output)
{
    $output = (string) $output;
    if ($output === '') {
        return false;
    }

    // Real script crashes — not per-recipient SMTP bounces ("SMTP Error: ... No Such User").
    $patterns = [
        '<h1>Error</h1>',
        '<strong>ERROR:</strong>',
        'FATAL ERROR',
        'Failed to load',
        'not found at:',
        'HTTP/1.1 500',
        'is not recognized as an internal or external command',
        'operable program or batch file',
    ];

    foreach ($patterns as $pattern) {
        if (stripos($output, $pattern) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * Run a cron PHP script in a subprocess with timeout (avoids blocking the admin request on IMAP, etc.).
 *
 * @return array{success:bool,output:string,error:?string,execution_time:float,timed_out:bool}
 */
function cron_exec_isolated_script($scriptPath, $timeoutSeconds)
{
    $start = microtime(true);
    $scriptPath = str_replace('\\', '/', (string) $scriptPath);
    $resolvedScript = realpath($scriptPath);
    if ($resolvedScript === false) {
        return [
            'success' => false,
            'output' => '',
            'error' => 'Cron script not found',
            'execution_time' => 0,
            'timed_out' => false,
        ];
    }

    $timeoutSeconds = max(10, (int) $timeoutSeconds);
    $phpBinary = cron_resolve_php_binary();
    $bootstrap = dirname(__DIR__) . '/cron/central_cli_bootstrap.php';
    if (!is_file($bootstrap)) {
        return [
            'success' => false,
            'output' => '',
            'error' => 'Missing cron bootstrap file',
            'execution_time' => 0,
            'timed_out' => false,
        ];
    }

    $cmd = escapeshellarg($phpBinary) . ' '
        . escapeshellarg($bootstrap) . ' '
        . escapeshellarg($resolvedScript);

    if (!function_exists('proc_open')) {
        if (!function_exists('exec')) {
            return [
                'success' => false,
                'output' => '',
                'error' => 'Subprocess unavailable (proc_open and exec are disabled on this server)',
                'execution_time' => round(microtime(true) - $start, 2),
                'timed_out' => false,
            ];
        }
        $lines = [];
        $code = 0;
        exec($cmd . ' 2>&1', $lines, $code);

        return [
            'success' => $code === 0,
            'output' => implode("\n", $lines),
            'error' => $code === 0 ? null : ('Process exited with code ' . $code),
            'execution_time' => round(microtime(true) - $start, 2),
            'timed_out' => false,
        ];
    }

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = @proc_open($cmd, $descriptors, $pipes, dirname($resolvedScript));
    if (!is_resource($proc)) {
        return [
            'success' => false,
            'output' => '',
            'error' => 'Failed to start cron process',
            'execution_time' => round(microtime(true) - $start, 2),
            'timed_out' => false,
        ];
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $stdout = '';
    $stderr = '';
    $timedOut = false;

    while (true) {
        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);
        $status = proc_get_status($proc);

        if (!$status['running']) {
            break;
        }

        if ((microtime(true) - $start) >= $timeoutSeconds) {
            @proc_terminate($proc, 9);
            $timedOut = true;
            break;
        }

        usleep(200000);
    }

    $stdout .= (string) stream_get_contents($pipes[1]);
    $stderr .= (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($proc);

    $output = trim($stdout . ($stderr !== '' ? "\n" . $stderr : ''));

    if ($timedOut) {
        return [
            'success' => false,
            'output' => $output,
            'error' => 'Timed out after ' . $timeoutSeconds . ' seconds',
            'execution_time' => round(microtime(true) - $start, 2),
            'timed_out' => true,
        ];
    }

    if ($exitCode !== 0 && cron_manual_output_indicates_failure($output)) {
        return [
            'success' => false,
            'output' => $output,
            'error' => 'Process exited with code ' . $exitCode,
            'execution_time' => round(microtime(true) - $start, 2),
            'timed_out' => false,
        ];
    }

    if ($exitCode !== 0 && !cron_manual_output_indicates_failure($output)) {
        // Many crons exit(1) in plain CLI when idle; bootstrap + output check avoids false failures.
        return [
            'success' => true,
            'output' => $output,
            'error' => null,
            'execution_time' => round(microtime(true) - $start, 2),
            'timed_out' => false,
        ];
    }

    return [
        'success' => $exitCode === 0,
        'output' => $output,
        'error' => $exitCode === 0 ? null : ('Process exited with code ' . $exitCode),
        'execution_time' => round(microtime(true) - $start, 2),
        'timed_out' => false,
    ];
}

/**
 * Send JSON and exit (cron AJAX endpoints).
 */
function cron_ajax_json_response(array $payload, $httpCode = 200)
{
    $GLOBALS['_cron_ajax_sent'] = true;
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code((int) $httpCode);
        header('Content-Type: application/json; charset=utf-8', true);
    }
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode($payload, $flags);
    if ($json === false) {
        $json = '{"success":false,"message":"JSON encode failed","error":' . json_encode(json_last_error_msg()) . '}';
    }
    echo $json;
    exit;
}

/**
 * Run a cron script in-process (reliable on shared hosting).
 *
 * @return array{success:bool,output:string,error:?string,execution_time:float,timed_out:bool}
 */
function cron_manual_run_inline($scriptPath)
{
    $start = microtime(true);
    $resolved = realpath((string) $scriptPath);
    if ($resolved === false || !is_file($resolved)) {
        return [
            'success' => false,
            'output' => '',
            'error' => 'Cron script not found',
            'execution_time' => 0,
            'timed_out' => false,
        ];
    }

    if (!defined('_CRON_AJAX_MODE')) {
        define('_CRON_AJAX_MODE', true);
    }
    if (!defined('_CRON_CENTRAL_MODE')) {
        define('_CRON_CENTRAL_MODE', true);
    }
    if (function_exists('putenv')) {
        @putenv('CENTRAL_CRON=1');
    }

    $projectRoot = dirname(__DIR__);
    $oldCwd = getcwd();
    @chdir($projectRoot);

    $output = '';
    $error = null;
    $success = true;

    ob_start();
    try {
        // Included cron scripts expect lib-initialize globals ($database, etc.).
        if (!empty($GLOBALS) && is_array($GLOBALS)) {
            extract($GLOBALS, EXTR_SKIP);
        }
        include $resolved;
    } catch (Throwable $e) {
        $success = false;
        $error = $e->getMessage();
    }
    $output = (string) ob_get_clean();
    if ($oldCwd) {
        @chdir($oldCwd);
    }

    if ($success && cron_manual_output_indicates_failure($output)) {
        if (cron_manual_output_indicates_success($output)) {
            $success = true;
        } else {
            $success = false;
            if ($error === null) {
                $error = 'Cron script reported an error';
            }
        }
    }

    return [
        'success' => $success,
        'output' => $output,
        'error' => $success ? null : ($error ?: 'Cron execution failed'),
        'execution_time' => round(microtime(true) - $start, 2),
        'timed_out' => false,
    ];
}

/**
 * Execute one cron job for admin manual / central batch runs.
 *
 * @return array{success:bool,output:string,error:?string,execution_time:float,timed_out:bool}
 */
function cron_manual_exec_job($fullPath, $filePath)
{
    $filePath = (string) $filePath;
    $preferSubprocess = function_exists('cron_central_batch_use_subprocess')
        && cron_central_batch_use_subprocess($filePath)
        && function_exists('cron_subprocess_available')
        && cron_subprocess_available();

    if ($preferSubprocess) {
        return cron_exec_isolated_script($fullPath, cron_manual_timeout_for_file($filePath));
    }

    return cron_manual_run_inline($fullPath);
}

/**
 * Update cron_jobs last_run without dying on SQL errors.
 */
function cron_manual_mark_job_run($cronId, $success)
{
    global $database;
    if (!isset($database) || !is_object($database)) {
        return false;
    }

    $status = $success ? 'success' : 'failed';
    $sql = "UPDATE cron_jobs SET last_run = NOW(), last_run_status = '" . $status . "' WHERE id = " . (int) $cronId;
    if (method_exists($database, 'querySoft')) {
        return (bool) $database->querySoft($sql);
    }

    return (bool) @$database->query($sql);
}

/**
 * Detect likely successful idle/completion cron output.
 */
function cron_manual_output_indicates_success($output)
{
    $output = trim((string) $output);
    if ($output === '') {
        return true;
    }

    $patterns = [
        'completed',
        'No job available',
        'No work to do',
        'No offline users',
        'No users found',
        'No messages found',
        'not enabled',
        'disabled',
        'Execution time:',
        'Users processed',
        'Emails sent',
    ];

    foreach ($patterns as $pattern) {
        if (stripos($output, $pattern) !== false) {
            return true;
        }
    }

    return false;
}

