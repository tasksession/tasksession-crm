<?php
if (empty($companyRow) || !is_array($companyRow)) {
    return;
}
$cname = htmlspecialchars((string) ($companyRow['name'] ?? ''), ENT_QUOTES, 'UTF-8');
$createdAt = !empty($companyRow['created_at']) ? date('M j, Y', strtotime((string) $companyRow['created_at'])) : '—';
$createdMeta = htmlspecialchars(($lang['Created'] ?? 'Created') . ': ' . $createdAt, ENT_QUOTES, 'UTF-8');
if ($companyCreatorName !== '') {
    $createdMeta .= ' · ' . htmlspecialchars(($lang['Created by'] ?? 'Created by') . ': ' . $companyCreatorName, ENT_QUOTES, 'UTF-8');
}
$companyAddr = htmlspecialchars(company_format_address_block($companyRow), ENT_QUOTES, 'UTF-8');
$billingAddr = htmlspecialchars(company_format_address_block($companyRow, 'billing'), ENT_QUOTES, 'UTF-8');
$initial = strtoupper(substr((string) ($companyRow['name'] ?? 'C'), 0, 1));
?>
<div class="filter-btn">
    <button class="sidebar-toggle primary-btn d-md-none"><?php echo htmlspecialchars($lang['View company details'] ?? 'View company details', ENT_QUOTES, 'UTF-8'); ?></button>
</div>
<div class="sidebar-overlay"></div>
<div class="sidebar-admin col-xl-3 col-lg-4 col-md-12 bg-white max-w-400 full-heights left-col project-sidebar" id="project-sidebar">
    <div class="cross-mobile">
        <?php echo ts_icon('close', 'w-4'); ?>
    </div>
    <button class="sidebar-shrink-btn" id="projectSidebarShrinkBtn" title="Shrink sidebar">
        <?php echo ts_icon('chevron-left', 'w-2'); ?>
    </button>
    <div class="sidebar-shirk"></div>
    <div class="scroll-bar mb-height pd-30 stikcy-sidebar" style="height: calc(100vh);">
        <div class="cs-card">
            <div class="card-body pt-0">
                <div class="text-center profile-image">
                    <div class="client-company-view-modal__hero d-flex align-items-center justify-content-center flex-column mb-3">
                        <div class="client-company-view-modal__logo-wrap flex-shrink-0 mb-2">
                            <img src="<?php echo htmlspecialchars($companyLogoUrl !== '' ? $companyLogoUrl : $companyLogoPlaceholder, ENT_QUOTES, 'UTF-8'); ?>" alt="" class="client-company-view-modal__logo-img img-fluid rounded">
                        </div>
                        <h2 class="user-name card-title font-size-20"><?php echo $cname; ?></h2>
                        <p class="user-id text-muted mb-2 small"><?php echo $createdMeta; ?></p>
                    </div>
                </div>
                <div class="client-company-view-modal__details text-start">
                    <div class="summary-section mb-3">
                        <div class="lead-data mt-3">
                            <div class="summary-item d-flex flex-column align-items-start mb-3">
                                <span class="summary-label"><?php echo htmlspecialchars($lang['VAT Number'] ?? 'VAT', ENT_QUOTES, 'UTF-8'); ?>:</span>
                                <span class="summary-value text-break"><?php echo htmlspecialchars((string) ($companyRow['vat_number'] ?? '') ?: '—', ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="summary-item d-flex flex-column align-items-start mb-3">
                                <span class="summary-label"><?php echo htmlspecialchars($lang['Phone'] ?? 'Phone', ENT_QUOTES, 'UTF-8'); ?>:</span>
                                <span class="summary-value text-break"><?php echo htmlspecialchars((string) ($companyRow['phone'] ?? '') ?: '—', ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="summary-item d-flex flex-column align-items-start mb-3">
                                <span class="summary-label"><?php echo htmlspecialchars($lang['Email'] ?? 'Email', ENT_QUOTES, 'UTF-8'); ?>:</span>
                                <span class="summary-value text-break"><?php
                                    $em = trim((string) ($companyRow['email'] ?? ''));
                                    echo $em !== '' ? '<a href="mailto:' . htmlspecialchars($em, ENT_QUOTES, 'UTF-8') . '" class="client-company-view-modal__link">' . htmlspecialchars($em, ENT_QUOTES, 'UTF-8') . '</a>' : '—';
                                ?></span>
                            </div>
                            <div class="summary-item d-flex flex-column align-items-start mb-3">
                                <span class="summary-label"><?php echo htmlspecialchars($lang['Website'] ?? 'Website', ENT_QUOTES, 'UTF-8'); ?>:</span>
                                <span class="summary-value text-break"><?php
                                    $web = trim((string) ($companyRow['website'] ?? ''));
                                    if ($web === '') {
                                        echo '—';
                                    } else {
                                        $href = preg_match('/^https?:\/\//i', $web) ? $web : 'https://' . $web;
                                        echo '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer" class="client-company-view-modal__link">' . htmlspecialchars($web, ENT_QUOTES, 'UTF-8') . '</a>';
                                    }
                                ?></span>
                            </div>
                            <div class="summary-item d-flex flex-column align-items-start mb-3">
                                <span class="summary-label"><?php echo htmlspecialchars($lang['Currency'] ?? 'Currency', ENT_QUOTES, 'UTF-8'); ?>:</span>
                                <span class="summary-value text-break"><?php echo htmlspecialchars((string) ($companyRow['currency'] ?? '') ?: '—', ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <?php foreach ($companyCustomFieldViewRows as $cfRow):
                                $vt = trim((string) ($cfRow['value_text'] ?? ''));
                                if ($vt === '' || $vt === '—' || $vt === '-') {
                                    continue;
                                }
                            ?>
                            <div class="summary-item d-flex flex-column align-items-start mb-3">
                                <span class="summary-label"><?php echo htmlspecialchars((string) ($cfRow['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>:</span>
                                <span class="summary-value text-break"><?php echo htmlspecialchars($vt, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <hr class="client-company-view-modal__rule my-3">
                    <div class="row g-3 client-company-view-modal__addresses-row">
                        <div class="col-12">
                            <div class="client-company-view-modal__section-head mb-2"><?php echo htmlspecialchars($lang['Company Address'] ?? 'Company address', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="client-company-view-modal__company-address mb-3"><?php echo $companyAddr; ?></div>
                        </div>
                        <div class="col-12">
                            <div class="client-company-view-modal__section-head mb-2"><?php echo htmlspecialchars($lang['Billing address'] ?? 'Billing address', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="client-company-view-modal__billing" style="white-space:pre-line;"><?php echo $billingAddr; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
