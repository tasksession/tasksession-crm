<?php
ob_start();
require_once __DIR__ . '/../includes/lib-initialize.php';

$title = ($lang['Import history'] ?? 'Import history') . ' | ' . $syatem_title;
include __DIR__ . '/../templates/header.php';

if (!$session->isLoggedIn()) {
    redirectTo($url . 'index.php');
}
$isAdmin = isset($_SESSION['accountStatus']) && (int)$_SESSION['accountStatus'] === 1;
if (!$isAdmin) {
    redirectTo($url . 'unauthorized.php');
}

require_once __DIR__ . '/../includes/permissions.php';
ensure_user_permissions($connect);

require_once __DIR__ . '/migration/includes/migration-ui.php';

require_once __DIR__ . '/../includes/import-export/bootstrap.php';
$jobs = Comon_IE_ImportJobService::listForUser($connect, (int)$session->userId, $isAdmin, 80);

$id = $session->userId;
$user = User::findById((int)$id);
$username = $user->firstName;
$email = $user->email;
$account_stat = $user->status;

$migrationTab = 'history';
$migrationToolbarH1 = $lang['Import history'] ?? 'Import history';

$migrationToolbarCtaHref = rtrim($url, '/') . '/import-export/migration/import/';
$migrationToolbarCtaLabel = $lang['Import data'] ?? 'Import data';
$migrationToolbarHidePrimaryCta = true;
?>

<link rel="stylesheet" href="<?php echo $h(MIGRATION_ASSETS_URL); ?>css/lead-import-export.css?v=<?php echo time(); ?>">

<div class="page-container">
    <div class="container-fluid">
        <div class="row row-eq-height">
            <?php include __DIR__ . '/../templates/sidebar.php'; ?>
            <div class="page-content">
                <?php include __DIR__ . '/../templates/top-header.php'; ?>

<?php include __DIR__ . '/assets/templates/migration-toolbar.php'; ?>

                <div class="row pd-15">
                    <div class="col-12 pd-0">
                        <div class="clearfix"></div>
                        <div class="vh-100">
                            <div class="table-responsive scroll-x vh-100">
                                <table class="table table-new projectspage mb-0" data-pagination="true" data-page-size="15">
                                    <thead>
                                        <tr>
                                            <th class="text-muted" style="width:2.75rem;"><?php echo $h($lang['No.'] ?? 'No.'); ?></th>
                                            <th>ID</th>
                                            <th><?php echo $h($lang['Module'] ?? 'Module'); ?></th>
                                            <th><?php echo $h($lang['Type'] ?? 'Type'); ?></th>
                                            <th><?php echo $h($lang['File'] ?? 'File'); ?></th>
                                            <th><?php echo $h($lang['Status'] ?? 'Status'); ?></th>
                                            <th><?php echo $h($lang['Rows'] ?? 'Rows'); ?></th>
                                            <th><?php echo $h($lang['OK'] ?? 'OK'); ?></th>
                                            <th><?php echo $h($lang['Failed'] ?? 'Failed'); ?></th>
                                            <th><?php echo $h($lang['Skipped'] ?? 'Skipped'); ?></th>
                                            <th><?php echo $h($lang['Updated'] ?? 'Updated'); ?></th>
                                            <th><?php echo $h($lang['Started'] ?? 'Started'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="projects-tbl">
                                        <?php
                                        $moduleLabel = static function (string $m): string {
                                            $map = [
                                                'chat' => 'Chat (ZIP)',
                                                'project' => 'Project',
                                                'task' => 'Task',
                                                'user' => 'User',
                                                'lead' => 'Lead',
                                                'invoice' => 'Invoice (2 files)',
                                                'notes' => 'Notes & Docs',
                                            ];
                                            return $map[$m] ?? $m;
                                        };
                                        ?>
                                        <?php $rowNo = 1; foreach ($jobs as $j): ?>
                                            <tr>
                                                <td class="text-muted"><?php echo $rowNo++; ?></td>
                                                <td><?php echo (int)$j['id']; ?></td>
                                                <td><?php echo $h($moduleLabel((string)$j['module_name'])); ?></td>
                                                <td><?php echo $h((string)($j['target_user_type'] ?? '')); ?></td>
                                                <td><?php echo $h((string)$j['original_filename']); ?></td>
                                                <td><?php echo $h((string)$j['status']); ?></td>
                                                <td><?php echo (int)$j['total_rows']; ?></td>
                                                <td><?php echo (int)$j['success_rows']; ?></td>
                                                <td><?php echo (int)$j['failed_rows']; ?></td>
                                                <td><?php echo (int)$j['skipped_rows']; ?></td>
                                                <td><?php echo (int)$j['updated_rows']; ?></td>
                                                <td><?php echo $h((string)($j['started_at'] ?? $j['created_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (count($jobs) === 0): ?>
                                            <tr><td colspan="12"><?php echo $h($lang['No import jobs yet'] ?? 'No import jobs yet'); ?></td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <p class="text-muted small mt-3 mb-2 px-2 px-md-0">
                            <?php echo $h($lang['Import history help'] ?? 'User and lead CSV/Excel imports appear here (file, module, row counts, status, time). Immediate runs and background queue jobs are both listed.'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/main-footer.php'; ?>
