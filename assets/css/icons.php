<?php
/**
 * Generated icon CSS from assets/icons/*.svg (mask + currentColor).
 * Serves a disk cache (icons.generated.css) when fresh; rebuilds when stale.
 */
header('Content-Type: text/css; charset=UTF-8');
header('Cache-Control: public, max-age=86400');

$cssDir = __DIR__;
$generatedFile = $cssDir . DIRECTORY_SEPARATOR . 'icons.generated.css';
$iconsDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'icons';
$selfMtime = (int) @filemtime(__FILE__);

$files = is_dir($iconsDir) ? glob($iconsDir . DIRECTORY_SEPARATOR . '*.svg') : array();
if (!is_array($files)) {
    $files = array();
}

$newestSvg = 0;
foreach ($files as $file) {
    $mtime = (int) @filemtime($file);
    if ($mtime > $newestSvg) {
        $newestSvg = $mtime;
    }
}

$sourceMax = max($newestSvg, $selfMtime);
$generatedMtime = is_file($generatedFile) ? (int) @filemtime($generatedFile) : 0;
$needRebuild = ($generatedMtime < 1) || ($generatedMtime < $sourceMax);

if (!$needRebuild) {
    readfile($generatedFile);
    exit;
}

$out = '';
$out .= ".ts-icon{display:inline-block;width:18px;height:18px;flex-shrink:0;vertical-align:middle;background-color:currentColor;-webkit-mask:var(--ts-icon) center/contain no-repeat;mask:var(--ts-icon) center/contain no-repeat}\n";
$out .= ".ts-icon.h-6{width:18px;height:18px}\n";
$out .= ".ts-icon.w-2{width:14px;height:14px}\n";
$out .= ".ts-icon.w-4{width:16px;height:16px}\n";
$out .= ".ts-icon.w-5{width:20px;height:20px}\n";
$out .= ".ts-icon.tasksession-timer-log-menu-ico{width:20px;height:20px;color:#8C939F;flex-shrink:0}\n";
$out .= ".ts-icon.ts-icon-delete.tasksession-timer-log-menu-ico{color:#F94747}\n";
$out .= ".search-icon .ts-icon{width:15px;height:15px;color:inherit}\n";
$out .= ".cross .ts-icon{width:18px;height:18px;color:inherit}\n";
$out .= ".ts-icon.dropdown-toggle-icon{width:18px;height:18px}\n";
$out .= ".kanban-filter-funnel.ts-icon,.action-toggle .kanban-filter-funnel{width:18px;height:18px}\n";
$out .= ".primary-btn .ts-icon{width:18px;height:18px}\n";
$out .= ".primary-btn .ts-icon,#primary-btn .ts-icon,.upload-btn .ts-icon,.btn.primary-btn .ts-icon,.toggle-action ul li a.primary-btn .ts-icon,.toggle-action ul li button.primary-btn .ts-icon{color:var(--primary-button-font-color)!important}\n";
$out .= ".icons-btn .ts-icon,.icons-btn a .ts-icon{width:20px;height:20px}\n";
$out .= ".btn-dots .ts-icon{width:18px;height:18px}\n";
$out .= ".eye .ts-icon,#eyeOpen.ts-icon,#eyeClosed.ts-icon{width:22px;height:22px}\n";
$out .= ".attendance-trend-icon .ts-icon{width:14px;height:14px}\n";
$out .= ".task-reports-card-icon.ts-icon,.ts-icon.task-reports-card-icon{width:20px;height:20px}\n";
$out .= ".ts-woo-prod-icon.ts-icon{width:18px;height:18px}\n";
$out .= ".ts-icon.upload-icon{width:40px;height:40px}\n";
$out .= ".upload-dropzone .upload-icon .ts-icon{width:48px;height:48px}\n";
$out .= ".marketing-import-success-icon .ts-icon{width:28px;height:28px}\n";
$out .= ".ts-icon.success-icon{width:40px;height:40px;color:#fff}\n";
$out .= ".ts-icon.tasksession-header-timer-pill-icon,.ts-icon.tasksession-timer-complete-timer-ico{width:20px;height:20px}\n";
$out .= ".ts-icon.tasksession-timer-ico-play,.ts-icon.tasksession-timer-ico-pause{width:14px;height:14px}\n";
$out .= ".ts-icon.tasksession-timer-delete-work-ico{width:20px;height:20px;color:#F94747}\n";
$out .= ".ts-icon.tasksession-timer-manual-dd-ico{width:20px;height:20px}\n";
$out .= ".left-center .ts-icon{width:14px;height:14px}\n";
$out .= ".widget-card>.ts-icon,.ts-icon.w-8{width:25px;height:25px}\n";
$out .= ".widget-card>.ts-icon,.ts-icon.primary{color:var(--primary-color)!important}\n";
$out .= ".up-down .ts-icon{width:1em;height:1em;vertical-align:middle}\n";
$out .= ".up-down .ts-icon-arrow-up-right{color:green}\n";
$out .= ".up-down .ts-icon-arrow-down-right{color:red}\n";
$out .= ".up-down .ts-icon-arrows-up-down{color:gray}\n";
$out .= ".contact-item{column-gap:8px}\n";
$out .= ".contact-item .ts-icon{width:16px;height:16px;flex-shrink:0;margin-right:0!important;color:var(--text-muted,#8C939F)}\n";
$out .= ".lead-card .font-size-12 .ts-icon,.lead-card .align-items-center>.ts-icon,.lead-card .font-size-12.d-flex>.ts-icon{width:12px;height:12px;flex-shrink:0;margin-right:4px;color:inherit}\n";
$out .= ".ts-icon.size-6{width:24px;height:24px}\n";
$out .= ".ts-icon.tasksession-timer-work-action-ico{width:20px;height:20px}\n";
$out .= ".ts-icon.card-icon,.ts-icon.timeline-header-icon{width:20px;height:20px}\n";
$out .= ".ts-icon.detail-icon,.ts-icon.btn-icon,.ts-icon.info-icon{width:16px;height:16px}\n";
$out .= ".ts-icon.icon-caret-down{width:16px;height:16px}\n";
$out .= ".ts-icon.backup-empty-icon{width:48px;height:48px}\n";
$out .= ".ts-icon.backup-meta-ico{width:14px;height:14px;color:#8C939F}\n";
$out .= ".ts-icon.date-icon,.ts-icon.info-item-icon,.ts-icon.warning-icon{width:16px;height:16px}\n";
$out .= ".ts-icon.mobile-filter-btn-icon{width:18px;height:18px}\n";
$out .= ".mobile-menu .ts-icon{width:25px;height:25px}\n";

foreach ($files as $file) {
    $name = basename($file, '.svg');
    if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/', $name)) {
        continue;
    }
    $svg = @file_get_contents($file);
    if (!is_string($svg) || $svg === '') {
        continue;
    }
    $svg = preg_replace('/<\?xml[^>]*\?>/', '', $svg);
    $svg = str_replace(array('currentColor', '#000000'), '#000', $svg);
    $svg = preg_replace('/\s+/', ' ', trim($svg));
    $uri = 'data:image/svg+xml,' . rawurlencode($svg);
    $out .= '.ts-icon-' . $name . '{--ts-icon:url("' . $uri . '")}' . "\n";
}

@file_put_contents($generatedFile, $out, LOCK_EX);
echo $out;
