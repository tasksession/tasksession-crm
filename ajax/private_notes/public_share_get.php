<?php
require_once("../../includes/lib-initialize.php");
require_once("../../includes/private_notes/permissions.php");

header('Content-Type: application/json; charset=utf-8');

if (!$session->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
    exit;
}

$userId = (int)$session->userId;
$noteId = isset($_GET['note_id']) ? (int)$_GET['note_id'] : 0;
if ($noteId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Invalid note id']);
    exit;
}

$ctx = privateNotesGetAccessContext($database, $noteId, $userId);
if (empty($ctx['is_owner'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => 'Only note owner can manage public share']);
    exit;
}

$sql = "SELECT public_share_enabled, public_share_token, public_share_permission,
               created_at, updated_at, public_share_updated_at
        FROM private_notes
        WHERE id = {$noteId}
        LIMIT 1";
$res = $database->query($sql);
$row = $res ? $database->fetchArray($res) : null;
if (!$row) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'error' => 'Note not found']);
    exit;
}

$enabled = (int)($row['public_share_enabled'] ?? 0) === 1;
$token = (string)($row['public_share_token'] ?? '');
$perm = (string)($row['public_share_permission'] ?? 'view');
echo json_encode([
    'status' => 'ok',
    'data' => [
        'general_access' => $enabled ? 'anyone_link' : 'restricted',
        'role' => $perm === 'edit' ? 'editor' : 'viewer',
        'token' => $token,
        'public_url' => ($enabled && $token !== '') ? privateNotesBuildPublicUrl($token) : '',
        'created_at' => (string)($row['created_at'] ?? ''),
        'updated_at' => (string)($row['updated_at'] ?? ''),
        'public_share_updated_at' => (string)($row['public_share_updated_at'] ?? '')
    ]
]);
