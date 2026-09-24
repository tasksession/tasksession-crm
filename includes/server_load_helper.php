<?php
/**
 * Server Health Monitor helpers.
 *
 * Slow/heavy request logging is registered via server_load_register_slow_request_logger()
 * from lib-initialize.php. Only requests >= 500ms or HTTP 5xx are stored (not all traffic).
 */

if (!function_exists('server_load_t')) {
    function server_load_t(string $key, string $fallback = ''): string
    {
        global $lang;
        if (isset($lang[$key]) && $lang[$key] !== '') {
            return (string)$lang[$key];
        }
        return $fallback !== '' ? $fallback : $key;
    }
}

if (!function_exists('server_load_app_root')) {
    function server_load_app_root(): string
    {
        return dirname(__DIR__);
    }
}

if (!function_exists('server_load_safe_read_file')) {
    function server_load_safe_read_file(string $path): ?string
    {
        if (!is_readable($path)) {
            return null;
        }
        $content = @file_get_contents($path);
        return ($content !== false && $content !== '') ? $content : null;
    }
}

if (!function_exists('server_load_format_bytes')) {
    function server_load_format_bytes($bytes, int $precision = 2): string
    {
        $bytes = (float)$bytes;
        if ($bytes < 0 || !is_finite($bytes)) {
            return server_load_t('Not Available');
        }
        if ($bytes < 1024) {
            return round($bytes) . ' B';
        }
        $units = ['KB', 'MB', 'GB', 'TB', 'PB'];
        // floor(log1024) = exponent; unit index is exponent - 1 (1024^2 bytes => MB, not GB).
        $pow = (int)floor(log($bytes, 1024));
        $pow = max(1, min($pow, count($units)));
        $value = $bytes / pow(1024, $pow);
        return round($value, $precision) . ' ' . $units[$pow - 1];
    }
}

if (!function_exists('server_load_format_uptime')) {
    function server_load_format_uptime(float $seconds): string
    {
        if ($seconds <= 0) {
            return server_load_t('Not Available');
        }
        $days = (int)floor($seconds / 86400);
        $hours = (int)floor(fmod($seconds, 86400) / 3600);
        $mins = (int)floor(fmod($seconds, 3600) / 60);
        $parts = [];
        if ($days > 0) {
            $parts[] = $days . ' ' . server_load_t($days === 1 ? 'server_load_day' : 'server_load_days');
        }
        if ($hours > 0) {
            $parts[] = $hours . 'h';
        }
        if (empty($parts) && $mins > 0) {
            $parts[] = $mins . 'm';
        }
        if (empty($parts)) {
            return round($seconds) . 's';
        }
        return implode(' ', $parts);
    }
}

if (!function_exists('server_load_parse_ini_bytes')) {
    function server_load_parse_ini_bytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return -1;
        }
        if (!preg_match('/^(-?\d+(?:\.\d+)?)([kKmMgG]?)$/', $value, $m)) {
            return (int)$value;
        }
        $num = (float)$m[1];
        $suffix = strtolower($m[2] ?? '');
        switch ($suffix) {
            case 'g':
                return (int)round($num * 1073741824);
            case 'm':
                return (int)round($num * 1048576);
            case 'k':
                return (int)round($num * 1024);
            default:
                return (int)round($num);
        }
    }
}

if (!function_exists('server_load_format_ini_bytes')) {
    function server_load_format_ini_bytes(string $iniValue): string
    {
        $bytes = server_load_parse_ini_bytes($iniValue);
        if ($bytes < 0) {
            return server_load_t('server_load_unlimited', 'Unlimited');
        }
        return server_load_format_bytes($bytes);
    }
}

if (!function_exists('server_load_format_ini_time')) {
    function server_load_format_ini_time(string $iniValue): string
    {
        $iniValue = trim($iniValue);
        if ($iniValue === '' || $iniValue === '0' || $iniValue === '-1') {
            return server_load_t('server_load_unlimited', 'Unlimited');
        }
        $sec = (int)$iniValue;
        if ($sec >= 3600 && $sec % 3600 === 0) {
            return (int)($sec / 3600) . 'h';
        }
        if ($sec >= 60 && $sec % 60 === 0) {
            return (int)($sec / 60) . 'm';
        }
        return $sec . 's';
    }
}

if (!function_exists('server_load_parse_ini_seconds')) {
    function server_load_parse_ini_seconds(string $iniValue): int
    {
        $iniValue = trim($iniValue);
        if ($iniValue === '' || $iniValue === '0' || $iniValue === '-1') {
            return -1;
        }
        return max(0, (int)$iniValue);
    }
}

if (!function_exists('server_load_php_recommended_limits')) {
    /** @return array<string, int> bytes or seconds; -1 = not applicable */
    function server_load_php_recommended_limits(): array
    {
        return [
            'memory_limit_bytes' => 1024 * 1048576,
            'post_max_size_bytes' => 1024 * 1048576,
            'upload_max_filesize_bytes' => 1024 * 1048576,
            'max_execution_time_sec' => 1500,
            'max_input_time_sec' => 1500,
            'max_input_vars' => 10000,
        ];
    }
}

if (!function_exists('server_load_ini_get_all_values')) {
    /** @return array{global:?string,local:?string} */
    function server_load_ini_get_all_values(string $key): array
    {
        // details MUST be true — false returns flat strings without global_value/local_value.
        $all = @ini_get_all(null, true);
        if (!is_array($all) || !isset($all[$key])) {
            return ['global' => null, 'local' => null];
        }

        $entry = $all[$key];
        if (!is_array($entry)) {
            $local = trim((string)$entry);
            return [
                'global' => null,
                'local' => $local !== '' ? $local : null,
            ];
        }

        $global = isset($entry['global_value']) ? trim((string)$entry['global_value']) : '';
        $local = isset($entry['local_value']) ? trim((string)$entry['local_value']) : '';
        return [
            'global' => $global !== '' ? $global : null,
            'local' => $local !== '' ? $local : null,
        ];
    }
}

if (!function_exists('server_load_ini_get_global')) {
    /** System-wide php.ini (PHP_INI_SYSTEM); may differ from hosting panel domain settings. */
    function server_load_ini_get_global(string $key): ?string
    {
        if (!function_exists('get_cfg_var')) {
            return null;
        }
        $val = @get_cfg_var($key);
        if ($val === false) {
            return null;
        }
        return (string)$val;
    }
}

if (!function_exists('server_load_get_hosting_php_directives')) {
    /** Directives from php.ini loaded for this site (hosting panel / domain PHP config). */
    function server_load_get_hosting_php_directives(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $directives = [];
        $loaded = php_ini_loaded_file();
        if (is_string($loaded) && $loaded !== '') {
            $directives = array_merge($directives, server_load_parse_php_directives_from_file($loaded));
        }

        $scanned = php_ini_scanned_files();
        if (is_string($scanned) && $scanned !== '') {
            foreach (explode(',', $scanned) as $file) {
                $file = trim($file);
                if ($file !== '') {
                    $directives = array_merge($directives, server_load_parse_php_directives_from_file($file));
                }
            }
        }

        $cache = $directives;
        return $cache;
    }
}

if (!function_exists('server_load_ini_get_server_value')) {
    /**
     * Hosting-panel value before project .user.ini / .htaccess overrides.
     * Priority: ini_get_all global_value → parsed domain php.ini → get_cfg_var → ini_get.
     */
    function server_load_ini_get_server_value(string $key, bool $hasProjectOverride): ?string
    {
        $active = (string)ini_get($key);
        $iniAll = server_load_ini_get_all_values($key);
        if ($iniAll['global'] !== null) {
            return $iniAll['global'];
        }

        if (!$hasProjectOverride) {
            return $active;
        }

        $hosting = server_load_get_hosting_php_directives();
        if (isset($hosting[$key])) {
            return (string)$hosting[$key];
        }

        return server_load_ini_get_global($key);
    }
}

if (!function_exists('server_load_format_php_setting_display')) {
    function server_load_format_php_setting_display(string $key, string $raw): string
    {
        switch ($key) {
            case 'php_version':
                return $raw;
            case 'memory_limit':
            case 'post_max_size':
            case 'upload_max_filesize':
                return server_load_format_ini_bytes($raw);
            case 'max_execution_time':
            case 'max_input_time':
                return server_load_format_ini_time($raw);
            case 'max_input_vars':
                return number_format((int)$raw);
            default:
                return $raw;
        }
    }
}

if (!function_exists('server_load_parse_php_directives_from_file')) {
    /** @return array<string, string> */
    function server_load_parse_php_directives_from_file(string $path): array
    {
        $content = server_load_safe_read_file($path);
        if ($content === null) {
            return [];
        }

        $keys = 'upload_max_filesize|post_max_size|max_execution_time|max_input_time|memory_limit|max_file_uploads|max_input_vars';
        $directives = [];

        if (basename($path) === '.htaccess') {
            if (preg_match_all('/php_value\s+(' . $keys . ')\s+(\S+)/i', $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $directives[strtolower($match[1])] = $match[2];
                }
            }
            return $directives;
        }

        foreach (preg_split('/\r?\n/', $content) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === ';' || $line[0] === '#') {
                continue;
            }
            if (preg_match('/^(' . $keys . ')\s*=\s*(\S+)/i', $line, $match)) {
                $directives[strtolower($match[1])] = $match[2];
            }
        }

        return $directives;
    }
}

if (!function_exists('server_load_get_project_php_override_sources')) {
    /** @return array<string, list<string>> */
    function server_load_get_project_php_override_sources(): array
    {
        $root = server_load_app_root();
        $map = [];
        foreach (['.user.ini', '.htaccess', 'admin/.htaccess', 'php.ini'] as $rel) {
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            $parsed = server_load_parse_php_directives_from_file($path);
            foreach ($parsed as $key => $val) {
                if (!isset($map[$key])) {
                    $map[$key] = [];
                }
                if (!in_array($rel, $map[$key], true)) {
                    $map[$key][] = $rel;
                }
            }
        }
        return $map;
    }
}

if (!function_exists('server_load_php_ini_values_equivalent')) {
    function server_load_php_ini_values_equivalent(string $key, string $left, string $right): bool
    {
        if ($left === $right) {
            return true;
        }

        switch ($key) {
            case 'memory_limit':
            case 'post_max_size':
            case 'upload_max_filesize':
                return server_load_parse_ini_bytes($left) === server_load_parse_ini_bytes($right);
            case 'max_execution_time':
            case 'max_input_time':
                return server_load_parse_ini_seconds($left) === server_load_parse_ini_seconds($right);
            case 'max_input_vars':
                return (int)$left === (int)$right;
            default:
                return false;
        }
    }
}

if (!function_exists('server_load_evaluate_php_setting')) {
    /**
     * Task Session recommended PHP limits (installer + CRM workloads).
     *
     * @return array{status:string,label:string,badge_class:string,recommended:string}
     */
    function server_load_evaluate_php_setting(string $key, ?string $rawIniValue = null): array
    {
        $limits = server_load_php_recommended_limits();
        $goodLabel = server_load_t('server_load_php_status_good', 'Good');
        $upgradeLabel = server_load_t('server_load_php_status_upgrade', 'Upgrade');

        $make = static function (bool $ok, string $recommended) use ($goodLabel, $upgradeLabel): array {
            return [
                'status' => $ok ? 'good' : 'upgrade',
                'label' => $ok ? $goodLabel : $upgradeLabel,
                'badge_class' => $ok ? 'server-load-php-badge server-load-php-badge-good' : 'server-load-php-badge server-load-php-badge-upgrade',
                'recommended' => $recommended,
            ];
        };

        switch ($key) {
            case 'php_version':
                return $make(
                    version_compare(PHP_VERSION, '8.0.0', '>='),
                    server_load_t('server_load_php_rec_version', 'PHP 8.2+')
                );
            case 'memory_limit':
                $bytes = server_load_parse_ini_bytes($rawIniValue ?? (string)ini_get('memory_limit'));
                $min = (int)$limits['memory_limit_bytes'];
                return $make(
                    $bytes < 0 || $bytes >= $min,
                    server_load_t('server_load_php_rec_memory', '1024M')
                );
            case 'max_execution_time':
                $sec = server_load_parse_ini_seconds($rawIniValue ?? (string)ini_get('max_execution_time'));
                $min = (int)$limits['max_execution_time_sec'];
                return $make(
                    $sec < 0 || $sec >= $min,
                    server_load_t('server_load_php_rec_max_execution', '1500s')
                );
            case 'max_input_time':
                $sec = server_load_parse_ini_seconds($rawIniValue ?? (string)ini_get('max_input_time'));
                $min = (int)$limits['max_input_time_sec'];
                return $make(
                    $sec < 0 || $sec >= $min,
                    server_load_t('server_load_php_rec_max_input_time', '1500s')
                );
            case 'max_input_vars':
                $vars = (int)($rawIniValue ?? ini_get('max_input_vars'));
                $min = (int)$limits['max_input_vars'];
                return $make(
                    $vars >= $min,
                    server_load_t('server_load_php_rec_max_input_vars', '10,000+')
                );
            case 'post_max_size':
                $bytes = server_load_parse_ini_bytes($rawIniValue ?? (string)ini_get('post_max_size'));
                $min = (int)$limits['post_max_size_bytes'];
                return $make(
                    $bytes >= $min,
                    server_load_t('server_load_php_rec_post_max', '1024M')
                );
            case 'upload_max_filesize':
                $upload = server_load_parse_ini_bytes($rawIniValue ?? (string)ini_get('upload_max_filesize'));
                if ($rawIniValue !== null) {
                    $postRaw = server_load_ini_get_global('post_max_size') ?? (string)ini_get('post_max_size');
                } else {
                    $postRaw = (string)ini_get('post_max_size');
                }
                $post = server_load_parse_ini_bytes($postRaw);
                $min = (int)$limits['upload_max_filesize_bytes'];
                $ok = $upload >= $min && ($post < 0 || $upload <= $post);
                return $make(
                    $ok,
                    server_load_t('server_load_php_rec_upload_max', '1024M (not larger than post_max_size)')
                );
            default:
                return $make(true, '');
        }
    }
}

