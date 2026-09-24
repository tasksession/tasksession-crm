<?php
// Define the core paths
// Define them as absolute paths to make sure that require_once works as expected

// DIRECTORY_SEPARATOR is a PHP pre-defined constant
// (\ for Windows, / for Unix)
defined('DS') ? null : define('DS', DIRECTORY_SEPARATOR);

// Fix the SITE_ROOT path to point to the actual root directory
defined('SITE_ROOT') ? null : define('SITE_ROOT', dirname(dirname(__FILE__)));

defined('LIB_ROOT') ? null : define('LIB_ROOT', SITE_ROOT . DS . 'includes');	
defined('INC_ROOT') ? null : define('INC_ROOT', SITE_ROOT . DS . 'includes');

// load config file first (Hostinger-safe wrap; does not overwrite generated config.php)
require_once(LIB_ROOT.DS.'bootstrap_config.php');

// load basic functions next so that everything after can use them
require_once(LIB_ROOT.DS.'functions.php');
require_once(LIB_ROOT.DS.'pretty_url.php');
require_once(LIB_ROOT.DS.'free_edition.php');
require_once(LIB_ROOT.DS.'user_presence.php');
require_once(LIB_ROOT.DS.'icon.php');
require_once(LIB_ROOT.DS.'page_assets.php');
require_once(LIB_ROOT.DS.'list-pagination.php');
require_once(LIB_ROOT.DS.'project_edit_return.php');

// load core objects
require_once(LIB_ROOT.DS.'session.php');
require_once(LIB_ROOT.DS.'database.php');
require_once(LIB_ROOT.DS.'database-object.php');

// load system helper functions
require_once(LIB_ROOT.DS.'system_helpers.php');

// load database-related classes
require_once(LIB_ROOT.DS.'user.php');
require_once(LIB_ROOT.DS.'auth_security.php');
require_once(LIB_ROOT.DS.'profilePicture.php');
require_once(LIB_ROOT.DS.'projects.php');
require_once(LIB_ROOT.DS.'settings.php');
require_once(LIB_ROOT.DS.'message.php');
require_once(LIB_ROOT.DS.'milestone.php');
require_once(LIB_ROOT.DS.'userWarnings.php');
require_once(LIB_ROOT.DS.'database.class.php');
require_once(LIB_ROOT.DS.'task.php');
require_once(LIB_ROOT.DS.'password_reset_tokens.php');

// Free edition: no license activation / remote verify.
$license_valid = true;
if (!crm_is_lightweight_request()) {
    // Load email helper system
    require_once(LIB_ROOT.DS.'email_helper.php');
}

if (!defined('SERVER_LOAD_REQUEST_START')) {
    define('SERVER_LOAD_REQUEST_START', microtime(true));
}

// config.php already opened $connect — a second mysqli_connect here exhausts host limits on AJAX bursts.
if (!isset($connect) || !($connect instanceof mysqli) || (int) $connect->connect_errno !== 0) {
    require_once(LIB_ROOT . DS . 'mysqli_connect_safe.php');
    $connect = function_exists('crm_mysqli_open')
        ? crm_mysqli_open()
        : mysqli_connect(DB_SERVER, DB_USER, DB_PASS, DB_NAME);
}
if (isset($connect) && $connect instanceof mysqli) {
    $connect->set_charset('utf8mb4');
    // Addon module flags (e.g. module_ai) must exist before Settings SELECT, or every page fatals.
    if (is_readable(LIB_ROOT . DS . 'addon_registry.php')) {
        require_once LIB_ROOT . DS . 'addon_registry.php';
        if (function_exists('comon_ensure_addon_module_columns')) {
            comon_ensure_addon_module_columns($connect);
        }
    }
    if (function_exists('auth_security_ensure_schema')) {
        auth_security_ensure_schema($connect);
    }
    if (function_exists('tasksession_free_ensure_modules_enabled')) {
        tasksession_free_ensure_modules_enabled();
    }
    if (function_exists('tasksession_free_ensure_core_tables')) {
        tasksession_free_ensure_core_tables();
    }
}
$dash_settings = settings::findById(1);
if (!is_object($dash_settings)) {
    error_log('lib-initialize: settings id=1 not found');
    $dash_settings = (object) array(
        'url' => '/',
        'company_name' => '',
        'syatem_title' => 'My Application',
        'login_page_title' => '',
        'copy_rights' => '',
        'time_zone' => 'UTC',
        'system_email' => '',
        'system_language' => 'en',
        'stripe_sk' => '',
        'stripe_pk' => '',
        'paypal_email' => '',
        'checkout_id' => '',
        'checkout_pk' => '',
    );
}
require_once(LIB_ROOT . DS . 'time_tracking_helper.php');
$img_path = 'uploads/system-uploads/';
$url = $dash_settings->url;
if (function_exists('crm_align_public_url_to_request')) {
    $url = crm_align_public_url_to_request((string) $url);
}
$company_name = $dash_settings->company_name;
$syatem_title = $dash_settings->syatem_title;
$system_title = (isset($syatem_title) && trim((string) $syatem_title) !== '') ? trim((string) $syatem_title) : 'My Application';
$login_page_title = $dash_settings->login_page_title;
$copy_rights = $dash_settings->copy_rights;
$time_zone = $dash_settings->time_zone;
$system_email = $dash_settings->system_email;
$system_language = $dash_settings->system_language;

