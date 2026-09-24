<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : documents.php
   Purpose : Manages private notes and personal annotations
 ================================================================================
 */
ob_start();
require_once("../includes/lib-initialize.php");

if (!($session->isLoggedIn()) || !isset($_SESSION['accountStatus']) || (int) $_SESSION['accountStatus'] !== 2) {
    redirectTo($url . "index.php");
}

$title = $lang['Documents'] . " | " . $syatem_title;
$body_class = 'documents-page';
include("../templates/header.php");

$id = $session->userId;
$user = User::findById((int) $id);
if (!$user) {
    redirectTo($url . "index.php");
}
$username = $user->firstName;
$email = $user->email;

function privateNotesRewriteEditorMediaSrc($html) {
    global $url;
    $html = (string)$html;
    if ($html === '') {
        return $html;
    }
    return preg_replace_callback('/(<img\b[^>]*\bsrc\s*=\s*)(["\'])([^"\']+)(\2)/i', function ($m) use ($url) {
        $src = html_entity_decode((string)$m[3], ENT_QUOTES, 'UTF-8');
        $parsed = parse_url($src);
        $path = ($parsed && isset($parsed['path'])) ? (string)$parsed['path'] : $src;
        $path = str_replace('\\', '/', $path);
        $marker = '/uploads/private-notes/';
        $pos = strpos($path, $marker);
        if ($pos === false && strpos($path, 'uploads/private-notes/') !== 0) {
            return $m[0];
        }
        $relative = ($pos !== false) ? ltrim(substr($path, $pos + 1), '/') : ltrim($path, '/');
        $safeUrl = rtrim((string)$url, '/') . '/share/private_media.php?src=' . rawurlencode($relative);
        return $m[1] . $m[2] . htmlspecialchars($safeUrl, ENT_QUOTES, 'UTF-8') . $m[4];
    }, $html);
}

$user_id = $_SESSION['userId'];
if (!isset($user_id) || empty($user_id)) {
    redirectTo($url . "index.php");
}

// Handle actions: create, update, delete
$message = "";
$edit_note = null;
require_once("../includes/private_notes/permissions.php");
require_once __DIR__ . '/../includes/profile_note_shares.php';
$current_access_permission = 'none';
$current_can_edit = false;
$csrfToken = generate_csrf_token();

// Create or update note
if (isset($_POST['save_note'])) {
    $profile_shared_save_id = isset($_POST['profile_shared_note_id']) ? (int)$_POST['profile_shared_note_id'] : 0;
    $note_id = isset($_POST['note_id']) ? intval($_POST['note_id']) : 0;
    $title_val = trim($_POST['title']);
    $content = trim(sanitize_tinymce_content($_POST['note_content'] ?? ''));
    if (empty($content)) {
        $message = "<div class='alert alert-danger'>" . $lang['Note content cannot be empty.'] . "</div>";
    } else if ($profile_shared_save_id > 0) {
        profile_note_shares_ensure_table($connect);
        $accPn = profile_note_share_recipient_access($connect, $profile_shared_save_id, (int)$user_id);
        if (empty($accPn['can_edit'])) {
            $message = "<div class='alert alert-danger'>You do not have permission to edit this note.</div>";
        } else {
            $encrypted_content = encryptString($content);
            $stPn = $connect->prepare('UPDATE profile_notes SET content = ?, updated_at = NOW() WHERE id = ?');
            $stPn->bind_param('si', $encrypted_content, $profile_shared_save_id);
            $okPn = $stPn->execute();
            $stPn->close();
            if ($okPn) {
                header('Location: documents?profile_shared_note=' . $profile_shared_save_id);
                exit;
            }
            $message = "<div class='alert alert-danger'>" . $lang['Error updating note.'] . "</div>";
        }
    } else if ($note_id > 0) {
        $access = privateNotesGetAccessContext($database, $note_id, $user_id);
        if (empty($access['can_edit'])) {
            $message = "<div class='alert alert-danger'>You do not have permission to edit this note.</div>";
        } else {
            $encrypted_content = encryptString($content);
            $sql = "UPDATE private_notes SET title='{$database->escapeValue($title_val)}', content='{$database->escapeValue($encrypted_content)}', updated_at=NOW() WHERE id={$note_id}";
            $result = $database->query($sql);
            $message = $result ? "<div class='alert alert-success'>" . $lang['Note updated!'] . "</div>" : "<div class='alert alert-danger'>" . $lang['Error updating note.'] . "</div>";
        }
    } else {
        // Create - encrypt content before saving
        $color_to_assign = '';
        if (isset($_GET['color_filter']) && $_GET['color_filter'] !== '') {
            $color_to_assign = $_GET['color_filter'];
        }
        $encrypted_content = encryptString($content);
        $sql = "INSERT INTO private_notes (user_id, title, content, color, created_at, updated_at) VALUES ({$user_id}, '{$database->escapeValue($title_val)}', '{$database->escapeValue($encrypted_content)}', '{$color_to_assign}', NOW(), NOW())";
        $result = $database->query($sql);
        if ($result) {
            $new_note_id = $database->insertId();
            $msg = '';
            if ($color_to_assign) {
                $msg = "&msg=created_with_color&color=" . urlencode($color_to_assign);
            }
            header("Location: documents?note_id=" . $new_note_id . $msg);
            exit;
        }
        $message = "<div class='alert alert-danger'>" . $lang['Error creating note.'] . "</div>";
    }
}

// Delete note
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $deleteAccess = privateNotesGetAccessContext($database, $delete_id, $user_id);
    if (!empty($deleteAccess['is_owner'])) {
        $currentTab = isset($_GET['tab']) ? (string)$_GET['tab'] : 'notes';
        if ($currentTab === 'trash') {
            $sql = "DELETE FROM private_notes WHERE id={$delete_id} AND user_id={$user_id}";
            $database->query($sql);
            $message = "<div class='alert alert-success'>" . $lang['Note deleted!'] . "</div>";
        } else {
            $sql = "UPDATE private_notes SET is_trashed=1, is_archived=0, updated_at=NOW() WHERE id={$delete_id} AND user_id={$user_id}";
            $database->query($sql);
            $message = "<div class='alert alert-success'>Note moved to trash!</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>Only the note owner can delete this note.</div>";
    }
}

if (isset($_GET['archive'])) {
    $archive_id = intval($_GET['archive']);
    $archiveAccess = privateNotesGetAccessContext($database, $archive_id, $user_id);
    if (!empty($archiveAccess['is_owner'])) {
        $sql = "UPDATE private_notes SET is_archived=1, is_trashed=0, updated_at=NOW() WHERE id={$archive_id} AND user_id={$user_id}";
        $database->query($sql);
        $message = "<div class='alert alert-success'>Note archived!</div>";
    } else {
        $message = "<div class='alert alert-danger'>Only the note owner can archive this note.</div>";
    }
}

