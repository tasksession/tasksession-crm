<?php
/**
 * Subtasks API - New separate table implementation
 * Handles CRUD operations for sub-tasks
 */

// Log errors; do not emit to response body (JSON must stay valid).
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Start output buffering
ob_start();

require_once("../includes/lib-initialize.php");
require_once("../includes/task.php");
require_once("../includes/projects.php");
require_once("../includes/permissions.php");
require_once("../includes/csrf-middleware.php");
require_once("../includes/notification_helper.php");
require_once("../includes/task_permission.php");

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if(!($session->isLoggedIn())){
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}
ensure_user_permissions($connect);
csrf_require_for_request('json');

$method = $_SERVER['REQUEST_METHOD'];

/**
 * PUT body includes editable fields other than status-only toggle.
 */
function subtaskPutMutatesEditableFields(array $input) {
    return isset($input['title'])
        || isset($input['description'])
        || array_key_exists('assigned_to', $input)
        || isset($input['due_date'])
        || isset($input['start_date']);
}

/**
 * Admin: full access. Staff: task_edit/task_delete + must be sub-task creator. Client: TaskPermission + must be sub-task creator.
 */
function canMutateSubtaskRow($subtaskId, $isDelete) {
    global $session;
    $accountStatus = isset($_SESSION['accountStatus']) ? (int)$_SESSION['accountStatus'] : 0;
    $userId = isset($_SESSION['userId']) ? (int)$_SESSION['userId'] : 0;

    if ($accountStatus === 1) {
        return true;
    }

    global $database;
    $res = $database->query("SELECT creator_id, user_id FROM subtasks WHERE id = " . (int)$subtaskId . " LIMIT 1");
    $row = $res ? $database->fetchArray($res) : null;
    $creatorId = 0;
    if ($row) {
        $creatorId = isset($row['creator_id']) ? (int)$row['creator_id'] : 0;
        if ($creatorId <= 0 && isset($row['user_id'])) {
            $creatorId = (int)$row['user_id'];
        }
    }

    if ($accountStatus === 3) {
        if ($isDelete) {
            if (!has_permission('task_delete')) {
                return false;
            }
        } else {
            if (!has_permission('task_edit')) {
                return false;
            }
        }
        return $creatorId > 0 && $creatorId === $userId;
    }

    if ($accountStatus === 2) {
        $tp = TaskPermission::getOrCreate($userId);
        if (!$tp) {
            return false;
        }
        $allowed = $isDelete ? (bool)$tp->can_delete_task : (bool)$tp->can_update_task;
        if (!$allowed) {
            return false;
        }
        return $creatorId > 0 && $creatorId === $userId;
    }

    return false;
}

function canAccessParentTask($taskId) {
    global $session;
    $task = Task::findById((int)$taskId);
    if (!$task) {
        return false;
    }
    $userId = (int)$session->userId;
    $accountStatus = isset($_SESSION['accountStatus']) ? (int)$_SESSION['accountStatus'] : 0;

    if ($accountStatus === 1) {
        return true;
    }
    if ($accountStatus === 2) {
        return $task->isClientAssociated($userId);
    }
    if ($accountStatus === 3) {
        return staff_can_view_task($task, $userId);
    }
    return false;
}

function getParentTaskIdBySubtaskId($subtaskId) {
    global $database;
    $res = $database->query("SELECT parent_task_id FROM subtasks WHERE id = " . (int)$subtaskId . " LIMIT 1");
    $row = $res ? $database->fetchArray($res) : null;
    return $row ? (int)$row['parent_task_id'] : 0;
}

/** @return array<string, string>|null */
function getSubtaskRowById($subtaskId) {
    global $database;
    $res = $database->query(
        "SELECT title, status, assigned_to, creator_id, user_id FROM subtasks WHERE id = " . (int)$subtaskId . " LIMIT 1"
    );
    return $res ? $database->fetchArray($res) : null;
}

function isSubtaskDoneStatus($status) {
    $s = strtolower(trim((string)$status));
    return $s === 'done' || $s === 'completed';
}

/**
 * Truncate for activity message (UTF-8 safe when mbstring available).
 */
