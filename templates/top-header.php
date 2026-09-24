<?php 
/*
 ================================================================================
   Task Session – Project Management System
   File    : top-header.php
   Purpose : Top header template with navigation, user menu, and notifications
 ================================================================================
*/
// Load messages loader if not already loaded
if (!isset($msg)) {
    $loaderPath = dirname(__DIR__) . DS . 'includes' . DS . 'loader.php';
    if (file_exists($loaderPath)) {
        include($loaderPath);
    } else {
        // Fallback: try to initialize Messages class if available
        if (class_exists('Messages')) {
            $msg = new Messages();
        }
    }
}
// Ensure message unread queries always have a receiver id when auth session exists
if (isset($msg) && is_object($msg) && isset($session->userId) && (int) $session->userId > 0) {
    $msgLoggedId = isset($msg->logged_user_id) ? (int) $msg->logged_user_id : 0;
    if ($msgLoggedId <= 0) {
        $msg->logged_user_id = (int) $session->userId;
        if (empty($_SESSION['logged_user_id'])) {
            $_SESSION['logged_user_id'] = (int) $session->userId;
        }
    }
}

// Load notifications class if it exists (for bell icon counter)
$notificationCount = 0;
if (file_exists(dirname(__FILE__) . '/../includes/notifications.php')) {
    @require_once(dirname(__FILE__) . '/../includes/notifications.php');
    if (class_exists('Notifications') && method_exists('Notifications', 'getUnreadCountGrouped')) {
        try {
            $notificationCount = Notifications::getUnreadCountGrouped($session->userId);
        } catch(Exception $e) {
            $notificationCount = 0;
        }
    } elseif (class_exists('Notifications') && method_exists('Notifications', 'getUnreadCount')) {
        try {
            $notificationCount = Notifications::getUnreadCount($session->userId);
        } catch(Exception $e) {
            $notificationCount = 0;
        }
    }
}
$profilePictureObj=profilePicture::findByfkUserId($session->userId);
if($profilePictureObj){
	foreach($profilePictureObj as $displayPicture)
	{
	 $profilePic=$displayPicture->filename;
	}
}
$id=$session->userId;
$userb = User::findById((int)$session->userId);
if(isset($_POST['user_language']))
	{ 
		$user = user::findById($id); 
		
		$flag=0;
		if($flag==0)
		{
			$user->id = $id;
			$user->user_language = $_POST['user_language'];
			$saveUser=$user->save();
				if($saveUser)
				{
					$uri = $_SERVER['REQUEST_URI'];
					header("Location: ".$uri);
				} else{
					// echo 'Error While Update Language! Please Try again Later';
				}
		}
	} 
