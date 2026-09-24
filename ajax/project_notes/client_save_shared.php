<?php
require_once __DIR__ . '/../../includes/lib-initialize.php';
require_once __DIR__ . '/../../includes/project_note_shares.php';
require_once __DIR__ . '/../../includes/csrf-middleware.php';

header('Content-Type: application/json; charset=utf-8');
if (!$session->isLoggedIn()) {
	http_response_code(401);
	echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
	exit;
}
csrf_require_for_request('json');
$recipientId = (int) $session->userId;
$input = json_decode(csrf_request_body_raw() ?: '[]', true);
if (!is_array($input)) {
	http_response_code(400);
	echo json_encode(['status' => 'error', 'error' => 'Invalid payload']);
	exit;
}
$pid = (int) ($input['project_note_id'] ?? 0);
$content = trim(sanitize_tinymce_content((string) ($input['note_content'] ?? '')));
if ($pid <= 0 || $content === '') {
	http_response_code(422);
	echo json_encode(['status' => 'error', 'error' => 'Invalid note']);
	exit;
}
project_note_shares_ensure_table($connect);
$acc = project_note_share_recipient_access($connect, $pid, $recipientId);
if (empty($acc['can_edit'])) {
	http_response_code(403);
	echo json_encode(['status' => 'error', 'error' => 'No edit permission']);
	exit;
}
$enc = encryptString($content);
$st = $connect->prepare('UPDATE project_tab_notes SET content = ?, updated_at = NOW() WHERE id = ?');
$st->bind_param('si', $enc, $pid);
if (!$st->execute()) {
	$st->close();
	http_response_code(500);
	echo json_encode(['status' => 'error', 'error' => 'Save failed']);
	exit;
}
$st->close();
$q = $connect->prepare('SELECT updated_at FROM project_tab_notes WHERE id = ? LIMIT 1');
$q->bind_param('i', $pid);
$q->execute();
$row = $q->get_result()->fetch_assoc();
$q->close();
echo json_encode(['status' => 'ok', 'updated_at' => (string) ($row['updated_at'] ?? '')]);
