<?php
// AJAX endpoint: filter/search projects across all pages (admin/staff/client)
// Returns JSON: { success, results_html, pagination_html, total_records, total_pages, page }

ob_start();

header('Content-Type: application/json; charset=utf-8');

require_once("../includes/lib-initialize.php");

if (!($session->isLoggedIn())) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!isset($_SESSION['accountStatus'])) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$userType = (int)$_SESSION['accountStatus']; // 1=admin, 2=client, 3=staff
if (!in_array($userType, [1, 2, 3], true)) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

global $database;

$limit = 15;
$page = isset($_GET["page"]) ? max(1, (int)$_GET["page"]) : 1;
$start_from = ($page - 1) * $limit;

$viewType = (isset($_GET['view']) && $_GET['view'] === 'grid') ? 'grid' : 'table';
$showAllProjects = (isset($_GET['all_projects']) && $_GET['all_projects'] === '1');
$context = isset($_GET['context']) ? strtolower(trim((string)$_GET['context'])) : '';
if ($context === '') {
    $context = ($userType === 3) ? 'staff' : (($userType === 2) ? 'client' : 'admin');
}
if (!in_array($context, ['admin', 'staff', 'client'], true)) {
    $context = ($userType === 3) ? 'staff' : (($userType === 2) ? 'client' : 'admin');
}

$searchQuery = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? (string)$_GET['status'] : '';

$currentUserId = (int)$session->userId;
$tsMenuIco = 'tasksession-timer-log-menu-ico me-2';

// Conditions (role-specific access control)
$projectFilterCondition = '';
if ($userType === 1) {
    // Admin: My Projects vs All Projects
    if (!$showAllProjects) {
        $projectFilterCondition = " AND FIND_IN_SET($currentUserId, p.s_ids)";
    }
} elseif ($userType === 3) {
    // Staff: only allow all_projects when permission exists
    require_once(__DIR__ . '/../includes/permissions.php');
    $canViewAll = function_exists('has_permission') && has_permission('project_view_all');
    if (!$canViewAll) {
        $showAllProjects = false;
    }
    if (!$showAllProjects) {
        $projectFilterCondition = " AND FIND_IN_SET($currentUserId, p.s_ids)";
    }
} else {
    // Client: only own projects (main + additional)
    $showAllProjects = false;
    $projectFilterCondition = " AND (p.c_id = $currentUserId OR p.main_client_id = $currentUserId OR FIND_IN_SET($currentUserId, p.c_ids))";
}

// Client permissions object for UI (payments link)
$taskPermissions = $taskPermissions ?? null;
if ($userType === 2) {
    $permFile = __DIR__ . '/../includes/task_permission.php';
    if ($taskPermissions === null && file_exists($permFile)) {
        require_once($permFile);
        if (class_exists('TaskPermission')) {
            $taskPermissions = TaskPermission::getOrCreate($currentUserId);
        }
    }
}

// Currency symbol (safe default)
$currency_symbol = '$';
if (class_exists('settings')) {
    $settings = settings::findById(1);
    if ($settings && isset($settings->system_currency) && !empty($settings->system_currency)) {
        $currency_parts = explode(',', (string)$settings->system_currency);
        if (count($currency_parts) >= 2) {
            $currency_symbol = trim($currency_parts[1]);
        }
    }
}

$statusCondition = '';
if ($statusFilter !== '' && ($statusFilter === '0' || $statusFilter === '1')) {
    $statusCondition = " AND p.status = " . (int)$statusFilter;
}

$joinSql = '';
$searchCondition = '';
if ($searchQuery !== '') {
    // Search in project title + client names (main + additional)
    $safeSearch = $database->escapeValue($searchQuery);
    $like = "%{$safeSearch}%";

    $joinSql = " LEFT JOIN users u ON (u.id = p.c_id OR u.id = p.main_client_id OR FIND_IN_SET(u.id, p.c_ids)) ";
    $searchCondition = " AND (
        p.project_title LIKE '{$like}'
        OR u.firstName LIKE '{$like}'
    ) ";
}

// Data query (distinct because the JOIN can duplicate projects)
$sqlBase =
    " FROM projects p " .
    $joinSql .
    " WHERE p.archive = 0 AND p.trash != 1 " .
    $projectFilterCondition .
    $statusCondition .
    $searchCondition;

$recentProjects = Projects::findBySql(
    "SELECT DISTINCT p.* " .
    $sqlBase .
    " ORDER BY p.p_id DESC " .
    " LIMIT $start_from, $limit"
);

// Total count
$countResult = $database->query("SELECT COUNT(DISTINCT p.p_id) AS total " . $sqlBase);
$countRow = $database->fetchArray($countResult);
$total_records = isset($countRow['total']) ? (int)$countRow['total'] : 0;
$total_pages = (int)ceil($total_records / $limit);

