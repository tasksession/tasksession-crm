<?php
/**
 * Shared activity feed helpers (admin dashboard widget + root activity page).
 */
require_once __DIR__ . '/dashboard_empty_state.php';
require_once __DIR__ . '/invoice_display_helpers.php';
require_once __DIR__ . '/milestone_invoice_total.php';

if (!function_exists('ts_icon') && is_file(__DIR__ . '/icon.php')) {
    require_once __DIR__ . '/icon.php';
}

/**
 * Task activity feed / notification badge icon.
 */
function adminDashTaskFeedIcon(): string
{
    $icon = function_exists('ts_icon') ? ts_icon('tasks-sidebar', 'h-6') : '';
    if ($icon === '') {
        return '<span class="dash-activity-icon-badge dash-activity-icon-badge--task" aria-hidden="true">'
            . '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 21 24" stroke-width="1.2" stroke="currentColor" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"></path></svg>'
            . '</span>';
    }

    return '<span class="dash-activity-icon-badge dash-activity-icon-badge--task" aria-hidden="true">' . $icon . '</span>';
}

/**
 * Marketing campaign activity / notification badge icon.
 */
function adminDashMarketingFeedIcon(): string
{
    $icon = function_exists('ts_icon') ? ts_icon('marketing', 'h-6') : '';
    if ($icon === '') {
        return '<span class="dash-activity-icon-badge dash-activity-icon-badge--marketing" aria-hidden="true">'
            . '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 16 16" stroke="currentColor" stroke-width="1.2" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" d="M14.667 1.333 7.333 8.667m7.334-7.334L10 14.667l-2.667-6-6-2.667 13.334-4.667z"/></svg>'
            . '</span>';
    }

    return '<span class="dash-activity-icon-badge dash-activity-icon-badge--marketing" aria-hidden="true">' . $icon . '</span>';
}

/**
 * AI agent activity / notification badge icon.
 */
function adminDashAiFeedIcon(): string
{
    $icon = function_exists('ts_icon') ? ts_icon('sparkles', 'h-6') : '';
    if ($icon === '') {
        return '<span class="dash-activity-icon-badge dash-activity-icon-badge--ai" aria-hidden="true">'
            . '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" d="M10.2 16.8 9.25 20.1l-.95-3.3A5.1 5.1 0 0 0 4.8 13.3L1.5 12.35l3.3-.95A5.1 5.1 0 0 0 8.3 7.9L9.25 4.6l.95 3.3A5.1 5.1 0 0 0 13.7 11.4l3.3.95-3.3.95a5.1 5.1 0 0 0-3.5 3.5Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M18.55 8.35 18.25 9.55l-.3-1.2a3.6 3.6 0 0 0-2.55-2.55l-1.2-.3 1.2-.3a3.6 3.6 0 0 0 2.55-2.55l.3-1.2.3 1.2a3.6 3.6 0 0 0 2.55 2.55l1.2.3-1.2.3a3.6 3.6 0 0 0-2.55 2.55Z"/></svg>'
            . '</span>';
    }

    return '<span class="dash-activity-icon-badge dash-activity-icon-badge--ai" aria-hidden="true">' . $icon . '</span>';
}

/**
 * Whether a notification belongs to the AI agents feed.
 */
function adminDashIsAiAgentNotification(object $n): bool
{
    $type = (string) ($n->type ?? '');
    $relatedType = strtolower(trim((string) ($n->related_type ?? '')));

    return strpos($type, 'ai_agent_') === 0 || $relatedType === 'ai_agent';
}

/**
 * Nav target for AI agent notifications (site-root paths: ai/*, mail/*).
 *
 * @return array{href?:string}
 */
function adminDashActivityNavFromAiNotification(object $n): array
{
    $type = (string) ($n->type ?? '');
    $relatedId = (int) ($n->related_id ?? 0);

    if ($type === 'ai_agent_auto_reply') {
        $accountId = 0;
        $threadId = '';
        if ($relatedId > 0 && isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli) {
            $mq = @$GLOBALS['connect']->query(
                'SELECT account_id, thread_id FROM email_messages WHERE id = ' . $relatedId . ' LIMIT 1'
            );
            if ($mq && ($mr = $mq->fetch_assoc())) {
                $accountId = (int) ($mr['account_id'] ?? 0);
                $threadId = trim((string) ($mr['thread_id'] ?? ''));
                if ($threadId === '') {
                    $threadId = (string) $relatedId;
                }
            }
            if ($mq) {
                $mq->free();
            }
        }
        if ($accountId > 0 && $threadId !== '') {
            return [
                'href' => 'mail/inbox?account=' . $accountId
                    . '&folder=inbox&thread=' . rawurlencode($threadId),
            ];
        }
        if ($accountId > 0) {
            return ['href' => 'mail/inbox?account=' . $accountId . '&folder=inbox'];
        }

        return ['href' => 'ai/agents'];
    }

    if ($type === 'ai_agent_digest') {
        return ['href' => 'ai/agents/alerts'];
    }

    $agentType = '';
    if ($relatedId > 0) {
        if (!function_exists('ai_agent_notify_alert_by_id')) {
            $notifyPath = __DIR__ . '/../ai/includes/agent-notify.php';
            if (is_readable($notifyPath)) {
                require_once $notifyPath;
            }
        }
        if (function_exists('ai_agent_notify_alert_by_id')) {
            $alertRow = ai_agent_notify_alert_by_id($relatedId);
            if (is_array($alertRow)) {
                $agentType = preg_replace('/[^a-z_]/', '', (string) ($alertRow['agent_type'] ?? ''));
            }
        }
    }
    if ($agentType !== '') {
        return ['href' => 'ai/agents?agent=' . rawurlencode($agentType)];
    }

    return ['href' => 'ai/agents/alerts'];
}

/**
 * Notification-style feed icon (matches assets/js/notifications.js dropdown icons).
 */
