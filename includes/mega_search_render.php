<?php
/**
 * Mega search HTML renderers — reuse list-page table/card classes.
 */

if (!function_exists('mega_search_lang')) {
    function mega_search_lang(array $ctx, string $key, string $fallback = ''): string
    {
        $lang = $ctx['lang'] ?? [];
        return isset($lang[$key]) ? (string) $lang[$key] : ($fallback !== '' ? $fallback : $key);
    }
}

if (!function_exists('mega_search_render_empty_icon_svg')) {
    function mega_search_render_empty_icon_svg(string $variant): string
    {
        if ($variant === 'prompt') {
            return '' . ts_icon('search') . '';
        }
        return '<svg class="mega-search-empty__svg mega-search-empty__svg--sad" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" aria-hidden="true">'
            . '<circle class="mega-search-empty__lens" cx="27" cy="27" r="17"/>'
            . '<circle cx="27" cy="27" r="17" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round"/>'
            . '<path d="M39.5 39.5 52 52" stroke="currentColor" stroke-width="3.2" stroke-linecap="round"/>'
            . '<path d="M18.5 21.5 22.5 25.5M22.5 21.5 18.5 25.5" stroke="currentColor" stroke-width="2.8" stroke-linecap="round"/>'
            . '<path d="M31.5 21.5 35.5 25.5M35.5 21.5 31.5 25.5" stroke="currentColor" stroke-width="2.8" stroke-linecap="round"/>'
            . '<path d="M19 33.5c2.2 3.2 5.4 4.8 8 4.8s5.8-1.6 8-4.8" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round"/>'
            . '</svg>';
    }
}

if (!function_exists('mega_search_render_empty_block')) {
    function mega_search_render_empty_block(string $variant, string $title, array $ctx, string $hint = ''): string
    {
        $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $hintEsc = $hint !== '' ? htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') : '';
        $icon = mega_search_render_empty_icon_svg($variant);
        $hintHtml = $hintEsc !== ''
            ? '<p class="mega-search-empty__hint">' . $hintEsc . '</p>'
            : '';

        return '<div class="mega-search-empty mega-search-empty--' . htmlspecialchars($variant, ENT_QUOTES, 'UTF-8') . '" role="status">'
            . '<div class="mega-search-empty__icon">' . $icon . '</div>'
            . '<p class="mega-search-empty__title">' . $titleEsc . '</p>'
            . $hintHtml
            . '</div>';
    }
}

if (!function_exists('mega_search_render_empty')) {
    function mega_search_render_empty(string $tab, string $q, array $ctx): string
    {
        if (trim($q) === '') {
            $title = mega_search_lang($ctx, 'Type to search across your workspace.', 'Type to search across your workspace.');
            return mega_search_render_empty_block('prompt', $title, $ctx);
        }
        $title = mega_search_lang($ctx, 'No results found.', 'No results found.');
        $hint = mega_search_lang($ctx, 'Mega search no results hint', 'Try another tab or adjust your search terms.');
        return mega_search_render_empty_block('no-results', $title, $ctx, $hint);
    }
}

if (!function_exists('mega_search_render_tab')) {
    function mega_search_render_tab(string $tab, array $rows, array $ctx): string
    {
        if ($rows === []) {
            return mega_search_render_empty($tab, 'x', $ctx);
        }

        $partial = dirname(__DIR__) . '/templates/partials/mega_search/' . $tab . '_rows.php';
        if (!is_file($partial)) {
            return mega_search_render_empty($tab, 'x', $ctx);
        }

        ob_start();
        $megaSearchRows = $rows;
        $megaSearchCtx = $ctx;
        include $partial;
        $html = ob_get_clean();
        if ($html !== '') {
            $html = preg_replace('/\xEF\xBB\xBF/u', '', $html);
            $html = preg_replace('/\x{FEFF}/u', '', $html);
        }
        return $html;
    }
}

if (!function_exists('mega_search_render_rows_fragment')) {
    /**
     * Table body rows only (for load-more append).
     */
    function mega_search_render_rows_fragment(string $tab, array $rows, array $ctx, int $rowNoStart = 1): string
    {
        if ($rows === []) {
            return '';
        }

        $partial = dirname(__DIR__) . '/templates/partials/mega_search/' . $tab . '_rows.php';
        if (!is_file($partial)) {
            return '';
        }

        global $connect, $database, $lang, $url, $db;
        $megaSearchRows = $rows;
        $megaSearchCtx = $ctx;
        $megaSearchOutputMode = 'rows';
        $megaSearchRowNoStart = max(1, $rowNoStart);

        ob_start();
        include $partial;
        $html = ob_get_clean();
        if ($html !== '') {
            $html = preg_replace('/\xEF\xBB\xBF/u', '', $html);
            $html = preg_replace('/\x{FEFF}/u', '', $html);
        }
        return $html;
    }
}