if (!function_exists('server_load_get_php_settings')) {
    function server_load_get_php_settings(): array
    {
        $overrideSources = server_load_get_project_php_override_sources();
        $hasHostingValues = false;
        $hasRuntimeOverrides = false;

        $definitions = [
            [
                'key' => 'php_version',
                'ini_key' => null,
                'label' => server_load_t('server_load_php_version', 'PHP Version'),
                'hint' => '',
            ],
            [
                'key' => 'memory_limit',
                'ini_key' => 'memory_limit',
                'label' => server_load_t('server_load_memory_limit', 'memory_limit'),
                'hint' => server_load_t('server_load_memory_limit_hint', 'Sets the maximum amount of memory that a script is allowed to allocate.'),
            ],
            [
                'key' => 'max_execution_time',
                'ini_key' => 'max_execution_time',
                'label' => server_load_t('server_load_max_execution_time', 'max_execution_time'),
                'hint' => server_load_t('server_load_max_execution_time_hint', 'Sets the maximum time a script is allowed to run before it is terminated by the parser.'),
            ],
            [
                'key' => 'max_input_time',
                'ini_key' => 'max_input_time',
                'label' => server_load_t('server_load_max_input_time', 'max_input_time'),
                'hint' => server_load_t('server_load_max_input_time_hint', 'Sets the maximum time a script is allowed to parse input data, like POST and GET.'),
            ],
            [
                'key' => 'max_input_vars',
                'ini_key' => 'max_input_vars',
                'label' => server_load_t('server_load_max_input_vars', 'max_input_vars'),
                'hint' => server_load_t('server_load_max_input_vars_hint', 'Sets the limit of the number of inputs for posting forms.'),
            ],
            [
                'key' => 'post_max_size',
                'ini_key' => 'post_max_size',
                'label' => server_load_t('server_load_post_max_size', 'post_max_size'),
                'hint' => server_load_t('server_load_post_max_size_hint', 'Maximum size of POST data that PHP will accept.'),
            ],
            [
                'key' => 'upload_max_filesize',
                'ini_key' => 'upload_max_filesize',
                'label' => server_load_t('server_load_upload_max_filesize', 'upload_max_filesize'),
                'hint' => server_load_t('server_load_upload_max_filesize_hint', 'The maximum size of an uploaded file.'),
            ],
        ];

        $rows = [];
        foreach ($definitions as $def) {
            $key = (string)$def['key'];
            $iniKey = $def['ini_key'];

            if ($iniKey === null) {
                $activeRaw = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
                $globalRaw = null;
                $displayRaw = $activeRaw;
                $isOverridden = false;
                $badgeRaw = null;
            } else {
                $activeRaw = (string)ini_get($iniKey);
                $iniAll = server_load_ini_get_all_values($iniKey);
                $hasProjectOverride = !empty($overrideSources[$iniKey]);
                $serverRaw = server_load_ini_get_server_value($iniKey, $hasProjectOverride);
                $isOverridden = $serverRaw !== null
                    && !server_load_php_ini_values_equivalent($key, $serverRaw, $activeRaw);
                if ($serverRaw !== null) {
                    $hasHostingValues = true;
                }
                if ($isOverridden) {
                    $hasRuntimeOverrides = true;
                }
                $badgeRaw = ($serverRaw !== null) ? $serverRaw : $activeRaw;
            }

            $row = [
                'key' => $key,
                'label' => $def['label'],
                'server_value' => ($iniKey === null)
                    ? server_load_format_php_setting_display($key, $activeRaw)
                    : (($serverRaw ?? null) !== null
                        ? server_load_format_php_setting_display($key, (string)$serverRaw)
                        : server_load_t('Not Available')),
                'override_value' => ($iniKey === null || !$isOverridden)
                    ? server_load_t('server_load_php_no_override', '—')
                    : server_load_format_php_setting_display($key, $activeRaw),
                'hint' => $def['hint'],
                'is_overridden' => $isOverridden,
                'override_sources' => ($iniKey !== null) ? ($overrideSources[$iniKey] ?? []) : [],
            ];

            $eval = server_load_evaluate_php_setting($key, $badgeRaw);
            $row['status'] = $eval['status'];
            $row['badge_label'] = $eval['label'];
            $row['badge_class'] = $eval['badge_class'];
            $row['recommended'] = $eval['recommended'];

            if ($iniKey !== null && $isOverridden) {
                $activeEval = server_load_evaluate_php_setting($key, $activeRaw);
                $row['active_status'] = $activeEval['status'];
                $row['active_badge_label'] = $activeEval['label'];
                $row['active_badge_class'] = $activeEval['badge_class'];
            } else {
                $row['active_status'] = '';
                $row['active_badge_label'] = '';
                $row['active_badge_class'] = '';
            }

            $rows[] = $row;
        }

        $overrideFiles = [];
        foreach ($overrideSources as $files) {
            foreach ($files as $file) {
                $overrideFiles[] = $file;
            }
        }

        return [
            'rows' => $rows,
            'has_hosting_values' => $hasHostingValues,
            'has_runtime_overrides' => $hasRuntimeOverrides,
            'override_files' => array_values(array_unique($overrideFiles)),
        ];
    }
}

if (!function_exists('server_load_render_php_settings_html')) {
    function server_load_render_php_settings_html(): string
    {
        $data = server_load_get_php_settings();
        $settings = $data['rows'] ?? [];
        $title = htmlspecialchars(server_load_t('server_load_php_settings', 'PHP Settings'), ENT_QUOTES, 'UTF-8');
        $statusCol = htmlspecialchars(server_load_t('server_load_php_status_col', 'Status'), ENT_QUOTES, 'UTF-8');
        $settingCol = htmlspecialchars(server_load_t('server_load_php_setting_col', 'Setting'), ENT_QUOTES, 'UTF-8');
        $serverCol = htmlspecialchars(server_load_t('server_load_php_server_value_col', 'Server value'), ENT_QUOTES, 'UTF-8');
        $overrideCol = htmlspecialchars(server_load_t('server_load_php_override_value_col', 'System override value'), ENT_QUOTES, 'UTF-8');

        $noticeHtml = '';
        $overrideFiles = $data['override_files'] ?? [];
        if (!empty($data['has_runtime_overrides']) || !empty($overrideFiles)) {
            $files = !empty($overrideFiles)
                ? implode(', ', array_map(static function (string $file): string {
                    return '<code>' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '</code>';
                }, $overrideFiles))
                : '';
            if (!empty($data['has_runtime_overrides'])) {
                $noticeText = server_load_t(
                    'server_load_php_override_notice',
                    'Hosting panel values may differ from what this application actually uses. Project files such as .user.ini or .htaccess can raise limits at runtime.'
                );
            } else {
                $noticeText = server_load_t(
                    'server_load_php_override_notice_files_only',
                    'This project sets PHP limits in local config files. Your hosting panel may show lower defaults than what the application uses at runtime.'
                );
            }
            $filesText = $files !== ''
                ? ' ' . server_load_t('server_load_php_override_files', 'Overrides found in:') . ' ' . $files
                : '';
            $noticeHtml = '<div class="server-load-php-settings-notice">'
                . htmlspecialchars($noticeText, ENT_QUOTES, 'UTF-8')
                . $filesText
                . '</div>';
        }

        $rows = '';
        foreach ($settings as $row) {
            $label = htmlspecialchars((string)$row['label'], ENT_QUOTES, 'UTF-8');
            $serverValue = htmlspecialchars((string)$row['server_value'], ENT_QUOTES, 'UTF-8');
            $overrideValue = htmlspecialchars((string)$row['override_value'], ENT_QUOTES, 'UTF-8');
            $badgeLabel = htmlspecialchars((string)($row['badge_label'] ?? ''), ENT_QUOTES, 'UTF-8');
            $badgeClass = htmlspecialchars((string)($row['badge_class'] ?? ''), ENT_QUOTES, 'UTF-8');
            $hint = trim((string)($row['hint'] ?? ''));
            $recommended = trim((string)($row['recommended'] ?? ''));
            $hintHtml = $hint !== ''
                ? '<div class="server-load-php-setting-hint">' . htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') . '</div>'
                : '';
            $recHtml = '';
            if (($row['status'] ?? '') === 'upgrade' && $recommended !== '') {
                $recHtml = '<div class="server-load-php-setting-recommended">'
                    . htmlspecialchars(server_load_t('server_load_php_recommended_prefix', 'Recommended:'), ENT_QUOTES, 'UTF-8')
                    . ' ' . htmlspecialchars($recommended, ENT_QUOTES, 'UTF-8')
                    . '</div>';
            }

            $overrideExtraHtml = '';
            if (!empty($row['is_overridden'])) {
                $activeBadgeLabel = htmlspecialchars((string)($row['active_badge_label'] ?? ''), ENT_QUOTES, 'UTF-8');
                $activeBadgeClass = htmlspecialchars((string)($row['active_badge_class'] ?? ''), ENT_QUOTES, 'UTF-8');
                $sources = $row['override_sources'] ?? [];
                $viaHtml = '';
                if (!empty($sources)) {
                    $viaList = implode(', ', array_map(static function (string $file): string {
                        return '<code>' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '</code>';
                    }, $sources));
                    $viaHtml = '<div class="server-load-php-setting-override-via">'
                        . htmlspecialchars(server_load_t('server_load_php_override_via', 'via'), ENT_QUOTES, 'UTF-8')
                        . ' ' . $viaList
                        . '</div>';
                }
                $overrideExtraHtml = ($activeBadgeLabel !== ''
                    ? '<div class="server-load-php-setting-override-badge"><span class="' . $activeBadgeClass . ' server-load-php-badge-active">' . $activeBadgeLabel . '</span></div>'
                    : '') . $viaHtml;
            }

            $rows .= '<tr>'
                . '<td class="server-load-php-setting-status"><span class="' . $badgeClass . '">' . $badgeLabel . '</span></td>'
                . '<th scope="row" class="server-load-php-setting-label">' . $label . $hintHtml . '</th>'
                . '<td class="server-load-php-setting-server"><strong>' . $serverValue . '</strong>' . $recHtml . '</td>'
                . '<td class="server-load-php-setting-override"><strong>' . $overrideValue . '</strong>' . $overrideExtraHtml . '</td>'
                . '</tr>';
        }

        return '<div class="server-load-php-settings widget-card mt-20">'
            . '<h3 class="server-load-php-settings-title">' . $title . '</h3>'
            . $noticeHtml
            . '<div class="table-responsive"><table class="table table-new server-load-php-settings-table mb-0">'
            . '<thead><tr>'
            . '<th class="server-load-php-setting-status">' . $statusCol . '</th>'
            . '<th class="server-load-php-setting-label">' . $settingCol . '</th>'
            . '<th class="server-load-php-setting-server">' . $serverCol . '</th>'
            . '<th class="server-load-php-setting-override">' . $overrideCol . '</th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody></table></div>'
            . '</div>';
    }
}

if (!function_exists('server_load_status_color')) {
    function server_load_status_color(string $level): string
    {
        switch ($level) {
            case 'good':
                return '#4caf50';
            case 'warning':
                return '#f9b233';
            case 'high':
            case 'critical':
                return '#f66';
            default:
                return '#888';
        }
    }
}

if (!function_exists('server_load_cpu_status')) {
    function server_load_cpu_status(?float $pct): array
    {
        if ($pct === null) {
            return ['level' => 'unknown', 'label' => server_load_t('Not Available'), 'color' => '#888'];
        }
        if ($pct <= 60) {
            return ['level' => 'good', 'label' => server_load_t('Good'), 'color' => server_load_status_color('good')];
        }
        if ($pct <= 85) {
            return ['level' => 'warning', 'label' => server_load_t('Warning'), 'color' => server_load_status_color('warning')];
        }
        return ['level' => 'high', 'label' => server_load_t('High'), 'color' => server_load_status_color('high')];
    }
}

if (!function_exists('server_load_ram_status')) {
    function server_load_ram_status(?float $pct): array
    {
        if ($pct === null) {
            return ['level' => 'unknown', 'label' => server_load_t('Not Available'), 'color' => '#888'];
        }
        if ($pct <= 70) {
            return ['level' => 'good', 'label' => server_load_t('Good'), 'color' => server_load_status_color('good')];
        }
        if ($pct <= 90) {
            return ['level' => 'warning', 'label' => server_load_t('Warning'), 'color' => server_load_status_color('warning')];
        }
        return ['level' => 'high', 'label' => server_load_t('High'), 'color' => server_load_status_color('high')];
    }
}

if (!function_exists('server_load_disk_status')) {
    function server_load_disk_status(?float $pct): array
    {
        if ($pct === null) {
            return ['level' => 'unknown', 'label' => server_load_t('Not Available'), 'color' => '#888'];
        }
        if ($pct <= 75) {
            return ['level' => 'good', 'label' => server_load_t('Good'), 'color' => server_load_status_color('good')];
        }
        if ($pct <= 90) {
            return ['level' => 'warning', 'label' => server_load_t('Warning'), 'color' => server_load_status_color('warning')];
        }
        return ['level' => 'critical', 'label' => server_load_t('Critical'), 'color' => server_load_status_color('critical')];
    }
}

if (!function_exists('server_load_card_progress')) {
    /**
     * @return array{pct:float,bar_class:string}|null
     */
    function server_load_card_progress(?float $pct, array $status): ?array
    {
        if ($pct === null) {
            return null;
        }
        $pct = max(0, min(100, round($pct, 1)));
        $level = $status['level'] ?? 'unknown';
        $barClass = 'bg-secondary';
        if ($level === 'good') {
            $barClass = 'bg-success';
        } elseif ($level === 'warning') {
            $barClass = 'bg-warning';
        } elseif (in_array($level, ['high', 'critical'], true)) {
            $barClass = 'bg-danger';
        }
        return ['pct' => $pct, 'bar_class' => $barClass];
    }
}

if (!function_exists('server_load_load_status')) {
    function server_load_load_status(?float $load1, int $cores): array
    {
        if ($load1 === null || $cores <= 0) {
            return ['level' => 'unknown', 'label' => server_load_t('Not Available'), 'color' => '#888'];
        }
        if ($load1 <= $cores) {
            return ['level' => 'good', 'label' => server_load_t('Good'), 'color' => server_load_status_color('good')];
        }
        if ($load1 <= ($cores * 2)) {
            return ['level' => 'warning', 'label' => server_load_t('Warning'), 'color' => server_load_status_color('warning')];
        }
        return ['level' => 'high', 'label' => server_load_t('High'), 'color' => server_load_status_color('high')];
    }
}

if (!function_exists('server_load_method_badge_class')) {
    function server_load_method_badge_class(string $method): string
    {
        switch (strtoupper($method)) {
            case 'GET':
                return 'badge badge-success';
            case 'POST':
                return 'badge color-inprogress inprogress-bg-op';
            case 'PUT':
            case 'PATCH':
                return 'badge badge-warning';
            case 'DELETE':
                return 'badge badge-danger';
            default:
                return 'badge badge-secondary';
        }
    }
}

if (!function_exists('server_load_parse_range')) {
    function server_load_parse_range(?string $range): string
    {
        $allowed = ['24h', '48h', '7d', '30d', '60d'];
        $range = trim((string)$range);
        return in_array($range, $allowed, true) ? $range : '24h';
    }
}

if (!function_exists('server_load_range_to_datetime')) {
    function server_load_range_to_datetime(string $range): string
    {
        switch ($range) {
            case '48h':
                return date('Y-m-d H:i:s', strtotime('-48 hours'));
            case '7d':
                return date('Y-m-d H:i:s', strtotime('-7 days'));
            case '30d':
                return date('Y-m-d H:i:s', strtotime('-30 days'));
            case '60d':
                return date('Y-m-d H:i:s', strtotime('-60 days'));
            case '24h':
            default:
                return date('Y-m-d H:i:s', strtotime('-24 hours'));
        }
    }
}

if (!function_exists('server_load_range_labels')) {
    function server_load_range_labels(): array
    {
        return [
            '24h' => server_load_t('Last 24 Hours'),
            '48h' => server_load_t('Last 48 Hours'),
            '7d' => server_load_t('Last 7 days'),
            '30d' => server_load_t('Last 30 days'),
            '60d' => server_load_t('Last 60 Days'),
        ];
    }
}

if (!function_exists('server_load_read_proc_stat')) {
    /**
     * @return array{total:float,idle:float}|null
     */
    function server_load_read_proc_stat(): ?array
    {
        $raw = server_load_safe_read_file('/proc/stat');
        if ($raw === null) {
            return null;
        }
        foreach (explode("\n", $raw) as $line) {
            if (strpos($line, 'cpu ') !== 0) {
                continue;
            }
            $parts = preg_split('/\s+/', trim($line));
            if (!$parts || count($parts) < 5) {
                return null;
            }
            array_shift($parts);
            $nums = array_map('floatval', $parts);
            $total = array_sum($nums);
            $idle = ($nums[3] ?? 0) + ($nums[4] ?? 0);
            return ['total' => $total, 'idle' => $idle];
        }
        return null;
    }
}

if (!function_exists('server_load_sample_cpu_percent')) {
    function server_load_sample_cpu_percent(): ?float
    {
        $first = server_load_read_proc_stat();
        if ($first === null) {
            return null;
        }
        usleep(100000);
        $second = server_load_read_proc_stat();
        if ($second === null) {
            return null;
        }
        $totalDelta = $second['total'] - $first['total'];
        $idleDelta = $second['idle'] - $first['idle'];
        if ($totalDelta <= 0) {
            return null;
        }
        $usage = (1 - ($idleDelta / $totalDelta)) * 100;
        return max(0, min(100, round($usage, 2)));
    }
}

if (!function_exists('server_load_read_meminfo')) {
    function server_load_read_meminfo(): ?array
    {
        $raw = server_load_safe_read_file('/proc/meminfo');
        if ($raw === null) {
            return null;
        }
        $out = [];
        foreach (explode("\n", $raw) as $line) {
            if (!preg_match('/^(\w+):\s+(\d+)\s+kB$/', trim($line), $m)) {
                continue;
            }
            $out[$m[1]] = (int)$m[2] * 1024;
        }
        return $out ?: null;
    }
}

if (!function_exists('server_load_read_network_bytes')) {
    function server_load_read_network_bytes(): ?array
    {
        $raw = server_load_safe_read_file('/proc/net/dev');
        if ($raw === null) {
            return null;
        }
        $rx = 0;
        $tx = 0;
        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, ':') === false) {
                continue;
            }
            if (preg_match('/^\s*(lo):/i', $line)) {
                continue;
            }
            $parts = preg_split('/\s+/', preg_replace('/^[^:]+:\s*/', '', $line));
            if (count($parts) < 9) {
                continue;
            }
            $rx += (int)$parts[0];
            $tx += (int)$parts[8];
        }
        return ['in' => $rx, 'out' => $tx];
    }
}

if (!function_exists('server_load_read_uptime_seconds')) {
    function server_load_read_uptime_seconds(): ?float
    {
        $raw = server_load_safe_read_file('/proc/uptime');
        if ($raw === null) {
            return null;
        }
        $parts = explode(' ', trim($raw));
        return isset($parts[0]) ? (float)$parts[0] : null;
    }
}