if (isset($_GET['restore'])) {
    $restore_id = intval($_GET['restore']);
    $restoreAccess = privateNotesGetAccessContext($database, $restore_id, $user_id);
    if (!empty($restoreAccess['is_owner'])) {
        $sql = "UPDATE private_notes SET is_trashed=0, is_archived=0, updated_at=NOW() WHERE id={$restore_id} AND user_id={$user_id} AND is_trashed=1";
        $database->query($sql);
        header('Location: documents?note_id=' . $restore_id . '&tab=notes');
        exit;
    }
    $message = "<div class='alert alert-danger'>Only the note owner can restore this note.</div>";
}

if (isset($_GET['clone'])) {
    $clone_id = intval($_GET['clone']);
    $cloneAccess = privateNotesGetAccessContext($database, $clone_id, $user_id);
    if (!empty($cloneAccess['is_owner'])) {
        $cloneRes = $database->query("SELECT title, content, color FROM private_notes WHERE id={$clone_id} AND user_id={$user_id} LIMIT 1");
        $cloneRow = $cloneRes ? $database->fetchArray($cloneRes) : null;
        if ($cloneRow) {
            $srcTitle = trim((string)($cloneRow['title'] ?? ''));
            $newTitle = $srcTitle === '' ? 'Untitled (Copy)' : ($srcTitle . ' (Copy)');
            $escTitle = $database->escapeValue($newTitle);
            $escContent = $database->escapeValue((string)($cloneRow['content'] ?? ''));
            $col = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($cloneRow['color'] ?? ''));
            if (!in_array($col, ['blue', 'green', 'yellow'], true)) {
                $col = '';
            }
            $escColor = $database->escapeValue($col);
            $sql = "INSERT INTO private_notes (user_id, title, content, color, created_at, updated_at) VALUES ({$user_id}, '{$escTitle}', '{$escContent}', '{$escColor}', NOW(), NOW())";
            if ($database->query($sql)) {
                $newId = (int)$database->insertId();
                if ($newId > 0) {
                    header('Location: documents?note_id=' . $newId . '&tab=notes');
                    exit;
                }
            }
        }
        $message = "<div class='alert alert-danger'>Could not clone this note.</div>";
    } else {
        $message = "<div class='alert alert-danger'>Only the note owner can clone this note.</div>";
    }
}

// Handle color change
if (isset($_GET['color']) && isset($_GET['note_id'])) {
    $color = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['color']);
    $color_allowed = ['default', 'blue', 'green', 'yellow'];
    if (in_array($color, $color_allowed)) {
        $note_id = intval($_GET['note_id']);
        $colorAccess = privateNotesGetAccessContext($database, $note_id, $user_id);
        if (!empty($colorAccess['can_edit'])) {
            $colorSql = ($color === 'default') ? "NULL" : "'" . $database->escapeValue($color) . "'";
            $sql = "UPDATE private_notes SET color={$colorSql} WHERE id={$note_id}";
            $database->query($sql);
            header("Location: documents?note_id=" . $note_id);
            exit;
        }
        $message = "<div class='alert alert-danger'>You do not have permission to update color for this note.</div>";
    }
}

// Color filter logic
$colorFilter = isset($_GET['color_filter']) ? $_GET['color_filter'] : '';
$allowedColors = ['blue', 'green', 'yellow'];
$scopeFilter = isset($_GET['scope_filter']) ? $_GET['scope_filter'] : '';
$allowedScopeFilters = ['shared_with_me', 'recent'];
$tabFilter = isset($_GET['tab']) ? (string)$_GET['tab'] : 'notes';
if (!in_array($tabFilter, ['notes', 'archive', 'trash'], true)) {
    $tabFilter = 'notes';
}

// Show message if note was created with color
if (isset($_GET['msg']) && $_GET['msg'] === 'created_with_color' && isset($_GET['color']) && in_array($_GET['color'], $allowedColors)) {
    $message = "<div class='alert alert-success'>" . $lang['Note created with color'] . " <strong>" . htmlspecialchars(ucfirst($_GET['color'])) . "</strong>!</div>";
}

$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$sql = "SELECT n.*, CASE WHEN n.user_id = {$user_id} THEN 'owner' ELSE COALESCE(s.permission, 'view') END AS access_permission
        FROM private_notes n
        LEFT JOIN private_note_shares s ON s.note_id = n.id AND s.shared_with_user_id = {$user_id}
        WHERE (n.user_id = {$user_id} OR s.shared_with_user_id = {$user_id})";
if ($tabFilter === 'archive') {
    $sql .= " AND n.is_archived=1 AND n.is_trashed=0";
} elseif ($tabFilter === 'trash') {
    $sql .= " AND n.is_trashed=1";
} else {
    $sql .= " AND n.is_archived=0 AND n.is_trashed=0";
}
if (in_array($colorFilter, $allowedColors)) {
    $sql .= " AND n.color='{$colorFilter}'";
}
if (in_array($scopeFilter, $allowedScopeFilters, true) && $scopeFilter === 'shared_with_me') {
    $sql .= " AND n.user_id <> {$user_id} AND s.shared_with_user_id = {$user_id}";
}
if (in_array($scopeFilter, $allowedScopeFilters, true) && $scopeFilter === 'recent') {
    $sql .= " AND n.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
}
$sql .= " ORDER BY n.updated_at DESC LIMIT 400";
$result = $database->query($sql);
$notes = [];
while ($result && ($row = $database->fetchArray($result))) {
    // Decrypt content for display
    if (!empty($row['content'])) {
        $row['content'] = decryptString($row['content']);
        $row['content'] = privateNotesRewriteEditorMediaSrc($row['content']);
    }
    $notes[] = $row;
}

