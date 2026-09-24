<?php
/**
 * TaskSession Free edition — Pro feature registry, guards, and upgrade rendering.
 * This package is always Free; Pro features are stubbed to an upgrade screen for Admin/Staff.
 */

if (!defined('TASKSESSION_FREE_EDITION')) {
    define('TASKSESSION_FREE_EDITION', true);
}

if (!defined('TASKSESSION_PRO_UPGRADE_URL')) {
    define('TASKSESSION_PRO_UPGRADE_URL', 'https://www.tasksession.com/pricing/');
}

/**
 * @return array<string, array{name:string,description:string,upgrade_url:string}>
 */
function tasksession_pro_features_registry()
{
    static $registry = null;
    if ($registry !== null) {
        return $registry;
    }

    $url = TASKSESSION_PRO_UPGRADE_URL;
    $registry = array(
        'chatting' => array(
            'name' => 'Chatting',
            'description' => 'Unlock team chatting with TaskSession Pro.',
            'upgrade_url' => $url,
        ),
        'discussion' => array(
            'name' => 'Discussion',
            'description' => 'Unlock project discussions with TaskSession Pro.',
            'upgrade_url' => $url,
        ),
        'mail' => array(
            'name' => 'Mail',
            'description' => 'Unlock workspace email tools with TaskSession Pro.',
            'upgrade_url' => $url,
        ),
        'leads' => array(
            'name' => 'Leads',
            'description' => 'Unlock lead management with TaskSession Pro.',
            'upgrade_url' => $url,
        ),
        'addons' => array(
            'name' => 'Add-ons',
            'description' => 'Unlock TaskSession add-ons with TaskSession Pro.',
            'upgrade_url' => $url,
        ),
        'companies' => array(
            'name' => 'Companies',
            'description' => 'Unlock company management with TaskSession Pro.',
            'upgrade_url' => $url,
        ),
        'payments' => array(
            'name' => 'Payments',
            'description' => 'Unlock payment management with TaskSession Pro.',
            'upgrade_url' => $url,
        ),
        'files_media' => array(
            'name' => 'Files & Media',
            'description' => 'Unlock project files and media with TaskSession Pro.',
            'upgrade_url' => $url,
        ),
        'media_vault' => array(
            'name' => 'Media Vault',
            'description' => 'Unlock Media Vault with TaskSession Pro.',
            'upgrade_url' => $url,
        ),
        'attendance' => array(
            'name' => 'Attendance',
            'description' => 'Unlock attendance management with TaskSession Pro.',
            'upgrade_url' => $url,
        ),
        'invoices' => array(
            'name' => 'Invoices',
            'description' => 'Unlock invoicing with TaskSession Pro.',
            'upgrade_url' => $url,
        ),
        'task_chat' => array(
            'name' => 'Task Chat',
            'description' => 'Unlock task chatting with TaskSession Pro.',
            'upgrade_url' => $url,
        ),
        'media_management' => array(
            'name' => 'Media Management',
            'description' => 'Unlock media and cache management with TaskSession Pro.',
            'upgrade_url' => $url,
        ),
        'attempts_ip' => array(
            'name' => 'Login Attempts & IP Setup',
            'description' => 'Unlock login attempts and IP controls with TaskSession Pro.',
            'upgrade_url' => $url,
        ),
        'google_drive' => array(
            'name' => 'Google Drive Integration',
            'description' => 'Unlock Google Drive integration with TaskSession Pro.',
            'upgrade_url' => $url,
        ),
        'google_calendar' => array(
            'name' => 'Google Calendar Integration',
            'description' => 'Unlock Google Calendar sync with TaskSession Pro.',
            'upgrade_url' => $url,
        ),
        'custom_fields' => array(
            'name' => 'Custom Fields',
            'description' => 'Unlock custom fields with TaskSession Pro.',
            'upgrade_url' => $url,
        ),
        'server_load' => array(
            'name' => 'Server Management',
            'description' => 'Unlock server load monitoring with TaskSession Pro.',
            'upgrade_url' => $url,
        ),
        'chat_settings' => array(
            'name' => 'Chat Settings',
            'description' => 'Unlock chat settings with TaskSession Pro.',
            'upgrade_url' => $url,
        ),
        'google_login' => array(
            'name' => 'Google Login',
            'description' => 'Unlock Google Login with TaskSession Pro.',
            'upgrade_url' => $url,
        ),
        'google_authenticator' => array(
            'name' => 'Google Authenticator',
            'description' => 'Unlock Google Authenticator (2FA) settings with TaskSession Pro.',
            'upgrade_url' => $url,
        ),
        'task_reports' => array(
            'name' => 'Task Reports',
            'description' => 'Unlock task reports with TaskSession Pro.',
            'upgrade_url' => $url,
        ),
        'project_reports' => array(
            'name' => 'Project Reports',
            'description' => 'Unlock project reports with TaskSession Pro.',
            'upgrade_url' => $url,
        ),
        'financial_reports' => array(
            'name' => 'Financial Reports',
            'description' => 'Unlock financial reports with TaskSession Pro.',
            'upgrade_url' => $url,
        ),
        'modules' => array(
            'name' => 'Modules',
            'description' => 'Unlock module management to enable or disable features across your workspace with TaskSession Pro.',
            'upgrade_url' => $url,
        ),
    );

    return $registry;
}

