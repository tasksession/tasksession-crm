<?php
/**
 * Post-install setup guide for Super Admin (user id = 1) only.
 * Enabled only when settings.setup_guide_pending = 1 (fresh install).
 */

if (!function_exists('setup_guide_steps')) {
    /**
     * @return array<string, array{key:string,label:string,url:string}>
     */
    function setup_guide_steps(): array
    {
        return array(
            'smtp' => array(
                'key' => 'smtp',
                'label' => 'SMTP setup',
                'url' => 'admin/smtp-setup.php',
            ),
            'cron' => array(
                'key' => 'cron',
                'label' => 'Cron setup',
                'url' => 'admin/cron.php',
            ),
            'general' => array(
                'key' => 'general',
                'label' => 'General settings',
                'url' => 'admin/system-settings.php',
            ),
            'logo' => array(
                'key' => 'logo',
                'label' => 'Logo & branding',
                'url' => 'admin/logo-settings.php',
            ),
            'email_notifications' => array(
                'key' => 'email_notifications',
                'label' => 'Email notifications',
                'url' => 'admin/email-setting.php',
            ),
            'roles' => array(
                'key' => 'roles',
                'label' => 'Roles & permissions',
                'url' => 'admin/roles.php',
            ),
        );
    }
}

if (!function_exists('setup_guide_is_super_admin')) {
    function setup_guide_is_super_admin(): bool
    {
        global $session;
        $uid = 0;
        if (isset($session) && is_object($session) && isset($session->userId)) {
            $uid = (int) $session->userId;
        } elseif (isset($_SESSION['userId'])) {
            $uid = (int) $_SESSION['userId'];
        }
        $status = isset($_SESSION['accountStatus']) ? (int) $_SESSION['accountStatus'] : 0;
        return $uid === 1 && $status === 1;
    }
}

