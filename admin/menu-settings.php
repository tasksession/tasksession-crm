<?php
ob_start();
require_once("../includes/lib-initialize.php");
$title = ($lang['Menu settings'] ?? 'Menu settings') . " | " . $syatem_title;
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
$activeTab = 'labels';
if (isset($_GET['tab'])) {
    $tabParam = (string) $_GET['tab'];
    if (in_array($tabParam, ['reorder', 'landing', 'header'], true)) {
        $activeTab = $tabParam;
    }
}

require_once __DIR__ . '/../includes/sidebar_navigation.php';

function ensure_menu_override_columns($connect)
{
    if (!function_exists('crm_ensure_settings_columns')) {
        require_once __DIR__ . '/../includes/system_helpers.php';
    }
    crm_ensure_settings_columns($connect, array(
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
    ));
}

ensure_menu_override_columns($connect);
comon_sidebar_ensure_menu_config_columns($connect);
$settings = settings::findById(1);

$menuOrderKeys = comon_sidebar_get_menu_order($settings, 'admin');
$menuHiddenKeys = comon_sidebar_get_hidden_keys($settings);
$menuRegistry = comon_sidebar_menu_registry('admin');
$hideOnlyMenuRegistry = comon_sidebar_hide_only_menu_registry();
$hideOnlyMenuKeys = comon_sidebar_hide_only_menu_keys();
$headerVisibilityRegistry = comon_header_visibility_registry();
$headerVisibilityKeys = comon_header_visibility_keys();
$adminLandingOptions = comon_login_landing_page_options('admin');
$staffLandingOptions = comon_login_landing_page_options('staff');
$currentAdminLanding = comon_get_login_landing_page($settings, 'admin');
$currentStaffLanding = comon_get_login_landing_page($settings, 'staff');

if (isset($_POST['save_login_landing'])) {
    if (!$settings) {
        header('location:menu-settings.php?tab=landing&message=error');
        exit;
    }
    $adminLanding = comon_normalize_login_landing_page((string) ($_POST['admin_login_landing_page'] ?? ''), 'admin');
    $staffLanding = comon_normalize_login_landing_page((string) ($_POST['staff_login_landing_page'] ?? ''), 'staff');
    if ($adminLanding === '' || $staffLanding === '') {
        header('location:menu-settings.php?tab=landing&message=fail');
        exit;
    }
    $settings->id = 1;
    $settings->admin_login_landing_page = $adminLanding;
    $settings->staff_login_landing_page = $staffLanding;
    if ($settings->save()) {
        header('location:menu-settings.php?tab=landing&message=landing_success');
        exit;
    }
    header('location:menu-settings.php?tab=landing&message=fail');
    exit;
}

if (isset($_POST['reset_login_landing'])) {
    if (!$settings) {
        header('location:menu-settings.php?tab=landing&message=error');
        exit;
    }
    $settings->id = 1;
    $settings->admin_login_landing_page = 'admin/dashboard';
    $settings->staff_login_landing_page = 'staff/dashboard';
    if ($settings->save()) {
        header('location:menu-settings.php?tab=landing&message=landing_reset_success');
        exit;
    }
    header('location:menu-settings.php?tab=landing&message=fail');
    exit;
}

