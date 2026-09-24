<?php
ob_start();
require_once __DIR__ . '/../../includes/lib-initialize.php';
$title = ($lang['Data migration'] ?? 'Data migration') . ' | ' . $syatem_title;
include __DIR__ . '/../../templates/header.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/includes/migration-ui.php';

$id = $session->userId;
$user = User::findById((int)$id);
$username = $user->firstName;
$email = $user->email;
$account_stat = $user->status;

$supportIconUrl = rtrim($url, '/') . '/assets/images/migration-tasksession-support.png';
$migrationPanelHeroUrl = MIGRATION_ASSETS_URL . 'images/migration-panel-hero.png';
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

                <div class="row mt-3 center-col">
                    <div class="col-12 migration-intro">
                        <div class="migration-panel-hero mb-3">
                            <img src="<?php echo $h($migrationPanelHeroUrl); ?>" alt="<?php echo $h($lang['Data migration'] ?? 'Data migration'); ?>" class="migration-panel-hero__img">
                        </div>
                        <h2 class="migration-panel-title"><?php echo $h($lang['Data migration panel'] ?? 'Data migration panel'); ?></h2>
                        <p class="text-muted migration-panel-lead mb-3"><?php echo $h($lang['Move data in or out of your workspace with guided CSV flows and a full import history.'] ?? 'Move data in or out of your workspace with guided CSV flows and a full import history.'); ?></p>
                    </div>
                    <div class="col-md-6 mb-3 d-flex">
                        <a class="migration-action-link w-100" href="import/">
                            <div class="settings-card h-100 w-100">
                                <div class="card-body migration-action-card-body">
                                    <div class="migration-action-icon migration-action-icon--import" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="28" height="28">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                        </svg>
                                    </div>
                                    <h3><?php echo $h($lang['Import'] ?? 'Import'); ?></h3>
                                    <p class="text-muted small migration-action-card-text mb-0"><?php echo $h($lang['Upload a CSV or Excel file, match columns to your CRM fields, preview the result, then bring your data in safely.'] ?? 'Upload a CSV or Excel file, match columns to your CRM fields, preview the result, then bring your data in safely.'); ?></p>
                                    <span class="btn primary-btn btn-sm align-self-start mt-3"><?php echo $h($lang['Start import'] ?? 'Start import'); ?></span>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-6 mb-3 d-flex">
                        <a class="migration-action-link w-100" href="export/">
                            <div class="settings-card h-100 w-100">
                                <div class="card-body migration-action-card-body">
                                    <div class="migration-action-icon migration-action-icon--export" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="28" height="28">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                                        </svg>
                                    </div>
                                    <h3><?php echo $h($lang['Export'] ?? 'Export'); ?></h3>
                                    <p class="text-muted small migration-action-card-text mb-0"><?php echo $h($lang['Download CSV exports for backups, reporting, or moving data to another tool.'] ?? 'Download CSV exports for backups, reporting, or moving data to another tool.'); ?></p>
                                    <span class="btn primary-btn btn-sm align-self-start mt-3"><?php echo $h($lang['Open export'] ?? 'Open export'); ?></span>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-12 mb-3">
                        <a class="migration-action-link migration-action-link-support" href="https://www.tasksession.com/ticket/" target="_blank" rel="noopener noreferrer">
                            <div class="settings-card h-100">
                                <div class="card-body migration-action-card-body">
                                    <div class="migration-action-brand">
                                        <img src="<?php echo $h($supportIconUrl); ?>" width="44" height="44" alt="<?php echo $h($lang['TaskSession'] ?? 'TaskSession'); ?>">
                                    </div>
                                    <h3><?php echo $h($lang['Support Migration Team'] ?? 'Support Migration Team'); ?></h3>
                                    <p class="text-muted small mb-0"><?php echo $h($lang['Need help importing data from Zoho or another CRM? Our migration team can assist with field mapping and large data transfers.'] ?? 'Need help importing data from Zoho or another CRM? Our migration team can assist with field mapping and large data transfers.'); ?></p>
                                    <span class="btn primary-btn btn-sm align-self-start mt-3"><?php echo $h($lang['Open support ticket'] ?? 'Open support ticket'); ?></span>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/main-footer.php'; ?>
