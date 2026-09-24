<?php
require_once __DIR__ . '/ajax_json_bootstrap.php';
require_once __DIR__ . '/../../includes/lib-initialize.php';
require_once __DIR__ . '/../../includes/private_notes/permissions.php';

if (!$session->isLoggedIn()) {
	project_notes_ajax_json_exit(['status' => 'error', 'error' => 'Unauthorized'], 401);
}
$projectId = (int) ($_GET['project_id'] ?? 0);
if ($projectId <= 0) {
	project_notes_ajax_json_exit(['status' => 'error', 'error' => 'Invalid project'], 422);
}
$project = projects::findByProjectId($projectId);
if (!$project) {
	project_notes_ajax_json_exit(['status' => 'error', 'error' => 'Project not found'], 404);
}
$uid = (int) $session->userId;
$status = (int) ($_SESSION['accountStatus'] ?? 0);
$canAccess = false;
if ($status === 1) {
	$canAccess = true;
} elseif ($status === 3) {
	include_once __DIR__ . '/../../includes/permissions.php';
	$canAccess = has_permission('project_view_all') || strpos((string) ($project->s_ids ?? ''), (string) $uid) !== false;
} elseif ($status === 2) {
	$canAccess = ((int) ($project->c_id ?? 0) === $uid) || ((int) ($project->main_client_id ?? 0) === $uid);
	if (!$canAccess && !empty($project->c_ids)) {
		$all = array_filter(explode(',', (string) $project->c_ids));
		$canAccess = in_array((string) $uid, $all, true) || in_array($uid, $all, true);
	}
}
if (!$canAccess) {
	project_notes_ajax_json_exit(['status' => 'error', 'error' => 'Access denied'], 403);
}

$search = trim((string) ($_GET['q'] ?? ''));
$subjectUserId = (int) ($_GET['subject_user_id'] ?? 0);
$rows = privateNotesResolveShareTargets($database, $search, $uid, 30);
if ($subjectUserId > 0) {
	$hasSubject = false;
	foreach ($rows as $r) {
		if ((int) ($r['id'] ?? 0) === $subjectUserId) {
			$hasSubject = true;
			break;
		}
	}
	if (!$hasSubject) {
		$one = privateNotesResolveOneShareTargetRow($database, $subjectUserId, $uid, $search);
		if ($one) {
			array_unshift($rows, $one);
		}
	}
}
project_notes_ajax_json_exit(['status' => 'ok', 'data' => ['users' => $rows]]);
