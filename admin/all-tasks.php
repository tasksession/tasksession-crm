<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : all-tasks.php
   Purpose : Displays and manages all tasks across all projects
 ================================================================================
 */
ob_start();
require_once("../includes/lib-initialize.php");
require_once("../includes/permissions.php");
if (function_exists('ensure_user_permissions') && isset($connect)) {
    ensure_user_permissions($connect);
}

// Auth before any HTML so redirectTo() can send a real HTTP redirect.
if (!($session->isLoggedIn())) {
    redirectTo($url . "index.php");
}
if ((int) ($_SESSION['accountStatus'] ?? 0) === 2) {
    redirectTo($url . "client/index.php");
}
if ((int) ($_SESSION['accountStatus'] ?? 0) === 3) {
    redirectTo($url . "staff/index.php");
}

$id = (int) $session->userId;
$user = User::findById($id);
if (!$user) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "All Tasks: logged-in user #{$id} not found in users table. Re-login or check users.id.";
    exit;
}

$title = "All Tasks | " . $syatem_title;
include("../templates/header.php");

$username = $user->firstName;
$email = $user->email;

// Handle task actions
if(isset($_POST['delete_task']) && isset($_POST['taskId'])) {
    require_once("../includes/task.php");
    $taskId = (int)$_POST['taskId'];
    $task = Task::findById($taskId);
    
    if($task) {
        if($task->delete()) {
            header("Location: all-tasks?message=deleted");
            exit;
        } else {
            header("Location: all-tasks?message=fail");
            exit;
        }
    } else {
        header("Location: all-tasks?message=fail");
        exit;
    }
}

// Handle bulk delete (kanban-style POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete_action']) && $_POST['bulk_delete_action'] === 'delete') {
    require_once("../includes/task.php");
    if (isset($_POST['bulk_delete_tasks']) && is_array($_POST['bulk_delete_tasks'])) {
        $taskIds = array_map('intval', $_POST['bulk_delete_tasks']);
        $taskIds = array_filter($taskIds);
        if (!empty($taskIds)) {
            $deletedCount = 0;
            foreach ($taskIds as $taskId) {
                $task = Task::findById($taskId);
                if ($task && $task->delete()) {
                    $deletedCount++;
                }
            }
            if ($deletedCount > 0) {
                header("Location: all-tasks?message=bulk_deleted&count=" . $deletedCount);
                exit;
            }
        }
    }
    header("Location: all-tasks?message=fail");
    exit;
}

// Handle bulk delete
if(isset($_POST['bulk_delete'])) {
    require_once("../includes/task.php");
    $taskIds = isset($_POST['task_ids']) ? $_POST['task_ids'] : '';
    
    if(!empty($taskIds)) {
        $deletedCount = 0;
        $taskIdArray = explode(',', $taskIds);
        
        foreach($taskIdArray as $taskId) {
            $taskId = (int)$taskId;
            $task = Task::findById($taskId);
            
            if($task && $task->delete()) {
                $deletedCount++;
            }
        }
        
        if($deletedCount > 0) {
            header("Location: all-tasks?message=bulk_deleted&count=".$deletedCount);
            exit;
        } else {
            header("Location: all-tasks?message=fail");
            exit;
        }
    } else {
        header("Location: all-tasks?message=no_selection");
        exit;
    }
}

// Handle status messages (toast)
/** @var array{type:string,msg:string}|null assets/js/toast.js reads window.__toastFlash */
$toast_flash = null;
if (isset($_GET['message'])) {
    $msgstatus = $_GET['message'];

    if ($msgstatus == 'success') {
        $toast_flash = array('type' => 'success', 'msg' => 'Task updated successfully');
    } elseif ($msgstatus == 'created') {
        $toast_flash = array('type' => 'success', 'msg' => 'Task has been created successfully!');
    } elseif ($msgstatus == 'deleted') {
        $toast_flash = array('type' => 'success', 'msg' => 'Task has been deleted successfully!');
    } elseif ($msgstatus == 'bulk_deleted') {
        $count = isset($_GET['count']) ? (int)$_GET['count'] : 0;
        $toast_flash = array('type' => 'success', 'msg' => $count . ' task(s) have been deleted successfully!');
    } elseif ($msgstatus == 'no_selection') {
        $toast_flash = array('type' => 'info', 'msg' => 'Please select at least one task to delete.');
    } elseif ($msgstatus == 'fail') {
        $toast_flash = array('type' => 'error', 'msg' => 'Error! Please try again later.');
    }
}