function adminDashNotifIcon(string $type): string
{
    $wrap = static function (string $bgColor, string $svg): string {
        return '<span style="background:' . $bgColor . ';border-radius:50%;display:flex;align-items:center;justify-content:center;width:40px;height:40px;flex-shrink:0;">'
            . $svg
            . '</span>';
    };

    switch ($type) {
        case 'ecommerce':
            return $wrap(
                '#fff8e1',
                '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#f57c00" width="28" height="28"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" /></svg>'
            );
        case 'invoice':
            return $wrap(
                '#e6f9ed',
                '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.2" stroke="#43a047" width="28" height="28"><path d="M20.016 2C18.903 2 18 4.686 18 8h2.016c.972 0 1.457 0 1.758-.335c.3-.336.248-.778.144-1.661C21.64 3.67 20.894 2 20.016 2"></path><path d="M18 8.054v10.592c0 1.511 0 2.267-.462 2.565c-.755.486-1.922-.534-2.509-.904c-.485-.306-.727-.458-.996-.467c-.291-.01-.538.137-1.062.467l-1.911 1.205c-.516.325-.773.488-1.06.488s-.545-.163-1.06-.488l-1.91-1.205c-.486-.306-.728-.458-.997-.467c-.587.37-1.754 1.39-2.51.904C2 20.913 2 20.158 2 18.646V8.054c0-2.854 0-4.28.879-5.167C3.757 2 5.172 2 8 2h12"></path><path d="M10 8c-1.105 0-2 .672-2 1.5s.895 1.5 2 1.5s2 .672 2 1.5s-.895 1.5-2 1.5m0-6c.87 0 1.612.417 1.886 1M10 8V7m0 7c-.87 0-1.612-.417-1.886-1M10 14v1"></path></svg>'
            );
        case 'task':
            return adminDashTaskFeedIcon();
        case 'marketing':
            return adminDashMarketingFeedIcon();
        case 'client':
            return $wrap(
                '#e3f0ff',
                '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#2196f3" width="28" height="28"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"></path></svg>'
            );
        case 'project':
            return $wrap(
                '#e3f0ff',
                '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#2196f3" width="28" height="28"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"></path></svg>'
            );
        case 'lead':
            return $wrap(
                '#ede7f6',
                '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#673ab7" width="28" height="28"><circle cx="12" cy="12" r="3"/><circle cx="12" cy="12" r="8"/><path stroke-linecap="round" d="M12 2v4M12 18v4M2 12h4M18 12h4"/></svg>'
            );
        case 'attendance':
            return $wrap(
                '#e0f2f1',
                '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#00897b" width="28" height="28"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>'
            );
        case 'ai':
            return adminDashAiFeedIcon();
        default:
            return $wrap(
                '#e0e0e0',
                '<svg width="28" height="28" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="16" cy="16" r="16" fill="#e0e0e0"/><path d="M10 22V10h12v12H10zm2-2h8V12h-8v8z" fill="#757575"/></svg>'
            );
    }
}

/**
 * Whether a notification belongs to the central ecommerce module.
 */
function adminDashIsEcommerceNotification(object $n): bool
{
    $type = strtolower(trim((string) ($n->type ?? '')));
    $relatedType = strtolower(trim((string) ($n->related_type ?? '')));
    if (strpos($type, 'ecommerce') === 0 || strpos($relatedType, 'ecommerce') === 0) {
        return true;
    }
    $blob = trim((string) ($n->message ?? '') . ' ' . (string) ($n->title ?? ''));
    return stripos($blob, 'WooCommerce') !== false;
}

/**
 * Map notification type to dashboard feed icon bucket.
 */
function adminDashNotifIconTypeFromNotification(string $type, string $relatedType = ''): string
{
    $type = strtolower(trim($type));
    $relatedType = strtolower(trim($relatedType));
    if (strpos($type, 'invoice') === 0) {
        return 'invoice';
    }
    if (strpos($type, 'task') === 0 || strpos($type, 'subtask_') === 0) {
        return 'task';
    }
    if (strpos($type, 'marketing_campaign_') === 0 || $type === 'marketing_campaign' || $relatedType === 'marketing_campaign') {
        return 'marketing';
    }
    if (strpos($type, 'project') === 0 || strpos($type, 'media_') === 0) {
        return 'project';
    }
    if (strpos($type, 'lead') === 0) {
        return 'lead';
    }
    if (strpos($type, 'attendance_') === 0) {
        return 'attendance';
    }
    if (strpos($type, 'ecommerce') === 0 || strpos($relatedType, 'ecommerce') === 0) {
        return 'ecommerce';
    }
    if (strpos($type, 'ai_agent_') === 0 || $relatedType === 'ai_agent') {
        return 'ai';
    }

    return 'default';
}

/**
 * Role folder prefix for resolving notification links from the site root.
 */
function activityPageRoleBase(int $accountStatus): string
{
    if ($accountStatus === 1) {
        return 'admin/';
    }
    if ($accountStatus === 3) {
        return 'staff/';
    }
    if ($accountStatus === 2) {
        return 'client/';
    }

    return 'admin/';
}

/**
 * Prefix relative notification nav targets for root-level pages.
 * Central modules (ecommerce/marketing/ai/mail/vendor) must never get admin|staff|client/.
 */
