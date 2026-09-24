<?php
if (empty($formsReportsFilterAssets)) {
    return;
}
global $url;
$jsFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'forms-reports-filters.js';
$jsHref = rtrim((string) $url, '/') . '/includes/forms/assets/js/forms-reports-filters.js';
$jsVer = is_file($jsFile) ? (int) @filemtime($jsFile) : 0;
if ($jsVer > 0) {
    $jsHref .= '?v=' . rawurlencode((string) $jsVer);
}
?>
<script src="<?php echo htmlspecialchars($jsHref, ENT_QUOTES, 'UTF-8'); ?>"></script>
