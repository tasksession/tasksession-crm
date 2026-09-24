<?php
ob_start();
require_once("../includes/lib-initialize.php");
require_once("../includes/task.php");
require_once("../includes/calendar_event.php");
require_once("../includes/functions.php");
require_once("../includes/permissions.php");

header('Content-Type: application/json');

function calendar_more_first_n_words($text, $limit = 15)
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

function calendar_more_initials_avatar_html($name, $colorIndex = 1)
{
    $safeName = trim((string)$name);
    $initial = $safeName !== '' ? strtoupper(substr($safeName, 0, 1)) : 'U';
    $color = (int)$colorIndex;
    if ($color < 1 || $color > 8) {
        $color = 1;
    }
    return '<div class="avatar-initials color-' . $color . ' avatar-initials-small rounded-circle">' . htmlspecialchars($initial) . '</div>';
}

if (!($session->isLoggedIn())) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

$userId = (int)$session->userId;
if (isset($_SESSION['accountStatus']) && (int)$_SESSION['accountStatus'] === 3) {
    ensure_user_permissions($connect);
}
$isStaffCalendarContext = isset($_SESSION['accountStatus']) && (int)$_SESSION['accountStatus'] === 3;
$kanbanCanViewAllTasks = !$isStaffCalendarContext || staff_kanban_can_view_all_tasks();
$kanbanCanViewCreatedTab = !$isStaffCalendarContext || staff_kanban_can_view_created_tasks_tab();
$kanbanShowAllTasksTab = !$isStaffCalendarContext || staff_kanban_show_all_tasks_tab();
$canEditTask = !$isStaffCalendarContext || has_permission('task_edit');
$canDeleteTask = !$isStaffCalendarContext || has_permission('task_delete');
$basePath = rtrim((string)parse_url((string)$url, PHP_URL_PATH), '/');
$calendarReturnBase = $basePath . ($isStaffCalendarContext ? '/staff/calendar' : '/admin/calendar');
$dateKey = trim((string)($_POST['date'] ?? ''));
$offset = max(0, (int)($_POST['offset'] ?? 20));
$limit = (int)($_POST['limit'] ?? 20);
if ($limit <= 0 || $limit > 100) {
    $limit = 20;
}
$showMyTasks = !isset($_POST['all_tasks']) || (string)$_POST['all_tasks'] !== '1';
if ($isStaffCalendarContext && !$showMyTasks && !$kanbanShowAllTasksTab) {
    $showMyTasks = true;
}
$calendarTaskScope = $isStaffCalendarContext
    ? staff_calendar_resolve_task_scope($showMyTasks)
    : ($showMyTasks ? 'my' : 'all');
$allTasksParam = $showMyTasks ? '' : '&all_tasks=1';
$searchQuery = trim((string)($_POST['search'] ?? ''));
$startDateFilter = trim((string)($_POST['start_date'] ?? ''));
$endDateFilter = trim((string)($_POST['end_date'] ?? ''));
if ($startDateFilter !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDateFilter)) {
    $startDateFilter = '';
}
if ($endDateFilter !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDateFilter)) {
    $endDateFilter = '';
}
$taskDateMode = trim((string)($_POST['task_date_mode'] ?? 'all'));
$sortOrder = trim((string)($_POST['sort_order'] ?? 'asc'));
if (!in_array($taskDateMode, ['all', 'due', 'start'], true)) {
    $taskDateMode = 'all';
}
if (!in_array($sortOrder, ['asc', 'desc'], true)) {
    $sortOrder = 'asc';
}
$currentUser = User::findById($userId);
$currentUserName = $currentUser ? trim((string)($currentUser->firstName ?? '')) : '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateKey)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid date']);
    exit;
}

$scheduledTasksByDay = [];
$eventsByDay = [];
$scheduledTaskIds = [];

if (CalendarEvent::moduleReady()) {
    $scheduledTasks = CalendarEvent::listScheduledTasksByMonth($userId, $dateKey, $dateKey, $calendarTaskScope);
    foreach ($scheduledTasks as $taskRow) {
        if (!staff_calendar_task_row_in_scope($taskRow, $userId, $calendarTaskScope)) {
            continue;
        }
        $tid = (int)($taskRow['task_id'] ?? 0);
        if ($tid > 0) {
            $scheduledTaskIds[$tid] = true;
        }
        if ($searchQuery !== '' && stripos((string)($taskRow['title'] ?? ''), $searchQuery) === false) {
            continue;
        }
        $scheduledTasksByDay[] = $taskRow;
    }

    $calendarEventsOnlyMine = ($calendarTaskScope !== 'all');
    $events = CalendarEvent::listEventsByMonth($userId, $dateKey, $dateKey, $calendarEventsOnlyMine);
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
        if ($eventDate !== $dateKey) {
            continue;
        }
        if ($searchQuery !== '' && stripos((string)($eventRow['title'] ?? ''), $searchQuery) === false) {
            continue;
        }
        $eventsByDay[] = $eventRow;
    }
}

