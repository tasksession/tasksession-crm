<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">    
<title><?php echo $title; ?></title>
<?php
// Match templates/header.php: theme-driven Google Fonts + theme-fonts.php (global --app-font-family on body)
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
    $googleFontHref = ($built !== '') ? $built : '';
}
?>
<link rel="stylesheet" type="text/css" href="<?php echo $url; ?>assets/css/theme-vars.php?v=<?php echo htmlspecialchars($theme_vars_cache_v, ENT_QUOTES, 'UTF-8'); ?>">
<?php if ($googleFontHref !== '') { ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="<?php echo htmlspecialchars($googleFontHref, ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
<?php } ?>
<link href="<?php echo $url; ?>assets/css/bootstrap.css" rel="stylesheet">
<link href="<?php echo $url; ?>assets/css/lightbox.css?v=<?php echo (int)@filemtime(SITE_ROOT . DS . 'assets' . DS . 'css' . DS . 'lightbox.css'); ?>" rel="stylesheet">
<link href="<?php echo $url; ?>assets/css/messages.css?v=<?php echo (int)@filemtime(SITE_ROOT . DS . 'assets' . DS . 'css' . DS . 'messages.css'); ?>" rel="stylesheet" id="comon-messages-css">
<link href="<?php echo $url; ?>assets/css/style.min.css?v=<?php echo filemtime(SITE_ROOT . DS . 'assets' . DS . 'css' . DS . 'style.min.css'); ?>" rel="stylesheet" type="text/css"/>
<link href="<?php echo $url; ?>assets/css/icons.php?v=<?php echo function_exists('ts_icons_cache_version') ? ts_icons_cache_version() : time(); ?>" rel="stylesheet" type="text/css"/>
<link href="<?php echo $url; ?>assets/css/tasksession-task-sidebar-timer.css?v=<?php echo @filemtime(SITE_ROOT . DS . 'assets' . DS . 'css' . DS . 'tasksession-task-sidebar-timer.css'); ?>" rel="stylesheet" type="text/css"/>
<?php
$theme_fonts_php = SITE_ROOT . DS . 'assets' . DS . 'css' . DS . 'theme-fonts.php';
$theme_fonts_v = is_file($theme_fonts_php) ? filemtime($theme_fonts_php) : time();
?>
<link href="<?php echo $url; ?>assets/css/theme-fonts.php?v=<?php echo $theme_fonts_v; ?>" rel="stylesheet" type="text/css"/>
<script src="<?php echo $url; ?>assets/js/jquery.js" type="text/javascript"></script>
<?php 
// Custom favicon: use secure handler, not direct /uploads/ (blocked by uploads/.htaccess).
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
<link rel="icon" href="<?php echo $favicon_src; ?>" sizes="16x16" type="image/png">
<?php
$chatPageCsrf = '';
if (function_exists('generate_csrf_token')) {
    $chatPageCsrf = generate_csrf_token();
}
?>
<?php if ($chatPageCsrf !== '') { ?>
<meta name="csrf-token" content="<?php echo htmlspecialchars($chatPageCsrf, ENT_QUOTES, 'UTF-8'); ?>">
<?php } ?>
<script>
window.baseUrl = "<?php echo rtrim($url, '/'); ?>/";
<?php if ($chatPageCsrf !== '') { ?>
window.csrfToken = <?php echo json_encode($chatPageCsrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
<?php } ?>
<?php if (isset($lang) && is_array($lang)) { ?>
window.comonNotificationsSettingsLabel = <?php echo json_encode(isset($lang['Notifications Settings']) ? $lang['Notifications Settings'] : 'Notifications settings', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.comonNotificationsSettingsSaved = <?php echo json_encode(isset($lang['Notifications settings saved.']) ? $lang['Notifications settings saved.'] : 'Notifications settings saved.', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
<?php } ?>
</script>
</head>
<body class="page-chatting">
<?php include __DIR__ . '/task-sidebar.php'; ?>
<?php include __DIR__ . '/partials/ai-assistant-rail.php'; ?>

<!-- END HEAD -->
<!-- BEGIN BODY -->