if (!function_exists('server_load_parse_cpuinfo')) {
    function server_load_parse_cpuinfo(): array
    {
        $defaults = [
            'model' => 'Not Available',
            'cores' => 0,
            'threads' => 0,
            'mhz' => null,
        ];
        $raw = server_load_safe_read_file('/proc/cpuinfo');
        if ($raw === null) {
            $machine = trim((string)@php_uname('m'));
            return [
                'model' => $machine !== '' ? $machine : 'Not Available',
                'cores' => 1,
                'threads' => 1,
                'mhz' => null,
            ];
        }
        $model = 'Not Available';
        $cores = 0;
        $siblings = 0;
        $mhz = null;
        foreach (explode("\n", $raw) as $line) {
            if (strpos($line, 'model name') === 0 && $model === 'Not Available') {
                $model = trim(substr($line, strpos($line, ':') + 1));
            } elseif (strpos($line, 'processor') === 0) {
                $cores++;
            } elseif (strpos($line, 'siblings') === 0 && $siblings === 0) {
                $siblings = (int)trim(substr($line, strpos($line, ':') + 1));
            } elseif (strpos($line, 'cpu MHz') === 0 && $mhz === null) {
                $mhz = round((float)trim(substr($line, strpos($line, ':') + 1)), 1);
            }
        }
        if ($cores <= 0) {
            $cores = 1;
        }
        $threads = $siblings > 0 ? $siblings : $cores;
        if ($model === 'Not Available') {
            $machine = trim((string)@php_uname('m'));
            if ($machine !== '') {
                $model = $machine;
            }
        }
        return [
            'model' => $model !== '' ? $model : 'Not Available',
            'cores' => $cores,
            'threads' => $threads,
            'mhz' => $mhz,
        ];
    }
}

if (!function_exists('server_load_format_cpu_ghz')) {
    function server_load_format_cpu_ghz(?float $mhz): ?string
    {
        if ($mhz === null || $mhz <= 0) {
            return null;
        }
        $ghz = $mhz >= 100 ? round($mhz / 1000, 2) : round($mhz, 2);
        if (fmod($ghz, 1.0) === 0.0) {
            return (string)(int)$ghz;
        }

        return rtrim(rtrim(number_format($ghz, 2, '.', ''), '0'), '.');
    }
}

if (!function_exists('server_load_get_home_dir')) {
    function server_load_get_home_dir(): ?string
    {
        if (!empty($_SERVER['HOME']) && is_dir((string)$_SERVER['HOME'])) {
            $home = realpath((string)$_SERVER['HOME']);
            return $home !== false ? $home : (string)$_SERVER['HOME'];
        }
        if (function_exists('posix_getuid') && function_exists('posix_getpwuid')) {
            $info = @posix_getpwuid(posix_getuid());
            if (is_array($info) && !empty($info['dir']) && is_dir($info['dir'])) {
                $home = realpath($info['dir']);
                return $home !== false ? $home : $info['dir'];
            }
        }

        return null;
    }
}

if (!function_exists('server_load_is_shared_hosting')) {
    function server_load_is_shared_hosting(): bool
    {
        if (is_readable('/proc/lve/list')) {
            return true;
        }
        $home = server_load_get_home_dir();
        if ($home && is_dir($home . '/.cpanel')) {
            return true;
        }

        return false;
    }
}

if (!function_exists('server_load_parse_lve_account_row')) {
    /**
     * @return array{headers:array<string,int>,values:array<int,string>}|null
     */
    function server_load_parse_lve_account_row(?int $targetUid): ?array
    {
        if ($targetUid === null || $targetUid <= 0) {
            return null;
        }
        $raw = server_load_safe_read_file('/proc/lve/list');
        if ($raw === null) {
            return null;
        }
        $lines = preg_split('/\r\n|\r|\n/', trim($raw));
        if (count($lines) < 2 || !preg_match('/^\d+:LVE(?:\t|$)/', $lines[0])) {
            return null;
        }

        $headerRaw = preg_replace('/^\d+:LVE\t?/', '', $lines[0]);
        $headers = preg_split('/\t+/', trim($headerRaw));
        $colIndex = [];
        foreach ($headers as $idx => $name) {
            $colIndex[strtoupper(trim($name))] = $idx;
        }

        foreach (array_slice($lines, 1) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = preg_split('/\t+/', $line);
            if (count($parts) < 2) {
                continue;
            }
            $lveIdRaw = $parts[0];
            if (strpos($lveIdRaw, ',') !== false) {
                $bits = explode(',', $lveIdRaw);
                $lveId = (int)end($bits);
            } else {
                $lveId = (int)$lveIdRaw;
            }
            if ($lveId !== $targetUid) {
                continue;
            }

            return [
                'headers' => $colIndex,
                'values' => array_slice($parts, 1),
            ];
        }

        return null;
    }
}

if (!function_exists('server_load_lve_value')) {
    function server_load_lve_value(array $row, string $key): ?float
    {
        $idx = $row['headers'][strtoupper($key)] ?? null;
        if ($idx === null || !isset($row['values'][$idx])) {
            return null;
        }
        $value = trim((string)$row['values'][$idx]);
        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        return (float)$value;
    }
}

if (!function_exists('server_load_array_find_disk_quota')) {
    /**
     * @return array{limit_bytes:int,used_bytes:?int}|null
     */
    function server_load_array_find_disk_quota(array $data, int $depth = 0): ?array
    {
        if ($depth > 8) {
            return null;
        }

        $limitMb = null;
        if (isset($data['megabyte_limit']) && is_numeric($data['megabyte_limit'])) {
            $limitMb = (int)$data['megabyte_limit'];
        } elseif (isset($data['disklimit']) && is_numeric($data['disklimit'])) {
            $limitMb = (int)$data['disklimit'];
        } elseif (isset($data['quota']) && is_numeric($data['quota'])) {
            $limitMb = (int)$data['quota'];
        }

        if ($limitMb !== null && $limitMb > 0) {
            $usedMb = null;
            foreach (['megabytes_used', 'diskused', 'used', 'bytes_used'] as $usedKey) {
                if (isset($data[$usedKey]) && is_numeric($data[$usedKey])) {
                    $usedMb = (int)$data[$usedKey];
                    break;
                }
            }

            return [
                'limit_bytes' => $limitMb * 1024 * 1024,
                'used_bytes' => $usedMb !== null && $usedMb > 0 ? $usedMb * 1024 * 1024 : null,
            ];
        }

        foreach ($data as $value) {
            if (!is_array($value)) {
                continue;
            }
            $found = server_load_array_find_disk_quota($value, $depth + 1);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}

if (!function_exists('server_load_read_cpanel_disk_quota')) {
    /**
     * @return array{limit_bytes:int,used_bytes:?int}|null
     */
    function server_load_read_cpanel_disk_quota(?string $home): ?array
    {
        if ($home === null || $home === '') {
            return null;
        }
        $dirs = [
            $home . '/.cpanel/datastore',
            $home . '/.cpanel/caches/diskusage',
            $home . '/.cpanel/caches',
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir) || !is_readable($dir)) {
                continue;
            }
            foreach (glob($dir . '/*') ?: [] as $file) {
                if (!is_file($file) || !is_readable($file)) {
                    continue;
                }
                $raw = @file_get_contents($file);
                if ($raw === false || $raw === '' || !isset($raw[0])) {
                    continue;
                }
                if ($raw[0] === '{' || $raw[0] === '[') {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $found = server_load_array_find_disk_quota($decoded);
                        if ($found !== null) {
                            return $found;
                        }
                    }
                }
            }
        }

        return null;
    }
}

if (!function_exists('server_load_cached_home_disk_used')) {
    function server_load_cached_home_disk_used(string $home): ?int
    {
        $cacheFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'crm_srv_disk_' . md5($home) . '.cache';
        if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
            $cached = (int)trim((string)@file_get_contents($cacheFile));
            return $cached > 0 ? $cached : null;
        }

        $size = server_load_dir_size_bytes($home);
        if ($size !== null && $size > 0) {
            @file_put_contents($cacheFile, (string)$size);
        }

        return $size;
    }
}

if (!function_exists('server_load_dir_size_bytes')) {
    function server_load_dir_size_bytes(string $path, int $maxSeconds = 10): ?int
    {
        if (!is_dir($path) || !is_readable($path)) {
            return null;
        }
        if (!class_exists('FilesystemIterator') || !class_exists('RecursiveDirectoryIterator') || !class_exists('RecursiveIteratorIterator')) {
            return null;
        }

        $realRoot = realpath($path);
        if ($realRoot === false || !is_dir($realRoot)) {
            return null;
        }
        $rootPrefix = rtrim(str_replace('\\', '/', $realRoot), '/') . '/';

        $start = time();
        $size = 0;
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($realRoot, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $file) {
                if ((time() - $start) > $maxSeconds) {
                    break;
                }
                if ($file->isLink()) {
                    continue;
                }
                if (!$file->isFile()) {
                    continue;
                }
                $realFile = $file->getRealPath();
                if ($realFile === false) {
                    continue;
                }
                $realNorm = str_replace('\\', '/', $realFile);
                if (strpos($realNorm, $rootPrefix) !== 0) {
                    continue;
                }
                $size += (int)@filesize($realFile);
            }
        } catch (Throwable $e) {
            return $size > 0 ? $size : null;
        }

        return $size >= 0 ? $size : null;
    }
}

if (!function_exists('server_load_uploads_root')) {
    function server_load_uploads_root(): string
    {
        return server_load_app_root() . DIRECTORY_SEPARATOR . 'uploads';
    }
}

if (!function_exists('server_load_dir_size_or_zero')) {
    function server_load_dir_size_or_zero(string $path, int $maxSeconds = 30): int
    {
        if (!is_dir($path) || !is_readable($path)) {
            return 0;
        }
        $size = server_load_dir_size_bytes($path, $maxSeconds);
        return $size !== null ? (int)$size : 0;
    }
}

if (!function_exists('server_load_dirs_size_bytes')) {
    function server_load_dirs_size_bytes(array $paths, int $maxSecondsPerDir = 45): int
    {
        $total = 0;
        foreach ($paths as $path) {
            $total += server_load_dir_size_or_zero((string)$path, $maxSecondsPerDir);
        }
        return $total;
    }
}

if (!function_exists('server_load_sum_uploads_top_level_bytes')) {
    /**
     * Sum each direct child of uploads/ separately so large trees do not hit one global timeout.
     */
    function server_load_sum_uploads_top_level_bytes(int $maxSecondsPerDir = 60): int
    {
        $root = server_load_uploads_root();
        if (!is_dir($root) || !is_readable($root)) {
            return 0;
        }

        $total = 0;
        $entries = @scandir($root);
        if ($entries === false) {
            return 0;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $root . DIRECTORY_SEPARATOR . $entry;
            if (is_file($path)) {
                $total += (int)@filesize($path);
                continue;
            }
            if (is_dir($path)) {
                $total += server_load_dir_size_or_zero($path, $maxSecondsPerDir);
            }
        }

        return $total;
    }
}

if (!function_exists('server_load_table_exists')) {
    function server_load_table_exists(string $table): bool
    {
        global $connect;
        if (empty($connect)) {
            return false;
        }
        $table = trim($table);
        if ($table === '') {
            return false;
        }
        $escaped = $connect->real_escape_string($table);
        $res = @$connect->query("SHOW TABLES LIKE '{$escaped}'");
        return $res && $res->num_rows > 0;
    }
}

if (!function_exists('server_load_abs_from_relative_upload')) {
    function server_load_abs_from_relative_upload(string $relativePath): string
    {
        $rel = str_replace('\\', '/', ltrim(trim($relativePath), '/'));
        return server_load_app_root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    }
}

if (!function_exists('server_load_private_notes_thumb_rel')) {
    function server_load_private_notes_thumb_rel(string $relativePath): string
    {
        $normalized = str_replace('\\', '/', trim($relativePath));
        $dir = trim((string)dirname($normalized), '/.');
        $name = basename($normalized);
        if ($dir === '' || $dir === '.') {
            return 'thumbs/' . $name;
        }
        return $dir . '/thumbs/' . $name;
    }
}

if (!function_exists('server_load_sum_task_files_bytes')) {
    function server_load_sum_task_files_bytes(): ?int
    {
        global $connect;
        if (empty($connect) || !server_load_table_exists('task_files')) {
            return null;
        }
        $res = @$connect->query('SELECT COALESCE(SUM(file_size), 0) AS total FROM task_files');
        if (!$res) {
            return null;
        }
        $row = $res->fetch_assoc();
        return $row ? (int)$row['total'] : null;
    }
}

if (!function_exists('server_load_sum_private_note_media_bytes')) {
    function server_load_sum_private_note_media_bytes(): ?int
    {
        global $connect;
        if (empty($connect) || !server_load_table_exists('private_note_media')) {
            return null;
        }
        $res = @$connect->query('SELECT file_path FROM private_note_media');
        if (!$res) {
            return null;
        }
        $total = 0;
        $seen = [];
        while ($row = $res->fetch_assoc()) {
            $path = trim((string)($row['file_path'] ?? ''));
            if ($path === '' || isset($seen[$path])) {
                continue;
            }
            $seen[$path] = true;
            $paths = [$path, server_load_private_notes_thumb_rel($path)];
            foreach ($paths as $rel) {
                $abs = server_load_abs_from_relative_upload($rel);
                if (is_file($abs)) {
                    $total += (int)@filesize($abs);
                }
            }
        }
        return $total;
    }
}

if (!function_exists('server_load_sum_email_attachments_bytes')) {
    function server_load_sum_email_attachments_bytes(): ?int
    {
        global $connect;
        if (empty($connect) || !server_load_table_exists('email_attachments')) {
            return null;
        }
        $res = @$connect->query('SELECT COALESCE(SUM(file_size), 0) AS total FROM email_attachments');
        if (!$res) {
            return null;
        }
        $row = $res->fetch_assoc();
        return $row ? (int)$row['total'] : null;
    }
}

if (!function_exists('server_load_sum_email_bodies_bytes')) {
    function server_load_sum_email_bodies_bytes(): ?int
    {
        global $connect;
        if (empty($connect) || !server_load_table_exists('email_messages')) {
            return null;
        }
        $res = @$connect->query(
            "SELECT DISTINCT body_file_path FROM email_messages
             WHERE body_file_path IS NOT NULL AND TRIM(body_file_path) <> ''"
        );
        if (!$res) {
            return null;
        }
        $total = 0;
        $seen = [];
        $baseDir = server_load_app_root() . DIRECTORY_SEPARATOR . 'uploads'
            . DIRECTORY_SEPARATOR . 'email-bodies' . DIRECTORY_SEPARATOR;
        while ($row = $res->fetch_assoc()) {
            $rel = str_replace('\\', '/', trim((string)($row['body_file_path'] ?? '')));
            if ($rel === '' || isset($seen[$rel])) {
                continue;
            }
            $seen[$rel] = true;
            $abs = $baseDir . str_replace('/', DIRECTORY_SEPARATOR, ltrim($rel, '/'));
            if (is_file($abs)) {
                $total += (int)@filesize($abs);
            }
        }
        return $total;
    }
}

if (!function_exists('server_load_sum_email_usage_bytes')) {
    function server_load_sum_email_usage_bytes(): ?int
    {
        $attachments = server_load_sum_email_attachments_bytes();
        $bodies = server_load_sum_email_bodies_bytes();
        if ($attachments === null && $bodies === null) {
            return null;
        }
        return max(0, (int)($attachments ?? 0) + (int)($bodies ?? 0));
    }
}