profile_note_shares_ensure_table($connect);
if ($tabFilter !== 'trash') {
    $archPn = ($tabFilter === 'archive') ? ' AND (pn.is_archived = 1) ' : ' AND (pn.is_archived = 0 OR pn.is_archived IS NULL) ';
    $colorPn = '';
    if (in_array($colorFilter, $allowedColors, true)) {
        $colorEsc = $database->escapeValue($colorFilter);
        $colorPn = " AND pn.color = '{$colorEsc}' ";
    }
    $recentPn = (in_array($scopeFilter, $allowedScopeFilters, true) && $scopeFilter === 'recent')
        ? ' AND pn.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) '
        : '';
    $sharedMePn = (in_array($scopeFilter, $allowedScopeFilters, true) && $scopeFilter === 'shared_with_me')
        ? ' AND pn.creator_id <> ' . (int)$user_id . ' '
        : '';
    $sqlPn = "SELECT pn.*, s.permission AS access_permission
        FROM profile_notes pn
        INNER JOIN profile_note_shares s ON s.profile_note_id = pn.id AND s.shared_with_user_id = " . (int)$user_id . "
        WHERE 1=1 {$archPn}{$colorPn}{$recentPn}{$sharedMePn}
        ORDER BY pn.updated_at DESC";
    $resPn = $database->query($sqlPn);
    while ($resPn && ($pr = $database->fetchArray($resPn))) {
        if (!empty($pr['content'])) {
            $pr['content'] = decryptString($pr['content']);
            $pr['content'] = privateNotesRewriteEditorMediaSrc($pr['content']);
        }
        $plain = trim(strip_tags((string)($pr['content'] ?? '')));
        $oneLine = preg_replace('/\s+/', ' ', $plain);
        if ($oneLine === '') {
            $pr['title'] = 'Profile note';
        } elseif (function_exists('mb_strlen') && mb_strlen($oneLine) > 80) {
            $pr['title'] = mb_substr($oneLine, 0, 80) . '…';
        } else {
            $pr['title'] = strlen($oneLine) > 80 ? substr($oneLine, 0, 80) . '…' : $oneLine;
        }
        $pr['user_id'] = (int)($pr['creator_id'] ?? 0);
        $pr['public_share_enabled'] = 0;
        $pr['_is_profile_shared'] = true;
        $pr['profile_note_id'] = (int)($pr['id'] ?? 0);
        $pr['id'] = 0;
        $pr['is_trashed'] = 0;
        $notes[] = $pr;
    }
    usort($notes, static function ($a, $b) {
        return strtotime((string)($b['updated_at'] ?? 0)) <=> strtotime((string)($a['updated_at'] ?? 0));
    });
}

// Post-process search in decrypted content if search query exists
if ($searchQuery !== '') {
    $filtered_notes = [];
    foreach ($notes as $note) {
        if (stripos((string)($note['title'] ?? ''), $searchQuery) !== false ||
            stripos((string)($note['content'] ?? ''), $searchQuery) !== false) {
            $filtered_notes[] = $note;
        }
    }
    $notes = $filtered_notes;
}

$noteShareMap = [];
$noteOwnerMap = [];
$profileNoteShareMap = [];
if (!empty($notes)) {
    $noteIds = array_map(static function ($n) {
        return (int)($n['id'] ?? 0);
    }, $notes);
    $noteIds = array_values(array_filter($noteIds, static function ($idVal) {
        return $idVal > 0;
    }));

    if (!empty($noteIds)) {
        $shareSql = "SELECT s.note_id, s.shared_with_user_id, u.firstName
                     FROM private_note_shares s
                     LEFT JOIN users u ON u.id = s.shared_with_user_id
                     WHERE s.note_id IN (" . implode(',', $noteIds) . ")
                     ORDER BY s.created_at ASC";
        $shareRes = $database->query($shareSql);
        while ($shareRes && ($shareRow = $database->fetchArray($shareRes))) {
            $nid = (int)$shareRow['note_id'];
            if (!isset($noteShareMap[$nid])) {
                $noteShareMap[$nid] = [];
            }
            $noteShareMap[$nid][] = [
                'user_id' => (int)$shareRow['shared_with_user_id'],
                'first_name' => (string)($shareRow['firstName'] ?? '')
            ];
        }
    }

    $ownerIds = array_map(static function ($n) {
        return (int)($n['user_id'] ?? 0);
    }, $notes);
    $ownerIds = array_values(array_unique(array_filter($ownerIds, static function ($idVal) {
        return $idVal > 0;
    })));
    if (!empty($ownerIds)) {
        $ownerSql = "SELECT id, firstName FROM users WHERE id IN (" . implode(',', $ownerIds) . ")";
        $ownerRes = $database->query($ownerSql);
        while ($ownerRes && ($ownerRow = $database->fetchArray($ownerRes))) {
            $noteOwnerMap[(int)$ownerRow['id']] = (string)($ownerRow['firstName'] ?? '');
        }
    }

    $pns = [];
    foreach ($notes as $n) {
        if (!empty($n['_is_profile_shared'])) {
            $p = (int) ($n['profile_note_id'] ?? 0);
            if ($p > 0) {
                $pns[] = $p;
            }
        }
    }
    if ($pns !== []) {
        $profileNoteShareMap = profile_note_shares_team_rows_by_note_ids($connect, $pns);
    }
}

// Fetch note to edit/view
if (isset($_GET['profile_shared_note'])) {
    $pnSid = (int)$_GET['profile_shared_note'];
    profile_note_shares_ensure_table($connect);
    $accPn = profile_note_share_recipient_access($connect, $pnSid, (int)$user_id);
    if (empty($accPn['can_view'])) {
        $message = "<div class='alert alert-danger'>You do not have access to this note.</div>";
        $edit_note = null;
    } else {
        $rPn = $database->query("SELECT * FROM profile_notes WHERE id = {$pnSid} LIMIT 1");
        $edit_note = $rPn ? $database->fetchArray($rPn) : null;
        if ($edit_note && !empty($edit_note['content'])) {
            $edit_note['content'] = decryptString($edit_note['content']);
            $edit_note['content'] = privateNotesRewriteEditorMediaSrc($edit_note['content']);
        }
        if ($edit_note) {
            $edit_note['profile_note_id'] = $pnSid;
            $edit_note['_is_profile_shared'] = true;
            $ptit = trim(strip_tags((string)($edit_note['content'] ?? '')));
            $ptit = preg_replace('/\s+/', ' ', $ptit);
            if ($ptit === '') {
                $edit_note['title'] = 'Profile note';
            } elseif (function_exists('mb_strlen') && mb_strlen($ptit) > 120) {
                $edit_note['title'] = mb_substr($ptit, 0, 120) . '…';
            } else {
                $edit_note['title'] = strlen($ptit) > 120 ? substr($ptit, 0, 120) . '…' : $ptit;
            }
        }
        $current_access_permission = (string)($accPn['permission'] ?? 'view');
        $current_can_edit = !empty($accPn['can_edit']);
    }
} elseif (isset($_GET['note_id'])) {
    $note_id = intval($_GET['note_id']);
    $accessCtx = privateNotesGetAccessContext($database, $note_id, $user_id);
    if (!empty($accessCtx['can_view'])) {
        $sql = "SELECT * FROM private_notes WHERE id={$note_id} LIMIT 1";
        $result = $database->query($sql);
        $edit_note = $database->fetchArray($result);
        if ($edit_note && !empty($edit_note['content'])) {
            $edit_note['content'] = decryptString($edit_note['content']);
            $edit_note['content'] = privateNotesRewriteEditorMediaSrc($edit_note['content']);
        }
        $current_access_permission = (string)$accessCtx['permission'];
        $current_can_edit = !empty($accessCtx['can_edit']);
    } else {
        $message = "<div class='alert alert-danger'>You do not have access to this note.</div>";
        $edit_note = null;
    }
} else if (isset($_GET['new'])) {
    // Show blank form for new note
    $edit_note = null;
    $current_access_permission = 'owner';
    $current_can_edit = true;
} else if (!empty($notes)) {
    $edit_note = null;
    foreach ($notes as $cand) {
        if (empty($cand['_is_profile_shared']) && (int)($cand['id'] ?? 0) > 0) {
            $edit_note = $cand;
            break;
        }
    }
    if ($edit_note) {
        $current_access_permission = (string)($edit_note['access_permission'] ?? 'view');
        $current_can_edit = ($current_access_permission === 'owner' || $current_access_permission === 'edit');
    } else {
        $current_access_permission = 'none';
        $current_can_edit = false;
    }
}

