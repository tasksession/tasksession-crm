<?php
/**
 * Staff sidebar navigation menu blocks.
 * Expects variables set by sidebar.php: $url, $lang, $id, $user, $settingsForModules,
 * $flatAddonMode, $visibleAddons, $isProjectsEnabled, $isTasksEnabled, $isInvoicesEnabled,
 * $isLeadBoardEnabled, $isEmailEnabled, $isFileManagementEnabled, $isNotesDocumentsEnabled,
 * $isAttendanceEnabled, $ecommerceMenuVisible, $marketingMenuVisible, $hideAttendanceForStaffUser,
 * active(), menu_module_url(), has_permission()
 */
$menuBlocks = array();

$menuBlocks['dashboard'] = '';
if (!$flatAddonMode) {
    ob_start();
    ?>
        	<li class="<?php echo active('index.php'); ?>"> <a href="<?php echo $url; ?>staff/dashboard"><span>
			<?php echo ts_icon('dashboard', 'h-6'); ?>
			</span><span class="menu-text"><?php echo $lang['Dashboard']; ?></span>  <?php echo ts_icon('chevron-right', 'h-6'); ?></a></li>
    <?php
    $menuBlocks['dashboard'] = ob_get_clean();
}

$menuBlocks['clients'] = '';
if (has_permission('client_view')) {
    ob_start();
    ?>
            <li class="<?php echo active('clients.php'); echo active('clients.php?company'); echo active('add-client.php'); ?>">
                <a href="#" data-bs-toggle="collapse" data-bs-target="#client-menu">
                    <span>
                        <?php echo ts_icon('clients', 'h-6'); ?>
                    </span>
                    <span class="menu-text"><?php echo $lang['Clients']; ?></span>
                    <?php echo ts_icon('plus', 'dropdown-toggle-icon h-6'); ?>
                </a>
                <ul id="client-menu" class="collapse">
                <li class="<?php echo active('clients.php'); ?>"><a href="<?php echo $url; ?>staff/clients"><span id="shrink-none" class="menu-text"><?php echo $lang['View all Clients']; ?></span><span class="menu-text dt-none"><?php echo $lang['View all']; ?></span></a></li>
                <li class="<?php echo active('clients.php?company'); ?>"><a href="<?php echo $url; ?>staff/clients?company"><span id="shrink-none" class="menu-text"><?php echo $lang['View all companies']; ?><?php echo isset($tsFreeProBadge) ? $tsFreeProBadge : ''; ?></span><span class="menu-text dt-none"><?php echo $lang['Company']; ?></span></a></li>
                <?php if (has_permission('client_create')): ?>
                <li class="<?php echo active('add-client.php'); ?>"><a href="<?php echo $url; ?>staff/add-client"><span id="shrink-none" class="menu-text"><?php echo $lang['Add New Client']; ?></span><span class="menu-text dt-none"><?php echo $lang['Add new']; ?></span></a></li>
                <?php endif; ?>
                </ul>
                </li>
    <?php
    $menuBlocks['clients'] = ob_get_clean();
}

$menuBlocks['staff'] = '';
if (has_permission('staff_view')) {
    ob_start();
    ?>
            <li class="<?php echo active('members.php'); ?>">
                <a href="#" data-bs-toggle="collapse" data-bs-target="#staff-menu">
                    <span>
                        <?php echo ts_icon('staff', 'h-6'); ?>
                    </span>
                    <span class="menu-text"><?php echo $lang['Staff']; ?></span>
                    <?php echo ts_icon('plus', 'dropdown-toggle-icon h-6'); ?>
                </a>
                <ul id="staff-menu" class="collapse">
                <li class="<?php echo active('members.php'); ?>">
                    <a href="<?php echo $url; ?>staff/members"><span id="shrink-none" class="menu-text"><?php echo $lang['View all Staff']; ?></span><span class="menu-text dt-none"><?php echo $lang['View all']; ?></span></a>
                </li>
                <?php if (has_permission('staff_create')): ?>
                <li class="<?php echo active('add-staff.php'); ?>">
                    <a href="<?php echo $url; ?>staff/add-staff"><span id="shrink-none" class="menu-text"><?php echo $lang['Add New Staff']; ?></span><span class="menu-text dt-none"><?php echo $lang['Add new']; ?></span></a>
                </li>
                <?php endif; ?>
                </ul>
            </li>
    <?php
    $menuBlocks['staff'] = ob_get_clean();
}

