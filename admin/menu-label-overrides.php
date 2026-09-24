<?php
ob_start();
require_once("../includes/lib-initialize.php");
$title = ($lang['Menu Label Overrides'] ?? 'Menu Label Overrides') . " | " . $syatem_title;
include("../templates/header.php");

if (!($session->isLoggedIn())) {
    redirectTo($url . "index.php");
}
if ($_SESSION['accountStatus'] == 2) {
    redirectTo($url . "client/index.php");
}
if ($_SESSION['accountStatus'] == 3) {
    redirectTo($url . "staff/index.php");
}

// load current logged-in user
$id = $session->userId;
$user = User::findById((int)$id);
$username = $user->firstName;
$email = $user->email;
$account_stat = $user->status;

$settings = settings::findById(1);
/** @var array{type:string,msg:string}|null assets/js/toast.js reads window.__toastFlash */
$toast_flash = null;

function ensure_menu_override_columns($connect)
{
    $columns = array(
        "label_projects_override" => "ALTER TABLE settings ADD COLUMN label_projects_override VARCHAR(191) NULL DEFAULT NULL",
        "label_tasks_override" => "ALTER TABLE settings ADD COLUMN label_tasks_override VARCHAR(191) NULL DEFAULT NULL",
        "label_clients_override" => "ALTER TABLE settings ADD COLUMN label_clients_override VARCHAR(191) NULL DEFAULT NULL",
        "label_financials_override" => "ALTER TABLE settings ADD COLUMN label_financials_override VARCHAR(191) NULL DEFAULT NULL",
        "label_chatting_override" => "ALTER TABLE settings ADD COLUMN label_chatting_override VARCHAR(191) NULL DEFAULT NULL",
        "label_private_notes_override" => "ALTER TABLE settings ADD COLUMN label_private_notes_override VARCHAR(191) NULL DEFAULT NULL",
        "label_leads_override" => "ALTER TABLE settings ADD COLUMN label_leads_override VARCHAR(191) NULL DEFAULT NULL",
        "label_media_vault_override" => "ALTER TABLE settings ADD COLUMN label_media_vault_override VARCHAR(191) NULL DEFAULT NULL",
        "label_custom_fields_override" => "ALTER TABLE settings ADD COLUMN label_custom_fields_override VARCHAR(191) NULL DEFAULT NULL",
        "label_event_override" => "ALTER TABLE settings ADD COLUMN label_event_override VARCHAR(191) NULL DEFAULT NULL"
    );

    foreach ($columns as $column => $alterSql) {
        $safeColumn = mysqli_real_escape_string($connect, $column);
        $check = mysqli_query($connect, "SHOW COLUMNS FROM settings LIKE '" . $safeColumn . "'");
        if ($check && mysqli_num_rows($check) === 0) {
            mysqli_query($connect, $alterSql);
        }
    }
}

ensure_menu_override_columns($connect);
$settings = settings::findById(1);

if (isset($_POST['save_menu_labels'])) {
    $projectsLabel = trim(strip_tags((string)($_POST['label_projects_override'] ?? '')));
    $tasksLabel = trim(strip_tags((string)($_POST['label_tasks_override'] ?? '')));
    $clientsLabel = trim(strip_tags((string)($_POST['label_clients_override'] ?? '')));
    $financialsLabel = trim(strip_tags((string)($_POST['label_financials_override'] ?? '')));
    $chattingLabel = trim(strip_tags((string)($_POST['label_chatting_override'] ?? '')));
    $privateNotesLabel = trim(strip_tags((string)($_POST['label_private_notes_override'] ?? '')));
    $leadsLabel = trim(strip_tags((string)($_POST['label_leads_override'] ?? '')));
    $mediaVaultLabel = trim(strip_tags((string)($_POST['label_media_vault_override'] ?? '')));
    $customFieldsLabel = trim(strip_tags((string)($_POST['label_custom_fields_override'] ?? '')));
    $eventLabel = trim(strip_tags((string)($_POST['label_event_override'] ?? '')));

    if (!$settings) {
        header("location:menu-label-overrides.php?message=error");
        exit;
    }

    $settings->id = 1;
    $settings->label_projects_override = $projectsLabel;
    $settings->label_tasks_override = $tasksLabel;
    $settings->label_clients_override = $clientsLabel;
    $settings->label_financials_override = $financialsLabel;
    $settings->label_chatting_override = $chattingLabel;
    $settings->label_private_notes_override = $privateNotesLabel;
    $settings->label_leads_override = $leadsLabel;
    $settings->label_media_vault_override = $mediaVaultLabel;
    $settings->label_custom_fields_override = $customFieldsLabel;
    $settings->label_event_override = $eventLabel;

    $saved = $settings->save();
    if ($saved) {
        header("location:menu-label-overrides.php?message=success");
        exit;
    }

    header("location:menu-label-overrides.php?message=fail");
    exit;
}

