<?php
if (!isset($pnFilterBuild) || !is_callable($pnFilterBuild)) {
	return;
}
$build = $pnFilterBuild;
$labAllColors = $lang['All Colors'] ?? 'All colors';
$labBlue = $lang['Blue'] ?? 'Blue';
$labGreen = $lang['Green'] ?? 'Green';
$labYellow = $lang['Yellow'] ?? 'Yellow';
$labArchive = $lang['Archive'] ?? 'Archive';
$labRecent = 'Recent';
$filterPanelId = isset($projectNotesFilterPanelId) && (string) $projectNotesFilterPanelId !== ''
	? (string) $projectNotesFilterPanelId
	: 'projectNotesFilterDropdown';
?>
<div id="<?php echo htmlspecialchars($filterPanelId, ENT_QUOTES, 'UTF-8'); ?>" class="toggle-action justify collapse shadow-dept">
	<ul>
		<li class="<?php echo $colorF === '' ? 'active' : ''; ?>"><a href="<?php echo htmlspecialchars($build(['color_filter' => null]), ENT_QUOTES, 'UTF-8'); ?>"><span><?php echo htmlspecialchars($labAllColors, ENT_QUOTES, 'UTF-8'); ?></span></a></li>
		<li class="<?php echo $colorF === 'blue' ? 'active' : ''; ?>"><a href="<?php echo htmlspecialchars($build(['color_filter' => 'blue']), ENT_QUOTES, 'UTF-8'); ?>"><span style="color:#007bff;"><?php echo htmlspecialchars($labBlue, ENT_QUOTES, 'UTF-8'); ?></span></a></li>
		<li class="<?php echo $colorF === 'green' ? 'active' : ''; ?>"><a href="<?php echo htmlspecialchars($build(['color_filter' => 'green']), ENT_QUOTES, 'UTF-8'); ?>"><span style="color:#28a745;"><?php echo htmlspecialchars($labGreen, ENT_QUOTES, 'UTF-8'); ?></span></a></li>
		<li class="<?php echo $colorF === 'yellow' ? 'active' : ''; ?>"><a href="<?php echo htmlspecialchars($build(['color_filter' => 'yellow']), ENT_QUOTES, 'UTF-8'); ?>"><span style="color:#ffc107;"><?php echo htmlspecialchars($labYellow, ENT_QUOTES, 'UTF-8'); ?></span></a></li>
		<hr class="dropdown-divider mb-0">
		<li class="<?php echo $scopeF === 'recent' ? 'active' : ''; ?>"><a href="<?php echo htmlspecialchars($build(['scope_filter' => 'recent']), ENT_QUOTES, 'UTF-8'); ?>"><span><?php echo htmlspecialchars($labRecent, ENT_QUOTES, 'UTF-8'); ?></span></a></li>
		<li class="<?php echo $isArchive ? 'active' : ''; ?>"><a href="<?php echo htmlspecialchars($build(['project_notes_list' => 'archive', 'scope_filter' => null]), ENT_QUOTES, 'UTF-8'); ?>"><span><?php echo htmlspecialchars($labArchive, ENT_QUOTES, 'UTF-8'); ?></span></a></li>
		<?php if ($isArchive || $colorF !== '' || $scopeF !== '') : ?>
		<li><a href="<?php echo htmlspecialchars($build(['project_notes_list' => null, 'color_filter' => null, 'scope_filter' => null]), ENT_QUOTES, 'UTF-8'); ?>"><span>Active notes</span></a></li>
		<?php endif; ?>
	</ul>
</div>
