<?php
header('Content-Type: application/json');
ob_start();
require_once("lib-initialize.php");
require_once("calendar_event.php");

if (!($session->isLoggedIn())) {
    echo json_encode(['status' => 'error', 'error' => 'Authentication required']);
    exit;
}

$eventId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$userId = (int)$session->userId;
if ($eventId <= 0) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid event ID']);
    exit;
}

$event = CalendarEvent::findEventById($eventId, $userId);
if (!$event) {
    echo json_encode(['status' => 'error', 'error' => 'Event not found']);
    exit;
}

$participants = [];
if (!empty($event['participants_json'])) {
    $decoded = json_decode((string)$event['participants_json'], true);
    if (is_array($decoded)) {
        foreach ($decoded as $participant) {
            if (is_array($participant)) {
                $pid = isset($participant['id']) ? (int)$participant['id'] : 0;
                $pname = trim((string)($participant['name'] ?? ''));
                if ($pid > 0) {
                    $u = User::findById($pid);
                    if ($u) {
                        $participants[] = [
                            'id' => (int)$u->id,
                            'name' => trim((string)($u->firstName ?? '')),
                            'image' => getProfilePicUrl('', 30, 30),
                            'avatar_html' => getUserAvatarHtml((int)$u->id, $u->firstName ?? '', '', 30, 30, 'rounded-circle', $u->firstName ?? '')
                        ];
                        continue;
                    }
                }
                if ($pname !== '') {
                    $participants[] = ['id' => 0, 'name' => $pname];
                }
            } else {
                $pname = trim((string)$participant);
                if ($pname !== '') {
                    $participants[] = ['id' => 0, 'name' => $pname];
                }
            }
        }
    }
}

echo json_encode([
    'status' => 'ok',
    'event' => [
        'id' => (int)$event['id'],
        'title' => (string)($event['title'] ?? ''),
        'description' => (string)($event['description'] ?? ''),
        'event_date' => (string)($event['event_date'] ?? ''),
        'start_datetime' => (string)($event['start_datetime'] ?? ''),
        'end_datetime' => (string)($event['end_datetime'] ?? ''),
        'location_label' => (string)($event['location_label'] ?? ''),
        'team_label' => (string)($event['team_label'] ?? ''),
        'participants_json' => (string)($event['participants_json'] ?? '')
    ],
    'participants' => $participants
]);
exit;
?>
