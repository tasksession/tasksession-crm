<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : project-tabs.php
   Purpose : Project tabs template for displaying project navigation tabs
 ================================================================================
*/

// Check if project_id exists
if(isset($project_id) && $project_id > 0): 
    // Get current page to set active tab (pretty URL rewrite still exposes *.php in PHP_SELF)
    $current_page = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
    $current_script = strtolower(preg_replace('/\.php$/i', '', $current_page));
    $isTab = static function ($name) use ($current_page, $current_script) {
        $name = strtolower((string) $name);
        return $current_script === $name || $current_page === $name . '.php';
    };
    
    // Load settings if not already loaded (for module checks)
    if (!isset($settingsForModules)) {
        $settingsForModules = isset($dash_settings) && $dash_settings ? $dash_settings : settings::findById(1);
    }
    $isFileManagementEnabled = ($settingsForModules && !empty($settingsForModules->module_file_management));
    // Free edition: discussions + invoices/payments + files/media are Pro (tabs → upgrade shell)
    $isInvoicesEnabled = false;
    $isDiscussionsEnabled = false;
    if (function_exists('tasksession_is_free_edition') && tasksession_is_free_edition()) {
        $acctTab = isset($accountStatus) ? (int) $accountStatus : 0;
        if ($acctTab === 1 || $acctTab === 3) {
            $isDiscussionsEnabled = true; // tab → upgrade page
            $isInvoicesEnabled = true;    // tab → upgrade page
            $isFileManagementEnabled = true; // tab → upgrade page
        } else {
            $isFileManagementEnabled = false; // clients: hide
        }
    } else {
        $isInvoicesEnabled = ($settingsForModules && !empty($settingsForModules->module_invoices));
        $isDiscussionsEnabled = ($settingsForModules && !empty($settingsForModules->module_discussions));
    }

    $projectPaymentsUnpaidCount = 0;
    if (isset($unpaidMilestones)) {
        $projectPaymentsUnpaidCount = (int) $unpaidMilestones;
    } else {
        global $database;
        $pidForUnpaid = (int) $project_id;
        if ($pidForUnpaid > 0 && isset($database) && is_object($database)) {
            $unpaidSql = "SELECT COUNT(*) AS c FROM milestones WHERE p_id = {$pidForUnpaid} AND status = 0";
            $unpaidRes = method_exists($database, 'querySoft') ? $database->querySoft($unpaidSql) : $database->query($unpaidSql);
            if ($unpaidRes && $unpaidRow = $database->fetchArray($unpaidRes)) {
                $projectPaymentsUnpaidCount = (int) $unpaidRow['c'];
            }
        }
    }
    $paymentsUnpaidBadge = '';
    if ($projectPaymentsUnpaidCount > 0) {
        $paymentsUnpaidBadge = '<span class="tab-unpaid-count">' . (int) $projectPaymentsUnpaidCount . '</span>';
    }

    $roleFolder = 'client';
    if ((int) $accountStatus === 1) {
        $roleFolder = 'admin';
    } elseif ((int) $accountStatus === 3) {
        $roleFolder = 'staff';
    }
    $pid = (int) $project_id;
    $tabHref = static function ($page, $query) use ($url, $roleFolder) {
        $path = $roleFolder . '/' . $page . '?' . $query;
        return function_exists('tasksession_app_href') ? tasksession_app_href($path) : (rtrim((string) $url, '/') . '/' . $path);
    };
    $discussionHref = function_exists('tasksession_app_href')
        ? tasksession_app_href('discussion?project_id=' . $pid)
        : (rtrim((string) $url, '/') . '/discussion?project_id=' . $pid);
?>
<div class="project-tabs-header">
    <div class="scrollable-tabs-container d-flex col-gap-40 col-gap-40-sep">
        <ul class="nav nav-tabs project-nav-tabs">
            <li class="nav-item">
                <a class="nav-link <?php echo $isTab('overview') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($tabHref('overview', 'projectId=' . $pid), ENT_QUOTES, 'UTF-8'); ?>">
                     <?php echo $lang['Project Overview'] ?? 'Project Overview'; ?>
                </a>
            </li>
           <!-- Add group discussion tab -->
            <?php if ($isDiscussionsEnabled): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $isTab('discussion') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($discussionHref, ENT_QUOTES, 'UTF-8'); ?>">
                   <?php echo $lang['Discussions'] ?? 'Discussions'; ?>
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $isTab('task') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($tabHref('task', 'projectId=' . $pid), ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo $lang['Tasks'] ?? 'Tasks'; ?>
                </a>
            </li>
              <!-- Media Tab -->
              <?php if ($isFileManagementEnabled): ?>
              <li class="nav-item">
                <a class="nav-link <?php echo $isTab('media') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($tabHref('media', 'projectId=' . $pid), ENT_QUOTES, 'UTF-8'); ?>">
                  <?php echo $lang['Files & Media'] ?? 'Files & Media'; ?>
                </a>
            </li>
            <?php endif; ?>
            <?php if ($isInvoicesEnabled): ?>
            <li class="nav-item">
                <?php if((int) $accountStatus === 1): ?>
                <a class="nav-link <?php echo $isTab('payments') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($tabHref('payments', 'projectId=' . $pid), ENT_QUOTES, 'UTF-8'); ?>">
                   <?php echo $lang['Payments & Invoice'] ?? 'Payments & invoice'; ?><?php echo $paymentsUnpaidBadge; ?>
                </a>
                <?php elseif((int) $accountStatus === 2): ?>
                    <?php /* Free edition: clients never see Payments & Invoice tab */ ?>
                <?php elseif((int) $accountStatus === 3 && function_exists('has_permission') && has_permission('milestone_view')): ?>
                <a class="nav-link <?php echo $isTab('payments') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($tabHref('payments', 'projectId=' . $pid), ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo $lang['Payments & Invoice'] ?? 'Payments & invoice'; ?><?php echo $paymentsUnpaidBadge; ?>
                </a>
                <?php endif; ?>
            </li>
            <?php endif; ?>
			
			               <li class="nav-item">
                <a class="nav-link <?php echo $isTab('notes') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($tabHref('notes', 'projectId=' . $pid), ENT_QUOTES, 'UTF-8'); ?>">
                  <?php echo $lang['Notes'] ?? 'Notes'; ?>
                </a>
            </li>
            
		</ul>
    </div>
</div>
<?php endif; ?>
