<?php
/**
 * Reusable migration sub-header (project-tabs style).
 * Expects: $lang, $h (callable), $migrationTab, $leadModule, $canLead
 * Optional: $migrationToolbarH1, $migrationToolbarCtaHref, $migrationToolbarCtaLabel
 * Optional: $migrationToolbarExtraHtml (raw HTML for extra buttons in the toolbar row)
 * Optional: $migrationToolbarHidePrimaryCta (bool) hide the blue "Import data" / CTA button
 * Optional: $migrationToolbarHideHistoryNavTab (bool) hide Import history link in the tab strip (use ExtraHtml instead)
 *
 * Nav links use absolute pretty URLs under /import-export/ (see includes/pretty_url.php).
 * Module POST/download endpoints stay as *.php (import.php / export.php / ajax job_status).
 */
if (!isset($h) || !is_callable($h)) {
    $h = static function ($s) {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    };
}
$migrationToolbarH1 = $migrationToolbarH1 ?? ($lang['Data migration'] ?? 'Data migration');
$migrationToolbarCtaLabel = $migrationToolbarCtaLabel ?? ($lang['Import data'] ?? 'Import data');
$migrationToolbarExtraHtml = $migrationToolbarExtraHtml ?? '';
$migrationToolbarHidePrimaryCta = !empty($migrationToolbarHidePrimaryCta);
$migrationToolbarHideHistoryNavTab = !empty($migrationToolbarHideHistoryNavTab);
$importExportBaseUrl = rtrim((string) ($url ?? ''), '/') . '/import-export/';
$migrationBaseUrl = $importExportBaseUrl . 'migration/';
$migrationOverviewHref = $migrationBaseUrl;
$migrationImportHref = $migrationBaseUrl . 'import/';
$migrationExportHref = $migrationBaseUrl . 'export/';
$migrationHistoryHref = $importExportBaseUrl . 'history';
$migrationToolbarCtaHref = $migrationToolbarCtaHref ?? $migrationImportHref;

$migrationTabRaw = (string) ($migrationTab ?? '');
$migrationTabKey = strtolower(preg_replace('/\.php$/i', '', basename(str_replace('\\', '/', $migrationTabRaw))));
if ($migrationTabKey === 'index' || $migrationTabKey === '' || $migrationTabKey === 'overview') {
    $migrationTabKey = 'overview';
}
?>
                <div class="row bg-grey">
                    <div class="col-md-12 project-tabs">
                        <div class="row">
                            <div class="project-tabs-header">
                                <div class="scrollable-tabs-container d-flex col-gap-40 col-gap-40-sep">
                                    <div class="main-heading">
                                        <h1><?php echo $h($migrationToolbarH1); ?></h1>
                                    </div>
                                    <div class="icon-container sep">
                                        <a href="<?php echo $h($migrationOverviewHref); ?>" class="<?php echo $migrationTabKey === 'overview' ? 'active' : ''; ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                                            </svg>
                                            <?php echo $h($lang['Overview'] ?? 'Overview'); ?>
                                        </a>
                                        <a href="<?php echo $h($migrationImportHref); ?>" class="<?php echo $migrationTabKey === 'import' ? 'active' : ''; ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                            </svg>
                                            <?php echo $h($lang['Import'] ?? 'Import'); ?>
                                        </a>
                                        <a href="<?php echo $h($migrationExportHref); ?>" class="<?php echo $migrationTabKey === 'export' ? 'active' : ''; ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                                            </svg>
                                            <?php echo $h($lang['Export'] ?? 'Export'); ?>
                                        </a>
                                        <?php if (!$migrationToolbarHideHistoryNavTab): ?>
                                        <a href="<?php echo $h($migrationHistoryHref); ?>" class="<?php echo $migrationTabKey === 'history' ? 'active' : ''; ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                            </svg>
                                            <?php echo $h($lang['Import history'] ?? 'Import history'); ?>
                                        </a>
                                        <?php endif; ?>
                                        <?php if ($leadModule && $canLead): ?>
                                        <a href="<?php echo $h($importExportBaseUrl . 'leads/'); ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" />
                                            </svg>
                                            <?php echo $h($lang['Leads'] ?? 'Leads'); ?>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="project-tabs-toolbar-end d-flex align-items-center flex-wrap justify-content-end col-gap">
                                <?php if ($migrationToolbarExtraHtml !== ''): ?>
                                <div class="d-flex col-gap align-items-center flex-wrap justify-content-end ms-md-auto">
                                    <?php echo $migrationToolbarExtraHtml; ?>
                                </div>
                                <?php endif; ?>
                                <?php if (!$migrationToolbarHidePrimaryCta): ?>
                                <div class="edit-overview-btn">
                                    <div class="d-none d-md-block">
                                        <a href="<?php echo $h($migrationToolbarCtaHref); ?>" class="btn primary-btn">
                                            <?php echo $h($migrationToolbarCtaLabel); ?>
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($migrationToolbarHidePrimaryCta && $migrationToolbarExtraHtml !== ''): ?>
                        <div class="d-md-none mt-2">
                            <div class="d-flex col-gap align-items-center flex-wrap">
                                <?php echo $migrationToolbarExtraHtml; ?>
                            </div>
                        </div>
                        <?php elseif (!$migrationToolbarHidePrimaryCta): ?>
                        <div class="d-md-none mt-2">
                            <div class="edit-overview-btn">
                                <a href="<?php echo $h($migrationToolbarCtaHref); ?>" class="btn primary-btn w-100"><?php echo $h($migrationToolbarCtaLabel); ?></a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
