<?php
/**
 * Safe SQL runner for install/system.php (Cloudways / older MySQL).
 * Failed ALTERs must not white-screen the admin setup step.
 */
declare(strict_types=1);

function installer_table_has_column($connect, string $table, string $column): bool
{
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($table === '' || $column === '') {
        return false;
    }
    $res = @mysqli_query($connect, "SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    return $res && mysqli_num_rows($res) > 0;
}

/**
 * MySQL < 8.0.12 / older MariaDB: ADD COLUMN IF NOT EXISTS is unsupported.
 * Split into per-column ALTERs with SHOW COLUMNS guard.
 */
function installer_run_add_columns_compat($connect, string $sql, array &$errors, string $label = '', ?array &$notes = null): void
{
    $sql = trim($sql);
    if (!preg_match('/ALTER\s+TABLE\s+`?([a-zA-Z0-9_]+)`?/i', $sql, $tableMatch)) {
        installer_run_single_sql($connect, $sql, $errors, $label);
        return;
    }
    $table = $tableMatch[1];
    $parts = preg_split('/\s*ADD\s+COLUMN\s+IF\s+NOT\s+EXISTS\s+/i', $sql);
    if (!$parts || count($parts) < 2) {
        installer_run_single_sql($connect, $sql, $errors, $label);
        return;
    }

    for ($i = 1; $i < count($parts); $i++) {
        $chunk = trim($parts[$i]);
        $chunk = rtrim($chunk, ", \t\n\r");
        $column = '';
        $definition = '';
        if (preg_match('/^`([^`]+)`\s+(.+)$/s', $chunk, $colMatch)) {
            $column = $colMatch[1];
            $definition = trim($colMatch[2]);
        } elseif (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s+(.+)$/s', $chunk, $colMatch)) {
            $column = $colMatch[1];
            $definition = trim($colMatch[2]);
        } else {
            $errors[] = ($label ?: 'SQL') . ' parse failed: ' . substr($chunk, 0, 80);
            continue;
        }
        $definition = rtrim($definition, ';');
        if (installer_table_has_column($connect, $table, $column)) {
            if ($notes !== null) {
                $notes[] = ($label ?: 'SQL') . " skipped existing column {$table}.{$column}";
            }
            continue;
        }
        $single = "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}";
        installer_run_single_sql($connect, $single, $errors, $label !== '' ? $label . ':' . $column : $column);
    }
}

function installer_sql_is_add_column_if_not_exists(string $sql): bool
{
    return stripos($sql, 'ADD COLUMN IF NOT EXISTS') !== false;
}

function installer_table_has_index($connect, string $table, string $indexName): bool
{
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $indexName = preg_replace('/[^a-zA-Z0-9_]/', '', $indexName);
    if ($table === '' || $indexName === '') {
        return false;
    }
    $escaped = mysqli_real_escape_string($connect, $indexName);
    $res = @mysqli_query($connect, "SHOW INDEX FROM `{$table}` WHERE Key_name = '{$escaped}'");
    return $res && mysqli_num_rows($res) > 0;
}

/**
 * MySQL < 8.0.13 / older MariaDB: ADD KEY|INDEX IF NOT EXISTS is unsupported.
 */
function installer_run_add_index_compat($connect, string $sql, array &$errors, string $label = '', ?array &$notes = null): void
{
    $sql = trim($sql);
    if (!preg_match('/ALTER\s+TABLE\s+`?([a-zA-Z0-9_]+)`?/i', $sql, $tableMatch)) {
        installer_run_single_sql($connect, $sql, $errors, $label);
        return;
    }
    if (!preg_match('/ADD\s+(?:UNIQUE\s+)?(?:KEY|INDEX)\s+IF\s+NOT\s+EXISTS\s+/i', $sql)) {
        installer_run_single_sql($connect, $sql, $errors, $label);
        return;
    }

    $table = $tableMatch[1];
    if (!preg_match('/ADD\s+(UNIQUE\s+)?(?:KEY|INDEX)\s+IF\s+NOT\s+EXISTS\s+`([^`]+)`\s*\(([^)]+)\)/is', $sql, $indexMatch)) {
        $errors[] = ($label ?: 'SQL') . ' index parse failed: ' . substr($sql, 0, 120);
        return;
    }

    $unique = trim((string) ($indexMatch[1] ?? '')) !== '';
    $indexName = $indexMatch[2];
    $columns = trim($indexMatch[3]);
    if (installer_table_has_index($connect, $table, $indexName)) {
        if ($notes !== null) {
            $notes[] = ($label ?: 'SQL') . " skipped existing index {$table}.{$indexName}";
        }
        return;
    }

    $uniqueSql = $unique ? 'UNIQUE ' : '';
    $single = "ALTER TABLE `{$table}` ADD {$uniqueSql}INDEX `{$indexName}` ({$columns})";
    installer_run_single_sql($connect, $single, $errors, $label !== '' ? $label . ':' . $indexName : $indexName);
}

function installer_sql_is_add_index_if_not_exists(string $sql): bool
{
    return (bool) preg_match('/ADD\s+(?:UNIQUE\s+)?(?:KEY|INDEX)\s+IF\s+NOT\s+EXISTS/i', $sql);
}

function installer_run_migration_sql_compat($connect, string $sql, array &$errors, string $label = '', ?array &$notes = null): bool
{
    if (installer_sql_is_add_column_if_not_exists($sql)) {
        installer_run_add_columns_compat($connect, $sql, $errors, $label, $notes);
        return true;
    }
    if (installer_sql_is_add_index_if_not_exists($sql)) {
        installer_run_add_index_compat($connect, $sql, $errors, $label, $notes);
        return true;
    }
    return false;
}

function installer_run_single_sql($connect, string $sql, array &$errors, string $label = ''): void
{
    try {
        if (@mysqli_query($connect, $sql)) {
            return;
        }
    } catch (mysqli_sql_exception $e) {
        $err = $e->getMessage();
        if (stripos($err, 'Duplicate') !== false || stripos($err, 'already exists') !== false) {
            return;
        }
        $errors[] = ($label ?: 'SQL') . ' exception: ' . $err;
        return;
    }
    $err = (string) mysqli_error($connect);
    if ($err === '') {
        return;
    }
    if (stripos($err, 'Duplicate') !== false || stripos($err, 'already exists') !== false) {
        return;
    }
    $errors[] = ($label ?: 'SQL') . ' failed: ' . $err;
}

function install_run_queries(mysqli $connect, array $queries, array &$warnings): void
{
    foreach ($queries as $sql) {
        $sql = trim((string) $sql);
        if ($sql === '') {
            continue;
        }
        if (installer_run_migration_sql_compat($connect, $sql, $warnings, 'schema')) {
            continue;
        }
        installer_run_single_sql($connect, $sql, $warnings, 'schema');
    }
}

function install_run_query(mysqli $connect, string $sql, array &$warnings): void
{
    install_run_queries($connect, [$sql], $warnings);
}

/**
 * Fresh install from install/index.php: core tables exist but admin setup (settings row) not done yet.
 */
function installer_fresh_schema_ready_for_admin_setup(mysqli $connect): bool
{
    $usersTable = @mysqli_query($connect, "SHOW TABLES LIKE 'users'");
    if (!$usersTable || mysqli_num_rows($usersTable) === 0) {
        return false;
    }
    $settingsTable = @mysqli_query($connect, "SHOW TABLES LIKE 'settings'");
    if (!$settingsTable || mysqli_num_rows($settingsTable) === 0) {
        return false;
    }
    $countRes = @mysqli_query($connect, 'SELECT COUNT(*) AS c FROM `settings`');
    if (!$countRes) {
        return false;
    }
    $row = mysqli_fetch_assoc($countRes);
    return (int) ($row['c'] ?? 0) === 0;
}

/**
 * Redirect after install; HTML fallback if output (e.g. BOM) was already sent.
 */
function install_safe_redirect(string $url): void
{
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    if (!headers_sent()) {
        header('Location: ' . $url);
        exit;
    }
    $esc = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    $jsUrl = json_encode($url, JSON_UNESCAPED_SLASHES);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
    echo '<meta http-equiv="refresh" content="0;url=' . $esc . '">';
    echo '<title>Continue installation</title></head><body style="font-family:system-ui,sans-serif;padding:2rem">';
    echo '<p>Setup step complete. <a href="' . $esc . '">Continue</a></p>';
    echo '<script>location.replace(' . $jsUrl . ');</script></body></html>';
    exit;
}

/**
 * Version 4.2 — Server Health Monitor tables (includes/migrations/4.2.sql).
 *
 * @return list<string>
 */
function installer_get_42_server_monitor_queries(): array
{
    return [
        "CREATE TABLE IF NOT EXISTS `tblserver_monitor_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `created_at` DATETIME NOT NULL,
            `cpu_usage` DECIMAL(5,2) NULL,
            `ram_usage` DECIMAL(5,2) NULL,
            `disk_usage` DECIMAL(5,2) NULL,
            `load_1` DECIMAL(8,2) NULL,
            `load_5` DECIMAL(8,2) NULL,
            `load_15` DECIMAL(8,2) NULL,
            `network_in` BIGINT NULL,
            `network_out` BIGINT NULL,
            `php_memory_usage` BIGINT NULL,
            `php_memory_peak` BIGINT NULL,
            `db_size` BIGINT NULL,
            INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `tblserver_request_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `created_at` DATETIME NOT NULL,
            `method` VARCHAR(10) NULL,
            `endpoint` VARCHAR(255) NULL,
            `status_code` INT NULL,
            `response_time_ms` DECIMAL(10,2) NULL,
            `memory_usage` BIGINT NULL,
            `ip_address` VARCHAR(100) NULL,
            `user_agent` TEXT NULL,
            `is_error` TINYINT(1) DEFAULT 0,
            INDEX `idx_created_at` (`created_at`),
            INDEX `idx_endpoint_method` (`endpoint`, `method`),
            INDEX `idx_response_time` (`response_time_ms`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
}

function installer_run_42_server_monitor_migrations(mysqli $connect, array &$warnings): void
{
    install_run_queries($connect, installer_get_42_server_monitor_queries(), $warnings);
}

/**
 * Version 4.6 — performance tables for fresh install (includes/migrations/4.6.sql CREATE only).
 * Column/index changes from 4.6.sql are baked into install/index.php CREATE TABLE definitions.
 *
 * @return list<string>
 */
function installer_get_46_performance_create_table_queries(): array
{
    return [
        "CREATE TABLE IF NOT EXISTS `crm_mail_queue` (
            `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
            `recipient_email` varchar(255) NOT NULL,
            `subject` varchar(500) NOT NULL,
            `body` mediumtext NOT NULL,
            `headers` text DEFAULT NULL,
            `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
            `attempts` tinyint UNSIGNED NOT NULL DEFAULT 0,
            `last_error` varchar(500) DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `sent_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_crm_mail_queue_status_created` (`status`,`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
}

function installer_run_46_performance_create_tables(mysqli $connect, array &$warnings): void
{
    install_run_queries($connect, installer_get_46_performance_create_table_queries(), $warnings);
}

/**
 * Version 4.7.1 — task recurring tables + columns (includes/migrations/4.7.1.sql).
 *
 * @return list<string>
 */
function installer_get_471_task_recurrence_queries(): array
{
    return [
        "ALTER TABLE `tasks` ADD COLUMN IF NOT EXISTS `recurrence_id` INT(11) NULL DEFAULT NULL",
        "ALTER TABLE `tasks` ADD COLUMN IF NOT EXISTS `recurrence_parent_task_id` INT(11) NULL DEFAULT NULL",
        "ALTER TABLE `tasks` ADD KEY IF NOT EXISTS `idx_tasks_recurrence` (`recurrence_id`)",
        "ALTER TABLE `tasks` ADD KEY IF NOT EXISTS `idx_tasks_recurrence_parent` (`recurrence_parent_task_id`)",
        "CREATE TABLE IF NOT EXISTS `task_recurrences` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `root_task_id` INT(11) NOT NULL,
            `mode` VARCHAR(32) NOT NULL DEFAULT 'time_based',
            `interval_unit` VARCHAR(16) NULL DEFAULT NULL,
            `interval_count` INT(11) NOT NULL DEFAULT 1,
            `skip_weekends` TINYINT(1) NOT NULL DEFAULT 0,
            `start_from_date` DATE NULL DEFAULT NULL,
            `due_offset_days` INT(11) NULL DEFAULT NULL,
            `default_status` VARCHAR(50) NOT NULL DEFAULT 'todo',
            `estimated_time_seconds` INT UNSIGNED NULL DEFAULT NULL,
            `after_status` VARCHAR(50) NOT NULL DEFAULT 'todo',
            `ends_at` DATE NULL DEFAULT NULL,
            `next_run_date` DATE NULL DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_tr_root` (`root_task_id`),
            KEY `idx_tr_active_next` (`is_active`, `mode`, `next_run_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `task_recurrence_occurrences` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `recurrence_id` INT(11) NOT NULL,
            `task_id` INT(11) NOT NULL,
            `occurrence_date` DATE NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_tro_recurrence_date` (`recurrence_id`, `occurrence_date`),
            KEY `idx_tro_task` (`task_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "INSERT INTO `cron_jobs` (`name`, `file_path`, `category`, `duration`, `enabled`)
            SELECT 'Recurring Tasks', 'cron/cron_recurring_tasks.php', 'Tasks', '1 day', 1 FROM DUAL
            WHERE NOT EXISTS (SELECT 1 FROM cron_jobs WHERE file_path='cron/cron_recurring_tasks.php' LIMIT 1)",
    ];
}

function installer_run_471_task_recurrence(mysqli $connect, array &$warnings): void
{
    install_run_queries($connect, installer_get_471_task_recurrence_queries(), $warnings);
}

/**
 * Split a .sql file into individual statements (comments stripped).
 *
 * @return list<string>
 */
function installer_split_sql_file(string $path): array
{
    if (!is_readable($path)) {
        return [];
    }
    $sql = (string) file_get_contents($path);
    if ($sql === '') {
        return [];
    }
    $sql = preg_replace('/^\s*DELIMITER\s+.*$/im', '', $sql) ?? $sql;
    $sql = preg_replace('/--[^\n\r]*/', '', $sql) ?? $sql;
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql) ?? $sql;
    $parts = preg_split('/;\s*[\r\n]+/', $sql) ?: [];
    $out = [];
    foreach ($parts as $part) {
        $part = trim((string) $part);
        $part = rtrim($part, "; \t\r\n");
        if ($part === '') {
            continue;
        }
        $out[] = $part;
    }
    return $out;
}

/**
 * Version 4.10 — AI Assistant + api_tokens CREATE TABLE statements (from includes/migrations/4.10.sql).
 *
 * @return list<string>
 */
function installer_get_410_create_table_queries(): array
{
    // Free: AI / api_tokens CREATE statements removed (see includes/migrations/4.10.sql).
    return [];
}

/**
 * Free edition: no AI table migration. Only optional settings flags from slim 4.10.sql.
 */
function installer_run_410_migrations(mysqli $connect, array &$warnings): void
{
    $master = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '4.10.sql';
    $queries = installer_split_sql_file($master);
    if ($queries === []) {
        return;
    }
    install_run_queries($connect, $queries, $warnings);
}

/**
 * Free edition: ecommerce schema removed.
 *
 * @return list<string>
 */
function installer_get_ecommerce_create_table_queries(): array
{
    return [];
}

/**
 * Free edition: ecommerce crons removed.
 *
 * @return list<array{name:string,file_path:string,category:string,duration:string,enabled:int}>
 */
function installer_get_ecommerce_cron_jobs(): array
{
    return [];
}

function installer_fix_functions_autoload_if_broken(string $functionsPath): bool
{
    if (!is_file($functionsPath)) {
        return false;
    }
    $content = file_get_contents($functionsPath);
    if ($content === false || strpos($content, 'could not be found') === false) {
        return true;
    }
    $fragmentPath = dirname(__FILE__) . '/functions_autoload.fragment.php';
    if (!is_file($fragmentPath)) {
        return false;
    }
    $newAutoload = trim((string) file_get_contents($fragmentPath));
    if ($newAutoload === '') {
        return false;
    }
    $replaced = preg_replace(
        '/spl_autoload_register\s*\(\s*function\s*\(\s*\$class_name\s*\)\s*\{.*?\}\s*\)\s*;/s',
        $newAutoload,
        $content,
        1,
        $count
    );
    if ($count < 1 || $replaced === null) {
        return false;
    }
    return file_put_contents($functionsPath, $replaced) !== false;
}

function installer_should_preserve_functions_php(string $functionsPath): bool
{
    if (!is_file($functionsPath)) {
        return false;
    }
    $content = file_get_contents($functionsPath);
    if ($content === false || strlen($content) < 500) {
        return false;
    }
    return strpos($content, 'spl_autoload_register') !== false
        && strpos($content, 'could not be found') === false;
}

function install_ensure_schema_migrations_table(mysqli $connect): void
{
    $sql = "CREATE TABLE IF NOT EXISTS `schema_migrations` (
        `version` VARCHAR(20) NOT NULL,
        `migration_file` VARCHAR(100) NOT NULL,
        `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`version`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    @mysqli_query($connect, $sql);
}

/**
 * @return array<string, string> version => basename
 */
function install_get_versioned_migration_files(string $basePath, ?string $upToVersion = null): array
{
    $migrationsDir = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'migrations';
    if (!is_dir($migrationsDir)) {
        return [];
    }

    $files = glob($migrationsDir . DIRECTORY_SEPARATOR . '*.sql') ?: [];
    $out = [];
    foreach ($files as $path) {
        $basename = basename($path);
        if (!preg_match('/^([0-9]+\.[0-9]+(?:\.[0-9]+)?)\.sql$/', $basename, $m)) {
            continue;
        }
        $ver = $m[1];
        if ($upToVersion !== null && version_compare($ver, (string) $upToVersion, '>')) {
            continue;
        }
        $out[$ver] = $basename;
    }
    ksort($out, SORT_NATURAL);

    return $out;
}

function install_record_schema_migrations_baseline(mysqli $connect, string $basePath, string $upToVersion): int
{
    install_ensure_schema_migrations_table($connect);

    $recorded = 0;
    foreach (install_get_versioned_migration_files($basePath, $upToVersion) as $ver => $basename) {
        $escapedVer = mysqli_real_escape_string($connect, $ver);
        $escapedFile = mysqli_real_escape_string($connect, $basename);
        $check = mysqli_query($connect, "SELECT version FROM schema_migrations WHERE version = '{$escapedVer}' LIMIT 1");
        if ($check && mysqli_num_rows($check) > 0) {
            continue;
        }
        mysqli_query($connect, "INSERT INTO schema_migrations (version, migration_file) VALUES ('{$escapedVer}', '{$escapedFile}')");
        if (mysqli_error($connect) === '') {
            $recorded++;
        }
    }

    return $recorded;
}

/**
 * Mark version SQL files as applied after fresh install (uses core helper when available).
 */
function install_baseline_schema_migrations(mysqli $connect, string $basePath, string $upToVersion): int
{
    $migrationRunner = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'run_version_migrations.php';
    if (is_readable($migrationRunner)) {
        require_once $migrationRunner;
    }

    if (function_exists('migration_record_versions_through')) {
        $installDb = new stdClass();
        $installDb->connection = $connect;

        return migration_record_versions_through($installDb, $basePath, $upToVersion);
    }

    return install_record_schema_migrations_baseline($connect, $basePath, $upToVersion);
}
