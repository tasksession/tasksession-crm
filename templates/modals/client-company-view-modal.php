<?php
if (!isset($lang) || !is_array($lang)) {
    $lang = [];
}
if (!isset($url)) {
    $url = '';
}
$ccvPlaceholder = (isset($url) && (string) $url !== '') ? rtrim((string) $url, '/') . '/assets/images/upload-img.jpg' : '';
?>
<div class="modal fade team-group-modal client-company-view-modal" id="clientCompanyViewModal" tabindex="-1" aria-labelledby="clientCompanyViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable client-company-view-modal__dialog">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center justify-content-between flex-wrap gap-2 w-100">
                <h5 class="modal-title mb-0" id="clientCompanyViewModalLabel"><?php echo htmlspecialchars($lang['Company profile'] ?? 'Company Profile'); ?></h5>
                <div class="d-flex align-items-center col-gap-10 ms-auto">
                    <div class="icons-btn d-flex">
                        <a href="#" class="prnintpage me-2 js-edit-client-company" id="clientCompanyViewEditBtn" data-company-id="" onclick="return false;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                            </svg>
                            <?php echo htmlspecialchars($lang['Edit company'] ?? 'Edit company'); ?>
                        </a>
                    </div>
                    <button type="button" class="btn-close me-0" data-bs-dismiss="modal" aria-label="<?php echo htmlspecialchars($lang['Close'] ?? 'Close'); ?>">
                        <?php echo ts_icon('close'); ?>
                    </button>
                </div>
            </div>
            <div class="modal-body client-company-view-modal__body modal-scroll">
                <div id="clientCompanyViewError" class="alert alert-danger" style="display:none;" role="alert"></div>
                <div class="client-company-view-modal__hero d-flex align-items-center">
                    <div class="client-company-view-modal__logo-wrap flex-shrink-0">
                        <img id="clientCompanyViewLogo" src="<?php echo htmlspecialchars($ccvPlaceholder, ENT_QUOTES, 'UTF-8'); ?>" data-cc-placeholder="<?php echo htmlspecialchars($ccvPlaceholder, ENT_QUOTES, 'UTF-8'); ?>" alt="" class="client-company-view-modal__logo-img">
                    </div>
                    <div class="client-company-view-modal__hero-text flex-shrink-0">
                        <h4 id="clientCompanyViewName" class="client-company-view-modal__name mb-1"></h4>
                        <div id="clientCompanyViewMeta" class="client-company-view-modal__meta"></div>
                    </div>
                </div>
                <div class="client-company-view-modal__details text-start">
                <div class="summary-section mb-3">
                    <div class="lead-data mt-3">
                        <div class="summary-item d-flex flex-column align-items-start mb-3">
                            <span class="summary-label"><?php echo htmlspecialchars($lang['VAT Number'] ?? 'VAT'); ?>:</span>
                            <span id="clientCompanyViewVat" class="summary-value text-break">—</span>
                        </div>
                        <div class="summary-item d-flex flex-column align-items-start mb-3">
                            <span class="summary-label"><?php echo htmlspecialchars($lang['Phone'] ?? 'Phone'); ?>:</span>
                            <span id="clientCompanyViewPhone" class="summary-value text-break">—</span>
                        </div>
                        <div class="summary-item d-flex flex-column align-items-start mb-3">
                            <span class="summary-label"><?php echo htmlspecialchars($lang['Email'] ?? 'Email'); ?>:</span>
                            <span id="clientCompanyViewEmail" class="summary-value text-break">—</span>
                        </div>
                        <div class="summary-item d-flex flex-column align-items-start mb-3">
                            <span class="summary-label"><?php echo htmlspecialchars($lang['Website'] ?? 'Website'); ?>:</span>
                            <span id="clientCompanyViewWebsite" class="summary-value text-break">—</span>
                        </div>
                        <div class="summary-item d-flex flex-column align-items-start mb-3">
                            <span class="summary-label"><?php echo htmlspecialchars($lang['Currency'] ?? 'Currency'); ?>:</span>
                            <span id="clientCompanyViewCurrency" class="summary-value text-break">—</span>
                        </div>
                        <div id="clientCompanyViewCustomFieldsSummary"></div>
                    </div>
                </div>
                <hr class="client-company-view-modal__rule my-3">
                <div class="row g-3 client-company-view-modal__addresses-row">
                    <div class="col-12 col-md-6">
                        <div class="client-company-view-modal__section-head mb-2"><?php echo htmlspecialchars($lang['Company Address'] ?? 'Company address'); ?></div>
                        <div id="clientCompanyViewCompanyAddress" class="client-company-view-modal__company-address mb-3">—</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="client-company-view-modal__section-head mb-2"><?php echo htmlspecialchars($lang['Billing address'] ?? 'Billing address'); ?></div>
                        <div id="clientCompanyViewBilling" class="client-company-view-modal__billing">—</div>
                    </div>
                </div>
                <hr class="client-company-view-modal__rule my-3">
                <div class="client-company-view-modal__section-head mb-2"><?php echo htmlspecialchars($lang['Linked clients'] ?? 'Linked clients'); ?></div>
                <ul id="clientCompanyViewMembers" class="list-unstyled mb-0 client-company-view-modal__members"></ul>
                </div>
            </div>
            <div class="modal-footer client-company-view-modal__footer d-flex justify-content-end align-items-center col-gap-10 flex-wrap">
                <button type="button" class="btn border-btn-a" data-bs-dismiss="modal"><?php echo htmlspecialchars($lang['Close'] ?? 'Close'); ?></button>
            </div>
        </div>
    </div>
</div>
<script>
window.__clientCompanyViewLang = {
    viewProfile: <?php echo json_encode($lang['View Profile'] ?? 'View profile'); ?>,
    noMembers: <?php echo json_encode($lang['No clients linked'] ?? 'No clients linked'); ?>,
    createdLabel: <?php echo json_encode($lang['Created'] ?? 'Created'); ?>,
    byLabel: <?php echo json_encode($lang['Created by'] ?? 'Created by'); ?>,
    primaryBadge: <?php echo json_encode($lang['Primary client badge'] ?? 'Main'); ?>
};
</script>