// Build pagination query string (exclude page because links will include it)
$paginationParams = [];
if ($showAllProjects) {
    $paginationParams[] = 'all_projects=1';
}
if ($statusFilter !== '' && ($statusFilter === '0' || $statusFilter === '1')) {
    $paginationParams[] = 'status=' . urlencode($statusFilter);
}
if ($viewType === 'grid') {
    $paginationParams[] = 'view=grid';
}
if ($searchQuery !== '') {
    $paginationParams[] = 'search=' . urlencode($searchQuery);
}
$paginationQueryString = !empty($paginationParams) ? '&' . implode('&', $paginationParams) : '';

// Render results HTML
ob_start();

// Use role-aware partials
$partialsRoot = __DIR__ . '/../partials';
$tablePartial = $partialsRoot . '/projects_table_rows.php';
$gridPartial = $partialsRoot . '/projects_grid_cards.php';
if ($context === 'staff') {
    $tablePartial = $partialsRoot . '/staff/projects_table_rows.php';
    $gridPartial = $partialsRoot . '/staff/projects_grid_cards.php';
} elseif ($context === 'client') {
    $tablePartial = $partialsRoot . '/client/projects_table_rows.php';
    $gridPartial = $partialsRoot . '/client/projects_grid_cards.php';
}

if ($viewType === 'table') {
    include($tablePartial);
} else {
    include($gridPartial);
}
$results_html = ob_get_clean();
goto render_pagination;