// Load tasks model
require_once("../includes/task.php");
require_once("../includes/all_tasks_list_filters.php");
require_once("../includes/all_tasks_table_helper.php");

// Pagination settings
$limit = 14;  // Show 14 tasks per page
$page = isset($_GET["page"]) ? max(1, (int)$_GET["page"]) : 1;  // Ensure page is at least 1
$start_from = ($page-1) * $limit;

// Define status types and labels
$statusTypes = ['todo', 'inprogress', 'review', 'done'];
$statusLabels = [
    'todo' => 'To Do',
    'inprogress' => 'In Progress',
    'review' => 'In Review',
    'done' => 'Completed'
];
$statusColors = [
    'todo' => 'todo todo-bg-op',
    'inprogress' => 'inprogress inprogress-bg-op',
    'review' => 'review review-bg-op',
    'done' => 'done review done-bg-op '
];
$allStatusFilters = ['todo', 'inprogress', 'review', 'done', 'overdue', 'due_today', 'due_soon', 'task_pro', 'on_time', 'recurring'];

// Load custom column names from database
global $database;
$columnNamesQuery = "SELECT column_key, custom_name FROM project_columns WHERE project_id = 0";
$columnNamesResult = $database->query($columnNamesQuery);

if ($columnNamesResult && $database->numRows($columnNamesResult) > 0) {
    while ($columnRow = $database->fetchArray($columnNamesResult)) {
        $key = $columnRow['column_key'];
        if (isset($statusLabels[$key])) {
            $statusLabels[$key] = $columnRow['custom_name'];
        }
    }
}
// Get status filter from URL
$statusFilter = isset($_GET['status']) && in_array($_GET['status'], $allStatusFilters, true) ? $_GET['status'] : null;
$internalFilter = isset($_GET['internal']) && $_GET['internal'] == '1';

// Get search query from URL
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$startDateFilter = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDateFilter = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$sortOrder = isset($_GET['sort_order']) ? trim((string)$_GET['sort_order']) : 'desc';
if (!in_array($sortOrder, ['asc', 'desc'], true)) {
    $sortOrder = 'desc';
}
$sortDirection = $sortOrder === 'asc' ? 'ASC' : 'DESC';

// Check if we're showing "My Tasks", "All Tasks", or "Archive"
$showMyTasks = !isset($_GET['all_tasks']) || $_GET['all_tasks'] !== '1';
$showArchive = isset($_GET['archive']);

function buildAllTasksListUrl(array $overrides = []) {
    global $statusFilter, $searchQuery, $showMyTasks, $showArchive, $sortOrder, $internalFilter, $startDateFilter, $endDateFilter;

    $params = array();
    if ($showArchive) {
        $params['archive'] = '1';
    } elseif (!$showMyTasks) {
        $params['all_tasks'] = '1';
    }
    if ($statusFilter) {
        $params['status'] = $statusFilter;
    }
    if ($internalFilter) {
        $params['internal'] = '1';
    }
    if ($searchQuery !== '') {
        $params['search'] = $searchQuery;
    }
    if ($startDateFilter !== '') {
        $params['start_date'] = $startDateFilter;
    }
    if ($endDateFilter !== '') {
        $params['end_date'] = $endDateFilter;
    }
    if (!empty($sortOrder)) {
        $params['sort_order'] = $sortOrder;
    }
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === false || $value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }
    $qs = http_build_query($params);
    return 'all-tasks' . ($qs !== '' ? '?' . $qs : '');
}

