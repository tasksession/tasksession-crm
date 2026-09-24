<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : calendar.php
   Purpose : Kanban-style task calendar with waiting list and events
 ================================================================================
*/
ob_start();
require_once("../includes/lib-initialize.php");
require_once("../includes/task.php");
require_once("../includes/calendar_event.php");
require_once("../includes/permissions.php");

function calendar_first_n_words($text, $limit = 15)
{
    $plain = strip_tags((string)$text);
    $words = preg_split('/\s+/', trim($plain));
    if (!$words || $words[0] === '') {
        return '';
    }
    if (count($words) > $limit) {
        return implode(' ', array_slice($words, 0, $limit)) . '...';
    }
    return $plain;
}

function calendar_initials_avatar_html($name, $colorIndex = 1)
{
    $safeName = trim((string)$name);
    $initial = $safeName !== '' ? strtoupper(substr($safeName, 0, 1)) : 'U';
    $color = (int)$colorIndex;
    if ($color < 1 || $color > 8) {
        $color = 1;
    }
    return '<div class="avatar-initials color-' . $color . ' avatar-initials-small rounded-circle">' . htmlspecialchars($initial) . '</div>';
}

$title = ($lang['Calendar View'] ?? 'Calendar View') . " | " . $syatem_title;
include("../templates/header.php");
ensure_user_permissions($connect);

if (!($session->isLoggedIn())) {
    redirectTo($url . "index.php");
}
if ($_SESSION['accountStatus'] == 2) {
    redirectTo($url . "client/index.php");
}
if ($_SESSION['accountStatus'] == 1) {
    redirectTo($url . "admin/index.php");
}
if ($_SESSION['accountStatus'] != 3) {
    redirectTo($url . "index.php");
}
// Get current user info
$id = $session->userId; 
$user = User::findById((int)$id);
$username = $user->firstName;
$email = $user->email;

$userId = (int)$session->userId;
$showMyTasks = !isset($_GET['all_tasks']) || $_GET['all_tasks'] !== '1';
$kanbanCanViewAllTasks = staff_kanban_can_view_all_tasks();
$kanbanCanViewCreatedTab = staff_kanban_can_view_created_tasks_tab();
$kanbanShowAllTasksTab = staff_kanban_show_all_tasks_tab();
if (!$showMyTasks && !$kanbanShowAllTasksTab) {
    $showMyTasks = true;
}
$calendarTaskScope = staff_calendar_resolve_task_scope($showMyTasks);
$allTasksMode = (!$showMyTasks && $kanbanShowAllTasksTab);
$allTasksQueryPrefix = $allTasksMode ? 'all_tasks=1&' : '';
// Match staff kanban permissions for task action dropdowns.
$canEditTask = has_permission('task_edit');
$canDeleteTask = has_permission('task_delete');
$currentUser = User::findById($userId);
$currentUserName = $currentUser ? trim((string)($currentUser->firstName ?? '')) : '';

$selectedDate = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}

$selectedMonth = isset($_GET['month']) ? trim($_GET['month']) : date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}
$searchQuery = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$startDateFilter = isset($_GET['start_date']) ? trim((string)$_GET['start_date']) : '';
$endDateFilter = isset($_GET['end_date']) ? trim((string)$_GET['end_date']) : '';
$taskDateMode = isset($_GET['task_date_mode']) ? trim((string)$_GET['task_date_mode']) : 'all';
if (!in_array($taskDateMode, ['all', 'due', 'start'], true)) {
    $taskDateMode = 'all';
}
$sortOrder = isset($_GET['sort_order']) ? trim((string)$_GET['sort_order']) : 'asc';
if (!in_array($sortOrder, ['asc', 'desc'], true)) {
    $sortOrder = 'asc';
}
if ($startDateFilter !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDateFilter)) {
    $startDateFilter = '';
}
if ($endDateFilter !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDateFilter)) {
    $endDateFilter = '';
}

$monthStart = $selectedMonth . "-01";
$monthEnd = date('Y-m-t', strtotime($monthStart));
$monthLabel = date('F Y', strtotime($monthStart));
$daysInMonth = (int)date('t', strtotime($monthStart));

// Default render window follows selected date/month behavior.
$renderStartDate = date('Y-m-d', strtotime($selectedDate . ' -1 day'));
$renderDaysCount = $daysInMonth;
$renderEndDate = date('Y-m-d', strtotime($renderStartDate . ' +' . ($renderDaysCount - 1) . ' day'));

// If custom date range is selected, render the board using that range.
if ($startDateFilter !== '' || $endDateFilter !== '') {
    if ($startDateFilter === '') {
        $startDateFilter = $endDateFilter;
    }
    if ($endDateFilter === '') {
        $endDateFilter = $startDateFilter;
    }
    if ($startDateFilter > $endDateFilter) {
        $tmp = $startDateFilter;
        $startDateFilter = $endDateFilter;
        $endDateFilter = $tmp;
    }

    $renderStartDate = $startDateFilter;
    $renderEndDate = $endDateFilter;
    $diffSeconds = strtotime($renderEndDate) - strtotime($renderStartDate);
    $renderDaysCount = (int)floor($diffSeconds / 86400) + 1;
    if ($renderDaysCount < 1) {
        $renderDaysCount = 1;
        $renderEndDate = $renderStartDate;
    }

    // Keep selected/highlighted date inside current rendered range.
    if ($selectedDate < $renderStartDate || $selectedDate > $renderEndDate) {
        $selectedDate = $renderStartDate;
    }
    $selectedMonth = date('Y-m', strtotime($renderStartDate));
    $monthStart = $selectedMonth . "-01";
    $monthEnd = date('Y-m-t', strtotime($monthStart));
    $monthLabel = date('F Y', strtotime($monthStart));
}

$scheduledTasksByDay = [];
$eventsByDay = [];
$waitingTasks = [];
$waitingEvents = [];
$calendarReady = CalendarEvent::moduleReady();
$fallbackTasksByDay = [];
$taskDetailsMap = [];
$staffCache = [];
$scheduledTaskIdsForUser = [];

