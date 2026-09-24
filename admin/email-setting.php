<?php 
/*
 ================================================================================
   Task Session – Project Management System
   File    : email-setting.php
   Purpose : Allows administrators to <?php echo $lang['Configure']; ?> and update system email notification templates.
 ================================================================================
 */
ob_start(); 
require_once("../includes/lib-initialize.php");

if (!($session->isLoggedIn())) {
	redirectTo($url . "index.php");
}
if ($_SESSION['accountStatus'] == 2) {
	redirectTo($url . "client/index.php");
}
if ($_SESSION['accountStatus'] == 3) {
	redirectTo($url . "staff/index.php");
}

require_once dirname(__DIR__) . '/includes/email_template_logo_helper.php';

/**
 * Redirect after save and stop rendering this huge page (was the main save delay).
 */
function email_setting_redirect($ok, $hash = '') {
	while (ob_get_level()) {
		ob_end_clean();
	}
	$status = $ok ? 'success' : 'fail';
	$loc = 'email-setting.php?message=' . $status;
	if ($hash !== '') {
		$loc .= '#' . ltrim($hash, '#');
	}
	header('Location: ' . $loc);
	exit;
}

/**
 * UPDATE only the given settings columns (skips rewriting every email template TEXT).
 * @param array<string,mixed> $columns
 */
function email_setting_save_columns(array $columns) {
	$row = new settings();
	$row->id = 1;
	foreach ($columns as $key => $value) {
		$row->$key = $value;
	}
	return $row->updateFields(array_keys($columns));
}

/** Store HTML templates with {LOGO} collapsed (shared across all email templates). */
function email_setting_store_html($raw) {
	static $logoCtx = null;
	if ($logoCtx === null) {
		}
	return task_chat_email_logo_collapse_for_storage(sanitize_tinymce_content($raw ?? ''), $logoCtx);
}

/** Editor preview: expand {LOGO} to logo image. */
function email_setting_editor_html($html) {
	global $settings;
	return task_chat_email_logo_expand_for_editor((string) $html, $settings);
}

/** Company name + logo shortcodes (values come from EmailHelper / settings). */
function email_setting_echo_brand_shortcodes() {
	global $lang;
	$companyLabel = !empty($lang['Company Name']) ? $lang['Company Name'] : 'Company name';
	$logoLabel = !empty($lang['Email template logo']) ? $lang['Email template logo'] : 'Email template logo';
	echo '<li>' . htmlspecialchars($companyLabel, ENT_QUOTES, 'UTF-8') . ': {COMPANY_NAME}</li>';
	echo '<li>' . htmlspecialchars($logoLabel, ENT_QUOTES, 'UTF-8') . ': {LOGO}</li>';
}

// --- Save handlers run before any HTML output ---

/**
 * Map posted form fields → settings columns (used by single + batch save).
 * @return array<string,mixed>
 */
function email_setting_columns_from_post(array $post) {
	$mapHtml = array(
		'createaccount' => 'create_account_email',
		'forgetaccount' => 'forget_email',
		'projectassign' => 'project_assign_email',
		'projectassignstaff' => 'assign_staff_email',
		'projectupdate' => 'project_update_email',
		'taskcreate' => 'task_create_email',
		'taskupdate' => 'task_update_email',
		'invoicecreate' => 'invoice_create_email',
		'invoicepaid' => 'invoice_paid_email',
		'invoicepaidadmin' => 'invoice_paid_email_admin',
		'email_report_template' => 'email_report_template',
		'payment_reminder_1_template' => 'payment_reminder_1_template',
		'payment_reminder_2_template' => 'payment_reminder_2_template',
		'payment_reminder_3_template' => 'payment_reminder_3_template',
		'task_reminder_1_template' => 'task_reminder_1_template',
		'task_reminder_2_template' => 'task_reminder_2_template',
		'task_reminder_3_template' => 'task_reminder_3_template',
		'groupchatcreate' => 'group_chat_create_email',
		'groupchatmessage' => 'group_chat_message_email',
		'groupchatbatch' => 'group_chat_batch_email',
		'onetoonechatbatch' => 'one_to_one_chat_batch_email',
		'taskchatbatch' => 'task_chat_batch_email',
	);
	$mapText = array(
		'email_report_subject' => 'email_report_subject',
		'payment_reminder_1_subject' => 'payment_reminder_1_subject',
		'payment_reminder_2_subject' => 'payment_reminder_2_subject',
		'payment_reminder_3_subject' => 'payment_reminder_3_subject',
		'task_reminder_1_subject' => 'task_reminder_1_subject',
		'task_reminder_2_subject' => 'task_reminder_2_subject',
		'task_reminder_3_subject' => 'task_reminder_3_subject',
		'groupchatcreate_subject' => 'group_chat_create_email_subject',
		'groupchatmessage_subject' => 'group_chat_message_email_subject',
		'groupchatbatch_subject' => 'group_chat_batch_email_subject',
		'onetoonechatbatch_subject' => 'one_to_one_chat_batch_email_subject',
		'taskchatbatch_subject' => 'task_chat_batch_email_subject',
	);
	$columns = array();
	foreach ($mapHtml as $postKey => $col) {
		if (array_key_exists($postKey, $post)) {
			$columns[$col] = email_setting_store_html($post[$postKey]);
		}
	}
	foreach ($mapText as $postKey => $col) {
		if (array_key_exists($postKey, $post)) {
			$columns[$col] = trim((string) $post[$postKey]);
		}
	}
	return $columns;
}

// Batch save: all open Configure templates in one request
if (isset($_POST['email_settings_batch_save'])) {
	$tab = isset($_POST['email_settings_tab']) ? preg_replace('/[^a-z0-9\-_]/i', '', (string) $_POST['email_settings_tab']) : '';
	$columns = email_setting_columns_from_post($_POST);
	if (empty($columns)) {
		email_setting_redirect(false, $tab);
	}
	email_setting_redirect(email_setting_save_columns($columns), $tab);
}

if (isset($_POST['createfrm'])) {
	email_setting_redirect(email_setting_save_columns(array(
		'create_account_email' => email_setting_store_html($_POST['createaccount'] ?? ''),
	)));
}
if (isset($_POST['forgetfrm'])) {
	email_setting_redirect(email_setting_save_columns(array(
		'forget_email' => email_setting_store_html($_POST['forgetaccount'] ?? ''),
	)));
}
if (isset($_POST['proassfrm'])) {
	email_setting_redirect(email_setting_save_columns(array(
		'project_assign_email' => email_setting_store_html($_POST['projectassign'] ?? ''),
	)));
}
if (isset($_POST['proassfrmstaff'])) {
	email_setting_redirect(email_setting_save_columns(array(
		'assign_staff_email' => email_setting_store_html($_POST['projectassignstaff'] ?? ''),
	)));
}
if (isset($_POST['proupdatefrm'])) {
	email_setting_redirect(email_setting_save_columns(array(
		'project_update_email' => email_setting_store_html($_POST['projectupdate'] ?? ''),
	)));
}
if (isset($_POST['taskcreatefrm'])) {
	email_setting_redirect(email_setting_save_columns(array(
		'task_create_email' => email_setting_store_html($_POST['taskcreate'] ?? ''),
	)));
}
if (isset($_POST['taskupdatefrm'])) {
	email_setting_redirect(email_setting_save_columns(array(
		'task_update_email' => email_setting_store_html($_POST['taskupdate'] ?? ''),
	)));
}
if (isset($_POST['invoicecreatefrm'])) {
	email_setting_redirect(email_setting_save_columns(array(
		'invoice_create_email' => email_setting_store_html($_POST['invoicecreate'] ?? ''),
	)));
}
if (isset($_POST['invoicepaidfrm'])) {
	email_setting_redirect(email_setting_save_columns(array(
		'invoice_paid_email' => email_setting_store_html($_POST['invoicepaid'] ?? ''),
	)));
}
if (isset($_POST['invoicepaidadminfrm'])) {
	email_setting_redirect(email_setting_save_columns(array(
		'invoice_paid_email_admin' => email_setting_store_html($_POST['invoicepaidadmin'] ?? ''),
	)));
}
if (isset($_POST['groupchatcreatefrm'])) {
	email_setting_redirect(email_setting_save_columns(array(
		'group_chat_create_email' => email_setting_store_html($_POST['groupchatcreate'] ?? ''),
		'group_chat_create_email_subject' => isset($_POST['groupchatcreate_subject']) ? trim($_POST['groupchatcreate_subject']) : '',
	)), 'messages');
}
if (isset($_POST['groupchatmessagefrm'])) {
	email_setting_redirect(email_setting_save_columns(array(
		'group_chat_message_email' => email_setting_store_html($_POST['groupchatmessage'] ?? ''),
		'group_chat_message_email_subject' => isset($_POST['groupchatmessage_subject']) ? trim($_POST['groupchatmessage_subject']) : '',
	)), 'messages');
}
if (isset($_POST['groupchatbatchfrm'])) {
	email_setting_redirect(email_setting_save_columns(array(
		'group_chat_batch_email' => email_setting_store_html($_POST['groupchatbatch'] ?? ''),
		'group_chat_batch_email_subject' => isset($_POST['groupchatbatch_subject']) ? trim($_POST['groupchatbatch_subject']) : '',
	)), 'messages');
}
if (isset($_POST['onetoonechatbatchfrm'])) {
	email_setting_redirect(email_setting_save_columns(array(
		'one_to_one_chat_batch_email' => email_setting_store_html($_POST['onetoonechatbatch'] ?? ''),
		'one_to_one_chat_batch_email_subject' => isset($_POST['onetoonechatbatch_subject']) ? trim($_POST['onetoonechatbatch_subject']) : '',
	)), 'messages');
}
if (isset($_POST['taskchatbatchfrm'])) {
	email_setting_redirect(email_setting_save_columns(array(
		'task_chat_batch_email' => email_setting_store_html($_POST['taskchatbatch'] ?? ''),
		'task_chat_batch_email_subject' => isset($_POST['taskchatbatch_subject']) ? trim($_POST['taskchatbatch_subject']) : '',
	)), 'messages');
}

if (isset($_POST['update_frequency_only']) && $_POST['update_frequency_only'] == '1' && isset($_POST['email_report_frequency'])) {
	while (ob_get_level()) {
		ob_end_clean();
	}
	header('Content-Type: application/json');
	if (!$session->isLoggedIn() || $_SESSION['accountStatus'] != 1) {
		echo json_encode(['success' => false, 'message' => 'Unauthorized']);
		exit;
	}
	$ok = email_setting_save_columns(array(
		'email_report_frequency' => trim($_POST['email_report_frequency']),
	));
	echo json_encode($ok
		? ['success' => true, 'message' => 'Frequency updated successfully']
		: ['success' => false, 'message' => 'Failed to update frequency']);
	exit;
}