// Get total task count for "All Tasks" tab (active tasks only)
$totalCountQuery = "SELECT COUNT(*) as total FROM tasks WHERE " . Task::activeTasksWhere();
$totalCountResult = $database->query($totalCountQuery);
$totalCountData = $database->fetchArray($totalCountResult);
$total_all_tasks = $totalCountData['total'];

// Get count of tasks assigned to current user for "My Tasks" tab
$myTasksCountQuery = "SELECT COUNT(*) as total FROM tasks WHERE FIND_IN_SET($id, assigned_to) > 0 AND " . Task::activeTasksWhere();
$myTasksCountResult = $database->query($myTasksCountQuery);
$myTasksCountData = $database->fetchArray($myTasksCountResult);
$total_my_tasks = $myTasksCountData['total'];

$archivedCountResult = $database->query("SELECT COUNT(*) as total FROM tasks WHERE is_archived = 1");
$archivedCountData = $database->fetchArray($archivedCountResult);
$total_archived_tasks = (int)($archivedCountData['total'] ?? 0);

$display_task_count = $showArchive ? $total_archived_tasks : ($showMyTasks ? $total_my_tasks : $total_all_tasks);

$projectId = 0;
if (!isset($recentProject) || !is_object($recentProject)) {
    $recentProject = (object) ['p_id' => 0];
}
$statuses = allTasksListBuildStatuses($statusLabels, $id);

// Get task counts for pagination (based on status filter if present)
$countQuery = "SELECT COUNT(*) as total FROM tasks";
$countWhere = [];
if ($showArchive) {
    $countWhere[] = "is_archived = 1";
} else {
    $countWhere[] = Task::activeTasksWhere();
}
if ($internalFilter) {
    $countWhere[] = "project_id = 0";
}
$statusFilterSql = allTasksListStatusFilterSql($statusFilter);
if ($statusFilterSql) {
    $countWhere[] = $statusFilterSql;
}
$dateFilterSql = allTasksListDateRangeSql($startDateFilter, $endDateFilter, '');
if ($dateFilterSql) {
    $countWhere[] = $dateFilterSql;
}
if ($searchQuery !== '') {
    $searchSafe = $database->escapeValue($searchQuery);
    $countWhere[] = "(title LIKE '%$searchSafe%' OR description LIKE '%$searchSafe%')";
}
// Filter by assigned tasks if showing "My Tasks" (not on archive view)
if ($showMyTasks && !$showArchive) {
    $countWhere[] = "FIND_IN_SET($id, assigned_to) > 0";
}
if (count($countWhere) > 0) {
    $countQuery .= " WHERE " . implode(' AND ', $countWhere);
}
$countResult = $database->query($countQuery);
$countData = $database->fetchArray($countResult);
$total_records = $countData['total'];
$total_pages = ceil($total_records / $limit);

// Get all tasks with project info - with optional status and search filter
$tasksQuery = "SELECT " . allTasksTableSelectSql() . "
    FROM tasks t
    " . allTasksTableJoinSql();

$where = [];
if ($showArchive) {
    $where[] = "t.is_archived = 1";
} else {
    $where[] = Task::activeTasksWhere('t');
}
if ($internalFilter) {
    $where[] = "t.project_id = 0";
}
$taskStatusFilterSql = allTasksListStatusFilterSql($statusFilter, 't');
if ($taskStatusFilterSql) {
    $where[] = $taskStatusFilterSql;
}
$taskDateFilterSql = allTasksListDateRangeSql($startDateFilter, $endDateFilter, 't');
if ($taskDateFilterSql) {
    $where[] = $taskDateFilterSql;
}
if ($searchQuery !== '') {
    $searchSafe = $database->escapeValue($searchQuery);
    $where[] = "(t.title LIKE '%$searchSafe%' OR t.description LIKE '%$searchSafe%')";
}
// Filter by assigned tasks if showing "My Tasks" (not on archive view)
if ($showMyTasks && !$showArchive) {
    $where[] = "FIND_IN_SET($id, t.assigned_to) > 0";
}
if (count($where) > 0) {
    $tasksQuery .= " WHERE " . implode(' AND ', $where);
}

