<?php
/**
 * Invoice action menu icons — central ts_icon set.
 */
function invoice_action_menu_icon(string $name): string
{
    $map = [
        'edit' => 'edit',
        'duplicate' => 'duplicate',
        'delete' => 'delete',
        'mark_paid' => 'check-circle',
        'cancel' => 'close',
        'copy_link' => 'link',
        'view_project' => 'info',
        'download_pdf' => 'download',
        'retry_charge' => 'refresh',
    ];
    $icon = $map[$name] ?? '';
    if ($icon === '' || !function_exists('ts_icon')) {
        return '';
    }
    return ts_icon($icon, 'tasksession-timer-log-menu-ico me-2');
}
