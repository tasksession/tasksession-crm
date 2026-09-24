<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : project-sidebar.php
   Purpose : Project sidebar template for displaying project details and team information
 ================================================================================
*/

// Ensure required variables exist
if (!isset($project) || !$project) {
    echo "<!-- Project sidebar not shown: project not set -->";
    return;
}

// Default values if variables aren't passed
$taskCount = isset($taskCount) ? $taskCount : 0;
$completedTaskCount = isset($completedTaskCount) ? $completedTaskCount : 0;
$totalDaysLeft = isset($totalDaysLeft) ? $totalDaysLeft : 0;
$totalBudget = isset($totalBudget) ? $totalBudget : 0;
$paidAmount = isset($paidAmount) ? $paidAmount : 0;
$unpaidAmount = isset($unpaidAmount) ? $unpaidAmount : 0;
$client = isset($client) ? $client : null;
$staffMembers = isset($staffMembers) ? $staffMembers : [];

// Hide Payments & Invoice widget for clients without can_view_milestones and staff without milestone_view
$showPaymentsWidget = true;
if (isset($_SESSION['accountStatus']) && $_SESSION['accountStatus'] == 2) {
    require_once __DIR__ . '/../includes/task_permission.php';
    $clientId = isset($_SESSION['userId']) ? $_SESSION['userId'] : null;
    $clientPermissions = $clientId ? TaskPermission::getOrCreate($clientId) : null;
    if (!$clientPermissions || !$clientPermissions->can_view_milestones) {
        $showPaymentsWidget = false;
    }
} elseif (isset($_SESSION['accountStatus']) && $_SESSION['accountStatus'] == 3) {
    // Check staff permissions for milestone_view
    if (!has_permission('milestone_view')) {
        $showPaymentsWidget = false;
    }
}

// Helper function to get color index for avatar
function getAvatarColorIndex($userId) {
    return ($userId % 8) + 1;
}

// Helper function to get user initials
function getUserInitials($firstName, $lastName = '') {
    $displayName = trim($firstName . ' ' . $lastName);
    $initials = '';
    
    if (!empty($firstName)) {
        $initials = strtoupper(substr($firstName, 0, 1));
        if (!empty($lastName)) {
            $initials .= strtoupper(substr($lastName, 0, 1));
        }
    } else {
        $initials = strtoupper(substr($displayName, 0, 1));
    }
    
    return empty($initials) ? 'U' : $initials;
}

// Helper function to get currency symbol from client
function getClientCurrencySymbol($client) {
    if (!$client || !isset($client->currency) || empty($client->currency)) {
        return '$';
    }
    
    // Extract symbol from "CODE,SYMBOL" format
    $currencyParts = explode(',', $client->currency);
    if (count($currencyParts) > 1) {
        return trim($currencyParts[1]);
    } else {
        return trim($currencyParts[0]);
    }
}
?>
<!-- Add Sidebar Toggle Button -->
<div class="filter-btn">
<button class="sidebar-toggle primary-btn d-md-none">
    <?php echo $lang['View Project Details']; ?>
