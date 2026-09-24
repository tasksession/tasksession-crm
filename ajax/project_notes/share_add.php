<?php
require_once __DIR__ . '/ajax_json_bootstrap.php';
require_once __DIR__ . '/../../includes/lib-initialize.php';
require_once __DIR__ . '/../../includes/project_note_shares.php';
require_once __DIR__ . '/../../includes/private_notes/permissions.php';
require_once __DIR__ . '/../../includes/csrf-middleware.php';

if (!$session->isLoggedIn()) {
	project_notes_ajax_json_exit(['status' => 'error', 'error' => 'Unauthorized'], 401);
}
csrf_require_for_request('json');

$userId = (int) $session->userId;
$status = (int) ($_SESSION['accountStatus'] ?? 0);
$creatorType = $status === 3 ? 'staff' : ($status === 2 ? 'client' : 'admin');
$input = json_decode(csrf_request_body_raw(), true);
if (!is_array($input)) {
	$input = [];
}
$noteId = (int) ($input['project_note_id'] ?? 0);
$projectId = (int) ($input['project_id'] ?? 0);
$targetUserId = (int) ($input['shared_with_user_id'] ?? 0);
$permission = strtolower(trim((string) ($input['permission'] ?? 'view')));
if ($noteId <= 0 || $projectId <= 0 || $targetUserId <= 0 || !in_array($permission, ['view', 'edit'], true)) {
	project_notes_ajax_json_exit(['status' => 'error', 'error' => 'Invalid request'], 400);
}
project_note_shares_ensure_table($connect);
$n = project_note_share_get_note_for_creator($connect, $noteId, $projectId, $userId, $creatorType);
if (!$n) {
	project_notes_ajax_json_exit(['status' => 'error', 'error' => 'Only the note author can share'], 403);
}
if ($targetUserId === $userId) {
	project_notes_ajax_json_exit(['status' => 'error', 'error' => 'You cannot share with yourself'], 400);
}
if (function_exists('privateNotesCanShareWithUser') && !privateNotesCanShareWithUser($database, $userId, $targetUserId)) {
	project_notes_ajax_json_exit(['status' => 'error', 'error' => 'User is not allowed for sharing'], 403);
}

$ins = $connect->prepare(
	'INSERT INTO project_tab_note_shares (project_note_id, project_id, creator_user_id, shared_with_user_id, permission)
	VALUES (?, ?, ?, ?, ?)
	ON DUPLICATE KEY UPDATE permission = VALUES(permission), updated_at = CURRENT_TIMESTAMP'
);
$ins->bind_param('iiiis', $noteId, $projectId, $userId, $targetUserId, $permission);
if (!$ins->execute()) {
	$ins->close();
	project_notes_ajax_json_exit(['status' => 'error', 'error' => 'Failed to update share'], 500);
}
$ins->close();
project_notes_ajax_json_exit(['status' => 'ok']);
