<?php
ob_start();
require_once __DIR__ . '/../../includes/lib-initialize.php';

$title = ($lang['Import users'] ?? 'Import users') . ' | ' . $syatem_title;
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

require_once __DIR__ . '/../../includes/permissions.php';
ensure_user_permissions($connect);

require_once __DIR__ . '/../migration/includes/migration-ui.php';

$target = isset($_GET['type']) ? strtolower(trim((string)$_GET['type'])) : 'client';
if (!in_array($target, ['client', 'staff', 'admin'], true)) {
    $target = 'client';
}
if ($target === 'admin' && !$isAdmin) {
    redirectTo($url . 'staff/index.php?message=error&error_msg=' . urlencode($lang['Not allowed'] ?? 'Not allowed'));
}
// Non-lead migration modules are admin-only.

$id = $session->userId;
$user = User::findById((int)$id);
$username = $user->firstName;
$email = $user->email;
$account_stat = $user->status;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$roles = [];
$resRoles = mysqli_query($connect, 'SELECT id, name FROM roles ORDER BY name ASC');
if ($resRoles) {
    while ($r = mysqli_fetch_assoc($resRoles)) {
        $roles[] = $r;
    }
}

$userFields = [
    'Email' => $lang['Email'] ?? 'Email',
    'First Name' => $lang['First Name'] ?? 'First Name',
    'Name' => $lang['Name'] ?? 'Name',
    'Title' => $lang['Title'] ?? 'Title',
    'Phone' => $lang['Phone'] ?? 'Phone',
    'Website' => $lang['Website'] ?? 'Website',
    'Company' => $lang['Company'] ?? 'Company',
    'Address' => $lang['Address'] ?? 'Address',
    'City' => $lang['City'] ?? 'City',
    'State' => $lang['State'] ?? 'State',
    'Zip' => $lang['Zip'] ?? 'Zip',
    'Country' => $lang['Country'] ?? 'Country',
    'Currency' => $lang['Currency'] ?? 'Currency',
    'Assigned Team' => $lang['Assigned Team'] ?? 'Assigned team',
    'Password' => $lang['Password'] ?? 'Password',
    'Role ID' => $lang['Role ID'] ?? 'Role ID',
];
$customFields = [];
$cfRes = mysqli_query(
    $connect,
    "SELECT * FROM custom_fields WHERE entity_type = '" . mysqli_real_escape_string($connect, $target) . "' AND COALESCE(is_disabled, 0) = 0 ORDER BY sort_order ASC, id ASC"
);
if ($cfRes) {
    while ($cfRow = mysqli_fetch_assoc($cfRes)) {
        $userFields['CustomField_' . $cfRow['id']] = $cfRow['label'];
        $customFields[] = ['id' => (int)$cfRow['id'], 'label' => $cfRow['label'], 'field_type' => $cfRow['field_type']];
    }
}

$clientCompaniesAvailable = false;
if ($target === 'client') {
    $ccTbl = mysqli_query($connect, "SHOW TABLES LIKE 'client_companies'");
    if ($ccTbl && mysqli_num_rows($ccTbl) > 0) {
        $clientCompaniesAvailable = true;
    }
    if ($ccTbl) {
        mysqli_free_result($ccTbl);
    }
}
if ($clientCompaniesAvailable) {
    $userFields = array_merge($userFields, [
        'Org Company Name' => $lang['Org Company Name'] ?? 'Organization: company name',
        'Org Currency' => $lang['Org Currency'] ?? 'Organization: currency',
        'Org VAT Number' => $lang['Org VAT Number'] ?? 'Organization: VAT number',
        'Org Phone' => $lang['Org Phone'] ?? 'Organization: phone',
        'Org Email' => $lang['Org Email'] ?? 'Organization: email',
        'Org Website' => $lang['Org Website'] ?? 'Organization: website',
        'Org Address' => $lang['Org Address'] ?? 'Organization: address',
        'Org City' => $lang['Org City'] ?? 'Organization: city',
        'Org State' => $lang['Org State'] ?? 'Organization: state',
        'Org Zip' => $lang['Org Zip'] ?? 'Organization: zip',
        'Org Country' => $lang['Org Country'] ?? 'Organization: country',
        'Org Billing address' => $lang['Org Billing address'] ?? 'Organization: billing address',
        'Org Billing Street' => $lang['Org Billing Street'] ?? 'Organization: billing street',
        'Org Billing City' => $lang['Org Billing City'] ?? 'Organization: billing city',
        'Org Billing State' => $lang['Org Billing State'] ?? 'Organization: billing state',
        'Org Billing Zip' => $lang['Org Billing Zip'] ?? 'Organization: billing zip',
        'Org Billing Country' => $lang['Org Billing Country'] ?? 'Organization: billing country',
    ]);
    $ccvTbl = @mysqli_query($connect, "SHOW TABLES LIKE 'company_custom_field_values'");
    $ccvOk = $ccvTbl && mysqli_num_rows($ccvTbl) > 0;
    if ($ccvTbl) {
        mysqli_free_result($ccvTbl);
    }
    if ($ccvOk) {
        $orgCfRes = mysqli_query(
            $connect,
            "SELECT id, label FROM custom_fields WHERE entity_type = 'company' AND COALESCE(is_disabled, 0) = 0 ORDER BY sort_order ASC, id ASC"
        );
        if ($orgCfRes) {
            $orgCfPrefix = $lang['Org custom field label prefix'] ?? 'Organization: ';
            while ($ocr = mysqli_fetch_assoc($orgCfRes)) {
                $oid = (int)($ocr['id'] ?? 0);
                if ($oid <= 0) {
                    continue;
                }
                $olab = trim((string)($ocr['label'] ?? ''));
                if ($olab === '') {
                    $olab = 'Custom field ' . $oid;
                }
                $userFields['OrgCustomField_' . $oid] = $orgCfPrefix . $olab;
            }
            mysqli_free_result($orgCfRes);
        }
    }
}

