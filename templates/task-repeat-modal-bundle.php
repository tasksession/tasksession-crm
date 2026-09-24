<?php
/**
 * Injects Set repeats customize modals before </body>.
 */
if (!empty($GLOBALS['comon_task_repeat_modal_bundle_loaded'])) {
    return;
}
$GLOBALS['comon_task_repeat_modal_bundle_loaded'] = true;

if (!isset($lang) || !is_array($lang)) {
    $lang = [];
}

ob_start();
include __DIR__ . '/modals/task-repeat-modal.php';
$modalHtml = ob_get_clean();
$GLOBALS['comon_before_body_close_html'] = ($GLOBALS['comon_before_body_close_html'] ?? '') . $modalHtml;