if (isset($_POST['save_menu_order'])) {
    $orderRaw = (string)($_POST['sidebar_menu_order_json'] ?? '[]');
    $hiddenRaw = (string)($_POST['sidebar_menu_hidden_json'] ?? '[]');
    $orderDecoded = json_decode($orderRaw, true);
    $hiddenDecoded = json_decode($hiddenRaw, true);
    $allowedKeys = array_keys($menuRegistry);
    $allowedHiddenKeys = array_merge($allowedKeys, $hideOnlyMenuKeys);

    if (!$settings || !is_array($orderDecoded) || !is_array($hiddenDecoded)) {
        header('location:menu-settings.php?tab=reorder&message=fail');
        exit;
    }

    $cleanOrder = [];
    foreach ($orderDecoded as $key) {
        $key = trim((string)$key);
        if ($key !== '' && in_array($key, $allowedKeys, true) && !in_array($key, $cleanOrder, true)) {
            $cleanOrder[] = $key;
        }
    }
    foreach ($allowedKeys as $key) {
        if (!in_array($key, $cleanOrder, true)) {
            $cleanOrder[] = $key;
        }
    }

    $cleanHidden = [];
    foreach ($hiddenDecoded as $key) {
        $key = trim((string)$key);
        if ($key === '' || !in_array($key, $allowedHiddenKeys, true)) {
            continue;
        }
        if (in_array($key, $hideOnlyMenuKeys, true)) {
            $cleanHidden[] = $key;
            continue;
        }
        if (!comon_sidebar_menu_item_available($key, $settings, 'admin')) {
            continue;
        }
        $cleanHidden[] = $key;
    }

    foreach (comon_sidebar_get_hidden_keys($settings) as $preserveKey) {
        if (in_array($preserveKey, $headerVisibilityKeys, true) && !in_array($preserveKey, $cleanHidden, true)) {
            $cleanHidden[] = $preserveKey;
        }
    }

    $settings->id = 1;
    $settings->sidebar_menu_order = json_encode(array_values($cleanOrder));
    $settings->sidebar_menu_hidden = json_encode(array_values(array_unique($cleanHidden)));

    if ($settings->save()) {
        header('location:menu-settings.php?tab=reorder&message=order_success');
        exit;
    }
    header('location:menu-settings.php?tab=reorder&message=fail');
    exit;
}

if (isset($_POST['save_header_visibility'])) {
    $hiddenRaw = (string) ($_POST['header_menu_hidden_json'] ?? '[]');
    $hiddenDecoded = json_decode($hiddenRaw, true);

    if (!$settings || !is_array($hiddenDecoded)) {
        header('location:menu-settings.php?tab=header&message=fail');
        exit;
    }

    $cleanHeaderHidden = [];
    foreach ($hiddenDecoded as $key) {
        $key = trim((string) $key);
        if ($key !== '' && in_array($key, $headerVisibilityKeys, true)) {
            $cleanHeaderHidden[] = $key;
        }
    }

    $mergedHidden = [];
    foreach (comon_sidebar_get_hidden_keys($settings) as $existingKey) {
        if (!in_array($existingKey, $headerVisibilityKeys, true)) {
            $mergedHidden[] = $existingKey;
        }
    }
    foreach ($cleanHeaderHidden as $key) {
        $mergedHidden[] = $key;
    }

    $settings->id = 1;
    $settings->sidebar_menu_hidden = json_encode(array_values(array_unique($mergedHidden)));

    if ($settings->save()) {
        header('location:menu-settings.php?tab=header&message=header_success');
        exit;
    }
    header('location:menu-settings.php?tab=header&message=fail');
    exit;
}

if (isset($_POST['reset_header_visibility'])) {
    if (!$settings) {
        header('location:menu-settings.php?tab=header&message=error');
        exit;
    }
    $mergedHidden = [];
    foreach (comon_sidebar_get_hidden_keys($settings) as $existingKey) {
        if (!in_array($existingKey, $headerVisibilityKeys, true)) {
            $mergedHidden[] = $existingKey;
        }
    }
    $settings->id = 1;
    $settings->sidebar_menu_hidden = json_encode(array_values(array_unique($mergedHidden)));
    if ($settings->save()) {
        header('location:menu-settings.php?tab=header&message=header_reset_success');
        exit;
    }
    header('location:menu-settings.php?tab=header&message=fail');
    exit;
}