$tasksQuery .= " ORDER BY t.created_at $sortDirection 
              LIMIT $start_from, $limit";

$result = $database->query($tasksQuery);
$tasks = [];

while($row = $database->fetchArray($result)) {
    $tasks[] = $row;
}

$listPaginationParams = array();
if ($statusFilter) {
    $listPaginationParams['status'] = $statusFilter;
}
if ($internalFilter) {
    $listPaginationParams['internal'] = 1;
}
if ($startDateFilter !== '') {
    $listPaginationParams['start_date'] = $startDateFilter;
}
if ($endDateFilter !== '') {
    $listPaginationParams['end_date'] = $endDateFilter;
}
if ($showArchive) {
    $listPaginationParams['archive'] = 1;
} elseif (!$showMyTasks) {
    $listPaginationParams['all_tasks'] = 1;
}
if ($searchQuery !== '') {
    $listPaginationParams['search'] = $searchQuery;
}
if (!empty($sortOrder)) {
    $listPaginationParams['sort_order'] = $sortOrder;
}
$allTasksPaginationMeta = tasksession_list_pagination_meta(
    (int) $total_records,
    $start_from,
    count($tasks),
    $listPaginationParams
);

$viewQueryParams = array();
if ($showArchive) {
    $viewQueryParams['archive'] = 1;
} elseif (!$showMyTasks) {
    $viewQueryParams['all_tasks'] = 1;
}
if ($statusFilter) {
    $viewQueryParams['status'] = $statusFilter;
}
if ($internalFilter) {
    $viewQueryParams['internal'] = 1;
}
if ($startDateFilter !== '') {
    $viewQueryParams['start_date'] = $startDateFilter;
}
if ($endDateFilter !== '') {
    $viewQueryParams['end_date'] = $endDateFilter;
}
if ($searchQuery !== '') {
    $viewQueryParams['search'] = $searchQuery;
}
if (!empty($sortOrder)) {
    $viewQueryParams['sort_order'] = $sortOrder;
}
$viewQueryString = !empty($viewQueryParams) ? '?' . http_build_query($viewQueryParams) : '';
$kanbanGridViewUrl = 'kanban' . $viewQueryString;
$kanbanTableViewUrl = 'all-tasks' . $viewQueryString;
$allTasksSearchClearHref = buildAllTasksListUrl(array('search' => null));
$calendarTabQueryParams = $viewQueryParams;
unset($calendarTabQueryParams['search']);
$allTasksCalendarTabHref = 'calendar' . (!empty($calendarTabQueryParams) ? '?' . http_build_query($calendarTabQueryParams) : '');
?>

