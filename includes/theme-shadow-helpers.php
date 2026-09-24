<?php
/**
 * Box shadow helpers — normalize rgba() to rgb(r g b / a) for safe use in CSS variables.
 * Commas inside rgba() break var(--box-shadow) when used in box-shadow shorthand.
 */

function theme_box_shadow_default() {
    return '0px 3px 13px -5px rgb(0 0 0 / 0.1)';
}

function theme_card_border_radius_default() {
    return '12';
}

function theme_title_font_weight_default() {
    return '600';
}

function theme_box_shadow_normalize($raw) {
    $v = trim((string)$raw);
    if ($v === '' || strtolower($v) === 'none') {
        return 'none';
    }
    if (strlen($v) > 120) {
        return theme_box_shadow_default();
    }

    $v = preg_replace_callback(
        '/rgba\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*([\d.]+)\s*\)/i',
        static function (array $m) {
            return 'rgb(' . $m[1] . ' ' . $m[2] . ' ' . $m[3] . ' / ' . $m[4] . ')';
        },
        $v
    );

    return $v;
}

function theme_box_shadow_is_valid($value) {
    $v = trim((string)$value);
    if ($v === 'none') {
        return true;
    }
    if (strlen($v) > 120) {
        return false;
    }
    if (preg_match('/^(?:inset\s+)?-?\d+px\s+-?\d+px\s+-?\d+px\s+-?\d+px\s+rgb\(\s*\d+\s+\d+\s+\d+\s*\/\s*[\d.]+\s*\)$/i', $v)) {
        return true;
    }
    if (preg_match('/^(?:inset\s+)?-?\d+px\s+-?\d+px\s+-?\d+px\s+-?\d+px\s+rgba\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*,\s*[\d.]+\s*\)$/i', $v)) {
        return true;
    }
    if (preg_match('/^[0-9a-zA-Z(),.%#\s\/-]+$/', $v)) {
        return true;
    }
    return false;
}

function theme_box_shadow_sanitize($raw) {
    $v = theme_box_shadow_normalize($raw);
    if ($v === 'none') {
        return 'none';
    }
    if (theme_box_shadow_is_valid($v)) {
        return $v;
    }
    return theme_box_shadow_default();
}

/**
 * Cache-bust token for theme-vars.php — changes when theme settings change.
 */
function theme_vars_cache_version($db) {
    if (!$db || !is_object($db)) {
        return (string)time();
    }
    $sql = 'SELECT * FROM theme_settings WHERE id=1 LIMIT 1';
    if (method_exists($db, 'querySoft')) {
        $result = $db->querySoft($sql);
    } else {
        $result = @mysqli_query($db->connection, $sql);
    }
    if (!$result || !method_exists($result, 'fetch_assoc')) {
        return (string)time();
    }
    $row = $result->fetch_assoc();
    if (!is_array($row)) {
        return (string)time();
    }
    $cacheKeys = array(
        'box_shadow',
        'card_border_radius',
        'primary_color',
        'secondary_color',
        'body_bg_color',
        'border_color',
        'card_body_color',
        'title_color',
        'title_font_weight',
        'button_border_radius',
    );
    $subset = array();
    foreach ($cacheKeys as $key) {
        if (array_key_exists($key, $row)) {
            $subset[$key] = $row[$key];
        }
    }
    return substr(md5(json_encode($subset)), 0, 12);
}
