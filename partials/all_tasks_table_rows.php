<?php
/**
 * All tasks table rows — shared by all-tasks.php and mega search.
 * Expects: $tasks, $lang, $statusLabels, $statusColors
 */
global $database;
require_once dirname(__DIR__) . '/includes/all_tasks_table_helper.php';

if (!isset($allTasksTableLinkBase)) { $allTasksTableLinkBase = ''; }
if (!isset($allTasksCloneBase)) { $allTasksCloneBase = '../includes/'; }
if (!isset($allTasksRedirectUri)) { $allTasksRedirectUri = $_SERVER['REQUEST_URI'] ?? ''; }
if (!isset($showArchive)) { $showArchive = false; }
if (!isset($allTasksShowCheckbox)) { $allTasksShowCheckbox = true; }
if (!isset($allTasksCheckboxDisabled)) { $allTasksCheckboxDisabled = false; }
if (!isset($allTasksCanUpdateStatus) || !isset($allTasksCanDelete) || !isset($allTasksCanArchive) || !isset($allTasksCanEdit) || !isset($allTasksCanDuplicate)) {
    $acct = (int) ($_SESSION['accountStatus'] ?? 0);
    if ($acct === 1) {
        // Admin: full access unless a flag was explicitly set above.
        if (!isset($allTasksCanUpdateStatus)) { $allTasksCanUpdateStatus = true; }
        if (!isset($allTasksCanDelete)) { $allTasksCanDelete = true; }
        if (!isset($allTasksCanArchive)) { $allTasksCanArchive = true; }
        if (!isset($allTasksCanEdit)) { $allTasksCanEdit = true; }
        if (!isset($allTasksCanDuplicate)) { $allTasksCanDuplicate = true; }
    } elseif ($acct === 3 && function_exists('has_permission')) {
        if (!isset($allTasksCanUpdateStatus)) { $allTasksCanUpdateStatus = has_permission('task_status_update'); }
        if (!isset($allTasksCanDelete)) { $allTasksCanDelete = has_permission('task_delete'); }
        if (!isset($allTasksCanArchive)) { $allTasksCanArchive = has_permission('task_edit'); }
        if (!isset($allTasksCanEdit)) { $allTasksCanEdit = has_permission('task_edit'); }
        if (!isset($allTasksCanDuplicate)) { $allTasksCanDuplicate = has_permission('task_duplicate'); }
    } elseif ($acct === 2) {
        $tp = null;
        if (class_exists('TaskPermission') && !empty($_SESSION['userId'])) {
            $tp = TaskPermission::getOrCreate((int) $_SESSION['userId']);
        }
        if (!isset($allTasksCanUpdateStatus)) { $allTasksCanUpdateStatus = $tp ? (bool) $tp->can_change_status : false; }
        if (!isset($allTasksCanDelete)) { $allTasksCanDelete = $tp ? (bool) $tp->can_delete_task : false; }
        if (!isset($allTasksCanArchive)) { $allTasksCanArchive = $tp ? (bool) $tp->can_update_task : false; }
        if (!isset($allTasksCanEdit)) { $allTasksCanEdit = $tp ? (bool) $tp->can_update_task : false; }
        if (!isset($allTasksCanDuplicate)) { $allTasksCanDuplicate = $tp ? (bool) $tp->can_create_task : false; }
    } else {
        if (!isset($allTasksCanUpdateStatus)) { $allTasksCanUpdateStatus = false; }
        if (!isset($allTasksCanDelete)) { $allTasksCanDelete = false; }
        if (!isset($allTasksCanArchive)) { $allTasksCanArchive = false; }
        if (!isset($allTasksCanEdit)) { $allTasksCanEdit = false; }
        if (!isset($allTasksCanDuplicate)) { $allTasksCanDuplicate = false; }
    }
}
if (!isset($allTasksActionsUi)) { $allTasksActionsUi = 'dropdown'; }

$tsMenuIco = 'tasksession-timer-log-menu-ico me-2';
$allTasksTableLinkBase = (string) $allTasksTableLinkBase;
$allTasksCloneBase = (string) $allTasksCloneBase;
$allTasksRedirectUri = (string) $allTasksRedirectUri;
$allTasksLinkEsc = htmlspecialchars($allTasksTableLinkBase, ENT_QUOTES, 'UTF-8');
$allTasksCloneEsc = htmlspecialchars($allTasksCloneBase, ENT_QUOTES, 'UTF-8');
$colspan = allTasksTableColspan((bool) $allTasksShowCheckbox);