if (isset($_POST['reset_menu_order'])) {
    if (!$settings) {
        header('location:menu-settings.php?tab=reorder&message=error');
        exit;
    }
    $preserveHeaderHidden = [];
    foreach (comon_sidebar_get_hidden_keys($settings) as $existingKey) {
        if (in_array($existingKey, $headerVisibilityKeys, true)) {
            $preserveHeaderHidden[] = $existingKey;
        }
    }
    $settings->id = 1;
    $settings->sidebar_menu_order = '';
    $settings->sidebar_menu_hidden = json_encode(array_values(array_unique($preserveHeaderHidden)));
    if ($settings->save()) {
        header('location:menu-settings.php?tab=reorder&message=order_reset_success');
        exit;
    }
    header('location:menu-settings.php?tab=reorder&message=fail');
    exit;
}

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
        header("location:menu-settings.php?message=error");
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
        header("location:menu-settings.php?message=success");
        exit;
    }

    header("location:menu-settings.php?message=fail");
    exit;
}

if (isset($_POST['reset_menu_labels'])) {
    if (!$settings) {
        header("location:menu-settings.php?message=error");
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
        header("location:menu-settings.php?message=reset_success");
        exit;
    }

    header("location:menu-settings.php?message=fail");
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
    } elseif ($msg === 'order_success') {
        $toast_flash = array(
            'type' => 'success',
            'msg' => ($lang['Sidebar menu order saved'] ?? 'Sidebar menu order saved.'),
        );
    } elseif ($msg === 'order_reset_success') {
        $toast_flash = array(
            'type' => 'success',
            'msg' => ($lang['Sidebar menu order reset to default'] ?? 'Sidebar menu order reset to default.'),
        );
    } elseif ($msg === 'landing_success') {
        $toast_flash = array(
            'type' => 'success',
            'msg' => ($lang['Login landing pages updated successfully'] ?? 'Login landing pages updated successfully.'),
        );
    } elseif ($msg === 'landing_reset_success') {
        $toast_flash = array(
            'type' => 'success',
            'msg' => ($lang['Login landing pages reset to default'] ?? 'Login landing pages reset to default.'),
        );
    } elseif ($msg === 'header_success') {
        $toast_flash = array(
            'type' => 'success',
            'msg' => ($lang['Header visibility saved'] ?? 'Header visibility saved.'),
        );
    } elseif ($msg === 'header_reset_success') {
        $toast_flash = array(
            'type' => 'success',
            'msg' => ($lang['Header visibility reset to default'] ?? 'Header visibility reset to default.'),
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
                                <h2 class="page-title"><?php echo $lang['Menu settings'] ?? 'Menu settings'; ?></h2>
                            </div>

                            <ul class="nav nav-tabs mb-3" id="menuOverrideTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link<?php echo $activeTab === 'labels' ? ' active' : ''; ?>" id="menu-labels-tab" data-bs-toggle="tab" data-bs-target="#menu-labels-pane" type="button" role="tab" aria-controls="menu-labels-pane" aria-selected="<?php echo $activeTab === 'labels' ? 'true' : 'false'; ?>"><?php echo $lang['Menu Label'] ?? 'Menu Label'; ?></button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link<?php echo $activeTab === 'reorder' ? ' active' : ''; ?>" id="menu-reorder-tab" data-bs-toggle="tab" data-bs-target="#menu-reorder-pane" type="button" role="tab" aria-controls="menu-reorder-pane" aria-selected="<?php echo $activeTab === 'reorder' ? 'true' : 'false'; ?>"><?php echo $lang['Reorder'] ?? 'Reorder'; ?></button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link<?php echo $activeTab === 'header' ? ' active' : ''; ?>" id="menu-header-tab" data-bs-toggle="tab" data-bs-target="#menu-header-pane" type="button" role="tab" aria-controls="menu-header-pane" aria-selected="<?php echo $activeTab === 'header' ? 'true' : 'false'; ?>"><?php echo $lang['Header'] ?? 'Header'; ?></button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link<?php echo $activeTab === 'landing' ? ' active' : ''; ?>" id="menu-landing-tab" data-bs-toggle="tab" data-bs-target="#menu-landing-pane" type="button" role="tab" aria-controls="menu-landing-pane" aria-selected="<?php echo $activeTab === 'landing' ? 'true' : 'false'; ?>"><?php echo $lang['Login Landing'] ?? 'Login Landing'; ?></button>
                                </li>
                            </ul>

                            <div class="tab-content" id="menuOverrideTabContent">
                            <div class="tab-pane fade<?php echo $activeTab === 'labels' ? ' show active' : ''; ?>" id="menu-labels-pane" role="tabpanel" aria-labelledby="menu-labels-tab">
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
                                                        placeholder="<?php echo $lang['Custom Fields'] ?? 'Custom fields'; ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="settings-sidebar">
                                        <div class="settings-card">
                                            <div class="card-header">
                                                <h4><?php echo $lang['Save Settings'] ?? 'Save settings'; ?></h4>
                                                <p><?php echo $lang['Apply custom labels across the system'] ?? 'Apply custom labels across the system'; ?></p>
                                            </div>
                                            <div class="card-body">
                                                <div class="save-actions">
                                                    <button type="submit" name="save_menu_labels" class="btn primary-btn btn-save">
                                                        <?php echo $lang['Save Settings'] ?? 'Save settings'; ?>
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

                            <div class="tab-pane fade<?php echo $activeTab === 'reorder' ? ' show active' : ''; ?>" id="menu-reorder-pane" role="tabpanel" aria-labelledby="menu-reorder-tab">
                            <form method="post" action="#" class="settings-form" id="menuReorderForm">
                                <div class="settings-grid">
                                    <div class="settings-main">
                                        <div class="settings-card">
                                            <div class="card-header">
                                                <h4><?php echo $lang['Menu Reorder'] ?? 'Menu Reorder'; ?></h4>
                                                <p><?php echo $lang['Drag to reorder sidebar menus'] ?? 'Drag to reorder sidebar menus. Toggle visibility to hide items from the admin sidebar.'; ?></p>
                                            </div>
                                            <div class="card-body">
                                                <ul id="sidebarMenuSortable" class="list-unstyled sidebar-menu-sortable">
                                                    <?php foreach ($hideOnlyMenuKeys as $fixedKey) :
                                                        if (!isset($hideOnlyMenuRegistry[$fixedKey])) {
                                                            continue;
                                                        }
                                                        $fixedMeta = $hideOnlyMenuRegistry[$fixedKey];
                                                        $fixedHidden = in_array($fixedKey, $menuHiddenKeys, true);
                                                        ?>
                                                    <li class="sidebar-menu-sortable__item is-fixed<?php echo $fixedHidden ? ' is-hidden-item' : ''; ?>" data-menu-key="<?php echo htmlspecialchars($fixedKey, ENT_QUOTES, 'UTF-8'); ?>" data-available="1" data-no-sort="1">
                                                        <span class="sidebar-menu-sortable__handle is-fixed-handle" aria-hidden="true" title="<?php echo htmlspecialchars($fixedMeta['note'], ENT_QUOTES, 'UTF-8'); ?>">&#8942;</span>
                                                        <span class="sidebar-menu-sortable__label"><?php echo htmlspecialchars($fixedMeta['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                        <span class="badge bg-secondary ms-2"><?php echo $lang['Fixed position'] ?? 'Fixed position'; ?></span>
                                                        <button type="button" class="sidebar-menu-sortable__toggle border-btn-a" data-hidden="<?php echo $fixedHidden ? '1' : '0'; ?>" data-label-hide="<?php echo htmlspecialchars($lang['Hide menu'] ?? 'Hide menu', ENT_QUOTES, 'UTF-8'); ?>" data-label-show="<?php echo htmlspecialchars($lang['Show menu'] ?? 'Show menu', ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo $fixedHidden ? ($lang['Show menu'] ?? 'Show menu') : ($lang['Hide menu'] ?? 'Hide menu'); ?>">
                                                            <?php echo $fixedHidden ? ($lang['Show menu'] ?? 'Show menu') : ($lang['Hide menu'] ?? 'Hide menu'); ?>
                                                        </button>
                                                    </li>
                                                    <?php endforeach; ?>
                                                    <?php foreach ($menuOrderKeys as $menuKey) :
                                                        if (!isset($menuRegistry[$menuKey])) {
                                                            continue;
                                                        }
                                                        $menuMeta = $menuRegistry[$menuKey];
                                                        $isAvailable = comon_sidebar_menu_item_available($menuKey, $settings, 'admin');
                                                        $isHidden = in_array($menuKey, $menuHiddenKeys, true);
                                                        ?>
                                                    <li class="sidebar-menu-sortable__item<?php echo !$isAvailable ? ' is-disabled' : ''; ?><?php echo $isHidden ? ' is-hidden-item' : ''; ?>" data-menu-key="<?php echo htmlspecialchars($menuKey, ENT_QUOTES, 'UTF-8'); ?>" data-available="<?php echo $isAvailable ? '1' : '0'; ?>">
                                                        <span class="sidebar-menu-sortable__handle" aria-hidden="true">&#9776;</span>
                                                        <span class="sidebar-menu-sortable__label"><?php echo htmlspecialchars($menuMeta['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                        <?php if (!$isAvailable) : ?>
                                                        <span class="badge bg-secondary ms-2"><?php echo $lang['Module off'] ?? 'Module off'; ?></span>
                                                        <?php endif; ?>
                                                        <button type="button" class="sidebar-menu-sortable__toggle border-btn-a" data-hidden="<?php echo $isHidden ? '1' : '0'; ?>" <?php echo !$isAvailable ? 'disabled' : ''; ?> title="<?php echo $isHidden ? ($lang['Show menu'] ?? 'Show menu') : ($lang['Hide menu'] ?? 'Hide menu'); ?>">
                                                            <?php echo $isHidden ? ($lang['Show menu'] ?? 'Show menu') : ($lang['Hide menu'] ?? 'Hide menu'); ?>
                                                        </button>
                                                    </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                                <input type="hidden" name="sidebar_menu_order_json" id="sidebar_menu_order_json" value="">
                                                <input type="hidden" name="sidebar_menu_hidden_json" id="sidebar_menu_hidden_json" value="">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="settings-sidebar">
                                        <div class="settings-card">
                                            <div class="card-header">
                                                <h4><?php echo $lang['Save Settings'] ?? 'Save settings'; ?></h4>
                                                <p><?php echo $lang['Apply sidebar order for admin users'] ?? 'Apply sidebar order for admin users.'; ?></p>
                                            </div>
                                            <div class="card-body">
                                                <div class="save-actions">
                                                    <button type="submit" name="save_menu_order" class="btn primary-btn btn-save"><?php echo $lang['Save Settings'] ?? 'Save settings'; ?></button>
                                                    <button type="submit" name="reset_menu_order" class="btn outline-btn btn-reset" onclick="return confirm('<?php echo htmlspecialchars($lang['Reset sidebar order?'] ?? 'Reset sidebar order?', ENT_QUOTES, 'UTF-8'); ?>');"><?php echo $lang['Reset'] ?? 'Reset'; ?></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                            </div>

                            <div class="tab-pane fade<?php echo $activeTab === 'header' ? ' show active' : ''; ?>" id="menu-header-pane" role="tabpanel" aria-labelledby="menu-header-tab">
                            <form method="post" action="#" class="settings-form" id="headerMenuForm">
                                <div class="settings-grid">
                                    <div class="settings-main">
                                        <div class="settings-card">
                                            <div class="card-header">
                                                <h4><?php echo $lang['Header visibility'] ?? 'Header visibility'; ?></h4>
                                                <p><?php echo $lang['Toggle header element visibility'] ?? 'Toggle visibility to hide elements from the top header for all users.'; ?></p>
                                            </div>
                                            <div class="card-body">
                                                <ul id="headerMenuSortable" class="list-unstyled sidebar-menu-sortable">
                                                    <?php foreach ($headerVisibilityKeys as $headerKey) :
                                                        if (!isset($headerVisibilityRegistry[$headerKey])) {
                                                            continue;
                                                        }
                                                        $headerMeta = $headerVisibilityRegistry[$headerKey];
                                                        $headerHidden = in_array($headerKey, $menuHiddenKeys, true);
                                                        ?>
                                                    <li class="sidebar-menu-sortable__item is-fixed<?php echo $headerHidden ? ' is-hidden-item' : ''; ?>" data-menu-key="<?php echo htmlspecialchars($headerKey, ENT_QUOTES, 'UTF-8'); ?>" data-available="1" data-no-sort="1">
                                                        <span class="sidebar-menu-sortable__handle is-fixed-handle" aria-hidden="true" title="<?php echo htmlspecialchars($headerMeta['note'], ENT_QUOTES, 'UTF-8'); ?>">&#8942;</span>
                                                        <span class="sidebar-menu-sortable__label"><?php echo htmlspecialchars($headerMeta['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                        <button type="button" class="sidebar-menu-sortable__toggle border-btn-a" data-hidden="<?php echo $headerHidden ? '1' : '0'; ?>" data-label-hide="<?php echo htmlspecialchars($lang['Hide from header'] ?? 'Hide from header', ENT_QUOTES, 'UTF-8'); ?>" data-label-show="<?php echo htmlspecialchars($lang['Show in header'] ?? 'Show in header', ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo $headerHidden ? ($lang['Show in header'] ?? 'Show in header') : ($lang['Hide from header'] ?? 'Hide from header'); ?>">
                                                            <?php echo $headerHidden ? ($lang['Show in header'] ?? 'Show in header') : ($lang['Hide from header'] ?? 'Hide from header'); ?>
                                                        </button>
                                                    </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                                <input type="hidden" name="header_menu_hidden_json" id="header_menu_hidden_json" value="">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="settings-sidebar">
                                        <div class="settings-card">
                                            <div class="card-header">
                                                <h4><?php echo $lang['Save Settings'] ?? 'Save settings'; ?></h4>
                                                <p><?php echo $lang['Apply header visibility for all users'] ?? 'Apply header visibility for all users.'; ?></p>
                                            </div>
                                            <div class="card-body">
                                                <div class="save-actions">
                                                    <button type="submit" name="save_header_visibility" class="btn primary-btn btn-save"><?php echo $lang['Save Settings'] ?? 'Save settings'; ?></button>
                                                    <button type="submit" name="reset_header_visibility" class="btn outline-btn btn-reset" onclick="return confirm('<?php echo htmlspecialchars($lang['Reset header visibility?'] ?? 'Reset header visibility?', ENT_QUOTES, 'UTF-8'); ?>');"><?php echo $lang['Reset'] ?? 'Reset'; ?></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                            </div>

                            <div class="tab-pane fade<?php echo $activeTab === 'landing' ? ' show active' : ''; ?>" id="menu-landing-pane" role="tabpanel" aria-labelledby="menu-landing-tab">
                            <form method="post" action="#" class="settings-form">
                                <div class="settings-grid">
                                    <div class="settings-main">
                                        <div class="settings-card">
                                            <div class="card-header">
                                                <h4><?php echo $lang['Login Landing Page'] ?? 'Login Landing Page'; ?></h4>
                                                <p><?php echo $lang['Choose the default page shown after login for admin and staff users'] ?? 'Choose the default page shown after login for admin and staff users. Default is each role dashboard (index.php).'; ?></p>
                                            </div>
                                            <div class="card-body">
                                                <div class="form-group">
                                                    <label for="admin_login_landing_page"><?php echo $lang['Admin login landing page'] ?? 'Admin login landing page'; ?></label>
                                                    <select class="form-control" name="admin_login_landing_page" id="admin_login_landing_page">
                                                        <?php foreach ($adminLandingOptions as $landingPath => $landingLabel) : ?>
                                                        <option value="<?php echo htmlspecialchars($landingPath, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $currentAdminLanding === $landingPath ? 'selected' : ''; ?>><?php echo htmlspecialchars($landingLabel . ' (' . $landingPath . ')', ENT_QUOTES, 'UTF-8'); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <label for="staff_login_landing_page"><?php echo $lang['Staff login landing page'] ?? 'Staff login landing page'; ?></label>
                                                    <select class="form-control" name="staff_login_landing_page" id="staff_login_landing_page">
                                                        <?php foreach ($staffLandingOptions as $landingPath => $landingLabel) : ?>
                                                        <option value="<?php echo htmlspecialchars($landingPath, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $currentStaffLanding === $landingPath ? 'selected' : ''; ?>><?php echo htmlspecialchars($landingLabel . ' (' . $landingPath . ')', ENT_QUOTES, 'UTF-8'); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <small class="form-text text-muted"><?php echo $lang['Addon auto redirect note'] ?? 'When Projects & Tasks is off and only one addon is active with no other CRM modules, login may still redirect to that addon dashboard.'; ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="settings-sidebar">
                                        <div class="settings-card">
                                            <div class="card-header">
                                                <h4><?php echo $lang['Save Settings'] ?? 'Save settings'; ?></h4>
                                                <p><?php echo $lang['Apply login landing pages immediately'] ?? 'Apply login landing pages immediately after save.'; ?></p>
                                            </div>
                                            <div class="card-body">
                                                <div class="save-actions">
                                                    <button type="submit" name="save_login_landing" class="btn primary-btn btn-save"><?php echo $lang['Save Settings'] ?? 'Save settings'; ?></button>
                                                    <button type="submit" name="reset_login_landing" class="btn outline-btn btn-reset" onclick="return confirm('<?php echo htmlspecialchars($lang['Reset login landing pages to default?'] ?? 'Reset login landing pages to default?', ENT_QUOTES, 'UTF-8'); ?>');"><?php echo $lang['Reset'] ?? 'Reset'; ?></button>
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
    </div>
</div>
<script>window.__toastFlash=<?php echo json_encode($toast_flash, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
<link rel="stylesheet" href="<?php echo htmlspecialchars(rtrim((string) $url, '/') . '/assets/css/jquery-ui.min.css', ENT_QUOTES, 'UTF-8'); ?>">
<style>
.sidebar-menu-sortable { margin: 0; padding: 0; }
.sidebar-menu-sortable__item {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 12px 14px;
    margin-bottom: 8px;
    border: 1px solid var(--border-color);
    border-radius: var(--card-border-radius, 8px);
    background: var(--card-body-color);
    color: var(--body-font-color);
    box-shadow: var(--box-shadow, 0px 3px 13px -5px rgb(0 0 0 / 0.1));
}
.sidebar-menu-sortable__item.is-disabled { opacity: 0.65; }
.sidebar-menu-sortable__item.is-hidden-item {
    opacity: 0.72;
    background: color-mix(in srgb, var(--card-body-color) 82%, var(--body-bg-color));
}
.sidebar-menu-sortable__handle {
    cursor: grab;
    font-size: 18px;
    line-height: 1;
    color: color-mix(in srgb, var(--body-font-color) 55%, transparent);
}
.sidebar-menu-sortable__label {
    flex: 1 1 auto;
    min-width: 0;
    font-weight: 600;
    color: var(--title-color);
}
.sidebar-menu-sortable__toggle:disabled {
    color: color-mix(in srgb, var(--body-font-color) 45%, transparent) !important;
}
.sidebar-menu-sortable__handle.is-fixed-handle {
    cursor: not-allowed;
    opacity: 0.35;
}
.sidebar-menu-sortable__item.is-fixed {
    border-style: dashed;
}
.sidebar-menu-sortable__placeholder {
    border: 2px dashed var(--border-color);
    min-height: 48px;
    margin-bottom: 8px;
    border-radius: var(--card-border-radius, 8px);
    background: color-mix(in srgb, var(--card-body-color) 90%, var(--body-bg-color));
}
</style>
<?php
$GLOBALS['comon_before_body_close_html'] = '<script src="' . htmlspecialchars(rtrim((string) $url, '/') . '/assets/js/menu-label-overrides.js', ENT_QUOTES, 'UTF-8') . '"></script>'
    . '<script src="' . htmlspecialchars(rtrim((string) $url, '/') . '/assets/js/toast.js', ENT_QUOTES, 'UTF-8') . '"></script>';
?>
<?php include("../templates/main-footer.php"); ?>
