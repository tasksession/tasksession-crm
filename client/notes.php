<?php
ob_start();
require_once("../includes/lib-initialize.php");
$title = $lang['Project Notes'] . " | " . $syatem_title;
include("../templates/header.php");

if (!($session->isLoggedIn()) || (int) $_SESSION['accountStatus'] !== 2) {
	redirectTo($url . "");
}

$id = (int) $session->userId;
$user = User::findById((int)$id);
$username=$user->firstName;;
$projectId = isset($_GET['projectId']) ? (int) $_GET['projectId'] : 0;
if (!$projectId) {
	redirectTo($url . "client/projects");
}
$project = projects::findByProjectId($projectId);
if (!$project) {
	redirectTo($url . "client/projects");
}
$isClient = false;
if ((int) $project->c_id === $id || (int) $project->main_client_id === $id) {
	$isClient = true;
} elseif (!empty($project->c_ids)) {
	$allClientIds = array_filter(explode(',', (string) $project->c_ids));
	$isClient = in_array((string) $id, $allClientIds, true) || in_array($id, $allClientIds, true);
}
if (!$isClient) {
	redirectTo($url . "client/projects");
}

include(__DIR__ . '/../includes/project-sidebar-data.php');
$profileNotesCsrfToken = function_exists('generate_csrf_token') ? generate_csrf_token() : '';
$projectNotesCreatorType = 'client';
require_once __DIR__ . '/../includes/project_notes_tab.php';
?>
<div class="page-container vh-100">
	<div class="container-fluid vh-100">
		<div class="row row-eq-height vh-100">
			<?php include("../templates/sidebar.php"); ?>
			<div class="page-content">
				<?php include('../templates/top-header.php'); ?>
				<link rel="stylesheet" href="../assets/css/kanban-bulk.css?v=9">
				<?php include __DIR__ . '/../templates/docs/project-notes-filters-data.php'; ?>
				<div class="row bg-grey">
					<div class="col-md-12 margin-top-10 clients project-tabs">
						<div class="row">
							<?php $project_id = $projectId; include('../templates/project-tabs.php'); ?>
							<div class="search">
								<span id="noteAutosaveStatus" class="me-2 d-none" aria-live="polite">Saving...</span>
								<div class="search-icon border-btn-a" onclick="toggleSearch()">
									<?php echo ts_icon('search', 'w-2'); ?>
								</div>
								<form method="GET" action="" class="search-form" id="searchForm" style="<?php echo !empty($searchQuery) ? 'display:block;' : 'display:none;'; ?>">
									<input type="hidden" name="projectId" value="<?php echo (int) $projectId; ?>">
									<div class="input-group">
										<span class="search-field-icon">
											<?php echo ts_icon('search', 'w-2'); ?>
										</span>
										<input type="text" id="file-search" name="search" class="form-control" placeholder="Search notes..." value="<?php echo htmlspecialchars((string) ($searchQuery ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
										<?php if (!empty($searchQuery)) : ?>
											<a href="notes?projectId=<?php echo (int) $projectId; ?>" class="cross" title="<?php echo htmlspecialchars($lang['Close'] ?? 'Close', ENT_QUOTES, 'UTF-8'); ?>">
												<?php echo ts_icon('close', 'w-2'); ?>
											</a>
										<?php else : ?>
											<a href="#" class="cross" onclick="toggleSearch(); return false;" title="<?php echo htmlspecialchars($lang['Close'] ?? 'Close', ENT_QUOTES, 'UTF-8'); ?>">
												<?php echo ts_icon('close', 'w-2'); ?>
											</a>
										<?php endif; ?>
									</div>
								</form>
							</div>
							<div class="edit-overview-btn kanban-header-filters">
								<div class="action-toggle border-btn-a collapsed" data-bs-toggle="collapse" data-bs-target="#projectNotesFilterDropdown" aria-expanded="false" role="button" tabindex="0">
									<span class="action-text"><?php echo htmlspecialchars($lang['Filters'] ?? 'Filters', ENT_QUOTES, 'UTF-8'); ?></span>
									<span class="mobile-ellipsis">
										<?php echo ts_icon('ellipsis', 'w-2'); ?>
									</span>
									<?php echo ts_icon('filter', 'w-2'); ?>
								</div>
								<?php $projectNotesFilterPanelId = 'projectNotesFilterDropdown'; include __DIR__ . '/../templates/docs/project-notes-filters-dropdown.php'; ?>
							</div>
							<div class="edit-overview-btn d-none d-md-block">
								<a href="notes?projectId=<?php echo (int) $projectId; ?>&new=1" class="primary-btn"><?php echo htmlspecialchars($lang['Create New'] ?? 'Create New', ENT_QUOTES, 'UTF-8'); ?></a>
							</div>
						</div>
					</div>
				</div>
				<div class="clearfix"></div>
				<div class="row vh-100">
					<div class="container-fluid vh-100">
						<div class="row vh-100">
							<?php include("../templates/project-sidebar.php"); ?>
							<div class="col-xl-9 col-lg-8 col-md-12 flex-grow-1 fill-rest right-col pd-0 body-bg">
								<div class="profile-notes-content-wrap"><?php include __DIR__ . '/../templates/docs/project-notes-panel.php'; ?></div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<?php $richEditorV = @filemtime(__DIR__ . '/../assets/js/rich-editor.js') ?: time(); ?>
