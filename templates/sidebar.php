<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : sidebar.php
   Purpose : Main sidebar template with navigation menu for all user types
 ================================================================================
*/
if (!function_exists('getSystemImageUrl')) {
    require_once(dirname(__DIR__) . '/includes/system_helpers.php');
}
if (!function_exists('ts_icon')) {
    require_once(dirname(__DIR__) . '/includes/icon.php');
}
function active($currect_page){
  $req = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
  $path = parse_url($req, PHP_URL_PATH);
  if ($path === null || $path === false) {
      $path = '';
  }
  $url_array = explode('/', $path);
  $url = end($url_array);
  $query = parse_url($req, PHP_URL_QUERY);
  $query = ($query !== null && $query !== false) ? (string) $query : '';
  $queryParams = [];
  if ($query !== '') {
      parse_str($query, $queryParams);
  }
  // Pretty /dashboard ≡ index.php; extensionless slug ≡ slug.php
  if ($url === 'dashboard') {
      $url = 'index.php';
  } elseif ($url !== '' && substr($url, -4) !== '.php' && strpos($url, '.') === false) {
      $url .= '.php';
  }
  $currentWithQuery = $query !== '' ? $url . '?' . $query : $url;
  // Handle mail subdirectory
  if (strpos($req, '/mail/') !== false) {
      $leaf = end($url_array);
      if ($leaf === 'dashboard') {
          $leaf = 'index.php';
      } elseif ($leaf !== '' && substr($leaf, -4) !== '.php' && strpos($leaf, '.') === false) {
          $leaf .= '.php';
      }
      $url = 'mail/' . $leaf;
      $currentWithQuery = $query !== '' ? $url . '?' . $query : $url;
  }
  // Ecommerce module: match ecommerce/foo(.php)
  if (strpos($path, '/ecommerce/') !== false && preg_match('#/(ecommerce/[^/]+?)(?:\.php)?$#', $path, $em)) {
      $url = $em[1];
      if (substr($url, -4) !== '.php') {
          $url .= '.php';
      }
      $currentWithQuery = $query !== '' ? $url . '?' . $query : $url;
  } elseif (strpos($path, '/admin/ecommerce/') !== false && preg_match('#/admin/(ecommerce/[^/]+?)(?:\.php)?$#', $path, $emLegacy)) {
      $url = $emLegacy[1];
      if (substr($url, -4) !== '.php') {
          $url .= '.php';
      }
      $currentWithQuery = $query !== '' ? $url . '?' . $query : $url;
  }
  // Marketing module: match marketing/foo(.php)
  if (strpos($path, '/marketing/') !== false && preg_match('#/(marketing/[^/]+?)(?:\.php)?$#', $path, $mm)) {
      $url = $mm[1];
      if (substr($url, -4) !== '.php') {
          $url .= '.php';
      }
      $currentWithQuery = $query !== '' ? $url . '?' . $query : $url;
  }
  if (strpos($currect_page, '?') !== false) {
      if ($currect_page === $currentWithQuery) {
          return 'active';
      }
      return '';
  }
  if ($currect_page === 'clients.php' && $url === 'clients.php') {
      return isset($queryParams['company']) ? '' : 'active';
  }
  if($currect_page == $url || (strpos($currect_page, 'inbox.php') !== false && strpos($url, 'inbox.php') !== false) || (strpos($currect_page, 'new.php') !== false && strpos($url, 'new.php') !== false)){
      return 'active'; //class name in css 
  } 
}
if (!isset($session) || !is_object($session)) {
    global $session;
}
$id = (isset($session) && is_object($session) && isset($session->userId)) ? (int) $session->userId : 0;
$user = (isset($userb) && $userb) ? $userb : ($id > 0 ? User::findById($id) : false);
$accountStatus = ($user && isset($user->accountStatus)) ? $user->accountStatus : 0;
if ((int)$accountStatus === 1 || (int)$accountStatus === 3) {
    require_once(dirname(__DIR__) . '/includes/permissions.php');
    global $connect;
    if (isset($connect)) {
    ensure_user_permissions($connect);
    }
}
if ($accountStatus == 2) {
    require_once(dirname(__DIR__).'/includes/task_permission.php');
    if ($id > 0) {
        $taskPermissions = TaskPermission::getOrCreate($id);
    }
}