function activityPageResolveHref(string $href, string $roleBase, string $siteUrl): string
{
    $href = trim($href);
    if ($href === '' || $href === '#') {
        return $href;
    }
    if (preg_match('#^(https?:)?//#i', $href)) {
        if (function_exists('tasksession_pretty_redirect_location')) {
            $prettyAbs = tasksession_pretty_redirect_location($href);
            if (is_string($prettyAbs) && $prettyAbs !== '') {
                return $prettyAbs;
            }
        }
        return $href;
    }

    // Root-absolute (/ecommerce/...) and legacy role-prefixed central paths.
    $href = ltrim(str_replace('\\', '/', $href), '/');
    if (preg_match('#^(?:admin|staff|client)/(ecommerce|marketing|vendor|ai|mail)/(.*)$#i', $href, $m)) {
        $href = strtolower($m[1]) . '/' . $m[2];
    }

    $isCentral = (bool) preg_match('#^(ecommerce|marketing|vendor|ai|mail)/#i', $href);
    $rel = $isCentral ? $href : ($roleBase . $href);
    if (function_exists('tasksession_pretty_path')) {
        $rel = tasksession_pretty_path($rel);
    }

    return rtrim($siteUrl, '/') . '/' . ltrim($rel, '/');
}

/**
 * Map notification type to activity page filter chip bucket.
 */
function activityPageFilterType(string $notifType): string
{
    if (strpos($notifType, 'invoice') === 0) {
        return 'invoice';
    }
    if (strpos($notifType, 'task') === 0 || strpos($notifType, 'subtask_') === 0) {
        return 'task';
    }
    if (strpos($notifType, 'project') === 0) {
        return 'project';
    }
    if (strpos($notifType, 'media_') === 0 || $notifType === 'project_media_file_uploaded') {
        return 'file';
    }
    if (strpos($notifType, 'lead') === 0) {
        return 'client';
    }
    if (strpos($notifType, 'ecommerce') === 0) {
        return 'all';
    }
    if (strpos($notifType, 'ai_agent_') === 0) {
        return 'ai';
    }

    return 'all';
}

/**
 * Date range metadata for the activity page header.
 *
 * @return array{label:string,start:string,end:string,start_label:string,end_label:string}
 */
function activityPageDateRangeLabel(int $days = 60): array
{
    $end = new DateTime('today');
    $start = (clone $end)->modify('-' . max(1, $days) . ' days');

    return [
        'label' => (string) $days,
        'start' => $start->format('Y-m-d'),
        'end' => $end->format('Y-m-d'),
        'start_label' => $start->format('M j'),
        'end_label' => $end->format('M j, Y'),
    ];
}

/**
 * Keep notifications within the last N days.
 *
 * @param array<int, object> $notifications
 * @return array<int, object>
 */
function activityPageFilterNotificationsByDays(array $notifications, int $days = 60): array
{
    $cutoffTs = strtotime('-' . max(1, $days) . ' days');
    if ($cutoffTs === false) {
        return $notifications;
    }

    $filtered = [];
    foreach ($notifications as $notification) {
        $createdAt = (string) ($notification->created_at ?? '');
        if ($createdAt === '') {
            continue;
        }
        $createdTs = strtotime($createdAt);
        if ($createdTs !== false && $createdTs >= $cutoffTs) {
            $filtered[] = $notification;
        }
    }

    return $filtered;
}

/**
 * Resolve click target for a notification row (mirrors notifications.js navigation).
 *
 * @return array{href?:string,task_id?:int}
 */
function adminDashActivityNavFromNotification(object $n): array
{
    $type = (string) ($n->type ?? '');
    $relatedId = (int) ($n->related_id ?? 0);
    $relatedType = strtolower((string) ($n->related_type ?? ''));
    $projectId = (int) ($n->related_project_id ?? 0);
    $userId = (int) ($n->user_id ?? 0);

    if (adminDashIsAiAgentNotification($n)) {
        return adminDashActivityNavFromAiNotification($n);
    }
    if (strpos($type, 'marketing_campaign_') === 0) {
        return ['href' => $relatedId > 0 ? 'marketing/report?id=' . $relatedId : 'marketing/campaigns'];
    }
    if (adminDashIsEcommerceNotification($n) || $relatedType === 'ecommerce_order' || $relatedType === 'ecommerce_orders' || strpos($type, 'ecommerce') === 0) {
        if ($relatedType === 'ecommerce_order' && $relatedId > 0) {
            return ['href' => 'ecommerce/order-view?id=' . $relatedId . '&return=orders'];
        }
        return ['href' => 'ecommerce/orders'];
    }
    if ($relatedType === 'lead' || strpos($type, 'lead_') === 0) {
        return ['href' => $relatedId > 0 ? 'leads?open_lead=' . $relatedId : 'leads'];
    }
    if (strpos($type, 'attendance_leave') === 0 || $relatedType === 'attendance_leave') {
        return ['href' => 'attendance-leave'];
    }
    if (strpos($type, 'attendance_regularization') === 0 || $relatedType === 'attendance_regularization') {
        return ['href' => 'attendance-regularization'];
    }
    if ($type === 'media_folder_shared' || $type === 'media_shared_folder_new_upload') {
        if ($projectId > 0) {
            $href = 'media?projectId=' . $projectId;
            if ($relatedId > 0) {
                $href .= '&folder=' . $relatedId;
            }
            return ['href' => $href];
        }
        $href = 'media-vault';
        if ($relatedId > 0) {
            $href .= '?folder=' . $relatedId;
        }
        return ['href' => $href];
    }
    if ($type === 'media_file_shared') {
        return ['href' => $projectId > 0 ? 'media?projectId=' . $projectId : 'media-vault'];
    }
    if ($type === 'media_vault_extended_share') {
        if ($relatedType === 'mv_profile_share' && $userId > 0) {
            $href = 'profile?user_id=' . $userId . '&tab=media&view=grid&page=1';
            if ($relatedId > 0) {
                $href .= '&folder=' . $relatedId;
            }
            return ['href' => $href];
        }
        if ($relatedType === 'mv_project_link' && $projectId > 0) {
            return ['href' => 'media?projectId=' . $projectId];
        }
        return ['href' => 'media-vault'];
    }

    switch ($type) {
        case 'project_created':
        case 'project_updated':
            if ($relatedId > 0) {
                return ['href' => 'overview?projectId=' . $relatedId];
            }
            break;
        case 'task_reminder_1':
        case 'task_reminder_2':
        case 'task_reminder_3':
            if ($projectId > 0) {
                return ['href' => 'overview?projectId=' . $projectId];
            }
            if ($relatedId > 0) {
                return ['task_id' => $relatedId];
            }
            break;
        case 'task_created':
        case 'task_updated':
        case 'task_status_changed':
        case 'subtask_created':
        case 'subtask_completed':
            if ($relatedId > 0) {
                return ['task_id' => $relatedId];
            }
            break;
        case 'invoice_created':
        case 'invoice_updated':
        case 'invoice_paid':
        case 'invoice_not_clear':
            if ($relatedType === 'invoice' && $relatedId > 0) {
                return ['href' => 'invoices?id=' . $relatedId];
            }
            if ($projectId > 0) {
                return ['href' => 'payments?projectId=' . $projectId];
            }
            return ['href' => 'invoices'];
        case 'project_media_file_uploaded':
            if ($projectId > 0) {
                $href = 'media?projectId=' . $projectId;
                if ($relatedId > 0) {
                    $href .= '&folder=' . $relatedId . '&page=1';
                }
                return ['href' => $href];
            }
            if ($relatedId > 0) {
                return ['href' => 'media?projectId=' . $relatedId];
            }
            return ['href' => 'media-vault'];
    }

    if ($relatedType === 'task' && $relatedId > 0) {
        return ['task_id' => $relatedId];
    }
    if ($relatedType === 'invoice' && $relatedId > 0) {
        return ['href' => 'invoices?id=' . $relatedId];
    }
    if ($relatedType === 'project' && $relatedId > 0) {
        return ['href' => 'overview?projectId=' . $relatedId];
    }

    return [];
}

