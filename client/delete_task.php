<?php
// client/delete_task.php - Handles AJAX requests to delete tasks

// Include required files
require_once("../includes/lib-initialize.php");
require_once("../includes/task.php");
require_once("../includes/task_permission.php");

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!$session->isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

// Check account type (must be client)
if ($_SESSION['accountStatus'] != 2) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid account type']);
    exit;
}

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);
$csrfToken = '';
if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
    $csrfToken = (string) $_SERVER['HTTP_X_CSRF_TOKEN'];
} elseif (is_array($data) && isset($data['csrf_token'])) {
    $csrfToken = (string) $data['csrf_token'];
}
if ($csrfToken === '' || !function_exists('validate_csrf_token') || !validate_csrf_token($csrfToken)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit;
}

// Validate required fields
if (!isset($data['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing task ID']);
    exit;
}

// Get task ID
$taskId = (int)$data['id'];

// Get current user ID
$userId = $session->userId;

// Check permissions
$permissions = TaskPermission::getOrCreate($userId);
if (!$permissions->can_delete_task) {
    echo json_encode(['status' => 'error', 'message' => 'Permission denied']);
    exit;
}

// Find the task
$task = Task::findById($taskId);
if (!$task) {
    echo json_encode(['status' => 'error', 'message' => 'Task not found']);
    exit;
}

// Verify client has access to this project
$client_id = $session->userId;
$project = projects::findByProjectId($task->project_id);

if (!$project) {
    echo json_encode(['status' => 'error', 'message' => 'Project not found']);
    exit;
}

// Check if current user is the client for this project (main client or additional client)
$isClient = false;
if($project->c_id == $client_id || $project->main_client_id == $client_id) {
    $isClient = true;
} elseif(!empty($project->c_ids)) {
    $allClientIds = array_filter(explode(',', $project->c_ids));
    if(in_array($client_id, $allClientIds)) {
        $isClient = true;
    }
}

if(!$isClient){
    echo json_encode(['status' => 'error', 'message' => 'Project access denied']);
    exit;
}

// Delete the task
if ($task->delete()) {
    echo json_encode(['status' => 'ok']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete task']);
}
?> 