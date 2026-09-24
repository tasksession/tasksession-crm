<?php
/**
 * Partial: Project grid cards (inside #project-grid row)
 * Expects:
 * - $recentProjects (array of Projects)
 * Uses globals/vars from parent scope: $lang, $database
 */
// Load settings for module check
if (!isset($settingsForModules)) {
    $settingsForModules = isset($dash_settings) && $dash_settings ? $dash_settings : settings::findById(1);
}
$isDiscussionsEnabled = ($settingsForModules && !empty($settingsForModules->module_discussions));
$isInvoicesEnabled = ($settingsForModules && !empty($settingsForModules->module_invoices));
$tsMenuIco = 'tasksession-timer-log-menu-ico me-2';
?>
<?php
if (empty($recentProjects)) {
    echo '<div class="col-12"><div class="alert alert-info mb-0">'.$lang['No projects available. Create a new project to proceed.'].'</div></div>';
} else {
    foreach($recentProjects as $recentProject):
        $projectId = $recentProject->p_id;
        global $database;
        // Total tasks
        $taskQuery = "SELECT COUNT(*) as task_count FROM tasks WHERE project_id = $projectId";
        $taskResult = $database->query($taskQuery);
        $taskCount = 0;
        if($taskRow = $database->fetchArray($taskResult)) {
            $taskCount = $taskRow['task_count'];
        }
        // Completed tasks
        $completedTaskQuery = "SELECT COUNT(*) as completed_count FROM tasks WHERE project_id = $projectId AND status = 'done'";
        $completedTaskResult = $database->query($completedTaskQuery);
        $completedTaskCount = 0;
        if($completedTaskRow = $database->fetchArray($completedTaskResult)) {
            $completedTaskCount = $completedTaskRow['completed_count'];
        }
        $percent = ($taskCount > 0) ? round(($completedTaskCount / $taskCount) * 100) : 0;
        ?>
        <div class="col-md-4  col-lg-4 col-xl-3 mb-2">
            <div class="card project-card" style="position: relative;">
                <!-- 2-dot dropdown menu -->
                <div class="dropdown card-action-dropdown" style="position: absolute; top: 12px; right: 16px;">
                    <button class="btn btn-link dropdown-toggle" type="button" id="dropdownMenu<?php echo $recentProject->p_id; ?>" data-bs-toggle="dropdown" aria-expanded="false" style="color: #333; font-size: 20px; text-decoration: none;">
                        <span class="light-grey" style="font-size: 20px; letter-spacing: &#8226;">&#8226;&#8226;&#8226;</span>
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="dropdownMenu<?php echo $recentProject->p_id; ?>">
                        <li>
                            <a href="overview?projectId=<?php echo $recentProject->p_id; ?>">
                                <?php echo ts_icon('info', $tsMenuIco); ?><?php echo $lang['Overview']; ?>
                            </a>
                        </li>
                        <?php if ($isDiscussionsEnabled): ?>
                        <li>
                            <form action="../discussion?project_id=<?php echo $recentProject->p_id;?>" method="post" style="display:inline;">
                                <input type="hidden" name="user_id" value="<?php echo $recentProject->c_id;?>" />
                                <input type="hidden" name="project_id" value="<?php echo $recentProject->p_id;?>" />
                                <button type="submit" name="chat" class="dropdown-item">
                                    <?php echo ts_icon('chat', $tsMenuIco); ?> <?php echo $lang['Discussion']; ?>
                                </button>
                            </form>
                        </li>
                        <?php endif; ?>
                        <li>
                            <a href="task?projectId=<?php echo $recentProject->p_id; ?>">
                                <?php echo ts_icon('tasks', $tsMenuIco); ?> <?php echo $lang['Tasks']; ?>
                            </a>
                        </li>
                        <?php if ($isInvoicesEnabled): ?>
                        <li>
                            <a href="payments?projectId=<?php echo $recentProject->p_id; ?>">
                                <?php echo ts_icon('payments', $tsMenuIco); ?> <?php echo $lang['Payments']; ?>
                            </a>
                        </li>
                        <?php endif; ?>
                        <li>
                            <a href="edit-project?id=<?php echo $recentProject->p_id;?>" class="dropdown-item"><?php echo ts_icon('edit', $tsMenuIco); ?> <?php echo $lang['Edit Project']; ?></a>
                        </li>
                        <li>
                            <form method="post" action="#" style="display:inline;">
                                <input type="hidden" value="<?php echo $recentProject->p_id;?>" name="comp_id" />
                                <input type="hidden" value="<?php if($recentProject->status == 0){ echo '1';}else { echo '0';} ?>" name="comp_val" />
                                <button type="submit" name="comp_proj" class="dropdown-item">
                                    <?php if($recentProject->status == 0){
                                            echo ts_icon('check-circle', $tsMenuIco) . ' ' . $lang['Mark as complete'];
                                        }else{ 
                                            echo ts_icon('check', $tsMenuIco) . $lang['Re-open'];
                                        } ?> </button>
                            </form>
                        </li>
                        <li>
                            <form method="post" action="#" style="display:inline;">
                                <input type="hidden" value="<?php echo $recentProject->p_id;?>" name="arc_id" />
                                <input type="hidden" value="<?php if($recentProject->archive == 0){ echo '1';}else { echo '0';} ?>" name="arc_val" />
                                <button type="submit" name="arc_proj" class="dropdown-item">
                                    <?php if($recentProject->archive == 0){
                                            echo ts_icon('archive', $tsMenuIco) . ' ' . $lang['Move to Archive'];
                                        }else{ 
                                            echo ts_icon('archive', $tsMenuIco) . ' ' . $lang['Move to Projects'];
                                        } ?> </button>
                            </form>
                        </li>
                        <li>
                            <form method="post" action="#" style="display:inline;">
                                <input type="hidden" value="<?php echo $recentProject->p_id;?>" name="del_id" />
                                <input type="hidden" value="<?php if($recentProject->trash == 0){ echo '1';}else { echo '0';} ?>" name="del_val" />
                                <button type="submit" name="del_proj" class="dropdown-item">
                                    <?php echo ts_icon('delete', $tsMenuIco); ?> <?php echo $lang['Delete Project']; ?>
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <!-- Due Date -->
                    <div class="due-date mb-2">
                        <?php
                            $due = strtotime($recentProject->end_time);
                            $now = strtotime(date('Y-m-d'));
                            $daysLeft = ceil(($due - $now) / 86400);
                            if ($daysLeft > 1) {
                                echo "<span class='badge success'>DUE: $daysLeft DAY LEFT</span>";
                            } elseif ($daysLeft == 1) {
                                echo "<span class='badge red-badge'>DUE: 1 DAY LEFT</span>";
                            } elseif ($daysLeft == 0) {
                                echo "<span class='badge red-badge'>DUE: TODAY</span>";
                            } else {
                                echo "<span class='badge red-badge'>DUE PASSED</span>";
                            }
                        ?>
                    </div>
                    <!-- Project Title -->
                    <h5 class="card-title mb-4"><?php echo htmlspecialchars($recentProject->project_title); ?></h5>
                    <!-- Assigned Team + Clients -->
                    <div class="d-flex col-gap-40 mb-4 flex-wrap" style="text-align: left;">
                        <div class="clients-rpt" style="text-align: left;">
                            <div class="title-head mb-2"><?php echo $lang['Assigned Team']; ?></div>
                            <div class="d-flex align-items-center">
                                <?php 
                                  $s_ids = $recentProject->s_ids;
                                  $st_ids = explode(',', $s_ids);
                                  $teamCounter = 0;
                                  foreach($st_ids as $st_id){
                                      if($st_id != $recentProject->c_id && $st_id != 0){
                                          $teamCounter++;
                                          if($teamCounter > 3){} else {
                                            $user2 = user::findById($st_id); 
                                            echo '<div class="user-box">';
                                            echo '<form action="../discussion?project_id='.$recentProject->p_id .'" method="post"><input type="hidden" name="user_id" value="'.$st_id.'" /><input type="hidden" name="project_id" value="'.$recentProject->p_id .'" /><button name="chat" type="submit" data-bs-toggle="tooltip" data-bs-placement="top" title="'.$user2->firstName.'">';
                                            echo getUserAvatarHtml($st_id, $user2->firstName, $user2->lastName ?? '', 36, 36, 'img-fluid rounded-circle', $user2->firstName);
                                            echo '</button></form>';
                                            echo '</div>'; 
                                          }
                                      }
                                  }
                                  if($teamCounter > 3){
                                      $more = $teamCounter-3;
                                       echo '<div class="plus-more shadow-dept">+'. $more .'</div>'; 
                                  }
                                  ?>
                            </div>
                        </div>
                        <div class="clients mb-2">
                            <div class="title-head mb-2"><?php echo $lang['Clients']; ?></div>
                            <div class="d-flex align-items-center">
                                <?php 
                                    $allClients = [];
                                    $mainClient = user::findById($recentProject->main_client_id ?: $recentProject->c_id); 
                                    if ($mainClient) {
                                        $allClients[] = [
                                            'id' => $mainClient->id,
                                            'firstName' => $mainClient->firstName,
                                            'lastName' => $mainClient->lastName ?? '',
                                            'isMain' => true
                                        ];
                                    }
                                    if (!empty($recentProject->c_ids)) {
                                        $allClientIds = array_filter(explode(',', $recentProject->c_ids));
                                        $additionalClients = array_filter($allClientIds, function($id) use ($recentProject) {
                                            return $id != $recentProject->main_client_id && $id != $recentProject->c_id;
                                        });
                                        foreach($additionalClients as $clientId) {
                                            $client = user::findById($clientId);
                                            if ($client) {
                                                $allClients[] = [
                                                    'id' => $client->id,
                                                    'firstName' => $client->firstName,
                                                    'lastName' => $client->lastName ?? '',
                                                    'isMain' => false
                                                ];
                                            }
                                        }
                                    }
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
                        </div>
                    </div>
                    <!-- Progress -->
                    <div class="progress mb-2" style="height:8px;">
                        <div class="progress-bar" role="progressbar" style="width: <?php echo $percent; ?>%; background: <?php echo $percent < 30 ? '#f66' : ($percent < 70 ? '#f9b233' : '#4caf50'); ?>;" aria-valuenow="<?php echo $percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="mb-2 d-flex col-gap-5"><div class="grey bold"><?php echo $lang['Task']; ?></div> <?php echo $completedTaskCount; ?>/<?php echo $taskCount; ?> <span class="text-align-right flex-grow"><?php echo $percent; ?>%</span></div>
                </div>
            </div>
        </div>
    <?php endforeach;
}
?>


