<?php
/**
 * Shared SQL migration compat helpers (safe without install/ folder).
 * Used by AI addon activation on production hosts that omit install/.
 */

if (!function_exists('installer_table_has_column')) {
    function installer_table_has_column($connect, $table, $column)
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $column);
        if ($table === '' || $column === '') {
            return false;
        }
        $res = @mysqli_query($connect, "SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        return $res && mysqli_num_rows($res) > 0;
    }
}

if (!function_exists('installer_table_has_index')) {
    function installer_table_has_index($connect, $table, $indexName)
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
        $indexName = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $indexName);
        if ($table === '' || $indexName === '') {
            return false;
        }
        $escaped = mysqli_real_escape_string($connect, $indexName);
        $res = @mysqli_query($connect, "SHOW INDEX FROM `{$table}` WHERE Key_name = '{$escaped}'");
        return $res && mysqli_num_rows($res) > 0;
    }
}

if (!function_exists('installer_sql_is_add_column_if_not_exists')) {
    function installer_sql_is_add_column_if_not_exists($sql)
    {
        return stripos((string) $sql, 'ADD COLUMN IF NOT EXISTS') !== false;
    }
}

if (!function_exists('installer_sql_is_add_index_if_not_exists')) {
    function installer_sql_is_add_index_if_not_exists($sql)
    {
        return (bool) preg_match('/ADD\s+(?:UNIQUE\s+)?(?:KEY|INDEX)\s+IF\s+NOT\s+EXISTS/i', (string) $sql);
    }
}

if (!function_exists('installer_run_single_sql')) {
    function installer_run_single_sql($connect, $sql, array &$errors, $label = '')
    {
        try {
            if (@mysqli_query($connect, $sql)) {
                return;
            }
        } catch (Throwable $e) {
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
}

if (!function_exists('installer_run_add_columns_compat')) {
    function installer_run_add_columns_compat($connect, $sql, array &$errors, $label = '', ?array &$notes = null)
    {
        $sql = trim((string) $sql);
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
            // Drop AFTER `col` — missing reference columns break ADD on partial installs.
            $definition = preg_replace('/\s+AFTER\s+`?[a-zA-Z0-9_]+`?/i', '', $definition);
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
}

if (!function_exists('installer_run_add_index_compat')) {
    function installer_run_add_index_compat($connect, $sql, array &$errors, $label = '', ?array &$notes = null)
    {
        $sql = trim((string) $sql);
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
}

if (!function_exists('installer_run_migration_sql_compat')) {
    function installer_run_migration_sql_compat($connect, $sql, array &$errors, $label = '', ?array &$notes = null)
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
}
