<?php
if (!isset($companyPickableClients) || !is_array($companyPickableClients)) {
    $companyPickableClients = [];
}
if (!isset($settings) || !$settings) {
    $settings = settings::findById(1);
}
if (!isset($countries) || !is_array($countries)) {
    $countries = [];
}
$ccEnabledCurrencies = ($settings && is_array($settings->getMultipleCurrencies())) ? $settings->getMultipleCurrencies() : [];
$ccDefaultCurrency = ($settings && !empty($settings->system_currency)) ? (string) $settings->system_currency : '';
$ccCurrencySymbols = ($settings && is_array($settings->currency_symbols)) ? $settings->currency_symbols : [];

ob_start();
foreach ($ccCurrencySymbols as $currencyKey => $symbolLabel) {
    if (in_array($currencyKey, $ccEnabledCurrencies, true)) {
        $sel = ($currencyKey === $ccDefaultCurrency) ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($currencyKey, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>' . htmlspecialchars((string) $symbolLabel, ENT_QUOTES, 'UTF-8') . '</option>';
    }
}
$ccCurrencyOptionsHtml = ob_get_clean();

ob_start();
echo '<option value="">' . htmlspecialchars($lang['Select Country'] ?? 'Select Country', ENT_QUOTES, 'UTF-8') . '</option>';
foreach ($countries as $countrie) {
    echo '<option value="' . htmlspecialchars((string) $countrie, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars((string) $countrie, ENT_QUOTES, 'UTF-8') . '</option>';
}
$ccCountryOptionsHtml = ob_get_clean();
$ccLogoPlaceholder = '';
if (isset($url) && (string) $url !== '') {
    $ccLogoPlaceholder = rtrim((string) $url, '/') . '/assets/images/upload-img.jpg';
}
?>
<div class="modal fade team-group-modal" id="clientCompanyCreateModal" tabindex="-1" aria-labelledby="clientCompanyCreateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="clientCompanyCreateModalLabel"><?php echo htmlspecialchars($lang['Create company'] ?? 'Create company'); ?></h5>
                <button type="button" class="btn-close me-2" data-bs-dismiss="modal" aria-label="Close"><?php echo ts_icon('close'); ?></button>
            </div>
            <div class="modal-body modal-height">
                <div id="clientCompanyCreateError" class="alert alert-danger" style="display:none;" role="alert"></div>
                <div class="modal-tabs-scroll mb-3">
                    <ul class="nav nav-tabs flex-nowrap" role="tablist">
                        <li class="nav-item flex-shrink-0" role="presentation">
                            <button class="nav-link" id="clientCompanyCreateTabDetailsBtn" data-bs-toggle="tab" data-bs-target="#clientCompanyCreatePaneDetails" type="button" role="tab"><?php echo htmlspecialchars($lang['Company details'] ?? 'Details'); ?></button>
                        </li>
                        <li class="nav-item flex-shrink-0" role="presentation">
                            <button class="nav-link active" id="clientCompanyCreateTabClientsBtn" data-bs-toggle="tab" data-bs-target="#clientCompanyCreatePaneClients" type="button" role="tab"><?php echo htmlspecialchars($lang['Add clients tab'] ?? 'Add clients'); ?></button>
                        </li>
                        <li class="nav-item flex-shrink-0" role="presentation">
                            <button class="nav-link" id="clientCompanyCreateTabAddressBtn" data-bs-toggle="tab" data-bs-target="#clientCompanyCreatePaneAddress" type="button" role="tab"><?php echo htmlspecialchars($lang['Company Address'] ?? 'Company address'); ?></button>
                        </li>
                        <li class="nav-item flex-shrink-0" role="presentation">
                            <button class="nav-link" id="clientCompanyCreateTabBillingBtn" data-bs-toggle="tab" data-bs-target="#clientCompanyCreatePaneBilling" type="button" role="tab"><?php echo htmlspecialchars($lang['Billing address'] ?? 'Billing address'); ?></button>
                        </li>
                        <li class="nav-item flex-shrink-0" role="presentation">
                            <button class="nav-link" id="clientCompanyCreateTabLinkCompaniesBtn" data-bs-toggle="tab" data-bs-target="#clientCompanyCreatePaneLinkCompanies" type="button" role="tab"><?php echo htmlspecialchars($lang['Link companies'] ?? 'Link companies'); ?></button>
                        </li>
                        <li class="nav-item flex-shrink-0" role="presentation">
                            <button class="nav-link" id="clientCompanyCreateTabCustomBtn" data-bs-toggle="tab" data-bs-target="#clientCompanyCreatePaneCustom" type="button" role="tab"><?php echo htmlspecialchars($lang['Custom fields'] ?? 'Custom fields'); ?></button>
                        </li>
                    </ul>
                </div>
                <div class="tab-content">
                    <div class="tab-pane fade" id="clientCompanyCreatePaneDetails" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="mb-3 d-flex align-items-center col-gap client-company-logo-upload">
                                    <div class="client-company-logo-upload__picker" style="position: relative; display: inline-block; flex-shrink: 0;">
                                        <div class="img-uploadwrap">
                                            <img src="<?php echo htmlspecialchars($ccLogoPlaceholder, ENT_QUOTES, 'UTF-8'); ?>" class="img-fluid" id="clientCompanyCreateLogoPreview" data-cc-logo-placeholder="<?php echo htmlspecialchars($ccLogoPlaceholder, ENT_QUOTES, 'UTF-8'); ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;" />
                                        </div>
                                        <div class="pro-pic">
                                            <span style="font-size: 24px;">+</span>
                                            <input type="file" name="clientCompanyCreateLogo" class="pro-pic" id="clientCompanyCreateLogo" accept="image/*" style="opacity: 0; position: absolute; width: 100%; height: 100%; cursor: pointer; top: 0; left: 0;" />
                                        </div>
                                    </div>
                                    <div class="mt-0 max-width-300 flex-grow-1">
                                        <h4 class="client-company-logo-upload__title"><?php echo htmlspecialchars($lang['Change company logo'] ?? 'Upload logo'); ?></h4>
                                        <p class="client-company-logo-upload__hint"><?php echo htmlspecialchars($lang['Company logo image requirements'] ?? 'Company logo must be a .jpg .png file smaller than 10MB and at least 400px by 400px.'); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group">
                                    <label for="clientCompanyCreateName"><?php echo htmlspecialchars($lang['Company name'] ?? 'Company name'); ?>*</label>
                                    <input type="text" class="form-control" id="clientCompanyCreateName" maxlength="191" autocomplete="organization">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group">
                                    <label for="clientCompanyCreateVat"><?php echo htmlspecialchars($lang['VAT Number'] ?? 'VAT Number'); ?></label>
                                    <input type="text" class="form-control" id="clientCompanyCreateVat" maxlength="64" autocomplete="off">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group">
                                    <label for="clientCompanyCreatePhone"><?php echo htmlspecialchars($lang['Phone'] ?? 'Phone'); ?></label>
                                    <input type="text" class="form-control" id="clientCompanyCreatePhone" maxlength="64" autocomplete="tel">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group">
                                    <label for="clientCompanyCreateEmail"><?php echo htmlspecialchars($lang['Email'] ?? 'Email'); ?></label>
                                    <input type="email" class="form-control" id="clientCompanyCreateEmail" maxlength="191" autocomplete="email" placeholder="name@example.com">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group">
                                    <label for="clientCompanyCreateWebsite"><?php echo htmlspecialchars($lang['Website'] ?? 'Website'); ?></label>
                                    <input type="url" class="form-control" id="clientCompanyCreateWebsite" maxlength="512" placeholder="https://">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group">
                                    <label for="clientCompanyCreateCurrency"><?php echo htmlspecialchars($lang['Currency'] ?? 'Currency'); ?>*</label>
                                    <select class="form-control" id="clientCompanyCreateCurrency" required><?php echo $ccCurrencyOptionsHtml; ?></select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade show active" id="clientCompanyCreatePaneClients" role="tabpanel" aria-labelledby="clientCompanyCreateTabClientsBtn">
                        <p class="text-muted small mb-2"><?php echo htmlspecialchars($lang['Add clients optional'] ?? 'Add clients (optional)'); ?></p>
                        <p class="text-muted small mb-3"><?php echo htmlspecialchars($lang['Primary client invoices hint'] ?? 'Use Main on the right to set the invoice contact when several clients are linked.'); ?></p>
                        <div class="form-group">
                            <div class="mb-2 w-100">
                                <div class="dropdown w-100">
                                    <button type="button" class="field-btn dropdown-toggle w-100 text-start" id="clientCompanyCreateMemberDropdownBtn" data-bs-toggle="dropdown" data-bs-auto-close="false" aria-expanded="false">
                                        <span id="clientCompanyCreateMemberDropdownLabel" class="text-muted small"><?php echo htmlspecialchars($lang['Select clients'] ?? 'Select clients'); ?></span>
                                    </button>
                                    <ul class="dropdown-menu w-100 dropdown-scroll p-0 no-shadow" id="clientCompanyCreateMemberDropdownMenu" aria-labelledby="clientCompanyCreateMemberDropdownBtn">
                                        <li class="modal-picker-dd-search px-2 py-2">
                                            <input type="text" id="clientCompanyCreateMemberSearchInput" class="form-control form-control-sm" placeholder="<?php echo htmlspecialchars($lang['Search clients...'] ?? 'Search clients...'); ?>" autocomplete="off">
                                        </li>
                                        <li id="clientCompanyCreateNoMembersFound" class="dropdown-item-text px-3 py-2 text-muted small fst-italic d-none"><?php echo htmlspecialchars($lang['No clients found'] ?? 'No clients found'); ?></li>
                                        <li class="p-0 border-0">
                                            <ul class="list-unstyled mb-0 w-100" id="clientCompanyCreateMemberOptionsList"></ul>
                                        </li>
                                    </ul>
                                </div>
                                <input type="hidden" id="clientCompanyCreateSelectedMemberIds" value="">
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="clientCompanyCreatePaneAddress" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="clientCompanyCreateAddress"><?php echo htmlspecialchars($lang['Address'] ?? 'Address'); ?></label>
                                    <input type="text" class="form-control" id="clientCompanyCreateAddress" maxlength="512">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group">
                                    <label for="clientCompanyCreateCity"><?php echo htmlspecialchars($lang['City'] ?? 'City'); ?></label>
                                    <input type="text" class="form-control" id="clientCompanyCreateCity" maxlength="128">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group">
                                    <label for="clientCompanyCreateState"><?php echo htmlspecialchars($lang['State'] ?? 'State'); ?></label>
                                    <input type="text" class="form-control" id="clientCompanyCreateState" maxlength="128">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group">
                                    <label for="clientCompanyCreateZip"><?php echo htmlspecialchars($lang['Zip Code'] ?? 'Zip Code'); ?></label>
                                    <input type="text" class="form-control" id="clientCompanyCreateZip" maxlength="32">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group">
                                    <label for="clientCompanyCreateCountry"><?php echo htmlspecialchars($lang['Country'] ?? 'Country'); ?></label>
                                    <select class="form-control" id="clientCompanyCreateCountry"><?php echo $ccCountryOptionsHtml; ?></select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="clientCompanyCreatePaneBilling" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="clientCompanyCreateBillingAddress"><?php echo htmlspecialchars($lang['Billing address line'] ?? 'Billing address'); ?></label>
                                    <input type="text" class="form-control" id="clientCompanyCreateBillingAddress" maxlength="512">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group">
                                    <label for="clientCompanyCreateBillingStreet"><?php echo htmlspecialchars($lang['Street'] ?? 'Street'); ?></label>
                                    <input type="text" class="form-control" id="clientCompanyCreateBillingStreet" maxlength="512">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group">
                                    <label for="clientCompanyCreateBillingCity"><?php echo htmlspecialchars($lang['City'] ?? 'City'); ?></label>
                                    <input type="text" class="form-control" id="clientCompanyCreateBillingCity" maxlength="128">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group">
                                    <label for="clientCompanyCreateBillingState"><?php echo htmlspecialchars($lang['State'] ?? 'State'); ?></label>
                                    <input type="text" class="form-control" id="clientCompanyCreateBillingState" maxlength="128">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group">
                                    <label for="clientCompanyCreateBillingZip"><?php echo htmlspecialchars($lang['Zip Code'] ?? 'Zip Code'); ?></label>
                                    <input type="text" class="form-control" id="clientCompanyCreateBillingZip" maxlength="32">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group">
                                    <label for="clientCompanyCreateBillingCountry"><?php echo htmlspecialchars($lang['Country'] ?? 'Country'); ?></label>
                                    <select class="form-control" id="clientCompanyCreateBillingCountry"><?php echo $ccCountryOptionsHtml; ?></select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="clientCompanyCreatePaneLinkCompanies" role="tabpanel">
                        <p class="text-muted small mb-2"><?php echo htmlspecialchars($lang['Link companies optional'] ?? 'Link other companies (optional)'); ?></p>
                        <p class="text-muted small mb-3"><?php echo htmlspecialchars($lang['Link companies hint'] ?? 'Linked companies appear on this company\'s Link companies tab.'); ?></p>
                        <div class="form-group">
                            <div class="mb-2 w-100">
                                <div class="dropdown w-100">
                                    <button type="button" class="field-btn dropdown-toggle w-100 text-start" id="clientCompanyCreateLinkedDropdownBtn" data-bs-toggle="dropdown" data-bs-auto-close="false" aria-expanded="false">
                                        <span id="clientCompanyCreateLinkedDropdownLabel" class="text-muted small"><?php echo htmlspecialchars($lang['Select companies'] ?? 'Select companies'); ?></span>
                                    </button>
                                    <ul class="dropdown-menu w-100 dropdown-scroll p-0 no-shadow" id="clientCompanyCreateLinkedDropdownMenu" aria-labelledby="clientCompanyCreateLinkedDropdownBtn">
                                        <li class="modal-picker-dd-search px-2 py-2">
                                            <input type="text" id="clientCompanyCreateLinkedSearchInput" class="form-control form-control-sm" placeholder="<?php echo htmlspecialchars($lang['Search companies...'] ?? 'Search companies...'); ?>" autocomplete="off">
                                        </li>
                                        <li id="clientCompanyCreateNoLinkedFound" class="dropdown-item-text px-3 py-2 text-muted small fst-italic d-none"><?php echo htmlspecialchars($lang['No companies found'] ?? 'No companies found'); ?></li>
                                        <li class="p-0 border-0">
                                            <ul class="list-unstyled mb-0 w-100" id="clientCompanyCreateLinkedOptionsList"></ul>
                                        </li>
                                    </ul>
                                </div>
                                <input type="hidden" id="clientCompanyCreateSelectedLinkedIds" value="">
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="clientCompanyCreatePaneCustom" role="tabpanel">
                        <p class="text-muted small mb-3"><?php echo htmlspecialchars($lang['Company custom fields hint'] ?? 'Values are saved with the company when you click Save.'); ?></p>
                        <div id="clientCompanyCreateCustomFieldsContainer" class="row g-3"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-end align-items-center flex-wrap">
                <button type="button" class="border-btn-a" data-bs-dismiss="modal"><?php echo htmlspecialchars($lang['Cancel'] ?? 'Cancel'); ?></button>
                <button type="button" class="primary-btn" id="clientCompanyCreateSave"><?php echo htmlspecialchars($lang['Save'] ?? 'Save'); ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade team-group-modal" id="clientCompanyEditModal" tabindex="-1" aria-labelledby="clientCompanyEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="clientCompanyEditModalLabel"><?php echo htmlspecialchars($lang['Edit company'] ?? 'Edit company'); ?></h5>
                <button type="button" class="btn-close me-2" data-bs-dismiss="modal" aria-label="Close"><?php echo ts_icon('close'); ?></button>
            </div>
            <div class="modal-body modal-height">
                <input type="hidden" id="clientCompanyEditId" value="">
                <div id="clientCompanyEditError" class="alert alert-danger" style="display:none;" role="alert"></div>
                <div class="modal-tabs-scroll mb-3">
                    <ul class="nav nav-tabs flex-nowrap" role="tablist">
                        <li class="nav-item flex-shrink-0" role="presentation">
                            <button class="nav-link active" id="clientCompanyEditTabDetailsBtn" data-bs-toggle="tab" data-bs-target="#clientCompanyEditPaneDetails" type="button" role="tab"><?php echo htmlspecialchars($lang['Company details'] ?? 'Details'); ?></button>
                        </li>
                        <li class="nav-item flex-shrink-0" role="presentation">
                            <button class="nav-link" id="clientCompanyEditTabClientsBtn" data-bs-toggle="tab" data-bs-target="#clientCompanyEditPaneClients" type="button" role="tab"><?php echo htmlspecialchars($lang['Add clients tab'] ?? 'Add clients'); ?></button>
                        </li>
                        <li class="nav-item flex-shrink-0" role="presentation">
                            <button class="nav-link" id="clientCompanyEditTabAddressBtn" data-bs-toggle="tab" data-bs-target="#clientCompanyEditPaneAddress" type="button" role="tab"><?php echo htmlspecialchars($lang['Company Address'] ?? 'Company address'); ?></button>
                        </li>
                        <li class="nav-item flex-shrink-0" role="presentation">
                            <button class="nav-link" id="clientCompanyEditTabBillingBtn" data-bs-toggle="tab" data-bs-target="#clientCompanyEditPaneBilling" type="button" role="tab"><?php echo htmlspecialchars($lang['Billing address'] ?? 'Billing address'); ?></button>
                        </li>
                        <li class="nav-item flex-shrink-0" role="presentation">
                            <button class="nav-link" id="clientCompanyEditTabLinkCompaniesBtn" data-bs-toggle="tab" data-bs-target="#clientCompanyEditPaneLinkCompanies" type="button" role="tab"><?php echo htmlspecialchars($lang['Link companies'] ?? 'Link companies'); ?></button>
                        </li>
                        <li class="nav-item flex-shrink-0" role="presentation">
                            <button class="nav-link" id="clientCompanyEditTabCustomBtn" data-bs-toggle="tab" data-bs-target="#clientCompanyEditPaneCustom" type="button" role="tab"><?php echo htmlspecialchars($lang['Custom fields'] ?? 'Custom fields'); ?></button>
                        </li>
                    </ul>
                </div>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="clientCompanyEditPaneDetails" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="mb-3 d-flex align-items-center col-gap client-company-logo-upload">
                                    <div class="client-company-logo-upload__picker" style="position: relative; display: inline-block; flex-shrink: 0;">
                                        <div class="img-uploadwrap">
                                            <img src="<?php echo htmlspecialchars($ccLogoPlaceholder, ENT_QUOTES, 'UTF-8'); ?>" class="img-fluid" id="clientCompanyEditLogoPreview" data-cc-logo-placeholder="<?php echo htmlspecialchars($ccLogoPlaceholder, ENT_QUOTES, 'UTF-8'); ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;" />
                                        </div>
                                        <div class="pro-pic">
                                            <span style="font-size: 24px;">+</span>
                                            <input type="file" name="clientCompanyEditLogo" class="pro-pic" id="clientCompanyEditLogo" accept="image/*" style="opacity: 0; position: absolute; width: 100%; height: 100%; cursor: pointer; top: 0; left: 0;" />
                                        </div>
                                    </div>
                                    <div class="mt-0 max-width-300 flex-grow-1">
                                        <h4 class="client-company-logo-upload__title"><?php echo htmlspecialchars($lang['Change company logo'] ?? 'Upload logo'); ?></h4>
                                        <p class="client-company-logo-upload__hint"><?php echo htmlspecialchars($lang['Company logo image requirements'] ?? 'Company logo must be a .jpg .png file smaller than 10MB and at least 400px by 400px.'); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group">
                                    <label for="clientCompanyEditName"><?php echo htmlspecialchars($lang['Company name'] ?? 'Company name'); ?>*</label>
                                    <input type="text" class="form-control" id="clientCompanyEditName" maxlength="191" autocomplete="organization">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group">
                                    <label for="clientCompanyEditVat"><?php echo htmlspecialchars($lang['VAT Number'] ?? 'VAT Number'); ?></label>
                                    <input type="text" class="form-control" id="clientCompanyEditVat" maxlength="64" autocomplete="off">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group">
                                    <label for="clientCompanyEditPhone"><?php echo htmlspecialchars($lang['Phone'] ?? 'Phone'); ?></label>
                                    <input type="text" class="form-control" id="clientCompanyEditPhone" maxlength="64" autocomplete="tel">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group">
                                    <label for="clientCompanyEditEmail"><?php echo htmlspecialchars($lang['Email'] ?? 'Email'); ?></label>
                                    <input type="email" class="form-control" id="clientCompanyEditEmail" maxlength="191" autocomplete="email" placeholder="name@example.com">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group">
                                    <label for="clientCompanyEditWebsite"><?php echo htmlspecialchars($lang['Website'] ?? 'Website'); ?></label>
                                    <input type="url" class="form-control" id="clientCompanyEditWebsite" maxlength="512" placeholder="https://">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group">
                                    <label for="clientCompanyEditCurrency"><?php echo htmlspecialchars($lang['Currency'] ?? 'Currency'); ?>*</label>
                                    <select class="form-control" id="clientCompanyEditCurrency" required><?php echo $ccCurrencyOptionsHtml; ?></select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="clientCompanyEditPaneClients" role="tabpanel" aria-labelledby="clientCompanyEditTabClientsBtn">
                        <p class="text-muted small mb-2"><?php echo htmlspecialchars($lang['Add clients optional'] ?? 'Add clients (optional)'); ?></p>
                        <p class="text-muted small mb-3"><?php echo htmlspecialchars($lang['Primary client invoices hint'] ?? 'Use Main on the right to set the invoice contact when several clients are linked.'); ?></p>
                        <div class="form-group">
                            <div class="mb-2 w-100">
                                <div class="dropdown w-100">
                                    <button type="button" class="field-btn dropdown-toggle w-100 text-start" id="clientCompanyEditMemberDropdownBtn" data-bs-toggle="dropdown" data-bs-auto-close="false" aria-expanded="false">
                                        <span id="clientCompanyEditMemberDropdownLabel" class="text-muted small"><?php echo htmlspecialchars($lang['Select clients'] ?? 'Select clients'); ?></span>
                                    </button>
                                    <ul class="dropdown-menu w-100 dropdown-scroll p-0 no-shadow" id="clientCompanyEditMemberDropdownMenu" aria-labelledby="clientCompanyEditMemberDropdownBtn">
                                        <li class="modal-picker-dd-search px-2 py-2">
                                            <input type="text" id="clientCompanyEditMemberSearchInput" class="form-control form-control-sm" placeholder="<?php echo htmlspecialchars($lang['Search clients...'] ?? 'Search clients...'); ?>" autocomplete="off">
                                        </li>
                                        <li id="clientCompanyEditNoMembersFound" class="dropdown-item-text px-3 py-2 text-muted small fst-italic d-none"><?php echo htmlspecialchars($lang['No clients found'] ?? 'No clients found'); ?></li>
                                        <li class="p-0 border-0">
                                            <ul class="list-unstyled mb-0 w-100" id="clientCompanyEditMemberOptionsList"></ul>
                                        </li>
                                    </ul>
                                </div>
                                <input type="hidden" id="clientCompanyEditSelectedMemberIds" value="">
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="clientCompanyEditPaneAddress" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="clientCompanyEditAddress"><?php echo htmlspecialchars($lang['Address'] ?? 'Address'); ?></label>
                                    <input type="text" class="form-control" id="clientCompanyEditAddress" maxlength="512">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group">
                                    <label for="clientCompanyEditCity"><?php echo htmlspecialchars($lang['City'] ?? 'City'); ?></label>
                                    <input type="text" class="form-control" id="clientCompanyEditCity" maxlength="128">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group">
                                    <label for="clientCompanyEditState"><?php echo htmlspecialchars($lang['State'] ?? 'State'); ?></label>
                                    <input type="text" class="form-control" id="clientCompanyEditState" maxlength="128">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group">
                                    <label for="clientCompanyEditZip"><?php echo htmlspecialchars($lang['Zip Code'] ?? 'Zip Code'); ?></label>
                                    <input type="text" class="form-control" id="clientCompanyEditZip" maxlength="32">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group">
                                    <label for="clientCompanyEditCountry"><?php echo htmlspecialchars($lang['Country'] ?? 'Country'); ?></label>
                                    <select class="form-control" id="clientCompanyEditCountry"><?php echo $ccCountryOptionsHtml; ?></select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="clientCompanyEditPaneBilling" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="clientCompanyEditBillingAddress"><?php echo htmlspecialchars($lang['Billing address line'] ?? 'Billing address'); ?></label>
                                    <input type="text" class="form-control" id="clientCompanyEditBillingAddress" maxlength="512">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group">
                                    <label for="clientCompanyEditBillingStreet"><?php echo htmlspecialchars($lang['Street'] ?? 'Street'); ?></label>
                                    <input type="text" class="form-control" id="clientCompanyEditBillingStreet" maxlength="512">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group">
                                    <label for="clientCompanyEditBillingCity"><?php echo htmlspecialchars($lang['City'] ?? 'City'); ?></label>
                                    <input type="text" class="form-control" id="clientCompanyEditBillingCity" maxlength="128">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group">
                                    <label for="clientCompanyEditBillingState"><?php echo htmlspecialchars($lang['State'] ?? 'State'); ?></label>
                                    <input type="text" class="form-control" id="clientCompanyEditBillingState" maxlength="128">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group">
                                    <label for="clientCompanyEditBillingZip"><?php echo htmlspecialchars($lang['Zip Code'] ?? 'Zip Code'); ?></label>
                                    <input type="text" class="form-control" id="clientCompanyEditBillingZip" maxlength="32">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-group">
                                    <label for="clientCompanyEditBillingCountry"><?php echo htmlspecialchars($lang['Country'] ?? 'Country'); ?></label>
                                    <select class="form-control" id="clientCompanyEditBillingCountry"><?php echo $ccCountryOptionsHtml; ?></select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="clientCompanyEditPaneLinkCompanies" role="tabpanel">
                        <p class="text-muted small mb-2"><?php echo htmlspecialchars($lang['Link companies optional'] ?? 'Link other companies (optional)'); ?></p>
                        <p class="text-muted small mb-3"><?php echo htmlspecialchars($lang['Link companies hint'] ?? 'Linked companies appear on this company\'s Link companies tab.'); ?></p>
                        <div class="form-group">
                            <div class="mb-2 w-100">
                                <div class="dropdown w-100">
                                    <button type="button" class="field-btn dropdown-toggle w-100 text-start" id="clientCompanyEditLinkedDropdownBtn" data-bs-toggle="dropdown" data-bs-auto-close="false" aria-expanded="false">
                                        <span id="clientCompanyEditLinkedDropdownLabel" class="text-muted small"><?php echo htmlspecialchars($lang['Select companies'] ?? 'Select companies'); ?></span>
                                    </button>
                                    <ul class="dropdown-menu w-100 dropdown-scroll p-0 no-shadow" id="clientCompanyEditLinkedDropdownMenu" aria-labelledby="clientCompanyEditLinkedDropdownBtn">
                                        <li class="modal-picker-dd-search px-2 py-2">
                                            <input type="text" id="clientCompanyEditLinkedSearchInput" class="form-control form-control-sm" placeholder="<?php echo htmlspecialchars($lang['Search companies...'] ?? 'Search companies...'); ?>" autocomplete="off">
                                        </li>
                                        <li id="clientCompanyEditNoLinkedFound" class="dropdown-item-text px-3 py-2 text-muted small fst-italic d-none"><?php echo htmlspecialchars($lang['No companies found'] ?? 'No companies found'); ?></li>
                                        <li class="p-0 border-0">
                                            <ul class="list-unstyled mb-0 w-100" id="clientCompanyEditLinkedOptionsList"></ul>
                                        </li>
                                    </ul>
                                </div>
                                <input type="hidden" id="clientCompanyEditSelectedLinkedIds" value="">
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="clientCompanyEditPaneCustom" role="tabpanel">
                        <p class="text-muted small mb-3"><?php echo htmlspecialchars($lang['Company custom fields hint'] ?? 'Values are saved with the company when you click Save.'); ?></p>
                        <div id="clientCompanyEditCustomFieldsContainer" class="row g-3"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-end align-items-center flex-wrap">
                <button type="button" class="btn border-btn-a" data-bs-dismiss="modal"><?php echo htmlspecialchars($lang['Cancel'] ?? 'Cancel'); ?></button>
                <button type="button" class="primary-btn" id="clientCompanyEditSave"><?php echo htmlspecialchars($lang['Save'] ?? 'Save'); ?></button>
            </div>
        </div>
    </div>
</div>

<script>
window.clientCompanyPickableClients = <?php echo json_encode($companyPickableClients, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
window.clientCompanyPickableCompanies = <?php echo json_encode(isset($companyPickableCompanies) && is_array($companyPickableCompanies) ? $companyPickableCompanies : [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
window.clientCompaniesApiUrl = <?php echo json_encode(rtrim($url, '/') . '/includes/api/client-companies-api.php'); ?>;
window.__clientCompaniesCsrfToken = <?php echo json_encode(generate_csrf_token()); ?>;
window.__clientCompaniesLang = {
    selectClients: <?php echo json_encode($lang['Select clients'] ?? 'Select clients'); ?>,
    clientsSelectedN: <?php echo json_encode($lang['{n} clients selected'] ?? '{n} clients selected'); ?>,
    nameRequired: <?php echo json_encode($lang['Company name is required'] ?? 'Company name is required'); ?>,
    currencyRequired: <?php echo json_encode($lang['Select Currency'] ?? 'Select a currency'); ?>,
    confirmDelete: <?php echo json_encode($lang['Delete this company?'] ?? 'Move this company to trash?'); ?>,
    confirmDeleteNamed: <?php echo json_encode($lang['Delete company named'] ?? 'Delete company "{name}"?'); ?>,
    primaryMainLabel: <?php echo json_encode($lang['Primary main client'] ?? 'Main'); ?>,
    selectCompanies: <?php echo json_encode($lang['Select companies'] ?? 'Select companies'); ?>,
    companiesSelectedN: <?php echo json_encode($lang['{n} companies selected'] ?? '{n} companies selected'); ?>,
    cfRequiredSummary: <?php echo json_encode($lang['Please complete required custom fields.'] ?? 'Please complete required custom fields.'); ?>,
    pinMaxError: <?php echo json_encode($lang['Maximum 4 companies can be pinned.'] ?? 'Maximum 4 companies can be pinned.'); ?>
};
window.__clientCompaniesBaseUrl = <?php echo json_encode(rtrim($url, '/')); ?>;
window.__clientCompanyCurrencyLabels = <?php echo json_encode($ccCurrencySymbols, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
window.__clientCompaniesCustomFieldsListUrl = <?php echo json_encode(rtrim($url, '/') . '/includes/custom-fields/list.php'); ?>;
window.__companyModalCfI18n = {
    select: <?php echo json_encode($lang['Select'] ?? 'Select'); ?>,
    fieldRequired: <?php echo json_encode($lang['This field is required.'] ?? 'This field is required.'); ?>
};
</script>
