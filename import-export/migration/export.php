<?php
ob_start();
require_once __DIR__ . '/../../includes/lib-initialize.php';
$title = ($lang['Export data'] ?? 'Export data') . ' | ' . $syatem_title;
include __DIR__ . '/../../templates/header.php';
require_once __DIR__ . '/_auth.php';
$migrationTab = 'export';
require_once __DIR__ . '/includes/migration-ui.php';
require_once __DIR__ . '/includes/migration-action-cards.php';

$migrationToolbarH1 = $lang['Export data'] ?? 'Export data';
$migrationToolbarCtaHref = rtrim($url, '/') . '/import-export/migration/export/';
$migrationToolbarCtaLabel = $lang['Export data'] ?? 'Export data';

$id = $session->userId;
$user = User::findById((int)$id);
$username = $user->firstName;
$email = $user->email;
$account_stat = $user->status;

$exportUsersBase = rtrim($url, '/') . '/import-export/users/export.php';
$stubBase = 'module-placeholder?flow=export&resource=';
$stubDesc = $lang['CSV export — next step will be added here.'] ?? 'CSV export — next step will be added here.';

$renderExportCard = $renderMigrationActionCard;
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
                        <h2 class="migration-panel-title text-start"><?php echo $h($lang['Choose what to export'] ?? 'Choose what to export'); ?></h2>
                    </div>

                    <?php
                    $settings = settings::findById(1);
                    $isFreeEdition = function_exists('tasksession_is_free_edition') && tasksession_is_free_edition();
                    $renderExportCard($D_CLIENTS, $lang['Clients'] ?? 'Clients', $lang['Export clients as CSV'] ?? 'Export clients as CSV', $exportUsersBase . '?type=client', $isAdmin);
                    $renderExportCard($D_STAFF, $lang['Staff'] ?? 'Staff', $lang['Export staff as CSV'] ?? 'Export staff as CSV', $exportUsersBase . '?type=staff', $isAdmin);
                    $renderExportCard($D_ADMIN, $lang['Admins'] ?? 'Admins', $lang['Export admins as CSV'] ?? 'Export admins as CSV', $exportUsersBase . '?type=admin', $isAdmin);
                    if (!$isFreeEdition) {
                        $companiesTableOk = false;
                        $tqCompaniesEx = @mysqli_query($connect, "SHOW TABLES LIKE 'client_companies'");
                        if ($tqCompaniesEx && mysqli_num_rows($tqCompaniesEx) > 0) {
                            $companiesTableOk = true;
                        }
                        if ($tqCompaniesEx) {
                            mysqli_free_result($tqCompaniesEx);
                        }
                        $companiesExportSample = rtrim($url, '/') . '/import-export/companies/export.php?sample=1';
                        $renderExportCard(
                            $D_COMPANIES,
                            $lang['Companies'] ?? 'Companies',
                            $lang['Export client companies as CSV'] ?? 'Download sample or export all client companies (with client IDs).',
                            $companiesTableOk ? $companiesExportSample : '#',
                            $isAdmin && $companiesTableOk,
                            $companiesTableOk ? ($lang['Download sample CSV'] ?? 'Download sample CSV') : null,
                            $companiesTableOk ? null : ($lang['Client companies table not available'] ?? 'Client companies not available on this database')
                        );
                    }
                    $projectsExport = rtrim($url, '/') . '/import-export/projects/export.php';
                    $tasksExport = rtrim($url, '/') . '/import-export/tasks/export.php';
                    $renderExportCard(
                        $D_PROJECTS,
                        $lang['Projects'] ?? 'Projects',
                        $lang['Download projects CSV'] ?? 'Download projects as CSV (admin).',
                        $projectsExport,
                        $isAdmin,
                        $lang['Download projects CSV'] ?? 'Download projects CSV',
                        $lang['Admins only'] ?? 'Admins only'
                    );
                    $renderExportCard(
                        $D_TASKS,
                        $lang['Tasks'] ?? 'Tasks',
                        $lang['Download tasks CSV'] ?? 'Download tasks as CSV (admin).',
                        $tasksExport,
                        $isAdmin,
                        $lang['Download tasks CSV'] ?? 'Download tasks CSV',
                        $lang['Admins only'] ?? 'Admins only'
                    );
                    if (!$isFreeEdition) {
                        $chatsZipExport = rtrim($url, '/') . '/import-export/chats/export.php';
                        $renderExportCard(
                            $D_CHAT,
                            $lang['Chats'] ?? 'Chats',
                            $lang['Chat ZIP export (template or live)'] ?? 'Download manifest v1 ZIP (template). Append ?live=1 for recent messages (admin).',
                            $chatsZipExport,
                            $isAdmin,
                            $lang['Download chat ZIP'] ?? 'Download chat ZIP',
                            $lang['Admins only'] ?? 'Admins only'
                        );
                        $invoicesTmpl = rtrim($url, '/') . '/import-export/invoices/export.php?sample=1';
                        $invoicesExportEnabled = $settings && !empty($settings->module_invoices);
                        $renderExportCard(
                            $D_INVOICE,
                            $lang['Invoices'] ?? 'Invoices',
                            $invoicesExportEnabled
                                ? ($lang['One CSV per line item; repeat External ID (import page + sample).'] ?? 'One CSV: each row is a line; repeat the same invoice on each line. Open import or download sample.')
                                : ($lang['Invoices module is disabled in settings'] ?? 'Invoices module is disabled in System settings.'),
                            $invoicesExportEnabled ? $invoicesTmpl : '#',
                            $invoicesExportEnabled,
                            $invoicesExportEnabled ? ($lang['Download sample CSV'] ?? 'Download sample CSV') : null,
                            $invoicesExportEnabled ? null : ($lang['Enable the Invoices module'] ?? 'Enable the Invoices module')
                        );
                    }
                    $notesExport = rtrim($url, '/') . '/import-export/notes/export.php?all=1';
                    $renderExportCard(
                        $D_NOTES,
                        $lang['Notes & Docs'] ?? 'Notes & Docs',
                        $lang['Export all private, profile, and project docs as CSV'] ?? 'Export all private, profile, and project docs as one CSV.',
                        $notesExport,
                        $isAdmin,
                        $lang['Download notes CSV'] ?? 'Download notes CSV',
                        $lang['Admins only'] ?? 'Admins only'
                    );
                    if (!$isFreeEdition) {
                        $leadsExportOk = $settings && !empty($settings->module_lead_board) && ($isAdmin || has_permission('lead_export'));
                        if ($leadsExportOk) {
                            $renderExportCard(
                                $D_LEADS,
                                $lang['Leads'] ?? 'Leads',
                                $lang['Leads export uses the dedicated lead export tool.'] ?? 'Leads export uses the dedicated lead export tool.',
                                '../leads/export.php',
                                true,
                                $lang['Download leads CSV'] ?? 'Download leads CSV',
                                null
                            );
                        } else {
                            $renderExportCard(
                                $D_LEADS,
                                $lang['Leads'] ?? 'Leads',
                                $lang['Leads export uses the dedicated lead export tool.'] ?? 'Leads export uses the dedicated lead export tool.',
                                '#',
                                false,
                                null,
                                $lang['Lead export not available'] ?? 'Lead export not available'
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