$menuBlocks['projects'] = '';
if ($isProjectsEnabled) {
    ob_start();
    ?>
            <li class="<?php echo active('projects.php'); ?>">
                <a href="#" data-bs-toggle="collapse" data-bs-target="#projects-menu-staff">
                    <span>
                        <?php echo ts_icon('projects', 'h-6'); ?>
                    </span>
                    <span class="menu-text"><?php echo $lang['Projects']; ?></span>
                    <?php echo ts_icon('plus', 'dropdown-toggle-icon h-6'); ?>
                </a>
                <ul id="projects-menu-staff" class="collapse">
                    <li class="<?php echo active('projects.php'); ?>">
                        <a href="<?php echo menu_module_url('staff', 'projects'); ?>">
                            <span id="shrink-none" class="menu-text"><?php echo $lang['My Projects'] ?? 'My Projects'; ?></span>
                            <span class="menu-text dt-none"><?php echo $lang['My Projects'] ?? 'My'; ?></span>
                        </a>
                    </li>
                    <li class="<?php echo active('projects.php?all_projects=1'); ?>">
                        <a href="<?php echo menu_module_url('staff', 'projects', array('all_projects' => 1)); ?>">
                            <span id="shrink-none" class="menu-text"><?php echo $lang['All Projects'] ?? 'All Projects'; ?></span>
                            <span class="menu-text dt-none"><?php echo $lang['All'] ?? 'All'; ?></span>
                        </a>
                    </li>
                </ul>
            </li>
    <?php
    $menuBlocks['projects'] = ob_get_clean();
}

$menuBlocks['tasks'] = '';
if ($isTasksEnabled) {
    ob_start();
    ?>
            <li class="<?php echo active('all-tasks.php'); ?>">
				<a href="#" data-bs-toggle="collapse" data-bs-target="#task-menu-staff">
                    <span>
                        <?php echo ts_icon('tasks-sidebar', 'h-6'); ?>
                    </span>
                    <span class="menu-text"><?php echo $lang['Tasks']; ?></span>
                    <?php echo ts_icon('plus', 'dropdown-toggle-icon h-6'); ?>
                </a>
				<ul id="task-menu-staff" class="collapse">
                <li class="<?php echo active('all-tasks.php'); ?>"><a href="<?php echo $url; ?>staff/all-tasks"><span id="shrink-none" class="menu-text"><?php echo $lang['View all Task']; ?></span><span class="menu-text dt-none"><?php echo $lang['View all']; ?></span></a></li>
                <li class="<?php echo active('kanban.php'); ?>"><a href="<?php echo $url; ?>staff/kanban"><span id="shrink-none" class="menu-text"><?php echo $lang['Kanban Board']; ?></span><span class="menu-text dt-none"><?php echo $lang['Kanban']; ?></span></a></li>
                <li class="<?php echo active('calendar.php'); ?>"><a href="<?php echo $url; ?>staff/calendar"><span id="shrink-none" class="menu-text"><?php echo $lang['Calendar View'] ?? 'Calendar View'; ?></span><span class="menu-text dt-none"><?php echo $lang['Calendar'] ?? 'Calendar'; ?></span></a></li>
					<?php if (has_permission('task_create')): ?>
                <li class="<?php echo active('add_task.php'); ?>"><a href="<?php echo $url; ?>staff/add_task"><span id="shrink-none" class="menu-text"><?php echo $lang['Add New Task']; ?></span><span class="menu-text dt-none"><?php echo $lang['Add new']; ?></span></a></li>
                <li class="<?php echo active('calendar.php'); ?>"><a href="<?php echo $url; ?>staff/calendar?open_event=1"><span id="shrink-none" class="menu-text"><?php echo $lang['Add New Event'] ?? 'Add New Event'; ?></span><span class="menu-text dt-none"><?php echo $lang['Add new']; ?></span></a></li>
					<?php endif; ?>
                </ul>
            </li>
    <?php
    $menuBlocks['tasks'] = ob_get_clean();
}