if (!isset($lang) || !is_array($lang)) {
    global $lang;
}
if (!isset($lang) || !is_array($lang)) {
    $lang = [];
}
if (!isset($login_page_logo_url)) {
    $login_page_logo_url = '';
}
if (!isset($copy_rights)) {
    $copy_rights = '';
}
if (!isset($url)) {
    global $url;
    $url = isset($url) ? $url : '/';
}

// Module toggles (loaded from settings)
$settingsForModules = isset($dash_settings) && $dash_settings ? $dash_settings : settings::findById(1);
$isLeadBoardEnabled = ($settingsForModules && !empty($settingsForModules->module_lead_board));
$isFileManagementEnabled = ($settingsForModules && !empty($settingsForModules->module_file_management));
$isNotesDocumentsEnabled = ($settingsForModules && !empty($settingsForModules->module_notes_documents));
$isInvoicesEnabled = ($settingsForModules && !empty($settingsForModules->module_invoices));
$isReportsEnabled = ($settingsForModules && (!isset($settingsForModules->module_reports) || !empty($settingsForModules->module_reports)));
$isEmailEnabled = ($settingsForModules && !empty($settingsForModules->module_email));
require_once __DIR__ . '/../includes/marketing_module_gate.php';
$isMarketingModuleEnabled = comon_marketing_module_enabled();
$marketingMenuVisible = $isMarketingModuleEnabled && function_exists('has_permission') && has_permission('marketing_access');
$isAttendanceEnabled = ($settingsForModules && !empty($settingsForModules->module_attendance));
if (!function_exists('comon_ecommerce_module_enabled')) {
    require_once __DIR__ . '/../includes/addon_registry.php';
}
$isEcommerceModuleEnabled = comon_ecommerce_module_enabled();
$ecommerceMenuVisible = $isEcommerceModuleEnabled && function_exists('has_permission') && has_permission('ecommerce_access');
$hideAttendanceForStaffUser = $user && (int)$user->accountStatus === 3 && isset($user->attendance_disabled) && (int)$user->attendance_disabled === 1;
require_once __DIR__ . '/../includes/sidebar_navigation.php';
$isProjectsEnabled = comon_sidebar_projects_enabled($settingsForModules);
$isTasksEnabled = comon_sidebar_tasks_enabled($settingsForModules);
$isProjectsTasksEnabled = $isProjectsEnabled || $isTasksEnabled;
$flatAddonMode = comon_sidebar_flat_addon_mode($settingsForModules, isset($user) ? $user : null);
$visibleAddons = comon_sidebar_visible_addons(isset($user) ? $user : null);