?>
<?php
$tasksessionHeaderTimerAcct = isset($_SESSION['accountStatus']) ? (int)$_SESSION['accountStatus'] : 0;
$tasksessionShowHeaderTimer = function_exists('tasksession_time_tracking_enabled') && tasksession_time_tracking_enabled() && ($tasksessionHeaderTimerAcct === 1 || $tasksessionHeaderTimerAcct === 3);
$tasksessionHeaderHasActiveTimers = false;
if ($tasksessionShowHeaderTimer && isset($session) && is_object($session) && method_exists($session, 'isLoggedIn') && $session->isLoggedIn()) {
    if (!function_exists('tasksession_user_has_active_timer_sessions')) {
        require_once dirname(__DIR__) . '/includes/time_tracking_helper.php';
    }
    global $connect;
    $tasksessionHeaderHasActiveTimers = tasksession_user_has_active_timer_sessions((int) $session->userId, isset($connect) ? $connect : null);
}
$tasksessionHeaderTimerSlotClass = 'tasksession-header-timer-slot d-inline-flex align-items-center';
if (!$tasksessionHeaderHasActiveTimers) {
    $tasksessionHeaderTimerSlotClass .= ' tasksession-header-timer-slot--ready tasksession-header-timer-slot--empty';
} else {
    $tasksessionHeaderTimerSlotClass .= ' tasksession-header-timer-slot--show-skeleton';
}
?>
	<div class="row message-tbar<?php echo $tasksessionShowHeaderTimer ? ' message-tbar--with-header-timer' : ''; ?>">
	<?php
	if (!function_exists('comon_header_language_enabled')) {
		require_once dirname(__DIR__) . '/includes/sidebar_navigation.php';
	}
	$headerBarSettings = isset($dash_settings) ? $dash_settings : null;
	$showHeaderLanguage = comon_header_language_enabled($headerBarSettings);
	$showHeaderDate = comon_header_date_enabled($headerBarSettings);
	$showHeaderSearch = $session->isLoggedIn() && comon_header_search_enabled($headerBarSettings);
	$hasHeaderMetaBeforeSearch = $showHeaderLanguage || $showHeaderDate;
	$msgDateExtraClass = ($showHeaderSearch && !$hasHeaderMetaBeforeSearch) ? ' msg-date--search-only' : '';
	$msgLftExtraClass = ($showHeaderSearch && !$hasHeaderMetaBeforeSearch) ? ' msg-lft--search-only' : '';
	?>
	<div class="msg-lft<?php echo htmlspecialchars($msgLftExtraClass, ENT_QUOTES, 'UTF-8'); ?>">
	<div class="mobile-menu"><?php echo ts_icon('list'); ?>
	<?php if($mobile_logo && file_exists(dirname(__DIR__) . '/uploads/system-uploads/' . $mobile_logo)){ ?>
    	<img src="<?php echo isset($mobile_logo_url) && $mobile_logo_url ? htmlspecialchars($mobile_logo_url, ENT_QUOTES, 'UTF-8') : getSystemImageUrl($mobile_logo); ?>" class="img-fluid" loading="eager" decoding="async" alt=""/>
	<?php } else { ?>
		<img src="<?php echo $url;?>assets/images/svg/mobile-logo.svg" class="img-fluid"/>
	<?php }?>
	</div>
	<div class="msg-date d-flex<?php echo htmlspecialchars($msgDateExtraClass, ENT_QUOTES, 'UTF-8'); ?>">
	<?php
	if ($showHeaderLanguage) :
	?>
	<form class="language-form" action="#" method="post">
	<div class="language-selecter" type="button" data-bs-toggle="dropdown">
	<?php echo ts_icon('globe'); ?>
	<span class="selected-lang"><?php echo strtoupper($userb->user_language); ?></span>
	<ul class="dropdown-menu dropdown-menu-end language-dropdown-menu">
	<li class="<?php if($userb->user_language == 'en'){echo 'active';}?>" data-value="en">English</li>
	<li class="<?php if($userb->user_language == 'fr'){echo 'active';}?>" data-value="fr">French</li>
	<li class="<?php if($userb->user_language == 'it'){echo 'active';}?>" data-value="it">Italian</li>
	<li class="<?php if($userb->user_language == 'sp'){echo 'active';}?>" data-value="sp">Spanish</li>
	</ul>
	</div>
	<input type="hidden" class="user_language" name="user_language" value="<?php echo $userb->user_language; ?>" />
	</form>
	<?php endif; ?>
	<?php if ($showHeaderDate) : ?>
	<div class="date"><?php $dt = new DateTime();
	echo $dt->format('l j F, Y');?>	</div>
	<?php endif; ?>
	<?php if ($showHeaderSearch) :
		if (!function_exists('comon_mega_search_placeholder')) {
			require_once dirname(__DIR__) . '/includes/mega_search_helper.php';
		}
		$megaSearchCtx = comon_mega_search_context($session);
		$megaSearchPlaceholder = comon_mega_search_placeholder($megaSearchCtx);
		$megaSearchPageUrl = rtrim((string) $url, '/') . '/search';
		$megaSearchHeaderQ = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
	?>
	<?php if ($hasHeaderMetaBeforeSearch) : ?>
	<div class="mega-search-divider" aria-hidden="true"></div>
	<?php endif; ?>
	<form class="mega-search-header-form" action="<?php echo htmlspecialchars($megaSearchPageUrl, ENT_QUOTES, 'UTF-8'); ?>" method="get">
		<span class="mega-search-header-icon" aria-hidden="true">
			<?php echo ts_icon('search'); ?>
		</span>
		<input type="text" name="q" id="mega-search-header-input" class="mega-search-header-input" value="<?php echo htmlspecialchars($megaSearchHeaderQ, ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars($megaSearchPlaceholder, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" aria-label="Search">
	</form>
	<?php endif; ?>
	</div>
	<?php
	$tasksessionMsgIconsClass = 'msg-icons' . ($tasksessionShowHeaderTimer ? ' msg-icons--with-timer' : '');
	?>
	<div class="<?php echo htmlspecialchars($tasksessionMsgIconsClass, ENT_QUOTES, 'UTF-8'); ?>">
	<?php
	$headerAskAiAllowed = false;
	$headerAskAiHref = '';
	$headerAskAiLabel = isset($lang['Ask AI']) ? $lang['Ask AI'] : 'Ask AI';
	if (!function_exists('comon_header_ask_ai_enabled')) {
		require_once dirname(__DIR__) . '/includes/sidebar_navigation.php';
	}
	$headerAskAiVisible = comon_header_ask_ai_enabled($headerBarSettings);
	if ($headerAskAiVisible) {
		if (!function_exists('ai_contextual_button_allowed')) {
			$aiGate = dirname(__DIR__) . '/includes/ai_module_gate.php';
			$aiHelpers = dirname(__DIR__) . '/ai/helpers.php';
			if (is_file($aiGate)) {
				require_once $aiGate;
			}
			if (is_file($aiHelpers)) {
				require_once $aiHelpers;
			}
		}
		if (function_exists('ai_contextual_button_allowed') && ai_contextual_button_allowed() && function_exists('ai_assistant_url')) {
			$headerAskAiAllowed = true;
			$headerAskAiHref = ai_assistant_url();
		}
	}
	if ($tasksessionShowHeaderTimer) :
		$tasksessionTimerRunning = isset($lang['running_timer']) ? $lang['running_timer'] : 'Running';
		$tasksessionTimerPaused = isset($lang['paused_timer']) ? $lang['paused_timer'] : 'Paused';
		$tasksessionLblPause = isset($lang['pause_timer']) ? $lang['pause_timer'] : 'Pause';
		$tasksessionLblResume = isset($lang['resume_timer']) ? $lang['resume_timer'] : 'Resume';
		$tasksessionTwDeleteWork = isset($lang['timer_delete_work']) ? $lang['timer_delete_work'] : 'Delete work';
		$tasksessionTwCompleteWork = isset($lang['timer_complete_work']) ? $lang['timer_complete_work'] : 'Complete work';
		$tasksessionLblMore = isset($lang['Actions']) ? $lang['Actions'] : 'More';
	?>
	<div class="<?php echo htmlspecialchars($tasksessionHeaderTimerSlotClass, ENT_QUOTES, 'UTF-8'); ?>" id="tasksession-header-timer-slot">
		<div class="tasksession-header-timer-skeleton" id="tasksession-header-timer-skeleton" aria-hidden="true">
			<span class="tasksession-header-timer-skeleton-pill">
				<span class="tasksession-header-timer-skeleton-ico"></span>
				<span class="tasksession-header-timer-skeleton-time"></span>
			</span>
		</div>
	<div id="tasksession-header-timer-root" class="dropdown tasksession-header-timer-dropdown d-none align-items-center" role="presentation">
		<button type="button" class="tasksession-header-timer-pill border-0 dropdown-toggle" data-bs-toggle="dropdown" data-bs-display="static" data-bs-auto-close="outside" aria-expanded="false" aria-haspopup="true" title="<?php echo htmlspecialchars(isset($lang['timer']) ? $lang['timer'] : 'Timer'); ?>">
			<svg fill="none" viewBox="0 0 20 20" class="tasksession-header-timer-pill-icon tasksession-timer-complete-timer-ico flex-shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10" stroke-width="1.2" d="M10 6.667v4.166M7.5 1.667h5m4.792 9.375A7.294 7.294 0 0 1 10 18.333a7.294 7.294 0 0 1-7.292-7.291A7.294 7.294 0 0 1 10 3.75a7.294 7.294 0 0 1 7.292 7.292"></path></svg>
			<span class="tasksession-header-timer-pill-time">00:00:00</span>
		</button>
		<div class="dropdown-menu dropdown-menu-end tasksession-header-timer-menu shadow border-0">
			<div class="msg-dropdown-caret caret-up" aria-hidden="true"></div>
			<div class="tasksession-header-timer-card">
				<ul class="list-unstyled mb-0 tasksession-header-timer-list" id="tasksession-header-timer-list"></ul>
				<template id="tasksession-header-timer-row-tpl">
					<li class="tasksession-header-timer-row">
						<div class="tasksession-header-timer-row-inner">
							<div class="tasksession-header-timer-col-text">
								<span class="tasksession-header-timer-row-title"></span>
								<span class="tasksession-header-timer-row-project"></span>
							</div>
							<div class="tasksession-header-timer-row-right">
							<div class="tasksession-header-timer-col-time">
								<time class="tasksession-header-timer-row-elapsed"></time>
							</div>
							<div class="tasksession-header-timer-col-actions"><button type="button" class="tasksession-header-timer-ico-btn tasksession-header-timer-pause" data-task-id="" title="<?php echo htmlspecialchars($tasksessionLblPause); ?>" aria-label="<?php echo htmlspecialchars($tasksessionLblPause); ?>"><svg fill="none" viewBox="0 0 26 26" aria-hidden="true"><path stroke="#8C939F" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="M12.968 23.833c5.983 0 10.833-4.85 10.833-10.834 0-5.983-4.85-10.833-10.833-10.833S2.135 7.016 2.135 12.999s4.85 10.834 10.833 10.834"/><path fill="#8C939F" stroke="#8C939F" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="M11.316 9.573v6.852a.8.8 0 0 1-.034.281.7.7 0 0 1-.226.027H9.514a.7.7 0 0 1-.214-.025.8.8 0 0 1-.034-.283V9.573c0-.179.024-.257.034-.283a.7.7 0 0 1 .214-.024h1.554c.128 0 .19.015.214.024.01.026.034.104.034.283zm4.418 0v6.852a.8.8 0 0 1-.034.283.7.7 0 0 1-.217.025h-1.548a.7.7 0 0 1-.217-.025.8.8 0 0 1-.034-.283V9.573a.8.8 0 0 1 .034-.282.7.7 0 0 1 .217-.025h1.548a.7.7 0 0 1 .217.025.8.8 0 0 1 .034.282z"/></svg></button><button type="button" class="tasksession-header-timer-ico-btn tasksession-header-timer-resume d-none" data-task-id="" title="<?php echo htmlspecialchars($tasksessionLblResume); ?>" aria-label="<?php echo htmlspecialchars($tasksessionLblResume); ?>"><svg fill="none" viewBox="0 0 26 26" aria-hidden="true"><path stroke="#8C939F" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="M12.968 23.833c5.983 0 10.833-4.85 10.833-10.834 0-5.983-4.85-10.833-10.833-10.833S2.135 7.016 2.135 12.999s4.85 10.834 10.833 10.834"></path><path fill="#8C939F" stroke="#8C939F" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="M10.25 8.75v8.5l7.25-4.25-7.25-4.25z"></path></svg></button><button type="button" class="tasksession-header-timer-ico-btn tasksession-header-timer-header-complete" data-task-id="" title="<?php echo htmlspecialchars($tasksessionTwCompleteWork); ?>" aria-label="<?php echo htmlspecialchars($tasksessionTwCompleteWork); ?>"><svg fill="none" viewBox="0 0 26 26" aria-hidden="true"><path stroke="#8C939F" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="M13 23.833c5.958 0 10.833-4.875 10.833-10.833S18.958 2.167 13 2.167 2.167 7.041 2.167 13 7.041 23.833 13 23.833"></path><path stroke="#8C939F" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="m7.222 13 3.847 4.333 7.709-8.666"></path></svg></button></div>
							<div class="dropend tasksession-header-timer-more-wrap">
								<button type="button" class="tasksession-header-timer-more-btn dropdown-toggle" data-bs-toggle="dropdown" data-bs-display="static" data-bs-boundary="viewport" data-bs-offset="4,0" aria-expanded="false" aria-haspopup="true" title="<?php echo htmlspecialchars($tasksessionLblMore); ?>" aria-label="<?php echo htmlspecialchars($tasksessionLblMore); ?>">
									<svg fill="none" viewBox="0 0 18 18" aria-hidden="true"><path fill="#8C939F" d="M9 5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3M9 10.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3M9 16a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3"/></svg>
								</button>
								<ul class="dropdown-menu tasksession-header-timer-more-menu shadow-sm border py-1 small" role="menu">
									<li role="none"><button type="button" class="dropdown-item d-flex align-items-center gap-2 tasksession-header-timer-discard-item" role="menuitem" data-header-timer-action="discard" data-task-id="">
										<?php echo ts_icon('delete', 'tasksession-timer-delete-work-ico flex-shrink-0 tasksession-timer-log-menu-ico me-2'); ?>
										<span><?php echo htmlspecialchars($tasksessionTwDeleteWork); ?></span>
									</button></li>
								</ul>
							</div>
							</div>
						</div>
					</li>
				</template>
			</div>
		</div>
		<span class="d-none tasksession-header-timer-i18n-running"><?php echo htmlspecialchars($tasksessionTimerRunning); ?></span>
		<span class="d-none tasksession-header-timer-i18n-paused"><?php echo htmlspecialchars($tasksessionTimerPaused); ?></span>
	</div>
	</div>
	<?php endif; ?>
	<?php if ($headerAskAiAllowed): ?>
	<div class="header-ask-ai-slot">
	<a href="<?php echo htmlspecialchars($headerAskAiHref, ENT_QUOTES, 'UTF-8'); ?>" class="header-ask-ai" data-ai-rail-open="1" role="button" title="<?php echo htmlspecialchars($headerAskAiLabel, ENT_QUOTES, 'UTF-8'); ?>">
		<?php echo function_exists('ts_icon') ? ts_icon('sparkles', 'header-ask-ai__ico') : ''; ?>
		<span class="header-ask-ai__label"><?php echo htmlspecialchars($headerAskAiLabel, ENT_QUOTES, 'UTF-8'); ?></span>
	</a>
	</div>
	<?php endif; ?>
	<div class="dropdown msg-envelope">
	<div class="msg-icon-img" type="button" type="button" data-bs-toggle="dropdown" data-bs-display="static" data-bs-auto-close="outside">
	<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
	  <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
	</svg>
	<?php 
	$format = '%s';
	$messageCount = 0;
	if (isset($msg) && is_object($msg) && method_exists($msg, 'total_unread_messages')) {
		$unmsg = $msg->total_unread_messages($format);
		$messageCount = $unmsg ? (int)$unmsg : 0;
	}
	
	// Add email notifications count
	if (file_exists(dirname(__FILE__) . '/../includes/notifications.php')) {
		@require_once(dirname(__FILE__) . '/../includes/notifications.php');
		if (class_exists('Notifications')) {
			global $db1;
			$email_notifications_count = 0;
			$chat_reaction_count = 0;
			if (isset($session->userId)) {
				$email_count_query = $db1->query(
					"SELECT COUNT(*) as count FROM notifications 
					WHERE user_id = " . (int)$session->userId . " 
					AND is_read = 0 
					AND related_type = 'email_account'
					AND type IN ('email_received', 'email_thread_updated')"
				);
				if ($email_count_query && $db1->num_rows($email_count_query) > 0) {
					$email_count_row = $db1->fetch_row($email_count_query);
					$email_notifications_count = (int)$email_count_row['count'];
				}
				$react_count_query = $db1->query(
					"SELECT COUNT(*) as count FROM notifications
					WHERE user_id = " . (int)$session->userId . "
					AND is_read = 0
					AND type = 'chat_reaction'"
				);
				if ($react_count_query && $db1->num_rows($react_count_query) > 0) {
					$react_count_row = $db1->fetch_row($react_count_query);
					$chat_reaction_count = (int)$react_count_row['count'];
				}
			}
			$messageCount += $email_notifications_count + $chat_reaction_count;
		}
	}
	?>
	<?php if($messageCount > 0): ?>
	<div class="head-counter"><?php echo $messageCount; ?></div>
	<?php else: ?>
	<div class="head-counter" style="display: none;">0</div>
	<?php endif; ?>
	</div>
	<div  class="dropdown-menu dropdown-menu-end msg-menu">
	<div class="msg-dropdown-caret caret-up" aria-hidden="true"></div>
	<div class="msg-menuww">
	<div class="notification-header">
	<h4><?php echo $lang['New Messages'] ?? 'New messages'; ?></h4>
	<button type="button" class="btn secondary-btn-a mark-all-messages-read"><?php echo $lang['Mark all as read']; ?></button>
	</div>
	<div class="unread-scrolls scroll-bar">
	<ul class="header-msg-dropdown-list" data-header-dropdown-list="messages" aria-busy="true">
	<li class="header-dropdown-skeleton header-dd-skel" role="status" aria-label="Loading messages" aria-hidden="true">
		<div class="header-dd-skel-row"><div class="reports-skel skel header-dd-skel-avatar"></div><div class="header-dd-skel-col"><div class="reports-skel skel header-dd-skel-line header-dd-skel-line--lg"></div><div class="reports-skel skel header-dd-skel-line header-dd-skel-line--md"></div><div class="reports-skel skel header-dd-skel-line header-dd-skel-line--sm"></div></div></div>
		<div class="header-dd-skel-row"><div class="reports-skel skel header-dd-skel-avatar"></div><div class="header-dd-skel-col"><div class="reports-skel skel header-dd-skel-line header-dd-skel-line--lg"></div><div class="reports-skel skel header-dd-skel-line header-dd-skel-line--md"></div><div class="reports-skel skel header-dd-skel-line header-dd-skel-line--sm"></div></div></div>
		<div class="header-dd-skel-row"><div class="reports-skel skel header-dd-skel-avatar"></div><div class="header-dd-skel-col"><div class="reports-skel skel header-dd-skel-line header-dd-skel-line--lg"></div><div class="reports-skel skel header-dd-skel-line header-dd-skel-line--md"></div><div class="reports-skel skel header-dd-skel-line header-dd-skel-line--sm"></div></div></div>
		<div class="header-dd-skel-row"><div class="reports-skel skel header-dd-skel-avatar"></div><div class="header-dd-skel-col"><div class="reports-skel skel header-dd-skel-line header-dd-skel-line--lg"></div><div class="reports-skel skel header-dd-skel-line header-dd-skel-line--md"></div><div class="reports-skel skel header-dd-skel-line header-dd-skel-line--sm"></div></div></div>
		<div class="header-dd-skel-row"><div class="reports-skel skel header-dd-skel-avatar"></div><div class="header-dd-skel-col"><div class="reports-skel skel header-dd-skel-line header-dd-skel-line--lg"></div><div class="reports-skel skel header-dd-skel-line header-dd-skel-line--md"></div><div class="reports-skel skel header-dd-skel-line header-dd-skel-line--sm"></div></div></div>
	</li>
	</ul>
	</div>
	</div>
	</div>
	</div>
	<div class="dropdown notification-icons">
    <button class="notification-icon" type="button" data-bs-toggle="dropdown" data-bs-display="static" data-bs-auto-close="outside">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0M3.124 7.5A8.969 8.969 0 0 1 5.292 3m13.416 0a8.969 8.969 0 0 1 2.168 4.5" />
    </svg>
    <?php 
    // Notification count is already calculated at the top of the file
    ?>
    <?php if($notificationCount > 0): ?>
    <div class="head-counter notification-count"><?php echo $notificationCount; ?></div>
    <?php else: ?>
    <div class="head-counter notification-count" style="display: none;">0</div>
    <?php endif; ?>
    </button>
    <div class="dropdown-menu dropdown-menu-end msg-menu">
    <div class="msg-dropdown-caret caret-up" aria-hidden="true"></div>
    <div class="notification-menu-wrap">
    <div class="notification-header">
    <h4><?php echo $lang['Notifications']; ?></h4>
    <div class="notification-header-actions d-flex align-items-center col-gap-5">
    <a href="<?php echo htmlspecialchars(rtrim((string) $url, '/') . '/activity', ENT_QUOTES, 'UTF-8'); ?>" class="btn secondary-btn-a notification-view-all"><?php echo htmlspecialchars($lang['View all'] ?? 'View all', ENT_QUOTES, 'UTF-8'); ?></a>
    <button type="button" class="btn secondary-btn-a mark-all-read"><?php echo htmlspecialchars($lang['Read all'] ?? 'Read all', ENT_QUOTES, 'UTF-8'); ?></button>
    </div>
    </div>
    <div class="unread-scrolls scroll-bar all-notify" data-header-dropdown-list="notifications" aria-busy="true">
    <div class="header-dropdown-skeleton header-dd-skel" role="status" aria-label="Loading notifications" aria-hidden="true">
        <div class="header-dd-skel-row"><div class="reports-skel skel header-dd-skel-avatar"></div><div class="header-dd-skel-col"><div class="reports-skel skel header-dd-skel-line header-dd-skel-line--lg"></div><div class="reports-skel skel header-dd-skel-line header-dd-skel-line--md"></div><div class="reports-skel skel header-dd-skel-line header-dd-skel-line--sm"></div></div></div>
        <div class="header-dd-skel-row"><div class="reports-skel skel header-dd-skel-avatar"></div><div class="header-dd-skel-col"><div class="reports-skel skel header-dd-skel-line header-dd-skel-line--lg"></div><div class="reports-skel skel header-dd-skel-line header-dd-skel-line--md"></div><div class="reports-skel skel header-dd-skel-line header-dd-skel-line--sm"></div></div></div>
        <div class="header-dd-skel-row"><div class="reports-skel skel header-dd-skel-avatar"></div><div class="header-dd-skel-col"><div class="reports-skel skel header-dd-skel-line header-dd-skel-line--lg"></div><div class="reports-skel skel header-dd-skel-line header-dd-skel-line--md"></div><div class="reports-skel skel header-dd-skel-line header-dd-skel-line--sm"></div></div></div>
        <div class="header-dd-skel-row"><div class="reports-skel skel header-dd-skel-avatar"></div><div class="header-dd-skel-col"><div class="reports-skel skel header-dd-skel-line header-dd-skel-line--lg"></div><div class="reports-skel skel header-dd-skel-line header-dd-skel-line--md"></div><div class="reports-skel skel header-dd-skel-line header-dd-skel-line--sm"></div></div></div>
        <div class="header-dd-skel-row"><div class="reports-skel skel header-dd-skel-avatar"></div><div class="header-dd-skel-col"><div class="reports-skel skel header-dd-skel-line header-dd-skel-line--lg"></div><div class="reports-skel skel header-dd-skel-line header-dd-skel-line--md"></div><div class="reports-skel skel header-dd-skel-line header-dd-skel-line--sm"></div></div></div>
    </div>
    </div>
    </div>
    </div>
	</div>
	</div>
	</div>
	<div class=" msg-rt">
	<div class="msr-wrapc">
	<div class="msg-welcome"><span><?php echo $lang['WELCOME']; ?></span><br><?php 
	if(isset($username)){
	echo $username;
	}	?></div>	<div class="msg-img">
    <?php 
    // Get user data for avatar
    $userFirstName = $userb->firstName ?? '';
    $userLastName = $userb->lastName ?? '';
    echo getUserAvatarHtml($session->userId, $userFirstName, $userLastName, 50, 50, 'img-fluid rounded-circle', $userFirstName . ' ' . $userLastName, 'eager');
    ?>	
		<div class="caret-down"></div>
    </div>
	<div class="logout-menu">
	<div class="logout-menuwrap justify-menu">
	<div class="profile-dropdown-caret caret-up" aria-hidden="true"></div>
	<ul>
	<?php
	$viewProfilePath = '';
	if (isset($_SESSION['accountStatus'])) {
		if ($_SESSION['accountStatus'] == 1) {
			$viewProfilePath = $url . 'admin/profile?user_id=' . $session->userId;
		} elseif ($_SESSION['accountStatus'] == 2) {
			$viewProfilePath = $url . 'client/profile?user_id=' . $session->userId;
		} elseif ($_SESSION['accountStatus'] == 3) {
			$viewProfilePath = $url . 'staff/profile?user_id=' . $session->userId;
		}
	}
	?>
	<li>
	  <a href="<?php echo $viewProfilePath; ?>">
		<?php echo ts_icon('profile', 'tasksession-timer-log-menu-ico me-2'); ?>
		<?php echo $lang['View Profile']; ?>
	  </a>
	</li>
	<?php
	$editProfilePath = '';
	if (isset($_SESSION['accountStatus'])) {
		if ($_SESSION['accountStatus'] == 1) { // Admin
			$editProfilePath = $url . 'admin/edit?editprofile=' . $session->userId;
		} elseif ($_SESSION['accountStatus'] == 2) { // Client
			$editProfilePath = $url . 'client/edit?editprofile=' . $session->userId;
		} elseif ($_SESSION['accountStatus'] == 3) { // Staff
			$editProfilePath = $url . 'staff/edit?editprofile=' . $session->userId;
		}
	}
	?>
	<li><a href="<?php echo $editProfilePath; ?>">
	<?php echo ts_icon('edit', 'tasksession-timer-log-menu-ico me-2'); ?>
	<?php echo $lang['Edit Profile']; ?></a></li>
	<ul><li class="logout"><a href="<?php echo $url; ?>logout.php">
	<?php echo ts_icon('logout', 'tasksession-timer-log-menu-ico me-2'); ?>
     <?php echo $lang['Logout']; ?></a></li>
	</ul>
	</div>
	</div>
	</div>
	</div>
	</div> 
