<!DOCTYPE html class="frontend-logo">
<html lang="en">
<!-- BEGIN HEAD -->
<head>
<meta charset="utf-8" />
<title><?php echo $title; ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<?php
if (!empty($is_login_page)) {
    if (!empty($login_brand_logo_url)) {
        echo '<link rel="preload" as="image" href="' . htmlspecialchars($login_brand_logo_url, ENT_QUOTES, 'UTF-8') . '" fetchpriority="high">' . "\n";
    }
    if (!empty($login_page_image_url)) {
        echo '<link rel="preload" as="image" href="' . htmlspecialchars($login_page_image_url, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    }
}
$googleFontHrefFe = 'https://fonts.googleapis.com/css2?family=Lato:wght@400;700;900&display=swap';
if (isset($db) && empty($is_login_page)) {
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
    ensure_theme_font_schema($db);
    $theme_font_row_fe = array();
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
                    $theme_font_row_fe[$fontKey] = $fontRow[$fontKey];
                }
            }
        }
    }
    $builtFe = theme_font_build_google_url($theme_font_row_fe);
    $googleFontHrefFe = ($builtFe !== '') ? $builtFe : '';
}
?>
<?php if ($googleFontHrefFe !== '') { ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<?php if (!empty($is_login_page)) { ?>
<link href="<?php echo htmlspecialchars($googleFontHrefFe, ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" media="print" onload="this.media='all'">
<noscript><link href="<?php echo htmlspecialchars($googleFontHrefFe, ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet"></noscript>
<?php } else { ?>
<link href="<?php echo htmlspecialchars($googleFontHrefFe, ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
<?php } ?>
<?php } ?>
<link href="assets/css/bootstrap.css" rel="stylesheet" type="text/css"/>
<?php if (isset($is_login_page) && $is_login_page): ?>
<?php
$frontCssPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'front.css';
$frontCssV = is_file($frontCssPath) ? filemtime($frontCssPath) : time();
?>
<link href="assets/css/front.css?v=<?php echo (int) $frontCssV; ?>" rel="stylesheet" type="text/css"/>
<?php else: ?>
<?php endif; ?>
<?php
$theme_fonts_php_fe = SITE_ROOT . DS . 'assets' . DS . 'css' . DS . 'theme-fonts.php';
$theme_fonts_v_fe = is_file($theme_fonts_php_fe) ? filemtime($theme_fonts_php_fe) : time();
?>
<link href="assets/css/theme-fonts.php?v=<?php echo $theme_fonts_v_fe; ?>" rel="stylesheet" type="text/css"/>
<?php 
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
</head>
<body>
<?php /* Free edition: no license modal */ ?>
