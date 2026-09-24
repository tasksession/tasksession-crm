<?php
/**
 * Documents recipient saves a profile note shared with them (edit permission only).
 * Allowed roles: client (2), staff (3), admin (1).
 */
require_once __DIR__ . '/../../includes/lib-initialize.php';
require_once __DIR__ . '/../../includes/profile_note_shares.php';
require_once __DIR__ . '/../../includes/csrf-middleware.php';

header('Content-Type: application/json; charset=utf-8');

if (!$session->isLoggedIn()) {
	http_response_code(401);
	echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
	exit;
}
$acct = (int) ($_SESSION['accountStatus'] ?? 0);
if (!in_array($acct, [1, 2, 3], true)) {
	http_response_code(403);
	echo json_encode(['status' => 'error', 'error' => 'Access denied']);
	exit;
}

csrf_require_for_request('json');

$recipientId = (int) $session->userId;
$raw = csrf_request_body_raw();
$input = json_decode($raw !== '' ? $raw : '[]', true);
if (!is_array($input)) {
	http_response_code(400);
	echo json_encode(['status' => 'error', 'error' => 'Invalid payload']);
	exit;
}

$pid = (int) ($input['profile_note_id'] ?? 0);
$content = trim(sanitize_tinymce_content((string) ($input['note_content'] ?? '')));

if ($pid <= 0 || $content === '') {
	http_response_code(422);
	echo json_encode(['status' => 'error', 'error' => 'Invalid note']);
	exit;
}

profile_note_shares_ensure_table($connect);
$acc = profile_note_share_recipient_access($connect, $pid, $recipientId);
if (empty($acc['can_edit'])) {
	http_response_code(403);
	echo json_encode(['status' => 'error', 'error' => 'No edit permission']);
	exit;
}

$enc = encryptString($content);
$st = $connect->prepare('UPDATE profile_notes SET content = ?, updated_at = NOW() WHERE id = ?');
$st->bind_param('si', $enc, $pid);
if (!$st->execute()) {
	$st->close();
	http_response_code(500);
	echo json_encode(['status' => 'error', 'error' => 'Save failed']);
	exit;
}
$st->close();

$row = null;
$q = $connect->prepare('SELECT updated_at FROM profile_notes WHERE id = ? LIMIT 1');
if ($q) {
	$q->bind_param('i', $pid);
	if ($q->execute()) {
		$q->bind_result($updatedAt);
		if ($q->fetch()) {
			$row = ['updated_at' => $updatedAt];
		}
	}
	$q->close();
}

echo json_encode([
	'status' => 'ok',
	'updated_at' => (string) ($row['updated_at'] ?? ''),
]);
