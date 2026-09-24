<?php
/**
 * Shared helpers for Task Reports and Project Reports.
 */

if (!function_exists('reports_date_presets')) {
    function reports_date_presets(): array
    {
        return [
            'today', 'yesterday', 'this_week', 'last_week',
            'this_month', 'last_month', 'custom',
        ];
    }
}

if (!function_exists('reports_resolve_date_range')) {
    /**
     * @return array{0:string,1:string} [from Y-m-d, to Y-m-d]
     */
    function reports_resolve_date_range(string $preset, string $from = '', string $to = ''): array
    {
        $today = date('Y-m-d');

        switch ($preset) {
            case 'today':
                return [$today, $today];
            case 'yesterday':
                $y = date('Y-m-d', strtotime('-1 day'));
                return [$y, $y];
            case 'this_week':
                return [
                    date('Y-m-d', strtotime('monday this week')),
                    date('Y-m-d', strtotime('sunday this week')),
                ];
            case 'last_week':
                return [
                    date('Y-m-d', strtotime('monday last week')),
                    date('Y-m-d', strtotime('sunday last week')),
                ];
            case 'last_month':
                return [
                    date('Y-m-01', strtotime('first day of last month')),
                    date('Y-m-t', strtotime('last day of last month')),
                ];
            case 'custom':
                if (!reports_valid_ymd($from)) {
                    $from = date('Y-m-01');
                }
                if (!reports_valid_ymd($to)) {
                    $to = date('Y-m-t');
                }
                if (strcmp($from, $to) > 0) {
                    $tmp = $from;
                    $from = $to;
                    $to = $tmp;
                }
                return [$from, $to];
            case 'this_month':
            default:
                return [date('Y-m-01'), date('Y-m-t')];
        }
    }
}

if (!function_exists('reports_sql_valid_date')) {
    /**
     * MySQL 8+ safe date/datetime check — never compare to '' or '0000-00-00' (NO_ZERO_DATE).
     */
    function reports_sql_valid_date(string $columnExpr): string
    {
        return "(UNIX_TIMESTAMP({$columnExpr}) IS NOT NULL AND UNIX_TIMESTAMP({$columnExpr}) > 0)";
    }
}

if (!function_exists('reports_sql_milestone_effective_date')) {
    /**
     * Milestone chart/filter date: issue_date if valid, else deadline.
     *
     * @param string $prefix Optional table alias prefix, e.g. "m."
     */
    function reports_sql_milestone_effective_date(string $prefix = ''): string
    {
        $p = $prefix !== '' ? rtrim($prefix, '.') . '.' : '';
        return "CASE
        WHEN UNIX_TIMESTAMP({$p}issue_date) > 0 THEN {$p}issue_date
        WHEN UNIX_TIMESTAMP({$p}deadline) > 0 THEN {$p}deadline
        ELSE NULL
    END";
    }
}

if (!function_exists('reports_sql_date_between')) {
    /**
     * Column is a valid date/datetime and falls in an inclusive Y-m-d range (two placeholders).
     */
    function reports_sql_date_between(string $columnExpr): string
    {
        return '(' . reports_sql_valid_date($columnExpr) . " AND {$columnExpr} BETWEEN ? AND ?)";
    }
}

if (!function_exists('reports_valid_ymd')) {
    function reports_valid_ymd(string $date): bool
    {
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        $parts = explode('-', $date);
        return checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]);
    }
}

if (!function_exists('reports_parse_date_filters')) {
    /**
     * @param array<string,mixed>|null $input
     * @return array{date_preset:string,from_date:string,to_date:string}
     */
    function reports_parse_date_filters(?array $input = null): array
    {
        if (!is_array($input)) {
            $input = [];
        }
        $preset = isset($input['date_preset']) ? trim((string)$input['date_preset']) : 'this_month';
        if (!in_array($preset, reports_date_presets(), true)) {
            $preset = 'this_month';
        }
        $from = isset($input['from_date']) ? trim((string)$input['from_date']) : '';
        $to = isset($input['to_date']) ? trim((string)$input['to_date']) : '';
        $range = reports_resolve_date_range($preset, $from, $to);
        $range = reports_clamp_date_range($range[0], $range[1]);

        return [
            'date_preset' => $preset,
            'from_date' => $range[0],
            'to_date' => $range[1],
        ];
    }
}