function subtask_activity_snippet($text, $maxLen = 120) {
    $text = trim((string)$text);
    if ($text === '') {
        return '';
    }
    if ($maxLen <= 0) {
        return '';
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text, 'UTF-8') > $maxLen) {
            return mb_substr($text, 0, $maxLen, 'UTF-8');
        }
    } elseif (strlen($text) > $maxLen) {
        return substr($text, 0, $maxLen);
    }
    return $text;
}

/** Plain text only: strip tags, drop dangerous control chars, cap length (safe for UI text / XSS storage). */
function sanitize_subtask_text_field($value, $maxLen) {
    if ($value === null) {
        return '';
    }
    $value = is_string($value) ? $value : (string)$value;
    $value = strip_tags($value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
    $value = trim($value);
    if ($maxLen <= 0) {
        return '';
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($value, 'UTF-8') > $maxLen) {
            $value = mb_substr($value, 0, $maxLen, 'UTF-8');
        }
    } elseif (strlen($value) > $maxLen) {
        $value = substr($value, 0, $maxLen);
    }
    return $value;
}

function sanitize_subtask_title_input($value) {
    return sanitize_subtask_text_field($value, 500);
}

function sanitize_subtask_description_input($value) {
    return sanitize_subtask_text_field($value, 65535);
}

function sanitize_subtask_status_input($value) {
    $v = is_string($value) ? strtolower(trim($value)) : 'todo';
    $allowed = ['todo', 'done', 'completed', 'inprogress', 'review'];
    return in_array($v, $allowed, true) ? $v : 'todo';
}

/** Returns Y-m-d or null if invalid / empty. */
function sanitize_subtask_date_input($value) {
    if ($value === null || $value === '') {
        return null;
    }
    $s = is_string($value) ? trim($value) : '';
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? $s : null;
}