$menuBlocks['financials'] = '';
if ($isInvoicesEnabled && has_permission('milestone_view')) {
    ob_start();
    ?>
            <li class="<?php echo active('paid-invoices.php'); echo active('unpaid-invoices.php'); echo active('subscriptions.php'); ?>">
                <a href="#" data-bs-toggle="collapse" data-bs-target="#financials-menu">
                    <span>
                        <?php echo ts_icon('payments', 'h-6'); ?>
                    </span>
                    <span class="menu-text"><?php echo $lang['Financials']; ?><?php echo isset($tsFreeProBadge) ? $tsFreeProBadge : ''; ?></span>
                   <?php echo ts_icon('plus', 'dropdown-toggle-icon h-6'); ?>
                </a>
            <ul id="financials-menu" class="collapse">
            <li class="<?php echo active('invoices.php'); ?>"><a href="<?php echo $url; ?>staff/invoices"><span class="menu-text"><?php echo isset($lang['All Invoices']) ? $lang['All Invoices'] : 'All invoices'; ?></span></a></li>
            <li class="<?php echo active('invoices.php'); ?>"><a href="<?php echo $url; ?>staff/invoices?status=1"><span class="menu-text"><?php echo $lang['Paid Invoices']; ?></span></a></li>
            <li class="<?php echo active('invoices.php?status=0'); ?>"><a href="<?php echo $url; ?>staff/invoices?status=0"><span class="menu-text"><?php echo $lang['Unpaid Invoices']; ?></span> </a></li>
            <li class="<?php echo active('subscriptions.php'); ?>"><a href="<?php echo $url; ?>staff/subscriptions"><span class="menu-text"><?php echo isset($lang['Subscriptions']) ? $lang['Subscriptions'] : 'Subscriptions'; ?></span></a></li>
            <?php if (has_permission('milestone_create')): ?>
            <li class="<?php echo active('add-invoice.php'); ?>"><a href="<?php echo $url; ?>staff/add-invoice"><span class="menu-text"><?php echo $lang['Add New Invoice']; ?></span></a></li>
            <?php endif; ?>
            </ul>
            </li>
    <?php
    $menuBlocks['financials'] = ob_get_clean();
}

$menuBlocks['leads'] = '';
if ($isLeadBoardEnabled) {
    ob_start();
    ?>
            <li class="<?php echo active('leads.php'); ?>">
                <a href="#" data-bs-toggle="collapse" data-bs-target="#leads-menu-staff">
                    <span>
                        <?php echo ts_icon('leads', 'h-6'); ?>
                    </span>
                    <span class="menu-text"><?php echo $lang['Leads']; ?><?php echo isset($tsFreeProBadge) ? $tsFreeProBadge : ''; ?></span>
                    <?php echo ts_icon('plus', 'dropdown-toggle-icon h-6'); ?>
                </a>
                <ul id="leads-menu-staff" class="collapse">
                    <li class="<?php echo active('leads.php'); ?>"><a href="<?php echo $url; ?>staff/leads"><span id="shrink-none" class="menu-text"><?php echo isset($lang['All Leads']) ? $lang['All Leads'] : 'All Leads'; ?></span><span class="menu-text dt-none"><?php echo $lang['View all']; ?></span></a></li>
                </ul>
            </li>
    <?php
    $menuBlocks['leads'] = ob_get_clean();
}