<script src="../assets/js/rich-editor.js?v=<?php echo (int) $richEditorV; ?>"></script>
<?php
if (empty($url) && class_exists('settings')) {
	$__ds = settings::findById(1);
	$url = $__ds && !empty($__ds->url) ? (string) $__ds->url : '';
}
$__urlBase = isset($url) && $url !== '' ? rtrim((string) $url, '/') . '/' : '';
$pnNoteId = 0;
if (isset($edit_note) && is_array($edit_note) && !empty($edit_note['id'])) {
	$pnNoteId = (int) $edit_note['id'];
} elseif (isset($_GET['note_id']) && (int) $_GET['note_id'] > 0) {
	$pnNoteId = (int) $_GET['note_id'];
}
$subjectUserId = (int) ($project->main_client_id ?? 0);
if ($subjectUserId <= 0) {
	$subjectUserId = (int) ($project->c_id ?? 0);
}
?>
<script>window.baseUrl = <?php echo json_encode($__urlBase, JSON_UNESCAPED_SLASHES); ?>; window.csrfToken = <?php echo json_encode($profileNotesCsrfToken, JSON_UNESCAPED_UNICODE); ?>;</script>
<?php
// media-replace: single load from templates/main-footer.php
?>
<script>
window.PROJECT_AUTOSAVE = {
	enabled: true,
	projectId: <?php echo (int) $projectId; ?>,
	noteId: <?php echo (int) $pnNoteId; ?>,
	csrfToken: <?php echo json_encode($profileNotesCsrfToken, JSON_UNESCAPED_UNICODE); ?>,
	ajaxBase: <?php echo json_encode('../ajax/project_notes/'); ?>
};
window.PROJECT_NOTE_SHARE = {
	projectId: <?php echo (int) $projectId; ?>,
	projectNoteId: 0,
	subjectUserId: <?php echo (int) $subjectUserId; ?>,
	ajaxBase: <?php echo json_encode('../ajax/project_notes/'); ?>,
	csrfToken: <?php echo json_encode($profileNotesCsrfToken, JSON_UNESCAPED_UNICODE); ?>
};
</script>
<?php $pnaV = @filemtime(__DIR__ . '/../assets/js/project-autosave.js') ?: time(); ?>
<script src="../assets/js/project-autosave.js?v=<?php echo (int) $pnaV; ?>"></script>
<?php require_once __DIR__ . '/../includes/private_notes/share_note_modal.php'; ?>
<?php $pnShareV = @filemtime(__DIR__ . '/../assets/js/project-notes-share.js') ?: time(); ?>
<script src="../assets/js/project-notes-share.js?v=<?php echo (int) $pnShareV; ?>"></script>
<script>
function toggleSearch() {
	var searchForm = document.getElementById('searchForm');
	var searchInput = document.getElementById('file-search');
	if (!searchForm) return;
	if (searchForm.style.display === 'none' || searchForm.style.display === '') {
		searchForm.style.display = 'block';
		if (searchInput) searchInput.focus();
	} else {
		searchForm.style.display = 'none';
	}
}
</script>
<?php $__aiCtx = __DIR__ . '/../includes/ai_contextual_snippet.php';
if (is_file($__aiCtx)) { require_once $__aiCtx; if (function_exists('ai_contextual_emit')) { ai_contextual_emit(); } } ?>
<?php include("../templates/main-footer.php"); ?>
