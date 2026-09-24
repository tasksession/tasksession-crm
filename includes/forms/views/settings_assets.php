<?php
/**
 * Forms settings pages — same CSS stack as ai/setting.php (shell + panel head).
 */
if (defined('FORMS_SETTINGS_ASSETS_LOADED')) {
    return;
}
define('FORMS_SETTINGS_ASSETS_LOADED', true);

global $url;
$base = rtrim((string) $url, '/');
$root = dirname(__DIR__, 3);

$aiCssFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'ai-settings-shell.css';
$aiCssHref = $base . '/includes/forms/assets/css/ai-settings-shell.css';
$aiCssVer = is_file($aiCssFile) ? (int) @filemtime($aiCssFile) : 0;
if ($aiCssVer > 0) {
    $aiCssHref .= '?v=' . rawurlencode((string) $aiCssVer);
}
echo '<link rel="stylesheet" href="' . htmlspecialchars($aiCssHref, ENT_QUOTES, 'UTF-8') . '">' . "\n";

$formsCssFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'forms-settings.css';
$formsCssHref = $base . '/includes/forms/assets/css/forms-settings.css';
$formsCssVer = is_file($formsCssFile) ? (int) @filemtime($formsCssFile) : 0;
if ($formsCssVer > 0) {
    $formsCssHref .= '?v=' . rawurlencode((string) $formsCssVer);
}
echo '<link rel="stylesheet" href="' . htmlspecialchars($formsCssHref, ENT_QUOTES, 'UTF-8') . '">' . "\n";

if (!empty($formsReportsFilterAssets)) {
    echo '<link rel="stylesheet" href="' . htmlspecialchars($base . '/assets/css/attendance.css', ENT_QUOTES, 'UTF-8') . '">' . "\n";
    $reportsCssFile = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'reports.css';
    $reportsCssHref = $base . '/assets/css/reports.css';
    $reportsCssVer = is_file($reportsCssFile) ? (int) @filemtime($reportsCssFile) : 0;
    if ($reportsCssVer > 0) {
        $reportsCssHref .= '?v=' . rawurlencode((string) $reportsCssVer);
    }
    echo '<link rel="stylesheet" href="' . htmlspecialchars($reportsCssHref, ENT_QUOTES, 'UTF-8') . '">' . "\n";
}
