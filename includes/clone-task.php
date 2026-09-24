<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : includes/clone-task.php
   Purpose : Centralized handler for task cloning functionality
 ================================================================================
 */
require_once("lib-initialize.php");
require_once("permissions.php");

// Check if user is logged in
if (!$session->isLoggedIn()) {
    die(json_encode(['status' => 'error', 'error' => 'Not logged in']));
}
ensure_user_permissions($connect);

// Get task ID and redirect URL from request
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'all-tasks.php';

if ($id <= 0) {
    $separator = (strpos($redirect, '?') !== false) ? '&' : '?';
    header("Location: {$redirect}{$separator}message=clone_fail");
    exit;
}

// Load task
$task = Task::findById($id);
if (!$task) {
    $separator = (strpos($redirect, '?') !== false) ? '&' : '?';
    header("Location: {$redirect}{$separator}message=clone_fail");
    exit;
}

// Enforce backend authorization for clone action
$userType = isset($_SESSION['accountStatus']) ? (int) $_SESSION['accountStatus'] : 0;
$canClone = false;
if ($userType === 1) {
    $canClone = true;
} elseif ($userType === 3) {
    $canClone = function_exists('has_permission') && has_permission('task_duplicate');
}

if (!$canClone) {
    $separator = (strpos($redirect, '?') !== false) ? '&' : '?';
    header("Location: {$redirect}{$separator}message=clone_unauthorized");
    exit;
}

// Clone the task
$cloneOptions = [
    'current_user_id' => (int)$session->userId,
    'user_id' => (int)$session->userId,
    'creator_id' => (int)$session->userId
];

// If clone is coming back to calendar My Schedule view,
// force current user assignment so the cloned task appears immediately.
$redirectPath = (string)parse_url($redirect, PHP_URL_PATH);
$redirectQueryString = (string)parse_url($redirect, PHP_URL_QUERY);
$redirectQuery = [];
if ($redirectQueryString !== '') {
    parse_str($redirectQueryString, $redirectQuery);
}
$isCalendarRedirect = stripos($redirectPath, 'calendar.php') !== false;
$isMyScheduleMode = !isset($redirectQuery['all_tasks']) || (string)$redirectQuery['all_tasks'] !== '1';
$calendarDate = null;

if ($isCalendarRedirect && $isMyScheduleMode) {
    $cloneOptions['force_assign_current_user'] = true;
    if (!empty($redirectQuery['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$redirectQuery['date'])) {
        $cloneOptions['default_date'] = (string)$redirectQuery['date'];
        $calendarDate = (string)$redirectQuery['date'];
    }
}

$newTask = Task::createFromClone($task, $cloneOptions);
if ($newTask) {
    if ($isCalendarRedirect) {
        if ($calendarDate === null) {
            if (!empty($redirectQuery['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$redirectQuery['date'])) {
                $calendarDate = (string)$redirectQuery['date'];
            } elseif (!empty($cloneOptions['default_date'])) {
                $calendarDate = (string)$cloneOptions['default_date'];
            } else {
                $calendarDate = date('Y-m-d');
            }
        }

        require_once("calendar_event.php");
        if (class_exists('CalendarEvent') && CalendarEvent::moduleReady()) {
            CalendarEvent::saveTaskPlacement((int)$newTask->id, (int)$session->userId, $calendarDate, false, 0);
        }
    }

    $separator = (strpos($redirect, '?') !== false) ? '&' : '?';
    header("Location: {$redirect}{$separator}message=clone_success");
    exit;
} else {
    $separator = (strpos($redirect, '?') !== false) ? '&' : '?';
    header("Location: {$redirect}{$separator}message=clone_fail");
    exit;
} 