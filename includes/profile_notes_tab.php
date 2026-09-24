<?php
/**
 * Profile "Notes" tab: load/save profile_notes for the viewed user (subject) and current viewer.
 *
 * Expects (set by caller before include):
 *   - $connect (mysqli)
 *   - $user_id (int) — profile user being viewed
 *   - $session — logged-in user
 *   - $profileNotesCreatorType — 'admin' or 'staff' (default 'admin')
 *
 * Sets for templates:
 *   - $notes_message, $edit_note, $notes, $total_notes, $searchQuery
 *   - $profileNotesFormAction, $profileNotesNewUrl
 *   - $profileNotesList — 'active' | 'archive'
 *   - $profileNotesArchiveCount
 */
if (!isset($connect) || !isset($user_id) || !isset($session)) {
	return;
}

require_once __DIR__ . '/profile_note_shares.php';
profile_note_shares_ensure_table($connect);

$ct = isset($profileNotesCreatorType) && $profileNotesCreatorType === 'staff' ? 'staff' : 'admin';
$current_creator_id = (int) $session->userId;

$__pnCol = $connect->query("SHOW COLUMNS FROM `profile_notes` LIKE 'color'");
if ($__pnCol && $__pnCol->num_rows === 0) {
	$connect->query("ALTER TABLE `profile_notes` ADD COLUMN `color` VARCHAR(32) NULL DEFAULT NULL AFTER `content`");
}
if ($__pnCol) {
	$__pnCol->close();
}
$__pnAr = $connect->query("SHOW COLUMNS FROM `profile_notes` LIKE 'is_archived'");
if ($__pnAr && $__pnAr->num_rows === 0) {
	$connect->query("ALTER TABLE `profile_notes` ADD COLUMN `is_archived` TINYINT(1) NOT NULL DEFAULT 0 AFTER `color`");
}
if ($__pnAr) {
	$__pnAr->close();
}

$profileBase = 'profile.php?user_id=' . (int) $user_id . '&tab=notes';
$searchQuery = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$profileNotesList = (isset($_GET['profile_notes_list']) && (string) $_GET['profile_notes_list'] === 'archive') ? 'archive' : 'active';
$colorFilter = isset($_GET['color_filter']) && in_array((string) $_GET['color_filter'], ['blue', 'green', 'yellow'], true) ? (string) $_GET['color_filter'] : '';
$scopeFilter = isset($_GET['scope_filter']) && (string) $_GET['scope_filter'] === 'recent' ? 'recent' : '';
$__pnListExtras = [];
if ($searchQuery !== '') {
	$__pnListExtras['search'] = $searchQuery;
}
if ($profileNotesList === 'archive') {
	$__pnListExtras['profile_notes_list'] = 'archive';
}
if ($colorFilter !== '') {
	$__pnListExtras['color_filter'] = $colorFilter;
}
if ($scopeFilter !== '') {
	$__pnListExtras['scope_filter'] = $scopeFilter;
}
$profileListQuery = $__pnListExtras !== [] ? ('&' . http_build_query($__pnListExtras)) : '';
unset($__pnListExtras);

$notes_message = '';
$edit_note = null;
$notes = [];
$total_notes = 0;

$profileNotesFormAction = 'profile.php?user_id=' . (int) $user_id . '&tab=notes' . $profileListQuery;
$profileNotesNewUrl = 'profile.php?user_id=' . (int) $user_id . '&tab=notes&new=1';

$profileNotesAssertAccess = static function (mysqli $c, int $noteId, int $subjectUserId, int $creatorId, string $creatorType) {
	$st = $c->prepare('SELECT id, content, color, is_archived FROM profile_notes WHERE id=? AND user_id=? AND creator_id=? AND creator_type=? LIMIT 1');
	$st->bind_param('iiis', $noteId, $subjectUserId, $creatorId, $creatorType);
	$st->execute();
	$row = $st->get_result()->fetch_assoc();
	$st->close();
	return $row ?: null;
};