if (isset($_POST['email_report_settings'])) {
	email_setting_redirect(email_setting_save_columns(array(
		'email_report_template' => email_setting_store_html($_POST['email_report_template'] ?? ''),
		'email_report_subject' => isset($_POST['email_report_subject']) ? trim($_POST['email_report_subject']) : 'Email Account Summary Report',
	)), 'email-report');
}
if (isset($_POST['payment_reminders_toggle'])) {
	email_setting_redirect(email_setting_save_columns(array(
		'payment_reminders_enabled' => isset($_POST['payment_reminders_enabled']) ? 1 : 0,
	)), 'invoices');
}
if (isset($_POST['payment_reminder_settings'])) {
	email_setting_redirect(email_setting_save_columns(array(
		'payment_reminder_1_enabled' => isset($_POST['payment_reminder_1_enabled']) ? 1 : 0,
		'payment_reminder_1_days' => isset($_POST['payment_reminder_1_days']) ? (int)$_POST['payment_reminder_1_days'] : 7,
		'payment_reminder_1_type' => isset($_POST['payment_reminder_1_type']) ? $_POST['payment_reminder_1_type'] : 'before',
		'payment_reminder_1_subject' => isset($_POST['payment_reminder_1_subject']) ? trim($_POST['payment_reminder_1_subject']) : '',
		'payment_reminder_2_enabled' => isset($_POST['payment_reminder_2_enabled']) ? 1 : 0,
		'payment_reminder_2_days' => isset($_POST['payment_reminder_2_days']) ? (int)$_POST['payment_reminder_2_days'] : 3,
		'payment_reminder_2_type' => isset($_POST['payment_reminder_2_type']) ? $_POST['payment_reminder_2_type'] : 'after',
		'payment_reminder_2_subject' => isset($_POST['payment_reminder_2_subject']) ? trim($_POST['payment_reminder_2_subject']) : '',
		'payment_reminder_3_enabled' => isset($_POST['payment_reminder_3_enabled']) ? 1 : 0,
		'payment_reminder_3_days' => isset($_POST['payment_reminder_3_days']) ? (int)$_POST['payment_reminder_3_days'] : 14,
		'payment_reminder_3_type' => isset($_POST['payment_reminder_3_type']) ? $_POST['payment_reminder_3_type'] : 'after',
		'payment_reminder_3_subject' => isset($_POST['payment_reminder_3_subject']) ? trim($_POST['payment_reminder_3_subject']) : '',
		'payment_reminder_send_to_client' => isset($_POST['payment_reminder_send_to_client']) ? 1 : 0,
		'payment_reminder_send_to_staff' => isset($_POST['payment_reminder_send_to_staff']) ? 1 : 0,
		'payment_reminder_notify_admin_in_app' => isset($_POST['payment_reminder_notify_admin_in_app']) ? 1 : 0,
	)), 'invoices');
}
if (isset($_POST['payment_reminder_1_templatefrm'])) {
	email_setting_redirect(email_setting_save_columns(array(
		'payment_reminder_1_template' => email_setting_store_html($_POST['payment_reminder_1_template'] ?? ''),
		'payment_reminder_1_subject' => isset($_POST['payment_reminder_1_subject']) ? trim($_POST['payment_reminder_1_subject']) : '',
	)), 'invoices');
}
if (isset($_POST['payment_reminder_2_templatefrm'])) {
	email_setting_redirect(email_setting_save_columns(array(
		'payment_reminder_2_template' => email_setting_store_html($_POST['payment_reminder_2_template'] ?? ''),
		'payment_reminder_2_subject' => isset($_POST['payment_reminder_2_subject']) ? trim($_POST['payment_reminder_2_subject']) : '',
	)), 'invoices');
}
if (isset($_POST['payment_reminder_3_templatefrm'])) {
	email_setting_redirect(email_setting_save_columns(array(
		'payment_reminder_3_template' => email_setting_store_html($_POST['payment_reminder_3_template'] ?? ''),
		'payment_reminder_3_subject' => isset($_POST['payment_reminder_3_subject']) ? trim($_POST['payment_reminder_3_subject']) : '',
	)), 'invoices');
}
if (isset($_POST['task_update_email_on_drag_toggle'])) {
	email_setting_redirect(email_setting_save_columns(array(
		'task_update_email_on_drag' => isset($_POST['task_update_email_on_drag']) ? 1 : 0,
	)), 'tasks');
}
if (isset($_POST['task_reminders_toggle'])) {
	email_setting_redirect(email_setting_save_columns(array(
		'task_reminders_enabled' => isset($_POST['task_reminders_enabled']) ? 1 : 0,
	)), 'tasks');
}
if (isset($_POST['task_reminder_settings'])) {
	email_setting_redirect(email_setting_save_columns(array(
		'task_reminder_1_enabled' => isset($_POST['task_reminder_1_enabled']) ? 1 : 0,
		'task_reminder_1_days' => isset($_POST['task_reminder_1_days']) ? (int)$_POST['task_reminder_1_days'] : 7,
		'task_reminder_1_type' => isset($_POST['task_reminder_1_type']) ? $_POST['task_reminder_1_type'] : 'before',
		'task_reminder_2_enabled' => isset($_POST['task_reminder_2_enabled']) ? 1 : 0,
		'task_reminder_2_days' => isset($_POST['task_reminder_2_days']) ? (int)$_POST['task_reminder_2_days'] : 3,
		'task_reminder_2_type' => isset($_POST['task_reminder_2_type']) ? $_POST['task_reminder_2_type'] : 'after',
		'task_reminder_3_enabled' => isset($_POST['task_reminder_3_enabled']) ? 1 : 0,
		'task_reminder_3_days' => isset($_POST['task_reminder_3_days']) ? (int)$_POST['task_reminder_3_days'] : 14,
		'task_reminder_3_type' => isset($_POST['task_reminder_3_type']) ? $_POST['task_reminder_3_type'] : 'after',
		'task_reminder_send_to_staff' => isset($_POST['task_reminder_send_to_staff']) ? 1 : 0,
		'task_reminder_send_to_client' => isset($_POST['task_reminder_send_to_client']) ? 1 : 0,
		'task_reminder_send_to_creator' => isset($_POST['task_reminder_send_to_creator']) ? 1 : 0,
	)), 'tasks');
}
if (isset($_POST['task_reminder_1_templatefrm'])) {
	email_setting_redirect(email_setting_save_columns(array(
		'task_reminder_1_template' => email_setting_store_html($_POST['task_reminder_1_template'] ?? ''),
		'task_reminder_1_subject' => isset($_POST['task_reminder_1_subject']) ? trim($_POST['task_reminder_1_subject']) : '',
	)), 'tasks');
}
if (isset($_POST['task_reminder_2_templatefrm'])) {
	email_setting_redirect(email_setting_save_columns(array(
		'task_reminder_2_template' => email_setting_store_html($_POST['task_reminder_2_template'] ?? ''),
		'task_reminder_2_subject' => isset($_POST['task_reminder_2_subject']) ? trim($_POST['task_reminder_2_subject']) : '',
	)), 'tasks');
}
if (isset($_POST['task_reminder_3_templatefrm'])) {
	email_setting_redirect(email_setting_save_columns(array(
		'task_reminder_3_template' => email_setting_store_html($_POST['task_reminder_3_template'] ?? ''),
		'task_reminder_3_subject' => isset($_POST['task_reminder_3_subject']) ? trim($_POST['task_reminder_3_subject']) : '',
	)), 'tasks');
}

// Mark setup-guide step before header so "You're all set" can render on this same load.
if (isset($_GET['message']) && $_GET['message'] === 'success') {
    if (!function_exists('setup_guide_mark_visit')) {
        require_once dirname(__DIR__) . '/includes/setup_guide.php';
    }
    setup_guide_mark_visit('email_notifications');
}
$title = "System Setting | ". $syatem_title;
include("../templates/header.php");

// load current logged-in user
$id = $session->userId;
$user = User::findById((int)$id);
$username = $user->firstName;
$email = $user->email;
$account_stat = $user->status;

/* I prefer not to use $_REQUEST...but for those who do: */
$_REQUEST = (array)$_POST + (array)$_GET + (array)$_REQUEST;

/** @var array{type:string,msg:string}|null Shown via assets/js/toast.js after ?message= */
$toast_flash = null;

// Load settings only when rendering the page (after POST handlers exit)
$settings = settings::findById(1);
$taskUpdateEmailOnDrag = !isset($settings->task_update_email_on_drag) || (int) $settings->task_update_email_on_drag !== 0;
$invoiceModuleEnabled = !empty($settings->module_invoices);
$isFreeEditionEmailSettings = function_exists('tasksession_is_free_edition') && tasksession_is_free_edition();
if ($isFreeEditionEmailSettings) {
    // Invoices / chat / workspace email report templates are Pro-only
    $invoiceModuleEnabled = false;
}