if (!function_exists('setup_guide_ensure_schema')) {
    function setup_guide_ensure_schema(): void
    {
        global $database, $connect;
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $mysqli = null;
        if (isset($connect) && $connect instanceof mysqli) {
            $mysqli = $connect;
        } elseif (isset($database) && is_object($database) && isset($database->connection) && $database->connection instanceof mysqli) {
            $mysqli = $database->connection;
        }
        if (!$mysqli) {
            return;
        }

        // MySQL < 8.0.12 has no ADD COLUMN IF NOT EXISTS — check column first.
        $hasColumn = false;
        $colRes = @mysqli_query($mysqli, "SHOW COLUMNS FROM `settings` LIKE 'setup_guide_pending'");
        if ($colRes instanceof mysqli_result) {
            $hasColumn = mysqli_num_rows($colRes) > 0;
            mysqli_free_result($colRes);
        }
        if (!$hasColumn) {
            try {
                @mysqli_query(
                    $mysqli,
                    "ALTER TABLE `settings` ADD COLUMN `setup_guide_pending` TINYINT(1) NOT NULL DEFAULT 0"
                );
            } catch (Throwable $e) {
                // Column may have been added concurrently; ignore duplicate-column errors.
                if (stripos($e->getMessage(), 'Duplicate column') === false) {
                    error_log('[setup_guide] settings column: ' . $e->getMessage());
                }
            }
        }

        try {
            @mysqli_query(
                $mysqli,
                "CREATE TABLE IF NOT EXISTS `setup_guide_prefs` (
                `user_id` INT NOT NULL,
                `dismissed_forever` TINYINT(1) NOT NULL DEFAULT 0,
                `wizard_minimized` TINYINT(1) NOT NULL DEFAULT 0,
                `completed_steps` TEXT NULL,
                `updated_at` DATETIME NULL,
                PRIMARY KEY (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (Throwable $e) {
            error_log('[setup_guide] prefs table: ' . $e->getMessage());
        }
    }
}

if (!function_exists('setup_guide_db_query')) {
    /**
     * @return mysqli_result|bool|null
     */
    function setup_guide_db_query(string $sql)
    {
        global $database, $connect;
        if (isset($database) && is_object($database)) {
            if (method_exists($database, 'querySoft')) {
                return $database->querySoft($sql);
            }
            if (method_exists($database, 'query')) {
                try {
                    return $database->query($sql);
                } catch (Throwable $e) {
                    error_log('[setup_guide] query: ' . $e->getMessage());
                    return null;
                }
            }
        }
        if (isset($connect) && $connect instanceof mysqli) {
            try {
                return @mysqli_query($connect, $sql);
            } catch (Throwable $e) {
                error_log('[setup_guide] query: ' . $e->getMessage());
                return null;
            }
        }
        return null;
    }
}

if (!function_exists('setup_guide_db_fetch')) {
    /**
     * @param mixed $result
     * @return array<string, mixed>|null
     */
    function setup_guide_db_fetch($result): ?array
    {
        global $database;
        if (!$result) {
            return null;
        }
        if (isset($database) && is_object($database) && method_exists($database, 'fetchArray')) {
            $row = $database->fetchArray($result);
            return is_array($row) ? $row : null;
        }
        if ($result instanceof mysqli_result) {
            $row = mysqli_fetch_assoc($result);
            return is_array($row) ? $row : null;
        }
        return null;
    }
}

if (!function_exists('setup_guide_pending_flag')) {
    function setup_guide_pending_flag(): bool
    {
        global $dash_settings;
        setup_guide_ensure_schema();

        if (isset($dash_settings) && is_object($dash_settings) && property_exists($dash_settings, 'setup_guide_pending')) {
            return (int) $dash_settings->setup_guide_pending === 1;
        }

        $settings = class_exists('Settings') || class_exists('settings')
            ? (class_exists('settings') ? settings::findById(1) : Settings::findById(1))
            : null;
        if ($settings && isset($settings->setup_guide_pending)) {
            return (int) $settings->setup_guide_pending === 1;
        }

        $result = setup_guide_db_query('SELECT setup_guide_pending FROM settings WHERE id = 1 LIMIT 1');
        $row = setup_guide_db_fetch($result);
        return $row && (int) ($row['setup_guide_pending'] ?? 0) === 1;
    }
}

if (!function_exists('setup_guide_clear_pending')) {
    function setup_guide_clear_pending(): bool
    {
        global $dash_settings;
        setup_guide_ensure_schema();
        $ok = (bool) setup_guide_db_query('UPDATE settings SET setup_guide_pending = 0 WHERE id = 1');
        if ($ok && isset($dash_settings) && is_object($dash_settings)) {
            $dash_settings->setup_guide_pending = 0;
        }
        return $ok;
    }
}

if (!function_exists('setup_guide_load_prefs')) {
    /**
     * @return array{dismissed_forever:int,wizard_minimized:int,completed_steps:string[],celebration:int}
     */
    function setup_guide_load_prefs(int $userId): array
    {
        setup_guide_ensure_schema();
        $defaults = array(
            'dismissed_forever' => 0,
            'wizard_minimized' => 0,
            'completed_steps' => array(),
            'celebration' => 0,
            'remind_session' => '',
        );
        if ($userId <= 0) {
            return $defaults;
        }
        $uid = (int) $userId;
        $result = setup_guide_db_query("SELECT dismissed_forever, wizard_minimized, completed_steps FROM setup_guide_prefs WHERE user_id = {$uid} LIMIT 1");
        $row = setup_guide_db_fetch($result);
        if (!$row) {
            return $defaults;
        }
        $steps = array();
        $celebration = 0;
        $remindSession = '';
        if (!empty($row['completed_steps'])) {
            $decoded = json_decode((string) $row['completed_steps'], true);
            // v2+ object format: {"v":2,"steps":["smtp",...],"celebration":0}
            if (is_array($decoded) && isset($decoded['v']) && (int) $decoded['v'] >= 2 && isset($decoded['steps']) && is_array($decoded['steps'])) {
                foreach ($decoded['steps'] as $step) {
                    $step = (string) $step;
                    if ($step !== '' && isset(setup_guide_steps()[$step])) {
                        $steps[] = $step;
                    }
                }
                $celebration = !empty($decoded['celebration']) ? 1 : 0;
                $remindSession = isset($decoded['remind_session']) ? (string) $decoded['remind_session'] : '';
            }
            // Legacy plain array from visit-based marking is discarded (was marking pages without config)
        }
        return array(
            'dismissed_forever' => (int) ($row['dismissed_forever'] ?? 0),
            'wizard_minimized' => (int) ($row['wizard_minimized'] ?? 0),
            'completed_steps' => array_values(array_unique($steps)),
            'celebration' => $celebration,
            'remind_session' => $remindSession,
        );
    }
}

if (!function_exists('setup_guide_save_prefs')) {
    /**
     * @param array{dismissed_forever?:int,wizard_minimized?:int,completed_steps?:string[],celebration?:int} $prefs
     */
    function setup_guide_save_prefs(int $userId, array $prefs): bool
    {
        setup_guide_ensure_schema();
        if ($userId <= 0) {
            return false;
        }
        $current = setup_guide_load_prefs($userId);
        $dismissed = isset($prefs['dismissed_forever']) ? (int) $prefs['dismissed_forever'] : $current['dismissed_forever'];
        $minimized = isset($prefs['wizard_minimized']) ? (int) $prefs['wizard_minimized'] : $current['wizard_minimized'];
        $celebration = isset($prefs['celebration']) ? (int) $prefs['celebration'] : (int) $current['celebration'];
        $remindSession = array_key_exists('remind_session', $prefs)
            ? (string) $prefs['remind_session']
            : (string) ($current['remind_session'] ?? '');
        $steps = isset($prefs['completed_steps']) && is_array($prefs['completed_steps'])
            ? array_values(array_unique(array_map('strval', $prefs['completed_steps'])))
            : $current['completed_steps'];
        $validKeys = array_keys(setup_guide_steps());
        $steps = array_values(array_intersect($steps, $validKeys));
        $payload = array(
            'v' => 2,
            'steps' => $steps,
            'celebration' => $celebration ? 1 : 0,
            'remind_session' => $remindSession,
        );
        $json = json_encode($payload);
        if ($json === false) {
            $json = '{"v":2,"steps":[],"celebration":0}';
        }
        global $database, $connect;
        $escaped = $json;
        if (isset($database) && is_object($database) && method_exists($database, 'escapeValue')) {
            $escaped = $database->escapeValue($json);
        } elseif (isset($connect) && $connect instanceof mysqli) {
            $escaped = mysqli_real_escape_string($connect, $json);
        } else {
            $escaped = addslashes($json);
        }
        $uid = (int) $userId;
        $sql = "INSERT INTO setup_guide_prefs (user_id, dismissed_forever, wizard_minimized, completed_steps, updated_at)
            VALUES ({$uid}, {$dismissed}, {$minimized}, '{$escaped}', NOW())
            ON DUPLICATE KEY UPDATE
                dismissed_forever = VALUES(dismissed_forever),
                wizard_minimized = VALUES(wizard_minimized),
                completed_steps = VALUES(completed_steps),
                updated_at = NOW()";
        return (bool) setup_guide_db_query($sql);
    }
}

if (!function_exists('setup_guide_detected_steps')) {
    /**
     * @return string[]
     */
    function setup_guide_detected_steps(): array
    {
        global $dash_settings;
        $done = array();
        $settings = (isset($dash_settings) && is_object($dash_settings))
            ? $dash_settings
            : (class_exists('settings') ? settings::findById(1) : null);
        if (!$settings) {
            return $done;
        }
        // SMTP is completed only via verify success or explicit Skip (not on Save / host detect).
        $logo = isset($settings->logo) ? trim((string) $settings->logo) : '';
        $loginLogo = isset($settings->login_page_logo) ? trim((string) $settings->login_page_logo) : '';
        $defaults = array('', 'dark-logo.png', 'favicon.png', 'logo.png');
        if (($logo !== '' && !in_array($logo, $defaults, true))
            || ($loginLogo !== '' && !in_array($loginLogo, $defaults, true))) {
            $done[] = 'logo';
        }
        // Cron: at least one job has actually run (avoid '0000-00-00' — invalid on MySQL 8 strict mode)
        $cronResult = setup_guide_db_query(
            "SELECT id FROM cron_jobs WHERE last_run IS NOT NULL AND last_run > '1970-01-01 00:00:00' LIMIT 1"
        );
        if ($cronResult && setup_guide_db_fetch($cronResult)) {
            $done[] = 'cron';
        }
        return $done;
    }
}

if (!function_exists('setup_guide_merged_completed')) {
    /**
     * @return string[]
     */
    function setup_guide_merged_completed(array $prefs): array
    {
        $merged = array_merge($prefs['completed_steps'] ?? array(), setup_guide_detected_steps());
        $valid = array_keys(setup_guide_steps());
        return array_values(array_intersect(array_unique($merged), $valid));
    }
}

if (!function_exists('setup_guide_is_snoozed_until_next_login')) {
    function setup_guide_is_snoozed_until_next_login(): bool
    {
        if (!empty($_SESSION['setup_guide_remind_next_login'])) {
            return true;
        }
        $sid = session_id();
        if ($sid === '') {
            return false;
        }
        $prefs = setup_guide_load_prefs(1);
        return ($prefs['remind_session'] ?? '') !== '' && hash_equals((string) $prefs['remind_session'], $sid);
    }
}

if (!function_exists('setup_guide_remind_next_login')) {
    /**
     * Hide setup guide for this session; show again after next login.
     */
    function setup_guide_remind_next_login(): bool
    {
        if (!setup_guide_is_super_admin()) {
            return false;
        }
        if (session_status() !== PHP_SESSION_ACTIVE && function_exists('session_start')) {
            @session_start();
        }
        $_SESSION['setup_guide_remind_next_login'] = 1;
        $sid = session_id();
        setup_guide_save_prefs(1, array('remind_session' => $sid));
        return true;
    }
}

if (!function_exists('setup_guide_clear_remind_snooze')) {
    function setup_guide_clear_remind_snooze(): void
    {
        unset($_SESSION['setup_guide_remind_next_login']);
        $prefs = setup_guide_load_prefs(1);
        if (($prefs['remind_session'] ?? '') !== '') {
            setup_guide_save_prefs(1, array('remind_session' => ''));
        }
    }
}

if (!function_exists('setup_guide_should_show')) {
    function setup_guide_should_show(): bool
    {
        global $license_valid;
        if (!setup_guide_is_super_admin()) {
            return false;
        }
        if (isset($license_valid) && !$license_valid) {
            return false;
        }
        if (setup_guide_is_snoozed_until_next_login()) {
            return false;
        }
        if (!setup_guide_pending_flag()) {
            return false;
        }
        $prefs = setup_guide_load_prefs(1);
        if (!empty($prefs['dismissed_forever'])) {
            return false;
        }
        $completed = setup_guide_merged_completed($prefs);
        if (count($completed) >= count(setup_guide_steps())) {
            setup_guide_trigger_celebration($completed);
            return false;
        }
        return true;
    }
}

if (!function_exists('setup_guide_trigger_celebration')) {
    /**
     * @param string[] $completed
     */
    function setup_guide_trigger_celebration(array $completed = array()): void
    {
        if (!setup_guide_is_super_admin()) {
            return;
        }
        $prefs = setup_guide_load_prefs(1);
        if (!empty($prefs['dismissed_forever'])) {
            setup_guide_clear_pending();
            return;
        }
        if ($completed === array()) {
            $completed = setup_guide_merged_completed($prefs);
        }
        setup_guide_save_prefs(1, array(
            'completed_steps' => $completed,
            'celebration' => 1,
            'wizard_minimized' => 1,
        ));
        setup_guide_clear_pending();
    }
}

if (!function_exists('setup_guide_should_show_celebration')) {
    function setup_guide_should_show_celebration(): bool
    {
        global $license_valid;
        if (!setup_guide_is_super_admin()) {
            return false;
        }
        if (isset($license_valid) && !$license_valid) {
            return false;
        }
        if (setup_guide_is_snoozed_until_next_login()) {
            return false;
        }
        $prefs = setup_guide_load_prefs(1);
        if (!empty($prefs['dismissed_forever'])) {
            return false;
        }
        return !empty($prefs['celebration']);
    }
}

if (!function_exists('setup_guide_ack_celebration')) {
    function setup_guide_ack_celebration(): bool
    {
        if (!setup_guide_is_super_admin()) {
            return false;
        }
        return setup_guide_save_prefs(1, array(
            'celebration' => 0,
            'dismissed_forever' => 1,
            'wizard_minimized' => 1,
        ));
    }
}

if (!function_exists('setup_guide_current_step_key')) {
    /**
     * If the current request is one of the setup guide admin pages, return that step key.
     */
    function setup_guide_current_step_key(): ?string
    {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''));
        $uri = str_replace('\\', '/', (string) ($_SERVER['REQUEST_URI'] ?? ''));
        $haystack = $script . ' ' . $uri;
        foreach (setup_guide_steps() as $key => $step) {
            $needle = basename((string) $step['url']);
            if ($needle !== '' && stripos($haystack, $needle) !== false) {
                return $key;
            }
        }
        return null;
    }
}

if (!function_exists('setup_guide_state')) {
    /**
     * @return array{
     *   show:bool,
     *   minimized:bool,
     *   on_step_page:bool,
     *   current_step:?string,
     *   completed:string[],
     *   total:int,
     *   done_count:int,
     *   next:?array{key:string,label:string,url:string},
     *   steps:array<int, array{key:string,label:string,url:string,done:bool,current:bool}>
     * }
     */
    function setup_guide_state(): array
    {
        $stepsDef = setup_guide_steps();
        $empty = array(
            'show' => false,
            'minimized' => false,
            'on_step_page' => false,
            'current_step' => null,
            'completed' => array(),
            'total' => count($stepsDef),
            'done_count' => 0,
            'next' => null,
            'steps' => array(),
        );
        if (!setup_guide_should_show()) {
            return $empty;
        }
        $prefs = setup_guide_load_prefs(1);
        $completed = setup_guide_merged_completed($prefs);
        // Persist detected steps so progress sticks
        if (count($completed) > count($prefs['completed_steps'])) {
            setup_guide_save_prefs(1, array('completed_steps' => $completed));
        }
        $currentStep = setup_guide_current_step_key();
        $onStepPage = $currentStep !== null;
        // Working through a setup page: keep modal closed (sticky only)
        if ($onStepPage && empty($prefs['wizard_minimized'])) {
            setup_guide_save_prefs(1, array('wizard_minimized' => 1));
            $prefs['wizard_minimized'] = 1;
        }
        $list = array();
        $next = null;
        foreach ($stepsDef as $key => $step) {
            $done = in_array($key, $completed, true);
            $isCurrent = ($currentStep === $key);
            $item = array(
                'key' => $step['key'],
                'label' => $step['label'],
                'url' => $step['url'],
                'done' => $done,
                'current' => $isCurrent,
            );
            $list[] = $item;
            if (!$done && $next === null) {
                $next = array(
                    'key' => $step['key'],
                    'label' => $step['label'],
                    'url' => $step['url'],
                );
            }
        }
        if ($next === null) {
            setup_guide_trigger_celebration($completed);
            return $empty;
        }
        return array(
            'show' => true,
            'minimized' => !empty($prefs['wizard_minimized']) || $onStepPage,
            'on_step_page' => $onStepPage,
            'current_step' => $currentStep,
            'completed' => $completed,
            'total' => count($stepsDef),
            'done_count' => count($completed),
            'next' => $next,
            'steps' => $list,
        );
    }
}

if (!function_exists('setup_guide_mark_visit')) {
    function setup_guide_mark_visit(string $stepKey): void
    {
        if (!setup_guide_is_super_admin()) {
            return;
        }
        if (!isset(setup_guide_steps()[$stepKey])) {
            return;
        }
        if (!setup_guide_pending_flag()) {
            return;
        }
        $prefs = setup_guide_load_prefs(1);
        if (!empty($prefs['dismissed_forever']) && empty($prefs['celebration'])) {
            return;
        }
        $completed = setup_guide_merged_completed($prefs);
        if (!in_array($stepKey, $completed, true)) {
            $completed[] = $stepKey;
        }
        setup_guide_save_prefs(1, array('completed_steps' => $completed));
        if (count($completed) >= count(setup_guide_steps())) {
            setup_guide_trigger_celebration($completed);
        }
    }
}

if (!function_exists('setup_guide_minimize')) {
    function setup_guide_minimize(): bool
    {
        if (!setup_guide_is_super_admin()) {
            return false;
        }
        return setup_guide_save_prefs(1, array('wizard_minimized' => 1));
    }
}

if (!function_exists('setup_guide_expand')) {
    function setup_guide_expand(): bool
    {
        if (!setup_guide_is_super_admin()) {
            return false;
        }
        return setup_guide_save_prefs(1, array('wizard_minimized' => 0));
    }
}

if (!function_exists('setup_guide_dismiss_forever')) {
    function setup_guide_dismiss_forever(): bool
    {
        if (!setup_guide_is_super_admin()) {
            return false;
        }
        setup_guide_save_prefs(1, array(
            'dismissed_forever' => 1,
            'wizard_minimized' => 1,
            'celebration' => 0,
        ));
        return setup_guide_clear_pending();
    }
}
