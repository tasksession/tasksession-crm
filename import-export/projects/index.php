<?php
ob_start();
require_once __DIR__ . '/../../includes/lib-initialize.php';

$title = ($lang['Import projects'] ?? 'Import projects') . ' | ' . $syatem_title;
include __DIR__ . '/../../templates/header.php';

if (!$session->isLoggedIn()) {
    redirectTo($url . 'index.php');
}
$isAdmin = isset($_SESSION['accountStatus']) && (int)$_SESSION['accountStatus'] === 1;
$isStaff = isset($_SESSION['accountStatus']) && (int)$_SESSION['accountStatus'] === 3;
if (!$isAdmin && !$isStaff) {
    redirectTo($url . 'unauthorized.php');
}
if (!$isAdmin) {
    redirectTo($url . 'staff/index.php?message=error&error_msg=' . urlencode($lang['Not allowed'] ?? 'Not allowed'));
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

$projectFields = [
    'Project Title' => $lang['Project Title'] ?? 'Project Title',
    'Project Description' => $lang['Description'] ?? 'Project Description',
    'Budget' => $lang['Budget'] ?? 'Budget',
    'Status' => $lang['Status'] ?? 'Status',
    'Archive' => $lang['Archive'] ?? 'Archive',
    'Trash' => $lang['Trash'] ?? 'Trash',
    'Start Date' => $lang['Start Date'] ?? 'Start date',
    'End Date' => $lang['End Date'] ?? 'End Date',
    'Company ID' => $lang['Company ID'] ?? 'Company ID',
    'Main Client ID' => $lang['Main Client ID'] ?? 'Main Client ID',
    'Additional Client IDs' => $lang['Additional Client IDs'] ?? 'Additional Client IDs',
    'Assigned Staff' => $lang['Assigned Staff'] ?? 'Assigned Staff',
];
$customFields = [];
$cfRes = mysqli_query(
    $connect,
    "SELECT * FROM custom_fields WHERE entity_type = 'project' AND COALESCE(is_disabled, 0) = 0 ORDER BY sort_order ASC, id ASC"
);
if ($cfRes) {
    while ($cfRow = mysqli_fetch_assoc($cfRes)) {
        $projectFields['CustomField_' . $cfRow['id']] = $cfRow['label'];
        $customFields[] = ['id' => (int)$cfRow['id'], 'label' => $cfRow['label'], 'field_type' => $cfRow['field_type']];
    }
}

$migrationTab = 'import';

$migrationToolbarH1 = ($lang['Import'] ?? 'Import') . ' — ' . ($lang['Projects'] ?? 'Projects');
$migrationToolbarCtaHref = rtrim($url, '/') . '/import-export/migration/import/';
$migrationToolbarCtaLabel = $lang['Import data'] ?? 'Import data';
$migrationToolbarHidePrimaryCta = true;
$migrationToolbarExtraHtml = '<a class="btn border-btn-a" href="export.php?sample=1">'
    . $h($lang['Download Sample'] ?? 'Download Sample') . '</a>';

$previewColumns = [
    ['key' => '__rowNo__', 'label' => $lang['No.'] ?? 'No.'],
    ['key' => 'project_title', 'label' => $lang['Project Title'] ?? 'Project Title'],
    ['key' => 'note', 'label' => $lang['Note'] ?? 'Note'],
];
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
                                <div class="step-list__step step-list__step--highlight" data-step="1">
                                    <dl class="step-list__step-content">
                                        <dt class="step-list__step-order">1</dt>
                                        <dd class="step-list__step-description"><span class="step-heading">1</span> <?php echo htmlspecialchars($lang['Upload'] ?? 'Upload'); ?></dd>
                                    </dl>
                                </div>
                                <div class="step-list__step" data-step="2">
                                    <dl class="step-list__step-content">
                                        <dt class="step-list__step-order">2</dt>
                                        <dd class="step-list__step-description"><span class="step-heading">2</span> <?php echo htmlspecialchars($lang['Match fields'] ?? 'Match fields'); ?></dd>
                                    </dl>
                                </div>
                                <div class="step-list__step" data-step="3">
                                    <dl class="step-list__step-content">
                                        <dt class="step-list__step-order">3</dt>
                                        <dd class="step-list__step-description"><span class="step-heading">3</span> <?php echo htmlspecialchars($lang['Options'] ?? 'Options'); ?></dd>
                                    </dl>
                                </div>
                                <div class="step-list__step" data-step="4">
                                    <dl class="step-list__step-content">
                                        <dt class="step-list__step-order">4</dt>
                                        <dd class="step-list__step-description"><span class="step-heading">4</span> <?php echo htmlspecialchars($lang['Preview & import'] ?? 'Preview & import'); ?></dd>
                                    </dl>
                                </div>
                            </div>

                            <div class="wizard-content">
                                <div class="step-content active" data-step="1">
                                    <div class="settings-card">
                                        <div class="card-body">
                                            <h3 class="mb-2"><?php echo htmlspecialchars($lang['Step 1: Upload File'] ?? 'Step 1: Upload File'); ?></h3>
                                            <p class="text-muted mb-4"><?php echo htmlspecialchars($lang['You will get an opportunity to verify the import before committing'] ?? 'You will get an opportunity to verify the import before committing'); ?></p>
                                            <div class="upload-dropzone" id="uploadDropzone">
                                                <div class="upload-icon">
                                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                                        <polyline points="17 8 12 3 7 8"></polyline>
                                                        <line x1="12" y1="3" x2="12" y2="15"></line>
                                                    </svg>
                                                </div>
                                                <p class="upload-text mt-4"><?php echo htmlspecialchars($lang['Click to upload or drag and drop'] ?? 'Click to upload or drag and drop'); ?></p>
                                                <p class="upload-hint"><?php echo htmlspecialchars($lang['CSV, XLSX (max. 100MB)'] ?? 'CSV, XLSX (max. 100MB)'); ?></p>
                                                <input type="file" id="csvFileInput" name="csv_file" accept=".csv,.xlsx" style="display: none;">
                                            </div>
                                            <div id="fileInfo" class="file-info mt-3" style="display: none;">
                                                <div class="d-flex align-items-center col-gap-10">
                                                    <span class="file-name"></span>
                                                    <button type="button" class="btn btn-sm border-btn-a" id="removeFileBtn"><?php echo htmlspecialchars($lang['Remove'] ?? 'Remove'); ?></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="step-content" data-step="2">
                                    <div class="settings-card">
                                        <div class="card-body">
                                            <div id="fieldMappingAlert" class="alert" style="display:none"></div>
                                            <table class="table table-fancy field-mapping-table">
                                                <thead><tr><th class="field-mapping-col--no"><?php echo htmlspecialchars($lang['No.'] ?? 'No.'); ?></th><th><?php echo htmlspecialchars($lang['CSV'] ?? 'CSV'); ?></th><th><?php echo htmlspecialchars($lang['Sample'] ?? 'Sample'); ?></th><th><?php echo htmlspecialchars($lang['CRM field'] ?? 'CRM field'); ?></th></tr></thead>
                                                <tbody id="fieldMappingBody"></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <div class="step-content" data-step="3">
                                    <div class="settings-card">
                                        <div class="card-body">
                                            <form id="importOptionsForm">
                                                <div class="form-group">
                                                    <label><?php echo htmlspecialchars($lang['When project title already exists'] ?? 'When project title already exists'); ?></label>
                                                    <select name="duplicate_strategy" class="form-control">
                                                        <option value="skip"><?php echo htmlspecialchars($lang['Skip row'] ?? 'Skip row'); ?></option>
                                                        <option value="create_anyway"><?php echo htmlspecialchars($lang['Import anyway'] ?? 'Import anyway'); ?></option>
                                                    </select>
                                                </div>
                                                <div class="permission-item d-flex col-gap align-items-center mb-3">
                                                    <div class="checkbox-wrapper-6">
                                                        <input class="tgl tgl-light" id="project_import_dry_run" name="dry_run" type="checkbox" value="1">
                                                        <label class="tgl-btn" for="project_import_dry_run"></label>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <label for="project_import_dry_run" class="permission-label fw-bold"><?php echo htmlspecialchars($lang['Dry run (no database writes)'] ?? 'Dry run'); ?></label>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <div class="step-content" data-step="4">
                                    <div class="settings-card">
                                        <div class="card-body">
                                            <div id="previewAlert" class="alert" style="display:none"></div>
                                            <p id="previewTotalRows" class="text-muted"></p>
                                            <div id="previewContainer" class="table-responsive">
                                                <table class="table table-fancy preview-table import-preview-table w-100">
                                                    <thead id="previewTableHead"></thead>
                                                    <tbody id="previewTableBody"></tbody>
                                                </table>
                                            </div>
                                            <div id="importResults" style="display:none" class="mt-3">
                                                <p id="importResultsText"></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="wizard-navigation d-flex col-gap justify-content-between p-3">
                                <button type="button" class="btn border-btn-a" id="wizardCancelBtn"><?php echo htmlspecialchars($lang['Cancel'] ?? 'Cancel'); ?></button>
                                <div class="d-flex col-gap">
                                    <button type="button" class="btn border-btn-a" id="wizardBackBtn" style="display:none"><?php echo htmlspecialchars($lang['Back'] ?? 'Back'); ?></button>
                                    <button type="button" class="btn primary-btn" id="wizardNextBtn"><?php echo htmlspecialchars($lang['Next'] ?? 'Next'); ?></button>
                                    <button type="button" class="btn primary-btn" id="wizardImportBtn" style="display:none"><?php echo htmlspecialchars($lang['Import'] ?? 'Import'); ?></button>
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
  skipTargetUserType: true,
  userFields: <?php echo json_encode($projectFields, JSON_UNESCAPED_UNICODE); ?>,
  customFields: <?php echo json_encode($customFields, JSON_UNESCAPED_UNICODE); ?>,
  previewColumns: <?php echo json_encode($previewColumns, JSON_UNESCAPED_UNICODE); ?>,
  mapDuplicateWarn: <?php echo json_encode($lang['Field already mapped with {column}'] ?? 'Field already mapped with {column}', JSON_UNESCAPED_UNICODE); ?>,
  mapDuplicateBlock: <?php echo json_encode($lang['Resolve duplicate field mappings before continuing.'] ?? 'Resolve duplicate field mappings before continuing.', JSON_UNESCAPED_UNICODE); ?>
};
</script>
<script src="<?php echo $url; ?>assets/js/user-import-wizard.js?v=<?php echo time(); ?>"></script>
<?php include __DIR__ . '/../../templates/main-footer.php'; ?>
