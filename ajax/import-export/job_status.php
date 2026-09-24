<?php
/**
 * JSON status for import_jobs (polling after async queue).
 */
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 2) . '/includes/lib-initialize.php';

if (!$session->isLoggedIn()) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

$isAdmin = isset($_SESSION['accountStatus']) && (int)$_SESSION['accountStatus'] === 1;
$jobId = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;
if ($jobId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid job']);
    exit;
}

require_once dirname(__DIR__, 2) . '/includes/import-export/bootstrap.php';
$row = Comon_IE_ImportJobService::getById($connect, $jobId);
if (!$row) {
    echo json_encode(['ok' => false, 'error' => 'Not found']);
    exit;
}
if (!$isAdmin && (int)$row['user_id'] !== (int)$session->userId) {
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

echo json_encode([
    'ok' => true,
    'job' => [
        'id' => (int)$row['id'],
        'status' => $row['status'],
        'module_name' => $row['module_name'],
        'total_rows' => (int)$row['total_rows'],
        'processed_rows' => (int)$row['processed_rows'],
        'success_rows' => (int)$row['success_rows'],
        'failed_rows' => (int)$row['failed_rows'],
        'updated_rows' => (int)$row['updated_rows'],
        'skipped_rows' => (int)$row['skipped_rows'],
        'dry_run' => !empty($row['dry_run']) ? 1 : 0,
        'last_error' => $row['last_error'],
        'finished_at' => $row['finished_at'],
    ],
], JSON_UNESCAPED_UNICODE);
exit;