if (isset($_GET['message'])) {
    $msgstatus = $_GET['message'];
    $notmessagea = $lang['System Settings has been saved successfully!'];
    $notmessageb = $lang['Same values will not be updated, please make changes and save settings again, Thanks'];
    if ($msgstatus == 'success') {
        $toast_flash = array('type' => 'success', 'msg' => $notmessagea);
    }
    if ($msgstatus == 'fail') {
        $toast_flash = array('type' => 'error', 'msg' => $notmessageb);
    }
}
?>
	<div class="page-container">
		<div class="container-fluid">
			<div class="row row-eq-height">
				<?php  include("../templates/sidebar.php"); ?>
					<div class="page-content" style="padding-bottom:0;">
						<?php include('../templates/top-header.php'); ?>
							<div class="row system-wrap vh-100-1">
								<?php include("../templates/system-nav.php"); ?>
								<div class="col-md-9 ss-right">
									<h2 class="page-title d-flex justify-content-between align-items-center flex-wrap row-gap-10">
										<?php echo $lang['Email Notifications']; ?>
										<button type="button" class="primary-btn" id="emailSettingsSaveBtn">
											<?php echo $lang['Save Setting']; ?>
										</button>
									</h2>

									<!-- Tab Navigation -->
									<ul class="nav nav-tabs" id="emailTabs" role="tablist">
										<li class="nav-item" role="presentation">
											<button class="nav-link active" id="account-tab" data-bs-toggle="tab" data-bs-target="#account" type="button" role="tab" aria-controls="account" aria-selected="true">Account</button>
										</li>
										<li class="nav-item" role="presentation">
											<button class="nav-link" id="projects-tab" data-bs-toggle="tab" data-bs-target="#projects" type="button" role="tab" aria-controls="projects" aria-selected="false">Projects</button>
										</li>
										<li class="nav-item" role="presentation">
											<button class="nav-link" id="tasks-tab" data-bs-toggle="tab" data-bs-target="#tasks" type="button" role="tab" aria-controls="tasks" aria-selected="false">Tasks</button>
										</li>
										<?php if ($invoiceModuleEnabled): ?>
										<li class="nav-item" role="presentation">
											<button class="nav-link" id="invoices-tab" data-bs-toggle="tab" data-bs-target="#invoices" type="button" role="tab" aria-controls="invoices" aria-selected="false">Invoices</button>
										</li>
										<?php endif; ?>
										<?php if (!$isFreeEditionEmailSettings): ?>
										<li class="nav-item" role="presentation">
											<button class="nav-link" id="messages-tab" data-bs-toggle="tab" data-bs-target="#messages" type="button" role="tab" aria-controls="messages" aria-selected="false">Messages</button>
										</li>
										<li class="nav-item" role="presentation">
											<button class="nav-link" id="email-report-tab" data-bs-toggle="tab" data-bs-target="#email-report" type="button" role="tab" aria-controls="email-report" aria-selected="false">Email Report</button>
										</li>
										<?php endif; ?>
									</ul>

									<!-- Tab Content -->
									<div class="tab-content" id="emailTabsContent">
										<!-- Account Tab -->
										<div class="tab-pane fade show active" id="account" role="tabpanel" aria-labelledby="account-tab">
											<div class="row general-settings">
											<div class="payment-box">
												<div class="pbox-wrap">
													<div class="pbox-lft">
														<h3><?php echo $lang['New account welcome email']; ?></h3> </div>
													<div class="pbox-rt">
														<a href="#" class="setup-btn">
															<?php echo $lang['Configure']; ?>
														</a>
													</div>
												</div>
												
												<div class="row">
													<div class="col-md-12">
														<div class="payment-fields" style="display: none;">
														<div class="payment-b-txt">
															<?php echo $lang['Shortcodes:']; ?>
																<ul>
																		<?php email_setting_echo_brand_shortcodes(); ?>
																		<li><?php echo $lang['User name']; ?>: {USER_NAME}</li>
																	<li><?php echo $lang['Login URL']; ?>: {DASHBOARD_URL}</li>
																	<li><?php echo $lang['User Email']; ?>: {USER_LOGIN_EMAIL}</li>
																	<li><?php echo $lang['User Password']; ?>: {USER_LOGIN_PASSWORD}</li>
																	<li><?php echo $lang['Signature']; ?>: {SIGNATURE}</li>
																</ul>
															</div>
															<form method="post" action="#" enctype="multipart/form-data">
																<textarea class="editor" name="createaccount">
																	<?php echo email_setting_editor_html($settings->create_account_email); ?>
																</textarea>
																<input type="hidden" name="createfrm" value="1" />
															</form>
														</div>
													</div>
												</div>
											</div>
											<!-- Box 2 -->
											<div class="payment-box">
												<div class="pbox-wrap">
													<div class="pbox-lft">
														<h3><?php echo $lang['Password reset request email']; ?></h3> </div>
													<div class="pbox-rt">
														<a href="#" class="setup-btn">
															<?php echo $lang['Configure']; ?>
														</a>
													</div>
												</div>
											
												<div class="row">
													<div class="col-md-12">
														<div class="payment-fields" style="display: none;">
															<div class="payment-b-txt">
																<?php echo $lang['Shortcodes:']; ?>
																<ul>
																		<?php email_setting_echo_brand_shortcodes(); ?>
																		<li><?php echo $lang['User name']; ?>: {USER_NAME}</li>
																	<li><?php echo $lang['Password Reset URL']; ?>: {RESET_URL}</li>
																	<li><?php echo $lang['Signature']; ?>: {SIGNATURE}</li>
																</ul>
															</div>
																<form method="post" action="#" enctype="multipart/form-data">
																<textarea class="editor" name="forgetaccount">
																	<?php echo email_setting_editor_html($settings->forget_email); ?>
																</textarea>
																<input type="hidden" name="forgetfrm" value="1" />
															</form>
														</div>
													</div>
												</div>
											</div>
											<!-- Box 2 End-->
											</div>
											<!-- End general-settings row -->
										</div>
										<!-- End Account Tab -->
										
										<!-- Projects Tab -->
										<div class="tab-pane fade" id="projects" role="tabpanel" aria-labelledby="projects-tab">
											<div class="row general-settings">
											<div class="payment-box">
												<div class="pbox-wrap">
													<div class="pbox-lft">
														<h3><?php echo $lang['Client notification – new project created']; ?></h3> </div>
													<div class="pbox-rt">
														<a href="#" class="setup-btn">
															<?php echo $lang['Configure']; ?>
														</a>
													</div>
												</div>
										
												<div class="row">
													<div class="col-md-12">
														<div class="payment-fields" style="display: none;">
																<div class="payment-b-txt">
																	<?php echo $lang['Shortcodes:']; ?>
																	<ul>
																		<?php email_setting_echo_brand_shortcodes(); ?>
																		<li><?php echo $lang['User name']; ?>: {USER_NAME}</li>
																		<li><?php echo $lang['Project Title']; ?>: {PROJECT_NAME}</li>
																		<li><?php echo $lang['Login URL']; ?>: {DASHBOARD_URL}</li>
																		<li><?php echo $lang['Signature']; ?>: {SIGNATURE}</li>
																	</ul>
																</div>
															<form method="post" action="#" enctype="multipart/form-data">
																<textarea class="editor" name="projectassign">
																	<?php echo email_setting_editor_html($settings->project_assign_email); ?>
																</textarea>
																<input type="hidden" name="proassfrm" value="1" />
															</form>
														</div>
													</div>
												</div>
											</div>
											<!-- Box 3 End-->
											<!-- Box 3.5 -->
											<div class="payment-box">
												<div class="pbox-wrap">
													<div class="pbox-lft">
														<h3><?php echo $lang['Staff notification – new project assigned']; ?></h3> </div>
													<div class="pbox-rt">
														<a href="#" class="setup-btn">
															<?php echo $lang['Configure']; ?>
														</a>
													</div>
												</div>
												<div class="row">
													<div class="col-md-12">
														<div class="payment-fields" style="display: none;">
															<div class="payment-b-txt">
																<?php echo $lang['Shortcodes:']; ?>
																<ul>
																		<?php email_setting_echo_brand_shortcodes(); ?>
																		<li><?php echo $lang['User name']; ?>: {USER_NAME}</li>
																	<li><?php echo $lang['Project Title']; ?>: {PROJECT_NAME}</li>
																	<li><?php echo $lang['Login URL']; ?>: {DASHBOARD_URL}</li>
																	<li><?php echo $lang['Signature']; ?>: {SIGNATURE}</li>
																</ul>
															</div>
															<form method="post" action="#" enctype="multipart/form-data">
																<textarea class="editor" name="projectassignstaff">
																	<?php echo email_setting_editor_html($settings->assign_staff_email); ?>
																</textarea>
																<input type="hidden" name="proassfrmstaff" value="1" />
															</form>
														</div>
													</div>
												</div>
											</div>
											<!-- Box 3.5 End-->
											<!-- Box 4 -->
											<div class="payment-box">
												<div class="pbox-wrap">
													<div class="pbox-lft">
														<h3><?php echo $lang['Project update notification']; ?></h3> </div>
													<div class="pbox-rt">
														<a href="#" class="setup-btn">
															<?php echo $lang['Configure']; ?>
														</a>
													</div>
												</div>
												<div class="row">
													<div class="col-md-12">
														<div class="payment-fields" style="display: none;">
															<div class="payment-b-txt">
																<?php echo $lang['Shortcodes:']; ?>
																<ul>
																		<?php email_setting_echo_brand_shortcodes(); ?>
																		<li><?php echo $lang['User name']; ?>: {USER_NAME}</li>
																	<li><?php echo $lang['Project Title']; ?>: {PROJECT_NAME}</li>
																	<li><?php echo $lang['Login URL']; ?>: {DASHBOARD_URL}</li>
																	<li><?php echo $lang['Signature']; ?>: {SIGNATURE}</li>
																</ul>
															</div>
															<form method="post" action="#" enctype="multipart/form-data">
																<textarea class="editor" name="projectupdate">
																	<?php echo email_setting_editor_html($settings->project_update_email); ?>
																</textarea>
																<input type="hidden" name="proupdatefrm" value="1" />
															</form>
														</div>
													</div>
												</div>
											</div>
											<!-- Box 4 End-->
											</div>
											<!-- End general-settings row -->
										</div>
										<!-- End Projects Tab -->
										
										<!-- Tasks Tab -->
										<div class="tab-pane fade" id="tasks" role="tabpanel" aria-labelledby="tasks-tab">
											<div class="row general-settings">
											<div class="payment-box">
												<div class="pbox-wrap">
													<div class="pbox-lft">
														<h3><?php echo $lang['New task assigned email']; ?></h3> </div>
													<div class="pbox-rt">
														<a href="#" class="setup-btn">
															<?php echo $lang['Configure']; ?>
														</a>
													</div>
												</div>
												
												<div class="row">
													<div class="col-md-12">
														<div class="payment-fields" style="display: none;">
															<div class="payment-b-txt">
																<?php echo $lang['Shortcodes:']; ?>
																<ul>
																		<?php email_setting_echo_brand_shortcodes(); ?>
																		<li><?php echo $lang['User name']; ?>: {USER_NAME}</li>
																	<li><?php echo $lang['Task Title']; ?>: {TASK_TITLE}</li>
																	<li><?php echo $lang['Task Description']; ?>: {TASK_DESCRIPTION}</li>
																	<li><?php echo $lang['Project Title']; ?>: {PROJECT_NAME}</li>
																	<li><?php echo $lang['Due Date']; ?>: {DUE_DATE}</li>
																	<li><?php echo $lang['Login URL']; ?>: {DASHBOARD_URL}</li>
																	<li><?php echo $lang['Signature']; ?>: {SIGNATURE}</li>
																</ul>
															</div>
															<form method="post" action="#" enctype="multipart/form-data">
																<textarea class="editor" name="taskcreate"><?php echo email_setting_editor_html($settings->task_create_email); ?></textarea>
																<input type="hidden" name="taskcreatefrm" value="1" />
															</form>
														</div>
													</div>
												</div>
											</div>
											<!-- Box 5 End-->
											<!-- Box 6 -->
											<div class="payment-box">
												<div class="pbox-wrap">
													<div class="pbox-lft">
														<h3><?php echo $lang['Task status updated email']; ?></h3> </div>
													<div class="pbox-rt">
														<a href="#" class="setup-btn">
															<?php echo $lang['Configure']; ?>
														</a>
													</div>
												</div>
												<div class="row">
													<div class="col-md-12">
														<div class="payment-fields" style="display: none;">
															<div class="payment-b-txt">
																<?php echo $lang['Shortcodes:']; ?>
																<ul>
																		<?php email_setting_echo_brand_shortcodes(); ?>
																		<li><?php echo $lang['User name']; ?>: {USER_NAME}</li>
																	<li><?php echo $lang['Task Title']; ?>: {TASK_TITLE}</li>
																	<li><?php echo $lang['Task Description']; ?>: {TASK_DESCRIPTION}</li>
																	<li><?php echo $lang['Project Title']; ?>: {PROJECT_NAME}</li>
																	<li><?php echo $lang['New Status']; ?>: {TASK_STATUS}</li>
																	<li><?php echo $lang['Due Date']; ?>: {DUE_DATE}</li>
																	<li><?php echo $lang['Login URL']; ?>: {DASHBOARD_URL}</li>
																	<li><?php echo $lang['Signature']; ?>: {SIGNATURE}</li>
																</ul>
															</div>
															<form method="post" action="#" enctype="multipart/form-data">
																<textarea class="editor" name="taskupdate"><?php echo email_setting_editor_html($settings->task_update_email); ?></textarea>
																<input type="hidden" name="taskupdatefrm" value="1" />
															</form>
														</div>
													</div>
												</div>
											</div>
											<!-- Box 6 End-->

											<div class="payment-box border-none">
												<div class="pbox-wrap">
													<div class="pbox-lft">
														<h3><?php echo isset($lang['Send email on Kanban drag and drop']) ? $lang['Send email on Kanban drag and drop'] : 'Send email on Kanban drag & drop'; ?></h3>
														<p class="pbox-help-text">
															<?php echo isset($lang['Kanban drag email help']) ? $lang['Kanban drag email help'] : 'Uses the “Task status updated” template above. When off, in-app notifications still work; no email is queued for board drag/drop. Edit-task and other status changes are not affected.'; ?>
														</p>
													</div>
													<div class="pbox-rt">
														<form method="post" action="#" style="display: inline-block;">
															<div class="checkbox-wrapper-6">
																<input class="tgl tgl-light" id="task_update_email_on_drag" name="task_update_email_on_drag" type="checkbox" <?php echo $taskUpdateEmailOnDrag ? 'checked' : ''; ?> onchange="this.form.submit()" />
																<label class="tgl-btn" for="task_update_email_on_drag"></label>
															</div>
															<input type="hidden" name="task_update_email_on_drag_toggle" value="1" />
														</form>
													</div>
												</div>
											</div>
											
											<!-- Task Reminders Section -->
											<div class="payment-box border-none">
												<div class="pbox-wrap">
													<div class="pbox-lft">
														<h3><?php echo isset($lang['Enable Task Reminders']) ? $lang['Enable Task Reminders'] : 'Enable task reminders'; ?></h3>
														<p class="pbox-help-text">
															<?php echo isset($lang['Automatically send task reminders for tasks with due dates']) ? $lang['Automatically send task reminders for tasks with due dates'] : 'Automatically send task reminders for tasks with due dates'; ?>
														</p>
													</div>
													<div class="pbox-rt">
														<form method="post" action="#" style="display: inline-block;">
															<div class="checkbox-wrapper-6">
																<input class="tgl tgl-light" id="task_reminders_enabled" name="task_reminders_enabled" type="checkbox" <?php echo (!empty($settings->task_reminders_enabled)) ? 'checked' : ''; ?> onchange="this.form.submit()" />
																<label class="tgl-btn" for="task_reminders_enabled"></label>
															</div>
															<input type="hidden" name="task_reminders_toggle" value="1" />
														</form>
													</div>
												</div>
												
												<!-- Reminder Settings Container -->
												<div id="task_reminder_settings" style="display: <?php echo (!empty($settings->task_reminders_enabled)) ? 'block' : 'none'; ?>;">
													<div class="row">
														<div class="col-md-12">
															<div class="payment-fields">
																<!-- Reminder Settings Form -->
																<form method="post" action="#" id="task_reminder_settings_form">
																	<!-- First Reminder -->
																	<div class="reminder-section license-details border mt-3">
																		<div class="d-flex justify-content-between align-items-center">
																			<h4 style="margin: 0;"><?php echo isset($lang['First Reminder']) ? $lang['First Reminder'] : 'First reminder'; ?></h4>
																			<div class="checkbox-wrapper-6">
																				<input class="tgl tgl-light" id="task_reminder_1_enabled" name="task_reminder_1_enabled" type="checkbox" <?php echo (!empty($settings->task_reminder_1_enabled)) ? 'checked' : ''; ?> />
																				<label class="tgl-btn" for="task_reminder_1_enabled"></label>
																			</div>
																		</div>
																		<div id="task_reminder_1_settings" style="display: <?php echo (!empty($settings->task_reminder_1_enabled)) ? 'block' : 'none'; ?>;">
																			<div class="row mb-3 mt-2">
																				<div class="col-md-6">
																					<label><?php echo isset($lang['Days']) ? $lang['Days'] : 'Days'; ?></label>
																					<input type="number" name="task_reminder_1_days" class="form-control" value="<?php echo isset($settings->task_reminder_1_days) ? $settings->task_reminder_1_days : 7; ?>" min="0" required />
																				</div>
																				<div class="col-md-6">
																					<label><?php echo isset($lang['Type']) ? $lang['Type'] : 'Type'; ?></label>
																					<select name="task_reminder_1_type" class="form-control">
																						<option value="before" <?php echo (isset($settings->task_reminder_1_type) && $settings->task_reminder_1_type == 'before') ? 'selected' : ''; ?>>
																							<?php echo isset($lang['Before due date']) ? $lang['Before due date'] : 'Before due date'; ?>
																						</option>
																						<option value="after" <?php echo (isset($settings->task_reminder_1_type) && $settings->task_reminder_1_type == 'after') ? 'selected' : ''; ?>>
																							<?php echo isset($lang['After due date']) ? $lang['After due date'] : 'After due date'; ?>
																						</option>
																					</select>
																				</div>
																			</div>
																		</div>
																	</div>
															
																	<!-- Second Reminder -->
																	<div class="reminder-section license-details border mt-3">
																		<div class="d-flex justify-content-between align-items-center">
																			<h4 style="margin: 0;"><?php echo isset($lang['Second Reminder']) ? $lang['Second Reminder'] : 'Second reminder'; ?></h4>
																			<div class="checkbox-wrapper-6">
																				<input class="tgl tgl-light" id="task_reminder_2_enabled" name="task_reminder_2_enabled" type="checkbox" <?php echo (!empty($settings->task_reminder_2_enabled)) ? 'checked' : ''; ?> />
																				<label class="tgl-btn" for="task_reminder_2_enabled"></label>
																			</div>
																		</div>
																		<div id="task_reminder_2_settings" style="display: <?php echo (!empty($settings->task_reminder_2_enabled)) ? 'block' : 'none'; ?>;">
																			<div class="row mb-3 mt-2">
																				<div class="col-md-6">
																					<label><?php echo isset($lang['Days']) ? $lang['Days'] : 'Days'; ?></label>
																					<input type="number" name="task_reminder_2_days" class="form-control" value="<?php echo isset($settings->task_reminder_2_days) ? $settings->task_reminder_2_days : 3; ?>" min="0" required />
																				</div>
																				<div class="col-md-6">
																					<label><?php echo isset($lang['Type']) ? $lang['Type'] : 'Type'; ?></label>
																					<select name="task_reminder_2_type" class="form-control">
																						<option value="before" <?php echo (isset($settings->task_reminder_2_type) && $settings->task_reminder_2_type == 'before') ? 'selected' : ''; ?>>
																							<?php echo isset($lang['Before due date']) ? $lang['Before due date'] : 'Before due date'; ?>
																						</option>
																						<option value="after" <?php echo (isset($settings->task_reminder_2_type) && $settings->task_reminder_2_type == 'after') ? 'selected' : ''; ?>>
																							<?php echo isset($lang['After due date']) ? $lang['After due date'] : 'After due date'; ?>
																						</option>
																					</select>
																				</div>
																			</div>
																		</div>
																	</div>
																	
																	<!-- Third Reminder -->
																	<div class="reminder-section license-details border mt-3">
																		<div class="d-flex justify-content-between align-items-center">
																			<h4 style="margin: 0;"><?php echo isset($lang['Third Reminder']) ? $lang['Third Reminder'] : 'Third reminder'; ?></h4>
																			<div class="checkbox-wrapper-6">
																				<input class="tgl tgl-light" id="task_reminder_3_enabled" name="task_reminder_3_enabled" type="checkbox" <?php echo (!empty($settings->task_reminder_3_enabled)) ? 'checked' : ''; ?> />
																				<label class="tgl-btn" for="task_reminder_3_enabled"></label>
																			</div>
																		</div>
																		<div id="task_reminder_3_settings" style="display: <?php echo (!empty($settings->task_reminder_3_enabled)) ? 'block' : 'none'; ?>;">
																			<div class="row mb-3 mt-2">
																				<div class="col-md-6">
																					<label><?php echo isset($lang['Days']) ? $lang['Days'] : 'Days'; ?></label>
																					<input type="number" name="task_reminder_3_days" class="form-control" value="<?php echo isset($settings->task_reminder_3_days) ? $settings->task_reminder_3_days : 14; ?>" min="0" required />
																				</div>
																				<div class="col-md-6">
																					<label><?php echo isset($lang['Type']) ? $lang['Type'] : 'Type'; ?></label>
																					<select name="task_reminder_3_type" class="form-control">
																						<option value="before" <?php echo (isset($settings->task_reminder_3_type) && $settings->task_reminder_3_type == 'before') ? 'selected' : ''; ?>>
																							<?php echo isset($lang['Before due date']) ? $lang['Before due date'] : 'Before due date'; ?>
																						</option>
																						<option value="after" <?php echo (isset($settings->task_reminder_3_type) && $settings->task_reminder_3_type == 'after') ? 'selected' : ''; ?>>
																							<?php echo isset($lang['After due date']) ? $lang['After due date'] : 'After due date'; ?>
																						</option>
																					</select>
																				</div>
																			</div>
																		</div>
																	</div>
																		
																	<!-- Send Reminders To Section (Shared for all reminders) -->
																	<div class="reminder-section license-details border mt-3">
																		<h4 style="margin-bottom: 20px;"><?php echo isset($lang['Send Reminders To']) ? $lang['Send Reminders To'] : 'Send reminders to'; ?></h4>
																		<div class="row">
																			<div class="col-md-4">
																				<div class="d-flex gap-15">
																					<div class="checkbox-wrapper-6">
																						<input class="tgl tgl-light" id="task_reminder_send_to_staff" name="task_reminder_send_to_staff" type="checkbox" <?php echo (!empty($settings->task_reminder_send_to_staff)) ? 'checked' : ''; ?> />
																						<label class="tgl-btn" for="task_reminder_send_to_staff"></label>
																					</div>
																					<div class="ms-3">
																						<label for="task_reminder_send_to_staff" style="margin: 0; font-weight: 500;">
																							<?php echo isset($lang['Send to Team']) ? $lang['Send to Team'] : 'Send to team/admins'; ?>
																						</label>
																						<p style="margin: 0; font-size: 12px; color: #999;">
																							<?php echo isset($lang['Send reminder emails to assigned team members']) ? $lang['Send reminder emails to assigned team members'] : 'Send reminder emails to assigned team members'; ?>
																						</p>
																					</div>
																				</div>
																			</div>
																			<div class="col-md-4">
																				<div class="d-flex gap-15">
																					<div class="checkbox-wrapper-6">
																						<input class="tgl tgl-light" id="task_reminder_send_to_client" name="task_reminder_send_to_client" type="checkbox" <?php echo (!empty($settings->task_reminder_send_to_client)) ? 'checked' : ''; ?> />
																						<label class="tgl-btn" for="task_reminder_send_to_client"></label>
																					</div>
																					<div class="ms-3">
																						<label for="task_reminder_send_to_client" style="margin: 0; font-weight: 500;">
																							<?php echo isset($lang['Send to Client']) ? $lang['Send to Client'] : 'Send to client'; ?>
																						</label>
																						<p style="margin: 0; font-size: 12px; color: #999;">
																							<?php echo isset($lang['Send reminder emails to the client']) ? $lang['Send reminder emails to the client'] : 'Send reminder emails to the client'; ?>
																						</p>
																					</div>
																				</div>
																			</div>
																			<div class="col-md-4">
																				<div class="d-flex gap-15">
																					<div class="checkbox-wrapper-6">
																						<input class="tgl tgl-light" id="task_reminder_send_to_creator" name="task_reminder_send_to_creator" type="checkbox" <?php echo (!empty($settings->task_reminder_send_to_creator)) ? 'checked' : ''; ?> />
																						<label class="tgl-btn" for="task_reminder_send_to_creator"></label>
																					</div>
																					<div class="ms-3">
																						<label for="task_reminder_send_to_creator" style="margin: 0; font-weight: 500;">
																							<?php echo isset($lang['Send to Creator']) ? $lang['Send to Creator'] : 'Send to creator'; ?>
																						</label>
																						<p style="margin: 0; font-size: 12px; color: #999;">
																							<?php echo isset($lang['Send reminder emails to the task creator']) ? $lang['Send reminder emails to the task creator'] : 'Send reminder emails to the task creator'; ?>
																						</p>
																					</div>
																				</div>
																			</div>
																		</div>
																	</div>
																	
																	<input type="hidden" name="task_reminder_settings" value="1" />
																</form>
																
																<!-- Email Templates Section -->
																<div style="margin-top: 30px;">
																	<h4><?php echo isset($lang['Email Templates']) ? $lang['Email Templates'] : 'Email templates'; ?></h4>
																	
																	<!-- First Reminder Template -->
																	<div class="payment-box m-0">
																		<div class="pbox-wrap">
																			<div class="pbox-lft">
																				<h3><?php echo isset($lang['First Reminder Email Template']) ? $lang['First Reminder Email Template'] : 'First reminder email template'; ?></h3>
																			</div>
																			<div class="pbox-rt">
																				<a href="#" class="setup-btn"><?php echo $lang['Configure']; ?></a>
																			</div>
																		</div>
																		<div class="row">
																			<div class="col-md-12">
																				<div class="payment-fields" style="display: none;">
																					<div class="payment-b-txt">
																						<?php echo isset($lang['Shortcodes:']) ? $lang['Shortcodes:'] : 'Shortcodes:'; ?>
																						<ul>
																		<?php email_setting_echo_brand_shortcodes(); ?>
																		<li><?php echo isset($lang['User name']) ? $lang['User name'] : 'User name'; ?>: {USER_NAME}</li>
																							<li><?php echo isset($lang['Task Title']) ? $lang['Task Title'] : 'Task Title'; ?>: {TASK_TITLE}</li>
																							<li><?php echo isset($lang['Task Description']) ? $lang['Task Description'] : 'Task Description'; ?>: {TASK_DESCRIPTION}</li>
																							<li><?php echo isset($lang['Project Title']) ? $lang['Project Title'] : 'Project Title'; ?>: {PROJECT_NAME}</li>
																							<li><?php echo isset($lang['Task Status']) ? $lang['Task Status'] : 'Task Status'; ?>: {TASK_STATUS}</li>
																							<li><?php echo isset($lang['Due Date']) ? $lang['Due Date'] : 'Due date'; ?>: {DUE_DATE}</li>
																							<li><?php echo isset($lang['Days overdue']) ? $lang['Days overdue'] : 'Days overdue'; ?>: {DAYS_OVERDUE}</li>
																							<li><?php echo isset($lang['Days until due']) ? $lang['Days until due'] : 'Days until due'; ?>: {DAYS_UNTIL_DUE}</li>
																							<li><?php echo isset($lang['Dashboard URL']) ? $lang['Dashboard URL'] : 'Dashboard URL'; ?>: {DASHBOARD_URL}</li>
																							<li><?php echo isset($lang['Signature']) ? $lang['Signature'] : 'Signature'; ?>: {SIGNATURE}</li>
																						</ul>
																					</div>
																					<form method="post" action="#" enctype="multipart/form-data">
																						<div class="form-group" style="margin-bottom: 15px;">
																							<label><?php echo isset($lang['Subject Line']) ? $lang['Subject Line'] : 'Subject Line'; ?></label>
																							<input type="text" name="task_reminder_1_subject" class="form-control" value="<?php echo isset($settings->task_reminder_1_subject) ? htmlspecialchars($settings->task_reminder_1_subject) : ''; ?>" placeholder="<?php echo isset($lang['Task Reminder: {TASK_TITLE}']) ? $lang['Task Reminder: {TASK_TITLE}'] : 'Task Reminder: {TASK_TITLE}'; ?>" />
																						</div>
																						<textarea class="editor" name="task_reminder_1_template"><?php echo email_setting_editor_html(isset($settings->task_reminder_1_template) ? $settings->task_reminder_1_template : ''); ?></textarea>
																						<input type="hidden" name="task_reminder_1_templatefrm" value="1" />
																					</form>
																				</div>
																			</div>
																		</div>
																	</div>
																	
																	<!-- Second Reminder Template -->
																	<div class="payment-box m-0">
																		<div class="pbox-wrap">
																			<div class="pbox-lft">
																				<h3><?php echo isset($lang['Second Reminder Email Template']) ? $lang['Second Reminder Email Template'] : 'Second reminder email template'; ?></h3>
																			</div>
																			<div class="pbox-rt">
																				<a href="#" class="setup-btn"><?php echo $lang['Configure']; ?></a>
																			</div>
																		</div>
																		<div class="row">
																			<div class="col-md-12">
																				<div class="payment-fields" style="display: none;">
																					<div class="payment-b-txt">
																						<?php echo isset($lang['Shortcodes:']) ? $lang['Shortcodes:'] : 'Shortcodes:'; ?>
																						<ul>
																		<?php email_setting_echo_brand_shortcodes(); ?>
																		<li><?php echo isset($lang['User name']) ? $lang['User name'] : 'User name'; ?>: {USER_NAME}</li>
																							<li><?php echo isset($lang['Task Title']) ? $lang['Task Title'] : 'Task Title'; ?>: {TASK_TITLE}</li>
																							<li><?php echo isset($lang['Task Description']) ? $lang['Task Description'] : 'Task Description'; ?>: {TASK_DESCRIPTION}</li>
																							<li><?php echo isset($lang['Project Title']) ? $lang['Project Title'] : 'Project Title'; ?>: {PROJECT_NAME}</li>
																							<li><?php echo isset($lang['Task Status']) ? $lang['Task Status'] : 'Task Status'; ?>: {TASK_STATUS}</li>
																							<li><?php echo isset($lang['Due Date']) ? $lang['Due Date'] : 'Due date'; ?>: {DUE_DATE}</li>
																							<li><?php echo isset($lang['Days overdue']) ? $lang['Days overdue'] : 'Days overdue'; ?>: {DAYS_OVERDUE}</li>
																							<li><?php echo isset($lang['Days until due']) ? $lang['Days until due'] : 'Days until due'; ?>: {DAYS_UNTIL_DUE}</li>
																							<li><?php echo isset($lang['Dashboard URL']) ? $lang['Dashboard URL'] : 'Dashboard URL'; ?>: {DASHBOARD_URL}</li>
																							<li><?php echo isset($lang['Signature']) ? $lang['Signature'] : 'Signature'; ?>: {SIGNATURE}</li>
																						</ul>
																					</div>
																					<form method="post" action="#" enctype="multipart/form-data">
																						<div class="form-group" style="margin-bottom: 15px;">
																							<label><?php echo isset($lang['Subject Line']) ? $lang['Subject Line'] : 'Subject Line'; ?></label>
																							<input type="text" name="task_reminder_2_subject" class="form-control" value="<?php echo isset($settings->task_reminder_2_subject) ? htmlspecialchars($settings->task_reminder_2_subject) : ''; ?>" placeholder="<?php echo isset($lang['Task Reminder: {TASK_TITLE}']) ? $lang['Task Reminder: {TASK_TITLE}'] : 'Task Reminder: {TASK_TITLE}'; ?>" />
																						</div>
																						<textarea class="editor" name="task_reminder_2_template"><?php echo email_setting_editor_html(isset($settings->task_reminder_2_template) ? $settings->task_reminder_2_template : ''); ?></textarea>
																						<input type="hidden" name="task_reminder_2_templatefrm" value="1" />
																					</form>
																				</div>
																			</div>
																		</div>
																	</div>
																	
																	<!-- Third Reminder Template -->
																	<div class="payment-box m-0">
																		<div class="pbox-wrap">
																			<div class="pbox-lft">
																				<h3><?php echo isset($lang['Third Reminder Email Template']) ? $lang['Third Reminder Email Template'] : 'Third reminder email template'; ?></h3>
																			</div>
																			<div class="pbox-rt">
																				<a href="#" class="setup-btn"><?php echo $lang['Configure']; ?></a>
																			</div>
																		</div>
																		<div class="row">
																			<div class="col-md-12">
																				<div class="payment-fields" style="display: none;">
																					<div class="payment-b-txt">
																						<?php echo isset($lang['Shortcodes:']) ? $lang['Shortcodes:'] : 'Shortcodes:'; ?>
																						<ul>
																		<?php email_setting_echo_brand_shortcodes(); ?>
																		<li><?php echo isset($lang['User name']) ? $lang['User name'] : 'User name'; ?>: {USER_NAME}</li>
																							<li><?php echo isset($lang['Task Title']) ? $lang['Task Title'] : 'Task Title'; ?>: {TASK_TITLE}</li>
																							<li><?php echo isset($lang['Task Description']) ? $lang['Task Description'] : 'Task Description'; ?>: {TASK_DESCRIPTION}</li>
																							<li><?php echo isset($lang['Project Title']) ? $lang['Project Title'] : 'Project Title'; ?>: {PROJECT_NAME}</li>
																							<li><?php echo isset($lang['Task Status']) ? $lang['Task Status'] : 'Task Status'; ?>: {TASK_STATUS}</li>
																							<li><?php echo isset($lang['Due Date']) ? $lang['Due Date'] : 'Due date'; ?>: {DUE_DATE}</li>
																							<li><?php echo isset($lang['Days overdue']) ? $lang['Days overdue'] : 'Days overdue'; ?>: {DAYS_OVERDUE}</li>
																							<li><?php echo isset($lang['Days until due']) ? $lang['Days until due'] : 'Days until due'; ?>: {DAYS_UNTIL_DUE}</li>
																							<li><?php echo isset($lang['Dashboard URL']) ? $lang['Dashboard URL'] : 'Dashboard URL'; ?>: {DASHBOARD_URL}</li>
																							<li><?php echo isset($lang['Signature']) ? $lang['Signature'] : 'Signature'; ?>: {SIGNATURE}</li>
																						</ul>
																					</div>
																					<form method="post" action="#" enctype="multipart/form-data">
																						<div class="form-group" style="margin-bottom: 15px;">
																							<label><?php echo isset($lang['Subject Line']) ? $lang['Subject Line'] : 'Subject Line'; ?></label>
																							<input type="text" name="task_reminder_3_subject" class="form-control" value="<?php echo isset($settings->task_reminder_3_subject) ? htmlspecialchars($settings->task_reminder_3_subject) : ''; ?>" placeholder="<?php echo isset($lang['Task Reminder: {TASK_TITLE}']) ? $lang['Task Reminder: {TASK_TITLE}'] : 'Task Reminder: {TASK_TITLE}'; ?>" />
																						</div>
																						<textarea class="editor" name="task_reminder_3_template"><?php echo email_setting_editor_html(isset($settings->task_reminder_3_template) ? $settings->task_reminder_3_template : ''); ?></textarea>
																						<input type="hidden" name="task_reminder_3_templatefrm" value="1" />
																					</form>
																				</div>
																			</div>
																		</div>
																	</div>
																</div>
															</div>
														</div>
													</div>
												</div>
												<!-- End Task Reminders Section -->
											</div>
											</div>
											<!-- End general-settings row -->
										</div>
										<!-- End Tasks Tab -->
										
										<?php if ($invoiceModuleEnabled): ?>
										<!-- Invoices Tab -->
										<div class="tab-pane fade" id="invoices" role="tabpanel" aria-labelledby="invoices-tab">
											<div class="row general-settings">
											<div class="payment-box">
												<div class="pbox-wrap">
													<div class="pbox-lft">
														<h3><?php echo $lang['New invoice issued notification']; ?></h3>
													</div>
													<div class="pbox-rt">
														<a href="#" class="setup-btn"><?php echo $lang['Configure']; ?></a>
													</div>
												</div>
												<div class="row">
													<div class="col-md-12">
														<div class="payment-fields" style="display: none;">
															<div class="payment-b-txt">
																Shortcodes:
																<ul>
																		<?php email_setting_echo_brand_shortcodes(); ?>
																		<li>User name: {USER_NAME}</li>
																	<li>Invoice title: {INVOICE_TITLE}</li>
																	<li>Amount: {INVOICE_AMOUNT}</li>
																	<li>Due Date: {INVOICE_DUE_DATE}</li>
																	<li>Project name: {PROJECT_NAME}</li>
																	<li>Status: {INVOICE_STATUS}</li>
																	<li>Login URL: {DASHBOARD_URL}</li>
																	<li>Signature: {SIGNATURE}</li>
																</ul>
															</div>
															<form method="post" action="#" enctype="multipart/form-data">
																<textarea class="editor" name="invoicecreate"><?php echo email_setting_editor_html($settings->invoice_create_email); ?></textarea>
																<input type="hidden" name="invoicecreatefrm" value="1" />
															</form>
														</div>
													</div>
												</div>
											</div>
											<!-- Payment Received Email Template -->
											<div class="payment-box">
												<div class="pbox-wrap">
													<div class="pbox-lft">
														<h3><?php echo $lang['Payment confirmation email']; ?></h3>
													</div>
													<div class="pbox-rt">
														<a href="#" class="setup-btn"><?php echo $lang['Configure']; ?></a>
													</div>
												</div>
												<div class="row">
													<div class="col-md-12">
														<div class="payment-fields" style="display: none;">
															<div class="payment-b-txt">
																Shortcodes:
																<ul>
																		<?php email_setting_echo_brand_shortcodes(); ?>
																		<li>User name: {USER_NAME}</li>
																	<li>Invoice title: {INVOICE_TITLE}</li>
																	<li>Amount: {INVOICE_AMOUNT}</li>
																		<li>Currency Symbol: {INVOICE_CURRENCY_SYMBOL}</li>
																	<li>Due Date: {INVOICE_DUE_DATE}</li>
																	<li>Project name: {PROJECT_NAME}</li>
																	<li>Status: {INVOICE_STATUS}</li>
																	<li>Login URL: {DASHBOARD_URL}</li>
																	<li>Signature: {SIGNATURE}</li>
																</ul>
															</div>
															<form method="post" action="#" enctype="multipart/form-data">
																<textarea class="editor" name="invoicepaid"><?php echo email_setting_editor_html($settings->invoice_paid_email); ?></textarea>
																<input type="hidden" name="invoicepaidfrm" value="1" />
															</form>
														</div>
													</div>
												</div>
											</div>
												<!-- Payment Confirmation Email (Admin) Template -->
												<div class="payment-box">
													<div class="pbox-wrap">
														<div class="pbox-lft">
															<h3><?php echo isset($lang['Payment confirmation email (admin)']) ? $lang['Payment confirmation email (admin)'] : 'Payment confirmation email (admin)'; ?></h3>
														</div>
														<div class="pbox-rt">
															<a href="#" class="setup-btn"><?php echo $lang['Configure']; ?></a>
														</div>
													</div>
													<div class="row">
														<div class="col-md-12">
															<div class="payment-fields" style="display: none;">
																<div class="payment-b-txt">
																	Shortcodes:
																	<ul>
																		<?php email_setting_echo_brand_shortcodes(); ?>
																		<li>User name: {USER_NAME}</li>
																		<li>Invoice title: {INVOICE_TITLE}</li>
																		<li>Amount: {INVOICE_AMOUNT}</li>
																		<li>Currency Symbol: {INVOICE_CURRENCY_SYMBOL}</li>
																		<li>Due Date: {INVOICE_DUE_DATE}</li>
																		<li>Project name: {PROJECT_NAME}</li>
																		<li>Status: {INVOICE_STATUS}</li>
																		<li>Client name: {CLIENT_NAME}</li>
																		<li>Client email: {CLIENT_EMAIL}</li>
																		<li>Login URL: {DASHBOARD_URL}</li>
																		<li>Signature: {SIGNATURE}</li>
																	</ul>
																</div>
																<form method="post" action="#" enctype="multipart/form-data">
																	<textarea class="editor" name="invoicepaidadmin"><?php echo email_setting_editor_html(isset($settings->invoice_paid_email_admin) ? $settings->invoice_paid_email_admin : ''); ?></textarea>
																	<input type="hidden" name="invoicepaidadminfrm" value="1" />
																</form>
															</div>
														</div>
													</div>
												</div>
												<!-- Payment Reminders Section -->
												<div class="payment-box border-none">
													<div class="pbox-wrap">
														<div class="pbox-lft">
														<h3><?php echo isset($lang['Enable Payment Reminders']) ? $lang['Enable Payment Reminders'] : 'Enable payment reminders'; ?></h3>
														<p class="pbox-help-text">
															<?php echo isset($lang['Automatically send payment reminders for overdue invoices']) ? $lang['Automatically send payment reminders for overdue invoices'] : 'Automatically send payment reminders for overdue invoices'; ?>
														</p>
													</div>
													<div class="pbox-rt">
														<form method="post" action="#" style="display: inline-block;">
															<div class="checkbox-wrapper-6">
																<input class="tgl tgl-light" id="payment_reminders_enabled" name="payment_reminders_enabled" type="checkbox" <?php echo (!empty($settings->payment_reminders_enabled)) ? 'checked' : ''; ?> onchange="this.form.submit()" />
																<label class="tgl-btn" for="payment_reminders_enabled"></label>
															</div>
															<input type="hidden" name="payment_reminders_toggle" value="1" />
														</form>
													</div>
												</div>
												
												<!-- Reminder Settings Container -->
												<div id="payment_reminder_settings" style="display: <?php echo (!empty($settings->payment_reminders_enabled)) ? 'block' : 'none'; ?>;">
													<div class="row">
														<div class="col-md-12">
															<div class="payment-fields">
																<!-- Reminder Settings Form -->
																<form method="post" action="#" id="payment_reminder_settings_form">
																	<!-- First Reminder -->
																	<div class="reminder-section license-details border mt-3">
																		<div class="d-flex justify-content-between align-items-center">
																			<h4 style="margin: 0;"><?php echo isset($lang['First Reminder']) ? $lang['First Reminder'] : 'First reminder'; ?></h4>
																			<div class="checkbox-wrapper-6">
																				<input class="tgl tgl-light" id="payment_reminder_1_enabled" name="payment_reminder_1_enabled" type="checkbox" <?php echo (!empty($settings->payment_reminder_1_enabled)) ? 'checked' : ''; ?> />
																				<label class="tgl-btn" for="payment_reminder_1_enabled"></label>
																			</div>
																		</div>
																		<div id="reminder_1_settings" style="display: <?php echo (!empty($settings->payment_reminder_1_enabled)) ? 'block' : 'none'; ?>;">
																			<div class="row mb-3 mt-2">
																				<div class="col-md-6">
																					<label><?php echo isset($lang['Days']) ? $lang['Days'] : 'Days'; ?></label>
																					<input type="number" name="payment_reminder_1_days" class="form-control" value="<?php echo isset($settings->payment_reminder_1_days) ? $settings->payment_reminder_1_days : 7; ?>" min="1" required />
																				</div>
																				<div class="col-md-6">
																					<label><?php echo isset($lang['Type']) ? $lang['Type'] : 'Type'; ?></label>
																					<select name="payment_reminder_1_type" class="form-control">
																						<option value="before" <?php echo (isset($settings->payment_reminder_1_type) && $settings->payment_reminder_1_type == 'before') ? 'selected' : ''; ?>>
																							<?php echo isset($lang['Before due date']) ? $lang['Before due date'] : 'Before due date'; ?>
																						</option>
																						<option value="after" <?php echo (isset($settings->payment_reminder_1_type) && $settings->payment_reminder_1_type == 'after') ? 'selected' : ''; ?>>
																							<?php echo isset($lang['After due date']) ? $lang['After due date'] : 'After due date'; ?>
																						</option>
																					</select>
																				</div>
																			</div>
																		</div>
																		</div>
															
																	
																	<!-- Second Reminder -->
																	<div class="reminder-section license-details border mt-3">
																		<div class="d-flex justify-content-between align-items-center">
																			<h4 style="margin: 0;"><?php echo isset($lang['Second Reminder']) ? $lang['Second Reminder'] : 'Second reminder'; ?></h4>
																			<div class="checkbox-wrapper-6">
																				<input class="tgl tgl-light" id="payment_reminder_2_enabled" name="payment_reminder_2_enabled" type="checkbox" <?php echo (!empty($settings->payment_reminder_2_enabled)) ? 'checked' : ''; ?> />
																				<label class="tgl-btn" for="payment_reminder_2_enabled"></label>
																			</div>
																		</div>
																		<div id="reminder_2_settings" style="display: <?php echo (!empty($settings->payment_reminder_2_enabled)) ? 'block' : 'none'; ?>;">
																			<div class="row mb-3 mt-2">
																				<div class="col-md-6">
																					<label><?php echo isset($lang['Days']) ? $lang['Days'] : 'Days'; ?></label>
																					<input type="number" name="payment_reminder_2_days" class="form-control" value="<?php echo isset($settings->payment_reminder_2_days) ? $settings->payment_reminder_2_days : 3; ?>" min="1" required />
																				</div>
																				<div class="col-md-6">
																					<label><?php echo isset($lang['Type']) ? $lang['Type'] : 'Type'; ?></label>
																					<select name="payment_reminder_2_type" class="form-control">
																						<option value="before" <?php echo (isset($settings->payment_reminder_2_type) && $settings->payment_reminder_2_type == 'before') ? 'selected' : ''; ?>>
																							<?php echo isset($lang['Before due date']) ? $lang['Before due date'] : 'Before due date'; ?>
																						</option>
																						<option value="after" <?php echo (isset($settings->payment_reminder_2_type) && $settings->payment_reminder_2_type == 'after') ? 'selected' : ''; ?>>
																							<?php echo isset($lang['After due date']) ? $lang['After due date'] : 'After due date'; ?>
																						</option>
																					</select>
																				</div>
																			</div>
																		</div>
																		</div>
																	</div>
																	
																	<!-- Third Reminder -->
																	<div class="reminder-section license-details border mt-3">
																		<div class="d-flex justify-content-between align-items-center">
																			<h4 style="margin: 0;"><?php echo isset($lang['Third Reminder']) ? $lang['Third Reminder'] : 'Third reminder'; ?></h4>
																			<div class="checkbox-wrapper-6">
																				<input class="tgl tgl-light" id="payment_reminder_3_enabled" name="payment_reminder_3_enabled" type="checkbox" <?php echo (!empty($settings->payment_reminder_3_enabled)) ? 'checked' : ''; ?> />
																				<label class="tgl-btn" for="payment_reminder_3_enabled"></label>
																			</div>
																		</div>
																		<div id="reminder_3_settings" style="display: <?php echo (!empty($settings->payment_reminder_3_enabled)) ? 'block' : 'none'; ?>;">
																			<div class="row mb-3 mt-2">
																				<div class="col-md-6">
																					<label><?php echo isset($lang['Days']) ? $lang['Days'] : 'Days'; ?></label>
																					<input type="number" name="payment_reminder_3_days" class="form-control" value="<?php echo isset($settings->payment_reminder_3_days) ? $settings->payment_reminder_3_days : 14; ?>" min="1" required />
																				</div>
																				<div class="col-md-6">
																					<label><?php echo isset($lang['Type']) ? $lang['Type'] : 'Type'; ?></label>
																					<select name="payment_reminder_3_type" class="form-control">
																						<option value="before" <?php echo (isset($settings->payment_reminder_3_type) && $settings->payment_reminder_3_type == 'before') ? 'selected' : ''; ?>>
																							<?php echo isset($lang['Before due date']) ? $lang['Before due date'] : 'Before due date'; ?>
																						</option>
																						<option value="after" <?php echo (isset($settings->payment_reminder_3_type) && $settings->payment_reminder_3_type == 'after') ? 'selected' : ''; ?>>
																							<?php echo isset($lang['After due date']) ? $lang['After due date'] : 'After due date'; ?>
																						</option>
																					</select>
																				</div>
																			</div>
																		</div>
																	</div>
																			
																	<!-- Send Reminders To Section (Shared for all reminders) -->
																	<div class="reminder-section license-details border mt-3">
																		<h4 style="margin-bottom: 20px;"><?php echo isset($lang['Send Reminders To']) ? $lang['Send Reminders To'] : 'Send reminders to'; ?></h4>
																		<div class="row">
																			<div class="col-md-6">
																				<div class="d-flex align-items-center gap-15">
																					<div class="checkbox-wrapper-6">
																						<input class="tgl tgl-light" id="payment_reminder_send_to_client" name="payment_reminder_send_to_client" type="checkbox" <?php echo (!empty($settings->payment_reminder_send_to_client)) ? 'checked' : ''; ?> />
																						<label class="tgl-btn" for="payment_reminder_send_to_client"></label>
																					</div>
																					<div class="ms-3">
																						<label for="payment_reminder_send_to_client" style="margin: 0; font-weight: 500;">
																							<?php echo isset($lang['Send to Client']) ? $lang['Send to Client'] : 'Send to client'; ?>
																						</label>
																						<p style="margin: 0; font-size: 12px; color: #999;">
																							<?php echo isset($lang['Send reminder emails to the client']) ? $lang['Send reminder emails to the client'] : 'Send reminder emails to the client'; ?>
																						</p>
																					</div>
																				</div>
																			</div>
																			<div class="col-md-6">
																				<div class="d-flex align-items-center gap-15">
																					<div class="checkbox-wrapper-6">
																						<input class="tgl tgl-light" id="payment_reminder_send_to_staff" name="payment_reminder_send_to_staff" type="checkbox" <?php echo (!empty($settings->payment_reminder_send_to_staff)) ? 'checked' : ''; ?> />
																						<label class="tgl-btn" for="payment_reminder_send_to_staff"></label>
																					</div>
																					<div class="ms-3">
																						<label for="payment_reminder_send_to_staff" style="margin: 0; font-weight: 500;">
																							<?php echo isset($lang['Send to Team']) ? $lang['Send to Team'] : 'Send to team'; ?>
																						</label>
																						<p style="margin: 0; font-size: 12px; color: #999;">
																							<?php echo isset($lang['Send notification to team members']) ? $lang['Send notification to team members'] : 'Send notification to team members'; ?>
																						</p>
																					</div>
																				</div>
																			</div>
																		</div>
																		<div class="row mt-3">
																			<div class="col-md-12">
																				<div class="d-flex align-items-center gap-15">
																					<div class="checkbox-wrapper-6">
																						<input class="tgl tgl-light" id="payment_reminder_notify_admin_in_app" name="payment_reminder_notify_admin_in_app" type="checkbox" value="1" <?php echo (!empty($settings->payment_reminder_notify_admin_in_app)) ? 'checked' : ''; ?> />
																						<label class="tgl-btn" for="payment_reminder_notify_admin_in_app"></label>
																					</div>
																					<div class="ms-3">
																						<label for="payment_reminder_notify_admin_in_app" style="margin: 0; font-weight: 500;">
																							<?php echo htmlspecialchars($lang['Notify admin in app'] ?? 'Notify admin in app'); ?>
																						</label>
																						<p style="margin: 0; font-size: 12px; color: #999;">
																							<?php echo htmlspecialchars($lang['Notify admin in app help'] ?? 'Show a bell notification when a client payment reminder email is sent (up to 3 per invoice).'); ?>
																						</p>
																					</div>
																				</div>
																			</div>
																		</div>
																	</div>
																	
																	<input type="hidden" name="payment_reminder_settings" value="1" />
																</form>
																
																<!-- Email Templates Section -->
																<div style="margin-top: 30px;">
																	<h4><?php echo isset($lang['Email Templates']) ? $lang['Email Templates'] : 'Email templates'; ?></h4>
																	
																	<!-- First Reminder Template -->
																	<div class="payment-box m-0">
																		<div class="pbox-wrap">
																			<div class="pbox-lft">
																				<h3><?php echo isset($lang['First Reminder Email Template']) ? $lang['First Reminder Email Template'] : 'First reminder email template'; ?></h3>
																			</div>
																			<div class="pbox-rt">
																				<a href="#" class="setup-btn"><?php echo $lang['Configure']; ?></a>
																			</div>
																		</div>
																		<div class="row">
																			<div class="col-md-12">
																				<div class="payment-fields" style="display: none;">
																					<div class="payment-b-txt">
																						<?php echo isset($lang['Shortcodes:']) ? $lang['Shortcodes:'] : 'Shortcodes:'; ?>
																						<ul>
																		<?php email_setting_echo_brand_shortcodes(); ?>
																		<li><?php echo isset($lang['Client name']) ? $lang['Client name'] : 'Client name'; ?>: {CLIENT_NAME}</li>
																							<li><?php echo isset($lang['Invoice number']) ? $lang['Invoice number'] : 'Invoice number'; ?>: {INVOICE_NUMBER}</li>
																							<li><?php echo isset($lang['Total amount']) ? $lang['Total amount'] : 'Total amount'; ?>: {TOTAL_AMOUNT}</li>
																							<li><?php echo isset($lang['Due date']) ? $lang['Due date'] : 'Due date'; ?>: {DUE_DATE}</li>
																							<li><?php echo isset($lang['Days overdue']) ? $lang['Days overdue'] : 'Days overdue'; ?>: {DAYS_OVERDUE}</li>
																							<li><?php echo isset($lang['Days until due']) ? $lang['Days until due'] : 'Days until due'; ?>: {DAYS_UNTIL_DUE}</li>
																							<li><?php echo isset($lang['Workspace name']) ? $lang['Workspace name'] : 'Workspace name'; ?>: {WORKSPACE_NAME}</li>
																							<li><?php echo isset($lang['Dashboard URL']) ? $lang['Dashboard URL'] : 'Dashboard URL'; ?>: {DASHBOARD_URL}</li>
																							<li><?php echo isset($lang['Signature']) ? $lang['Signature'] : 'Signature'; ?>: {SIGNATURE}</li>
																						</ul>
																					</div>
																					<form method="post" action="#" enctype="multipart/form-data">
																						<div class="form-group" style="margin-bottom: 15px;">
																							<label><?php echo isset($lang['Subject Line']) ? $lang['Subject Line'] : 'Subject Line'; ?></label>
																							<input type="text" name="payment_reminder_1_subject" class="form-control" value="<?php echo isset($settings->payment_reminder_1_subject) ? htmlspecialchars($settings->payment_reminder_1_subject) : ''; ?>" placeholder="<?php echo isset($lang['Payment Reminder: Invoice #{INVOICE_NUMBER}']) ? $lang['Payment Reminder: Invoice #{INVOICE_NUMBER}'] : 'Payment Reminder: Invoice #{INVOICE_NUMBER}'; ?>" />
																						</div>
																						<textarea class="editor" name="payment_reminder_1_template"><?php echo email_setting_editor_html(isset($settings->payment_reminder_1_template) ? $settings->payment_reminder_1_template : ''); ?></textarea>
																						<input type="hidden" name="payment_reminder_1_templatefrm" value="1" />
																					</form>
																				</div>
																			</div>
																		</div>
																	</div>
																	
																	<!-- Second Reminder Template -->
																	<div class="payment-box m-0">
																		<div class="pbox-wrap">
																			<div class="pbox-lft">
																				<h3><?php echo isset($lang['Second Reminder Email Template']) ? $lang['Second Reminder Email Template'] : 'Second reminder email template'; ?></h3>
																			</div>
																			<div class="pbox-rt">
																				<a href="#" class="setup-btn"><?php echo $lang['Configure']; ?></a>
																			</div>
																		</div>
																		<div class="row">
																			<div class="col-md-12">
																				<div class="payment-fields" style="display: none;">
																					<div class="payment-b-txt">
																						<?php echo isset($lang['Shortcodes:']) ? $lang['Shortcodes:'] : 'Shortcodes:'; ?>
																						<ul>
																		<?php email_setting_echo_brand_shortcodes(); ?>
																		<li><?php echo isset($lang['Client name']) ? $lang['Client name'] : 'Client name'; ?>: {CLIENT_NAME}</li>
																							<li><?php echo isset($lang['Invoice number']) ? $lang['Invoice number'] : 'Invoice number'; ?>: {INVOICE_NUMBER}</li>
																							<li><?php echo isset($lang['Total amount']) ? $lang['Total amount'] : 'Total amount'; ?>: {TOTAL_AMOUNT}</li>
																							<li><?php echo isset($lang['Due date']) ? $lang['Due date'] : 'Due date'; ?>: {DUE_DATE}</li>
																							<li><?php echo isset($lang['Days overdue']) ? $lang['Days overdue'] : 'Days overdue'; ?>: {DAYS_OVERDUE}</li>
																							<li><?php echo isset($lang['Days until due']) ? $lang['Days until due'] : 'Days until due'; ?>: {DAYS_UNTIL_DUE}</li>
																							<li><?php echo isset($lang['Workspace name']) ? $lang['Workspace name'] : 'Workspace name'; ?>: {WORKSPACE_NAME}</li>
																							<li><?php echo isset($lang['Dashboard URL']) ? $lang['Dashboard URL'] : 'Dashboard URL'; ?>: {DASHBOARD_URL}</li>
																							<li><?php echo isset($lang['Signature']) ? $lang['Signature'] : 'Signature'; ?>: {SIGNATURE}</li>
																						</ul>
																					</div>
																					<form method="post" action="#" enctype="multipart/form-data">
																						<div class="form-group" style="margin-bottom: 15px;">
																							<label><?php echo isset($lang['Subject Line']) ? $lang['Subject Line'] : 'Subject Line'; ?></label>
																							<input type="text" name="payment_reminder_2_subject" class="form-control" value="<?php echo isset($settings->payment_reminder_2_subject) ? htmlspecialchars($settings->payment_reminder_2_subject) : ''; ?>" placeholder="<?php echo isset($lang['Overdue Payment Notice: Invoice #{INVOICE_NUMBER}']) ? $lang['Overdue Payment Notice: Invoice #{INVOICE_NUMBER}'] : 'Overdue Payment Notice: Invoice #{INVOICE_NUMBER}'; ?>" />
																						</div>
																						<textarea class="editor" name="payment_reminder_2_template"><?php echo email_setting_editor_html(isset($settings->payment_reminder_2_template) ? $settings->payment_reminder_2_template : ''); ?></textarea>
																						<input type="hidden" name="payment_reminder_2_templatefrm" value="1" />
																					</form>
																				</div>
																			</div>
																		</div>
																	</div>
																	
																	<!-- Third Reminder Template -->
																	<div class="payment-box m-0">
																		<div class="pbox-wrap">
																			<div class="pbox-lft">
																				<h3><?php echo isset($lang['Third Reminder Email Template']) ? $lang['Third Reminder Email Template'] : 'Third reminder email template'; ?></h3>
																			</div>
																			<div class="pbox-rt">
																				<a href="#" class="setup-btn"><?php echo $lang['Configure']; ?></a>
																			</div>
																		</div>
																		<div class="row">
																			<div class="col-md-12">
																				<div class="payment-fields" style="display: none;">
																					<div class="payment-b-txt">
																						<?php echo isset($lang['Shortcodes:']) ? $lang['Shortcodes:'] : 'Shortcodes:'; ?>
																						<ul>
																		<?php email_setting_echo_brand_shortcodes(); ?>
																		<li><?php echo isset($lang['Client name']) ? $lang['Client name'] : 'Client name'; ?>: {CLIENT_NAME}</li>
																							<li><?php echo isset($lang['Invoice number']) ? $lang['Invoice number'] : 'Invoice number'; ?>: {INVOICE_NUMBER}</li>
																							<li><?php echo isset($lang['Total amount']) ? $lang['Total amount'] : 'Total amount'; ?>: {TOTAL_AMOUNT}</li>
																							<li><?php echo isset($lang['Due date']) ? $lang['Due date'] : 'Due date'; ?>: {DUE_DATE}</li>
																							<li><?php echo isset($lang['Days overdue']) ? $lang['Days overdue'] : 'Days overdue'; ?>: {DAYS_OVERDUE}</li>
																							<li><?php echo isset($lang['Days until due']) ? $lang['Days until due'] : 'Days until due'; ?>: {DAYS_UNTIL_DUE}</li>
																							<li><?php echo isset($lang['Workspace name']) ? $lang['Workspace name'] : 'Workspace name'; ?>: {WORKSPACE_NAME}</li>
																							<li><?php echo isset($lang['Dashboard URL']) ? $lang['Dashboard URL'] : 'Dashboard URL'; ?>: {DASHBOARD_URL}</li>
																							<li><?php echo isset($lang['Signature']) ? $lang['Signature'] : 'Signature'; ?>: {SIGNATURE}</li>
																						</ul>
																					</div>
																					<form method="post" action="#" enctype="multipart/form-data">
																						<div class="form-group" style="margin-bottom: 15px;">
																							<label><?php echo isset($lang['Subject Line']) ? $lang['Subject Line'] : 'Subject Line'; ?></label>
																							<input type="text" name="payment_reminder_3_subject" class="form-control" value="<?php echo isset($settings->payment_reminder_3_subject) ? htmlspecialchars($settings->payment_reminder_3_subject) : ''; ?>" placeholder="<?php echo isset($lang['Final Notice: Invoice #{INVOICE_NUMBER}']) ? $lang['Final Notice: Invoice #{INVOICE_NUMBER}'] : 'Final Notice: Invoice #{INVOICE_NUMBER}'; ?>" />
																						</div>
																						<textarea class="editor" name="payment_reminder_3_template"><?php echo email_setting_editor_html(isset($settings->payment_reminder_3_template) ? $settings->payment_reminder_3_template : ''); ?></textarea>
																						<input type="hidden" name="payment_reminder_3_templatefrm" value="1" />
																					</form>
																				</div>
																			</div>
																		</div>
																	</div>
																</div>
															</div>
														</div>
													</div>
												</div>
												<!-- End Payment Reminders Section -->
											</div>
											<!-- End general-settings row -->
										</div>
										<?php endif; ?>
										<!-- End Invoices Tab -->
										
										<?php if (!$isFreeEditionEmailSettings): ?>
										<!-- Messages Tab -->
										<div class="tab-pane fade" id="messages" role="tabpanel" aria-labelledby="messages-tab">
											<div class="row general-settings">
											
												<!-- One to One Chat Batch Email Template -->
											<div class="payment-box">
												<div class="pbox-wrap">
													<div class="pbox-lft">
														<h3><?php echo isset($lang['One to one chat notification email']) ? $lang['One to one chat notification email'] : 'One to one chat notification email'; ?></h3>
													</div>
													<div class="pbox-rt">
														<a href="#" class="setup-btn"><?php echo $lang['Configure']; ?></a>
													</div>
												</div>
												<div class="row">
													<div class="col-md-12">
														<div class="payment-fields" style="display: none;">
															<div class="payment-b-txt">
																<?php echo $lang['Shortcodes:']; ?>
																<ul>
																		<?php email_setting_echo_brand_shortcodes(); ?>
																		<li><?php echo $lang['User name']; ?>: {USER_NAME}</li>
																	<li><?php echo isset($lang['Sender name']) ? $lang['Sender name'] : 'Sender name'; ?>: {SENDER_NAME}</li>
																	<li><?php echo isset($lang['Message count']) ? $lang['Message count'] : 'Message count'; ?>: {MESSAGE_COUNT}</li>
																	<li><?php echo isset($lang['Messages list']) ? $lang['Messages list'] : 'Messages list'; ?>: {MESSAGES_LIST}</li>
																	<li><?php echo isset($lang['Message time']) ? $lang['Message time'] : 'Message time'; ?>: {MESSAGE_TIME}</li>
																	<li><?php echo $lang['Login URL']; ?>: {DASHBOARD_URL}</li>
																	<li><?php echo $lang['Signature']; ?>: {SIGNATURE}</li>
																</ul>
																
															</div>
															<form method="post" action="#" enctype="multipart/form-data">
																<div class="form-group" style="margin-bottom: 15px;">
																	<label><?php echo isset($lang['Email subject']) ? $lang['Email subject'] : 'Email subject'; ?></label>
																	<input type="text" name="onetoonechatbatch_subject" class="form-control" value="<?php echo isset($settings->one_to_one_chat_batch_email_subject) ? htmlspecialchars($settings->one_to_one_chat_batch_email_subject) : 'You have {MESSAGE_COUNT} new message(s) from {SENDER_NAME}'; ?>" placeholder="<?php echo isset($lang['You have MESSAGE_COUNT new message(s) from SENDER_NAME']) ? $lang['You have MESSAGE_COUNT new message(s) from SENDER_NAME'] : 'You have {MESSAGE_COUNT} new message(s) from {SENDER_NAME}'; ?>">
																</div>
																<textarea class="editor" name="onetoonechatbatch"><?php echo task_chat_email_logo_expand_for_editor(isset($settings->one_to_one_chat_batch_email) ? $settings->one_to_one_chat_batch_email : (isset($settings->message_notification_email) ? $settings->message_notification_email : ''), $settings); ?></textarea>
																<input type="hidden" name="onetoonechatbatchfrm" value="1" />
															</form>
														</div>
													</div>
												</div>
											</div>
											<!-- Group Chat Creation Email Template -->
											<div class="payment-box">
												<div class="pbox-wrap">
													<div class="pbox-lft">
														<h3><?php echo isset($lang['Group chat creation email']) ? $lang['Group chat creation email'] : 'Group Chat Creation Email'; ?></h3>
													</div>
													<div class="pbox-rt">
														<a href="#" class="setup-btn"><?php echo $lang['Configure']; ?></a>
													</div>
												</div>
												<div class="row">
													<div class="col-md-12">
														<div class="payment-fields" style="display: none;">
															<div class="payment-b-txt">
																<?php echo $lang['Shortcodes:']; ?>
																<ul>
																		<?php email_setting_echo_brand_shortcodes(); ?>
																		<li><?php echo $lang['User name']; ?>: {USER_NAME}</li>
																	<li><?php echo isset($lang['Group name']) ? $lang['Group name'] : 'Group name'; ?>: {GROUP_NAME}</li>
																	<li><?php echo isset($lang['Creator name']) ? $lang['Creator name'] : 'Creator name'; ?>: {CREATOR_NAME}</li>
																	<li><?php echo $lang['Login URL']; ?>: {DASHBOARD_URL}</li>
																	<li><?php echo $lang['Signature']; ?>: {SIGNATURE}</li>
																</ul>
															</div>
															<form method="post" action="#" enctype="multipart/form-data">
																<div class="form-group" style="margin-bottom: 15px;">
																	<label><?php echo isset($lang['Email subject']) ? $lang['Email subject'] : 'Email subject'; ?></label>
																	<input type="text" name="groupchatcreate_subject" class="form-control" value="<?php echo isset($settings->group_chat_create_email_subject) ? htmlspecialchars($settings->group_chat_create_email_subject) : 'You have been added to group: {GROUP_NAME}'; ?>" placeholder="<?php echo isset($lang['You have been added to group']) ? $lang['You have been added to group'] : 'You have been added to group'; ?>: {GROUP_NAME}">
																</div>
																<textarea class="editor" name="groupchatcreate"><?php echo task_chat_email_logo_expand_for_editor(isset($settings->group_chat_create_email) ? $settings->group_chat_create_email : '', $settings); ?></textarea>
																<input type="hidden" name="groupchatcreatefrm" value="1" />
															</form>
														</div>
													</div>
												</div>
											</div>
											<!-- Group Chat Batch Email Template -->
											<div class="payment-box">
												<div class="pbox-wrap">
													<div class="pbox-lft">
														<h3><?php echo isset($lang['Group chat batch email']) ? $lang['Group chat batch email'] : 'Group Chat Batch Email'; ?></h3>
													</div>
													<div class="pbox-rt">
														<a href="#" class="setup-btn"><?php echo $lang['Configure']; ?></a>
													</div>
												</div>
												<div class="row">
													<div class="col-md-12">
														<div class="payment-fields" style="display: none;">
															<div class="payment-b-txt">
																<?php echo $lang['Shortcodes:']; ?>
																<ul>
																		<?php email_setting_echo_brand_shortcodes(); ?>
																		<li><?php echo $lang['User name']; ?>: {USER_NAME}</li>
																	<li><?php echo isset($lang['Group name']) ? $lang['Group name'] : 'Group name'; ?>: {GROUP_NAME}</li>
																	<li><?php echo isset($lang['Message count']) ? $lang['Message count'] : 'Message count'; ?>: {MESSAGE_COUNT}</li>
																	<li><?php echo isset($lang['Messages list']) ? $lang['Messages list'] : 'Messages list'; ?>: {MESSAGES_LIST}</li>
																	<li><?php echo $lang['Login URL']; ?>: {DASHBOARD_URL}</li>
																	<li><?php echo $lang['Signature']; ?>: {SIGNATURE}</li>
																</ul>
															</div>
															<form method="post" action="#" enctype="multipart/form-data">
																<div class="form-group" style="margin-bottom: 15px;">
																	<label><?php echo isset($lang['Email subject']) ? $lang['Email subject'] : 'Email subject'; ?></label>
																	<input type="text" name="groupchatbatch_subject" class="form-control" value="<?php echo isset($settings->group_chat_batch_email_subject) ? htmlspecialchars($settings->group_chat_batch_email_subject) : 'You have {MESSAGE_COUNT} new message(s) in {GROUP_NAME}'; ?>" placeholder="<?php echo isset($lang['You have MESSAGE_COUNT new message(s) in GROUP_NAME']) ? $lang['You have MESSAGE_COUNT new message(s) in GROUP_NAME'] : 'You have {MESSAGE_COUNT} new message(s) in {GROUP_NAME}'; ?>">
																</div>
																<textarea class="editor" name="groupchatbatch"><?php echo task_chat_email_logo_expand_for_editor(isset($settings->group_chat_batch_email) ? $settings->group_chat_batch_email : (isset($settings->group_chat_message_email) ? $settings->group_chat_message_email : ''), $settings); ?></textarea>
																<input type="hidden" name="groupchatbatchfrm" value="1" />
															</form>
														</div>
													</div>
												</div>
											</div>
											<!-- Discussion Chat Message Email Template -->
											<div class="payment-box">
												<div class="pbox-wrap">
													<div class="pbox-lft">
														<h3><?php echo isset($lang['Discussion chat message email']) ? $lang['Discussion chat message email'] : 'Discussion Chat Message Email'; ?></h3>
													</div>
													<div class="pbox-rt">
														<a href="#" class="setup-btn"><?php echo $lang['Configure']; ?></a>
													</div>
												</div>
												<div class="row">
													<div class="col-md-12">
														<div class="payment-fields" style="display: none;">
															<div class="payment-b-txt">
																<?php echo $lang['Shortcodes:']; ?>
																<ul>
																		<?php email_setting_echo_brand_shortcodes(); ?>
																		<li><?php echo $lang['User name']; ?>: {USER_NAME}</li>
																	<li><?php echo isset($lang['Sender name']) ? $lang['Sender name'] : 'Sender name'; ?>: {SENDER_NAME}</li>
																	<li><?php echo $lang['Project Title']; ?>: {PROJECT_NAME}</li>
																	<li><?php echo isset($lang['Message count']) ? $lang['Message count'] : 'Message count'; ?>: {MESSAGE_COUNT}</li>
																	<li><?php echo isset($lang['Messages list']) ? $lang['Messages list'] : 'Messages list'; ?>: {MESSAGES_LIST}</li>
																	<li><?php echo isset($lang['Message time']) ? $lang['Message time'] : 'Message time'; ?>: {MESSAGE_TIME}</li>
																	<li><?php echo $lang['Login URL']; ?>: {DASHBOARD_URL}</li>
																	<li><?php echo $lang['Signature']; ?>: {SIGNATURE}</li>
																</ul>
															</div>
															<form method="post" action="#" enctype="multipart/form-data">
																<div class="form-group" style="margin-bottom: 15px;">
																	<label><?php echo isset($lang['Email subject']) ? $lang['Email subject'] : 'Email subject'; ?></label>
																	<input type="text" name="groupchatmessage_subject" class="form-control" value="<?php echo isset($settings->group_chat_message_email_subject) ? htmlspecialchars($settings->group_chat_message_email_subject) : 'You have {MESSAGE_COUNT} new message(s) in {PROJECT_NAME}'; ?>" placeholder="<?php echo isset($lang['You have MESSAGE_COUNT new message(s) in GROUP_NAME']) ? $lang['You have MESSAGE_COUNT new message(s) in GROUP_NAME'] : 'You have {MESSAGE_COUNT} new message(s) in {PROJECT_NAME}'; ?>">
																</div>
																<textarea class="editor" name="groupchatmessage"><?php echo task_chat_email_logo_expand_for_editor(isset($settings->group_chat_message_email) ? $settings->group_chat_message_email : (isset($settings->message_notification_email) ? $settings->message_notification_email : ''), $settings); ?></textarea>
																<input type="hidden" name="groupchatmessagefrm" value="1" />
															</form>
														</div>
													</div>
												</div>
											</div>
											<!-- Task Chat Batch Email Template -->
											<div class="payment-box">
												<div class="pbox-wrap">
													<div class="pbox-lft">
														<h3><?php echo isset($lang['Task chat batch email']) ? $lang['Task chat batch email'] : 'Task Chat Batch Email'; ?></h3>
													</div>
													<div class="pbox-rt">
														<a href="#" class="setup-btn"><?php echo $lang['Configure']; ?></a>
													</div>
												</div>
												<div class="row">
													<div class="col-md-12">
														<div class="payment-fields" style="display: none;">
															<div class="payment-b-txt">
																<?php echo $lang['Shortcodes:']; ?>
																<ul>
																		<?php email_setting_echo_brand_shortcodes(); ?>
																		<li><?php echo $lang['User name']; ?>: {USER_NAME}</li>
																	<li><?php echo isset($lang['Task name']) ? $lang['Task name'] : 'Task name'; ?>: {TASK_NAME}</li>
																	<li><?php echo $lang['Project Title']; ?>: {PROJECT_NAME}</li>
																	<li><?php echo isset($lang['Message count']) ? $lang['Message count'] : 'Message count'; ?>: {MESSAGE_COUNT}</li>
																	<li><?php echo isset($lang['Messages list']) ? $lang['Messages list'] : 'Messages list'; ?>: {MESSAGES_LIST}</li>
																	<li><?php echo $lang['Login URL']; ?>: {DASHBOARD_URL}</li>
																	<li><?php echo $lang['Signature']; ?>: {SIGNATURE}</li>
																</ul>
															</div>
															<form method="post" action="#" enctype="multipart/form-data">
																<div class="form-group" style="margin-bottom: 15px;">
																	<label><?php echo isset($lang['Email subject']) ? $lang['Email subject'] : 'Email subject'; ?></label>
																	<input type="text" name="taskchatbatch_subject" class="form-control" value="<?php echo isset($settings->task_chat_batch_email_subject) ? htmlspecialchars($settings->task_chat_batch_email_subject) : 'You have {MESSAGE_COUNT} new message(s) in task: {TASK_NAME}'; ?>" placeholder="<?php echo isset($lang['You have MESSAGE_COUNT new message(s) in task: TASK_NAME']) ? $lang['You have MESSAGE_COUNT new message(s) in task: TASK_NAME'] : 'You have {MESSAGE_COUNT} new message(s) in task: {TASK_NAME}'; ?>">
																</div>
																<textarea class="editor" name="taskchatbatch"><?php
																	$task_chat_tpl_editor = isset($settings->task_chat_batch_email) ? $settings->task_chat_batch_email : (isset($settings->message_notification_email) ? $settings->message_notification_email : '');
																	echo task_chat_email_logo_expand_for_editor($task_chat_tpl_editor, $settings);
																?></textarea>
																<input type="hidden" name="taskchatbatchfrm" value="1" />
															</form>
														</div>
													</div>
												</div>
											</div>
										</div>
											<!-- End general-settings row -->
										</div>
										<!-- End Messages Tab -->
										
										<!-- Email Report Tab -->
										<div class="tab-pane fade" id="email-report" role="tabpanel" aria-labelledby="email-report-tab">
											<div class="row general-settings">
												<div class="payment-box">
													<div class="pbox-wrap">
														<div class="pbox-lft">
															<h3><?php echo isset($lang['Email Report Settings']) ? $lang['Email Report Settings'] : 'Email Report Settings'; ?></h3>
														</div>
														<div class="pbox-rt">
															<a href="#" class="setup-btn"><?php echo $lang['Configure']; ?></a>
														</div>
													</div>
													<div class="row">
														<div class="col-md-12">
															<div class="payment-fields" style="display: none;">
																<div class="payment-b-txt">
																	<?php echo isset($lang['Shortcodes:']) ? $lang['Shortcodes:'] : 'Shortcodes:'; ?>
																	<ul>
																		<?php email_setting_echo_brand_shortcodes(); ?>
																		<li><?php echo isset($lang['User name']) ? $lang['User name'] : 'User name'; ?>: {USER_NAME}</li>
																		<li><?php echo isset($lang['Total Accounts']) ? $lang['Total Accounts'] : 'Total Accounts'; ?>: {TOTAL_ACCOUNTS}</li>
																		<li><?php echo isset($lang['Total Emails Received']) ? $lang['Total Emails Received'] : 'Total Emails Received'; ?>: {TOTAL_EMAILS_RECEIVED}</li>
																		<li><?php echo isset($lang['Total Emails Sent']) ? $lang['Total Emails Sent'] : 'Total Emails Sent'; ?>: {TOTAL_EMAILS_SENT}</li>
																		<li><?php echo isset($lang['Account Reports']) ? $lang['Account Reports'] : 'Account Reports'; ?>: {ACCOUNT_REPORTS} (Automatically repeats for all accounts)</li>
																		<li><?php echo isset($lang['Report Period']) ? $lang['Report Period'] : 'Report Period'; ?>: {REPORT_PERIOD}</li>
																		<li><?php echo isset($lang['Dashboard URL']) ? $lang['Dashboard URL'] : 'Dashboard URL'; ?>: {DASHBOARD_URL}</li>
																		<li><?php echo isset($lang['Signature']) ? $lang['Signature'] : 'Signature'; ?>: {SIGNATURE}</li>
																	</ul>
																</div>
																<form method="post" action="#" enctype="multipart/form-data">
																	<div class="form-group" style="margin-bottom: 15px;">
																		<label><?php echo isset($lang['Subject Line']) ? $lang['Subject Line'] : 'Subject Line'; ?></label>
																		<input type="text" name="email_report_subject" class="form-control" value="<?php echo isset($settings->email_report_subject) ? htmlspecialchars($settings->email_report_subject) : 'Email Account Summary Report'; ?>" placeholder="<?php echo isset($lang['Email Account Summary Report']) ? $lang['Email Account Summary Report'] : 'Email Account Summary Report'; ?>" />
																	</div>
																	<textarea class="editor" name="email_report_template"><?php echo isset($settings->email_report_template) ? $settings->email_report_template : 'Dear {USER_NAME},

