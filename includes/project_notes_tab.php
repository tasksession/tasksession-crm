<?php
/**
 * Project Notes tab backend for admin/staff/client notes.php?projectId=...
 */
if (!isset($connect, $projectId, $session)) {
	return;
}

require_once __DIR__ . '/project_note_shares.php';
project_note_shares_ensure_table($connect);

$current_creator_id = (int) $session->userId;
$ctRaw = isset($projectNotesCreatorType) ? (string) $projectNotesCreatorType : '';
$ct = in_array($ctRaw, ['admin', 'staff', 'client'], true) ? $ctRaw : 'admin';
$projectId = (int) $projectId;

$connect->query("CREATE TABLE IF NOT EXISTS `project_tab_notes` (
	`id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`project_id` INT(11) NOT NULL,
	`creator_id` INT(11) NOT NULL,
	`creator_type` ENUM('admin','staff','client') NOT NULL,
	`title` VARCHAR(255) NULL DEFAULT NULL,
	`content` TEXT NOT NULL,
	`color` VARCHAR(32) NULL DEFAULT NULL,
	`is_archived` TINYINT(1) NOT NULL DEFAULT 0,
	`created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
	`updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	INDEX idx_project_id (project_id),
	INDEX idx_creator_id (creator_id),
	INDEX idx_creator_type (creator_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$projectBase = 'notes.php?projectId=' . $projectId;
$searchQuery = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$projectNotesList = (isset($_GET['project_notes_list']) && (string) $_GET['project_notes_list'] === 'archive') ? 'archive' : 'active';
$colorFilter = isset($_GET['color_filter']) && in_array((string) $_GET['color_filter'], ['blue', 'green', 'yellow'], true) ? (string) $_GET['color_filter'] : '';
$scopeFilter = isset($_GET['scope_filter']) && (string) $_GET['scope_filter'] === 'recent' ? 'recent' : '';
$__pnListExtras = [];
if ($searchQuery !== '') {
	$__pnListExtras['search'] = $searchQuery;
}
if ($projectNotesList === 'archive') {
	$__pnListExtras['project_notes_list'] = 'archive';
}
if ($colorFilter !== '') {
	$__pnListExtras['color_filter'] = $colorFilter;
}
if ($scopeFilter !== '') {
	$__pnListExtras['scope_filter'] = $scopeFilter;
}
$projectListQuery = $__pnListExtras !== [] ? ('&' . http_build_query($__pnListExtras)) : '';

$notes_message = '';
$notes_toast_flash = null;
$edit_note = null;
$notes = [];
$total_notes = 0;
$projectNotesFormAction = $projectBase . $projectListQuery;
$projectNotesNewUrl = $projectBase . '&new=1';

// Legacy import: migrate one old row for this project/user into the new encrypted table when empty.
$legacyRes = $connect->query(
	"SELECT COUNT(*) AS c FROM project_tab_notes WHERE project_id={$projectId} AND creator_id={$current_creator_id} AND creator_type='" . $connect->real_escape_string($ct) . "'"
);
$legacyCountRow = $legacyRes ? $legacyRes->fetch_assoc() : ['c' => 0];
if ($legacyRes instanceof mysqli_result) {
	$legacyRes->free();
}
if (((int) ($legacyCountRow['c'] ?? 0)) === 0) {
	$legacySql = "SELECT note_content FROM project_notes WHERE project_id={$projectId} AND user_id={$current_creator_id} LIMIT 1";
	$legacyRowRes = $connect->query($legacySql);
	$legacyRow = $legacyRowRes ? $legacyRowRes->fetch_assoc() : null;
	if ($legacyRowRes instanceof mysqli_result) {
		$legacyRowRes->free();
	}
	$legacyContent = trim((string) ($legacyRow['note_content'] ?? ''));
	if ($legacyContent !== '') {
		$enc = encryptString($legacyContent);
		$insLegacy = $connect->prepare(
			'INSERT INTO project_tab_notes (project_id, creator_id, creator_type, content, is_archived, created_at, updated_at) VALUES (?, ?, ?, ?, 0, NOW(), NOW())'
		);
		$insLegacy->bind_param('iiss', $projectId, $current_creator_id, $ct, $enc);
		$insLegacy->execute();
		$insLegacy->close();
	}
}

$projectNotesAssertAccess = static function (mysqli $c, int $noteId, int $projId, int $creatorId, string $creatorType) {
	$st = $c->prepare('SELECT id, content, color, is_archived FROM project_tab_notes WHERE id=? AND project_id=? AND creator_id=? AND creator_type=? LIMIT 1');
	$st->bind_param('iiis', $noteId, $projId, $creatorId, $creatorType);
	$st->execute();
	$row = $st->get_result()->fetch_assoc();
	$st->close();
	return $row ?: null;
};

if (isset($_GET['archive']) && (int) $_GET['archive'] > 0) {
	$aid = (int) $_GET['archive'];
	$cur = $projectNotesAssertAccess($connect, $aid, $projectId, $current_creator_id, $ct);
	if ($cur) {
		$u = $connect->prepare('UPDATE project_tab_notes SET is_archived=1, updated_at=NOW() WHERE id=? AND project_id=? AND creator_id=? AND creator_type=?');
		$u->bind_param('iiis', $aid, $projectId, $current_creator_id, $ct);
		$u->execute();
		$u->close();
		header('Location: ' . $projectBase);
		exit;
	}
	$notes_message = "<div class='alert alert-danger'>Could not archive this note.</div>";
}

if (isset($_GET['unarchive']) && (int) $_GET['unarchive'] > 0) {
	$nid = (int) $_GET['unarchive'];
	$cur = $projectNotesAssertAccess($connect, $nid, $projectId, $current_creator_id, $ct);
	if ($cur) {
		$u = $connect->prepare('UPDATE project_tab_notes SET is_archived=0, updated_at=NOW() WHERE id=? AND project_id=? AND creator_id=? AND creator_type=?');
		$u->bind_param('iiis', $nid, $projectId, $current_creator_id, $ct);
		$u->execute();
		$u->close();
		header('Location: ' . $projectBase . '&project_notes_list=archive&note_id=' . $nid);
		exit;
	}
	$notes_message = "<div class='alert alert-danger'>Could not restore this note.</div>";
}

if (isset($_GET['clone']) && (int) $_GET['clone'] > 0) {
	$clone_id = (int) $_GET['clone'];
	$cur = $projectNotesAssertAccess($connect, $clone_id, $projectId, $current_creator_id, $ct);
	if ($cur) {
		$enc = (string) ($cur['content'] ?? '');
		$rawCol = isset($cur['color']) ? preg_replace('/[^a-z0-9_-]/i', '', (string) $cur['color']) : '';
		$cV = in_array($rawCol, ['blue', 'green', 'yellow'], true) ? $rawCol : null;
		$ins = $connect->prepare('INSERT INTO project_tab_notes (project_id, creator_id, creator_type, content, color, is_archived, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 0, NOW(), NOW())');
		$ins->bind_param('iisss', $projectId, $current_creator_id, $ct, $enc, $cV);
		if ($ins->execute()) {
			$newId = (int) $connect->insert_id;
			$ins->close();
			header('Location: ' . $projectBase . '&note_id=' . $newId);
			exit;
		}
		$ins->close();
	}
	$notes_message = "<div class='alert alert-danger'>Could not clone this note.</div>";
}

if (isset($_GET['color'], $_GET['note_id'])) {
	$color = preg_replace('/[^a-z0-9_-]/i', '', (string) $_GET['color']);
	$nid = (int) $_GET['note_id'];
	$allowed = ['default', 'blue', 'green', 'yellow'];
	if ($nid > 0 && in_array($color, $allowed, true)) {
		$cur = $projectNotesAssertAccess($connect, $nid, $projectId, $current_creator_id, $ct);
		if ($cur) {
			if ($color === 'default') {
				$u = $connect->prepare('UPDATE project_tab_notes SET color=NULL, updated_at=NOW() WHERE id=? AND project_id=? AND creator_id=? AND creator_type=?');
				$u->bind_param('iiis', $nid, $projectId, $current_creator_id, $ct);
			} else {
				$u = $connect->prepare('UPDATE project_tab_notes SET color=?, updated_at=NOW() WHERE id=? AND project_id=? AND creator_id=? AND creator_type=?');
				$u->bind_param('siiis', $color, $nid, $projectId, $current_creator_id, $ct);
			}
			$u->execute();
			$u->close();
			header('Location: ' . $projectBase . '&note_id=' . $nid . $projectListQuery);
			exit;
		}
	}
	$notes_message = "<div class='alert alert-danger'>Could not update note color.</div>";
}

if (isset($_POST['save_note'])) {
	$note_id = isset($_POST['note_id']) ? (int) $_POST['note_id'] : 0;
	$content = trim((string) ($_POST['note_content'] ?? ''));
	if ($content === '') {
		$notes_message = "<div class='alert alert-danger'>Note content cannot be empty.</div>";
	} elseif ($note_id > 0) {
		$enc = encryptString($content);
		$own = $projectNotesAssertAccess($connect, $note_id, $projectId, $current_creator_id, $ct);
		if ($own) {
			$stmt = $connect->prepare('UPDATE project_tab_notes SET content=?, updated_at=NOW() WHERE id=? AND project_id=? AND creator_id=? AND creator_type=?');
			$stmt->bind_param('siiis', $enc, $note_id, $projectId, $current_creator_id, $ct);
			$ok = $stmt->execute();
			$stmt->close();
			$notes_message = $ok ? "<div class='alert alert-success'>Note updated!</div>" : "<div class='alert alert-danger'>Error updating note.</div>";
		} else {
			$sharedAccess = function_exists('project_note_share_recipient_access')
				? project_note_share_recipient_access($connect, $note_id, $current_creator_id)
				: ['can_edit' => false];
			if (!empty($sharedAccess['can_edit'])) {
				$stmt = $connect->prepare('UPDATE project_tab_notes SET content=?, updated_at=NOW() WHERE id=? AND project_id=?');
				$stmt->bind_param('sii', $enc, $note_id, $projectId);
				$ok = $stmt->execute();
				$stmt->close();
				$notes_message = $ok ? "<div class='alert alert-success'>Note updated!</div>" : "<div class='alert alert-danger'>Error updating shared note.</div>";
			} else {
				$notes_message = "<div class='alert alert-danger'>You do not have permission to edit this note.</div>";
			}
		}
	} else {
		$enc = encryptString($content);
		$stmt = $connect->prepare('INSERT INTO project_tab_notes (project_id, creator_id, creator_type, content, is_archived, created_at, updated_at) VALUES (?, ?, ?, ?, 0, NOW(), NOW())');
		$stmt->bind_param('iiss', $projectId, $current_creator_id, $ct, $enc);
		$ok = $stmt->execute();
		if ($ok) {
			$newId = (int) $connect->insert_id;
			$stmt->close();
			header('Location: ' . $projectBase . '&note_id=' . $newId);
			exit;
		}
		$stmt->close();
		$notes_message = "<div class='alert alert-danger'>Error creating note.</div>";
	}
}

if (isset($_GET['delete'])) {
	$did = (int) $_GET['delete'];
	$stmt = $connect->prepare('DELETE FROM project_tab_notes WHERE id=? AND project_id=? AND creator_id=? AND creator_type=?');
	$stmt->bind_param('iiis', $did, $projectId, $current_creator_id, $ct);
	$stmt->execute();
	$stmt->close();
	$deleteRedirect = $projectBase . $projectListQuery . '&note_deleted=1';
	header('Location: ' . $deleteRedirect);
	exit;
}

if (isset($_GET['note_deleted']) && (string) $_GET['note_deleted'] === '1') {
	$notes_toast_flash = array(
		'type' => 'success',
		'msg' => isset($lang['Note deleted!']) ? (string) $lang['Note deleted!'] : 'Note deleted!',
	);
}

$archFilter = $projectNotesList === 'archive' ? 'AND is_archived=1' : 'AND (is_archived=0 OR is_archived IS NULL)';
$colorCond = $colorFilter !== '' ? ' AND color = ?' : '';
$recentOnly = $scopeFilter === 'recent' ? ' AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)' : '';

$sql = "SELECT * FROM project_tab_notes WHERE project_id=? AND creator_id=? AND creator_type=? {$archFilter}{$colorCond}{$recentOnly} ORDER BY updated_at DESC";
$stmt = $connect->prepare($sql);
if ($colorFilter !== '') {
	$stmt->bind_param('iiss', $projectId, $current_creator_id, $ct, $colorFilter);
} else {
	$stmt->bind_param('iis', $projectId, $current_creator_id, $ct);
}
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
	if (!empty($row['content'])) {
		$row['content'] = decryptString((string) $row['content']);
	}
	$text = (string) ($row['content'] ?? '');
	if ($searchQuery !== '') {
		$hit = $text !== '' && (function_exists('mb_stripos') ? (mb_stripos($text, $searchQuery) !== false) : (stripos($text, $searchQuery) !== false));
		if (!$hit) {
			continue;
		}
	}
	$notes[] = $row;
}
$stmt->close();

// Also show notes shared with the logged-in user for this project.
$sharedSql = "SELECT n.*, s.permission AS shared_permission, s.creator_user_id AS shared_by_user_id
	FROM project_tab_notes n
	INNER JOIN project_tab_note_shares s ON s.project_note_id = n.id
	WHERE n.project_id=? AND s.shared_with_user_id=? {$archFilter}{$colorCond}{$recentOnly}";
$stmtShared = $connect->prepare($sharedSql);
if ($colorFilter !== '') {
	$stmtShared->bind_param('iis', $projectId, $current_creator_id, $colorFilter);
} else {
	$stmtShared->bind_param('ii', $projectId, $current_creator_id);
}
$stmtShared->execute();
$resShared = $stmtShared->get_result();
while ($row = $resShared->fetch_assoc()) {
	if ((int) ($row['creator_id'] ?? 0) === $current_creator_id && (string) ($row['creator_type'] ?? '') === $ct) {
		continue;
	}
	if (!empty($row['content'])) {
		$row['content'] = decryptString((string) $row['content']);
	}
	$text = (string) ($row['content'] ?? '');
	if ($searchQuery !== '') {
		$hit = $text !== '' && (function_exists('mb_stripos') ? (mb_stripos($text, $searchQuery) !== false) : (stripos($text, $searchQuery) !== false));
		if (!$hit) {
			continue;
		}
	}
	$row['_is_shared'] = 1;
	$row['_shared_permission'] = (string) ($row['shared_permission'] ?? 'view');
	$notes[] = $row;
}
$stmtShared->close();

if (!empty($notes)) {
	usort($notes, static function (array $a, array $b): int {
		$ta = strtotime((string) ($a['updated_at'] ?? '1970-01-01 00:00:00'));
		$tb = strtotime((string) ($b['updated_at'] ?? '1970-01-01 00:00:00'));
		if ($ta === $tb) {
			return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
		}
		return $tb <=> $ta;
	});
}

$projectNoteShareMap = [];
$pnIdsForShare = array_values(array_filter(array_map(static function ($n) {
	return (int) ($n['id'] ?? 0);
}, $notes), static function ($id) {
	return $id > 0;
}));
if ($pnIdsForShare !== []) {
	$projectNoteShareMap = project_note_shares_team_rows_by_note_ids($connect, $pnIdsForShare);
}

$stmtArc = $connect->prepare('SELECT COUNT(*) as c FROM project_tab_notes WHERE project_id=? AND creator_id=? AND creator_type=? AND is_archived=1');
$stmtArc->bind_param('iis', $projectId, $current_creator_id, $ct);
$stmtArc->execute();
$arcRow = $stmtArc->get_result()->fetch_assoc();
$projectNotesArchiveCount = (int) ($arcRow['c'] ?? 0);
$stmtArc->close();

if (isset($_GET['note_id'])) {
	$note_id = (int) $_GET['note_id'];
	$stmt = $connect->prepare('SELECT * FROM project_tab_notes WHERE id=? AND project_id=? AND creator_id=? AND creator_type=? LIMIT 1');
	$stmt->bind_param('iiis', $note_id, $projectId, $current_creator_id, $ct);
	$stmt->execute();
	$edit_note = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	if (!$edit_note) {
		$stmt = $connect->prepare('SELECT n.*, s.permission AS shared_permission, s.creator_user_id AS shared_by_user_id
			FROM project_tab_notes n
			INNER JOIN project_tab_note_shares s ON s.project_note_id = n.id
			WHERE n.id=? AND n.project_id=? AND s.shared_with_user_id=? LIMIT 1');
		$stmt->bind_param('iii', $note_id, $projectId, $current_creator_id);
		$stmt->execute();
		$edit_note = $stmt->get_result()->fetch_assoc();
		$stmt->close();
		if ($edit_note) {
			$edit_note['_is_shared'] = 1;
			$edit_note['_shared_permission'] = (string) ($edit_note['shared_permission'] ?? 'view');
		} else {
			// Fallback for inconsistent legacy share rows:
			// if recipient access is granted, still load the note for direct note_id links.
			$sharedAccess = function_exists('project_note_share_recipient_access')
				? project_note_share_recipient_access($connect, $note_id, $current_creator_id)
				: ['can_view' => false, 'permission' => 'none'];
			if (!empty($sharedAccess['can_view'])) {
				$stmt = $connect->prepare('SELECT * FROM project_tab_notes WHERE id=? AND project_id=? LIMIT 1');
				$stmt->bind_param('ii', $note_id, $projectId);
				$stmt->execute();
				$edit_note = $stmt->get_result()->fetch_assoc();
				$stmt->close();
				if ($edit_note) {
					$edit_note['_is_shared'] = 1;
					$edit_note['_shared_permission'] = (string) ($sharedAccess['permission'] ?? 'view');
				}
			}
		}
	}
	if ($edit_note && !empty($edit_note['content'])) {
		$edit_note['content'] = decryptString((string) $edit_note['content']);
	}
} elseif (isset($_GET['new'])) {
	$edit_note = null;
} elseif (!empty($notes)) {
	$edit_note = $notes[0];
}

$stmtCount = $connect->prepare("SELECT COUNT(*) as total FROM project_tab_notes WHERE project_id=? AND creator_id=? AND creator_type=? {$archFilter}");
$stmtCount->bind_param('iis', $projectId, $current_creator_id, $ct);
$stmtCount->execute();
$rc = $stmtCount->get_result()->fetch_assoc();
$total_notes = (int) ($rc['total'] ?? 0);
$stmtCount->close();
if ($searchQuery !== '') {
	$total_notes = count($notes);
}