// Fallback tasks (same rule as calendar page).
$taskDetailsMap = [];
$allTasks = Task::findAll();
foreach ($allTasks as $taskObj) {
    $taskDetailsMap[(int)$taskObj->id] = $taskObj;
}
if ($calendarTaskScope !== 'all') {
    $allTasks = array_filter($allTasks, function ($task) use ($userId, $calendarTaskScope) {
        return staff_calendar_task_in_scope($task, $userId, $calendarTaskScope);
    });
}

foreach ($allTasks as $task) {
    $taskIdInt = (int)$task->id;
    if (isset($scheduledTaskIds[$taskIdInt])) {
        continue;
    }
    $taskTitle = (string)($task->title ?? '');
    if ($searchQuery !== '' && stripos($taskTitle, $searchQuery) === false) {
        continue;
    }
    $startDateRaw = (string)($task->start_date ?? '');
    $dueDateRaw = (string)($task->due_date ?? '');
    $taskDate = '';
    if ($taskDateMode === 'due') {
        if ($dueDateRaw !== '' && $dueDateRaw !== '0000-00-00') {
            $taskDate = $dueDateRaw;
        }
    } elseif ($taskDateMode === 'start') {
        if ($startDateRaw !== '' && $startDateRaw !== '0000-00-00') {
            $taskDate = $startDateRaw;
        }
    } else {
        if ($dueDateRaw !== '' && $dueDateRaw !== '0000-00-00') {
            $taskDate = $dueDateRaw;
        } elseif ($startDateRaw !== '' && $startDateRaw !== '0000-00-00') {
            $taskDate = $startDateRaw;
        }
    }
    if ($taskDate !== $dateKey) {
        continue;
    }
    if ($startDateFilter !== '' && $taskDate < $startDateFilter) {
        continue;
    }
    if ($endDateFilter !== '' && $taskDate > $endDateFilter) {
        continue;
    }
    $exists = false;
    foreach ($scheduledTasksByDay as $existing) {
        if ((int)($existing['task_id'] ?? 0) === $taskIdInt) {
            $exists = true;
            break;
        }
    }
    if ($exists) {
        continue;
    }
    $scheduledTasksByDay[] = [
        'task_id' => $taskIdInt,
        'schedule_date' => $taskDate,
        'title' => $taskTitle,
        'status' => (string)($task->status ?? 'todo'),
        'project_id' => (int)($task->project_id ?? 0),
        'assigned_to' => (string)($task->assigned_to ?? ''),
        'description' => (string)($task->description ?? ''),
        'start_date' => (string)($task->start_date ?? ''),
        'due_date' => (string)($task->due_date ?? '')
    ];
}

// Task de-dupe.
$unique = [];
$taskRows = [];
foreach ($scheduledTasksByDay as $row) {
    $tid = (int)($row['task_id'] ?? 0);
    if ($tid <= 0 || isset($unique[$tid])) {
        continue;
    }
    $unique[$tid] = true;
    $taskRows[] = $row;
}

$combined = CalendarEvent::mergeCalendarDayCardsForDisplay($taskRows, $eventsByDay, $sortOrder);

$totalCount = count($combined);
$slice = array_slice($combined, $offset, $limit);
$nextOffset = $offset + count($slice);
$remaining = max(0, $totalCount - $nextOffset);

// Build staff cache for avatars.
$staffCache = [];
foreach ($taskRows as $row) {
    $assigned = array_map('trim', explode(',', (string)($row['assigned_to'] ?? '')));
    foreach ($assigned as $sidRaw) {
        $sid = (int)$sidRaw;
        if ($sid <= 0 || isset($staffCache[$sid])) {
            continue;
        }
        $staffCache[$sid] = User::findById($sid);
    }
}

