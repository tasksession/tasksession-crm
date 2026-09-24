<?php
/** @var array $megaSearchRows @var array $megaSearchCtx */
global $database, $lang;

$ctx = $megaSearchCtx;
$rows = $megaSearchRows;
$lang = is_array($ctx['lang'] ?? null) && ($ctx['lang'] !== []) ? $ctx['lang'] : ($lang ?? []);
$tasks = $rows;
$allTasksTableLinkBase = (string) ($ctx['panel_base'] ?? '');
$allTasksCloneBase = rtrim((string) ($ctx['url'] ?? ''), '/') . '/includes/';
$searchPageUrl = rtrim((string) ($ctx['url'] ?? ''), '/') . '/search';
$allTasksRedirectUri = $searchPageUrl . (isset($_GET['q']) ? '?q=' . rawurlencode((string) $_GET['q']) : '');
if (isset($_GET['tab']) && (string) $_GET['tab'] !== '') {
    $allTasksRedirectUri .= (strpos($allTasksRedirectUri, '?') !== false ? '&' : '?') . 'tab=' . rawurlencode((string) $_GET['tab']);
}
$showArchive = false;

$statusConfig = mega_search_all_tasks_status_config($database, $lang);
$statusLabels = $statusConfig['labels'];
$statusColors = $statusConfig['colors'];
$allTasksActionsUi = 'legacy';
require_once dirname(__DIR__, 3) . '/includes/all_tasks_table_helper.php';

ob_start();
include dirname(__DIR__, 3) . '/partials/all_tasks_table_rows.php';
$taskRowsHtml = ob_get_clean();
$taskRowsHtml = preg_replace('/\xEF\xBB\xBF/u', '', $taskRowsHtml);
$taskRowsHtml = preg_replace('/\x{FEFF}/u', '', $taskRowsHtml);

if (($megaSearchOutputMode ?? 'full') === 'rows') {
    echo $taskRowsHtml;
    return;
}

echo '<div class="table-responsive mega-search-tasks-table-wrap"><table class="table table-new projectspage all-tasks-table" id="tasks-table">';
echo allTasksTableTheadHtml($lang, true);
echo '<tbody id="projects-tbl">' . $taskRowsHtml . '</tbody></table></div>';
