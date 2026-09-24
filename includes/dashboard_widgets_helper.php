<?php
/**
 * Shared dashboard widget helpers (admin + staff dashboards).
 */

require_once __DIR__ . '/dashboard_empty_state.php';

if (!function_exists('dashWidgetPreloadTaskAssignees')) {
    /**
     * Preload assignee avatars for dashboard task widgets.
     */
    function dashWidgetPreloadTaskAssignees(array $tasks, array &$dashboardUsersById) {
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
}

if (!function_exists('dashWidgetRenderMyTaskRows')) {
    function dashWidgetRenderMyTaskRows(array $tasks, $url, array $lang, array $dashboardUsersById, $rolePrefix = 'staff/', $canEditTask = null) {
        if (empty($tasks)) {
            renderDashboardEmptyState('tasks', $lang['No tasks found.'] ?? 'No tasks found.');
            return;
        }

        $showEdit = true;
        if ($canEditTask === false) {
            $showEdit = false;
        } elseif ($canEditTask === null && function_exists('has_permission')) {
            $showEdit = has_permission('task_edit');
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
                        <?php if ($showEdit) : ?>
                        <li>
                            <a class="dropdown-item" href="<?php echo $url . $rolePrefix; ?>edit_task?id=<?php echo (int) $task->id; ?>">
                                <?php echo ts_icon('edit', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Edit Task'] ?? 'Edit Task'; ?>
                            </a>
                        </li>
                        <?php endif; ?>
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
}

if (!function_exists('dashWidgetModuleDisabledOverlay')) {
    /**
     * Disabled module overlay (same markup/classes as invoice sales stats).
     */
    function dashWidgetModuleDisabledOverlay(string $url, array $lang, string $title, string $message, string $settingsHref = ''): void
    {
        ?>
        <div class="sales-stats-overlay">
            <div class="overlay-card">
                <h3><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h3>
                <p><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php if ($settingsHref !== '') : ?>
                <a href="<?php echo htmlspecialchars($settingsHref, ENT_QUOTES, 'UTF-8'); ?>" class="primary-btn"><?php echo htmlspecialchars($lang['Enable Now'] ?? 'Enable Now', ENT_QUOTES, 'UTF-8'); ?></a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('dashWidgetLeadStatusColorHex')) {
    /**
     * Resolve lead status color_class to hex (matches leads.php column colors).
     */
    function dashWidgetLeadStatusColorHex(string $colorClass): string
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
}

if (!function_exists('dashWidgetLeadCurrencyBaseCode')) {
    /**
     * Base currency code from stored lead currency string (USD,$ / Rs,Rs).
     */
    function dashWidgetLeadCurrencyBaseCode(string $currency): string
    {
        $parts = preg_split('/[,\(]/', trim($currency));
        $base = is_array($parts) ? trim((string) ($parts[0] ?? '')) : '';

        return strtoupper($base);
    }
}

if (!function_exists('dashWidgetLeadCurrencyDisplayName')) {
    /**
     * Dropdown label for enabled lead currency.
     */
    function dashWidgetLeadCurrencyDisplayName(string $currency): string
    {
        $currency = trim($currency);
        if ($currency === '') {
            return 'USD';
        }

        $parts = explode(',', $currency);
        $code = trim((string) ($parts[0] ?? ''));

        return $code !== '' ? $code : $currency;
    }
}

if (!function_exists('dashWidgetLeadsPipelineCurrencyDropdown')) {
    /**
     * Render leads pipeline currency dropdown (before View all).
     */
    function dashWidgetLeadsPipelineCurrencyDropdown(array $data): void
    {
        $metricsByCurrency = is_array($data['metrics_by_currency'] ?? null) ? $data['metrics_by_currency'] : [];
        if ($metricsByCurrency === []) {
            return;
        }

        $defaultCurrency = (string) ($data['default_currency'] ?? array_key_first($metricsByCurrency));
        $defaultLabel = (string) (($metricsByCurrency[$defaultCurrency]['display_name'] ?? '') ?: dashWidgetLeadCurrencyDisplayName($defaultCurrency));
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
                            <?php echo htmlspecialchars((string) ($metricRow['display_name'] ?? dashWidgetLeadCurrencyDisplayName((string) $currencyKey)), ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('dashWidgetLeadsPipelineBuild')) {
    /**
     * Build leads pipeline snapshot for dashboard widget.
     *
     * @return array<string,mixed>
     */
    function dashWidgetLeadsPipelineBuild(array $lang, string $leadsHref = 'leads'): array
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

        $tableCheck = $connect->query("SHOW TABLES LIKE 'lead_statuses'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
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
                'bar_color' => dashWidgetLeadStatusColorHex($colorClass),
                'href' => $leadsHref,
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
            $systemBase = dashWidgetLeadCurrencyBaseCode($systemCurrency);
            $hasSystemCurrency = false;
            foreach ($enabledCurrencies as $enabledCurrency) {
                if (dashWidgetLeadCurrencyBaseCode((string) $enabledCurrency) === $systemBase) {
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
            $baseCode = dashWidgetLeadCurrencyBaseCode($enabledCurrency);
            $pipelineValue = 0.0;
            $valuedLeadCount = 0;
            foreach ($totalsByDbCurrency as $dbCurrency => $row) {
                if (dashWidgetLeadCurrencyBaseCode((string) $dbCurrency) !== $baseCode) {
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
                'display_name' => dashWidgetLeadCurrencyDisplayName($enabledCurrency),
            ];
        }

        $defaultCurrency = $systemCurrency;
        if (!isset($metricsByCurrency[$defaultCurrency])) {
            $systemBase = dashWidgetLeadCurrencyBaseCode($systemCurrency);
            foreach (array_keys($metricsByCurrency) as $currencyKey) {
                if (dashWidgetLeadCurrencyBaseCode((string) $currencyKey) === $systemBase) {
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
            'display_name' => dashWidgetLeadCurrencyDisplayName($defaultCurrency),
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
}

if (!function_exists('dashWidgetLeadsPipelineRender')) {
    /**
     * Render leads pipeline dashboard widget (reference layout).
     */
    function dashWidgetLeadsPipelineRender(array $data, array $lang): void
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
}
