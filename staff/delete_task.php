<?php
// staff/delete_task.php
require_once("../includes/lib-initialize.php");

// auth check
if (!$session->isLoggedIn()) {
    die(json_encode(['status' => 'error', 'error' => 'Not logged in']));
}
if ($_SESSION['accountStatus'] != 3) {
    die(json_encode(['status' => 'error', 'error' => 'Insufficient permissions']));
}

// Load permission model
require_once("../includes/task_permission.php");

// Check if user has permission to delete tasks
$staff_id = $_SESSION['userId'];
$permissions = TaskPermission::findByUserId($staff_id);
if (!$permissions || !$permissions->can_delete_task) {
    die(json_encode(['status' => 'error', 'error' => 'Permission denied']));
}

// get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id'])) {
    die(json_encode(['status' => 'error', 'error' => 'Missing task ID']));
}

$id = (int)$input['id'];

// load task
require_once("../includes/task.php");
$task = Task::findById($id);
if (!$task) {
    die(json_encode(['status' => 'error', 'error' => 'Task not found']));
}

// Check if staff is assigned to this project
$project_id = $task->project_id;
$project = projects::findByProjectId($project_id);
if (!$project) {
    die(json_encode(['status' => 'error', 'error' => 'Project not found']));
}

$staffIds = explode(',', $project->s_ids);
if (!in_array($staff_id, $staffIds)) {
    die(json_encode(['status' => 'error', 'error' => 'Not authorized for this project']));
}

// delete task
if ($task->delete()) {
    echo json_encode(['status' => 'ok']);
} else {
    echo json_encode(['status' => 'error', 'error' => 'Failed to delete task']);
} 