</button></div>
<!-- Add Overlay -->
<div class="sidebar-overlay"></div>
	<div class="sidebar-admin col-xl-3 col-lg-4 col-md-12 bg-white max-w-400 full-heights left-col project-sidebar" id="project-sidebar" data-presence-ajax="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>ajax/ajax_last_seen.php"><div class="cross-mobile ">
			<?php echo ts_icon('close', 'w-4'); ?>
				</div> 
				<button class="sidebar-shrink-btn" id="projectSidebarShrinkBtn" title="Shrink sidebar">
				  <?php echo ts_icon('chevron-left', 'w-2'); ?>
				</button>
	<div class="sidebar-shirk"></div>
	<div class="scroll-bar mb-height full-height pd-30 stikcy-sidebar">
       <!-- Project Title -->
    <div class="cs-card">
        <div class="card-body pt-0">
            <div class="title-head mb-1"><?php echo $lang['Project Title']; ?></div>
            <h2 class="project-title"><?php echo $project->project_title; ?></h2>
        </div>
    </div>
    <!-- Task Information -->
    <div class="cs-card">
        <div class="card-body">
		<div class="align-all">
           
            <div class="title-head"><?php echo $lang['Task Information']; ?></div>
			   <span class="badge bg-light text-dark rounded-pill"><?php echo $taskCount; ?></span>
             </div>
            <!-- Progress Bar -->
            <div class="align-all mb-2">
                <span><?php echo $lang['On schedule']; ?></span>
                <span class="text-success"><?php echo ($taskCount > 0) ? round(($completedTaskCount / $taskCount) * 100) : 0; ?>%</span>
            </div>
            <div class="progress mb-4">
                <div class="progress-bar bg-success" role="progressbar" 
                    style="width: <?php echo ($taskCount > 0) ? ($completedTaskCount / $taskCount * 100) : 0; ?>%" 
                    aria-valuenow="<?php echo $completedTaskCount; ?>" 
                    aria-valuemin="0" 
                    aria-valuemax="<?php echo $taskCount; ?>">
                </div>
            </div>
            
            <!-- Task Metrics -->
            <div class="d-flex text-center ">
                <div class="flex-grow">
                    <div class="metric-circle danger-circle">
                        <span><?php echo $taskCount - $completedTaskCount; ?></span>
                    </div>
                    <p class="mt-2"><?php echo $lang['Tasks left']; ?></p>
                </div>
                <div class="flex-grow">
                    <div class="metric-circle success-circle">
                        <span><?php echo $completedTaskCount; ?></span>
                    </div>
                    <p class="mt-2"><?php echo $lang['Complete']; ?></p>
                </div>
               
                <div class="flex-grow">
                    <div class="metric-circle neutral-circle">
                        <span><?php echo $totalDaysLeft; ?></span>
                    </div>
                    <p class="mt-2"><?php echo $lang['Days left']; ?></p>
                </div>
            </div>
        </div>
    </div>
<?php if ($showPaymentsWidget): ?>
    <!-- Payments & Invoice -->
    <div class="cs-card pay-card">
        <div class="card-body">
            <div class="align-all">
                <div class="title-head"><?php echo $lang['Payments & Invoice']; ?></div>
                <span class="badge bg-light text-dark rounded-pill"><?php echo $invoiceCount; ?></span>
            </div>
            <!-- Progress Bar -->
            <div class="align-all mb-2">
                <span><?php echo $lang['Payment Progress']; ?></span>
                <span class="text-success"><?php echo ($totalBudget > 0) ? round(($paidAmount / $totalBudget) * 100) : 0; ?>%</span>
            </div>
            <div class="progress mb-4">
                <div class="progress-bar bg-success" role="progressbar" 
                    style="width: <?php echo ($totalBudget > 0) ? ($paidAmount / $totalBudget * 100) : 0; ?>%" 
                    aria-valuenow="<?php echo $paidAmount; ?>" 
                    aria-valuemin="0" 
                    aria-valuemax="<?php echo $totalBudget; ?>">
                </div>
            </div>
            <div class="text-center">
                <div class="box-border d-flex flex-wrap m-0">
                    <?php
                    // Get currency symbol based on client's currency
                    $currency_symbol = '$';
                    if (isset($allClients) && count($allClients) > 0) {
                        $mainClient = $allClients[0];
                        $currency_symbol = getClientCurrencySymbol($mainClient);
                    }
                    ?>
                           <div class="counter flex-grow">
                         <h3 class="text-danger"><?php echo $currency_symbol . number_format($unpaidAmount, 0); ?></h3>
                         <p class="text-muted"><?php echo $lang['Unpaid']; ?></p>
                     </div>
                     <div class="counter flex-grow">
                         <h3 class="text-success"><?php echo $currency_symbol . number_format($paidAmount, 0); ?></h3>
                         <p class="text-muted"><?php echo $lang['Paid']; ?></p>
                     </div>
                     <div class="counter flex-grow last-expend">
                         <h3><?php echo $currency_symbol . number_format($totalBudget, 0); ?></h3>
                         <p class="text-muted"><?php echo $lang['Total']; ?></p>
                     </div>
                </div>  
            </div>
        </div>
    </div>