if (isset($_POST['reset_menu_labels'])) {
    if (!$settings) {
        header("location:menu-label-overrides.php?message=error");
        exit;
    }

    $settings->id = 1;
    $settings->label_projects_override = '';
    $settings->label_tasks_override = '';
    $settings->label_clients_override = '';
    $settings->label_financials_override = '';
    $settings->label_chatting_override = '';
    $settings->label_private_notes_override = '';
    $settings->label_leads_override = '';
    $settings->label_media_vault_override = '';
    $settings->label_custom_fields_override = '';
    $settings->label_event_override = '';

    $resetSaved = $settings->save();
    if ($resetSaved) {
        header("location:menu-label-overrides.php?message=reset_success");
        exit;
    }

    header("location:menu-label-overrides.php?message=fail");
    exit;
}

if (isset($_GET['message'])) {
    $msg = $_GET['message'];
    if ($msg === 'success') {
        $toast_flash = array(
            'type' => 'success',
            'msg' => ($lang['Menu labels updated successfully'] ?? 'Menu labels updated successfully.'),
        );
    } elseif ($msg === 'reset_success') {
        $toast_flash = array(
            'type' => 'success',
            'msg' => ($lang['Menu labels reset to default successfully'] ?? 'Menu labels reset to default successfully.'),
        );
    } elseif ($msg === 'fail') {
        $toast_flash = array(
            'type' => 'error',
            'msg' => ($lang['Unable to save menu labels. Please try again'] ?? 'Unable to save menu labels. Please try again.'),
        );
    } elseif ($msg === 'error') {
        $toast_flash = array(
            'type' => 'error',
            'msg' => ($lang['Settings record not found'] ?? 'Settings record not found.'),
        );
    }
}
?>
<div class="page-container">
    <div class="container-fluid">
        <div class="row row-eq-height">
            <?php include("../templates/sidebar.php"); ?>
            <div class="page-content" style="padding-bottom:0;">
                <?php include("../templates/top-header.php"); ?>
                <div class="row system-wrap">
                    <?php include("../templates/system-nav.php"); ?>
                    <div class="col-md-9 ss-right">
                        <div class="system-settings-container">
                            <div class="settings-header">
                                <h2 class="page-title"><?php echo $lang['Menu Label Overrides'] ?? 'Menu Label Overrides'; ?></h2>
                            </div>

                            <form method="post" action="#" class="settings-form">
                                <div class="settings-grid">
                                    <div class="settings-main">
                                        <div class="settings-card">
                                            <div class="card-header">
                                                <h4><?php echo $lang['Menu Labels'] ?? 'Menu Labels'; ?></h4>
                                                <p><?php echo $lang['Override labels for key modules. Leave any field empty to use default language text.'] ?? 'Override labels for key modules. Leave any field empty to use default language text.'; ?></p>
                                            </div>
                                            <div class="card-body">
                                                <div class="form-group">
                                                    <label for="label_projects_override"><?php echo $lang['Projects Label'] ?? 'Projects Label'; ?></label>
                                                    <input
                                                        id="label_projects_override"
                                                        type="text"
                                                        maxlength="191"
                                                        class="form-control"
                                                        name="label_projects_override"
                                                        value="<?php echo htmlspecialchars((string)($settings->label_projects_override ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                        placeholder="<?php echo $lang['Projects'] ?? 'Projects'; ?>">
                                                </div>

                                                <div class="form-group">
                                                    <label for="label_tasks_override"><?php echo $lang['Tasks Label'] ?? 'Tasks Label'; ?></label>
                                                    <input
                                                        id="label_tasks_override"
                                                        type="text"
                                                        maxlength="191"
                                                        class="form-control"
                                                        name="label_tasks_override"
                                                        value="<?php echo htmlspecialchars((string)($settings->label_tasks_override ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                        placeholder="<?php echo $lang['Tasks'] ?? 'Tasks'; ?>">
                                                </div>

                                                <div class="form-group">
                                                    <label for="label_event_override"><?php echo $lang['Event Label'] ?? 'Event Label'; ?></label>
                                                    <input
                                                        id="label_event_override"
                                                        type="text"
                                                        maxlength="191"
                                                        class="form-control"
                                                        name="label_event_override"
                                                        value="<?php echo htmlspecialchars((string)($settings->label_event_override ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                        placeholder="<?php echo $lang['Add New Event'] ?? 'Add New Event'; ?>">
                                                    <small class="form-text text-muted"><?php echo $lang['Used for the Tasks submenu item that opens the calendar to add an event'] ?? 'Used for the Tasks submenu item that opens the calendar to add an event.'; ?></small>
                                                </div>

                                                <div class="form-group">
                                                    <label for="label_clients_override"><?php echo $lang['Clients Label'] ?? 'Clients Label'; ?></label>
                                                    <input
                                                        id="label_clients_override"
                                                        type="text"
                                                        maxlength="191"
                                                        class="form-control"
                                                        name="label_clients_override"
                                                        value="<?php echo htmlspecialchars((string)($settings->label_clients_override ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                        placeholder="<?php echo $lang['Clients'] ?? 'Clients'; ?>">
                                                </div>

                                                <div class="form-group">
                                                    <label for="label_financials_override"><?php echo $lang['Financials Label'] ?? 'Financials Label'; ?></label>
                                                    <input
                                                        id="label_financials_override"
                                                        type="text"
                                                        maxlength="191"
                                                        class="form-control"
                                                        name="label_financials_override"
                                                        value="<?php echo htmlspecialchars((string)($settings->label_financials_override ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                        placeholder="<?php echo $lang['Financials'] ?? 'Financials'; ?>">
                                                </div>

                                                <div class="form-group">
                                                    <label for="label_chatting_override"><?php echo $lang['Chatting Label'] ?? 'Chatting Label'; ?></label>
                                                    <input
                                                        id="label_chatting_override"
                                                        type="text"
                                                        maxlength="191"
                                                        class="form-control"
                                                        name="label_chatting_override"
                                                        value="<?php echo htmlspecialchars((string)($settings->label_chatting_override ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                        placeholder="<?php echo $lang['Chatting'] ?? 'Chatting'; ?>">
                                                </div>

                                                <div class="form-group">
                                                    <label for="label_private_notes_override"><?php echo $lang['Documents Label'] ?? 'Documents Label'; ?></label>
                                                    <input
                                                        id="label_private_notes_override"
                                                        type="text"
                                                        maxlength="191"
                                                        class="form-control"
                                                        name="label_private_notes_override"
                                                        value="<?php echo htmlspecialchars((string)($settings->label_private_notes_override ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                        placeholder="<?php echo $lang['Documents'] ?? 'Documents'; ?>">
                                                </div>

                                                <div class="form-group">
                                                    <label for="label_leads_override"><?php echo $lang['Leads Label'] ?? 'Leads Label'; ?></label>
                                                    <input
                                                        id="label_leads_override"
                                                        type="text"
                                                        maxlength="191"
                                                        class="form-control"
                                                        name="label_leads_override"
                                                        value="<?php echo htmlspecialchars((string)($settings->label_leads_override ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                        placeholder="<?php echo $lang['Leads'] ?? 'Leads'; ?>">
                                                </div>

                                                <div class="form-group">
                                                    <label for="label_media_vault_override"><?php echo $lang['Media Vault Label'] ?? 'Media Vault Label'; ?></label>
                                                    <input
                                                        id="label_media_vault_override"
                                                        type="text"
                                                        maxlength="191"
                                                        class="form-control"
                                                        name="label_media_vault_override"
                                                        value="<?php echo htmlspecialchars((string)($settings->label_media_vault_override ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                        placeholder="<?php echo $lang['Media Vault'] ?? 'Media Vault'; ?>">
                                                </div>

                                                <div class="form-group">
                                                    <label for="label_custom_fields_override"><?php echo $lang['Custom Fields Label'] ?? 'Custom Fields Label'; ?></label>
                                                    <input
                                                        id="label_custom_fields_override"
                                                        type="text"
                                                        maxlength="191"
                                                        class="form-control"
                                                        name="label_custom_fields_override"
                                                        value="<?php echo htmlspecialchars((string)($settings->label_custom_fields_override ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                        placeholder="<?php echo $lang['Custom Fields'] ?? 'Custom Fields'; ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="settings-sidebar">
                                        <div class="settings-card">
                                            <div class="card-header">
                                                <h4><?php echo $lang['Save Settings'] ?? 'Save Settings'; ?></h4>
                                                <p><?php echo $lang['Apply custom labels across the system'] ?? 'Apply custom labels across the system'; ?></p>
                                            </div>
                                            <div class="card-body">
                                                <div class="save-actions">
                                                    <button type="submit" name="save_menu_labels" class="btn primary-btn btn-save">
                                                        <?php echo $lang['Save Settings'] ?? 'Save Settings'; ?>
                                                    </button>
                                                    <button type="submit" name="reset_menu_labels" class="btn outline-btn btn-reset" onclick="return confirm('<?php echo htmlspecialchars($lang['Reset all menu labels to default?'] ?? 'Reset all menu labels to default?', ENT_QUOTES, 'UTF-8'); ?>');">
                                                        <?php echo $lang['Reset'] ?? 'Reset'; ?>
                                                    </button>
                                                </div>
                                                <div class="settings-info">
                                                    <div class="info-item">
                                                        <i class="fas fa-info-circle text-info"></i>
                                                        <span><?php echo $lang['Changes are applied immediately after save'] ?? 'Changes are applied immediately after save'; ?></span>
                                                    </div>
                                                    <div class="info-item">
                                                        <i class="fas fa-language text-warning"></i>
                                                        <span><?php echo $lang['Empty fields fallback to active language defaults'] ?? 'Empty fields fallback to active language defaults'; ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>window.__toastFlash=<?php echo json_encode($toast_flash, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
<script src="<?php echo htmlspecialchars(rtrim((string) $url, '/') . '/assets/js/toast.js', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php include("../templates/main-footer.php"); ?>
