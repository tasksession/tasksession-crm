<?php
/**
 * Mobile hamburger: Create New (primary) — same as Add New Task.
 * Filter links are output before this include from profile-notes-filters-hamburger.php.
 */
if (!isset($user_id)) {
	return;
}
$pnNew = 'profile?user_id=' . (int) $user_id . '&tab=notes&new=1';
$pnCreate = isset($lang['Create New']) ? $lang['Create New'] : 'Create New';
?>
<li>
	<a href="<?php echo htmlspecialchars($pnNew, ENT_QUOTES, 'UTF-8'); ?>" class="primary-btn w-100"><?php echo htmlspecialchars($pnCreate, ENT_QUOTES, 'UTF-8'); ?></a>
</li>
