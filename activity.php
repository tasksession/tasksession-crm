<?php
/**
 * Central activity page — full notification feed with search, filters, and load-more.
 */
ob_start();
require_once __DIR__ . '/includes/lib-initialize.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/activity_page_helper.php';

if (!$session->isLoggedIn()) {
    redirectTo(function_exists('tasksession_app_href') ? tasksession_app_href('index.php') : rtrim((string) $url, '/') . '/');
}

$accountStatus = (int) ($_SESSION['accountStatus'] ?? 0);
if (!in_array($accountStatus, [1, 2, 3], true)) {
    redirectTo(function_exists('tasksession_app_href') ? tasksession_app_href('index.php') : rtrim((string) $url, '/') . '/');
}

$activityLimit = 20;
$activityDays = 60;
$userId = (int) $session->userId;
$roleBase = activityPageRoleBase($accountStatus);
$dateRange = activityPageDateRangeLabel($activityDays);
$activityLoadUrl = rtrim((string) $url, '/') . '/ajax/activity_load.php';

$rawActivityNotifs = Notifications::getActivityPageNotifications($userId, $activityLimit, 0, $activityDays);
$activityItems = activityPageItemsFromNotifications(is_array($rawActivityNotifs) ? $rawActivityNotifs : [], $lang, $roleBase, (string) $url);
$activityFilterCounts = activityPageFilterCounts($activityItems);
$hasMoreActivities = is_array($rawActivityNotifs) && count($rawActivityNotifs) >= $activityLimit;

$pageTitle = $lang['Recent activity'] ?? 'Recent activity';
$searchPlaceholder = $lang['Search activities, projects, invoices...'] ?? 'Search activities, projects, invoices...';
$dateRangeHeading = sprintf(
    $lang['Showing activities from last %d days'] ?? 'Showing activities from last %d days',
    $activityDays
);
$dateRangeHeading .= ' · ' . $dateRange['start_label'] . ' – ' . $dateRange['end_label'];

$activityFilters = [
    'all' => $lang['All'] ?? 'All',
    'invoice' => $lang['Invoices'] ?? 'Invoices',
    'project' => $lang['Projects'] ?? 'Projects',
    'task' => $lang['Tasks'] ?? 'Tasks',
    'client' => $lang['Clients'] ?? 'Clients',
    'file' => $lang['Files'] ?? 'Files',
    'ai' => $lang['AI'] ?? 'AI',
];

$title = $pageTitle . ' | ' . $syatem_title;
include __DIR__ . '/templates/header.php';