if (isset($_GET['archive']) && (int) $_GET['archive'] > 0) {
	$aid = (int) $_GET['archive'];
	$cur = $profileNotesAssertAccess($connect, $aid, (int) $user_id, $current_creator_id, $ct);
	if ($cur) {
		$u = $connect->prepare('UPDATE profile_notes SET is_archived=1, updated_at=NOW() WHERE id=? AND user_id=? AND creator_id=? AND creator_type=?');
		$u->bind_param('iiis', $aid, $user_id, $current_creator_id, $ct);
		$u->execute();
		$u->close();
		header('Location: ' . $profileBase);
		exit;
	}
	$notes_message = "<div class='alert alert-danger'>Could not archive this note.</div>";
}

if (isset($_GET['unarchive']) && (int) $_GET['unarchive'] > 0) {
	$ur = (int) $_GET['unarchive'];
	$cur = $profileNotesAssertAccess($connect, $ur, (int) $user_id, $current_creator_id, $ct);
	if ($cur) {
		$u = $connect->prepare('UPDATE profile_notes SET is_archived=0, updated_at=NOW() WHERE id=? AND user_id=? AND creator_id=? AND creator_type=?');
		$u->bind_param('iiis', $ur, $user_id, $current_creator_id, $ct);
		$u->execute();
		$u->close();
		header('Location: ' . $profileBase . '&profile_notes_list=archive&note_id=' . $ur);
		exit;
	}
	$notes_message = "<div class='alert alert-danger'>Could not restore this note.</div>";
}

if (isset($_GET['clone']) && (int) $_GET['clone'] > 0) {
	$clone_id = (int) $_GET['clone'];
	$cur = $profileNotesAssertAccess($connect, $clone_id, (int) $user_id, $current_creator_id, $ct);
	if ($cur) {
		$enc = (string) ($cur['content'] ?? '');
		$rawCol = isset($cur['color']) && is_string($cur['color']) ? $cur['color'] : '';
		$rawCol = preg_replace('/[^a-z0-9_-]/i', '', $rawCol);
		$cV = in_array($rawCol, ['blue', 'green', 'yellow'], true) ? $rawCol : null;
		$ins = $connect->prepare('INSERT INTO profile_notes (user_id, creator_id, creator_type, content, color, is_archived, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 0, NOW(), NOW())');
		$ins->bind_param('iisss', $user_id, $current_creator_id, $ct, $enc, $cV);
		if ($ins->execute()) {
			$nid = (int) $connect->insert_id;
			$ins->close();
			if ($nid > 0) {
				header('Location: ' . $profileBase . '&note_id=' . $nid);
				exit;
			}
		}
		$ins->close();
	}
	$notes_message = "<div class='alert alert-danger'>Could not clone this note.</div>";
}

