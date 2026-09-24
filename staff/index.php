<?php 
/*
 ================================================================================
   Task Session – Project Management System
   Purpose: Staff Dashboard Template
 ================================================================================
 */
ob_start();
require_once("../includes/lib-initialize.php");
require_once("../includes/permissions.php");
require_once("../includes/reports_common_helper.php");
require_once("../includes/stat_sparkline_helper.php");
require_once("../includes/milestone_invoice_total.php");
require_once("../includes/dashboard_empty_state.php");
require_once("../includes/activity_page_helper.php");
require_once("../includes/notifications.php");
require_once("../includes/dashboard_widgets_helper.php");
$title = "Dashboard | " . $syatem_title;
if (!function_exists('comon_page_assets_set')) {
    require_once LIB_ROOT . DS . 'page_assets.php';
}
comon_page_assets_set([
    'jquery_ui' => false,
    'rich_text' => false,
    'file_sharing' => false,
    'email_notification_modal' => false,
    'ai_rail' => 'lazy',
    'task_sidebar_bundle' => 'lazy',
]);
include("../templates/header.php");

// Authentication checks
 if(!($session->isLoggedIn())){
		redirectTo($url."index.php");
	}
if($_SESSION['accountStatus'] == 2){
	redirectTo($url."client/index.php");
}
if($_SESSION['accountStatus'] == 1){
	redirectTo($url."admin/index.php");
} 

// Set timezone
date_default_timezone_set($time_zone);

// Load settings for module checks
$settingsForModules = isset($dash_settings) && $dash_settings ? $dash_settings : settings::findById(1);
require_once __DIR__ . '/../includes/sidebar_navigation.php';
$guardUser = User::findById((int) $session->userId);
comon_guard_hidden_dashboard($settingsForModules, 'staff', $guardUser);
$isDiscussionsEnabled = ($settingsForModules && !empty($settingsForModules->module_discussions));
// Permissions must be in session before has_permission() — sidebar loads them too late for this page.
ensure_user_permissions($connect);
$canViewMilestones = has_permission('milestone_view');
$invoiceModuleEnabled = !empty($settingsForModules->module_invoices);
$canUseInvoiceWidgets = ($canViewMilestones && $invoiceModuleEnabled);
$attendanceModuleEnabled = !empty($settingsForModules->module_attendance);
$isTasksEnabled = ($settingsForModules && !empty($settingsForModules->module_tasks));
$isNotesDocumentsEnabled = ($settingsForModules && !empty($settingsForModules->module_notes_documents));
$isLeadBoardEnabled = ($settingsForModules && !empty($settingsForModules->module_lead_board));
$staffCanViewLeadsPipeline = $isLeadBoardEnabled && has_permission('lead_view_all');

/**
 * Project card avatar on dashboard — always visible; chat link only when discussions module is on.
 */
function renderDashboardProjectUserBox($user, $projectId, $tooltipTitle, $isDiscussionsEnabled, $userId = null) {
    if (!$user) {
        return;
    }
    $uid = $userId !== null ? (int) $userId : (int) $user->id;
    if ($uid <= 0) {
        return;
    }
    $firstName = (string) ($user->firstName ?? '');
    $lastName = (string) ($user->lastName ?? '');
    $titleEsc = htmlspecialchars($tooltipTitle !== '' ? $tooltipTitle : $firstName, ENT_QUOTES, 'UTF-8');
    $avatar = getUserAvatarHtml($uid, $firstName, $lastName, 36, 36, 'img-fluid rounded-circle', $firstName);
    echo '<div class="user-box">';
    if ($isDiscussionsEnabled) {
        echo '<form action="../discussion?project_id=' . (int) $projectId . '" method="post">';
        echo '<input type="hidden" name="user_id" value="' . $uid . '" />';
        echo '<input type="hidden" name="project_id" value="' . (int) $projectId . '" />';
        echo '<button name="chat" type="submit" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $titleEsc . '">';
        echo $avatar;
        echo '</button></form>';
    } else {
        echo '<span data-bs-toggle="tooltip" data-bs-placement="top" title="' . $titleEsc . '">';
        echo $avatar;
        echo '</span>';
    }
    echo '</div>';
}

/**
 * Staff dashboard "My Work Today" card (Attendance-style shell).
 */
function renderStaffDashTimeLoggedCard($lang, int $loggedSec, int $targetSec, int $entryCount = 0) {
    $title = $lang['Task time today'] ?? 'Task time today';
    $loggedLabel = tasksession_format_duration_hm($loggedSec);
    $metaLabel = tasksession_format_entries_today_label($entryCount, $lang);
    ?>
							<div class="widget-card dash-counter stat-spark-card stat-spark-card--time-logged" id="staff-dash-time-logged-card">
								<div class="grey"><span><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></span></div>
								<div class="counts dash-rttb" id="staff-dash-time-logged-value"><?php echo htmlspecialchars($loggedLabel, ENT_QUOTES, 'UTF-8'); ?></div>
								<div class="grey persent-count" id="staff-dash-time-logged-meta"><?php echo htmlspecialchars($metaLabel, ENT_QUOTES, 'UTF-8'); ?></div>
								<?php renderStaffDashProgressGraph($loggedSec, $targetSec, 'staff-dash-time-logged'); ?>
							</div>
    <?php
}

function renderStaffDashShiftTodayCard($url, $lang, array $shift) {
    $title = $lang['Today\'s shift'] ?? 'Today\'s shift';
    $elapsedSec = (int) ($shift['elapsed_sec'] ?? 0);
    $targetSec = (int) ($shift['shift_total_sec'] ?? 0);
    $elapsedLabel = tasksession_format_duration_hm($elapsedSec);
    $shiftLabel = (string) ($shift['shift_label'] ?? '');
    $metaNote = (string) ($shift['meta_note'] ?? '');
    $noteColor = (string) ($shift['meta_note_color'] ?? '#888');
    $checkedIn = !empty($shift['checked_in']);
    $checkedOut = !empty($shift['checked_out']);
    $checkInTs = (int) ($shift['check_in_ts'] ?? 0);
    ?>
							<div class="widget-card dash-counter stat-spark-card stat-spark-card--shift-today widget-card--linked" id="staff-dash-shift-today-card"
								data-checked-in="<?php echo $checkedIn ? '1' : '0'; ?>"
								data-checked-out="<?php echo $checkedOut ? '1' : '0'; ?>"
								data-check-in-ts="<?php echo $checkInTs; ?>"
								data-shift-total-sec="<?php echo $targetSec; ?>">
								<div class="grey"><span><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></span></div>
								<div class="counts dash-rttb" id="staff-dash-shift-today-value"><?php echo htmlspecialchars($elapsedLabel, ENT_QUOTES, 'UTF-8'); ?></div>
								<div class="grey persent-count">
									<span id="staff-dash-shift-today-target"><?php echo htmlspecialchars($shiftLabel, ENT_QUOTES, 'UTF-8'); ?></span><?php if ($shiftLabel !== '' && $metaNote !== ''): ?> - <?php endif; ?><span id="staff-dash-shift-today-note" style="color:<?php echo htmlspecialchars($noteColor, ENT_QUOTES, 'UTF-8'); ?>;"><?php echo htmlspecialchars($metaNote, ENT_QUOTES, 'UTF-8'); ?></span>
								</div>
								<?php renderStaffDashProgressGraph($elapsedSec, $targetSec, 'staff-dash-shift-today'); ?>
								<a href="<?php echo htmlspecialchars($url . 'staff/attendance', ENT_QUOTES, 'UTF-8'); ?>" class="widget-card__stretch-link" aria-label="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>"></a>
							</div>
    <?php
}

function renderStaffDashTimerStatCards($url, $lang, int $loggedSec, int $targetSec, int $entryCount, array $shiftCard, $timeColClass, $shiftColClass) {
    ?>
						<div class="<?php echo htmlspecialchars($timeColClass, ENT_QUOTES, 'UTF-8'); ?>">
							<?php renderStaffDashTimeLoggedCard($lang, $loggedSec, $targetSec, $entryCount); ?>
						</div>
						<div class="<?php echo htmlspecialchars($shiftColClass, ENT_QUOTES, 'UTF-8'); ?>">
							<?php renderStaffDashShiftTodayCard($url, $lang, $shiftCard); ?>
						</div>
    <?php
}

function renderStaffAttendanceOverviewCard($url, $lang, array $att) {
    $present = (int) ($att['present'] ?? 0);
    $late = (int) ($att['late'] ?? 0);
    $absent = (int) ($att['absent'] ?? 0);
    $total = (int) ($att['total'] ?? 0);
    $pctChange = (int) ($att['pct_change'] ?? 0);
    $deductionAmount = (float) ($att['deduction_amount'] ?? 0);
    $currencySymbol = (string) ($att['currency_symbol'] ?? '$');
    $sparkPresent = $att['spark_present'] ?? array_fill(0, 12, 0);
    $sparkAbsent = $att['spark_absent'] ?? array_fill(0, 12, 0);
    $fmtDashMoney = $att['fmt_money'] ?? static function ($amount, $symbol) {
        return $symbol . number_format((float) $amount, 2, '.', ',');
    };
    $presentLabel = $lang['Present'] ?? 'Present';
    $lateLabel = $lang['Late'] ?? ($lang['Late Arrival'] ?? 'Late');
    $absentLabel = $lang['Absent'] ?? 'Absent';
    $deductionLabel = $lang['Deduction'] ?? 'Deduction';
    $vsLastMonth = $lang['task_reports_vs_last_month'] ?? 'vs last month';
    ?>
							<div class="widget-card stat-spark-card stat-spark-card--invoice-overview stat-spark-card--attendance-overview widget-card--linked" id="attendance-overview-card">
								<div class="stat-invoice-head">
									<div class="grey"><span><?php echo htmlspecialchars($lang['Attendance'] ?? 'Attendance', ENT_QUOTES, 'UTF-8'); ?></span></div>
									<span class="stat-invoice-badge"><?php echo $total; ?> <?php echo htmlspecialchars($lang['this month'] ?? 'this month', ENT_QUOTES, 'UTF-8'); ?></span>
								</div>
								<div class="stat-invoice-grid stat-invoice-grid--triple">
									<div class="stat-invoice-col">
										<div class="counts dash-rttb is-paid mt-0"><?php echo $present; ?></div>
										<div class="grey persent-count"><?php echo htmlspecialchars($presentLabel, ENT_QUOTES, 'UTF-8'); ?></div>
									</div>
									<div class="stat-invoice-col">
										<div class="counts dash-rttb is-late mt-0"><?php echo $late; ?></div>
										<div class="grey persent-count"><?php echo htmlspecialchars($lateLabel, ENT_QUOTES, 'UTF-8'); ?></div>
									</div>
									<div class="stat-invoice-col">
										<div class="counts dash-rttb is-unpaid mt-0"><?php echo $absent; ?></div>
										<div class="grey persent-count"><?php
											echo htmlspecialchars($absentLabel, ENT_QUOTES, 'UTF-8') . ' - ' . htmlspecialchars($deductionLabel, ENT_QUOTES, 'UTF-8');
											echo ' &middot; ' . htmlspecialchars(is_callable($fmtDashMoney) ? $fmtDashMoney($deductionAmount, $currencySymbol) : ($currencySymbol . number_format($deductionAmount, 2)), ENT_QUOTES, 'UTF-8');
										?></div>
									</div>
								</div>
								<?php renderAdminInvoiceDualSparkline($sparkPresent, $sparkAbsent); ?>
								<div class="stat-invoice-foot">
									<div class="stat-invoice-legend">
										<span><i class="dot bg-green"></i> <?php echo htmlspecialchars($presentLabel, ENT_QUOTES, 'UTF-8'); ?></span>
										<span><i class="dot bg-red"></i> <?php echo htmlspecialchars($absentLabel, ENT_QUOTES, 'UTF-8'); ?></span>
									</div>
									<div class="grey persent-count">
										<?php
										if ($pctChange > 0) {
											echo '<span style="color:green;">+' . $pctChange . '%</span> ' . htmlspecialchars($vsLastMonth, ENT_QUOTES, 'UTF-8');
										} elseif ($pctChange < 0) {
											echo '<span style="color:red;">' . abs($pctChange) . '%</span> ' . htmlspecialchars($vsLastMonth, ENT_QUOTES, 'UTF-8');
										} else {
											echo '0% ' . htmlspecialchars($vsLastMonth, ENT_QUOTES, 'UTF-8');
										}
										?>
									</div>
								</div>
								<a href="<?php echo htmlspecialchars($url . 'staff/attendance', ENT_QUOTES, 'UTF-8'); ?>" class="widget-card__stretch-link" aria-label="<?php echo htmlspecialchars($lang['Attendance'] ?? 'Attendance', ENT_QUOTES, 'UTF-8'); ?>"></a>
							</div>
    <?php
}