$user = User::findById($userId);
$username = $user ? (string) ($user->firstName ?? '') : '';
?>
<div class="page-container admin-dashboard">
    <div class="container-fluid">
        <div class="row row-eq-height">
            <?php include __DIR__ . '/templates/sidebar.php'; ?>
            <div class="page-content">
                <?php include __DIR__ . '/templates/top-header.php'; ?>
                <div class="row">
                    <div class="col-md-12 center-col margin-top-10">
                        <div class="project-header page-title mb-4">
                            <h2 class="mb-0"><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h2>
                        </div>

                        <div class="activity-page-search mb-4">
                            <form class="search-form expanded" id="activityPageSearchForm" onsubmit="return false;" aria-label="<?php echo htmlspecialchars($searchPlaceholder, ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="input-group">
                                    <span class="search-field-icon">
                                        <?php echo ts_icon('search', 'w-2'); ?>
                                    </span>
                                    <input type="search"
                                           id="activityPageSearch"
                                           class="form-control"
                                           placeholder="<?php echo htmlspecialchars($searchPlaceholder, ENT_QUOTES, 'UTF-8'); ?>"
                                           autocomplete="off"
                                           aria-label="<?php echo htmlspecialchars($searchPlaceholder, ENT_QUOTES, 'UTF-8'); ?>">
                                    <span class="activity-page-search-kbd font-size-12 grey d-none d-md-inline">Ctrl K</span>
                                </div>
                            </form>
                        </div>

                        <div class="media-view-toggle mb-4" id="activityPageFilters" role="group" aria-label="<?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php foreach ($activityFilters as $filterKey => $filterLabel) : ?>
                            <a href="#"
                               class="border-btn-a<?php echo $filterKey === 'all' ? ' is-active' : ''; ?>"
                               data-activity-filter="<?php echo htmlspecialchars($filterKey, ENT_QUOTES, 'UTF-8'); ?>"
                               aria-pressed="<?php echo $filterKey === 'all' ? 'true' : 'false'; ?>">
                                <?php echo htmlspecialchars($filterLabel, ENT_QUOTES, 'UTF-8'); ?>
                                <span class="activity-filter-count" data-activity-count="<?php echo htmlspecialchars($filterKey, ENT_QUOTES, 'UTF-8'); ?>">(<?php echo (int) ($activityFilterCounts[$filterKey] ?? 0); ?>)</span>
                            </a>
                            <?php endforeach; ?>
                        </div>

                        <div class="font-size-12 grey mb-4 d-flex align-items-center col-gap-5">
                            <?php echo ts_icon('calendar', 'w-2'); ?>
                            <span><?php echo htmlspecialchars($dateRangeHeading, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>

                        <div class="widget-card">
                            <div class="list-group" id="activityList">
                                <?php renderActivityPageItems($activityItems, $lang['No activity found'] ?? 'No activity found', $lang); ?>
                            </div>
                            <div id="activityPageFilterEmpty" class="d-none">
                                <?php renderDashboardEmptyState(
                                    'bell',
                                    $lang['No activities match your search'] ?? 'No activities match your search',
                                    ['list_item' => false]
                                ); ?>
                            </div>
                        </div>

                        <?php if ($hasMoreActivities) : ?>
                        <div class="text-center mt-4" id="activityPageLoadMoreWrap">
                            <div class="border-btn d-inline-block">
                                <a href="#" id="activityPageLoadMore" role="button">
                                    <?php echo htmlspecialchars($lang['Load more activities'] ?? 'Load more activities', ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
window.activityPageConfig = {
    loadUrl: <?php echo json_encode($activityLoadUrl, JSON_UNESCAPED_SLASHES); ?>,
    limit: <?php echo (int) $activityLimit; ?>,
    offset: <?php echo (int) $activityLimit; ?>,
    hasMore: <?php echo $hasMoreActivities ? 'true' : 'false'; ?>,
    labels: {
        loadMore: <?php echo json_encode($lang['Load more activities'] ?? 'Load more activities', JSON_UNESCAPED_UNICODE); ?>,
        loading: <?php echo json_encode($lang['Loading...'] ?? 'Loading...', JSON_UNESCAPED_UNICODE); ?>
    }
};
</script>
<script>
(function () {
    function initActivityPage() {
        var cfg = window.activityPageConfig || {};
        var searchInput = document.getElementById('activityPageSearch');
        var activityList = document.getElementById('activityList');
        var filterEmpty = document.getElementById('activityPageFilterEmpty');
        var filterWrap = document.getElementById('activityPageFilters');
        var loadMoreBtn = document.getElementById('activityPageLoadMore');
        var loadMoreWrap = document.getElementById('activityPageLoadMoreWrap');
        var activeFilter = 'all';
        var loading = false;

        function getRows() {
            if (!activityList) {
                return [];
            }
            return Array.prototype.slice.call(activityList.querySelectorAll('.dash-activity-item[data-activity-type]'));
        }

        function updateFilterCounts() {
            if (!filterWrap) {
                return;
            }
            var query = searchInput ? searchInput.value.trim().toLowerCase() : '';
            var queryAlt = query.replace(/^#/, '');
            var rows = getRows();
            var counts = { all: 0, invoice: 0, project: 0, task: 0, client: 0, file: 0 };
            rows.forEach(function (row) {
                var rowText = row.getAttribute('data-activity-search') || '';
                if (rowText === '') {
                    rowText = (row.textContent || '').toLowerCase();
                }
                var matchesSearch = query === ''
                    || rowText.indexOf(query) !== -1
                    || (queryAlt !== '' && queryAlt !== query && rowText.indexOf(queryAlt) !== -1);
                if (!matchesSearch) {
                    return;
                }
                counts.all++;
                var type = row.getAttribute('data-activity-type') || '';
                if (type && Object.prototype.hasOwnProperty.call(counts, type)) {
                    counts[type]++;
                }
            });
            filterWrap.querySelectorAll('[data-activity-count]').forEach(function (el) {
                var key = el.getAttribute('data-activity-count') || 'all';
                el.textContent = '(' + (counts[key] || 0) + ')';
            });
        }

        function applyActivityFilters() {
            var query = searchInput ? searchInput.value.trim().toLowerCase() : '';
            var queryAlt = query.replace(/^#/, '');
            var rows = getRows();
            var visible = 0;

            rows.forEach(function (row) {
                var rowType = row.getAttribute('data-activity-type') || 'all';
                var matchesFilter = activeFilter === 'all' || rowType === activeFilter;
                var rowText = row.getAttribute('data-activity-search') || '';
                if (rowText === '') {
                    rowText = (row.textContent || '').toLowerCase();
                }
                var matchesSearch = query === ''
                    || rowText.indexOf(query) !== -1
                    || (queryAlt !== '' && queryAlt !== query && rowText.indexOf(queryAlt) !== -1);
                var show = matchesFilter && matchesSearch;
                row.classList.toggle('activity-row-filter-hidden', !show);
                if (show) {
                    visible++;
                }
            });

            updateFilterCounts();

            if (filterEmpty) {
                filterEmpty.classList.toggle('d-none', visible > 0 || rows.length === 0);
            }
        }

        function bindSearchInput() {
            if (!searchInput) {
                return;
            }
            ['input', 'keyup', 'search'].forEach(function (eventName) {
                searchInput.addEventListener(eventName, applyActivityFilters);
            });
        }

        function bindFilterTabs() {
            if (!filterWrap) {
                return;
            }
            filterWrap.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-activity-filter]');
                if (!btn || !filterWrap.contains(btn)) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                activeFilter = btn.getAttribute('data-activity-filter') || 'all';
                filterWrap.querySelectorAll('[data-activity-filter]').forEach(function (chip) {
                    var isActive = chip === btn;
                    chip.classList.toggle('is-active', isActive);
                    chip.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });
                applyActivityFilters();
            });
        }

        function bindTaskSidebarClicks() {
            if (!activityList) {
                return;
            }
            activityList.addEventListener('click', function (e) {
                if (e.target.closest('.btn-dots')) {
                    return;
                }
                var taskTrigger = e.target.closest('.view-task-btn[data-task-id]');
                if (!taskTrigger) {
                    return;
                }
                var taskId = parseInt(taskTrigger.getAttribute('data-task-id') || '0', 10);
                if (!taskId || typeof openTaskSidebar !== 'function') {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                openTaskSidebar(taskId);
            });
            activityList.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' && e.key !== ' ') {
                    return;
                }
                var card = e.target.closest('.view-task-btn[data-task-id]');
                if (!card || e.target.closest('.dropdown, .btn-dots')) {
                    return;
                }
                e.preventDefault();
                card.click();
            });
        }

        document.addEventListener('keydown', function (e) {
            if (!searchInput) {
                return;
            }
            if ((e.ctrlKey || e.metaKey) && String(e.key).toLowerCase() === 'k') {
                e.preventDefault();
                searchInput.focus();
                searchInput.select();
            }
        });

        function appendLoadedRows(html) {
            if (!activityList || !html) {
                return;
            }
            var temp = document.createElement('div');
            temp.innerHTML = html;
            Array.prototype.slice.call(temp.children).forEach(function (node) {
                activityList.appendChild(node);
            });
            applyActivityFilters();
        }

        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', function (e) {
                e.preventDefault();
                if (loading || !cfg.hasMore || !cfg.loadUrl) {
                    return;
                }
                loading = true;
                loadMoreBtn.textContent = cfg.labels.loading || 'Loading...';
                loadMoreBtn.style.pointerEvents = 'none';

                var requestUrl = cfg.loadUrl + '?offset=' + encodeURIComponent(String(cfg.offset || 0));
                fetch(requestUrl, {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (data && data.success && data.html) {
                            appendLoadedRows(data.html);
                        }
                        cfg.offset = (cfg.offset || 0) + (cfg.limit || 20);
                        cfg.hasMore = !!(data && data.has_more);
                        if (!cfg.hasMore && loadMoreWrap) {
                            loadMoreWrap.style.display = 'none';
                        }
                    })
                    .catch(function () {
                        cfg.hasMore = false;
                        if (loadMoreWrap) {
                            loadMoreWrap.style.display = 'none';
                        }
                    })
                    .finally(function () {
                        loading = false;
                        loadMoreBtn.style.pointerEvents = '';
                        loadMoreBtn.textContent = cfg.labels.loadMore || 'Load more activities';
                    });
            });
        }

        bindSearchInput();
        bindFilterTabs();
        bindTaskSidebarClicks();
        applyActivityFilters();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initActivityPage);
    } else {
        initActivityPage();
    }
})();
</script>
<?php include __DIR__ . '/templates/main-footer.php'; ?>
