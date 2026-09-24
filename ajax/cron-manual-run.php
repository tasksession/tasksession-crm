<?php
/**
 * Manual Cron Job Execution AJAX Handler
 */

header('Content-Type: application/json; charset=utf-8');

$GLOBALS['_cron_ajax_sent'] = false;
$GLOBALS['_cron_ajax_job'] = null;

register_shutdown_function(static function () {
    if (!empty($GLOBALS['_cron_ajax_sent'])) {
        return;
    }

    $output = '';
    while (ob_get_level() > 0) {
        $chunk = ob_get_contents();
        if ($chunk !== false && $chunk !== '') {
            $output .= $chunk;
        }
        ob_end_clean();
    }

    $job = is_array($GLOBALS['_cron_ajax_job'] ?? null) ? $GLOBALS['_cron_ajax_job'] : [];
    $err = error_get_last();
    $hasFatal = $err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true);

    if ($output === '' && !$hasFatal && empty($job)) {
        return;
    }

    $failedOutput = function_exists('cron_manual_output_indicates_failure')
        && cron_manual_output_indicates_failure($output);
    $success = !$hasFatal && !$failedOutput;
    if (trim($output) === '' && !$hasFatal) {
        $success = true;
    }

    $GLOBALS['_cron_ajax_sent'] = true;
    if (!headers_sent()) {
        http_response_code($success ? 200 : 500);
        header('Content-Type: application/json; charset=utf-8', true);
    }

    $cleanOutput = trim(preg_replace('/\s+/', ' ', strip_tags($output)));
    $payload = [
        'success' => $success,
        'message' => $success
            ? (trim($cleanOutput) === '' ? 'No job available at this time' : 'Cron job executed successfully')
            : 'Cron job execution failed',
        'error' => $success ? null : ($hasFatal
            ? ($err['message'] . ' in ' . $err['file'] . ' on line ' . $err['line'])
            : (trim(strip_tags($output)) ?: 'Cron script exited unexpectedly')),
        'execution_time' => 0,
        'output' => trim($cleanOutput) === '' ? 'No job available at this time' : substr($cleanOutput, 0, 500),
        'job' => $job['name'] ?? null,
        'file_path' => $job['file_path'] ?? null,
        'mode' => $job['mode'] ?? null,
        'shutdown_recovery' => true,
    ];

    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    echo json_encode($payload, $flags);
});

ob_start();

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (!defined('CRM_LIGHTWEIGHT_INIT')) {
    define('CRM_LIGHTWEIGHT_INIT', true);
}

try {
    require_once __DIR__ . '/../includes/lib-initialize.php';
    require_once __DIR__ . '/../includes/cron_helper.php';
} catch (Throwable $e) {
    if (!function_exists('cron_ajax_json_response')) {
        while (ob_get_level() > 0) {
    ob_end_clean();
        }
        http_response_code(500);
    echo json_encode([
            'success' => false,
            'message' => 'Failed to load required files',
            'error' => $e->getMessage(),
        ]);
        exit;
    }
    cron_ajax_json_response([
        'success' => false,
        'message' => 'Failed to load required files',
        'error' => $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine(),
    ], 500);
}

ob_clean();

if (!function_exists('cron_ajax_json_response') || !function_exists('cron_release_session_lock')) {
    cron_ajax_json_response([
        'success' => false,
        'message' => 'Cron helper is missing or outdated on the server',
        'error' => 'Upload includes/cron_helper.php (with cron_ajax_json_response)',
    ], 500);
}

if (!$session->isLoggedIn() || (int) ($_SESSION['accountStatus'] ?? 0) !== 1) {
    cron_ajax_json_response(['success' => false, 'message' => 'Unauthorized'], 403);
}

if (function_exists('cron_apply_shared_host_limits')) {
    cron_apply_shared_host_limits();
} else {
    @set_time_limit(90);
}
@ignore_user_abort(true);
cron_release_session_lock();

if (!isset($_POST['cron_id']) || $_POST['cron_id'] === '') {
    cron_ajax_json_response(['success' => false, 'message' => 'Cron ID is required'], 400);
}

$cronId = (int) $_POST['cron_id'];
$centralBatch = !empty($_POST['central_batch']);

global $database;
if (!isset($database) || !is_object($database)) {
    cron_ajax_json_response(['success' => false, 'message' => 'Database not available'], 500);
}

$sql = 'SELECT * FROM cron_jobs WHERE id = ' . $cronId;
$result = method_exists($database, 'querySoft') ? $database->querySoft($sql) : $database->query($sql);
if (!$result) {
    cron_ajax_json_response(['success' => false, 'message' => 'Database query failed'], 500);
}

$job = $database->fetchArray($result);
if (!$job) {
    cron_ajax_json_response(['success' => false, 'message' => 'Cron job not found'], 404);
}

$filePath = isset($job['file_path']) ? trim((string) $job['file_path']) : '';
$jobName = isset($job['name']) ? (string) $job['name'] : 'Unknown';
$projectRoot = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');

