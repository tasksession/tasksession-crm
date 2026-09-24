<?php
if (!isset($lang) || !is_array($lang)) {
    $lang = [];
}
?>
<div id="edit-milestone1" class="modal fade client-invoice-view-modal" tabindex="-1" aria-labelledby="clientInvoiceViewModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content client-invoice-view-modal__content">
            <div class="text-center py-4 text-muted client-invoice-view-modal__loading d-none">
                <?php echo htmlspecialchars($lang['Loading'] ?? 'Loading...', ENT_QUOTES, 'UTF-8'); ?>
            </div>
        </div>
    </div>
</div>

<!-- Project invoice: Mark as Paid confirm (admin/staff Pay now) -->
<div class="modal fade" id="invoicePayNowConfirmModal" tabindex="-1" aria-labelledby="invoicePayNowConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="invoicePayNowConfirmModalLabel">
                    <?php echo htmlspecialchars($lang['Mark as Paid'] ?? 'Mark as Paid', ENT_QUOTES, 'UTF-8'); ?>
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="<?php echo htmlspecialchars($lang['Close'] ?? 'Close', ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo function_exists('ts_icon') ? ts_icon('close') : '&times;'; ?>
                </button>
            </div>
            <div class="modal-body font-size-16 text-center pd-30">
                <?php echo htmlspecialchars(
                    $lang['Are you sure you want to mark this invoice as paid?']
                        ?? 'Are you sure you want to mark this invoice as paid?',
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
            </div>
            <div class="modal-footer justify-content-center">
                <form method="post" action="" id="invoicePayNowMarkPaidForm" class="d-flex col-gap-10 align-items-center m-0">
                    <input type="hidden" name="invoice_id" id="invoicePayNowInvoiceId" value="" />
                    <button type="button" class="outline-btn m-0" data-bs-dismiss="modal">
                        <?php echo htmlspecialchars($lang['Cancel'] ?? 'Cancel', ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                    <button type="submit" name="mark_as_paid" value="1" class="btn btn-success m-0">
                        <?php echo htmlspecialchars($lang['Mark as Paid'] ?? 'Mark as Paid', ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