if (!function_exists('server_load_get_media_usage')) {
    /**
     * Disk usage breakdown for uploads/ subfolders (cached 1 hour).
     *
     * @param bool $forceRefresh Skip file cache and rebuild.
     * @return list<array{key:string,title:string,line1:string,line2:string}>
     */
    function server_load_get_media_usage(bool $forceRefresh = false): array
    {
        static $requestCache = null;
        if (!$forceRefresh && $requestCache !== null) {
            return $requestCache;
        }

        $cacheFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'crm_server_media_usage_v2.cache';
        if ($forceRefresh) {
            @unlink($cacheFile);
            $requestCache = null;
        } elseif (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
            $raw = @file_get_contents($cacheFile);
            if ($raw !== false && $raw !== '') {
                $cached = json_decode($raw, true);
                if (is_array($cached) && !empty($cached['cards'])) {
                    $requestCache = $cached['cards'];
                    return $requestCache;
                }
            }
        }

        $uploadsRoot = server_load_uploads_root();
        $defs = [
            'all' => [
                'title' => server_load_t('server_load_media_all', 'All Data Usage'),
                'line2' => server_load_t('server_load_media_uploads_total', 'Total uploads/ folder size on disk'),
                'max_seconds' => 60,
            ],
            'chat' => [
                'title' => server_load_t('server_load_media_chat', 'Chat files'),
                'paths' => [$uploadsRoot . DIRECTORY_SEPARATOR . 'user-uploads'],
                'line2' => server_load_t('server_load_media_chat_line2', 'uploads/user-uploads/ · on disk'),
                'max_seconds' => 60,
            ],
            'notes' => [
                'title' => server_load_t('server_load_media_notes', 'Notes files'),
                'paths' => [$uploadsRoot . DIRECTORY_SEPARATOR . 'private-notes'],
                'line2' => server_load_t('server_load_media_notes_line2', 'uploads/private-notes/ · on disk'),
                'max_seconds' => 60,
            ],
            'email' => [
                'title' => server_load_t('server_load_media_email', 'Email usage'),
                'paths' => [
                    $uploadsRoot . DIRECTORY_SEPARATOR . 'email-attachments',
                    $uploadsRoot . DIRECTORY_SEPARATOR . 'email-bodies',
                ],
                'line2' => server_load_t('server_load_media_email_line2', 'Attachments + body files · on disk'),
                'max_seconds' => 60,
            ],
            'task' => [
                'title' => server_load_t('server_load_media_task', 'Task files'),
                'paths' => [$uploadsRoot . DIRECTORY_SEPARATOR . 'task-files'],
                'line2' => server_load_t('server_load_media_task_line2', 'uploads/task-files/ · on disk'),
                'max_seconds' => 60,
            ],
        ];

        $cards = [];
        foreach ($defs as $key => $def) {
            if ($key === 'all') {
                $bytes = server_load_sum_uploads_top_level_bytes((int)$def['max_seconds']);
            } else {
                $bytes = server_load_dirs_size_bytes($def['paths'], (int)$def['max_seconds']);
            }
            $cards[] = [
                'key' => $key,
                'title' => $def['title'],
                'line1' => server_load_format_bytes($bytes),
                'line2' => $def['line2'],
            ];
        }

        @file_put_contents($cacheFile, json_encode(['cards' => $cards, 'ts' => time()]));
        $requestCache = $cards;
        return $cards;
    }
}

if (!function_exists('server_load_media_breakdown_cache_file')) {
    function server_load_media_breakdown_cache_file(string $detailKey): string
    {
        $safe = preg_replace('/[^a-z0-9_]/', '', strtolower($detailKey));
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . 'crm_server_media_breakdown_' . $safe . '.cache';
    }
}

if (!function_exists('server_load_media_breakdown_sort_rows')) {
    /**
     * @param list<array{label:string,bytes:int,formatted?:string,meta?:string,pct?:float|null}> $rows
     * @return list<array{label:string,bytes:int,formatted:string,meta:string,pct:float|null}>
     */
    function server_load_media_breakdown_sort_rows(array $rows): array
    {
        usort($rows, static function ($a, $b) {
            return ((int)($b['bytes'] ?? 0)) <=> ((int)($a['bytes'] ?? 0));
        });
        $total = 0;
        foreach ($rows as $row) {
            $total += max(0, (int)($row['bytes'] ?? 0));
        }
        $out = [];
        foreach ($rows as $row) {
            $bytes = max(0, (int)($row['bytes'] ?? 0));
            if ($bytes <= 0 && empty($row['keep_zero'])) {
                continue;
            }
            $pct = ($total > 0) ? round(($bytes / $total) * 100, 1) : null;
            $item = [
                'label' => (string)($row['label'] ?? ''),
                'bytes' => $bytes,
                'formatted' => server_load_format_bytes($bytes),
                'meta' => (string)($row['meta'] ?? ''),
                'pct' => $pct,
            ];
            if (isset($row['account_id'])) {
                $item['account_id'] = (int)$row['account_id'];
            }
            $out[] = $item;
        }
        return $out;
    }
}

if (!function_exists('server_load_user_display_map')) {
    /**
     * @param list<int> $userIds
     * @return array<int,string>
     */
    function server_load_user_display_map(array $userIds): array
    {
        global $connect;
        $map = [];
        if (empty($connect) || empty($userIds) || !server_load_table_exists('users')) {
            return $map;
        }
        $ids = [];
        foreach ($userIds as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if (empty($ids)) {
            return $map;
        }
        $idList = implode(',', $ids);
        $res = @$connect->query("SELECT id, firstName, email FROM users WHERE id IN ({$idList})");
        if (!$res) {
            return $map;
        }
        while ($row = $res->fetch_assoc()) {
            $uid = (int)$row['id'];
            $name = trim((string)($row['firstName'] ?? ''));
            $email = trim((string)($row['email'] ?? ''));
            if ($name !== '') {
                $map[$uid] = $name . ' (#' . $uid . ')';
            } elseif ($email !== '') {
                $map[$uid] = $email . ' (#' . $uid . ')';
            } else {
                $map[$uid] = 'User #' . $uid;
            }
        }
        return $map;
    }
}

if (!function_exists('server_load_media_breakdown_uploads_folders')) {
    /**
     * @return list<array{label:string,bytes:int,meta?:string}>
     */
    function server_load_media_breakdown_uploads_folders(int $maxSecondsPerDir = 45): array
    {
        $root = server_load_uploads_root();
        if (!is_dir($root) || !is_readable($root)) {
            return [];
        }
        $entries = @scandir($root);
        if ($entries === false) {
            return [];
        }
        $rows = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $root . DIRECTORY_SEPARATOR . $entry;
            if (is_file($path)) {
                $bytes = (int)@filesize($path);
                $rows[] = [
                    'label' => $entry,
                    'bytes' => $bytes,
                    'meta' => 'file',
                ];
                continue;
            }
            if (is_dir($path)) {
                $rows[] = [
                    'label' => $entry . '/',
                    'bytes' => server_load_dir_size_or_zero($path, $maxSecondsPerDir),
                    'meta' => 'uploads/' . $entry,
                ];
            }
        }
        return $rows;
    }
}

if (!function_exists('server_load_media_breakdown_notes_users')) {
    /**
     * @return list<array{label:string,bytes:int,meta?:string}>
     */
    function server_load_media_breakdown_notes_users(int $maxSecondsPerDir = 20): array
    {
        $root = server_load_uploads_root() . DIRECTORY_SEPARATOR . 'private-notes';
        if (!is_dir($root) || !is_readable($root)) {
            return [];
        }
        $entries = @scandir($root);
        if ($entries === false) {
            return [];
        }
        $byUser = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $root . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($path)) {
                continue;
            }
            if (!ctype_digit((string)$entry)) {
                $byUser['other:' . $entry] = [
                    'uid' => 0,
                    'label' => $entry . '/',
                    'bytes' => server_load_dir_size_or_zero($path, $maxSecondsPerDir),
                    'meta' => 'uploads/private-notes/' . $entry,
                ];
                continue;
            }
            $uid = (int)$entry;
            $byUser[$uid] = [
                'uid' => $uid,
                'label' => 'User #' . $uid,
                'bytes' => server_load_dir_size_or_zero($path, $maxSecondsPerDir),
                'meta' => 'uploads/private-notes/' . $uid,
            ];
        }
        $userIds = [];
        foreach ($byUser as $row) {
            $uid = (int)($row['uid'] ?? 0);
            if ($uid > 0) {
                $userIds[] = $uid;
            }
        }
        $names = server_load_user_display_map($userIds);
        $rows = [];
        foreach ($byUser as $row) {
            $uid = (int)($row['uid'] ?? 0);
            if ($uid > 0 && isset($names[$uid])) {
                $row['label'] = $names[$uid];
            }
            $rows[] = $row;
        }
        return $rows;
    }
}

if (!function_exists('server_load_media_breakdown_task_users')) {
    /**
     * @return list<array{label:string,bytes:int,meta?:string}>
     */
    function server_load_media_breakdown_task_users(): array
    {
        global $connect;
        if (empty($connect) || !server_load_table_exists('task_files')) {
            return [];
        }
        $res = @$connect->query(
            'SELECT user_id, COALESCE(SUM(file_size), 0) AS total_bytes
             FROM task_files
             GROUP BY user_id
             ORDER BY total_bytes DESC'
        );
        if (!$res) {
            return [];
        }
        $raw = [];
        while ($row = $res->fetch_assoc()) {
            $uid = (int)($row['user_id'] ?? 0);
            $bytes = (int)($row['total_bytes'] ?? 0);
            if ($bytes <= 0) {
                continue;
            }
            $raw[] = [
                'uid' => $uid,
                'bytes' => $bytes,
            ];
        }
        $names = server_load_user_display_map(array_column($raw, 'uid'));
        $rows = [];
        foreach ($raw as $item) {
            $uid = (int)$item['uid'];
            $label = ($uid > 0 && isset($names[$uid]))
                ? $names[$uid]
                : (($uid > 0) ? ('User #' . $uid) : server_load_t('server_load_media_unknown', 'Unknown'));
            $rows[] = [
                'label' => $label,
                'bytes' => (int)$item['bytes'],
                'meta' => $uid > 0 ? ('user_id=' . $uid) : '',
            ];
        }
        return $rows;
    }
}

if (!function_exists('server_load_media_breakdown_email_accounts')) {
    /**
     * @return list<array{label:string,bytes:int,meta?:string}>
     */
    function server_load_media_breakdown_email_accounts(int $maxSecondsPerAccount = 15): array
    {
        global $connect;
        $byAccount = [];

        if (!empty($connect) && server_load_table_exists('email_accounts')) {
            $accRes = @$connect->query('SELECT id, email_address FROM email_accounts');
            if ($accRes) {
                while ($row = $accRes->fetch_assoc()) {
                    $aid = (int)$row['id'];
                    $byAccount[$aid] = [
                        'label' => trim((string)($row['email_address'] ?? '')) !== ''
                            ? (string)$row['email_address']
                            : ('Account #' . $aid),
                        'bytes' => 0,
                        'meta' => 'account_id=' . $aid,
                        'account_id' => $aid,
                    ];
                }
            }
        }

        if (!empty($connect)
            && server_load_table_exists('email_attachments')
            && server_load_table_exists('email_messages')
        ) {
            $attRes = @$connect->query(
                'SELECT m.account_id AS account_id, COALESCE(SUM(a.file_size), 0) AS total_bytes
                 FROM email_attachments a
                 INNER JOIN email_messages m ON m.id = a.message_id
                 GROUP BY m.account_id'
            );
            if ($attRes) {
                while ($row = $attRes->fetch_assoc()) {
                    $aid = (int)($row['account_id'] ?? 0);
                    $bytes = (int)($row['total_bytes'] ?? 0);
                    if ($aid <= 0 || $bytes <= 0) {
                        continue;
                    }
                    if (!isset($byAccount[$aid])) {
                        $byAccount[$aid] = [
                            'label' => 'Account #' . $aid,
                            'bytes' => 0,
                            'meta' => 'account_id=' . $aid,
                            'account_id' => $aid,
                        ];
                    }
                    $byAccount[$aid]['bytes'] += $bytes;
                }
            }
        }

        $bodiesRoot = server_load_uploads_root() . DIRECTORY_SEPARATOR . 'email-bodies';
        if (is_dir($bodiesRoot) && is_readable($bodiesRoot)) {
            $entries = @scandir($bodiesRoot);
            if ($entries !== false) {
                foreach ($entries as $entry) {
                    if ($entry === '.' || $entry === '..') {
                        continue;
                    }
                    $path = $bodiesRoot . DIRECTORY_SEPARATOR . $entry;
                    if (!is_dir($path)) {
                        continue;
                    }
                    $aid = 0;
                    if (preg_match('/^account_(\d+)$/', $entry, $m)) {
                        $aid = (int)$m[1];
                    }
                    $bytes = server_load_dir_size_or_zero($path, $maxSecondsPerAccount);
                    if ($bytes <= 0) {
                        continue;
                    }
                    if ($aid > 0) {
                        if (!isset($byAccount[$aid])) {
                            $byAccount[$aid] = [
                                'label' => 'Account #' . $aid,
                                'bytes' => 0,
                                'meta' => 'account_id=' . $aid,
                                'account_id' => $aid,
                            ];
                        }
                        $byAccount[$aid]['bytes'] += $bytes;
                    } else {
                        $byAccount['folder:' . $entry] = [
                            'label' => $entry . '/',
                            'bytes' => $bytes,
                            'meta' => 'uploads/email-bodies/' . $entry,
                        ];
                    }
                }
            }
        } elseif (!empty($connect) && server_load_table_exists('email_messages')) {
            $bodyRes = @$connect->query(
                "SELECT account_id, body_file_path FROM email_messages
                 WHERE body_file_path IS NOT NULL AND TRIM(body_file_path) <> ''"
            );
            if ($bodyRes) {
                $seen = [];
                $baseDir = $bodiesRoot . DIRECTORY_SEPARATOR;
                while ($row = $bodyRes->fetch_assoc()) {
                    $aid = (int)($row['account_id'] ?? 0);
                    $rel = str_replace('\\', '/', trim((string)($row['body_file_path'] ?? '')));
                    if ($aid <= 0 || $rel === '' || isset($seen[$rel])) {
                        continue;
                    }
                    $seen[$rel] = true;
                    $abs = $baseDir . str_replace('/', DIRECTORY_SEPARATOR, ltrim($rel, '/'));
                    if (!is_file($abs)) {
                        continue;
                    }
                    $bytes = (int)@filesize($abs);
                    if ($bytes <= 0) {
                        continue;
                    }
                    if (!isset($byAccount[$aid])) {
                        $byAccount[$aid] = [
                            'label' => 'Account #' . $aid,
                            'bytes' => 0,
                            'meta' => 'account_id=' . $aid,
                            'account_id' => $aid,
                        ];
                    }
                    $byAccount[$aid]['bytes'] += $bytes;
                }
            }
        }

        return array_values($byAccount);
    }
}

if (!function_exists('server_load_chat_extract_attachment_files')) {
    /**
     * @return list<string>
     */
    function server_load_chat_extract_attachment_files(string $message): array
    {
        $files = [];
        if ($message === '') {
            return $files;
        }
        if (preg_match_all('/\[(?:photo|file|voice)Attachment-([^\]\|]+)(?:\|[^\]]*)?\]/i', $message, $matches)) {
            foreach ($matches[1] as $name) {
                $name = basename(str_replace(['\\', '/'], '', trim((string)$name)));
                if ($name !== '') {
                    $files[$name] = $name;
                }
            }
        }
        return array_values($files);
    }
}

