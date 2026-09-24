<?php
/**
 * Run version-based SQL migrations from includes/migrations/ (e.g. 4.1.sql).
 * Only runs files not yet recorded in schema_migrations; skips duplicate/already-exists errors.
 * Used by update-system.php after file copy + version update (Option B - auto).
 */

/**
 * Split SQL on ';' only outside single/double-quoted strings and backtick identifiers.
 * Naive explode() breaks on semicolons inside COMMENT '...;...' (e.g. email compose_session_id).
 *
 * @return list<string>
 */
function migration_sql_split_statements($sql) {
    $sql = trim((string) $sql);
    if ($sql === '') {
        return [];
    }
    $statements = [];
    $len = strlen($sql);
    $buf = '';
    $inSingle = false;
    $inDouble = false;
    $inBacktick = false;
    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        if ($inSingle) {
            if ($c === "'" && $i + 1 < $len && $sql[$i + 1] === "'") {
                $buf .= "''";
                $i++;
                continue;
            }
            if ($c === "'") {
                $inSingle = false;
            }
            $buf .= $c;
            continue;
        }
        if ($inDouble) {
            if ($c === '"' && $i + 1 < $len && $sql[$i + 1] === '"') {
                $buf .= '""';
                $i++;
                continue;
            }
            if ($c === '"') {
                $inDouble = false;
            }
            $buf .= $c;
            continue;
        }
        if ($inBacktick) {
            if ($c === '`' && $i + 1 < $len && $sql[$i + 1] === '`') {
                $buf .= '``';
                $i++;
                continue;
            }
            if ($c === '`') {
                $inBacktick = false;
            }
            $buf .= $c;
            continue;
        }
        if ($c === "'") {
            $inSingle = true;
            $buf .= $c;
            continue;
        }
        if ($c === '"') {
            $inDouble = true;
            $buf .= $c;
            continue;
        }
        if ($c === '`') {
            $inBacktick = true;
            $buf .= $c;
            continue;
        }
        if ($c === ';') {
            $stmt = trim($buf);
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $buf = '';
            continue;
        }
        $buf .= $c;
    }
    $stmt = trim($buf);
    if ($stmt !== '') {
        $statements[] = $stmt;
    }
    return $statements;
}

/**
 * True when a migration statement failed in a way that is safe to ignore (idempotent re-runs).
 * PHP 8+ often throws mysqli_sql_exception for these instead of leaving mysqli_error().
 */
function migration_sql_is_benign_migration_error($msg) {
    $m = strtolower((string) $msg);
    if ($m === '') {
        return false;
    }
    if (stripos($m, 'duplicate') !== false) {
        return true;
    }
    if (stripos($m, 'already exists') !== false) {
        return true;
    }
    // DROP FOREIGN KEY when constraint name differs or already removed (safe re-run / varied installers)
    if (stripos($m, "can't drop foreign key") !== false || stripos($m, 'cannot drop foreign key') !== false) {
        return true;
    }
    if (stripos($m, 'check that column/key exists') !== false) {
        return true;
    }
    if (stripos($m, 'unknown foreign key') !== false) {
        return true;
    }
    return false;
}

/**
 * Execute one migration statement (MySQL 8+ syntax or older MariaDB compat).
 *
 * @throws Exception
 */
function migration_execute_statement($conn, string $stmt, string $migrationLabel): void
{
    $stmt = trim($stmt);
    if ($stmt === '') {
        return;
    }

    $helper = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'installer_sql_helper.php';
    if (is_file($helper)) {
        require_once $helper;
    }

    $errors = [];
    if (function_exists('installer_run_migration_sql_compat')
        && installer_run_migration_sql_compat($conn, $stmt, $errors, $migrationLabel)) {
        if (!empty($errors)) {
            throw new Exception($migrationLabel . ' failed: ' . implode('; ', $errors));
        }
        return;
    }

    try {
        $res = mysqli_query($conn, $stmt . ';');
    } catch (Throwable $e) {
        if (migration_sql_is_benign_migration_error($e->getMessage())) {
            return;
        }
        throw new Exception($migrationLabel . ' failed: ' . $e->getMessage(), 0, $e);
    }
    if ($res === false) {
        $err = mysqli_error($conn);
        if (migration_sql_is_benign_migration_error($err)) {
            return;
        }
        if ($err !== '') {
            throw new Exception($migrationLabel . ' failed: ' . $err);
        }
    }
}