Here is your email account summary for {REPORT_PERIOD}:

Total Accounts: {TOTAL_ACCOUNTS}

Your account summary: {ACCOUNT_EMAIL}

Total Emails Received: {ACCOUNT_RECEIVED}
Total Emails Sent: {ACCOUNT_SENT}

---------------------------------------

View your inbox: {DASHBOARD_URL}

{SIGNATURE}'; ?></textarea>
																	<input type="hidden" name="email_report_settings" value="1" />
																</form>
															</div>
														</div>
													</div>
												</div>
											</div>
										</div>
										<!-- End Email Report Tab -->
										<?php endif; ?>
									</div>
									<!-- End Tab Content -->
								</div>
							</div>
					</div>
			</div>
			<div class="clearfix"></div>
		</div>
	</div>
<script>window.__toastFlash=<?php echo json_encode($toast_flash, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
<script src="<?php echo htmlspecialchars(rtrim((string) $url, '/') . '/assets/js/toast.js', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php include("../templates/main-footer.php"); ?>
<script>window.skipAutoRichEditorInit = true;</script>
<script src="../assets/js/rich-editor.js"></script>
<style>
/* Ensure tab panes are properly displayed */
#emailTabsContent .tab-pane {
    display: none !important;
}
#emailTabsContent .tab-pane.show,
#emailTabsContent .tab-pane.active,
#emailTabsContent .tab-pane.show.active {
    display: block !important;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Lazy RichEditors: only when Configure is opened (page has ~22 templates)
    function emailSettingsInitEditorsIn(root) {
        if (!root || typeof RichEditor === 'undefined') return;
        root.querySelectorAll('textarea.editor').forEach(function (textarea) {
            if (textarea.dataset.richEditorInitialized === 'true') return;
            if (!textarea.name) return;
            new RichEditor('textarea[name="' + textarea.name + '"]', {
                height: 300,
                placeholder: textarea.placeholder || 'Start typing...'
            });
        });
    }

    document.querySelectorAll('#emailTabsContent .payment-fields').forEach(function (pf) {
        if (pf.style.display === 'none') return;
        if (window.getComputedStyle(pf).display === 'none') return;
        emailSettingsInitEditorsIn(pf);
    });

    document.querySelectorAll('#emailTabsContent .setup-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var box = this.closest('.payment-box');
            if (!box) return;
            setTimeout(function () {
                var fields = box.querySelector('.payment-fields');
                if (!fields || fields.style.display === 'none') return;
                emailSettingsInitEditorsIn(fields);
            }, 20);
        });
    });

    // Function to show a specific tab pane
    function showTabPane(targetId) {
        // Hide all tab panes
        document.querySelectorAll('#emailTabsContent .tab-pane').forEach(function(pane) {
            pane.classList.remove('show', 'active');
            pane.style.display = 'none';
        });
        
        // Remove active from all tab buttons
        document.querySelectorAll('#emailTabs .nav-link').forEach(function(btn) {
            btn.classList.remove('active');
            btn.setAttribute('aria-selected', 'false');
        });
        
        // Show target pane
        const targetPane = document.querySelector(targetId);
        if (targetPane) {
            targetPane.classList.add('show', 'active');
            targetPane.style.display = 'block';
        }
        
        // Activate target button
        const targetButton = document.querySelector('[data-bs-target="' + targetId + '"]');
        if (targetButton) {
            targetButton.classList.add('active');
            targetButton.setAttribute('aria-selected', 'true');
        }
    }
    
    // Tab persistence
    const lastTab = localStorage.getItem('emailSettingsActiveTab');
    if (lastTab) {
        showTabPane(lastTab);
    } else {
        // Default to Account tab
        showTabPane('#account');
    }

    // Handle tab clicks
    const tabLinks = document.querySelectorAll('#emailTabs button[data-bs-toggle="tab"]');
    tabLinks.forEach(function(tabLink) {
        tabLink.addEventListener('click', function(event) {
            event.preventDefault();
            const targetId = this.getAttribute('data-bs-target');
            if (targetId) {
                showTabPane(targetId);
                localStorage.setItem('emailSettingsActiveTab', targetId);
            }
        });
        
        tabLink.addEventListener('shown.bs.tab', function(event) {
            const targetId = event.target.getAttribute('data-bs-target');
            if (targetId) {
                localStorage.setItem('emailSettingsActiveTab', targetId);
            }
        });
    });
    
    // Ensure active tab is visible on page load
    setTimeout(function() {
        const activeTab = document.querySelector('#emailTabs .nav-link.active');
        if (activeTab) {
            const targetId = activeTab.getAttribute('data-bs-target');
            if (targetId) {
                showTabPane(targetId);
            }
        }
    }, 100);
    
    // Payment Reminders Toggle Functionality
    const paymentRemindersToggle = document.getElementById('payment_reminders_enabled');
    const paymentReminderSettings = document.getElementById('payment_reminder_settings');
    
    if (paymentRemindersToggle && paymentReminderSettings) {
        // Initial state
        paymentReminderSettings.style.display = paymentRemindersToggle.checked ? 'block' : 'none';
        
        // Handle toggle change (for when form is submitted and page reloads)
        paymentRemindersToggle.addEventListener('change', function() {
            paymentReminderSettings.style.display = this.checked ? 'block' : 'none';
        });
    }
    
    // Individual Reminder Toggles
    const reminder1Toggle = document.getElementById('payment_reminder_1_enabled');
    const reminder1Settings = document.getElementById('reminder_1_settings');
    if (reminder1Toggle && reminder1Settings) {
        reminder1Settings.style.display = reminder1Toggle.checked ? 'block' : 'none';
        reminder1Toggle.addEventListener('change', function() {
            reminder1Settings.style.display = this.checked ? 'block' : 'none';
        });
    }
    
    const reminder2Toggle = document.getElementById('payment_reminder_2_enabled');
    const reminder2Settings = document.getElementById('reminder_2_settings');
    if (reminder2Toggle && reminder2Settings) {
        reminder2Settings.style.display = reminder2Toggle.checked ? 'block' : 'none';
        reminder2Toggle.addEventListener('change', function() {
            reminder2Settings.style.display = this.checked ? 'block' : 'none';
        });
    }
    
    const reminder3Toggle = document.getElementById('payment_reminder_3_enabled');
    const reminder3Settings = document.getElementById('reminder_3_settings');
    if (reminder3Toggle && reminder3Settings) {
        reminder3Settings.style.display = reminder3Toggle.checked ? 'block' : 'none';
        reminder3Toggle.addEventListener('change', function() {
            reminder3Settings.style.display = this.checked ? 'block' : 'none';
        });
    }
    
    // Task Reminders Toggle Functionality
    const taskRemindersToggle = document.getElementById('task_reminders_enabled');
    const taskReminderSettings = document.getElementById('task_reminder_settings');
    
    if (taskRemindersToggle && taskReminderSettings) {
        // Initial state
        taskReminderSettings.style.display = taskRemindersToggle.checked ? 'block' : 'none';
        
        // Handle toggle change (for when form is submitted and page reloads)
        taskRemindersToggle.addEventListener('change', function() {
            taskReminderSettings.style.display = this.checked ? 'block' : 'none';
        });
    }
    
    // Individual Task Reminder Toggles
    const taskReminder1Toggle = document.getElementById('task_reminder_1_enabled');
    const taskReminder1Settings = document.getElementById('task_reminder_1_settings');
    if (taskReminder1Toggle && taskReminder1Settings) {
        taskReminder1Settings.style.display = taskReminder1Toggle.checked ? 'block' : 'none';
        taskReminder1Toggle.addEventListener('change', function() {
            taskReminder1Settings.style.display = this.checked ? 'block' : 'none';
        });
    }
    
    const taskReminder2Toggle = document.getElementById('task_reminder_2_enabled');
    const taskReminder2Settings = document.getElementById('task_reminder_2_settings');
    if (taskReminder2Toggle && taskReminder2Settings) {
        taskReminder2Settings.style.display = taskReminder2Toggle.checked ? 'block' : 'none';
        taskReminder2Toggle.addEventListener('change', function() {
            taskReminder2Settings.style.display = this.checked ? 'block' : 'none';
        });
    }
    
    const taskReminder3Toggle = document.getElementById('task_reminder_3_enabled');
    const taskReminder3Settings = document.getElementById('task_reminder_3_settings');
    if (taskReminder3Toggle && taskReminder3Settings) {
        taskReminder3Settings.style.display = taskReminder3Toggle.checked ? 'block' : 'none';
        taskReminder3Toggle.addEventListener('change', function() {
            taskReminder3Settings.style.display = this.checked ? 'block' : 'none';
        });
    }

    // Central header Save — sync editors, then save ALL open Configure forms on this tab
    var saveBtn = document.getElementById('emailSettingsSaveBtn');
    if (saveBtn) {
        function emailSettingsIsVisible(el) {
            if (!el) return false;
            var style = window.getComputedStyle(el);
            if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
                return false;
            }
            return el.getClientRects().length > 0;
        }

        function emailSettingsActivePane() {
            return document.querySelector('#emailTabsContent .tab-pane.active')
                || document.querySelector('#emailTabsContent .tab-pane.show');
        }

        function emailSettingsFindOpenForms() {
            var active = emailSettingsActivePane();
            if (!active) return [];

            var expanded = [];
            active.querySelectorAll('.payment-fields').forEach(function (pf) {
                var inline = (pf.style && pf.style.display) ? pf.style.display : '';
                if (inline === 'none') return;
                if (!emailSettingsIsVisible(pf)) return;
                var form = pf.querySelector('form');
                if (form) expanded.push(form);
            });

            if (!expanded.length) {
                var reminderForm = active.querySelector('#task_reminder_settings_form, #payment_reminder_settings_form');
                if (reminderForm && emailSettingsIsVisible(reminderForm)) {
                    expanded.push(reminderForm);
                }
            }
            return expanded;
        }

        function emailSettingsSyncOpenEditors(forms) {
            if (typeof window.syncRichEditors === 'function') {
                forms.forEach(function (form) {
                    window.syncRichEditors(form);
                });
            } else if (typeof RichEditor !== 'undefined' && RichEditor.syncAll) {
                forms.forEach(function (form) {
                    RichEditor.syncAll(form);
                });
            }
            if (typeof tinymce !== 'undefined' && tinymce.triggerSave) {
                tinymce.triggerSave();
            }
        }

        function emailSettingsTabHash() {
            var active = emailSettingsActivePane();
            if (active && active.id) return active.id;
            return '';
        }

        function emailSettingsBatchSubmit(forms) {
            var batch = document.createElement('form');
            batch.method = 'post';
            batch.action = window.location.pathname.split('/').pop() || 'email-setting.php';
            batch.style.display = 'none';

            function addField(name, value) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value == null ? '' : String(value);
                batch.appendChild(input);
            }

            addField('email_settings_batch_save', '1');
            addField('email_settings_tab', emailSettingsTabHash());

            forms.forEach(function (form) {
                var fd = new FormData(form);
                fd.forEach(function (value, key) {
                    // Skip empty file inputs; keep template markers + fields
                    if (typeof value === 'object' && value !== null && typeof File !== 'undefined' && value instanceof File) {
                        if (!value.name) return;
                    }
                    addField(key, value);
                });
            });

            document.body.appendChild(batch);
            batch.submit();
        }

        saveBtn.addEventListener('click', function () {
            var forms = emailSettingsFindOpenForms();
            if (!forms.length) {
                var msg = 'Open a section with Configure, then click Save Setting.';
                if (typeof window.showToast === 'function') {
                    window.showToast(msg, 'info');
                } else {
                    alert(msg);
                }
                return;
            }

            emailSettingsSyncOpenEditors(forms);

            // One form: normal submit (after sync so textarea has latest HTML)
            if (forms.length === 1) {
                var form = forms[0];
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
                return;
            }

            // Multiple open templates: save all in one POST
            emailSettingsBatchSubmit(forms);
        });
    }
});
</script>