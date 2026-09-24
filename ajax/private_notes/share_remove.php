<?php
require_once("../../includes/lib-initialize.php");
require_once("../../includes/private_notes/permissions.php");
require_once("../../includes/csrf-middleware.php");

header('Content-Type: application/json; charset=utf-8');

if (!$session->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
    exit;
}
csrf_require_for_request('json');

$userId = (int)$session->userId;
$input = json_decode(csrf_request_body_raw(), true);
if (!is_array($input)) {
    $input = [];
}
$noteId = (int)($input['note_id'] ?? 0);
$targetUserId = (int)($input['shared_with_user_id'] ?? 0);

if ($noteId <= 0 || $targetUserId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Invalid request']);
    exit;
}

$ctx = privateNotesGetAccessContext($database, $noteId, $userId);
if (empty($ctx['is_owner'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => 'Only note owner can remove shares']);
    exit;
}

$sql = "DELETE FROM private_note_shares WHERE note_id = {$noteId} AND shared_with_user_id = {$targetUserId}";
$ok = $database->query($sql);
if (!$ok) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Failed to remove share']);
    exit;
}

echo json_encode(['status' => 'ok']);
