<?php
require_once __DIR__ . '/ajax_json_bootstrap.php';
require_once __DIR__ . '/../../includes/lib-initialize.php';
require_once __DIR__ . '/../../includes/private_notes/permissions.php';

if (!$session->isLoggedIn()) {
	profile_notes_ajax_json_exit(['status' => 'error', 'error' => 'Unauthorized'], 401);
}

if (!$session->isAdmin() && !$session->isStaff()) {
	profile_notes_ajax_json_exit(['status' => 'error', 'error' => 'Access denied'], 403);
}

$userId = (int) $session->userId;
$search = trim((string) ($_GET['q'] ?? ''));
$subjectUserId = (int) ($_GET['subject_user_id'] ?? 0);

$rows = privateNotesResolveShareTargets($database, $search, $userId, 30);

if ($subjectUserId > 0) {
	$hasSubject = false;
	foreach ($rows as $r) {
		if ((int) ($r['id'] ?? 0) === $subjectUserId) {
			$hasSubject = true;
			break;
		}
	}
	if (! $hasSubject) {
		$one = privateNotesResolveOneShareTargetRow($database, $subjectUserId, $userId, $search);
		if ($one) {
			array_unshift($rows, $one);
		}
	}
}

profile_notes_ajax_json_exit(['status' => 'ok', 'data' => ['users' => $rows]]);
