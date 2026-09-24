<?php
/**
 * Global mega search results page.
 */
ob_start();
require_once __DIR__ . '/includes/lib-initialize.php';
require_once __DIR__ . '/includes/mega_search_helper.php';
require_once __DIR__ . '/includes/mega_search_render.php';
require_once __DIR__ . '/includes/mega_search_bulk.php';
require_once __DIR__ . '/includes/list-bulk-helpers.php';

if (!$session->isLoggedIn()) {
    redirectTo($url . 'index.php');
}

$accountStatus = (int) ($_SESSION['accountStatus'] ?? 0);
$ctx = comon_mega_search_context($session);
$searchQuery = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$activeTab = isset($_GET['tab']) ? trim((string) $_GET['tab']) : 'people';
$searchPageUrl = rtrim((string) $url, '/') . '/search.php';
$searchHeading = mega_search_lang($ctx, 'Search', 'Search');
$megaSearchPlaceholder = comon_mega_search_placeholder($ctx);

if ($activeTab === '' || empty($ctx['tabs'][$activeTab]['enabled'])) {
    foreach ($ctx['tabs'] as $key => $t) {
        if (!empty($t['enabled'])) {
            $activeTab = $key;
            break;
        }
    }
}

$bulkTabPermissions = comon_mega_search_bulk_tab_permissions($ctx);
$searchClearUrl = $searchPageUrl . '?tab=' . rawurlencode($activeTab);
$hasAnyBulkPermission = false;
foreach ($bulkTabPermissions as $bulkAllowed) {
    if (!empty($bulkAllowed)) {
        $hasAnyBulkPermission = true;
        break;
    }
}

$title = 'Search | ' . $syatem_title;
include __DIR__ . '/templates/header.php';

