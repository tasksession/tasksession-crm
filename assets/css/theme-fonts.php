<?php
/**
 * Dynamic CSS for --app-font-family. Must never 500 — browsers treat failed CSS harshly.
 */
header('Content-Type: text/css; charset=UTF-8');
header('Cache-Control: public, max-age=300');
header('X-Content-Type-Options: nosniff');

$fallbackCss = ":root{--app-font-family:\"Lato\", sans-serif;}\nbody{font-family:var(--app-font-family)!important;}\n";

try {
    if (!defined('CRM_LIGHTWEIGHT_INIT')) {
        define('CRM_LIGHTWEIGHT_INIT', true);
    }

    include_once('../../includes/lib-initialize.php');

    $schemaFile = defined('LIB_ROOT')
        ? (LIB_ROOT . DIRECTORY_SEPARATOR . 'ensure-theme-font-schema.php')
        : (__DIR__ . '/../../includes/ensure-theme-font-schema.php');
    if (is_file($schemaFile)) {
        require_once $schemaFile;
    }
    if (function_exists('ensure_theme_font_schema') && isset($db) && $db) {
        try {
            ensure_theme_font_schema($db);
        } catch (Throwable $e) {
            // Schema migrate must not break CSS delivery
        }
    }

    if (!isset($db) || !$db) {
        echo $fallbackCss;
        exit;
    }

    if (method_exists($db, 'querySoft')) {
        $themeResult = $db->querySoft('SELECT * FROM theme_settings WHERE id=1 LIMIT 1');
    } else {
        $themeResult = @mysqli_query($db->connection, 'SELECT * FROM theme_settings WHERE id=1 LIMIT 1');
    }
    $theme = ($themeResult && method_exists($themeResult, 'fetch_assoc')) ? $themeResult->fetch_assoc() : null;
    if (!is_array($theme)) {
        echo "/* theme_settings missing */\n" . $fallbackCss;
        exit;
    }

    $src = isset($theme['ui_font_source']) ? trim((string)$theme['ui_font_source']) : 'default';
    $googleFamily = isset($theme['ui_google_font_family']) ? trim((string)$theme['ui_google_font_family']) : 'Lato';
    $websafe = isset($theme['ui_websafe_stack']) ? trim((string)$theme['ui_websafe_stack']) : '';
    $customId = isset($theme['ui_custom_font_id']) ? (int)$theme['ui_custom_font_id'] : 0;

    $familyCss = '"Lato", sans-serif';

    if ($src === 'websafe' && $websafe !== '') {
        $ws = trim($websafe);
        if (strlen($ws) > 500) {
            $ws = substr($ws, 0, 500);
        }
        if (preg_match('/[;{}\\\\]|\/\*|@import|url\s*\(/i', $ws)) {
            $familyCss = 'sans-serif';
        } else {
            $familyCss = $ws;
        }
    } elseif ($src === 'google' && $googleFamily !== '') {
        $q = str_replace(array('"', '\\'), '', $googleFamily);
        $familyCss = '"' . $q . '", sans-serif';
    } elseif ($src === 'custom' && $customId > 0) {
        $cid = (int)$customId;
        $cf = null;
        $cf_res = method_exists($db, 'querySoft')
            ? $db->querySoft("SELECT family_css, font_weight, is_italic, file_path FROM custom_fonts WHERE id = {$cid} LIMIT 1")
            : @$db->query("SELECT family_css, font_weight, is_italic, file_path FROM custom_fonts WHERE id = {$cid} LIMIT 1");
        if ($cf_res && method_exists($cf_res, 'fetch_assoc')) {
            $cf = $cf_res->fetch_assoc();
        }
        if ($cf && !empty($cf['file_path'])) {
            $fn = trim((string)$cf['family_css']);
            $fn = preg_replace('/[^a-zA-Z0-9 _-]/', '', $fn);
            if ($fn === '') {
                $fn = 'CustomFont';
            }
            $rel = str_replace('\\', '/', (string)$cf['file_path']);
            $rel = ltrim($rel, '/');
            if ($rel === '' || !preg_match('#^uploads/custom-fonts/[a-zA-Z0-9_.-]+$#', $rel)) {
                $familyCss = 'sans-serif';
            } else {
                $local = SITE_ROOT . DS . str_replace('/', DS, $rel);
                $realFile = realpath($local);
                $allowedDir = realpath(SITE_ROOT . DS . 'uploads' . DS . 'custom-fonts');
                $w = (int)$cf['font_weight'];
                if ($w < 1 || $w > 1000) {
                    $w = 400;
                }
                $italic = !empty($cf['is_italic']) ? 'italic' : 'normal';
                $ff = '"' . str_replace('"', '', $fn) . '"';
                $extOk = $realFile && $allowedDir && strpos($realFile, $allowedDir) === 0
                    && in_array(strtolower(pathinfo($realFile, PATHINFO_EXTENSION)), array('woff', 'woff2'), true);
                if ($extOk && is_file($realFile)) {
                    $v = (int)@filemtime($realFile);
                    if ($v < 1) {
                        $v = time();
                    }
                    $urlInCss = 'font-serve.php?id=' . $cid . '&v=' . $v;
                    $urlEsc = htmlspecialchars($urlInCss, ENT_QUOTES, 'UTF-8');
                    $fontExt = strtolower(pathinfo($realFile, PATHINFO_EXTENSION));
                    $format = ($fontExt === 'woff2') ? 'woff2' : 'woff';
                    echo '@font-face{font-family:' . $ff . ';src:url("' . $urlEsc . '") format(\'' . $format . '\');font-weight:' . $w . ';font-style:' . $italic . ';font-display:swap;}' . "\n";
                    $familyCss = $ff . ', sans-serif';
                } else {
                    $familyCss = 'sans-serif';
                }
            }
        } else {
            $familyCss = 'sans-serif';
        }
    }

    echo ':root{--app-font-family:' . $familyCss . ";}\n";
    echo 'body{font-family:var(--app-font-family)!important;}' . "\n";
} catch (Throwable $e) {
    echo "/* theme-fonts error */\n" . $fallbackCss;
}
