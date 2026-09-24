<?php
if (!isset($latestMile) || !is_object($latestMile)) {
    return;
}

$processUrl = $processUrl ?? (rtrim((string) ($url ?? ''), '/') . '/client/process');
$returnUrl = $returnUrl ?? '';
$projectId = (int) ($projectId ?? 0);
$edit_id = (int) ($edit_id ?? $latestMile->id ?? 0);
$id = (int) ($id ?? 0);
$total_amount = calculateInvoiceTotal(client_payment_row_from_milestone($latestMile));

$activeTab = '';
if (!empty($stripe_sk) && !empty($stripe_pk)) {
    $activeTab = 'stripe';
} elseif (!empty($checkout_id) && !empty($checkout_pk)) {
    $activeTab = '2co';
} elseif (!empty($paypal_email)) {
    $activeTab = 'paypal';
}
$gatewayCount = 0;
if (!empty($checkout_id) && !empty($checkout_pk)) {
    $gatewayCount++;
}
if (!empty($stripe_sk) && !empty($stripe_pk)) {
    $gatewayCount++;
}
if (!empty($paypal_email)) {
    $gatewayCount++;
}
$showGatewayTabs = $gatewayCount > 1;
$invoiceDisplayNo = !empty($latestMile->p_id)
    ? ($latestMile->p_id . $latestMile->id)
    : (string) $latestMile->id;
