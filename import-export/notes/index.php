<?php
ob_start();
require_once __DIR__ . '/../../includes/lib-initialize.php';

$title = ($lang['Import notes and docs'] ?? 'Import notes and docs') . ' | ' . $syatem_title;
include __DIR__ . '/../../templates/header.php';

if (!$session->isLoggedIn()) {
    redirectTo($url . 'index.php');
}
$isAdmin = isset($_SESSION['accountStatus']) && (int)$_SESSION['accountStatus'] === 1;
$isStaff = isset($_SESSION['accountStatus']) && (int)$_SESSION['accountStatus'] === 3;
if (!$isAdmin) {
    if ($isStaff) {
        redirectTo($url . 'staff/index.php?message=error&error_msg=' . urlencode($lang['Not allowed'] ?? 'Not allowed'));
    }
    redirectTo($url . 'unauthorized.php');
}

require_once __DIR__ . '/../migration/includes/migration-ui.php';

$id = $session->userId;
$user = User::findById((int)$id);
$username = $user->firstName;
$email = $user->email;
$account_stat = $user->status;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$notesType = isset($_GET['notes_type']) ? strtolower(trim((string)$_GET['notes_type'])) : 'private';
if (!in_array($notesType, ['private', 'profile', 'project', 'system'], true)) {
    $notesType = 'private';
}

$fieldsByType = [
    'private' => [
        'User ID' => $lang['Owner User ID'] ?? 'Owner User ID',
        'Title' => $lang['Title'] ?? 'Title',
        'Content' => $lang['Content'] ?? 'Content (HTML/text)',
        'Color' => $lang['Color'] ?? 'Color (default, blue, green, yellow)',
    ],
    'profile' => [
        'User ID' => $lang['Profile User ID'] ?? 'Profile User ID',
        'Creator ID' => $lang['Creator ID'] ?? 'Creator ID',
        'Creator Type' => $lang['Creator Type'] ?? 'Creator Type (admin, staff)',
        'Content' => $lang['Content'] ?? 'Content (HTML/text)',
        'Color' => $lang['Color'] ?? 'Color (default, blue, green, yellow)',
    ],
    'project' => [
        'Project ID' => $lang['Project ID'] ?? 'Project ID',
        'Creator ID' => $lang['Creator ID'] ?? 'Creator ID',
        'Creator Type' => $lang['Creator Type'] ?? 'Creator Type (admin, staff, client)',
        'Title' => $lang['Title'] ?? 'Title',
        'Content' => $lang['Content'] ?? 'Content (HTML/text)',
        'Color' => $lang['Color'] ?? 'Color (default, blue, green, yellow)',
    ],
    'system' => [
        'Note Type' => $lang['Note Type'] ?? 'Note Type (private, profile, project)',
        'User ID' => $lang['User ID'] ?? 'User ID',
        'Project ID' => $lang['Project ID'] ?? 'Project ID',
        'Creator ID' => $lang['Creator ID'] ?? 'Creator ID',
        'Creator Type' => $lang['Creator Type'] ?? 'Creator Type',
        'Title' => $lang['Title'] ?? 'Title',
        'Content' => $lang['Content'] ?? 'Content (HTML/text)',
        'Color' => $lang['Color'] ?? 'Color',
    ],
];
$previewByType = [
    'private' => [
        ['key' => '__rowNo__', 'label' => $lang['No.'] ?? 'No.'],
        ['key' => 'scope_id', 'label' => $lang['User ID'] ?? 'User ID'],
        ['key' => 'title', 'label' => $lang['Title'] ?? 'Title'],
        ['key' => 'note', 'label' => $lang['Note'] ?? 'Note'],
    ],
    'profile' => [
        ['key' => '__rowNo__', 'label' => $lang['No.'] ?? 'No.'],
        ['key' => 'scope_id', 'label' => $lang['Profile User ID'] ?? 'Profile User ID'],
        ['key' => 'creator', 'label' => $lang['Creator'] ?? 'Creator'],
        ['key' => 'note', 'label' => $lang['Note'] ?? 'Note'],
    ],
    'project' => [
        ['key' => '__rowNo__', 'label' => $lang['No.'] ?? 'No.'],
        ['key' => 'scope_id', 'label' => $lang['Project ID'] ?? 'Project ID'],
        ['key' => 'creator', 'label' => $lang['Creator'] ?? 'Creator'],
        ['key' => 'title', 'label' => $lang['Title'] ?? 'Title'],
        ['key' => 'note', 'label' => $lang['Note'] ?? 'Note'],
    ],
    'system' => [
        ['key' => '__rowNo__', 'label' => $lang['No.'] ?? 'No.'],
        ['key' => 'scope_id', 'label' => $lang['Scope'] ?? 'Scope'],
        ['key' => 'creator', 'label' => $lang['Creator'] ?? 'Creator'],
        ['key' => 'title', 'label' => $lang['Title'] ?? 'Title'],
        ['key' => 'note', 'label' => $lang['Note'] ?? 'Note'],
    ],
];
$tabLabel = [
    'private' => $lang['Private Docs'] ?? 'Private Docs',
    'profile' => $lang['Profile Docs'] ?? 'Profile Docs',
    'project' => $lang['Project Docs'] ?? 'Project Docs',
    'system' => $lang['System Import'] ?? 'System Import',
];
$notesTypeLabel = $tabLabel[$notesType] ?? ($lang['Private Docs'] ?? 'Private Docs');