<?php endif; ?>
    
    <!-- Clients -->
    <div class="cs-card">
        <div class="card-body">
            <div class="align-all">
                <div class="title-head mb-2"><?php echo $lang['Clients']; ?></div>
                <span class="badge bg-light text-dark rounded-pill"><?php echo isset($allClients) ? count($allClients) : 0; ?></span>
            </div>
            
            <?php if(isset($allClients) && count($allClients) > 0): ?>
                <?php if(count($allClients) == 1): ?>
                    <!-- Single client display -->
                    <?php 
                    $client = $allClients[0];
                    // Online indicator logic for client
                    $isOnline = function_exists('user_presence_from_user')
                        ? user_presence_from_user($client)
                        : (
                            isset($client->last_seen) &&
                            isset($client->session_status) &&
                            $client->session_status === 'online' &&
                            (time() - (int)$client->last_seen) < 300
                        );
                    ?>
                    <div class="d-flex align-items-center clients-rpt">
                        <?php 
                        $clientId = (int)$client->id;
                        $stmt = $connect->prepare("SELECT filename FROM profile_pics WHERE fkUserId = ?");
                        $stmt->bind_param("i", $clientId);
                        $stmt->execute();
                        $query = $stmt->get_result();
                        $row = $query->fetch_assoc();
                        $image = $row ? $row['filename'] : null;
                        $stmt->close();
                        
                        // Get first letter of client name for avatar fallback
                        $firstLetter = getUserInitials($client->firstName, $client->lastName ?? '');
                        $colorIndex = getAvatarColorIndex($client->id);
                        ?>
                        
                        <div class="avatar-circle client-avatar" 
                             data-presence-user="<?php echo (int) $clientId; ?>"
                             data-bs-toggle="tooltip" 
                             data-bs-placement="top" 
                             title="<?php echo htmlspecialchars($client->firstName . ' ' . ($client->lastName ?? '')); ?>">
                            <span class="online-dot <?php echo $isOnline ? 'online' : 'offline'; ?>"></span>

                            <?php if($image): ?>
                                <img src="<?php echo $url; ?>includes/thumbnail.php?src=<?php echo $url; ?>uploads/profile-pics/<?php echo $image; ?>&h=40&w=40" class="rounded-circle img-fluid" alt="<?php echo $client->firstName; ?>">
                            <?php else: ?>
                                <div class="avatar-initials avatar-initials-medium color-<?php echo $colorIndex; ?>"><?php echo $firstLetter; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="client-details">
                            <h6 class="client-name"><?php echo $client->firstName; ?></h6>
                            <small class="text-muted"><?php echo !empty($client->title) ? $client->title : $lang['Client']; ?></small>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Multiple clients display with overlapping avatars -->
                    <div class="d-flex flex-wrap clients-rpt">
                        <?php 
                        $maxDisplay = 4; // Show this many avatars before showing "+X more"
                        $extraCount = count($allClients) - $maxDisplay;
                        
                        foreach($allClients as $index => $client): 
                            if($index < $maxDisplay):
                                $clientId = (int)$client->id;
                                $stmt = $connect->prepare("SELECT filename FROM profile_pics WHERE fkUserId = ?");
                                $stmt->bind_param("i", $clientId);
                                $stmt->execute();
                                $query = $stmt->get_result();
                                $row = $query->fetch_assoc();
                                $image = $row ? $row['filename'] : null;
                                $stmt->close();
                                
                                // Get initials for avatar fallback
                                $initials = getUserInitials($client->firstName, $client->lastName ?? '');
                                $colorIndex = getAvatarColorIndex($client->id);
                                
                                // Online indicator logic for client
                                $isOnline = function_exists('user_presence_from_user')
                                    ? user_presence_from_user($client)
                                    : (
                                        isset($client->last_seen) &&
                                        isset($client->session_status) &&
                                        $client->session_status === 'online' &&
                                        (time() - (int)$client->last_seen) < 300
                                    );
                        ?>
                            <div class="avatar-circle client-avatar" 
                                 data-presence-user="<?php echo (int) $clientId; ?>"
                                 data-bs-toggle="tooltip" 
                                 data-bs-placement="top" 
                                 title="<?php echo htmlspecialchars($client->firstName . ' ' . ($client->lastName ?? '')); ?>">
                                <span class="online-dot <?php echo $isOnline ? 'online' : 'offline'; ?>"></span>
                                <?php if($image): ?>
                                    <img src="<?php echo $url; ?>includes/thumbnail.php?src=<?php echo $url; ?>uploads/profile-pics/<?php echo $image; ?>&h=40&w=40" class="rounded-circle img-fluid" alt="<?php echo $client->firstName; ?>">
                                <?php else: ?>
                                   <div class="avatar-initials avatar-initials-medium color-<?php echo $colorIndex; ?>"><?php echo $initials; ?></div>
                                <?php endif; ?>
                            </div>
                        <?php 
                            endif;
                        endforeach; 
                        
                        // Show +X more if we have more clients than we're displaying
                        if($extraCount > 0):
                        ?>
                            <div class="avatar-circle extra-avatar">
                                <span>+<?php echo $extraCount; ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p class="text-muted"><?php echo $lang['No client assigned']; ?></p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Assigned Team -->
    <div class="cs-card">
        <div class="card-body border-none">
            <div class="align-all">
                <div class="title-head mb-2"><?php echo $lang['Assigned Team']; ?></div>
                <span class="badge bg-light text-dark rounded-pill"><?php echo count($staffMembers); ?></span>
            </div>
            
            <?php if(count($staffMembers) > 0): ?>
                <div class="d-flex flex-wrap clients-rpt">
                    <?php 
                    $maxDisplay = 4; // Show this many avatars before showing "+X more"
                    $extraCount = count($staffMembers) - $maxDisplay;
                    
                    foreach($staffMembers as $index => $staff): 
                        if($index < $maxDisplay):
                            $staffId = (int)$staff->id;
                            $stmt = $connect->prepare("SELECT filename FROM profile_pics WHERE fkUserId = ?");
                            $stmt->bind_param("i", $staffId);
                            $stmt->execute();
                            $query = $stmt->get_result();
                            $row = $query->fetch_assoc();
                            $image = $row ? $row['filename'] : null;
                            $stmt->close();
                            
                            // Get initials for avatar fallback
                            $initials = getUserInitials($staff->firstName, $staff->lastName ?? '');
                            $colorIndex = getAvatarColorIndex($staff->id);
                            
                            // Online indicator logic for staff
                            $isOnline = function_exists('user_presence_from_user')
                                ? user_presence_from_user($staff)
                                : (
                                    isset($staff->last_seen) &&
                                    isset($staff->session_status) &&
                                    $staff->session_status === 'online' &&
                                    (time() - (int)$staff->last_seen) < 300
                                );
                    ?>
                        <div class="avatar-circle staff-avatar" 
                             data-presence-user="<?php echo (int) $staffId; ?>"
                             data-bs-toggle="tooltip" 
                             data-bs-placement="top" 
                             title="<?php echo htmlspecialchars($staff->firstName . ' ' . ($staff->lastName ?? '')); ?>">
                            <span class="online-dot <?php echo $isOnline ? 'online' : 'offline'; ?>"></span>
                            <?php if($image): ?>
                                <img src="<?php echo $url; ?>includes/thumbnail.php?src=<?php echo $url; ?>uploads/profile-pics/<?php echo $image; ?>&h=40&w=40" class="rounded-circle img-fluid" alt="<?php echo $staff->firstName; ?>">
                            <?php else: ?>
                               <div class="avatar-initials avatar-initials-medium color-<?php echo $colorIndex; ?>"><?php echo $initials; ?></div>
                            <?php endif; ?>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                     // Show +X more if we have more staff than we're displaying
                    if($extraCount > 0):
                    ?>
                        <div class="avatar-circle extra-avatar">
                            <span>+<?php echo $extraCount; ?></span>
                        </div>
                    <?php endif; ?>
                </div>
				<?php else: ?>
                <p class="text-muted"><?php echo $lang['No team members assigned']; ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