// Get total notes count for the current user
profile_note_shares_ensure_table($connect);
$sql_count = "SELECT COUNT(DISTINCT n.id) as total
              FROM private_notes n
              LEFT JOIN private_note_shares s ON s.note_id = n.id AND s.shared_with_user_id = {$user_id}
              WHERE (n.user_id = {$user_id} OR s.shared_with_user_id = {$user_id})";
$result_count = $database->query($sql_count);
$row_count = $result_count ? $database->fetchArray($result_count) : null;
$total_notes = (int)($row_count['total'] ?? 0);
$pnActiveCountRes = $database->query(
    'SELECT COUNT(DISTINCT pn.id) AS c FROM profile_notes pn
    INNER JOIN profile_note_shares s ON s.profile_note_id = pn.id AND s.shared_with_user_id = ' . (int)$user_id . '
    WHERE (pn.is_archived = 0 OR pn.is_archived IS NULL)'
);
$pnActiveRow = $pnActiveCountRes ? $database->fetchArray($pnActiveCountRes) : null;
$total_notes += (int)($pnActiveRow['c'] ?? 0);

$sql_archive_count = "SELECT COUNT(DISTINCT n.id) as total
                      FROM private_notes n
                      LEFT JOIN private_note_shares s ON s.note_id = n.id
                      WHERE (n.user_id = {$user_id} OR s.shared_with_user_id = {$user_id})
                        AND n.is_archived=1
                        AND n.is_trashed=0";
$result_archive_count = $database->query($sql_archive_count);
$row_archive_count = $result_archive_count ? $database->fetchArray($result_archive_count) : null;
$archive_notes = (int)($row_archive_count['total'] ?? 0);
$pnArchCountRes = $database->query(
    'SELECT COUNT(DISTINCT pn.id) AS c FROM profile_notes pn
    INNER JOIN profile_note_shares s ON s.profile_note_id = pn.id AND s.shared_with_user_id = ' . (int)$user_id . '
    WHERE pn.is_archived = 1'
);
$pnArchRow = $pnArchCountRes ? $database->fetchArray($pnArchCountRes) : null;
$archive_notes += (int)($pnArchRow['c'] ?? 0);

$sql_trash_count = "SELECT COUNT(DISTINCT n.id) as total
                    FROM private_notes n
                    LEFT JOIN private_note_shares s ON s.note_id = n.id
                    WHERE (n.user_id = {$user_id} OR s.shared_with_user_id = {$user_id})
                      AND n.is_trashed=1";
$result_trash_count = $database->query($sql_trash_count);
$row_trash_count = $result_trash_count ? $database->fetchArray($result_trash_count) : null;
$trash_notes = (int)($row_trash_count['total'] ?? 0);
?>

