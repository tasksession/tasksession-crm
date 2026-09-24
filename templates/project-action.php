<?php
/**
 * Project header "Actions" dropdown (Edit / Delete / Complete / Archive).
 * Expects: $url, $lang, $project (object with p_id, status, archive).
 * Optional: $recentProject (for stable menu id — defaults to $project), $accountStatus.
 * Hidden project_edit_return: return to same tab after saving edit-project.
 */
if (!isset($url) || !isset($lang)) {
    return;
}

$paProject = null;
if (isset($project) && is_object($project) && !empty($project->p_id)) {
    $paProject = $project;
} elseif (isset($proj_info) && is_object($proj_info) && !empty($proj_info->p_id)) {
    $paProject = $proj_info;
}

if (!$paProject) {
    return;
}

$acct = isset($accountStatus) ? (int)$accountStatus : (int)($_SESSION['accountStatus'] ?? 1);
$isClient = ($acct === 2);

$menuId = 0;
if (isset($recentProject) && is_object($recentProject) && !empty($recentProject->p_id)) {
    $menuId = (int)$recentProject->p_id;
} else {
    $menuId = (int)$paProject->p_id;
}

$isStaff = ($acct === 3);
$editUrl = (function_exists('tasksession_app_href')
    ? tasksession_app_href(($isStaff ? 'staff' : 'admin') . '/edit-project?id=' . (int) $paProject->p_id)
    : ($url . ($isStaff ? 'staff/edit-project?id=' : 'admin/edit-project?id=') . (int) $paProject->p_id));
if (function_exists('project_edit_return_capture_current_uri')) {
    $returnUri = project_edit_return_capture_current_uri();
    if ($returnUri) {
        $editUrl .= (strpos($editUrl, '?') !== false ? '&' : '?') . 'project_edit_return=' . rawurlencode($returnUri);
    }
}
$projectsHandler = (function_exists('tasksession_app_href')
    ? tasksession_app_href(($isStaff ? 'staff' : 'admin') . '/projects')
    : ($url . ($isStaff ? 'staff/projects' : 'admin/projects')));

$canEdit = !$isClient && (!$isStaff || (function_exists('has_permission') && has_permission('project_edit')));
$canDelete = !$isClient && (!$isStaff || (function_exists('has_permission') && has_permission('project_delete')));

$isDiscussionPage = (isset($_SERVER['SCRIPT_NAME']) && basename((string) $_SERVER['SCRIPT_NAME']) === 'discussion.php')
    || (isset($_SERVER['PHP_SELF']) && basename((string) $_SERVER['PHP_SELF']) === 'discussion.php');

$uidForNotif = (int) ($_SESSION['userId'] ?? 0);
$emailNotifEntryAllowed = false;
if ($uidForNotif > 0) {
    if (!function_exists('chat_email_notifications_entry_allowed')) {
        require_once dirname(__DIR__) . '/includes/email-notification/bootstrap.php';
        require_once dirname(__DIR__) . '/includes/email-notification/chat_email_permissions.php';
    }
    $emailNotifEntryAllowed = chat_email_notifications_entry_allowed($uidForNotif, $acct);
}
if (function_exists('tasksession_is_free_edition') && tasksession_is_free_edition()) {
    $emailNotifEntryAllowed = false;
}

$showEmailNotifInActions = $isDiscussionPage && !empty($emailNotifEntryAllowed);

$showAiInActions = false;
if ($isDiscussionPage) {
    $aiHelpersPath = dirname(__DIR__) . '/ai/helpers.php';
    if (!function_exists('ai_contextual_button_allowed') && is_file($aiHelpersPath)) {
        require_once $aiHelpersPath;
    }
    if (!function_exists('ai_contextual_emit_launcher_script') && is_file(dirname(__DIR__) . '/includes/ai_contextual_snippet.php')) {
        require_once dirname(__DIR__) . '/includes/ai_contextual_snippet.php';
    }
    $showAiInActions = function_exists('ai_contextual_button_allowed') && ai_contextual_button_allowed();
}

// Clients: only show Actions on discussion when Notifications Settings and/or AI actions are available.
if ($isClient && !$showEmailNotifInActions && !$showAiInActions) {
    return;
}

// Staff with no project edit/delete must still see Actions on discussion when Notifications and/or AI are available.
if ($isStaff && !$canEdit && !$canDelete && !$showEmailNotifInActions && !$showAiInActions) {
    return;
}