<div class="page-container">
    <div class="container-fluid">
        <div class="row row-eq-height">
            <?php include("../templates/sidebar.php"); ?>
            <div class="page-content">
                <?php include('../templates/top-header.php'); ?>
                <link rel="stylesheet" href="../assets/css/kanban-bulk.css?v=10">
                <div class="row bg-grey">
                    <div class="col-md-12 project-tabs">
					 <div class="row">
					   <div class="project-tabs-header">
							<div class="scrollable-tabs-container d-flex col-gap-40 col-gap-40-sep">
								<div class="main-heading"><h1><?php echo $lang['All Tasks']; ?> <span>(<?php echo $display_task_count; ?>)</span></h1></div>
								<div class="icon-container sep" id="kanbanNavTabs">
                                    <a href="<?php echo htmlspecialchars(buildAllTasksListUrl(array('archive' => null, 'all_tasks' => null))); ?>" class="<?php echo ($showMyTasks && !$showArchive) ? 'active' : ''; ?>">
													<?php echo ts_icon('tasks'); ?><?php echo $lang['My Tasks']; ?> (<?php echo $total_my_tasks; ?>)</a>
													<a href="<?php echo htmlspecialchars(buildAllTasksListUrl(array('archive' => null, 'all_tasks' => '1'))); ?>" class="<?php echo (!$showMyTasks && !$showArchive) ? 'active' : ''; ?>">
														<?php echo ts_icon('inbox-stack'); ?><?php echo $lang['All Tasks']; ?> (<?php echo $total_all_tasks; ?>)</a>
												<a href="<?php echo htmlspecialchars(buildAllTasksListUrl(array('archive' => '1', 'all_tasks' => null))); ?>" class="<?php echo $showArchive ? 'active' : ''; ?>">
													<?php echo ts_icon('archive-box'); ?>
													<?php echo $lang['Archive'] ?? 'Archive'; ?> (<?php echo $total_archived_tasks; ?>)
												</a>
												<a href="<?php echo htmlspecialchars($allTasksCalendarTabHref, ENT_QUOTES, 'UTF-8'); ?>">
													<?php echo ts_icon('calendar'); ?><?php echo $lang['Calendar View'] ?? 'Calendar View'; ?>
												</a>
                                </div>
                            </div>
                        </div>
                        <div class="search">
														<div class="search-icon border-btn-a" onclick="toggleSearch()">
															<?php echo ts_icon('search', 'w-2'); ?>
														</div>
														<form method="GET" action="" class="search-form" id="searchForm">
															<?php if($statusFilter): ?>
																<input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
															<?php endif; ?>
															<?php if($internalFilter): ?>
																<input type="hidden" name="internal" value="1">
															<?php endif; ?>
															<?php if($startDateFilter): ?>
																<input type="hidden" name="start_date" value="<?php echo htmlspecialchars($startDateFilter); ?>">
															<?php endif; ?>
															<?php if($endDateFilter): ?>
																<input type="hidden" name="end_date" value="<?php echo htmlspecialchars($endDateFilter); ?>">
															<?php endif; ?>
															<?php if(!$showMyTasks && !$showArchive): ?>
																<input type="hidden" name="all_tasks" value="1">
															<?php endif; ?>
															<?php if($showArchive): ?>
																<input type="hidden" name="archive" value="1">
															<?php endif; ?>
															<input type="hidden" name="sort_order" value="<?php echo htmlspecialchars($sortOrder); ?>">
															<div class="input-group">
																<span class="search-field-icon">
																	<?php echo ts_icon('search', 'w-2'); ?>
																</span>
																<input type="text" 
																	   name="search" 
																	   class="form-control" 
																	   placeholder="<?php echo htmlspecialchars($lang['Search tasks...'] ?? 'Search tasks...', ENT_QUOTES, 'UTF-8'); ?>" 
																	   value="<?php echo htmlspecialchars($searchQuery ?? ''); ?>">
																<?php if ($searchQuery !== ''): ?>
																	<a href="<?php echo htmlspecialchars($allTasksSearchClearHref, ENT_QUOTES, 'UTF-8'); ?>" class="cross" title="<?php echo htmlspecialchars($lang['Close'] ?? 'Close', ENT_QUOTES, 'UTF-8'); ?>">
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
                                    <div class="edit-overview-btn d-none d-md-block" id="kanbanBulkToolbar">
                                        <div class="icon-container sep">
                                            <div class="pm-trash task-trash align-middle d-flex col-gap-5">
                                                <a href="#" onclick="enterBulkMode(); return false;" class="bulk-delete-tab red border-btn-a" id="bulkSelectTab" title="<?php echo htmlspecialchars($lang['Bulk Select'] ?? 'Bulk select', ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($lang['Bulk Select'] ?? 'Bulk select', ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php echo ts_icon('duplicate', 'w-2'); ?>
                                                </a>
                                                <div class="media-view-toggle" id="kanbanViewToggle" role="group" aria-label="View mode">
                                                    <a href="<?php echo htmlspecialchars($kanbanGridViewUrl, ENT_QUOTES, 'UTF-8'); ?>" class="media-view-toggle__btn border-btn-a" title="<?php echo htmlspecialchars($lang['Grid View'] ?? 'Grid view', ENT_QUOTES, 'UTF-8'); ?>">
                                                        <?php echo ts_icon('view-grid', 'w-2'); ?>
                                                    </a>
                                                    <a href="<?php echo htmlspecialchars($kanbanTableViewUrl, ENT_QUOTES, 'UTF-8'); ?>" class="media-view-toggle__btn border-btn-a is-active" title="<?php echo htmlspecialchars($lang['Table View'] ?? 'Table view', ENT_QUOTES, 'UTF-8'); ?>">
                                                        <?php echo ts_icon('table', 'w-2'); ?>
                                                    </a>
                                                </div>
                                                <a href="#" onclick="exitBulkMode(); return false;" class="bulk-delete-tab border-btn-a" id="bulkBackTab" style="display:none;">
                                                    <?php echo ts_icon('arrow-left', 'w-2'); ?>
                                                    <span><?php echo $lang['Back'] ?? 'Back'; ?></span>
                                                </a>
                                                <a href="#" onclick="selectAllTasks(); return false;" class="bulk-delete-tab border-btn-a" id="bulkSelectAllTab" style="display:none;">
                                                    <?php echo ts_icon('check-circle', 'w-2'); ?>
                                                    <span id="bulkSelectAllLabel"><?php echo $lang['Select all'] ?? 'Select all'; ?></span>
                                                </a>
                                                <a href="#" onclick="confirmBulkDelete(); return false;" class="bulk-delete-tab border-btn-a" id="bulkDeleteTab" style="display:none;">
                                                    <?php echo ts_icon('delete', 'w-2'); ?>
                                                    <span id="bulkDeleteLabel"><?php echo $lang['Delete'] ?? 'Delete'; ?></span>
                                                </a>
                                                <a href="#" onclick="confirmBulkArchive(); return false;" class="bulk-delete-tab border-btn-a" id="bulkArchiveTab" style="display:none;">
                                                    <?php echo ts_icon('archive-box', 'w-2'); ?>
                                                    <span id="bulkArchiveLabel"><?php echo $showArchive ? ($lang['Unarchive Task'] ?? 'Unarchive') : ($lang['Archive'] ?? 'Archive'); ?></span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                    $aiMenuId = 'aiHeaderMenuAllTasks';
                                    $aiMenuPartial = dirname(__DIR__) . '/templates/partials/ai-menu.php';
                                    if (is_readable($aiMenuPartial)) {
                                        try {
                                            include $aiMenuPartial;
                                        } catch (Throwable $e) {
                                            error_log('all-tasks ai-menu: ' . $e->getMessage());
                                        }
                                    }
                                    unset($aiMenuId);
                                    include(dirname(__DIR__) . '/templates/partials/all-tasks-filter-dropdown.php');
                                    ?>
										<div class="d-none d-md-block kanban-header-add-btn">
											  <?php if($projectId > 0): ?>
												<a href="add_task?projectId=<?= (int) $projectId ?>&source=table" class="primary-btn">
													<?php echo $lang['Add New Task']; ?> <?php echo ts_icon('plus', 'w-2'); ?>
												</a>
											<?php else: ?>
												<a href="add_task?source=table" class="primary-btn">
													<?php echo $lang['Add New Task']; ?> <?php echo ts_icon('plus', 'w-2'); ?>
												</a>
											<?php endif; ?>
										</div>
									</div>
								 </div>
                  </div><div class="clearfix"></div>
                          <?php if ($startDateFilter || $endDateFilter): ?>
                          <div class="alert alert-success mb-3 mx-3">
                              <strong>Date Filter Active:</strong>
                              <?php
                              if ($startDateFilter && $endDateFilter) {
                                  echo 'From ' . date('M j, Y', strtotime($startDateFilter)) . ' to ' . date('M j, Y', strtotime($endDateFilter));
                              } elseif ($startDateFilter) {
                                  echo 'From ' . date('M j, Y', strtotime($startDateFilter)) . ' onwards';
                              } elseif ($endDateFilter) {
                                  echo 'Until ' . date('M j, Y', strtotime($endDateFilter));
                              }
                              ?>
                              <a href="#" onclick="clearDateRange(); return false;" class="float-end btn btn-sm btn-outline-success">Clear Date Filter</a>
                          </div>
                          <?php endif; ?>
											<div class="vh-100" data-ts-list-bulk-root="all-tasks">
                            <div class="table-responsive scroll-x vh-100">
                                <table class="table table-new projectspage all-tasks-table" id="tasks-table">
                                    <?php echo allTasksTableTheadHtml($lang, true); ?>
                                    <tbody id="projects-tbl">
                                        <?php
                                        $allTasksActionsUi = 'legacy';
                                        include dirname(__DIR__) . '/partials/all_tasks_table_rows.php';
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                            <!-- Pagination -->
                            <?php if ($total_records > $limit): ?>
                            <?php
                                tasksession_render_list_pagination(
                                    $allTasksPaginationMeta,
                                    (int) $total_records,
                                    (int) $total_pages,
                                    $page
                                );
                            ?>
                            <?php endif; ?>
                            <?php if ($total_records > 0 && ($statusFilter || $internalFilter || $startDateFilter || $endDateFilter)): ?>
                                <p class="text-muted small mb-0 px-3">(filtered from <?php echo $display_task_count; ?> total entries)</p>
                            <?php endif; ?>
                        </div>
            </div>
        </div>
    </div>