function tasksession_is_free_edition()
{
    return defined('TASKSESSION_FREE_EDITION') && TASKSESSION_FREE_EDITION;
}

/**
 * Free edition keeps every settings.module_* flag ON (no admin toggles).
 */
function tasksession_free_module_flag_columns()
{
    return array(
        'module_lead_board',
        'module_invoices',
        'module_projects',
        'module_tasks',
        'module_email',
        'module_file_management',
        'module_notes_documents',
        'module_discussions',
        'module_attendance',
        'module_ip_restriction',
        'module_time_tracking',
        'module_reports',
    );
}

/**
 * Ensure Free installs have all modules enabled (idempotent).
 */
function tasksession_free_ensure_modules_enabled()
{
    static $done = false;
    if ($done || !tasksession_is_free_edition()) {
        return;
    }
    $done = true;

    $connect = null;
    if (isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli) {
        $connect = $GLOBALS['connect'];
    } elseif (isset($GLOBALS['database']) && is_object($GLOBALS['database']) && isset($GLOBALS['database']->connection)) {
        $connect = $GLOBALS['database']->connection;
    }
    if (!$connect instanceof mysqli) {
        return;
    }

    $cols = array();
    $res = @$connect->query('SHOW COLUMNS FROM `settings`');
    if (!$res) {
        return;
    }
    while ($row = $res->fetch_assoc()) {
        if (!empty($row['Field'])) {
            $cols[$row['Field']] = true;
        }
    }
    $res->free();

    $sets = array();
    $whereOff = array();
    foreach (tasksession_free_module_flag_columns() as $field) {
        if (empty($cols[$field])) {
            continue;
        }
        $sets[] = '`' . $field . '` = 1';
        $whereOff[] = '`' . $field . '` = 0';
    }
    if (empty($sets)) {
        return;
    }

    $sql = 'UPDATE `settings` SET ' . implode(', ', $sets)
        . ' WHERE `id` = 1 AND (' . implode(' OR ', $whereOff) . ')';
    @$connect->query($sql);
}

/**
 * Ensure shared tables still queried by Free CRM exist (idempotent).
 * Fresh Free installs previously skipped milestones; dashboard/profile still read them.
 */
