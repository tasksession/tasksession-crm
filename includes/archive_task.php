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
$taskId = isset($input['id']) ? (int)$input['id'] : 0;
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

if ($taskId <= 0) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid task ID']);
    exit;
}

require_once("task.php");

try {
    $task = Task::findById($taskId);
    if (!$task) {
        echo json_encode(['status' => 'error', 'error' => 'Task not found']);
        exit;
    }

    $userType = isset($_SESSION['accountStatus']) ? (int) $_SESSION['accountStatus'] : 0;
    $canArchive = ($userType === 1) || ($userType === 3 && function_exists('has_permission') && has_permission('task_edit'));
    if (!$canArchive) {
        echo json_encode(['status' => 'error', 'error' => 'Not authorized to archive this task']);
        exit;
    }

    if (!empty($task->is_archived)) {
        echo json_encode(['status' => 'ok']);
        exit;
    }

    if ($task->archive()) {
        echo json_encode(['status' => 'ok']);
    } else {
        echo json_encode(['status' => 'error', 'error' => 'Failed to archive task']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}
