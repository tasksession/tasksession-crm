<?php
require_once __DIR__ . '/ajax_json_bootstrap.php';
require_once __DIR__ . '/../../includes/lib-initialize.php';
require_once __DIR__ . '/../../includes/profile_note_shares.php';

if (!$session->isLoggedIn()) {
	profile_notes_ajax_json_exit(['status' => 'error', 'error' => 'Unauthorized'], 401);
}

if (!$session->isAdmin() && !$session->isStaff()) {
	profile_notes_ajax_json_exit(['status' => 'error', 'error' => 'Access denied'], 403);
}

$userId = (int) $session->userId;
$creatorType = $session->isStaff() ? 'staff' : 'admin';
$noteId = isset($_GET['profile_note_id']) ? (int) $_GET['profile_note_id'] : 0;
$subjectUserId = isset($_GET['subject_user_id']) ? (int) $_GET['subject_user_id'] : 0;

if ($noteId <= 0 || $subjectUserId <= 0) {
	profile_notes_ajax_json_exit(['status' => 'error', 'error' => 'Invalid request'], 400);
}

profile_note_shares_ensure_table($connect);

$row = profile_note_share_get_note_for_creator($connect, $noteId, $subjectUserId, $userId, $creatorType);
if (!$row) {
	profile_notes_ajax_json_exit(['status' => 'error', 'error' => 'Access denied'], 403);
}

$sql = "SELECT s.id, s.shared_with_user_id, s.permission, s.created_at, u.firstName, u.email
	FROM profile_note_shares s
	LEFT JOIN users u ON u.id = s.shared_with_user_id
	WHERE s.profile_note_id = " . (int) $noteId . "
	ORDER BY s.created_at DESC";
$res = $connect->query($sql);
if (!$res) {
	profile_notes_ajax_debug_log('mysql_query_failed', [
		'error' => $connect->error,
		'errno' => $connect->errno,
		'sql' => $sql,
	]);
}
$shares = [];
if ($res) {
	while ($r = $res->fetch_assoc()) {
		$shares[] = [
			'share_id' => (int) ($r['id'] ?? 0),
			'shared_with_user_id' => (int) ($r['shared_with_user_id'] ?? 0),
			'permission' => (string) ($r['permission'] ?? 'view'),
			'created_at' => (string) ($r['created_at'] ?? ''),
			'name' => (string) ($r['firstName'] ?? ''),
			'email' => (string) ($r['email'] ?? ''),
		];
	}
	$res->free();
}

profile_notes_ajax_json_exit([
	'status' => 'ok',
	'data' => [
		'shares' => $shares,
		'is_owner' => true,
		'permission' => 'owner',
		'public_share' => [
			'general_access' => 'restricted',
			'role' => 'viewer',
			'token' => '',
			'public_url' => '',
		],
	],
]);