try {
    switch ($method) {
        case 'GET':
            handleGetRequest();
            break;
        case 'POST':
            handlePostRequest();
            break;
        case 'PUT':
            handlePutRequest();
            break;
        case 'DELETE':
            handleDeleteRequest();
            break;
        default:
            throw new Exception('Unsupported HTTP method');
    }
} catch (Exception $e) {
    ob_end_clean();
    error_log('Sub-task API error: ' . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

function handleGetRequest() {
    $parentId = isset($_GET['parent_task_id']) ? (int)$_GET['parent_task_id'] : 0;
    
    if ($parentId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid parent task ID']);
        exit;
    }
    if (!canAccessParentTask($parentId)) {
        echo json_encode(['status' => 'error', 'message' => 'Access denied']);
        exit;
    }
    
    $subtasks = getSubTasks($parentId);
    ob_end_clean();
    echo json_encode(['status' => 'success', 'subtasks' => $subtasks]);
}

function handlePostRequest() {
    $raw = function_exists('csrf_request_body_raw') ? csrf_request_body_raw() : file_get_contents('php://input');
    $input = json_decode($raw, true);
    
    if (!$input || !isset($input['parent_task_id'])) {
        throw new Exception('Missing required field: parent_task_id');
    }
    if (!canAccessParentTask((int)$input['parent_task_id'])) {
        throw new Exception('Access denied');
    }
    
    $subtaskId = createSubTask($input);
    $actorId = isset($_SESSION['userId']) ? (int)$_SESSION['userId'] : 0;
    $plainTitle = sanitize_subtask_title_input($input['title'] ?? '');
    try {
        global $lang;
        $parentId = (int)$input['parent_task_id'];
        $st = sanitize_subtask_status_input($input['status'] ?? 'todo');
        $stKey = ($st === 'completed' || $st === 'done') ? 'done' : $st;
        $statusLabel = function_exists('task_activity_status_label') ? task_activity_status_label($stKey) : ucfirst($stKey);
        $snippet = subtask_activity_snippet($plainTitle, 120);
        $msg = $snippet !== '' ? ($snippet . ' · ' . $statusLabel) : $statusLabel;
        $evtTitle = isset($lang['Sub-task created']) ? (string)$lang['Sub-task created'] : 'Sub-task created';
        recordTaskActivity($parentId, $actorId, 'subtask_created', $evtTitle, $msg);
    } catch (Throwable $e) {
        error_log('recordTaskActivity (subtask create): ' . $e->getMessage());
    }

    $assigneeNotify = 0;
    if (isset($input['assigned_to']) && $input['assigned_to'] !== '' && $input['assigned_to'] !== null) {
        $assigneeNotify = (int)$input['assigned_to'];
    }
    try {
        NotificationHelper::subtaskNotifyAssignee(
            (int)$input['parent_task_id'],
            $plainTitle,
            $assigneeNotify,
            $actorId,
            'created'
        );
    } catch (Throwable $e) {
        error_log('subtaskNotifyAssignee (create): ' . $e->getMessage());
    }

    ob_end_clean();
    echo json_encode(['status' => 'ok', 'subtask_id' => $subtaskId, 'message' => 'Sub-task created successfully']);
}

function handlePutRequest() {
    $raw = function_exists('csrf_request_body_raw') ? csrf_request_body_raw() : file_get_contents('php://input');
    $input = json_decode($raw, true);
    
    if (!$input || !isset($input['id'])) {
        throw new Exception('Missing required field: id');
    }
    $parentTaskId = getParentTaskIdBySubtaskId((int)$input['id']);
    if ($parentTaskId <= 0 || !canAccessParentTask($parentTaskId)) {
        throw new Exception('Access denied');
    }
    $subtaskId = (int)$input['id'];
    $rowBefore = getSubtaskRowById($subtaskId);
    if (subtaskPutMutatesEditableFields($input) && !canMutateSubtaskRow($subtaskId, false)) {
        throw new Exception('Permission denied');
    }
    updateSubTask($subtaskId, $input);
    $actorId = isset($_SESSION['userId']) ? (int)$_SESSION['userId'] : 0;
    if ($rowBefore && isset($input['status'])) {
        try {
            global $lang;
            $old = strtolower(trim((string)($rowBefore['status'] ?? '')));
            $new = strtolower(sanitize_subtask_status_input($input['status']));
            $doneOld = isSubtaskDoneStatus($old);
            $doneNew = isSubtaskDoneStatus($new);
            if ($old !== $new) {
                $titleSnippet = subtask_activity_snippet((string)($rowBefore['title'] ?? ''), 120);
                if ($doneNew && !$doneOld) {
                    $evtTitle = isset($lang['Sub-task completed']) ? (string)$lang['Sub-task completed'] : 'Sub-task completed';
                    $msg = $titleSnippet !== '' ? $titleSnippet : (isset($lang['Done']) ? (string)$lang['Done'] : 'Done');
                    recordTaskActivity($parentTaskId, $actorId, 'subtask_completed', $evtTitle, $msg);
                    $rowAfter = getSubtaskRowById($subtaskId);
                    $assigneeComplete = 0;
                    if ($rowAfter && isset($rowAfter['assigned_to']) && $rowAfter['assigned_to'] !== '' && $rowAfter['assigned_to'] !== null) {
                        $assigneeComplete = (int)$rowAfter['assigned_to'];
                    }
                    $titleForNotify = $rowAfter ? sanitize_subtask_title_input((string)($rowAfter['title'] ?? '')) : '';
                    try {
                        NotificationHelper::subtaskNotifyAssignee(
                            $parentTaskId,
                            $titleForNotify,
                            $assigneeComplete,
                            $actorId,
                            'completed'
                        );
                        $subtaskCreatorNotify = 0;
                        if ($rowAfter) {
                            $subtaskCreatorNotify = isset($rowAfter['creator_id']) ? (int)$rowAfter['creator_id'] : 0;
                            if ($subtaskCreatorNotify <= 0 && isset($rowAfter['user_id'])) {
                                $subtaskCreatorNotify = (int)$rowAfter['user_id'];
                            }
                        }
                        NotificationHelper::subtaskNotifySubtaskCreatorOnComplete(
                            $parentTaskId,
                            $titleForNotify,
                            $subtaskCreatorNotify,
                            $assigneeComplete,
                            $actorId
                        );
                        NotificationHelper::subtaskNotifyParentCreatorOnComplete(
                            $parentTaskId,
                            $titleForNotify,
                            $actorId
                        );
                    } catch (Throwable $ne) {
                        error_log('subtaskNotifyAssignee (complete): ' . $ne->getMessage());
                    }
                } elseif (!$doneNew && $doneOld) {
                    $evtTitle = isset($lang['Sub-task reopened']) ? (string)$lang['Sub-task reopened'] : 'Sub-task reopened';
                    $fromLabel = function_exists('task_activity_status_label') ? task_activity_status_label('done') : 'Done';
                    $toLabel = function_exists('task_activity_status_label') ? task_activity_status_label($new) : ucfirst($new);
                    $msg = ($titleSnippet !== '' ? ($titleSnippet . ' · ') : '') . $fromLabel . ' -> ' . $toLabel;
                    recordTaskActivity($parentTaskId, $actorId, 'subtask_reopened', $evtTitle, $msg);
                }
            }
        } catch (Throwable $e) {
            error_log('recordTaskActivity (subtask update): ' . $e->getMessage());
        }
    }
    ob_end_clean();
    echo json_encode(['status' => 'ok', 'message' => 'Sub-task updated successfully']);
}

function handleDeleteRequest() {
    $raw = function_exists('csrf_request_body_raw') ? csrf_request_body_raw() : file_get_contents('php://input');
    $input = json_decode($raw, true);
    
    if (!$input || !isset($input['id'])) {
        throw new Exception('Invalid data or missing ID');
    }
    $parentTaskId = getParentTaskIdBySubtaskId((int)$input['id']);
    if ($parentTaskId <= 0 || !canAccessParentTask($parentTaskId)) {
        throw new Exception('Access denied');
    }
    $subtaskId = (int)$input['id'];
    if (!canMutateSubtaskRow($subtaskId, true)) {
        throw new Exception('Permission denied');
    }
    $rowBeforeDelete = getSubtaskRowById($subtaskId);
    $actorId = isset($_SESSION['userId']) ? (int)$_SESSION['userId'] : 0;
    try {
        global $lang;
        $snippet = subtask_activity_snippet((string)($rowBeforeDelete['title'] ?? ''), 120);
        $evtTitle = isset($lang['Sub-task deleted']) ? (string)$lang['Sub-task deleted'] : 'Sub-task deleted';
        $msg = $snippet !== '' ? $snippet : '';
        recordTaskActivity($parentTaskId, $actorId, 'subtask_deleted', $evtTitle, $msg);
    } catch (Throwable $e) {
        error_log('recordTaskActivity (subtask delete): ' . $e->getMessage());
    }
    deleteSubTask($subtaskId);
    ob_end_clean();
    echo json_encode(['status' => 'ok', 'message' => 'Sub-task deleted successfully']);
}

function getSubTasks($parentId) {
    global $database;
    
    $query = "SELECT st.*, 
                     u.firstName as assigned_user_name,
                     pp.filename as assigned_user_image
              FROM subtasks st
              LEFT JOIN users u ON u.id = st.assigned_to
              LEFT JOIN profile_pics pp ON u.id = pp.fkUserId
              WHERE st.parent_task_id = " . (int)$parentId . " 
              ORDER BY st.position ASC, st.created_at ASC";
    
    $result = $database->query($query);
    $subtasks = [];
    
    while ($row = $database->fetchArray($result)) {
        // Build the full image URL if image exists
        $imageUrl = '';
        if (!empty($row['assigned_user_image'])) {
            global $url;
            $imageUrl = $url . 'includes/thumbnail.php?src=' . urlencode($url . 'uploads/profile-pics/' . $row['assigned_user_image']) . '&w=24&h=24';
        }
        
        $creatorId = isset($row['creator_id']) ? (int)$row['creator_id'] : 0;
        if ($creatorId <= 0 && !empty($row['user_id'])) {
            $creatorId = (int)$row['user_id'];
        }

        $subtasks[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'status' => $row['status'],
            'completed' => $row['status'] === 'done' || $row['status'] === 'completed',
            'creator_id' => $creatorId,
            'assigned_to' => $row['assigned_to'],
            'assigned_user_name' => $row['assigned_user_name'] ? trim($row['assigned_user_name']) : '',
            'assigned_user_image' => $imageUrl,
            'due_date' => $row['due_date'],
            'start_date' => $row['start_date'],
            'created_at' => $row['created_at'],
            'completed_at' => $row['completed_at']
        ];
    }
    
    return $subtasks;
}

function createSubTask($data) {
    global $database;
    
    $parentTaskId = (int)$data['parent_task_id'];
    $title = sanitize_subtask_title_input($data['title'] ?? '');
    if ($title === '') {
        throw new Exception('Title is required');
    }
    $title = $database->escapeValue($title);
    $description = $database->escapeValue(sanitize_subtask_description_input($data['description'] ?? ''));
    $status = $database->escapeValue(sanitize_subtask_status_input($data['status'] ?? 'todo'));
    $dueSql = 'NULL';
    $dueSan = sanitize_subtask_date_input($data['due_date'] ?? null);
    if ($dueSan !== null) {
        $dueSql = "'" . $database->escapeValue($dueSan) . "'";
    }
    $startSql = 'NULL';
    $startSan = sanitize_subtask_date_input($data['start_date'] ?? null);
    if ($startSan !== null) {
        $startSql = "'" . $database->escapeValue($startSan) . "'";
    }
    $assignedTo = 'NULL';
    if (isset($data['assigned_to']) && $data['assigned_to'] !== '' && $data['assigned_to'] !== null) {
        $aid = (int)$data['assigned_to'];
        if ($aid > 0) {
            $assignedTo = "'" . $aid . "'";
        }
    }
    $creatorId = (int)$_SESSION['userId'];
    $userId = (int)$_SESSION['userId'];
    
    $query = "INSERT INTO subtasks (parent_task_id, title, description, status, due_date, start_date, assigned_to, creator_id, user_id, position) 
              VALUES ($parentTaskId, '$title', '$description', '$status', $dueSql, $startSql, $assignedTo, $creatorId, $userId, 0)";
    
    $result = $database->query($query);
    
    if (!$result) {
        throw new Exception('Database query failed');
    }
    
    return $database->insertId();
}

function updateSubTask($subtaskId, $data) {
    global $database;
    
    $updates = [];
    
    if (isset($data['title'])) {
        $titleSan = sanitize_subtask_title_input($data['title']);
        if ($titleSan === '') {
            throw new Exception('Title cannot be empty');
        }
        $updates[] = "title = '" . $database->escapeValue($titleSan) . "'";
    }
    
    if (isset($data['description'])) {
        $updates[] = "description = '" . $database->escapeValue(sanitize_subtask_description_input($data['description'])) . "'";
    }
    
    if (isset($data['status'])) {
        $stSan = sanitize_subtask_status_input($data['status']);
        $updates[] = "status = '" . $database->escapeValue($stSan) . "'";
        if ($stSan === 'done' || $stSan === 'completed') {
            $updates[] = "completed_at = NOW()";
        } else {
            $updates[] = "completed_at = NULL";
        }
    }
    
    if (isset($data['due_date'])) {
        if ($data['due_date'] === '' || $data['due_date'] === null) {
            $updates[] = "due_date = NULL";
        } else {
            $dueSan = sanitize_subtask_date_input($data['due_date']);
            if ($dueSan !== null) {
                $updates[] = "due_date = '" . $database->escapeValue($dueSan) . "'";
            }
        }
    }
    
    if (isset($data['start_date'])) {
        if ($data['start_date'] === '' || $data['start_date'] === null) {
            $updates[] = "start_date = NULL";
        } else {
            $startSan = sanitize_subtask_date_input($data['start_date']);
            if ($startSan !== null) {
                $updates[] = "start_date = '" . $database->escapeValue($startSan) . "'";
            }
        }
    }
    
    if (isset($data['assigned_to'])) {
        if ($data['assigned_to'] === '' || $data['assigned_to'] === null) {
            $updates[] = "assigned_to = NULL";
        } else {
            $aid = (int)$data['assigned_to'];
            $updates[] = $aid > 0 ? "assigned_to = '" . $aid . "'" : "assigned_to = NULL";
        }
    }
    
    if (empty($updates)) {
        throw new Exception('No fields to update');
    }
    
    $query = "UPDATE subtasks SET " . implode(', ', $updates) . " WHERE id = " . (int)$subtaskId;
    $result = $database->query($query);
    
    if (!$result) {
        throw new Exception('Update failed');
    }
}

function deleteSubTask($subtaskId) {
    global $database;
    
    $query = "DELETE FROM subtasks WHERE id = " . (int)$subtaskId;
    $result = $database->query($query);
    
    if (!$result) {
        throw new Exception('Delete failed');
    }
}