if (isset($_GET['color'], $_GET['note_id'])) {
	$color = preg_replace('/[^a-z0-9_-]/i', '', (string) $_GET['color']);
	$colorAllowed = ['default', 'blue', 'green', 'yellow'];
	$nid = (int) $_GET['note_id'];
	if ($nid > 0 && in_array($color, $colorAllowed, true)) {
		$cur = $profileNotesAssertAccess($connect, $nid, (int) $user_id, $current_creator_id, $ct);
		if ($cur) {
			if ($color === 'default') {
				$u = $connect->prepare('UPDATE profile_notes SET color=NULL, updated_at=NOW() WHERE id=? AND user_id=? AND creator_id=? AND creator_type=?');
				$u->bind_param('iiis', $nid, $user_id, $current_creator_id, $ct);
			} else {
				$u = $connect->prepare('UPDATE profile_notes SET color=?, updated_at=NOW() WHERE id=? AND user_id=? AND creator_id=? AND creator_type=?');
				$u->bind_param('siiis', $color, $nid, $user_id, $current_creator_id, $ct);
			}
			$u->execute();
			$u->close();
			$redir = $profileBase . '&note_id=' . $nid . $profileListQuery;
			header('Location: ' . $redir);
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
		$encrypted_content = encryptString($content);
		$stmt = $connect->prepare('UPDATE profile_notes SET content=?, updated_at=NOW() WHERE id=? AND user_id=? AND creator_id=? AND creator_type=?');
		$stmt->bind_param('siiis', $encrypted_content, $note_id, $user_id, $current_creator_id, $ct);
		$result = $stmt->execute();
		$notes_message = $result ? "<div class='alert alert-success'>Note updated!</div>" : "<div class='alert alert-danger'>Error updating note.</div>";
		$stmt->close();
	} else {
		$encrypted_content = encryptString($content);
		$stmt = $connect->prepare('INSERT INTO profile_notes (user_id, creator_id, creator_type, content, is_archived, created_at, updated_at) VALUES (?, ?, ?, ?, 0, NOW(), NOW())');
		$stmt->bind_param('iiss', $user_id, $current_creator_id, $ct, $encrypted_content);
		$result = $stmt->execute();
		if ($result) {
			$new_note_id = (int) $connect->insert_id;
			$stmt->close();
			header('Location: profile.php?user_id=' . (int) $user_id . '&tab=notes&note_id=' . $new_note_id);
			exit;
		}
		$stmt->close();
		$notes_message = "<div class='alert alert-danger'>Error creating note.</div>";
	}
}

if (isset($_GET['delete'])) {
	$delete_id = (int) $_GET['delete'];
	$stmt = $connect->prepare('DELETE FROM profile_notes WHERE id=? AND user_id=? AND creator_id=? AND creator_type=?');
	$stmt->bind_param('iiis', $delete_id, $user_id, $current_creator_id, $ct);
	$stmt->execute();
	$stmt->close();
	$notes_message = "<div class='alert alert-success'>Note deleted!</div>";
}

$archFilter = $profileNotesList === 'archive' ? 'AND is_archived=1' : 'AND (is_archived=0 OR is_archived IS NULL)';
$colorCond = $colorFilter !== '' ? ' AND color = ?' : '';
$recentOnly = $scopeFilter === 'recent' ? ' AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)' : '';

// Content is encrypted at rest: SQL LIKE cannot match user text. Load list, decrypt, filter in PHP when searching.
$sql = "SELECT * FROM profile_notes WHERE user_id=? AND creator_id=? AND creator_type=? $archFilter $colorCond" . $recentOnly . " ORDER BY updated_at DESC";
$stmt = $connect->prepare($sql);
if ($colorFilter !== '') {
	$stmt->bind_param('iiss', $user_id, $current_creator_id, $ct, $colorFilter);
} else {
	$stmt->bind_param('iis', $user_id, $current_creator_id, $ct);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
	if (!empty($row['content'])) {
		$row['content'] = decryptString($row['content']);
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

$profileNoteShareMap = [];
$pnIdsForShare = array_values(array_filter(array_map(static function ($n) {
	return (int) ($n['id'] ?? 0);
}, $notes), static function ($id) {
	return $id > 0;
}));
if ($pnIdsForShare !== []) {
	$profileNoteShareMap = profile_note_shares_team_rows_by_note_ids($connect, $pnIdsForShare);
}

$stmt = $connect->prepare('SELECT COUNT(*) as c FROM profile_notes WHERE user_id=? AND creator_id=? AND creator_type=? AND is_archived=1');
$stmt->bind_param('iis', $user_id, $current_creator_id, $ct);
$stmt->execute();
$arcRow = $stmt->get_result()->fetch_assoc();
$profileNotesArchiveCount = (int) ($arcRow['c'] ?? 0);
$stmt->close();

if (isset($_GET['note_id'])) {
	$note_id = (int) $_GET['note_id'];
	$stmt = $connect->prepare('SELECT * FROM profile_notes WHERE id=? AND user_id=? AND creator_id=? AND creator_type=? LIMIT 1');
	$stmt->bind_param('iiis', $note_id, $user_id, $current_creator_id, $ct);
	$stmt->execute();
	$r = $stmt->get_result();
	$edit_note = $r->fetch_assoc();
	$stmt->close();
	if ($edit_note && !empty($edit_note['content'])) {
		$edit_note['content'] = decryptString($edit_note['content']);
	}
} elseif (isset($_GET['new'])) {
	$edit_note = null;
} elseif (!empty($notes)) {
	$edit_note = $notes[0];
}

$stmt_count = $connect->prepare("SELECT COUNT(*) as total FROM profile_notes WHERE user_id=? AND creator_id=? AND creator_type=? $archFilter");
$stmt_count->bind_param('iis', $user_id, $current_creator_id, $ct);
$stmt_count->execute();
$rc = $stmt_count->get_result()->fetch_assoc();
$total_notes = (int) ($rc['total'] ?? 0);
$stmt_count->close();
if ($searchQuery !== '') {
	$total_notes = count($notes);
}

unset($ct);
