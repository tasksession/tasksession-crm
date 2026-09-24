<?php
/**
 * Partial: Project table rows
 * Expects:
 * - $recentProjects (array of Projects)
 * Uses globals/vars from parent scope: $lang, $currency_symbol, $database
 */
// Load settings for module check
if (!isset($settingsForModules)) {
    $settingsForModules = isset($dash_settings) && $dash_settings ? $dash_settings : settings::findById(1);
}
$isDiscussionsEnabled = ($settingsForModules && !empty($settingsForModules->module_discussions));
$isInvoicesEnabled = ($settingsForModules && !empty($settingsForModules->module_invoices));
if (!isset($projectsTableLinkBase)) {
    $projectsTableLinkBase = '';
}
if (!isset($projectsTableDiscussionBase)) {
    $projectsTableDiscussionBase = '../';
}
if (!isset($projectsTableFormAction)) {
    $projectsTableFormAction = '#';
}
$projectsTableLinkBase = (string) $projectsTableLinkBase;
$projectsTableDiscussionBase = (string) $projectsTableDiscussionBase;
$projectsTableFormAction = (string) $projectsTableFormAction;
$tsMenuIco = 'tasksession-timer-log-menu-ico me-2';
?>
<?php
$rowIndex = 1;
if (empty($recentProjects)) {
    echo '<tr><td colspan="9">'.$lang['No projects available. Create a new project to proceed.'].'</td></tr>';
} else {
    foreach ($recentProjects as $recentProject) { ?>
        <tr>
            <td class="bs-checkbox ts-list-bulk-checkbox-cell">
                <input data-index="<?php echo $rowIndex; ?>" value="<?php echo $recentProject->p_id ?>" name="btSelectItem" type="checkbox" aria-label="Select row">
            </td>
            <td><div class="tbl-ttl">
                <?php echo $recentProject->project_title;?><div>
            </td>
            <td class="clients-rpt" style="text-align: left;">
                <div class="d-flex align-items-center">
                <?php
                $s_ids = $recentProject->s_ids;
                $st_ids = explode(',', $s_ids);
                $teamCounter = 0;
                foreach ($st_ids as $st_id) {
                    if ($st_id != $recentProject->c_id && $st_id != 0) {
                        $teamCounter++;
                        if ($teamCounter > 3) {
                            continue;
                        }
                        $user2 = user::findById($st_id);
                        echo '<div class="user-box">';
                        echo '<form action="' . htmlspecialchars($projectsTableDiscussionBase, ENT_QUOTES, 'UTF-8') . 'discussion?project_id=' . $recentProject->p_id . '" method="post"><input type="hidden" name="user_id" value="' . $st_id . '" /><input type="hidden" name="project_id" value="' . $recentProject->p_id . '" /><button name="chat" type="submit" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $user2->firstName . '">';
                        echo getUserAvatarHtml($st_id, $user2->firstName, $user2->lastName ?? '', 36, 36, 'img-fluid rounded-circle', $user2->firstName);
                        echo '</button></form>';
                        echo '</div>';
                    }
                }

                if ($teamCounter > 3) {
                    $more = $teamCounter - 3;
                    echo '<div class="plus-more">+'. $more .'</div>';
                }
                ?>
                </div>
            </td>
            <td class="clients-rpt">
                <div class="d-flex align-items-center">
                <?php
                // Display main client first
                $mainClient = user::findById($recentProject->main_client_id ?: $recentProject->c_id);
                if ($mainClient) {
                    echo '<div class="user-box">';
                    echo '<form action="' . htmlspecialchars($projectsTableDiscussionBase, ENT_QUOTES, 'UTF-8') . 'discussion?project_id=' . $recentProject->p_id . '" method="post"><input type="hidden" name="user_id" value="' . $mainClient->id . '" /><input type="hidden" name="project_id" value="' . $recentProject->p_id . '" /><button name="chat" type="submit" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $mainClient->firstName . ' (Main)">';
                    echo getUserAvatarHtml($mainClient->id, $mainClient->firstName, $mainClient->lastName ?? '', 36, 36, 'img-fluid rounded-circle', $mainClient->firstName);
                    echo '</button></form>';
                    echo '</div>';
                }

                // Display additional clients if any
                if (!empty($recentProject->c_ids)) {
                    $allClientIds = array_filter(explode(',', $recentProject->c_ids));
                    $additionalClients = array_filter($allClientIds, function($id) use ($recentProject) {
                        return $id != $recentProject->main_client_id && $id != $recentProject->c_id;
                    });

                    $clientCounter = 0;
                    foreach ($additionalClients as $clientId) {
                        $clientCounter++;
                        if ($clientCounter > 2) break;

                        $client = user::findById($clientId);
                        if ($client) {
                            echo '<div class="user-box">';
                            echo '<form action="' . htmlspecialchars($projectsTableDiscussionBase, ENT_QUOTES, 'UTF-8') . 'discussion?project_id=' . $recentProject->p_id . '" method="post"><input type="hidden" name="user_id" value="' . $client->id . '" /><input type="hidden" name="project_id" value="' . $recentProject->p_id . '" /><button name="chat" type="submit" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $client->firstName . '">';
                            echo getUserAvatarHtml($client->id, $client->firstName, $client->lastName ?? '', 36, 36, 'img-fluid rounded-circle', $client->firstName);
                            echo '</button></form>';
                            echo '</div>';
                        }
                    }

                    if (count($additionalClients) > 2) {
                        $more = count($additionalClients) - 2;
                        echo '<div class="plus-more">+'. $more .'</div>';
                    }
                }
                ?>
                </div>
            </td>
            <td class="red-text">
                <?php echo $recentProject->end_time;?>
            </td>
            <td class="prostatus">
                <?php
                $status = $recentProject->status;
                $archive = $recentProject->archive;
                $trash = $recentProject->trash;
                if ($status == 0) { ?>
                    <span class="badge inprogress"><?php echo $lang['In Progress']; ?></span>
                <?php } else { ?>
                    <span class="badge completed"><?php echo $lang['Completed']; ?></span>
                <?php } ?>
            </td>
            <td class="pro-bdgt">
                <?php echo $currency_symbol . $recentProject->budget;?>
            </td>
            <td class="tbl-tasks extra-height">
                <?php
                $projectId = $recentProject->p_id;
                global $database;
                $taskQuery = "SELECT COUNT(*) as task_count FROM tasks WHERE project_id = $projectId";
                $taskResult = $database->query($taskQuery);
                $taskCount = 0;
                if ($taskRow = $database->fetchArray($taskResult)) {
                    $taskCount = $taskRow['task_count'];
                }
                $completedTaskQuery = "SELECT COUNT(*) as completed_count FROM tasks WHERE project_id = $projectId AND status = 'done'";
                $completedTaskResult = $database->query($completedTaskQuery);
                $completedTaskCount = 0;
                if ($completedTaskRow = $database->fetchArray($completedTaskResult)) {
                    $completedTaskCount = $completedTaskRow['completed_count'];
                }
                $percent = ($taskCount > 0) ? round(($completedTaskCount / $taskCount) * 100) : 0;
                ?>
                <div class="mb-1 d-flex col-gap-5"> <?php echo $completedTaskCount; ?>/<?php echo $taskCount; ?> <span class="text-align-right flex-grow"><?php echo $percent; ?>%</span></div>
                <div class="progress" style="height:8px;">
                    <div class="progress-bar" role="progressbar" style="width: <?php echo $percent; ?>%; background: <?php echo $percent < 30 ? '#f66' : ($percent < 70 ? '#f9b233' : '#4caf50'); ?>;" aria-valuenow="<?php echo $percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </td>
            <td class="extra-height">
                <div class="action-toggle" data-bs-toggle="collapse" data-bs-target="#actionDropdown<?php echo $recentProject->p_id;?>">
                    <?php echo $lang['Action']; ?><?php echo ts_icon('chevron-down'); ?></div>
                <div id="actionDropdown<?php echo $recentProject->p_id;?>" class="toggle-action collapse  shadow-dept">
                    <ul>
                        <li>
                            <a href="<?php echo htmlspecialchars($projectsTableLinkBase, ENT_QUOTES, 'UTF-8'); ?>overview?projectId=<?php echo $recentProject->p_id;?>">
                                <?php echo ts_icon('info', $tsMenuIco); ?><?php echo $lang['Overview']; ?>
                            </a>
                        </li>
                        <?php if ($isDiscussionsEnabled): ?>
                        <li>
                            <form action="<?php echo htmlspecialchars($projectsTableDiscussionBase, ENT_QUOTES, 'UTF-8'); ?>discussion?project_id=<?php echo $recentProject->p_id;?>" method="post">
                                <input type="hidden" name="user_id" value="<?php echo $recentProject->c_id;?>" />
                                <input type="hidden" name="project_id" value="<?php echo $recentProject->p_id;?>" />
                                <button type="submit" name="chat" class="dropdown-item">
                                    <?php echo ts_icon('chat', $tsMenuIco); ?> <?php echo $lang['Discussion']; ?>
                                </button>
                            </form>
                        </li>
                        <?php endif; ?>
                        <li>
                            <a href="<?php echo htmlspecialchars($projectsTableLinkBase, ENT_QUOTES, 'UTF-8'); ?>task?projectId=<?php echo $recentProject->p_id; ?>">
                                <?php echo ts_icon('tasks', $tsMenuIco); ?><?php echo $lang['Tasks']; ?>
                            </a>
                        </li>
                        <?php if ($isInvoicesEnabled): ?>
                        <li>
                            <a href="<?php echo htmlspecialchars($projectsTableLinkBase, ENT_QUOTES, 'UTF-8'); ?>payments?projectId=<?php echo $recentProject->p_id; ?>">
                                <?php echo ts_icon('payments', $tsMenuIco); ?><?php echo $lang['Payments']; ?>
                            </a>
                        </li>
                        <?php endif; ?>
                        <li>
                            <a href="<?php echo htmlspecialchars($projectsTableLinkBase, ENT_QUOTES, 'UTF-8'); ?>edit-project?id=<?php echo $recentProject->p_id;?>"><?php echo ts_icon('edit', $tsMenuIco); ?> <?php echo $lang['Edit Project']; ?></a>
                        </li>
                        <li>
                            <form method="post" action="<?php echo htmlspecialchars($projectsTableFormAction, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" value="<?php echo $recentProject->p_id;?>" name="comp_id" />
                                <input type="hidden" value="<?php if($status == 0){ echo '1';}else { echo '0';} ?>" name="comp_val" />
                                <button type="submit" name="comp_proj">
                                    <?php if($status == 0){
                                            echo ts_icon('check-circle', $tsMenuIco) . ' ' . $lang['Mark as complete'];
                                        } else {
                                            echo ts_icon('check', $tsMenuIco) . $lang['Re-open'];
                                        } ?> </button>
                            </form>
                        </li>
                        <li>
                            <form method="post" action="<?php echo htmlspecialchars($projectsTableFormAction, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" value="<?php echo $recentProject->p_id;?>" name="arc_id" />
                                <input type="hidden" value="<?php if($archive == 0){ echo '1';}else { echo '0';} ?>" name="arc_val" />
                                <button type="submit" name="arc_proj" class="dropdown-item">
                                    <?php if($archive == 0){
                                            echo ts_icon('archive', $tsMenuIco) . ' ' . $lang['Move to Archive'];
                                        } else {
                                            echo ts_icon('archive', $tsMenuIco) . ' ' . $lang['Move to Projects'];
                                        } ?> </button>
                            </form>
                        </li>
                        <li>
                            <form method="post" action="<?php echo htmlspecialchars($projectsTableFormAction, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" value="<?php echo $recentProject->p_id;?>" name="del_id" />
                                <input type="hidden" value="<?php if($trash == 0){ echo '1';}else { echo '0';} ?>" name="del_val" />
                                <button type="submit" name="del_proj">
                                    <?php echo ts_icon('delete', $tsMenuIco); ?> <?php echo $lang['Delete Project']; ?>
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </td>
        </tr>
    <?php
        $rowIndex++;
    }
}
?>