$username = isset($userb->firstName) ? $userb->firstName : '';
$initialTabs = ($searchQuery !== '') ? comon_mega_search_run($searchQuery, $activeTab, $ctx) : [];
$totalResults = 0;
if ($searchQuery !== '' && is_array($initialTabs)) {
    foreach ($initialTabs as $tabResult) {
        $totalResults += (int) ($tabResult['count'] ?? 0);
    }
}
?>
<div class="page-container">
	<div class="container-fluid">
		<div class="row row-eq-height">
			<?php include __DIR__ . '/templates/sidebar.php'; ?>
			<div class="page-content">
				<?php include __DIR__ . '/templates/top-header.php'; ?>
				<link rel="stylesheet" href="<?php echo htmlspecialchars(rtrim((string) $url, '/') . '/assets/css/kanban-bulk.css?v=9', ENT_QUOTES, 'UTF-8'); ?>">
				<div class="row">
					<div class="col-md-12 margin-top-10 mega-search-page">
						<div class="row bg-grey">
							<div class="col-md-12 project-tabs">
								<div class="row">
									<div class="project-tabs-header">
										<div class="scrollable-tabs-container d-flex col-gap-40 col-gap-40-sep">
											<div class="main-heading">
												<h1><?php echo htmlspecialchars($searchHeading, ENT_QUOTES, 'UTF-8'); ?>
													<?php if ($searchQuery !== ''): ?>
														<span>(<?php echo (int) $totalResults; ?>)</span>
													<?php endif; ?>
												</h1>
											</div>
											<div class="icon-container sep">
												<div class="icon-container members-team-tabs mega-search-tabs" id="megaSearchTabs" role="tablist">
													<?php foreach ($ctx['tabs'] as $key => $tabCfg): if (empty($tabCfg['enabled'])) { continue; }
														$cnt = isset($initialTabs[$key]['count']) ? (int) $initialTabs[$key]['count'] : 0;
														$isActive = ($key === $activeTab);
													?>
													<button type="button" class="mega-search-tabs__btn<?php echo $isActive ? ' active' : ''; ?>" data-tab="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" role="tab" aria-selected="<?php echo $isActive ? 'true' : 'false'; ?>">
														<?php echo htmlspecialchars((string) $tabCfg['label'], ENT_QUOTES, 'UTF-8'); ?>
														<span class="mega-search-tabs__count" data-tab-count="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"><?php echo (int) $cnt; ?></span>
													</button>
													<?php endforeach; ?>
												</div>
											</div>
										</div>
									</div>
									<div class="search">
										<div class="search-icon border-btn-a" onclick="toggleSearch()">
											<?php echo ts_icon('search', 'w-2'); ?>
										</div>
										<form method="GET" action="<?php echo htmlspecialchars($searchPageUrl, ENT_QUOTES, 'UTF-8'); ?>" class="search-form" id="megaSearchPageForm">
											<input type="hidden" name="tab" id="mega-search-page-tab" value="<?php echo htmlspecialchars($activeTab, ENT_QUOTES, 'UTF-8'); ?>">
											<div class="input-group">
												<span class="search-field-icon">
													<?php echo ts_icon('search', 'w-2'); ?>
												</span>
												<input type="text" id="mega-search-page-input" name="q" class="form-control" placeholder="<?php echo htmlspecialchars($megaSearchPlaceholder, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" aria-label="<?php echo htmlspecialchars($searchHeading, ENT_QUOTES, 'UTF-8'); ?>">
												<?php if ($searchQuery !== ''): ?>
													<a href="<?php echo htmlspecialchars($searchClearUrl, ENT_QUOTES, 'UTF-8'); ?>" class="cross" title="<?php echo htmlspecialchars(mega_search_lang($ctx, 'Close', 'Close'), ENT_QUOTES, 'UTF-8'); ?>">
														<?php echo ts_icon('close', 'w-2'); ?>
													</a>
												<?php else: ?>
													<a href="#" class="cross" onclick="toggleSearch(); return false;" title="<?php echo htmlspecialchars(mega_search_lang($ctx, 'Close', 'Close'), ENT_QUOTES, 'UTF-8'); ?>">
														<?php echo ts_icon('close', 'w-2'); ?>
													</a>
												<?php endif; ?>
											</div>
										</form>
									</div>
									<?php if ($hasAnyBulkPermission): ?>
									<div class="edit-overview-btn ts-list-bulk-toolbar-wrap mega-search-bulk-toolbar-wrap d-none d-md-block" id="megaSearchBulkToolbar"<?php echo empty($bulkTabPermissions[$activeTab]) ? ' style="display:none;"' : ''; ?>>
										<div class="icon-container sep ts-list-bulk-toolbar" data-mega-search-bulk-toolbar>
											<div class="pm-trash task-trash align-middle d-flex col-gap-5">
												<a href="#" class="bulk-delete-tab red border-btn-a" id="megaSearchBulkSelectTab" title="<?php echo htmlspecialchars(mega_search_lang($ctx, 'Bulk Select', 'Bulk Select'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(mega_search_lang($ctx, 'Bulk Select', 'Bulk Select'), ENT_QUOTES, 'UTF-8'); ?>">
													<?php echo ts_icon('duplicate', 'w-2'); ?>
												</a>
												<a href="#" class="bulk-delete-tab border-btn-a" id="megaSearchBulkBackTab" style="display:none;">
													<?php echo ts_icon('arrow-left', 'w-2'); ?>
													<span><?php echo htmlspecialchars(mega_search_lang($ctx, 'Back', 'Back'), ENT_QUOTES, 'UTF-8'); ?></span>
												</a>
												<a href="#" class="bulk-delete-tab border-btn-a" id="megaSearchBulkSelectAllTab" style="display:none;">
													<?php echo ts_icon('check-circle', 'w-2'); ?>
													<span id="megaSearchBulkSelectAllLabel"><?php echo htmlspecialchars(mega_search_lang($ctx, 'Select all', 'Select all'), ENT_QUOTES, 'UTF-8'); ?></span>
												</a>
												<a href="#" class="bulk-delete-tab border-btn-a" id="megaSearchBulkDeleteTab" style="display:none;">
													<?php echo ts_icon('delete', 'w-2'); ?>
													<span id="megaSearchBulkDeleteLabel"><?php echo htmlspecialchars(mega_search_lang($ctx, 'Delete', 'Delete'), ENT_QUOTES, 'UTF-8'); ?></span>
												</a>
											</div>
										</div>
									</div>
									<?php endif; ?>
								</div>
							</div>
						</div>
						<div class="clearfix"></div>
						<input type="hidden" id="mega-search-tab-input" value="<?php echo htmlspecialchars($activeTab, ENT_QUOTES, 'UTF-8'); ?>">
						<div class="mega-search-results-wrap">
							<div id="mega-search-results" class="mega-search-results" aria-live="polite">
								<?php
								if ($searchQuery === '') {
                                    echo mega_search_render_empty($activeTab, '', $ctx);
								} elseif (isset($initialTabs[$activeTab]['html'])) {
                                    echo $initialTabs[$activeTab]['html'];
								} else {
                                    echo mega_search_render_empty($activeTab, $searchQuery, $ctx);
								}
								?>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<script>
