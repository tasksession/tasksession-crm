<?php
if (!isset($filterMenuId)) {
    $filterMenuId = (isset($recentProject) && is_object($recentProject) && isset($recentProject->p_id))
        ? (int) $recentProject->p_id
        : 0;
}
$showMobileAddTask = !isset($showMobileAddTask) || $showMobileAddTask;
$hasActiveListFilters = !empty($statusFilter) || !empty($internalFilter) || ($startDateFilter !== '') || ($endDateFilter !== '');
$clearFiltersUrl = buildAllTasksListUrl(array(
    'status' => null,
    'internal' => null,
    'start_date' => null,
    'end_date' => null,
));
?>
<div class="edit-overview-btn kanban-header-filters">
    <td class="extra-height">
        <div class="action-toggle border-btn-a<?php echo $hasActiveListFilters ? ' has-active-filters' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#project-menu<?php echo $filterMenuId; ?>" role="button" tabindex="0">
            <span class="action-text"><?php echo $lang['Filters']; ?></span>
            <span class="mobile-ellipsis"><?php echo ts_icon('ellipsis', 'w-2'); ?></span>
            <?php echo ts_icon('filter', 'w-2'); ?>
            <?php if ($hasActiveListFilters): ?>
            <a href="<?php echo htmlspecialchars($clearFiltersUrl); ?>" class="kanban-filter-clear" title="<?php echo htmlspecialchars($lang['Clear'] ?? 'Clear', ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($lang['Clear filters'] ?? 'Clear filters', ENT_QUOTES, 'UTF-8'); ?>" onclick="event.stopPropagation();">
                <?php echo ts_icon('close'); ?>
            </a>
            <?php endif; ?>
        </div>
        <div id="project-menu<?php echo $filterMenuId; ?>" class="toggle-action collapse shadow-dept">
            <ul>
                <?php if ($showMobileAddTask): ?>
                <li class="primary-btn d-block d-md-none">
                    <?php if (!empty($projectId)): ?>
                        <a href="add_task?projectId=<?php echo (int) $projectId; ?>">
                            <span><?php echo $lang['Add New Task']; ?> <?php echo ts_icon('plus', 'dropdown-toggle-icon h-6 tasksession-timer-log-menu-ico me-2'); ?></span>
                        </a>
                    <?php else: ?>
                        <a href="add_task">
                            <span><?php echo $lang['Add New Task']; ?> <?php echo ts_icon('plus', 'dropdown-toggle-icon h-6'); ?></span>
                        </a>
                    <?php endif; ?>
                </li>
                <?php endif; ?>
                <li class="<?php echo !$statusFilter && !$internalFilter ? 'active' : ''; ?>" data-status="all">
                    <a href="<?php echo htmlspecialchars(buildAllTasksListUrl(array('status' => null, 'internal' => null))); ?>">
                        <span><b><?php echo $lang['All Task']; ?></b></span>
                    </a>
                </li>
                <li class="<?php echo $internalFilter ? 'active' : ''; ?>" data-status="internal">
                    <a href="<?php echo htmlspecialchars(buildAllTasksListUrl(array('internal' => '1', 'status' => null))); ?>">
                        <span><?php echo $lang['Internal Task']; ?> <i class="dots text-secondary object-align-right"></i></span>
                    </a>
                </li>
                <li class="<?php echo $statusFilter === 'recurring' ? 'active' : ''; ?>" data-status="recurring">
                    <a href="<?php echo htmlspecialchars(buildAllTasksListUrl(array('status' => 'recurring', 'internal' => null))); ?>">
                        <span>
                            <?php echo htmlspecialchars($lang['Recurring task'] ?? 'Recurring task', ENT_QUOTES, 'UTF-8'); ?>
                            <?php echo function_exists('ts_icon') ? ts_icon('refresh', 'w-2 object-align-right') : ''; ?>
                        </span>
                    </a>
                </li>
                <?php foreach ($statusTypes as $status): ?>
                <li class="<?php echo $statusFilter === $status ? 'active' : ''; ?>" data-status="<?php echo $status; ?>">
                    <a href="<?php echo htmlspecialchars(buildAllTasksListUrl(array('status' => $status, 'internal' => null))); ?>">
                        <span><?php echo $statusLabels[$status]; ?> <i class="dots <?php echo $statuses[$status]['icon'] ?? 'text-secondary'; ?> object-align-right"></i></span>
                    </a>
                </li>
                <?php endforeach; ?>
                <hr>
                <li><b><?php echo $lang['Sort By'] ?? 'Sort by'; ?></b></li>
                <li class="<?php echo $sortOrder === 'asc' ? 'active' : ''; ?>" data-status="ascending">
                    <a href="<?php echo htmlspecialchars(buildAllTasksListUrl(array('sort_order' => 'asc'))); ?>">
                        <span>
                            <?php echo ts_icon('arrow-up'); ?>
                            <?php echo $lang['Ascending'] ?? 'Ascending'; ?>
                        </span>
                    </a>
                </li>
                <li class="<?php echo $sortOrder === 'desc' ? 'active' : ''; ?>" data-status="descending">
                    <a href="<?php echo htmlspecialchars(buildAllTasksListUrl(array('sort_order' => 'desc'))); ?>">
                        <span>
                            <?php echo ts_icon('chevron-down'); ?>
                            <?php echo $lang['Descending'] ?? 'Descending'; ?>
                        </span>
                    </a>
                </li>
                <hr>
                <li><b><?php echo $lang['By Status']; ?></b></li>
                <?php
                $badgeFilters = [
                    'overdue' => $lang['Overdue'],
                    'due_today' => $lang['Due Today'],
                    'due_soon' => $lang['Due Soon'] ?? 'Upcoming deadline',
                    'task_pro' => $lang['Task Pro'],
                    'on_time' => $lang['On Time'],
                ];
                foreach ($badgeFilters as $badgeKey => $badgeLabel):
                ?>
                <li class="<?php echo $statusFilter === $badgeKey ? 'active' : ''; ?>" data-status="<?php echo $badgeKey; ?>">
                    <a href="<?php echo htmlspecialchars(buildAllTasksListUrl(array('status' => $badgeKey, 'internal' => null))); ?>">
                        <span><?php echo $badgeLabel; ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
                <hr>
                <li>
                    <a class="secondary-btn-a" href="#" onclick="toggleDateRangeCard(); return false;">
                        <span><?php echo $lang['Select Date Range']; ?></span>
                    </a>
                </li>
                <div class="date-range card" style="display: none;">
                    <div class="card-body">
                        <b class="mb-2 d-block"><?php echo $lang['Quick Range']; ?></b>
                        <div>
                            <li><button class="dropdown-item" type="button" onclick="selectQuickRange('last7')"><?php echo $lang['Last 7 days']; ?></button></li>
                            <li><button class="dropdown-item" type="button" onclick="selectQuickRange('last30')"><?php echo $lang['Last 30 days']; ?></button></li>
                            <li><button class="dropdown-item" type="button" onclick="selectQuickRange('last90')"><?php echo $lang['Last 90 days']; ?></button></li>
                            <li><button class="dropdown-item" type="button" onclick="selectQuickRange('last6months')"><?php echo $lang['Last 6 months']; ?></button></li>
                            <li><button class="dropdown-item" type="button" onclick="selectQuickRange('thisYear')"><?php echo $lang['This Year (Jan - Today)']; ?></button></li>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <div class="date-f"><label class="form-label font-size-10"><?php echo $lang['Start Date']; ?></label><input type="date" id="customStart" class="form-control"></div>
                            <div class="date-f"><label class="form-label font-size-10"><?php echo $lang['End Date']; ?></label><input type="date" id="customEnd" class="form-control"></div>
                        </div>
                        <div class="d-flex col-gap-10">
                            <button class="primary-btn" type="button" onclick="selectCustomRange()" id="primary-btn"><?php echo $lang['Apply']; ?></button>
                            <button class="btn border-btn-a" type="button" onclick="clearDateRange()"><?php echo $lang['Clear']; ?></button>
                        </div>
                    </div>
                </div>
            </ul>
        </div>
    </td>
</div>