if (!function_exists('reports_default_chart_grouping')) {
    /**
     * Sensible default bar chart grouping for a resolved date filter.
     */
    function reports_default_chart_grouping(array $filters): string
    {
        $preset = (string) ($filters['date_preset'] ?? 'this_month');
        if (in_array($preset, array('today', 'yesterday', 'this_week', 'last_week'), true)) {
            return 'daily';
        }
        if (in_array($preset, array('this_month', 'last_month'), true)) {
            return 'weekly';
        }
        $from = (string) ($filters['from_date'] ?? '');
        $to = (string) ($filters['to_date'] ?? '');
        if (reports_valid_ymd($from) && reports_valid_ymd($to)) {
            $start = strtotime($from);
            $end = strtotime($to);
            if ($start !== false && $end !== false) {
                $days = (int) floor(($end - $start) / 86400) + 1;
                if ($days <= 7) {
                    return 'daily';
                }
                if ($days <= 45) {
                    return 'weekly';
                }

                return 'monthly';
            }
        }

        return 'weekly';
    }
}

if (!function_exists('reports_clamp_date_range')) {
    /**
     * Limit custom ranges to prevent expensive report queries (default 366 days).
     *
     * @return array{0:string,1:string}
     */
    function reports_clamp_date_range(string $from, string $to, int $maxDays = 366): array
    {
        if (!reports_valid_ymd($from) || !reports_valid_ymd($to)) {
            return [$from, $to];
        }
        $start = strtotime($from);
        $end = strtotime($to);
        if ($start === false || $end === false) {
            return [$from, $to];
        }
        if ($end < $start) {
            $tmp = $from;
            $from = $to;
            $to = $tmp;
            $start = strtotime($from);
            $end = strtotime($to);
        }
        $days = (int)floor(($end - $start) / 86400) + 1;
        if ($days <= $maxDays) {
            return [$from, $to];
        }
        $to = date('Y-m-d', strtotime($from . ' +' . ($maxDays - 1) . ' days'));

        return [$from, $to];
    }
}

if (!function_exists('reports_sanitize_action')) {
    /**
     * @param list<string> $allowed
     */
    function reports_sanitize_action(string $action, array $allowed = ['data', 'export']): string
    {
        $action = trim($action);
        return in_array($action, $allowed, true) ? $action : 'data';
    }
}

if (!function_exists('reports_request_input')) {
    /**
     * Report API filters — prefer POST body so hosting WAF does not block USD/$ in query strings.
     *
     * @return array<string,mixed>
     */
    function reports_request_input(): array
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'POST' && is_array($_POST) && !empty($_POST)) {
            return $_POST;
        }
        return is_array($_GET) ? $_GET : [];
    }
}

if (!function_exists('reports_request_action')) {
    function reports_request_action(string $default = 'data'): string
    {
        $input = reports_request_input();
        $action = isset($input['action']) ? (string) $input['action'] : $default;
        return reports_sanitize_action($action);
    }
}

if (!function_exists('reports_send_json_headers')) {
    function reports_send_json_headers(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }
}

if (!function_exists('reports_ajax_endpoint_url')) {
    /**
     * Build absolute AJAX URL regardless of trailing slash on site base URL.
     */
    function reports_ajax_endpoint_url(string $baseUrl, string $script): string
    {
        $path = parse_url($baseUrl, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }
        $path = rtrim($path, '/');
        $script = ltrim((string) $script, '/');
        // AJAX endpoints are real .php files (not pretty-rewritten; /ajax/ is excluded from rewrite).
        if ($script !== '' && !preg_match('/\.php(\?|#|$)/i', $script)) {
            if (strpos($script, '?') !== false) {
                $script = preg_replace('/\?/', '.php?', $script, 1);
            } elseif (strpos($script, '#') !== false) {
                $script = preg_replace('/#/', '.php#', $script, 1);
            } else {
                $script .= '.php';
            }
        }
        return $path . '/ajax/' . $script;
    }
}

if (!function_exists('reports_ecommerce_module_enabled')) {
    /**
     * Ecommerce module gate — false when column missing, file absent, or disabled.
     */
    function reports_ecommerce_module_enabled(): bool
    {
        if (!function_exists('comon_ecommerce_module_enabled')) {
            require_once __DIR__ . '/addon_registry.php';
        }

        return comon_ecommerce_module_enabled();
    }
}

