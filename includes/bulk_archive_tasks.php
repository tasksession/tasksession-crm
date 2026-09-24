<?php
require_once("lib-initialize.php");
require_once("permissions.php");

header('Content-Type: application/json');

if (!$session->isLoggedIn()) {
    echo json_encode(['status' => 'error', 'error' => 'Not logged in']);
    exit;
}
ensure_user_permissions($connect);

$input = json_decode(file_get_contents('php://input'), true);
$taskIds = [];
if (is_array($input) && isset($input['ids']) && is_array($input['ids'])) {
    $taskIds = array_map('intval', $input['ids']);
    $taskIds = array_values(array_filter($taskIds));
}
$action = (is_array($input) && isset($input['action'])) ? (string) $input['action'] : '';
$csrfToken = '';
if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
    $csrfToken = (string) $_SERVER['HTTP_X_CSRF_TOKEN'];
} elseif (is_array($input) && isset($input['csrf_token'])) {
    $csrfToken = (string) $input['csrf_token'];
}

if ($csrfToken === '' || !function_exists('validate_csrf_token') || !validate_csrf_token($csrfToken)) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid CSRF token']);
    exit;
}

if (empty($taskIds)) {
    echo json_encode(['status' => 'error', 'error' => 'No tasks selected']);
    exit;
}

if ($action !== 'archive' && $action !== 'unarchive') {
    echo json_encode(['status' => 'error', 'error' => 'Invalid action']);
    exit;
}

$userType = isset($_SESSION['accountStatus']) ? (int) $_SESSION['accountStatus'] : 0;
$canArchive = ($userType === 1) || ($userType === 3 && function_exists('has_permission') && has_permission('task_edit'));
if (!$canArchive) {
    echo json_encode(['status' => 'error', 'error' => 'Not authorized']);
    exit;
}

require_once("task.php");

$successCount = 0;
$failed = [];

foreach ($taskIds as $taskId) {
    try {
        $task = Task::findById($taskId);
        if (!$task) {
            $failed[] = "Task ID $taskId not found";
            continue;
        }

        if ($action === 'archive') {
            if (!empty($task->is_archived)) {
                $successCount++;
                continue;
            }
            if ($task->archive()) {
                $successCount++;
            } else {
                $failed[] = "Failed to archive task ID $taskId";
            }
        } else {
            if (empty($task->is_archived)) {
                $successCount++;
                continue;
            }
            if ($task->unarchive()) {
                $successCount++;
            } else {
                $failed[] = "Failed to unarchive task ID $taskId";
            }
        }
    } catch (Exception $e) {
        $failed[] = "Task ID $taskId: " . $e->getMessage();
    }
}

echo json_encode([
    'status' => 'ok',
    'success' => $successCount,
    'failed' => $failed,
]);
