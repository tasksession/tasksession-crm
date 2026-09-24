<?php
require_once __DIR__ . '/../../includes/lib-initialize.php';
require_once __DIR__ . '/../../includes/project_note_shares.php';
require_once __DIR__ . '/../../includes/csrf-middleware.php';

header('Content-Type: application/json; charset=utf-8');

if (!function_exists('project_notes_autosave_log')) {
	function project_notes_autosave_log(string $message, array $context = []): void {
		// No-op in production mode.
		return;
	}
}
if (!$session->isLoggedIn()) {
	project_notes_autosave_log('unauthorized', ['session_user_id' => $session->userId ?? 0]);
	http_response_code(401);
	echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
	exit;
}
csrf_require_for_request('json');
$raw = csrf_request_body_raw();
$input = json_decode($raw !== '' ? $raw : '[]', true);
if (!is_array($input)) {
	http_response_code(400);
	echo json_encode(['status' => 'error', 'error' => 'Invalid payload']);
	exit;
}

$noteId = (int) ($input['note_id'] ?? 0);
$projectId = (int) ($input['project_id'] ?? 0);
$clientUpdatedAt = trim((string) ($input['client_updated_at'] ?? ''));
$content = trim(sanitize_tinymce_content((string) ($input['note_content'] ?? '')));
if ($projectId <= 0 || $content === '') {
	project_notes_autosave_log('invalid_note_payload', ['project_id' => $projectId, 'note_id' => $noteId, 'content_len' => strlen($content)]);
	http_response_code(422);
	echo json_encode(['status' => 'error', 'error' => 'Invalid note']);
	exit;
}
$project = projects::findByProjectId($projectId);
if (!$project) {
	project_notes_autosave_log('project_not_found', ['project_id' => $projectId, 'note_id' => $noteId]);
	http_response_code(404);
	echo json_encode(['status' => 'error', 'error' => 'Project not found']);
	exit;
}

$uid = (int) $session->userId;
$status = (int) ($_SESSION['accountStatus'] ?? 0);
$ct = $status === 3 ? 'staff' : ($status === 2 ? 'client' : 'admin');
$canAccess = false;
if ($status === 1) {
	$canAccess = true;
} elseif ($status === 3) {
	include_once __DIR__ . '/../../includes/permissions.php';
	if (function_exists('ensure_user_permissions')) {
		ensure_user_permissions($connect);
	}
	$assigned = false;
	$sids = array_filter(array_map('trim', explode(',', (string) ($project->s_ids ?? ''))), static function ($v) {
		return $v !== '';
	});
	foreach ($sids as $sid) {
		if ((int) $sid === $uid) {
			$assigned = true;
			break;
		}
	}
	$canAccess = (function_exists('has_permission') && has_permission('project_view_all')) || $assigned;
} elseif ($status === 2) {
	$canAccess = ((int) ($project->c_id ?? 0) === $uid) || ((int) ($project->main_client_id ?? 0) === $uid);
	if (!$canAccess && !empty($project->c_ids)) {
		$all = array_filter(explode(',', (string) $project->c_ids));
		$canAccess = in_array((string) $uid, $all, true) || in_array($uid, $all, true);
	}
}
if (!$canAccess) {
	project_notes_autosave_log('project_access_denied', [
		'user_id' => $uid,
		'account_status' => $status,
		'project_id' => $projectId,
		's_ids' => (string) ($project->s_ids ?? ''),
	]);
	http_response_code(403);
	echo json_encode(['status' => 'error', 'error' => 'Access denied']);
	exit;
}

$enc = encryptString($content);
if ($noteId <= 0) {
	$stmt = $connect->prepare('INSERT INTO project_tab_notes (project_id, creator_id, creator_type, content, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())');
	$stmt->bind_param('iiss', $projectId, $uid, $ct, $enc);
	if (!$stmt->execute()) {
		project_notes_autosave_log('create_failed', ['user_id' => $uid, 'project_id' => $projectId, 'db_error' => $connect->error]);
		$stmt->close();
		http_response_code(500);
		echo json_encode(['status' => 'error', 'error' => 'Failed to create note']);
		exit;
	}
	$newId = (int) $connect->insert_id;
	$stmt->close();
	$q = $connect->prepare('SELECT updated_at FROM project_tab_notes WHERE id = ? LIMIT 1');
	$q->bind_param('i', $newId);
	$q->execute();
	$row = $q->get_result()->fetch_assoc();
	$q->close();
	echo json_encode(['status' => 'ok', 'saved' => true, 'created' => true, 'note_id' => $newId, 'updated_at' => (string) ($row['updated_at'] ?? '')]);
	exit;
}

