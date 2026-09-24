<?php
$canViewAllTasks = !function_exists('has_permission') || has_permission('task_view_all');
$canCreateTask = !function_exists('has_permission') || has_permission('task_create');
?><link rel="stylesheet" href="../assets/css/kanban-bulk.css?v=1">
                <div class="row bg-grey">
                    <div class="col-md-12 margin-top-10 project-tabs">
					 <div class="row">
					   <div class="project-tabs-header">
							<div class="scrollable-tabs-container d-flex col-gap-40 col-gap-40-sep">
								<div class="main-heading">
                                    <h1>
                                        <?php echo $lang['All Tasks']; ?>
                                        <?php if ($statusFilter): ?>
                                            - <?php
                                            switch($statusFilter) {
                                                case 'overdue': echo $lang['Overdue']; break;
                                                case 'due_today': echo $lang['Due Today']; break;
                                                case 'due_soon': echo $lang['Due Soon'] ?? 'Due Soon'; break;
                                                case 'task_pro': echo $lang['Task Pro']; break;
                                                case 'on_time': echo $lang['On Time']; break;
                                                default: echo ucfirst($statusFilter) . ' Tasks';
                                            }
                                            ?>
                                        <?php endif; ?>
                                        <?php if ($startDateFilter || $endDateFilter): ?> - Date Range <?php endif; ?>
                                        <span>(<?php echo $showArchive ? $total_records : ($showMyTasks ? $total_my_tasks : $total_all_tasks); ?> <?php echo $lang['Task']; ?>)</span>
                                    </h1>
                                </div>
								<div class="icon-container sep" id="kanbanNavTabs">
                                    <a href="<?php echo htmlspecialchars(buildFilterUrl('all-tasks', [], 'my')); ?>" class="<?php echo ($showMyTasks && !$showArchive) ? 'active' : ''; ?>">
                                        <?php echo ts_icon('tasks'); ?><?php echo $lang['My Tasks']; ?> (<?php echo $total_my_tasks; ?>)</a>
                                    <?php if ($canViewAllTasks): ?><a href="<?php echo htmlspecialchars(buildFilterUrl('all-tasks', [], 'all')); ?>" class="<?php echo (!$showMyTasks && !$showArchive) ? 'active' : ''; ?>">
                                        <?php echo ts_icon('inbox-stack'); ?><?php echo $lang['All Tasks']; ?> (<?php echo $total_all_tasks; ?>)</a><?php endif; ?>
                                    <a href="<?php echo htmlspecialchars(buildFilterUrl('all-tasks', array_merge($viewUrlExtra, ['archive' => '1']), 'archive')); ?>" class="<?php echo $showArchive ? 'active' : ''; ?>">
                                        <?php echo ts_icon('archive'); ?>
                                        <?php echo $lang['Archive'] ?? 'Archive'; ?> (<?php echo $total_archived_tasks; ?>)
                                    </a>
                                    <a href="<?php echo htmlspecialchars($kanbanGridViewUrl); ?>">
                                        <?php echo ts_icon('view-grid'); ?>
                                        <?php echo $lang['Kanban View'] ?? 'Kanban view'; ?>
                                    </a>
                                    <a href="calendar<?php echo !$showMyTasks ? '?all_tasks=1' : ''; ?>">
                                        <?php echo ts_icon('calendar'); ?>
                                        <?php echo $lang['Calendar View'] ?? 'Calendar View'; ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="search">
                            <div class="search-icon" onclick="toggleSearch()">
                                <?php echo ts_icon('search'); ?>
                            </div>
                            <form method="GET" action="" class="search-form" id="searchForm">
                                <?php if($statusFilter): ?>
                                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                                <?php endif; ?>
                                <?php if($internalFilter): ?>
                                    <input type="hidden" name="internal" value="1">
                                <?php endif; ?>
                                <?php if($showArchive): ?>
                                    <input type="hidden" name="archive" value="1">
                                <?php elseif(!$showMyTasks && $canViewAllTasks): ?>
                                    <input type="hidden" name="all_tasks" value="1">
                                <?php endif; ?>
                                <?php if($startDateFilter): ?>
                                    <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($startDateFilter); ?>">
                                <?php endif; ?>
                                <?php if($endDateFilter): ?>
                                    <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($endDateFilter); ?>">
                                <?php endif; ?>
                                <input type="hidden" name="sort_order" value="<?php echo htmlspecialchars($sortOrder); ?>">
                                <div class="input-group">
                                    <input type="text" name="search" class="form-control" placeholder="Search tasks..." value="<?php echo htmlspecialchars($searchQuery ?? ''); ?>">
                                    <button type="submit" class="search-icon">
                                        <?php echo ts_icon('search'); ?>
                                    </button>
                                    <?php if(isset($searchQuery) && !empty($searchQuery)): ?>
                                        <a href="<?php echo htmlspecialchars(buildFilterUrl('all-tasks')); ?>" class="cross">
                                            <?php echo ts_icon('close'); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                        <div class="edit-overview-btn d-none d-md-block" id="kanbanBulkToolbar">
                            <div class="icon-container sep">
                                <div class="pm-trash task-trash align-middle d-flex col-gap">
                                    <div class="media-view-toggle" id="kanbanViewToggle" role="group" aria-label="View mode">
                                        <a href="<?php echo htmlspecialchars($kanbanGridViewUrl); ?>" class="media-view-toggle__btn" title="<?php echo htmlspecialchars($lang['Grid View'] ?? 'Grid view'); ?>">
                                            <?php echo ts_icon('view-grid', 'w-5'); ?>
                                        </a>
                                        <a href="<?php echo htmlspecialchars($kanbanTableViewUrl); ?>" class="media-view-toggle__btn is-active" title="<?php echo htmlspecialchars($lang['Table View'] ?? 'Table view'); ?>">
                                            <?php echo ts_icon('table', 'w-5'); ?>
                                        </a>
                                    </div>
                                    <a href="#" onclick="enterBulkMode(); return false;" class="bulk-delete-tab red" id="bulkSelectTab">
                                        <span><?php echo $lang['Bulk Select'] ?? 'Bulk Select'; ?></span>
                                    </a>
                                    <a href="#" onclick="exitBulkMode(); return false;" class="bulk-delete-tab" id="bulkBackTab" style="display:none;">
                                        <?php echo ts_icon('arrow-left'); ?>
                                        <span><?php echo $lang['Back'] ?? 'Back'; ?></span>
                                    </a>
                                    <a href="#" onclick="selectAllTasks(); return false;" class="bulk-delete-tab" id="bulkSelectAllTab" style="display:none;">
                                        <span id="bulkSelectAllLabel"><?php echo $lang['Select all'] ?? 'Select all'; ?></span>
                                    </a>
                                    <a href="#" onclick="confirmBulkDelete(); return false;" class="bulk-delete-tab" id="bulkDeleteTab" style="display:none;">
                                        <?php echo ts_icon('delete'); ?>
                                        <span id="bulkDeleteLabel"><?php echo $lang['Delete'] ?? 'Delete'; ?></span>
                                    </a>
                                    <a href="#" onclick="confirmBulkArchive(); return false;" class="bulk-delete-tab" id="bulkArchiveTab" style="display:none;">
                                        <?php echo ts_icon('archive'); ?>
                                        <span id="bulkArchiveLabel"><?php echo $showArchive ? ($lang['Unarchive Task'] ?? 'Unarchive') : ($lang['Archive'] ?? 'Archive'); ?></span>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="edit-overview-btn kanban-header-filters">
                            <td class="extra-height">
                                <div class="action-toggle" data-bs-toggle="collapse" data-bs-target="#project-menu<?php echo $recentProject->p_id;?>">
                                    <span class="action-text"><?php echo $lang['Filters']; ?></span>
                                    <span class="mobile-ellipsis"><?php echo ts_icon('ellipsis'); ?></span>
                                    <?php echo ts_icon('filter'); ?>
                                </div>
                                <div id="project-menu<?php echo $recentProject->p_id;?>" class="toggle-action collapse shadow-dept">
                                    <ul>
                                        <li class="<?php echo !$statusFilter && !$internalFilter ? 'active' : ''; ?>" data-status="all">
                                            <a href="?<?php echo $filterArchivePrefix . $filterAllTasksPrefix; ?><?php echo $startDateFilter ? 'start_date=' . htmlspecialchars($startDateFilter) . '&' : ''; ?><?php echo $endDateFilter ? 'end_date=' . htmlspecialchars($endDateFilter) . '&' : ''; ?><?php echo !empty($sortOrder) ? 'sort_order=' . htmlspecialchars($sortOrder) : ''; ?>">
                                                <span><b><?php echo $lang['All Task']; ?></b></span>
                                            </a>
                                        </li>
                                        <li class="<?php echo $internalFilter ? 'active' : ''; ?>" data-status="internal">
                                            <a href="?internal=1&<?php echo $filterArchivePrefix . $filterAllTasksPrefix; ?><?php echo $startDateFilter ? 'start_date=' . htmlspecialchars($startDateFilter) . '&' : ''; ?><?php echo $endDateFilter ? 'end_date=' . htmlspecialchars($endDateFilter) . '&' : ''; ?><?php echo !empty($sortOrder) ? 'sort_order=' . htmlspecialchars($sortOrder) : ''; ?>">
                                                <span><?php echo $lang['Internal Task']; ?> <i class="dots text-secondary object-align-right"></i></span>
                                            </a>
                                        </li>
                                        <?php foreach ($statusTypes as $status): ?>
                                        <li class="<?php echo $statusFilter === $status ? 'active' : ''; ?>" data-status="<?php echo $status; ?>">
                                            <a href="?status=<?php echo $status; ?>&<?php echo $filterArchivePrefix . $filterAllTasksPrefix; ?><?php echo $startDateFilter ? 'start_date=' . htmlspecialchars($startDateFilter) . '&' : ''; ?><?php echo $endDateFilter ? 'end_date=' . htmlspecialchars($endDateFilter) . '&' : ''; ?><?php echo !empty($sortOrder) ? 'sort_order=' . htmlspecialchars($sortOrder) : ''; ?>">
                                                <span><?php echo $statusLabels[$status]; ?> <i class="dots <?php echo $statuses[$status]['icon'] ?? 'text-secondary'; ?> object-align-right"></i></span>
                                            </a>
                                        </li>
                                        <?php endforeach; ?>
                                        <hr>
                                        <li><b><?php echo $lang['Sort By'] ?? 'Sort By'; ?></b></li>
                                        <li class="<?php echo $sortOrder === 'asc' ? 'active' : ''; ?>">
                                            <a href="?<?php echo $statusFilter ? 'status=' . htmlspecialchars($statusFilter) . '&' : ''; ?><?php echo $internalFilter ? 'internal=1&' : ''; ?><?php echo $filterArchivePrefix . $filterAllTasksPrefix; ?><?php echo $startDateFilter ? 'start_date=' . htmlspecialchars($startDateFilter) . '&' : ''; ?><?php echo $endDateFilter ? 'end_date=' . htmlspecialchars($endDateFilter) . '&' : ''; ?>sort_order=asc">
                                                <span><?php echo $lang['Ascending'] ?? 'Ascending'; ?></span>
                                            </a>
                                        </li>
                                        <li class="<?php echo $sortOrder === 'desc' ? 'active' : ''; ?>">
                                            <a href="?<?php echo $statusFilter ? 'status=' . htmlspecialchars($statusFilter) . '&' : ''; ?><?php echo $internalFilter ? 'internal=1&' : ''; ?><?php echo $filterArchivePrefix . $filterAllTasksPrefix; ?><?php echo $startDateFilter ? 'start_date=' . htmlspecialchars($startDateFilter) . '&' : ''; ?><?php echo $endDateFilter ? 'end_date=' . htmlspecialchars($endDateFilter) . '&' : ''; ?>sort_order=desc">
                                                <span><?php echo $lang['Descending'] ?? 'Descending'; ?></span>
                                            </a>
                                        </li>
                                        <hr>
                                        <li><b><?php echo $lang['By Status']; ?></b></li>
                                        <?php
                                        $badgeFilters = ['overdue' => $lang['Overdue'], 'due_today' => $lang['Due Today'], 'due_soon' => $lang['Due Soon'] ?? 'Due Soon', 'task_pro' => $lang['Task Pro'], 'on_time' => $lang['On Time']];
                                        foreach ($badgeFilters as $badgeKey => $badgeLabel):
                                        ?>
                                        <li class="<?php echo $statusFilter === $badgeKey ? 'active' : ''; ?>">
                                            <a href="?status=<?php echo $badgeKey; ?>&<?php echo $filterArchivePrefix . $filterAllTasksPrefix; ?><?php echo $startDateFilter ? 'start_date=' . htmlspecialchars($startDateFilter) . '&' : ''; ?><?php echo $endDateFilter ? 'end_date=' . htmlspecialchars($endDateFilter) . '&' : ''; ?><?php echo !empty($sortOrder) ? 'sort_order=' . htmlspecialchars($sortOrder) : ''; ?>">
                                                <span><?php echo $badgeLabel; ?></span>
                                            </a>
                                        </li>
                                        <?php endforeach; ?>
                                        <hr>
                                        <li><a class="secondary-btn-a" href="#" onclick="toggleDateRangeCard(); return false;"><span><?php echo $lang['Select Date Range']; ?></span></a></li>
                                        <div class="date-range card" style="display: none;">
                                            <div class="card-body">
                                                <b class="mb-2 d-block"><?php echo $lang['Quick Range']; ?></b>
                                                <div>
                                                    <li><button class="dropdown-item" onclick="selectQuickRange('last7')"><?php echo $lang['Last 7 days']; ?></button></li>
                                                    <li><button class="dropdown-item" onclick="selectQuickRange('last30')"><?php echo $lang['Last 30 days']; ?></button></li>
                                                    <li><button class="dropdown-item" onclick="selectQuickRange('last90')"><?php echo $lang['Last 90 days']; ?></button></li>
                                                    <li><button class="dropdown-item" onclick="selectQuickRange('last6months')"><?php echo $lang['Last 6 months']; ?></button></li>
                                                    <li><button class="dropdown-item" onclick="selectQuickRange('thisYear')"><?php echo $lang['This Year (Jan - Today)']; ?></button></li>
                                                </div>
                                                <hr>
                                                <div class="mb-3">
                                                    <div class="date-f"><label class="form-label font-size-10"><?php echo $lang['Start Date']; ?></label><input type="date" id="customStart" class="form-control"></div>
                                                    <div class="date-f"><label class="form-label font-size-10"><?php echo $lang['End Date']; ?></label><input type="date" id="customEnd" class="form-control"></div>
                                                </div>
                                                <div class="d-flex col-gap-10">
                                                    <button class="primary-btn" onclick="selectCustomRange()"><?php echo $lang['Apply']; ?></button>
                                                    <button class="btn border-btn-a" onclick="clearDateRange()"><?php echo $lang['Clear']; ?></button>
                                                </div>
                                            </div>
                                        </div>
                                    </ul>
                                </div>
                            </td>
                        </div>
                        <div class="d-none d-md-block kanban-header-add-btn">
                            <a href="add_task" class="primary-btn"><?php echo $lang['Add New Task']; ?> <?php echo ts_icon('plus', 'dropdown-toggle-icon h-6'); ?></a>
                        </div>
                    </div>
                </div>
                          <div class="clearfix"></div>
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
                       
