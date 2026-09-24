<?php
/**
 * Placeholder for module import/export wizards (projects, tasks, chats, invoices).
 * Linked from import.php / export.php until full flows are implemented.
 */
ob_start();
require_once __DIR__ . '/../../includes/lib-initialize.php';

$flow = isset($_GET['flow']) && $_GET['flow'] === 'export' ? 'export' : 'import';
$resource = isset($_GET['resource']) ? (string) $_GET['resource'] : '';
$allowed = ['projects', 'tasks', 'chats', 'invoices'];
if (!in_array($resource, $allowed, true)) {
    header('Location: ' . ($flow === 'export' ? 'export/' : 'import/'));
    exit;
}

$migrationTab = $flow === 'export' ? 'export' : 'import';
$migrationToolbarH1 = $flow === 'export'
    ? ($lang['Export data'] ?? 'Export data')
    : ($lang['Import data'] ?? 'Import data');
$migrationToolbarCtaHref = rtrim($url ?? '', '/') . '/import-export/migration/' . $flow . '/';
$migrationToolbarCtaLabel = $migrationToolbarH1;

$resourceTitles = [
    'projects' => $lang['Projects'] ?? 'Projects',
    'tasks' => $lang['Tasks'] ?? 'Tasks',
    'chats' => $lang['Chats'] ?? 'Chats',
    'invoices' => $lang['Invoices'] ?? 'Invoices',
];
$rt = $resourceTitles[$resource] ?? $resource;
$title = ($flow === 'export'
    ? (($lang['Export'] ?? 'Export') . ' ' . $rt)
    : (($lang['Import'] ?? 'Import') . ' ' . $rt)) . ' | ' . $syatem_title;

include __DIR__ . '/../../templates/header.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/includes/migration-ui.php';

$backHref = $flow === 'export' ? 'export/' : 'import/';
$lead = $lang['This wizard is not wired up yet. You can build the next step here.'] ?? 'This wizard is not wired up yet. You can build the next step here.';
?>

<link rel="stylesheet" href="<?php echo $h(MIGRATION_ASSETS_URL); ?>css/lead-import-export.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="<?php echo $h(MIGRATION_ASSETS_URL); ?>css/migration.css?v=<?php echo time(); ?>">

<div class="page-container">
    <div class="container-fluid">
        <div class="row row-eq-height">
            <?php include __DIR__ . '/../../templates/sidebar.php'; ?>
            <div class="page-content">
                <?php include __DIR__ . '/../../templates/top-header.php'; ?>

<?php include __DIR__ . '/../assets/templates/migration-toolbar.php'; ?>

                <div class="row mt-3 center-col migration-import-grid">
                    <div class="col-12 mb-3">
                        <h2 class="migration-panel-title text-start"><?php echo $h($rt); ?></h2>
                        <p class="text-muted"><?php echo $h($lead); ?></p>
                        <a class="btn border-btn-a btn-sm" href="<?php echo $h($backHref); ?>"><?php echo $h($lang['Back'] ?? 'Back'); ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/main-footer.php'; ?>
