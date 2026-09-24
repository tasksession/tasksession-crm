<?php
if (empty($gatewayContext) && !isset($processUrl)) {
    return;
}

$ctx = $gatewayContext ?? [];
$lang = $ctx['lang'] ?? ($lang ?? []);
$processUrl = $ctx['processUrl'] ?? ($processUrl ?? '');
$returnUrl = $ctx['returnUrl'] ?? ($returnUrl ?? '');
$id = (int) ($ctx['clientId'] ?? $ctx['id'] ?? 0);
$username = $ctx['username'] ?? '';
$email = $ctx['email'] ?? '';
$projectId = (int) ($ctx['projectId'] ?? 0);
$edit_id = (int) ($ctx['edit_id'] ?? 0);
$isBulk = !empty($ctx['isBulk']);
$gatewayId = $ctx['gatewayId'] ?? 'stripe';
$invoiceIds = $ctx['invoiceIds'] ?? [];
$completePaymentLabel = $lang['Complete Payment'] ?? 'Complete payment';

$h = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

require_once __DIR__ . '/../../../includes/stripe_saved_payment.php';
require_once __DIR__ . '/../../../includes/client_payment_modal_helper.php';
$clientSavedCards = stripe_list_payment_methods_for_user($id);
?>
<div class="paymentmeth">
    <div class="panel panel-default">
        <div class="panel-body">
            <span class="paymentErrors alert-danger" id="payment-errors"></span>
            <form id="stripe-payment-form" method="POST" action="<?php echo $h($processUrl); ?>">
                <?php if ($returnUrl !== ''): ?>
                <input type="hidden" name="return_to" value="<?php echo $h($returnUrl); ?>">
                <?php endif; ?>
                <input type="hidden" name="gateway" value="<?php echo $h($gatewayId); ?>">
                <?php if ($isBulk): ?>
                <input type="hidden" name="bulk_pay" value="1">
                <?php foreach ($invoiceIds as $invoiceId): ?>
                <input type="hidden" name="invoice_ids[]" value="<?php echo (int) $invoiceId; ?>">
                <?php endforeach; ?>
                <?php else: ?>
                <input type="hidden" name="proj_Id" value="<?php echo $projectId; ?>">
                <input type="hidden" name="milestone_id" value="<?php echo $edit_id; ?>">
                <?php if (!empty($ctx['latestMile'])): ?>
                <?php
                $latestMile = $ctx['latestMile'];
                $total_amount = calculateInvoiceTotal(client_payment_row_from_milestone($latestMile));
                ?>
                <input type="hidden" name="amount" value="<?php echo $total_amount; ?>">
                <input type="hidden" name="currency" value="<?php echo $h(strtolower(explode(',', $latestMile->currency)[0] ?? 'USD')); ?>">
                <input type="hidden" name="invoice_number" value="<?php echo $h($latestMile->p_id . $latestMile->id); ?>">
                <?php endif; ?>
                <?php endif; ?>
                <input type="hidden" name="custName" value="<?php echo $h($username); ?>">
                <input type="hidden" name="custEmail" value="<?php echo $h($email); ?>">
                <input type="hidden" name="user_Id" value="<?php echo $id; ?>">
                <?php if (!empty($clientSavedCards)): ?>
                <?php
                $selectedSavedValue = 'new';
                $selectedSavedLabel = $lang['Use a new card'] ?? 'Use a new card';
                foreach ($clientSavedCards as $pmRow) {
                    if (!empty($pmRow['is_default'])) {
                        $selectedSavedValue = $pmRow['stripe_payment_method_id'];
                        $selectedSavedLabel = ucfirst($pmRow['brand'] ?: 'Card') . ' •••• ' . $pmRow['last4'] . ' (' . ($lang['Default'] ?? 'Default') . ')';
                        break;
                    }
                }
                if ($selectedSavedValue === 'new' && !empty($clientSavedCards[0])) {
                    $first = $clientSavedCards[0];
                    $selectedSavedValue = $first['stripe_payment_method_id'];
                    $selectedSavedLabel = ucfirst($first['brand'] ?: 'Card') . ' •••• ' . $first['last4'];
                    if (!empty($first['is_default'])) {
                        $selectedSavedLabel .= ' (' . ($lang['Default'] ?? 'Default') . ')';
                    }
                }
                ?>
                <div class="form-group mb-3 client-payment-saved-card">
                    <label for="savedPaymentDropdownBtn"><h4><?php echo $h($lang['Saved card'] ?? 'Saved card'); ?></h4></label>
                    <select id="saved-payment-method-select" name="saved_payment_method" class="d-none" aria-hidden="true" tabindex="-1">
                        <?php foreach ($clientSavedCards as $pmRow): ?>
                            <?php
                            $optionLabel = ucfirst($pmRow['brand'] ?: 'Card') . ' •••• ' . $pmRow['last4'];
                            if (!empty($pmRow['is_default'])) {
                                $optionLabel .= ' (' . ($lang['Default'] ?? 'Default') . ')';
                            }
                            $optionValue = $pmRow['stripe_payment_method_id'];
                            $isSelected = ($optionValue === $selectedSavedValue);
                            ?>
                            <option value="<?php echo $h($optionValue); ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
                                <?php echo $h($optionLabel); ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="new" <?php echo ($selectedSavedValue === 'new') ? 'selected' : ''; ?>><?php echo $h($lang['Use a new card'] ?? 'Use a new card'); ?></option>
                    </select>
                    <div class="dropdown">
                        <button
                            type="button"
                            class="field-btn dropdown-toggle w-100 text-start"
                            id="savedPaymentDropdownBtn"
                            data-bs-toggle="dropdown"
                            data-bs-display="static"
                            data-bs-boundary="viewport"
                            data-bs-auto-close="outside"
                            aria-expanded="false"
                        >
                            <span id="savedPaymentDropdownBtnText"><?php echo $h($selectedSavedLabel); ?></span>
                        </button>
                        <ul class="dropdown-menu w-100 dropdown-scroll" id="savedPaymentDropdownMenu" aria-labelledby="savedPaymentDropdownBtn">
                            <?php foreach ($clientSavedCards as $pmRow): ?>
                                <?php
                                $itemLabel = ucfirst($pmRow['brand'] ?: 'Card') . ' •••• ' . $pmRow['last4'];
                                if (!empty($pmRow['is_default'])) {
                                    $itemLabel .= ' (' . ($lang['Default'] ?? 'Default') . ')';
                                }
                                $itemValue = $pmRow['stripe_payment_method_id'];
                                $itemActive = ($itemValue === $selectedSavedValue);
                                ?>
                                <li>
                                    <a href="#"
                                       class="dropdown-item<?php echo $itemActive ? ' active' : ''; ?>"
                                       data-value="<?php echo $h($itemValue); ?>">
                                        <?php echo $h($itemLabel); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                            <li>
                                <a href="#"
                                   class="dropdown-item<?php echo ($selectedSavedValue === 'new') ? ' active' : ''; ?>"
                                   data-value="new">
                                    <?php echo $h($lang['Use a new card'] ?? 'Use a new card'); ?>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
                <div id="new-card-fields">
                    <?php if (!$isBulk): ?>
                    <div id="payment-request-button" class="payment-request-button-wrapper"></div>
                    <?php endif; ?>
                    <label for="card-element"><h4><?php echo $h($lang['Card details'] ?? 'Card details'); ?></h4></label>
                    <div id="card-element" style="padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
                    <div class="d-flex col-gap align-items-center mt-3">
                        <div class="checkbox-wrapper-6">
                            <input class="tgl tgl-light" id="save-card-checkbox" name="save_card_ui" type="checkbox" value="1">
                            <label class="tgl-btn" for="save-card-checkbox"></label>
                        </div>
                        <div>
                            <label for="save-card-checkbox" class="permission-label mb-0"><?php echo $h($lang['Save this card for future payments'] ?? 'Save this card for future payments'); ?></label>
                        </div>
                    </div>
                </div>
                <div id="card-errors" class="text-danger" style="margin-top: 10px;"></div>
                <div class="form-group mt-4">
                    <button type="submit" id="submit-payment" class="bigbutton"><?php echo $h($completePaymentLabel); ?></button>
                </div>
            </form>
            <div id="payment-message" class="hidden"></div>
        </div>
    </div>
</div>