if (!function_exists('reports_load_ecommerce_helpers')) {
    /**
     * Load ecommerce page helpers only when the file exists (optional module).
     */
    function reports_load_ecommerce_helpers(): bool
    {
        $path = __DIR__ . '/ecommerce/page-helpers.php';
        if (!is_file($path)) {
            return false;
        }
        require_once $path;
        return true;
    }
}

if (!function_exists('reports_render_dashboard_skeleton')) {
    /**
     * Include reports skeleton partial when present (safe on partial deploys).
     */
    function reports_render_dashboard_skeleton(string $variant): void
    {
        $path = dirname(__DIR__) . '/partials/reports_dashboard_skeleton.inc.php';
        if (!is_file($path)) {
            return;
        }
        $reportsSkeletonVariant = $variant;
        include $path;
    }
}

if (!function_exists('admin_render_dashboard_skeleton')) {
    /**
     * Admin home dashboard skeleton (matches admin/index.php layout).
     */
    function admin_render_dashboard_skeleton(): void
    {
        global $connect;
        $path = dirname(__DIR__) . '/partials/admin_dashboard_skeleton.inc.php';
        if (!is_file($path)) {
            return;
        }
        include $path;
    }
}

if (!function_exists('reports_suppress_mysqli_exceptions')) {
    /**
     * PHP 8+ mysqli throws on SQL errors by default; reports helpers handle failures gracefully.
     */
    function reports_suppress_mysqli_exceptions(): void
    {
        if (function_exists('mysqli_report')) {
            mysqli_report(MYSQLI_REPORT_OFF);
        }
    }
}

if (!function_exists('reports_send_csv_headers')) {
    function reports_send_csv_headers(string $filename): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9._-]/', '', $filename) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }
}

if (!function_exists('reports_sql_bind_inline')) {
    /**
     * Run a prepared-style query via mysqli::query (no mysqlnd / get_result required).
     *
     * @param array<int,mixed> $params
     * @return mysqli_result|false
     */
    function reports_sql_bind_inline(mysqli $connect, string $sql, string $types, array $params)
    {
        if ($types === '' || $params === []) {
            return $connect->query($sql);
        }
        $parts = explode('?', $sql);
        if (count($parts) !== count($params) + 1) {
            error_log('[reports] placeholder/param count mismatch');
            return false;
        }
        $built = $parts[0];
        $typeLen = strlen($types);
        for ($i = 0; $i < $typeLen; $i++) {
            $type = $types[$i];
            $param = $params[$i];
            if ($type === 'i') {
                $built .= (int) $param;
            } elseif ($type === 'd') {
                $built .= (string) (float) $param;
            } else {
                $built .= "'" . $connect->real_escape_string((string) $param) . "'";
            }
            $built .= $parts[$i + 1];
        }
        $res = $connect->query($built);
        if ($res === false) {
            error_log('[reports] inline query failed: ' . $connect->error);
        }
        return $res;
    }
}