if (!isset($session)) {
    $session = new Session();
}

if (function_exists('auth_mfa_pending_enforce')) {
    auth_mfa_pending_enforce();
}

if (function_exists('crm_should_release_session_lock') && crm_should_release_session_lock()
    && function_exists('session_write_close') && session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$id = isset($session->userId) ? $session->userId : null;
$userb = $id ? User::findById((int)$id) : null;
$user_lang = ($userb && isset($userb->user_language)) ? $userb->user_language : '';

if (!empty($user_lang) && file_exists(LIB_ROOT.DS.'languages/'.$user_lang.'.php')) {
    require_once(LIB_ROOT.DS.'languages/'.$user_lang.'.php');
    global $lang;
} elseif (!empty($system_language) && file_exists(LIB_ROOT.DS.'languages/'.$system_language.'.php')) {
    require_once(LIB_ROOT.DS.'languages/'.$system_language.'.php');
    global $lang;
} else {
    require_once(LIB_ROOT.DS.'languages/en.php');
    global $lang;
}

if (!isset($lang) || !is_array($lang)) {
    $lang = array();
}

if ($session->isLoggedIn() && $userb && User::isTrashed($userb)) {
    require_once LIB_ROOT . DS . 'auth_helper.php';
    $authHelper = new AuthHelper($session);
    $authHelper->logout();
    redirectTo($url . 'index.php?err=' . urlencode(User::trashedLoginMessage()));
}

$applyMenuLabelOverride = function ($overrideValue, $baseWords, $keys) use (&$lang) {
    $value = trim((string)$overrideValue);
    if ($value === '') {
        return;
    }
    $escapedBaseWords = array();
    foreach ($baseWords as $baseWord) {
        $word = trim((string)$baseWord);
        if ($word !== '') {
            $escapedBaseWords[] = preg_quote($word, '/');
        }
    }
    if (empty($escapedBaseWords)) {
        return;
    }
    usort($escapedBaseWords, static function ($a, $b) {
        return strlen($b) <=> strlen($a);
    });
    $pattern = '/\b(?:' . implode('|', $escapedBaseWords) . ')\b/i';

    foreach ($keys as $key) {
        $current = isset($lang[$key]) ? (string)$lang[$key] : (string)$key;
        if (trim($current) === $value) {
            $lang[$key] = $value;
            continue;
        }
        $updated = preg_replace($pattern, $value, $current);

        if ($updated === $current || trim($updated) === '') {
            $updated = $value;
        }

        $lang[$key] = $updated;
    }
};

$applyGlobalLangReplacement = function ($overrideValue, $baseWords) use (&$lang) {
    $value = trim((string)$overrideValue);
    if ($value === '') {
        return;
    }
    $escapedBaseWords = array();
    foreach ($baseWords as $baseWord) {
        $word = trim((string)$baseWord);
        if ($word !== '') {
            $escapedBaseWords[] = preg_quote($word, '/');
        }
    }
    if (empty($escapedBaseWords)) {
        return;
    }
    usort($escapedBaseWords, static function ($a, $b) {
        return strlen($b) <=> strlen($a);
    });
    $pattern = '/\b(?:' . implode('|', $escapedBaseWords) . ')\b/i';

    foreach ($lang as $langKey => $langValue) {
        if (!is_string($langValue)) {
            continue;
        }
        if (trim($langValue) === $value) {
            continue;
        }
        $lang[$langKey] = preg_replace($pattern, $value, $langValue);
    }
};

$projectOverride = $dash_settings->label_projects_override ?? '';
$taskOverride = $dash_settings->label_tasks_override ?? '';
$clientOverride = $dash_settings->label_clients_override ?? '';
$financialsOverride = $dash_settings->label_financials_override ?? '';
$chattingOverride = $dash_settings->label_chatting_override ?? '';
$privateNotesOverride = $dash_settings->label_private_notes_override ?? '';
$leadsOverride = $dash_settings->label_leads_override ?? '';
$mediaVaultOverride = $dash_settings->label_media_vault_override ?? '';
$customFieldsOverride = $dash_settings->label_custom_fields_override ?? '';
$eventOverride = $dash_settings->label_event_override ?? '';

// Global centralized replacements for all language-driven UI labels.
$applyGlobalLangReplacement($projectOverride, array('Projects', 'Project'));
$applyGlobalLangReplacement($taskOverride, array('Tasks', 'Task'));
$applyGlobalLangReplacement($clientOverride, array('Clients', 'Client'));
$applyGlobalLangReplacement($financialsOverride, array('Financials', 'Invoices', 'Invoice'));
$applyGlobalLangReplacement($chattingOverride, array('Chatting', 'Chats', 'Chat'));
$applyGlobalLangReplacement($privateNotesOverride, array('Documents', 'Private Notes', 'Project Notes', 'Notes'));
$applyGlobalLangReplacement($leadsOverride, array('Leads', 'Lead'));
$applyGlobalLangReplacement($mediaVaultOverride, array('Media Vault'));
$applyGlobalLangReplacement($customFieldsOverride, array('Custom Fields', 'Custom Field'));

// Keep explicit menu keys guaranteed for any edge-case fallback usages.
$applyMenuLabelOverride($projectOverride, array('Projects', 'Project'), array('Projects', 'Project'));
$applyMenuLabelOverride($taskOverride, array('Tasks', 'Task'), array('Tasks', 'Task'));
$applyMenuLabelOverride($clientOverride, array('Clients', 'Client'), array('Clients', 'Client'));
$applyMenuLabelOverride($financialsOverride, array('Financials', 'Invoices', 'Invoice'), array('Financials', 'Invoices', 'Invoice'));
$applyMenuLabelOverride($chattingOverride, array('Chatting', 'Chats', 'Chat'), array('Chatting', 'Chats', 'Chat'));
$applyMenuLabelOverride($privateNotesOverride, array('Documents', 'Private Notes', 'Project Notes', 'Notes'), array('Documents', 'Private Notes', 'Project Notes', 'Notes'));
$applyMenuLabelOverride($leadsOverride, array('Leads', 'Lead'), array('Leads', 'Lead'));
$applyMenuLabelOverride($mediaVaultOverride, array('Media Vault'), array('Media Vault'));
$applyMenuLabelOverride($customFieldsOverride, array('Custom Fields', 'Custom Field'), array('Custom Fields', 'Custom Field'));
$applyMenuLabelOverride($eventOverride, array('Add New Event'), array('Add New Event'));

if (!function_exists('menu_slugify')) {
    function menu_slugify($text, $fallback = 'item') {
        $text = strtolower(trim((string)$text));
        if ($text === '') {
            return $fallback;
        }
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
        $text = trim((string)$text, '-');
        return $text !== '' ? $text : $fallback;
    }
}

if (!function_exists('get_menu_routes_for_role')) {
    function get_menu_routes_for_role($role) {
        global $dash_settings, $lang;

        $definitions = array(
            'projects' => array('field' => 'label_projects_override', 'label_key' => 'Projects', 'default_slug' => 'projects', 'targets' => array('admin' => 'projects.php', 'staff' => 'projects.php', 'client' => 'projects.php')),
            'tasks' => array('field' => 'label_tasks_override', 'label_key' => 'Tasks', 'default_slug' => 'tasks', 'targets' => array('admin' => 'all-tasks.php', 'staff' => 'all-tasks.php', 'client' => 'kanban.php')),
            'clients' => array('field' => 'label_clients_override', 'label_key' => 'Clients', 'default_slug' => 'clients', 'targets' => array('admin' => 'clients.php', 'staff' => 'clients.php')),
            'financials' => array('field' => 'label_financials_override', 'label_key' => 'Financials', 'default_slug' => 'financials', 'targets' => array('admin' => 'invoices.php', 'staff' => 'invoices.php', 'client' => 'invoices.php')),
            'leads' => array('field' => 'label_leads_override', 'label_key' => 'Leads', 'default_slug' => 'leads', 'targets' => array('admin' => 'leads.php', 'staff' => 'leads.php')),
            'media_vault' => array('field' => 'label_media_vault_override', 'label_key' => 'Media Vault', 'default_slug' => 'media-vault', 'targets' => array('admin' => 'media-vault.php', 'staff' => 'media-vault.php')),
            'private_notes' => array('field' => 'label_private_notes_override', 'label_key' => 'Documents', 'default_slug' => 'documents', 'targets' => array('admin' => 'documents.php', 'staff' => 'documents.php', 'client' => 'documents.php')),
            'custom_fields' => array('field' => 'label_custom_fields_override', 'label_key' => 'Custom Fields', 'default_slug' => 'custom-fields', 'targets' => array('admin' => 'custom-fields.php'))
        );

        $routes = array();
        $usedSlugs = array();
        foreach ($definitions as $module => $def) {
            if (empty($def['targets'][$role])) {
                continue;
            }

            $overrideValue = '';
            if ($dash_settings && isset($def['field']) && isset($dash_settings->{$def['field']})) {
                $overrideValue = trim((string)$dash_settings->{$def['field']});
            }
            $labelValue = $overrideValue !== '' ? $overrideValue : ((isset($lang[$def['label_key']]) && trim((string)$lang[$def['label_key']]) !== '') ? (string)$lang[$def['label_key']] : $def['default_slug']);
            $baseSlug = menu_slugify($labelValue, $def['default_slug']);
            $slug = $baseSlug;
            $suffix = 2;
            while (isset($usedSlugs[$slug])) {
                $slug = $baseSlug . '-' . $suffix;
                $suffix++;
            }
            $usedSlugs[$slug] = true;

            $routes[$module] = array(
                'slug' => $slug,
                'target' => $def['targets'][$role]
            );
        }

        return $routes;
    }
}

if (!function_exists('menu_module_url')) {
    function menu_module_url($role, $module, $query = array()) {
        global $url;
        $routes = get_menu_routes_for_role($role);
        if (empty($routes[$module]['slug'])) {
            $fallback = function_exists('tasksession_role_dashboard_path')
                ? tasksession_role_dashboard_path($role)
                : ($role . '/index.php');
            return rtrim((string)$url, '/') . '/' . $fallback;
        }
        $slugPath = $role . '/' . $routes[$module]['slug'] . '.php';
        if (function_exists('tasksession_pretty_path')) {
            $slugPath = tasksession_pretty_path($slugPath);
        } else {
            $slugPath = $role . '/' . $routes[$module]['slug'] . '.php';
        }
        $path = rtrim((string)$url, '/') . '/' . ltrim($slugPath, '/');
        if (!empty($query) && is_array($query)) {
            $qs = http_build_query($query);
            if ($qs !== '') {
                $path .= '?' . $qs;
            }
        }
        return $path;
    }
}

if (!function_exists('menu_module_slug_file')) {
    function menu_module_slug_file($role, $module) {
        $routes = get_menu_routes_for_role($role);
        if (empty($routes[$module]['slug'])) {
            return null;
        }
        return $routes[$module]['slug'] . '.php';
    }
}

// Dual-key string crypto (legacy decrypt + ENC2 new writes). See tasksession-crypto.php.
require_once __DIR__ . '/tasksession-crypto.php';

// Binary file crypto (voice notes at rest) — ENC1 + IV + ciphertext
require_once __DIR__ . '/file-crypto.php';
if (!crm_is_lightweight_request()) {
    $stripe_sk = decryptString($dash_settings->stripe_sk);
    $stripe_pk = decryptString($dash_settings->stripe_pk);
    $paypal_email = decryptString($dash_settings->paypal_email);
    $checkout_id = decryptString($dash_settings->checkout_id);
    $checkout_pk = decryptString($dash_settings->checkout_pk);
} else {
    $stripe_sk = '';
    $stripe_pk = '';
    $paypal_email = '';
    $checkout_id = '';
    $checkout_pk = '';
}

$favicon_image_check = $dash_settings->favicon_image;
if($favicon_image_check){
	$favicon_image = $favicon_image_check;
}else{
	$favicon_image ='favicon.png';
}
$logo_check = $dash_settings->logo;
if($logo_check){
	$logo = $logo_check;
}else{
	$logo = 'dark-logo.png';
}
$login_page_logo = $dash_settings->login_page_logo;
$mobile_logo = $dash_settings->mobile_logo;
$login_page_logo_url = crm_resolve_system_brand_url($login_page_logo);
$sidebar_favicon_url = crm_resolve_sidebar_favicon_url(
    $favicon_image_check ? $favicon_image : '',
    $login_page_logo
);
$mobile_logo_url = crm_resolve_system_brand_url($mobile_logo);
$login_brand_logo_url = crm_resolve_system_brand_url($logo_check ? $logo : '');
if ($login_brand_logo_url === '') {
    $login_brand_logo_url = crm_default_asset_image_url('assets/images/svg/dark-logo.svg');
}
$login_page_image_setting = trim((string) ($dash_settings->login_page_image ?? ''));
$login_page_image_url = crm_resolve_system_brand_url($login_page_image_setting);
if ($login_page_image_url === '') {
    $login_page_image_url = crm_default_asset_image_url('assets/images/login.jpg');
}
$system_currency = $dash_settings->system_currency;
	$sc_arr = explode(",", (string) $system_currency);
	$currency = isset($sc_arr[0]) ? $sc_arr[0] : '';
	$currency_symbol = isset($sc_arr[1]) ? $sc_arr[1] : '';
	
	date_default_timezone_set($time_zone);
if (!empty($connect) && $connect instanceof mysqli) {
    crm_sync_mysql_timezone($connect, $time_zone);
}
if (isset($database) && isset($database->connection) && $database->connection instanceof mysqli) {
    crm_sync_mysql_timezone($database->connection, $time_zone);
}
if (!crm_is_lightweight_request()) {
    crm_sync_offline_session_status($connect);
}
$countries = array("Afghanistan", "Albania", "Algeria", "American Samoa", "Andorra", "Angola", "Anguilla", "Antarctica", "Antigua and Barbuda", "Argentina", "Armenia", "Aruba", "Australia", "Austria", "Azerbaijan", "Bahamas", "Bahrain", "Bangladesh", "Barbados", "Belarus", "Belgium", "Belize", "Benin", "Bermuda", "Bhutan", "Bolivia", "Bosnia and Herzegowina", "Botswana", "Bouvet Island", "Brazil", "British Indian Ocean Territory", "Brunei Darussalam", "Bulgaria", "Burkina Faso", "Burundi", "Cambodia", "Cameroon", "Canada", "Cape Verde", "Cayman Islands", "Central African Republic", "Chad", "Chile", "China", "Christmas Island", "Cocos (Keeling) Islands", "Colombia", "Comoros", "Congo", "Congo, the Democratic Republic of the", "Cook Islands", "Costa Rica", "Cote d'Ivoire", "Croatia (Hrvatska)", "Cuba", "Cyprus", "Czech Republic", "Denmark", "Djibouti", "Dominica", "Dominican Republic", "East Timor", "Ecuador", "Egypt", "El Salvador", "Equatorial Guinea", "Eritrea", "Estonia", "Ethiopia", "Falkland Islands (Malvinas)", "Faroe Islands", "Fiji", "Finland", "France", "France Metropolitan", "French Guiana", "French Polynesia", "French Southern Territories", "Gabon", "Gambia", "Georgia", "Germany", "Ghana", "Gibraltar", "Greece", "Greenland", "Grenada", "Guadeloupe", "Guam", "Guatemala", "Guinea", "Guinea-Bissau", "Guyana", "Haiti", "Heard and Mc Donald Islands", "Holy See (Vatican City State)", "Honduras", "Hong Kong", "Hungary", "Iceland", "India", "Indonesia", "Iran (Islamic Republic of)", "Iraq", "Ireland", "Israel", "Italy", "Jamaica", "Japan", "Jordan", "Kazakhstan", "Kenya", "Kiribati", "Korea, Democratic People's Republic of", "Korea, Republic of", "Kuwait", "Kyrgyzstan", "Lao, People's Democratic Republic", "Latvia", "Lebanon", "Lesotho", "Liberia", "Libyan Arab Jamahiriya", "Liechtenstein", "Lithuania", "Luxembourg", "Macau", "Macedonia, The Former Yugoslav Republic of", "Madagascar", "Malawi", "Malaysia", "Maldives", "Mali", "Malta", "Marshall Islands", "Martinique", "Mauritania", "Mauritius", "Mayotte", "Mexico", "Micronesia, Federated States of", "Moldova, Republic of", "Monaco", "Mongolia", "Montserrat", "Morocco", "Mozambique", "Myanmar", "Namibia", "Nauru", "Nepal", "Netherlands", "Netherlands Antilles", "New Caledonia", "New Zealand", "Nicaragua", "Niger", "Nigeria", "Niue", "Norfolk Island", "Northern Mariana Islands", "Norway", "Oman", "Pakistan", "Palau", "Panama", "Papua New Guinea", "Paraguay", "Peru", "Philippines", "Pitcairn", "Poland", "Portugal", "Puerto Rico", "Qatar", "Reunion", "Romania", "Russian Federation", "Rwanda", "Saint Kitts and Nevis", "Saint Lucia", "Saint Vincent and the Grenadines", "Samoa", "San Marino", "Sao Tome and Principe", "Saudi Arabia", "Senegal", "Seychelles", "Sierra Leone", "Singapore", "Slovakia (Slovak Republic)", "Slovenia", "Solomon Islands", "Somalia", "South Africa", "South Georgia and the South Sandwich Islands", "Spain", "Sri Lanka", "St. Helena", "St. Pierre and Miquelon", "Sudan", "Suriname", "Svalbard and Jan Mayen Islands", "Swaziland", "Sweden", "Switzerland", "Syrian Arab Republic", "Taiwan, Province of China", "Tajikistan", "Tanzania, United Republic of", "Thailand", "Togo", "Tokelau", "Tonga", "Trinidad and Tobago", "Tunisia", "Turkey", "Turkmenistan", "Turks and Caicos Islands", "Tuvalu", "Uganda", "Ukraine", "United Arab Emirates", "United Kingdom", "United States", "United States Minor Outlying Islands", "Uruguay", "Uzbekistan", "Vanuatu", "Venezuela", "Vietnam", "Virgin Islands (British)", "Virgin Islands (U.S.)", "Wallis and Futuna Islands", "Western Sahara", "Yemen", "Yugoslavia", "Zambia", "Zimbabwe");

if (!isset($db1) || !$db1) {
    $db1 = new ConnectMe(DB_SERVER, DB_USER, DB_PASS, DB_NAME);
}

/**
 * Generate profile picture URL with cache-busting to prevent broken images after upload
 * @param string $filename The profile picture filename
 * @param int $width Width of the thumbnail (default: 36)
 * @param int $height Height of the thumbnail (default: 36)
 * @return string The complete URL with cache-busting parameter
 */
function getProfilePicUrl($filename, $width = 36, $height = 36) {
    global $url;
    if (empty($filename)) {
        // Return empty string to indicate no image, let the calling code handle initials
        return '';
    }
    $cacheBuster = time();
    return $url . "includes/thumbnail.php?src=" . $url . "uploads/profile-pics/" . $filename . "&h=" . $height . "&w=" . $width . "&cb=" . $cacheBuster;
}

if (!crm_is_lightweight_request()) {
    if (!(function_exists('tasksession_is_free_edition') && tasksession_is_free_edition())) {
        require_once(LIB_ROOT . DS . 'server_load_helper.php');
        server_load_register_slow_request_logger();
    }
}

?>