<?php 
/**
 ================================================================================
   Task Session – Project Management System
   File    : Admin Dashboard Template
 ================================================================================
 */
// Initialize session and security
ob_start();
require_once("../includes/lib-initialize.php");
require_once("../includes/reports_common_helper.php");
require_once("../includes/dashboard_empty_state.php");
require_once("../includes/activity_page_helper.php");

// Authentication before any HTML output
if (!$session->isLoggedIn()) {
    redirectTo($url . "index.php");
}
if ($_SESSION['accountStatus'] == 2) {
    redirectTo($url . "client/index.php");
}
if ($_SESSION['accountStatus'] == 3) {
    redirectTo($url . "staff/index.php");
}

// Ensure CSRF exists before releasing the lock (footer still reads it for JS).
if (function_exists('generate_csrf_token')) {
    generate_csrf_token();
}
// Release session lock so dashboard AJAX can run while this page renders.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    session_write_close();
}

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

// Set timezone
date_default_timezone_set($time_zone);

// Load settings for module checks
$settingsForModules = isset($dash_settings) && $dash_settings ? $dash_settings : settings::findById(1);
require_once __DIR__ . '/../includes/sidebar_navigation.php';
$guardUser = User::findById((int) $session->userId);
comon_guard_hidden_dashboard($settingsForModules, 'admin', $guardUser);
$isDiscussionsEnabled = ($settingsForModules && !empty($settingsForModules->module_discussions));
$isInvoicesEnabled = ($settingsForModules && !empty($settingsForModules->module_invoices));
$isTasksEnabled = ($settingsForModules && !empty($settingsForModules->module_tasks));
$isNotesDocumentsEnabled = ($settingsForModules && !empty($settingsForModules->module_notes_documents));
$isLeadBoardEnabled = ($settingsForModules && !empty($settingsForModules->module_lead_board));
// This Free package always locks Pro dashboard widgets (sales + leads).
$isFreeEditionDash = !function_exists('tasksession_is_free_edition') || tasksession_is_free_edition();
if ($isFreeEditionDash) {
    $isInvoicesEnabled = false;
    $isDiscussionsEnabled = false;
    $isLeadBoardEnabled = false;
}
$salesStatsLocked = ($isFreeEditionDash || !$isInvoicesEnabled);
$leadsPipelineLocked = ($isFreeEditionDash || !$isLeadBoardEnabled);

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
 * Admin dashboard — My tasks widget rows (assigned to current admin).
 */
