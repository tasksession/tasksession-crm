<?php
require_once __DIR__ . '/ajax_json_bootstrap.php';
require_once __DIR__ . '/../../includes/lib-initialize.php';
require_once __DIR__ . '/../../includes/project_note_shares.php';
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
if ($noteId <= 0 || $projectId <= 0 || $targetUserId <= 0) {
	project_notes_ajax_json_exit(['status' => 'error', 'error' => 'Invalid request'], 400);
}
project_note_shares_ensure_table($connect);
$n = project_note_share_get_note_for_creator($connect, $noteId, $projectId, $userId, $creatorType);
if (!$n) {
	project_notes_ajax_json_exit(['status' => 'error', 'error' => 'Only the note author can remove shares'], 403);
}
$del = $connect->prepare('DELETE FROM project_tab_note_shares WHERE project_note_id = ? AND shared_with_user_id = ?');
$del->bind_param('ii', $noteId, $targetUserId);
if (!$del->execute()) {
	$del->close();
	project_notes_ajax_json_exit(['status' => 'error', 'error' => 'Failed to remove share'], 500);
}
$del->close();
project_notes_ajax_json_exit(['status' => 'ok']);
