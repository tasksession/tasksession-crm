<?php
/**
 * Task Files API
 * Handles listing and deleting task file attachments (authorization required).
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/lib-initialize.php';
require_once __DIR__ . '/../includes/task.php';
require_once __DIR__ . '/../includes/projects.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf-middleware.php';

header('Content-Type: application/json; charset=utf-8');

if (!$session->isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

ensure_user_permissions($connect);

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (in_array($method, ['DELETE', 'POST', 'PUT', 'PATCH'], true)) {
    csrf_require_for_request('json');
}

/**
 * Same rules as ajax/task_files_upload.php — user must be allowed to see the task.
 */
function task_files_user_can_access_task($task, $user_id) {
    if (!$task instanceof Task) {
        return false;
    }
    $user_id = (int) $user_id;
    $user_type = isset($_SESSION['accountStatus']) ? (int) $_SESSION['accountStatus'] : 0;

    if ($user_type === 1) {
        return true;
    }
    if ($user_type === 3) {
        return staff_can_view_task($task, $user_id);
    }
    if ($user_type === 2) {
        return $task->isClientAssociated($user_id);
    }

    return false;
}

$task_id = 0;
if ($method === 'GET' && isset($_GET['task_id'])) {
    $task_id = (int) $_GET['task_id'];
} elseif ($method === 'POST' && isset($_POST['task_id'])) {
    $task_id = (int) $_POST['task_id'];
} elseif ($method === 'DELETE' && isset($_GET['task_id'])) {
    $task_id = (int) $_GET['task_id'];
}

if ($task_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Task ID is required']);
    exit;
}

$task = Task::findById($task_id);
if (!$task) {
    echo json_encode(['status' => 'error', 'message' => 'Task not found']);
    exit;
}

if (!task_files_user_can_access_task($task, (int) $session->userId)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied for this task']);
    exit;
}

switch ($method) {
    case 'GET':
        getTaskFiles($task_id);
        break;
    case 'DELETE':
        deleteTaskFile($task_id);
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        break;
}

function getTaskFiles($task_id) {
    global $connect, $url;

    $stmt = $connect->prepare("SELECT tf.*, u.firstName as uploaded_by_name 
              FROM task_files tf 
              LEFT JOIN users u ON tf.user_id = u.id 
              WHERE tf.task_id = ? 
              ORDER BY tf.upload_date DESC");
    $stmt->bind_param('i', $task_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $files = [];

    while ($row = $result->fetch_assoc()) {
        $file_url = $url . 'includes/secure_file_handler.php?src=' . urlencode($url . 'uploads/task-files/' . $row['filename']);
        $thumb_url = $file_url . '&thumb=1&t=' . time();

        $files[] = [
            'id' => $row['id'],
            'filename' => $row['filename'],
            'original_name' => $row['original_name'],
            'file_size' => $row['file_size'],
            'file_type' => $row['file_type'],
            'file_url' => $file_url,
            'thumb_url' => $thumb_url,
            'upload_date' => $row['upload_date'],
            'uploaded_by_name' => $row['uploaded_by_name'],
        ];
    }

    $stmt->close();
    echo json_encode([
        'status' => 'success',
        'files' => $files,
    ]);
}

function deleteTaskFile($task_id) {
    global $connect;

    if (!isset($_GET['file_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'File ID is required']);
        return;
    }

    $file_id = (int) $_GET['file_id'];

    $stmt = $connect->prepare('SELECT * FROM task_files WHERE id = ? AND task_id = ?');
    $stmt->bind_param('ii', $file_id, $task_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $file = $result->fetch_assoc();
    $stmt->close();

    if (!$file) {
        echo json_encode(['status' => 'error', 'message' => 'File not found']);
        return;
    }

    if (!empty($file['file_path']) && file_exists($file['file_path'])) {
        @unlink($file['file_path']);
    }

    $stmt = $connect->prepare('DELETE FROM task_files WHERE id = ?');
    $stmt->bind_param('i', $file_id);
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode(['status' => 'success', 'message' => 'File deleted successfully']);
    } else {
        $stmt->close();
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete file from database']);
    }
}
