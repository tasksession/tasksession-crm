<?php
/**
 * Injects global success modal HTML before </body> (via main-footer hook).
 * JS lives in assets/js/task.js — no separate file.
 */
if (!empty($GLOBALS['comon_success_modal_bundle_loaded'])) {
    return;
}
$GLOBALS['comon_success_modal_bundle_loaded'] = true;

if (!isset($lang) || !is_array($lang)) {
    $lang = [];
}

ob_start();
include __DIR__ . '/modals/comon-success-modal.php';
$modalHtml = ob_get_clean();
$GLOBALS['comon_before_body_close_html'] = ($GLOBALS['comon_before_body_close_html'] ?? '') . $modalHtml;