if ($calendarReady) {
    CalendarEvent::seedWaitingTasksFromUnscheduled($userId, 120);

    $scheduledTasks = CalendarEvent::listScheduledTasksByMonth($userId, $renderStartDate, $renderEndDate, $calendarTaskScope);
    foreach ($scheduledTasks as $taskRow) {
        if (!staff_calendar_task_row_in_scope($taskRow, $userId, $calendarTaskScope)) {
            continue;
        }

        $scheduledTid = (int)($taskRow['task_id'] ?? 0);
        if ($scheduledTid > 0) {
            $scheduledTaskIdsForUser[$scheduledTid] = true;
        }
        $key = $taskRow['schedule_date'];
        if ($startDateFilter !== '' && $key < $startDateFilter) {
            continue;
        }
        if ($endDateFilter !== '' && $key > $endDateFilter) {
            continue;
        }
        if ($searchQuery !== '' && stripos((string)$taskRow['title'], $searchQuery) === false) {
            continue;
        }
        if (!isset($scheduledTasksByDay[$key])) {
            $scheduledTasksByDay[$key] = [];
        }
        $scheduledTasksByDay[$key][] = $taskRow;
    }

    $calendarEventsOnlyMine = ($calendarTaskScope !== 'all');
    $events = CalendarEvent::listEventsByMonth($userId, $renderStartDate, $renderEndDate, $calendarEventsOnlyMine);
    foreach ($events as $eventRow) {
        if ($calendarEventsOnlyMine) {
            $participants = [];
            if (!empty($eventRow['participants_json'])) {
                $decodedParticipants = json_decode((string)$eventRow['participants_json'], true);
                if (is_array($decodedParticipants)) {
                    $participants = $decodedParticipants;
                }
            }

            $isAssignedToCurrentUser = ((int)($eventRow['user_id'] ?? 0) === $userId);
            foreach ($participants as $p) {
                if (is_array($p)) {
                    $pid = isset($p['id']) ? (int)$p['id'] : 0;
                    $pname = trim((string)($p['name'] ?? ''));
                    if ($pid === $userId) {
                        $isAssignedToCurrentUser = true;
                        break;
                    }
                    if ($pid === 0 && $currentUserName !== '' && strcasecmp($pname, $currentUserName) === 0) {
                        $isAssignedToCurrentUser = true;
                        break;
                    }
                } else {
                    $pname = trim((string)$p);
                    if ($pname !== '' && ctype_digit($pname) && (int)$pname === $userId) {
                        $isAssignedToCurrentUser = true;
                        break;
                    }
                    if ($currentUserName !== '' && strcasecmp($pname, $currentUserName) === 0) {
                        $isAssignedToCurrentUser = true;
                        break;
                    }
                }
            }

            if (!$isAssignedToCurrentUser) {
                continue;
            }
        }

        $eventDate = !empty($eventRow['event_date']) ? $eventRow['event_date'] : date('Y-m-d', strtotime($eventRow['start_datetime']));
        if ($startDateFilter !== '' && $eventDate < $startDateFilter) {
            continue;
        }
        if ($endDateFilter !== '' && $eventDate > $endDateFilter) {
            continue;
        }
        if ($searchQuery !== '' && stripos((string)$eventRow['title'], $searchQuery) === false) {
            continue;
        }
        if (!isset($eventsByDay[$eventDate])) {
            $eventsByDay[$eventDate] = [];
        }
        $eventsByDay[$eventDate][] = $eventRow;
    }

    $waiting = CalendarEvent::listWaitingItems($userId);
    $waitingTasks = $waiting['tasks'] ?? [];
    $waitingEvents = $waiting['events'] ?? [];
    if ($searchQuery !== '') {
        $waitingTasks = array_values(array_filter($waitingTasks, function ($row) use ($searchQuery) {
            return stripos((string)($row['title'] ?? ''), $searchQuery) !== false;
        }));
        $waitingEvents = array_values(array_filter($waitingEvents, function ($row) use ($searchQuery) {
            return stripos((string)($row['title'] ?? ''), $searchQuery) !== false;
        }));
    }
}

// Fallback: show tasks by start/due dates even if calendar scheduling is not set yet.
$allTasksForCalendar = Task::findAll();
foreach ($allTasksForCalendar as $taskObj) {
    $taskDetailsMap[(int)$taskObj->id] = $taskObj;
}
if ($calendarTaskScope !== 'all') {
    $allTasksForCalendar = array_filter($allTasksForCalendar, function ($task) use ($userId, $calendarTaskScope) {
        return staff_calendar_task_in_scope($task, $userId, $calendarTaskScope);
    });
}

foreach ($allTasksForCalendar as $task) {
    $taskIdInt = (int)$task->id;
    // Important: if task is already scheduled via calendar_task_schedule,
    // do not render it again from start/due fallback (prevents duplicates after refresh).
    if (isset($scheduledTaskIdsForUser[$taskIdInt])) {
        continue;
    }

    $taskTitle = isset($task->title) ? (string)$task->title : '';
    if ($searchQuery !== '' && stripos($taskTitle, $searchQuery) === false) {
        continue;
    }

    $dateKey = '';
    $startDateRaw = (string)($task->start_date ?? '');
    $dueDateRaw = (string)($task->due_date ?? '');

    if ($taskDateMode === 'due') {
        if (!empty($dueDateRaw) && $dueDateRaw !== '0000-00-00') {
            $dateKey = $dueDateRaw;
        }
    } elseif ($taskDateMode === 'start') {
        if (!empty($startDateRaw) && $startDateRaw !== '0000-00-00') {
            $dateKey = $startDateRaw;
        }
    } else {
        // Default mixed mode: due date first, then start date.
        if (!empty($dueDateRaw) && $dueDateRaw !== '0000-00-00') {
            $dateKey = $dueDateRaw;
        } elseif (!empty($startDateRaw) && $startDateRaw !== '0000-00-00') {
            $dateKey = $startDateRaw;
        }
    }
    if ($dateKey === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateKey)) {
        continue;
    }
    if ($dateKey < $renderStartDate || $dateKey > $renderEndDate) {
        continue;
    }
    if ($startDateFilter !== '' && $dateKey < $startDateFilter) {
        continue;
    }
    if ($endDateFilter !== '' && $dateKey > $endDateFilter) {
        continue;
    }

    if (!isset($scheduledTasksByDay[$dateKey])) {
        $scheduledTasksByDay[$dateKey] = [];
    }

    $exists = false;
    foreach ($scheduledTasksByDay[$dateKey] as $existing) {
        if ((int)($existing['task_id'] ?? 0) === (int)$task->id) {
            $exists = true;
            break;
        }
    }
    if ($exists) {
        continue;
    }

    $scheduledTasksByDay[$dateKey][] = [
        'task_id' => $taskIdInt,
        'schedule_date' => $dateKey,
        'title' => $taskTitle,
        'status' => isset($task->status) ? (string)$task->status : 'todo',
        'project_id' => isset($task->project_id) ? (int)$task->project_id : 0,
        'assigned_to' => isset($task->assigned_to) ? (string)$task->assigned_to : '',
        'description' => isset($task->description) ? (string)$task->description : '',
        'start_date' => isset($task->start_date) ? (string)$task->start_date : '',
        'due_date' => isset($task->due_date) ? (string)$task->due_date : ''
    ];
}