project_note_shares_ensure_table($connect);

$sel = $connect->prepare('SELECT id, updated_at FROM project_tab_notes WHERE id = ? AND project_id = ? AND creator_id = ? AND creator_type = ? LIMIT 1');
$sel->bind_param('iiis', $noteId, $projectId, $uid, $ct);
$sel->execute();
$noteRow = $sel->get_result()->fetch_assoc();
$sel->close();
$isOwnerEdit = !empty($noteRow['id']);
$canSharedEdit = false;
$serverUpdatedAt = '';
if ($isOwnerEdit) {
	$serverUpdatedAt = (string) ($noteRow['updated_at'] ?? '');
} else {
	$acc = project_note_share_recipient_access($connect, $noteId, $uid);
	$canSharedEdit = !empty($acc['can_edit']);
	if (!$canSharedEdit) {
		project_notes_autosave_log('shared_edit_denied', [
			'user_id' => $uid,
			'project_id' => $projectId,
			'note_id' => $noteId,
			'permission' => $acc['permission'] ?? 'none',
		]);
		http_response_code(403);
		echo json_encode(['status' => 'error', 'error' => 'No edit permission']);
		exit;
	}
	project_notes_autosave_log('shared_edit_allowed', [
		'user_id' => $uid,
		'project_id' => $projectId,
		'note_id' => $noteId,
		'permission' => $acc['permission'] ?? 'none',
	]);
	$qs = $connect->prepare('SELECT updated_at FROM project_tab_notes WHERE id = ? AND project_id = ? LIMIT 1');
	$qs->bind_param('ii', $noteId, $projectId);
	$qs->execute();
	$rowShared = $qs->get_result()->fetch_assoc();
	$qs->close();
	if (empty($rowShared)) {
		project_notes_autosave_log('shared_note_not_found', ['user_id' => $uid, 'project_id' => $projectId, 'note_id' => $noteId]);
		http_response_code(404);
		echo json_encode(['status' => 'error', 'error' => 'Note not found']);
		exit;
	}
	$serverUpdatedAt = (string) ($rowShared['updated_at'] ?? '');
}
if ($clientUpdatedAt !== '' && $serverUpdatedAt !== '' && $clientUpdatedAt !== $serverUpdatedAt) {
	project_notes_autosave_log('conflict', [
		'user_id' => $uid,
		'project_id' => $projectId,
		'note_id' => $noteId,
		'client_updated_at' => $clientUpdatedAt,
		'server_updated_at' => $serverUpdatedAt,
		'is_owner_edit' => $isOwnerEdit ? 1 : 0,
	]);
	http_response_code(409);
	echo json_encode(['status' => 'conflict', 'error' => 'Note was updated elsewhere', 'updated_at' => $serverUpdatedAt]);
	exit;
}
$upd = null;
if ($isOwnerEdit) {
	$upd = $connect->prepare('UPDATE project_tab_notes SET content = ?, updated_at = NOW() WHERE id = ? AND project_id = ? AND creator_id = ? AND creator_type = ?');
	$upd->bind_param('siiis', $enc, $noteId, $projectId, $uid, $ct);
} else {
	$upd = $connect->prepare('UPDATE project_tab_notes SET content = ?, updated_at = NOW() WHERE id = ? AND project_id = ?');
	$upd->bind_param('sii', $enc, $noteId, $projectId);
}
if (!$upd->execute()) {
	project_notes_autosave_log('update_failed', [
		'user_id' => $uid,
		'project_id' => $projectId,
		'note_id' => $noteId,
		'is_owner_edit' => $isOwnerEdit ? 1 : 0,
		'db_error' => $connect->error,
	]);
	$upd->close();
	http_response_code(500);
	echo json_encode(['status' => 'error', 'error' => 'Failed to update note']);
	exit;
}
$upd->close();
project_notes_autosave_log('update_ok', [
	'user_id' => $uid,
	'project_id' => $projectId,
	'note_id' => $noteId,
	'is_owner_edit' => $isOwnerEdit ? 1 : 0,
]);
$q2 = $connect->prepare('SELECT updated_at FROM project_tab_notes WHERE id = ? LIMIT 1');
$q2->bind_param('i', $noteId);
$q2->execute();
$row2 = $q2->get_result()->fetch_assoc();
$q2->close();
echo json_encode(['status' => 'ok', 'saved' => true, 'created' => false, 'note_id' => $noteId, 'updated_at' => (string) ($row2['updated_at'] ?? '')]);