</div>
<script>
window.kanbanIsArchiveView = <?php echo $showArchive ? 'true' : 'false'; ?>;
window.kanbanCanDelete = true;
window.kanbanCanArchive = true;
window.langSelectAll = <?php echo json_encode($lang['Select all'] ?? 'Select all'); ?>;
window.langDeselectAll = <?php echo json_encode($lang['Deselect all'] ?? 'Deselect all'); ?>;
window.langArchive = <?php echo json_encode($lang['Archive'] ?? 'Archive'); ?>;
window.langUnarchive = <?php echo json_encode($lang['Unarchive Task'] ?? 'Unarchive'); ?>;
window.langBulkDeleteConfirm = <?php echo json_encode($lang['Bulk delete confirm'] ?? 'Are you sure you want to delete %d task(s)? This action cannot be undone.'); ?>;
window.langBulkDeleteNone = <?php echo json_encode($lang['Bulk delete none'] ?? 'Please select at least one task to delete.'); ?>;
window.langBulkArchiveConfirm = <?php echo json_encode($lang['Bulk archive confirm'] ?? 'Are you sure you want to archive %d selected task(s)?'); ?>;
window.langBulkUnarchiveConfirm = <?php echo json_encode($lang['Bulk unarchive confirm'] ?? 'Restore %d selected task(s) to the active board?'); ?>;
window.langBulkArchiveNone = <?php echo json_encode($lang['Bulk archive none'] ?? 'Please select at least one task.'); ?>;
window.langBulkArchiveFail = <?php echo json_encode($lang['Bulk archive fail'] ?? 'Failed to update tasks'); ?>;
window.langBulkPartialFail = <?php echo json_encode($lang['Bulk partial fail'] ?? 'Some tasks could not be updated.'); ?>;
</script>
<script src="../assets/js/task-bulk-delete.js"></script>
<script src="../assets/js/task-date-range.js"></script>
<script src="../assets/js/task.js"></script>
<script>window.__toastFlash=<?php echo json_encode($toast_flash, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
<script src="<?php echo htmlspecialchars(rtrim((string) $url, '/') . '/assets/js/toast.js', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php include("../templates/main-footer.php"); ?>