$aiProjectId = (int) $paProject->p_id;
$aiAskUrl = '';
$aiSummarizeUrl = '';
if ($showAiInActions && function_exists('ai_assistant_url')) {
    $aiAskUrl = ai_assistant_url('', [
        'context_type' => 'project',
        'context_id' => $aiProjectId,
    ]);
    $aiSummarizeUrl = ai_assistant_url('', [
        'context_type' => 'project',
        'context_id' => $aiProjectId,
        'prefill' => 'Summarize this project discussion and list action items',
    ]);
}
$aiAskLabel = $lang['Ask AI'] ?? 'Ask AI';
$aiSummarizeLabel = $lang['Summarize discussion'] ?? 'Summarize discussion';
?>
<div class="edit-overview-btn kanban-header-filters">
    <td class="extra-height">
        <div class="action-toggle border-btn-a collapsed" data-bs-toggle="collapse" data-bs-target="#dropdown-menu<?php echo $menuId; ?>" aria-expanded="false" role="button" tabindex="0">
            <span class="action-text"><?php echo $lang['Actions']; ?></span>
            <span class="mobile-ellipsis" aria-hidden="true"><?php echo ts_icon('ellipsis', 'w-2'); ?></span>
            <?php echo ts_icon('chevron-down', 'w-2'); ?>
        </div>
        <div id="dropdown-menu<?php echo $menuId; ?>" class="toggle-action collapse shadow-dept">
            <ul>
                <?php if ($showAiInActions): ?>
                <li>
                    <div class="ai-contextual-wrap" data-ai-contextual="1" data-context-type="project" data-context-id="<?php echo $aiProjectId; ?>" data-prefill="Summarize this project discussion and list action items" data-assistant-url="<?php echo htmlspecialchars($aiSummarizeUrl, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="button" class="pd-0 ai-contextual-trigger" data-ai-lazy="contextual-ai">
                            <?php echo ts_icon('sparkles', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo htmlspecialchars($aiSummarizeLabel, ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                    </div>
                </li>
                <li>
                    <div class="ai-contextual-wrap" data-ai-contextual="1" data-context-type="project" data-context-id="<?php echo $aiProjectId; ?>" data-assistant-url="<?php echo htmlspecialchars($aiAskUrl, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="button" class="pd-0 ai-contextual-trigger" data-ai-lazy="contextual-ai">
                            <?php echo ts_icon('sparkles', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo htmlspecialchars($aiAskLabel, ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                    </div>
                </li>
                <?php endif; ?>
                <?php if ($canEdit): ?>
                <li>
                    <a href="<?php echo htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo ts_icon('edit', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Edit Project']; ?>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (!$isClient): ?>
                <li>
                    <form method="post" action="<?php echo htmlspecialchars($projectsHandler, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" value="<?php echo (int)$paProject->p_id; ?>" name="comp_id" />
                        <input type="hidden" value="<?php echo ((int)$paProject->status === 0) ? '1' : '0'; ?>" name="comp_val" />
                        <button type="submit" name="comp_proj">
                            <?php if ((int)$paProject->status === 0): ?>
                                <?php echo ts_icon('check-circle', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Mark as complete']; ?>
                            <?php else: ?>
                                <?php echo ts_icon('restore', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Re-open']; ?>
                            <?php endif; ?>
                        </button>
                    </form>
                </li>
                <?php if ($isDiscussionPage && $acct === 1 && !(function_exists('tasksession_is_free_edition') && tasksession_is_free_edition())): ?>
                <li>
                    <button type="button" class="discussion-clear-chat-action pd-0" data-project-id="<?php echo (int) $paProject->p_id; ?>">
                        <?php echo ts_icon('trash-box', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo htmlspecialchars(isset($lang['Clear chat']) ? $lang['Clear chat'] : 'Clear chat', ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                </li>
                <?php endif; ?>
                <li>
                    <form method="post" action="<?php echo htmlspecialchars($projectsHandler, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" value="<?php echo (int)$paProject->p_id; ?>" name="arc_id" />
                        <input type="hidden" value="<?php echo ((int)$paProject->archive === 0) ? '1' : '0'; ?>" name="arc_val" />
                        <button type="submit" name="arc_proj">
                            <?php echo ts_icon('archive', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo ((int)$paProject->archive === 0) ? $lang['Move to Archive'] : $lang['Move to Projects']; ?>
                        </button>
                    </form>
                </li>
                <?php endif; ?>
                <?php if ($showEmailNotifInActions): ?>
                <li>
                    <button type="button" id="discEmailNotifBtn" class="project-action-email-notif-btn pd-0">
                        <?php echo ts_icon('emails', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo htmlspecialchars(isset($lang['Notifications Settings']) ? $lang['Notifications Settings'] : 'Notifications Settings', ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                </li>
                <?php endif; ?>
                <?php if ($canDelete): ?>
                <li>
                    <form method="post" action="<?php echo htmlspecialchars($projectsHandler, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" value="<?php echo (int)$paProject->p_id; ?>" name="del_id" />
                        <input type="hidden" value="1" name="del_val" />
                        <button type="submit" name="del_proj">
                            <?php echo ts_icon('delete', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Delete Project']; ?>
                        </button>
                    </form>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </td>
</div>
<?php
if ($showAiInActions && function_exists('ai_contextual_emit_launcher_script')) {
    ai_contextual_emit_launcher_script();
}
?>
