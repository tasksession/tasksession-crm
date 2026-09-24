<?php
ob_start();
require_once("../includes/lib-initialize.php");
require_once("../includes/calendar_event.php");
$__gcalSvc = __DIR__ . '/../includes/google_calendar_service.php';
if (is_file($__gcalSvc)) { require_once $__gcalSvc; }

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

$title = trim((string)($_POST['title'] ?? ''));
$eventDate = trim((string)($_POST['event_date'] ?? ''));
$startTime = trim((string)($_POST['start_time'] ?? ''));
$endTime = trim((string)($_POST['end_time'] ?? ''));
$eventId = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
$location = trim((string)($_POST['location_label'] ?? ''));
$team = trim((string)($_POST['team_label'] ?? ''));
$participantsRaw = trim((string)($_POST['participants'] ?? ''));
$description = sanitize_tinymce_content((string)($_POST['description'] ?? ''));

if ($title === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
    echo json_encode(['status' => 'error', 'message' => 'invalid_input']);
    exit;
}

$startDateTime = null;
$endDateTime = null;
if ($startTime !== '' && preg_match('/^\d{2}:\d{2}$/', $startTime)) {
    $startDateTime = $eventDate . ' ' . $startTime . ':00';
}
if ($endTime !== '' && preg_match('/^\d{2}:\d{2}$/', $endTime)) {
    $endDateTime = $eventDate . ' ' . $endTime . ':00';
}
if ($startDateTime && $endDateTime && strtotime($endDateTime) <= strtotime($startDateTime)) {
    echo json_encode(['status' => 'error', 'message' => 'end_before_start']);
    exit;
}

$participants = [];
if ($participantsRaw !== '') {
    $parts = explode(',', $participantsRaw);
    foreach ($parts as $part) {
        $v = trim($part);
        if ($v !== '') {
            $participants[] = $v;
        }
    }
}

if ($eventId > 0) {
    $existingEvent = CalendarEvent::findEventById($eventId, $userId);
    if (!$existingEvent) {
        echo json_encode(['status' => 'error', 'message' => 'not_found_or_forbidden']);
        exit;
    }
    global $connect;
    $stmt = $connect->prepare("UPDATE calendar_events SET title = ?, description = ?, event_date = ?, start_datetime = ?, end_datetime = ?, location_label = ?, team_label = ?, participants_json = ?, sync_state = 'pending', updated_at = NOW() WHERE id = ? AND user_id = ?");
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'update_prepare_failed']);
        exit;
    }
    $participantsJson = json_encode($participants);
    $stmt->bind_param("ssssssssii", $title, $description, $eventDate, $startDateTime, $endDateTime, $location, $team, $participantsJson, $eventId, $userId);
    $okUpdate = $stmt->execute();
    $stmt->close();
    if (!$okUpdate) {
        echo json_encode(['status' => 'error', 'message' => 'update_failed']);
        exit;
    }
} else {
    $eventId = CalendarEvent::createEvent([
        'user_id' => $userId,
        'title' => $title,
        'description' => $description,
        'event_date' => $eventDate,
        'start_datetime' => $startDateTime,
        'end_datetime' => $endDateTime,
        'location_label' => $location,
        'team_label' => $team,
        'participants_json' => json_encode($participants),
        'is_waiting_list' => 0,
        'waiting_position' => 0
    ]);
}

if ($eventId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'save_failed']);
    exit;
}

$sync = ['status' => 'skipped'];
if (class_exists('GoogleCalendarService') && GoogleCalendarService::moduleReady()) {
    $eventRow = CalendarEvent::findEventById($eventId, $userId);
    if ($eventRow) {
        $push = GoogleCalendarService::pushEvent($userId, $eventRow);
        if ($push['ok']) {
            global $connect;
            $googleId = $push['data']['id'];
            $etag = isset($push['data']['etag']) ? $push['data']['etag'] : null;
            $calendarId = $push['calendar_id'];
            $stmt = $connect->prepare("UPDATE calendar_events SET google_event_id = ?, google_calendar_id = ?, google_etag = ?, sync_state = 'synced', last_synced_at = NOW() WHERE id = ? AND user_id = ?");
            if ($stmt) {
                $stmt->bind_param("sssii", $googleId, $calendarId, $etag, $eventId, $userId);
                $stmt->execute();
                $stmt->close();
            }
            GoogleCalendarService::logSync($userId, 'push', 'event', $eventId, $googleId, 'success', 'Event pushed to Google Calendar');
            $sync = ['status' => 'ok'];
        } else {
            GoogleCalendarService::logSync($userId, 'push', 'event', $eventId, null, 'error', $push['error'] ?? 'push_failed');
            $sync = ['status' => 'error'];
        }
    }
}

echo json_encode(['status' => 'ok', 'id' => $eventId, 'sync' => $sync]);
exit;
?>