$amountFormatted = getCurrencySymbol($latestMile->currency) . number_format($total_amount, 2);
$completePaymentLabel = $lang['Complete Payment'] ?? 'Complete payment';
?>
<div class="client-payment-milestone">
    <div class="client-payment-milestone__table-wrap">
        <table class="client-payment-milestone__table">
            <thead>
                <tr>
                    <th><?php echo htmlspecialchars($lang['Invoice'] ?? 'Invoice', ENT_QUOTES, 'UTF-8'); ?></th>
                    <th><?php echo htmlspecialchars($lang['Amount'] ?? 'Amount', ENT_QUOTES, 'UTF-8'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td data-label="<?php echo htmlspecialchars($lang['Invoice'] ?? 'Invoice', ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="client-payment-milestone__invoice-no">#<?php echo htmlspecialchars($invoiceDisplayNo, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="client-payment-milestone__invoice-title"><?php echo htmlspecialchars($latestMile->title, ENT_QUOTES, 'UTF-8'); ?></span>
                    </td>
                    <td data-label="<?php echo htmlspecialchars($lang['Amount'] ?? 'Amount', ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="client-payment-milestone__amount"><?php echo htmlspecialchars($amountFormatted, ENT_QUOTES, 'UTF-8'); ?></span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<div class="client-payment-gateway<?php echo $showGatewayTabs ? '' : ' client-payment-gateway--single'; ?>">
<?php if ($showGatewayTabs): ?>
<ul class="nav nav-tabs client-payment-gateway__tabs" id="paymentgway" role="tablist">
    <?php if (!empty($checkout_id) && !empty($checkout_pk)) { ?>
    <li class="nav-item">
        <a class="nav-link <?php echo ($activeTab === '2co' ? 'active' : ''); ?>" id="2co-tab" data-bs-toggle="tab" href="#2co" role="tab" aria-controls="2co" aria-selected="<?php echo ($activeTab === '2co' ? 'true' : 'false'); ?>">
            <img src="<?php echo $url; ?>assets/images/2checkout.svg" alt="2Checkout"/>
        </a>
    </li>
    <?php } if (!empty($stripe_sk) && !empty($stripe_pk)) { ?>
    <li class="nav-item">
        <a class="nav-link <?php echo ($activeTab === 'stripe' ? 'active' : ''); ?>" id="stripe-tab" data-bs-toggle="tab" href="#stripe" role="tab" aria-controls="stripe" aria-selected="<?php echo ($activeTab === 'stripe' ? 'true' : 'false'); ?>">
            <img src="<?php echo $url; ?>assets/images/stripe.svg" alt="Stripe"/>
        </a>
    </li>
    <?php } if (!empty($paypal_email)) { ?>
    <li class="nav-item">
        <a class="nav-link <?php echo ($activeTab === 'paypal' ? 'active' : ''); ?>" id="paypal-tab" data-bs-toggle="tab" href="#paypal" role="tab" aria-controls="paypal" aria-selected="<?php echo ($activeTab === 'paypal' ? 'true' : 'false'); ?>">
            <img src="<?php echo $url; ?>assets/images/paypal.svg" alt="PayPal"/>
        </a>
    </li>
    <?php } ?>
</ul>
<?php else: ?>
<div class="client-payment-gateway__provider">
    <?php if ($activeTab === 'stripe'): ?>
        <img src="<?php echo $url; ?>assets/images/stripe.svg" alt="Stripe"/>
    <?php elseif ($activeTab === '2co'): ?>
        <img src="<?php echo $url; ?>assets/images/2checkout.svg" alt="2Checkout"/>
    <?php elseif ($activeTab === 'paypal'): ?>
        <img src="<?php echo $url; ?>assets/images/paypal.svg" alt="PayPal"/>
    <?php endif; ?>
</div>
<?php endif; ?>
<div class="tab-content" id="paymentgwaycontent">
    <?php if (!empty($checkout_id) && !empty($checkout_pk)) { ?>
    <div class="tab-pane <?php echo ($activeTab === '2co' ? 'show active' : 'fade'); ?>" id="2co" role="tabpanel" aria-labelledby="2co-tab">
        <div class="paymentmeth">
            <form id="myCCForm" method="POST" action="<?php echo htmlspecialchars($processUrl, ENT_QUOTES, 'UTF-8'); ?>">
                <?php if ($returnUrl !== ''): ?>
                <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8'); ?>">
                <?php endif; ?>
                <input type="hidden" name="sellerId" value="<?php echo htmlspecialchars($checkout_id, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="publishableKey" value="<?php echo htmlspecialchars($checkout_pk, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="token" value="">
                <input type="hidden" name="custName" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="custEmail" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="user_Id" value="<?php echo $id; ?>">
                <input type="hidden" name="proj_Id" value="<?php echo $projectId; ?>">
                <input type="hidden" name="milestone_id" value="<?php echo $edit_id; ?>">
                <input type="hidden" name="amount" value="<?php echo $total_amount; ?>">
                <input type="hidden" name="currency" value="<?php echo strtolower(explode(',', $latestMile->currency)[0] ?? 'USD'); ?>">
                <input type="hidden" name="invoice_number" value="<?php echo $latestMile->p_id . $latestMile->id; ?>">
                <div class="form-group">
                    <div class="col-sm-12 card-settings">
                        <label for="ccNo"><?php echo $lang['Card Number']; ?></label>
                        <input type="text" id="ccNo" name="ccNo" class="form-control" placeholder="xxxx xxxx xxxx xxxx" autocomplete="off">
                    </div>
                </div>
                <div class="form-group">
                    <div class="container">
                        <div class="row">
                            <div class="col-sm-7">
                                <h4>Expiry date</h4>
                                <input type="text" id="expMonth" name="expMonth" class="form-control" placeholder="MM">
                                <input type="text" id="expYear" name="expYear" class="form-control" placeholder="YY">
                            </div>
                            <div class="col-sm-5">
                                <h4>Security Code</h4>
                                <input type="text" id="cvv" name="cvv" class="form-control" placeholder="CVV" autocomplete="off">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-md-12">
                        <button type="submit" class="bigbutton"><?php echo htmlspecialchars($completePaymentLabel, ENT_QUOTES, 'UTF-8'); ?></button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php } if (!empty($stripe_sk) && !empty($stripe_pk)) { ?>
    <div class="tab-pane <?php echo ($activeTab === 'stripe' ? 'show active' : 'fade'); ?>" id="stripe" role="tabpanel" aria-labelledby="stripe-tab">
        <div class="paymentmeth">
            <div class="panel panel-default">
                <div class="panel-body">
                    <span class="paymentErrors alert-danger" id="payment-errors"></span>
                    <form id="stripe-payment-form" method="POST" action="<?php echo htmlspecialchars($processUrl, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php if ($returnUrl !== ''): ?>
                        <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php endif; ?>
                        <input type="hidden" name="custName" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="custEmail" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="user_Id" value="<?php echo $id; ?>">
                        <input type="hidden" name="proj_Id" value="<?php echo $projectId; ?>">
                        <input type="hidden" name="milestone_id" value="<?php echo $edit_id; ?>">
                        <input type="hidden" name="amount" value="<?php echo $total_amount; ?>">
                        <input type="hidden" name="currency" value="<?php echo strtolower(explode(',', $latestMile->currency)[0] ?? 'USD'); ?>">
                        <input type="hidden" name="invoice_number" value="<?php echo $latestMile->p_id . $latestMile->id; ?>">
                        <?php
                        require_once __DIR__ . '/../../includes/stripe_saved_payment.php';
                        $clientSavedCards = stripe_list_payment_methods_for_user($id);
                        if (!empty($clientSavedCards)):
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
                            <label for="savedPaymentDropdownBtn"><h4><?php echo htmlspecialchars($lang['Saved card'] ?? 'Saved card', ENT_QUOTES, 'UTF-8'); ?></h4></label>
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
                                    <option value="<?php echo htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="new" <?php echo ($selectedSavedValue === 'new') ? 'selected' : ''; ?>><?php echo htmlspecialchars($lang['Use a new card'] ?? 'Use a new card', ENT_QUOTES, 'UTF-8'); ?></option>
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
                                    <span id="savedPaymentDropdownBtnText"><?php echo htmlspecialchars($selectedSavedLabel, ENT_QUOTES, 'UTF-8'); ?></span>
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
                                               data-value="<?php echo htmlspecialchars($itemValue, ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo htmlspecialchars($itemLabel, ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                    <li>
                                        <a href="#"
                                           class="dropdown-item<?php echo ($selectedSavedValue === 'new') ? ' active' : ''; ?>"
                                           data-value="new">
                                            <?php echo htmlspecialchars($lang['Use a new card'] ?? 'Use a new card', ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div id="new-card-fields">
                            <div id="payment-request-button" class="payment-request-button-wrapper"></div>
                            <label for="card-element"><h4><?php echo htmlspecialchars($lang['Card details'] ?? 'Card details', ENT_QUOTES, 'UTF-8'); ?></h4></label>
                            <div id="card-element" style="padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
                            <div class="d-flex col-gap align-items-center mt-3">
                                <div class="checkbox-wrapper-6">
                                    <input class="tgl tgl-light" id="save-card-checkbox" name="save_card_ui" type="checkbox" value="1">
                                    <label class="tgl-btn" for="save-card-checkbox"></label>
                                </div>
                                <div>
                                    <label for="save-card-checkbox" class="permission-label mb-0"><?php echo htmlspecialchars($lang['Save this card for future payments'] ?? 'Save this card for future payments'); ?></label>
                                </div>
                            </div>
                        </div>
                        <div id="card-errors" class="text-danger" style="margin-top: 10px;"></div>
                        <div class="form-group mt-4">
                            <button type="submit" id="submit-payment" class="bigbutton"><?php echo htmlspecialchars($completePaymentLabel, ENT_QUOTES, 'UTF-8'); ?></button>
                        </div>
                    </form>
                    <div id="payment-message" class="hidden"></div>
                </div>
            </div>
        </div>
    </div>
    <?php } if (!empty($paypal_email)) { ?>
    <div class="tab-pane <?php echo ($activeTab === 'paypal' ? 'show active' : 'fade'); ?>" id="paypal" role="tabpanel" aria-labelledby="paypal-tab">
        <div class="paymentmeth">
            <form action="https://www.paypal.com/cgi-bin/webscr" method="post" style="text-align: center;">
                <input type="hidden" name="cmd" value="_xclick">
                <input type="hidden" name="business" value="<?php echo htmlspecialchars($paypal_email, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="item_name" value="<?php echo htmlspecialchars($latestMile->title, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="item_number" value="1">
                <input type="hidden" name="amount" value="<?php echo $total_amount; ?>">
                <input type="hidden" name="no_shipping" value="0">
                <input type="hidden" name="no_note" value="1">
                <input type="hidden" name="currency_code" value="<?php echo explode(',', $latestMile->currency)[0] ?? 'USD'; ?>">
                <input type="hidden" name="lc" value="AU">
                <input type="hidden" name="bn" value="PP-BuyNowBF">
                <input type="submit" class="bigbutton" name="submit" value="<?php echo htmlspecialchars($completePaymentLabel, ENT_QUOTES, 'UTF-8'); ?>">
                <img alt="" border="0" src="https://www.paypal.com/en_AU/i/scr/pixel.gif" width="1" height="1" style="width: 5px; height: 5px;">
                <input type="hidden" name="return" value="<?php echo $url; ?>client/paypal_payment?milestone_id=<?php echo $edit_id; ?>&projectId=<?php echo $projectId; ?>&status=success&clientId=<?php echo $id; ?>">
            </form>
        </div>
    </div>
    <?php }
    if (empty($paypal_email) && empty($stripe_sk) && empty($stripe_pk) && empty($checkout_id) && empty($checkout_pk)) {
        echo '<div style="text-align: center; padding-bottom: 40px;">Please Contact Admin for payment!</div>';
    } ?>
</div>
</div>
<div class="client-payment-footer">
    <div class="client-payment-footer__row client-payment-footer__row--top">
        <div class="client-payment-footer__section client-payment-footer__secure">
            <strong><?php echo htmlspecialchars($lang['Your payment is secure.'] ?? 'Your payment is secure.', ENT_QUOTES, 'UTF-8'); ?></strong>
            <p><?php echo htmlspecialchars($lang['All data is encrypted, and your card details are never stored on our servers.'] ?? 'All data is encrypted, and your card details are never stored on our servers.', ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <div class="client-payment-footer__section client-payment-footer__badge">
            <strong><?php echo htmlspecialchars($lang['System secure by'] ?? 'System secure by', ENT_QUOTES, 'UTF-8'); ?></strong>
            <img src="<?php echo $url; ?>assets/images/comodo-ssl.png" alt="Comodo SSL"/>
        </div>
    </div>
    <div class="client-payment-footer__row client-payment-footer__row--bottom">
        <div class="client-payment-footer__section client-payment-footer__accept">
            <span><?php echo htmlspecialchars($lang['We accecpt'] ?? 'We accept', ENT_QUOTES, 'UTF-8'); ?></span>
            <img src="<?php echo $url; ?>assets/images/gatways.png" alt="Payment gateways"/>
        </div>
    </div>
</div>
