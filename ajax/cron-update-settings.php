<?php
/**
 * Update Cron Job Settings AJAX Handler
 * Updates duration and enabled status for cron jobs
 */

require_once(__DIR__ . '/../includes/lib-initialize.php');
require_once(__DIR__ . '/../includes/cron_helper.php');

// Check if user is logged in and is admin
if (!$session->isLoggedIn() || $_SESSION['accountStatus'] != 1) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once(__DIR__ . '/../includes/csrf-middleware.php');
csrf_require_for_request();

header('Content-Type: application/json');

if (!isset($_POST['cron_id']) || empty($_POST['cron_id'])) {
    echo json_encode(['success' => false, 'message' => 'Cron ID is required']);
    exit;
}

$cronId = (int)$_POST['cron_id'];

// Get cron job from database
global $database;
$sql = "SELECT * FROM cron_jobs WHERE id = " . (int)$cronId;
$result = $database->query($sql);

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Database query failed']);
    exit;
}

$job = $database->fetchArray($result);
if (!$job) {
    echo json_encode(['success' => false, 'message' => 'Cron job not found']);
    exit;
}

$updates = [];
$errors = [];

// Update duration if provided
if (isset($_POST['duration'])) {
    $duration = trim($_POST['duration']);
    
    // Validate duration format
    $durationSeconds = parseDurationToSeconds($duration);
    if ($durationSeconds == 0 && !empty($duration)) {
        $errors[] = 'Invalid duration format. Use format like "5 minutes", "1 hour", "1 day"';
    } else {
        $durationEscaped = $database->escapeValue($duration);
        $updates[] = "duration = '{$durationEscaped}'";
    }
}

// Update enabled status if provided
$isEmailReport = false;
if (isset($_POST['enabled'])) {
    $enabled = (int)$_POST['enabled'];
    $enabled = ($enabled == 1) ? 1 : 0;
    $updates[] = "enabled = {$enabled}";
    
    // If this is the Email Report cron job, also update email_report_enabled in settings
    if ($job['file_path'] == 'cron/email_report.php' || $job['name'] == 'Email Report') {
        $isEmailReport = true;
        require_once(__DIR__ . '/../includes/settings.php');
        $settings = settings::findById(1);
        if ($settings) {
            $settings->id = 1;
            $settings->email_report_enabled = $enabled;
            $settings->save();
        }
    }
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

if (empty($updates)) {
    echo json_encode(['success' => false, 'message' => 'No updates provided']);
    exit;
}

// Update database
$updateSql = "UPDATE cron_jobs SET " . implode(', ', $updates) . " WHERE id = " . (int)$cronId;
$result = $database->query($updateSql);

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Failed to update cron job settings']);
    exit;
}

if (!function_exists('setup_guide_mark_visit')) {
    require_once dirname(__DIR__) . '/includes/setup_guide.php';
}
if (function_exists('setup_guide_mark_visit')) {
    setup_guide_mark_visit('cron');
}

// Get updated job info
$sql = "SELECT * FROM cron_jobs WHERE id = " . (int)$cronId;
$result = $database->query($sql);
$updatedJob = $database->fetchArray($result);

// Check if cron is active
$isActive = isCronActive($updatedJob['last_run'], $updatedJob['duration']);

// Custom message for Email Report
$message = 'Cron job settings updated successfully';
if ($isEmailReport && isset($_POST['enabled'])) {
    $enabled = (int)$_POST['enabled'];
    if ($enabled == 1) {
        $message = 'Email reports have been enabled successfully';
    } else {
        $message = 'Email reports have been disabled successfully';
    }
}

echo json_encode([
    'success' => true,
    'message' => $message,
    'cron_job' => [
        'id' => $updatedJob['id'],
        'name' => $updatedJob['name'],
        'duration' => $updatedJob['duration'],
        'enabled' => (bool)$updatedJob['enabled'],
        'last_run' => $updatedJob['last_run'],
        'last_run_status' => $updatedJob['last_run_status'],
        'is_active' => $isActive,
        'time_since_last_run' => getTimeSinceLastRun($updatedJob['last_run'])
    ]
]);