if (!function_exists('reports_bind_and_execute')) {
    /**
     * @param array<int,mixed> $params
     * @return mysqli_result|false
     */
    function reports_bind_and_execute(mysqli $connect, string $sql, string $types, array $params)
    {
        reports_suppress_mysqli_exceptions();
        try {
            $stmt = $connect->prepare($sql);
            if (!$stmt) {
                error_log('[reports] prepare failed: ' . $connect->error);
                return false;
            }
            if ($types !== '') {
                $bindArgs = [$types];
                foreach ($params as $k => $v) {
                    $bindArgs[] = &$params[$k];
                }
                call_user_func_array([$stmt, 'bind_param'], $bindArgs);
            }
            if (!$stmt->execute()) {
                error_log('[reports] execute failed: ' . $stmt->error);
                $stmt->close();
                return false;
            }
            if (!method_exists($stmt, 'get_result')) {
                $stmt->close();
                return reports_sql_bind_inline($connect, $sql, $types, $params);
            }
            $res = $stmt->get_result();
            $stmt->close();
            return $res;
        } catch (Throwable $e) {
            error_log('[reports] bind_and_execute: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('reports_format_duration_short')) {
    function reports_format_duration_short(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0m';
        }
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        if ($h > 0) {
            return $h . 'h ' . $m . 'm';
        }
        return $m . 'm';
    }
}

if (!function_exists('reports_avg_time_sec')) {
    function reports_avg_time_sec(int $totalTimeSec, int $divisor): int
    {
        if ($divisor <= 0 || $totalTimeSec <= 0) {
            return 0;
        }
        return (int)round($totalTimeSec / $divisor);
    }
}

if (!function_exists('reports_calc_percent_change')) {
    function reports_calc_percent_change($current, $previous): float
    {
        $current = (float)$current;
        $previous = (float)$previous;
        $maxPct = 999.0;

        if ($current == 0.0 && $previous == 0.0) {
            return 0.0;
        }

        if ($previous <= 0.0) {
            return $current > 0.0 ? 100.0 : 0.0;
        }

        if ($current == 0.0) {
            return -100.0;
        }

        $pct = (($current - $previous) / $previous) * 100;
        $pct = round($pct, 0);

        if ($pct > $maxPct) {
            return $maxPct;
        }
        if ($pct < -$maxPct) {
            return -$maxPct;
        }

        return $pct;
    }
}

if (!function_exists('reports_comparison_filters')) {
    /**
     * @return array<string,mixed>|null
     */
    function reports_comparison_filters(array $filters): ?array
    {
        $preset = (string)($filters['date_preset'] ?? 'this_month');
        $from = (string)($filters['from_date'] ?? '');
        $to = (string)($filters['to_date'] ?? '');

        switch ($preset) {
            case 'today':
                $prev = date('Y-m-d', strtotime('-1 day'));
                break;
            case 'yesterday':
                $prev = date('Y-m-d', strtotime('-2 day'));
                $from = $prev;
                $to = $prev;
                break;
            case 'this_week':
                $from = date('Y-m-d', strtotime('monday last week'));
                $to = date('Y-m-d', strtotime('sunday last week'));
                break;
            case 'last_week':
                $from = date('Y-m-d', strtotime($from . ' -7 days'));
                $to = date('Y-m-d', strtotime($to . ' -7 days'));
                break;
            case 'this_month':
                $from = date('Y-m-01', strtotime('first day of last month'));
                $to = date('Y-m-t', strtotime('last day of last month'));
                break;
            case 'last_month':
                $from = date('Y-m-01', strtotime($from . ' -1 month'));
                $to = date('Y-m-t', strtotime($from));
                break;
            case 'custom':
                if (!reports_valid_ymd($from) || !reports_valid_ymd($to)) {
                    return null;
                }
                $start = strtotime($from);
                $end = strtotime($to);
                if ($start === false || $end === false) {
                    return null;
                }
                $days = (int)floor(($end - $start) / 86400) + 1;
                $to = date('Y-m-d', strtotime($from . ' -1 day'));
                $from = date('Y-m-d', strtotime($to . ' -' . ($days - 1) . ' days'));
                break;
            default:
                return null;
        }

        if ($preset === 'today') {
            $from = $prev;
            $to = $prev;
        }

        $compare = $filters;
        $compare['from_date'] = $from;
        $compare['to_date'] = $to;
        $compare['date_preset'] = 'custom';

        return $compare;
    }
}

if (!function_exists('reports_comparison_label_key')) {
    function reports_comparison_label_key(string $preset): string
    {
        $map = [
            'today' => 'since_yesterday',
            'yesterday' => 'since_day_before',
            'this_week' => 'since_last_week',
            'last_week' => 'since_previous_week',
            'this_month' => 'since_last_month',
            'last_month' => 'since_previous_month',
            'custom' => 'since_previous_period',
        ];

        return $map[$preset] ?? 'since_previous_period';
    }
}

if (!function_exists('reports_build_card_comparison')) {
    /**
     * @param array<int,string> $metrics
     * @return array<string,array{percent:float,direction:string}>
     */
    function reports_build_card_comparison(array $current, array $previous, array $metrics, ?string $rateNumeratorKey = null, ?string $rateDenominatorKey = null): array
    {
        $out = [];
        foreach ($metrics as $key) {
            $pct = reports_calc_percent_change($current[$key] ?? 0, $previous[$key] ?? 0);
            $direction = 'flat';
            if ($pct > 0) {
                $direction = 'up';
            } elseif ($pct < 0) {
                $direction = 'down';
            }
            $out[$key] = [
                'percent' => $pct,
                'direction' => $direction,
            ];
        }

        if ($rateNumeratorKey !== null && $rateDenominatorKey !== null) {
            $currentTotal = (int)($current[$rateDenominatorKey] ?? 0);
            $prevTotal = (int)($previous[$rateDenominatorKey] ?? 0);
            $currentRate = $currentTotal > 0
                ? round(((int)($current[$rateNumeratorKey] ?? 0) / $currentTotal) * 100, 2)
                : 0.0;
            $prevRate = $prevTotal > 0
                ? round(((int)($previous[$rateNumeratorKey] ?? 0) / $prevTotal) * 100, 2)
                : 0.0;
            $ratePct = reports_calc_percent_change($currentRate, $prevRate);
            $rateDirection = 'flat';
            if ($ratePct > 0) {
                $rateDirection = 'up';
            } elseif ($ratePct < 0) {
                $rateDirection = 'down';
            }
            $out['completion_rate'] = [
                'percent' => $ratePct,
                'direction' => $rateDirection,
            ];
        }

        return $out;
    }
}

if (!function_exists('reports_render_toolbar_dropdown')) {
    function reports_render_toolbar_dropdown(array $args): void
    {
        $inputId = (string)$args['inputId'];
        $menuId = (string)$args['menuId'];
        $btnTextId = (string)$args['btnTextId'];
        $fieldName = (string)$args['fieldName'];
        $fieldLabel = (string)$args['fieldLabel'];
        $options = $args['options'] ?? [];
        $selectedValue = (string)($args['selectedValue'] ?? '');
        $selectedLabel = (string)($args['selectedLabel'] ?? '');
        $wrapperClass = (string)($args['wrapperClass'] ?? 'task-reports-filter-dropdown');
        $includeInQuery = !array_key_exists('includeInQuery', $args) || !empty($args['includeInQuery']);
        $searchable = array_key_exists('searchable', $args)
            ? !empty($args['searchable'])
            : in_array($fieldName, ['user_id', 'client_id', 'project_id'], true);
        $searchPlaceholder = (string)($args['searchPlaceholder'] ?? 'Search...');

        if ($selectedLabel === '') {
            foreach ($options as $opt) {
                if ((string)($opt['value'] ?? '') === $selectedValue) {
                    $selectedLabel = (string)$opt['label'];
                    break;
                }
            }
        }

        $count = count($options);
        ?>
        <div class="toolbar-dropdown-wrapper floating-filter-field <?php echo htmlspecialchars($wrapperClass, ENT_QUOTES, 'UTF-8'); ?>">
            <label class="floating-label"><?php echo htmlspecialchars($fieldLabel, ENT_QUOTES, 'UTF-8'); ?></label>
            <button type="button" class="border-btn-a task-reports-toolbar-toggle" data-reports-dropdown="<?php echo htmlspecialchars($menuId, ENT_QUOTES, 'UTF-8'); ?>" aria-expanded="false">
                <span class="task-reports-filter-selected" id="<?php echo htmlspecialchars($btnTextId, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($selectedLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php echo ts_icon('chevron-down', 'icon-caret-down'); ?>
            </button>
            <div id="<?php echo htmlspecialchars($menuId, ENT_QUOTES, 'UTF-8'); ?>" class="task-reports-toolbar-menu<?php echo $searchable ? ' task-reports-toolbar-menu--searchable' : ''; ?>">
                <?php if ($searchable) : ?>
                <div class="task-reports-toolbar-menu-search-wrap">
                    <input type="search" class="task-reports-toolbar-menu-search" placeholder="<?php echo htmlspecialchars($searchPlaceholder, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" aria-label="<?php echo htmlspecialchars($searchPlaceholder, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <?php endif; ?>
                <div class="task-reports-toolbar-menu-list">
                <?php foreach ($options as $i => $opt) :
                    $val = (string)($opt['value'] ?? '');
                    $lab = (string)$opt['label'];
                    $isFirst = $i === 0;
                    $isLast = $i === $count - 1;
                    $btnClass = trim(($isFirst ? 'first ' : '') . ($isLast ? 'last ' : '') . ($val === $selectedValue ? 'active' : ''));
                    ?>
                <button type="button" class="<?php echo htmlspecialchars($btnClass, ENT_QUOTES, 'UTF-8'); ?>" data-filter-field="<?php echo htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8'); ?>" data-filter-value="<?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?>">
                    <span><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></span>
                </button>
                <?php endforeach; ?>
                </div>
            </div>
            <input type="hidden"<?php if ($includeInQuery) : ?> name="<?php echo htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?> id="<?php echo htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($selectedValue, ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <?php
    }
}

if (!function_exists('reports_currency_symbol')) {
    function reports_currency_symbol(string $currencyString): string
    {
        if ($currencyString === '' || $currencyString === 'all') {
            return '$';
        }
        if (strlen($currencyString) <= 3) {
            return $currencyString;
        }
        $parts = explode(',', $currencyString);
        if (count($parts) > 1) {
            $symbol = trim($parts[1]);
            return str_replace([':', ' '], '', $symbol);
        }
        return '$';
    }
}

if (!function_exists('reports_format_money')) {
    function reports_format_money(float $amount, string $symbol = '$'): string
    {
        return $symbol . number_format($amount, 2, '.', ',');
    }
}

if (!function_exists('reports_invoice_item_subtotal_cache')) {
    /**
     * Request-scoped cache: milestone_id => float subtotal, or null when no line items (use budget).
     *
     * @return array<int,float|null>
     */
    function &reports_invoice_item_subtotal_cache(): array
    {
        if (!isset($GLOBALS['reports_invoice_item_subtotal_cache'])) {
            $GLOBALS['reports_invoice_item_subtotal_cache'] = [];
        }

        return $GLOBALS['reports_invoice_item_subtotal_cache'];
    }
}

if (!function_exists('reports_invoice_item_subtotal_cache_reset')) {
    function reports_invoice_item_subtotal_cache_reset(): void
    {
        $GLOBALS['reports_invoice_item_subtotal_cache'] = [];
    }
}

if (!function_exists('reports_preload_invoice_item_subtotals')) {
    /**
     * Batch-load invoice line-item subtotals (avoids one query per milestone in reports).
     *
     * @param array<int,int> $milestoneIds
     */
    function reports_preload_invoice_item_subtotals(mysqli $connect, array $milestoneIds): void
    {
        $cache = &reports_invoice_item_subtotal_cache();
        $need = [];
        foreach ($milestoneIds as $id) {
            $id = (int)$id;
            if ($id > 0 && !array_key_exists($id, $cache)) {
                $need[$id] = true;
            }
        }
        $need = array_keys($need);
        if ($need === []) {
            return;
        }

        foreach (array_chunk($need, 500) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $sql = "SELECT milestone_id, COALESCE(SUM(rate * quantity), 0) AS subtotal
                FROM invoice_items
                WHERE milestone_id IN ({$placeholders})
                GROUP BY milestone_id";
            $types = str_repeat('i', count($chunk));
            $res = reports_bind_and_execute($connect, $sql, $types, $chunk);
            $found = [];
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $mid = (int)($row['milestone_id'] ?? 0);
                    if ($mid > 0) {
                        $cache[$mid] = (float)($row['subtotal'] ?? 0);
                        $found[$mid] = true;
                    }
                }
            }
            foreach ($chunk as $id) {
                if (!isset($found[$id])) {
                    $cache[$id] = null;
                }
            }
        }
    }
}

if (!function_exists('reports_users_by_ids')) {
    /**
     * @param array<int,int> $userIds
     * @return array<int,object> id => user row
     */
    function reports_users_by_ids(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static function ($id) {
            return $id > 0;
        })));
        if ($userIds === [] || !class_exists('User')) {
            return [];
        }

        $map = [];
        foreach (array_chunk($userIds, 500) as $chunk) {
            $in = implode(',', $chunk);
            $rows = User::findBySql('SELECT id, firstName FROM users WHERE id IN (' . $in . ')');
            if (!$rows) {
                continue;
            }
            foreach ($rows as $user) {
                $uid = (int)($user->id ?? 0);
                if ($uid > 0) {
                    $map[$uid] = $user;
                }
            }
        }

        return $map;
    }
}