$menuBlocks['email'] = '';
if ($isEmailEnabled && has_permission('email_inbox_view')) {
    ob_start();
    ?>
            <li class="<?php echo active('inbox.php'); echo active('new.php'); ?>">
                <a href="#" data-bs-toggle="collapse" data-bs-target="#email-menu-staff">
                    <span>
                        <?php echo ts_icon('emails', 'h-6'); ?>
                    </span>
                    <span class="menu-text"><?php echo $lang['Emails']; ?><?php echo isset($tsFreeProBadge) ? $tsFreeProBadge : ''; ?></span>
                    <?php echo ts_icon('plus', 'dropdown-toggle-icon h-6'); ?>
                </a>
                <ul id="email-menu-staff" class="collapse">
                    <li class="<?php echo active('inbox.php'); ?>">
                        <a href="<?php echo $url; ?>mail/inbox">
                            <span id="shrink-none" class="menu-text"><?php echo $lang['Inbox']; ?></span>
                            <span class="menu-text dt-none"><?php echo $lang['Inbox']; ?></span>
                        </a>
                    </li>
                    <li class="<?php echo active('new.php'); ?>">
                        <a href="<?php echo $url; ?>mail/new">
                            <span id="shrink-none" class="menu-text"><?php echo $lang['New Email']; ?></span>
                            <span class="menu-text dt-none"><?php echo $lang['New Email']; ?></span>
                        </a>
                    </li>
                </ul>
            </li>
    <?php
    $menuBlocks['email'] = ob_get_clean();
}

$menuBlocks['marketing'] = '';
if ($marketingMenuVisible && (!$flatAddonMode || in_array('marketing', $visibleAddons, true)) && is_file(__DIR__ . '/marketing-sidebar-menu.php')) {
    ob_start();
    $addonMenuFlat = $flatAddonMode && in_array('marketing', $visibleAddons, true);
    $marketingMenuId = 'marketing-menu-staff';
    include __DIR__ . '/marketing-sidebar-menu.php';
    $menuBlocks['marketing'] = ob_get_clean();
}

$menuBlocks['ecommerce'] = '';
if ($ecommerceMenuVisible && (!$flatAddonMode || in_array('ecommerce', $visibleAddons, true)) && is_file(__DIR__ . '/ecommerce-sidebar-menu.php')) {
    ob_start();
    $addonMenuFlat = $flatAddonMode && in_array('ecommerce', $visibleAddons, true);
    $ecommerceMenuId = 'ecommerce-menu-staff';
    include __DIR__ . '/ecommerce-sidebar-menu.php';
    $menuBlocks['ecommerce'] = ob_get_clean();
}

ob_start();
?>
            <li class="<?php echo active('chatting.php'); ?>">
                <a href="<?php echo $url; ?>chatting">
                    <span>
                        <?php echo ts_icon('chat', 'h-6'); ?>
                    </span>
                    <span class="menu-text"><?php echo $lang['Chatting']; ?><?php echo isset($tsFreeProBadge) ? $tsFreeProBadge : ''; ?></span>
                    <?php echo ts_icon('chevron-right', 'h-6'); ?>
                </a>
            </li>
<?php
$menuBlocks['chatting'] = ob_get_clean();

$menuBlocks['ai_assistant'] = '';
$__aiGate = __DIR__ . '/../../includes/ai_module_gate.php';
if (!function_exists('comon_ai_module_enabled') && is_file($__aiGate)) {
    require_once $__aiGate;
}
if (
    function_exists('comon_ai_module_enabled')
    && comon_ai_module_enabled()
    && function_exists('has_permission')
    && has_permission('ai_access')
) {
    ob_start();
    $aiWorkspaceMenuId = 'ai-workspace-menu-staff';
    include __DIR__ . '/ai-sidebar-menu.php';
    $menuBlocks['ai_assistant'] = ob_get_clean();
}

