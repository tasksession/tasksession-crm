<?php
// Get current page name for active state detection
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$on_import_export_page = !empty($_SERVER['REQUEST_URI']) && (
    strpos((string) $_SERVER['REQUEST_URI'], 'import-export/migration') !== false
    || strpos((string) $_SERVER['REQUEST_URI'], 'import-export/history') !== false
    || preg_match('#/import-export/(users|companies|projects|tasks|invoices|notes|chats|leads)(/|$)#', (string) $_SERVER['REQUEST_URI'])
);

// Settings for module toggles (e.g. Email module)
if (!isset($settingsForModules) || !$settingsForModules) {
    $settingsForModules = (isset($settings) && $settings) ? $settings : settings::findById(1);
}

$ssFreePro = function_exists('tasksession_is_free_edition') && tasksession_is_free_edition();
$ssProBadge = $ssFreePro ? 'PRO' : '';

// Define navigation items
$nav_items = [
    'system-settings' => [
        'title' => $lang['General Settings'],
        'url' => 'system-settings'
    ],
    'logo-settings' => [
        'title' => $lang['Logo Settings'],
        'url' => 'logo-settings'
    ],
    'chat-settings' => [
        'title' => $lang['Chat Settings'],
        'url' => 'chat-settings',
        'badge' => $ssProBadge,
    ],
    'theme-style' => [
        'title' => $lang['Theme Style'],
        'url' => 'theme-style'
    ],
    'menu-settings' => [
        'title' => $lang['Menu settings'] ?? 'Menu settings',
        'url' => 'menu-settings'
    ],
    'invoice-settings' => [
        'title' => $lang['Invoice Configuration'],
        'url' => 'invoice-settings',
        'badge' => $ssProBadge,
    ],
    'payment-setting' => [
        'title' => $lang['Payment Settings'],
        'url' => 'payment-setting',
        'badge' => $ssProBadge,
    ],
    'smtp-setup' => [
        'title' => $lang['SMTP Setup'],
        'url' => 'smtp-setup'
    ],
    'email-setting' => [
        'title' => $lang['Email Notifications'],
        'url' => 'email-setting'
    ],
    'email-setup' => [
        'title' => $lang['Email Integration'],
        'url' => 'email-setup',
        'badge' => $ssProBadge,
    ],
    'addons' => [
        'title' => $lang['Addons'] ?? 'Addons',
        'url' => 'addons',
        'badge' => $ssFreePro ? 'PRO' : ($lang['New'] ?? 'New'),
    ],
    'ai-settings' => [
        'title' => $lang['AI Settings'] ?? 'AI Settings',
        'url' => '../ai/setting',
    ],
    'roles' => [
        'title' => $lang['Roles Permissions'],
        'url' => 'roles'
    ],
    'tags' => [
        'title' => $lang['Tags Management'],
        'url' => 'tags'
    ],
    'media-management' => [
        'title' => $lang['Media Management'],
        'url' => 'media-management',
        'badge' => $ssProBadge,
    ],
    'attempts-ip' => [
        'title' => $lang['Login Attempts & IP Setup'] ?? 'Login attempts & IP setup',
        'url' => 'attempts-ip',
        'badge' => $ssProBadge,
    ],
    'google-drive-integration' => [
        'title' => $lang['Google Drive Integration'] ?? 'Google Drive integration',
        'url' => 'google-drive-integration',
        'badge' => $ssProBadge,
    ],
    'google-calendar-integration' => [
        'title' => $lang['Google Calendar Integration'] ?? 'Google calendar integration',
        'url' => 'google-calendar-integration',
        'badge' => $ssProBadge,
    ],
    'google-login' => [
        'title' => $lang['Google Login'] ?? 'Google login',
        'url' => 'google-login',
        'badge' => $ssProBadge,
    ],
    'google-authenticator' => [
        'title' => $lang['Google Authenticator'] ?? 'Google Authenticator',
        'url' => 'google-authenticator',
        'badge' => $ssProBadge,
    ],
    'cron' => [
        'title' => $lang['Cron Management'],
        'url' => 'cron'
    ],
    'custom-fields' => [
        'title' => $lang['Custom Fields'] ?? 'Custom fields',
        'url' => 'custom-fields',
        'badge' => $ssProBadge,
    ],
    'import-export' => [
        'title' => $lang['Import / Export'] ?? 'Import / Export',
        'url' => '../import-export/migration/',
    ],
    'server-load' => [
        'title' => $lang['Server Management'] ?? 'Server management',
        'url' => 'server-load',
        'badge' => $ssProBadge,
    ],
];
?>

<div class="col-md-3 ss-left">
    <h2 class="page-title"><?php echo $lang['System Settings']; ?></h2>
    <div class="ss-sidenav">
        <ul>
            <?php foreach ($nav_items as $page => $item): ?>
                <?php
                // Free: keep Pro settings links visible (badge + upgrade page)
                if ($page === 'email-setup' && empty($settingsForModules->module_email) && !$ssFreePro) continue;
                if ($page === 'ai-settings') {
                    if (!function_exists('comon_ai_module_enabled')) {
                        $aiGate = __DIR__ . '/../includes/ai_module_gate.php';
                        if (is_file($aiGate)) {
                            require_once $aiGate;
                        }
                    }
                    if (!function_exists('comon_ai_module_enabled') || !comon_ai_module_enabled()) {
                        continue;
                    }
                }
                if ($page === 'invoice-settings' && empty($settingsForModules->module_invoices) && !$ssFreePro) continue;
                if ($page === 'payment-setting' && empty($settingsForModules->module_invoices) && !$ssFreePro) continue;
                $nav_active = ($current_page === $page);
                if ($page === 'import-export') {
                    $nav_active = $on_import_export_page;
                }
                ?>
                <li<?php echo $nav_active ? ' class="active"' : ''; ?>>
                    <a href="<?php echo $item['url']; ?>">
                        <span class="ss-nav-label"><?php echo $item['title']; ?></span>
                        <?php if (!empty($item['badge'])) : ?>
                            <span class="ts-pro-badge ss-nav-badge"><?php echo htmlspecialchars((string) $item['badge'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<style>
.ss-sidenav ul li a {
    display: flex !important;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.ss-sidenav .ss-nav-label {
    flex: 1 1 auto;
    min-width: 0;
}
.ss-sidenav .ss-nav-badge,
.ss-sidenav .ts-pro-badge {
    display: inline-block !important;
    margin-left: auto !important;
    margin-right: 0 !important;
    padding: 2px 5px !important;
    font-size: 9px !important;
    font-weight: 600 !important;
    line-height: 1.35 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.04em;
    border-radius: 999px !important;
    background: var(--primary-color, #0094ff) !important;
    color: #fff !important;
    vertical-align: middle;
    text-align: center !important;
    flex-shrink: 0;
}
.ss-sidenav ul li.active a .ss-nav-badge,
.ss-sidenav ul li.active a .ts-pro-badge {
    opacity: 0.95;
}
</style>
