<?php
ob_start();
require_once __DIR__ . '/../../includes/lib-initialize.php';
$title = ($lang['Import data'] ?? 'Import data') . ' | ' . $syatem_title;
include __DIR__ . '/../../templates/header.php';
require_once __DIR__ . '/_auth.php';
$migrationTab = 'import';
require_once __DIR__ . '/includes/migration-ui.php';
require_once __DIR__ . '/includes/migration-action-cards.php';

$migrationToolbarH1 = $lang['Import data'] ?? 'Import data';
$migrationToolbarCtaHref = rtrim($url, '/') . '/import-export/migration/import/';
$migrationToolbarCtaLabel = $lang['Import data'] ?? 'Import data';

$id = $session->userId;
$user = User::findById((int)$id);
$username = $user->firstName;
$email = $user->email;
$account_stat = $user->status;

$usersWizard = rtrim($url, '/') . '/import-export/users/';
$stubBase = 'module-placeholder?flow=import&resource=';
$stubDesc = $lang['CSV wizard — next step will be added here.'] ?? 'CSV wizard — next step will be added here.';

$renderImportCard = $renderMigrationActionCard;
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
                        <h2 class="migration-panel-title text-start"><?php echo $h($lang['Choose what to import'] ?? 'Choose what to import'); ?></h2>
                    </div>

                    <?php
                    $settings = settings::findById(1);
                    $isFreeEdition = function_exists('tasksession_is_free_edition') && tasksession_is_free_edition();
                    $renderImportCard($D_CLIENTS, $lang['Clients'] ?? 'Clients', $lang['CSV user import as clients'] ?? 'CSV import as clients (users)', $usersWizard . '?type=client', $isAdmin);
                    $renderImportCard($D_STAFF, $lang['Staff'] ?? 'Staff', $lang['CSV user import as staff'] ?? 'CSV import as staff', $usersWizard . '?type=staff', $isAdmin);
                    $renderImportCard($D_ADMIN, $lang['Admins'] ?? 'Admins', $lang['CSV user import as admins'] ?? 'CSV import as administrators', $usersWizard . '?type=admin', $isAdmin);
                    if (!$isFreeEdition) {
                        $companiesTableOk = false;
                        $tqCompanies = @mysqli_query($connect, "SHOW TABLES LIKE 'client_companies'");
                        if ($tqCompanies && mysqli_num_rows($tqCompanies) > 0) {
                            $companiesTableOk = true;
                        }
                        if ($tqCompanies) {
                            mysqli_free_result($tqCompanies);
                        }
                        $companiesImport = rtrim($url, '/') . '/import-export/companies/';
                        $renderImportCard(
                            $D_COMPANIES,
                            $lang['Companies'] ?? 'Companies',
                            $lang['CSV client companies import'] ?? 'Import client companies from CSV (details, addresses, billing, linked client user IDs).',
                            $companiesTableOk ? $companiesImport : '#',
                            $isAdmin && $companiesTableOk,
                            null,
                            $companiesTableOk ? null : ($lang['Client companies table not available'] ?? 'Client companies not available on this database')
                        );
                    }
                    $projectsImport = rtrim($url, '/') . '/import-export/projects/';
                    $tasksImport = rtrim($url, '/') . '/import-export/tasks/';
                    $renderImportCard(
                        $D_PROJECTS,
                        $lang['Projects'] ?? 'Projects',
                        $lang['CSV project import'] ?? 'Import projects from CSV (clients, companies, team groups, custom fields).',
                        $projectsImport,
                        $isAdmin,
                        null,
                        $lang['Admins only'] ?? 'Admins only'
                    );
                    $renderImportCard(
                        $D_TASKS,
                        $lang['Tasks'] ?? 'Tasks',
                        $lang['CSV task import'] ?? 'Import tasks from CSV (project or internal, assignees, custom fields).',
                        $tasksImport,
                        $isAdmin,
                        null,
                        $lang['Admins only'] ?? 'Admins only'
                    );
                    if (!$isFreeEdition) {
                        $chatsImport = rtrim($url, '/') . '/import-export/chats/';
                        $renderImportCard(
                            $D_CHAT,
                            $lang['Chats'] ?? 'Chats',
                            $lang['ZIP chat import (1:1, discussion, group, task)'] ?? 'Import chats from a ZIP package (1:1, project discussion, group, task messages).',
                            $chatsImport,
                            $isAdmin,
                            null,
                            $lang['Admins only'] ?? 'Admins only'
                        );
                        $invoicesImport = rtrim($url, '/') . '/import-export/invoices/';
                        $invoicesEnabled = $settings && !empty($settings->module_invoices);
                        $renderImportCard(
                            $D_INVOICE,
                            $lang['Invoices'] ?? 'Invoices',
                            $invoicesEnabled
                                ? ($lang['Two-file CSV: invoices + line items'] ?? 'Import invoices from two CSV (or XLSX) files: one file per invoice row, one for line items linked by External ID.')
                                : ($lang['Invoices module is disabled in settings'] ?? 'Invoices module is disabled in System settings.'),
                            $invoicesEnabled ? $invoicesImport : '#',
                            $invoicesEnabled,
                            null,
                            $invoicesEnabled ? null : ($lang['Enable the Invoices module'] ?? 'Enable the Invoices module')
                        );
                    }
                    $notesImport = rtrim($url, '/') . '/import-export/notes/';
                    $renderImportCard(
                        $D_NOTES,
                        $lang['Notes & Docs'] ?? 'Notes & Docs',
                        $lang['Import private, profile, and project docs from CSV'] ?? 'Import private, profile, and project docs from CSV in a single wizard.',
                        $notesImport,
                        $isAdmin,
                        null,
                        $lang['Admins only'] ?? 'Admins only'
                    );
                    if (!$isFreeEdition) {
                        $leadsOk = $settings && !empty($settings->module_lead_board) && ($isAdmin || has_permission('lead_import'));
                        if ($leadsOk) {
                            $renderImportCard(
                                $D_LEADS,
                                $lang['Leads'] ?? 'Leads',
                                $lang['Leads stay in the dedicated lead import tool.'] ?? 'Leads stay in the dedicated lead import tool.',
                                '../leads/',
                                true,
                                null,
                                null
                            );
                        } else {
                            $renderImportCard(
                                $D_LEADS,
                                $lang['Leads'] ?? 'Leads',
                                $lang['Leads stay in the dedicated lead import tool.'] ?? 'Leads stay in the dedicated lead import tool.',
                                '#',
                                false,
                                null,
                                $lang['Lead board disabled or no permission'] ?? 'Lead board disabled or no permission'
                            );
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/main-footer.php'; ?>
