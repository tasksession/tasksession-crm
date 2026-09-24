<?php
/*
================================================================================
  Task activity audit helper (task_activities)
================================================================================
*/

/**
 * Human-readable label for a kanban/default task status key.
 */
function task_activity_status_label($statusKey)
{
    global $lang;
    $s = strtolower(trim((string)$statusKey));
    $map = [
        'todo' => $lang['To Do'] ?? 'To Do',
        'inprogress' => $lang['In Progress'] ?? 'In Progress',
        'review' => $lang['Review'] ?? 'Review',
        'done' => $lang['Done'] ?? 'Done',
    ];
    if (isset($map[$s])) {
        return $map[$s];
    }
    return $s !== '' ? ucfirst($s) : ($lang['Unknown'] ?? 'Unknown');
}

/**
 * Record an activity row for a task (mirrors lead_activities pattern).
 *
 * @return bool
 */
function recordTaskActivity($taskId, $actorUserId, $eventType, $title, $message = null)
{
    global $connect;
    $taskId = (int)$taskId;
    if ($taskId <= 0 || !($connect instanceof mysqli)) {
        return false;
    }

    $actorUserId = (int)$actorUserId;
    $eventType = trim((string)$eventType);
    $title = trim((string)$title);
    $message = $message === null ? '' : (string)$message;

    if ($eventType === '' || $title === '') {
        return false;
    }

    $tblRes = mysqli_query($connect, "SHOW TABLES LIKE 'task_activities'");
    if (!$tblRes || !mysqli_fetch_assoc($tblRes)) {
        return false;
    }

    $stmt = $connect->prepare('INSERT INTO task_activities (task_id, actor_user_id, event_type, event_title, event_message) VALUES (?, ?, ?, ?, ?)');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('iisss', $taskId, $actorUserId, $eventType, $title, $message);
    $ok = $stmt->execute();
    $stmt->close();
    return (bool)$ok;
}
