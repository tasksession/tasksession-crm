<?php
require_once __DIR__ . '/ajax_json_bootstrap.php';
require_once __DIR__ . '/../../includes/lib-initialize.php';
require_once __DIR__ . '/../../includes/profile_note_shares.php';
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

if ($noteId <= 0 || $subjectUserId <= 0 || $targetUserId <= 0) {
	profile_notes_ajax_json_exit(['status' => 'error', 'error' => 'Invalid request'], 400);
}

profile_note_shares_ensure_table($connect);

$n = profile_note_share_get_note_for_creator($connect, $noteId, $subjectUserId, $userId, $creatorType);
if (!$n) {
	profile_notes_ajax_json_exit(['status' => 'error', 'error' => 'Only the note author can remove shares'], 403);
}

$del = $connect->prepare('DELETE FROM profile_note_shares WHERE profile_note_id = ? AND shared_with_user_id = ?');
$del->bind_param('ii', $noteId, $targetUserId);
if (!$del->execute()) {
	$del->close();
	profile_notes_ajax_json_exit(['status' => 'error', 'error' => 'Failed to remove share'], 500);
}
$del->close();

profile_notes_ajax_json_exit(['status' => 'ok']);