$orgCompanyCfAvailable = false;
if ($clientCompaniesAvailable) {
    foreach ($userFields as $k => $_v) {
        if (strpos((string)$k, 'OrgCustomField_') === 0) {
            $orgCompanyCfAvailable = true;
            break;
        }
    }
}

$targetLabel = $target === 'client' ? ($lang['Clients'] ?? 'Clients') : ($target === 'staff' ? ($lang['Staff'] ?? 'Staff') : ($lang['Admins'] ?? 'Admins'));

$migrationTab = 'import';

$migrationToolbarH1 = ($lang['Import'] ?? 'Import') . ' — ' . $targetLabel;
$migrationToolbarCtaHref = rtrim($url, '/') . '/import-export/migration/import/';
$migrationToolbarCtaLabel = $lang['Import data'] ?? 'Import data';
$migrationToolbarHidePrimaryCta = true;
$migrationToolbarExtraHtml = '<a class="btn border-btn-a" href="export.php?type=' . $h($target) . '&sample=1">'
    . $h($lang['Download Sample'] ?? 'Download Sample') . '</a>';
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
                        <input type="hidden" id="targetUserType" value="<?php echo htmlspecialchars($target); ?>">

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
                                            <?php if ($target === 'client' && $clientCompaniesAvailable): ?>
                                            <p class="text-muted mb-3"><?php echo htmlspecialchars($lang['Client import org fields help'] ?? 'Optional columns prefixed with Org (for example Org Company Name) create or reuse one client company per row and link the imported client as a member. They do not replace person-level columns such as Address or Currency. If two rows share the same organization name and VAT, they attach to one company.'); ?></p>
                                            <?php endif; ?>
                                            <?php if ($target === 'client' && $orgCompanyCfAvailable): ?>
                                            <p class="text-muted mb-3"><?php echo htmlspecialchars($lang['Client import org custom fields help'] ?? 'Map organization-level custom fields using CRM targets OrgCustomField_{id} (shown with your field labels). Values are saved on the linked client company after import.'); ?></p>
                                            <?php endif; ?>
                                            <p class="text-muted import-wizard__importing-as"><?php echo htmlspecialchars($lang['Importing as'] ?? 'Importing as'); ?>: <strong><?php echo htmlspecialchars($targetLabel); ?></strong></p>
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
                                                <?php if ($target === 'staff'): ?>
                                                <div class="form-group">
                                                    <label><?php echo htmlspecialchars($lang['Default role'] ?? 'Default role (if Role ID column empty)'); ?></label>
                                                    <select name="fallback_role_id" id="fallback_role_id" class="form-control">
                                                        <option value="0">—</option>
                                                        <?php foreach ($roles as $ro): ?>
                                                            <option value="<?php echo (int)$ro['id']; ?>"><?php echo htmlspecialchars($ro['name']); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <?php endif; ?>
                                                <div class="form-group">
                                                    <label><?php echo htmlspecialchars($lang['When email already exists'] ?? 'When email already exists'); ?></label>
                                                    <select name="duplicate_strategy" id="duplicate_strategy_select" class="form-control">
                                                        <option value="skip"><?php echo htmlspecialchars($lang['Skip row'] ?? 'Skip row'); ?></option>
                                                        <option value="new_clients_only"><?php echo htmlspecialchars($lang['Import for new client'] ?? 'Import for new client only'); ?></option>
                                                        <option value="overwrite"><?php echo htmlspecialchars($lang['Update existing same type'] ?? 'Update existing same type'); ?></option>
                                                    </select>
                                                    <?php if ($target === 'client' && $clientCompaniesAvailable): ?>
                                                    <div id="clientImportOverwriteHint" class="mt-2" style="display:none;">
                                                        <p class="text-muted small mb-0"><?php echo htmlspecialchars($lang['Client import overwrite for org reimport hint'] ?? 'To update clients you already imported when you add new columns (such as organization fields), choose Update existing same type. Skip row or Import for new client only leaves existing profiles unchanged.'); ?></p>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="permission-item d-flex col-gap align-items-center mb-3">
                                                    <div class="checkbox-wrapper-6">
                                                        <input class="tgl tgl-light" id="user_import_dry_run" name="dry_run" type="checkbox" value="1">
                                                        <label class="tgl-btn" for="user_import_dry_run"></label>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <label for="user_import_dry_run" class="permission-label fw-bold"><?php echo htmlspecialchars($lang['Dry run (no database writes)'] ?? 'Dry run'); ?></label>
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
                                            <div id="previewStage" class="import-preview-stage">
                                                <div id="previewSkeletonBlock" class="import-preview-skeleton-block" hidden aria-hidden="true"></div>
                                                <div id="previewContainer" class="table-responsive">
                                                    <table class="table table-fancy preview-table import-preview-table w-100">
                                                        <thead id="previewTableHead"></thead>
                                                        <tbody id="previewTableBody"></tbody>
                                                    </table>
                                                </div>
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
  jobStatusUrl: '<?php echo rtrim($url, '/'); ?>/ajax/import-export/job_status.php',
  targetUserType: <?php echo json_encode($target); ?>,
  userFields: <?php echo json_encode($userFields, JSON_UNESCAPED_UNICODE); ?>,
  previewColumns: <?php echo json_encode([
    ['key' => '__rowNo__', 'label' => $lang['No.'] ?? 'No.'],
    ['key' => 'email', 'label' => $lang['Email'] ?? 'Email'],
    ['key' => 'firstName', 'label' => $lang['First Name'] ?? 'First Name'],
    ['key' => 'phone', 'label' => $lang['Phone'] ?? 'Phone'],
    ['key' => 'note', 'label' => $lang['Note'] ?? 'Note'],
  ], JSON_UNESCAPED_UNICODE); ?>,
  customFields: <?php echo json_encode($customFields, JSON_UNESCAPED_UNICODE); ?>,
  mapDuplicateWarn: <?php echo json_encode($lang['Field already mapped with {column}'] ?? 'Field already mapped with {column}', JSON_UNESCAPED_UNICODE); ?>,
  mapDuplicateBlock: <?php echo json_encode($lang['Resolve duplicate field mappings before continuing.'] ?? 'Resolve duplicate field mappings before continuing.', JSON_UNESCAPED_UNICODE); ?>,
  mapFieldSearchPlaceholder: <?php echo json_encode($lang['Search CRM fields'] ?? 'Search fields…', JSON_UNESCAPED_UNICODE); ?>,
  previewLoadingLabel: <?php echo json_encode($lang['Loading preview'] ?? 'Loading preview…', JSON_UNESCAPED_UNICODE); ?>,
  importProgressStarting: <?php echo json_encode($lang['Starting import'] ?? 'Starting import…', JSON_UNESCAPED_UNICODE); ?>,
  importProgressProcessing: <?php echo json_encode($lang['Processing import rows'] ?? 'Processing rows…', JSON_UNESCAPED_UNICODE); ?>,
  importProgressQueued: <?php echo json_encode($lang['Import queued'] ?? 'Import queued…', JSON_UNESCAPED_UNICODE); ?>
};
</script>
<script src="<?php echo $url; ?>assets/js/user-import-wizard.js?v=<?php echo time(); ?>"></script>
<?php include __DIR__ . '/../../templates/main-footer.php'; ?>
