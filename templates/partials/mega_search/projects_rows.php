<?php
/** @var array $megaSearchRows @var array $megaSearchCtx */
global $database, $lang;

require_once dirname(__DIR__, 3) . '/includes/list-bulk-helpers.php';

$ctx = $megaSearchCtx;
$rows = $megaSearchRows;
$lang = is_array($ctx['lang'] ?? null) && ($ctx['lang'] !== []) ? $ctx['lang'] : ($lang ?? []);
$projectsTableLinkBase = (string) ($ctx['panel_base'] ?? '');
$projectsTableDiscussionBase = rtrim((string) ($ctx['url'] ?? ''), '/') . '/';
$projectsTableFormAction = $projectsTableLinkBase . 'projects';

$settingsForModules = $ctx['settings'] ?? settings::findById(1);
$currency_symbol = '$';
if ($settingsForModules && !empty($settingsForModules->system_currency)) {
	$parts = explode(',', (string) $settingsForModules->system_currency);
	if (count($parts) >= 2) {
		$currency_symbol = trim($parts[1]);
	}
}

$recentProjects = [];
foreach ($rows as $row) {
	$recentProjects[] = (object) $row;
}
if (($megaSearchOutputMode ?? 'full') === 'rows') {
	include dirname(__DIR__, 3) . '/partials/projects_table_rows.php';
	return;
}
?>
<div class="vh-100" data-ts-list-bulk-root="projects">
	<div class="table-responsive scroll-x vh-100">
		<table class="table table-new projectspage">
			<thead>
				<tr>
					<?php echo tasksession_list_bulk_checkbox_th_html(); ?>
					<th width="26%"><?php echo htmlspecialchars($lang['Project Name'] ?? 'Project name', ENT_QUOTES, 'UTF-8'); ?></th>
					<th><?php echo htmlspecialchars($lang['Assigned Team'] ?? 'Assigned team', ENT_QUOTES, 'UTF-8'); ?></th>
					<th><?php echo htmlspecialchars($lang['Client'] ?? 'Client', ENT_QUOTES, 'UTF-8'); ?></th>
					<th><?php echo htmlspecialchars($lang['Deadline'] ?? 'Deadline', ENT_QUOTES, 'UTF-8'); ?></th>
					<th><?php echo htmlspecialchars($lang['Status'] ?? 'Status', ENT_QUOTES, 'UTF-8'); ?></th>
					<th><?php echo htmlspecialchars($lang['Budget'] ?? 'Budget', ENT_QUOTES, 'UTF-8'); ?></th>
					<th><?php echo htmlspecialchars($lang['Task'] ?? 'Task', ENT_QUOTES, 'UTF-8'); ?></th>
					<th><?php echo htmlspecialchars($lang['Options'] ?? 'Options', ENT_QUOTES, 'UTF-8'); ?></th>
				</tr>
			</thead>
			<tbody id="projects-tbl">
				<?php include dirname(__DIR__, 3) . '/partials/projects_table_rows.php'; ?>
			</tbody>
		</table>
	</div>
</div>
