<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : includes/delete_task.php
   Purpose : Centralized handler for task deletion functionality
 ================================================================================
 */
require_once("lib-initialize.php");
require_once("permissions.php");

// Authentication check
if (!$session->isLoggedIn()) {
    echo json_encode(['status' => 'error', 'error' => 'Not logged in']);
    exit;
}
ensure_user_permissions($connect);

// Get POST data
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

// Load task model
require_once("task.php");

try {
    // Find the task
    $task = Task::findById($taskId);
    
    if (!$task) {
        echo json_encode(['status' => 'error', 'error' => 'Task not found']);
        exit;
    }

    // Enforce role and permission checks on backend (UI checks alone are not enough)
    $userType = isset($_SESSION['accountStatus']) ? (int) $_SESSION['accountStatus'] : 0;
    $canDelete = false;
    if ($userType === 1) {
        $canDelete = true;
    } elseif ($userType === 3) {
        $canDelete = function_exists('has_permission') && has_permission('task_delete');
    }

    if (!$canDelete) {
        echo json_encode(['status' => 'error', 'error' => 'Not authorized to delete this task']);
        exit;
    }
    
    // Delete the task
    $result = $task->delete();
    
    if ($result) {
        echo json_encode(['status' => 'ok']);
    } else {
        echo json_encode(['status' => 'error', 'error' => 'Failed to delete task']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
} 