if (!function_exists('server_load_media_breakdown_chat_users')) {
    /**
     * Attribute user-uploads files to users via decrypted chat message markers.
     *
     * @return list<array{label:string,bytes:int,meta?:string}>
     */
    function server_load_media_breakdown_chat_users(int $maxSeconds = 25): array
    {
        global $connect;
        $uploadDir = server_load_uploads_root() . DIRECTORY_SEPARATOR . 'user-uploads';
        if (!is_dir($uploadDir) || !is_readable($uploadDir)) {
            return [];
        }

        $diskBytes = [];
        $entries = @scandir($uploadDir);
        if ($entries === false) {
            return [];
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $uploadDir . DIRECTORY_SEPARATOR . $entry;
            if (!is_file($path)) {
                continue;
            }
            $diskBytes[$entry] = (int)@filesize($path);
        }
        if (empty($diskBytes)) {
            return [];
        }

        $fileOwner = [];
        $start = time();
        $canDecrypt = function_exists('decryptString');

        $consumeRows = static function ($res) use (&$fileOwner, &$start, $maxSeconds, $canDecrypt, $diskBytes): bool {
            if (!$res) {
                return true;
            }
            while ($row = $res->fetch_assoc()) {
                if ((time() - $start) > $maxSeconds) {
                    return false;
                }
                $uid = (int)($row['user_id'] ?? 0);
                $raw = (string)($row['message'] ?? '');
                if ($raw === '') {
                    continue;
                }
                $plain = $raw;
                if ($canDecrypt && strpos($raw, '[') === false) {
                    $dec = @decryptString($raw);
                    if (is_string($dec) && $dec !== '') {
                        $plain = $dec;
                    }
                }
                if (stripos($plain, 'Attachment-') === false) {
                    continue;
                }
                foreach (server_load_chat_extract_attachment_files($plain) as $filename) {
                    if (!isset($diskBytes[$filename])) {
                        continue;
                    }
                    if (!isset($fileOwner[$filename])) {
                        $fileOwner[$filename] = $uid > 0 ? $uid : 0;
                    }
                }
            }
            return true;
        };

        if (!empty($connect) && server_load_table_exists('messages')) {
            $res = @$connect->query('SELECT user_id, message FROM messages ORDER BY id DESC');
            if (!$consumeRows($res)) {
                // time budget hit
            }
        }
        if ((time() - $start) <= $maxSeconds
            && !empty($connect)
            && server_load_table_exists('group_chat_messages')
        ) {
            $res = @$connect->query('SELECT user_id, message FROM group_chat_messages ORDER BY id DESC');
            $consumeRows($res);
        }

        $byUser = [];
        $attributed = 0;
        foreach ($diskBytes as $filename => $bytes) {
            if ($bytes <= 0) {
                continue;
            }
            if (isset($fileOwner[$filename]) && (int)$fileOwner[$filename] > 0) {
                $uid = (int)$fileOwner[$filename];
                if (!isset($byUser[$uid])) {
                    $byUser[$uid] = 0;
                }
                $byUser[$uid] += $bytes;
                $attributed += $bytes;
            }
        }
        $orphan = array_sum($diskBytes) - $attributed;

        $names = server_load_user_display_map(array_keys($byUser));
        $rows = [];
        foreach ($byUser as $uid => $bytes) {
            $rows[] = [
                'label' => isset($names[$uid]) ? $names[$uid] : ('User #' . (int)$uid),
                'bytes' => (int)$bytes,
                'meta' => 'user_id=' . (int)$uid,
            ];
        }
        if ($orphan > 0) {
            $rows[] = [
                'label' => server_load_t('server_load_media_unknown', 'Unknown'),
                'bytes' => (int)$orphan,
                'meta' => 'unattributed',
            ];
        }
        return $rows;
    }
}

if (!function_exists('server_load_get_media_breakdown')) {
    /**
     * Per-card breakdown rows for Media usage detail table.
     *
     * @param bool $forceRefresh Skip file cache and rebuild.
     * @return list<array{label:string,bytes:int,formatted:string,meta:string,pct:float|null}>
     */
    function server_load_get_media_breakdown(string $detailKey, bool $forceRefresh = false): array
    {
        static $requestCache = [];
        $detailKey = strtolower(trim($detailKey));
        $allowed = ['all', 'chat', 'notes', 'email', 'task'];
        if (!in_array($detailKey, $allowed, true)) {
            return [];
        }
        if (!$forceRefresh && isset($requestCache[$detailKey])) {
            return $requestCache[$detailKey];
        }

        $cacheFile = server_load_media_breakdown_cache_file($detailKey);
        if ($forceRefresh) {
            @unlink($cacheFile);
            unset($requestCache[$detailKey]);
        } elseif (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 1800) {
            $raw = @file_get_contents($cacheFile);
            if ($raw !== false && $raw !== '') {
                $cached = json_decode($raw, true);
                if (is_array($cached) && isset($cached['rows']) && is_array($cached['rows'])) {
                    $requestCache[$detailKey] = $cached['rows'];
                    return $requestCache[$detailKey];
                }
            }
        }

        try {
            switch ($detailKey) {
                case 'all':
                    $rawRows = server_load_media_breakdown_uploads_folders(45);
                    break;
                case 'chat':
                    $rawRows = server_load_media_breakdown_chat_users(25);
                    break;
                case 'notes':
                    $rawRows = server_load_media_breakdown_notes_users(20);
                    break;
                case 'email':
                    $rawRows = server_load_media_breakdown_email_accounts(15);
                    break;
                case 'task':
                    $rawRows = server_load_media_breakdown_task_users();
                    break;
                default:
                    $rawRows = [];
            }
        } catch (Throwable $e) {
            $rawRows = [];
        }

        $rows = server_load_media_breakdown_sort_rows(is_array($rawRows) ? $rawRows : []);
        @file_put_contents($cacheFile, json_encode(['rows' => $rows, 'ts' => time()]));
        $requestCache[$detailKey] = $rows;
        return $rows;
    }
}

if (!function_exists('server_load_safe_unlink_under_uploads')) {
    /**
     * Unlink a path only if it resolves under uploads/.
     */
    function server_load_safe_unlink_under_uploads(string $relativeOrAbsolute): bool
    {
        $uploadsRoot = realpath(server_load_uploads_root());
        if ($uploadsRoot === false) {
            return false;
        }
        $uploadsPrefix = rtrim(str_replace('\\', '/', $uploadsRoot), '/') . '/';

        $candidate = str_replace('\\', '/', trim($relativeOrAbsolute));
        if ($candidate === '') {
            return false;
        }
        if (!preg_match('#^(?:[a-z]:)?/#i', $candidate) && strpos($candidate, ':/') === false) {
            $candidate = rtrim(str_replace('\\', '/', server_load_app_root()), '/') . '/' . ltrim($candidate, '/');
        }
        $candidate = str_replace('/', DIRECTORY_SEPARATOR, $candidate);
        $real = realpath($candidate);
        if ($real === false || !is_file($real)) {
            return false;
        }
        $realNorm = str_replace('\\', '/', $real);
        if (strpos($realNorm, $uploadsPrefix) !== 0) {
            return false;
        }
        return @unlink($real);
    }
}

if (!function_exists('server_load_unlink_email_attachment_thumbs')) {
    function server_load_unlink_email_attachment_thumbs(int $attachmentId): void
    {
        if ($attachmentId <= 0) {
            return;
        }
        $dir = server_load_uploads_root() . DIRECTORY_SEPARATOR . 'email-attachments'
            . DIRECTORY_SEPARATOR . 'thumbnails';
        if (!is_dir($dir)) {
            return;
        }
        $pattern = $dir . DIRECTORY_SEPARATOR . 'thumb_*_' . $attachmentId . '_*';
        foreach (glob($pattern) ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}

if (!function_exists('server_load_empty_email_account_attachments')) {
    /**
     * Delete attachments only for one email account (keep messages/threads/bodies).
     *
     * @return array{ok:bool,deleted_count:int,freed_bytes:int,error?:string}
     */
    function server_load_empty_email_account_attachments(int $accountId): array
    {
        global $connect;
        $accountId = (int)$accountId;
        if ($accountId <= 0) {
            return ['ok' => false, 'deleted_count' => 0, 'freed_bytes' => 0, 'error' => 'invalid_account'];
        }
        if (empty($connect)
            || !server_load_table_exists('email_accounts')
            || !server_load_table_exists('email_attachments')
            || !server_load_table_exists('email_messages')
        ) {
            return ['ok' => false, 'deleted_count' => 0, 'freed_bytes' => 0, 'error' => 'tables_unavailable'];
        }

        $check = @$connect->query('SELECT id FROM email_accounts WHERE id = ' . $accountId . ' LIMIT 1');
        if (!$check || $check->num_rows < 1) {
            return ['ok' => false, 'deleted_count' => 0, 'freed_bytes' => 0, 'error' => 'account_not_found'];
        }

        $res = @$connect->query(
            'SELECT a.id, a.file_path, a.file_size
             FROM email_attachments a
             INNER JOIN email_messages m ON m.id = a.message_id
             WHERE m.account_id = ' . $accountId
        );
        if (!$res) {
            return ['ok' => false, 'deleted_count' => 0, 'freed_bytes' => 0, 'error' => 'query_failed'];
        }

        $ids = [];
        $freed = 0;
        while ($row = $res->fetch_assoc()) {
            $attId = (int)($row['id'] ?? 0);
            if ($attId <= 0) {
                continue;
            }
            $ids[] = $attId;
            $freed += max(0, (int)($row['file_size'] ?? 0));
            $rel = trim((string)($row['file_path'] ?? ''));
            if ($rel !== '') {
                server_load_safe_unlink_under_uploads($rel);
            }
            server_load_unlink_email_attachment_thumbs($attId);
        }

        $deletedCount = 0;
        if (!empty($ids)) {
            $idList = implode(',', array_map('intval', $ids));
            $del = @$connect->query(
                'DELETE a FROM email_attachments a
                 INNER JOIN email_messages m ON m.id = a.message_id
                 WHERE m.account_id = ' . $accountId . ' AND a.id IN (' . $idList . ')'
            );
            if ($del) {
                $deletedCount = (int)$connect->affected_rows;
            } else {
                return ['ok' => false, 'deleted_count' => 0, 'freed_bytes' => 0, 'error' => 'delete_failed'];
            }
        }

        @$connect->query(
            "UPDATE email_messages
             SET attachments = NULL
             WHERE account_id = {$accountId}
               AND attachments IS NOT NULL
               AND TRIM(attachments) <> ''"
        );

        if (function_exists('server_load_clear_db_info_cache')) {
            server_load_clear_db_info_cache();
        }

        return [
            'ok' => true,
            'deleted_count' => $deletedCount > 0 ? $deletedCount : count($ids),
            'freed_bytes' => $freed,
        ];
    }
}

if (!function_exists('server_load_get_account_resources')) {
    /**
     * Account-level limits (cPanel / CloudLinux LVE) when available.
     *
     * @return array<string,mixed>
     */
    function server_load_get_account_resources(): array
    {
        $shared = server_load_is_shared_hosting();
        $home = server_load_get_home_dir();
        $uid = function_exists('posix_getuid') ? (int)posix_getuid() : null;
        $lve = server_load_parse_lve_account_row($uid);

        $memLimitBytes = null;
        $memUsedBytes = null;
        $cpuLimitPct = null;
        $cpuUsedPct = null;
        $cpuCoresLimit = null;

        if ($lve !== null) {
            foreach (['LMEMPHY', 'LPMEM'] as $limitKey) {
                $limitKb = server_load_lve_value($lve, $limitKey);
                if ($limitKb !== null && $limitKb > 0) {
                    $memLimitBytes = (int)round($limitKb * 1024);
                    break;
                }
            }
            foreach (['MEMPHY', 'PMEM'] as $usageKey) {
                $usedKb = server_load_lve_value($lve, $usageKey);
                if ($usedKb !== null && $usedKb >= 0) {
                    $memUsedBytes = (int)round($usedKb * 1024);
                    break;
                }
            }

            $cpuLimitRaw = server_load_lve_value($lve, 'LCPU');
            $cpuUsedRaw = server_load_lve_value($lve, 'CPU');
            if ($cpuLimitRaw !== null && $cpuLimitRaw > 0) {
                $cpuLimitPct = $cpuLimitRaw > 1000 ? round($cpuLimitRaw / 100, 2) : $cpuLimitRaw;
                if ($cpuUsedRaw !== null) {
                    $cpuUsedPct = min(100, round(($cpuUsedRaw / max(1, $cpuLimitPct)) * 100, 2));
                }
            }
            $cpuCoresLimit = server_load_lve_value($lve, 'NCPU');
            if ($cpuCoresLimit !== null) {
                $cpuCoresLimit = max(1, (int)$cpuCoresLimit);
            }
        }

        $diskLimitBytes = null;
        $diskUsedBytes = null;
        $diskPath = $home ?: server_load_app_root();
        $cpQuota = server_load_read_cpanel_disk_quota($home);
        if ($cpQuota !== null) {
            $diskLimitBytes = $cpQuota['limit_bytes'];
            $diskUsedBytes = $cpQuota['used_bytes'];
        }

        $reportedTotal = @disk_total_space($diskPath);
        $reportedFree = @disk_free_space($diskPath);
        $partitionLooksReal = $reportedTotal && $reportedTotal > 0 && $reportedTotal <= (500 * 1024 * 1024 * 1024);
        if ($diskLimitBytes === null && $partitionLooksReal) {
            $diskLimitBytes = (int)$reportedTotal;
            if ($reportedFree !== false) {
                $diskUsedBytes = (int)($reportedTotal - $reportedFree);
            }
        }

        if ($shared && $home && ($diskLimitBytes === null || $diskLimitBytes > (500 * 1024 * 1024 * 1024))) {
            $walkUsed = server_load_cached_home_disk_used($home);
            if ($walkUsed !== null) {
                $diskUsedBytes = $walkUsed;
            }
            if ($cpQuota !== null) {
                $diskLimitBytes = $cpQuota['limit_bytes'];
            }
        }

        $memPct = null;
        if ($memLimitBytes !== null && $memLimitBytes > 0 && $memUsedBytes !== null) {
            $memPct = round(($memUsedBytes / $memLimitBytes) * 100, 2);
        }
        $diskPct = null;
        if ($diskLimitBytes !== null && $diskLimitBytes > 0 && $diskUsedBytes !== null) {
            $diskPct = round(($diskUsedBytes / $diskLimitBytes) * 100, 2);
        }

        $hasAccount = $memLimitBytes !== null || $diskLimitBytes !== null || $cpuLimitPct !== null;

        return [
            'mode' => ($shared && $hasAccount) ? 'account' : 'server',
            'shared_hosting' => $shared,
            'home' => $home,
            'mem_limit_bytes' => $memLimitBytes,
            'mem_used_bytes' => $memUsedBytes,
            'mem_pct' => $memPct,
            'disk_limit_bytes' => $diskLimitBytes,
            'disk_used_bytes' => $diskUsedBytes,
            'disk_pct' => $diskPct,
            'cpu_limit_pct' => $cpuLimitPct,
            'cpu_used_pct' => $cpuUsedPct,
            'cpu_cores_limit' => $cpuCoresLimit,
        ];
    }
}

if (!function_exists('server_load_get_db_info')) {
    /**
     * On-disk database size (same as phpMyAdmin "Size" column — not .sql export file size).
     *
     * @return array{name:string,size_bytes:int,table_count:int}|null
     */
    function server_load_get_db_info(): ?array
    {
        static $requestCache = null;
        if ($requestCache !== null) {
            return $requestCache;
        }

        global $connect;
        if (empty($connect)) {
            return null;
        }

        $cacheFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'crm_server_db_info.cache';
        if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
            $raw = @file_get_contents($cacheFile);
            if ($raw !== false && $raw !== '') {
                $cached = json_decode($raw, true);
                if (is_array($cached) && !empty($cached['name']) && isset($cached['size_bytes'])) {
                    $requestCache = [
                        'name' => (string)$cached['name'],
                        'size_bytes' => (int)$cached['size_bytes'],
                        'table_count' => (int)($cached['table_count'] ?? 0),
                    ];
                    return $requestCache;
                }
            }
        }

        $dbName = null;
        $res = @$connect->query('SELECT DATABASE() AS db');
        if ($res && ($row = $res->fetch_assoc()) && !empty($row['db'])) {
            $dbName = (string)$row['db'];
        }
        if (($dbName === null || $dbName === '') && defined('DB_NAME')) {
            $dbName = (string)DB_NAME;
        }
        if ($dbName === '') {
            return null;
        }

        $sql = 'SELECT COALESCE(SUM(data_length + index_length), 0) AS total, COUNT(*) AS table_count
                FROM information_schema.TABLES WHERE table_schema = ?';
        $stmt = $connect->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $dbName);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        $info = [
            'name' => $dbName,
            'size_bytes' => $row ? (int)$row['total'] : 0,
            'table_count' => $row ? (int)$row['table_count'] : 0,
        ];
        @file_put_contents($cacheFile, json_encode($info));
        $requestCache = $info;
        return $info;
    }
}

if (!function_exists('server_load_clear_db_info_cache')) {
    function server_load_clear_db_info_cache(): void
    {
        $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        @unlink($dir . 'crm_server_db_info.cache');
        @unlink($dir . 'crm_server_db_size.cache');
        @unlink($dir . 'crm_server_media_usage.cache');
        @unlink($dir . 'crm_server_media_usage_v2.cache');
        foreach (glob($dir . 'crm_server_media_breakdown_*.cache') ?: [] as $breakdownCache) {
            @unlink($breakdownCache);
        }
    }
}

if (!function_exists('server_load_get_db_size')) {
    function server_load_get_db_size(): ?int
    {
        $info = server_load_get_db_info();
        if (!$info || $info['size_bytes'] <= 0) {
            return null;
        }
        return (int)$info['size_bytes'];
    }
}