<?php
// Prepare unread messages array for JS (dropdown UI is filled by message-notification.js)
$unread_msgs = [];
if (isset($msg) && is_object($msg) && method_exists($msg, 'get_unread_messages')) {
    $unread_msgs = $msg->get_unread_messages();
}
$unread_msgs_js = [];
if (!empty($unread_msgs)) {
    foreach ($unread_msgs as $unread_msg) {
        $unread_uid = $unread_msg['user_id'];
        $unread_umsg = $unread_msg['message'];
        $Project_id = $unread_msg['Project_id'];
        $unread_time = $unread_msg['time'];
        $row2 = [];
        $unread_uid_int = (int)$unread_uid;
        $Project_id_int = (int)$Project_id;
        $row2 = [];
        if (isset($connect)) {
            $stmt = $connect->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $unread_uid_int);
            $stmt->execute();
            $query2 = $stmt->get_result();
            $row2 = $query2->fetch_assoc();
            $stmt->close();
        }
        // Use global function to get avatar data
        $senderFirstName = $row2['firstName'] ?? '';
        $senderLastName = isset($row2['lastName']) ? $row2['lastName'] : '';
        $avatarData = getUserAvatarData($unread_uid_int, $senderFirstName, $senderLastName, 40, 40);
        $profile_pic_url = $avatarData['type'] === 'image' ? $avatarData['url'] : '';
        $sender_name = $row2['firstName'] ?? '';
        $type = ($Project_id_int != 0) ? 'discussion' : 'chat';
        $project_title = '';
        if ($type === 'discussion') {
            $stmt = $connect->prepare("SELECT * FROM projects WHERE p_id = ?");
            $stmt->bind_param("i", $Project_id_int);
            $stmt->execute();
            $proResult = $stmt->get_result();
            $proRow = $proResult->fetch_assoc();
            $stmt->close();
            $project_title = $proRow ? $proRow['project_title'] : '';
        }
        $time_str = date("g:iA", $unread_time);
        if (!empty($unread_umsg) && function_exists('decryptString')) {
            try {
                $decrypted = decryptString($unread_umsg);
                if ($decrypted !== false && $decrypted !== $unread_umsg) {
                    $unread_umsg = $decrypted;
                }
            } catch (Exception $e) {
            }
        }
        if (!function_exists('msg_notif_format_message_preview')) {
            $__mnr = dirname(__DIR__) . '/includes/message_notification_read_helper.php';
            if (is_file($__mnr)) { require_once $__mnr; }
        }
        if (function_exists('msg_notif_format_message_preview')) {
            $unread_umsg = msg_notif_format_message_preview($unread_umsg);
        }
        $unread_msgs_js[] = [
            'profile_pic_url' => $profile_pic_url,
            'avatar_data' => $avatarData,
            'sender_name' => $sender_name,
            'type' => $type,
            'project_title' => $project_title,
            'message' => $unread_umsg,
            'time' => (int) $unread_time,
            'time_str' => $time_str,
            'user_id' => $unread_uid,
            'project_id' => $Project_id,
            'Project_id' => $Project_id
        ];
    }
}
if (!function_exists('msg_notif_append_chat_reactions')) {
    $__mnr = dirname(__DIR__) . '/includes/message_notification_read_helper.php';
    if (is_file($__mnr)) { require_once $__mnr; }
}
if (isset($session->userId) && (int)$session->userId > 0 && function_exists('msg_notif_append_chat_reactions')) {
    global $db1, $url;
    msg_notif_append_chat_reactions($db1, (int)$session->userId, $unread_msgs_js, $url);
}
if (!empty($unread_msgs_js)) {
    usort($unread_msgs_js, function ($a, $b) {
        $ta = isset($a['time']) ? (int) $a['time'] : 0;
        $tb = isset($b['time']) ? (int) $b['time'] : 0;
        return $tb <=> $ta;
    });
}
?>
<script>
window.unreadMessages = <?php echo json_encode($unread_msgs_js); ?>;
window.baseUrl = "<?php echo rtrim($url, '/'); ?>/";
</script>
<?php
if (!defined('CHAT_TAB_NOTIFY_JS_V1')) {
	define('CHAT_TAB_NOTIFY_JS_V1', true);
	$__chatTabNotifyJs = dirname(__DIR__) . '/assets/js/chat-tab-notify.js';
	if (is_file($__chatTabNotifyJs)) {
		echo '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . 'assets/js/chat-tab-notify.js?v=' . (int)@filemtime($__chatTabNotifyJs) . '"></script>' . "\n";
	}
}
?>
<?php if (empty($GLOBALS['deferHeaderMessageNotifScript'])) {
	$__msgNotifJs = dirname(__DIR__) . '/assets/js/message-notification.js';
?>
<script src="<?php echo $url; ?>assets/js/message-notification.js?v=<?php echo (int)@filemtime($__msgNotifJs); ?>"></script>
<?php } ?>
<?php if ($session->isLoggedIn()) {
	if (!function_exists('comon_header_search_enabled')) {
		require_once dirname(__DIR__) . '/includes/sidebar_navigation.php';
	}
	$__headerSearchSettings = isset($dash_settings) ? $dash_settings : null;
	if (comon_header_search_enabled($__headerSearchSettings)) {
		$__msJs = @filemtime(dirname(__DIR__) . '/assets/js/mega-search.js') ?: time();
		$__megaSearchUrl = rtrim((string) $url, '/') . '/search';
		$__megaSearchAjax = rtrim((string) $url, '/') . '/ajax/mega_search.php';
?>
<script>
window.MEGA_SEARCH = Object.assign({}, window.MEGA_SEARCH || {}, {
	searchUrl: <?php echo json_encode($__megaSearchUrl, JSON_UNESCAPED_SLASHES); ?>,
	ajaxUrl: <?php echo json_encode($__megaSearchAjax, JSON_UNESCAPED_SLASHES); ?>,
	debounceMs: 300
});
</script>
<script src="<?php echo $url; ?>assets/js/mega-search.js?v=<?php echo (int) $__msJs; ?>"></script>
<?php
	}
} ?>
