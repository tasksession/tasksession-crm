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
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Invalid payload']);
    exit;
}

$noteId = (int)($input['note_id'] ?? 0);
$generalAccess = trim((string)($input['general_access'] ?? 'restricted'));
$role = trim((string)($input['role'] ?? 'viewer'));
$rotate = !empty($input['rotate']);

if ($noteId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Invalid note id']);
    exit;
}
if ($generalAccess !== 'restricted' && $generalAccess !== 'anyone_link') {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'error' => 'Invalid general access']);
    exit;
}
if ($role !== 'viewer' && $role !== 'editor') {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'error' => 'Invalid role']);
    exit;
}

$ctx = privateNotesGetAccessContext($database, $noteId, $userId);
if (empty($ctx['is_owner'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => 'Only note owner can update public sharing']);
    exit;
}

$enabled = ($generalAccess === 'anyone_link') ? 1 : 0;
$permission = ($role === 'editor') ? 'edit' : 'view';
$noteSql = "SELECT public_share_token FROM private_notes WHERE id = {$noteId} LIMIT 1";
$noteRes = $database->query($noteSql);
$noteRow = $noteRes ? $database->fetchArray($noteRes) : null;
if (!$noteRow) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'error' => 'Note not found']);
    exit;
}

$token = (string)($noteRow['public_share_token'] ?? '');
if ($enabled === 1 && ($token === '' || $rotate)) {
    $token = privateNotesGeneratePublicToken();
}
if ($enabled === 0) {
    // keep token for quick re-enable; access still blocked by enabled flag
}

$tokenEsc = $database->escapeValue($token);
$permissionEsc = $database->escapeValue($permission);
$sql = "UPDATE private_notes
        SET public_share_enabled = {$enabled},
            public_share_token = " . ($token === '' ? "NULL" : "'{$tokenEsc}'") . ",
            public_share_permission = '{$permissionEsc}',
            public_share_updated_at = NOW()
        WHERE id = {$noteId}";
$ok = $database->query($sql);
if (!$ok) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Failed to update public share']);
    exit;
}

echo json_encode([
    'status' => 'ok',
    'data' => [
        'general_access' => $enabled === 1 ? 'anyone_link' : 'restricted',
        'role' => $permission === 'edit' ? 'editor' : 'viewer',
        'token' => $token,
        'public_url' => ($enabled === 1 && $token !== '') ? privateNotesBuildPublicUrl($token) : ''
    ]
]);