if (!function_exists('ensure_server_monitor_tables')) {
    function ensure_server_monitor_tables(): void
    {
        global $connect;
        if (empty($connect)) {
            return;
        }
        $sqlMonitor = "CREATE TABLE IF NOT EXISTS tblserver_monitor_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            created_at DATETIME NOT NULL,
            cpu_usage DECIMAL(5,2) NULL,
            ram_usage DECIMAL(5,2) NULL,
            disk_usage DECIMAL(5,2) NULL,
            load_1 DECIMAL(8,2) NULL,
            load_5 DECIMAL(8,2) NULL,
            load_15 DECIMAL(8,2) NULL,
            network_in BIGINT NULL,
            network_out BIGINT NULL,
            php_memory_usage BIGINT NULL,
            php_memory_peak BIGINT NULL,
            db_size BIGINT NULL,
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $connect->query($sqlMonitor);

        $sqlRequests = "CREATE TABLE IF NOT EXISTS tblserver_request_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            created_at DATETIME NOT NULL,
            method VARCHAR(10) NULL,
            endpoint VARCHAR(255) NULL,
            status_code INT NULL,
            response_time_ms DECIMAL(10,2) NULL,
            memory_usage BIGINT NULL,
            ip_address VARCHAR(100) NULL,
            user_agent TEXT NULL,
            is_error TINYINT(1) DEFAULT 0,
            INDEX idx_created_at (created_at),
            INDEX idx_endpoint_method (endpoint, method),
            INDEX idx_response_time (response_time_ms)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $connect->query($sqlRequests);
    }
}

if (!function_exists('cleanup_old_server_logs')) {
    function cleanup_old_server_logs(): void
    {
        global $connect;
        if (empty($connect)) {
            return;
        }
        $flag = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'crm_server_monitor_cleanup.flag';
        if (is_file($flag) && (time() - filemtime($flag)) < 86400) {
            return;
        }
        $connect->query("DELETE FROM tblserver_monitor_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 60 DAY)");
        $connect->query("DELETE FROM tblserver_request_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 60 DAY)");
        @touch($flag);
    }
}

if (!function_exists('clear_server_request_logs')) {
    /**
     * Remove all heavy/slow request log rows (tblserver_request_logs).
     */
    function clear_server_request_logs(): bool
    {
        global $connect;
        if (empty($connect)) {
            return false;
        }
        ensure_server_monitor_tables();
        $result = $connect->query('DELETE FROM tblserver_request_logs');
        return $result !== false;
    }
}

if (!function_exists('server_load_collect_metrics')) {
    /**
     * @return array<string,mixed>
     */
    function server_load_collect_metrics(bool $sampleCpu = false): array
    {
        static $baseCache = null;

        if ($baseCache === null) {
            $root = server_load_app_root();
            $cpuInfo = server_load_parse_cpuinfo();
            $account = server_load_get_account_resources();
            $cores = max(1, (int)($account['cpu_cores_limit'] ?? $cpuInfo['cores']));

            $ramPct = null;
            if ($account['mode'] === 'account' && $account['mem_pct'] !== null) {
                $ramPct = (float)$account['mem_pct'];
            } else {
                $mem = server_load_read_meminfo();
                if ($mem && !empty($mem['MemTotal']) && isset($mem['MemAvailable'])) {
                    $used = $mem['MemTotal'] - $mem['MemAvailable'];
                    $ramPct = round(($used / $mem['MemTotal']) * 100, 2);
                } elseif ($mem && !empty($mem['MemTotal']) && isset($mem['MemFree'], $mem['Buffers'], $mem['Cached'])) {
                    $free = $mem['MemFree'] + $mem['Buffers'] + $mem['Cached'];
                    $used = $mem['MemTotal'] - $free;
                    $ramPct = round(($used / $mem['MemTotal']) * 100, 2);
                }
            }

            $diskPct = null;
            if ($account['mode'] === 'account' && $account['disk_pct'] !== null) {
                $diskPct = (float)$account['disk_pct'];
            } else {
                $totalDisk = @disk_total_space($root);
                $freeDisk = @disk_free_space($root);
                if ($totalDisk && $freeDisk !== false && $totalDisk > 0) {
                    $diskPct = round((($totalDisk - $freeDisk) / $totalDisk) * 100, 2);
                }
            }

            $load = function_exists('sys_getloadavg') ? @sys_getloadavg() : false;
            $load1 = ($load && isset($load[0])) ? round((float)$load[0], 2) : null;
            $load5 = ($load && isset($load[1])) ? round((float)$load[1], 2) : null;
            $load15 = ($load && isset($load[2])) ? round((float)$load[2], 2) : null;

            $net = server_load_read_network_bytes();
            $uptime = server_load_read_uptime_seconds();

            $baseCache = [
                'account' => $account,
                'cores' => $cores,
                'ram_usage' => $ramPct,
                'disk_usage' => $diskPct,
                'load_1' => $load1,
                'load_5' => $load5,
                'load_15' => $load15,
                'network_in' => $net ? $net['in'] : null,
                'network_out' => $net ? $net['out'] : null,
                'php_memory_usage' => memory_get_usage(true),
                'php_memory_peak' => memory_get_peak_usage(true),
                'db_size' => server_load_get_db_size(),
                'uptime_seconds' => $uptime,
                'cpu_usage' => null,
            ];
        }

        $metrics = $baseCache;
        $account = $metrics['account'];
        $cores = (int)$metrics['cores'];
        $load1 = $metrics['load_1'];

        $cpuPct = $metrics['cpu_usage'];
        if ($account['mode'] === 'account' && $account['cpu_used_pct'] !== null) {
            $cpuPct = (float)$account['cpu_used_pct'];
        } elseif ($sampleCpu) {
            $cpuPct = server_load_sample_cpu_percent();
        }
        if ($cpuPct === null && $load1 !== null) {
            $cpuPct = min(100, round(($load1 / $cores) * 100, 2));
        }

        $metrics['cpu_usage'] = $cpuPct;
        return $metrics;
    }
}