if (!function_exists('reports_invoice_total')) {
    /**
     * Invoice total from line items (or budget) with tax and discount.
     *
     * @param object $milestone Row with id, budget, discount, sales_tax, etc.
     */
    function reports_invoice_total($milestone): float
    {
        if (!is_object($milestone)) {
            return 0.0;
        }

        // Load explicitly — spl_autoload maps InvoiceItem → invoiceitem.php (wrong path).
        if (!class_exists('InvoiceItem', false)) {
            require_once __DIR__ . '/invoice_item.php';
        }

        $subtotal = 0.0;
        $milestoneId = (int)($milestone->id ?? 0);
        if ($milestoneId > 0) {
            $cache = reports_invoice_item_subtotal_cache();
            if (array_key_exists($milestoneId, $cache)) {
                $subtotal = $cache[$milestoneId] === null
                    ? (float)($milestone->budget ?? 0)
                    : (float)$cache[$milestoneId];
            } else {
                $invoiceItems = InvoiceItem::findByMilestoneId($milestoneId);
                if ($invoiceItems && count($invoiceItems) > 0) {
                    foreach ($invoiceItems as $item) {
                        $subtotal += (float)$item->rate * (float)$item->quantity;
                    }
                } else {
                    $subtotal = (float)($milestone->budget ?? 0);
                }
            }
        }

        $discount = isset($milestone->discount) ? (float)$milestone->discount : 0.0;
        $discountType = isset($milestone->discount_type) ? (string)$milestone->discount_type : 'percentage';
        $discountAmount = 0.0;
        if ($discount > 0 && $subtotal > 0) {
            if ($discountType === 'percentage') {
                $discountAmount = ($subtotal * $discount) / 100;
            } else {
                $discountAmount = $discount;
            }
        }

        $amountAfterDiscount = $subtotal - $discountAmount;
        $salesTax = isset($milestone->sales_tax) ? (float)$milestone->sales_tax : 0.0;
        $salesTaxType = isset($milestone->sales_tax_type) ? (string)$milestone->sales_tax_type : 'percentage';
        $taxAmount = 0.0;
        if ($salesTax > 0 && $amountAfterDiscount > 0) {
            if ($salesTaxType === 'percentage') {
                $taxAmount = ($amountAfterDiscount * $salesTax) / 100;
            } else {
                $taxAmount = $salesTax;
            }
        }

        return $amountAfterDiscount + $taxAmount;
    }
}

