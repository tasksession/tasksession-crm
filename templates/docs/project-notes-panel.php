<?php
if (!isset($projectId) || !isset($projectNotesFormAction)) {
	return;
}
$notes = isset($notes) && is_array($notes) ? $notes : [];
$lang = isset($lang) && is_array($lang) ? $lang : [];
$labUpdated = $lang['Updated'] ?? 'Updated';
$labDelete = $lang['Delete'] ?? 'Delete';
$labNoNotes = $lang['No notes yet'] ?? 'No notes yet.';
$labColor = $lang['Color'] ?? 'Color';
$labDefaultColor = $lang['Default color'] ?? 'Default color';
$labBlue = $lang['Blue'] ?? 'Blue';
$labGreen = $lang['Green'] ?? 'Green';
$labYellow = $lang['Yellow'] ?? 'Yellow';
$labClone = $lang['Clone'] ?? 'Clone';
$labShare = $lang['Share'] ?? 'Share';
$labSharedWith = $lang['Shared with'] ?? 'Shared with';
$labReadOnly = $lang['Read only'] ?? 'Read only';
$labReadEdit = $lang['Read & Edit'] ?? 'Read & Edit';
$projectNoteShareMap = isset($projectNoteShareMap) && is_array($projectNoteShareMap) ? $projectNoteShareMap : [];
$labArchive = $lang['Archive'] ?? 'Archive';
$labUnarchive = $lang['Unarchive'] ?? 'Restore to list';
$pid = (int) $projectId;
$__htmlEntityFlags = defined('ENT_HTML5') ? (ENT_QUOTES | ENT_HTML5) : ENT_QUOTES;
$pnq = isset($projectListQuery) ? (string) $projectListQuery : '';
$pnList = isset($projectNotesList) && $projectNotesList === 'archive' ? 'archive' : 'active';
$baseNote = 'notes.php?projectId=' . $pid . $pnq;
$titleFor = function (array $row) use ($__htmlEntityFlags) {
	$raw = (string) ($row['content'] ?? '');
	$raw = html_entity_decode($raw, $__htmlEntityFlags, 'UTF-8');
	$plain = trim(strip_tags($raw));
	$id = (int) ($row['id'] ?? 0);
	if (isset($row['title']) && (string) $row['title'] !== '') {
		return (string) html_entity_decode((string) $row['title'], $__htmlEntityFlags, 'UTF-8');
	}
	if ($plain === '') {
		return 'Note #' . $id;
	}
	$oneLine = preg_replace('/\s+/', ' ', $plain);
	if (function_exists('mb_substr')) {
		return mb_strlen($oneLine) > 50 ? mb_substr($oneLine, 0, 50) . '…' : $oneLine;
	}
	return strlen($oneLine) > 50 ? substr($oneLine, 0, 50) . '…' : $oneLine;
};
$pnMediaCsrf = isset($profileNotesCsrfToken) ? (string) $profileNotesCsrfToken : '';
if ($pnMediaCsrf === '' && function_exists('generate_csrf_token')) {
	$pnMediaCsrf = (string) generate_csrf_token();
}
global $url;
$pnAppBase = (isset($url) && (string) $url !== '') ? rtrim((string) $url, '/') . '/' : '';
?>
<div id="profileNotesMediaConfig" class="d-none" aria-hidden="true" data-csrf="<?php echo htmlspecialchars($pnMediaCsrf, ENT_QUOTES, 'UTF-8'); ?>" data-app-base="<?php echo htmlspecialchars($pnAppBase, ENT_QUOTES, 'UTF-8'); ?>"></div>
<div class="profile-user-notes profile-notes-board admin-docs-split">
	<div class="profile-notes-board__sidebar br-right bg-white pd-0 admin-docs-sidebar">
		<ul class="list-group admin-docs-sidebar-list scroll-bar">
			<?php if (empty($notes)): ?>
				<li class="list-group-item text-center card" style="padding: 40px 10px; margin: 20px;"><?php echo htmlspecialchars($labNoNotes, ENT_QUOTES, 'UTF-8'); ?></li>
			<?php else: foreach ($notes as $note) :
				$cid = (int) ($note['id'] ?? 0);
				$colorClass = !empty($note['color']) ? 'note-card-' . preg_replace('/[^a-z0-9_-]/i', '', (string) $note['color']) : '';
				$isActive = isset($edit_note['id']) && (int) $edit_note['id'] === $cid;
				$prevRaw = html_entity_decode((string) ($note['content'] ?? ''), $__htmlEntityFlags, 'UTF-8');
				$preview = strip_tags($prevRaw);
				if (function_exists('mb_strlen') && mb_strlen($preview) > 80) {
					$preview = mb_substr($preview, 0, 80) . '…';
				} elseif (strlen($preview) > 80) {
					$preview = substr($preview, 0, 80) . '…';
				}
				$pnShareList = $projectNoteShareMap[$cid] ?? [];
				$hasProjectShares = !empty($pnShareList);
				$pnAnyEdit = false;
				if ($hasProjectShares) {
					foreach ($pnShareList as $___sp) {
						if (($___sp['permission'] ?? 'view') === 'edit') {
							$pnAnyEdit = true;
							break;
						}
					}
				}
				$pnPermBadge = $pnAnyEdit ? $labReadEdit : $labReadOnly;
			?>
			<li class="list-group-item note-card<?php echo $isActive ? ' active' : ''; ?><?php echo $colorClass !== '' ? ' ' . htmlspecialchars($colorClass, ENT_QUOTES, 'UTF-8') : ''; ?>" style="position: relative;">
				<a href="<?php echo htmlspecialchars($baseNote . '&note_id=' . $cid, ENT_QUOTES, 'UTF-8'); ?>" class="note-card-link">
					<div>
						<strong class="card-title mb-1 d-block"><?php echo htmlspecialchars($titleFor($note), ENT_QUOTES, 'UTF-8'); ?></strong>
						<div class="note-preview mb-3 text-muted small"><?php echo htmlspecialchars($preview, ENT_QUOTES, 'UTF-8'); ?></div>
						<div class="d-flex doc-date small">
							<?php echo isset($note['created_at']) ? date('Y-m-d', strtotime((string) $note['created_at'])) : ''; ?>
							<?php $ca = $note['created_at'] ?? ''; $ua = $note['updated_at'] ?? '';
							if ($ca && $ua && $ca !== $ua) { echo ' &nbsp;|&nbsp; <span><b>' . htmlspecialchars($labUpdated, ENT_QUOTES, 'UTF-8') . ':</b> ' . date('Y-m-d', strtotime((string) $ua)) . '</span>'; } ?>
						</div>
						<?php if ($hasProjectShares) : $__vis = array_slice($pnShareList, 0, 2); $__extra = count($pnShareList) - 2; ?>
						<div class="mt-2">
							<div class="grey font-size-11 mb-1"><?php echo htmlspecialchars($labSharedWith, ENT_QUOTES, 'UTF-8'); ?></div>
							<div class="team-col d-flex align-items-baseline justify-content-between">
								<div class="d-flex avatar-head">
								<?php foreach ($__vis as $sharedUser) :
									$suid = (int) $sharedUser['user_id'];
									$sfirst = (string) ($sharedUser['first_name'] ?? '');
									$sfull = trim($sfirst) !== '' ? trim($sfirst) : 'User'; ?>
									<div class="avatar-overlap" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="<?php echo htmlspecialchars($sfull, ENT_QUOTES, 'UTF-8'); ?>" data-bs-original-title="<?php echo htmlspecialchars($sfull, ENT_QUOTES, 'UTF-8'); ?>" style="cursor:pointer;" onclick="event.preventDefault(); event.stopPropagation(); window.location.href='profile?user_id=<?php echo (int) $suid; ?>';">
										<?php echo getUserAvatarHtml($suid, $sfirst, '', 30, 30, 'rounded-circle', $sfull); ?>
									</div>
								<?php endforeach; if ($__extra > 0) : ?>
									<div class="avatar-overlap d-flex align-items-center justify-content-center" style="font-size:12px;font-weight:600;min-width:30px;min-height:30px;">+<?php echo (int) $__extra; ?></div>
								<?php endif; ?>
								</div>
								<div class="badge"><span class="doc-bdge"><?php echo htmlspecialchars($pnPermBadge, ENT_QUOTES, 'UTF-8'); ?></span></div>
							</div>
						</div>
						<?php endif; ?>
					</div>
				</a>
				<div class="dropdown note-card-menu" style="position: absolute; top: 10px; right: 15px;">
					<button class="btn-dots dropdown-toggle" type="button" id="projectNoteMenu<?php echo (int) $cid; ?>" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" style="color: #888; text-decoration: none;">
						<?php echo ts_icon('dots-vertical', 'w-6'); ?>
					</button>
					<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="projectNoteMenu<?php echo (int) $cid; ?>">
						<li><a class="dropdown-item d-flex align-items-center text-danger" href="<?php echo htmlspecialchars($baseNote . '&delete=' . $cid, ENT_QUOTES, 'UTF-8'); ?>" onclick="return confirm('<?php echo htmlspecialchars($labDelete, ENT_QUOTES, 'UTF-8'); ?>');"><?php echo ts_icon('delete', 'w-4 me-2 tasksession-timer-log-menu-ico'); ?><?php echo htmlspecialchars($labDelete, ENT_QUOTES, 'UTF-8'); ?></a></li>
						<?php if ($pnList === 'archive') : ?>
						<li><a class="dropdown-item d-flex align-items-center" href="<?php echo htmlspecialchars($baseNote . '&unarchive=' . $cid, ENT_QUOTES, 'UTF-8'); ?>" onclick="return confirm('<?php echo htmlspecialchars($labUnarchive, ENT_QUOTES, 'UTF-8'); ?>');"><?php echo ts_icon('restore', 'w-4 me-2 tasksession-timer-log-menu-ico'); ?><?php echo htmlspecialchars($labUnarchive, ENT_QUOTES, 'UTF-8'); ?></a></li>
						<?php else : ?>
						<li><a class="dropdown-item d-flex align-items-center" href="<?php echo htmlspecialchars($baseNote . '&archive=' . $cid, ENT_QUOTES, 'UTF-8'); ?>" onclick="return confirm(<?php echo json_encode($lang['Archive this?'] ?? 'Archive this?'); ?>);"><?php echo ts_icon('archive', 'w-4 me-2 tasksession-timer-log-menu-ico'); ?><?php echo htmlspecialchars($labArchive, ENT_QUOTES, 'UTF-8'); ?></a></li>
						<?php endif; ?>
						<li><a class="dropdown-item d-flex align-items-center" href="<?php echo htmlspecialchars($baseNote . '&clone=' . $cid, ENT_QUOTES, 'UTF-8'); ?>" onclick="return confirm('<?php echo htmlspecialchars($lang['Create a copy of this note?'] ?? 'Create a copy of this note in All notes?', ENT_QUOTES, 'UTF-8'); ?>');"><?php echo ts_icon('duplicate', 'me-2 tasksession-timer-log-menu-ico'); ?><?php echo htmlspecialchars($labClone, ENT_QUOTES, 'UTF-8'); ?></a></li>
						<li>
							<a class="dropdown-item d-flex align-items-center" href="#" data-project-note-share="<?php echo (int) $cid; ?>">
								<?php echo ts_icon('share', 'w-4 me-2 tasksession-timer-log-menu-ico'); ?>
								<?php echo htmlspecialchars($labShare, ENT_QUOTES, 'UTF-8'); ?>
							</a>
						</li>
						<hr class="dropdown-divider mb-0">
						<span class="dropdown-item-text" style="padding: 5px 10px; display: block;"><?php echo htmlspecialchars($labColor, ENT_QUOTES, 'UTF-8'); ?>:</span>
						<li><a class="dropdown-item" href="<?php echo htmlspecialchars($baseNote . '&color=default&note_id=' . $cid, ENT_QUOTES, 'UTF-8'); ?>"><span style="display:inline-block;width:16px;height:16px;background:#ced4da;border-radius:3px;margin-right:8px;border:1px solid #adb5bd;"></span> <?php echo htmlspecialchars($labDefaultColor, ENT_QUOTES, 'UTF-8'); ?></a></li>
						<li><a class="dropdown-item" href="<?php echo htmlspecialchars($baseNote . '&color=blue&note_id=' . $cid, ENT_QUOTES, 'UTF-8'); ?>"><span style="display:inline-block;width:16px;height:16px;background:#007bff;border-radius:3px;margin-right:8px;"></span> <?php echo htmlspecialchars($labBlue, ENT_QUOTES, 'UTF-8'); ?></a></li>
						<li><a class="dropdown-item" href="<?php echo htmlspecialchars($baseNote . '&color=green&note_id=' . $cid, ENT_QUOTES, 'UTF-8'); ?>"><span style="display:inline-block;width:16px;height:16px;background:#28a745;border-radius:3px;margin-right:8px;"></span> <?php echo htmlspecialchars($labGreen, ENT_QUOTES, 'UTF-8'); ?></a></li>
						<li><a class="dropdown-item" href="<?php echo htmlspecialchars($baseNote . '&color=yellow&note_id=' . $cid, ENT_QUOTES, 'UTF-8'); ?>"><span style="display:inline-block;width:16px;height:16px;background:#ffc107;border-radius:3px;margin-right:8px;"></span> <?php echo htmlspecialchars($labYellow, ENT_QUOTES, 'UTF-8'); ?></a></li>
					</ul>
				</div>
			</li>
			<?php endforeach; endif; ?>
		</ul>
	</div>
	<div class="profile-notes-board__editor px-0 bg-white private-note notes-editor-loading admin-docs-editor" id="user-notes-editor">
		<?php if (!empty($notes_message)) { echo $notes_message; } ?>
		<div class="profile-doc">
			<form method="post" action="<?php echo htmlspecialchars($projectNotesFormAction, ENT_QUOTES, 'UTF-8'); ?>" id="noteForm">
				<?php
				$pnId = 0;
				if (isset($edit_note['id']) && (int) $edit_note['id'] > 0) {
					$pnId = (int) $edit_note['id'];
				} elseif (isset($_GET['note_id']) && (int) $_GET['note_id'] > 0) {
					$pnId = (int) $_GET['note_id'];
				}
				$pnUpdatedAt = (isset($edit_note['updated_at']) && (string) $edit_note['updated_at'] !== '') ? (string) $edit_note['updated_at'] : '';
				?>
				<input type="hidden" name="note_id" value="<?php echo $pnId > 0 ? (int) $pnId : 0; ?>">
				<input type="hidden" name="note_updated_at" id="noteUpdatedAt" value="<?php echo htmlspecialchars($pnUpdatedAt, ENT_QUOTES, 'UTF-8'); ?>">
				<div class="form-group mb-2">
					<textarea name="note_content" class="form-control notes-textarea" rows="12" placeholder="Type your notes here..."><?php echo isset($edit_note['content']) ? htmlspecialchars($edit_note['content'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
				</div>
			</form>
		</div>
	</div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
	var pane = document.getElementById('user-notes-editor');
	if (!pane) return;
	var tries = 0;
	var maxTries = 60;
	var timer = setInterval(function () {
		var editorReady = pane.querySelector('.rich-editor-container .rich-editor-content');
		if (editorReady || tries >= maxTries) {
			pane.classList.remove('notes-editor-loading');
			clearInterval(timer);
		}
		tries++;
	}, 50);
});
</script>
<?php
$notesToastFlash = isset($notes_toast_flash) ? $notes_toast_flash : null;
if (is_array($notesToastFlash) && !empty($notesToastFlash['msg'])) {
	$notesToastJs = '../assets/js/toast.js';
	if (!empty($url)) {
		$notesToastJs = rtrim((string) $url, '/') . '/assets/js/toast.js';
	}
	$notesToastJson = json_encode($notesToastFlash, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
	?>
<script>window.__toastFlash=<?php echo $notesToastJson; ?>;</script>
<script src="<?php echo htmlspecialchars($notesToastJs, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php } ?>
