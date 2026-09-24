<?php
require_once __DIR__ . '/ajax_json_bootstrap.php';
require_once __DIR__ . '/../../includes/lib-initialize.php';
require_once __DIR__ . '/../../includes/profile_note_shares.php';
require_once __DIR__ . '/../../includes/private_notes/permissions.php';
require_once __DIR__ . '/../../includes/csrf-middleware.php';

if (!$session->isLoggedIn()) {
	profile_notes_ajax_json_exit(['status' => 'error', 'error' => 'Unauthorized'], 401);
}
if (!$session->isAdmin() && !$session->isStaff()) {
	profile_notes_ajax_json_exit(['status' => 'error', 'error' => 'Access denied'], 403);
}
csrf_require_for_request('json');

$userId = (int) $session->userId;
$creatorType = $session->isStaff() ? 'staff' : 'admin';
$input = json_decode(csrf_request_body_raw(), true);
if (!is_array($input)) {
	$input = [];
}
$noteId = (int) ($input['profile_note_id'] ?? 0);
$subjectUserId = (int) ($input['subject_user_id'] ?? 0);
$targetUserId = (int) ($input['shared_with_user_id'] ?? 0);
$permission = strtolower(trim((string) ($input['permission'] ?? 'view')));

if ($noteId <= 0 || $subjectUserId <= 0 || $targetUserId <= 0 || ($permission !== 'view' && $permission !== 'edit')) {
	profile_notes_ajax_json_exit(['status' => 'error', 'error' => 'Invalid request'], 400);
}

profile_note_shares_ensure_table($connect);

$n = profile_note_share_get_note_for_creator($connect, $noteId, $subjectUserId, $userId, $creatorType);
if (!$n) {
	profile_notes_ajax_json_exit(['status' => 'error', 'error' => 'Only the note author can share'], 403);
}

if ($targetUserId === $userId) {
	profile_notes_ajax_json_exit(['status' => 'error', 'error' => 'You cannot share with yourself'], 400);
}

$targetSql = "SELECT id FROM users WHERE id = {$targetUserId} LIMIT 1";
$targetRes = $database->querySoft($targetSql);
if (!$targetRes) {
	profile_notes_ajax_json_exit(['status' => 'error', 'error' => 'Lookup failed'], 500);
}
$target = $database->fetchArray($targetRes);
if (!$target || empty($target['id'])) {
	profile_notes_ajax_json_exit(['status' => 'error', 'error' => 'User not found'], 404);
}

if (!privateNotesCanShareWithUser($database, $userId, $targetUserId)) {
	profile_notes_ajax_json_exit(['status' => 'error', 'error' => 'User is not allowed for sharing'], 403);
}

$permEsc = $permission === 'edit' ? 'edit' : 'view';
$ins = $connect->prepare(
	'INSERT INTO profile_note_shares (profile_note_id, creator_user_id, shared_with_user_id, permission)
	VALUES (?, ?, ?, ?)
	ON DUPLICATE KEY UPDATE permission = VALUES(permission), updated_at = CURRENT_TIMESTAMP'
);
if (!$ins) {
	if (function_exists('profile_notes_ajax_debug_log')) {
		profile_notes_ajax_debug_log('share_add_prepare_failed', [
			'error' => $connect->error,
			'errno' => $connect->errno,
		]);
	}
	profile_notes_ajax_json_exit(['status' => 'error', 'error' => 'Share storage misconfigured (see logs/profile_notes_ajax.log)'], 500);
}
$ins->bind_param('iiis', $noteId, $userId, $targetUserId, $permEsc);
if (!$ins->execute()) {
	$ins->close();
	profile_notes_ajax_json_exit(['status' => 'error', 'error' => 'Failed to update share'], 500);
}
$ins->close();

profile_notes_ajax_json_exit(['status' => 'ok']);