/**
 * Context menu actions for a dashboard activity row.
 *
 * @return array<int, array{label:string,href:string,icon?:string,class?:string,task_id?:int}>
 */
function adminDashActivityMenuActionsFromNotification(object $n, array $lang): array
{
    $type = (string) ($n->type ?? '');
    $relatedId = (int) ($n->related_id ?? 0);
    $relatedType = strtolower((string) ($n->related_type ?? ''));
    $projectId = (int) ($n->related_project_id ?? 0);
    $menu = [];

    $addLink = static function (string $label, string $href, string $icon = 'eye') use (&$menu): void {
        if ($href === '') {
            return;
        }
        $menu[] = ['label' => $label, 'href' => $href, 'icon' => $icon];
    };

    $addTaskView = static function (int $taskId) use (&$menu, $lang): void {
        if ($taskId <= 0) {
            return;
        }
        $menu[] = [
            'label' => $lang['View Task'] ?? 'View Task',
            'href' => '#',
            'icon' => 'eye',
            'class' => 'view-task-btn',
            'task_id' => $taskId,
        ];
    };

    if (strpos($type, 'invoice') === 0 || $relatedType === 'invoice') {
        if ($relatedId > 0) {
            $addLink($lang['View Invoice'] ?? 'View Invoice', 'invoices?id=' . $relatedId, 'eye');
        }
        if ($projectId > 0) {
            $addLink($lang['View Project'] ?? 'View Project', 'overview?projectId=' . $projectId, 'archive');
        }
        return $menu;
    }

    if (strpos($type, 'task') === 0 || strpos($type, 'subtask_') === 0 || $relatedType === 'task') {
        $addTaskView($relatedId);
        if ($relatedId > 0) {
            $addLink($lang['Edit Task'] ?? 'Edit Task', 'edit_task?id=' . $relatedId, 'edit');
        }
        if ($projectId > 0) {
            $addLink($lang['View Project'] ?? 'View Project', 'overview?projectId=' . $projectId, 'archive');
        }
        return $menu;
    }

    if ($type === 'project_media_file_uploaded' || strpos($type, 'media_') === 0) {
        $nav = adminDashActivityNavFromNotification($n);
        if (!empty($nav['href'])) {
            $addLink($lang['View Media'] ?? 'View media', (string) $nav['href'], 'eye');
        }
        if ($projectId > 0) {
            $addLink($lang['View Project'] ?? 'View Project', 'overview?projectId=' . $projectId, 'archive');
        }
        return $menu;
    }

    if ($type === 'project_created' || $type === 'project_updated' || strpos($type, 'project') === 0 || $relatedType === 'project') {
        $pid = $relatedId > 0 ? $relatedId : $projectId;
        if ($pid > 0) {
            $addLink($lang['View Project'] ?? 'View Project', 'overview?projectId=' . $pid, 'archive');
        }
        return $menu;
    }

    if (strpos($type, 'lead') === 0 || $relatedType === 'lead') {
        $addLink($lang['View Lead'] ?? 'View lead', $relatedId > 0 ? 'leads?open_lead=' . $relatedId : 'leads', 'eye');
        return $menu;
    }

    if (strpos($type, 'marketing_campaign_') === 0) {
        $addLink($lang['View Report'] ?? 'View report', $relatedId > 0 ? 'marketing/report?id=' . $relatedId : 'marketing/campaigns', 'eye');
        return $menu;
    }

    if (adminDashIsEcommerceNotification($n) || strpos($type, 'ecommerce') === 0 || $relatedType === 'ecommerce_order' || $relatedType === 'ecommerce_orders') {
        if ($relatedType === 'ecommerce_order' && $relatedId > 0) {
            $addLink(
                $lang['ecommerce_view_order'] ?? 'View order',
                'ecommerce/order-view?id=' . $relatedId . '&return=orders',
                'eye'
            );
        } else {
            $addLink($lang['View Orders'] ?? 'View orders', 'ecommerce/orders', 'eye');
        }
        return $menu;
    }

    if (strpos($type, 'attendance_leave') === 0 || $relatedType === 'attendance_leave') {
        $addLink($lang['View Leave'] ?? 'View leave', 'attendance-leave', 'eye');
        return $menu;
    }

    if (strpos($type, 'attendance_regularization') === 0 || $relatedType === 'attendance_regularization') {
        $addLink($lang['View Regularization'] ?? 'View regularization', 'attendance-regularization', 'eye');
        return $menu;
    }

    if (adminDashIsAiAgentNotification($n)) {
        $nav = adminDashActivityNavFromAiNotification($n);
        $href = (string) ($nav['href'] ?? '');
        $type = (string) ($n->type ?? '');
        if ($type === 'ai_agent_auto_reply') {
            $addLink($lang['View Email'] ?? 'View email', $href !== '' ? $href : 'ai/agents', 'eye');
        } elseif ($type === 'ai_agent_digest') {
            $addLink($lang['View Alerts'] ?? 'View alerts', $href !== '' ? $href : 'ai/agents/alerts', 'eye');
        } else {
            $addLink($lang['View Agent'] ?? 'View agent', $href !== '' ? $href : 'ai/agents', 'eye');
            $addLink($lang['View Alerts'] ?? 'View alerts', 'ai/agents/alerts', 'bell');
        }
        return $menu;
    }

    $nav = adminDashActivityNavFromNotification($n);
    if (!empty($nav['task_id'])) {
        $addTaskView((int) $nav['task_id']);
        if ((int) $nav['task_id'] > 0) {
            $addLink($lang['Edit Task'] ?? 'Edit Task', 'edit_task?id=' . (int) $nav['task_id'], 'edit');
        }
        if ($projectId > 0) {
            $addLink($lang['View Project'] ?? 'View Project', 'overview?projectId=' . $projectId, 'archive');
        }
    } elseif (!empty($nav['href'])) {
        $addLink($lang['View'] ?? 'View', (string) $nav['href'], 'eye');
    }

    return $menu;
}