$migrationTab = 'import';

$migrationToolbarH1 = ($lang['Import'] ?? 'Import') . ' — ' . ($lang['Notes & Docs'] ?? 'Notes & Docs');
$migrationToolbarCtaHref = rtrim($url, '/') . '/import-export/migration/import/';
$migrationToolbarCtaLabel = $lang['Import data'] ?? 'Import data';
$migrationToolbarHidePrimaryCta = true;
$migrationToolbarExtraHtml = '<a class="btn border-btn-a" href="export.php?sample=1&notes_type=' . $h($notesType) . '">'
    . $h($lang['Download sample CSV'] ?? 'Download sample CSV') . '</a>';
?>
<link rel="stylesheet" href="<?php echo $h(MIGRATION_ASSETS_URL); ?>css/lead-import-export.css?v=<?php echo time(); ?>">
<div class="page-container">
    <div class="container-fluid">
        <div class="row row-eq-height">
            <?php include __DIR__ . '/../../templates/sidebar.php'; ?>
            <div class="page-content">
                <?php include __DIR__ . '/../../templates/top-header.php'; ?>
                <?php include __DIR__ . '/../assets/templates/migration-toolbar.php'; ?>
                <div class="row">
                    <div class="col-12 pd-0">
                        <div class="import-wizard w-100">
                            <div class="step-list border-bottom">
                                <div class="step-list__step step-list__step--highlight" data-step="1"><dl class="step-list__step-content"><dt class="step-list__step-order">1</dt><dd class="step-list__step-description"><?php echo $h($lang['Upload'] ?? 'Upload'); ?></dd></dl></div>
                                <div class="step-list__step" data-step="2"><dl class="step-list__step-content"><dt class="step-list__step-order">2</dt><dd class="step-list__step-description"><?php echo $h($lang['Match fields'] ?? 'Match fields'); ?></dd></dl></div>
                                <div class="step-list__step" data-step="3"><dl class="step-list__step-content"><dt class="step-list__step-order">3</dt><dd class="step-list__step-description"><?php echo $h($lang['Options'] ?? 'Options'); ?></dd></dl></div>
                                <div class="step-list__step" data-step="4"><dl class="step-list__step-content"><dt class="step-list__step-order">4</dt><dd class="step-list__step-description"><?php echo $h($lang['Preview & import'] ?? 'Preview & import'); ?></dd></dl></div>
                            </div>
                            <div class="wizard-content">
                                <div class="step-content active" data-step="1">
                                    <div class="settings-card">
                                        <div class="card-body">
                                            <div class="btn-group mb-3 col-gap" role="group" aria-label="Notes import type">
                                                <a class="<?php echo $notesType === 'private' ? 'primary-btn' : 'border-btn-a'; ?>" href="?notes_type=private"><?php echo $h($tabLabel['private']); ?></a>
                                                <a class="<?php echo $notesType === 'profile' ? 'primary-btn' : 'border-btn-a'; ?>" href="?notes_type=profile"><?php echo $h($tabLabel['profile']); ?></a>
                                                <a class="<?php echo $notesType === 'project' ? 'primary-btn' : 'border-btn-a'; ?>" href="?notes_type=project"><?php echo $h($tabLabel['project']); ?></a>
                                                <a class="<?php echo $notesType === 'system' ? 'primary-btn' : 'border-btn-a'; ?>" href="?notes_type=system"><?php echo $h($tabLabel['system']); ?></a>
                                            </div>
                                            <h3 class="mb-2"><?php echo htmlspecialchars($lang['Step 1: Upload File'] ?? 'Step 1: Upload File'); ?></h3>
                                            <p class="text-muted mb-4"><?php echo htmlspecialchars($lang['You will get an opportunity to verify the import before committing'] ?? 'You will get an opportunity to verify the import before committing'); ?></p>
                                            <p class="text-muted import-wizard__importing-as"><?php echo htmlspecialchars($lang['Importing as'] ?? 'Importing as'); ?>: <strong><?php echo htmlspecialchars($notesTypeLabel); ?></strong></p>
                                            <div id="uploadDropzone" class="upload-dropzone">
                                                <div class="upload-icon">
                                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                                        <polyline points="17 8 12 3 7 8"></polyline>
                                                        <line x1="12" y1="3" x2="12" y2="15"></line>
                                                    </svg>
                                                </div>
                                                <p class="upload-text mt-4"><?php echo htmlspecialchars($lang['Click to upload or drag and drop'] ?? 'Click to upload or drag and drop'); ?></p>
                                                <p class="upload-hint"><?php echo htmlspecialchars($lang['CSV, XLSX (max. 100MB)'] ?? 'CSV, XLSX (max. 100MB)'); ?></p>
                                                <input type="file" id="csvFileInput" accept=".csv,.xlsx" style="display:none;">
                                            </div>
                                            <div id="fileInfo" class="file-info mt-3" style="display:none;">
                                                <div class="d-flex align-items-center col-gap-10">
                                                    <span class="file-name"></span>
                                                    <button type="button" class="btn btn-sm border-btn-a ms-2" id="removeFileBtn"><?php echo $h($lang['Remove'] ?? 'Remove'); ?></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="step-content" data-step="2">
                                    <div class="settings-card"><div class="card-body">
                                        <div id="fieldMappingAlert" class="alert" style="display:none"></div>
                                        <table class="table table-fancy field-mapping-table">
                                            <thead><tr><th class="field-mapping-col--no"><?php echo $h($lang['No.'] ?? 'No.'); ?></th><th><?php echo $h($lang['CSV'] ?? 'CSV'); ?></th><th><?php echo $h($lang['Sample'] ?? 'Sample'); ?></th><th><?php echo $h($lang['CRM field'] ?? 'CRM field'); ?></th></tr></thead>
                                            <tbody id="fieldMappingBody"></tbody>
                                        </table>
                                    </div></div>
                                </div>
                                <div class="step-content" data-step="3">
                                    <div class="settings-card"><div class="card-body">
                                        <form id="importOptionsForm">
                                            <input type="hidden" name="notes_type" value="<?php echo $h($notesType); ?>">
                                            <div class="form-group">
                                                <label><?php echo $h($lang['Duplicate behavior'] ?? 'Duplicate behavior'); ?></label>
                                                <select name="duplicate_strategy" class="form-control">
                                                    <option value="skip"><?php echo $h($lang['Skip row'] ?? 'Skip row'); ?></option>
                                                    <option value="create_anyway"><?php echo $h($lang['Import anyway'] ?? 'Import anyway'); ?></option>
                                                </select>
                                            </div>
                                            <div class="permission-item d-flex col-gap align-items-center mb-3">
                                                <div class="checkbox-wrapper-6">
                                                    <input class="tgl tgl-light" id="notes_import_dry_run" name="dry_run" type="checkbox" value="1">
                                                    <label class="tgl-btn" for="notes_import_dry_run"></label>
                                                </div>
                                                <div class="flex-grow-1"><label for="notes_import_dry_run" class="permission-label fw-bold"><?php echo $h($lang['Dry run (no database writes)'] ?? 'Dry run (no database writes)'); ?></label></div>
                                            </div>
                                        </form>
                                    </div></div>
                                </div>
                                <div class="step-content" data-step="4">
                                    <div class="settings-card"><div class="card-body">
                                        <div id="previewAlert" class="alert" style="display:none"></div>
                                        <p id="previewTotalRows" class="text-muted"></p>
                                        <div id="previewContainer" class="table-responsive">
                                            <table class="table table-fancy preview-table import-preview-table w-100">
                                                <thead id="previewTableHead"></thead>
                                                <tbody id="previewTableBody"></tbody>
                                            </table>
                                        </div>
                                        <div id="importResults" style="display:none" class="mt-3"><p id="importResultsText"></p></div>
                                    </div></div>
                                </div>
                            </div>
                            <div class="wizard-navigation d-flex col-gap justify-content-between p-3">
                                <button type="button" class="btn border-btn-a" id="wizardCancelBtn"><?php echo $h($lang['Cancel'] ?? 'Cancel'); ?></button>
                                <div class="d-flex col-gap">
                                    <button type="button" class="btn border-btn-a" id="wizardBackBtn" style="display:none"><?php echo $h($lang['Back'] ?? 'Back'); ?></button>
                                    <button type="button" class="btn primary-btn" id="wizardNextBtn"><?php echo $h($lang['Next'] ?? 'Next'); ?></button>
                                    <button type="button" class="btn primary-btn" id="wizardImportBtn" style="display:none"><?php echo $h($lang['Import'] ?? 'Import'); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
<script>
window.csrfToken = <?php echo json_encode($csrfToken); ?>;
window.userImportConfig = {
  importUrl: 'import.php',
  jobStatusUrl: <?php echo json_encode(rtrim($url, '/') . '/ajax/import-export/job_status.php', JSON_UNESCAPED_UNICODE); ?>,
  userFields: <?php echo json_encode($fieldsByType[$notesType], JSON_UNESCAPED_UNICODE); ?>,
  skipTargetUserType: true,
  previewColumns: <?php echo json_encode($previewByType[$notesType], JSON_UNESCAPED_UNICODE); ?>,
  mapDuplicateWarn: <?php echo json_encode($lang['Field already mapped with {column}'] ?? 'Field already mapped with {column}', JSON_UNESCAPED_UNICODE); ?>,
  mapDuplicateBlock: <?php echo json_encode($lang['Resolve duplicate field mappings before continuing.'] ?? 'Resolve duplicate field mappings before continuing.', JSON_UNESCAPED_UNICODE); ?>
};
</script>
<script src="<?php echo $url; ?>assets/js/user-import-wizard.js?v=<?php echo time(); ?>"></script>
<?php include __DIR__ . '/../../templates/main-footer.php'; ?>