$menuBlocks['media_vault'] = '';
if (!empty($isMediaVaultMenuVisible) || $isFileManagementEnabled) {
    if (empty($isMediaVaultMenuVisible)) { $isMediaVaultMenuVisible = true; }
}
if (!empty($isMediaVaultMenuVisible)) {
    ob_start();
    ?>
            <li class="<?php echo active('media-vault.php'); ?>">
                <a href="<?php echo $url; ?>staff/media-vault">
                    <span>
                       <?php echo ts_icon('media-vault', 'h-6'); ?>

                    </span>
                    <span class="menu-text"><?php echo $lang['Media Vault'] ?? 'Media Vault'; ?><?php echo isset($tsFreeProBadge) ? $tsFreeProBadge : ''; ?></span>
                   <?php echo ts_icon('chevron-right', 'h-6'); ?>
                </a>
            </li>
    <?php
    $menuBlocks['media_vault'] = ob_get_clean();
}

$menuBlocks['documents'] = '';
if ($isNotesDocumentsEnabled) {
    ob_start();
    ?>
            <li class="<?php echo active('documents.php'); ?>">
                <a href="<?php echo $url; ?>staff/documents">
                    <span>
                        <?php echo ts_icon('documents', 'h-6'); ?>
                    </span>
                    <span class="menu-text"><?php echo $lang['Documents'] ?? 'Documents'; ?></span>
                    <?php echo ts_icon('chevron-right', 'h-6'); ?>
                </a>
            </li>
    <?php
    $menuBlocks['documents'] = ob_get_clean();
}

$menuBlocks['attendance'] = '';
if ($isAttendanceEnabled && has_permission('attendance_view') && !$hideAttendanceForStaffUser) {
    ob_start();
    ?>
            <li class="<?php echo active('attendance.php'); echo active('attendance-reports.php'); echo active('attendance-settings.php'); ?>">
                <a href="#" data-bs-toggle="collapse" data-bs-target="#attendance-menu-staff">
                    <span>
                        <?php echo ts_icon('attendance', 'h-6'); ?>
                    </span>
                    <span class="menu-text"><?php echo $lang['Attendance'] ?? 'Attendance'; ?><?php echo isset($tsFreeProBadge) ? $tsFreeProBadge : ''; ?></span>
                    <?php echo ts_icon('plus', 'dropdown-toggle-icon h-6'); ?>
                </a>
                <ul id="attendance-menu-staff" class="collapse">
                    <li class="<?php echo active('attendance.php'); ?>"><a href="<?php echo $url; ?>staff/attendance"><span class="menu-text"><?php echo $lang['Dashboard'] ?? 'Dashboard'; ?></span></a></li>
                    <?php if (has_permission('attendance_reports_view')): ?>
                    <li class="<?php echo active('attendance-reports.php'); ?>"><a href="<?php echo $url; ?>staff/attendance-reports"><span class="menu-text"><?php echo $lang['Reports'] ?? 'Reports'; ?></span></a></li>
                    <?php endif; ?>
                    <?php if (has_permission('attendance_settings_manage')): ?>
                    <li class="<?php echo active('attendance-settings.php'); ?>"><a href="<?php echo $url; ?>staff/attendance-settings"><span class="menu-text"><?php echo $lang['Settings'] ?? 'Settings'; ?></span></a></li>
                    <?php endif; ?>
                </ul>
            </li>
    <?php
    $menuBlocks['attendance'] = ob_get_clean();
}
?>
<?php comon_sidebar_apply_menu_layout($menuBlocks, $settingsForModules, 'staff', $user ?? null); ?>
            <li class="<?php echo active('profile.php'); ?> mobile-show d-md-none">
                <a href="<?php echo $url; ?>staff/profile?user_id=<?php echo (int) $id; ?>">
                    <span>
                        <?php echo ts_icon('profile', 'h-6'); ?>
                    </span>
                    <span class="menu-text"><?php echo $lang['Profile']; ?></span>
                    <?php echo ts_icon('chevron-right', 'h-6'); ?>
                </a>
            </li>
            <li class="<?php echo active('logout.php'); ?> mobile-show d-md-none">
                <a href="<?php echo $url; ?>logout.php">
                    <span>
                        <?php echo ts_icon('logout', 'h-6'); ?>
                    </span>
                    <span class="menu-text"><?php echo $lang['Logout']; ?></span>
                    <?php echo ts_icon('chevron-right', 'h-6'); ?>
                </a>
            </li>