if (!function_exists('save_server_monitor_snapshot')) {
    function save_server_monitor_snapshot(): void
    {
        global $connect;
        if (empty($connect)) {
            return;
        }
        ensure_server_monitor_tables();

        $stmt = $connect->prepare('SELECT MAX(created_at) AS last_at FROM tblserver_monitor_logs');
        if ($stmt) {
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            if (!empty($row['last_at']) && strtotime((string)$row['last_at']) > (time() - 300)) {
                return;
            }
        }

        $metrics = server_load_collect_metrics(true);
        $now = date('Y-m-d H:i:s');

        $sql = 'INSERT INTO tblserver_monitor_logs
            (created_at, cpu_usage, ram_usage, disk_usage, load_1, load_5, load_15,
             network_in, network_out, php_memory_usage, php_memory_peak, db_size)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = $connect->prepare($sql);
        if (!$stmt) {
            return;
        }
        $cpu = $metrics['cpu_usage'] ?? 0.0;
        $ram = $metrics['ram_usage'] ?? 0.0;
        $disk = $metrics['disk_usage'] ?? 0.0;
        $l1 = $metrics['load_1'] ?? 0.0;
        $l5 = $metrics['load_5'] ?? 0.0;
        $l15 = $metrics['load_15'] ?? 0.0;
        $nin = $metrics['network_in'] ?? 0;
        $nout = $metrics['network_out'] ?? 0;
        $phpm = (int)$metrics['php_memory_usage'];
        $phpp = (int)$metrics['php_memory_peak'];
        $dbs = (int)($metrics['db_size'] ?? 0);
        $stmt->bind_param(
            'sddddddiiiii',
            $now,
            $cpu,
            $ram,
            $disk,
            $l1,
            $l5,
            $l15,
            $nin,
            $nout,
            $phpm,
            $phpp,
            $dbs
        );
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('get_server_specs')) {
    function get_server_specs(): array
    {
        $cpu = server_load_parse_cpuinfo();
        $account = server_load_get_account_resources();
        $mem = server_load_read_meminfo();
        $root = server_load_app_root();
        $accountNote = server_load_t('server_load_account_limit_note', 'Account limit (cPanel / CloudLinux)');

        $ramLabel = server_load_t('Not Available');
        $ramLine2 = '';
        if ($account['mode'] === 'account' && !empty($account['mem_limit_bytes'])) {
            $ramLabel = server_load_format_bytes((int)$account['mem_limit_bytes']);
            $ramLine2 = $accountNote;
            if (!empty($account['mem_used_bytes'])) {
                $ramLine2 = sprintf(
                    server_load_t('server_load_account_used_of', '%s used of %s'),
                    server_load_format_bytes((int)$account['mem_used_bytes']),
                    server_load_format_bytes((int)$account['mem_limit_bytes'])
                );
            }
        } elseif ($mem && !empty($mem['MemTotal']) && !server_load_is_shared_hosting()) {
            $ramLabel = server_load_format_bytes($mem['MemTotal']);
        } elseif (server_load_is_shared_hosting()) {
            $ramLabel = server_load_t('server_load_shared_hidden', 'Hidden on shared hosting');
            $ramLine2 = $accountNote;
        }

        $diskLabel = server_load_t('Not Available');
        $diskLine2 = '';
        if ($account['mode'] === 'account' && !empty($account['disk_limit_bytes'])) {
            $diskLabel = server_load_format_bytes((int)$account['disk_limit_bytes']);
            if (!empty($account['disk_used_bytes'])) {
                $diskLine2 = sprintf(
                    server_load_t('server_load_account_used_of', '%s used of %s'),
                    server_load_format_bytes((int)$account['disk_used_bytes']),
                    server_load_format_bytes((int)$account['disk_limit_bytes'])
                );
            } else {
                $diskLine2 = $accountNote;
            }
        } else {
            $totalDisk = @disk_total_space($root);
            if ($totalDisk && $totalDisk <= (500 * 1024 * 1024 * 1024)) {
                $diskLabel = server_load_format_bytes($totalDisk);
            } elseif (server_load_is_shared_hosting()) {
                $diskLabel = server_load_t('server_load_shared_hidden', 'Hidden on shared hosting');
                $diskLine2 = $accountNote;
            }
        }

        $sharedHosting = server_load_is_shared_hosting();
        if ($sharedHosting) {
            $cpuLine1 = server_load_t('server_load_shared_hidden', 'Not shown on shared hosting');
            $cpuLine2 = server_load_t('server_load_processor_shared_note', 'Account CPU limit is managed by cPanel / CloudLinux');
        } else {
            $cpuLine1 = $cpu['model'];
            $displayCores = (int)($account['cpu_cores_limit'] ?? $cpu['cores']);
            if ($displayCores <= 0) {
                $displayCores = 1;
            }
            $cpuLine2 = $displayCores . ' ' . server_load_t($displayCores === 1 ? 'server_load_core' : 'server_load_cores');
            if ($account['mode'] === 'account' && !empty($account['cpu_limit_pct'])) {
                $cpuLine2 .= ' · ' . sprintf(server_load_t('server_load_cpu_limit'), round((float)$account['cpu_limit_pct'], 1) . '%');
            } elseif ($cpu['threads'] > $cpu['cores']) {
                $cpuLine2 .= ' / ' . $cpu['threads'] . ' ' . server_load_t('server_load_threads');
            }
            $ghz = server_load_format_cpu_ghz($cpu['mhz']);
            if ($ghz !== null) {
                $cpuLine2 .= ' ' . sprintf(server_load_t('server_load_cpu_ghz'), $ghz);
            }
            if ($account['mode'] === 'account') {
                $cpuLine2 .= ' · ' . $accountNote;
            }
        }

        $osName = php_uname('s') . ' ' . php_uname('r');
        $kernel = php_uname('v');
        if ($kernel === '') {
            $kernel = php_uname('r');
        }

        $dbInfo = server_load_get_db_info();
        $dbLine1 = server_load_t('Not Available');
        $dbLine2 = '';
        if ($dbInfo && $dbInfo['size_bytes'] > 0) {
            $dbLine1 = server_load_format_bytes((int)$dbInfo['size_bytes']);
            $dbLine2 = sprintf(
                server_load_t('server_load_db_spec_line2', '%d tables · %s on disk (information_schema)'),
                (int)$dbInfo['table_count'],
                server_load_format_bytes((int)$dbInfo['size_bytes'])
            );
        } elseif ($dbInfo) {
            $dbLine1 = '0 B';
        }

        return [
            'processor' => [
                'title' => server_load_t('Processor'),
                'line1' => $cpuLine1,
                'line2' => $cpuLine2,
            ],
            'memory' => [
                'title' => server_load_t('Memory'),
                'line1' => $ramLabel,
                'line2' => $ramLine2,
            ],
            'storage' => [
                'title' => server_load_t('Storage'),
                'line1' => $diskLabel,
                'line2' => $diskLine2,
            ],
            'database' => [
                'title' => server_load_t('Database'),
                'line1' => $dbLine1,
                'line2' => $dbLine2,
            ],
            'network' => [
                'title' => server_load_t('Network'),
                'line1' => server_load_t('Not Available'),
                'line2' => '',
            ],
            'os' => [
                'title' => server_load_t('Operating System'),
                'line1' => $osName !== ' ' ? trim($osName) : server_load_t('Not Available'),
                'line2' => sprintf(server_load_t('server_load_kernel_prefix'), $kernel ?: server_load_t('Not Available')),
            ],
        ];
    }
}

if (!function_exists('server_load_get_last_snapshots')) {
    function server_load_get_last_snapshots(int $limit = 2): array
    {
        global $connect;
        if (empty($connect)) {
            return [];
        }
        $limit = max(1, min(10, $limit));
        $sql = 'SELECT * FROM tblserver_monitor_logs ORDER BY created_at DESC LIMIT ?';
        $stmt = $connect->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('get_current_resource_usage')) {
    function get_current_resource_usage(): array
    {
        $metrics = server_load_collect_metrics(false);
        $cores = (int)$metrics['cores'];
        $snaps = server_load_get_last_snapshots(2);

        if ($metrics['cpu_usage'] === null && count($snaps) >= 2) {
            $newer = $snaps[0];
            $older = $snaps[1];
            if ($newer['cpu_usage'] !== null && $older['cpu_usage'] !== null) {
                $metrics['cpu_usage'] = (float)$newer['cpu_usage'];
            }
        } elseif ($metrics['cpu_usage'] === null && count($snaps) === 1 && $snaps[0]['cpu_usage'] !== null) {
            $metrics['cpu_usage'] = (float)$snaps[0]['cpu_usage'];
        }

        $netRateIn = null;
        $netRateOut = null;
        if (count($snaps) >= 2
            && $snaps[0]['network_in'] !== null
            && $snaps[1]['network_in'] !== null
            && $snaps[0]['network_out'] !== null
            && $snaps[1]['network_out'] !== null
        ) {
            $t0 = strtotime((string)$snaps[1]['created_at']);
            $t1 = strtotime((string)$snaps[0]['created_at']);
            $delta = $t1 - $t0;
            if ($delta > 0) {
                $netRateIn = max(0, ((int)$snaps[0]['network_in'] - (int)$snaps[1]['network_in']) / $delta);
                $netRateOut = max(0, ((int)$snaps[0]['network_out'] - (int)$snaps[1]['network_out']) / $delta);
            }
        }

        $uptimeSec = $metrics['uptime_seconds'];
        $uptimeLabel = $uptimeSec !== null ? server_load_format_uptime($uptimeSec) : server_load_t('Not Available');

        $cpuStatus = server_load_cpu_status($metrics['cpu_usage'] !== null ? (float)$metrics['cpu_usage'] : null);
        $ramStatus = server_load_ram_status($metrics['ram_usage'] !== null ? (float)$metrics['ram_usage'] : null);
        $diskStatus = server_load_disk_status($metrics['disk_usage'] !== null ? (float)$metrics['disk_usage'] : null);
        $loadStatus = server_load_load_status($metrics['load_1'] !== null ? (float)$metrics['load_1'] : null, $cores);

        $netSubtitle = server_load_t('Not Available');
        if ($netRateIn !== null && $netRateOut !== null) {
            $netSubtitle = sprintf(
                server_load_t('server_load_net_rate'),
                server_load_format_bytes($netRateIn),
                server_load_format_bytes($netRateOut)
            );
        } elseif ($metrics['network_in'] !== null) {
            $netSubtitle = sprintf(
                server_load_t('server_load_net_totals'),
                server_load_format_bytes($metrics['network_in']),
                server_load_format_bytes($metrics['network_out'])
            );
        }

        $account = is_array($metrics['account'] ?? null) ? $metrics['account'] : server_load_get_account_resources();
        $dbInfo = server_load_get_db_info();
        $dbSizeBytes = ($dbInfo && $dbInfo['size_bytes'] > 0) ? (int)$dbInfo['size_bytes'] : 0;
        $ramSubtitle = sprintf(server_load_t('server_load_php_memory'), server_load_format_bytes($metrics['php_memory_usage']));
        if ($account['mode'] === 'account' && !empty($account['mem_used_bytes']) && !empty($account['mem_limit_bytes'])) {
            $ramSubtitle = sprintf(
                server_load_t('server_load_ram_subtitle'),
                server_load_format_bytes((int)$account['mem_used_bytes']),
                server_load_format_bytes((int)$account['mem_limit_bytes']),
                server_load_format_bytes($metrics['php_memory_usage'])
            );
        } else {
            $mem = server_load_read_meminfo();
            if ($mem && !empty($mem['MemTotal']) && !server_load_is_shared_hosting()) {
                $available = $mem['MemAvailable'] ?? (($mem['MemFree'] ?? 0) + ($mem['Buffers'] ?? 0) + ($mem['Cached'] ?? 0));
                $usedRam = max(0, $mem['MemTotal'] - $available);
                $ramSubtitle = sprintf(
                    server_load_t('server_load_ram_subtitle'),
                    server_load_format_bytes($usedRam),
                    server_load_format_bytes($mem['MemTotal']),
                    server_load_format_bytes($metrics['php_memory_usage'])
                );
            } elseif (server_load_is_shared_hosting()) {
                $ramSubtitle = server_load_t('server_load_account_limit_note', 'Account limit (cPanel / CloudLinux)');
            }
        }

        $diskSubtitle = server_load_t('Database size unavailable');
        if ($dbSizeBytes > 0) {
            $diskSubtitle = sprintf(
                server_load_t('server_load_db_prefix', 'Database size: %s'),
                server_load_format_bytes($dbSizeBytes)
            );
        }
        if ($account['mode'] === 'account' && !empty($account['disk_used_bytes']) && !empty($account['disk_limit_bytes'])) {
            $diskSubtitle = sprintf(
                server_load_t('server_load_disk_subtitle_plain'),
                server_load_format_bytes((int)$account['disk_used_bytes']),
                server_load_format_bytes((int)$account['disk_limit_bytes'])
            );
            if ($dbSizeBytes > 0) {
                $diskSubtitle = sprintf(
                    server_load_t('server_load_disk_subtitle'),
                    server_load_format_bytes((int)$account['disk_used_bytes']),
                    server_load_format_bytes((int)$account['disk_limit_bytes']),
                    server_load_format_bytes($dbSizeBytes)
                );
            }
        } else {
            $root = server_load_app_root();
            $totalDisk = @disk_total_space($root);
            $freeDisk = @disk_free_space($root);
            if ($totalDisk && $freeDisk !== false && $totalDisk > 0 && $totalDisk <= (500 * 1024 * 1024 * 1024)) {
                $usedDisk = $totalDisk - $freeDisk;
                $diskSubtitle = sprintf(
                    server_load_t('server_load_disk_subtitle_plain'),
                    server_load_format_bytes($usedDisk),
                    server_load_format_bytes($totalDisk)
                );
                if ($dbSizeBytes > 0) {
                    $diskSubtitle = sprintf(
                        server_load_t('server_load_disk_subtitle'),
                        server_load_format_bytes($usedDisk),
                        server_load_format_bytes($totalDisk),
                        server_load_format_bytes($dbSizeBytes)
                    );
                }
            }
        }

        $networkStatus = ['level' => 'good', 'label' => server_load_t('Active'), 'color' => server_load_status_color('good')];
        $uptimeStatus = $uptimeSec !== null
            ? ['level' => 'good', 'label' => server_load_t('Online'), 'color' => server_load_status_color('good')]
            : ['level' => 'unknown', 'label' => server_load_t('Not Available'), 'color' => '#888'];

        $loadProgressPct = null;
        if ($metrics['load_1'] !== null && $cores > 0) {
            $loadProgressPct = min(100, ((float)$metrics['load_1'] / $cores) * 100);
        }

        $cpuSubtitle = server_load_t('Current usage');
        if ($account['mode'] === 'account' && !empty($account['cpu_limit_pct'])) {
            $cpuSubtitle = sprintf(
                server_load_t('server_load_cpu_account_subtitle', 'Limit %s (cPanel / LVE)'),
                round((float)$account['cpu_limit_pct'], 1) . '%'
            );
        }

        return [
            'cpu' => [
                'value' => $metrics['cpu_usage'] !== null ? round($metrics['cpu_usage'], 1) . '%' : '0%',
                'subtitle' => $cpuSubtitle,
                'status' => $cpuStatus,
                'progress' => server_load_card_progress($metrics['cpu_usage'] !== null ? (float)$metrics['cpu_usage'] : 0.0, $cpuStatus),
            ],
            'ram' => [
                'value' => $metrics['ram_usage'] !== null ? round($metrics['ram_usage'], 1) . '%' : '0%',
                'subtitle' => $ramSubtitle,
                'status' => $ramStatus,
                'progress' => server_load_card_progress($metrics['ram_usage'] !== null ? (float)$metrics['ram_usage'] : 0.0, $ramStatus),
            ],
            'disk' => [
                'value' => $metrics['disk_usage'] !== null ? round($metrics['disk_usage'], 1) . '%' : '0%',
                'subtitle' => $diskSubtitle,
                'status' => $diskStatus,
                'progress' => server_load_card_progress($metrics['disk_usage'] !== null ? (float)$metrics['disk_usage'] : 0.0, $diskStatus),
            ],
            'load' => [
                'value' => $metrics['load_1'] !== null ? (string)$metrics['load_1'] : '0',
                'subtitle' => ($metrics['load_5'] !== null && $metrics['load_15'] !== null)
                    ? sprintf(server_load_t('server_load_load_windows'), $metrics['load_5'], $metrics['load_15'])
                    : server_load_t('Load average'),
                'status' => $loadStatus,
                'progress' => server_load_card_progress($loadProgressPct ?? 0.0, $loadStatus),
            ],
            'network' => [
                'value' => ($netRateIn !== null) ? server_load_format_bytes($netRateIn) . '/s' : '0 B/s',
                'subtitle' => $netSubtitle,
                'status' => $networkStatus,
                'progress' => null,
            ],
            'uptime' => [
                'value' => $uptimeSec !== null ? $uptimeLabel : '0',
                'subtitle' => server_load_t('Since last reboot'),
                'status' => $uptimeStatus,
                'progress' => null,
            ],
            'raw' => $metrics,
        ];
    }
}

if (!function_exists('server_load_range_previous_bounds')) {
    /**
     * @return array{current_since:string,prev_since:string,prev_until:string}
     */
    function server_load_range_previous_bounds(string $range): array
    {
        $range = server_load_parse_range($range);
        $currentSince = server_load_range_to_datetime($range);
        switch ($range) {
            case '48h':
                $prevSince = date('Y-m-d H:i:s', strtotime($currentSince . ' -48 hours'));
                break;
            case '7d':
                $prevSince = date('Y-m-d H:i:s', strtotime($currentSince . ' -7 days'));
                break;
            case '30d':
                $prevSince = date('Y-m-d H:i:s', strtotime($currentSince . ' -30 days'));
                break;
            case '60d':
                $prevSince = date('Y-m-d H:i:s', strtotime($currentSince . ' -60 days'));
                break;
            case '24h':
            default:
                $prevSince = date('Y-m-d H:i:s', strtotime($currentSince . ' -24 hours'));
                break;
        }

        return [
            'current_since' => $currentSince,
            'prev_since' => $prevSince,
            'prev_until' => $currentSince,
        ];
    }
}

if (!function_exists('server_load_fetch_monitor_rows')) {
    function server_load_fetch_monitor_rows(string $since, ?string $until = null): array
    {
        global $connect;
        if (empty($connect)) {
            return [];
        }

        $sql = 'SELECT created_at, cpu_usage, ram_usage, disk_usage, network_in, network_out
                FROM tblserver_monitor_logs
                WHERE created_at >= ?';
        if ($until !== null && $until !== '') {
            $sql .= ' AND created_at < ?';
        }
        $sql .= ' ORDER BY created_at ASC';

        $stmt = $connect->prepare($sql);
        if (!$stmt) {
            return [];
        }

        if ($until !== null && $until !== '') {
            $stmt->bind_param('ss', $since, $until);
        } else {
            $stmt->bind_param('s', $since);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        $stmt->close();

        return $rows;
    }
}

if (!function_exists('server_load_avg_numeric_array')) {
    function server_load_avg_numeric_array(array $values): ?float
    {
        $nums = [];
        foreach ($values as $value) {
            if ($value !== null && $value !== '' && is_numeric($value)) {
                $nums[] = (float)$value;
            }
        }
        if ($nums === []) {
            return null;
        }

        return round(array_sum($nums) / count($nums), 1);
    }
}

if (!function_exists('server_load_bucket_monitor_rows')) {
    /**
     * @return array{cpu:array,ram:array,disk:array,network_in:array,network_out:array}
     */
    function server_load_bucket_monitor_rows(array $rows, string $range): array
    {
        $empty = [
            'cpu' => [],
            'ram' => [],
            'disk' => [],
            'network_in' => [],
            'network_out' => [],
        ];
        if ($rows === []) {
            return $empty;
        }

        $range = server_load_parse_range($range);
        $bucketMinutes = 5;
        if ($range === '7d') {
            $bucketMinutes = 60;
        } elseif (in_array($range, ['30d', '60d'], true)) {
            $bucketMinutes = 1440;
        } elseif ($range === '48h') {
            $bucketMinutes = 15;
        }

        $buckets = [];
        foreach ($rows as $row) {
            $ts = strtotime((string)$row['created_at']);
            $key = (int)floor($ts / ($bucketMinutes * 60));
            if (!isset($buckets[$key])) {
                $buckets[$key] = [
                    'cpu' => [],
                    'ram' => [],
                    'disk' => [],
                    'net_in' => [],
                    'net_out' => [],
                ];
            }
            if ($row['cpu_usage'] !== null) {
                $buckets[$key]['cpu'][] = (float)$row['cpu_usage'];
            }
            if ($row['ram_usage'] !== null) {
                $buckets[$key]['ram'][] = (float)$row['ram_usage'];
            }
            if ($row['disk_usage'] !== null) {
                $buckets[$key]['disk'][] = (float)$row['disk_usage'];
            }
            if ($row['network_in'] !== null) {
                $buckets[$key]['net_in'][] = (int)$row['network_in'];
            }
            if ($row['network_out'] !== null) {
                $buckets[$key]['net_out'][] = (int)$row['network_out'];
            }
        }

        $cpu = [];
        $ram = [];
        $disk = [];
        $netIn = [];
        $netOut = [];
        $prevIn = null;
        $prevOut = null;

        foreach ($buckets as $bucket) {
            $cpu[] = !empty($bucket['cpu']) ? round(array_sum($bucket['cpu']) / count($bucket['cpu']), 2) : null;
            $ram[] = !empty($bucket['ram']) ? round(array_sum($bucket['ram']) / count($bucket['ram']), 2) : null;
            $disk[] = !empty($bucket['disk']) ? round(array_sum($bucket['disk']) / count($bucket['disk']), 2) : null;

            $avgIn = !empty($bucket['net_in']) ? (int)round(array_sum($bucket['net_in']) / count($bucket['net_in'])) : null;
            $avgOut = !empty($bucket['net_out']) ? (int)round(array_sum($bucket['net_out']) / count($bucket['net_out'])) : null;
            if ($avgIn !== null && $prevIn !== null) {
                $netIn[] = max(0, $avgIn - $prevIn);
            } else {
                $netIn[] = 0;
            }
            if ($avgOut !== null && $prevOut !== null) {
                $netOut[] = max(0, $avgOut - $prevOut);
            } else {
                $netOut[] = 0;
            }
            $prevIn = $avgIn;
            $prevOut = $avgOut;
        }

        return [
            'cpu' => $cpu,
            'ram' => $ram,
            'disk' => $disk,
            'network_in' => $netIn,
            'network_out' => $netOut,
        ];
    }
}

if (!function_exists('server_load_avg_network_chart_series')) {
    function server_load_avg_network_chart_series(array $chartSeries): ?float
    {
        $in = $chartSeries['network_in'] ?? [];
        $out = $chartSeries['network_out'] ?? [];
        $totals = [];
        $len = max(count($in), count($out));
        for ($i = 0; $i < $len; $i++) {
            $totals[] = (float)($in[$i] ?? 0) + (float)($out[$i] ?? 0);
        }

        return server_load_avg_numeric_array($totals);
    }
}

if (!function_exists('server_load_chart_early_baseline')) {
    function server_load_chart_early_baseline(array $values): ?float
    {
        $nums = [];
        foreach ($values as $value) {
            if ($value !== null && $value !== '' && is_numeric($value)) {
                $nums[] = (float)$value;
            }
        }
        if ($nums === []) {
            return null;
        }
        if (count($nums) === 1) {
            return round($nums[0], 1);
        }

        $take = max(1, (int)ceil(count($nums) * 0.25));
        $early = array_slice($nums, 0, $take);

        return round(array_sum($early) / count($early), 1);
    }
}

if (!function_exists('server_load_chart_early_baseline_network')) {
    function server_load_chart_early_baseline_network(array $chartData): ?float
    {
        $in = $chartData['network_in'] ?? [];
        $out = $chartData['network_out'] ?? [];
        $totals = [];
        $len = max(count($in), count($out));
        for ($i = 0; $i < $len; $i++) {
            $totals[] = (float)($in[$i] ?? 0) + (float)($out[$i] ?? 0);
        }

        return server_load_chart_early_baseline($totals);
    }
}

if (!function_exists('server_load_compare_chart_metric')) {
    /**
     * @return array{has_compare:bool,direction:string,percent:?float}
     */
    function server_load_compare_chart_metric(?float $current, ?float $previous): array
    {
        if ($current === null || $previous === null) {
            return ['has_compare' => false, 'direction' => 'none', 'percent' => null];
        }

        if ($previous <= 0 && $current <= 0) {
            return ['has_compare' => true, 'direction' => 'flat', 'percent' => 0.0];
        }
        if ($previous <= 0 && $current > 0) {
            return ['has_compare' => true, 'direction' => 'up', 'percent' => 100.0];
        }
        if ($previous > 0 && $current <= 0) {
            return ['has_compare' => true, 'direction' => 'down', 'percent' => 100.0];
        }

        $diff = $current - $previous;
        if (abs($diff) < 0.05) {
            return ['has_compare' => true, 'direction' => 'flat', 'percent' => 0.0];
        }

        return [
            'has_compare' => true,
            'direction' => $diff > 0 ? 'up' : 'down',
            'percent' => round(abs(($diff / $previous) * 100), 1),
        ];
    }
}

if (!function_exists('server_load_vs_previous_label')) {
    function server_load_vs_previous_label(string $range): string
    {
        switch (server_load_parse_range($range)) {
            case '48h':
                return server_load_t('server_load_vs_previous_48h', 'vs previous 48 hours');
            case '7d':
                return server_load_t('server_load_vs_previous_7d', 'vs previous 7 days');
            case '30d':
                return server_load_t('server_load_vs_previous_30d', 'vs previous 30 days');
            case '60d':
                return server_load_t('server_load_vs_previous_60d', 'vs previous 60 days');
            case '24h':
            default:
                return server_load_t('server_load_vs_previous_24h', 'vs previous 24 hours');
        }
    }
}

if (!function_exists('server_load_format_chart_summary_main')) {
    function server_load_format_chart_summary_main(string $metric, ?float $value, string $suffix = '%'): string
    {
        if ($value === null) {
            return '—';
        }
        if ($metric === 'network') {
            return server_load_format_bytes((int)round($value));
        }
        if (fmod($value, 1.0) === 0.0) {
            return (string)(int)$value . $suffix;
        }

        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.') . $suffix;
    }
}

if (!function_exists('server_load_summary_arrow_svgs')) {
    function server_load_summary_arrow_svgs(): array
    {
        return [
            'up' => function_exists('ts_icon') ? ts_icon('arrow-up-right') : '',
            'down' => function_exists('ts_icon') ? ts_icon('arrow-down-right') : '',
            'flat' => function_exists('ts_icon') ? ts_icon('arrow-up') : '',
        ];
    }
}

if (!function_exists('server_load_render_shared_hosting_notice_html')) {
    function server_load_render_shared_hosting_notice_html(): string
    {
        if (!server_load_is_shared_hosting()) {
            return '';
        }

        $text = htmlspecialchars(
            server_load_t(
                'server_load_shared_notice',
                'Shared hosting detected. CPU, memory, storage, and network details may be hidden by your hosting provider. For complete server monitoring, use a VPS or dedicated server.'
            ),
            ENT_QUOTES,
            'UTF-8'
        );
        $icon = '' . ts_icon('info') . '';

        return '<div class="server-load-shared-notice" role="note">'
            . '<span class="server-load-shared-notice-icon">' . $icon . '</span>'
            . '<span class="server-load-shared-notice-text">' . $text . '</span>'
            . '</div>';
    }
}

if (!function_exists('server_load_render_chart_summary_html')) {
    function server_load_render_chart_summary_html(array $summary): string
    {
        $main = htmlspecialchars((string)($summary['main'] ?? '—'), ENT_QUOTES, 'UTF-8');
        $vs = htmlspecialchars((string)($summary['vs_label'] ?? ''), ENT_QUOTES, 'UTF-8');
        $arrows = server_load_summary_arrow_svgs();

        $arrowClass = 'task-reports-bar-summary-arrow';
        $pctClass = 'task-reports-bar-summary-pct';
        $arrowSvg = '';
        $pctText = '';
        $sepStyle = ' style="display:none"';

        $showMeta = !empty($summary['has_compare']);
        if ($showMeta) {
            $sepStyle = '';
            $direction = (string)($summary['direction'] ?? 'none');
            if ($direction === 'flat') {
                $arrowClass .= ' is-flat';
                $pctClass .= ' is-flat';
                $arrowSvg = $arrows['flat'];
                $pctText = htmlspecialchars(server_load_t('task_reports_no_change', 'No change'), ENT_QUOTES, 'UTF-8');
            } elseif ($direction === 'up') {
                $arrowClass .= ' is-up';
                $pctClass .= ' is-up';
                $arrowSvg = $arrows['up'];
                $pctText = htmlspecialchars((string)($summary['percent'] ?? 0) . '%', ENT_QUOTES, 'UTF-8');
            } elseif ($direction === 'down') {
                $arrowClass .= ' is-down';
                $pctClass .= ' is-down';
                $arrowSvg = $arrows['down'];
                $pctText = htmlspecialchars((string)($summary['percent'] ?? 0) . '%', ENT_QUOTES, 'UTF-8');
            }
        } else {
            $vs = '';
        }

        return '<div class="task-reports-bar-summary">'
            . '<div class="task-reports-bar-summary-main">' . $main . '</div>'
            . ($showMeta
                ? '<div class="task-reports-bar-summary-meta">'
                    . '<span class="' . $arrowClass . '">' . $arrowSvg . '</span>'
                    . '<span class="' . $pctClass . '">' . $pctText . '</span>'
                    . '<span class="task-reports-bar-summary-sep"' . $sepStyle . ' aria-hidden="true">•</span>'
                    . '<span class="task-reports-bar-summary-vs">' . $vs . '</span>'
                    . '</div>'
                : '')
            . '</div>';
    }
}

if (!function_exists('get_server_load_chart_summaries')) {
    function get_server_load_chart_summaries(string $range, ?array $currentChartData = null): array
    {
        $range = server_load_parse_range($range);
        if ($currentChartData === null) {
            $currentChartData = get_monitor_history($range);
        }

        $bounds = server_load_range_previous_bounds($range);
        $prevRows = server_load_fetch_monitor_rows($bounds['prev_since'], $bounds['prev_until']);
        $prevBuckets = server_load_bucket_monitor_rows($prevRows, $range);

        $current = [
            'cpu' => server_load_avg_numeric_array($currentChartData['cpu'] ?? []),
            'ram' => server_load_avg_numeric_array($currentChartData['ram'] ?? []),
            'disk' => server_load_avg_numeric_array($currentChartData['disk'] ?? []),
            'network' => server_load_avg_network_chart_series($currentChartData),
        ];
        $previous = [
            'cpu' => server_load_avg_numeric_array($prevBuckets['cpu'] ?? []),
            'ram' => server_load_avg_numeric_array($prevBuckets['ram'] ?? []),
            'disk' => server_load_avg_numeric_array($prevBuckets['disk'] ?? []),
            'network' => server_load_avg_network_chart_series($prevBuckets),
        ];

        $vsPreviousLabel = server_load_vs_previous_label($range);
        $vsEarlierLabel = server_load_t('server_load_vs_earlier_in_period', 'vs earlier in period');
        $summaries = [];
        foreach (['cpu' => '%', 'ram' => '%', 'disk' => '%', 'network' => ''] as $metric => $suffix) {
            $prevValue = $previous[$metric];
            $usedEarlierFallback = false;
            if ($prevValue === null && $current[$metric] !== null) {
                if ($metric === 'network') {
                    $prevValue = server_load_chart_early_baseline_network($currentChartData);
                } else {
                    $prevValue = server_load_chart_early_baseline($currentChartData[$metric] ?? []);
                }
                if ($prevValue !== null) {
                    $usedEarlierFallback = true;
                }
            }

            $cmp = server_load_compare_chart_metric($current[$metric], $prevValue);
            $summaries[$metric] = [
                'main' => server_load_format_chart_summary_main($metric, $current[$metric], $suffix),
                'has_compare' => $cmp['has_compare'],
                'direction' => $cmp['direction'],
                'percent' => $cmp['percent'],
                'vs_label' => $usedEarlierFallback ? $vsEarlierLabel : $vsPreviousLabel,
            ];
        }

        return $summaries;
    }
}

if (!function_exists('get_monitor_history')) {
    function get_monitor_history(string $range): array
    {
        global $connect;
        $empty = [
            'labels' => [],
            'cpu' => [],
            'ram' => [],
            'disk' => [],
            'network_in' => [],
            'network_out' => [],
        ];
        if (empty($connect)) {
            return $empty;
        }

        $range = server_load_parse_range($range);
        $since = server_load_range_to_datetime($range);

        $sql = 'SELECT created_at, cpu_usage, ram_usage, disk_usage, network_in, network_out
                FROM tblserver_monitor_logs
                WHERE created_at >= ?
                ORDER BY created_at ASC';
        $stmt = $connect->prepare($sql);
        if (!$stmt) {
            return $empty;
        }
        $stmt->bind_param('s', $since);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        $stmt->close();

        if (empty($rows)) {
            return $empty;
        }

        $bucketMinutes = 5;
        if ($range === '7d') {
            $bucketMinutes = 60;
        } elseif (in_array($range, ['30d', '60d'], true)) {
            $bucketMinutes = 1440;
        } elseif ($range === '48h') {
            $bucketMinutes = 15;
        }

        $buckets = [];
        foreach ($rows as $row) {
            $ts = strtotime((string)$row['created_at']);
            $key = (int)floor($ts / ($bucketMinutes * 60));
            if (!isset($buckets[$key])) {
                $buckets[$key] = [
                    'label' => date($bucketMinutes >= 1440 ? 'M j' : ($bucketMinutes >= 60 ? 'M j H:00' : 'H:i'), $ts),
                    'cpu' => [],
                    'ram' => [],
                    'disk' => [],
                    'net_in' => [],
                    'net_out' => [],
                ];
            }
            if ($row['cpu_usage'] !== null) {
                $buckets[$key]['cpu'][] = (float)$row['cpu_usage'];
            }
            if ($row['ram_usage'] !== null) {
                $buckets[$key]['ram'][] = (float)$row['ram_usage'];
            }
            if ($row['disk_usage'] !== null) {
                $buckets[$key]['disk'][] = (float)$row['disk_usage'];
            }
            if ($row['network_in'] !== null) {
                $buckets[$key]['net_in'][] = (int)$row['network_in'];
            }
            if ($row['network_out'] !== null) {
                $buckets[$key]['net_out'][] = (int)$row['network_out'];
            }
        }

        $labels = [];
        $cpu = [];
        $ram = [];
        $disk = [];
        $netIn = [];
        $netOut = [];
        $prevIn = null;
        $prevOut = null;

        foreach ($buckets as $bucket) {
            $labels[] = $bucket['label'];
            $cpu[] = !empty($bucket['cpu']) ? round(array_sum($bucket['cpu']) / count($bucket['cpu']), 2) : null;
            $ram[] = !empty($bucket['ram']) ? round(array_sum($bucket['ram']) / count($bucket['ram']), 2) : null;
            $disk[] = !empty($bucket['disk']) ? round(array_sum($bucket['disk']) / count($bucket['disk']), 2) : null;

            $avgIn = !empty($bucket['net_in']) ? (int)round(array_sum($bucket['net_in']) / count($bucket['net_in'])) : null;
            $avgOut = !empty($bucket['net_out']) ? (int)round(array_sum($bucket['net_out']) / count($bucket['net_out'])) : null;
            if ($avgIn !== null && $prevIn !== null) {
                $netIn[] = max(0, $avgIn - $prevIn);
            } else {
                $netIn[] = 0;
            }
            if ($avgOut !== null && $prevOut !== null) {
                $netOut[] = max(0, $avgOut - $prevOut);
            } else {
                $netOut[] = 0;
            }
            $prevIn = $avgIn;
            $prevOut = $avgOut;
        }

        return [
            'labels' => $labels,
            'cpu' => $cpu,
            'ram' => $ram,
            'disk' => $disk,
            'network_in' => $netIn,
            'network_out' => $netOut,
        ];
    }
}

if (!function_exists('get_heavy_endpoints')) {
    function get_heavy_endpoints(string $range): array
    {
        global $connect;
        if (empty($connect)) {
            return [];
        }

        $range = server_load_parse_range($range);
        $since = server_load_range_to_datetime($range);

        $sql = 'SELECT method, endpoint,
                    COUNT(*) AS slow_count,
                    AVG(response_time_ms) AS avg_response_ms,
                    MAX(response_time_ms) AS max_response_ms,
                    MAX(created_at) AS last_seen
                FROM tblserver_request_logs
                WHERE created_at >= ? AND response_time_ms >= 500
                GROUP BY method, endpoint
                ORDER BY avg_response_ms DESC, slow_count DESC
                LIMIT 50';
        $stmt = $connect->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('s', $since);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        $rank = 1;
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = [
                    'rank' => $rank++,
                    'method' => strtoupper((string)$row['method']),
                    'endpoint' => (string)$row['endpoint'],
                    'slow_count' => number_format((int)$row['slow_count']),
                    'avg_response_ms' => round((float)$row['avg_response_ms']) . 'ms',
                    'max_response_ms' => round((float)$row['max_response_ms']) . 'ms',
                    'last_seen' => date('M j, H:i', strtotime((string)$row['last_seen'])),
                    'badge_class' => server_load_method_badge_class((string)$row['method']),
                ];
            }
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('get_top_api_requests')) {
    function get_top_api_requests(string $period = '1 hour'): array
    {
        global $connect;
        if (empty($connect)) {
            return [];
        }

        $period = trim($period);
        $sinceCurrent = date('Y-m-d H:i:s', strtotime('-' . $period));
        $sincePrev = date('Y-m-d H:i:s', strtotime('-' . $period . ' -' . $period));

        $sql = 'SELECT method, endpoint,
                    COUNT(*) AS request_count,
                    AVG(response_time_ms) AS avg_response_ms,
                    SUM(is_error) AS error_count
                FROM tblserver_request_logs
                WHERE created_at >= ?
                GROUP BY method, endpoint
                ORDER BY request_count DESC
                LIMIT 20';
        $stmt = $connect->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('s', $sinceCurrent);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        $rank = 1;
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $count = (int)$row['request_count'];
                $errors = (int)$row['error_count'];
                $errorRate = $count > 0 ? round(($errors / $count) * 100, 2) : 0;

                $trend = '→ 0%';
                $trendClass = 'is-flat';
                $prevCount = server_load_prev_request_count(
                    (string)$row['method'],
                    (string)$row['endpoint'],
                    $sincePrev,
                    $sinceCurrent
                );
                if ($prevCount > 0) {
                    $change = round((($count - $prevCount) / $prevCount) * 100, 1);
                    if ($change > 0) {
                        $trend = '↑ ' . abs($change) . '%';
                        $trendClass = 'is-up';
                    } elseif ($change < 0) {
                        $trend = '↓ ' . abs($change) . '%';
                        $trendClass = 'is-down';
                    }
                } elseif ($count > 0) {
                    $trend = '↑ 100%';
                    $trendClass = 'is-up';
                }

                $rows[] = [
                    'rank' => $rank++,
                    'method' => strtoupper((string)$row['method']),
                    'endpoint' => (string)$row['endpoint'],
                    'request_count' => number_format($count),
                    'avg_response_ms' => round((float)$row['avg_response_ms']) . 'ms',
                    'error_rate' => $errorRate . '%',
                    'trend' => $trend,
                    'trend_class' => $trendClass,
                    'badge_class' => server_load_method_badge_class((string)$row['method']),
                ];
            }
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('server_load_prev_request_count')) {
    function server_load_prev_request_count(string $method, string $endpoint, string $sincePrev, string $sinceCurrent): int
    {
        global $connect;
        $sql = 'SELECT COUNT(*) AS c FROM tblserver_request_logs
                WHERE method = ? AND endpoint = ? AND created_at >= ? AND created_at < ?';
        $stmt = $connect->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('ssss', $method, $endpoint, $sincePrev, $sinceCurrent);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row ? (int)$row['c'] : 0;
    }
}

if (!function_exists('get_slow_requests')) {
    function get_slow_requests(string $range): array
    {
        global $connect;
        if (empty($connect)) {
            return [];
        }
        $range = server_load_parse_range($range);
        $since = server_load_range_to_datetime($range);

        $sql = 'SELECT created_at, method, endpoint, response_time_ms, memory_usage, status_code, ip_address
                FROM tblserver_request_logs
                WHERE created_at >= ? AND response_time_ms >= 500
                ORDER BY response_time_ms DESC
                LIMIT 50';
        $stmt = $connect->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('s', $since);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $ms = (float)$row['response_time_ms'];
                $level = 'normal';
                if ($ms >= 3000) {
                    $level = 'danger';
                } elseif ($ms >= 1000) {
                    $level = 'warning';
                }
                $rows[] = [
                    'time' => date('M j, H:i:s', strtotime((string)$row['created_at'])),
                    'endpoint' => (string)$row['endpoint'],
                    'method' => strtoupper((string)$row['method']),
                    'response_time_ms' => round($ms) . 'ms',
                    'memory_usage' => server_load_format_bytes((int)$row['memory_usage']),
                    'status_code' => (int)$row['status_code'],
                    'ip_address' => (string)$row['ip_address'],
                    'level' => $level,
                    'badge_class' => server_load_method_badge_class((string)$row['method']),
                ];
            }
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('log_server_request')) {
    function log_server_request(
        string $method,
        string $endpoint,
        int $status_code,
        float $response_time_ms,
        int $memory_usage,
        string $ip_address,
        string $user_agent
    ): bool {
        global $connect;
        if (empty($connect)) {
            return false;
        }
        ensure_server_monitor_tables();

        $method = strtoupper(substr(trim($method), 0, 10));
        $endpoint = substr(trim($endpoint), 0, 255);
        $ip_address = substr(trim($ip_address), 0, 100);
        $is_error = ($status_code >= 400) ? 1 : 0;
        $now = date('Y-m-d H:i:s');

        $sql = 'INSERT INTO tblserver_request_logs
            (created_at, method, endpoint, status_code, response_time_ms, memory_usage, ip_address, user_agent, is_error)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = $connect->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param(
            'sssidissi',
            $now,
            $method,
            $endpoint,
            $status_code,
            $response_time_ms,
            $memory_usage,
            $ip_address,
            $user_agent,
            $is_error
        );
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }
}

if (!function_exists('server_load_register_slow_request_logger')) {
    function server_load_register_slow_request_logger(): void
    {
        if (PHP_SAPI === 'cli' || defined('SERVER_LOAD_LOGGER_REGISTERED')) {
            return;
        }
        define('SERVER_LOAD_LOGGER_REGISTERED', true);

        register_shutdown_function(static function (): void {
            if (empty($GLOBALS['connect'])) {
                return;
            }

            $uri = $_SERVER['REQUEST_URI'] ?? '/';
            if (preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff2?|ttf|map|webp)(\?|$)/i', $uri)) {
                return;
            }

            $path = parse_url($uri, PHP_URL_PATH) ?: '/';
            if (preg_match('#/admin/server-load\.php$#i', $path)) {
                return;
            }

            $start = defined('SERVER_LOAD_REQUEST_START') ? SERVER_LOAD_REQUEST_START : $_SERVER['REQUEST_TIME_FLOAT'];
            $elapsedMs = (microtime(true) - (float)$start) * 1000;
            $status = (int)(http_response_code() ?: 200);

            if ($elapsedMs < 500 && $status < 500) {
                return;
            }

            log_server_request(
                $_SERVER['REQUEST_METHOD'] ?? 'GET',
                $path,
                $status,
                round($elapsedMs, 2),
                (int)memory_get_peak_usage(true),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            );
        });
    }
}