function renderStaffMyWorkTodayCard($url, $lang, array $work, array $sparkOnTime, array $sparkLate, $onTimePct = 0, $onTimePctChange = 0) {
    $assigned = (int) ($work['assigned'] ?? 0);
    $completed = (int) ($work['completed'] ?? 0);
    $dueToday = (int) ($work['due_today'] ?? 0);
    $overdue = (int) ($work['overdue'] ?? 0);
    $tasksUrl = $url . 'staff/kanban';
    $title = $lang['My Work Today'] ?? 'My work today';
    $viewLabel = $lang['View Tasks'] ?? 'View Tasks';
    $completeLabel = $lang['Complete'] ?? 'Complete';
    $dueTodayLabel = $lang['Due Today'] ?? 'Due today';
    $overdueLabel = $lang['Overdue'] ?? 'Overdue';
    $onTimeLabel = $lang['On-Time Completion'] ?? $lang['Overall tasks completed on time'] ?? 'On-Time Completion';
    $vsLastMonth = $lang['task_reports_vs_last_month'] ?? 'vs last month';
    $onTimePct = max(0, min(100, (int) $onTimePct));
    if ($onTimePct >= 80) {
        $onTimeColor = 'green';
    } elseif ($onTimePct >= 50) {
        $onTimeColor = '#d97706';
    } else {
        $onTimeColor = 'red';
    }
    ?>
							<div class="widget-card stat-spark-card stat-spark-card--invoice-overview stat-spark-card--attendance-overview widget-card--linked" id="my-work-today-card">
								<div class="stat-invoice-head">
									<div class="grey"><span><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></span></div>
									<span class="stat-invoice-badge"><?php echo $assigned; ?> <?php echo htmlspecialchars($lang['Assigned Tasks'] ?? 'Assigned Tasks', ENT_QUOTES, 'UTF-8'); ?></span>
								</div>
								<div class="stat-invoice-grid stat-invoice-grid--triple">
									<div class="stat-invoice-col">
										<div class="counts dash-rttb is-paid mt-0"><?php echo $completed; ?></div>
										<div class="grey persent-count"><?php echo htmlspecialchars($completeLabel, ENT_QUOTES, 'UTF-8'); ?></div>
									</div>
									<div class="stat-invoice-col">
										<div class="counts dash-rttb is-due-today mt-0"><?php echo $dueToday; ?></div>
										<div class="grey persent-count"><?php echo htmlspecialchars($dueTodayLabel, ENT_QUOTES, 'UTF-8'); ?></div>
									</div>
									<div class="stat-invoice-col">
										<div class="counts dash-rttb is-unpaid mt-0"><?php echo $overdue; ?></div>
										<div class="grey persent-count"><?php echo htmlspecialchars($overdueLabel, ENT_QUOTES, 'UTF-8'); ?></div>
									</div>
								</div>
								<?php renderAdminInvoiceDualSparkline($sparkOnTime, $sparkLate); ?>
								<div class="stat-invoice-foot">
									<div class="grey persent-count stat-work-performance">
										<strong style="color:<?php echo htmlspecialchars($onTimeColor, ENT_QUOTES, 'UTF-8'); ?>;"><?php echo $onTimePct; ?>%</strong>
										<?php echo htmlspecialchars($onTimeLabel, ENT_QUOTES, 'UTF-8'); ?>
									</div>
									<div class="grey persent-count">
										<?php
										if ($onTimePctChange > 0) {
											echo '<span style="color:green;">+' . (int) $onTimePctChange . '%</span> ' . htmlspecialchars($vsLastMonth, ENT_QUOTES, 'UTF-8');
										} elseif ($onTimePctChange < 0) {
											echo '<span style="color:red;">' . (int) $onTimePctChange . '%</span> ' . htmlspecialchars($vsLastMonth, ENT_QUOTES, 'UTF-8');
										} else {
											echo '0% ' . htmlspecialchars($vsLastMonth, ENT_QUOTES, 'UTF-8');
										}
										?>
								</div>
								</div>
								<a href="<?php echo htmlspecialchars($tasksUrl, ENT_QUOTES, 'UTF-8'); ?>" class="widget-card__stretch-link" aria-label="<?php echo htmlspecialchars($viewLabel, ENT_QUOTES, 'UTF-8'); ?>"></a>
							</div>
    <?php
}