if ($viewType === 'table') {
    if (empty($recentProjects)) {
        // Keep same empty-state shape as the page
        echo '<tr><td colspan="9">' . $lang['No projects available. Create a new project to proceed.'] . '</td></tr>';
    } else {
        $counter = 1;
        foreach ($recentProjects as $recentProject) {
            ?>
            <tr>
                <td class="bs-checkbox">
                    <input data-index="<?php echo $counter; ?>" value="<?php echo $recentProject->p_id ?>" name="btSelectItem" type="checkbox">
                </td>
                <td><div class="tbl-ttl">
                    <?php echo $recentProject->project_title;?><div>
                </td>
                <td class="clients-rpt" style="text-align: left;">
                 <div class="d-flex align-items-center">
                <?php 
                  $s_ids = $recentProject->s_ids;
                  $st_ids = explode(',', $s_ids);
                  $counter2 = 0;
                  foreach($st_ids as $st_id){
                      if($st_id != $recentProject->c_id && $st_id != 0){
                          $counter2++;
                          if($counter2 > 3){} else {

                    $user2 = user::findById($st_id); 
                    echo '<div class="user-box">';
                    echo '<form action="../discussion?project_id='.$recentProject->p_id .'" method="post"><input type="hidden" name="user_id" value="'.$st_id.'" /><input type="hidden" name="project_id" value="'.$recentProject->p_id .'" /><button name="chat" type="submit" data-bs-toggle="tooltip" data-bs-placement="top" title="'.$user2->firstName.'">';
                    echo getUserAvatarHtml($st_id, $user2->firstName, $user2->lastName ?? '', 36, 36, 'img-fluid rounded-circle', $user2->firstName);
                    echo '</button></form>';
                    echo '</div>'; 
                  }
                      }
                      }
                 
                  if($counter2 > 3){
                      $more = $counter2-3;
                       echo '<div class="plus-more">+'. $more .'</div>'; 
                  }
                  ?>
                </div>
                </td>
                <td class="clients-rpt">
                 <div class="d-flex align-items-center">
                    <?php 
                // Display main client first (with crown icon)
                $mainClient = user::findById($recentProject->main_client_id ?: $recentProject->c_id); 
                if ($mainClient) {
                    echo '<div class="user-box">';
                    echo '<form action="../discussion?project_id='.$recentProject->p_id .'" method="post"><input type="hidden" name="user_id" value="'.$mainClient->id.'" /><input type="hidden" name="project_id" value="'.$recentProject->p_id .'" /><button name="chat" type="submit" data-bs-toggle="tooltip" data-bs-placement="top" title="'.$mainClient->firstName.' (Main)">';
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
                    foreach($additionalClients as $clientId) {
                        $clientCounter++;
                        if($clientCounter > 2) break; // Show max 2 additional clients in grid
                        
                        $client = user::findById($clientId);
                        if ($client) {
                            echo '<div class="user-box">';
                            echo '<form action="../discussion?project_id='.$recentProject->p_id .'" method="post"><input type="hidden" name="user_id" value="'.$client->id.'" /><input type="hidden" name="project_id" value="'.$recentProject->p_id .'" /><button name="chat" type="submit" data-bs-toggle="tooltip" data-bs-placement="top" title="'.$client->firstName.'">';
                            echo getUserAvatarHtml($client->id, $client->firstName, $client->lastName ?? '', 36, 36, 'img-fluid rounded-circle', $client->firstName);
                            echo '</button></form>';
                            echo '</div>';
                        }
                    }
                    
                    if(count($additionalClients) > 2) {
                        $more = count($additionalClients) - 2;
                        echo '<div class="plus-more">+'. $more .'</div>';
                    }
                }
                 ?></div>
                </td>
                <td class="red-text">
                    <?php echo $recentProject->end_time;?>
                </td>
                <td class="prostatus">
                    <?php $status = $recentProject->status;
                  $archive = $recentProject->archive;
                  $trash = $recentProject->trash;
                  if($status == 0){ ?> <span class="badge inprogress"><?php echo $lang['In Progress']; ?></span>
                                <?php } else { ?> <span class="badge completed"><?php echo $lang['Completed']; ?></span>
                                    <?php }?>
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
                                    <?php echo ts_icon('info', $tsMenuIco); ?><?php echo $lang['Overview']; ?>
                                </a>
                            </li>
                            <li>
                                <form action="../discussion?project_id=<?php echo $recentProject->p_id;?>" method="post">
                                    <input type="hidden" name="user_id" value="<?php echo $recentProject->c_id;?>" />
                                    <input type="hidden" name="project_id" value="<?php echo $recentProject->p_id;?>" />
                                    <button type="submit" name="chat" class="dropdown-item">
                                        <?php echo ts_icon('chat', $tsMenuIco); ?> <?php echo $lang['Discussion']; ?>
                                    </button>
                                </form>
                            </li>
                            <li>
                                <a href="task?projectId=<?php echo $recentProject->p_id; ?>">
                                    <?php echo ts_icon('tasks', $tsMenuIco); ?><?php echo $lang['Tasks']; ?>
                                </a>
                            </li>
                            <li>
                                <a href="edit-project?id=<?php echo $recentProject->p_id;?>"><?php echo ts_icon('edit', $tsMenuIco); ?> <?php echo $lang['Edit Project']; ?></a>
                            </li>
                            <li>
                                <form method="post" action="#">
                                    <input type="hidden" value="<?php echo $recentProject->p_id;?>" name="comp_id" />
                                    <input type="hidden" value="<?php if($status == 0){ echo '1';}else { echo '0';} ?>" name="comp_val" />
                                    <button type="submit" name="comp_proj">
                                        <?php if($status == 0){
                                echo ts_icon('check-circle', $tsMenuIco) . ' ' . $lang['Mark as complete'];
                                }else{ 
                                echo ts_icon('check', $tsMenuIco) . $lang['Re-open'];
                                } ?> </button>
                                </form>
                            </li>
                            <li>
                                <form method="post" action="#">
                                    <input type="hidden" value="<?php echo $recentProject->p_id;?>" name="arc_id" />
                                    <input type="hidden" value="<?php if($archive == 0){ echo '1';}else { echo '0';} ?>" name="arc_val" />
                                    <button type="submit" name="arc_proj" class="dropdown-item">
                                        <?php if($archive == 0){
                                echo ts_icon('archive', $tsMenuIco) . ' ' . $lang['Move to Archive'];
                                }else{ 
                                echo ts_icon('archive', $tsMenuIco) . ' ' . $lang['Move to Projects'];
                                } ?> </button>
                                </form>
                            </li>
                            <li>
                                <form method="post" action="#">
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
            $counter++;
        }
    }
} else {
    // Grid view cards
    foreach ($recentProjects as $recentProject) {
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
                        <li>
                            <form action="../discussion?project_id=<?php echo $recentProject->p_id;?>" method="post" style="display:inline;">
                                <input type="hidden" name="user_id" value="<?php echo $recentProject->c_id;?>" />
                                <input type="hidden" name="project_id" value="<?php echo $recentProject->p_id;?>" />
                                <button type="submit" name="chat" class="dropdown-item">
                                    <?php echo ts_icon('chat', $tsMenuIco); ?> <?php echo $lang['Discussion']; ?>
                                </button>
                            </form>
                        </li>
                        <li>
                            <a href="task?projectId=<?php echo $recentProject->p_id; ?>">
                                <?php echo ts_icon('tasks', $tsMenuIco); ?> <?php echo $lang['Tasks']; ?>
                            </a>
                        </li>
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
                    <div class="d-flex col-gap-40 mb-4 flex-wrap" style="text-align: left;">

                        <div class="clients-rpt" style="text-align: left;">
                            <div class="title-head mb-2"><?php echo $lang['Assigned Team']; ?></div>
                 <div class="d-flex align-items-center">
                    <?php 
                      $s_ids = $recentProject->s_ids;
                      $st_ids = explode(',', $s_ids);
                      $counter3 = 0;
                      foreach($st_ids as $st_id){
                          if($st_id != $recentProject->c_id && $st_id != 0){
                              $counter3++;
                              if($counter3 > 3){} else {

                    $user2 = user::findById($st_id); 
                    echo '<div class="user-box">';
                    echo '<form action="../discussion?project_id='.$recentProject->p_id .'" method="post"><input type="hidden" name="user_id" value="'.$st_id.'" /><input type="hidden" name="project_id" value="'.$recentProject->p_id .'" /><button name="chat" type="submit" data-bs-toggle="tooltip" data-bs-placement="top" title="'.$user2->firstName.'">';
                    echo getUserAvatarHtml($st_id, $user2->firstName, $user2->lastName ?? '', 36, 36, 'img-fluid rounded-circle', $user2->firstName);
                    echo '</button></form>';
                    echo '</div>'; 
                  }
                          }
                          }
                 
                  if($counter3 > 3){
                      $more = $counter3-3;
                       echo '<div class="plus-more shadow-dept">+'. $more .'</div>'; 
                  }
                  ?>
                        </div>        </div>
                        <div class="clients mb-2">
                    <div class="title-head mb-2"><?php echo $lang['Clients']; ?></div>
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
                                'lastName' => $mainClient->lastName ?? '',
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
                                        'lastName' => $client->lastName ?? '',
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
                        
                        // Show + indicator if there are more than 2 clients
                        if(count($allClients) > 2) {
                            $more = count($allClients) - 2;
                            echo '<div class="plus-more shadow-dept">+'. $more .'</div>';
                        }
                     ?>
                        </div>
                        </div>
                        </div>
                    <!-- Progress Bar and Task Completion (dynamic) -->
                    <div class="progress mb-2" style="height:8px;">
                        <div class="progress-bar" role="progressbar" style="width: <?php echo $percent; ?>%; background: <?php echo $percent < 30 ? '#f66' : ($percent < 70 ? '#f9b233' : '#4caf50'); ?>;" aria-valuenow="<?php echo $percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="mb-2 d-flex col-gap-5"><div class="grey bold"><?php echo $lang['Task']; ?></div> <?php echo $completedTaskCount; ?>/<?php echo $taskCount; ?> <span class="text-align-right flex-grow"><?php echo $percent; ?>%</span></div>
                </div>
            </div>
        </div>
        <?php
    }
}

