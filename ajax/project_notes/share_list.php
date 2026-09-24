<?php
require_once __DIR__ . '/ajax_json_bootstrap.php';
require_once __DIR__ . '/../../includes/lib-initialize.php';
require_once __DIR__ . '/../../includes/project_note_shares.php';

if (!$session->isLoggedIn()) {
	project_notes_ajax_json_exit(['status' => 'error', 'error' => 'Unauthorized'], 401);
}
$userId = (int) $session->userId;
$creatorType = ((int) ($_SESSION['accountStatus'] ?? 0) === 3) ? 'staff' : ((((int) ($_SESSION['accountStatus'] ?? 0) === 2) ? 'client' : 'admin'));
$noteId = (int) ($_GET['project_note_id'] ?? 0);
$projectId = (int) ($_GET['project_id'] ?? 0);
if ($noteId <= 0 || $projectId <= 0) {
	project_notes_ajax_json_exit(['status' => 'error', 'error' => 'Invalid request'], 400);
}
project_note_shares_ensure_table($connect);
$row = project_note_share_get_note_for_creator($connect, $noteId, $projectId, $userId, $creatorType);
if (!$row) {
	project_notes_ajax_json_exit(['status' => 'error', 'error' => 'Access denied'], 403);
}

$sql = "SELECT s.id, s.shared_with_user_id, s.permission, s.created_at, u.firstName, u.email
	FROM project_tab_note_shares s
	LEFT JOIN users u ON u.id = s.shared_with_user_id
	WHERE s.project_note_id = " . (int) $noteId . "
	ORDER BY s.created_at DESC";
$res = $connect->query($sql);
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
project_notes_ajax_json_exit(['status' => 'ok', 'data' => ['shares' => $shares]]);
