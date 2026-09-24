<?php
ob_start();
require_once("../includes/lib-initialize.php");
require_once("../includes/calendar_event.php");
$__gcalSvc = __DIR__ . '/../includes/google_calendar_service.php';
if (is_file($__gcalSvc)) { require_once $__gcalSvc; }
require_once("../includes/task.php");
require_once("../includes/permissions.php");

header('Content-Type: application/json');

if (!$session->isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}
if (!validate_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
    echo json_encode(['status' => 'error', 'message' => 'invalid_csrf']);
    exit;
}

$userId = (int)$session->userId;
if (!CalendarEvent::moduleReady()) {
    echo json_encode(['status' => 'error', 'message' => 'calendar_module_not_ready']);
    exit;
}

$itemType = trim((string)($_POST['item_type'] ?? ''));
$itemId = (int)($_POST['item_id'] ?? 0);
$targetDate = trim((string)($_POST['target_date'] ?? ''));
$isWaiting = isset($_POST['is_waiting_list']) && $_POST['is_waiting_list'] === '1';
$insertBeforeKey = trim((string)($_POST['insert_before_key'] ?? ''));
$insertAfterKey = trim((string)($_POST['insert_after_key'] ?? ''));
$dropPosition = isset($_POST['position']) ? (int)$_POST['position'] : 0;
$sourceDate = trim((string)($_POST['source_date'] ?? ''));
$sourceIsWaiting = isset($_POST['source_is_waiting']) && $_POST['source_is_waiting'] === '1';

if (!in_array($itemType, ['task', 'event'], true) || $itemId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'invalid_item']);
    exit;
}

if (!$isWaiting && ($targetDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate))) {
    echo json_encode(['status' => 'error', 'message' => 'invalid_date']);
    exit;
}

if ($itemType === 'task') {
    $task = Task::findById($itemId);
    if (!$task) {
        echo json_encode(['status' => 'error', 'message' => 'task_not_found']);
        exit;
    }
    $canManageTask = false;
    $accountStatus = (int)($_SESSION['accountStatus'] ?? 0);
    if ($accountStatus === 1) {
        $canManageTask = true;
    } else {
        if ($accountStatus === 3) {
            ensure_user_permissions($connect);
            $canManageTask = staff_can_view_task($task, $userId) || has_permission('task_edit');
        }
    }
    if (!$canManageTask) {
        echo json_encode(['status' => 'error', 'message' => 'forbidden']);
        exit;
    }
    if ($isWaiting) {
        $position = CalendarEvent::nextWaitingPosition($userId, 'task');
        $ok = CalendarEvent::saveTaskPlacement($itemId, $userId, null, true, $position);
        if ($ok && $sourceDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $sourceDate) && !$sourceIsWaiting) {
            CalendarEvent::compactDayColumn($userId, $sourceDate);
        }
    } else {
        $ok = CalendarEvent::applyDayDropOrdering(
            $userId,
            'task',
            $itemId,
            $targetDate,
            $insertBeforeKey,
            $insertAfterKey,
            $dropPosition,
            $sourceDate,
            $sourceIsWaiting
        );
    }
    if ($ok && !$isWaiting && class_exists('GoogleCalendarService') && GoogleCalendarService::moduleReady()) {
        $settings = GoogleCalendarService::getSettings();
        if (!empty($settings['task_sync_enabled'])) {
            $syncEventId = CalendarEvent::upsertTaskSyncEvent($itemId, $userId, $targetDate);
            if ($syncEventId > 0) {
                $syncEvent = CalendarEvent::findEventById($syncEventId, $userId);
                if ($syncEvent) {
                    $push = GoogleCalendarService::pushEvent($userId, $syncEvent);
                    if ($push['ok']) {
                        global $connect;
                        $googleId = $push['data']['id'];
                        $etag = isset($push['data']['etag']) ? $push['data']['etag'] : null;
                        $calendarId = $push['calendar_id'];
                        $stmt = $connect->prepare("UPDATE calendar_events SET google_event_id = ?, google_calendar_id = ?, google_etag = ?, sync_state = 'synced', last_synced_at = NOW() WHERE id = ? AND user_id = ?");
                        if ($stmt) {
                            $stmt->bind_param("sssii", $googleId, $calendarId, $etag, $syncEventId, $userId);
                            $stmt->execute();
                            $stmt->close();
                        }
                        GoogleCalendarService::logSync($userId, 'push', 'task', $itemId, $googleId, 'success', 'Task placement synced');
                    } else {
                        GoogleCalendarService::logSync($userId, 'push', 'task', $itemId, null, 'error', $push['error'] ?? 'push_failed');
                    }
                }
            }
        }
    }
    echo json_encode(['status' => $ok ? 'ok' : 'error']);
    exit;
}

$ownedEvent = CalendarEvent::findEventById($itemId, $userId);
if (!$ownedEvent) {
    echo json_encode(['status' => 'error', 'message' => 'forbidden']);
    exit;
}
if ($isWaiting) {
    $position = CalendarEvent::nextWaitingPosition($userId, 'event');
    $ok = CalendarEvent::updateEventPlacement($itemId, $userId, null, true, $position);
    if ($ok && $sourceDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $sourceDate) && !$sourceIsWaiting) {
        CalendarEvent::compactDayColumn($userId, $sourceDate);
    }
} else {
    $ok = CalendarEvent::applyDayDropOrdering(
        $userId,
        'event',
        $itemId,
        $targetDate,
        $insertBeforeKey,
        $insertAfterKey,
        $dropPosition,
        $sourceDate,
        $sourceIsWaiting
    );
}

if ($ok && class_exists('GoogleCalendarService') && GoogleCalendarService::moduleReady() && !$isWaiting) {
    $eventRow = CalendarEvent::findEventById($itemId, $userId);
    if ($eventRow) {
        $push = GoogleCalendarService::pushEvent($userId, $eventRow);
        if ($push['ok']) {
            global $connect;
            $googleId = $push['data']['id'];
            $etag = isset($push['data']['etag']) ? $push['data']['etag'] : null;
            $calendarId = $push['calendar_id'];
            $stmt = $connect->prepare("UPDATE calendar_events SET google_event_id = ?, google_calendar_id = ?, google_etag = ?, sync_state = 'synced', last_synced_at = NOW() WHERE id = ? AND user_id = ?");
            if ($stmt) {
                $stmt->bind_param("sssii", $googleId, $calendarId, $etag, $itemId, $userId);
                $stmt->execute();
                $stmt->close();
            }
            GoogleCalendarService::logSync($userId, 'push', 'event', $itemId, $googleId, 'success', 'Event placement synced');
        } else {
            GoogleCalendarService::logSync($userId, 'push', 'event', $itemId, null, 'error', $push['error'] ?? 'push_failed');
        }
    }
}

echo json_encode(['status' => $ok ? 'ok' : 'error']);
exit;
?>