// Free edition: Admin/Staff keep Pro menus visible (upgrade stubs). Clients never see them.
$tsFreeProBadge = '';
$tsFreeShowProMenus = false;
$tsFreeHideClientPro = false;
if (function_exists('tasksession_is_free_edition') && tasksession_is_free_edition()) {
    $acctNav = isset($accountStatus) ? (int) $accountStatus : 0;
    if ($acctNav === 1 || $acctNav === 3) {
        $tsFreeShowProMenus = true;
        $tsFreeProBadge = function_exists('tasksession_pro_badge_html') ? tasksession_pro_badge_html() : '';
        $isInvoicesEnabled = true;
        $isEmailEnabled = true;
        $isLeadBoardEnabled = true;
        $isAttendanceEnabled = true;
        $isMediaVaultMenuVisible = true;
    } elseif ($acctNav === 2) {
        $tsFreeHideClientPro = true;
        $isInvoicesEnabled = false;
        $isEmailEnabled = false;
        $isAttendanceEnabled = false;
        $isMediaVaultMenuVisible = false;
    }
}
if (!isset($isMediaVaultMenuVisible)) {
    $isMediaVaultMenuVisible = !empty($isFileManagementEnabled);
}
?>
<div class="sidebar-admin col-lg-3 col-md-3 col-sm-12">
<script>
(function () {
    try {
        var el = document.currentScript && document.currentScript.parentElement;
        if (el && el.classList.contains('sidebar-admin') && localStorage.getItem('sidebarShrunk') === 'true') {
            el.classList.add('shrink');
            document.documentElement.classList.remove('sidebar-shrunk-initial');
        }
    } catch (e) {}
})();
</script>
	<button class="sidebar-shrink-btn" id="sidebarShrinkBtn" title="Shrink sidebar">
	<?php echo ts_icon('chevron-left', 'w-2'); ?>
	</button>
	<div class="sidebar-header">
	<div class="logo-admin-area">
	<div class="cross-mobile">
	<?php echo ts_icon('close', 'h-6'); ?>
	</div>
	<div class="mobile-welcome">
	<div class="msr-wrapc">
	<div class="msg-img">
	<?php 
	// Get user data for avatar
	$userFirstName = isset($username) ? $username : '';
	$userLastName = '';
	echo getUserAvatarHtml($id, $userFirstName, $userLastName, 50, 50, 'img-fluid rounded-circle', $userFirstName);
	?>
	</div>
	<div class="msg-welcome"><span><?php echo $lang['WELCOME']; ?></span><br><?php 
	if(isset($username)){
	echo $username;
	}	?></div>
	</div>
	</div>
	<?php if($login_page_logo_url){ ?>
	<img src="<?php echo htmlspecialchars($login_page_logo_url, ENT_QUOTES, 'UTF-8'); ?>" class="main-logo" fetchpriority="high" loading="eager" decoding="async" alt=""/>
	<?php } else { ?> 
	<img src="<?php echo $url; ?>assets/images/svg/logo.svg" class="main-logo" fetchpriority="high" loading="eager" decoding="async" alt=""/>
	<?php } ?>
    <?php $favicon_src = isset($sidebar_favicon_url) && $sidebar_favicon_url ? $sidebar_favicon_url : crm_default_asset_image_url('assets/images/favicon.png'); ?>
    <img src="<?php echo htmlspecialchars($favicon_src, ENT_QUOTES, 'UTF-8'); ?>" class="sidebar-favicon" alt="Favicon" loading="eager" decoding="async"/>
	<button type="button" class="sidebar-expand-trigger" id="sidebarExpandBtn" aria-label="<?php echo isset($lang['Open sidebar']) ? $lang['Open sidebar'] : 'Open sidebar'; ?>">
		<span class="sidebar-expand-trigger__icon" aria-hidden="true">
			<?php echo ts_icon('sidebar-expand'); ?>
		</span>
		<span class="sidebar-expand-trigger__label"><?php echo isset($lang['Open sidebar']) ? $lang['Open sidebar'] : 'Open sidebar'; ?></span>
	</button>
    </div><!-- logo-admin-area -->
	<?php
	$showSidebarCreateBtn = false;
	if (isset($accountStatus)) {
		if ((int)$accountStatus === 1) {
			$showSidebarCreateBtn = true;
		} elseif ((int)$accountStatus === 3 && function_exists('has_permission') && has_permission('project_create')) {
			$showSidebarCreateBtn = true;
		}
	}
	$hideSidebarCreateBtn = function_exists('comon_sidebar_is_create_new_hidden')
		&& comon_sidebar_is_create_new_hidden($settingsForModules);
	?>
	<?php if ($showSidebarCreateBtn && !$hideSidebarCreateBtn): ?>
	<div class="bigbutton sidebar-create-btn">
		<a href="#" class="create-new-trigger" data-bs-toggle="modal" data-bs-target="#createNewModal">
			<span><?php echo $lang['Create New']; ?></span>
			<?php echo ts_icon('plus', 'dropdown-toggle-icon h-6'); ?>
		</a>
	</div>
            <?php endif; ?>
	</div><!-- sidebar-header -->
	<div class="sidebar-scroll-body scroll-bar sidebar-scroll-left">
    <div class="admin-nav-area">
	 <?php if($accountStatus == 1){?>
	<ul class="list-unstyled">
	<?php include __DIR__ . '/partials/sidebar-admin-nav.php'; ?>
        </ul>
		<?php } else if($accountStatus == 3){?>
        	<ul class="list-unstyled">
	<?php include __DIR__ . '/partials/sidebar-staff-nav.php'; ?>
        </ul>
		<?php } else { ?>
         <ul class="list-unstyled">
            <li class="<?php echo active('index.php'); ?>"> 
                <a href="<?php echo $url; ?>client/dashboard">
                    <span><?php echo ts_icon('dashboard', 'h-6'); ?></span>
                    <span class="menu-text"><?php echo $lang['Dashboard']; ?></span>
                    <?php echo ts_icon('chevron-right', 'h-6'); ?>
                </a>
            </li>
            <?php if ($isProjectsEnabled): ?>
            <li class="<?php echo active('projects.php'); ?>"> 
                <a href="<?php echo menu_module_url('client', 'projects'); ?>">
                    <span><?php echo ts_icon('projects', 'h-6'); ?></span>
                    <span class="menu-text"><?php echo $lang['Projects']; ?></span>
                    <?php echo ts_icon('chevron-right', 'h-6'); ?>
                </a>
            </li>
            <?php endif; ?>
            <?php if ($isTasksEnabled): ?>
            <li class="<?php echo active('tasks.php'); ?>"> 
                <a href="<?php echo $url; ?>client/kanban">
                    <span><?php echo ts_icon('tasks-sidebar', 'h-6'); ?></span>
                    <span class="menu-text"><?php echo $lang['Tasks']; ?></span>
                    <?php echo ts_icon('chevron-right', 'h-6'); ?>
                </a>
            </li>
            <?php endif; ?>
            <?php if (!$tsFreeHideClientPro && $isInvoicesEnabled && isset($taskPermissions) && !empty($taskPermissions->can_view_milestones)): ?>
            <?php include __DIR__ . '/partials/client-payments-sidebar-menu.php'; ?>
            <?php endif; ?>
            <?php if (!$tsFreeHideClientPro): ?>
            <li class="<?php echo active('chatting.php'); ?>">
                <a href="<?php echo $url; ?>chatting">
                    <span><?php echo ts_icon('chat', 'h-6'); ?></span>
                    <span class="menu-text"><?php echo $lang['Chatting']; ?></span>
                    <?php echo ts_icon('chevron-right', 'h-6'); ?>
                </a>
            </li>
            <?php endif; ?>
            <li class="<?php echo active('documents.php'); ?>"> 
                <a href="<?php echo $url; ?>client/documents">
                    <span><?php echo ts_icon('documents', 'h-6'); ?></span>
                    <span class="menu-text"><?php echo $lang['Documents'] ?? 'Documents'; ?></span>
                    <?php echo ts_icon('chevron-right', 'h-6'); ?>
                </a>
            </li>
            <?php if (!$tsFreeHideClientPro && !empty($isMediaVaultMenuVisible)): ?>
            <li class="<?php echo active('media-vault.php'); ?>">
                <a href="<?php echo $url; ?>client/media-vault">
                    <span><?php echo ts_icon('media-vault', 'h-6'); ?></span>
                    <span class="menu-text"><?php echo $lang['Media Vault'] ?? 'Media Vault'; ?></span>
                    <?php echo ts_icon('chevron-right', 'h-6'); ?>
                </a>
            </li>
            <?php endif; ?>
            <?php
            // AI Workspace (same group as admin/staff) — also on chatting.php client sidebar
            if (!function_exists('comon_ai_module_enabled')) {
                $__aiGate = __DIR__ . '/../includes/ai_module_gate.php';
                if (is_file($__aiGate)) {
                    require_once $__aiGate;
                }
            }
            if (function_exists('comon_ai_module_enabled') && comon_ai_module_enabled()) {
                if (!function_exists('has_permission') && is_file(__DIR__ . '/../includes/permissions.php')) {
                    require_once __DIR__ . '/../includes/permissions.php';
                }
                if (function_exists('ensure_user_permissions') && isset($connect) && $connect instanceof mysqli) {
                    ensure_user_permissions($connect);
                }
                if (function_exists('has_permission') && has_permission('ai_access')) {
                    $aiWorkspaceMenuId = 'ai-workspace-menu-client';
                    include __DIR__ . '/partials/ai-sidebar-menu.php';
                }
            }
            ?>
            <li class="<?php echo active('profile.php'); ?>"> 
                <a href="<?php echo $url; ?>client/profile?user_id=<?php echo (int) $id; ?>">
                    <span><?php echo ts_icon('profile', 'h-6'); ?></span>
                    <span class="menu-text"><?php echo $lang['Profile']; ?></span>
                    <?php echo ts_icon('chevron-right', 'h-6'); ?>
                </a>
            </li>
            <li class="<?php echo active('logout.php'); ?>"> 
                <a href="<?php echo $url; ?>logout.php">
                    <span><?php echo ts_icon('logout', 'h-6'); ?></span>
                    <span class="menu-text"><?php echo $lang['Logout']; ?></span>
                    <?php echo ts_icon('chevron-right', 'h-6'); ?>
                </a>
            </li>
        </ul>
		<?php }?>

		<?php
		// Render the Create New modal only for Admins or Staff who can create projects
		$isAdminAccount = isset($accountStatus) && (int)$accountStatus === 1;
		$isStaffAccount = isset($accountStatus) && (int)$accountStatus === 3;
		$canUsePermissions = function_exists('has_permission');
		$showCreateNewModal = $isAdminAccount || ($isStaffAccount && $canUsePermissions && has_permission('project_create'));
		$pathPrefix = $isAdminAccount ? 'admin/' : 'staff/';

		$canCreateProject = $isAdminAccount || ($isStaffAccount && $canUsePermissions && has_permission('project_create'));
		$canCreateTask = $isAdminAccount || ($isStaffAccount && $canUsePermissions && has_permission('task_create'));
		$canCreateInvoice = false;
		if (!(function_exists('tasksession_is_free_edition') && tasksession_is_free_edition())) {
			$canCreateInvoice = $isAdminAccount || ($isStaffAccount && $canUsePermissions && has_permission('milestone_create'));
		}
		$canCreateClient = $isAdminAccount || ($isStaffAccount && $canUsePermissions && has_permission('client_create'));
		$canCreateStaff = $isAdminAccount || ($isStaffAccount && $canUsePermissions && has_permission('staff_create'));
		$canOpenUserStep = $isAdminAccount || $canCreateClient || $canCreateStaff;
		?>

		<?php if ($showCreateNewModal): ?>
		<div class="modal fade create-new-modal" id="createNewModal" tabindex="-1" aria-labelledby="createNewModalLabel" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered modal-lg">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="createNewModalLabel"><?php echo $lang['Create New']; ?></h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
				<?php echo ts_icon('close'); ?>
				</button>
					</div>

					<div class="modal-body">
						<!-- Step 1 -->
						<div class="create-new-step" data-step="main">
							<div class="row g-1">
								<?php if ($canCreateProject && $isProjectsEnabled): ?>
								<div class="col-6 col-md-3">
									<a class="create-new-card" href="<?php echo $url . $pathPrefix; ?>add-new-project">
										<div class="create-new-icon" aria-hidden="true">
											<?php echo ts_icon('plus'); ?>
										</div>
										<div class="create-new-title"><?php echo $lang['Create project']; ?></div>
										<div class="create-new-action">
											<span class="btn primary-btn"><?php echo $lang['Create']; ?></span>
										</div>
									</a>
								</div>
								<?php endif; ?>

								<?php if ($isTasksEnabled): ?>
								<div class="col-6 col-md-3">
									<?php if ($canCreateTask): ?>
									<a class="create-new-card" href="<?php echo $url . $pathPrefix; ?>add_task">
										<div class="create-new-icon" aria-hidden="true">
											<?php echo ts_icon('document-text'); ?>
										</div>
										<div class="create-new-title"><?php echo $lang['Create Task']; ?></div>
										<div class="create-new-action">
											<span class="btn primary-btn"><?php echo $lang['Create']; ?></span>
										</div>
									</a>
									<?php else: ?>
									<div class="create-new-card create-new-card-disabled" aria-disabled="true" tabindex="-1">
										<div class="create-new-icon" aria-hidden="true">
											<?php echo ts_icon('document-text'); ?>
										</div>
										<div class="create-new-title"><?php echo $lang['Create Task']; ?></div>
										<div class="create-new-action">
											<span class="btn primary-btn disabled"><?php echo $lang['Create']; ?></span>
										</div>
									</div>
									<?php endif; ?>
								</div>
								<?php endif; ?>

								<?php if ($isInvoicesEnabled): ?>
								<div class="col-6 col-md-3">
									<?php if ($canCreateInvoice): ?>
									<a class="create-new-card" href="<?php echo $url . $pathPrefix; ?>add-invoice">
										<div class="create-new-icon" aria-hidden="true">
											<?php echo ts_icon('invoice'); ?>
										</div>
										<div class="create-new-title"><?php echo $lang['Create Invoice']; ?></div>
										<div class="create-new-action">
											<span class="btn primary-btn"><?php echo $lang['Create']; ?></span>
										</div>
									</a>
									<?php else: ?>
									<div class="create-new-card create-new-card-disabled" aria-disabled="true" tabindex="-1">
										<div class="create-new-icon" aria-hidden="true">
											<?php echo ts_icon('invoice'); ?>
										</div>
										<div class="create-new-title"><?php echo $lang['Create Invoice']; ?></div>
										<div class="create-new-action">
											<span class="btn primary-btn disabled"><?php echo $lang['Create']; ?></span>
										</div>
									</div>
									<?php endif; ?>
								</div>
								<?php endif; ?>

								<div class="col-6 col-md-3">
									<button type="button" class="create-new-card create-new-card-button js-create-new-open-user">
										<div class="create-new-icon" aria-hidden="true">
											<?php echo ts_icon('staff'); ?>
										</div>
										<div class="create-new-title"><?php echo $lang['Create New User']; ?></div>
										<div class="create-new-action">
											<span class="btn primary-btn"><?php echo $lang['Create']; ?></span>
										</div>
									</button>
								</div>
							</div>
						</div>

						<!-- Step 2 -->
						<div class="create-new-step d-none" data-step="user">
							<div class="row g-1">
								<div class="col-6 col-md-3">
									<?php if ($canCreateClient): ?>
									<a class="create-new-card" href="<?php echo $url . $pathPrefix; ?>add-client">
										<div class="create-new-icon" aria-hidden="true">
											<?php echo ts_icon('user'); ?>
										</div>
										<div class="create-new-title"><?php echo $lang['Create New Client']; ?></div>
										<div class="create-new-action">
											<span class="btn primary-btn"><?php echo $lang['Create']; ?></span>
										</div>
									</a>
									<?php else: ?>
									<div class="create-new-card create-new-card-disabled" aria-disabled="true" tabindex="-1">
										<div class="create-new-icon" aria-hidden="true">
											<?php echo ts_icon('user'); ?>
										</div>
										<div class="create-new-title"><?php echo $lang['Create New Client']; ?></div>
										<div class="create-new-action">
											<span class="btn primary-btn disabled"><?php echo $lang['Create']; ?></span>
										</div>
									</div>
									<?php endif; ?>
								</div>

								<div class="col-6 col-md-3">
									<?php if ($canCreateStaff): ?>
									<a class="create-new-card" href="<?php echo $url . $pathPrefix; ?>add-staff">
										<div class="create-new-icon" aria-hidden="true">
											<?php echo ts_icon('staff'); ?>
										</div>
										<div class="create-new-title"><?php echo $lang['Create Staff']; ?></div>
										<div class="create-new-action">
											<span class="btn primary-btn"><?php echo $lang['Create']; ?></span>
										</div>
									</a>
									<?php else: ?>
									<div class="create-new-card create-new-card-disabled" aria-disabled="true" tabindex="-1">
										<div class="create-new-icon" aria-hidden="true">
											<?php echo ts_icon('staff'); ?>
										</div>
										<div class="create-new-title"><?php echo $lang['Create Staff']; ?></div>
										<div class="create-new-action">
											<span class="btn primary-btn disabled"><?php echo $lang['Create']; ?></span>
										</div>
									</div>
									<?php endif; ?>
								</div>

								<?php if ($isAdminAccount): ?>
								<div class="col-6 col-md-3">
									<a class="create-new-card" href="<?php echo $url; ?>admin/add-admin">
										<div class="create-new-icon" aria-hidden="true">
											<?php echo ts_icon('shield'); ?>
										</div>
										<div class="create-new-title"><?php echo $lang['Create Admin']; ?></div>
										<div class="create-new-action">
											<span class="btn primary-btn"><?php echo $lang['Create']; ?></span>
										</div>
									</a>
								</div>
								<?php else: ?>
								<div class="col-6 col-md-3">
									<div class="create-new-card create-new-card-disabled" aria-disabled="true" tabindex="-1">
										<div class="create-new-icon" aria-hidden="true">
											<?php echo ts_icon('shield'); ?>
										</div>
										<div class="create-new-title"><?php echo $lang['Create Admin']; ?></div>
										<div class="create-new-action">
											<span class="btn primary-btn disabled"><?php echo $lang['Create']; ?></span>
										</div>
									</div>
								</div>
								<?php endif; ?>

								<div class="col-6 col-md-3">
									<button type="button" class="create-new-card create-new-card-button js-create-new-back">
										<div class="create-new-icon" aria-hidden="true">
											<?php echo ts_icon('arrow-left'); ?>
										</div>
										<div class="create-new-title"><?php echo $lang['Back to main']; ?></div>
										<div class="create-new-action">
											<span class="btn secondary-btn-a"><?php echo $lang['Back']; ?></span>
										</div>
									</button>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php endif; ?>

           </div><!-- admin-nav-area -->
	</div><!-- sidebar-scroll-body -->
	<p class="copyright sidebar-footer"><?php echo $copy_rights; ?></p>
       </div><!-- sidebar-admin -->