function renderStaffSessionCard($url, $lang, $dateLabel, $durationLabel, $isOnline, array $sparkSession, $userId) {
    $title = $lang['Last Session'] ?? 'Last session';
    $activeLabel = $lang['Active'] ?? 'Active';
    $profileUrl = $url . 'staff/profile?user_id=' . (int) $userId . '&tab=activity';
    ?>
							<div class="widget-card stat-spark-card stat-spark-card--session widget-card--linked">
								<div class="grey"><span><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></span></div>
								<div class="counts dash-rttb"><?php echo htmlspecialchars((string) $dateLabel, ENT_QUOTES, 'UTF-8'); ?></div>
								<div class="grey persent-count">
									<?php if (!empty($isOnline)): ?>
										<span class="stat-session-status is-online">
											<?php echo ts_icon('dot', 'stat-session-dot-icon'); ?>
											<span><?php echo htmlspecialchars($activeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
										</span>
										<?php else: ?>
										<?php echo htmlspecialchars((string) $durationLabel, ENT_QUOTES, 'UTF-8'); ?>
										<?php endif; ?>
									</div>
								<?php renderAdminStatSparkline('session', $sparkSession); ?>
								<a href="<?php echo htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8'); ?>" class="widget-card__stretch-link" aria-label="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>"></a>
								</div>
    <?php
}

function renderStaffInvoiceOverviewCard($url, $lang, $invoiceOverviewTotal, $paidCount, $unpaidCount, callable $fmtDashMoney, $paidAmountAll, $unpaidAmount, $dashCurrencySymbol, array $sparkPaid, array $sparkUnpaid, $invoicePaidPctChange) {
    ?>
							<div class="widget-card stat-spark-card stat-spark-card--invoice-overview stat-spark-card--attendance-overview widget-card--linked" id="invoice-overview-card">
								<div class="stat-invoice-head">
									<div class="grey"><span>Invoice Overview</span></div>
									<span class="stat-invoice-badge" id="invoice-overview-badge"><?php echo (int) $invoiceOverviewTotal; ?> total</span>
							</div>
								<div class="stat-invoice-grid">
									<div class="stat-invoice-col">
										<div class="counts dash-rttb is-paid mt-0" id="invoice-overview-paid-count"><?php echo (int) $paidCount; ?></div>
										<div class="grey persent-count" id="invoice-overview-paid-meta"><?php echo $lang['Paid Invoices']; ?> &middot; <?php echo htmlspecialchars($fmtDashMoney($paidAmountAll, $dashCurrencySymbol), ENT_QUOTES, 'UTF-8'); ?></div>
									</div>
									<div class="stat-invoice-col">
										<div class="counts dash-rttb is-unpaid mt-0" id="invoice-overview-unpaid-count"><?php echo (int) $unpaidCount; ?></div>
										<div class="grey persent-count" id="invoice-overview-unpaid-meta"><?php echo $lang['Unpaid']; ?> &middot; <?php echo htmlspecialchars($fmtDashMoney($unpaidAmount, $dashCurrencySymbol), ENT_QUOTES, 'UTF-8'); ?></div>
									</div>
								</div>
								<?php renderAdminInvoiceDualSparkline($sparkPaid, $sparkUnpaid); ?>
								<div class="stat-invoice-foot">
									<div class="stat-invoice-legend">
										<span><i class="dot bg-green"></i> Paid trend</span>
										<span><i class="dot bg-red"></i> Unpaid trend</span>
									</div>
									<div class="grey persent-count" id="invoice-overview-pct">
										<?php
										if ($invoicePaidPctChange > 0) {
											echo "<span style=\"color:green;\">+" . (int) $invoicePaidPctChange . "%</span> vs last month";
										} elseif ($invoicePaidPctChange < 0) {
											echo "<span style=\"color:red;\">" . abs((int) $invoicePaidPctChange) . "%</span> vs last month";
										} else {
											echo '0% vs last month';
										}
										?>
									</div>
								</div>
								<a href="<?php echo $url; ?>staff/invoices" class="widget-card__stretch-link" aria-label="<?php echo htmlspecialchars($lang['View Invoices'], ENT_QUOTES, 'UTF-8'); ?>"></a>
							</div>
    <?php
}

function renderStaffDashOpenProjectCard($url, $lang, $count, $pctChange, array $sparkProjects) {
    ?>
							<div class="widget-card dash-counter stat-spark-card stat-spark-card--projects widget-card--linked">
								<div class="grey d-flex align-items-center col-gap-5">
									<span><?php echo $lang['Open Project']; ?></span>
									<i style="color:#888;cursor:pointer;" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($lang['This count shows the number of open projects that were active at any point during this month.'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo ts_icon('info', 'w-6'); ?></i>
								</div>
								<div class="counts dash-rttb"><?php echo (int) $count; ?></div>
								<div class="grey persent-count">
									<?php
									if ($pctChange > 0) {
										echo '<span style="color:green;">' . ts_icon('arrow-up-right', 'w-4') . ' ' . (int) $pctChange . '%</span> vs last month';
									} elseif ($pctChange < 0) {
										echo '<span style="color:red;">' . ts_icon('arrow-down-right', 'w-4') . ' ' . abs((int) $pctChange) . '%</span> vs last month';
									} else {
										echo ts_icon('arrows-up-down', 'w-4') . ' 0% vs last month';
									}
									?>
								</div>
								<?php renderAdminStatSparkline('projects', $sparkProjects); ?>
								<a href="<?php echo $url; ?>staff/projects" class="widget-card__stretch-link" aria-label="<?php echo htmlspecialchars($lang['Open Project'], ENT_QUOTES, 'UTF-8'); ?>"></a>
							</div>
    <?php
}

function renderStaffDashOpenTaskCard($url, $lang, $count, $pctChange, array $sparkTasks) {
    ?>
							<div class="widget-card dash-counter stat-spark-card stat-spark-card--tasks widget-card--linked">
								<div class="grey d-flex align-items-center col-gap-5">
									<span><?php echo $lang['Open Task']; ?></span>
									<i style="color:#888;cursor:pointer;" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($lang['This count shows the number of open task that were active at any point during this month.'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo ts_icon('info', 'w-6'); ?></i>
								</div>
								<div class="counts dash-rttb"><?php echo (int) $count; ?></div>
								<div class="grey persent-count">
									<?php
									if ($pctChange > 0) {
										echo '<span style="color:green;">' . ts_icon('arrow-up-right', 'w-4') . ' ' . (int) $pctChange . '%</span> vs last month';
									} elseif ($pctChange < 0) {
										echo '<span style="color:red;">' . ts_icon('arrow-down-right', 'w-4') . ' ' . abs((int) $pctChange) . '%</span> vs last month';
									} else {
										echo ts_icon('arrows-up-down', 'w-4') . ' 0% vs last month';
									}
									?>
								</div>
								<?php renderAdminStatSparkline('tasks', $sparkTasks); ?>
								<a href="<?php echo $url; ?>staff/kanban" class="widget-card__stretch-link" aria-label="<?php echo htmlspecialchars($lang['Open Task'], ENT_QUOTES, 'UTF-8'); ?>"></a>
							</div>
    <?php
}

// Get current user data
$id = $session->userId;
$user = isset($userb) && $userb ? $userb : User::findById((int)$id);
$username = $user->firstName;
$email = $user->email;
$account_stat = $user->status;
$month = date('m');
$curr_year = date("Y");

$hideAttendanceForStaffUser = isset($user->attendance_disabled) && (int) $user->attendance_disabled === 1;
$canUseAttendanceWidgets = (
    $attendanceModuleEnabled
    && has_permission('attendance_view')
    && !$hideAttendanceForStaffUser
);
$staffDashAttendanceWithInvoice = $canUseAttendanceWidgets && $canUseInvoiceWidgets;

// Handle note saving
if (isset($_POST['savenote'])) {
    $usera = user::findById((int)$session->userId);
    $usera->id = $session->userId;
    $usera->note = htmlspecialchars($_POST['snote']); // Sanitize input
    if ($usera->save()) {
        header("Location: index.php");
        exit();
    }
}

// Dashboard counters — aggregate queries (60s session cache per user)
$dashCountCacheKey = 'crm_staff_dash_counts_v2_' . (int) $id;
$dashCountsCached = isset($_SESSION[$dashCountCacheKey]) && is_array($_SESSION[$dashCountCacheKey])
    && (time() - (int) ($_SESSION[$dashCountCacheKey]['ts'] ?? 0)) < 60;

$dashCurrencyCode = 'USD';
$dashCurrencySymbol = '$';
$sysCurrencyRaw = trim((string) ($settingsForModules->system_currency ?? 'USD,$'));
if ($sysCurrencyRaw !== '') {
    $sysParts = explode(',', $sysCurrencyRaw);
    $dashCurrencyCode = trim($sysParts[0]) !== '' ? trim($sysParts[0]) : 'USD';
    $dashCurrencySymbol = isset($sysParts[1]) && trim($sysParts[1]) !== '' ? trim($sysParts[1]) : $dashCurrencyCode;
}
$dashCurrencyCodeEsc = $database->escapeValue($dashCurrencyCode);
$dashCurrencySql = " AND (currency LIKE '{$dashCurrencyCodeEsc},%' OR currency = '{$dashCurrencyCodeEsc}' OR currency LIKE '%,{$dashCurrencyCodeEsc}')";

$paid_total_count = 0;
$recvable_count = 0;
$this_month_count = 0;

if ($dashCountsCached) {
    $c = $_SESSION[$dashCountCacheKey];
    $total_projects = (int) ($c['total_projects'] ?? 0);
    $projects_ip = (int) ($c['projects_ip'] ?? 0);
    $projects_c = (int) ($c['projects_c'] ?? 0);
    $staff_mem = (int) ($c['staff_mem'] ?? 0);
    $clients_mem = (int) ($c['clients_mem'] ?? 0);
    $recvable_count = (int) ($c['recvable_count'] ?? 0);
    $paid_total_count = (int) ($c['paid_total_count'] ?? 0);
    $this_month_count = (int) ($c['this_month_count'] ?? 0);
    if (!empty($c['dash_currency_code'])) {
        $dashCurrencyCode = (string) $c['dash_currency_code'];
    }
    if (!empty($c['dash_currency_symbol'])) {
        $dashCurrencySymbol = (string) $c['dash_currency_symbol'];
    }
    $dashCurrencyCodeEsc = $database->escapeValue($dashCurrencyCode);
    $dashCurrencySql = " AND (currency LIKE '{$dashCurrencyCodeEsc},%' OR currency = '{$dashCurrencyCodeEsc}' OR currency LIKE '%,{$dashCurrencyCodeEsc}')";
} else {
$project_scope_sql = has_permission('project_view_all')
    ? "archive = 0 AND trash != 1"
    : "find_in_set(" . (int)$id . ", s_ids) AND archive = 0 AND trash != 1";
$project_stats = $database->fetchArray($database->query(
    "SELECT COUNT(*) AS total,
            SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) AS in_progress,
            SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS completed
     FROM projects
     WHERE " . $project_scope_sql
));
$total_projects = (int)($project_stats['total'] ?? 0);
$projects_ip = (int)($project_stats['in_progress'] ?? 0);
$projects_c = (int)($project_stats['completed'] ?? 0);

$user_stats = $database->fetchArray($database->query(
    "SELECT SUM(CASE WHEN accountStatus = 3 THEN 1 ELSE 0 END) AS staff_count,
            SUM(CASE WHEN accountStatus = 2 THEN 1 ELSE 0 END) AS client_count
     FROM users"
));
$staff_mem = (int)($user_stats['staff_count'] ?? 0);
$clients_mem = (int)($user_stats['client_count'] ?? 0);

if ($canUseInvoiceWidgets) {
    $invoice_stats = $database->fetchArray($database->query(
        "SELECT SUM(CASE WHEN status = '0' THEN 1 ELSE 0 END) AS unpaid_count,
                SUM(CASE WHEN status = '1' THEN 1 ELSE 0 END) AS paid_count,
                SUM(CASE WHEN status = '1'
                         AND YEAR(releaseDate) = " . (int)$curr_year . "
                         AND MONTH(releaseDate) = " . (int)$month . " THEN 1 ELSE 0 END) AS paid_this_month_count
         FROM milestones
         WHERE 1=1 {$dashCurrencySql}"
    ));
    $recvable_count = (int)($invoice_stats['unpaid_count'] ?? 0);
    $paid_total_count = (int)($invoice_stats['paid_count'] ?? 0);
    $this_month_count = (int)($invoice_stats['paid_this_month_count'] ?? 0);
}

$_SESSION[$dashCountCacheKey] = [
    'ts' => time(),
    'total_projects' => $total_projects,
    'projects_ip' => $projects_ip,
    'projects_c' => $projects_c,
    'staff_mem' => $staff_mem,
    'clients_mem' => $clients_mem,
    'recvable_count' => $recvable_count,
    'paid_total_count' => $paid_total_count,
    'this_month_count' => $this_month_count,
    'dash_currency_code' => $dashCurrencyCode,
    'dash_currency_symbol' => $dashCurrencySymbol,
];
}

$invoice_overview_total = (int) $recvable_count + (int) $paid_total_count;
$sparkFrom = $database->escapeValue(date('Y-m-01', strtotime('-11 months')));
$sparkUid = (int)$id;
$sparkClients = statSparklineSeriesFromQuery(
    $database,
    "SELECT DATE_FORMAT(regDate, '%Y-%m') AS ym, COUNT(*) AS c
     FROM users
     WHERE accountStatus = 2 AND regDate >= '{$sparkFrom}'
     GROUP BY ym"
);
$sparkStaff = statSparklineSeriesFromQuery(
    $database,
    "SELECT DATE_FORMAT(regDate, '%Y-%m') AS ym, COUNT(*) AS c
     FROM users
     WHERE accountStatus = 3 AND regDate >= '{$sparkFrom}'
     GROUP BY ym"
);
$sparkUnpaid = $canUseInvoiceWidgets ? statSparklineSeriesFromQuery(
    $database,
    "SELECT DATE_FORMAT(" . statSparklineSqlMilestoneEffectiveDate() . ", '%Y-%m') AS ym, COUNT(*) AS c
     FROM milestones
     WHERE status = '0'
       AND " . statSparklineSqlMilestoneEffectiveDate() . " IS NOT NULL
       AND " . statSparklineSqlMilestoneEffectiveDate() . " >= '{$sparkFrom}'
       {$dashCurrencySql}
     GROUP BY ym"
) : array_fill(0, 12, 0);
$sparkPaid = $canUseInvoiceWidgets ? statSparklineSeriesFromQuery(
    $database,
    "SELECT DATE_FORMAT(releaseDate, '%Y-%m') AS ym, COUNT(*) AS c
     FROM milestones
     WHERE status = '1'
       AND " . statSparklineSqlUnixValid('releaseDate') . "
       AND releaseDate >= '{$sparkFrom}'
       {$dashCurrencySql}
     GROUP BY ym"
) : array_fill(0, 12, 0);
$sparkSession = statSparklineSeriesFromQuery(
    $database,
    "SELECT DATE_FORMAT(attempt_time, '%Y-%m') AS ym, COUNT(*) AS c
     FROM login_attempts
     WHERE user_id = {$sparkUid} AND type = 'login' AND attempt_time >= '{$sparkFrom}'
     GROUP BY ym"
);

$project_spark_scope = has_permission('project_view_all')
    ? "archive = 0 AND trash != 1"
    : "find_in_set({$sparkUid}, s_ids) AND archive = 0 AND trash != 1";
$sparkProjects = statSparklineSeriesFromQuery(
    $database,
    "SELECT DATE_FORMAT(start_time, '%Y-%m') AS ym, COUNT(*) AS c
     FROM projects
     WHERE {$project_spark_scope}
       AND start_time >= '{$sparkFrom}'
     GROUP BY ym"
);
$task_spark_scope = has_permission('task_view_all') ? '' : " AND FIND_IN_SET({$sparkUid}, assigned_to) > 0";
$sparkTasks = statSparklineSeriesFromQuery(
    $database,
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS c
     FROM tasks
     WHERE created_at >= '{$sparkFrom}'{$task_spark_scope}
     GROUP BY ym"
);

// Counter meta (clients / staff) — same pattern as admin dashboard
$clientsNewThisMonth = !empty($sparkClients) ? (int) end($sparkClients) : 0;
$clientsNewLastMonth = (count($sparkClients) > 1) ? (int) $sparkClients[count($sparkClients) - 2] : 0;
$clientsPctChange = $clientsNewLastMonth > 0
    ? (int) round((($clientsNewThisMonth - $clientsNewLastMonth) / $clientsNewLastMonth) * 100)
    : ($clientsNewThisMonth > 0 ? 100 : 0);
$staffNewThisMonth = !empty($sparkStaff) ? (int) end($sparkStaff) : 0;
require_once __DIR__ . '/../includes/user_presence.php';
$staffPresenceCutoff = time() - (int) USER_PRESENCE_IDLE_SECONDS;
$staffActiveRow = $database->fetchArray($database->query(
    "SELECT COUNT(*) AS c
     FROM users
     WHERE accountStatus = 3
       AND session_status = 'online'
       AND last_seen > 0
       AND last_seen >= " . (int) $staffPresenceCutoff
));
$staffActiveToday = (int) ($staffActiveRow['c'] ?? 0);

$sessionLoginsThis = !empty($sparkSession) ? (int) end($sparkSession) : 0;
$sessionLoginsLast = (count($sparkSession) > 1) ? (int) $sparkSession[count($sparkSession) - 2] : 0;
$sessionPctChange = $sessionLoginsLast > 0
    ? (int) round((($sessionLoginsThis - $sessionLoginsLast) / $sessionLoginsLast) * 100)
    : ($sessionLoginsThis > 0 ? 100 : 0);

$lastLoginRow = $database->fetchArray($database->query(
    "SELECT attempt_time FROM login_attempts
     WHERE user_id = {$sparkUid} AND type = 'login'
     ORDER BY attempt_time DESC LIMIT 1"
));
$lastLoginLabel = !empty($lastLoginRow['attempt_time'])
    ? date('M j, Y', strtotime($lastLoginRow['attempt_time']))
    : 'No data';
$lastSessionDateDisplay = $lastLoginLabel;
$lastSessionDurationDisplay = 'N/A';
$lastSessionIsOnline = false;
if (!empty($lastLoginRow['attempt_time'])) {
    $lastLoginTime = (string) $lastLoginRow['attempt_time'];
    $loginTime = strtotime($lastLoginTime);
    $logoutTs = null;
    if (isset($connect) && $connect) {
        $logoutStmt = $connect->prepare("SELECT attempt_time FROM login_attempts WHERE user_id = ? AND type = 'logout' AND attempt_time > ? ORDER BY attempt_time ASC LIMIT 1");
        if ($logoutStmt) {
            $logoutStmt->bind_param('is', $sparkUid, $lastLoginTime);
            $logoutStmt->execute();
            $logoutRes = $logoutStmt->get_result();
            if ($logoutRes && ($logoutRow = $logoutRes->fetch_assoc())) {
                $logoutTs = strtotime($logoutRow['attempt_time']);
            }
            $logoutStmt->close();
        }
    }
    $lastSessionDurationDisplay = function_exists('user_presence_activity_duration_label')
        ? user_presence_activity_duration_label(
            $loginTime,
            $logoutTs,
            $user->last_seen ?? 0,
            $user->session_status ?? 'offline'
        )
        : 'N/A';
    $lastSessionIsOnline = ($lastSessionDurationDisplay === 'Active');
}

// My Work Today — personal assigned tasks (open + completed today)
$myWorkAssigned = 0;
$myWorkCompleted = 0;
$myWorkDueToday = 0;
$myWorkOverdue = 0;
$myWorkOnTimePct = 0;
$myWorkOnTimePctChange = 0;
$sparkWorkOnTime = array_fill(0, 12, 0);
$sparkWorkLate = array_fill(0, 12, 0);
$myWorkWhere = Task::kanbanMyTasksWhere((int) $id, 'tasks') . ' AND ' . Task::activeTasksWhere('tasks');
$myWorkOnTimeSql = "(NOT " . reports_sql_valid_date('due_date') . " OR DATE(completed_at) <= DATE(due_date))";
$myWorkLateSql = "(" . reports_sql_valid_date('due_date') . " AND DATE(completed_at) > DATE(due_date))";
$myWorkStatsRow = method_exists($database, 'querySoft')
    ? $database->querySoft(
    "SELECT
        SUM(CASE WHEN status != 'done' THEN 1 ELSE 0 END) AS open_c,
        SUM(CASE WHEN status = 'done' AND completed_at IS NOT NULL AND DATE(completed_at) = CURDATE() THEN 1 ELSE 0 END) AS done_today_c,
        SUM(CASE WHEN status != 'done' AND " . reports_sql_valid_date('due_date') . " AND DATE(due_date) = CURDATE() THEN 1 ELSE 0 END) AS due_today_c,
        SUM(CASE WHEN status != 'done' AND " . reports_sql_valid_date('due_date') . " AND DATE(due_date) < CURDATE() THEN 1 ELSE 0 END) AS overdue_c
     FROM tasks
     WHERE {$myWorkWhere}"
    )
    : $database->query(
        "SELECT
        SUM(CASE WHEN status != 'done' THEN 1 ELSE 0 END) AS open_c,
        SUM(CASE WHEN status = 'done' AND completed_at IS NOT NULL AND DATE(completed_at) = CURDATE() THEN 1 ELSE 0 END) AS done_today_c,
        SUM(CASE WHEN status != 'done' AND " . reports_sql_valid_date('due_date') . " AND DATE(due_date) = CURDATE() THEN 1 ELSE 0 END) AS due_today_c,
        SUM(CASE WHEN status != 'done' AND " . reports_sql_valid_date('due_date') . " AND DATE(due_date) < CURDATE() THEN 1 ELSE 0 END) AS overdue_c
     FROM tasks
     WHERE {$myWorkWhere}"
    );
$myWorkStats = $myWorkStatsRow ? ($database->fetchArray($myWorkStatsRow) ?: array()) : array();
$myWorkOpen = (int) ($myWorkStats['open_c'] ?? 0);
$myWorkCompleted = (int) ($myWorkStats['done_today_c'] ?? 0);
$myWorkDueToday = (int) ($myWorkStats['due_today_c'] ?? 0);
$myWorkOverdue = (int) ($myWorkStats['overdue_c'] ?? 0);
$myWorkAssigned = $myWorkOpen + $myWorkCompleted;

$myWorkMonthStart = $database->escapeValue(date('Y-m-01'));
$myWorkMonthEnd = $database->escapeValue(date('Y-m-t'));
$myWorkLastStart = $database->escapeValue(date('Y-m-01', strtotime('-1 month')));
$myWorkLastEnd = $database->escapeValue(date('Y-m-t', strtotime('-1 month')));
$myWorkPerfThis = $database->fetchArray($database->query(
    "SELECT COUNT(*) AS total_c,
            SUM(CASE WHEN {$myWorkOnTimeSql} THEN 1 ELSE 0 END) AS on_time_c
     FROM tasks
     WHERE {$myWorkWhere}
       AND status = 'done'
       AND completed_at IS NOT NULL
       AND completed_at >= '{$myWorkMonthStart}'
       AND completed_at <= '{$myWorkMonthEnd} 23:59:59'"
));
$myWorkPerfLast = $database->fetchArray($database->query(
    "SELECT COUNT(*) AS total_c,
            SUM(CASE WHEN {$myWorkOnTimeSql} THEN 1 ELSE 0 END) AS on_time_c
     FROM tasks
     WHERE {$myWorkWhere}
       AND status = 'done'
       AND completed_at IS NOT NULL
       AND completed_at >= '{$myWorkLastStart}'
       AND completed_at <= '{$myWorkLastEnd} 23:59:59'"
));
$myWorkDoneThisMonth = (int) ($myWorkPerfThis['total_c'] ?? 0);
$myWorkDoneLastMonth = (int) ($myWorkPerfLast['total_c'] ?? 0);
$myWorkOnTimeThisMonth = (int) ($myWorkPerfThis['on_time_c'] ?? 0);
$myWorkOnTimeLastMonth = (int) ($myWorkPerfLast['on_time_c'] ?? 0);
$myWorkOnTimePctThis = $myWorkDoneThisMonth > 0
    ? (int) round(($myWorkOnTimeThisMonth / $myWorkDoneThisMonth) * 100)
    : 0;
$myWorkOnTimePctLast = $myWorkDoneLastMonth > 0
    ? (int) round(($myWorkOnTimeLastMonth / $myWorkDoneLastMonth) * 100)
    : 0;
$myWorkOnTimePct = $myWorkOnTimePctThis;
$myWorkOnTimePctChange = $myWorkOnTimePctThis - $myWorkOnTimePctLast;

$sparkWorkOnTime = statSparklineSeriesFromQuery(
    $database,
    "SELECT DATE_FORMAT(completed_at, '%Y-%m') AS ym, COUNT(*) AS c
     FROM tasks
     WHERE {$myWorkWhere}
       AND status = 'done'
       AND completed_at IS NOT NULL
       AND completed_at >= '{$sparkFrom}'
       AND {$myWorkOnTimeSql}
     GROUP BY ym"
);
$sparkWorkLate = statSparklineSeriesFromQuery(
    $database,
    "SELECT DATE_FORMAT(completed_at, '%Y-%m') AS ym, COUNT(*) AS c
     FROM tasks
     WHERE {$myWorkWhere}
       AND status = 'done'
       AND completed_at IS NOT NULL
       AND completed_at >= '{$sparkFrom}'
       AND {$myWorkLateSql}
     GROUP BY ym"
);

$myWorkCardData = [
    'assigned' => $myWorkAssigned,
    'completed' => $myWorkCompleted,
    'due_today' => $myWorkDueToday,
    'overdue' => $myWorkOverdue,
];

$dashTimeLoggedSec = tasksession_user_logged_seconds_today((int) $id, isset($connect) ? $connect : null);
$dashTaskEntriesCount = tasksession_user_task_entries_count_today((int) $id, isset($connect) ? $connect : null);
$dashShiftCard = [
    'available' => false,
    'elapsed_sec' => 0,
    'shift_total_sec' => 0,
    'remaining_sec' => 0,
    'progress_pct' => 0.0,
    'checked_in' => false,
    'checked_out' => false,
    'check_in_ts' => 0,
    'shift_label' => '',
    'meta_note' => $lang['No shift assigned'] ?? 'No shift assigned',
    'meta_note_color' => '#888',
];
if ($attendanceModuleEnabled) {
    $__att = __DIR__ . '/../includes/attendance/helpers.php';
    if (is_file($__att)) { require_once $__att; }
    if (function_exists('attendance_dashboard_shift_card_data')) {
        $dashShiftCard = attendance_dashboard_shift_card_data((int) $id, is_array($lang) ? $lang : [], isset($connect) ? $connect : null);
    }
}
$dashTaskGraphTargetSec = (int) ($dashShiftCard['shift_total_sec'] ?? 0) > 0
    ? (int) $dashShiftCard['shift_total_sec']
    : tasksession_user_daily_target_seconds((int) $id);

$unpaidAmount = 0;
$paidAmountAll = 0;
$invoicePaidPctChange = 0;
$fmtDashMoney = static function ($amount, $symbol) {
    return $symbol . number_format((float) $amount, 2, '.', ',');
};

// Attendance overview (personal) when module + permission + not disabled for this staff
$attendancePresentCount = 0;
$attendanceLateCount = 0;
$attendanceAbsentCount = 0;
$attendanceOverviewTotal = 0;
$attendancePctChange = 0;
$attendanceDeductionAmount = 0.0;
$attendanceSalaryCurrencySymbol = $dashCurrencySymbol;
$sparkAttendancePresent = array_fill(0, 12, 0);
$sparkAttendanceAbsent = array_fill(0, 12, 0);
$attPresentStatuses = "'present','half_day','early_exit','work_from_home','on_duty'";
$attLateStatuses = "'late'";
$attAbsentStatuses = "'absent','missed_punch'";
$attendanceCardData = [
    'present' => 0,
    'late' => 0,
    'absent' => 0,
    'total' => 0,
    'pct_change' => 0,
    'deduction_amount' => 0.0,
    'currency_symbol' => $dashCurrencySymbol,
    'spark_present' => $sparkAttendancePresent,
    'spark_absent' => $sparkAttendanceAbsent,
];
if ($canUseAttendanceWidgets) {
    $__att = __DIR__ . '/../includes/attendance/helpers.php';
if (is_file($__att)) { require_once $__att; }
    $attMonthStart = $database->escapeValue(date('Y-m-01'));
    $attMonthEnd = $database->escapeValue(date('Y-m-t'));
    $attLastStart = $database->escapeValue(date('Y-m-01', strtotime('-1 month')));
    $attLastEnd = $database->escapeValue(date('Y-m-t', strtotime('-1 month')));

    $attThis = $database->fetchArray($database->query(
        "SELECT
            SUM(CASE WHEN status IN ({$attPresentStatuses}) THEN 1 ELSE 0 END) AS present_c,
            SUM(CASE WHEN status IN ({$attLateStatuses}) THEN 1 ELSE 0 END) AS late_c,
            SUM(CASE WHEN status IN ({$attAbsentStatuses}) THEN 1 ELSE 0 END) AS absent_c,
            COUNT(*) AS total_c
         FROM attendance_records
         WHERE user_id = {$sparkUid}
           AND attendance_date >= '{$attMonthStart}'
           AND attendance_date <= '{$attMonthEnd}'"
    ));
    $attendancePresentCount = (int) ($attThis['present_c'] ?? 0);
    $attendanceLateCount = (int) ($attThis['late_c'] ?? 0);
    $attendanceAbsentCount = (int) ($attThis['absent_c'] ?? 0);
    $attendanceOverviewTotal = (int) ($attThis['total_c'] ?? 0);

    $attSalary = attendance_salary_summary_for_range(
        $sparkUid,
        date('Y-m-01'),
        date('Y-m-t'),
        $attendanceAbsentCount
    );
    $attendanceDeductionAmount = (float) ($attSalary['deduction_amount'] ?? 0);
    $attSalaryCurrency = attendance_resolve_salary_currency(null, $sparkUid);
    $attendanceSalaryCurrencySymbol = (string) ($attSalaryCurrency['symbol'] ?? $dashCurrencySymbol);

    $attLast = $database->fetchArray($database->query(
        "SELECT SUM(CASE WHEN status IN ({$attPresentStatuses}) THEN 1 ELSE 0 END) AS present_c
         FROM attendance_records
         WHERE user_id = {$sparkUid}
           AND attendance_date >= '{$attLastStart}'
           AND attendance_date <= '{$attLastEnd}'"
    ));
    $attPresentLast = (int) ($attLast['present_c'] ?? 0);
    $attendancePctChange = $attPresentLast > 0
        ? (int) round((($attendancePresentCount - $attPresentLast) / $attPresentLast) * 100)
        : ($attendancePresentCount > 0 ? 100 : 0);

    $sparkAttendancePresent = statSparklineSeriesFromQuery(
        $database,
        "SELECT DATE_FORMAT(attendance_date, '%Y-%m') AS ym, COUNT(*) AS c
         FROM attendance_records
         WHERE user_id = {$sparkUid}
           AND status IN ({$attPresentStatuses})
           AND attendance_date >= '{$sparkFrom}'
         GROUP BY ym"
    );
    $sparkAttendanceAbsent = statSparklineSeriesFromQuery(
        $database,
        "SELECT DATE_FORMAT(attendance_date, '%Y-%m') AS ym, COUNT(*) AS c
         FROM attendance_records
         WHERE user_id = {$sparkUid}
           AND status IN ({$attAbsentStatuses})
           AND attendance_date >= '{$sparkFrom}'
         GROUP BY ym"
    );

    $attendanceCardData = [
        'present' => $attendancePresentCount,
        'late' => $attendanceLateCount,
        'absent' => $attendanceAbsentCount,
        'total' => $attendanceOverviewTotal,
        'pct_change' => $attendancePctChange,
        'deduction_amount' => $attendanceDeductionAmount,
        'currency_symbol' => $attendanceSalaryCurrencySymbol,
        'spark_present' => $sparkAttendancePresent,
        'spark_absent' => $sparkAttendanceAbsent,
        'fmt_money' => $fmtDashMoney,
    ];
}

$unpaidAmount = 0;
$paidAmountAll = 0;
$invoicePaidPctChange = 0;
if ($canUseInvoiceWidgets) {
    $dashMetaMonthStart = date('Y-m-01');
    $dashMetaMonthEnd = date('Y-m-t');
    $dashMetaLastStart = date('Y-m-01', strtotime('-1 month'));
    $dashMetaLastEnd = date('Y-m-t', strtotime('-1 month'));
    $currencyLike = $database->escapeValue($dashCurrencyCode);

    $unpaidMilestones = milestone::findBySql(
        "SELECT * FROM milestones WHERE (status = '0' OR status = 0)
         AND (currency LIKE '{$currencyLike},%' OR currency = '{$currencyLike}' OR currency LIKE '%,{$currencyLike}')"
    );
    if (is_array($unpaidMilestones)) {
        foreach ($unpaidMilestones as $um) {
            $unpaidAmount += milestone_calculate_invoice_total($um);
        }
    }
    $paidMilestonesAll = milestone::findBySql(
        "SELECT * FROM milestones WHERE (status = '1' OR status = 1)
         AND (currency LIKE '{$currencyLike},%' OR currency = '{$currencyLike}' OR currency LIKE '%,{$currencyLike}')"
    );
    if (is_array($paidMilestonesAll)) {
        foreach ($paidMilestonesAll as $pm) {
            $paidAmountAll += milestone_calculate_invoice_total($pm);
        }
    }
    $paidThisAmount = 0;
    $paidThisMilestones = milestone::findBySql(
        "SELECT * FROM milestones
         WHERE (status = '1' OR status = 1)
           AND releaseDate IS NOT NULL
           AND releaseDate >= '" . $database->escapeValue($dashMetaMonthStart) . "'
           AND releaseDate <= '" . $database->escapeValue($dashMetaMonthEnd) . "'
           AND (currency LIKE '{$currencyLike},%' OR currency = '{$currencyLike}' OR currency LIKE '%,{$currencyLike}')"
    );
    if (is_array($paidThisMilestones)) {
        foreach ($paidThisMilestones as $pm) {
            $paidThisAmount += milestone_calculate_invoice_total($pm);
        }
    }
    $paidLastAmount = 0;
    $paidLastMilestones = milestone::findBySql(
        "SELECT * FROM milestones
         WHERE (status = '1' OR status = 1)
           AND releaseDate IS NOT NULL
           AND releaseDate >= '" . $database->escapeValue($dashMetaLastStart) . "'
           AND releaseDate <= '" . $database->escapeValue($dashMetaLastEnd) . "'
           AND (currency LIKE '{$currencyLike},%' OR currency = '{$currencyLike}' OR currency LIKE '%,{$currencyLike}')"
    );
    if (is_array($paidLastMilestones)) {
        foreach ($paidLastMilestones as $pm) {
            $paidLastAmount += milestone_calculate_invoice_total($pm);
        }
    }
    $invoicePaidPctChange = $paidLastAmount > 0
        ? (int) round((($paidThisAmount - $paidLastAmount) / $paidLastAmount) * 100)
        : ($paidThisAmount > 0 ? 100 : 0);
}

// Chart placeholders — analytics.js loads real totals via ajax/monthly_range.php on DOMContentLoaded
$present_year = date('Y');
$month_labels = [];
$monthly_earnings = [];
$monthly_unpaid = [];
for ($m = 1; $m <= 12; $m++) {
    $month_labels[] = $present_year . ' ' . date('M', mktime(0, 0, 0, $m, 1));
    $monthly_earnings[] = 0;
    $monthly_unpaid[] = 0;
}

// Task and project statistics
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$last_month_start = date('Y-m-01', strtotime('-1 month'));
$last_month_end = date('Y-m-t', strtotime('-1 month'));

$task_scope_sql = has_permission('task_view_all') ? '' : " AND FIND_IN_SET(" . (int)$id . ", assigned_to) > 0";

// Open tasks (COUNT only)
$open_tasks_this_row = $database->fetchArray($database->query(
    "SELECT COUNT(*) AS c FROM tasks WHERE status = 'todo' AND start_date <= '" . $database->escapeValue($month_end) . "' AND due_date >= '" . $database->escapeValue($month_start) . "'" . $task_scope_sql
));
$open_tasks_last_row = $database->fetchArray($database->query(
    "SELECT COUNT(*) AS c FROM tasks WHERE status = 'todo' AND start_date <= '" . $database->escapeValue($last_month_end) . "' AND due_date >= '" . $database->escapeValue($last_month_start) . "'" . $task_scope_sql
));
$this_count = (int)($open_tasks_this_row['c'] ?? 0);
$last_count = (int)($open_tasks_last_row['c'] ?? 0);
$percent_change = $last_count > 0 ?
    round((($this_count - $last_count) / $last_count) * 100, 2) :
    ($this_count > 0 ? 100 : 0);

// Open projects (COUNT only)
$open_project_base = "status = 0 AND start_time <= '" . $database->escapeValue($month_end) . "' AND end_time >= '" . $database->escapeValue($month_start) . "' AND archive = 0 AND trash != 1";
$open_project_last_base = "status = 0 AND start_time <= '" . $database->escapeValue($last_month_end) . "' AND end_time >= '" . $database->escapeValue($last_month_start) . "' AND archive = 0 AND trash != 1";
if (!has_permission('project_view_all')) {
    $open_project_base = "find_in_set(" . (int)$id . ", s_ids) AND " . $open_project_base;
    $open_project_last_base = "find_in_set(" . (int)$id . ", s_ids) AND " . $open_project_last_base;
}
$open_projects_this_row = $database->fetchArray($database->query("SELECT COUNT(*) AS c FROM projects WHERE " . $open_project_base));
$open_projects_last_row = $database->fetchArray($database->query("SELECT COUNT(*) AS c FROM projects WHERE " . $open_project_last_base));
$open_projects_this_count = (int)($open_projects_this_row['c'] ?? 0);
$open_projects_last_count = (int)($open_projects_last_row['c'] ?? 0);
$open_projects_percent_change = $open_projects_last_count > 0 ?
    round((($open_projects_this_count - $open_projects_last_count) / $open_projects_last_count) * 100, 2) :
    ($open_projects_this_count > 0 ? 100 : 0);

// Helper function for color generation
function getColorById($id) {
    $colors = ['#a81bcb', '#2a5fb7', '#36b9cc', '#42b72a', '#fe6094', '#4db6ad', '#fd7e14', '#20c997'];
    return $colors[$id % count($colors)];
}

// Fetch private notes
$user_id = $_SESSION['userId'];
$notes = [];
if (!empty($user_id)) {
    $sql = "SELECT * FROM private_notes WHERE user_id = " . (int)$user_id . " ORDER BY updated_at DESC LIMIT 4";
    $result = $database->query($sql);
    while ($row = $database->fetchArray($result)) {
        // Decrypt content for display
        if (!empty($row['content'])) {
            $row['content'] = decryptString($row['content']);
        }
        $notes[] = $row;
    }
}

$staffDashRecentActivityItems = [];
$dashboardUsersById = [];
$staffDashActivityNotifs = Notifications::getUserNotificationsGrouped((int) $session->userId, 5, 0);
if (is_array($staffDashActivityNotifs)) {
    foreach ($staffDashActivityNotifs as $staffDashActivityNotif) {
        $staffDashFeedItem = adminDashFeedItemFromNotification($staffDashActivityNotif, $lang);
        $staffDashRecentActivityItems[] = activityPageApplyRoleToFeedItem($staffDashFeedItem, 'staff/', (string) $url);
    }
}
$staffDashLeadsPipeline = dashWidgetLeadsPipelineBuild($lang, 'leads');

$staffDashUserId = (int) $id;
$staffMyTasksBaseWhere = Task::kanbanMyTasksWhere($staffDashUserId, 'tasks')
    . ' AND ' . Task::activeTasksWhere('tasks')
    . " AND tasks.status != 'done'";
$staffMyTasksDueValid = reports_sql_valid_date('tasks.due_date');
$staff_my_tasks_today = Task::findBySqlSoft(
    "SELECT tasks.*, p.project_title
     FROM tasks
     LEFT JOIN projects p ON p.p_id = tasks.project_id
     WHERE {$staffMyTasksBaseWhere}
       AND {$staffMyTasksDueValid}
       AND DATE(tasks.due_date) <= CURDATE()
     ORDER BY tasks.due_date ASC, tasks.created_at DESC
     LIMIT 4"
);
$staffDashWeekEnd = date('Y-m-d', strtotime('sunday this week'));
$staff_my_tasks_week = Task::findBySqlSoft(
    "SELECT tasks.*, p.project_title
     FROM tasks
     LEFT JOIN projects p ON p.p_id = tasks.project_id
     WHERE {$staffMyTasksBaseWhere}
       AND {$staffMyTasksDueValid}
       AND DATE(tasks.due_date) > CURDATE()
       AND DATE(tasks.due_date) <= '{$staffDashWeekEnd}'
     ORDER BY tasks.due_date ASC, tasks.created_at DESC
     LIMIT 4"
);
if (!is_array($staff_my_tasks_today)) {
    $staff_my_tasks_today = [];
}
if (!is_array($staff_my_tasks_week)) {
    $staff_my_tasks_week = [];
}
dashWidgetPreloadTaskAssignees(array_merge($staff_my_tasks_today, $staff_my_tasks_week), $dashboardUsersById);

// Recent projects — prefetch with permission scope and batch related lookups
$recentProjectTaskStats = [];
if (has_permission('project_view_all')) {
    $recentProjects = projects::findBySql("SELECT * FROM projects WHERE archive = 0 AND trash != 1 ORDER BY start_time DESC LIMIT 5");
} else {
    $recentProjects = projects::findBySql("SELECT * FROM projects WHERE find_in_set(" . (int)$id . ", s_ids) AND archive = 0 AND trash != 1 ORDER BY start_time DESC LIMIT 5");
}
$recentProjectIds = [];
$dashboardUserIds = [];
foreach ($recentProjects as $rpPrep) {
    $pid = (int)$rpPrep->p_id;
    if ($pid > 0) {
        $recentProjectIds[] = $pid;
    }
    $mainClientIdPrep = (int)($rpPrep->c_id ?? 0);
    if ($mainClientIdPrep > 0) {
        $dashboardUserIds[$mainClientIdPrep] = true;
    }
    $mainClientFieldPrep = (int)($rpPrep->main_client_id ?? 0);
    if ($mainClientFieldPrep > 0) {
        $dashboardUserIds[$mainClientFieldPrep] = true;
    }
    foreach (array_filter(array_map('intval', explode(',', (string)($rpPrep->s_ids ?? '')))) as $staffIdPrep) {
        if ($staffIdPrep > 0) {
            $dashboardUserIds[$staffIdPrep] = true;
        }
    }
    foreach (array_filter(array_map('intval', explode(',', (string)($rpPrep->c_ids ?? '')))) as $clientIdPrep) {
        if ($clientIdPrep > 0) {
            $dashboardUserIds[$clientIdPrep] = true;
        }
    }
}
if (!empty($recentProjectIds)) {
    $taskStatsResult = $database->query(
        "SELECT project_id,
                COUNT(*) AS task_count,
                SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) AS completed_count
         FROM tasks
         WHERE project_id IN (" . implode(',', $recentProjectIds) . ")
         GROUP BY project_id"
    );
    if ($taskStatsResult) {
        while ($taskStatsRow = $database->fetchArray($taskStatsResult)) {
            $recentProjectTaskStats[(int)$taskStatsRow['project_id']] = $taskStatsRow;
        }
    }
}
if (!empty($dashboardUserIds)) {
    $loadedDashboardUsers = user::findBySql(
        "SELECT * FROM users WHERE id IN (" . implode(',', array_keys($dashboardUserIds)) . ")"
    );
    if ($loadedDashboardUsers) {
        foreach ($loadedDashboardUsers as $loadedDashboardUser) {
            $dashboardUsersById[(int)$loadedDashboardUser->id] = $loadedDashboardUser;
        }
    }
}
?>
<link rel="stylesheet" href="<?php echo $url; ?>assets/css/reports.css">
<div class="page-container staff-dashboard admin-dashboard">
  <div class="container-fluid">
    <div class="row row-eq-height">
      <?php include("../templates/sidebar.php"); ?>
      <div class="page-content dashboard-page" style="padding-bottom:0px !important;">
        <?php include('../templates/top-header.php'); ?>
        <div class="extra-page-pd">
           <?php
           $__aiDash = __DIR__ . '/../includes/ai_dashboard_widget.php';
           if (is_file($__aiDash)) {
               include $__aiDash;
           }
           ?>
				         <!-- Content Row -->
           <div class="row staff-dash-stats-row">
                       <div class="col-md-12 col-lg-4">
                    <div class="widget-card shadow center-align pie-chart">

                              <div class="d-flex justify-content-between align-items-center">
                                    <div class="card-title">
                                        <h3><?php echo $lang['My Projects']; ?></h3>
                                    </div>
                                    <div class="border-btn">
                                        <a href="<?php echo $url; ?>staff/projects">
                                            <?php echo $lang['View all']; ?>
                                        </a>
                                    </div>
                                </div>
                                      <div class="chart-pie mb-2  " style="width: 200px; height: 200px; margin: 0 auto;">
                                        <canvas id="myPieChart" width="350" height="350"></canvas>
				                         <div class="total-pro">
                                         <h2><?php echo $total_projects; ?></h2><span><?php echo $lang['Projects']; ?></span>
                                    </div>
                                </div>
				                       <div class="container counters-bottom">
                                    <div class="row chart-footer justify-content-center col-gap-35 full-col-gap-35-sep ">
                                        <div class=" foot-c-box">
										<div class="d-flex align-items-center justify-content-end col-gap-10">
										        <span class="fctxt " style="text-align: right;"><?php echo $lang['Inprogress Projects']; ?></span> 
	                                         <div class="font-size-30  primary">  
										 <?php echo $projects_ip; ?></div>
                                        </div>
										</div>									
										 <div class="foot-c-box green">
										<div class="d-flex align-items-center col-gap-10">
										 <div class="font-size-30"> <?php echo $projects_c; ?></div>
                                           <span class="fctxt"><?php echo $lang['Completed Projects']; ?></span> 
                                        </div>
                                        </div>
                                    </div>
                                   </div>
                                </div>
                               </div>
                   <div class="col-md-12 col-lg-8">
                       <div class="row counter-align">
										<?php
						$dashCol6Lt = 'col-12 col-lg-6 order-1 order-sm-0 pd-adjust-lt staff-dash-stat-col-half';
						$dashCol6Rt = 'col-12 col-lg-6 order-2 order-sm-0 pd-adjust-rt staff-dash-stat-col-half';
						$dashCol3Time = 'col-6 col-lg-3 order-3 order-sm-0 pd-adjust-lt';
						$dashCol3Shift = 'col-6 col-lg-3 order-4 order-sm-0';
						$dashCol3Project = 'col-6 col-lg-3 order-5 order-sm-0 pd-adjust-lt';
						$dashCol3Task = 'col-6 col-lg-3 order-6 order-sm-0 pd-adjust-rt';
						$dashCol6MyWork = 'col-12 col-lg-6 order-5 order-sm-0 pd-adjust-lt';
						if ($staffDashAttendanceWithInvoice): ?>
						<!-- Row 1: Attendance | Invoice -->
						<div class="<?php echo $dashCol6Lt; ?>">
							<?php renderStaffAttendanceOverviewCard($url, $lang, $attendanceCardData); ?>
									</div>
						<div class="<?php echo $dashCol6Rt; ?>">
							<?php renderStaffInvoiceOverviewCard($url, $lang, $invoice_overview_total, $paid_total_count, $recvable_count, $fmtDashMoney, $paidAmountAll, $unpaidAmount, $dashCurrencySymbol, $sparkPaid, $sparkUnpaid, $invoicePaidPctChange); ?>
								</div>
						<!-- Row 2: Task time | Shift | My work today -->
						<?php renderStaffDashTimerStatCards($url, $lang, $dashTimeLoggedSec, $dashTaskGraphTargetSec, $dashTaskEntriesCount, $dashShiftCard, $dashCol3Time, $dashCol3Shift); ?>
						<div class="<?php echo $dashCol6MyWork; ?>">
							<?php renderStaffMyWorkTodayCard($url, $lang, $myWorkCardData, $sparkWorkOnTime, $sparkWorkLate, $myWorkOnTimePct, $myWorkOnTimePctChange); ?>
							</div>
						<?php elseif ($canUseAttendanceWidgets): ?>
						<!-- Row 1: Attendance | My work today -->
						<div class="<?php echo $dashCol6Lt; ?>">
							<?php renderStaffAttendanceOverviewCard($url, $lang, $attendanceCardData); ?>
						</div>
						<div class="<?php echo $dashCol6Rt; ?>">
							<?php renderStaffMyWorkTodayCard($url, $lang, $myWorkCardData, $sparkWorkOnTime, $sparkWorkLate, $myWorkOnTimePct, $myWorkOnTimePctChange); ?>
								</div>
						<!-- Row 2: Task time | Shift | Open project | Open task -->
						<?php renderStaffDashTimerStatCards($url, $lang, $dashTimeLoggedSec, $dashTaskGraphTargetSec, $dashTaskEntriesCount, $dashShiftCard, $dashCol3Time, $dashCol3Shift); ?>
						<div class="<?php echo $dashCol3Project; ?>">
							<?php renderStaffDashOpenProjectCard($url, $lang, $open_projects_this_count, $open_projects_percent_change, $sparkProjects); ?>
									</div>
						<div class="<?php echo $dashCol3Task; ?>">
							<?php renderStaffDashOpenTaskCard($url, $lang, $this_count, $percent_change, $sparkTasks); ?>
						</div>
						<?php elseif ($canUseInvoiceWidgets): ?>
						<!-- Row 1: My work today | Invoice -->
						<div class="<?php echo $dashCol6Lt; ?>">
							<?php renderStaffMyWorkTodayCard($url, $lang, $myWorkCardData, $sparkWorkOnTime, $sparkWorkLate, $myWorkOnTimePct, $myWorkOnTimePctChange); ?>
                                </div>
						<div class="<?php echo $dashCol6Rt; ?>">
							<?php renderStaffInvoiceOverviewCard($url, $lang, $invoice_overview_total, $paid_total_count, $recvable_count, $fmtDashMoney, $paidAmountAll, $unpaidAmount, $dashCurrencySymbol, $sparkPaid, $sparkUnpaid, $invoicePaidPctChange); ?>
                            </div>
						<!-- Row 2: Task time | Shift | Open project | Open task -->
						<?php renderStaffDashTimerStatCards($url, $lang, $dashTimeLoggedSec, $dashTaskGraphTargetSec, $dashTaskEntriesCount, $dashShiftCard, $dashCol3Time, $dashCol3Shift); ?>
						<div class="<?php echo $dashCol3Project; ?>">
							<?php renderStaffDashOpenProjectCard($url, $lang, $open_projects_this_count, $open_projects_percent_change, $sparkProjects); ?>
                          </div>
						<div class="<?php echo $dashCol3Task; ?>">
							<?php renderStaffDashOpenTaskCard($url, $lang, $this_count, $percent_change, $sparkTasks); ?>
						</div>
						<?php else: ?>
						<!-- Row 1: Session | My work today -->
						<div class="<?php echo $dashCol6Lt; ?>">
							<?php renderStaffSessionCard($url, $lang, $lastSessionDateDisplay, $lastSessionDurationDisplay, $lastSessionIsOnline, $sparkSession, (int) $id); ?>
								</div>
						<div class="<?php echo $dashCol6Rt; ?>">
							<?php renderStaffMyWorkTodayCard($url, $lang, $myWorkCardData, $sparkWorkOnTime, $sparkWorkLate, $myWorkOnTimePct, $myWorkOnTimePctChange); ?>
							</div>
						<!-- Row 2: Task time | Shift | Open project | Open task -->
						<?php renderStaffDashTimerStatCards($url, $lang, $dashTimeLoggedSec, $dashTaskGraphTargetSec, $dashTaskEntriesCount, $dashShiftCard, $dashCol3Time, $dashCol3Shift); ?>
						<div class="<?php echo $dashCol3Project; ?>">
							<?php renderStaffDashOpenProjectCard($url, $lang, $open_projects_this_count, $open_projects_percent_change, $sparkProjects); ?>
						</div>
						<div class="<?php echo $dashCol3Task; ?>">
							<?php renderStaffDashOpenTaskCard($url, $lang, $this_count, $percent_change, $sparkTasks); ?>
						</div>
						<?php endif; ?>
								</div>
							 </div>
						 </div>
					 <div class="row db-container ">
                        <div class="col-lg-12 col-md-12 col-sm-12">
                            <div class="db-box-wrap widget-card ">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div class="card-title">
                                        <h3><?php echo $lang['Running Projects']; ?></h3>
                                    </div>
                                    <div class="border-btn">
                                        <a href="<?php echo $url; ?>staff/projects">
                                            <?php echo $lang['View all']; ?>
                                        </a>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="row"> <div class="d-flex col-gap-20 xx">
                                        <?php if (empty($recentProjects)): ?>
                                            <div class="col-12">
                                                <?php renderDashboardEmptyState('projects', $lang['No projects found.'] ?? 'No projects found.', ['list_item' => false]); ?>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach($recentProjects as $recentProject): ?>
                                                <?php
                                                $projectId = (int)$recentProject->p_id;
                                                $taskStatsRow = $recentProjectTaskStats[$projectId] ?? null;
                                                $taskCount = (int)($taskStatsRow['task_count'] ?? 0);
                                                $completedTaskCount = (int)($taskStatsRow['completed_count'] ?? 0);
                                                $percent = ($taskCount > 0) ? round(($completedTaskCount / $taskCount) * 100) : 0;
                                                ?>
                                                <div class="project-x">                               
                                                    <div class="card project-card" style="position: relative;">
                                                        <!-- 3-dot dropdown menu -->
                                                        <div class="dropdown card-action-dropdown" style="position: absolute; top: 12px; right: 16px;">
                                                            <button class="btn btn-link dropdown-toggle" type="button" id="dropdownMenu<?php echo $recentProject->p_id; ?>" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" style="color: #333; font-size: 20px; text-decoration: none;">
                                                                <span class="light-grey" style="font-size: 20px; letter-spacing: &#8226;">&#8226;&#8226;&#8226;</span>
                                                            </button>
                                                            <ul class="dropdown-menu" aria-labelledby="dropdownMenu<?php echo $recentProject->p_id; ?>">
                                                                <li>
                                                                    <a class="dropdown-item" href="overview?projectId=<?php echo $recentProject->p_id; ?>">
                                                                        <?php echo ts_icon('clock', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Overview']; ?>
                                                                    </a>
                                                                </li>
                                                                <?php if ($isDiscussionsEnabled): ?>
                                                                <li>
                                                                    <form action="../discussion?project_id=<?php echo $recentProject->p_id;?>" method="post" style="display:inline;">
                                                                        <input type="hidden" name="user_id" value="<?php echo $recentProject->c_id;?>" />
                                                                        <input type="hidden" name="project_id" value="<?php echo $recentProject->p_id;?>" />
                                                                        <button type="submit" name="chat" class="dropdown-item">
                                                                          <?php echo ts_icon('chat', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Discussion']; ?>
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                                <?php endif; ?>
                                                                <li>
                                                                    <a class="dropdown-item" href="task?projectId=<?php echo $recentProject->p_id; ?>">
                                                                        <?php echo ts_icon('tasks', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Tasks']; ?>
                                                                    </a>
                                                                </li>
                                                                <?php if (has_permission('project_edit') || has_permission('project_delete')): ?>
                                                                <li><?php if (has_permission('project_edit')): ?>
                                                                    <a href="edit-project?id=<?php echo $recentProject->p_id;?>" class="dropdown-item"><?php echo ts_icon('edit', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Edit Project']; ?></a>
                                                                </li><?php endif; ?>
                                                                <li>
                                                                    <form method="post" action="projects" style="display:inline;">
                                                                        <input type="hidden" value="<?php echo $recentProject->p_id;?>" name="comp_id" />
                                                                        <input type="hidden" value="<?php if($recentProject->status == 0){ echo '1';}else { echo '0';} ?>" name="comp_val" />
                                                                        <button type="submit" name="comp_proj" class="dropdown-item">
                                                                          <?php if($recentProject->status == 0){ ?>
                                                                            <?php echo ts_icon('check-circle', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Mark as complete']; ?>
                                                                          <?php } else { ?>
                                                                            <?php echo ts_icon('plus', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Re-open']; ?>
                                                                          <?php } ?>
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                                <li>
                                                                    <form method="post" action="projects" style="display:inline;">
                                                                        <input type="hidden" value="<?php echo $recentProject->p_id;?>" name="arc_id" />
                                                                        <input type="hidden" value="<?php if($recentProject->archive == 0){ echo '1';}else { echo '0';} ?>" name="arc_val" />
                                                                        <button type="submit" name="arc_proj" class="dropdown-item">
                                                                          <?php echo ts_icon('archive', 'tasksession-timer-log-menu-ico me-2'); ?><?php if($recentProject->archive == 0){ echo $lang['Move to Archive']; }else{ echo $lang['Move to Projects']; } ?>
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                                <li><?php if (has_permission('project_delete')): ?>
                                                                    <form method="post" action="projects" style="display:inline;">
                                                                        <input type="hidden" value="<?php echo $recentProject->p_id;?>" name="del_id" />
                                                                        <input type="hidden" value="<?php if($recentProject->trash == 0){ echo '1';}else { echo '0';} ?>" name="del_val" />
                                                                        <button type="submit" name="del_proj" class="dropdown-item">
                                                                          <?php echo ts_icon('delete', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Delete Project']; ?>
                                                                        </button>
                                                                    </form>
                                                                </li><?php endif; ?>
                                                                <?php endif; ?>
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
                                                            <!-- Assigned Team -->
                                                            <div class="d-flex col-gap-35 mb-4 flex-wrap" style="text-align: left;">
                                                                <div class="clients-rpt" style="text-align: left;">
                                                                    <div class="title-head mb-2"><?php echo $lang['Assigned Team']; ?></div>
                                                                    <div class="d-flex align-items-center">
                                                                <?php
                                                                $st_ids = array_filter(array_map('intval', explode(',', (string) $recentProject->s_ids)));
                                                                $counter = 0;
                                                                $mainClientId = (int) ($recentProject->c_id ?? 0);
                                                                foreach ($st_ids as $st_id) {
                                                                    if ($st_id <= 0 || $st_id === $mainClientId) {
                                                                        continue;
                                                                    }
                                                                    $counter++;
                                                                    if ($counter > 3) {
                                                                        continue;
                                                                    }
                                                                    $user2 = $dashboardUsersById[$st_id] ?? null;
                                                                    renderDashboardProjectUserBox(
                                                                        $user2,
                                                                        (int) $recentProject->p_id,
                                                                        $user2 ? (string) $user2->firstName : '',
                                                                        $isDiscussionsEnabled,
                                                                        $st_id
                                                                    );
                                                                }
                                                                if ($counter > 3) {
                                                                    $more = $counter - 3;
                                                                    echo '<div class="plus-more shadow-dept">+' . $more . '</div>';
                                                                }
                                                                ?>
                                                                    </div>
                                                                </div>
                                                                <!-- Clients -->
                                                                <div class="clients mb-2">
                                                                    <div class="title-head mb-2"><?php echo $lang['Clients']; ?></div>
                                                                    <div class="d-flex align-items-center">
                                                                <?php
                                                                $mainClientIdLookup = (int) ($recentProject->main_client_id ?: $recentProject->c_id);
                                                                $mainClient = $mainClientIdLookup > 0 ? ($dashboardUsersById[$mainClientIdLookup] ?? null) : null;
                                                                if ($mainClient) {
                                                                    renderDashboardProjectUserBox(
                                                                        $mainClient,
                                                                        (int) $recentProject->p_id,
                                                                        $mainClient->firstName . ' (Main)',
                                                                        $isDiscussionsEnabled
                                                                    );
                                                                }

                                                                if (!empty($recentProject->c_ids)) {
                                                                    $allClientIds = array_filter(array_map('intval', explode(',', (string) $recentProject->c_ids)));
                                                                    $additionalClients = array_filter($allClientIds, function ($clientId) use ($recentProject) {
                                                                        return $clientId > 0
                                                                            && $clientId != (int) $recentProject->main_client_id
                                                                            && $clientId != (int) $recentProject->c_id;
                                                                    });

                                                                    $clientCounter = 0;
                                                                    foreach ($additionalClients as $clientId) {
                                                                        $clientCounter++;
                                                                        if ($clientCounter > 1) {
                                                                            break;
                                                                        }
                                                                        $client = $dashboardUsersById[(int) $clientId] ?? null;
                                                                        renderDashboardProjectUserBox(
                                                                            $client,
                                                                            (int) $recentProject->p_id,
                                                                            $client ? (string) $client->firstName : '',
                                                                            $isDiscussionsEnabled,
                                                                            (int) $clientId
                                                                        );
                                                                    }

                                                                    if (count($additionalClients) > 1) {
                                                                        $more = count($additionalClients) - 1;
                                                                        echo '<div class="plus-more shadow-dept">+' . $more . '</div>';
                                                                    }
                                                                }
                                                                ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <!-- Progress Bar and Task Completion (dynamic) -->
                                                            <div class="progress mb-2" style="height:8px;">
                                                                <div class="progress-bar" role="progressbar" style="width: <?php echo $percent; ?>%; background: <?php echo $percent < 30 ? '#f66' : ($percent < 70 ? '#f9b233' : '#4caf50'); ?>;" aria-valuenow="<?php echo $percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                            </div>
                                                            <div class="mb-2 d-flex col-gap-5"><div class="grey bold"><?php echo $lang['Tasks']; ?></div> <?php echo $completedTaskCount; ?>/<?php echo $taskCount; ?> <span class="text-align-right flex-grow"><?php echo $percent; ?>%</span></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
						       </div>
                            </div>
                        </div>
                    </div>
					<div class="row align-items-stretch admin-dash-widgets-row">
						 <div class="col-lg-4 col-md-6 col-sm-12 d-flex">
                            <div class="widget-card flex-grow <?php echo !$isTasksEnabled ? 'sales-stats-disabled' : ''; ?>">
                                <?php if (!$isTasksEnabled) :
                                    dashWidgetModuleDisabledOverlay(
                                        $url,
                                        $lang,
                                        $lang['Tasks module disabled'] ?? 'Tasks module disabled',
                                        $lang['tasks_module_overlay'] ?? 'Enable tasks to create, assign, and track work across your team.'
                                    );
                                endif; ?>
                                <div class="sales-stats-content">
								<div class="task-widget" id="dash-my-tasks-widget">
								  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap row-gap-10">
									<div class="card-title">
										<h3><?php echo $lang['My Tasks'] ?? 'My tasks'; ?></h3>
									</div>
									<div class="d-flex align-items-center col-gap-10 flex-wrap">
										<div class="media-view-toggle dash-my-tasks-toggle" role="group" aria-label="<?php echo htmlspecialchars($lang['My Tasks'] ?? 'My tasks', ENT_QUOTES, 'UTF-8'); ?>">
											<a href="#" class="border-btn-a is-active" data-dash-my-tasks-period="today" aria-pressed="true"><?php echo $lang['Today'] ?? 'Today'; ?></a>
											<a href="#" class="border-btn-a" data-dash-my-tasks-period="week" aria-pressed="false"><?php echo $lang['Week'] ?? 'Week'; ?></a>
									</div>
									<div class="border-btn">
										<a href="<?php echo $url; ?>staff/all-tasks">
											<?php echo $lang['View all']; ?>
										</a>
										</div>
									</div>
								  </div>

								  <div id="dash-my-tasks-today" class="list-group dash-my-tasks-list dash-my-tasks-panel">
									<?php dashWidgetRenderMyTaskRows($staff_my_tasks_today, $url, $lang, $dashboardUsersById, 'staff/'); ?>
											</div>
								  <div id="dash-my-tasks-week" class="list-group dash-my-tasks-list dash-my-tasks-panel is-hidden">
									<?php dashWidgetRenderMyTaskRows($staff_my_tasks_week, $url, $lang, $dashboardUsersById, 'staff/'); ?>
										  </div>
											</div>
									</div>
                            </div>
                        </div>
                         <div class="col-lg-4 col-md-6 col-sm-12 d-flex">
                           	<div class="widget-card flex-grow <?php echo !$isNotesDocumentsEnabled ? 'sales-stats-disabled' : ''; ?>">
                                <?php if (!$isNotesDocumentsEnabled) :
                                    dashWidgetModuleDisabledOverlay(
                                        $url,
                                        $lang,
                                        $lang['Notes & documents module disabled'] ?? 'Notes & documents module disabled',
                                        $lang['notes_documents_module_overlay'] ?? 'Enable notes and documents to store and share important files with your team.'
                                    );
                                endif; ?>
                              <div class="sales-stats-content">
                              <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div class="card-title">
                                        <h3><?php echo $lang['Documents']; ?></h3>
                                    </div>
									  <div class="border-btn">
                                        <a href="<?php echo $url; ?>staff/documents">
                                            <?php echo $lang['View all']; ?>
                                        </a>
                                    </div>
                              </div>
					 	<div class="list-group dash-docs-list">
                        <?php if (empty($notes)): ?>
                            <?php renderDashboardEmptyState('documents', $lang['No notes found.'] ?? 'No notes found.'); ?>
                        <?php else: ?>
                            <?php
                            $docCalIcon = function_exists('ts_icon') ? ts_icon('calendar', 'dash-task-meta-ico') : '';
                            foreach ($notes as $note):
                                $createdTs = !empty($note['created_at']) ? strtotime((string) $note['created_at']) : 0;
                                $updatedTs = !empty($note['updated_at']) ? strtotime((string) $note['updated_at']) : 0;
                                $createdLabel = $createdTs > 0 ? date('M j', $createdTs) : '';
                                $updatedLabel = $updatedTs > 0 ? date('M j', $updatedTs) : '';
                                $showUpdated = ($createdLabel !== '' && $updatedLabel !== '' && (string) $note['created_at'] !== (string) $note['updated_at']);
                            ?>
                                <div class="list-group-item note-card dash-task-card d-flex align-items-center justify-content-between" style="position: relative; cursor:pointer;">
                                    <a href="documents?note_id=<?php echo (int) $note['id']; ?>" class="dash-task-link flex-grow text-align-left">
                                        <div class="dash-task-title font-size-14"><?php echo htmlspecialchars($note['title']); ?></div>
                                        <div class="dash-task-meta font-size-12 grey">
                                            <?php if ($createdLabel !== '') : ?>
                                            <span class="dash-task-meta-date">
                                                <?php echo $docCalIcon; ?>
                                                <?php echo htmlspecialchars(($lang['Created'] ?? 'Created') . ': ' . $createdLabel, ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                            <?php endif; ?>
                                            <?php if ($showUpdated) : ?>
                                            <?php if ($createdLabel !== '') : ?><span class="dash-task-meta-sep" aria-hidden="true"></span><?php endif; ?>
                                            <span class="dash-task-meta-date">
                                                <?php echo $docCalIcon; ?>
                                                <?php echo htmlspecialchars(($lang['Updated'] ?? 'Updated') . ': ' . $updatedLabel, ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                    <div class="dash-task-aside d-flex align-items-center">
                                    <div class="dropdown ms-2 dash-task-menu">
                                        <button class="btn-dots" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?php echo htmlspecialchars($lang['Actions'] ?? 'Actions', ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo ts_icon('dots-vertical'); ?>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                               <a class="dropdown-item" href="documents?note_id=<?php echo (int) $note['id']; ?>">
                                                  <?php echo ts_icon('eye', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['View Note']; ?>
                                                </a>
                                            </li>
                                            <li>
                                               <a class="dropdown-item" href="documents?note_id=<?php echo (int) $note['id']; ?>">
                                                  <?php echo ts_icon('edit', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Edit Note']; ?>
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item text-danger" href="documents?delete=<?php echo (int) $note['id']; ?>" onclick="return confirm('Delete this note?')">
                                                  <?php echo ts_icon('delete', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Delete']; ?>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
					   </div>  
						  </div>
                                    </div>
							<div class="col-lg-4 col-md-6 col-sm-12 d-flex">
                                <?php if ($staffCanViewLeadsPipeline) : ?>
								 <div class="widget-card flex-grow">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <div class="card-title">
                                            <h3><?php echo $lang['Leads pipeline'] ?? 'Leads pipeline'; ?></h3>
                                </div>
                                        <div class="d-flex align-items-center col-gap-10">
                                            <?php dashWidgetLeadsPipelineCurrencyDropdown($staffDashLeadsPipeline); ?>
                                            <div class="border-btn">
                                                <a href="<?php echo $url; ?>staff/leads">
                                                    <?php echo $lang['View all']; ?>
                                                </a>
							 </div>
                        </div>
					</div>	
                                    <?php dashWidgetLeadsPipelineRender($staffDashLeadsPipeline, $lang); ?>
                </div>	
                                <?php else : ?>
                                 <div class="widget-card flex-grow">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <div class="card-title">
                                            <h3><?php echo $lang['Recent Activity'] ?? 'Recent activity'; ?></h3>
            </div>
                                        <div class="border-btn">
                                            <a href="<?php echo htmlspecialchars(function_exists('tasksession_app_href') ? tasksession_app_href('activity') : ($url . 'activity'), ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo $lang['View all']; ?>
                                            </a>
                                        </div>
                                    </div>
                                    <div class="list-group dash-docs-list">
                                        <?php renderAdminDashFeedItems($staffDashRecentActivityItems, $lang['No activity found'] ?? 'No activity found.', $lang); ?>
                                    </div>
                                 </div>
                                <?php endif; ?>
                        </div>
					</div>	
                </div>	
            </div>
        </div>
    </div>
</div>
<script>
window.totalProjects = <?php echo (int)$total_projects; ?>;
window.projectsCompleted = <?php echo (int)$projects_c; ?>;
window.projectsInProgress = <?php echo (int)$projects_ip; ?>;
window.completedLabel = <?php echo json_encode($lang['Completed Projects']); ?>;
window.inprogressLabel = <?php echo json_encode($lang['Inprogress Projects']); ?>;
window.monthLabels = <?php echo json_encode($month_labels); ?>;
window.monthlyEarnings = <?php echo json_encode($monthly_earnings); ?>;
window.monthlyUnpaid = <?php echo json_encode($monthly_unpaid); ?>;
window.totalPaidLabel = <?php echo json_encode($lang['Total Paid']); ?>;
window.totalUnpaidLabel = <?php echo json_encode($lang['Total Unpaid']); ?>;
window.invoicePaidLabel = <?php echo json_encode($lang['Paid Invoices'] ?? 'Paid Invoices'); ?>;
window.invoiceUnpaidLabel = <?php echo json_encode($lang['Unpaid'] ?? 'Unpaid'); ?>;
window.defaultCurrency = <?php echo json_encode($sysCurrencyRaw !== '' ? $sysCurrencyRaw : ($dashCurrencyCode . ',' . $dashCurrencySymbol)); ?>;
window.selectedCurrency = window.defaultCurrency;
</script>
<script>
(function () {
    function initDashMyTasksWidget() {
        var widget = document.getElementById('dash-my-tasks-widget');
        if (!widget) {
            return;
        }
        var todayPanel = document.getElementById('dash-my-tasks-today');
        var weekPanel = document.getElementById('dash-my-tasks-week');

        function showDashMyTasksPeriod(period) {
            period = period === 'week' ? 'week' : 'today';
            widget.querySelectorAll('[data-dash-my-tasks-period]').forEach(function (tabBtn) {
                var isActive = (tabBtn.getAttribute('data-dash-my-tasks-period') || 'today') === period;
                tabBtn.classList.toggle('is-active', isActive);
                tabBtn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
            if (todayPanel) {
                todayPanel.classList.toggle('is-hidden', period !== 'today');
            }
            if (weekPanel) {
                weekPanel.classList.toggle('is-hidden', period !== 'week');
            }
        }

        widget.querySelectorAll('[data-dash-my-tasks-period]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                showDashMyTasksPeriod(btn.getAttribute('data-dash-my-tasks-period') || 'today');
            });
        });

        widget.addEventListener('click', function (e) {
            if (e.target.closest('.dropdown, .dropdown-menu, .btn-dots, .dropdown-item, [data-dash-my-tasks-period]')) {
                return;
            }
            var card = e.target.closest('.dash-task-card[data-task-id]');
            if (!card) {
                return;
            }
            var taskId = parseInt(card.getAttribute('data-task-id') || '0', 10);
            if (!taskId || typeof openTaskSidebar !== 'function') {
                return;
            }
            e.preventDefault();
            openTaskSidebar(taskId);
            widget.querySelectorAll('.dash-task-card.active').forEach(function (activeCard) {
                activeCard.classList.remove('active');
            });
            card.classList.add('active');
        });

        widget.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') {
                return;
            }
            var card = e.target.closest('.dash-task-card[data-task-id]');
            if (!card || e.target.closest('.dropdown, .btn-dots')) {
                return;
            }
            e.preventDefault();
            card.click();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDashMyTasksWidget);
    } else {
        initDashMyTasksWidget();
    }
})();

(function () {
    function initDashLeadsPipelineCurrency() {
        var pipeline = document.querySelector('.dash-leads-pipeline[data-currency-metrics]');
        if (!pipeline) {
            return;
        }

        var metrics = {};
        try {
            metrics = JSON.parse(pipeline.getAttribute('data-currency-metrics') || '{}');
        } catch (e) {
            metrics = {};
        }

        var defaultCurrency = pipeline.getAttribute('data-default-currency') || '';
        var pipelineValueEl = document.getElementById('dash-leads-pipeline-value');
        var avgDealValueEl = document.getElementById('dash-leads-avg-deal-value');
        var dropdownBtn = document.getElementById('dashLeadsPipelineCurrencyDropdown');

        function applyDashLeadsCurrency(currency) {
            if (!currency || !metrics[currency]) {
                return;
            }
            var row = metrics[currency];
            if (pipelineValueEl) {
                pipelineValueEl.textContent = row.pipeline_label || '$0';
            }
            if (avgDealValueEl) {
                avgDealValueEl.textContent = row.avg_label || '$0';
            }
            if (dropdownBtn && row.display_name) {
                dropdownBtn.textContent = row.display_name;
            }
        }

        document.querySelectorAll('[data-dash-leads-currency]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                applyDashLeadsCurrency(btn.getAttribute('data-dash-leads-currency') || '');
            });
        });

        if (defaultCurrency) {
            applyDashLeadsCurrency(defaultCurrency);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDashLeadsPipelineCurrency);
    } else {
        initDashLeadsPipelineCurrency();
    }
})();
</script>
<script src="../assets/js/Chart.js"></script>
<script src="../assets/js/analytics.js"></script>
<?php include("../templates/main-footer.php"); ?>