if (!function_exists('reports_invoice_tax_amount')) {
    function reports_invoice_tax_amount($milestone): float
    {
        if (!is_object($milestone)) {
            return 0.0;
        }
        if (!class_exists('InvoiceItem', false)) {
            require_once __DIR__ . '/invoice_item.php';
        }
        $subtotal = 0.0;
        $milestoneId = (int)($milestone->id ?? 0);
        if ($milestoneId > 0) {
            $cache = reports_invoice_item_subtotal_cache();
            if (array_key_exists($milestoneId, $cache)) {
                $subtotal = $cache[$milestoneId] === null
                    ? (float)($milestone->budget ?? 0)
                    : (float)$cache[$milestoneId];
            } else {
                $invoiceItems = InvoiceItem::findByMilestoneId($milestoneId);
                if ($invoiceItems && count($invoiceItems) > 0) {
                    foreach ($invoiceItems as $item) {
                        $subtotal += (float)$item->rate * (float)$item->quantity;
                    }
                } else {
                    $subtotal = (float)($milestone->budget ?? 0);
                }
            }
        }
        $discount = isset($milestone->discount) ? (float)$milestone->discount : 0.0;
        $discountType = isset($milestone->discount_type) ? (string)$milestone->discount_type : 'percentage';
        $discountAmount = 0.0;
        if ($discount > 0 && $subtotal > 0) {
            $discountAmount = ($discountType === 'percentage') ? (($subtotal * $discount) / 100) : $discount;
        }
        $amountAfterDiscount = $subtotal - $discountAmount;
        $salesTax = isset($milestone->sales_tax) ? (float)$milestone->sales_tax : 0.0;
        $salesTaxType = isset($milestone->sales_tax_type) ? (string)$milestone->sales_tax_type : 'percentage';
        if ($salesTax > 0 && $amountAfterDiscount > 0) {
            return ($salesTaxType === 'percentage')
                ? (($amountAfterDiscount * $salesTax) / 100)
                : $salesTax;
        }
        return 0.0;
    }
}

if (!function_exists('reports_module_enabled')) {
    /**
     * Admin Reports dashboard module gate (settings.module_reports).
     * Returns true when column is missing (pre-migration) or enabled.
     */
    function reports_module_enabled(): bool
    {
        if (!class_exists('Settings')) {
            require_once __DIR__ . '/settings.php';
        }
        $settings = Settings::findById(1);
        if (!$settings) {
            return false;
        }
        if (!isset($settings->module_reports)) {
            return true;
        }
        return (int)$settings->module_reports === 1;
    }
}