window.MEGA_SEARCH = {
	searchUrl: <?php echo json_encode($searchPageUrl, JSON_UNESCAPED_SLASHES); ?>,
	ajaxUrl: <?php echo json_encode(rtrim((string) $url, '/') . '/ajax/mega_search.php', JSON_UNESCAPED_SLASHES); ?>,
	activeTab: <?php echo json_encode($activeTab, JSON_UNESCAPED_UNICODE); ?>,
	initialQuery: <?php echo json_encode($searchQuery, JSON_UNESCAPED_UNICODE); ?>,
	debounceMs: 300
};
window.MEGA_SEARCH_BULK = {
	ajaxUrl: <?php echo json_encode(rtrim((string) $url, '/') . '/ajax/mega_search_bulk.php', JSON_UNESCAPED_SLASHES); ?>,
	labels: {
		delete: <?php echo json_encode(mega_search_lang($ctx, 'Delete', 'Delete'), JSON_UNESCAPED_UNICODE); ?>,
		selectAll: <?php echo json_encode(mega_search_lang($ctx, 'Select all', 'Select all'), JSON_UNESCAPED_UNICODE); ?>,
		deselectAll: <?php echo json_encode(mega_search_lang($ctx, 'Deselect all', 'Deselect all'), JSON_UNESCAPED_UNICODE); ?>,
		confirm: <?php echo json_encode(mega_search_lang($ctx, 'Delete selected items?', 'Delete selected items?'), JSON_UNESCAPED_UNICODE); ?>
	},
	tabs: {
		people: {
			enabled: <?php echo !empty($bulkTabPermissions['people']) ? 'true' : 'false'; ?>,
			root: '#mega-search-results .mega-search-people-table-wrap',
			checkbox: 'input.client-bulk-checkbox:not(:disabled)',
			selectAll: '#select-all-clients',
			confirm: <?php echo json_encode(mega_search_lang($ctx, 'Delete selected users?', 'Delete selected users?'), JSON_UNESCAPED_UNICODE); ?>
		},
		companies: {
			enabled: <?php echo !empty($bulkTabPermissions['companies']) ? 'true' : 'false'; ?>,
			root: '#mega-search-results .mega-search-companies-table-wrap',
			checkbox: 'input.company-bulk-checkbox:not(:disabled)',
			selectAll: '#select-all-companies',
			confirm: <?php echo json_encode(mega_search_lang($ctx, 'Delete selected companies?', 'Delete selected companies?'), JSON_UNESCAPED_UNICODE); ?>
		},
		projects: {
			enabled: <?php echo !empty($bulkTabPermissions['projects']) ? 'true' : 'false'; ?>,
			root: '#mega-search-results [data-ts-list-bulk-root="projects"]',
			checkbox: 'input[name="btSelectItem"]:not(:disabled)',
			selectAll: null,
			confirm: <?php echo json_encode(mega_search_lang($ctx, 'Delete selected projects?', 'Delete selected projects?'), JSON_UNESCAPED_UNICODE); ?>
		},
		tasks: {
			enabled: <?php echo !empty($bulkTabPermissions['tasks']) ? 'true' : 'false'; ?>,
			root: '#mega-search-results #tasks-table',
			checkbox: '#tasks-table .task-checkbox:not(:disabled)',
			selectAll: '#select-all-tasks',
			confirm: <?php echo json_encode(mega_search_lang($ctx, 'Delete selected tasks?', 'Delete selected tasks?'), JSON_UNESCAPED_UNICODE); ?>
		},
		invoices: {
			enabled: <?php echo !empty($bulkTabPermissions['invoices']) ? 'true' : 'false'; ?>,
			root: '#mega-search-results .mega-search-invoices-table-wrap',
			checkbox: '.invoice-checkbox:not(:disabled)',
			selectAll: '#select-all-invoices',
			confirm: <?php echo json_encode(mega_search_lang($ctx, 'Delete selected invoices?', 'Delete selected invoices?'), JSON_UNESCAPED_UNICODE); ?>
		}
	}
};
</script>
<?php
echo tasksession_list_bulk_styles_tag();
$__msBulkJs = @filemtime(__DIR__ . '/assets/js/mega-search-bulk.js') ?: time();
?>
<script src="<?php echo htmlspecialchars(rtrim((string) $url, '/') . '/assets/js/mega-search-bulk.js?v=' . (int) $__msBulkJs, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php include __DIR__ . '/templates/main-footer.php'; ?>
