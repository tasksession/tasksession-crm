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
if (empty($ctx['can_view'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => 'Access denied']);
    exit;
}

$sql = "SELECT s.id, s.shared_with_user_id, s.permission, s.created_at, u.firstName, u.email
        FROM private_note_shares s
        LEFT JOIN users u ON u.id = s.shared_with_user_id
        WHERE s.note_id = {$noteId}
        ORDER BY s.created_at DESC";
$res = $database->query($sql);
$shares = [];
while ($res && ($row = $database->fetchArray($res))) {
    $shares[] = [
        'share_id' => (int)$row['id'],
        'shared_with_user_id' => (int)$row['shared_with_user_id'],
        'permission' => (string)$row['permission'],
        'created_at' => (string)$row['created_at'],
        'name' => (string)($row['firstName'] ?? ''),
        'email' => (string)($row['email'] ?? '')
    ];
}

$pubSql = "SELECT public_share_enabled, public_share_token, public_share_permission
           FROM private_notes
           WHERE id = {$noteId}
           LIMIT 1";
$pubRes = $database->query($pubSql);
$pubRow = $pubRes ? $database->fetchArray($pubRes) : null;
$pubEnabled = (int)($pubRow['public_share_enabled'] ?? 0) === 1;
$pubToken = (string)($pubRow['public_share_token'] ?? '');
$pubPerm = (string)($pubRow['public_share_permission'] ?? 'view');

echo json_encode([
    'status' => 'ok',
    'data' => [
        'shares' => $shares,
        'is_owner' => !empty($ctx['is_owner']),
        'permission' => (string)$ctx['permission'],
        'public_share' => [
            'general_access' => $pubEnabled ? 'anyone_link' : 'restricted',
            'role' => $pubPerm === 'edit' ? 'editor' : 'viewer',
            'token' => $pubToken,
            'public_url' => ($pubEnabled && $pubToken !== '') ? privateNotesBuildPublicUrl($pubToken) : ''
        ]
    ]
]);