if ($filePath === '' || strpos($filePath, '..') !== false) {
    cron_ajax_json_response(['success' => false, 'message' => 'Invalid file path', 'error' => $filePath], 400);
}

$filePath = trim(str_replace('\\', '/', $filePath), '/');
$fullPath = $projectRoot . '/' . $filePath;

if (!is_file($fullPath)) {
    cron_ajax_json_response([
        'success' => false, 
        'message' => 'Cron file not found',
        'error' => $filePath,
    ], 404);
}

    $resolvedPath = realpath($fullPath);
$baseRealPath = realpath($projectRoot);
    if ($resolvedPath && $baseRealPath) {
        $resolvedPath = str_replace('\\', '/', $resolvedPath);
        $baseRealPath = str_replace('\\', '/', $baseRealPath);
        if (strpos($resolvedPath, $baseRealPath) !== 0) {
        cron_ajax_json_response(['success' => false, 'message' => 'File path is outside project root'], 400);
    }
}

$batchStart = microtime(true);
$GLOBALS['_cron_ajax_job'] = [
    'name' => $jobName,
    'file_path' => $filePath,
    'mode' => $centralBatch ? 'central_batch' : 'manual',
];

try {
    if ($centralBatch) {
if (!defined('_CRON_AJAX_MODE')) {
    define('_CRON_AJAX_MODE', true);
        }
        if (!defined('_CRON_CENTRAL_MODE')) {
            define('_CRON_CENTRAL_MODE', true);
        }
        if (function_exists('putenv')) {
            @putenv('CENTRAL_CRON=1');
        }

        $execResult = function_exists('cron_manual_exec_job')
            ? cron_manual_exec_job($fullPath, $filePath)
            : (function_exists('cron_manual_run_inline')
                ? cron_manual_run_inline($fullPath)
                : ['success' => false, 'output' => '', 'error' => 'No cron execution helper available', 'execution_time' => 0, 'timed_out' => false]);
            } else {
        if (!defined('_CRON_AJAX_MODE')) {
            define('_CRON_AJAX_MODE', true);
        }

    $oldCwd = getcwd();
        @chdir($projectRoot);
        ob_start();
        $thrown = null;
        try {
            include $fullPath;
    } catch (Throwable $e) {
            $thrown = $e;
        }
        $inlineOutput = (string) ob_get_clean();
        @chdir($oldCwd ?: $projectRoot);

        $inlineSuccess = $thrown === null;
        if ($inlineSuccess && function_exists('cron_manual_output_indicates_failure') && cron_manual_output_indicates_failure($inlineOutput)) {
            $inlineSuccess = false;
        }
        if (!$inlineSuccess && $thrown === null && function_exists('cron_manual_output_indicates_success') && cron_manual_output_indicates_success($inlineOutput)) {
            $inlineSuccess = true;
        }

        $execResult = [
            'success' => $inlineSuccess,
            'output' => $inlineOutput,
            'error' => $inlineSuccess ? null : ($thrown ? $thrown->getMessage() : 'Cron script reported an error'),
            'execution_time' => round(microtime(true) - $batchStart, 2),
            'timed_out' => false,
        ];
    }
} catch (Throwable $e) {
    cron_ajax_json_response([
        'success' => false,
        'message' => 'Cron job execution failed',
        'error' => $e->getMessage(),
        'job' => $jobName,
        'file_path' => $filePath,
        'execution_time' => round(microtime(true) - $batchStart, 2),
    ], 500);
}

$executionSuccess = !empty($execResult['success']);
$output = (string) ($execResult['output'] ?? '');
$errorOutput = $executionSuccess ? null : (string) ($execResult['error'] ?? 'Unknown error');
$executionTime = (float) ($execResult['execution_time'] ?? round(microtime(true) - $batchStart, 2));
$timedOut = !empty($execResult['timed_out']);

if (function_exists('cron_manual_mark_job_run')) {
    cron_manual_mark_job_run($cronId, $executionSuccess);
}

if ($executionSuccess) {
    if (!function_exists('setup_guide_mark_visit')) {
        require_once dirname(__DIR__) . '/includes/setup_guide.php';
    }
    if (function_exists('setup_guide_mark_visit')) {
        setup_guide_mark_visit('cron');
    }
}

$cleanOutput = trim(preg_replace('/\s+/', ' ', strip_tags($output)));
$isNoJob = $executionSuccess && $cleanOutput === '';

cron_ajax_json_response([
    'success' => $executionSuccess,
    'message' => $executionSuccess
        ? ($isNoJob ? 'No job available at this time' : 'Cron job executed successfully')
        : 'Cron job execution failed',
    'error' => $errorOutput,
    'execution_time' => $executionTime,
    'output' => $isNoJob ? 'No job available at this time' : substr($cleanOutput, 0, 500),
    'timed_out' => $timedOut,
    'no_job_available' => $isNoJob,
    'job' => $jobName,
    'file_path' => $filePath,
    'mode' => $centralBatch ? 'central_batch' : 'manual',
    'last_run' => $job['last_run'] ?? null,
    'last_run_status' => $executionSuccess ? 'success' : 'failed',
], $executionSuccess ? 200 : 500);
