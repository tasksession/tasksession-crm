<!DOCTYPE html>
<html class="no-js">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title><?php echo $title; ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php
$crm_preload_sidebar_logo = !empty($login_page_logo_url) ? $login_page_logo_url : '';
$crm_preload_sidebar_favicon = !empty($sidebar_favicon_url) ? $sidebar_favicon_url : '';
if ($crm_preload_sidebar_logo !== '') {
    echo '<link rel="preload" as="image" href="' . htmlspecialchars($crm_preload_sidebar_logo, ENT_QUOTES, 'UTF-8') . '" fetchpriority="high">' . "\n";
}
if ($crm_preload_sidebar_favicon !== '' && $crm_preload_sidebar_favicon !== $crm_preload_sidebar_logo) {
    echo '<link rel="preload" as="image" href="' . htmlspecialchars($crm_preload_sidebar_favicon, ENT_QUOTES, 'UTF-8') . '" fetchpriority="high">' . "\n";
}
$googleFontHref = 'https://fonts.googleapis.com/css2?family=Lato:wght@400;700;900&display=swap';
$theme_vars_cache_v = (string)@filemtime(SITE_ROOT . DS . 'assets' . DS . 'css' . DS . 'theme-vars.php');
if (isset($db)) {
    require_once(LIB_ROOT . DS . 'ensure-theme-font-schema.php');
    $themeFontHelpersPath = LIB_ROOT . DS . 'theme-font-helpers.php';
    if (is_file($themeFontHelpersPath)) {
        require_once($themeFontHelpersPath);
    } elseif (!function_exists('theme_font_build_google_url')) {
        function theme_font_build_google_url(array $theme) {
            $src = isset($theme['ui_font_source']) ? trim((string)$theme['ui_font_source']) : 'default';
            if ($src !== 'default' && $src !== 'google') {
                return '';
            }
            $fam = isset($theme['ui_google_font_family']) ? trim((string)$theme['ui_google_font_family']) : 'Lato';
            if ($fam === '') {
                $fam = 'Lato';
            }
            $fam = preg_replace('/[^a-zA-Z0-9 ]+/u', '', $fam);
            if ($fam === '') {
                $fam = 'Lato';
            }
            $w = isset($theme['ui_google_font_weights']) ? (string)$theme['ui_google_font_weights'] : '400;700;900';
            $w = preg_replace('/[^0-9;]/', '', $w);
            if ($w === '') {
                $w = '400;700;900';
            }
            return 'https://fonts.googleapis.com/css2?family=' . rawurlencode($fam) . ':wght@' . $w . '&display=swap';
        }
    }
    require_once(LIB_ROOT . DS . 'theme-shadow-helpers.php');
    ensure_theme_font_schema($db);
    $theme_vars_cache_v = theme_vars_cache_version($db);
    $theme_font_row = array();
    $fontSql = 'SELECT * FROM theme_settings WHERE id=1 LIMIT 1';
    if (method_exists($db, 'querySoft')) {
        $tfr = $db->querySoft($fontSql);
    } else {
        $tfr = @mysqli_query($db->connection, $fontSql);
    }
    if ($tfr && method_exists($tfr, 'fetch_assoc') && $tfr->num_rows) {
        $fontRow = $tfr->fetch_assoc();
        if (is_array($fontRow)) {
            foreach (array('ui_font_source', 'ui_google_font_family', 'ui_google_font_weights') as $fontKey) {
                if (array_key_exists($fontKey, $fontRow)) {
                    $theme_font_row[$fontKey] = $fontRow[$fontKey];
                }
            }
        }
    }
    $built = theme_font_build_google_url($theme_font_row);
    /* Custom / web-safe: no Google Fonts; do not keep default Lato below */
    $googleFontHref = ($built !== '') ? $built : '';
}
?>
<link rel="stylesheet" type="text/css" href="<?php echo $url; ?>assets/css/theme-vars.php?v=<?php echo htmlspecialchars($theme_vars_cache_v, ENT_QUOTES, 'UTF-8'); ?>">
<?php if ($googleFontHref !== '') { ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="<?php echo htmlspecialchars($googleFontHref, ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
<?php } ?>
<link href="<?php echo $url; ?>assets/css/bootstrap.css" rel="stylesheet" type="text/css"/>
<?php
if (!function_exists('comon_page_asset_enabled') && defined('LIB_ROOT')) {
    require_once LIB_ROOT . DS . 'page_assets.php';
}
// Exclude jquery-ui.min.css on inbox and new (compose) pages
$isEmailPage = isset($_SERVER['REQUEST_URI']) && (
    strpos($_SERVER['REQUEST_URI'], '/mail/inbox.php') !== false ||
    strpos($_SERVER['REQUEST_URI'], '/mail/new.php') !== false
);
$loadJqueryUi = (!$isEmailPage) && (!function_exists('comon_page_asset_enabled') || comon_page_asset_enabled('jquery_ui'));
if ($loadJqueryUi) {
    echo '<link href="' . $url . 'assets/css/jquery-ui.min.css" rel="stylesheet" type="text/css"/>';
}
?>
<link href="<?php echo $url; ?>assets/css/style.min.css?v=<?php echo filemtime(SITE_ROOT . DS . 'assets' . DS . 'css' . DS . 'style.min.css'); ?>" rel="stylesheet" type="text/css"/>
<link href="<?php echo $url; ?>assets/css/icons.php?v=<?php echo function_exists('ts_icons_cache_version') ? ts_icons_cache_version() : time(); ?>" rel="stylesheet" type="text/css"/>
<link href="<?php echo $url; ?>assets/css/tasksession-task-sidebar-timer.css?v=<?php echo @filemtime(SITE_ROOT . DS . 'assets' . DS . 'css' . DS . 'tasksession-task-sidebar-timer.css'); ?>" rel="stylesheet" type="text/css"/>
<?php
$theme_fonts_php = SITE_ROOT . DS . 'assets' . DS . 'css' . DS . 'theme-fonts.php';
$theme_fonts_v = is_file($theme_fonts_php) ? filemtime($theme_fonts_php) : time();
?>
<link href="<?php echo $url; ?>assets/css/theme-fonts.php?v=<?php echo $theme_fonts_v; ?>" rel="stylesheet" type="text/css"/>
<?php
$loadRichText = !function_exists('comon_page_asset_enabled') || comon_page_asset_enabled('rich_text');
if ($loadRichText) {
    echo '<link href="' . $url . 'assets/css/rich-text.css?v=' . filemtime(SITE_ROOT . DS . 'assets' . DS . 'css' . DS . 'rich-text.css') . '" rel="stylesheet" type="text/css"/>' . "\n";
}
?>
<?php 
// Do not link favicon as a direct /uploads/... URL — uploads/.htaccess blocks it (403).
// getSystemImageUrl() serves via includes/secure_image_handler.php (system-uploads are public there).
$default_favicon_path = $url . 'assets/images/favicon.png';
$custom_favicon_file = dirname(__DIR__) . '/' . $img_path . $favicon_image;
if ($favicon_image_check && file_exists($custom_favicon_file)) {
    $favicon_src = isset($sidebar_favicon_url) && $sidebar_favicon_url
        ? $sidebar_favicon_url
        : crm_resolve_sidebar_favicon_url($favicon_image, $login_page_logo ?? '');
} else {
    $favicon_src = crm_default_asset_image_url('assets/images/favicon.png');
}
?>
<link rel="icon" href="<?php echo htmlspecialchars($favicon_src, ENT_QUOTES, 'UTF-8'); ?>" sizes="16x16" type="image/png">
<script>
(function () {
    try {
        if (localStorage.getItem('sidebarShrunk') === 'true') {
            document.documentElement.classList.add('sidebar-shrunk-initial');
        }
    } catch (e) {}
})();
</script>
</head>
<!-- END HEAD --><!-- BEGIN BODY -->
<body<?php if (!empty($body_class)) { echo ' class="' . htmlspecialchars((string) $body_class, ENT_QUOTES, 'UTF-8') . '"'; } ?>>
<?php if (isset($lang) && is_array($lang)) { ?>
<script>window.comonNotificationsSettingsLabel=<?php echo json_encode(isset($lang['Notifications Settings']) ? $lang['Notifications Settings'] : 'Notifications Settings', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;window.comonNotificationsSettingsSaved=<?php echo json_encode(isset($lang['Notifications settings saved.']) ? $lang['Notifications settings saved.'] : 'Notifications settings saved.', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
<?php } ?>
<?php if (!empty($url)) { ?>
<script>
(function () {
    var configured = <?php echo json_encode(rtrim((string) $url, '/') . '/'); ?>;
    try {
        var loc = window.location;
        var cfg = new URL(configured, loc.origin);
        var a = loc.hostname.replace(/^www\./i, '');
        var b = cfg.hostname.replace(/^www\./i, '');
        if (a === b) {
            var path = cfg.pathname || '/';
            if (path.slice(-1) !== '/') {
                path += '/';
            }
            configured = loc.origin + path;
        }
    } catch (e) {}
    window.baseUrl = configured;
    window.siteRootUrl = configured.replace(/\/$/, '');
})();
</script>
<?php } ?>
<?php 
if (isset($_SESSION['accountStatus'])) {
    $license_valid = true;
    if (!empty($license_valid)) {
        require_once(LIB_ROOT . DS . 'setup_guide.php');
        $showSetupGuide = (function_exists('setup_guide_should_show') && setup_guide_should_show())
            || (function_exists('setup_guide_should_show_celebration') && setup_guide_should_show_celebration());
        if ($showSetupGuide) {
            include __DIR__ . '/setup_guide.php';
        }
    }
}
?>
<?php include __DIR__ . '/task-sidebar.php'; ?>
<?php
$__aiRail = __DIR__ . '/partials/ai-assistant-rail.php';
if (is_file($__aiRail)) {
    include $__aiRail;
}
?>
