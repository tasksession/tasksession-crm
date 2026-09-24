<?php
/**
 * Desktop: same row as search + filters — primary CTA (matches Add New Task).
 */
if (!isset($user_id)) {
	return;
}
$pnNew = 'profile?user_id=' . (int) $user_id . '&tab=notes&new=1';
$pnCreate = isset($lang['Create New']) ? $lang['Create New'] : 'Create New';
?>
<div class="edit-overview-btn d-none d-md-block profile-notes-create-btn">
	<a href="<?php echo htmlspecialchars($pnNew, ENT_QUOTES, 'UTF-8'); ?>" class="primary-btn"><?php echo htmlspecialchars($pnCreate, ENT_QUOTES, 'UTF-8'); ?> <?php echo ts_icon('plus', 'w-2'); ?></a>
</div>