if (count($tasks) > 0) {
    foreach ($tasks as $task) {
        ?><tr data-status="<?php echo htmlspecialchars($task['status'], ENT_QUOTES, 'UTF-8'); ?>" data-task-id="<?php echo (int) $task['id']; ?>">
<?php if ($allTasksShowCheckbox): ?>
                                            <td class="bs-checkbox">
                                                <div class="task-table-checkbox">
                                                    <input type="checkbox" id="task-<?php echo (int) $task['id']; ?>" class="task-checkbox" value="<?php echo (int) $task['id']; ?>"<?php echo !empty($allTasksCheckboxDisabled) ? ' disabled' : ''; ?> aria-label="<?php echo htmlspecialchars(($lang['Select task'] ?? 'Select task') . ': ' . $task['title'], ENT_QUOTES, 'UTF-8'); ?>">
                                                </div>
                                            </td>
<?php endif; ?>
                                            <td class="text-start all-tasks-task-cell">
                                                <div class="tbl-ttl">
                                                    <a href="#" class="all-tasks-open-task text-decoration-none" onclick="if(typeof openTaskSidebar==='function'){openTaskSidebar(<?php echo (int) $task['id']; ?>);} return false;" title="<?php echo htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8'); ?>">
                                                        <?php echo htmlspecialchars($task['title']); ?>
                                                    </a>
                                                </div>
                                            </td>
                                            <td class="clients-rpt text-start">
                                                <?php
                                                if (!empty($task['assigned_to'])):
                                                    $assignedIds = array_filter(array_map('trim', explode(',', $task['assigned_to'])));
                                                    $assignedMembers = [];
                                                    foreach ($assignedIds as $staffId) {
                                                        if ($staffId === '' || (int) $staffId === 0) {
                                                            continue;
                                                        }
                                                        $staffMember = User::findById($staffId);
                                                        if ($staffMember) {
                                                            $assignedMembers[] = $staffMember;
                                                        }
                                                    }
                                                    $assigneeCount = count($assignedMembers);
                                                    if ($assigneeCount === 0):
                                                        echo '<span class="text-muted">' . htmlspecialchars($lang['Unassigned'] ?? 'Unassigned', ENT_QUOTES, 'UTF-8') . '</span>';
                                                    else:
                                                ?>
                                                <div class="d-flex align-items-center">
                                                <?php
                                                        $show = min(3, $assigneeCount);
                                                        for ($i = 0; $i < $show; $i++):
                                                            $staffMember = $assignedMembers[$i];
                                                ?>
                                                <div class="user-box" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($staffMember->firstName . ' ' . crm_user_last_name($staffMember), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php
                                                    echo getUserAvatarHtml($staffMember->id, $staffMember->firstName, crm_user_last_name($staffMember), 36, 36, 'img-fluid profile-img', $staffMember->firstName);
                                                    ?>
                                                </div>
                                                <?php
                                                        endfor;
                                                        if ($assigneeCount > 3) {
                                                            echo '<div class="plus-more">+' . ($assigneeCount - 3) . '</div>';
                                                        }
                                                ?>
                                                </div>
                                                <?php
                                                    endif;
                                                else:
                                                    echo '<span class="text-muted">' . htmlspecialchars($lang['Unassigned'] ?? 'Unassigned', ENT_QUOTES, 'UTF-8') . '</span>';
                                                endif;
                                                ?>
                                            </td>
                                            <?php echo allTasksTableClientCellHtml($task, $lang); ?>
                                            <?php echo allTasksTableDatesCellHtml($task, $lang); ?>
                                            <?php echo allTasksTableProgressCellHtml($task, $lang); ?>
                                            <?php echo allTasksTableStatusCellHtml($task, $statusLabels, $statusColors, (bool) $allTasksCanUpdateStatus); ?>
                                            <?php echo allTasksTableActionsCellHtml($task, $lang, $allTasksLinkEsc, $allTasksCloneEsc, $allTasksRedirectUri, (bool) $showArchive, $tsMenuIco, (bool) $allTasksCanArchive, (bool) $allTasksCanDelete, (string) $allTasksActionsUi, (bool) $allTasksCanEdit, (bool) $allTasksCanDuplicate); ?>
                                        </tr><?php
    }
} else {
    ?><tr>
                                            <td colspan="<?php echo (int) $colspan; ?>" class="p-0 border-0">
                                                <div class="alert alert-info mb-0"><?php echo htmlspecialchars($lang['No tasks found'] ?? 'No tasks found', ENT_QUOTES, 'UTF-8'); ?></div>
                                            </td>
                                        </tr><?php
}
