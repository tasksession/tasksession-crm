<?php
/**
 * Staff partial: Project table rows
 * Expects:
 * - $recentProjects (array of Projects)
 * Uses: $lang, $currency_symbol, $database, has_permission()
 */
?>
<?php
$rowIndex = 1;
$showBudget = function_exists('has_permission') && has_permission('milestone_view');
$colspan = $showBudget ? 9 : 8;

if (empty($recentProjects)) {
    echo '<tr><td colspan="'.$colspan.'">'.$lang['No projects available. Create a new project to proceed.'].'</td></tr>';
} else {
    foreach ($recentProjects as $recentProject) { ?>
        <tr>
            <td class="bs-checkbox">
                <input data-index="<?php echo $rowIndex; ?>" value="<?php echo $recentProject->p_id ?>" name="btSelectItem" type="checkbox">
            </td>
            <td><div class="tbl-ttl">
                <?php echo $recentProject->project_title;?><div>
            </td>
            <td class="clients-rpt" style="text-align: left;">
                <div class="d-flex align-items-center">
                <?php 
                  $s_ids = $recentProject->s_ids;
                  $st_ids = explode(',', $s_ids);
                  $team_counter = 0;
                  foreach($st_ids as $st_id){
                      if($st_id != $recentProject->c_id && $st_id != 0){
                          $team_counter++;
                          if($team_counter > 3){} else {
                            $user2 = user::findById($st_id); 
                            echo '<div class="user-box">';
                            echo '<form action="../discussion?project_id='.$recentProject->p_id .'" method="post"><input type="hidden" name="user_id" value="'.$st_id.'" /><input type="hidden" name="project_id" value="'.$recentProject->p_id .'" /><button name="chat" type="submit" data-bs-toggle="tooltip" data-bs-placement="top" title="'.$user2->firstName.'">';
                            echo getUserAvatarHtml($st_id, $user2->firstName, '', 36, 36, '', $user2->firstName);
                            echo '</button></form>';
                            echo '</div>'; 
                          }
                      }
                  }
                  if($team_counter > 3){
                      $more = $team_counter-3;
                       echo '<div class="plus-more">+'. $more .'</div>'; 
                  }
                ?>
                </div>
            </td>
            <td class="clients-rpt">
                <div class="d-flex align-items-center">
                <?php 
                    // Get all clients (main + additional)
                    $allClients = array();
                    
                    // Add main client first
                    $mainClient = user::findById($recentProject->main_client_id ?: $recentProject->c_id); 
                    if ($mainClient) {
                        $allClients[] = array(
                            'id' => $mainClient->id,
                            'firstName' => $mainClient->firstName,
                            'lastName' => '',
                            'isMain' => true
                        );
                    }
                    
                    // Add additional clients
                    if (!empty($recentProject->c_ids)) {
                        $allClientIds = array_filter(explode(',', $recentProject->c_ids));
                        $additionalClients = array_filter($allClientIds, function($id) use ($recentProject) {
                            return $id != $recentProject->main_client_id && $id != $recentProject->c_id;
                        });
                        foreach($additionalClients as $clientId) {
                            $client = user::findById($clientId);
                            if ($client) {
                                $allClients[] = array(
                                    'id' => $client->id,
                                    'firstName' => $client->firstName,
                                    'lastName' => '',
                                    'isMain' => false
                                );
                            }
                        }
                    }
                    
                    // Display only first 2 clients
                    $displayedCount = 0;
                    foreach($allClients as $client) {
                        if($displayedCount >= 2) break;
                        $title = $client['isMain'] ? $client['firstName'] . ' (Main)' : $client['firstName'];
                        echo '<div class="user-box">';
                        echo '<form action="../discussion?project_id='.$recentProject->p_id .'" method="post"><input type="hidden" name="user_id" value="'.$client['id'].'" /><input type="hidden" name="project_id" value="'.$recentProject->p_id .'" /><button name="chat" type="submit" data-bs-toggle="tooltip" data-bs-placement="top" title="'.$title.'">';
                        echo getUserAvatarHtml($client['id'], $client['firstName'], $client['lastName'], 36, 36, 'img-fluid rounded-circle', $client['firstName']);
                        echo '</button></form>';
                        echo '</div>';
                        $displayedCount++;
                    }
                    
                    if(count($allClients) > 2) {
                        $more = count($allClients) - 2;
                        echo '<div class="plus-more shadow-dept">+'. $more .'</div>';
                    }
                ?>
                </div>
            </td>
            <td class="red-text">
                <?php echo $recentProject->end_time;?>
            </td>
            <td class="prostatus">
                <?php if((int)$recentProject->status === 0){ ?>
                    <span class="badge inprogress"><?php echo $lang['In Progress']; ?></span>
                <?php } else { ?>
                    <span class="badge completed"><?php echo $lang['Completed']; ?></span>
                <?php } ?>
            </td>
            <?php if ($showBudget): ?>
            <td class="pro-bdgt">
                <?php echo ($currency_symbol ?? '$') . $recentProject->budget;?>
            </td>
            <?php endif; ?>
            <td class="tbl-tasks extra-height">
                <?php 
                    $projectId = $recentProject->p_id;
                    global $database;
                    $taskQuery = "SELECT COUNT(*) as task_count FROM tasks WHERE project_id = $projectId";
                    $taskResult = $database->query($taskQuery);
                    $taskCount = 0;
                    if($taskRow = $database->fetchArray($taskResult)) {
                        $taskCount = $taskRow['task_count'];
                    }
                    $completedTaskQuery = "SELECT COUNT(*) as completed_count FROM tasks WHERE project_id = $projectId AND status = 'done'";
                    $completedTaskResult = $database->query($completedTaskQuery);
                    $completedTaskCount = 0;
                    if($completedTaskRow = $database->fetchArray($completedTaskResult)) {
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
                            <a href="overview?projectId=<?php echo $recentProject->p_id;?>">
                                <?php echo ts_icon('info', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Overview']; ?>
                            </a>
                        </li>
                        <li>
                            <form action="../discussion?project_id=<?php echo $recentProject->p_id;?>" method="post">
                                <input type="hidden" name="user_id" value="<?php echo $recentProject->c_id;?>" />
                                <input type="hidden" name="project_id" value="<?php echo $recentProject->p_id;?>" />
                                <button type="submit" name="chat" class="dropdown-item">
                                    <?php echo ts_icon('chat', 'tasksession-timer-log-menu-ico me-2'); ?> <?php echo $lang['Discussion']; ?>
                                </button>
                            </form>
                        </li>
                        <li>
                            <a href="task?projectId=<?php echo $recentProject->p_id; ?>">
                                <?php echo ts_icon('tasks', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Tasks']; ?>
                            </a>
                        </li>
                        <?php if (function_exists('has_permission') && has_permission('project_edit')): ?>
                        <li>
                            <a href="edit-project?id=<?php echo $recentProject->p_id;?>"><?php echo ts_icon('edit'); ?> <?php echo $lang['Edit Project']; ?></a>
                        </li>
                        <?php endif; ?>
                        <li>
                            <form method="post" action="#">
                                <input type="hidden" value="<?php echo $recentProject->p_id;?>" name="comp_id" />
                                <input type="hidden" value="<?php echo ((int)$recentProject->status === 0) ? '1' : '0'; ?>" name="comp_val" />
                                <button type="submit" name="comp_proj" class="dropdown-item">
                                    <?php if((int)$recentProject->status === 0){
                                        echo ts_icon('check-circle', 'tasksession-timer-log-menu-ico me-2') . ' ' . $lang['Mark as complete'];
                                    }else{ 
                                        echo ts_icon('check') . $lang['Re-open'];
                                    } ?>
                                </button>
                            </form>
                        </li>
                        <li>
                            <form method="post" action="#">
                                <input type="hidden" value="<?php echo $recentProject->p_id;?>" name="arc_id" />
                                <input type="hidden" value="<?php echo ((int)$recentProject->archive === 0) ? '1' : '0'; ?>" name="arc_val" />
                                <button type="submit" name="arc_proj" class="dropdown-item">
                                    <?php if((int)$recentProject->archive === 0){
                                        echo ts_icon('archive', 'tasksession-timer-log-menu-ico me-2') . ' ' . $lang['Move to Archive'];
                                    }else{ 
                                        echo ts_icon('archive') . ' ' . $lang['Move to Projects'];
                                    } ?>
                                </button>
                            </form>
                        </li>
                        <?php if (function_exists('has_permission') && has_permission('project_delete')): ?>
                        <li>
                            <form method="post" action="#">
                                <input type="hidden" value="<?php echo $recentProject->p_id;?>" name="del_id" />
                                <input type="hidden" value="<?php echo (isset($recentProject->trash) && (int)$recentProject->trash === 0) ? '1' : '0'; ?>" name="del_val" />
                                <button type="submit" name="del_proj">
                                    <?php echo ts_icon('delete'); ?> <?php echo $lang['Delete Project']; ?>
                                </button>
                            </form>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </td>
        </tr>
    <?php
        $rowIndex++;
    }
}
?>


