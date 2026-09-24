<?php
/**
 * Ensures theme_settings font columns and custom_fonts table exist (idempotent).
 *
 * @param mysqli $db
 */
function ensure_theme_font_schema($db) {
    static $done = false;
    if ($done || !$db) {
        return;
    }
    $done = true;

    if (method_exists($db, 'querySoft')) {
        $res = $db->querySoft('SHOW COLUMNS FROM theme_settings');
    } else {
        $res = @mysqli_query($db->connection, 'SHOW COLUMNS FROM theme_settings');
    }
    if (!$res || !method_exists($res, 'fetch_assoc')) {
        return;
    }
    $existing = array();
    while ($row = $res->fetch_assoc()) {
        $existing[$row['Field']] = true;
    }
    $res->free();

    $font_columns = array(
        'ui_font_source' => "VARCHAR(20) NOT NULL DEFAULT 'default'",
        'ui_google_font_family' => "VARCHAR(120) NOT NULL DEFAULT 'Lato'",
        'ui_google_font_weights' => "VARCHAR(80) NOT NULL DEFAULT '400;700;900'",
        'ui_websafe_stack' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'ui_custom_font_id' => 'INT UNSIGNED NULL DEFAULT NULL',
    );

    foreach ($font_columns as $column => $definition) {
        if (empty($existing[$column])) {
            $alterSql = "ALTER TABLE theme_settings ADD COLUMN $column $definition";
            if (method_exists($db, 'querySoft')) {
                $db->querySoft($alterSql);
            } else {
                @mysqli_query($db->connection, $alterSql);
            }
        }
    }

    if (method_exists($db, 'querySoft')) {
        $t = $db->querySoft("SHOW TABLES LIKE 'custom_fonts'");
    } else {
        $t = @mysqli_query($db->connection, "SHOW TABLES LIKE 'custom_fonts'");
    }
    if ($t && $t->num_rows === 0) {
        $createSql = "CREATE TABLE `custom_fonts` (
              `id` int unsigned NOT NULL AUTO_INCREMENT,
              `family_css` varchar(120) NOT NULL,
              `font_weight` int NOT NULL DEFAULT 400,
              `is_italic` tinyint(1) NOT NULL DEFAULT 0,
              `file_path` varchar(255) NOT NULL,
              `original_filename` varchar(255) NOT NULL DEFAULT '',
              `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_family` (`family_css`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (method_exists($db, 'querySoft')) {
            $db->querySoft($createSql);
        } else {
            @mysqli_query($db->connection, $createSql);
        }
    }
    if ($t) {
        $t->free();
    }
}
