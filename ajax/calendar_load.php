<?php
ob_start();
require_once("../includes/lib-initialize.php");
require_once("../includes/calendar_event.php");

header('Content-Type: application/json');

if (!$session->isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

$userId = (int)$session->userId;
$month = trim((string)($_GET['month'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
$monthStart = $month . "-01";
$monthEnd = date('Y-m-t', strtotime($monthStart));

if (!CalendarEvent::moduleReady()) {
    echo json_encode(['status' => 'error', 'message' => 'calendar_module_not_ready']);
    exit;
}

$tasks = CalendarEvent::listScheduledTasksByMonth($userId, $monthStart, $monthEnd, false);
$events = CalendarEvent::listEventsByMonth($userId, $monthStart, $monthEnd);
$waiting = CalendarEvent::listWaitingItems($userId);

echo json_encode([
    'status' => 'ok',
    'tasks' => $tasks,
    'events' => $events,
    'waiting' => $waiting
]);
exit;
?>
