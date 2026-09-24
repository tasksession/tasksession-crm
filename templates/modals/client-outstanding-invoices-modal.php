<?php
if (!isset($outstandingInvoicesPayload) || !is_array($outstandingInvoicesPayload)) {
    return;
}
if (!isset($lang) || !is_array($lang)) {
    $lang = [];
}

require_once __DIR__ . '/../../includes/client_payment_gateway_registry.php';
$showPayAll = client_payment_has_bulk_gateway();

$L = $lang;
$h = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$lbl = static function (string $key, string $fallback) use ($L, $h): string {
    return $h(isset($L[$key]) ? (string) $L[$key] : $fallback);
};

$payload = $outstandingInvoicesPayload;
$outstandingModalAutoShow = $outstandingModalAutoShow ?? true;
$clientName = $payload['client_name'] ?? '';
$totalCount = (int) ($payload['total_count'] ?? 0);
$remainingCount = (int) ($payload['remaining_count'] ?? 0);
$currencyTotals = $payload['currency_totals'] ?? [];
$displayGroups = $payload['display_groups'] ?? [];
$dismissUrl = rtrim((string) ($url ?? ''), '/') . '/ajax/dismiss-outstanding-invoices-modal.php';
$invoicesBase = rtrim((string) ($url ?? ''), '/') . '/client/invoices';
$outstandingModalRestoreOnPaymentClose = $outstandingModalRestoreOnPaymentClose ?? true;

$intro = sprintf(
    $L['Outstanding invoices intro'] ?? 'Hi %s, here\'s an overview of your currently unpaid invoices.',
    $clientName
);

