<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : includes/save_task.php
   Purpose : Centralized handler for saving and creating new tasks
 ================================================================================
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');

ob_start();

try {
    require_once __DIR__ . '/lib-initialize.php';
    require_once __DIR__ . '/permissions.php';
    require_once __DIR__ . '/csrf-middleware.php';
    require_once __DIR__ . '/task_permission.php';
    require_once __DIR__ . '/project_permission.php';

    if (!isset($database)) {
        throw new Exception('Database object not initialized');
    }

    if (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: application/json; charset=utf-8');

    if (!$session->isLoggedIn()) {
        echo json_encode(['status' => 'error', 'error' => 'Not logged in']);
        exit;
    }

    ensure_user_permissions($connect);
    csrf_require_for_request('json');

    $userType = isset($_SESSION['accountStatus']) ? (int) $_SESSION['accountStatus'] : 0;
    $userId = isset($_SESSION['userId']) ? (int) $_SESSION['userId'] : 0;

    if (empty($_POST['title']) || empty($_POST['project_id'])) {
        echo json_encode(['status' => 'error', 'error' => 'Missing required fields']);
        exit;
    }

    $title = strip_tags((string) $_POST['title']);
    $description = sanitize_tinymce_content($_POST['description'] ?? '');
    $project_id = (int) $_POST['project_id'];

    $allowedStatuses = ['todo', 'inprogress', 'review', 'done'];
    $statusInput = strip_tags((string) ($_POST['category'] ?? 'todo'));
    $status = in_array($statusInput, $allowedStatuses, true) ? $statusInput : 'todo';

    require_once __DIR__ . '/projects.php';
    $project = projects::findByProjectId($project_id);
    if (!$project) {
        echo json_encode(['status' => 'error', 'error' => 'Invalid project']);
        exit;
    }

    $canCreateTask = false;

    switch ($userType) {
        case 1:
            $canCreateTask = true;
            break;
        case 2:
            $canCreateTask = ProjectPermission::isProjectClient($userId, $project_id);
            break;
        case 3:
            $canCreateTask = ProjectPermission::canManageProject($userId, $project_id)
                || has_permission('task_create')
                || TaskPermission::canCreateTask($userId);
            break;
        default:
            $canCreateTask = false;
    }

    if (!$canCreateTask) {
        echo json_encode(['status' => 'error', 'error' => 'Not authorized to create tasks in this project']);
        exit;
    }

    global $connect;
    $stmt = $connect->prepare('SELECT COALESCE(MAX(position), -1) + 1 AS pos FROM tasks WHERE project_id = ? AND status = ?');
    $stmt->bind_param('is', $project_id, $status);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result) {
        $err = mysqli_error($connect);
        $stmt->close();
        error_log('save_task.php position query failed: ' . $err);
        throw new Exception('Unable to save task.');
    }

    $row = $result->fetch_assoc();
    $position = (int) ($row['pos'] ?? 0);
    $stmt->close();

    require_once __DIR__ . '/task.php';
    $task = new Task();
    $task->title = $title;
    $task->description = $description;
    $task->project_id = $project_id;
    $task->status = $status;
    $task->user_id = $userId;
    $task->created_at = date('Y-m-d H:i:s');
    $task->position = $position;

    if ($task->save()) {
        echo json_encode(['status' => 'ok']);
    } else {
        echo json_encode(['status' => 'error', 'error' => 'Failed to save task']);
    }
} catch (Throwable $e) {
    if (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code(400);
    error_log('save_task.php: ' . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'error' => 'Unable to save task. Please try again.',
    ]);
}

exit();
