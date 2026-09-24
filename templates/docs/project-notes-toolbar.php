<?php
if (!isset($projectId)) {
	return;
}
$pnNew = 'notes.php?projectId=' . (int) $projectId . '&new=1';
$pnCreate = isset($lang['Create New']) ? $lang['Create New'] : 'Create New';
?>
<li>
	<a href="<?php echo htmlspecialchars($pnNew, ENT_QUOTES, 'UTF-8'); ?>" class="primary-btn w-100"><?php echo htmlspecialchars($pnCreate, ENT_QUOTES, 'UTF-8'); ?></a>
</li>