<div class="page-container admin-docs-board">
    <div class="container-fluid admin-docs-board__container">
        <div class="row row-eq-height admin-docs-board__row">
            <?php include("../templates/sidebar.php"); ?>
            <div class="page-content">
                <?php include('../templates/top-header.php'); ?>
				<div class="row bg-grey admin-docs-main">
                    <div class="col-md-12 project-tabs">
						<div class="row">
							<div class="project-tabs-header">
								<div class="scrollable-tabs-container d-flex col-gap-40 col-gap-40-sep">
									<div class="main-heading d-flex align-items-center justify-content-between">
										<div><h1> <?php echo $lang['Documents']; ?><span> (<?php echo $total_notes; ?>)</span></h1></div>
									</div>
							<?php
								$showBack = false;
								if (
									(isset($_GET['search']) && trim($_GET['search']) !== '') ||
									(isset($_GET['color_filter']) && in_array($_GET['color_filter'], ['blue', 'green', 'yellow'])) ||
									(isset($_GET['scope_filter']) && in_array($_GET['scope_filter'], ['shared_with_me', 'recent'])) ||
									(isset($_GET['new'])) ||
									(isset($_GET['profile_shared_note']) && (int)$_GET['profile_shared_note'] > 0)
								) {
									$showBack = true;
								}
								?>
								<div class="icon-container sep">
								 <?php if ($showBack): ?>
										<a href="documents"><?php echo ts_icon('restore'); ?>
								<?php echo $lang['Back']; ?></a>
								  <?php endif; ?>

									<?php if (!isset($_GET['new'])): ?>
										<a href="documents?new=1">
								 <?php echo $lang['Create New']; ?>  <?php echo ts_icon('plus', 'dropdown-toggle-icon h-6'); ?>
										</a>
									<?php endif; ?>

								</div>
                                <div class="icon-container sep">
                                    <a href="documents?tab=notes" class="<?php echo $tabFilter === 'notes' ? 'active' : ''; ?>">All</a>
                                    <a href="documents?tab=archive" class="<?php echo $tabFilter === 'archive' ? 'active' : ''; ?>">Archive (<?php echo $archive_notes; ?>)</a>
                                    <a href="documents?tab=trash" class="<?php echo $tabFilter === 'trash' ? 'active' : ''; ?>">Trash (<?php echo $trash_notes; ?>)</a>
                                </div>
					 	   </div>   
					   </div> 
										<div class="search">
                                            <span id="noteAutosaveStatus" class="me-2 d-none" aria-live="polite">Saving...</span>
                                            <div class="search-icon border-btn-a" onclick="toggleSearch()">
                                                <?php echo ts_icon('search', 'w-2'); ?>
                                            </div>
                                            <form method="GET" action="" class="search-form" id="searchForm">
                                                <div class="input-group">
                                                   <span class="search-field-icon">
                                                       <?php echo ts_icon('search', 'w-2'); ?>
                                                   </span>
                                                   <input type="text" id="task-search" name="search" class="form-control" placeholder="<?php echo $lang['Search notes...']; ?>" value="<?php echo htmlspecialchars($searchQuery ?? ''); ?>">
                                                    <?php if(isset($searchQuery) && !empty($searchQuery)): ?>
                                                        <a href="documents" class="cross" title="<?php echo htmlspecialchars($lang['Close'] ?? 'Close', ENT_QUOTES, 'UTF-8'); ?>">
                                                            <?php echo ts_icon('close', 'w-2'); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="#" class="cross" onclick="toggleSearch(); return false;" title="<?php echo htmlspecialchars($lang['Close'] ?? 'Close', ENT_QUOTES, 'UTF-8'); ?>">
                                                            <?php echo ts_icon('close', 'w-2'); ?>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </form>
                                        </div>
											<div class="edit-overview-btn kanban-header-filters">
															<div class="action-toggle border-btn-a" data-bs-toggle="collapse" data-bs-target="#color-menu" role="button" tabindex="0">
																<span class="action-text"><?php echo $lang['Filters'] ?? 'Filters'; ?></span><span class="mobile-ellipsis"><?php echo ts_icon('ellipsis', 'w-2'); ?></span>
																<?php echo ts_icon('filter', 'w-2'); ?>
															</div>
													 <div id="color-menu" class="toggle-action justify collapse shadow-dept">
																<ul>
																<li class="<?php echo !isset($_GET['color_filter']) ? 'active' : ''; ?>">
																		<a href="documents">
																			<span><?php echo $lang['All Colors']; ?></span>
																		</a>
																	</li>
																	<li class="<?php echo (isset($_GET['color_filter']) && $_GET['color_filter'] == 'blue') ? 'active' : ''; ?>">
																		<a href="documents?color_filter=blue">
																			<span style="color:#007bff;"><?php echo $lang['Blue']; ?></span>
																		</a>
																	</li>
																	<li class="<?php echo (isset($_GET['color_filter']) && $_GET['color_filter'] == 'green') ? 'active' : ''; ?>">
																		<a href="documents?color_filter=green">
																			<span style="color:#28a745;"><?php echo $lang['Green']; ?></span>
																		</a>
																	</li>
																	<li class="<?php echo (isset($_GET['color_filter']) && $_GET['color_filter'] == 'yellow') ? 'active' : ''; ?>">
																		<a href="documents?color_filter=yellow">
																			<span style="color:#ffc107;"><?php echo $lang['Yellow']; ?></span>
																		</a>
																	</li>
                                                                    <hr class="dropdown-divider mb-0">
                                                                    <li class="<?php echo (isset($_GET['scope_filter']) && $_GET['scope_filter'] == 'shared_with_me') ? 'active' : ''; ?>">
                                                                        <a href="documents?scope_filter=shared_with_me">
                                                                            <span>Shared with me</span>
                                                                        </a>
                                                                    </li>
                                                                    <li class="<?php echo (isset($_GET['scope_filter']) && $_GET['scope_filter'] == 'recent') ? 'active' : ''; ?>">
                                                                        <a href="documents?scope_filter=recent">
                                                                            <span>Recent</span>
                                                                        </a>
                                                                    </li>
																</ul>
															</div>
													</div>
				            <div class="d-none d-md-block">
								<?php
							$is_editing = (!empty($edit_note['id']) && (int)$edit_note['id'] > 0 && empty($edit_note['_is_profile_shared']))
								|| (isset($_GET['note_id']) && intval($_GET['note_id']) > 0)
								|| (!empty($edit_note['_is_profile_shared']) && !empty($edit_note['profile_note_id']))
								|| (isset($_GET['profile_shared_note']) && (int)$_GET['profile_shared_note'] > 0);
							$is_creating = isset($_GET['new']);
							if ($is_editing) {
								// Autosave indicator is rendered near search icon.
							} else if ($is_creating) {
								// Save button removed. Autosave handles updates.
							}
							?>   
							</div>
                            <?php if (!empty($edit_note['id']) && empty($edit_note['_is_profile_shared'])): ?>
                            <div class="dropdown d-none d-md-block">
                                <button class="btn border-btn-a dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <?php echo ts_icon('download', 'dropdown-toggle-icon h-6'); ?>
                                    Export
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <?php
                                    $privateNoteExportUrl = rtrim((string)$url, '/') . '/import-export/notes/export_private_note.php';
                                    $eid = (int)$edit_note['id'];
                                    ?>
                                    <li><a class="dropdown-item" href="<?php echo htmlspecialchars($privateNoteExportUrl . '?note_id=' . $eid . '&format=word'); ?>">Export as Word</a></li>
                                    <li><a class="dropdown-item" href="<?php echo htmlspecialchars($privateNoteExportUrl . '?note_id=' . $eid . '&format=pdf'); ?>" target="_blank" rel="noopener">Export as PDF</a></li>
                                </ul>
                            </div>
                            <button type="button" class="btn primary-btn d-none d-md-inline-flex align-items-center" id="openShareNoteBtn">
                                <?php echo ts_icon('share', 'dropdown-toggle-icon h-6'); ?>
                                Share
                            </button>
                            <?php endif; ?>
						</div>
                       </div>
                    </div>
                <div class="row h-100 admin-docs-content"> 
                    <div class="col-md-12 admin-docs-content-col">
                        <div class="row h-100 admin-docs-split">
                            <div class="col-lg-3 br-right bg-white pd-0 admin-docs-sidebar">
                                <ul class="list-group admin-docs-sidebar-list">
                                    <?php if (empty($notes)): ?>
                                        <li class="list-group-item text-center card" style="padding: 40px 10px;margin: 20px;">
                                            No docs found.

                                        </li>
                                    <?php else: ?>
                                        <?php foreach ($notes as $note): ?>
                                            <?php
                                                $colorClass = '';
                                                if (!empty($note['color'])) {
                                                    $colorClass = 'note-card-' . htmlspecialchars($note['color']);
                                                }
                                                $isProfShare = !empty($note['_is_profile_shared']);
                                                $pnCardId = $isProfShare ? (int)($note['profile_note_id'] ?? 0) : (int)($note['id'] ?? 0);
                                                $noteHref = $isProfShare ? ('documents?profile_shared_note=' . $pnCardId) : ('documents?note_id=' . (int)($note['id'] ?? 0));
                                                $isRowActive = $isProfShare
                                                    ? (!empty($edit_note['_is_profile_shared']) && (int)($edit_note['profile_note_id'] ?? 0) === $pnCardId)
                                                    : (!empty($edit_note['id']) && (int)$edit_note['id'] === (int)($note['id'] ?? 0) && empty($edit_note['_is_profile_shared']));
                                            ?>
                                            <li class="list-group-item note-card <?php echo $colorClass; ?><?php if ($isRowActive) {
                                                echo ' active';
                                            } ?>" style="position: relative;">
                                                <a href="<?php echo htmlspecialchars($noteHref, ENT_QUOTES, 'UTF-8'); ?>" class="note-card-link" style="display: block; text-decoration: none; color: inherit; width: 100%; height: 100%;">
                                                    <div>
                                                        <strong class="card-title mb-1"><?php echo htmlspecialchars($note['title']); ?></strong>
                                                        <div class="note-preview mb-3">
                                                            <?php 
                                                            $preview = strip_tags($note['content']);
                                                            if (strlen($preview) > 80) {
                                                                $preview = mb_substr($preview, 0, 80) . '...';
                                                            }
                                                            echo htmlspecialchars($preview);
                                                            ?>
                                                        </div>
														
											   <div class="d-flex doc-date">
														                                                     
                                                            <?php echo date('Y-m-d', strtotime($note['created_at'])); ?>
                                                            <?php if ($note['created_at'] != $note['updated_at']) { ?>
                                                                &nbsp;|&nbsp; <span> <b><?php echo $lang['Updated']; ?>:</b> <?php echo date('Y-m-d', strtotime($note['updated_at'])); ?></span>
                                                            <?php } ?>
												</div>                                                   
                                                <?php
                                                    $sharedUsers = [];
                                                    if ($isProfShare && $pnCardId > 0) {
                                                        $sharedUsers = $profileNoteShareMap[$pnCardId] ?? [];
                                                    } elseif (!$isProfShare && (int) ($note['id'] ?? 0) > 0) {
                                                        $sharedUsers = $noteShareMap[(int) $note['id']] ?? [];
                                                    }
                                                    $noteOwnerId = (int)($note['user_id'] ?? 0);
                                                    $isOwnerView = ($noteOwnerId === (int)$user_id);
                                                    $hasTeamShare = $isOwnerView && !empty($sharedUsers);
                                                    $hasPublicShare = $isOwnerView && (int)($note['public_share_enabled'] ?? 0) === 1;
                                                    $recipientSeesPublic = !$isOwnerView && (int)($note['public_share_enabled'] ?? 0) === 1;
                                                    $publicLinkAccessLabel = ((string)($note['public_share_permission'] ?? 'view')) === 'edit' ? 'Edit & view access' : 'View access only';
                                                    if ($hasTeamShare) {
                                                        $visibleSharedUsers = array_slice($sharedUsers, 0, 5);
                                                        $extraSharedCount = count($sharedUsers) - count($visibleSharedUsers);
                                                    }
                                                ?>
                                                <?php if ($hasTeamShare || $hasPublicShare): ?>
                                                <div class="mt-2 note-card-share-meta">
                                                    <?php if ($hasTeamShare): ?>
                                                    <div class="grey font-size-11 mb-1">Shared with</div>
                                                    <div class="team-col d-flex align-items-baseline justify-content-between">
                                                        <div class="d-flex avatar-head">
                                                            <?php foreach ($visibleSharedUsers as $sharedUser): ?>
                                                                <?php
                                                                    $sharedUserId = (int)$sharedUser['user_id'];
                                                                    $sharedFirstName = (string)$sharedUser['first_name'];
                                                                    $sharedFullName = trim($sharedFirstName);
                                                                    if ($sharedFullName === '') {
                                                                        $sharedFullName = 'User';
                                                                    }
                                                                ?>
                                                                <div class="avatar-overlap" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="<?php echo htmlspecialchars($sharedFullName, ENT_QUOTES, 'UTF-8'); ?>" data-bs-original-title="<?php echo htmlspecialchars($sharedFullName, ENT_QUOTES, 'UTF-8'); ?>" style="cursor:pointer;" onclick="event.preventDefault(); event.stopPropagation(); window.location.href='profile?user_id=<?php echo $sharedUserId; ?>';">
                                                                    <?php echo getUserAvatarHtml($sharedUserId, $sharedFirstName, '', 30, 30, 'rounded-circle', $sharedFullName); ?>
                                                                </div>
                                                            <?php endforeach; ?>
                                                            <?php if ($extraSharedCount > 0): ?>
                                                                <div class="avatar-overlap d-flex align-items-center justify-content-center" style="font-size:12px;font-weight:600;">
                                                                    +<?php echo (int)$extraSharedCount; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if (!empty($note['access_permission']) && $note['access_permission'] !== 'owner'): ?>
                                                            <div class="badge">
                                                                <span class="doc-bdge"><?php echo htmlspecialchars(($note['access_permission'] === 'edit') ? 'Read & Edit' : 'Read only'); ?></span>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php endif; ?>
                                                    <?php if ($hasPublicShare): ?>
                                                    <div class="<?php echo $hasTeamShare ? 'mt-2 pt-2 br-top' : ''; ?>">
                                                        <div class="grey font-size-11 mb-1">Public link</div>
                                                        <div class="badge note-card-public-link-wrap<?php echo ((string)($note['public_share_permission'] ?? 'view')) === 'edit' ? ' note-card-public-link-wrap--edit' : ''; ?> note-card-public-link-trigger" role="button" tabindex="0" data-note-id="<?php echo $isProfShare ? 0 : (int)$note['id']; ?>" title="Open public link settings">
                                                            <span class="doc-bdge"><?php echo htmlspecialchars($publicLinkAccessLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                <?php elseif (!$isOwnerView): ?>
                                                <?php
                                                    $ownerFirstName = trim((string)($noteOwnerMap[$noteOwnerId] ?? ''));
                                                    if ($ownerFirstName === '') {
                                                        $ownerFirstName = 'User';
                                                    }
                                                ?>
                                                <div class="mt-2">
                                                    <div class="grey font-size-11 mb-1">Shared By</div>
                                                    <div class="team-col d-flex align-items-baseline justify-content-between">
                                                        <div class="d-flex avatar-head">
                                                            <div class="avatar-overlap" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="<?php echo htmlspecialchars($ownerFirstName, ENT_QUOTES, 'UTF-8'); ?>" data-bs-original-title="<?php echo htmlspecialchars($ownerFirstName, ENT_QUOTES, 'UTF-8'); ?>" style="cursor:pointer;" onclick="event.preventDefault(); event.stopPropagation(); window.location.href='profile?user_id=<?php echo $noteOwnerId; ?>';">
                                                                <?php echo getUserAvatarHtml($noteOwnerId, $ownerFirstName, '', 30, 30, 'rounded-circle', $ownerFirstName); ?>
                                                            </div>
                                                        </div>
                                                        <?php if (!empty($note['access_permission']) && $note['access_permission'] !== 'owner'): ?>
                                                            <div class="badge">
                                                                <span class="doc-bdge"><?php echo htmlspecialchars(($note['access_permission'] === 'edit') ? 'Read & Edit' : 'Read only'); ?></span>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if ($recipientSeesPublic): ?>
                                                    <div class="mt-2 pt-2 br-top">
                                                        <div class="grey font-size-11 mb-1">Public link</div>
                                                        <div class="badge note-card-public-link-wrap<?php echo ((string)($note['public_share_permission'] ?? 'view')) === 'edit' ? ' note-card-public-link-wrap--edit' : ''; ?>">
                                                            <span class="doc-bdge"><?php echo htmlspecialchars($publicLinkAccessLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                <?php endif; ?>
												</div>
                                                </a>
                                                <?php if (!$isProfShare): ?>
                                                <div class="dropdown note-card-menu" style="position: absolute; top: 10px; right: 15px;">
                                                    <button class="btn-dots dropdown-toggle" type="button" id="dropdownMenu<?php echo (int)$note['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false" style="color: #888; text-decoration: none;">
                                                        <?php echo ts_icon('dots-vertical', 'w-6'); ?>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end note-actions-dropdown" aria-labelledby="dropdownMenu<?php echo $note['id']; ?>">
                                                        <?php if ($tabFilter === 'trash' && (int)($note['user_id'] ?? 0) === (int)$user_id): ?>
                                                        <li>
                                                            <a class="dropdown-item d-flex align-items-center" href="documents?restore=<?php echo (int)$note['id']; ?>&amp;tab=trash" onclick="return confirm('Restore this note to All notes?');">
                                                                <?php echo ts_icon('restore', 'w-4 me-2 tasksession-timer-log-menu-ico'); ?>
                                                                Restore
                                                            </a>
                                                        </li>
                                                        <?php endif; ?>
                                                        <li>
                                                            <a class="dropdown-item d-flex align-items-center text-danger" href="documents?delete=<?php echo $note['id']; ?>&tab=<?php echo urlencode($tabFilter); ?>" onclick="return confirm('<?php echo $tabFilter === 'trash' ? 'Delete permanently?' : 'Delete this?'; ?>')">
                                                                <?php echo ts_icon('delete', 'w-4 me-2 tasksession-timer-log-menu-ico'); ?>
                                                                <?php echo $tabFilter === 'trash' ? 'Delete Permanently' : $lang['Delete']; ?>

                                                            </a>
                                                        </li>
                                                        <?php if ($tabFilter !== 'trash'): ?>
                                                        <li>
                                                            <a class="dropdown-item d-flex align-items-center" href="documents?archive=<?php echo $note['id']; ?>" onclick="return confirm('Archive this?')">
                                                                <?php echo ts_icon('archive', 'w-4 me-2 tasksession-timer-log-menu-ico'); ?>
                                                                Archive
                                                            </a>
                                                        </li>
                                                        <?php endif; ?>
                                                        <?php if ((int)($note['user_id'] ?? 0) === (int)$user_id): ?>
                                                        <li>
                                                            <a class="dropdown-item d-flex align-items-center copy-note-link-action" href="#" data-note-id="<?php echo (int)$note['id']; ?>">
                                                                <?php echo ts_icon('link', 'me-2 tasksession-timer-log-menu-ico'); ?>
                                                                Copy link
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item d-flex align-items-center" href="documents?clone=<?php echo (int)$note['id']; ?>&amp;tab=<?php echo urlencode($tabFilter); ?>" onclick="return confirm('Create a copy of this note in All notes?');">
                                                                <?php echo ts_icon('duplicate', 'me-2 tasksession-timer-log-menu-ico'); ?>
                                                                Clone
                                                            </a>
                                                        </li>
                                                        <?php endif; ?>
                                                        <hr class="dropdown-divider mb-0">
                                                        <span class="dropdown-item-text" style="padding: 5px 10px; display: block;"> <?php echo $lang['Color']; ?>:</span>
                                                        <li>
                                                            <a class="dropdown-item" href="documents?color=default&note_id=<?php echo $note['id']; ?>">
                                                                <span style="display:inline-block;width:16px;height:16px;background:#ced4da;border-radius:3px;margin-right:8px;border:1px solid #adb5bd;"></span> <?php echo $lang['Default color']; ?>
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item" href="documents?color=blue&note_id=<?php echo $note['id']; ?>">
                                                                <span style="display:inline-block;width:16px;height:16px;background:#007bff;border-radius:3px;margin-right:8px;"></span> <?php echo $lang['Blue']; ?>
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item" href="documents?color=green&note_id=<?php echo $note['id']; ?>">
                                                                <span style="display:inline-block;width:16px;height:16px;background:#28a745;border-radius:3px;margin-right:8px;"></span> <?php echo $lang['Green']; ?>
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item" href="documents?color=yellow&note_id=<?php echo $note['id']; ?>">
                                                                <span style="display:inline-block;width:16px;height:16px;background:#ffc107;border-radius:3px;margin-right:8px;"></span> <?php echo $lang['Yellow']; ?>
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            <div class="col-lg-9 px-0 bg-white private-note notes-editor-loading admin-docs-editor" id="privateNoteEditorPane">
                                <?php if ($message) echo $message; ?>
                                <div>
                                    <form method="post" action="" id="noteForm">
                                        <input type="hidden" name="note_id" value="<?php echo (!empty($edit_note['_is_profile_shared'])) ? '' : (isset($edit_note['id']) ? (int)$edit_note['id'] : (isset($_GET['note_id']) ? intval($_GET['note_id']) : '')); ?>">
                                        <input type="hidden" name="profile_shared_note_id" value="<?php echo !empty($edit_note['profile_note_id']) ? (int)$edit_note['profile_note_id'] : ''; ?>">
                                        <input type="hidden" name="note_updated_at" id="noteUpdatedAt" value="<?php echo isset($edit_note['updated_at']) ? htmlspecialchars((string)$edit_note['updated_at'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                                        <div class="form-group mb-2">
                                            <textarea name="note_content" class="form-control notes-textarea" placeholder="<?php echo $lang['Type your notes here...']; ?>" <?php echo (!$current_can_edit && !isset($_GET['new'])) ? 'readonly' : ''; ?>><?php echo isset($edit_note['content']) ? htmlspecialchars($edit_note['content']) : ''; ?></textarea>
                                            <textarea name="title" class="note-title-under-editor" placeholder="<?php echo $lang['Title']; ?>" rows="1" required <?php echo (!$current_can_edit && !isset($_GET['new'])) ? 'readonly' : ''; ?>><?php echo isset($edit_note['title']) ? htmlspecialchars($edit_note['title']) : ''; ?></textarea>
                                        </div>
                                        <button type="submit" id="hiddenSubmitBtn" name="save_note" style="display:none;" <?php echo (!$current_can_edit && !isset($_GET['new'])) ? 'disabled' : ''; ?>></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once(__DIR__ . '/../includes/private_notes/share_note_modal.php'); ?>
<?php $richEditorV = @filemtime(__DIR__ . '/../assets/js/rich-editor.js') ?: time(); ?>
<script src="../assets/js/rich-editor.js?v=<?php echo (int)$richEditorV; ?>"></script>
<?php if (empty($edit_note['_is_profile_shared'])): ?>
<script>
window.PRIVATE_NOTE_SHARE = {
    noteId: <?php echo isset($edit_note['id']) ? (int)$edit_note['id'] : 0; ?>,
    ajaxBase: '../ajax/private_notes/',
    canEdit: <?php echo $current_can_edit ? 'true' : 'false'; ?>,
    csrfToken: <?php echo json_encode($csrfToken); ?>
};
</script>
<script src="../assets/js/private-notes-share.js"></script>
<?php elseif (!empty($current_can_edit) && !empty($edit_note['profile_note_id'])): ?>
<script>
window.PROFILE_NOTE_CLIENT_SAVE = {
    profileNoteId: <?php echo (int)$edit_note['profile_note_id']; ?>,
    ajaxUrl: '../ajax/profile_notes/client_save_shared.php',
    csrfToken: <?php echo json_encode($csrfToken); ?>
};
</script>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var pane = document.getElementById('privateNoteEditorPane');
    if (!pane) return;
    var noteId = <?php echo (!empty($edit_note['_is_profile_shared'])) ? 0 : (isset($edit_note['id']) ? (int)$edit_note['id'] : 0); ?>;
    var canEdit = <?php echo $current_can_edit ? 'true' : 'false'; ?>;

    function mountTitleBetweenToolbarAndBody() {
        var titleField = pane.querySelector('.note-title-under-editor');
        var editorContainer = pane.querySelector('.rich-editor-container');
        var toolbar = editorContainer ? editorContainer.querySelector('.rich-editor-toolbar') : null;
        var content = editorContainer ? editorContainer.querySelector('.rich-editor-content') : null;
        if (!titleField || !editorContainer || !toolbar || !content) return false;

        if (titleField.parentNode !== editorContainer || titleField.previousElementSibling !== toolbar) {
            toolbar.insertAdjacentElement('afterend', titleField);
        }

        var resizeTitle = function () {
            titleField.style.height = 'auto';
            var minH = 60;
            var nextH = Math.max(minH, titleField.scrollHeight);
            titleField.style.height = nextH + 'px';
            titleField.style.overflowY = 'hidden';
        };
        if (!titleField.dataset.autosizeBound) {
            titleField.addEventListener('input', resizeTitle);
            window.addEventListener('resize', resizeTitle);
            titleField.dataset.autosizeBound = '1';
        }
        setTimeout(resizeTitle, 0);
        resizeTitle();
        return true;
    }

    var tries = 0;
    var maxTries = 60;
    var timer = setInterval(function () {
        var editorReady = pane.querySelector('.rich-editor-container .rich-editor-content');
        if ((editorReady && mountTitleBetweenToolbarAndBody()) || tries >= maxTries) {
            pane.classList.remove('notes-editor-loading');
            setTimeout(mountTitleBetweenToolbarAndBody, 120);
            clearInterval(timer);
        }
        tries++;
    }, 50);

    if (window.PROFILE_NOTE_CLIENT_SAVE && canEdit) {
        var pcfg = window.PROFILE_NOTE_CLIENT_SAVE;
        var saveTimer = null;
        var statusEl = document.getElementById('noteAutosaveStatus');
        function bindProfileSharedAutosave() {
            var editorContent = pane.querySelector('.rich-editor-container .rich-editor-content');
            if (!editorContent) return false;
            function scheduleSave() {
                clearTimeout(saveTimer);
                saveTimer = setTimeout(function () {
                    var html = editorContent.innerHTML || '';
                    if (statusEl) {
                        statusEl.textContent = 'Saving...';
                        statusEl.classList.remove('d-none');
                    }
                    fetch(pcfg.ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': pcfg.csrfToken },
                        body: JSON.stringify({
                            profile_note_id: pcfg.profileNoteId,
                            note_content: html,
                            csrf_token: pcfg.csrfToken
                        })
                    })
                        .then(function (r) {
                            return r.json();
                        })
                        .then(function (body) {
                            if (statusEl) {
                                statusEl.classList.add('d-none');
                            }
                            if (!body || body.status !== 'ok') {
                                throw new Error('save');
                            }
                        })
                        .catch(function () {
                            if (statusEl) {
                                statusEl.textContent = 'Not saved';
                                statusEl.classList.remove('d-none');
                            }
                        });
                }, 2000);
            }
            editorContent.addEventListener('input', scheduleSave);
            return true;
        }
        var pt = 0;
        var pint = setInterval(function () {
            if (bindProfileSharedAutosave() || pt++ > 80) {
                clearInterval(pint);
            }
        }, 100);
    }

    if (!canEdit) {
        var noteForm = document.getElementById('noteForm');
        if (noteForm) {
            noteForm.addEventListener('submit', function (e) {
                e.preventDefault();
                alert('You have view-only permission for this note.');
            });
        }
    }
});
</script>
<?php $__aiCtx = __DIR__ . '/../includes/ai_contextual_snippet.php';
if (is_file($__aiCtx)) { require_once $__aiCtx; if (function_exists('ai_contextual_emit')) { ai_contextual_emit(); } } ?>
<?php include("../templates/main-footer.php"); ?> 