function tasksession_free_ensure_core_tables()
{
    static $done = false;
    if ($done || !tasksession_is_free_edition()) {
        return;
    }
    $done = true;

    $connect = null;
    if (isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli) {
        $connect = $GLOBALS['connect'];
    } elseif (isset($GLOBALS['database']) && is_object($GLOBALS['database']) && isset($GLOBALS['database']->connection)) {
        $connect = $GLOBALS['database']->connection;
    }
    if (!$connect instanceof mysqli) {
        return;
    }

    $hasMilestones = false;
    if (function_exists('crm_db_table_exists')) {
        $hasMilestones = crm_db_table_exists($connect, 'milestones');
    } else {
        $__chk = @$connect->query("SHOW TABLES LIKE 'milestones'");
        $hasMilestones = ($__chk && $__chk->num_rows > 0);
    }
    if (!$hasMilestones) {
        @$connect->query("CREATE TABLE IF NOT EXISTS milestones (
            id int(255) NOT NULL AUTO_INCREMENT,
            p_id int(255) DEFAULT NULL,
            c_id int(255) DEFAULT NULL,
            company_id INT UNSIGNED DEFAULT NULL,
            company_billing_snapshot TEXT DEFAULT NULL,
            title varchar(255) COLLATE utf8_unicode_ci NOT NULL,
            deadline date NOT NULL,
            releaseDate date DEFAULT NULL,
            budget varchar(255) COLLATE utf8_unicode_ci NOT NULL,
            paid_total DECIMAL(10,2) DEFAULT NULL,
            status tinyint(4) NOT NULL,
            bill_from varchar(255) DEFAULT NULL,
            bill_from_address varchar(255) DEFAULT NULL,
            currency varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
            created_by INT NULL,
            sales_tax DECIMAL(5,2) DEFAULT NULL,
            sales_tax_type VARCHAR(10) DEFAULT 'percentage',
            discount DECIMAL(10,2) DEFAULT NULL,
            discount_type VARCHAR(10) DEFAULT 'percentage',
            memo TEXT DEFAULT NULL,
            issue_date DATE DEFAULT NULL,
            is_recurring TINYINT(1) DEFAULT 0,
            recurring_frequency VARCHAR(20) DEFAULT NULL,
            recurring_parent_id INT(11) DEFAULT NULL,
            recurring_next_date DATE DEFAULT NULL,
            recurring_stopped TINYINT(1) DEFAULT 0,
            footer TEXT DEFAULT NULL,
            recurring_end_date DATE DEFAULT NULL,
            recurring_next_renewal_date DATE DEFAULT NULL,
            recurring_billing_cycle_days INT(11) DEFAULT NULL,
            recurring_paused TINYINT(1) DEFAULT 0,
            recurring_auto_charge TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY p_id (p_id),
            KEY idx_milestones_company_id (company_id),
            KEY idx_milestones_created_by (created_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");
        if (isset($GLOBALS['__crm_db_table_cache']) && is_array($GLOBALS['__crm_db_table_cache'])) {
            unset($GLOBALS['__crm_db_table_cache']['milestones']);
        }
    }

    $hasInvoiceItems = false;
    if (function_exists('crm_db_table_exists')) {
        $hasInvoiceItems = crm_db_table_exists($connect, 'invoice_items');
    } else {
        $__chk = @$connect->query("SHOW TABLES LIKE 'invoice_items'");
        $hasInvoiceItems = ($__chk && $__chk->num_rows > 0);
    }
    if (!$hasInvoiceItems) {
        @$connect->query("CREATE TABLE IF NOT EXISTS invoice_items (
            id int(11) NOT NULL AUTO_INCREMENT,
            milestone_id int(255) NOT NULL,
            description varchar(255) COLLATE utf8_unicode_ci NOT NULL,
            item_description TEXT NULL,
            rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
            sort_order int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY milestone_id (milestone_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");
        if (isset($GLOBALS['__crm_db_table_cache']) && is_array($GLOBALS['__crm_db_table_cache'])) {
            unset($GLOBALS['__crm_db_table_cache']['invoice_items']);
        }
    }
}

function tasksession_is_pro_feature($key)
{
    $key = (string) $key;
    $registry = tasksession_pro_features_registry();
    return isset($registry[$key]);
}

/**
 * @return array{name:string,description:string,upgrade_url:string}|null
 */
function tasksession_pro_feature($key)
{
    $registry = tasksession_pro_features_registry();
    $key = (string) $key;
    if (!isset($registry[$key])) {
        return null;
    }
    return $registry[$key];
}

/**
 * accountStatus: 1=admin, 2=client, 3=staff
 */
function tasksession_free_account_status()
{
    if (isset($_SESSION['accountStatus'])) {
        return (int) $_SESSION['accountStatus'];
    }
    return 0;
}

function tasksession_free_is_admin_or_staff()
{
    $s = tasksession_free_account_status();
    return $s === 1 || $s === 3;
}

function tasksession_free_is_client()
{
    return tasksession_free_account_status() === 2;
}

function tasksession_free_role_dashboard_url()
{
    global $url;
    $base = isset($url) ? rtrim((string) $url, '/') . '/' : '/';
    $status = tasksession_free_account_status();
    if ($status === 1) {
        return function_exists('tasksession_app_href')
            ? tasksession_app_href('admin/dashboard')
            : $base . 'admin/';
    }
    if ($status === 3) {
        return function_exists('tasksession_app_href')
            ? tasksession_app_href('staff/dashboard')
            : $base . 'staff/';
    }
    if ($status === 2) {
        return function_exists('tasksession_app_href')
            ? tasksession_app_href('client/dashboard')
            : $base . 'client/';
    }
    return function_exists('tasksession_app_href')
        ? tasksession_app_href('')
        : $base;
}

/**
 * Clients → dashboard. Admin/Staff → continue (caller renders upgrade).
 * Unauthenticated → login.
 *
 * @return bool true if caller should render upgrade
 */
function tasksession_guard_pro_page($featureKey)
{
    global $session, $url;

    if (!isset($session) || !$session->isLoggedIn()) {
        $login = isset($url) ? rtrim((string) $url, '/') . '/' : '/';
        if (function_exists('redirectTo')) {
            redirectTo($login);
        }
        header('Location: ' . $login);
        exit;
    }

    if (tasksession_free_is_client()) {
        if (function_exists('redirectTo')) {
            redirectTo(tasksession_free_role_dashboard_url());
        }
        header('Location: ' . tasksession_free_role_dashboard_url());
        exit;
    }

    if (!tasksession_free_is_admin_or_staff()) {
        if (function_exists('redirectTo')) {
            redirectTo(tasksession_free_role_dashboard_url());
        }
        header('Location: ' . tasksession_free_role_dashboard_url());
        exit;
    }

    return true;
}

/**
 * Render central upgrade card HTML (main content only).
 */
function tasksession_render_pro_upgrade($featureKey, $overrides = array())
{
    $feature = tasksession_pro_feature($featureKey);
    if (!$feature) {
        $feature = array(
            'name' => 'Pro feature',
            'description' => 'Upgrade to Pro to unlock this feature and access more powerful tools for your workspace.',
            'upgrade_url' => TASKSESSION_PRO_UPGRADE_URL,
        );
    }
    if (!empty($overrides['name'])) {
        $feature['name'] = (string) $overrides['name'];
    }
    if (!empty($overrides['description'])) {
        $feature['description'] = (string) $overrides['description'];
    }
    if (!empty($overrides['upgrade_url'])) {
        $feature['upgrade_url'] = (string) $overrides['upgrade_url'];
    }

    $heading = 'This feature is available in TaskSession Pro';
    $body = 'Upgrade to Pro to unlock this feature and access more powerful tools for your workspace.';
    $buttonText = 'Upgrade to Pro';
    $featureName = $feature['name'];
    $featureDescription = $feature['description'];
    $upgradeUrl = $feature['upgrade_url'];

    include SITE_ROOT . DS . 'templates' . DS . 'free' . DS . 'pro-upgrade.php';
}

/**
 * Full page: header + sidebar + upgrade + footer. Exits.
 */
function tasksession_serve_pro_upgrade_page($featureKey, $pageTitle = null)
{
    global $session, $url, $syatem_title, $lang, $time_zone;

    tasksession_guard_pro_page($featureKey);

    $feature = tasksession_pro_feature($featureKey);
    $name = $feature ? $feature['name'] : 'Pro';
    $title = ($pageTitle !== null && $pageTitle !== '')
        ? $pageTitle
        : ($name . ' | ' . (isset($syatem_title) ? $syatem_title : 'Task Session'));

    $id = isset($session->userId) ? (int) $session->userId : 0;
    $user = ($id > 0 && class_exists('User')) ? User::findById($id) : null;
    $username = $user ? $user->firstName : '';
    $accountStatus = isset($_SESSION['accountStatus']) ? (int) $_SESSION['accountStatus'] : 0;
    $email = $user ? $user->email : '';

    if (!empty($time_zone)) {
        @date_default_timezone_set($time_zone);
    }

    include SITE_ROOT . DS . 'templates' . DS . 'header.php';
    ?>
<div class="page-container">
    <div class="container-fluid">
        <div class="row row-eq-height">
            <?php include SITE_ROOT . DS . 'templates' . DS . 'sidebar.php'; ?>
            <div class="page-content">
                <?php include SITE_ROOT . DS . 'templates' . DS . 'top-header.php'; ?>
                <div class="row">
                    <div class="col-md-12">
                        <?php tasksession_render_pro_upgrade($featureKey); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    <?php
    include SITE_ROOT . DS . 'templates' . DS . 'main-footer.php';
    exit;
}

/**
 * Project shell (tabs + project sidebar) with Pro upgrade in the main column. Exits.
 * Same layout as overview / Free discussion notice.
 */
function tasksession_serve_project_pro_upgrade_page($featureKey, $pageTitle = null)
{
    global $session, $url, $syatem_title, $lang, $time_zone;

    tasksession_guard_pro_page($featureKey);

    $feature = tasksession_pro_feature($featureKey);
    $name = $feature ? $feature['name'] : 'Pro';
    $title = ($pageTitle !== null && $pageTitle !== '')
        ? $pageTitle
        : ($name . ' | ' . (isset($syatem_title) ? $syatem_title : 'Task Session'));

    $id = isset($session->userId) ? (int) $session->userId : 0;
    $user = ($id > 0 && class_exists('User')) ? User::findById($id) : null;
    $username = $user ? $user->firstName : '';
    $accountStatus = isset($_SESSION['accountStatus']) ? (int) $_SESSION['accountStatus'] : 0;
    $email = $user ? $user->email : '';

    if (!empty($time_zone)) {
        @date_default_timezone_set($time_zone);
    }

    $projectId = 0;
    if (isset($_GET['projectId'])) {
        $projectId = (int) $_GET['projectId'];
    } elseif (isset($_GET['project_id'])) {
        $projectId = (int) $_GET['project_id'];
    } elseif (isset($_POST['project_id'])) {
        $projectId = (int) $_POST['project_id'];
    } elseif (isset($_POST['projectId'])) {
        $projectId = (int) $_POST['projectId'];
    }

    $roleFolder = 'admin';
    if ($accountStatus === 3) {
        $roleFolder = 'staff';
    } elseif ($accountStatus === 2) {
        $roleFolder = 'client';
    }

    if ($projectId <= 0) {
        if (function_exists('redirectTo')) {
            redirectTo(rtrim((string) $url, '/') . '/' . $roleFolder . '/projects');
        }
        header('Location: ' . rtrim((string) $url, '/') . '/' . $roleFolder . '/projects');
        exit;
    }

    include SITE_ROOT . DS . 'includes' . DS . 'project-sidebar-data.php';

    $project_id = $projectId;
    $paProject = isset($project) ? $project : null;

    include SITE_ROOT . DS . 'templates' . DS . 'header.php';
    ?>
<style>
.ts-pro-upgrade-wrap.ts-pro-upgrade-wrap--embedded {
    min-height: calc(100vh - 220px) !important;
    padding: 48px 20px !important;
}
</style>
<div class="page-container vh-100">
    <div class="container-fluid vh-100">
        <div class="row row-eq-height vh-100">
            <?php include SITE_ROOT . DS . 'templates' . DS . 'sidebar.php'; ?>
            <div class="page-content">
                <?php include SITE_ROOT . DS . 'templates' . DS . 'top-header.php'; ?>
                <div class="row bg-grey">
                    <div class="col-md-12 margin-top-10 clients project-tabs">
                        <div class="row">
                            <?php
                            if (isset($project_id) && $project_id > 0) {
                                include SITE_ROOT . DS . 'templates' . DS . 'project-tabs.php';
                            }
                            include SITE_ROOT . DS . 'templates' . DS . 'project-action.php';
                            ?>
                        </div>
                    </div>
                </div>
                <div class="clearfix"></div>
                <div class="row vh-100">
                    <div class="container-fluid vh-100">
                        <div class="row vh-100">
                            <?php include SITE_ROOT . DS . 'templates' . DS . 'project-sidebar.php'; ?>
                            <div class="col-lg-8 center-col">
                                <?php
                                ob_start();
                                tasksession_render_pro_upgrade($featureKey);
                                $upgradeHtml = ob_get_clean();
                                echo str_replace(
                                    'class="ts-pro-upgrade-wrap"',
                                    'class="ts-pro-upgrade-wrap ts-pro-upgrade-wrap--embedded"',
                                    $upgradeHtml
                                );
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    <?php
    include SITE_ROOT . DS . 'templates' . DS . 'main-footer.php';
    exit;
}

/**
 * Hard-deny for AJAX/API (never HTML upgrade). Exits.
 */
function tasksession_deny_pro_api($featureKey = '', $httpCode = 403)
{
    if (tasksession_free_is_client() || !tasksession_free_is_admin_or_staff()) {
        // Clients and others get generic denial
    }
    $httpCode = (int) $httpCode;
    if ($httpCode < 400) {
        $httpCode = 403;
    }
    if (!headers_sent()) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(array(
        'success' => false,
        'error' => 'This feature is not available in TaskSession Free.',
        'feature' => (string) $featureKey,
        'code' => 'pro_required',
    ));
    exit;
}

/**
 * Small PRO badge HTML for Admin/Staff menus.
 */
function tasksession_pro_badge_html()
{
    return '<span class="ts-pro-badge" title="Available in Pro">PRO</span>';
}

/**
 * Whether client portal should hide this Pro surface entirely.
 */
function tasksession_client_hides_pro($featureKey)
{
    return tasksession_is_free_edition() && tasksession_is_pro_feature($featureKey);
}

/**
 * Render Pro upgrade card sized for embedding inside an existing page shell.
 */
function tasksession_render_pro_upgrade_embedded($featureKey, $overrides = array())
{
    static $stylePrinted = false;
    if (!$stylePrinted) {
        $stylePrinted = true;
        echo '<style>.ts-pro-upgrade-wrap.ts-pro-upgrade-wrap--embedded{min-height:calc(100vh - 220px)!important;padding:48px 20px!important;}</style>';
    }
    ob_start();
    tasksession_render_pro_upgrade($featureKey, $overrides);
    $html = ob_get_clean();
    echo str_replace(
        'class="ts-pro-upgrade-wrap"',
        'class="ts-pro-upgrade-wrap ts-pro-upgrade-wrap--embedded"',
        $html
    );
}

function tasksession_render_pro_widget_overlay($featureKey = '')
{
    $feature = tasksession_pro_feature($featureKey);
    $title = $feature ? (string) $feature['name'] : 'Pro feature';
    $message = $feature
        ? (string) $feature['description']
        : 'Upgrade to Pro to unlock this feature and access more powerful tools for your workspace.';
    $upgradeUrl = $feature
        ? (string) $feature['upgrade_url']
        : TASKSESSION_PRO_UPGRADE_URL;
    $buttonText = 'Upgrade to Pro';
    ?>
    <div class="sales-stats-overlay">
        <div class="overlay-card">
            <h3><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h3>
            <p><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            <a href="<?php echo htmlspecialchars($upgradeUrl, ENT_QUOTES, 'UTF-8'); ?>" class="primary-btn" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($buttonText, ENT_QUOTES, 'UTF-8'); ?></a>
        </div>
    </div>
    <?php
}