$summaryCountLabel = sprintf(
    $L['Unpaid invoices count label'] ?? '%d Unpaid invoices',
    $totalCount
);
?>
<div id="clientOutstandingInvoicesModal"
     class="modal fade client-outstanding-invoices-modal"
     tabindex="-1"
     aria-labelledby="clientOutstandingInvoicesModalLabel"
     aria-hidden="true"
     data-bs-backdrop="static"
     data-bs-keyboard="false"
     data-dismiss-url="<?php echo $h($dismissUrl); ?>"
     data-auto-show="<?php echo !empty($outstandingModalAutoShow) ? '1' : '0'; ?>"
     data-restore-on-payment-close="<?php echo !empty($outstandingModalRestoreOnPaymentClose) ? '1' : '0'; ?>">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable client-outstanding-invoices-modal__dialog">
        <div class="modal-content client-outstanding-invoices-modal__content">
            <button type="button"
                    class="btn-close client-outstanding-invoices-modal__close"
                    data-bs-dismiss="modal"
                    aria-label="<?php echo $lbl('Close', 'Close'); ?>">
                <?php echo ts_icon('close'); ?>
            </button>

            <div class="modal-body client-outstanding-invoices-modal__body">
                <div class="client-outstanding-invoices-modal__hero text-center">
                    <div class="client-outstanding-invoices-modal__icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="0.5" stroke="currentColor">
                            <path fill="currentColor" d="M3.5 5A2.5 2.5 0 0 1 6 2.5h10A2.5 2.5 0 0 1 18.5 5v5.5a.5.5 0 0 1-1 0V5A1.5 1.5 0 0 0 16 3.5H6A1.5 1.5 0 0 0 4.5 5v14.382a.5.5 0 0 0 .724.447l1-.5a1.5 1.5 0 0 1 1.57.142l.906.679a.5.5 0 0 0 .6 0l.862-.647a1.5 1.5 0 0 1 1.672-.086l.673.404a.5.5 0 1 1-.514.858l-.674-.404a.5.5 0 0 0-.557.028l-.862.647a1.5 1.5 0 0 1-1.8 0l-.906-.68a.5.5 0 0 0-.523-.046l-1 .5A1.5 1.5 0 0 1 3.5 19.382z"></path>
                            <path fill="currentColor" d="M6.5 7a.5.5 0 0 1 .5-.5h6.5a.5.5 0 0 1 0 1H7a.5.5 0 0 1-.5-.5m0 3a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 0 1H7a.5.5 0 0 1-.5-.5m0 3a.5.5 0 0 1 .5-.5h3.5a.5.5 0 0 1 0 1H7a.5.5 0 0 1-.5-.5m0 3a.5.5 0 0 1 .5-.5h3.5a.5.5 0 0 1 0 1H7a.5.5 0 0 1-.5-.5m11-1.5a3 3 0 1 0 0 6a3 3 0 0 0 0-6m-4 3a4 4 0 1 1 8 0a4 4 0 0 1-8 0m5.666-1.229a.5.5 0 0 1 0 .708l-2.104 2.103l-1.228-1.228a.5.5 0 0 1 .707-.708l.521.522l1.397-1.397a.5.5 0 0 1 .707 0"></path>
                        </svg>
                    </div>
                    <h5 class="client-outstanding-invoices-modal__title" id="clientOutstandingInvoicesModalLabel">
                        <?php echo $lbl('Outstanding Invoices', 'Outstanding invoices'); ?>
                    </h5>
                    <p class="client-outstanding-invoices-modal__intro"><?php echo $h($intro); ?></p>
                </div>

                <div class="client-outstanding-invoices-modal__summary text-center">
                    <div class="client-outstanding-invoices-modal__summary-count"><?php echo $h($summaryCountLabel); ?></div>
                    <?php if (!empty($currencyTotals)): ?>
                        <div class="client-outstanding-invoices-modal__summary-currencies">
                            <?php foreach ($currencyTotals as $index => $currencyTotal): ?>
                                <?php if ($index > 0): ?>
                                    <span class="client-outstanding-invoices-modal__currency-sep" aria-hidden="true">&bull;</span>
                                <?php endif; ?>
                                <span class="client-outstanding-invoices-modal__currency-chip">
                                    <?php echo $h($currencyTotal['summary_formatted'] ?? ''); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="client-outstanding-invoices-modal__groups">
                    <?php
                    $displayInvoices = [];
                    foreach ($displayGroups as $group) {
                        foreach ($group['invoices'] as $invoice) {
                            $displayInvoices[] = $invoice;
                        }
                    }
                    ?>
                    <?php if (!empty($displayInvoices)): ?>
                        <div class="client-outstanding-invoices-modal__table-wrap">
                            <table class="client-outstanding-invoices-modal__table">
                                <thead>
                                    <tr>
                                        <th><?php echo $lbl('Invoice', 'Invoice'); ?></th>
                                        <th><?php echo $lbl('Due Date', 'Due date'); ?></th>
                                        <th><?php echo $lbl('Amount', 'Amount'); ?></th>
                                        <th><?php echo $lbl('Actions', 'Actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($displayInvoices as $invoice): ?>
                                        <tr>
                                            <td data-label="<?php echo $lbl('Invoice', 'Invoice'); ?>">
                                                <span class="client-outstanding-invoices-modal__invoice-no"><?php echo $h($invoice['display_number'] ?? ''); ?></span>
                                            </td>
                                            <td data-label="<?php echo $lbl('Due Date', 'Due date'); ?>">
                                                <?php if (!empty($invoice['is_overdue'])): ?>
                                                    <?php
                                                    $overdueDays = (int) ($invoice['overdue_days'] ?? 0);
                                                    if ($overdueDays === 1) {
                                                        $overdueBadgeText = sprintf(
                                                            $L['Outstanding invoice overdue badge singular'] ?? 'Overdue | %d day',
                                                            $overdueDays
                                                        );
                                                    } else {
                                                        $overdueBadgeText = sprintf(
                                                            $L['Outstanding invoice overdue badge plural'] ?? 'Overdue | %d days',
                                                            max(1, $overdueDays)
                                                        );
                                                    }
                                                    $overdueParts = explode('|', $overdueBadgeText, 2);
                                                    $overdueLabel = trim($overdueParts[0] ?? 'Overdue');
                                                    $overdueDetail = trim($overdueParts[1] ?? $overdueBadgeText);
                                                    ?>
                                                    <span class="client-outstanding-invoices-modal__overdue-badge">
                                                        <span class="client-outstanding-invoices-modal__overdue-badge-label"><?php echo $h($overdueLabel); ?></span>
                                                        <span class="client-outstanding-invoices-modal__overdue-badge-sep" aria-hidden="true">|</span>
                                                        <span class="client-outstanding-invoices-modal__overdue-badge-days"><?php echo $h($overdueDetail); ?></span>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="client-outstanding-invoices-modal__due-date"><?php echo $h($invoice['deadline_formatted'] ?? '—'); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="<?php echo $lbl('Amount', 'Amount'); ?>">
                                                <span class="client-outstanding-invoices-modal__amount"><?php echo $h($invoice['amount_formatted'] ?? ''); ?></span>
                                            </td>
                                            <td data-label="">
                                                <?php
                                                $payUrl = $invoice['pay_url'] ?? ($invoicesBase . '?invoice_id=' . (int) ($invoice['id'] ?? 0));
                                                $payTarget = strpos($payUrl, '/pay/') !== false ? '_blank' : '_self';
                                                $invoiceProjectId = (int) ($invoice['p_id'] ?? 0);
                                                ?>
                                                <div class="client-outstanding-invoices-modal__actions">
                                                    <button type="button"
                                                            class="client-outstanding-invoices-modal__action-link client-invoice-view-trigger"
                                                            data-invoice-id="<?php echo (int) ($invoice['id'] ?? 0); ?>">
                                                        <?php echo $lbl('View', 'View'); ?>
                                                    </button>
                                                    <?php if ($invoiceProjectId > 0): ?>
                                                        <button type="button"
                                                                class="client-outstanding-invoices-modal__action-link client-outstanding-invoices-modal__action-link--pay client-pay-now-trigger"
                                                                data-project-id="<?php echo $invoiceProjectId; ?>"
                                                                data-milestone-id="<?php echo (int) ($invoice['id'] ?? 0); ?>">
                                                            <?php echo $lbl('Make Payment', 'Pay now'); ?>
                                                        </button>
                                                    <?php else: ?>
                                                        <a href="<?php echo $h($payUrl); ?>"
                                                           target="<?php echo $h($payTarget); ?>"
                                                           class="client-outstanding-invoices-modal__action-link client-outstanding-invoices-modal__action-link--pay">
                                                            <?php echo $lbl('Make Payment', 'Pay now'); ?>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($remainingCount > 0): ?>
                    <p class="client-outstanding-invoices-modal__more text-center">
                        <?php echo $h(sprintf($L['More outstanding invoices'] ?? '+ %d more outstanding invoices', $remainingCount)); ?>
                    </p>
                <?php endif; ?>

                <div class="client-outstanding-invoices-modal__footer text-center">
                    <?php if (!empty($showPayAll)): ?>
                        <button type="button" class="btn client-outstanding-invoices-modal__cta client-outstanding-invoices-modal__cta--pay-all client-pay-all-trigger">
                            <?php echo $lbl('Pay all invoices', 'Pay all invoices'); ?>
                        </button>
                    <?php endif; ?>
                    <a href="<?php echo $h($invoicesBase . '?status=0'); ?>" class="btn outine client-outstanding-invoices-modal__cta-secondary">
                        <?php echo $lbl('View All Invoices', 'View all invoices'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