/**
 *
 * @param object $database MySQLDatabase instance (must have ->connection and ->query or raw mysqli)
 */
function ensure_schema_migrations_table($database) {
    $conn = $database->connection;
    $sql = "CREATE TABLE IF NOT EXISTS `schema_migrations` (
        `version` VARCHAR(20) NOT NULL,
        `migration_file` VARCHAR(100) NOT NULL,
        `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`version`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    mysqli_query($conn, $sql);
    if (mysqli_error($conn)) {
        throw new Exception("Failed to create schema_migrations table: " . mysqli_error($conn));
    }
}

/**
 * Run all pending version migrations up to and including $up_to_version.
 * Migrations are optional per version: if no X.Y.sql exists, nothing is run for that version.
 *
 * @param object $database MySQLDatabase instance
 * @param string $up_to_version Version to run up to (e.g. "4.1")
 * @param string $base_path Site root path (e.g. dirname(__DIR__) from admin)
 * @return array ['success' => bool, 'message' => string, 'run' => array]
 */
function runVersionMigrations($database, $up_to_version, $base_path) {
    $migrations_dir = $base_path . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'migrations';
    $run = [];

    ensure_schema_migrations_table($database);
    $conn = $database->connection;

    if (!is_dir($migrations_dir)) {
        return ['success' => true, 'message' => 'No migrations folder; no database changes.', 'run' => []];
    }

    $files = glob($migrations_dir . DIRECTORY_SEPARATOR . '*.sql');
    $pending = [];
    foreach ($files as $path) {
        $basename = basename($path);
        if (!preg_match('/^([0-9]+\.[0-9]+(?:\.[0-9]+)?)\.sql$/', $basename, $m)) {
            continue;
        }
        $ver = $m[1];
        if (version_compare($ver, $up_to_version, '>')) {
            continue;
        }
        $pending[$ver] = $path;
    }

    if (empty($pending)) {
        return ['success' => true, 'message' => 'No database changes for this version.', 'run' => []];
    }

    $res = mysqli_query($conn, "SELECT version FROM schema_migrations");
    $applied = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $applied[$row['version']] = true;
        }
    }

    ksort($pending, SORT_NATURAL);
    foreach ($pending as $ver => $path) {
        if (!empty($applied[$ver])) {
            continue;
        }
        $sql = file_get_contents($path);
        if ($sql === false || trim($sql) === '') {
            $run[] = ['version' => $ver, 'file' => basename($path), 'status' => 'skipped_empty'];
            continue;
        }
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        $statements = migration_sql_split_statements($sql);
        if ((function_exists('tasksession_is_free_edition') && tasksession_is_free_edition())
            || (defined('TASKSESSION_FREE_EDITION') && TASKSESSION_FREE_EDITION)) {
            if (!function_exists('installer_free_should_skip_sql')) {
                $filter = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'free_edition_filter.php';
                if (is_readable($filter)) {
                    require_once $filter;
                }
            }
            if (function_exists('installer_free_filter_sql_list')) {
                $statements = installer_free_filter_sql_list($statements);
            }
            // Extra: drop any remaining AI / api_tokens DDL/DML (keep settings.module_ai ALTER)
            $statements = array_values(array_filter($statements, static function ($q) {
                $q = (string) $q;
                if (preg_match('/ALTER\s+TABLE\s+`?settings`?/i', $q)) {
                    return true;
                }
                if (preg_match('/\b(ai_[a-z0-9_]+|api_tokens)\b/i', $q)
                    && preg_match('/^\s*(CREATE\s+TABLE|ALTER\s+TABLE|INSERT\s+INTO|UPDATE|DROP\s+TABLE)\b/i', $q)) {
                    return false;
                }
                return true;
            }));
        }
        $migrationLabel = 'Migration ' . basename($path);
        foreach ($statements as $stmt) {
            if ($stmt === '') {
                continue;
            }
            migration_execute_statement($conn, $stmt, $migrationLabel);
        }
        $escaped_ver = mysqli_real_escape_string($conn, $ver);
        $escaped_file = mysqli_real_escape_string($conn, basename($path));
        mysqli_query($conn, "INSERT INTO schema_migrations (version, migration_file) VALUES ('$escaped_ver', '$escaped_file')");
        if (mysqli_error($conn)) {
            throw new Exception("Failed to record migration: " . mysqli_error($conn));
        }
        $run[] = ['version' => $ver, 'file' => basename($path), 'status' => 'applied'];
    }

    return ['success' => true, 'message' => count($run) ? 'Database updated.' : 'No new migrations to run.', 'run' => $run];
}

function migration_table_exists($conn, string $table): bool
{
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($table === '') {
        return false;
    }
    $res = @mysqli_query($conn, "SHOW TABLES LIKE '{$table}'");
    return $res && mysqli_num_rows($res) > 0;
}

/**
 * @return array<string, string> version => basename
 */
function migration_get_versioned_sql_files($base_path, $up_to_version = null): array
{
    $migrations_dir = $base_path . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'migrations';
    if (!is_dir($migrations_dir)) {
        return [];
    }
    $files = glob($migrations_dir . DIRECTORY_SEPARATOR . '*.sql') ?: [];
    $out = [];
    foreach ($files as $path) {
        $basename = basename($path);
        if (!preg_match('/^([0-9]+\.[0-9]+(?:\.[0-9]+)?)\.sql$/', $basename, $m)) {
            continue;
        }
        $ver = $m[1];
        if ($up_to_version !== null && version_compare($ver, (string) $up_to_version, '>')) {
            continue;
        }
        $out[$ver] = $basename;
    }
    ksort($out, SORT_NATURAL);
    return $out;
}

/**
 * Record versioned migration files as applied without executing SQL (fresh install baseline).
 */
function migration_record_versions_through($database, $base_path, $up_to_version): int
{
    ensure_schema_migrations_table($database);
    $conn = $database->connection;
    $recorded = 0;
    foreach (migration_get_versioned_sql_files($base_path, $up_to_version) as $ver => $basename) {
        $escaped_ver = mysqli_real_escape_string($conn, $ver);
        $escaped_file = mysqli_real_escape_string($conn, $basename);
        $check = mysqli_query($conn, "SELECT version FROM schema_migrations WHERE version = '{$escaped_ver}' LIMIT 1");
        if ($check && mysqli_num_rows($check) > 0) {
            continue;
        }
        mysqli_query($conn, "INSERT INTO schema_migrations (version, migration_file) VALUES ('{$escaped_ver}', '{$escaped_file}')");
        if (mysqli_error($conn) === '') {
            $recorded++;
        }
    }
    return $recorded;
}

/**
 * True when installer already created schema for this version (upgrade migrations not needed).
 */
function migration_installer_schema_matches_version($conn, string $current_version): bool
{
    if (version_compare($current_version, '4.1', '>=') && !migration_table_exists($conn, 'project_activities')) {
        return false;
    }
    if (version_compare($current_version, '4.2', '>=') && !migration_table_exists($conn, 'tblserver_monitor_logs')) {
        return false;
    }
    if (version_compare($current_version, '4.3', '>=')) {
        // Free edition does not ship marketing / license addon tables.
        if (!(defined('TASKSESSION_FREE_EDITION') && TASKSESSION_FREE_EDITION)) {
            if (!migration_table_exists($conn, 'marketing_recipients')) {
                return false;
            }
            if (!migration_table_exists($conn, 'wc_am_addon_activations')) {
                return false;
            }
        }
    }
    if (version_compare($current_version, '4.6', '>=') && !migration_table_exists($conn, 'invoice_items')) {
        return false;
    }
    $usersUsername = @mysqli_query($conn, "SHOW COLUMNS FROM `users` LIKE 'username'");
    if (!$usersUsername || mysqli_num_rows($usersUsername) === 0) {
        return false;
    }
    return true;
}

/**
 * Fresh install runs full schema via install/index.php — mark version SQL files as applied when tables already exist.
 */
function migration_baseline_fresh_install_if_needed($database, $base_path, $current_version): void
{
    ensure_schema_migrations_table($database);
    $conn = $database->connection;
    if (!migration_installer_schema_matches_version($conn, (string) $current_version)) {
        return;
    }

    $applied = [];
    $res = mysqli_query($conn, 'SELECT version FROM schema_migrations');
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $applied[$row['version']] = true;
        }
    }

    $needsBaseline = false;
    foreach (migration_get_versioned_sql_files($base_path, $current_version) as $ver => $_basename) {
        if (empty($applied[$ver])) {
            $needsBaseline = true;
            break;
        }
    }
    if (!$needsBaseline) {
        return;
    }

    migration_record_versions_through($database, $base_path, $current_version);
}

/**
 * List SQL migration files not yet recorded in schema_migrations (up to $current_version).
 *
 * @return list<array{version: string, file: string}>
 */
function migration_list_pending($database, $base_path, $ordered_versions, $current_version) {
    $migrations_dir = $base_path . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'migrations';
    if (!is_dir($migrations_dir)) {
        return [];
    }

    migration_baseline_fresh_install_if_needed($database, $base_path, $current_version);

    ensure_schema_migrations_table($database);
    $conn = $database->connection;

    $applied = [];
    $res = mysqli_query($conn, 'SELECT version FROM schema_migrations');
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $applied[$row['version']] = true;
        }
    }

    $pending = [];
    $files = glob($migrations_dir . DIRECTORY_SEPARATOR . '*.sql');
    if (!$files) {
        return [];
    }

    foreach ($files as $path) {
        $basename = basename($path);
        if (!preg_match('/^([0-9]+\.[0-9]+(?:\.[0-9]+)?)\.sql$/', $basename, $m)) {
            continue;
        }
        $ver = $m[1];
        if (version_compare($ver, (string) $current_version, '>')) {
            continue;
        }
        if (!empty($applied[$ver])) {
            continue;
        }
        $pending[] = ['version' => $ver, 'file' => $basename];
    }

    usort($pending, static function ($a, $b) {
        return version_compare($a['version'], $b['version']);
    });

    return $pending;
}

/**
 * Apply all pending migrations for the installed version (manual "Run now" + post-update safety).
 *
 * @return array{success: bool, message: string, run: array}
 */
function runPendingDatabaseMigrations($database, $base_path, $current_version, $ordered_versions) {
    return runVersionMigrations($database, (string) $current_version, $base_path);
}

/**
 * Clear PHP opcode cache after file updates so the next request loads new code immediately.
 */
function update_system_clear_php_opcode_cache($base_path) {
    if (function_exists('opcache_reset')) {
        @opcache_reset();
        return;
    }
    if (!function_exists('opcache_invalidate')) {
        return;
    }
    $paths = [
        $base_path . '/templates/header.php',
        $base_path . '/includes/theme-shadow-helpers.php',
        $base_path . '/assets/css/theme-vars.php',
        $base_path . '/includes/ensure-theme-font-schema.php',
        $base_path . '/includes/run_version_migrations.php',
    ];
    foreach ($paths as $path) {
        if (is_file($path)) {
            @opcache_invalidate($path, true);
        }
    }
}