// Safety dedupe: one task card per day per task_id.
foreach ($scheduledTasksByDay as $dateKey => $rows) {
    $seenTaskIds = [];
    $uniqueRows = [];
    foreach ($rows as $row) {
        $tid = (int)($row['task_id'] ?? 0);
        if ($tid <= 0) {
            continue;
        }
        if (isset($seenTaskIds[$tid])) {
            continue;
        }
        $seenTaskIds[$tid] = true;
        $uniqueRows[] = $row;
    }
    $scheduledTasksByDay[$dateKey] = $uniqueRows;
}

foreach ($scheduledTasksByDay as $dayRows) {
    foreach ($dayRows as $row) {
        if (empty($row['assigned_to'])) {
            continue;
        }
        $assignedIds = array_map('trim', explode(',', (string)$row['assigned_to']));
        foreach ($assignedIds as $sid) {
            $sid = (int)$sid;
            if ($sid <= 0 || isset($staffCache[$sid])) {
                continue;
            }
            $staffCache[$sid] = User::findById($sid);
        }
    }
}

$prevMonth = date('Y-m', strtotime($monthStart . ' -1 month'));
$nextMonth = date('Y-m', strtotime($monthStart . ' +1 month'));
$dayTaskCount = 0;
foreach ($scheduledTasksByDay as $items) {
    $dayTaskCount += count($items);
}
$dayEventCount = 0;
foreach ($eventsByDay as $items) {
    $dayEventCount += count($items);
}
$calendarTotalItems = $dayTaskCount + $dayEventCount + count($waitingTasks) + count($waitingEvents);

$myTasksCount = 0;
$allTasksCount = 0;
if (isset($database)) {
    $uid = (int)$userId;
    $myRes = $database->query("SELECT COUNT(*) AS total FROM tasks WHERE FIND_IN_SET($uid, assigned_to) > 0");
    if ($myRes) {
        $myRow = $database->fetchArray($myRes);
        $myTasksCount = isset($myRow['total']) ? (int)$myRow['total'] : 0;
    }
    if ($kanbanCanViewAllTasks) {
        $allRes = $database->query("SELECT COUNT(*) AS total FROM tasks");
        if ($allRes) {
            $allRow = $database->fetchArray($allRes);
            $allTasksCount = isset($allRow['total']) ? (int)$allRow['total'] : 0;
        }
    } elseif ($kanbanCanViewCreatedTab) {
        $createdWhere = Task::kanbanCreatedTasksWhere($uid, 'tasks');
        $allRes = $database->query("SELECT COUNT(*) AS total FROM tasks WHERE $createdWhere");
        if ($allRes) {
            $allRow = $database->fetchArray($allRes);
            $allTasksCount = isset($allRow['total']) ? (int)$allRow['total'] : 0;
        }
    }
}
$calendarViewToggleParams = array();
if ($allTasksMode) {
    $calendarViewToggleParams['all_tasks'] = '1';
}
if ($searchQuery !== '') {
    $calendarViewToggleParams['search'] = $searchQuery;
}
if ($startDateFilter !== '') {
    $calendarViewToggleParams['start_date'] = $startDateFilter;
}
if ($endDateFilter !== '') {
    $calendarViewToggleParams['end_date'] = $endDateFilter;
}
if ($sortOrder !== '') {
    $calendarViewToggleParams['sort_order'] = $sortOrder;
}
$calendarTableViewHref = 'all-tasks' . (!empty($calendarViewToggleParams) ? '?' . http_build_query($calendarViewToggleParams) : '');
$calendarKanbanViewHref = 'kanban' . (!empty($calendarViewToggleParams) ? '?' . http_build_query($calendarViewToggleParams) : '');
$calendarSearchClearParams = array(
    'month' => $selectedMonth,
    'date' => $selectedDate,
    'task_date_mode' => $taskDateMode,
    'sort_order' => $sortOrder,
);
if ($allTasksMode) {
    $calendarSearchClearParams['all_tasks'] = '1';
}
if ($startDateFilter !== '') {
    $calendarSearchClearParams['start_date'] = $startDateFilter;
}
if ($endDateFilter !== '') {
    $calendarSearchClearParams['end_date'] = $endDateFilter;
}
$calendarSearchClearHref = '?' . http_build_query($calendarSearchClearParams);
?>