ob_start();
foreach ($slice as $entry) {
    if ($entry['type'] === 'task') {
        $task = $entry['data'];
        $taskId = (int)($task['task_id'] ?? 0);
        $taskDetail = isset($taskDetailsMap[$taskId]) ? $taskDetailsMap[$taskId] : (object)$task;
        $projectIdForCard = (int)($taskDetail->project_id ?? ($task['project_id'] ?? 0));
        $titleForCard = htmlspecialchars((string)($taskDetail->title ?? ($task['title'] ?? '')));
        $descriptionForCard = calendar_more_first_n_words((string)($taskDetail->description ?? ($task['description'] ?? '')), 15);
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
                                <span class="internal-badge mb-2"><?php echo $lang['Internal Task'] ?? 'Internal Task'; ?></span>
                            <?php endif; ?>
                        </div>
                        <h5 class="card-title mb-1"><?php echo $titleForCard; ?></h5>
                    </div>
                    <div class="dropdown">
                        <button class="btn-dots" type="button" data-bs-toggle="dropdown">
                            <?php echo ts_icon('dots-vertical', 'w-6'); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item d-flex align-items-center" href="#" onclick="openTaskSidebar(<?php echo $taskId; ?>); return false;"><?php echo ts_icon('eye', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['View Task'] ?? 'View Task'; ?></a></li>
                            <?php if ($canEditTask): ?>
                            <li><a class="dropdown-item d-flex align-items-center" href="edit_task?id=<?php echo $taskId; ?>&source=calendar<?php echo $allTasksParam; ?>&return=<?php echo urlencode($calendarReturnBase . (!$showMyTasks ? '?all_tasks=1' : '')); ?>"><?php echo ts_icon('edit', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Edit Task'] ?? 'Edit task'; ?></a></li>
                            <?php endif; ?>
                            <?php if ($canDeleteTask): ?>
                            <li><a class="dropdown-item d-flex align-items-center text-danger" href="#" onclick="deleteTask(<?php echo $taskId; ?>); return false;"><?php echo ts_icon('delete', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Delete Task'] ?? 'Delete task'; ?></a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                <?php if ($descriptionForCard !== ''): ?><div class="task-description mb-2"><?php echo htmlspecialchars($descriptionForCard); ?></div><?php endif; ?>
                <?php if ($startDate !== '' || $dueDate !== ''): ?>
                    <div class="task-date d-flex">
                        <?php if ($startDate !== '' && $startDate !== '0000-00-00'): ?><div class="start-date"><b><?php echo $lang['Start'] ?? 'Start'; ?>:</b> <?php echo date('M j, Y', strtotime($startDate)); ?></div><?php endif; ?>
                        <?php if ($dueDate !== '' && $dueDate !== '0000-00-00'): ?><div><b><?php echo $lang['Due'] ?? 'Due'; ?>:</b> <?php echo date('M j, Y', strtotime($dueDate)); ?></div><?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($assignedTo !== ''): ?>
                    <div class="team-col d-flex align-items-baseline">
                        <div class="d-flex avatar-head">
                            <?php foreach (array_map('trim', explode(',', $assignedTo)) as $staffIdRaw):
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
        <?php
    } else {
        $event = $entry['data'];
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
                        <div class="project-badge"><span class="internal-badge mb-2"><?php echo htmlspecialchars($eventBadgeText); ?></span></div>
                        <h5 class="card-title mb-1"><?php echo htmlspecialchars((string)($event['title'] ?? '')); ?></h5>
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
                    <div class="task-description mb-2"><?php echo htmlspecialchars(calendar_more_first_n_words((string)$event['description'], 15)); ?></div>
                <?php elseif (!empty($event['location_label'])): ?>
                    <div class="task-description mb-2"><?php echo htmlspecialchars((string)$event['location_label']); ?></div>
                <?php endif; ?>
                <?php if (!empty($event['start_datetime']) || !empty($event['end_datetime']) || !empty($event['event_date'])): ?>
                    <div class="task-date d-flex">
                        <?php if (!empty($event['start_datetime'])): ?>
                            <div class="start-date"><b><?php echo $lang['Start'] ?? 'Start'; ?>:</b> <?php echo date('M j, Y', strtotime($event['start_datetime'])); ?></div>
                        <?php elseif (!empty($event['event_date'])): ?>
                            <div class="start-date"><b><?php echo $lang['Start'] ?? 'Start'; ?>:</b> <?php echo date('M j, Y', strtotime($event['event_date'])); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($event['end_datetime'])): ?>
                            <div><b><?php echo $lang['Due'] ?? 'Due'; ?>:</b> <?php echo date('M j, Y', strtotime($event['end_datetime'])); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($eventParticipants) && is_array($eventParticipants)): ?>
                    <div class="team-col d-flex align-items-baseline">
                        <div class="d-flex avatar-head">
                            <?php foreach ($eventParticipants as $idx => $participant):
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
                                    if ($participantUserId > 0) {
                                        $pu = User::findById($participantUserId);
                                        if ($pu) {
                                            echo getUserAvatarHtml($participantUserId, $pu->firstName, $pu->lastName ?? '', 30, 30, 'rounded-circle', $pu->firstName);
                                        } else {
                                            echo calendar_more_initials_avatar_html($participantName, (($idx % 8) + 1));
                                        }
                                    } else {
                                        echo calendar_more_initials_avatar_html($participantName, (($idx % 8) + 1));
                                    }
                                    ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

$html = ob_get_clean();
echo json_encode([
    'status' => 'ok',
    'html' => $html,
    'remaining_count' => $remaining,
    'has_more' => $remaining > 0,
    'next_offset' => $nextOffset,
    'total_count' => $totalCount
]);
exit;
?>
