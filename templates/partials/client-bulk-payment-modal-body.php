<?php
if (empty($review) || empty($invoiceIds)) {
    return;
}

$lang = $lang ?? [];
$review = is_array($review) ? $review : [];
$allGroups = $review['all_groups'] ?? [];
$currencyTotals = $review['currency_totals'] ?? [];
$totalCount = (int) ($review['total_count'] ?? count($invoiceIds));
$gateways = $gateways ?? [];
$gatewaySections = $gatewaySections ?? [];
$primaryGateway = $primaryGateway ?? 'stripe';
$showGatewayTabs = count($gateways) > 1;

$h = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$lbl = static function (string $key, string $fallback) use ($lang, $h): string {
    return $h(isset($lang[$key]) ? (string) $lang[$key] : $fallback);
};

$summaryCountLabel = sprintf(
    $lang['Unpaid invoices count label'] ?? '%d Unpaid invoices',
    $totalCount
);
?>
<div class="client-bulk-payment">
    <div class="client-bulk-payment__intro">
        <p class="client-bulk-payment__intro-text"><?php echo $lbl('Review invoices before payment', 'Review your invoices before completing payment.'); ?></p>
    </div>

    <div class="client-bulk-payment__summary text-center">
        <div class="client-bulk-payment__summary-count"><?php echo $h($summaryCountLabel); ?></div>
        <?php if (!empty($currencyTotals)): ?>
            <div class="client-bulk-payment__summary-currencies">
                <?php foreach ($currencyTotals as $index => $currencyTotal): ?>
                    <?php if ($index > 0): ?>
                        <span class="client-bulk-payment__currency-sep" aria-hidden="true">&bull;</span>
                    <?php endif; ?>
                    <span class="client-bulk-payment__currency-chip"><?php echo $h($currencyTotal['summary_formatted'] ?? ''); ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="client-bulk-payment__groups">
        <?php
        $displayInvoices = [];
        foreach ($allGroups as $group) {
            foreach ($group['invoices'] as $invoice) {
                $displayInvoices[] = $invoice;
            }
        }
        ?>
        <?php if (!empty($displayInvoices)): ?>
            <div class="client-bulk-payment__table-wrap">
                <table class="client-bulk-payment__table">
                    <thead>
                        <tr>
                            <th><?php echo $lbl('Invoice', 'Invoice'); ?></th>
                            <th><?php echo $lbl('Due Date', 'Due date'); ?></th>
                            <th><?php echo $lbl('Amount', 'Amount'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($displayInvoices as $invoice): ?>
                            <tr class="client-bulk-payment__row" data-invoice-id="<?php echo (int) ($invoice['id'] ?? 0); ?>">
                                <td data-label="<?php echo $lbl('Invoice', 'Invoice'); ?>">
                                    <span class="client-bulk-payment__invoice-no"><?php echo $h($invoice['display_number'] ?? ''); ?></span>
                                </td>
                                <td data-label="<?php echo $lbl('Due Date', 'Due date'); ?>">
                                    <span class="client-bulk-payment__status">
                                    <?php if (!empty($invoice['is_overdue'])): ?>
                                        <?php
                                        $overdueDays = (int) ($invoice['overdue_days'] ?? 0);
                                        if ($overdueDays === 1) {
                                            $overdueBadgeText = sprintf($lang['Outstanding invoice overdue badge singular'] ?? 'Overdue | %d day', $overdueDays);
                                        } else {
                                            $overdueBadgeText = sprintf($lang['Outstanding invoice overdue badge plural'] ?? 'Overdue | %d days', max(1, $overdueDays));
                                        }
                                        $overdueParts = explode('|', $overdueBadgeText, 2);
                                        $overdueLabel = trim($overdueParts[0] ?? 'Overdue');
                                        $overdueDetail = trim($overdueParts[1] ?? $overdueBadgeText);
                                        ?>
                                        <span class="client-bulk-payment__overdue-badge">
                                            <span class="client-bulk-payment__overdue-badge-label"><?php echo $h($overdueLabel); ?></span>
                                            <span class="client-bulk-payment__overdue-badge-sep" aria-hidden="true">|</span>
                                            <span class="client-bulk-payment__overdue-badge-days"><?php echo $h($overdueDetail); ?></span>
                                        </span>
                                    <?php else: ?>
                                        <span class="client-bulk-payment__due-date"><?php echo $h($invoice['deadline_formatted'] ?? '—'); ?></span>
                                    <?php endif; ?>
                                    </span>
                                </td>
                                <td data-label="<?php echo $lbl('Amount', 'Amount'); ?>">
                                    <span class="client-bulk-payment__amount-wrap">
                                        <span class="client-bulk-payment__amount"><?php echo $h($invoice['amount_formatted'] ?? ''); ?></span>
                                        <span class="client-bulk-payment__amount-spinner" hidden aria-hidden="true">
                                            <svg class="spinner-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                                <circle class="spinner-circle-animated" cx="12" cy="12" r="10" stroke-dasharray="24" stroke-dashoffset="24"></circle>
                                            </svg>
                                        </span>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="client-payment-gateway<?php echo $showGatewayTabs ? '' : ' client-payment-gateway--single'; ?>">
<?php if ($showGatewayTabs): ?>
<ul class="nav nav-tabs client-payment-gateway__tabs" role="tablist">
    <?php foreach ($gateways as $index => $gateway): ?>
    <li class="nav-item">
        <a class="nav-link <?php echo ($gateway['id'] === $primaryGateway || ($index === 0 && $primaryGateway === null)) ? 'active' : ''; ?>"
           data-bs-toggle="tab"
           href="#<?php echo $h($gateway['id']); ?>"
           role="tab">
            <img src="<?php echo $h($gateway['logo'] ?? ''); ?>" alt="<?php echo $h($gateway['label'] ?? ''); ?>"/>
        </a>
    </li>
    <?php endforeach; ?>
</ul>
<?php else: ?>
<div class="client-payment-gateway__provider">
    <?php if (!empty($gateways[0]['logo'])): ?>
        <img src="<?php echo $h($gateways[0]['logo']); ?>" alt="<?php echo $h($gateways[0]['label'] ?? ''); ?>"/>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="tab-content" id="paymentgwaycontent">
    <?php if ($showGatewayTabs): ?>
        <?php foreach ($gateways as $index => $gateway): ?>
        <div class="tab-pane <?php echo ($gateway['id'] === $primaryGateway || ($index === 0 && $primaryGateway === null)) ? 'show active' : 'fade'; ?>"
             id="<?php echo $h($gateway['id']); ?>"
             role="tabpanel"
             aria-labelledby="<?php echo $h($gateway['id']); ?>-tab">
            <?php echo $gatewaySections[$gateway['id']] ?? ''; ?>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="tab-pane show active" id="stripe" role="tabpanel" aria-labelledby="stripe-tab">
            <?php echo $gatewaySections[$primaryGateway] ?? ($gatewaySections['stripe'] ?? ''); ?>
        </div>
    <?php endif; ?>
</div>
</div>

<div class="client-payment-footer">
    <div class="client-payment-footer__row client-payment-footer__row--top">
        <div class="client-payment-footer__section client-payment-footer__secure">
            <strong><?php echo $lbl('Your payment is secure.', 'Your payment is secure.'); ?></strong>
            <p><?php echo $lbl('All data is encrypted, and your card details are never stored on our servers.', 'All data is encrypted, and your card details are never stored on our servers.'); ?></p>
        </div>
        <div class="client-payment-footer__section client-payment-footer__badge">
            <strong><?php echo $lbl('System secure by', 'System secure by'); ?></strong>
            <img src="<?php echo $h(rtrim((string) ($url ?? ''), '/') . '/assets/images/comodo-ssl.png'); ?>" alt="Comodo SSL"/>
        </div>
    </div>
    <div class="client-payment-footer__row client-payment-footer__row--bottom">
        <div class="client-payment-footer__section client-payment-footer__accept">
            <span><?php echo $lbl('We accecpt', 'We accept'); ?></span>
            <img src="<?php echo $h(rtrim((string) ($url ?? ''), '/') . '/assets/images/gatways.png'); ?>" alt="Payment gateways"/>
        </div>
    </div>
</div>
