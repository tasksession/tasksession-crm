<?php
/**
 * Profile-style project card (admin profile.php projects tab).
 * Requires: $recentProject, $percent, $taskCount, $completedTaskCount, $lang
 */
if (!isset($recentProject)) {
    return;
}
?>
<div class="col-xl-4 col-lg-6 col-md-6 col-sm-6 col-12">
    <div class="card project-card" style="position: relative;">
        <div class="dropdown card-action-dropdown" style="position: absolute; top: 12px; right: 16px;">
            <button class="btn btn-link dropdown-toggle" type="button" id="dropdownMenu<?php echo (int) $recentProject->p_id; ?>" data-bs-toggle="dropdown" aria-expanded="false" style="color: #333; font-size: 20px; text-decoration: none;">
                <span class="light-grey" style="font-size: 20px; letter-spacing: &#8226;">&#8226;&#8226;&#8226;</span>
            </button>
            <ul class="dropdown-menu" aria-labelledby="dropdownMenu<?php echo (int) $recentProject->p_id; ?>">
                <li>
                    <a class="dropdown-item" href="overview?projectId=<?php echo (int) $recentProject->p_id; ?>">
                        <?php echo ts_icon('clock', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Overview']; ?>
                    </a>
                </li>
                <li>
                    <form action="../discussion?project_id=<?php echo (int) $recentProject->p_id; ?>" method="post" style="display:inline;">
                        <input type="hidden" name="user_id" value="<?php echo (int) $recentProject->c_id; ?>" />
                        <input type="hidden" name="project_id" value="<?php echo (int) $recentProject->p_id; ?>" />
                        <button type="submit" name="chat" class="dropdown-item">
                            <?php echo ts_icon('document-text', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Discussion']; ?>
                        </button>
                    </form>
                </li>
                <li>
                    <a class="dropdown-item" href="task?projectId=<?php echo (int) $recentProject->p_id; ?>">
                        <?php echo ts_icon('inbox-stack', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Tasks']; ?>
                    </a>
                </li>
                <li>
                    <a href="edit-project?id=<?php echo (int) $recentProject->p_id; ?>" class="dropdown-item"><?php echo ts_icon('edit', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Edit Project']; ?></a>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="due-date mb-2">
                <?php
                $due = strtotime((string) $recentProject->end_time);
                $now = strtotime(date('Y-m-d'));
                $daysLeft = (int) ceil(($due - $now) / 86400);
                if ($daysLeft > 1) {
                    echo "<span class='badge success'>DUE: {$daysLeft} DAY LEFT</span>";
                } elseif ($daysLeft === 1) {
                    echo "<span class='badge red-badge'>DUE: 1 DAY LEFT</span>";
                } elseif ($daysLeft === 0) {
                    echo "<span class='badge red-badge'>DUE: TODAY</span>";
                } else {
                    echo "<span class='badge red-badge'>DUE PASSED</span>";
                }
                ?>
            </div>
            <h5 class="card-title mb-4"><?php echo htmlspecialchars((string) $recentProject->project_title, ENT_QUOTES, 'UTF-8'); ?></h5>
            <div class="d-flex col-gap-40 mb-4 flex-wrap" style="text-align: left;">
                <div class="clients-rpt" style="text-align: left;">
                    <div class="title-head mb-2"><?php echo $lang['Assigned Team']; ?></div>
                    <div class="d-flex align-items-center">
                        <?php
                        $st_ids = explode(',', (string) ($recentProject->s_ids ?? ''));
                        $counter = 0;
                        foreach ($st_ids as $st_id) {
                            $st_id = (int) $st_id;
                            if ($st_id !== (int) $recentProject->c_id && $st_id !== 0) {
                                $counter++;
                                if ($counter <= 3) {
                                    $user2 = user::findById($st_id);
                                    if ($user2) {
                                        echo '<div class="user-box">';
                                        echo getUserAvatarHtml($st_id, $user2->firstName, $user2->lastName ?? '', 36, 36, '', $user2->firstName);
                                        echo '</div>';
                                    }
                                }
                            }
                        }
                        if ($counter > 3) {
                            echo '<div class="plus-more shadow-dept">+' . ($counter - 3) . '</div>';
                        }
                        ?>
                    </div>
                </div>
                <div class="clients mb-2">
                    <div class="title-head mb-2"><?php echo $lang['Clients']; ?></div>
                    <div class="d-flex align-items-center">
                        <?php
                        $mainClient = user::findById((int) ($recentProject->main_client_id ?: $recentProject->c_id));
                        if ($mainClient) {
                            echo '<div class="user-box">';
                            echo getUserAvatarHtml($mainClient->id, $mainClient->firstName, $mainClient->lastName ?? '', 36, 36, '', $mainClient->firstName);
                            echo '</div>';
                        }
                        if (!empty($recentProject->c_ids)) {
                            $allClientIds = array_filter(explode(',', (string) $recentProject->c_ids));
                            $additionalClients = array_filter($allClientIds, static function ($id) use ($recentProject) {
                                return (int) $id !== (int) $recentProject->main_client_id && (int) $id !== (int) $recentProject->c_id;
                            });
                            $clientCounter = 0;
                            foreach ($additionalClients as $clientId) {
                                $clientCounter++;
                                if ($clientCounter > 1) {
                                    break;
                                }
                                $client = user::findById((int) $clientId);
                                if ($client) {
                                    echo '<div class="user-box">';
                                    echo getUserAvatarHtml($client->id, $client->firstName, $client->lastName ?? '', 36, 36, '', $client->firstName);
                                    echo '</div>';
                                }
                            }
                            if (count($additionalClients) > 1) {
                                echo '<div class="plus-more shadow-dept">+' . (count($additionalClients) - 1) . '</div>';
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
            <div class="progress mb-2" style="height:8px;">
                <div class="progress-bar" role="progressbar" style="width: <?php echo (int) $percent; ?>%; background: <?php echo $percent < 30 ? '#f66' : ($percent < 70 ? '#f9b233' : '#4caf50'); ?>;" aria-valuenow="<?php echo (int) $percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <div class="mb-2 d-flex col-gap-5">
                <div class="grey bold"><?php echo $lang['TASK']; ?></div>
                <?php echo (int) $completedTaskCount; ?>/<?php echo (int) $taskCount; ?>
                <span class="text-align-right flex-grow"><?php echo (int) $percent; ?>%</span>
            </div>
        </div>
    </div>
</div>