$results_html = ob_get_clean();

// Render pagination HTML (only when more than one page of results)
render_pagination:
$pagination_html = '';
if ($total_records > $limit) {
    ob_start();
    $startVal = ($total_records > 0) ? ($start_from + 1) : 0;
    $endVal = ($total_records > 0) ? min($start_from + count($recentProjects), $total_records) : 0;
    ?>
<div class="row pagination-box">
    <div class="col-md-6 resilts-txt">
        <?php echo $lang['Showing']; ?> <span class="start_val"><?php echo (int)$startVal;?></span>
            <?php echo $lang['to']; ?> <span class="end_val"><?php echo (int)$endVal; ?></span>
                <?php echo $lang['of']; ?>
                    <?php echo (int)$total_records;?>
                        <?php echo $lang['entries']; ?>
    </div>
    <div class="col-md-6">
        <?php
            $pagLink = '';
            echo '<nav aria-label="Page navigation"><ul class="pagination justify-content-end">';
            echo '<li class="page-item">
                  <a class="page-link" href="?page=1'.$paginationQueryString.'" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                    <span class="sr-only">'.$lang['Previous'].'</span>
                  </a>
                </li>
            ';
            for ($i=1; $i<=$total_pages; $i++) {
                $pagLink .= "<li class='page-item'><a class='page-link' href='?page=".$i.$paginationQueryString."'>".$i."</a></li>";
            }
            echo $pagLink . '
            <li class="page-item">
                  <a class="page-link" href="?page='.$total_pages.$paginationQueryString.'" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                    <span class="sr-only">'.$lang['Next'].'</span>
                  </a>
                </li>
            </ul></nav>';
        ?>
    </div>
</div>
    <?php
    $pagination_html = ob_get_clean();
}

ob_clean();
echo json_encode([
    'success' => true,
    'results_html' => $results_html,
    'pagination_html' => $pagination_html,
    'total_records' => $total_records,
    'total_pages' => $total_pages,
    'page' => $page,
]);
exit;


