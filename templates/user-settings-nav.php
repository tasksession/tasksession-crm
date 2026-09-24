<?php
/**
 * User account settings side navigation (Profile, Notifications, etc.)
 */
if (!isset($lang) || !is_array($lang)) {
    $lang = array();
}

$current_page = basename($_SERVER['PHP_SELF'], '.php');
$accountStatus = isset($_SESSION['accountStatus']) ? (int) $_SESSION['accountStatus'] : 0;

$profilePath = isset($userSettingsProfilePath) ? $userSettingsProfilePath : '';
if ($profilePath === '' && isset($session) && is_object($session)) {
    $uid = (int) $session->userId;
    if ($accountStatus === 1) {
        $profilePath = 'admin/profile?user_id=' . $uid;
    } elseif ($accountStatus === 2) {
        $profilePath = 'client/edit?editprofile=' . $uid;
    } elseif ($accountStatus === 3) {
        $profilePath = 'staff/edit?editprofile=' . $uid;
    }
}

$nav_items = array(
    'profile' => array(
        'title' => $lang['Edit Profile'] ?? 'Edit profile',
        'url' => $profilePath,
        'external' => true,
    ),
    'settings' => array(
        'title' => $lang['Notifications'] ?? 'Notifications',
        'url' => 'settings',
        'external' => true,
    ),
);

$totpFeatureOn = function_exists('auth_totp_feature_enabled') && auth_totp_feature_enabled();
$userTotpOn = false;
if (isset($user) && is_object($user) && !empty($user->totp_enabled)) {
    $userTotpOn = true;
} elseif (isset($session) && is_object($session) && !empty($session->userId) && class_exists('User')) {
    $navTotpUser = User::findById((int) $session->userId);
    $userTotpOn = $navTotpUser && !empty($navTotpUser->totp_enabled);
}
if ($totpFeatureOn || $userTotpOn) {
    $nav_items['authenticator'] = array(
        'title' => $lang['Authenticator'] ?? 'Authenticator',
        'url' => 'authenticator',
        'external' => true,
    );
}

$showAiCustomInstructions = false;
if (!function_exists('comon_ai_module_enabled')) {
    $aiGate = dirname(__DIR__) . '/includes/ai_module_gate.php';
    if (is_file($aiGate)) {
        require_once $aiGate;
    }
}
if (function_exists('comon_ai_module_enabled') && comon_ai_module_enabled()) {
    if (!function_exists('has_permission')) {
        $permFile = dirname(__DIR__) . '/includes/permissions.php';
        if (is_file($permFile)) {
            require_once $permFile;
        }
    }
    if (function_exists('has_permission')) {
        if (isset($connect) && function_exists('ensure_user_permissions')) {
            ensure_user_permissions($connect);
        }
        $showAiCustomInstructions = has_permission('ai_access');
    } else {
        $showAiCustomInstructions = true;
    }
}
if ($showAiCustomInstructions) {
    $nav_items['preferences'] = array(
        'title' => $lang['Assistant Preferences'] ?? 'Assistant preferences',
        'url' => 'ai/preferences',
        'external' => true,
    );
    // AI Settings page is admin-only (ai_admin_page_guard).
    if ($accountStatus === 1) {
        $nav_items['ai-setting'] = array(
            'title' => $lang['AI Settings'] ?? 'AI settings',
            'url' => 'ai/setting',
            'external' => true,
        );
    }
}

$settingsForNav = (isset($settings) && is_object($settings)) ? $settings : settings::findById(1);
if (!empty($settingsForNav->module_email)) {
    if ($accountStatus === 3) {
        $nav_items['email-setup'] = array(
            'title' => $lang['Email Accounts'] ?? 'Email accounts',
            'url' => 'staff/email-setup',
            'external' => true,
        );
    } elseif ($accountStatus === 1) {
        $nav_items['email-setup'] = array(
            'title' => $lang['Email Accounts'] ?? 'Email accounts',
            'url' => 'admin/email-setup',
            'external' => true,
        );
    }
}

if ($accountStatus === 1) {
    $nav_items['system-settings'] = array(
        'title' => $lang['Control panel'] ?? 'Control panel',
        'url' => 'admin/system-settings',
        'external' => true,
    );
}

if ($accountStatus === 2 && !empty($settingsForNav->module_invoices)
    && !(function_exists('tasksession_is_free_edition') && tasksession_is_free_edition())) {
    $clientCanViewPayments = false;
    if (isset($taskPermissions) && is_object($taskPermissions) && !empty($taskPermissions->can_view_milestones)) {
        $clientCanViewPayments = true;
    } elseif (isset($session) && is_object($session) && (int) $session->userId > 0) {
        if (!class_exists('TaskPermission')) {
            require_once dirname(__DIR__) . '/includes/task_permission.php';
        }
        $tpNav = TaskPermission::getOrCreate((int) $session->userId);
        $clientCanViewPayments = $tpNav && !empty($tpNav->can_view_milestones);
    }
    if ($clientCanViewPayments) {
        $nav_items['payment-methods'] = array(
            'title' => $lang['Payment Methods'] ?? 'Payment methods',
            'url' => 'client/payment-methods',
            'external' => true,
        );
    }
}
?>

<div class="col-md-3 ss-left">
    <h2 class="page-title"><?php echo $lang['Settings'] ?? 'Settings'; ?></h2>
    <div class="ss-sidenav">
        <ul>
            <?php foreach ($nav_items as $page => $item): ?>
                <?php
                $nav_active = false;
                if ($page === 'settings' && $current_page === 'settings') {
                    $nav_active = true;
                }
                if ($page === 'authenticator' && $current_page === 'authenticator') {
                    $nav_active = true;
                }
                if ($page === 'preferences' && $current_page === 'preferences') {
                    $nav_active = true;
                }
                if ($page === 'ai-setting' && $current_page === 'setting') {
                    $nav_active = true;
                }
                if ($page === 'profile' && ($current_page === 'profile' || $current_page === 'edit')) {
                    $nav_active = true;
                }
                if ($page === 'email-setup' && $current_page === 'email-setup') {
                    $nav_active = true;
                }
                if ($page === 'system-settings' && $current_page === 'system-settings') {
                    $nav_active = true;
                }
                if ($page === 'payment-methods' && $current_page === 'payment-methods') {
                    $nav_active = true;
                }
                $href = $item['url'];
                if (!empty($item['external']) && isset($url) && strpos($href, 'http') !== 0) {
                    $href = rtrim((string) $url, '/') . '/' . ltrim($href, '/');
                }
                ?>
                <li<?php echo $nav_active ? ' class="active"' : ''; ?>>
                    <a href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