if (!function_exists('mega_search_render_load_more_html')) {
    function mega_search_render_load_more_html(string $tab, string $q, array $ctx, int $total, int $loaded): string
    {
        $total = max(0, $total);
        $loaded = max(0, $loaded);
        $remaining = $total - $loaded;
        if ($remaining <= 0) {
            return '';
        }

        $label = mega_search_lang($ctx, 'Load more', 'Load more');
        $tabEsc = htmlspecialchars($tab, ENT_QUOTES, 'UTF-8');
        $qEsc = htmlspecialchars($q, ENT_QUOTES, 'UTF-8');
        $tbodySel = htmlspecialchars(mega_search_tbody_selector($tab), ENT_QUOTES, 'UTF-8');

        return '<div class="load-more-container mega-search-load-more-wrap px-3 pb-3" data-mega-search-load-more="1"'
            . ' data-tab="' . $tabEsc . '" data-q="' . $qEsc . '" data-offset="' . (int) $loaded . '" data-total="' . (int) $total . '"'
            . ' data-tbody-selector="' . $tbodySel . '">'
            . '<button type="button" class="btn primary-btn mega-search-load-more-btn">'
            . '<span class="load-more-text">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>'
            . ' <span class="load-more-count">(' . (int) $remaining . ')</span>'
            . '</button></div>';
    }
}

if (!function_exists('mega_search_tbody_selector')) {
    function mega_search_tbody_selector(string $tab): string
    {
        switch ($tab) {
            case 'people':
                return '.mega-search-people-table-wrap tbody#projects-tbl';
            case 'companies':
                return '.mega-search-companies-table-wrap tbody#projects-tbl';
            case 'projects':
                return '[data-ts-list-bulk-root="projects"] tbody#projects-tbl';
            case 'tasks':
                return '#tasks-table tbody#projects-tbl';
            case 'invoices':
                return '.mega-search-invoices-table-wrap tbody#projects-tbl';
            case 'leads':
                return 'tbody#leads-tbl';
            default:
                return '';
        }
    }
}

if (!function_exists('mega_search_col_no_label')) {
    function mega_search_col_no_label(array $ctx): string
    {
        return mega_search_lang($ctx, 'No.', 'No.');
    }
}

if (!function_exists('mega_search_invoice_module_enabled')) {
    function mega_search_invoice_module_enabled(array $ctx): bool
    {
        $settings = $ctx['settings'] ?? null;

        return $settings && !empty($settings->module_invoices);
    }
}

if (!function_exists('mega_search_people_last_login_map')) {
    function mega_search_people_last_login_map(mysqli $connect, array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if ($userIds === []) {
            return [];
        }

        $map = [];
        $idList = implode(',', $userIds);
        $loginRes = mysqli_query(
            $connect,
            "SELECT user_id, MAX(attempt_time) AS last_login
            FROM login_attempts
            WHERE success = 1 AND type = 'login' AND user_id IN ($idList)
            GROUP BY user_id"
        );
        if ($loginRes) {
            while ($lr = mysqli_fetch_assoc($loginRes)) {
                $uid = (int) ($lr['user_id'] ?? 0);
                if ($uid > 0 && !empty($lr['last_login'])) {
                    $map[$uid] = (string) $lr['last_login'];
                }
            }
        }

        return $map;
    }
}

if (!function_exists('mega_search_format_last_login')) {
    function mega_search_format_last_login(?string $lastLoginRaw, array $ctx): string
    {
        if ($lastLoginRaw === null || trim($lastLoginRaw) === '') {
            return '-';
        }

        $settings = $ctx['settings'] ?? null;
        $settingsTz = ($settings && !empty($settings->time_zone)) ? (string) $settings->time_zone : null;
        global $time_zone;
        $targetTz = $settingsTz ?: (isset($time_zone) && !empty($time_zone) ? $time_zone : date_default_timezone_get());
        $serverTz = ini_get('date.timezone') ?: 'UTC';

        try {
            $dt = new DateTime($lastLoginRaw, new DateTimeZone($serverTz));
            $dt->setTimezone(new DateTimeZone($targetTz));
            $dt->add(new DateInterval('PT3H'));

            return $dt->format('M j, Y \\a\\t g:i A');
        } catch (Exception $e) {
            return date('M j, Y \\a\\t g:i A', strtotime($lastLoginRaw));
        }
    }
}

if (!function_exists('mega_search_all_tasks_status_config')) {
    /**
     * @return array{labels:array<string,string>,colors:array<string,string>}
     */
    function mega_search_all_tasks_status_config($database = null, array $lang = []): array
    {
        $statusLabels = [
            'todo' => $lang['To Do'] ?? 'To Do',
            'inprogress' => $lang['In Progress'] ?? 'In Progress',
            'review' => $lang['In Review'] ?? 'In Review',
            'done' => $lang['Completed'] ?? 'Completed',
        ];
        $statusColors = [
            'todo' => 'todo todo-bg-op',
            'inprogress' => 'inprogress inprogress-bg-op',
            'review' => 'review review-bg-op',
            'done' => 'done review done-bg-op ',
        ];

        if ($database) {
            $columnNamesResult = $database->query('SELECT column_key, custom_name FROM project_columns WHERE project_id = 0');
            if ($columnNamesResult && $database->numRows($columnNamesResult) > 0) {
                while ($columnRow = $database->fetchArray($columnNamesResult)) {
                    $key = (string) ($columnRow['column_key'] ?? '');
                    if ($key !== '' && isset($statusLabels[$key])) {
                        $statusLabels[$key] = (string) $columnRow['custom_name'];
                    }
                }
            }
        }

        return ['labels' => $statusLabels, 'colors' => $statusColors];
    }
}