function renderAdminDashMyTaskRows(array $tasks, $url, array $lang, array $dashboardUsersById) {
    if (empty($tasks)) {
        renderDashboardEmptyState('tasks', $lang['No tasks found.'] ?? 'No tasks found.');
        return;
    }

    $today = new DateTime('today');
    $calIcon = function_exists('ts_icon') ? ts_icon('calendar', 'dash-task-meta-ico') : '';
    foreach ($tasks as $task) {
        $dueTs = !empty($task->due_date) ? strtotime((string) $task->due_date) : 0;
        $startTs = !empty($task->start_date) ? strtotime((string) $task->start_date) : 0;
        $projectId = (int) ($task->project_id ?? 0);
        $isInternal = $projectId <= 0;
        $projectTitle = trim((string) ($task->project_title ?? ''));
        $typeLabel = (!$isInternal && $projectTitle !== '') ? $projectTitle : '';
        $isOverdue = false;
        if ($dueTs > 0) {
            try {
                $isOverdue = (new DateTime($task->due_date)) < $today;
            } catch (Exception $e) {
                $isOverdue = false;
            }
        }
        $startLabel = $startTs > 0 ? date('M j', $startTs) : '';
        $dueLabel = $dueTs > 0 ? date('M j', $dueTs) : '';
        ?>
        <div class="list-group-item note-card dash-task-card d-flex align-items-center justify-content-between"
             data-task-id="<?php echo (int) $task->id; ?>"
             role="button"
             tabindex="0"
             aria-label="<?php echo htmlspecialchars($task->title, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="dash-task-link flex-grow text-align-left">
                <div class="dash-task-title font-size-14"><?php echo htmlspecialchars($task->title); ?></div>
                <div class="dash-task-meta font-size-12 grey">
                    <?php if ($typeLabel !== '') : ?>
                    <span class="dash-task-meta-type"><?php echo htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                    <?php if ($startLabel !== '') : ?>
                    <?php if ($typeLabel !== '') : ?><span class="dash-task-meta-sep" aria-hidden="true"></span><?php endif; ?>
                    <span class="dash-task-meta-date">
                        <?php echo $calIcon; ?>
                        <?php echo htmlspecialchars(($lang['Start'] ?? 'Start') . ': ' . $startLabel, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($dueLabel !== '') : ?>
                    <?php if ($typeLabel !== '' || $startLabel !== '') : ?><span class="dash-task-meta-sep" aria-hidden="true"></span><?php endif; ?>
                    <span class="dash-task-meta-date<?php echo $isOverdue ? ' is-overdue' : ''; ?>">
                        <?php echo $calIcon; ?>
                        <?php echo htmlspecialchars(($lang['Due'] ?? 'Due') . ': ' . $dueLabel, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="dash-task-aside d-flex align-items-center">
                <div class="dash-task-avatars d-flex align-items-center">
                <?php
                $assigned_ids = array_filter(explode(',', (string) ($task->assigned_to ?? '')));
                $shown = 0;
                foreach ($assigned_ids as $uid) {
                    if ($shown >= 3) {
                        break;
                    }
                    $uid = (int) $uid;
                    $assignedUser = $uid > 0 ? ($dashboardUsersById[$uid] ?? null) : null;
                    if ($assignedUser) {
                        echo "<div class='user-box' data-bs-toggle='tooltip' data-bs-placement='top' title='" . htmlspecialchars($assignedUser->firstName, ENT_QUOTES, 'UTF-8') . "'>";
                        echo getUserAvatarHtml($uid, $assignedUser->firstName, $assignedUser->lastName ?? '', 32, 32, 'avatar', $assignedUser->firstName);
                        echo '</div>';
                        $shown++;
                    }
                }
                if (count($assigned_ids) > 3) {
                    echo '<div class="plus-more">+' . (count($assigned_ids) - 3) . '</div>';
                }
                ?>
                </div>
                <div class="dropdown ms-2 dash-task-menu">
                <button class="btn-dots" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?php echo htmlspecialchars($lang['Actions'] ?? 'Actions', ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo ts_icon('dots-vertical'); ?>
                </button>
                <ul class="dropdown-menu">
                    <li>
                        <a class="dropdown-item view-task-btn" href="#" data-task-id="<?php echo (int) $task->id; ?>">
                            <?php echo ts_icon('eye', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['View Task'] ?? 'View Task'; ?>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="<?php echo $url; ?>admin/edit_task?id=<?php echo (int) $task->id; ?>">
                            <?php echo ts_icon('edit', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Edit Task'] ?? 'Edit Task'; ?>
                        </a>
                    </li>
                    <?php if (!empty($task->project_id) && (int) $task->project_id !== 0): ?>
                    <li>
                        <a class="dropdown-item" href="overview?projectId=<?php echo (int) $task->project_id; ?>">
                            <?php echo ts_icon('archive', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['View Project'] ?? 'View Project'; ?>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                </div>
            </div>
        </div>
        <?php
    }
}

/**
 * Preload assignee avatars for dashboard task widgets.
 */
function adminDashPreloadTaskAssignees(array $tasks, array &$dashboardUsersById) {
    $recentTaskUserIds = [];
    foreach ($tasks as $recentTaskPrep) {
        foreach (array_filter(array_map('intval', explode(',', (string) ($recentTaskPrep->assigned_to ?? '')))) as $assignedUidPrep) {
            if ($assignedUidPrep > 0) {
                $recentTaskUserIds[$assignedUidPrep] = true;
            }
        }
    }
    if (empty($recentTaskUserIds)) {
        return;
    }
    $missingUserIds = array_diff(array_keys($recentTaskUserIds), array_keys($dashboardUsersById));
    if (empty($missingUserIds)) {
        return;
    }
    $loadedRecentTaskUsers = user::findBySql(
        'SELECT * FROM users WHERE id IN (' . implode(',', $missingUserIds) . ')'
    );
    if ($loadedRecentTaskUsers) {
        foreach ($loadedRecentTaskUsers as $loadedRecentTaskUser) {
            $dashboardUsersById[(int) $loadedRecentTaskUser->id] = $loadedRecentTaskUser;
        }
    }
}


/**
 * Upcoming widget — short date label (Today, Tomorrow, Sat 22).
 */
function adminDashUpcomingDateLabel(int $ts, array $lang): string
{
    if ($ts <= 0) {
        return '';
    }
    $dayTs = strtotime(date('Y-m-d', $ts));
    $todayTs = strtotime('today');
    if ($dayTs === $todayTs) {
        return $lang['Today'] ?? 'Today';
    }
    if ($dayTs === strtotime('tomorrow')) {
        return $lang['Tomorrow'] ?? 'Tomorrow';
    }

    return date('D j', $ts);
}

/**
 * Upcoming widget — date column parts (Mon / 24 / Nov).
 *
 * @return array{dow:string,day:string,mon:string}
 */
function adminDashUpcomingDateParts(int $ts): array
{
    if ($ts <= 0) {
        return ['dow' => '', 'day' => '', 'mon' => ''];
    }

    return [
        'dow' => date('D', $ts),
        'day' => date('j', $ts),
        'mon' => date('M', $ts),
    ];
}

/**
 * Build merged upcoming items for admin dashboard (next 30 days).
 *
 * @return array<int, array<string,mixed>>
 */
function adminDashUpcomingBuildItems(array $lang, array &$dashboardUsersById): array
{
    $items = [];
    $rangeEnd = date('Y-m-d', strtotime('+30 days'));

    $resolveClientName = static function ($clientIdRaw) use (&$dashboardUsersById): string {
        $clientIds = array_filter(array_map('intval', explode(',', (string) $clientIdRaw)));
        $cid = (int) ($clientIds[0] ?? 0);
        if ($cid <= 0) {
            return '';
        }
        if (isset($dashboardUsersById[$cid])) {
            return trim((string) ($dashboardUsersById[$cid]->firstName ?? ''));
        }
        $clientUser = user::findById($cid);
        if ($clientUser) {
            $dashboardUsersById[$cid] = $clientUser;
            return trim((string) ($clientUser->firstName ?? ''));
        }
        return '';
    };

    $push = static function (array $row) use (&$items): void {
        if ((int) ($row['ts'] ?? 0) <= 0) {
            return;
        }
        $items[] = $row;
    };

    $upcomingTasks = Task::findBySqlSoft(
        "SELECT t.*, p.project_title
         FROM tasks t
         LEFT JOIN projects p ON p.p_id = t.project_id
         WHERE t.status != 'done'
           AND " . reports_sql_valid_date('t.due_date') . "
           AND DATE(t.due_date) >= CURDATE()
           AND DATE(t.due_date) <= '" . date('Y-m-d', strtotime($rangeEnd)) . "'
         ORDER BY t.due_date ASC, t.id ASC
         LIMIT 12"
    );
    if (is_array($upcomingTasks)) {
        foreach ($upcomingTasks as $taskRow) {
            $dueTs = strtotime((string) $taskRow->due_date);
            if ($dueTs <= 0) {
                continue;
            }
            $projectTitle = trim((string) ($taskRow->project_title ?? ''));
            $projectId = (int) ($taskRow->project_id ?? 0);
            $isInternalTask = ($projectId <= 0);
            $timeLabel = '';
            if (date('H:i:s', $dueTs) !== '00:00:00') {
                $timeLabel = date('g:i A', $dueTs);
            }
            if ($isInternalTask) {
                $categoryLabel = $lang['Internal Task'] ?? 'Internal Task';
            } elseif ($projectTitle !== '') {
                $categoryLabel = $projectTitle;
            } else {
                $categoryLabel = $lang['Project'] ?? 'Project';
            }
            $metaParts = array_filter([$timeLabel, $categoryLabel]);
            $taskId = (int) $taskRow->id;
            $menu = [
                [
                    'label' => $lang['View Task'] ?? 'View Task',
                    'href' => '#',
                    'icon' => 'eye',
                    'class' => 'view-task-btn',
                    'task_id' => $taskId,
                ],
                [
                    'label' => $lang['Edit Task'] ?? 'Edit Task',
                    'href' => 'edit_task?id=' . $taskId,
                    'icon' => 'edit',
                ],
            ];
            if ($projectId > 0) {
                $menu[] = [
                    'label' => $lang['View Project'] ?? 'View Project',
                    'href' => 'overview?projectId=' . $projectId,
                    'icon' => 'archive',
                ];
            }
            $push([
                'ts' => $dueTs,
                'date_label' => adminDashUpcomingDateLabel($dueTs, $lang),
                'title' => (string) ($taskRow->title ?? ''),
                'meta' => implode(' · ', $metaParts),
                'tag' => 'task',
                'task_id' => $taskId,
                'menu' => $menu,
            ]);
        }
    }

    $upcomingProjects = projects::findBySqlSoft(
        "SELECT p.*,
                (SELECT COUNT(*) FROM tasks t2
                 WHERE t2.project_id = p.p_id AND t2.status != 'done') AS open_tasks
         FROM projects p
         WHERE p.status = 0
           AND (p.archive = 0 OR p.archive IS NULL)
           AND (p.trash IS NULL OR p.trash = 0 OR p.trash = '0')
           AND " . reports_sql_valid_date('p.end_time') . "
           AND DATE(p.end_time) >= CURDATE()
           AND DATE(p.end_time) <= '" . date('Y-m-d', strtotime($rangeEnd)) . "'
         ORDER BY p.end_time ASC, p.p_id ASC
         LIMIT 10"
    );
    if (is_array($upcomingProjects)) {
        foreach ($upcomingProjects as $projectRow) {
            $dueTs = strtotime((string) $projectRow->end_time);
            if ($dueTs <= 0) {
                continue;
            }
            $projectTitle = trim((string) ($projectRow->project_title ?? ''));
            if ($projectTitle === '') {
                $projectTitle = $lang['Project'] ?? 'Project';
            }
            $openTasks = (int) ($projectRow->open_tasks ?? 0);
            $tasksLeftLabel = $openTasks === 1
                ? '1 ' . ($lang['task left'] ?? 'task left')
                : $openTasks . ' ' . ($lang['tasks left'] ?? 'tasks left');
            $push([
                'ts' => $dueTs,
                'date_label' => adminDashUpcomingDateLabel($dueTs, $lang),
                'title' => $projectTitle . ' ' . ($lang['deadline'] ?? 'deadline'),
                'meta' => ($lang['Project'] ?? 'Project') . ' · ' . $tasksLeftLabel,
                'tag' => 'deadline',
                'href' => 'overview?projectId=' . (int) $projectRow->p_id,
                'menu' => [[
                    'label' => $lang['View Project'] ?? 'View Project',
                    'href' => 'overview?projectId=' . (int) $projectRow->p_id,
                    'icon' => 'archive',
                ]],
            ]);
        }
    }

    $upcomingInvoices = array();
    if (!(function_exists('tasksession_is_free_edition') && tasksession_is_free_edition())) {
    $upcomingInvoices = milestone::findBySqlSoft(
        "SELECT * FROM milestones
         WHERE (status = '0' OR status = 0)
           AND " . reports_sql_valid_date('deadline') . "
           AND DATE(deadline) >= CURDATE()
           AND DATE(deadline) <= '" . date('Y-m-d', strtotime($rangeEnd)) . "'
         ORDER BY deadline ASC, id ASC
         LIMIT 10"
    );
    }
    if (is_array($upcomingInvoices)) {
        foreach ($upcomingInvoices as $invoiceRow) {
            $dueTs = strtotime((string) $invoiceRow->deadline);
            if ($dueTs <= 0) {
                continue;
            }
            $amount = (float) ($invoiceRow->budget ?? 0);
            $symbol = getCurrencySymbol((string) ($invoiceRow->currency ?? ''));
            $clientName = $resolveClientName($invoiceRow->c_id ?? '');
            $metaParts = array_filter([$clientName, $symbol . number_format($amount, 2)]);
            $invoiceId = (int) $invoiceRow->id;
            $push([
                'ts' => $dueTs,
                'date_label' => adminDashUpcomingDateLabel($dueTs, $lang),
                'title' => ($lang['Invoice'] ?? 'Invoice') . ' #' . $invoiceId . ' ' . ($lang['due'] ?? 'due'),
                'meta' => implode(' · ', $metaParts),
                'tag' => 'invoice',
                'href' => 'invoices?id=' . $invoiceId,
                'menu' => [[
                    'label' => $lang['View Invoice'] ?? 'View Invoice',
                    'href' => 'invoices?id=' . $invoiceId,
                    'icon' => 'eye',
                ]],
            ]);
        }
    }

    global $connect;
    if (isset($connect) && $connect instanceof mysqli && function_exists('crm_db_table_exists') && crm_db_table_exists($connect, 'calendar_events')) {
        $calStmt = $connect->prepare(
                "SELECT id, title, event_date, start_datetime, end_datetime, location_label, team_label
                 FROM calendar_events
                 WHERE is_waiting_list = 0
                   AND (task_id IS NULL OR task_id = 0)
                   AND (
                     (event_date IS NOT NULL AND event_date >= CURDATE() AND event_date <= ?)
                     OR (start_datetime IS NOT NULL AND DATE(start_datetime) >= CURDATE() AND DATE(start_datetime) <= ?)
                   )
                 ORDER BY COALESCE(start_datetime, CONCAT(event_date, ' 00:00:00')) ASC
                 LIMIT 12"
            );
            if ($calStmt) {
                $calStmt->bind_param('ss', $rangeEnd, $rangeEnd);
                $calStmt->execute();
                $calRes = $calStmt->get_result();
                if ($calRes) {
                    while ($calRow = $calRes->fetch_assoc()) {
                    $startRaw = (string) ($calRow['start_datetime'] ?? '');
                    $eventDate = (string) ($calRow['event_date'] ?? '');
                    $dueTs = $startRaw !== '' ? strtotime($startRaw) : ($eventDate !== '' ? strtotime($eventDate) : 0);
                    if ($dueTs <= 0) {
                        continue;
                    }
                    $metaParts = [];
                    if ($startRaw !== '' && date('H:i:s', $dueTs) !== '00:00:00') {
                        $metaParts[] = date('g:i A', $dueTs);
                        $endRaw = (string) ($calRow['end_datetime'] ?? '');
                        if ($endRaw !== '') {
                            $mins = max(1, (int) round((strtotime($endRaw) - $dueTs) / 60));
                            $metaParts[] = $mins . ' ' . ($lang['min'] ?? 'min');
                        }
                    }
                    $location = trim((string) ($calRow['location_label'] ?? ''));
                    if ($location !== '') {
                        $metaParts[] = $location;
                    } elseif (trim((string) ($calRow['team_label'] ?? '')) !== '') {
                        $metaParts[] = trim((string) $calRow['team_label']);
                    }
                    $teamLabel = trim((string) ($calRow['team_label'] ?? ''));
                    $tag = ($teamLabel !== '' && stripos($location, 'meet') === false) ? 'internal' : 'meeting';
                    $eventId = (int) ($calRow['id'] ?? 0);
                    $push([
                        'ts' => $dueTs,
                        'date_label' => adminDashUpcomingDateLabel($dueTs, $lang),
                        'title' => trim((string) ($calRow['title'] ?? '')) !== ''
                            ? (string) $calRow['title']
                            : ($lang['Calendar event'] ?? 'Calendar event'),
                        'meta' => implode(' · ', $metaParts),
                        'tag' => $tag,
                        'href' => 'calendar' . ($eventId > 0 ? '?event=' . $eventId : ''),
                        'menu' => [[
                            'label' => $lang['Calendar'] ?? 'Calendar',
                            'href' => 'calendar' . ($eventId > 0 ? '?event=' . $eventId : ''),
                            'icon' => 'calendar',
                        ]],
                    ]);
                    }
                }
                $calStmt->close();
            }

        if (function_exists('crm_db_table_exists') && crm_db_table_exists($connect, 'leads')) {
            $leadStmt = $connect->prepare(
                "SELECT id, company, name, expected_close_date
                 FROM leads
                 WHERE " . reports_sql_valid_date('expected_close_date') . "
                   AND (is_won = 0 OR is_won IS NULL)
                   AND (is_lost = 0 OR is_lost IS NULL)
                   AND DATE(expected_close_date) >= CURDATE()
                   AND DATE(expected_close_date) <= ?
                 ORDER BY expected_close_date ASC, id ASC
                 LIMIT 8"
            );
            if ($leadStmt) {
                $leadStmt->bind_param('s', $rangeEnd);
                $leadStmt->execute();
                $leadRes = $leadStmt->get_result();
                if ($leadRes) {
                    while ($leadRow = $leadRes->fetch_assoc()) {
                    $dueTs = strtotime((string) ($leadRow['expected_close_date'] ?? ''));
                    if ($dueTs <= 0) {
                        continue;
                    }
                    $leadName = trim((string) ($leadRow['company'] ?? ''));
                    if ($leadName === '') {
                        $leadName = trim((string) ($leadRow['name'] ?? ''));
                    }
                    if ($leadName === '') {
                        $leadName = $lang['Lead'] ?? 'Lead';
                    }
                    $leadId = (int) ($leadRow['id'] ?? 0);
                    $push([
                        'ts' => $dueTs,
                        'date_label' => adminDashUpcomingDateLabel($dueTs, $lang),
                        'title' => ($lang['Lead close'] ?? 'Lead close') . ' – ' . $leadName,
                        'meta' => $lang['Expected close'] ?? 'Expected close',
                        'tag' => 'lead',
                        'href' => $leadId > 0 ? 'leads?open_lead=' . $leadId : 'leads',
                        'menu' => [[
                            'label' => $lang['View Lead'] ?? 'View lead',
                            'href' => $leadId > 0 ? 'leads?open_lead=' . $leadId : 'leads',
                            'icon' => 'eye',
                        ]],
                    ]);
                    }
                }
                $leadStmt->close();
            }
        }
    }

    usort($items, static function ($a, $b) {
        $cmp = (int) ($a['ts'] ?? 0) <=> (int) ($b['ts'] ?? 0);
        if ($cmp !== 0) {
            return $cmp;
        }
        return strcmp((string) ($a['tag'] ?? ''), (string) ($b['tag'] ?? ''));
    });

    return array_slice($items, 0, 5);
}

/**
 * Render Upcoming widget rows.
 */
function renderAdminDashUpcomingItems(array $items, string $emptyLabel, array $lang = []): void
{
    $tagLabels = [
        'meeting' => $lang['Meeting'] ?? 'Meeting',
        'internal' => $lang['Internal'] ?? 'Internal',
        'deadline' => $lang['Deadline'] ?? 'Deadline',
        'invoice' => $lang['Invoice'] ?? 'Invoice',
        'task' => $lang['Task'] ?? 'Task',
        'lead' => $lang['Lead'] ?? 'Lead',
    ];
    // Same global badge classes as admin/all-tasks.php status badges.
    $tagBadgeClasses = [
        'meeting' => 'color-inprogress inprogress inprogress-bg-op',
        'internal' => 'text-muted',
        'deadline' => 'color-todo todo todo-bg-op',
        'invoice' => 'color-done done review done-bg-op',
        'task' => 'color-review review review-bg-op',
        'lead' => 'color-inprogress inprogress inprogress-bg-op',
    ];

    if ($items === []) {
        renderDashboardEmptyState('calendar', $emptyLabel);
        return;
    }

    foreach ($items as $item) {
        $href = trim((string) ($item['href'] ?? ''));
        $taskId = (int) ($item['task_id'] ?? 0);
        $menu = is_array($item['menu'] ?? null) ? $item['menu'] : [];
        $itemTs = (int) ($item['ts'] ?? 0);
        $dateParts = adminDashUpcomingDateParts($itemTs);
        $title = htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $meta = (string) ($item['meta'] ?? '');
        $tag = (string) ($item['tag'] ?? 'task');
        $tagLabel = $tagLabels[$tag] ?? ucfirst($tag);
        $badgeClass = $tagBadgeClasses[$tag] ?? 'color-review review review-bg-op';
        ?>
        <div class="list-group-item note-card d-flex align-items-center dash-upcoming-item">
            <?php if ($itemTs > 0) : ?>
            <div class="dash-upcoming-date-col" aria-hidden="true">
                <span class="dash-upcoming-dow"><?php echo htmlspecialchars($dateParts['dow'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="dash-upcoming-day"><?php echo htmlspecialchars($dateParts['day'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="dash-upcoming-mon"><?php echo htmlspecialchars($dateParts['mon'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($taskId > 0) : ?>
            <div class="dash-upcoming-main view-task-btn"
                 data-task-id="<?php echo $taskId; ?>"
                 role="button"
                 tabindex="0">
            <?php elseif ($href !== '') : ?>
            <a href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>" class="dash-upcoming-main">
            <?php else : ?>
            <div class="dash-upcoming-main">
            <?php endif; ?>
                <div class="dash-upcoming-copy">
                    <div class="dash-upcoming-title font-size-14"><strong><?php echo $title; ?></strong></div>
                    <?php if ($meta !== '') : ?>
                    <div class="dash-upcoming-meta font-size-12 grey"><?php echo htmlspecialchars($meta, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>
                </div>
            <?php if ($taskId > 0 || $href === '') : ?>
            </div>
            <?php else : ?>
            </a>
            <?php endif; ?>
            <span class="badge <?php echo htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($tagLabel, ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <?php adminDashRenderActivityMenu($menu, $lang); ?>
        </div>
        <?php
    }
}

/**
 * Resolve lead status color_class to hex (matches leads.php column colors).
 */
function adminDashLeadStatusColorHex(string $colorClass): string
{
    $map = [
        'color-todo-bg' => '#dc3545',
        'color-todo' => '#dc3545',
        'color-inprogress-bg' => '#17a2b8',
        'color-inprogress' => '#17a2b8',
        'color-review-bg' => '#f2711c',
        'color-review' => '#f2711c',
        'color-done-bg' => '#28a745',
        'color-done' => '#28a745',
        'text-primary' => '#0d6efd',
        'text-success' => '#198754',
        'text-warning' => '#ffc107',
        'text-danger' => '#dc3545',
        'text-info' => '#0dcaf0',
        'text-secondary' => '#6c757d',
        'text-dark' => '#212529',
        'text-muted' => '#6c757d',
    ];
    $key = trim($colorClass);
    if (isset($map[$key])) {
        return $map[$key];
    }
    if (str_ends_with($key, '-bg')) {
        $base = substr($key, 0, -3);
        if (isset($map[$base])) {
            return $map[$base];
        }
    }

    return '#6c757d';
}

/**
 * Base currency code from stored lead currency string (USD,$ / Rs,Rs).
 */
function adminDashLeadCurrencyBaseCode(string $currency): string
{
    $parts = preg_split('/[,\(]/', trim($currency));
    $base = is_array($parts) ? trim((string) ($parts[0] ?? '')) : '';

    return strtoupper($base);
}

/**
 * Dropdown label for enabled lead currency.
 */
function adminDashLeadCurrencyDisplayName(string $currency): string
{
    $currency = trim($currency);
    if ($currency === '') {
        return 'USD';
    }

    $parts = explode(',', $currency);
    $code = trim((string) ($parts[0] ?? ''));

    return $code !== '' ? $code : $currency;
}

/**
 * Render leads pipeline currency dropdown (before View all).
 */
function renderAdminDashLeadsPipelineCurrencyDropdown(array $data): void
{
    $metricsByCurrency = is_array($data['metrics_by_currency'] ?? null) ? $data['metrics_by_currency'] : [];
    if ($metricsByCurrency === []) {
        return;
    }

    $defaultCurrency = (string) ($data['default_currency'] ?? array_key_first($metricsByCurrency));
    $defaultLabel = (string) (($metricsByCurrency[$defaultCurrency]['display_name'] ?? '') ?: adminDashLeadCurrencyDisplayName($defaultCurrency));
    ?>
    <div class="dropdown-btn dash-leads-currency-dropdown">
        <div class="dropdown">
            <button class="btn btn-light dropdown-toggle" type="button" id="dashLeadsPipelineCurrencyDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <?php echo htmlspecialchars($defaultLabel, ENT_QUOTES, 'UTF-8'); ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end p-2" aria-labelledby="dashLeadsPipelineCurrencyDropdown" style="min-width: 180px;">
                <?php foreach ($metricsByCurrency as $currencyKey => $metricRow) : ?>
                <li>
                    <button class="dropdown-item" type="button"
                            data-dash-leads-currency="<?php echo htmlspecialchars((string) $currencyKey, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars((string) ($metricRow['display_name'] ?? adminDashLeadCurrencyDisplayName((string) $currencyKey)), ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php
}

/**
 * Disabled module overlay (same markup/classes as invoice sales stats).
 */
function renderAdminDashModuleDisabledOverlay(string $url, array $lang, string $title, string $message): void
{
    ?>
    <div class="sales-stats-overlay">
        <div class="overlay-card">
            <h3><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h3>
            <p><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            <a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>admin/system-settings" class="primary-btn"><?php echo htmlspecialchars($lang['Enable Now'] ?? 'Enable Now', ENT_QUOTES, 'UTF-8'); ?></a>
        </div>
    </div>
    <?php
}

/**
 * Build leads pipeline snapshot for admin dashboard widget.
 *
 * @return array<string,mixed>
 */
function adminDashLeadsPipelineBuild(array $lang): array
{
    global $connect;

    $empty = [
        'ready' => false,
        'stages' => [],
        'conversions' => [],
        'pipeline_value' => 0,
        'pipeline_value_label' => getCurrencySymbol('') . '0',
        'avg_deal_label' => getCurrencySymbol('') . '0',
    ];

    if (!isset($connect) || !($connect instanceof mysqli)) {
        return $empty;
    }

    $tableCheckOk = function_exists('crm_db_table_exists')
        ? crm_db_table_exists($connect, 'lead_statuses')
        : (($tmp = $connect->query("SHOW TABLES LIKE 'lead_statuses'")) && $tmp->num_rows > 0);
    if (!$tableCheckOk) {
        return $empty;
    }

    $statuses = [];
    $statusRes = $connect->query('SELECT id, name, sort_order, color_class FROM lead_statuses ORDER BY sort_order ASC, id ASC LIMIT 4');
    if ($statusRes) {
        while ($row = $statusRes->fetch_assoc()) {
            $statuses[] = $row;
        }
    }
    if ($statuses === []) {
        return $empty;
    }

    $countsByStatus = [];
    $countRes = $connect->query(
        "SELECT status_id, COUNT(*) AS cnt
         FROM leads
         WHERE (is_junk = 0 OR is_junk IS NULL)
         GROUP BY status_id"
    );
    if ($countRes) {
        while ($row = $countRes->fetch_assoc()) {
            $countsByStatus[(int) ($row['status_id'] ?? 0)] = (int) ($row['cnt'] ?? 0);
        }
    }

    $stages = [];
    foreach ($statuses as $statusRow) {
        $sid = (int) ($statusRow['id'] ?? 0);
        $colorClass = trim((string) ($statusRow['color_class'] ?? 'text-secondary'));
        if ($colorClass === '') {
            $colorClass = 'text-secondary';
        }
        $stages[] = [
            'id' => $sid,
            'name' => (string) ($statusRow['name'] ?? ''),
            'count' => (int) ($countsByStatus[$sid] ?? 0),
            'color_class' => $colorClass,
            'bar_color' => adminDashLeadStatusColorHex($colorClass),
            'href' => 'leads',
        ];
    }

    $conversions = [];
    for ($i = 1, $stageCount = count($stages); $i < $stageCount; $i++) {
        $prevCount = (int) ($stages[$i - 1]['count'] ?? 0);
        $currCount = (int) ($stages[$i]['count'] ?? 0);
        $pct = $prevCount > 0 ? (int) round(($currCount / $prevCount) * 100) : 0;
        $conversions[] = [
            'pct' => $pct,
            'label' => strtolower((string) ($stages[$i]['name'] ?? '')),
            'text' => $pct . '% to ' . strtolower((string) ($stages[$i]['name'] ?? '')),
            'color' => (string) ($stages[$i]['bar_color'] ?? '#2563eb'),
        ];
    }

    $dashSettings = null;
    if (isset($GLOBALS['settings']) && is_object($GLOBALS['settings'])) {
        $dashSettings = $GLOBALS['settings'];
    } elseif (class_exists('settings')) {
        $dashSettings = settings::findById(1);
    }

    $enabledCurrencies = [];
    if ($dashSettings && method_exists($dashSettings, 'getMultipleCurrencies')) {
        $enabledCurrencies = $dashSettings->getMultipleCurrencies();
    }
    if (!is_array($enabledCurrencies)) {
        $enabledCurrencies = [];
    }
    if ($enabledCurrencies === [] && $dashSettings && !empty($dashSettings->system_currency)) {
        $enabledCurrencies = [(string) $dashSettings->system_currency];
    }

    $systemCurrency = ($dashSettings && !empty($dashSettings->system_currency)) ? (string) $dashSettings->system_currency : 'USD,$';
    if ($systemCurrency !== '') {
        $systemBase = adminDashLeadCurrencyBaseCode($systemCurrency);
        $hasSystemCurrency = false;
        foreach ($enabledCurrencies as $enabledCurrency) {
            if (adminDashLeadCurrencyBaseCode((string) $enabledCurrency) === $systemBase) {
                $hasSystemCurrency = true;
                break;
            }
        }
        if (!$hasSystemCurrency) {
            array_unshift($enabledCurrencies, $systemCurrency);
        }
    }

    $totalsByDbCurrency = [];
    $valueRes = $connect->query(
        "SELECT currency, COUNT(*) AS cnt, SUM(COALESCE(lead_value, 0)) AS total
         FROM leads
         WHERE (is_junk = 0 OR is_junk IS NULL)
           AND (is_lost = 0 OR is_lost IS NULL)
           AND lead_value IS NOT NULL
           AND currency IS NOT NULL
           AND currency <> ''
         GROUP BY currency"
    );
    if ($valueRes) {
        while ($valueRow = $valueRes->fetch_assoc()) {
            $dbCurrency = trim((string) ($valueRow['currency'] ?? ''));
            if ($dbCurrency === '') {
                continue;
            }
            $totalsByDbCurrency[$dbCurrency] = [
                'cnt' => (int) ($valueRow['cnt'] ?? 0),
                'total' => (float) ($valueRow['total'] ?? 0),
            ];
        }
    }

    $metricsByCurrency = [];
    foreach ($enabledCurrencies as $enabledCurrency) {
        $enabledCurrency = trim((string) $enabledCurrency);
        if ($enabledCurrency === '') {
            continue;
        }
        $baseCode = adminDashLeadCurrencyBaseCode($enabledCurrency);
        $pipelineValue = 0.0;
        $valuedLeadCount = 0;
        foreach ($totalsByDbCurrency as $dbCurrency => $row) {
            if (adminDashLeadCurrencyBaseCode((string) $dbCurrency) !== $baseCode) {
                continue;
            }
            $pipelineValue += (float) ($row['total'] ?? 0);
            $valuedLeadCount += (int) ($row['cnt'] ?? 0);
        }
        $currencySymbol = getCurrencySymbol($enabledCurrency);
        $avgDeal = $valuedLeadCount > 0 ? ($pipelineValue / $valuedLeadCount) : 0.0;
        $metricsByCurrency[$enabledCurrency] = [
            'pipeline_value' => $pipelineValue,
            'pipeline_label' => $currencySymbol . number_format($pipelineValue, 0),
            'avg_label' => $currencySymbol . number_format($avgDeal, 0),
            'display_name' => adminDashLeadCurrencyDisplayName($enabledCurrency),
        ];
    }

    $defaultCurrency = $systemCurrency;
    if (!isset($metricsByCurrency[$defaultCurrency])) {
        $systemBase = adminDashLeadCurrencyBaseCode($systemCurrency);
        foreach (array_keys($metricsByCurrency) as $currencyKey) {
            if (adminDashLeadCurrencyBaseCode((string) $currencyKey) === $systemBase) {
                $defaultCurrency = (string) $currencyKey;
                break;
            }
        }
    }
    if (!isset($metricsByCurrency[$defaultCurrency])) {
        $defaultCurrency = (string) (array_key_first($metricsByCurrency) ?: $systemCurrency);
    }
    $defaultMetrics = $metricsByCurrency[$defaultCurrency] ?? [
        'pipeline_label' => getCurrencySymbol('') . '0',
        'avg_label' => getCurrencySymbol('') . '0',
        'display_name' => adminDashLeadCurrencyDisplayName($defaultCurrency),
    ];

    return [
        'ready' => true,
        'stages' => $stages,
        'conversions' => $conversions,
        'currencies' => array_keys($metricsByCurrency),
        'metrics_by_currency' => $metricsByCurrency,
        'default_currency' => $defaultCurrency,
        'pipeline_value' => (float) ($defaultMetrics['pipeline_value'] ?? 0),
        'pipeline_value_label' => (string) ($defaultMetrics['pipeline_label'] ?? getCurrencySymbol('') . '0'),
        'avg_deal_label' => (string) ($defaultMetrics['avg_label'] ?? getCurrencySymbol('') . '0'),
    ];
}

/**
 * Render leads pipeline dashboard widget (reference layout).
 */
function renderAdminDashLeadsPipelineWidget(array $data, array $lang): void
{
    if (empty($data['ready'])) {
        renderDashboardEmptyState(
            'leads',
            $lang['Leads module unavailable'] ?? 'Leads module unavailable.',
            ['list_item' => false, 'class' => 'dash-leads-pipeline-empty']
        );
        return;
    }

    $stages = is_array($data['stages'] ?? null) ? array_slice($data['stages'], 0, 4) : [];
    $conversions = is_array($data['conversions'] ?? null) ? $data['conversions'] : [];
    $metricsJson = htmlspecialchars(
        json_encode(is_array($data['metrics_by_currency'] ?? null) ? $data['metrics_by_currency'] : [], JSON_UNESCAPED_UNICODE),
        ENT_QUOTES,
        'UTF-8'
    );
    $defaultCurrency = htmlspecialchars((string) ($data['default_currency'] ?? ''), ENT_QUOTES, 'UTF-8');
    ?>
    <div class="dash-leads-pipeline"
         data-default-currency="<?php echo $defaultCurrency; ?>"
         data-currency-metrics="<?php echo $metricsJson; ?>">
        <?php if ($stages !== []) : ?>
        <div class="dash-leads-stages mt-4">
            <div class="dash-leads-stages-line" aria-hidden="true"></div>
            <div class="dash-leads-stages-cols">
                <?php foreach ($stages as $stage) :
                    $ringColor = (string) ($stage['bar_color'] ?? '#1e3a8a');
                    ?>
                <a href="<?php echo htmlspecialchars((string) ($stage['href'] ?? 'leads'), ENT_QUOTES, 'UTF-8'); ?>"
                   class="dash-leads-stage-col text-decoration-none">
                    <div class="dash-leads-stage-ring" style="border-color:<?php echo htmlspecialchars($ringColor, ENT_QUOTES, 'UTF-8'); ?>;">
                        <span class="dash-leads-stage-num"><?php echo (int) ($stage['count'] ?? 0); ?></span>
                    </div>
                    <div class="dash-leads-stage-label title font-size-12 mb-2"><?php echo htmlspecialchars((string) ($stage['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($conversions !== []) : ?>
        <div class="dash-leads-flow">
            <?php foreach ($conversions as $convIdx => $conversion) : ?>
                <?php if ($convIdx > 0) : ?>
                <span class="dash-leads-flow-sep" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="14" height="14">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
                    </svg>
                </span>
                <?php endif; ?>
                <div class="dash-leads-flow-block">
                    <div class="dash-leads-flow-pct" style="color:<?php echo htmlspecialchars((string) ($conversion['color'] ?? '#2563eb'), ENT_QUOTES, 'UTF-8'); ?>;"><?php echo (int) ($conversion['pct'] ?? 0); ?>%</div>
                    <div class="dash-leads-flow-label"><?php echo htmlspecialchars('to ' . (string) ($conversion['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="dash-leads-metrics">
            <div class="dash-leads-metric">
                <div class="dash-leads-metric-icon">
                    <?php echo ts_icon('payments', 'h-6'); ?>
                </div>
                <div class="dash-leads-metric-copy">
                    <div class="dash-leads-metric-label"><?php echo htmlspecialchars($lang['Pipeline value'] ?? 'Pipeline value', ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="dash-leads-metric-value" id="dash-leads-pipeline-value"><?php echo htmlspecialchars((string) ($data['pipeline_value_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>
            <div class="dash-leads-vsep" aria-hidden="true"></div>
            <div class="dash-leads-metric">
                <div class="dash-leads-metric-icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="22" height="22">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 18V10M9 18V6M14 18v-8M19 18V4"></path>
                    </svg>
                </div>
                <div class="dash-leads-metric-copy">
                    <div class="dash-leads-metric-label"><?php echo htmlspecialchars($lang['Avg. deal size'] ?? 'Avg. deal size', ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="dash-leads-metric-value" id="dash-leads-avg-deal-value"><?php echo htmlspecialchars((string) ($data['avg_deal_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

require_once __DIR__ . '/../includes/stat_sparkline_helper.php';
require_once __DIR__ . '/../includes/project_activity.php';

// Get current user data
$id = $session->userId;
$user = User::findById((int)$id);
if (!$user) {
    redirectTo($url . 'index.php');
}
$username = $user->firstName;
$email = $user->email;
$account_stat = $user->status;
$month = date('m');
$curr_year = date("Y");

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
// Dashboard counters — soft queries (never die() on missing/empty tables during fresh install)
$project_stats = array('total' => 0, 'in_progress' => 0, 'completed' => 0);
$project_stats_q = method_exists($database, 'querySoft')
    ? $database->querySoft(
        "SELECT COUNT(*) AS total,
                SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) AS in_progress,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS completed
         FROM projects"
    )
    : $database->query(
        "SELECT COUNT(*) AS total,
                SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) AS in_progress,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS completed
         FROM projects"
    );
if ($project_stats_q) {
    $project_stats = $database->fetchArray($project_stats_q) ?: $project_stats;
}
$total_projects = (int)($project_stats['total'] ?? 0);
$projects_ip = (int)($project_stats['in_progress'] ?? 0);
$projects_c = (int)($project_stats['completed'] ?? 0);

$user_stats = array('staff_count' => 0, 'client_count' => 0);
$user_stats_q = method_exists($database, 'querySoft')
    ? $database->querySoft(
        "SELECT SUM(CASE WHEN accountStatus = 3 THEN 1 ELSE 0 END) AS staff_count,
                SUM(CASE WHEN accountStatus = 2 THEN 1 ELSE 0 END) AS client_count
         FROM users"
    )
    : $database->query(
        "SELECT SUM(CASE WHEN accountStatus = 3 THEN 1 ELSE 0 END) AS staff_count,
                SUM(CASE WHEN accountStatus = 2 THEN 1 ELSE 0 END) AS client_count
         FROM users"
    );
if ($user_stats_q) {
    $user_stats = $database->fetchArray($user_stats_q) ?: $user_stats;
}
$staff_mem = (int)($user_stats['staff_count'] ?? 0);
$clients_mem = (int)($user_stats['client_count'] ?? 0);

$invoice_stats = array('unpaid_count' => 0, 'paid_count' => 0, 'paid_this_month_count' => 0);
if (!empty($isInvoicesEnabled)) {
$invoice_stats_sql = "SELECT SUM(CASE WHEN status = '0' THEN 1 ELSE 0 END) AS unpaid_count,
            SUM(CASE WHEN status = '1' THEN 1 ELSE 0 END) AS paid_count,
            SUM(CASE WHEN status = '1'
                     AND YEAR(releaseDate) = " . (int)$curr_year . "
                     AND MONTH(releaseDate) = " . (int)$month . " THEN 1 ELSE 0 END) AS paid_this_month_count
     FROM milestones
     WHERE (currency LIKE 'USD,%' OR currency = 'USD' OR currency LIKE '%,USD')";
$invoice_stats_q = method_exists($database, 'querySoft')
    ? $database->querySoft($invoice_stats_sql)
    : $database->query($invoice_stats_sql);
if ($invoice_stats_q) {
    $invoice_stats = $database->fetchArray($invoice_stats_q) ?: $invoice_stats;
}
}
$recvable_count = (int)($invoice_stats['unpaid_count'] ?? 0);
$paid_total_count = (int)($invoice_stats['paid_count'] ?? 0);
$this_month_count = (int)($invoice_stats['paid_this_month_count'] ?? 0);
$invoice_overview_total = $recvable_count + $paid_total_count;

$sparkFrom = $database->escapeValue(date('Y-m-01', strtotime('-11 months')));
$dashCurrencySql = " AND (currency LIKE 'USD,%' OR currency = 'USD' OR currency LIKE '%,USD')";
$milestoneSparkDate = statSparklineSqlMilestoneEffectiveDate();
$sparkClients = statSparklineSeriesFromQuery(
    $database,
    "SELECT DATE_FORMAT(regDate, '%Y-%m') AS ym, COUNT(*) AS c
     FROM users
     WHERE accountStatus = 2
       AND " . statSparklineSqlUnixValid('regDate') . "
       AND regDate >= '{$sparkFrom}'
     GROUP BY ym"
);
$sparkStaff = statSparklineSeriesFromQuery(
    $database,
    "SELECT DATE_FORMAT(regDate, '%Y-%m') AS ym, COUNT(*) AS c
     FROM users
     WHERE accountStatus = 3
       AND " . statSparklineSqlUnixValid('regDate') . "
       AND regDate >= '{$sparkFrom}'
     GROUP BY ym"
);
$sparkUnpaid = array();
$sparkPaid = array();
if (!empty($isInvoicesEnabled)) {
    $sparkUnpaid = statSparklineSeriesFromQuery(
        $database,
        "SELECT DATE_FORMAT({$milestoneSparkDate}, '%Y-%m') AS ym, COUNT(*) AS c
         FROM milestones
         WHERE status = '0'
           AND {$milestoneSparkDate} IS NOT NULL
           AND {$milestoneSparkDate} >= '{$sparkFrom}'
           {$dashCurrencySql}
         GROUP BY ym"
    );
    $sparkPaid = statSparklineSeriesFromQuery(
        $database,
        "SELECT DATE_FORMAT(releaseDate, '%Y-%m') AS ym, COUNT(*) AS c
         FROM milestones
         WHERE status = '1'
           AND " . statSparklineSqlUnixValid('releaseDate') . "
           AND releaseDate >= '{$sparkFrom}'
           {$dashCurrencySql}
         GROUP BY ym"
    );
}

// Counter meta (admin spark cards)
$clientsNewThisMonth = !empty($sparkClients) ? (int) end($sparkClients) : 0;
$clientsNewLastMonth = (count($sparkClients) > 1) ? (int) $sparkClients[count($sparkClients) - 2] : 0;
$clientsPctChange = $clientsNewLastMonth > 0
    ? (int) round((($clientsNewThisMonth - $clientsNewLastMonth) / $clientsNewLastMonth) * 100)
    : ($clientsNewThisMonth > 0 ? 100 : 0);

$staffNewThisMonth = !empty($sparkStaff) ? (int) end($sparkStaff) : 0;
$staffNewLastMonth = (count($sparkStaff) > 1) ? (int) $sparkStaff[count($sparkStaff) - 2] : 0;
$staffPctChange = $staffNewLastMonth > 0
    ? (int) round((($staffNewThisMonth - $staffNewLastMonth) / $staffNewLastMonth) * 100)
    : ($staffNewThisMonth > 0 ? 100 : 0);
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
$clientsOnlineRow = $database->fetchArray($database->query(
    "SELECT COUNT(*) AS c
     FROM users
     WHERE accountStatus = 2
       AND session_status = 'online'
       AND last_seen > 0
       AND last_seen >= " . (int) $staffPresenceCutoff
));
$clientsOnlineNow = (int) ($clientsOnlineRow['c'] ?? 0);
$clientsOnlineTooltip = $clientsOnlineNow > 0
    ? (int) $clientsOnlineNow . ' ' . ($lang['Online'] ?? 'online')
    : ($lang['Offline'] ?? 'Offline');
$staffOnlineTooltip = $staffActiveToday > 0
    ? (int) $staffActiveToday . ' ' . ($lang['Online'] ?? 'online')
    : ($lang['Offline'] ?? 'Offline');

require_once __DIR__ . '/../includes/milestone_invoice_total.php';
$dashCurrencySymbol = '$';
$dashMetaMonthStart = date('Y-m-01');
$dashMetaMonthEnd = date('Y-m-t');
$dashMetaLastStart = date('Y-m-01', strtotime('-1 month'));
$dashMetaLastEnd = date('Y-m-t', strtotime('-1 month'));
$dashCurrencyWhere = "(currency LIKE 'USD,%' OR currency = 'USD' OR currency LIKE '%,USD')";

$unpaidAmount = 0.0;
$paidAmountAll = 0.0;
$paidThisAmount = 0.0;
$paidLastAmount = 0.0;
$paidLastMonthCount = 0;
$invoicePaidPctChange = 0;

if (!empty($isInvoicesEnabled)) {
/**
 * Fast milestone amount for dashboard cards (budget sum).
 * Precise totals load via analytics.js → invoice_overview.php / monthly_range.php.
 */
$adminDashBudgetSum = static function (string $extraWhere) use ($database): float {
    $row = $database->fetchArray($database->query(
        'SELECT COALESCE(SUM(COALESCE(budget, 0)), 0) AS total FROM milestones WHERE ' . $extraWhere
    ));
    return (float) ($row['total'] ?? 0);
};

$unpaidAmount = $adminDashBudgetSum("(status = '0' OR status = 0) AND {$dashCurrencyWhere}");
$paidAmountAll = $adminDashBudgetSum("(status = '1' OR status = 1) AND {$dashCurrencyWhere}");
$paidThisAmount = $adminDashBudgetSum(
    "(status = '1' OR status = 1) AND releaseDate IS NOT NULL"
    . " AND releaseDate >= '" . $database->escapeValue($dashMetaMonthStart) . "'"
    . " AND releaseDate <= '" . $database->escapeValue($dashMetaMonthEnd) . "'"
    . " AND {$dashCurrencyWhere}"
);
$paidLastAmount = $adminDashBudgetSum(
    "(status = '1' OR status = 1) AND releaseDate IS NOT NULL"
    . " AND releaseDate >= '" . $database->escapeValue($dashMetaLastStart) . "'"
    . " AND releaseDate <= '" . $database->escapeValue($dashMetaLastEnd) . "'"
    . " AND {$dashCurrencyWhere}"
);

$paidLastMonthCountRow = $database->fetchArray($database->query(
    "SELECT COUNT(*) AS c FROM milestones
     WHERE (status = '1' OR status = 1)
       AND releaseDate IS NOT NULL
       AND releaseDate >= '" . $database->escapeValue($dashMetaLastStart) . "'
       AND releaseDate <= '" . $database->escapeValue($dashMetaLastEnd) . "'
       AND {$dashCurrencyWhere}"
));
$paidLastMonthCount = (int) ($paidLastMonthCountRow['c'] ?? 0);
$invoicePaidPctChange = $paidLastMonthCount > 0
    ? (int) round((($this_month_count - $paidLastMonthCount) / $paidLastMonthCount) * 100)
    : ($this_month_count > 0 ? 100 : 0);
}

$fmtDashMoney = static function ($amount, $symbol) {
    return $symbol . number_format((float) $amount, 2, '.', ',');
};

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

// Open tasks (COUNT only)
$open_tasks_this_q = method_exists($database, 'querySoft')
    ? $database->querySoft(
        "SELECT COUNT(*) AS c FROM tasks WHERE status = 'todo'
         AND " . reports_sql_valid_date('start_date') . "
         AND start_date <= '" . $database->escapeValue($month_end) . "'
         AND " . reports_sql_valid_date('due_date') . "
         AND due_date >= '" . $database->escapeValue($month_start) . "'"
    )
    : $database->query(
        "SELECT COUNT(*) AS c FROM tasks WHERE status = 'todo'
         AND " . reports_sql_valid_date('start_date') . "
         AND start_date <= '" . $database->escapeValue($month_end) . "'
         AND " . reports_sql_valid_date('due_date') . "
         AND due_date >= '" . $database->escapeValue($month_start) . "'"
    );
$open_tasks_this_row = $open_tasks_this_q ? ($database->fetchArray($open_tasks_this_q) ?: array('c' => 0)) : array('c' => 0);
$open_tasks_last_q = method_exists($database, 'querySoft')
    ? $database->querySoft(
        "SELECT COUNT(*) AS c FROM tasks WHERE status = 'todo'
         AND " . reports_sql_valid_date('start_date') . "
         AND start_date <= '" . $database->escapeValue($last_month_end) . "'
         AND " . reports_sql_valid_date('due_date') . "
         AND due_date >= '" . $database->escapeValue($last_month_start) . "'"
    )
    : $database->query(
        "SELECT COUNT(*) AS c FROM tasks WHERE status = 'todo'
         AND " . reports_sql_valid_date('start_date') . "
         AND start_date <= '" . $database->escapeValue($last_month_end) . "'
         AND " . reports_sql_valid_date('due_date') . "
         AND due_date >= '" . $database->escapeValue($last_month_start) . "'"
    );
$open_tasks_last_row = $open_tasks_last_q ? ($database->fetchArray($open_tasks_last_q) ?: array('c' => 0)) : array('c' => 0);
$this_count = (int)($open_tasks_this_row['c'] ?? 0);
$last_count = (int)($open_tasks_last_row['c'] ?? 0);
$percent_change = $last_count > 0 ? 
    round((($this_count - $last_count) / $last_count) * 100, 2) : 
    ($this_count > 0 ? 100 : 0);

// Open projects (COUNT only)
$open_projects_this_row = $database->fetchArray($database->query(
    "SELECT COUNT(*) AS c FROM projects WHERE status = 0 AND start_time <= '" . $database->escapeValue($month_end) . "' AND end_time >= '" . $database->escapeValue($month_start) . "'"
));
$open_projects_last_row = $database->fetchArray($database->query(
    "SELECT COUNT(*) AS c FROM projects WHERE status = 0 AND start_time <= '" . $database->escapeValue($last_month_end) . "' AND end_time >= '" . $database->escapeValue($last_month_start) . "'"
));
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
    $sql = "SELECT * FROM private_notes WHERE user_id = " . (int)$user_id . " ORDER BY updated_at DESC LIMIT 5";
    $result = $database->query($sql);
    while ($row = $database->fetchArray($result)) {
        // Decrypt content for display
        if (!empty($row['content'])) {
            $row['content'] = decryptString($row['content']);
        }
        $notes[] = $row;
    }
}

// Team workload — top 5 staff by open assigned tasks
$teamWorkloadRows = [];
$teamWorkloadMaxOpen = 1;
$teamWorkloadEmptyLabel = $lang['No staff found.'] ?? 'No staff found.';
try {
    global $connect;

    $teamHasLastNameCol = function_exists('crm_db_column_exists')
        ? crm_db_column_exists($connect, 'users', 'last_name')
        : true;
    if (!function_exists('crm_db_column_exists') && isset($connect) && $connect instanceof mysqli) {
        $teamLastNameCheck = $connect->query("SHOW COLUMNS FROM users LIKE 'last_name'");
        $teamHasLastNameCol = ($teamLastNameCheck && $teamLastNameCheck->num_rows > 0);
    }

    $teamRoleNameExpr = 'NULL AS role_name';
    if (isset($connect) && $connect instanceof mysqli) {
        $hasRoles = function_exists('crm_db_table_exists')
            ? crm_db_table_exists($connect, 'roles')
            : (($tmp = $connect->query("SHOW TABLES LIKE 'roles'")) && $tmp->num_rows > 0);
        if ($hasRoles) {
            $teamRoleNameExpr = '(SELECT r.name FROM roles r WHERE r.id = u.role_id LIMIT 1) AS role_name';
        }
    }

    $teamTaskAssignMatch = method_exists('Task', 'sqlAssignedToUserId')
        ? Task::sqlAssignedToUserId('u.id', 't')
        : "(FIND_IN_SET(u.id, t.assigned_to) > 0 OR TRIM(COALESCE(t.assigned_to, '')) = CAST(u.id AS CHAR))";
    $teamActiveWhere = Task::activeTasksWhere('t');
    $teamLastNameSelect = $teamHasLastNameCol ? 'u.last_name' : "'' AS last_name";
    $teamWorkloadSql = "
        SELECT u.id, u.firstName, {$teamLastNameSelect}, u.title, u.accountStatus,
               {$teamRoleNameExpr},
               COUNT(t.id) AS open_tasks
        FROM users u
        LEFT JOIN tasks t
          ON {$teamTaskAssignMatch}
         AND t.status != 'done'
         AND {$teamActiveWhere}
        WHERE u.accountStatus = 3
          AND u.status = 0
        GROUP BY u.id
        ORDER BY open_tasks DESC, u.firstName ASC, u.id ASC
        LIMIT 5
    ";

    $teamWorkloadResult = method_exists($database, 'querySoft')
        ? $database->querySoft($teamWorkloadSql)
        : $database->query($teamWorkloadSql);

    if ($teamWorkloadResult) {
        while ($teamRow = $database->fetchArray($teamWorkloadResult)) {
            $openTasks = (int) ($teamRow['open_tasks'] ?? 0);
            if ($openTasks > $teamWorkloadMaxOpen) {
                $teamWorkloadMaxOpen = $openTasks;
            }
            $firstName = trim((string) ($teamRow['firstName'] ?? ''));
            $lastName = trim((string) ($teamRow['last_name'] ?? ''));
            $displayName = $firstName;
            if ($lastName !== '') {
                $displayName .= ' ' . strtoupper(substr($lastName, 0, 1)) . '.';
            }
            $roleLabel = trim((string) ($teamRow['title'] ?? ''));
            if ($roleLabel === '') {
                $roleLabel = trim((string) ($teamRow['role_name'] ?? ''));
            }
            if ($roleLabel === '') {
                $roleLabel = $lang['Staff'] ?? 'Staff';
            }
            $teamWorkloadRows[] = [
                'id' => (int) ($teamRow['id'] ?? 0),
                'firstName' => $firstName,
                'lastName' => $lastName,
                'name' => $displayName,
                'role' => $roleLabel,
                'open_tasks' => $openTasks,
            ];
        }
    }

    if ($teamWorkloadRows !== []) {
        foreach ($teamWorkloadRows as &$teamWorkloadItem) {
            $openTasks = (int) $teamWorkloadItem['open_tasks'];
            $barPct = $teamWorkloadMaxOpen > 0
                ? (int) round(($openTasks / $teamWorkloadMaxOpen) * 100)
                : 0;
            if ($openTasks <= 0) {
                $barColor = '#4caf50';
            } elseif ($barPct >= 85 || $openTasks >= 10) {
                $barColor = '#f66';
            } elseif ($barPct >= 55 || $openTasks >= 6) {
                $barColor = '#f9b233';
            } else {
                $barColor = '#4caf50';
            }
            $teamWorkloadItem['bar_pct'] = $barPct;
            $teamWorkloadItem['bar_color'] = $barColor;
        }
        unset($teamWorkloadItem);
    }
} catch (Throwable $teamWorkloadErr) {
    error_log('[admin dashboard team workload] ' . $teamWorkloadErr->getMessage());
}

// Admin dashboard bottom feed
require_once __DIR__ . '/../includes/notifications.php';

$dashRecentActivityItems = [];
$dashUpcomingItems = [];
$dashLeadsPipeline = ['ready' => false, 'stages' => [], 'conversions' => []];

try {
    $dashActivityNotifs = Notifications::getUserNotificationsGrouped((int) $session->userId, 5, 0);
    if (is_array($dashActivityNotifs)) {
        foreach ($dashActivityNotifs as $dashActivityNotif) {
            $dashRecentActivityItems[] = activityPageApplyRoleToFeedItem(
                adminDashFeedItemFromNotification($dashActivityNotif, $lang),
                'admin/',
                (string) $url
            );
        }
    }
} catch (Throwable $dashActivityErr) {
    error_log('[admin dashboard activity feed] ' . $dashActivityErr->getMessage());
}

$dashboardUsersById = [];
try {
    $dashUpcomingItems = adminDashUpcomingBuildItems($lang, $dashboardUsersById);
} catch (Throwable $dashUpcomingErr) {
    error_log('[admin dashboard upcoming widget] ' . $dashUpcomingErr->getMessage());
    $dashUpcomingItems = [];
}

try {
    $dashLeadsPipeline = adminDashLeadsPipelineBuild($lang);
} catch (Throwable $dashLeadsErr) {
    error_log('[admin dashboard leads pipeline] ' . $dashLeadsErr->getMessage());
    $dashLeadsPipeline = ['ready' => false, 'stages' => [], 'conversions' => []];
}

$adminSkeletonPath = __DIR__ . '/../partials/admin_dashboard_skeleton.inc.php';
$adminShowSkeletonLoading = is_file($adminSkeletonPath);
?>
<link rel="stylesheet" href="<?php echo $url; ?>assets/css/reports.css">
<?php if ($adminShowSkeletonLoading) : ?>
<style id="admin-dashboard-critical-loading-css">
.admin-dashboard.reports-page.reports-loading .reports-dashboard-live{display:none!important}
.admin-dashboard.reports-page:not(.reports-loading) .reports-dashboard-skeleton{display:none!important}
</style>
<?php endif; ?>
<div class="page-container admin-dashboard">
  <div class="container-fluid">
    <div class="row row-eq-height">
      <?php include("../templates/sidebar.php"); ?>
      <div class="page-content dashboard-page" style="padding-bottom:0px !important;">
        <?php include('../templates/top-header.php'); ?>
        <div class="reports-page<?php echo $adminShowSkeletonLoading ? ' reports-loading' : ''; ?>">
        <div class="extra-page-pd">
        <?php if ($adminShowSkeletonLoading) {
            admin_render_dashboard_skeleton();
        } ?>
        <div class="reports-dashboard-live">
           <?php
           $__aiDash = __DIR__ . '/../includes/ai_dashboard_widget.php';
           if (is_file($__aiDash)) {
               include $__aiDash;
           }
           ?>
           <div class="row">
                       <div class="col-md-12 col-lg-4">
                    <div class="widget-card shadow center-align pie-chart">

                              <div class="d-flex justify-content-between align-items-center">
                                    <div class="card-title">
                                        <h3><?php echo $lang['Total Projects']; ?></h3>
                                    </div>
                                    <div class="border-btn">
                                        <a href="<?php echo $url; ?>admin/projects">
                                            <?php echo $lang['View all']; ?>
                                        </a>
                                    </div>
                                </div>
                                      <div class="chart-pie pt-4 pb-2" style="width: 250px; height: 250px; margin: 0 auto;">
                                        <canvas id="myPieChart" width="350" height="350"></canvas>
				                         <div class="total-pro">
                                         <h2><?php echo $total_projects; ?></h2><span><?php echo $lang['All projects']; ?></span>
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
			<div class="row counter-align">
                <div class="col-sm-6 col-6"> 
                       <div class="widget-card">
                                <div class="grey d-flex align-items-center grey col-gap-5">
                                    <span><?php echo $lang['Open Project']; ?></span>
                                    <i  style="color:#888;cursor:pointer;" 
                                       data-bs-toggle="tooltip" 
                                       data-bs-placement="top" 
                                       title="<?php echo $lang['This count shows the number of open projects that were active at any point during this month.']; ?>"><?php echo ts_icon('info', 'w-6'); ?>
                                    </i>
                                </div>
                                <div class="counts"><?php echo $open_projects_this_count; ?></div>
                               <div class="up-down">
								<?php
									if ($open_projects_percent_change > 0) {
										echo ts_icon('arrow-up-right');
									} elseif ($open_projects_percent_change < 0) {
										echo ts_icon('arrow-down-right');
									} else {
										echo ts_icon('arrows-up-down');
									}
								?>
							</div>
												   <div class="grey persent-count">
								<?php
									if ($open_projects_percent_change > 0) {
										echo "<span style='color:green;'>{$open_projects_percent_change}%</span> Since last month";
									} elseif ($open_projects_percent_change < 0) {
										echo "<span style='color:red;'>" . abs($open_projects_percent_change) . "%</span> Since last month";
									} else {
										echo "0% Since last month";
									}
								?>
							</div>
                    </div>
			</div>
                  <div class="col-sm-6 col-6"> 
						<div class="widget-card">
                                <div class="grey  d-flex align-items-center grey col-gap-5"><span><?php echo $lang['Open Task']; ?></span>
								 <i style="color:#888;cursor:pointer;" 
                                       data-bs-toggle="tooltip" 
                                       data-bs-placement="top" 
                                       title="<?php echo $lang['This count shows the number of open task that were active at any point during this month.']; ?>"><?php echo ts_icon('info', 'w-6'); ?></i>
                                    </div>
                                <div class="counts dash-rttb"><?php echo $this_count; ?></div>
									<div class="up-down">
										<?php
											if ($percent_change > 0) {
												echo ts_icon('arrow-up-right');
											} elseif ($percent_change < 0) {
												echo ts_icon('arrow-down-right');
											} else {
												echo ts_icon('arrows-up-down');
											}
										?>
									</div>
											<div class="grey persent-count">
												<?php
													if ($percent_change > 0) {
														echo "<span style='color:green;'>{$percent_change}%</span> Since last month";
													} elseif ($percent_change < 0) {
														echo "<span style='color:red;'>" . abs($percent_change) . "%</span> Since last month";
													} else {
														echo "0% Since last month";
													}
												?>
											</div>
                                        </div>
                                    </div>
                                </div>
							</div>
                   <div class="col-md-12 col-lg-8">
                       <div class="row counter-align">
                          <div class="col-lg-3 col-sm-6 col-6"> 
                              <div class="widget-card dash-counter stat-spark-card stat-spark-card--clients widget-card--linked">    
                                <div class="stat-invoice-head">
                                    <div class="grey"><span><?php echo $lang['Total Clients']; ?></span></div>
                                    <span class="stat-invoice-badge<?php echo $clientsOnlineNow > 0 ? ' stat-invoice-badge--online' : ''; ?>"
                                          data-bs-toggle="tooltip"
                                          data-bs-placement="top"
                                          title="<?php echo htmlspecialchars($clientsOnlineTooltip, ENT_QUOTES, 'UTF-8'); ?>"><?php
                                        if ($clientsOnlineNow > 0) {
                                            echo (int) $clientsOnlineNow . ' ' . htmlspecialchars($lang['Online'] ?? 'online', ENT_QUOTES, 'UTF-8');
                                        } else {
                                            echo htmlspecialchars($lang['Offline'] ?? 'Offline', ENT_QUOTES, 'UTF-8');
                                        }
                                    ?></span>
                                </div>
                                <div class="counts dash-rttb"><?php echo $clients_mem; ?></div>
                                <div class="grey persent-count">
                                    <?php
                                    if ($clientsPctChange > 0) {
                                        echo '<span style="color:green;">' . ts_icon('arrow-up-right', 'w-4') . ' ' . (int) $clientsPctChange . '%</span> vs last month';
                                    } elseif ($clientsPctChange < 0) {
                                        echo '<span style="color:red;">' . ts_icon('arrow-down-right', 'w-4') . ' ' . abs((int) $clientsPctChange) . '%</span> vs last month';
                                    } else {
                                        echo ts_icon('arrows-up-down', 'w-4') . ' 0% vs last month';
                                    }
                                    ?>
                                </div>
                                <?php renderAdminStatSparkline('clients', $sparkClients); ?>
                                <a href="<?php echo $url; ?>admin/clients" class="widget-card__stretch-link" aria-label="<?php echo htmlspecialchars($lang['View all clients'], ENT_QUOTES, 'UTF-8'); ?>"></a>
                            </div>
                          </div>
						<div class="col-lg-3 col-sm-6 col-6">
							<div class="widget-card stat-spark-card stat-spark-card--staff widget-card--linked">    
                                <div class="stat-invoice-head">
                                    <div class="grey"><span><?php echo $lang['Total Staff']; ?></span></div>
                                    <span class="stat-invoice-badge<?php echo $staffActiveToday > 0 ? ' stat-invoice-badge--online' : ''; ?>"
                                          data-bs-toggle="tooltip"
                                          data-bs-placement="top"
                                          title="<?php echo htmlspecialchars($staffOnlineTooltip, ENT_QUOTES, 'UTF-8'); ?>"><?php
                                        if ($staffActiveToday > 0) {
                                            echo (int) $staffActiveToday . ' ' . htmlspecialchars($lang['Online'] ?? 'online', ENT_QUOTES, 'UTF-8');
                                        } else {
                                            echo htmlspecialchars($lang['Offline'] ?? 'Offline', ENT_QUOTES, 'UTF-8');
                                        }
                                    ?></span>
                                </div>
									<div class="counts dash-rttb"><?php echo $staff_mem; ?></div>
									<div class="grey persent-count">
										<?php
										if ($staffPctChange > 0) {
											echo '<span style="color:green;">' . ts_icon('arrow-up-right', 'w-4') . ' ' . (int) $staffPctChange . '%</span> vs last month';
										} elseif ($staffPctChange < 0) {
											echo '<span style="color:red;">' . ts_icon('arrow-down-right', 'w-4') . ' ' . abs((int) $staffPctChange) . '%</span> vs last month';
										} else {
											echo ts_icon('arrows-up-down', 'w-4') . ' 0% vs last month';
										}
										?>
									</div>
									<?php renderAdminStatSparkline('staff', $sparkStaff); ?>
									<a href="<?php echo $url; ?>admin/members" class="widget-card__stretch-link" aria-label="<?php echo htmlspecialchars($lang['View members'], ENT_QUOTES, 'UTF-8'); ?>"></a>
							</div>
						</div>
						<div class="col-lg-6 col-sm-12">
							<div class="widget-card stat-spark-card stat-spark-card--invoice-overview widget-card--linked" id="invoice-overview-card">
								<div class="stat-invoice-head">
									<div class="grey"><span>Invoice Overview</span></div>
									<span class="stat-invoice-badge" id="invoice-overview-badge"><?php echo (int) $invoice_overview_total; ?> total</span>
								</div>
								<div class="stat-invoice-grid">
									<div class="stat-invoice-col">
										<div class="counts dash-rttb is-paid mt-0" id="invoice-overview-paid-count"><?php echo (int) $paid_total_count; ?></div>
										<div class="grey persent-count" id="invoice-overview-paid-meta"><?php echo $lang['Paid Invoices']; ?> &middot; <?php echo htmlspecialchars($fmtDashMoney($paidAmountAll, $dashCurrencySymbol), ENT_QUOTES, 'UTF-8'); ?></div>
									</div>
									<div class="stat-invoice-col">
										<div class="counts dash-rttb is-unpaid mt-0" id="invoice-overview-unpaid-count"><?php echo (int) $recvable_count; ?></div>
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
								<a href="<?php echo $url; ?>admin/invoices" class="widget-card__stretch-link" aria-label="<?php echo htmlspecialchars($lang['View Invoices'], ENT_QUOTES, 'UTF-8'); ?>"></a>
							</div>
						</div>
					<div class="col-12">
						<?php if (!empty($isFreeEditionDash) && $salesStatsLocked && function_exists('tasksession_render_pro_widget_overlay')): ?>
						<div class="widget-card sales-stats-pro-lock">
							<?php tasksession_render_pro_widget_overlay('payments'); ?>
						</div>
						<?php else: ?>
						<div class="widget-card <?php echo $salesStatsLocked ? 'sales-stats-disabled' : ''; ?>">
                            <?php if ($salesStatsLocked): ?>
                            <div class="sales-stats-overlay">
                                <div class="overlay-card">
                                    <h3><?php echo htmlspecialchars($lang['Payments module disabled'] ?? 'Payments module disabled', ENT_QUOTES, 'UTF-8'); ?></h3>
                                    <p><?php echo htmlspecialchars($lang['invoice_reports_module_overlay'] ?? 'Enable invoicing and payment features to manage your business transactions efficiently.', ENT_QUOTES, 'UTF-8'); ?></p>
                                    <a href="<?php echo $url; ?>admin/system-settings" class="primary-btn"><?php echo htmlspecialchars($lang['Enable Now'] ?? 'Enable Now', ENT_QUOTES, 'UTF-8'); ?></a>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="sales-stats-content">
							<div class="d-flex justify-content-between align-items-center mb-2">
                                 <div class="card-title">
                                   <h3><?php echo $lang['Sales Statistics']; ?></h3>
                                   </div>

                                   <div class="d-flex align-items-center col-gap-10">
                                     <!-- Currency Filter Dropdown -->
                                     <div class="dropdown-btn">
                                       <div class="dropdown">
                                                                                 <button class="btn btn-light dropdown-toggle" type="button" id="currencyFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                          United States ($)
                                        </button>
                                         <ul class="dropdown-menu p-3" aria-labelledby="currencyFilterDropdown" style="min-width: 200px;">
                                           <li style="display: none;"><button class="dropdown-item" type="button" onclick="selectCurrency('all')"><?php echo $lang['All Currencies']; ?></button></li>
                                           <?php
                                           // Get enabled currencies from settings
                                           $settings = Settings::findById(1);
                                           if ($settings) {
                                               $enabledCurrencies = $settings->getMultipleCurrencies();
                                               foreach ($enabledCurrencies as $currency) {
                                                   $currency = trim($currency);
                                                   if (!empty($currency)) {
                                                       $currencyParts = explode(',', $currency);
                                                       $currencyCode = trim($currencyParts[0]);
                                                       $currencySymbol = isset($currencyParts[1]) ? trim($currencyParts[1]) : $currencyCode;
                                                       
                                                       // Get country name for display
                                                       $countryName = '';
                                                       switch ($currencyCode) {
                                                           case 'USD':
                                                               $countryName = 'United States';
                                                               break;
                                                           case 'PKR':
                                                               $countryName = 'Pakistan';
                                                               break;
                                                           case 'Rs':
                                                               $countryName = 'Pakistan';
                                                               break;
                                                           case 'CAD':
                                                               $countryName = 'Canada';
                                                               break;
                                                           default:
                                                               $countryName = $currencyCode;
                                                       }
                                                       
                                                       $displayName = $countryName . ' (' . $currencySymbol . ')';
                                                       echo '<li><button class="dropdown-item" type="button" onclick="selectCurrency(\'' . $currency . '\')">' . $displayName . '</button></li>';
                                                   }
                                               }
                                           }
                                           ?>
                                         </ul>
                                       </div>
                                     </div>
                                     
                                     <!-- Date Range Dropdown -->
                                     <div class="dropdown-btn">
                                       <div class="dropdown">
                                         <button class="btn btn-light dropdown-toggle" type="button" id="customRangeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                           <?php echo $lang['This Year (Jan - Dec)']; ?>
                                         </button>
                                         <ul class="dropdown-menu p-3" aria-labelledby="customRangeDropdown" style="min-width: 300px;">
                                           <li><button class="dropdown-item" type="button" onclick="selectQuickRange('last7')"><?php echo $lang['Last 7 days']; ?></button></li>
                                           <li><button class="dropdown-item" type="button" onclick="selectQuickRange('last30')"><?php echo $lang['Last 30 days']; ?></button></li>
                                           <li><button class="dropdown-item" type="button" onclick="selectQuickRange('last90')"><?php echo $lang['Last 90 days']; ?></button></li>
                                           <li><button class="dropdown-item" type="button" onclick="selectQuickRange('last6months')"><?php echo $lang['Last 6 months']; ?></button></li>
                                           <li><button class="dropdown-item" type="button" onclick="selectQuickRange('thisYear')"><?php echo $lang['This Year (Jan - Dec)']; ?></button></li>
                                           <li><hr class="dropdown-divider"></li>
                                           <li>
                                             <div class="px-2 sales-stats-custom-range">
                                               <label><?php echo $lang['Custom']; ?></label>
                                               <input type="date" id="customStart" class="form-control mb-2">
                                               <input type="date" id="customEnd" class="form-control mb-2">
                                               <button class="primary-btn w-100" type="button" onclick="selectCustomRange()"><?php echo $lang['Apply']; ?></button>
                                             </div>
                                           </li>
                                         </ul>
                                       </div>
                                     </div>
                                   </div>
                              </div>
						 <div class="monthly-rev">
						  <div class="task-reports-bar-summary sales-stats-bar-summary" id="salesStatsBarSummary">
							<div class="month-rps flex-grow invoice-financial-summary-main" id="month-rps">
								<div class="invoice-financial-summary-item is-chart-toggle" data-dataset-index="0" role="button" tabindex="0" title="<?php echo htmlspecialchars($lang['Paid'] ?? 'Paid', ENT_QUOTES, 'UTF-8'); ?>">
									<span class="invoice-financial-summary-label invoice-financial-summary-label--paid"><?php echo $lang['Paid'] ?? 'Paid'; ?></span>
									<span class="invoice-financial-summary-value" id="salesStatsPaid">—</span>
								</div>
								<div class="invoice-financial-summary-item is-chart-toggle" data-dataset-index="1" role="button" tabindex="0" title="<?php echo htmlspecialchars($lang['Unpaid'] ?? 'Unpaid', ENT_QUOTES, 'UTF-8'); ?>">
									<span class="invoice-financial-summary-label invoice-financial-summary-label--unpaid"><?php echo $lang['Unpaid'] ?? 'Unpaid'; ?></span>
									<span class="invoice-financial-summary-value" id="salesStatsUnpaid">—</span>
								</div>
								<div class="invoice-financial-summary-item">
									<span class="invoice-financial-summary-label invoice-financial-summary-label--tax"><?php echo $lang['Sales Tax'] ?? 'Sales Tax'; ?></span>
									<span class="invoice-financial-summary-value" id="salesStatsTax">—</span>
								</div>
								<div class="invoice-financial-summary-item is-chart-toggle" data-dataset-index="2" role="button" tabindex="0" title="<?php echo htmlspecialchars($lang['Cancel'] ?? 'Cancel', ENT_QUOTES, 'UTF-8'); ?>">
									<span class="invoice-financial-summary-label invoice-financial-summary-label--cancelled"><?php echo $lang['Cancel'] ?? 'Cancel'; ?></span>
									<span class="invoice-financial-summary-value" id="salesStatsCancelled">—</span>
								</div>
							</div>
							<div class="task-reports-bar-summary-meta" id="salesStatsBarSummaryMeta">
								<span class="task-reports-bar-summary-arrow" id="salesStatsBarSummaryArrow"></span>
								<span class="task-reports-bar-summary-pct" id="salesStatsBarSummaryPct"></span>
								<span class="task-reports-bar-summary-sep" id="salesStatsBarSummarySep" aria-hidden="true">·</span>
								<span class="task-reports-bar-summary-vs" id="salesStatsBarSummaryVs"></span>
							</div>
						   </div>
						 </div>
						<div class="stats-graph">
					      <canvas id="earningsLineChart"></canvas>
						</div>
                        </div>
						</div>
						<?php endif; ?>
					</div>
				  </div>
			   </div>
			 </div>
					 <div class="row db-container ">
                        <div class="col-lg-12 col-md-12 col-sm-12">
                            <div class="db-box-wrap widget-card ">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="card-title">
                                        <h3><?php echo $lang['Running Projects']; ?></h3>
                                    </div>
                                    <div class="border-btn">
                                        <a href="<?php echo $url; ?>admin/projects">
                                            <?php echo $lang['View all']; ?>
                                        </a>
                                    </div>
                                </div>
                                <div class="col-12">
								                                    <?php
                                    $recentProjects = projects::findBySql("select * from projects WHERE archive=0 AND trash != 1 ORDER BY start_time DESC LIMIT 5");
                                    $recentProjectTaskStats = [];
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
                                                                <li>
                                                                    <a href="edit-project?id=<?php echo $recentProject->p_id;?>" class="dropdown-item"><?php echo ts_icon('edit', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Edit Project']; ?></a>
                                                                </li>
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
                                                                <li>
                                                                    <form method="post" action="projects" style="display:inline;">
                                                                        <input type="hidden" value="<?php echo $recentProject->p_id;?>" name="del_id" />
                                                                        <input type="hidden" value="<?php if($recentProject->trash == 0){ echo '1';}else { echo '0';} ?>" name="del_val" />
                                                                        <button type="submit" name="del_proj" class="dropdown-item">
                                                                          <?php echo ts_icon('delete', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Delete Project']; ?>
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
                                                                    $additionalClients = array_filter($allClientIds, function ($id) use ($recentProject) {
                                                                        return $id > 0
                                                                            && $id != (int) $recentProject->main_client_id
                                                                            && $id != (int) $recentProject->c_id;
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
                                    renderAdminDashModuleDisabledOverlay(
                                        $url,
                                        $lang,
                                        $lang['Tasks module disabled'] ?? 'Tasks module disabled',
                                        $lang['tasks_module_overlay'] ?? 'Enable tasks to create, assign, and track work across your team.'
                                    );
                                endif; ?>
                                <div class="sales-stats-content">
								<?php
								$adminDashUserId = (int) $id;
								$myTasksBaseWhere = Task::kanbanMyTasksWhere($adminDashUserId, 'tasks')
									. ' AND ' . Task::activeTasksWhere('tasks')
									. " AND tasks.status != 'done'";
								$myTasksDueValid = reports_sql_valid_date('tasks.due_date');
								$my_tasks_today = Task::findBySqlSoft(
									"SELECT tasks.*, p.project_title
									 FROM tasks
									 LEFT JOIN projects p ON p.p_id = tasks.project_id
									 WHERE {$myTasksBaseWhere}
									   AND {$myTasksDueValid}
									   AND DATE(tasks.due_date) <= CURDATE()
									 ORDER BY tasks.due_date ASC, tasks.created_at DESC
									 LIMIT 5"
								);
								$dashWeekEnd = date('Y-m-d', strtotime('sunday this week'));
								$my_tasks_week = Task::findBySqlSoft(
									"SELECT tasks.*, p.project_title
									 FROM tasks
									 LEFT JOIN projects p ON p.p_id = tasks.project_id
									 WHERE {$myTasksBaseWhere}
									   AND {$myTasksDueValid}
									   AND DATE(tasks.due_date) > CURDATE()
									   AND DATE(tasks.due_date) <= '{$dashWeekEnd}'
									 ORDER BY tasks.due_date ASC, tasks.created_at DESC
									 LIMIT 5"
								);
								if (!is_array($my_tasks_today)) {
									$my_tasks_today = [];
								}
								if (!is_array($my_tasks_week)) {
									$my_tasks_week = [];
								}
								adminDashPreloadTaskAssignees(array_merge($my_tasks_today, $my_tasks_week), $dashboardUsersById);
								?>
								<div class="task-widget" id="dash-my-tasks-widget">
								  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap row-gap-10">
									<div class="card-title">
										<h3><?php echo $lang['My Tasks'] ?? 'My tasks'; ?></h3>
									</div>
									<div class="d-flex align-items-center col-gap-10 flex-wrap">
										<div class="media-view-toggle dash-my-tasks-toggle" role="group" aria-label="<?php echo htmlspecialchars($lang['My Tasks'] ?? 'My tasks', ENT_QUOTES, 'UTF-8'); ?>">
											<a href="#" class="border-btn-a is-active" data-dash-my-tasks-period="today" aria-pressed="true"><?php echo $lang['Today'] ?? 'Today'; ?></a>
											<a href="#" class="border-btn-a" data-dash-my-tasks-period="week" aria-pressed="false"><?php echo $lang['Week'] ?? 'Week'; ?></a>
										</div>
										<div class="border-btn">
											<a href="<?php echo $url; ?>admin/all-tasks">
												<?php echo $lang['View all']; ?>
											</a>
										</div>
									</div>
								  </div>

								  <div id="dash-my-tasks-today" class="list-group dash-my-tasks-list dash-my-tasks-panel">
									<?php renderAdminDashMyTaskRows($my_tasks_today, $url, $lang, $dashboardUsersById); ?>
								  </div>
								  <div id="dash-my-tasks-week" class="list-group dash-my-tasks-list dash-my-tasks-panel is-hidden">
									<?php renderAdminDashMyTaskRows($my_tasks_week, $url, $lang, $dashboardUsersById); ?>
								  </div>
								</div>
                                </div>
                            </div>
                        </div>
                         <div class="col-lg-4 col-md-6 col-sm-12 d-flex">
                            <div class="widget-card flex-grow <?php echo !$isTasksEnabled ? 'sales-stats-disabled' : ''; ?>">
                                <?php if (!$isTasksEnabled) :
                                    renderAdminDashModuleDisabledOverlay(
                                        $url,
                                        $lang,
                                        $lang['Tasks module disabled'] ?? 'Tasks module disabled',
                                        $lang['tasks_module_overlay'] ?? 'Enable tasks to create, assign, and track work across your team.'
                                    );
                                endif; ?>
                                <div class="sales-stats-content">
                                <div class="task-widget" id="dash-team-workload-widget">
                                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap row-gap-10">
                                        <div class="card-title">
                                            <h3><?php echo $lang['Team workload'] ?? 'Team workload'; ?></h3>
                                        </div>
                                        <div class="border-btn">
                                            <a href="<?php echo $url; ?>admin/members">
                                                <?php echo $lang['Manage'] ?? 'Manage'; ?>
                                            </a>
                                        </div>
                                    </div>

                                    <div class="dash-team-workload-body">
                                    <div class="list-group dash-team-workload-list">
                                        <?php if (empty($teamWorkloadRows)): ?>
                                            <?php renderDashboardEmptyState('user-group', $teamWorkloadEmptyLabel); ?>
                                        <?php else: ?>
                                            <?php foreach ($teamWorkloadRows as $teamWorkloadItem): ?>
                                                <?php $teamProfileHref = 'profile?user_id=' . (int) $teamWorkloadItem['id'] . '&tab=tasks'; ?>
                                                <div class="list-group-item note-card dash-team-workload-row d-flex align-items-start" style="position: relative; cursor: pointer;">
                                                    <a href="<?php echo htmlspecialchars($teamProfileHref, ENT_QUOTES, 'UTF-8'); ?>" class="dash-team-workload-link">
                                                        <div class="dash-team-workload-avatar">
                                                            <div class="user-box">
                                                            <?php echo getUserAvatarHtml(
                                                                (int) $teamWorkloadItem['id'],
                                                                (string) ($teamWorkloadItem['firstName'] ?? ''),
                                                                (string) ($teamWorkloadItem['lastName'] ?? ''),
                                                                32,
                                                                32,
                                                                'avatar',
                                                                (string) ($teamWorkloadItem['name'] ?? '')
                                                            ); ?>
                                                            </div>
                                                        </div>
                                                        <div class="dash-team-workload-main">
                                                            <div class="dash-team-workload-head">
                                                                <div class="title font-size-14 dash-team-workload-name"><?php echo htmlspecialchars($teamWorkloadItem['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                                <div class="dash-team-workload-bar-wrap">
                                                                    <div class="progress mb-0">
                                                                        <div class="progress-bar" role="progressbar"
                                                                             style="width: <?php echo (int) $teamWorkloadItem['bar_pct']; ?>%; background: <?php echo htmlspecialchars($teamWorkloadItem['bar_color'], ENT_QUOTES, 'UTF-8'); ?>;"
                                                                             aria-valuenow="<?php echo (int) $teamWorkloadItem['bar_pct']; ?>"
                                                                             aria-valuemin="0"
                                                                             aria-valuemax="100"></div>
                                                                    </div>
                                                                </div>
                                                                <div class="dash-team-workload-count"><?php echo (int) $teamWorkloadItem['open_tasks']; ?></div>
                                                            </div>
                                                            <div class="font-size-12 grey dash-team-workload-role"><?php echo htmlspecialchars($teamWorkloadItem['role'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                        </div>
                                                    </a>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    </div>

                                    <footer class="dash-team-workload-footer">
                                        <div class="stat-invoice-foot">
                                            <div class="grey persent-count font-size-12"><?php echo $lang['Open tasks per person'] ?? 'Open tasks per person'; ?></div>
                                            <div class="stat-invoice-legend">
                                                <span><i class="dot bg-red"></i> <?php echo $lang['Overloaded'] ?? 'Overloaded'; ?></span>
                                                <span><i class="dot bg-amber"></i> <?php echo $lang['Busy'] ?? 'Busy'; ?></span>
                                                <span><i class="dot bg-green"></i> <?php echo $lang['OK'] ?? 'OK'; ?></span>
                                            </div>
                                        </div>
                                    </footer>
                                </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6 col-sm-12 d-flex">
                            <div class="widget-card flex-grow <?php echo !$isNotesDocumentsEnabled ? 'sales-stats-disabled' : ''; ?>">
                                <?php if (!$isNotesDocumentsEnabled) :
                                    renderAdminDashModuleDisabledOverlay(
                                        $url,
                                        $lang,
                                        $lang['Notes & documents module disabled'] ?? 'Notes & documents module disabled',
                                        $lang['notes_documents_module_overlay'] ?? 'Enable notes and documents to store and share important files with your team.'
                                    );
                                endif; ?>
                              <div class="sales-stats-content">
                              <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="card-title">
                                        <h3><?php echo $lang['Documents']; ?></h3>
                                    </div>
                                      <div class="border-btn">
                                        <a href="<?php echo $url; ?>admin/documents">
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
					</div>
                    <div class="row align-items-stretch admin-dash-widgets-row">
                        <div class="col-lg-4 col-md-6 col-sm-12 d-flex">
                            <div class="widget-card flex-grow">
                                <div class="d-flex justify-content-between align-items-center mb-3">
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
                                    <?php renderAdminDashFeedItems($dashRecentActivityItems, $lang['No activity found'] ?? 'No activity found.', $lang); ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6 col-sm-12 d-flex">
                            <div class="widget-card flex-grow">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="card-title">
                                        <h3><?php echo $lang['Upcoming'] ?? 'Upcoming'; ?></h3>
                                    </div>
                                    <div class="border-btn">
                                        <a href="<?php echo $url; ?>admin/calendar">
                                            <?php echo $lang['Calendar'] ?? 'Calendar'; ?>
                                        </a>
                                    </div>
                                </div>
                                <div class="list-group dash-docs-list">
                                    <?php renderAdminDashUpcomingItems($dashUpcomingItems, $lang['Nothing upcoming'] ?? 'Nothing upcoming.', $lang); ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6 col-sm-12 d-flex">
                            <div class="widget-card flex-grow <?php echo $leadsPipelineLocked ? 'sales-stats-disabled' : ''; ?>">
                                <?php if ($leadsPipelineLocked) :
                                    if (!empty($isFreeEditionDash) && function_exists('tasksession_render_pro_widget_overlay')) {
                                        tasksession_render_pro_widget_overlay('leads');
                                    } else {
                                        renderAdminDashModuleDisabledOverlay(
                                            $url,
                                            $lang,
                                            $lang['Lead board module disabled'] ?? 'Lead board module disabled',
                                            $lang['lead_board_module_overlay'] ?? 'Enable the lead board to track prospects and manage your sales pipeline.'
                                        );
                                    }
                                endif; ?>
                                <div class="sales-stats-content">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="card-title">
                                        <h3><?php echo $lang['Leads pipeline'] ?? 'Leads pipeline'; ?></h3>
                                    </div>
                                    <div class="d-flex align-items-center col-gap-10">
                                        <?php renderAdminDashLeadsPipelineCurrencyDropdown($dashLeadsPipeline); ?>
                                        <div class="border-btn">
                                            <a href="<?php echo $url; ?>admin/leads">
                                                <?php echo $lang['View all']; ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php renderAdminDashLeadsPipelineWidget($dashLeadsPipeline, $lang); ?>
                                </div>
                            </div>
                        </div>
                    </div>
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
window.monthlyCancelled = <?php echo json_encode(array_fill(0, count($monthly_earnings), 0)); ?>;
window.totalPaidLabel = <?php echo json_encode($lang['Total Paid']); ?>;
window.totalUnpaidLabel = <?php echo json_encode($lang['Total Unpaid']); ?>;
window.invoicePaidLabel = <?php echo json_encode($lang['Paid Invoices']); ?>;
window.invoiceUnpaidLabel = <?php echo json_encode($lang['Unpaid']); ?>;
window.salesPaidLabel = <?php echo json_encode($lang['Paid'] ?? 'Paid'); ?>;
window.salesUnpaidLabel = <?php echo json_encode($lang['Unpaid'] ?? 'Unpaid'); ?>;
window.salesCancelledLabel = <?php echo json_encode($lang['Cancel'] ?? 'Cancel'); ?>;
window.salesTaxLabel = <?php echo json_encode($lang['Sales Tax'] ?? 'Sales Tax'); ?>;

// Global variables for currency filtering
window.selectedCurrency = 'USD,$';
window.currentDateRange = { start: null, end: null };

// Currency selection function
function selectCurrency(currency) {
    window.selectedCurrency = currency;
    
    // Update dropdown button text
    var currencyDropdown = document.getElementById('currencyFilterDropdown');
    if (currencyDropdown) {
        var buttonText;
        if (currency === 'all') {
            buttonText = '<?php echo $lang['All Currencies']; ?>';
        } else {
            var parts = String(currency).split(',');
            var code = (parts[0] || '').trim();
            var symbol = parts.length > 1 ? parts[1].trim() : code;
            var countryMap = { USD: 'United States', PKR: 'Pakistan', Rs: 'Pakistan', CAD: 'Canada', INR: 'INR' };
            var country = countryMap[code] || code;
            buttonText = country + ' (' + symbol + ')';
        }
        currencyDropdown.innerText = buttonText;
    }
    
    // Refresh the chart with current date range and new currency
    if (window.currentDateRange.start && window.currentDateRange.end) {
        updateRangeTotals(window.currentDateRange.start, window.currentDateRange.end);
    } else if (typeof window.refreshInvoiceOverview === 'function') {
        window.refreshInvoiceOverview();
    }
}
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

        if (defaultCurrency && metrics[defaultCurrency]) {
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
<script src="../assets/js/Chart.js" defer></script>
<script src="../assets/js/analytics.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/analytics.js') ?: time(); ?>" defer></script>
<?php if ($adminShowSkeletonLoading) : ?>
<script>
(function () {
  function revealAdminDashboardLive() {
    var wrap = document.querySelector('.admin-dashboard .reports-page.reports-loading');
    if (wrap) {
      wrap.classList.remove('reports-loading');
    }
  }
  // Safety net only — analytics.js reveals within ~1.2s; do not hold skeleton for 20s
  if (document.readyState === 'complete') {
    setTimeout(revealAdminDashboardLive, 2500);
  } else {
    window.addEventListener('load', function () {
      setTimeout(revealAdminDashboardLive, 1500);
    });
  }
})();
</script>
<?php endif; ?>
<?php include("../templates/main-footer.php"); ?>