<div class="page-container">
    <div class="container-fluid">
        <div class="row row-eq-height">
            <?php include("../templates/sidebar.php"); ?>
            <div class="page-content">
                <?php include('../templates/top-header.php'); ?>
                <link rel="stylesheet" href="../assets/css/kanban-bulk.css?v=9">
                <div class="row bg-grey">
                    <div class="col-md-12 margin-top-10 project-tabs calendar-project-tabs">
                        <div class="row">
                            <div class="project-tabs-header">
                                <div class="scrollable-tabs-container d-flex col-gap-40 col-gap-40-sep">
                                <div class="main-heading">
                                    <h1>
                                        <?php echo $lang['Calendar View'] ?? 'Calendar View'; ?>
                                        <span>(<?php echo (int)$calendarTotalItems; ?>)</span>
                                    </h1>
                                </div>
                                <div class="icon-container sep">
                                    <a href="?month=<?php echo htmlspecialchars($selectedMonth); ?>&date=<?php echo htmlspecialchars($selectedDate); ?>" class="<?php echo $showMyTasks ? 'active' : ''; ?>">
                                        <?php echo ts_icon('tasks'); ?><?php echo ($lang['My Schedule'] ?? 'My schedule') . ' (' . (int)$myTasksCount . ')'; ?>
                                    </a>
                                    <?php if ($kanbanShowAllTasksTab): ?>
                                    <a href="?all_tasks=1&month=<?php echo htmlspecialchars(date('Y-m')); ?>&date=<?php echo htmlspecialchars(date('Y-m-d')); ?>" class="<?php echo !$showMyTasks ? 'active' : ''; ?>">
                                        <?php echo ts_icon('inbox-stack'); ?><?php echo ($lang['All Schedule'] ?? 'All schedule') . ' (' . (int)$allTasksCount . ')'; ?>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                            <div class="search">
                                <div class="search-icon border-btn-a" onclick="toggleSearch()">
                                    <?php echo ts_icon('search', 'w-2'); ?>
                                </div>
                                <form method="GET" action="" class="search-form" id="searchForm">
                                    <?php if ($allTasksMode): ?><input type="hidden" name="all_tasks" value="1"><?php endif; ?>
                                    <input type="hidden" name="month" value="<?php echo htmlspecialchars($selectedMonth); ?>">
                                    <input type="hidden" name="date" value="<?php echo htmlspecialchars($selectedDate); ?>">
                                    <input type="hidden" name="task_date_mode" value="<?php echo htmlspecialchars($taskDateMode); ?>">
                                    <input type="hidden" name="sort_order" value="<?php echo htmlspecialchars($sortOrder); ?>">
                                    <?php if ($startDateFilter): ?><input type="hidden" name="start_date" value="<?php echo htmlspecialchars($startDateFilter); ?>"><?php endif; ?>
                                    <?php if ($endDateFilter): ?><input type="hidden" name="end_date" value="<?php echo htmlspecialchars($endDateFilter); ?>"><?php endif; ?>
                                    <div class="input-group">
                                        <span class="search-field-icon">
                                            <?php echo ts_icon('search', 'w-2'); ?>
                                        </span>
                                        <input type="text" name="search" class="form-control" placeholder="<?php echo htmlspecialchars($lang['Search Tasks'] ?? 'Search Tasks', ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($searchQuery); ?>">
                                        <?php if ($searchQuery !== ''): ?>
                                            <a href="<?php echo htmlspecialchars($calendarSearchClearHref, ENT_QUOTES, 'UTF-8'); ?>" class="cross" title="<?php echo htmlspecialchars($lang['Close'] ?? 'Close', ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo ts_icon('close', 'w-2'); ?>
                                            </a>
                                        <?php else: ?>
                                            <a href="#" class="cross" onclick="toggleSearch(); return false;" title="<?php echo htmlspecialchars($lang['Close'] ?? 'Close', ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo ts_icon('close', 'w-2'); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>

                            <div class="edit-overview-btn d-none d-md-block">
                                <div class="icon-container sep">
                                    <div class="pm-trash task-trash align-middle d-flex col-gap-5">
                                        <div class="media-view-toggle" id="calendarViewToggle" role="group" aria-label="View mode">
                                            <a href="<?php echo htmlspecialchars($calendarKanbanViewHref, ENT_QUOTES, 'UTF-8'); ?>" class="media-view-toggle__btn border-btn-a" title="<?php echo htmlspecialchars($lang['Grid View'] ?? 'Grid view', ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo ts_icon('view-grid', 'w-2'); ?>
                                            </a>
                                            <a href="<?php echo htmlspecialchars($calendarTableViewHref, ENT_QUOTES, 'UTF-8'); ?>" class="media-view-toggle__btn border-btn-a" title="<?php echo htmlspecialchars($lang['Table View'] ?? 'Table view', ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo ts_icon('table', 'w-2'); ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        <?php
                        $aiMenuId = 'aiHeaderMenuCalendar';
                        include dirname(__DIR__) . '/templates/partials/ai-menu.php';
                        unset($aiMenuId);
                        ?>
                        <div class="edit-overview-btn kanban-header-filters">
                            <div class="action-toggle border-btn-a collapsed" data-bs-toggle="collapse" data-bs-target="#project-menu-calendar" aria-expanded="false" role="button" tabindex="0">
                                <span class="action-text"><?php echo $lang['Filters'] ?? 'Filters'; ?></span>
                                <span class="mobile-ellipsis">
                                    <?php echo ts_icon('ellipsis', 'w-2'); ?>
                                </span>
                                <?php echo ts_icon('filter', 'w-2'); ?>
                            </div>
                            <div id="project-menu-calendar" class="toggle-action shadow-dept collapse">
                                <ul>
                                    <li class="d-block d-md-none">
                                        <a href="add_task?source=calendar&return=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="primary-btn-a">
                                            <span><?php echo ts_icon('plus'); ?> <?php echo $lang['Add Task'] ?? 'Add Task'; ?></span>
                                        </a>
                                    </li>
                                    <li class="d-block d-md-none">
                                        <a href="#" role="button" id="openAddEventModalMobile" class="primary-btn-a" onclick="return false;">
                                            <span><?php echo ts_icon('plus'); ?> <?php echo $lang['Add Event'] ?? 'Add Event'; ?></span>
                                        </a>
                                    </li>
                                    <li class="<?php echo (!$startDateFilter && !$endDateFilter && $taskDateMode === 'all') ? 'active' : ''; ?>" data-status="today">
                                        <a href="?<?php echo $allTasksQueryPrefix; ?>month=<?php echo htmlspecialchars(date('Y-m')); ?>&date=<?php echo htmlspecialchars(date('Y-m-d')); ?>">
                                            <span><?php echo $lang['Today'] ?? 'Today'; ?></span>
                                        </a>
                                    </li>
                                    <li class="<?php echo $taskDateMode === 'all' ? 'active' : ''; ?>" data-status="all">
                                        <a href="?<?php echo $allTasksQueryPrefix; ?>month=<?php echo htmlspecialchars($selectedMonth); ?>&date=<?php echo htmlspecialchars($selectedDate); ?>&task_date_mode=all">
                                            <span><?php echo $lang['All Schedule'] ?? 'All schedule'; ?></span>
                                        </a>
                                    </li>
                                    <li class="<?php echo $taskDateMode === 'due' ? 'active' : ''; ?>" data-status="due_date">
                                        <a href="?<?php echo $allTasksQueryPrefix; ?>month=<?php echo htmlspecialchars($selectedMonth); ?>&date=<?php echo htmlspecialchars($selectedDate); ?>&task_date_mode=due">
                                            <span><?php echo $lang['Due Date'] ?? 'Due date'; ?></span>
                                        </a>
                                    </li>
                                    <li class="<?php echo $taskDateMode === 'start' ? 'active' : ''; ?>" data-status="start_date">
                                        <a href="?<?php echo $allTasksQueryPrefix; ?>month=<?php echo htmlspecialchars($selectedMonth); ?>&date=<?php echo htmlspecialchars($selectedDate); ?>&task_date_mode=start">
                                            <span><?php echo $lang['Start Date'] ?? 'Start date'; ?></span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="secondary-btn-a" href="#" onclick="toggleDateRangeCard(); return false;">
                                            <span><?php echo $lang['Select Date Range']; ?></span>
                                        </a>
                                    </li>
                                    <hr>
                                    <li><b><?php echo $lang['Sort By'] ?? 'Sort By'; ?></b></li>
                                    <li class="<?php echo $sortOrder === 'asc' ? 'active' : ''; ?>" data-status="ascending">
                                        <a href="?<?php echo $allTasksQueryPrefix; ?>month=<?php echo htmlspecialchars($selectedMonth); ?>&date=<?php echo htmlspecialchars($selectedDate); ?>&task_date_mode=<?php echo htmlspecialchars($taskDateMode); ?>&sort_order=asc<?php echo $startDateFilter ? '&start_date=' . htmlspecialchars($startDateFilter) : ''; ?><?php echo $endDateFilter ? '&end_date=' . htmlspecialchars($endDateFilter) : ''; ?>">
                                            <span>
                                                <?php echo ts_icon('arrow-up'); ?>
                                                <?php echo $lang['Ascending'] ?? 'Ascending'; ?>
                                            </span>
                                        </a>
                                    </li>
                                    <li class="<?php echo $sortOrder === 'desc' ? 'active' : ''; ?>" data-status="descending">
                                        <a href="?<?php echo $allTasksQueryPrefix; ?>month=<?php echo htmlspecialchars($selectedMonth); ?>&date=<?php echo htmlspecialchars($selectedDate); ?>&task_date_mode=<?php echo htmlspecialchars($taskDateMode); ?>&sort_order=desc<?php echo $startDateFilter ? '&start_date=' . htmlspecialchars($startDateFilter) : ''; ?><?php echo $endDateFilter ? '&end_date=' . htmlspecialchars($endDateFilter) : ''; ?>">
                                            <span>
                                                <?php echo ts_icon('chevron-down'); ?>
                                                <?php echo $lang['Descending'] ?? 'Descending'; ?>
                                            </span>
                                        </a>
                                    </li>
                                    <div class="date-range card" style="display: none;">
                                        <div class="card-body">
                                            <b class="mb-2 d-block"><?php echo $lang['Quick Range'] ?? 'Quick Range'; ?></b>
                                            <div>
                                                <li><button class="dropdown-item" onclick="selectQuickRange('last7')"><?php echo $lang['Last 7 days']; ?></button></li>
                                                <li><button class="dropdown-item" onclick="selectQuickRange('last30')"><?php echo $lang['Last 30 days']; ?></button></li>
                                                <li><button class="dropdown-item" onclick="selectQuickRange('last90')"><?php echo $lang['Last 90 days']; ?></button></li>
                                                <li><button class="dropdown-item" onclick="selectQuickRange('last6months')"><?php echo $lang['Last 6 months']; ?></button></li>
                                                <li><button class="dropdown-item" onclick="selectQuickRange('thisYear')"><?php echo $lang['This Year (Jan - Today)']; ?></button></li>
                                            </div>
                                            <hr>
                                            <div class="mb-3">
                                                <div class="date-f">
                                                    <label class="form-label font-size-10"><?php echo $lang['Start Date']; ?></label>
                                                    <input type="date" id="customStart" class="form-control" value="<?php echo htmlspecialchars($startDateFilter); ?>">
                                                </div>
                                                <div class="date-f">
                                                    <label class="form-label font-size-10"><?php echo $lang['End Date']; ?></label>
                                                    <input type="date" id="customEnd" class="form-control" value="<?php echo htmlspecialchars($endDateFilter); ?>">
                                                </div>
                                            </div>
                                            <div class="d-flex col-gap-10">
                                                <button class="btn primary-btn" id="primary-btn" onclick="selectCustomRange()"><?php echo $lang['Apply']; ?></button>
                                                <button class="btn border-btn-a" onclick="clearDateRange()"><?php echo $lang['Clear']; ?></button>
                                            </div>
                                        </div>
                                    </div>
                                </ul>
                            </div>
                        </div>

                            <div class="d-none d-md-block calendar-add-new-md-wrap">
                                <div class="edit-overview-btn calendar-add-new-collapse">
                                    <div class="action-toggle collapsed calendar-add-new-toggle" id="calendarAddNewBtn" data-bs-toggle="collapse" data-bs-target="#calendarAddNewMenu" aria-expanded="false" role="button" tabindex="0">
                                        <span class="action-text"><?php echo $lang['Add New'] ?? $lang['Add new'] ?? 'Add New'; ?></span>
                                        <?php echo ts_icon('plus', 'dropdown-toggle-icon h-6'); ?>
                                    </div>
                                    <div id="calendarAddNewMenu" class="toggle-action collapse shadow-dept">
                                        <ul>
                                            <li><a class="d-flex align-items-center" href="add_task?source=calendar&return=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>"><?php echo ts_icon('tasks', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Add Task'] ?? 'Add Task'; ?></a></li>
                                            <li><a class="d-flex align-items-center" href="#" id="openAddEventModal" onclick="return false;"><?php echo ts_icon('calendar', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo htmlspecialchars($lang['Add Event'] ?? 'Add Event', ENT_QUOTES, 'UTF-8'); ?></a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="clearfix"></div>
                <div class="row">
                    <div class="container-fluid pd-0">
                        <div class="row">
                            <div class="col-12">
                <?php if (!$calendarReady): ?>
                    <div class="alert alert-warning mb-3">
                        <?php echo $lang['Calendar tables are not installed. Please run calendar SQL migration first.'] ?? 'Calendar tables are not installed. Please run calendar SQL migration first.'; ?>
                    </div>
                <?php endif; ?>

                <div class="calendar-board-shell no-waiting-list">
                    <div class="board-wrap calendar-board-wrap full-width">
                        <div class="board d-flex full-width calendar-days-board" id="calendarDaysBoard">
                            <?php
                            $initialCardsPerDay = 20;
                            for ($day = 0; $day < $renderDaysCount; $day++):
                                $dateKey = date('Y-m-d', strtotime($renderStartDate . ' +' . $day . ' day'));
                                $isSelected = $dateKey === $selectedDate;
                                $dayTasks = $scheduledTasksByDay[$dateKey] ?? [];
                                $dayEvents = $eventsByDay[$dateKey] ?? [];
                                $mergedDayCards = CalendarEvent::mergeCalendarDayCardsForDisplay($dayTasks, $dayEvents, $sortOrder);
                                $dayTotalCards = count($dayTasks) + count($dayEvents);
                                $renderedCards = 0;
                            ?>
                                <div class="board-column calendar-day-column <?php echo $isSelected ? 'selected-day' : ''; ?>" data-date="<?php echo htmlspecialchars($dateKey); ?>">
                                    <h2 class="d-flex align-items-center justify-content-between">
                                        <span class="column-title">
                                            <?php echo date('d D', strtotime($dateKey)); ?>
                                        </span>
                                        <span class="badge"><?php echo count($dayTasks) + count($dayEvents); ?></span>
                                    </h2>

                                    <div class="calendar-dropzone" data-date="<?php echo htmlspecialchars($dateKey); ?>">
                                        <?php if (empty($mergedDayCards)): ?>
                                            <div class="alert alert-light text-center small">
                                                <?php echo $lang['No tasks in this column']; ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php foreach ($mergedDayCards as $dayCardEntry): ?>
                                            <?php if ($renderedCards >= $initialCardsPerDay) {
                                                break;
                                            } ?>
                                            <?php if ($dayCardEntry['type'] === 'task'): ?>
                                            <?php
                                            $task = $dayCardEntry['data'];
                                            $taskId = (int)($task['task_id'] ?? 0);
                                            $taskDetail = isset($taskDetailsMap[$taskId]) ? $taskDetailsMap[$taskId] : (object)$task;
                                            $projectIdForCard = (int)($taskDetail->project_id ?? ($task['project_id'] ?? 0));
                                            $titleForCard = htmlspecialchars((string)($taskDetail->title ?? ($task['title'] ?? '')));
                                            $descriptionForCard = calendar_first_n_words((string)($taskDetail->description ?? ($task['description'] ?? '')), 15);
                                            $startDate = (string)($taskDetail->start_date ?? ($task['start_date'] ?? ''));
                                            $dueDate = (string)($taskDetail->due_date ?? ($task['due_date'] ?? ''));
                                            $assignedTo = (string)($taskDetail->assigned_to ?? ($task['assigned_to'] ?? ''));
                                            ?>
                                            <div class="card mb-2 task-card calendar-item-card"
                                                 draggable="true"
                                                 data-id="<?php echo $taskId; ?>"
                                                 data-project-id="<?php echo $projectIdForCard; ?>"
                                                 data-item-type="task"
                                                 data-item-id="<?php echo $taskId; ?>"
                                                 onclick="openTaskFromCalendar(event, <?php echo $taskId; ?>)">
                                                <div class="card-body p-2">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <div class="project-badge">
                                                                <?php if ($projectIdForCard === 0): ?>
                                                                    <span class="internal-badge mb-2"><?php echo $lang['Internal Task']; ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <h5 class="card-title mb-1"><?php echo $titleForCard; ?></h5>
                                                        </div>
                                                        <div class="dropdown">
                                                            <button class="btn-dots" type="button" data-bs-toggle="dropdown">
                                                                <?php echo ts_icon('dots-vertical', 'w-6'); ?>
                                                            </button>
                                                            <ul class="dropdown-menu dropdown-menu-end">
                                                                <li><a class="dropdown-item d-flex align-items-center" href="#" onclick="openTaskSidebar(<?php echo $taskId; ?>); return false;"><?php echo ts_icon('eye', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['View Task']; ?></a></li>
                                                                <?php if ($canEditTask): ?>
                                                                <li><a class="dropdown-item d-flex align-items-center" href="edit_task?id=<?php echo $taskId; ?>&source=calendar&return=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>"><?php echo ts_icon('edit', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Edit Task']; ?></a></li>
                                                                <?php endif; ?>
                                                                <li><a class="dropdown-item d-flex align-items-center" href="../includes/clone-task.php?id=<?php echo $taskId; ?>&redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>"><?php echo ts_icon('duplicate', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Clone Task']; ?></a></li>
                                                                <?php if ($canDeleteTask): ?>
                                                                <li><a class="dropdown-item d-flex align-items-center text-danger" href="#" onclick="deleteTask(<?php echo $taskId; ?>); return false;"><?php echo ts_icon('delete', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Delete Task']; ?></a></li>
                                                                <?php endif; ?>
                                                            </ul>
                                                        </div>
                                                    </div>

                                                    <?php if ($descriptionForCard !== ''): ?>
                                                        <div class="task-description mb-2"><?php echo htmlspecialchars($descriptionForCard); ?></div>
                                                    <?php endif; ?>

                                                    <?php if ($startDate !== '' || $dueDate !== ''): ?>
                                                        <div class="task-date d-flex">
                                                            <?php if ($startDate !== '' && $startDate !== '0000-00-00'): ?>
                                                                <div class="start-date"><b><?php echo $lang['Start']; ?>:</b> <?php echo date('M j, Y', strtotime($startDate)); ?></div>
                                                            <?php endif; ?>
                                                            <?php if ($dueDate !== '' && $dueDate !== '0000-00-00'): ?>
                                                                <div><b><?php echo $lang['Due']; ?>:</b> <?php echo date('M j, Y', strtotime($dueDate)); ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if ($assignedTo !== ''): ?>
                                                        <div class="team-col d-flex align-items-baseline">
                                                            <div class="d-flex avatar-head">
                                                                <?php
                                                                $assignedStaffIds = array_map('trim', explode(',', $assignedTo));
                                                                foreach ($assignedStaffIds as $staffIdRaw):
                                                                    $staffId = (int)$staffIdRaw;
                                                                    if ($staffId <= 0 || !isset($staffCache[$staffId]) || !$staffCache[$staffId]) {
                                                                        continue;
                                                                    }
                                                                    $staffUser = $staffCache[$staffId];
                                                                ?>
                                                                    <div class="avatar-overlap" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($staffUser->firstName . ' ' . ($staffUser->lastName ?? '')); ?>">
                                                                        <?php echo getUserAvatarHtml($staffId, $staffUser->firstName, $staffUser->lastName ?? '', 30, 30, 'rounded-circle', $staffUser->firstName); ?>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php elseif ($dayCardEntry['type'] === 'event'): ?>
                                            <?php
                                            $event = $dayCardEntry['data'];
                                            $eventParticipants = [];
                                            $eventBadgeText = $lang['Event'] ?? 'Event';
                                            $eventSourceType = strtolower((string)($event['source_type'] ?? ''));
                                            if ($eventSourceType === 'google') {
                                                $eventDescLower = strtolower((string)($event['description'] ?? ''));
                                                $isGoogleTask = (strpos($eventDescLower, 'changes made to this task') !== false)
                                                    || (strpos($eventDescLower, 'to make edits') !== false);
                                                $eventBadgeText = $isGoogleTask
                                                    ? ($lang['Google Task'] ?? 'Google Task')
                                                    : ($lang['Google Event'] ?? 'Google Event');
                                            }
                                            if (!empty($event['participants_json'])) {
                                                $decoded = json_decode((string)$event['participants_json'], true);
                                                if (is_array($decoded)) {
                                                    $eventParticipants = $decoded;
                                                }
                                            }
                                            ?>
                                            <div class="card mb-2 task-card calendar-item-card event-item-card" draggable="true"
                                                 data-item-type="event"
                                                 data-item-id="<?php echo (int)$event['id']; ?>"
                                                 onclick="openEventFromCalendar(event, <?php echo (int)$event['id']; ?>)">
                                                <div class="card-body p-2">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <div class="project-badge">
                                                                <span class="internal-badge mb-2"><?php echo htmlspecialchars($eventBadgeText); ?></span>
                                                            </div>
                                                            <h5 class="card-title mb-1"><?php echo htmlspecialchars($event['title']); ?></h5>
                                                        </div>
                                                        <div class="dropdown">
                                                            <button class="btn-dots" type="button" data-bs-toggle="dropdown" onclick="event.stopPropagation();">
                                                                <?php echo ts_icon('dots-vertical', 'w-6'); ?>
                                                            </button>
                                                            <ul class="dropdown-menu dropdown-menu-end">
                                                                <li><a class="dropdown-item d-flex align-items-center" href="#" onclick="openEventSidebar(<?php echo (int)$event['id']; ?>); return false;"><?php echo ts_icon('eye', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['View Event'] ?? 'View Event'; ?></a></li>
                                                                <li><a class="dropdown-item d-flex align-items-center" href="#" onclick="editEventFromCard(<?php echo (int)$event['id']; ?>); return false;"><?php echo ts_icon('edit', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Edit Event'] ?? 'Edit Event'; ?></a></li>
                                                                <li><a class="dropdown-item d-flex align-items-center text-danger" href="#" onclick="deleteEventFromCard(<?php echo (int)$event['id']; ?>); return false;"><?php echo ts_icon('delete', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Delete Event'] ?? 'Delete Event'; ?></a></li>
                                                            </ul>
                                                        </div>
                                                    </div>

                                                    <?php if (!empty($event['description'])): ?>
                                                        <div class="task-description mb-2"><?php echo htmlspecialchars(calendar_first_n_words((string)$event['description'], 15)); ?></div>
                                                    <?php elseif (!empty($event['location_label'])): ?>
                                                        <div class="task-description mb-2"><?php echo htmlspecialchars($event['location_label']); ?></div>
                                                    <?php endif; ?>

                                                    <?php if (!empty($event['start_datetime']) || !empty($event['end_datetime']) || !empty($event['event_date'])): ?>
                                                        <div class="task-date d-flex">
                                                            <?php if (!empty($event['start_datetime'])): ?>
                                                                <div class="start-date">
                                                                    <b><?php echo $lang['Start']; ?>:</b> <?php echo date('M j, Y', strtotime($event['start_datetime'])); ?>
                                                                </div>
                                                            <?php elseif (!empty($event['event_date'])): ?>
                                                                <div class="start-date">
                                                                    <b><?php echo $lang['Start']; ?>:</b> <?php echo date('M j, Y', strtotime($event['event_date'])); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if (!empty($event['end_datetime'])): ?>
                                                                <div>
                                                                    <b><?php echo $lang['Due']; ?>:</b> <?php echo date('M j, Y', strtotime($event['end_datetime'])); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if (!empty($eventParticipants) && is_array($eventParticipants)): ?>
                                                        <div class="team-col d-flex align-items-baseline">
                                                            <div class="d-flex avatar-head">
                                                                <?php foreach ($eventParticipants as $idx => $participant): ?>
                                                                    <?php
                                                                    $participantName = '';
                                                                    $participantUserId = 0;
                                                                    if (is_array($participant)) {
                                                                        $participantName = trim((string)($participant['name'] ?? ''));
                                                                        $participantUserId = isset($participant['id']) ? (int)$participant['id'] : 0;
                                                                    } else {
                                                                        $participantName = trim((string)$participant);
                                                                    }
                                                                    ?>
                                                                    <div class="avatar-overlap" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($participantName !== '' ? $participantName : ('User ' . ($idx + 1))); ?>">
                                                                        <?php
                                                                        if ($participantUserId > 0 && isset($staffCache[$participantUserId]) && $staffCache[$participantUserId]) {
                                                                            $pu = $staffCache[$participantUserId];
                                                                            echo getUserAvatarHtml($participantUserId, $pu->firstName, $pu->lastName ?? '', 30, 30, 'rounded-circle', $pu->firstName);
                                                                        } elseif ($participantUserId > 0) {
                                                                            $pu = User::findById($participantUserId);
                                                                            if ($pu) {
                                                                                echo getUserAvatarHtml($participantUserId, $pu->firstName, $pu->lastName ?? '', 30, 30, 'rounded-circle', $pu->firstName);
                                                                            } else {
                                                                                echo calendar_initials_avatar_html($participantName, (($idx % 8) + 1));
                                                                            }
                                                                        } else {
                                                                            echo calendar_initials_avatar_html($participantName, (($idx % 8) + 1));
                                                                        }
                                                                        ?>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            <?php $renderedCards++; ?>
                                        <?php endforeach; ?>

                                        <?php if ($dayTotalCards > $initialCardsPerDay): ?>
                                            <div class="load-more-container calendar-load-more-container" id="calendar-load-more-<?php echo htmlspecialchars($dateKey); ?>">
                                                <button
                                                    class="btn primary-btn w-100 calendar-load-more-btn"
                                                    data-date="<?php echo htmlspecialchars($dateKey); ?>"
                                                    data-offset="<?php echo (int)$initialCardsPerDay; ?>"
                                                    data-limit="<?php echo (int)$initialCardsPerDay; ?>"
                                                >
                                                    <span class="load-more-text"><?php echo $lang['Load more tasks'] ?? 'Load more tasks'; ?></span>
                                                    <span class="load-more-count">(<?php echo (int)($dayTotalCards - $initialCardsPerDay); ?>)</span>
                                                    <span class="spinner-border spinner-border-sm ms-2" style="display: none;"></span>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endfor; ?>
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

<?php include("../templates/calendar-event-modal.php"); ?>
<?php include("../templates/event-sidebar.php"); ?>
<?php
$__calCssPath = __DIR__ . '/../assets/css/calendar.css';
$__calCssVer = (string)(@filemtime($__calCssPath) ?: time());
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(rtrim((string)$url, '/') . '/assets/css/calendar.css?v=' . $__calCssVer, ENT_QUOTES, 'UTF-8'); ?>">
<?php
ob_start();
?>
<script>
    window.calendarPageData = {
        selectedMonth: "<?php echo htmlspecialchars($selectedMonth, ENT_QUOTES, 'UTF-8'); ?>",
        selectedDate: "<?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?>",
        allTasks: <?php echo $allTasksMode ? '1' : '0'; ?>,
        search: "<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>",
        startDate: "<?php echo htmlspecialchars($startDateFilter, ENT_QUOTES, 'UTF-8'); ?>",
        endDate: "<?php echo htmlspecialchars($endDateFilter, ENT_QUOTES, 'UTF-8'); ?>",
        taskDateMode: "<?php echo htmlspecialchars($taskDateMode, ENT_QUOTES, 'UTF-8'); ?>",
        sortOrder: "<?php echo htmlspecialchars($sortOrder, ENT_QUOTES, 'UTF-8'); ?>",
        csrfToken: <?php echo json_encode(generate_csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        lang: {
            saveError: <?php echo json_encode($lang['Something went wrong. Please try again.'] ?? 'Something went wrong. Please try again.', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
        }
    };
</script>
<?php
/** Relative to /staff/calendar.php — same as admin; survives wrong DB site_url when app lives in a subfolder. */
$__calendarJsPath = __DIR__ . '/../assets/js/calendar.js';
$__calendarJsVer = (string)(@filemtime($__calendarJsPath) ?: time());
$__calendarJsSrcRel = '../assets/js/calendar.js?v=' . $__calendarJsVer;
$__stickyHeadersJsPath = __DIR__ . '/../assets/js/kanban-sticky-headers.js';
$__stickyHeadersJsVer = (string)(@filemtime($__stickyHeadersJsPath) ?: time());
$__stickyHeadersJsSrcRel = '../assets/js/kanban-sticky-headers.js?v=' . $__stickyHeadersJsVer;
?>
<script id="comon-kanban-sticky-headers-script" data-cfasync="false" src="<?php echo htmlspecialchars($__stickyHeadersJsSrcRel, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script id="comon-calendar-js-script" data-cfasync="false" src="<?php echo htmlspecialchars($__calendarJsSrcRel, ENT_QUOTES, 'UTF-8'); ?>" onload="window.__calendarScriptLoaded=1" onerror="window.__calendarScriptLoaded=0;window.__calendarScriptLoadError=(window.__calendarScriptLoadError||'error');"></script>
<?php
$__calFooterBuf = ob_get_clean();
if ($__calFooterBuf === false) {
    $__calFooterBuf = '<!-- calendar footer: ob_get_clean failed (buffer stack) -->';
}
// Echoed before </body> in templates/main-footer.php (after core scripts).
$GLOBALS['comon_before_body_close_html'] = $__calFooterBuf;
include("../templates/main-footer.php");
