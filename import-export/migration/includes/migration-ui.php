<?php
/**
 * Shared helpers & defaults for import-export/migration/* pages.
 * Include after _auth.php (needs $isAdmin, $connect, permissions).
 */
if (!isset($h) || !is_callable($h)) {
    $h = static function ($s) {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    };
}
if (!isset($migrationTab)) {
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    $dir = strtolower(basename(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))));
    // Pretty routes: /migration/import/index.php → tab import
    if (in_array($dir, array('import', 'export'), true) && strcasecmp($script, 'index.php') === 0) {
        $migrationTab = $dir;
    } else {
        $migrationTab = preg_replace('/\.php$/i', '', $script);
        if ($migrationTab === 'index' || $migrationTab === '') {
            $migrationTab = 'overview';
        }
    }
}
if (!isset($leadModule) || !isset($canLead)) {
    $settingsMigrationUi = settings::findById(1);
    $leadModule = $settingsMigrationUi && !empty($settingsMigrationUi->module_lead_board);
    $canLead = $isAdmin || has_permission('lead_import') || has_permission('lead_export');
}
if (!defined('MIGRATION_ASSETS_URL')) {
    define('MIGRATION_ASSETS_URL', rtrim($url, '/') . '/import-export/assets/');
}
