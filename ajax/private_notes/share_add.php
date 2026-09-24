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
$permission = strtolower(trim((string)($input['permission'] ?? 'view')));

if ($noteId <= 0 || $targetUserId <= 0 || ($permission !== 'view' && $permission !== 'edit')) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Invalid request']);
    exit;
}

$ctx = privateNotesGetAccessContext($database, $noteId, $userId);
if (empty($ctx['is_owner'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => 'Only note owner can share']);
    exit;
}
if ($targetUserId === $userId) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'You already own this note']);
    exit;
}

$targetSql = "SELECT id FROM users WHERE id = {$targetUserId} LIMIT 1";
$targetRes = $database->query($targetSql);
$target = $targetRes ? $database->fetchArray($targetRes) : null;
if (!$target || empty($target['id'])) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'error' => 'User not found']);
    exit;
}

if (!privateNotesCanShareWithUser($database, $userId, $targetUserId)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => 'User is not allowed for sharing']);
    exit;
}

$ownerUserId = (int)$ctx['owner_user_id'];
$permissionEsc = $database->escapeValue($permission);
$sql = "INSERT INTO private_note_shares (note_id, owner_user_id, shared_with_user_id, permission)
        VALUES ({$noteId}, {$ownerUserId}, {$targetUserId}, '{$permissionEsc}')
        ON DUPLICATE KEY UPDATE permission = VALUES(permission)";
$ok = $database->query($sql);
if (!$ok) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Failed to update share']);
    exit;
}

echo json_encode(['status' => 'ok']);