/**
 * Render three-dots menu for an activity row.
 */
function adminDashRenderActivityMenu(array $menu, array $lang = []): void
{
    if ($menu === []) {
        return;
    }
    ?>
    <div class="dropdown ms-2 dash-activity-menu">
        <button class="btn-dots" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?php echo htmlspecialchars($lang['Actions'] ?? 'Actions', ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo ts_icon('dots-vertical'); ?>
        </button>
        <ul class="dropdown-menu">
            <?php foreach ($menu as $action) :
                $actionClass = trim((string) ($action['class'] ?? ''));
                $actionHref = (string) ($action['href'] ?? '#');
                $actionIcon = (string) ($action['icon'] ?? 'eye');
                $actionTaskId = (int) ($action['task_id'] ?? 0);
                ?>
            <li>
                <a class="dropdown-item<?php echo $actionClass !== '' ? ' ' . htmlspecialchars($actionClass, ENT_QUOTES, 'UTF-8') : ''; ?>"
                   href="<?php echo htmlspecialchars($actionHref, ENT_QUOTES, 'UTF-8'); ?>"
                   <?php if ($actionTaskId > 0) : ?>data-task-id="<?php echo $actionTaskId; ?>"<?php endif; ?>>
                    <?php echo ts_icon($actionIcon, 'tasksession-timer-log-menu-ico me-2'); ?><?php echo htmlspecialchars((string) ($action['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
}

/**
 * Inline bold helper for activity titles.
 */
function adminDashActivityBold(string $text): string
{
    return '<strong>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</strong>';
}

/**
 * Build activity copy in dashboard sentence style (not notification dropdown layout).
 *
 * @return array{title_html:string,meta:string}
 */
function adminDashActivityCopyFromNotification(object $n, array $lang): array
{
    $message = trim((string) ($n->message ?? ''));
    $timeAgo = method_exists($n, 'getTimeAgo') ? (string) $n->getTimeAgo() : '';
    $relatedId = (int) ($n->related_id ?? 0);
    $relatedType = strtolower((string) ($n->related_type ?? ''));
    $projectId = (int) ($n->related_project_id ?? 0);
    if ($projectId <= 0 && $relatedType === 'project' && $relatedId > 0) {
        $projectId = $relatedId;
    }

    static $projectTitleCache = [];
    static $invoiceMetaCache = [];
    static $leadNameCache = [];

    $getProjectTitle = static function (int $pid) use ($lang, &$projectTitleCache): string {
        if ($pid <= 0) {
            return '';
        }
        if (array_key_exists($pid, $projectTitleCache)) {
            return $projectTitleCache[$pid];
        }
        $project = projects::findByProjectId($pid);
        if (!$project) {
            $projectTitleCache[$pid] = '';
            return '';
        }
        $title = trim((string) ($project->project_title ?? ''));
        $projectTitleCache[$pid] = $title !== '' ? $title : ($lang['Internal'] ?? 'Internal');

        return $projectTitleCache[$pid];
    };

    $quoteBold = static function (string $text): string {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        return adminDashActivityBold('"' . $text . '"');
    };

    $metaContext = $getProjectTitle($projectId);
    $titleHtml = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    if (adminDashIsAiAgentNotification($n)) {
        $notifTitle = trim((string) ($n->title ?? ''));
        $lines = preg_split('/\r?\n/', $message) ?: [];
        $line1 = trim((string) ($lines[0] ?? ''));
        $line2 = trim((string) ($lines[1] ?? ''));
        if ($notifTitle !== '') {
            $titleHtml = htmlspecialchars($notifTitle, ENT_QUOTES, 'UTF-8');
            $metaContext = $line2 !== '' ? $line2 : $line1;
        } elseif ($line1 !== '') {
            $titleHtml = htmlspecialchars($line1, ENT_QUOTES, 'UTF-8');
            $metaContext = $line2;
        }
        $meta = $timeAgo;
        if ($metaContext !== '') {
            $meta = $meta !== '' ? $timeAgo . ' · ' . $metaContext : $metaContext;
        }

        return [
            'title_html' => $titleHtml,
            'meta' => $meta,
        ];
    }

    if (adminDashIsEcommerceNotification($n)) {
        $notifTitle = trim((string) ($n->title ?? ''));
        $lines = preg_split('/\r?\n/', $message) ?: [];
        $line1 = trim((string) ($lines[0] ?? ''));
        $line2 = trim((string) ($lines[1] ?? ''));
        // Batch cards store "WooCommerce • Waiting for processing" as the message.
        if ($line1 !== '' && stripos($line1, 'WooCommerce') !== false) {
            $titleHtml = htmlspecialchars($line1, ENT_QUOTES, 'UTF-8');
            $metaContext = '';
        } elseif ($notifTitle !== '' && $notifTitle !== '-') {
            $titleHtml = htmlspecialchars($notifTitle, ENT_QUOTES, 'UTF-8');
            $metaContext = $line2 !== '' ? $line1 . ($line1 !== '' ? ' · ' : '') . $line2 : $line1;
        } elseif ($line1 !== '') {
            $titleHtml = htmlspecialchars($line1, ENT_QUOTES, 'UTF-8');
            $metaContext = $line2;
        }
        $meta = $timeAgo;
        if ($metaContext !== '') {
            $meta = $meta !== '' ? $timeAgo . ' · ' . $metaContext : $metaContext;
        }

        return [
            'title_html' => $titleHtml,
            'meta' => $meta,
        ];
    }

    if (preg_match('/^Invoice #(.+?) has been paid$/i', $message, $matches)) {
        $titleHtml = ($lang['Invoice'] ?? 'Invoice') . ' ' . adminDashActivityBold('#' . trim($matches[1]))
            . ' ' . strtolower($lang['Paid'] ?? 'paid');
        $metaContext = '';
        if ($relatedType === 'invoice' && $relatedId > 0) {
            if (!array_key_exists($relatedId, $invoiceMetaCache)) {
                $invoiceMetaCache[$relatedId] = '';
                $invoiceRow = milestone::findById($relatedId);
                if ($invoiceRow) {
                    $amount = milestone_calculate_invoice_total($invoiceRow);
                    $symbol = getCurrencySymbol((string) ($invoiceRow->currency ?? ''));
                    $invoiceMetaCache[$relatedId] = $symbol . number_format($amount, 2);
                }
            }
            $metaContext = $invoiceMetaCache[$relatedId];
        }
    } elseif (preg_match('/^(.+?) created new invoice #(.+)$/i', $message, $matches)) {
        $titleHtml = htmlspecialchars(trim($matches[1]), ENT_QUOTES, 'UTF-8') . ' created invoice '
            . adminDashActivityBold('#' . trim($matches[2]));
    } elseif (preg_match('/^(.+?) updated invoice #(.+)$/i', $message, $matches)) {
        $titleHtml = htmlspecialchars(trim($matches[1]), ENT_QUOTES, 'UTF-8') . ' updated invoice '
            . adminDashActivityBold('#' . trim($matches[2]));
    } elseif (preg_match('/^(.+?) changed task "(.+?)" status to (.+)$/i', $message, $matches)) {
        $user = trim($matches[1]);
        $task = trim($matches[2]);
        $status = strtolower(trim($matches[3]));
        if (in_array($status, ['done', 'completed', 'complete'], true)) {
            $titleHtml = htmlspecialchars($user, ENT_QUOTES, 'UTF-8') . ' completed ' . $quoteBold($task);
        } else {
            $titleHtml = htmlspecialchars($user, ENT_QUOTES, 'UTF-8') . ' updated ' . $quoteBold($task);
        }
    } elseif (preg_match('/^(.+?) created new task "(.+?)"$/i', $message, $matches)) {
        $titleHtml = htmlspecialchars(trim($matches[1]), ENT_QUOTES, 'UTF-8') . ' created ' . $quoteBold(trim($matches[2]));
    } elseif (preg_match('/^(.+?) updated task "(.+?)"$/i', $message, $matches)) {
        $titleHtml = htmlspecialchars(trim($matches[1]), ENT_QUOTES, 'UTF-8') . ' updated ' . $quoteBold(trim($matches[2]));
    } elseif (preg_match('/^(.+?) completed sub-task "(.+?)" on task "(.+?)"$/i', $message, $matches)) {
        $titleHtml = htmlspecialchars(trim($matches[1]), ENT_QUOTES, 'UTF-8') . ' completed ' . $quoteBold(trim($matches[2]));
    } elseif (preg_match('/^(.+?) assigned you a sub-task "(.+?)" on task "(.+?)"$/i', $message, $matches)) {
        $titleHtml = htmlspecialchars(trim($matches[1]), ENT_QUOTES, 'UTF-8') . ' assigned ' . $quoteBold(trim($matches[2]));
    } elseif (preg_match('/^(.+?) created new project "(.+?)"$/i', $message, $matches)) {
        $titleHtml = htmlspecialchars(trim($matches[1]), ENT_QUOTES, 'UTF-8') . ' created project '
            . adminDashActivityBold(trim($matches[2]));
        $metaContext = '';
    } elseif (preg_match('/^(.+?) updated project "(.+?)"$/i', $message, $matches)) {
        $titleHtml = htmlspecialchars(trim($matches[1]), ENT_QUOTES, 'UTF-8') . ' updated project '
            . adminDashActivityBold(trim($matches[2]));
        $metaContext = '';
    } elseif (preg_match('/^(.+?) uploaded a file to project media$/i', $message, $matches)) {
        $projectTitle = $getProjectTitle($projectId);
        $uploadCount = (int) ($n->upload_count ?? 1);
        $actor = htmlspecialchars(trim($matches[1]), ENT_QUOTES, 'UTF-8');
        if ($projectTitle !== '') {
            if ($uploadCount > 1) {
                $titleHtml = $actor . ' uploaded ' . $uploadCount . ' files to ' . adminDashActivityBold($projectTitle);
            } else {
                $titleHtml = $actor . ' uploaded media to ' . adminDashActivityBold($projectTitle);
            }
        } else {
            $titleHtml = $actor . ' uploaded project media';
        }
        $metaContext = '';
    } elseif (preg_match('/^A new lead was created from (.+)\.$/i', $message, $matches)) {
        $source = trim($matches[1]);
        $leadName = '';
        if ($relatedId > 0) {
            if (!array_key_exists($relatedId, $leadNameCache)) {
                $leadNameCache[$relatedId] = '';
                if (isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli) {
                    $stmt = $GLOBALS['connect']->prepare('SELECT company, name FROM leads WHERE id = ? LIMIT 1');
                    if ($stmt) {
                        $stmt->bind_param('i', $relatedId);
                        $stmt->execute();
                        $leadRes = $stmt->get_result();
                        $leadRow = $leadRes ? $leadRes->fetch_assoc() : null;
                        $stmt->close();
                        if ($leadRow) {
                            $name = trim((string) ($leadRow['company'] ?? ''));
                            if ($name === '') {
                                $name = trim((string) ($leadRow['name'] ?? ''));
                            }
                            $leadNameCache[$relatedId] = $name;
                        }
                    }
                }
            }
            $leadName = $leadNameCache[$relatedId];
        }
        if ($leadName !== '') {
            $titleHtml = ($lang['New lead'] ?? 'New lead') . ' ' . adminDashActivityBold($leadName)
                . ' from ' . htmlspecialchars($source, ENT_QUOTES, 'UTF-8');
        } else {
            $titleHtml = ($lang['New lead'] ?? 'New lead') . ' from ' . htmlspecialchars($source, ENT_QUOTES, 'UTF-8');
        }
        $metaContext = '';
    } elseif (preg_match('/^Task "(.+?)" is due today$/i', $message, $matches)) {
        $titleHtml = ($lang['Task due today'] ?? 'Task due today') . ' · ' . $quoteBold(trim($matches[1]));
    } elseif (preg_match('/^Task "(.+?)" is (\d+) day/i', $message, $matches)) {
        $titleHtml = $quoteBold(trim($matches[1])) . ' ' . ($lang['needs attention'] ?? 'needs attention');
    } elseif (preg_match('/"([^"]+)"/', $message, $matches)) {
        $quoted = trim($matches[1]);
        $escapedMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $titleHtml = str_replace(
            htmlspecialchars('"' . $quoted . '"', ENT_QUOTES, 'UTF-8'),
            adminDashActivityBold('"' . $quoted . '"'),
            $escapedMessage
        );
    } elseif (preg_match('/#(\S+)/', $message, $matches)) {
        $escapedMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $titleHtml = preg_replace(
            '/#' . preg_quote($matches[1], '/') . '/',
            adminDashActivityBold('#' . $matches[1]),
            $escapedMessage,
            1
        ) ?? $escapedMessage;
    }

    $meta = $timeAgo;
    if ($metaContext !== '') {
        $meta = $meta !== '' ? $timeAgo . ' · ' . $metaContext : $metaContext;
    }

    return [
        'title_html' => $titleHtml,
        'meta' => $meta,
    ];
}

/**
 * Build one dashboard feed row from a notification object.
 *
 * @return array<string,mixed>
 */
function adminDashFeedItemFromNotification(object $n, array $lang = []): array
{
    $type = (string) ($n->type ?? '');
    $relatedType = (string) ($n->related_type ?? '');
    $copy = adminDashActivityCopyFromNotification($n, $lang);

    $notifType = adminDashNotifIconTypeFromNotification($type, $relatedType);
    if ($notifType === 'default' && adminDashIsEcommerceNotification($n)) {
        $notifType = 'ecommerce';
    }

    $item = [
        'notif_type' => $notifType,
        'title_html' => $copy['title_html'],
        'meta' => $copy['meta'],
        'menu' => adminDashActivityMenuActionsFromNotification($n, $lang),
    ];

    $nav = adminDashActivityNavFromNotification($n);
    if (!empty($nav['task_id'])) {
        $item['task_id'] = (int) $nav['task_id'];
    } elseif (!empty($nav['href'])) {
        $item['href'] = (string) $nav['href'];
    }

    return $item;
}

/**
 * Apply role-prefixed URLs to a feed item for root activity page links.
 *
 * @param array<string,mixed> $item
 * @return array<string,mixed>
 */
function activityPageApplyRoleToFeedItem(array $item, string $roleBase, string $siteUrl): array
{
    if (!empty($item['href'])) {
        $item['href'] = activityPageResolveHref((string) $item['href'], $roleBase, $siteUrl);
    }

    if (!empty($item['menu']) && is_array($item['menu'])) {
        foreach ($item['menu'] as $idx => $action) {
            if (!is_array($action)) {
                continue;
            }
            $href = (string) ($action['href'] ?? '');
            $taskId = (int) ($action['task_id'] ?? 0);
            if ($href !== '' && $href !== '#' && $taskId <= 0) {
                $item['menu'][$idx]['href'] = activityPageResolveHref($href, $roleBase, $siteUrl);
            }
        }
    }

    return $item;
}

/**
 * Build feed items for the activity page with filter metadata and resolved links.
 *
 * @param array<int, object> $notifications
 * @return array<int, array<string,mixed>>
 */
function activityPageItemsFromNotifications(array $notifications, array $lang, string $roleBase, string $siteUrl): array
{
    $items = [];
    foreach ($notifications as $notification) {
        $item = adminDashFeedItemFromNotification($notification, $lang);
        $item['filter_type'] = activityPageFilterType((string) ($notification->type ?? ''));
        $item = activityPageApplyRoleToFeedItem($item, $roleBase, $siteUrl);
        $titlePlain = trim(strip_tags((string) ($item['title_html'] ?? '')));
        $metaPlain = trim((string) ($item['meta'] ?? ''));
        $rawTitle = trim((string) ($notification->title ?? ''));
        $rawMessage = trim((string) ($notification->message ?? ''));
        $relatedId = (string) ((int) ($notification->related_id ?? 0));
        $searchParts = [$titlePlain, $metaPlain, $rawTitle, $rawMessage];
        if ($relatedId !== '0') {
            $searchParts[] = $relatedId;
            $searchParts[] = '#' . $relatedId;
        }
        if (preg_match_all('/#([A-Za-z0-9\-_]+)/', $rawTitle . ' ' . $rawMessage . ' ' . $titlePlain, $idMatches)) {
            foreach ($idMatches[1] as $token) {
                $searchParts[] = $token;
                $searchParts[] = '#' . $token;
            }
        }
        $item['search_text'] = strtolower(trim(preg_replace('/\s+/', ' ', implode(' ', $searchParts)) ?? ''));
        $items[] = $item;
    }

    return $items;
}

/**
 * Count activity rows per filter bucket.
 *
 * @param array<int, array<string,mixed>> $items
 * @return array<string,int>
 */
function activityPageFilterCounts(array $items): array
{
    $counts = [
        'all' => count($items),
        'invoice' => 0,
        'project' => 0,
        'task' => 0,
        'client' => 0,
        'file' => 0,
        'ai' => 0,
    ];

    foreach ($items as $item) {
        $type = (string) ($item['filter_type'] ?? 'all');
        if (isset($counts[$type])) {
            $counts[$type]++;
        }
    }

    return $counts;
}

/**
 * Render a single activity row.
 *
 * @param array<string,mixed> $item
 */
function adminDashRenderActivityRow(array $item, array $lang, bool $includeFilterAttr = false): void
{
    $href = trim((string) ($item['href'] ?? ''));
    $taskId = (int) ($item['task_id'] ?? 0);
    $notifType = (string) ($item['notif_type'] ?? 'default');
    $menu = is_array($item['menu'] ?? null) ? $item['menu'] : [];
    $filterType = (string) ($item['filter_type'] ?? 'all');
    $searchText = (string) ($item['search_text'] ?? '');
    $rowInner = static function () use ($notifType, $item): void {
        ?>
                <div class="dash-activity-icon" aria-hidden="true">
                    <?php echo adminDashNotifIcon($notifType); ?>
                </div>
                <div class="dash-activity-copy flex-grow text-align-left">
                    <div class="dash-activity-title title font-size-14"><?php echo $item['title_html'] ?? ''; ?></div>
                    <?php if ((string) ($item['meta'] ?? '') !== '') : ?>
                    <div class="dash-activity-meta font-size-12 grey"><?php echo htmlspecialchars((string) ($item['meta'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>
                </div>
        <?php
    };
    ?>
        <div class="list-group-item note-card d-flex align-items-center dash-activity-item" style="position: relative;"<?php if ($includeFilterAttr) : ?> data-activity-type="<?php echo htmlspecialchars($filterType, ENT_QUOTES, 'UTF-8'); ?>" data-activity-search="<?php echo htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8'); ?>"<?php if ($taskId > 0) : ?> data-task-id="<?php echo $taskId; ?>"<?php endif; ?><?php endif; ?>>
            <?php if ($taskId > 0) : ?>
            <div class="dash-activity-main view-task-btn"
                 data-task-id="<?php echo $taskId; ?>"
                 role="button"
                 tabindex="0"
                 style="cursor:pointer;flex:1;min-width:0;display:flex;align-items:flex-start;gap:12px;">
                <?php $rowInner(); ?>
            </div>
            <?php elseif ($href !== '') : ?>
            <a href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>"
               class="dash-activity-main"
               style="text-decoration:none;color:inherit;flex:1;min-width:0;display:flex;align-items:flex-start;gap:12px;">
                <?php $rowInner(); ?>
            </a>
            <?php else : ?>
            <div class="dash-activity-main" style="flex:1;min-width:0;display:flex;align-items:flex-start;gap:12px;">
                <?php $rowInner(); ?>
            </div>
            <?php endif; ?>
            <?php adminDashRenderActivityMenu($menu, $lang); ?>
        </div>
    <?php
}

/**
 * Admin dashboard feed rows (widget-card + list-group; notification-style icons).
 */
function renderAdminDashFeedItems(array $items, string $emptyLabel, array $lang = [], string $emptyIcon = 'bell'): void
{
    if (empty($items)) {
        renderDashboardEmptyState($emptyIcon, $emptyLabel);
        return;
    }

    foreach ($items as $item) {
        adminDashRenderActivityRow($item, $lang, false);
    }
}

/**
 * Activity page feed rows with filter metadata on each row.
 */
function renderActivityPageItems(array $items, string $emptyLabel, array $lang = [], string $emptyIcon = 'bell', bool $rowsOnly = false): void
{
    if (empty($items)) {
        if (!$rowsOnly) {
            renderDashboardEmptyState($emptyIcon, $emptyLabel);
        }
        return;
    }

    foreach ($items as $item) {
        adminDashRenderActivityRow($item, $lang, true);
    }
}

/**
 * Capture rendered activity rows as HTML (for AJAX load-more).
 */
function activityPageRenderItemsHtml(array $items, array $lang): string
{
    ob_start();
    renderActivityPageItems($items, '', $lang, 'bell', true);
    return (string) ob_get_clean();
}

/**
 * Dashboard URL for the current account role.
 */
function activityPageDashboardHref(int $accountStatus, string $siteUrl): string
{
    $role = rtrim(activityPageRoleBase($accountStatus), '/');
    $rel = $role . '/dashboard';
    if (function_exists('tasksession_pretty_path')) {
        $rel = tasksession_pretty_path($role . '/index.php');
    }
    return rtrim($siteUrl, '/') . '/' . ltrim($rel, '/');
}
