<?php
/**
 * Google Fonts stylesheet URL from theme_settings (default + google sources only).
 *
 * @param array<string,mixed> $theme Row from theme_settings (partial ok).
 */
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

/**
 * @param array<string,mixed> $theme
 */
function theme_font_loads_google(array $theme) {
    return theme_font_build_google_url($theme) !== '';
}