(function() {
    if (window.__crmProjectPresenceBound) return;
    window.__crmProjectPresenceBound = true;
    var sidebar = document.getElementById('project-sidebar');
    if (!sidebar) return;
    var endpoint = sidebar.getAttribute('data-presence-ajax');
    if (!endpoint) return;

    function applyStatus(userId, isOnline) {
        var nodes = sidebar.querySelectorAll('[data-presence-user="' + userId + '"] .online-dot');
        for (var i = 0; i < nodes.length; i++) {
            nodes[i].classList.toggle('online', !!isOnline);
            nodes[i].classList.toggle('offline', !isOnline);
        }
    }

    function poll() {
        if (document.hidden) return;
        var els = sidebar.querySelectorAll('[data-presence-user]');
        var ids = [];
        var seen = {};
        for (var i = 0; i < els.length; i++) {
            var id = parseInt(els[i].getAttribute('data-presence-user'), 10);
            if (!id || seen[id]) continue;
            seen[id] = true;
            ids.push(id);
        }
        if (!ids.length) return;
        var url = endpoint + (endpoint.indexOf('?') >= 0 ? '&' : '?') + 'user_ids=' + ids.join(',');
        fetch(url, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var list = (data && data.users) ? data.users : [];
                for (var j = 0; j < list.length; j++) {
                    var row = list[j];
                    applyStatus(row.user_id, row.session_status === 'online');
                }
            })
            .catch(function() {});
    }

    poll();
    setInterval(poll, 30000);
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) poll();
    });
})();
</script>
</div>
 