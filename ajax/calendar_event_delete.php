<?php
ob_start();
require_once("../includes/lib-initialize.php");

header('Content-Type: application/json');

if (!$session->isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}
if (!validate_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
    echo json_encode(['status' => 'error', 'message' => 'invalid_csrf']);
    exit;
}

$eventId = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
$userId = (int)$session->userId;
if ($eventId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid event']);
    exit;
}

global $connect;
$stmt = $connect->prepare("DELETE FROM calendar_events WHERE id = ? AND user_id = ?");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Delete prepare failed']);
    exit;
}
$stmt->bind_param("ii", $eventId, $userId);
$stmt->execute();
$ok = $stmt->affected_rows > 0;
$stmt->close();

echo json_encode(['status' => $ok ? 'ok' : 'error']);
exit;
?>
